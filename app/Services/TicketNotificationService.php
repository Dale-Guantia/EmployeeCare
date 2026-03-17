<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketSystemNotification;
use Illuminate\Support\Collection;

class TicketNotificationService
{
    protected function ticketUrl(Ticket $ticket): string
    {
        return url(config('backpack.base.route_prefix') . "/ticket/{$ticket->id}/show");
    }

    protected function notifyUsers(Collection $users, string $settingKey, callable $payloadBuilder): void
    {
        $users
            ->filter()
            ->unique('id')
            ->filter(fn ($user) => method_exists($user, 'wantsNotification') ? $user->wantsNotification($settingKey) : true)
            ->each(function ($user) use ($payloadBuilder) {
                $payload = $payloadBuilder($user);

                if (!$payload) {
                    return;
                }

                $user->notify(new TicketSystemNotification(
                    $payload['title'],
                    $payload['message'],
                    $payload['url'] ?? null,
                    $payload['extra'] ?? []
                ));
            });
    }

    protected function excludeActor(Collection $users, ?User $actor): Collection
    {
        if (!$actor) {
            return $users;
        }

        return $users->filter(fn ($user) => (int) $user->id !== (int) $actor->id);
    }

    protected function managementUsers(): Collection
    {
        return User::whereHas('roles', function ($q) {
            $q->whereIn('name', ['admin', 'dept_head', 'div_head']);
        })->get();
    }

    protected function commentParticipants(Ticket $ticket): Collection
    {
        $commentUserIds = $ticket->comments()
            ->pluck('user_id')
            ->filter()
            ->unique()
            ->values();

        return User::whereIn('id', $commentUserIds)->get();
    }

    /*
    |--------------------------------------------------------------------------
    | Role rule helpers
    |--------------------------------------------------------------------------
    */

    protected function shouldReceiveTicketCreated(User $user, Ticket $ticket): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isDeptHead()) {
            return (int) $user->department_id === (int) $ticket->department_id;
        }

        if ($user->isDivHead()) {
            return (int) $user->division_id === (int) $ticket->division_id;
        }

        if ($user->isHrStaff() || $user->isEmployee()) {
            return false;
        }

        return false;
    }

    protected function shouldReceiveTicketAssigned(User $user, Ticket $ticket): bool
    {
        if ($user->isAdmin()) {
            return (int) $ticket->user_id === (int) $user->id
                || (int) $ticket->assigned_to === (int) $user->id;
        }

        if ($user->isDeptHead()) {
            return (int) $user->department_id === (int) $ticket->department_id;
        }

        if ($user->isDivHead()) {
            return (int) $user->division_id === (int) $ticket->division_id;
        }

        if ($user->isHrStaff()) {
            return (int) $ticket->user_id === (int) $user->id
                || (int) $ticket->assigned_to === (int) $user->id;
        }

        if ($user->isEmployee()) {
            return (int) $ticket->user_id === (int) $user->id;
        }

        return false;
    }

    protected function shouldReceiveTicketStatusChanged(User $user, Ticket $ticket): bool
    {
        if ($user->isAdmin()) {
            return (int) $ticket->user_id === (int) $user->id
                || (int) $ticket->assigned_to === (int) $user->id;
        }

        if ($user->isDeptHead()) {
            return (int) $user->department_id === (int) $ticket->department_id;
        }

        if ($user->isDivHead()) {
            return (int) $user->division_id === (int) $ticket->division_id;
        }

        if ($user->isHrStaff()) {
            return (int) $ticket->user_id === (int) $user->id
                || (int) $ticket->assigned_to === (int) $user->id;
        }

        if ($user->isEmployee()) {
            return (int) $ticket->user_id === (int) $user->id;
        }

        return false;
    }

    protected function shouldReceiveTicketCommented(User $user, Ticket $ticket, Collection $participants): bool
    {
        $isCreator = (int) $ticket->user_id === (int) $user->id;
        $isParticipant = $participants->contains(fn ($participant) => (int) $participant->id === (int) $user->id);

        if ($user->isAdmin()) {
            return $isCreator || $isParticipant;
        }

        if ($user->isDeptHead()) {
            return $isCreator || $isParticipant;
        }

        if ($user->isDivHead()) {
            return $isCreator || $isParticipant;
        }

        if ($user->isHrStaff()) {
            return (int) $ticket->user_id === (int) $user->id
                || (int) $ticket->assigned_to === (int) $user->id;
        }

        if ($user->isEmployee()) {
            return $isCreator;
        }

        return false;
    }

    protected function assignmentMessageFor(User $recipient, Ticket $ticket): string
    {
        $assigneeName = optional($ticket->assignee)->name ?? 'a staff member';

        if ($ticket->assigned_to && (int) $recipient->id === (int) $ticket->assigned_to) {
            return "Ticket {$ticket->reference_id} was assigned to you.";
        }

        return "Ticket {$ticket->reference_id} was assigned to {$assigneeName}.";
    }

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    */

    public function notifyTicketCreated(Ticket $ticket, ?User $actor = null): void
    {
        $ticket->loadMissing(['user', 'assignee']);

        $users = User::all();

        $recipients = $users->filter(function ($user) use ($ticket) {
            return $this->shouldReceiveTicketCreated($user, $ticket);
        });

        $recipients = $this->excludeActor($recipients, $actor);

        $this->notifyUsers($recipients, 'notify_ticket_created', function ($user) use ($ticket) {
            return [
                'title' => 'New Ticket',
                'message' => "A new ticket {$ticket->reference_id} was created.",
                'url' => $this->ticketUrl($ticket),
                'extra' => [
                    'ticket_id' => $ticket->id,
                    'type' => 'ticket_created',
                ],
            ];
        });
    }

    public function notifyTicketAssigned(Ticket $ticket, ?User $actor = null): void
    {
        $ticket->loadMissing(['user', 'assignee']);

        $users = User::all();

        $recipients = $users->filter(function ($user) use ($ticket) {
            return $this->shouldReceiveTicketAssigned($user, $ticket);
        });

        $recipients = $this->excludeActor($recipients, $actor);

        $this->notifyUsers($recipients, 'notify_ticket_assigned', function ($user) use ($ticket) {
            return [
                'title' => 'Ticket Assigned',
                'message' => $this->assignmentMessageFor($user, $ticket),
                'url' => $this->ticketUrl($ticket),
                'extra' => [
                    'ticket_id' => $ticket->id,
                    'type' => 'ticket_assigned',
                ],
            ];
        });
    }

    public function notifyTicketStatusChanged(Ticket $ticket, ?User $actor = null): void
    {
        $ticket->loadMissing(['user', 'assignee', 'status']);

        $users = User::all();

        $recipients = $users->filter(function ($user) use ($ticket) {
            return $this->shouldReceiveTicketStatusChanged($user, $ticket);
        });

        $recipients = $this->excludeActor($recipients, $actor);

        $this->notifyUsers($recipients, 'notify_ticket_status_changed', function ($user) use ($ticket) {
            return [
                'title' => 'Ticket Status Updated',
                'message' => "Ticket {$ticket->reference_id} status changed to {$ticket->status->status_name}.",
                'url' => $this->ticketUrl($ticket),
                'extra' => [
                    'ticket_id' => $ticket->id,
                    'type' => 'ticket_status_changed',
                ],
            ];
        });
    }

    public function notifyTicketCommented(Ticket $ticket, User $actor): void
    {
        $ticket->loadMissing(['user', 'assignee']);

        $participants = $this->commentParticipants($ticket);
        $users = User::all();

        $recipients = $users->filter(function ($user) use ($ticket, $participants) {
            return $this->shouldReceiveTicketCommented($user, $ticket, $participants);
        });

        $recipients = $this->excludeActor($recipients, $actor);

        $this->notifyUsers($recipients, 'notify_ticket_commented', function ($user) use ($ticket, $actor) {
            return [
                'title' => 'New Ticket Comment',
                'message' => "{$actor->name} commented on ticket {$ticket->reference_id}.",
                'url' => $this->ticketUrl($ticket),
                'extra' => [
                    'ticket_id' => $ticket->id,
                    'type' => 'ticket_commented',
                ],
            ];
        });
    }
}
