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
            ['priority_name' => 'High', 'priority_color' => '#fe7c7c'],
            ['priority_name' => 'Medium', 'priority_color' => '#fed971'],
            ['priority_name' => 'Low', 'priority_color' => '#5cb0ff'],
        ];

        foreach ($priorities as $priority) {
            Priority::updateOrCreate(
                ['priority_name' => $priority['priority_name']],
                $priority
            );
        }
    }
}
