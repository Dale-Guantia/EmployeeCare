@if ($crud->hasAccess('delete', $entry))
    @php
        $entity = match ($entry->getTable()) {
            'departments' => 'department', 'divisions' => 'division', 'issues' => 'issue', 'statuses' => 'status',
        };
    @endphp
    <button type="button" class="btn btn-sm btn-link text-danger" data-priority-action="delete" data-record-label="{{ $entity }}"
        data-action-url="{{ url($crud->route . '/' . $entry->getKey()) }}"
        data-impact-url="{{ route($entity . '.delete-impact', $entry->getKey()) }}"
        data-return-url="{{ url($crud->route) }}" data-table-id="{{ $crudTableId ?? 'crudTable' }}">
        <i class="la la-trash"></i> Delete
    </button>
@endif
@push('after_scripts') @if (request()->ajax()) @endpush @endif
@basset(public_path('js/priority-actions.js'))
@if (!request()->ajax()) @endpush @endif
