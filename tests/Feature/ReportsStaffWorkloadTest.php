<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Division;
use App\Models\Issue;
use App\Models\Priority;
use App\Models\Status;
use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ReportsStaffWorkloadTest extends TestCase
{
    use DatabaseTransactions;

    public function test_staff_workload_includes_assigned_count_and_avg_resolution()
    {
        $admin = User::role('admin')->first();
        $this->assertNotNull($admin);
        Permission::firstOrCreate(['name' => 'reports.view', 'guard_name' => 'web']);
        if (! $admin->can('reports.view')) {
            $admin->givePermissionTo('reports.view');
        }

        // HR department is id 1 in this app (used by the existing $users query filter).
        $hrDept = Department::find(1);
        $this->assertNotNull($hrDept, 'Expected HR department (id 1) to exist.');

        $div = Division::create(['division_name' => 'WorkloadDiv_' . uniqid(), 'department_id' => $hrDept->id]);
        $priority = Priority::firstOrCreate(['priority_name' => 'Normal'], ['priority_color' => '#ccc']);
        $unassigned = Status::firstOrCreate(['status_name' => 'Unassigned'], ['status_color' => '#ccc']);
        $resolved = Status::firstOrCreate(['status_name' => 'Resolved'], ['status_color' => '#ccc']);
        $issue = Issue::create([
            'department_id' => $hrDept->id,
            'division_id' => $div->id,
            'priority_id' => $priority->id,
            'issue_description' => 'Workload Test Issue ' . uniqid(),
        ]);

        $staff = User::create([
            'name' => 'WorkloadStaff_' . uniqid(),
            'username' => 'workload_staff_' . uniqid(),
            'email' => 'workload_staff_' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
            'department_id' => $hrDept->id,
            'division_id' => $div->id,
            'is_active' => true,
        ]);

        $now = Carbon::now();

        $ticket = new Ticket();
        $ticket->forceFill([
            'user_id' => $staff->id,
            'department_id' => $hrDept->id,
            'division_id' => $div->id,
            'issue_id' => $issue->id,
            'status_id' => $unassigned->id,
            'message' => 'workload ticket',
            'assigned_to' => $staff->id,
        ]);
        $ticket->save();
        $ticket->created_at = $now->copy()->subHours(2);
        $ticket->saveQuietly();

        // Use saveQuietly() here: the Ticket model's `saving` observer
        // overwrites resolved_by from the authenticated backpack user (or
        // null if none) whenever status_id transitions to Resolved, which
        // would clobber the explicit resolved_by/resolved_at we need for
        // deterministic assertions below.
        $ticket->status_id = $resolved->id;
        $ticket->resolved_by = $staff->id;
        $ticket->resolved_at = $now;
        $ticket->saveQuietly();

        $this->actingAs($admin, 'web');
        $response = $this->get(route('page.reports.index'));
        $response->assertStatus(200);

        $users = $response->viewData('users');
        $staffRow = $users->firstWhere('id', $staff->id);

        $this->assertNotNull($staffRow, 'Expected the workload test staff member to appear in the users widget.');
        // assertEquals (not assertSame): this SQL Server driver returns
        // withCount() aggregates as numeric strings via PDO, not ints —
        // a pre-existing environment quirk, not something this widget
        // introduces (resolved_tickets_count/overdue_tickets_count behave
        // the same way).
        $this->assertEquals(1, $staffRow->assigned_count);
        $this->assertGreaterThanOrEqual(115, $staffRow->avg_resolution_minutes);
        $this->assertLessThanOrEqual(125, $staffRow->avg_resolution_minutes);
    }
}
