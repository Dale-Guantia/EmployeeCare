@if (backpack_user()->can('issue' . ($entry->is_active ? '.archive' : '.reactivate')))
    <form method="POST" action="{{ route($entry->is_active ? 'issue.archive' : 'issue.reactivate', $entry->getKey()) }}" class="d-inline"
        data-issue-archive-action="{{ $entry->is_active ? 'archive' : 'reactivate' }}">
        @csrf
        <button type="submit" class="btn btn-sm btn-link">
            <i class="la {{ $entry->is_active ? 'la-archive' : 'la-undo' }}"></i>
            {{ $entry->is_active ? 'Archive' : 'Reactivate' }}
        </button>
    </form>
@endif

@push('after_scripts') @if (request()->ajax()) @endpush @endif
@basset(public_path('js/issue-archive.js'))
@if (!request()->ajax()) @endpush @endif
