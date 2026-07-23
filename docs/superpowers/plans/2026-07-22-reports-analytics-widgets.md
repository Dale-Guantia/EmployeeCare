# Reports Analytics Widgets + MSSQL SQL Cleanup Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add 7 analytics widgets to the ticketing Reports page and make every report-page SQL query MSSQL-safe, without touching the PDF export's output.

**Architecture:** `ReportsController::getReportData()` grows a second data-collection phase: one Eloquent pull of in-range tickets (`$widgetTickets`, eager-loading `department`/`issue`) feeds six PHP-computed widgets (KPIs, volume trend, resolution distribution, SLA breakdown, status funnel via a portable `GROUP BY`, reassignment rate). A seventh widget (staff workload) extends the existing `$users` query in place. All datetime math happens in Carbon over pulled rows; the one unavoidable SQL-level date diff (`User::overdueTickets()`) is extracted into a shared `SqlDialectHelper` service. The Reports blade gets new Bootstrap 4 cards/charts above the existing tables, using the same Chart.js CDN and color palette as the admin dashboard. The PDF path is untouched at the template level.

**Tech Stack:** Laravel 8, Eloquent, SQL Server (`sqlsrv` driver, confirmed live), Chart.js (CDN, unpinned) + chartjs-plugin-datalabels@2, DataTables (already present), PHPUnit feature tests with `DatabaseTransactions` against the live MSSQL DB (this project's existing test convention — see `tests/Feature/TicketReassignmentTest.php`).

## Global Constraints

- Ticket statuses are exactly `Unassigned`(3), `Pending`(2), `Resolved`(1), `Reopened`(4) — confirmed against the live DB. Never introduce another stage.
- SLA = 72 hours / 3 days: open ticket with `created_at` older than `now()->subDays(3)` is overdue; resolved ticket where `created_at`→`resolved_at` > 72h is "resolved overdue". Reuse this exact rule (mirrors `Ticket::getOverdueBadgeAttribute()`).
- Never write `TIMESTAMPDIFF`, `DATE_FORMAT`, `STR_TO_DATE`, `GROUP_CONCAT`, `IFNULL`, or `DATEDIFF` directly in new code — the only two places a SQL date-diff is allowed are inside `SqlDialectHelper` methods.
- Every date-diff based metric (resolution time, overdue-ness, buckets) is computed in PHP/Carbon over pulled rows — never `GROUP BY DATE(...)` or `DATE_FORMAT(...)`.
- SQL Server requires every non-aggregated selected column to appear in `GROUP BY`. Verify this for any grouped query touched or added.
- Do not edit `resources/views/admin/pdf/reports_pdf.blade.php`. New `getReportData()` keys must be additive only — existing keys (`users`, `latestTickets`, `ticketOverview`, `divisions`) keep their current shape.
- Do not remove or restructure the existing `reports.blade.php` tables (`latestTicketsTable`, `userActivityTable`, `ticketsPerDivisionTable`) — only extend `userActivityTable` with new columns and add new cards above them.
- PHP compatibility floor is `^7.3|^8.0` (composer.json) — do not use arrow functions (`fn() =>`) or any 7.4+/8-only syntax; use regular closures.
- New widget queries default their date window to the **last 30 days** when `getReportData()` is called with no explicit range (the on-screen page); when a range is passed (PDF download), the widgets honor it. This window (`$widgetStart`/`$widgetEnd`) is separate from the existing `$startDate`/`$endDate` used by `users`/`ticketOverview`/`divisions`, which keep their current all-time-on-screen / ranged-in-PDF behavior unchanged.
- Widget 6 (staff workload) is the one exception: its new fields reuse the *existing* `$startDate`/`$endDate` (nullable) semantics already used by the `$users` query block, not the new `$widgetStart`/`$widgetEnd` default — because it's described as extending that existing block, not a new time window.

---

### Task 1: Shared SQL dialect helper + refactor existing inline branches

**Files:**
- Create: `app/Services/SqlDialectHelper.php`
- Modify: `app/Models/User.php:12-13` (imports), `app/Models/User.php:104-123` (`overdueTickets()`)
- Modify: `app/Http/Controllers/Admin/ReportsController.php:10` (imports), `app/Http/Controllers/Admin/ReportsController.php:108-110` (`$diffMinutesSql`)
- Test: `tests/Unit/SqlDialectHelperTest.php`

**Interfaces:**
- Produces: `App\Services\SqlDialectHelper::diffHoursSql(string $start, string $end): string` and `::diffMinutesSql(string $start, string $end): string` — both return a driver-correct SQL fragment (`DATEDIFF(UNIT, start, end)` on `sqlsrv`, `TIMESTAMPDIFF(UNIT, start, end)` otherwise). Later tasks do not consume these directly (only Task 1 uses them), but they must keep this exact signature since they are the "intentional dialect helper" the final re-grep (Task 8) whitelists.

- [ ] **Step 1: Write the failing unit test**

```php
<?php

namespace Tests\Unit;

use App\Services\SqlDialectHelper;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class SqlDialectHelperTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_diff_hours_sql_uses_datediff_on_sqlsrv()
    {
        $connection = Mockery::mock();
        $connection->shouldReceive('getDriverName')->andReturn('sqlsrv');
        DB::shouldReceive('connection')->andReturn($connection);

        $sql = SqlDialectHelper::diffHoursSql('created_at', 'resolved_at');

        $this->assertSame('DATEDIFF(HOUR, created_at, resolved_at)', $sql);
    }

    public function test_diff_hours_sql_uses_timestampdiff_on_mysql()
    {
        $connection = Mockery::mock();
        $connection->shouldReceive('getDriverName')->andReturn('mysql');
        DB::shouldReceive('connection')->andReturn($connection);

        $sql = SqlDialectHelper::diffHoursSql('created_at', 'resolved_at');

        $this->assertSame('TIMESTAMPDIFF(HOUR, created_at, resolved_at)', $sql);
    }

    public function test_diff_minutes_sql_uses_datediff_on_sqlsrv()
    {
        $connection = Mockery::mock();
        $connection->shouldReceive('getDriverName')->andReturn('sqlsrv');
        DB::shouldReceive('connection')->andReturn($connection);

        $sql = SqlDialectHelper::diffMinutesSql('tickets.created_at', 'tickets.resolved_at');

        $this->assertSame('DATEDIFF(MINUTE, tickets.created_at, tickets.resolved_at)', $sql);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SqlDialectHelperTest`
Expected: FAIL — `Class "App\Services\SqlDialectHelper" not found`

- [ ] **Step 3: Create the helper**

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class SqlDialectHelper
{
    public static function diffHoursSql(string $start, string $end): string
    {
        return self::isSqlServer()
            ? "DATEDIFF(HOUR, {$start}, {$end})"
            : "TIMESTAMPDIFF(HOUR, {$start}, {$end})";
    }

    public static function diffMinutesSql(string $start, string $end): string
    {
        return self::isSqlServer()
            ? "DATEDIFF(MINUTE, {$start}, {$end})"
            : "TIMESTAMPDIFF(MINUTE, {$start}, {$end})";
    }

    private static function isSqlServer(): bool
    {
        return DB::connection()->getDriverName() === 'sqlsrv';
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=SqlDialectHelperTest`
Expected: PASS (3 tests)

- [ ] **Step 5: Refactor `User::overdueTickets()` to use the helper**

In `app/Models/User.php`, remove the now-unused `use Illuminate\Support\Facades\DB;` import (line 13; it is used nowhere else in this file) and add `use App\Services\SqlDialectHelper;`. Replace the method body:

```php
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

- [ ] **Step 6: Refactor `ReportsController::getReportData()`'s `ticketOverview` to use the helper**

In `app/Http/Controllers/Admin/ReportsController.php`, add `use App\Services\SqlDialectHelper;` to the imports. Replace lines 108-110:

```php
        $diffMinutesSql = SqlDialectHelper::diffMinutesSql('tickets.created_at', 'tickets.resolved_at');
```

- [ ] **Step 7: Confirm the existing reassignment/overdue-badge feature tests still pass (regression check)**

Run: `php artisan test --filter=TicketReassignmentTest`
Run: `php artisan test --filter=TicketPerformanceTest`
Expected: PASS, unchanged from before this task (behavior is identical — this is a pure extraction, not a logic change).

- [ ] **Step 8: Commit**

```bash
git add app/Services/SqlDialectHelper.php app/Models/User.php app/Http/Controllers/Admin/ReportsController.php tests/Unit/SqlDialectHelperTest.php
git commit -m "refactor: extract shared MSSQL/MySQL date-diff helper for reports SQL"
```

---

### Task 2: `User::assignedTickets()` relation

**Files:**
- Modify: `app/Models/User.php` (add method near `resolvedTickets()`/`overdueTickets()`)
- Test: `tests/Feature/UserAssignedTicketsTest.php`

**Interfaces:**
- Consumes: nothing new.
- Produces: `User::assignedTickets(): HasMany` — tickets where `tickets.assigned_to = users.id`. Task 4 (staff workload widget) uses this via `withCount(['assignedTickets as assigned_count' => ...])`.

- [ ] **Step 1: Write the failing feature test**

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

class UserAssignedTicketsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_assigned_tickets_returns_tickets_assigned_to_the_user()
    {
        $dept = Department::create(['department_name' => 'AssignDept_' . uniqid()]);
        $div = Division::create(['division_name' => 'AssignDiv_' . uniqid(), 'department_id' => $dept->id]);
        $priority = Priority::firstOrCreate(['priority_name' => 'Normal'], ['priority_color' => '#ccc']);
        $status = Status::firstOrCreate(['status_name' => 'Pending'], ['status_color' => '#ccc']);
        $issue = Issue::create([
            'department_id' => $dept->id,
            'division_id' => $div->id,
            'priority_id' => $priority->id,
            'issue_description' => 'Assign Test Issue ' . uniqid(),
        ]);

        $staff = User::create([
            'name' => 'AssignStaff_' . uniqid(),
            'username' => 'assign_staff_' . uniqid(),
            'email' => 'assign_staff_' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
            'department_id' => $dept->id,
            'division_id' => $div->id,
            'is_active' => true,
        ]);

        $ticket = new Ticket();
        $ticket->forceFill([
            'department_id' => $dept->id,
            'division_id' => $div->id,
            'issue_id' => $issue->id,
            'status_id' => $status->id,
            'message' => 'assigned ticket test',
            'assigned_to' => $staff->id,
        ]);
        $ticket->save();

        $this->assertSame(1, $staff->assignedTickets()->count());
        $this->assertSame($ticket->id, $staff->assignedTickets()->first()->id);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=UserAssignedTicketsTest`
Expected: FAIL — `Call to undefined method App\Models\User::assignedTickets()`

- [ ] **Step 3: Add the relation**

In `app/Models/User.php`, add directly after `overdueTickets()`:

```php
    public function assignedTickets()
    {
        return $this->hasMany(Ticket::class, 'assigned_to');
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=UserAssignedTicketsTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Models/User.php tests/Feature/UserAssignedTicketsTest.php
git commit -m "feat: add User::assignedTickets relation for staff workload widget"
```

---

### Task 3: Backend — widgets 1, 2, 3, 4, 5, 7 (KPIs, volume trend, resolution distribution, SLA breakdown, status funnel, reassignment)

**Files:**
- Modify: `app/Http/Controllers/Admin/ReportsController.php` (imports, `getReportData()` body, new private methods)
- Test: `tests/Feature/ReportsWidgetsTest.php`

**Interfaces:**
- Consumes: `SqlDialectHelper` (already imported in Task 1), `App\Models\Status`, `App\Models\TicketReassignmentRequest`.
- Produces (new keys returned by `getReportData()`, consumed by Task 5/6 blade work):
  - `reportKpis`: `['total','resolved','open','avg_resolution_minutes','avg_resolution_formatted','overdue_count','overdue_pct','reopen_count','reopen_rate']`
  - `reportVolumeTrend`: `['labels' => [...'YYYY-MM-DD'], 'counts' => [...int]]`
  - `reportResolutionDistribution`: `['labels' => ['<1 day','1-3 days','3-7 days','7+ days'], 'counts' => [...int]]`
  - `reportSlaBreakdown`: `['overdue_count','on_time_count','by_department' => [name => ['total','overdue']], 'by_issue' => [name => ['total','overdue']], 'department_labels','department_overdue','department_on_time']`
  - `reportStatusFunnel`: `['labels' => ['Unassigned','Pending','Resolved','Reopened'], 'counts' => [...int]]`
  - `reportReassignment`: `['reassigned_count','total','rate','by_department' => [name => ['total','reassigned','rate']], 'department_labels','department_rates']`
  - `reportWidgetStart`, `reportWidgetEnd`: Carbon instances of the effective window.
  - Also produces private helper `formatMinutes(int $totalMinutes): string` and `isTicketOverdue($ticket, int $resolvedStatusId): bool`, both reused internally by Task 4.

- [ ] **Step 1: Write the failing feature test**

```php
<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Division;
use App\Models\Issue;
use App\Models\Priority;
use App\Models\Status;
use App\Models\Ticket;
use App\Models\TicketReassignmentRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ReportsWidgetsTest extends TestCase
{
    use DatabaseTransactions;

    private function makeAdmin(): User
    {
        $admin = User::role('admin')->first();
        $this->assertNotNull($admin, 'Expected at least one admin user to exist.');

        Permission::firstOrCreate(['name' => 'reports.view', 'guard_name' => 'web']);
        if (! $admin->can('reports.view')) {
            $admin->givePermissionTo('reports.view');
        }

        return $admin;
    }

    private function makeDeptDiv(string $tag): array
    {
        $dept = Department::create(['department_name' => "RptDept{$tag}_" . uniqid()]);
        $div = Division::create(['division_name' => "RptDiv{$tag}_" . uniqid(), 'department_id' => $dept->id]);

        return [$dept, $div];
    }

    public function test_reports_page_returns_widget_data_with_known_counts()
    {
        $admin = $this->makeAdmin();
        [$dept, $div] = $this->makeDeptDiv('A');
        $priority = Priority::firstOrCreate(['priority_name' => 'Normal'], ['priority_color' => '#ccc']);
        $unassigned = Status::firstOrCreate(['status_name' => 'Unassigned'], ['status_color' => '#ccc']);
        $resolved = Status::firstOrCreate(['status_name' => 'Resolved'], ['status_color' => '#ccc']);
        $issue = Issue::create([
            'department_id' => $dept->id,
            'division_id' => $div->id,
            'priority_id' => $priority->id,
            'issue_description' => 'Widget Test Issue ' . uniqid(),
        ]);

        $now = Carbon::now();

        // Ticket 1: open, created 5 days ago -> overdue (case A)
        $t1 = new Ticket();
        $t1->forceFill([
            'department_id' => $dept->id,
            'division_id' => $div->id,
            'issue_id' => $issue->id,
            'status_id' => $unassigned->id,
            'message' => 'overdue open ticket',
        ]);
        $t1->save();
        $t1->created_at = $now->copy()->subDays(5);
        $t1->saveQuietly();

        // Ticket 2: resolved within SLA (created 1 day ago, resolved now -> 24h)
        $t2 = new Ticket();
        $t2->forceFill([
            'department_id' => $dept->id,
            'division_id' => $div->id,
            'issue_id' => $issue->id,
            'status_id' => $unassigned->id,
            'message' => 'on-time resolved ticket',
        ]);
        $t2->save();
        $t2->created_at = $now->copy()->subDay();
        $t2->saveQuietly();
        $t2->status_id = $resolved->id;
        $t2->save();
        $t2->resolved_at = $now;
        $t2->saveQuietly();

        // Reassignment request against ticket 1
        TicketReassignmentRequest::create([
            'ticket_id' => $t1->id,
            'from_department_id' => $dept->id,
            'to_department_id' => $dept->id,
            'requested_by' => $admin->id,
            'status' => TicketReassignmentRequest::STATUS_PENDING,
        ]);

        $this->actingAs($admin, 'web');
        $response = $this->get(route('page.reports.index'));

        $response->assertStatus(200);
        $response->assertViewHas('reportKpis');
        $response->assertViewHas('reportVolumeTrend');
        $response->assertViewHas('reportResolutionDistribution');
        $response->assertViewHas('reportSlaBreakdown');
        $response->assertViewHas('reportStatusFunnel');
        $response->assertViewHas('reportReassignment');

        $kpis = $response->viewData('reportKpis');
        $this->assertGreaterThanOrEqual(2, $kpis['total']);
        $this->assertGreaterThanOrEqual(1, $kpis['overdue_count']);

        $funnel = $response->viewData('reportStatusFunnel');
        $this->assertSame(['Unassigned', 'Pending', 'Resolved', 'Reopened'], $funnel['labels']);

        $reassignment = $response->viewData('reportReassignment');
        $this->assertGreaterThanOrEqual(1, $reassignment['reassigned_count']);
    }

    public function test_resolution_distribution_buckets_sum_to_resolved_count()
    {
        $admin = $this->makeAdmin();
        [$dept, $div] = $this->makeDeptDiv('B');
        $priority = Priority::firstOrCreate(['priority_name' => 'Normal'], ['priority_color' => '#ccc']);
        $unassigned = Status::firstOrCreate(['status_name' => 'Unassigned'], ['status_color' => '#ccc']);
        $resolved = Status::firstOrCreate(['status_name' => 'Resolved'], ['status_color' => '#ccc']);
        $issue = Issue::create([
            'department_id' => $dept->id,
            'division_id' => $div->id,
            'priority_id' => $priority->id,
            'issue_description' => 'Bucket Test Issue ' . uniqid(),
        ]);

        $now = Carbon::now();

        $ticket = new Ticket();
        $ticket->forceFill([
            'department_id' => $dept->id,
            'division_id' => $div->id,
            'issue_id' => $issue->id,
            'status_id' => $unassigned->id,
            'message' => 'bucket test ticket',
        ]);
        $ticket->save();
        $ticket->created_at = $now->copy()->subHours(10);
        $ticket->saveQuietly();
        $ticket->status_id = $resolved->id;
        $ticket->save();
        $ticket->resolved_at = $now;
        $ticket->saveQuietly();

        $this->actingAs($admin, 'web');
        $response = $this->get(route('page.reports.index'));

        $distribution = $response->viewData('reportResolutionDistribution');
        $totalBucketed = array_sum($distribution['counts']);

        $kpis = $response->viewData('reportKpis');
        $this->assertSame($kpis['resolved'], $totalBucketed);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ReportsWidgetsTest`
Expected: FAIL — `assertViewHas('reportKpis')` fails, key not present in the view data.

- [ ] **Step 3: Implement the widgets in `ReportsController`**

Add imports at the top of `app/Http/Controllers/Admin/ReportsController.php`:

```php
use App\Models\Status;
use App\Models\TicketReassignmentRequest;
```

Insert the following block into `getReportData()` immediately before the final `return [...]` (i.e., after the existing `$divisions` computation, keeping everything above it untouched):

```php
        $resolvedStatusId = (int) Status::where('status_name', 'Resolved')->value('id');
        $reopenedStatusId = (int) Status::where('status_name', 'Reopened')->value('id');

        // On-screen page (no explicit range passed) defaults these widgets to
        // the last 30 days so the page isn't an unbounded all-time dump.
        // A PDF download always passes an explicit range, which is honored as-is.
        $widgetStart = $startDate ? $startDate->copy() : now()->subDays(30)->startOfDay();
        $widgetEnd = $endDate ? $endDate->copy() : now()->endOfDay();

        $widgetTickets = Ticket::with(['department', 'issue'])
            ->whereBetween('created_at', [$widgetStart, $widgetEnd])
            ->get(['id', 'status_id', 'department_id', 'issue_id', 'custom_issue', 'assigned_to', 'created_at', 'resolved_at', 'reopened_at']);

        $reportKpis = $this->buildKpiWidget($widgetTickets, $resolvedStatusId, $reopenedStatusId);
        $reportVolumeTrend = $this->buildVolumeTrendWidget($widgetTickets, $widgetStart, $widgetEnd);
        $reportResolutionDistribution = $this->buildResolutionDistributionWidget($widgetTickets, $resolvedStatusId);
        $reportSlaBreakdown = $this->buildSlaBreakdownWidget($widgetTickets, $resolvedStatusId);
        $reportStatusFunnel = $this->buildStatusFunnelWidget($widgetStart, $widgetEnd);
        $reportReassignment = $this->buildReassignmentWidget($widgetTickets, $widgetStart, $widgetEnd);
```

Change the existing `return [...]` to:

```php
        return [
            'users' => $users,
            'latestTickets' => $latestTickets,
            'ticketOverview' => $ticketOverview,
            'divisions' => $divisions,
            'reportKpis' => $reportKpis,
            'reportVolumeTrend' => $reportVolumeTrend,
            'reportResolutionDistribution' => $reportResolutionDistribution,
            'reportSlaBreakdown' => $reportSlaBreakdown,
            'reportStatusFunnel' => $reportStatusFunnel,
            'reportReassignment' => $reportReassignment,
            'reportWidgetStart' => $widgetStart,
            'reportWidgetEnd' => $widgetEnd,
        ];
```

Add these private methods to the class, after `getReportData()`:

```php
    private function isTicketOverdue($ticket, int $resolvedStatusId): bool
    {
        $createdAt = Carbon::parse($ticket->created_at);

        if ((int) $ticket->status_id === $resolvedStatusId) {
            if (!$ticket->resolved_at) {
                return false;
            }

            return $createdAt->diffInHours(Carbon::parse($ticket->resolved_at)) > 72;
        }

        return $createdAt->lt(now()->subDays(3));
    }

    private function formatMinutes(int $totalMinutes): string
    {
        $hours = intdiv($totalMinutes, 60);
        $minutes = $totalMinutes % 60;

        return sprintf('%dh %02dm', $hours, $minutes);
    }

    private function buildKpiWidget($tickets, int $resolvedStatusId, int $reopenedStatusId): array
    {
        $total = $tickets->count();

        $resolvedTickets = $tickets->filter(function ($ticket) use ($resolvedStatusId) {
            return (int) $ticket->status_id === $resolvedStatusId;
        });
        $resolved = $resolvedTickets->count();
        $open = $total - $resolved;

        $resolutionMinutes = $resolvedTickets
            ->filter(function ($ticket) {
                return $ticket->resolved_at !== null;
            })
            ->map(function ($ticket) {
                return Carbon::parse($ticket->created_at)->diffInMinutes(Carbon::parse($ticket->resolved_at));
            });

        $avgResolutionMinutes = $resolutionMinutes->count() > 0
            ? (int) round($resolutionMinutes->avg())
            : 0;

        $overdueCount = $tickets->filter(function ($ticket) use ($resolvedStatusId) {
            return $this->isTicketOverdue($ticket, $resolvedStatusId);
        })->count();

        // NOTE: Ticket::booted()'s saving() hook clears reopened_at whenever a
        // ticket transitions out of the Reopened status for any reason other
        // than resolving it. That means there is no persistent "was ever
        // reopened" flag once a re-reopened ticket is resolved/reassigned
        // again — only tickets *currently* Reopened are detectable here. This
        // matches the spec's stated rule (non-null reopened_at OR current
        // status = Reopened) but undercounts historical reopens.
        $reopenedCount = $tickets->filter(function ($ticket) use ($reopenedStatusId) {
            return (int) $ticket->status_id === $reopenedStatusId || $ticket->reopened_at !== null;
        })->count();

        return [
            'total' => $total,
            'resolved' => $resolved,
            'open' => $open,
            'avg_resolution_minutes' => $avgResolutionMinutes,
            'avg_resolution_formatted' => $this->formatMinutes($avgResolutionMinutes),
            'overdue_count' => $overdueCount,
            'overdue_pct' => $total > 0 ? round(($overdueCount / $total) * 100, 1) : 0.0,
            'reopen_count' => $reopenedCount,
            'reopen_rate' => $total > 0 ? round(($reopenedCount / $total) * 100, 1) : 0.0,
        ];
    }

    private function buildVolumeTrendWidget($tickets, Carbon $start, Carbon $end): array
    {
        $counts = [];
        $cursor = $start->copy()->startOfDay();
        $lastDay = $end->copy()->startOfDay();

        while ($cursor->lte($lastDay)) {
            $counts[$cursor->format('Y-m-d')] = 0;
            $cursor->addDay();
        }

        foreach ($tickets as $ticket) {
            $day = Carbon::parse($ticket->created_at)->format('Y-m-d');
            if (array_key_exists($day, $counts)) {
                $counts[$day]++;
            }
        }

        return [
            'labels' => array_keys($counts),
            'counts' => array_values($counts),
        ];
    }

    private function buildResolutionDistributionWidget($tickets, int $resolvedStatusId): array
    {
        $buckets = [
            '<1 day' => 0,
            '1-3 days' => 0,
            '3-7 days' => 0,
            '7+ days' => 0,
        ];

        foreach ($tickets as $ticket) {
            if ((int) $ticket->status_id !== $resolvedStatusId || !$ticket->resolved_at) {
                continue;
            }

            $hours = Carbon::parse($ticket->created_at)->diffInHours(Carbon::parse($ticket->resolved_at));

            if ($hours < 24) {
                $buckets['<1 day']++;
            } elseif ($hours < 72) {
                $buckets['1-3 days']++;
            } elseif ($hours < 168) {
                $buckets['3-7 days']++;
            } else {
                $buckets['7+ days']++;
            }
        }

        return [
            'labels' => array_keys($buckets),
            'counts' => array_values($buckets),
        ];
    }

    private function buildSlaBreakdownWidget($tickets, int $resolvedStatusId): array
    {
        $overdue = 0;
        $onTime = 0;
        $byDepartment = [];
        $byIssue = [];

        foreach ($tickets as $ticket) {
            $isOverdue = $this->isTicketOverdue($ticket, $resolvedStatusId);
            $isOverdue ? $overdue++ : $onTime++;

            $deptName = optional($ticket->department)->department_name ?? 'Unassigned Department';
            if (!isset($byDepartment[$deptName])) {
                $byDepartment[$deptName] = ['total' => 0, 'overdue' => 0];
            }
            $byDepartment[$deptName]['total']++;
            if ($isOverdue) {
                $byDepartment[$deptName]['overdue']++;
            }

            $issueName = optional($ticket->issue)->issue_description ?? ($ticket->custom_issue ?: 'Other');
            if (!isset($byIssue[$issueName])) {
                $byIssue[$issueName] = ['total' => 0, 'overdue' => 0];
            }
            $byIssue[$issueName]['total']++;
            if ($isOverdue) {
                $byIssue[$issueName]['overdue']++;
            }
        }

        $departmentLabels = [];
        $departmentOverdue = [];
        $departmentOnTime = [];
        foreach ($byDepartment as $name => $counts) {
            $departmentLabels[] = $name;
            $departmentOverdue[] = $counts['overdue'];
            $departmentOnTime[] = $counts['total'] - $counts['overdue'];
        }

        return [
            'overdue_count' => $overdue,
            'on_time_count' => $onTime,
            'by_department' => $byDepartment,
            'by_issue' => $byIssue,
            'department_labels' => $departmentLabels,
            'department_overdue' => $departmentOverdue,
            'department_on_time' => $departmentOnTime,
        ];
    }

    private function buildStatusFunnelWidget(Carbon $start, Carbon $end): array
    {
        $counts = Ticket::whereBetween('created_at', [$start, $end])
            ->select('status_id', DB::raw('COUNT(*) as total'))
            ->groupBy('status_id')
            ->pluck('total', 'status_id');

        $statusIdsByName = Status::pluck('id', 'status_name');

        $order = ['Unassigned', 'Pending', 'Resolved', 'Reopened'];
        $labels = [];
        $data = [];

        foreach ($order as $name) {
            $statusId = $statusIdsByName[$name] ?? null;
            $labels[] = $name;
            $data[] = $statusId !== null ? (int) ($counts[$statusId] ?? 0) : 0;
        }

        return [
            'labels' => $labels,
            'counts' => $data,
        ];
    }

    private function buildReassignmentWidget($tickets, Carbon $start, Carbon $end): array
    {
        $total = $tickets->count();

        $reassignedTicketIds = TicketReassignmentRequest::whereHas('ticket', function ($query) use ($start, $end) {
            $query->whereBetween('created_at', [$start, $end]);
        })->distinct()->pluck('ticket_id')->all();

        $reassignedCount = count($reassignedTicketIds);

        $byDepartment = [];
        foreach ($tickets as $ticket) {
            $deptName = optional($ticket->department)->department_name ?? 'Unassigned Department';
            if (!isset($byDepartment[$deptName])) {
                $byDepartment[$deptName] = ['total' => 0, 'reassigned' => 0];
            }
            $byDepartment[$deptName]['total']++;
            if (in_array($ticket->id, $reassignedTicketIds, true)) {
                $byDepartment[$deptName]['reassigned']++;
            }
        }

        $departmentLabels = [];
        $departmentRates = [];
        foreach ($byDepartment as $name => $counts) {
            $rate = $counts['total'] > 0 ? round(($counts['reassigned'] / $counts['total']) * 100, 1) : 0.0;
            $byDepartment[$name]['rate'] = $rate;
            $departmentLabels[] = $name;
            $departmentRates[] = $rate;
        }

        return [
            'reassigned_count' => $reassignedCount,
            'total' => $total,
            'rate' => $total > 0 ? round(($reassignedCount / $total) * 100, 1) : 0.0,
            'by_department' => $byDepartment,
            'department_labels' => $departmentLabels,
            'department_rates' => $departmentRates,
        ];
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=ReportsWidgetsTest`
Expected: PASS (2 tests)

- [ ] **Step 5: Run the full existing reports-related suite to confirm no regression**

Run: `php artisan test --filter=TicketReassignmentTest`
Run: `php artisan test --filter=TicketPerformanceTest`
Expected: PASS, unchanged.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Admin/ReportsController.php tests/Feature/ReportsWidgetsTest.php
git commit -m "feat: add KPI, volume trend, resolution distribution, SLA, status funnel and reassignment report widgets"
```

---

### Task 4: Backend — widget 6 (staff workload table extension)

**Files:**
- Modify: `app/Http/Controllers/Admin/ReportsController.php` (the `$users` query block)
- Test: `tests/Feature/ReportsStaffWorkloadTest.php`

**Interfaces:**
- Consumes: `User::assignedTickets()` (Task 2), `$this->formatMinutes()` (Task 3).
- Produces: each item in the `users` collection returned by `getReportData()` gains `assigned_count` (int), `avg_resolution_minutes` (int), `avg_resolution_formatted` (string), alongside its existing `resolved_tickets_count`/`overdue_tickets_count`.

- [ ] **Step 1: Write the failing feature test**

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
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ReportsStaffWorkloadTest extends TestCase
{
    use DatabaseTransactions;

    public function test_staff_workload_includes_assigned_count_and_avg_resolution()
    {
        $admin = User::role('admin')->first();
        $this->assertNotNull($admin);
        Permission::firstOrCreate(['name' => 'reports.view', 'guard_name' => 'web']);
        if (! $admin->can('reports.view')) {
            $admin->givePermissionTo('reports.view');
        }

        // HR department is id 1 in this app (used by the existing $users query filter).
        $hrDept = Department::find(1);
        $this->assertNotNull($hrDept, 'Expected HR department (id 1) to exist.');

        $div = Division::create(['division_name' => 'WorkloadDiv_' . uniqid(), 'department_id' => $hrDept->id]);
        $priority = Priority::firstOrCreate(['priority_name' => 'Normal'], ['priority_color' => '#ccc']);
        $unassigned = Status::firstOrCreate(['status_name' => 'Unassigned'], ['status_color' => '#ccc']);
        $resolved = Status::firstOrCreate(['status_name' => 'Resolved'], ['status_color' => '#ccc']);
        $issue = Issue::create([
            'department_id' => $hrDept->id,
            'division_id' => $div->id,
            'priority_id' => $priority->id,
            'issue_description' => 'Workload Test Issue ' . uniqid(),
        ]);

        $staff = User::create([
            'name' => 'WorkloadStaff_' . uniqid(),
            'username' => 'workload_staff_' . uniqid(),
            'email' => 'workload_staff_' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
            'department_id' => $hrDept->id,
            'division_id' => $div->id,
            'is_active' => true,
        ]);

        $now = Carbon::now();

        $ticket = new Ticket();
        $ticket->forceFill([
            'department_id' => $hrDept->id,
            'division_id' => $div->id,
            'issue_id' => $issue->id,
            'status_id' => $unassigned->id,
            'message' => 'workload ticket',
            'assigned_to' => $staff->id,
        ]);
        $ticket->save();
        $ticket->created_at = $now->copy()->subHours(2);
        $ticket->saveQuietly();
        $ticket->status_id = $resolved->id;
        $ticket->resolved_by = $staff->id;
        $ticket->save();
        $ticket->resolved_at = $now;
        $ticket->saveQuietly();

        $this->actingAs($admin, 'web');
        $response = $this->get(route('page.reports.index'));
        $response->assertStatus(200);

        $users = $response->viewData('users');
        $staffRow = $users->firstWhere('id', $staff->id);

        $this->assertNotNull($staffRow, 'Expected the workload test staff member to appear in the users widget.');
        $this->assertSame(1, $staffRow->assigned_count);
        $this->assertGreaterThanOrEqual(115, $staffRow->avg_resolution_minutes);
        $this->assertLessThanOrEqual(125, $staffRow->avg_resolution_minutes);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ReportsStaffWorkloadTest`
Expected: FAIL — `Undefined property: stdClass::$assigned_count` (or similar), since `assigned_count`/`avg_resolution_minutes` don't exist yet.

- [ ] **Step 3: Implement**

In `app/Http/Controllers/Admin/ReportsController.php`, replace the existing `$users = User::with('division')...->get();` block with:

```php
        $users = User::with('division')
            ->where('department_id', 1)
            ->withCount([
                'resolvedTickets as resolved_tickets_count' => function ($query) use ($startDate, $endDate) {
                    if ($startDate && $endDate) {
                        $query->whereBetween('tickets.created_at', [$startDate, $endDate]);
                    }
                },
                'overdueTickets as overdue_tickets_count' => function ($query) use ($startDate, $endDate) {
                    if ($startDate && $endDate) {
                        $query->whereBetween('tickets.created_at', [$startDate, $endDate]);
                    }
                },
                'assignedTickets as assigned_count' => function ($query) use ($startDate, $endDate) {
                    if ($startDate && $endDate) {
                        $query->whereBetween('tickets.created_at', [$startDate, $endDate]);
                    }
                },
            ])
            ->get();

        $staffResolvedStatusId = (int) \App\Models\Status::where('status_name', 'Resolved')->value('id');

        $staffResolutionRows = Ticket::where('status_id', $staffResolvedStatusId)
            ->whereNotNull('resolved_by')
            ->whereNotNull('resolved_at')
            ->whereIn('resolved_by', $users->pluck('id'))
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('created_at', [$startDate, $endDate]);
            })
            ->get(['resolved_by', 'created_at', 'resolved_at']);

        $avgResolutionByStaff = $staffResolutionRows
            ->groupBy('resolved_by')
            ->map(function ($rows) {
                $minutes = $rows->map(function ($ticket) {
                    return Carbon::parse($ticket->created_at)->diffInMinutes(Carbon::parse($ticket->resolved_at));
                });

                return (int) round($minutes->avg());
            });

        $users->each(function ($user) use ($avgResolutionByStaff) {
            $avgMinutes = (int) ($avgResolutionByStaff[$user->id] ?? 0);
            $user->avg_resolution_minutes = $avgMinutes;
            $user->avg_resolution_formatted = $this->formatMinutes($avgMinutes);
        });
```

(This sits directly above the existing `if (!$includeZeroActivity) { $users = $users->filter(...) }` block, which stays as-is.)

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=ReportsStaffWorkloadTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Admin/ReportsController.php tests/Feature/ReportsStaffWorkloadTest.php
git commit -m "feat: extend staff workload table with assigned count and avg resolution time"
```

---

### Task 5: Frontend — Chart.js loading, KPI strip, volume trend + resolution distribution charts

**Files:**
- Modify: `resources/views/admin/reports.blade.php`
- Test: `tests/Feature/ReportsPageWidgetsRenderTest.php`

**Interfaces:**
- Consumes: `reportKpis`, `reportVolumeTrend`, `reportResolutionDistribution` (Task 3).
- Produces: `<canvas id="volumeTrendChart">`, `<canvas id="resolutionDistributionChart">` DOM nodes and their Chart.js init functions, guarded by existence/data checks. Task 6/7 append more `@push('after_scripts')` init functions to the same `$(document).ready(...)` call added here.

- [ ] **Step 1: Write the failing feature test**

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ReportsPageWidgetsRenderTest extends TestCase
{
    use DatabaseTransactions;

    private function actingAdmin(): User
    {
        $admin = User::role('admin')->first();
        $this->assertNotNull($admin);
        Permission::firstOrCreate(['name' => 'reports.view', 'guard_name' => 'web']);
        if (! $admin->can('reports.view')) {
            $admin->givePermissionTo('reports.view');
        }
        $this->actingAs($admin, 'web');

        return $admin;
    }

    public function test_reports_page_renders_kpi_strip_and_trend_charts()
    {
        $this->actingAdmin();

        $response = $this->get(route('page.reports.index'));

        $response->assertStatus(200);
        $response->assertSee('id="volumeTrendChart"', false);
        $response->assertSee('id="resolutionDistributionChart"', false);
        $response->assertSee('cdn.jsdelivr.net/npm/chart.js', false);
        $response->assertSee('Overdue', false);
        $response->assertSee('Reopen Rate', false);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ReportsPageWidgetsRenderTest`
Expected: FAIL — the strings/ids don't exist in the current blade output yet.

- [ ] **Step 3: Implement the blade changes**

In `resources/views/admin/reports.blade.php`, insert immediately after the `@section('content')` line (line 15) and before the existing `<div class="row">` (line 16, the latest-tickets row):

```blade
    <div class="row mb-2">
        <div class="col-12">
            <small class="text-muted">
                Widgets below cover {{ $reportWidgetStart->format('M j, Y') }} &ndash; {{ $reportWidgetEnd->format('M j, Y') }}
            </small>
        </div>
    </div>

    <div class="row">
        <div class="col-sm-6 col-md-2">
            <div class="card text-white bg-dark">
                <div class="card-body">
                    <div class="text-value">{{ $reportKpis['total'] }}</div>
                    <small class="text-muted text-uppercase font-weight-bold">Total Tickets</small>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-md-2">
            <div class="card text-white bg-success">
                <div class="card-body">
                    <div class="text-value">{{ $reportKpis['resolved'] }}</div>
                    <small class="text-muted text-uppercase font-weight-bold">Resolved</small>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-md-2">
            <div class="card text-white bg-warning">
                <div class="card-body">
                    <div class="text-value">{{ $reportKpis['open'] }}</div>
                    <small class="text-muted text-uppercase font-weight-bold">Open</small>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-md-2">
            <div class="card text-white" style="background-color: #4BC0C0;">
                <div class="card-body">
                    <div class="text-value">{{ $reportKpis['avg_resolution_formatted'] }}</div>
                    <small class="text-muted text-uppercase font-weight-bold">Avg Resolution</small>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-md-2">
            <div class="card text-white" style="background-color: {{ $reportKpis['overdue_pct'] >= 20 ? '#dc3545' : '#28a745' }};">
                <div class="card-body">
                    <div class="text-value">{{ $reportKpis['overdue_pct'] }}%</div>
                    <small class="text-muted text-uppercase font-weight-bold">Overdue (SLA)</small>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-md-2">
            <div class="card text-white" style="background-color: #9966FF;">
                <div class="card-body">
                    <div class="text-value">{{ $reportKpis['reopen_rate'] }}%</div>
                    <small class="text-muted text-uppercase font-weight-bold">Reopen Rate</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header"><strong>Ticket Volume Trend</strong></div>
                <div class="card-body" style="height: 320px;">
                    <canvas id="volumeTrendChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header"><strong>Resolution Time Distribution</strong></div>
                <div class="card-body" style="height: 320px;">
                    <canvas id="resolutionDistributionChart"></canvas>
                </div>
            </div>
        </div>
    </div>

```

At the top of `@push('after_scripts')` (currently line 263), before the existing DataTables `<script>` tags, add:

```blade
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
const reportVolumeTrendLabels = @json($reportVolumeTrend['labels']);
const reportVolumeTrendCounts = @json($reportVolumeTrend['counts']);
const reportResolutionLabels = @json($reportResolutionDistribution['labels']);
const reportResolutionCounts = @json($reportResolutionDistribution['counts']);
const reportColorPalette = ['#FF6384', '#36A2EB', '#4BC0C0', '#FFCE56', '#9966FF', '#FF9F40', '#C9CBCF', '#4D5360'];

function initVolumeTrendChart() {
    const el = document.getElementById('volumeTrendChart');
    if (!el || !reportVolumeTrendLabels.length) return;
    new Chart(el, {
        type: 'line',
        data: {
            labels: reportVolumeTrendLabels,
            datasets: [{
                label: 'Tickets Created',
                data: reportVolumeTrendCounts,
                borderColor: '#36A2EB',
                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                fill: true,
                tension: 0.2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });
}

function initResolutionDistributionChart() {
    const el = document.getElementById('resolutionDistributionChart');
    if (!el || !reportResolutionLabels.length) return;
    new Chart(el, {
        type: 'bar',
        data: {
            labels: reportResolutionLabels,
            datasets: [{
                label: 'Resolved Tickets',
                data: reportResolutionCounts,
                backgroundColor: reportColorPalette
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });
}

$(document).ready(function () {
    initVolumeTrendChart();
    initResolutionDistributionChart();
});
</script>

```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=ReportsPageWidgetsRenderTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add resources/views/admin/reports.blade.php tests/Feature/ReportsPageWidgetsRenderTest.php
git commit -m "feat: add KPI strip and volume/resolution charts to reports page"
```

---

### Task 6: Frontend — SLA/overdue charts, department + issue-type breakdown, status funnel chart

**Files:**
- Modify: `resources/views/admin/reports.blade.php`
- Modify: `tests/Feature/ReportsPageWidgetsRenderTest.php` (add a test method)

**Interfaces:**
- Consumes: `reportSlaBreakdown`, `reportStatusFunnel` (Task 3).
- Produces: `<canvas id="slaByDepartmentChart">`, `<canvas id="statusFunnelChart">`, and an issue-type breakdown `<table>`.

- [ ] **Step 1: Add the failing test method**

Append to `tests/Feature/ReportsPageWidgetsRenderTest.php` (inside the class, after the existing test method):

```php
    public function test_reports_page_renders_sla_and_funnel_charts()
    {
        $this->actingAdmin();

        $response = $this->get(route('page.reports.index'));

        $response->assertStatus(200);
        $response->assertSee('id="slaByDepartmentChart"', false);
        $response->assertSee('id="statusFunnelChart"', false);
        $response->assertSee('Overdue vs On-Time', false);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ReportsPageWidgetsRenderTest`
Expected: FAIL on the new method — ids not present yet.

- [ ] **Step 3: Implement the blade changes**

In `resources/views/admin/reports.blade.php`, insert directly after the volume-trend/resolution-distribution `<div class="row">` block added in Task 5, and before the pre-existing latest-tickets `<div class="row">`:

```blade
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><strong>Overdue vs On-Time by Department</strong></div>
                <div class="card-body" style="height: 320px;">
                    <canvas id="slaByDepartmentChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><strong>Overdue vs On-Time by Issue Type</strong></div>
                <div class="card-body" style="max-height: 320px; overflow-y: auto;">
                    <table class="table table-sm">
                        <thead><tr><th>Issue</th><th>Total</th><th>Overdue</th><th>%</th></tr></thead>
                        <tbody>
                        @forelse($reportSlaBreakdown['by_issue'] as $issueName => $row)
                            <tr>
                                <td>{{ $issueName }}</td>
                                <td>{{ $row['total'] }}</td>
                                <td>{{ $row['overdue'] }}</td>
                                <td>{{ $row['total'] > 0 ? round(($row['overdue'] / $row['total']) * 100, 1) : 0 }}%</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted">No data for this range</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header"><strong>Status Funnel</strong></div>
                <div class="card-body" style="height: 280px;">
                    <canvas id="statusFunnelChart"></canvas>
                </div>
            </div>
        </div>
    </div>

```

In the `<script>` block added in Task 5 (inside `@push('after_scripts')`), add these new `const` declarations right after the existing `reportColorPalette` line:

```blade
const reportSlaDeptLabels = @json($reportSlaBreakdown['department_labels']);
const reportSlaDeptOverdue = @json($reportSlaBreakdown['department_overdue']);
const reportSlaDeptOnTime = @json($reportSlaBreakdown['department_on_time']);
const reportStatusFunnelLabels = @json($reportStatusFunnel['labels']);
const reportStatusFunnelCounts = @json($reportStatusFunnel['counts']);
```

And add these two functions after `initResolutionDistributionChart()`:

```blade
function initSlaByDepartmentChart() {
    const el = document.getElementById('slaByDepartmentChart');
    if (!el || !reportSlaDeptLabels.length) return;
    new Chart(el, {
        type: 'bar',
        data: {
            labels: reportSlaDeptLabels,
            datasets: [
                { label: 'On-Time', data: reportSlaDeptOnTime, backgroundColor: '#28a745' },
                { label: 'Overdue', data: reportSlaDeptOverdue, backgroundColor: '#dc3545' }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } } }
        }
    });
}

function initStatusFunnelChart() {
    const el = document.getElementById('statusFunnelChart');
    if (!el || !reportStatusFunnelLabels.length) return;
    new Chart(el, {
        type: 'bar',
        data: {
            labels: reportStatusFunnelLabels,
            datasets: [{
                label: 'Tickets',
                data: reportStatusFunnelCounts,
                backgroundColor: reportColorPalette
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });
}
```

Update the `$(document).ready(function () { ... })` call to also invoke them:

```blade
$(document).ready(function () {
    initVolumeTrendChart();
    initResolutionDistributionChart();
    initSlaByDepartmentChart();
    initStatusFunnelChart();
});
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=ReportsPageWidgetsRenderTest`
Expected: PASS (both test methods)

- [ ] **Step 5: Commit**

```bash
git add resources/views/admin/reports.blade.php tests/Feature/ReportsPageWidgetsRenderTest.php
git commit -m "feat: add SLA/overdue and status funnel charts to reports page"
```

---

### Task 7: Frontend — extend staff workload table, add reassignment KPI tile + department chart

**Files:**
- Modify: `resources/views/admin/reports.blade.php`
- Modify: `tests/Feature/ReportsPageWidgetsRenderTest.php` (add a test method)

**Interfaces:**
- Consumes: `assigned_count`/`avg_resolution_formatted` on each `$users` item (Task 4), `reportReassignment` (Task 3).
- Produces: two new `<th>`/`<td>` columns in `userActivityTable`, `<canvas id="reassignmentByDeptChart">`, and a reassignment-rate KPI tile.

- [ ] **Step 1: Add the failing test method**

Append to `tests/Feature/ReportsPageWidgetsRenderTest.php`:

```php
    public function test_reports_page_renders_staff_workload_columns_and_reassignment_widget()
    {
        $this->actingAdmin();

        $response = $this->get(route('page.reports.index'));

        $response->assertStatus(200);
        $response->assertSee('Assigned', false);
        $response->assertSee('Avg Resolution', false);
        $response->assertSee('id="reassignmentByDeptChart"', false);
        $response->assertSee('Reassignment Rate', false);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ReportsPageWidgetsRenderTest`
Expected: FAIL on the new method.

- [ ] **Step 3: Implement the blade changes**

In `resources/views/admin/reports.blade.php`, find the `userActivityTable` `<thead>` (existing lines ~127-132) and change:

```blade
                        <tr>
                            <th>Name</th>
                            <th>Division</th>
                            <th>Total Resolved Tickets</th>
                            <th>Overdue Tickets</th>
                        </tr>
```

to:

```blade
                        <tr>
                            <th>Name</th>
                            <th>Division</th>
                            <th>Assigned</th>
                            <th>Total Resolved Tickets</th>
                            <th>Overdue Tickets</th>
                            <th>Avg Resolution</th>
                        </tr>
```

And the corresponding `<tbody>` row (existing lines ~136-141) from:

```blade
                            <tr>
                                <td>{{ $user->name ?? 'N/A'}}</td>
                                <td>{{ $user->division->division_name ?? 'N/A'}}</td>
                                <td>{{ $user->resolved_tickets_count}}</td>
                                <td>{{ $user->overdue_tickets_count}}</td>
                            </tr>
```

to:

```blade
                            <tr>
                                <td>{{ $user->name ?? 'N/A'}}</td>
                                <td>{{ $user->division->division_name ?? 'N/A'}}</td>
                                <td>{{ $user->assigned_count }}</td>
                                <td>{{ $user->resolved_tickets_count}}</td>
                                <td>{{ $user->overdue_tickets_count}}</td>
                                <td>{{ $user->avg_resolution_formatted }}</td>
                            </tr>
```

Then, in the row added by Task 6 that currently only has the Status Funnel card (`<div class="col-md-8">`), change that row to add two more columns:

```blade
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><strong>Status Funnel</strong></div>
                <div class="card-body" style="height: 280px;">
                    <canvas id="statusFunnelChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body text-center d-flex flex-column justify-content-center">
                    <div class="text-value">{{ $reportReassignment['rate'] }}%</div>
                    <small class="text-muted text-uppercase font-weight-bold">Reassignment Rate</small>
                    <div class="text-muted small mt-1">{{ $reportReassignment['reassigned_count'] }} of {{ $reportReassignment['total'] }} tickets</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-header"><strong>Reassignment by Department</strong></div>
                <div class="card-body" style="height: 220px;">
                    <canvas id="reassignmentByDeptChart"></canvas>
                </div>
            </div>
        </div>
    </div>

```

In the `<script>` block, add after `const reportStatusFunnelCounts = @json($reportStatusFunnel['counts']);`:

```blade
const reportReassignDeptLabels = @json($reportReassignment['department_labels']);
const reportReassignDeptRates = @json($reportReassignment['department_rates']);
```

Add this function after `initStatusFunnelChart()`:

```blade
function initReassignmentByDeptChart() {
    const el = document.getElementById('reassignmentByDeptChart');
    if (!el || !reportReassignDeptLabels.length) return;
    new Chart(el, {
        type: 'bar',
        data: {
            labels: reportReassignDeptLabels,
            datasets: [{
                label: 'Reassignment Rate %',
                data: reportReassignDeptRates,
                backgroundColor: '#FF9F40'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, max: 100 } }
        }
    });
}
```

Update `$(document).ready(...)` to also call it:

```blade
$(document).ready(function () {
    initVolumeTrendChart();
    initResolutionDistributionChart();
    initSlaByDepartmentChart();
    initStatusFunnelChart();
    initReassignmentByDeptChart();
});
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=ReportsPageWidgetsRenderTest`
Expected: PASS (all 3 test methods)

- [ ] **Step 5: Commit**

```bash
git add resources/views/admin/reports.blade.php tests/Feature/ReportsPageWidgetsRenderTest.php
git commit -m "feat: extend staff workload table and add reassignment rate widget to reports page"
```

---

### Task 8: PDF non-regression, mobile/empty-range guards, final re-grep and full verification

**Files:**
- Test: `tests/Feature/ReportsPdfRegressionTest.php`
- No production code changes expected — this task verifies Tasks 1-7 didn't regress the PDF export or break on an empty range, and performs the required final re-grep.

**Interfaces:**
- Consumes: `downloadPdf()` (unchanged), `getReportData()` (Tasks 3-4).

- [ ] **Step 1: Write the PDF regression test**

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ReportsPdfRegressionTest extends TestCase
{
    use DatabaseTransactions;

    private function actingAdmin(): User
    {
        $admin = User::role('admin')->first();
        $this->assertNotNull($admin);
        Permission::firstOrCreate(['name' => 'reports.view', 'guard_name' => 'web']);
        if (! $admin->can('reports.view')) {
            $admin->givePermissionTo('reports.view');
        }
        $this->actingAs($admin, 'web');

        return $admin;
    }

    public function test_pdf_download_still_succeeds_and_is_a_pdf()
    {
        $this->actingAdmin();

        $response = $this->post(route('page.reports.download_pdf'), [
            'start_date' => Carbon::now()->subDays(7)->format('Y-m-d'),
            'end_date' => Carbon::now()->format('Y-m-d'),
        ]);

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_pdf_download_succeeds_on_a_zero_ticket_range()
    {
        $this->actingAdmin();

        // A window far in the past guaranteed to have zero tickets.
        $response = $this->post(route('page.reports.download_pdf'), [
            'start_date' => '2000-01-01',
            'end_date' => '2000-01-02',
        ]);

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_reports_page_renders_on_effectively_empty_range()
    {
        // The on-screen page always uses the last-30-days default window;
        // this confirms it degrades gracefully rather than erroring when
        // that window is sparse/empty, satisfying the T12 empty-range check.
        $this->actingAdmin();

        $response = $this->get(route('page.reports.index'));

        $response->assertStatus(200);
        $response->assertViewHas('reportKpis', function ($kpis) {
            return array_key_exists('total', $kpis);
        });
    }

    public function test_survey_and_arta_report_pages_still_load_on_mssql()
    {
        // T10 regression check: Part 0d found SurveyReportsController and
        // ArtaSurveyReportsController were already pure Eloquent (no raw SQL),
        // so this plan makes no changes to them. This test proves that claim
        // rather than just asserting it in the report.
        $admin = User::role('admin')->first();
        $this->assertNotNull($admin);
        Permission::firstOrCreate(['name' => 'survey-reports.view', 'guard_name' => 'web']);
        if (! $admin->can('survey-reports.view')) {
            $admin->givePermissionTo('survey-reports.view');
        }
        $this->actingAs($admin, 'web');

        $surveyResponse = $this->get(route('page.survey_reports.index'));
        $surveyResponse->assertStatus(200);

        $artaResponse = $this->get(route('page.arta_survey_reports.index'));
        $artaResponse->assertStatus(200);

        $surveyPdfResponse = $this->post(route('page.survey_reports.download_pdf'), [
            'start_date' => Carbon::now()->subDays(7)->format('Y-m-d'),
            'end_date' => Carbon::now()->format('Y-m-d'),
        ]);
        $surveyPdfResponse->assertStatus(200);
        $surveyPdfResponse->assertHeader('content-type', 'application/pdf');

        $artaPdfResponse = $this->post(route('page.arta_survey_reports.download_pdf'), [
            'start_date' => Carbon::now()->subDays(7)->format('Y-m-d'),
            'end_date' => Carbon::now()->format('Y-m-d'),
        ]);
        $artaPdfResponse->assertStatus(200);
        $artaPdfResponse->assertHeader('content-type', 'application/pdf');
    }
}
```

- [ ] **Step 2: Run test to verify current state**

Run: `php artisan test --filter=ReportsPdfRegressionTest`
Expected: PASS immediately (this task adds no production code — it's a regression net over Tasks 1-7's changes). If it fails, that is a real regression from an earlier task; stop and fix the offending task before proceeding.

- [ ] **Step 3: Manually diff the PDF output against pre-change baseline**

Run (from a shell, not PHPUnit — this produces a byte artifact for a human/CI diff, not an assertion):

```bash
git stash
php artisan tinker --execute="
\$c = new App\Http\Controllers\Admin\ReportsController();
\$r = app()->call([\$c, 'downloadPdf'], ['request' => new Illuminate\Http\Request(['start_date' => '2026-06-01', 'end_date' => '2026-07-22'])]);
"
```

This step is exploratory (confirms the PDF template only reads pre-existing keys); the actual guarantee is structural — `reports_pdf.blade.php` was read in Part 0 and confirmed to reference only `$reportStartDate`, `$reportEndDate`, `$divisions`/`$division`, `$users`/`$user`, `$category` (from `$division->problemCategories`) — none of the new keys (`reportKpis`, `reportVolumeTrend`, etc.) are referenced anywhere in it, and Blade/DomPDF silently ignores unused array keys passed to a view. Restore your work after this manual check:

```bash
git stash pop
```

- [ ] **Step 4: Full re-grep for MySQL-only SQL functions**

Run: `grep -rn "TIMESTAMPDIFF\|DATE_FORMAT\|GROUP_CONCAT\|IFNULL\|STR_TO_DATE\|UNIX_TIMESTAMP" app/ resources/views/admin/`

Expected: the only two hits are inside `app/Services/SqlDialectHelper.php` (the intentional dialect helper itself, which contains the string `TIMESTAMPDIFF` as a literal in its MySQL branch). No hits in `ReportsController.php`, `User.php`, `SurveyReportsController.php`, `ArtaSurveyReportsController.php`, or any blade file.

- [ ] **Step 5: Run the entire Feature + Unit suite**

Run: `php artisan test`
Expected: All tests pass, including the pre-existing `TicketPerformanceTest`, `TicketReassignmentTest`, `HrChatConversationMemoryTest`, `HrConversationTest`.

- [ ] **Step 6: Manual browser check (T1, T5, T12 — cannot be fully automated without a JS runner)**

Start the app (`php artisan serve` or the project's existing local dev server), log in as a user with `reports.view`, open the Reports page, and confirm:
- No JS console errors.
- All 7 widgets render with real or zero-state data.
- Resize to 375px width — cards stack, no horizontal overflow.
- A date range with a gap day (e.g. two tickets three days apart) shows the middle day as `0` in the volume trend, not omitted.

- [ ] **Step 7: Commit**

```bash
git add tests/Feature/ReportsPdfRegressionTest.php
git commit -m "test: add PDF non-regression and empty-range coverage for reports widgets"
```

---

## Post-Plan: Final Report Checklist

After Task 8, produce the final report the original brief requested, using data already gathered during execution:

1. Part 0 findings (already gathered above — status ids, SLA rule, reassignment schema, chart-lib loading, raw-SQL inventory, and the correction that the two "confirmed breakers" were already driver-branched).
2. SQL changes: Task 1's extraction (file/line before/after) — note this was a DRY refactor, not a bug fix, since both sites were already dialect-safe.
3. Per-widget data source + MSSQL-safety note (all PHP/Carbon except the portable `GROUP BY status_id` COUNT in the status funnel, which needs no dialect handling).
4. PDF confirmation from Task 8 Step 3.
5. T1-T12 results — pull actual numbers from the `ReportsWidgetsTest`/`ReportsStaffWorkloadTest` assertions (T3, T4, T6) and the manual browser pass (T1, T5, T12).
6. Survey/ARTA audit result: already clean, no changes made (confirmed in Part 0d, re-confirmed by Task 8 Step 4's re-grep).
