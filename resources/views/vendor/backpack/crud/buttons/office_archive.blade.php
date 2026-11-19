@if (backpack_user()->can(($entry instanceof \App\Models\Department ? 'department' : 'division') . ($entry->is_active ? '.archive' : '.reactivate')))
    @php
        $entity = $entry instanceof \App\Models\Department ? 'department' : 'division';
        $action = $entry->is_active ? 'archive' : 'reactivate';
    @endphp
    <form method="POST" action="{{ route($entity . '.' . $action, $entry->getKey()) }}" class="d-inline"
        data-office-archive-action="{{ $action }}" data-office-label="{{ $entity }}"
        data-reactivation-summary="{{ !$entry->is_active ? \App\Services\MasterDataRules::reactivationSummary($entry) : '' }}"
        data-table-id="{{ $crudTableId ?? 'crudTable' }}" data-return-url="{{ url($crud->route) }}">
        @csrf
        <button type="submit" class="btn btn-sm btn-link">
            <i class="la {{ $entry->is_active ? 'la-archive' : 'la-undo' }}"></i>
            {{ $entry->is_active ? 'Archive' : 'Reactivate' }}
        </button>
    </form>
@endif

@push('after_scripts') @if (request()->ajax()) @endpush @endif
@basset(public_path('js/office-archive.js'))
@if (!request()->ajax()) @endpush @endif
