<?php

namespace App\Http\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\TicketComment;
use Illuminate\Support\Facades\Storage;

class TicketChat extends Component
{
    use WithFileUploads;

    public $ticketId;
    public $comment = '';
    public $attachments = []; // Always use plural for multiple

    public function render()
    {
        return view('livewire.ticket-chat', [
            'comments' => TicketComment::where('ticket_id', $this->ticketId)
                            ->with('user')
                            ->oldest()
                            ->get()
        ]);
    }

    public function removeUpload($index)
    {
        unset($this->attachments[$index]);
        $this->attachments = array_values($this->attachments);
    }

    public function sendComment()
    {
        $this->validate([
            'comment' => 'required_without:attachments|nullable',
            'attachments.*' => 'nullable|max:10240',
        ]);

        $paths = [];
        if ($this->attachments) {
            foreach ($this->attachments as $file) {
                $filename = time() . '_' . $file->getClientOriginalName();
                $paths[] = $file->storeAs('attachments', $filename, 'public');
            }
        }

        TicketComment::create([
            'ticket_id' => $this->ticketId,
            'user_id' => auth()->id(),
            'comment' => $this->comment ?? '',
            'attachment' => $paths,
        ]);

        $this->comment = '';
        $this->attachments = [];
        $this->emit('commentSent');
    }
}
