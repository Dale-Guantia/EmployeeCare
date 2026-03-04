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
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        /*
        |--------------------------------------------------------------------------
        | 1. Create CRUD Permissions
        |--------------------------------------------------------------------------
        */

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
            'report',
            'survey_response'
        ];

        $actions = ['view', 'create', 'update', 'delete'];

        foreach ($entities as $entity) {
            foreach ($actions as $action) {
                Permission::firstOrCreate([
                    'name' => "{$entity}.{$action}"
                ]);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 2. Create Roles
        |--------------------------------------------------------------------------
        */

        $admin = Role::firstOrCreate(['name' => 'admin']);
        $deptHead = Role::firstOrCreate(['name' => 'dept_head']);
        $divHead = Role::firstOrCreate(['name' => 'div_head']);
        $hrStaff = Role::firstOrCreate(['name' => 'hr_staff']);
        $employee = Role::firstOrCreate(['name' => 'employee']);

        /*
        |--------------------------------------------------------------------------
        | 3. Assign Permissions
        |--------------------------------------------------------------------------
        */

        // ADMIN → Everything
        $admin->givePermissionTo(Permission::all());


        // DEPARTMENT HEAD
        $deptHead->givePermissionTo([
            // Tickets
            'ticket.view',
            'ticket.create',
            'ticket.update',

            // Issues
            'issue.view',
            'issue.create',
            'issue.update',

            // Departments & Divisions
            'department.view',
            'division.view',
        ]);


        // DIVISION HEAD (same as dept_head but customizable later)
        $divHead->givePermissionTo([
            'ticket.view',
            'ticket.create',
            'ticket.update',

            'issue.view',
            'issue.create',
            'issue.update',
        ]);


        // HR STAFF
        $hrStaff->givePermissionTo([
            'ticket.view',
            'ticket.create',
            'ticket.update',
        ]);


        // EMPLOYEE
        $employee->givePermissionTo([
            'ticket.view',
            'ticket.create',
            'ticket.update',
        ]);
    }
}
