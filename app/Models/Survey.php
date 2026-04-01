<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Survey extends Model
{
    use HasFactory;

    protected $table = 'surveys';

    protected $fillable = [
        'user_id',
        'issue_id',
        'submission_date',
        'timeliness_rating',
        'handling_rating',
        'quality_rating',
        'overall_rating',
    ];

    public function staff()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function service()
    {
        return $this->belongsTo(Issue::class, 'issue_id');
    }
}
