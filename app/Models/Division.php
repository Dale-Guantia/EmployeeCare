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
    use \App\Models\Concerns\HasMasterDataLifecycle;
    use \App\Models\Concerns\HasPermanentCode;

    public const CODE_COLUMN = 'division_code';
    public const PERFORMANCE_MANAGEMENT = 'pm';
    public const LEARNING_AND_DEVELOPMENT = 'lnd';
    public const RSP = 'rsp';
    public const CLAIMS = 'claims';
    public const RECORDS = 'records';
    public const PAYROLL = 'payroll';
    public const ADMINISTRATIVE = 'admin';
    public const INFORMATION_TECHNOLOGY = 'it';
    public const DEPARTMENT_HEAD = 'dh';

    protected $fillable = [
        'division_name',
        'division_code',
        'department_id',
        'is_active',
    ];

    protected $attributes = ['is_active' => true];
    protected $casts = ['is_active' => 'boolean'];

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
