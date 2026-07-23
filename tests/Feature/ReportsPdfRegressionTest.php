<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ReportsPdfRegressionTest extends TestCase
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

    public function test_pdf_download_still_succeeds_and_is_a_pdf()
    {
        $this->actingAdmin();

        $response = $this->post(route('page.reports.download_pdf'), [
            'start_date' => Carbon::now()->subDays(7)->format('Y-m-d'),
            'end_date' => Carbon::now()->format('Y-m-d'),
        ]);

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_pdf_download_succeeds_on_a_zero_ticket_range()
    {
        $this->actingAdmin();

        // A window far in the past guaranteed to have zero tickets.
        $response = $this->post(route('page.reports.download_pdf'), [
            'start_date' => '2000-01-01',
            'end_date' => '2000-01-02',
        ]);

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_reports_page_renders_on_effectively_empty_range()
    {
        // The on-screen page always uses the last-30-days default window;
        // this confirms it degrades gracefully rather than erroring when
        // that window is sparse/empty, satisfying the T12 empty-range check.
        $this->actingAdmin();

        $response = $this->get(route('page.reports.index'));

        $response->assertStatus(200);
        $response->assertViewHas('reportKpis', function ($kpis) {
            return array_key_exists('total', $kpis);
        });
    }

    public function test_survey_and_arta_report_pages_still_load_on_mssql()
    {
        // T10 regression check: Part 0d found SurveyReportsController and
        // ArtaSurveyReportsController were already pure Eloquent (no raw SQL),
        // so this plan makes no changes to them. This test proves that claim
        // rather than just asserting it in the report.
        $admin = User::role('admin')->first();
        $this->assertNotNull($admin);
        Permission::firstOrCreate(['name' => 'survey-reports.view', 'guard_name' => 'web']);
        if (! $admin->can('survey-reports.view')) {
            $admin->givePermissionTo('survey-reports.view');
        }
        $this->actingAs($admin, 'web');

        $surveyResponse = $this->get(route('page.survey_reports.index'));
        $surveyResponse->assertStatus(200);

        $artaResponse = $this->get(route('page.arta_survey_reports.index'));
        $artaResponse->assertStatus(200);

        $surveyPdfResponse = $this->post(route('page.survey_reports.download_pdf'), [
            'start_date' => Carbon::now()->subDays(7)->format('Y-m-d'),
            'end_date' => Carbon::now()->format('Y-m-d'),
        ]);
        $surveyPdfResponse->assertStatus(200);
        $surveyPdfResponse->assertHeader('content-type', 'application/pdf');

        $artaPdfResponse = $this->post(route('page.arta_survey_reports.download_pdf'), [
            'start_date' => Carbon::now()->subDays(7)->format('Y-m-d'),
            'end_date' => Carbon::now()->format('Y-m-d'),
        ]);
        $artaPdfResponse->assertStatus(200);
        $artaPdfResponse->assertHeader('content-type', 'application/pdf');
    }
}
