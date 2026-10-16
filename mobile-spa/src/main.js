import { createApp } from 'vue'
import App from './App.vue'
import router from './router.js'
import './styles.css'
import './design-system.css'
import { initNotificationsWatcher } from './services/notifications.js'
import { auth } from './services/auth.js'

// PWA durum çubuğu, merkezi tasarım sisteminin aktif yüzeyini kullanır.
router.afterEach(() => {
  const theme = getComputedStyle(document.documentElement).getPropertyValue('--ds-canvas').trim()
  if (theme) document.querySelector('meta[name="theme-color"]')?.setAttribute('content', theme)
})

createApp(App).use(router).mount('#app')

// Service Worker Kaydı (Push bildirimleri ve çevrimdışı kabuk desteği)
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js').then(() => {
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
})

