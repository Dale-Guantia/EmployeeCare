<?php

namespace App\Services;

use App\Exceptions\AttachmentInspectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Read raw references: Eloquent's JSON cast can hide corrupt legacy values. */
class AttachmentInventory
{
    public function references(): array
    {
        $paths = [];
        foreach (['tickets' => 'attachments', 'ticket_comments' => 'attachment'] as $table => $column) {
            DB::table($table)->select(['id', $column])->orderBy('id')->chunkById(500, function ($rows) use (&$paths, $table, $column) {
                foreach ($rows as $row) {
                    if ($row->$column === null) { continue; }
                    $values = json_decode($row->$column, true);
                    if (json_last_error() !== JSON_ERROR_NONE || !is_array($values)
                        || substr(ltrim($row->$column), 0, 1) !== '['
                        || ($values && array_keys($values) !== range(0, count($values) - 1))) {
                        throw new AttachmentInspectionException('Malformed attachment references in ' . $table . ' row ' . $row->id . '.');
                    }
                    foreach ($values as $path) {
                        if (!self::validPath($path)) {
                            throw new AttachmentInspectionException('Unsafe attachment reference in ' . $table . ' row ' . $row->id . '.');
                        }
                        $paths[$path] = true;
                    }
                }
            });
        }
        return $paths;
    }

    public static function validPath($path): bool
    {
        if (!is_string($path) || strlen($path) > 1024
            || !preg_match('#\Aattachments/[^\x00-\x1F\x7F\\\\:<>"|?*]+\z#uD', $path)) {
            return false;
        }
        foreach (explode('/', $path) as $part) {
            $base = rtrim(explode('.', $part)[0]);
            if ($part === '' || $part === '.' || $part === '..' || rtrim($part, " .") !== $part
                || preg_match('/\A(?:CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])\z/i', $base)) { return false; }
        }
        return true;
    }

    public function files(string $name): array
    {
        $disk = Storage::disk($name);
        $root = $this->root($name);
        // Local adapters may omit links from listings. An omitted tree must never authorize cleanup.
        $directory = $root . DIRECTORY_SEPARATOR . 'attachments';
        if (is_link($directory)) { throw new AttachmentInspectionException('Linked attachment directory requires manual review on disk=' . $name . '.'); }
        if (is_dir($directory)) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
            foreach ($iterator as $entry) {
                if ($entry->isLink()) { throw new AttachmentInspectionException('Linked attachment entry requires manual review on disk=' . $name . '; path_id=' . hash('sha256', substr($entry->getPathname(), strlen($root) + 1)) . '.'); }
            }
        }
        $files = [];
        foreach ($disk->allFiles('attachments') as $path) {
            if (!self::validPath($path)) { throw new AttachmentInspectionException('Unsafe stored path requires manual review on disk=' . $name . '; path_id=' . hash('sha256', $path) . '.'); }
            $files[$path] = $this->snapshot($name, $path);
        }
        return $files;
    }

    public function root(string $name): string
    {
        $configured = config('filesystems.disks.' . $name);
        if (($configured['driver'] ?? null) !== 'local' || empty($configured['root']) || !is_dir($configured['root'])) {
            // Laravel's Local adapter creates missing roots when resolved. An audit must not do that.
            throw new \RuntimeException('Local attachment disk root is unavailable.');
        }
        $adapter = Storage::disk($name)->getDriver()->getAdapter();
        if (!$adapter instanceof \League\Flysystem\Adapter\Local) {
            throw new \RuntimeException('Reconciliation requires local attachment disks.');
        }
        $root = realpath($adapter->getPathPrefix());
        if ($root === false || $root !== realpath($configured['root'])) { throw new \RuntimeException('Attachment disk root is unavailable or inconsistent.'); }
        return $root;
    }

    public function assertSeparateRoots(): void
    {
        $private = strtolower(str_replace('\\', '/', $this->root(AttachmentStorage::DISK))) . '/';
        $public = strtolower(str_replace('\\', '/', $this->root('public'))) . '/';
        $web = strtolower(str_replace('\\', '/', realpath(public_path()) ?: public_path())) . '/';
        if (strpos($private, $public) === 0 || strpos($public, $private) === 0 || strpos($private, $web) === 0) {
            throw new \RuntimeException('Attachment roots overlap or private storage is publicly rooted.');
        }
    }

    public function snapshot(string $name, string $path): array
    {
        if (!self::validPath($path)) { throw new \RuntimeException('Unsafe attachment path.'); }
        $root = $this->root($name);
        $file = $root;
        foreach (explode('/', $path) as $part) {
            $file .= DIRECTORY_SEPARATOR . $part;
            if (is_link($file)) { throw new \RuntimeException('Linked attachment requires manual review.'); }
        }
        clearstatcache(true, $file);
        $resolved = realpath($file);
        if ($resolved === false || !is_file($resolved)
            || strpos(strtolower(str_replace('\\', '/', $resolved)), strtolower(str_replace('\\', '/', $root)) . '/') !== 0) {
            throw new \RuntimeException('Attachment is missing or outside its disk.');
        }
        $disk = Storage::disk($name);
        $stream = $disk->readStream($path);
        if (!is_resource($stream)) { throw new \RuntimeException('Attachment cannot be read.'); }
        try {
            $size = $disk->size($path);
            $modified = $disk->lastModified($path);
            $hash = hash_init('sha256');
            $read = hash_update_stream($hash, $stream);
            clearstatcache(true, $resolved);
            if ($read !== $size || $disk->size($path) !== $size || $disk->lastModified($path) !== $modified) {
                throw new AttachmentInspectionException('Attachment integrity scan failed or file changed on disk=' . $name . '; path_id=' . hash('sha256', $path) . '.');
            }
            return ['size' => $size, 'modified' => $modified, 'sha256' => hash_final($hash)];
        } finally {
            fclose($stream);
        }
    }
}
