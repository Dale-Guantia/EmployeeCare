<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Priority;

class PriorityTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $priorities = [
            ['priority_code' => Priority::HIGH, 'priority_name' => 'High', 'priority_color' => '#fe7c7c'],
            ['priority_code' => Priority::MEDIUM, 'priority_name' => 'Medium', 'priority_color' => '#fed971'],
            ['priority_code' => Priority::LOW, 'priority_name' => 'Low', 'priority_color' => '#5cb0ff'],
        ];

        foreach ($priorities as $priority) {
            Priority::firstOrCreate(
                ['priority_code' => $priority['priority_code']],
                $priority
            );
        }
    }
}
