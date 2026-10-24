import { createApp } from 'vue'
import App from './App.vue'
import router from './router.js'
import './styles.css'
import './design-system.css'
import 'sweetalert2/dist/sweetalert2.min.css'
import { initNotificationsWatcher } from './services/notifications.js'
import { auth } from './services/auth.js'
import { preferences } from './services/preferences.js'

preferences.apply()

// Mobil ve masaüstü aynı API'leri kullandığı için denetim loglarında
// isteğin kaynağını sunucuya her aynı-origin API çağrısında bildir.
const nativeFetch = window.fetch.bind(window)
window.fetch = (input, init = {}) => {
  const requestUrl = typeof input === 'string' || input instanceof URL ? input : input.url
  const url = new URL(requestUrl, window.location.href)
  if (url.origin !== window.location.origin || !url.pathname.startsWith('/api/')) {
    return nativeFetch(input, init)
  }

  const inheritedHeaders = input instanceof Request ? input.headers : undefined
  const headers = new Headers(init.headers || inheritedHeaders)
  headers.set('X-Client-Source', 'mobile-spa')
  return nativeFetch(input, { ...init, headers })
}

// PWA durum çubuğu, merkezi tasarım sisteminin aktif yüzeyini kullanır.
router.afterEach(() => {
  preferences.syncThemeColorMeta()
})

const splashStart = performance.now()
createApp(App).use(router).mount('#app')

router.isReady().finally(() => {
  const splashEl = document.getElementById('app-splash')
  if (!splashEl) return
  const elapsed = performance.now() - splashStart
  const remaining = Math.max(0, 850 - elapsed)
  setTimeout(() => {
    splashEl.classList.add('is-hiding')
    setTimeout(() => {
      splashEl.remove()
    }, 520)
  }, remaining)
})

// Service Worker Kaydı (Push bildirimleri ve çevrimdışı kabuk desteği)
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js').then((reg) => {
      reg.update()
      if (auth.state.authenticated) {
        initNotificationsWatcher()
      }
    }).catch((err) => {
      console.warn('Service Worker kaydı yapılamadı:', err)
    })
  })
}

router.afterEach((to) => {
  if (to.name !== 'login' && auth.state.authenticated) {
    initNotificationsWatcher()
  }

  if (to.meta.requiresAuth && to.meta.requiresCompany && auth.state.authenticated && auth.state.company) {
    auth.logPageView(String(to.name || '')).catch(() => {})
  }
})

