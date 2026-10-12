<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Services\AttachmentStorage;
use Illuminate\Support\Facades\Storage;

class TicketAttachmentController extends Controller
{
    public function show($ticket, $index)
    {
        $entry = Ticket::visibleTo(backpack_user())->findOrFail($ticket);
        return $this->respond(($entry->attachments ?: [])[(int) $index] ?? null);
    }

    public function comment($ticket, $comment, $index)
    {
        $entry = Ticket::visibleTo(backpack_user())->findOrFail($ticket);
        $comment = $entry->comments()->findOrFail($comment);
        return $this->respond(($comment->attachment ?: [])[(int) $index] ?? null);
    }

    private function respond($path)
    {
        abort_unless(is_string($path), 404);
        $disk = Storage::disk(AttachmentStorage::readableDisk($path));
        abort_unless($disk->exists($path), 404);
        return $disk->response($path, basename($path), [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
