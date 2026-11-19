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
        $response->assertDontSee('id="resolutionDistributionChart"', false);
        $response->assertSee('Average Resolution Time')->assertSee('data-average-resolution-card', false);
        $response->assertSee('analytics.css', false)->assertSee('analytics-charts.js', false);
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
        $response->assertSee('id="statusChart"', false)->assertSee('Tickets Overview');
        $response->assertDontSee('id="statusFunnelChart"', false)->assertDontSee('Status Funnel');
        $response->assertSee('SLA by Issue Type', false);
    }

    public function test_reports_page_renders_staff_workload_columns_and_reassignment_widget()
    {
        $this->actingAdmin();

        $response = $this->get(route('page.reports.index'));

        $response->assertStatus(200);
        $response->assertSee('Assigned', false);
        $response->assertSee('Avg Resolution', false);
        $response->assertDontSee('id="reassignmentByDivisionChart"', false);
        $response->assertDontSee('Reassignment by Division', false);
        $response->assertSee('Reassignment Rate', false);
        $response->assertSee('data-reassignment-card', false);
        $response->assertSee('reassignment-card.css', false);
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
        $response->assertSee('id="kpiVisitors"', false)->assertDontSee('id="kpiAvgResolution"', false);
        $response->assertSee('id="kpiOverdue"', false);
        $response->assertSee('id="kpiReopenRate"', false);
        $response->assertDontSee('Total Tickets (All-Time)', false);
        $response->assertDontSee('Total Tickets (Period)', false);
        $response->assertDontSee('>Users<', false);
        $document = new \DOMDocument();
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        $xpath = new \DOMXPath($document);
        $cards = $xpath->query('//div[contains(@class,"reports-kpis")]/section');
        $this->assertSame(6, $cards->length);
        $this->assertSame('Visitors', $cards->item(0)->getAttribute('aria-label'));
        $this->assertSame('Total Tickets', $cards->item(1)->getAttribute('aria-label'));
        $this->assertSame(1, $xpath->query('//h2[text()="Average Resolution Time"]')->length);
        $this->assertSame(1, $xpath->query('//*[@data-average-resolution-card and contains(@class,"ec-resolution-card-green")]')->length);
    }
}
