<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { Download, X } from '@lucide/vue'

const installEvent = ref(null)
const visible = ref(false)

function onInstallAvailable(event) {
  event.preventDefault()
  installEvent.value = event
  visible.value = true
}

async function install() {
  if (!installEvent.value) return
  await installEvent.value.prompt()
  await installEvent.value.userChoice
  installEvent.value = null
  visible.value = false
}

onMounted(() => {
  window.addEventListener('beforeinstallprompt', onInstallAvailable)
  window.addEventListener('appinstalled', () => { visible.value = false }, { once: true })
})

onBeforeUnmount(() => window.removeEventListener('beforeinstallprompt', onInstallAvailable))
</script>

<template>
  <Transition name="install-prompt">
    <aside v-if="visible" class="pwa-install" aria-label="Uygulama yükleme önerisi">
      <span class="pwa-install__icon pwa-install__icon--3d">
        <img src="/images/logo-3d.png?v=3" alt="" class="pwa-install__logo-img" />
      </span>
      <span class="pwa-install__copy"><strong>Ersan Mobile</strong><small>Ana ekranınıza ekleyin</small></span>
      <button class="pwa-install__button" type="button" @click="install">Yükle</button>
      <button class="pwa-install__close" type="button" aria-label="Kapat" @click="visible = false"><X :size="18" /></button>
    </aside>
  </Transition>
</template>
