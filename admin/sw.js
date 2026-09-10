const CACHE_NAME = 'dentality-v1';
const urlsToCache = [
    '/admin/login.php',
    '/admin/manifest.json',
    '/admin/assets/images/logo_Dentality.png',
    '/admin/assets/icons/icon-192x192.png',
    '/admin/assets/icons/icon-512x512.png',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'
];

// Instalar Service Worker
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => {
                return Promise.all(
                    urlsToCache.map(url => {
                        return cache.add(url).catch(error => {
                            console.log('Error cacheando recurso:', url, error);
                            return Promise.resolve();
                        });
                    })
                );
            })
    );
    self.skipWaiting();
});

// Activar: limpiar cachés antiguas
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames
                    .filter(name => name !== CACHE_NAME)
                    .map(name => caches.delete(name))
            );
        }).then(() => {
            return self.clients.claim();
        })
    );
});

// Fetch: interceptar solicitudes
self.addEventListener('fetch', event => {
    if (event.request.method !== 'GET') {
        return;
    }
    const url = new URL(event.request.url);
    const isStaticAsset = url.pathname.match(/\.(css|js|png|jpg|jpeg|gif|svg|ico|woff2?|ttf|eot)(\?.*)?$/i)
        || url.hostname.includes('cdn')
        || url.hostname.includes('cloudflare');

    if (isStaticAsset) {
        event.respondWith(
            caches.match(event.request).then(cachedResponse => {
                if (cachedResponse) {
                    return cachedResponse;
                }
                return fetch(event.request).then(response => {
                    if (!response || response.status !== 200) {
                        return response;
                    }
                    const responseToCache = response.clone();
                    caches.open(CACHE_NAME).then(cache => {
                        cache.put(event.request, responseToCache);
                    });
                    return response;
                });
            })
        );
    } else {
        event.respondWith(
            fetch(event.request).catch(() => caches.match(event.request))
        );
    }
});