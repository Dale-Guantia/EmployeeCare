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
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TicketPerformanceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_list_and_show_pages_query_counts()
    {
        $admin = User::role('admin')->first();
        $this->assertNotNull($admin, 'Expected at least one admin user to exist.');

        $dept = Department::create(['department_name' => 'PerfDept_' . uniqid()]);
        $div = Division::create(['division_name' => 'PerfDiv_' . uniqid(), 'department_id' => $dept->id]);
        $priority = Priority::firstOrCreate(['priority_name' => 'Normal'], ['priority_color' => '#ccc']);
        $status = Status::firstOrCreate(['status_name' => 'Unassigned'], ['status_color' => '#ccc']);
        $issue = Issue::create([
            'department_id' => $dept->id,
            'division_id' => $div->id,
            'priority_id' => $priority->id,
            'issue_description' => 'Perf Test Issue ' . uniqid(),
        ]);

        // Seed enough rows for an N+1 to actually show up (the live DB only
        // has 1 real ticket, which hides any per-row difference).
        $tickets = [];
        for ($i = 0; $i < 15; $i++) {
            $ticket = new Ticket();
            $ticket->forceFill([
                'user_id' => $admin->id,
                'department_id' => $dept->id,
                'division_id' => $div->id,
                'issue_id' => $issue->id,
                'status_id' => $status->id,
                'message' => 'perf test message ' . $i,
            ]);
            $ticket->save();
            $tickets[] = $ticket;
        }

        $this->actingAs($admin, 'web');

        $queryCount = 0;
        $queries = [];
        DB::listen(function ($q) use (&$queryCount, &$queries) {
            $queryCount++;
            $queries[] = $q->sql;
        });

        // Backpack's list page itself is just a shell; row rendering (and any
        // N+1) happens on the DataTables ajax endpoint.
        $response = $this->post('/employee-care/ticket/search');
        $response->assertStatus(200);

        $listQueryCount = $queryCount;
        fwrite(STDERR, "\nLIST PAGE /search (15 tickets) QUERY COUNT: {$listQueryCount}\n");
        if (getenv('HRF_DUMP_SQL')) {
            foreach ($queries as $i => $sql) {
                fwrite(STDERR, "  [{$i}] {$sql}\n");
            }
        }

        $queryCount = 0;
        $queries = [];

        $response = $this->get('/employee-care/ticket/' . $tickets[0]->id . '/show');
        $response->assertStatus(200);

        $showQueryCount = $queryCount;
        fwrite(STDERR, "SHOW PAGE QUERY COUNT: {$showQueryCount}\n");

        // Regression guards, well below the pre-fix N+1 baseline (43 for the
        // list search with 15 rows, 10 for a single-entry show page).
        $this->assertLessThan(25, $listQueryCount);
        $this->assertLessThanOrEqual(9, $showQueryCount);
    }
}
