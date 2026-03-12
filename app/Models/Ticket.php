<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

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
        'resolved_by'
    ];

    protected $casts = [
        'is_custom_issue' => 'boolean',
        'attachments'=> 'array',
        'resolved_at'=> 'date',
        'reopened_at'=> 'date',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // 1. Generate Reference ID (from our previous fix)
            do {
                // Generate the short ID
                $reference = 'TKT-' . strtoupper(substr(uniqid(), -6));
                // Check if it already exists in the database
            } while (self::where('reference_id', $reference)->exists());

            $model->reference_id = $reference;

            // 2. Automatically assign the logged-in user's ID
            if (backpack_auth()->check()) {
                $model->user_id = backpack_user()->id;
            }

            // 3. Set default status to 3 (Unassigned)
            if (empty($model->status_id)) {
                $model->status_id = 3;
            }
        });

        // Triggered right before a record is saved to the database
        static::saving(function ($model) {
            if ($model->issue_id) {
                $issue = \App\Models\Issue::find($model->issue_id);
                if ($issue) {
                    // Auto-fill the ticket fields from the issue's data
                    $model->department_id = $issue->department_id;
                    $model->division_id = $issue->division_id;
                    $model->priority_id = $issue->priority_id;
                }
            }

            if ($model->isDirty('assigned_to')) {
                if (!empty($model->assigned_to)) {
                    $assignedStatus = \App\Models\Status::where('status_name', 'Pending')->first();
                    if ($assignedStatus && !$model->isDirty('status_id')) {
                        $model->status_id = $assignedStatus->id;
                    }
                } else {
                    $unassignedStatus = \App\Models\Status::where('status_name', 'Unassigned')->first();
                    if ($unassignedStatus && !$model->isDirty('status_id')) {
                        $model->status_id = $unassignedStatus->id;
                    }
                }
            }

            if ($model->isDirty('status_id')) {
                $resolvedStatus = \App\Models\Status::where('status_name', 'Resolved')->first();
                if ($resolvedStatus && $model->status_id == $resolvedStatus->id) {
                    $model->resolved_at = now();
                    $model->resolved_by = backpack_user()->id; // Record who did it
                }
            }
        });
    }

    public function setAttachmentsAttribute($value)
    {
        $attribute_name = "attachments";
        $disk = "public";
        $destination_path = "attachments";

        // 1. Get the current files from the database (as an array)
        $attribute_value = $this->{$attribute_name} ?? [];
        if (is_string($attribute_value)) {
            $attribute_value = json_decode($attribute_value, true) ?? [];
        }

        // 2. Handle files marked for deletion
        // Backpack sends an array of files to be cleared in the request
        $files_to_clear = request()->get('clear_'.$attribute_name) ?? [];
        foreach ($files_to_clear as $key => $filename) {
            Storage::disk($disk)->delete($filename);
            $attribute_value = array_diff($attribute_value, [$filename]);
        }

        // 3. Handle new uploaded files
        if (request()->hasFile($attribute_name)) {
            foreach (request()->file($attribute_name) as $file) {
                if ($file->isValid()) {
                    // YOUR CUSTOM FORMAT: time() + original name
                    $fileName = time() . '_' . $file->getClientOriginalName();

                    // Store the file
                    $path = $file->storeAs($destination_path, $fileName, $disk);

                    // Add the new path to our array
                    $attribute_value[] = $path;
                }
            }
        }

        // 4. Update the attribute (Backpack expects a JSON string or array depending on your cast)
        $this->attributes[$attribute_name] = json_encode(array_values($attribute_value));
    }

    public function issue(){
        return $this->belongsTo(Issue::class, 'issue_id');
    }
    public function user() {
        return $this->belongsTo(User::class);
    }
    public function status() {
        return $this->belongsTo(Status::class, 'status_id');
    }
    public function department() {
        return $this->belongsTo(Department::class, 'department_id');
    }
    public function division() {
        return $this->belongsTo(Division::class, 'division_id');
    }
    public function priority() {
        return $this->belongsTo(Priority::class, 'priority_id');
    }
    public function assignee()
    {
        // This connects the 'assigned_to' column to the Users table
        return $this->belongsTo(User::class, 'assigned_to');
    }
    public function comments()
    {
        return $this->hasMany(TicketComment::class)->latest();
    }
}
