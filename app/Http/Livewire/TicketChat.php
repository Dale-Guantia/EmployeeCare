<?php

namespace App\Http\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\TicketComment;
use App\Models\Ticket;
use App\Services\TicketNotificationService;

class TicketChat extends Component
{
    use WithFileUploads;

    public $ticketId;
    public $comment = '';
    public $attachments = [];

    public function getTicketProperty()
    {
        return Ticket::with(['status', 'user', 'assignee'])->findOrFail($this->ticketId);
    }

    public function getIsResolvedProperty()
    {
        return optional($this->ticket->status)->status_name === 'Resolved';
    }

    public function render()
    {
        return view('livewire.ticket-chat', [
            'ticket' => $this->ticket,
            'isResolved' => $this->isResolved,
            'comments' => TicketComment::where('ticket_id', $this->ticketId)
                ->with('user')
                ->oldest()
                ->get(),
        ]);
    }

    public function removeUpload($index)
    {
        if ($this->isResolved) {
            return;
        }

        unset($this->attachments[$index]);
        $this->attachments = array_values($this->attachments);
    }

    public function sendComment()
    {
        if ($this->isResolved) {
            $this->addError('comment', 'This ticket is resolved. Reopen it to continue the conversation.');
            return;
        }

        $this->validate([
            'comment' => 'required_without:attachments|nullable',
            'attachments.*' => 'nullable|max:10240',
        ], [
            'comment.required_without' => 'Please enter a message or attach a file.',
            'attachments.*.max' => 'Each attachment must not exceed 10MB.',
        ]);

        $paths = [];

        if ($this->attachments) {
            foreach ($this->attachments as $file) {
                $filename = time() . '_' . $file->getClientOriginalName();

                $paths[] = $file->storeAs(
                    'attachments',
                    $filename,
                    'public'
                );
            }
        }

        TicketComment::create([
            'ticket_id' => $this->ticketId,
            'user_id' => backpack_user()->id,
            'comment' => $this->comment ?? '',
            'attachment' => $paths,
        ]);

        app(TicketNotificationService::class)
            ->notifyTicketCommented($this->ticket, backpack_user());

        $this->comment = '';
        $this->attachments = [];
        $this->resetErrorBag();

        $this->emit('commentSent');
    }
}
