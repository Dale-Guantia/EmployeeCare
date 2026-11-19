<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Survey extends Model
{
    use HasFactory;
    use \App\Models\Concerns\LocksOfficeReferences;

    protected static function booted()
    {
        static::saving(function ($survey) {
            if ((!$survey->exists || $survey->isDirty('issue_id'))
                && !\App\Services\IssueSelectionRules::options(Issue::query())->whereKey($survey->issue_id)->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'issue_id' => 'The selected service is unavailable or archived. Choose an active service.',
                ]);
            }
        });
    }

    protected $table = 'surveys';

    protected $fillable = [
        'submission_id',
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
