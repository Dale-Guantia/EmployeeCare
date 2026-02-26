<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\TicketRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Backpack\CRUD\app\Library\Widget;

/**
 * Class TicketCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class TicketCrudController extends CrudController
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
        CRUD::setModel(\App\Models\Ticket::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/ticket');
        CRUD::setEntityNameStrings('ticket', 'tickets');
    }

    protected function setupListOperation()
    {
        CRUD::column('reference_id')->label('Reference Id');
        CRUD::column('user_id')->label('Created by');
        CRUD::column('issue_id');
        CRUD::column('custom_issue');
        CRUD::column('priority_id');
        CRUD::addColumn([
            'name'      => 'status_id',
            'label'     => 'Status',
            'type'      => 'select',
            'entity'    => 'status',         // The relationship in your Ticket Model
            'attribute' => 'status_name',    // The column in the Statuses table to show
            'model'     => "App\Models\Status",
            'wrapper'   => [
                'element' => 'span',
                'class'   => 'badge',
                'style'   => function ($crud, $column, $entry, $related_key) {
                    // Access the color from the related Status model
                    $color = $entry->status ? $entry->status->status_color : '#c2c2c2';
                    return "background-color: {$color}; color: #000; padding: 5px 10px; border-radius: 4px; font-weight: bold;";
                },
            ],
        ]);
    }

    protected function setupCreateOperation()
    {
        CRUD::setValidation(TicketRequest::class);

        CRUD::addField([
            'label'       => "Select Issue/Problem",
            'type'        => 'select',
            'name'        => 'issue_id',
            'entity'      => 'issue',
            'allows_null' => true,
            'model'       => "App\Models\Issue",
            'attribute'   => 'issue_description',

            'wrapper' => [
                'class' => 'form-group col-md-12 issue-select-wrapper'
            ],
        ]);
        CRUD::addField([
            'name'    => 'custom_issue',
            'label'   => "Type your issue here",
            'type'    => 'text',
            'wrapper' => [
                'class' => 'form-group col-md-12 custom-issue-wrapper d-none'
            ],
        ]);
        CRUD::addField([
            'name'      => 'is_custom_issue',
            'label'     => 'My issue is not on the list',
            'type'      => 'checkbox',
            'default'   => 0,
        ]);
        CRUD::field('message');
        CRUD::addField([
            'name'      => 'attachments',
            'label'     => 'Attachments',
            'type'      => 'upload_multiple',
            'upload'    => true,
            'disk'      => 'public',
        ]);
        CRUD::field('department_id');
        CRUD::field('division_id');
        CRUD::field('priority_id');

        CRUD::addField([
            'name'  => 'custom_toggle_script',
            'type'  => 'custom_html',
            'value' => '
                <script>
                    document.addEventListener("DOMContentLoaded", function () {

                        function toggleIssueFields() {
                            let wrapper = document.querySelector("[bp-field-name=\'is_custom_issue\']");
                            if (!wrapper) return;

                            let checkbox = wrapper.querySelector("input[type=\'checkbox\']");
                            let issueSelect = document.querySelector(".issue-select-wrapper");
                            let customIssue = document.querySelector(".custom-issue-wrapper");

                            if (!checkbox || !issueSelect || !customIssue) return;

                            if (checkbox.checked) {
                                issueSelect.classList.add("d-none");
                                customIssue.classList.remove("d-none");

                                let select = document.querySelector("select[name=\'issue_id\']");
                                if (select) select.value = "";
                            } else {
                                issueSelect.classList.remove("d-none");
                                customIssue.classList.add("d-none");

                                let custom = document.querySelector("input[name=\'custom_issue\']");
                                if (custom) custom.value = "";
                            }
                        }
                        setTimeout(toggleIssueFields, 500);

                        document.addEventListener("change", function(e) {
                            if (e.target.closest("[bp-field-name=\'is_custom_issue\']")) {
                                toggleIssueFields();
                            }
                        });
                    });
                </script>
            ',
        ]);
    }

    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
    }

    protected function setupShowOperation()
    {
        // 1. Tell Backpack which columns to show (since setFromDb is false)
        CRUD::column('reference_id')->label('Reference Id');

        // Show names instead of IDs
        CRUD::column('user_id')->type('select')->entity('user')->attribute('name')->label('Created by');
        CRUD::column('issue_id')->type('select')->entity('issue')->attribute('issue_description')->label('Issue');

        CRUD::column('custom_issue');
        CRUD::column('message');

        // Show status name
        CRUD::column('status_id')->type('select')->entity('status')->attribute('status_name')->label('Status');

        CRUD::column('priority_id')->type('select')->entity('priority')->attribute('priority_name')->label('Priority');

        // 2. The Attachments Column
        CRUD::addColumn([
            'name'     => 'attachments',
            'label'    => 'Attachments',
            'type'     => 'closure',
            'function' => function($entry) {
                // Check if attachments exist and are an array
                if (!$entry->attachments || !is_array($entry->attachments)) {
                    return '-';
                }

                $output = '';
                foreach ($entry->attachments as $path) {
                    // Generate URL - This works because we switched the disk to 'public'
                    $url = asset('storage/' . $path);
                    $fileName = basename($path);
                    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

                    // Decide behavior based on file type
                    $viewable = ['jpg', 'jpeg', 'png', 'pdf'];
                    $isViewable = in_array($extension, $viewable);

                    $attributes = $isViewable
                        ? 'target="_blank"'
                        : 'download="' . $fileName . '"';

                    // Return a nice Bootstrap button
                    $output .= '<a href="'.$url.'" '.$attributes.' class="btn btn-sm btn-outline-primary mr-1 mb-1" style="text-transform: none;">';
                    $output .= '<i class="la la-paperclip"></i> ' . $fileName;
                    $output .= '</a> ';
                }

                return $output;
            },
            'escaped' => false,
        ]);

        // UI Tweaks
        $this->crud->setShowContentClass('col-md-10 offset-md-1');
        $this->crud->set('show.setFromDb', false);
    }
}
