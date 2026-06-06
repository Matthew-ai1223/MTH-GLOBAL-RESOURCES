// Service Worker with Web Push Support
// This will immediately take over, delete all existing caches, and fetch everything from the network,
// plus support push notification events and clicks.

self.addEventListener('install', (e) => {
  self.skipWaiting();
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cacheName) => {
          console.log('Deleting cache:', cacheName);
          return caches.delete(cacheName);
        })
      );
    }).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  // Always fetch from network, never cache
  event.respondWith(fetch(event.request));
});

self.addEventListener('push', (event) => {
  event.waitUntil(
    fetch('/api/index.php?route=products.latest_notification')
      .then(res => res.json())
      .then(data => {
        if (data.success && data.data) {
          const p = data.data;
          const formattedPrice = '₦' + Number(p.price).toLocaleString('en-NG', { minimumFractionDigits: 2 });
          return self.registration.showNotification('New Product Added! 🌱', {
            body: `${p.name} - ${formattedPrice}\n${p.description || ''}`,
            icon: '/assets/icon.svg',
            image: p.image_url,
            badge: '/assets/icon.svg',
            data: { url: '/pages/shop.html' }
          });
        }
      }).catch(err => {
        console.error('Failed to fetch latest product or show notification:', err);
      })
  );
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
      const url = event.notification.data?.url || '/pages/shop.html';
      for (const client of list) {
        if (client.url.includes(url) && 'focus' in client) return client.focus();
      }
      if (clients.openWindow) return clients.openWindow(url);
    })
  );
});
