<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Division;
use App\Models\Issue;
use App\Models\Priority;
use App\Models\Status;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ReassignmentWidgetXssTest extends TestCase
{
    use DatabaseTransactions;

    public function test_division_name_with_raw_angle_brackets_is_hex_escaped_in_reassignment_widget()
    {
        // Plain Eloquent ->toJson() escapes quotes/slashes/backslashes by
        // default but leaves literal `<` and `>` untouched. @json() adds
        // JSON_HEX_TAG, turning them into < / >. A division name
        // containing a raw "<script>" (no closing slash needed — the slash
        // is what plain toJson() already escapes) demonstrates the gap.
        $admin = User::role('admin')->first();
        $this->assertNotNull($admin);

        $marker = 'xssmarker' . uniqid();
        $dept = Department::create(['department_name' => 'XssDept_' . uniqid()]);
        $div = Division::create(['division_name' => "<script>{$marker}", 'department_id' => $dept->id]);
        $priority = Priority::firstOrCreate(['priority_name' => 'Normal'], ['priority_color' => '#ccc']);
        $status = Status::firstOrCreate(['status_name' => 'Unassigned'], ['status_color' => '#ccc']);
        $issue = Issue::create([
            'department_id' => $dept->id,
            'division_id' => $div->id,
            'priority_id' => $priority->id,
            'issue_description' => 'XSS Test Issue ' . uniqid(),
        ]);

        $ticket = new Ticket();
        $ticket->forceFill([
            'user_id' => $admin->id,
            'department_id' => $dept->id,
            'division_id' => $div->id,
            'issue_id' => $issue->id,
            'status_id' => $status->id,
            'message' => 'xss test ticket',
        ]);
        $ticket->save();

        $this->actingAs($admin, 'web');

        $response = $this->get(route('ticket.show', $ticket->id));

        $response->assertStatus(200);
        // The raw, unescaped "<script>{marker}" must never appear in the
        // response body — it must come through JSON_HEX_TAG-escaped
        // (backslash-u-0-0-3-C / backslash-u-0-0-3-E) instead.
        $backslashU = chr(92) . 'u';
        $hexEscapedOpenTag = $backslashU . '003Cscript' . $backslashU . '003E' . $marker;
        $rawOpenTag = '<script>' . $marker;

        $response->assertDontSee($rawOpenTag, false);
        $response->assertSee($hexEscapedOpenTag, false);
    }
}
