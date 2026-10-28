const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

function harness(fetch, timer = setTimeout) {
    const input = { value: 'Leave?', focus() {} };
    const thread = { innerHTML: '', querySelectorAll: () => [] };
    const sendBtn = {};
    const sandbox = { window: { AbortController }, fetch, URL, setTimeout: timer, clearTimeout,
        document: { getElementById: id => ({ input, thread, sendBtn })[id] } };
    vm.createContext(sandbox);
    vm.runInContext(fs.readFileSync(path.join(__dirname, '../../public/js/hr-chat-core.js'), 'utf8'), sandbox);
    const chat = sandbox.window.HrChatCore.create({ ids: { input: 'input', thread: 'thread', sendBtn: 'sendBtn' },
        endpoints: { ask: '/nested/ask', conversationMessages: '/nested/conversations/:id/messages', feedback: '/feedback', clearHistory: '/clear' },
        strings: { connectionError: 'Connection failed', thanksHelpful: 'Thanks', notedUnhelpful: 'Noted' }, csrf: 'fixture' });
    const rendered = [];
    chat.ensureMarkdown = () => Promise.resolve();
    chat._appendUser = text => rendered.push({ user: text });
    chat._appendAi = (text, sources, logId, isError) => rendered.push({ text, sources, logId, isError });
    chat._appendTyping = () => ({ remove() {} });
    return { chat, rendered, input, sendBtn };
}
const response = (data, status = 200) => ({ ok: status >= 200 && status < 300, status, json: async () => data });
const deferred = () => { let resolve; const promise = new Promise(r => { resolve = r; }); return { resolve, promise }; };

test('send disables repeated requests and includes JSON/session/subdirectory contract', async () => {
    const pending = deferred();
    const calls = [];
    const h = harness((url, options) => { calls.push({ url, options }); return pending.promise; });
    const request = h.chat.send();
    h.chat.send();
    h.chat.ask('duplicate');
    assert.equal(calls.length, 1);
    assert.equal(calls[0].url, '/nested/ask');
    assert.equal(calls[0].options.headers.Accept, 'application/json');
    assert.deepEqual(JSON.parse(calls[0].options.body), { question: 'Leave?', conversation_id: null });
    assert.equal(h.sendBtn.disabled, true);
    pending.resolve(response({ answer: 'Synthetic answer', conversation_id: 12, log_id: 1, sources: ['Policy'] }));
    await request;
    assert.equal(h.chat.currentConversationId, 12);
    assert.equal(h.sendBtn.disabled, false);
    assert.equal(h.rendered[1].isError, undefined);
});

for (const status of [401, 419, 422, 429, 500, 503]) {
    test(`HTTP ${status} never renders a sourced success or feedback`, async () => {
        const h = harness(async () => response({ answer: 'SECRET body', sources: ['Fake policy'], log_id: 8 }, status));
        await h.chat.send();
        assert.equal(h.rendered[1].isError, true);
        assert.equal(h.rendered[1].sources.length, 0);
        assert.equal(h.rendered[1].logId, null);
        assert.equal(h.rendered[1].text.includes('SECRET'), false);
        assert.equal(h.chat.loading, false);
    });
}

test('network, malformed JSON and empty answer errors restore controls', async () => {
    for (const fetch of [async () => { throw new Error('network'); }, async () => ({ ok: true, json: async () => { throw new Error('JSON'); } }), async () => response({ answer: '' })]) {
        const h = harness(fetch);
        await h.chat.send();
        assert.equal(h.rendered[1].isError, true);
        assert.equal(h.chat.loading, false);
    }
});

test('new conversation discards old slow answer and its returned conversation identity', async () => {
    const pending = deferred();
    const h = harness(() => pending.promise);
    const request = h.chat.send();
    h.chat.startNewConversation();
    pending.resolve(response({ answer: 'Old answer', conversation_id: 9 }));
    await request;
    assert.equal(h.rendered.length, 1);
    assert.equal(h.chat.currentConversationId, null);
    assert.equal(h.chat.loading, false);
});

test('clear view stays local and preserves the conversation for the next question', async () => {
    const calls = [];
    const h = harness(async (url, options) => {
        calls.push({ url, options });
        return response({ answer: 'Next answer', conversation_id: 7 });
    });
    h.chat.setConversation(7);
    h.chat.el('thread').innerHTML = 'Previous messages';
    let clears = 0;
    h.chat.cfg.onClearView = () => { clears++; };
    for (let i = 0; i < 25; i++) h.chat.clearView();
    assert.equal(calls.length, 0);
    assert.equal(clears, 25);
    assert.equal(h.chat.el('thread').innerHTML, '');
    assert.equal(h.chat.getConversation(), 7);

    await h.chat.send();
    assert.equal(calls.length, 1);
    assert.equal(JSON.parse(calls[0].options.body).conversation_id, 7);
    assert.equal(h.rendered[1].text, 'Next answer');
});

test('a hung request times out, aborts transport and unlocks sending without retrying', async () => {
    let signal; let calls = 0;
    const h = harness((url, options) => { calls++; signal = options.signal; return new Promise(() => {}); },
        (callback, delay) => setTimeout(callback, delay >= 30000 ? 0 : delay));
    await h.chat.send();
    assert.equal(signal.aborted, true);
    assert.equal(calls, 1);
    assert.equal(h.chat.loading, false);
    assert.equal(h.rendered[1].isError, true);
});

test('out-of-order transcript loads cannot overwrite the newest selection', async () => {
    const a = deferred(); const b = deferred();
    const h = harness(url => url.includes('/1/') ? a.promise : b.promise);
    const first = h.chat.loadConversation(1);
    await new Promise(setImmediate);
    const second = h.chat.loadConversation(2);
    await new Promise(setImmediate);
    b.resolve(response({ messages: [{ question: 'Second', answer: 'Second answer', id: 2 }] }));
    await second;
    a.resolve(response({ messages: [{ question: 'First', answer: 'First answer', id: 1 }] }));
    await first;
    assert.equal(h.chat.currentConversationId, 2);
    assert.equal(h.rendered.length, 2);
    assert.equal(h.rendered[0].user, 'Second');
});

test('failed transcript fetch unlocks sending and retains prior identity', async () => {
    const h = harness(async () => response({}, 401));
    h.chat.setConversation(7);
    await h.chat.loadConversation(9);
    assert.equal(h.chat.currentConversationId, 7);
    assert.equal(h.chat.loading, false);
    assert.equal(h.rendered[0].isError, true);
});

test('failed feedback is retryable and never shows thanks', async () => {
    const h = harness(async () => response({ ok: false }));
    let title;
    const group = { innerHTML: 'Helpful?' };
    const button = { closest: () => group, setAttribute: (name, value) => { title = value; } };
    await h.chat.giveFeedback(1, 1, button);
    assert.equal(group.innerHTML, 'Helpful?');
    assert.equal(button.disabled, false);
    assert.match(title, /not saved/);
});

test('successful clear resets conversation identity; failed clear retains it', async () => {
    for (const status of [200, 503]) {
        const h = harness(async () => response({ ok: true }, status));
        h.chat._confirm = async () => true;
        h.chat.setConversation(5);
        try { await h.chat.clearHistory('Clear?'); } catch (e) { assert.equal(status, 503); }
        assert.equal(h.chat.currentConversationId, status === 200 ? null : 5);
    }
});
