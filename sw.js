/**
 * Service Worker — GSB SINIYAT
 * Cache: static assets only. Payments require network.
 */
const CACHE_NAME = 'gsb-siniyat-v1';

const STATIC_ASSETS = [
    '/assets/css/style.css',
    '/assets/js/lang.js',
    '/assets/js/idle-timer.js',
    '/assets/js/app.js',
    '/assets/img/logo.png',
    '/lang/fr.json',
    '/lang/en.json',
    '/offline.html'
];

// Install: cache static assets
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => cache.addAll(STATIC_ASSETS))
            .then(() => self.skipWaiting())
    );
});

// Activate: clean old caches
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys =>
            Promise.all(keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k)))
        ).then(() => self.clients.claim())
    );
});

// Fetch strategy:
// - Static assets: cache-first
// - API / payment endpoints: network-only
// - Other pages: network-first with offline fallback
self.addEventListener('fetch', event => {
    const url = new URL(event.request.url);

    // Network-only for write operations (payments, login, logout, API mutations)
    if (url.pathname.startsWith('/api/') ||
        url.pathname === '/logout.php' ||
        url.pathname === '/login.php' ||
        (url.pathname.startsWith('/secretary/payments') && event.request.method === 'POST') ||
        event.request.method === 'POST') {
        return; // Let browser handle (no cache)
    }

    // Cache-first for static assets
    if (STATIC_ASSETS.some(a => url.pathname === a) ||
        url.hostname.includes('cdn.jsdelivr.net')) {
        event.respondWith(
            caches.match(event.request).then(cached =>
                cached || fetch(event.request).then(res => {
                    const clone = res.clone();
                    caches.open(CACHE_NAME).then(c => c.put(event.request, clone));
                    return res;
                })
            )
        );
        return;
    }

    // Network-first for pages
    event.respondWith(
        fetch(event.request)
            .then(res => {
                if (res.ok && event.request.method === 'GET') {
                    const clone = res.clone();
                    caches.open(CACHE_NAME).then(c => c.put(event.request, clone));
                }
                return res;
            })
            .catch(() => caches.match(event.request).then(cached => cached || caches.match('/offline.html')))
    );
});
