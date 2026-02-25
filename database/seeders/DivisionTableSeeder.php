<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Division;

class DivisionTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Division::create([
            'division_name' => 'Department Head',
            'department_id' => 1,
        ]);
        Division::create([
            'division_name' => 'Information Technology',
            'department_id' => 1,
        ]);
        Division::create([
            'division_name' => 'Administrative',
            'department_id' => 1,
        ]);
        Division::create([
            'division_name' => 'Payroll',
            'department_id' => 1,
        ]);
        Division::create([
            'division_name' => 'Records',
            'department_id' => 1,
        ]);
        Division::create([
            'division_name' => 'Claims and Benefits',
            'department_id' => 1,
        ]);
        Division::create([
            'division_name' => 'RSP',
            'department_id' => 1,
        ]);
        Division::create([
            'division_name' => 'Learning and Development',
            'department_id' => 1,
        ]);
        Division::create([
            'division_name' => 'Performance Management',
            'department_id' => 1,
        ]);
    }
}
