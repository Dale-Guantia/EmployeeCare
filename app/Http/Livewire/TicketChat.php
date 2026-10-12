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
        $user = backpack_user();
        abort_unless($user && $user->is_active && $user->can('ticket.view'), 403);

        return Ticket::visibleTo($user)->with(['status', 'user', 'assignee'])->findOrFail($this->ticketId);
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

    public function removeAttachment($index)
    {
        if ($this->isResolved) {
            return;
        }

        if (isset($this->attachments[$index])) {
            $this->removeUpload('attachments', $this->attachments[$index]->getFilename());
        }
    }

    public function sendComment()
    {
        if ($this->isResolved) {
            $this->addError('comment', 'This ticket is resolved. Reopen it to continue the conversation.');
            return;
        }

        $this->validate([
            'comment' => 'required_without:attachments|nullable|string|max:65535',
            'attachments' => 'nullable|array|max:' . \App\Services\AttachmentStorage::MAX_FILES,
            'attachments.*' => 'nullable|mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx,txt|max:20480',
        ], [
            'comment.required_without' => 'Please enter a message or attach a file.',
            'attachments.*.mimes' => 'Allowed file types: jpg, jpeg, png, pdf, doc, docx, xls, xlsx, txt.',
            'attachments.*.max' => 'Each attachment must not exceed 20MB.',
        ]);

        $paths = \App\Services\AttachmentStorage::storeFiles($this->attachments ?: []);
        $createdComment = null;
        $references = function () use (&$createdComment) {
            return $createdComment ? (optional(TicketComment::find($createdComment->id))->attachment ?: []) : [];
        };
        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($paths, &$createdComment) {
                $createdComment = TicketComment::create([
                    'ticket_id' => $this->ticketId,
                    'user_id' => backpack_user()->id,
                    'comment' => $this->comment ?? '',
                    'attachment' => $paths,
                ]);
                app(TicketNotificationService::class)->notifyTicketCommented($this->ticket, backpack_user());
            });
            app(\App\Services\AttachmentTransactions::class)->track(\Illuminate\Support\Facades\DB::connection(), $paths, $references);
        } catch (\Throwable $e) {
            \App\Services\AttachmentStorage::discardUnreferenced($paths, $references);
            \Illuminate\Support\Facades\Log::error('[TicketChat] Comment persistence failed.', ['exception' => $e]);
            $this->addError('attachments', 'The comment and attachments could not be saved. Please try again.');
            return;
        }

        $this->comment = '';
        $this->attachments = [];
        $this->resetErrorBag();

        $this->emit('commentSent');
    }
}
