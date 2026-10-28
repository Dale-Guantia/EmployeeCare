<?php

namespace Tests\Feature;

use App\Models\Status;
use App\Models\User;
use App\Services\AttachmentInventory;
use App\Services\AttachmentStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttachmentOperationsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Log::spy();
    }

    private function file($path = 'attachments/orphan.txt', $old = true, $disk = AttachmentStorage::DISK): string
    {
        Storage::disk($disk)->put($path, 'fixture bytes');
        if ($old) { touch(Storage::disk($disk)->path($path), time() - 72 * 3600); }
        return $path;
    }

    private function reference($raw): void
    {
        DB::table('tickets')->insert(['reference_id' => uniqid('OPS-'), 'user_id' => User::first()->id,
            'status_id' => Status::first()->id, 'message' => 'fixture', 'attachments' => $raw]);
    }

    public function test_default_audit_retains_orphans_and_reports_missing_references_without_filenames()
    {
        $path = $this->file();
        $this->reference(json_encode(['attachments/missing.txt']));
        $this->artisan('attachments:reconcile')->expectsOutput('missing-reference disk=either path_id=' . hash('sha256', 'attachments/missing.txt'))
            ->expectsOutput('candidate-unreferenced disk=ticket_attachments path_id=' . hash('sha256', $path))->assertExitCode(0);
        Storage::disk(AttachmentStorage::DISK)->assertExists($path);
    }

    public function test_cleanup_retains_ticket_comment_recent_and_public_files()
    {
        $ticketPath = $this->file('attachments/ticket.txt');
        $commentPath = $this->file('attachments/comment.txt');
        $recent = $this->file('attachments/inflight.txt', false);
        $public = $this->file('attachments/legacy.txt', true, 'public');
        $orphan = $this->file();
        $this->reference(json_encode([$ticketPath]));
        DB::table('ticket_comments')->insert(['ticket_id' => DB::table('tickets')->first()->id,
            'user_id' => User::first()->id, 'comment' => 'fixture', 'attachment' => json_encode([$commentPath])]);
        $this->artisan('attachments:reconcile', ['--cleanup' => true, '--uploads-paused' => true])->assertExitCode(0);
        foreach ([$ticketPath, $commentPath, $recent] as $path) { Storage::disk(AttachmentStorage::DISK)->assertExists($path); }
        Storage::disk('public')->assertExists($public);
        Storage::disk(AttachmentStorage::DISK)->assertMissing($orphan);
    }

    public function test_cleanup_requires_explicit_paused_uploads_and_conservative_age()
    {
        $path = $this->file();
        $this->artisan('attachments:reconcile', ['--cleanup' => true])->assertExitCode(1);
        $this->artisan('attachments:reconcile', ['--min-age-hours' => '1'])->assertExitCode(1);
        Storage::disk(AttachmentStorage::DISK)->assertExists($path);
    }

    /** @dataProvider malformedReferences */
    public function test_malformed_references_prevent_all_cleanup($raw)
    {
        $path = $this->file(); $this->reference($raw);
        $this->artisan('attachments:reconcile', ['--cleanup' => true, '--uploads-paused' => true])->assertExitCode(1);
        Storage::disk(AttachmentStorage::DISK)->assertExists($path);
    }

    public function malformedReferences(): array
    {
        return [['not json'], ['null'], ['{}'], ['["attachments/../outside.txt"]'], ['["attachments\\\\outside.txt"]'],
            ['["attachments/C:secret.txt"]'], ['[null]'], ['["attachments/CON.txt"]']];
    }

    public function test_unsafe_filesystem_path_aborts_before_deleting_other_candidates()
    {
        $path = $this->file();
        $disk = Storage::disk(AttachmentStorage::DISK);
        $proxy = \Mockery::mock($disk);
        $proxy->shouldReceive('allFiles')->with('attachments')->once()->andReturn([$path, 'attachments/../outside.txt']);
        Storage::set(AttachmentStorage::DISK, $proxy);
        $this->artisan('attachments:reconcile', ['--cleanup' => true, '--uploads-paused' => true])->assertExitCode(1);
        Storage::disk(AttachmentStorage::DISK)->assertExists($path);
    }

    public function test_legacy_spaces_and_unicode_names_are_safely_retained_when_referenced()
    {
        $path = $this->file('attachments/legacy résumé file.txt');
        $this->reference(json_encode([$path]));
        $this->artisan('attachments:reconcile', ['--cleanup' => true, '--uploads-paused' => true])->assertExitCode(0);
        Storage::disk(AttachmentStorage::DISK)->assertExists($path);
    }

    public function test_unavailable_disk_root_is_retained_without_creating_it()
    {
        $path = $this->file();
        $absent = Storage::disk(AttachmentStorage::DISK)->path('unavailable-root');
        config(['filesystems.disks.ticket_attachments.root' => $absent]);
        $this->artisan('attachments:reconcile')->assertExitCode(1);
        $this->artisan('attachments:privatize', ['--dry-run' => true])->assertExitCode(1);
        $this->assertDirectoryDoesNotExist($absent);
        Storage::disk(AttachmentStorage::DISK)->assertExists($path);
    }

    public function test_overlapping_disk_roots_prevent_cleanup()
    {
        $path = $this->file();
        config(['filesystems.disks.ticket_attachments.root' => Storage::disk('public')->path('')]);
        $this->artisan('attachments:reconcile', ['--cleanup' => true, '--uploads-paused' => true])->assertExitCode(1);
        Storage::disk(AttachmentStorage::DISK)->assertExists($path);
    }

    public function test_candidate_changed_since_scan_is_retained()
    {
        $path = $this->file();
        $inventory = \Mockery::mock(AttachmentInventory::class)->makePartial();
        $snapshot = (new AttachmentInventory)->snapshot(AttachmentStorage::DISK, $path);
        $inventory->shouldReceive('snapshot')->with(AttachmentStorage::DISK, $path)->once()->ordered()->andReturn($snapshot);
        $inventory->shouldReceive('snapshot')->with(AttachmentStorage::DISK, $path)->once()->ordered()->andReturn(array_merge($snapshot, ['sha256' => 'changed']));
        $this->app->instance(AttachmentInventory::class, $inventory);
        $this->artisan('attachments:reconcile', ['--cleanup' => true, '--uploads-paused' => true])->assertExitCode(0);
        Storage::disk(AttachmentStorage::DISK)->assertExists($path);
    }

    public function test_database_failure_retains_everything()
    {
        $path = $this->file();
        $inventory = \Mockery::mock(AttachmentInventory::class)->makePartial();
        $inventory->shouldReceive('references')->once()->andThrow(new \RuntimeException('database unavailable'));
        $this->app->instance(AttachmentInventory::class, $inventory);
        $this->artisan('attachments:reconcile', ['--cleanup' => true, '--uploads-paused' => true])->assertExitCode(1);
        Storage::disk(AttachmentStorage::DISK)->assertExists($path);
    }

    public function test_incomplete_second_disk_scan_cannot_authorize_deletion()
    {
        $path = $this->file();
        $inventory = \Mockery::mock(AttachmentInventory::class)->makePartial();
        $inventory->shouldReceive('files')->with('public')->once()->andThrow(new \RuntimeException('listing failed'));
        $this->app->instance(AttachmentInventory::class, $inventory);
        $this->artisan('attachments:reconcile', ['--cleanup' => true, '--uploads-paused' => true])->assertExitCode(1);
        Storage::disk(AttachmentStorage::DISK)->assertExists($path);
    }

    public function test_fresh_reference_check_retains_a_newly_referenced_file()
    {
        $path = $this->file();
        $inventory = \Mockery::mock(AttachmentInventory::class)->makePartial();
        $inventory->shouldReceive('references')->once()->ordered()->andReturn([]);
        $inventory->shouldReceive('references')->once()->ordered()->andReturn([$path => true]);
        $this->app->instance(AttachmentInventory::class, $inventory);
        $this->artisan('attachments:reconcile', ['--cleanup' => true, '--uploads-paused' => true])->assertExitCode(0);
        Storage::disk(AttachmentStorage::DISK)->assertExists($path);
    }

    public function test_failed_fresh_reference_check_retains_candidate()
    {
        $path = $this->file();
        $inventory = \Mockery::mock(AttachmentInventory::class)->makePartial();
        $inventory->shouldReceive('references')->once()->ordered()->andReturn([]);
        $inventory->shouldReceive('references')->once()->ordered()->andThrow(new \RuntimeException('uncertain commit'));
        $this->app->instance(AttachmentInventory::class, $inventory);
        $this->artisan('attachments:reconcile', ['--cleanup' => true, '--uploads-paused' => true])->assertExitCode(1);
        Storage::disk(AttachmentStorage::DISK)->assertExists($path);
    }

    public function test_failed_storage_delete_is_reported_and_file_retained()
    {
        $path = $this->file(); $disk = Storage::disk(AttachmentStorage::DISK);
        $proxy = \Mockery::mock($disk);
        $proxy->shouldReceive('delete')->with($path)->once()->andReturn(false);
        Storage::set(AttachmentStorage::DISK, $proxy);
        $this->artisan('attachments:reconcile', ['--cleanup' => true, '--uploads-paused' => true])->assertExitCode(1);
        $disk->assertExists($path);
    }

    public function test_privatization_dry_run_detects_conflict_without_changing_either_disk()
    {
        $path = $this->file('attachments/conflict.txt', true, 'public');
        Storage::disk(AttachmentStorage::DISK)->put($path, 'conflicting bytes');
        $this->artisan('attachments:privatize', ['--dry-run' => true])->assertExitCode(1);
        $this->assertSame('fixture bytes', Storage::disk('public')->get($path));
        $this->assertSame('conflicting bytes', Storage::disk(AttachmentStorage::DISK)->get($path));
    }

    public function test_privatization_resumes_verified_copies_and_preserves_other_assets()
    {
        $path = $this->file('attachments/copied.txt', true, 'public');
        Storage::disk(AttachmentStorage::DISK)->put($path, 'fixture bytes');
        Storage::disk('public')->put('avatars/keep.txt', 'keep');
        $this->artisan('attachments:privatize')->assertExitCode(0);
        $this->artisan('attachments:privatize')->assertExitCode(0);
        Storage::disk('public')->assertMissing($path);
        Storage::disk(AttachmentStorage::DISK)->assertExists($path);
        Storage::disk('public')->assertExists('avatars/keep.txt');
    }
}
