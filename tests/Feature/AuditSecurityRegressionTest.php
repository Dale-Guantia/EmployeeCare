<?php

namespace Tests\Feature;

use App\Http\Livewire\TicketChat;
use App\Models\Department;
use App\Models\Division;
use App\Models\Issue;
use App\Models\Priority;
use App\Models\Status;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Services\PolicyIngestService;
use App\Services\TicketAutoAssignmentService;
use App\Services\TicketNotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AuditSecurityRegressionTest extends TestCase
{
    private $department;
    private $division;

    protected function setUp(): void
    {
        parent::setUp();
        $this->department = Department::create(['department_name' => 'Audit department']);
        $this->division = Division::create(['department_id' => $this->department->id, 'division_name' => 'Audit division']);
        $this->app->instance(TicketNotificationService::class, \Mockery::mock(TicketNotificationService::class)->shouldIgnoreMissing());
    }

    private function user($role = 'employee', $active = true): User
    {
        $user = User::create([
            'name' => 'Audit actor ' . uniqid(), 'username' => uniqid('audit_'),
            'email' => uniqid() . '@example.test', 'password' => bcrypt('audit-fixture-password'),
            'department_id' => $this->department->id, 'division_id' => $this->division->id,
            'is_active' => $active,
        ]);
        $user->assignRole($role);
        return $user;
    }

    private function ticket(User $owner, array $extra = []): Ticket
    {
        return Ticket::withoutEvents(function () use ($owner, $extra) {
            return Ticket::create(array_merge([
                'reference_id' => uniqid('AUDIT-'), 'user_id' => $owner->id,
                'department_id' => $owner->department_id, 'division_id' => $owner->division_id,
                'status_id' => Status::idByName('Unassigned'), 'is_custom_issue' => true,
                'custom_issue' => 'Audit issue', 'message' => 'Private message ' . uniqid(),
            ], $extra));
        });
    }

    private function dispatchAs(User $user, $method, $uri, array $data = [])
    {
        $this->app['session']->flush();
        $this->app->forgetInstance('crud');
        Facade::clearResolvedInstance('crud');
        foreach ($this->app['router']->getRoutes() as $route) {
            $route->flushController();
        }
        return $this->actingAs($user, 'web')->call($method, $uri, $data);
    }

    private function updateData(Ticket $ticket, array $extra = []): array
    {
        return array_merge([
            'id' => $ticket->id, 'message' => 'Updated message', 'is_custom_issue' => 1,
            'custom_issue' => 'Audit issue', 'department_id' => $ticket->department_id,
            'division_id' => $ticket->division_id, 'attachments' => $ticket->attachments ?: [],
        ], $extra);
    }

    private function signedChat(User $user, Ticket $ticket): array
    {
        $response = $this->dispatchAs($user, 'GET', '/employee-care/ticket/' . $ticket->id . '/show');
        $response->assertOk();
        $this->assertSame(1, preg_match('/wire:initial-data="([^"]+)"/', $response->getContent(), $match));
        return json_decode(html_entity_decode($match[1], ENT_QUOTES, 'UTF-8'), true);
    }

    private function sendSignedChat(User $user, array $initial, $ticketId, array $before = [])
    {
        return $this->actingAs($user, 'web')->postJson('/livewire/message/ticket-chat', [
            'fingerprint' => $initial['fingerprint'], 'serverMemo' => $initial['serverMemo'],
            'updates' => array_merge($before, [
                ['type' => 'syncInput', 'payload' => ['name' => 'ticketId', 'value' => $ticketId]],
                ['type' => 'syncInput', 'payload' => ['name' => 'comment', 'value' => 'Audit comment']],
                ['type' => 'callMethod', 'payload' => ['id' => 'audit-call', 'method' => 'sendComment', 'params' => []]],
            ]),
        ], ['X-Livewire' => 'true']);
    }

    public function test_signed_livewire_ticket_id_change_cannot_read_or_write_another_ticket()
    {
        $actor = $this->user();
        $own = $this->ticket($actor);
        $other = $this->ticket($this->user());
        TicketComment::create(['ticket_id' => $other->id, 'user_id' => $other->user_id, 'comment' => 'Secret comment marker']);

        $response = $this->sendSignedChat($actor, $this->signedChat($actor, $own), $other->id);
        $response->assertNotFound()->assertDontSee('Secret comment marker');
        $this->assertSame(1, TicketComment::count());
    }

    public function test_owner_can_send_a_comment_through_the_ticket_page_livewire_endpoint()
    {
        $actor = $this->user();
        $ticket = $this->ticket($actor);

        $this->sendSignedChat($actor, $this->signedChat($actor, $ticket), $ticket->id)
            ->assertOk()->assertSee('Audit comment');

        $comment = TicketComment::where('ticket_id', $ticket->id)->firstOrFail();
        $this->assertSame('Audit comment', $comment->comment);
        $this->assertSame((int) $actor->id, (int) $comment->user_id);
        $this->assertSame([], $comment->attachment);
    }

    /** @dataProvider livewireBasePaths */
    public function test_chat_assets_messages_and_signed_uploads_use_the_page_origin(string $basePath)
    {
        $actor = $this->user();
        $ticket = $this->ticket($actor);
        $origin = 'http://127.0.0.1:8765';
        Storage::fake(\Livewire\FileUploadConfiguration::disk());
        $this->app['config']->set('app.url', 'http://unreachable.example.test:9999');
        $this->withServerVariables([
            'SCRIPT_NAME' => $basePath . '/index.php',
            'PHP_SELF' => $basePath . '/index.php',
            'SCRIPT_FILENAME' => public_path('index.php'),
        ])->actingAs($actor, 'web');
        $page = $this->get($origin . $basePath . '/employee-care/ticket/' . $ticket->id . '/show')->assertOk();
        $html = $page->getContent();
        $this->assertSame(1, preg_match('/<script src="([^"]*livewire[^"]*)"/', $html, $asset));
        $this->assertStringStartsWith($basePath . '/livewire/', $asset[1]);
        $this->assertNull(parse_url($asset[1], PHP_URL_HOST));
        $this->assertSame(1, preg_match("/window\\.livewire_app_url = '([^']*)';/", $html, $appUrl));
        $this->assertSame($basePath, $appUrl[1]);
        $this->assertSame(1, preg_match('/wire:initial-data="([^"]+)"/', $html, $match));
        $snapshot = json_decode(html_entity_decode($match[1], ENT_QUOTES, 'UTF-8'), true);
        $endpoint = $origin . $appUrl[1] . '/livewire/message/ticket-chat';
        $send = function (array $updates) use (&$snapshot, $endpoint) {
            $response = $this->postJson($endpoint, [
                'fingerprint' => $snapshot['fingerprint'], 'serverMemo' => $snapshot['serverMemo'],
                'updates' => $updates,
            ], ['X-Livewire' => 'true'])->assertOk();
            // The browser merges partial memo data into the previous snapshot.
            $memo = $response->json('serverMemo');
            $memo['data'] = array_replace($snapshot['serverMemo']['data'], $memo['data'] ?? []);
            $snapshot['serverMemo'] = array_replace($snapshot['serverMemo'], $memo);
            return $response;
        };
        $call = function ($method, array $params) {
            return ['type' => 'callMethod', 'payload' => ['id' => uniqid('chat-'), 'method' => $method, 'params' => $params]];
        };

        $send([
            ['type' => 'syncInput', 'payload' => ['name' => 'comment', 'value' => 'Reply through page origin']],
            $call('sendComment', []),
        ]);
        $this->assertDatabaseHas('ticket_comments', ['ticket_id' => $ticket->id, 'comment' => 'Reply through page origin']);

        $file = UploadedFile::fake()->createWithContent('reply.txt', 'Actual discussion attachment bytes');
        $start = $send([$call('startUpload', ['attachments', [
            ['name' => $file->getClientOriginalName(), 'size' => $file->getSize(), 'type' => $file->getMimeType()],
        ], true])]);
        $event = collect($start->json('effects.emits'))->firstWhere('event', 'upload:generatedSignedUrl');
        $uploadUrl = $event['params'][1];
        $this->assertStringStartsWith($origin . $basePath . '/livewire/upload-file?', $uploadUrl);
        $upload = $this->call('POST', $uploadUrl, [], [], ['files' => [$file]], ['HTTP_ACCEPT' => 'application/json'])->assertOk();
        $send([$call('finishUpload', ['attachments', $upload->json('paths'), true])]);
        $send([$call('sendComment', [])]); // Attachment-only replies are still allowed.

        $comments = TicketComment::where('ticket_id', $ticket->id)->orderBy('id')->get();
        $this->assertCount(2, $comments);
        $this->assertCount(1, $comments->last()->attachment);
        $this->assertSame('Actual discussion attachment bytes', Storage::disk('ticket_attachments')->get($comments->last()->attachment[0]));
        Storage::disk('public')->assertMissing($comments->last()->attachment[0]);
    }

    public function livewireBasePaths(): array
    {
        return ['domain root' => [''], 'subdirectory' => ['/EmployeeCare/public']];
    }

    public function test_signed_livewire_cannot_write_after_account_is_deactivated()
    {
        $actor = $this->user();
        $ticket = $this->ticket($actor);
        $initial = $this->signedChat($actor, $ticket);
        $actor->update(['is_active' => false]);
        $this->sendSignedChat($actor, $initial, $ticket->id)->assertForbidden();
        $this->assertSame(0, TicketComment::count());
    }

    public function test_livewire_reauthorizes_after_a_computed_ticket_was_cached_earlier_in_the_request()
    {
        $actor = $this->user();
        $own = $this->ticket($actor);
        $other = $this->ticket($this->user());
        TicketComment::create(['ticket_id' => $other->id, 'user_id' => $other->user_id, 'comment' => 'Secret comment marker']);
        $response = $this->sendSignedChat($actor, $this->signedChat($actor, $own), $other->id, [
            ['type' => 'callMethod', 'payload' => ['id' => 'prime-cache', 'method' => 'removeAttachment', 'params' => [0]]],
        ]);
        $response->assertNotFound()->assertDontSee('Secret comment marker');
        $this->assertSame(1, TicketComment::count());
    }

    public function test_owner_can_still_comment_and_upload_an_attachment()
    {
        $actor = $this->user();
        $ticket = $this->ticket($actor);
        $this->actingAs($actor, 'web');
        Livewire::test(TicketChat::class, ['ticketId' => $ticket->id])
            ->set('comment', 'Legitimate comment')
            ->set('attachments', [UploadedFile::fake()->create('document.pdf', 1, 'application/pdf')])
            ->call('sendComment')->assertHasNoErrors();
        $comment = TicketComment::where('ticket_id', $ticket->id)->firstOrFail();
        $this->assertEquals($actor->id, $comment->user_id);
        Storage::disk('ticket_attachments')->assertExists($comment->attachment[0]);
        Storage::disk('public')->assertMissing($comment->attachment[0]);
    }

    public function test_inactive_user_cannot_log_in_but_active_user_can()
    {
        $actor = $this->user('employee', false);
        $credentials = ['email' => $actor->email, 'password' => 'audit-fixture-password'];
        $this->post('/employee-care/login', $credentials)->assertRedirect();
        $this->assertGuest('web');
        $actor->update(['is_active' => true]);
        $this->post('/employee-care/login', $credentials)->assertRedirect();
        $this->assertAuthenticatedAs($actor, 'web');
    }

    public function test_deactivated_existing_session_is_logged_out()
    {
        $actor = $this->user('employee', false);
        $this->dispatchAs($actor, 'GET', '/employee-care/submit-ticket')->assertRedirect('/employee-care/login');
        $this->assertGuest('web');
    }

    public function test_inactive_staff_are_excluded_from_auto_assignment_and_manual_assignment()
    {
        $inactive = $this->user('hr_staff', false);
        $active = $this->user('hr_staff');
        $ticket = $this->ticket($this->user());
        $this->assertTrue(app(TicketAutoAssignmentService::class)->assignTicket($ticket));
        $this->assertEquals($active->id, $ticket->fresh()->assigned_to);
        $this->dispatchAs($this->user('admin'), 'PUT', '/employee-care/ticket/' . $ticket->id, [
            '_ticket_action' => 'quick_assign', 'assigned_to' => $inactive->id,
        ])->assertSessionHasErrors('assigned_to');
        $this->assertEquals($active->id, $ticket->fresh()->assigned_to);
    }

    public function test_department_head_without_delete_permission_cannot_delete_even_own_ticket()
    {
        $actor = $this->user('dept_head');
        $ticket = $this->ticket($actor, ['attachments' => ['attachments/keep.txt']]);
        Storage::disk('public')->put('attachments/keep.txt', 'keep');
        $this->dispatchAs($actor, 'DELETE', '/employee-care/ticket/' . $ticket->id)->assertForbidden();
        $this->assertNotNull($ticket->fresh());
        Storage::disk('public')->assertExists('attachments/keep.txt');
    }

    public function test_delete_permission_still_requires_ticket_visibility()
    {
        $actor = $this->user('dept_head');
        $actor->givePermissionTo('ticket.delete');
        $other = $this->ticket($this->user(), ['department_id' => Department::create(['department_name' => 'Other'])->id]);
        $this->dispatchAs($actor, 'DELETE', '/employee-care/ticket/' . $other->id)->assertNotFound();
        $this->assertNotNull($other->fresh());
        $own = $this->ticket($actor);
        $this->dispatchAs($actor, 'DELETE', '/employee-care/ticket/' . $own->id)->assertOk();
        $this->assertNull($own->fresh());
    }

    public function test_rejected_updates_preserve_attachments()
    {
        $actor = $this->user();
        $ticket = $this->ticket($actor, ['attachments' => ['attachments/keep.txt']]);
        Storage::disk('public')->put('attachments/keep.txt', 'keep');
        $this->dispatchAs($actor, 'PUT', '/employee-care/ticket/' . $ticket->id, $this->updateData($ticket, ['attachments' => []]))->assertForbidden();
        Storage::disk('public')->assertExists('attachments/keep.txt');
        $this->dispatchAs($this->user('admin'), 'PUT', '/employee-care/ticket/' . $ticket->id, $this->updateData($ticket, ['attachments' => [], 'message' => '']))->assertSessionHasErrors('message');
        Storage::disk('public')->assertExists('attachments/keep.txt');
        $this->assertSame(['attachments/keep.txt'], $ticket->fresh()->attachments);
    }

    public function test_forged_retained_paths_are_rejected_on_create_and_update()
    {
        $actor = $this->user('admin');
        $ticket = $this->ticket($actor);
        Storage::disk('public')->put('attachments/foreign.txt', 'foreign');
        $data = $this->updateData($ticket, ['attachments' => ['attachments/foreign.txt']]);
        $this->dispatchAs($actor, 'PUT', '/employee-care/ticket/' . $ticket->id, $data)->assertSessionHasErrors('attachments');
        $this->assertEmpty($ticket->fresh()->attachments);
        $before = Ticket::count();
        $this->dispatchAs($actor, 'POST', '/employee-care/ticket', $data)->assertSessionHasErrors('attachments');
        $this->assertSame($before, Ticket::count());
        Storage::disk('public')->assertExists('attachments/foreign.txt');
    }

    public function test_valid_update_retains_existing_uploads_and_removes_only_omitted_files()
    {
        $actor = $this->user('admin');
        $ticket = $this->ticket($actor, ['attachments' => ['attachments/keep.txt', 'attachments/remove.txt']]);
        Storage::disk('public')->put('attachments/keep.txt', 'keep');
        Storage::disk('public')->put('attachments/remove.txt', 'remove');
        $data = $this->updateData($ticket, ['attachments' => [
            'attachments/keep.txt', UploadedFile::fake()->create('new.pdf', 1, 'application/pdf'),
        ]]);
        $this->dispatchAs($actor, 'PUT', '/employee-care/ticket/' . $ticket->id, $data)->assertSessionHasNoErrors()->assertRedirect();
        $paths = $ticket->fresh()->attachments;
        $this->assertCount(2, $paths);
        $this->assertContains('attachments/keep.txt', $paths);
        foreach ($paths as $path) {
            Storage::disk(\App\Services\AttachmentStorage::readableDisk($path))->assertExists($path);
        }
        Storage::disk('public')->assertMissing('attachments/remove.txt');
    }

    public function test_malformed_and_unusually_keyed_attachments_cannot_bypass_validation()
    {
        $actor = $this->user('admin');
        $ticket = $this->ticket($actor);
        foreach ([
            'not-an-array',
            ['nested' => ['attachments/foreign.txt']],
            ['dotted.key' => UploadedFile::fake()->create('malicious.html', 1, 'text/html')],
            [UploadedFile::fake()->create('malicious.php', 1, 'application/pdf')],
        ] as $attachments) {
            $this->dispatchAs($actor, 'PUT', '/employee-care/ticket/' . $ticket->id,
                $this->updateData($ticket, ['attachments' => $attachments]))->assertSessionHasErrors('attachments');
            $this->assertEmpty($ticket->fresh()->attachments);
            $this->assertSame([], Storage::disk('public')->allFiles('attachments'));
        }
    }

    public function test_request_body_cannot_redirect_update_to_another_ticket()
    {
        $actor = $this->user('dept_head');
        $own = $this->ticket($actor);
        $other = $this->ticket($this->user(), ['department_id' => Department::create(['department_name' => 'Other'])->id]);
        $message = $other->message;
        $this->dispatchAs($actor, 'PUT', '/employee-care/ticket/' . $own->id, $this->updateData($own, ['id' => $other->id]))->assertRedirect();
        $this->assertSame($message, $other->fresh()->message);
        $this->assertSame('Updated message', $own->fresh()->message);
    }

    public function test_update_and_delete_rollback_leave_files_and_records_intact()
    {
        $ticket = $this->ticket($this->user(), ['attachments' => ['attachments/keep.txt']]);
        Storage::disk('public')->put('attachments/keep.txt', 'keep');
        foreach (['update', 'delete'] as $operation) {
            try {
                DB::transaction(function () use ($ticket, $operation) {
                    $entry = $ticket->fresh();
                    if ($operation === 'update') {
                        $entry->update(['attachments' => []]);
                    } else {
                        $entry->delete();
                    }
                    Storage::disk('public')->assertExists('attachments/keep.txt');
                    throw new \RuntimeException('rollback fixture');
                });
                $this->fail('The injected failure must propagate.');
            } catch (\RuntimeException $e) {
                $this->assertSame('rollback fixture', $e->getMessage());
            }
            Storage::disk('public')->assertExists('attachments/keep.txt');
            $this->assertSame(['attachments/keep.txt'], $ticket->fresh()->attachments);
        }
        DB::transaction(function () use ($ticket) {
            $ticket->fresh()->delete();
            Storage::disk('public')->assertExists('attachments/keep.txt');
        });
        Storage::disk('public')->assertMissing('attachments/keep.txt');
    }

    public function test_staff_dashboard_only_lists_owned_or_assigned_tickets()
    {
        $actor = $this->user('hr_staff');
        $own = $this->ticket($actor);
        $assigned = $this->ticket($this->user(), ['assigned_to' => $actor->id]);
        $foreign = $this->ticket($this->user());
        $this->dispatchAs($actor, 'GET', '/employee-care/dashboard')
            ->assertOk()->assertDontSee($foreign->message)
            ->assertViewHas('latestTickets', function ($tickets) use ($own, $assigned) {
                return $tickets->pluck('id')->map('intval')->sort()->values()->all() === collect([$own->id, $assigned->id])->sort()->values()->all();
            });
        $this->dispatchAs($this->user('admin'), 'GET', '/employee-care/dashboard')->assertOk()->assertSee($foreign->message);
    }

    public function test_non_debug_500_does_not_render_exception_details()
    {
        config(['app.debug' => false]);
        $response = $this->app->make(\Illuminate\Contracts\Debug\ExceptionHandler::class)
            ->render(Request::create('/audit-error'), new \RuntimeException('AUDIT_INTERNAL_SECRET'));
        $this->assertSame(500, $response->getStatusCode());
        $this->assertStringNotContainsString('AUDIT_INTERNAL_SECRET', $response->getContent());
        $this->assertStringContainsString('An internal server error has occurred', $response->getContent());
    }

    public function test_policy_upload_failure_logs_details_without_flashing_them()
    {
        $service = \Mockery::mock(PolicyIngestService::class);
        $service->shouldReceive('store')->once()->andThrow(new \RuntimeException('AUDIT_INTERNAL_SECRET'));
        $this->app->instance(PolicyIngestService::class, $service);
        $this->dispatchAs($this->user('admin'), 'POST', '/employee-care/hr-policy-documents/upload', [
            'title' => 'Policy', 'category' => 'general', 'pdf_file' => UploadedFile::fake()->create('policy.pdf', 1, 'application/pdf'),
        ])->assertRedirect();
        $this->assertStringNotContainsString('AUDIT_INTERNAL_SECRET', json_encode($this->app['session']->all()));
    }

    public function test_reseeding_preserves_existing_passwords()
    {
        $actor = $this->user();
        $hash = $actor->password;
        $method = new \ReflectionMethod(\Database\Seeders\UsersTableSeeder::class, 'seedUser');
        $method->setAccessible(true);
        $method->invoke(new \Database\Seeders\UsersTableSeeder, [
            'username' => $actor->username, 'name' => 'Updated name', 'email' => $actor->email,
            'password' => bcrypt('different-fixture-password'), 'is_active' => true,
        ], 'employee');
        $this->assertSame($hash, $actor->fresh()->password);
        $this->assertSame('Updated name', $actor->fresh()->name);
    }

    public function test_demo_seeder_refuses_production_before_any_query()
    {
        $this->app->instance('env', 'production');
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            (new \Database\Seeders\UsersTableSeeder)->run();
            $this->fail('Production demonstration seeding must fail.');
        } catch (\LogicException $e) {
            $this->assertSame([], DB::getQueryLog());
        } finally {
            $this->app->instance('env', 'testing');
        }
    }

    public function test_production_database_seeder_does_not_create_or_reset_users()
    {
        $count = User::count();
        $actor = $this->user();
        $hash = $actor->password;
        $this->app->instance('env', 'production');
        try {
            $seeder = new \Database\Seeders\DatabaseSeeder;
            $seeder->setContainer($this->app);
            $seeder->run();
            $this->assertSame($count + 1, User::count());
            $this->assertSame($hash, $actor->fresh()->password);
        } finally {
            $this->app->instance('env', 'testing');
        }
    }

    public function test_issue_create_and_edit_forms_render_compatible_select_fields()
    {
        $actor = $this->user('admin');
        $issue = Issue::create([
            'department_id' => $this->department->id, 'division_id' => $this->division->id,
            'priority_id' => Priority::first()->id, 'issue_description' => 'Audit issue',
        ]);
        foreach (['/employee-care/issue/create', '/employee-care/issue/' . $issue->id . '/edit'] as $uri) {
            $response = $this->dispatchAs($actor, 'GET', $uri)->assertOk();
            foreach (['department_id', 'division_id', 'priority_id'] as $field) {
                $response->assertSee('name="' . $field . '"', false);
            }
        }
    }
}
