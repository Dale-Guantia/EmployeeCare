<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\StatusRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Class StatusCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class StatusCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
    use \App\Http\Controllers\Admin\Operations\MasterDataArchivingOperation;

    /**
     * Configure the CrudPanel object. Apply settings to all operations.
     *
     * @return void
     */
    public function setup()
    {
        CRUD::setModel(\App\Models\Status::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/status');
        CRUD::setEntityNameStrings('status', 'statuses');

        if (!backpack_user()->can('status.view')) {
            abort(403);
        }

        $this->crud->denyAccess(['create', 'update', 'delete']);

        if (backpack_user()->can('status.create')) {
            $this->crud->allowAccess('create');
        }
        if (backpack_user()->can('status.update')) {
            $this->crud->allowAccess('update');
        }
        if (backpack_user()->can('status.delete')) {
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
        CRUD::addColumn(['name' => 'is_active', 'label' => 'State', 'type' => 'boolean', 'options' => [0 => 'Archived', 1 => 'Active']]);
        $this->crud->addButtonFromView('line', 'status_archive', 'status_archive', 'end');
        CRUD::column('status_name');
        // CRUD::column('status_code')->label('Code');
        CRUD::addColumn([
            'name'  => 'status_color',
            'label' => 'Status Color',
            'type'  => 'text',
            'wrapper' => [
                'element' => 'span',
                'class'   => function ($crud, $column, $entry, $related_key) {
                    return 'badge'; // Adds a rounded pill look
                },
                'style' => function ($crud, $column, $entry, $related_key) {
                    // This injects the actual hex code from your DB into the CSS
                    return 'background-color: '.$entry->status_color.'; color: black; padding: 5px 10px;';
                },
            ],
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
        CRUD::setValidation(StatusRequest::class);
        $codeAttributes = ['maxlength' => 64];
        if ($this->crud->getCurrentOperation() === 'update') $codeAttributes['readonly'] = 'readonly';
        CRUD::addField(['name' => 'status_code', 'label' => 'Permanent code', 'type' => 'text',
            'attributes' => $codeAttributes, 'hint' => 'Assigned once. Leave empty on creation to generate a code automatically.']);
        if ($this->crud->getCurrentOperation() === 'update') {
            CRUD::addField(['name' => 'reason', 'label' => 'Reason for change (optional)', 'type' => 'textarea',
                'attributes' => ['maxlength' => 1000, 'rows' => 2], 'hint' => 'Stored in the audit history.']);
        }

        CRUD::field('status_name')->hint('The display name may be changed. The permanent code determines workflow behavior.');
        CRUD::addField([   // Color
            'name'    => 'status_color',
            'label'   => 'Status color',
            'type'    => 'color',
            'default' => '#000000',
        ]);

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
}
