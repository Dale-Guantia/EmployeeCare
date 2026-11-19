<?php

namespace Tests\Feature;

use App\Models\{Department, Division, Issue, Priority, Status, Ticket, User};
use App\Services\{DashboardTicketMetrics, TicketReportQueries};
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardOperationalMetricsTest extends TestCase
{
    private Department $department;
    private Department $foreign;
    private Division $division;
    private Division $otherDivision;
    private Division $foreignDivision;
    private User $admin;
    private User $staff;
    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-05 12:00:00');
        $this->department = Department::create(['department_name' => 'Operations department']);
        $this->foreign = Department::create(['department_name' => 'Foreign operations department']);
        $this->division = Division::create(['department_id' => $this->department->id, 'division_name' => 'Operations division']);
        $this->otherDivision = Division::create(['department_id' => $this->department->id, 'division_name' => 'Empty operations division']);
        $this->foreignDivision = Division::create(['department_id' => $this->foreign->id, 'division_name' => 'Foreign operations division']);
        $this->admin = User::role('admin')->firstOrFail();
        $this->admin->update(['department_id' => $this->department->id, 'division_id' => null]);
        $this->staff = User::factory()->create(['department_id' => $this->department->id, 'division_id' => $this->division->id, 'is_active' => true]);
        $this->actingAs($this->admin, 'web');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function ticket(string $status, $created, array $extra = []): int
    {
        return DB::table('tickets')->insertGetId($extra + ['reference_id' => 'OPS-' . ++$this->sequence,
            'user_id' => $this->admin->id, 'department_id' => $this->department->id, 'division_id' => $this->division->id,
            'assigned_to' => $this->staff->id, 'resolved_by' => $status === Status::RESOLVED ? $this->staff->id : null,
            'status_id' => Status::idByCode($status), 'custom_issue' => 'Operations fixture', 'message' => 'Operations fixture',
            'created_at' => $created]);
    }

    private function page(array $filters = [])
    {
        return $this->get(route('backpack.dashboard', $filters + ['period' => 'month', 'department_id' => $this->department->id]))->assertOk();
    }

    public function test_precise_deadlines_partition_open_and_resolved_tickets_without_rounding_hours(): void
    {
        $this->ticket(Status::UNASSIGNED, now()->subHours(72));
        $this->ticket(Status::PENDING, now()->subHours(72)->subSecond(), ['assigned_to' => null]);
        $this->ticket(Status::PENDING, now()->subHours(48));
        $this->ticket(Status::PENDING, now()->subHours(48)->addSecond());
        $this->ticket(Status::REOPENED, now()->subHour());
        $this->ticket(Status::PENDING, now()->subHour(), ['resolved_at' => now()]);
        foreach ([4320, 4320, 0, 60] as $i => $minutes) {
            $created = Carbon::parse('2026-11-01 08:00:00');
            $this->ticket(Status::RESOLVED, $created, ['resolved_at' => $created->copy()->addMinutes($minutes)->addSeconds($i === 1 ? 1 : 0)]);
        }
        $this->ticket(Status::RESOLVED, now()->subHour());
        $this->ticket(Status::RESOLVED, now()->subHour(), ['resolved_at' => now()->subHours(2)]);
        $response = $this->page();
        $m = $response->viewData('dashboardMetrics');
        foreach (['total' => 12, 'open' => 6, 'unassigned' => 2, 'overdue_open' => 2, 'due_soon' => 1,
            'resolved' => 6, 'timed_resolved' => 4, 'on_time_resolved' => 3, 'late_resolved' => 1,
            'reopened' => 1, 'untimed_resolved' => 2, 'avg_resolution_minutes' => 2175] as $key => $expected) {
            $this->assertSame($expected, $m[$key], $key);
        }
        $this->assertEquals(75, $m['on_time_rate']);
        $this->assertSame('36h 15m', $m['avg_resolution_formatted']);
        $this->assertSame(2, $response->viewData('dashboardIssues')[0]['overdue_open']);
        $this->assertSame(1, $response->viewData('dashboardIssues')[0]['late_resolved']);
        $leader = $response->viewData('staffLeaderboard')[0];
        $this->assertSame(4, $leader['resolved_count']);
        $this->assertSame(3, $leader['on_time_count']);
        $this->assertSame(1, $leader['overdue_count']);
        $this->assertSame('36h 15m', $leader['avg_resolution_formatted']);
        $response->assertSee('Average Resolution Time')->assertSee('4 valid timed resolutions');
        $response->assertDontSee('Visitors')->assertDontSee('Total Users');
    }

    public function test_no_timed_resolutions_are_distinct_from_a_real_zero_minute_resolution(): void
    {
        $this->assertNull($this->page()->viewData('dashboardMetrics')['on_time_rate']);
        $this->ticket(Status::RESOLVED, now()->subHour());
        $m = $this->page()->viewData('dashboardMetrics');
        $this->assertSame('—', $m['avg_resolution_formatted']);
        $this->assertNull($m['on_time_rate']);
        $this->ticket(Status::RESOLVED, now()->subHour(), ['resolved_at' => now()->subHour()]);
        $m = $this->page()->viewData('dashboardMetrics');
        $this->assertSame('0h 00m', $m['avg_resolution_formatted']);
        $this->assertEquals(100, $m['on_time_rate']);
        $this->assertSame(1, $m['untimed_resolved']);
    }

    public function test_dashboard_averages_round_after_averaging_exact_elapsed_times(): void
    {
        foreach ([1, 2] as $hours) {
            $created = now()->subHours($hours);
            $this->ticket(Status::RESOLVED, $created, ['resolved_at' => $created->copy()->addSeconds(59)]);
        }
        $response = $this->page();
        $this->assertSame('0h 01m', $response->viewData('dashboardMetrics')['avg_resolution_formatted']);
        $this->assertSame('0h 01m', $response->viewData('dashKpis')['avg_resolution_formatted']);
        $this->assertSame('0h 01m', $response->viewData('staffLeaderboard')[0]['avg_resolution_formatted']);
        $this->assertSame(0, TicketReportQueries::staffResolutionAverages(Ticket::where('department_id', $this->department->id))[$this->staff->id]);
    }

    public function test_top_summary_shows_the_filtered_average_instead_of_on_time_percentage(): void
    {
        $created = Carbon::parse('2026-11-01 08:00:00');
        $this->ticket(Status::RESOLVED, $created, ['resolved_at' => $created->copy()->addMinutes(60)]);
        $this->ticket(Status::RESOLVED, $created, ['resolved_at' => $created->copy()->addMinutes(120)]);
        $this->ticket(Status::RESOLVED, $created);
        $this->ticket(Status::PENDING, $created, ['resolved_at' => $created->copy()->addMinutes(900)]);
        foreach ([[$this->division->id, '1h 30m', 2], [$this->otherDivision->id, '—', 0]] as [$division, $average, $timed]) {
            $response = $this->page(['division_id' => $division, 'chart_department_id' => $this->foreign->id]);
            $document = new \DOMDocument();
            $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
            $xpath = new \DOMXPath($document);
            $cards = $xpath->query('//*[@data-dashboard-summary]/section');
            $this->assertSame(6, $cards->length);
            $averageCards = $xpath->query('//*[@data-dashboard-summary]/section[@aria-label="Average Resolution Time"]');
            $this->assertSame(1, $averageCards->length);
            $card = $averageCards->item(0);
            $this->assertSame($average, trim($xpath->query('.//*[@class="ec-dashboard-stat-value"]', $card)->item(0)->textContent));
            $this->assertStringContainsString($timed . ' valid timed resolutions', $card->textContent);
            $this->assertSame(0, $xpath->query('//*[@data-dashboard-summary]/section[@aria-label="On-time resolution"]')->length);
            $this->assertSame(1, $xpath->query('//*[self::h2][text()="Average Resolution Time"]')->length);
        }
    }

    public function test_global_filters_and_older_open_notice_do_not_silently_expand_the_selected_period(): void
    {
        $old = $this->ticket(Status::PENDING, '2026-10-01 08:00:00');
        $oldestCurrent = $this->ticket(Status::PENDING, '2026-11-01 08:00:00', ['assigned_to' => null]);
        $newer = $this->ticket(Status::PENDING, '2026-11-03 12:00:00');
        $foreign = $this->ticket(Status::PENDING, '2026-10-01 08:00:00', ['department_id' => $this->foreign->id,
            'division_id' => $this->foreignDivision->id]);
        $response = $this->page(['division_id' => $this->division->id, 'chart_department_id' => $this->foreign->id]);
        $this->assertSame(2, $response->viewData('dashboardMetrics')['total']);
        $this->assertSame(1, $response->viewData('olderOpenCount'));
        $this->assertSame([$oldestCurrent, $newer], $response->viewData('attentionTickets')->pluck('ticket.id')->all());
        $response->assertSee('data-older-open-notice', false)->assertSee('View All Time');
        $all = $this->page(['period' => 'all']);
        $this->assertSame(0, $all->viewData('olderOpenCount'));
        $this->assertSame([$old, $oldestCurrent, $newer], $all->viewData('attentionTickets')->pluck('ticket.id')->all());
        $this->assertNotContains($foreign, $all->viewData('attentionTickets')->pluck('ticket.id')->all());
        $empty = $this->page(['division_id' => $this->otherDivision->id]);
        $this->assertSame(0, $empty->viewData('dashboardMetrics')['total']);
        $this->assertSame(0, $empty->viewData('olderOpenCount'));
        $empty->assertSee('No open tickets in this selection.');
    }

    public function test_department_heads_cannot_include_foreign_owned_or_assigned_tickets(): void
    {
        $head = User::factory()->create(['department_id' => $this->department->id, 'division_id' => null, 'is_active' => true]);
        $head->assignRole('dept_head');
        $this->ticket(Status::PENDING, '2026-10-01 08:00:00');
        $current = $this->ticket(Status::PENDING, '2026-11-02 08:00:00');
        $this->ticket(Status::PENDING, '2026-10-01 08:00:00', ['user_id' => $head->id, 'assigned_to' => $head->id,
            'department_id' => $this->foreign->id, 'division_id' => $this->foreignDivision->id]);
        $this->actingAs($head, 'web');
        $response = $this->page(['department_id' => $this->foreign->id, 'division_id' => $this->foreignDivision->id]);
        $this->assertSame(1, $response->viewData('dashboardMetrics')['open']);
        $this->assertSame(1, $response->viewData('olderOpenCount'));
        $this->assertSame([$current], $response->viewData('attentionTickets')->pluck('ticket.id')->all());
        $head->update(['department_id' => null]);
        $response = $this->page();
        $this->assertSame(0, $response->viewData('dashboardMetrics')['total']);
        $this->assertSame(0, $response->viewData('olderOpenCount'));
        $this->assertCount(0, $response->viewData('attentionTickets'));
    }

    public function test_issue_subjects_and_delay_ordering_are_correct_and_escaped(): void
    {
        $this->ticket(Status::PENDING, now()->subHours(73), ['is_custom_issue' => true, 'custom_issue' => '<script>late issue</script>']);
        $this->ticket(Status::RESOLVED, now()->subHours(3), ['custom_issue' => 'Quick issue', 'resolved_at' => now()->subHours(2)]);
        $this->ticket(Status::PENDING, now()->subHours(2), ['is_custom_issue' => true, 'custom_issue' => '0']);
        $response = $this->page();
        $issues = $response->viewData('dashboardIssues');
        $this->assertSame('<script>late issue</script>', $issues[0]['subject']);
        $this->assertSame(1, $issues[0]['overdue_open']);
        $this->assertTrue($issues->contains('subject', '0'));
        $response->assertSee('<script>late issue</script>')->assertDontSee('<script>late issue</script>', false);
    }

    public function test_metrics_span_chunks_and_attention_is_bounded_and_ordered_without_per_ticket_queries(): void
    {
        $rows = [];
        for ($i = 0; $i < 501; $i++) $rows[] = ['reference_id' => 'OPS-BULK-' . $i, 'user_id' => $this->admin->id,
            'department_id' => $this->department->id, 'division_id' => $this->division->id,
            'status_id' => Status::idByCode(Status::RESOLVED), 'message' => 'Chunk fixture', 'custom_issue' => 'Chunk fixture',
            'created_at' => '2026-11-01 08:00:00', 'resolved_at' => $i === 500 ? '2026-11-04 09:00:00' : '2026-11-01 08:01:00'];
        foreach (array_chunk($rows, 40) as $chunk) DB::table('tickets')->insert($chunk);
        $priority = Priority::idByCode(Priority::HIGH);
        $issue = Issue::create(['department_id' => $this->department->id, 'division_id' => $this->division->id,
            'priority_id' => $priority, 'issue_description' => 'Operations issue']);
        $ids = [];
        for ($i = 0; $i < 12; $i++) $ids[] = $this->ticket(Status::PENDING, Carbon::parse('2026-11-01')->addHours($i),
            ['issue_id' => $issue->id, 'priority_id' => $priority]);
        $queries = 0;
        DB::listen(function () use (&$queries) { $queries++; });
        $data = DashboardTicketMetrics::summary(Ticket::where('department_id', $this->department->id), now());
        $attention = DashboardTicketMetrics::attention(Ticket::where('department_id', $this->department->id), now());
        $this->assertSame(513, $data['metrics']['total']);
        $this->assertSame(501, $data['metrics']['timed_resolved']);
        $this->assertSame(500, $data['metrics']['on_time_resolved']);
        $this->assertSame(1, $data['metrics']['late_resolved']);
        $this->assertSame('0h 10m', $data['metrics']['avg_resolution_formatted']);
        $this->assertSame(array_slice($ids, 0, 10), $attention->pluck('ticket.id')->all());
        $this->assertSame('Operations issue', $attention[0]['subject']);
        $this->assertSame($this->staff->name, $attention[0]['ticket']->assignee->name);
        $this->assertSame($priority, $attention[0]['ticket']->priority->id);
        $this->assertLessThanOrEqual(8, $queries);
    }
}
