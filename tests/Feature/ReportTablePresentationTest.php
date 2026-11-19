<?php

namespace Tests\Feature;

use App\Models\{Department, Division, Issue, Status, Ticket, User};
use App\Services\{TicketReportQueries, TicketReportWidgets};
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReportTablePresentationTest extends TestCase
{
    private Department $department;
    private Division $division;
    private Issue $issue;
    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-04 12:00:00');
        $this->department = Department::create(['department_name' => 'Table presentation office']);
        $this->division = Division::create(['department_id' => $this->department->id, 'division_name' => 'Table presentation team']);
        $this->issue = Issue::create(['department_id' => $this->department->id, 'division_id' => $this->division->id,
            'issue_description' => 'Standard subject']);
        $this->staff = User::factory()->create(['department_id' => $this->department->id, 'division_id' => $this->division->id, 'is_active' => true]);
        $this->staff->assignRole('hr_staff');
        $this->actingAs(User::role('admin')->firstOrFail(), 'web');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function ticket(array $extra = []): Ticket
    {
        $id = DB::table('tickets')->insertGetId($extra + ['reference_id' => 'TABLE-' . Ticket::count(),
            'user_id' => backpack_user()->id, 'department_id' => $this->department->id, 'division_id' => $this->division->id,
            'issue_id' => $this->issue->id, 'assigned_to' => $this->staff->id, 'status_id' => Status::idByCode(Status::PENDING),
            'message' => 'Preview of this ticket', 'created_at' => now()->subHour()]);
        return Ticket::findOrFail($id);
    }

    private function page(string $route = 'page.reports.index')
    {
        return $this->get(route($route, ['period' => 'all', 'department_id' => $this->department->id]))->assertOk();
    }

    private function xpath(string $html): \DOMXPath
    {
        $document = new \DOMDocument();
        $document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        return new \DOMXPath($document);
    }

    public function test_reports_show_the_correct_subject_link_preview_and_sortable_date(): void
    {
        $standard = $this->ticket(['custom_issue' => 'Stale custom field']);
        $custom = $this->ticket(['is_custom_issue' => true, 'custom_issue' => '<img src=x onerror=alert(1)> Custom subject',
            'message' => '<script>alert(1)</script> ' . str_repeat('Detailed message ', 20) . 'SEARCH-END']);
        $legacy = $this->ticket(['issue_id' => null, 'custom_issue' => 'Legacy custom subject']);
        foreach (['page.reports.index'] as $route) {
            $response = $this->page($route);
            $xpath = $this->xpath($response->getContent());
            $headers = $xpath->query('//table[@id="latestTicketsTable"]/thead/tr/th');
            $this->assertSame(['Details', 'Reference', 'Created by', 'Subject', 'Status', 'Priority', 'Created'],
                array_map(fn ($node) => trim($node->textContent), iterator_to_array($headers)));
            foreach ([$standard->id => 'Standard subject', $custom->id => $custom->custom_issue, $legacy->id => 'Legacy custom subject'] as $id => $subject) {
                $link = $xpath->query('//table[@id="latestTicketsTable"]//a[@href="' . route('ticket.show', $id) . '"]')->item(0);
                $this->assertNotNull($link);
                $cells = $link->parentNode->parentNode->getElementsByTagName('td');
                $this->assertStringContainsString($subject, $cells->item(3)->textContent);
                $this->assertSame(7, $cells->length);
                $this->assertSame((string) Ticket::findOrFail($id)->created_at->timestamp, $cells->item(6)->getAttribute('data-order'));
                if ($id === $standard->id) $this->assertStringNotContainsString('Stale custom field', $cells->item(3)->textContent);
                if ($id === $custom->id) {
                    $this->assertStringContainsString('SEARCH-END', $cells->item(3)->getAttribute('data-search'));
                    $this->assertStringNotContainsString('SEARCH-END', $cells->item(3)->textContent);
                    $this->assertSame(0, $cells->item(3)->getElementsByTagName('script')->length);
                    $this->assertSame(0, $cells->item(3)->getElementsByTagName('img')->length);
                }
            }
        }
        $overview = $this->page()->viewData('ticketOverview');
        $this->assertTrue($overview->contains('issue_description', $custom->custom_issue));
        $this->assertSame(3, (int) $overview->sum('total_tickets'));
    }

    public function test_reports_load_table_assets_after_datatables_on_a_fresh_request(): void
    {
        $response = $this->page();
        $response->assertSee('report-tables.js', false)->assertSee('dataTables.responsive.min.js', false)->assertSee('report-tables.css', false);
        $this->assertLessThan(strpos($response->getContent(), 'report-tables.js'), strpos($response->getContent(), 'dataTables.bootstrap5.min.js'));
        $this->assertSame(3, $this->xpath($response->getContent())->query('//table[@data-report-table]')->length);
    }

    public function test_a_zero_subject_is_displayed_as_data_rather_than_a_missing_value(): void
    {
        $ticket = $this->ticket(['is_custom_issue' => true, 'custom_issue' => '0']);
        $xpath = $this->xpath($this->page()->getContent());
        $subject = $xpath->query('//table[@id="latestTicketsTable"]//td[contains(@class, "ec-ticket-subject")]/div[contains(@class, "ec-ticket-subject-label")]')->item(0);
        $this->assertSame('0', trim($subject->textContent));
        $this->assertTrue($this->page()->viewData('ticketOverview')->contains('issue_description', '0'));
    }

    public function test_no_measured_resolutions_show_a_dash_even_with_stale_timestamps(): void
    {
        $this->ticket(['resolved_at' => now()]);
        $this->ticket(['status_id' => Status::idByCode(Status::RESOLVED), 'resolved_by' => $this->staff->id]);
        foreach (['backpack.dashboard' => 'dashKpis', 'page.reports.index' => 'reportKpis'] as $route => $key) {
            $response = $this->page($route);
            $this->assertSame('—', $response->viewData($key)['avg_resolution_formatted']);
            $this->assertSame(0, $response->viewData($key)['avg_resolution_minutes']);
            $response->assertSee('No resolved tickets with a recorded resolution time');
        }
        $report = $this->page();
        $this->assertSame('—', $report->viewData('ticketOverview')->first()->formatted_time);
        $this->assertSame('—', $report->viewData('divisions')->first()->problemCategories->first()->average_resolve_time);
        $staff = $report->viewData('users')->firstWhere('id', $this->staff->id);
        $this->assertNull($staff->avg_resolution_minutes);
        $this->assertSame('—', $staff->avg_resolution_formatted);
    }

    public function test_genuine_zero_minute_resolutions_remain_zero_in_every_average(): void
    {
        $this->ticket(['status_id' => Status::idByCode(Status::RESOLVED), 'resolved_by' => $this->staff->id,
            'created_at' => now()->subHour(), 'resolved_at' => now()->subHour()]);
        $response = $this->page();
        $this->assertSame('0h 00m', $response->viewData('reportKpis')['avg_resolution_formatted']);
        $this->assertSame('0h 00m', $response->viewData('ticketOverview')->first()->formatted_time);
        $this->assertSame('0h 00m', $response->viewData('users')->firstWhere('id', $this->staff->id)->avg_resolution_formatted);
        $this->assertSame('0h 00m', $this->page('backpack.dashboard')->viewData('dashKpis')['avg_resolution_formatted']);
    }

    public function test_averages_use_resolved_samples_and_preserve_counts(): void
    {
        $this->ticket(['status_id' => Status::idByCode(Status::RESOLVED), 'resolved_by' => $this->staff->id, 'resolved_at' => now()]);
        $this->ticket(['status_id' => Status::idByCode(Status::REOPENED), 'resolved_by' => $this->staff->id, 'resolved_at' => now()->addHours(5)]);
        $report = $this->page();
        $this->assertSame(2, $report->viewData('reportKpis')['total']);
        $this->assertSame(1, $report->viewData('reportKpis')['resolved']);
        $this->assertSame('1h 00m', $report->viewData('reportKpis')['avg_resolution_formatted']);
        $this->assertSame('1h 00m', $report->viewData('ticketOverview')->first()->formatted_time);
        $this->assertSame('1h 00m', $report->viewData('users')->firstWhere('id', $this->staff->id)->avg_resolution_formatted);
    }

    public function test_badges_reuse_configured_colors_and_escape_names(): void
    {
        $status = Status::where('status_code', Status::PENDING)->firstOrFail();
        $status->update(['status_name' => '<b>Waiting</b>', 'status_color' => '#123456']);
        $this->ticket();
        $xpath = $this->xpath($this->page()->getContent());
        $badge = $xpath->query('//table[@id="latestTicketsTable"]//span[contains(@class, "ec-record-badge")]')->item(0);
        $this->assertSame('<b>Waiting</b>', trim($badge->textContent));
        $this->assertStringContainsString('#123456', $badge->getAttribute('style'));
        $this->assertStringContainsString('#ffffff', $badge->getAttribute('style'));
        $this->assertSame(0, $badge->getElementsByTagName('b')->length);
    }

    public function test_empty_and_multi_chunk_resolution_samples_have_matching_displays(): void
    {
        $query = Ticket::where('department_id', $this->department->id);
        $start = now()->subDay(); $end = now();
        $this->assertSame('—', TicketReportQueries::widgets($query, $start, $end)['kpis']['avg_resolution_formatted']);
        $rows = [];
        for ($i = 0; $i < 502; $i++) $rows[] = ['reference_id' => 'TABLE-CHUNK-' . $i, 'user_id' => backpack_user()->id,
            'department_id' => $this->department->id, 'status_id' => Status::idByCode($i === 501 ? Status::RESOLVED : Status::PENDING),
            'message' => 'Chunk sample', 'created_at' => now()->subHour(), 'resolved_at' => $i === 501 ? now()->subHour() : null];
        foreach (array_chunk($rows, 40) as $chunk) DB::table('tickets')->insert($chunk);
        $kpi = TicketReportQueries::widgets($query, $start, $end)['kpis'];
        $this->assertSame(502, $kpi['total']);
        $this->assertSame('0h 00m', $kpi['avg_resolution_formatted']);
        $this->assertSame(TicketReportWidgets::buildKpiWidget($query->get(), Status::idByCode(Status::RESOLVED),
            Status::idByCode(Status::PENDING), Status::idByCode(Status::REOPENED)), $kpi);
    }
}
