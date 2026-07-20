<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HrChatLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'session_id',
        'conversation_id',
        'question',
        'answer',
        'matched_chunk_ids',
        'feedback',
    ];

    protected $casts = [
        'matched_chunk_ids' => 'array',
        'feedback'          => 'integer',
        'user_id'           => 'integer',
        'conversation_id'   => 'integer',
    ];

    // FIX: Added relationship to User so logs can be joined to employee info
    // in future HRDO reporting queries without raw SQL joins.
    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    public function conversation()
    {
        return $this->belongsTo(HrConversation::class, 'conversation_id');
    }

    // FIX: Added a scope for filtering helpful/unhelpful answers.
    // Usage: HrChatLog::helpful()->count()
    //        HrChatLog::unhelpful()->get()
    public function scopeHelpful($query)
    {
        return $query->where('feedback', 1);
    }

    public function scopeUnhelpful($query)
    {
        return $query->where('feedback', -1);
    }
}
