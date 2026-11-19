const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const dom = require('./dom-harness.cjs');

const script = fs.readFileSync(path.join(__dirname, '../../public/js/issue-archive.js'), 'utf8');

function harness(action = 'archive', confirm = async () => true) {
    const document = dom('<form method="POST" action="/issue/1/' + action + '" data-issue-archive-action="' + action
        + '"><input type="hidden" name="_token" value="csrf-fixture"><button type="submit">Action</button></form>');
    const form = document.querySelector('form');
    const button = form.querySelector('button');
    const dialogs = [], submissions = [], alerts = [];
    let handler, listeners = 0;
    document.addEventListener = (event, fn) => { assert.equal(event, 'submit'); handler = fn; listeners++; };
    form.submit = () => submissions.push({ method: form.getAttribute('method'),
        action: form.getAttribute('action'), token: form.querySelector('input').value });
    const window = { alert: message => alerts.push(message) };
    const context = vm.createContext({ document, window,
        swal: async options => { dialogs.push(options); return confirm(); }
    });
    vm.runInContext(script, context);
    return { form, button, dialogs, submissions, alerts,
        submit: (target = form) => handler({ target, preventDefault() {} }),
        reloadScript: () => vm.runInContext(script, context), listeners: () => listeners };
}

test('cancelling the archive confirmation sends no request and restores the button', async () => {
    const h = harness('archive', async () => false);
    await h.submit();
    assert.equal(h.dialogs[0].title, 'Archive issue?');
    assert.match(h.dialogs[0].text, /Tickets and Surveys will keep this issue/);
    assert.deepEqual(Array.from(h.dialogs[0].buttons), ['Cancel', 'Archive']);
    assert.deepEqual(h.submissions, []);
    assert.equal(h.button.disabled, false);
});

test('confirmed archive and reactivate submit the original POST form and CSRF token once', async () => {
    for (const action of ['archive', 'reactivate']) {
        const h = harness(action);
        await h.submit();
        assert.equal(h.dialogs[0].title, action === 'archive' ? 'Archive issue?' : 'Reactivate issue?');
        assert.deepEqual(h.submissions, [{ method: 'POST', action: '/issue/1/' + action, token: 'csrf-fixture' }]);
        assert.equal(h.button.disabled, true);
        await h.submit();
        assert.equal(h.submissions.length, 1);
        assert.equal(h.dialogs.length, 1);
    }
});

test('repeated clicks while the dialog is open cannot produce duplicate confirmations or requests', async () => {
    let resolve;
    const answer = new Promise(done => { resolve = done; });
    const h = harness('archive', () => answer);
    const first = h.submit();
    await h.submit();
    assert.equal(h.dialogs.length, 1);
    assert.equal(h.submissions.length, 0);
    resolve(true);
    await first;
    assert.equal(h.submissions.length, 1);
});

test('loading the script again after table redraw does not duplicate the listener', async () => {
    const h = harness();
    h.reloadScript();
    assert.equal(h.listeners(), 1);
    await h.submit();
    assert.equal(h.submissions.length, 1);
});

test('a failed dialog leaves the issue unchanged and permits retry', async () => {
    const h = harness('archive', async () => { throw new Error('Dialog unavailable'); });
    await h.submit();
    assert.equal(h.submissions.length, 0);
    assert.equal(h.button.disabled, false);
    assert.match(h.alerts[0], /Could not confirm/);
});

test('other forms keep their normal submission behavior', async () => {
    const h = harness();
    await h.submit(dom('<form></form>').querySelector('form'));
    assert.equal(h.dialogs.length, 0);
    assert.equal(h.submissions.length, 0);
});


test('issue archive accepts an empty optional reason and still submits once', async () => {
    const h = harness('archive', async () => '');
    await h.submit();
    assert.equal(h.submissions.length, 1);
    assert.equal(h.form.children.at(-1).name, 'reason');
    assert.equal(h.form.children.at(-1).value, '');
});
