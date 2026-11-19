<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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

    protected $stagedAttachmentPaths = [];
    protected $initialCreationInProgress = false;

    protected function newReference(): string
    {
        return 'TKT-' . strtoupper(bin2hex(random_bytes(3)));
    }

    public function save(array $options = [])
    {
        $existed = $this->exists;
        $previousCreationState = $this->initialCreationInProgress;
        if (!$existed) {
            $this->initialCreationInProgress = true;
        }
        try {
            for ($attempt = 0; $attempt < 5; $attempt++) {
                try {
                    $saved = $this->getConnection()->transaction(function () use ($options) {
                        \App\Services\MasterDataLocks::acquire($this);
                        return parent::save($options);
                    });
                    if (!$saved) {
                        throw new \RuntimeException('Ticket persistence was cancelled.');
                    }
                    $ticketId = $this->getKey();
                    $connectionName = $this->getConnectionName();
                    app(\App\Services\AttachmentTransactions::class)->track($this->getConnection(), $this->stagedAttachmentPaths, function () use ($ticketId, $connectionName) {
                        return optional(Ticket::on($connectionName)->find($ticketId))->attachments ?: [];
                    });
                    $this->stagedAttachmentPaths = [];
                    return $saved;
                } catch (\Illuminate\Database\QueryException $e) {
                    $code = (int) ($e->errorInfo[1] ?? 0);
                    $referenceCollision = in_array($code, [19, 1062, 2601, 2627], true)
                        && preg_match('/(?:tickets\.reference_id|tickets_reference_id_unique)/i', $e->getMessage());
                    if ($existed || !$referenceCollision || $attempt === 4) {
                        throw $e;
                    }
                    $this->exists = false;
                    $this->wasRecentlyCreated = false;
                    unset($this->attributes[$this->getKeyName()]);
                }
            }
        } catch (\Throwable $e) {
            $ticketId = $this->getKey();
            \App\Services\AttachmentStorage::discardUnreferenced($this->stagedAttachmentPaths, function () use ($ticketId) {
                return $ticketId ? (optional($this->newQuery()->find($ticketId))->attachments ?: []) : [];
            });
            $this->exists = $existed;
            if (!$existed) {
                unset($this->attributes[$this->getKeyName()]);
            }
            $this->stagedAttachmentPaths = [];
            throw $e;
        } finally {
            $this->initialCreationInProgress = $previousCreationState;
        }
    }

    public function delete()
    {
        if (!$this->exists) {
            return parent::delete();
        }

        try {
            return $this->getConnection()->transaction(function () {
                $deleted = parent::delete();
                if (!$deleted) {
                    throw new \RuntimeException('Ticket deletion was cancelled.');
                }
                return $deleted;
            });
        } catch (\Throwable $e) {
            $this->exists = true;
            throw $e;
        }
    }

    protected static function booted()
    {
        static::deleting(function ($ticket) {
            // Delete models individually so comment attachment cleanup is scheduled too.
            $ticket->comments()->reorder()->chunkById(100, function ($comments) {
                foreach ($comments as $comment) {
                    if (!$comment->delete()) {
                        throw new \RuntimeException('Ticket comment deletion was cancelled.');
                    }
                }
            });
        });

        static::creating(function ($model) {
            // 1. Generate unique Reference ID
            $reference = $model->newReference();

            $model->reference_id = $reference;

            // 2. Automatically assign logged-in user
            if (backpack_auth()->check() && empty($model->user_id)) {
                $model->user_id = backpack_user()->id;
            }

            // Resolve the permanent workflow identity independently of labels and seed order.
            if (empty($model->status_id)) {
                $model->status_id = Status::idByCode(Status::UNASSIGNED);
                if (!$model->status_id) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['status_id' => 'The required Unassigned workflow status is missing. Restore the workflow configuration first.']);
                }
            }
        });

        static::saving(function ($model) {
            if ($model->status_id && (!$model->exists || $model->isDirty('status_id'))) {
                $status = Status::find($model->status_id);
                if (!$status || !$status->is_active || !\App\Services\MasterDataRules::isCoreStatus($status)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'status_id' => 'Choose an active status with a supported workflow.',
                    ]);
                }
            }
            if ($model->priority_id && (!$model->exists || $model->isDirty('priority_id'))
                && !Priority::whereKey($model->priority_id)->where('is_active', true)->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['priority_id' => 'Choose an active priority.']);
            }
            // Auto-fill ticket fields from selected issue.
            // Only run this on create, or when issue_id itself changed — otherwise this
            // clobbers manual department/division changes made via reassignment on every save.
            if ($model->issue_id && (!$model->exists || $model->isDirty('issue_id'))) {
                $issue = Issue::find($model->issue_id);

                if ($issue) {
                    if (!$issue->is_active) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'issue_id' => 'The selected issue has been archived. Please choose an active issue.',
                        ]);
                    }
                    $model->department_id = $issue->department_id;
                    $model->division_id   = $issue->division_id;
                    // An archived issue priority remains historical; new assignments omit it.
                    $model->priority_id   = $issue->priority?->is_active ? $issue->priority_id : null;
                }
            }

            // Auto-set status when assignee changes
            if ($model->isDirty('assigned_to')) {
                if (!empty($model->assigned_to)) {
                    $assignedStatusId = Status::idByCode(Status::PENDING);

                    if ($assignedStatusId && !$model->isDirty('status_id')) {
                        $model->status_id = $assignedStatusId;
                    }
                } else {
                    $unassignedStatusId = Status::idByCode(Status::UNASSIGNED);

                    if ($unassignedStatusId && !$model->isDirty('status_id')) {
                        $model->status_id = $unassignedStatusId;
                    }
                }
            }

            $resolvedStatus = Status::idByCode(Status::RESOLVED);
            if (!$model->exists || $model->isDirty(['department_id', 'division_id'])
                || ($model->isDirty('status_id') && (int) $model->status_id !== (int) $resolvedStatus)) {
                \App\Services\OfficeSelectionRules::assertAvailable($model->department_id, $model->division_id);
            }

            // Handle resolved / reopened timestamps
            if ($model->isDirty('status_id')) {
                $resolvedStatusId = Status::idByCode(Status::RESOLVED);
                $reopenedStatusId = Status::idByCode(Status::REOPENED);

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
                else {
                    $model->reopened_at = null;
                    $model->resolved_at = null;
                    $model->resolved_by = null;
                }
            }
        });

        static::updated(function ($ticket) {
            if (!$ticket->wasChanged('attachments')) {
                return;
            }
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

            $ticket->getConnection()->afterCommit(function () use ($filesToDelete) {
                \App\Services\AttachmentStorage::deleteFiles($filesToDelete);
            });
        });

        static::deleted(function ($ticket) {
            $files = $ticket->attachments ?? [];

            if (is_string($files)) {
                $files = json_decode($files, true);
            }

            $files = is_array($files) ? array_values(array_filter($files)) : [];

            $ticket->getConnection()->afterCommit(function () use ($files) {
                \App\Services\AttachmentStorage::deleteFiles($files);
            });
        });

        static::created(function ($ticket) {
            $actor = backpack_auth()->check() ? backpack_user() : null;
            app(TicketNotificationService::class)->notifyTicketCreated($ticket, $actor);
        });

        static::updated(function ($ticket) {
            $actor = backpack_auth()->check() ? backpack_user() : null;

            if ($ticket->wasChanged('assigned_to')) {
                app(TicketNotificationService::class)->notifyTicketAssigned($ticket, $actor, $ticket->initialCreationInProgress);
            }

            if ($ticket->wasChanged('status_id')) {
                $ticket->loadMissing('status');
                // Auto-assignment establishes the initial Pending state during creation.
                // Keep assignment/creation notifications, and notify subsequent status changes.
                if (!$ticket->initialCreationInProgress) {
                    app(TicketNotificationService::class)->notifyTicketStatusChanged($ticket, $actor);
                }

                $resolvedStatusId = Status::idByCode(Status::RESOLVED);

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

    public function scopeVisibleTo($query, $user)
    {
        if (!$user || !$user->is_active || !$user->can('ticket.view')) {
            return $query->whereRaw('1 = 0');
        }
        if ($user->hasRole('admin')) {
            return $query;
        }

        return $query->where(function ($query) use ($user) {
            $query->where('user_id', $user->id);
            if ($user->hasRole('dept_head') && $user->department_id) {
                $query->orWhere('department_id', $user->department_id)
                    ->orWhereHas('pendingReassignmentRequest', function ($pending) use ($user) {
                        $pending->where('to_department_id', $user->department_id);
                    });
            }
            if ($user->hasRole('div_head') && $user->division_id) {
                $query->orWhere('division_id', $user->division_id)
                    ->orWhereHas('pendingReassignmentRequest', function ($pending) use ($user) {
                        $pending->where('to_division_id', $user->division_id);
                    });
            }
            if ($user->hasRole('hr_staff')) {
                $query->orWhere('assigned_to', $user->id);
            }
        });
    }

    public function setAttachmentsAttribute($value)
    {
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
            $paths = \App\Services\AttachmentStorage::storeFiles((array) request()->file('attachments'));
            $this->stagedAttachmentPaths = array_merge($this->stagedAttachmentPaths, $paths);
            $base = array_merge($base, $paths);
        }

        $this->attributes['attachments'] = json_encode(array_values($base));
    }

    public function getOverdueBadgeAttribute()
    {
        if (!$this->created_at) {
            return [
                'text'  => '-',
                'class' => 'badge bg-secondary',
                'style' => 'color:#000000; padding:5px 10px; border-radius:4px; font-weight:bold;',
            ];
        }

        $resolvedStatusId = Status::idByCode(Status::RESOLVED);
        $createdAt = Carbon::parse($this->created_at);

        // CASE A: Resolved ticket
        if ((int) $this->status_id === (int) $resolvedStatusId) {
            if (!$this->resolved_at) {
                return [
                    'text'  => 'Resolved',
                    'class' => 'badge bg-success',
                    'style' => 'color:#000000; padding:5px 10px; border-radius:4px; font-weight:bold;',
                ];
            }

            $resolvedAt = Carbon::parse($this->resolved_at);
            $hoursTaken = (int) $createdAt->diffInHours($resolvedAt, true);

            if ($hoursTaken > 72) {
                $overdueMinutes = (int) $createdAt->copy()->addHours(72)->diffInMinutes($resolvedAt, true);

                $days = intdiv($overdueMinutes, 1440);
                $hours = intdiv($overdueMinutes % 1440, 60);
                $minutes = $overdueMinutes % 60;

                return [
                    'text'  => "Resolved overdue",
                    'class' => 'badge',
                    'style' => 'background-color:#FE7C7C; color:#000000; padding:5px 10px; border-radius:4px; font-weight:bold;',
                ];
            }

            return [
                'text'  => 'Resolved on time',
                'class' => 'badge',
                'style' => 'background-color:#42ffc6; color:#000000; padding:5px 10px; border-radius:4px; font-weight:bold;',
            ];
        }

        // CASE B: Open / pending ticket
        $deadline = $createdAt->copy()->addDays(3);
        $now = now();

        if ($createdAt->lt($now->copy()->subDays(3))) {
            $overdueMinutes = (int) $deadline->diffInMinutes($now, true);

            $days = intdiv($overdueMinutes, 1440);
            $hours = intdiv($overdueMinutes % 1440, 60);
            $minutes = $overdueMinutes % 60;

            return [
                'text'  => "{$days}d {$hours}h {$minutes}m overdue",
                'class' => 'badge',
                'style' => 'background-color:#FE7C7C; color:#000000; padding:5px 10px; border-radius:4px; font-weight:bold;',
            ];
        }

        $remainingMinutes = (int) $now->diffInMinutes($deadline, true);

        $days = intdiv($remainingMinutes, 1440);
        $hours = intdiv($remainingMinutes % 1440, 60);
        $minutes = $remainingMinutes % 60;

        return [
            'text'  => "{$days}d {$hours}h {$minutes}m left",
            'class' => 'badge',
            'style' => 'background-color:#fed971; color:#000000; padding:5px 10px; border-radius:4px; font-weight:bold;',
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
