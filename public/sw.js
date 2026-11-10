/* Patient Portal service worker — minimal, network-first, scoped to /portal.
   Never caches the staff app. Falls back to an offline message when the
   network is unavailable for a navigation. */
const CACHE = 'patient-portal-v1';
const OFFLINE_URL = '/posinnova/public/portal/offline';

self.addEventListener('install', function (event) {
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (keys) {
            return Promise.all(keys.filter(function (k) { return k !== CACHE; }).map(function (k) { return caches.delete(k); }));
        }).then(function () { return self.clients.claim(); })
    );
});

self.addEventListener('fetch', function (event) {
    const req = event.request;
    if (req.method !== 'GET') return;

    // Network-first for navigations; on failure show a cached offline page.
    if (req.mode === 'navigate') {
        event.respondWith(
            fetch(req).catch(function () {
                return caches.match(OFFLINE_URL).then(function (r) {
                    return r || new Response(
                        '<h1 style="font-family:sans-serif;text-align:center;margin-top:40vh;color:#6b7280">You are offline</h1>',
                        { headers: { 'Content-Type': 'text/html' } }
                    );
                });
            })
        );
        return;
    }

    // Cache-first for static assets (css/js/img) to speed up repeat loads.
    if (/\.(css|js|png|jpg|jpeg|svg|woff2?|ttf)$/.test(new URL(req.url).pathname)) {
        event.respondWith(
            caches.open(CACHE).then(function (cache) {
                return cache.match(req).then(function (cached) {
                    const network = fetch(req).then(function (res) {
                        if (res && res.status === 200) cache.put(req, res.clone());
                        return res;
                    }).catch(function () { return cached; });
                    return cached || network;
                });
            })
        );
    }
});
