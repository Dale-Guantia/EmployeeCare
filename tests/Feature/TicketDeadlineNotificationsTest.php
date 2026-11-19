<?php

namespace Tests\Feature;

use App\Models\{Department, Division, Status, Ticket, User};
use App\Services\TicketDeadlineNotifier;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TicketDeadlineNotificationsTest extends TestCase
{
    private User $owner;
    private Department $department;
    private Division $division;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-04 12:00:00');
        $this->department = Department::create(['department_name' => 'Deadline office']);
        $this->division = Division::create(['department_id' => $this->department->id, 'division_name' => 'Deadline team']);
        $this->owner = User::factory()->create(['is_active' => true]);
        $this->owner->assignRole('employee');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function ticket(int $minutesOld, array $extra = []): Ticket
    {
        $id = DB::table('tickets')->insertGetId($extra + ['user_id' => $this->owner->id,
            'department_id' => $this->department->id, 'division_id' => $this->division->id,
            'reference_id' => 'DEADLINE-' . $minutesOld . '-' . Ticket::count(), 'message' => 'Synthetic deadline fixture',
            'status_id' => Status::idByCode(Status::PENDING), 'created_at' => now()->subMinutes($minutesOld)]);
        return Ticket::findOrFail($id);
    }

    public function test_exact_boundaries_and_both_notifications_are_deduplicated(): void
    {
        $ticket = $this->ticket(48 * 60 - 1);
        $notifier = app(TicketDeadlineNotifier::class);
        $this->assertSame(0, $notifier->send());
        Carbon::setTestNow(now()->addMinute());
        $this->assertSame(1, $notifier->send());
        $this->assertSame(0, $notifier->send());
        $this->assertSame('ticket_due_soon', $this->owner->notifications()->first()->data['type']);
        Carbon::setTestNow(now()->addHours(24));
        $this->assertSame(1, $notifier->send());
        $this->assertSame(0, $notifier->send());
        $this->assertSame(2, $this->owner->notifications()->count());
        $this->assertSame(2, DB::table('ticket_deadline_notifications')->where('ticket_id', $ticket->id)->count());
    }

    public function test_overdue_catchup_does_not_send_a_retroactive_warning_and_resolved_are_skipped(): void
    {
        $this->ticket(90 * 60);
        $this->ticket(49 * 60, ['status_id' => Status::idByCode(Status::RESOLVED)]);
        $this->assertSame(1, app(TicketDeadlineNotifier::class)->send());
        $this->assertSame('ticket_overdue', $this->owner->notifications()->first()->data['type']);
        $this->assertSame(1, DB::table('ticket_deadline_notifications')->count());
    }

    public function test_only_active_eligible_recipients_with_preferences_receive_alerts(): void
    {
        $head = User::factory()->create(['is_active' => true, 'department_id' => $this->department->id]);
        $head->assignRole('dept_head');
        $staff = User::factory()->create(['is_active' => true, 'department_id' => $this->department->id, 'division_id' => $this->division->id]);
        $staff->assignRole('hr_staff');
        $foreignHead = User::role('dept_head')->where('department_id', '<>', $this->department->id)->firstOrFail();
        $this->owner->update(['notify_ticket_status_changed' => false]);
        $ticket = $this->ticket(49 * 60, ['assigned_to' => $staff->id]);
        $this->assertSame(2, app(TicketDeadlineNotifier::class)->send());
        $this->assertSame(1, $head->notifications()->count());
        $this->assertSame(1, $staff->notifications()->count());
        $this->assertSame(0, $this->owner->notifications()->count());
        $this->assertSame(0, $foreignHead->notifications()->count());
        $head->update(['is_active' => false]);
        Carbon::setTestNow(now()->addDay());
        $this->assertSame(1, app(TicketDeadlineNotifier::class)->send());
        $this->assertSame(1, $head->notifications()->count());
    }

    public function test_delivery_failure_rolls_back_notification_and_ledger_for_retry(): void
    {
        $this->ticket(49 * 60);
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Notifications\Events\NotificationSent::class,
            function () { throw new \RuntimeException('Simulated delivery failure'); });
        try {
            app(TicketDeadlineNotifier::class)->send();
            $this->fail('Delivery failure must propagate.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Simulated delivery failure', $e->getMessage());
        }
        $this->assertSame(0, DB::table('notifications')->count());
        $this->assertSame(0, DB::table('ticket_deadline_notifications')->count());
    }

    public function test_command_is_scheduled_and_repeat_runs_do_not_duplicate(): void
    {
        $this->ticket(49 * 60);
        $this->artisan('tickets:notify-deadlines')->expectsOutput('Deadline notifications sent: 1')->assertSuccessful();
        $this->artisan('tickets:notify-deadlines')->expectsOutput('Deadline notifications sent: 0')->assertSuccessful();
        $events = app(Schedule::class)->events();
        $event = collect($events)->first(fn ($event) => str_contains($event->command ?? '', 'tickets:notify-deadlines'));
        $this->assertNotNull($event);
        $this->assertSame('* * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }
}
