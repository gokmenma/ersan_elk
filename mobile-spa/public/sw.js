const CACHE_NAME = 'ersan-mobile-shell-v19'
const SHELL = ['/', '/manifest.webmanifest', '/pwa-icons/icon-192.png', '/pwa-icons/icon-512.png']

self.addEventListener('install', event => {
  event.waitUntil(caches.open(CACHE_NAME).then(cache => cache.addAll(SHELL)).then(() => self.skipWaiting()))
})

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys => Promise.all(keys.filter(key => key !== CACHE_NAME).map(key => caches.delete(key))))
      .then(() => self.clients.claim())
  )
})

self.addEventListener('fetch', event => {
  const request = event.request
  const url = new URL(request.url)
  if (request.method !== 'GET' || url.origin !== self.location.origin || url.pathname.startsWith('/api/')) return

  if (request.mode === 'navigate') {
    event.respondWith(fetch(request).then(response => {
      const copy = response.clone()
      caches.open(CACHE_NAME).then(cache => cache.put('/', copy))
      return response
    }).catch(() => caches.match('/')))
    return
  }

  if (url.pathname.startsWith('/assets/') || url.pathname.startsWith('/pwa-icons/')) {
    event.respondWith(caches.match(request).then(cached => cached || fetch(request).then(response => {
      const copy = response.clone()
      caches.open(CACHE_NAME).then(cache => cache.put(request, copy))
      return response
    })))
  }
})

// Web Push Bildirimleri
self.addEventListener('push', event => {
  let data = {}
  if (event.data) {
    try {
      data = event.data.json()
    } catch (e) {
      data = { body: event.data.text() }
    }
  }

  const title = data.title || 'Ersan Elektrik'
  const options = {
    body: data.body || 'Yeni bir bildiriminiz var.',
    icon: data.icon || '/pwa-icons/icon-192.png',
    badge: '/pwa-icons/icon-192.png',
    vibrate: [100, 50, 100],
    data: {
      url: data.url || '/',
      dateOfArrival: Date.now()
    },
    actions: [
      { action: 'open', title: 'Görüntüle' },
      { action: 'close', title: 'Kapat' }
    ]
  }

  event.waitUntil(self.registration.showNotification(title, options))
})

// Bildirime Tıklama Olayı
self.addEventListener('notificationclick', event => {
  event.notification.close()

  if (event.action === 'close') return

  let targetUrl = (event.notification.data && event.notification.data.url) || '/'
  const urlObj = new URL(targetUrl, self.location.origin)
  const fullSearch = urlObj.pathname + urlObj.search

  if (fullSearch.includes('talepler')) {
    targetUrl = new URL('/#/talepler', self.location.origin).href
  } else if (fullSearch.includes('km-onay')) {
    targetUrl = new URL('/#/km-onaylari', self.location.origin).href
  } else if (fullSearch.includes('arac')) {
    targetUrl = new URL('/#/arac-takip', self.location.origin).href
  } else if (fullSearch.includes('personel')) {
    targetUrl = new URL('/#/personel', self.location.origin).href
  } else if (fullSearch.includes('nobet')) {
    targetUrl = new URL('/#/nobet-onay', self.location.origin).href
  } else if (fullSearch.includes('kacak')) {
    targetUrl = new URL('/#/kacak-kontrol', self.location.origin).href
  } else if (fullSearch.includes('ihbar')) {
    targetUrl = new URL('/#/ihbar', self.location.origin).href
  } else if (fullSearch.includes('puantaj')) {
    targetUrl = new URL('/#/puantaj', self.location.origin).href
  } else if (fullSearch.includes('fatura') || fullSearch.includes('efatura')) {
    targetUrl = new URL('/#/fatura', self.location.origin).href
  } else if (fullSearch.includes('kasa') || fullSearch.includes('gelir-gider')) {
    targetUrl = new URL('/#/kasa', self.location.origin).href
  } else if (fullSearch.includes('bordro') || fullSearch.includes('maas')) {
    targetUrl = new URL('/#/bordro', self.location.origin).href
  } else if (!targetUrl.includes('#') && urlObj.pathname === '/') {
    targetUrl = new URL('/#/', self.location.origin).href
  }

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then(windowClients => {
      for (const client of windowClients) {
        if (client.url.startsWith(self.location.origin) && 'focus' in client) {
          if ('navigate' in client) {
            client.navigate(targetUrl)
          }
          return client.focus()
        }
      }
      if (clients.openWindow) {
        return clients.openWindow(targetUrl)
      }
    })
  )
})
