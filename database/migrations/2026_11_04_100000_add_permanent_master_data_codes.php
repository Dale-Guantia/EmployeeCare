<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COLUMNS = [
        'departments' => 'department_code', 'divisions' => 'division_code',
        'statuses' => 'status_code', 'priorities' => 'priority_code',
    ];

    public function up(): void
    {
        // Resolve every identity before changing schema or data. Never guess from seed order or IDs.
        $plans = $this->backfillPlans();
        $this->addCodeColumns(array_keys(self::COLUMNS));
        foreach ($plans as $table => $rows) {
            foreach ($rows as $id => $code) {
                DB::table($table)->where('id', $id)->update([self::COLUMNS[$table] => $code]);
            }
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->unique(self::COLUMNS[$table]));
            Cache::forget('ref:' . $table . '.by_code');
        }
        Cache::forget('ref:departments.all');
        Cache::forget('ref:divisions.all');
        Cache::forget('ref:statuses.by_name');
    }

    public function addCodeColumns(array $tables): void
    {
        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                // Additive schema change; model writes always assign and validate a nonempty code.
                $blueprint->string(self::COLUMNS[$table], 64)->nullable();
            });
        }
    }

    private function backfillPlans(): array
    {
        $names = [
            'departments' => ['City Human Resource Development Office' => 'hrdo'],
            'divisions' => ['Performance Management' => 'pm', 'Learning and Development' => 'lnd',
                'RSP' => 'rsp', 'Claims and Benefits' => 'claims', 'Records' => 'records', 'Payroll' => 'payroll',
                'Administrative' => 'admin', 'Information Technology' => 'it', 'Department Head' => 'dh'],
            'statuses' => ['Resolved' => 'resolved', 'Pending' => 'pending', 'Unassigned' => 'unassigned', 'Reopened' => 'reopened'],
            'priorities' => ['High' => 'high', 'Medium' => 'medium', 'Low' => 'low'],
        ];
        $plans = [];
        $hrdoId = null;
        foreach ($names as $table => $mapping) {
            $plans[$table] = [];
            foreach (DB::table($table)->pluck('id') as $id) {
                // Preserve additional records too; their identity must not depend on a mutable label.
                $plans[$table][$id] = 'legacy_' . $id;
            }
            if (!$plans[$table]) {
                continue; // Fresh installations seed their explicit codes after migrations.
            }
            $nameColumn = str_replace('_code', '_name', self::COLUMNS[$table]);
            foreach ($mapping as $name => $code) {
                $query = DB::table($table)->where($nameColumn, $name);
                if ($table === 'divisions') {
                    $query->where('department_id', $hrdoId);
                }
                $matches = $query->pluck('id');
                if ($matches->count() !== 1) {
                    throw new RuntimeException("Cannot assign permanent code '{$code}': expected exactly one '{$name}' in {$table}. Review the existing records before migrating.");
                }
                $id = $matches->first();
                $plans[$table][$id] = $code;
                if ($table === 'departments') {
                    $hrdoId = $id;
                }
            }
        }
        return $plans;
    }

    public function down(): void
    {
        foreach (self::COLUMNS as $table => $column) {
            Schema::table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->dropUnique([$column]);
                $blueprint->dropColumn($column);
            });
            Cache::forget('ref:' . $table . '.by_code');
        }
        Cache::forget('ref:departments.all');
        Cache::forget('ref:divisions.all');
    }
};
