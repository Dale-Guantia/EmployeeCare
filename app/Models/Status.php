<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Status extends Model
{
    use CrudTrait;
    use HasFactory;

    protected $fillable = [
        'status_name',
        'status_color'
    ];

    const CACHE_KEY = 'ref:statuses.by_name';

    protected static function booted()
    {
        static::saved(function () {
            Cache::forget(self::CACHE_KEY);
        });

        static::deleted(function () {
            Cache::forget(self::CACHE_KEY);
        });
    }

    public function tickets()
    {
        return $this->hasMany(\App\Models\Ticket::class);
    }

    /**
     * All status ids keyed by status_name, cache-backed (15 min TTL,
     * invalidated immediately on any Status save/delete) so the same
     * lookup that used to run as Status::where('status_name', ...)->value('id')
     * in a dozen places across the app now hits the DB at most once per
     * cache window instead of once per call site per request.
     */
    public static function idsByName(): \Illuminate\Support\Collection
    {
        return Cache::remember(self::CACHE_KEY, now()->addMinutes(15), function () {
            return static::pluck('id', 'status_name');
        });
    }

    public static function idByName(string $name): ?int
    {
        $id = self::idsByName()->get($name);

        return $id !== null ? (int) $id : null;
    }
}
