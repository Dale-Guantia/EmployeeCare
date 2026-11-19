<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\PriorityRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Class PriorityCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class PriorityCrudController extends CrudController
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
        CRUD::setModel(\App\Models\Priority::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/priority');
        CRUD::setEntityNameStrings('priority', 'priorities');

        if (!backpack_user()->can('priority.view')) {
            abort(403);
        }

        $this->crud->denyAccess(['create', 'update', 'delete']);

        if (backpack_user()->can('priority.create')) {
            $this->crud->allowAccess('create');
        }
        if (backpack_user()->can('priority.update')) {
            $this->crud->allowAccess('update');
        }
        if (backpack_user()->can('priority.delete')) {
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
        $this->crud->addButtonFromView('line', 'priority_actions', 'priority_actions', 'end');
        CRUD::column('priority_name');
        // CRUD::column('priority_code')->label('Code');
        CRUD::addColumn([
            'name' => 'is_active', 'label' => 'State', 'type' => 'boolean',
            'options' => [0 => 'Archived', 1 => 'Active'],
        ]);
        CRUD::addColumn([
            'name'  => 'priority_color',
            'label' => 'Priority Color',
            'type'  => 'text',
            'wrapper' => [
                'element' => 'span',
                'class'   => function ($crud, $column, $entry, $related_key) {
                    return 'badge'; // Adds a rounded pill look
                },
                'style' => function ($crud, $column, $entry, $related_key) {
                    // This injects the actual hex code from your DB into the CSS
                    return 'background-color: '.$entry->priority_color.'; color: black; padding: 5px 10px;';
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
        CRUD::setValidation(PriorityRequest::class);
        $codeAttributes = ['maxlength' => 64];
        if ($this->crud->getCurrentOperation() === 'update') $codeAttributes['readonly'] = 'readonly';
        CRUD::addField(['name' => 'priority_code', 'label' => 'Permanent code', 'type' => 'text',
            'attributes' => $codeAttributes, 'hint' => 'Assigned once. Leave empty on creation to generate a code automatically.']);
        if ($this->crud->getCurrentOperation() === 'update') {
            CRUD::addField(['name' => 'reason', 'label' => 'Reason for change (optional)', 'type' => 'textarea',
                'attributes' => ['maxlength' => 1000, 'rows' => 2], 'hint' => 'Stored in the audit history.']);
        }

        CRUD::field('priority_name');
        CRUD::addField([   // Color
            'name'    => 'priority_color',
            'label'   => 'Priority color',
            'type'    => 'color',
            'default' => '#000000',
        ]);
        CRUD::addField([
            'name' => 'is_active', 'label' => 'Active', 'type' => 'checkbox', 'default' => 1,
            'hint' => 'Archived priorities remain visible on existing records and cannot be newly assigned.',
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
