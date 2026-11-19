<?php

namespace Tests\Feature;

use App\Models\{Department, Division, Status, Ticket, User};
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReportOfficeFiltersTest extends TestCase
{
    private Department $department;
    private Department $foreign;
    private Division $division;
    private Division $otherDivision;
    private Division $foreignDivision;
    private User $head;
    private array $ids;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-04 12:00:00');
        $this->department = Department::create(['department_name' => 'Analytics actual office']);
        $this->foreign = Department::create(['department_name' => 'Analytics foreign office']);
        $this->division = Division::create(['department_id' => $this->department->id, 'division_name' => 'Analytics actual team']);
        $this->otherDivision = Division::create(['department_id' => $this->department->id, 'division_name' => 'Analytics other team']);
        $this->foreignDivision = Division::create(['department_id' => $this->foreign->id, 'division_name' => 'Analytics foreign team']);
        $this->head = User::factory()->create(['department_id' => $this->department->id, 'division_id' => null, 'is_active' => true]);
        $this->head->assignRole('dept_head');
        $this->head->givePermissionTo('reports.view');
        $employee = User::role('employee')->firstOrFail();
        $this->ids = [];
        foreach ([[$this->division, $employee, null, '2026-11-03 08:00:00'],
            [$this->otherDivision, $employee, null, '2026-11-03 09:00:00'],
            [$this->foreignDivision, $this->head, null, '2026-11-03 10:00:00'],
            [$this->foreignDivision, $employee, $this->head->id, '2026-11-03 11:00:00'],
            [$this->division, $employee, null, '2020-01-01 00:00:00']] as $i => [$division, $owner, $assignee, $created]) {
            $this->ids[] = DB::table('tickets')->insertGetId(['reference_id' => 'OFFICE-FILTER-' . $i,
                'user_id' => $owner->id, 'assigned_to' => $assignee, 'department_id' => $division->department_id,
                'division_id' => $division->id, 'custom_issue' => 'Office category ' . $i, 'message' => 'Analytics fixture',
                'status_id' => Status::idByCode(Status::PENDING), 'created_at' => $created]);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createVisitorFixtures(): void
    {
        DB::table('users')->update(['last_login_at' => null]);
        foreach ([[$this->division, now()], [$this->otherDivision, now()->startOfDay()],
            [$this->foreignDivision, now()], [$this->division, now()->subDay()], [$this->division, now()->addHour()]] as [$division, $login]) {
            User::factory()->create(['department_id' => $division->department_id, 'division_id' => $division->id,
                'last_login_at' => $login, 'is_active' => true]);
        }
    }

    public function test_visitors_count_distinct_accounts_signed_in_today_in_the_selected_office(): void
    {
        $this->createVisitorFixtures();
        $this->actingAs(User::role('admin')->firstOrFail(), 'web');
        $this->assertSame(3, $this->get(route('page.reports.index'))->assertOk()->viewData('reportVisitors'));
        $this->assertSame(2, $this->get(route('page.reports.index', ['department_id' => $this->department->id]))->assertOk()->viewData('reportVisitors'));
        $this->assertSame(1, $this->get(route('page.reports.index', ['department_id' => $this->department->id,
            'division_id' => $this->division->id, 'period' => 'custom', 'start' => '2020-01-01', 'end' => '2020-01-02']))->assertOk()->viewData('reportVisitors'));
    }

    public function test_department_head_visitors_count_cannot_include_foreign_users(): void
    {
        $this->createVisitorFixtures();
        $this->actingAs($this->head, 'web');
        $this->assertSame(2, $this->get(route('page.reports.index', ['department_id' => $this->foreign->id]))->assertOk()->viewData('reportVisitors'));
        $this->head->update(['department_id' => null]);
        $this->assertSame(0, $this->get(route('page.reports.index'))->assertOk()->viewData('reportVisitors'));
    }

    public function test_tickets_overview_uses_saved_status_names_and_preserves_the_filtered_total(): void
    {
        $admin = User::role('admin')->firstOrFail();
        $this->actingAs($admin, 'web');
        $custom = Status::where('status_code', Status::REOPENED)->firstOrFail();
        $custom->update(['status_name' => 'Awaiting follow-up', 'status_color' => '#123abc']);
        DB::table('tickets')->insert(['reference_id' => 'CUSTOM-OVERVIEW', 'user_id' => $admin->id,
            'department_id' => $this->department->id, 'division_id' => $this->division->id,
            'status_id' => $custom->id, 'message' => 'Custom status fixture', 'created_at' => now()]);
        $response = $this->get(route('page.reports.index', ['department_id' => $this->department->id]))->assertOk();
        $overview = $response->viewData('reportTicketsOverview');
        $index = array_search('Awaiting follow-up', $overview['labels'], true);
        $this->assertNotFalse($index);
        $this->assertSame('#123abc', $overview['colors'][$index]);
        $this->assertSame(1, $overview['counts'][$index]);
        $this->assertSame($response->viewData('reportKpis')['total'], array_sum($overview['counts']));
    }

    private function assertPages(array $filter, array $expected, bool $admin): void
    {
        foreach (['backpack.dashboard' => 'dashKpis', 'page.reports.index' => 'reportKpis'] as $route => $key) {
            $response = $this->get(route($route, $filter))->assertOk();
            $this->assertSame(count($expected), $response->viewData($key)['total']);
            $this->assertEqualsCanonicalizing($expected, $response->viewData('latestTickets')->pluck('id')->all());
            $this->assertSame($admin, $response->viewData('officeFiltersEnabled'));
            if ($admin) $response->assertSee('id="reportDepartment"', false)->assertSee('id="reportDivision"', false);
            else $response->assertDontSee('id="reportDepartment"', false)->assertDontSee('id="reportDivision"', false);
            if ($route === 'page.reports.index') {
                $this->assertSame(count($expected), (int) $response->viewData('ticketOverview')->sum('total_tickets'));
                $this->assertSame(count($expected), (int) $response->viewData('divisions')->sum('tickets_count'));
                $document = new \DOMDocument();
                $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
                $xpath = new \DOMXPath($document);
                $chartData = json_decode($xpath->query('//script[@data-report-charts]')->item(0)->textContent, true, 512, JSON_THROW_ON_ERROR);
                $this->assertSame($response->viewData('reportVolumeTrend')['labels'], $chartData['volume']['labels']);
                $this->assertSame($response->viewData('reportVolumeTrend')['counts'], $chartData['volume']['counts']);
                $this->assertSame($response->viewData('reportTicketsOverview'), $chartData['status']);
                $this->assertSame(count($expected), array_sum($chartData['status']['counts']));
                $metric = $xpath->query('//*[@data-average-resolution-card]')->item(0);
                $this->assertNotNull($metric);
                $this->assertSame($response->viewData('reportKpis')['avg_resolution_formatted'],
                    trim($xpath->query('.//*[@class="ec-reassignment-rate"]', $metric)->item(0)->textContent));
                $this->assertSame((string) count($expected), trim($xpath->query('.//dl/div[2]/dd', $metric)->item(0)->textContent));
            } else {
                $this->assertSame(count($expected), (int) $response->viewData('statusData')->sum());
                $chartTickets = Ticket::visibleTo(backpack_user())->where('department_id', $response->viewData('divisionChartDepartmentId'))
                    ->when(($filter['period'] ?? '') !== 'all', fn ($query) => $query->whereBetween('created_at',
                        [$response->viewData('dashWidgetStart'), $response->viewData('dashWidgetEnd')]))->count();
                $this->assertSame($chartTickets, (int) array_sum($response->viewData('divisionCounts')));
                $this->assertSame(count($expected), array_sum($response->viewData('volumeTrendCounts')));
            }
        }
    }

    public function test_admin_can_filter_both_pages_by_department_division_and_date(): void
    {
        $this->actingAs(User::role('admin')->firstOrFail(), 'web');
        $this->assertPages(['period' => 'all', 'department_id' => $this->department->id], [$this->ids[0], $this->ids[1], $this->ids[4]], true);
        $this->assertPages(['period' => 'all', 'department_id' => $this->department->id, 'division_id' => $this->otherDivision->id], [$this->ids[1]], true);
        $this->assertPages(['period' => 'all', 'division_id' => $this->foreignDivision->id], [$this->ids[2], $this->ids[3]], true);
        $this->assertPages(['period' => 'custom', 'start' => '2026-11-01', 'end' => '2026-11-04',
            'department_id' => $this->department->id], [$this->ids[0], $this->ids[1]], true);
    }

    public function test_department_head_is_strictly_scoped_even_for_owned_and_assigned_foreign_tickets(): void
    {
        $this->actingAs($this->head, 'web');
        $this->assertPages(['period' => 'all'], [$this->ids[0], $this->ids[1], $this->ids[4]], false);
        $this->assertPages(['period' => 'all', 'department_id' => $this->foreign->id, 'division_id' => $this->foreignDivision->id],
            [$this->ids[0], $this->ids[1], $this->ids[4]], false);
        $this->assertPages(['period' => 'custom', 'start' => '2026-11-01', 'end' => '2026-11-04',
            'department_id' => ['invalid'], 'division_id' => 'bad'], [$this->ids[0], $this->ids[1]], false);
    }

    public function test_head_without_department_sees_no_data(): void
    {
        $head = User::factory()->create(['department_id' => null, 'division_id' => null, 'is_active' => true]);
        $head->assignRole('dept_head');
        $head->givePermissionTo('reports.view');
        $this->actingAs($head, 'web');
        $this->assertPages(['period' => 'all', 'department_id' => $this->department->id], [], false);
    }

    public function test_admin_rejects_invalid_or_mismatched_office_filters(): void
    {
        $this->actingAs(User::role('admin')->firstOrFail(), 'web');
        foreach (['backpack.dashboard', 'page.reports.index'] as $route) {
            $this->getJson(route($route, ['department_id' => $this->department->id, 'division_id' => $this->foreignDivision->id]))
                ->assertUnprocessable()->assertJsonValidationErrors('division_id');
            $this->getJson(route($route, ['department_id' => 999999]))->assertUnprocessable()->assertJsonValidationErrors('department_id');
            $this->getJson(route($route, ['department_id' => ['bad'], 'division_id' => $this->division->id]))
                ->assertUnprocessable()->assertJsonValidationErrors('department_id');
            $this->getJson(route($route, ['department_id' => 'bad', 'division_id' => $this->division->id]))
                ->assertUnprocessable()->assertJsonValidationErrors('department_id');
        }
    }

    public function test_staff_counts_and_averages_use_only_tickets_in_the_selected_office(): void
    {
        $staff = User::factory()->create(['department_id' => $this->department->id, 'division_id' => $this->division->id, 'is_active' => true]);
        $staff->assignRole('hr_staff');
        foreach ([0 => '2026-11-03 09:00:00', 2 => '2026-11-03 13:00:00'] as $index => $resolvedAt) {
            DB::table('tickets')->where('id', $this->ids[$index])->update(['assigned_to' => $staff->id,
                'resolved_by' => $staff->id, 'resolved_at' => $resolvedAt, 'status_id' => Status::idByCode(Status::RESOLVED)]);
        }
        foreach ([$this->head, User::role('admin')->firstOrFail()] as $actor) {
            $this->flushSession();
            $response = $this->actingAs($actor, 'web')->get(route('page.reports.index', ['period' => 'all',
                'department_id' => $this->department->id, 'division_id' => $this->division->id]))->assertOk();
            $row = $response->viewData('users')->firstWhere('id', $staff->id);
            $this->assertNotNull($row);
            $this->assertEquals(1, $row->resolved_tickets_count);
            $this->assertEquals(1, $row->assigned_count);
            $this->assertSame(60, $row->avg_resolution_minutes);
        }
    }

    public function test_department_level_tickets_remain_counted_in_division_charts_and_pdf_data(): void
    {
        $department = Department::create(['department_name' => 'No division analytics']);
        $id = DB::table('tickets')->insertGetId(['reference_id' => 'NO-DIVISION-REPORT', 'user_id' => $this->head->id,
            'department_id' => $department->id, 'status_id' => Status::idByCode(Status::PENDING),
            'message' => 'Department only fixture', 'custom_issue' => 'Department-only request', 'created_at' => now()]);
        $this->actingAs(User::role('admin')->firstOrFail(), 'web');
        $this->assertPages(['period' => 'all', 'department_id' => $department->id], [$id], true);
        $data = $this->get(route('page.reports.index', ['period' => 'all', 'department_id' => $department->id]))->viewData('divisions');
        $this->assertSame('Department-level (no division)', $data->first()->division_name);
        $this->assertSame('Department-only request', $data->first()->problemCategories->first()->category_name);
    }

    public function test_pdf_uses_the_same_strict_head_scope_and_admin_selection(): void
    {
        foreach ([[$this->head, [$this->ids[0], $this->ids[1]]],
            [User::role('admin')->firstOrFail(), [$this->ids[2], $this->ids[3]]]] as [$actor, $expected]) {
            $this->flushSession();
            $pdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
            Pdf::swap($pdf);
            $pdf->shouldReceive('loadView')->once()->withArgs(function ($view, $data) use ($expected) {
                $this->assertSame('admin.pdf.reports_pdf', $view);
                $this->assertEqualsCanonicalizing($expected, $data['latestTickets']->pluck('id')->all());
                $this->assertSame(2, $data['reportKpis']['total']);
                $this->assertSame(2, (int) $data['ticketOverview']->sum('total_tickets'));
                return true;
            })->andReturn($pdf);
            $pdf->shouldReceive('setPaper')->once()->with('a4', 'portrait')->andReturnSelf();
            $pdf->shouldReceive('stream')->once()->andReturn(response('pdf fixture'));
            $this->actingAs($actor, 'web')->post(route('page.reports.download_pdf'), ['start_date' => '2026-11-01', 'end_date' => '2026-11-04',
                'department_id' => $this->foreign->id, 'division_id' => $this->foreignDivision->id, 'include_zero_activity' => 1])->assertOk();
        }
    }
}
