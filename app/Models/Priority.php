<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Priority extends Model
{
    use CrudTrait;
    use HasFactory;
    use \App\Models\Concerns\HasMasterDataLifecycle;
    use \App\Models\Concerns\HasPermanentCode;

    public const CODE_COLUMN = 'priority_code';
    public const HIGH = 'high';
    public const MEDIUM = 'medium';
    public const LOW = 'low';

    protected $fillable = [
        'priority_name',
        'priority_code',
        'priority_color',
        'is_active',
    ];

    protected $attributes = ['is_active' => true];

    protected $casts = ['is_active' => 'boolean'];

    public function issues()
    {
        return $this->hasMany(Issue::class);
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }
}
