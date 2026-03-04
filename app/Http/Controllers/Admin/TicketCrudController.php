<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\TicketRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Backpack\CRUD\app\Library\Widget;
use Prologue\Alerts\Facades\Alert;

/**
 * Class TicketCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class TicketCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation { update as traitUpdate; }
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

        if (!backpack_user()->can('ticket.view')) {
            abort(403);
        }

        $this->crud->denyAccess(['create','update','delete','show']);

        $user = backpack_user();

        // If Admin, don't add any filters
        if ($user->hasRole('admin')) {
            $this->crud->allowAccess(['create','update','delete','show']);
            return;
        }
        // Use a nested "Where" clause to handle "Owned by me" OR "Assigned to my scope"
        $this->crud->addClause('where', function($query) use ($user) {
            // 1. Always let everyone see their own created tickets
            $query->where('user_id', $user->id);
            // 2. Add scope-based visibility
            if ($user->hasRole('dept_head')) {
                $query->orWhere('department_id', $user->department_id);
                $this->crud->allowAccess(['create','update','delete','show']);
            }
            if ($user->hasRole('div_head')) {
                $query->orWhere('division_id', $user->division_id);
                $this->crud->allowAccess(['create','show']);
            }
            if ($user->hasRole('hr_staff')) {
                // HR Staff ONLY sees tickets assigned specifically to them
                $query->orWhere('assigned_to', $user->id);
                $this->crud->allowAccess(['create','show']);
            }
        });
    }

    protected function setupListOperation()
    {
        $user = backpack_user();

        // 1. If user is a Department Head, only show tickets for their department
        if ($user->hasRole('dept_head')) {
            $this->crud->addClause('where', 'department_id', $user->department_id);
        }
        // 2. If user is a Division Head, only show tickets for their division
        if ($user->hasRole('div_head')) {
            $this->crud->addClause('where', 'division_id', $user->division_id);
        }
        // // 3. If user is a regular Employee, only show tickets they created
        // if ($user->hasRole('hr_staff')) {
        //     $this->crud->addClause('where', 'user_id', $user->id);
        // };
        // 4. If user is a regular Employee, only show tickets they created
        if ($user->hasRole('employee')) {
            $this->crud->addClause('where', 'user_id', $user->id);
        };

        CRUD::column('reference_id')->label('Reference Id');
        CRUD::column('user_id')->label('Created by');
        CRUD::column('issue_id');
        CRUD::column('custom_issue');
        CRUD::addColumn([
            'name'      => 'priority_id',
            'label'     => 'Priority',
            'type'      => 'select',
            'entity'    => 'priority',         // The relationship in your Ticket Model
            'attribute' => 'priority_name',    // The column in the Statuses table to show
            'model'     => "App\Models\Priority",
            'wrapper'   => [
                'element' => 'span',
                'class'   => 'badge',
                'style'   => function ($crud, $column, $entry, $related_key) {
                    // Access the color from the related Status model
                    $color = $entry->priority ? $entry->priority->priority_color : '#c2c2c2';
                    return "background-color: {$color}; color: #000; padding: 5px 10px; border-radius: 4px; font-weight: bold;";
                },
            ],
        ]);
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
        CRUD::addField([
            'label'     => "Department",
            'type'      => 'select',
            'name'      => 'department_id',
            'entity'    => 'department',
            'model'     => "App\Models\Department",
            'attribute' => 'department_name',
            'wrapper'   => [
                'class' => 'form-group col-md-12 dept-wrapper d-none'
            ],
        ]);
        CRUD::addField([
            'label'     => "Division",
            'type'      => 'select',
            'name'      => 'division_id',
            'entity'    => 'division',
            'model'     => "App\Models\Division",
            'attribute' => 'division_name',
            'attributes' => [
                'disabled' => 'disabled',
            ],
            'wrapper'   => [
                'class' => 'form-group col-md-12 div-wrapper d-none'
            ],
        ]);

        CRUD::addField([
            'name'  => 'custom_toggle_script',
            'type'  => 'custom_html',
            'value' => '
                <script>
                    document.addEventListener("DOMContentLoaded", function () {

                        function toggleIssueFields() {
                            const checkbox = document.querySelector("[bp-field-name=\'is_custom_issue\'] input[type=\'checkbox\']");
                            const deptWrapper = document.querySelector(".dept-wrapper");
                            const divWrapper = document.querySelector(".div-wrapper");
                            const issueSelect = document.querySelector(".issue-select-wrapper");
                            const customIssue = document.querySelector(".custom-issue-wrapper");

                            if (!checkbox) return;

                            if (checkbox.checked) {
                                deptWrapper.classList.remove("d-none");
                                divWrapper.classList.remove("d-none");
                                issueSelect.classList.add("d-none");
                                customIssue.classList.remove("d-none");
                            } else {
                                deptWrapper.classList.add("d-none");
                                divWrapper.classList.add("d-none");
                                issueSelect.classList.remove("d-none");
                                customIssue.classList.add("d-none");
                            }
                        }

                        // Handle Department -> Division logic
                        document.addEventListener("change", function(e) {
                            // Check if the change happened on the department select
                            if (e.target.name === "department_id") {
                                const deptId = e.target.value;
                                const divSelect = document.querySelector("select[name=\'division_id\']");

                                if (!divSelect) return;

                                if (deptId) {
                                    // 1. Enable Division and show loading state
                                    divSelect.disabled = false;
                                    divSelect.innerHTML = "<option value=\'\'>Loading...</option>";

                                    // 2. Fetch Data
                                    fetch("/api/department/" + deptId + "/divisions")
                                        .then(response => response.json())
                                        .then(data => {
                                            divSelect.innerHTML = "<option value=\'\'>-</option>";
                                            data.forEach(div => {
                                                const option = document.createElement("option");
                                                option.value = div.id;
                                                option.text = div.division_name;
                                                divSelect.appendChild(option);
                                            });
                                        })
                                        .catch(error => {
                                            console.error("Error fetching divisions:", error);
                                            divSelect.innerHTML = "<option value=\'\'>Error loading divisions</option>";
                                        });
                                } else {
                                    // Disable and reset if no department selected
                                    divSelect.value = "";
                                    divSelect.disabled = true;
                                    divSelect.innerHTML = "<option value=\'\'>-</option>";
                                }
                            }

                            // Trigger visibility toggle if checkbox clicked
                            if (e.target.closest("[bp-field-name=\'is_custom_issue\']")) {
                                toggleIssueFields();
                            }
                        });

                        // Run once on load to set initial state
                        setTimeout(toggleIssueFields, 500);
                    });
                </script>
            ',
        ]);
    }

    protected function renderAttachments($ticket)
    {
        if (!$ticket->attachments || !is_array($ticket->attachments)) {
            return '<p class="text-muted">No attachments.</p>';
        }
        // Use a label tag to match native Backpack styling
        $html = '<div class="form-group col-md-12" style="padding:0;">';
        $html .= '<label>Attachment/s</label>';
        // Remove bullets with list-style:none
        $html .= '<ul style="list-style: none; padding-left: 0; margin-bottom: 0;">';

        foreach ($ticket->attachments as $file) {
            $url = asset('storage/' . $file);
            $html .= "
            <li style='margin-bottom: 5px;'>
                <a href='{$url}' target='_blank' class='text-primary' style='text-decoration: none;'>
                    <i class='la la-file-text'></i> " . basename($file) . "
                </a>
            </li>";
        }
        $html .= '</ul></div>';
        return $html;
    }

    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();

        $this->crud->setEditContentClass('col-md-8');

        $ticket = $this->crud->getCurrentEntry();
        $user = backpack_user();
        $canEditAttachments = $user->id === $ticket->user_id || $user->hasRole('admin');

        if (!$canEditAttachments) {
            $this->crud->modifyField('issue_id', [
                'attributes' => [
                    'style' => 'pointer-events:none; background-color:#e9ecef;',
                    'tabindex' => '-1',
                ],
            ]);
        }
        if (!$canEditAttachments) {
            $this->crud->modifyField('is_custom_issue', [
                'attributes' => [
                    'onclick' => 'return false;',
                    'onkeydown' => 'return false;',
                    'style' => 'opacity:0.7;',
                ],
            ]);
        }
        if (!$canEditAttachments) {
            $this->crud->modifyField('custom_issue', [
                'attributes' => [
                    'style' => 'pointer-events:none; background-color:#e9ecef;',
                    'tabindex' => '-1',
                ],
            ]);
        }
        if (!$canEditAttachments) {
            $this->crud->modifyField('message', [
                'attributes' => [
                    'style' => 'pointer-events:none; background-color:#e9ecef;',
                    'tabindex' => '-1',
                ],
            ]);
        }
        if (!$canEditAttachments) {
            $this->crud->removeField('attachments');
            $this->crud->addField([
                'name'  => 'attachments',
                'type'  => 'custom_html',
                'value' => $this->renderAttachments($ticket),
            ]);
        }
        if ($user->hasAnyRole(['admin', 'dept_head', 'div_head'])) {
            // Get the current ticket being edited
            $entry = $this->crud->getCurrentEntry();

            CRUD::addField([
                'label'     => "Assign to Staff",
                'type'      => 'select',
                'name'      => 'assigned_to',
                'entity'    => 'assignee',
                'model'     => "App\Models\User",
                'attribute' => 'name',
                'options'   => (function ($query) use ($entry) {
                    // 1. If we are in "Create" mode or ticket has no data, return empty or all HR
                    if (!$entry) {
                        return $query->role('hr_staff');
                    }
                    // 2. Filter HR staff to match the TICKET'S location exactly
                    return $query->role('hr_staff')
                                ->where('department_id', $entry->department_id)
                                ->where('division_id', $entry->division_id)
                                ->get();
                }),
            ]);
        }
    }

    public function update()
    {
        $request = $this->crud->getRequest();

        // 1. Handle "Quick Assign" (Staff)
        if ($request->has('assigned_to') && !$request->has('message')) {
            $entry = $this->crud->getEntry($request->id);
            $entry->assigned_to = $request->assigned_to;
            $entry->save();

            Alert::success('Staff assigned successfully.')->flash();
            return redirect($request->get('_http_referrer') ?? $this->crud->route);
        }

        // 2. Handle "Office Reassignment" (Department/Division)
        // We check for department_id while ensuring it's not the full edit form
        if ($request->has('department_id') && !$request->has('message')) {
            $entry = $this->crud->getEntry($request->id);

            $entry->department_id = $request->department_id;
            $entry->division_id = $request->division_id;
            $entry->assigned_to = null;

            $entry->save();

            Alert::success('Ticket reassigned to new office successfully.')->flash();
            return redirect($this->crud->route);
        }

        // Otherwise, proceed with standard Backpack update
        return $this->traitUpdate();
    }

    protected function setupShowOperation()
    {
        $user = backpack_user();

        CRUD::column('reference_id')->label('Reference Id');
        CRUD::column('user_id')->type('select')->entity('user')->attribute('name')->label('Created by');
        CRUD::column('issue_id')->type('select')->entity('issue')->attribute('issue_description')->label('Issue');
        CRUD::column('custom_issue');
        CRUD::column('message');
        CRUD::column('department_id')->type('select')->entity('department')->attribute('department_name')->label('Department')->limit(75);
        CRUD::column('division_id')->type('select')->entity('division')->attribute('division_name')->label('Division');

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
        CRUD::addColumn([
            'name'      => 'priority_id',
            'label'     => 'Priority',
            'type'      => 'select',
            'entity'    => 'priority',         // The relationship in your Ticket Model
            'attribute' => 'priority_name',    // The column in the Statuses table to show
            'model'     => "App\Models\Priority",
            'wrapper'   => [
                'element' => 'span',
                'class'   => 'badge',
                'style'   => function ($crud, $column, $entry, $related_key) {
                    // Access the color from the related Status model
                    $color = $entry->priority ? $entry->priority->priority_color : '#c2c2c2';
                    return "background-color: {$color}; color: #000; padding: 5px 10px; border-radius: 4px; font-weight: bold;";
                },
            ],
        ]);
        CRUD::addColumn([
            'name'     => 'attachments',
            'label'    => 'Attachments',
            'type'     => 'closure',
            'function' => function($entry) {
                if (!$entry->attachments || !is_array($entry->attachments)) {
                    return '-';
                }

                $output = '';
                foreach ($entry->attachments as $path) {
                    $url = asset('storage/' . $path);
                    $fileName = basename($path);
                    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

                    $viewable = ['jpg', 'jpeg', 'png', 'pdf'];
                    $isViewable = in_array($extension, $viewable);

                    $attributes = $isViewable
                        ? 'target="_blank"'
                        : 'download="' . $fileName . '"';
                    $output .= '<a href="'.$url.'" '.$attributes.' class="btn btn-sm btn-outline-primary mr-1 mb-1" style="text-transform: none;">';
                    $output .= '<i class="la la-paperclip"></i> ' . $fileName;
                    $output .= '</a> ';
                }
                return $output;
            },
            'escaped' => false,
        ]);

        if ($user->hasAnyRole(['admin', 'dept_head', 'div_head'])) {
            CRUD::addColumn([
                'name'     => 'quick_assign',
                'label'    => 'Quick Assign Staff',
                'type'     => 'closure',
                'function' => function($entry) {
                    $hrStaff = \App\Models\User::role('hr_staff')
                        ->where('department_id', $entry->department_id)
                        ->where('division_id', $entry->division_id)
                        ->get();

                    $formUrl = url($this->crud->route.'/'.$entry->id);
                    $csrf = csrf_field();
                    $method = method_field('PUT');

                    $options = '<option value="">- Select Staff -</option>';
                    foreach ($hrStaff as $staff) {
                        $selected = ($entry->assigned_to == $staff->id) ? 'selected' : '';
                        $options .= "<option value='{$staff->id}' {$selected}>{$staff->name}</option>";
                    }

                    return "
                        <form action='{$formUrl}' method='POST' class='form-inline'>
                            {$csrf}
                            {$method}
                            <input type='hidden' name='_http_referrer' value='".url()->current()."'>
                            <div class='form-group mb-0'>
                                <select name='assigned_to' class='form-control form-control-sm' style='width: 275px;'>
                                    {$options}
                                </select>
                                <button type='submit' class='btn btn-sm btn-success ml-1'>
                                    <i class='la la-save'></i>
                                </button>
                            </div>
                        </form>
                    ";
                },
                'escaped' => false, // Crucial: allows rendering the HTML form
            ]);
        }
        if ($user->hasAnyRole(['admin', 'dept_head', 'div_head']) && $this->crud->getCurrentEntry()->is_custom_issue == 1) {
            CRUD::addColumn([
                'name'     => 'office_reassignment',
                'label'    => 'Reassign',
                'type'     => 'closure',
                'wrapper'  => [
                    'class' => 'reassign-row-wrapper'
                ],
                'function' => function($entry) {
                    $departments = \App\Models\Department::all();
                    // Fetch divisions grouped by department to make filtering easier for JS
                    $divisions = \App\Models\Division::all();

                    $formUrl = url($this->crud->route.'/'.$entry->id);
                    $csrf = csrf_field();
                    $method = method_field('PUT');

                    $dept_options = '<option value="">- Select Department -</option>';
                    foreach ($departments as $department) {
                        $selected = ($entry->department_id == $department->id) ? 'selected' : '';
                        $dept_options .= "<option value='{$department->id}' {$selected}>{$department->department_name}</option>";
                    }

                    // We will populate divisions via JavaScript, but we need the initial state
                    $div_options = '<option value="">- Select Division -</option>';
                    foreach ($divisions as $division) {
                        $selected = ($entry->division_id == $division->id) ? 'selected' : '';
                        $div_options .= "<option value='{$division->id}' {$selected}>{$division->division_name}</option>";
                    }

                    return "
                        <form action='{$formUrl}' method='POST' class='form-inline' id='reassignForm-{$entry->id}'>
                            {$csrf}
                            {$method}
                            <input type='hidden' name='_http_referrer' value='".url()->current()."'>
                            <div class='form-group mb-0'>
                                <select name='department_id' id='dept_select-{$entry->id}' class='form-control form-control-sm' style='width: 250px;'>
                                    {$dept_options}
                                </select>
                                &nbsp;
                                <select name='division_id' id='div_select-{$entry->id}' class='form-control form-control-sm' style='width: 250px;'>
                                    {$div_options}
                                </select>
                                <button type='submit' class='btn btn-sm btn-success ml-1'>
                                    <i class='la la-save'></i>
                                </button>
                            </div>
                        </form>

                        <script>
                            (function() {
                                const divisions = ". $divisions->toJson() .";
                                const deptSelect = document.getElementById('dept_select-{$entry->id}');
                                const divSelect = document.getElementById('div_select-{$entry->id}');

                                deptSelect.addEventListener('change', function() {
                                    const selectedDeptId = this.value;

                                    // Clear and Reset Division Dropdown
                                    divSelect.innerHTML = '<option value=\"\">- Select Division -</option>';

                                    if (selectedDeptId) {
                                        // Filter divisions belonging to this department
                                        const filtered = divisions.filter(d => d.department_id == selectedDeptId);

                                        filtered.forEach(div => {
                                            let option = document.createElement('option');
                                            option.value = div.id;
                                            option.text = div.division_name;
                                            divSelect.appendChild(option);
                                        });
                                    }
                                });
                            })();
                        </script>
                    ";
                },
                'escaped' => false,
            ]);
        }
        $this->crud->removeAllButtonsFromStack('line');
    }
}
