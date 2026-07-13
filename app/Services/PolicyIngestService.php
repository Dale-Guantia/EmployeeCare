<?php
namespace App\Services;

use App\Models\HrPolicyDocument;
use App\Models\HrPolicyChunk;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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
        set_time_limit(0);

        $path     = $file->storeAs('policies', time() . '_' . $file->getClientOriginalName());
        $fullPath = Storage::path($path);

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
        ]);
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
    public function ingest(HrPolicyDocument $document, bool $forceReExtract = false): void
    {
        set_time_limit(0);

        $document->update(['status' => 'processing']);

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
            $needsReview   = $usedOcr && $confidence !== null && $confidence < $minConfidence;

            // Delete old chunks before re-ingesting (matches previous behavior).
            $document->chunks()->delete();

            DB::transaction(function () use ($document, $chunks) {
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
            });

            $document->update([
                'status'         => $needsReview ? 'needs_review' : 'active',
                'chunk_count'    => count($chunks),
                'ingest_error'   => $needsReview
                    ? sprintf(
                        'OCR confidence %d%% is below the %d%% quality threshold. Review the extracted text below, or re-upload a clearer scan.',
                        round($confidence),
                        $minConfidence
                    )
                    : null,
                'ocr_used'       => $usedOcr,
                'ocr_confidence' => $confidence !== null ? (int) round($confidence) : null,
                // Persisted page-by-page so a future re-chunk (or another
                // document uploading the same file) doesn't require re-OCRing.
                'extracted_text' => json_encode($pages),
            ]);

            Log::info("[PolicyIngest] Ingested '{$document->title}': " . count($chunks) . ' chunks'
                . ($usedOcr ? ' (via OCR, confidence ' . round($confidence ?? 0) . '%)' : ' (via pdfparser)')
                . ($needsReview ? ' — FLAGGED needs_review' : '')
            );

        } catch (\Throwable $e) {
            $document->update([
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
        set_time_limit(0);

        if ($document->file_path && Storage::exists($document->file_path)) {
            Storage::delete($document->file_path);
        }

        $path     = $newFile->storeAs('policies', time() . '_' . $newFile->getClientOriginalName());
        $fullPath = Storage::path($path);

        $document->update([
            'title'          => $data['title'],
            'filename'       => $newFile->getClientOriginalName(),
            'file_path'      => $path,
            'file_hash'      => hash_file('sha256', $fullPath),
            'category'       => $data['category'],
            'effective_date' => $data['effective_date'] ?? null,
            'status'         => 'pending',
            'ingest_error'   => null,
        ]);
    }

    /**
     * Reset a document to 'pending' so it can be re-ingested in place
     * (e.g. after installing missing OCR language data, or to retry after
     * a transient failure) without deleting and re-creating the record.
     */
    public function markPendingForReingest(HrPolicyDocument $document): void
    {
        $document->update(['status' => 'pending', 'ingest_error' => null]);
    }

    /**
     * Delete a document: its chunks, physical file, and DB record.
     */
    public function destroy(HrPolicyDocument $document): void
    {
        $document->chunks()->delete();

        if ($document->file_path && Storage::exists($document->file_path)) {
            Storage::delete($document->file_path);
        }

        $document->delete();
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
