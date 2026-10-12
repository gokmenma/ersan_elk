<script setup>
import { Bell, Search, SlidersHorizontal } from '@lucide/vue'

defineProps({
  eyebrow: { type: String, default: '12 Ekim, Pazartesi' },
  title: { type: String, default: 'Merhaba, Gökhan' },
  subtitle: { type: String, default: 'Bugün sahada neler oluyor?' },
  search: { type: Boolean, default: true },
})
</script>

<template>
  <header class="topbar">
    <div class="topbar-row">
      <div class="avatar" aria-hidden="true">GE</div>
      <div class="topbar-copy">
        <span class="eyebrow">{{ eyebrow }}</span>
        <h1>{{ title }}</h1>
        <p>{{ subtitle }}</p>
      </div>
      <button class="icon-button has-dot" type="button" aria-label="Bildirimler">
        <Bell :size="20" />
      </button>
    </div>

    <div v-if="search" class="search-box">
      <Search :size="18" />
      <input aria-label="Uygulamada ara" placeholder="Personel, araç veya işlem ara" />
      <button type="button" aria-label="Arama filtreleri"><SlidersHorizontal :size="17" /></button>
    </div>
  </header>
</template>
