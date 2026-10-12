<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $seeders = [
            RolesAndPermissionsSeeder::class,
            DepartmentTableSeeder::class,
            DivisionTableSeeder::class,
        ];
        if (app()->environment(['local', 'testing'])) {
            $seeders[] = UsersTableSeeder::class;
        }

        $this->call(array_merge($seeders, [
            PriorityTableSeeder::class,
            StatusTableSeeder::class,
            IssueTableSeeder::class,
            // TicketTableSeeder::class, // Optional test data only. Enable if needed.
        ]));
    }
}
