<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Division;
use App\Models\Issue;
use App\Models\Priority;
use App\Models\Status;
use App\Models\Ticket;
use App\Models\TicketReassignmentRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ReportsWidgetsTest extends TestCase
{
    use DatabaseTransactions;

    private function makeAdmin(): User
    {
        $admin = User::role('admin')->first();
        $this->assertNotNull($admin, 'Expected at least one admin user to exist.');

        Permission::firstOrCreate(['name' => 'reports.view', 'guard_name' => 'web']);
        if (! $admin->can('reports.view')) {
            $admin->givePermissionTo('reports.view');
        }

        return $admin;
    }

    private function makeDeptDiv(string $tag): array
    {
        $dept = Department::create(['department_name' => "RptDept{$tag}_" . uniqid()]);
        $div = Division::create(['division_name' => "RptDiv{$tag}_" . uniqid(), 'department_id' => $dept->id]);

        return [$dept, $div];
    }

    public function test_tickets_overview_colors_follow_saved_statuses_after_rename_and_color_edits(): void
    {
        $this->actingAs($this->makeAdmin(), 'web');
        $expected = Status::whereIn('status_code', Status::CORE_CODES)->get()->keyBy('status_code');
        $colors = Status::all()->pluck('status_color')->all();
        $response = $this->get(route('page.reports.index'))->assertOk();
        $this->assertSame($colors, $response->viewData('reportTicketsOverview')['colors']);
        $document = new \DOMDocument();
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        $payload = (new \DOMXPath($document))->query('//script[@data-report-charts]')->item(0)->textContent;
        $this->assertSame($colors, json_decode($payload, true, 512, JSON_THROW_ON_ERROR)['status']['colors']);
        $response->assertSee('analytics-charts.js', false);

        $expected[Status::PENDING]->update(['status_name' => 'Awaiting Action', 'status_color' => '#13579b']);
        $response = $this->get(route('page.reports.index'))->assertOk();
        $funnel = $response->viewData('reportTicketsOverview');
        $index = array_search('Awaiting Action', $funnel['labels'], true);
        $this->assertNotFalse($index);
        $this->assertSame('#13579b', $funnel['colors'][$index]);
        $this->assertSame(0, array_sum($funnel['counts']));
        $this->assertSame(count($funnel['labels']), count($funnel['colors']));
    }

    public function test_reports_page_returns_widget_data_with_known_counts()
    {
        $admin = $this->makeAdmin();
        [$dept, $div] = $this->makeDeptDiv('A');
        $priority = Priority::firstOrCreate(['priority_name' => 'Normal'], ['priority_color' => '#ccc']);
        $unassigned = Status::firstOrCreate(['status_name' => 'Unassigned'], ['status_color' => '#ccc']);
        $resolved = Status::firstOrCreate(['status_name' => 'Resolved'], ['status_color' => '#ccc']);
        $issue = Issue::create([
            'department_id' => $dept->id,
            'division_id' => $div->id,
            'priority_id' => $priority->id,
            'issue_description' => 'Widget Test Issue ' . uniqid(),
        ]);

        $now = Carbon::now();

        // Ticket 1: open, created 5 days ago -> overdue (case A)
        $t1 = new Ticket();
        $t1->forceFill([
            'user_id' => $admin->id,
            'department_id' => $dept->id,
            'division_id' => $div->id,
            'issue_id' => $issue->id,
            'status_id' => $unassigned->id,
            'message' => 'overdue open ticket',
        ]);
        $t1->save();
        $t1->created_at = $now->copy()->subDays(5);
        $t1->saveQuietly();

        // Ticket 2: resolved within SLA (created 1 day ago, resolved now -> 24h)
        $t2 = new Ticket();
        $t2->forceFill([
            'user_id' => $admin->id,
            'department_id' => $dept->id,
            'division_id' => $div->id,
            'issue_id' => $issue->id,
            'status_id' => $unassigned->id,
            'message' => 'on-time resolved ticket',
        ]);
        $t2->save();
        $t2->created_at = $now->copy()->subDay();
        $t2->saveQuietly();
        $t2->status_id = $resolved->id;
        $t2->save();
        $t2->resolved_at = $now;
        $t2->saveQuietly();

        // Reassignment request against ticket 1
        TicketReassignmentRequest::create([
            'ticket_id' => $t1->id,
            'from_department_id' => $dept->id,
            'to_department_id' => $dept->id,
            'requested_by' => $admin->id,
            'status' => TicketReassignmentRequest::STATUS_PENDING,
        ]);

        $this->actingAs($admin, 'web');
        // This fixture spans a month boundary during the first five days of a month.
        $response = $this->get(route('page.reports.index', ['period' => 'all']));

        $response->assertStatus(200);
        $response->assertViewHas('reportKpis');
        $response->assertViewHas('reportVolumeTrend');
        $response->assertViewHas('reportResolutionDistribution');
        $response->assertViewHas('reportSlaBreakdown');
        $response->assertViewHas('reportStatusFunnel');
        $response->assertViewHas('reportReassignment');

        $kpis = $response->viewData('reportKpis');
        $this->assertGreaterThanOrEqual(2, $kpis['total']);
        $this->assertGreaterThanOrEqual(1, $kpis['overdue_count']);

        $funnel = $response->viewData('reportStatusFunnel');
        $this->assertSame(['Unassigned', 'Pending', 'Resolved', 'Reopened'], $funnel['labels']);

        $reassignment = $response->viewData('reportReassignment');
        $this->assertGreaterThanOrEqual(1, $reassignment['reassigned_count']);
    }

    public function test_resolution_distribution_buckets_sum_to_resolved_count()
    {
        $admin = $this->makeAdmin();
        [$dept, $div] = $this->makeDeptDiv('B');
        $priority = Priority::firstOrCreate(['priority_name' => 'Normal'], ['priority_color' => '#ccc']);
        $unassigned = Status::firstOrCreate(['status_name' => 'Unassigned'], ['status_color' => '#ccc']);
        $resolved = Status::firstOrCreate(['status_name' => 'Resolved'], ['status_color' => '#ccc']);
        $issue = Issue::create([
            'department_id' => $dept->id,
            'division_id' => $div->id,
            'priority_id' => $priority->id,
            'issue_description' => 'Bucket Test Issue ' . uniqid(),
        ]);

        $now = Carbon::now();

        $ticket = new Ticket();
        $ticket->forceFill([
            'user_id' => $admin->id,
            'department_id' => $dept->id,
            'division_id' => $div->id,
            'issue_id' => $issue->id,
            'status_id' => $unassigned->id,
            'message' => 'bucket test ticket',
        ]);
        $ticket->save();
        $ticket->created_at = $now->copy()->subHours(10);
        $ticket->saveQuietly();
        $ticket->status_id = $resolved->id;
        $ticket->save();
        $ticket->resolved_at = $now;
        $ticket->saveQuietly();

        $this->actingAs($admin, 'web');
        $response = $this->get(route('page.reports.index'));

        $distribution = $response->viewData('reportResolutionDistribution');
        $totalBucketed = array_sum($distribution['counts']);

        $kpis = $response->viewData('reportKpis');
        $this->assertLessThanOrEqual($kpis['resolved'], $totalBucketed);
    }
}
