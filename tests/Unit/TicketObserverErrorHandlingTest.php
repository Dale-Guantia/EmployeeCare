<?php

namespace Tests\Unit;

use App\Models\Department;
use App\Models\Division;
use App\Models\Issue;
use App\Models\Priority;
use App\Models\Status;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketAutoAssignmentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Tests\TestCase;

class TicketObserverErrorHandlingTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_ticket_creation_succeeds_even_if_auto_assignment_throws()
    {
        $admin = User::role('admin')->first();
        $this->assertNotNull($admin);

        $dept = Department::create(['department_name' => 'ObserverErrDept_' . uniqid()]);
        $div = Division::create(['division_name' => 'ObserverErrDiv_' . uniqid(), 'department_id' => $dept->id]);
        $priority = Priority::firstOrCreate(['priority_name' => 'Normal'], ['priority_color' => '#ccc']);
        $status = Status::firstOrCreate(['status_name' => 'Unassigned'], ['status_color' => '#ccc']);
        $issue = Issue::create([
            'department_id' => $dept->id,
            'division_id' => $div->id,
            'priority_id' => $priority->id,
            'issue_description' => 'Observer error test issue ' . uniqid(),
        ]);

        $failingService = Mockery::mock(TicketAutoAssignmentService::class);
        $failingService->shouldReceive('assignTicket')->andThrow(new \RuntimeException('simulated failure'));
        $this->app->instance(TicketAutoAssignmentService::class, $failingService);

        $ticket = new Ticket();
        $ticket->forceFill([
            'user_id' => $admin->id,
            'department_id' => $dept->id,
            'division_id' => $div->id,
            'issue_id' => $issue->id,
            'status_id' => $status->id,
            'message' => 'observer error handling test',
        ]);
        $ticket->save(); // fires the real, container-resolved observer via the model event

        $this->assertTrue(Ticket::where('id', $ticket->id)->exists());
    }
}
