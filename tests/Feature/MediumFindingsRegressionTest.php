<?php

namespace Tests\Feature;

use App\Http\Livewire\TicketChat;
use App\Models\ArtaSurvey;
use App\Models\Department;
use App\Models\Division;
use App\Models\Issue;
use App\Models\Status;
use App\Models\Survey;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Services\AttachmentStorage;
use App\Services\SurveySubmission;
use App\Services\TicketAssignmentRules;
use App\Services\TicketNotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class MediumFindingsRegressionTest extends TestCase
{
    private $department;
    private $division;

    protected function setUp(): void
    {
        parent::setUp();
        $this->department = Department::create(['department_name' => 'Medium department']);
        $this->division = Division::create(['department_id' => $this->department->id, 'division_name' => 'Medium division']);
        $this->app->instance(TicketNotificationService::class, \Mockery::mock(TicketNotificationService::class)->shouldIgnoreMissing());
    }

    private function actor($role = 'employee', $active = true, $department = null, $division = null): User
    {
        $user = User::create(['name' => 'Medium ' . uniqid(), 'username' => uniqid('medium_'), 'email' => uniqid() . '@example.test',
            'password' => 'unused', 'is_active' => $active, 'department_id' => $department ?? $this->department->id,
            'division_id' => $division ?? $this->division->id]);
        $user->assignRole($role);
        return $user;
    }

    private function ticket(User $owner, array $data = []): Ticket
    {
        return Ticket::withoutEvents(function () use ($owner, $data) {
            return Ticket::create(array_merge(['reference_id' => uniqid('MED-'), 'user_id' => $owner->id,
                'department_id' => $owner->department_id, 'division_id' => $owner->division_id,
                'is_custom_issue' => true, 'custom_issue' => 'Medium issue', 'message' => 'Private message',
                'status_id' => Status::idByName('Unassigned')], $data));
        });
    }

    private function dispatch(User $actor, $method, $url, array $data = [])
    {
        $this->app['session']->flush();
        $this->app->forgetInstance('crud'); Facade::clearResolvedInstance('crud');
        foreach ($this->app['router']->getRoutes() as $route) { $route->flushController(); }
        return $this->actingAs($actor, 'web')->call($method, $url, $data);
    }

    private function quickAssign(User $actor, Ticket $ticket, $assignee)
    {
        return $this->dispatch($actor, 'PUT', '/employee-care/ticket/' . $ticket->id,
            ['id' => $ticket->id, '_ticket_action' => 'quick_assign', 'assigned_to' => $assignee]);
    }

    public function test_c8_private_ticket_and_comment_downloads_enforce_ticket_and_comment_scope()
    {
        $owner = $this->actor(); $stranger = $this->actor();
        Storage::disk(AttachmentStorage::DISK)->put('attachments/private.txt', 'private bytes');
        Storage::disk('public')->put('attachments/legacy.txt', 'legacy bytes');
        $ticket = $this->ticket($owner, ['attachments' => ['attachments/private.txt', 'attachments/legacy.txt']]);
        $comment = TicketComment::create(['ticket_id' => $ticket->id, 'user_id' => $owner->id, 'comment' => 'Private', 'attachment' => ['attachments/private.txt']]);
        $base = '/employee-care/ticket/' . $ticket->id;
        $this->dispatch($owner, 'GET', $base . '/attachments/0')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->dispatch($owner, 'GET', $base . '/attachments/1')->assertOk();
        $this->dispatch($owner, 'GET', $base . '/comments/' . $comment->id . '/attachments/0')->assertOk();
        $this->dispatch($stranger, 'GET', $base . '/attachments/0')->assertNotFound();
        $this->dispatch($stranger, 'GET', $base . '/comments/' . $comment->id . '/attachments/0')->assertNotFound();
        $this->dispatch($owner, 'GET', $base . '/attachments/999')->assertNotFound();
        $other = $this->ticket($owner);
        $this->dispatch($owner, 'GET', '/employee-care/ticket/' . $other->id . '/comments/' . $comment->id . '/attachments/0')->assertNotFound();
        backpack_auth()->logout();
        $this->get($base . '/attachments/0')->assertRedirect();
        $this->dispatch($this->actor('employee', false), 'GET', $base . '/attachments/0')->assertRedirect();
    }

    public function test_c8_legacy_migration_preserves_bytes_and_other_public_files()
    {
        Storage::disk('public')->put('attachments/legacy.txt', 'legacy bytes');
        Storage::disk('public')->put('avatars/avatar.txt', 'public avatar');
        $this->artisan('attachments:privatize', ['--dry-run' => true])->assertExitCode(0);
        Storage::disk('public')->assertExists('attachments/legacy.txt');
        $this->artisan('attachments:privatize')->assertExitCode(0);
        $this->assertSame('legacy bytes', Storage::disk(AttachmentStorage::DISK)->get('attachments/legacy.txt'));
        Storage::disk('public')->assertMissing('attachments/legacy.txt');
        Storage::disk('public')->assertExists('avatars/avatar.txt');
        $this->artisan('attachments:privatize')->assertExitCode(0);
        Storage::disk('public')->put('attachments/conflict.txt', 'original');
        Storage::disk(AttachmentStorage::DISK)->put('attachments/conflict.txt', 'different');
        $this->artisan('attachments:privatize')->assertExitCode(1);
        $this->assertSame('original', Storage::disk('public')->get('attachments/conflict.txt'));
    }

    public function test_d2_notification_settings_alias_updates_only_the_authenticated_account()
    {
        $actor = $this->actor(); $other = $this->actor();
        $before = $other->fresh()->getAttributes();
        $this->dispatch($actor, 'POST', '/employee-care/my-account/notification-settings', ['notify_ticket_assigned' => 'on', 'id' => $other->id])->assertRedirect();
        $this->assertTrue((bool) $actor->fresh()->notify_ticket_assigned);
        $this->assertFalse((bool) $actor->fresh()->notify_ticket_commented);
        $this->assertSame($before, $other->fresh()->getAttributes());
    }

    public function test_d4_admin_can_assign_any_eligible_hr_staff_but_not_wrong_roles_inactive_or_inconsistent_locations()
    {
        $admin = $this->actor('admin'); $ticket = $this->ticket($admin);
        $foreign = Department::create(['department_name' => 'Foreign']);
        $foreignDivision = Division::create(['department_id' => $foreign->id, 'division_name' => 'Foreign division']);
        $eligible = $this->actor('hr_staff', true, $foreign->id, $foreignDivision->id);
        $this->quickAssign($admin, $ticket, $eligible->id)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($eligible->id, (int) $ticket->fresh()->assigned_to);
        foreach ([$this->actor()->id, $this->actor('hr_staff', false)->id,
            $this->actor('hr_staff', true, $foreign->id, $this->division->id)->id, 999999] as $invalid) {
            $this->quickAssign($admin, $ticket, $invalid)->assertSessionHasErrors('assigned_to');
            $this->assertSame($eligible->id, (int) $ticket->fresh()->assigned_to);
        }
    }

    public function test_d4_department_and_division_heads_assign_only_within_their_own_scope()
    {
        $deptHead = $this->actor('dept_head'); $divHead = $this->actor('div_head');
        $ticket = $this->ticket($deptHead);
        $secondDivision = Division::create(['department_id' => $this->department->id, 'division_name' => 'Second']);
        $local = $this->actor('hr_staff'); $second = $this->actor('hr_staff', true, $this->department->id, $secondDivision->id);
        $foreign = $this->actor('hr_staff', true, 1, Division::where('department_id', 1)->first()->id);
        $this->quickAssign($deptHead, $ticket, $second->id)->assertSessionHasNoErrors();
        $this->quickAssign($deptHead, $ticket, $foreign->id)->assertSessionHasErrors('assigned_to');
        $this->quickAssign($divHead, $ticket, $local->id)->assertSessionHasNoErrors();
        $this->quickAssign($divHead, $ticket, $second->id)->assertSessionHasErrors('assigned_to');
        $this->assertSame($local->id, (int) $ticket->fresh()->assigned_to);
    }

    public function test_d4_hr_assignment_requires_an_explicit_permission()
    {
        $hr = $this->actor('hr_staff'); $candidate = $this->actor('hr_staff'); $ticket = $this->ticket($hr);
        $this->quickAssign($hr, $ticket, $candidate->id)->assertForbidden();
        $this->assertFalse(TicketAssignmentRules::candidates($hr, $ticket)->exists());
        $hr->givePermissionTo('ticket.assign');
        $this->quickAssign($hr->fresh(), $ticket, $candidate->id)->assertSessionHasNoErrors();
        $this->assertSame($candidate->id, (int) $ticket->fresh()->assigned_to);
    }

    public function test_d5_ticket_input_types_required_fields_and_custom_location_are_validated()
    {
        $admin = $this->actor('admin');
        $valid = ['is_custom_issue' => 1, 'custom_issue' => 'Valid issue', 'message' => 'Valid message',
            'department_id' => $this->department->id, 'division_id' => $this->division->id];
        foreach ([['is_custom_issue' => 'yes'], ['message' => ['invalid']], ['custom_issue' => str_repeat('x', 256)],
            ['message' => str_repeat('x', 65536)], ['division_id' => Division::where('department_id', 1)->first()->id], ['department_id' => 999999]] as $invalid) {
            $response = $this->dispatch($admin, 'POST', '/employee-care/ticket', array_merge($valid, $invalid));
            $response->assertSessionHasErrors(array_key_first($invalid));
        }
        $this->dispatch($admin, 'POST', '/employee-care/ticket', ['message' => 'Missing issue mode'])->assertSessionHasErrors('is_custom_issue');
        $this->dispatch($admin, 'POST', '/employee-care/ticket', ['is_custom_issue' => 0, 'issue_id' => 999999, 'message' => 'Missing issue'])->assertSessionHasErrors('issue_id');
        $this->dispatch($admin, 'POST', '/employee-care/ticket', $valid)->assertSessionHasNoErrors();
        $this->assertSame(1, Ticket::where('message', 'Valid message')->count());
    }

    public function test_d5_issue_and_user_divisions_must_belong_to_the_submitted_department()
    {
        $admin = $this->actor('admin'); $foreignDivision = Division::where('department_id', 1)->first()->id;
        $this->dispatch($admin, 'POST', '/employee-care/issue', ['issue_description' => 'Invalid', 'department_id' => $this->department->id,
            'division_id' => $foreignDivision, 'priority_id' => 1])->assertSessionHasErrors('division_id');
        $this->dispatch($admin, 'POST', '/employee-care/user', ['name' => 'Invalid', 'email' => 'newmedium@example.test', 'username' => 'newmedium',
            'password' => 'valid-password', 'password_confirmation' => 'valid-password', 'department_id' => $this->department->id,
            'division_id' => $foreignDivision, 'roles' => ['employee']])->assertSessionHasErrors('division_id');
    }

    public function test_d6_arta_rejects_empty_or_unknown_answers_and_preserves_optional_fields_and_cc_normalization()
    {
        $id = (string) Str::uuid();
        $this->post('/arta-form', ['submission_id' => $id])->assertSessionHasErrors('sqd0');
        $this->post('/arta-form', ['submission_id' => $id, 'sqd0' => 'Invented'])->assertSessionHasErrors('sqd0');
        $this->post('/arta-form', ['submission_id' => $id, 'sqd0' => str_repeat('x', 10000)])->assertSessionHasErrors('sqd0');
        $this->post('/arta-form', ['submission_id' => $id, 'sqd1' => 'Agree', 'cc1' => 4, 'cc2' => 1, 'cc3' => 1])->assertRedirect();
        $saved = ArtaSurvey::where('submission_id', $id)->firstOrFail();
        $this->assertSame('Agree', $saved->sqd1); $this->assertSame(5, (int) $saved->cc2); $this->assertSame(4, (int) $saved->cc3);
        $this->assertNull($saved->age);
    }

    public function test_d7_only_resolved_tickets_can_reopen_and_other_statuses_clear_resolution_fields()
    {
        $owner = $this->actor(); $ticket = $this->ticket($owner);
        $this->dispatch($owner, 'PUT', '/employee-care/ticket/' . $ticket->id, ['id' => $ticket->id, '_ticket_action' => 'reopen', 'reopen_ticket' => 1])->assertStatus(409);
        $this->assertSame((int) Status::idByName('Unassigned'), (int) $ticket->fresh()->status_id);
        $ticket->update(['status_id' => Status::idByName('Resolved')]);
        $this->assertNotNull($ticket->fresh()->resolved_at);
        $this->dispatch($owner, 'PUT', '/employee-care/ticket/' . $ticket->id, ['id' => $ticket->id, '_ticket_action' => 'reopen', 'reopen_ticket' => 1])->assertRedirect();
        $this->assertNull($ticket->fresh()->resolved_at);
        $ticket->refresh()->update(['status_id' => Status::idByName('Resolved')]);
        $ticket->update(['status_id' => Status::idByName('Pending')]);
        $this->assertNull($ticket->fresh()->resolved_at); $this->assertNull($ticket->fresh()->resolved_by);
    }

    public function test_e2_attachment_columns_store_long_arrays_and_file_names_are_bounded()
    {
        $owner = $this->actor(); $paths = [];
        for ($i = 0; $i < 20; $i++) { $paths[] = 'attachments/' . $i . str_repeat('x', 100) . '.txt'; }
        $ticket = $this->ticket($owner, ['attachments' => $paths]);
        $comment = TicketComment::create(['ticket_id' => $ticket->id, 'user_id' => $owner->id, 'comment' => 'Files', 'attachment' => $paths]);
        $this->assertSame($paths, $ticket->fresh()->attachments); $this->assertSame($paths, $comment->fresh()->attachment);
        foreach (['tickets' => 'attachments', 'ticket_comments' => 'attachment'] as $table => $column) {
            $type = collect(DB::select('PRAGMA table_info(' . $table . ')'))->firstWhere('name', $column)->type;
            $this->assertContains(strtoupper($type), ['TEXT', 'CLOB']);
        }
        $name = AttachmentStorage::randomizedFilename(UploadedFile::fake()->create(str_repeat('x', 240) . '.txt'));
        $this->assertLessThanOrEqual(122, strlen($name));
        $this->actingAs($owner, 'web');
        Livewire::test(TicketChat::class, ['ticketId' => $ticket->id])->set('attachments', array_fill(0, 21, UploadedFile::fake()->create('file.txt', 1, 'text/plain')))
            ->call('sendComment')->assertHasErrors(['attachments' => 'max']);
    }

    public function test_e3_reference_collisions_retry_only_the_unique_reference_constraint()
    {
        $owner = $this->actor(); $existing = $this->ticket($owner);
        CollisionTicket::$references = [$existing->reference_id, 'TKT-ABCDEF']; CollisionTicket::$calls = 0;
        $ticket = new CollisionTicket(['user_id' => $owner->id, 'status_id' => Status::idByName('Unassigned'), 'message' => 'Collision retry']);
        $ticket->save();
        $this->assertSame('TKT-ABCDEF', $ticket->reference_id); $this->assertSame(2, CollisionTicket::$calls);
        CollisionTicket::$references = ['TKT-ABCDEF']; CollisionTicket::$calls = 0;
        try {
            (new CollisionTicket(['user_id' => $owner->id, 'message' => 'Exhausted']))->save();
            $this->fail('Expected collision exhaustion.');
        } catch (\Illuminate\Database\QueryException $e) { $this->assertSame(5, CollisionTicket::$calls); }
        CollisionTicket::$references = ['TKT-FEDCBA']; CollisionTicket::$calls = 0;
        try {
            (new CollisionTicket(['user_id' => 999999, 'message' => 'Invalid FK']))->save();
            $this->fail('Expected FK rejection.');
        } catch (\Illuminate\Database\QueryException $e) { $this->assertSame(1, CollisionTicket::$calls); }
    }

    public function test_e5_idempotency_retains_identical_answers_from_different_clients_and_rejects_id_reuse()
    {
        $data = ['submission_id' => (string) Str::uuid(), 'user_id' => User::first()->id, 'issue_id' => Issue::first()->id,
            'timeliness_rating' => 'Satisfied', 'handling_rating' => 'Satisfied', 'quality_rating' => 'Satisfied', 'overall_rating' => 'Satisfied'];
        $first = SurveySubmission::save(Survey::class, $data);
        $this->assertSame($first->id, SurveySubmission::save(Survey::class, $data)->id);
        $second = SurveySubmission::save(Survey::class, array_merge($data, ['submission_id' => (string) Str::uuid()]));
        $this->assertNotSame($first->id, $second->id); $this->assertSame(2, Survey::count());
        try { SurveySubmission::save(Survey::class, array_merge($data, ['overall_rating' => 'Dissatisfied'])); $this->fail('Expected reused ID rejection.'); }
        catch (\Illuminate\Validation\ValidationException $e) { $this->assertArrayHasKey('submission_id', $e->errors()); }
        $this->assertSame('Satisfied', $first->fresh()->overall_rating);
        $arta = ['submission_id' => (string) Str::uuid(), 'sqd0' => 'Agree'];
        $this->assertSame(SurveySubmission::save(ArtaSurvey::class, $arta)->id, SurveySubmission::save(ArtaSurvey::class, $arta)->id);
    }

    public function test_e5_unique_constraint_race_returns_the_matching_winner_and_does_not_hide_other_database_errors()
    {
        $data = ['submission_id' => strtoupper((string) Str::uuid()), 'sqd0' => 'Agree', 'age' => 20];
        $winner = ArtaSurvey::create(array_merge($data, ['submission_id' => strtolower($data['submission_id'])]));
        RacingArtaSurvey::$uniqueCollision = true;
        $this->assertSame((int) $winner->id, (int) SurveySubmission::save(RacingArtaSurvey::class, $data)->id);
        try {
            SurveySubmission::save(RacingArtaSurvey::class, ['submission_id' => $data['submission_id'], 'sqd0' => 'Agree']);
            $this->fail('Removing a previously recorded answer must not reuse the ID.');
        } catch (\Illuminate\Validation\ValidationException $e) { $this->assertArrayHasKey('submission_id', $e->errors()); }
        RacingArtaSurvey::$uniqueCollision = false;
        $this->expectException(\Illuminate\Database\QueryException::class);
        SurveySubmission::save(RacingArtaSurvey::class, $data);
    }

    public function test_d4_full_ticket_update_uses_the_same_assignment_validator_and_preserves_a_valid_form()
    {
        $admin = $this->actor('admin'); $ticket = $this->ticket($admin); $eligible = $this->actor('hr_staff');
        $data = ['id' => $ticket->id, 'is_custom_issue' => 1, 'custom_issue' => $ticket->custom_issue, 'message' => $ticket->message,
            'department_id' => $ticket->department_id, 'division_id' => $ticket->division_id, 'assigned_to' => $this->actor()->id];
        $this->dispatch($admin, 'PUT', '/employee-care/ticket/' . $ticket->id, $data)->assertSessionHasErrors('assigned_to');
        $this->assertNull($ticket->fresh()->assigned_to);
        $data['assigned_to'] = $eligible->id;
        $this->dispatch($admin, 'PUT', '/employee-care/ticket/' . $ticket->id, $data)->assertSessionHasNoErrors();
        $this->assertSame($eligible->id, (int) $ticket->fresh()->assigned_to);
    }

    public function test_c11_public_survey_posts_are_throttled_without_disabling_an_ordinary_kiosk_session()
    {
        $this->withCookie(config('session.cookie'), str_repeat('a', 40));
        for ($i = 0; $i < 30; $i++) {
            $this->post('/arta-form', ['submission_id' => (string) Str::uuid(), 'sqd0' => 'Agree'])->assertRedirect();
        }
        $this->post('/arta-form', ['submission_id' => (string) Str::uuid(), 'sqd0' => 'Agree'])->assertStatus(429);
        $this->assertSame(30, ArtaSurvey::count());
        $this->post('/survey', [])->assertStatus(429);
    }

    public function test_j1_former_heads_do_not_receive_comments_after_transfer_but_current_participants_do()
    {
        Notification::fake();
        $owner = $this->actor(); $former = $this->actor('dept_head');
        $foreignDivision = Division::where('department_id', 1)->first();
        $current = $this->actor('dept_head', true, 1, $foreignDivision->id);
        $assignee = $this->actor('hr_staff', true, 1, $foreignDivision->id);
        $ticket = $this->ticket($owner, ['department_id' => 1, 'division_id' => $foreignDivision->id, 'assigned_to' => $assignee->id]);
        foreach ([$former, $current] as $person) {
            TicketComment::create(['ticket_id' => $ticket->id, 'user_id' => $person->id, 'comment' => 'Participant']);
        }
        (new TicketNotificationService)->notifyTicketCommented($ticket, $assignee);
        Notification::assertNotSentTo($former, \App\Notifications\TicketSystemNotification::class);
        Notification::assertNotSentTo($assignee, \App\Notifications\TicketSystemNotification::class);
        Notification::assertSentTo($owner, \App\Notifications\TicketSystemNotification::class);
        Notification::assertSentTo($current, \App\Notifications\TicketSystemNotification::class);
    }

    public function test_f2_notifications_select_candidates_and_mark_all_read_in_one_scoped_update()
    {
        Notification::fake(); $owner = $this->actor(); $head = $this->actor('dept_head');
        $ticket = $this->ticket($owner); $unrelated = $this->actor('hr_staff');
        DB::enableQueryLog(); DB::flushQueryLog();
        (new TicketNotificationService)->notifyTicketCreated($ticket, $owner);
        $userQueries = collect(DB::getQueryLog())->pluck('query')->filter(function ($sql) { return strpos($sql, 'from "users"') !== false; });
        $this->assertNotEmpty($userQueries);
        foreach ($userQueries as $sql) { $this->assertStringContainsString('where', $sql); }
        Notification::assertNotSentTo($unrelated, \App\Notifications\TicketSystemNotification::class);
        Notification::assertSentTo($head, \App\Notifications\TicketSystemNotification::class);
        foreach ([$owner, $unrelated] as $user) {
            for ($i = 0; $i < 12; $i++) {
                $user->notifications()->create(['id' => (string) Str::uuid(), 'type' => 'fixture', 'data' => []]);
            }
        }
        DB::flushQueryLog();
        $this->actingAs($owner, 'web'); (new \App\Http\Controllers\Admin\NotificationController)->markAllAsRead();
        $updates = collect(DB::getQueryLog())->pluck('query')->filter(function ($sql) { return strpos($sql, 'update "notifications"') === 0; });
        $this->assertCount(1, $updates);
        $this->assertSame(0, $owner->unreadNotifications()->count()); $this->assertSame(12, $unrelated->unreadNotifications()->count());
        DB::disableQueryLog();
    }

    public function test_f2_candidate_queries_preserve_the_existing_event_recipient_matrix()
    {
        $owner = $this->actor(); $staff = $this->actor('hr_staff');
        foreach (['admin', 'dept_head', 'div_head', 'employee', 'hr_staff'] as $role) {
            $this->actor($role);
            $this->actor($role, true, 1, Division::where('department_id', 1)->first()->id);
        }
        $nullHead = $this->actor('dept_head'); $nullHead->update(['department_id' => null, 'division_id' => null]);
        $multi = $this->actor('dept_head'); $multi->assignRole('admin');
        $service = new TicketNotificationService;
        $candidateMethod = new \ReflectionMethod($service, 'candidates'); $candidateMethod->setAccessible(true);
        foreach ([[$owner->department_id, $owner->division_id], [null, null]] as $location) {
            $ticket = $this->ticket($owner, ['department_id' => $location[0], 'division_id' => $location[1], 'assigned_to' => $staff->id]);
            foreach (['Created', 'Assigned', 'StatusChanged'] as $event) {
                $filter = new \ReflectionMethod($service, 'shouldReceiveTicket' . $event); $filter->setAccessible(true);
                $eligible = function ($user) use ($filter, $service, $ticket) { return $filter->invoke($service, $user, $ticket); };
                $original = User::where('is_active', true)->with('roles')->get()->filter($eligible)->pluck('id')->map(function ($id) { return (int) $id; })->sort()->values()->all();
                $optimized = $candidateMethod->invoke($service, $ticket, $event === 'Created')->filter($eligible)->pluck('id')->map(function ($id) { return (int) $id; })->sort()->values()->all();
                $this->assertSame($original, $optimized);
            }
        }
    }

    public function test_f1_server_side_response_tables_preserve_full_history_search_order_and_permissions()
    {
        $admin = $this->actor('admin');
        for ($i = 0; $i < 27; $i++) { ArtaSurvey::create(['service_availed' => sprintf('Service %02d', $i), 'sqd0' => 'Agree']); }
        $this->dispatch($admin, 'GET', '/employee-care/arta-survey-reports/responses',
            ['draw' => 2, 'start' => 10, 'length' => 10, 'order' => [['column' => 2, 'dir' => 'asc']]])
            ->assertOk()->assertJsonPath('recordsTotal', 27)->assertJsonCount(10, 'data')->assertJsonPath('data.0.2', 'Service 10');
        $this->dispatch($admin, 'GET', '/employee-care/arta-survey-reports/responses',
            ['search' => ['value' => '"Service 26"']])->assertJsonPath('recordsFiltered', 1)->assertJsonPath('data.0.2', 'Service 26');
        $this->dispatch($admin, 'GET', '/employee-care/arta-survey-reports/responses',
            ['search' => ['value' => 'Service%']])->assertJsonPath('recordsFiltered', 0);
        $this->dispatch($admin, 'GET', '/employee-care/arta-survey-reports/responses',
            ['search' => ['value' => 'N/A']])->assertJsonPath('recordsFiltered', 27);
        $this->dispatch($admin, 'GET', '/employee-care/arta-survey-reports/responses', ['length' => 101])->assertSessionHasErrors('length');
        $this->dispatch($this->actor(), 'GET', '/employee-care/arta-survey-reports/responses')->assertForbidden();
        $this->app['config']->set('reports.arta_pdf_max_rows', 20);
        $this->dispatch($admin, 'POST', '/employee-care/arta-survey-reports/download-pdf',
            ['start_date' => now()->toDateString(), 'end_date' => now()->toDateString()])->assertSessionHas('error');
    }

    public function test_j2_partial_storage_failure_is_visible_and_removes_only_staged_files()
    {
        $disk = Storage::disk(AttachmentStorage::DISK);
        $disk->put('attachments/retained.txt', 'retained');
        $broken = \Mockery::mock($disk)->makePartial();
        $calls = 0;
        $broken->shouldReceive('put')->andReturnUsing(function ($path, $contents, $options = []) use ($disk, &$calls) {
            $disk->put($path, 'partially written');
            return ++$calls === 1;
        });
        Storage::set(AttachmentStorage::DISK, $broken);
        try {
            AttachmentStorage::storeFiles([UploadedFile::fake()->create('one.txt'), UploadedFile::fake()->create('two.txt')]);
            $this->fail('Expected visible storage failure.');
        } catch (\Illuminate\Validation\ValidationException $e) { $this->assertArrayHasKey('attachments', $e->errors()); }
        $this->assertSame(['attachments/retained.txt'], $disk->allFiles('attachments'));
    }

    public function test_j2_ticket_persistence_failure_removes_uploads_and_retains_existing_files()
    {
        $owner = $this->actor(); Storage::disk(AttachmentStorage::DISK)->put('attachments/retained.txt', 'retained');
        $request = Request::create('/test', 'POST', [], [], ['attachments' => [UploadedFile::fake()->create('new.txt', 1, 'text/plain')]]);
        $this->app->instance('request', $request);
        $ticket = new Ticket(['user_id' => 999999, 'message' => 'Invalid FK', 'attachments' => []]);
        $this->assertCount(2, Storage::disk(AttachmentStorage::DISK)->allFiles('attachments'));
        try { $ticket->save(); $this->fail('Expected FK rejection.'); }
        catch (\Illuminate\Database\QueryException $e) { $this->assertFalse($ticket->exists); }
        $this->assertSame(['attachments/retained.txt'], Storage::disk(AttachmentStorage::DISK)->allFiles('attachments'));
    }

    public function test_j2_comment_persistence_failure_rolls_back_row_and_upload_and_retains_input()
    {
        $owner = $this->actor(); $ticket = $this->ticket($owner); $this->actingAs($owner, 'web');
        $mock = \Mockery::mock(TicketNotificationService::class);
        $mock->shouldReceive('notifyTicketCommented')->once()->andThrow(new \RuntimeException('Injected persistence failure'));
        $this->app->instance(TicketNotificationService::class, $mock);
        Livewire::test(TicketChat::class, ['ticketId' => $ticket->id])->set('comment', 'Retain my comment')
            ->set('attachments', [UploadedFile::fake()->create('comment.txt', 1, 'text/plain')])->call('sendComment')
            ->assertHasErrors('attachments')->assertSet('comment', 'Retain my comment');
        $this->assertSame(0, TicketComment::count());
        $this->assertSame([], Storage::disk(AttachmentStorage::DISK)->allFiles('attachments'));
    }

    public function test_j2_outer_and_nested_rollbacks_cleanup_new_files_but_committed_files_survive()
    {
        $owner = $this->actor();
        $this->app->instance('request', Request::create('/test', 'POST', [], [], ['attachments' => [UploadedFile::fake()->create('new.txt', 1, 'text/plain')]]));
        DB::beginTransaction();
        $first = new Ticket(['user_id' => $owner->id, 'message' => 'First upload', 'attachments' => []]);
        $first->save();
        $firstPath = $first->attachments[0];
        DB::beginTransaction();
        $second = new Ticket(['user_id' => $owner->id, 'message' => 'Second upload', 'attachments' => []]);
        $second->save();
        DB::commit(); // The second upload now belongs to the outer transaction.
        DB::beginTransaction(); DB::rollBack(); // An unrelated savepoint must not delete it.
        Storage::disk(AttachmentStorage::DISK)->assertExists($second->attachments[0]);
        DB::rollBack();
        Storage::disk(AttachmentStorage::DISK)->assertMissing($firstPath);
        Storage::disk(AttachmentStorage::DISK)->assertMissing($second->attachments[0]);
        $this->assertSame(0, Ticket::whereIn('id', [$first->id, $second->id])->count());
        DB::beginTransaction();
        $committed = new Ticket(['user_id' => $owner->id, 'message' => 'Committed upload', 'attachments' => []]);
        $committed->save(); DB::commit();
        DB::beginTransaction(); DB::rollBack();
        Storage::disk(AttachmentStorage::DISK)->assertExists($committed->attachments[0]);
    }

    public function test_j2_exception_after_commit_does_not_delete_a_committed_ticket_upload()
    {
        $owner = $this->actor();
        $this->app->instance('request', Request::create('/test', 'POST', [], [], ['attachments' => [UploadedFile::fake()->create('committed.txt', 1, 'text/plain')]]));
        $mock = \Mockery::mock(TicketNotificationService::class);
        $mock->shouldReceive('notifyTicketCreated')->once()->andReturnUsing(function () {
            DB::afterCommit(function () { throw new \RuntimeException('Failure after durable commit'); });
        });
        $this->app->instance(TicketNotificationService::class, $mock);
        try {
            (new Ticket(['user_id' => $owner->id, 'message' => 'Durably committed', 'attachments' => []]))->save();
            $this->fail('Expected after-commit failure.');
        } catch (\RuntimeException $e) { $this->assertSame('Failure after durable commit', $e->getMessage()); }
        $saved = Ticket::where('message', 'Durably committed')->firstOrFail();
        Storage::disk(AttachmentStorage::DISK)->assertExists($saved->attachments[0]);
    }

    public function test_j2_unverifiable_commit_state_preserves_files_for_reconciliation()
    {
        Storage::disk(AttachmentStorage::DISK)->put('attachments/uncertain.txt', 'possibly committed');
        AttachmentStorage::discardUnreferenced(['attachments/uncertain.txt'], function () { throw new \RuntimeException('Database unavailable'); });
        Storage::disk(AttachmentStorage::DISK)->assertExists('attachments/uncertain.txt');
        AttachmentStorage::discardUnreferenced(['attachments/uncertain.txt'], function () { return []; });
        Storage::disk(AttachmentStorage::DISK)->assertMissing('attachments/uncertain.txt');
    }
}

class CollisionTicket extends Ticket
{
    protected $table = 'tickets';
    public static $references = [];
    public static $calls = 0;
    protected function newReference(): string
    {
        return self::$references[min(self::$calls++, count(self::$references) - 1)];
    }
}

class RacingArtaSurvey extends ArtaSurvey
{
    protected $table = 'arta_surveys';
    public static $uniqueCollision = true;
    public function getAttribute($key)
    {
        $value = parent::getAttribute($key);
        return $key === 'submission_id' && $value ? strtoupper($value) : $value;
    }
    public static function firstOrCreate(array $attributes, array $values = [])
    {
        $previous = new \PDOException(self::$uniqueCollision ? 'UNIQUE constraint failed: arta_surveys.submission_id' : 'Injected unrelated SQL failure');
        $previous->errorInfo = ['HY000', self::$uniqueCollision ? 19 : 1];
        throw new \Illuminate\Database\QueryException('insert into arta_surveys', [], $previous);
    }
}
