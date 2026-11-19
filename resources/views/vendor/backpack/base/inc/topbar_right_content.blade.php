{{-- This file is used to store topbar (right) items --}}


{{-- <li class="nav-item d-md-down-none"><a class="nav-link" href="#"><i class="la la-bell"></i><span class="badge rounded-pill bg-danger">5</span></a></li>
<li class="nav-item d-md-down-none"><a class="nav-link" href="#"><i class="la la-list"></i></a></li>
<li class="nav-item d-md-down-none"><a class="nav-link" href="#"><i class="la la-map"></i></a></li> --}}
<li class="nav-item dropdown">
    <a class="nav-link notification-toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside" href="#" aria-expanded="false" aria-label="Notifications">
        <i class="la la-bell" style="font-size:1.4rem;"></i>
        <span class="badge bg-danger navbar-badge d-none" id="notificationCount">0</span>
    </a>

    <div class="dropdown-menu dropdown-menu-end dropdown-lg notification-dropdown"
         data-feed-url="{{ route('notifications.feed') }}"
         data-read-all-url="{{ route('notifications.read_all') }}"
         data-clear-all-url="{{ route('notifications.clear_all') }}"
         data-read-base-url="{{ url(config('backpack.base.route_prefix').'/notifications') }}"
         data-system-avatar="{{ asset('assets/icons/icon-192x192.png') }}">
        <div class="notification-dropdown__header">
            <div class="notification-dropdown__heading">
                <h2>Notifications</h2>
                <span id="notificationHeader" role="status" aria-live="polite">Checking for updates</span>
            </div>

            <div class="notification-dropdown__actions">
                <button class="notification-action" id="markAllNotificationsRead" type="button">
                    Mark all as read
                </button>

                <button
                    class="notification-action notification-action--danger"
                    type="button"
                    data-bs-toggle="modal"
                    data-bs-target="#clearAllNotificationsModal"
                    title="Clear all notifications"
                >
                    Clear all
                </button>
            </div>
        </div>

        <div id="notificationFeedback" class="notification-feedback" role="alert" hidden></div>
        <div id="notificationList" aria-label="Recent notifications" aria-busy="true">
            <div class="notification-empty" role="status">Loading notifications…</div>
        </div>

        <a href="{{ route('notifications.index') }}" class="notification-dropdown__footer">
            View all notifications <i class="la la-arrow-right" aria-hidden="true"></i>
        </a>
    </div>
</li>

{{-- Clear All Notifications Modal --}}
<div class="modal fade" id="clearAllNotificationsModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Clear Notifications</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body text-start">
                <p>Are you sure you want to clear all notifications?</p>
                <p class="text-muted small mb-0">This will permanently remove all notifications from your account.</p>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmClearAllNotifications">
                    Yes, Clear all
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        var modal = document.getElementById('clearAllNotificationsModal');
        if (modal) {
            document.body.appendChild(modal);
        }
    })();
</script>
