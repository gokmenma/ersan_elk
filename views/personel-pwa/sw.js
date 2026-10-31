/**
 * Ersan Elektrik - Service Worker
 * Offline desteği ve önbellekleme
 */

// pwa-offline-queue.js her değiştiğinde bu sürüm artırılmalı: hem eski önbellek
// temizlenir hem de importScripts ve precache URL'leri değişir. Kayıt tarafında
// updateViaCache="none" kullanılarak worker bağımlılıklarının eski HTTP
// önbelleğinden gelmesi de engellenir.
const KUYRUK_SURUM = "34";
const ONBELLEK_REVIZYON = "20261031c";
const CACHE_NAME = "personel-pwa-v" + KUYRUK_SURUM + "-" + ONBELLEK_REVIZYON;
const SAYFA_CACHE = "personel-pwa-sayfa-v6";
const OFFLINE_URL = "offline.html";

// Önbelleğe alınacak dosyalar
const PRECACHE_ASSETS = [
  "./assets/css/pwa-style.css",
  "./assets/css/pwa-chat.css",
  "./assets/css/tailwind-build.css",
  "./assets/js/pwa-app.js",
  "./assets/js/pwa-chat.js",
  "./assets/js/pwa-offline-queue.js?v=" + KUYRUK_SURUM,
  "./assets/js/exif-cekim.js",
  "./assets/libs/sweetalert2/sweetalert2.all.min.js",
  "./manifest.json",
  "./offline.html",
  "./assets/icons/app-192.png?v=2",
  "./assets/images/logo-3d-splash.png?v=1",
  "./assets/images/van.png?v=6",
  "./assets/images/van-wheel.png?v=3",
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
      .then(() => self.skipWaiting()),
  );
});

// Activate event - eski önbellekleri temizle
self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches
      .keys()
      .then(async (cacheNames) => {
        await Promise.all(
          cacheNames.map((cacheName) => {
            if (cacheName !== CACHE_NAME && cacheName !== SAYFA_CACHE) {
              console.log("Eski önbellek siliniyor:", cacheName);
              return caches.delete(cacheName);
            }
          }),
        );
        // Önceki worker sürümleri asset sorgu parametresini yok saydığı için
        // aynı cache içinde eski kuyruk dosyaları kalmış olabilir.
        const kuyrukYolu = new URL("./assets/js/pwa-offline-queue.js", self.location.href).pathname;
        await Promise.all([CACHE_NAME, SAYFA_CACHE].map(async (cacheName) => {
          const cache = await caches.open(cacheName);
          const keys = await cache.keys();
          await Promise.all(keys.map((key) => {
            const keyUrl = new URL(key.url);
            if (keyUrl.pathname === kuyrukYolu && keyUrl.searchParams.get("v") !== KUYRUK_SURUM) {
              return cache.delete(key);
            }
          }));
        }));
      })
      .then(() => self.clients.claim()),
  );
});

// Web sayfasından (pwa-app.js) gelen bildirimleri dinle
self.addEventListener("message", (event) => {
  if (event.data && event.data.type === "SKIP_WAITING") {
    self.skipWaiting();
    return;
  }

  // Giriş yapıldığında kullanıcının sık kullandığı sayfaları arka planda önbelleğe al
  if (event.data && event.data.type === "SAYFALARI_ONBELLEKLE") {
    const sayfalar = Array.isArray(event.data.sayfalar) ? event.data.sayfalar : [];
    if (sayfalar.length > 0) {
      event.waitUntil(sayfalariOnbellekle(sayfalar));
    }
  }
});

/**
 * Tam eşleşme bulunamazsa aynı ?page= değerine sahip başka bir kopyayı,
 * o da yoksa çevrimdışı sayfasını döndürür.
 */
function bordroOzelUrl(yol) {
  const u = new URL(yol, self.location.href);
  return u.searchParams.get("page") === "bordro" || u.pathname.includes("bordro-goster.php");
}

async function sayfaOnbellegindenBenzer(request) {
  if (bordroOzelUrl(request.url)) return caches.match(OFFLINE_URL);
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
    if (bordroOzelUrl(yol)) continue;
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

function sahaSayfasiniGetir(request) {
  const page = new URL(request.url).searchParams.get("page");
  if (page !== "kacak" && page !== "ihbar") return fetch(request);
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), 8000);
  return fetch(request, { signal: controller.signal }).finally(() => clearTimeout(timer));
}

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

  // Kuyruk kodunda sürüm karışması IndexedDB VersionError üretir. Bu dosyada
  // ignoreSearch/stale-while-revalidate kullanılmaz; çevrimiçiyken daima ağdaki
  // güncel kopya, çevrimdışıyken yalnızca mevcut worker sürümü döndürülür.
  const requestUrl = new URL(url);
  const kuyrukYolu = new URL("./assets/js/pwa-offline-queue.js", self.location.href).pathname;
  if (event.request.method === "GET" && requestUrl.origin === self.location.origin && requestUrl.pathname === kuyrukYolu) {
    const surumluIstek = new Request("./assets/js/pwa-offline-queue.js?v=" + KUYRUK_SURUM);
    event.respondWith(
      fetch(event.request, { cache: "no-store" })
        .then((response) => {
          if (response && response.ok) {
            caches.open(CACHE_NAME).then((cache) => cache.put(surumluIstek, response.clone())).catch(() => {});
          }
          return response;
        })
        .catch(() => caches.open(CACHE_NAME).then((cache) => cache.match(surumluIstek)))
    );
    return;
  }

  // Bordro sayfası/PDF için hiçbir önbellek veya benzer sayfa fallback'i kullanılmaz.
  if (bordroOzelUrl(event.request.url)) {
    event.respondWith(fetch(event.request, { cache: "no-store" }).catch(() =>
      caches.match(OFFLINE_URL).then(r => r || new Response("Bordro için internet bağlantısı gerekiyor.", {status:503}))
    ));
    return;
  }

  // iOS/WebKit, service worker uzerinden art arda gecirilen multipart POST
  // govdelerini zaman zaman PHP'ye ulasmadan iptal ediyor. Yazma/yukleme
  // isteklerini respondWith ile vekillemeyip dogrudan tarayicinin ag
  // katmanina birak. Kuyruk zaten cevrimdisi ve tekrar deneme durumlarini
  // yonetiyor; GET isteklerinde ise cevrimdisi JSON fallback'i korunur.
  const apiIstegi = url.includes("api.php") || url.includes("kacak-foto") || url.includes("foto-goruntule");
  if (apiIstegi && event.request.method !== "GET") {
    return;
  }

  // API GET isteklerini her zaman networkten al
  if (apiIstegi) {
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

  // POST/PUT/DELETE gibi istekler önbelleklenmez
  if (event.request.method !== "GET") {
    return;
  }

  // Navigasyon istekleri: ağdan al, kopyasını sakla; bağlantı yoksa
  // en son görüntülenen sürümü göster (saha personeli formu açabilsin).
  if (event.request.mode === "navigate") {
    event.respondWith(
      sahaSayfasiniGetir(event.request)
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

  // Diğer istekler: sürümlü (?v=) statik dosyalar tam URL eşleşmesiyle önbellekten,
  // aksi halde ağdan alınır. Bozuk/HTML yanıtlar asla önbelleğe yazılmaz ve
  // respondWith'e hiçbir zaman undefined verilmez (stilsiz sayfa sorunu).
  event.respondWith(statikIstegiCoz(event.request));
});

function statikYanitGecerli(request, response) {
  if (!response || response.status !== 200 || response.type === "opaque") return false;
  const tur = (response.headers.get("content-type") || "").toLowerCase();
  const yol = new URL(request.url).pathname;
  if (/\.(css|js)$/i.test(yol) && tur.indexOf("text/html") !== -1) return false;
  return true;
}

async function statikIstegiCoz(request) {
  const url = new URL(request.url);
  const ayniKok = url.origin === self.location.origin;
  const cache = await caches.open(CACHE_NAME);
  const tamEslesme = await cache.match(request);

  if (tamEslesme && ayniKok && url.searchParams.has("v")) {
    return tamEslesme;
  }

  try {
    const ag = await fetch(request);
    if (statikYanitGecerli(request, ag)) {
      cache.put(request, ag.clone()).catch(() => {});
      return ag;
    }
    if (ag && ag.ok === false && tamEslesme) return tamEslesme;
    return ag;
  } catch (e) {
    if (tamEslesme) return tamEslesme;
    const benzer = await cache.match(request, { ignoreSearch: true });
    if (benzer) return benzer;
    return new Response("", { status: 504, statusText: "Gateway Timeout" });
  }
}

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
    event.waitUntil(self.OfflineQueue.flush().then((result) => {
      if (result.gecici || result.kalan > 0 || result.hata) throw new Error("Gönderilmeyi bekleyen kayıtlar var.");
    }));
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
