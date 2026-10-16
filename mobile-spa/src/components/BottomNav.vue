<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  Banknote,
  CalendarCheck,
  CalendarDays,
  CalendarRange,
  Car,
  ChartNoAxesCombined,
  ChevronRight,
  ClipboardCheck,
  ClipboardList,
  CreditCard,
  Edit3,
  FileText,
  Fuel,
  Gauge,
  Headset,
  Home,
  Inbox,
  Landmark,
  Layers,
  LayoutGrid,
  LogOut,
  Megaphone,
  Monitor,
  ReceiptText,
  Send,
  ShieldAlert,
  Sparkles,
  UserCheck,
  UserRound,
  UsersRound,
  Wallet,
  Wrench,
  X,
} from '@lucide/vue'
import { auth } from '../services/auth.js'
import {
  requestsState,
  closeRequestsDrawer,
  toggleRequestsDrawer,
  setRequestsTab,
} from '../services/requests.js'
import {
  aracState,
  closeAracDrawer,
  toggleAracDrawer,
  setAracTab,
} from '../services/arac.js'
import {
  faturaState,
  closeFaturaDrawer,
  setFaturaTab,
} from '../services/fatura.js'

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

const isTaleplerPage = computed(() => route.path === '/talepler')
const showTaleplerDrawer = computed(() => isTaleplerPage.value && requestsState.drawerOpen)

const isAracPage = computed(() => route.path === '/arac-takip')
const showAracDrawer = computed(() => isAracPage.value && aracState.drawerOpen)

const isFaturaPage = computed(() => route.path === '/fatura' || route.path === '/efatura')
const showFaturaDrawer = computed(() => isFaturaPage.value && faturaState.drawerOpen)

const aracScrollEl = ref(null)

function handleAracTabClick(tab) {
  setAracTab(tab)
  nextTick(() => {
    scrollActiveAracTab()
  })
}

function scrollActiveAracTab() {
  const activeEl = aracScrollEl.value?.querySelector('.nav-link.is-active')
  if (activeEl) {
    activeEl.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' })
  }
}

watch(() => aracState.activeTab, () => {
  nextTick(() => {
    scrollActiveAracTab()
  })
} )

watch(() => route.path, (newPath) => {
  if (newPath !== '/talepler') {
    requestsState.drawerOpen = false
  }
  if (newPath !== '/arac-takip') {
    aracState.drawerOpen = false
  }
  if (newPath !== '/fatura' && newPath !== '/efatura') {
    faturaState.drawerOpen = false
  }
})

function handleNavClick(item, event) {
  if (item.to === '/talepler') {
    event?.preventDefault?.()
    if (route.path !== '/talepler') {
      router.push('/talepler')
      requestsState.drawerOpen = true
    } else {
      toggleRequestsDrawer()
    }
  }
}

function handleCenterNavClick() {
  menuOpen.value = !menuOpen.value
}

const items = [
  { to: '/', label: 'Ana Sayfa', icon: Home },
  { to: '/personel', label: 'Personel', icon: UsersRound, permission: 'personnel' },
  { label: 'Diğer', icon: LayoutGrid, center: true },
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
  { permission: 'vehicles', label: 'Araç Takip', icon: Car, to: '/arac-takip' },
  { permission: 'vehicles', label: 'KM Onayları', icon: Gauge, href: '/mobile/index.php?p=km-onaylari' },
  { permission: 'documents', label: 'Evrak Takip', icon: FileText, to: '/evrak-takip' },
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
    if (op.to === '/arac-takip') {
      aracState.drawerOpen = true
    } else if (op.to === '/fatura') {
      faturaState.drawerOpen = true
    }
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

  <nav class="bottom-nav" :class="{ 'has-drawer': showTaleplerDrawer || showAracDrawer || showFaturaDrawer }" aria-label="Ana gezinme">
    <div class="bottom-nav__inner">
      <!-- Normal Navigasyon Sekmeleri -->
      <Transition name="nav-slide-tabs">
        <div
          v-if="!showTaleplerDrawer && !showAracDrawer && !showFaturaDrawer"
          class="bottom-nav__tabs"
          :style="{ '--nav-count': visibleItems.length }"
        >
          <template v-for="item in visibleItems" :key="item.label">
            <button
              v-if="item.center"
              class="nav-link"
              :class="{ 'is-active': isItemActive(item) }"
              type="button"
              aria-controls="more-menu"
              aria-haspopup="dialog"
              :aria-label="`${item.label} menüsünü aç`"
              :aria-expanded="menuOpen"
              @click="handleCenterNavClick"
            >
              <span class="nav-icon">
                <component :is="item.icon" :size="21" :stroke-width="isItemActive(item) ? 2.4 : 1.9" />
              </span>
              <span class="nav-label">{{ item.label }}</span>
            </button>
            <button
              v-else-if="item.to === '/talepler'"
              type="button"
              class="nav-link"
              :class="{ 'is-active': isItemActive(item) }"
              :aria-label="item.label"
              @click="handleNavClick(item, $event)"
            >
              <span class="nav-icon">
                <component :is="item.icon" :size="21" :stroke-width="isItemActive(item) ? 2.4 : 1.9" />
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
      </Transition>

      <!-- Talep Türleri Çekmecesi (Tam Nav Menü Formatında ve İkonlu) -->
      <Transition name="nav-slide-drawer">
        <div v-if="showTaleplerDrawer" class="bottom-nav__tabs talepler-nav-tabs" style="--nav-count: 5;">
          <!-- 1. Tümü -->
          <button
            type="button"
            class="nav-link"
            :class="{ 'is-active': requestsState.activeTab === 'all' }"
            aria-label="Tüm Talepler"
            @click="setRequestsTab('all')"
          >
            <span class="nav-icon">
              <Sparkles :size="21" :stroke-width="requestsState.activeTab === 'all' ? 2.4 : 1.9" />
              <span class="nav-bubble-badge" :class="{ 'has-count': requestsState.counts.total > 0 }">
                {{ requestsState.counts.total }}
              </span>
            </span>
            <span class="nav-label">Tümü</span>
          </button>

          <!-- 2. Avans -->
          <button
            v-if="requestsState.permissions.avans !== false"
            type="button"
            class="nav-link"
            :class="{ 'is-active': requestsState.activeTab === 'avans' }"
            aria-label="Avans Talepleri"
            @click="setRequestsTab('avans')"
          >
            <span class="nav-icon">
              <Wallet :size="21" :stroke-width="requestsState.activeTab === 'avans' ? 2.4 : 1.9" />
              <span class="nav-bubble-badge" :class="{ 'has-count': requestsState.counts.avans > 0 }">
                {{ requestsState.counts.avans }}
              </span>
            </span>
            <span class="nav-label">Avans</span>
          </button>

          <!-- 3. İzin -->
          <button
            v-if="requestsState.permissions.izin !== false"
            type="button"
            class="nav-link"
            :class="{ 'is-active': requestsState.activeTab === 'izin' }"
            aria-label="İzin Talepleri"
            @click="setRequestsTab('izin')"
          >
            <span class="nav-icon">
              <CalendarDays :size="21" :stroke-width="requestsState.activeTab === 'izin' ? 2.4 : 1.9" />
              <span class="nav-bubble-badge" :class="{ 'has-count': requestsState.counts.izin > 0 }">
                {{ requestsState.counts.izin }}
              </span>
            </span>
            <span class="nav-label">İzin</span>
          </button>

          <!-- 4. Genel -->
          <button
            v-if="requestsState.permissions.talep !== false"
            type="button"
            class="nav-link"
            :class="{ 'is-active': requestsState.activeTab === 'talep' }"
            aria-label="Genel Talepler"
            @click="setRequestsTab('talep')"
          >
            <span class="nav-icon">
              <Layers :size="21" :stroke-width="requestsState.activeTab === 'talep' ? 2.4 : 1.9" />
              <span class="nav-bubble-badge" :class="{ 'has-count': requestsState.counts.talep > 0 }">
                {{ requestsState.counts.talep }}
              </span>
            </span>
            <span class="nav-label">Genel</span>
          </button>

          <!-- 5. Kapat -->
          <button
            type="button"
            class="nav-link nav-link--close"
            aria-label="Talep türleri menüsünü kapat"
            title="Kapat"
            @click="closeRequestsDrawer"
          >
            <span class="nav-icon">
              <X :size="22" :stroke-width="2.3" />
            </span>
            <span class="nav-label">Kapat</span>
          </button>
        </div>
      </Transition>

      <!-- Fatura / Evrak Takip Sekmeleri Çekmecesi (Tam Nav Menü Formatında ve İkonlu) -->
      <Transition name="nav-slide-drawer">
        <div v-if="showFaturaDrawer" class="bottom-nav__tabs fatura-nav-tabs" style="--nav-count: 5;">
          <!-- 1. Özet & KDV -->
          <button
            type="button"
            class="nav-link"
            :class="{ 'is-active': faturaState.activeTab === 'ozet' }"
            aria-label="Özet ve KDV"
            @click="setFaturaTab('ozet')"
          >
            <span class="nav-icon">
              <ReceiptText :size="21" :stroke-width="faturaState.activeTab === 'ozet' ? 2.4 : 1.9" />
            </span>
            <span class="nav-label">Özet</span>
          </button>

          <!-- 2. Giden -->
          <button
            type="button"
            class="nav-link"
            :class="{ 'is-active': faturaState.activeTab === 'giden' }"
            aria-label="Giden Faturalar"
            @click="setFaturaTab('giden')"
          >
            <span class="nav-icon">
              <Send :size="20" :stroke-width="faturaState.activeTab === 'giden' ? 2.4 : 1.9" />
              <span class="nav-bubble-badge" :class="{ 'has-count': faturaState.counts.giden > 0 }">
                {{ faturaState.counts.giden }}
              </span>
            </span>
            <span class="nav-label">Giden</span>
          </button>

          <!-- 3. Gelen -->
          <button
            type="button"
            class="nav-link"
            :class="{ 'is-active': faturaState.activeTab === 'gelen' }"
            aria-label="Gelen Faturalar"
            @click="setFaturaTab('gelen')"
          >
            <span class="nav-icon">
              <Inbox :size="20" :stroke-width="faturaState.activeTab === 'gelen' ? 2.4 : 1.9" />
              <span class="nav-bubble-badge" :class="{ 'has-count': faturaState.counts.gelen > 0 }">
                {{ faturaState.counts.gelen }}
              </span>
            </span>
            <span class="nav-label">Gelen</span>
          </button>

          <!-- 4. Taslaklar -->
          <button
            type="button"
            class="nav-link"
            :class="{ 'is-active': faturaState.activeTab === 'taslak' }"
            aria-label="Taslak Faturalar"
            @click="setFaturaTab('taslak')"
          >
            <span class="nav-icon">
              <Edit3 :size="21" :stroke-width="faturaState.activeTab === 'taslak' ? 2.4 : 1.9" />
              <span class="nav-bubble-badge" :class="{ 'has-count': faturaState.counts.taslak > 0 }">
                {{ faturaState.counts.taslak }}
              </span>
            </span>
            <span class="nav-label">Taslak</span>
          </button>

          <!-- 5. Kapat -->
          <button
            type="button"
            class="nav-link nav-link--close"
            aria-label="Fatura menüsünü kapat"
            title="Kapat"
            @click="closeFaturaDrawer"
          >
            <span class="nav-icon">
              <X :size="22" :stroke-width="2.3" />
            </span>
            <span class="nav-label">Kapat</span>
          </button>
        </div>
      </Transition>

      <!-- Araç Takip Sekmeleri Çekmecesi (Scrollable & Sabit Kapat Butonlu) -->
      <Transition name="nav-slide-drawer">
        <div v-if="showAracDrawer" class="bottom-nav__tabs arac-nav-tabs">
          <!-- Scrollable Sekmeler -->
          <div ref="aracScrollEl" class="arac-nav-tabs__scroll">
            <!-- 1. Araçlar -->
            <button
              type="button"
              class="nav-link"
              :class="{ 'is-active': aracState.activeTab === 'araclar' }"
              aria-label="Araçlar"
              @click="handleAracTabClick('araclar')"
            >
              <span class="nav-icon">
                <Car :size="21" :stroke-width="aracState.activeTab === 'araclar' ? 2.4 : 1.9" />
              </span>
              <span class="nav-label">Araçlar</span>
            </button>

            <!-- 2. Zimmet -->
            <button
              type="button"
              class="nav-link"
              :class="{ 'is-active': aracState.activeTab === 'zimmet' }"
              aria-label="Zimmet"
              @click="handleAracTabClick('zimmet')"
            >
              <span class="nav-icon">
                <UserCheck :size="21" :stroke-width="aracState.activeTab === 'zimmet' ? 2.4 : 1.9" />
              </span>
              <span class="nav-label">Zimmet</span>
            </button>

            <!-- 3. Yakıt -->
            <button
              type="button"
              class="nav-link"
              :class="{ 'is-active': aracState.activeTab === 'yakit' }"
              aria-label="Yakıt"
              @click="handleAracTabClick('yakit')"
            >
              <span class="nav-icon">
                <Fuel :size="21" :stroke-width="aracState.activeTab === 'yakit' ? 2.4 : 1.9" />
              </span>
              <span class="nav-label">Yakıt</span>
            </button>

            <!-- 4. KM -->
            <button
              type="button"
              class="nav-link"
              :class="{ 'is-active': aracState.activeTab === 'km' }"
              aria-label="KM"
              @click="handleAracTabClick('km')"
            >
              <span class="nav-icon">
                <Gauge :size="21" :stroke-width="aracState.activeTab === 'km' ? 2.4 : 1.9" />
              </span>
              <span class="nav-label">KM</span>
            </button>

            <!-- 5. Servis -->
            <button
              type="button"
              class="nav-link"
              :class="{ 'is-active': aracState.activeTab === 'servis' }"
              aria-label="Servis"
              @click="handleAracTabClick('servis')"
            >
              <span class="nav-icon">
                <Wrench :size="21" :stroke-width="aracState.activeTab === 'servis' ? 2.4 : 1.9" />
              </span>
              <span class="nav-label">Servis</span>
            </button>
          </div>

          <!-- Sabit Kapat Butonu -->
          <button
            type="button"
            class="nav-link nav-link--close arac-nav-tabs__close"
            aria-label="Araç menüsünü kapat"
            title="Kapat"
            @click="closeAracDrawer"
          >
            <span class="nav-icon">
              <X :size="22" :stroke-width="2.3" />
            </span>
            <span class="nav-label">Kapat</span>
          </button>
        </div>
      </Transition>
    </div>
    <div class="bottom-nav__home-bar" aria-hidden="true"></div>
  </nav>
</template>
