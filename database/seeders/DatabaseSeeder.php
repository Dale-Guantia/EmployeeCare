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
        $this->call([
            RolesAndPermissionsSeeder::class,
            DepartmentTableSeeder::class,
            DivisionTableSeeder::class,
            UsersTableSeeder::class,
            PriorityTableSeeder::class,
            StatusTableSeeder::class,
            IssueTableSeeder::class,
            // TicketTableSeeder::class, // Optional test data only. Enable if needed.
        ]);
    }
}
