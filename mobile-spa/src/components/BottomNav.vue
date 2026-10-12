<script setup>
import { Home, ChartNoAxesCombined, UserRound, UsersRound } from '@lucide/vue'

const items = [
  { to: '/', label: 'Ana Sayfa', icon: Home },
  { to: '/personel', label: 'Personel', icon: UsersRound },
  { to: '/raporlar', label: 'Raporlar', icon: ChartNoAxesCombined },
  { to: '/profil', label: 'Profil', icon: UserRound },
]
</script>

<template>
  <nav class="bottom-nav" aria-label="Ana gezinme">
    <RouterLink v-for="item in items" :key="item.to" :to="item.to" class="nav-link">
      <component :is="item.icon" :size="20" :stroke-width="2.2" />
      <span>{{ item.label }}</span>
    </RouterLink>
  </nav>
</template>
