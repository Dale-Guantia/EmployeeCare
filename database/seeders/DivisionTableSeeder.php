<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Department;
use App\Models\Division;

class DivisionTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $department = Department::firstOrCreate([
            'department_name' => 'City Human Resource Development Office',
        ]);

        $divisions = [
            'Department Head',
            'Information Technology',
            'Administrative',
            'Payroll',
            'Records',
            'Claims and Benefits',
            'RSP',
            'Learning and Development',
            'Performance Management',
        ];

        foreach ($divisions as $divisionName) {
            Division::updateOrCreate(
                [
                    'division_name' => $divisionName,
                    'department_id' => $department->id,
                ],
                [
                    'division_name' => $divisionName,
                    'department_id' => $department->id,
                ]
            );
        }
    }
}
