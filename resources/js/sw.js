const CACHE_NAME = 'gvc-display-static-v3';

self.addEventListener('install', (event) => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => Promise.all(keys.map((key) => {
      if (key !== CACHE_NAME) return caches.delete(key);
      return Promise.resolve();
    }))).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url);

  // API, TV e uploads são dinâmicos — nunca cachear
  if (
    url.pathname.includes('/api/') ||
    url.pathname.includes('/tv/') ||
    url.pathname.endsWith('/tv') ||
    url.pathname.includes('/uploads/')
  ) {
    return;
  }

  if (event.request.method !== 'GET') return;

  // CSS, JS e HTML — Network First (sempre pega o mais recente)
  const isResource = /\.(css|js|html)(\?|$)/i.test(url.pathname);

  if (isResource) {
    event.respondWith(
      fetch(event.request)
        .then((response) => {
          if (response.ok && response.type === 'basic') {
            const copy = response.clone();
            caches.open(CACHE_NAME).then((cache) => cache.put(event.request, copy)).catch(() => {});
          }
          return response;
        })
        .catch(() => caches.match(event.request).then((cached) => cached || new Response('Offline', { status: 503 })))
    );
    return;
  }

  // Demais assets (fontes, ícones, imagens estáticas) — Cache First
  event.respondWith(
    caches.match(event.request).then((cached) => {
      if (cached) return cached;

      return fetch(event.request).then((response) => {
        if (response.ok && response.type === 'basic') {
          const copy = response.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(event.request, copy)).catch(() => {});
        }
        return response;
      });
    })
  );
});
