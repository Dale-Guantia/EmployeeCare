<?php

namespace Tests\Feature;

use App\Models\{ArtaSurvey, Issue, Survey, User};
use App\Services\SurveyReportQueries;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SurveyReportPresentationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-05 12:00:00');
        $this->actingAs(User::role('admin')->firstOrFail(), 'web');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function xpath(string $html): \DOMXPath
    {
        $document = new \DOMDocument();
        $document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        return new \DOMXPath($document);
    }

    private function charts(\DOMXPath $xpath): array
    {
        return array_map(fn ($node) => json_decode($node->textContent, true, 512, JSON_THROW_ON_ERROR),
            iterator_to_array($xpath->query('//script[@data-survey-chart-data]')));
    }

    private function assertStructure($response, int $responseColumns, int $orderColumn): \DOMXPath
    {
        $response->assertOk()->assertSee('report-tables.js', false)->assertSee('report-tables.css', false)
            ->assertSee('survey-report-charts.js', false)->assertSee('dataTables.responsive.min.js', false);
        $xpath = $this->xpath($response->getContent());
        $this->assertSame(6, $xpath->query('//div[@data-report-kpi]')->length);
        $this->assertSame(2, $xpath->query('//div[@data-survey-chart]')->length);
        $this->assertSame($responseColumns, $xpath->query('//table[@id="surveyResponsesTable"]/thead/tr/th')->length);
        $table = $xpath->query('//table[@id="surveyResponsesTable"]')->item(0);
        $this->assertSame((string) $orderColumn, $table->getAttribute('data-report-order-column'));
        $this->assertStringContainsString('/responses', $table->getAttribute('data-report-ajax'));
        $this->assertSame(0, $xpath->query('//table[contains(@class,"table-striped")]')->length);
        $this->assertSame(0, $xpath->query('//div[contains(@class,"reports-kpis")]//div[contains(@class,"h1") and contains(@class,"text-muted")]')->length);
        $this->assertCount(2, $this->charts($xpath));
        return $xpath;
    }

    public function test_css_cards_charts_response_columns_and_staff_details_preserve_the_four_dimensions(): void
    {
        $staff = User::role('hr_staff')->firstOrFail();
        $staff->update(['name' => "O'Neil <script>unsafe</script>"]);
        $issue = Issue::firstOrFail();
        for ($i = 0; $i < 13; $i++) DB::table('surveys')->insert(['user_id' => $staff->id, 'issue_id' => $issue->id,
            'timeliness_rating' => 'Very Satisfied', 'handling_rating' => 'Satisfied', 'quality_rating' => 'Dissatisfied',
            'overall_rating' => 'Very Dissatisfied', 'created_at' => now()->subMinutes($i)]);
        $response = $this->get(route('page.survey_reports.index'));
        $xpath = $this->assertStructure($response, 9, 8);
        $summary = $response->viewData('summaryCards');
        $this->assertSame(13, $summary['total_surveys']);
        foreach (['very_satisfied', 'satisfied', 'dissatisfied', 'very_dissatisfied'] as $key) $this->assertSame(13, $summary[$key]);
        $this->assertSame(1, $summary['staff_rated']);
        foreach ($summary as $key => $count) {
            $node = $xpath->query('//div[@data-report-kpi="' . $key . '"]')->item(0);
            $this->assertSame((string) $count, trim($node->textContent));
        }
        $this->assertSame([13, 13, 13, 13], $this->charts($xpath)[0]['counts']);
        $this->assertSame('Rating answers', $this->charts($xpath)[0]['unit']);
        $this->assertSame([13], $this->charts($xpath)[1]['counts']);
        $this->assertSame(10, $xpath->query('//table[@id="surveyResponsesTable"]/tbody/tr')->length);
        $details = $xpath->query('//button[@data-staff]')->item(0);
        $data = json_decode($details->getAttribute('data-staff'), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($staff->name, $data['name']);
        $this->assertSame(13, $data['details']['quality']['dissatisfied']);
        $this->assertSame('#surveyDetailsModal', $details->getAttribute('data-bs-target'));
        $this->assertSame(0, $xpath->query('//table//script')->length);
        $page = $this->getJson(route('page.survey_reports.responses', ['start' => 10, 'length' => 10,
            'order' => [['column' => 7, 'dir' => 'desc']]]))->assertOk()->assertJsonPath('recordsTotal', 13)->assertJsonCount(3, 'data');
        $this->assertCount(8, $page->json('data.0'));
        $this->getJson(route('page.survey_reports.responses', ['search' => ['value' => "O'Neil"]]))->assertJsonPath('recordsFiltered', 13);
    }

    public function test_arta_totals_cover_all_responses_and_use_only_sqd0_for_satisfaction(): void
    {
        $choices = ['Strongly Agree', 'Agree', 'Neither', 'Disagree', 'Strongly Disagree', 'N/A', null];
        for ($i = 0; $i < 27; $i++) ArtaSurvey::create(['client_type' => $i % 2 ? 'Citizen' : 'Business',
            'service_availed' => sprintf('Service %02d', $i), 'sqd0' => $choices[$i % 7], 'sqd1' => 'Strongly Disagree',
            'created_at' => now()->subMinutes($i)]);
        $response = $this->get(route('page.arta_survey_reports.index'));
        $xpath = $this->assertStructure($response, 16, 1);
        $summary = $response->viewData('artaSummary');
        $this->assertSame(['total_responses' => 27, 'strongly_agree' => 4, 'agree' => 4, 'neither' => 4, 'disagree' => 4,
            'strongly_disagree' => 4, 'not_applicable' => 4, 'unanswered_or_other' => 3], $summary);
        $charts = $this->charts($xpath);
        $this->assertSame(27, array_sum($charts[0]['counts']));
        $this->assertSame(27, array_sum($charts[1]['counts']));
        foreach (['total_responses', 'strongly_agree', 'agree', 'neither', 'disagree', 'strongly_disagree'] as $key) {
            $this->assertSame((string) $summary[$key], trim($xpath->query('//div[@data-report-kpi="' . $key . '"]')->item(0)->textContent));
        }
        $this->assertSame(10, $xpath->query('//table[@id="surveyResponsesTable"]/tbody/tr')->length);
        $this->getJson(route('page.arta_survey_reports.responses', ['start' => 10, 'length' => 10,
            'order' => [['column' => 2, 'dir' => 'asc']]]))->assertOk()->assertJsonPath('recordsTotal', 27)
            ->assertJsonCount(10, 'data')->assertJsonPath('data.0.2', 'Service 10');
        $this->getJson(route('page.arta_survey_reports.responses', ['search' => ['value' => '"Service 26"']]))
            ->assertOk()->assertJsonPath('recordsFiltered', 1)->assertJsonPath('data.0.2', 'Service 26');
    }

    #[DataProvider('pages')]
    public function test_empty_pages_keep_zero_cards_and_valid_chart_data(string $route, int $columns, int $order): void
    {
        $response = $this->get(route($route));
        $xpath = $this->assertStructure($response, $columns, $order);
        foreach ($xpath->query('//div[@data-report-kpi]') as $node) $this->assertSame('0', trim($node->textContent));
        foreach ($this->charts($xpath) as $chart) $this->assertSame(0, array_sum($chart['counts']));
    }

    public static function pages(): array
    {
        return [['page.survey_reports.index', 9, 8], ['page.arta_survey_reports.index', 16, 1]];
    }

    public function test_arta_missing_and_unrecognized_answers_are_not_treated_as_agreement(): void
    {
        foreach (['Agree', 'agree', 'Agree ', null, 'N/A', 'Unexpected'] as $i => $choice) ArtaSurvey::create([
            'sqd0' => $choice, 'client_type' => $i % 2 ? null : '']);
        $data = SurveyReportQueries::artaSummary();
        $this->assertSame(6, $data['artaSummary']['total_responses']);
        $this->assertSame(1, $data['artaSummary']['agree']);
        $this->assertSame(1, $data['artaSummary']['not_applicable']);
        $this->assertSame(4, $data['artaSummary']['unanswered_or_other']);
        $this->assertCount(1, $data['artaClients']);
        $this->assertSame('Not specified', $data['artaClients']->first()->client_type);
        $this->assertSame(6, $data['artaClients']->first()->total_responses);
    }

    public function test_arta_summary_date_bounds_exclude_outside_responses(): void
    {
        DB::table('arta_surveys')->insert(['sqd0' => 'Agree', 'client_type' => 'Citizen', 'created_at' => now()->subYear()]);
        DB::table('arta_surveys')->insert(['sqd0' => 'Disagree', 'client_type' => 'Business', 'created_at' => now()]);
        $data = SurveyReportQueries::artaSummary(now()->startOfDay(), now()->endOfDay());
        $this->assertSame(1, $data['artaSummary']['total_responses']);
        $this->assertSame(0, $data['artaSummary']['agree']);
        $this->assertSame(1, $data['artaSummary']['disagree']);
        $this->assertSame('Business', $data['artaClients']->first()->client_type);
    }

    public function test_survey_page_and_ajax_permissions_are_unchanged(): void
    {
        $this->actingAs(User::role('employee')->firstOrFail(), 'web');
        foreach (['page.survey_reports.index', 'page.survey_reports.responses', 'page.arta_survey_reports.index', 'page.arta_survey_reports.responses'] as $route) {
            $this->getJson(route($route))->assertForbidden();
        }
    }
}
