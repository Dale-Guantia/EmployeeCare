<?php

namespace App\Console\Commands;

use App\Services\AttachmentStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PrivatizeTicketAttachments extends Command
{
    protected $signature = 'attachments:privatize {--dry-run}';
    protected $description = 'Move legacy ticket attachments to private storage after verifying their contents.';

    public function handle(): int
    {
        $source = Storage::disk('public');
        $target = Storage::disk(AttachmentStorage::DISK);
        foreach ($source->allFiles('attachments') as $path) {
            if ($this->option('dry-run')) {
                $this->line($path);
                continue;
            }
            try {
                if (!$target->exists($path)) {
                    $stream = $source->readStream($path);
                    try {
                        if (!$target->put($path, $stream)) {
                            throw new \RuntimeException('Private copy failed.');
                        }
                    } finally {
                        if (is_resource($stream)) { fclose($stream); }
                    }
                }
                if ($this->digest($source, $path) !== $this->digest($target, $path)) {
                    throw new \RuntimeException('The private copy differs; the original was retained.');
                }
                if (!$source->delete($path)) {
                    throw new \RuntimeException('The public original could not be removed.');
                }
            } catch (\Throwable $e) {
                $this->error($path . ': ' . $e->getMessage());
                return 1;
            }
        }
        return 0;
    }

    private function digest($disk, string $path): string
    {
        $stream = $disk->readStream($path);
        if (!is_resource($stream)) { throw new \RuntimeException('Unable to verify the attachment.'); }
        try {
            $hash = hash_init('sha256');
            hash_update_stream($hash, $stream);
            return hash_final($hash);
        } finally {
            fclose($stream);
        }
    }
}
