<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Division;
use App\Models\Issue;
use App\Models\Priority;
use App\Models\Status;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Services\AttachmentStorage;
use App\Services\TicketNotificationService;
use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use App\Http\Livewire\TicketChat;
use Tests\TestCase;

class TicketChatAttachmentValidationTest extends TestCase
{
    use DatabaseTransactions;

    private function makeTicket(User $admin): Ticket
    {
        $dept = Department::create(['department_name' => 'ChatValDept_' . uniqid()]);
        $div = Division::create(['division_name' => 'ChatValDiv_' . uniqid(), 'department_id' => $dept->id]);
        $priority = Priority::firstOrCreate(['priority_name' => 'Normal'], ['priority_color' => '#ccc']);
        $status = Status::firstOrCreate(['status_name' => 'Unassigned'], ['status_color' => '#ccc']);
        $issue = Issue::create([
            'department_id' => $dept->id,
            'division_id' => $div->id,
            'priority_id' => $priority->id,
            'issue_description' => 'Chat Attachment Test Issue ' . uniqid(),
        ]);

        $ticket = new Ticket();
        $ticket->forceFill([
            'user_id' => $admin->id,
            'department_id' => $dept->id,
            'division_id' => $div->id,
            'issue_id' => $issue->id,
            'status_id' => $status->id,
            'message' => 'attachment validation test',
        ]);
        $ticket->save();

        return $ticket;
    }

    public function test_disallowed_file_extension_is_rejected()
    {
        $admin = User::role('admin')->first();
        $this->assertNotNull($admin);
        $ticket = $this->makeTicket($admin);

        $this->actingAs($admin, 'web');

        Livewire::test(TicketChat::class, ['ticketId' => $ticket->id])
            ->set('comment', 'here is a file')
            ->set('attachments', [File::create('malicious.html', 10)])
            ->call('sendComment')
            ->assertHasErrors(['attachments.0'])
            ->assertSee('Allowed file types:');
    }

    public function test_allowed_file_extension_is_accepted()
    {
        $admin = User::role('admin')->first();
        $this->assertNotNull($admin);
        $ticket = $this->makeTicket($admin);

        $this->actingAs($admin, 'web');

        Livewire::test(TicketChat::class, ['ticketId' => $ticket->id])
            ->set('comment', 'here is a pdf')
            ->set('attachments', [File::create('document.pdf', 10)])
            ->call('sendComment')
            ->assertHasNoErrors();
    }

    public function test_temporary_upload_errors_are_visible_in_the_discussion()
    {
        $admin = User::role('admin')->firstOrFail();
        $ticket = $this->makeTicket($admin);
        $this->actingAs($admin, 'web');

        Livewire::test(TicketChat::class, ['ticketId' => $ticket->id])
            ->call('uploadErrored', 'attachments', null, true)
            ->assertHasErrors('attachments')
            ->assertSee('The attachments failed to upload.');
    }

    public function test_livewire_can_remove_a_temporary_upload_by_filename()
    {
        $admin = User::role('admin')->firstOrFail();
        $ticket = $this->makeTicket($admin);
        $this->actingAs($admin, 'web');
        $component = Livewire::test(TicketChat::class, ['ticketId' => $ticket->id])
            ->set('attachments', [UploadedFile::fake()->create('remove.txt', 1, 'text/plain')]);
        $files = \Livewire\TemporaryUploadedFile::unserializeFromLivewireRequest($component->get('attachments'));
        $file = $files[0];

        $component->call('removeUpload', 'attachments', $file->getFilename())
            ->assertSet('attachments', []);
        $this->assertFalse($file->exists());
    }

    public function test_failed_livewire_storage_does_not_create_a_comment_or_clear_the_draft()
    {
        $admin = User::role('admin')->firstOrFail();
        $ticket = $this->makeTicket($admin);
        $this->actingAs($admin, 'web');
        $component = Livewire::test(TicketChat::class, ['ticketId' => $ticket->id])
            ->set('comment', 'Keep this draft')
            ->set('attachments', [UploadedFile::fake()->create('document.txt', 1, 'text/plain')]);
        $disk = Storage::disk(AttachmentStorage::DISK);
        $broken = \Mockery::mock($disk)->makePartial();
        $broken->shouldReceive('put')->once()->andReturn(false);
        Storage::set(AttachmentStorage::DISK, $broken);
        try {
            $component->call('sendComment')->assertHasErrors('attachments')
                ->assertSee('The attachments could not be saved. Please try again.')
                ->assertSet('comment', 'Keep this draft');
            $this->assertSame(0, TicketComment::where('ticket_id', $ticket->id)->count());
            $this->assertSame([], $disk->allFiles('attachments'));
        } finally {
            Storage::set(AttachmentStorage::DISK, $disk);
        }
    }

    public function test_plain_text_persistence_errors_are_visible_and_retain_the_draft()
    {
        $admin = User::role('admin')->firstOrFail();
        $ticket = $this->makeTicket($admin);
        $this->actingAs($admin, 'web');
        $notifications = \Mockery::mock(TicketNotificationService::class);
        $notifications->shouldReceive('notifyTicketCommented')->once()
            ->andThrow(new \RuntimeException('Injected notification persistence failure'));
        $this->app->instance(TicketNotificationService::class, $notifications);

        Livewire::test(TicketChat::class, ['ticketId' => $ticket->id])
            ->set('comment', 'Keep my reply')->call('sendComment')
            ->assertHasErrors('attachments')
            ->assertSee('The comment and attachments could not be saved. Please try again.')
            ->assertSet('comment', 'Keep my reply');
        $this->assertSame(0, TicketComment::where('ticket_id', $ticket->id)->count());
    }

    public function test_temporary_upload_accepts_a_file_between_twelve_and_twenty_megabytes()
    {
        Storage::fake(\Livewire\FileUploadConfiguration::disk());
        $paths = (new \Livewire\Controllers\FileUploadHandler)->validateAndStore(
            [File::create('document.pdf', 16 * 1024, 'application/pdf')],
            \Livewire\FileUploadConfiguration::disk()
        );

        $this->assertCount(1, $paths);
        Storage::disk(\Livewire\FileUploadConfiguration::disk())->assertExists(
            \Livewire\FileUploadConfiguration::path($paths->first())
        );
    }

    public function test_temporary_upload_rejects_files_over_twenty_megabytes()
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        (new \Livewire\Controllers\FileUploadHandler)->validateAndStore(
            [File::create('large.pdf', 20 * 1024 + 1, 'application/pdf')],
            \Livewire\FileUploadConfiguration::disk()
        );
    }
}
