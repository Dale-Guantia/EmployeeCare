<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guardName = config('auth.defaults.guard', 'web');

        $entities = [
            'ticket',
            'issue',
            'department',
            'division',
            'priority',
            'status',
            'user',
            'role',
            'permission',
            'reports',
            'survey-reports',
        ];

        $actions = ['view', 'create', 'update', 'delete'];

        foreach ($entities as $entity) {
            foreach ($actions as $action) {
                Permission::firstOrCreate([
                    'name' => "{$entity}.{$action}",
                    'guard_name' => $guardName,
                ]);
            }
        }

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => $guardName]);
        $deptHead = Role::firstOrCreate(['name' => 'dept_head', 'guard_name' => $guardName]);
        $divHead = Role::firstOrCreate(['name' => 'div_head', 'guard_name' => $guardName]);
        $hrStaff = Role::firstOrCreate(['name' => 'hr_staff', 'guard_name' => $guardName]);
        $employee = Role::firstOrCreate(['name' => 'employee', 'guard_name' => $guardName]);

        $admin->syncPermissions(Permission::where('guard_name', $guardName)->get());

        $deptHead->syncPermissions([
            'ticket.view',
            'ticket.create',
            'ticket.update',
            'issue.view',
            'issue.create',
            'issue.update',
            'department.view',
            'division.view',
        ]);

        $divHead->syncPermissions([
            'ticket.view',
            'ticket.create',
            'ticket.update',
            'issue.view',
            'issue.create',
            'issue.update',
        ]);

        $hrStaff->syncPermissions([
            'ticket.view',
            'ticket.create',
            'ticket.update',
        ]);

        $employee->syncPermissions([
            'ticket.view',
            'ticket.create',
            'ticket.update',
        ]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
