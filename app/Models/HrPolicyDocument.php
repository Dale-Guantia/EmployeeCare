<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Backpack\CRUD\app\Models\Traits\CrudTrait;

class HrPolicyDocument extends Model
{
    use HasFactory;
    use CrudTrait;

    // FIX: Valid status values defined as a constant.
    // Used for validation in controllers and for clarity when reading the code.
    // 'pending' = uploaded, ingestion not yet run/queued.
    // 'needs_review' = ingested but OCR confidence was below the configured
    // threshold — chunks exist but the document is intentionally excluded
    // from scopeActive() until a human approves it or re-uploads a cleaner scan.
    const STATUSES = ['pending', 'processing', 'active', 'needs_review', 'failed', 'inactive'];

    protected $fillable = [
        'title',
        'filename',
        'file_path',
        'file_hash',
        'category',
        'is_active',
        'effective_date',
        'status',
        'chunk_count',
        'ingest_error',
        'extracted_text',
        'ocr_used',
        'ocr_confidence',
    ];

    protected $casts = [
        'is_active'      => 'boolean',
        'effective_date' => 'date',
        // FIX: Cast chunk_count to integer so it's always a number, not a string.
        'chunk_count'    => 'integer',
        'ocr_used'       => 'boolean',
        'ocr_confidence' => 'integer',
    ];

    public function chunks()
    {
        return $this->hasMany(HrPolicyChunk::class, 'document_id');
    }

    // FIX: getStatusBadgeAttribute now escapes the status value before injecting
    // it into HTML. The status comes from the database which is trusted, but
    // escaping is a good habit and prevents issues if the value is ever unexpected.
    public function getStatusBadgeAttribute(): string
    {
        $map = [
            'pending'      => 'secondary',
            'active'       => 'success',
            'processing'   => 'warning',
            'needs_review' => 'warning',
            'failed'       => 'danger',
            'inactive'     => 'secondary',
        ];
        $class  = $map[$this->status] ?? 'secondary';
        $label  = e(ucwords(str_replace('_', ' ', $this->status)));
        return '<span class="badge badge-' . $class . '">' . $label . '</span>';
    }

    // NEW: OCR badge for the list view
    // Shows whether this document required OCR during ingestion
    public function getOcrBadgeAttribute(): string
    {
        if ($this->ocr_used) {
            return '<span class="badge badge-info" title="This was a scanned PDF — OCR was used to extract text. Accuracy may vary.">'
                 . '<i class="la la-eye"></i> OCR</span>';
        }
        return '<span class="badge badge-light text-muted">Text PDF</span>';
    }

    // FIX: Added scope for active documents — used in PolicyRetriever and
    // anywhere else that needs only chatbot-visible documents.
    // Usage: HrPolicyDocument::active()->get()
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->where('status', 'active');
    }
}
