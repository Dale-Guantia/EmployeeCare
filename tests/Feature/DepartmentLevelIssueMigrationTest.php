<?php

namespace Tests\Feature;

use App\Models\{Department, Issue};
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

class DepartmentLevelIssueMigrationTest extends TestCase
{
    public function test_nullable_migration_preserves_existing_rows_and_foreign_keys(): void
    {
        $migration = require database_path('migrations/2026_11_04_110000_allow_department_level_issues.php');
        $before = DB::table('issues')->orderBy('id')->get()->toJson();
        $foreignKeys = Schema::getForeignKeys('issues');
        $migration->down();
        $migration->up();
        $this->assertSame($before, DB::table('issues')->orderBy('id')->get()->toJson());
        $this->assertEquals($foreignKeys, Schema::getForeignKeys('issues'));
        $issue = Issue::firstOrFail();
        $this->expectException(QueryException::class);
        DB::table('issues')->where('id', $issue->id)->update(['division_id' => 999999]);
    }

    public function test_rollback_refuses_to_discard_department_only_issues(): void
    {
        $department = Department::create(['department_name' => 'Nullable migration office']);
        $issue = Issue::create(['department_id' => $department->id, 'issue_description' => 'Department only service']);
        $migration = require database_path('migrations/2026_11_04_110000_allow_department_level_issues.php');
        try {
            $migration->down();
            $this->fail('Rollback must require an explicit office assignment first.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Assign divisions', $e->getMessage());
        }
        $this->assertNull($issue->fresh()->division_id);
    }
}
