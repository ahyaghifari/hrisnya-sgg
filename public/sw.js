// ponytail: minimal service worker — satisfies PWA installability criteria (a registered
// SW with a fetch handler) plus web push. No offline caching yet; add a cache strategy
// here when offline support is actually needed.
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));
self.addEventListener('fetch', (event) => {
    event.respondWith(fetch(event.request));
});

self.addEventListener('push', (event) => {
    const payload = event.data ? event.data.json() : {};
    event.waitUntil(
        self.registration.showNotification(payload.title || 'HRIS Syifa Global', {
            body: payload.body || '',
            icon: payload.icon || '/pwa/icons/icon-192.png',
            badge: '/pwa/icons/icon-192.png',
            data: payload.data || {},
        })
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    event.waitUntil(clients.openWindow('/dashboard'));
});
