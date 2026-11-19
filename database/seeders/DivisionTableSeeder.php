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
        $department = Department::where('department_code', Department::HRDO)->firstOrFail();

        $divisions = [
            Division::DEPARTMENT_HEAD => 'Department Head',
            Division::INFORMATION_TECHNOLOGY => 'Information Technology',
            Division::ADMINISTRATIVE => 'Administrative',
            Division::PAYROLL => 'Payroll',
            Division::RECORDS => 'Records',
            Division::CLAIMS => 'Claims and Benefits',
            Division::RSP => 'RSP',
            Division::LEARNING_AND_DEVELOPMENT => 'Learning and Development',
            Division::PERFORMANCE_MANAGEMENT => 'Performance Management',
        ];

        foreach ($divisions as $code => $divisionName) {
            Division::firstOrCreate(
                ['division_code' => $code],
                [
                    'division_name' => $divisionName,
                    'department_id' => $department->id,
                ]
            );
        }
    }
}
