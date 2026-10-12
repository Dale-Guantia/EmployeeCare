<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SyncUserAvatarsTest extends TestCase
{
    private $sourceRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sourceRoot = storage_path('framework/testing/avatar-source-' . uniqid());
        File::makeDirectory($this->sourceRoot . '/assets/avatars', 0755, true);
        $this->app->instance('path.public', $this->sourceRoot);
    }

    protected function tearDown(): void
    {
        try {
            if ($this->sourceRoot) {
                File::deleteDirectory($this->sourceRoot);
            }
        } finally {
            parent::tearDown();
        }
    }

    private function employee(string $empNo, ?string $avatar = null): User
    {
        $user = new User;
        $user->forceFill(['emp_no' => $empNo, 'name' => 'Avatar employee',
            'email' => uniqid('avatar') . '@example.test', 'password' => 'unused', 'avatar_url' => $avatar]);
        $user->save();
        return $user;
    }

    private function source(string $filename, string $contents = 'avatar bytes'): void
    {
        File::put($this->sourceRoot . '/assets/avatars/' . $filename, $contents);
    }

    public function test_sync_copies_matches_replaces_existing_paths_and_preserves_sources(): void
    {
        $employee = $this->employee('009876', 'avatars/uploaded.png');
        $other = $this->employee('9876');
        $this->source('009876.webp');
        $this->source('999999999.webp');
        $this->source('uploaded.png');
        Storage::disk('public')->put('avatars/009876.webp', 'old bytes');
        Storage::disk('public')->put('avatars/uploaded.png', 'personal upload');

        $this->artisan('users:sync-avatars')->assertExitCode(0);

        $this->assertSame('avatars/009876.webp', $employee->fresh()->avatar_url);
        $this->assertNull($other->fresh()->avatar_url);
        $this->assertSame('avatar bytes', Storage::disk('public')->get('avatars/009876.webp'));
        $this->assertFileExists($this->sourceRoot . '/assets/avatars/009876.webp');
        Storage::disk('public')->assertExists('avatars/uploaded.png');
        Storage::disk('public')->assertMissing('avatars/999999999.webp');
        $this->assertSame(asset('storage/avatars/009876.webp'), $employee->fresh()->getAvatarUrl());
        $this->assertSame($employee->fresh()->getAvatarUrl(), $employee->fresh()->getAvatarWebpUrl());

        $before = $employee->fresh()->getAttributes();
        $this->artisan('users:sync-avatars')->assertExitCode(0);
        $this->assertSame($before, $employee->fresh()->getAttributes());
    }

    public function test_dry_run_does_not_copy_or_update(): void
    {
        $employee = $this->employee('009876', 'avatars/uploaded.png');
        $this->source('009876.webp');
        $this->artisan('users:sync-avatars', ['--dry-run' => true])->assertExitCode(0);
        $this->assertSame('avatars/uploaded.png', $employee->fresh()->avatar_url);
        Storage::disk('public')->assertMissing('avatars/009876.webp');
    }

    public function test_duplicate_employee_numbers_are_rejected(): void
    {
        $first = $this->employee('009876');
        $second = $this->employee('009876');
        $this->source('009876.webp');
        $this->artisan('users:sync-avatars')->assertExitCode(1);
        $this->assertNull($first->fresh()->avatar_url);
        $this->assertNull($second->fresh()->avatar_url);
        Storage::disk('public')->assertMissing('avatars/009876.webp');
    }

    public function test_failed_copy_does_not_save_avatar_path(): void
    {
        $employee = $this->employee('009876', 'avatars/uploaded.png');
        $this->source('009876.webp');
        $disk = \Mockery::mock();
        $disk->shouldReceive('exists')->with('avatars/009876.webp')->andReturn(false);
        $disk->shouldReceive('put')->with('avatars/009876.webp', 'avatar bytes')->andReturn(false);
        Storage::shouldReceive('disk')->with('public')->andReturn($disk);

        $this->artisan('users:sync-avatars')->assertExitCode(1);
        $this->assertSame('avatars/uploaded.png', $employee->fresh()->avatar_url);
    }
}
