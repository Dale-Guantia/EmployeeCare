const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const dom = require('./dom-harness.cjs');

const script = fs.readFileSync(path.join(__dirname, '../../public/js/office-archive.js'), 'utf8');

function harness(entity = 'department', action = 'archive', confirm = async () => true, response = {}, withTable = true) {
    const document = dom('<form method="POST" action="/' + entity + '/2/' + action
        + '" data-return-url="/' + entity + '" data-table-id="crudTable" data-office-label="' + entity + '" data-office-archive-action="' + action
        + '"><input type="hidden" name="_token" value="office-token"><button type="submit">Action</button></form>');
    const form = document.querySelector('form');
    const button = form.querySelector('button');
    form.action = form.getAttribute('action');
    const dialogs = [], requests = [], alerts = [], notifications = [], draws = [], redirects = [], stored = [];
    let handler, listeners = 0;
    document.addEventListener = (event, fn) => { assert.equal(event, 'submit'); handler = fn; listeners++; };
    const context = vm.createContext({ document,
        window: { alert: message => alerts.push(message),
            crud: withTable ? { tables: { crudTable: { draw: value => draws.push(value) } } } : {},
            location: { assign: url => redirects.push(url) },
            localStorage: { setItem: (key, value) => stored.push({ key, value }) }
        },
        FormData: class { constructor(source) { this.token = source.querySelector('input').value; } append(name, value) { this[name] = value; } },
        Noty: function (options) { notifications.push(options); this.show = () => {}; },
        swal: async options => { dialogs.push(options); return confirm(); },
        fetch: async (url, options) => {
            requests.push({ url, options });
            if (response instanceof Error) throw response;
            return { ok: !response.status || response.status < 400, status: response.status || 200,
                json: async () => response.body || { message: 'Office updated.' } };
        }
    });
    vm.runInContext(script, context);
    return { form, button, dialogs, requests, alerts, notifications, draws, redirects, stored,
        submit: () => handler({ target: form, preventDefault() {} }),
        reloadScript: () => vm.runInContext(script, context), listeners: () => listeners };
}

test('cancelling retirement preserves the office and explains the blockers', async () => {
    const h = harness('department', 'archive', async () => false);
    await h.submit();
    assert.equal(h.dialogs[0].title, 'Archive department?');
    assert.match(h.dialogs[0].text, /Active users, unresolved tickets, and pending transfers/);
    assert.deepEqual(h.requests, []);
    assert.equal(h.button.disabled, false);
});

test('department and division changes send one CSRF-protected request and display success', async () => {
    for (const entity of ['department', 'division']) {
        for (const action of ['archive', 'reactivate']) {
            const h = harness(entity, action);
            await h.submit();
            assert.equal(h.dialogs[0].title, (action === 'archive' ? 'Archive ' : 'Reactivate ') + entity + '?');
            assert.equal(h.requests[0].url, '/' + entity + '/2/' + action);
            assert.equal(h.requests[0].options.method, 'POST');
            assert.equal(h.requests[0].options.body.token, 'office-token');
            assert.equal(h.requests[0].options.headers.Accept, 'application/json');
            assert.equal(h.requests[0].options.credentials, 'same-origin');
            assert.equal(h.notifications[0].type, 'success');
            assert.deepEqual(h.draws, [false]);
            await h.submit();
            assert.equal(h.dialogs.length, 1);
            assert.equal(h.requests.length, 1);
        }
    }
});

test('table redraws and clicks while the confirmation is open do not duplicate requests', async () => {
    let resolve;
    const answer = new Promise(done => { resolve = done; });
    const h = harness('division', 'archive', () => answer);
    h.reloadScript();
    assert.equal(h.listeners(), 1);
    const first = h.submit();
    await h.submit();
    assert.equal(h.dialogs.length, 1);
    assert.equal(h.requests.length, 0);
    resolve(true);
    await first;
    assert.equal(h.requests.length, 1);
});

test('a failed confirmation does not retire the office and allows retry', async () => {
    const h = harness('department', 'archive', async () => { throw new Error('Dialog failed'); });
    await h.submit();
    assert.equal(h.requests.length, 0);
    assert.equal(h.button.disabled, false);
    assert.equal(h.notifications[0].type, 'error');
    assert.equal(h.notifications[0].timeout, false);
});

test('blocked retirement displays every blocker in a persistent red notification without redirecting', async () => {
    const message = 'Cannot archive: 3 active user accounts, 2 unresolved tickets, and 1 pending transfer.';
    const h = harness('division', 'archive', async () => true, { status: 422, body: { errors: { is_active: [message] } } });
    await h.submit();
    assert.equal(h.notifications[0].type, 'error');
    assert.equal(h.notifications[0].text, message);
    assert.equal(h.notifications[0].timeout, false);
    assert.deepEqual(h.draws, []);
    assert.deepEqual(h.redirects, []);
    assert.equal(h.button.disabled, false);
});

test('notification content is escaped and the protected survey department reason is displayed', async () => {
    const message = 'This department is required by the survey. <img src=x onerror=alert(1)>';
    const h = harness('department', 'archive', async () => true, { status: 422, body: { errors: { is_active: [message] } } });
    await h.submit();
    assert.match(h.notifications[0].text, /required by the survey/);
    assert.doesNotMatch(h.notifications[0].text, /<img/);
    assert.match(h.notifications[0].text, /&lt;img/);
});

test('network and permission failures show errors and allow another attempt', async () => {
    for (const response of [new Error('Network down'), { status: 403, body: {} }]) {
        const h = harness('division', 'archive', async () => true, response);
        await h.submit();
        assert.equal(h.notifications[0].type, 'error');
        assert.equal(h.button.disabled, false);
        assert.deepEqual(h.draws, []);
    }
});

test('a successful show-page action carries its success notification back to the list', async () => {
    const h = harness('division', 'reactivate', async () => true, {}, false);
    await h.submit();
    assert.deepEqual(h.redirects, ['/division']);
    assert.equal(h.stored[0].key, 'backpack_alerts');
    assert.deepEqual(JSON.parse(h.stored[0].value), { success: ['Office updated.'] });
});


test('office archive captures an optional reason and accepts an empty reason', async () => {
    for (const reason of ['Office retired', '']) {
        const h = harness('department', 'archive', async () => reason);
        await h.submit();
        assert.equal(h.requests.length, 1);
        assert.equal(h.requests[0].options.body.reason, reason);
    }
});

test('reactivation confirmation lists the children becoming available', async () => {
    const h = harness('department', 'reactivate', async () => false);
    h.form.setAttribute('data-reactivation-summary', 'Divisions becoming available: Records. Issues becoming available: Certificates.');
    await h.submit();
    assert.match(h.dialogs[0].text, /Records/);
    assert.match(h.dialogs[0].text, /Certificates/);
    assert.equal(h.requests.length, 0);
});
