<?php

namespace Tests\Feature;

use App\Models\HrPolicyChunk;
use App\Models\HrPolicyDocument;
use App\Services\OcrService;
use App\Services\PolicyIngestService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PolicyIngestAtomicityTest extends TestCase
{
    private function document($status = 'active'): HrPolicyDocument
    {
        $path = 'policies/' . uniqid() . '.pdf';
        Storage::put($path, 'original PDF fixture');
        $document = HrPolicyDocument::create([
            'title' => 'Original policy', 'filename' => 'original.pdf', 'file_path' => $path,
            'file_hash' => hash('sha256', 'original PDF fixture'), 'category' => 'general',
            'status' => $status, 'is_active' => true, 'chunk_count' => 1,
            'extracted_text' => json_encode([['page_number' => 1, 'text' => str_repeat('original policy ', 40)]]),
            'ocr_used' => true, 'ocr_confidence' => 80,
        ]);
        $document->chunks()->create([
            'chunk_index' => 0, 'page_number' => 1, 'content' => 'original chunk', 'search_vector' => 'original',
        ]);
        return $document;
    }

    private function ocrResult(): array
    {
        return ['pages' => [['page_number' => 1, 'text' => str_repeat('replacement policy ', 40)]], 'used_ocr' => false, 'confidence' => null];
    }

    private function service($ocr = null): PolicyIngestService
    {
        if (!$ocr) {
            $ocr = \Mockery::mock(OcrService::class);
            $ocr->shouldReceive('extractDocument')->andReturn($this->ocrResult());
        }
        return new PolicyIngestService($ocr);
    }

    private function upload(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('same-name.pdf', '%PDF replacement fixture');
    }

    public function test_replacement_invalidates_old_extraction_and_duplicate_upload_extracts_new_text()
    {
        $document = $this->document();
        $oldPath = $document->file_path;
        $ocr = \Mockery::mock(OcrService::class);
        $ocr->shouldReceive('extractDocument')->once()->andReturn($this->ocrResult());
        $service = $this->service($ocr);
        $service->replace($document, $this->upload(), ['title' => 'Replacement', 'category' => 'general']);
        $document->refresh();
        $this->assertNull($document->extracted_text);
        $this->assertFalse($document->ocr_used);
        $this->assertNull($document->ocr_confidence);
        $this->assertSame('pending', $document->status);
        $this->assertSame(0, $document->chunk_count);
        Storage::assertMissing($oldPath);
        Storage::assertExists($document->file_path);

        $second = $service->store($this->upload(), ['title' => 'Second copy', 'category' => 'general']);
        $this->assertSame($document->file_hash, $second->file_hash);
        $service->ingest($second);
        $this->assertStringContainsString('replacement policy', $second->fresh()->extracted_text);
        $this->assertSame('active', $second->fresh()->status);
    }

    /** @dataProvider unfinishedStatuses */
    public function test_unfinished_extractions_are_not_cache_candidates($status)
    {
        $this->document($status);
        $second = $this->document('pending');
        $ocr = \Mockery::mock(OcrService::class);
        $ocr->shouldReceive('extractDocument')->once()->andReturn($this->ocrResult());
        $this->service($ocr)->ingest($second);
        $this->assertStringContainsString('replacement policy', $second->fresh()->extracted_text);
    }

    public function unfinishedStatuses(): array
    {
        return [['pending'], ['processing'], ['failed']];
    }

    public function test_completed_extraction_cache_and_quality_gate_are_preserved()
    {
        $cached = $this->document('needs_review');
        $cached->update(['ocr_confidence' => 25]);
        $second = $this->document('pending');
        $ocr = \Mockery::mock(OcrService::class);
        $ocr->shouldNotReceive('extractDocument');
        $this->service($ocr)->ingest($second);
        $this->assertStringContainsString('original policy', $second->fresh()->extracted_text);
        $this->assertTrue($second->fresh()->ocr_used);
        $this->assertSame('needs_review', $second->fresh()->status);
        $this->assertStringContainsString('25%', $second->fresh()->ingest_error);
    }

    public function test_failed_replacement_storage_preserves_the_original_file_and_record()
    {
        $document = $this->document();
        $oldPath = $document->file_path;
        $upload = \Mockery::mock(UploadedFile::class);
        $upload->shouldReceive('storeAs')->once()->andThrow(new \RuntimeException('storage fixture failure'));
        try {
            $this->service()->replace($document, $upload, ['title' => 'New', 'category' => 'general']);
            $this->fail('The injected failure must propagate.');
        } catch (\RuntimeException $e) {
            $this->assertSame('storage fixture failure', $e->getMessage());
        }
        $this->assertSame($oldPath, $document->fresh()->file_path);
        $this->assertSame('active', $document->fresh()->status);
        Storage::assertExists($oldPath);
        $this->assertSame(1, $document->chunks()->count());
    }

    public function test_replacement_database_failure_preserves_original_and_removes_unused_upload()
    {
        $document = $this->document();
        $oldPath = $document->file_path;
        HrPolicyDocument::updating(function ($entry) {
            if ($entry->isDirty('file_path')) {
                throw new \RuntimeException('database fixture failure');
            }
        });
        try {
            $this->service()->replace($document, $this->upload(), ['title' => 'New', 'category' => 'general']);
            $this->fail('The injected failure must propagate.');
        } catch (\RuntimeException $e) {
            $this->assertSame('database fixture failure', $e->getMessage());
        }
        $this->assertSame($oldPath, $document->fresh()->file_path);
        Storage::assertExists($oldPath);
        $this->assertSame([$oldPath], Storage::allFiles('policies'));
    }

    public function test_replacement_waits_for_commit_before_deleting_original()
    {
        $document = $this->document();
        $oldPath = $document->file_path;
        try {
            DB::transaction(function () use ($document, $oldPath) {
                $this->service()->replace($document, $this->upload(), ['title' => 'New', 'category' => 'general']);
                Storage::assertExists($oldPath);
                throw new \RuntimeException('rollback fixture');
            });
        } catch (\RuntimeException $e) {
            $this->assertSame('rollback fixture', $e->getMessage());
        }
        $this->assertSame($oldPath, $document->fresh()->file_path);
        Storage::assertExists($oldPath);
    }

    public function test_chunk_insert_failure_rolls_back_previous_chunks_and_extraction()
    {
        $document = $this->document();
        $chunk = $document->chunks()->firstOrFail();
        $text = $document->extracted_text;
        HrPolicyChunk::creating(function () {
            throw new \RuntimeException('chunk fixture failure');
        });
        try {
            $this->service()->ingest($document, true);
            $this->fail('The injected failure must propagate.');
        } catch (\RuntimeException $e) {
            $this->assertSame('chunk fixture failure', $e->getMessage());
        }
        $this->assertNotNull($chunk->fresh());
        $this->assertSame(1, $document->chunks()->count());
        $this->assertSame($text, $document->fresh()->extracted_text);
        $this->assertSame('failed', $document->fresh()->status);
    }

    public function test_successful_ingestion_replaces_chunks_and_metadata_together()
    {
        $document = $this->document();
        $chunk = $document->chunks()->firstOrFail();
        $this->service()->ingest($document, true);
        $this->assertNull($chunk->fresh());
        $document->refresh();
        $this->assertSame('active', $document->status);
        $this->assertSame($document->chunk_count, $document->chunks()->count());
        $this->assertStringContainsString('replacement policy', $document->extracted_text);
    }

    public function test_same_name_uploads_have_distinct_paths_and_contents()
    {
        $service = $this->service();
        $first = $service->store(UploadedFile::fake()->createWithContent('same.pdf', 'first content'), ['title' => 'First', 'category' => 'general']);
        $second = $service->store(UploadedFile::fake()->createWithContent('same.pdf', 'second content'), ['title' => 'Second', 'category' => 'general']);
        $this->assertNotSame($first->file_path, $second->file_path);
        $this->assertSame('first content', Storage::get($first->file_path));
        $this->assertSame('second content', Storage::get($second->file_path));
        $this->assertSame('same.pdf', $first->filename);
    }

    public function test_delete_failure_rolls_back_chunks_and_preserves_file()
    {
        $document = $this->document();
        $path = $document->file_path;
        HrPolicyDocument::deleting(function () {
            throw new \RuntimeException('delete fixture failure');
        });
        try {
            $this->service()->destroy($document);
            $this->fail('The injected failure must propagate.');
        } catch (\RuntimeException $e) {
            $this->assertSame('delete fixture failure', $e->getMessage());
        }
        $this->assertNotNull($document->fresh());
        $this->assertSame(1, $document->chunks()->count());
        Storage::assertExists($path);
    }

    public function test_replacing_a_legacy_shared_path_preserves_the_other_document_file()
    {
        $document = $this->document();
        $path = $document->file_path;
        $other = $document->replicate();
        $other->save();
        $this->service()->replace($document, $this->upload(), ['title' => 'New', 'category' => 'general']);
        $this->assertSame($path, $other->fresh()->file_path);
        Storage::assertExists($path);
        $this->service()->destroy($other);
        Storage::assertMissing($path);
    }

    public function test_successful_delete_removes_record_chunks_and_file()
    {
        $document = $this->document();
        $path = $document->file_path;
        $this->service()->destroy($document);
        $this->assertNull($document->fresh());
        $this->assertSame(0, $document->chunks()->count());
        Storage::assertMissing($path);
    }

    public function test_delete_response_does_not_disclose_service_exception()
    {
        $document = $this->document();
        $service = \Mockery::mock(PolicyIngestService::class);
        $service->shouldReceive('destroy')->once()->andThrow(new \RuntimeException('AUDIT_INTERNAL_SECRET'));
        $this->app->instance(PolicyIngestService::class, $service);
        $this->actingAs(\App\Models\User::role('admin')->first(), 'web')
            ->delete('/employee-care/hr-policy-documents/' . $document->id)
            ->assertStatus(500)->assertDontSee('AUDIT_INTERNAL_SECRET');
    }

    public function test_update_failure_does_not_flash_service_exception()
    {
        $document = $this->document();
        $service = \Mockery::mock(PolicyIngestService::class);
        $service->shouldReceive('replace')->once()->andThrow(new \RuntimeException('AUDIT_INTERNAL_SECRET'));
        $this->app->instance(PolicyIngestService::class, $service);
        $this->actingAs(\App\Models\User::role('admin')->first(), 'web')
            ->post('/employee-care/hr-policy-documents/' . $document->id . '/update-file', [
                'title' => 'Updated', 'category' => 'general',
                'pdf_file' => UploadedFile::fake()->create('policy.pdf', 1, 'application/pdf'),
            ])->assertRedirect();
        $this->assertStringNotContainsString('AUDIT_INTERNAL_SECRET', json_encode($this->app['session']->all()));
    }
}
