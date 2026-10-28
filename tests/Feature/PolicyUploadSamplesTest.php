<?php

namespace Tests\Feature;

use App\Models\HrPolicyDocument;
use App\Models\User;
use App\Services\OcrService;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** @group policy-samples */
class PolicyUploadSamplesTest extends TestCase
{
    /** @dataProvider samples */
    public function test_supplied_pdfs_upload_queue_and_ingest_with_real_extraction($filename, $pageCount, $scanned)
    {
        if (getenv('POLICY_SAMPLE_TESTS') !== '1') { $this->markTestSkipped('Opt in with POLICY_SAMPLE_TESTS=1; uses supplied PDFs and installed OCR tools.'); }
        $source = public_path('policyDocs/' . $filename);
        if (!is_file($source)) { $this->markTestSkipped('Required supplied PDF is unavailable: ' . $filename); }
        $ocr = app(OcrService::class);
        if ($scanned && (!$ocr->isOcrAvailable() || !$ocr->isFilipinoLanguageAvailable())) {
            $this->fail('Real scan test requires configured Poppler/Tesseract and the Filipino language pack.');
        }
        $originalHash = hash_file('sha256', $source);
        // Work only on an isolated copy. The user's original PDF is never moved or changed.
        Storage::put('incoming/sample.pdf', file_get_contents($source));
        $file = new UploadedFile(Storage::path('incoming/sample.pdf'), $filename, 'application/pdf', UPLOAD_ERR_OK, true);
        $this->actingAs(User::role('admin')->first(), 'web')->post(route('hr.policy.upload.store'), [
            'title' => 'Isolated sample: ' . $filename, 'category' => 'leave', 'pdf_file' => $file,
        ])->assertRedirect(backpack_url('hr-policy-documents'))->assertSessionHasNoErrors();
        $document = HrPolicyDocument::where('filename', $filename)->firstOrFail();
        $this->assertSame('pending', $document->status);
        $this->assertSame(1, DB::table('policy_jobs')->count());
        $this->assertSame(0, $document->chunks()->count());
        // One bounded job, never a persistent worker or application database fixture.
        app('queue.worker')->runNextJob('policy_ingestion', 'policy-ingestion', new WorkerOptions('sample-test', 0, 256, 900, 0, 1));
        $document->refresh();
        $this->assertContains($document->status, ['active', 'needs_review'], $document->ingest_error ?? '');
        $this->assertSame($scanned, $document->ocr_used);
        $this->assertGreaterThan(0, $document->chunk_count);
        $this->assertSame($document->chunk_count, $document->chunks()->count());
        $this->assertCount($pageCount, json_decode($document->extracted_text, true));
        $this->assertSame(0, DB::table('policy_jobs')->count());
        $this->assertSame($originalHash, hash_file('sha256', $source));
        Storage::assertExists($document->file_path);
        fwrite(STDOUT, "\nSAMPLE RESULT: " . $filename . ' -> ' . $document->status . ', pages=' . $pageCount . ', chunks=' . $document->chunk_count . ', OCR=' . ($document->ocr_used ? 'yes' : 'no') . ', confidence=' . ($document->ocr_confidence ?? 'n/a') . "\n");
    }

    public static function samples(): array
    {
        return [
            ['MC No. 05, s. 2021 (1).pdf', 9, false],
            ['OMNIBUS RULE ON LEAVE (1).pdf', 49, false],
            ['15-07-2026-EXECUTIVE-ORDER-NO.-PCG-39-s-2026.pdf', 4, true],
        ];
    }
}
