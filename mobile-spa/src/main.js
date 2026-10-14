import { createApp } from 'vue'
import App from './App.vue'
import router from './router.js'
import './styles.css'
import './design-system.css'

// PWA durum çubuğu, merkezi tasarım sisteminin aktif yüzeyini kullanır.
router.afterEach(() => {
  const theme = getComputedStyle(document.documentElement).getPropertyValue('--ds-canvas').trim()
  if (theme) document.querySelector('meta[name="theme-color"]')?.setAttribute('content', theme)
})

createApp(App).use(router).mount('#app')

if ('serviceWorker' in navigator && import.meta.env.PROD) {
  window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}))
}
