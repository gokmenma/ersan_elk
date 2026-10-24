<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import {
  AlertCircle,
  Bell,
  Calendar,
  CalendarCheck,
  CalendarDays,
  Check,
  CheckCheck,
  CheckSquare,
  ChevronLeft,
  ChevronRight,
  Clock,
  Filter,
  Layers,
  Moon,
  Plus,
  RefreshCw,
  Search,
  Sparkles,
  Square,
  Sun,
  Trash2,
  User,
  Users,
  X,
  XCircle,
} from '@lucide/vue'
import TopBar from '../components/TopBar.vue'
import { auth } from '../services/auth.js'
import {
  nobetApi,
  nobetState,
  setNobetMonth,
  setNobetTab,
} from '../services/nobet.js'

const router = useRouter()

// Yüklenme & Hata Durumları
const loading = ref(false)
const actionLoading = ref(false)
const errorMessage = ref('')
const toastMessage = ref('')
let toastTimer = null

// Veriler
const shifts = ref([])
const calendarDays = ref([])
const poolPersonnel = ref([])
const allPersonnel = ref([])
const maxPoolShifts = ref(1)

// Seçili Gün (Takvim için)
const selectedCalendarDate = ref(null)

// Arama & Filtreler
const searchInput = ref('')
let searchDebounceTimer = null
const currentDurum = ref('bekleyen') // 'bekleyen' | 'onaylanan' | 'tumu'
const currentTip = ref('') // '' | 'hafta_ici' | 'hafta_sonu' | 'resmi_tatil'

// Çoklu Seçim Modu
const selectionMode = ref(false)
const selectedIds = ref(new Set())

// Planlama Bottom Sheet (Modal)
const planModalOpen = ref(false)
const planPersonnelSearch = ref('')
const planForm = ref({
  personelId: '',
  personelName: '',
  nobetTarihi: '',
  nobetTipi: 'standart',
  baslangicSaati: '18:00',
  bitisSaati: '08:00',
  aciklama: '',
  yoneticiOnayi: true,
})

// Onay Modalı (Silme veya Toplu İşlemler)
const confirmModalOpen = ref(false)
const confirmData = ref({
  title: '',
  message: '',
  type: 'danger', // 'danger' | 'success' | 'warning'
  confirmText: 'Onayla',
  action: null,
})

const monthNames = [
  'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran',
  'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'
]

const currentMonthLabel = computed(() => {
  return `${monthNames[nobetState.selectedMonth - 1]} ${nobetState.selectedYear}`
})

// Takvim Pazartesi ile başlar. Ayın ilk gününden önceki sütunları boş
// bırakarak gün numaralarının gerçek hafta günleriyle hizalanmasını sağla.
const calendarCells = computed(() => {
  const firstDay = new Date(
    nobetState.selectedYear,
    nobetState.selectedMonth - 1,
    1,
  ).getDay()
  const leadingEmptyCellCount = (firstDay + 6) % 7

  return [
    ...Array.from({ length: leadingEmptyCellCount }, (_, index) => ({
      key: `empty-${index}`,
      isEmpty: true,
    })),
    ...calendarDays.value.map(day => ({
      ...day,
      key: day.date,
      isEmpty: false,
    })),
  ]
})

// KPI Kartları
const kpiStats = computed(() => nobetState.stats)

// Toast Göster
function showToast(msg) {
  toastMessage.value = msg
  if (toastTimer) clearTimeout(toastTimer)
  toastTimer = setTimeout(() => {
    toastMessage.value = ''
  }, 3500)
}

// Ay Değiştirme
function changeMonth(delta) {
  let m = nobetState.selectedMonth + delta
  let y = nobetState.selectedYear
  if (m > 12) {
    m = 1
    y++
  } else if (m < 1) {
    m = 12
    y--
  }
  setNobetMonth(m, y)
  selectedCalendarDate.value = null
  selectedIds.value.clear()
  selectionMode.value = false
  loadCurrentView()
}

// Ana Veri Yükleme
async function loadCurrentView() {
  loading.value = true
  errorMessage.value = ''
  try {
    if (nobetState.activeTab === 'bekleyen') {
      await loadShifts()
    } else if (nobetState.activeTab === 'planlama') {
      await loadCalendar()
    } else if (nobetState.activeTab === 'havuz') {
      await loadPersonnelSummary()
    }
  } catch (err) {
    errorMessage.value = err.message || 'Veriler yüklenirken bir hata oluştu.'
  } finally {
    loading.value = false
  }
}

// Nöbet Listesini Çek
async function loadShifts() {
  const res = await nobetApi.list({
    ay: nobetState.selectedMonth,
    yil: nobetState.selectedYear,
    durum: currentDurum.value,
    tip: currentTip.value,
    search: searchInput.value.trim(),
  })
  shifts.value = res.list || []
  if (res.stats) {
    Object.assign(nobetState.stats, res.stats)
  }
}

// Takvim Verilerini Çek
async function loadCalendar() {
  const res = await nobetApi.calendar({
    ay: nobetState.selectedMonth,
    yil: nobetState.selectedYear,
  })
  calendarDays.value = res.days || []
  // Varsayılan olarak bugünü veya ilk günü seç
  if (!selectedCalendarDate.value && calendarDays.value.length > 0) {
    const todayStr = new Date().toISOString().slice(0, 10)
    const foundToday = calendarDays.value.find(d => d.date === todayStr)
    selectedCalendarDate.value = foundToday ? foundToday.date : calendarDays.value[0].date
  }
}

// Personel Dağılımını Çek
async function loadPersonnelSummary() {
  const res = await nobetApi.personnelSummary({
    ay: nobetState.selectedMonth,
    yil: nobetState.selectedYear,
    search: searchInput.value.trim(),
  })
  poolPersonnel.value = res.personnel || []
  maxPoolShifts.value = res.max_nobet || 1
}

// Personel Listesini Çek (Modal için)
async function ensurePersonnelList() {
  if (allPersonnel.value.length === 0) {
    try {
      const res = await nobetApi.personnelList()
      allPersonnel.value = res.personnel || []
    } catch {
      // Hata durumunda boş liste
    }
  }
}

// Sekme Değişimi
function switchTab(tab) {
  setNobetTab(tab)
  selectedIds.value.clear()
  selectionMode.value = false
  loadCurrentView()
}

// Arama Girişi (Debounce)
function handleSearchInput(e) {
  const val = e.target.value
  searchInput.value = val
  if (searchDebounceTimer) clearTimeout(searchDebounceTimer)
  searchDebounceTimer = setTimeout(() => {
    loadCurrentView()
  }, 350)
}

// Hızlı KPI Filtreleme
function filterByKpi(tip) {
  if (nobetState.activeTab !== 'bekleyen') {
    nobetState.activeTab = 'bekleyen'
  }
  if (tip === 'all') {
    currentDurum.value = 'bekleyen'
    currentTip.value = ''
  } else {
    currentDurum.value = 'bekleyen'
    currentTip.value = tip
  }
  loadShifts()
}

// Durum Filtresi Değişimi
function setDurumFilter(durum) {
  currentDurum.value = durum
  loadShifts()
}

// Tip Filtresi Değişimi
function setTipFilter(tip) {
  currentTip.value = tip
  loadShifts()
}

// Çoklu Seçim İşlemleri
function toggleSelection(id) {
  if (selectedIds.value.has(id)) {
    selectedIds.value.delete(id)
  } else {
    selectedIds.value.add(id)
  }
}

function selectAllShifts() {
  if (selectedIds.value.size === shifts.value.length) {
    selectedIds.value.clear()
  } else {
    shifts.value.forEach(s => selectedIds.value.add(s.id))
  }
}

// Tekil Onaylama
async function handleApprove(shift) {
  actionLoading.value = true
  try {
    await nobetApi.approve(shift.id, auth.state.csrf_token)
    showToast(`${shift.personel_name} için nöbet onaylandı.`)
    await loadCurrentView()
  } catch (err) {
    showToast(err.message || 'Onaylama başarısız.')
  } finally {
    actionLoading.value = false
  }
}

// Tekil Onay Kaldırma
async function handleUnapprove(shift) {
  actionLoading.value = true
  try {
    await nobetApi.unapprove(shift.id, auth.state.csrf_token)
    showToast('Nöbet onayı kaldırıldı.')
    await loadCurrentView()
  } catch (err) {
    showToast(err.message || 'İşlem başarısız.')
  } finally {
    actionLoading.value = false
  }
}

// Tekil Silme
function confirmDeleteShift(shift) {
  confirmData.value = {
    title: 'Nöbeti Sil',
    message: `${shift.personel_name} adına kayıtlı ${shift.tarih_formatli} tarihindeki nöbeti silmek istediğinize emin misiniz?`,
    type: 'danger',
    confirmText: 'Evet, Sil',
    action: async () => {
      actionLoading.value = true
      try {
        await nobetApi.delete(shift.id, auth.state.csrf_token)
        showToast('Nöbet kaydı silindi.')
        confirmModalOpen.value = false
        await loadCurrentView()
      } catch (err) {
        showToast(err.message || 'Silme başarısız.')
      } finally {
        actionLoading.value = false
      }
    },
  }
  confirmModalOpen.value = true
}

// Toplu Onayla
function confirmBulkApprove() {
  if (selectedIds.value.size === 0) return
  confirmData.value = {
    title: 'Toplu Nöbet Onayı',
    message: `Seçilen ${selectedIds.value.size} adet nöbeti onaylamak istediğinize emin misiniz?`,
    type: 'success',
    confirmText: 'Hepsini Onayla',
    action: async () => {
      actionLoading.value = true
      try {
        const ids = Array.from(selectedIds.value)
        await nobetApi.bulkApprove(ids, auth.state.csrf_token)
        showToast(`${ids.length} adet nöbet onaylandı.`)
        confirmModalOpen.value = false
        selectedIds.value.clear()
        selectionMode.value = false
        await loadCurrentView()
      } catch (err) {
        showToast(err.message || 'Toplu onaylama başarısız.')
      } finally {
        actionLoading.value = false
      }
    },
  }
  confirmModalOpen.value = true
}

// Toplu Sil
function confirmBulkDelete() {
  if (selectedIds.value.size === 0) return
  confirmData.value = {
    title: 'Toplu Nöbet Silme',
    message: `Seçilen ${selectedIds.value.size} adet nöbeti kalıcı olarak silmek istediğinize emin misiniz?`,
    type: 'danger',
    confirmText: 'Hepsini Sil',
    action: async () => {
      actionLoading.value = true
      try {
        const ids = Array.from(selectedIds.value)
        await nobetApi.bulkDelete(ids, auth.state.csrf_token)
        showToast(`${ids.length} adet nöbet silindi.`)
        confirmModalOpen.value = false
        selectedIds.value.clear()
        selectionMode.value = false
        await loadCurrentView()
      } catch (err) {
        showToast(err.message || 'Toplu silme başarısız.')
      } finally {
        actionLoading.value = false
      }
    },
  }
  confirmModalOpen.value = true
}

// Hatırlatma Gönder
async function handleSendReminder(shift) {
  actionLoading.value = true
  try {
    const res = await nobetApi.sendReminder({ nobet_id: shift.id }, auth.state.csrf_token)
    showToast(res.message || 'Hatırlatma iletildi.')
  } catch (err) {
    showToast(err.message || 'Hatırlatma gönderilemedi.')
  } finally {
    actionLoading.value = false
  }
}

// Planlama Modalını Aç
async function openPlanModal(defaultDate = null, defaultPersonel = null) {
  await ensurePersonnelList()
  const todayStr = new Date().toISOString().slice(0, 10)
  planForm.value = {
    personelId: defaultPersonel ? defaultPersonel.id : '',
    personelName: defaultPersonel ? defaultPersonel.name : '',
    nobetTarihi: defaultDate || todayStr,
    nobetTipi: 'standart',
    baslangicSaati: '18:00',
    bitisSaati: '08:00',
    aciklama: '',
    yoneticiOnayi: true,
  }
  planPersonnelSearch.value = defaultPersonel ? defaultPersonel.name : ''
  planModalOpen.value = true
}

// Personel Filtreleme (Modal içi)
const filteredPlanPersonnel = computed(() => {
  const q = planPersonnelSearch.value.toLowerCase().trim()
  if (!q) return allPersonnel.value
  return allPersonnel.value.filter(p =>
    p.name.toLowerCase().includes(q) || (p.department && p.department.toLowerCase().includes(q))
  )
})

function selectPersonelForPlan(p) {
  planForm.value.personelId = p.id
  planForm.value.personelName = p.name
  planPersonnelSearch.value = p.name
}

// Saat Preseti Seçimi
function setTimePreset(start, end, tip = null) {
  planForm.value.baslangicSaati = start
  planForm.value.bitisSaati = end
  if (tip) planForm.value.nobetTipi = tip
}

// Nöbet Kaydet
async function savePlan() {
  if (!planForm.value.personelId || !planForm.value.nobetTarihi) {
    showToast('Lütfen personel ve nöbet tarihini seçin.')
    return
  }
  actionLoading.value = true
  try {
    await nobetApi.add({
      personel_id: planForm.value.personelId,
      nobet_tarihi: planForm.value.nobetTarihi,
      nobet_tipi: planForm.value.nobetTipi,
      baslangic_saati: planForm.value.baslangicSaati,
      bitis_saati: planForm.value.bitisSaati,
      aciklama: planForm.value.aciklama,
      yonetici_onayi: planForm.value.yoneticiOnayi ? 1 : 0,
    }, auth.state.csrf_token)

    showToast('Nöbet başarıyla planlandı.')
    planModalOpen.value = false
    await loadCurrentView()
  } catch (err) {
    showToast(err.message || 'Nöbet kaydedilemedi.')
  } finally {
    actionLoading.value = false
  }
}

// Takvimde Seçili Günün Nöbetleri
const selectedDayShifts = computed(() => {
  if (!selectedCalendarDate.value || calendarDays.value.length === 0) return []
  const found = calendarDays.value.find(d => d.date === selectedCalendarDate.value)
  return found ? found.shifts : []
})

const selectedDayFormatted = computed(() => {
  if (!selectedCalendarDate.value) return ''
  const d = new Date(selectedCalendarDate.value)
  return new Intl.DateTimeFormat('tr-TR', { day: 'numeric', month: 'long', weekday: 'long' }).format(d)
})

onMounted(() => {
  loadCurrentView()
  ensurePersonnelList()
})
</script>

<template>
  <section class="ds-screen ds-nobet-page" :aria-busy="loading">
    <!-- Üst Bar -->
    <TopBar
      :eyebrow="`NÖBET YÖNETİMİ • ${nobetState.selectedYear}`"
      subtitle="Nöbet planlama, takvim ve yönetici onayları."
    />

    <!-- Ay ve Yıl Seçici & Aksiyon Barı -->
    <div class="nobet-header-ctrl bento-card bento-card--white">
      <div class="nobet-month-picker">
        <button
          type="button"
          class="clay-circle-btn"
          aria-label="Önceki Ay"
          @click="changeMonth(-1)"
        >
          <ChevronLeft :size="18" />
        </button>
        <div class="nobet-month-display">
          <CalendarDays :size="16" class="text-rose-500" />
          <strong>{{ currentMonthLabel }}</strong>
        </div>
        <button
          type="button"
          class="clay-circle-btn"
          aria-label="Sonraki Ay"
          @click="changeMonth(1)"
        >
          <ChevronRight :size="18" />
        </button>
      </div>

      <div class="nobet-header-actions">
        <button
          type="button"
          class="clay-pill-action clay-pill-action--primary"
          @click="openPlanModal()"
        >
          <Plus :size="16" />
          <span>Nöbet Planla</span>
        </button>
      </div>
    </div>

    <!-- 4 Minimal Bento KPI Kartı -->
    <div class="nobet-kpi-grid">
      <!-- 1. Toplam Bekleyen -->
      <button
        type="button"
        class="nobet-kpi-card nobet-kpi-card--amber"
        :class="{ 'is-active': nobetState.activeTab === 'bekleyen' && currentTip === '' }"
        @click="filterByKpi('all')"
      >
        <div class="nobet-kpi-top">
          <span class="nobet-kpi-label">BEKLEYENLER</span>
          <span class="nobet-kpi-icon"><Clock :size="16" /></span>
        </div>
        <div class="nobet-kpi-val">{{ kpiStats.total_bekleyen }}</div>
        <span class="nobet-kpi-sub">Onay bekleyen nöbet</span>
      </button>

      <!-- 2. Hafta Sonu -->
      <button
        type="button"
        class="nobet-kpi-card nobet-kpi-card--indigo"
        :class="{ 'is-active': nobetState.activeTab === 'bekleyen' && currentTip === 'hafta_sonu' }"
        @click="filterByKpi('hafta_sonu')"
      >
        <div class="nobet-kpi-top">
          <span class="nobet-kpi-label">H. SONU</span>
          <span class="nobet-kpi-icon"><Calendar :size="16" /></span>
        </div>
        <div class="nobet-kpi-val">{{ kpiStats.hafta_sonu_bekleyen }}</div>
        <span class="nobet-kpi-sub">Cumartesi - Pazar</span>
      </button>

      <!-- 3. Resmi Tatil -->
      <button
        type="button"
        class="nobet-kpi-card nobet-kpi-card--rose"
        :class="{ 'is-active': nobetState.activeTab === 'bekleyen' && currentTip === 'resmi_tatil' }"
        @click="filterByKpi('resmi_tatil')"
      >
        <div class="nobet-kpi-top">
          <span class="nobet-kpi-label">R. TATİL</span>
          <span class="nobet-kpi-icon"><Sparkles :size="16" /></span>
        </div>
        <div class="nobet-kpi-val">{{ kpiStats.resmi_tatil_bekleyen }}</div>
        <span class="nobet-kpi-sub">Bayram & Tatiller</span>
      </button>

      <!-- 4. Hafta İçi -->
      <button
        type="button"
        class="nobet-kpi-card nobet-kpi-card--blue"
        :class="{ 'is-active': nobetState.activeTab === 'bekleyen' && currentTip === 'hafta_ici' }"
        @click="filterByKpi('hafta_ici')"
      >
        <div class="nobet-kpi-top">
          <span class="nobet-kpi-label">H. İÇİ</span>
          <span class="nobet-kpi-icon"><Layers :size="16" /></span>
        </div>
        <div class="nobet-kpi-val">{{ kpiStats.hafta_ici_bekleyen }}</div>
        <span class="nobet-kpi-sub">Hafta içi nöbetleri</span>
      </button>
    </div>

    <!-- 3 Segmentli Navigasyon Barı -->
    <div class="nobet-tabs-wrap">
      <button
        type="button"
        class="nobet-tab-btn"
        :class="{ 'is-active': nobetState.activeTab === 'bekleyen' }"
        @click="switchTab('bekleyen')"
      >
        <CalendarCheck :size="16" />
        <span>Onay Listesi</span>
        <span v-if="kpiStats.total_bekleyen > 0" class="nobet-tab-badge">
          {{ kpiStats.total_bekleyen }}
        </span>
      </button>

      <button
        type="button"
        class="nobet-tab-btn"
        :class="{ 'is-active': nobetState.activeTab === 'planlama' }"
        @click="switchTab('planlama')"
      >
        <CalendarDays :size="16" />
        <span>Takvim</span>
      </button>

      <button
        type="button"
        class="nobet-tab-btn"
        :class="{ 'is-active': nobetState.activeTab === 'havuz' }"
        @click="switchTab('havuz')"
      >
        <Users :size="16" />
        <span>Havuz & Dağılım</span>
      </button>
    </div>

    <!-- TAB 1: ONAY LİSTESİ -->
    <section v-if="nobetState.activeTab === 'bekleyen'" class="nobet-tab-content">
      <!-- Filtreler ve Arama Çubuğu -->
      <div class="nobet-filter-row">
        <div class="nobet-search-box">
          <Search :size="16" class="nobet-search-icon" />
          <input
            type="text"
            :value="searchInput"
            placeholder="Personel veya departman ara..."
            class="nobet-search-input"
            @input="handleSearchInput"
          />
          <button
            v-if="searchInput"
            type="button"
            class="nobet-search-clear"
            @click="searchInput = ''; loadShifts()"
          >
            <X :size="14" />
          </button>
        </div>

        <button
          type="button"
          class="nobet-select-mode-btn"
          :class="{ 'is-active': selectionMode }"
          @click="selectionMode = !selectionMode; if (!selectionMode) selectedIds.clear()"
        >
          <CheckSquare :size="16" />
          <span>{{ selectionMode ? 'Vazgeç' : 'Çoklu Seç' }}</span>
        </button>
      </div>

      <!-- Durum Hapları: Bekleyen / Onaylanan / Tümü -->
      <div class="nobet-pills-bar">
        <button
          type="button"
          class="clay-filter-pill"
          :class="{ 'is-active': currentDurum === 'bekleyen' }"
          @click="setDurumFilter('bekleyen')"
        >
          Bekleyenler ({{ kpiStats.total_bekleyen }})
        </button>
        <button
          type="button"
          class="clay-filter-pill"
          :class="{ 'is-active': currentDurum === 'onaylanan' }"
          @click="setDurumFilter('onaylanan')"
        >
          Onaylananlar ({{ kpiStats.total_onaylanan }})
        </button>
        <button
          type="button"
          class="clay-filter-pill"
          :class="{ 'is-active': currentDurum === 'tumu' }"
          @click="setDurumFilter('tumu')"
        >
          Tümü ({{ kpiStats.total_nobet }})
        </button>
      </div>

      <!-- Çoklu Seçim Aksiyon Barı -->
      <div v-if="selectionMode" class="nobet-bulk-bar bento-card bento-card--white">
        <div class="nobet-bulk-info">
          <button type="button" class="nobet-bulk-select-all" @click="selectAllShifts">
            <component :is="selectedIds.size === shifts.length && shifts.length > 0 ? CheckSquare : Square" :size="18" />
            <span>{{ selectedIds.size === shifts.length && shifts.length > 0 ? 'Seçimi Kaldır' : 'Tümünü Seç' }}</span>
          </button>
          <strong>{{ selectedIds.size }} seçildi</strong>
        </div>
        <div class="nobet-bulk-buttons">
          <button
            type="button"
            class="clay-pill-btn clay-pill-btn--success"
            :disabled="selectedIds.size === 0 || actionLoading"
            @click="confirmBulkApprove"
          >
            <Check :size="16" />
            <span>Onayla</span>
          </button>
          <button
            type="button"
            class="clay-pill-btn clay-pill-btn--danger"
            :disabled="selectedIds.size === 0 || actionLoading"
            @click="confirmBulkDelete"
          >
            <Trash2 :size="16" />
            <span>Sil</span>
          </button>
        </div>
      </div>

      <!-- Yükleniyor / Boş Durum -->
      <div v-if="loading" class="clay-skeleton-grid">
        <div class="clay-skeleton-card clay-skeleton-card--md"></div>
        <div class="clay-skeleton-card clay-skeleton-card--md"></div>
      </div>

      <div v-else-if="shifts.length === 0" class="clay-state-card">
        <CalendarCheck :size="32" class="text-slate-400" />
        <strong>Kayıt Bulunamadı</strong>
        <p>Seçilen dönem ve filtrelere uygun nöbet kaydı bulunmuyor.</p>
        <button
          type="button"
          class="clay-pill-action clay-pill-action--primary mt-3"
          @click="openPlanModal()"
        >
          <Plus :size="16" />
          <span>Yeni Nöbet Planla</span>
        </button>
      </div>

      <!-- Nöbet Kartları Listesi -->
      <div v-else class="nobet-cards-stack">
        <article
          v-for="shift in shifts"
          :key="shift.id"
          class="nobet-card bento-card bento-card--white"
          :class="{ 'is-selected': selectedIds.has(shift.id) }"
        >
          <!-- Kart Üst Başlık -->
          <div class="nobet-card-head">
            <div class="nobet-card-user">
              <button
                v-if="selectionMode"
                type="button"
                class="nobet-card-chk"
                @click="toggleSelection(shift.id)"
              >
                <component :is="selectedIds.has(shift.id) ? CheckSquare : Square" :size="20" class="text-rose-500" />
              </button>
              <div v-if="shift.personel_avatar" class="nobet-avatar nobet-avatar--img">
                <img :src="shift.personel_avatar" :alt="shift.personel_name" @error="shift.personel_avatar = null" />
              </div>
              <div v-else class="nobet-avatar nobet-avatar--initials">
                {{ shift.personel_initials }}
              </div>
              <div class="nobet-user-meta">
                <strong class="nobet-user-name">{{ shift.personel_name }}</strong>
                <span class="nobet-user-dept">{{ shift.departman }}</span>
              </div>
            </div>

            <div class="nobet-card-badges">
              <span
                class="nobet-type-pill"
                :class="`nobet-type-pill--${shift.nobet_tipi}`"
              >
                {{ shift.nobet_tipi === 'hafta_sonu' ? 'H. Sonu' : (shift.nobet_tipi === 'resmi_tatil' ? 'R. Tatil' : 'H. İçi') }}
              </span>
              <span
                class="nobet-status-pill"
                :class="shift.yonetici_onayi ? 'is-approved' : 'is-pending'"
              >
                {{ shift.yonetici_onayi ? 'Onaylandı' : 'Bekliyor' }}
              </span>
            </div>
          </div>

          <!-- Tarih ve Saat Kutuları -->
          <div class="nobet-card-info-grid">
            <div class="nobet-info-box">
              <span class="nobet-info-box-label">TARİH & GÜN</span>
              <strong class="nobet-info-box-val">
                {{ shift.tarih_formatli }}
                <small>{{ shift.gun_adi }}</small>
              </strong>
            </div>
            <div class="nobet-info-box">
              <span class="nobet-info-box-label">NÖBET SAATLERİ</span>
              <strong class="nobet-info-box-val">
                {{ shift.baslangic_saati }} - {{ shift.bitis_saati }}
              </strong>
            </div>
          </div>

          <!-- Açıklama (Varsa) -->
          <div v-if="shift.aciklama" class="nobet-card-note">
            <p>{{ shift.aciklama }}</p>
          </div>

          <!-- Aksiyon Butonları -->
          <div class="nobet-card-actions">
            <!-- Onay Butonu (Onay Bekliyorsa) -->
            <button
              v-if="!shift.yonetici_onayi"
              type="button"
              class="nobet-btn nobet-btn--approve"
              :disabled="actionLoading"
              @click="handleApprove(shift)"
            >
              <Check :size="16" />
              <span>Onayla</span>
            </button>

            <!-- Onayı Kaldır (Onaylandıysa) -->
            <button
              v-else
              type="button"
              class="nobet-btn nobet-btn--unapprove"
              :disabled="actionLoading"
              @click="handleUnapprove(shift)"
            >
              <XCircle :size="16" />
              <span>Onayı Kaldır</span>
            </button>

            <!-- Hatırlatma Gönder -->
            <button
              type="button"
              class="nobet-btn nobet-btn--icon"
              title="Personele Bildirim Gönder"
              :disabled="actionLoading"
              @click="handleSendReminder(shift)"
            >
              <Bell :size="17" />
            </button>

            <!-- Sil Butonu -->
            <button
              type="button"
              class="nobet-btn nobet-btn--danger-icon"
              title="Nöbeti Sil"
              :disabled="actionLoading"
              @click="confirmDeleteShift(shift)"
            >
              <Trash2 :size="17" />
            </button>
          </div>
        </article>
      </div>
    </section>

    <!-- TAB 2: TAKVİM & PLANLAMA -->
    <section v-else-if="nobetState.activeTab === 'planlama'" class="nobet-tab-content">
      <!-- Mini Aylık Takvim Kartı -->
      <div class="bento-card bento-card--white nobet-cal-card">
        <div class="nobet-cal-weekdays">
          <span>Pzt</span>
          <span>Sal</span>
          <span>Çar</span>
          <span>Per</span>
          <span>Cum</span>
          <span class="is-weekend">Cmt</span>
          <span class="is-weekend">Paz</span>
        </div>

        <div class="nobet-cal-days-grid">
          <template v-for="cell in calendarCells" :key="cell.key">
            <span
              v-if="cell.isEmpty"
              class="nobet-cal-day-placeholder"
              aria-hidden="true"
            ></span>
            <button
              v-else
              type="button"
              class="nobet-cal-day-cell"
              :class="{
                'is-weekend': cell.is_weekend,
                'has-shifts': cell.duty_count > 0,
                'is-selected': selectedCalendarDate === cell.date
              }"
              @click="selectedCalendarDate = cell.date"
            >
              <span class="nobet-cal-day-num">{{ cell.day }}</span>
              <div v-if="cell.duty_count > 0" class="nobet-cal-dots">
                <span
                  v-for="(s, idx) in cell.shifts.slice(0, 3)"
                  :key="idx"
                  class="nobet-cal-dot"
                  :class="s.yonetici_onayi ? 'is-green' : 'is-amber'"
                ></span>
                <small v-if="cell.duty_count > 3">+{{ cell.duty_count - 3 }}</small>
              </div>
            </button>
          </template>
        </div>
      </div>

      <!-- Seçili Günün Detayları -->
      <div class="nobet-cal-detail-sec">
        <div class="nobet-cal-detail-head">
          <div>
            <span class="clay-page-eyebrow">SEÇİLİ GÜN</span>
            <h3 class="nobet-cal-detail-title">{{ selectedDayFormatted }}</h3>
          </div>
          <button
            type="button"
            class="clay-pill-action clay-pill-action--primary"
            @click="openPlanModal(selectedCalendarDate)"
          >
            <Plus :size="15" />
            <span>Nöbet Ekle</span>
          </button>
        </div>

        <!-- O günkü nöbetçiler -->
        <div v-if="selectedDayShifts.length === 0" class="clay-state-card clay-state-card--sm">
          <Calendar :size="24" class="text-slate-400" />
          <p>Bu tarihte planlanmış nöbet kaydı bulunmuyor.</p>
        </div>

        <div v-else class="nobet-cal-shifts-list">
          <div
            v-for="shift in selectedDayShifts"
            :key="shift.id"
            class="nobet-cal-shift-row bento-card bento-card--white"
          >
            <div class="nobet-avatar nobet-avatar--sm">
              <img v-if="shift.personel_avatar" :src="shift.personel_avatar" :alt="shift.personel_name" @error="shift.personel_avatar = null" />
              <span v-else>{{ shift.personel_initials }}</span>
            </div>

            <div class="nobet-cal-shift-info">
              <strong>{{ shift.personel_name }}</strong>
              <span>{{ shift.departman }} • {{ shift.baslangic_saati }} - {{ shift.bitis_saati }}</span>
            </div>

            <div class="nobet-cal-shift-actions">
              <span
                class="nobet-status-pill nobet-status-pill--sm"
                :class="shift.yonetici_onayi ? 'is-approved' : 'is-pending'"
              >
                {{ shift.yonetici_onayi ? 'Onaylı' : 'Bekliyor' }}
              </span>
              <button
                type="button"
                class="nobet-btn-mini-danger"
                title="Sil"
                @click="confirmDeleteShift({ id: shift.id, personel_name: shift.personel_name, tarih_formatli: selectedDayFormatted })"
              >
                <Trash2 :size="15" />
              </button>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- TAB 3: HAVUZ & AYLIK DAĞILIM -->
    <section v-else-if="nobetState.activeTab === 'havuz'" class="nobet-tab-content">
      <!-- Arama Kutusu -->
      <div class="nobet-search-box mb-3">
        <Search :size="16" class="nobet-search-icon" />
        <input
          type="text"
          :value="searchInput"
          placeholder="Personel ara..."
          class="nobet-search-input"
          @input="handleSearchInput"
        />
        <button
          v-if="searchInput"
          type="button"
          class="nobet-search-clear"
          @click="searchInput = ''; loadPersonnelSummary()"
        >
          <X :size="14" />
        </button>
      </div>

      <!-- Personel Dağılım Kartları -->
      <div v-if="loading" class="clay-skeleton-grid">
        <div class="clay-skeleton-card clay-skeleton-card--md"></div>
      </div>

      <div v-else-if="poolPersonnel.length === 0" class="clay-state-card">
        <Users :size="32" class="text-slate-400" />
        <strong>Personel Bulunamadı</strong>
        <p>Arama kriterinize uygun personel kaydı bulunamadı.</p>
      </div>

      <div v-else class="nobet-pool-stack">
        <article
          v-for="p in poolPersonnel"
          :key="p.id"
          class="nobet-pool-card bento-card bento-card--white"
          @click="openPlanModal(null, p)"
        >
          <div class="nobet-pool-left">
            <div class="nobet-avatar">
              <img v-if="p.avatar" :src="p.avatar" :alt="p.name" @error="p.avatar = null" />
              <span v-else>{{ p.initials }}</span>
            </div>
            <div class="nobet-pool-meta">
              <strong>{{ p.name }}</strong>
              <small>{{ p.department }}</small>
            </div>
          </div>

          <div class="nobet-pool-right">
            <div class="nobet-pool-counts">
              <span class="nobet-pool-count-badge">
                <strong>{{ p.nobet_sayisi }}</strong> Nöbet
              </span>
              <span v-if="p.bekleyen_sayisi > 0" class="nobet-pool-pending-badge">
                {{ p.bekleyen_sayisi }} onay bekliyor
              </span>
            </div>
            <!-- Dağılım Progress Barı -->
            <div class="nobet-pool-track">
              <div
                class="nobet-pool-fill"
                :style="{ width: `${Math.min(100, Math.round((p.nobet_sayisi / (maxPoolShifts || 1)) * 100))}%` }"
              ></div>
            </div>
          </div>
        </article>
      </div>
    </section>

    <!-- NÖBET PLANLAMA BOTTOM SHEET (MODAL) -->
    <Transition name="ds-modal">
      <div
        v-if="planModalOpen"
        class="nobet-sheet-overlay"
        @click.self="planModalOpen = false"
      >
        <div class="nobet-sheet bento-card bento-card--white">
          <div class="nobet-sheet-drag"></div>
          <div class="nobet-sheet-header">
            <div>
              <span class="clay-page-eyebrow">NÖBET PROGRAMI</span>
              <h2 class="nobet-sheet-title">Yeni Nöbet Planla</h2>
            </div>
            <button
              type="button"
              class="clay-circle-btn"
              aria-label="Kapat"
              @click="planModalOpen = false"
            >
              <X :size="18" />
            </button>
          </div>

          <div class="nobet-sheet-body">
            <!-- 1. Personel Seçimi -->
            <div class="nobet-field-group">
              <label class="nobet-field-label">PERSONEL SEÇİN</label>
              <div class="nobet-search-box mb-2">
                <Search :size="15" class="nobet-search-icon" />
                <input
                  v-model="planPersonnelSearch"
                  type="text"
                  placeholder="Personel ismiyle ara..."
                  class="nobet-search-input"
                />
              </div>

              <!-- Personel Seçim Listesi (Kompakt Kaydırma) -->
              <div class="nobet-plan-personnel-scroll">
                <button
                  v-for="p in filteredPlanPersonnel"
                  :key="p.id"
                  type="button"
                  class="nobet-plan-p-tile"
                  :class="{ 'is-selected': planForm.personelId === p.id }"
                  @click="selectPersonelForPlan(p)"
                >
                  <div class="nobet-avatar nobet-avatar--sm">
                    <img v-if="p.avatar" :src="p.avatar" :alt="p.name" @error="p.avatar = null" />
                    <span v-else>{{ p.initials }}</span>
                  </div>
                  <div class="nobet-plan-p-info">
                    <strong>{{ p.name }}</strong>
                    <small>{{ p.department }}</small>
                  </div>
                  <Check v-if="planForm.personelId === p.id" :size="18" class="text-rose-500" />
                </button>
              </div>
            </div>

            <!-- 2. Nöbet Tarihi -->
            <div class="nobet-field-group">
              <label class="nobet-field-label">NÖBET TARİHİ</label>
              <input
                v-model="planForm.nobetTarihi"
                type="date"
                class="nobet-text-input"
              />
            </div>

            <!-- 3. Nöbet Tipi -->
            <div class="nobet-field-group">
              <label class="nobet-field-label">NÖBET TİPİ</label>
              <div class="nobet-type-segmented">
                <button
                  type="button"
                  class="nobet-seg-btn"
                  :class="{ 'is-active': planForm.nobetTipi === 'standart' || planForm.nobetTipi === 'hafta_ici' }"
                  @click="planForm.nobetTipi = 'hafta_ici'"
                >
                  Hafta İçi
                </button>
                <button
                  type="button"
                  class="nobet-seg-btn"
                  :class="{ 'is-active': planForm.nobetTipi === 'hafta_sonu' }"
                  @click="planForm.nobetTipi = 'hafta_sonu'"
                >
                  Hafta Sonu
                </button>
                <button
                  type="button"
                  class="nobet-seg-btn"
                  :class="{ 'is-active': planForm.nobetTipi === 'resmi_tatil' }"
                  @click="planForm.nobetTipi = 'resmi_tatil'"
                >
                  Resmi Tatil
                </button>
              </div>
            </div>

            <!-- 4. Hızlı Saat Presetleri ve Girişi -->
            <div class="nobet-field-group">
              <label class="nobet-field-label">NÖBET SAATLERİ</label>
              <div class="nobet-preset-chips mb-2">
                <button
                  type="button"
                  class="nobet-chip"
                  @click="setTimePreset('18:00', '08:00')"
                >
                  18:00 - 08:00 (Gece)
                </button>
                <button
                  type="button"
                  class="nobet-chip"
                  @click="setTimePreset('08:00', '18:00')"
                >
                  08:00 - 18:00 (Gündüz)
                </button>
                <button
                  type="button"
                  class="nobet-chip"
                  @click="setTimePreset('08:00', '08:00')"
                >
                  24 Saat
                </button>
              </div>
              <div class="grid grid-cols-2 gap-2">
                <div>
                  <small class="text-slate-400 block mb-1">Başlangıç</small>
                  <input
                    v-model="planForm.baslangicSaati"
                    type="time"
                    class="nobet-text-input"
                  />
                </div>
                <div>
                  <small class="text-slate-400 block mb-1">Bitiş</small>
                  <input
                    v-model="planForm.bitisSaati"
                    type="time"
                    class="nobet-text-input"
                  />
                </div>
              </div>
            </div>

            <!-- 5. Açıklama -->
            <div class="nobet-field-group">
              <label class="nobet-field-label">NOT / AÇIKLAMA (OPSİYONEL)</label>
              <textarea
                v-model="planForm.aciklama"
                rows="2"
                placeholder="Nöbet hakkında özel talimat veya not..."
                class="nobet-text-input nobet-textarea"
              ></textarea>
            </div>

            <!-- 6. Yönetici Onayı Toggle -->
            <div class="nobet-toggle-row">
              <div>
                <strong>Doğrudan Onaylı Planla</strong>
                <p>Nöbeti yönetici onayından geçmiş olarak doğrudan onaylı kaydet.</p>
              </div>
              <label class="nobet-switch">
                <input v-model="planForm.yoneticiOnayi" type="checkbox" />
                <span class="nobet-slider"></span>
              </label>
            </div>
          </div>

          <div class="nobet-sheet-footer">
            <button
              type="button"
              class="clay-pill-btn clay-pill-btn--secondary"
              @click="planModalOpen = false"
            >
              Vazgeç
            </button>
            <button
              type="button"
              class="clay-pill-action clay-pill-action--primary flex-1"
              :disabled="actionLoading || !planForm.personelId"
              @click="savePlan"
            >
              <Check :size="16" />
              <span>Planlamayı Kaydet</span>
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- ONAY / SİLME MODALI -->
    <Transition name="ds-modal">
      <div
        v-if="confirmModalOpen"
        class="nobet-sheet-overlay"
        @click.self="confirmModalOpen = false"
      >
        <div class="nobet-confirm-dialog bento-card bento-card--white">
          <div
            class="nobet-confirm-icon"
            :class="`nobet-confirm-icon--${confirmData.type}`"
          >
            <AlertCircle :size="28" />
          </div>
          <h3 class="nobet-confirm-title">{{ confirmData.title }}</h3>
          <p class="nobet-confirm-msg">{{ confirmData.message }}</p>

          <div class="nobet-confirm-actions">
            <button
              type="button"
              class="clay-pill-btn clay-pill-btn--secondary"
              @click="confirmModalOpen = false"
            >
              Vazgeç
            </button>
            <button
              type="button"
              class="clay-pill-btn"
              :class="confirmData.type === 'danger' ? 'clay-pill-btn--danger' : 'clay-pill-btn--success'"
              :disabled="actionLoading"
              @click="confirmData.action?.()"
            >
              <span>{{ confirmData.confirmText }}</span>
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- TOAST BİLDİRİMİ -->
    <Transition name="ds-toast">
      <div v-if="toastMessage" class="nobet-toast">
        <span>{{ toastMessage }}</span>
      </div>
    </Transition>
  </section>
</template>

<style scoped>
.ds-nobet-page {
  padding-bottom: 90px;
}

/* Üst Kontrol & Ay Seçici */
.nobet-header-ctrl {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 16px;
  border-radius: var(--ds-radius-card);
  margin-bottom: 14px;
  box-shadow: var(--ds-shadow-bento);
}
.nobet-month-picker {
  display: flex;
  align-items: center;
  gap: 8px;
}
.nobet-month-display {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 14px;
  color: #1e293b;
}

/* 4 Minimal Bento KPI Kartı */
.nobet-kpi-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 10px;
  margin-bottom: 14px;
}
@media (min-width: 480px) {
  .nobet-kpi-grid {
    grid-template-columns: repeat(4, 1fr);
  }
}
.nobet-kpi-card {
  background: #ffffff;
  border: 1px solid rgba(255, 255, 255, 0.9);
  border-radius: 20px;
  padding: 12px 14px;
  display: flex;
  flex-direction: column;
  text-align: left;
  box-shadow: var(--ds-shadow-bento);
  transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
  cursor: pointer;
}
.nobet-kpi-card:active {
  transform: scale(0.97);
}
.nobet-kpi-card.is-active {
  border-color: #f43f5e;
  box-shadow: 0 8px 20px rgba(244, 63, 94, 0.18);
}
.nobet-kpi-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 4px;
}
.nobet-kpi-label {
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.05em;
  color: #64748b;
}
.nobet-kpi-icon {
  width: 26px;
  height: 26px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.nobet-kpi-card--amber .nobet-kpi-icon { background: #fef3c7; color: #d97706; }
.nobet-kpi-card--indigo .nobet-kpi-icon { background: #e0e7ff; color: #4f46e5; }
.nobet-kpi-card--rose .nobet-kpi-icon { background: #ffe4e6; color: #e11d48; }
.nobet-kpi-card--blue .nobet-kpi-icon { background: #e0f2fe; color: #0284c7; }

.nobet-kpi-val {
  font-size: 24px;
  font-weight: 900;
  line-height: 1.1;
  color: #0f172a;
  margin-bottom: 2px;
}
.nobet-kpi-sub {
  font-size: 10px;
  color: #94a3b8;
  font-weight: 600;
}

/* 3 Segmentli Tab Barı */
.nobet-tabs-wrap {
  display: flex;
  gap: 6px;
  padding: 4px;
  background: rgba(255, 255, 255, 0.7);
  backdrop-filter: blur(8px);
  border-radius: 16px;
  margin-bottom: 14px;
  border: 1px solid rgba(255, 255, 255, 0.8);
}
.nobet-tab-btn {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 10px 8px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 800;
  color: #64748b;
  border: none;
  background: transparent;
  transition: all 0.2s ease;
  cursor: pointer;
}
.nobet-tab-btn.is-active {
  background: #ffffff;
  color: #e11d48;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
}
.nobet-tab-badge {
  background: #f43f5e;
  color: #ffffff;
  font-size: 10px;
  padding: 1px 6px;
  border-radius: 10px;
  font-weight: 900;
}

/* Filtre & Arama Satırı */
.nobet-filter-row {
  display: flex;
  gap: 8px;
  margin-bottom: 10px;
}
.nobet-search-box {
  flex: 1;
  position: relative;
  display: flex;
  align-items: center;
}
.nobet-search-icon {
  position: absolute;
  left: 12px;
  color: #94a3b8;
  pointer-events: none;
}
.nobet-search-input {
  width: 100%;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  padding: 10px 34px 10px 36px;
  font-size: 13px;
  font-weight: 600;
  color: #1e293b;
  outline: none;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02);
}
.nobet-search-input:focus {
  border-color: #f43f5e;
}
.nobet-search-clear {
  position: absolute;
  right: 10px;
  background: #f1f5f9;
  border: none;
  border-radius: 50%;
  width: 20px;
  height: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #64748b;
  cursor: pointer;
}

.nobet-select-mode-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 0 14px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  font-size: 12px;
  font-weight: 700;
  color: #475569;
  cursor: pointer;
}
.nobet-select-mode-btn.is-active {
  background: #ffe4e6;
  border-color: #f43f5e;
  color: #e11d48;
}

/* Durum Hapları */
.nobet-pills-bar {
  display: flex;
  gap: 6px;
  overflow-x: auto;
  padding-bottom: 4px;
  margin-bottom: 12px;
  scrollbar-width: none;
}
.nobet-pills-bar::-webkit-scrollbar { display: none; }

/* Toplu Seçim Barı */
.nobet-bulk-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 14px;
  margin-bottom: 12px;
  background: #fff1f2;
  border: 1px solid #fecdd3;
  border-radius: 16px;
}
.nobet-bulk-info {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 12px;
}
.nobet-bulk-select-all {
  display: flex;
  align-items: center;
  gap: 4px;
  background: none;
  border: none;
  color: #be123c;
  font-weight: 800;
  cursor: pointer;
}
.nobet-bulk-buttons {
  display: flex;
  gap: 6px;
}

/* Nöbet Kartı */
.nobet-cards-stack {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.nobet-card {
  padding: 14px;
  border-radius: 20px;
  transition: transform 0.15s ease, border-color 0.15s ease;
}
.nobet-card.is-selected {
  border-color: #f43f5e;
  background: #fff1f2;
}
.nobet-card-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
}
.nobet-card-user {
  display: flex;
  align-items: center;
  gap: 10px;
}
.nobet-card-chk {
  background: none;
  border: none;
  padding: 0;
  cursor: pointer;
}
.nobet-avatar {
  width: 40px;
  height: 40px;
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 800;
  font-size: 13px;
  background: linear-gradient(135deg, #f43f5e, #fb7185);
  color: #ffffff;
  overflow: hidden;
  flex-shrink: 0;
  border: 2px solid #ffffff;
  box-shadow: 0 3px 8px rgba(244, 63, 94, 0.2);
}
.nobet-avatar--sm {
  width: 32px;
  height: 32px;
  border-radius: 10px;
  font-size: 11px;
}
.nobet-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.nobet-user-meta {
  display: flex;
  flex-direction: column;
}
.nobet-user-name {
  font-size: 14px;
  font-weight: 800;
  color: #0f172a;
}
.nobet-user-dept {
  font-size: 11px;
  font-weight: 600;
  color: #64748b;
  text-transform: uppercase;
}
.nobet-card-badges {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 4px;
}
.nobet-type-pill {
  font-size: 10px;
  font-weight: 800;
  padding: 2px 8px;
  border-radius: 8px;
  text-transform: uppercase;
}
.nobet-type-pill--hafta_sonu { background: #e0e7ff; color: #4338ca; }
.nobet-type-pill--resmi_tatil { background: #ffe4e6; color: #be123c; }
.nobet-type-pill--hafta_ici,
.nobet-type-pill--standart { background: #f1f5f9; color: #475569; }

.nobet-status-pill {
  font-size: 10px;
  font-weight: 800;
  padding: 2px 8px;
  border-radius: 8px;
}
.nobet-status-pill.is-approved { background: #dcfce7; color: #15803d; }
.nobet-status-pill.is-pending { background: #fef3c7; color: #b45309; }
.nobet-status-pill--sm { padding: 1px 6px; font-size: 9px; }

/* Bilgi Grid */
.nobet-card-info-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
  background: #f8fafc;
  padding: 10px;
  border-radius: 14px;
  margin-bottom: 10px;
}
.nobet-info-box {
  display: flex;
  flex-direction: column;
}
.nobet-info-box-label {
  font-size: 9px;
  font-weight: 800;
  color: #94a3b8;
  letter-spacing: 0.05em;
  margin-bottom: 2px;
}
.nobet-info-box-val {
  font-size: 12px;
  font-weight: 800;
  color: #1e293b;
}
.nobet-info-box-val small {
  font-size: 11px;
  font-weight: 600;
  color: #64748b;
  margin-left: 4px;
}

.nobet-card-note {
  background: #fffbeb;
  border: 1px dashed #fde68a;
  border-radius: 10px;
  padding: 8px 10px;
  font-size: 11px;
  color: #92400e;
  font-weight: 600;
  margin-bottom: 10px;
}

/* Kart Aksiyonları */
.nobet-card-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}
.nobet-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  height: 38px;
  padding: 0 16px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 800;
  border: none;
  cursor: pointer;
  transition: transform 0.1s ease, opacity 0.1s ease;
}
.nobet-btn:active {
  transform: scale(0.96);
}
.nobet-btn--approve {
  flex: 1;
  background: #10b981;
  color: #ffffff;
  box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);
}
.nobet-btn--unapprove {
  flex: 1;
  background: #f59e0b;
  color: #ffffff;
  box-shadow: 0 4px 12px rgba(245, 158, 11, 0.25);
}
.nobet-btn--icon {
  width: 38px;
  padding: 0;
  background: #f1f5f9;
  color: #475569;
}
.nobet-btn--danger-icon {
  width: 38px;
  padding: 0;
  background: #ffe4e6;
  color: #e11d48;
}

/* TAKVİM BİLEŞENİ */
.nobet-cal-card {
  padding: 14px;
  border-radius: 20px;
  margin-bottom: 16px;
}
.nobet-cal-weekdays {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  text-align: center;
  font-size: 11px;
  font-weight: 800;
  color: #94a3b8;
  margin-bottom: 8px;
}
.nobet-cal-weekdays .is-weekend {
  color: #f43f5e;
}
.nobet-cal-days-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 4px;
}
.nobet-cal-day-placeholder {
  min-height: 48px;
}
.nobet-cal-day-cell {
  background: #f8fafc;
  border: 1px solid #f1f5f9;
  border-radius: 12px;
  min-height: 48px;
  padding: 4px 2px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: flex-start;
  cursor: pointer;
  transition: all 0.15s ease;
}
.nobet-cal-day-cell.is-weekend {
  background: #fff1f2;
  border-color: #ffe4e6;
}
.nobet-cal-day-cell.is-selected {
  background: #f43f5e;
  border-color: #f43f5e;
  box-shadow: 0 4px 12px rgba(244, 63, 94, 0.3);
}
.nobet-cal-day-cell.is-selected .nobet-cal-day-num {
  color: #ffffff;
}
.nobet-cal-day-num {
  font-size: 12px;
  font-weight: 800;
  color: #334155;
  margin-bottom: 2px;
}
.nobet-cal-dots {
  display: flex;
  align-items: center;
  gap: 2px;
}
.nobet-cal-dot {
  width: 5px;
  height: 5px;
  border-radius: 50%;
}
.nobet-cal-dot.is-green { background: #10b981; }
.nobet-cal-dot.is-amber { background: #f59e0b; }
.nobet-cal-day-cell.is-selected .nobet-cal-dot { background: #ffffff; }

.nobet-cal-detail-sec {
  margin-top: 14px;
}
.nobet-cal-detail-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 10px;
}
.nobet-cal-detail-title {
  font-size: 15px;
  font-weight: 900;
  color: #0f172a;
}
.nobet-cal-shifts-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.nobet-cal-shift-row {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border-radius: 16px;
}
.nobet-cal-shift-info {
  flex: 1;
  display: flex;
  flex-direction: column;
}
.nobet-cal-shift-info strong {
  font-size: 13px;
  color: #1e293b;
}
.nobet-cal-shift-info span {
  font-size: 11px;
  color: #64748b;
  font-weight: 600;
}
.nobet-cal-shift-actions {
  display: flex;
  align-items: center;
  gap: 6px;
}
.nobet-btn-mini-danger {
  background: #fee2e2;
  color: #ef4444;
  border: none;
  width: 28px;
  height: 28px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

/* HAVUZ & DAĞILIM */
.nobet-pool-stack {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.nobet-pool-card {
  padding: 12px 14px;
  border-radius: 18px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  cursor: pointer;
  transition: transform 0.15s ease;
}
.nobet-pool-card:active {
  transform: scale(0.98);
}
.nobet-pool-left {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
}
.nobet-pool-meta {
  display: flex;
  flex-direction: column;
  min-width: 0;
}
.nobet-pool-meta strong {
  font-size: 13px;
  font-weight: 800;
  color: #1e293b;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.nobet-pool-meta small {
  font-size: 11px;
  color: #64748b;
  font-weight: 600;
}
.nobet-pool-right {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 4px;
  width: 110px;
  flex-shrink: 0;
}
.nobet-pool-counts {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
}
.nobet-pool-count-badge {
  font-size: 12px;
  font-weight: 800;
  color: #0f172a;
}
.nobet-pool-count-badge strong {
  font-size: 15px;
  color: #f43f5e;
}
.nobet-pool-pending-badge {
  font-size: 9px;
  font-weight: 700;
  color: #d97706;
}
.nobet-pool-track {
  width: 100%;
  height: 5px;
  background: #f1f5f9;
  border-radius: 4px;
  overflow: hidden;
}
.nobet-pool-fill {
  height: 100%;
  background: linear-gradient(90deg, #f43f5e, #fb7185);
  border-radius: 4px;
  transition: width 0.3s ease;
}

/* BOTTOM SHEET & MODAL */
.nobet-sheet-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.6);
  backdrop-filter: blur(4px);
  z-index: 100;
  display: flex;
  align-items: flex-end;
  justify-content: center;
}
.nobet-sheet {
  width: 100%;
  max-width: 500px;
  max-height: 90vh;
  border-radius: 28px 28px 0 0;
  padding: 16px 20px 24px 20px;
  display: flex;
  flex-direction: column;
  box-shadow: 0 -10px 40px rgba(0, 0, 0, 0.2);
}
.nobet-sheet-drag {
  width: 36px;
  height: 4px;
  background: #cbd5e1;
  border-radius: 2px;
  margin: 0 auto 12px auto;
}
.nobet-sheet-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 14px;
}
.nobet-sheet-title {
  font-size: 17px;
  font-weight: 900;
  color: #0f172a;
}
.nobet-sheet-body {
  overflow-y: auto;
  max-height: 60vh;
  padding-right: 2px;
  margin-bottom: 16px;
}
.nobet-field-group {
  margin-bottom: 14px;
}
.nobet-field-label {
  display: block;
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.05em;
  color: #64748b;
  margin-bottom: 6px;
}
.nobet-plan-personnel-scroll {
  max-height: 140px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 6px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  padding: 6px;
}
.nobet-plan-p-tile {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 6px 8px;
  border-radius: 10px;
  background: transparent;
  border: 1px solid transparent;
  cursor: pointer;
  text-align: left;
  transition: all 0.15s ease;
}
.nobet-plan-p-tile.is-selected {
  background: #ffffff;
  border-color: #f43f5e;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
}
.nobet-plan-p-info {
  flex: 1;
  display: flex;
  flex-direction: column;
}
.nobet-plan-p-info strong {
  font-size: 12px;
  color: #1e293b;
}
.nobet-plan-p-info small {
  font-size: 10px;
  color: #64748b;
}

.nobet-text-input {
  width: 100%;
  padding: 10px 14px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  font-size: 13px;
  font-weight: 700;
  color: #1e293b;
  outline: none;
}
.nobet-text-input:focus {
  border-color: #f43f5e;
  background: #ffffff;
}
.nobet-textarea {
  resize: none;
}

.nobet-type-segmented {
  display: flex;
  gap: 6px;
  background: #f1f5f9;
  padding: 3px;
  border-radius: 12px;
}
.nobet-seg-btn {
  flex: 1;
  padding: 8px 4px;
  border-radius: 9px;
  font-size: 11px;
  font-weight: 800;
  border: none;
  background: transparent;
  color: #64748b;
  cursor: pointer;
}
.nobet-seg-btn.is-active {
  background: #ffffff;
  color: #e11d48;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
}

.nobet-preset-chips {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}
.nobet-chip {
  padding: 5px 10px;
  background: #f1f5f9;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  font-size: 11px;
  font-weight: 700;
  color: #475569;
  cursor: pointer;
}
.nobet-chip:active {
  background: #e2e8f0;
}

.nobet-toggle-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px;
  background: #f8fafc;
  border-radius: 14px;
  margin-top: 6px;
}
.nobet-toggle-row strong {
  display: block;
  font-size: 12px;
  color: #1e293b;
}
.nobet-toggle-row p {
  font-size: 10px;
  color: #64748b;
  margin: 0;
}
.nobet-switch {
  position: relative;
  display: inline-block;
  width: 44px;
  height: 24px;
  flex-shrink: 0;
}
.nobet-switch input {
  opacity: 0;
  width: 0;
  height: 0;
}
.nobet-slider {
  position: absolute;
  cursor: pointer;
  inset: 0;
  background-color: #cbd5e1;
  transition: 0.3s;
  border-radius: 24px;
}
.nobet-slider:before {
  position: absolute;
  content: "";
  height: 18px;
  width: 18px;
  left: 3px;
  bottom: 3px;
  background-color: white;
  transition: 0.3s;
  border-radius: 50%;
}
.nobet-switch input:checked + .nobet-slider {
  background-color: #10b981;
}
.nobet-switch input:checked + .nobet-slider:before {
  transform: translateX(20px);
}

.nobet-sheet-footer {
  display: flex;
  align-items: center;
  gap: 10px;
}

/* ONAY DIALOG */
.nobet-confirm-dialog {
  width: 90%;
  max-width: 360px;
  padding: 24px 20px;
  border-radius: 24px;
  text-align: center;
  margin-bottom: auto;
  margin-top: auto;
}
.nobet-confirm-icon {
  width: 52px;
  height: 52px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 12px auto;
}
.nobet-confirm-icon--danger { background: #fee2e2; color: #ef4444; }
.nobet-confirm-icon--success { background: #dcfce7; color: #16a34a; }
.nobet-confirm-icon--warning { background: #fef3c7; color: #d97706; }
.nobet-confirm-title {
  font-size: 16px;
  font-weight: 900;
  color: #0f172a;
  margin-bottom: 6px;
}
.nobet-confirm-msg {
  font-size: 12px;
  color: #64748b;
  margin-bottom: 18px;
  line-height: 1.4;
}
.nobet-confirm-actions {
  display: flex;
  gap: 8px;
  justify-content: center;
}

/* TOAST */
.nobet-toast {
  position: fixed;
  bottom: 80px;
  left: 50%;
  transform: translateX(-50%);
  background: #0f172a;
  color: #ffffff;
  padding: 10px 18px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 700;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.25);
  z-index: 200;
  white-space: nowrap;
}

.clay-pill-btn--secondary {
  background: #f1f5f9;
  color: #475569;
}
</style>
