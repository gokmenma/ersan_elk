<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { ChartNoAxesCombined, ClipboardCheck, Home, LayoutGrid, UserRound, UsersRound, X } from '@lucide/vue'

const router = useRouter()
const menuOpen = ref(false)

const items = [
  { to: '/personel', label: 'Personel', icon: UsersRound },
  { to: '/gorevler', label: 'Görevler', icon: ClipboardCheck },
  { label: 'İşlemler', icon: LayoutGrid, center: true },
  { to: '/raporlar', label: 'Raporlar', icon: ChartNoAxesCombined },
  { to: '/profil', label: 'Profil', icon: UserRound },
]

const operations = [
  { to: '/', label: 'Ana sayfa', icon: Home },
  { to: '/personel', label: 'Personel', icon: UsersRound },
  { to: '/gorevler', label: 'Görevler', icon: ClipboardCheck },
  { to: '/raporlar', label: 'Raporlar', icon: ChartNoAxesCombined },
  { to: '/profil', label: 'Profil', icon: UserRound },
]

function selectOperation(path) {
  menuOpen.value = false
  router.push(path)
}
</script>

<template>
  <Transition name="nav-sheet">
    <div v-if="menuOpen" class="nav-sheet-layer" @click.self="menuOpen = false">
      <section class="nav-sheet" role="dialog" aria-modal="true" aria-label="Diğer işlemler">
        <header><div><span>HIZLI ERİŞİM</span><h2>Diğer işlemler</h2></div><button type="button" aria-label="Kapat" @click="menuOpen = false"><X :size="19" /></button></header>
        <div class="nav-sheet-grid">
          <button v-for="operation in operations" :key="operation.to" type="button" @click="selectOperation(operation.to)">
            <span><component :is="operation.icon" :size="22" /></span><strong>{{ operation.label }}</strong>
          </button>
        </div>
      </section>
    </div>
  </Transition>
  <nav class="bottom-nav" aria-label="Ana gezinme">
    <template v-for="item in items" :key="item.label">
      <button
        v-if="item.center"
        class="nav-link nav-link--center"
        type="button"
        aria-label="Diğer işlemleri aç"
        :aria-expanded="menuOpen"
        @click="menuOpen = !menuOpen"
      >
        <span class="nav-icon"><component :is="item.icon" :size="25" :stroke-width="2.15" /></span>
        <span class="nav-label">{{ item.label }}</span>
      </button>
      <RouterLink
      v-else
      :to="item.to"
      class="nav-link"
      :aria-label="item.label"
    >
      <span class="nav-icon"><component :is="item.icon" :size="22" :stroke-width="2.15" /></span>
      <span class="nav-label">{{ item.label }}</span>
    </RouterLink>
    </template>
  </nav>
</template>
