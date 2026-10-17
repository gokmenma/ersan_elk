<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import {
  AlertTriangle,
  ArrowRight,
  ArrowUpRight,
  CalendarCheck,
  CalendarDays,
  CarFront,
  ChartNoAxesCombined,
  Check,
  ChevronDown,
  ChevronRight,
  ChevronUp,
  ClipboardCheck,
  Clock,
  EyeOff,
  FileText,
  Gauge,
  GripVertical,
  Megaphone,
  Phone,
  RefreshCw,
  RotateCcw,
  ShieldAlert,
  SlidersHorizontal,
  UsersRound,
  X,
  Zap,
} from '@lucide/vue'
import TopBar from '../components/TopBar.vue'
import { auth } from '../services/auth.js'
import { getDashboard, saveDashboardSettings } from '../services/dashboard.js'

const router = useRouter()
const loading = ref(true)
const error = ref('')
const dashboard = ref({})
const showCustomizeModal = ref(false)
const selectedAnnouncement = ref(null)
const operationsMode = ref('daily') // 'daily' | 'monthly'
const isSavingSettings = ref(false)

const today = new Intl.DateTimeFormat('tr-TR', {
  day: 'numeric',
  month: 'long',
  weekday: 'long',
  timeZone: 'Europe/Istanbul',
}).format(new Date())

// Kart Tanımları ve Varsayılan Sıralama
const DEFAULT_CARD_ORDER = [
  'personnel',
  'pending_approvals',
  'shifts',
  'vehicles',
  'operations',
  'quick_actions',
  'announcements',
]

const CARD_DEFINITIONS = {
  personnel: {
    id: 'personnel',
    title: 'Personel Durumu',
    icon: UsersRound,
    description: 'Saha, izinli ve geç kalan personel dökümü',
  },
  pending_approvals: {
    id: 'pending_approvals',
    title: 'Bekleyen Onaylar',
    icon: AlertTriangle,
    description: 'Onay bekleyen talep, avans ve izinler',
  },
  shifts: {
    id: 'shifts',
    title: 'Bugünün Nöbetçileri',
    icon: CalendarCheck,
    description: 'Bugün görevli nöbetçi personeller',
  },
  vehicles: {
    id: 'vehicles',
    title: 'Araç & Filo Durumu',
    icon: CarFront,
    description: 'Aktif, sahada ve servisteki araçlar',
  },
  operations: {
    id: 'operations',
    title: 'Günün Operasyonları',
    icon: Gauge,
    description: 'Mühürleme, kesme, endeks ve sayaç sayaçları',
  },
  quick_actions: {
    id: 'quick_actions',
    title: 'Hızlı Erişim',
    icon: Zap,
    description: 'Sık kullanılan modüllere doğrudan kısayollar',
  },
  announcements: {
    id: 'announcements',
    title: 'Kurumsal Duyurular',
    icon: Megaphone,
    description: 'Şirket içi haberler ve duyurular',
  },
}

// Kart sıralaması ve görünürlük state'i
const cardOrder = ref([...DEFAULT_CARD_ORDER])
const cardSettings = ref({}) // { [cardId]: { hidden: boolean } }

// Hızlı İşlem Tanımları
const actionDefinitions = [
  { permission: 'personnel', label: 'Personel', icon: UsersRound, to: '/personel' },
  { permission: 'vehicles', label: 'Araçlar', icon: CarFront, to: '/arac-takip' },
  { permission: 'shifts', label: 'Nöbetler', icon: CalendarCheck, to: '/nobet-onay' },
  { permission: 'notices', label: 'İhbarlar', icon: Megaphone, to: '/ihbar' },
  { permission: 'reports', label: 'Puantaj', icon: CalendarDays, to: '/puantaj' },
  { permission: 'illegal', label: 'Kaçak Kontrol', icon: ShieldAlert, to: '/kacak-kontrol' },
  { permission: 'documents', label: 'Evraklar', icon: FileText, to: '/evrak-takip' },
  { permission: 'tasks', label: 'Görevler', icon: ClipboardCheck, to: '/gorevler' },
  { permission: 'reports', label: 'Raporlar', icon: ChartNoAxesCombined, to: '/raporlar' },
]

const actions = computed(() =>
  actionDefinitions.filter(item => auth.state.permissions[item.permission]).slice(0, 8),
)

// Reaktif Veri Modelleri
const personnel = computed(() => (auth.state.permissions.personnel ? dashboard.value.personnel : null))
const vehicles = computed(() => (auth.state.permissions.vehicles ? dashboard.value.vehicles : null))
const shifts = computed(() => (auth.state.permissions.shifts ? (dashboard.value.shifts || []) : []))
const pendingApprovals = computed(() => (auth.state.permissions.requests ? (dashboard.value.pending_approvals || { requests: 0, advances: 0, leaves: 0, total: 0 }) : null))
const announcements = computed(() => dashboard.value.announcements || [])
const operations = computed(() => dashboard.value.operations || [])

// Personel Metrikleri
const personnelRate = computed(() => {
  const total = Number(personnel.value?.total || 0)
  return total ? Math.min(100, Math.round((Number(personnel.value?.working || 0) / total) * 100)) : 0
})

const personnelSegments = computed(() => {
  const total = Number(personnel.value?.total || 0)
  if (!total) return { working: 0, leave: 0, late: 0, other: 0 }
  const working = Math.max(0, Number(personnel.value?.working || 0))
  const leave = Math.max(0, Number(personnel.value?.on_leave || 0))
  const late = Math.max(0, Number(personnel.value?.late || 0))
  const other = Math.max(0, total - working - leave)
  return {
    working: (working / total) * 100,
    leave: (leave / total) * 100,
    late: (late / total) * 100,
    other: (other / total) * 100,
  }
})

// Araç Metrikleri
const vehicleSegments = computed(() => {
  const total = Number(vehicles.value?.total || 0)
  const ratio = val => (total ? `${Math.max(0, (Number(val || 0) / total) * 100)}%` : '0%')
  return {
    field: ratio(vehicles.value?.field),
    service: ratio(vehicles.value?.service),
    idle: ratio(vehicles.value?.idle),
  }
})

const operationIcons = {
  sealing: ShieldAlert,
  cut_open: Zap,
  meter_change: Gauge,
  reading: Gauge,
  illegal: ShieldAlert,
  notice: Megaphone,
}

// Görünürlük Kontrolleri
function isCardVisible(cardId) {
  if (cardSettings.value[cardId]?.hidden) return false

  if (cardId === 'announcements') return announcements.value.length > 0
  if (cardId === 'pending_approvals') return Boolean(pendingApprovals.value)
  if (cardId === 'shifts') return Boolean(auth.state.permissions.shifts)
  if (cardId === 'personnel') return Boolean(personnel.value)
  if (cardId === 'vehicles') return Boolean(vehicles.value)
  if (cardId === 'operations') return operations.value.length > 0
  if (cardId === 'quick_actions') return actions.value.length > 0
  return true
}

// Sıralı ve Görünür Kart Listesi
const visibleCardList = computed(() => {
  return cardOrder.value.filter(cardId => isCardVisible(cardId))
})

// Gizlenen Kartlar Listesi
const hiddenCardList = computed(() => {
  return cardOrder.value.filter(cardId => cardSettings.value[cardId]?.hidden)
})

// Kart Taşıma (Yukarı / Aşağı)
function moveCard(cardId, direction) {
  const currentIndex = cardOrder.value.indexOf(cardId)
  if (currentIndex === -1) return
  const targetIndex = direction === 'up' ? currentIndex - 1 : currentIndex + 1
  if (targetIndex < 0 || targetIndex >= cardOrder.value.length) return

  const newOrder = [...cardOrder.value]
  const [removed] = newOrder.splice(currentIndex, 1)
  newOrder.splice(targetIndex, 0, removed)
  cardOrder.value = newOrder
  persistSettings()
}

// Kart Gizle / Göster
function toggleCardVisibility(cardId) {
  const currentHidden = Boolean(cardSettings.value[cardId]?.hidden)
  cardSettings.value = {
    ...cardSettings.value,
    [cardId]: { ...cardSettings.value[cardId], hidden: !currentHidden },
  }
  persistSettings()
}

// Varsayılan Sıralama ve Görünürlüğe Sıfırla
function resetToDefault() {
  cardOrder.value = [...DEFAULT_CARD_ORDER]
  cardSettings.value = {}
  persistSettings()
}

// Sürükle - Bırak (HTML5 Drag & Touch)
const draggedCard = ref(null)
const dragOverCard = ref(null)
const dragOverPosition = ref(null) // 'top' | 'bottom'

function handleDragStart(cardId, event) {
  draggedCard.value = cardId
  if (event.dataTransfer) {
    event.dataTransfer.effectAllowed = 'move'
    event.dataTransfer.setData('text/plain', cardId)
  }
}

function handleDragOver(cardId, event) {
  event.preventDefault()
  if (draggedCard.value === cardId) return
  dragOverCard.value = cardId

  const rect = event.currentTarget.getBoundingClientRect()
  const offset = event.clientY - rect.top
  dragOverPosition.value = offset > rect.height / 2 ? 'bottom' : 'top'
}

function handleDragLeave(cardId) {
  if (dragOverCard.value === cardId) {
    dragOverCard.value = null
    dragOverPosition.value = null
  }
}

function handleDrop(cardId, event) {
  event.preventDefault()
  if (!draggedCard.value || draggedCard.value === cardId) {
    resetDragState()
    return
  }

  const fromIndex = cardOrder.value.indexOf(draggedCard.value)
  let toIndex = cardOrder.value.indexOf(cardId)
  if (fromIndex === -1 || toIndex === -1) {
    resetDragState()
    return
  }

  if (dragOverPosition.value === 'bottom' && fromIndex < toIndex) {
    // Aynı kalır
  } else if (dragOverPosition.value === 'bottom') {
    toIndex += 1
  }

  const newOrder = [...cardOrder.value]
  const [item] = newOrder.splice(fromIndex, 1)
  newOrder.splice(toIndex > fromIndex ? toIndex - 1 : toIndex, 0, item)
  cardOrder.value = newOrder

  resetDragState()
  persistSettings()
}

function handleDragEnd() {
  resetDragState()
}

function resetDragState() {
  draggedCard.value = null
  dragOverCard.value = null
  dragOverPosition.value = null
}

// Ayarları Yerel Depolama ve Sunucuya Kaydetme
async function persistSettings() {
  try {
    localStorage.setItem('ersan_mobile_home_card_order', JSON.stringify(cardOrder.value))
    localStorage.setItem('ersan_mobile_home_card_settings', JSON.stringify(cardSettings.value))
  } catch (e) {}

  isSavingSettings.value = true
  try {
    await saveDashboardSettings(cardOrder.value, cardSettings.value)
  } catch (err) {
    console.warn('Dashboard ayarları sunucuya kaydedilemedi:', err)
  } finally {
    isSavingSettings.value = false
  }
}

// Yerel ve Sunucu Ayarlarını Yükleme
function loadCachedPreferences() {
  try {
    const cachedOrder = localStorage.getItem('ersan_mobile_home_card_order')
    if (cachedOrder) {
      const parsed = JSON.parse(cachedOrder)
      if (Array.isArray(parsed) && parsed.length) {
        cardOrder.value = parsed
      }
    }
    const cachedSettings = localStorage.getItem('ersan_mobile_home_card_settings')
    if (cachedSettings) {
      const parsedSettings = JSON.parse(cachedSettings)
      if (typeof parsedSettings === 'object' && parsedSettings !== null) {
        cardSettings.value = parsedSettings
      }
    }
  } catch (e) {}
}

function openAction(action) {
  if (action.to) router.push(action.to)
  else if (action.href) window.location.href = action.href
}

function formatDate(value) {
  if (!value) return ''
  const date = new Date(value)
  return !Number.isNaN(date.getTime())
    ? new Intl.DateTimeFormat('tr-TR', { day: '2-digit', month: 'short' }).format(date)
    : ''
}

function openAnnouncementDetail(announcement) {
  selectedAnnouncement.value = announcement
}

function closeAnnouncementDetail() {
  selectedAnnouncement.value = null
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const data = await getDashboard()
    dashboard.value = data

    // Sunucudan gelen tercihleri uygula
    if (data.card_preferences) {
      if (Array.isArray(data.card_preferences.order) && data.card_preferences.order.length) {
        cardOrder.value = data.card_preferences.order
      }
      if (data.card_preferences.settings && typeof data.card_preferences.settings === 'object') {
        cardSettings.value = data.card_preferences.settings
      }
    }
  } catch (reason) {
    error.value = reason.message
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadCachedPreferences()
  load()
})
</script>

<template>
  <section class="ds-screen ds-home" :aria-busy="loading">
    <!-- En Üst Bar: Kullanıcı isteği doğrultusunda sağ tarafta "Kartları Düzenle" butonu -->
    <TopBar
      :eyebrow="today"
      :subtitle="auth.state.company?.name ? 'İşinizin bugünkü görünümü.' : 'Günlük operasyon ve saha özeti.'"
    >
      <template #actions>
        <button
          type="button"
          class="clay-topbar-edit-btn"
          aria-label="Ana sayfayı özelleştir"
          title="Ana Sayfayı Düzenle"
          @click="showCustomizeModal = true"
        >
          <span class="edit-icon-wrap"><SlidersHorizontal :size="13" /></span>
          <span>Kartları Düzenle</span>
        </button>
      </template>
    </TopBar>

    <!-- Yükleniyor İskeleti -->
    <div v-if="loading" class="clay-skeleton-grid" role="status" aria-label="Günlük özet yükleniyor">
      <div class="clay-skeleton-card clay-skeleton-card--md"></div>
      <div class="clay-skeleton-card clay-skeleton-card--lg"></div>
    </div>

    <!-- Hata Kartı -->
    <div v-else-if="error" class="clay-state-card" role="alert">
      <RefreshCw :size="24" />
      <strong>Veriler alınamadı</strong>
      <p>{{ error }}</p>
      <button type="button" class="clay-retry-btn" @click="load">Tekrar dene</button>
    </div>

    <!-- Dashboard Kartlar Listesi (Taşıma İşlemleri Kartların İçinde En Üstte) -->
    <template v-else>
      <div class="home-sections-stack">
        <template v-for="cardId in visibleCardList" :key="cardId">
          <div
            class="home-card-wrapper"
            :class="{
              'is-dragging': draggedCard === cardId,
              'drag-over-top': dragOverCard === cardId && dragOverPosition === 'top',
              'drag-over-bottom': dragOverCard === cardId && dragOverPosition === 'bottom',
            }"
            draggable="true"
            @dragstart="handleDragStart(cardId, $event)"
            @dragover="handleDragOver(cardId, $event)"
            @dragleave="handleDragLeave(cardId)"
            @drop="handleDrop(cardId, $event)"
            @dragend="handleDragEnd"
          >
            <!-- 1. PERSONEL DURUMU (MİNİMAL, KOMPAKT VE ŞIK) -->
            <section
              v-if="cardId === 'personnel' && personnel"
              class="bento-card bento-card--dark"
            >
              <!-- Kart İçi En Üst Kontroller & Başlık -->
              <div class="card-inner-head">
                <div class="card-inner-title">
                  <UsersRound :size="16" class="text-blue" />
                  <span>Personel Durumu</span>
                  <span class="dark-pill-tag ms-1"><span class="bento-status-dot"></span> SAHADA</span>
                </div>
                <div class="card-inner-controls">
                  <span
                    class="card-inner-handle"
                    title="Sürükle ve taşı"
                    aria-label="Sürükle"
                  >
                    <GripVertical :size="14" />
                  </span>
                  <button
                    type="button"
                    class="card-inner-btn"
                    title="Yukarı taşı"
                    aria-label="Yukarı taşı"
                    @click.stop="moveCard(cardId, 'up')"
                  >
                    <ChevronUp :size="14" />
                  </button>
                  <button
                    type="button"
                    class="card-inner-btn"
                    title="Aşağı taşı"
                    aria-label="Aşağı taşı"
                    @click.stop="moveCard(cardId, 'down')"
                  >
                    <ChevronDown :size="14" />
                  </button>
                  <button
                    type="button"
                    class="card-inner-btn card-inner-btn--danger"
                    title="Kartı gizle"
                    aria-label="Kartı gizle"
                    @click.stop="toggleCardVisibility(cardId)"
                  >
                    <EyeOff :size="14" />
                  </button>
                </div>
              </div>

              <!-- Minimal Ana Metrik ve Ekip Linki -->
              <div class="personnel-minimal-body">
                <div class="personnel-minimal-stat">
                  <strong>{{ personnel.working }}</strong>
                  <span>/ {{ personnel.total }} sahada aktif (%{{ personnelRate }})</span>
                </div>
                <RouterLink to="/personel" class="personnel-minimal-link">
                  <span>Ekip detayı</span>
                  <ArrowUpRight :size="13" />
                </RouterLink>
              </div>

              <!-- İnce Zarif Progress Çubuğu -->
              <div
                class="personnel-minimal-track"
                role="img"
                :aria-label="`Sahada ${personnel.working}, İzinli ${personnel.on_leave}, Geç kalan ${personnel.late || 0}`"
              >
                <span class="seg-pill seg-pill--blue" :style="{ width: `${personnelSegments.working}%` }"></span>
                <span class="seg-pill seg-pill--yellow" :style="{ width: `${personnelSegments.leave}%` }"></span>
                <span class="seg-pill seg-pill--coral" :style="{ width: `${personnelSegments.late}%` }"></span>
              </div>

              <!-- 3'lü Kompakt Alt Metrik -->
              <div class="personnel-minimal-stats">
                <div class="personnel-stat-col">
                  <span><i class="ds-dot ds-dot--blue"></i>Sahada</span>
                  <strong>{{ personnel.working }}</strong>
                </div>
                <div class="personnel-stat-col">
                  <span><i class="ds-dot ds-dot--yellow"></i>İzinli</span>
                  <strong>{{ personnel.on_leave }}</strong>
                </div>
                <div class="personnel-stat-col">
                  <span><i class="ds-dot ds-dot--coral"></i>Geç Kalan</span>
                  <strong>{{ personnel.late || 0 }}</strong>
                </div>
              </div>
            </section>

            <!-- 2. BEKLEYEN ONAYLAR -->
            <section
              v-else-if="cardId === 'pending_approvals' && pendingApprovals"
              class="bento-card bento-card--white approvals-bento"
            >
              <!-- Kart İçi En Üst Kontroller -->
              <div class="card-inner-head">
                <div class="card-inner-title">
                  <AlertTriangle :size="16" class="text-amber" />
                  <span>Bekleyen Onaylar</span>
                  <span class="clay-pill-tag ms-1">{{ pendingApprovals.total }} İşlem</span>
                </div>
                <div class="card-inner-controls">
                  <span class="card-inner-handle" title="Sürükle ve taşı"><GripVertical :size="14" /></span>
                  <button type="button" class="card-inner-btn" title="Yukarı taşı" @click.stop="moveCard(cardId, 'up')"><ChevronUp :size="14" /></button>
                  <button type="button" class="card-inner-btn" title="Aşağı taşı" @click.stop="moveCard(cardId, 'down')"><ChevronDown :size="14" /></button>
                  <button type="button" class="card-inner-btn card-inner-btn--danger" title="Kartı gizle" @click.stop="toggleCardVisibility(cardId)"><EyeOff :size="14" /></button>
                </div>
              </div>

              <div class="approvals-content">
                <div class="approvals-info-block">
                  <div class="approvals-icon-bubble">
                    <AlertTriangle :size="20" />
                  </div>
                  <div class="approvals-text">
                    <strong>{{ pendingApprovals.total > 0 ? `${pendingApprovals.total} Onay Bekliyor` : 'Tüm Onaylar Güncel' }}</strong>
                    <p v-if="pendingApprovals.total > 0">
                      {{ pendingApprovals.requests }} talep, {{ pendingApprovals.advances }} avans, {{ pendingApprovals.leaves }} izin
                    </p>
                    <p v-else class="text-emerald">Bekleyen personel işlemi bulunmuyor.</p>
                  </div>
                </div>
                <RouterLink
                  to="/talepler"
                  class="clay-pill-btn"
                  aria-label="Onay ekranına git"
                >
                  <span>İncele</span>
                  <ChevronRight :size="13" />
                </RouterLink>
              </div>
            </section>

            <!-- 3. BUGÜNÜN NÖBETÇİLERİ -->
            <section
              v-else-if="cardId === 'shifts'"
              class="bento-card bento-card--white"
            >
              <!-- Kart İçi En Üst Kontroller -->
              <div class="card-inner-head">
                <div class="card-inner-title">
                  <CalendarCheck :size="16" class="text-primary" />
                  <span>Bugünün Nöbetçileri</span>
                  <span class="clay-pill-tag ms-1">{{ shifts.length ? `${shifts.length} Kişi` : 'Yok' }}</span>
                </div>
                <div class="card-inner-controls">
                  <RouterLink to="/nobet-onay" class="clay-section-link text-[11px] me-1">
                    Takvim <ChevronRight :size="12" />
                  </RouterLink>
                  <span class="card-inner-handle" title="Sürükle ve taşı"><GripVertical :size="14" /></span>
                  <button type="button" class="card-inner-btn" title="Yukarı taşı" @click.stop="moveCard(cardId, 'up')"><ChevronUp :size="14" /></button>
                  <button type="button" class="card-inner-btn" title="Aşağı taşı" @click.stop="moveCard(cardId, 'down')"><ChevronDown :size="14" /></button>
                  <button type="button" class="card-inner-btn card-inner-btn--danger" title="Kartı gizle" @click.stop="toggleCardVisibility(cardId)"><EyeOff :size="14" /></button>
                </div>
              </div>

              <div v-if="shifts.length" class="shifts-grid">
                <div v-for="shift in shifts" :key="shift.id" class="shift-card-row">
                  <div class="shift-person-info">
                    <div class="shift-avatar">
                      <img v-if="shift.photo" :src="shift.photo" :alt="shift.name" />
                      <span v-else>{{ shift.initials }}</span>
                    </div>
                    <div class="shift-meta">
                      <strong>{{ shift.name }}</strong>
                      <span>
                        <Clock :size="11" />
                        {{ shift.start_time }} - {{ shift.end_time }}
                      </span>
                    </div>
                  </div>
                  <a
                    v-if="shift.phone"
                    :href="`tel:${shift.phone}`"
                    class="shift-call-btn"
                    :title="`${shift.name} ara`"
                    :aria-label="`${shift.name} telefonla ara`"
                  >
                    <Phone :size="15" />
                  </a>
                </div>
              </div>

              <div v-else class="text-center py-3 text-xs text-muted">
                Bugün için atanmış onaylı nöbet kaydı bulunmuyor.
              </div>
            </section>

            <!-- 4. ARAÇ DURUMU -->
            <section
              v-else-if="cardId === 'vehicles' && vehicles"
              class="bento-card bento-card--white"
            >
              <!-- Kart İçi En Üst Kontroller -->
              <div class="card-inner-head">
                <div class="card-inner-title">
                  <CarFront :size="16" class="text-emerald" />
                  <span>Araç & Filo</span>
                  <span class="clay-pill-tag ms-1">{{ vehicles.total }} Araç</span>
                </div>
                <div class="card-inner-controls">
                  <RouterLink to="/arac-takip" class="clay-section-link text-[11px] me-1">
                    Filo <ChevronRight :size="12" />
                  </RouterLink>
                  <span class="card-inner-handle" title="Sürükle ve taşı"><GripVertical :size="14" /></span>
                  <button type="button" class="card-inner-btn" title="Yukarı taşı" @click.stop="moveCard(cardId, 'up')"><ChevronUp :size="14" /></button>
                  <button type="button" class="card-inner-btn" title="Aşağı taşı" @click.stop="moveCard(cardId, 'down')"><ChevronDown :size="14" /></button>
                  <button type="button" class="card-inner-btn card-inner-btn--danger" title="Kartı gizle" @click.stop="toggleCardVisibility(cardId)"><EyeOff :size="14" /></button>
                </div>
              </div>

              <div
                class="segmented-track"
                role="img"
                :aria-label="`Sahada ${vehicles.field}, serviste ${vehicles.service}, boşta ${vehicles.idle} araç`"
              >
                <span class="seg-pill seg-pill--emerald" :style="{ width: vehicleSegments.field }"></span>
                <span class="seg-pill seg-pill--coral" :style="{ width: vehicleSegments.service }"></span>
                <span class="seg-pill seg-pill--amber" :style="{ width: vehicleSegments.idle }"></span>
              </div>
              <div class="bento-team-metrics mt-2">
                <div><span>Sahada</span><strong>{{ vehicles.field }}</strong></div>
                <div><span>Serviste</span><strong>{{ vehicles.service }}</strong></div>
                <div><span>Boşta</span><strong>{{ vehicles.idle }}</strong></div>
              </div>
            </section>

            <!-- 5. GÜNÜN OPERASYONLARI -->
            <section
              v-else-if="cardId === 'operations' && operations.length"
              class="bento-card bento-card--white"
            >
              <!-- Kart İçi En Üst Kontroller & Gün/Ay Toggle -->
              <div class="card-inner-head">
                <div class="card-inner-title">
                  <Gauge :size="16" class="text-primary" />
                  <span>Operasyonlar</span>
                  <div class="op-mode-toggle ms-1">
                    <button
                      type="button"
                      class="op-mode-btn"
                      :class="{ 'is-active': operationsMode === 'daily' }"
                      @click="operationsMode = 'daily'"
                    >
                      Gün
                    </button>
                    <button
                      type="button"
                      class="op-mode-btn"
                      :class="{ 'is-active': operationsMode === 'monthly' }"
                      @click="operationsMode = 'monthly'"
                    >
                      Ay
                    </button>
                  </div>
                </div>
                <div class="card-inner-controls">
                  <span class="card-inner-handle" title="Sürükle ve taşı"><GripVertical :size="14" /></span>
                  <button type="button" class="card-inner-btn" title="Yukarı taşı" @click.stop="moveCard(cardId, 'up')"><ChevronUp :size="14" /></button>
                  <button type="button" class="card-inner-btn" title="Aşağı taşı" @click.stop="moveCard(cardId, 'down')"><ChevronDown :size="14" /></button>
                  <button type="button" class="card-inner-btn card-inner-btn--danger" title="Kartı gizle" @click.stop="toggleCardVisibility(cardId)"><EyeOff :size="14" /></button>
                </div>
              </div>

              <div class="operations-grid">
                <article
                  v-for="item in operations"
                  :key="item.key"
                  class="operation-tile cursor-pointer"
                  @click="item.link && router.push(item.link)"
                >
                  <header class="operation-tile-top">
                    <span class="op-icon-bubble">
                      <component :is="operationIcons[item.key] || Zap" :size="17" />
                    </span>
                    <span class="op-label">{{ item.label }}</span>
                  </header>

                  <div class="operation-tile-bottom">
                    <strong>{{ operationsMode === 'daily' ? item.daily : item.monthly }}</strong>
                    <span>{{ operationsMode === 'daily' ? 'Bugün' : 'Bu ay' }}</span>
                  </div>

                  <footer v-if="item.last_update" class="op-footer-row">
                    <span class="op-last-update">
                      <Clock :size="10" />
                      {{ item.last_update }}
                    </span>
                    <span class="text-[10px] text-primary font-bold">Git</span>
                  </footer>
                </article>
              </div>
            </section>

            <!-- 6. HIZLI ERİŞİM -->
            <section
              v-else-if="cardId === 'quick_actions' && actions.length"
              class="bento-card bento-card--white"
            >
              <!-- Kart İçi En Üst Kontroller -->
              <div class="card-inner-head">
                <div class="card-inner-title">
                  <Zap :size="16" class="text-amber" />
                  <span>Hızlı Erişim</span>
                </div>
                <div class="card-inner-controls">
                  <span class="card-inner-handle" title="Sürükle ve taşı"><GripVertical :size="14" /></span>
                  <button type="button" class="card-inner-btn" title="Yukarı taşı" @click.stop="moveCard(cardId, 'up')"><ChevronUp :size="14" /></button>
                  <button type="button" class="card-inner-btn" title="Aşağı taşı" @click.stop="moveCard(cardId, 'down')"><ChevronDown :size="14" /></button>
                  <button type="button" class="card-inner-btn card-inner-btn--danger" title="Kartı gizle" @click.stop="toggleCardVisibility(cardId)"><EyeOff :size="14" /></button>
                </div>
              </div>

              <div class="quick-action-grid" :style="{ '--action-count': Math.min(actions.length, 4) }">
                <button
                  v-for="action in actions"
                  :key="action.label"
                  type="button"
                  class="quick-action-tile"
                  @click="openAction(action)"
                >
                  <span class="quick-action-icon">
                    <component :is="action.icon" :size="20" :stroke-width="1.7" />
                  </span>
                  <span class="quick-action-text"><strong>{{ action.label }}</strong></span>
                </button>
              </div>
            </section>

            <!-- 7. KURUMSAL DUYURULAR -->
            <section
              v-else-if="cardId === 'announcements' && announcements.length"
              class="announcement-slide-card"
              @click="openAnnouncementDetail(announcements[0])"
            >
              <!-- Kart İçi En Üst Kontroller -->
              <div class="card-inner-head">
                <div class="card-inner-title text-white">
                  <Megaphone :size="15" />
                  <span class="text-[10px] font-bold uppercase tracking-wider text-white/90">KURUMSAL DUYURU</span>
                  <span class="text-[10px] text-white/70">{{ formatDate(announcements[0].date) }}</span>
                </div>
                <div class="card-inner-controls" @click.stop>
                  <span class="card-inner-handle" title="Sürükle ve taşı"><GripVertical :size="14" /></span>
                  <button type="button" class="card-inner-btn" title="Yukarı taşı" @click.stop="moveCard(cardId, 'up')"><ChevronUp :size="14" /></button>
                  <button type="button" class="card-inner-btn" title="Aşağı taşı" @click.stop="moveCard(cardId, 'down')"><ChevronDown :size="14" /></button>
                  <button type="button" class="card-inner-btn card-inner-btn--danger" title="Kartı gizle" @click.stop="toggleCardVisibility(cardId)"><EyeOff :size="14" /></button>
                </div>
              </div>

              <strong class="block text-sm font-bold text-white truncate drop-shadow-sm">{{ announcements[0].title }}</strong>
              <p class="text-xs text-white/90 line-clamp-2 mt-1 leading-snug">{{ announcements[0].content }}</p>
              <div class="flex items-center gap-1.5 text-[11px] font-semibold text-white/90 mt-2.5 pt-1.5 border-t border-white/15">
                <span>Detayları görüntüle</span>
                <ArrowRight :size="13" class="ms-auto" />
              </div>
            </section>
          </div>
        </template>
      </div>

      <!-- Kapatılan / Gizlenen Kartlar Çubuğu -->
      <div v-if="hiddenCardList.length" class="hidden-cards-bar">
        <div class="flex items-center gap-2">
          <EyeOff :size="15" class="text-muted" />
          <span class="text-xs font-semibold text-muted">
            {{ hiddenCardList.length }} kart gizlendi
          </span>
        </div>
        <button
          type="button"
          class="clay-pill-btn"
          @click="showCustomizeModal = true"
        >
          <span>Yeniden Göster</span>
          <ChevronRight :size="12" />
        </button>
      </div>
    </template>

    <!-- KARTLARI ÖZELLEŞTİRME BOTTOM SHEET MODALI -->
    <div
      v-if="showCustomizeModal"
      class="home-sheet-backdrop"
      @click.self="showCustomizeModal = false"
    >
      <div class="home-sheet-modal">
        <div class="home-sheet-handle"></div>
        <header class="home-sheet-head">
          <div>
            <h3>Ana Sayfayı Özelleştir</h3>
            <p>Kartların sırasını belirleyin, kapatın veya açın.</p>
          </div>
          <button
            type="button"
            class="clay-circle-btn"
            aria-label="Kapat"
            @click="showCustomizeModal = false"
          >
            <X :size="18" />
          </button>
        </header>

        <div class="home-sheet-body">
          <div
            v-for="(cardId, index) in cardOrder"
            :key="cardId"
            class="customize-item-row"
            :class="{ 'is-hidden': cardSettings[cardId]?.hidden }"
          >
            <div class="customize-item-left">
              <span class="customize-item-icon">
                <component :is="CARD_DEFINITIONS[cardId]?.icon || Zap" :size="18" />
              </span>
              <div class="customize-item-meta">
                <strong>{{ CARD_DEFINITIONS[cardId]?.title || cardId }}</strong>
                <span>{{ CARD_DEFINITIONS[cardId]?.description || '' }}</span>
              </div>
            </div>

            <div class="customize-item-actions">
              <button
                type="button"
                class="home-card-btn"
                :disabled="index === 0"
                title="Yukarı Taşı"
                aria-label="Yukarı Taşı"
                @click="moveCard(cardId, 'up')"
              >
                <ChevronUp :size="16" />
              </button>
              <button
                type="button"
                class="home-card-btn"
                :disabled="index === cardOrder.length - 1"
                title="Aşağı Taşı"
                aria-label="Aşağı Taşı"
                @click="moveCard(cardId, 'down')"
              >
                <ChevronDown :size="16" />
              </button>
              <label class="switch-toggle" :title="cardSettings[cardId]?.hidden ? 'Kartı Göster' : 'Kartı Gizle'">
                <input
                  type="checkbox"
                  :checked="!cardSettings[cardId]?.hidden"
                  @change="toggleCardVisibility(cardId)"
                />
                <span class="switch-slider"></span>
              </label>
            </div>
          </div>
        </div>

        <footer class="home-sheet-foot">
          <button
            type="button"
            class="clay-pill-btn clay-pill-btn--secondary"
            @click="resetToDefault"
          >
            <RotateCcw :size="13" />
            <span>Varsayılana Sıfırla</span>
          </button>
          <button
            type="button"
            class="clay-pill-btn ms-auto"
            @click="showCustomizeModal = false"
          >
            <Check :size="14" />
            <span>Tamamla</span>
          </button>
        </footer>
      </div>
    </div>

    <!-- KURUMSAL DUYURU DETAY BOTTOM SHEET MODALI -->
    <div
      v-if="selectedAnnouncement"
      class="home-sheet-backdrop"
      @click.self="closeAnnouncementDetail"
    >
      <div class="home-sheet-modal">
        <div class="home-sheet-handle"></div>
        <header class="home-sheet-head">
          <div class="flex items-center gap-2">
            <span class="w-8 h-8 rounded-xl bg-primary/10 text-primary flex items-center justify-center">
              <Megaphone :size="17" />
            </span>
            <div>
              <span class="text-[10px] font-bold uppercase tracking-wider text-primary">KURUMSAL DUYURU</span>
              <p class="text-xs text-muted">{{ formatDate(selectedAnnouncement.date) }}</p>
            </div>
          </div>
          <button
            type="button"
            class="clay-circle-btn"
            aria-label="Kapat"
            @click="closeAnnouncementDetail"
          >
            <X :size="18" />
          </button>
        </header>

        <div class="home-sheet-body">
          <img
            v-if="selectedAnnouncement.image"
            :src="selectedAnnouncement.image"
            alt="Duyuru Görseli"
            class="w-full h-auto max-h-56 object-cover rounded-2xl mb-2 border border-slate-100"
          />
          <h3 class="text-base font-bold text-ink leading-snug">{{ selectedAnnouncement.title }}</h3>
          <div v-if="selectedAnnouncement.event_date" class="clay-pill-tag self-start">
            <CalendarDays :size="12" />
            <span>Etkinlik: {{ formatDate(selectedAnnouncement.event_date) }}</span>
          </div>
          <p class="text-sm text-slate-700 leading-relaxed whitespace-pre-line mt-2">
            {{ selectedAnnouncement.raw_content || selectedAnnouncement.content }}
          </p>
        </div>

        <footer class="home-sheet-foot">
          <button
            type="button"
            class="clay-pill-btn w-full justify-center py-2.5 text-xs font-bold"
            @click="closeAnnouncementDetail"
          >
            Kapat
          </button>
        </footer>
      </div>
    </div>
  </section>
</template>
