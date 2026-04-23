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
            $this->assignmentService->assignTicket($ticket);
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
