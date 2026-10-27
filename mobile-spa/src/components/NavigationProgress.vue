<script setup>
import { navigation } from '../services/navigation.js'
</script>

<template>
  <div class="nav-progress" :class="{ 'is-active': navigation.visible }" role="progressbar" aria-hidden="true">
    <span class="nav-progress__bar"></span>
  </div>
  <Transition name="page">
    <div v-if="navigation.slow" class="nav-slow-hint" role="status" aria-live="polite">
      <span class="nav-slow-hint__spinner"></span>
      Bağlantı yavaş, sayfa yükleniyor…
    </div>
  </Transition>
</template>
