<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Division;
use App\Models\Issue;
use App\Models\Priority;
use App\Models\Status;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketNotificationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TicketNotificationServiceQueryCountTest extends TestCase
{
    use DatabaseTransactions;

    public function test_notify_ticket_created_does_not_lazy_load_roles_per_user()
    {
        $admin = User::role('admin')->first();
        $this->assertNotNull($admin);

        $dept = Department::create(['department_name' => 'NotifDept_' . uniqid()]);
        $div = Division::create(['division_name' => 'NotifDiv_' . uniqid(), 'department_id' => $dept->id]);
        $priority = Priority::firstOrCreate(['priority_name' => 'Normal'], ['priority_color' => '#ccc']);
        $status = Status::firstOrCreate(['status_name' => 'Unassigned'], ['status_color' => '#ccc']);
        $issue = Issue::create([
            'department_id' => $dept->id,
            'division_id' => $div->id,
            'priority_id' => $priority->id,
            'issue_description' => 'Notif Test Issue ' . uniqid(),
        ]);

        $ticket = new Ticket();
        $ticket->forceFill([
            'user_id' => $admin->id,
            'department_id' => $dept->id,
            'division_id' => $div->id,
            'issue_id' => $issue->id,
            'status_id' => $status->id,
            'message' => 'notification query count test',
        ]);
        $ticket->save();

        $queryCount = 0;
        DB::listen(function ($query) use (&$queryCount) {
            $queryCount++;
        });

        app(TicketNotificationService::class)->notifyTicketCreated($ticket, $admin);

        echo "\nNOTIFY QUERY COUNT: {$queryCount}\n";

        $this->assertLessThan(15, $queryCount, "notifyTicketCreated triggered {$queryCount} queries.");
    }
}
