// ponytail: network-first with cache fallback — every successful same-origin GET
// (page navigations + build assets) is cached, so offline reloads show the last-seen
// version instead of the browser's no-connection page. Not stale-while-revalidate or
// precaching; upgrade to Workbox if per-route strategies are needed later.
const CACHE = 'hris-runtime-v3';

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET' || !req.url.startsWith(self.location.origin)) {
        return;
    }

    event.respondWith(
        fetch(req)
            .then((res) => {
                if (res.ok) {
                    const copy = res.clone();
                    caches.open(CACHE).then((cache) => cache.put(req, copy));
                }
                return res;
            })
            .catch(() => caches.match(req).then((cached) => cached || Response.error()))
    );
});

self.addEventListener('push', (event) => {
    const payload = event.data ? event.data.json() : {};
    event.waitUntil(
        self.registration.showNotification(payload.title || 'HRIS Syifa Global', {
            body: payload.body || '',
            icon: payload.icon || '/pwa/icons/icon-192-v2.png',
            badge: '/pwa/icons/icon-192-v2.png',
            data: payload.data || {},
        })
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const requestId = event.notification.data?.request_id;
    const url = requestId ? `/pengajuan-absensi/${requestId}` : '/';
    event.waitUntil(clients.openWindow(url));
});
