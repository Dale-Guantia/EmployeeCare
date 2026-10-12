<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Generates randomized, non-attacker-controlled stored filenames for ticket
 * attachments, while keeping a slugified fragment of the original name in
 * the stored path so the UI (which derives the displayed name from
 * basename($path)) still shows something human-recognizable — without
 * restructuring the `attachments` column's plain-string-array storage shape.
 */
class AttachmentStorage
{
    public const DISK = 'ticket_attachments';
    public const MAX_FILES = 20;

    public static function randomizedFilename(UploadedFile $file): string
    {
        $original = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $slug = substr(Str::slug($original, '_'), 0, 80);
        $extension = $file->getClientOriginalExtension();

        $name = (string) Str::uuid();
        if ($slug !== '') {
            $name .= '__' . $slug;
        }

        return $extension !== '' ? "{$name}.{$extension}" : $name;
    }

    public static function storeFiles(array $files): array
    {
        $paths = [];
        try {
            foreach ($files as $file) {
                $path = 'attachments/' . self::randomizedFilename($file);
                // Track the intended path too: an adapter can write and then report failure.
                $paths[] = $path;
                // Livewire's storeAs returns a path even when the destination write fails.
                $stream = method_exists($file, 'readStream') ? $file->readStream() : fopen($file->getRealPath(), 'rb');
                if (!is_resource($stream)) {
                    throw new \RuntimeException('Attachment could not be read.');
                }
                try {
                    if (!Storage::disk(self::DISK)->put($path, $stream)) {
                        throw new \RuntimeException('Attachment storage returned false.');
                    }
                } finally {
                    if (is_resource($stream)) { fclose($stream); }
                }
            }
            return $paths;
        } catch (\Throwable $e) {
            self::discard($paths);
            Log::error('[Attachments] Upload failed.', ['exception' => $e]);
            throw ValidationException::withMessages(['attachments' => 'The attachments could not be saved. Please try again.']);
        }
    }

    public static function discard(array $paths): void
    {
        foreach ($paths as $path) {
            try {
                if (!Storage::disk(self::DISK)->delete($path)) {
                    Log::warning('[Attachments] Could not remove a staged file.', ['path' => $path]);
                }
            } catch (\Throwable $e) {
                Log::error('[Attachments] Cleanup failed.', ['path' => $path, 'exception' => $e]);
            }
        }
    }

    public static function deleteFiles(array $paths): void
    {
        foreach (array_unique($paths) as $path) {
            if (!is_string($path) || $path === '') {
                continue;
            }
            foreach ([self::DISK, 'public'] as $disk) {
                try {
                    if (Storage::disk($disk)->exists($path) && !Storage::disk($disk)->delete($path)) {
                        Log::warning('[Attachments] Could not delete a file.', ['path' => $path, 'disk' => $disk]);
                    }
                } catch (\Throwable $e) {
                    Log::error('[Attachments] File deletion failed.', ['path' => $path, 'disk' => $disk, 'exception' => $e]);
                }
            }
        }
    }

    public static function discardUnreferenced(array $paths, callable $references): void
    {
        if (!$paths) { return; }
        try {
            $retained = $references();
        } catch (\Throwable $e) {
            // A lost connection can make COMMIT ambiguous. Preserve files until references can be checked.
            Log::error('[Attachments] Reference check failed; staged files retained for reconciliation.', ['paths' => $paths, 'exception' => $e]);
            return;
        }
        self::discard(array_values(array_diff($paths, $retained ?: [])));
    }

    public static function readableDisk(string $path): string
    {
        abort_unless(strpos($path, 'attachments/') === 0 && strpos($path, '\\') === false
            && strpos($path, "\0") === false && !preg_match('#(?:^|/)\.\.(?:/|$)#', $path), 404);
        // Compatibility while the deployment command moves legacy files. Public HTTP access is blocked.
        return Storage::disk(self::DISK)->exists($path) ? self::DISK : 'public';
    }

    public static function ticketUrl($ticket, string $path): string
    {
        $index = array_search($path, $ticket->attachments ?: [], true);
        return route('ticket.attachments.show', ['ticket' => $ticket->id, 'index' => $index]);
    }

    public static function commentUrl($comment, string $path): string
    {
        $index = array_search($path, $comment->attachment ?: [], true);
        return route('ticket.comment.attachments.show', ['ticket' => $comment->ticket_id, 'comment' => $comment->id, 'index' => $index]);
    }
}
