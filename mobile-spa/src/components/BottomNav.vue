<script setup>
import { computed, nextTick, onBeforeUnmount, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  AlertCircle,
  Banknote,
  Briefcase,
  CalendarCheck,
  CalendarDays,
  CalendarRange,
  Car,
  ChartNoAxesCombined,
  Check,
  CheckCircle2,
  ChevronRight,
  ClipboardCheck,
  ClipboardList,
  Clock,
  CreditCard,
  Edit3,
  FileText,
  Fuel,
  Gauge,
  GripVertical,
  Headset,
  Home,
  Inbox,
  Landmark,
  Layers,
  LayoutDashboard,
  LayoutGrid,
  LogOut,
  Megaphone,
  MessageCircle,
  Pin,
  ReceiptText,
  RotateCcw,
  Send,
  ShieldAlert,
  ShieldCheck,
  SlidersHorizontal,
  Sparkles,
  UserCheck,
  UserRound,
  UsersRound,
  Wallet,
  Wrench,
  X,
  XCircle,
  ZapOff,
} from '@lucide/vue'
import { auth } from '../services/auth.js'
import { BADGE_MODULES, badgesState, moduleBadge, fetchBadges, markSeen } from '../services/badges.js'
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
import {
  kmState,
  closeKmDrawer,
  toggleKmDrawer,
  setKmTab,
} from '../services/km-onaylari.js'
import {
  reportsState,
  closeReportsDrawer,
  toggleReportsDrawer,
  setReportsTab,
} from '../services/reports.js'

const route = useRoute()
const router = useRouter()
const menuOpen = ref(false)
const sheet = ref(null)
let previousOverflow = ''
let returnFocus = null
watch(menuOpen, async (open) => {
  if (open) {
    fetchBadges()
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

const isKmPage = computed(() => route.path === '/km-onaylari' || route.path === '/km-onay')
const showKmDrawer = computed(() => isKmPage.value && kmState.drawerOpen)

const isRaporlarPage = computed(() => route.path === '/raporlar')
const showRaporlarDrawer = computed(() => isRaporlarPage.value && reportsState.drawerOpen)

const aracScrollEl = ref(null)
const reportsScrollEl = ref(null)

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

function handleReportsTabClick(tab) {
  setReportsTab(tab)
  nextTick(() => {
    scrollActiveReportsTab()
  })
}

function scrollActiveReportsTab() {
  const activeEl = reportsScrollEl.value?.querySelector('.nav-link.is-active')
  if (activeEl) {
    activeEl.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' })
  }
}

watch(() => reportsState.activeTab, () => {
  nextTick(() => {
    scrollActiveReportsTab()
  })
})

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
  if (newPath !== '/km-onaylari' && newPath !== '/km-onay') {
    kmState.drawerOpen = false
  }
  if (newPath !== '/raporlar') {
    reportsState.drawerOpen = false
  } else {
    reportsState.drawerOpen = true
  }
})

function handleNavClick(item, event) {
  if (item.href) {
    event?.preventDefault?.()
    window.location.href = item.href
    return
  }
  if (item.to === '/talepler') {
    event?.preventDefault?.()
    if (route.path !== '/talepler') {
      router.push('/talepler')
      requestsState.drawerOpen = true
    } else {
      toggleRequestsDrawer()
    }
  } else if (item.to === '/raporlar') {
    event?.preventDefault?.()
    if (route.path !== '/raporlar') {
      router.push('/raporlar')
      reportsState.drawerOpen = true
    } else {
      toggleReportsDrawer()
    }
  } else if (item.to === '/arac-takip') {
    event?.preventDefault?.()
    if (route.path !== '/arac-takip') {
      router.push('/arac-takip')
      aracState.drawerOpen = true
    } else {
      toggleAracDrawer()
    }
  } else if (item.to === '/fatura') {
    event?.preventDefault?.()
    if (route.path !== '/fatura' && route.path !== '/efatura') {
      router.push('/fatura')
      faturaState.drawerOpen = true
    } else {
      faturaState.drawerOpen = !faturaState.drawerOpen
    }
  } else if (item.to === '/km-onaylari') {
    event?.preventDefault?.()
    if (route.path !== '/km-onaylari' && route.path !== '/km-onay') {
      router.push('/km-onaylari')
      kmState.drawerOpen = true
    } else {
      toggleKmDrawer()
    }
  }
}

function handleCenterNavClick() {
  menuOpen.value = !menuOpen.value
}

const DEFAULT_GROUPS = [
  { id: 'finans', category: 'FİNANS & MUHASEBE' },
  { id: 'operasyon', category: 'OPERASYON & SAHA' },
  { id: 'planlama', category: 'PLANLAMA & İK' },
  { id: 'sistem', category: 'SİSTEM & DESTEK' },
]

const MODULE_CATALOG = {
  invoices: {
    key: 'invoices',
    group: 'finans',
    permission: 'invoices',
    label: 'E-Fatura & Finans',
    navLabel: 'E-Fatura',
    eyebrow: 'FATURA & KDV',
    subtitle: 'Gelen/giden faturalar ve finansal özet',
    icon: ReceiptText,
    theme: 'emerald',
    to: '/fatura',
    hasDrawer: true,
  },
  cash: {
    key: 'cash',
    group: 'finans',
    permission: 'cash',
    label: 'Kasa Yönetimi',
    navLabel: 'Kasa',
    eyebrow: 'NAKİT AKIŞI',
    subtitle: 'Kasa ve banka hesap hareketleri',
    icon: Landmark,
    theme: 'purple',
    to: '/kasa',
  },
  current_accounts: {
    key: 'current_accounts',
    group: 'finans',
    permission: 'current_accounts',
    label: 'Cari Takip',
    navLabel: 'Cari',
    eyebrow: 'HESAP TAKİBİ',
    subtitle: 'Müşteri ve tedarikçi bakiye dökümleri',
    icon: CreditCard,
    theme: 'amber',
    to: '/cari-takip',
  },
  payroll: {
    key: 'payroll',
    group: 'finans',
    permission: 'payroll',
    label: 'Bordro',
    navLabel: 'Bordro',
    eyebrow: 'HAKEDİŞ & MAAŞ',
    subtitle: 'Aylık maaş, hakediş ve ödeme dökümü',
    icon: Banknote,
    theme: 'emerald',
    to: '/bordro',
  },
  contracts: {
    key: 'contracts',
    group: 'finans',
    permission: 'contracts',
    label: 'Sözleşme & Hakediş',
    navLabel: 'Hakediş',
    eyebrow: 'HAKEDİŞ & PROJE',
    subtitle: 'Sözleşmeler, keşif ve dönem hakedişleri',
    icon: Briefcase,
    theme: 'blue',
    to: '/sozlesme-hakedis',
  },
  vehicles: {
    key: 'vehicles',
    group: 'operasyon',
    permission: 'vehicles',
    label: 'Araç Takip',
    navLabel: 'Araçlar',
    eyebrow: 'FİLO YÖNETİMİ',
    subtitle: 'Şirket araçları, zimmet ve yakıt takibi',
    icon: Car,
    theme: 'blue',
    to: '/arac-takip',
    hasDrawer: true,
  },
  km_approvals: {
    key: 'km_approvals',
    group: 'operasyon',
    permission: 'km_approvals',
    label: 'KM Onayları',
    navLabel: 'KM Onay',
    eyebrow: 'SAHA DENETİMİ',
    subtitle: 'Personel sayaç bildirimleri ve onay kuyruğu',
    icon: Gauge,
    theme: 'emerald',
    to: '/km-onaylari',
    hasDrawer: true,
  },
  documents: {
    key: 'documents',
    group: 'operasyon',
    permission: 'documents',
    label: 'Evrak Takip',
    navLabel: 'Evraklar',
    eyebrow: 'RESMÎ YAZIŞMALAR',
    subtitle: 'Gelen/giden evrak ve arşiv kaydı',
    icon: FileText,
    theme: 'purple',
    to: '/evrak-takip',
  },
  notices: {
    key: 'notices',
    group: 'operasyon',
    permission: 'notices',
    label: 'İhbar Yönetimi',
    navLabel: 'İhbar',
    eyebrow: 'MÜŞTERİ BİLDİRİMİ',
    subtitle: 'Saha ihbarları ve acil müdahaleler',
    icon: Megaphone,
    theme: 'amber',
    to: '/ihbar',
  },
  illegal: {
    key: 'illegal',
    group: 'operasyon',
    permission: 'illegal',
    label: 'Kaçak Kontrol',
    navLabel: 'Kaçak',
    eyebrow: 'DENETİM & TUTANAK',
    subtitle: 'Kaçak elektrik tespit ve tutanakları',
    icon: ShieldAlert,
    theme: 'coral',
    to: '/kacak-kontrol',
  },
  reports: {
    key: 'reports',
    group: 'operasyon',
    permission: 'reports',
    label: 'Saha Raporları',
    navLabel: 'Raporlar',
    eyebrow: 'PERFORMANS & ANALİZ',
    subtitle: 'Endeks okuma, kesme/açma ve sayaç raporları',
    icon: ChartNoAxesCombined,
    theme: 'emerald',
    to: '/raporlar',
    hasDrawer: true,
  },
  personnel: {
    key: 'personnel',
    group: 'planlama',
    permission: 'personnel',
    label: 'Personel Yönetimi',
    navLabel: 'Personel',
    eyebrow: 'İNSAN KAYNAKLARI',
    subtitle: 'Personel özlük, iletişim ve evrak kayıtları',
    icon: UsersRound,
    theme: 'blue',
    to: '/personel',
  },
  requests: {
    key: 'requests',
    group: 'planlama',
    permission: 'requests',
    label: 'Personel Talepleri',
    navLabel: 'Talepler',
    eyebrow: 'ONAY MERKEZİ',
    subtitle: 'Avans, izin ve genel talep onay kuyruğu',
    icon: ClipboardList,
    theme: 'purple',
    to: '/talepler',
    hasDrawer: true,
  },
  tasks: {
    key: 'tasks',
    group: 'planlama',
    permission: 'tasks',
    label: 'Görev Takibi',
    navLabel: 'Görevler',
    eyebrow: 'İŞ & GÖREV',
    subtitle: 'Şirket içi görevler ve iş akışı',
    icon: ClipboardCheck,
    theme: 'blue',
    to: '/gorevler',
  },
  shifts: {
    key: 'shifts',
    group: 'planlama',
    permission: 'shifts',
    label: 'Nöbet Onay',
    navLabel: 'Nöbet',
    eyebrow: 'VARDİYA & NÖBET',
    subtitle: 'Haftalık nöbet çizelgeleri ve onaylar',
    icon: CalendarCheck,
    theme: 'amber',
    to: '/nobet-onay',
  },
  puantaj: {
    key: 'puantaj',
    group: 'planlama',
    permission: 'puantaj',
    label: 'Puantaj',
    navLabel: 'Puantaj',
    eyebrow: 'MESAİ & ÇALIŞMA',
    subtitle: 'Personel devam ve fiili çalışma saatleri',
    icon: CalendarRange,
    theme: 'blue',
    to: '/puantaj',
  },
  logs: {
    key: 'logs',
    group: 'sistem',
    permission: 'logs',
    label: 'Sistem Logları',
    navLabel: 'Loglar',
    eyebrow: 'GÜVENLİK & DENETİM',
    subtitle: 'Kullanıcı hareketleri ve sistem logları',
    icon: ShieldCheck,
    theme: 'blue',
    to: '/loglar',
  },
  live_support: {
    key: 'live_support',
    group: 'sistem',
    permission: 'live_support',
    label: 'Canlı Destek',
    navLabel: 'Destek',
    eyebrow: 'ANLIK İLETİŞİM',
    subtitle: 'Personel mesajları ve destek kuyruğu',
    icon: MessageCircle,
    theme: 'emerald',
    to: '/canli-destek',
  },
  support: {
    key: 'support',
    group: 'sistem',
    permission: 'support',
    label: 'Destek & Yardım',
    navLabel: 'Yardım',
    eyebrow: 'YARDIM MERKEZİ',
    subtitle: 'Kullanım kılavuzu ve teknik destek',
    icon: Headset,
    theme: 'slate',
    href: '/mobile/index.php?p=yardim',
  },
}

const DEFAULT_NAVBAR_KEYS = ['personnel', 'reports', 'requests']
const DEFAULT_GROUP_KEYS = ['finans', 'operasyon', 'planlama', 'sistem']
const DEFAULT_GROUP_ITEMS = {
  finans: ['invoices', 'cash', 'current_accounts', 'payroll', 'contracts'],
  operasyon: ['vehicles', 'km_approvals', 'documents', 'notices', 'illegal', 'reports'],
  planlama: ['personnel', 'requests', 'tasks', 'shifts', 'puantaj'],
  sistem: ['logs', 'live_support', 'support'],
}

function isModulePermitted(key) {
  const mod = MODULE_CATALOG[key]
  if (!mod) return false
  return !mod.permission || Boolean(auth.state.permissions[mod.permission])
}

function buildNormalizedOrder(source) {
  const rawNavbar = Array.isArray(source?.navbar) ? source.navbar : DEFAULT_NAVBAR_KEYS
  const navbar = []
  for (const key of rawNavbar) {
    if (MODULE_CATALOG[key] && !navbar.includes(key)) {
      navbar.push(key)
    }
    if (navbar.length >= 3) break
  }

  const rawGroups = Array.isArray(source?.groups) ? source.groups : DEFAULT_GROUP_KEYS
  const groups = []
  for (const gId of rawGroups) {
    if (DEFAULT_GROUP_KEYS.includes(gId) && !groups.includes(gId)) {
      groups.push(gId)
    }
  }
  for (const gId of DEFAULT_GROUP_KEYS) {
    if (!groups.includes(gId)) groups.push(gId)
  }

  const items = {}
  for (const gId of DEFAULT_GROUP_KEYS) {
    const defaultList = DEFAULT_GROUP_ITEMS[gId] || []
    const customList = Array.isArray(source?.items?.[gId]) ? source.items[gId] : []
    const merged = []
    for (const k of customList) {
      if (defaultList.includes(k) && !merged.includes(k)) {
        merged.push(k)
      }
    }
    for (const k of defaultList) {
      if (!merged.includes(k)) {
        merged.push(k)
      }
    }
    items[gId] = merged
  }

  return { navbar, groups, items }
}

const editMode = ref(false)
const savingOrder = ref(false)
const orderFeedback = ref('')
const pendingSwapModuleKey = ref(null)
const sheetContentEl = ref(null)

const draftOrder = reactive(buildNormalizedOrder(null))

const effectiveOrder = computed(() => {
  if (editMode.value) {
    return {
      navbar: [...draftOrder.navbar],
      groups: [...draftOrder.groups],
      items: {
        finans: [...(draftOrder.items.finans || [])],
        operasyon: [...(draftOrder.items.operasyon || [])],
        planlama: [...(draftOrder.items.planlama || [])],
        sistem: [...(draftOrder.items.sistem || [])],
      },
    }
  }
  return buildNormalizedOrder(auth.state.menuOrder)
})

function syncDraftFromSaved() {
  const norm = buildNormalizedOrder(auth.state.menuOrder)
  draftOrder.navbar = [...norm.navbar]
  draftOrder.groups = [...norm.groups]
  draftOrder.items = {
    finans: [...norm.items.finans],
    operasyon: [...norm.items.operasyon],
    planlama: [...norm.items.planlama],
    sistem: [...norm.items.sistem],
  }
  pendingSwapModuleKey.value = null
}

function toggleEditMode() {
  if (!editMode.value) {
    syncDraftFromSaved()
    orderFeedback.value = ''
    editMode.value = true
  } else {
    editMode.value = false
    pendingSwapModuleKey.value = null
  }
}

watch(menuOpen, (open) => {
  if (!open) {
    editMode.value = false
    pendingSwapModuleKey.value = null
    orderFeedback.value = ''
  }
})

const permittedNavbarItems = computed(() => {
  return effectiveOrder.value.navbar
    .filter(key => isModulePermitted(key))
    .map(key => MODULE_CATALOG[key])
})

const visibleItems = computed(() => {
  const list = [
    { key: 'home', to: '/', label: 'Ana Sayfa', navLabel: 'Ana Sayfa', icon: Home, fixed: true },
    ...permittedNavbarItems.value.map(mod => ({
      ...mod,
      label: mod.navLabel || mod.label,
    })),
    { key: 'more', label: 'Diğer', navLabel: 'Diğer', icon: LayoutGrid, center: true, fixed: true },
  ]
  return list
})

function itemBadge(item) {
  if (item.center) {
    const shown = new Set(permittedNavbarItems.value.map(mod => mod.key))
    return Object.keys(BADGE_MODULES)
      .filter(key => !shown.has(key) && isModulePermitted(key))
      .reduce((sum, key) => sum + moduleBadge(key), 0)
  }
  return item.key ? moduleBadge(item.key) : 0
}

const SEEN_ROUTES = {
  '/talepler': 'requests',
  '/km-onaylari': 'km_approvals',
  '/km-onay': 'km_approvals',
  '/kacak-kontrol': 'kacak',
  '/kacak': 'kacak',
  '/ihbar': 'ihbar',
  '/evrak-takip': 'evrak',
  '/giden-evrak': 'evrak',
}

function currentSeenKey() {
  if (route.path === '/fatura' || route.path === '/efatura') {
    if (faturaState.activeTab === 'gelen') return 'efatura_gelen'
    if (faturaState.activeTab === 'giden') return 'efatura_giden'
    return null
  }
  return SEEN_ROUTES[route.path] || null
}

watch(
  () => [route.path, faturaState.activeTab],
  () => {
    fetchBadges().then(() => {
      const key = currentSeenKey()
      if (key) markSeen(key)
    })
  },
  { immediate: true },
)

watch(
  () => ({ ...badgesState.counts }),
  () => {
    const key = currentSeenKey()
    if (key) markSeen(key)
  },
)

function isItemActive(item) {
  if (item.center) return menuOpen.value
  if (menuOpen.value) return false
  if (!item.to) return false
  if (item.to === '/') return route.path === '/'
  return route.path.startsWith(item.to)
}

const visibleOperationGroups = computed(() => {
  const order = effectiveOrder.value
  const activeNavKeys = new Set(order.navbar.filter(k => isModulePermitted(k)))
  const groupMetaMap = Object.fromEntries(DEFAULT_GROUPS.map(g => [g.id, g]))

  return order.groups
    .map(groupId => {
      const meta = groupMetaMap[groupId]
      if (!meta) return null
      const itemKeys = order.items[groupId] || DEFAULT_GROUP_ITEMS[groupId] || []
      const groupItems = itemKeys
        .filter(key => {
          if (!isModulePermitted(key)) return false
          // Normal görünümde alt barda olan kısayollar listede tekrarlanmaz;
          // düzenleme modunda ise grup içinde sıralanabilmesi ve durumu görülebilmesi için gösterilir.
          if (!editMode.value && activeNavKeys.has(key)) return false
          return true
        })
        .map(key => ({
          ...MODULE_CATALOG[key],
          isPinned: activeNavKeys.has(key),
        }))

      return {
        id: groupId,
        category: meta.category,
        items: groupItems,
      }
    })
    .filter(group => group && group.items.length > 0)
})

function isModulePinnedInDraft(key) {
  return draftOrder.navbar.includes(key)
}

function handleTogglePin(modKey) {
  const idx = draftOrder.navbar.indexOf(modKey)
  if (idx !== -1) {
    draftOrder.navbar.splice(idx, 1)
    if (pendingSwapModuleKey.value === modKey) {
      pendingSwapModuleKey.value = null
    }
    return
  }

  // Yalnızca kullanıcının yetkili olduğu aktif alt bar öğelerini say
  const currentPermittedNav = draftOrder.navbar.filter(k => isModulePermitted(k))
  if (currentPermittedNav.length < 3) {
    draftOrder.navbar = [...currentPermittedNav, modKey]
    pendingSwapModuleKey.value = null
  } else {
    // 3 slot doluysa kullanıcının hangi slotun yerine koyacağını seçmesi için bar aç
    pendingSwapModuleKey.value = pendingSwapModuleKey.value === modKey ? null : modKey
  }
}

function confirmSwapNavbarSlot(replaceKey) {
  if (!pendingSwapModuleKey.value) return
  const targetIdx = draftOrder.navbar.indexOf(replaceKey)
  if (targetIdx !== -1) {
    draftOrder.navbar.splice(targetIdx, 1, pendingSwapModuleKey.value)
  }
  pendingSwapModuleKey.value = null
}

function removeNavbarSlot(modKey) {
  const idx = draftOrder.navbar.indexOf(modKey)
  if (idx !== -1) {
    draftOrder.navbar.splice(idx, 1)
  }
  if (pendingSwapModuleKey.value === modKey) {
    pendingSwapModuleKey.value = null
  }
}

async function handleSaveMenuOrder() {
  if (savingOrder.value) return
  savingOrder.value = true
  orderFeedback.value = ''
  try {
    const payload = {
      navbar: [...draftOrder.navbar],
      groups: [...draftOrder.groups],
      items: {
        finans: [...draftOrder.items.finans],
        operasyon: [...draftOrder.items.operasyon],
        planlama: [...draftOrder.items.planlama],
        sistem: [...draftOrder.items.sistem],
      },
    }
    await auth.saveMenuOrder(payload)
    editMode.value = false
    pendingSwapModuleKey.value = null
  } catch (err) {
    orderFeedback.value = err?.message || 'Menü düzeni kaydedilemedi.'
  } finally {
    savingOrder.value = false
  }
}

async function handleResetMenuOrder() {
  if (savingOrder.value) return
  savingOrder.value = true
  orderFeedback.value = ''
  try {
    await auth.resetMenuOrder()
    syncDraftFromSaved()
    editMode.value = false
  } catch (err) {
    orderFeedback.value = err?.message || 'Sıfırlama sırasında hata oluştu.'
  } finally {
    savingOrder.value = false
  }
}

// ============================================================================
// Dokunmatik ve Fare Uyumlu Sürükle-Bırak (Pointer Events Drag & Drop)
// ============================================================================
const dragState = reactive({
  active: false,
  type: '', // 'navbar' | 'group' | 'item'
  groupId: '',
  activeKey: '',
})

let autoScrollRaf = null
let lastPointerY = 0
let lastPointerX = 0

function stopAutoScroll() {
  if (autoScrollRaf) {
    cancelAnimationFrame(autoScrollRaf)
    autoScrollRaf = null
  }
}

function runAutoScroll() {
  if (!dragState.active || !sheetContentEl.value) {
    stopAutoScroll()
    return
  }
  const container = sheetContentEl.value
  const rect = container.getBoundingClientRect()
  const edgeThreshold = 52
  const maxSpeed = 9

  if (lastPointerY < rect.top + edgeThreshold) {
    const ratio = Math.min(1, (rect.top + edgeThreshold - lastPointerY) / edgeThreshold)
    container.scrollTop -= Math.ceil(maxSpeed * ratio)
  } else if (lastPointerY > rect.bottom - edgeThreshold) {
    const ratio = Math.min(1, (lastPointerY - (rect.bottom - edgeThreshold)) / edgeThreshold)
    container.scrollTop += Math.ceil(maxSpeed * ratio)
  }

  autoScrollRaf = requestAnimationFrame(runAutoScroll)
}

function startNavbarDrag(modKey, event) {
  if (!editMode.value) return
  event.preventDefault()
  event.stopPropagation()
  dragState.active = true
  dragState.type = 'navbar'
  dragState.activeKey = modKey
  lastPointerX = event.clientX
  lastPointerY = event.clientY
  window.addEventListener('pointermove', onGlobalPointerMove, { passive: false })
  window.addEventListener('pointerup', onGlobalPointerUp)
  window.addEventListener('pointercancel', onGlobalPointerUp)
}

function startGroupDrag(groupId, event) {
  if (!editMode.value) return
  event.preventDefault()
  event.stopPropagation()
  dragState.active = true
  dragState.type = 'group'
  dragState.activeKey = groupId
  lastPointerX = event.clientX
  lastPointerY = event.clientY
  runAutoScroll()
  window.addEventListener('pointermove', onGlobalPointerMove, { passive: false })
  window.addEventListener('pointerup', onGlobalPointerUp)
  window.addEventListener('pointercancel', onGlobalPointerUp)
}

function startItemDrag(groupId, modKey, event) {
  if (!editMode.value) return
  event.preventDefault()
  event.stopPropagation()
  dragState.active = true
  dragState.type = 'item'
  dragState.groupId = groupId
  dragState.activeKey = modKey
  lastPointerX = event.clientX
  lastPointerY = event.clientY
  runAutoScroll()
  window.addEventListener('pointermove', onGlobalPointerMove, { passive: false })
  window.addEventListener('pointerup', onGlobalPointerUp)
  window.addEventListener('pointercancel', onGlobalPointerUp)
}

function onGlobalPointerMove(event) {
  if (!dragState.active) return
  event.preventDefault()
  lastPointerX = event.clientX
  lastPointerY = event.clientY

  const targetEl = document.elementFromPoint(event.clientX, event.clientY)
  if (!targetEl) return

  if (dragState.type === 'navbar') {
    const slotEl = targetEl.closest('[data-nav-slot-key]')
    if (!slotEl) return
    const overKey = slotEl.getAttribute('data-nav-slot-key')
    if (!overKey || overKey === dragState.activeKey) return
    const fromIdx = draftOrder.navbar.indexOf(dragState.activeKey)
    const toIdx = draftOrder.navbar.indexOf(overKey)
    if (fromIdx !== -1 && toIdx !== -1 && fromIdx !== toIdx) {
      const updated = [...draftOrder.navbar]
      const [moved] = updated.splice(fromIdx, 1)
      updated.splice(toIdx, 0, moved)
      draftOrder.navbar = updated
    }
    return
  }

  if (dragState.type === 'group') {
    const groupEl = targetEl.closest('[data-group-id]')
    if (!groupEl) return
    const overGroupId = groupEl.getAttribute('data-group-id')
    if (!overGroupId || overGroupId === dragState.activeKey) return
    const fromIdx = draftOrder.groups.indexOf(dragState.activeKey)
    const toIdx = draftOrder.groups.indexOf(overGroupId)
    if (fromIdx !== -1 && toIdx !== -1 && fromIdx !== toIdx) {
      const updated = [...draftOrder.groups]
      const [moved] = updated.splice(fromIdx, 1)
      updated.splice(toIdx, 0, moved)
      draftOrder.groups = updated
    }
    return
  }

  if (dragState.type === 'item') {
    const itemEl = targetEl.closest('[data-item-key]')
    if (!itemEl) return
    const overGroupId = itemEl.getAttribute('data-item-group')
    const overKey = itemEl.getAttribute('data-item-key')
    if (overGroupId !== dragState.groupId || !overKey || overKey === dragState.activeKey) return
    const list = draftOrder.items[dragState.groupId]
    if (!Array.isArray(list)) return
    const fromIdx = list.indexOf(dragState.activeKey)
    const toIdx = list.indexOf(overKey)
    if (fromIdx !== -1 && toIdx !== -1 && fromIdx !== toIdx) {
      const updated = [...list]
      const [moved] = updated.splice(fromIdx, 1)
      updated.splice(toIdx, 0, moved)
      draftOrder.items[dragState.groupId] = updated
    }
  }
}

function onGlobalPointerUp() {
  stopAutoScroll()
  dragState.active = false
  dragState.type = ''
  dragState.groupId = ''
  dragState.activeKey = ''
  window.removeEventListener('pointermove', onGlobalPointerMove)
  window.removeEventListener('pointerup', onGlobalPointerUp)
  window.removeEventListener('pointercancel', onGlobalPointerUp)
}

onBeforeUnmount(() => {
  onGlobalPointerUp()
})

function selectOperation(op) {
  if (editMode.value) return
  menuOpen.value = false
  if (op.to) {
    if (op.to === '/arac-takip') {
      aracState.drawerOpen = true
    } else if (op.to === '/fatura') {
      faturaState.drawerOpen = true
    } else if (op.to === '/km-onaylari') {
      kmState.drawerOpen = true
    } else if (op.to === '/talepler') {
      requestsState.drawerOpen = true
    } else if (op.to === '/raporlar') {
      reportsState.drawerOpen = true
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
      <section id="more-menu" ref="sheet" class="nav-sheet" :class="{ 'is-editing': editMode }" role="dialog" aria-modal="true" aria-label="Daha Fazla">
        <header class="nav-sheet__header">
          <div class="nav-sheet__title-block">
            <span class="nav-sheet__eyebrow">{{ editMode ? 'KİŞİSELLEŞTİRME' : 'HIZLI ERİŞİM & MENÜ' }}</span>
            <h2 class="nav-sheet__title">{{ editMode ? 'Menü & Alt Bar Düzeni' : 'Daha Fazla' }}</h2>
            <p class="nav-sheet__subtitle">
              {{ editMode ? 'Grupları, menüleri ve alt bar kısayollarını sürükleyerek sıralayın' : 'Operasyonel modüller ve araçlar' }}
            </p>
          </div>
          <div class="nav-sheet__header-actions">
            <button
              type="button"
              class="clay-circle-btn nav-sheet__edit-btn"
              :class="{ 'is-active': editMode }"
              :aria-label="editMode ? 'Düzenlemeyi kapat' : 'Menü düzenini özelleştir'"
              :title="editMode ? 'Vazgeç' : 'Menüyü Düzenle'"
              @click="toggleEditMode"
            >
              <SlidersHorizontal :size="17" />
            </button>
            <button type="button" class="clay-circle-btn nav-sheet__close-btn" aria-label="Kapat" @click="menuOpen = false">
              <X :size="19" />
            </button>
          </div>
        </header>

        <div ref="sheetContentEl" class="nav-sheet__content">
          <!-- Hata / Bilgi Geri Bildirimi -->
          <div v-if="orderFeedback" class="nav-order-feedback">
            {{ orderFeedback }}
          </div>

          <!-- Düzenleme Modu: Alt Bar (Navbar) Kısayolları Yönetimi -->
          <div v-if="editMode" class="nav-customizer-dock">
            <div class="nav-customizer-dock__head">
              <div>
                <span class="nav-customizer-dock__title">ALT BAR KISAYOLLARI ({{ permittedNavbarItems.length }}/3)</span>
                <p class="nav-customizer-dock__hint">Sıralamak için sürükleyin, çıkarmak için × simgesine dokunun</p>
              </div>
            </div>

            <div class="nav-customizer-slots">
              <!-- Sol Sabit: Ana Sayfa -->
              <div class="nav-customizer-slot is-fixed" title="Sabit">
                <span class="nav-customizer-slot__icon">
                  <Home :size="16" />
                </span>
                <span class="nav-customizer-slot__label">Ana Sayfa</span>
                <span class="nav-customizer-slot__tag">Sabit</span>
              </div>

              <!-- Kullanıcı Kısayol Slotları (Sürüklenebilir) -->
              <div
                v-for="navMod in permittedNavbarItems"
                :key="navMod.key"
                :data-nav-slot-key="navMod.key"
                class="nav-customizer-slot is-custom"
                :class="{ 'is-dragging': dragState.active && dragState.type === 'navbar' && dragState.activeKey === navMod.key }"
                @pointerdown="startNavbarDrag(navMod.key, $event)"
              >
                <button
                  type="button"
                  class="nav-customizer-slot__remove"
                  :aria-label="`${navMod.navLabel} kısayolunu alt bardan çıkar`"
                  @pointerdown.stop
                  @click.stop="removeNavbarSlot(navMod.key)"
                >
                  <X :size="11" />
                </button>
                <span class="nav-customizer-slot__icon">
                  <component :is="navMod.icon" :size="17" />
                </span>
                <span class="nav-customizer-slot__label">{{ navMod.navLabel }}</span>
                <span class="nav-customizer-slot__grip">
                  <GripVertical :size="12" />
                </span>
              </div>

              <!-- Boş Slot Gösterimi -->
              <div
                v-for="emptyIdx in Math.max(0, 3 - permittedNavbarItems.length)"
                :key="`empty-${emptyIdx}`"
                class="nav-customizer-slot is-empty"
              >
                <span class="nav-customizer-slot__empty-plus">+</span>
                <span class="nav-customizer-slot__label">Boş Slot</span>
              </div>

              <!-- Sağ Sabit: Diğer -->
              <div class="nav-customizer-slot is-fixed" title="Sabit">
                <span class="nav-customizer-slot__icon">
                  <LayoutGrid :size="16" />
                </span>
                <span class="nav-customizer-slot__label">Diğer</span>
                <span class="nav-customizer-slot__tag">Sabit</span>
              </div>
            </div>

            <!-- 3 Slot Dolu İken Değiştirme Seçici -->
            <div v-if="pendingSwapModuleKey && MODULE_CATALOG[pendingSwapModuleKey]" class="nav-swap-prompt">
              <div class="nav-swap-prompt__head">
                <span>
                  <strong>{{ MODULE_CATALOG[pendingSwapModuleKey].label }}</strong> için alt barda değiştirilecek kısayolu seçin:
                </span>
                <button type="button" class="nav-swap-prompt__cancel" @click="pendingSwapModuleKey = null">İptal</button>
              </div>
              <div class="nav-swap-prompt__choices">
                <button
                  v-for="navMod in permittedNavbarItems"
                  :key="`swap-${navMod.key}`"
                  type="button"
                  class="nav-swap-choice-btn"
                  @click="confirmSwapNavbarSlot(navMod.key)"
                >
                  <component :is="navMod.icon" :size="14" />
                  <span>{{ navMod.navLabel }} yerine koy</span>
                </button>
              </div>
            </div>
          </div>

          <!-- Yatay Sayfa Başlığı Standartında Modül Listesi -->
          <div class="nav-sheet-list">
            <section
              v-for="group in visibleOperationGroups"
              :key="group.id"
              :data-group-id="group.id"
              class="nav-sheet-group"
              :class="{ 'is-dragging-group': dragState.active && dragState.type === 'group' && dragState.activeKey === group.id }"
            >
              <div
                class="nav-sheet-group__header"
                :class="{ 'is-draggable': editMode }"
                @pointerdown="editMode ? startGroupDrag(group.id, $event) : null"
              >
                <span
                  v-if="editMode"
                  class="nav-drag-handle nav-drag-handle--group"
                  aria-label="Grubu sürükle"
                  title="Grubu sıralamak için basılı tutup sürükleyin"
                >
                  <GripVertical :size="15" />
                </span>
                <span class="nav-sheet-group__title">{{ group.category }}</span>
              </div>

              <div
                v-for="op in group.items"
                :key="op.key"
                :data-item-group="group.id"
                :data-item-key="op.key"
                class="nav-sheet-item-wrap"
                :class="{ 'is-dragging-item': dragState.active && dragState.type === 'item' && dragState.activeKey === op.key }"
              >
                <button
                  type="button"
                  class="nav-sheet-item"
                  :class="[
                    `nav-sheet-item--${op.theme || 'blue'}`,
                    { 'is-edit-mode': editMode, 'is-pinned-nav': op.isPinned }
                  ]"
                  @click="editMode ? handleTogglePin(op.key) : selectOperation(op)"
                >
                  <span
                    v-if="editMode"
                    class="nav-drag-handle nav-drag-handle--item"
                    aria-label="Menüyü sürükle"
                    title="Grubun içinde sıralamak için basılı tutup sürükleyin"
                    @pointerdown="startItemDrag(group.id, op.key, $event)"
                    @click.stop
                  >
                    <GripVertical :size="18" />
                  </span>

                  <span class="nav-sheet-item__icon">
                    <component :is="op.icon" :size="20" />
                  </span>

                  <div class="nav-sheet-item__body">
                    <div class="nav-sheet-item__eyebrow-row">
                      <span v-if="op.eyebrow" class="nav-sheet-item__eyebrow">{{ op.eyebrow }}</span>
                      <span v-if="editMode && isModulePinnedInDraft(op.key)" class="nav-sheet-item__pin-badge">
                        Alt Barda
                      </span>
                    </div>
                    <strong class="nav-sheet-item__label">{{ op.label }}</strong>
                    <span v-if="op.subtitle" class="nav-sheet-item__subtitle">{{ op.subtitle }}</span>
                  </div>

                  <span
                    v-if="editMode"
                    class="nav-pin-toggle-btn"
                    :class="{ 'is-active': isModulePinnedInDraft(op.key) }"
                    :aria-label="isModulePinnedInDraft(op.key) ? 'Alt bardan çıkar' : 'Alt bara sabitle'"
                    @click.stop="handleTogglePin(op.key)"
                  >
                    <Pin :size="14" />
                    <span>{{ isModulePinnedInDraft(op.key) ? 'Alt Barda' : 'Alt Bar' }}</span>
                  </span>
                  <template v-else>
                    <span v-if="moduleBadge(op.key) > 0" class="nav-sheet-item__badge" :aria-label="`${moduleBadge(op.key)} bekleyen`">{{ moduleBadge(op.key) > 99 ? '99+' : moduleBadge(op.key) }}</span>
                    <ChevronRight :size="16" class="nav-sheet-item__arrow" />
                  </template>
                </button>
              </div>
            </section>
          </div>

          <!-- Alt Menü / Aksiyonlar -->
          <div v-if="editMode" class="nav-sheet-footer nav-sheet-footer--edit">
            <button
              type="button"
              class="nav-sheet-footer__btn"
              :disabled="savingOrder"
              @click="handleResetMenuOrder"
            >
              <RotateCcw :size="16" />
              <span>Varsayılana Sıfırla</span>
            </button>
            <button
              type="button"
              class="nav-sheet-footer__btn nav-sheet-footer__btn--primary"
              :disabled="savingOrder"
              @click="handleSaveMenuOrder"
            >
              <Check :size="17" />
              <span>{{ savingOrder ? 'Kaydediliyor…' : 'Düzeni Kaydet' }}</span>
            </button>
          </div>
          <div v-else class="nav-sheet-footer">
            <button
              type="button"
              class="nav-sheet-footer__btn"
              @click="selectOperation({ to: '/profil' })"
            >
              <UserRound :size="18" />
              <span>Profil</span>
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

  <nav class="bottom-nav" :class="{ 'has-drawer': showTaleplerDrawer || showAracDrawer || showFaturaDrawer || showKmDrawer || showRaporlarDrawer }" aria-label="Ana gezinme">
    <div class="bottom-nav__inner">
      <!-- Normal Navigasyon Sekmeleri -->
      <Transition name="nav-slide-tabs">
        <div
          v-if="!showTaleplerDrawer && !showAracDrawer && !showFaturaDrawer && !showKmDrawer && !showRaporlarDrawer"
          class="bottom-nav__tabs"
          :style="{ '--nav-count': visibleItems.length }"
        >
          <template v-for="item in visibleItems" :key="item.key || item.label">
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
                <span v-if="itemBadge(item) > 0" class="nav-badge" aria-hidden="true">{{ itemBadge(item) > 99 ? '99+' : itemBadge(item) }}</span>
              </span>
              <span class="nav-label">{{ item.label }}</span>
            </button>
            <button
              v-else-if="item.hasDrawer || item.href"
              type="button"
              class="nav-link"
              :class="{ 'is-active': isItemActive(item) }"
              :aria-label="item.label"
              @click="handleNavClick(item, $event)"
            >
              <span class="nav-icon">
                <component :is="item.icon" :size="21" :stroke-width="isItemActive(item) ? 2.4 : 1.9" />
                <span v-if="itemBadge(item) > 0" class="nav-badge" aria-hidden="true">{{ itemBadge(item) > 99 ? '99+' : itemBadge(item) }}</span>
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
                <span v-if="itemBadge(item) > 0" class="nav-badge" aria-hidden="true">{{ itemBadge(item) > 99 ? '99+' : itemBadge(item) }}</span>
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
            v-if="auth.state.permissions.invoice_dashboard"
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
            v-if="auth.state.permissions.invoice_outgoing"
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
            v-if="auth.state.permissions.invoice_incoming"
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
            v-if="auth.state.permissions.invoice_drafts"
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
            <!-- 0. Dashboard -->
            <button
              type="button"
              class="nav-link"
              :class="{ 'is-active': aracState.activeTab === 'dashboard' }"
              aria-label="Dashboard"
              @click="handleAracTabClick('dashboard')"
            >
              <span class="nav-icon">
                <LayoutDashboard :size="20" :stroke-width="aracState.activeTab === 'dashboard' ? 2.4 : 1.9" />
              </span>
              <span class="nav-label">Dashboard</span>
            </button>

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

      <!-- KM Onayları Sekmeleri Çekmecesi (Tam Nav Menü Formatında ve İkonlu) -->
      <Transition name="nav-slide-drawer">
        <div v-if="showKmDrawer" class="bottom-nav__tabs km-nav-tabs" style="--nav-count: 5;">
          <!-- 1. Bekleyen -->
          <button
            type="button"
            class="nav-link"
            :class="{ 'is-active': kmState.activeTab === 'pending' }"
            aria-label="Bekleyen KM Bildirimleri"
            @click="setKmTab('pending')"
          >
            <span class="nav-icon">
              <Clock :size="20" :stroke-width="kmState.activeTab === 'pending' ? 2.4 : 1.9" />
              <span class="nav-bubble-badge" :class="{ 'has-count': kmState.counts.pending > 0 }">
                {{ kmState.counts.pending }}
              </span>
            </span>
            <span class="nav-label">Bekleyen</span>
          </button>

          <!-- 2. Yapmayanlar -->
          <button
            type="button"
            class="nav-link"
            :class="{ 'is-active': kmState.activeTab === 'unreported' }"
            aria-label="Bildirim Yapmayanlar"
            @click="setKmTab('unreported')"
          >
            <span class="nav-icon">
              <AlertCircle :size="20" :stroke-width="kmState.activeTab === 'unreported' ? 2.4 : 1.9" />
              <span class="nav-bubble-badge nav-bubble-badge--amber" :class="{ 'has-count': kmState.counts.unreported > 0 }">
                {{ kmState.counts.unreported }}
              </span>
            </span>
            <span class="nav-label">Yapmayan</span>
          </button>

          <!-- 3. Onaylanan -->
          <button
            type="button"
            class="nav-link"
            :class="{ 'is-active': kmState.activeTab === 'approved' }"
            aria-label="Onaylanan KM'ler"
            @click="setKmTab('approved')"
          >
            <span class="nav-icon">
              <CheckCircle2 :size="20" :stroke-width="kmState.activeTab === 'approved' ? 2.4 : 1.9" />
              <span class="nav-bubble-badge nav-bubble-badge--emerald" :class="{ 'has-count': kmState.counts.approved > 0 }">
                {{ kmState.counts.approved }}
              </span>
            </span>
            <span class="nav-label">Onaylanan</span>
          </button>

          <!-- 4. Reddedilen -->
          <button
            type="button"
            class="nav-link"
            :class="{ 'is-active': kmState.activeTab === 'rejected' }"
            aria-label="Reddedilen KM'ler"
            @click="setKmTab('rejected')"
          >
            <span class="nav-icon">
              <XCircle :size="20" :stroke-width="kmState.activeTab === 'rejected' ? 2.4 : 1.9" />
              <span class="nav-bubble-badge nav-bubble-badge--coral" :class="{ 'has-count': kmState.counts.rejected > 0 }">
                {{ kmState.counts.rejected }}
              </span>
            </span>
            <span class="nav-label">Reddedilen</span>
          </button>

          <!-- 5. Kapat -->
          <button
            type="button"
            class="nav-link nav-link--close"
            aria-label="KM Onayları menüsünü kapat"
            title="Kapat"
            @click="closeKmDrawer"
          >
            <span class="nav-icon">
              <X :size="22" :stroke-width="2.3" />
            </span>
            <span class="nav-label">Kapat</span>
          </button>
        </div>
      </Transition>

      <!-- Raporlar / İş Türleri Çekmecesi (Talepler / KM Stili) -->
      <Transition name="nav-slide-drawer">
        <div v-if="showRaporlarDrawer" class="bottom-nav__tabs reports-nav-tabs">
          <div ref="reportsScrollEl" class="reports-nav-tabs__scroll">
            <!-- 1. Endeks Okuma -->
            <button
              type="button"
              class="nav-link"
              :class="{ 'is-active': reportsState.activeTab === 'okuma' }"
              aria-label="Endeks Okuma"
              @click="handleReportsTabClick('okuma')"
            >
              <span class="nav-icon">
                <Gauge :size="21" :stroke-width="reportsState.activeTab === 'okuma' ? 2.4 : 1.9" />
              </span>
              <span class="nav-label">Okuma</span>
            </button>

            <!-- 2. Kesme / Açma -->
            <button
              type="button"
              class="nav-link"
              :class="{ 'is-active': reportsState.activeTab === 'kesme' }"
              aria-label="Kesme Açma"
              @click="handleReportsTabClick('kesme')"
            >
              <span class="nav-icon">
                <ZapOff :size="21" :stroke-width="reportsState.activeTab === 'kesme' ? 2.4 : 1.9" />
              </span>
              <span class="nav-label">Kesme</span>
            </button>

            <!-- 3. Sayaç Sökme Takma -->
            <button
              type="button"
              class="nav-link"
              :class="{ 'is-active': reportsState.activeTab === 'sokme_takma' }"
              aria-label="Sayaç Sökme Takma"
              @click="handleReportsTabClick('sokme_takma')"
            >
              <span class="nav-icon">
                <Wrench :size="21" :stroke-width="reportsState.activeTab === 'sokme_takma' ? 2.4 : 1.9" />
              </span>
              <span class="nav-label">Sayaç</span>
            </button>

            <!-- 4. Mühürleme -->
            <button
              type="button"
              class="nav-link"
              :class="{ 'is-active': reportsState.activeTab === 'muhurleme' }"
              aria-label="Mühürleme"
              @click="handleReportsTabClick('muhurleme')"
            >
              <span class="nav-icon">
                <ShieldCheck :size="21" :stroke-width="reportsState.activeTab === 'muhurleme' ? 2.4 : 1.9" />
              </span>
              <span class="nav-label">Mühür</span>
            </button>

            <!-- 5. Kaçak Kontrol -->
            <button
              type="button"
              class="nav-link"
              :class="{ 'is-active': reportsState.activeTab === 'kacakkontrol' }"
              aria-label="Kaçak Kontrol"
              @click="handleReportsTabClick('kacakkontrol')"
            >
              <span class="nav-icon">
                <ShieldAlert :size="21" :stroke-width="reportsState.activeTab === 'kacakkontrol' ? 2.4 : 1.9" />
              </span>
              <span class="nav-label">Kaçak</span>
            </button>
          </div>

          <!-- Sabit Kapat Butonu -->
          <button
            type="button"
            class="nav-link nav-link--close reports-nav-tabs__close"
            aria-label="İş türleri menüsünü kapat"
            title="Kapat"
            @click="closeReportsDrawer"
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
