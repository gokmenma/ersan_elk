<script setup>
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowLeft, Bell, ChevronDown, ClipboardList, Search, Zap } from '@lucide/vue'
import { auth } from '../services/auth.js'
import { notificationsState, toggleNotificationsDrawer } from '../services/notifications.js'
import { canGlobalSearch, openSearchSheet } from '../services/search.js'

const props = defineProps({
  eyebrow: { type: String, default: '' },
  title: { type: String, default: '' },
  subtitle: { type: String, default: '' },
  back: { type: Boolean, default: null },
  search: { type: Boolean, default: false },
  backHandler: { type: Function, default: null },
})
const router = useRouter()
const route = useRoute()
const showBack = computed(() => props.back !== null ? props.back : route.path !== '/')
const username = computed(() => auth.state.user?.name || auth.state.user?.username || 'Kullanıcı')
const initials = computed(() => auth.state.user?.initials || username.value.slice(0, 2).toLocaleUpperCase('tr-TR'))
const heading = computed(() => props.title || `Merhaba, ${username.value.split(' ')[0]}`)
const emit = defineEmits(['search'])
function goBack() {
  if (props.backHandler && props.backHandler() === true) return
  if (window.history.state?.back) router.back()
  else router.push('/')
}
</script>

<template>
  <header class="clay-topbar-wrapper" :class="{ 'has-inline-header': showBack && heading }">
    <div class="clay-topbar" :class="{ 'has-inline-header': showBack && heading }">
      <div class="clay-topbar-main">
        <button v-if="showBack" type="button" class="clay-circle-btn" aria-label="Geri dön" @click="goBack">
          <ArrowLeft :size="20" />
        </button>
        <button v-else type="button" class="clay-company-chip" aria-label="Firma seçimini aç" @click="router.push('/firma-secimi')">
          <span class="clay-brand-icon clay-brand-icon--3d">
            <img src="/images/logo-3d-splash.png?v=3" alt="" class="clay-brand-logo-img" />
          </span>
          <span>{{ auth.state.company?.name || 'Ersan Elektrik' }}</span>
          <ChevronDown :size="14" />
        </button>

        <div v-if="showBack && heading" class="clay-topbar-title-block">
          <span v-if="eyebrow" class="clay-topbar-eyebrow">{{ eyebrow }}</span>
          <h1 class="clay-topbar-title">{{ heading }}</h1>
          <p v-if="subtitle" class="clay-topbar-subtitle">{{ subtitle }}</p>
        </div>
      </div>

      <div class="clay-actions-row">
        <slot name="actions">
          <button
            v-if="canGlobalSearch"
            type="button"
            class="clay-circle-btn"
            aria-label="Genel arama"
            title="Genel Arama"
            @click="openSearchSheet"
          >
            <Search :size="19" />
          </button>
          <button
            type="button"
            class="clay-circle-btn topbar-bell-btn"
            aria-label="Bildirimleri aç"
            title="Bildirimler"
            @click="toggleNotificationsDrawer"
          >
            <Bell :size="19" />
            <span
              v-if="notificationsState.unreadCount > 0"
              class="topbar-bell-badge"
            >
              {{ notificationsState.unreadCount > 99 ? '99+' : notificationsState.unreadCount }}
            </span>
          </button>
          <button v-if="auth.state.permissions.requests" type="button" class="clay-circle-btn" aria-label="Talepleri aç" @click="router.push('/talepler')">
            <ClipboardList :size="19" />
          </button>
          <button type="button" class="clay-avatar-circle" aria-label="Profil ve ayarları aç" @click="router.push('/profil')">
            {{ initials }}
          </button>
        </slot>
      </div>
    </div>
    <div v-if="!showBack && (eyebrow || heading || subtitle)" class="clay-page-header">
      <span v-if="eyebrow" class="clay-page-eyebrow">{{ eyebrow }}</span>
      <h1 class="clay-page-title">{{ heading }}</h1>
      <p v-if="subtitle" class="clay-page-subtitle">{{ subtitle }}</p>
    </div>
    <label v-if="search" class="clay-search-bar">
      <Search :size="18" />
      <input type="search" aria-label="Bu sayfada ara" placeholder="Bu sayfada ara…" class="clay-search-input" @input="emit('search', $event.target.value)" />
    </label>
  </header>
</template>

<style scoped>
.topbar-bell-btn {
  position: relative;
}

.topbar-bell-badge {
  position: absolute;
  top: -3px;
  right: -3px;
  min-width: 17px;
  height: 17px;
  padding: 0 4px;
  border-radius: 999px;
  background: #ef4444;
  color: #ffffff;
  font: 800 10px/17px var(--ds-font-main, sans-serif);
  text-align: center;
  border: 2px solid var(--ds-surface, var(--dsx-bd-ffffff));
  box-shadow: 0 2px 5px rgba(239, 68, 68, 0.4);
  pointer-events: none;
  box-sizing: border-box;
}
</style>
