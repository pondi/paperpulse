/**
 * Cache public scanner assets without storing authenticated pages or uploads.
 */
const CACHE_NAME = 'paperpulse-scanner-v2';
const ASSETS_TO_CACHE = [
  '/vendor/opencv.js?v=2'
];

self.addEventListener('install', (event) => {
  event.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((names) => Promise.all(
      names.filter((name) => name.startsWith('paperpulse-scanner-') && name !== CACHE_NAME)
        .map((name) => caches.delete(name))
    )).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  if (event.request.method !== 'GET') return;
  const url = new URL(event.request.url);
  if (url.origin !== self.location.origin) return;

  if (event.request.mode === 'navigate' && url.pathname === '/scanner') {
    event.respondWith(fetch(event.request).catch(() => new Response(
      '<!doctype html><html lang="en"><title>Scanner offline</title><p>Connect to the internet to use the scanner.</p></html>',
      { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } }
    )));
    return;
  }

  if (event.request.mode === 'navigate' || !ASSETS_TO_CACHE.includes(url.pathname + url.search)) return;

  event.respondWith(
    fetch(new Request(event.request, { credentials: 'omit' })).then(async (response) => {
      if (response.ok && !response.redirected && !response.headers.get('Content-Type')?.includes('text/html')) {
        const cache = await caches.open(CACHE_NAME);
        await cache.put(event.request, response.clone());
      }
      return response;
    }).catch(async () => {
      const cache = await caches.open(CACHE_NAME);
      return await cache.match(event.request) || Response.error();
    })
  );
});
