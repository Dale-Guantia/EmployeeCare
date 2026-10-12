<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class SyncUserAvatars extends Command
{
    protected $signature = 'users:sync-avatars {--dry-run : Preview matches without copying files or updating users}';

    protected $description = 'Copy repository WebP avatars to public storage and assign them by exact employee number.';

    public function handle(): int
    {
        $directory = public_path('assets/avatars');
        if (!File::isDirectory($directory)) {
            $this->error('Avatar source directory does not exist: ' . $directory);
            return 1;
        }

        // Prefix keys to preserve employee numbers as strings, including leading zeros.
        $users = User::query()->whereNotNull('emp_no')->get(['id', 'emp_no', 'avatar_url'])
            ->groupBy(function ($user) { return 'emp:' . $user->emp_no; });
        $disk = Storage::disk('public');
        $dryRun = (bool) $this->option('dry-run');
        $matched = $skipped = $failed = 0;

        foreach (File::files($directory) as $file) {
            if ($file->getExtension() !== 'webp') {
                continue;
            }

            $empNo = $file->getBasename('.webp');
            if (!preg_match('/^[0-9]+$/D', $empNo)) {
                $this->warn('Skipped non-employee filename: ' . $file->getFilename());
                $skipped++;
                continue;
            }

            $matches = $users->get('emp:' . $empNo, collect());
            if ($matches->isEmpty()) {
                $this->warn('No user matches emp_no ' . $empNo . '; skipped.');
                $skipped++;
                continue;
            }
            if ($matches->count() !== 1) {
                $this->error('Multiple users match emp_no ' . $empNo . '; skipped.');
                $failed++;
                continue;
            }

            $user = $matches->first();
            $path = 'avatars/' . $file->getFilename();
            $this->line(($dryRun ? 'Would sync' : 'Syncing') . ' emp_no ' . $empNo
                . ' (user ' . $user->id . '): ' . ($user->avatar_url ?: '(none)') . ' -> ' . $path);

            if (!$dryRun) {
                try {
                    $contents = File::get($file->getPathname());
                    if (!$disk->exists($path) || $disk->get($path) !== $contents) {
                        if (!$disk->put($path, $contents)) {
                            throw new \RuntimeException('Could not copy avatar to public storage.');
                        }
                    }
                    if ($disk->get($path) !== $contents) {
                        throw new \RuntimeException('The stored avatar does not match the source.');
                    }

                    // Save the path only after the copy has been verified.
                    $user->avatar_url = $path;
                    if ($user->isDirty('avatar_url')) {
                        $user->saveOrFail();
                    }
                } catch (\Throwable $e) {
                    $this->error('Failed emp_no ' . $empNo . ': ' . $e->getMessage());
                    $failed++;
                    continue;
                }
            }
            $matched++;
        }

        $this->info(($dryRun ? 'Preview' : 'Finished') . ": {$matched} matched, {$skipped} skipped, {$failed} failed.");
        return $failed > 0 ? 1 : 0;
    }
}
