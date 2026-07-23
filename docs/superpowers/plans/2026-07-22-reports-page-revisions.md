# Reports Page Revisions Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the Reports page's 12 static KPI cards with a single date-range control driving 6 dynamic cards, make the volume trend and reassignment widgets range-aware/correctly-scoped, hide the (currently signal-free) by-department overdue chart, and clean up the grid layout — without rebuilding the 7 widgets added in the prior pass.

**Architecture:** `ReportsController::index()` gains a `resolvePeriod(Request $request)` helper that turns `period`/`half`/`year`/`start`/`end` query params into a resolved `[$period, $startDate, $endDate, $half, $year, $error]` tuple, defaulting to "This Month" when no params are given. `getReportData($startDate, $endDate, $includeZeroActivity)` keeps its exact signature (so `downloadPdf()` is untouched) but its internal "null start/end" meaning changes from "default to last 30 days" to genuinely "all-time, derive real bucketing bounds from the data" — this also fixes, at the root, the all-time-vs-period inconsistency the prior pass's final review flagged. The frontend control triggers a full-page reload with query params (chosen over AJAX — see Part 0c) via `window.location.href`, so `index()` re-renders the whole page server-side each time; no new route or JSON endpoint is needed.

**Tech Stack:** Laravel 8, Eloquent, SQL Server (`sqlsrv`), vanilla JS (no new libraries), Chart.js (already loaded), Bootstrap 4, PHPUnit `DatabaseTransactions` against the live MSSQL DB (established convention from the prior pass).

## Global Constraints

- Do not rebuild any of the 7 widgets from the prior pass — only revise per the spec below.
- All datetime math stays in PHP/Carbon over pulled rows. No `TIMESTAMPDIFF`/`DATE_FORMAT`/`GROUP_CONCAT`/`IFNULL` anywhere. The one existing SQL-level diff (`ReportsController.php`'s `$diffMinutesSql` in the `ticketOverview` block) already uses `SqlDialectHelper::diffMinutesSql(...)` — leave it as-is, don't touch it.
- `getReportData($startDate = null, $endDate = null, $includeZeroActivity = false)` keeps this exact signature — `downloadPdf()` (`ReportsController.php:47-69`) calls it unmodified and must keep working with no changes to that method.
- Do not edit `resources/views/admin/pdf/reports_pdf.blade.php`.
- Do not touch `SurveyReportsController`, `ArtaSurveyReportsController`, or their blade/PDF views.
- HR office is `departments.id = 1`, `department_name = "City Human Resource Development Office"` — the only department in the system today. Its 9 divisions (`divisions.department_id = 1`): Department Head, Information Technology, Administrative, Payroll, Records, Claims and Benefits, RSP, Learning and Development, Performance Management.
- "This Month" = current calendar month, 1st to today (inclusive, end-of-day today).
- "Semestral" H1 = Jan 1–Jun 30 of the given/current year; H2 = Jul 1–Dec 31. Default half when first selecting Semestral = whichever half today falls in (today, 2026-07-22, is in H2). Default year = current year.
- "All time" = no lower/upper date bound anywhere in `getReportData()` — this replaces the old "null means last 30 days" fallback entirely.
- Default period on first page load (no query params): **"This Month"** — chosen instead of keeping the old 30-day default so the control's displayed selection always truthfully matches the data on screen (30 days isn't one of the 4 dropdown options).
- Volume trend bucketing (computed in PHP, no SQL date functions): range ≤ 92 days → daily buckets (`Y-m-d` labels); 93–730 days → weekly buckets (label = the Monday of that week, `Y-m-d` format); > 730 days **or** the "All time" period specifically (regardless of its actual span) → monthly buckets (`Y-m` labels).
- Reassignment-by-division: scoped to tickets whose `department_id` = the HR office id; grouped by the ticket's `division_id` → `division_name`; shown as a rate (%), matching the prior by-department widget's convention; every HR division is zero-filled up front so the chart always shows a complete, stable set of bars.
- PHP compatibility floor `^7.3|^8.0` — no arrow functions (`fn() =>`).
- Reuse the Download Report modal's exact input styling (`form-group`/`form-control` date inputs) for the Custom range sub-control.

---

### Task 1: Backend — period resolution + true all-time semantics + Pending/Open KPI swap

**Files:**
- Modify: `app/Http/Controllers/Admin/ReportsController.php` (imports, `index()`, new `resolvePeriod()`, `getReportData()`'s widget-window block, `buildKpiWidget()`)
- Test: `tests/Feature/ReportsPeriodControlTest.php`

**Interfaces:**
- Produces: `private function resolvePeriod(Request $request): array` returning `[string $period, ?Carbon $startDate, ?Carbon $endDate, ?string $half, int $year, ?string $error]`. `$startDate`/`$endDate` are both `null` only for `period === 'all'`.
- Produces: `index()` now passes `activePeriod`, `activeHalf`, `activeYear`, `periodError` view keys, consumed by Task 4's blade control.
- Produces: `buildKpiWidget()` signature becomes `buildKpiWidget($tickets, int $resolvedStatusId, int $pendingStatusId, int $reopenedStatusId): array`, return array gains `'pending'`, loses `'open'`. Task 4's blade consumes `$reportKpis['pending']` instead of `$reportKpis['open']`.
- Consumes: nothing new from other tasks.

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

class ReportsPeriodControlTest extends TestCase
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

    private function makeDeptDiv(string $tag): array
    {
        $dept = Department::create(['department_name' => "PeriodDept{$tag}_" . uniqid()]);
        $div = Division::create(['division_name' => "PeriodDiv{$tag}_" . uniqid(), 'department_id' => $dept->id]);

        return [$dept, $div];
    }

    private function makeTicket(Department $dept, Division $div, Issue $issue, Status $status, User $admin, Carbon $createdAt): Ticket
    {
        $ticket = new Ticket();
        $ticket->forceFill([
            'user_id' => $admin->id,
            'department_id' => $dept->id,
            'division_id' => $div->id,
            'issue_id' => $issue->id,
            'status_id' => $status->id,
            'message' => 'period control test ticket',
        ]);
        $ticket->save();
        $ticket->created_at = $createdAt;
        $ticket->saveQuietly();

        return $ticket;
    }

    public function test_default_period_with_no_query_params_is_this_month()
    {
        $this->actingAdmin();

        $response = $this->get(route('page.reports.index'));

        $response->assertStatus(200);
        $response->assertViewHas('activePeriod', 'month');

        $start = $response->viewData('reportWidgetStart');
        $end = $response->viewData('reportWidgetEnd');
        $this->assertTrue($start->isSameDay(now()->startOfMonth()));
        $this->assertTrue($end->isSameDay(now()));
    }

    public function test_all_time_period_is_unbounded_and_includes_old_tickets()
    {
        $admin = $this->actingAdmin();
        [$dept, $div] = $this->makeDeptDiv('AllTime');
        $priority = Priority::firstOrCreate(['priority_name' => 'Normal'], ['priority_color' => '#ccc']);
        $unassigned = Status::firstOrCreate(['status_name' => 'Unassigned'], ['status_color' => '#ccc']);
        $issue = Issue::create([
            'department_id' => $dept->id,
            'division_id' => $div->id,
            'priority_id' => $priority->id,
            'issue_description' => 'All-time Test Issue ' . uniqid(),
        ]);

        $oldTicket = $this->makeTicket($dept, $div, $issue, $unassigned, $admin, Carbon::now()->subYears(3));

        $response = $this->get(route('page.reports.index', ['period' => 'all']));

        $response->assertStatus(200);
        $response->assertViewHas('activePeriod', 'all');

        $kpis = $response->viewData('reportKpis');
        $this->assertGreaterThanOrEqual(1, $kpis['total']);
        $this->assertArrayHasKey('pending', $kpis);
        $this->assertArrayNotHasKey('open', $kpis);

        $start = $response->viewData('reportWidgetStart');
        $this->assertTrue($start->lte($oldTicket->created_at));
    }

    public function test_semestral_defaults_to_the_half_today_falls_in()
    {
        $this->actingAdmin();

        $response = $this->get(route('page.reports.index', ['period' => 'half']));

        $response->assertStatus(200);
        $expectedHalf = now()->month <= 6 ? 'h1' : 'h2';
        $response->assertViewHas('activeHalf', $expectedHalf);
    }

    public function test_semestral_h1_window_is_jan_to_jun()
    {
        $this->actingAdmin();

        $response = $this->get(route('page.reports.index', ['period' => 'half', 'half' => 'h1', 'year' => 2026]));

        $response->assertStatus(200);
        $start = $response->viewData('reportWidgetStart');
        $end = $response->viewData('reportWidgetEnd');
        $this->assertSame('2026-01-01', $start->format('Y-m-d'));
        $this->assertSame('2026-06-30', $end->format('Y-m-d'));
    }

    public function test_semestral_h2_window_is_jul_to_dec()
    {
        $this->actingAdmin();

        $response = $this->get(route('page.reports.index', ['period' => 'half', 'half' => 'h2', 'year' => 2026]));

        $response->assertStatus(200);
        $start = $response->viewData('reportWidgetStart');
        $end = $response->viewData('reportWidgetEnd');
        $this->assertSame('2026-07-01', $start->format('Y-m-d'));
        $this->assertSame('2026-12-31', $end->format('Y-m-d'));
    }

    public function test_custom_range_applies_exact_dates()
    {
        $this->actingAdmin();

        $response = $this->get(route('page.reports.index', [
            'period' => 'custom',
            'start' => '2026-02-01',
            'end' => '2026-02-15',
        ]));

        $response->assertStatus(200);
        $start = $response->viewData('reportWidgetStart');
        $end = $response->viewData('reportWidgetEnd');
        $this->assertSame('2026-02-01', $start->format('Y-m-d'));
        $this->assertSame('2026-02-15', $end->format('Y-m-d'));
        $response->assertViewHas('periodError', null);
    }

    public function test_custom_range_with_end_before_start_falls_back_gracefully()
    {
        $this->actingAdmin();

        $response = $this->get(route('page.reports.index', [
            'period' => 'custom',
            'start' => '2026-02-15',
            'end' => '2026-02-01',
        ]));

        $response->assertStatus(200);
        $response->assertViewHas('activePeriod', 'month');
        $error = $response->viewData('periodError');
        $this->assertNotNull($error);
        $this->assertStringContainsString('start', strtolower($error));
    }

    public function test_custom_range_with_missing_dates_falls_back_gracefully()
    {
        $this->actingAdmin();

        $response = $this->get(route('page.reports.index', ['period' => 'custom']));

        $response->assertStatus(200);
        $response->assertViewHas('activePeriod', 'month');
        $this->assertNotNull($response->viewData('periodError'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ReportsPeriodControlTest`
Expected: FAIL — `index()` doesn't yet accept query params, `activePeriod`/`periodError` view keys don't exist, `reportKpis` still has `'open'` not `'pending'`.

- [ ] **Step 3: Add `resolvePeriod()` and update `index()`**

In `app/Http/Controllers/Admin/ReportsController.php`, replace the `index()` method:

```php
    public function index(Request $request)
    {
        [$period, $startDate, $endDate, $half, $year, $periodError] = $this->resolvePeriod($request);

        $data = $this->getReportData($startDate, $endDate);

        return view('admin.reports', array_merge([
            'title' => 'Reports',
            'breadcrumbs' => [
                trans('backpack::crud.admin') => backpack_url('dashboard'),
                'Reports' => false,
            ],
            'page' => 'resources/views/admin/reports.blade.php',
            'controller' => 'app/Http/Controllers/Admin/ReportsController.php',
            'activePeriod' => $period,
            'activeHalf' => $half,
            'activeYear' => $year,
            'periodError' => $periodError,
        ], $data));
    }
```

Add this new private method directly after `index()`:

```php
    private function resolvePeriod(Request $request): array
    {
        $period = $request->query('period', 'month');
        $year = (int) $request->query('year', now()->year);

        if ($period === 'all') {
            return ['all', null, null, null, $year, null];
        }

        if ($period === 'half') {
            $half = $request->query('half');
            if (!in_array($half, ['h1', 'h2'], true)) {
                // Default to whichever half today falls in.
                $half = now()->month <= 6 ? 'h1' : 'h2';
            }

            if ($half === 'h1') {
                $start = Carbon::create($year, 1, 1)->startOfDay();
                $end = Carbon::create($year, 6, 30)->endOfDay();
            } else {
                $start = Carbon::create($year, 7, 1)->startOfDay();
                $end = Carbon::create($year, 12, 31)->endOfDay();
            }

            return ['half', $start, $end, $half, $year, null];
        }

        if ($period === 'custom') {
            $startInput = $request->query('start');
            $endInput = $request->query('end');
            $start = null;
            $end = null;
            $error = null;

            if (!$startInput || !$endInput) {
                $error = 'Please provide both a start and end date for a custom range.';
            } else {
                try {
                    $start = Carbon::parse($startInput)->startOfDay();
                    $end = Carbon::parse($endInput)->endOfDay();
                } catch (\Exception $e) {
                    $error = 'Those dates could not be understood.';
                }

                if ($start && $end && $end->lt($start)) {
                    $error = 'End date must be on or after the start date.';
                    $start = null;
                    $end = null;
                }
            }

            if ($start && $end) {
                return ['custom', $start, $end, null, $year, null];
            }

            // Validation failed: fall back to "This Month" rather than crash
            // or silently swap the dates, carrying the error message so the
            // page can display it.
            $error = ($error ?? 'Invalid custom range.') . ' Showing this month instead.';
            $start = now()->startOfMonth()->startOfDay();
            $end = now()->endOfDay();

            return ['month', $start, $end, null, $year, $error];
        }

        // "month" (default) — current calendar month to date.
        $start = now()->startOfMonth()->startOfDay();
        $end = now()->endOfDay();

        return ['month', $start, $end, null, $year, null];
    }
```

- [ ] **Step 4: Rework the widget-window block and `buildKpiWidget()` in `getReportData()`**

In `app/Http/Controllers/Admin/ReportsController.php`, replace this block (currently right after the `$divisions` computation):

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
```

with:

```php
        $resolvedStatusId = (int) Status::where('status_name', 'Resolved')->value('id');
        $pendingStatusId = (int) Status::where('status_name', 'Pending')->value('id');
        $reopenedStatusId = (int) Status::where('status_name', 'Reopened')->value('id');

        // $startDate/$endDate now mean exactly what they say: null on both
        // means genuinely all-time (no filter), matching the same semantics
        // already used by the users/ticketOverview/divisions/latestTickets
        // queries above. There is no more "default to last 30 days" fallback
        // here — the caller (index(), or downloadPdf()'s validated request)
        // decides the window; index() defaults to "This Month" when no
        // period is chosen (see resolvePeriod()).
        if ($startDate && $endDate) {
            $widgetStart = $startDate->copy();
            $widgetEnd = $endDate->copy();
        } else {
            // All-time: derive real bounds from the data so bucketing has an
            // actual range to work with, instead of an arbitrary epoch anchor.
            $earliestCreatedAt = Ticket::min('created_at');
            $widgetStart = $earliestCreatedAt ? Carbon::parse($earliestCreatedAt)->startOfDay() : now()->startOfDay();
            $widgetEnd = now()->endOfDay();
        }

        $widgetTickets = Ticket::with(['department', 'division', 'issue'])
            ->whereBetween('created_at', [$widgetStart, $widgetEnd])
            ->get(['id', 'status_id', 'department_id', 'division_id', 'issue_id', 'custom_issue', 'assigned_to', 'created_at', 'resolved_at', 'reopened_at']);

        // All-time is open-ended by nature, so it always buckets by month
        // regardless of how much data currently falls inside it.
        $forceBucketUnit = (!$startDate && !$endDate) ? 'month' : null;

        $reportKpis = $this->buildKpiWidget($widgetTickets, $resolvedStatusId, $pendingStatusId, $reopenedStatusId);
        $reportVolumeTrend = $this->buildVolumeTrendWidget($widgetTickets, $widgetStart, $widgetEnd, $forceBucketUnit);
```

Leave the remaining lines in that block (`$reportResolutionDistribution = ...` through `$reportReassignment = ...` and the `return [...]`) untouched for this task — Task 2 will update the `buildVolumeTrendWidget` call signature further, Task 3 will touch `buildReassignmentWidget`'s internals only (not this call site).

Now update `buildKpiWidget()`. Replace:

```php
    private function buildKpiWidget($tickets, int $resolvedStatusId, int $reopenedStatusId): array
    {
        $total = $tickets->count();

        $resolvedTickets = $tickets->filter(function ($ticket) use ($resolvedStatusId) {
            return (int) $ticket->status_id === $resolvedStatusId;
        });
        $resolved = $resolvedTickets->count();
        $open = $total - $resolved;
```

with:

```php
    private function buildKpiWidget($tickets, int $resolvedStatusId, int $pendingStatusId, int $reopenedStatusId): array
    {
        $total = $tickets->count();

        $resolvedTickets = $tickets->filter(function ($ticket) use ($resolvedStatusId) {
            return (int) $ticket->status_id === $resolvedStatusId;
        });
        $resolved = $resolvedTickets->count();

        $pending = $tickets->filter(function ($ticket) use ($pendingStatusId) {
            return (int) $ticket->status_id === $pendingStatusId;
        })->count();
```

And in the same method's final `return [...]`, replace:

```php
        return [
            'total' => $total,
            'resolved' => $resolved,
            'open' => $open,
            'avg_resolution_minutes' => $avgResolutionMinutes,
```

with:

```php
        return [
            'total' => $total,
            'resolved' => $resolved,
            'pending' => $pending,
            'avg_resolution_minutes' => $avgResolutionMinutes,
```

(The rest of that return array — `avg_resolution_formatted` through `reopen_rate` — is unchanged.)

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=ReportsPeriodControlTest`
Expected: PASS (8 tests) — note some assertions (bucket format, reassignment-by-division) depend on Tasks 2-3 and are added there; this task's own 8 tests only exercise period resolution and the KPI key swap, which are fully implemented here.

- [ ] **Step 6: Regression check on existing tests touching `getReportData`**

Run: `php artisan test --filter=ReportsWidgetsTest`
Run: `php artisan test --filter=ReportsPdfRegressionTest`
Expected: both still PASS — `downloadPdf()` always passes explicit non-null `$startDate`/`$endDate` (its request validation requires both), so it never hits the new all-time branch; `ReportsWidgetsTest` doesn't reference `reportKpis['open']`.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Admin/ReportsController.php tests/Feature/ReportsPeriodControlTest.php
git commit -m "feat: add date-range period control resolution and Pending KPI"
```

---

### Task 2: Backend — adaptive volume-trend bucketing

**Files:**
- Modify: `app/Http/Controllers/Admin/ReportsController.php` (`buildVolumeTrendWidget()`)
- Test: append to `tests/Feature/ReportsPeriodControlTest.php`

**Interfaces:**
- Consumes: the `$forceBucketUnit` variable Task 1 already threads into the call site (`$this->buildVolumeTrendWidget($widgetTickets, $widgetStart, $widgetEnd, $forceBucketUnit);` — already correct from Task 1, no call-site change needed here).
- Produces: `buildVolumeTrendWidget($tickets, Carbon $start, Carbon $end, ?string $forceUnit = null): array`, same return shape (`['labels' => [...], 'counts' => [...]]`) as before — Task 5's frontend chart code doesn't need to change, it already just renders whatever labels/counts it's given.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/ReportsPeriodControlTest.php` (inside the class, after the existing test methods):

```php
    public function test_volume_trend_buckets_daily_for_a_short_custom_range()
    {
        $this->actingAdmin();

        $response = $this->get(route('page.reports.index', [
            'period' => 'custom',
            'start' => '2026-03-01',
            'end' => '2026-03-07',
        ]));

        $response->assertStatus(200);
        $trend = $response->viewData('reportVolumeTrend');
        $this->assertCount(7, $trend['labels']);
        $this->assertSame('2026-03-01', $trend['labels'][0]);
        $this->assertSame('2026-03-07', $trend['labels'][6]);
    }

    public function test_volume_trend_buckets_weekly_for_a_six_month_custom_range()
    {
        $this->actingAdmin();

        $response = $this->get(route('page.reports.index', [
            'period' => 'custom',
            'start' => '2026-01-01',
            'end' => '2026-06-30',
        ]));

        $response->assertStatus(200);
        $trend = $response->viewData('reportVolumeTrend');
        // 181-day range: well over the 92-day daily threshold, under the
        // 730-day weekly ceiling. Weekly buckets over ~26 weeks.
        $this->assertLessThan(60, count($trend['labels']));
        $this->assertGreaterThan(20, count($trend['labels']));
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $trend['labels'][0]);
    }

    public function test_volume_trend_buckets_monthly_for_all_time()
    {
        $this->actingAdmin();

        $response = $this->get(route('page.reports.index', ['period' => 'all']));

        $response->assertStatus(200);
        $trend = $response->viewData('reportVolumeTrend');
        foreach ($trend['labels'] as $label) {
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}$/', $label);
        }
    }

    public function test_volume_trend_zero_days_show_as_zero_not_missing()
    {
        $this->actingAdmin();

        $response = $this->get(route('page.reports.index', [
            'period' => 'custom',
            'start' => '2000-01-01',
            'end' => '2000-01-05',
        ]));

        $response->assertStatus(200);
        $trend = $response->viewData('reportVolumeTrend');
        $this->assertCount(5, $trend['labels']);
        $this->assertSame([0, 0, 0, 0, 0], $trend['counts']);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ReportsPeriodControlTest`
Expected: FAIL on the 4 new methods — `buildVolumeTrendWidget()` doesn't yet accept a 4th parameter or bucket adaptively (always buckets daily today).

- [ ] **Step 3: Implement adaptive bucketing**

In `app/Http/Controllers/Admin/ReportsController.php`, replace `buildVolumeTrendWidget()` entirely:

```php
    private function buildVolumeTrendWidget($tickets, Carbon $start, Carbon $end, ?string $forceUnit = null): array
    {
        $rangeDays = $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay());

        if ($forceUnit) {
            $unit = $forceUnit;
        } elseif ($rangeDays <= 92) {
            $unit = 'day';
        } elseif ($rangeDays <= 730) {
            $unit = 'week';
        } else {
            $unit = 'month';
        }

        $counts = [];
        $cursor = $start->copy()->startOfDay();
        $lastDay = $end->copy()->startOfDay();

        if ($unit === 'week') {
            $cursor = $cursor->startOfWeek();
        } elseif ($unit === 'month') {
            $cursor = $cursor->startOfMonth();
        }

        while ($cursor->lte($lastDay)) {
            $label = $unit === 'month' ? $cursor->format('Y-m') : $cursor->format('Y-m-d');
            $counts[$label] = 0;

            if ($unit === 'day') {
                $cursor->addDay();
            } elseif ($unit === 'week') {
                $cursor->addWeek();
            } else {
                $cursor->addMonth();
            }
        }

        foreach ($tickets as $ticket) {
            $createdAt = Carbon::parse($ticket->created_at);

            if ($unit === 'day') {
                $label = $createdAt->format('Y-m-d');
            } elseif ($unit === 'week') {
                $label = $createdAt->copy()->startOfWeek()->format('Y-m-d');
            } else {
                $label = $createdAt->format('Y-m');
            }

            if (array_key_exists($label, $counts)) {
                $counts[$label]++;
            }
        }

        return [
            'labels' => array_keys($counts),
            'counts' => array_values($counts),
        ];
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=ReportsPeriodControlTest`
Expected: PASS (12 tests total now)

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Admin/ReportsController.php tests/Feature/ReportsPeriodControlTest.php
git commit -m "feat: adaptive daily/weekly/monthly bucketing for volume trend"
```

---

### Task 3: Backend — Reassignment by Division (HR office only, zero-filled)

**Files:**
- Modify: `app/Http/Controllers/Admin/ReportsController.php` (imports, `buildReassignmentWidget()`)
- Test: append to `tests/Feature/ReportsPeriodControlTest.php`

**Interfaces:**
- Produces: `buildReassignmentWidget()`'s return array replaces `'by_department'`/`'department_labels'`/`'department_rates'` with `'by_division'`/`'division_labels'`/`'division_rates'`. `'reassigned_count'`, `'total'`, `'rate'` keys are unchanged. Task 7's frontend consumes the new `division_*` keys.
- Consumes: `$widgetTickets` now eager-loads `'division'` (added in Task 1, Step 4) and selects `division_id` — this task relies on that already being in place.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/ReportsPeriodControlTest.php`:

```php
    public function test_reassignment_widget_is_scoped_to_hr_office_divisions()
    {
        $admin = $this->actingAdmin();

        $hrDept = \App\Models\Department::find(1);
        $this->assertNotNull($hrDept, 'Expected HR department (id 1) to exist.');
        $itDivision = \App\Models\Division::where('department_id', 1)->where('division_name', 'Information Technology')->first();
        $this->assertNotNull($itDivision, 'Expected an Information Technology division under the HR office.');

        $priority = Priority::firstOrCreate(['priority_name' => 'Normal'], ['priority_color' => '#ccc']);
        $unassigned = Status::firstOrCreate(['status_name' => 'Unassigned'], ['status_color' => '#ccc']);
        $issue = Issue::create([
            'department_id' => $hrDept->id,
            'division_id' => $itDivision->id,
            'priority_id' => $priority->id,
            'issue_description' => 'Reassign-by-division Test Issue ' . uniqid(),
        ]);

        $ticket = $this->makeTicket($hrDept, $itDivision, $issue, $unassigned, $admin, Carbon::now()->subDays(2));

        \App\Models\TicketReassignmentRequest::create([
            'ticket_id' => $ticket->id,
            'from_department_id' => $hrDept->id,
            'to_department_id' => $hrDept->id,
            'requested_by' => $admin->id,
            'status' => \App\Models\TicketReassignmentRequest::STATUS_PENDING,
        ]);

        $response = $this->get(route('page.reports.index', ['period' => 'custom', 'start' => Carbon::now()->subDays(7)->format('Y-m-d'), 'end' => Carbon::now()->format('Y-m-d')]));

        $response->assertStatus(200);
        $reassignment = $response->viewData('reportReassignment');

        $this->assertArrayHasKey('division_labels', $reassignment);
        $this->assertArrayNotHasKey('department_labels', $reassignment);
        $this->assertContains('Information Technology', $reassignment['division_labels']);
        // Zero-filled: every HR division appears even with zero reassignments,
        // e.g. Payroll should be present at index with rate 0 if no ticket exists for it.
        $this->assertContains('Payroll', $reassignment['division_labels']);

        $itIndex = array_search('Information Technology', $reassignment['division_labels'], true);
        $this->assertGreaterThan(0, $reassignment['division_rates'][$itIndex]);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ReportsPeriodControlTest`
Expected: FAIL — `division_labels` doesn't exist yet, `department_labels` still does.

- [ ] **Step 3: Implement**

In `app/Http/Controllers/Admin/ReportsController.php`, add `use App\Models\Department;` to the imports (near the existing `use App\Models\Division;`).

Replace `buildReassignmentWidget()` entirely:

```php
    private function buildReassignmentWidget($tickets, Carbon $start, Carbon $end): array
    {
        $total = $tickets->count();

        $reassignedTicketIds = TicketReassignmentRequest::whereHas('ticket', function ($query) use ($start, $end) {
            $query->whereBetween('created_at', [$start, $end]);
        })->distinct()->pluck('ticket_id')->all();

        $reassignedCount = count($reassignedTicketIds);

        $hrDepartmentId = (int) Department::where('department_name', 'City Human Resource Development Office')->value('id');

        // Zero-fill every HR division up front so the chart always shows a
        // stable, complete set of bars rather than only the divisions that
        // happened to have a ticket in this range.
        $byDivision = [];
        if ($hrDepartmentId) {
            Division::where('department_id', $hrDepartmentId)
                ->orderBy('division_name')
                ->pluck('division_name')
                ->each(function ($name) use (&$byDivision) {
                    $byDivision[$name] = ['total' => 0, 'reassigned' => 0];
                });
        }

        foreach ($tickets as $ticket) {
            if ($hrDepartmentId && (int) $ticket->department_id !== $hrDepartmentId) {
                continue;
            }

            $divisionName = optional($ticket->division)->division_name ?? 'Unassigned Division';
            if (!isset($byDivision[$divisionName])) {
                $byDivision[$divisionName] = ['total' => 0, 'reassigned' => 0];
            }
            $byDivision[$divisionName]['total']++;
            if (in_array($ticket->id, $reassignedTicketIds, true)) {
                $byDivision[$divisionName]['reassigned']++;
            }
        }

        $divisionLabels = [];
        $divisionRates = [];
        foreach ($byDivision as $name => $counts) {
            $rate = $counts['total'] > 0 ? round(($counts['reassigned'] / $counts['total']) * 100, 1) : 0.0;
            $byDivision[$name]['rate'] = $rate;
            $divisionLabels[] = $name;
            $divisionRates[] = $rate;
        }

        return [
            'reassigned_count' => $reassignedCount,
            'total' => $total,
            'rate' => $total > 0 ? round(($reassignedCount / $total) * 100, 1) : 0.0,
            'by_division' => $byDivision,
            'division_labels' => $divisionLabels,
            'division_rates' => $divisionRates,
        ];
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=ReportsPeriodControlTest`
Expected: PASS (13 tests total now)

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Admin/ReportsController.php tests/Feature/ReportsPeriodControlTest.php
git commit -m "feat: scope reassignment breakdown to HR office divisions, zero-filled"
```

---

### Task 4: Frontend — period control UI + 6 consolidated KPI cards

**Files:**
- Modify: `resources/views/admin/reports.blade.php`
- Test: `tests/Feature/ReportsPageWidgetsRenderTest.php` (add test methods)

**Interfaces:**
- Consumes: `activePeriod`, `activeHalf`, `activeYear`, `periodError` (Task 1), `$reportKpis['pending']` (Task 1, replacing `['open']`), `$reportWidgetStart`/`$reportWidgetEnd` (existing).
- Produces: removes both old KPI rows entirely (lines 16-124 of the pre-task file) and the old "cover X–Y" caption; the six KPI tiles get stable ids (`kpiTotal`, `kpiResolved`, `kpiPending`, `kpiAvgResolution`, `kpiOverdue`, `kpiReopenRate`) that later tasks/tests can target. `#periodSelect`/`#halfSelect`/`#halfToggleWrap`/`#customRangeWrap`/`#customStart`/`#customEnd`/`#customApplyBtn` element ids are the interface Task 4's own JS (added in this same task) binds to — no other task depends on these ids.

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/ReportsPageWidgetsRenderTest.php` (inside the class):

```php
    public function test_reports_page_shows_exactly_six_kpi_cards_with_period_control()
    {
        $this->actingAdmin();

        $response = $this->get(route('page.reports.index'));

        $response->assertStatus(200);
        $response->assertSee('id="periodSelect"', false);
        $response->assertSee('id="kpiTotal"', false);
        $response->assertSee('id="kpiResolved"', false);
        $response->assertSee('id="kpiPending"', false);
        $response->assertSee('id="kpiAvgResolution"', false);
        $response->assertSee('id="kpiOverdue"', false);
        $response->assertSee('id="kpiReopenRate"', false);
        $response->assertDontSee('Total Tickets (All-Time)', false);
        $response->assertDontSee('Total Tickets (Period)', false);
        $response->assertDontSee('>Users<', false);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ReportsPageWidgetsRenderTest`
Expected: FAIL on the new method — none of the new ids exist yet, the old "(All-Time)"/"(Period)" labels are still present.

- [ ] **Step 3: Implement the blade changes**

In `resources/views/admin/reports.blade.php`, replace everything from the start of `@section('content')` (the old line 15) through the end of the old period-KPI row (old line 124) — i.e. both the all-time row, the "cover X–Y" caption, and the period row — with:

```blade
@section('content')
    @if($periodError)
        <div class="row mb-2">
            <div class="col-12">
                <div class="alert alert-warning py-2 mb-0">{{ $periodError }}</div>
            </div>
        </div>
    @endif

    <div class="row mb-3 align-items-end" id="reportsPeriodControl">
        <div class="col-auto">
            <label for="periodSelect" class="small text-muted text-uppercase font-weight-bold mb-1">Date Range</label>
            <select id="periodSelect" class="form-control form-control-sm">
                <option value="all" {{ $activePeriod === 'all' ? 'selected' : '' }}>All Time</option>
                <option value="month" {{ $activePeriod === 'month' ? 'selected' : '' }}>This Month</option>
                <option value="half" {{ $activePeriod === 'half' ? 'selected' : '' }}>Semestral</option>
                <option value="custom" {{ $activePeriod === 'custom' ? 'selected' : '' }}>Custom</option>
            </select>
        </div>

        <div class="col-auto" id="halfToggleWrap" style="{{ $activePeriod === 'half' ? '' : 'display:none;' }}">
            <label for="halfSelect" class="small text-muted text-uppercase font-weight-bold mb-1">Half</label>
            <select id="halfSelect" class="form-control form-control-sm">
                <option value="h1" {{ $activeHalf === 'h1' ? 'selected' : '' }}>Jan &ndash; Jun {{ $activeYear }}</option>
                <option value="h2" {{ $activeHalf === 'h2' ? 'selected' : '' }}>Jul &ndash; Dec {{ $activeYear }}</option>
            </select>
        </div>

        <div class="col-auto" id="customRangeWrap" style="{{ $activePeriod === 'custom' ? '' : 'display:none;' }}">
            <div class="form-row align-items-end">
                <div class="col-auto">
                    <label for="customStart" class="small text-muted text-uppercase font-weight-bold mb-1">Start Date</label>
                    <input type="date" id="customStart" class="form-control form-control-sm" value="{{ request('start') }}">
                </div>
                <div class="col-auto">
                    <label for="customEnd" class="small text-muted text-uppercase font-weight-bold mb-1">End Date</label>
                    <input type="date" id="customEnd" class="form-control form-control-sm" value="{{ request('end') }}">
                </div>
                <div class="col-auto">
                    <button type="button" id="customApplyBtn" class="btn btn-sm btn-primary">Apply</button>
                </div>
            </div>
        </div>

        <div class="col-auto ml-auto">
            <small class="text-muted">Data shown for: {{ $reportWidgetStart->format('M j, Y') }} &ndash; {{ $reportWidgetEnd->format('M j, Y') }}</small>
        </div>
    </div>

    <div class="row">
        <div class="col-6 col-md-4 col-xl-2 mb-4">
            <div class="card text-white bg-dark h-100">
                <div class="card-body">
                    <div class="text-value" id="kpiTotal">{{ $reportKpis['total'] }}</div>
                    <small class="text-muted text-uppercase font-weight-bold">Total Tickets</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2 mb-4">
            <div class="card text-white bg-success h-100">
                <div class="card-body">
                    <div class="text-value" id="kpiResolved">{{ $reportKpis['resolved'] }}</div>
                    <small class="text-muted text-uppercase font-weight-bold">Resolved</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2 mb-4">
            <div class="card text-white bg-warning h-100">
                <div class="card-body">
                    <div class="text-value" id="kpiPending">{{ $reportKpis['pending'] }}</div>
                    <small class="text-muted text-uppercase font-weight-bold">Pending</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2 mb-4">
            <div class="card text-white h-100" style="background-color: #4BC0C0;">
                <div class="card-body">
                    <div class="text-value" id="kpiAvgResolution">{{ $reportKpis['avg_resolution_formatted'] }}</div>
                    <small class="text-muted text-uppercase font-weight-bold">Avg Resolution</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2 mb-4">
            <div class="card text-white h-100" style="background-color: {{ $reportKpis['overdue_pct'] >= 20 ? '#dc3545' : '#28a745' }};">
                <div class="card-body">
                    <div class="text-value" id="kpiOverdue">{{ $reportKpis['overdue_pct'] }}%</div>
                    <small class="text-muted text-uppercase font-weight-bold">Overdue (SLA)</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2 mb-4">
            <div class="card text-white h-100" style="background-color: #9966FF;">
                <div class="card-body">
                    <div class="text-value" id="kpiReopenRate">{{ $reportKpis['reopen_rate'] }}%</div>
                    <small class="text-muted text-uppercase font-weight-bold">Reopen Rate</small>
                </div>
            </div>
        </div>
    </div>
```

At the top of the existing `<script>` block inside `@push('after_scripts')` (right after `const reportVolumeTrendLabels = ...` and its sibling `const`s — i.e., add these new lines alongside the existing `const` declarations, not replacing them), add:

```blade
function buildReportsUrl(params) {
    const url = new URL(window.location.href);
    url.search = '';
    Object.keys(params).forEach(function (key) {
        if (params[key] !== null && params[key] !== undefined && params[key] !== '') {
            url.searchParams.set(key, params[key]);
        }
    });
    return url.toString();
}
```

And add this to the existing `$(document).ready(function () { ... })` call in that same script block (append inside the function body, alongside the existing `initVolumeTrendChart();` etc. calls):

```blade
    const periodSelect = document.getElementById('periodSelect');
    const halfSelect = document.getElementById('halfSelect');
    const halfWrap = document.getElementById('halfToggleWrap');
    const customWrap = document.getElementById('customRangeWrap');
    const customStart = document.getElementById('customStart');
    const customEnd = document.getElementById('customEnd');
    const customApplyBtn = document.getElementById('customApplyBtn');

    if (periodSelect) {
        periodSelect.addEventListener('change', function () {
            const period = periodSelect.value;
            if (halfWrap) halfWrap.style.display = period === 'half' ? '' : 'none';
            if (customWrap) customWrap.style.display = period === 'custom' ? '' : 'none';

            if (period === 'all' || period === 'month') {
                window.location.href = buildReportsUrl({ period: period });
            } else if (period === 'half') {
                window.location.href = buildReportsUrl({ period: 'half', half: halfSelect.value });
            }
        });
    }

    if (halfSelect) {
        halfSelect.addEventListener('change', function () {
            window.location.href = buildReportsUrl({ period: 'half', half: halfSelect.value });
        });
    }

    if (customApplyBtn) {
        customApplyBtn.addEventListener('click', function () {
            window.location.href = buildReportsUrl({
                period: 'custom',
                start: customStart.value,
                end: customEnd.value
            });
        });
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=ReportsPageWidgetsRenderTest`
Expected: PASS (all methods in this file, including the new one)

- [ ] **Step 5: Commit**

```bash
git add resources/views/admin/reports.blade.php tests/Feature/ReportsPageWidgetsRenderTest.php
git commit -m "feat: replace 12 static KPI cards with period control + 6 dynamic cards"
```

---

### Task 5: Frontend — Volume Trend / Resolution Distribution chart row cleanup

**Files:**
- Modify: `resources/views/admin/reports.blade.php`

**Interfaces:**
- Consumes: `$reportVolumeTrend['labels'/'counts']` (Task 2's adaptive bucketing — the JS/chart code doesn't change, it already just renders whatever it's given).
- No new ids/keys produced for other tasks to consume.

- [ ] **Step 1: Update the chart card markup**

In `resources/views/admin/reports.blade.php`, replace the Volume Trend / Resolution Distribution row:

```blade
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

with:

```blade
    <div class="row">
        <div class="col-md-8 mb-4">
            <div class="card h-100">
                <div class="card-header"><strong>Ticket Volume Trend</strong></div>
                <div class="card-body">
                    <div style="height: 300px; position: relative;">
                        <canvas id="volumeTrendChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-header"><strong>Resolution Time Distribution</strong></div>
                <div class="card-body">
                    <div style="height: 300px; position: relative;">
                        <canvas id="resolutionDistributionChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
```

No JS changes needed — `initVolumeTrendChart()`/`initResolutionDistributionChart()` already use `responsive: true, maintainAspectRatio: false`, which fills whatever positioned container the canvas sits in.

- [ ] **Step 2: Verify no test regression**

Run: `php artisan test --filter=ReportsPageWidgetsRenderTest`
Expected: PASS — this task only changes wrapper `<div>`s and CSS classes around the same `id="volumeTrendChart"`/`id="resolutionDistributionChart"` canvases the existing test asserts on; no id or text content changed.

- [ ] **Step 3: Commit**

```bash
git add resources/views/admin/reports.blade.php
git commit -m "style: fixed-height canvas wrappers and equal card heights for trend/distribution row"
```

---

### Task 6: Frontend — hide Overdue-by-Department chart, re-flow Issue-Type table

**Files:**
- Modify: `resources/views/admin/reports.blade.php`
- Test: `tests/Feature/ReportsPageWidgetsRenderTest.php` (update one existing test method)

**Interfaces:**
- No new interfaces — `$reportSlaBreakdown['by_issue']` consumption is unchanged, only the surrounding markup/width changes and the department chart's card is wrapped in `@if(false)`.

- [ ] **Step 1: Update the existing test that currently asserts the department chart is present**

In `tests/Feature/ReportsPageWidgetsRenderTest.php`, `test_reports_page_renders_sla_and_funnel_charts` currently has:

```php
        $response->assertSee('id="slaByDepartmentChart"', false);
```

Change that line to:

```php
        $response->assertDontSee('id="slaByDepartmentChart"', false);
```

(Leave the rest of that test method — `assertStatus(200)`, `assertSee('id="statusFunnelChart"', false)`, `assertSee('Overdue vs On-Time', false)` — unchanged; "Overdue vs On-Time" still appears via the Issue Type table's card header.)

- [ ] **Step 2: Run test to verify it fails against the current (untouched) blade**

Run: `php artisan test --filter=ReportsPageWidgetsRenderTest --filter=test_reports_page_renders_sla_and_funnel_charts`
Expected: FAIL — the department chart is still rendered unconditionally at this point, so `assertDontSee` fails.

- [ ] **Step 3: Implement the blade changes**

In `resources/views/admin/reports.blade.php`, replace this block:

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
```

with:

```blade
    {{--
        Overdue vs On-Time by Department chart hidden: the org is currently
        single-department (City Human Resource Development Office is the
        only department in the system), so a per-department breakdown adds
        no signal. Kept in code (not deleted) so it can be restored once
        multiple departments are active — ReportsController::buildSlaBreakdownWidget()
        still computes 'department_labels'/'department_overdue'/'department_on_time'
        for this chart, unchanged.
    --}}
    @if(false)
    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header"><strong>Overdue vs On-Time by Department</strong></div>
                <div class="card-body">
                    <div style="height: 300px; position: relative;">
                        <canvas id="slaByDepartmentChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <div class="row">
        <div class="col-md-12 mb-4">
            <div class="card h-100">
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
```

In the `<script>` block inside `@push('after_scripts')`, find the `$(document).ready(function () { ... })` call that invokes `initSlaByDepartmentChart();` and remove just that one line (leave `initVolumeTrendChart();`, `initResolutionDistributionChart();`, `initStatusFunnelChart();`, `initReassignmentByDeptChart();` — the last one is renamed in Task 7 — untouched). Leave the `function initSlaByDepartmentChart() { ... }` definition itself in place (harmless if unused, and ready to be re-wired when the chart is restored) but add a one-line comment directly above its declaration: `// Not called while the by-department chart is hidden (see the @if(false) block in the blade above).`

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=ReportsPageWidgetsRenderTest`
Expected: PASS (all methods)

- [ ] **Step 5: Commit**

```bash
git add resources/views/admin/reports.blade.php tests/Feature/ReportsPageWidgetsRenderTest.php
git commit -m "feat: hide overdue-by-department chart (restorable), widen issue-type table"
```

---

### Task 7: Frontend — Reassignment by Division + Status Funnel row cleanup

**Files:**
- Modify: `resources/views/admin/reports.blade.php`
- Test: `tests/Feature/ReportsPageWidgetsRenderTest.php` (update one existing test method)

**Interfaces:**
- Consumes: `$reportReassignment['division_labels'/'division_rates']` (Task 3, replacing `department_labels`/`department_rates`).

- [ ] **Step 1: Update the existing test**

In `tests/Feature/ReportsPageWidgetsRenderTest.php`, `test_reports_page_renders_staff_workload_columns_and_reassignment_widget` currently has:

```php
        $response->assertSee('id="reassignmentByDeptChart"', false);
```

Change to:

```php
        $response->assertSee('id="reassignmentByDivisionChart"', false);
        $response->assertSee('Reassignment by Division', false);
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ReportsPageWidgetsRenderTest --filter=test_reports_page_renders_staff_workload_columns_and_reassignment_widget`
Expected: FAIL — the canvas is still `id="reassignmentByDeptChart"` and titled "Reassignment by Department" at this point.

- [ ] **Step 3: Implement the blade changes**

In `resources/views/admin/reports.blade.php`, replace the Status Funnel row:

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

with:

```blade
    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header"><strong>Status Funnel</strong></div>
                <div class="card-body">
                    <div style="height: 280px; position: relative;">
                        <canvas id="statusFunnelChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card h-100">
                <div class="card-body text-center d-flex flex-column justify-content-center">
                    <div class="text-value">{{ $reportReassignment['rate'] }}%</div>
                    <small class="text-muted text-uppercase font-weight-bold">Reassignment Rate</small>
                    <div class="text-muted small mt-1">{{ $reportReassignment['reassigned_count'] }} of {{ $reportReassignment['total'] }} tickets</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card h-100">
                <div class="card-header"><strong>Reassignment by Division</strong></div>
                <div class="card-body">
                    <div style="height: 220px; position: relative;">
                        <canvas id="reassignmentByDivisionChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
```

In the `<script>` block inside `@push('after_scripts')`, replace:

```blade
const reportReassignDeptLabels = @json($reportReassignment['department_labels']);
const reportReassignDeptRates = @json($reportReassignment['department_rates']);
```

with:

```blade
const reportReassignDivisionLabels = @json($reportReassignment['division_labels']);
const reportReassignDivisionRates = @json($reportReassignment['division_rates']);
```

Replace the `initReassignmentByDeptChart()` function:

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

with:

```blade
function initReassignmentByDivisionChart() {
    const el = document.getElementById('reassignmentByDivisionChart');
    if (!el || !reportReassignDivisionLabels.length) return;
    new Chart(el, {
        type: 'bar',
        data: {
            labels: reportReassignDivisionLabels,
            datasets: [{
                label: 'Reassignment Rate %',
                data: reportReassignDivisionRates,
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

And update the `$(document).ready(...)` call's `initReassignmentByDeptChart();` line to `initReassignmentByDivisionChart();`.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=ReportsPageWidgetsRenderTest`
Expected: PASS (all methods)

- [ ] **Step 5: Commit**

```bash
git add resources/views/admin/reports.blade.php tests/Feature/ReportsPageWidgetsRenderTest.php
git commit -m "feat: rename reassignment breakdown chart to by-division, equalize funnel row heights"
```

---

### Task 8: Regression sweep — full suite, MSSQL re-grep, PDF/CSS/ARTA check

**Files:**
- No production code changes expected — this task verifies Tasks 1-7 didn't regress anything and performs the required final checks.

**Interfaces:**
- Consumes: everything from Tasks 1-7.

- [ ] **Step 1: Run the full test suite**

Run: `php artisan test`
Expected: all Reports-related tests pass. The one pre-existing unrelated failure from the prior pass (`HrChatConversationMemoryTest::bare_followup_retrieves_wellness_leave_not_omnibus`, a chatbot retrieval test untouched by any Reports work) is expected to still fail — confirm it's the *only* failure, and that it's unrelated to any file this plan touched (`ReportsController.php`, `reports.blade.php`, and this plan's own test files).

- [ ] **Step 2: Re-grep for MySQL-only SQL functions**

Run: `grep -rn "TIMESTAMPDIFF\|DATE_FORMAT\|GROUP_CONCAT\|IFNULL\|STR_TO_DATE\|UNIX_TIMESTAMP" app/ resources/views/admin/`
Expected: only hits inside `app/Services/SqlDialectHelper.php` (unchanged from the prior pass — this plan doesn't touch that file). No hits in `ReportsController.php` or any blade file.

- [ ] **Step 3: PDF export regression check**

Run: `php artisan test --filter=ReportsPdfRegressionTest`
Expected: PASS (all 5 methods, including the T10 survey/ARTA cross-check) — confirms `downloadPdf()` is unaffected by the `index()`/`getReportData()` changes, since it always passes explicit validated dates and never touches the new all-time branch or `resolvePeriod()`.

- [ ] **Step 4: CSS (survey) and ARTA report pages — confirm unaffected**

These are already covered by `ReportsPdfRegressionTest::test_survey_and_arta_report_pages_still_load_on_mssql` (run in Step 1/3 above) — this plan makes no changes to `SurveyReportsController`, `ArtaSurveyReportsController`, or their views, so no new test is needed; confirm that existing test still passes as part of Step 1's full run.

- [ ] **Step 5: Manual layout/responsiveness check (T7 — cannot be fully automated without a JS/browser runner)**

Start the app locally, log in as a user with `reports.view`, open the Reports page, and confirm at 1200px, 768px, and 375px widths:
- The 6 KPI tiles are evenly sized and aligned in one row (wrapping to 2-3 per row on narrower widths per the `col-6 col-md-4 col-xl-2` classes).
- Volume Trend and Resolution Distribution charts are the same height and fill their cards without overflowing.
- The Issue Type table spans full width with no dead space beside it.
- Status Funnel, Reassignment Rate, and Reassignment by Division cards line up at equal height.
- No stray/empty card appears near the top (this was an artifact of the old 12-card layout, which Task 4 removed entirely).
- Switching the period dropdown (All Time / This Month / Semestral / Custom) reloads the page with the right query params in the URL and the 6 KPI tiles update.

- [ ] **Step 6: Commit**

No production files change in this task. If Step 1 surfaces any regression, stop and fix the offending task before considering this plan complete — do not commit a "fix" here; return to the relevant task.

---

## Post-Plan: Final Report Checklist

After Task 8, produce the final report the original brief requested:

1. Part 0 findings (already gathered above — card/chart structure, chart-instance storage (0c → reload chosen), HR office id + divisions (0e)).
2. Update mechanism used (query-param reload) and why (0c: no stored Chart.js instances).
3. "This Month" and "Semestral" definitions, default half/year, and the default range on first load ("This Month" — stated rationale in Global Constraints).
4. Volume-trend bucketing thresholds (≤92 days daily, 93-730 days weekly, >730 days or All Time monthly).
5. Every file changed, pulled from the task list above.
6. T1-T9 results, with the independently-verified numbers for T1/T3/T5 pulled from `ReportsPeriodControlTest`'s assertions.
