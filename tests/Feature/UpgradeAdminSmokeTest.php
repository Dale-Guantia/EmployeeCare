<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class UpgradeAdminSmokeTest extends TestCase
{
    public function test_admin_lists_and_datatable_endpoints_render_after_the_upgrade(): void
    {
        $this->actingAs(User::role('admin')->firstOrFail(), 'web');
        foreach (['ticket', 'issue', 'department', 'division', 'priority', 'status', 'user', 'role', 'permission', 'hr-policy-documents'] as $entity) {
            $this->get(backpack_url($entity))->assertOk();
            $this->postJson(backpack_url($entity . '/search'), ['start' => 0, 'length' => 10, 'draw' => 1])
                ->assertOk()->assertJsonStructure(['data', 'recordsTotal', 'recordsFiltered']);
        }
    }

    public function test_admin_create_forms_render_after_the_upgrade(): void
    {
        $this->actingAs(User::role('admin')->firstOrFail(), 'web');
        foreach (['ticket', 'issue', 'department', 'division', 'priority', 'status', 'user', 'role', 'permission'] as $entity) {
            $this->get(backpack_url($entity . '/create'))->assertOk();
        }
        $this->get(route('hr.policy.upload.form'))->assertOk();
    }

    public function test_admin_layout_loads_the_current_theme_stylesheet(): void
    {
        $this->actingAs(User::role('admin')->firstOrFail(), 'web');
        $this->get(backpack_url('ticket'))->assertOk()
            ->assertSee('coreui4.css', false)
            ->assertDontSee('coreuiv2.css', false)
            ->assertSee('nav-group-toggle', false)
            ->assertSee('data-bs-toggle="dropdown"', false);
    }
}
