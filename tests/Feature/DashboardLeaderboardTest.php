<?php

namespace Tests\Feature;

use App\Models\{Department, Division, Status, Ticket, User};
use App\Services\TicketReportQueries;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardLeaderboardTest extends TestCase
{
    private Department $department;
    private Department $foreign;
    private Division $division;
    private Division $otherDivision;
    private Division $foreignDivision;
    private User $admin;
    private User $head;
    private User $first;
    private User $second;
    private User $third;
    private int $reference = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-05 12:00:00');
        $this->department = Department::create(['department_name' => 'Leaderboard actual department']);
        $this->foreign = Department::create(['department_name' => 'Leaderboard foreign department']);
        $this->division = Division::create(['department_id' => $this->department->id, 'division_name' => 'Leaderboard division']);
        $this->otherDivision = Division::create(['department_id' => $this->department->id, 'division_name' => 'Leaderboard other division']);
        $this->foreignDivision = Division::create(['department_id' => $this->foreign->id, 'division_name' => 'Leaderboard foreign division']);
        $this->admin = User::role('admin')->firstOrFail();
        $this->admin->update(['department_id' => $this->department->id, 'division_id' => null]);
        $this->actingAs($this->admin, 'web');
        $this->head = User::factory()->create(['department_id' => $this->department->id, 'division_id' => null, 'is_active' => true]);
        $this->head->assignRole('dept_head');
        $this->first = User::factory()->create(['name' => 'Alex <script>staff</script>', 'department_id' => $this->department->id, 'division_id' => null]);
        $this->second = User::factory()->create(['name' => 'Shared staff name', 'department_id' => $this->foreign->id, 'division_id' => null]);
        $this->third = User::factory()->create(['name' => 'Shared staff name', 'department_id' => $this->department->id, 'division_id' => null]);
        foreach ([60, 120, 4380] as $minutes) $this->ticket($this->first, $minutes, $this->division);
        foreach ([30, 30, 30] as $minutes) $this->ticket($this->second, $minutes, $this->division);
        foreach ([180, 180, 4380] as $minutes) $this->ticket($this->third, $minutes, $this->otherDivision);
        $this->ticket($this->first, 60, $this->division, '2020-01-01 08:00:00');
        $this->ticket($this->first, 10, $this->foreignDivision);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function ticket(?User $resolver, ?int $minutes, Division $division, string $created = '2026-11-01 08:00:00', string $status = Status::RESOLVED): void
    {
        DB::table('tickets')->insert(['reference_id' => 'LEADERBOARD-' . ++$this->reference,
            'user_id' => $division->department_id === $this->foreign->id ? $this->head->id : $this->admin->id,
            'assigned_to' => $this->admin->id, 'resolved_by' => $resolver?->id,
            'department_id' => $division->department_id, 'division_id' => $division->id,
            'status_id' => Status::idByCode($status), 'custom_issue' => 'Leaderboard fixture', 'message' => 'Leaderboard fixture',
            'created_at' => $created, 'resolved_at' => $minutes === null ? null : Carbon::parse($created)->addMinutes($minutes)]);
    }

    private function dashboard(array $filters = [])
    {
        return $this->get(route('backpack.dashboard', $filters + ['period' => 'month', 'department_id' => $this->department->id]))->assertOk();
    }

    private function assertAverageCard($response, string $average, int $resolved, int $total): void
    {
        $document = new \DOMDocument();
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        $xpath = new \DOMXPath($document);
        $cards = $xpath->query('//*[@data-dashboard-summary]//section[@aria-label="Average Resolution Time"]');
        $this->assertSame(1, $cards->length);
        $card = $cards->item(0);
        $this->assertSame($average, trim($xpath->query('.//*[@class="ec-dashboard-stat-value"]', $card)->item(0)->textContent));
        $this->assertSame($resolved, $response->viewData('dashboardMetrics')['resolved']);
        $this->assertSame((string) $total, trim($xpath->query('//*[@data-dashboard-summary]//section[@aria-label="Total tickets"]//*[@class="ec-dashboard-stat-value"]')->item(0)->textContent));
        $this->assertSame(0, $xpath->query('.//canvas | .//select', $card)->length);
        $response->assertDontSee('Resolution Time Distribution')->assertDontSee('dashResolutionDistributionChart', false);
    }

    public function test_average_card_follows_global_filters_and_uses_the_same_average_as_the_kpi(): void
    {
        foreach ([['month', null, '17h 23m', 9], ['month', $this->division->id, '12h 55m', 6],
            ['month', $this->otherDivision->id, '26h 20m', 3], ['all', null, '15h 45m', 10]] as [$period, $division, $average, $total]) {
            $response = $this->dashboard(['period' => $period, 'division_id' => $division,
                'chart_department_id' => $this->foreign->id]);
            $this->assertAverageCard($response, $average, $total, $total);
            $this->assertSame($average, $response->viewData('dashKpis')['avg_resolution_formatted']);
        }
        $this->assertAverageCard($this->dashboard(['department_id' => $this->foreign->id,
            'division_id' => $this->foreignDivision->id]), '0h 10m', 1, 1);
        $this->assertAverageCard($this->dashboard(['period' => 'custom', 'start' => '2020-01-01', 'end' => '2020-01-01']), '1h 00m', 1, 1);
    }

    public function test_average_card_keeps_department_head_scope_locked(): void
    {
        $this->actingAs($this->head, 'web');
        $this->assertAverageCard($this->dashboard(['department_id' => $this->foreign->id,
            'division_id' => $this->foreignDivision->id]), '17h 23m', 9, 9);
    }

    public function test_average_card_distinguishes_missing_times_from_real_zero_minutes(): void
    {
        $division = Division::create(['department_id' => $this->department->id, 'division_name' => 'Average card empty division']);
        $filter = ['division_id' => $division->id];
        $this->assertAverageCard($this->dashboard($filter), '—', 0, 0);
        $this->ticket($this->first, null, $division);
        $this->assertAverageCard($this->dashboard($filter), '—', 1, 1);
        $this->ticket($this->first, 0, $division);
        $this->assertAverageCard($this->dashboard($filter), '0h 00m', 2, 2);
        $this->ticket($this->first, 60, $division);
        $this->ticket($this->first, 600, $division, status: Status::PENDING);
        $this->assertAverageCard($this->dashboard($filter), '0h 30m', 3, 4);
    }

    public function test_leaderboard_uses_resolver_ids_complete_resolutions_and_deterministic_ranking(): void
    {
        $this->ticket($this->first, 10, $this->division, status: Status::PENDING);
        $this->ticket($this->first, 10, $this->division, status: Status::REOPENED);
        $this->ticket($this->first, null, $this->division);
        $this->ticket(null, 10, $this->division);
        Status::where('status_code', Status::RESOLVED)->update(['status_name' => 'Completed']);
        $response = $this->dashboard();
        $rows = $response->viewData('staffLeaderboard');
        $this->assertSame([$this->second->id, $this->first->id, $this->third->id], $rows->pluck('user_id')->all());
        $this->assertSame([3, 3, 3], $rows->pluck('resolved_count')->all());
        $this->assertSame([3, 2, 2], $rows->pluck('on_time_count')->all());
        $this->assertSame([0, 1, 1], $rows->pluck('overdue_count')->all());
        $this->assertSame([30, 1520, 1580], $rows->pluck('avg_resolution_minutes')->all());
        $this->assertSame('25h 20m', $rows[1]['avg_resolution_formatted']);
        $response->assertSee($this->first->name)->assertDontSee($this->first->name, false);
        $this->assertNotContains($this->admin->id, $rows->pluck('user_id')->all());
    }

    public function test_leaderboard_follows_global_department_division_and_creation_dates(): void
    {
        $division = $this->dashboard(['division_id' => $this->division->id])->viewData('staffLeaderboard');
        $this->assertSame([$this->second->id, $this->first->id], $division->pluck('user_id')->all());
        $other = $this->dashboard(['division_id' => $this->otherDivision->id])->viewData('staffLeaderboard');
        $this->assertSame([$this->third->id], $other->pluck('user_id')->all());
        $foreign = $this->dashboard(['department_id' => $this->foreign->id, 'division_id' => $this->foreignDivision->id])->viewData('staffLeaderboard');
        $this->assertSame([$this->first->id], $foreign->pluck('user_id')->all());
        $this->assertSame(1, $foreign[0]['resolved_count']);
        $all = $this->dashboard(['period' => 'all'])->viewData('staffLeaderboard');
        $this->assertSame($this->first->id, $all[0]['user_id']);
        $this->assertSame(4, $all[0]['resolved_count']);
        $old = $this->dashboard(['period' => 'custom', 'start' => '2020-01-01', 'end' => '2020-01-01'])->viewData('staffLeaderboard');
        $this->assertSame(1, $old[0]['resolved_count']);
        $this->assertSame(60, $old[0]['avg_resolution_minutes']);
        $baseline = $this->dashboard()->viewData('staffLeaderboard');
        $local = $this->dashboard(['chart_department_id' => $this->foreign->id])->viewData('staffLeaderboard');
        $this->assertEquals($baseline->all(), $local->all());
    }

    public function test_department_heads_cannot_widen_leaderboard_with_foreign_owned_tickets(): void
    {
        $this->actingAs($this->head, 'web');
        $rows = $this->dashboard(['department_id' => $this->foreign->id, 'division_id' => $this->foreignDivision->id])->viewData('staffLeaderboard');
        $this->assertSame(9, $rows->sum('resolved_count'));
        $this->assertSame(3, $rows->firstWhere('user_id', $this->first->id)['resolved_count']);
        $this->head->update(['department_id' => null]);
        $this->assertCount(0, $this->dashboard()->viewData('staffLeaderboard'));
    }

    public function test_zero_time_and_sla_boundary_match_existing_reports_and_empty_selection_has_no_rankings(): void
    {
        $empty = Division::create(['department_id' => $this->department->id, 'division_name' => 'Leaderboard boundary division']);
        $response = $this->dashboard(['division_id' => $empty->id]);
        $this->assertCount(0, $response->viewData('staffLeaderboard'));
        $response->assertSee('No resolved tickets yet');
        foreach ([0, 4320, 4380] as $minutes) $this->ticket($this->first, $minutes, $empty);
        $rows = $this->dashboard(['division_id' => $empty->id])->viewData('staffLeaderboard');
        $this->assertSame(2, $rows[0]['on_time_count']);
        $this->assertSame(1, $rows[0]['overdue_count']);
        $this->assertSame(2900, $rows[0]['avg_resolution_minutes']);
    }

    public function test_staff_leaderboard_uses_only_tickets_the_viewer_can_access(): void
    {
        $staff = User::factory()->create(['department_id' => $this->department->id,
            'division_id' => $this->division->id, 'is_active' => true]);
        $staff->assignRole('hr_staff');
        DB::table('tickets')->where('resolved_by', $this->first->id)->where('division_id', $this->division->id)
            ->update(['assigned_to' => $staff->id]);
        $this->actingAs($staff, 'web');
        $rows = $this->dashboard()->viewData('staffLeaderboard');
        $this->assertSame([$this->first->id], $rows->pluck('user_id')->all());
        $this->assertSame(3, $rows[0]['resolved_count']);
    }

    public function test_leaderboard_aggregates_across_chunks_and_limits_to_ten_without_per_staff_queries(): void
    {
        $division = Division::create(['department_id' => $this->department->id, 'division_name' => 'Leaderboard chunk division']);
        for ($i = 0; $i < 501; $i++) $this->ticket($this->first, 1, $division);
        for ($i = 0; $i < 12; $i++) $this->ticket(User::factory()->create(), 2, $division);
        $queries = 0;
        DB::listen(function () use (&$queries) { $queries++; });
        $rows = TicketReportQueries::staffLeaderboard(Ticket::where('division_id', $division->id));
        $this->assertCount(10, $rows);
        $this->assertSame($this->first->id, $rows[0]['user_id']);
        $this->assertSame(501, $rows[0]['resolved_count']);
        $this->assertSame('0h 01m', $rows[0]['avg_resolution_formatted']);
        $this->assertLessThanOrEqual(4, $queries);
    }

    public function test_dashboard_places_leaderboard_in_overview_and_average_in_operational_summary(): void
    {
        $response = $this->dashboard();
        $document = new \DOMDocument();
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        $xpath = new \DOMXPath($document);
        $top = '//div[@class="ec-dashboard-overview-grid"]';
        $this->assertSame(3, $xpath->query($top . '/div')->length);
        $this->assertSame(0, $xpath->query($top . '//canvas[@id="dashVolumeTrendChart"]')->length);
        $this->assertSame(1, $xpath->query($top . '/div[1]//canvas[@id="divisionChart"]')->length);
        $this->assertSame(1, $xpath->query($top . '/div[3]//*[@data-staff-leaderboard]')->length);
        $this->assertSame(0, $xpath->query($top . '//canvas[@id="dashResolutionDistributionChart"]')->length);
        $this->assertSame(0, $xpath->query('//canvas[@id="dashResolutionDistributionChart"]')->length);
        $this->assertSame(6, $xpath->query('//*[@data-dashboard-summary]/section')->length);
        $this->assertSame(1, $xpath->query('//*[@data-dashboard-summary]//section[@aria-label="Average Resolution Time"]')->length);
        $this->assertSame(0, $xpath->query('//*[@data-average-resolution-card or @data-reassignment-card]')->length);
        $actions = '//div[contains(@class,"ec-dashboard-action-row")]';
        $this->assertSame(1, $xpath->query($actions . '/div[1]//*[@data-dashboard-attention]')->length);
        $this->assertSame(1, $xpath->query($actions . '/div[2]//canvas[@id="dashVolumeTrendChart"]')->length);
        $this->assertSame(1, $xpath->query('//canvas[@id="divisionChart"]')->length);
        $this->assertSame(1, $xpath->query('//canvas[@id="dashVolumeTrendChart"]')->length);
        $payload = json_decode($xpath->query('//script[@data-dashboard-charts]')->item(0)->textContent, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($response->viewData('volumeTrendLabels'), $payload['volume']['labels']);
        $this->assertSame($response->viewData('volumeTrendCounts'), $payload['volume']['counts']);
        $this->assertSame($response->viewData('statusLabels')->all(), $payload['status']['labels']);
        $this->assertSame($response->viewData('statusData')->all(), $payload['status']['counts']);
        $this->assertSame($response->viewData('statusColors')->all(), $payload['status']['colors']);
        $slaCells = $xpath->query('//*[@data-dashboard-sla]//tbody/tr[1]/td');
        $this->assertSame(['Leaderboard fixture9 tickets', '0', '2', '77.8%'],
            array_map(fn ($node) => trim($node->textContent), iterator_to_array($slaCells)));
    }
}
