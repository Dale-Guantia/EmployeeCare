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
    /**
     * Store the PDF file and create the document record.
     * Ingestion (parsing + chunking) is done in a separate ingest() call
     * so the HTTP response is not blocked by the PDF parsing time.
     */
    public function store(UploadedFile $file, array $data): HrPolicyDocument
    {
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
        ]);
    }

    /**
     * Run the full ingest pipeline: parse PDF → chunk → save to DB.
     * Called after store(). Safe to re-run on the same document (re-ingest).
     */
    public function ingest(HrPolicyDocument $document): void
    {
        try {
            $fullPath = Storage::path($document->file_path);

            if (!file_exists($fullPath)) {
                throw new \RuntimeException(
                    "PDF file not found on disk: {$document->file_path}"
                );
            }

            // Delete all existing chunks before re-ingesting
            $document->chunks()->delete();

            $parser = new \Smalot\PdfParser\Parser();
            $text   = $parser->parseFile($fullPath)->getText();

            if (empty(trim($text))) {
                throw new \RuntimeException(
                    'PDF returned no text. It may be a scanned image PDF that requires OCR.'
                );
            }

            $chunks = $this->chunkText($text, 300, 50);

            if (empty($chunks)) {
                throw new \RuntimeException(
                    'PDF was parsed but produced no valid text chunks.'
                );
            }

            // FIX: Use a database transaction for chunk insertion.
            // If a chunk insert fails halfway through, the document won't be
            // left in a partial/corrupt state — all chunks are rolled back together.
            DB::transaction(function () use ($document, $chunks) {
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
                ]);
            });

            Log::info("[PolicyIngest] Successfully ingested '{$document->title}': " . count($chunks) . " chunks.");

        } catch (\Throwable $e) {
            // Mark document as failed so HRDO can see it in the UI
            $document->update([
                'status'       => 'failed',
                'ingest_error' => $e->getMessage(),
            ]);
            Log::error("[PolicyIngest] Failed to ingest '{$document->title}': " . $e->getMessage());

            // Re-throw so the controller can display the error to the user
            throw $e;
        }
    }

    /**
     * Replace the PDF on an existing document and re-ingest from scratch.
     */
    public function replace(HrPolicyDocument $document, UploadedFile $newFile, array $data): void
    {
        // Delete the old physical file before replacing
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

        // ingest() handles deleting old chunks and re-creating them
        $this->ingest($document);
    }

    /**
     * Fully remove a document: delete chunks, physical file, and the DB record.
     */
    public function destroy(HrPolicyDocument $document): void
    {
        // Explicit chunk deletion (cascadeOnDelete handles it too, but explicit is safer
        // in case the constraint is ever removed from the migration)
        $document->chunks()->delete();

        if ($document->file_path && Storage::exists($document->file_path)) {
            Storage::delete($document->file_path);
        }

        $document->delete();
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Split raw PDF text into overlapping chunks.
     *
     * @param int $wordsPerChunk Target chunk size in words
     * @param int $overlap       How many words to repeat between consecutive chunks
     *                           so that sentences split across boundaries are not lost
     */
    private function chunkText(string $text, int $wordsPerChunk = 300, int $overlap = 50): array
    {
        // Collapse all whitespace (newlines, tabs, multiple spaces) to single spaces
        $text  = preg_replace('/\s+/', ' ', trim($text));
        $words = explode(' ', $text);
        $total = count($words);
        $step  = $wordsPerChunk - $overlap;
        $chunks = [];

        for ($i = 0; $i < $total; $i += $step) {
            $chunk = implode(' ', array_slice($words, $i, $wordsPerChunk));

            // FIX: Increased minimum word count from 20 to 30.
            // Chunks under 30 words are usually page headers, footers, or
            // page numbers that add noise without retrieval value.
            if (str_word_count($chunk) >= 30) {
                $chunks[] = $chunk;
            }
        }

        return $chunks;
    }

    /**
     * Try to detect the section heading at the start of a chunk.
     * Used to populate section_title for richer source citations.
     */
    private function detectSection(string $chunk): ?string
    {
        // "Section 3. Vacation Leave" or "Section 3.1 Filing Requirements"
        if (preg_match('/^(Section\s+[\d.]+[^.\n]{3,60})/i', $chunk, $m)) {
            return trim($m[1]);
        }
        // All-caps headings common in CSC issuances and Philippine government documents
        if (preg_match('/^([A-Z][A-Z\s]{5,50})\n/', $chunk, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    /**
     * Build a deduplicated keyword string for the search_vector column.
     * This is used as a supplementary search field alongside content.
     */
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
