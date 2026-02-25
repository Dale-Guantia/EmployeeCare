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
        Priority::create([
            'priority_name' => 'High',
            'priority_color' => '#fe7c7c',
        ]);
        Priority::create([
            'priority_name' => 'Medium',
            'priority_color' => '#fed971',
        ]);
        Priority::create([
            'priority_name' => 'Low',
            'priority_color' => '#5cb0ff',
        ]);
    }
}
