<?php

namespace Tests\Feature;

use App\Models\Issue;
use App\Models\Survey;
use App\Models\Ticket;
use App\Models\User;
use App\Services\SurveyResponseTable;
use App\Services\TicketNotificationService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class IssueArchivingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(TicketNotificationService::class, \Mockery::mock(TicketNotificationService::class)->shouldIgnoreMissing());
        $this->actingAs(User::role('admin')->firstOrFail(), 'web');
    }

    private function issue(string $name = 'Archive fixture issue', bool $active = true): Issue
    {
        $source = Issue::where('department_id', 1)->firstOrFail();
        return Issue::create(['department_id' => $source->department_id, 'division_id' => $source->division_id,
            'priority_id' => $source->priority_id, 'issue_description' => $name, 'is_active' => $active]);
    }

    private function ticket(Issue $issue): Ticket
    {
        return Ticket::create(['user_id' => backpack_user()->id, 'issue_id' => $issue->id,
            'is_custom_issue' => false, 'message' => 'Existing issue archive ticket']);
    }

    private function ticketPayload(Issue $issue): array
    {
        return ['is_custom_issue' => 0, 'issue_id' => $issue->id, 'message' => 'Edited issue archive ticket'];
    }

    private function surveyPayload(Issue $issue): array
    {
        $staff = User::factory()->create(['department_id' => $issue->department_id,
            'division_id' => $issue->division_id, 'is_active' => true]);
        $staff->assignRole('hr_staff');
        return ['submission_id' => (string) Str::uuid(), 'user_id' => $staff->id, 'issue_id' => $issue->id,
            'timeliness_rating' => 'Satisfied', 'handling_rating' => 'Satisfied',
            'quality_rating' => 'Satisfied', 'overall_rating' => 'Very Satisfied'];
    }

    public function test_archive_and_reactivate_preserve_ticket_and_survey_relationships(): void
    {
        $issue = $this->issue();
        $ticket = $this->ticket($issue);
        $survey = Survey::create($this->surveyPayload($issue));

        $this->post(route('issue.archive', $issue->id))->assertRedirect(backpack_url('issue'));
        $this->assertFalse($issue->fresh()->is_active);
        $this->assertSame($issue->id, $ticket->fresh()->issue_id);
        $this->assertSame($issue->id, $survey->fresh()->issue_id);
        $this->assertSame($issue->issue_description, $ticket->fresh()->issue->issue_description);
        $this->assertSame($issue->issue_description, $survey->fresh()->service->issue_description);
        $history = SurveyResponseTable::data(Request::create('/fixture', 'GET', ['length' => 10]));
        $this->assertSame($issue->issue_description, $history['data'][0][2]);

        $this->postJson(route('issue.reactivate', $issue->id))->assertOk();
        $this->assertTrue($issue->fresh()->is_active);
        $this->assertSame($issue->id, $ticket->fresh()->issue_id);
    }

    public function test_ticket_forms_offer_active_and_current_archived_issues_only(): void
    {
        $current = $this->issue('Retained archived issue');
        $ticket = $this->ticket($current);
        $current->update(['is_active' => false]);
        $this->issue('Other archived issue', false);
        $this->issue('Available active issue');

        $this->get(route('ticket.create'))->assertOk()->assertSee('Available active issue')
            ->assertDontSee('Retained archived issue')->assertDontSee('Other archived issue');
        $this->get(route('ticket.edit', $ticket->id))->assertOk()->assertSee('Available active issue')
            ->assertSee('Retained archived issue')->assertDontSee('Other archived issue');
    }

    public function test_new_tickets_reject_archived_issues_and_allow_reactivated_issues(): void
    {
        $issue = $this->issue(active: false);
        $this->postJson(route('ticket.store'), $this->ticketPayload($issue))
            ->assertUnprocessable()->assertJsonValidationErrors('issue_id');
        $this->assertSame(0, Ticket::where('issue_id', $issue->id)->count());
        $this->postJson(route('issue.reactivate', $issue->id))->assertOk();
        $this->postJson(route('ticket.store'), $this->ticketPayload($issue))->assertRedirect();
        $this->assertSame(1, Ticket::where('issue_id', $issue->id)->count());
    }

    public function test_existing_ticket_can_retain_its_archived_issue_but_cannot_select_another(): void
    {
        $issue = $this->issue();
        $ticket = $this->ticket($issue);
        $issue->update(['is_active' => false]);
        $other = $this->issue('Other archive', false);
        $this->putJson(route('ticket.update', $ticket->id), $this->ticketPayload($issue) + ['id' => $ticket->id])->assertRedirect();
        $this->assertSame($issue->id, $ticket->fresh()->issue_id);
        $this->assertSame('Edited issue archive ticket', $ticket->fresh()->message);
        $this->putJson(route('ticket.update', $ticket->id), $this->ticketPayload($other) + ['id' => $ticket->id])
            ->assertUnprocessable()->assertJsonValidationErrors('issue_id');
        $this->assertSame($issue->id, $ticket->fresh()->issue_id);
    }

    public function test_resolving_and_reopening_a_ticket_with_an_archived_issue_still_work(): void
    {
        $issue = $this->issue();
        $ticket = $this->ticket($issue);
        $issue->update(['is_active' => false]);
        $this->put(route('ticket.update', $ticket->id), ['_ticket_action' => 'resolve'])->assertRedirect();
        $this->assertNotNull($ticket->fresh()->resolved_at);
        $this->put(route('ticket.update', $ticket->id), ['_ticket_action' => 'reopen'])->assertRedirect();
        $this->assertNotNull($ticket->fresh()->reopened_at);
        $this->assertSame($issue->id, $ticket->fresh()->issue_id);
    }

    public function test_model_saves_cannot_assign_archived_issues_outside_form_validation(): void
    {
        $active = $this->issue();
        $ticket = $this->ticket($active);
        $archive = $this->issue('Archived model fixture', false);
        foreach (['create', 'update'] as $operation) {
            try {
                if ($operation === 'create') {
                    $this->ticket($archive);
                } else {
                    $ticket->update(['issue_id' => $archive->id]);
                }
                $this->fail('A new assignment to an archived issue must fail.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('issue_id', $e->errors());
            }
        }
        $this->assertSame(0, Ticket::where('issue_id', $archive->id)->count());
        $this->assertSame($active->id, $ticket->fresh()->issue_id);
    }

    public function test_new_surveys_hide_and_reject_archived_services(): void
    {
        $archive = $this->issue('Archived survey service', false);
        $active = $this->issue('Active survey service');
        $this->get(route('survey.form'))->assertOk()->assertSee('Active survey service')->assertDontSee('Archived survey service');
        $payload = $this->surveyPayload($archive);
        $this->postJson(route('survey.submit'), $payload)->assertUnprocessable()->assertJsonValidationErrors('issue_id');
        $this->assertSame(0, Survey::where('issue_id', $archive->id)->count());
        $payload['issue_id'] = $active->id;
        $this->postJson(route('survey.submit'), $payload)->assertOk()->assertJsonPath('saved', true);
    }

    public function test_a_saved_survey_can_retry_after_archiving_but_cannot_create_a_new_submission(): void
    {
        $issue = $this->issue();
        $payload = $this->surveyPayload($issue);
        $this->postJson(route('survey.submit'), $payload)->assertOk()->assertJsonPath('saved', true);
        $this->postJson(route('issue.archive', $issue->id))->assertOk();
        $this->postJson(route('survey.submit'), $payload)->assertOk()->assertJsonPath('saved', true);
        $this->postJson(route('survey.submit'), array_replace($payload, ['submission_id' => (string) Str::uuid()]))
            ->assertUnprocessable()->assertJsonValidationErrors('issue_id');
        $this->postJson(route('survey.submit'), array_replace($payload, ['overall_rating' => 'Dissatisfied']))
            ->assertUnprocessable()->assertJsonValidationErrors('submission_id');
        $this->assertSame(1, Survey::where('issue_id', $issue->id)->count());
    }

    public function test_issue_update_permission_controls_archiving_and_reactivation(): void
    {
        $issue = $this->issue();
        $viewer = User::factory()->create(['is_active' => true]);
        $viewer->givePermissionTo('issue.view');
        $this->actingAs($viewer, 'web');
        $this->postJson(route('issue.archive', $issue->id))->assertForbidden();
        $this->postJson(route('issue.reactivate', $issue->id))->assertForbidden();
        $this->postJson(backpack_url('issue/search'), ['start' => 0, 'length' => 10, 'draw' => 1])
            ->assertOk()->assertDontSee('/archive')->assertDontSee('/reactivate');
        $this->assertTrue($issue->fresh()->is_active);
        $viewer->givePermissionTo('issue.archive');
        $this->actingAs($viewer->fresh(), 'web')->postJson(route('issue.archive', $issue->id))->assertOk();
        $this->assertFalse($issue->fresh()->is_active);
        $this->deleteJson(route('issue.destroy', $issue->id))->assertForbidden();
    }

    public function test_used_issues_remain_protected_from_deletion_but_unused_issues_can_be_deleted(): void
    {
        $usedByTicket = $this->issue('Ticket issue');
        $ticket = $this->ticket($usedByTicket);
        $usedBySurvey = $this->issue('Survey issue');
        $survey = Survey::create($this->surveyPayload($usedBySurvey));
        foreach ([$usedByTicket, $usedBySurvey] as $issue) {
            $this->postJson(route('issue.archive', $issue->id))->assertOk();
            $this->deleteJson(route('issue.destroy', $issue->id))->assertOk()->assertJsonStructure(['error']);
            $this->assertNotNull($issue->fresh());
        }
        $this->assertSame($usedByTicket->id, $ticket->fresh()->issue_id);
        $this->assertSame($usedBySurvey->id, $survey->fresh()->issue_id);
        $unused = $this->issue('Unused archive', false);
        $this->deleteJson(route('issue.destroy', $unused->id))->assertOk()->assertContent('1');
        $this->assertNull($unused->fresh());
    }

    public function test_issue_crud_displays_archive_state_and_validates_the_active_field(): void
    {
        $issue = $this->issue();
        $this->postJson(backpack_url('issue/search'), ['start' => 0, 'length' => 10, 'draw' => 1], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertSee('Archive')->assertSee('method=\"POST\"', false)->assertSee('_token')
            ->assertSee('data-issue-archive-action', false)->assertSee('issue-archive.js', false);
        $payload = ['id' => $issue->id, 'department_id' => $issue->department_id, 'division_id' => $issue->division_id,
            'priority_id' => $issue->priority_id, 'issue_description' => $issue->issue_description, 'is_active' => 0];
        $this->putJson(route('issue.update', $issue->id), $payload)->assertRedirect();
        $this->assertFalse($issue->fresh()->is_active);
        $payload['is_active'] = 'invalid';
        $this->putJson(route('issue.update', $issue->id), $payload)->assertUnprocessable()->assertJsonValidationErrors('is_active');
    }

    public function test_issue_show_page_exposes_reactivation_without_hiding_the_archived_record(): void
    {
        Http::fake(['*' => Http::response('// Theme asset fixture', 200, ['Content-Type' => 'application/javascript'])]);
        $issue = $this->issue(active: false);
        $this->get(route('issue.show', $issue->id))->assertOk()->assertSee('Archived')->assertSee('Reactivate')
            ->assertSee('data-issue-archive-action="reactivate"', false)->assertSee('issue-archive.js', false);
    }

    public function test_archive_routes_require_post_and_return_not_found_for_unknown_issues(): void
    {
        $issue = $this->issue();
        $this->get(route('issue.archive', $issue->id))->assertStatus(405);
        $this->postJson(route('issue.archive', 999999))->assertNotFound();
        $this->postJson(route('issue.reactivate', 999999))->assertNotFound();
    }

    public function test_archive_form_requires_csrf(): void
    {
        $issue = $this->issue();
        $this->app['env'] = 'local';
        $this->withSession(['_token' => 'issue-archive-token'])->post(route('issue.archive', $issue->id))->assertStatus(419);
        $this->assertTrue($issue->fresh()->is_active);
        $this->post(route('issue.archive', $issue->id), ['_token' => 'issue-archive-token'])->assertRedirect();
        $this->assertFalse($issue->fresh()->is_active);
    }

    public function test_migration_round_trip_preserves_records_and_activates_existing_issues(): void
    {
        $issue = $this->issue();
        $ticket = $this->ticket($issue);
        $survey = Survey::create($this->surveyPayload($issue));
        $migration = require database_path('migrations/2026_11_03_100000_add_issue_archiving.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('issues', 'is_active'));
        $migration->up();
        $this->assertTrue($issue->fresh()->is_active);
        $this->assertSame($issue->id, $ticket->fresh()->issue_id);
        $this->assertSame($issue->id, $survey->fresh()->issue_id);
        // The migration must not weaken either foreign key's deletion protection.
        try {
            DB::table('issues')->where('id', $issue->id)->delete();
            $this->fail('Referenced issues must not be deleted.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('FOREIGN KEY', $e->getMessage());
        }
    }
}
