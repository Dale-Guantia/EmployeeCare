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
}
