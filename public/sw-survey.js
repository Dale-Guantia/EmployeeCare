const CACHE_NAME = 'survey-cache-v1';

const APP_SHELL = [
    '/survey',
    '/offline-survey.html',
    '/manifest-survey.json',
    '/icons/icon-192x192.png',
    '/icons/icon-512x512.png',
    '/survey-assets/css/bootstrap.min.css',
    '/survey-assets/css/fontawesome.min.css',
    '/survey-assets/css/css2.css',
    '/survey-assets/css/survey.css',
    '/survey-assets/js/jquery-3.6.0.min.js',
    '/survey-assets/js/bootstrap.bundle.min.js',
    '/survey-assets/js/browser@4.js',
    '/survey-assets/js/survey.js',
    '/storage/assets/logo-with-seal.webp',
    '/storage/assets/blue.webp',
    '/storage/assets/blue.jpg',
    '/storage/assets/blue.webm',
    '/storage/assets/blue.mp4'
];

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(cache => cache.addAll(APP_SHELL))
    );
    self.skipWaiting();
});

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys =>
            Promise.all(
                keys
                    .filter(key => key !== CACHE_NAME)
                    .map(key => caches.delete(key))
            )
        )
    );
    self.clients.claim();
});

self.addEventListener('fetch', event => {
    const request = event.request;
    const url = new URL(request.url);

    // Do NOT cache POST, PUT, PATCH, DELETE, etc.
    if (event.request.method !== 'GET') {
        return;
    }

    if (url.pathname === '/survey/submit') {
        return;
    }

    // Only handle same-origin requests
    if (url.origin !== self.location.origin) {
        return;
    }

    // Network-first for the main survey page
    if (url.pathname === '/survey') {
        event.respondWith(
            fetch(request)
                .then(response => {
                    const responseClone = response.clone();
                    caches.open(CACHE_NAME).then(cache => {
                        cache.put('/survey', responseClone);
                    });
                    return response;
                })
                .catch(() =>
                    caches.match('/survey').then(cached =>
                        cached || caches.match('/offline-survey.html')
                    )
                )
        );
        return;
    }

    // Cache-first for local static assets
    if (
        url.pathname.startsWith('/survey-assets/') ||
        url.pathname.startsWith('/storage/assets/') ||
        url.pathname.startsWith('/icons/') ||
        url.pathname === '/manifest-survey.json' ||
        url.pathname === '/offline-survey.html'
    ) {
        event.respondWith(
            caches.match(request).then(cached => {
                if (cached) {
                    return cached;
                }

                return fetch(request).then(response => {
                    const responseClone = response.clone();
                    caches.open(CACHE_NAME).then(cache => {
                        cache.put(request, responseClone);
                    });
                    return response;
                });
            })
        );
    }
});
