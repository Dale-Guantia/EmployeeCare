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
use Tests\TestCase;

class UserAssignedTicketsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_assigned_tickets_returns_tickets_assigned_to_the_user()
    {
        $dept = Department::create(['department_name' => 'AssignDept_' . uniqid()]);
        $div = Division::create(['division_name' => 'AssignDiv_' . uniqid(), 'department_id' => $dept->id]);
        $priority = Priority::firstOrCreate(['priority_name' => 'Normal'], ['priority_color' => '#ccc']);
        $status = Status::firstOrCreate(['status_name' => 'Pending'], ['status_color' => '#ccc']);
        $issue = Issue::create([
            'department_id' => $dept->id,
            'division_id' => $div->id,
            'priority_id' => $priority->id,
            'issue_description' => 'Assign Test Issue ' . uniqid(),
        ]);

        $creator = User::create([
            'name' => 'Creator_' . uniqid(),
            'username' => 'creator_' . uniqid(),
            'email' => 'creator_' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
            'department_id' => $dept->id,
            'division_id' => $div->id,
            'is_active' => true,
        ]);

        $staff = User::create([
            'name' => 'AssignStaff_' . uniqid(),
            'username' => 'assign_staff_' . uniqid(),
            'email' => 'assign_staff_' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
            'department_id' => $dept->id,
            'division_id' => $div->id,
            'is_active' => true,
        ]);

        $ticket = new Ticket();
        $ticket->forceFill([
            'user_id' => $creator->id,
            'department_id' => $dept->id,
            'division_id' => $div->id,
            'issue_id' => $issue->id,
            'status_id' => $status->id,
            'message' => 'assigned ticket test',
            'assigned_to' => $staff->id,
        ]);
        $ticket->save();

        $this->assertSame(1, $staff->assignedTickets()->count());
        $this->assertSame($ticket->id, $staff->assignedTickets()->first()->id);
    }
}
