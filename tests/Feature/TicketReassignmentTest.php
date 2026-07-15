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
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Notifications\DatabaseNotification;
use Tests\TestCase;

class TicketReassignmentTest extends TestCase
{
    use DatabaseTransactions;

    private $priority;
    private $unassignedStatus;
    private $pendingStatus;
    private $resolvedStatus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->priority = Priority::firstOrCreate(['priority_name' => 'Normal'], ['priority_color' => '#ccc']);
        $this->unassignedStatus = Status::firstOrCreate(['status_name' => 'Unassigned'], ['status_color' => '#ccc']);
        $this->pendingStatus = Status::firstOrCreate(['status_name' => 'Pending'], ['status_color' => '#ccc']);
        $this->resolvedStatus = Status::firstOrCreate(['status_name' => 'Resolved'], ['status_color' => '#ccc']);
        Status::firstOrCreate(['status_name' => 'Reopened'], ['status_color' => '#ccc']);
    }

    private function makeDeptDiv(string $tag): array
    {
        $dept = Department::create(['department_name' => "Dept{$tag}_" . uniqid()]);
        $div = Division::create(['division_name' => "Div{$tag}_" . uniqid(), 'department_id' => $dept->id]);

        return [$dept, $div];
    }

    private function makeUser(string $role, ?int $departmentId = null, ?int $divisionId = null, array $extra = []): User
    {
        static $seq = 0;
        $seq++;

        $user = User::create(array_merge([
            'name' => ucfirst($role) . "User{$seq}",
            'username' => "{$role}_user_{$seq}_" . uniqid(),
            'email' => "{$role}_user_{$seq}_" . uniqid() . '@example.test',
            'password' => bcrypt('password'),
            'department_id' => $departmentId,
            'division_id' => $divisionId,
            'is_active' => true,
        ], $extra));

        $user->assignRole($role);

        return $user;
    }

    private function makeTicket(User $creator, Department $dept, Division $div, array $overrides = []): Ticket
    {
        $ticket = new Ticket();
        $ticket->forceFill(array_merge([
            'user_id' => $creator->id,
            'department_id' => $dept->id,
            'division_id' => $div->id,
            'status_id' => $this->unassignedStatus->id,
            'message' => 'test ticket message',
        ], $overrides));
        $ticket->save();

        return $ticket;
    }

    // Backpack's `crud` container binding is registered with ->scoped(),
    // which behaves like a singleton for the lifetime of a single container
    // — fine in production (every real HTTP request is a fresh PHP process)
    // but within one PHPUnit test method the *same* container persists
    // across several simulated requests, so CrudPanel::$entry from an
    // earlier call would otherwise leak into the next one. Forgetting the
    // instance after every dispatched request keeps each call isolated,
    // matching real request/process boundaries.
    private function dispatch(User $actingAs, string $method, string $uri, array $data = [])
    {
        // Backpack's AuthenticateSession middleware stores the acting user's
        // password hash in the session and compares it on every request; a
        // real second actor would have their own separate session/cookie,
        // but multiple actingAs()->call() invocations in one test method
        // share this test's single session store, so switching the acting
        // user mid-test trips that check and silently bounces the request
        // to the login page (a 302, which looks like success unless you
        // check the target). Flushing the session first keeps each
        // dispatch() isolated, matching two independent real sessions.
        $this->app['session']->flush();

        $response = $this->actingAs($actingAs, 'web')->call($method, $uri, $data);

        // The container binding is reset, but the CRUD:: facade caches its
        // resolved instance separately (Facade::$resolvedInstance) — both
        // caches have to be cleared or the next request's controller and
        // its own CRUD:: facade calls would desync onto two different
        // CrudPanel objects.
        $this->app->forgetInstance('crud');
        \Illuminate\Support\Facades\Facade::clearResolvedInstance('crud');

        return $response;
    }

    private function requestReassignment(User $actingAs, Ticket $ticket, Department $toDept, Division $toDiv, ?string $reason = null)
    {
        return $this->dispatch($actingAs, 'PUT', "/employee-care/ticket/{$ticket->id}", [
            '_ticket_action' => 'reassign_request',
            'to_department_id' => $toDept->id,
            'to_division_id' => $toDiv->id,
            'reason' => $reason,
        ]);
    }

    private function acceptReassignment(User $actingAs, Ticket $ticket, int $requestId)
    {
        return $this->dispatch($actingAs, 'PUT', "/employee-care/ticket/{$ticket->id}", [
            '_ticket_action' => 'reassign_accept',
            'reassignment_request_id' => $requestId,
        ]);
    }

    private function rejectReassignment(User $actingAs, Ticket $ticket, int $requestId, ?string $note = null)
    {
        return $this->dispatch($actingAs, 'PUT', "/employee-care/ticket/{$ticket->id}", [
            '_ticket_action' => 'reassign_reject',
            'reassignment_request_id' => $requestId,
            'response_note' => $note,
        ]);
    }

    private function cancelReassignment(User $actingAs, Ticket $ticket, int $requestId)
    {
        return $this->dispatch($actingAs, 'PUT', "/employee-care/ticket/{$ticket->id}", [
            '_ticket_action' => 'reassign_cancel',
            'reassignment_request_id' => $requestId,
        ]);
    }

    // T1 — Requesting creates a pending request and does NOT move the ticket.
    public function test_t1_request_creates_pending_request_without_moving_ticket()
    {
        [$deptA, $divA] = $this->makeDeptDiv('A');
        [$deptB, $divB] = $this->makeDeptDiv('B');

        $divHead = $this->makeUser('div_head', $deptA->id, $divA->id);
        $ticket = $this->makeTicket($divHead, $deptA, $divA);

        $response = $this->requestReassignment($divHead, $ticket, $deptB, $divB, 'wrong division');
        $response->assertRedirect();

        $ticket->refresh();
        $this->assertEquals($deptA->id, $ticket->department_id);
        $this->assertEquals($divA->id, $ticket->division_id);

        $this->assertDatabaseHas('ticket_reassignment_requests', [
            'ticket_id' => $ticket->id,
            'to_department_id' => $deptB->id,
            'to_division_id' => $divB->id,
            'requested_by' => $divHead->id,
            'status' => TicketReassignmentRequest::STATUS_PENDING,
        ]);
    }

    // T2 — div_head cannot request reassignment for a ticket outside their division.
    public function test_t2_div_head_blocked_outside_own_division()
    {
        [$deptA, $divA] = $this->makeDeptDiv('A');
        [$deptB, $divB] = $this->makeDeptDiv('B');
        [$deptC, $divC] = $this->makeDeptDiv('C');

        // The div_head is assigned to Division A, but authored a ticket that
        // (for whatever reason) lives in Division B — they can still view/
        // update it via the "own tickets" access rule, but must NOT be able
        // to request reassignment for it since it's outside their division.
        $divHead = $this->makeUser('div_head', $deptA->id, $divA->id);
        $ticket = $this->makeTicket($divHead, $deptB, $divB);

        $response = $this->requestReassignment($divHead, $ticket, $deptC, $divC);
        $response->assertStatus(403);

        $this->assertDatabaseMissing('ticket_reassignment_requests', ['ticket_id' => $ticket->id]);
    }

    // T3 — Only one pending request allowed per ticket at a time.
    public function test_t3_only_one_pending_request_at_a_time()
    {
        [$deptA, $divA] = $this->makeDeptDiv('A');
        [$deptB, $divB] = $this->makeDeptDiv('B');
        [$deptC, $divC] = $this->makeDeptDiv('C');

        $divHead = $this->makeUser('div_head', $deptA->id, $divA->id);
        $ticket = $this->makeTicket($divHead, $deptA, $divA);

        $this->requestReassignment($divHead, $ticket, $deptB, $divB)->assertRedirect();
        $this->requestReassignment($divHead, $ticket, $deptC, $divC)->assertRedirect();

        $this->assertEquals(1, TicketReassignmentRequest::where('ticket_id', $ticket->id)
            ->where('status', TicketReassignmentRequest::STATUS_PENDING)
            ->count());

        // The one that stuck should be the first request (to Dept/Div B).
        $pending = $ticket->fresh()->pendingReassignmentRequest;
        $this->assertEquals($deptB->id, $pending->to_department_id);
    }

    // T4 — Accept moves the ticket and it persists, for both standard and custom issues.
    public function test_t4_accept_moves_ticket_and_persists_for_standard_issue()
    {
        [$deptA, $divA] = $this->makeDeptDiv('A');
        [$deptB, $divB] = $this->makeDeptDiv('B');

        $issue = Issue::create([
            'department_id' => $deptA->id,
            'division_id' => $divA->id,
            'priority_id' => $this->priority->id,
            'issue_description' => 'Standard Issue ' . uniqid(),
        ]);

        $divHead = $this->makeUser('div_head', $deptA->id, $divA->id);
        $receivingDivHead = $this->makeUser('div_head', $deptB->id, $divB->id);

        $ticket = $this->makeTicket($divHead, $deptA, $divA, ['issue_id' => $issue->id]);

        $response = $this->requestReassignment($divHead, $ticket, $deptB, $divB);
        $requestId = TicketReassignmentRequest::where('ticket_id', $ticket->id)->latest()->first()->id;

        $this->acceptReassignment($receivingDivHead, $ticket, $requestId)->assertRedirect();

        $ticket->refresh();
        $this->assertEquals($deptB->id, $ticket->department_id, 'department_id must persist after accept (Part 0a)');
        $this->assertEquals($divB->id, $ticket->division_id, 'division_id must persist after accept (Part 0a)');

        $this->assertDatabaseHas('ticket_reassignment_requests', [
            'id' => $requestId,
            'status' => TicketReassignmentRequest::STATUS_ACCEPTED,
            'responded_by' => $receivingDivHead->id,
        ]);
    }

    public function test_t4_accept_moves_ticket_and_persists_for_custom_issue()
    {
        [$deptA, $divA] = $this->makeDeptDiv('A');
        [$deptB, $divB] = $this->makeDeptDiv('B');

        $divHead = $this->makeUser('div_head', $deptA->id, $divA->id);
        $receivingDivHead = $this->makeUser('div_head', $deptB->id, $divB->id);

        $ticket = $this->makeTicket($divHead, $deptA, $divA, [
            'custom_issue' => 'Broken chair',
            'is_custom_issue' => true,
        ]);

        $this->requestReassignment($divHead, $ticket, $deptB, $divB);
        $requestId = TicketReassignmentRequest::where('ticket_id', $ticket->id)->latest()->first()->id;

        $this->acceptReassignment($receivingDivHead, $ticket, $requestId)->assertRedirect();

        $ticket->refresh();
        $this->assertEquals($deptB->id, $ticket->department_id);
        $this->assertEquals($divB->id, $ticket->division_id);
    }

    // T5 — Accept auto-assigns a staff member in the NEW division; status -> Pending.
    public function test_t5_accept_auto_assigns_best_staff_in_new_division()
    {
        [$deptA, $divA] = $this->makeDeptDiv('A');
        [$deptB, $divB] = $this->makeDeptDiv('B');

        $issue = Issue::create([
            'department_id' => $deptA->id,
            'division_id' => $divA->id,
            'priority_id' => $this->priority->id,
            'issue_description' => 'Password Reset',
        ]);

        $divHead = $this->makeUser('div_head', $deptA->id, $divA->id);
        $receivingDivHead = $this->makeUser('div_head', $deptB->id, $divB->id);

        // Two candidates in the TARGET division: one has a matching skill
        // with 2 active tickets, the other has no skills but 0 active
        // tickets. Skill match must win regardless of load.
        $skilledStaff = $this->makeUser('hr_staff', $deptB->id, $divB->id, ['skills' => ['Password Reset']]);
        $unskilledStaff = $this->makeUser('hr_staff', $deptB->id, $divB->id, ['skills' => []]);

        for ($i = 0; $i < 2; $i++) {
            $busyTicket = $this->makeTicket($divHead, $deptB, $divB);
            $busyTicket->assigned_to = $skilledStaff->id;
            $busyTicket->status_id = $this->pendingStatus->id;
            $busyTicket->save();
        }

        // Deliberately generic message — the skill match must come from the
        // Issue's name (via TicketAutoAssignmentService), not the free text.
        $ticket = $this->makeTicket($divHead, $deptA, $divA, ['issue_id' => $issue->id]);

        $this->requestReassignment($divHead, $ticket, $deptB, $divB);
        $requestId = TicketReassignmentRequest::where('ticket_id', $ticket->id)->latest()->first()->id;

        $this->acceptReassignment($receivingDivHead, $ticket, $requestId)->assertRedirect();

        $ticket->refresh();
        $this->assertEquals($skilledStaff->id, $ticket->assigned_to,
            'T5: expected the skilled staff (' . $skilledStaff->id . ') to win despite having 2 active tickets ' .
            'vs the unskilled staff (' . $unskilledStaff->id . ') with 0 — skill match must beat load.');
        $this->assertEquals($this->pendingStatus->id, $ticket->status_id);
    }

    // T6 — Accept onto an empty division: no exception, ticket unassigned.
    public function test_t6_accept_onto_empty_division_leaves_unassigned_no_exception()
    {
        [$deptA, $divA] = $this->makeDeptDiv('A');
        [$deptB, $divB] = $this->makeDeptDiv('B'); // deliberately zero hr_staff here

        $divHead = $this->makeUser('div_head', $deptA->id, $divA->id);
        $receivingDivHead = $this->makeUser('div_head', $deptB->id, $divB->id);

        $ticket = $this->makeTicket($divHead, $deptA, $divA);

        $this->requestReassignment($divHead, $ticket, $deptB, $divB);
        $requestId = TicketReassignmentRequest::where('ticket_id', $ticket->id)->latest()->first()->id;

        $this->acceptReassignment($receivingDivHead, $ticket, $requestId)->assertRedirect();

        $ticket->refresh();
        $this->assertEquals($deptB->id, $ticket->department_id);
        $this->assertNull($ticket->assigned_to);
        $this->assertEquals($this->unassignedStatus->id, $ticket->status_id);
    }

    // T7 — Reject leaves the ticket unchanged and records the response_note.
    public function test_t7_reject_leaves_ticket_unchanged_and_records_note()
    {
        [$deptA, $divA] = $this->makeDeptDiv('A');
        [$deptB, $divB] = $this->makeDeptDiv('B');

        $divHead = $this->makeUser('div_head', $deptA->id, $divA->id);
        $receivingDivHead = $this->makeUser('div_head', $deptB->id, $divB->id);
        $staff = $this->makeUser('hr_staff', $deptA->id, $divA->id);

        $ticket = $this->makeTicket($divHead, $deptA, $divA, ['assigned_to' => $staff->id, 'status_id' => $this->pendingStatus->id]);

        $this->requestReassignment($divHead, $ticket, $deptB, $divB);
        $requestId = TicketReassignmentRequest::where('ticket_id', $ticket->id)->latest()->first()->id;

        $this->rejectReassignment($receivingDivHead, $ticket, $requestId, 'Not our team\'s scope')->assertRedirect();

        $ticket->refresh();
        $this->assertEquals($deptA->id, $ticket->department_id);
        $this->assertEquals($divA->id, $ticket->division_id);
        $this->assertEquals($staff->id, $ticket->assigned_to);
        $this->assertEquals($this->pendingStatus->id, $ticket->status_id);

        $this->assertDatabaseHas('ticket_reassignment_requests', [
            'id' => $requestId,
            'status' => TicketReassignmentRequest::STATUS_REJECTED,
            'response_note' => 'Not our team\'s scope',
        ]);
    }

    // T8 — Only the receiving head (or admin) can accept/reject; others get 403.
    // A totally unrelated user (no view access to the ticket at all) legitimately
    // 404s at the CRUD-scoping layer before ever reaching this check — which is
    // more correct than a 403 (it doesn't confirm the ticket's existence to
    // someone with zero relationship to it). The meaningful case for this guard
    // is someone who CAN see the ticket (e.g. the original requester, via their
    // own "my tickets" access) but is still not the receiving head.
    public function test_t8_only_receiving_head_can_accept_or_reject()
    {
        [$deptA, $divA] = $this->makeDeptDiv('A');
        [$deptB, $divB] = $this->makeDeptDiv('B');

        $divHead = $this->makeUser('div_head', $deptA->id, $divA->id);

        $ticket = $this->makeTicket($divHead, $deptA, $divA);

        $this->requestReassignment($divHead, $ticket, $deptB, $divB);
        $requestId = TicketReassignmentRequest::where('ticket_id', $ticket->id)->latest()->first()->id;

        // The requester can see their own ticket, but is not deptB/divB's head.
        $this->acceptReassignment($divHead, $ticket, $requestId)->assertStatus(403);
        $this->rejectReassignment($divHead, $ticket, $requestId)->assertStatus(403);

        $this->assertDatabaseHas('ticket_reassignment_requests', [
            'id' => $requestId,
            'status' => TicketReassignmentRequest::STATUS_PENDING,
        ]);
    }

    // T9 — Resolving a ticket with a pending request cancels/neutralizes it.
    public function test_t9_resolving_ticket_cancels_pending_request()
    {
        [$deptA, $divA] = $this->makeDeptDiv('A');
        [$deptB, $divB] = $this->makeDeptDiv('B');

        $divHead = $this->makeUser('div_head', $deptA->id, $divA->id);
        $ticket = $this->makeTicket($divHead, $deptA, $divA);

        $this->requestReassignment($divHead, $ticket, $deptB, $divB);
        $requestId = TicketReassignmentRequest::where('ticket_id', $ticket->id)->latest()->first()->id;

        $ticket->refresh();
        $ticket->status_id = $this->resolvedStatus->id;
        $ticket->save();

        $this->assertDatabaseHas('ticket_reassignment_requests', [
            'id' => $requestId,
            'status' => TicketReassignmentRequest::STATUS_CANCELLED,
        ]);
    }

    // T10 — Requester can cancel their own pending request.
    public function test_t10_requester_can_cancel_own_pending_request()
    {
        [$deptA, $divA] = $this->makeDeptDiv('A');
        [$deptB, $divB] = $this->makeDeptDiv('B');

        $divHead = $this->makeUser('div_head', $deptA->id, $divA->id);
        $ticket = $this->makeTicket($divHead, $deptA, $divA);

        $this->requestReassignment($divHead, $ticket, $deptB, $divB);
        $requestId = TicketReassignmentRequest::where('ticket_id', $ticket->id)->latest()->first()->id;

        $this->cancelReassignment($divHead, $ticket, $requestId)->assertRedirect();

        $this->assertDatabaseHas('ticket_reassignment_requests', [
            'id' => $requestId,
            'status' => TicketReassignmentRequest::STATUS_CANCELLED,
        ]);
    }

    // T11 — Deleting a ticket with reassignment requests does not FK-error.
    public function test_t11_deleting_ticket_with_reassignment_requests_does_not_fk_error()
    {
        [$deptA, $divA] = $this->makeDeptDiv('A');
        [$deptB, $divB] = $this->makeDeptDiv('B');

        $divHead = $this->makeUser('div_head', $deptA->id, $divA->id);
        $ticket = $this->makeTicket($divHead, $deptA, $divA);

        $this->requestReassignment($divHead, $ticket, $deptB, $divB);
        $requestId = TicketReassignmentRequest::where('ticket_id', $ticket->id)->latest()->first()->id;

        $ticket->delete();

        $this->assertNull(Ticket::find($ticket->id));
        $this->assertNull(TicketReassignmentRequest::find($requestId));
    }

    // T12 — Regression: a normal full edit still works and does NOT create/trigger reassignment or auto-assign.
    public function test_t12_normal_full_edit_regression()
    {
        [$deptA, $divA] = $this->makeDeptDiv('A');

        $divHead = $this->makeUser('div_head', $deptA->id, $divA->id);
        $issue = Issue::create([
            'department_id' => $deptA->id,
            'division_id' => $divA->id,
            'priority_id' => $this->priority->id,
            'issue_description' => 'T12 Issue ' . uniqid(),
        ]);

        $ticket = $this->makeTicket($divHead, $deptA, $divA, ['issue_id' => $issue->id]);

        $response = $this->dispatch($divHead, 'PUT', "/employee-care/ticket/{$ticket->id}", [
            'id' => $ticket->id,
            'issue_id' => $issue->id,
            'message' => 'an edited message, no _ticket_action present',
            'is_custom_issue' => 0,
        ]);

        $response->assertRedirect();

        $ticket->refresh();
        $this->assertEquals('an edited message, no _ticket_action present', $ticket->message);
        $this->assertEquals(0, TicketReassignmentRequest::where('ticket_id', $ticket->id)->count());
        $this->assertNull($ticket->assigned_to);
    }

    // T13 — Requesting notifies each receiving head (target div_head, target dept_head, admin) and no one else.
    public function test_t13_request_notifies_receiving_heads_only()
    {
        [$deptA, $divA] = $this->makeDeptDiv('A');
        [$deptB, $divB] = $this->makeDeptDiv('B');
        [$deptC, $divC] = $this->makeDeptDiv('C');

        $divHead = $this->makeUser('div_head', $deptA->id, $divA->id);
        $targetDivHead = $this->makeUser('div_head', $deptB->id, $divB->id);
        $targetDeptHead = $this->makeUser('dept_head', $deptB->id, null);
        $admin = $this->makeUser('admin');
        $unrelatedDivHead = $this->makeUser('div_head', $deptC->id, $divC->id);

        $ticket = $this->makeTicket($divHead, $deptA, $divA);

        $this->requestReassignment($divHead, $ticket, $deptB, $divB);

        foreach ([$targetDivHead, $targetDeptHead, $admin] as $expectedRecipient) {
            $this->assertGreaterThan(
                0,
                DatabaseNotification::where('notifiable_id', $expectedRecipient->id)
                    ->where('notifiable_type', User::class)
                    ->get()
                    ->filter(fn ($n) => ($n->data['type'] ?? null) === 'reassignment_requested')
                    ->count(),
                "Expected user {$expectedRecipient->id} ({$expectedRecipient->name}) to receive a reassignment_requested notification."
            );
        }

        $unrelatedNotifications = DatabaseNotification::where('notifiable_id', $unrelatedDivHead->id)
            ->where('notifiable_type', User::class)
            ->get()
            ->filter(fn ($n) => ($n->data['type'] ?? null) === 'reassignment_requested');

        $this->assertCount(0, $unrelatedNotifications);

        $notification = DatabaseNotification::where('notifiable_id', $targetDivHead->id)
            ->where('notifiable_type', User::class)
            ->get()
            ->first(fn ($n) => ($n->data['type'] ?? null) === 'reassignment_requested');

        $this->assertNotNull($notification);
        $this->assertEquals(url("/employee-care/ticket/{$ticket->id}/show"), $notification->data['url']);
    }

    // T14 — Accept notifies the original requester (reassignment_accepted); the
    // newly assigned staff is notified via the existing 'ticket_assigned' path.
    public function test_t14_accept_notifies_requester_and_assigned_staff()
    {
        [$deptA, $divA] = $this->makeDeptDiv('A');
        [$deptB, $divB] = $this->makeDeptDiv('B');

        $divHead = $this->makeUser('div_head', $deptA->id, $divA->id);
        $receivingDivHead = $this->makeUser('div_head', $deptB->id, $divB->id);
        $staff = $this->makeUser('hr_staff', $deptB->id, $divB->id);

        $ticket = $this->makeTicket($divHead, $deptA, $divA);

        $this->requestReassignment($divHead, $ticket, $deptB, $divB);
        $requestId = TicketReassignmentRequest::where('ticket_id', $ticket->id)->latest()->first()->id;

        $this->acceptReassignment($receivingDivHead, $ticket, $requestId);

        $requesterNotified = DatabaseNotification::where('notifiable_id', $divHead->id)
            ->where('notifiable_type', User::class)
            ->get()
            ->filter(fn ($n) => ($n->data['type'] ?? null) === 'reassignment_accepted');

        $this->assertGreaterThan(0, $requesterNotified->count());

        $staffAssignedNotified = DatabaseNotification::where('notifiable_id', $staff->id)
            ->where('notifiable_type', User::class)
            ->get()
            ->filter(fn ($n) => ($n->data['type'] ?? null) === 'ticket_assigned');

        $this->assertGreaterThan(0, $staffAssignedNotified->count(),
            'Expected the newly auto-assigned staff to be notified via the existing ticket_assigned path.');
    }

    // T15 — Reject notifies the requester with the response_note included.
    public function test_t15_reject_notifies_requester_with_note()
    {
        [$deptA, $divA] = $this->makeDeptDiv('A');
        [$deptB, $divB] = $this->makeDeptDiv('B');

        $divHead = $this->makeUser('div_head', $deptA->id, $divA->id);
        $receivingDivHead = $this->makeUser('div_head', $deptB->id, $divB->id);

        $ticket = $this->makeTicket($divHead, $deptA, $divA);

        $this->requestReassignment($divHead, $ticket, $deptB, $divB);
        $requestId = TicketReassignmentRequest::where('ticket_id', $ticket->id)->latest()->first()->id;

        $this->rejectReassignment($receivingDivHead, $ticket, $requestId, 'Wrong queue entirely');

        $notification = DatabaseNotification::where('notifiable_id', $divHead->id)
            ->where('notifiable_type', User::class)
            ->get()
            ->first(fn ($n) => ($n->data['type'] ?? null) === 'reassignment_rejected');

        $this->assertNotNull($notification);
        $this->assertStringContainsString('Wrong queue entirely', $notification->data['message']);
    }

    // T16 — wantsNotification defaults to TRUE for the new keys with no explicit preference set.
    public function test_t16_wants_notification_defaults_true_for_unset_reassignment_keys()
    {
        [$deptA, $divA] = $this->makeDeptDiv('A');
        [$deptB, $divB] = $this->makeDeptDiv('B');

        $divHead = $this->makeUser('div_head', $deptA->id, $divA->id);
        $targetDivHead = $this->makeUser('div_head', $deptB->id, $divB->id);

        // Sanity check at the model level first: no notify_reassignment_*
        // column/attribute exists, yet wantsNotification() must still return true.
        $this->assertTrue($targetDivHead->wantsNotification('notify_reassignment_requested'));
        $this->assertTrue($targetDivHead->wantsNotification('notify_reassignment_responded'));

        $ticket = $this->makeTicket($divHead, $deptA, $divA);
        $this->requestReassignment($divHead, $ticket, $deptB, $divB);

        $notified = DatabaseNotification::where('notifiable_id', $targetDivHead->id)
            ->where('notifiable_type', User::class)
            ->get()
            ->filter(fn ($n) => ($n->data['type'] ?? null) === 'reassignment_requested');

        $this->assertGreaterThan(0, $notified->count(),
            'Notification must fire by default when no explicit preference is set for the new keys.');
    }

    // Regression — the pre-existing Resolve/Reopen/Quick-Assign actions were
    // rerouted onto the new _ticket_action dispatch (Part 0c) and never got an
    // explicit end-to-end HTTP test confirming they still work post-refactor.
    public function test_resolve_action_still_works_via_ticket_action_dispatch()
    {
        [$dept, $div] = $this->makeDeptDiv('Resolve');
        $divHead = $this->makeUser('div_head', $dept->id, $div->id);
        $ticket = $this->makeTicket($divHead, $dept, $div);

        $response = $this->dispatch($divHead, 'PUT', "/employee-care/ticket/{$ticket->id}", [
            '_ticket_action' => 'resolve',
            'status_id' => $this->resolvedStatus->id,
        ]);
        $response->assertRedirect();

        $ticket->refresh();
        $this->assertEquals($this->resolvedStatus->id, $ticket->status_id);
        $this->assertNotNull($ticket->resolved_at);
    }

    public function test_reopen_action_still_works_via_ticket_action_dispatch()
    {
        [$dept, $div] = $this->makeDeptDiv('Reopen');
        $divHead = $this->makeUser('div_head', $dept->id, $div->id);
        $ticket = $this->makeTicket($divHead, $dept, $div, ['status_id' => $this->resolvedStatus->id]);

        // Only the ticket creator may reopen — confirm that guard still holds.
        $otherDivHead = $this->makeUser('div_head', $dept->id, $div->id);
        $this->dispatch($otherDivHead, 'PUT', "/employee-care/ticket/{$ticket->id}", [
            '_ticket_action' => 'reopen',
        ])->assertStatus(403);

        $response = $this->dispatch($divHead, 'PUT', "/employee-care/ticket/{$ticket->id}", [
            '_ticket_action' => 'reopen',
        ]);
        $response->assertRedirect();

        $ticket->refresh();
        $reopenedId = Status::where('status_name', 'Reopened')->value('id');
        $this->assertEquals($reopenedId, $ticket->status_id);
    }

    public function test_quick_assign_action_still_works_via_ticket_action_dispatch()
    {
        [$dept, $div] = $this->makeDeptDiv('QuickAssign');
        $divHead = $this->makeUser('div_head', $dept->id, $div->id);
        $staff = $this->makeUser('hr_staff', $dept->id, $div->id);
        $ticket = $this->makeTicket($divHead, $dept, $div);

        $response = $this->dispatch($divHead, 'PUT', "/employee-care/ticket/{$ticket->id}", [
            '_ticket_action' => 'quick_assign',
            'assigned_to' => $staff->id,
        ]);
        $response->assertRedirect();

        $ticket->refresh();
        $this->assertEquals($staff->id, $ticket->assigned_to);
        $this->assertEquals($this->pendingStatus->id, $ticket->status_id);
    }

    // Static verification of the two frontend fixes (Tom Select placeholders
    // and the modal-nested-in-a-table-cell bug) — there's no browser tool
    // available here, so this checks the actual compiled HTML for the exact
    // markers those fixes rely on, rather than JS execution itself.
    public function test_show_page_html_has_tom_select_assets_and_modal_body_move_fix()
    {
        [$dept, $div] = $this->makeDeptDiv('HtmlCheck');
        $admin = $this->makeUser('admin');
        $ticket = $this->makeTicket($admin, $dept, $div);

        $this->requestReassignment($admin, $ticket, ...$this->makeDeptDiv('HtmlCheckTarget'));

        $response = $this->actingAs($admin, 'web')->get("/employee-care/ticket/{$ticket->id}/show");
        $response->assertStatus(200);
        $html = $response->getContent();

        $this->assertStringContainsString('tom-select', $html, 'Tom Select CDN assets must be present.');
        $this->assertStringContainsString('window.hrfInitTomSelect', $html, 'The Tom Select init helper must be defined.');
        $this->assertStringContainsString('requestReassignModal', $html);
        $this->assertStringContainsString("document.body.appendChild(modal)", $html,
            'The reassignment modals must be moved to <body> or they stay clipped inside the show-page table cell.');
    }

    public function test_create_page_html_has_tom_select_init_with_placeholders()
    {
        $admin = $this->makeUser('admin');

        $response = $this->actingAs($admin, 'web')->get('/employee-care/ticket/create');
        $response->assertStatus(200);
        $html = $response->getContent();

        $this->assertStringContainsString('tom-select', $html);
        foreach (['issue_id', 'department_id', 'division_id', 'assigned_to', 'status_id'] as $field) {
            $this->assertStringContainsString("select[name='{$field}']", $html,
                "Expected a hrfInitTomSelect call targeting {$field}.");
        }
        $this->assertStringContainsString('emptyOptionText', $html,
            'Expected the friendlier placeholder labels instead of the bare "-" default.');
    }
}
