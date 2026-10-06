const CACHE_PREFIX = 'survey-cache-';
const CACHE_NAME = CACHE_PREFIX + 'v7';

// Required, stable resources only. The dynamic document is cached after a normal GET.
const APP_SHELL = [
    '/offline-survey.html',
    '/manifest-survey.json',
    '/assets/icons/icon-192x192.png',
    '/assets/icons/icon-512x512.png',
    '/public-assets/css/bootstrap.min.css',
    '/public-assets/css/fontawesome.min.css',
    '/public-assets/css/css2.css',
    '/public-assets/webfonts/fa-solid-900.woff2',
    '/public-assets/webfonts/fa-regular-400.woff2',
    '/public-assets/webfonts/fa-brands-400.woff2',
    '/survey-assets/css/survey.css',
    '/public-assets/js/jquery-3.6.0.min.js',
    '/public-assets/js/bootstrap.bundle.min.js',
    '/survey-assets/js/survey.js',
    '/assets/logo-with-seal.webp',
    '/assets/blue.webp',
    '/assets/blue.jpg'
];

self.addEventListener('install', event => {
    event.waitUntil(caches.open(CACHE_NAME).then(cache => cache.addAll(
        APP_SHELL.map(path => new Request(path, { cache: 'reload' }))
    )));
    // Updates wait until the page signals a safe boundary between surveys.
});

self.addEventListener('message', event => {
    if (event.data && event.data.type === 'ACTIVATE_SURVEY_UPDATE') {
        event.waitUntil(self.skipWaiting());
    }
});

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys => Promise.all(
            keys.filter(key => key.startsWith(CACHE_PREFIX) && key !== CACHE_NAME)
                .map(key => caches.delete(key))
        )).then(() => self.clients.claim())
    );
});

async function cacheSurveyDocument(request, response) {
    const url = new URL(request.url);
    const responseUrl = new URL(response.url || request.url);
    if (url.search || response.status !== 200 || response.redirected || responseUrl.origin !== self.location.origin ||
        responseUrl.pathname !== '/survey' || responseUrl.search ||
        response.headers.get('X-Survey-Cacheable') !== '1' ||
        !(response.headers.get('Content-Type') || '').includes('text/html')) {
        return;
    }

    // Offline HTML is public UI only: never persist a session's CSRF credential.
    const html = (await response.clone().text()).replace(
        /<input\b[^>]*\bname=["']_token["'][^>]*>/gi,
        '<input type="hidden" name="_token" value="">'
    );
    const headers = new Headers(response.headers);
    ['Content-Length', 'Content-Encoding', 'Set-Cookie', 'Vary'].forEach(name => headers.delete(name));
    headers.set('X-Survey-Offline', '1');
    const cache = await caches.open(CACHE_NAME);
    await cache.put('/survey', new Response(html, { status: 200, headers }));
}

function isSurveyAsset(path) {
    return APP_SHELL.includes(path) ||
        /^\/survey-assets\/.*\.(css|js)$/i.test(path) ||
        /^\/public-assets\/webfonts\/fa-(solid-900|regular-400|brands-400)\.(woff2?|ttf|eot|svg)$/i.test(path) ||
        /^\/(storage|assets)\/avatars\/.*\.(png|jpe?g|webp|gif|avif)$/i.test(path) ||
        path === '/assets/blue.webm' || path === '/assets/blue.mp4';
}

self.addEventListener('fetch', event => {
    const request = event.request;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin) return;

    // Network-first for the main survey page
    if (url.pathname === '/survey') {
        event.respondWith(fetch(request, { cache: 'no-store' }).then(response => {
            event.waitUntil(cacheSurveyDocument(request, response).catch(error => {
                console.warn('Survey document could not be cached:', error);
            }));
            return response;
        }).catch(async () => {
            const cache = await caches.open(CACHE_NAME);
            return (await cache.match('/survey')) || (await cache.match('/offline-survey.html'));
        }));
        return;
    }

    // Partial video responses cannot be stored by Cache.put(); the poster works offline.
    if (!isSurveyAsset(url.pathname) || request.headers.has('Range')) return;

    event.respondWith(caches.open(CACHE_NAME).then(async cache => {
        const cached = await cache.match(request);
        if (cached) return cached;

        const response = await fetch(request);
        if (response.status === 200 && !response.redirected) {
            event.waitUntil(cache.put(request, response.clone()).catch(error => {
                console.warn('Survey asset could not be cached:', error);
            }));
        }
        return response;
    }));
});
