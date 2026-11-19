<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Department extends Model
{
    use CrudTrait;
    use HasFactory;
    use \App\Models\Concerns\HasMasterDataLifecycle;
    use \App\Models\Concerns\HasPermanentCode;

    public const CODE_COLUMN = 'department_code';
    public const HRDO = 'hrdo';

    protected $fillable = [
        'department_name',
        'department_code',
        'is_active',
    ];

    protected $attributes = ['is_active' => true];
    protected $casts = ['is_active' => 'boolean'];

    const CACHE_KEY = 'ref:departments.all';

    protected static function booted()
    {
        static::saved(function () {
            Cache::forget(self::CACHE_KEY);
        });

        static::deleted(function () {
            Cache::forget(self::CACHE_KEY);
        });
    }

    public static function allCached(): \Illuminate\Support\Collection
    {
        return Cache::remember(self::CACHE_KEY, now()->addMinutes(15), function () {
            return static::all();
        });
    }
}
