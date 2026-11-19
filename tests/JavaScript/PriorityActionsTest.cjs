const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const dom = require('./dom-harness.cjs');

const script = fs.readFileSync(path.join(__dirname, '../../public/js/priority-actions.js'), 'utf8');

function harness(action, responses, confirm = true, withTable = true, withNoty = false) {
    const document = dom('<meta name="csrf-token" content="csrf-fixture"><button data-priority-action="' + action
        + '" data-action-url="/priority/1' + (action === 'delete' ? '' : '/' + action)
        + '" data-impact-url="/priority/1/delete-impact" data-return-url="/priority" data-table-id="crudTable"><i></i></button>');
    document.querySelector('meta').content = 'csrf-fixture';
    const button = document.querySelector('button');
    const requests = [], dialogs = [], draws = [], pages = [], redirects = [], notifications = [];
    let handler, listeners = 0;
    document.addEventListener = (event, fn) => { handler = fn; listeners++; };
    const table = { rows: () => ({ count: () => 1 }), page: value => pages.push(value), draw: value => draws.push(value) };
    const window = { crud: withTable ? { tables: { crudTable: table } } : {}, location: { assign: url => redirects.push(url) } };
    const context = vm.createContext({ document, window,
        Noty: withNoty ? function (options) { notifications.push(options); this.show = () => {}; } : undefined,
        swal: async options => { dialogs.push(options); return confirm; },
        fetch: async (url, options) => {
            requests.push({ url, options });
            const result = responses.shift();
            if (result instanceof Error) throw result;
            return { ok: !result.status, status: result.status || 200, json: async () => result.body };
        }
    });
    vm.runInContext(script, context);
    return { button, requests, dialogs, draws, pages, redirects, notifications,
        click: () => handler({ target: button.querySelector('i'), preventDefault() {} }),
        reloadScript: () => vm.runInContext(script, context), listeners: () => listeners };
}

test('permanent delete shows counts first, sends CSRF, and refreshes only after success', async () => {
    const h = harness('delete', [{ body: { issues: 0, tickets: 0 } }, { body: 1 }]);
    await h.click();
    assert.match(h.dialogs[0].text, /unused, unprotected/);
    assert.match(h.dialogs[0].text, /references will never be cleared/);
    assert.equal(h.requests[0].options.method, 'GET');
    assert.equal(h.requests[1].options.method, 'DELETE');
    assert.equal(h.requests[1].options.headers['X-CSRF-TOKEN'], 'csrf-fixture');
    assert.equal(h.requests[1].options.credentials, 'same-origin');
    assert.deepEqual(h.draws, [false]);
    assert.deepEqual(h.pages, ['previous']);
    assert.equal(h.button.disabled, false);
});

test('cancelling a delete performs no mutation', async () => {
    const h = harness('delete', [{ body: { issues: 0, tickets: 0 } }], false);
    await h.click();
    assert.equal(h.requests.length, 1);
    assert.deepEqual(h.draws, []);
    assert.equal(h.button.disabled, false);
});

test('a refused delete leaves the row intact and shows the server notification', async () => {
    const h = harness('delete', [{ body: { issues: 0, tickets: 0 } }, { body: { error: ['Record is still referenced.'] } }]);
    await h.click();
    assert.deepEqual(h.draws, []);
    assert.equal(h.dialogs.at(-1).icon, 'error');
    assert.equal(h.dialogs.at(-1).text, 'Record is still referenced.');
});

test('a permission failure while loading counts never proceeds to delete', async () => {
    const h = harness('delete', [{ status: 403 }]);
    await h.click();
    assert.equal(h.requests.length, 1);
    assert.match(h.dialogs[0].text, /permission/);
    assert.deepEqual(h.draws, []);
});

test('archive and reactivate use POST and do not request delete counts', async () => {
    for (const action of ['archive', 'reactivate']) {
        const h = harness(action, [{ body: { message: 'State changed.' } }]);
        await h.click();
        assert.equal(h.requests.length, 1);
        assert.equal(h.requests[0].options.method, 'POST');
        assert.match(h.requests[0].url, new RegExp('/' + action + '$'));
        assert.deepEqual(h.draws, [false]);
        assert.deepEqual(h.pages, []);
    }
});

test('show-page actions return to the list and loading the script twice does not duplicate handlers', async () => {
    const h = harness('reactivate', [{ body: { message: 'State changed.' } }], true, false);
    h.reloadScript();
    assert.equal(h.listeners(), 1);
    await h.click();
    assert.deepEqual(h.redirects, ['/priority']);
});

test('a network failure re-enables the button and keeps the row', async () => {
    const h = harness('archive', [new Error('Network unavailable')]);
    await h.click();
    assert.equal(h.button.disabled, false);
    assert.deepEqual(h.draws, []);
    assert.equal(h.dialogs.at(-1).icon, 'error');
});


test('reference counts block deletion during preview without a destructive request', async () => {
    const h = harness('delete', [{ body: { issues: 2, tickets: 3 } }]);
    await h.click();
    assert.equal(h.requests.length, 1);
    assert.match(h.dialogs[0].text, /2 issues, 3 tickets/);
    assert.match(h.dialogs[0].text, /Archive it to preserve history/);
    assert.equal(h.dialogs[0].icon, 'error');
    assert.deepEqual(h.draws, []);
});

test('delete previews handle office transfer history and use the record label', async () => {
    const h = harness('delete', [{ body: { issues: 0, users: 0, tickets: 0, ticket_reassignment_requests: 2 } }]);
    h.button.setAttribute('data-record-label', 'division');
    await h.click();
    assert.equal(h.requests.length, 1);
    assert.match(h.dialogs[0].text, /2 ticket reassignment requests/);
    assert.match(h.dialogs[0].text, /this division/);
});

test('archive refusal shows the server blocker as an escaped persistent error bubble', async () => {
    const h = harness('archive', [{ status: 422, body: { errors: { is_active: ['Replace <default> first.'] } } }], true, true, true);
    await h.click();
    assert.equal(h.notifications[0].type, 'error');
    assert.equal(h.notifications[0].timeout, false);
    assert.match(h.notifications[0].text, /&lt;default&gt;/);
    assert.deepEqual(h.draws, []);
    assert.equal(h.button.disabled, false);
});


test('optional reasons are submitted without treating an empty reason as cancellation', async () => {
    for (const reason of ['Service retired', '']) {
        const h = harness('archive', [{ body: { message: 'Archived.' } }], reason);
        await h.click();
        assert.equal(h.requests.length, 1);
        assert.equal(JSON.parse(h.requests[0].options.body).reason, reason);
    }
});
