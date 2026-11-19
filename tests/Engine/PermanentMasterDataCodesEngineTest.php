<?php

namespace Tests\Engine;

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\Support\EngineDatabase;

/** Opt-in migration verification on an explicitly configured, empty SQL Server/MySQL test database. */
class PermanentMasterDataCodesEngineTest extends \PHPUnit\Framework\TestCase
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

    public function test_codes_backfill_existing_ids_without_changing_references(): void
    {
        if (getenv('ENGINE_TEST_DRIVER') === false) {
            $this->markTestSkipped('No isolated engine database configured.');
        }
        $this->app = EngineDatabase::boot();
        EngineDatabase::claimEmptyDatabase(bin2hex(random_bytes(32)));
        foreach (['departments' => 'department_name', 'statuses' => 'status_name', 'priorities' => 'priority_name'] as $table => $name) {
            Schema::create($table, function (Blueprint $blueprint) use ($name) {
                $blueprint->id();
                $blueprint->string($name);
                $blueprint->boolean('is_active')->default(true);
            });
        }
        Schema::create('divisions', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->foreignId('department_id')->constrained();
            $blueprint->string('division_name');
            $blueprint->boolean('is_active')->default(true);
        });
        Schema::create('tickets', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->foreignId('department_id')->constrained();
            $blueprint->foreignId('division_id')->constrained();
            $blueprint->foreignId('status_id')->constrained();
            $blueprint->foreignId('priority_id')->constrained();
        });
        DB::table('departments')->insert(['department_name' => 'Additional synthetic department']);
        $department = DB::table('departments')->insertGetId(['department_name' => 'City Human Resource Development Office']);
        $divisionIds = [];
        foreach (['Performance Management', 'Learning and Development', 'RSP', 'Claims and Benefits', 'Records',
            'Payroll', 'Administrative', 'Information Technology', 'Department Head'] as $name) {
            $divisionIds[] = DB::table('divisions')->insertGetId(['department_id' => $department, 'division_name' => $name, 'is_active' => false]);
        }
        $statusIds = [];
        foreach (['Unassigned', 'Reopened', 'Resolved', 'Pending'] as $name) {
            $statusIds[$name] = DB::table('statuses')->insertGetId(['status_name' => $name]);
        }
        $priorityIds = [];
        foreach (['Low', 'High', 'Medium'] as $name) {
            $priorityIds[$name] = DB::table('priorities')->insertGetId(['priority_name' => $name]);
        }
        $ticket = DB::table('tickets')->insertGetId(['department_id' => $department, 'division_id' => $divisionIds[0],
            'status_id' => $statusIds['Resolved'], 'priority_id' => $priorityIds['High']]);
        $before = (array) DB::table('tickets')->find($ticket);
        $migration = require database_path('migrations/2026_11_04_100000_add_permanent_master_data_codes.php');
        $migration->up();
        $this->assertNotSame(1, (int) $department);
        $this->assertSame('hrdo', DB::table('departments')->where('id', $department)->value('department_code'));
        $this->assertSame('unassigned', DB::table('statuses')->where('id', $statusIds['Unassigned'])->value('status_code'));
        $this->assertSame('resolved', DB::table('statuses')->where('id', $statusIds['Resolved'])->value('status_code'));
        $this->assertSame('high', DB::table('priorities')->where('id', $priorityIds['High'])->value('priority_code'));
        $this->assertSame(['pm', 'lnd', 'rsp', 'claims', 'records', 'payroll', 'admin', 'it', 'dh'], DB::table('divisions')->orderBy('id')->pluck('division_code')->all());
        $this->assertSame(0, DB::table('divisions')->where('is_active', true)->count());
        $this->assertSame($before, (array) DB::table('tickets')->find($ticket));
        try {
            DB::table('departments')->insert(['department_name' => 'Duplicate code fixture', 'department_code' => 'hrdo']);
            $this->fail('Codes must have a database uniqueness constraint.');
        } catch (QueryException $e) {
            $this->assertSame('23000', $e->errorInfo[0]);
        }
        // Keep all synthetic records for inspection in the explicitly claimed test database.
    }
}
