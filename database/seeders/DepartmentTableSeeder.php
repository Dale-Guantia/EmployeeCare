<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Department;

class DepartmentTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Department::updateOrCreate(
            ['department_name' => 'City Human Resource Development Office'],
            ['department_name' => 'City Human Resource Development Office']
        );
    }
}
