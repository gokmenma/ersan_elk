/**
 * Ersan Elektrik - Service Worker
 * Offline desteği ve önbellekleme
 */

const CACHE_NAME = "yonetici-pwa-v9";
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
  const requestScheme = new URL(event.request.url).protocol;
  if (requestScheme !== "http:" && requestScheme !== "https:") {
    return;
  }

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
      fetch(event.request).catch(async () => {
        const offlinePage = await caches.match(OFFLINE_URL);
        return offlinePage || new Response(
          "<!doctype html><html lang='tr'><meta charset='utf-8'><meta name='viewport' content='width=device-width'><title>Bağlantı yok</title><body><h1>Bağlantı kurulamadı</h1><p>Lütfen internet bağlantınızı kontrol edip sayfayı yenileyin.</p></body></html>",
          { status: 503, statusText: "Offline", headers: { "Content-Type": "text/html; charset=UTF-8" } },
        );
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
          return cachedResponse || new Response("Kaynak çevrimdışıyken kullanılamıyor.", {
            status: 503,
            statusText: "Offline",
            headers: { "Content-Type": "text/plain; charset=UTF-8" },
          });
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

  const title = data.title || "Ersan | Yönetici Paneli";
  const options = {
    body: data.body || "Yeni bildiriminiz var",
    icon: "./assets/icons/icon-192-new.png", // Her zaman varsayılan logo
    badge: "./assets/icons/icon-72-new.png",
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
  if (data.image && data.image.startsWith("http")) {
    options.image = data.image;
    console.log("Push Notification Image:", data.image);
  }

  event.waitUntil(self.registration.showNotification(title, options));
});

function bildirimAdresiniCozumle(ham) {
  const kok = self.registration.scope;
  let adres = ham || "index.php";

  if (adres.startsWith("?")) {
    adres = "index.php" + adres;
  }

  try {
    const hedef = new URL(adres, kok);
    return hedef.origin === self.location.origin ? hedef.href : kok;
  } catch (e) {
    return kok;
  }
}

async function bildirimHedefiniAc(hedefUrl) {
  const pencereler = await clients.matchAll({
    type: "window",
    includeUncontrolled: true,
  });

  const ayniOrigin = pencereler.filter((c) => {
    try {
      return new URL(c.url).origin === self.location.origin;
    } catch (e) {
      return false;
    }
  });

  const tamEslesen = ayniOrigin.find((c) => c.url === hedefUrl);
  if (tamEslesen && "focus" in tamEslesen) {
    return tamEslesen.focus();
  }

  for (const pencere of ayniOrigin) {
    if (!("focus" in pencere)) continue;
    try {
      const odaklanan = await pencere.focus();
      if (odaklanan && typeof odaklanan.navigate === "function") {
        await odaklanan.navigate(hedefUrl);
        return;
      }
    } catch (e) {
      console.log("Bildirim yonlendirmesi basarisiz:", e);
    }
    break;
  }

  if (clients.openWindow) {
    return clients.openWindow(hedefUrl);
  }
}

// Notification click
self.addEventListener("notificationclick", (event) => {
  event.notification.close();

  if (event.action === "close") {
    return;
  }

  const hedefUrl = bildirimAdresiniCozumle(
    (event.notification.data && event.notification.data.url) || "",
  );

  event.waitUntil(bildirimHedefiniAc(hedefUrl));
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
