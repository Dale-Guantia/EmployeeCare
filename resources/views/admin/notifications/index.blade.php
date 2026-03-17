@extends(backpack_view('blank'))

@section('header')
<section class="container-fluid">
    <h2>
        <span class="text-capitalize">{{ $title }}</span>
    </h2>
</section>

<section class="container-fluid">
    <div class="mb-3 d-flex justify-content-end">
        <button
            class="btn btn-danger btn-sm"
            type="button"
            data-toggle="modal"
            data-target="#clearAllNotificationsPageModal"
        >
            <i class="la la-trash"></i> Clear all notifications
        </button>
    </div>
</section>
@endsection

@section('content')
<div class="card">
    <div class="card-body">
        @forelse($notifications as $notification)
            <a href="{{ $notification->data['url'] ?? '#' }}"
               class="d-block p-3 mb-2 border rounded {{ $notification->read_at ? '' : 'bg-light' }}">
                <strong>{{ $notification->data['title'] ?? 'Notification' }}</strong>
                <div>{{ $notification->data['message'] ?? '' }}</div>
                <small class="text-muted">{{ $notification->created_at->diffForHumans() }}</small>
            </a>
        @empty
            <p class="text-muted mb-0">No notifications found.</p>
        @endforelse

        <div class="mt-3 d-flex justify-content-center">
            {{ $notifications->links() }}
        </div>
    </div>
</div>

{{-- Page Clear All Modal --}}
<div class="modal fade" id="clearAllNotificationsPageModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Clear Notifications</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body text-left">
                <p>Are you sure you want to clear all notifications?</p>
                <p class="text-muted small mb-0">This will permanently remove all notifications from your account.</p>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmClearAllNotificationsPage">
                    Yes, Clear all
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('after_scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('clearAllNotificationsPageModal');
    if (modal) {
        document.body.appendChild(modal);
    }

    const clearAllUrl = @json(route('notifications.clear_all'));
    const csrfToken = @json(csrf_token());
    const btn = document.getElementById('confirmClearAllNotificationsPage');

    if (btn) {
        btn.addEventListener('click', function () {
            fetch(clearAllUrl, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            })
            .then(() => {
                $('#clearAllNotificationsPageModal').modal('hide');
                window.location.reload();
            })
            .catch(err => console.error(err));
        });
    }
});
</script>
@endpush

