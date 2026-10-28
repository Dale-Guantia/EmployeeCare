<?php

namespace Tests\Feature;

use App\Models\HrPolicyDocument;
use App\Models\User;
use App\Services\OcrService;
use App\Services\PolicyIngestService;
use App\Services\PolicyRetriever;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PolicyChatIngestionTest extends TestCase
{
    /** @dataProvider incompleteResults */
    public function test_incomplete_or_unscored_ocr_is_never_chatbot_visible($confidence, $failedPage)
    {
        $document = app(PolicyIngestService::class)->store(UploadedFile::fake()->create('policy.pdf', 1, 'application/pdf'), ['title' => 'Synthetic policy', 'category' => 'general']);
        $ocr = \Mockery::mock(OcrService::class);
        $ocr->shouldReceive('extractDocument')->once()->andReturn(['used_ocr' => true, 'confidence' => $confidence,
            'pages' => [['page_number' => 1, 'text' => str_repeat('wellness leave policy ', 40)],
                ['page_number' => 2, 'text' => '', 'ocr_failed' => $failedPage]]]);
        (new PolicyIngestService($ocr))->ingest($document, true);
        $this->assertSame('needs_review', $document->fresh()->status);
        $this->assertStringContainsString('incomplete', $document->fresh()->ingest_error);
        $this->assertCount(0, (new PolicyRetriever)->retrieve('wellness leave'));
        Storage::assertExists($document->file_path);
    }

    public static function incompleteResults(): array { return [[null, false], [95, true]]; }

    public function test_successful_reingestion_preserves_inactive_state()
    {
        $service = app(PolicyIngestService::class);
        $document = $service->store(UploadedFile::fake()->create('policy.pdf', 1, 'application/pdf'), ['title' => 'Synthetic policy', 'category' => 'general']);
        $document->update(['status' => 'inactive', 'is_active' => false]);
        $service->markPendingForReingest($document);
        $ocr = \Mockery::mock(OcrService::class);
        $ocr->shouldReceive('extractDocument')->once()->andReturn(['used_ocr' => false, 'confidence' => null,
            'pages' => [['page_number' => 1, 'text' => str_repeat('wellness leave policy ', 40)]]]);
        (new PolicyIngestService($ocr))->ingest($document, true);
        $this->assertSame('inactive', $document->fresh()->status);
        $this->assertCount(0, (new PolicyRetriever)->retrieve('wellness leave'));
    }

    public function test_activation_rechecks_current_state_instead_of_trusting_stale_route_model()
    {
        $document = HrPolicyDocument::create(['title' => 'Synthetic', 'filename' => 'fixture.pdf', 'file_path' => 'fixture.pdf', 'status' => 'active', 'is_active' => true]);
        // Bind a stale active model, then move its database row to processing.
        $this->app['router']->bind('document', function () use ($document) {
            HrPolicyDocument::whereKey($document->id)->update(['status' => 'processing']);
            return $document;
        });
        $this->actingAs(User::role('admin')->first())->postJson(route('hr.policy.toggle', $document))->assertStatus(422);
        $this->assertSame('processing', $document->fresh()->status);
    }

    public function test_failed_conversion_cannot_be_treated_as_success_from_partial_stdout()
    {
        $method = new \ReflectionMethod(OcrService::class, 'runCommand');
        $method->setAccessible(true);
        $this->assertNull($method->invoke(new OcrService, [PHP_BINARY, '-r', 'echo "partial conversion output"; exit(1);']));
        $this->assertSame('complete', $method->invoke(new OcrService, [PHP_BINARY, '-r', 'echo "complete";']));
    }

    public function test_pending_dispatch_failure_remains_visible_in_the_policy_list()
    {
        HrPolicyDocument::create(['title' => 'Synthetic', 'filename' => 'fixture.pdf', 'file_path' => 'fixture.pdf',
            'status' => 'pending', 'is_active' => true, 'ingest_error' => 'Saved but not queued. Please retry ingestion.']);
        $this->actingAs(User::role('admin')->first())->postJson(backpack_url('hr-policy-documents/search'), ['start' => 0, 'length' => 10, 'draw' => 1])
            ->assertOk()->assertSee('Saved but not queued.', false);
    }

    public function test_zero_padded_poppler_pages_are_resolved_by_their_actual_page_numbers()
    {
        Storage::put('poppler/page-01.pgm', 'fixture');
        Storage::put('poppler/page-10.pgm', 'fixture');
        $method = new \ReflectionMethod(OcrService::class, 'sortedPageImages');
        $method->setAccessible(true);
        $images = $method->invoke(new OcrService, Storage::path('poppler'));
        $this->assertSame([1, 10], array_keys($images));
        $this->assertStringEndsWith('page-01.pgm', $images[1]);
    }

    /** @group subprocess */
    public function test_real_process_polling_enforces_per_page_timeout()
    {
        config(['services.ocr.tesseract_path' => PHP_BINARY, 'ocr.page_timeout' => 1, 'ocr.max_concurrency' => 1]);
        Storage::put('slow-ocr.php', '<?php sleep(4); echo "never reached";');
        $method = new \ReflectionMethod(OcrService::class, 'ocrImagesConcurrently');
        $method->setAccessible(true);
        $start = microtime(true);
        $result = $method->invoke(new OcrService, [1 => Storage::path('slow-ocr.php')]);
        $this->assertLessThan(3.5, microtime(true) - $start);
        $this->assertSame(['text' => '', 'confidence' => null], $result[1]);
    }
}
