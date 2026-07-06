/* ============================================================
   GVC Display — Service Worker KILL SWITCH
   Se auto-desregistra e limpa TODOS os caches ao ativar.
   Existe apenas para eliminar SWs zumbis das versões antigas.
   ============================================================ */

self.addEventListener('install', (event) => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil((async () => {
    const keys = await caches.keys();
    await Promise.all(keys.map(k => caches.delete(k)));
    await self.registration.unregister();
    const clients = await self.clients.matchAll({ type: 'window' });
    for (const c of clients) c.navigate(c.url);
  })());
});

// Não intercepta requisições
self.addEventListener('fetch', (event) => { return; });
