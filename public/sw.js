/*
 * BillTrix service worker.
 *  - Static assets: served from cache, refreshed in the background.
 *  - The POS page: network first, cached copy when offline (the POS itself queues bills).
 *  - Other pages: network first, cached copy or the offline page when there's no connection.
 *  - Data requests (JSON) and every POST always go to the network; the page decides what to do offline.
 */
const VERSION = 'bt-v2';
const STATIC = `${VERSION}-static`;
const PAGES = `${VERSION}-pages`;
const scope = self.registration.scope;
const PRECACHE = [
    'offline.html', 'manifest.webmanifest', 'assets/icons/icon-192.png', 'assets/img/billtrix-logo-1.png', 'assets/img/favicon.png',
    'assets/vendor/bootstrap/css/bootstrap.min.css', 'assets/vendor/bootstrap/js/bootstrap.bundle.min.js',
    'assets/vendor/font-awesome/css/all.min.css', 'assets/vendor/jquery/jquery.min.js',
    'assets/vendor/select2/css/select2.css', 'assets/vendor/select2/js/select2.min.js',
    'assets/vendor/select2-bootstrap-theme/select2-bootstrap.min.css',
    'assets/css/pos.css', 'assets/js/billtrix.js', 'assets/js/pos.js',
].map((p) => new URL(p, scope).toString());

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(STATIC).then((c) => c.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(caches.keys()
        .then((keys) => Promise.all(keys.filter((k) => !k.startsWith(VERSION)).map((k) => caches.delete(k))))
        .then(() => self.clients.claim()));
});

self.addEventListener('message', (event) => {
    // The POS asks us to keep a fresh copy of itself for offline use.
    if (event.data && event.data.type === 'clear-pages') {
        caches.delete(PAGES); // signing out: don't leave business pages on a shared device
    }
    if (event.data && event.data.type === 'cache-page') {
        caches.open(PAGES).then((c) => c.add(new Request(event.data.url, { credentials: 'same-origin' }))).catch(() => {});
    }
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    const url = new URL(req.url);
    if (req.method !== 'GET' || url.origin !== self.location.origin) return;

    if (url.pathname.includes('/assets/')) {
        event.respondWith(caches.open(STATIC).then(async (cache) => {
            const hit = await cache.match(req, { ignoreSearch: true });
            const fresh = fetch(req).then((res) => { if (res.ok) cache.put(req, res.clone()); return res; }).catch(() => hit);
            return hit || fresh;
        }));
        return;
    }

    if (req.mode === 'navigate') {
        event.respondWith(fetch(req).then((res) => {
            // Keep the latest copy of pages people visit (signed-in pages only, never the login form).
            if (res.ok && !url.pathname.endsWith('/login') && !res.redirected) {
                const copy = res.clone();
                caches.open(PAGES).then((c) => c.put(req, copy));
            }
            return res;
        }).catch(async () => (await caches.match(req, { ignoreSearch: true })) || caches.match(new URL('offline.html', scope).toString())));
    }
});
