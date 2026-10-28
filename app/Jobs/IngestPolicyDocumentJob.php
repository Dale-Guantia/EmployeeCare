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
 * A dedicated database queue keeps OCR off HTTP requests even when the default is sync.
 */
class IngestPolicyDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const TIMEOUT = 900;
    public $tries = 1;

    public $timeout = self::TIMEOUT;
    public $failOnTimeout = true;

    protected $documentId;
    protected $forceReExtract;
    protected $ingestionVersion;

    public function __construct(int $documentId, bool $forceReExtract = false, ?string $ingestionVersion = null)
    {
        $this->documentId     = $documentId;
        $this->forceReExtract = $forceReExtract;
        $this->ingestionVersion = $ingestionVersion;
        $this->onConnection('policy_ingestion')->onQueue('policy-ingestion')->beforeCommit();
    }

    public function handle(PolicyIngestService $ingest): void
    {
        $document = HrPolicyDocument::find($this->documentId);

        if (!$document) {
            Log::warning("[IngestPolicyDocumentJob] Document {$this->documentId} no longer exists — skipping.");
            return;
        }

        // Pre-release jobs have no generation; never let them ingest a replacement file.
        if (!$this->ingestionVersion) { return; }
        $ingest->ingest($document, $this->forceReExtract, $this->ingestionVersion);
    }

    public function failed(\Throwable $exception): void
    {
        if (!$this->ingestionVersion) { return; }
        HrPolicyDocument::whereKey($this->documentId)->where('ingestion_version', $this->ingestionVersion)
            ->whereIn('status', ['pending', 'processing'])->update([
                'status' => 'failed',
                'ingest_error' => 'Background ingestion failed or exceeded its time limit. Re-run ingestion after checking the worker.',
            ]);
    }
}
