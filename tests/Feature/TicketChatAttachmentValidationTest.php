<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Division;
use App\Models\Issue;
use App\Models\Priority;
use App\Models\Status;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Testing\File;
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
            ->assertHasErrors(['attachments.0']);
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
}
