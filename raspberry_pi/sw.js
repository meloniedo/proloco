// Service Worker per Proloco Bar Manager PWA
// Versione 2 - Ottimizzato per funzionamento offline locale

const CACHE_NAME = 'proloco-bar-v2';
const OFFLINE_URL = '/index.html';

// File da cachare all'installazione
const PRECACHE_URLS = [
    './',
    './index.html',
    './manifest.json',
    './css/style.css',
    './icons/icon.svg',
    './icons/icon-512.svg'
];

// Installazione - cache dei file essenziali
self.addEventListener('install', event => {
    console.log('[SW] Installazione...');
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => {
                console.log('[SW] Cache aperta, aggiungo file...');
                return cache.addAll(PRECACHE_URLS).catch(err => {
                    console.log('[SW] Errore pre-cache (ignorato):', err);
                    // Non bloccare l'installazione se qualche file manca
                    return Promise.resolve();
                });
            })
            .then(() => {
                console.log('[SW] Installazione completata');
                return self.skipWaiting();
            })
    );
});

// Attivazione - pulizia vecchie cache
self.addEventListener('activate', event => {
    console.log('[SW] Attivazione...');
    event.waitUntil(
        caches.keys()
            .then(cacheNames => {
                return Promise.all(
                    cacheNames
                        .filter(name => name !== CACHE_NAME)
                        .map(name => {
                            console.log('[SW] Elimino vecchia cache:', name);
                            return caches.delete(name);
                        })
                );
            })
            .then(() => {
                console.log('[SW] Attivazione completata');
                return self.clients.claim();
            })
    );
});

// Fetch - Strategia: Network first, fallback to cache
self.addEventListener('fetch', event => {
    const request = event.request;
    const url = new URL(request.url);
    
    // Ignora richieste non-GET
    if (request.method !== 'GET') {
        return;
    }
    
    // Per le API: sempre network (no cache)
    if (url.pathname.includes('/api/')) {
        event.respondWith(
            fetch(request).catch(() => {
                return new Response(JSON.stringify({error: 'Offline'}), {
                    headers: {'Content-Type': 'application/json'}
                });
            })
        );
        return;
    }
    
    // Per altri file: network first, poi cache
    event.respondWith(
        fetch(request)
            .then(response => {
                // Salva in cache solo risposte valide
                if (response && response.status === 200) {
                    const responseClone = response.clone();
                    caches.open(CACHE_NAME).then(cache => {
                        cache.put(request, responseClone);
                    });
                }
                return response;
            })
            .catch(() => {
                // Offline: cerca in cache
                return caches.match(request).then(cachedResponse => {
                    if (cachedResponse) {
                        return cachedResponse;
                    }
                    // Se non trovato e richiesta HTML, ritorna index.html
                    if (request.headers.get('accept').includes('text/html')) {
                        return caches.match(OFFLINE_URL);
                    }
                    return new Response('Offline', {status: 503});
                });
            })
    );
});

// Gestione messaggi
self.addEventListener('message', event => {
    if (event.data === 'skipWaiting') {
        self.skipWaiting();
    }
});

console.log('[SW] Service Worker caricato');
