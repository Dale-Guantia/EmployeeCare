<?php

namespace Tests\Unit;

use App\Services\AttachmentStorage;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AttachmentStorageTest extends TestCase
{
    public function test_randomized_filename_is_not_the_original_name()
    {
        $file = UploadedFile::fake()->create('my report.pdf', 10);

        $result = AttachmentStorage::randomizedFilename($file);

        $this->assertNotSame('my report.pdf', $result);
        $this->assertStringEndsWith('.pdf', $result);
        $this->assertStringContainsString('my_report', $result);
    }

    public function test_two_calls_for_the_same_original_name_produce_different_filenames()
    {
        $fileA = UploadedFile::fake()->create('same.pdf', 10);
        $fileB = UploadedFile::fake()->create('same.pdf', 10);

        $this->assertNotSame(
            AttachmentStorage::randomizedFilename($fileA),
            AttachmentStorage::randomizedFilename($fileB)
        );
    }

    public function test_disallowed_extension_is_preserved_as_given_not_sanitized_here()
    {
        // Extension whitelisting is the validation layer's job (TicketRequest /
        // TicketChat's mimes rule) — this helper only randomizes the name, it
        // does not re-validate the extension. Confirm it doesn't crash on an
        // unusual but well-formed filename.
        $file = UploadedFile::fake()->create('weird..name.PDF', 10);

        $result = AttachmentStorage::randomizedFilename($file);

        $this->assertStringEndsWith('.PDF', $result);
    }
}
