<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Ticket;

class TicketTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Ticket::create([
            'user_id' => 1,
            'reference_id' => '0001-052225',
            'department_id' => 1,
            'office_id' => 3,
            'problem_category_id' => 1,
            'priority_id' => 1,
            'status_id' => 3,
            'description' => 'Test message',
        ]);
        Ticket::create([
            'user_id' => 4,
            'reference_id' => '0002-052225',
            'department_id' => 1,
            'office_id' => 3,
            'problem_category_id' => 2,
            'priority_id' => 2,
            'status_id' => 3,
            'description' => 'Test message 2',
        ]);
    }
}
