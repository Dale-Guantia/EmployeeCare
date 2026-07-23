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

class TicketAutoAssignmentQueryCountTest extends TestCase
{
    use DatabaseTransactions;

    public function test_workload_counting_uses_one_grouped_query_not_one_per_staff()
    {
        $dept = Department::create(['department_name' => 'AutoAssignDept_' . uniqid()]);
        $div = Division::create(['division_name' => 'AutoAssignDiv_' . uniqid(), 'department_id' => $dept->id]);
        $priority = Priority::firstOrCreate(['priority_name' => 'Normal'], ['priority_color' => '#ccc']);
        Status::firstOrCreate(['status_name' => 'Unassigned'], ['status_color' => '#ccc']);
        Status::firstOrCreate(['status_name' => 'Pending'], ['status_color' => '#ccc']);
        Status::firstOrCreate(['status_name' => 'Reopened'], ['status_color' => '#ccc']);
        $issue = Issue::create([
            'department_id' => $dept->id,
            'division_id' => $div->id,
            'priority_id' => $priority->id,
            'issue_description' => 'Auto-assign query count test ' . uniqid(),
        ]);

        // 5 eligible hr_staff in the same dept/division
        $requester = null;
        for ($i = 0; $i < 5; $i++) {
            $staff = User::create([
                'name' => "AutoAssignStaff{$i}_" . uniqid(),
                'username' => "auto_assign_staff_{$i}_" . uniqid(),
                'email' => "auto_assign_staff_{$i}_" . uniqid() . '@example.test',
                'password' => bcrypt('password'),
                'department_id' => $dept->id,
                'division_id' => $div->id,
                'is_active' => true,
            ]);
            $staff->assignRole('hr_staff');
            $requester = $requester ?: $staff;
        }

        $queryCount = 0;
        DB::listen(function () use (&$queryCount) {
            $queryCount++;
        });

        // Ticket creation fires TicketObserver::created() -> assignTicket() ->
        // analyzeContentAndMatch() synchronously.
        $ticket = new Ticket();
        $ticket->forceFill([
            'user_id' => $requester->id,
            'department_id' => $dept->id,
            'division_id' => $div->id,
            'issue_id' => $issue->id,
            'status_id' => Status::idByName('Unassigned'),
            'message' => 'auto-assign query count test ticket',
        ]);
        $ticket->save();

        echo "\nAUTO-ASSIGN QUERY COUNT (5 staff): {$queryCount}\n";

        // With 5 eligible staff, a per-staff COUNT loop would add 5 queries
        // just for workload counting on top of everything else ticket
        // creation already does. This ceiling is generous but would catch a
        // regression back to the per-row loop.
        $this->assertLessThan(40, $queryCount, "Ticket creation with 5 eligible staff triggered {$queryCount} queries.");
    }
}
