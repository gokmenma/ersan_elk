/**
 * Ersan Elektrik - Service Worker
 * Offline desteği ve önbellekleme
 */

const CACHE_NAME = "personel-pwa-v1";
const OFFLINE_URL = "offline.html";

// Önbelleğe alınacak dosyalar
const PRECACHE_ASSETS = [
  "./",
  "./index.php",
  "./assets/css/pwa-style.css",
  "./assets/js/pwa-app.js",
  "./manifest.json",
  "./offline.html",
  "https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap",
  "https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap",
  "https://cdn.tailwindcss.com?plugins=forms,container-queries",
];

// Install event - önbellekleme
self.addEventListener("install", (event) => {
  event.waitUntil(
    caches
      .open(CACHE_NAME)
      .then((cache) => {
        console.log("Opened cache");
        return cache.addAll(PRECACHE_ASSETS);
      })
      .then(() => {
        self.skipWaiting();
      })
  );
});

// Activate event - eski önbellekleri temizle
self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches
      .keys()
      .then((cacheNames) => {
        return Promise.all(
          cacheNames.map((cacheName) => {
            if (cacheName !== CACHE_NAME) {
              console.log("Deleting old cache:", cacheName);
              return caches.delete(cacheName);
            }
          })
        );
      })
      .then(() => {
        self.clients.claim();
      })
  );
});

// Fetch event - network first, fallback to cache
self.addEventListener("fetch", (event) => {
  // API isteklerini her zaman networkten al
  if (event.request.url.includes("api.php")) {
    event.respondWith(
      fetch(event.request)
        .then((response) => {
          return response;
        })
        .catch(() => {
          return new Response(
            JSON.stringify({
              success: false,
              message: "Çevrimdışı modda API erişimi yok",
            }),
            { headers: { "Content-Type": "application/json" } }
          );
        })
    );
    return;
  }

  // Navigasyon istekleri
  if (event.request.mode === "navigate") {
    event.respondWith(
      fetch(event.request).catch(() => {
        return caches.match(OFFLINE_URL);
      })
    );
    return;
  }

  // Diğer istekler - stale while revalidate
  event.respondWith(
    caches.match(event.request).then((cachedResponse) => {
      const fetchPromise = fetch(event.request)
        .then((networkResponse) => {
          // Başarılı yanıtları önbelleğe al
          if (networkResponse && networkResponse.status === 200) {
            const responseToCache = networkResponse.clone();
            caches.open(CACHE_NAME).then((cache) => {
              cache.put(event.request, responseToCache);
            });
          }
          return networkResponse;
        })
        .catch(() => {
          // Network hatası durumunda önbellekten dön
          return cachedResponse;
        });

      // Önbellekte varsa hemen dön, yoksa fetch'i bekle
      return cachedResponse || fetchPromise;
    })
  );
});

// Push notification
self.addEventListener("push", (event) => {
  const options = {
    body: event.data ? event.data.text() : "Yeni bildiriminiz var",
    icon: "./assets/icons/icon-192.png",
    badge: "./assets/icons/badge-72.png",
    vibrate: [100, 50, 100],
    data: {
      dateOfArrival: Date.now(),
      primaryKey: 1,
    },
    actions: [
      { action: "explore", title: "Görüntüle" },
      { action: "close", title: "Kapat" },
    ],
  };

  event.waitUntil(
    self.registration.showNotification("Ersan Elektrik", options)
  );
});

// Notification click
self.addEventListener("notificationclick", (event) => {
  event.notification.close();

  if (event.action === "explore") {
    event.waitUntil(clients.openWindow("index.php"));
  }
});

// Background sync
self.addEventListener("sync", (event) => {
  if (event.tag === "sync-requests") {
    event.waitUntil(syncPendingRequests());
  }
});

async function syncPendingRequests() {
  // TODO: IndexedDB'den bekleyen istekleri al ve gönder
  console.log("Syncing pending requests...");
}
