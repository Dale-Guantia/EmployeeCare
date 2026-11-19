<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Division;
use App\Models\Issue;
use App\Models\Priority;
use App\Models\Status;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketAutoAssignmentService;
use Tests\TestCase;

class TicketAssignmentWorkflowTest extends TestCase
{
    private $department;
    private $division;
    private $issue;
    private $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->department = Department::create(['department_name' => 'Synthetic assignment office']);
        $this->division = Division::create(['department_id' => $this->department->id, 'division_name' => 'Synthetic assignment division']);
        $this->issue = Issue::create(['department_id' => $this->department->id, 'division_id' => $this->division->id,
            'priority_id' => Priority::firstOrFail()->id, 'issue_description' => 'Office assignment/ Retagging']);
        $this->owner = User::role('admin')->firstOrFail();
        $this->actingAs($this->owner, 'web');
    }

    private function staff(array $extra = []): User
    {
        $user = User::factory()->create(array_merge(['is_active' => true, 'department_id' => $this->department->id,
            'division_id' => $this->division->id, 'skills' => []], $extra));
        $user->assignRole('hr_staff');
        return $user;
    }

    private function ticket(bool $custom = false): Ticket
    {
        return Ticket::create(['user_id' => $this->owner->id, 'issue_id' => $custom ? null : $this->issue->id,
            'is_custom_issue' => $custom, 'custom_issue' => $custom ? 'Please handle office assignment/retagging' : null,
            'department_id' => $this->department->id, 'division_id' => $this->division->id,
            'message' => 'Synthetic assignment request', 'status_id' => Status::idByCode(Status::UNASSIGNED)]);
    }

    public function test_standard_issue_prefers_normalized_skill_then_workload_and_preserves_identity(): void
    {
        $expert = $this->staff(['skills' => [' office assignment /retagging ']]);
        $other = $this->staff();
        $first = $this->ticket();
        $this->assertSame($expert->id, (int) $first->fresh()->assigned_to);
        $this->assertSame(Status::idByCode(Status::PENDING), $first->fresh()->status_id);
        $this->assertSame($first->id, $first->fresh()->id);
        $other->update(['skills' => ['Office assignment/Retagging']]);
        $this->assertSame($other->id, (int) $this->ticket()->fresh()->assigned_to);
    }

    public function test_custom_issue_balances_workload_and_ties_use_the_lowest_id(): void
    {
        $first = $this->staff(['skills' => ['Office assignment/Retagging']]);
        $second = $this->staff();
        $counts = [$first->id => 0, $second->id => 0];
        for ($index = 0; $index < 20; $index++) {
            $ticket = $this->ticket(true)->fresh();
            if ($index === 0) { $this->assertSame($first->id, (int) $ticket->assigned_to); }
            $counts[$ticket->assigned_to]++;
        }
        $this->assertSame([10, 10], array_values($counts));
    }

    public function test_inactive_wrong_office_and_incoherent_staff_are_not_assigned(): void
    {
        $this->staff(['is_active' => false]);
        $other = Department::create(['department_name' => 'Another synthetic office']);
        $incoherent = $this->staff();
        // Simulate imported legacy inconsistency; normal model writes now reject it.
        \Illuminate\Support\Facades\DB::table('users')->where('id', $incoherent->id)->update(['department_id' => $other->id]);
        $ticket = $this->ticket()->fresh();
        $this->assertNull($ticket->assigned_to);
        $this->assertSame(Status::idByCode(Status::UNASSIGNED), $ticket->status_id);
        $eligible = $this->staff();
        $this->assertSame($eligible->id, (int) $this->ticket()->fresh()->assigned_to);
    }

    public function test_missing_location_does_not_assign_an_unscoped_ticket(): void
    {
        $this->assertFalse(app(TicketAutoAssignmentService::class)->assignTicket(new Ticket(['department_id' => null])));
    }

    public function test_quick_assignment_escapes_staff_names_in_option_labels(): void
    {
        \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response('/* isolated asset */')]);
        $name = '</option></select><img src=x onerror=alert(1)>';
        $this->staff(['name' => $name]);
        $ticket = $this->ticket();

        $this->get(backpack_url("ticket/{$ticket->id}/show"))->assertOk()
            ->assertSee(e($name), false)
            ->assertDontSee($name, false);
    }
}
