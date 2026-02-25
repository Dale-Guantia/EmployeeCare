<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Status;

class StatusTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Status::create([
            'status_name' => 'Resolved',
            'status_color' => '#42ffc6',
        ]);
        Status::create([
            'status_name' => 'Pending',
            'status_color' => '#fed971',
        ]);
        Status::create([
            'status_name' => 'Unassigned',
            'status_color' => '#c2c2c2',
        ]);
        Status::create([
            'status_name' => 'Reopened',
            'status_color' => '#5cb0ff',
        ]);
    }
}
