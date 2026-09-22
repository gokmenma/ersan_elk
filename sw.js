/**
 * Ersan Elektrik - Service Worker
 * Offline desteği ve önbellekleme
 */

const CACHE_NAME = "yonetici-pwa-v12";
const OFFLINE_URL = new URL("offline-admin.html", self.registration.scope).href;

// Önbelleğe alınacak dosyalar
const PRECACHE_ASSETS = [
  "./manifest.json",
  OFFLINE_URL,
  "./assets/icons/icon-72-new.png",
  "./assets/icons/icon-144-new.png",
  "./assets/icons/icon-192-new.png",
  "./assets/icons/icon-512-new.png",
  "./assets/images/screenshot-desktop.jpg",
  "./assets/images/screenshot-mobile.jpg",
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
  const url = event.request.url;
  let requestScheme = "";
  try {
    requestScheme = new URL(url).protocol;
  } catch (e) {
    return;
  }
  if (requestScheme !== "http:" && requestScheme !== "https:") {
    return;
  }

  // POST/PUT/DELETE istekleri, ERP yönetim sayfaları ve API uç noktaları doğrudan ağ üzerinden yapılmalıdır (Service Worker araya girmez)
  if (
    event.request.method !== "GET" ||
    url.includes("p=") ||
    url.includes("index") ||
    url.includes("views/") ||
    url.includes("api.php") ||
    url.includes("export") ||
    url.includes("download") ||
    url.includes("foto-goruntule") ||
    url.includes("kacak-foto") ||
    url.includes("ajax")
  ) {
    return;
  }

  // Navigasyon istekleri (Sadece PWA bağımsız sayfalar)
  if (event.request.mode === "navigate") {
    event.respondWith(
      fetch(event.request).catch(async () => {
        const offlinePage = await caches.match(OFFLINE_URL);
        return (
          offlinePage ||
          new Response(
            "<!doctype html><html lang='tr'><meta charset='utf-8'><meta name='viewport' content='width=device-width'><title>Bağlantı yok</title><body><h1>Bağlantı kurulamadı</h1><p>Lütfen internet bağlantınızı kontrol edip sayfayı yenileyin.</p></body></html>",
            {
              status: 503,
              statusText: "Offline",
              headers: { "Content-Type": "text/html; charset=UTF-8" },
            },
          )
        );
      }),
    );
    return;
  }

  // Diğer statik varlık istekleri - stale while revalidate
  event.respondWith(
    caches.match(event.request).then((cachedResponse) => {
      const fetchPromise = fetch(event.request)
        .then((networkResponse) => {
          // Başarılı yanıtları önbelleğe al
          if (
            networkResponse &&
            networkResponse.status === 200 &&
            event.request.method === "GET"
          ) {
            const responseToCache = networkResponse.clone();
            caches.open(CACHE_NAME).then((cache) => {
              cache.put(event.request, responseToCache).catch(() => {});
            });
          }
          return networkResponse;
        })
        .catch(() => {
          // Network hatası durumunda önbellekten dön
          return (
            cachedResponse ||
            new Response("Kaynak çevrimdışıyken kullanılamıyor.", {
              status: 503,
              statusText: "Offline",
              headers: { "Content-Type": "text/plain; charset=UTF-8" },
            })
          );
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

  const title = data.title || "Ersan Elektrik";
  const options = {
    body: data.body || "Yeni bir bildiriminiz var.",
    icon: data.icon || "./assets/icons/icon-192-new.png",
    badge: "./assets/icons/icon-72-new.png",
    data: {
      url: data.url || "./index.php",
    },
  };

  event.waitUntil(self.registration.showNotification(title, options));
});

// Notification click event
self.addEventListener("notificationclick", (event) => {
  event.notification.close();

  const targetUrl =
    event.notification.data && event.notification.data.url
      ? event.notification.data.url
      : "./index.php";

  event.waitUntil(
    clients
      .matchAll({ type: "window", includeUncontrolled: true })
      .then((windowClients) => {
        for (let client of windowClients) {
          if (client.url === targetUrl && "focus" in client) {
            return client.focus();
          }
        }
        if (clients.openWindow) {
          return clients.openWindow(targetUrl);
        }
      }),
  );
});
