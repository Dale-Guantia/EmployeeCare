<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\DepartmentRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Class DepartmentCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class DepartmentCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation { destroy as protected traitDestroy; }
    use \App\Http\Controllers\Admin\Operations\OfficeArchivingOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    /**
     * Configure the CrudPanel object. Apply settings to all operations.
     *
     * @return void
     */
    public function setup()
    {
        CRUD::setModel(\App\Models\Department::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/department');
        CRUD::setEntityNameStrings('department', 'departments');

        if (!backpack_user()->can('department.view')) {
            abort(403);
        }

        $this->crud->denyAccess(['create', 'update', 'delete']);

        if (backpack_user()->can('department.create')) {
            $this->crud->allowAccess('create');
        }
        if (backpack_user()->can('department.update')) {
            $this->crud->allowAccess('update');
        }
        if (backpack_user()->can('department.delete')) {
            $this->crud->allowAccess('delete');
        }
    }

    /**
     * Define what happens when the List operation is loaded.
     *
     * @see  https://backpackforlaravel.com/docs/crud-operation-list-entries
     * @return void
     */
    protected function setupListOperation()
    {
        $this->crud->removeButton('delete');
        $this->crud->addButtonFromView('line', 'master_data_delete', 'master_data_delete', 'end');
        $this->setupOfficeArchivingButtons();
        CRUD::addColumn(['name' => 'is_active', 'label' => 'State', 'type' => 'boolean', 'options' => [0 => 'Archived', 1 => 'Active']]);
        CRUD::column('department_name');
        // CRUD::column('department_code')->label('Code');

        /**
         * Columns can be defined using the fluent syntax or array syntax:
         * - CRUD::column('price')->type('number');
         * - CRUD::addColumn(['name' => 'price', 'type' => 'number']);
         */
    }

    /**
     * Define what happens when the Create operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-create
     * @return void
     */
    protected function setupCreateOperation()
    {
        CRUD::setValidation(DepartmentRequest::class);
        $codeAttributes = ['maxlength' => 64];
        if ($this->crud->getCurrentOperation() === 'update') $codeAttributes['readonly'] = 'readonly';
        CRUD::addField(['name' => 'department_code', 'label' => 'Permanent code', 'type' => 'text',
            'attributes' => $codeAttributes, 'hint' => 'Assigned once. Leave empty on creation to generate a code automatically.']);
        if ($this->crud->getCurrentOperation() === 'update') {
            CRUD::addField(['name' => 'reason', 'label' => 'Reason for change (optional)', 'type' => 'textarea',
                'attributes' => ['maxlength' => 1000, 'rows' => 2], 'hint' => 'Stored in the audit history.']);
        }

        CRUD::field('department_name');
        CRUD::addField(['name' => 'is_active', 'label' => 'Active', 'type' => 'checkbox', 'default' => 1,
            'hint' => 'Retirement requires moving active users, resolving or transferring open tickets, and completing pending transfers.']);

        /**
         * Fields can be defined using the fluent syntax or array syntax:
         * - CRUD::field('price')->type('number');
         * - CRUD::addField(['name' => 'price', 'type' => 'number']));
         */
    }

    /**
     * Define what happens when the Update operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-update
     * @return void
     */
    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
    }

    protected function setupShowOperation()
    {
        $this->setupListOperation();
    }

    public function destroy($id)
    {
        return $this->destroyOffice($id);
    }
}
