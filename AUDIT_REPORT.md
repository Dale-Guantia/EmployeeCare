# EmployeeCare — Full Codebase Audit

**Date:** 2026-07-23
**Scope:** Entire project except `vendor/`, `node_modules/`, `storage/framework/`, `storage/logs/`, `bootstrap/cache/`, `.git/`.
**Method:** Read-only investigation. Four parallel focused passes (Security 1a-1e, Security 1f-1j, Performance, Code Quality), cross-checked and deduplicated into this single report. **Zero files were edited, zero migrations created, zero dependencies changed.**

Two findings were investigated independently by two different passes and are presented once, with a cross-reference note at the second location, so the summary counts aren't inflated by double-counting:
- Missing MIME validation on ticket-comment attachments → filed under **Security 1e** (High, XSS risk) — also surfaced by the Quality pass under validation completeness.
- `GeminiService`/`GPTService` dead code → filed under **Quality 3a** (Medium, maintenance burden) — also surfaced by the Security pass under secrets/config review.

---

## Summary Table

| Category | Critical | High | Medium | Low | Note | Total |
|---|---|---|---|---|---|---|
| 1 — Security | 0 | 4 | 4 | 3 | 6 | 17 |
| 2 — Performance | 0 | 4 | 7 | 1 | 3 | 15 |
| 3 — Quality | 0 | 0 | 6 | 12 | 1 | 19 |
| **Total** | **0** | **8** | **17** | **16** | **10** | **51** |

**No Critical findings** (nothing actively exploitable without another precondition, no live data exposure, no auth bypass found). The 4 Security-High findings are the top priority.

---

## PART 1 — SECURITY

### High

#### [High] Authorization gap: Department/Division/Issue CRUD controllers only gate on the `.view` permission
**File:** `app/Http/Controllers/Admin/DepartmentCrudController.php:33`, `DivisionCrudController.php:33`, `IssueCrudController.php:33`
**Issue:** Each controller's `setup()` only checks `backpack_user()->can('department.view')` / `'division.view'` / `'issue.view'` and never calls Backpack's `denyAccess()`. Backpack's `CreateOperation`/`UpdateOperation`/`DeleteOperation` traits each unconditionally call `$this->crud->allowAccess(<op>)` by default, so passing the single `.view` check silently unlocks create/update/delete too. Per `database/seeders/RolesAndPermissionsSeeder.php:51-60`, `dept_head` is seeded with `department.view`, `division.view`, `issue.view`, `issue.create`, `issue.update` — explicitly **not** department/division create/update/delete, or issue delete. `div_head` is seeded with `issue.view/create/update` only.
**Risk / impact:** Any `dept_head` account can create/edit/delete Department and Division records (shared org data used across every ticket in the system, not just their own scope) and delete Issues — none of which the seeded permission set intends to grant. Enables the stored-XSS finding below (attacker-controlled `division_name`).
**Recommendation:** Check the operation-specific permission per action, or call `$this->crud->denyAccess([...])` in `setup()` and selectively `allowAccess()` per permission — mirroring the pattern already used correctly in `TicketCrudController::setup()`.
**Confidence:** high

#### [High] Ticket-comment attachment upload has no file-type (MIME) restriction
**File:** `app/Http/Livewire/TicketChat.php:58-64`, rendered at `resources/views/livewire/ticket-chat.blade.php:23`
**Issue:** `sendComment()`'s validation is `'attachments.*' => 'nullable|max:10240'` — size cap only, **no `mimes:`/`file` type restriction**, unlike ticket creation (`app/Http/Requests/TicketRequest.php:38`, restricted to `jpg,jpeg,png,pdf,doc,docx,xls,xlsx,txt`). Any authenticated user can attach any file type to a ticket comment; it's stored via `$file->storeAs('attachments', $filename, 'public')` and linked directly (`target="_blank"`) with no server-side `Content-Type` control.
**Risk / impact:** An attacker can upload an `.html`/`.svg` file containing `<script>`; when a staff member opens the attachment link, the browser renders and executes it — stored XSS in the context of whatever origin serves `public/storage`. Combined with `time() . '_' . <original filename>` (not randomized — see Low finding below), the attacker fully controls the stored file's extension.
**Recommendation:** Add `mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx,txt` (matching `TicketRequest`) to the Livewire validation rule; consider serving attachments through a controller action that forces `Content-Disposition: attachment`.
**Confidence:** high

#### [High] Hardcoded weak default password for real, named employee accounts in a seeder that runs by default
**File:** `database/seeders/UsersTableSeeder.php:128,139,165,178,206` (also `database/factories/UserFactory.php:21`)
**Issue:** `DatabaseSeeder.php` unconditionally calls `UsersTableSeeder` (no environment guard). The seeder creates dozens of accounts using what appear to be real full names and employee numbers, and assigns **every one** the identical hardcoded password `bcrypt('12341234')`. No forced-password-change-on-first-login mechanism exists anywhere.
**Risk / impact:** If this seeder was ever run against a real/staging/production database, every named employee account shares one publicly-known password sitting in git history — a ready-made credential list for anyone with repo read access, several with `admin`/`div_head`/`hr_staff` roles that can view HR tickets and chatbot conversation logs. Also a PII exposure (real names + employee numbers hardcoded in source).
**Recommendation:** Never hardcode a shared literal password for named individuals in a seeder that runs by default. Generate random per-user passwords or require an invite/reset flow, gate demo data behind `app()->environment('local')`, and rotate any already-provisioned `12341234` passwords.
**Confidence:** high (code confirmed directly; whether it was actually executed against production is inferred, not directly verified).

#### [High] Multiple known-vulnerable Composer dependencies, including 2 High-severity CVEs
**File:** `composer.lock` / `vendor` — `laravel/framework` (8.x-dev), `symfony/mime`, `guzzlehttp/guzzle` (7.10.0), `guzzlehttp/psr7` (2.8.0), `dompdf/dompdf` (v2.0.8), `league/commonmark`, `symfony/routing`, `symfony/polyfill-intl-idn`
**Issue:** `composer audit` reports 27 advisories across 8 packages (full output in Appendix A). Two are **High**: `symfony/mime` CVE-2026-45067 (email header/SMTP command injection via CRLF in `Address`) and `laravel/framework` GHSA-5vg9-5847-vvmq (CRLF injection in the default email validation rule). The rest are Medium/Low: dompdf SVG/BMP local-file-read and DoS issues (6 advisories), Guzzle/psr7 cookie and CRLF issues (9 advisories), Symfony routing URL-generation confusion (2), CommonMark sanitizer bypasses (2), a polyfill IDN equivalence issue (1).
**Risk / impact:** The app sends email (ticket notifications) and generates PDFs via dompdf from data that may include user-controlled input — the email-header-injection and dompdf local-file-read issues are directly relevant to those paths if user-controlled strings ever reach an email `Address` or get rendered into a PDF as SVG/BMP.
**Recommendation:** Update the affected packages within Laravel 8's constraints, prioritizing the two High findings (`symfony/mime`, `laravel/framework`) first. Not executed as part of this read-only audit.
**Confidence:** high (installed versions confirmed via `composer show` to fall in the vulnerable ranges).

### Medium

#### [Medium] Same `.view`-only authorization pattern on Status/Priority/User/Role/Permission controllers (currently latent)
**File:** `StatusCrudController.php:33`, `PriorityCrudController.php:33`, `UserCrudController.php:29`, `RoleCrudController.php:19`, `PermissionCrudController.php:13`
**Issue:** Same structural flaw as the High finding above. Not currently exploitable because no non-admin role has even `.view` for these entities today.
**Risk / impact:** Latent trap — if any non-admin role is ever granted read-only visibility into users/roles/permissions (a plausible future change), it would silently also gain full create/update/delete, including assigning the `admin` role to any user via `UserCrudController`'s roles checklist — a privilege-escalation path.
**Recommendation:** Same fix, applied project-wide as a hardening pass.
**Confidence:** medium (real code path, but exploitability today is null given current seeded permissions).

#### [Medium] Stored XSS via unescaped JSON embedded in inline `<script>` blocks
**File:** `resources/views/admin/ticket/reassignment_widget.blade.php:176`; possibly `resources/views/vendor/backpack/base/dashboard.blade.php:331-333` (lower confidence — data source for those 3 variables wasn't located, may be dead code)
**Issue:** `var allDivisions = {!! $divisions->toJson() !!};` embeds `Division::all()` directly into a `<script>` block via raw `toJson()`, which doesn't set `JSON_HEX_TAG`/`JSON_HEX_AMP`. If any `division_name` contains `</script>`, it breaks out of the script block. Given the CRUD authorization gap above, a `dept_head` can set `division_name` to an arbitrary payload; any admin/dept_head/div_head who later opens a ticket's show page executes it.
**Risk / impact:** Stored XSS in an admin session — could lead to session/CSRF-token theft or unauthorized admin actions performed as the victim.
**Recommendation:** Use Blade's `@json(...)` directive (sets hex-escaping flags) instead of `{!! ...->toJson() !!}` when embedding data into `<script>` tags; or pass data via `data-*` attributes.
**Confidence:** high for `reassignment_widget.blade.php`; low for the `dashboard.blade.php` instance (couldn't confirm it's actually reachable).

#### [Medium] `.env.example` ships with `APP_DEBUG=true`
**File:** `.env.example:4`
**Issue:** `config/app.php:42` itself defaults safely to `false`, but the template file operators copy to `.env` actively sets debug mode on.
**Risk / impact:** A deployment that copies `.env.example` to `.env` without changing this line runs in debug mode in production — full stack traces, file paths, env values exposed via Ignition/Whoops error pages.
**Recommendation:** Change the template default to `APP_DEBUG=false` (and `APP_ENV=production`) so a naive copy-paste fails safe.
**Confidence:** high

#### [Medium] HR chatbot AI endpoints have no rate limiting
**File:** `routes/backpack/custom.php:90-102` (`hr-assistant/ask` and siblings)
**Issue:** No `throttle:` middleware or other rate limiter on any HR chatbot route. The only `throttle:api` in the app applies to the unrelated `routes/api.php` group.
**Risk / impact:** `ask()` triggers a real, paid Anthropic API call per request with a 60s timeout. Any authenticated user (or compromised session) can hammer this endpoint to run up API costs, exhaust rate limits for everyone, or tie up PHP-FPM workers. (Login itself IS throttled correctly — see Note below.)
**Recommendation:** Add a named `throttle:hr-chat` limiter (e.g. N requests/minute per user) to the `hr-assistant/*` route group, particularly `ask`.
**Confidence:** high that no throttling exists; medium on real-world exploitability since it requires an authenticated session.

### Low

#### [Low] Upload filenames not randomized
**File:** `app/Models/Ticket.php:224` (`time() . '_' . $file->getClientOriginalName()`), `app/Http/Livewire/TicketChat.php:70` (same pattern)
**Issue:** Not a path-traversal bug (Symfony's `getClientOriginalName()` already strips separators), but the extension and most of the filename are fully attacker-chosen; collisions are only avoided by a second-resolution `time()` prefix.
**Risk / impact:** Minor on its own; is the second ingredient (alongside the missing MIME check above) that makes the stored-XSS-via-attachment finding possible.
**Recommendation:** Use `Str::uuid()` or `$file->hashName()` for the stored filename; keep the original name in a DB column for display only.
**Confidence:** high

#### [Low] Raw HR chat question text logged on exception paths
**File:** `app/Services/AnthropicService.php:150-152`, `app/Http/Controllers/Admin/HrChatController.php:161-164`
**Issue:** Both catch blocks log the user's literal question text on any unhandled exception.
**Risk / impact:** HR chatbot questions can contain personal/sensitive HR matters (leave reasons, disciplinary concerns). Writing this into `storage/logs/laravel.log` extends the exposure surface beyond the already access-controlled `hr_chat_logs` table.
**Recommendation:** Log a truncated reference / `log_id`/`conversation_id` instead of full question text, unless an explicit debug flag is enabled.
**Confidence:** medium (only fires on the exception path, not every request).

#### [Low] Session `secure` cookie flag has no safe default
**File:** `config/session.php:171`
**Issue:** `'secure' => env('SESSION_SECURE_COOKIE')` has no default argument — if unset (as in the local `.env`), the session cookie's `Secure` flag is off. `http_only` (line 184, hardcoded `true`) and `same_site` (line 199, `'lax'`) are both fine.
**Risk / impact:** If a production deployment also fails to set `SESSION_SECURE_COOKIE=true`, session cookies could be sent over unencrypted connections.
**Recommendation:** `env('SESSION_SECURE_COOKIE', true)` or tie it to `env('APP_ENV') === 'production'` so a missing var fails safe.
**Confidence:** low on actual production impact (production `.env` not observable from source); high on the code-level fact.

### Notes

- **Validated upload paths — no issue.** Ticket creation (`TicketRequest.php:37-39`, mimes+size), HR policy documents (`HrPolicyDocumentCrudController.php:97,135`, `mimes:pdf`), and avatar upload (`MyAccountController.php:38`, `image|mimes:...`, randomized stored name) all correctly validate. Confidence: high.
- **`{!! !!}` full inventory — no other risky occurrences.** ~29 raw-output sites reviewed; all are QR-code generation from hardcoded URLs, `e()`-escaped exception messages, `csrf_field()`/`method_field()`, or developer-set CRUD config strings — except the two flagged above. The HR chatbot's client-side rendering (`public/js/hr-chat-core.js`) explicitly HTML-escapes both the user's question and the AI's raw answer text *before* Markdown rendering, correctly defending against both user-input and prompt-injected-LLM-output XSS. Confidence: high.
- **Role/permission name cross-check — no other mismatches.** Every `hasRole(`/`hasAnyRole(`/`->can(` literal string in the app matches `RolesAndPermissionsSeeder.php` exactly (case and spelling) — the previously-fixed `'Admin'` vs `'admin'` class of bug has no surviving siblings. Confidence: high.
- **`TicketCrudController`'s per-ticket authorization is solid.** Its custom resolve/reopen/quick-assign/reassign actions don't call Backpack's `hasAccessOrFail()` directly, but every one first calls `getEntry()`, which uses the CrudPanel's already-scoped query (department/division/assigned-to scoping applied in `setup()`) — an out-of-scope ticket ID 404s before any handler's role check runs. Traced through the Backpack vendor source to confirm this actually works. Confidence: high.
- **Abandoned dependencies.** `composer audit` flags `fruitcake/laravel-cors` (v2.2.0) and `swiftmailer/swiftmailer` (v6.3.0) as abandoned upstream — no further patches will be published for either. Not urgent standalone; track migration to `symfony/mailer` and Laravel's built-in CORS on the eventual framework upgrade.
- **`GeminiService.php`/`GPTService.php` dead code — see Quality 3a** for the full write-up (filed there as it's primarily a maintainability concern, not a live security exposure — both are correctly env-backed with no hardcoded secrets).
- **CSRF coverage — no findings.** Every state-changing `<form>` found (18 locations, including the 3 raw-HTML-string forms in `TicketCrudController.php`'s quick-assign/resolve/reopen modals) includes `@csrf`/`csrf_field()`. `VerifyCsrfToken.php`'s `$except` array is empty.
- **Login throttling — intact.** Backpack's `LoginController` pulls in `ThrottlesLogins` (5 attempts/minute, keyed by username+IP) and nothing in the app overrides or disables it.

---

## PART 2 — PERFORMANCE / OPTIMIZATION

### High

#### [High] Dashboard "Latest Tickets" table has a classic N+1 (up to 4 relations × 10 rows)
**File:** `resources/views/vendor/backpack/base/dashboard.blade.php:5, 211-238`
**Issue:** `$latestTickets = App\Models\Ticket::latest()->take(10)->get();` has no `->with()`. The loop then touches `$ticket->user->name`, `$ticket->issue->issue_description`, `$ticket->status->status_color`/`status_name`, `$ticket->priority->priority_color`/`priority_name` — four unloaded `belongsTo` relations per row.
**Risk / impact:** Up to 40 extra round-trips on the single most-visited page in the app. `ReportsController.php:213` already does the equivalent fetch correctly (`Ticket::with(['user','issue','status','priority'])`) — the right pattern exists one file over, just not applied here.
**Recommendation:** `Ticket::with(['user', 'issue', 'status', 'priority'])->latest()->take(10)->get();`
**Confidence:** high

#### [High] `TicketNotificationService` loads every user then lazy-loads `roles` per user, on every ticket event
**File:** `app/Services/TicketNotificationService.php:198, 223, 248, 355` (4× `User::all()`), consumed via `isAdmin()`/`isDeptHead()`/etc. at lines 73-175
**Issue:** `notifyTicketCreated`/`Assigned`/`StatusChanged`/`Commented` each run `User::all()` with no `->with('roles')`, then filter every user through role-check methods that each call Spatie's `hasRole()` → `loadMissing('roles')` → one lazy query per user per call.
**Risk / impact:** Fires on every ticket create, assign, status change, and comment — the hottest write paths in the system. Scales as N users × 4 event types.
**Recommendation:** `User::with('roles')->get()` in all four methods (ideally memoized into one shared helper since the same list is fetched independently four times).
**Confidence:** high

#### [High] `tickets` table has no indexes on its hottest filter columns
**File:** `database/migrations/2026_02_24_073426_create_tickets_table.php:16-34` (no later migration adds indexes)
**Issue:** `status_id`, `department_id`, `division_id`, `issue_id`, `priority_id`, `assigned_to`, `resolved_by`, `user_id` are all `foreignId()->constrained()`. **SQL Server does not auto-index the referencing column on a foreign key** (unlike MySQL/InnoDB) — only the referenced parent key is guaranteed indexed. All of these columns are filtered/joined constantly across `User.php`, `TicketAutoAssignmentService.php`, `ReportsController.php`, `TicketCrudController.php` (role-scoped list queries), and `TicketNotificationService.php`. `created_at` is also used in every `whereBetween` report/dashboard query with no index. Note: `.env.example` still defaults `DB_CONNECTION=mysql`, making this an easy blind spot for anyone testing locally against MySQL, where it wouldn't manifest.
**Risk / impact:** As `tickets` grows, every ticket-list view, every report/dashboard widget, and every auto-assignment workload count degrades from an index seek to a table scan. This is the most consequential structural finding — virtually all ticket querying goes through these columns.
**Recommendation:** New migration adding at minimum: `$table->index('status_id'); $table->index(['department_id','division_id']); $table->index('assigned_to'); $table->index('user_id'); $table->index('created_at'); $table->index('issue_id');` — a composite `['department_id','division_id','status_id']` would also directly serve the dept/div-head list-scoping clauses.
**Confidence:** high

#### [High] Status-ID-by-name lookups repeated many times within a single request
**File:** `app/Models/Ticket.php:83,89,99-100,184,246`; `TicketCrudController.php:469,497,548,773-774,625-675`; `TicketAutoAssignmentService.php:49,68-69`; `ReportsController.php:180,286-288`; `dashboard.blade.php:60-62`; `reassignment_widget.blade.php:6`
**Issue:** `Status::where('status_name', 'Resolved'/'Pending'/'Reopened'/'Unassigned')->value('id')` is queried independently with no request-level memoization from many places. Worst case: a single "Accept Reassignment" click can trigger `$entry->save()` → `Ticket::saving()` hook queries Resolved+Reopened → `Ticket::updated()` hook queries Resolved again → `TicketAutoAssignmentService::assignTicket()` queries Pending twice → its own `$ticket->save()` re-triggers the same hooks again — **8-10+ redundant queries for one click.**
**Risk / impact:** Each query is cheap individually, but this adds real, avoidable round-trips to write-path latency on the exact actions users click most (resolve, reopen, assign, reassign).
**Recommendation:** Compute `Status::pluck('id', 'status_name')` once per request (or cache it — see Medium finding below) and thread it through instead of re-querying by string comparison everywhere.
**Confidence:** high

### Medium

#### [Medium] Dashboard pulls the entire tickets table, all-time, on every load
**File:** `dashboard.blade.php:64-65`
**Issue:** `$dashAllTickets = Ticket::with([...])->get([...]);` has no date bound. (Relations ARE eager-loaded here — this is purely an unbounded-fetch issue, not N+1.) `ReportsController::getReportData()` defaults to "this month" for the equivalent widgets; the dashboard doesn't.
**Risk / impact:** Fine today, but this is the busiest page in the app and will scale linearly and unboundedly with total ticket volume forever.
**Recommendation:** Cache the computed all-time widget payload for a few minutes, or cap the practical "all time" window.
**Confidence:** high

#### [Medium] Dead unbounded query in `ReportsController::getReportData()`
**File:** `app/Http/Controllers/Admin/ReportsController.php:157`
**Issue:** `$ticketIds = (clone $ticketQuery)->pluck('id');` is computed but never referenced again anywhere in the method or file.
**Risk / impact:** A wasted, unbounded query runs on every Reports page view and PDF export for zero benefit.
**Recommendation:** Delete the line.
**Confidence:** high

#### [Medium] Survey/ARTA report pages default to an unbounded all-time fetch
**File:** `ArtaSurveyReportsController.php:24-35,63-77`; `SurveyReportsController.php:25-38,64-87`
**Issue:** Both `index()` actions call `getReportData()` with no arguments, so the `whereBetween` guard is skipped and every survey response ever submitted is fetched unfiltered on every page view. (The code even has a comment noting `->get()` fetches all columns including every SQD/CC rating field.) `ReportsController` (the ticket one) defaults to "this month" via `resolvePeriod()`; these two do not.
**Risk / impact:** Survey tables are filled by the public and can grow faster than internal ticket data.
**Recommendation:** Give both `index()` actions the same "default to current month" behavior `ReportsController::resolvePeriod()` already implements.
**Confidence:** high

#### [Medium] Auto-assignment issues one workload-count query per eligible staff member, inside a loop
**File:** `app/Services/TicketAutoAssignmentService.php:73-78`
**Issue:** `$eligibleStaff->map(function ($staff) { $staff->active_ticket_count = Ticket::where(...)->count(); ... })` — a fresh COUNT query per staff member instead of one aggregate query.
**Risk / impact:** Runs synchronously on every new ticket creation. Scales linearly with headcount, in the hot create path.
**Recommendation:** One grouped query — `Ticket::whereIn('assigned_to', ...)->whereIn('status_id', ...)->groupBy('assigned_to')->selectRaw('assigned_to, COUNT(*) as cnt')->pluck('cnt','assigned_to')` — then map counts in PHP.
**Confidence:** medium

#### [Medium] `ticket_reassignment_requests` missing indexes on its hot filter columns
**File:** `database/migrations/2026_07_15_090000_create_ticket_reassignment_requests_table.php:16-30`
**Issue:** `ticket_id`, `to_department_id`, `to_division_id` have the same SQL-Server FK-no-auto-index gap; `status` is a plain string column with **no index at all**. Queried repeatedly via `Ticket::pendingReassignmentRequest()`, `TicketCrudController.php:64-73`'s `orWhereHas(...)` role-scoping, and `Ticket.php:187-189`.
**Risk / impact:** Queried on essentially every ticket-list render for dept_head/div_head users, plus every ticket Show page.
**Recommendation:** New migration: `$table->index(['ticket_id', 'status']); $table->index('to_department_id'); $table->index('to_division_id');`
**Confidence:** medium

#### [Medium] `users` table missing indexes on `department_id`/`division_id`
**File:** `database/migrations/2026_02_24_022530_create_users_table.php:24-25`
**Issue:** Same FK-without-index gap. Filtered directly in `TicketAutoAssignmentService.php:27,31`, `SurveyController.php:23`, `ReportsController.php:160`, `TicketCrudController.php:425-426,896-897`.
**Risk / impact:** Lower urgency (headcount grows slowly) but runs on every ticket auto-assignment and quick-assign dropdown render.
**Recommendation:** `$table->index(['department_id', 'division_id']);` in a new migration.
**Confidence:** medium

#### [Medium] Reference/lookup data re-queried fresh on nearly every request
**File:** `reassignment_widget.blade.php:24-25` (`Department::all()`, `Division::all()` on every ticket Show page); `TicketReportWidgets.php:241,275,282`; `dashboard.blade.php:49,60-62`; `Ticket.php:83,89,99-100`
**Issue:** None of `departments`, `divisions`, `statuses`, `issues`, `priorities` are cached anywhere, despite changing only a few times a year.
**Risk / impact:** Individually cheap, but pure repeated load, compounded by the redundant Status lookups finding above.
**Recommendation:** `Cache::remember('ref:statuses.by_name', now()->addMinutes(15), fn () => ...)` (and similarly for departments/divisions/issues/priorities), invalidated in the respective CRUD controllers' write hooks.
**Confidence:** high

### Low

#### [Low] HR chat FAQ query groups by an unindexed text column
**File:** `app/Http/Controllers/Admin/HrChatController.php:30-36`
**Issue:** `HrChatLog::select('question')->selectRaw('COUNT(*)...')->groupBy('question')->orderByDesc(...)->limit(5)` scans and groups the entire table by a free-text column with no index.
**Risk / impact:** Only runs once per HR Assistant page load, so impact is bounded; grouping by exact free-text is also inherently low-value (questions are rarely identical strings).
**Recommendation:** Low priority; consider a periodically-refreshed cached "top prompts" computation if it becomes slow.
**Confidence:** low

### Notes

- **`hr_chat_logs` indexing is already correct.** `conversation_id` and `user_id` (leftmost prefix of a composite index) are both index-served for the hot lookups `HrChatController.php` actually uses. This is the "known-good" pattern, applied correctly.
- **N+1 loops confirmed already eager-loaded — no issue found in:** `TicketChat.php`/`ticket-chat.blade.php` (`with(['status','user','assignee'])`), `reports.blade.php`'s latest-tickets/users tables (sourced from `ReportsController`'s already-eager-loaded queries), `survey_reports.blade.php` (`Survey::with(['staff.division','service'])`), and `TicketCrudController::setupShowOperation()` (explicit `->load([...])` with a comment explaining Backpack drops `->with()` on Show — the deliberate correct pattern).
- **No genuine same-page duplicate CDN loads found.** Chart.js/DataTables/jQuery each load once per distinct page. The one place that could plausibly double-load (floating HR-chat widget vs. the full HR Assistant page) is explicitly guarded against rendering together.

---

## PART 3 — CODE QUALITY / MAINTAINABILITY

### 3a. Dead code

#### [Medium] `GeminiService`/`GPTService` are 100% dead code
**File:** `app/Services/GeminiService.php:1`, `app/Services/GPTService.php:1`
**Issue:** Both are complete, functioning AI-provider integrations (Gemini, and an OpenRouter-based GPT client), correctly env-backed with no hardcoded secrets, but never instantiated anywhere — the only reference is a commented-out `use` statement in `HrChatController.php:10-11`.
**Risk / impact:** ~200 lines of maintenance burden with zero runtime effect; a future maintainer could waste time "fixing" these for a bug report that has no production impact, or wrongly assume multi-provider support exists.
**Recommendation:** Delete both (and the unused `gemini`/`openrouter` config blocks in `config/services.php`) if multi-provider support isn't planned; otherwise wire one in behind a config flag with a test proving it's reachable.
**Confidence:** high

#### [Low] `TicketNotificationService::managementUsers()` is unused
**File:** `app/Services/TicketNotificationService.php:49`
**Issue:** Defined, never called; `notifyTicketCreated()`/reassignment-notification logic instead duplicates similar role-filtering inline.
**Recommendation:** Delete, or refactor the duplicated inline blocks to use it.
**Confidence:** high

#### [Low] `TicketReassignmentRequest::isPending()` is unused
**File:** `app/Models/TicketReassignmentRequest.php:70`
**Issue:** Defined, never called; all actual call sites compare `->status` directly against `STATUS_PENDING` instead.
**Recommendation:** Delete, or replace the direct comparisons with `->isPending()` for consistency.
**Confidence:** high

#### [Low] Dead commented-out container bindings in `AppServiceProvider`
**File:** `app/Providers/AppServiceProvider.php:27-35`
**Issue:** Two commented-out bindings for `PermissionCrudController`/`RoleCrudController` are vestigial — routing already resolves the app's own controllers by namespace.
**Recommendation:** Delete, or add a one-line comment explaining why the binding is unnecessary.
**Confidence:** medium

#### [Low] Unused `use` imports across 8 files
**File:** `UserCrudController.php:5` (imports a `UserRequest` FormRequest that **doesn't exist** — leftover from an abandoned refactor), `Console/Commands/TestOcrSetup.php:6`, `MyAccountController.php:7`, `NotificationController.php:6`, `Listeners/UpdateLastLoginAt.php:5-6`, `Models/User.php:6`, `Providers/AuthServiceProvider.php:6`, `Providers/EventServiceProvider.php:9`
**Issue:** None affect runtime; the `UserCrudController` one is notable since it actively misleads about how user validation is (not) centralized.
**Recommendation:** Remove; for `UserCrudController`, either create the missing `UserRequest` (see 3c) or drop the import.
**Confidence:** high

### 3b. Error handling

#### [Medium] Ticket auto-assignment save on create is unguarded
**File:** `app/Observers/TicketObserver.php:21` → `app/Services/TicketAutoAssignmentService.php:54`
**Issue:** `TicketObserver::created()` calls `assignTicket()`, which does a second `$ticket->save()`, with no try/catch anywhere in the chain.
**Risk / impact:** If that UPDATE fails, the exception propagates uncaught even though the ticket's own INSERT already committed — the user sees a raw 500 despite their ticket having been created, and may resubmit a duplicate.
**Recommendation:** Wrap the `assignTicket()` call in try/catch; log failures and let creation succeed with the ticket left unassigned.
**Confidence:** high

#### [Medium] Last-login-at update on every login is unguarded
**File:** `app/Listeners/UpdateLastLoginAt.php:26`
**Issue:** Fires synchronously on every login (not queued, despite importing `ShouldQueue`), no try/catch.
**Risk / impact:** If the update fails, the login request itself fails with a raw 500 — the user can't log in despite correct credentials, over a non-critical side effect.
**Recommendation:** Wrap in try/catch (log-and-swallow), or make the listener actually queued.
**Confidence:** high

#### [Medium] Ticket attachment storage writes/deletes are unguarded
**File:** `app/Models/Ticket.php:225` (`setAttachmentsAttribute`), `:143-147` (updating hook), `:161-165` (deleting hook)
**Issue:** Four file-storage call sites inside the model itself, none wrapped in try/catch.
**Risk / impact:** A storage failure (disk full, permission denied) propagates out of `save()`/`delete()` uncaught into controllers that don't catch it either — raw 500 or broken Livewire request, and possibly the ticket record left out of sync with its attachments array.
**Recommendation:** Wrap each Storage call, log failures, fail the specific operation gracefully rather than the whole request.
**Confidence:** high

#### [Low] Attachment cleanup loop in ticket update is unguarded
**File:** `app/Http/Controllers/Admin/TicketCrudController.php:748-752`
**Issue:** Loops over removed attachments calling `Storage::delete()`, no try/catch, runs before the actual field save.
**Risk / impact:** One failed delete aborts the entire edit request — the user's actual field changes never get a chance to save.
**Recommendation:** Wrap the loop; log individual failures and continue.
**Confidence:** medium

#### [Low] Avatar upload/delete in My Account is unguarded
**File:** `app/Http/Controllers/Admin/MyAccountController.php:55-69`
**Issue:** Avatar delete+store directly in the controller action, no try/catch.
**Risk / impact:** A failure 500s the whole "My Account" save, losing the user's other field edits from the same submission.
**Recommendation:** Wrap the avatar block; save other fields anyway and flash a specific error on avatar failure.
**Confidence:** medium

#### [Low] Policy document delete endpoint is unguarded
**File:** `HrPolicyDocumentCrudController.php:209-217` → `PolicyIngestService.php:196-205`
**Issue:** `destroy()` isn't wrapped in try/catch, inconsistent with `uploadStore()`/`updateStore()` in the same controller, which are.
**Risk / impact:** This is an AJAX endpoint expecting JSON; an uncaught exception returns an HTML error page instead, silently breaking the delete-confirmation UI.
**Recommendation:** Wrap in the same try/catch pattern already used by the other two methods.
**Confidence:** medium

#### [Note] PDF report generation is unguarded in 3 controllers
**File:** `ReportsController.php:139-146`, `SurveyReportsController.php:54-61`, `ArtaSurveyReportsController.php:53-60`
**Issue:** `Pdf::loadView(...)->stream(...)` has no try/catch in any of the three.
**Risk / impact:** DomPDF can plausibly fail on a large date range, producing a raw 500 instead of a clean error. Lower likelihood than the findings above since report data is already date-bounded.
**Recommendation:** Wrap in try/catch, redirect back with a flashed error on failure.
**Confidence:** low

### 3c. Validation completeness

#### [Medium] 5 CRUD form requests have empty validation rules
**File:** `app/Http/Requests/{Department,Division,Issue,Priority,Status}Request.php` (`rules()`)
**Issue:** All five are correctly wired via `CRUD::setValidation(...)`, but every `rules()` still returns the untouched Backpack scaffold placeholder — an empty array. No rules exist for any field on any of these five entities.
**Risk / impact:** An admin can submit an empty `department_name`, blank `issue_description`, etc. — either a raw SQL constraint-violation 500, or silent garbage rows rendered as blanks across every dropdown/badge/report that uses these names.
**Recommendation:** Fill in `rules()` with at minimum `required|string|max:255` (plus `unique` where the field is a name/key), matching `TicketRequest.php`'s existing pattern.
**Confidence:** high

#### [Medium] TicketChat comment attachments have no mime restriction
*(Same underlying gap as Security 1e, High — see that entry for the full risk writeup. Filed here too since it's also a validation-completeness gap, not just a security one: the rule set doesn't match the sibling `TicketRequest` rules for the same conceptual field.)*
**File:** `app/Http/Livewire/TicketChat.php:60`
**Confidence:** high

#### [Low] User create/update validation is minimal
**File:** `UserCrudController.php:64-96, 98-134`
**Issue:** Only name/email/password (create-only) are validated. No rule on the `roles` checklist (can save zero roles), no password length/complexity, no `required` on `department_id`/`division_id`.
**Risk / impact:** An admin can create a login-capable account with a 1-character password and no roles.
**Recommendation:** Add `min:8` to password, require at least one role, consider requiring dept/division for non-admin roles.
**Confidence:** medium

#### [Low] Survey rating fields aren't restricted to the expected enum
**File:** `app/Http/Controllers/SurveyController.php:37-40`
**Issue:** Four rating fields validated as `required|string` only, not restricted to the 4 labels the reporting code assumes.
**Risk / impact:** `SurveyReportsController.php:98-146` counts by exact string match against `'Very Dissatisfied'`/etc. — a wrong-case or typo submission saves successfully but is silently excluded from every rating bucket, undercounting reports with no visible error.
**Recommendation:** `'required|in:Very Dissatisfied,Dissatisfied,Satisfied,Very Satisfied'`.
**Confidence:** medium

#### [Low] Web upload doesn't validate policy-document category against the CLI's whitelist
**File:** `HrPolicyDocumentCrudController.php:95,133` vs. `Console/Commands/IngestHrPolicyPdf.php:64-70`
**Issue:** Web path validates `category` as `required|string` (any value); the CLI ingestion path enforces a fixed whitelist for the same field.
**Risk / impact:** A typo'd category via the (more commonly used) web form produces documents that can't be filtered/found by category later.
**Recommendation:** Add the same `in:` whitelist to `uploadStore()`/`updateStore()`.
**Confidence:** medium

### 3d. Consistency issues

#### [Medium] Hardcoded status IDs bypass the name-based lookup used everywhere else
**File:** `app/Models/User.php:101` (`resolvedTickets`), `:117` (`overdueTickets`); `app/Models/Ticket.php:62` (`creating` hook)
**Issue:** These two `User` methods hardcode `status_id = 1` for "Resolved" while every other place in the codebase (`TicketCrudController`, `ReportsController`, `Ticket`'s own `saving()` hook, `TicketAutoAssignmentService`, `TicketReportWidgets`) resolves it dynamically via `Status::where('status_name', 'Resolved')->value('id')`. `Ticket.php`'s `creating` hook similarly hardcodes `status_id = 3` for the new-ticket default instead of a name lookup.
**Risk / impact:** The same class of bug as the already-fixed role-name-casing issue: a value that must exactly match a seeded definition is duplicated as a magic literal. If `StatusTableSeeder`'s insertion order ever changes, or a fresh/test environment seeds in a different sequence, these two methods (and everything derived from them — dashboard "resolved" counts, staff avg-resolution-time column) would silently count the wrong status with no error, and new tickets would default to whatever status happens to hold ID 3.
**Recommendation:** Replace both with `Status::where('status_name', 'Resolved'/'Unassigned')->value('id')` lookups (statically cached if perf matters), matching the pattern used consistently elsewhere.
**Confidence:** high

#### [Low] Attachment size error message doesn't match the actual limit
**File:** `TicketRequest.php:46` (message) vs. `:20-24` (rule)
**Issue:** Rule allows `max:20480` (20MB); the validation message still says "must not exceed 2MB."
**Recommendation:** Update the message text to 20MB.
**Confidence:** high

#### [Low] Inconsistent attachment size limits per upload path
**File:** `TicketChat.php:60` (10MB, no mime check) vs. `TicketRequest.php:20` (20MB, mime-restricted)
**Issue:** Same conceptual feature (ticket attachment), two different silent size policies depending on whether it's the initial ticket submission or a later comment.
**Risk / impact:** A file accepted on ticket creation can be confusingly rejected when attached to a follow-up comment on the same ticket.
**Recommendation:** Extract one shared rule set both paths reference.
**Confidence:** medium

### 3e. Test coverage inventory

| Feature area | Covered? | Test file(s) |
|---|---|---|
| Ticket create (HTTP `store()`, validation, attachment upload) | **Gap** | none — existing tests create tickets via a model helper, never through the controller's create endpoint |
| Ticket edit/update | Yes | `TicketReassignmentTest::test_t12_normal_full_edit_regression` |
| Ticket delete | Yes | `TicketReassignmentTest::test_t11_deleting_ticket_with_reassignment_requests_does_not_fk_error` |
| Ticket assignment / reassignment (request, accept, reject, cancel, quick-assign, auto-assignment) | Yes (extensive) | `TicketReassignmentTest` (T1–T16), `UserAssignedTicketsTest` |
| Ticket comments/chat (`TicketChat` Livewire component) | **Gap** | none |
| HR chatbot Q&A | Yes | `HrConversationTest`, `HrChatConversationMemoryTest` |
| Conversation memory | Yes | `HrChatConversationMemoryTest`, `HrConversationTest` |
| Reports page widgets (KPIs, trend, SLA, funnel, staff workload, period control, PDF) | Yes (extensive) | `ReportsWidgetsTest`, `ReportsPageWidgetsRenderTest`, `ReportsPeriodControlTest`, `ReportsStaffWorkloadTest`, `ReportsPdfRegressionTest` |
| Dashboard | **Gap** | none |
| Survey reports (submission form + report page) | Partial | report page load only (`ReportsPdfRegressionTest::test_survey_and_arta_report_pages_still_load_on_mssql`); `SurveyController::submitForm` itself untested |
| ARTA survey reports (submission form + report page) | Partial | same as above; `ArtaController::submitForm` itself untested |
| User / role / permission management (CRUD controllers) | **Gap** | none |
| OCR upload / policy ingestion (`OcrService`, `PolicyIngestService`) | **Gap** | none — `HrPolicyDocument` appears only as a fixture in `HrConversationTest`, not testing upload/OCR logic itself |
| Notifications | Partial | reassignment-specific notifications covered (`TicketReassignmentTest` T13–T16); base ticket-created/assigned/status-changed/commented notifications and `NotificationController`'s feed/markAsRead/clearAll endpoints untested |
| `SqlDialectHelper` | Yes | `tests/Unit/SqlDialectHelperTest.php` |

---

## Appendix A — `composer audit` full output (verbatim)

```
Found 27 security vulnerability advisories affecting 8 packages:
Package: dompdf/dompdf
Severity: medium
CVE: CVE-2026-59943
Title: Dompdf: Embedded SVG images can leak existence of files and directories within the filesystem
URL: https://github.com/advisories/GHSA-j8qw-6jw8-r297
Affected versions: <3.1.6
Reported at: 2026-07-22T22:52:44+00:00
--------
Package: dompdf/dompdf
Severity: medium
CVE: CVE-2026-59942
Title: Dompdf: Denial of Service (DoS) via Resource Exhaustion using Oversized Image Bitmaps
URL: https://github.com/advisories/GHSA-f5gf-2cj8-52g2
Affected versions: <3.1.6
Reported at: 2026-07-22T22:51:40+00:00
--------
Package: dompdf/dompdf
Severity: medium
CVE: CVE-2026-59941
Title: Dompdf: Uncontrolled resource consumption based on declared BMP dimensions
URL: https://github.com/advisories/GHSA-8hg6-c449-896m
Affected versions: <3.1.6
Reported at: 2026-07-22T22:50:34+00:00
--------
Package: dompdf/dompdf
Severity: medium
CVE: CVE-2026-56722
Title: Dompdf: Local file read due to improper file path validation in SVG images encoded as data-URI
URL: https://github.com/advisories/GHSA-cx96-42px-69fm
Affected versions: <3.1.6
Reported at: 2026-07-22T21:30:01+00:00
--------
Package: dompdf/dompdf
Severity: low
CVE: CVE-2026-55555
Title: Dompdf: File existence oracle via font-face stylesheet declaration
URL: https://github.com/advisories/GHSA-7x2p-4jvh-6384
Affected versions: <3.1.6
Reported at: 2026-07-22T21:07:07+00:00
--------
Package: dompdf/dompdf
Severity: low
CVE: CVE-2026-55554
Title: Dompdf: Chroot Validation Bypass
URL: https://github.com/advisories/GHSA-wvh6-f5jh-8gw4
Affected versions: <3.1.6
Reported at: 2026-07-22T21:06:48+00:00
--------
Package: guzzlehttp/guzzle
Severity: medium
CVE: NO CVE
Title: Guzzle: URI fragments disclosed in redirect Referer headers
URL: https://github.com/advisories/GHSA-h95v-h523-3mw8
Affected versions: <7.15.1
Reported at: 2026-07-20T23:28:36+00:00
--------
Package: guzzlehttp/guzzle
Severity: medium
CVE: NO CVE
Title: Guzzle: Host-only cookie scope is not preserved
URL: https://github.com/advisories/GHSA-wm3w-8rrp-j577
Affected versions: <7.15.1
Reported at: 2026-07-20T23:27:49+00:00
--------
Package: guzzlehttp/guzzle
Severity: medium
CVE: NO CVE
Title: Guzzle: Unbounded response cookies risk denial of service
URL: https://github.com/advisories/GHSA-f283-ghqc-fg79
Affected versions: <7.15.1
Reported at: 2026-07-20T23:27:02+00:00
--------
Package: guzzlehttp/guzzle
Severity: medium
CVE: CVE-2026-59883
Title: Guzzle: Cookie Disclosure and Injection via IP-Address Domains
URL: https://github.com/advisories/GHSA-g446-98w2-8p5w
Affected versions: <7.12.3
Reported at: 2026-07-20T22:00:09+00:00
--------
Package: guzzlehttp/guzzle
Severity: medium
CVE: NO CVE
Title: Guzzle: Proxy-Authorization headers can be sent to origin servers
URL: https://github.com/advisories/GHSA-94pj-82f3-465w
Affected versions: <7.14.2
Reported at: 2026-07-20T21:46:02+00:00
--------
Package: guzzlehttp/guzzle
Severity: medium
CVE: CVE-2026-55767
Title: Dot-only cookie domains match all hosts
URL: https://github.com/guzzle/guzzle/security/advisories/GHSA-cwxw-98qj-8qjx
Affected versions: <7.12.1
Reported at: 2026-06-18T14:12:49+00:00
--------
Package: guzzlehttp/guzzle
Severity: medium
CVE: CVE-2026-55568
Title: Silent HTTPS proxy downgrade to cleartext
URL: https://github.com/guzzle/guzzle/security/advisories/GHSA-wpwq-4j6v-78m3
Affected versions: <7.12.1
Reported at: 2026-06-18T14:12:49+00:00
--------
Package: guzzlehttp/psr7
Severity: medium
CVE: CVE-2026-59882
Title: guzzlehttp/psr7: Host Confusion via Weak URI Host Validation
URL: https://github.com/advisories/GHSA-c2w2-prh8-qm98
Affected versions: <2.12.3
Reported at: 2026-07-21T18:35:25+00:00
--------
Package: guzzlehttp/psr7
Severity: medium
CVE: CVE-2026-55766
Title: CRLF injection in HTTP start-line serialization
URL: https://github.com/guzzle/psr7/security/advisories/GHSA-vm85-hxw5-5432
Affected versions: <2.12.1
Reported at: 2026-06-18T09:49:37+00:00
--------
Package: guzzlehttp/psr7
Severity: medium
CVE: CVE-2026-49214
Title: CRLF injection via URI host component
URL: https://github.com/guzzle/psr7/security/advisories/GHSA-hq7v-mx3g-29hw
Affected versions: <2.10.2
Reported at: 2026-05-25T22:58:15+00:00
--------
Package: guzzlehttp/psr7
Severity: medium
CVE: CVE-2026-48998
Title: Host confusion via authority reinterpretation
URL: https://github.com/guzzle/psr7/security/advisories/GHSA-34xg-wgjx-8xph
Affected versions: <2.10.2
Reported at: 2026-05-25T22:58:15+00:00
--------
Package: laravel/framework
Severity: medium
CVE: NO CVE
Title: Laravel Framework: Temporary Signed URL Path Confusion
URL: https://github.com/advisories/GHSA-crmm-hgp2-wgrp
Affected versions: <12.61.1|>=13.0.0,<13.12.0
Reported at: 2026-06-17T13:54:13+00:00
--------
Package: laravel/framework
Severity: high
CVE: NO CVE
Title: Laravel Framework: CRLF injection in default email rule
URL: https://github.com/advisories/GHSA-5vg9-5847-vvmq
Affected versions: <12.60.0|>=13.0.0,<=13.9.0
Reported at: 2026-06-17T13:53:44+00:00
--------
Package: laravel/framework
Severity: medium
CVE: CVE-2025-27515
Title: Laravel has a File Validation Bypass
URL: https://github.com/advisories/GHSA-78fx-h6xr-vch4
Affected versions: <10.48.29|>=11.0.0,<11.44.1|>=12.0.0,<12.1.1
Reported at: 2025-03-05T19:09:39+00:00
--------
Package: league/commonmark
Severity: medium
CVE: CVE-2026-33347
Title: league/commonmark has an embed extension allowed_domains bypass
URL: https://github.com/advisories/GHSA-hh8v-hgvp-g3f5
Affected versions: >=2.3.0,<=2.8.1
Reported at: 2026-03-19T19:04:24+00:00
--------
Package: league/commonmark
Severity: medium
CVE: CVE-2026-30838
Title: CommonMark has DisallowedRawHtml extension bypass via whitespace in HTML tag names
URL: https://github.com/advisories/GHSA-4v6x-c7xx-hw9f
Affected versions: >=2.0.0,<=2.8.0
Reported at: 2026-03-06T23:27:03+00:00
--------
Package: symfony/mime
Severity: medium
CVE: CVE-2026-45070
Title: CVE-2026-45070: Email Header Injection via Non-Token Characters in Mime Parameter Names
URL: https://symfony.com/cve-2026-45070
Affected versions: (all ranges up to) <8.0.12
Reported at: 2026-05-20T08:00:00+00:00
--------
Package: symfony/mime
Severity: high
CVE: CVE-2026-45067
Title: CVE-2026-45067: Email Header / SMTP Command Injection via CRLF in Symfony\Component\Mime\Address
URL: https://symfony.com/cve-2026-45067
Affected versions: (all ranges up to) <8.0.12
Reported at: 2026-05-20T08:00:00+00:00
--------
Package: symfony/polyfill-intl-idn
Severity: low
CVE: CVE-2026-46644
Title: symfony/polyfill-intl-idn accepts xn-- labels whose Punycode payload decodes to ASCII-only: insecure equivalence
URL: https://symfony.com/cve-2026-46644
Affected versions: >=1.17.1,<1.38.1
Reported at: 2026-05-26T08:00:00+00:00
--------
Package: symfony/routing
Severity: medium
CVE: CVE-2026-48784
Title: UrlGenerator Dot-Segment Encoding Skips Every Other Chained '../' or './' -> Generated URL Collapses Off-Route Under RFC 3986 Normalization
URL: https://symfony.com/cve-2026-48784
Affected versions: (all ranges up to) <8.0.13
Reported at: 2026-05-26T08:00:00+00:00
--------
Package: symfony/routing
Severity: medium
CVE: CVE-2026-45065
Title: UrlGenerator Route-Requirement Bypass via Unanchored Regex Alternation -> Off-Site //host URL Injection
URL: https://symfony.com/cve-2026-45065
Affected versions: (all ranges up to) <8.0.12
Reported at: 2026-05-20T08:00:00+00:00

Found 2 abandoned packages:
fruitcake/laravel-cors (v2.2.0) is abandoned. No replacement was suggested.
swiftmailer/swiftmailer (v6.3.0) is abandoned. Use symfony/mailer instead.
```

`npm audit`/`yarn audit`: not applicable — no `package-lock.json` or `yarn.lock` exists next to the root `package.json`.

---

## Top 3 Recommended Next Actions

1. **Fix the Department/Division/Issue CRUD authorization gap and the ticket-comment attachment MIME check** (both Security-High). These are the two findings closest to being genuinely exploitable today by an existing, legitimately-provisioned account (`dept_head`) rather than requiring an external attacker — small, targeted fixes, high value.
2. **Rotate the seeded `12341234` password and gate `UsersTableSeeder` behind an environment check**, then update the two vulnerable dependencies with High-severity CVEs (`symfony/mime`, `laravel/framework`). All are credential/supply-chain exposure, not logic bugs — cheap to fix, high blast radius if left.
3. **Add the missing indexes on `tickets`, `ticket_reassignment_requests`, and `users`, plus the one-line `->with()` fix on the dashboard's Latest Tickets query.** Nothing hurts today at current data volume, but this is the one category of finding that will silently turn into hard-to-diagnose production slowness with no warning as ticket volume grows — worth doing while it's still cheap and low-risk.
