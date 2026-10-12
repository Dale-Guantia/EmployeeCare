<?php

namespace App\Services;

use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionRolledBack;

/** Track staged files until the transaction owning their rows really completes. */
class AttachmentTransactions
{
    private $pending = [];

    public function __construct()
    {
        app('events')->listen(TransactionCommitted::class, function ($event) { $this->complete($event, false); });
        app('events')->listen(TransactionRolledBack::class, function ($event) { $this->complete($event, true); });
    }

    public function track($connection, array $paths, callable $references): void
    {
        if ($paths && $connection->transactionLevel() > 0) {
            $this->pending[] = ['connection' => $connection, 'level' => $connection->transactionLevel(), 'paths' => $paths, 'references' => $references];
        }
    }

    private function complete($event, bool $rollback): void
    {
        foreach ($this->pending as $key => &$entry) {
            if ($entry['connection'] !== $event->connection) { continue; }
            $remaining = $event->connection->transactionLevel();
            if ($rollback && $entry['level'] > $remaining) {
                AttachmentStorage::discardUnreferenced($entry['paths'], $entry['references']);
                unset($this->pending[$key]);
            } elseif (!$rollback) {
                if ($remaining === 0) { unset($this->pending[$key]); }
                else { $entry['level'] = min($entry['level'], $remaining); }
            }
        }
        unset($entry);
    }
}
