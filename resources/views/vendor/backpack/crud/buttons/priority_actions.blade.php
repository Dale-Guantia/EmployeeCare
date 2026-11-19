@if (backpack_user()->can('priority' . ($entry->is_active ? '.archive' : '.reactivate')))
    <button type="button" class="btn btn-sm btn-link" data-priority-action="{{ $entry->is_active ? 'archive' : 'reactivate' }}"
        data-action-url="{{ route($entry->is_active ? 'priority.archive' : 'priority.reactivate', $entry->getKey()) }}"
        data-return-url="{{ url($crud->route) }}" data-table-id="{{ $crudTableId ?? 'crudTable' }}">
        <i class="la {{ $entry->is_active ? 'la-archive' : 'la-undo' }}"></i> {{ $entry->is_active ? 'Archive' : 'Reactivate' }}
    </button>
@endif
@if ($crud->hasAccess('delete', $entry))
    <button type="button" class="btn btn-sm btn-link text-danger" data-priority-action="delete"
        data-action-url="{{ url($crud->route . '/' . $entry->getKey()) }}"
        data-impact-url="{{ route('priority.delete-impact', $entry->getKey()) }}"
        data-return-url="{{ url($crud->route) }}" data-table-id="{{ $crudTableId ?? 'crudTable' }}">
        <i class="la la-trash"></i> Delete
    </button>
@endif

@push('after_scripts') @if (request()->ajax()) @endpush @endif
@basset(public_path('js/priority-actions.js'))
@if (!request()->ajax()) @endpush @endif
