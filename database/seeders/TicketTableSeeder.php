<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Department;
use App\Models\Division;
use App\Models\Issue;
use App\Models\Priority;
use App\Models\Status;
use App\Models\Ticket;
use App\Models\User;

class TicketTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * This is optional test data. It is not called by DatabaseSeeder by default.
     */
    public function run(): void
    {
        $department = Department::where('department_name', 'City Human Resource Development Office')->first();
        $division = Division::where('division_name', 'Administrative')->first();
        $issue = Issue::where('division_id', optional($division)->id)->first();
        $priorityHigh = Priority::where('priority_name', 'High')->first();
        $priorityMedium = Priority::where('priority_name', 'Medium')->first();
        $status = Status::where('status_name', 'Unassigned')->first();
        $admin = User::where('username', 'admin')->first();
        $employee = User::where('username', 'employee')->first();

        if (! $admin || ! $employee || ! $status) {
            return;
        }

        Ticket::updateOrCreate(
            ['reference_id' => '0001-052225'],
            [
                'user_id' => $admin->id,
                'department_id' => optional($department)->id,
                'division_id' => optional($division)->id,
                'status_id' => $status->id,
                'issue_id' => optional($issue)->id,
                'custom_issue' => null,
                'is_custom_issue' => false,
                'priority_id' => optional($priorityHigh)->id,
                'message' => 'Test message',
            ]
        );

        Ticket::updateOrCreate(
            ['reference_id' => '0002-052225'],
            [
                'user_id' => $employee->id,
                'department_id' => optional($department)->id,
                'division_id' => optional($division)->id,
                'status_id' => $status->id,
                'issue_id' => optional($issue)->id,
                'custom_issue' => null,
                'is_custom_issue' => false,
                'priority_id' => optional($priorityMedium)->id,
                'message' => 'Test message 2',
            ]
        );
    }
}
