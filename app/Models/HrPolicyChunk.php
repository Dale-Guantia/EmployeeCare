<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HrPolicyChunk extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'chunk_index',
        'section_title',
        'content',
        'search_vector',
    ];

    // FIX: Added chunk_index cast to integer.
    // Without this, chunk_index is returned as a string by MySQL,
    // which can cause unexpected sort/comparison bugs if used in PHP logic.
    protected $casts = [
        'chunk_index' => 'integer',
    ];

    public function document()
    {
        return $this->belongsTo(HrPolicyDocument::class, 'document_id');
    }
}
