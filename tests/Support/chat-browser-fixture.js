(function () {
    'use strict';
    var conversations = [{ id: 1, title: 'Wellness leave', is_starred: false, last_message_at: '2026-10-14T12:00:00Z' },
        { id: 2, title: '<img src=x onerror=alert(1)> benefits', is_starred: false, last_message_at: '2026-10-14T11:00:00Z' }];
    var messages = { 1: [{ id: 1, question: 'Wellness leave?', answer: '**Five** days in this synthetic policy.', sources: ['Fixture leave policy'] }],
        2: [{ id: 2, question: 'Benefits?', answer: 'Synthetic benefits answer.', sources: ['Fixture benefits policy'] }] };
    var nextId = 3;
    window.fixtureCalls = [];
    window.fetch = function (url, options) {
        options = options || {};
        window.fixtureCalls.push({ url: String(url), method: options.method || 'GET' });
        var route = new URL(url, location.href).pathname;
        var mode = document.getElementById('fixture-mode');
        var value = mode ? mode.value : 'normal';
        var payload = options.body ? JSON.parse(options.body) : {};
        var match = route.match(/conversations\/(\d+)/);
        var id = match ? Number(match[1]) : null;
        return new Promise(function (resolve, reject) {
            setTimeout(function () {
                if (options.signal && options.signal.aborted) { reject(new Error('aborted')); return; }
                if (value === 'network') { reject(new Error('synthetic network failure')); return; }
                var status = /^\d+$/.test(value) ? Number(value) : 200;
                var data;
                if (route.endsWith('/ask')) {
                    id = payload.conversation_id || nextId++;
                    if (!messages[id]) { messages[id] = []; conversations.push({ id: id, title: payload.question, is_starred: false, last_message_at: new Date().toISOString() }); }
                    var answer = value === 'xss' ? '**Literal:** <img src=x onerror="window.injected=true"> [bad](javascript:alert(1)) [data](data:text/html,test) ![tracker](https://invalid.example/image.png) [safe](https://example.com) ñ 中文' : 'Synthetic answer for **' + payload.question + '**.';
                    data = { answer: answer, sources: ['Fixture <img src=x onerror=alert(1)> policy'], log_id: nextId++, conversation_id: id };
                    if (status === 200) messages[id].push({ id: data.log_id, question: payload.question, answer: answer, sources: data.sources });
                } else if (route.endsWith('/feedback')) data = { ok: status === 200 };
                else if (route.endsWith('/clear-history')) { if (status === 200) { conversations = []; messages = {}; } data = { ok: status === 200 }; }
                else if (route.endsWith('/messages')) data = { id: id, messages: messages[id] || [] };
                else if (id && options.method === 'PATCH') {
                    if (status === 200) conversations.forEach(function (c) { if (c.id === id) Object.assign(c, payload); });
                    data = { ok: status === 200 };
                } else if (id && options.method === 'DELETE') {
                    if (status === 200) { conversations = conversations.filter(function (c) { return c.id !== id; }); delete messages[id]; }
                    data = { ok: status === 200 };
                } else if (route.endsWith('/conversations')) data = conversations;
                else if (route.endsWith('/history')) data = [];
                else data = {};
                resolve({ ok: status >= 200 && status < 300, status: status, json: function () { return value === 'malformed' ? Promise.reject(new Error('bad JSON')) : Promise.resolve(data); } });
            }, value === 'slow' ? 4000 : 100);
        });
    };
    document.addEventListener('DOMContentLoaded', function () {
        var toolbar = document.createElement('div');
        toolbar.style.cssText = 'position:fixed;top:0;right:0;z-index:99999;background:#fff;padding:4px;border:1px solid #333';
        toolbar.innerHTML = '<label>Fixture response <select id="fixture-mode"><option>normal</option><option>slow</option><option>xss</option><option>network</option><option>malformed</option><option>401</option><option>419</option><option>422</option><option>429</option><option>503</option></select></label> <button id="fixture-security">Check Markdown safety</button> <output id="fixture-result"></output>';
        document.body.appendChild(toolbar);
        document.getElementById('fixture-security').addEventListener('click', function () {
            HrChatCore.loadMarked().then(function () {
                var box = document.createElement('div');
                box.innerHTML = HrChatCore.renderMarkdown('**bold** <img src=x onerror=alert(1)> [bad](javascript:alert(1)) [encoded](jav&#x61;script:alert(1)) [data](data:text/html,test) ![image](https://invalid.example/tracker.png) [safe](https://example.com) ñ 中文');
                var unsafe = box.querySelector('img,script,iframe,[onclick],[onerror],a[href^="javascript:"],a[href^="data:"]');
                document.getElementById('fixture-result').textContent = !unsafe && box.querySelector('strong') && box.querySelector('a[href="https://example.com/"]') ? 'Markdown DOM checks passed' : 'Markdown DOM checks FAILED';
            }).catch(function () { document.getElementById('fixture-result').textContent = 'Markdown CDN unavailable; check blocked'; });
        });
    });
})();
