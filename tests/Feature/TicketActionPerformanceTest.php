<?php

namespace Tests\Feature;

use App\Models\Status;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Tests\TestCase;

class TicketActionPerformanceTest extends TestCase
{
    public function test_repeated_resolve_and_reopen_preserve_state_and_bound_queries(): void
    {
        $actor = User::role('admin')->firstOrFail();
        $ticket = Ticket::withoutEvents(fn () => Ticket::create([
            'reference_id' => 'ACTION-BENCHMARK',
            'user_id' => $actor->id, 'department_id' => $actor->department_id,
            'division_id' => $actor->division_id, 'status_id' => Status::idByCode(Status::PENDING),
            'is_custom_issue' => true, 'custom_issue' => 'Action benchmark', 'message' => 'Synthetic benchmark',
        ]));
        $this->actingAs($actor, 'web');
        $metrics = [];
        for ($index = 0; $index < 10; $index++) {
            foreach (['resolve' => 'Resolved', 'reopen' => 'Reopened'] as $action => $status) {
                $this->app->forgetInstance('crud'); Facade::clearResolvedInstance('crud');
                foreach ($this->app['router']->getRoutes() as $route) { $route->flushController(); }
                DB::enableQueryLog(); DB::flushQueryLog();
                $start = hrtime(true);
                $this->put(backpack_url('ticket/' . $ticket->id), ['_ticket_action' => $action])->assertRedirect();
                $metrics[] = ['action' => $action, 'milliseconds' => round((hrtime(true) - $start) / 1e6, 3), 'queries' => count(DB::getQueryLog())];
                DB::disableQueryLog();
                $ticket->refresh();
                $this->assertSame(Status::idByName($status), $ticket->status_id);
                $this->assertSame($status === 'Resolved', $ticket->resolved_at !== null);
                $this->assertLessThan(45, end($metrics)['queries']);
            }
        }
        if ($label = getenv('ACTION_BENCHMARK_LABEL')) {
            if (!preg_match('/\A[a-z0-9-]+\z/', $label)) { throw new \LogicException('Invalid benchmark label.'); }
            file_put_contents(storage_path('app/browser-fixes-backup/actions-' . $label . '.json'), json_encode($metrics, JSON_PRETTY_PRINT));
        }
    }
}
