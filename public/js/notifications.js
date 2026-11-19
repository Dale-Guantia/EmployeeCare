(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        const panel = document.querySelector('.notification-dropdown');
        const list = document.getElementById('notificationList');
        if (!panel || !list) return;

        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const events = {
            ticket_created: ['New ticket', 'blue', 'la-ticket-alt'],
            ticket_commented: ['Comment', 'blue', 'la-comment'],
            ticket_assigned: ['Assignment', 'purple', 'la-user-check'],
            ticket_status_changed: ['Status update', 'slate', 'la-exchange-alt'],
            reassignment_requested: ['Approval needed', 'amber', 'la-random'],
            reassignment_accepted: ['Approved', 'green', 'la-check-circle'],
            reassignment_rejected: ['Declined', 'red', 'la-times-circle'],
            ticket_due_soon: ['Due soon', 'amber', 'la-clock'],
            ticket_overdue: ['Overdue', 'red', 'la-exclamation-circle']
        };
        const statusTones = { resolved: 'green', pending: 'amber', unassigned: 'slate', reopened: 'blue' };
        let previousData;

        function element(tag, className, text) {
            const node = document.createElement(tag);
            node.className = className;
            if (text !== undefined) node.textContent = text;
            return node;
        }

        function localUrl(value, fallback) {
            try {
                const url = new URL(value, window.location.href);
                if (url.origin === window.location.origin && ['http:', 'https:'].includes(url.protocol)) return url.href;
            } catch (_) { /* Malformed historical notification links use the safe fallback. */ }
            return fallback;
        }

        function emptyState(title, description, icon) {
            const state = element('div', 'notification-empty');
            state.setAttribute('role', 'status');
            const symbol = element('i', 'la ' + icon);
            symbol.setAttribute('aria-hidden', 'true');
            state.appendChild(symbol);
            state.appendChild(element('strong', '', title));
            state.appendChild(element('span', '', description));
            return state;
        }

        function feedback(message) {
            const notice = document.getElementById('notificationFeedback');
            notice.textContent = message || '';
            notice.hidden = !message;
        }

        function notificationItem(notification) {
            const actor = notification.actor || { name: 'Employee Care', is_system: true };
            const event = Object.hasOwn(events, notification.type) ? events[notification.type] : ['Update', 'slate', 'la-bell'];
            const tone = notification.type === 'ticket_status_changed'
                ? (Object.hasOwn(statusTones, notification.status_code) ? statusTones[notification.status_code] : event[1]) : event[1];
            const unread = !notification.read_at;
            const item = element('a', 'notification-item notification-item--' + tone + (unread ? ' is-unread' : ''));
            item.setAttribute('href', localUrl(notification.url || '#', '#'));
            item.setAttribute('data-id', notification.id);

            const avatar = element('span', 'notification-avatar' + (actor.is_system ? ' notification-avatar--system' : ''));
            avatar.setAttribute('aria-hidden', 'true');
            const name = actor.name || 'Former user';
            const initials = name.trim().split(/\s+/u).slice(0, 2).map(part => Array.from(part)[0] || '').join('').toUpperCase();
            avatar.appendChild(element('span', 'notification-avatar__initials', initials));
            const photoUrl = localUrl(actor.is_system ? panel.dataset.systemAvatar : actor.avatar_url || '', null);
            if ((actor.is_system || actor.avatar_url) && photoUrl) {
                const photo = element('img', 'notification-avatar__photo');
                photo.setAttribute('alt', '');
                photo.setAttribute('src', photoUrl);
                photo.setAttribute('loading', 'lazy');
                photo.addEventListener('error', function () { photo.remove(); });
                avatar.appendChild(photo);
            }
            const eventBadge = element('span', 'notification-avatar__event');
            eventBadge.setAttribute('aria-hidden', 'true');
            eventBadge.appendChild(element('i', 'la ' + event[2]));
            avatar.appendChild(eventBadge);
            item.appendChild(avatar);

            const body = element('span', 'notification-item__body');
            const meta = element('span', 'notification-item__meta');
            meta.appendChild(element('span', 'notification-tag', event[0]));
            if (unread) meta.appendChild(element('span', 'notification-unread', 'Unread'));
            body.appendChild(meta);
            body.appendChild(element('span', 'notification-item__sender', name));
            body.appendChild(element('span', 'notification-item__title', notification.title || 'Notification'));
            body.appendChild(element('span', 'notification-item__message', notification.message || ''));
            body.appendChild(element('span', 'notification-item__time', notification.created_at_human || 'Just now'));
            item.appendChild(body);
            return item;
        }

        function render(data) {
            const signature = JSON.stringify(data);
            list.setAttribute('aria-busy', 'false');
            if (signature === previousData) return;
            previousData = signature;
            const count = Number(data.unread_count) || 0;
            const badge = document.getElementById('notificationCount');
            badge.textContent = count > 99 ? '99+' : count;
            badge.classList[count ? 'remove' : 'add']('d-none');
            document.getElementById('notificationHeader').textContent = count ? count + ' unread' : 'You’re all caught up';
            document.querySelector('.notification-toggle').setAttribute('aria-label', 'Notifications, ' + count + ' unread');
            document.getElementById('markAllNotificationsRead').disabled = count === 0;
            const scroll = list.scrollTop;
            const focusedId = document.activeElement?.closest('.notification-item')?.dataset.id;
            const notifications = Array.isArray(data.notifications) ? data.notifications : [];
            list.replaceChildren(...(notifications.length ? notifications.map(notificationItem)
                : [emptyState('No notifications yet', 'Ticket updates and reminders will appear here.', 'la-bell')]));
            list.scrollTop = scroll;
            if (focusedId) Array.from(list.querySelectorAll('.notification-item')).find(item => item.dataset.id === focusedId)?.focus();
        }

        async function request(url, method) {
            const response = await fetch(url, {
                method: method || 'GET', credentials: 'same-origin', keepalive: method === 'POST',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken }
            });
            if (!response.ok) throw new Error('Notification request failed');
            return response.json();
        }

        async function refresh() {
            try {
                render(await request(panel.dataset.feedUrl));
            } catch (error) {
                list.setAttribute('aria-busy', 'false');
                if (!previousData) list.replaceChildren(emptyState('Unable to load notifications', 'Open all notifications below or try again shortly.', 'la-exclamation-circle'));
                console.error('Notification fetch error:', error);
            }
        }

        list.addEventListener('click', function (event) {
            const item = event.target.closest('.notification-item');
            if (item) request(panel.dataset.readBaseUrl + '/' + encodeURIComponent(item.dataset.id) + '/read', 'POST')
                .then(refresh).catch(error => { feedback('Unable to mark this notification as read. Please try again.'); console.error('Mark read error:', error); });
        });
        document.getElementById('markAllNotificationsRead').addEventListener('click', function () {
            feedback();
            request(panel.dataset.readAllUrl, 'POST').then(refresh).catch(error => {
                feedback('Unable to mark notifications as read. Please try again.');
                console.error('Mark all read error:', error);
            });
        });
        document.getElementById('confirmClearAllNotifications').addEventListener('click', async function () {
            this.disabled = true;
            feedback();
            try {
                await request(panel.dataset.clearAllUrl, 'DELETE');
                await refresh();
                bootstrap.Modal.getOrCreateInstance(document.getElementById('clearAllNotificationsModal')).hide();
            } catch (error) {
                feedback('Unable to clear notifications. Please try again.');
                console.error('Clear all error:', error);
            } finally {
                this.disabled = false;
            }
        });

        refresh();
        setInterval(refresh, 10000);
    });
})();
