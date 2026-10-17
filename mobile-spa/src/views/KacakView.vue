<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import {
  AlertCircle,
  Award,
  Calendar,
  Camera,
  Check,
  CheckCircle2,
  ChevronDown,
  ChevronLeft,
  ChevronRight,
  Clock,
  Download,
  ExternalLink,
  Eye,
  FileSpreadsheet,
  FileText,
  Filter,
  Image as ImageIcon,
  Info,
  MapPin,
  Maximize2,
  Navigation,
  Phone,
  Plus,
  RefreshCw,
  RotateCcw,
  Search,
  Send,
  ShieldAlert,
  Sparkles,
  Trophy,
  Trash2,
  User,
  UserCheck,
  Users,
  X,
  XCircle,
} from '@lucide/vue'
import TopBar from '../components/TopBar.vue'
import { auth } from '../services/auth.js'
import { kacakApi, kacakState } from '../services/kacak.js'

// UI Yükleme & İşlem Durumları
const loading = ref(false)
const actionLoading = ref(false)
const toastMessage = ref('')
const toastType = ref('success') // 'success' | 'error' | 'warning'
let toastTimer = null

function showToast(msg, type = 'success') {
  toastMessage.value = msg
  toastType.value = type
  if (toastTimer) clearTimeout(toastTimer)
  toastTimer = setTimeout(() => {
    toastMessage.value = ''
  }, 3500)
}

// Liste Verileri
const kayitlar = ref([])
const counts = ref({ toplam: 0, beklemede: 0, onaylandi: 0, reddedildi: 0, iptal: 0 })
const leaderboard = ref([])
const permissions = ref({ can_approve: false, can_cancel: false, can_revert_cancel: false })

// İlçeler (Sabit İlçe Listesi)
const ilceler = [
  'Onikişubat',
  'Dulkadiroğlu',
  'Afşin',
  'Andırın',
  'Çağlayancerit',
  'Ekinözü',
  'Elbistan',
  'Göksun',
  'Nurhak',
  'Pazarcık',
  'Türkoğlu',
]

// Filtreler
const searchInput = ref('')
const selectedIlce = ref('')
const selectedTur = ref('') // '' | 'Kaçak' | 'Abonesiz' | 'Usülsüz' | 'Pasif Abone'
const activeStatus = ref('') // '' (tümü) | 'beklemede' | 'onaylandi' | 'reddedildi' | 'iptal'
const datePreset = ref('30days') // 'month' | '30days' | 'year' | 'all'
const baslangicTarihi = ref('')
const bitisTarihi = ref('')

// Yatay Kaydırma Yardımcıları (Mouse Wheel & Drag-to-Scroll)
function handleWheelScroll(e) {
  if (e.deltaY !== 0) {
    e.currentTarget.scrollLeft += e.deltaY
  }
}

function startDragScroll(e) {
  if (e.button !== 0) return
  const slider = e.currentTarget
  let isDown = true
  const startX = e.pageX - slider.offsetLeft
  const scrollLeft = slider.scrollLeft

  function onMouseMove(moveEvent) {
    if (!isDown) return
    moveEvent.preventDefault()
    const x = moveEvent.pageX - slider.offsetLeft
    const walk = (x - startX) * 1.5
    slider.scrollLeft = scrollLeft - walk
  }

  function onMouseUp() {
    isDown = false
    window.removeEventListener('mousemove', onMouseMove)
    window.removeEventListener('mouseup', onMouseUp)
  }

  window.addEventListener('mousemove', onMouseMove)
  window.addEventListener('mouseup', onMouseUp)
}

// Tutanak Detayı & Drawer
const activeDetail = ref(null)
const drawerOpen = ref(false)

// Ekip Başarı Sıralaması (Stats Sheet)
const statsSheetOpen = ref(false)
const statsSearch = ref('')

// Resim Galerisi & Lightbox
const galleryOpen = ref(false)
const galleryPhotos = ref([])
const galleryLoading = ref(false)
const lightboxOpen = ref(false)
const lightboxIndex = ref(0)

// Aksiyon Modalı (Onayla / Reddet / İptal / Geri Al)
const actionModal = ref({
  open: false,
  type: '', // 'approve' | 'reject' | 'cancel' | 'revert_cancel'
  title: '',
  text: '',
  reason: '',
  hakedistenDus: false,
  token: '',
})

// Tarih Yardımcıları
function initDates() {
  const now = new Date()
  bitisTarihi.value = now.toISOString().slice(0, 10)
  // Varsayılan son 30 gün
  const d30 = new Date(now)
  d30.setDate(d30.getDate() - 30)
  baslangicTarihi.value = d30.toISOString().slice(0, 10)
  datePreset.value = '30days'
}

function applyDatePreset(preset) {
  datePreset.value = preset
  const now = new Date()
  bitisTarihi.value = now.toISOString().slice(0, 10)

  if (preset === 'month') {
    const oneMonthAgo = new Date(now)
    oneMonthAgo.setMonth(oneMonthAgo.getMonth() - 1)
    baslangicTarihi.value = oneMonthAgo.toISOString().slice(0, 10)
  } else if (preset === '30days') {
    const d30 = new Date(now)
    d30.setDate(d30.getDate() - 30)
    baslangicTarihi.value = d30.toISOString().slice(0, 10)
  } else if (preset === 'year') {
    baslangicTarihi.value = `${now.getFullYear()}-01-01`
  } else if (preset === 'all') {
    baslangicTarihi.value = '2020-01-01'
  }
  loadData()
}

// Ana Veri Yükleme
async function loadData() {
  loading.value = true
  try {
    const res = await kacakApi.list({
      baslangic: baslangicTarihi.value,
      bitis: bitisTarihi.value,
    })
    kayitlar.value = res.data.records || []
    counts.value = res.data.counts || { toplam: 0, beklemede: 0, onaylandi: 0, reddedildi: 0, iptal: 0 }
    leaderboard.value = res.data.leaderboard || []
    permissions.value = res.data.permissions || { can_approve: false, can_cancel: false, can_revert_cancel: false }

    kacakState.counts = counts.value
    kacakState.leaderboard = leaderboard.value
    kacakState.permissions = permissions.value
  } catch (err) {
    showToast(err.message || 'Kayıtlar yüklenemedi.', 'error')
  } finally {
    loading.value = false
  }
}

// Hero Segment Dağılımları
const onayOrani = computed(() => {
  const total = counts.value.toplam || 0
  if (!total) return 0
  return Math.round(((counts.value.onaylandi || 0) / total) * 100)
})

const heroSegments = computed(() => {
  const total = counts.value.toplam || 0
  if (!total) {
    return { beklemede: 0, onaylandi: 0, reddedildi: 0, iptal: 0 }
  }
  return {
    beklemede: Math.round(((counts.value.beklemede || 0) / total) * 100),
    onaylandi: Math.round(((counts.value.onaylandi || 0) / total) * 100),
    reddedildi: Math.round(((counts.value.reddedildi || 0) / total) * 100),
    iptal: Math.round(((counts.value.iptal || 0) / total) * 100),
  }
})

// Filtrelenmiş Tutanaklar
const filteredKayitlar = computed(() => {
  const q = searchInput.value.trim().toLocaleLowerCase('tr-TR')
  const ilce = selectedIlce.value
  const tur = selectedTur.value
  const status = activeStatus.value

  return kayitlar.value.filter((item) => {
    // Durum filtresi
    if (status === 'beklemede' && (item.durum === 'iptal' || item.onay_durumu !== 'beklemede')) return false
    if (status === 'onaylandi' && (item.durum === 'iptal' || item.onay_durumu !== 'onaylandi')) return false
    if (status === 'reddedildi' && (item.durum === 'iptal' || item.onay_durumu !== 'reddedildi')) return false
    if (status === 'iptal' && item.durum !== 'iptal') return false

    // İlçe filtresi
    if (ilce && item.ilce !== ilce) return false

    // Tür filtresi
    if (tur && item.tur !== tur) return false

    // Arama filtresi
    if (q) {
      const searchBlob = `${item.tutanak_no} ${item.abone_adi} ${item.sayac_no} ${item.ekip_adi} ${item.bildiren_adi} ${item.ilce} ${item.tur}`.toLocaleLowerCase('tr-TR')
      if (!searchBlob.includes(q)) return false
    }

    return true
  })
})

function setStatus(st) {
  activeStatus.value = activeStatus.value === st ? '' : st
}

// Kart Tıklama & Detay Drawer
function openDetail(item) {
  activeDetail.value = item
  drawerOpen.value = true
}

function closeDetail() {
  drawerOpen.value = false
  setTimeout(() => {
    activeDetail.value = null
  }, 250)
}

// Galeri & Lightbox
async function openGallery(token) {
  galleryLoading.value = true
  galleryPhotos.value = []
  galleryOpen.value = true

  try {
    const res = await kacakApi.photos(token)
    galleryPhotos.value = res.data || []
  } catch (err) {
    showToast(err.message || 'Fotoğraflar yüklenemedi.', 'error')
  } finally {
    galleryLoading.value = false
  }
}

function closeGallery() {
  galleryOpen.value = false
  galleryPhotos.value = []
}

const imageOnlyPhotos = computed(() => galleryPhotos.value.filter((p) => !p.pdf))

function openLightbox(photo) {
  const idx = imageOnlyPhotos.value.findIndex((p) => p.token === photo.token)
  if (idx !== -1) {
    lightboxIndex.value = idx
    lightboxOpen.value = true
  }
}

function closeLightbox() {
  lightboxOpen.value = false
}

function nextPhoto() {
  if (!imageOnlyPhotos.value.length) return
  lightboxIndex.value = (lightboxIndex.value + 1) % imageOnlyPhotos.value.length
}

function prevPhoto() {
  if (!imageOnlyPhotos.value.length) return
  lightboxIndex.value =
    (lightboxIndex.value - 1 + imageOnlyPhotos.value.length) % imageOnlyPhotos.value.length
}

// Aksiyon Başlat
function promptAction(type, item) {
  actionModal.value = {
    open: true,
    type,
    token: item.token,
    title: '',
    text: '',
    reason: '',
    hakedistenDus: false,
  }

  if (type === 'approve') {
    actionModal.value.title = 'Bildirimi Onayla'
    actionModal.value.text = `${item.tutanak_no || 'Bu'} kaçak tutanağını onaylamak üzeresiniz. Tutanak onaylanarak hakediş ve sisteme işlenecektir.`
  } else if (type === 'reject') {
    actionModal.value.title = 'Bildirimi Reddet'
    actionModal.value.text = 'Lütfen bildirimin reddedilme gerekçesini belirtin.'
  } else if (type === 'cancel') {
    actionModal.value.title = 'Tutanağı İptal Et'
    actionModal.value.text = 'Bu tutanak iptal edilecek. Lütfen iptal gerekçesini girin.'
    actionModal.value.hakedistenDus = false
  } else if (type === 'revert_cancel') {
    actionModal.value.title = 'İptali Geri Al'
    actionModal.value.text = 'İptal edilmiş bu tutanak tekrar aktif duruma getirilecek ve listelere dahil edilecektir.'
  }
}

function closeActionModal() {
  actionModal.value.open = false
}

async function submitAction() {
  const { type, token, reason, hakedistenDus } = actionModal.value
  const csrfToken = auth.state.csrf_token

  if ((type === 'reject' || type === 'cancel') && !reason.trim()) {
    showToast('İşlem nedeni yazılması zorunludur.', 'warning')
    return
  }

  actionLoading.value = true
  try {
    let res
    if (type === 'approve') {
      res = await kacakApi.approve(token, csrfToken)
    } else if (type === 'reject') {
      res = await kacakApi.reject(token, reason.trim(), csrfToken)
    } else if (type === 'cancel') {
      res = await kacakApi.cancel(token, reason.trim(), hakedistenDus, csrfToken)
    } else if (type === 'revert_cancel') {
      res = await kacakApi.revertCancel(token, csrfToken)
    }

    showToast(res.message || 'İşlem başarıyla tamamlandı.')
    closeActionModal()
    closeDetail()
    await loadData()
  } catch (err) {
    showToast(err.message || 'İşlem gerçekleştirilemedi.', 'error')
  } finally {
    actionLoading.value = false
  }
}

// Ekip Liderlik Sheet
function openStatsSheet() {
  statsSheetOpen.value = true
  statsSearch.value = ''
}

function closeStatsSheet() {
  statsSheetOpen.value = false
}

const filteredLeaderboard = computed(() => {
  const q = statsSearch.value.trim().toLocaleLowerCase('tr-TR')
  if (!q) return leaderboard.value
  return leaderboard.value.filter((team) => team.name.toLocaleLowerCase('tr-TR').includes(q))
})

// Tür ve Durum Rozet Stilleri
function getTurBadgeClass(tur) {
  switch (tur) {
    case 'Kaçak':
      return 'badge-tur--kacak'
    case 'Abonesiz':
      return 'badge-tur--abonesiz'
    case 'Usülsüz':
      return 'badge-tur--usulsuz'
    case 'Pasif Abone':
      return 'badge-tur--pasif'
    default:
      return 'badge-tur--default'
  }
}

function getStatusBadge(item) {
  if (item.durum === 'iptal') {
    return { label: 'İptal', class: 'badge--slate' }
  }
  switch (item.onay_durumu) {
    case 'onaylandi':
      return { label: 'Onaylandı', class: 'badge--emerald' }
    case 'reddedildi':
      return { label: 'Reddedildi', class: 'badge--rose' }
    default:
      return { label: 'Beklemede', class: 'badge--amber' }
  }
}

onMounted(() => {
  initDates()
  loadData()
})
</script>

<template>
  <div class="ihbar-view">
    <!-- TopBar -->
    <TopBar
      eyebrow="Saha İşlemleri"
      title="Kaçak Kontrol"
      subtitle="Kaçak ve abonesiz tutanak operasyonları"
    >
      <template #actions>
        <button
          type="button"
          class="clay-circle-btn"
          aria-label="Ekip Performansı"
          title="Ekip Performansı"
          @click="openStatsSheet"
        >
          <Trophy :size="20" class="text-amber-500" />
        </button>
        <button
          type="button"
          class="clay-circle-btn"
          :class="{ 'animate-spin': loading }"
          aria-label="Yenile"
          title="Yenile"
          @click="loadData"
        >
          <RefreshCw :size="18" />
        </button>
      </template>
    </TopBar>

    <div class="ihbar-content">
      <!-- Obsidian Bento Hero Kartı (Özet Metrikler) -->
      <section class="bento-card bento-card--obsidian ihbar-hero-card">
        <div class="ihbar-hero-head">
          <div>
            <span class="ihbar-hero-eyebrow">GENEL OPERASYON DURUMU</span>
            <div class="ihbar-hero-total">
              <strong>{{ counts.toplam }}</strong>
              <small>toplam tutanak</small>
            </div>
          </div>
          <div class="ihbar-hero-rate">
            <span class="rate-value">%{{ onayOrani }}</span>
            <span class="rate-label">Onay Oranı</span>
          </div>
        </div>

        <!-- Segmentli İlerleme Çubuğu -->
        <div class="segmented-track" role="img" aria-label="Kaçak tutanak durum dağılımı">
          <span
            class="seg-pill seg-pill--amber"
            :style="{ width: `${heroSegments.beklemede}%` }"
            title="Bekleyen"
          ></span>
          <span
            class="seg-pill seg-pill--emerald"
            :style="{ width: `${heroSegments.onaylandi}%` }"
            title="Onaylı"
          ></span>
          <span
            class="seg-pill seg-pill--coral"
            :style="{ width: `${heroSegments.reddedildi}%` }"
            title="Reddedilen"
          ></span>
          <span
            class="seg-pill seg-pill--slate"
            :style="{ width: `${heroSegments.iptal}%` }"
            title="İptal"
          ></span>
        </div>

        <!-- 4'lü Metrik Izgarası -->
        <div class="ihbar-hero-grid">
          <button
            type="button"
            class="ihbar-hero-cell"
            :class="{ 'is-active': activeStatus === 'beklemede' }"
            @click="setStatus('beklemede')"
          >
            <span class="cell-label"><i class="ds-dot ds-dot--amber"></i>Bekleyen</span>
            <strong class="cell-num text-amber-400">{{ counts.beklemede }}</strong>
          </button>

          <button
            type="button"
            class="ihbar-hero-cell"
            :class="{ 'is-active': activeStatus === 'onaylandi' }"
            @click="setStatus('onaylandi')"
          >
            <span class="cell-label"><i class="ds-dot ds-dot--emerald"></i>Onaylı</span>
            <strong class="cell-num text-emerald-400">{{ counts.onaylandi }}</strong>
          </button>

          <button
            type="button"
            class="ihbar-hero-cell"
            :class="{ 'is-active': activeStatus === 'reddedildi' }"
            @click="setStatus('reddedildi')"
          >
            <span class="cell-label"><i class="ds-dot ds-dot--coral"></i>Reddedilen</span>
            <strong class="cell-num text-rose-400">{{ counts.reddedildi }}</strong>
          </button>

          <button
            type="button"
            class="ihbar-hero-cell"
            :class="{ 'is-active': activeStatus === 'iptal' }"
            @click="setStatus('iptal')"
          >
            <span class="cell-label"><i class="ds-dot ds-dot--slate"></i>İptal</span>
            <strong class="cell-num text-slate-300">{{ counts.iptal }}</strong>
          </button>
        </div>
      </section>

      <!-- Dönem Seçici & Tarih Hapları -->
      <div class="ihbar-date-bar">
        <div
          class="date-preset-group"
          @wheel.passive="handleWheelScroll"
          @mousedown="startDragScroll"
        >
          <button
            type="button"
            class="clay-filter-pill"
            :class="{ 'is-active': datePreset === 'month' }"
            @click="applyDatePreset('month')"
          >
            Son 1 Ay
          </button>
          <button
            type="button"
            class="clay-filter-pill"
            :class="{ 'is-active': datePreset === '30days' }"
            @click="applyDatePreset('30days')"
          >
            30 Gün
          </button>
          <button
            type="button"
            class="clay-filter-pill"
            :class="{ 'is-active': datePreset === 'year' }"
            @click="applyDatePreset('year')"
          >
            Bu Yıl
          </button>
          <button
            type="button"
            class="clay-filter-pill"
            :class="{ 'is-active': datePreset === 'all' }"
            @click="applyDatePreset('all')"
          >
            Tümü
          </button>
        </div>
        <div class="date-range-text">
          <Calendar :size="14" />
          <span>{{ baslangicTarihi.split('-').reverse().join('.') }} – {{ bitisTarihi.split('-').reverse().join('.') }}</span>
        </div>
      </div>

      <!-- Arama ve Filtre Kartı (Bento White) -->
      <div class="ihbar-search-card bento-card bento-card--white">
        <!-- Arama Inputu -->
        <div class="search-input-wrap">
          <Search :size="18" class="search-icon" />
          <input
            v-model="searchInput"
            type="search"
            placeholder="Tutanak no, sayaç no, abone, ekip veya ilçe ara…"
            class="search-input"
          />
          <button
            v-if="searchInput"
            type="button"
            class="search-clear-btn"
            @click="searchInput = ''"
          >
            <X :size="16" />
          </button>
        </div>

        <!-- Durum Hap Filtreleri (Scrollable) -->
        <div
          class="status-pills-scroll"
          @wheel.passive="handleWheelScroll"
          @mousedown="startDragScroll"
        >
          <button
            type="button"
            class="clay-filter-pill"
            :class="{ 'is-active': activeStatus === '' }"
            @click="activeStatus = ''"
          >
            Tümü <span class="pill-badge">{{ counts.toplam }}</span>
          </button>
          <button
            type="button"
            class="clay-filter-pill"
            :class="{ 'is-active': activeStatus === 'beklemede' }"
            @click="setStatus('beklemede')"
          >
            Bekleyen <span class="pill-badge">{{ counts.beklemede }}</span>
          </button>
          <button
            type="button"
            class="clay-filter-pill"
            :class="{ 'is-active': activeStatus === 'onaylandi' }"
            @click="setStatus('onaylandi')"
          >
            Onaylı <span class="pill-badge">{{ counts.onaylandi }}</span>
          </button>
          <button
            type="button"
            class="clay-filter-pill"
            :class="{ 'is-active': activeStatus === 'reddedildi' }"
            @click="setStatus('reddedildi')"
          >
            Reddedilen <span class="pill-badge">{{ counts.reddedildi }}</span>
          </button>
          <button
            type="button"
            class="clay-filter-pill"
            :class="{ 'is-active': activeStatus === 'iptal' }"
            @click="setStatus('iptal')"
          >
            İptal <span class="pill-badge">{{ counts.iptal }}</span>
          </button>
        </div>

        <!-- Tür Çipleri (Scrollable) -->
        <div
          class="status-pills-scroll"
          @wheel.passive="handleWheelScroll"
          @mousedown="startDragScroll"
        >
          <button
            type="button"
            class="clay-filter-pill"
            :class="{ 'is-active': selectedTur === '' }"
            @click="selectedTur = ''"
          >
            Tüm Türler
          </button>
          <button
            type="button"
            class="clay-filter-pill"
            :class="{ 'is-active': selectedTur === 'Kaçak' }"
            @click="selectedTur = selectedTur === 'Kaçak' ? '' : 'Kaçak'"
          >
            Kaçak
          </button>
          <button
            type="button"
            class="clay-filter-pill"
            :class="{ 'is-active': selectedTur === 'Abonesiz' }"
            @click="selectedTur = selectedTur === 'Abonesiz' ? '' : 'Abonesiz'"
          >
            Abonesiz
          </button>
          <button
            type="button"
            class="clay-filter-pill"
            :class="{ 'is-active': selectedTur === 'Usülsüz' }"
            @click="selectedTur = selectedTur === 'Usülsüz' ? '' : 'Usülsüz'"
          >
            Usülsüz
          </button>
          <button
            type="button"
            class="clay-filter-pill"
            :class="{ 'is-active': selectedTur === 'Pasif Abone' }"
            @click="selectedTur = selectedTur === 'Pasif Abone' ? '' : 'Pasif Abone'"
          >
            Pasif Abone
          </button>
        </div>

        <!-- İlçe Filtresi Dropdown -->
        <div class="ilce-filter-row">
          <select v-model="selectedIlce" class="ilce-select">
            <option value="">Tüm İlçeler (Kahramanmaraş)</option>
            <option v-for="ilce in ilceler" :key="ilce" :value="ilce">
              {{ ilce }}
            </option>
          </select>
        </div>
      </div>

      <!-- Tutanak Listesi -->
      <section class="ihbar-list-section">
        <div class="list-header-row">
          <span class="list-count-badge">
            <b>{{ filteredKayitlar.length }}</b> tutanak gösteriliyor
          </span>
          <button
            v-if="searchInput || selectedIlce || selectedTur || activeStatus"
            type="button"
            class="clear-all-filter-btn"
            @click="searchInput = ''; selectedIlce = ''; selectedTur = ''; activeStatus = ''"
          >
            Filtreleri Temizle
          </button>
        </div>

        <!-- Yükleniyor / Boş Durum -->
        <div v-if="loading && !kayitlar.length" class="ihbar-state-card">
          <div class="loading-spinner"></div>
          <p>Kaçak tutanakları yükleniyor…</p>
        </div>

        <div v-else-if="!filteredKayitlar.length" class="ihbar-state-card">
          <AlertCircle :size="32" class="text-slate-400" />
          <strong>Eşleşen tutanak bulunamadı</strong>
          <p>Arama veya filtre kriterlerinizi değiştirerek tekrar deneyin.</p>
        </div>

        <!-- Tutanak Kartları Izgarası / Listesi -->
        <div v-else class="ihbar-cards-stack">
          <article
            v-for="item in filteredKayitlar"
            :key="item.token"
            class="ihbar-card bento-card bento-card--white active:scale-[.99] transition-transform"
            @click="openDetail(item)"
          >
            <header class="ihbar-card-top">
              <div class="ihbar-location-wrap">
                <span class="location-icon-bubble">
                  <ShieldAlert :size="16" />
                </span>
                <div class="location-text">
                  <h3 class="location-title">
                    {{ item.tutanak_no ? `Tutanak: ${item.tutanak_no}` : (item.abone_adi || 'Tutanak No Yok') }}
                    <span v-if="item.ilce" class="location-sub">· {{ item.ilce }}</span>
                  </h3>
                </div>
              </div>
              <span class="status-badge" :class="getStatusBadge(item).class">
                {{ getStatusBadge(item).label }}
              </span>
            </header>

            <p v-if="item.abone_adi && item.tutanak_no" class="ihbar-card-desc">
              Abone: <strong>{{ item.abone_adi }}</strong> {{ item.aciklama ? '— ' + item.aciklama : '' }}
            </p>
            <p v-else-if="item.aciklama" class="ihbar-card-desc">
              {{ item.aciklama }}
            </p>

            <!-- Ek Bilgi Etiketleri (Tür, Sayaç, Fotoğraf, ZIP İndir) -->
            <div class="ihbar-card-tags">
              <span class="badge-tur" :class="getTurBadgeClass(item.tur)">
                {{ item.tur }}
              </span>
              <span v-if="item.sayac_no" class="mini-tag">
                Sayaç: {{ item.sayac_no }}
              </span>
              <span v-if="item.endeks" class="mini-tag">
                Endeks: {{ item.endeks }}
              </span>
              <span v-if="item.foto_sayisi > 0" class="mini-tag mini-tag--foto">
                <ImageIcon :size="12" />
                {{ item.foto_sayisi }} Fotoğraf
              </span>
              <a
                v-if="item.foto_sayisi > 0"
                :href="kacakApi.getZipUrl(item.token)"
                target="_blank"
                class="mini-tag mini-tag--download"
                title="ZIP İndir"
                @click.stop
              >
                <Download :size="12" /> ZIP İndir
              </a>
            </div>

            <!-- Alt Bilgi: Ekip & Tarih -->
            <footer class="ihbar-card-footer">
              <div class="footer-left">
                <span class="footer-item">
                  <User :size="13" class="footer-icon" />
                  <span class="truncate">{{ item.ekip_adi || item.bildiren_adi || 'Ekip yok' }}</span>
                </span>
              </div>
              <time class="footer-date">
                {{ item.tarih_formatted }}
              </time>
            </footer>
          </article>
        </div>
      </section>
    </div>

    <!-- Tutanak Detay Bottom Sheet (Drawer) -->
    <div v-if="drawerOpen" class="ihbar-drawer-backdrop" @click="closeDetail"></div>
    <div class="ihbar-drawer-sheet" :class="{ 'is-open': drawerOpen && activeDetail }">
      <div class="drawer-drag-handle"></div>

      <header v-if="activeDetail" class="drawer-header">
        <div>
          <span class="drawer-eyebrow">TUTANAK DETAYI</span>
          <h2 class="drawer-title">
            {{ activeDetail.tutanak_no ? `Tutanak No: ${activeDetail.tutanak_no}` : (activeDetail.abone_adi || 'Tutanak Detayı') }}
          </h2>
        </div>
        <button type="button" class="clay-circle-btn" @click="closeDetail">
          <X :size="18" />
        </button>
      </header>

      <div v-if="activeDetail" class="drawer-body">
        <!-- Durum & Tür Rozetleri -->
        <div class="flex items-center gap-2">
          <span class="status-badge" :class="getStatusBadge(activeDetail).class">
            {{ getStatusBadge(activeDetail).label }}
          </span>
          <span class="badge-tur" :class="getTurBadgeClass(activeDetail.tur)">
            {{ activeDetail.tur }}
          </span>
        </div>

        <!-- Detay Bilgileri Bento Kartı -->
        <div class="detail-info-bento bento-card bento-card--white">
          <div class="detail-row">
            <span class="detail-label">Tutanak No</span>
            <strong class="detail-val">{{ activeDetail.tutanak_no || '-' }}</strong>
          </div>
          <div class="detail-row">
            <span class="detail-label">Tarih</span>
            <span class="detail-val font-semibold">{{ activeDetail.tarih_formatted }}</span>
          </div>
          <div class="detail-row">
            <span class="detail-label">Abone Adı</span>
            <strong class="detail-val">{{ activeDetail.abone_adi || '-' }}</strong>
          </div>
          <div class="detail-row">
            <span class="detail-label">İlçe</span>
            <span class="detail-val">{{ activeDetail.ilce || '-' }}</span>
          </div>
          <div class="detail-row">
            <span class="detail-label">Sayaç No</span>
            <span class="detail-val font-semibold">{{ activeDetail.sayac_no || '-' }}</span>
          </div>
          <div class="detail-row">
            <span class="detail-label">Endeks</span>
            <span class="detail-val">{{ activeDetail.endeks || '-' }}</span>
          </div>
          <div class="detail-row detail-row--col">
            <span class="detail-label">Ekip / Bildiren Personel</span>
            <span class="detail-val font-bold text-slate-800">
              {{ activeDetail.ekip_adi || activeDetail.bildiren_adi || '-' }}
            </span>
          </div>
          <div v-if="activeDetail.aciklama" class="detail-row detail-row--col">
            <span class="detail-label">Açıklama</span>
            <p class="text-xs text-slate-600 m-0 whitespace-pre-wrap">{{ activeDetail.aciklama }}</p>
          </div>
        </div>

        <!-- Varsa Red Nedeni -->
        <div v-if="activeDetail.red_nedeni" class="detail-reason-box">
          <div class="flex items-center gap-1.5 font-bold text-xs text-rose-700">
            <AlertCircle :size="15" />
            <span>Red Nedeni</span>
          </div>
          <p class="text-xs text-rose-900 mt-1 m-0">{{ activeDetail.red_nedeni }}</p>
        </div>

        <!-- Varsa İptal Açıklaması -->
        <div v-if="activeDetail.durum === 'iptal'" class="detail-reason-box detail-reason-box--slate">
          <div class="flex items-center gap-1.5 font-bold text-xs text-slate-700">
            <AlertCircle :size="15" />
            <span>İptal Açıklaması</span>
          </div>
          <p class="text-xs text-slate-800 mt-1 m-0">
            {{ activeDetail.iptal_aciklama || 'Açıklama belirtilmemiş.' }}
          </p>
          <span class="text-[11px] text-slate-500 mt-1 block">
            {{ activeDetail.hakedisten_dus ? '⚠️ Hakediş hesabından düşüldü.' : 'ℹ️ Hakediş hesabından düşülmedi.' }}
          </span>
        </div>

        <!-- Fotoğraf İşlemleri -->
        <div class="detail-actions-grid">
          <button
            type="button"
            class="detail-action-btn detail-action-btn--primary flex-1"
            :disabled="activeDetail.foto_sayisi === 0"
            @click="openGallery(activeDetail.token)"
          >
            <Camera :size="16" />
            <span>Fotoğrafları İncele ({{ activeDetail.foto_sayisi }})</span>
          </button>
          <a
            v-if="activeDetail.foto_sayisi > 0"
            :href="kacakApi.getZipUrl(activeDetail.token)"
            target="_blank"
            class="detail-action-btn detail-action-btn--secondary"
            title="Tümünü ZIP İndir"
          >
            <Download :size="16" />
          </a>
        </div>

        <!-- Yetkili Aksiyonlar (Onay / Red / İptal) -->
        <div class="detail-actions-vertical space-y-2 pt-2 border-t border-slate-100">
          <template v-if="permissions.can_approve && activeDetail.onay_durumu === 'beklemede' && activeDetail.durum !== 'iptal'">
            <button
              type="button"
              class="w-full py-3 rounded-xl bg-emerald-600 text-white font-bold text-xs flex items-center justify-center gap-1.5 shadow-sm active:scale-95 transition-transform"
              @click="promptAction('approve', activeDetail)"
            >
              <Check :size="16" />
              Bildirimi Onayla
            </button>
            <button
              type="button"
              class="w-full py-3 rounded-xl bg-amber-600 text-white font-bold text-xs flex items-center justify-center gap-1.5 shadow-sm active:scale-95 transition-transform"
              @click="promptAction('reject', activeDetail)"
            >
              <XCircle :size="16" />
              Bildirimi Reddet
            </button>
          </template>

          <button
            v-if="permissions.can_revert_cancel && activeDetail.durum === 'iptal'"
            type="button"
            class="w-full py-3 rounded-xl bg-sky-600 text-white font-bold text-xs flex items-center justify-center gap-1.5 shadow-sm active:scale-95 transition-transform"
            @click="promptAction('revert_cancel', activeDetail)"
          >
            <RotateCcw :size="16" />
            İptali Geri Al
          </button>

          <button
            v-if="permissions.can_cancel && activeDetail.durum !== 'iptal'"
            type="button"
            class="w-full py-3 rounded-xl bg-slate-800 text-white font-bold text-xs flex items-center justify-center gap-1.5 shadow-sm active:scale-95 transition-transform"
            @click="promptAction('cancel', activeDetail)"
          >
            <AlertCircle :size="16" />
            Tutanağı İptal Et
          </button>
        </div>
      </div>
    </div>

    <!-- Ekip Başarı Sıralaması (Stats Sheet) -->
    <div v-if="statsSheetOpen" class="modal-backdrop" @click="closeStatsSheet"></div>
    <div class="ihbar-stats-sheet" :class="{ 'is-open': statsSheetOpen }">
      <div class="drawer-drag-handle"></div>
      <header class="stats-header">
        <div>
          <span class="drawer-eyebrow">EKİP PERFORMANSI</span>
          <h2 class="stats-title">Ekip Kaçak Kontrol Sıralaması</h2>
          <p class="stats-subtitle">{{ baslangicTarihi.split('-').reverse().join('.') }} – {{ bitisTarihi.split('-').reverse().join('.') }}</p>
        </div>
        <button type="button" class="clay-circle-btn" @click="closeStatsSheet">
          <X :size="18" />
        </button>
      </header>

      <div class="stats-body">
        <!-- Arama -->
        <div class="search-input-wrap">
          <Search :size="16" class="search-icon" />
          <input
            v-model="statsSearch"
            type="search"
            placeholder="Ekip adı ara…"
            class="search-input text-xs"
          />
        </div>

        <div v-if="filteredLeaderboard.length === 0" class="text-center py-10 text-slate-400 text-xs">
          Ekip kaydı bulunamadı.
        </div>

        <div v-else class="space-y-3">
          <article
            v-for="team in filteredLeaderboard"
            :key="team.name"
            class="bento-card bento-card--white p-3 space-y-2 border border-slate-100"
          >
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2 min-w-0">
                <span
                  class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold shrink-0"
                  :class="
                    team.rank === 1
                      ? 'bg-amber-100 text-amber-700'
                      : team.rank === 2
                      ? 'bg-slate-200 text-slate-700'
                      : team.rank === 3
                      ? 'bg-orange-100 text-orange-700'
                      : 'bg-slate-100 text-slate-500'
                  "
                >
                  <Trophy v-if="team.rank === 1" :size="13" />
                  <span v-else>{{ team.rank }}</span>
                </span>
                <strong class="text-xs text-slate-800 truncate">{{ team.name }}</strong>
              </div>
              <span class="text-xs font-black text-primary shrink-0">{{ team.count }} Tutanak</span>
            </div>

            <!-- Progress Track -->
            <div class="h-2 rounded-full bg-slate-100 overflow-hidden">
              <div
                class="h-full rounded-full transition-all duration-300"
                :style="{
                  width: `${team.percentage}%`,
                  backgroundColor:
                    team.rank === 1
                      ? '#ea580c'
                      : team.rank === 2
                      ? '#3b82f6'
                      : team.rank === 3
                      ? '#10b981'
                      : '#64748b',
                }"
              ></div>
            </div>
          </article>
        </div>
      </div>
    </div>

    <!-- Fotoğraf Galerisi & Lightbox -->
    <div v-if="galleryOpen" class="modal-backdrop" @click="closeGallery"></div>
    <div class="modal-dialog-sheet" :class="{ 'is-open': galleryOpen }">
      <div class="drawer-drag-handle"></div>
      <header class="modal-head">
        <div>
          <h3 class="modal-title">Tutanak Fotoğrafları</h3>
          <p class="modal-subtitle">{{ galleryPhotos.length }} dosya bulundu</p>
        </div>
        <button type="button" class="clay-circle-btn" @click="closeGallery">
          <X :size="18" />
        </button>
      </header>

      <div class="p-4 overflow-y-auto max-h-[70vh]">
        <div v-if="galleryLoading" class="text-center py-10">
          <RefreshCw :size="28" class="animate-spin text-slate-400 mx-auto" />
          <p class="text-xs text-slate-400 mt-2">Fotoğraflar yükleniyor…</p>
        </div>

        <div v-else-if="galleryPhotos.length === 0" class="text-center py-10 text-slate-400 text-xs">
          Fotoğraf bulunamadı.
        </div>

        <div v-else class="grid grid-cols-2 gap-3 pb-8">
          <div
            v-for="photo in galleryPhotos"
            :key="photo.token"
            class="relative aspect-square rounded-2xl overflow-hidden bg-slate-100 border border-slate-200"
          >
            <a
              v-if="photo.pdf"
              :href="photo.full_url"
              target="_blank"
              class="w-full h-full flex flex-col items-center justify-center p-3 text-center"
            >
              <FileText :size="36" class="text-rose-500" />
              <span class="text-[11px] font-bold text-slate-700 mt-1 truncate max-w-full">{{ photo.ad }}</span>
              <span class="text-[9px] text-slate-400 uppercase font-semibold">PDF Belgesi</span>
            </a>

            <button
              v-else
              type="button"
              class="w-full h-full p-0 border-0 bg-transparent"
              @click="openLightbox(photo)"
            >
              <img
                :src="photo.thumbnail_url"
                :alt="photo.ad"
                loading="lazy"
                class="w-full h-full object-cover"
              />
              <span class="absolute bottom-2 left-2 rounded-full bg-black/60 backdrop-blur px-2 py-0.5 text-[9px] font-bold text-white">
                {{ photo.tur === 'tutanak' ? 'Tutanak' : 'Saha' }}
              </span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Lightbox Tam Ekran Görünüm -->
    <div
      v-if="lightboxOpen && imageOnlyPhotos[lightboxIndex]"
      class="fixed inset-0 z-[120] bg-black/95 flex flex-col justify-between p-4"
      @click.self="closeLightbox"
    >
      <header class="flex items-center justify-between text-white pt-2">
        <span class="text-xs font-bold">
          {{ lightboxIndex + 1 }} / {{ imageOnlyPhotos.length }}
        </span>
        <div class="flex items-center gap-2">
          <a
            :href="imageOnlyPhotos[lightboxIndex].full_url"
            download
            class="w-9 h-9 rounded-full bg-white/20 flex items-center justify-center text-white"
          >
            <Download :size="18" />
          </a>
          <button
            type="button"
            class="w-9 h-9 rounded-full bg-white/20 flex items-center justify-center text-white"
            @click="closeLightbox"
          >
            <X :size="18" />
          </button>
        </div>
      </header>

      <div class="flex-1 flex items-center justify-center p-2 min-h-0">
        <img
          :src="imageOnlyPhotos[lightboxIndex].full_url"
          :alt="imageOnlyPhotos[lightboxIndex].ad"
          class="max-w-full max-h-full object-contain rounded-xl"
        />
      </div>

      <footer class="flex items-center justify-between text-white pb-6">
        <button
          type="button"
          class="w-12 h-12 rounded-full bg-white/20 flex items-center justify-center text-white"
          @click="prevPhoto"
        >
          <ChevronLeft :size="24" />
        </button>
        <button
          type="button"
          class="w-12 h-12 rounded-full bg-white/20 flex items-center justify-center text-white"
          @click="nextPhoto"
        >
          <ChevronRight :size="24" />
        </button>
      </footer>
    </div>

    <!-- Aksiyon Onay Modalı (Sheet) -->
    <div v-if="actionModal.open" class="modal-backdrop" @click="closeActionModal"></div>
    <div class="modal-dialog-sheet" :class="{ 'is-open': actionModal.open }">
      <div class="drawer-drag-handle"></div>
      <header class="modal-head">
        <div>
          <h3 class="modal-title">{{ actionModal.title }}</h3>
          <p class="modal-subtitle">{{ actionModal.text }}</p>
        </div>
        <button type="button" class="clay-circle-btn" @click="closeActionModal">
          <X :size="18" />
        </button>
      </header>

      <div class="p-4 space-y-3">
        <!-- Neden / Açıklama Alanı -->
        <div v-if="actionModal.type === 'reject' || actionModal.type === 'cancel'">
          <label class="form-label font-bold text-xs text-slate-700">
            {{ actionModal.type === 'reject' ? 'Red Nedeni *' : 'İptal Açıklaması *' }}
          </label>
          <textarea
            v-model="actionModal.reason"
            rows="3"
            class="form-textarea"
            :placeholder="actionModal.type === 'reject' ? 'Lütfen red nedenini yazın…' : 'İptal gerekçesini yazın…'"
          ></textarea>
        </div>

        <!-- Hakedişten Düş Onay Kutusu (İptal İçin) -->
        <label
          v-if="actionModal.type === 'cancel'"
          class="flex items-center gap-2 p-3 rounded-xl bg-amber-50 border border-amber-200 text-xs font-bold text-amber-800"
        >
          <input v-model="actionModal.hakedistenDus" type="checkbox" class="rounded text-amber-600" />
          <span>Hakediş hesabından düşülsün</span>
        </label>

        <!-- Butonlar -->
        <div class="grid grid-cols-2 gap-2 pt-2">
          <button
            type="button"
            class="py-3 rounded-xl bg-slate-100 text-slate-700 font-bold text-xs"
            :disabled="actionLoading"
            @click="closeActionModal"
          >
            Vazgeç
          </button>
          <button
            type="button"
            class="py-3 rounded-xl font-bold text-xs text-white"
            :class="{
              'bg-emerald-600': actionModal.type === 'approve',
              'bg-amber-600': actionModal.type === 'reject',
              'bg-rose-600': actionModal.type === 'cancel',
              'bg-sky-600': actionModal.type === 'revert_cancel',
            }"
            :disabled="actionLoading"
            @click="submitAction"
          >
            {{ actionLoading ? 'İşleniyor…' : actionModal.title }}
          </button>
        </div>
      </div>
    </div>

    <!-- Toast Bildirimi -->
    <Transition name="fade">
      <div v-if="toastMessage" class="toast-message" :class="`toast--${toastType}`">
        <CheckCircle2 v-if="toastType === 'success'" :size="16" />
        <AlertCircle v-else :size="16" />
        <span>{{ toastMessage }}</span>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.ihbar-view {
  min-height: 100vh;
  background: var(--ds-canvas, #e6eaef);
  padding-bottom: 96px;
}

.ihbar-content {
  padding: 12px 16px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

/* Obsidian Bento Hero Kartı */
.ihbar-hero-card {
  padding: 16px;
  background: #191d24;
  color: #fff;
  border-radius: var(--ds-radius-card, 28px);
  box-shadow: var(--ds-shadow-bento-dark, 0 10px 30px rgba(0, 0, 0, 0.25));
  border: 1px solid rgba(255, 255, 255, 0.08);
}

.ihbar-hero-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 12px;
}

.ihbar-hero-eyebrow {
  display: block;
  font-size: 10px;
  text-transform: uppercase;
  letter-spacing: 0.12em;
  font-weight: 700;
  color: rgba(255, 255, 255, 0.5);
  margin-bottom: 2px;
}

.ihbar-hero-total strong {
  font-size: 32px;
  font-weight: 900;
  line-height: 1;
}

.ihbar-hero-total small {
  font-size: 12px;
  color: rgba(255, 255, 255, 0.6);
  margin-left: 6px;
}

.ihbar-hero-rate {
  text-align: right;
  background: rgba(255, 255, 255, 0.08);
  padding: 6px 12px;
  border-radius: 14px;
  border: 1px solid rgba(255, 255, 255, 0.1);
}

.rate-value {
  display: block;
  font-size: 18px;
  font-weight: 900;
  color: #34d399;
}

.rate-label {
  display: block;
  font-size: 9px;
  color: rgba(255, 255, 255, 0.6);
  font-weight: 700;
  text-transform: uppercase;
}

.segmented-track {
  display: flex;
  height: 8px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.12);
  overflow: hidden;
  margin-bottom: 14px;
  gap: 2px;
}

.seg-pill {
  height: 100%;
  transition: width 0.3s ease;
}

.seg-pill--slate {
  background: #94a3b8;
}

.seg-pill--amber {
  background: #fbbf24;
}

.seg-pill--emerald {
  background: #34d399;
}

.seg-pill--coral {
  background: #f87171;
}

.ihbar-hero-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 6px;
}

.ihbar-hero-cell {
  background: rgba(255, 255, 255, 0.06);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 16px;
  padding: 8px 4px;
  text-align: center;
  transition: all 0.2s ease;
}

.ihbar-hero-cell:active,
.ihbar-hero-cell.is-active {
  background: rgba(255, 255, 255, 0.18);
  border-color: rgba(255, 255, 255, 0.3);
  transform: scale(0.98);
}

.cell-label {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
  font-size: 10px;
  color: rgba(255, 255, 255, 0.6);
  font-weight: 600;
  margin-bottom: 2px;
}

.cell-num {
  display: block;
  font-size: 15px;
  font-weight: 800;
  color: #fff;
}

/* Tarih Seçici Çubuğu */
.ihbar-date-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 4px;
}

.date-preset-group {
  display: flex;
  gap: 6px;
  overflow-x: auto;
  overflow-y: hidden;
  scrollbar-width: none;
  -webkit-overflow-scrolling: touch;
  touch-action: pan-x;
  cursor: grab;
  user-select: none;
}

.date-preset-group:active {
  cursor: grabbing;
}

.date-preset-group::-webkit-scrollbar {
  display: none;
}

.date-preset-group > button,
.date-preset-group .clay-filter-pill {
  flex-shrink: 0 !important;
  white-space: nowrap !important;
}

.date-range-text {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  color: #64748b;
  font-weight: 600;
  flex-shrink: 0;
}

/* Arama ve Filtre Kartı */
.ihbar-search-card {
  padding: 12px;
  border-radius: 20px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.search-input-wrap {
  position: relative;
  display: flex;
  align-items: center;
}

.search-icon {
  position: absolute;
  left: 12px;
  color: #94a3b8;
  pointer-events: none;
}

.search-input {
  width: 100%;
  height: 42px;
  padding: 0 36px 0 38px;
  border-radius: 14px;
  border: 1px solid #e2e8f0;
  background: #f8fafc;
  font-size: 13px;
  color: #1e293b;
  outline: none;
  transition: all 0.2s ease;
}

.search-input:focus {
  border-color: #3b82f6;
  background: #fff;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.search-clear-btn {
  position: absolute;
  right: 10px;
  width: 24px;
  height: 24px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #94a3b8;
}

.status-pills-scroll {
  display: flex;
  gap: 6px;
  overflow-x: auto;
  overflow-y: hidden;
  padding: 2px 0 4px;
  scrollbar-width: none;
  -webkit-overflow-scrolling: touch;
  touch-action: pan-x;
  cursor: grab;
  user-select: none;
}

.status-pills-scroll:active {
  cursor: grabbing;
}

.status-pills-scroll::-webkit-scrollbar {
  display: none;
}

.status-pills-scroll > button,
.status-pills-scroll .clay-filter-pill {
  flex-shrink: 0 !important;
  white-space: nowrap !important;
}

.pill-badge {
  font-size: 10px;
  background: rgba(0, 0, 0, 0.08);
  padding: 1px 5px;
  border-radius: 999px;
  margin-left: 2px;
}

.clay-filter-pill.is-active .pill-badge {
  background: rgba(255, 255, 255, 0.25);
}

.ilce-filter-row {
  margin-top: 2px;
}

.ilce-select {
  width: 100%;
  height: 38px;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  background: #f8fafc;
  padding: 0 10px;
  font-size: 12px;
  font-weight: 600;
  color: #475569;
  outline: none;
}

/* Liste Başlığı */
.list-header-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 4px 6px;
  font-size: 12px;
}

.list-count-badge {
  color: #64748b;
}

.clear-all-filter-btn {
  color: #ea580c;
  font-weight: 700;
  font-size: 11px;
}

/* Tutanak Kartı */
.ihbar-cards-stack {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.ihbar-card {
  padding: 14px;
  border-radius: 20px;
  cursor: pointer;
  border: 1px solid rgba(255, 255, 255, 0.9);
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.04);
}

.ihbar-card-top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 8px;
}

.ihbar-location-wrap {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
}

.location-icon-bubble {
  width: 32px;
  height: 32px;
  border-radius: 10px;
  background: #fee2e2;
  color: #dc2626;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.location-title {
  font-size: 14px;
  font-weight: 800;
  color: #0f172a;
  margin: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.location-sub {
  color: #64748b;
  font-weight: 600;
}

.status-badge {
  padding: 4px 9px;
  border-radius: 999px;
  font-size: 10px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  white-space: nowrap;
  flex-shrink: 0;
}

.badge--slate {
  background: #f1f5f9;
  color: #475569;
}

.badge--amber {
  background: #fef3c7;
  color: #b45309;
}

.badge--emerald {
  background: #d1fae5;
  color: #047857;
}

.badge--rose {
  background: #ffe4e6;
  color: #be123c;
}

/* Tür Rozetleri */
.badge-tur {
  display: inline-flex;
  align-items: center;
  padding: 2px 7px;
  border-radius: 8px;
  font-size: 10px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}
.badge-tur--kacak {
  background: #fee2e2;
  color: #dc2626;
}
.badge-tur--abonesiz {
  background: #ffedd5;
  color: #ea580c;
}
.badge-tur--usulsuz {
  background: #f3e8ff;
  color: #9333ea;
}
.badge-tur--pasif {
  background: #e0f2fe;
  color: #0284c7;
}
.badge-tur--default {
  background: #f1f5f9;
  color: #475569;
}

.ihbar-card-desc {
  font-size: 12px;
  color: #475569;
  line-height: 1.4;
  margin: 0 0 10px 0;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.ihbar-card-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-bottom: 10px;
}

.mini-tag {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 2px 7px;
  border-radius: 8px;
  font-size: 10px;
  font-weight: 700;
  background: #f1f5f9;
  color: #475569;
}

.mini-tag--foto {
  background: #fdf2f8;
  color: #be185d;
}

.mini-tag--download {
  background: #ecfdf5;
  color: #047857;
  text-decoration: none;
}

.ihbar-card-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-top: 8px;
  border-top: 1px solid #f1f5f9;
  font-size: 11px;
  color: #94a3b8;
}

.footer-left {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
}

.footer-item {
  display: flex;
  align-items: center;
  gap: 4px;
  color: #64748b;
  font-weight: 600;
  max-width: 220px;
}

.footer-date {
  font-size: 10px;
  color: #94a3b8;
  white-space: nowrap;
}

/* Durum Kartı (Boş & Yükleme) */
.ihbar-state-card {
  padding: 40px 16px;
  text-align: center;
  border-radius: 20px;
  background: #fff;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  color: #64748b;
}

.ihbar-state-card strong {
  color: #1e293b;
  font-size: 15px;
}

.ihbar-state-card p {
  font-size: 12px;
  margin: 0;
}

.loading-spinner {
  width: 22px;
  height: 22px;
  border: 2px solid #e2e8f0;
  border-top-color: #ea580c;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

/* Drawer & Modal Sheet */
.ihbar-drawer-backdrop,
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  backdrop-filter: blur(2px);
  z-index: 60;
}

.ihbar-drawer-sheet,
.ihbar-stats-sheet,
.modal-dialog-sheet {
  position: fixed;
  left: 0;
  right: 0;
  bottom: 0;
  background: #fff;
  border-radius: 28px 28px 0 0;
  z-index: 61;
  max-height: 88vh;
  display: flex;
  flex-direction: column;
  transform: translateY(100%);
  transition: transform 0.28s cubic-bezier(0.16, 1, 0.3, 1);
  box-shadow: 0 -10px 40px rgba(0, 0, 0, 0.2);
  padding-bottom: env(safe-area-inset-bottom, 20px);
}

.ihbar-drawer-sheet.is-open,
.ihbar-stats-sheet.is-open,
.modal-dialog-sheet.is-open {
  transform: translateY(0);
}

.drawer-drag-handle {
  width: 40px;
  height: 4px;
  background: #cbd5e1;
  border-radius: 999px;
  margin: 8px auto 4px;
}

.drawer-header,
.modal-head,
.stats-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 18px;
  border-bottom: 1px solid #f1f5f9;
}

.drawer-eyebrow {
  display: block;
  font-size: 9px;
  font-weight: 800;
  letter-spacing: 0.1em;
  color: #ea580c;
}

.drawer-title,
.modal-title,
.stats-title {
  font-size: 16px;
  font-weight: 800;
  color: #0f172a;
  margin: 0;
}

.modal-subtitle,
.stats-subtitle {
  font-size: 11px;
  color: #94a3b8;
  margin: 2px 0 0 0;
}

.drawer-body,
.stats-body {
  padding: 16px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

/* Detay Satırları */
.detail-info-bento {
  padding: 14px;
  border-radius: 18px;
  border: 1px solid #f1f5f9;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 13px;
}

.detail-row--col {
  flex-direction: column;
  align-items: flex-start;
  gap: 4px;
}

.detail-label {
  font-size: 11px;
  font-weight: 700;
  color: #94a3b8;
  text-transform: uppercase;
}

.detail-val {
  color: #1e293b;
}

.detail-reason-box {
  background: #fff1f2;
  border: 1px solid #fecdd3;
  padding: 10px 12px;
  border-radius: 14px;
}

.detail-reason-box--slate {
  background: #f8fafc;
  border-color: #cbd5e1;
}

.detail-actions-grid {
  display: flex;
  gap: 8px;
}

.detail-action-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 12px;
  border-radius: 14px;
  font-size: 12px;
  font-weight: 700;
  border: none;
  cursor: pointer;
  text-decoration: none;
}

.detail-action-btn--primary {
  background: #ea580c;
  color: #fff;
}

.detail-action-btn--secondary {
  background: #ecfdf5;
  color: #047857;
  border: 1px solid #a7f3d0;
  width: 48px;
}

/* Toast Bildirimi */
.toast-message {
  position: fixed;
  bottom: 24px;
  left: 50%;
  transform: translateX(-50%);
  background: #0f172a;
  color: #fff;
  padding: 10px 18px;
  border-radius: 999px;
  font-size: 13px;
  font-weight: 600;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
  display: flex;
  align-items: center;
  gap: 8px;
  z-index: 150;
}

.toast--error {
  background: #dc2626;
}

.toast--warning {
  background: #d97706;
}

/* Form Elemanları */
.form-label {
  display: block;
  font-size: 11px;
  font-weight: 700;
  color: #475569;
  margin-bottom: 4px;
}

.form-textarea {
  width: 100%;
  padding: 10px 12px;
  border-radius: 12px;
  border: 1px solid #cbd5e1;
  font-size: 12px;
  color: #1e293b;
  outline: none;
}

.form-textarea:focus {
  border-color: #ea580c;
}

/* Animasyonlar */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
