<?php

namespace Tests\Feature;

use App\Jobs\IngestPolicyDocumentJob;
use App\Models\HrPolicyDocument;
use App\Models\User;
use App\Services\OcrService;
use App\Services\PolicyIngestService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PolicyQueueTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['queue.default' => 'sync']);
        $this->actingAs(User::role('admin')->first(), 'web');
        \Illuminate\Support\Facades\Log::spy();
    }

    private function document(): HrPolicyDocument
    {
        return app(PolicyIngestService::class)->store(UploadedFile::fake()->create('original.pdf', 1, 'application/pdf'),
            ['title' => 'Original policy', 'category' => 'general']);
    }

    private function forbidOcr(): void
    {
        $ocr = \Mockery::mock(OcrService::class); $ocr->shouldNotReceive('extractDocument');
        $this->app->instance(OcrService::class, $ocr);
    }

    private function queuedJob(): IngestPolicyDocumentJob
    {
        $row = DB::table('policy_jobs')->orderBy('id')->first();
        $this->assertSame('policy-ingestion', $row->queue);
        $payload = json_decode($row->payload, true);
        $job = unserialize($payload['data']['command']);
        $this->assertInstanceOf(IngestPolicyDocumentJob::class, $job);
        $this->assertSame('policy_ingestion', $job->connection);
        $this->assertGreaterThan(0, $job->timeout);
        $this->assertGreaterThan($job->timeout, config('queue.connections.policy_ingestion.retry_after'));
        return $job;
    }

    public function test_upload_replacement_and_reingestion_queue_with_sync_default_without_inline_ocr()
    {
        $this->forbidOcr();
        $this->post('/employee-care/hr-policy-documents/upload', ['title' => 'Queued policy', 'category' => 'general',
            'pdf_file' => UploadedFile::fake()->create('policy.pdf', 1, 'application/pdf')])->assertRedirect();
        $this->assertStringContainsString('queued', json_encode($this->app['session']->get(config('prologue.alerts.session_key'), [])));
        $document = HrPolicyDocument::where('title', 'Queued policy')->firstOrFail();
        $this->assertSame('pending', $document->status); Storage::assertExists($document->file_path);
        $this->assertSame(1, DB::table('policy_jobs')->count()); $this->queuedJob();
        $firstVersion = $document->ingestion_version;
        $this->post('/employee-care/hr-policy-documents/' . $document->id . '/update-file', ['title' => 'Replaced', 'category' => 'general',
            'pdf_file' => UploadedFile::fake()->create('replacement.pdf', 1, 'application/pdf')])->assertRedirect();
        $document->refresh();
        $this->assertNotSame($firstVersion, $document->ingestion_version);
        $this->assertSame('pending', $document->status);
        $this->assertSame(2, DB::table('policy_jobs')->count());
        // An already queued re-run is idempotent, not a third dispatch.
        $this->post('/employee-care/hr-policy-documents/' . $document->id . '/reingest')->assertOk()->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Re-ingestion queued. Refresh this list to check its status.');
        $this->assertSame(2, DB::table('policy_jobs')->count());
        $document->update(['status' => 'active']);
        $this->post('/employee-care/hr-policy-documents/' . $document->id . '/reingest')->assertOk()->assertJsonPath('success', true);
        $this->assertSame(3, DB::table('policy_jobs')->count());
        $this->assertSame('pending', $document->fresh()->status);
    }

    public function test_dispatch_failure_preserves_uploaded_pdf_and_pending_record_with_accurate_message()
    {
        $this->forbidOcr(); $this->breakQueue();
        $this->post('/employee-care/hr-policy-documents/upload', ['title' => 'Saved but not queued', 'category' => 'general',
            'pdf_file' => UploadedFile::fake()->create('policy.pdf', 1, 'application/pdf')])->assertRedirect();
        $document = HrPolicyDocument::where('title', 'Saved but not queued')->firstOrFail();
        $this->assertSame('pending', $document->status); $this->assertNull($document->ingestion_queued_at);
        Storage::assertExists($document->file_path);
        $this->assertStringContainsString('could not be queued', $document->ingest_error);
        $session = json_encode($this->app['session']->get(config('prologue.alerts.session_key'), []));
        $this->assertStringContainsString('Policy saved', $session);
        $this->assertStringNotContainsString('INTERNAL_QUEUE_FAILURE', $session);
        $this->assertSame(0, DB::table('policy_jobs')->count());
    }

    public function test_replacement_and_reingestion_dispatch_failures_keep_recoverable_state()
    {
        $document = $this->document(); $oldPath = $document->file_path;
        $document->update(['status' => 'active']);
        $this->forbidOcr(); $this->breakQueue();
        $this->post('/employee-care/hr-policy-documents/' . $document->id . '/update-file', ['title' => 'Replacement saved', 'category' => 'general',
            'pdf_file' => UploadedFile::fake()->create('new.pdf', 1, 'application/pdf')])->assertRedirect();
        $document->refresh();
        $this->assertNotSame($oldPath, $document->file_path); Storage::assertExists($document->file_path);
        $this->assertSame('pending', $document->status); $this->assertNull($document->ingestion_queued_at);
        $this->post('/employee-care/hr-policy-documents/' . $document->id . '/reingest')->assertStatus(503)->assertJsonPath('success', false);
        Storage::assertExists($document->fresh()->file_path);
        $this->assertSame('pending', $document->fresh()->status);
    }

    private function breakQueue(): void
    {
        $queue = \Mockery::mock(\Illuminate\Queue\DatabaseQueue::class);
        $queue->shouldReceive('push')->andThrow(new \RuntimeException('INTERNAL_QUEUE_FAILURE'));
        Queue::shouldReceive('connection')->with('policy_ingestion')->andReturn($queue);
    }

    public function test_old_generation_and_completed_duplicates_skip_ocr_and_overlap_is_rejected()
    {
        $document = $this->document(); $service = app(PolicyIngestService::class);
        $old = new IngestPolicyDocumentJob($document->id, true, $document->ingestion_version);
        $service->replace($document, UploadedFile::fake()->create('new.pdf', 1, 'application/pdf'), ['title' => 'New', 'category' => 'general']);
        $ocr = \Mockery::mock(OcrService::class);
        $ocr->shouldReceive('extractDocument')->once()->andReturnUsing(function () use ($document) {
            $this->assertSame('processing', $document->fresh()->status);
            $this->post('/employee-care/hr-policy-documents/' . $document->id . '/reingest')->assertStatus(409);
            $this->post('/employee-care/hr-policy-documents/' . $document->id . '/update-file', ['title' => 'Busy replacement', 'category' => 'general',
                'pdf_file' => UploadedFile::fake()->create('busy.pdf', 1, 'application/pdf')])->assertRedirect()->assertSessionHasErrors('pdf_file');
            $duplicate = new IngestPolicyDocumentJob($document->id, true, $document->ingestion_version);
            $duplicate->handle(app(PolicyIngestService::class));
            return ['pages' => [['page_number' => 1, 'text' => str_repeat('policy words ', 40)]], 'used_ocr' => false, 'confidence' => null];
        });
        $this->app->instance(OcrService::class, $ocr);
        $service = app(PolicyIngestService::class);
        $old->handle($service);
        $job = new IngestPolicyDocumentJob($document->id, true, $document->ingestion_version);
        $job->handle($service); $job->handle($service);
        $this->assertSame('active', $document->fresh()->status);
        $this->assertSame('New', $document->fresh()->title);
        $this->assertCount(1, Storage::allFiles('policies'));
    }

    public function test_stale_job_failure_cannot_overwrite_replacement_and_current_failure_is_visible()
    {
        $document = $this->document();
        $old = new IngestPolicyDocumentJob($document->id, true, $document->ingestion_version);
        app(PolicyIngestService::class)->replace($document, UploadedFile::fake()->create('new.pdf', 1, 'application/pdf'), ['title' => 'New', 'category' => 'general']);
        $old->failed(new \RuntimeException('old failure'));
        $this->assertSame('pending', $document->fresh()->status);
        $current = new IngestPolicyDocumentJob($document->id, true, $document->ingestion_version);
        $current->failed(new \RuntimeException('timeout'));
        $this->assertSame('failed', $document->fresh()->status);
        Storage::assertExists($document->fresh()->file_path);
    }

    public function test_worker_failure_keeps_pdf_and_old_chunks_and_records_failure()
    {
        $document = $this->document();
        $document->chunks()->create(['chunk_index' => 0, 'content' => 'old text', 'search_vector' => 'old']);
        $ocr = \Mockery::mock(OcrService::class);
        $ocr->shouldReceive('extractDocument')->once()->andThrow(new \RuntimeException('OCR fixture failure'));
        $service = new PolicyIngestService($ocr);
        $job = new IngestPolicyDocumentJob($document->id, true, $document->ingestion_version);
        try { $job->handle($service); $this->fail('Worker failure must propagate.'); }
        catch (\RuntimeException $e) { $this->assertSame('OCR fixture failure', $e->getMessage()); }
        $this->assertSame('failed', $document->fresh()->status);
        $this->assertSame('old text', $document->chunks()->first()->content);
        Storage::assertExists($document->file_path);
    }

    public function test_queue_marker_failure_rolls_back_the_inserted_job()
    {
        $document = $this->document();
        HrPolicyDocument::updating(function ($entry) {
            if ($entry->isDirty('ingestion_queued_at')) { throw new \RuntimeException('marker failed'); }
        });
        try { app(PolicyIngestService::class)->queue($document); $this->fail('Dispatch must fail.'); }
        catch (\App\Exceptions\PolicyIngestionDispatchException $e) { $this->assertSame(0, DB::table('policy_jobs')->count()); }
        $this->assertNull($document->fresh()->ingestion_queued_at);
        Storage::assertExists($document->file_path);
    }

    public function test_sync_override_is_rejected_instead_of_running_ocr_inline()
    {
        $document = $this->document(); $this->forbidOcr();
        config(['queue.connections.policy_ingestion.driver' => 'sync']);
        $this->expectException(\App\Exceptions\PolicyIngestionDispatchException::class);
        app(PolicyIngestService::class)->queue($document);
    }

    public function test_a_real_database_queue_job_runs_once_and_finishes_when_consumed()
    {
        $document = $this->document();
        app(PolicyIngestService::class)->queue($document);
        app(PolicyIngestService::class)->queue($document);
        $this->assertSame(1, DB::table('policy_jobs')->count());
        $ocr = \Mockery::mock(OcrService::class);
        $ocr->shouldReceive('extractDocument')->once()->andReturn([
            'pages' => [['page_number' => 1, 'text' => str_repeat('worker policy words ', 40)]], 'used_ocr' => true, 'confidence' => 20,
        ]);
        $this->app->instance(OcrService::class, $ocr);
        // One job on SQLite :memory:, not a persistent worker or an application connection.
        app('queue.worker')->runNextJob('policy_ingestion', 'policy-ingestion', new \Illuminate\Queue\WorkerOptions('policy-test', 0, 128, 900, 0, 1));
        $this->assertSame(0, DB::table('policy_jobs')->count());
        $this->assertSame('needs_review', $document->fresh()->status);
        $this->assertGreaterThan(0, $document->fresh()->chunk_count);
    }

    public function test_expired_reserved_job_marks_interrupted_document_failed_without_repeating_ocr()
    {
        $document = $this->document();
        app(PolicyIngestService::class)->queue($document);
        $document->update(['status' => 'processing', 'ingestion_started_at' => now()->subMinutes(25)]);
        DB::table('policy_jobs')->update(['attempts' => 1, 'reserved_at' => time() - 1201]);
        $this->forbidOcr();
        app('queue.worker')->runNextJob('policy_ingestion', 'policy-ingestion', new \Illuminate\Queue\WorkerOptions('policy-test', 0, 128, 900, 0, 1));
        $this->assertSame('failed', $document->fresh()->status);
        $this->assertSame(0, DB::table('policy_jobs')->count());
        Storage::assertExists($document->file_path);
    }
}
