// Service Worker per Proloco Bar Manager
// Permette funzionamento offline e installazione come app

const CACHE_NAME = 'proloco-bar-v1';
const urlsToCache = [
    '/',
    '/index.html',
    '/css/style.css',
    '/manifest.json'
];

// Installazione
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => {
                console.log('Cache aperta');
                return cache.addAll(urlsToCache);
            })
            .catch(err => console.log('Errore cache:', err))
    );
    self.skipWaiting();
});

// Attivazione
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => {
                    if (cacheName !== CACHE_NAME) {
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );
    self.clients.claim();
});

// Fetch - Network first, then cache
self.addEventListener('fetch', event => {
    // Skip API calls - sempre network
    if (event.request.url.includes('/api/')) {
        return;
    }
    
    event.respondWith(
        fetch(event.request)
            .then(response => {
                // Clona la risposta
                const responseClone = response.clone();
                
                caches.open(CACHE_NAME)
                    .then(cache => {
                        cache.put(event.request, responseClone);
                    });
                
                return response;
            })
            .catch(() => {
                return caches.match(event.request);
            })
    );
});
