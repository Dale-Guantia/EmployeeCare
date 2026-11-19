@if (!\App\Services\MasterDataRules::isCoreStatus($entry) && backpack_user()->can('status' . ($entry->is_active ? '.archive' : '.reactivate')))
    <form method="POST" action="{{ route($entry->is_active ? 'status.archive' : 'status.reactivate', $entry->getKey()) }}" class="d-inline"
        data-office-archive-action="{{ $entry->is_active ? 'archive' : 'reactivate' }}" data-office-label="status"
        data-table-id="{{ $crudTableId ?? 'crudTable' }}" data-return-url="{{ url($crud->route) }}">
        @csrf
        <button type="submit" class="btn btn-sm btn-link">
            <i class="la {{ $entry->is_active ? 'la-archive' : 'la-undo' }}"></i> {{ $entry->is_active ? 'Archive' : 'Reactivate' }}
        </button>
    </form>
@endif
@push('after_scripts') @if (request()->ajax()) @endpush @endif
@basset(public_path('js/office-archive.js'))
@if (!request()->ajax()) @endpush @endif
