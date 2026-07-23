<?php

namespace App\Observers;

use App\Models\Ticket;
use App\Services\TicketAutoAssignmentService;

class TicketObserver
{
    protected $assignmentService;

    public function __construct(TicketAutoAssignmentService $assignmentService)
    {
        $this->assignmentService = $assignmentService;
    }

    public function created(Ticket $ticket)
    {
        // If the ticket isn't already assigned manually upon creation...
        if (is_null($ticket->assigned_to)) {
            try {
                $this->assignmentService->assignTicket($ticket);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error(
                    '[TicketObserver] Auto-assignment failed for ticket ' . $ticket->id . ': ' . $e->getMessage()
                );
                // Ticket creation itself already succeeded (this fires from the
                // "created" event, after the INSERT committed) — leave the
                // ticket unassigned rather than surface a 500 for something
                // that already worked from the user's point of view.
            }
        }
    }

    /**
     * Handle the Ticket "updated" event.
     *
     * @param  \App\Models\Ticket  $ticket
     * @return void
     */
    public function updated(Ticket $ticket)
    {
        //
    }

    /**
     * Handle the Ticket "deleted" event.
     *
     * @param  \App\Models\Ticket  $ticket
     * @return void
     */
    public function deleted(Ticket $ticket)
    {
        //
    }

    /**
     * Handle the Ticket "restored" event.
     *
     * @param  \App\Models\Ticket  $ticket
     * @return void
     */
    public function restored(Ticket $ticket)
    {
        //
    }

    /**
     * Handle the Ticket "force deleted" event.
     *
     * @param  \App\Models\Ticket  $ticket
     * @return void
     */
    public function forceDeleted(Ticket $ticket)
    {
        //
    }
}
