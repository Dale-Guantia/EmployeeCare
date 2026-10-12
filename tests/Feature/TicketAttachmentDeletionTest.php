<?php

namespace Tests\Feature;

use App\Models\Status;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Services\AttachmentStorage;
use App\Services\TicketNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TicketAttachmentDeletionTest extends TestCase
{
    private $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(TicketNotificationService::class, \Mockery::mock(TicketNotificationService::class)->shouldIgnoreMissing());
        $this->owner = User::create([
            'name' => 'Deletion reviewer', 'username' => 'deletion-reviewer',
            'email' => 'deletion-reviewer@example.test', 'password' => bcrypt('fixture'),
            'is_active' => true,
        ]);
        $this->owner->assignRole('admin');
    }

    private function ticketWithComment(string $suffix = 'one'): Ticket
    {
        $ticket = Ticket::withoutEvents(function () use ($suffix) {
            return Ticket::create([
                'reference_id' => 'DELETE-' . $suffix, 'user_id' => $this->owner->id,
                'status_id' => Status::idByName('Unassigned'), 'message' => 'Fixture',
                'attachments' => ['attachments/ticket-' . $suffix . '.txt'],
            ]);
        });
        TicketComment::create([
            'ticket_id' => $ticket->id, 'user_id' => $this->owner->id, 'comment' => 'Fixture',
            'attachment' => ['attachments/comment-' . $suffix . '.txt'],
        ]);
        foreach ([AttachmentStorage::DISK, 'public'] as $disk) {
            Storage::disk($disk)->put('attachments/ticket-' . $suffix . '.txt', 'ticket');
            Storage::disk($disk)->put('attachments/comment-' . $suffix . '.txt', 'comment');
        }
        return $ticket;
    }

    private function assertFiles(bool $exist, string $suffix = 'one'): void
    {
        foreach ([AttachmentStorage::DISK, 'public'] as $disk) {
            foreach (['ticket', 'comment'] as $kind) {
                $path = 'attachments/' . $kind . '-' . $suffix . '.txt';
                if ($exist) {
                    Storage::disk($disk)->assertExists($path);
                } else {
                    Storage::disk($disk)->assertMissing($path);
                }
            }
        }
    }

    public function test_delete_endpoint_removes_ticket_comments_and_files_but_preserves_other_tickets()
    {
        $ticket = $this->ticketWithComment();
        $other = $this->ticketWithComment('other');
        $this->actingAs($this->owner, 'web')
            ->delete('/' . config('backpack.base.route_prefix') . '/ticket/' . $ticket->id)->assertOk();

        $this->assertNull($ticket->fresh());
        $this->assertSame(0, TicketComment::where('ticket_id', $ticket->id)->count());
        $this->assertFiles(false);
        $this->assertNotNull($other->fresh());
        $this->assertSame(1, $other->comments()->count());
        $this->assertFiles(true, 'other');
    }

    public function test_files_are_removed_only_after_outer_transaction_commits()
    {
        $ticket = $this->ticketWithComment();
        DB::transaction(function () use ($ticket) {
            $ticket->delete();
            $this->assertFiles(true);
        });
        $this->assertFiles(false);
    }

    public function test_rollback_preserves_ticket_comments_and_all_files()
    {
        $ticket = $this->ticketWithComment();
        try {
            DB::transaction(function () use ($ticket) {
                $ticket->delete();
                throw new \RuntimeException('Rollback fixture');
            });
            $this->fail('Expected rollback.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Rollback fixture', $e->getMessage());
        }
        $this->assertNotNull(Ticket::find($ticket->id));
        $this->assertSame(1, TicketComment::where('ticket_id', $ticket->id)->count());
        $this->assertFiles(true);
        // A later commit must not execute cleanup from the rolled-back deletion.
        DB::transaction(function () {});
        $this->assertFiles(true);
    }

    public function test_failed_ticket_delete_restores_comments_and_preserves_files()
    {
        $ticket = $this->ticketWithComment();
        Ticket::deleted(function () { throw new \RuntimeException('Delete fixture failure'); });
        try {
            $ticket->delete();
            $this->fail('Expected deletion failure.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Delete fixture failure', $e->getMessage());
        }
        $this->assertTrue($ticket->exists);
        $this->assertNotNull($ticket->fresh());
        $this->assertSame(1, $ticket->comments()->count());
        $this->assertFiles(true);
    }

    public function test_cancelled_comment_delete_keeps_ticket_and_files()
    {
        $ticket = $this->ticketWithComment();
        TicketComment::deleting(function () { return false; });
        try {
            $ticket->delete();
            $this->fail('Expected cancellation.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Ticket comment deletion was cancelled.', $e->getMessage());
        }
        $this->assertNotNull($ticket->fresh());
        $this->assertSame(1, $ticket->comments()->count());
        $this->assertFiles(true);
    }

    public function test_cancelled_ticket_delete_restores_previously_deleted_comments()
    {
        $ticket = $this->ticketWithComment();
        Ticket::deleting(function () { return false; });
        try {
            $ticket->delete();
            $this->fail('Expected cancellation.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Ticket deletion was cancelled.', $e->getMessage());
        }
        $this->assertNotNull($ticket->fresh());
        $this->assertSame(1, $ticket->comments()->count());
        $this->assertFiles(true);
    }

    public function test_standalone_comment_delete_cleans_its_files_after_commit()
    {
        $ticket = $this->ticketWithComment();
        DB::transaction(function () use ($ticket) {
            $ticket->comments()->first()->delete();
            $this->assertFiles(true);
        });
        $this->assertNotNull($ticket->fresh());
        foreach ([AttachmentStorage::DISK, 'public'] as $disk) {
            Storage::disk($disk)->assertMissing('attachments/comment-one.txt');
            Storage::disk($disk)->assertExists('attachments/ticket-one.txt');
        }
    }

    public function test_deleting_more_than_one_batch_removes_all_comment_files()
    {
        $ticket = $this->ticketWithComment();
        for ($i = 0; $i < 101; $i++) {
            $path = 'attachments/batch-' . $i . '.txt';
            TicketComment::create([
                'ticket_id' => $ticket->id, 'user_id' => $this->owner->id,
                'comment' => 'Batch fixture', 'attachment' => [$path],
            ]);
            Storage::disk(AttachmentStorage::DISK)->put($path, 'fixture');
        }
        $ticket->delete();
        $this->assertSame(0, TicketComment::where('ticket_id', $ticket->id)->count());
        $this->assertSame([], Storage::disk(AttachmentStorage::DISK)->allFiles('attachments'));
        $this->assertFiles(false);
    }
}
