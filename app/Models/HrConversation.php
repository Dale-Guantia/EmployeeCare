<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class HrConversation extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'title', 'is_starred', 'last_message_at'];

    protected $casts = [
        'is_starred'      => 'boolean',
        'last_message_at' => 'datetime',
        'user_id'         => 'integer',
    ];

    public function logs()
    {
        return $this->hasMany(HrChatLog::class, 'conversation_id')->orderBy('id');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    public function scopeForUser($q, $userId)
    {
        return $q->where('user_id', $userId);
    }

    // Sidebar order: starred first, then most recently active.
    public function scopeSidebarOrder($q)
    {
        return $q->orderByDesc('is_starred')->orderByDesc('last_message_at')->orderByDesc('id');
    }

    /** Derive a short title from the first question. */
    public static function titleFrom(string $question): string
    {
        $t = trim(preg_replace('/\s+/', ' ', $question));
        return Str::limit($t, 60, '…') ?: 'New conversation';
    }
}
