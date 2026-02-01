/**
 * PaperPulse Scanner Service Worker
 *
 * Simple caching strategy for quick access to scanner assets.
 * Network-first approach since the app requires online connectivity.
 */

const CACHE_NAME = 'paperpulse-scanner-v1';
const ASSETS_TO_CACHE = [
  '/scanner',
  '/vendor/opencv.js',
  '/icons/icon-192.png',
  '/icons/icon-512.png'
];

// Install: Pre-cache essential assets
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(ASSETS_TO_CACHE);
    }).then(() => {
      self.skipWaiting();
    })
  );
});

// Activate: Clean up old caches
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames
          .filter((name) => name.startsWith('paperpulse-scanner-') && name !== CACHE_NAME)
          .map((name) => caches.delete(name))
      );
    }).then(() => {
      self.clients.claim();
    })
  );
});

// Fetch: Network-first strategy with cache fallback
self.addEventListener('fetch', (event) => {
  // Skip non-GET requests
  if (event.request.method !== 'GET') {
    return;
  }

  // Skip API and auth requests
  const url = new URL(event.request.url);
  if (url.pathname.startsWith('/api/') ||
      url.pathname.startsWith('/auth/') ||
      url.pathname.startsWith('/sanctum/')) {
    return;
  }

  event.respondWith(
    fetch(event.request)
      .then((response) => {
        // Cache successful responses for scanner-related assets
        if (response.ok && shouldCache(event.request)) {
          const responseClone = response.clone();
          caches.open(CACHE_NAME).then((cache) => {
            cache.put(event.request, responseClone);
          });
        }
        return response;
      })
      .catch(() => {
        // Fallback to cache if network fails
        return caches.match(event.request);
      })
  );
});

// Determine if a request should be cached
function shouldCache(request) {
  const url = new URL(request.url);

  // Cache scanner page and static assets
  if (url.pathname === '/scanner') return true;
  if (url.pathname.startsWith('/vendor/')) return true;
  if (url.pathname.startsWith('/icons/')) return true;
  if (url.pathname.startsWith('/build/')) return true;

  return false;
}
