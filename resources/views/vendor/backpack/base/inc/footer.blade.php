@if(backpack_auth()->check())
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap4.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script>
// Backpack-PRO-free searchable dropdowns via Tom Select (MIT licensed).
window.hrfInitTomSelect = function (selectorOrEl, options) {
    options = Object.assign({}, options || {});
    var emptyOptionText = options.emptyOptionText;
    delete options.emptyOptionText;

    var els = typeof selectorOrEl === 'string'
        ? document.querySelectorAll(selectorOrEl)
        : [selectorOrEl];

    els.forEach(function (el) {
        if (!el || el.tomselect) return;

        if (emptyOptionText) {
            var blank = el.querySelector('option[value=""]');
            if (blank) { blank.textContent = emptyOptionText; }
        }

        new TomSelect(el, Object.assign({
            create: false,
            allowEmptyOption: true,
            sortField: { field: 'text', direction: 'asc' },
        }, options));
    });
};

window.hrfRebindTomSelect = function (selectEl, options) {
    if (!selectEl) return;
    if (selectEl.tomselect) {
        selectEl.tomselect.destroy();
    }
    window.hrfInitTomSelect(selectEl, options || {});
};
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const feedUrl = @json(route('notifications.feed'));
    const readAllUrl = @json(route('notifications.read_all'));
    const clearAllUrl = @json(route('notifications.clear_all'));
    const readOneBaseUrl = @json(url(config('backpack.base.route_prefix').'/notifications'));
    const csrfToken = @json(csrf_token());

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text ?? '';
        return div.innerHTML;
    }

    function updateBadge(count) {
        const countEl = document.getElementById('notificationCount');
        if (!countEl) return;

        countEl.textContent = count;

        if (count > 0) {
            countEl.classList.remove('d-none');
        } else {
            countEl.classList.add('d-none');
        }
    }

    function renderNotifications(data) {
        const headerEl = document.getElementById('notificationHeader');
        const listEl = document.getElementById('notificationList');

        updateBadge(data.unread_count);

        if (headerEl) {
            headerEl.textContent = `${data.unread_count} Notification${data.unread_count === 1 ? '' : 's'}`;
        }

        if (!listEl) return;

        if (!data.notifications || !data.notifications.length) {
            listEl.innerHTML = `<div class="dropdown-item text-muted">No notifications</div>`;
            return;
        }

        listEl.innerHTML = data.notifications.map(n => {
            const unreadClass = n.read_at ? '' : 'font-weight-bold bg-light';

            return `
                <a href="${escapeHtml(n.url || '#')}"
                   class="dropdown-item notification-item ${unreadClass}"
                   data-id="${escapeHtml(n.id)}">
                    <div>${escapeHtml(n.title)}</div>
                    <small class="text-muted d-block">${escapeHtml(n.message)}</small>
                    <small class="text-muted">${escapeHtml(n.created_at_human)}</small>
                </a>
            `;
        }).join('');
    }

    function fetchNotifications() {
        fetch(feedUrl, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        })
        .then(res => {
            if (!res.ok) throw new Error('Failed to fetch notifications');
            return res.json();
        })
        .then(data => {
            renderNotifications(data);
        })
        .catch(err => console.error('Notification fetch error:', err));
    }

    function markNotificationRead(id) {
        fetch(`${readOneBaseUrl}/${id}/read`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json'
            },
            credentials: 'same-origin'
        })
        .then(() => fetchNotifications())
        .catch(err => console.error('Mark read error:', err));
    }

    function clearAllNotifications() {
        return fetch(clearAllUrl, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        })
        .then(res => {
            if (!res.ok) throw new Error('Failed to clear notifications');
            return res.json();
        })
        .then(() => fetchNotifications())
        .catch(err => console.error('Clear all error:', err));
    }

    document.addEventListener('click', function (e) {
        const item = e.target.closest('.notification-item');
        if (item) {
            markNotificationRead(item.dataset.id);
        }

        if (e.target.id === 'markAllNotificationsRead') {
            e.preventDefault();

            fetch(readAllUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            })
            .then(() => fetchNotifications())
            .catch(err => console.error('Mark all read error:', err));
        }
    });

    const confirmClearAllBtn = document.getElementById('confirmClearAllNotifications');

    if (confirmClearAllBtn) {
        confirmClearAllBtn.addEventListener('click', function () {
            clearAllNotifications().then(() => {
                $('#clearAllNotificationsModal').modal('hide');
            });
        });
    }

    fetchNotifications();
    setInterval(fetchNotifications, 10000);
});
</script>
@endif

@include(backpack_view('inc.hr-float-widget'))
