<?php

namespace Tests\Feature;

use App\Models\{Department, Division, Status, User};
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IndependentDivisionChartsTest extends TestCase
{
    private Department $a;
    private Department $b;
    private Department $empty;
    private Division $a1;
    private Division $a2;
    private Division $b1;
    private User $head;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-05 12:00:00');
        $this->a = Department::create(['department_name' => 'Chart department A']);
        $this->b = Department::create(['department_name' => 'Chart department B']);
        $this->empty = Department::create(['department_name' => 'Chart empty department']);
        $this->a1 = Division::create(['department_id' => $this->a->id,
            'division_name' => 'Division with a very long descriptive name & <script>name only</script>']);
        $this->a2 = Division::create(['department_id' => $this->a->id, 'division_name' => 'Shared division name']);
        Division::create(['department_id' => $this->a->id, 'division_name' => 'Zero activity division']);
        $this->b1 = Division::create(['department_id' => $this->b->id, 'division_name' => 'Shared division name']);
        Division::create(['department_id' => $this->b->id, 'division_name' => 'Other zero activity division']);
        $admin = User::role('admin')->firstOrFail();
        $admin->update(['department_id' => $this->a->id, 'division_id' => null]);
        $this->actingAs($admin, 'web');
        $this->head = User::factory()->create(['department_id' => $this->a->id, 'division_id' => null, 'is_active' => true]);
        $this->head->assignRole('dept_head');
        $this->head->givePermissionTo('reports.view');
        $this->ticket($this->a, $this->a1, true);
        $this->ticket($this->a, $this->a1);
        $this->ticket($this->a, $this->a1, true, '2020-01-01 08:00:00');
        $this->ticket($this->a, $this->a2);
        $this->ticket($this->a, null, true);
        for ($i = 0; $i < 4; $i++) $this->ticket($this->b, $this->b1, $i < 2, null, $this->head->id);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function ticket(Department $department, ?Division $division, bool $reassigned = false, ?string $created = null, ?int $owner = null): void
    {
        $id = DB::table('tickets')->insertGetId(['reference_id' => 'LOCAL-CHART-' . DB::table('tickets')->count(),
            'user_id' => $owner ?? backpack_user()->id, 'department_id' => $department->id, 'division_id' => $division?->id,
            'status_id' => Status::idByCode(Status::PENDING), 'message' => 'Local chart fixture',
            'custom_issue' => 'Local chart request', 'created_at' => $created ?? '2026-11-04 08:00:00']);
        if ($reassigned) {
            foreach (['pending', 'approved'] as $status) DB::table('ticket_reassignment_requests')->insert([
                'ticket_id' => $id, 'to_department_id' => $department->id, 'to_division_id' => $division?->id,
                'requested_by' => backpack_user()->id, 'status' => $status]);
        }
    }

    private function payload($response): array
    {
        $document = new \DOMDocument();
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        $xpath = new \DOMXPath($document);
        $card = '//div[@data-division-chart-card="divisionChart"]';
        $nodes = $xpath->query($card . '//script[@data-division-chart-data]');
        $this->assertSame(1, $nodes->length);
        $this->assertSame($response->viewData('divisionChartFiltersEnabled') ? 1 : 0,
            $xpath->query($card . '//select[@data-division-chart-department]')->length);
        return json_decode($nodes->item(0)->textContent, true, 512, JSON_THROW_ON_ERROR);
    }

    private function assertRateCard($response, float $rate, int $reassigned, int $total): void
    {
        $document = new \DOMDocument();
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        $xpath = new \DOMXPath($document);
        $cards = $xpath->query('//*[@data-reassignment-card]');
        if ($xpath->query('//*[@data-dashboard-summary]')->length) {
            // The operational dashboard replaced the standalone card; reports retain it.
            $this->assertSame(0, $cards->length);
            $this->assertEquals($rate, $response->viewData('reassignmentRate'));
            $this->assertSame($reassigned, $response->viewData('reassignedCount'));
            $this->assertSame($total, $response->viewData('dashboardMetrics')['total']);
            $this->assertSame((string) $total, trim($xpath->query('//*[@data-dashboard-summary]//section[@aria-label="Total tickets"]//*[@class="ec-dashboard-stat-value"]')->item(0)->textContent));
            $response->assertSee('data-dashboard-attention', false)->assertDontSee('latestTicketsTable', false);
            return;
        }
        $this->assertSame(1, $cards->length);
        $card = $cards->item(0);
        $this->assertSame($rate . '%', trim($xpath->query('.//*[@class="ec-reassignment-rate"]', $card)->item(0)->textContent));
        $this->assertSame((string) $reassigned, trim($xpath->query('.//dl/div[1]/dd', $card)->item(0)->textContent));
        $this->assertSame((string) $total, trim($xpath->query('.//dl/div[2]/dd', $card)->item(0)->textContent));
        $this->assertSame(0, $xpath->query('.//select | .//canvas', $card)->length);
        $response->assertDontSee('Reassignment by Division')->assertDontSee('data-reassignment-period', false);
        if (!$total) $this->assertStringContainsString('No tickets in this selection.', $card->textContent);
    }

    public function test_dashboard_division_chart_ignores_global_office_filters_and_keeps_zero_activity_divisions(): void
    {
        $response = $this->get(route('backpack.dashboard', ['period' => 'month', 'department_id' => $this->b->id,
            'division_id' => $this->b1->id, 'chart_department_id' => $this->a->id]))->assertOk();
        $this->assertSame(4, $response->viewData('dashKpis')['total']);
        $this->assertSame($this->a->id, $response->viewData('divisionChartDepartmentId'));
        $data = $this->payload($response);
        $values = array_combine($data['labels'], $data['values']);
        $this->assertSame(2, $values[$this->a1->division_name]);
        $this->assertSame(1, $values[$this->a2->division_name]);
        $this->assertSame(0, $values['Zero activity division']);
        $this->assertSame(1, $values['Department-level (no division)']);
        $this->assertNotContains('Other zero activity division', $data['labels']);
        $response->assertSee('dashboard-charts.css', false)->assertSee('reassignment-card.css', false);
        $this->assertRateCard($response, 50, 2, 4);
    }

    public function test_changing_local_dashboard_department_preserves_global_metrics_and_card(): void
    {
        $global = ['period' => 'month', 'department_id' => $this->a->id, 'division_id' => $this->a2->id];
        $first = $this->get(route('backpack.dashboard', $global + ['chart_department_id' => $this->a->id]))->assertOk();
        $second = $this->get(route('backpack.dashboard', $global + ['chart_department_id' => $this->b->id]))->assertOk();
        $this->assertSame($first->viewData('dashKpis'), $second->viewData('dashKpis'));
        $this->assertSame($first->viewData('latestTickets')->pluck('id')->all(), $second->viewData('latestTickets')->pluck('id')->all());
        $data = $this->payload($second);
        $values = array_combine($data['labels'], $data['values']);
        $this->assertSame(4, $values[$this->b1->division_name]);
        $this->assertRateCard($first, 0, 0, 1);
        $this->assertRateCard($second, 0, 0, 1);
    }

    public function test_dates_still_filter_dashboard_division_chart_and_all_time_includes_older_history(): void
    {
        foreach (['month' => 2, 'all' => 3] as $period => $total) {
            $response = $this->get(route('backpack.dashboard', ['period' => $period,
                'department_id' => $this->b->id, 'chart_department_id' => $this->a->id]))->assertOk();
            $data = $this->payload($response);
            $index = array_search($this->a1->division_name, $data['labels'], true);
            $this->assertSame($total, $data['values'][$index]);
        }
    }

    public function test_dashboard_default_is_one_department_and_invalid_local_filters_are_rejected(): void
    {
        $response = $this->get(route('backpack.dashboard', ['period' => 'month', 'department_id' => $this->b->id]))->assertOk();
        $this->assertSame($this->a->id, $response->viewData('divisionChartDepartmentId'));
        foreach ([999999, 'bad', ['bad']] as $invalid) $this->getJson(route('backpack.dashboard', ['chart_department_id' => $invalid]))
            ->assertUnprocessable()->assertJsonValidationErrors('chart_department_id');
    }

    public function test_department_heads_cannot_override_dashboard_chart_scope_even_with_foreign_owned_tickets(): void
    {
        $this->actingAs($this->head, 'web');
        $response = $this->get(route('backpack.dashboard', ['period' => 'month', 'department_id' => $this->b->id,
            'division_id' => $this->b1->id, 'chart_department_id' => ['bad']]))->assertOk();
        $this->assertFalse($response->viewData('divisionChartFiltersEnabled'));
        $this->assertSame($this->a->id, $response->viewData('divisionChartDepartmentId'));
        $this->assertCount(4, $this->payload($response)['labels']);
        $this->assertRateCard($response, 50, 2, 4);
    }

    public function test_empty_departments_and_many_divisions_have_scrollable_dashboard_chart_data(): void
    {
        $response = $this->get(route('backpack.dashboard', ['chart_department_id' => $this->empty->id]))->assertOk();
        $this->assertSame([], $this->payload($response)['labels']);
        for ($i = 0; $i < 30; $i++) Division::create(['department_id' => $this->empty->id, 'division_name' => 'Future division ' . $i]);
        $response = $this->get(route('backpack.dashboard', ['chart_department_id' => $this->empty->id]))->assertOk();
        $this->assertCount(30, $this->payload($response)['labels']);
        $response->assertSee('height: 1130px', false)->assertSee('max-height: 360px', false);
    }

    public function test_department_heads_without_a_department_cannot_use_owned_foreign_tickets_to_widen_data(): void
    {
        $head = User::factory()->create(['department_id' => null, 'division_id' => null, 'is_active' => true]);
        $head->assignRole('dept_head');
        $head->givePermissionTo('reports.view');
        $this->ticket($this->b, $this->b1, true, null, $head->id);
        $this->actingAs($head, 'web');
        foreach (['backpack.dashboard', 'page.reports.index'] as $route) {
            $response = $this->get(route($route, ['chart_department_id' => $this->b->id]))->assertOk();
            if ($route === 'backpack.dashboard') {
                $this->assertNull($response->viewData('divisionChartDepartmentId'));
                $this->assertSame([], $this->payload($response)['labels']);
            }
            $this->assertRateCard($response, 0, 0, 0);
        }
    }

    public function test_admin_without_department_defaults_dashboard_chart_to_hrdo(): void
    {
        $admin = User::factory()->create(['department_id' => null, 'division_id' => null, 'is_active' => true]);
        $admin->assignRole('admin');
        $this->actingAs($admin, 'web');
        $response = $this->get(route('backpack.dashboard', ['department_id' => $this->b->id]))->assertOk();
        $this->assertSame(Department::idByCode(Department::HRDO), $response->viewData('divisionChartDepartmentId'));
        $this->assertSame(0, array_sum($this->payload($response)['values']));
    }

    public function test_reassignment_cards_follow_global_filters_and_ignore_retired_local_filters(): void
    {
        foreach (['backpack.dashboard', 'page.reports.index'] as $route) {
            foreach ([['month', $this->a->id, null, 50, 2, 4], ['all', $this->a->id, null, 60, 3, 5],
                ['month', $this->a->id, $this->a1->id, 50, 1, 2], ['all', $this->a->id, $this->a1->id, 66.7, 2, 3],
                ['month', $this->a->id, $this->a2->id, 0, 0, 1], ['month', $this->b->id, $this->b1->id, 50, 2, 4],
                ['month', $this->empty->id, null, 0, 0, 0]] as [$period, $department, $division, $rate, $reassigned, $total]) {
                $response = $this->get(route($route, ['period' => $period, 'department_id' => $department,
                    'division_id' => $division, 'chart_department_id' => $this->empty->id,
                    'reassignment_department_id' => 999999, 'reassignment_period' => 'all']))->assertOk();
                $this->assertRateCard($response, $rate, $reassigned, $total);
                if ($route === 'page.reports.index') $response->assertDontSee('data-division-chart-department', false);
            }
        }
    }

    public function test_reassignment_card_keeps_department_head_scope_and_global_dates(): void
    {
        $this->actingAs($this->head, 'web');
        foreach (['backpack.dashboard', 'page.reports.index'] as $route) {
            foreach (['month' => [50, 2, 4], 'all' => [60, 3, 5]] as $period => [$rate, $reassigned, $total]) {
                $response = $this->get(route($route, ['period' => $period, 'department_id' => $this->b->id,
                    'division_id' => $this->b1->id, 'chart_department_id' => $this->b->id,
                    'reassignment_period' => 'all']))->assertOk();
                $this->assertRateCard($response, $rate, $reassigned, $total);
                $response->assertDontSee('id="reportDepartment"', false)
                    ->assertDontSee('aria-label="Department for Tickets per Division"', false);
            }
        }
    }
}
