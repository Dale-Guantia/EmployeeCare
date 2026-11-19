<?php

namespace Tests\Feature;

use App\Models\{Department, Division, Issue, Priority, Status, Ticket, User};
use App\Services\TicketNotificationService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\{Cache, DB};
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MasterDataLifecycleRulesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(TicketNotificationService::class, \Mockery::mock(TicketNotificationService::class)->shouldIgnoreMissing());
        $this->actingAs(User::role('admin')->firstOrFail(), 'web');
    }

    private function office(): array
    {
        $department = Department::create(['department_name' => 'Lifecycle fixture department']);
        $division = Division::create(['department_id' => $department->id, 'division_name' => 'Lifecycle fixture division']);
        return [$department, $division];
    }

    private function issue(Department $department, Division $division, array $extra = []): Issue
    {
        return Issue::create($extra + ['department_id' => $department->id, 'division_id' => $division->id,
            'issue_description' => 'Lifecycle fixture issue']);
    }

    private function legacyStatus(bool $active = true): Status
    {
        $id = DB::table('statuses')->insertGetId(['status_name' => 'Legacy optional status', 'status_color' => '#abcdef', 'is_active' => $active]);
        Cache::forget(Status::CACHE_KEY);
        return Status::findOrFail($id);
    }

    private function ticket(Issue $issue, array $extra = []): Ticket
    {
        return Ticket::withoutEvents(fn () => Ticket::create($extra + [
            'reference_id' => 'LIFECYCLE-FIXTURE', 'user_id' => backpack_user()->id,
            'department_id' => $issue->department_id, 'division_id' => $issue->division_id,
            'issue_id' => $issue->id, 'status_id' => Status::idByCode(Status::RESOLVED),
            'message' => 'Synthetic history fixture', 'resolved_at' => now(),
        ]));
    }

    public function test_admin_can_delete_unused_records_without_archiving_first(): void
    {
        [$department, $division] = $this->office();
        $issue = $this->issue($department, $division);
        $priority = Priority::create(['priority_name' => 'Lifecycle priority', 'priority_color' => '#123456']);
        $status = $this->legacyStatus();
        foreach (['issue' => $issue, 'priority' => $priority, 'status' => $status] as $entity => $record) {
            $this->deleteJson(route($entity . '.destroy', $record->id))->assertOk()->assertContent('1');
        }
        foreach (['division' => $division, 'department' => $department] as $entity => $record) {
            $this->deleteJson(route($entity . '.destroy', $record->id))->assertOk()->assertContent('1');
        }
    }

    public function test_archived_dependents_still_block_parent_deletion_with_a_specific_count(): void
    {
        [$department, $division] = $this->office();
        $issue = $this->issue($department, $division, ['is_active' => false]);
        $this->postJson(route('division.archive', $division->id))->assertOk();
        $this->postJson(route('department.archive', $department->id))->assertOk();
        $this->deleteJson(route('division.destroy', $division->id))->assertOk()->assertSee('1 issues');
        $this->deleteJson(route('department.destroy', $department->id))->assertOk()->assertSee('1 divisions')->assertSee('1 issues');
        $this->assertFalse($issue->fresh()->is_active);
    }

    public function test_referenced_divisions_and_issues_cannot_change_parent_through_http_or_models(): void
    {
        [$department, $division] = $this->office();
        $issue = $this->issue($department, $division);
        $ticket = $this->ticket($issue);
        $target = Department::create(['department_name' => 'Replacement department']);
        $targetDivision = Division::create(['department_id' => $target->id, 'division_name' => 'Replacement division']);
        $this->putJson(route('division.update', $division->id), ['id' => $division->id,
            'division_name' => $division->division_name, 'department_id' => $target->id])->assertUnprocessable()->assertJsonValidationErrors('department_id');
        $this->putJson(route('issue.update', $issue->id), ['id' => $issue->id, 'issue_description' => $issue->issue_description,
            'department_id' => $target->id, 'division_id' => $targetDivision->id])->assertUnprocessable()->assertJsonValidationErrors('division_id');
        foreach ([$division, $issue] as $record) {
            try {
                $record->update(['department_id' => $target->id, 'division_id' => $targetDivision->id]);
                $this->fail('Model writes must enforce parent-change restrictions.');
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
            $this->assertSame($department->id, $record->fresh()->department_id);
        }
        $this->assertSame($division->id, $ticket->fresh()->division_id);
    }

    public function test_names_are_unique_within_their_scope_and_empty_divisions_may_move(): void
    {
        [$department, $division] = $this->office();
        $target = Department::create(['department_name' => 'Other department']);
        $this->postJson(route('division.store'), ['division_name' => $division->division_name, 'department_id' => $department->id])
            ->assertUnprocessable()->assertJsonValidationErrors('division_name');
        $this->postJson(route('division.store'), ['division_name' => $division->division_name, 'department_id' => $target->id])->assertRedirect();
        $issue = $this->issue($department, $division);
        $this->postJson(route('issue.store'), ['issue_description' => $issue->issue_description,
            'department_id' => $department->id, 'division_id' => $division->id])->assertUnprocessable()->assertJsonValidationErrors('issue_description');
        $empty = Division::create(['department_id' => $department->id, 'division_name' => 'Empty division']);
        $empty->update(['department_id' => $target->id]);
        $this->assertSame($target->id, $empty->fresh()->department_id);
    }

    #[DataProvider('coreStatuses')]
    public function test_core_workflow_labels_can_change_but_archiving_and_deletion_remain_prohibited(string $name): void
    {
        $status = Status::where('status_name', $name)->firstOrFail();
        $this->putJson(route('status.update', $status->id), ['id' => $status->id,
            'status_name' => 'Updated display label', 'status_color' => '#123456'])->assertRedirect();
        $this->assertSame($status->id, Status::idByCode($status->status_code));
        $this->postJson(route('status.archive', $status->id))->assertUnprocessable()->assertSee('Core workflow statuses');
        $this->deleteJson(route('status.destroy', $status->id))->assertOk()->assertSee('Core workflow statuses');
        $this->putJson(route('status.update', $status->id), ['id' => $status->id,
            'status_name' => $name, 'status_color' => '#123456'])->assertRedirect();
        $this->assertSame($name, $status->fresh()->status_name);
        $this->assertSame('#123456', $status->fresh()->status_color);
    }

    public static function coreStatuses(): array
    {
        return array_map(fn ($name) => [$name], ['Unassigned', 'Pending', 'Resolved', 'Reopened']);
    }

    public function test_optional_statuses_require_a_supported_workflow_before_new_use_or_reactivation(): void
    {
        $this->postJson(route('status.store'), ['status_name' => 'Unsupported custom status', 'status_color' => '#123456'])
            ->assertUnprocessable()->assertSee('implemented transitions');
        $status = $this->legacyStatus(false);
        $this->postJson(route('status.reactivate', $status->id))->assertUnprocessable()->assertSee('implemented transitions');
        [$department, $division] = $this->office();
        $issue = $this->issue($department, $division);
        $this->postJson(route('ticket.store'), ['is_custom_issue' => 0, 'issue_id' => $issue->id,
            'status_id' => $status->id, 'message' => 'Must not enter unsupported workflow'])
            ->assertUnprocessable()->assertJsonValidationErrors('status_id');
        $this->assertFalse($status->fresh()->is_active);
    }

    public function test_optional_status_open_tickets_block_archiving_and_closed_history_blocks_deletion(): void
    {
        [$department, $division] = $this->office();
        $status = $this->legacyStatus();
        $ticket = $this->ticket($this->issue($department, $division), ['status_id' => $status->id, 'resolved_at' => null]);
        $this->postJson(route('status.archive', $status->id))->assertUnprocessable()->assertSee('open tickets');
        DB::table('tickets')->where('id', $ticket->id)->update(['resolved_at' => now()]);
        $this->postJson(route('status.archive', $status->id))->assertOk();
        $this->deleteJson(route('status.destroy', $status->id))->assertOk()->assertSee('1 tickets');
        $this->assertSame($status->id, $ticket->fresh()->status_id);
    }

    public function test_priority_defaults_must_be_cleared_explicitly_and_history_never_changes(): void
    {
        [$department, $division] = $this->office();
        $priority = Priority::create(['priority_name' => 'Original severity', 'priority_color' => '#123456']);
        $replacement = Priority::create(['priority_name' => 'Future severity', 'priority_color' => '#abcdef']);
        $issue = $this->issue($department, $division, ['priority_id' => $priority->id]);
        $ticket = $this->ticket($issue, ['priority_id' => $priority->id]);
        $this->postJson(route('priority.archive', $priority->id))->assertUnprocessable()->assertSee('1 active issue default(s)');
        $issue->update(['priority_id' => $replacement->id]);
        $this->postJson(route('priority.archive', $priority->id))->assertOk();
        $this->deleteJson(route('priority.destroy', $priority->id))->assertOk()->assertSee('1 tickets');
        $this->assertSame($priority->id, $ticket->fresh()->priority_id);
        $this->assertSame($replacement->id, $issue->fresh()->priority_id);
        try {
            DB::table('priorities')->where('id', $priority->id)->delete();
            $this->fail('Even direct database deletion must preserve priority references.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('FOREIGN KEY', $e->getMessage());
        }
    }

    public function test_issue_reactivation_rechecks_its_default_priority(): void
    {
        [$department, $division] = $this->office();
        $priority = Priority::create(['priority_name' => 'Retired default', 'priority_color' => '#123456']);
        $issue = $this->issue($department, $division, ['priority_id' => $priority->id]);
        $issue->update(['is_active' => false]);
        $priority->update(['is_active' => false]);
        $this->postJson(route('issue.reactivate', $issue->id))->assertUnprocessable()->assertJsonValidationErrors('priority_id');
        $issue->update(['priority_id' => null]);
        $this->postJson(route('issue.reactivate', $issue->id))->assertOk();
        $this->assertTrue($issue->fresh()->is_active);
    }

    public function test_archive_and_reactivate_permissions_are_separate_from_edit_and_delete(): void
    {
        [$department, $division] = $this->office();
        $editor = User::factory()->create(['is_active' => true]);
        $editor->givePermissionTo(['division.view', 'division.update']);
        $this->actingAs($editor, 'web');
        $this->postJson(route('division.archive', $division->id))->assertForbidden();
        $this->putJson(route('division.update', $division->id), ['id' => $division->id,
            'division_name' => $division->division_name, 'department_id' => $department->id, 'is_active' => false])->assertForbidden();
        $editor->givePermissionTo('division.archive');
        $this->actingAs($editor->fresh(), 'web')->postJson(route('division.archive', $division->id))->assertOk();
        $this->postJson(route('division.reactivate', $division->id))->assertForbidden();
        $this->deleteJson(route('division.destroy', $division->id))->assertForbidden();
        $editor->givePermissionTo('division.reactivate');
        $this->actingAs($editor->fresh(), 'web')->postJson(route('division.reactivate', $division->id))->assertOk();
    }

    public function test_audit_records_actor_reason_and_snapshots_and_rolls_back_atomically(): void
    {
        [$department, $division] = $this->office();
        $this->postJson(route('division.archive', $division->id), ['reason' => 'Service retired'])
            ->assertOk();
        $audit = DB::table('master_data_audits')->where('record_type', 'divisions')->where('record_id', $division->id)->latest('id')->first();
        $this->assertSame('archive', $audit->action);
        $this->assertSame(backpack_user()->id, $audit->actor_id);
        $this->assertSame('Service retired', $audit->reason);
        $this->assertTrue((bool) json_decode($audit->before_values, true)['is_active']);
        $this->assertFalse((bool) json_decode($audit->after_values, true)['is_active']);
        $count = DB::table('master_data_audits')->count();
        try {
            DB::transaction(function () use ($division) {
                $division->fresh()->update(['is_active' => true]);
                throw new \RuntimeException('Rollback fixture');
            });
        } catch (\RuntimeException $e) {
            $this->assertSame('Rollback fixture', $e->getMessage());
        }
        $this->assertFalse($division->fresh()->is_active);
        $this->assertSame($count, DB::table('master_data_audits')->count());
        $this->deleteJson(route('division.destroy', $division->id))->assertOk()->assertContent('1');
        $this->assertSame('delete', DB::table('master_data_audits')->where('record_type', 'divisions')->where('record_id', $division->id)->latest('id')->value('action'));
    }

    public function test_department_level_custom_tickets_do_not_require_a_division(): void
    {
        $department = Department::create(['department_name' => 'Department with no divisions']);
        $this->postJson(route('ticket.store'), ['is_custom_issue' => 1, 'custom_issue' => 'Department request',
            'department_id' => $department->id, 'division_id' => null, 'message' => 'Department level concern'])->assertRedirect();
        $ticket = Ticket::where('message', 'Department level concern')->firstOrFail();
        $this->assertSame($department->id, $ticket->department_id);
        $this->assertNull($ticket->division_id);
    }

    public function test_stale_model_cannot_bypass_new_references_when_deleting(): void
    {
        $priority = Priority::create(['priority_name' => 'Stale priority', 'priority_color' => '#123456', 'is_active' => false]);
        $stale = $priority->fresh();
        $priority->update(['is_active' => true]);
        [$department, $division] = $this->office();
        $this->issue($department, $division, ['priority_id' => $priority->id]);
        $this->expectException(ValidationException::class);
        $stale->delete();
    }

    public function test_new_survey_model_writes_recheck_issue_availability(): void
    {
        [$department, $division] = $this->office();
        $issue = $this->issue($department, $division);
        $issue->update(['is_active' => false]);
        $this->expectException(ValidationException::class);
        \App\Models\Survey::create(['user_id' => backpack_user()->id, 'issue_id' => $issue->id,
            'timeliness_rating' => 'Satisfied', 'handling_rating' => 'Satisfied',
            'quality_rating' => 'Satisfied', 'overall_rating' => 'Satisfied']);
    }

    public function test_office_retirement_guards_also_apply_when_model_events_are_suppressed(): void
    {
        [$department, $division] = $this->office();
        User::factory()->create(['department_id' => $department->id, 'division_id' => $division->id, 'is_active' => true]);
        foreach ([$department, $division] as $office) {
            try {
                $office::withoutEvents(fn () => $office->update(['is_active' => false]));
                $this->fail('Disabling model events must not bypass retirement eligibility.');
            } catch (ValidationException $e) {
                $this->assertStringContainsString('1 active user account(s)', $e->getMessage());
            }
            $this->assertTrue($office->fresh()->is_active);
        }
    }
}
