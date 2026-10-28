<?php
namespace App\Services;

use App\Models\HrPolicyDocument;
use App\Models\HrPolicyChunk;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use App\Jobs\IngestPolicyDocumentJob;
use App\Exceptions\PolicyIngestionDispatchException;
use App\Exceptions\PolicyIngestionSetupException;

class PolicyIngestService
{
    protected $ocr;

    // Inject OcrService so it can be mocked in tests
    // and so the OCR logic stays in one dedicated place
    public function __construct(OcrService $ocr)
    {
        $this->ocr = $ocr;
    }

    /**
     * Store the uploaded PDF and create the document record.
     * Does NOT run ingestion — the caller dispatches IngestPolicyDocumentJob.
     */
    public function store(UploadedFile $file, array $data): HrPolicyDocument
    {
        $this->assertUploadReady();
        $path     = $file->storeAs('policies', Str::uuid() . '.pdf');
        if (!$path) {
            throw new \RuntimeException('Could not store the policy PDF.');
        }
        $fullPath = Storage::path($path);

        try {
            return HrPolicyDocument::create([
                'title'          => $data['title'],
                'filename'       => $file->getClientOriginalName(),
                'file_path'      => $path,
                'file_hash'      => hash_file('sha256', $fullPath),
                'category'       => $data['category'],
                'effective_date' => $data['effective_date'] ?? null,
                'is_active'      => true,
                'status'         => 'pending',
                'chunk_count'    => 0,
                'ocr_used'       => false,
                'ingestion_version' => (string) Str::uuid(),
            ]);
        } catch (\Throwable $e) {
            // Reuse reference-aware compensation; retain the file if commit state is uncertain.
            $this->deleteStoredFile($path);
            throw $e;
        }
    }

    public function assertUploadReady(): void
    {
        try {
            $schema = (new HrPolicyDocument)->getConnection()->getSchemaBuilder();
            $ready = $schema->hasColumns('hr_policy_documents', ['ingestion_version', 'ingestion_queued_at', 'ingestion_started_at'])
                && $schema->hasColumns('policy_jobs', ['id', 'queue', 'payload', 'attempts', 'reserved_at', 'available_at', 'created_at']);
        } catch (\Throwable $e) {
            throw new PolicyIngestionSetupException('Policy upload is temporarily unavailable because the database could not be checked. Please retry or contact your administrator.');
        }
        if (!$ready) {
            throw new PolicyIngestionSetupException('Policy upload is unavailable until an administrator applies the policy ingestion queue migration (2026_10_14_110000_add_policy_ingestion_queue). No policy was saved.');
        }
    }

    /**
     * Run the full ingest pipeline: per-page text extraction (native text
     * layer where present, OCR only for pages that need it), a quality gate
     * on OCR confidence, then chunking with page-number attribution.
     *
     * @param bool $forceReExtract Skip the file-hash extraction cache — used
     *   by the admin "re-run ingestion" action so it actually re-attempts
     *   OCR instead of silently reusing a previous bad/cached result.
     */
    public function ingest(HrPolicyDocument $document, bool $forceReExtract = false, ?string $version = null): void
    {
        set_time_limit(IngestPolicyDocumentJob::TIMEOUT);
        $claim = HrPolicyDocument::whereKey($document->id);
        if ($version !== null) {
            $claim->where('ingestion_version', $version)->where('status', 'pending');
        } else {
            // CLI callers share the same claim; a second ingestion cannot overlap a worker.
            $version = $document->ingestion_version ?: (string) Str::uuid();
            $claim->where('ingestion_version', $document->ingestion_version)->where('status', '!=', 'processing');
        }
        if (!$claim->update(['status' => 'processing', 'ingestion_version' => $version, 'ingestion_started_at' => now()])) { return; }
        $document->refresh();

        try {
            $fullPath = Storage::path($document->file_path);

            if (!file_exists($fullPath)) {
                throw new \RuntimeException(
                    "PDF file not found on disk: {$document->file_path}"
                );
            }

            $cached = $forceReExtract ? null : $this->findCachedExtraction($document);

            if ($cached !== null) {
                [$pages, $usedOcr, $confidence] = $cached;
                Log::info("[PolicyIngest] Reusing cached extraction for '{$document->title}' (identical file already OCR'd elsewhere)");
            } else {
                $result     = $this->ocr->extractDocument($fullPath);
                $pages      = $result['pages'];
                $usedOcr    = $result['used_ocr'];
                $confidence = $result['confidence'];
            }

            $fullText = trim(implode("\n\n", array_column($pages, 'text')));

            if ($fullText === '') {
                throw new \RuntimeException(
                    'Could not extract any text from this PDF. '
                    . 'If this is a scanned document, ensure Tesseract is installed. '
                    . 'If the document is encrypted or password-protected, please remove the protection first.'
                );
            }

            [$words, $pageOfWord] = $this->buildWordPageMap($pages);
            $chunks = $this->chunkWords($words, $pageOfWord, 300, 50);

            if (empty($chunks)) {
                throw new \RuntimeException(
                    'PDF was parsed but produced no valid text chunks (minimum 30 words per chunk).'
                );
            }

            // Quality gate: only documents that actually went through OCR are
            // scored. A pure text-layer document has no OCR confidence and
            // always proceeds straight to 'active'.
            $minConfidence = (float) config('ocr.min_confidence', 60);
            $incompleteOcr = collect($pages)->contains(function ($page) { return !empty($page['ocr_failed']); });
            $needsReview   = $usedOcr && ($incompleteOcr || $confidence === null || $confidence < $minConfidence);

            $committed = DB::transaction(function () use ($document, $version, $chunks, $needsReview, $incompleteOcr, $minConfidence, $usedOcr, $confidence, $pages) {
                $current = HrPolicyDocument::whereKey($document->id)->lockForUpdate()->first();
                if (!$current || strtolower((string) $current->ingestion_version) !== strtolower($version) || $current->status !== 'processing') { return false; }
                $document->setRawAttributes($current->getAttributes(), true);
                $document->chunks()->delete();
                foreach ($chunks as $index => $chunk) {
                    HrPolicyChunk::create([
                        'document_id'   => $document->id,
                        'chunk_index'   => $index,
                        'page_number'   => $chunk['page_number'],
                        'section_title' => $this->detectSection($chunk['text']),
                        'content'       => $chunk['text'],
                        'search_vector' => $this->buildVector($chunk['text']),
                    ]);
                }
                $document->update([
                    'status'         => $needsReview ? 'needs_review' : ($current->is_active ? 'active' : 'inactive'),
                    'chunk_count'    => count($chunks),
                    'ingest_error'   => $needsReview
                        ? ($incompleteOcr || $confidence === null ? 'OCR extraction is incomplete or confidence is unavailable. Review every page before using this policy.' : sprintf(
                            'OCR confidence %d%% is below the %d%% quality threshold. Review the extracted text below, or re-upload a clearer scan.',
                            round($confidence),
                            $minConfidence
                        ))
                        : null,
                    'ocr_used'       => $usedOcr,
                    'ocr_confidence' => $confidence !== null ? (int) round($confidence) : null,
                    // Persisted page-by-page so a future re-chunk (or another
                    // document uploading the same file) doesn't require re-OCRing.
                    'extracted_text' => json_encode($pages),
                ]);
                return true;
            });
            if (!$committed) { return; }

            Log::info("[PolicyIngest] Ingested '{$document->title}': " . count($chunks) . ' chunks'
                . ($usedOcr ? ' (via OCR, confidence ' . round($confidence ?? 0) . '%)' : ' (via pdfparser)')
                . ($needsReview ? ' — FLAGGED needs_review' : '')
            );

        } catch (\Throwable $e) {
            HrPolicyDocument::whereKey($document->id)->where('ingestion_version', $version)->where('status', 'processing')->update([
                'status'       => 'failed',
                'ingest_error' => $e->getMessage(),
            ]);
            Log::error("[PolicyIngest] Failed: '{$document->title}': " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Replace the PDF file on an existing document. Does NOT re-ingest —
     * the caller dispatches IngestPolicyDocumentJob afterwards.
     */
    public function replace(HrPolicyDocument $document, UploadedFile $newFile, array $data): void
    {
        $this->assertUploadReady();
        $oldPath  = $document->file_path;
        $path     = $newFile->storeAs('policies', Str::uuid() . '.pdf');
        if (!$path) {
            throw new \RuntimeException('Could not store the replacement PDF.');
        }
        $fullPath = Storage::path($path);

        try {
            DB::transaction(function () use ($document, $newFile, $data, $path, $fullPath, $oldPath) {
                $current = $this->editableDocument($document);
                $oldPath = $current->file_path;
                $document->setRawAttributes($current->getAttributes(), true);
                $document->update([
                    'title'          => $data['title'],
                    'filename'       => $newFile->getClientOriginalName(),
                    'file_path'      => $path,
                    'file_hash'      => hash_file('sha256', $fullPath),
                    'category'       => $data['category'],
                    'effective_date' => $data['effective_date'] ?? null,
                    'status'         => 'pending',
                    'ingest_error'   => null,
                    'extracted_text' => null,
                    'ocr_used'       => false,
                    'ocr_confidence' => null,
                    'chunk_count'    => 0,
                    'ingestion_version' => (string) Str::uuid(),
                    'ingestion_queued_at' => null,
                    'ingestion_started_at' => null,
                ]);
                DB::afterCommit(function () use ($oldPath) {
                    $this->deleteStoredFile($oldPath);
                });
            });
        } catch (\Throwable $e) {
            // A callback can fail after commit; never remove a file the record now references.
            try {
                if (!HrPolicyDocument::whereKey($document->id)->where('file_path', $path)->exists()) {
                    $this->deleteStoredFile($path);
                }
            } catch (\Throwable $cleanupError) {
                Log::error('[PolicyIngest] Could not check replacement cleanup: ' . $cleanupError->getMessage());
            }
            throw $e;
        }
    }

    /**
     * Reset a document to 'pending' so it can be re-ingested in place
     * (e.g. after installing missing OCR language data, or to retry after
     * a transient failure) without deleting and re-creating the record.
     */
    public function markPendingForReingest(HrPolicyDocument $document): void
    {
        $this->assertUploadReady();
        DB::transaction(function () use ($document) {
            $current = $this->editableDocument($document);
            // A repeated request for an already queued generation is idempotent.
            if ($current->status === 'pending' && $current->ingestion_queued_at) {
                $document->setRawAttributes($current->getAttributes(), true);
                return;
            }
            $document->setRawAttributes($current->getAttributes(), true);
            $document->update(['status' => 'pending', 'ingest_error' => null,
                'ingestion_version' => (string) Str::uuid(), 'ingestion_queued_at' => null, 'ingestion_started_at' => null]);
        });
    }

    public function queue(HrPolicyDocument $document, bool $forceReExtract = false): void
    {
        $version = $document->ingestion_version;
        try {
            DB::transaction(function () use ($document, $version, $forceReExtract) {
                $current = HrPolicyDocument::whereKey($document->id)->lockForUpdate()->firstOrFail();
                if (!$version || strtolower((string) $current->ingestion_version) !== strtolower($version)) {
                    throw new \RuntimeException('The document generation changed before dispatch.');
                }
                if ($current->ingestion_queued_at) { return; }
                $connection = config('queue.connections.policy_ingestion');
                if ($current->status !== 'pending' || ($connection['driver'] ?? null) !== 'database'
                    || !empty($connection['after_commit'])
                    || (!empty($connection['connection']) && $connection['connection'] !== $current->getConnection()->getName())) {
                    throw new \RuntimeException('Policy ingestion requires the dedicated transactional database queue.');
                }
                $job = new IngestPolicyDocumentJob($document->id, $forceReExtract, $version);
                if (!Queue::connection('policy_ingestion')->push($job, '', 'policy-ingestion')) {
                    throw new \RuntimeException('Policy job was not queued.');
                }
                $current->update(['ingestion_queued_at' => now(), 'ingest_error' => null]);
            });
        } catch (\Throwable $e) {
            $message = 'Policy saved, but background ingestion could not be queued. Re-run ingestion after the queue is restored.';
            try {
                HrPolicyDocument::whereKey($document->id)->where('ingestion_version', $version)->where('status', 'pending')
                    ->whereNull('ingestion_queued_at')->update(['ingest_error' => $message]);
            } catch (\Throwable $stateError) {
                Log::error('[Policy dispatch] Could not record dispatch failure.', ['document_id' => $document->id]);
            }
            Log::error('[Policy dispatch] Queue dispatch failed.', ['document_id' => $document->id, 'exception' => $e]);
            throw new PolicyIngestionDispatchException($message);
        }
    }

    private function editableDocument(HrPolicyDocument $document): HrPolicyDocument
    {
        $current = HrPolicyDocument::whereKey($document->id)->lockForUpdate()->firstOrFail();
        if ($current->status === 'processing') {
            throw ValidationException::withMessages(['pdf_file' => 'This policy is processing. Wait for ingestion to finish before replacing or re-running it.']);
        }
        return $current;
    }

    /**
     * Delete a document: its chunks, physical file, and DB record.
     */
    public function destroy(HrPolicyDocument $document): void
    {
        $path = $document->file_path;
        DB::transaction(function () use ($document, $path) {
            $document->chunks()->delete();
            $document->delete();
            DB::afterCommit(function () use ($path) {
                $this->deleteStoredFile($path);
            });
        });
    }

    private function deleteStoredFile($path): void
    {
        if ($path) {
            try {
                // Older timestamp-based uploads can share a path; preserve any remaining reference.
                if (!HrPolicyDocument::where('file_path', $path)->exists()) {
                    Storage::delete($path);
                }
            } catch (\Throwable $e) {
                Log::error('[PolicyIngest] Could not remove a policy PDF: ' . $e->getMessage());
            }
        }
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Look for another document with the identical file (by sha256) that
     * already has persisted extraction results, so we can skip re-extracting
     * (and, crucially, re-OCRing) entirely.
     *
     * @return array{0: array, 1: bool, 2: ?float}|null [pages, used_ocr, confidence]
     */
    private function findCachedExtraction(HrPolicyDocument $document): ?array
    {
        if (empty($document->file_hash)) {
            return null;
        }

        $match = HrPolicyDocument::where('file_hash', $document->file_hash)
            ->where('id', '!=', $document->id)
            ->whereIn('status', ['active', 'inactive', 'needs_review'])
            ->whereNotNull('extracted_text')
            ->latest('updated_at')
            ->first();

        if (!$match) {
            return null;
        }

        $pages = json_decode($match->extracted_text, true);
        if (!is_array($pages)) {
            return null;
        }

        return [
            $pages,
            (bool) $match->ocr_used,
            $match->ocr_confidence !== null ? (float) $match->ocr_confidence : null,
        ];
    }

    /**
     * Flatten per-page text into a single word list, tagging each word with
     * the page it came from, so chunking can span page boundaries exactly
     * like before while still attributing each chunk to a starting page.
     *
     * @param array $pages from OcrService::extractDocument()['pages']
     * @return array{0: string[], 1: int[]} [words, pageOfWord] (parallel arrays)
     */
    private function buildWordPageMap(array $pages): array
    {
        $words      = [];
        $pageOfWord = [];

        foreach ($pages as $page) {
            $pageWords = preg_split('/\s+/', trim($page['text']), -1, PREG_SPLIT_NO_EMPTY);
            foreach ($pageWords as $word) {
                $words[]      = $word;
                $pageOfWord[] = $page['page_number'];
            }
        }

        return [$words, $pageOfWord];
    }

    /**
     * Same sliding-window chunking as before (300 words/chunk, 50-word
     * overlap, minimum 30 words to keep a chunk), now page-aware: each chunk
     * is attributed to the page its first word came from.
     *
     * @return array<int, array{text: string, page_number: ?int}>
     */
    private function chunkWords(array $words, array $pageOfWord, int $wordsPerChunk = 300, int $overlap = 50): array
    {
        $total  = count($words);
        $step   = $wordsPerChunk - $overlap;
        $chunks = [];

        for ($i = 0; $i < $total; $i += $step) {
            $chunkText = implode(' ', array_slice($words, $i, $wordsPerChunk));
            if (str_word_count($chunkText) >= 30) {
                $chunks[] = [
                    'text'        => $chunkText,
                    'page_number' => $pageOfWord[$i] ?? null,
                ];
            }
        }

        return $chunks;
    }

    private function detectSection(string $chunk): ?string
    {
        if (preg_match('/^(Section\s+[\d.]+[^.\n]{3,60})/i', $chunk, $m)) {
            return trim($m[1]);
        }
        if (preg_match('/^([A-Z][A-Z\s]{5,50})\n/', $chunk, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    private function buildVector(string $text): string
    {
        $clean = strtolower(preg_replace('/[^a-zA-Z0-9\s]/', ' ', $text));
        $words = array_unique(array_filter(
            explode(' ', $clean),
            function ($w) { return strlen($w) > 3; }
        ));
        return implode(' ', $words);
    }
}
