@php
    $ticket = $entry;
    $user = backpack_user();
    $pending = $ticket->pendingReassignmentRequest;

    $resolvedId = \App\Models\Status::idByName('Resolved');
    $isResolved = $resolvedId && (int) $ticket->status_id === (int) $resolvedId;

    $isOwningHead = $user->hasRole('admin')
        || ($user->hasRole('dept_head') && (int) $user->department_id === (int) $ticket->department_id)
        || ($user->hasRole('div_head') && (int) $user->division_id === (int) $ticket->division_id);

    $isReceivingHead = $pending && (
        $user->hasRole('admin')
        || ($user->hasRole('dept_head') && (int) $user->department_id === (int) $pending->to_department_id)
        || ($user->hasRole('div_head') && $pending->to_division_id && (int) $user->division_id === (int) $pending->to_division_id)
    );

    $isRequester = $pending && (
        $user->hasRole('admin') || (int) $user->id === (int) $pending->requested_by
    );

    $formUrl = url(config('backpack.base.route_prefix') . '/ticket/' . $ticket->id);
    $departments = \App\Models\Department::allCached();
    $divisions = \App\Models\Division::allCached();
@endphp

@if ($pending)
    <div class="mb-1">
        <span class="badge badge-info">
            Pending: {{ optional($pending->toDepartment)->department_name }}
            @if ($pending->toDivision) / {{ $pending->toDivision->division_name }} @endif
        </span>
    </div>

    @if ($isReceivingHead)
        <button type="button" class="btn btn-sm btn-success mr-1" data-toggle="modal" data-target="#reassignAcceptModal{{ $ticket->id }}">
            <i class="la la-check"></i> Accept
        </button>
        <button type="button" class="btn btn-sm btn-danger mr-1" data-toggle="modal" data-target="#reassignRejectModal{{ $ticket->id }}">
            <i class="la la-times"></i> Reject
        </button>

        <div class="modal fade" id="reassignAcceptModal{{ $ticket->id }}" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title">Accept Reassignment</h5>
                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <form action="{{ $formUrl }}" method="POST">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="_http_referrer" value="{{ url()->current() }}">
                        <input type="hidden" name="_ticket_action" value="reassign_accept">
                        <div class="modal-body text-left">
                            <p>Accept ticket <strong>{{ $ticket->reference_id }}</strong> into your department/division?</p>
                            <p class="text-muted small">It will be auto-assigned to the least-busy available staff.</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-success">Yes, Accept</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="reassignRejectModal{{ $ticket->id }}" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title">Reject Reassignment</h5>
                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <form action="{{ $formUrl }}" method="POST">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="_http_referrer" value="{{ url()->current() }}">
                        <input type="hidden" name="_ticket_action" value="reassign_reject">
                        <div class="modal-body text-left">
                            <p>Reject the request to reassign ticket <strong>{{ $ticket->reference_id }}</strong> to your office?</p>
                            <div class="form-group">
                                <label>Reason (optional)</label>
                                <textarea name="response_note" class="form-control" rows="2"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-danger">Yes, Reject</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @elseif ($isRequester)
        <button type="button" class="btn btn-sm btn-secondary" data-toggle="modal" data-target="#reassignCancelModal{{ $ticket->id }}">
            <i class="la la-ban"></i> Cancel Request
        </button>

        <div class="modal fade" id="reassignCancelModal{{ $ticket->id }}" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header bg-secondary text-white">
                        <h5 class="modal-title">Cancel Reassignment Request</h5>
                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <form action="{{ $formUrl }}" method="POST">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="_http_referrer" value="{{ url()->current() }}">
                        <input type="hidden" name="_ticket_action" value="reassign_cancel">
                        <div class="modal-body text-left">
                            <p>Cancel your pending reassignment request for ticket <strong>{{ $ticket->reference_id }}</strong>?</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-danger">Yes, Cancel it</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @else
        <span class="text-muted small">Awaiting response</span>
    @endif
@elseif ($isOwningHead && !$isResolved)
    <button type="button" class="btn btn-sm btn-outline-primary" data-toggle="modal" data-target="#requestReassignModal{{ $ticket->id }}">
        <i class="la la-exchange-alt"></i> Request Reassignment
    </button>

    <div class="modal fade" id="requestReassignModal{{ $ticket->id }}" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Request Reassignment</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <form action="{{ $formUrl }}" method="POST">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="_http_referrer" value="{{ url()->current() }}">
                    <input type="hidden" name="_ticket_action" value="reassign_request">
                    <div class="modal-body text-left">
                        <div class="form-group">
                            <label>Target Department</label>
                            <select name="to_department_id" id="reassign_to_department_{{ $ticket->id }}" class="form-control">
                                <option value="">- Select Department -</option>
                                @foreach ($departments as $department)
                                    <option value="{{ $department->id }}">{{ $department->department_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Target Division</label>
                            <select name="to_division_id" id="reassign_to_division_{{ $ticket->id }}" class="form-control" disabled>
                                <option value="">- Select Division -</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Reason (optional)</label>
                            <textarea name="reassign_reason" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Send Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        (function() {
            var allDivisions = @json($divisions);
            var deptSelect = document.getElementById('reassign_to_department_{{ $ticket->id }}');
            var divSelect = document.getElementById('reassign_to_division_{{ $ticket->id }}');

            if (deptSelect && divSelect) {
                deptSelect.addEventListener('change', function() {
                    var deptId = this.value;
                    divSelect.innerHTML = '<option value="">- Select Division -</option>';

                    if (deptId) {
                        divSelect.disabled = false;
                        allDivisions
                            .filter(function(d) { return d.department_id == deptId; })
                            .forEach(function(d) {
                                var option = document.createElement('option');
                                option.value = d.id;
                                option.text = d.division_name;
                                divSelect.appendChild(option);
                            });
                    } else {
                        divSelect.disabled = true;
                    }

                    if (window.hrfRebindTomSelect) {
                        window.hrfRebindTomSelect(divSelect, { emptyOptionText: '- Select Division -' });
                    }
                });

                if (window.hrfInitTomSelect) {
                    window.hrfInitTomSelect(deptSelect, { emptyOptionText: '- Select Department -' });
                    window.hrfInitTomSelect(divSelect, { emptyOptionText: '- Select Division -' });
                }
            }
        })();
    </script>
@else
    <span class="text-muted">-</span>
@endif

<script>
    (function() {
        ['requestReassignModal{{ $ticket->id }}', 'reassignAcceptModal{{ $ticket->id }}', 'reassignRejectModal{{ $ticket->id }}', 'reassignCancelModal{{ $ticket->id }}'].forEach(function(id) {
            var modal = document.getElementById(id);
            if (modal && modal.parentElement !== document.body) {
                document.body.appendChild(modal);
            }
        });
    })();
</script>
