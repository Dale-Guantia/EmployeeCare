<?php

namespace Tests\Feature;

use App\Models\{Status, Ticket, User};
use Carbon\Carbon;
use Database\Seeders\DashboardDemoSeeder;
use Illuminate\Support\Facades\{DB, Notification};
use Tests\TestCase;

class DashboardDemoSeederTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_demo_data_populates_dashboard_without_notifications_or_overwriting_existing_records(): void
    {
        Carbon::setTestNow('2026-11-24 12:00:00');
        Notification::fake();
        $usersBefore = DB::table('users')->orderBy('id')->get()->toJson();
        $this->seed(DashboardDemoSeeder::class);

        $this->assertSame(240, Ticket::count());
        $this->assertSame(360, DB::table('ticket_comments')->count());
        $this->assertSame(27, DB::table('ticket_reassignment_requests')->count());
        $this->assertSame(8, Ticket::distinct()->count('division_id'));
        $this->assertSame(6, Ticket::all()->groupBy(fn ($ticket) => $ticket->created_at->format('Y-m'))->count());
        $this->assertSame(144, Ticket::where('status_id', Status::idByCode(Status::RESOLVED))->count());
        $this->assertSame(0, Ticket::where('created_at', '>', now())->count());
        $this->assertSame(0, Ticket::where('resolved_at', '>', now())->count());
        $this->assertSame(0, Ticket::whereColumn('resolved_at', '<', 'created_at')->count());
        $this->assertSame(0, DB::table('ticket_comments')->where('created_at', '>', now())->count());
        $this->assertSame(0, DB::table('ticket_reassignment_requests')->where('responded_at', '>', now())->count());
        $this->assertSame(0, DB::table('notifications')->count());
        Notification::assertNothingSent();

        $admin = User::role('admin')->firstOrFail();
        $response = $this->actingAs($admin, 'web')->get(route('backpack.dashboard'))->assertOk();
        $metrics = $response->viewData('dashboardMetrics');
        $this->assertSame(120, $metrics['total']);
        foreach (['open', 'resolved', 'overdue_open', 'due_soon', 'reopened', 'on_time_resolved', 'late_resolved'] as $key) {
            $this->assertGreaterThan(0, $metrics[$key], $key);
        }
        $this->assertNotEmpty($response->viewData('staffLeaderboard'));
        $this->assertGreaterThan(0, $response->viewData('reassignedCount'));
        $this->assertGreaterThan(0, $response->viewData('olderOpenCount'));
        $response->assertSee('DEMO-DASH-');

        DB::table('tickets')->where('reference_id', 'DEMO-DASH-0001')->update(['message' => 'User-edited demo ticket']);
        $before = [];
        foreach (['tickets', 'ticket_comments', 'ticket_reassignment_requests'] as $table) {
            $before[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }
        Carbon::setTestNow('2026-12-24 12:00:00');
        $this->seed(DashboardDemoSeeder::class);
        foreach ($before as $table => $rows) {
            $this->assertSame($rows, DB::table($table)->orderBy('id')->get()->toJson(), $table);
        }
        $this->assertSame($usersBefore, DB::table('users')->orderBy('id')->get()->toJson());
    }

    public function test_missing_prerequisites_leave_no_partial_demo_data(): void
    {
        DB::table('priorities')->update(['is_active' => false]);
        try {
            $this->seed(DashboardDemoSeeder::class);
            $this->fail('Seeding must fail when there are no active priorities.');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString('active employees, priorities', $exception->getMessage());
        }
        $this->assertSame(0, Ticket::count());
        $this->assertSame(0, DB::table('ticket_comments')->count());
    }

    public function test_demo_seeding_is_refused_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        try {
            $this->expectException(\LogicException::class);
            $this->expectExceptionMessage('only be seeded locally or in tests');
            (new DashboardDemoSeeder)->run();
        } finally {
            $this->app->detectEnvironment(fn () => 'testing');
        }
    }
}
