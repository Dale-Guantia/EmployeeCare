<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['issues', 'tickets'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropForeign(['priority_id']);
                $table->foreign('priority_id')->references('id')->on('priorities')->noActionOnDelete();
            });
        }
        if (!Schema::hasColumn('statuses', 'is_active')) {
            Schema::table('statuses', fn (Blueprint $table) => $table->boolean('is_active')->default(true));
        }
        $this->createAuditTable();
        $guard = config('auth.defaults.guard', 'web');
        $admin = Role::where('name', 'admin')->where('guard_name', $guard)->first();
        foreach (['department', 'division', 'issue', 'priority', 'status'] as $entity) {
            foreach (['archive', 'reactivate'] as $action) {
                $permission = Permission::firstOrCreate(['name' => "$entity.$action", 'guard_name' => $guard]);
                $admin?->givePermissionTo($permission);
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function createAuditTable(): void
    {
        if (!Schema::hasTable('master_data_audits')) {
            Schema::create('master_data_audits', function (Blueprint $table) {
                $table->id();
                $table->string('record_type', 64);
                $table->unsignedBigInteger('record_id');
                // Deliberately retain actor IDs and snapshots after a master record or actor is deleted.
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('action', 20);
                $table->string('reason', 1000);
                $table->text('before_values')->nullable();
                $table->text('after_values')->nullable();
                $table->timestamp('created_at');
                $table->index(['record_type', 'record_id']);
            });
        }
    }

    public function down(): void
    {
        // Keep history-preserving foreign keys even on rollback. Never restore SET NULL deletion.
        Schema::table('statuses', fn (Blueprint $table) => $table->dropColumn('is_active'));
        // Audit history and permission grants are retained intentionally.
    }
};
