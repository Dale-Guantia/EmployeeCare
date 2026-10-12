<?php

namespace Tests\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\SQLiteGrammar;

class SqliteTestGrammar extends SQLiteGrammar
{
    public function compileSelect(Builder $query)
    {
        // Production supports MySQL/SQL Server. Adapt only their elapsed-time expressions for test SQLite.
        return preg_replace_callback(
            '/TIMESTAMPDIFF\((HOUR|MINUTE),\s*([a-z_][a-z0-9_.]*),\s*([a-z_][a-z0-9_.]*)\)/i',
            function ($match) {
                $seconds = strtoupper($match[1]) === 'HOUR' ? 3600 : 60;
                return "CAST((strftime('%s', {$match[3]}) - strftime('%s', {$match[2]})) / {$seconds} AS INTEGER)";
            },
            parent::compileSelect($query)
        );
    }
}
