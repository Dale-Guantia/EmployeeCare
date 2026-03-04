<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\IssueRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Class IssueCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class IssueCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    /**
     * Configure the CrudPanel object. Apply settings to all operations.
     *
     * @return void
     */
    public function setup()
    {
        CRUD::setModel(\App\Models\Issue::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/issue');
        CRUD::setEntityNameStrings('issue', 'issues');

        if (!backpack_user()->can('issue.view')) {
            abort(403);
        }

        // $this->crud->denyAccess(['create','update','delete']);

        // if (backpack_user()->can('ticket.create')) {
        //     $this->crud->allowAccess('create');
        // }

        // if (backpack_user()->can('ticket.update')) {
        //     $this->crud->allowAccess('update');
        // }

        // if (backpack_user()->can('ticket.delete')) {
        //     $this->crud->allowAccess('delete');
        // }
    }

    /**
     * Define what happens when the List operation is loaded.
     *
     * @see  https://backpackforlaravel.com/docs/crud-operation-list-entries
     * @return void
     */
    protected function setupListOperation()
    {
        CRUD::addColumn([
            'label'     => "Department",
            'name'      => 'department_id',
            'entity'    => 'department',
            'attribute' => 'department_name',
            'model'     => "App\Models\Department",
        ]);
        CRUD::addColumn([
            'label'     => "Division",
            'name'      => 'division_id',
            'entity'    => 'division',
            'attribute' => 'division_name',
            'model'     => "App\Models\Division",
        ]);
        CRUD::addColumn([
            'name'      => 'priority_id',
            'label'     => 'Priority',
            'type'      => 'select',
            'entity'    => 'priority',
            'attribute' => 'priority_name',
            'model'     => "App\Models\Priority",
            'wrapper'   => [
                'element' => 'span',
                'class'   => 'badge',
                'style'   => function ($crud, $column, $entry, $related_key) {
                    $color = $entry->priority ? $entry->priority->priority_color : '#c2c2c2';
                    return "background-color: {$color}; color: #000; padding: 5px 10px; border-radius: 4px; font-weight: bold;";
                },
            ],
        ]);
        CRUD::column('issue_description');

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
        CRUD::setValidation(IssueRequest::class);

        CRUD::field('department_id');
        CRUD::field('division_id');
        CRUD::field('priority_id');
        CRUD::field('issue_description');
        CRUD::field('icon');

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
        // This allows the preview to show the same columns as the list view
        $this->setupListOperation();
    }
}
