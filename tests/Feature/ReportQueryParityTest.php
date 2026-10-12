<?php

namespace Tests\Feature;

use Tests\Support\LegacySurveyReports;
use App\Models\Survey;
use App\Models\Ticket;
use App\Models\Status;
use App\Models\User;
use App\Services\TicketReportQueries;
use App\Services\TicketReportWidgets;
use App\Services\SurveyReportQueries;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReportQueryParityTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_ticket_queries_equal_original_widgets_across_chunks_and_date_buckets()
    {
        Carbon::setTestNow(Carbon::parse('2026-10-07 12:00:00'));
        $owner = User::first();
        $resolved = (int) Status::idByName('Resolved');
        $pending = (int) Status::idByName('Pending');
        $reopened = (int) Status::idByName('Reopened');
        $states = [$resolved, $pending, $reopened, (int) Status::idByName('Unassigned')];
        $hours = [0, 23, 24, 71, 72, 73, 167, 168, -73];
        $rows = [];
        for ($i = 0; $i < 553; $i++) {
            $created = now()->subDays($i % 850)->subSeconds($i % 59);
            $rows[] = ['reference_id' => 'PARITY-' . $i, 'user_id' => $owner->id, 'department_id' => $i % 5 ? 1 : null,
                'division_id' => null, 'status_id' => $states[$i % 4], 'custom_issue' => $i % 3 ? 'Shared issue' : null,
                'message' => 'Parity fixture', 'created_at' => $created, 'updated_at' => $created,
                'resolved_at' => $i % 7 ? $created->copy()->addHours($hours[$i % 9])->addSeconds(59) : null,
                'reopened_at' => $i % 11 ? null : $created, 'resolved_by' => $i % 2 ? $owner->id : null];
        }
        foreach (array_chunk($rows, 50) as $chunk) { DB::table('tickets')->insert($chunk); }
        $ids = Ticket::where('reference_id', 'like', 'PARITY-%')->orderBy('id')->pluck('id');
        foreach (['pending', 'approved', 'rejected', 'cancelled'] as $i => $state) {
            DB::table('ticket_reassignment_requests')->insert(['ticket_id' => $ids[$i], 'from_department_id' => 1,
                'to_department_id' => 1, 'requested_by' => $owner->id, 'status' => $state, 'created_at' => now(), 'updated_at' => now()]);
        }
        foreach ([[90, null], [500, null], [900, null], [900, 'month'], [0, null]] as $range) {
            $start = now()->subDays($range[0])->startOfDay(); $end = now()->endOfDay();
            $query = Ticket::whereBetween('created_at', [$start, $end]);
            $tickets = (clone $query)->with(['department', 'division', 'issue'])->orderBy('id')->get();
            $expected = ['kpis' => TicketReportWidgets::buildKpiWidget($tickets, $resolved, $pending, $reopened),
                'volume' => TicketReportWidgets::buildVolumeTrendWidget($tickets, $start, $end, $range[1]),
                'resolution' => TicketReportWidgets::buildResolutionDistributionWidget($tickets, $resolved),
                'sla' => TicketReportWidgets::buildSlaBreakdownWidget($tickets, $resolved),
                'funnel' => TicketReportWidgets::buildStatusFunnelWidget($tickets),
                'reassignment' => TicketReportWidgets::buildReassignmentWidget($tickets, $start, $end)];
            $this->assertEquals($expected, TicketReportQueries::widgets($query, $start, $end, $range[1]));
            $staffQuery = (clone $query)->where('status_id', $resolved)->whereNotNull('resolved_by')->whereNotNull('resolved_at');
            $original = (clone $staffQuery)->get()->groupBy('resolved_by')->map(function ($tickets) {
                return (int) round($tickets->map(function ($ticket) { return Carbon::parse($ticket->created_at)->diffInMinutes(Carbon::parse($ticket->resolved_at)); })->avg());
            });
            $this->assertEquals($original->all(), TicketReportQueries::staffResolutionAverages($staffQuery)->all());
        }
        $start = now()->addYear(); $end = $start->copy()->endOfDay();
        $this->assertSame(0, TicketReportQueries::widgets(Ticket::whereBetween('created_at', [$start, $end]), $start, $end)['kpis']['total']);
    }

    public function test_survey_grouped_queries_equal_original_for_all_staff_ratings_and_zero_activity()
    {
        $owner = User::first();
        $staff = User::create(['name' => 'Parity HR', 'username' => 'parity_hr', 'email' => 'parity@example.test', 'password' => 'unused', 'department_id' => 1, 'is_active' => true]);
        $staff->assignRole('hr_staff');
        $zero = User::create(['name' => 'Zero HR', 'username' => 'parity_zero', 'email' => 'zero@example.test', 'password' => 'unused', 'department_id' => 1, 'is_active' => true]);
        $zero->assignRole('hr_staff');
        $choices = ['Very Satisfied', 'Satisfied', 'Dissatisfied', 'Very Dissatisfied'];
        $rows = [];
        for ($i = 0; $i < 507; $i++) {
            $rows[] = ['user_id' => $i % 5 ? $staff->id : $owner->id, 'issue_id' => 1,
                'timeliness_rating' => $choices[$i % 4], 'handling_rating' => $choices[($i + 1) % 4],
                'quality_rating' => $choices[($i + 2) % 4], 'overall_rating' => $choices[($i + 3) % 4],
                'created_at' => now()->subDays($i % 30), 'updated_at' => now()];
        }
        foreach (array_chunk($rows, 50) as $chunk) { DB::table('surveys')->insert($chunk); }
        DB::table('surveys')->insert(['user_id' => $staff->id, 'issue_id' => 1, 'timeliness_rating' => 'satisfied',
            'handling_rating' => 'Satisfied ', 'quality_rating' => 'Legacy value', 'overall_rating' => 'Satisfied', 'created_at' => now(), 'updated_at' => now()]);
        $controller = new LegacySurveyReports;
        $method = new \ReflectionMethod($controller, 'getReportData'); $method->setAccessible(true);
        foreach ([[null, null], [now()->subDays(10), now()], [now()->addYear(), now()->addYear()->endOfDay()]] as $range) {
            foreach ([true, false] as $includeZero) {
                $old = $method->invoke($controller, $range[0], $range[1], $includeZero);
                $new = SurveyReportQueries::summary($range[0], $range[1], $includeZero);
                $this->assertEquals($old['summaryCards'], $new['summaryCards']);
                $this->assertEquals($old['surveyRatings']->all(), $new['surveyRatings']->all());
            }
        }
    }
}
