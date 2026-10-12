import { createApp } from 'vue'
import App from './App.vue'
import router from './router.js'
import './styles.css'

// Android/Chromium PWA durum çubuğunu aktif ekranın yüzeyiyle bütünleştirir.
router.afterEach((to) => {
  const theme = to.meta.chrome === false && to.name === 'login' ? '#0b1c3b' : '#f7f7f4'
  document.querySelector('meta[name="theme-color"]')?.setAttribute('content', theme)
})

createApp(App).use(router).mount('#app')

if ('serviceWorker' in navigator && import.meta.env.PROD) {
  window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}))
}
