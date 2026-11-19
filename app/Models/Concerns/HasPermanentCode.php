<?php

namespace App\Models\Concerns;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

trait HasPermanentCode
{
    public static function codeCacheKey(): string
    {
        return 'ref:' . (new static)->getTable() . '.by_code';
    }

    public static function idsByCode(): Collection
    {
        return Cache::remember(static::codeCacheKey(), now()->addMinutes(15),
            fn () => static::pluck('id', static::CODE_COLUMN));
    }

    public static function idByCode(string $code): ?int
    {
        $id = static::idsByCode()->get($code);
        return $id === null ? null : (int) $id;
    }

    public function hasCode(string $code): bool
    {
        return $this->getAttribute(static::CODE_COLUMN) === $code;
    }

    public static function forgetCodeCache(): void
    {
        Cache::forget(static::codeCacheKey());
    }
}
