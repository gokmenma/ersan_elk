/**
 * Ersan Elektrik - Service Worker
 * Offline desteği ve önbellekleme
 */

// pwa-offline-queue.js her değiştiğinde bu sürüm artırılmalı: hem eski önbellek
// temizlenir hem de importScripts URL'i değişir. Kayıt updateViaCache belirtmediği
// için varsayılan "imports" geçerlidir ve sürümsüz import HTTP önbelleğinden
// gelip service worker'ı eski kodla çalıştırır.
const KUYRUK_SURUM = "17";
const CACHE_NAME = "personel-pwa-v" + KUYRUK_SURUM;
const SAYFA_CACHE = "personel-pwa-sayfa-v1";
const OFFLINE_URL = "offline.html";

// Önbelleğe alınacak dosyalar
const PRECACHE_ASSETS = [
  "./assets/css/pwa-style.css",
  "./assets/css/tailwind-build.css",
  "./assets/js/pwa-app.js",
  "./assets/js/pwa-offline-queue.js",
  "../../assets/js/exif-cekim.js",
  "./manifest.json",
  "./offline.html",
  "./assets/icons/icon-144-new.png",
  "./assets/icons/icon-192-new.png",
];

// Çevrimdışı kuyruk mantığı sayfa ile ortak; uygulama kapalıyken de
// Background Sync ile buradan gönderilebilmesi için içe aktarılır.
importScripts("./assets/js/pwa-offline-queue.js?v=" + KUYRUK_SURUM);

// Install event - önbellekleme
// addAll atomiktir: tek bir dosya getirilemezse kurulum tümden düşer ve yeni
// service worker hiç aktifleşmez; cihaz eski kodla çalışmaya devam eder.
// Bu yüzden her dosya ayrı ayrı, hatası yutularak alınır.
self.addEventListener("install", (event) => {
  event.waitUntil(
    caches
      .open(CACHE_NAME)
      .then((cache) =>
        Promise.all(
          PRECACHE_ASSETS.map((yol) =>
            cache
              .add(yol)
              .catch((e) => console.log("Önbelleğe alınamadı:", yol, e)),
          ),
        ),
      )
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
            if (cacheName !== CACHE_NAME && cacheName !== SAYFA_CACHE) {
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

/**
 * Tam eşleşme bulunamazsa aynı ?page= değerine sahip başka bir kopyayı,
 * o da yoksa çevrimdışı sayfasını döndürür.
 */
async function sayfaOnbellegindenBenzer(request) {
  try {
    const istenen = new URL(request.url).searchParams.get("page") || "ana-sayfa";
    const cache = await caches.open(SAYFA_CACHE);

    for (const anahtar of await cache.keys()) {
      const sayfa = new URL(anahtar.url).searchParams.get("page") || "ana-sayfa";
      if (sayfa === istenen) {
        const yanit = await cache.match(anahtar, { ignoreVary: true });
        if (yanit) return yanit;
      }
    }
  } catch (e) {
    console.log("Sayfa önbelleği okunamadı:", e);
  }

  return caches.match(OFFLINE_URL);
}

/**
 * Sayfaları personel o sayfayı hiç açmamış olsa bile önden önbelleğe alır;
 * aksi halde saha ekibi ilk kez çevrimdışıyken kaçak sayfasına ulaşamaz.
 * İstek `onbellek=1` ile yapılır (sunucu bunu sayfa görüntüleme olarak
 * loglamaz) ama önbelleğe temiz adresle yazılır, navigasyon onunla eşleşir.
 */
async function sayfalariOnbellekle(sayfalar) {
  const cache = await caches.open(SAYFA_CACHE);

  for (const yol of sayfalar) {
    try {
      const ayirac = yol.indexOf("?") === -1 ? "?" : "&";
      const yanit = await fetch(yol + ayirac + "onbellek=1", {
        credentials: "same-origin",
        cache: "no-store",
      });

      if (yanit && yanit.ok && !yanit.redirected) {
        await cache.put(new Request(yol), yanit);
      }
    } catch (e) {
      console.log("Sayfa önden alınamadı:", yol, e);
    }
  }
}

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
              offline: true,
              message: "Çevrimdışı modda API erişimi yok",
            }),
            { headers: { "Content-Type": "application/json" } },
          );
        }),
    );
    return;
  }

  // Navigasyon istekleri: ağdan al, kopyasını sakla; bağlantı yoksa
  // en son görüntülenen sürümü göster (saha personeli formu açabilsin).
  if (event.request.mode === "navigate") {
    event.respondWith(
      fetch(event.request)
        .then((response) => {
          // login.php'ye yönlenmiş bir yanıt sayfa olarak saklanmamalı.
          // POST ile gelen navigasyonlar önbelleğe yazılamaz.
          if (
            response &&
            response.ok &&
            !response.redirected &&
            event.request.method === "GET"
          ) {
            const kopya = response.clone();
            caches
              .open(SAYFA_CACHE)
              .then((cache) => cache.put(event.request, kopya))
              .catch((e) => console.log("Sayfa önbelleğe alınamadı:", e));
          }
          return response;
        })
        .catch(() => {
          return caches
            .match(event.request, { ignoreVary: true })
            .then((onbellek) => {
              return onbellek || sayfaOnbellegindenBenzer(event.request);
            });
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
    icon: "./assets/icons/icon-192-new.png", // Her zaman varsayılan logo
    badge: "./assets/icons/icon-72.png",
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

// Background sync - uygulama kapalıyken bağlantı gelince kuyruğu boşaltır
self.addEventListener("sync", (event) => {
  if (event.tag === (self.OfflineQueue && self.OfflineQueue.SYNC_ETIKETI)) {
    event.waitUntil(self.OfflineQueue.flush());
  }
});

// Sayfadan gelen komutlar
self.addEventListener("message", (event) => {
  const data = event.data || {};

  if (data.tip === "kuyrugu-gonder" && self.OfflineQueue) {
    event.waitUntil(self.OfflineQueue.flush(data.secenekler));
    return;
  }

  if (data.tip === "sayfalari-onbellekle" && Array.isArray(data.sayfalar)) {
    event.waitUntil(sayfalariOnbellekle(data.sayfalar));
    return;
  }

  // Çıkışta oturuma ait önbelleklenmiş sayfalar cihazda bırakılmaz.
  // Kuyruk bilinçli olarak korunur: gönderilmemiş tutanak silinmemelidir.
  if (data.tip === "oturum-temizle") {
    event.waitUntil(caches.delete(SAYFA_CACHE));
  }
});
