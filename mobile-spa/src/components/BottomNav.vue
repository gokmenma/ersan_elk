<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
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
const sheet = ref(null)
let previousOverflow = ''
let returnFocus = null
watch(menuOpen, async (open) => {
  if (open) {
    returnFocus = document.activeElement
    previousOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    await nextTick()
    sheet.value?.querySelector('button')?.focus()
  } else {
    document.body.style.overflow = previousOverflow
    if (returnFocus?.isConnected) returnFocus.focus()
  }
})
watch(() => route.fullPath, () => { menuOpen.value = false })
onBeforeUnmount(() => { if (menuOpen.value) document.body.style.overflow = previousOverflow })
function handleSheetKeydown(event) {
  if (event.key === 'Escape') { menuOpen.value = false; return }
  if (event.key !== 'Tab') return
  const buttons = [...sheet.value.querySelectorAll('button, a[href], [tabindex="0"]')]
  const first = buttons[0], last = buttons.at(-1)
  if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus() }
  else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus() }
}

const items = [
  { to: '/', label: 'Ana Sayfa', icon: Home },
  { to: '/personel', label: 'Personel', icon: UsersRound, permission: 'personnel' },
  { label: 'Daha Fazla', icon: LayoutGrid, center: true },
  { to: '/raporlar', label: 'Raporlar', icon: ChartNoAxesCombined, permission: 'reports' },
  { to: '/talepler', label: 'Talepler', icon: ClipboardList, permission: 'requests' },
]
const visibleItems = computed(() => items.filter(item => !item.permission || auth.state.permissions[item.permission]))

function isItemActive(item) {
  if (item.center) return menuOpen.value
  if (menuOpen.value) return false
  if (item.to === '/') return route.path === '/'
  return route.path.startsWith(item.to)
}

const operations = [
  { permission: 'tasks', label: 'Görev Takibi', icon: ClipboardCheck, to: '/gorevler' },
  { permission: 'invoices', label: 'E-Fatura & Finans', icon: ReceiptText, to: '/fatura' },
  { permission: 'cash', label: 'Kasa Yönetimi', icon: Landmark, to: '/kasa' },
  { permission: 'current_accounts', label: 'Cari Takip', icon: CreditCard, to: '/cari-takip' },
  { permission: 'vehicles', label: 'Araç Takip', icon: Car, href: '/mobile/index.php?p=arac' },
  { permission: 'vehicles', label: 'KM Onayları', icon: Gauge, href: '/mobile/index.php?p=km-onaylari' },
  { permission: 'documents', label: 'Evrak Takip', icon: FileText, href: '/mobile/index.php?p=evrak-takip' },
  { permission: 'shifts', label: 'Nöbet Onay', icon: CalendarCheck, href: '/mobile/index.php?p=nobet' },
  { permission: 'reports', label: 'Puantaj', icon: CalendarRange, href: '/mobile/index.php?p=puantaj' },
  { permission: 'payroll', label: 'Bordro', icon: Banknote, to: '/bordro' },
  { permission: 'notices', label: 'İhbar Yönetimi', icon: Megaphone, href: '/mobile/index.php?p=ihbar' },
  { permission: 'illegal', label: 'Kaçak Kontrol', icon: ShieldAlert, href: '/mobile/index.php?p=kacak' },
  { permission: 'support', label: 'Destek & Yardım', icon: Headset, href: '/mobile/index.php?p=yardim' },
  { label: 'Ana Sayfa', icon: Home, to: '/' },
  { label: 'Profil & Ayarlar', icon: UserRound, to: '/profil' },
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
    <div v-if="menuOpen" class="nav-sheet-layer" @keydown="handleSheetKeydown" @click.self="menuOpen = false">
      <section id="more-menu" ref="sheet" class="nav-sheet" role="dialog" aria-modal="true" aria-label="Daha Fazla">
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
          <button type="button" class="nav-sheet-profile" @click="selectOperation({ to: '/profil' })">
            <div class="nav-sheet-profile__avatar">
              {{ auth.state.user?.initials || 'EE' }}
            </div>
            <div class="nav-sheet-profile__info">
              <span class="nav-sheet-profile__badge">KULLANICI PROFİLİ</span>
              <strong>{{ auth.state.user?.name || 'Kullanıcı' }}</strong>
              <small>{{ auth.state.company?.name || 'Ersan Elektrik' }}</small>
            </div>
            <ChevronRight :size="18" class="nav-sheet-profile__arrow" />
          </button>

          <!-- Mobil Görünümdeki İşlemler Grid'i -->
          <div class="nav-sheet-grid">
            <button
              v-for="op in visibleOperations"
              :key="op.label"
              type="button"
              class="nav-sheet-item"
              @click="selectOperation(op)"
            >
              <span class="nav-sheet-item__icon">
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
    <div class="bottom-nav__tabs" :style="{ '--nav-count': visibleItems.length }">
      <template v-for="item in visibleItems" :key="item.label">
        <button
          v-if="item.center"
          class="nav-link nav-link--center"
          :class="{ 'is-active': isItemActive(item) }"
          type="button"
          aria-controls="more-menu"
          aria-haspopup="dialog"
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
          :class="{ 'is-active': isItemActive(item) }"
          :aria-label="item.label"
          :aria-current="isItemActive(item) ? 'page' : undefined"
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
