<?php

namespace App\Services;

use App\Models\{Status, Ticket};
use App\Notifications\TicketSystemNotification;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class TicketDeadlineNotifier
{
    public function __construct(private TicketNotificationService $notifications)
    {
    }

    public function send(?CarbonInterface $at = null): int
    {
        $at ??= now();
        $resolved = Status::idByCode(Status::RESOLVED);
        if (!$resolved) {
            throw new \RuntimeException('The resolved status code is required for deadline notifications.');
        }
        $sent = 0;
        Ticket::where('created_at', '<=', $at->copy()->subHours(48))
            ->where(fn ($query) => $query->where('status_id', '<>', $resolved)->orWhereNull('status_id'))
            ->select('id')->chunkById(100, function ($tickets) use ($at, $resolved, &$sent) {
                foreach ($tickets as $candidate) {
                    $sent += DB::transaction(function () use ($candidate, $at, $resolved) {
                        $ticket = Ticket::whereKey($candidate->id)->lockForUpdate()->first();
                        if (!$ticket || (int) $ticket->status_id === (int) $resolved) return 0;
                        $deadline = $ticket->created_at->copy()->addHours(72);
                        if ($deadline->gt($at->copy()->addHours(24))) return 0;
                        $stage = $deadline->lte($at) ? 'overdue' : 'due_soon';
                        $count = 0;
                        foreach ($this->notifications->deadlineRecipients($ticket) as $user) {
                            $key = ['ticket_id' => $ticket->id, 'user_id' => $user->id,
                                'stage' => $stage, 'deadline_at' => $deadline];
                            if (DB::table('ticket_deadline_notifications')->where($key)->exists()) continue;
                            $user->notify(new TicketSystemNotification(
                                $stage === 'overdue' ? 'Ticket Overdue' : 'Ticket Due Within 24 Hours',
                                "Ticket {$ticket->reference_id} " . ($stage === 'overdue'
                                    ? 'has reached its 72-hour deadline.' : 'will reach its 72-hour deadline within 24 hours.'),
                                url(config('backpack.base.route_prefix') . "/ticket/{$ticket->id}/show"),
                                ['ticket_id' => $ticket->id, 'type' => 'ticket_' . $stage,
                                    'deadline_at' => $deadline->toIso8601String()]
                            ));
                            DB::table('ticket_deadline_notifications')->insert($key + ['created_at' => $at]);
                            $count++;
                        }
                        return $count;
                    }, 3);
                }
            });
        return $sent;
    }
}
