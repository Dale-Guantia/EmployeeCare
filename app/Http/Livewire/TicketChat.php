<?php

namespace App\Http\Livewire;

use Livewire\Component;
use App\Models\TicketComment;
use App\Models\TicketRead;

class TicketChat extends Component
{
    public $ticketId;
    public $comment = '';

    public function render()
    {
        return view('livewire.ticket-chat', [
            'comments' => TicketComment::where('ticket_id', $this->ticketId)
                            ->with('user')
                            ->oldest()
                            ->get()
        ]);
    }

    public function sendComment()
    {
        $this->validate(['comment' => 'required|min:1']);

        TicketComment::create([
            'ticket_id' => $this->ticketId,
            'user_id' => auth()->id(),
            'comment' => $this->comment,
        ]);

        $this->comment = ''; // Clear input

        // This tells the JavaScript: "Hey, I just added a message, scroll down!"
        $this->emit('commentSent');
    }
}
