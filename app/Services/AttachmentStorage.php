<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Generates randomized, non-attacker-controlled stored filenames for ticket
 * attachments, while keeping a slugified fragment of the original name in
 * the stored path so the UI (which derives the displayed name from
 * basename($path)) still shows something human-recognizable — without
 * restructuring the `attachments` column's plain-string-array storage shape.
 */
class AttachmentStorage
{
    public static function randomizedFilename(UploadedFile $file): string
    {
        $original = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $slug = Str::slug($original, '_');
        $extension = $file->getClientOriginalExtension();

        $name = (string) Str::uuid();
        if ($slug !== '') {
            $name .= '__' . $slug;
        }

        return $extension !== '' ? "{$name}.{$extension}" : $name;
    }
}
