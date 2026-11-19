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
        $statuses = [
            ['status_code' => Status::RESOLVED, 'status_name' => 'Resolved', 'status_color' => '#42ffc6'],
            ['status_code' => Status::PENDING, 'status_name' => 'Pending', 'status_color' => '#fed971'],
            ['status_code' => Status::UNASSIGNED, 'status_name' => 'Unassigned', 'status_color' => '#c2c2c2'],
            ['status_code' => Status::REOPENED, 'status_name' => 'Reopened', 'status_color' => '#5cb0ff'],
        ];

        foreach ($statuses as $status) {
            Status::firstOrCreate(
                ['status_code' => $status['status_code']],
                $status
            );
        }
    }
}
