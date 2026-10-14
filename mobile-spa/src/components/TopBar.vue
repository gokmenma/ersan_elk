<script setup>
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowLeft, Bell, Mail, Search, SlidersHorizontal, Zap } from '@lucide/vue'
import { auth } from '../services/auth.js'

const props = defineProps({
  eyebrow: { type: String, default: '' },
  title: { type: String, default: '' },
  subtitle: { type: String, default: '' },
  back: { type: Boolean, default: null }, // null = auto-detect based on route
  search: { type: Boolean, default: false },
})

const router = useRouter()
const route = useRoute()

const showBack = computed(() => {
  if (props.back !== null) return props.back
  return route.path !== '/'
})

const username = computed(() => auth.state.user?.username || 'Kullanıcı')
const initials = computed(() => auth.state.user?.initials || username.value.slice(0, 2).toLocaleUpperCase('tr-TR'))
const heading = computed(() => props.title || `Merhaba, ${username.value}`)
</script>

<template>
  <header class="clay-topbar-wrapper">
    <!-- 1. Üst Navigasyon Çubuğu (Marka / Geri Butonu & Sağ Aksiyon Butonları) -->
    <div class="clay-topbar">
      <div v-if="showBack" class="clay-nav-left">
        <button type="button" class="clay-circle-btn" aria-label="Geri Dön" @click="router.back()">
          <ArrowLeft :size="18" />
        </button>
      </div>
      <div v-else class="clay-brand-wrap" role="button" tabindex="0" @click="router.push('/')">
        <span class="clay-brand-icon"><Zap :size="16" /></span>
        <span class="clay-brand-name">ersan</span>
      </div>

      <div class="clay-actions-row">
        <slot name="actions">
          <button type="button" class="clay-circle-btn" aria-label="Destek & Mesajlar" @click="router.push('/talepler')">
            <Mail :size="17" />
          </button>
          <button type="button" class="clay-circle-btn has-dot" aria-label="Bildirimler">
            <Bell :size="17" />
            <span class="clay-unread-dot"></span>
          </button>
          <div class="clay-avatar-circle" title="Kullanıcı Profili" @click="router.push('/profil')">
            <span>{{ initials }}</span>
          </div>
        </slot>
      </div>
    </div>

    <!-- 2. Sayfa Başlığı ve Açıklaması -->
    <div v-if="eyebrow || heading || subtitle" class="clay-page-header">
      <span v-if="eyebrow" class="clay-page-eyebrow">{{ eyebrow }}</span>
      <h1 class="clay-page-title">{{ heading }}</h1>
      <p v-if="subtitle" class="clay-page-subtitle">{{ subtitle }}</p>
    </div>

    <!-- 3. Opsiyonel Arama Kutusu -->
    <div v-if="search" class="clay-search-bar">
      <Search :size="18" />
      <input aria-label="Uygulamada ara" placeholder="Personel, araç veya işlem ara..." class="clay-search-input" />
      <button type="button" class="clay-search-action" aria-label="Arama filtreleri">
        <SlidersHorizontal :size="16" />
      </button>
    </div>
  </header>
</template>

<style scoped>
.clay-topbar-wrapper {
  margin-bottom: 16px;
}
.clay-nav-left {
  display: flex;
  align-items: center;
}
</style>
