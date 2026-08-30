const CACHE_NAME = 'neophyten-app-v2';

const APP_FILES = [
  './',
  './index.html',
  './manifest.json',
  'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
  'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
  'https://unpkg.com/@geoman-io/leaflet-geoman-free@2.18.0/dist/leaflet-geoman.css',
  'https://unpkg.com/@geoman-io/leaflet-geoman-free@2.18.0/dist/leaflet-geoman.min.js'
];


// ============================================================
// INSTALLATION
// Grunddateien für die Offline-Nutzung speichern
// ============================================================

self.addEventListener('install', event => {

  event.waitUntil(

    caches.open(CACHE_NAME)
      .then(cache => {
        return cache.addAll(APP_FILES);
      })

  );

  self.skipWaiting();

});


// ============================================================
// AKTIVIERUNG
// Alte Cache-Versionen entfernen
// ============================================================

self.addEventListener('activate', event => {

  event.waitUntil(

    caches.keys()
      .then(cacheNames => {

        return Promise.all(

          cacheNames
            .filter(name => name !== CACHE_NAME)
            .map(name => caches.delete(name))

        );

      })

  );

  self.clients.claim();

});


// ============================================================
// FETCH
// ============================================================

self.addEventListener('fetch', event => {

  const request = event.request;
  const requestUrl = new URL(request.url);


  // ----------------------------------------------------------
  // Nur GET-Anfragen behandeln
  // ----------------------------------------------------------

  if (request.method !== 'GET') {
    return;
  }


  // ----------------------------------------------------------
  // OpenStreetMap-Kacheln NICHT offline speichern
  // ----------------------------------------------------------

  if (
    requestUrl.hostname === 'tile.openstreetmap.org' ||
    requestUrl.hostname.endsWith('.tile.openstreetmap.org')
  ) {

    event.respondWith(
      fetch(request)
    );

    return;

  }


  // ----------------------------------------------------------
  // Navigation / HTML:
  //
  // ONLINE  -> aktuelle Version aus dem Internet
  // OFFLINE -> gespeicherte Version aus dem Cache
  // ----------------------------------------------------------

  if (request.mode === 'navigate') {

    event.respondWith(

      fetch(request)
        .then(response => {

          const responseCopy = response.clone();

          caches.open(CACHE_NAME)
            .then(cache => {
              cache.put('./index.html', responseCopy);
            });

          return response;

        })

        .catch(() => {

          return caches.match('./index.html');

        })

    );

    return;

  }


  // ----------------------------------------------------------
  // Übrige App-Dateien:
  //
  // Erst Cache verwenden.
  // Falls nicht vorhanden -> Internet.
  // ----------------------------------------------------------

  event.respondWith(

    caches.match(request)
      .then(cachedResponse => {

        if (cachedResponse) {
          return cachedResponse;
        }

        return fetch(request)
          .then(response => {

            return response;

          });

      })

  );

});
