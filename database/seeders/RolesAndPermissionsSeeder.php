<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run()
    {
        // 1. Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 2. Create Permissions
        // Based on your screenshot, you need these:
        $permissions = [
            'tickets', 'issues', 'priorities',
            'departments', 'divisions', 'authentication', 'reports', 'survey_response'
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // 3. Create Roles and Assign Permissions

        // Admin: Gets everything
        $role = Role::create(['name' => 'admin']);
        $role->givePermissionTo(Permission::all());

        // Dept Head: Specific permissions
        $role = Role::create(['name' => 'dept_head']);
        $role->givePermissionTo(['tickets', 'issues', 'departments', 'divisions']);

        // Dept Head: Specific permissions
        $role = Role::create(['name' => 'div_head']);
        $role->givePermissionTo(['tickets', 'issues', 'departments', 'divisions']);

        // Dept Head: Specific permissions
        $role = Role::create(['name' => 'hr_staff']);
        $role->givePermissionTo(['tickets']);

        // Employee: Limited access
        $role = Role::create(['name' => 'employee']);
        $role->givePermissionTo(['tickets']);
    }
}
