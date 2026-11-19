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
    use \App\Models\Concerns\HasMasterDataLifecycle;
    use \App\Models\Concerns\HasPermanentCode;

    public const CODE_COLUMN = 'status_code';
    public const RESOLVED = 'resolved';
    public const PENDING = 'pending';
    public const UNASSIGNED = 'unassigned';
    public const REOPENED = 'reopened';
    public const CORE_CODES = [self::UNASSIGNED, self::PENDING, self::RESOLVED, self::REOPENED];

    protected $attributes = ['is_active' => true];
    protected $casts = ['is_active' => 'boolean'];

    protected $fillable = [
        'status_name',
        'status_code',
        'status_color',
        'is_active',
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

    /** Legacy display-label lookup. Workflow logic must use idsByCode(). */
    public static function idsByName(): \Illuminate\Support\Collection
    {
        return Cache::remember(self::CACHE_KEY, now()->addMinutes(15), function () {
            return static::pluck('id', 'status_name');
        });
    }

    /** Legacy display-label lookup. Workflow logic must use idByCode(). */
    public static function idByName(string $name): ?int
    {
        $id = self::idsByName()->get($name);

        return $id !== null ? (int) $id : null;
    }
}
