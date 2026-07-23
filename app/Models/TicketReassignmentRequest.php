<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketReassignmentRequest extends Model
{
    use HasFactory;

    const STATUS_PENDING = 'pending';
    const STATUS_ACCEPTED = 'accepted';
    const STATUS_REJECTED = 'rejected';
    const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'ticket_id',
        'from_department_id',
        'from_division_id',
        'to_department_id',
        'to_division_id',
        'requested_by',
        'reason',
        'status',
        'responded_by',
        'responded_at',
        'response_note',
    ];

    protected $casts = [
        'responded_at' => 'datetime',
    ];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function responder()
    {
        return $this->belongsTo(User::class, 'responded_by');
    }

    public function fromDepartment()
    {
        return $this->belongsTo(Department::class, 'from_department_id');
    }

    public function fromDivision()
    {
        return $this->belongsTo(Division::class, 'from_division_id');
    }

    public function toDepartment()
    {
        return $this->belongsTo(Department::class, 'to_department_id');
    }

    public function toDivision()
    {
        return $this->belongsTo(Division::class, 'to_division_id');
    }
}
