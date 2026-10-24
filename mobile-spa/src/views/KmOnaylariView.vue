<script setup>
import { computed, nextTick, onMounted, onBeforeUnmount, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import {
  AlertCircle,
  Bell,
  Calendar,
  Car,
  Check,
  CheckCheck,
  CheckSquare,
  ChevronLeft,
  ChevronRight,
  Clock,
  Edit3,
  Gauge,
  Info,
  Maximize2,
  Moon,
  RefreshCw,
  Search,
  Send,
  Sparkles,
  Square,
  Sun,
  User,
  X,
} from '@lucide/vue'
import TopBar from '../components/TopBar.vue'
import { auth } from '../services/auth.js'
import {
  kmApi,
  kmState,
  setKmTab,
  openKmDrawer,
  closeKmDrawer,
} from '../services/km-onaylari.js'

const router = useRouter()

// UI Durumları
const loading = ref(false)
const actionLoading = ref(false)
const errorMessage = ref('')
const toastMessage = ref('')
let toastTimer = null

// Veri listesi ve sayfalama
const reports = ref([])
const pagination = ref({
  page: 1,
  perPage: 15,
  total: 0,
  totalPages: 1,
})
const canEdit = ref(false)

// Filtreler & Arama
const searchInput = ref('')
let searchDebounceTimer = null
const currentTurFilter = ref('') // '' | 'sabah' | 'aksam'

// Çoklu Seçim Modu
const selectionMode = ref(false)
const selectedIds = ref(new Set())

// Resim Lightbox Modalı
const lightboxOpen = ref(false)
const lightboxData = ref({
  imageUrl: '',
  plaka: '',
  km: '',
  personel: '',
  tur: '',
  tarih: '',
})
const lightboxZoomed = ref(false)

// Düzelt ve Onayla Modalı
const editModalOpen = ref(false)
const editData = ref({
  id: 0,
  plaka: '',
  personel: '',
  tur: '',
  tarih: '',
  originalKm: 0,
  newKm: 0,
  aciklama: '',
  sabahKm: null,
})

// Reddet Modalı
const rejectModalOpen = ref(false)
const rejectData = ref({
  id: 0,
  plaka: '',
  personel: '',
  km: 0,
  redNedeni: '',
})

// AI Sonuç Rapor Modalı
const aiResultModalOpen = ref(false)
const aiResultData = ref(null)
const aiReasonModalOpen = ref(false)
const aiReasonData = ref({ plaka: '', reason: '' })

function openAiReason(report) {
  aiReasonData.value = {
    plaka: report?.plaka || 'KM Bildirimi',
    reason: report?.ai_onaylanmama_nedeni || 'Ayrıntılı gerekçe bulunamadı.',
  }
  aiReasonModalOpen.value = true
}

// Toast Bildirimi
function showToast(msg) {
  toastMessage.value = msg
  if (toastTimer) clearTimeout(toastTimer)
  toastTimer = setTimeout(() => {
    toastMessage.value = ''
  }, 3500)
}

// Yardımcı Biçimlendirme Fonksiyonları
function formatNumber(num) {
  if (num === null || num === undefined || isNaN(num)) return '0'
  return Number(num).toLocaleString('tr-TR')
}

function formatDate(dateStr) {
  if (!dateStr) return ''
  const parts = String(dateStr).split(' ')[0].split('-')
  if (parts.length === 3) {
    return `${parts[2]}.${parts[1]}.${parts[0]}`
  }
  return dateStr
}

function formatTime(dateTimeStr) {
  if (!dateTimeStr) return ''
  const parts = String(dateTimeStr).split(' ')
  if (parts.length > 1) {
    return parts[1].substring(0, 5)
  }
  return ''
}

function resolveImageUrl(path) {
  if (!path) return ''
  if (path.startsWith('http')) return path
  if (path.startsWith('/api/')) return path
  if (path.startsWith('uploads/km_bildirim') || path.includes('km_bildirim')) {
    return `/api/km-onaylari.php?action=foto&file=${encodeURIComponent(path)}`
  }
  if (path.startsWith('/')) return path
  return `/${path}`
}

function getKmPhotoUrl(report) {
  if (!report) return ''
  if (report.resim_url) return report.resim_url
  if (report.id) return kmApi.getPhotoUrl(report.id)
  if (report.resim_yolu) return resolveImageUrl(report.resim_yolu)
  return ''
}

function getInitials(name) {
  if (!name) return '?'
  const parts = name.trim().split(/\s+/)
  if (parts.length >= 2) {
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
  }
  return name.slice(0, 2).toUpperCase()
}

// Sekme Başlık ve Alt Başlıkları
const tabMeta = {
  pending: {
    title: 'Bekleyen Bildirimler',
    subtitle: 'Onay bekleyen araç kilometre bildirimleri',
    emptyTitle: 'Bekleyen Bildirim Yok',
    emptyDesc: 'Şu anda onayınızı bekleyen herhangi bir kilometre bildirimi bulunmuyor.',
  },
  unreported: {
    title: 'Bildirim Yapmayanlar',
    subtitle: 'Bugün zimmetli aracına KM bildirimi girmeyen personeller',
    emptyTitle: 'Herkes Bildirim Yaptı',
    emptyDesc: 'Bugün zimmetli aracı bulunan tüm personeller bildirimlerini tamamlamış.',
  },
  approved: {
    title: 'Onaylanan KM Bildirimleri',
    subtitle: 'Yönetici veya sistem tarafından onaylanmış kayıtlar',
    emptyTitle: 'Kayıt Bulunamadı',
    emptyDesc: 'Seçili kriterlerde onaylanmış bildirim kaydı bulunamadı.',
  },
  rejected: {
    title: 'Reddedilen Bildirimler',
    subtitle: 'Uygun görülmeyerek reddedilen bildirimler',
    emptyTitle: 'Reddedilen Kayıt Yok',
    emptyDesc: 'Seçili kriterlerde reddedilmiş bildirim kaydı bulunamadı.',
  },
}

// Aktif Sekme Başlığı & Sayacı
const activeTabTitle = computed(() => {
  switch (kmState.activeTab) {
    case 'pending': return 'ONAY BEKLEYENLER'
    case 'unreported': return 'BİLDİRİM YAPMAYANLAR'
    case 'approved': return 'ONAYLANANLAR'
    case 'rejected': return 'REDDEDİLENLER'
    default: return 'KM TAKİBİ'
  }
})

const currentTabCount = computed(() => {
  switch (kmState.activeTab) {
    case 'pending': return kmState.counts.pending || 0
    case 'unreported': return kmState.counts.unreported || 0
    case 'approved': return kmState.counts.approved || 0
    case 'rejected': return kmState.counts.rejected || 0
    default: return 0
  }
})

function toggleTurFilter() {
  if (currentTurFilter.value === 'sabah') {
    currentTurFilter.value = 'aksam'
  } else if (currentTurFilter.value === 'aksam') {
    currentTurFilter.value = 'sabah'
  } else {
    currentTurFilter.value = 'sabah'
  }
  pagination.value.page = 1
  loadReports(1)
}

// Segment Çubuğu Oranları
const segmentRatio = computed(() => {
  const c = kmState.counts
  const total = Number(c.pending || 0) + Number(c.approved || 0) + Number(c.unreported || 0) + Number(c.rejected || 0)
  if (total <= 0) {
    return { pending: '25%', approved: '25%', unreported: '25%', rejected: '25%' }
  }
  return {
    pending: `${Math.max(4, Math.round((c.pending / total) * 100))}%`,
    approved: `${Math.max(4, Math.round((c.approved / total) * 100))}%`,
    unreported: `${Math.max(4, Math.round((c.unreported / total) * 100))}%`,
    rejected: `${Math.max(4, Math.round((c.rejected / total) * 100))}%`,
  }
})

// Tümünü Seç Durumu
const isAllSelected = computed(() => {
  if (reports.value.length === 0) return false
  return reports.value.every((r) => selectedIds.value.has(r.id))
})

// Verileri Yükle
async function loadReports(page = 1) {
  loading.value = true
  errorMessage.value = ''
  selectedIds.value.clear()

  try {
    const res = await kmApi.list({
      status: kmState.activeTab,
      page,
      perPage: pagination.value.perPage,
      search: searchInput.value.trim(),
      tur: currentTurFilter.value,
    })

    if (res.success) {
      reports.value = res.reports || []
      pagination.value = res.pagination || {
        page: 1,
        perPage: 15,
        total: 0,
        totalPages: 1,
      }
      canEdit.value = Boolean(res.can_edit)

      if (res.counts) {
        kmState.counts.pending = Number(res.counts.pending || 0)
        kmState.counts.unreported = Number(res.counts.unreported || 0)
        kmState.counts.approved = Number(res.counts.approved || 0)
        kmState.counts.rejected = Number(res.counts.rejected || 0)
      }
    }
  } catch (err) {
    errorMessage.value = err.message || 'Veriler yüklenirken bir hata oluştu.'
  } finally {
    loading.value = false
  }
}

// Sekme Değişimi
function switchTab(tabKey) {
  if (kmState.activeTab === tabKey) return
  setKmTab(tabKey)
  selectionMode.value = false
  selectedIds.value.clear()
  currentTurFilter.value = ''
  pagination.value.page = 1
  loadReports(1)
}

// Arama Girişi Debounce
function handleSearchInput() {
  if (searchDebounceTimer) clearTimeout(searchDebounceTimer)
  searchDebounceTimer = setTimeout(() => {
    pagination.value.page = 1
    loadReports(1)
  }, 350)
}

function clearSearch() {
  searchInput.value = ''
  pagination.value.page = 1
  loadReports(1)
}

// Tür Filtresi Değişimi
function setTurFilter(tur) {
  currentTurFilter.value = tur
  pagination.value.page = 1
  loadReports(1)
}

// Çoklu Seçim
function toggleSelectionMode() {
  selectionMode.value = !selectionMode.value
  if (!selectionMode.value) {
    selectedIds.value.clear()
  }
}

function toggleSelectReport(id) {
  if (selectedIds.value.has(id)) {
    selectedIds.value.delete(id)
  } else {
    selectedIds.value.add(id)
  }
}

function toggleSelectAll() {
  if (isAllSelected.value) {
    selectedIds.value.clear()
  } else {
    reports.value.forEach((r) => selectedIds.value.add(r.id))
  }
}

// ==========================================
// AKSİYONLAR: ONAY, DÜZELT, RED, AI, HATIRLAT
// ==========================================

// 1. Tekil Onayla
async function handleApprove(report) {
  if (!confirm(`${report.plaka} plakalı aracın ${formatNumber(report.bitis_km)} KM bildirimini onaylamak istiyor musunuz?`)) {
    return
  }

  actionLoading.value = true
  try {
    const res = await kmApi.approve(report.id, auth.state.csrfToken || auth.state.csrf_token)
    showToast(res.message || 'KM bildirimi başarıyla onaylandı.')
    await loadReports(pagination.value.page)
  } catch (err) {
    alert(err.message || 'Onaylama sırasında bir hata oluştu.')
  } finally {
    actionLoading.value = false
  }
}

// 2. Düzelt ve Onayla Modalı Aç
function openEditModal(report) {
  editData.value = {
    id: report.id,
    plaka: report.plaka,
    personel: report.personel_adi,
    tur: report.tur === 'sabah' ? 'Sabah Bildirimi' : 'Akşam Bildirimi',
    tarih: formatDate(report.tarih),
    originalKm: Number(report.bitis_km),
    newKm: Number(report.bitis_km),
    aciklama: '',
    sabahKm: report.sabah_km ? Number(report.sabah_km) : null,
  }
  editModalOpen.value = true
}

function adjustNewKm(delta) {
  editData.value.newKm = Math.max(0, Number(editData.value.newKm || 0) + delta)
}

function resetNewKm() {
  editData.value.newKm = editData.value.originalKm
}

async function submitEditApprove() {
  if (editData.value.newKm <= 0) {
    alert('Lütfen geçerli bir KM değeri giriniz.')
    return
  }

  actionLoading.value = true
  try {
    const res = await kmApi.editApprove(
      editData.value.id,
      editData.value.newKm,
      editData.value.aciklama,
      auth.state.csrfToken || auth.state.csrf_token
    )
    editModalOpen.value = false
    showToast(res.message || 'KM düzeltilerek onaylandı.')
    await loadReports(pagination.value.page)
  } catch (err) {
    alert(err.message || 'İşlem gerçekleştirilemedi.')
  } finally {
    actionLoading.value = false
  }
}

// 3. Reddet Modalı Aç
function openRejectModal(report) {
  rejectData.value = {
    id: report.id,
    plaka: report.plaka,
    personel: report.personel_adi,
    km: Number(report.bitis_km),
    redNedeni: '',
  }
  rejectModalOpen.value = true
}

function setRejectReasonTemplate(templateText) {
  rejectData.value.redNedeni = templateText
}

async function submitReject() {
  actionLoading.value = true
  try {
    const res = await kmApi.reject(
      rejectData.value.id,
      rejectData.value.redNedeni,
      auth.state.csrfToken || auth.state.csrf_token
    )
    rejectModalOpen.value = false
    showToast(res.message || 'KM bildirimi reddedildi.')
    await loadReports(pagination.value.page)
  } catch (err) {
    alert(err.message || 'Reddetme sırasında hata oluştu.')
  } finally {
    actionLoading.value = false
  }
}

// 4. Toplu Onayla
async function handleBulkApprove() {
  const ids = Array.from(selectedIds.value)
  if (ids.length === 0) return

  if (!confirm(`Seçilen ${ids.length} adet bildirimi onaylamak istiyor musunuz?`)) {
    return
  }

  actionLoading.value = true
  try {
    const res = await kmApi.bulkApprove(ids, auth.state.csrfToken || auth.state.csrf_token)
    showToast(res.message || 'Seçilen bildirimler onaylandı.')
    selectionMode.value = false
    selectedIds.value.clear()
    await loadReports(pagination.value.page)
  } catch (err) {
    alert(err.message || 'Toplu onay sırasında hata oluştu.')
  } finally {
    actionLoading.value = false
  }
}

// 5. Yapay Zeka ile Kontrol Et & Onayla
async function handleAiCheck(targetIds = null) {
  let ids = []
  if (Array.isArray(targetIds)) {
    ids = targetIds
  } else if (selectedIds.value.size > 0) {
    ids = Array.from(selectedIds.value)
  } else {
    // Seçim yoksa sayfadaki bekleyen ve resmi olan kayıtları al (maksimum 15)
    ids = reports.value
      .filter((r) => r.resim_yolu && r.durum === 'beklemede')
      .map((r) => r.id)
      .slice(0, 15)
  }

  if (ids.length === 0) {
    alert('Fotoğrafı bulunan ve bekleyen bildirim bulunamadı.')
    return
  }

  actionLoading.value = true
  try {
    const res = await kmApi.aiCheck(ids, auth.state.csrfToken || auth.state.csrf_token)
    aiResultData.value = res.data || null
    aiResultModalOpen.value = true
    showToast(res.message || 'Yapay zeka analizi tamamlandı.')
    selectionMode.value = false
    selectedIds.value.clear()
    await loadReports(pagination.value.page)
  } catch (err) {
    alert(err.message || 'Yapay zekâ kontrolü sırasında hata oluştu.')
  } finally {
    actionLoading.value = false
  }
}

// 6. Tekil Hatırlatma Gönder (Bildirim Yapmayanlara)
async function handleSendReminder(item) {
  actionLoading.value = true
  try {
    const res = await kmApi.sendReminder(
      item.personel_id,
      item.tarih || new Date().toISOString().slice(0, 10),
      item.hedef_tur || 'sabah',
      auth.state.csrfToken || auth.state.csrf_token
    )
    showToast(`${item.personel_adi} personeline hatırlatma iletildi.`)
  } catch (err) {
    alert(err.message || 'Hatırlatma gönderilemedi.')
  } finally {
    actionLoading.value = false
  }
}

// 7. Toplu Hatırlatma Gönder
async function handleBulkReminder() {
  if (!confirm('Bildirim yapmayan tüm personellere hatırlatma bildirimi gönderilsin mi?')) {
    return
  }

  actionLoading.value = true
  try {
    const res = await kmApi.bulkReminder(
      new Date().toISOString().slice(0, 10),
      'sabah',
      auth.state.csrfToken || auth.state.csrf_token
    )
    showToast(res.message || 'Hatırlatmalar gönderildi.')
  } catch (err) {
    alert(err.message || 'Toplu hatırlatma sırasında hata oluştu.')
  } finally {
    actionLoading.value = false
  }
}

// 8. Resim Lightbox Aç
function openLightbox(report) {
  const imgUrl = getKmPhotoUrl(report)
  if (!imgUrl) return
  lightboxData.value = {
    imageUrl: imgUrl,
    plaka: report.plaka,
    km: formatNumber(report.bitis_km),
    personel: report.personel_adi,
    tur: report.tur === 'sabah' ? 'Sabah Bildirimi' : 'Akşam Bildirimi',
    tarih: formatDate(report.tarih),
  }
  lightboxZoomed.value = false
  lightboxOpen.value = true
}

function toggleLightboxZoom() {
  lightboxZoomed.value = !lightboxZoomed.value
}

// Sayfalama
function goToPage(p) {
  if (p < 1 || p > pagination.value.totalPages || p === pagination.value.page) return
  loadReports(p)
}

watch(() => kmState.activeTab, (newTab) => {
  selectionMode.value = false
  selectedIds.value.clear()
  currentTurFilter.value = ''
  pagination.value.page = 1
  loadReports(1)
})

onMounted(() => {
  openKmDrawer()
  loadReports(1)
})

onBeforeUnmount(() => {
  closeKmDrawer()
})
</script>

<template>
  <section class="ds-screen ds-km-screen" :aria-busy="loading || actionLoading">
    <!-- Üst Başlık Barı -->
    <TopBar
      :title="tabMeta[kmState.activeTab]?.title || 'KM Onayları'"
      :subtitle="tabMeta[kmState.activeTab]?.subtitle"
    >
      <template #actions>
        <button
          v-if="kmState.activeTab === 'pending'"
          type="button"
          class="clay-circle-btn"
          :class="{ 'clay-circle-btn--active': selectionMode }"
          :aria-label="selectionMode ? 'Seçim modunu kapat' : 'Çoklu seçim yap'"
          :title="selectionMode ? 'Seçimi Kapat' : 'Çoklu Seçim'"
          @click="toggleSelectionMode"
        >
          <CheckSquare v-if="!selectionMode" :size="18" />
          <X v-else :size="18" />
        </button>
        <button
          type="button"
          class="clay-circle-btn"
          :disabled="loading"
          aria-label="Listeyi yenile"
          title="Yenile"
          @click="loadReports(pagination.page)"
        >
          <RefreshCw :size="18" :class="{ 'spin-anim': loading }" />
        </button>
      </template>
    </TopBar>

    <!-- Toast Bildirimi -->
    <Transition name="ds-toast">
      <div v-if="toastMessage" class="ds-toast-banner" role="status">
        <Check :size="16" class="ds-toast-icon" />
        <span>{{ toastMessage }}</span>
      </div>
    </Transition>

    <!-- 1. MİNİMAL BENTO ÖZET KARTI (OBSIDIAN DARK STRIP & DAY/NIGHT TOGGLE) -->
    <section class="bento-card bento-card--dark km-minimal-summary-strip">
      <!-- Sol Taraf: Durum Başlığı ve Anlık Sayaç -->
      <div class="km-mini-summary-info">
        <div class="km-mini-tag">
          <span class="bento-status-dot"></span>
          <span class="km-mini-tag-text">{{ activeTabTitle }}</span>
        </div>
        <div class="km-mini-counter-row">
          <strong class="km-mini-count">{{ formatNumber(currentTabCount) }}</strong>
          <span class="km-mini-label">kayıt</span>
        </div>
        <!-- İnce Segment Dağılım Çizgisi -->
        <div class="segmented-track segmented-track--dark km-mini-track" role="img" title="Genel Durum Oranları">
          <span class="seg-pill seg-pill--blue" :style="{ width: segmentRatio.pending }"></span>
          <span class="seg-pill seg-pill--amber" :style="{ width: segmentRatio.unreported }"></span>
          <span class="seg-pill seg-pill--emerald" :style="{ width: segmentRatio.approved }"></span>
          <span class="seg-pill seg-pill--coral" :style="{ width: segmentRatio.rejected }"></span>
        </div>
      </div>

      <!-- Sağ Taraf: Sabah ☀️ / Akşam 🌙 Day-Night Toggle Switch (2. Resimdeki Tasarım) -->
      <div v-if="kmState.activeTab !== 'unreported'" class="km-mini-toggle-wrap">
        <div class="toggle-control-box">
          <!-- Tümü Filtre Hapı -->
          <button
            type="button"
            class="toggle-all-chip"
            :class="{ 'is-active': currentTurFilter === '' }"
            title="Sabah ve Akşam Tümü"
            @click="setTurFilter('')"
          >
            Tümü
          </button>

          <!-- Day/Night Toggle Switch (2. Resimdeki Güneş / Hilal Switch) -->
          <button
            type="button"
            class="day-night-toggle"
            :class="[
              currentTurFilter === 'aksam' ? 'is-night' : 'is-day',
              { 'is-all': currentTurFilter === '' }
            ]"
            :title="currentTurFilter === 'aksam' ? 'Akşam Aktif (Sabah için tıkla)' : 'Sabah Aktif (Akşam için tıkla)'"
            :aria-label="currentTurFilter === 'aksam' ? 'Akşam bildirimleri modu' : 'Sabah bildirimleri modu'"
            @click="toggleTurFilter"
          >
            <!-- Gece İkonu (Hilal ve Yıldızlar) -->
            <div class="toggle-night-icon" aria-hidden="true">
              <svg class="night-moon-svg" viewBox="0 0 24 24" fill="none">
                <path d="M12 3a9 9 0 1 0 9 9c0-.46-.04-.92-.1-1.36a5.5 5.5 0 0 1-7.54-7.54C13.08 3.04 12.54 3 12 3z" fill="#ffffff" />
                <circle cx="19" cy="4" r="1.1" fill="#ffffff" />
                <circle cx="21" cy="8.5" r="0.9" fill="#ffffff" />
                <circle cx="16.5" cy="11" r="0.8" fill="#ffffff" />
              </svg>
            </div>

            <!-- Kayan Yuvarlak Düğme (Knob) -->
            <div class="toggle-sliding-knob">
              <span class="knob-dot"></span>
            </div>

            <!-- Gündüz İkonu (Güneş Işınları) -->
            <div class="toggle-day-icon" aria-hidden="true">
              <svg class="day-sun-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round">
                <circle cx="12" cy="12" r="4.5" fill="#1e293b" />
                <line x1="12" y1="1.5" x2="12" y2="3.5" stroke="#1e293b" />
                <line x1="12" y1="20.5" x2="12" y2="22.5" stroke="#1e293b" />
                <line x1="4.22" y1="4.22" x2="5.64" y2="5.64" stroke="#1e293b" />
                <line x1="18.36" y1="18.36" x2="19.78" y2="19.78" stroke="#1e293b" />
                <line x1="1.5" y1="12" x2="3.5" y2="12" stroke="#1e293b" />
                <line x1="20.5" y1="12" x2="22.5" y2="12" stroke="#1e293b" />
                <line x1="4.22" y1="19.78" x2="5.64" y2="18.36" stroke="#1e293b" />
                <line x1="18.36" y1="5.64" x2="19.78" y2="4.22" stroke="#1e293b" />
              </svg>
            </div>
          </button>
        </div>

        <span class="toggle-state-text">
          {{ currentTurFilter === 'aksam' ? 'Akşam Bildirimleri' : currentTurFilter === 'sabah' ? 'Sabah Bildirimleri' : 'Tüm Bildirimler' }}
        </span>
      </div>
    </section>

    <!-- Alt Menü Kapalıyken Yüzen Açma Butonu -->
    <Transition name="ds-fade">
      <button
        v-if="!kmState.drawerOpen"
        type="button"
        class="floating-km-dock-btn"
        title="KM Menüsünü Aç"
        aria-label="KM Menüsünü Aç"
        @click="openKmDrawer"
      >
        <Gauge :size="17" />
        <span>Menü</span>
      </button>
    </Transition>

    <!-- 3. ARAMA VE FİLTRELEME BARI -->
    <div class="km-filter-bar">
      <div class="clay-search-box">
        <Search :size="18" class="clay-search-icon" />
        <input
          v-model="searchInput"
          type="text"
          class="clay-search-input"
          placeholder="Personel veya plaka ile ara..."
          @input="handleSearchInput"
        />
        <button
          v-if="searchInput"
          type="button"
          class="clay-search-clear"
          aria-label="Aramayı temizle"
          @click="clearSearch"
        >
          <X :size="16" />
        </button>
      </div>



      <!-- Yapmayanlar sekmesinde: Toplu Hatırlatma Butonu -->
      <div v-if="kmState.activeTab === 'unreported' && reports.length > 0" class="unreported-actions-bar">
        <button
          type="button"
          class="btn-bulk-remind"
          :disabled="actionLoading"
          @click="handleBulkReminder"
        >
          <Send :size="16" />
          <span>Tümüne Hatırlatma Gönder ({{ reports.length }})</span>
        </button>
      </div>
    </div>

    <!-- 4. ÇOKLU SEÇİM EYLEM BARI (Floating Multi-Action Bar) -->
    <Transition name="ds-slide-up">
      <div v-if="selectionMode && kmState.activeTab === 'pending'" class="km-bulk-action-bar">
        <div class="bulk-info">
          <button type="button" class="btn-check-all" @click="toggleSelectAll">
            <CheckSquare v-if="isAllSelected" :size="18" class="text-blue" />
            <Square v-else :size="18" />
            <span>{{ isAllSelected ? 'Seçimi Kaldır' : 'Tümünü Seç' }}</span>
          </button>
          <span class="selected-badge">{{ selectedIds.size }} seçildi</span>
        </div>

        <div class="bulk-buttons">
          <button
            type="button"
            class="btn-bulk-ai"
            :disabled="actionLoading || selectedIds.size === 0"
            title="Seçilenleri Yapay Zeka ile Doğrula"
            @click="handleAiCheck()"
          >
            <Sparkles :size="16" />
            <span>AI Doğrula</span>
          </button>
          <button
            type="button"
            class="btn-bulk-approve"
            :disabled="actionLoading || selectedIds.size === 0"
            @click="handleBulkApprove"
          >
            <CheckCheck :size="16" />
            <span>Toplu Onayla</span>
          </button>
        </div>
      </div>
    </Transition>

    <!-- 5. HATA VEYA YÜKLENİYOR DURUMLARI -->
    <div v-if="loading && reports.length === 0" class="clay-skeleton-grid" role="status">
      <div class="clay-skeleton-card clay-skeleton-card--lg"></div>
      <div class="clay-skeleton-card clay-skeleton-card--lg"></div>
    </div>

    <div v-else-if="errorMessage" class="clay-state-card" role="alert">
      <AlertCircle :size="28" class="text-coral" />
      <strong>Veriler Alınamadı</strong>
      <p>{{ errorMessage }}</p>
      <button type="button" class="clay-retry-btn" @click="loadReports(pagination.page)">
        Tekrar Dene
      </button>
    </div>

    <!-- 6. BOŞ DURUM -->
    <div v-else-if="reports.length === 0" class="bento-card bento-card--white empty-state-card">
      <div class="empty-icon-wrap">
        <Gauge :size="36" class="text-muted" />
      </div>
      <h3>{{ tabMeta[kmState.activeTab]?.emptyTitle || 'Kayıt Yok' }}</h3>
      <p>{{ tabMeta[kmState.activeTab]?.emptyDesc || 'Bu görünümde görüntülenecek veri bulunmuyor.' }}</p>
    </div>

    <!-- 7. BİLDİRİM LİSTESİ -->
    <div v-else class="km-cards-list">
      <!-- A) YAPMAYANLAR LİSTESİ KARTLARI (Puantaj Tarzı Minimal Kart) -->
      <template v-if="kmState.activeTab === 'unreported'">
        <article
          v-for="item in reports"
          :key="`${item.personel_id}-${item.arac_id}`"
          class="project-card km-unreported-card"
        >
          <!-- Üst Satır: Tarih/Durum Çipi & Plaka Tag'i -->
          <div class="project-card-header">
            <div class="project-date-pill">
              <Clock :size="12" class="text-muted" />
              <span>Bugün Bildirim Yok</span>
            </div>

            <div class="project-card-actions">
              <div class="tr-plate-badge tr-plate-badge--sm" title="Araç Plakası">
                <span class="tr-band">TR</span>
                <span class="tr-number">{{ item.plaka }}</span>
              </div>
            </div>
          </div>

          <!-- Orta Satır: Personel Adı, Avatarı & Telefonu -->
          <div class="project-card-body">
            <div class="project-avatar project-avatar--sm">
              <img
                v-if="item.personel_resim_url || item.personel_resim"
                :src="item.personel_resim_url || resolveImageUrl(item.personel_resim)"
                :alt="item.personel_adi"
                class="project-avatar-img"
                @error="$event.target.style.display = 'none'"
              />
              <div class="project-avatar-txt">
                {{ getInitials(item.personel_adi) }}
              </div>
            </div>
            <div class="project-title-wrap">
              <h3 class="project-title">{{ item.personel_adi }}</h3>
              <span v-if="item.telefon" class="project-tc">Tel: {{ item.telefon }}</span>
              <span v-else class="project-tc">Zimmetli Araç Kullanıcısı</span>
            </div>
          </div>

          <!-- Alt Satır: Durum Noktası & Minimal Hatırlat Butonu -->
          <div class="project-card-footer">
            <div class="project-dot-item">
              <span
                class="dot-indicator"
                :class="item.son_30_gun_aksam_eksik > 2 ? 'dot-orange' : 'dot-purple'"
              ></span>
              <span>Son 30 günde <strong>{{ item.son_30_gun_aksam_eksik || 0 }}</strong> akşam eksik</span>
            </div>

            <button
              type="button"
              class="km-pill-action-btn"
              :disabled="actionLoading"
              @click="handleSendReminder(item)"
            >
              <Bell :size="12" />
              <span>Hatırlat</span>
            </button>
          </div>
        </article>
      </template>

      <!-- B) BEKLEYEN, ONAYLANAN VE REDDEDİLEN BİLDİRİM KARTLARI (Puantaj Tarzı Minimal Kart) -->
      <template v-else>
        <article
          v-for="r in reports"
          :key="r.id"
          class="project-card km-project-card"
          :class="{ 'is-selected': selectedIds.has(r.id) }"
        >
          <!-- Üst Satır: Seçim Checkbox + Tarih Çipi & Tür Rozeti + Plaka -->
          <div class="project-card-header">
            <div class="project-header-left">
              <label v-if="selectionMode && kmState.activeTab === 'pending'" class="km-select-checkbox">
                <input
                  type="checkbox"
                  :checked="selectedIds.has(r.id)"
                  @change="toggleSelectReport(r.id)"
                />
                <span class="custom-checkbox"></span>
              </label>

              <div class="project-date-pill">
                <Calendar :size="12" class="text-muted" />
                <span>{{ formatDate(r.tarih) }} <small class="text-dark fw-bold">{{ formatTime(r.olusturma_tarihi) }}</small></span>
              </div>
            </div>

            <div class="project-card-actions">
              <span
                class="tur-pill-badge"
                :class="r.tur === 'sabah' ? 'tur-pill-badge--sabah' : 'tur-pill-badge--aksam'"
              >
                <Sun v-if="r.tur === 'sabah'" :size="11" />
                <Moon v-else :size="11" />
                <span>{{ r.tur === 'sabah' ? 'Sabah' : 'Akşam' }}</span>
              </span>

              <div class="tr-plate-badge tr-plate-badge--sm" title="Araç Plakası">
                <span class="tr-band">TR</span>
                <span class="tr-number">{{ r.plaka }}</span>
              </div>
            </div>
          </div>

          <!-- Orta Satır: Personel Bilgisi & Sağda Kompakt KM Sayaç + Mini Fotoğraf -->
          <div class="project-card-body km-body-split">
            <!-- Sol: Avatar + İsim + Araç -->
            <div class="km-personel-info">
              <div class="project-avatar project-avatar--sm">
                <img
                  v-if="r.personel_resim_url || r.personel_resim"
                  :src="r.personel_resim_url || resolveImageUrl(r.personel_resim)"
                  :alt="r.personel_adi"
                  class="avatar-img"
                  @error="$event.target.style.display = 'none'"
                />
                <div class="project-avatar-txt">
                  {{ getInitials(r.personel_adi) }}
                </div>
              </div>
              <div class="project-title-wrap">
                <h3 class="project-title">{{ r.personel_adi }}</h3>
                <span class="project-tc">{{ r.marka ? `${r.marka} ${r.model || ''}` : 'Zimmetli Araç' }}</span>
              </div>
            </div>

            <!-- Sağ: KM Değeri & Mini Fotoğraf Kutusu -->
            <div class="km-reading-badge-wrap">
              <div class="km-counter-box">
                <strong class="km-number-bold">{{ formatNumber(r.bitis_km) }}</strong>
                <small class="km-unit-text">KM</small>
                <span
                  v-if="r.tur === 'aksam' && r.sabah_km && Number(r.bitis_km) >= Number(r.sabah_km)"
                  class="km-diff-pill"
                >
                  +{{ formatNumber(Number(r.bitis_km) - Number(r.sabah_km)) }}
                </span>
              </div>

              <!-- Mini Fotoğraf Thumbnail (Lightbox Açıcı) -->
              <div
                v-if="r.resim_url || r.resim_yolu"
                class="km-thumb-preview"
                role="button"
                tabindex="0"
                title="KM Fotoğrafını Büyüt"
                @click.stop="openLightbox(r)"
              >
                <img
                  :src="getKmPhotoUrl(r)"
                  alt="KM Göstergesi"
                  loading="lazy"
                  class="km-thumb-img"
                />
                <span class="thumb-zoom-icon"><Maximize2 :size="10" /></span>
              </div>
              <div v-else class="km-thumb-placeholder" title="Fotoğraf Yüklenmedi">
                <Car :size="16" class="text-muted" />
              </div>
            </div>
          </div>

          <!-- Varsa Personel Notu -->
          <div v-if="r.aciklama" class="km-mini-note">
            <span>“{{ r.aciklama }}”</span>
          </div>

          <!-- Varsa AI Uyarısı -->
          <button
            v-if="r.ai_onaylanmama_nedeni"
            type="button"
            class="km-mini-ai-alert"
            aria-label="Yapay zekâ onaylamama nedenini göster"
            @click.stop="openAiReason(r)"
          >
            <Sparkles :size="13" class="text-amber" />
            <span>{{ r.ai_onaylanmama_nedeni }}</span>
            <Info :size="13" class="km-ai-reason-info" />
          </button>

          <!-- Alt Satır (Footer): Durum / Aksiyonlar -->
          <div class="project-card-footer km-card-footer-custom">
            <!-- Bekleyenler: Minimal Butonlar Grubu -->
            <template v-if="kmState.activeTab === 'pending'">
              <div class="km-action-pill-group">
                <button
                  type="button"
                  class="btn-km-subtle btn-km-subtle--danger"
                  :disabled="actionLoading"
                  @click.stop="openRejectModal(r)"
                >
                  <X :size="13" />
                  <span>Reddet</span>
                </button>

                <button
                  v-if="canEdit"
                  type="button"
                  class="btn-km-subtle btn-km-subtle--warning"
                  :disabled="actionLoading"
                  @click.stop="openEditModal(r)"
                >
                  <Edit3 :size="13" />
                  <span>Düzelt</span>
                </button>

                <button
                  type="button"
                  class="btn-km-subtle btn-km-subtle--primary"
                  :disabled="actionLoading"
                  @click.stop="handleApprove(r)"
                >
                  <Check :size="13" />
                  <span>Onayla</span>
                </button>

                <!-- Mini AI Doğrulama Butonu -->
                <button
                  v-if="r.resim_url || r.resim_yolu"
                  type="button"
                  class="btn-km-subtle btn-km-subtle--ai"
                  :disabled="actionLoading"
                  title="Yapay zeka ile doğrula"
                  @click.stop="handleAiCheck([r.id])"
                >
                  <Sparkles :size="13" />
                  <span>AI</span>
                </button>
              </div>
            </template>

            <!-- Onaylananlar: Renkli Dot Göstergesi -->
            <template v-else-if="kmState.activeTab === 'approved'">
              <div class="project-dot-item">
                <span class="dot-indicator dot-green"></span>
                <span>Onaylayan: <strong>{{ r.onaylayan_adi || 'Sistem' }}</strong> ({{ formatNumber(r.onaylanan_km || r.bitis_km) }} KM)</span>
              </div>
            </template>

            <!-- Reddedilenler: Renkli Dot Göstergesi -->
            <template v-else-if="kmState.activeTab === 'rejected'">
              <div class="project-dot-item">
                <span class="dot-indicator dot-red"></span>
                <span>Red Nedeni: <strong>{{ r.red_nedeni || 'Açıklama belirtilmedi' }}</strong></span>
              </div>
            </template>
          </div>
        </article>
      </template>
    </div>

    <!-- 8. SAYFALAMA KONTROLLERİ -->
    <div v-if="pagination.totalPages > 1" class="km-pagination-wrap">
      <button
        type="button"
        class="clay-circle-btn pagination-btn"
        :disabled="pagination.page <= 1 || loading"
        aria-label="Önceki sayfa"
        @click="goToPage(pagination.page - 1)"
      >
        <ChevronLeft :size="20" />
      </button>

      <div class="pagination-info">
        <strong>Sayfa {{ pagination.page }} / {{ pagination.totalPages }}</strong>
        <small>{{ formatNumber(pagination.total) }} Toplam Kayıt</small>
      </div>

      <button
        type="button"
        class="clay-circle-btn pagination-btn"
        :disabled="pagination.page >= pagination.totalPages || loading"
        aria-label="Sonraki sayfa"
        @click="goToPage(pagination.page + 1)"
      >
        <ChevronRight :size="20" />
      </button>
    </div>

    <!-- ============================================== -->
    <!-- MODAL 1: RESİM LIGHTBOX (BÜYÜTEÇ) MODALI -->
    <!-- ============================================== -->
    <Transition name="ds-modal">
      <div
        v-if="lightboxOpen"
        class="ds-modal-layer"
        role="dialog"
        aria-modal="true"
        aria-label="KM Fotoğrafı Önizleme"
        @click.self="lightboxOpen = false"
      >
        <div class="lightbox-content">
          <button
            type="button"
            class="lightbox-close-btn"
            aria-label="Kapat"
            @click="lightboxOpen = false"
          >
            <X :size="22" />
          </button>

          <div
            class="lightbox-image-wrap"
            :class="{ 'is-zoomed': lightboxZoomed }"
            @dblclick="toggleLightboxZoom"
          >
            <img :src="lightboxData.imageUrl" :alt="lightboxData.plaka" class="lightbox-img" />
          </div>

          <div class="lightbox-footer">
            <div class="lightbox-info">
              <div class="tr-plate-badge tr-plate-badge--sm">
                <span class="tr-band">TR</span>
                <span class="tr-number">{{ lightboxData.plaka }}</span>
              </div>
              <strong class="lightbox-km">{{ lightboxData.km }} KM</strong>
            </div>
            <div class="lightbox-person-date">
              <span>{{ lightboxData.personel }}</span>
              <small>{{ lightboxData.tur }} • {{ lightboxData.tarih }}</small>
            </div>
          </div>
        </div>
      </div>
    </Transition>

    <Transition name="ds-modal">
      <div
        v-if="aiReasonModalOpen"
        class="ds-modal-layer"
        role="dialog"
        aria-modal="true"
        aria-label="Yapay zekâ onaylamama nedeni"
        @click.self="aiReasonModalOpen = false"
      >
        <div class="bento-sheet-modal ai-reason-sheet">
          <header class="sheet-head">
            <div>
              <span class="sheet-eyebrow text-cyan">YAPAY ZEKÂ KARAR AYRINTISI</span>
              <h3>Neden Otomatik Onaylanmadı?</h3>
            </div>
            <button type="button" class="sheet-close-btn" @click="aiReasonModalOpen = false">
              <X :size="20" />
            </button>
          </header>
          <div class="sheet-body">
            <div class="ai-reason-plate">{{ aiReasonData.plaka }}</div>
            <div class="ai-reason-card">
              <AlertCircle :size="20" />
              <p>{{ aiReasonData.reason }}</p>
            </div>
            <p class="ai-reason-note">
              Bu kayıt doğruluk eşiğini geçmediği için otomatik onaylanmadı ve manuel incelemede bırakıldı.
            </p>
            <footer class="sheet-footer">
              <button type="button" class="btn-primary-close" @click="aiReasonModalOpen = false">
                Anladım, Kapat
              </button>
            </footer>
          </div>
        </div>
      </div>
    </Transition>

    <!-- ============================================== -->
    <!-- MODAL 2: DÜZELT VE ONAYLA MODALI -->
    <!-- ============================================== -->
    <Transition name="ds-modal">
      <div
        v-if="editModalOpen"
        class="ds-modal-layer"
        role="dialog"
        aria-modal="true"
        aria-label="KM Düzelt ve Onayla"
        @click.self="editModalOpen = false"
      >
        <div class="bento-sheet-modal">
          <header class="sheet-head">
            <div>
              <span class="sheet-eyebrow">DÜZELTME & ONAY</span>
              <h3>KM Değerini Güncelle</h3>
            </div>
            <button type="button" class="sheet-close-btn" @click="editModalOpen = false">
              <X :size="20" />
            </button>
          </header>

          <form class="sheet-body" @submit.prevent="submitEditApprove">
            <div class="edit-target-info">
              <div class="tr-plate-badge tr-plate-badge--sm">
                <span class="tr-band">TR</span>
                <span class="tr-number">{{ editData.plaka }}</span>
              </div>
              <div>
                <strong>{{ editData.personel }}</strong>
                <small>{{ editData.tur }} • {{ editData.tarih }}</small>
              </div>
            </div>

            <!-- KM Giriş Alanı -->
            <div class="form-group">
              <div class="label-row">
                <label for="input-new-km">Onaylanacak Kilometre</label>
                <span v-if="editData.sabahKm" class="ref-km-hint">
                  Sabah KM: {{ formatNumber(editData.sabahKm) }}
                </span>
              </div>
              <div class="km-input-wrapper">
                <input
                  id="input-new-km"
                  v-model.number="editData.newKm"
                  type="number"
                  required
                  min="1"
                  class="km-large-input"
                />
                <span class="km-unit-badge">KM</span>
              </div>
            </div>

            <!-- Hızlı Düzeltme Butonları (+10, +50, +100) -->
            <div class="quick-adjust-buttons">
              <button type="button" class="adjust-chip" @click="adjustNewKm(10)">+10</button>
              <button type="button" class="adjust-chip" @click="adjustNewKm(50)">+50</button>
              <button type="button" class="adjust-chip" @click="adjustNewKm(100)">+100</button>
              <button type="button" class="adjust-chip adjust-chip--reset" @click="resetNewKm">
                Sıfırla
              </button>
            </div>

            <!-- Açıklama Notu -->
            <div class="form-group">
              <label for="input-edit-note">Düzeltme Açıklaması / Not (İsteğe Bağlı)</label>
              <textarea
                id="input-edit-note"
                v-model="editData.aciklama"
                rows="2"
                class="clay-textarea"
                placeholder="KM düzeltme nedenini yazabilirsiniz..."
              ></textarea>
            </div>

            <footer class="sheet-footer">
              <button type="button" class="btn-cancel" @click="editModalOpen = false">
                Vazgeç
              </button>
              <button type="submit" class="btn-confirm-edit" :disabled="actionLoading">
                <Check :size="16" />
                <span>Düzelt ve Onayla</span>
              </button>
            </footer>
          </form>
        </div>
      </div>
    </Transition>

    <!-- ============================================== -->
    <!-- MODAL 3: REDDETME MODALI -->
    <!-- ============================================== -->
    <Transition name="ds-modal">
      <div
        v-if="rejectModalOpen"
        class="ds-modal-layer"
        role="dialog"
        aria-modal="true"
        aria-label="KM Bildirimi Reddet"
        @click.self="rejectModalOpen = false"
      >
        <div class="bento-sheet-modal">
          <header class="sheet-head">
            <div>
              <span class="sheet-eyebrow text-coral">BİLDİRİM REDDİ</span>
              <h3>Bildirimi Reddet</h3>
            </div>
            <button type="button" class="sheet-close-btn" @click="rejectModalOpen = false">
              <X :size="20" />
            </button>
          </header>

          <form class="sheet-body" @submit.prevent="submitReject">
            <div class="reject-summary-box">
              <p>
                <b>{{ rejectData.plaka }}</b> plakalı araca ait
                <b>{{ formatNumber(rejectData.km) }} KM</b> bildirimini reddetmek üzeresiniz.
              </p>
            </div>

            <!-- Hazır Şablonlar -->
            <div class="form-group">
              <label>Hızlı Red Sebepleri</label>
              <div class="quick-reasons-wrap">
                <button
                  type="button"
                  class="reason-pill"
                  @click="setRejectReasonTemplate('Fotoğraftaki sayaç rakamı okunaksız / bulanık.')"
                >
                  Okunaksız Fotoğraf
                </button>
                <button
                  type="button"
                  class="reason-pill"
                  @click="setRejectReasonTemplate('Girilen KM değeri ile göstergedeki değer uyuşmuyor.')"
                >
                  KM Uyuşmazlığı
                </button>
                <button
                  type="button"
                  class="reason-pill"
                  @click="setRejectReasonTemplate('Mükerrer veya hatalı bildirim yapılmış.')"
                >
                  Mükerrer Bildirim
                </button>
              </div>
            </div>

            <div class="form-group">
              <label for="input-reject-reason">Red Nedeni Açıklaması</label>
              <textarea
                id="input-reject-reason"
                v-model="rejectData.redNedeni"
                rows="3"
                class="clay-textarea"
                placeholder="Personele iletilecek red nedenini yazınız..."
              ></textarea>
            </div>

            <footer class="sheet-footer">
              <button type="button" class="btn-cancel" @click="rejectModalOpen = false">
                Vazgeç
              </button>
              <button type="submit" class="btn-confirm-reject" :disabled="actionLoading">
                <X :size="16" />
                <span>Reddet</span>
              </button>
            </footer>
          </form>
        </div>
      </div>
    </Transition>

    <!-- ============================================== -->
    <!-- MODAL 4: YAPAY ZEKA KONTROL RAPOR MODALI -->
    <!-- ============================================== -->
    <Transition name="ds-modal">
      <div
        v-if="aiResultModalOpen"
        class="ds-modal-layer"
        role="dialog"
        aria-modal="true"
        aria-label="Yapay Zeka Analiz Raporu"
        @click.self="aiResultModalOpen = false"
      >
        <div class="bento-sheet-modal">
          <header class="sheet-head">
            <div>
              <span class="sheet-eyebrow text-cyan">GÖRÜNTÜ İŞLEME VE OCR ANALİZİ</span>
              <h3>Yapay Zekâ Analiz Raporu</h3>
            </div>
            <button type="button" class="sheet-close-btn" @click="aiResultModalOpen = false">
              <X :size="20" />
            </button>
          </header>

          <div class="sheet-body">
            <div v-if="aiResultData" class="ai-report-summary">
              <div class="ai-score-row">
                <div class="ai-score-box ai-score-box--success">
                  <strong>{{ aiResultData.onaylanan || 0 }}</strong>
                  <span>Otomatik Onaylandı</span>
                </div>
                <div class="ai-score-box ai-score-box--warning">
                  <strong>{{ aiResultData.manuel || 0 }}</strong>
                  <span>Manuel İncelemede</span>
                </div>
              </div>

              <div v-if="aiResultData.sonuclar && aiResultData.sonuclar.length" class="ai-results-list">
                <div
                  v-for="(item, idx) in aiResultData.sonuclar"
                  :key="idx"
                  class="ai-result-item"
                  :class="item.durum === 'onaylandi' ? 'is-success' : 'is-manual'"
                >
                  <div class="result-head">
                    <span class="result-status-badge">
                      {{ item.durum === 'onaylandi' ? 'ONAYLANDI' : 'MANUEL' }}
                    </span>
                    <strong v-if="item.plaka">{{ item.plaka }}</strong>
                    <span v-if="item.okunan_km" class="odometer-reading">
                      Okunan: {{ formatNumber(item.okunan_km) }} KM
                    </span>
                  </div>
                  <p v-if="item.neden" class="result-reason">{{ item.neden }}</p>
                </div>
              </div>
            </div>

            <footer class="sheet-footer">
              <button
                type="button"
                class="btn-primary-close"
                @click="aiResultModalOpen = false"
              >
                Anladım, Kapat
              </button>
            </footer>
          </div>
        </div>
      </div>
    </Transition>
  </section>
</template>

<style scoped>
.ds-km-screen {
  padding-bottom: 90px;
}

/* ======================================================== */
/* 1. MİNİMAL BENTO ÖZET STRIP & DAY/NIGHT TOGGLE (2. RESİM) */
/* ======================================================== */
.km-minimal-summary-strip {
  margin-top: 10px;
  margin-bottom: 14px;
  padding: 12px 16px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  border-radius: 22px;
  background: var(--dsx-bg-191d24);
  box-shadow: var(--ds-shadow-bento-dark);
}

.km-mini-summary-info {
  display: flex;
  flex-direction: column;
  gap: 3px;
  flex: 1;
  min-width: 0;
}

.km-mini-tag {
  display: flex;
  align-items: center;
  gap: 6px;
}

.km-mini-tag-text {
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.06em;
  color: var(--ds-on-dark-muted, var(--dsx-fg-94a3b8));
  text-transform: uppercase;
}

.km-mini-counter-row {
  display: flex;
  align-items: baseline;
  gap: 6px;
}

.km-mini-count {
  font-size: 26px;
  font-weight: 900;
  line-height: 1;
  color: #ffffff;
  letter-spacing: -0.02em;
}

.km-mini-label {
  font-size: 11px;
  font-weight: 600;
  color: var(--dsx-fg-94a3b8);
}

.km-mini-track {
  height: 4px;
  width: 100%;
  max-width: 140px;
  margin-top: 2px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.1);
}

/* ======================================================== */
/* 2. GÜNDÜZ / GECE (SABAH - AKŞAM) TOGGLE SWITCH (2. RESİM) */
/* ======================================================== */
.km-mini-toggle-wrap {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 4px;
  flex-shrink: 0;
}

.toggle-control-box {
  display: flex;
  align-items: center;
  gap: 8px;
}

.toggle-all-chip {
  height: 28px;
  padding: 0 10px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 700;
  border: 1px solid rgba(255, 255, 255, 0.16);
  background: rgba(255, 255, 255, 0.08);
  color: var(--dsx-fg-94a3b8);
  cursor: pointer;
  transition: all 0.2s ease;
}

.toggle-all-chip:hover,
.toggle-all-chip.is-active {
  background: rgba(255, 255, 255, 0.22);
  color: #ffffff;
  border-color: rgba(255, 255, 255, 0.35);
}

/* 2. Resimdeki Oval Switch Kapsülü */
.day-night-toggle {
  position: relative;
  width: 74px;
  height: 36px;
  border-radius: 999px;
  border: 2px solid #000000;
  cursor: pointer;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 4px;
  transition: background 0.35s ease, border-color 0.35s ease, box-shadow 0.2s ease;
  user-select: none;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

.day-night-toggle:active {
  transform: scale(0.96);
}

/* Gündüz (Sabah) Teması - 2. Resimdeki Üst Switch */
.day-night-toggle.is-day {
  background: var(--dsx-bg-f1f5f9);
  border-color: #0f172a;
}

/* Gece (Akşam) Teması - 2. Resimdeki Alt Switch */
.day-night-toggle.is-night {
  background: var(--dsx-bg-1e293b);
  border-color: #334155;
  box-shadow: inset 0 2px 6px rgba(0, 0, 0, 0.4), 0 4px 14px rgba(15, 23, 42, 0.5);
}

.day-night-toggle.is-all {
  background: #334155;
  border-color: #475569;
}

/* Düğme (Knob) */
.toggle-sliding-knob {
  position: absolute;
  top: 3px;
  left: 3px;
  width: 26px;
  height: 26px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 2;
  transition: transform 0.35s cubic-bezier(0.34, 1.56, 0.64, 1), background 0.35s ease, box-shadow 0.35s ease;
}

/* Gündüz Modunda Knob Sola Konumlanır ve Siyah Daire Olur (2. resim üst switch) */
.day-night-toggle.is-day .toggle-sliding-knob {
  transform: translateX(0);
  background: var(--dsx-bg-191d24);
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.35);
}

/* Gece Modunda Knob Sağa Kayar ve Beyaz Daire Olur (2. resim alt switch) */
.day-night-toggle.is-night .toggle-sliding-knob {
  transform: translateX(38px);
  background: var(--dsx-bg-ffffff);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.4);
}

.day-night-toggle.is-all .toggle-sliding-knob {
  transform: translateX(19px);
  background: var(--dsx-bg-cbd5e1);
}

/* Güneş İkonu (Sağ tarafta) */
.toggle-day-icon {
  position: absolute;
  right: 8px;
  width: 20px;
  height: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: opacity 0.3s ease, transform 0.3s ease;
  pointer-events: none;
}

.day-sun-svg {
  width: 18px;
  height: 18px;
  color: var(--dsx-fg-0f172a);
}

.day-night-toggle.is-night .toggle-day-icon {
  opacity: 0.15;
  transform: scale(0.85);
}

/* Hilal ve Yıldızlar İkonu (Sol tarafta) */
.toggle-night-icon {
  position: absolute;
  left: 7px;
  width: 22px;
  height: 22px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: opacity 0.3s ease, transform 0.3s ease;
  pointer-events: none;
}

.night-moon-svg {
  width: 20px;
  height: 20px;
}

.day-night-toggle.is-day .toggle-night-icon {
  opacity: 0.15;
  transform: scale(0.85);
}

.toggle-state-text {
  font-size: 10px;
  font-weight: 700;
  color: var(--ds-on-dark-muted, var(--dsx-fg-94a3b8));
  letter-spacing: 0.02em;
}

.text-cyan {
  color: #38bdf8 !important;
}

.spin-anim {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

/* ======================================================== */
/* 3. ARAMA VE FİLTRELEME BARI */
/* ======================================================== */
.km-filter-bar {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-bottom: 16px;
}

.clay-search-box {
  position: relative;
  display: flex;
  align-items: center;
  background: var(--dsx-bg-ffffff);
  border: 1px solid var(--dsx-bd-000000-a006);
  border-radius: 18px;
  padding: 0 14px;
  height: 46px;
  box-shadow: var(--ds-shadow-bento);
}

.clay-search-icon {
  color: var(--dsx-fg-94a3b8);
  margin-right: 10px;
  flex-shrink: 0;
}

.clay-search-input {
  flex: 1;
  border: none;
  outline: none;
  background: transparent;
  font-family: inherit;
  font-size: 13px;
  font-weight: 500;
  color: var(--dsx-fg-1e293b);
}

.clay-search-clear {
  border: none;
  background: transparent;
  color: var(--dsx-fg-94a3b8);
  cursor: pointer;
  padding: 4px;
}

.km-tur-filter-chips {
  display: flex;
  gap: 6px;
  overflow-x: auto;
  padding-bottom: 2px;
}

.tur-chip {
  border: 1px solid var(--dsx-bd-000000-a005);
  background: var(--dsx-bg-ffffff);
  padding: 5px 12px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 600;
  color: var(--dsx-fg-64748b);
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  transition: all 0.2s;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
}

.tur-chip.is-active {
  background: var(--dsx-bg-191d24);
  color: #ffffff;
  border-color: #191d24;
}

.unreported-actions-bar {
  margin-top: 2px;
}

.btn-bulk-remind {
  width: 100%;
  height: 42px;
  background: linear-gradient(135deg, #f59e0b, #d97706);
  color: #ffffff;
  border: none;
  border-radius: 14px;
  font-size: 12px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  cursor: pointer;
  box-shadow: 0 4px 10px rgba(245, 158, 11, 0.25);
  transition: transform 0.15s;
}

.btn-bulk-remind:active {
  transform: scale(0.98);
}

/* ======================================================== */
/* 4. ÇOKLU SEÇİM EYLEM BARI */
/* ======================================================== */
.km-bulk-action-bar {
  position: sticky;
  top: 10px;
  z-index: 40;
  background: var(--dsx-bg-191d24);
  color: #ffffff;
  padding: 10px 14px;
  border-radius: 18px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 14px;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.28);
}

.bulk-info {
  display: flex;
  align-items: center;
  gap: 10px;
}

.btn-check-all {
  background: transparent;
  border: none;
  color: #ffffff;
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 11px;
  font-weight: 700;
  cursor: pointer;
}

.selected-badge {
  background: rgba(255, 255, 255, 0.15);
  font-size: 10px;
  padding: 2px 8px;
  border-radius: 10px;
  font-weight: 600;
}

.bulk-buttons {
  display: flex;
  gap: 6px;
}

.btn-bulk-ai {
  background: linear-gradient(135deg, #8b5cf6, #6366f1);
  color: #ffffff;
  border: none;
  border-radius: 12px;
  padding: 6px 12px;
  font-size: 11px;
  font-weight: 700;
  display: flex;
  align-items: center;
  gap: 5px;
  cursor: pointer;
}

.btn-bulk-approve {
  background: #10b981;
  color: #ffffff;
  border: none;
  border-radius: 12px;
  padding: 6px 12px;
  font-size: 11px;
  font-weight: 700;
  display: flex;
  align-items: center;
  gap: 5px;
  cursor: pointer;
}

.btn-bulk-ai:disabled,
.btn-bulk-approve:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* ======================================================== */
/* 5. PUANTAJ TARZI MİNİMAL LİSTE KARTLARI */
/* ======================================================== */
.km-cards-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

/* Ana Kart */
.project-card {
  background: var(--dsx-bg-ffffff);
  border-radius: 20px;
  padding: 12px 14px;
  box-shadow: 0 4px 14px -4px rgba(15, 23, 42, 0.05);
  border: 1px solid var(--dsx-bd-ffffff-a085);
  position: relative;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.project-card:active {
  transform: scale(0.99);
}

.project-card.is-selected {
  outline: 2px solid #3b82f6;
  box-shadow: 0 6px 16px rgba(59, 130, 246, 0.18);
}

/* Üst Satır (Header) */
.project-card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 10px;
}

.project-header-left {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
}

.project-date-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  background: var(--dsx-bg-f8fafc);
  border: 1px solid var(--dsx-bd-f1f5f9);
  padding: 3px 8px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 600;
  color: var(--dsx-fg-64748b);
  white-space: nowrap;
}

.project-card-actions {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-shrink: 0;
}

/* Checkbox */
.km-select-checkbox {
  position: relative;
  display: flex;
  align-items: center;
  cursor: pointer;
}

.km-select-checkbox input {
  opacity: 0;
  position: absolute;
}

.custom-checkbox {
  width: 18px;
  height: 18px;
  border-radius: 5px;
  border: 2px solid var(--dsx-bd-cbd5e1);
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.15s;
}

.km-select-checkbox input:checked + .custom-checkbox {
  background: #3b82f6;
  border-color: #3b82f6;
}

.km-select-checkbox input:checked + .custom-checkbox::after {
  content: '✓';
  color: white;
  font-size: 11px;
  font-weight: bold;
}

/* TR Plaka Tasarımı (Kompakt) */
.tr-plate-badge {
  display: inline-flex;
  align-items: center;
  background: var(--dsx-bg-ffffff);
  border: 1.2px solid #1e293b;
  border-radius: 5px;
  overflow: hidden;
  height: 22px;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.06);
}

.tr-plate-badge--sm {
  height: 20px;
}

.tr-band {
  background: #003399;
  color: #ffffff;
  font-size: 8px;
  font-weight: 900;
  padding: 0 3px;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  letter-spacing: 0.3px;
}

.tr-number {
  color: var(--dsx-fg-0f172a);
  font-size: 11px;
  font-weight: 800;
  padding: 0 5px;
  letter-spacing: 0.5px;
  text-transform: uppercase;
}

/* Tür Rozetleri */
.tur-pill-badge {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  font-size: 10px;
  font-weight: 700;
  padding: 2px 7px;
  border-radius: 999px;
  text-transform: uppercase;
}

.tur-pill-badge--sabah {
  background: var(--dsx-bg-fef3c7);
  color: var(--dsx-fg-b45309);
}

.tur-pill-badge--aksam {
  background: var(--dsx-bg-ede9fe);
  color: #6d28d9;
}

/* Orta Satır (Body) */
.project-card-body {
  display: flex;
  align-items: center;
  gap: 10px;
}

.km-body-split {
  justify-content: space-between;
}

.km-personel-info {
  display: flex;
  align-items: center;
  gap: 10px;
  flex: 1;
  min-width: 0;
}

/* Avatar */
.project-avatar {
  width: 38px;
  height: 38px;
  border-radius: 50%;
  background: var(--dsx-bg-f1f5f9);
  border: 1.5px solid var(--dsx-bd-e2e8f0);
  overflow: hidden;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
}

.project-avatar--sm {
  width: 36px;
  height: 36px;
}

.project-avatar-img,
.avatar-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.project-avatar-txt {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: 800;
  color: var(--dsx-fg-1e293b);
  background: var(--dsx-bg-f1f5f9);
}

/* İsim & İkincil Bilgi */
.project-title-wrap {
  flex: 1;
  min-width: 0;
}

.project-title {
  font-size: 14px;
  font-weight: 800;
  color: var(--dsx-fg-1e293b);
  margin: 0 0 2px 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  line-height: 1.2;
}

.project-tc {
  font-size: 11px;
  font-weight: 600;
  color: var(--dsx-fg-94a3b8);
  display: block;
}

/* KM Okuması & Mini Fotoğraf Thumbnail Alanı */
.km-reading-badge-wrap {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
}

.km-counter-box {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
}

.km-number-bold {
  font-size: 15px;
  font-weight: 900;
  line-height: 1.1;
  color: var(--dsx-fg-0f172a);
  letter-spacing: -0.01em;
}

.km-unit-text {
  font-size: 9px;
  font-weight: 700;
  color: var(--dsx-fg-64748b);
  letter-spacing: 0.5px;
}

.km-diff-pill {
  background: var(--dsx-bg-ecfdf5);
  color: var(--dsx-fg-059669);
  border: 1px solid var(--dsx-bd-a7f3d0);
  font-size: 9px;
  font-weight: 800;
  padding: 1px 5px;
  border-radius: 999px;
  margin-top: 1px;
}

/* Mini Fotoğraf Thumbnail */
.km-thumb-preview {
  position: relative;
  width: 44px;
  height: 44px;
  border-radius: 10px;
  overflow: hidden;
  border: 1.5px solid var(--dsx-bd-e2e8f0);
  cursor: pointer;
  background: var(--dsx-bg-0f172a);
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
}

.km-thumb-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.2s ease;
}

.km-thumb-preview:active .km-thumb-img {
  transform: scale(1.08);
}

.thumb-zoom-icon {
  position: absolute;
  bottom: 2px;
  right: 2px;
  background: rgba(0, 0, 0, 0.6);
  color: #ffffff;
  width: 16px;
  height: 16px;
  border-radius: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
  backdrop-filter: blur(2px);
}

.km-thumb-placeholder {
  width: 44px;
  height: 44px;
  border-radius: 10px;
  background: var(--dsx-bg-f1f5f9);
  border: 1px dashed var(--dsx-bd-cbd5e1);
  display: flex;
  align-items: center;
  justify-content: center;
}

/* Personel Notu & AI Uyarısı */
.km-mini-note {
  margin-top: 8px;
  background: var(--dsx-bg-f8fafc);
  border-left: 2.5px solid #3b82f6;
  padding: 5px 8px;
  border-radius: 0 8px 8px 0;
  font-size: 11px;
  font-style: italic;
  color: var(--dsx-fg-475569);
  line-height: 1.35;
}

.km-mini-ai-alert {
  width: 100%;
  margin-top: 8px;
  background: var(--dsx-bg-fffbeb);
  border: 1px solid var(--dsx-bd-fde68a);
  border-radius: 8px;
  padding: 5px 8px;
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 11px;
  color: var(--dsx-fg-92400e);
  font-family: inherit;
  text-align: left;
  cursor: pointer;
}

.km-mini-ai-alert span {
  flex: 1;
}

.km-ai-reason-info {
  flex: 0 0 auto;
  opacity: 0.75;
}

/* Alt Satır (Footer) & Renkli Noktalar (Puantaj Görsel 2) */
.project-card-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-top: 10px;
  padding-top: 8px;
  border-top: 1px solid var(--dsx-bd-f1f5f9);
}

.km-card-footer-custom {
  margin-top: 8px;
  padding-top: 8px;
}

.project-dot-item {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 11px;
  color: var(--dsx-fg-64748b);
}

.dot-indicator {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  flex-shrink: 0;
}

.dot-purple { background: #8b5cf6; }
.dot-green { background: #10b981; }
.dot-orange { background: #f59e0b; }
.dot-red { background: #ef4444; }
.dot-blue { background: #3b82f6; }

/* Minimal Hatırlat Butonu (Puantaj tarzı pill) */
.km-pill-action-btn {
  background: #0284c7;
  color: #ffffff;
  border: none;
  border-radius: 999px;
  padding: 5px 12px;
  font-size: 11px;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  cursor: pointer;
  box-shadow: 0 1px 4px rgba(2, 132, 199, 0.25);
  transition: transform 0.15s ease;
}

.km-pill-action-btn:active {
  transform: scale(0.96);
}

.km-pill-action-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Bekleyenler İçin Minimal Aksiyon Butonları */
.km-action-pill-group {
  display: flex;
  align-items: center;
  gap: 6px;
  width: 100%;
}

.btn-km-subtle {
  flex: 1;
  height: 30px;
  border-radius: 8px;
  font-size: 11px;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
  border: 1px solid transparent;
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-km-subtle:active {
  transform: scale(0.97);
}

.btn-km-subtle--primary {
  background: var(--dsx-bg-ecfdf5);
  color: var(--dsx-fg-059669);
  border-color: var(--dsx-bd-a7f3d0);
  flex: 1.3;
}

.btn-km-subtle--warning {
  background: var(--dsx-bg-fffbeb);
  color: var(--dsx-fg-b45309);
  border-color: var(--dsx-bd-fde68a);
}

.btn-km-subtle--danger {
  background: var(--dsx-bg-fef2f2);
  color: #dc2626;
  border-color: var(--dsx-bd-fecaca);
}

.btn-km-subtle--ai {
  flex: 0 0 34px;
  background: var(--dsx-bg-f5f3ff);
  color: #7c3aed;
  border-color: var(--dsx-bd-ddd6fe);
}

/* ======================================================== */
/* 7. SAYFALAMA */
/* ======================================================== */
.km-pagination-wrap {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 18px;
  padding: 0 4px;
}

.pagination-btn {
  width: 42px;
  height: 42px;
}

.pagination-info {
  text-align: center;
}

.pagination-info strong {
  display: block;
  font-size: 12px;
  font-weight: 800;
  color: var(--dsx-fg-1e293b);
}

.pagination-info small {
  font-size: 10px;
  font-weight: 600;
  color: var(--dsx-fg-94a3b8);
}

/* ======================================================== */
/* 8. MODALLAR VE LIGHTBOX */
/* ======================================================== */
.ds-modal-layer {
  position: fixed;
  inset: 0;
  z-index: 100;
  background: rgba(15, 23, 42, 0.7);
  backdrop-filter: blur(6px);
  display: flex;
  align-items: flex-end;
  justify-content: center;
}

@media (min-width: 640px) {
  .ds-modal-layer {
    align-items: center;
    padding: 20px;
  }
}

.bento-sheet-modal {
  width: 100%;
  max-width: 480px;
  background: var(--dsx-bg-ffffff);
  border-radius: 28px 28px 0 0;
  overflow: hidden;
  box-shadow: 0 -10px 40px rgba(0, 0, 0, 0.25);
  animation: slideUp 0.25s ease-out;
}

@media (min-width: 640px) {
  .bento-sheet-modal {
    border-radius: 28px;
  }
}

@keyframes slideUp {
  from { transform: translateY(100%); }
  to { transform: translateY(0); }
}

.sheet-head {
  padding: 16px 20px;
  border-bottom: 1px solid var(--dsx-bd-000000-a005);
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: var(--dsx-bg-f8fafc);
}

.sheet-eyebrow {
  display: block;
  font-size: 10px;
  font-weight: 800;
  color: var(--dsx-fg-0284c7);
  letter-spacing: 0.5px;
}

.sheet-head h3 {
  font-size: 15px;
  font-weight: 900;
  color: var(--dsx-fg-0f172a);
  margin: 0;
}

.sheet-close-btn {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: var(--dsx-bg-e2e8f0);
  color: var(--dsx-fg-475569);
  border: none;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.sheet-body {
  padding: 18px 20px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.edit-target-info {
  display: flex;
  align-items: center;
  gap: 10px;
  background: var(--dsx-bg-f8fafc);
  padding: 10px 12px;
  border-radius: 14px;
}

.edit-target-info strong {
  display: block;
  font-size: 12px;
  font-weight: 800;
  color: var(--dsx-fg-1e293b);
}

.edit-target-info small {
  font-size: 10px;
  color: var(--dsx-fg-64748b);
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.label-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.label-row label {
  font-size: 11px;
  font-weight: 700;
  color: var(--dsx-fg-475569);
}

.ref-km-hint {
  font-size: 10px;
  font-weight: 700;
  color: var(--dsx-fg-0284c7);
}

.km-input-wrapper {
  position: relative;
  display: flex;
  align-items: center;
}

.km-large-input {
  width: 100%;
  height: 52px;
  border: 2px solid var(--dsx-bd-e2e8f0);
  border-radius: 16px;
  padding: 0 50px 0 16px;
  font-size: 20px;
  font-weight: 900;
  color: var(--dsx-fg-0f172a);
  outline: none;
  transition: border-color 0.2s;
}

.km-large-input:focus {
  border-color: #0284c7;
}

.km-unit-badge {
  position: absolute;
  right: 14px;
  font-size: 12px;
  font-weight: 800;
  color: var(--dsx-fg-94a3b8);
}

.quick-adjust-buttons {
  display: flex;
  gap: 6px;
}

.adjust-chip {
  flex: 1;
  height: 32px;
  border: 1px solid var(--dsx-bd-e2e8f0);
  background: var(--dsx-bg-f8fafc);
  border-radius: 10px;
  font-size: 11px;
  font-weight: 700;
  color: var(--dsx-fg-334155);
  cursor: pointer;
  transition: all 0.15s;
}

.adjust-chip:active {
  background: #0284c7;
  color: #ffffff;
}

.adjust-chip--reset {
  color: #ef4444;
  border-color: var(--dsx-bd-fecaca);
  background: var(--dsx-bg-fef2f2);
}

.clay-textarea {
  width: 100%;
  border: 1.5px solid var(--dsx-bd-e2e8f0);
  border-radius: 14px;
  padding: 10px 12px;
  font-family: inherit;
  font-size: 12px;
  color: var(--dsx-fg-1e293b);
  outline: none;
  resize: none;
}

.clay-textarea:focus {
  border-color: #0284c7;
}

.sheet-footer {
  display: flex;
  gap: 10px;
  margin-top: 6px;
}

.btn-cancel {
  flex: 1;
  height: 44px;
  border-radius: 14px;
  background: var(--dsx-bg-f1f5f9);
  color: var(--dsx-fg-475569);
  border: none;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
}

.btn-confirm-edit {
  flex: 2;
  height: 44px;
  border-radius: 14px;
  background: #0284c7;
  color: #ffffff;
  border: none;
  font-size: 12px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  cursor: pointer;
  box-shadow: 0 4px 12px rgba(2, 132, 199, 0.25);
}

.btn-confirm-reject {
  flex: 2;
  height: 44px;
  border-radius: 14px;
  background: #ef4444;
  color: #ffffff;
  border: none;
  font-size: 12px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  cursor: pointer;
  box-shadow: 0 4px 12px rgba(239, 68, 68, 0.25);
}

/* Red Sebepleri Şablonları */
.quick-reasons-wrap {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.reason-pill {
  border: 1px solid var(--dsx-bd-e2e8f0);
  background: var(--dsx-bg-f8fafc);
  padding: 5px 10px;
  border-radius: 12px;
  font-size: 10px;
  font-weight: 600;
  color: var(--dsx-fg-475569);
  cursor: pointer;
}

.reason-pill:active {
  background: var(--dsx-bg-fee2e2);
  color: var(--dsx-fg-b91c1c);
  border-color: var(--dsx-bd-fca5a5);
}

/* Lightbox Modal */
.lightbox-content {
  position: relative;
  width: 100%;
  height: 100%;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  padding: 16px;
}

.lightbox-close-btn {
  position: absolute;
  top: 20px;
  right: 20px;
  z-index: 10;
  width: 44px;
  height: 44px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.2);
  color: #ffffff;
  border: none;
  display: flex;
  align-items: center;
  justify-content: center;
  backdrop-filter: blur(8px);
  cursor: pointer;
}

.lightbox-image-wrap {
  max-width: 100%;
  max-height: 75vh;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: auto;
  transition: transform 0.2s;
}

.lightbox-image-wrap.is-zoomed {
  transform: scale(1.6);
  cursor: zoom-out;
}

.lightbox-img {
  max-width: 100%;
  max-height: 70vh;
  border-radius: 16px;
  object-contain: contain;
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
}

.lightbox-footer {
  position: absolute;
  bottom: 24px;
  background: rgba(0, 0, 0, 0.65);
  backdrop-filter: blur(12px);
  padding: 12px 20px;
  border-radius: 20px;
  display: flex;
  align-items: center;
  gap: 16px;
  color: #ffffff;
}

.lightbox-info {
  display: flex;
  align-items: center;
  gap: 10px;
}

.lightbox-km {
  font-size: 18px;
  font-weight: 900;
  color: #38bdf8;
}

.lightbox-person-date {
  display: flex;
  flex-direction: column;
  font-size: 11px;
}

.lightbox-person-date span {
  font-weight: 700;
}

.lightbox-person-date small {
  opacity: 0.7;
}

/* AI Rapor Modalı */
.ai-report-summary {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.ai-score-row {
  display: flex;
  gap: 10px;
}

.ai-score-box {
  flex: 1;
  padding: 14px;
  border-radius: 16px;
  text-align: center;
}

.ai-score-box strong {
  display: block;
  font-size: 24px;
  font-weight: 900;
  margin-bottom: 2px;
}

.ai-score-box span {
  font-size: 11px;
  font-weight: 700;
}

.ai-score-box--success {
  background: var(--dsx-bg-ecfdf5);
  color: var(--dsx-fg-059669);
}

.ai-score-box--warning {
  background: var(--dsx-bg-fffbeb);
  color: var(--dsx-fg-b45309);
}

.ai-results-list {
  max-height: 200px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.ai-result-item {
  padding: 10px 12px;
  border-radius: 12px;
  font-size: 11px;
}

.ai-result-item.is-success {
  background: var(--dsx-bg-f0fdf4);
  border: 1px solid var(--dsx-bd-bbf7d0);
}

.ai-result-item.is-manual {
  background: var(--dsx-bg-fffbeb);
  border: 1px solid var(--dsx-bd-fef3c7);
}

.result-head {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 3px;
}

.result-status-badge {
  font-size: 9px;
  font-weight: 800;
  padding: 1px 5px;
  border-radius: 6px;
  background: var(--dsx-bg-000000-a008);
}

.odometer-reading {
  font-weight: 700;
  color: var(--dsx-fg-0284c7);
  margin-left: auto;
}

.result-reason {
  margin: 0;
  color: var(--dsx-fg-64748b);
  font-size: 10px;
  line-height: 1.35;
}

.btn-primary-close {
  width: 100%;
  height: 44px;
  background: var(--dsx-bg-191d24);
  color: #ffffff;
  border: none;
  border-radius: 14px;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
}

.ai-reason-sheet {
  max-width: 420px;
}

.ai-reason-plate {
  display: inline-flex;
  align-items: center;
  min-height: 32px;
  padding: 0 12px;
  border: 1px solid var(--dsx-bd-cbd5e1);
  border-radius: 10px;
  background: var(--dsx-bg-ffffff);
  color: var(--dsx-fg-172033);
  font-size: 13px;
  font-weight: 900;
  letter-spacing: 0.06em;
}

.ai-reason-card {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  margin-top: 12px;
  padding: 14px;
  border: 1px solid var(--dsx-bd-fde68a);
  border-radius: 16px;
  background: var(--dsx-bg-fffbeb);
  color: var(--dsx-fg-92400e);
}

.ai-reason-card svg {
  flex: 0 0 auto;
  margin-top: 1px;
}

.ai-reason-card p,
.ai-reason-note {
  margin: 0;
  line-height: 1.5;
}

.ai-reason-card p {
  font-size: 12px;
  font-weight: 700;
}

.ai-reason-note {
  margin-top: 10px;
  color: var(--dsx-fg-64748b);
  font-size: 11px;
}

/* Toast Bildirimi */
.ds-toast-banner {
  position: fixed;
  top: 16px;
  left: 16px;
  right: 16px;
  max-width: 440px;
  margin: 0 auto;
  z-index: 120;
  background: #10b981;
  color: #ffffff;
  padding: 12px 16px;
  border-radius: 16px;
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 12px;
  font-weight: 700;
  box-shadow: 0 10px 30px rgba(16, 185, 129, 0.4);
}

.ds-toast-icon {
  background: rgba(255, 255, 255, 0.2);
  border-radius: 50%;
  padding: 2px;
  flex-shrink: 0;
}

/* Boş Durum */
.empty-state-card {
  text-align: center;
  padding: 36px 20px;
}

.empty-icon-wrap {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  background: var(--dsx-bg-f1f5f9);
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 14px;
}

.empty-state-card h3 {
  font-size: 15px;
  font-weight: 800;
  color: var(--dsx-fg-1e293b);
  margin: 0 0 6px;
}

.empty-state-card p {
  font-size: 12px;
  color: var(--dsx-fg-64748b);
  margin: 0;
  line-height: 1.5;
}
</style>
