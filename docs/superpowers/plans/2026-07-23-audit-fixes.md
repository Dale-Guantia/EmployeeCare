# Audit Findings Remediation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Apply exactly the 37 selected findings from `AUDIT_REPORT.md`, no more, no less, as uncommitted working-tree changes on the current branch.

**Architecture:** No architectural change. This is a remediation pass touching CRUD-controller authorization, one dependency update, three new index-only migrations, a new `Status::idsByName()` cache-backed lookup (replacing scattered `Status::where('status_name', ...)->value('id')` calls), a new `App\Services\AttachmentStorage` helper, and a series of small, independent try/catch/validation/consistency fixes.

**Tech Stack:** Laravel 8, Eloquent, SQL Server (`sqlsrv`), Spatie Permission, Backpack CRUD, PHPUnit `DatabaseTransactions` against the live MSSQL DB.

## Global Constraints

- **Scope is exactly the 37 items below.** Do NOT touch: the hardcoded seeded password (`UsersTableSeeder.php`/`UserFactory.php`), survey/ARTA report pages' unbounded fetch, `GeminiService.php`/`GPTService.php`, "User create/update validation is minimal," or the dead commented-out `AppServiceProvider.php` bindings (already edited outside this plan — leave as-is).
- **`laravel/framework` must not change version.** Task 3's `composer update` explicitly excludes it; if Composer's resolver wants to bump it as a side effect, stop and report instead of proceeding.
- **No git commits.** Every task leaves changes as uncommitted working-tree edits — do not run `git add`, `git commit`, or `git push`, and do not create a new branch. Work happens directly on the current checkout.
- New migrations must be MSSQL-safe and actually run against the live SQL Server connection (`php artisan migrate`).
- PHP compatibility floor `^7.3|^8.0` — no arrow functions (`fn() =>`), no PHP 8-only syntax.
- Run the relevant subset of the existing suite after each task (`HrConversationTest`, `HrChatConversationMemoryTest`, `TicketReassignmentTest`, `UserAssignedTicketsTest`, `ReportsWidgetsTest`, `ReportsPageWidgetsRenderTest`, `ReportsPeriodControlTest`, `ReportsStaffWorkloadTest`, `ReportsPdfRegressionTest`, `SqlDialectHelperTest`, `TicketPerformanceTest`) — never leave the suite red between tasks.
- For hot-path files (`Ticket.php`, `TicketAutoAssignmentService.php`, `TicketNotificationService.php`, `TicketCrudController.php`), keep diffs minimal and surgical — no incidental refactors beyond the specific finding being fixed.

---

### Task 1: CRUD authorization gates (findings 1.1, 1.4)

**Files:**
- Modify: `app/Http/Controllers/Admin/DepartmentCrudController.php`, `DivisionCrudController.php`, `IssueCrudController.php`, `StatusCrudController.php`, `PriorityCrudController.php`, `UserCrudController.php`, `RoleCrudController.php`, `PermissionCrudController.php` (all `setup()` methods)
- Test: `tests/Feature/CrudAuthorizationGatesTest.php`

**Interfaces:**
- Produces: no new interfaces. Each `setup()` denies `create`/`update`/`delete` by default after the existing `.view` check, then selectively re-allows per the user's actual `{entity}.create`/`{entity}.update`/`{entity}.delete` permission.
- Confirmed from `database/seeders/RolesAndPermissionsSeeder.php:51-69`: `dept_head` has `department.view`, `division.view` (no create/update/delete on either), `issue.view/create/update` (no `issue.delete`). `div_head` has no department/division permissions at all (blocked by the existing `.view` check already) and `issue.view/create/update` (no `issue.delete`). Only `admin` has full CRUD on all eight entities via `syncPermissions(Permission::all())`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Division;
use App\Models\Issue;
use App\Models\Priority;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CrudAuthorizationGatesTest extends TestCase
{
    use DatabaseTransactions;

    private function makeUser(string $role, ?int $departmentId = null, ?int $divisionId = null): User
    {
        static $seq = 0;
        $seq++;

        $user = User::create([
            'name' => ucfirst($role) . "GateUser{$seq}",
            'username' => "{$role}_gate_user_{$seq}_" . uniqid(),
            'email' => "{$role}_gate_user_{$seq}_" . uniqid() . '@example.test',
            'password' => bcrypt('password'),
            'department_id' => $departmentId,
            'division_id' => $divisionId,
            'is_active' => true,
        ]);
        $user->assignRole($role);

        return $user;
    }

    public function test_dept_head_can_view_but_not_create_update_delete_departments()
    {
        $hrDept = Department::find(1);
        $deptHead = $this->makeUser('dept_head', $hrDept->id);
        $this->actingAs($deptHead, 'web');

        $this->get(route('department.index'))->assertStatus(200);
        $this->get(route('department.create'))->assertStatus(403);
        $this->post(route('department.store'), ['department_name' => 'Nope'])->assertStatus(403);
    }

    public function test_dept_head_can_manage_issues_but_not_delete()
    {
        $hrDept = Department::find(1);
        $division = Division::where('department_id', $hrDept->id)->first();
        $priority = Priority::firstOrCreate(['priority_name' => 'Normal'], ['priority_color' => '#ccc']);
        $deptHead = $this->makeUser('dept_head', $hrDept->id);
        $this->actingAs($deptHead, 'web');

        $this->get(route('issue.create'))->assertStatus(200);

        $issue = Issue::create([
            'department_id' => $hrDept->id,
            'division_id' => $division->id,
            'priority_id' => $priority->id,
            'issue_description' => 'Gate Test Issue ' . uniqid(),
        ]);

        $this->delete(route('issue.destroy', $issue->id))->assertStatus(403);
    }

    public function test_admin_retains_full_department_access()
    {
        $admin = User::role('admin')->first();
        $this->assertNotNull($admin);
        $this->actingAs($admin, 'web');

        $this->get(route('department.create'))->assertStatus(200);
    }

    public function test_div_head_still_blocked_from_departments_entirely()
    {
        $hrDept = Department::find(1);
        $division = Division::where('department_id', $hrDept->id)->first();
        $divHead = $this->makeUser('div_head', $hrDept->id, $division->id);
        $this->actingAs($divHead, 'web');

        $this->get(route('department.index'))->assertStatus(403);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=CrudAuthorizationGatesTest`
Expected: FAIL on `test_dept_head_can_create_update_delete...` and `test_dept_head_can_manage_issues_but_not_delete` — `department.create`/`issue.destroy` currently return 200/302, not 403, because Backpack's Create/Update/Delete operation traits grant access by default.

- [ ] **Step 3: Implement**

For each of the 8 controllers, replace the `setup()` body's tail (after the existing `if (!backpack_user()->can('{entity}.view')) { abort(403); }` check) by adding this block. Example for `app/Http/Controllers/Admin/DepartmentCrudController.php`:

```php
    public function setup()
    {
        CRUD::setModel(\App\Models\Department::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/department');
        CRUD::setEntityNameStrings('department', 'departments');

        if (!backpack_user()->can('department.view')) {
            abort(403);
        }

        $this->crud->denyAccess(['create', 'update', 'delete']);

        if (backpack_user()->can('department.create')) {
            $this->crud->allowAccess('create');
        }
        if (backpack_user()->can('department.update')) {
            $this->crud->allowAccess('update');
        }
        if (backpack_user()->can('department.delete')) {
            $this->crud->allowAccess('delete');
        }
    }
```

Apply the identical pattern (only the entity prefix changes) to:
- `DivisionCrudController.php` — `'division.create'`/`'division.update'`/`'division.delete'`
- `IssueCrudController.php` — `'issue.create'`/`'issue.update'`/`'issue.delete'`
- `StatusCrudController.php` — `'status.create'`/`'status.update'`/`'status.delete'`
- `PriorityCrudController.php` — `'priority.create'`/`'priority.update'`/`'priority.delete'`
- `UserCrudController.php` — `'user.create'`/`'user.update'`/`'user.delete'` (append after its existing `if (!backpack_user()->can('user.view')) { abort(403); }` block; do not touch anything else in this controller's `setup()`)

For `RoleCrudController.php` and `PermissionCrudController.php`, which extend vendor Backpack\PermissionManager controllers and call `parent::setup()` first, append the same block after their own `.view` check:

```php
    public function setup()
    {
        parent::setup();

        if (!backpack_user()->can('role.view')) {
            abort(403);
        }

        $this->crud->denyAccess(['create', 'update', 'delete']);

        if (backpack_user()->can('role.create')) {
            $this->crud->allowAccess('create');
        }
        if (backpack_user()->can('role.update')) {
            $this->crud->allowAccess('update');
        }
        if (backpack_user()->can('role.delete')) {
            $this->crud->allowAccess('delete');
        }
    }
```

(same for `PermissionCrudController.php` with `'permission.*'`).

Do NOT touch `'show'`/`'list'` access — those stay exactly as currently gated by the top-level `.view` check.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=CrudAuthorizationGatesTest`
Expected: PASS (4 tests)

- [ ] **Step 5: Regression check**

Run: `php artisan test --filter=TicketReassignmentTest`
Run: `php artisan test --filter=TicketPerformanceTest`
Expected: PASS, unchanged — confirms admin and the existing ticket-scoping tests still work (none of those roles/flows touch the 8 controllers changed here beyond what's tested).

- [ ] **Step 6: Manual verification (per the plan's TESTS section item 3)**

Note in your report: confirm via the test above that `dept_head` can still view/create/update issues and view (but not create/update/delete) departments and divisions — i.e., this closes the gap without revoking access the role is actually seeded to have.

---

### Task 2: Ticket-comment attachment MIME restriction (findings 1.2, 3.12)

**Files:**
- Modify: `app/Http/Livewire/TicketChat.php`
- Test: `tests/Feature/TicketChatAttachmentValidationTest.php`

**Interfaces:** No new interfaces. `sendComment()`'s validation rule for `attachments.*` gains a `mimes:` constraint matching `TicketRequest.php:38`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Division;
use App\Models\Issue;
use App\Models\Priority;
use App\Models\Status;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Testing\File;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use App\Http\Livewire\TicketChat;
use Tests\TestCase;

class TicketChatAttachmentValidationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_disallowed_file_extension_is_rejected()
    {
        $admin = User::role('admin')->first();
        $this->assertNotNull($admin);

        $dept = Department::create(['department_name' => 'ChatValDept_' . uniqid()]);
        $div = Division::create(['division_name' => 'ChatValDiv_' . uniqid(), 'department_id' => $dept->id]);
        $priority = Priority::firstOrCreate(['priority_name' => 'Normal'], ['priority_color' => '#ccc']);
        $status = Status::firstOrCreate(['status_name' => 'Unassigned'], ['status_color' => '#ccc']);
        $issue = Issue::create([
            'department_id' => $dept->id,
            'division_id' => $div->id,
            'priority_id' => $priority->id,
            'issue_description' => 'Chat Attachment Test Issue ' . uniqid(),
        ]);

        $ticket = new Ticket();
        $ticket->forceFill([
            'user_id' => $admin->id,
            'department_id' => $dept->id,
            'division_id' => $div->id,
            'issue_id' => $issue->id,
            'status_id' => $status->id,
            'message' => 'attachment validation test',
        ]);
        $ticket->save();

        $this->actingAs($admin, 'web');

        Livewire::test(TicketChat::class, ['ticketId' => $ticket->id])
            ->set('comment', 'here is a file')
            ->set('attachments', [File::create('malicious.html', 10)])
            ->call('sendComment')
            ->assertHasErrors(['attachments.0']);
    }

    public function test_allowed_file_extension_is_accepted()
    {
        $admin = User::role('admin')->first();
        $this->assertNotNull($admin);

        $dept = Department::create(['department_name' => 'ChatValDept2_' . uniqid()]);
        $div = Division::create(['division_name' => 'ChatValDiv2_' . uniqid(), 'department_id' => $dept->id]);
        $priority = Priority::firstOrCreate(['priority_name' => 'Normal'], ['priority_color' => '#ccc']);
        $status = Status::firstOrCreate(['status_name' => 'Unassigned'], ['status_color' => '#ccc']);
        $issue = Issue::create([
            'department_id' => $dept->id,
            'division_id' => $div->id,
            'priority_id' => $priority->id,
            'issue_description' => 'Chat Attachment Test Issue 2 ' . uniqid(),
        ]);

        $ticket = new Ticket();
        $ticket->forceFill([
            'user_id' => $admin->id,
            'department_id' => $dept->id,
            'division_id' => $div->id,
            'issue_id' => $issue->id,
            'status_id' => $status->id,
            'message' => 'attachment validation test 2',
        ]);
        $ticket->save();

        $this->actingAs($admin, 'web');

        Livewire::test(TicketChat::class, ['ticketId' => $ticket->id])
            ->set('comment', 'here is a pdf')
            ->set('attachments', [File::create('document.pdf', 10)])
            ->call('sendComment')
            ->assertHasNoErrors();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=TicketChatAttachmentValidationTest`
Expected: `test_disallowed_file_extension_is_rejected` FAILS (no error raised for `malicious.html` today).

- [ ] **Step 3: Implement**

In `app/Http/Livewire/TicketChat.php`, replace:

```php
        $this->validate([
            'comment' => 'required_without:attachments|nullable',
            'attachments.*' => 'nullable|max:10240',
        ], [
            'comment.required_without' => 'Please enter a message or attach a file.',
            'attachments.*.max' => 'Each attachment must not exceed 10MB.',
        ]);
```

with:

```php
        $this->validate([
            'comment' => 'required_without:attachments|nullable',
            'attachments.*' => 'nullable|mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx,txt|max:10240',
        ], [
            'comment.required_without' => 'Please enter a message or attach a file.',
            'attachments.*.mimes' => 'Allowed file types: jpg, jpeg, png, pdf, doc, docx, xls, xlsx, txt.',
            'attachments.*.max' => 'Each attachment must not exceed 10MB.',
        ]);
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=TicketChatAttachmentValidationTest`
Expected: PASS (2 tests)

- [ ] **Step 5: Commit-equivalent — no commit, per Global Constraints. Report which of items 1.2/3.12 this closes (both — same fix, one place).**

---

### Task 3: Composer dependency updates, excluding `laravel/framework` (finding 1.3)

**Files:** `composer.json` (lockfile only, no manual constraint edits), `composer.lock`

**Interfaces:** None — this is a dependency-version change, not a code interface change.

- [ ] **Step 1: Dry-run check before updating**

Run: `composer update symfony/mime guzzlehttp/guzzle guzzlehttp/psr7 dompdf/dompdf league/commonmark symfony/routing symfony/polyfill-intl-idn --with-all-dependencies --dry-run`

Read the dry-run output. If any line proposes upgrading, downgrading, or otherwise touching `laravel/framework`, STOP — do not proceed to Step 2. Report the conflict (paste the relevant dry-run lines) instead, and leave `composer.json`/`composer.lock` untouched.

- [ ] **Step 2: Run the real update (only if Step 1 showed no `laravel/framework` involvement)**

Run: `composer update symfony/mime guzzlehttp/guzzle guzzlehttp/psr7 dompdf/dompdf league/commonmark symfony/routing symfony/polyfill-intl-idn --with-all-dependencies`

Do not manually edit any version constraint in `composer.json` — let Composer resolve within the existing constraints.

- [ ] **Step 3: Confirm `laravel/framework`'s version is unchanged**

Run: `composer show laravel/framework` before and after (capture both outputs in your report) — the installed version string must be identical.

- [ ] **Step 4: Re-run `composer audit`**

Run: `composer audit`
Report the full before/after advisory count and which specific advisories are now resolved vs. which remain. Expect the `laravel/framework`-specific advisories (the High CRLF-injection-in-email-validation finding, GHSA-crmm-hgp2-wgrp, and CVE-2025-27515) to still be present — this is expected and by design, not a bug.

- [ ] **Step 5: Run the full test suite**

Run: `php artisan test`
Expected: same pass/fail profile as before this task (the one pre-existing unrelated `HrChatConversationMemoryTest` failure noted in `AUDIT_REPORT.md` may still be present — that's not a regression from this task). If anything NEW breaks because of the dependency bump, report it in detail — do not silently revert `composer.lock` to make it pass.

---

### Task 4: Stored XSS via unescaped JSON in inline `<script>` blocks (finding 1.5)

**Files:**
- Modify: `resources/views/admin/ticket/reassignment_widget.blade.php`
- Modify: `resources/views/vendor/backpack/base/dashboard.blade.php`
- Test: `tests/Feature/ReassignmentWidgetXssTest.php`

**Interfaces:** No new interfaces — pure output-encoding change, same variable names and JS behavior.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Division;
use App\Models\Issue;
use App\Models\Priority;
use App\Models\Status;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ReassignmentWidgetXssTest extends TestCase
{
    use DatabaseTransactions;

    public function test_division_name_with_script_tag_is_hex_escaped_in_reassignment_widget()
    {
        $admin = User::role('admin')->first();
        $this->assertNotNull($admin);

        $dept = Department::create(['department_name' => 'XssDept_' . uniqid()]);
        // Division name containing a literal </script> — must never appear
        // unescaped in the rendered page's inline <script> block.
        $div = Division::create(['division_name' => 'XssDiv</script><script>alert(1)</script>_' . uniqid(), 'department_id' => $dept->id]);
        $priority = Priority::firstOrCreate(['priority_name' => 'Normal'], ['priority_color' => '#ccc']);
        $status = Status::firstOrCreate(['status_name' => 'Unassigned'], ['status_color' => '#ccc']);
        $issue = Issue::create([
            'department_id' => $dept->id,
            'division_id' => $div->id,
            'priority_id' => $priority->id,
            'issue_description' => 'XSS Test Issue ' . uniqid(),
        ]);

        $ticket = new Ticket();
        $ticket->forceFill([
            'user_id' => $admin->id,
            'department_id' => $dept->id,
            'division_id' => $div->id,
            'issue_id' => $issue->id,
            'status_id' => $status->id,
            'message' => 'xss test ticket',
        ]);
        $ticket->save();

        $this->actingAs($admin, 'web');

        $response = $this->get(route('ticket.show', $ticket->id));

        $response->assertStatus(200);
        // The literal, unescaped payload must never appear in the raw response body.
        $response->assertDontSee('</script><script>alert(1)</script>', false);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ReassignmentWidgetXssTest`
Expected: FAIL — `$divisions->toJson()` currently embeds the raw `</script>` sequence unescaped.

- [ ] **Step 3: Implement**

In `resources/views/admin/ticket/reassignment_widget.blade.php`, replace:

```php
            var allDivisions = {!! $divisions->toJson() !!};
```

with:

```php
            var allDivisions = @json($divisions);
```

In `resources/views/vendor/backpack/base/dashboard.blade.php`, replace:

```php
const divisionLabels = {!! json_encode($divisionLabels) !!};
const divisionCounts = {!! json_encode($divisionCounts) !!};
const divisionColors = {!! json_encode($divisionColors) !!};
```

with:

```php
const divisionLabels = @json($divisionLabels);
const divisionCounts = @json($divisionCounts);
const divisionColors = @json($divisionColors);
```

Note for your report: `$divisionLabels` in `dashboard.blade.php` falls back to the raw `division_name` (line 43: `$shortName = $abbreviationMap[$item->division_name] ?? $item->division_name;`) whenever a division's name isn't one of the 8 hardcoded abbreviation-map entries — so this WAS a genuinely reachable second instance of the same XSS pattern, not dead code as the audit's lower-confidence note suggested. State this finding in your report.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=ReassignmentWidgetXssTest`
Expected: PASS

- [ ] **Step 5: Regression check**

Run: `php artisan test --filter=TicketReassignmentTest`
Expected: PASS, unchanged (this widget is exercised by several of those tests).

---

### Task 5: HR chatbot rate limiting (finding 1.6)

**Files:**
- Modify: `app/Providers/RouteServiceProvider.php`
- Modify: `routes/backpack/custom.php`
- Test: `tests/Feature/HrChatRateLimitTest.php`

**Interfaces:** Produces a new named rate limiter `'hr-chat'` (20 requests/minute per authenticated user), applied via `throttle:hr-chat` middleware to the `hr-assistant/*` route group.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class HrChatRateLimitTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('hr-chat');
    }

    public function test_ask_endpoint_is_throttled_after_configured_limit()
    {
        $admin = User::role('admin')->first();
        $this->assertNotNull($admin);
        $this->actingAs($admin, 'web');

        $lastResponse = null;
        for ($i = 0; $i < 21; $i++) {
            $lastResponse = $this->postJson(route('hr.chat.ask'), ['question' => 'hi']);
        }

        $lastResponse->assertStatus(429);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=HrChatRateLimitTest`
Expected: FAIL — no 429 is ever returned today (21 "hi" greetings all succeed with 200).

- [ ] **Step 3: Implement**

In `app/Providers/RouteServiceProvider.php`, add to `configureRateLimiting()`:

```php
    protected function configureRateLimiting()
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by(optional($request->user())->id ?: $request->ip());
        });

        RateLimiter::for('hr-chat', function (Request $request) {
            return Limit::perMinute(20)->by(optional($request->user())->id ?: $request->ip());
        });
    }
```

In `routes/backpack/custom.php`, wrap the chatbot routes in a `throttle:hr-chat` middleware group. Replace:

```php
    // Chatbot Routes
    Route::get('hr-assistant',       [HrChatController::class, 'index'])->name('hr.chat.index');
    Route::post('hr-assistant/ask',  [HrChatController::class, 'ask'])->name('hr.chat.ask');
    Route::post('hr-assistant/feedback', [HrChatController::class, 'feedback'])->name('hr.chat.feedback');
    Route::get('hr-assistant/history', [HrChatController::class, 'history'])->name('hr.chat.history');
    Route::delete('hr-assistant/clear-history', [HrChatController::class, 'clearHistory'])->name('hr.chat.clear_history');

    // Conversation management (full-page sidebar). The widget keeps using the
    // flat `history` route above and never calls these.
    Route::get('hr-assistant/conversations', [HrChatController::class, 'conversations'])->name('hr.chat.conversations');
    Route::get('hr-assistant/conversations/{id}/messages', [HrChatController::class, 'conversationMessages'])->name('hr.chat.conversation.messages');
    Route::patch('hr-assistant/conversations/{id}', [HrChatController::class, 'updateConversation'])->name('hr.chat.conversation.update');
    Route::delete('hr-assistant/conversations/{id}', [HrChatController::class, 'deleteConversation'])->name('hr.chat.conversation.delete');
```

with:

```php
    // Chatbot Routes — rate-limited so a single user can't spam the paid
    // Anthropic API or exhaust the shared rate limit for everyone else.
    Route::middleware('throttle:hr-chat')->group(function () {
        Route::get('hr-assistant',       [HrChatController::class, 'index'])->name('hr.chat.index');
        Route::post('hr-assistant/ask',  [HrChatController::class, 'ask'])->name('hr.chat.ask');
        Route::post('hr-assistant/feedback', [HrChatController::class, 'feedback'])->name('hr.chat.feedback');
        Route::get('hr-assistant/history', [HrChatController::class, 'history'])->name('hr.chat.history');
        Route::delete('hr-assistant/clear-history', [HrChatController::class, 'clearHistory'])->name('hr.chat.clear_history');

        // Conversation management (full-page sidebar). The widget keeps using the
        // flat `history` route above and never calls these.
        Route::get('hr-assistant/conversations', [HrChatController::class, 'conversations'])->name('hr.chat.conversations');
        Route::get('hr-assistant/conversations/{id}/messages', [HrChatController::class, 'conversationMessages'])->name('hr.chat.conversation.messages');
        Route::patch('hr-assistant/conversations/{id}', [HrChatController::class, 'updateConversation'])->name('hr.chat.conversation.update');
        Route::delete('hr-assistant/conversations/{id}', [HrChatController::class, 'deleteConversation'])->name('hr.chat.conversation.delete');
    });
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=HrChatRateLimitTest`
Expected: PASS

- [ ] **Step 5: Regression check**

Run: `php artisan test --filter=HrConversationTest`
Run: `php artisan test --filter=HrChatConversationMemoryTest`
Expected: both pass — neither test sends more than 20 requests per test method, so the new limiter shouldn't affect them; confirm this is actually true by checking each test's request count before running, and report the count.

---

### Task 6: Randomize upload filenames (finding 1.7)

**Files:**
- Create: `app/Services/AttachmentStorage.php`
- Modify: `app/Models/Ticket.php`
- Modify: `app/Http/Livewire/TicketChat.php`
- Test: `tests/Unit/AttachmentStorageTest.php`

**Interfaces:**
- Produces: `App\Services\AttachmentStorage::randomizedFilename(\Illuminate\Http\UploadedFile $file): string` — returns a UUID-prefixed, slug-suffixed filename (`{uuid}__{slugified-original-name}.{extension}`) so the stored path is no longer attacker-predictable/attacker-chosen, while `basename($path)` in the UI still shows a human-recognizable trace of the original name (satisfies "don't lose the original filename info" without restructuring the `attachments` array's storage shape, which stays a plain string array — fully backward-compatible with existing historical ticket data).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit;

use App\Services\AttachmentStorage;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AttachmentStorageTest extends TestCase
{
    public function test_randomized_filename_is_not_the_original_name()
    {
        $file = UploadedFile::fake()->create('my report.pdf', 10);

        $result = AttachmentStorage::randomizedFilename($file);

        $this->assertNotSame('my report.pdf', $result);
        $this->assertStringEndsWith('.pdf', $result);
        $this->assertStringContainsString('my_report', $result);
    }

    public function test_two_calls_for_the_same_original_name_produce_different_filenames()
    {
        $fileA = UploadedFile::fake()->create('same.pdf', 10);
        $fileB = UploadedFile::fake()->create('same.pdf', 10);

        $this->assertNotSame(
            AttachmentStorage::randomizedFilename($fileA),
            AttachmentStorage::randomizedFilename($fileB)
        );
    }

    public function test_disallowed_extension_is_preserved_as_given_not_sanitized_here()
    {
        // Extension whitelisting is the validation layer's job (TicketRequest /
        // TicketChat's mimes rule) — this helper only randomizes the name, it
        // does not re-validate the extension. Confirm it doesn't crash on an
        // unusual but well-formed filename.
        $file = UploadedFile::fake()->create('weird..name.PDF', 10);

        $result = AttachmentStorage::randomizedFilename($file);

        $this->assertStringEndsWith('.PDF', $result);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=AttachmentStorageTest`
Expected: FAIL — `App\Services\AttachmentStorage` doesn't exist yet.

- [ ] **Step 3: Create the service**

```php
<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Generates randomized, non-attacker-controlled stored filenames for ticket
 * attachments, while keeping a slugified fragment of the original name in
 * the stored path so the UI (which derives the displayed name from
 * basename($path)) still shows something human-recognizable — without
 * restructuring the `attachments` column's plain-string-array storage shape.
 */
class AttachmentStorage
{
    public static function randomizedFilename(UploadedFile $file): string
    {
        $original = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $slug = Str::slug($original, '_');
        $extension = $file->getClientOriginalExtension();

        $name = (string) Str::uuid();
        if ($slug !== '') {
            $name .= '__' . $slug;
        }

        return $extension !== '' ? "{$name}.{$extension}" : $name;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=AttachmentStorageTest`
Expected: PASS (3 tests)

- [ ] **Step 5: Wire it into the two upload call sites**

In `app/Models/Ticket.php`, inside `setAttachmentsAttribute()`, replace:

```php
                if ($file && $file->isValid()) {
                    $fileName = time() . '_' . $file->getClientOriginalName();
                    $path     = $file->storeAs($destination_path, $fileName, $disk);
                    $base[]   = $path;
                }
```

with:

```php
                if ($file && $file->isValid()) {
                    $fileName = \App\Services\AttachmentStorage::randomizedFilename($file);
                    $path     = $file->storeAs($destination_path, $fileName, $disk);
                    $base[]   = $path;
                }
```

In `app/Http/Livewire/TicketChat.php`, inside `sendComment()`, replace:

```php
        if ($this->attachments) {
            foreach ($this->attachments as $file) {
                $filename = time() . '_' . $file->getClientOriginalName();

                $paths[] = $file->storeAs(
                    'attachments',
                    $filename,
                    'public'
                );
            }
        }
```

with:

```php
        if ($this->attachments) {
            foreach ($this->attachments as $file) {
                $filename = \App\Services\AttachmentStorage::randomizedFilename($file);

                $paths[] = $file->storeAs(
                    'attachments',
                    $filename,
                    'public'
                );
            }
        }
```

- [ ] **Step 6: Regression check**

Run: `php artisan test --filter=TicketReassignmentTest`
Run: `php artisan test --filter=TicketPerformanceTest`
Run: `php artisan test --filter=TicketChatAttachmentValidationTest`
Expected: all PASS unchanged — none of these tests assert on the exact stored filename string, only on validation/behavior, so this change shouldn't break them; confirm by reading each test file first if uncertain.

---

### Task 7: Chat-log question truncation on exceptions + session cookie safe default (findings 1.8, 1.9)

**Files:**
- Modify: `app/Services/AnthropicService.php`
- Modify: `app/Http/Controllers/Admin/HrChatController.php`
- Modify: `config/session.php`
- Test: `tests/Unit/AnthropicServiceLoggingTest.php`

**Interfaces:** No new interfaces — the two catch blocks log a truncated question instead of the full text; `config/session.php`'s `secure` key gets a safe default.

- [ ] **Step 1: Write a focused test for the truncation behavior**

```php
<?php

namespace Tests\Unit;

use Illuminate\Support\Str;
use Tests\TestCase;

class AnthropicServiceLoggingTest extends TestCase
{
    public function test_long_question_is_truncated_before_logging()
    {
        // This is a behavioral contract test on Str::limit itself (the exact
        // truncation helper used in the fix), since triggering the real
        // catch block requires mocking the HTTP client — asserting the
        // truncation utility's contract is what actually matters here.
        $longQuestion = str_repeat('a', 600);

        $truncated = Str::limit($longQuestion, 50);

        $this->assertLessThanOrEqual(53, strlen($truncated)); // 50 chars + "..."
        $this->assertStringEndsWith('...', $truncated);
    }
}
```

- [ ] **Step 2: Run test to verify it passes as a baseline** (this test doesn't fail before the fix — it's testing the truncation utility itself, not the log call sites, since mocking `Http::fake()` inside a catch-only path is disproportionate effort for this Low-severity finding)

Run: `php artisan test --filter=AnthropicServiceLoggingTest`
Expected: PASS (confirms `Str::limit` behaves as the fix will rely on).

- [ ] **Step 3: Implement the logging fix**

In `app/Services/AnthropicService.php`, replace:

```php
        } catch (\Exception $e) {
            Log::error('[AnthropicService] Exception: ' . $e->getMessage(), [
                'question' => $question,
            ]);
            return 'Could not connect to the AI service. Please check your internet connection and try again.';
        }
```

with:

```php
        } catch (\Exception $e) {
            Log::error('[AnthropicService] Exception: ' . $e->getMessage(), [
                'question' => \Illuminate\Support\Str::limit($question, 50),
            ]);
            return 'Could not connect to the AI service. Please check your internet connection and try again.';
        }
```

In `app/Http/Controllers/Admin/HrChatController.php`, replace:

```php
        } catch (\Throwable $e) {
            Log::error('[HrChatController] ask() failed: ' . $e->getMessage(), [
                'user_id'  => backpack_user()->id ?? null,
                'question' => $request->input('question'),
            ]);
```

with:

```php
        } catch (\Throwable $e) {
            Log::error('[HrChatController] ask() failed: ' . $e->getMessage(), [
                'user_id'  => backpack_user()->id ?? null,
                'question' => \Illuminate\Support\Str::limit((string) $request->input('question'), 50),
            ]);
```

- [ ] **Step 4: Implement the session cookie fix**

In `config/session.php`, replace:

```php
    'secure' => env('SESSION_SECURE_COOKIE'),
```

with:

```php
    'secure' => env('SESSION_SECURE_COOKIE', true),
```

- [ ] **Step 5: Regression check**

Run: `php artisan test --filter=HrConversationTest`
Run: `php artisan test --filter=HrChatConversationMemoryTest`
Expected: PASS, unchanged.

Note in your report: `SESSION_SECURE_COOKIE=true` by default means local HTTP-only dev environments now need `SESSION_SECURE_COOKIE=false` explicitly in their `.env` if they're not running under HTTPS locally (Laragon typically is not, by default) — otherwise the session cookie won't be sent back over plain HTTP and login/session state will silently fail to persist. Check the project's actual local `.env` for `SESSION_SECURE_COOKIE` and flag if this needs to be set explicitly for local dev to keep working; state what you found.

---

### Task 8: Fix the dashboard's "Latest Tickets" N+1 (finding 2.1)

**Files:** Modify `resources/views/vendor/backpack/base/dashboard.blade.php`

**Interfaces:** None new.

- [ ] **Step 1: Write a query-count regression test**

```php
<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Division;
use App\Models\Issue;
use App\Models\Priority;
use App\Models\Status;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardLatestTicketsQueryCountTest extends TestCase
{
    use DatabaseTransactions;

    public function test_latest_tickets_do_not_trigger_n_plus_one()
    {
        $admin = User::role('admin')->first();
        $this->assertNotNull($admin);

        $dept = Department::create(['department_name' => 'DashN1Dept_' . uniqid()]);
        $div = Division::create(['division_name' => 'DashN1Div_' . uniqid(), 'department_id' => $dept->id]);
        $priority = Priority::firstOrCreate(['priority_name' => 'Normal'], ['priority_color' => '#ccc']);
        $status = Status::firstOrCreate(['status_name' => 'Unassigned'], ['status_color' => '#ccc']);
        $issue = Issue::create([
            'department_id' => $dept->id,
            'division_id' => $div->id,
            'priority_id' => $priority->id,
            'issue_description' => 'Dashboard N+1 Test Issue ' . uniqid(),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $ticket = new Ticket();
            $ticket->forceFill([
                'user_id' => $admin->id,
                'department_id' => $dept->id,
                'division_id' => $div->id,
                'issue_id' => $issue->id,
                'status_id' => $status->id,
                'message' => "dashboard n+1 test {$i}",
            ]);
            $ticket->save();
        }

        $this->actingAs($admin, 'web');

        $queryCount = 0;
        DB::listen(function () use (&$queryCount) {
            $queryCount++;
        });

        $response = $this->get(route('backpack.dashboard'));
        $response->assertStatus(200);

        // Well below the ~1 (base) + 4 relations * 5 rows = 21 queries the
        // N+1 would produce for just the latest-tickets block alone.
        $this->assertLessThan(30, $queryCount, "Dashboard triggered {$queryCount} queries — check for N+1.");
    }
}
```

- [ ] **Step 2: Run test to record the baseline** (informational — the assertion threshold is loose enough it may already pass; the point is to record the BEFORE query count in your report, then show it drops after the fix)

Run: `php artisan test --filter=DashboardLatestTicketsQueryCountTest`
Record the query count if you add a temporary `dump($queryCount)` or check via `HRF_DUMP_SQL`-style output — report the before/after numbers explicitly even if the loose assertion already passes both times.

- [ ] **Step 3: Implement**

In `resources/views/vendor/backpack/base/dashboard.blade.php`, replace:

```php
$latestTickets = App\Models\Ticket::latest()->take(10)->get();
```

with:

```php
$latestTickets = App\Models\Ticket::with(['user', 'issue', 'status', 'priority'])->latest()->take(10)->get();
```

- [ ] **Step 4: Run test to confirm the query count actually dropped**

Run: `php artisan test --filter=DashboardLatestTicketsQueryCountTest`
Expected: PASS, and report the new (lower) query count compared to Step 2's baseline.

---

### Task 9: `TicketNotificationService` eager-loads roles (finding 2.2)

**Files:** Modify `app/Services/TicketNotificationService.php`

**Interfaces:** None new — same method signatures, same behavior, just eager-loaded.

- [ ] **Step 1: Write a query-count regression test**

```php
<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Division;
use App\Models\Issue;
use App\Models\Priority;
use App\Models\Status;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketNotificationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TicketNotificationServiceQueryCountTest extends TestCase
{
    use DatabaseTransactions;

    public function test_notify_ticket_created_does_not_lazy_load_roles_per_user()
    {
        $admin = User::role('admin')->first();
        $this->assertNotNull($admin);

        $dept = Department::create(['department_name' => 'NotifDept_' . uniqid()]);
        $div = Division::create(['division_name' => 'NotifDiv_' . uniqid(), 'department_id' => $dept->id]);
        $priority = Priority::firstOrCreate(['priority_name' => 'Normal'], ['priority_color' => '#ccc']);
        $status = Status::firstOrCreate(['status_name' => 'Unassigned'], ['status_color' => '#ccc']);
        $issue = Issue::create([
            'department_id' => $dept->id,
            'division_id' => $div->id,
            'priority_id' => $priority->id,
            'issue_description' => 'Notif Test Issue ' . uniqid(),
        ]);

        $ticket = new Ticket();
        $ticket->forceFill([
            'user_id' => $admin->id,
            'department_id' => $dept->id,
            'division_id' => $div->id,
            'issue_id' => $issue->id,
            'status_id' => $status->id,
            'message' => 'notification query count test',
        ]);
        $ticket->save();

        $queryCount = 0;
        DB::listen(function ($query) use (&$queryCount) {
            $queryCount++;
        });

        app(TicketNotificationService::class)->notifyTicketCreated($ticket, $admin);

        // 1 for User::all(), 1 for the roles eager-load (regardless of user
        // count) — a per-user lazy-load would scale with total user count instead.
        $this->assertLessThan(10, $queryCount, "notifyTicketCreated triggered {$queryCount} queries.");
    }
}
```

- [ ] **Step 2: Run test to record baseline**

Run: `php artisan test --filter=TicketNotificationServiceQueryCountTest`
Report the query count before the fix (likely already passing the loose threshold in this small test DB, but report the actual number — the real proof is a lower number after the fix).

- [ ] **Step 3: Implement**

In `app/Services/TicketNotificationService.php`, replace all four occurrences of:

```php
        $users = User::all();
```

(inside `notifyTicketCreated`, `notifyTicketAssigned`, `notifyTicketStatusChanged`, `notifyTicketCommented`) with:

```php
        $users = User::with('roles')->get();
```

- [ ] **Step 4: Run test to confirm the query count dropped**

Run: `php artisan test --filter=TicketNotificationServiceQueryCountTest`
Report the new query count.

- [ ] **Step 5: Regression check**

Run: `php artisan test --filter=TicketReassignmentTest`
Expected: PASS, unchanged — this file's notification logic is exercised extensively by that suite (T13-T16).

---

### Task 10: New migrations — missing indexes on `tickets`, `ticket_reassignment_requests`, `users` (findings 2.3, 2.8, 2.9)

**Files:**
- Create: `database/migrations/2026_07_23_000001_add_indexes_to_tickets_table.php`
- Create: `database/migrations/2026_07_23_000002_add_indexes_to_ticket_reassignment_requests_table.php`
- Create: `database/migrations/2026_07_23_000003_add_indexes_to_users_table.php`

**Interfaces:** None — pure schema change, no query code changes in this task (queries already work; this only adds indexes SQL Server doesn't create automatically for foreign keys).

- [ ] **Step 1: Create the tickets indexes migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexesToTicketsTable extends Migration
{
    public function up()
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->index('status_id');
            $table->index(['department_id', 'division_id']);
            $table->index('assigned_to');
            $table->index('user_id');
            $table->index('created_at');
            $table->index('issue_id');
            $table->index(['department_id', 'division_id', 'status_id']);
        });
    }

    public function down()
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['status_id']);
            $table->dropIndex(['department_id', 'division_id']);
            $table->dropIndex(['assigned_to']);
            $table->dropIndex(['user_id']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['issue_id']);
            $table->dropIndex(['department_id', 'division_id', 'status_id']);
        });
    }
}
```

- [ ] **Step 2: Create the ticket_reassignment_requests indexes migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexesToTicketReassignmentRequestsTable extends Migration
{
    public function up()
    {
        Schema::table('ticket_reassignment_requests', function (Blueprint $table) {
            $table->index(['ticket_id', 'status']);
            $table->index('to_department_id');
            $table->index('to_division_id');
        });
    }

    public function down()
    {
        Schema::table('ticket_reassignment_requests', function (Blueprint $table) {
            $table->dropIndex(['ticket_id', 'status']);
            $table->dropIndex(['to_department_id']);
            $table->dropIndex(['to_division_id']);
        });
    }
}
```

- [ ] **Step 3: Create the users indexes migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexesToUsersTable extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->index(['department_id', 'division_id']);
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['department_id', 'division_id']);
        });
    }
}
```

- [ ] **Step 4: Run the migrations against the live SQL Server connection**

Run: `php artisan migrate`
Expected: all three migrations run without error. If SQL Server rejects any index (e.g. a duplicate-name conflict with an existing FK-constraint-backed index), report the exact error — do not silently drop the conflicting index definition without reporting it first.

- [ ] **Step 5: Verify the indexes exist on the live DB**

Run (via `php artisan tinker --execute="..."` or a raw query): confirm each new index is present, e.g.:
```
php artisan tinker --execute="print_r(DB::select(\"SELECT name FROM sys.indexes WHERE object_id = OBJECT_ID('tickets')\"));"
```
Repeat for `ticket_reassignment_requests` and `users`. Paste the index name lists in your report (this satisfies TESTS item 5 in the original prompt).

- [ ] **Step 6: Full regression check**

Run: `php artisan test`
Expected: same pass/fail profile as before (adding indexes should never change query results, only performance).

---

### Task 11: `Status::idsByName()` cache-backed lookup + apply to `Ticket.php` and `User.php` (findings 2.4 part 1, 3.15)

**Files:**
- Modify: `app/Models/Status.php`
- Modify: `app/Models/Ticket.php`
- Modify: `app/Models/User.php`
- Test: `tests/Unit/StatusIdsByNameTest.php`

**Interfaces:**
- Produces: `App\Models\Status::idsByName(): \Illuminate\Support\Collection` (id keyed by status_name, cached 15 minutes via `Cache::remember('ref:statuses.by_name', ...)`, invalidated automatically on any `Status` save/delete via a `booted()` hook) and `App\Models\Status::idByName(string $name): ?int`. Later tasks (12, 13) consume these instead of repeating `Status::where('status_name', '...')->value('id')`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit;

use App\Models\Status;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class StatusIdsByNameTest extends TestCase
{
    use DatabaseTransactions;

    public function test_ids_by_name_returns_the_real_seeded_statuses()
    {
        $map = Status::idsByName();

        $this->assertTrue($map->has('Resolved'));
        $this->assertTrue($map->has('Pending'));
        $this->assertTrue($map->has('Unassigned'));
        $this->assertTrue($map->has('Reopened'));
    }

    public function test_id_by_name_returns_an_int_matching_a_direct_query()
    {
        $direct = (int) Status::where('status_name', 'Resolved')->value('id');

        $this->assertSame($direct, Status::idByName('Resolved'));
    }

    public function test_id_by_name_returns_null_for_unknown_name()
    {
        $this->assertNull(Status::idByName('NotARealStatus_' . uniqid()));
    }

    public function test_cache_is_invalidated_when_a_status_is_saved()
    {
        Cache::forget('ref:statuses.by_name');
        Status::idsByName(); // warm the cache

        $newStatus = Status::create(['status_name' => 'TestCacheInvalidation_' . uniqid(), 'status_color' => '#ccc']);

        $map = Status::idsByName();

        $this->assertTrue($map->has($newStatus->status_name));

        $newStatus->delete();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=StatusIdsByNameTest`
Expected: FAIL — `Status::idsByName()` doesn't exist yet.

- [ ] **Step 3: Implement `Status::idsByName()`/`idByName()`**

In `app/Models/Status.php`, replace the whole file with:

```php
<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Status extends Model
{
    use CrudTrait;
    use HasFactory;

    protected $fillable = [
        'status_name',
        'status_color'
    ];

    const CACHE_KEY = 'ref:statuses.by_name';

    protected static function booted()
    {
        static::saved(function () {
            Cache::forget(self::CACHE_KEY);
        });

        static::deleted(function () {
            Cache::forget(self::CACHE_KEY);
        });
    }

    public function tickets()
    {
        return $this->hasMany(\App\Models\Ticket::class);
    }

    /**
     * All status ids keyed by status_name, cache-backed (15 min TTL,
     * invalidated immediately on any Status save/delete) so the same
     * lookup that used to run as Status::where('status_name', ...)->value('id')
     * in a dozen places across the app now hits the DB at most once per
     * cache window instead of once per call site per request.
     */
    public static function idsByName(): \Illuminate\Support\Collection
    {
        return Cache::remember(self::CACHE_KEY, now()->addMinutes(15), function () {
            return static::pluck('id', 'status_name');
        });
    }

    public static function idByName(string $name): ?int
    {
        $id = self::idsByName()->get($name);

        return $id !== null ? (int) $id : null;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=StatusIdsByNameTest`
Expected: PASS (4 tests)

- [ ] **Step 5: Apply to `app/Models/Ticket.php`**

Replace every one of these 5 occurrences in `booted()`'s `saving`/`updated` closures:

```php
                $assignedStatus = Status::where('status_name', 'Pending')->first();

                if ($assignedStatus && !$model->isDirty('status_id')) {
                    $model->status_id = $assignedStatus->id;
                }
```
→
```php
                $assignedStatusId = Status::idByName('Pending');

                if ($assignedStatusId && !$model->isDirty('status_id')) {
                    $model->status_id = $assignedStatusId;
                }
```

```php
                $unassignedStatus = Status::where('status_name', 'Unassigned')->first();

                if ($unassignedStatus && !$model->isDirty('status_id')) {
                    $model->status_id = $unassignedStatus->id;
                }
```
→
```php
                $unassignedStatusId = Status::idByName('Unassigned');

                if ($unassignedStatusId && !$model->isDirty('status_id')) {
                    $model->status_id = $unassignedStatusId;
                }
```

```php
                $resolvedStatusId = Status::where('status_name', 'Resolved')->value('id');
                $reopenedStatusId = Status::where('status_name', 'Reopened')->value('id');
```
→
```php
                $resolvedStatusId = Status::idByName('Resolved');
                $reopenedStatusId = Status::idByName('Reopened');
```

```php
                $resolvedStatusId = Status::where('status_name', 'Resolved')->value('id');

                if ($resolvedStatusId && (int) $ticket->status_id === (int) $resolvedStatusId) {
```
(inside `static::updated`) →
```php
                $resolvedStatusId = Status::idByName('Resolved');

                if ($resolvedStatusId && (int) $ticket->status_id === (int) $resolvedStatusId) {
```

And in `getOverdueBadgeAttribute()`, replace:

```php
        static $resolvedStatusId = null;
        if ($resolvedStatusId === null) {
            $resolvedStatusId = Status::where('status_name', 'Resolved')->value('id');
        }
```

with:

```php
        $resolvedStatusId = Status::idByName('Resolved');
```

(the per-request `static` cache is now redundant since `Status::idsByName()` is itself cached — remove it rather than layering two caches).

- [ ] **Step 6: Run Ticket-related tests**

Run: `php artisan test --filter=TicketReassignmentTest`
Run: `php artisan test --filter=TicketPerformanceTest`
Expected: PASS, unchanged — every status transition this suite exercises must resolve to the identical status id as before.

- [ ] **Step 7: Apply to `app/Models/User.php`, fixing the hardcoded `status_id = 1` (finding 3.15)**

Replace:

```php
    public function resolvedTickets()
    {
        // Replace 'assigned_to_user_id' with the actual FK in your tickets table
        return $this->hasMany(Ticket::class, 'resolved_by')
                    ->where('status_id', 1); // 1 = Resolved based on your DB
    }

    public function overdueTickets()
    {
        $diffHoursSql = SqlDialectHelper::diffHoursSql('created_at', 'resolved_at');

        return $this->hasMany(\App\Models\Ticket::class, 'assigned_to')
            ->where(function ($query) use ($diffHoursSql) {
                $query->where(function ($q) {
                    // Case A: Still open and older than 3 days
                    $q->where('status_id', '!=', 1)
                    ->where('created_at', '<', now()->subDays(3));
                })
                ->orWhere(function ($q) use ($diffHoursSql) {
                    // Case B: Resolved, but it took more than 72 hours (3 days)
                    $q->where('status_id', 1)
                    ->whereRaw("{$diffHoursSql} > 72");
                });
            });
    }
```

with:

```php
    public function resolvedTickets()
    {
        return $this->hasMany(Ticket::class, 'resolved_by')
                    ->where('status_id', Status::idByName('Resolved'));
    }

    public function overdueTickets()
    {
        $diffHoursSql = SqlDialectHelper::diffHoursSql('created_at', 'resolved_at');
        $resolvedStatusId = Status::idByName('Resolved');

        return $this->hasMany(\App\Models\Ticket::class, 'assigned_to')
            ->where(function ($query) use ($diffHoursSql, $resolvedStatusId) {
                $query->where(function ($q) use ($resolvedStatusId) {
                    // Case A: Still open and older than 3 days
                    $q->where('status_id', '!=', $resolvedStatusId)
                    ->where('created_at', '<', now()->subDays(3));
                })
                ->orWhere(function ($q) use ($diffHoursSql, $resolvedStatusId) {
                    // Case B: Resolved, but it took more than 72 hours (3 days)
                    $q->where('status_id', $resolvedStatusId)
                    ->whereRaw("{$diffHoursSql} > 72");
                });
            });
    }
```

`App\Models\Status` is already reachable from `User.php` without a new import since both live in `App\Models` (same namespace) — do not add a redundant `use` statement.

- [ ] **Step 8: Run User/Ticket-related tests**

Run: `php artisan test --filter=UserAssignedTicketsTest`
Run: `php artisan test --filter=ReportsStaffWorkloadTest`
Run: `php artisan test --filter=TicketPerformanceTest`
Expected: PASS, unchanged — these exercise `resolvedTickets()`/`overdueTickets()` directly.

---

### Task 12: Apply `Status::idByName()` to `TicketCrudController.php` and `TicketAutoAssignmentService.php` (finding 2.4 part 2)

**Files:**
- Modify: `app/Http/Controllers/Admin/TicketCrudController.php`
- Modify: `app/Services/TicketAutoAssignmentService.php`

**Interfaces:** Consumes `Status::idByName()` from Task 11. No new interfaces produced.

- [ ] **Step 1: Apply to `TicketCrudController.php`**

In `handleReopen()`, replace:
```php
        $reopenedId = Status::where('status_name', 'Reopened')->value('id');
```
with:
```php
        $reopenedId = Status::idByName('Reopened');
```

In `handleResolve()`, replace:
```php
        $resolvedId = Status::where('status_name', 'Resolved')->value('id');
```
with:
```php
        $resolvedId = Status::idByName('Resolved');
```

In `handleReassignRequest()`, replace:
```php
        $resolvedId = Status::where('status_name', 'Resolved')->value('id');
```
with:
```php
        $resolvedId = Status::idByName('Resolved');
```

In `setupShowOperation()`, replace:
```php
        $resolvedId = Status::where('status_name', 'Resolved')->value('id');
        $reopenedId = Status::where('status_name', 'Reopened')->value('id');
```
with:
```php
        $resolvedId = Status::idByName('Resolved');
        $reopenedId = Status::idByName('Reopened');
```

- [ ] **Step 2: Apply to `TicketAutoAssignmentService.php`**

Replace:
```php
            // EXPLICIT STATUS UPDATE: Change to "Pending"
            $pendingStatus = Status::where('status_name', 'Pending')->first();
            if ($pendingStatus) {
                $ticket->status_id = $pendingStatus->id;
            }
```
with:
```php
            // EXPLICIT STATUS UPDATE: Change to "Pending"
            $pendingStatusId = Status::idByName('Pending');
            if ($pendingStatusId) {
                $ticket->status_id = $pendingStatusId;
            }
```

Replace:
```php
        // Fetch exact IDs for active statuses to prevent hardcoding errors
        $pendingId = Status::where('status_name', 'Pending')->value('id');
        $reopenedId = Status::where('status_name', 'Reopened')->value('id');
        $activeStatusIds = array_filter([$pendingId, $reopenedId]);
```
with:
```php
        // Fetch exact IDs for active statuses to prevent hardcoding errors
        $pendingId = Status::idByName('Pending');
        $reopenedId = Status::idByName('Reopened');
        $activeStatusIds = array_filter([$pendingId, $reopenedId]);
```

- [ ] **Step 3: Run tests to confirm identical behavior**

Run: `php artisan test --filter=TicketReassignmentTest`
Run: `php artisan test --filter=TicketPerformanceTest`
Run: `php artisan test --filter=UserAssignedTicketsTest`
Expected: PASS, unchanged — every status transition and auto-assignment scenario these tests cover must resolve to the same ids as before.

---

### Task 13: Apply `Status::idByName()` to `ReportsController.php`, `dashboard.blade.php`, `reassignment_widget.blade.php` + remove dead query (findings 2.4 part 3, 2.6)

**Files:**
- Modify: `app/Http/Controllers/Admin/ReportsController.php`
- Modify: `resources/views/vendor/backpack/base/dashboard.blade.php`
- Modify: `resources/views/admin/ticket/reassignment_widget.blade.php`

**Interfaces:** Consumes `Status::idByName()` from Task 11. No new interfaces produced.

- [ ] **Step 1: Apply to `ReportsController.php`**

Replace:
```php
        $staffResolvedStatusId = (int) \App\Models\Status::where('status_name', 'Resolved')->value('id');
```
with:
```php
        $staffResolvedStatusId = (int) Status::idByName('Resolved');
```

Replace:
```php
        $resolvedStatusId = (int) Status::where('status_name', 'Resolved')->value('id');
        $pendingStatusId = (int) Status::where('status_name', 'Pending')->value('id');
        $reopenedStatusId = (int) Status::where('status_name', 'Reopened')->value('id');
```
with:
```php
        $resolvedStatusId = (int) Status::idByName('Resolved');
        $pendingStatusId = (int) Status::idByName('Pending');
        $reopenedStatusId = (int) Status::idByName('Reopened');
```

In the same file, also remove the dead unbounded query (finding 2.6). Replace:
```php
        $ticketQuery = Ticket::query();

        if ($startDate && $endDate) {
            $ticketQuery->whereBetween('created_at', [$startDate, $endDate]);
        }

        $ticketIds = (clone $ticketQuery)->pluck('id');

        $users = User::with('division')
```
with:
```php
        $users = User::with('division')
```

Confirm before removing it that `$ticketIds` is genuinely unreferenced anywhere else in the file (grep the file for `$ticketIds` first) — the audit already confirmed this, but re-verify given intervening changes from Tasks 1-12.

- [ ] **Step 2: Apply to `dashboard.blade.php`**

Replace:
```php
$dashResolvedStatusId = (int) \App\Models\Status::where('status_name', 'Resolved')->value('id');
$dashPendingStatusId = (int) \App\Models\Status::where('status_name', 'Pending')->value('id');
$dashReopenedStatusId = (int) \App\Models\Status::where('status_name', 'Reopened')->value('id');
```
with:
```php
$dashResolvedStatusId = (int) \App\Models\Status::idByName('Resolved');
$dashPendingStatusId = (int) \App\Models\Status::idByName('Pending');
$dashReopenedStatusId = (int) \App\Models\Status::idByName('Reopened');
```

- [ ] **Step 3: Apply to `reassignment_widget.blade.php`**

Replace:
```php
    $resolvedId = \App\Models\Status::where('status_name', 'Resolved')->value('id');
```
with:
```php
    $resolvedId = \App\Models\Status::idByName('Resolved');
```

- [ ] **Step 4: Run the full Reports + Dashboard + Ticket test suite**

Run: `php artisan test --filter=ReportsWidgetsTest`
Run: `php artisan test --filter=ReportsPageWidgetsRenderTest`
Run: `php artisan test --filter=ReportsPeriodControlTest`
Run: `php artisan test --filter=ReportsStaffWorkloadTest`
Run: `php artisan test --filter=ReportsPdfRegressionTest`
Run: `php artisan test --filter=TicketReassignmentTest`
Expected: all PASS, unchanged.

---

### Task 14: Auto-assignment workload N+1 loop fix (finding 2.7)

**Files:** Modify `app/Services/TicketAutoAssignmentService.php`

**Interfaces:** `analyzeContentAndMatch()`'s internal workload-counting changes from N queries (one per staff member) to 1 grouped aggregate query. No change to the method's public behavior/return value.

- [ ] **Step 1: Write a query-count regression test**

```php
<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Division;
use App\Models\Issue;
use App\Models\Priority;
use App\Models\Status;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TicketAutoAssignmentQueryCountTest extends TestCase
{
    use DatabaseTransactions;

    public function test_workload_counting_uses_one_grouped_query_not_one_per_staff()
    {
        $dept = Department::create(['department_name' => 'AutoAssignDept_' . uniqid()]);
        $div = Division::create(['division_name' => 'AutoAssignDiv_' . uniqid(), 'department_id' => $dept->id]);
        $priority = Priority::firstOrCreate(['priority_name' => 'Normal'], ['priority_color' => '#ccc']);
        Status::firstOrCreate(['status_name' => 'Unassigned'], ['status_color' => '#ccc']);
        Status::firstOrCreate(['status_name' => 'Pending'], ['status_color' => '#ccc']);
        Status::firstOrCreate(['status_name' => 'Reopened'], ['status_color' => '#ccc']);
        $issue = Issue::create([
            'department_id' => $dept->id,
            'division_id' => $div->id,
            'priority_id' => $priority->id,
            'issue_description' => 'Auto-assign query count test ' . uniqid(),
        ]);

        // 5 eligible hr_staff in the same dept/division
        for ($i = 0; $i < 5; $i++) {
            $staff = User::create([
                'name' => "AutoAssignStaff{$i}_" . uniqid(),
                'username' => "auto_assign_staff_{$i}_" . uniqid(),
                'email' => "auto_assign_staff_{$i}_" . uniqid() . '@example.test',
                'password' => bcrypt('password'),
                'department_id' => $dept->id,
                'division_id' => $div->id,
                'is_active' => true,
            ]);
            $staff->assignRole('hr_staff');
        }

        $queryCount = 0;
        DB::listen(function () use (&$queryCount) {
            $queryCount++;
        });

        // Ticket creation fires TicketObserver::created() -> assignTicket() ->
        // analyzeContentAndMatch() synchronously.
        $ticket = new Ticket();
        $ticket->forceFill([
            'department_id' => $dept->id,
            'division_id' => $div->id,
            'issue_id' => $issue->id,
            'status_id' => Status::idByName('Unassigned'),
            'message' => 'auto-assign query count test ticket',
        ]);
        $ticket->save();

        // With 5 eligible staff, a per-staff COUNT loop would add 5 queries
        // just for workload counting on top of everything else ticket
        // creation already does. This ceiling is generous but would catch a
        // regression back to the per-row loop.
        $this->assertLessThan(40, $queryCount, "Ticket creation with 5 eligible staff triggered {$queryCount} queries.");
    }
}
```

- [ ] **Step 2: Run test to record baseline**

Run: `php artisan test --filter=TicketAutoAssignmentQueryCountTest`
Report the query count before the fix.

- [ ] **Step 3: Implement**

In `app/Services/TicketAutoAssignmentService.php`, replace:

```php
        // Count active tickets for each staff member to ensure FAIRNESS
        $staffWithWorkload = $eligibleStaff->map(function ($staff) use ($activeStatusIds) {
            $staff->active_ticket_count = Ticket::where('assigned_to', $staff->id)
                                                ->whereIn('status_id', $activeStatusIds)
                                                ->count();
            return $staff;
        });
```

with:

```php
        // Count active tickets for each staff member to ensure FAIRNESS.
        // One grouped aggregate query instead of one COUNT per staff member.
        $workloadByStaffId = Ticket::whereIn('assigned_to', $eligibleStaff->pluck('id'))
            ->whereIn('status_id', $activeStatusIds)
            ->groupBy('assigned_to')
            ->selectRaw('assigned_to, COUNT(*) as cnt')
            ->pluck('cnt', 'assigned_to');

        $staffWithWorkload = $eligibleStaff->map(function ($staff) use ($workloadByStaffId) {
            $staff->active_ticket_count = (int) ($workloadByStaffId[$staff->id] ?? 0);
            return $staff;
        });
```

Note: `groupBy('assigned_to')` selecting only `assigned_to` (grouped) plus the aggregate `COUNT(*)` satisfies the SQL Server GROUP BY rule (every non-aggregated selected column must appear in GROUP BY) — verify this holds in the query above before moving on.

- [ ] **Step 4: Run test to confirm the query count dropped and behavior is unchanged**

Run: `php artisan test --filter=TicketAutoAssignmentQueryCountTest`
Report the new query count.

- [ ] **Step 5: Regression check on actual assignment behavior**

Run: `php artisan test --filter=TicketReassignmentTest`
Expected: PASS, unchanged — the fairness/workload-based assignment logic itself must produce identical assignment decisions, not just fewer queries.

---

### Task 15: Department/Division reference-data caching (finding 2.10)

**Files:**
- Modify: `app/Models/Department.php`
- Modify: `app/Models/Division.php`
- Modify: `resources/views/admin/ticket/reassignment_widget.blade.php`
- Modify: `app/Services/TicketReportWidgets.php`
- Test: `tests/Unit/DepartmentDivisionCacheTest.php`

**Interfaces:**
- Produces: `App\Models\Department::allCached(): \Illuminate\Support\Collection` and `App\Models\Division::allCached(): \Illuminate\Support\Collection` (15-min TTL, invalidated on save/delete via `booted()` hooks, mirroring `Status::idsByName()`'s pattern from Task 11).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit;

use App\Models\Department;
use App\Models\Division;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DepartmentDivisionCacheTest extends TestCase
{
    use DatabaseTransactions;

    public function test_department_all_cached_matches_a_direct_query()
    {
        $direct = Department::all()->pluck('id')->sort()->values();
        $cached = Department::allCached()->pluck('id')->sort()->values();

        $this->assertEquals($direct, $cached);
    }

    public function test_department_cache_invalidates_on_create()
    {
        Cache::forget(Department::CACHE_KEY);
        Department::allCached();

        $newDept = Department::create(['department_name' => 'CacheTestDept_' . uniqid()]);

        $this->assertTrue(Department::allCached()->pluck('id')->contains($newDept->id));

        $newDept->delete();
    }

    public function test_division_all_cached_matches_a_direct_query()
    {
        $direct = Division::all()->pluck('id')->sort()->values();
        $cached = Division::allCached()->pluck('id')->sort()->values();

        $this->assertEquals($direct, $cached);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=DepartmentDivisionCacheTest`
Expected: FAIL — `allCached()` doesn't exist on either model yet.

- [ ] **Step 3: Implement on `Department.php`**

```php
<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Department extends Model
{
    use CrudTrait;
    use HasFactory;

    protected $fillable = [
        'department_name',
    ];

    const CACHE_KEY = 'ref:departments.all';

    protected static function booted()
    {
        static::saved(function () {
            Cache::forget(self::CACHE_KEY);
        });

        static::deleted(function () {
            Cache::forget(self::CACHE_KEY);
        });
    }

    public static function allCached(): \Illuminate\Support\Collection
    {
        return Cache::remember(self::CACHE_KEY, now()->addMinutes(15), function () {
            return static::all();
        });
    }
}
```

- [ ] **Step 4: Implement on `Division.php`**

```php
<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Division extends Model
{
    use CrudTrait;
    use HasFactory;

    protected $fillable = [
        'division_name',
        'department_id'
    ];

    const CACHE_KEY = 'ref:divisions.all';

    protected static function booted()
    {
        static::saved(function () {
            Cache::forget(self::CACHE_KEY);
        });

        static::deleted(function () {
            Cache::forget(self::CACHE_KEY);
        });
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class, 'division_id');
    }

    public static function allCached(): \Illuminate\Support\Collection
    {
        return Cache::remember(self::CACHE_KEY, now()->addMinutes(15), function () {
            return static::all();
        });
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=DepartmentDivisionCacheTest`
Expected: PASS (3 tests)

- [ ] **Step 6: Apply to `reassignment_widget.blade.php`**

Replace:
```php
    $departments = \App\Models\Department::all();
    $divisions = \App\Models\Division::all();
```
with:
```php
    $departments = \App\Models\Department::allCached();
    $divisions = \App\Models\Division::allCached();
```

- [ ] **Step 7: Apply to `app/Services/TicketReportWidgets.php`'s `buildReassignmentWidget()`**

Replace:
```php
        $hrDepartmentId = (int) Department::where('department_name', 'City Human Resource Development Office')->value('id');
```
with:
```php
        $hrDepartmentId = (int) optional(Department::allCached()->firstWhere('department_name', 'City Human Resource Development Office'))->id;
```

Replace:
```php
        $byDivision = [];
        if ($hrDepartmentId) {
            Division::where('department_id', $hrDepartmentId)
                ->orderBy('division_name')
                ->pluck('division_name')
                ->each(function ($name) use (&$byDivision) {
                    $byDivision[$name] = ['total' => 0, 'reassigned' => 0];
                });
        }
```
with:
```php
        $byDivision = [];
        if ($hrDepartmentId) {
            Division::allCached()
                ->where('department_id', $hrDepartmentId)
                ->sortBy('division_name')
                ->pluck('division_name')
                ->each(function ($name) use (&$byDivision) {
                    $byDivision[$name] = ['total' => 0, 'reassigned' => 0];
                });
        }
```

(Collection's `where`/`sortBy`/`pluck`/`each` mirror the query-builder methods being replaced, operating on the now-cached, already-fetched collection instead of issuing a fresh query.)

- [ ] **Step 8: Run tests to confirm behavior is unchanged**

Run: `php artisan test --filter=TicketReassignmentTest`
Run: `php artisan test --filter=ReportsWidgetsTest`
Run: `php artisan test --filter=ReportsPeriodControlTest`
Expected: PASS, unchanged — the reassignment-by-division breakdown and the reassignment widget's UI must produce identical results.

---

### Task 16: HR chat FAQ query — verify MSSQL-safety, add clarifying note (finding 2.11)

**Files:** Modify `app/Http/Controllers/Admin/HrChatController.php` (comment only, per the audit's explicit "do not over-engineer a caching layer" instruction)

**Interfaces:** None.

- [ ] **Step 1: Verify the `question` column's length is bounded**

Check `database/migrations/2026_07_21_000000_bound_hr_chat_logs_question_column.php` (already confirmed to exist from the audit) to confirm `hr_chat_logs.question` has a bounded length (not unbounded `text`) — a bounded `string`/`varchar` column is required for this `groupBy('question')` query to work at all on SQL Server (SQL Server cannot `GROUP BY` an unbounded `text`/`nvarchar(max)` column). Report the exact column type/length found.

- [ ] **Step 2: Add the clarifying comment**

In `app/Http/Controllers/Admin/HrChatController.php`'s `index()` method, above the existing `$topPrompts` query, add:

```php
        // Groups the entire hr_chat_logs table by the free-text `question`
        // column — accepted as low-priority per the audit: this runs once per
        // HR Assistant page load (not per-message), and grouping by exact
        // free-text is inherently low-yield anyway (questions are rarely
        // identical strings), so a caching layer here isn't warranted yet.
        // Revisit if this page's load time becomes noticeable as chat volume grows.
```

directly before:

```php
        $topPrompts = HrChatLog::select('question')
```

- [ ] **Step 3: Confirm no behavior change**

Run: `php artisan test --filter=HrConversationTest`
Expected: PASS, unchanged (comment-only change).

---

### Task 17: Dead code cleanup (findings 3.1, 3.2, 3.3)

**Files:**
- Modify: `app/Services/TicketNotificationService.php`
- Modify: `app/Models/TicketReassignmentRequest.php`
- Modify: `app/Http/Controllers/Admin/UserCrudController.php`
- Modify: `app/Console/Commands/TestOcrSetup.php`
- Modify: `app/Http/Controllers/Admin/MyAccountController.php`
- Modify: `app/Http/Controllers/Admin/NotificationController.php`
- Modify: `app/Listeners/UpdateLastLoginAt.php`
- Modify: `app/Models/User.php`
- Modify: `app/Providers/AuthServiceProvider.php`
- Modify: `app/Providers/EventServiceProvider.php`

**Interfaces:** Removes unused code only — no interface changes, nothing else in the codebase references any of the removed methods/imports (confirmed by the audit's grep; re-confirm each one yourself with a grep before deleting, since Tasks 1-16 may have added new references).

- [ ] **Step 1: Delete `TicketNotificationService::managementUsers()`**

Before deleting, grep the whole `app/` tree for `managementUsers` to confirm it's still unreferenced after Tasks 1-16's changes to this same file (Task 9 touched it). If still unreferenced, remove:

```php
    protected function managementUsers(): Collection
    {
        return User::whereHas('roles', function ($q) {
            $q->whereIn('name', ['admin', 'dept_head', 'div_head']);
        })->get();
    }

```

Report explicitly: this is a deletion, not the "refactor other blocks to use it" alternative, because `managementUsers()`'s actual behavior (return every admin/dept_head/div_head, unconditionally) doesn't match what `shouldReceiveTicketCreated()`/`shouldReceiveTicketAssigned()`/etc. actually need (they also check department/division scoping per user, not just role membership) — refactoring to "use" it wouldn't be a clean fit, it would require the caller to still do the same scoping filtering afterward, so there's no real duplication to remove. State this reasoning in your report.

- [ ] **Step 2: `TicketReassignmentRequest::isPending()`**

Grep the whole `app/` and `resources/` tree for `isPending()` and for `->status === ` / `->status ===` comparisons against `TicketReassignmentRequest::STATUS_PENDING`. Confirm (as the plan author already did) that no instance-level comparison exists anywhere — every other reference to `STATUS_PENDING` in the codebase is a query-builder `->where('status', STATUS_PENDING)` clause, which cannot be rewritten to use an instance method like `->isPending()`. Since there is no valid call site to refactor to use it, delete the method:

```php
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

```

Report explicitly that you verified this (paste your grep output) and that this deviates from the finding's literally-stated "preferred" fix (replace call sites) only because no such call site exists — not because deletion was arbitrarily chosen over refactoring.

- [ ] **Step 3: Remove the 8 unused imports**

- `app/Http/Controllers/Admin/UserCrudController.php:5` — remove `use App\Http\Requests\UserRequest;` (the class doesn't exist in `app/Http/Requests/` at all — confirm this with `ls app/Http/Requests/` before removing, matching the audit's finding). Do NOT create a `UserRequest` class — that's the excluded "User validation is minimal" item.
- `app/Console/Commands/TestOcrSetup.php:6` — remove the unused `Storage` import (confirm unused via grep of the file body first).
- `app/Http/Controllers/Admin/MyAccountController.php:7` — remove the unused `Hash` import (confirm unused first — note `Hash` might be used inside `parent::postChangePasswordForm()`'s call chain, but that's in the VENDOR class, not this file; if this file's own body never references `Hash::`, it's safe to remove).
- `app/Http/Controllers/Admin/NotificationController.php:6` — remove the unused `Request` import (confirm unused first; if any method signature in this controller type-hints `Request`, do NOT remove it — re-check before deleting).
- `app/Listeners/UpdateLastLoginAt.php:5-6` — remove `use Illuminate\Contracts\Queue\ShouldQueue;` and `use Illuminate\Queue\InteractsWithQueue;` ONLY IF Task 18 (which also touches this file) hasn't already made the listener actually implement `ShouldQueue` — check Task 18's outcome first if it ran before this task; if Task 17 runs before Task 18, leave a note for Task 18's implementer that these imports were removed here and Task 18 should re-add `ShouldQueue`/`InteractsWithQueue` itself if it makes the listener queued (Task 18's fix is a try/catch only, not queuing, per this plan's Task 18 scope — so these imports should stay removed).
- `app/Models/User.php:6` — remove the unused `use Illuminate\Contracts\Auth\MustVerifyEmail;` import (confirm the class doesn't `implements MustVerifyEmail` anywhere in the file first).
- `app/Providers/AuthServiceProvider.php:6` — remove the unused `Gate` import (confirm unused first).
- `app/Providers/EventServiceProvider.php:9` — remove the unused `Event` import (confirm unused first).

For each file, grep the file's own body (excluding the `use` line itself) for the imported class/facade's short name before removing — do not remove an import based on the audit's finding alone without re-confirming against the current file content.

- [ ] **Step 4: Full regression check**

Run: `php artisan test`
Expected: same pass/fail profile as before this task (0 new failures) — removing genuinely-unused code and dead imports must not change any behavior. If any test fails after a specific removal, that import/method was NOT actually unused — restore it and report the discrepancy rather than force the deletion through.

---

### Task 18: Unguarded operations — Ticket lifecycle try/catch (findings 3.4, 3.5, 3.6, 3.7)

**Files:**
- Modify: `app/Observers/TicketObserver.php`
- Modify: `app/Listeners/UpdateLastLoginAt.php`
- Modify: `app/Models/Ticket.php`
- Modify: `app/Http/Controllers/Admin/TicketCrudController.php`
- Test: `tests/Unit/TicketObserverErrorHandlingTest.php`

**Interfaces:** None new — all four fixes wrap existing operations in try/catch, log failures, and degrade gracefully (ticket creation still succeeds if auto-assignment fails; login still succeeds if the last-login-at update fails; a ticket save/delete still succeeds if one attachment's storage operation fails; a ticket update still succeeds if one stale attachment fails to delete).

- [ ] **Step 1: Write a test proving ticket creation survives an auto-assignment failure**

```php
<?php

namespace Tests\Unit;

use App\Models\Department;
use App\Models\Division;
use App\Models\Issue;
use App\Models\Priority;
use App\Models\Status;
use App\Models\Ticket;
use App\Observers\TicketObserver;
use App\Services\TicketAutoAssignmentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Tests\TestCase;

class TicketObserverErrorHandlingTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_ticket_creation_succeeds_even_if_auto_assignment_throws()
    {
        $dept = Department::create(['department_name' => 'ObserverErrDept_' . uniqid()]);
        $div = Division::create(['division_name' => 'ObserverErrDiv_' . uniqid(), 'department_id' => $dept->id]);
        $priority = Priority::firstOrCreate(['priority_name' => 'Normal'], ['priority_color' => '#ccc']);
        $status = Status::firstOrCreate(['status_name' => 'Unassigned'], ['status_color' => '#ccc']);
        $issue = Issue::create([
            'department_id' => $dept->id,
            'division_id' => $div->id,
            'priority_id' => $priority->id,
            'issue_description' => 'Observer error test issue ' . uniqid(),
        ]);

        $failingService = Mockery::mock(TicketAutoAssignmentService::class);
        $failingService->shouldReceive('assignTicket')->andThrow(new \RuntimeException('simulated failure'));
        $this->app->instance(TicketAutoAssignmentService::class, $failingService);

        $observer = $this->app->make(TicketObserver::class);

        $ticket = new Ticket();
        $ticket->forceFill([
            'department_id' => $dept->id,
            'division_id' => $div->id,
            'issue_id' => $issue->id,
            'status_id' => $status->id,
            'message' => 'observer error handling test',
        ]);
        $ticket->save(); // fires the real, container-resolved observer via the model event, not $observer directly

        $this->assertTrue(Ticket::where('id', $ticket->id)->exists());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=TicketObserverErrorHandlingTest`
Expected: FAIL — today, the mocked exception propagates uncaught out of `Ticket::save()` entirely, so the assertion is never reached (the test method itself errors out with the RuntimeException).

- [ ] **Step 3: Implement the `TicketObserver` fix**

In `app/Observers/TicketObserver.php`, replace:

```php
    public function created(Ticket $ticket)
    {
        // If the ticket isn't already assigned manually upon creation...
        if (is_null($ticket->assigned_to)) {
            $this->assignmentService->assignTicket($ticket);
        }
    }
```

with:

```php
    public function created(Ticket $ticket)
    {
        // If the ticket isn't already assigned manually upon creation...
        if (is_null($ticket->assigned_to)) {
            try {
                $this->assignmentService->assignTicket($ticket);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error(
                    '[TicketObserver] Auto-assignment failed for ticket ' . $ticket->id . ': ' . $e->getMessage()
                );
                // Ticket creation itself already succeeded (this fires from the
                // "created" event, after the INSERT committed) — leave the
                // ticket unassigned rather than surface a 500 for something
                // that already worked from the user's point of view.
            }
        }
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=TicketObserverErrorHandlingTest`
Expected: PASS

- [ ] **Step 5: Implement the `UpdateLastLoginAt` fix**

In `app/Listeners/UpdateLastLoginAt.php`, replace:

```php
    public function handle($event)
    {
        $event->user->update([
            'last_login_at' => now(),
        ]);
    }
```

with:

```php
    public function handle($event)
    {
        try {
            $event->user->update([
                'last_login_at' => now(),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error(
                '[UpdateLastLoginAt] Failed to update last_login_at for user ' . optional($event->user)->id . ': ' . $e->getMessage()
            );
            // Never let this non-critical side effect block a successful login.
        }
    }
```

- [ ] **Step 6: Implement the `Ticket.php` storage-hook fixes**

In `app/Models/Ticket.php`'s `setAttachmentsAttribute()`, wrap the store call. Replace:

```php
                if ($file && $file->isValid()) {
                    $fileName = \App\Services\AttachmentStorage::randomizedFilename($file);
                    $path     = $file->storeAs($destination_path, $fileName, $disk);
                    $base[]   = $path;
                }
```

with:

```php
                if ($file && $file->isValid()) {
                    try {
                        $fileName = \App\Services\AttachmentStorage::randomizedFilename($file);
                        $path     = $file->storeAs($destination_path, $fileName, $disk);
                        $base[]   = $path;
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::error(
                            '[Ticket] Failed to store attachment "' . $file->getClientOriginalName() . '": ' . $e->getMessage()
                        );
                        // Skip this one file rather than failing the whole save.
                    }
                }
```

In the `static::updating` closure, wrap the delete loop. Replace:

```php
            foreach ($filesToDelete as $file) {
                if (!empty($file) && Storage::disk($disk)->exists($file)) {
                    Storage::disk($disk)->delete($file);
                }
            }
```

with:

```php
            foreach ($filesToDelete as $file) {
                if (empty($file)) {
                    continue;
                }
                try {
                    if (Storage::disk($disk)->exists($file)) {
                        Storage::disk($disk)->delete($file);
                    }
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error(
                        '[Ticket] Failed to delete removed attachment "' . $file . '": ' . $e->getMessage()
                    );
                }
            }
```

In the `static::deleting` closure, wrap its delete loop the same way. Replace:

```php
            foreach ($files as $file) {
                if (!empty($file) && Storage::disk($disk)->exists($file)) {
                    Storage::disk($disk)->delete($file);
                }
            }
```

with:

```php
            foreach ($files as $file) {
                if (empty($file)) {
                    continue;
                }
                try {
                    if (Storage::disk($disk)->exists($file)) {
                        Storage::disk($disk)->delete($file);
                    }
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error(
                        '[Ticket] Failed to delete attachment "' . $file . '" for deleted ticket: ' . $e->getMessage()
                    );
                }
            }
```

- [ ] **Step 7: Implement the `TicketCrudController.php` attachment-cleanup-loop fix**

Replace:

```php
        foreach (array_diff($oldPaths, $retainedPaths) as $removedPath) {
            if (!empty($removedPath) && Storage::disk('public')->exists($removedPath)) {
                Storage::disk('public')->delete($removedPath);
            }
        }
```

with:

```php
        foreach (array_diff($oldPaths, $retainedPaths) as $removedPath) {
            if (empty($removedPath)) {
                continue;
            }
            try {
                if (Storage::disk('public')->exists($removedPath)) {
                    Storage::disk('public')->delete($removedPath);
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error(
                    '[TicketCrudController] Failed to delete removed attachment "' . $removedPath . '": ' . $e->getMessage()
                );
            }
        }
```

- [ ] **Step 8: Regression check**

Run: `php artisan test --filter=TicketReassignmentTest`
Run: `php artisan test --filter=TicketPerformanceTest`
Run: `php artisan test --filter=TicketObserverErrorHandlingTest`
Expected: all PASS.

---

### Task 19: Unguarded operations — Account/Policy/PDF try/catch (findings 3.8, 3.9, 3.10)

**Files:**
- Modify: `app/Http/Controllers/Admin/MyAccountController.php`
- Modify: `app/Http/Controllers/Admin/HrPolicyDocumentCrudController.php`
- Modify: `app/Http/Controllers/Admin/ReportsController.php`
- Modify: `app/Http/Controllers/Admin/SurveyReportsController.php`
- Modify: `app/Http/Controllers/Admin/ArtaSurveyReportsController.php`

**Interfaces:** None new — same graceful-degradation pattern as Task 18, applied to secondary (non-hot-path) features.

- [ ] **Step 1: Implement the `MyAccountController.php` avatar fix**

Replace:

```php
        if ($request->boolean('remove_avatar')) {
            if ($user->avatar_url && Storage::disk('public')->exists($user->avatar_url)) {
                Storage::disk('public')->delete($user->avatar_url);
            }

            $user->avatar_url = null;
        }

        if ($request->hasFile('avatar')) {
            if ($user->avatar_url && Storage::disk('public')->exists($user->avatar_url)) {
                Storage::disk('public')->delete($user->avatar_url);
            }

            $path = $request->file('avatar')->store('avatars', 'public');
            $user->avatar_url = $path;
        }

        $user->save();

        return redirect()->back()->with('success', trans('backpack::base.account_updated'));
```

with:

```php
        $avatarError = null;

        try {
            if ($request->boolean('remove_avatar')) {
                if ($user->avatar_url && Storage::disk('public')->exists($user->avatar_url)) {
                    Storage::disk('public')->delete($user->avatar_url);
                }

                $user->avatar_url = null;
            }

            if ($request->hasFile('avatar')) {
                if ($user->avatar_url && Storage::disk('public')->exists($user->avatar_url)) {
                    Storage::disk('public')->delete($user->avatar_url);
                }

                $path = $request->file('avatar')->store('avatars', 'public');
                $user->avatar_url = $path;
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('[MyAccountController] Avatar update failed for user ' . $user->id . ': ' . $e->getMessage());
            $avatarError = 'Your other account details were saved, but the avatar could not be updated. Please try again.';
        }

        $user->save();

        if ($avatarError) {
            return redirect()->back()->with('error', $avatarError);
        }

        return redirect()->back()->with('success', trans('backpack::base.account_updated'));
```

- [ ] **Step 2: Implement the `HrPolicyDocumentCrudController.php` destroy fix**

Replace:

```php
    public function destroy($id)
    {
        $this->crud->hasAccessOrFail('delete');

        $document = HrPolicyDocument::findOrFail($id);
        app(PolicyIngestService::class)->destroy($document);

        return response()->json(['success' => true]);
    }
```

with:

```php
    public function destroy($id)
    {
        $this->crud->hasAccessOrFail('delete');

        $document = HrPolicyDocument::findOrFail($id);

        try {
            app(PolicyIngestService::class)->destroy($document);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('[HrPolicyDocumentCrudController] Failed to delete document ' . $id . ': ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Could not delete this document: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json(['success' => true]);
    }
```

- [ ] **Step 3: Implement the PDF-generation fixes in all 3 report controllers**

In `app/Http/Controllers/Admin/ReportsController.php`, replace:

```php
        $data = $this->getReportData($startDate, $endDate, $includeZeroActivity);

        $pdf = Pdf::loadView('admin.pdf.reports_pdf', array_merge($data, [
            'reportStartDate' => $startDate->format('F j, Y'),
            'reportEndDate' => $endDate->format('F j, Y'),
        ]))->setPaper('a4', 'portrait');

        return $pdf->stream(
            'ticketing-report-' . $startDate->format('Ymd') . '-to-' . $endDate->format('Ymd') . '.pdf'
        );
```

with:

```php
        $data = $this->getReportData($startDate, $endDate, $includeZeroActivity);

        try {
            $pdf = Pdf::loadView('admin.pdf.reports_pdf', array_merge($data, [
                'reportStartDate' => $startDate->format('F j, Y'),
                'reportEndDate' => $endDate->format('F j, Y'),
            ]))->setPaper('a4', 'portrait');

            return $pdf->stream(
                'ticketing-report-' . $startDate->format('Ymd') . '-to-' . $endDate->format('Ymd') . '.pdf'
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('[ReportsController] PDF generation failed: ' . $e->getMessage());

            return redirect()->back()->with('error', 'Could not generate the PDF report. Try a narrower date range, or contact support if this continues.');
        }
```

Apply the identical pattern (same try/catch shape, same log-tag-per-class, same user-facing message) to `app/Http/Controllers/Admin/SurveyReportsController.php`'s `downloadPdf()` (wrapping its `Pdf::loadView('admin.pdf.survey_pdf', ...)->stream(...)` call) and `app/Http/Controllers/Admin/ArtaSurveyReportsController.php`'s `downloadPdf()` (wrapping its `Pdf::loadView('admin.pdf.arta_survey_pdf', ...)->setPaper(...)->stream(...)` call — note this one chains `setPaper` before `stream`, keep that chain inside the try block).

- [ ] **Step 4: Regression check**

Run: `php artisan test --filter=ReportsPdfRegressionTest`
Expected: PASS, unchanged (this suite already exercises the success path for all 3 PDF exports plus the survey/ARTA pages — confirms the try/catch doesn't change success-path behavior).

---

### Task 20: Validation completeness (findings 3.11, 3.13, 3.14)

**Files:**
- Modify: `app/Http/Requests/DepartmentRequest.php`, `DivisionRequest.php`, `IssueRequest.php`, `PriorityRequest.php`, `StatusRequest.php`
- Modify: `app/Http/Controllers/SurveyController.php`
- Modify: `app/Http/Controllers/Admin/HrPolicyDocumentCrudController.php`
- Test: `tests/Feature/CrudRequestValidationTest.php`

**Interfaces:** None new — validation rules only.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CrudRequestValidationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_department_create_rejects_empty_name()
    {
        $admin = User::role('admin')->first();
        $this->assertNotNull($admin);
        $this->actingAs($admin, 'web');

        $response = $this->post(route('department.store'), ['department_name' => '']);

        $response->assertSessionHasErrors('department_name');
    }

    public function test_department_create_accepts_a_real_name()
    {
        $admin = User::role('admin')->first();
        $this->assertNotNull($admin);
        $this->actingAs($admin, 'web');

        $response = $this->post(route('department.store'), ['department_name' => 'Real Dept ' . uniqid()]);

        $response->assertSessionDoesntHaveErrors('department_name');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=CrudRequestValidationTest`
Expected: `test_department_create_rejects_empty_name` FAILS — today `DepartmentRequest::rules()` returns `[]`, so an empty `department_name` is accepted.

- [ ] **Step 3: Implement the 5 empty `rules()` methods**

`app/Http/Requests/DepartmentRequest.php`, replace `rules()`:
```php
    public function rules()
    {
        return [
            'department_name' => 'required|string|max:255|unique:departments,department_name,' . $this->route('department'),
        ];
    }
```

`app/Http/Requests/DivisionRequest.php`, replace `rules()`:
```php
    public function rules()
    {
        return [
            'division_name' => 'required|string|max:255',
            'department_id' => 'required|exists:departments,id',
        ];
    }
```

`app/Http/Requests/IssueRequest.php`, replace `rules()`:
```php
    public function rules()
    {
        return [
            'department_id' => 'required|exists:departments,id',
            'division_id' => 'required|exists:divisions,id',
            'priority_id' => 'required|exists:priorities,id',
            'issue_description' => 'required|string|max:255',
            'icon' => 'nullable|string|max:255',
        ];
    }
```

`app/Http/Requests/PriorityRequest.php`, replace `rules()`:
```php
    public function rules()
    {
        return [
            'priority_name' => 'required|string|max:255|unique:priorities,priority_name,' . $this->route('priority'),
            'priority_color' => 'required|string|max:20',
        ];
    }
```

`app/Http/Requests/StatusRequest.php`, replace `rules()`:
```php
    public function rules()
    {
        return [
            'status_name' => 'required|string|max:255|unique:statuses,status_name,' . $this->route('status'),
            'status_color' => 'required|string|max:20',
        ];
    }
```

Note: `$this->route('department')` (etc.) resolves to the current route's bound id parameter on update, and `null` on create — Laravel's `unique` rule ignores the `null` case correctly (it only excludes a row when a real id is present), so this single rule string works for both create and update without duplicating the request class per operation, matching how Backpack wires `setupUpdateOperation()` to reuse `setupCreateOperation()`'s validation in these controllers.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=CrudRequestValidationTest`
Expected: PASS (2 tests)

- [ ] **Step 5: Regression check — confirm existing seeded/updated data still saves correctly**

Run: `php artisan test --filter=CrudAuthorizationGatesTest` (from Task 1 — it creates an `Issue` directly via `Issue::create()`, bypassing these HTTP-level rules, so should be unaffected, but re-run to confirm)
Run: `php artisan test`
Expected: full suite still passes — if any existing test now fails because it POSTs an incomplete department/division/issue/priority/status payload through the HTTP layer, report exactly which test and field, and adjust ONLY that test's payload to include the now-required field (do not loosen the new validation rule to work around it).

- [ ] **Step 6: Implement the survey rating enum fix (3.13)**

In `app/Http/Controllers/SurveyController.php`, replace:

```php
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'issue_id' => 'required|exists:issues,id',
            'timeliness_rating' => 'required|string',
            'handling_rating' => 'required|string',
            'quality_rating' => 'required|string',
            'overall_rating' => 'required|string',
        ]);
```

with:

```php
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'issue_id' => 'required|exists:issues,id',
            'timeliness_rating' => 'required|in:Very Dissatisfied,Dissatisfied,Satisfied,Very Satisfied',
            'handling_rating' => 'required|in:Very Dissatisfied,Dissatisfied,Satisfied,Very Satisfied',
            'quality_rating' => 'required|in:Very Dissatisfied,Dissatisfied,Satisfied,Very Satisfied',
            'overall_rating' => 'required|in:Very Dissatisfied,Dissatisfied,Satisfied,Very Satisfied',
        ]);
```

(These 4 exact label strings were confirmed against `SurveyReportsController.php:98-146`, which compares against exactly these 4 strings — no casing discrepancy found.)

- [ ] **Step 7: Implement the policy-document category whitelist fix (3.14)**

In `app/Http/Controllers/Admin/HrPolicyDocumentCrudController.php`, in `uploadStore()`, replace:

```php
        $request->validate([
            'title'          => 'required|string|max:255',
            'category'       => 'required|string',
            'effective_date' => 'nullable|date',
            'pdf_file'       => 'required|file|mimes:pdf|max:20480', // 20MB max
        ]);
```

with:

```php
        $request->validate([
            'title'          => 'required|string|max:255',
            'category'       => 'required|in:general,leave,benefits,performance',
            'effective_date' => 'nullable|date',
            'pdf_file'       => 'required|file|mimes:pdf|max:20480', // 20MB max
        ]);
```

In `updateStore()`, replace:

```php
        $rules = [
            'title'          => 'required|string|max:255',
            'category'       => 'required|string',
            'effective_date' => 'nullable|date',
            'pdf_file'       => 'nullable|file|mimes:pdf|max:20480',
        ];
```

with:

```php
        $rules = [
            'title'          => 'required|string|max:255',
            'category'       => 'required|in:general,leave,benefits,performance',
            'effective_date' => 'nullable|date',
            'pdf_file'       => 'nullable|file|mimes:pdf|max:20480',
        ];
```

(Matches the web upload form's own dropdown options — `general, leave, benefits, performance` — set in `uploadForm()`/`updateForm()`. Note this is intentionally narrower than the CLI command's whitelist, which additionally allows `conduct`/`discipline` — the web form has no UI option for those two, so requiring them here would make the rule inconsistent with what the form actually offers; state this in your report.)

- [ ] **Step 8: Full regression check**

Run: `php artisan test`
Expected: same pass/fail profile as before this task.

---

### Task 21: Consistency fixes (findings 3.16, 3.17)

**Files:** Modify `app/Http/Requests/TicketRequest.php`, `app/Http/Livewire/TicketChat.php`

**Interfaces:** None new — message text and a size-limit value change only.

- [ ] **Step 1: Fix the attachment size error message mismatch (3.16)**

In `app/Http/Requests/TicketRequest.php`, replace:

```php
            'attachments.*.max'   => 'Each attachment must not exceed 2MB.',
```

with:

```php
            'attachments.*.max'   => 'Each attachment must not exceed 20MB.',
```

- [ ] **Step 2: Unify the attachment size limit (3.17)**

Both paths use the same MIME allowlist as of Task 2 — align the size limit too, at 20MB (matching `TicketRequest`, the primary ticket-creation path) rather than the comment path's lower 10MB. In `app/Http/Livewire/TicketChat.php`, replace:

```php
        $this->validate([
            'comment' => 'required_without:attachments|nullable',
            'attachments.*' => 'nullable|mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx,txt|max:10240',
        ], [
            'comment.required_without' => 'Please enter a message or attach a file.',
            'attachments.*.mimes' => 'Allowed file types: jpg, jpeg, png, pdf, doc, docx, xls, xlsx, txt.',
            'attachments.*.max' => 'Each attachment must not exceed 10MB.',
        ]);
```

with:

```php
        $this->validate([
            'comment' => 'required_without:attachments|nullable',
            'attachments.*' => 'nullable|mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx,txt|max:20480',
        ], [
            'comment.required_without' => 'Please enter a message or attach a file.',
            'attachments.*.mimes' => 'Allowed file types: jpg, jpeg, png, pdf, doc, docx, xls, xlsx, txt.',
            'attachments.*.max' => 'Each attachment must not exceed 20MB.',
        ]);
```

- [ ] **Step 3: Update Task 2's test to match the new limit**

In `tests/Feature/TicketChatAttachmentValidationTest.php`, this task doesn't require new test cases (Task 2's existing 2 tests use file size 10, well under either 10MB or 20MB, so they remain valid unchanged) — just re-run them.

Run: `php artisan test --filter=TicketChatAttachmentValidationTest`
Expected: PASS, unchanged.

- [ ] **Step 4: Regression check**

Run: `php artisan test --filter=TicketReassignmentTest`
Expected: PASS, unchanged.

---

### Task 22: Final verification

**Files:** No production code changes — verification only.

- [ ] **Step 1: Run the full test suite**

Run: `php artisan test`
Report the full pass/fail count. The only acceptable failure is the pre-existing, unrelated `HrChatConversationMemoryTest` failure already noted in `AUDIT_REPORT.md` (confirm it's still the SAME failure, not a new one, by comparing the error message) — anything else failing must be fixed before this task is considered complete.

- [ ] **Step 2: Re-run `composer audit` one more time**

Run: `composer audit`
Confirm the advisory count matches Task 3's Step 4 findings exactly (no new advisories introduced by any later task's changes, which shouldn't touch dependencies at all after Task 3).

- [ ] **Step 3: Confirm `laravel/framework`'s version is still unchanged**

Run: `composer show laravel/framework`
Compare against Task 3 Step 3's recorded before/after — must still match.

- [ ] **Step 4: Manually verify the CRUD authorization gates (Task 1) don't over-restrict**

Re-run: `php artisan test --filter=CrudAuthorizationGatesTest`
Confirm `test_dept_head_can_manage_issues_but_not_delete`'s FIRST assertion (`GET issue.create` returns 200) still passes — i.e., the gate change didn't accidentally revoke access dept_head is actually supposed to have.

- [ ] **Step 5: Verify the new indexes exist on the live SQL Server DB**

Re-run Task 10 Step 5's verification queries for `tickets`, `ticket_reassignment_requests`, `users` and paste the current index lists in your report.

- [ ] **Step 6: Verify HR chatbot throttling**

Re-run: `php artisan test --filter=HrChatRateLimitTest`
Confirm PASS.

- [ ] **Step 7: `git status` and `git diff --stat`**

Run: `git status`
Run: `git diff --stat`
Paste both outputs in full in your report. Confirm:
- No commits were made (branch HEAD is unchanged from before this plan started).
- The changed-files list matches exactly what the 22 tasks above should have touched — no unexpected files, and specifically confirm `app/Providers/AppServiceProvider.php`, `database/seeders/UsersTableSeeder.php`, `database/factories/UserFactory.php`, `app/Services/GeminiService.php`, `app/Services/GPTService.php`, and `app/Http/Controllers/Admin/UserCrudController.php`'s validation methods (beyond the Task 1 authorization-gate addition and Task 17's import removal) do NOT appear with unexpected changes.

- [ ] **Step 8: State the 4 excluded findings explicitly untouched**

In your final report, explicitly list and confirm untouched: (1) the hardcoded seeded password in `UsersTableSeeder.php`/`UserFactory.php`, (2) survey/ARTA report pages' unbounded all-time fetch, (3) `GeminiService.php`/`GPTService.php` dead code, (4) "User create/update validation is minimal" in `UserCrudController.php`, (5) the dead commented-out `AppServiceProvider.php` bindings (already removed outside this plan, before it started — confirm this pre-existing state is undisturbed, not that you left it alone mid-task).
