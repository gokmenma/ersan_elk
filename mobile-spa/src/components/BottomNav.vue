<script setup>
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  Banknote,
  CalendarCheck,
  CalendarRange,
  Car,
  ChartNoAxesCombined,
  ChevronRight,
  ClipboardCheck,
  ClipboardList,
  CreditCard,
  FileText,
  Gauge,
  Headset,
  Home,
  Landmark,
  LayoutGrid,
  LogOut,
  Megaphone,
  Monitor,
  ReceiptText,
  ShieldAlert,
  UserRound,
  UsersRound,
  X,
} from '@lucide/vue'
import { auth } from '../services/auth.js'

const route = useRoute()
const router = useRouter()
const menuOpen = ref(false)

const items = [
  { to: '/', label: 'Ana Sayfa', icon: Home, slot: 1 },
  { to: '/personel', label: 'Personel', icon: UsersRound, permission: 'personnel', slot: 2 },
  { label: 'Daha Fazla', icon: LayoutGrid, center: true, slot: 3 },
  { to: '/raporlar', label: 'Raporlar', icon: ChartNoAxesCombined, permission: 'reports', slot: 4 },
  { to: '/talepler', label: 'Talepler', icon: ClipboardList, permission: 'requests', slot: 5 },
]
const visibleItems = computed(() => items.filter(item => !item.permission || auth.state.permissions[item.permission]))

function isItemActive(item) {
  if (item.center) return menuOpen.value
  if (menuOpen.value) return false
  if (item.to === '/') return route.path === '/'
  return route.path.startsWith(item.to)
}

const operations = [
  { permission: 'tasks', label: 'Görev Takibi', icon: ClipboardCheck, bg: '#f5f3ff', color: '#7c3aed', to: '/gorevler' },
  { permission: 'invoices', label: 'E-Fatura & Finans', icon: ReceiptText, bg: '#eef2ff', color: '#4f46e5', href: '/mobile/index.php?p=efatura' },
  { permission: 'cash', label: 'Kasa Yönetimi', icon: Landmark, bg: '#fffbeb', color: '#d97706', href: '/mobile/index.php?p=gelir-gider' },
  { permission: 'current_accounts', label: 'Cari Takip', icon: CreditCard, bg: '#ecfdf5', color: '#059669', to: '/cari-takip' },
  { permission: 'vehicles', label: 'Araç Takip', icon: Car, bg: '#f0fdf4', color: '#16a34a', href: '/mobile/index.php?p=arac' },
  { permission: 'vehicles', label: 'KM Onayları', icon: Gauge, bg: '#ecfeff', color: '#0891b2', href: '/mobile/index.php?p=km-onaylari' },
  { permission: 'documents', label: 'Evrak Takip', icon: FileText, bg: '#f0f9ff', color: '#0284c7', href: '/mobile/index.php?p=evrak-takip' },
  { permission: 'shifts', label: 'Nöbet Onay', icon: CalendarCheck, bg: '#fff1f2', color: '#e11d48', href: '/mobile/index.php?p=nobet' },
  { permission: 'reports', label: 'Puantaj', icon: CalendarRange, bg: '#ecfdf5', color: '#059669', href: '/mobile/index.php?p=puantaj' },
  { permission: 'payroll', label: 'Bordro', icon: Banknote, bg: '#eef2ff', color: '#4338ca', href: '/mobile/index.php?p=bordro' },
  { permission: 'notices', label: 'İhbar Yönetimi', icon: Megaphone, bg: '#fff7ed', color: '#ea580c', href: '/mobile/index.php?p=ihbar' },
  { permission: 'illegal', label: 'Kaçak Kontrol', icon: ShieldAlert, bg: '#fef2f2', color: '#dc2626', href: '/mobile/index.php?p=kacak' },
  { permission: 'support', label: 'Destek & Yardım', icon: Headset, bg: '#eef2ff', color: '#4f46e5', href: '/mobile/index.php?p=yardim' },
  { label: 'Ana Sayfa', icon: Home, bg: '#eff6ff', color: '#2563eb', to: '/' },
  { label: 'Profil & Ayarlar', icon: UserRound, bg: '#f0fdfa', color: '#0d9488', to: '/profil' },
]
const visibleOperations = computed(() => operations.filter(item => !item.permission || auth.state.permissions[item.permission]))

function selectOperation(op) {
  menuOpen.value = false
  if (op.to) {
    router.push(op.to)
  } else if (op.href) {
    window.location.href = op.href
  }
}

async function handleLogout() {
  menuOpen.value = false
  try {
    await auth.logout()
    await router.replace('/giris')
  } catch {
    window.location.href = '/logout.php'
  }
}
</script>

<template>
  <Transition name="nav-sheet">
    <div v-if="menuOpen" class="nav-sheet-layer" @click.self="menuOpen = false">
      <section class="nav-sheet" role="dialog" aria-modal="true" aria-label="Daha Fazla">
        <header>
          <div>
            <span>HIZLI ERİŞİM & MENÜ</span>
            <h2>Daha Fazla</h2>
          </div>
          <button type="button" aria-label="Kapat" @click="menuOpen = false">
            <X :size="19" />
          </button>
        </header>

        <div class="nav-sheet__content">
          <!-- Profil Özeti (Mobil görünüm gibi en üstte) -->
          <div class="nav-sheet-profile" @click="selectOperation({ to: '/profil' })">
            <div class="nav-sheet-profile__avatar">
              {{ auth.state.user?.initials || 'MA' }}
            </div>
            <div class="nav-sheet-profile__info">
              <span class="nav-sheet-profile__badge">KULLANICI PROFİLİ</span>
              <strong>{{ auth.state.user?.name || 'Kullanıcı' }}</strong>
              <small>{{ auth.state.company?.name || 'Ersan Elektrik' }}</small>
            </div>
            <ChevronRight :size="18" class="nav-sheet-profile__arrow" />
          </div>

          <!-- Mobil Görünümdeki İşlemler Grid'i -->
          <div class="nav-sheet-grid">
            <button
              v-for="op in visibleOperations"
              :key="op.label"
              type="button"
              class="nav-sheet-item"
              @click="selectOperation(op)"
            >
              <span class="nav-sheet-item__icon" :style="{ background: op.bg, color: op.color }">
                <component :is="op.icon" :size="20" />
              </span>
              <strong class="nav-sheet-item__label">{{ op.label }}</strong>
            </button>
          </div>

          <!-- Alt Menü / Aksiyonlar -->
          <div class="nav-sheet-footer">
            <button
              type="button"
              class="nav-sheet-footer__btn"
              @click="selectOperation({ href: '/index.php?force_desktop=1&p=home' })"
            >
              <Monitor :size="18" />
              <span>Masaüstü Modu</span>
            </button>
            <button
              type="button"
              class="nav-sheet-footer__btn nav-sheet-footer__btn--danger"
              @click="handleLogout"
            >
              <LogOut :size="18" />
              <span>Güvenli Çıkış</span>
            </button>
          </div>
        </div>
      </section>
    </div>
  </Transition>

  <nav class="bottom-nav" aria-label="Ana gezinme">
    <div class="bottom-nav__tabs">
      <template v-for="item in visibleItems" :key="item.label">
        <button
          v-if="item.center"
          class="nav-link nav-link--center"
          :style="{ gridColumn: item.slot }"
          :class="{ 'is-active': isItemActive(item) }"
          type="button"
          aria-label="Daha fazla menüsünü aç"
          :aria-expanded="menuOpen"
          @click="menuOpen = !menuOpen"
        >
          <span class="nav-icon">
            <component :is="item.icon" :size="22" :stroke-width="2.3" />
          </span>
          <span class="nav-label">{{ item.label }}</span>
        </button>
        <RouterLink
          v-else
          :to="item.to"
          class="nav-link"
          :style="{ gridColumn: item.slot }"
          :class="{ 'is-active': isItemActive(item) }"
          :aria-label="item.label"
        >
          <span class="nav-icon">
            <component :is="item.icon" :size="21" :stroke-width="isItemActive(item) ? 2.4 : 1.9" />
          </span>
          <span class="nav-label">{{ item.label }}</span>
        </RouterLink>
      </template>
    </div>
    <div class="bottom-nav__home-bar" aria-hidden="true"></div>
  </nav>
</template>
