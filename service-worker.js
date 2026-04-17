const CACHE_NAME = 'analisis-saham-v4';

self.addEventListener('install', event => {
  self.skipWaiting();
});

self.addEventListener('activate', event => {
  // Hapus semua cache versi lama
  event.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.map(key => caches.delete(key)))
    )
  );
});

self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);

  // Jangan pernah cache file PHP, navigasi HTML, atau rute root - selalu ambil fresh dari server
  if (url.pathname.endsWith('.php') || url.pathname.endsWith('/') || event.request.mode === 'navigate') {
    event.respondWith(fetch(event.request));
    return;
  }

  // Untuk aset statis (JS, CSS, PNG, dll) gunakan cache-first
  event.respondWith(
    caches.match(event.request).then(cached => {
      if (cached) return cached;
      return fetch(event.request).then(response => {
        if (response && response.status === 200) {
          const clone = response.clone();
          caches.open(CACHE_NAME).then(cache => cache.put(event.request, clone));
        }
        return response;
      });
    })
  );
});