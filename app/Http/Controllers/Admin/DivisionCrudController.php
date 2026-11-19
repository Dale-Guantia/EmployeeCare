<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\DivisionRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Class DivisionCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class DivisionCrudController extends CrudController
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
        CRUD::setModel(\App\Models\Division::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/division');
        CRUD::setEntityNameStrings('division', 'divisions');

        if (!backpack_user()->can('division.view')) {
            abort(403);
        }

        $this->crud->denyAccess(['create', 'update', 'delete']);

        if (backpack_user()->can('division.create')) {
            $this->crud->allowAccess('create');
        }
        if (backpack_user()->can('division.update')) {
            $this->crud->allowAccess('update');
        }
        if (backpack_user()->can('division.delete')) {
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
        CRUD::addColumn(['name' => 'availability', 'label' => 'State', 'type' => 'closure',
            'function' => fn ($entry) => !$entry->is_active ? 'Archived' : ($entry->department?->is_active ? 'Active' : 'Department archived')]);
        CRUD::column('division_name');
        // CRUD::column('division_code')->label('Code');
        CRUD::addColumn([
            'label'     => "Department",
            'type'      => 'select',
            'name'      => 'department_id',
            'entity'    => 'department',
            'attribute' => 'department_name',
            'model'     => "App\Models\Department",
        ]);

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
        CRUD::setValidation(DivisionRequest::class);
        $codeAttributes = ['maxlength' => 64];
        if ($this->crud->getCurrentOperation() === 'update') $codeAttributes['readonly'] = 'readonly';
        CRUD::addField(['name' => 'division_code', 'label' => 'Permanent code', 'type' => 'text',
            'attributes' => $codeAttributes, 'hint' => 'Assigned once. Leave empty on creation to generate a code automatically.']);
        if ($this->crud->getCurrentOperation() === 'update') {
            CRUD::addField(['name' => 'reason', 'label' => 'Reason for change (optional)', 'type' => 'textarea',
                'attributes' => ['maxlength' => 1000, 'rows' => 2], 'hint' => 'Stored in the audit history.']);
        }
        CRUD::addField([
            'label'     => "Department",
            'type'      => 'select', // or 'select2' for a searchable dropdown
            'name'      => 'department_id', // The foreign key in your database
            'entity'    => 'department',    // The method name in your Division Model
            'model'     => "App\Models\Department", // The related Model
            'attribute' => 'department_name',          // The column to show in the dropdown
            'options' => function ($query) {
                $entry = $this->crud->getCurrentOperation() === 'update' ? $this->crud->getCurrentEntry() : null;
                return \App\Services\OfficeSelectionRules::departments($query, $entry?->department_id)->get();
            },
        ]);
        CRUD::field('division_name');
        CRUD::addField(['name' => 'is_active', 'label' => 'Active', 'type' => 'checkbox', 'default' => 1,
            'hint' => 'An archived parent department also makes this division unavailable for new selections.']);


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
