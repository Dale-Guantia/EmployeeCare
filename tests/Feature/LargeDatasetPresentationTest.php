<?php

namespace Tests\Feature;

use App\Models\{Issue, User};
use Carbon\Carbon;
use Database\Seeders\DashboardDemoSeeder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LargeDatasetPresentationTest extends TestCase
{
    private function assertInsetTableScroller(string $html, string $label, int $columns): void
    {
        $document = new \DOMDocument();
        $document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($document);
        $scrollers = $xpath->query('//div[@role="region" and @aria-label="' . $label . '"]');
        $this->assertSame(1, $scrollers->length);
        $scroller = $scrollers->item(0);
        $this->assertStringNotContainsString('card-body', $scroller->getAttribute('class'));
        $this->assertStringContainsString('card-body', $scroller->parentNode->getAttribute('class'));
        $this->assertSame('0', $scroller->getAttribute('tabindex'));
        $this->assertSame($columns, $xpath->query('./table/thead/tr/th', $scroller)->length);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_dashboard_and_all_report_pages_keep_full_totals_and_bounded_response_pages(): void
    {
        Carbon::setTestNow('2026-11-24 12:00:00');
        $this->seed(DashboardDemoSeeder::class);
        $this->actingAs(User::role('admin')->firstOrFail(), 'web');
        $staff = User::role('hr_staff')->firstOrFail();
        $issue = Issue::where('division_id', $staff->division_id)->firstOrFail();
        for ($i = 0; $i < 65; $i++) {
            DB::table('surveys')->insert([
                'user_id' => $staff->id, 'issue_id' => $issue->id,
                'timeliness_rating' => 'Very Satisfied', 'handling_rating' => 'Satisfied',
                'quality_rating' => 'Satisfied', 'overall_rating' => 'Very Satisfied',
                'created_at' => now()->subMinutes($i),
            ]);
            DB::table('arta_surveys')->insert([
                'client_type' => 'Client ' . $i . ' ' . str_repeat('Long label ', 15),
                'service_availed' => 'Service ' . $i, 'sqd0' => 'Agree',
                'created_at' => now()->subMinutes($i),
            ]);
        }

        $dashboard = $this->get(route('backpack.dashboard', ['period' => 'all']))->assertOk();
        $this->assertSame(240, $dashboard->viewData('dashboardMetrics')['total']);
        $this->assertCount(10, $dashboard->viewData('attentionTickets'));
        $this->assertCount(10, $dashboard->viewData('staffLeaderboard'));
        $dashboard->assertSee('aria-label="Issue types and delays"', false);
        $this->assertInsetTableScroller($dashboard->getContent(), 'Open ticket worklist', 6);
        $this->assertInsetTableScroller($dashboard->getContent(), 'Issue types and delays', 4);

        $report = $this->get(route('page.reports.index', ['period' => 'all']))->assertOk();
        $this->assertSame(240, $report->viewData('reportKpis')['total']);
        $this->assertCount(10, $report->viewData('latestTickets'));
        $report->assertSee('aria-label="SLA by issue type"', false);
        $this->assertInsetTableScroller($report->getContent(), 'SLA by issue type', 4);

        $css = $this->get(route('page.survey_reports.index', ['period' => 'all']))->assertOk();
        $this->assertSame(65, $css->viewData('summaryCards')['total_surveys']);
        $this->assertCount(10, $css->viewData('responses'));
        $arta = $this->get(route('page.arta_survey_reports.index', ['period' => 'all']))->assertOk();
        $this->assertSame(65, $arta->viewData('artaSummary')['total_responses']);
        $this->assertCount(10, $arta->viewData('responses'));
        $this->assertCount(65, $arta->viewData('artaClients'));

        foreach (['page.survey_reports.responses', 'page.arta_survey_reports.responses'] as $route) {
            $this->getJson(route($route, ['period' => 'all', 'start' => 60, 'length' => 10]))
                ->assertOk()->assertJsonPath('recordsTotal', 65)->assertJsonCount(5, 'data');
        }
    }
}
