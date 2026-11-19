<?php

namespace Tests\Engine;

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EngineDatabase;

/** Opt-in verification on an explicitly configured, empty SQL Server/MySQL test database. */
class PriorityLifecycleEngineTest extends \PHPUnit\Framework\TestCase
{
    private $app;

    protected function tearDown(): void
    {
        if ($this->app) {
            $this->app->flush();
            \Illuminate\Foundation\Bootstrap\HandleExceptions::flushState($this);
        }
        parent::tearDown();
    }

    public function test_priority_migrations_preserve_history_and_restrict_both_foreign_keys(): void
    {
        if (getenv('ENGINE_TEST_DRIVER') === false) {
            $this->markTestSkipped('No isolated engine database configured. See docs/DEPLOYMENT_READINESS.md.');
        }
        $this->app = EngineDatabase::boot();
        EngineDatabase::claimEmptyDatabase(bin2hex(random_bytes(32)));

        Schema::create('priorities', function (Blueprint $table) {
            $table->id();
            $table->string('priority_name');
        });
        Schema::create('statuses', function (Blueprint $table) {
            $table->id();
        });
        Schema::create('issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('priority_id')->nullable()->constrained();
        });
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('priority_id')->nullable()->constrained();
            $table->foreignId('issue_id')->nullable()->constrained();
            $table->foreignId('status_id')->constrained();
        });
        $priority = DB::table('priorities')->insertGetId(['priority_name' => 'Synthetic priority']);
        $status = DB::table('statuses')->insertGetId([]);
        $issue = DB::table('issues')->insertGetId(['priority_id' => $priority]);
        $ticket = DB::table('tickets')->insertGetId(['priority_id' => $priority, 'issue_id' => $issue, 'status_id' => $status]);

        $migration = require database_path('migrations/2026_11_03_090000_add_priority_lifecycle.php');
        $migration->up();
        require_once database_path('migrations/2026_02_23_070500_create_permission_tables.php');
        (new \CreatePermissionTables)->up();
        $restriction = require database_path('migrations/2026_11_04_090000_enforce_master_data_lifecycle.php');
        $restriction->up();
        $this->assertTrue((bool) DB::table('priorities')->where('id', $priority)->value('is_active'));
        $this->assertSame((int) $priority, (int) DB::table('issues')->where('id', $issue)->value('priority_id'));
        DB::table('priorities')->where('id', $priority)->update(['is_active' => false]);
        $this->assertSame((int) $priority, (int) DB::table('tickets')->where('id', $ticket)->value('priority_id'));
        try {
            DB::table('priorities')->where('id', $priority)->delete();
            $this->fail('Referenced priorities must not be deleted or clear historical links.');
        } catch (QueryException $e) {
            $this->assertSame('23000', $e->errorInfo[0]);
        }
        $this->assertSame((int) $priority, (int) DB::table('issues')->where('id', $issue)->value('priority_id'));
        $this->assertSame((int) $priority, (int) DB::table('tickets')->where('id', $ticket)->value('priority_id'));
        $this->assertSame(1, DB::table('issues')->count());
        $this->assertSame(1, DB::table('tickets')->count());

        try {
            DB::table('statuses')->where('id', $status)->delete();
            $this->fail('Status deletion must still be restricted.');
        } catch (QueryException $e) {
            $this->assertSame('23000', $e->errorInfo[0]);
        }

        $restriction->down();
        $this->assertTrue(Schema::hasTable('master_data_audits'));
        $migration->down();
        $this->assertFalse(Schema::hasColumn('priorities', 'is_active'));
        $other = DB::table('priorities')->insertGetId(['priority_name' => 'Rollback priority']);
        DB::table('issues')->where('id', $issue)->update(['priority_id' => $other]);
        try {
            DB::table('priorities')->where('id', $other)->delete();
            $this->fail('Rollback must restore the original delete restriction.');
        } catch (QueryException $e) {
            $this->assertSame('23000', $e->errorInfo[0]);
        }
        // Retain all synthetic fixtures for inspection; never wipe or drop the test database.
    }
}
