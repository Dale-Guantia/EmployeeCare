<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use App\Services\TicketNotificationService;
use App\Models\Status;
use App\Models\Issue;
use App\Models\TicketReassignmentRequest;
use Carbon\Carbon;

class Ticket extends Model
{
    use CrudTrait;
    use HasFactory;

    protected $fillable = [
        'reference_id',
        'user_id',
        'department_id',
        'division_id',
        'status_id',
        'issue_id',
        'custom_issue',
        'is_custom_issue',
        'priority_id',
        'message',
        'attachments',
        'resolved_at',
        'reopened_at',
        'assigned_to',
        'resolved_by',
    ];

    protected $casts = [
        'is_custom_issue' => 'boolean',
        'attachments'     => 'array',
        'resolved_at'     => 'datetime',
        'reopened_at'     => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            // 1. Generate unique Reference ID
            do {
                $reference = 'TKT-' . strtoupper(substr(uniqid(), -6));
            } while (self::where('reference_id', $reference)->exists());

            $model->reference_id = $reference;

            // 2. Automatically assign logged-in user
            if (backpack_auth()->check() && empty($model->user_id)) {
                $model->user_id = backpack_user()->id;
            }

            // 3. Default status = Unassigned (ID 3 in your current setup)
            if (empty($model->status_id)) {
                $model->status_id = 3;
            }
        });

        static::saving(function ($model) {
            // Auto-fill ticket fields from selected issue.
            // Only run this on create, or when issue_id itself changed — otherwise this
            // clobbers manual department/division changes made via reassignment on every save.
            if ($model->issue_id && (!$model->exists || $model->isDirty('issue_id'))) {
                $issue = Issue::find($model->issue_id);

                if ($issue) {
                    $model->department_id = $issue->department_id;
                    $model->division_id   = $issue->division_id;
                    $model->priority_id   = $issue->priority_id;
                }
            }

            // Auto-set status when assignee changes
            if ($model->isDirty('assigned_to')) {
                if (!empty($model->assigned_to)) {
                    $assignedStatus = Status::where('status_name', 'Pending')->first();

                    if ($assignedStatus && !$model->isDirty('status_id')) {
                        $model->status_id = $assignedStatus->id;
                    }
                } else {
                    $unassignedStatus = Status::where('status_name', 'Unassigned')->first();

                    if ($unassignedStatus && !$model->isDirty('status_id')) {
                        $model->status_id = $unassignedStatus->id;
                    }
                }
            }

            // Handle resolved / reopened timestamps
            if ($model->isDirty('status_id')) {
                $resolvedStatusId = Status::where('status_name', 'Resolved')->value('id');
                $reopenedStatusId = Status::where('status_name', 'Reopened')->value('id');

                // When resolved
                if ($resolvedStatusId && (int) $model->status_id === (int) $resolvedStatusId) {
                    $model->resolved_at = now();
                    $model->resolved_by = backpack_auth()->check() ? backpack_user()->id : null;
                    $model->reopened_at = null;
                }
                // When reopened
                elseif ($reopenedStatusId && (int) $model->status_id === (int) $reopenedStatusId) {
                    $model->reopened_at = now();
                    $model->resolved_at = null;
                    $model->resolved_by = null;
                }
                // Any other status change (e.g. the auto-assign hook above flipping
                // a Reopened ticket to Pending on reassignment) moves the ticket out
                // of the Reopened state without going through either branch above —
                // clear the now-stale reopened_at so it doesn't linger.
                elseif ($model->reopened_at !== null) {
                    $model->reopened_at = null;
                }
            }
        });

        static::updating(function ($ticket) {
            $disk = 'public';

            $oldFiles = $ticket->getOriginal('attachments');
            $newFiles = $ticket->attachments ?? [];

            if (is_string($oldFiles)) {
                $oldFiles = json_decode($oldFiles, true);
            }

            if (is_string($newFiles)) {
                $newFiles = json_decode($newFiles, true);
            }

            $oldFiles = is_array($oldFiles) ? array_values(array_filter($oldFiles)) : [];
            $newFiles = is_array($newFiles) ? array_values(array_filter($newFiles)) : [];

            $filesToDelete = array_diff($oldFiles, $newFiles);

            foreach ($filesToDelete as $file) {
                if (!empty($file) && Storage::disk($disk)->exists($file)) {
                    Storage::disk($disk)->delete($file);
                }
            }
        });

        static::deleting(function ($ticket) {
            $disk = 'public';

            $files = $ticket->attachments ?? [];

            if (is_string($files)) {
                $files = json_decode($files, true);
            }

            $files = is_array($files) ? array_values(array_filter($files)) : [];

            foreach ($files as $file) {
                if (!empty($file) && Storage::disk($disk)->exists($file)) {
                    Storage::disk($disk)->delete($file);
                }
            }
        });

        static::created(function ($ticket) {
            $actor = backpack_auth()->check() ? backpack_user() : null;
            app(TicketNotificationService::class)->notifyTicketCreated($ticket, $actor);
        });

        static::updated(function ($ticket) {
            $actor = backpack_auth()->check() ? backpack_user() : null;

            if ($ticket->wasChanged('assigned_to')) {
                app(TicketNotificationService::class)->notifyTicketAssigned($ticket, $actor);
            }

            if ($ticket->wasChanged('status_id')) {
                $ticket->loadMissing('status');
                app(TicketNotificationService::class)->notifyTicketStatusChanged($ticket, $actor);

                $resolvedStatusId = Status::where('status_name', 'Resolved')->value('id');

                if ($resolvedStatusId && (int) $ticket->status_id === (int) $resolvedStatusId) {
                    $pending = $ticket->reassignmentRequests()
                        ->where('status', TicketReassignmentRequest::STATUS_PENDING)
                        ->get();

                    foreach ($pending as $request) {
                        $request->update(['status' => TicketReassignmentRequest::STATUS_CANCELLED]);
                    }
                }
            }
        });
    }

    public function setAttachmentsAttribute($value)
    {
        $disk             = 'public';
        $destination_path = 'attachments';

        // $value is the retained paths array passed by the controller.
        // Start from this list only — do NOT read $this->attachments here,
        // as that would re-merge the old DB value and undo any removals.
        if (is_string($value)) {
            $base = json_decode($value, true) ?? [];
        } elseif (is_array($value)) {
            $base = $value;
        } else {
            $base = [];
        }

        // Filter to only valid non-empty string paths (not uploaded file objects)
        $base = array_values(array_filter($base, function ($item) {
            return is_string($item) && !empty($item);
        }));

        // Append any newly uploaded files
        if (request()->hasFile('attachments')) {
            foreach ((array) request()->file('attachments') as $file) {
                if ($file && $file->isValid()) {
                    $fileName = time() . '_' . $file->getClientOriginalName();
                    $path     = $file->storeAs($destination_path, $fileName, $disk);
                    $base[]   = $path;
                }
            }
        }

        $this->attributes['attachments'] = json_encode(array_values($base));
    }

    public function getOverdueBadgeAttribute()
    {
        if (!$this->created_at) {
            return [
                'text'  => '-',
                'class' => 'badge badge-secondary',
                'style' => 'background-color:#6c757d; color:#fff; padding:5px 10px; border-radius:4px; font-weight:bold;',
            ];
        }

        static $resolvedStatusId = null;
        if ($resolvedStatusId === null) {
            $resolvedStatusId = Status::where('status_name', 'Resolved')->value('id');
        }
        $createdAt = Carbon::parse($this->created_at);

        // CASE A: Resolved ticket
        if ((int) $this->status_id === (int) $resolvedStatusId) {
            if (!$this->resolved_at) {
                return [
                    'text'  => 'Resolved',
                    'class' => 'badge badge-success',
                    'style' => 'background-color:#28a745; color:#fff; padding:5px 10px; border-radius:4px; font-weight:bold;',
                ];
            }

            $resolvedAt = Carbon::parse($this->resolved_at);
            $hoursTaken = $createdAt->diffInHours($resolvedAt);

            if ($hoursTaken > 72) {
                $overdueMinutes = $createdAt->copy()->addHours(72)->diffInMinutes($resolvedAt);

                $days = intdiv($overdueMinutes, 1440);
                $hours = intdiv($overdueMinutes % 1440, 60);
                $minutes = $overdueMinutes % 60;

                return [
                    'text'  => "Resolved overdue",
                    'class' => 'badge badge-danger',
                    'style' => 'background-color:#dc3545; color:#fff; padding:5px 10px; border-radius:4px; font-weight:bold;',
                ];
            }

            return [
                'text'  => 'Resolved on time',
                'class' => 'badge badge-success',
                'style' => 'background-color:#28a745; color:#fff; padding:5px 10px; border-radius:4px; font-weight:bold;',
            ];
        }

        // CASE B: Open / pending ticket
        $deadline = $createdAt->copy()->addDays(3);
        $now = now();

        if ($createdAt->lt($now->copy()->subDays(3))) {
            $overdueMinutes = $deadline->diffInMinutes($now);

            $days = intdiv($overdueMinutes, 1440);
            $hours = intdiv($overdueMinutes % 1440, 60);
            $minutes = $overdueMinutes % 60;

            return [
                'text'  => "{$days}d {$hours}h {$minutes}m overdue",
                'class' => 'badge badge-danger',
                'style' => 'background-color:#dc3545; color:#fff; padding:5px 10px; border-radius:4px; font-weight:bold;',
            ];
        }

        $remainingMinutes = $now->diffInMinutes($deadline);

        $days = intdiv($remainingMinutes, 1440);
        $hours = intdiv($remainingMinutes % 1440, 60);
        $minutes = $remainingMinutes % 60;

        return [
            'text'  => "{$days}d {$hours}h {$minutes}m left",
            'class' => 'badge badge-warning',
            'style' => 'background-color:#ffc107; color:#000; padding:5px 10px; border-radius:4px; font-weight:bold;',
        ];
    }

    public function issue()
    {
        return $this->belongsTo(Issue::class, 'issue_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function status()
    {
        return $this->belongsTo(Status::class, 'status_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function division()
    {
        return $this->belongsTo(Division::class, 'division_id');
    }

    public function priority()
    {
        return $this->belongsTo(Priority::class, 'priority_id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function comments()
    {
        return $this->hasMany(TicketComment::class)->latest();
    }

    public function reassignmentRequests()
    {
        return $this->hasMany(TicketReassignmentRequest::class);
    }

    public function pendingReassignmentRequest()
    {
        return $this->hasOne(TicketReassignmentRequest::class)
            ->where('status', TicketReassignmentRequest::STATUS_PENDING)
            ->latestOfMany();
    }
}
