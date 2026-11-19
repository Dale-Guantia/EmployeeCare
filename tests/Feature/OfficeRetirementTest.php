<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Division;
use App\Models\Issue;
use App\Models\Status;
use App\Models\Survey;
use App\Models\Ticket;
use App\Models\TicketReassignmentRequest;
use App\Models\User;
use App\Services\IssueSelectionRules;
use App\Services\OfficeSelectionRules;
use App\Services\TicketAssignmentRules;
use App\Services\TicketNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OfficeRetirementTest extends TestCase
{
    private $admin;
    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(TicketNotificationService::class, \Mockery::mock(TicketNotificationService::class)->shouldIgnoreMissing());
        $this->admin = User::role('admin')->firstOrFail();
        $this->actingAs($this->admin, 'web');
    }

    private function offices(): array
    {
        $department = Department::create(['department_name' => 'Retirement department ' . ++$this->sequence]);
        $division = Division::create(['division_name' => 'Retirement division ' . $this->sequence, 'department_id' => $department->id]);
        return [$department, $division];
    }

    private function issue($department, $division): Issue
    {
        return Issue::create(['department_id' => $department->id, 'division_id' => $division->id,
            'issue_description' => 'Retirement issue ' . ++$this->sequence]);
    }

    private function ticket($department, $division, string $status = 'Pending', array $extra = []): Ticket
    {
        $id = DB::table('tickets')->insertGetId(array_replace([
            'reference_id' => 'RETIRE-' . ++$this->sequence, 'user_id' => $this->admin->id,
            'department_id' => $department->id, 'division_id' => $division->id,
            'status_id' => Status::idByName($status), 'message' => 'Synthetic retirement fixture',
            'is_custom_issue' => true, 'custom_issue' => 'Historical service',
            'created_at' => now()->subDays(5), 'updated_at' => now(),
            'resolved_at' => $status === 'Resolved' ? now()->subDay() : null,
        ], $extra));
        return Ticket::findOrFail($id);
    }

    private function user($department, $division, bool $active = true): User
    {
        return User::factory()->create(['department_id' => $department->id,
            'division_id' => $division->id, 'is_active' => $active]);
    }

    private function pendingTransfer($fromDepartment, $fromDivision, $toDepartment, $toDivision): TicketReassignmentRequest
    {
        $ticket = $this->ticket($fromDepartment, $fromDivision, 'Resolved');
        return TicketReassignmentRequest::create(['ticket_id' => $ticket->id,
            'from_department_id' => $fromDepartment->id, 'from_division_id' => $fromDivision->id,
            'to_department_id' => $toDepartment->id, 'to_division_id' => $toDivision->id,
            'requested_by' => $this->admin->id, 'status' => TicketReassignmentRequest::STATUS_PENDING]);
    }

    public function test_empty_offices_can_archive_and_reactivate_without_changing_child_flags(): void
    {
        [$department, $division] = $this->offices();
        $issue = $this->issue($department, $division);
        $this->postJson(route('department.archive', $department->id))->assertOk();
        $this->assertFalse($department->fresh()->is_active);
        $this->assertTrue($division->fresh()->is_active);
        $this->assertTrue($issue->fresh()->is_active);
        $this->assertFalse(OfficeSelectionRules::divisions(Division::query())->whereKey($division->id)->exists());
        $this->assertFalse(IssueSelectionRules::options(Issue::query())->whereKey($issue->id)->exists());
        $this->postJson(route('department.reactivate', $department->id))->assertOk();
        $this->assertTrue(OfficeSelectionRules::divisions(Division::query())->whereKey($division->id)->exists());
        $this->assertTrue(IssueSelectionRules::options(Issue::query())->whereKey($issue->id)->exists());
    }

    public function test_reactivating_a_department_preserves_individually_archived_children(): void
    {
        [$department, $division] = $this->offices();
        $issue = $this->issue($department, $division);
        $issue->update(['is_active' => false]);
        $this->postJson(route('division.archive', $division->id))->assertOk();
        $this->postJson(route('department.archive', $department->id))->assertOk();
        $this->postJson(route('division.reactivate', $division->id))->assertUnprocessable()->assertJsonValidationErrors('department_id');
        $this->postJson(route('department.reactivate', $department->id))->assertOk();
        $this->assertFalse($division->fresh()->is_active);
        $this->assertFalse($issue->fresh()->is_active);
        $this->postJson(route('division.reactivate', $division->id))->assertOk();
        $this->assertFalse($issue->fresh()->is_active);
    }

    public function test_active_users_block_retirement_until_moved_or_deactivated(): void
    {
        [$department, $division] = $this->offices();
        $user = $this->user($department, $division);
        foreach (['department' => $department, 'division' => $division] as $entity => $office) {
            $this->postJson(route($entity . '.archive', $office->id))->assertUnprocessable()
                ->assertJsonValidationErrors('is_active')->assertSee('1 active user account(s)');
            $this->assertTrue($office->fresh()->is_active);
        }
        $user->update(['is_active' => false]);
        $this->postJson(route('division.archive', $division->id))->assertOk();
        $this->assertSame($division->id, $user->fresh()->division_id);
    }

    public function test_blocked_archive_returns_the_explanation_for_ajax_and_flashes_an_error_for_normal_forms(): void
    {
        [$department, $division] = $this->offices();
        $this->user($department, $division);
        $this->postJson(route('division.archive', $division->id))->assertUnprocessable()
            ->assertJsonValidationErrors('is_active')->assertSee('1 active user account(s)');
        $key = config('prologue.alerts.session_key', 'alert_messages');
        $this->post(route('division.archive', $division->id))->assertRedirect(backpack_url('division'))
            ->assertSessionHas($key . '.error', fn ($messages) => str_contains(implode(' ', $messages), '1 active user account(s)'));
        $this->assertTrue($department->fresh()->is_active);
        $this->assertTrue($division->fresh()->is_active);
    }

    public function test_all_unresolved_statuses_block_retirement_and_resolved_history_does_not(): void
    {
        [$department, $division] = $this->offices();
        DB::table('statuses')->insert(['status_name' => 'Waiting fixture', 'status_color' => '#fff']);
        \Illuminate\Support\Facades\Cache::forget(Status::CACHE_KEY);
        foreach (['Pending', 'Unassigned', 'Reopened', 'Waiting fixture'] as $status) {
            $this->ticket($department, $division, $status);
        }
        foreach (['department' => $department, 'division' => $division] as $entity => $office) {
            $this->postJson(route($entity . '.archive', $office->id))->assertUnprocessable()
                ->assertSee('4 unresolved ticket(s)');
        }
        DB::table('tickets')->where('department_id', $department->id)->update([
            'status_id' => Status::idByCode(Status::RESOLVED), 'resolved_at' => now(),
        ]);
        $this->postJson(route('division.archive', $division->id))->assertOk();
        $this->postJson(route('department.archive', $department->id))->assertOk();
        $this->assertSame(4, Ticket::where('division_id', $division->id)->count());
    }

    public function test_pending_incoming_and_outgoing_transfers_block_retirement(): void
    {
        [$department, $division] = $this->offices();
        [$otherDepartment, $otherDivision] = $this->offices();
        $incoming = $this->pendingTransfer($otherDepartment, $otherDivision, $department, $division);
        $outgoing = $this->pendingTransfer($department, $division, $otherDepartment, $otherDivision);
        foreach (['department' => $department, 'division' => $division] as $entity => $office) {
            $this->postJson(route($entity . '.archive', $office->id))->assertUnprocessable()->assertSee('2 pending transfer(s)');
        }
        $incoming->update(['status' => TicketReassignmentRequest::STATUS_CANCELLED]);
        $outgoing->update(['status' => TicketReassignmentRequest::STATUS_ACCEPTED]);
        $this->postJson(route('division.archive', $division->id))->assertOk();
    }

    public function test_checkbox_edits_and_model_updates_cannot_bypass_retirement_checks(): void
    {
        [$department, $division] = $this->offices();
        $this->user($department, $division);
        $this->putJson(route('department.update', $department->id), ['id' => $department->id,
            'department_name' => $department->department_name, 'is_active' => 0])
            ->assertUnprocessable()->assertJsonValidationErrors('is_active');
        $this->putJson(route('division.update', $division->id), ['id' => $division->id,
            'division_name' => $division->division_name, 'department_id' => $department->id, 'is_active' => 0])
            ->assertUnprocessable()->assertJsonValidationErrors('is_active');
        try {
            $department->update(['is_active' => false]);
            $this->fail('Direct model retirement must also enforce blockers.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('is_active', $e->errors());
        }
        $this->assertTrue($department->fresh()->is_active);
        $this->assertTrue($division->fresh()->is_active);
    }

    public function test_survey_department_is_protected_from_retirement_and_deletion(): void
    {
        $this->postJson(route('department.archive', 1))->assertUnprocessable()->assertJsonValidationErrors('is_active');
        $this->deleteJson(route('department.destroy', 1))->assertOk()->assertJsonStructure(['error']);
        $this->assertTrue(Department::findOrFail(1)->is_active);
    }

    public function test_retirement_keeps_historical_ids_and_inactive_user_editing(): void
    {
        [$department, $division] = $this->offices();
        $issue = $this->issue($department, $division);
        $user = $this->user($department, $division, false);
        $ticket = $this->ticket($department, $division, 'Resolved', ['issue_id' => $issue->id, 'is_custom_issue' => false]);
        $this->postJson(route('department.archive', $department->id))->assertOk();
        $this->assertSame($department->department_name, $ticket->fresh()->department->department_name);
        $this->assertSame($division->division_name, $ticket->fresh()->division->division_name);
        $this->assertSame($department->id, $issue->fresh()->department_id);
        $this->putJson(route('user.update', $user->id), ['id' => $user->id, 'name' => 'Updated inactive fixture',
            'email' => $user->email, 'department_id' => $department->id, 'division_id' => $division->id, 'is_active' => 0])->assertRedirect();
        $this->assertSame($division->id, $user->fresh()->division_id);
        $this->putJson(route('user.update', $user->id), ['id' => $user->id, 'name' => $user->name,
            'email' => $user->email, 'department_id' => $department->id, 'division_id' => $division->id, 'is_active' => 1])
            ->assertUnprocessable();
        $this->assertFalse($user->fresh()->is_active);
    }

    public function test_new_choices_and_crafted_submissions_exclude_inherited_archived_offices(): void
    {
        [$department, $division] = $this->offices();
        $issue = $this->issue($department, $division);
        $this->postJson(route('department.archive', $department->id))->assertOk();
        $this->getJson(url('api/department/' . $department->id . '/divisions'))->assertOk()->assertExactJson([]);
        $this->get(route('ticket.create'))->assertOk()->assertDontSee($issue->issue_description)->assertDontSee($department->department_name);
        $this->postJson(route('ticket.store'), ['is_custom_issue' => 0, 'issue_id' => $issue->id, 'message' => 'Rejected standard ticket'])
            ->assertUnprocessable()->assertJsonValidationErrors('issue_id');
        $this->postJson(route('ticket.store'), ['is_custom_issue' => 1, 'custom_issue' => 'Rejected custom ticket', 'message' => 'Fixture',
            'department_id' => $department->id, 'division_id' => $division->id])->assertUnprocessable()->assertJsonValidationErrors(['department_id', 'division_id']);
        $this->postJson(route('issue.store'), ['issue_description' => 'Rejected issue',
            'department_id' => $department->id, 'division_id' => $division->id])->assertUnprocessable()->assertJsonValidationErrors(['department_id', 'division_id']);
        $this->postJson(route('division.store'), ['division_name' => 'Rejected child', 'department_id' => $department->id])
            ->assertUnprocessable()->assertJsonValidationErrors('department_id');
    }

    public function test_resolved_historical_tickets_remain_editable_but_require_an_active_office_to_reopen(): void
    {
        [$department, $division] = $this->offices();
        $ticket = $this->ticket($department, $division, 'Resolved');
        $this->postJson(route('department.archive', $department->id))->assertOk();
        $this->putJson(route('ticket.update', $ticket->id), ['id' => $ticket->id, 'is_custom_issue' => 1,
            'custom_issue' => 'Historical service', 'message' => 'Updated historical details',
            'department_id' => $department->id, 'division_id' => $division->id])->assertRedirect();
        $this->assertSame('Updated historical details', $ticket->fresh()->message);
        $this->putJson(route('ticket.update', $ticket->id), ['_ticket_action' => 'reopen'])->assertUnprocessable();
        $this->assertSame(Status::idByCode(Status::RESOLVED), $ticket->fresh()->status_id);
    }

    public function test_editing_reassigned_tickets_preserves_the_actual_office_even_if_the_original_office_is_archived(): void
    {
        [$department, $division] = $this->offices();
        [$targetDepartment, $targetDivision] = $this->offices();
        $issue = $this->issue($department, $division);
        $ticket = $this->ticket($targetDepartment, $targetDivision, 'Pending', ['issue_id' => $issue->id, 'is_custom_issue' => false]);
        $this->postJson(route('department.archive', $department->id))->assertOk();
        $this->putJson(route('ticket.update', $ticket->id), ['id' => $ticket->id, 'is_custom_issue' => 0,
            'issue_id' => $issue->id, 'message' => 'Retained reassignment'])->assertRedirect();
        $this->assertSame($targetDepartment->id, $ticket->fresh()->department_id);
        $this->assertSame($targetDivision->id, $ticket->fresh()->division_id);
    }

    public function test_reassignment_rejects_archived_targets_and_auto_assignment_excludes_legacy_staff_in_archived_offices(): void
    {
        [$department, $division] = $this->offices();
        [$targetDepartment, $targetDivision] = $this->offices();
        $ticket = $this->ticket($department, $division);
        $this->postJson(route('department.archive', $targetDepartment->id))->assertOk();
        $this->put(route('ticket.update', $ticket->id), ['_ticket_action' => 'reassign_request',
            'to_department_id' => $targetDepartment->id, 'to_division_id' => $targetDivision->id])->assertRedirect();
        $this->assertSame(0, TicketReassignmentRequest::where('ticket_id', $ticket->id)->count());
        $staff = $this->user($department, $division);
        $staff->assignRole('hr_staff');
        DB::table('departments')->where('id', $department->id)->update(['is_active' => false]);
        $this->assertFalse(TicketAssignmentRules::eligibleStaff()->whereKey($staff->id)->exists());
    }

    public function test_saved_survey_retry_survives_division_retirement_but_new_surveys_are_blocked(): void
    {
        $department = Department::findOrFail(1);
        $division = Division::create(['department_id' => 1, 'division_name' => 'Survey retirement division']);
        $issue = $this->issue($department, $division);
        $staff = $this->user($department, $division);
        $staff->assignRole('hr_staff');
        $payload = ['submission_id' => (string) Str::uuid(), 'user_id' => $staff->id, 'issue_id' => $issue->id,
            'timeliness_rating' => 'Satisfied', 'handling_rating' => 'Satisfied', 'quality_rating' => 'Satisfied', 'overall_rating' => 'Satisfied'];
        $this->postJson(route('survey.submit'), $payload)->assertOk()->assertJsonPath('saved', true);
        $staff->update(['is_active' => false]);
        $this->postJson(route('division.archive', $division->id))->assertOk();
        $this->get(route('survey.form'))->assertOk()->assertDontSee($issue->issue_description)->assertDontSee('SURVEY RETIREMENT DIVISION');
        $this->postJson(route('survey.submit'), $payload)->assertOk()->assertJsonPath('saved', true);
        $this->postJson(route('survey.submit'), array_replace($payload, ['submission_id' => (string) Str::uuid()]))->assertUnprocessable();
        $this->assertSame($issue->id, Survey::where('submission_id', $payload['submission_id'])->firstOrFail()->issue_id);
    }

    public function test_update_permissions_control_archiving_and_delete_permissions_stay_separate(): void
    {
        [$department, $division] = $this->offices();
        $viewer = User::factory()->create(['is_active' => true]);
        $viewer->givePermissionTo(['department.view', 'division.view']);
        $this->actingAs($viewer, 'web');
        foreach (['department' => $department, 'division' => $division] as $entity => $office) {
            $this->postJson(route($entity . '.archive', $office->id))->assertForbidden();
            $this->postJson(route($entity . '.reactivate', $office->id))->assertForbidden();
        }
        $viewer->givePermissionTo('division.archive');
        $this->actingAs($viewer->fresh(), 'web')->postJson(route('division.archive', $division->id))->assertOk();
        $this->deleteJson(route('division.destroy', $division->id))->assertForbidden();
    }

    public function test_office_actions_render_with_confirmation_and_preserve_csrf(): void
    {
        [$department, $division] = $this->offices();
        foreach (['department' => $department, 'division' => $division] as $entity => $office) {
            $this->postJson(backpack_url($entity . '/search'), ['start' => 0, 'length' => 10, 'draw' => 1], ['X-Requested-With' => 'XMLHttpRequest'])
                ->assertOk()->assertSee('data-office-archive-action', false)->assertSee('_token');
            $this->get(route($entity . '.archive', $office->id))->assertStatus(405);
        }
        $this->app['env'] = 'local';
        $this->withSession(['_token' => 'office-token'])->post(route('division.archive', $division->id))->assertStatus(419);
        $this->post(route('division.archive', $division->id), ['_token' => 'office-token'])->assertRedirect();
        $this->assertFalse($division->fresh()->is_active);
    }

    public function test_referenced_offices_remain_protected_from_deletion_and_unused_offices_can_be_deleted(): void
    {
        [$department, $division] = $this->offices();
        $ticket = $this->ticket($department, $division, 'Resolved');
        $this->postJson(route('department.archive', $department->id))->assertOk();
        foreach (['department' => $department, 'division' => $division] as $entity => $office) {
            $this->deleteJson(route($entity . '.destroy', $office->id))->assertOk()->assertJsonStructure(['error']);
            $this->assertNotNull($office->fresh());
        }
        $this->assertSame($department->id, $ticket->fresh()->department_id);
        [$emptyDepartment, $emptyDivision] = $this->offices();
        $this->postJson(route('division.archive', $emptyDivision->id))->assertOk();
        $this->deleteJson(route('division.destroy', $emptyDivision->id))->assertOk()->assertContent('1');
        $this->postJson(route('department.archive', $emptyDepartment->id))->assertOk();
        $this->deleteJson(route('department.destroy', $emptyDepartment->id))->assertOk()->assertContent('1');
    }

    public function test_migration_round_trip_preserves_existing_records_and_defaults_to_active(): void
    {
        [$department, $division] = $this->offices();
        $ticket = $this->ticket($department, $division, 'Resolved');
        $migration = require database_path('migrations/2026_11_03_110000_add_office_archiving.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('departments', 'is_active'));
        $this->assertFalse(Schema::hasColumn('divisions', 'is_active'));
        $migration->up();
        $this->assertTrue($department->fresh()->is_active);
        $this->assertTrue($division->fresh()->is_active);
        $this->assertSame($division->id, $ticket->fresh()->division_id);
    }

    public function test_models_cannot_add_new_references_or_activate_users_in_archived_offices(): void
    {
        [$department, $division] = $this->offices();
        $user = $this->user($department, $division, false);
        $issue = $this->issue($department, $division);
        $issue->update(['is_active' => false]);
        $this->postJson(route('department.archive', $department->id))->assertOk();
        foreach (['user', 'issue', 'division', 'activate'] as $operation) {
            try {
                match ($operation) {
                    'user' => $this->user($department, $division),
                    'issue' => $this->issue($department, $division),
                    'division' => Division::create(['department_id' => $department->id, 'division_name' => 'Rejected child']),
                    'activate' => $user->update(['is_active' => true]),
                };
                $this->fail('New assignments to an archived office must be rejected.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('department_id', $e->errors());
            }
        }
        $this->postJson(route('issue.reactivate', $issue->id))->assertUnprocessable()->assertJsonValidationErrors('department_id');
        $this->assertFalse($issue->fresh()->is_active);
        $this->assertFalse($user->fresh()->is_active);
    }

    public function test_a_stale_transfer_target_is_rechecked_before_acceptance(): void
    {
        [$department, $division] = $this->offices();
        [$targetDepartment, $targetDivision] = $this->offices();
        $request = $this->pendingTransfer($department, $division, $targetDepartment, $targetDivision);
        // Simulate legacy/imported inconsistency; normal retirement already blocks this.
        DB::table('departments')->where('id', $targetDepartment->id)->update(['is_active' => false]);
        $this->put(route('ticket.update', $request->ticket_id), ['_ticket_action' => 'reassign_accept'])->assertRedirect();
        $this->assertSame(TicketReassignmentRequest::STATUS_PENDING, $request->fresh()->status);
        $this->assertSame($department->id, $request->ticket->fresh()->department_id);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('officeEntities')]
    public function test_office_show_pages_expose_the_confirmation_controls(string $entity): void
    {
        Http::fake(['*' => Http::response('// Theme asset fixture', 200, ['Content-Type' => 'application/javascript'])]);
        [$department, $division] = $this->offices();
        $office = $entity === 'department' ? $department : $division;
        $this->get(route($entity . '.show', $office->id))->assertOk()->assertSee('Archive')
            ->assertSee('data-office-archive-action="archive"', false)->assertSee('office-archive.js', false);
    }

    public static function officeEntities(): array
    {
        return [['department'], ['division']];
    }

    public function test_new_pending_transfers_cannot_originate_in_an_archived_office(): void
    {
        [$department, $division] = $this->offices();
        [$targetDepartment, $targetDivision] = $this->offices();
        $ticket = $this->ticket($department, $division, 'Resolved');
        $this->postJson(route('department.archive', $department->id))->assertOk();
        try {
            TicketReassignmentRequest::create(['ticket_id' => $ticket->id,
                'from_department_id' => $department->id, 'from_division_id' => $division->id,
                'to_department_id' => $targetDepartment->id, 'to_division_id' => $targetDivision->id,
                'requested_by' => $this->admin->id, 'status' => TicketReassignmentRequest::STATUS_PENDING]);
            $this->fail('A new pending transfer must use active source and target offices.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('department_id', $e->errors());
        }
        $this->assertSame(0, TicketReassignmentRequest::where('ticket_id', $ticket->id)->count());
    }
}
