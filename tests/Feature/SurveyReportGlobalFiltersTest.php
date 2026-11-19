<?php

namespace Tests\Feature;

use App\Models\{ArtaSurvey, Department, Division, Issue, User};
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SurveyReportGlobalFiltersTest extends TestCase
{
    private Department $department;
    private Department $foreign;
    private Division $division;
    private Division $other;
    private Division $foreignDivision;
    private User $first;
    private User $second;
    private User $foreignStaff;
    private User $head;
    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-05 12:00:00');
        $this->department = Department::create(['department_name' => 'CSS filter office']);
        $this->foreign = Department::create(['department_name' => 'CSS foreign office']);
        $this->division = Division::create(['department_id' => $this->department->id, 'division_name' => 'CSS filter division']);
        $this->other = Division::create(['department_id' => $this->department->id, 'division_name' => 'CSS other division']);
        $this->foreignDivision = Division::create(['department_id' => $this->foreign->id, 'division_name' => 'CSS foreign division']);
        $this->first = $this->staff($this->division);
        $this->second = $this->staff($this->other);
        $this->foreignStaff = $this->staff($this->foreignDivision);
        $this->head = User::factory()->create(['department_id' => $this->department->id, 'division_id' => null, 'is_active' => true]);
        $this->head->assignRole('dept_head');
        $this->head->givePermissionTo('survey-reports.view');
        $this->actingAs(User::role('admin')->firstOrFail(), 'web');
        $this->survey($this->first, '2026-11-01 00:00:00', 'Very Satisfied');
        $this->survey($this->first, '2026-11-05 23:59:59', 'Very Satisfied');
        $this->survey($this->second, '2026-11-03 08:00:00', 'Satisfied');
        for ($i = 0; $i < 3; $i++) $this->survey($this->foreignStaff, '2026-11-03 08:00:00', 'Dissatisfied');
        $this->survey($this->first, '2026-10-31 23:59:59', 'Satisfied');
        $this->survey($this->first, '2026-02-01 08:00:00', 'Very Dissatisfied');
        foreach ([['2026-11-01 00:00:00', 'Agree'], ['2026-11-05 23:59:59', 'Disagree'],
            ['2026-10-31 23:59:59', 'Strongly Agree'], ['2026-02-01 08:00:00', 'Neither']] as [$date, $rating]) {
            DB::table('arta_surveys')->insert(['created_at' => $date, 'sqd0' => $rating,
                'client_type' => 'Citizen', 'service_availed' => $rating . ' service']);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function staff(Division $division): User
    {
        $staff = User::factory()->create(['name' => 'Same staff display name', 'department_id' => $division->department_id,
            'division_id' => $division->id, 'is_active' => true]);
        $staff->assignRole('hr_staff');
        return $staff;
    }

    private function survey(User $staff, string $date, string $rating): void
    {
        $issue = Issue::create(['department_id' => $staff->department_id, 'division_id' => $staff->division_id,
            'issue_description' => 'CSS service ' . ++$this->sequence]);
        DB::table('surveys')->insert(['user_id' => $staff->id, 'issue_id' => $issue->id, 'created_at' => $date,
            'timeliness_rating' => $rating, 'handling_rating' => $rating, 'quality_rating' => $rating, 'overall_rating' => $rating]);
    }

    private function xpath($response): \DOMXPath
    {
        $document = new \DOMDocument();
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        return new \DOMXPath($document);
    }

    private function assertCss(array $filters, int $total, int $rated): void
    {
        $response = $this->get(route('page.survey_reports.index', $filters))->assertOk();
        $summary = $response->viewData('summaryCards');
        $this->assertSame($total, $summary['total_surveys']);
        $this->assertSame($rated, $summary['staff_rated']);
        $this->assertSame($total * 4, $summary['very_satisfied'] + $summary['satisfied'] + $summary['dissatisfied'] + $summary['very_dissatisfied']);
        $this->assertSame($total, (int) $response->viewData('surveyRatings')->sum('total_surveys'));
        $xpath = $this->xpath($response);
        $charts = $xpath->query('//script[@data-survey-chart-data]');
        $this->assertSame($total * 4, array_sum(json_decode($charts->item(0)->textContent, true)['counts']));
        $this->assertSame($total, array_sum(json_decode($charts->item(1)->textContent, true)['counts']));
        $url = $xpath->query('//table[@id="surveyResponsesTable"]')->item(0)->getAttribute('data-report-ajax');
        $this->getJson($url . '&start=0&length=1')->assertOk()->assertJsonPath('recordsTotal', $total)
            ->assertJsonPath('recordsFiltered', $total)->assertJsonCount($total ? 1 : 0, 'data');
        if ($total > 1) $this->getJson($url . '&start=1&length=100')->assertOk()->assertJsonCount($total - 1, 'data');
        $this->assertSame(6, $xpath->query('//section[contains(@class,"ec-dashboard-stat")]')->length);
        $this->assertSame($response->viewData('exportStartDate'), $xpath->query('//input[@name="start_date"]')->item(0)->getAttribute('value'));
    }

    public function test_css_global_filters_apply_to_cards_charts_staff_details_and_response_pages(): void
    {
        $this->assertCss([], 6, 3);
        $this->assertCss(['period' => 'month', 'department_id' => $this->department->id], 3, 2);
        $this->assertCss(['period' => 'month', 'department_id' => $this->department->id, 'division_id' => $this->division->id], 2, 1);
        $this->assertCss(['period' => 'all', 'department_id' => $this->department->id, 'division_id' => $this->division->id], 4, 1);
        $this->assertCss(['period' => 'custom', 'start' => '2026-11-01', 'end' => '2026-11-01', 'department_id' => $this->department->id], 1, 1);
        $this->assertCss(['period' => 'half', 'half' => 'h1', 'year' => 2026, 'department_id' => $this->department->id], 1, 1);
        $this->assertCss(['period' => 'custom', 'start' => '2000-01-01', 'end' => '2000-01-02'], 0, 0);
        $response = $this->get(route('page.survey_reports.index', ['department_id' => $this->department->id, 'division_id' => $this->division->id]))->assertOk();
        $details = json_decode($this->xpath($response)->query('//button[@data-staff]')->item(0)->getAttribute('data-staff'), true);
        $this->assertSame(2, $details['details']['overall']['very_satisfied']);
        $this->assertSame(2, $details['total_surveys']);
    }

    public function test_css_filters_use_current_rated_staff_office_and_keep_historical_rows(): void
    {
        $this->first->update(['department_id' => $this->foreign->id, 'division_id' => $this->foreignDivision->id]);
        $this->assertCss(['period' => 'all', 'department_id' => $this->department->id], 1, 1);
        $this->assertCss(['period' => 'all', 'department_id' => $this->foreign->id], 7, 2);
    }

    public function test_css_department_heads_are_locked_to_their_department_in_pages_ajax_and_pdf(): void
    {
        $this->actingAs($this->head, 'web');
        $filters = ['period' => 'all', 'department_id' => $this->foreign->id, 'division_id' => $this->foreignDivision->id];
        $this->assertCss($filters, 5, 2);
        $page = $this->get(route('page.survey_reports.index', $filters))->assertOk();
        $page->assertDontSee('id="surveyReportDepartment"', false)->assertDontSee('id="surveyReportDivision"', false);
        Pdf::shouldReceive('loadView')->once()->with('admin.pdf.survey_pdf', \Mockery::on(function ($data) {
            return $data['summaryCards']['total_surveys'] === 5 && $data['surveyRatings']->sum('total_surveys') === 5;
        }))->andReturnSelf();
        Pdf::shouldReceive('setPaper')->once()->andReturnSelf();
        Pdf::shouldReceive('stream')->once()->andReturn(response('pdf'));
        $this->post(route('page.survey_reports.download_pdf'), ['start_date' => '2026-01-01', 'end_date' => '2026-12-31',
            'department_id' => $this->foreign->id, 'division_id' => $this->foreignDivision->id])->assertOk();
        $this->head->update(['department_id' => null]);
        $this->assertCss(['period' => 'all', 'department_id' => $this->foreign->id], 0, 0);
    }

    public function test_invalid_office_and_date_parameters_are_rejected_or_show_the_date_fallback(): void
    {
        foreach (['page.survey_reports.index', 'page.survey_reports.responses'] as $route) {
            $this->getJson(route($route, ['department_id' => $this->department->id, 'division_id' => $this->foreignDivision->id]))
                ->assertUnprocessable()->assertJsonValidationErrors('division_id');
            $this->getJson(route($route, ['department_id' => 999999]))->assertUnprocessable()->assertJsonValidationErrors('department_id');
        }
        foreach (['page.survey_reports.index', 'page.arta_survey_reports.index', 'page.survey_reports.responses', 'page.arta_survey_reports.responses'] as $route) {
            $this->getJson(route($route, ['period' => ['all']]))->assertUnprocessable()->assertJsonValidationErrors('period');
        }
        $response = $this->get(route('page.survey_reports.index', ['period' => 'custom', 'start' => '2026-11-05', 'end' => '2026-11-01']))->assertOk();
        $this->assertSame('month', $response->viewData('activePeriod'));
        $response->assertSee('Showing this month instead.');
    }

    public function test_arta_date_filter_matches_summary_charts_table_and_ignores_office_parameters(): void
    {
        foreach ([['month', null, null, 2], ['all', null, null, 4], ['custom', '2026-11-05', '2026-11-05', 1],
            ['half', null, null, 1], ['custom', '2000-01-01', '2000-01-02', 0]] as [$period, $start, $end, $total]) {
            $response = $this->get(route('page.arta_survey_reports.index', ['period' => $period, 'start' => $start,
                'end' => $end, 'half' => 'h1', 'year' => 2026, 'department_id' => 999999, 'division_id' => ['ignored']]))->assertOk();
            $this->assertSame($total, $response->viewData('artaSummary')['total_responses']);
            $xpath = $this->xpath($response);
            foreach ($xpath->query('//script[@data-survey-chart-data]') as $chart) {
                $this->assertSame($total, array_sum(json_decode($chart->textContent, true)['counts']));
            }
            $url = $xpath->query('//table[@id="surveyResponsesTable"]')->item(0)->getAttribute('data-report-ajax');
            $this->getJson($url . '&start=0&length=100')->assertOk()->assertJsonPath('recordsTotal', $total)->assertJsonCount($total, 'data');
            $this->assertSame(0, $xpath->query('//select[@name="department_id" or @name="division_id"]')->length);
            $this->assertSame(1, $xpath->query('//select[@name="period"]')->length);
        }
    }

    public function test_css_admin_pdf_receives_the_selected_office_and_dates(): void
    {
        Pdf::shouldReceive('loadView')->once()->with('admin.pdf.survey_pdf', \Mockery::on(function ($data) {
            return $data['summaryCards']['total_surveys'] === 2 && $data['summaryCards']['very_satisfied'] === 8
                && $data['surveyRatings']->count() === 1 && $data['surveyRatings'][0]->id === $this->first->id;
        }))->andReturnSelf();
        Pdf::shouldReceive('setPaper')->once()->andReturnSelf();
        Pdf::shouldReceive('stream')->once()->andReturn(response('pdf'));
        $this->post(route('page.survey_reports.download_pdf'), ['start_date' => '2026-11-01', 'end_date' => '2026-11-05',
            'department_id' => $this->department->id, 'division_id' => $this->division->id])->assertOk();
    }

    public function test_css_controls_assets_and_export_defaults_reflect_the_selected_filters(): void
    {
        $response = $this->get(route('page.survey_reports.index', ['period' => 'all', 'department_id' => $this->department->id,
            'division_id' => $this->division->id]))->assertOk();
        $response->assertSee('analytics.css', false)->assertSee('survey-report-filters.js', false);
        $xpath = $this->xpath($response);
        $this->assertSame('all', $xpath->query('//select[@name="period"]/option[@selected]')->item(0)->getAttribute('value'));
        $this->assertSame((string) $this->department->id, $xpath->query('//select[@name="department_id"]/option[@selected]')->item(0)->getAttribute('value'));
        $this->assertSame((string) $this->division->id, $xpath->query('//select[@name="division_id"]/option[@selected]')->item(0)->getAttribute('value'));
        $this->assertSame('2026-02-01', $response->viewData('exportStartDate'));
        $this->assertSame((string) $this->department->id, $xpath->query('//input[@type="hidden" and @name="department_id"]')->item(0)->getAttribute('value'));
        $this->getJson(route('page.survey_reports.responses', ['period' => 'month', 'department_id' => $this->department->id,
            'search' => ['value' => '"CSS foreign division"']]))->assertOk()->assertJsonPath('recordsTotal', 3)->assertJsonPath('recordsFiltered', 0);
    }
}
