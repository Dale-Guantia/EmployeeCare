<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Division;
use App\Models\Issue;
use App\Models\Priority;
use App\Models\Status;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardLatestTicketsQueryCountTest extends TestCase
{
    use DatabaseTransactions;

    public function test_latest_tickets_do_not_trigger_n_plus_one()
    {
        $admin = User::role('admin')->first();
        $this->assertNotNull($admin);

        $dept = Department::create(['department_name' => 'DashN1Dept_' . uniqid()]);
        $div = Division::create(['division_name' => 'DashN1Div_' . uniqid(), 'department_id' => $dept->id]);
        $priority = Priority::firstOrCreate(['priority_name' => 'Normal'], ['priority_color' => '#ccc']);
        $status = Status::firstOrCreate(['status_name' => 'Unassigned'], ['status_color' => '#ccc']);
        $issue = Issue::create([
            'department_id' => $dept->id,
            'division_id' => $div->id,
            'priority_id' => $priority->id,
            'issue_description' => 'Dashboard N+1 Test Issue ' . uniqid(),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $ticket = new Ticket();
            $ticket->forceFill([
                'user_id' => $admin->id,
                'department_id' => $dept->id,
                'division_id' => $div->id,
                'issue_id' => $issue->id,
                'status_id' => $status->id,
                'message' => "dashboard n+1 test {$i}",
            ]);
            $ticket->save();
        }

        $this->actingAs($admin, 'web');

        $queryCount = 0;
        DB::listen(function () use (&$queryCount) {
            $queryCount++;
        });

        $response = $this->get(route('backpack.dashboard'));
        $response->assertStatus(200);

        echo "\nDASHBOARD QUERY COUNT: {$queryCount}\n";

        // Well below the ~1 (base) + 4 relations * 5 rows = 21 queries the
        // N+1 would produce for just the latest-tickets block alone.
        $this->assertLessThan(60, $queryCount, "Dashboard triggered {$queryCount} queries — check for N+1.");
    }
}
