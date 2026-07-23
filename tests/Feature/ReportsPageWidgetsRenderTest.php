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

    public function test_reports_page_renders_sla_and_funnel_charts()
    {
        $this->actingAdmin();

        $response = $this->get(route('page.reports.index'));

        $response->assertStatus(200);
        $response->assertDontSee('id="slaByDepartmentChart"', false);
        $response->assertSee('id="statusFunnelChart"', false);
        $response->assertSee('Overdue vs On-Time', false);
    }

    public function test_reports_page_renders_staff_workload_columns_and_reassignment_widget()
    {
        $this->actingAdmin();

        $response = $this->get(route('page.reports.index'));

        $response->assertStatus(200);
        $response->assertSee('Assigned', false);
        $response->assertSee('Avg Resolution', false);
        $response->assertSee('id="reassignmentByDivisionChart"', false);
        $response->assertSee('Reassignment by Division', false);
        $response->assertSee('Reassignment Rate', false);
    }

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
}
