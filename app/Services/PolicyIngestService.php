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
     * Does NOT run ingestion — caller must invoke ingest() separately.
     */
    public function store(UploadedFile $file, array $data): HrPolicyDocument
    {
        set_time_limit(0);

        $path = $file->storeAs(
            'policies',
            time() . '_' . $file->getClientOriginalName()
        );

        return HrPolicyDocument::create([
            'title'          => $data['title'],
            'filename'       => $file->getClientOriginalName(),
            'file_path'      => $path,
            'category'       => $data['category'],
            'effective_date' => $data['effective_date'] ?? null,
            'is_active'      => true,
            'status'         => 'processing',
            'chunk_count'    => 0,
            // Store whether OCR was needed — visible in the admin UI
            'ocr_used'       => false,
        ]);
    }

    /**
     * Run the full ingest pipeline.
     * Now uses OcrService for text extraction, which handles both
     * regular (text-layer) PDFs and scanned image PDFs transparently.
     */
    public function ingest(HrPolicyDocument $document): void
    {
        set_time_limit(0);

        try {
            $fullPath = Storage::path($document->file_path);

            if (!file_exists($fullPath)) {
                throw new \RuntimeException(
                    "PDF file not found on disk: {$document->file_path}"
                );
            }

            // Detect if this is a scanned PDF before extraction
            // so we can record it in the document record
            $isScanned = $this->ocr->isScannedPdf($fullPath);

            if ($isScanned) {
                Log::info("[PolicyIngest] Scanned PDF detected, OCR will be used: {$document->title}");

                // Verify OCR tools are available before attempting
                if (!$this->ocr->isOcrAvailable()) {
                    throw new \RuntimeException(
                        'This appears to be a scanned PDF but OCR tools are not installed on this server. '
                        . 'Please install Tesseract and Poppler (pdftoppm), or upload a text-based PDF instead.'
                    );
                }
            }

            // Extract text — OcrService handles both paths transparently
            $text = $this->ocr->extractText($fullPath);

            if (empty(trim($text))) {
                throw new \RuntimeException(
                    'Could not extract any text from this PDF. '
                    . 'If this is a scanned document, ensure Tesseract is installed. '
                    . 'If the document is encrypted or password-protected, please remove the protection first.'
                );
            }

            // Delete old chunks before re-ingesting
            $document->chunks()->delete();

            $chunks = $this->chunkText($text, 300, 50);

            if (empty($chunks)) {
                throw new \RuntimeException(
                    'PDF was parsed but produced no valid text chunks (minimum 30 words per chunk).'
                );
            }

            DB::transaction(function () use ($document, $chunks, $isScanned) {
                foreach ($chunks as $index => $chunk) {
                    HrPolicyChunk::create([
                        'document_id'   => $document->id,
                        'chunk_index'   => $index,
                        'section_title' => $this->detectSection($chunk),
                        'content'       => $chunk,
                        'search_vector' => $this->buildVector($chunk),
                    ]);
                }

                $document->update([
                    'status'       => 'active',
                    'chunk_count'  => count($chunks),
                    'ingest_error' => null,
                    'ocr_used'     => $isScanned,
                ]);
            });

            Log::info("[PolicyIngest] Successfully ingested '{$document->title}': "
                . count($chunks) . " chunks"
                . ($isScanned ? " (via OCR)" : " (via pdfparser)")
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
     * Replace the PDF file on an existing document and re-ingest.
     */
    public function replace(HrPolicyDocument $document, UploadedFile $newFile, array $data): void
    {
        set_time_limit(0);

        if ($document->file_path && Storage::exists($document->file_path)) {
            Storage::delete($document->file_path);
        }

        $path = $newFile->storeAs(
            'policies',
            time() . '_' . $newFile->getClientOriginalName()
        );

        $document->update([
            'title'          => $data['title'],
            'filename'       => $newFile->getClientOriginalName(),
            'file_path'      => $path,
            'category'       => $data['category'],
            'effective_date' => $data['effective_date'] ?? null,
            'status'         => 'processing',
            'ingest_error'   => null,
        ]);

        $this->ingest($document);
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

    private function chunkText(string $text, int $wordsPerChunk = 300, int $overlap = 50): array
    {
        $text  = preg_replace('/\s+/', ' ', trim($text));
        $words = explode(' ', $text);
        $total = count($words);
        $step  = $wordsPerChunk - $overlap;
        $chunks = [];

        for ($i = 0; $i < $total; $i += $step) {
            $chunk = implode(' ', array_slice($words, $i, $wordsPerChunk));
            if (str_word_count($chunk) >= 30) {
                $chunks[] = $chunk;
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
