<?php

namespace Tests\Feature;

use App\Http\Livewire\TicketChat;
use App\Models\Issue;
use App\Models\Department;
use App\Models\Division;
use App\Models\Status;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketNotificationService;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationSettingsTest extends TestCase
{
    private const SETTINGS = [
        'notify_ticket_created',
        'notify_ticket_assigned',
        'notify_ticket_status_changed',
        'notify_ticket_commented',
    ];

    private function makeUser(string $role): User
    {
        $issue = Issue::firstOrFail();
        $user = User::factory()->create([
            'username' => uniqid('notification_'),
            'is_active' => true,
            'department_id' => $issue->department_id,
            'division_id' => $issue->division_id,
        ]);
        $user->assignRole($role);

        return $user->refresh();
    }

    private function makeTicket(User $owner): Ticket
    {
        return Ticket::create([
            'user_id' => $owner->id,
            'issue_id' => Issue::firstOrFail()->id,
            'status_id' => Status::idByName('Unassigned'),
            'message' => 'Notification settings integration test',
        ]);
    }

    private function saveSettings(User $user, array $enabled): void
    {
        $this->actingAs($user, backpack_guard_name());
        // Browsers submit checked boxes as "on" and omit unchecked boxes.
        $this->from(route('backpack.account.info'))
            ->post(route('backpack.account.notifications'), array_fill_keys($enabled, 'on'))
            ->assertRedirect(route('backpack.account.info'))
            ->assertSessionHas('success', 'Notification settings updated successfully.');
        $user->refresh();
    }

    private function assertRenderedSettings(User $user): void
    {
        $response = $this->get(route('backpack.account.info'))->assertOk();
        $response->assertSee('Notification Settings');
        $response->assertSee('action="' . route('backpack.account.notifications') . '"', false);
        foreach (self::SETTINGS as $key) {
            if ($key === 'notify_ticket_created' && !$user->hasAnyRole(['admin', 'dept_head', 'div_head'])) {
                $response->assertDontSee('id="' . $key . '"', false);
                continue;
            }
            $this->assertSame(1, preg_match('/<input\s[^>]*id="' . $key . '"[^>]*>/s', $response->getContent(), $matches));
            $this->assertSame($user->wantsNotification($key), strpos($matches[0], 'checked') !== false, $key);
        }
    }

    public function test_all_sixteen_switch_combinations_persist_and_render_after_reload(): void
    {
        $user = $this->makeUser('admin');
        $other = $this->makeUser('employee');
        $originalOther = $other->getAttributes();
        $originalProfile = $user->only(['name', 'username', 'email', 'password']);

        for ($mask = 0; $mask < 16; $mask++) {
            $enabled = [];
            foreach (self::SETTINGS as $index => $key) {
                if ($mask & (1 << $index)) {
                    $enabled[] = $key;
                }
            }
            $this->saveSettings($user, $enabled);
            foreach (self::SETTINGS as $key) {
                $this->assertSame(in_array($key, $enabled, true), $user->wantsNotification($key));
            }
            $this->assertRenderedSettings($user);
        }

        $this->assertSame($originalProfile, $user->only(array_keys($originalProfile)));
        $this->assertSame($originalOther, $other->fresh()->getAttributes());
    }

    public function test_all_five_roles_can_save_their_visible_settings(): void
    {
        foreach (['admin', 'dept_head', 'div_head', 'hr_staff', 'employee'] as $role) {
            $user = $this->makeUser($role);
            $visible = $user->hasAnyRole(['admin', 'dept_head', 'div_head'])
                ? self::SETTINGS : array_slice(self::SETTINGS, 1);
            $this->saveSettings($user, []);
            $this->assertRenderedSettings($user);
            $this->saveSettings($user, $visible);
            $this->assertRenderedSettings($user);
        }
    }

    public function test_guest_cannot_save_preferences(): void
    {
        $user = $this->makeUser('employee');
        $before = $user->getAttributes();
        $this->post(route('backpack.account.notifications'), [])->assertRedirect(backpack_url('login'));
        $this->assertSame($before, $user->fresh()->getAttributes());
    }

    public function test_inactive_user_cannot_save_preferences(): void
    {
        $user = $this->makeUser('employee');
        $user->update(['is_active' => false]);
        $before = $user->only(self::SETTINGS);
        $this->actingAs($user, backpack_guard_name());
        $this->post(route('backpack.account.notifications'), [])->assertRedirect(backpack_url('login'));
        $this->assertSame($before, $user->fresh()->only(self::SETTINGS));
    }

    /** @dataProvider ticketEvents */
    public function test_saved_switch_controls_real_event_delivery(string $event, string $key): void
    {
        $recipient = $this->makeUser($event === 'created' ? 'admin' : 'employee');
        $actor = $this->makeUser('admin');
        foreach ([false, true] as $enabled) {
            // Enable only the tested setting, to catch accidental coupling between switches.
            $this->saveSettings($recipient, $enabled ? [$key] : []);
            $this->actingAs($actor, backpack_guard_name());
            $recipient->notifications()->delete();
            $ticket = $this->makeTicket($event === 'created' ? $actor : $recipient);
            if ($event !== 'created') {
                // Auto-assignment during creation may itself send assignment/status notifications.
                $recipient->notifications()->delete();
            }
            if ($event === 'assigned') {
                $ticket->update(['assigned_to' => $actor->id]);
            } elseif ($event === 'status_changed') {
                $ticket = $ticket->fresh();
                $ticket->update(['status_id' => Status::idByName('Reopened')]);
            } elseif ($event === 'commented') {
                Livewire::test(TicketChat::class, ['ticketId' => $ticket->id])
                    ->set('comment', 'Testing notification preferences')
                    ->call('sendComment')
                    ->assertHasNoErrors();
                $this->assertDatabaseHas('ticket_comments', ['ticket_id' => $ticket->id, 'user_id' => $actor->id]);
            }

            $notifications = $recipient->notifications()->get();
            $this->assertCount($enabled ? 1 : 0, $notifications);
            if ($enabled) {
                $data = $notifications->first()->data;
                $this->assertSame('ticket_' . $event, $data['type']);
                $this->assertSame($ticket->id, $data['ticket_id']);
                $this->assertSame(backpack_url('ticket/' . $ticket->id . '/show'), $data['url']);
                if ($event === 'status_changed') {
                    $this->assertStringContainsString('Reopened', $data['message']);
                }
                $this->actingAs($recipient, backpack_guard_name());
                $this->getJson(route('notifications.feed'))->assertOk()
                    ->assertJsonPath('unread_count', 1)
                    ->assertJsonPath('notifications.0.message', $data['message']);
            }
            $this->assertSame(0, $actor->notifications()->where('data->ticket_id', $ticket->id)->count());
        }
    }

    public function ticketEvents(): array
    {
        return [
            'created' => ['created', 'notify_ticket_created'],
            'assigned' => ['assigned', 'notify_ticket_assigned'],
            'status changed' => ['status_changed', 'notify_ticket_status_changed'],
            'commented' => ['commented', 'notify_ticket_commented'],
        ];
    }

    public function test_creation_preferences_do_not_override_role_or_department_scope(): void
    {
        $actor = $this->makeUser('employee');
        $users = [];
        foreach (['admin', 'dept_head', 'div_head', 'hr_staff', 'employee'] as $role) {
            $users[$role] = $this->makeUser($role);
        }
        $outside = $this->makeUser('dept_head');
        $outside->update(['department_id' => null]);
        $this->actingAs($actor, backpack_guard_name());
        $ticket = $this->makeTicket($actor);
        foreach ($users as $role => $user) {
            $this->assertSame(in_array($role, ['admin', 'dept_head', 'div_head'], true) ? 1 : 0,
                $user->notifications()->where('data->type', 'ticket_created')->count(), $role);
        }
        $this->assertSame(0, $outside->notifications()->where('data->type', 'ticket_created')->count());

        foreach ($users as $user) {
            $user->notifications()->delete();
            $user->update(['notify_ticket_created' => false]);
        }
        app(TicketNotificationService::class)->notifyTicketCreated($ticket, $actor);
        foreach ($users as $user) {
            $this->assertSame(0, $user->notifications()->count());
        }
    }

    public function test_status_notification_reports_new_status_when_relation_was_already_loaded(): void
    {
        $recipient = $this->makeUser('employee');
        $actor = $this->makeUser('admin');
        $this->saveSettings($recipient, ['notify_ticket_status_changed']);
        $this->actingAs($actor, backpack_guard_name());
        $ticket = $this->makeTicket($recipient)->fresh(['status']);
        $recipient->notifications()->delete();

        $ticket->update(['status_id' => Status::idByName('Reopened')]);

        $this->assertSame('Reopened', $ticket->fresh()->status->status_name);
        $notifications = $recipient->notifications()->get();
        $this->assertCount(1, $notifications);
        $this->assertStringContainsString('Reopened', $notifications->first()->data['message']);
    }

    public function test_initial_auto_assignment_does_not_send_a_status_update_but_later_updates_do(): void
    {
        $department = Department::create(['department_name' => 'Creation notification regression']);
        $division = Division::create(['department_id' => $department->id, 'division_name' => 'Creation notification division']);
        $issue = Issue::create(['department_id' => $department->id, 'division_id' => $division->id,
            'priority_id' => Issue::firstOrFail()->priority_id, 'issue_description' => 'Creation notification issue']);
        $owner = $this->makeUser('employee');
        $staff = $this->makeUser('hr_staff');
        $head = $this->makeUser('dept_head');
        foreach ([$owner, $staff, $head] as $user) {
            $user->update(['department_id' => $department->id, 'division_id' => $division->id]);
        }
        $this->actingAs($owner, backpack_guard_name());
        $ticket = Ticket::create(['user_id' => $owner->id, 'issue_id' => $issue->id,
            'status_id' => Status::idByName('Unassigned'), 'message' => 'Automatically assigned at creation']);
        $this->assertSame($staff->id, (int) $ticket->fresh()->assigned_to);
        $this->assertSame((int) Status::idByName('Pending'), (int) $ticket->fresh()->status_id);
        $this->assertSame(1, $head->notifications()->where('data->type', 'ticket_created')->count());
        foreach ([$staff, $head] as $recipient) {
            $this->assertSame(1, $recipient->notifications()->where('data->type', 'ticket_assigned')->count());
            $this->assertSame(0, $recipient->notifications()->where('data->type', 'ticket_status_changed')->count());
        }
        // Laravel keeps this flag set on the same model even after the creation call returns.
        $this->assertTrue($ticket->wasRecentlyCreated);
        $ticket->update(['status_id' => Status::idByName('Resolved')]);
        foreach ([$staff, $head] as $recipient) {
            $updates = $recipient->notifications()->where('data->type', 'ticket_status_changed')->get();
            $this->assertCount(1, $updates);
            $this->assertStringContainsString('Resolved', $updates->first()->data['message']);
        }
        $ticket->update(['status_id' => Status::idByName('Pending')]);
        foreach ([$staff, $head] as $recipient) {
            $updates = $recipient->notifications()->where('data->type', 'ticket_status_changed')->get();
            $this->assertCount(2, $updates);
            $this->assertTrue($updates->contains(function ($notification) {
                return strpos($notification->data['message'], 'Pending') !== false;
            }));
        }
    }
}
