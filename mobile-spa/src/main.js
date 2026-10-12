import { createApp } from 'vue'
import App from './App.vue'
import router from './router.js'
import './styles.css'
import './design-system.css'

// Android/Chromium PWA durum çubuğunu aktif ekranın yüzeyiyle bütünleştirir.
router.afterEach((to) => {
  const routeThemes = { login: '#0b1c3b', personnel: '#174b82', 'personnel-create': '#17345f', 'personnel-edit': '#17345f' }
  const theme = routeThemes[to.name] || '#f7f7f4'
  document.querySelector('meta[name="theme-color"]')?.setAttribute('content', theme)
})

createApp(App).use(router).mount('#app')

if ('serviceWorker' in navigator && import.meta.env.PROD) {
  window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}))
}
