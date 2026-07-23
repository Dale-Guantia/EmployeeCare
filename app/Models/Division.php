<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Division extends Model
{
    use CrudTrait;
    use HasFactory;

    protected $fillable = [
        'division_name',
        'department_id'
    ];

    const CACHE_KEY = 'ref:divisions.all';

    protected static function booted()
    {
        static::saved(function () {
            Cache::forget(self::CACHE_KEY);
        });

        static::deleted(function () {
            Cache::forget(self::CACHE_KEY);
        });
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class, 'division_id');
    }

    public static function allCached(): \Illuminate\Support\Collection
    {
        return Cache::remember(self::CACHE_KEY, now()->addMinutes(15), function () {
            return static::all();
        });
    }
}
