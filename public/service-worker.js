/**
 * ELHOE Verification - Service Worker
 * Caches static shell assets for fast repeat loads + offline message.
 */
const CACHE = 'elhoe-checker-v1';
const SHELL = [
    '/checker/',
    '/checker/public/assets/css/style.css',
    '/checker/public/assets/js/checker.js',
    '/checker/manifest.json'
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE).then((cache) => cache.addAll(SHELL).catch(() => {}))
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))
        )
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    if (request.method !== 'GET') return;

    const url = new URL(request.url);

    // Never cache API calls
    if (url.pathname.includes('/api/')) {
        event.respondWith(
            fetch(request).catch(() =>
                new Response(
                    JSON.stringify({ error: 'offline', message: 'You are offline. Please connect to verify.' }),
                    { status: 503, headers: { 'Content-Type': 'application/json' } }
                )
            )
        );
        return;
    }

    // Static shell: cache-first
    event.respondWith(
        caches.match(request).then((hit) => hit || fetch(request).then((res) => {
            // Cache successful GET responses
            if (res && res.status === 200 && res.type === 'basic') {
                const clone = res.clone();
                caches.open(CACHE).then((cache) => cache.put(request, clone));
            }
            return res;
        }).catch(() => caches.match('/checker/')))
    );
});
