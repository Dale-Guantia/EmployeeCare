<?php

namespace App\Console\Commands;

use App\Services\AttachmentInventory;
use App\Services\AttachmentStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ReconcileTicketAttachments extends Command
{
    protected $signature = 'attachments:reconcile {--cleanup : Delete eligible private orphans}
        {--uploads-paused : Confirm all upload writers are paused for the maintenance window}
        {--min-age-hours=48 : Minimum age, at least 48 hours}
        {--show-paths : Show stored paths in restricted operator output}';

    protected $description = 'Audit attachment references and files; retain everything unless cleanup is explicitly requested.';

    public function handle(AttachmentInventory $inventory): int
    {
        $hours = $this->option('min-age-hours');
        if (!ctype_digit((string) $hours) || (int) $hours < 48 || (int) $hours > 87600) {
            $this->error('Minimum age must be between 48 and 87600 hours.'); return 1;
        }
        if ($this->option('cleanup') && !$this->option('uploads-paused')) {
            $this->error('Cleanup requires --uploads-paused and a coordinated maintenance window.'); return 1;
        }
        $cutoff = time() - (int) $hours * 3600;
        $deleted = 0;
        $phase = 'disk-roots';
        try {
            $inventory->assertSeparateRoots();
            $phase = 'initial-reference-scan';
            $references = $inventory->references();
            // Complete both disk scans before allowing any deletion.
            $phase = 'private-file-scan';
            $files = [AttachmentStorage::DISK => $inventory->files(AttachmentStorage::DISK)];
            $phase = 'public-file-scan';
            $files['public'] = $inventory->files('public');
            foreach ($references as $path => $unused) {
                if (!isset($files[AttachmentStorage::DISK][$path]) && !isset($files['public'][$path])) {
                    $this->reportFile('missing-reference', 'either', $path);
                }
            }
            foreach ($files as $disk => $entries) {
                foreach ($entries as $path => $snapshot) {
                    if (isset($references[$path])) {
                        $this->reportFile('retained-reference', $disk, $path);
                        if ($disk === 'public' && isset($files[AttachmentStorage::DISK][$path])
                            && $snapshot['sha256'] !== $files[AttachmentStorage::DISK][$path]['sha256']) {
                            $this->reportFile('manual-review-conflicting-copy', $disk, $path);
                        }
                        continue;
                    }
                    if ($snapshot['modified'] > $cutoff) { $this->reportFile('retained-recent', $disk, $path); continue; }
                    if ($disk === 'public') { $this->reportFile('manual-review-legacy-orphan', $disk, $path); continue; }
                    $this->reportFile('candidate-unreferenced', $disk, $path);
                    if (!$this->option('cleanup')) { continue; }
                    // Re-read all raw references and metadata immediately before each deletion.
                    $phase = 'fresh-reference-check';
                    $fresh = $inventory->references();
                    $phase = 'fresh-file-check';
                    if (isset($fresh[$path]) || $inventory->snapshot($disk, $path) !== $snapshot) {
                        $this->reportFile('retained-changed', $disk, $path); continue;
                    }
                    $phase = 'delete-private-orphan';
                    if (!Storage::disk($disk)->delete($path)) { throw new \RuntimeException('Storage refused deletion.'); }
                    $deleted++;
                    Log::notice('[Attachments] Reconciled private orphan.', ['path_id' => hash('sha256', $path)]);
                    $this->reportFile('deleted', $disk, $path);
                }
            }
        } catch (\Throwable $e) {
            // Exception messages from drivers can contain SQL, credentials, or filenames.
            Log::warning('[Attachments] Reconciliation incomplete; remaining files retained.', ['phase' => $phase, 'exception_type' => get_class($e), 'deleted' => $deleted]);
            $this->error('Manual review required: ' . $phase . ' failed. Remaining files retained; deleted=' . $deleted . '.');
            if ($e instanceof \App\Exceptions\AttachmentInspectionException) { $this->error($e->getMessage()); }
            return 1;
        }
        $this->info(($this->option('cleanup') ? 'Cleanup' : 'Read-only audit') . ' complete; deleted=' . $deleted . '.');
        return 0;
    }

    private function reportFile(string $status, string $disk, string $path): void
    {
        $this->line($status . ' disk=' . $disk . ' path_id=' . hash('sha256', $path)
            . ($this->option('show-paths') ? ' path=' . $path : ''));
    }
}
