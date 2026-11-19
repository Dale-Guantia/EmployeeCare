const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const dom = require('./dom-harness.cjs');

function harness(states, pending = '1') {
    const document = dom('<div id="policy-processing-status" data-pending="' + pending + '" data-status-url="/employee-care/hr-policy-documents/ingestion-status"></div>');
    const timers = []; const reloads = []; const requests = [];
    vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../public/js/policy-status.js'), 'utf8'), {
        document, window: { crud: { table: { ajax: { reload: (...args) => reloads.push(args) } } }, addEventListener() {} },
        setTimeout: fn => { timers.push(fn); return timers.length; }, clearTimeout() {},
        AbortSignal: { timeout: () => undefined },
        fetch: async (url, options) => { requests.push({ url, options }); const state = states.shift(); return { ok: !state.error, json: async () => state }; }
    });
    return { document, timers, reloads, requests };
}

test('pending and processing documents refresh the table, preserve pagination, and stop when finished', async () => {
    const h = harness([{ pending: 1, processing: 0, version: 'a' }, { pending: 0, processing: 1, version: 'b' }, { pending: 0, processing: 0, version: 'c' }]);
    for (let i = 0; i < 3; i++) await h.timers.shift()();
    assert.equal(h.requests.length, 3);
    assert.equal(h.requests[0].options.credentials, 'same-origin');
    assert.deepEqual(h.reloads, [[null, false], [null, false], [null, false]]);
    assert.equal(h.timers.length, 0);
    assert.match(h.document.getElementById('policy-processing-status').textContent, /finished/);
});

test('hidden tabs defer requests, and failures stop polling with a useful status', async () => {
    const h = harness([{ error: true }]);
    h.document.hidden = true; await h.timers.shift()();
    assert.equal(h.requests.length, 0);
    h.document.hidden = false; await h.timers.shift()();
    assert.equal(h.timers.length, 0);
    assert.match(h.document.getElementById('policy-processing-status').textContent, /Refresh this page/);
});

test('an idle policy list does not keep polling', () => {
    assert.equal(harness([], '0').timers.length, 0);
});
