<?php

namespace App\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use App\Models\Division;

/**
 * Class UserCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class UserCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    public function setup()
    {
        CRUD::setModel(\App\Models\User::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/user');
        CRUD::setEntityNameStrings('user', 'users');

        if (!backpack_user()->can('user.view')) {
            abort(403);
        }

        $this->crud->denyAccess(['create', 'update', 'delete']);

        if (backpack_user()->can('user.create')) {
            $this->crud->allowAccess('create');
        }
        if (backpack_user()->can('user.update')) {
            $this->crud->allowAccess('update');
        }
        if (backpack_user()->can('user.delete')) {
            $this->crud->allowAccess('delete');
        }
    }

    protected function setupListOperation()
    {
        CRUD::column('name');
        CRUD::column('department');
        CRUD::column('division');

        $this->crud->addColumn([
            'label'     => 'Role',
            'type'      => 'select_multiple',
            'name'      => 'roles',
            'entity'    => 'roles',
            'attribute' => 'name',
            'model'     => "Backpack\PermissionManager\app\Models\Role",
        ]);
        $this->crud->addColumn([
            'name'  => 'is_active',
            'label' => 'Active',
            'type'  => 'boolean',
            'options' => [0 => 'Inactive', 1 => 'Active'],
            'wrapper' => [
                'element' => 'span',
                'class' => function ($crud, $column, $entry) {
                    return $entry->is_active
                        ? 'badge badge-success'
                        : 'badge badge-secondary';
                },
            ],
        ]);
    }

    protected function setupCreateOperation()
    {
        CRUD::field('name')->validationRules('required');
        CRUD::field('email')->validationRules('required|email|unique:users,email');
        CRUD::field('password')->validationRules('required');

        $this->addDynamicDropdownFields();

        $this->crud->addField([
            'label'             => "Roles",
            'type'              => 'checklist',
            'name'              => 'roles',
            'entity'            => 'roles',
            'attribute'         => 'name',
            'model'             => "Backpack\PermissionManager\app\Models\Role",
            'pivot'             => true,
            'number_columns'    => 3,
        ]);

        CRUD::addField([
            'name'     => 'is_active',
            'label'    => 'Inactive/Active',
            'type'     => 'switch',
            'color'    => 'primary',
            'onLabel'  => '✓',
            'offLabel' => '✕',
            'default'  => 1,
        ]);

        \App\Models\User::creating(function ($entry) {
            $entry->password = \Hash::make($entry->password);
        });
    }

    protected function setupUpdateOperation()
    {
        CRUD::field('name')->validationRules('required');
        CRUD::field('email')->validationRules('required|email|unique:users,email,'.CRUD::getCurrentEntryId());
        CRUD::field('password')->hint('Type a password to change it.');

        \App\Models\User::updating(function ($entry) {
            if (request('password') == null) {
                $entry->password = $entry->getOriginal('password');
            } else {
                $entry->password = \Hash::make(request('password'));
            }
        });

        $this->addDynamicDropdownFields();

        $this->crud->addField([
            'label'             => "Roles",
            'type'              => 'checklist',
            'name'              => 'roles',
            'entity'            => 'roles',
            'attribute'         => 'name',
            'model'             => "Backpack\PermissionManager\app\Models\Role",
            'pivot'             => true,
            'number_columns'    => 3,
        ]);

        CRUD::addField([
            'name'     => 'is_active',
            'label'    => 'Inactive/Active',
            'type'     => 'switch',
            'color'    => 'primary',
            'onLabel'  => '✓',
            'offLabel' => '✕',
            'default'  => 1,
        ]);
    }

    /**
     * Custom method to inject the Department/Division fields and the JS Script
     * Keeping it here prevents duplicating code in Create and Update operations.
     */
    protected function addDynamicDropdownFields()
    {
        CRUD::addField([
            'label'      => "Department",
            'type'       => 'select',
            'name'       => 'department_id',
            'entity'     => 'department',
            'model'      => "App\Models\Department",
            'attribute'  => 'department_name',
            'attributes' => [
                'id' => 'department_dropdown'
            ]
        ]);

        CRUD::addField([
            'label'      => "Division",
            'type'       => 'select',
            'name'       => 'division_id',
            'entity'     => 'division',
            'model'      => "App\Models\Division",
            'attribute'  => 'division_name',
            'attributes' => [
                'id'       => 'division_dropdown',
                'disabled' => 'disabled', // Automatically locked on page load
            ]
        ]);

        // Get all divisions as a JSON string to use directly in JavaScript
        $divisionsJson = Division::all(['id', 'department_id', 'division_name'])->toJson();

        CRUD::addField([
            'name'  => 'custom_toggle_script',
            'type'  => 'custom_html',
            'value' => "
                <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        const divisions = {$divisionsJson};
                        const deptSelect = document.getElementById('department_dropdown');
                        const divSelect = document.getElementById('division_dropdown');

                        if (!deptSelect || !divSelect) return;

                        // Function to filter and populate the Division dropdown
                        function populateDivisions(deptId, preselectedDivId = null) {
                            divSelect.innerHTML = '<option value=\"\">- Select Division -</option>';

                            if (deptId) {
                                divSelect.disabled = false; // Unlock field

                                const filtered = divisions.filter(d => d.department_id == deptId);

                                filtered.forEach(div => {
                                    let option = document.createElement('option');
                                    option.value = div.id;
                                    option.text = div.division_name;

                                    // Keep the existing selection if we are on the Edit/Update page
                                    if (preselectedDivId == div.id) {
                                        option.selected = true;
                                    }

                                    divSelect.appendChild(option);
                                });
                            } else {
                                divSelect.disabled = true; // Lock field if no department
                            }
                        }

                        // Listen for User changing the Department
                        deptSelect.addEventListener('change', function(e) {
                            populateDivisions(this.value);
                        });

                        // INITIAL LOAD logic (Crucial for the Update screen so existing data shows)
                        if (deptSelect.value) {
                            populateDivisions(deptSelect.value, divSelect.value);
                        } else {
                            divSelect.disabled = true;
                        }
                    });
                </script>
            ",
        ]);
    }
}
