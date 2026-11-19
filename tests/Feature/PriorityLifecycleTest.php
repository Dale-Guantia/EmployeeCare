<?php

namespace Tests\Feature;

use App\Models\Issue;
use App\Models\Priority;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketNotificationService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PriorityLifecycleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(TicketNotificationService::class, \Mockery::mock(TicketNotificationService::class)->shouldIgnoreMissing());
        $this->actingAs(User::role('admin')->firstOrFail(), 'web');
    }

    private function priority(bool $active = true): Priority
    {
        return Priority::create(['priority_name' => $active ? 'Active fixture' : 'Archived fixture',
            'priority_color' => '#ff0000', 'is_active' => $active]);
    }

    private function issue(?Priority $priority): Issue
    {
        static $sequence = 0;
        $source = Issue::firstOrFail();
        $issue = Issue::create(['department_id' => $source->department_id, 'division_id' => $source->division_id,
            'issue_description' => 'Priority lifecycle fixture ' . ++$sequence, 'priority_id' => $priority?->is_active ? $priority->id : null]);
        // Legacy history can already retain an archived default. New assignments cannot create it.
        if ($priority && !$priority->is_active) DB::table('issues')->where('id', $issue->id)->update(['priority_id' => $priority->id]);
        return $issue->fresh();
    }

    private function ticket(Issue $issue): Ticket
    {
        return Ticket::create(['user_id' => backpack_user()->id, 'issue_id' => $issue->id,
            'is_custom_issue' => false, 'message' => 'Priority lifecycle fixture']);
    }

    private function issuePayload(Issue $issue, $priorityId): array
    {
        return ['id' => $issue->id, 'department_id' => $issue->department_id, 'division_id' => $issue->division_id,
            'issue_description' => $issue->issue_description, 'priority_id' => $priorityId];
    }

    public function test_archive_and_reactivate_preserve_existing_issue_and_ticket_priorities(): void
    {
        $priority = $this->priority();
        $issue = $this->issue($priority);
        $ticket = $this->ticket($issue);

        $issue->update(['is_active' => false]);
        $this->postJson(route('priority.archive', $priority->id))->assertOk();
        $this->assertFalse($priority->fresh()->is_active);
        $this->assertSame($priority->id, $issue->fresh()->priority_id);
        $this->assertSame($priority->id, $ticket->fresh()->priority_id);
        $this->assertSame('Active fixture', $ticket->fresh()->priority->priority_name);

        $this->postJson(route('priority.reactivate', $priority->id))->assertOk();
        $this->assertTrue($priority->fresh()->is_active);
    }

    public function test_deletion_counts_and_database_restrictions_preserve_all_references(): void
    {
        $priority = $this->priority();
        $issue = $this->issue($priority);
        $ticket = $this->ticket($issue);
        $other = $this->priority(false);
        $otherIssue = $this->issue($other);

        $this->getJson(route('priority.delete-impact', $priority->id))->assertOk()
            ->assertExactJson(['issues' => 1, 'tickets' => 1])->assertHeader('Cache-Control', 'no-store, private');
        try {
            DB::table('priorities')->where('id', $priority->id)->delete();
            $this->fail('The database must refuse deletion of referenced priorities.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('FOREIGN KEY', $e->getMessage());
        }
        $this->assertNotNull($priority->fresh());
        $this->assertSame($priority->id, $issue->fresh()->priority_id);
        $this->assertSame($priority->id, $ticket->fresh()->priority_id);
        $this->assertSame($other->id, $otherIssue->fresh()->priority_id);
        $this->assertFalse($other->fresh()->is_active);
    }

    public function test_archived_unused_priority_can_be_deleted_through_the_crud(): void
    {
        $priority = $this->priority(false);
        $this->deleteJson(route('priority.destroy', $priority->id))->assertOk()->assertContent('1');
        $this->assertNull($priority->fresh());
    }

    public function test_archive_actions_and_counts_require_the_corresponding_permissions(): void
    {
        $priority = $this->priority();
        $viewer = User::factory()->create(['is_active' => true]);
        $viewer->givePermissionTo('priority.view');
        $this->actingAs($viewer, 'web');
        $this->postJson(route('priority.archive', $priority->id))->assertForbidden();
        $this->postJson(route('priority.reactivate', $priority->id))->assertForbidden();
        $this->getJson(route('priority.delete-impact', $priority->id))->assertForbidden();
        $this->deleteJson(route('priority.destroy', $priority->id))->assertForbidden();
        $this->assertTrue($priority->fresh()->is_active);
    }

    public function test_unknown_priority_actions_return_not_found(): void
    {
        $this->postJson(route('priority.archive', 999999))->assertNotFound();
        $this->postJson(route('priority.reactivate', 999999))->assertNotFound();
        $this->getJson(route('priority.delete-impact', 999999))->assertNotFound();
    }

    public function test_issue_form_offers_active_and_current_archived_priorities_only(): void
    {
        $active = $this->priority();
        $archived = $this->priority(false);
        $otherArchived = Priority::create(['priority_name' => 'Other archived fixture', 'priority_color' => '#fff', 'is_active' => false]);
        $issue = $this->issue($archived);

        $this->get(route('issue.create'))->assertOk()->assertSee($active->priority_name)
            ->assertDontSee($archived->priority_name)->assertDontSee($otherArchived->priority_name);
        $this->get(route('issue.edit', $issue->id))->assertOk()->assertSee($active->priority_name)
            ->assertSee($archived->priority_name)->assertDontSee($otherArchived->priority_name);
    }

    public function test_issue_validation_rejects_new_archived_assignments_and_accepts_null(): void
    {
        $archived = $this->priority(false);
        $issue = $this->issue(null);
        $payload = $this->issuePayload($issue, $archived->id);
        unset($payload['id']);
        $this->postJson(route('issue.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('priority_id');
        $payload['priority_id'] = null;
        $payload['issue_description'] = 'New nullable priority fixture';
        $this->postJson(route('issue.store'), $payload)->assertRedirect();
        $this->assertNull(Issue::where('issue_description', $payload['issue_description'])->firstOrFail()->priority_id);
    }

    public function test_existing_issue_can_retain_its_archived_priority_but_cannot_pick_another(): void
    {
        $archived = $this->priority(false);
        $other = Priority::create(['priority_name' => 'Other archive', 'priority_color' => '#fff', 'is_active' => false]);
        $issue = $this->issue($archived);
        $this->putJson(route('issue.update', $issue->id), $this->issuePayload($issue, $archived->id))->assertRedirect();
        $this->assertSame($archived->id, $issue->fresh()->priority_id);
        $this->putJson(route('issue.update', $issue->id), $this->issuePayload($issue, $other->id))
            ->assertUnprocessable()->assertJsonValidationErrors('priority_id');
        $this->putJson(route('issue.update', $issue->id), $this->issuePayload($issue, null))->assertRedirect();
        $this->assertNull($issue->fresh()->priority_id);
    }

    public function test_new_tickets_omit_archived_inherited_priorities_and_resume_after_reactivation(): void
    {
        $priority = $this->priority(false);
        $issue = $this->issue($priority);
        $this->assertNull($this->ticket($issue)->fresh()->priority_id);
        $this->assertSame($priority->id, $issue->fresh()->priority_id);
        $this->postJson(route('ticket.store'), ['is_custom_issue' => 0, 'issue_id' => $issue->id,
            'message' => 'Archived issue priority via browser form'])->assertRedirect();
        $this->assertNull(Ticket::where('message', 'Archived issue priority via browser form')->firstOrFail()->priority_id);
        $this->postJson(route('priority.reactivate', $priority->id))->assertOk();
        $this->assertSame($priority->id, $this->ticket($issue)->fresh()->priority_id);
    }

    public function test_retaining_an_archived_priority_cannot_be_used_to_update_a_different_issue(): void
    {
        $archived = $this->priority(false);
        $issue = $this->issue($archived);
        $other = $this->issue(null);
        $payload = $this->issuePayload($issue, $archived->id);
        $payload['id'] = $other->id;
        $this->putJson(route('issue.update', $issue->id), $payload)->assertUnprocessable()->assertJsonValidationErrors('id');
        $this->assertNull($other->fresh()->priority_id);
    }

    public function test_ticket_validation_allows_retaining_an_archived_priority_but_rejects_new_assignments(): void
    {
        $priority = $this->priority();
        $issue = $this->issue($priority);
        $ticket = $this->ticket($issue);
        $issue->update(['is_active' => false]);
        $priority->update(['is_active' => false]);
        $payload = ['is_custom_issue' => 0, 'issue_id' => $issue->id, 'message' => 'Updated fixture', 'priority_id' => $priority->id];
        $this->postJson(route('ticket.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('priority_id');
        $this->putJson(route('ticket.update', $ticket->id), $payload + ['id' => $ticket->id])->assertRedirect();
        $this->assertSame($priority->id, $ticket->fresh()->priority_id);
        $this->assertSame('Updated fixture', $ticket->fresh()->message);
    }

    public function test_priority_crud_exposes_lifecycle_actions_and_validates_active_state(): void
    {
        $priority = $this->priority();
        $this->postJson(backpack_url('priority/search'), ['start' => 0, 'length' => 10, 'draw' => 1])
            ->assertOk()->assertSee('data-priority-action', false)->assertSee('Delete')->assertDontSee('deleteEntry(this)', false);
        $this->putJson(route('priority.update', $priority->id), ['id' => $priority->id,
            'priority_name' => $priority->priority_name, 'priority_color' => '#ff0000', 'is_active' => 0])->assertRedirect();
        $this->assertFalse($priority->fresh()->is_active);
        $this->postJson(route('priority.store'), ['priority_name' => 'Invalid active fixture',
            'priority_color' => '#fff', 'is_active' => 'invalid'])->assertUnprocessable()->assertJsonValidationErrors('is_active');
    }

    public function test_priority_show_page_loads_the_lifecycle_controls_and_script(): void
    {
        Http::fake([
            '*' => Http::response('// Theme asset fixture', 200, ['Content-Type' => 'application/javascript']),
        ]);
        $priority = $this->priority();
        $this->get(route('priority.show', $priority->id))->assertOk()->assertSee('priority-actions.js', false)
            ->assertSee('data-priority-action', false)->assertSee('Delete')->assertDontSee('deleteEntry(this)', false);
    }

    public function test_migration_round_trip_preserves_existing_rows_and_changes_only_priority_delete_rules(): void
    {
        $priority = $this->priority();
        $issue = $this->issue($priority);
        $ticket = $this->ticket($issue);
        $migration = require database_path('migrations/2026_11_03_090000_add_priority_lifecycle.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('priorities', 'is_active'));
        try {
            DB::table('priorities')->where('id', $priority->id)->delete();
            $this->fail('The old foreign key must still block deletion.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('FOREIGN KEY', $e->getMessage());
        }
        $migration->up();
        $this->assertTrue($priority->fresh()->is_active);
        $this->assertSame($priority->id, $issue->fresh()->priority_id);
        $this->assertSame($priority->id, $ticket->fresh()->priority_id);
        $restriction = require database_path('migrations/2026_11_04_090000_enforce_master_data_lifecycle.php');
        $restriction->up();
        try {
            DB::table('priorities')->where('id', $priority->id)->delete();
            $this->fail('The forward migration must restore history preservation.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('FOREIGN KEY', $e->getMessage());
        }
        $this->assertSame($priority->id, $issue->fresh()->priority_id);
        $this->assertSame($priority->id, $ticket->fresh()->priority_id);
    }
}
