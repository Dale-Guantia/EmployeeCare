<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class SqlDialectHelper
{
    public static function diffHoursSql(string $start, string $end): string
    {
        return self::isSqlServer()
            ? "DATEDIFF(HOUR, {$start}, {$end})"
            : "TIMESTAMPDIFF(HOUR, {$start}, {$end})";
    }

    public static function diffMinutesSql(string $start, string $end): string
    {
        return self::isSqlServer()
            ? "DATEDIFF(MINUTE, {$start}, {$end})"
            : "TIMESTAMPDIFF(MINUTE, {$start}, {$end})";
    }

    private static function isSqlServer(): bool
    {
        return DB::connection()->getDriverName() === 'sqlsrv';
    }
}
