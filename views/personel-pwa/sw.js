/**
 * Ersan Elektrik - Service Worker
 * Offline desteği ve önbellekleme
 */

const CACHE_NAME = "personel-pwa-v3";
const OFFLINE_URL = "offline.html";

// Önbelleğe alınacak dosyalar
const PRECACHE_ASSETS = [
  "./",
  "./index.php",
  "./assets/css/pwa-style.css",
  "./assets/js/pwa-app.js",
  "./manifest.json",
  "./offline.html",
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
      }),
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
          }),
        );
      })
      .then(() => {
        self.clients.claim();
      }),
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
            { headers: { "Content-Type": "application/json" } },
          );
        }),
    );
    return;
  }

  // Navigasyon istekleri
  if (event.request.mode === "navigate") {
    event.respondWith(
      fetch(event.request).catch(() => {
        return caches.match(OFFLINE_URL);
      }),
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
    }),
  );
});

// Push notification
self.addEventListener("push", (event) => {
  let data = {};

  if (event.data) {
    try {
      data = event.data.json();
    } catch (e) {
      data = { body: event.data.text() };
    }
  }

  console.log("Push Data Received:", data);

  const title = data.title || "Ersan | Personel Yönetim";
  const options = {
    body: data.body || "Yeni bildiriminiz var",
    icon: "./assets/icons/icon-192.png",  // Her zaman varsayılan logo
    badge: "./assets/icons/badge-72.png",
    vibrate: [100, 50, 100],
    data: {
      dateOfArrival: Date.now(),
      url: data.url || "index.php",
    },
    actions: [
      { action: "explore", title: "Görüntüle" },
      { action: "close", title: "Kapat" },
    ],
  };

  // Resim varsa ekle - Android Chrome'da büyük resim olarak görünür
  if (data.image && data.image.startsWith('http')) {
    options.image = data.image;
    console.log("Push Notification Image:", data.image);
  }

  event.waitUntil(self.registration.showNotification(title, options));
});

// Notification click
self.addEventListener("notificationclick", (event) => {
  event.notification.close();

  if (event.action === "explore" || !event.action) {
    const urlToOpen = event.notification.data.url || "index.php";

    event.waitUntil(
      clients.matchAll({ type: "window" }).then((windowClients) => {
        // Eğer açık bir pencere varsa ona odaklan
        for (let i = 0; i < windowClients.length; i++) {
          const client = windowClients[i];
          // URL kontrolü tam eşleşme yerine içeriyor mu diye bakabiliriz
          if ("focus" in client) {
            return client.focus().then((c) => c.navigate(urlToOpen));
          }
        }
        // Yoksa yeni pencere aç
        if (clients.openWindow) {
          return clients.openWindow(urlToOpen);
        }
      }),
    );
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
