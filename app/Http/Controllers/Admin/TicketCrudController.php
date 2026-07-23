<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\TicketRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Prologue\Alerts\Facades\Alert;
use App\Models\Status;
use App\Models\Department;
use App\Models\Division;
use App\Models\Ticket;
use App\Models\TicketReassignmentRequest;
use App\Models\User;
use App\Services\TicketAutoAssignmentService;
use App\Services\TicketNotificationService;
use Illuminate\Support\Facades\Storage;

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
                // Also let a receiving dept_head see tickets pending transfer into their department
                $query->orWhereHas('pendingReassignmentRequest', function ($q) use ($user) {
                    $q->where('to_department_id', $user->department_id);
                });
                $this->crud->allowAccess(['create','update','delete','show']);
            }
            if ($user->hasRole('div_head')) {
                $query->orWhere('division_id', $user->division_id);
                // Also let a receiving div_head see tickets pending transfer into their division
                $query->orWhereHas('pendingReassignmentRequest', function ($q) use ($user) {
                    $q->where('to_division_id', $user->division_id);
                });
                $this->crud->allowAccess(['create','show', 'update']);
            }
            if ($user->hasRole('hr_staff')) {
                // HR Staff ONLY sees tickets assigned specifically to them
                $query->orWhere('assigned_to', $user->id);
                $this->crud->allowAccess(['create','show']);
            }
            if ($user->hasRole('employee')) {
                $query;
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
        // 4. If user is a regular Employee, only show tickets they created
        if ($user->hasRole('employee')) {
            $this->crud->addClause('where', 'user_id', $user->id);
        };

        // Eager-load relations used by columns below to avoid N+1 queries.
        // Backpack keeps its query builder around, so ->with() here sticks.
        $this->crud->query->with(['status', 'priority', 'user', 'issue']);

        CRUD::column('reference_id')->label('Reference Id');
        CRUD::column('user_id')->label('Created by');
        CRUD::addColumn([
            'name'     => 'issue_id',
            'label'    => 'Issue',
            'type'     => 'closure',
            'function' => function ($entry) {
                if ($entry->is_custom_issue) {
                    return e($entry->custom_issue ?: '-');
                }
                return e(optional($entry->issue)->issue_description ?? '-');
            },
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
            'name'     => 'overdue_badge',
            'label'    => 'Overdue',
            'type'     => 'closure',
            'function' => function ($entry) {
                $badge = $entry->overdue_badge;

                return '<span class="' . $badge['class'] . '" style="' . $badge['style'] . '">' . e($badge['text']) . '</span>';
            },
            'escaped' => false,
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
            'type'      => 'view', // Tell Backpack to use a custom view
            'view'      => 'vendor.backpack.crud.fields.modern_upload', // Point to your new file
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
                                            divSelect.innerHTML = "<option value=\'\'>- Select Division -</option>";
                                            data.forEach(div => {
                                                const option = document.createElement("option");
                                                option.value = div.id;
                                                option.text = div.division_name;
                                                divSelect.appendChild(option);
                                            });
                                            if (window.hrfRebindTomSelect) {
                                                window.hrfRebindTomSelect(divSelect, { emptyOptionText: "- Select Division -" });
                                            }
                                        })
                                        .catch(error => {
                                            console.error("Error fetching divisions:", error);
                                            divSelect.innerHTML = "<option value=\'\'>Error loading divisions</option>";
                                        });
                                } else {
                                    // Disable and reset if no department selected
                                    divSelect.value = "";
                                    divSelect.disabled = true;
                                    divSelect.innerHTML = "<option value=\'\'>- Select Division -</option>";
                                    if (window.hrfRebindTomSelect) {
                                        window.hrfRebindTomSelect(divSelect, { emptyOptionText: "- Select Division -" });
                                    }
                                }
                            }

                            // Trigger visibility toggle if checkbox clicked
                            if (e.target.closest("[bp-field-name=\'is_custom_issue\']")) {
                                toggleIssueFields();
                            }
                        });

                        // Run once on load to set initial state
                        setTimeout(toggleIssueFields, 500);

                        // Tom Select wiring — Backpack-PRO-free searchable dropdowns.
                        if (window.hrfInitTomSelect) {
                            window.hrfInitTomSelect("select[name=\'issue_id\']", { emptyOptionText: "- Select Issue -" });
                            window.hrfInitTomSelect("select[name=\'department_id\']", { emptyOptionText: "- Select Department -" });
                            window.hrfInitTomSelect("select[name=\'division_id\']", { emptyOptionText: "- Select Division -" });
                            window.hrfInitTomSelect("select[name=\'assigned_to\']", { emptyOptionText: "- Select Staff -" });
                            window.hrfInitTomSelect("select[name=\'status_id\']", { emptyOptionText: "- Select Status -" });
                        }
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
        $canEditAttachments = $user->id === $ticket->user_id || $user->hasRole('admin') || $user->hasRole('div_head');

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
                'allows_null' => true,
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
        if ($user->hasAnyRole(['admin', 'dept_head', 'div_head'])) {
            CRUD::addField([
                'label'     => "Change Status",
                'type'      => 'select',
                'name'      => 'status_id',
                'entity'    => 'status',
                'model'     => "App\Models\Status",
                'attribute' => 'status_name',
            ]);
        }
    }

    private function isReceivingHead(TicketReassignmentRequest $reassignment, $user): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('dept_head') && (int) $user->department_id === (int) $reassignment->to_department_id) {
            return true;
        }

        if ($user->hasRole('div_head') && $reassignment->to_division_id && (int) $user->division_id === (int) $reassignment->to_division_id) {
            return true;
        }

        return false;
    }

    private function handleReopen($request)
    {
        $entry = $this->crud->getEntry($request->id);
        $user = backpack_user();

        if ((int) $user->id !== (int) $entry->user_id) {
            abort(403, 'Only the ticket creator can reopen this ticket.');
        }

        $reopenedId = Status::idByName('Reopened');

        if (!$reopenedId) {
            abort(500, 'Reopened status not found.');
        }

        $entry->status_id = $reopenedId;
        $entry->save();

        Alert::success('Ticket reopened successfully.')->flash();

        return redirect($request->get('_http_referrer') ?? $this->crud->route);
    }

    private function handleResolve($request)
    {
        $entry = $this->crud->getEntry($request->id);
        $user = backpack_user();

        $canResolve = $user->hasRole('admin')
            || ($user->hasRole('dept_head') && (int) $user->department_id === (int) $entry->department_id)
            || ($user->hasRole('div_head') && (int) $user->division_id === (int) $entry->division_id)
            || ($user->hasRole('hr_staff') && (int) $user->id === (int) $entry->assigned_to);

        if (!$canResolve) {
            abort(403, 'You are not allowed to resolve this ticket.');
        }

        $resolvedId = Status::idByName('Resolved');

        if (!$resolvedId) {
            abort(500, 'Resolved status not found.');
        }

        $entry->status_id = $resolvedId;
        $entry->save();

        Alert::success('Ticket resolved successfully.')->flash();

        return redirect($request->get('_http_referrer') ?? $this->crud->route);
    }

    private function handleQuickAssign($request)
    {
        $entry = $this->crud->getEntry($request->id);
        $user = backpack_user();

        // Must match the role list that renders the "Assign Staff" form in
        // setupShowOperation() (quick_assign column) — hr_staff can see and
        // submit that form, so it has to be allowed here too.
        $canAssign = $user->hasAnyRole(['admin', 'dept_head', 'div_head', 'hr_staff']);

        if (!$canAssign) {
            abort(403, 'You are not allowed to assign staff to this ticket.');
        }

        $entry->assigned_to = $request->get('assigned_to') ?: null;
        $entry->save();

        Alert::success('Staff assigned successfully.')->flash();

        return redirect($request->get('_http_referrer') ?? $this->crud->route);
    }

    private function handleReassignRequest($request)
    {
        $entry = $this->crud->getEntry($request->id);
        $user = backpack_user();

        $canRequest = $user->hasRole('admin')
            || ($user->hasRole('dept_head') && (int) $user->department_id === (int) $entry->department_id)
            || ($user->hasRole('div_head') && (int) $user->division_id === (int) $entry->division_id);

        if (!$canRequest) {
            abort(403, 'You are not allowed to request reassignment for this ticket.');
        }

        $referrer = $request->get('_http_referrer') ?? $this->crud->route;

        $resolvedId = Status::idByName('Resolved');
        if ($resolvedId && (int) $entry->status_id === (int) $resolvedId) {
            Alert::error('Cannot request reassignment for a resolved ticket.')->flash();
            return redirect($referrer);
        }

        if ($entry->pendingReassignmentRequest) {
            Alert::error('This ticket already has a pending reassignment request.')->flash();
            return redirect($referrer);
        }

        $toDepartmentId = $request->get('to_department_id');
        $toDivisionId = $request->get('to_division_id');

        if (!$toDepartmentId || !$toDivisionId) {
            Alert::error('Please select a target department and division.')->flash();
            return redirect($referrer);
        }

        $divisionBelongs = Division::where('id', $toDivisionId)
            ->where('department_id', $toDepartmentId)
            ->exists();

        if (!$divisionBelongs) {
            Alert::error('The selected division does not belong to the selected department.')->flash();
            return redirect($referrer);
        }

        if ((int) $toDepartmentId === (int) $entry->department_id && (int) $toDivisionId === (int) $entry->division_id) {
            Alert::error('Please select a different department or division to reassign to.')->flash();
            return redirect($referrer);
        }

        $reassignment = TicketReassignmentRequest::create([
            'ticket_id' => $entry->id,
            'requested_by' => $user->id,
            'from_department_id' => $entry->department_id,
            'from_division_id' => $entry->division_id,
            'to_department_id' => $toDepartmentId,
            'to_division_id' => $toDivisionId,
            'reason' => $request->get('reassign_reason'),
            'status' => TicketReassignmentRequest::STATUS_PENDING,
        ]);

        app(TicketNotificationService::class)->notifyReassignmentRequested($entry, $reassignment, $user);

        Alert::success('Reassignment request sent.')->flash();

        return redirect($referrer);
    }

    private function handleReassignCancel($request)
    {
        $entry = $this->crud->getEntry($request->id);
        $user = backpack_user();
        $referrer = $request->get('_http_referrer') ?? $this->crud->route;

        $reassignment = $entry->pendingReassignmentRequest;

        if (!$reassignment) {
            Alert::error('There is no pending reassignment request to cancel.')->flash();
            return redirect($referrer);
        }

        $canCancel = $user->hasRole('admin') || (int) $user->id === (int) $reassignment->requested_by;

        if (!$canCancel) {
            abort(403, 'Only the requester can cancel this reassignment request.');
        }

        $reassignment->update(['status' => TicketReassignmentRequest::STATUS_CANCELLED]);

        Alert::success('Reassignment request cancelled.')->flash();

        return redirect($referrer);
    }

    private function handleReassignAccept($request)
    {
        $entry = $this->crud->getEntry($request->id);
        $user = backpack_user();
        $referrer = $request->get('_http_referrer') ?? $this->crud->route;

        $reassignment = $entry->pendingReassignmentRequest;

        if (!$reassignment) {
            Alert::error('There is no pending reassignment request to accept.')->flash();
            return redirect($referrer);
        }

        if (!$this->isReceivingHead($reassignment, $user)) {
            abort(403, 'Only the receiving department/division head can accept this reassignment.');
        }

        // 1-3: move the ticket and clear the assignee. Part 0a's saving() guard
        // lets this survive (issue_id isn't dirty, so the auto-fill won't clobber
        // it), and the existing isDirty('assigned_to') hook flips status to
        // Unassigned automatically.
        $entry->department_id = $reassignment->to_department_id;
        $entry->division_id = $reassignment->to_division_id;
        $entry->assigned_to = null;
        $entry->save();

        // 4. Reuse the existing skill+load auto-assignment service — do not
        // reimplement matching logic here.
        $assigned = app(TicketAutoAssignmentService::class)->assignTicket($entry);

        // 5. Mark the request accepted.
        $reassignment->update([
            'status' => TicketReassignmentRequest::STATUS_ACCEPTED,
            'responded_by' => $user->id,
            'responded_at' => now(),
        ]);

        // 6. Notify the requester; the newly assigned staff (if any) is notified
        // via the existing notifyTicketAssigned path fired from Ticket::updated().
        app(TicketNotificationService::class)->notifyReassignmentAccepted($entry, $reassignment, $user);

        if ($assigned) {
            $entry->refresh()->loadMissing('assignee');
            $staffName = optional($entry->assignee)->name ?? 'a staff member';
            Alert::success("Reassignment accepted and auto-assigned to {$staffName}.")->flash();
        } else {
            Alert::success('Reassignment accepted. No eligible staff found in the new division — ticket left unassigned.')->flash();
        }

        return redirect($referrer);
    }

    private function handleReassignReject($request)
    {
        $entry = $this->crud->getEntry($request->id);
        $user = backpack_user();
        $referrer = $request->get('_http_referrer') ?? $this->crud->route;

        $reassignment = $entry->pendingReassignmentRequest;

        if (!$reassignment) {
            Alert::error('There is no pending reassignment request to reject.')->flash();
            return redirect($referrer);
        }

        if (!$this->isReceivingHead($reassignment, $user)) {
            abort(403, 'Only the receiving department/division head can reject this reassignment.');
        }

        $reassignment->update([
            'status' => TicketReassignmentRequest::STATUS_REJECTED,
            'responded_by' => $user->id,
            'responded_at' => now(),
            'response_note' => $request->get('response_note'),
        ]);

        app(TicketNotificationService::class)->notifyReassignmentRejected($entry, $reassignment, $user);

        Alert::success('Reassignment request rejected.')->flash();

        return redirect($referrer);
    }

    public function update()
    {
        $request = $this->crud->getRequest();
        $action = $request->get('_ticket_action');

        switch ($action) {
            case 'reopen':
                return $this->handleReopen($request);
            case 'resolve':
                return $this->handleResolve($request);
            case 'quick_assign':
                return $this->handleQuickAssign($request);
            case 'reassign_request':
                return $this->handleReassignRequest($request);
            case 'reassign_cancel':
                return $this->handleReassignCancel($request);
            case 'reassign_accept':
                return $this->handleReassignAccept($request);
            case 'reassign_reject':
                return $this->handleReassignReject($request);
        }

        // Default Backpack Update — with attachment removal handling
        $request = $this->crud->getRequest();

        // Collect only retained string paths from the hidden inputs.
        // Files the user removed via ✕ will be absent here.
        $retainedPaths = collect($request->input('attachments', []))
            ->filter(function ($item) {
                return is_string($item) && !empty($item);
            })
            ->values()
            ->all();

        // Delete removed files from disk
        $entry    = $this->crud->getEntry($request->id);
        $oldPaths = is_array($entry->attachments)
            ? $entry->attachments
            : (json_decode($entry->getRawOriginal('attachments'), true) ?? []);

        foreach (array_diff($oldPaths, $retainedPaths) as $removedPath) {
            if (empty($removedPath)) {
                continue;
            }
            try {
                if (Storage::disk('public')->exists($removedPath)) {
                    Storage::disk('public')->delete($removedPath);
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error(
                    '[TicketCrudController] Failed to delete removed attachment "' . $removedPath . '": ' . $e->getMessage()
                );
            }
        }

        // Pass only the retained paths — the mutator will append new uploads on top
        $request->merge(['attachments' => $retainedPaths]);
        $this->crud->setRequest($request);

        return $this->traitUpdate();
    }

    protected function setupShowOperation()
    {
        $this->crud->setShowContentClass('col-md-8');
        $user = backpack_user();

        // Eager-load relations used below to avoid N+1 queries.
        // Backpack's getModelWithCrudPanelQuery() discards ->with() on the query
        // builder for the Show operation, so load directly on the fetched entry.
        $this->crud->getCurrentEntry()->load([
            'status', 'priority', 'user', 'issue', 'department', 'division', 'pendingReassignmentRequest',
        ]);

        $resolvedId = Status::idByName('Resolved');
        $reopenedId = Status::idByName('Reopened');

        CRUD::column('reference_id')->label('Reference Id');
        CRUD::column('user_id')->type('select')->entity('user')->attribute('name')->label('Created by');
        CRUD::addColumn([
            'name'     => 'issue_id',
            'label'    => 'Issue',
            'type'     => 'closure',
            'function' => function ($entry) {
                if ($entry->is_custom_issue) {
                    return e($entry->custom_issue ?: '-');
                }
                return e(optional($entry->issue)->issue_description ?? '-');
            },
        ]);
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
            'name'     => 'overdue_badge',
            'label'    => 'Overdue',
            'type'     => 'closure',
            'function' => function ($entry) {
                $badge = $entry->overdue_badge;

                return '<span class="' . $badge['class'] . '" style="' . $badge['style'] . '">' . e($badge['text']) . '</span>';
            },
            'escaped' => false,
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

        if ($user->hasAnyRole(['admin', 'dept_head', 'div_head', 'hr_staff'])) {
            CRUD::addColumn([
                'name'     => 'quick_assign',
                'label'    => 'Assign Staff',
                'type'     => 'closure',
                'function' => function($entry) use ($resolvedId) {
                    // Mirrors the Discussion panel's lock (see TicketChat::getIsResolvedProperty()):
                    // a resolved ticket is frozen until reopened, so the assignment
                    // control stays visible but non-interactive rather than a live form.
                    $isResolved = $resolvedId && (int) $entry->status_id === (int) $resolvedId;

                    if ($isResolved) {
                        $currentName = e(optional($entry->assignee)->name ?? '— Unassigned —');

                        return "
                            <div class='form-inline'>
                                <select class='form-control form-control-sm' style='width: 250px;' disabled>
                                    <option selected>{$currentName}</option>
                                </select>
                                <button type='button' class='btn btn-sm btn-success ml-1' disabled>
                                    <i class='la la-save'></i>
                                </button>
                            </div>
                            <small class='text-muted d-block mt-1'>Reopen the ticket to reassign.</small>
                        ";
                    }

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
                            <input type='hidden' name='_ticket_action' value='quick_assign'>
                            <div class='form-group mb-0'>
                                <select name='assigned_to' class='form-control form-control-sm' style='width: 250px;'>
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

        if ($user->hasAnyRole(['admin', 'dept_head', 'div_head'])) {
            CRUD::addColumn([
                'name'     => 'reassignment_widget',
                'label'    => 'Reassign',
                'type'     => 'view',
                'view'     => 'admin.ticket.reassignment_widget',
            ]);
        }

        CRUD::addColumn([
            'name'     => 'ticket_actions',
            'label'    => 'Action',
            'type'     => 'closure',
            'function' => function($entry) use ($resolvedId, $reopenedId, $user) {
                $formUrl = url($this->crud->route.'/'.$entry->id);
                $csrf = csrf_field();
                $method = method_field('PUT');

                $buttons = '';

                /*
                |--------------------------------------------------------------------------
                | Resolve Button
                |--------------------------------------------------------------------------
                | Visible to admin / dept_head / div_head / hr_staff if ticket is not yet resolved.
                | Must match handleResolve()'s $canResolve exactly (role alone isn't
                | enough there — dept_head/div_head/hr_staff are also scoped to their
                | own department/division/assignment) or this button shows for users
                | who will get a 403 the moment they click it.
                */

                $canResolve = $user->hasRole('admin')
                    || ($user->hasRole('dept_head') && (int) $user->department_id === (int) $entry->department_id)
                    || ($user->hasRole('div_head') && (int) $user->division_id === (int) $entry->division_id)
                    || ($user->hasRole('hr_staff') && (int) $user->id === (int) $entry->assigned_to);
                $isResolved = (int) $entry->status_id === (int) $resolvedId;

                if ($canResolve && !$isResolved && $resolvedId) {
                    $resolveModalId = "resolveModal{$entry->id}";

                    $buttons .= "
                        <button type='button' class='btn btn-sm btn-success mr-1' data-toggle='modal' data-target='#{$resolveModalId}'>
                            <i class='la la-check-circle'></i> Resolve
                        </button>

                        <div class='modal fade' id='{$resolveModalId}' tabindex='-1' role='dialog' aria-hidden='true'>
                            <div class='modal-dialog' role='document'>
                                <div class='modal-content'>
                                    <div class='modal-header bg-primary text-white'>
                                        <h5 class='modal-title'>Confirm Resolution</h5>
                                        <button type='button' class='close text-white' data-dismiss='modal' aria-label='Close'>
                                            <span aria-hidden='true'>&times;</span>
                                        </button>
                                    </div>
                                    <form action='{$formUrl}' method='POST'>
                                        {$csrf}
                                        {$method}
                                        <input type='hidden' name='_http_referrer' value='".url()->current()."'>
                                        <input type='hidden' name='_ticket_action' value='resolve'>
                                        <input type='hidden' name='status_id' value='{$resolvedId}'>

                                        <div class='modal-body text-left'>
                                            <p>Are you sure you want to mark Ticket <strong>{$entry->reference_id}</strong> as Resolved?</p>
                                            <p class='text-muted small'>This will notify the user and close the ticket.</p>
                                        </div>
                                        <div class='modal-footer'>
                                            <button type='button' class='btn btn-secondary' data-dismiss='modal'>Cancel</button>
                                            <button type='submit' class='btn btn-success'>Yes, Resolve it</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    ";
                }

                /*
                |--------------------------------------------------------------------------
                | Reopen Button
                |--------------------------------------------------------------------------
                | Visible only to ticket creator, only when ticket is resolved
                */

                $isCreator = (int) $user->id === (int) $entry->user_id;
                $canReopen = $isCreator && $isResolved && $reopenedId;

                if ($canReopen) {
                    $reopenModalId = "reopenModal{$entry->id}";

                    $buttons .= "
                        <button type='button' class='btn btn-sm btn-warning mr-1' data-toggle='modal' data-target='#{$reopenModalId}'>
                            <i class='la la-undo'></i> Reopen
                        </button>

                        <div class='modal fade' id='{$reopenModalId}' tabindex='-1' role='dialog' aria-hidden='true'>
                            <div class='modal-dialog' role='document'>
                                <div class='modal-content'>
                                    <div class='modal-header bg-warning text-dark'>
                                        <h5 class='modal-title'>Confirm Reopen</h5>
                                        <button type='button' class='close' data-dismiss='modal' aria-label='Close'>
                                            <span aria-hidden='true'>&times;</span>
                                        </button>
                                    </div>
                                    <form action='{$formUrl}' method='POST'>
                                        {$csrf}
                                        {$method}
                                        <input type='hidden' name='_http_referrer' value='".url()->current()."'>
                                        <input type='hidden' name='_ticket_action' value='reopen'>
                                        <input type='hidden' name='reopen_ticket' value='1'>

                                        <div class='modal-body text-left'>
                                            <p>Do you want to reopen Ticket <strong>{$entry->reference_id}</strong>?</p>
                                            <p class='text-muted small'>Use this if your concern is not yet resolved or you have follow-up questions.</p>
                                        </div>
                                        <div class='modal-footer'>
                                            <button type='button' class='btn btn-secondary' data-dismiss='modal'>Cancel</button>
                                            <button type='submit' class='btn btn-warning'>Yes, Reopen it</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    ";
                }

                if ($buttons === '') {
                    return '<span class=\"text-muted\">-</span>';
                }

                return $buttons . "
                    <script>
                        (function() {
                            ['resolveModal{$entry->id}', 'reopenModal{$entry->id}'].forEach(function(id) {
                                var modal = document.getElementById(id);
                                if (modal) {
                                    document.body.appendChild(modal);
                                }
                            });
                        })();
                    </script>
                ";
            },
            'escaped' => false,
        ]);

        $this->crud->removeAllButtonsFromStack('line');
    }
}
