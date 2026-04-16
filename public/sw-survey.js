const CACHE_NAME = 'survey-cache-v2'; // Bumped version to force browser to re-cache

const APP_SHELL = [
    '/survey',
    '/offline-survey.html', // Make sure this file actually exists in your public/ folder!
    '/manifest-survey.json',
    '/assets/icons/icon-192x192.png',
    '/assets/icons/icon-512x512.png',
    '/survey-assets/css/bootstrap.min.css',
    '/survey-assets/css/fontawesome.min.css',
    '/survey-assets/css/css2.css',
    '/survey-assets/css/survey.css',
    '/survey-assets/js/jquery-3.6.0.min.js',
    '/survey-assets/js/bootstrap.bundle.min.js',
    '/survey-assets/js/browser@4.js',
    '/survey-assets/js/survey.js',
    '/assets/logo-with-seal.webp',
    '/assets/blue.webp',
    '/assets/blue.jpg',
    '/assets/blue.webm',
    '/assets/blue.mp4'
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
        url.pathname.startsWith('/assets/') || // Fixed to match the APP_SHELL paths
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
