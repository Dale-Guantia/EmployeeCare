<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CrudRequestValidationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_department_create_rejects_empty_name()
    {
        $admin = User::role('admin')->first();
        $this->assertNotNull($admin);
        $this->actingAs($admin, 'web');

        $response = $this->post(route('department.store'), ['department_name' => '']);

        $response->assertSessionHasErrors('department_name');
    }

    public function test_department_create_accepts_a_real_name()
    {
        $admin = User::role('admin')->first();
        $this->assertNotNull($admin);
        $this->actingAs($admin, 'web');

        $response = $this->post(route('department.store'), ['department_name' => 'Real Dept ' . uniqid()]);

        $response->assertSessionDoesntHaveErrors('department_name');
    }
}
