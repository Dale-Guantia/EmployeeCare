<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Division extends Model
{
    use CrudTrait;
    use HasFactory;

    protected $fillable = [
        'division_name',
        'department_id'
    ];

    public function department()
    {
        return $this->belongsTo(\App\Models\Department::class, 'department_id');
    }
}
