<?php

namespace Tests\Feature;

use App\Models\{Department, Division, Issue, Priority, Status, Ticket, User};
use App\Services\TicketNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdminWorkflowUpdatesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::role('admin')->firstOrFail(), 'web');
        $this->app->instance(TicketNotificationService::class, \Mockery::mock(TicketNotificationService::class)->shouldIgnoreMissing());
    }

    public function test_reason_is_shown_only_on_edit_forms(): void
    {
        foreach (['department' => Department::first(), 'division' => Division::first(), 'issue' => Issue::first(),
            'priority' => Priority::first(), 'status' => Status::first()] as $entity => $record) {
            $this->get(route($entity . '.create'))->assertOk()->assertDontSee('name="reason"', false);
            $this->get(route($entity . '.edit', $record->id))->assertOk()->assertSee('name="reason"', false);
        }
    }

    public function test_department_without_divisions_accepts_an_issue_and_standard_ticket(): void
    {
        $department = Department::create(['department_name' => 'Department only']);
        $this->postJson(route('issue.store'), ['department_id' => $department->id, 'division_id' => null,
            'issue_description' => 'Department service'])->assertRedirect();
        $issue = Issue::where('issue_description', 'Department service')->firstOrFail();
        $this->assertNull($issue->division_id);
        $this->postJson(route('ticket.store'), ['issue_id' => $issue->id, 'is_custom_issue' => 0,
            'message' => 'Department-only standard ticket'])->assertRedirect();
        $ticket = Ticket::where('message', 'Department-only standard ticket')->firstOrFail();
        $this->assertSame($department->id, $ticket->department_id);
        $this->assertNull($ticket->division_id);
    }

    public function test_issue_requires_matching_division_when_department_has_divisions(): void
    {
        $department = Department::create(['department_name' => 'Divisional office']);
        $division = Division::create(['department_id' => $department->id, 'division_name' => 'Divisional team']);
        $payload = ['department_id' => $department->id, 'issue_description' => 'Divisional service'];
        $this->postJson(route('issue.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('division_id');
        $this->postJson(route('issue.store'), ['department_id' => 'bad', 'division_id' => $division->id, 'issue_description' => 'Bad office'])
            ->assertUnprocessable()->assertJsonValidationErrors('department_id');
        $this->postJson(route('issue.store'), $payload + ['division_id' => 'bad'])
            ->assertUnprocessable()->assertJsonValidationErrors('division_id');
        $this->postJson(route('issue.store'), $payload + ['division_id' => Division::where('department_id', '<>', $department->id)->first()->id])
            ->assertUnprocessable()->assertJsonValidationErrors('division_id');
        $this->postJson(route('issue.store'), $payload + ['division_id' => $division->id])->assertRedirect();
        $issue = Issue::where('issue_description', 'Divisional service')->firstOrFail();
        try {
            $issue->update(['division_id' => null]);
            $this->fail('Direct model writes must enforce the division requirement.');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }
    }

    public function test_department_only_issue_retains_history_when_divisions_are_added(): void
    {
        $department = Department::create(['department_name' => 'Growing office']);
        $issue = Issue::create(['department_id' => $department->id, 'issue_description' => 'Existing service']);
        Division::create(['department_id' => $department->id, 'division_name' => 'New team']);
        $this->putJson(route('issue.update', $issue->id), ['id' => $issue->id, 'department_id' => $department->id,
            'issue_description' => 'Renamed service', 'division_id' => null])->assertRedirect();
        $this->assertNull($issue->fresh()->division_id);
        $other = Department::create(['department_name' => 'Another department only']);
        $copy = Issue::create(['department_id' => $other->id, 'issue_description' => 'Renamed service']);
        $this->assertNotSame($issue->id, $copy->id);
    }

    public function test_department_only_issue_displays_an_active_office_state(): void
    {
        $department = Department::create(['department_name' => 'Active department-only office']);
        Issue::create(['department_id' => $department->id, 'issue_description' => 'Active department-only service']);

        $response = $this->postJson(backpack_url('issue/search'), ['start' => 0, 'length' => 10, 'draw' => 1,
            'search' => ['value' => 'Active department-only service']])->assertOk();

        $this->assertSame(1, $response->json('recordsFiltered'));
        $this->assertSame('Active', trim(strip_tags($response->json('data.0.0'))));
    }

    public function test_custom_ticket_always_redirects_to_list_after_successful_save(): void
    {
        $department = Department::create(['department_name' => 'Custom ticket office']);
        foreach (['save_and_new', 'save_and_edit', 'save_and_back'] as $action) {
            $this->post(route('ticket.store'), ['is_custom_issue' => 1, 'department_id' => $department->id,
                'custom_issue' => 'Custom service ' . $action, 'message' => 'Custom request ' . $action,
                '_save_action' => $action])->assertRedirect(backpack_url('ticket'));
        }
        $this->assertSame(3, Ticket::where('department_id', $department->id)->count());
        $this->postJson(route('ticket.store'), ['is_custom_issue' => 1, 'department_id' => $department->id,
            'message' => 'Missing custom description'])->assertUnprocessable()->assertJsonValidationErrors('custom_issue');
        $this->assertSame(3, Ticket::where('department_id', $department->id)->count());
    }
}
