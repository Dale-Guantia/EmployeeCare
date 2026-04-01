<?php

namespace App\Http\Controllers\Admin;

use Backpack\PermissionManager\app\Http\Controllers\RoleCrudController as VendorRoleCrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Spatie\Permission\Models\Permission;

class RoleCrudController extends VendorRoleCrudController
{
    use CreateOperation { store as traitStore; }
    use UpdateOperation { update as traitUpdate; }

    public function setup()
    {
        parent::setup();

        if (!backpack_user()->can('role.view')) {
            abort(403);
        }
    }

    public function setupCreateOperation()
    {
        $this->crud->removeAllFields();

        $this->crud->setValidation(\Backpack\PermissionManager\app\Http\Requests\RoleStoreCrudRequest::class);

        $this->crud->addField([
            'name'  => 'name',
            'label' => trans('backpack::permissionmanager.name'),
            'type'  => 'text',
        ]);

        $this->crud->addField([
            'name'  => 'grouped_permissions',
            'type'  => 'custom_html',
            'value' => $this->renderGroupedPermissions(),
        ]);

        \Cache::forget('spatie.permission.cache');
    }

    public function setupUpdateOperation()
    {
        $this->crud->removeAllFields();

        $this->crud->setValidation(\Backpack\PermissionManager\app\Http\Requests\RoleUpdateCrudRequest::class);

        $this->crud->addField([
            'name'  => 'name',
            'label' => trans('backpack::permissionmanager.name'),
            'type'  => 'text',
        ]);

        $this->crud->addField([
            'name'  => 'grouped_permissions',
            'type'  => 'custom_html',
            'value' => $this->renderGroupedPermissions($this->crud->getCurrentEntry()),
        ]);

        \Cache::forget('spatie.permission.cache');
    }

    public function store()
    {
        $response = $this->traitStore();
        $this->syncPermissionsFromRequest();
        return $response;
    }

    public function update()
    {
        $response = $this->traitUpdate();
        $this->syncPermissionsFromRequest();
        return $response;
    }

    protected function syncPermissionsFromRequest()
    {
        $role = $this->crud->getCurrentEntry();
        $permissionIds = request()->input('permissions', []);

        $permissions = Permission::whereIn('id', $permissionIds)->get();
        $role->syncPermissions($permissions);

        \Cache::forget('spatie.permission.cache');
    }

    protected function renderGroupedPermissions($role = null)
    {
        $permissions = Permission::all()->groupBy(function ($permission) {
            return explode('.', $permission->name)[0];
        });

        $selectedPermissions = $role
            ? $role->permissions->pluck('id')->toArray()
            : old('permissions', []);

        $html = '<div class="mb-0">';
        $html .= '<label>Permissions</label>';

        foreach ($permissions as $group => $groupPermissions) {
            $html .= '<div class="card mb-3">';
            $html .= '<div class="card-header font-weight-bold text-capitalize">' . e(str_replace('_', ' ', $group)) . '</div>';
            $html .= '<div class="card-body">';
            $html .= '<div class="row">';

            $ordered = collect(['view', 'create', 'update', 'delete'])->map(function ($action) use ($groupPermissions, $group) {
                return $groupPermissions->firstWhere('name', $group . '.' . $action);
            })->filter();

            foreach ($ordered as $permission) {
                $checked = in_array($permission->id, $selectedPermissions) ? 'checked' : '';

                $html .= '
                    <div class="col-md-3 mb-2">
                        <div class="form-check">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                name="permissions[]"
                                id="permission_' . $permission->id . '"
                                value="' . $permission->id . '"
                                ' . $checked . '
                            >
                            <label class="form-check-label" for="permission_' . $permission->id . '">
                                ' . e(explode('.', $permission->name)[1] ?? $permission->name) . '
                            </label>
                        </div>
                    </div>
                ';
            }

            $html .= '</div>';
            $html .= '</div>';
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }
}
