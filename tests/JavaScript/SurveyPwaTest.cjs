const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const root = path.resolve(__dirname, '../..');
const origin = 'https://survey.example.test';
const absolute = value => new URL(typeof value === 'string' ? value : value.url, origin).href;
class LocalRequest extends Request {
    constructor(value, options) { super(absolute(value), options); }
}

function workerHarness() {
    const listeners = {};
    const storage = new Map();
    const calls = { fetched: [], skipped: 0, claimed: 0, warnings: [] };
    const state = {
        network: async () => new Response('network', { status: 200 }),
        failCacheWrite: false
    };
    const caches = {
        async open(name) {
            if (!storage.has(name)) storage.set(name, new Map());
            const entries = storage.get(name);
            return {
                async put(request, response) {
                    if (state.failCacheWrite) throw new Error('Quota exceeded');
                    entries.set(absolute(request), response.clone());
                },
                async match(request) { return entries.get(absolute(request))?.clone(); },
                async addAll(requests) {
                    const resources = requests.map(request => {
                        assert.equal(request.cache, 'reload');
                        const file = path.join(root, 'public', new URL(request.url).pathname);
                        assert.ok(fs.existsSync(file), `Missing shell asset: ${file}`);
                        return [request.url, new Response(fs.readFileSync(file))];
                    });
                    resources.forEach(([url, response]) => entries.set(url, response));
                }
            };
        },
        async keys() { return Array.from(storage.keys()); },
        async delete(name) { return storage.delete(name); }
    };
    const context = vm.createContext({
        URL, Headers, Response, Request: LocalRequest, caches,
        console: { warn: (...args) => calls.warnings.push(args) },
        fetch: async (request, options) => {
            calls.fetched.push({ request, options });
            return state.network(request);
        },
        self: {
            location: { origin },
            addEventListener: (name, handler) => { listeners[name] = handler; },
            skipWaiting: async () => { calls.skipped++; },
            clients: { claim: async () => { calls.claimed++; } }
        }
    });
    vm.runInContext(fs.readFileSync(path.join(root, 'public/sw-survey.js'), 'utf8'), context);
    const cacheName = vm.runInContext('CACHE_NAME', context);

    async function dispatch(name, extra = {}) {
        const tasks = [];
        let responsePromise;
        listeners[name]({
            ...extra,
            waitUntil: task => tasks.push(task),
            respondWith: task => { responsePromise = task; }
        });
        const response = responsePromise ? await responsePromise : undefined;
        await Promise.all(tasks);
        return response;
    }
    return { calls, state, storage, caches, cacheName, dispatch };
}

function surveyResponse(html = '<form><input type="hidden" name="_token" value="secret-token"></form>', options = {}) {
    const response = new Response(html, {
        status: options.status || 200,
        headers: {
            'Content-Type': 'text/html; charset=UTF-8',
            'X-Survey-Cacheable': options.cacheable === false ? '0' : '1',
            'Content-Length': '999',
            'Set-Cookie': 'XSRF-TOKEN=secret-token',
            ...options.headers
        }
    });
    Object.defineProperty(response, 'url', { value: options.url || origin + '/survey' });
    Object.defineProperty(response, 'redirected', { value: Boolean(options.redirected) });
    return response;
}

test('install precaches real shell resources, not the dynamic document or videos, and waits for safe activation', async () => {
    const h = workerHarness();
    await h.dispatch('install');
    const entries = h.storage.get(h.cacheName);
    assert.ok(entries.size > 10);
    assert.equal(entries.has(origin + '/survey'), false);
    assert.equal(entries.has(origin + '/assets/blue.mp4'), false);
    assert.equal(h.calls.skipped, 0);
    await h.dispatch('message', { data: { type: 'ACTIVATE_SURVEY_UPDATE' } });
    assert.equal(h.calls.skipped, 1);
});

test('activation removes obsolete survey caches only', async () => {
    const h = workerHarness();
    await h.caches.open('survey-cache-v2');
    await h.caches.open('employee-care-cache');
    await h.caches.open(h.cacheName);
    await h.dispatch('activate');
    assert.deepEqual(await h.caches.keys(), ['employee-care-cache', h.cacheName]);
    assert.equal(h.calls.claimed, 1);
});

test('normal network GET returns current HTML but persists no CSRF token or cookie', async () => {
    const h = workerHarness();
    h.state.network = async () => surveyResponse();
    const response = await h.dispatch('fetch', { request: new LocalRequest('/survey') });
    assert.match(await response.text(), /secret-token/);
    const cached = await (await h.caches.open(h.cacheName)).match('/survey');
    assert.doesNotMatch(await cached.text(), /secret-token/);
    assert.equal(cached.headers.has('Set-Cookie'), false);
    assert.equal(cached.headers.has('Content-Length'), false);
    assert.equal(cached.headers.get('X-Survey-Offline'), '1');
    assert.equal(h.calls.fetched[0].options.cache, 'no-store');
});

test('query states, flashed success/errors, non-HTML, redirects and failures never replace the normal document', async () => {
    const h = workerHarness();
    const cache = await h.caches.open(h.cacheName);
    await cache.put('/survey', new Response('normal form'));
    const scenarios = [
        ['/survey?thank_you=1', {}],
        ['/survey?other_state=1', {}],
        ['/survey', { cacheable: false }],
        ['/survey', { status: 500 }],
        ['/survey', { status: 404 }],
        ['/survey', { status: 206 }],
        ['/survey', { redirected: true }],
        ['/survey', { url: origin + '/survey?thank_you=1' }],
        ['/survey', { url: origin + '/admin/login' }],
        ['/survey', { headers: { 'Content-Type': 'application/json' } }]
    ];
    for (const [url, options] of scenarios) {
        h.state.network = async () => surveyResponse('transient state', options);
        await h.dispatch('fetch', { request: new LocalRequest(url) });
        assert.equal(await (await cache.match('/survey')).text(), 'normal form', url);
    }
});

test('offline navigation uses normal form or the installed offline fallback', async () => {
    const h = workerHarness();
    await h.dispatch('install');
    h.state.network = async () => { throw new Error('Offline'); };
    const fallback = await h.dispatch('fetch', { request: new LocalRequest('/survey') });
    assert.match(await fallback.text(), /Survey is offline/);
    const cache = await h.caches.open(h.cacheName);
    await cache.put('/survey', new Response('cached normal form'));
    const form = await h.dispatch('fetch', { request: new LocalRequest('/survey') });
    assert.equal(await form.text(), 'cached normal form');
});

test('POSTs, token refresh, admin pages, unrelated assets, third parties and Range requests bypass caching', async () => {
    const h = workerHarness();
    for (const request of [
        new LocalRequest('/survey', { method: 'POST' }),
        new LocalRequest('/survey/csrf-token'),
        new LocalRequest('/admin/dashboard'),
        new LocalRequest('/arta-form'),
        new LocalRequest('/public-assets/js/jquery.dataTables.min.js'),
        new LocalRequest('https://ui-avatars.com/api/?name=Staff'),
        new LocalRequest('/assets/blue.mp4', { headers: { Range: 'bytes=0-100' } })
    ]) {
        assert.equal(await h.dispatch('fetch', { request }), undefined);
    }
    assert.equal(h.calls.fetched.length, 0);
});

test('local avatars cache on success; 404s, redirects and partial responses do not', async () => {
    const h = workerHarness();
    const cache = await h.caches.open(h.cacheName);
    h.state.network = async () => new Response('avatar');
    await h.dispatch('fetch', { request: new LocalRequest('/storage/avatars/staff.webp') });
    h.state.network = async () => { throw new Error('Offline'); };
    assert.equal(await (await h.dispatch('fetch', { request: new LocalRequest('/storage/avatars/staff.webp') })).text(), 'avatar');
    for (const status of [404, 500, 206]) {
        h.state.network = async () => new Response('invalid', { status });
        const request = new LocalRequest(`/assets/avatars/${status}.png`);
        await h.dispatch('fetch', { request });
        assert.equal(await cache.match(request), undefined);
    }
    const redirect = new Response('redirected asset');
    Object.defineProperty(redirect, 'redirected', { value: true });
    h.state.network = async () => redirect;
    const request = new LocalRequest('/assets/avatars/redirect.png');
    await h.dispatch('fetch', { request });
    assert.equal(await cache.match(request), undefined);
});

test('runtime cache failure never replaces a successful network response with offline HTML', async () => {
    const h = workerHarness();
    h.state.failCacheWrite = true;
    h.state.network = async () => surveyResponse();
    const response = await h.dispatch('fetch', { request: new LocalRequest('/survey') });
    assert.match(await response.text(), /secret-token/);
    assert.equal(h.calls.warnings.length, 1);
});

function pageHarness({ online = true, token = '', missing = false, thankYouQuery = false, serviceWorker = false, standalone = false, secure = true } = {}) {
    const listeners = {};
    const notices = {};
    const calls = { submitted: 0, tokensAtSubmit: [], fetches: [], slides: [], updates: [], reloads: 0, helpShown: 0, resets: 0 };
    const timers = new Map();
    let timerId = 0;
    function element() {
        const classes = new Set(['is-hidden']);
        return { textContent: '', hidden: true, disabled: false, style: {}, attributes: {}, setAttribute(name, value) { this.attributes[name] = value; }, removeAttribute(name) { delete this.attributes[name]; }, addEventListener: () => {}, classList: {
            add: (...names) => names.forEach(name => classes.add(name)), remove: (...names) => names.forEach(name => classes.delete(name)),
            contains: name => classes.has(name),
            toggle: (name, force) => force ? classes.add(name) : classes.delete(name)
        } };
    }
    for (const id of ['survey-connectivity', 'survey-connectivity-message', 'retrySurveySubmission', 'installSurveyApp', 'installSurveyNow', 'surveyInstallHelp', 'surveyInstallHelpMessage', 'fullscreenSurvey', 'surveyFullscreenLabel', 'surveyFullscreenMessage', 'surveyAppTools', 'qr-timeout-slide', 'success-message', 'surveyHeader', 'staff-page-indicator', 'service-page-indicator']) {
        notices[id] = element();
        notices[id].addEventListener = (name, handler) => { listeners[id + ':' + name] = handler; };
    }
    notices.surveyAppTools.open = false;
    notices.surveyAppTools.contains = target => target === notices.surveyAppTools;
    const tokenInput = { value: token };
    const firstSlide = element();
    firstSlide.classList.add('active');
    const rating = element();
    const radio = { checked: true };
    const form = {
        action: origin + '/survey',
        dataset: { csrfUrl: origin + '/survey/csrf-token' },
        querySelector: () => tokenInput,
        querySelectorAll: selector => selector === 'input[required]'
            ? (missing ? [{ checkValidity: () => false, closest: () => firstSlide }] : [])
            : (selector === 'input[type="radio"]' ? [radio] : [rating]),
        addEventListener: (name, handler) => { listeners['form:' + name] = handler; },
        submit: () => { calls.submitted++; },
        reset: () => { calls.resets++; tokenInput.value = ''; }
    };
    const carousel = {
        querySelector: () => firstSlide,
        querySelectorAll: () => [firstSlide],
        addEventListener: (name, handler) => { listeners['carousel:' + name] = handler; }
    };
    const state = { network: async (url, options) => new Response(JSON.stringify(options.method === 'POST' ? { saved: true, message: 'Thank you!' } : { token: 'fresh-token' })) };
    let modalCreated = false;
    const modal = { show: () => { calls.helpShown++; }, hide: () => {} };
    const context = vm.createContext({
        URLSearchParams, AbortController,
        setTimeout: (fn, delay) => { const id = ++timerId; timers.set(id, { fn, delay }); return id; },
        clearTimeout: id => timers.delete(id),
        FormData: class {
            constructor(target) { assert.equal(target, form); calls.tokensAtSubmit.push(tokenInput.value); this.values = {}; }
            set(name, value) { this.values[name] = value; }
        },
        fetch: async (url, options) => { calls.fetches.push({ url, options }); return state.network(url, options); },
        navigator: { onLine: online },
        history: { replaceState: () => {} },
        document: {
            documentElement: {},
            querySelector: selector => selector === '.survey-form' ? form : { style: {} },
            querySelectorAll: selector => selector === '.carousel-indicators button' ? [] : [rating],
            getElementById: id => id === 'surveyCarousel' ? carousel : (notices[id] || null),
            addEventListener: (name, handler) => { listeners[name] = handler; }
        },
        window: {
            crypto: require('node:crypto').webcrypto,
            isSecureContext: secure,
            matchMedia: () => ({ matches: standalone, addEventListener: (name, handler) => { listeners['display-mode:' + name] = handler; } }),
            location: { search: thankYouQuery ? '?thank_you=1' : '', pathname: '/survey', reload: () => { calls.reloads++; } },
            addEventListener: (name, handler) => { listeners['window:' + name] = handler; }
        },
        bootstrap: {
            Carousel: class { to(index) { calls.slides.push(index); } },
            Modal: {
                getOrCreateInstance: () => { modalCreated = true; return modal; },
                getInstance: () => modalCreated ? modal : null
            }
        },
        $: () => ({ ready: () => {} })
    });
    if (serviceWorker) {
        context.navigator.serviceWorker = {
            controller: null,
            addEventListener: (name, handler) => { listeners['worker:' + name] = handler; },
            register: async (url, options) => {
                assert.equal(url, '/sw-survey.js');
                assert.equal(options.scope, '/survey');
                assert.equal(options.updateViaCache, 'none');
                return { waiting: null, addEventListener: () => {} };
            }
        };
    }
    vm.runInContext(fs.readFileSync(path.join(root, 'public/survey-assets/js/survey.js'), 'utf8'), context);
    return { context, state, calls, notices, tokenInput, rating, radio, listeners, firstSlide, timers };
}

test('offline submission preserves answers and offers manual retry without any network or POST', async () => {
    const h = pageHarness({ online: false });
    await vm.runInContext('submitForm()', h.context);
    assert.equal(h.calls.submitted, 0);
    assert.equal(h.calls.fetches.length, 0);
    assert.match(h.notices['survey-connectivity-message'].textContent, /not been confirmed as saved/);
    assert.equal(h.notices.retrySurveySubmission.classList.contains('is-hidden'), false);
});

test('cached form submits only after current token arrives; failure permits manual retry', async () => {
    const h = pageHarness();
    h.state.network = async () => { throw new Error('Server offline'); };
    await vm.runInContext('submitForm()', h.context);
    assert.equal(h.calls.submitted, 0);
    assert.equal(h.rating.style.pointerEvents, '');
    h.state.network = async (url, options) => new Response(JSON.stringify(options.method === 'POST' ? { saved: true } : { token: 'fresh-session-token' }));
    await vm.runInContext('submitForm()', h.context);
    assert.deepEqual(h.calls.tokensAtSubmit, ['fresh-session-token']);
    assert.equal(h.calls.fetches[1].options.cache, 'no-store');
    assert.equal(h.calls.fetches[1].options.credentials, 'same-origin');
    await vm.runInContext('submitForm()', h.context);
    assert.equal(h.calls.submitted, 0, 'never navigates using native form.submit');
    assert.equal(h.calls.fetches.filter(call => call.options.method === 'POST').length, 1);
});

test('incomplete carousel cannot submit, and offline thank-you URL cannot show successful completion', async () => {
    const incomplete = pageHarness({ missing: true });
    await vm.runInContext('submitForm()', incomplete.context);
    assert.equal(incomplete.calls.fetches.length, 0);
    assert.deepEqual(incomplete.calls.slides, [0]);
    const cached = pageHarness({ thankYouQuery: true });
    cached.listeners.DOMContentLoaded();
    assert.equal(cached.calls.slides.length, 0);
    const online = pageHarness({ token: 'live-token', thankYouQuery: true });
    online.listeners.DOMContentLoaded();
    assert.deepEqual(online.calls.slides, [7]);
});

test('worker activation waits until welcome or completed QR state, never during submission', () => {
    const h = pageHarness();
    h.context.updateMessages = h.calls.updates;
    vm.runInContext('surveyWorkerRegistration = { waiting: { postMessage: message => updateMessages.push(message) } }; surveyStarted = true; activateSurveyUpdateIfIdle();', h.context);
    assert.equal(h.calls.updates.length, 0);
    vm.runInContext('surveyStarted = false; activateSurveyUpdateIfIdle();', h.context);
    assert.equal(h.calls.updates.length, 1);
    assert.equal(h.calls.updates[0].type, 'ACTIVATE_SURVEY_UPDATE');
    vm.runInContext('surveyStarted = true; surveyCompleted = true;', h.context);
    h.notices['qr-timeout-slide'].classList.add('active');
    vm.runInContext('activateSurveyUpdateIfIdle();', h.context);
    assert.equal(h.calls.updates.length, 2);
    vm.runInContext('isSubmittingSurvey = true; activateSurveyUpdateIfIdle();', h.context);
    assert.equal(h.calls.updates.length, 2);
});

test('first controllerchange warms the normal document without reloading; thank-you state never warms it', async () => {
    const h = pageHarness({ token: 'current-token', serviceWorker: true });
    h.listeners.DOMContentLoaded();
    h.listeners['window:load']();
    h.listeners['worker:controllerchange']();
    assert.equal(h.calls.fetches[0].url, '/survey');
    assert.equal(h.calls.reloads, 0);
    const success = pageHarness({ token: 'current-token', serviceWorker: true, thankYouQuery: true });
    success.listeners.DOMContentLoaded();
    assert.equal(success.listeners['worker:controllerchange'], undefined);
});

test('manifest icons exist with the advertised PNG dimensions and scope stays /survey', () => {
    const manifest = JSON.parse(fs.readFileSync(path.join(root, 'public/manifest-survey.json'), 'utf8'));
    assert.equal(manifest.start_url, '/survey');
    assert.equal(manifest.scope, '/survey');
    assert.equal(manifest.display, 'standalone');
    for (const size of [192, 512]) {
        const icon = manifest.icons.find(item => item.sizes === `${size}x${size}`);
        assert.ok(icon);
        const png = fs.readFileSync(path.join(root, 'public', icon.src));
        assert.equal(png.readUInt32BE(16), size);
        assert.equal(png.readUInt32BE(20), size);
    }
});

test('visible install button explains unavailable prompts instead of silently doing nothing', async () => {
    const h = pageHarness();
    h.listeners.DOMContentLoaded();
    assert.equal(h.notices.installSurveyApp.hidden, false);
    await h.listeners['installSurveyApp:click']();
    assert.equal(h.calls.helpShown, 1);
    assert.match(h.notices.surveyInstallHelpMessage.textContent, /not offered installation yet/);
    assert.equal(h.notices.installSurveyNow.classList.contains('is-hidden'), true);

    const insecure = pageHarness({ secure: false });
    insecure.listeners.DOMContentLoaded();
    await insecure.listeners['installSurveyApp:click']();
    assert.match(insecure.notices.surveyInstallHelpMessage.textContent, /HTTPS/);
});

test('in-page install controls trigger the native prompt once, handle dismissal, and hide after installation', async () => {
    const h = pageHarness();
    h.listeners.DOMContentLoaded();
    await h.listeners['installSurveyApp:click']();
    let promptCalls = 0;
    let prevented = false;
    h.listeners['window:beforeinstallprompt']({
        preventDefault: () => { prevented = true; },
        prompt: async () => { promptCalls++; },
        userChoice: Promise.resolve({ outcome: 'dismissed' })
    });
    assert.equal(prevented, true);
    assert.equal(h.notices.installSurveyNow.classList.contains('is-hidden'), false);
    await h.listeners['installSurveyNow:click']();
    assert.equal(promptCalls, 1);
    assert.equal(h.notices.installSurveyApp.hidden, false);
    await h.listeners['installSurveyApp:click']();
    assert.equal(promptCalls, 1);

    h.listeners['window:appinstalled']();
    assert.equal(h.notices.installSurveyApp.hidden, true);
    const installed = pageHarness({ standalone: true });
    installed.listeners.DOMContentLoaded();
    assert.equal(installed.notices.installSurveyApp.hidden, true);
});

test('failed native install prompt leaves usable controls and an explanatory message', async () => {
    const h = pageHarness();
    h.listeners.DOMContentLoaded();
    h.listeners['window:beforeinstallprompt']({
        preventDefault: () => {},
        prompt: async () => { throw new Error('Browser rejected prompt'); },
        userChoice: Promise.resolve({ outcome: 'dismissed' })
    });
    await h.listeners['installSurveyApp:click']();
    assert.equal(h.notices.installSurveyApp.disabled, false);
    assert.equal(h.notices.installSurveyApp.hidden, false);
    assert.equal(h.calls.helpShown, 1);
    assert.match(h.notices.surveyInstallHelpMessage.textContent, /could not open installation/);
});

test('fullscreen button enters the entire document, exits, and follows browser-initiated exit', async () => {
    const h = pageHarness();
    const doc = h.context.document;
    let entries = 0;
    doc.documentElement.requestFullscreen = async function (options) {
        assert.equal(this, doc.documentElement);
        assert.equal(options.navigationUI, 'hide');
        entries++;
        doc.fullscreenElement = this;
        h.listeners.fullscreenchange();
    };
    doc.exitFullscreen = async function () {
        assert.equal(this, doc);
        doc.fullscreenElement = null;
        h.listeners.fullscreenchange();
    };
    h.listeners.DOMContentLoaded();
    assert.equal(entries, 0, 'never automatically requests fullscreen');
    assert.equal(h.notices.surveyFullscreenLabel.textContent, 'Enter Fullscreen');
    await h.listeners['fullscreenSurvey:click']();
    assert.equal(entries, 1);
    assert.equal(h.notices.surveyFullscreenLabel.textContent, 'Exit Fullscreen');
    assert.equal(h.notices.fullscreenSurvey.attributes['aria-pressed'], 'true');
    await h.listeners['fullscreenSurvey:click']();
    assert.equal(h.notices.surveyFullscreenLabel.textContent, 'Enter Fullscreen');
    await h.listeners['fullscreenSurvey:click']();
    doc.fullscreenElement = null;
    h.listeners.fullscreenchange();
    assert.equal(h.notices.fullscreenSurvey.attributes['aria-pressed'], 'false');
    assert.equal(h.calls.submitted, 0);
    assert.equal(h.calls.reloads, 0);
});

test('fullscreen unavailable or forbidden explains the limitation without changing answers', async () => {
    for (const enabled of [undefined, false]) {
        const h = pageHarness({ token: 'existing-token' });
        if (enabled === false) {
            h.context.document.fullscreenEnabled = false;
            h.context.document.documentElement.requestFullscreen = () => { throw new Error('must not request'); };
        }
        h.listeners.DOMContentLoaded();
        await h.listeners['fullscreenSurvey:click']();
        assert.equal(h.notices.surveyFullscreenMessage.hidden, false);
        assert.match(h.notices.surveyFullscreenMessage.textContent, /does not allow fullscreen/);
        assert.equal(h.notices.fullscreenSurvey.disabled, false);
        assert.equal(h.tokenInput.value, 'existing-token');
        assert.equal(h.calls.submitted, 0);
    }
});

test('fullscreen rejection reenables retry and ignores double taps while pending', async () => {
    const h = pageHarness();
    let rejectRequest;
    let requests = 0;
    h.context.document.documentElement.requestFullscreen = () => {
        requests++;
        return new Promise((resolve, reject) => { rejectRequest = reject; });
    };
    h.listeners.DOMContentLoaded();
    const pending = h.listeners['fullscreenSurvey:click']();
    assert.equal(requests, 1, 'request happens directly in the tap handler');
    assert.equal(h.notices.fullscreenSurvey.disabled, true);
    await h.listeners['fullscreenSurvey:click']();
    assert.equal(requests, 1);
    rejectRequest(new Error('Browser denied request'));
    await pending;
    assert.equal(h.notices.fullscreenSurvey.disabled, false);
    assert.equal(h.notices.surveyFullscreenMessage.hidden, false);
    assert.match(h.notices.surveyFullscreenMessage.textContent, /could not enter or exit fullscreen/);
});

test('fullscreen uses legacy WebKit methods when standard methods are unavailable', async () => {
    const h = pageHarness();
    const doc = h.context.document;
    doc.documentElement.webkitRequestFullscreen = function () {
        assert.equal(this, doc.documentElement);
        doc.webkitFullscreenElement = this;
        h.listeners.webkitfullscreenchange();
    };
    doc.webkitExitFullscreen = function () {
        assert.equal(this, doc);
        doc.webkitFullscreenElement = null;
        h.listeners.webkitfullscreenchange();
    };
    h.listeners.DOMContentLoaded();
    await h.listeners['fullscreenSurvey:click']();
    assert.equal(h.notices.surveyFullscreenLabel.textContent, 'Exit Fullscreen');
    await h.listeners['fullscreenSurvey:click']();
    assert.equal(h.notices.surveyFullscreenLabel.textContent, 'Enter Fullscreen');
});

test('confirmed save, timed reset, and next client all stay in the fullscreen document', async () => {
    const h = pageHarness({ token: 'old-token' });
    const doc = h.context.document;
    doc.fullscreenElement = doc.documentElement;
    h.listeners.DOMContentLoaded();
    assert.equal(h.calls.slides.length, 0, 'hidden success placeholder must not jump to thank-you');
    h.rating.classList.add('active', 'selected');
    await vm.runInContext('submitForm()', h.context);
    assert.deepEqual(h.calls.slides, [7]);
    assert.equal(h.calls.fetches[1].url, origin + '/survey');
    assert.equal(h.calls.fetches[1].options.method, 'POST');
    assert.equal(h.calls.fetches[1].options.headers.Accept, 'application/json');
    assert.equal(h.calls.fetches[1].options.credentials, 'same-origin');
    assert.equal(h.notices['success-message'].hidden, false);
    assert.equal(h.calls.submitted, 0);
    const resetTimer = Array.from(h.timers.values()).find(timer => timer.delay === 60000);
    assert.ok(resetTimer);
    resetTimer.fn();
    assert.deepEqual(h.calls.slides, [7, 0]);
    assert.equal(h.calls.resets, 1);
    assert.equal(h.radio.checked, false);
    assert.equal(h.rating.classList.contains('active'), false);
    assert.equal(h.rating.classList.contains('selected'), false);
    assert.equal(h.notices['success-message'].hidden, true);
    assert.equal(h.tokenInput.value, 'fresh-token');
    assert.equal(h.notices.surveyFullscreenLabel.textContent, 'Exit Fullscreen');
    assert.equal(doc.fullscreenElement, doc.documentElement);
    assert.equal(h.calls.reloads, 0);
    h.radio.checked = true;
    await vm.runInContext('submitForm()', h.context);
    assert.equal(h.calls.fetches.filter(call => call.options.method === 'POST').length, 2);
    assert.deepEqual(h.calls.slides, [7, 0, 7]);
});

test('Rate Again clears completion timer without navigation or stale success state', async () => {
    const h = pageHarness();
    h.listeners.DOMContentLoaded();
    await vm.runInContext('submitForm()', h.context);
    await vm.runInContext('resetSurvey()', h.context);
    assert.equal(Array.from(h.timers.values()).some(timer => timer.delay === 60000), false);
    assert.equal(vm.runInContext('surveyCompleted || surveyStarted || submissionFailed', h.context), false);
    assert.equal(h.calls.reloads, 0);
    const view = fs.readFileSync(path.join(root, 'resources/views/survey_form.blade.php'), 'utf8');
    assert.match(view, /onclick="resetSurvey\(\)"/);
    assert.doesNotMatch(view, /window\.location\.reload/);
});

test('failed POST never shows success or resets answers and permits manual retry', async () => {
    for (const response of [
        () => new Response(JSON.stringify({ saved: false, errors: { issue_id: ['Please select a valid service.'] } }), { status: 422 }),
        () => new Response(JSON.stringify({ message: 'CSRF expired' }), { status: 419 }),
        () => new Response('Server error', { status: 500 }),
        () => new Response(JSON.stringify({ saved: false })),
        () => { throw new Error('Connection lost'); }
    ]) {
        const h = pageHarness({ token: 'live-token' });
        h.listeners.DOMContentLoaded();
        h.state.network = async (url, options) => options.method === 'POST'
            ? response() : new Response(JSON.stringify({ token: 'fresh-token' }));
        await vm.runInContext('submitForm()', h.context);
        assert.equal(h.notices['success-message'].hidden, true);
        assert.equal(h.calls.slides.length, 0);
        assert.equal(h.calls.resets, 0);
        assert.equal(h.radio.checked, true);
        assert.equal(h.notices.retrySurveySubmission.classList.contains('is-hidden'), false);
        assert.equal(h.notices.retrySurveySubmission.disabled, false);
        assert.equal(Array.from(h.timers.values()).some(timer => timer.delay === 60000), false);
        h.state.network = async (url, options) => new Response(JSON.stringify(options.method === 'POST' ? { saved: true } : { token: 'fresh-token' }));
        await vm.runInContext('submitForm()', h.context);
        assert.equal(h.notices['success-message'].hidden, false);
    }
});

test('pending save prevents double submission and reset, and times out without claiming success', async () => {
    const h = pageHarness();
    h.listeners.DOMContentLoaded();
    h.state.network = async (url, options) => {
        if (options.method !== 'POST') return new Response(JSON.stringify({ token: 'fresh-token' }));
        return new Promise((resolve, reject) => {
            options.signal.addEventListener('abort', () => reject(new Error('Timed out')));
        });
    };
    const pending = vm.runInContext('submitForm()', h.context);
    // Allow the CSRF response body to resolve before asserting the pending POST.
    await new Promise(resolve => setImmediate(resolve));
    await vm.runInContext('submitForm(); resetSurvey();', h.context);
    assert.equal(h.calls.fetches.filter(call => call.options.method === 'POST').length, 1);
    assert.equal(h.calls.resets, 0);
    const timeout = Array.from(h.timers.values()).find(timer => timer.delay === 30000);
    assert.ok(timeout);
    timeout.fn();
    await pending;
    assert.equal(h.notices['success-message'].hidden, true);
    assert.equal(vm.runInContext('isSubmittingSurvey', h.context), false);
    assert.equal(h.calls.reloads, 0);
});

test('compact tools stay collapsed initially and close after actions, outside taps, Escape, and reset', async () => {
    const h = pageHarness();
    h.listeners.DOMContentLoaded();
    const tools = h.notices.surveyAppTools;
    assert.equal(tools.open, false);
    tools.open = true;
    h.listeners.click({ target: tools });
    assert.equal(tools.open, true);
    h.listeners.click({ target: {} });
    assert.equal(tools.open, false);
    tools.open = true;
    h.listeners.keydown({ key: 'Escape' });
    assert.equal(tools.open, false);
    tools.open = true;
    await h.listeners['fullscreenSurvey:click']();
    assert.equal(tools.open, false);
    assert.equal(h.notices.surveyFullscreenMessage.hidden, false, 'fullscreen fallback remains visible outside the collapsed menu');
    tools.open = true;
    await h.listeners['installSurveyApp:click']();
    assert.equal(tools.open, false);
    assert.equal(h.calls.helpShown, 1);
    tools.open = true;
    vm.runInContext('resetSurvey()', h.context);
    assert.equal(tools.open, false);
});

test('tools markup uses a closed native disclosure with an accessible icon-only opener', () => {
    const view = fs.readFileSync(path.join(root, 'resources/views/survey_form.blade.php'), 'utf8');
    assert.match(view, /<details id="surveyAppTools" class="survey-app-tools">/);
    assert.match(view, /<summary aria-label="Survey display and installation options"/);
    assert.match(view, /fas fa-ellipsis-v/);
    assert.match(view, /<\/details>\s*<p id="surveyFullscreenMessage"/);
});

test('E5 manual retries retain the submission UUID and the next client receives a new UUID', async () => {
    const h = pageHarness({ token: 'live-token' });
    h.state.network = async (url, options) => options.method === 'POST'
        ? new Response(JSON.stringify({ message: 'Retry' }), { status: 500 })
        : new Response(JSON.stringify({ token: 'live-token' }));
    await vm.runInContext('submitForm()', h.context);
    const first = h.calls.fetches.find(call => call.options.method === 'POST').options.body.values.submission_id;
    assert.match(first, /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/);
    await vm.runInContext('submitForm()', h.context);
    const posts = h.calls.fetches.filter(call => call.options.method === 'POST');
    assert.equal(posts[1].options.body.values.submission_id, first);
    vm.runInContext('resetSurvey()', h.context);
    assert.equal(vm.runInContext('surveySubmissionId', h.context), null);
});
