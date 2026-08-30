const CACHE_NAME = 'neophyten-app-v1';

const APP_FILES = [
  './',
  './index.html',
  './manifest.json',

  'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
  'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',

  'https://unpkg.com/@geoman-io/leaflet-geoman-free@2.18.0/dist/leaflet-geoman.css',
  'https://unpkg.com/@geoman-io/leaflet-geoman-free@2.18.0/dist/leaflet-geoman.min.js'
];


// ------------------------------------------------------------
// Installation
// App und benötigte Bibliotheken lokal speichern
// ------------------------------------------------------------

self.addEventListener('install', event => {

  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => cache.addAll(APP_FILES))
  );

  self.skipWaiting();

});


// ------------------------------------------------------------
// Aktivierung
// Alte App-Caches entfernen
// ------------------------------------------------------------

self.addEventListener('activate', event => {

  event.waitUntil(
    caches.keys().then(cacheNames => {

      return Promise.all(
        cacheNames
          .filter(name => name !== CACHE_NAME)
          .map(name => caches.delete(name))
      );

    })
  );

  self.clients.claim();

});


// ------------------------------------------------------------
// Netzwerk / Offline
// ------------------------------------------------------------

self.addEventListener('fetch', event => {

  const requestUrl = new URL(event.request.url);

  // OpenStreetMap-Kartenkacheln NICHT offline speichern
  if (
    requestUrl.hostname === 'tile.openstreetmap.org' ||
    requestUrl.hostname.endsWith('.tile.openstreetmap.org')
  ) {

    event.respondWith(
      fetch(event.request)
    );

    return;

  }


  // App-Dateien:
  // zuerst Cache, sonst Netzwerk
  event.respondWith(

    caches.match(event.request)
      .then(cachedResponse => {

        if (cachedResponse) {
          return cachedResponse;
        }

        return fetch(event.request);

      })

  );

});
