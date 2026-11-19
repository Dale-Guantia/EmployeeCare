<?php

namespace Tests\Feature;

use App\Http\Livewire\TicketChat;
use App\Models\{Issue, Status, Ticket, TicketComment, User};
use App\Notifications\TicketSystemNotification;
use Illuminate\Support\Facades\{DB, Storage};
use Livewire\Livewire;
use Tests\TestCase;

class NotificationDropdownTest extends TestCase
{
    public function test_comment_to_assigned_staff_shows_comment_author_and_current_profile_photo(): void
    {
        $issue = Issue::firstOrFail();
        $owner = User::factory()->create(['is_active' => true, 'avatar_url' => 'avatars/comment-author.png']);
        $owner->assignRole('employee');
        $staff = User::factory()->create(['is_active' => true, 'department_id' => $issue->department_id,
            'division_id' => $issue->division_id, 'notify_ticket_commented' => true]);
        $staff->assignRole('hr_staff');
        Storage::disk('public')->put($owner->avatar_url, 'isolated avatar fixture');
        $ticket = Ticket::withoutEvents(fn () => Ticket::create([
            'reference_id' => 'NOTIFICATION-COMMENT', 'user_id' => $owner->id, 'assigned_to' => $staff->id,
            'issue_id' => $issue->id, 'department_id' => $issue->department_id, 'division_id' => $issue->division_id,
            'status_id' => Status::idByCode(Status::PENDING), 'message' => 'Comment notification fixture',
        ]));

        $this->actingAs($owner, backpack_guard_name());
        Livewire::test(TicketChat::class, ['ticketId' => $ticket->id])
            ->set('comment', 'Please check my request')->call('sendComment')->assertHasNoErrors();

        $notification = $staff->notifications()->firstOrFail();
        $this->assertSame($owner->id, $notification->data['actor_id']);
        $this->assertSame('ticket_commented', $notification->data['type']);
        $this->assertSame(0, $owner->notifications()->count());
        $this->actingAs($staff, backpack_guard_name())->getJson(route('notifications.feed'))->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('notifications.0.actor.name', $owner->name)
            ->assertJsonPath('notifications.0.actor.is_system', false)
            ->assertJsonPath('notifications.0.actor.avatar_url', asset('storage/' . $owner->avatar_url))
            ->assertJsonPath('notifications.0.url', backpack_url('ticket/' . $ticket->id . '/show'));
    }

    public function test_system_and_historical_notifications_use_system_icon_without_guessing_an_actor(): void
    {
        $recipient = User::role('admin')->firstOrFail();
        $recipient->notify(new TicketSystemNotification('Ticket Overdue', 'Deadline reached', null, ['type' => 'ticket_overdue']));
        $this->actingAs($recipient, backpack_guard_name())->getJson(route('notifications.feed'))->assertOk()
            ->assertJsonPath('notifications.0.actor.name', 'Employee Care')
            ->assertJsonPath('notifications.0.actor.is_system', true)
            ->assertJsonPath('notifications.0.actor.avatar_url', asset('assets/icons/icon-192x192.png'));
        $this->assertFileExists(public_path('assets/icons/icon-192x192.png'));
    }

    public function test_missing_photo_or_removed_actor_uses_initials_instead_of_a_broken_image(): void
    {
        $recipient = User::role('admin')->firstOrFail();
        $actor = User::factory()->create(['avatar_url' => 'avatars/missing.png']);
        $recipient->notify(new TicketSystemNotification('Comment', 'A comment was added', null, ['actor_id' => $actor->id]));
        $this->actingAs($recipient, backpack_guard_name())->getJson(route('notifications.feed'))->assertOk()
            ->assertJsonPath('notifications.0.actor.name', $actor->name)
            ->assertJsonPath('notifications.0.actor.avatar_url', null)
            ->assertJsonPath('notifications.0.actor.is_system', false);
        $actor->delete();
        $this->getJson(route('notifications.feed'))->assertOk()
            ->assertJsonPath('notifications.0.actor.name', 'Former user')
            ->assertJsonPath('notifications.0.actor.avatar_url', null)
            ->assertJsonPath('notifications.0.actor.is_system', false);
    }

    public function test_feed_batches_sender_lookup_and_returns_only_ten_owned_notifications(): void
    {
        $recipient = User::role('admin')->firstOrFail();
        foreach (range(1, 12) as $index) {
            $actor = User::factory()->create();
            $recipient->notify(new TicketSystemNotification('Update ' . $index, 'Message', null, ['actor_id' => $actor->id]));
        }
        $other = User::factory()->create();
        $other->notify(new TicketSystemNotification('Private other account update', 'Private message'));
        $actorQueries = [];
        DB::listen(function ($query) use (&$actorQueries) {
            if (str_contains($query->sql, '"users"') && str_contains($query->sql, '"avatar_url"')) $actorQueries[] = $query->sql;
        });
        $this->actingAs($recipient, backpack_guard_name())->getJson(route('notifications.feed'))->assertOk()
            ->assertJsonPath('unread_count', 12)->assertJsonCount(10, 'notifications')
            ->assertDontSee('Private other account update');
        $this->assertCount(1, $actorQueries);
    }

    public function test_read_and_clear_controls_cannot_modify_another_accounts_notifications(): void
    {
        $recipient = User::role('admin')->firstOrFail();
        $other = User::factory()->create();
        $recipient->notify(new TicketSystemNotification('Own update', 'Message'));
        $other->notify(new TicketSystemNotification('Other update', 'Private'));
        $own = $recipient->notifications()->firstOrFail();
        $foreign = $other->notifications()->firstOrFail();
        $this->actingAs($recipient, backpack_guard_name());
        $this->postJson(route('notifications.read', $foreign->id))->assertNotFound();
        $this->postJson(route('notifications.read', $own->id))->assertOk();
        $this->assertNotNull($own->fresh()->read_at);
        $recipient->notify(new TicketSystemNotification('Second update', 'Message'));
        $this->postJson(route('notifications.read_all'))->assertOk();
        $this->assertSame(0, $recipient->unreadNotifications()->count());
        $this->deleteJson(route('notifications.clear_all'))->assertOk();
        $this->assertSame(0, $recipient->notifications()->count());
        $this->assertNull($foreign->fresh()->read_at);
    }

    public function test_guest_cannot_read_the_feed(): void
    {
        $this->getJson(route('notifications.feed'))->assertUnauthorized();
    }

    public function test_legacy_comment_uses_recorded_authors_current_avatar_without_rewriting_notification(): void
    {
        [$recipient, $author, $ticket, $comment, $notification] = $this->legacyCommentFixture();
        Storage::disk('public')->put('avatars/updated-author.webp', 'isolated updated avatar');
        $author->update(['avatar_url' => 'avatars/updated-author.webp']);
        // A later comment by another user must not become this notification's sender.
        TicketComment::create(['ticket_id' => $ticket->id, 'user_id' => $recipient->id, 'comment' => 'Later reply']);

        $this->actingAs($recipient, backpack_guard_name())->getJson(route('notifications.feed'))->assertOk()
            ->assertJsonPath('notifications.0.actor.name', $author->name)
            ->assertJsonPath('notifications.0.actor.avatar_url', asset('storage/avatars/updated-author.webp'))
            ->assertJsonPath('notifications.0.actor.is_system', false);
        $this->assertArrayNotHasKey('actor_id', $notification->fresh()->data);
    }

    public function test_legacy_comment_does_not_guess_between_users_with_the_same_name(): void
    {
        [$recipient, $author, $ticket, $comment] = $this->legacyCommentFixture();
        $namesake = User::factory()->create(['name' => $author->name]);
        TicketComment::create(['ticket_id' => $ticket->id, 'user_id' => $namesake->id,
            'comment' => 'Another reply'])->forceFill(['created_at' => $comment->created_at])->save();
        $this->actingAs($recipient, backpack_guard_name())->getJson(route('notifications.feed'))->assertOk()
            ->assertJsonPath('notifications.0.actor.is_system', true)
            ->assertJsonPath('notifications.0.actor.name', 'Employee Care');
    }

    public function test_legacy_matching_requires_the_ticket_time_and_exact_message_and_never_overrides_explicit_system_sender(): void
    {
        [$recipient, $author, $ticket, $comment, $notification] = $this->legacyCommentFixture();
        $originalData = $notification->data;
        $this->actingAs($recipient, backpack_guard_name());
        foreach ([
            array_merge($originalData, ['actor_id' => null]),
            array_merge($originalData, ['ticket_id' => $ticket->id + 100000]),
            array_merge($originalData, ['message' => 'Another person commented on ticket ' . $ticket->reference_id . '.']),
        ] as $data) {
            $notification->update(['data' => $data]);
            $this->getJson(route('notifications.feed'))->assertOk()->assertJsonPath('notifications.0.actor.is_system', true);
        }
        $notification->update(['data' => $originalData]);
        $comment->forceFill(['created_at' => $notification->created_at->copy()->subMinutes(2)])->save();
        $this->getJson(route('notifications.feed'))->assertOk()->assertJsonPath('notifications.0.actor.is_system', true);
    }

    private function legacyCommentFixture(): array
    {
        $recipient = User::role('admin')->firstOrFail();
        $author = User::factory()->create(['name' => 'Historical Comment Author']);
        $issue = Issue::firstOrFail();
        $ticket = Ticket::withoutEvents(fn () => Ticket::create([
            'reference_id' => 'LEGACY-COMMENT', 'user_id' => $author->id, 'issue_id' => $issue->id,
            'department_id' => $issue->department_id, 'division_id' => $issue->division_id,
            'status_id' => Status::idByCode(Status::PENDING), 'message' => 'Historical ticket fixture',
        ]));
        $time = now()->subDay()->startOfSecond();
        $comment = TicketComment::create(['ticket_id' => $ticket->id, 'user_id' => $author->id,
            'comment' => 'Historical comment']);
        $comment->forceFill(['created_at' => $time])->save();
        $recipient->notify(new TicketSystemNotification('New Ticket Comment',
            "{$author->name} commented on ticket {$ticket->reference_id}.", null,
            ['type' => 'ticket_commented', 'ticket_id' => $ticket->id]));
        $notification = $recipient->notifications()->firstOrFail();
        $notification->update(['created_at' => $time->copy()->addSecond()]);
        return [$recipient, $author, $ticket, $comment, $notification];
    }
}
