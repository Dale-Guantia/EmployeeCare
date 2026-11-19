<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePermissionEditorTest extends TestCase
{
    public function test_role_form_keeps_assignment_lifecycle_and_custom_permissions_on_save(): void
    {
        Http::fake(['*' => Http::response('/* isolated asset */')]);
        $this->actingAs(User::role('admin')->firstOrFail(), 'web');
        $role = Role::create(['name' => 'Synthetic permission editor', 'guard_name' => 'web']);
        $custom = Permission::create(['name' => 'ticket.review', 'guard_name' => 'web']);
        $permissions = Permission::whereIn('name', ['ticket.view', 'ticket.assign', 'department.archive', 'department.reactivate', $custom->name])->get();
        $role->syncPermissions($permissions);
        $response = $this->get(backpack_url("role/{$role->id}/edit"))->assertOk();
        $document = new \DOMDocument();
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        $xpath = new \DOMXPath($document);
        $checked = [];
        foreach ($xpath->query('//input[@name="permissions[]" and @checked]') as $input) {
            $checked[] = (int) $input->getAttribute('value');
        }

        $this->assertEqualsCanonicalizing($permissions->pluck('id')->all(), $checked);
        $this->put(backpack_url("role/{$role->id}"), ['id' => $role->id, 'name' => $role->name, 'permissions' => $checked])->assertRedirect();
        $this->assertEqualsCanonicalizing($permissions->pluck('id')->all(), $role->fresh()->permissions->pluck('id')->all());
    }
}
