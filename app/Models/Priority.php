<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Priority extends Model
{
    use CrudTrait;
    use HasFactory;

    protected $fillable = [
        'priority_name',
        'priority_color'
    ];
}
