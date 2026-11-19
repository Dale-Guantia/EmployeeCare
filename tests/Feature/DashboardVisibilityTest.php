<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Division;
use App\Models\Status;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketReportWidgets;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardVisibilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-10-14 12:00:00'));
    }

    protected function tearDown(): void
    {
        \Carbon\Carbon::setTestNow();
        parent::tearDown();
    }
    #[\PHPUnit\Framework\Attributes\DataProvider('roles')]
    public function test_every_analytics_query_uses_ticket_visibility($role)
    {
        $department = Department::create(['department_name' => 'Scoped dashboard']);
        $division = Division::create(['department_id' => $department->id, 'division_name' => 'Scoped division']);
        $otherDivision = Division::create(['department_id' => $department->id, 'division_name' => 'Other scoped division']);
        $foreign = Department::create(['department_name' => 'Foreign dashboard']);
        $foreignDivision = Division::create(['department_id' => $foreign->id, 'division_name' => 'Hidden division']);
        $actor = User::create(['username' => 'dash_actor', 'name' => 'Actor', 'password' => 'unused', 'is_active' => true,
            'department_id' => $department->id, 'division_id' => $division->id]);
        $actor->assignRole($role);
        $owner = User::role('employee')->first();
        $resolved = Status::idByCode(Status::RESOLVED); $pending = Status::idByCode(Status::PENDING);
        $rows = [
            [$actor->id, $foreign->id, $foreignDivision->id, null, $resolved, '2026-10-01 09:00:00'],
            [$owner->id, $foreign->id, $foreignDivision->id, $actor->id, $pending, '2026-10-02 09:00:00'],
            [$owner->id, $department->id, $otherDivision->id, null, $resolved, '2026-10-03 09:00:00'],
            [$owner->id, $department->id, $division->id, null, $pending, '2026-10-04 09:00:00'],
            [$owner->id, $foreign->id, $foreignDivision->id, null, $pending, '2026-10-05 09:00:00'],
            [$owner->id, $foreign->id, $foreignDivision->id, null, $resolved, '2026-10-06 09:00:00'],
            [$owner->id, $foreign->id, $foreignDivision->id, null, $pending, '2018-01-01 09:00:00'],
        ];
        $ids = [];
        foreach ($rows as $i => $row) {
            $ids[] = DB::table('tickets')->insertGetId(['reference_id' => 'DASH-SCOPE-' . $i, 'user_id' => $row[0], 'department_id' => $row[1],
                'division_id' => $row[2], 'assigned_to' => $row[3], 'status_id' => $row[4], 'message' => 'Scope fixture',
                'custom_issue' => 'Scope issue ' . $i, 'created_at' => $row[5], 'resolved_at' => '2026-10-10 12:00:00']);
        }
        foreach ([$ids[0], $ids[4], $ids[5], $ids[6]] as $id) {
            DB::table('ticket_reassignment_requests')->insert(['ticket_id' => $id, 'to_department_id' => $department->id,
                'to_division_id' => $division->id, 'requested_by' => $owner->id, 'status' => $id === $ids[4] ? 'pending' : 'approved']);
        }
        $visible = Ticket::visibleTo($actor)->get();
        $this->assertSame(['admin' => 7, 'dept_head' => 4, 'div_head' => 3, 'hr_staff' => 2][$role], $visible->count());
        if ($role === 'dept_head') $visible = $visible->where('department_id', $department->id);
        foreach ([['period' => 'all'], ['period' => 'custom', 'start' => '2026-10-02', 'end' => '2026-10-06']] as $filter) {
            $response = $this->actingAs($actor, 'web')->get(route('backpack.dashboard', $filter))->assertOk();
            $start = $response->viewData('dashWidgetStart'); $end = $response->viewData('dashWidgetEnd');
            $scoped = $visible->filter(function ($ticket) use ($start, $end) { return $ticket->created_at->betweenIncluded($start, $end); });
            $this->assertSame($scoped->count(), $response->viewData('dashKpis')['total']);
            $metrics = $response->viewData('dashboardMetrics');
            $open = $scoped->reject(fn ($ticket) => (int) $ticket->status_id === (int) $resolved);
            $this->assertSame($scoped->count(), $metrics['total']);
            $this->assertSame($open->count(), $metrics['open']);
            $this->assertSame($metrics['total'], $metrics['open'] + $metrics['resolved']);
            $this->assertSame($open->sortBy('created_at')->take(10)->pluck('id')->values()->all(),
                $response->viewData('attentionTickets')->pluck('ticket.id')->all());
            $this->assertSame($scoped->count(), (int) $response->viewData('statusData')->sum());
            $chartDepartment = $response->viewData('divisionChartDepartmentId');
            $chartTickets = Ticket::visibleTo($actor)->where('department_id', $chartDepartment)->get()
                ->filter(fn ($ticket) => $filter['period'] === 'all' || $ticket->created_at->betweenIncluded($start, $end));
            $this->assertSame($chartTickets->count(), (int) array_sum($response->viewData('divisionCounts')));
            $this->assertSame($scoped->count(), array_sum($response->viewData('volumeTrendCounts')));
            $this->assertSame($scoped->count(), $response->viewData('reassignmentTotal'));
            $reassigned = DB::table('ticket_reassignment_requests')->whereIn('ticket_id', $scoped->pluck('id'))->distinct()->count('ticket_id');
            $this->assertSame($reassigned, $response->viewData('reassignedCount'));
            $rates = array_combine($response->viewData('reassignDivisionLabels'), $response->viewData('reassignDivisionRates'));
            foreach ($scoped->groupBy('division_id') as $divisionId => $tickets) {
                $count = DB::table('ticket_reassignment_requests')->whereIn('ticket_id', $tickets->pluck('id'))->distinct()->count('ticket_id');
                $this->assertEquals(round($count / $tickets->count() * 100, 1), $rates[Division::findOrFail($divisionId)->division_name]);
            }
            $this->assertEquals(TicketReportWidgets::buildKpiWidget($scoped, $resolved, $pending, Status::idByCode(Status::REOPENED)), $response->viewData('dashKpis'));
            $this->assertEquals(TicketReportWidgets::buildSlaBreakdownWidget($scoped, $resolved)['by_issue'], $response->viewData('overdueByIssue'));
            $distribution = TicketReportWidgets::buildResolutionDistributionWidget($scoped, $resolved);
            $this->assertEquals($distribution['counts'], $response->viewData('resolutionCounts'));
            $this->assertEquals($scoped->sortByDesc('created_at')->pluck('id')->values()->all(), $response->viewData('latestTickets')->pluck('id')->all());
            if ($filter['period'] === 'all') { $this->assertSame($visible->min('created_at')->startOfDay()->toDateString(), $start->toDateString()); }
        }
    }

    public static function roles(): array { return [['admin'], ['dept_head'], ['div_head'], ['hr_staff']]; }

    public function test_ticket_view_is_required_and_employee_redirect_is_preserved()
    {
        $user = User::create(['username' => 'no_analytics', 'name' => 'No analytics', 'password' => 'unused', 'is_active' => true]);
        $this->actingAs($user, 'web')->get(route('backpack.dashboard'))->assertForbidden();
        $user->assignRole('employee');
        $this->actingAs($user, 'web')->get(route('backpack.dashboard'))->assertRedirect(route('submit-ticket.show'));
    }

    public function test_empty_visible_scope_does_not_use_hidden_history_or_reassignment_totals()
    {
        $owner = User::role('employee')->first(); $staff = User::role('hr_staff')->first();
        $ticket = DB::table('tickets')->insertGetId(['reference_id' => 'DASH-HIDDEN-ONLY', 'user_id' => $owner->id,
            'status_id' => Status::idByCode(Status::PENDING), 'message' => 'Hidden fixture', 'created_at' => '2010-01-01 00:00:00']);
        DB::table('ticket_reassignment_requests')->insert(['ticket_id' => $ticket, 'requested_by' => $owner->id,
            'to_department_id' => Department::first()->id, 'status' => 'approved']);
        $response = $this->actingAs($staff, 'web')->get(route('backpack.dashboard', ['period' => 'all']))->assertOk();
        $this->assertSame(0, $response->viewData('dashKpis')['total']);
        $this->assertSame(0, $response->viewData('reassignedCount'));
        $this->assertSame(0, (int) $response->viewData('statusData')->sum());
        $this->assertSame('2026-10-14', $response->viewData('dashWidgetStart')->toDateString());
    }
}
