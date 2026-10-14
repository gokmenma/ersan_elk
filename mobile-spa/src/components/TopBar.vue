<script setup>
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowLeft, ChevronDown, ClipboardList, Search, Zap } from '@lucide/vue'
import { auth } from '../services/auth.js'

const props = defineProps({
  eyebrow: { type: String, default: '' },
  title: { type: String, default: '' },
  subtitle: { type: String, default: '' },
  back: { type: Boolean, default: null },
  search: { type: Boolean, default: false },
})
const router = useRouter()
const route = useRoute()
const showBack = computed(() => props.back !== null ? props.back : route.path !== '/')
const username = computed(() => auth.state.user?.name || auth.state.user?.username || 'Kullanıcı')
const initials = computed(() => auth.state.user?.initials || username.value.slice(0, 2).toLocaleUpperCase('tr-TR'))
const heading = computed(() => props.title || `Merhaba, ${username.value.split(' ')[0]}`)
const emit = defineEmits(['search'])
function goBack() {
  if (window.history.state?.back) router.back()
  else router.push('/')
}
</script>

<template>
  <header class="clay-topbar-wrapper">
    <div class="clay-topbar">
      <button v-if="showBack" type="button" class="clay-circle-btn" aria-label="Geri dön" @click="goBack">
        <ArrowLeft :size="20" />
      </button>
      <button v-else type="button" class="clay-company-chip" aria-label="Firma seçimini aç" @click="router.push('/firma-secimi')">
        <span class="clay-brand-icon"><Zap :size="16" /></span>
        <span>{{ auth.state.company?.name || 'Ersan Elektrik' }}</span>
        <ChevronDown :size="14" />
      </button>
      <div class="clay-actions-row">
        <slot name="actions">
          <button v-if="auth.state.permissions.requests" type="button" class="clay-circle-btn" aria-label="Talepleri aç" @click="router.push('/talepler')">
            <ClipboardList :size="19" />
          </button>
          <button type="button" class="clay-avatar-circle" aria-label="Profil ve ayarları aç" @click="router.push('/profil')">
            {{ initials }}
          </button>
        </slot>
      </div>
    </div>
    <div v-if="eyebrow || heading || subtitle" class="clay-page-header">
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
