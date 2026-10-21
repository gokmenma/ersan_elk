import { createApp } from 'vue'
import App from './App.vue'
import router from './router.js'
import './styles.css'
import './design-system.css'
import { initNotificationsWatcher } from './services/notifications.js'
import { auth } from './services/auth.js'

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
  const theme = getComputedStyle(document.documentElement).getPropertyValue('--ds-canvas').trim()
  if (theme) document.querySelector('meta[name="theme-color"]')?.setAttribute('content', theme)
})

createApp(App).use(router).mount('#app')

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

