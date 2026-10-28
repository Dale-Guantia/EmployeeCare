<?php

namespace App\Console\Commands;

use App\Services\AttachmentStorage;
use App\Services\AttachmentInventory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PrivatizeTicketAttachments extends Command
{
    protected $signature = 'attachments:privatize {--dry-run} {--show-paths : Show paths in restricted operator output}';
    protected $description = 'Move legacy ticket attachments to private storage after verifying their contents.';

    public function handle(AttachmentInventory $inventory): int
    {
        $completed = 0;
        $path = null;
        $phase = 'disk-roots';
        try {
            $inventory->assertSeparateRoots();
            $source = Storage::disk('public');
            $target = Storage::disk(AttachmentStorage::DISK);
            $phase = 'public-file-scan';
            $files = $inventory->files('public');
            // Preflight conflicts and unreadable copies even during a dry run.
            foreach ($files as $path => $snapshot) {
                $phase = 'existing-private-copy-integrity';
                if ($target->exists($path) && $inventory->snapshot(AttachmentStorage::DISK, $path)['sha256'] !== $snapshot['sha256']) {
                    throw new \RuntimeException('Conflicting private copy.');
                }
            }
            foreach ($files as $path => $snapshot) {
                $this->line('path_id=' . hash('sha256', $path) . ($this->option('show-paths') ? ' path=' . $path : ''));
                if ($this->option('dry-run')) { continue; }
                $phase = 'copy-to-private';
                if (!$target->exists($path)) {
                    $stream = $source->readStream($path);
                    if (!is_resource($stream)) { throw new \RuntimeException('Public original cannot be read.'); }
                    try {
                        if (!$target->put($path, $stream)) {
                            throw new \RuntimeException('Private copy failed.');
                        }
                    } finally {
                        if (is_resource($stream)) { fclose($stream); }
                    }
                }
                $phase = 'integrity-before-removal';
                if ($inventory->snapshot('public', $path) !== $snapshot
                    || $inventory->snapshot(AttachmentStorage::DISK, $path)['sha256'] !== $snapshot['sha256']) {
                    throw new \RuntimeException('The private copy differs; the original was retained.');
                }
                $phase = 'remove-public-original';
                if (!$source->delete($path)) {
                    throw new \RuntimeException('The public original could not be removed.');
                }
                $completed++;
            }
        } catch (\Throwable $e) {
            $this->error('Privatization stopped at ' . $phase . '; original retained for the failed entry. Completed=' . $completed
                . ($path ? '; path_id=' . hash('sha256', $path) : '') . '. Check readability, links, conflicting copies, and storage permissions.');
            if ($e instanceof \App\Exceptions\AttachmentInspectionException) { $this->error($e->getMessage()); }
            return 1;
        }
        $this->info(($this->option('dry-run') ? 'Verified dry run' : 'Privatization') . ' complete; scanned=' . count($files) . '; moved=' . $completed . '.');
        return 0;
    }
}
