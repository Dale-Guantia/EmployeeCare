<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AddExplicitTicketAssignmentPermission extends Migration
{
    public function up()
    {
        $permission = Permission::firstOrCreate(['name' => 'ticket.assign', 'guard_name' => 'web']);
        $admin = Role::where('name', 'admin')->where('guard_name', 'web')->first();
        if ($admin) { $admin->givePermissionTo($permission); }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down()
    {
        // Retain any explicit grants made after deployment.
    }
}
