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

    protected $fillable = [
        'department_name',
    ];

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
