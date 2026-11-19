<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Issue extends Model
{
    use CrudTrait;
    use HasFactory;
    use \App\Models\Concerns\HasMasterDataLifecycle;

    protected $fillable = [
        'department_id',
        'division_id',
        'priority_id',
        'issue_description',
        'icon',
        'is_active',
    ];

    protected $attributes = ['is_active' => true];

    protected $casts = ['is_active' => 'boolean'];

    public function department()
    {
        return $this->belongsTo(\App\Models\Department::class, 'department_id');
    }

    public function division()
    {
        return $this->belongsTo(\App\Models\Division::class, 'division_id');
    }

    public function priority()
    {
        return $this->belongsTo(\App\Models\Priority::class, 'priority_id');
    }
}
