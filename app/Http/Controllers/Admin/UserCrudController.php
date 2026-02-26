<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\UserRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

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
     }

    protected function setupListOperation()
    {
        CRUD::column('name');
        CRUD::column('email');

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
        CRUD::field('name')->validationRules('required|min:5');
        CRUD::field('email')->validationRules('required|email|unique:users,email');
        CRUD::field('password')->validationRules('required');

        \App\Models\User::creating(function ($entry) {
            $entry->password = \Hash::make($entry->password);
        });

        $this->crud->addField([
            'label'             => "Roles",
            'type'              => 'checklist',
            'name'              => 'roles',
            'entity'            => 'roles',
            'attribute'         => 'name',
            'model'             => "Backpack\PermissionManager\app\Models\Role",
            'pivot'             => true,
            'number_columns'    => 3, // Optional: makes it look cleaner
        ]);

        // The Active Switch
        CRUD::addField([
            'name'     => 'is_active',
            'label'    => 'Inactive/Active',
            'type'     => 'switch',
            'color'    => 'primary', // The color of the switch when ON
            'onLabel'  => '✓',      // Text/Icon inside the switch when ON
            'offLabel' => '✕',      // Text/Icon inside the switch when OFF
            'default'  => 1,        // Set as Active by default for new users
        ]);
    }

    protected function setupUpdateOperation()
    {
        CRUD::field('name')->validationRules('required|min:5');
        CRUD::field('email')->validationRules('required|email|unique:users,email,'.CRUD::getCurrentEntryId());
        CRUD::field('password')->hint('Type a password to change it.');

        \App\Models\User::updating(function ($entry) {
            if (request('password') == null) {
                $entry->password = $entry->getOriginal('password');
            } else {
                $entry->password = \Hash::make(request('password'));
            }
        });

        $this->crud->addField([
            'label'             => "Roles",
            'type'              => 'checklist',
            'name'              => 'roles',
            'entity'            => 'roles',
            'attribute'         => 'name',
            'model'             => "Backpack\PermissionManager\app\Models\Role",
            'pivot'             => true,
            'number_columns'    => 3, // Optional: makes it look cleaner
        ]);

        $this->crud->addField([
            'name'     => 'is_active',
            'label'    => 'Inactive/Active',
            'type'     => 'switch',
            'color'    => 'primary', // The color of the switch when ON
            'onLabel'  => '✓',      // Text/Icon inside the switch when ON
            'offLabel' => '✕',      // Text/Icon inside the switch when OFF
            'default'  => 1,        // Set as Active by default for new users
        ]);
    }
}
