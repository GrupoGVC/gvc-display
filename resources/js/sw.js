/* ============================================================
   GVC Display — Service Worker
   Estratégia:
   - Admin:  Network First (sempre dados frescos do painel)
   - Assets: Cache First  (CSS, JS, fontes — mudam pouco)
   - TV:     Stale-While-Revalidate (exibe cache, atualiza em bg)
   - API:    Network Only (nunca cacheia dados da API)
   ============================================================ */

const VERSION     = 'gvc-v1';
const CACHE_STATIC = `${VERSION}-static`;
const CACHE_PAGES  = `${VERSION}-pages`;

// Arquivos essenciais para funcionar offline
const PRECACHE = [
  '/gvc-display/',
  '/gvc-display/login',
  '/gvc-display/resources/css/main.css',
  '/gvc-display/resources/css/admin.css',
  '/gvc-display/resources/css/player.css',
  '/gvc-display/resources/js/admin.js',
  '/gvc-display/resources/js/api.js',
  '/gvc-display/resources/js/utils.js',
  '/gvc-display/resources/js/player.js',
  '/gvc-display/assets/icons/icon-192.png',
  '/gvc-display/assets/icons/icon-512.png',
];

// ── Install: pré-cacheia assets essenciais ─────────────────
self.addEventListener('install', (e) => {
  e.waitUntil(
    caches.open(CACHE_STATIC)
      .then(cache => cache.addAll(PRECACHE))
      .then(() => self.skipWaiting())
  );
});

// ── Activate: remove caches antigos ───────────────────────
self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys()
      .then(keys => Promise.all(
        keys
          .filter(k => k.startsWith('gvc-') && k !== CACHE_STATIC && k !== CACHE_PAGES)
          .map(k => caches.delete(k))
      ))
      .then(() => self.clients.claim())
  );
});

// ── Fetch: estratégia por tipo de recurso ─────────────────
self.addEventListener('fetch', (e) => {
  const url = new URL(e.request.url);

  // 1. API — nunca cacheia (sempre Network)
  if (url.pathname.includes('/api/')) return;

  // 2. Assets estáticos (CSS, JS, imagens, fontes) — Cache First
  if (isStaticAsset(url)) {
    e.respondWith(cacheFirst(e.request));
    return;
  }

  // 3. Páginas HTML admin — Network First com fallback de cache
  if (e.request.mode === 'navigate' && !url.pathname.includes('/tv/')) {
    e.respondWith(networkFirstWithFallback(e.request));
    return;
  }

  // 4. Player TV — Stale While Revalidate
  // Exibe o que tem em cache imediatamente, atualiza em background
  if (url.pathname.includes('/tv/')) {
    e.respondWith(staleWhileRevalidate(e.request, CACHE_PAGES));
    return;
  }
});

// ── Estratégias ────────────────────────────────────────────
async function cacheFirst(request) {
  const cached = await caches.match(request);
  if (cached) return cached;
  try {
    const response = await fetch(request);
    if (response.ok) {
      const cache = await caches.open(CACHE_STATIC);
      cache.put(request, response.clone());
    }
    return response;
  } catch {
    return new Response('Recurso indisponível offline', { status: 503 });
  }
}

async function networkFirstWithFallback(request) {
  try {
    const response = await fetch(request);
    if (response.ok) {
      const cache = await caches.open(CACHE_PAGES);
      cache.put(request, response.clone());
    }
    return response;
  } catch {
    const cached = await caches.match(request);
    return cached || caches.match('/gvc-display/');
  }
}

async function staleWhileRevalidate(request, cacheName) {
  const cache  = await caches.open(cacheName);
  const cached = await cache.match(request);

  const fetchPromise = fetch(request).then(response => {
    if (response.ok) cache.put(request, response.clone());
    return response;
  }).catch(() => null);

  return cached || fetchPromise;
}

// ── Helpers ────────────────────────────────────────────────
function isStaticAsset(url) {
  return /\.(css|js|png|jpg|jpeg|gif|webp|svg|ico|woff|woff2|ttf)$/i.test(url.pathname);
}

// ── Push notifications (futuro) ───────────────────────────
self.addEventListener('push', (e) => {
  if (!e.data) return;
  const data = e.data.json();
  self.registration.showNotification(data.title || 'GVC Display', {
    body: data.body || '',
    icon: '/gvc-display/assets/icons/icon-192.png',
    badge: '/gvc-display/assets/icons/icon-72.png',
    data: { url: data.url || '/gvc-display/' },
  });
});

self.addEventListener('notificationclick', (e) => {
  e.notification.close();
  e.waitUntil(clients.openWindow(e.notification.data?.url || '/gvc-display/'));
});
