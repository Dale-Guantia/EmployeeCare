<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'user_id',
        'comment',
        'attachment'
    ];

    protected $casts = [
        'attachment' => 'array',
    ];

    protected static function booted()
    {
        static::deleted(function ($comment) {
            $files = $comment->attachment ?: [];
            $comment->getConnection()->afterCommit(function () use ($files) {
                \App\Services\AttachmentStorage::deleteFiles($files);
            });
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }
}
