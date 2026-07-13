<?php
namespace App\Jobs;

use App\Models\HrPolicyDocument;
use App\Services\PolicyIngestService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Runs PolicyIngestService::ingest() off the HTTP request cycle. Under the
 * default QUEUE_CONNECTION=sync this still executes inline (no worker
 * process involved), but the moment a real queue connection + worker is
 * configured, large scanned PDFs stop tying up upload requests.
 */
class IngestPolicyDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    // No blanket job timeout: OCR documents can legitimately take minutes.
    // Per-page timeouts inside OcrService bound the real runaway-process risk.
    public $timeout = 0;

    protected $documentId;
    protected $forceReExtract;

    public function __construct(int $documentId, bool $forceReExtract = false)
    {
        $this->documentId     = $documentId;
        $this->forceReExtract = $forceReExtract;
    }

    public function handle(PolicyIngestService $ingest): void
    {
        $document = HrPolicyDocument::find($this->documentId);

        if (!$document) {
            Log::warning("[IngestPolicyDocumentJob] Document {$this->documentId} no longer exists — skipping.");
            return;
        }

        try {
            $ingest->ingest($document, $this->forceReExtract);
        } catch (\Throwable $e) {
            // ingest() already persisted status=failed + ingest_error on the
            // document itself — that's the actual signal admins see. Nothing
            // further to do here; swallow so the queue doesn't retry/alert
            // on what is already a recorded, user-visible failure.
        }
    }
}
