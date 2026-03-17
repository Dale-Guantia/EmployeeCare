{{-- This file is used to store topbar (right) items --}}


{{-- <li class="nav-item d-md-down-none"><a class="nav-link" href="#"><i class="la la-bell"></i><span class="badge badge-pill badge-danger">5</span></a></li>
<li class="nav-item d-md-down-none"><a class="nav-link" href="#"><i class="la la-list"></i></a></li>
<li class="nav-item d-md-down-none"><a class="nav-link" href="#"><i class="la la-map"></i></a></li> --}}
<li class="nav-item dropdown">
    <a class="nav-link" data-toggle="dropdown" href="#" aria-expanded="false">
        <i class="la la-bell" style="font-size:1.4rem;"></i>
        <span class="badge badge-danger navbar-badge d-none" id="notificationCount">0</span>
    </a>

    <div class="dropdown-menu dropdown-menu-right dropdown-lg" style="min-width: 350px;">
        <div class="dropdown-header d-flex justify-content-between align-items-center">
            <span id="notificationHeader">0 Notifications</span>

            <div class="d-flex align-items-center">
                <button class="btn btn-sm btn-link p-0 mr-2" id="markAllNotificationsRead" type="button">
                    Mark all as read
                </button>

                <button
                    class="btn btn-sm btn-link text-danger p-0"
                    type="button"
                    data-toggle="modal"
                    data-target="#clearAllNotificationsModal"
                    title="Clear all notifications"
                >
                    Clear all
                </button>
            </div>
        </div>

        <div class="dropdown-divider"></div>

        <div id="notificationList" style="max-height: 350px; overflow-y: auto;">
            <div class="dropdown-item text-muted">No notifications</div>
        </div>

        <div class="dropdown-divider"></div>

        <a href="{{ route('notifications.index') }}" class="dropdown-item dropdown-footer">
            View all notifications
        </a>
    </div>
</li>

{{-- Clear All Notifications Modal --}}
<div class="modal fade" id="clearAllNotificationsModal" tabindex="-1" role="dialog" aria-hidden="true">
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
