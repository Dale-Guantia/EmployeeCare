const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const dom = require('./dom-harness.cjs');
const source = fs.readFileSync(path.join(__dirname, '../../public/js/notifications.js'), 'utf8');

async function harness(data) {
    const document = dom(`<meta name="csrf-token" content="test-csrf">
        <a class="notification-toggle"></a><span id="notificationCount"></span>
        <div class="notification-dropdown" data-feed-url="/feed" data-read-all-url="/read-all"
          data-clear-all-url="/clear-all" data-read-base-url="/notifications" data-system-avatar="/assets/icons/icon-192x192.png">
          <span id="notificationHeader"></span><button id="markAllNotificationsRead"></button>
          <div id="notificationFeedback" hidden></div><div id="notificationList"></div></div>
        <div id="clearAllNotificationsModal"></div><button id="confirmClearAllNotifications"></button>`);
    let currentData = data, failure = false, timer, modalHides = 0;
    const requests = [], errors = [];
    vm.runInNewContext(source, {
        document, URL, window: { location: new URL('http://localhost/employee-care/reports') },
        setInterval: fn => { timer = fn; }, console: { error: (...args) => errors.push(args) },
        bootstrap: { Modal: { getOrCreateInstance: () => ({ hide: () => modalHides++ }) } },
        fetch: async (url, options) => {
            requests.push({ url, options });
            if (failure) return { ok: false };
            return { ok: true, json: async () => currentData };
        }
    });
    const settle = () => new Promise(resolve => setImmediate(resolve));
    document.fire('DOMContentLoaded');
    await settle();
    return { document, requests, errors, settle, poll: async () => { await timer(); await settle(); },
        update: data => { currentData = data; }, fail: value => { failure = value; }, modalHides: () => modalHides };
}

function notification(overrides = {}) {
    return { id: 'notice-1', type: 'ticket_commented', title: 'New Ticket Comment', message: 'A ticket was updated.',
        url: '/employee-care/ticket/1/show', read_at: null, created_at_human: '2 minutes ago',
        actor: { name: 'Jamie Cruz', avatar_url: '/storage/avatars/jamie.png', is_system: false }, ...overrides };
}

test('comments show profile photo, sender, readable message, colored event and unread marker', async () => {
    const h = await harness({ unread_count: 1, notifications: [notification()] });
    const item = h.document.querySelector('.notification-item');
    assert.match(item.className, /notification-item--blue.*is-unread/);
    assert.equal(item.querySelector('img').getAttribute('src'), 'http://localhost/storage/avatars/jamie.png');
    assert.equal(item.querySelector('.notification-item__sender').textContent, 'Jamie Cruz');
    assert.equal(item.querySelector('.notification-tag').textContent, 'Comment');
    assert.equal(item.querySelector('.notification-unread').textContent, 'Unread');
    const eventBadge = item.querySelector('.notification-avatar__event');
    assert.equal(eventBadge.name, 'span');
    assert.equal(eventBadge.querySelector('i').classList.contains('la-comment'), true);
    assert.equal(eventBadge.classList.contains('la'), false);
    assert.equal(h.document.getElementById('notificationHeader').textContent, '1 unread');
    item.querySelector('img').fire('error');
    assert.equal(item.querySelector('img'), null);
    assert.equal(item.querySelector('.notification-avatar__initials').textContent, 'JC');
});

test('system reminders use supplied application icon with urgency colors; status colors use permanent codes', async () => {
    const h = await harness({ unread_count: 0, notifications: [
        notification({ id: 'overdue', type: 'ticket_overdue', read_at: 'today', actor: { name: 'Employee Care', is_system: true } }),
        notification({ id: 'resolved', type: 'ticket_status_changed', status_code: 'resolved', title: 'A renamed status' }),
        notification({ id: 'pending', type: 'ticket_status_changed', status_code: 'pending' })
    ] });
    const items = h.document.querySelectorAll('.notification-item');
    assert.match(items[0].className, /notification-item--red/);
    assert.equal(items[0].querySelector('img').getAttribute('src'), 'http://localhost/assets/icons/icon-192x192.png');
    assert.equal(items[0].querySelector('.notification-unread'), null);
    assert.match(items[1].className, /notification-item--green/);
    assert.match(items[2].className, /notification-item--amber/);
});

test('untrusted notification text and unsafe URLs cannot inject markup or active links', async () => {
    const h = await harness({ unread_count: 1, notifications: [notification({
        title: '<script>alert(1)</script>', message: '<img src=x onerror=alert(1)>', url: 'javascript:alert(1)',
        type: 'toString', actor: { name: '<b>Injected</b>', avatar_url: 'https://external.example/photo.png' }
    })] });
    const item = h.document.querySelector('.notification-item');
    assert.equal(item.querySelector('script'), null);
    assert.equal(item.querySelector('img'), null);
    assert.equal(item.querySelector('b'), null);
    assert.equal(item.querySelector('.notification-item__message').textContent, '<img src=x onerror=alert(1)>');
    assert.equal(item.getAttribute('href'), '#');
    assert.equal(item.querySelector('.notification-tag').textContent, 'Update');
});

test('empty state and capped badge update; unchanged polling preserves focused notification', async () => {
    const h = await harness({ unread_count: 120, notifications: [notification()] });
    assert.equal(h.document.getElementById('notificationCount').textContent, '99+');
    const item = h.document.querySelector('.notification-item');
    item.focus();
    await h.poll();
    assert.equal(h.document.querySelector('.notification-item'), item);
    h.update({ unread_count: 0, notifications: [] });
    await h.poll();
    assert.equal(h.document.getElementById('notificationCount').classList.contains('d-none'), true);
    assert.equal(h.document.getElementById('markAllNotificationsRead').disabled, true);
    assert.match(h.document.getElementById('notificationList').textContent, /No notifications yet/);
});

test('read one, read all and clear send authenticated requests and refresh the feed', async () => {
    const h = await harness({ unread_count: 1, notifications: [notification()] });
    h.document.getElementById('notificationList').fire('click', { target: h.document.querySelector('.notification-item__message') });
    await h.settle();
    const read = h.requests.find(request => request.url === '/notifications/notice-1/read');
    assert.equal(read.options.method, 'POST');
    assert.equal(read.options.keepalive, true);
    assert.equal(read.options.headers['X-CSRF-TOKEN'], 'test-csrf');
    h.document.getElementById('markAllNotificationsRead').click();
    await h.settle();
    assert.equal(h.requests.find(request => request.url === '/read-all').options.method, 'POST');
    h.update({ unread_count: 0, notifications: [] });
    h.document.getElementById('confirmClearAllNotifications').click();
    await h.settle();
    assert.equal(h.requests.find(request => request.url === '/clear-all').options.method, 'DELETE');
    assert.equal(h.modalHides(), 1);
    assert.match(h.document.getElementById('notificationList').textContent, /No notifications yet/);
});

test('failed clear keeps confirmation open and stale notifications survive a failed poll', async () => {
    const h = await harness({ unread_count: 1, notifications: [notification()] });
    h.fail(true);
    h.document.getElementById('confirmClearAllNotifications').click();
    await h.settle();
    assert.equal(h.modalHides(), 0);
    assert.equal(h.document.getElementById('confirmClearAllNotifications').disabled, false);
    assert.match(h.document.getElementById('notificationFeedback').textContent, /Unable to clear/);
    assert.equal(h.document.getElementById('notificationFeedback').hidden, false);
    await h.poll();
    assert.equal(h.document.querySelectorAll('.notification-item').length, 1);
    assert.equal(h.errors.length, 2);
});
