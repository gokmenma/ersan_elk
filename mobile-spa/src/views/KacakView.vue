<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import {
  AlertCircle,
  ArrowLeft,
  Ban,
  Calendar,
  Camera,
  Check,
  CheckCircle2,
  ChevronLeft,
  ChevronRight,
  Clock,
  Download,
  Eye,
  FileDown,
  FileText,
  Gauge,
  MapPin,
  RefreshCw,
  RotateCcw,
  Search,
  ShieldAlert,
  SlidersHorizontal,
  Trophy,
  User,
  Users,
  X,
  XCircle,
} from '@lucide/vue'
import TopBar from '../components/TopBar.vue'
import { auth } from '../services/auth.js'
import { kacakApi, kacakState, setKacakTab } from '../services/kacak.js'

// Durum değişkenleri
const loading = ref(true)
const refreshing = ref(false)
const error = ref('')
const toastMessage = ref('')
const toastType = ref('success') // 'success' | 'error' | 'warning'
let toastTimeout = null

function showToast(msg, type = 'success') {
  toastMessage.value = msg
  toastType.value = type
  if (toastTimeout) clearTimeout(toastTimeout)
  toastTimeout = setTimeout(() => {
    toastMessage.value = ''
  }, 3500)
}

// Filtreler
const activeStatus = ref('all') // 'all' | 'beklemede' | 'onaylandi' | 'reddedildi' | 'iptal'
const activeTur = ref('all') // 'all' | 'Kaçak' | 'Abonesiz' | 'Usülsüz' | 'Pasif Abone'
const searchQuery = ref('')
const startDate = ref(kacakState.startDate)
const endDate = ref(kacakState.endDate)
const showDateFilter = ref(false)
const showLeaderboardSheet = ref(false)

// Veriler
const allRecords = ref([])
const counts = ref({ toplam: 0, beklemede: 0, onaylandi: 0, reddedildi: 0, iptal: 0 })
const leaderboard = ref([])
const permissions = ref({ can_approve: false, can_cancel: false, can_revert_cancel: false })

// Detay Modal
const selectedRecord = ref(null)
const isDetailOpen = ref(false)

// Galeri & Lightbox
const isGalleryOpen = ref(false)
const galleryPhotos = ref([])
const galleryLoading = ref(false)
const galleryRecordToken = ref('')
const isLightboxOpen = ref(false)
const lightboxIndex = ref(0)

// Aksiyon Modalı (Onayla / Reddet / İptal / Geri Al)
const actionModal = ref({
  open: false,
  type: '', // 'approve' | 'reject' | 'cancel' | 'revert_cancel'
  title: '',
  text: '',
  reason: '',
  hakedistenDus: false,
  submitting: false,
  token: '',
})

// Hızlı Tarih Seçenekleri
function applyQuickDate(type) {
  const now = new Date()
  const format = (d) => d.toISOString().slice(0, 10)

  if (type === '30days') {
    endDate.value = format(now)
    startDate.value = format(new Date(now.getTime() - 30 * 24 * 60 * 60 * 1000))
  } else if (type === 'thisYear') {
    endDate.value = format(now)
    startDate.value = `${now.getFullYear()}-01-01`
  } else if (type === '1year') {
    endDate.value = format(now)
    startDate.value = format(new Date(now.getTime() - 365 * 24 * 60 * 60 * 1000))
  } else if (type === 'all') {
    startDate.value = '2020-01-01'
    endDate.value = format(now)
  }
  showDateFilter.value = false
  loadRecords()
}

// Kayıtları Yükle
async function loadRecords() {
  loading.value = true
  error.value = ''
  try {
    const res = await kacakApi.list({
      baslangic: startDate.value,
      bitis: endDate.value,
    })
    allRecords.value = res.data.records || []
    counts.value = res.data.counts || { toplam: 0, beklemede: 0, onaylandi: 0, reddedildi: 0, iptal: 0 }
    leaderboard.value = res.data.leaderboard || []
    permissions.value = res.data.permissions || { can_approve: false, can_cancel: false, can_revert_cancel: false }

    kacakState.counts = counts.value
    kacakState.leaderboard = leaderboard.value
    kacakState.permissions = permissions.value
  } catch (err) {
    error.value = err.message || 'Kayıtlar yüklenirken bir hata oluştu.'
  } finally {
    loading.value = false
    refreshing.value = false
  }
}

async function handleRefresh() {
  refreshing.value = true
  await loadRecords()
}

// Filtrelenmiş Liste
const filteredRecords = computed(() => {
  const q = searchQuery.value.trim().toLocaleLowerCase('tr-TR')
  const status = activeStatus.value
  const tur = activeTur.value

  return allRecords.value.filter((item) => {
    // Durum filtresi
    if (status === 'beklemede' && (item.durum === 'iptal' || item.onay_durumu !== 'beklemede')) return false
    if (status === 'onaylandi' && (item.durum === 'iptal' || item.onay_durumu !== 'onaylandi')) return false
    if (status === 'reddedildi' && (item.durum === 'iptal' || item.onay_durumu !== 'reddedildi')) return false
    if (status === 'iptal' && item.durum !== 'iptal') return false

    // Tür filtresi
    if (tur !== 'all' && item.tur !== tur) return false

    // Arama filtresi
    if (q) {
      const searchBlob = `${item.tutanak_no} ${item.abone_adi} ${item.sayac_no} ${item.ekip_adi} ${item.bildiren_adi} ${item.ilce} ${item.tur}`.toLocaleLowerCase('tr-TR')
      if (!searchBlob.includes(q)) return false
    }

    return true
  })
})

// Detay Aç
function openDetail(record) {
  selectedRecord.value = record
  isDetailOpen.value = true
}

function closeDetail() {
  isDetailOpen.value = false
  setTimeout(() => {
    selectedRecord.value = null
  }, 250)
}

// Galeri Aç
async function openGallery(token) {
  galleryRecordToken.value = token
  galleryLoading.value = true
  galleryPhotos.value = []
  isGalleryOpen.value = true

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
  isGalleryOpen.value = false
  galleryPhotos.value = []
}

// Lightbox
const imageOnlyPhotos = computed(() => galleryPhotos.value.filter((p) => !p.pdf))

function openLightbox(photo) {
  const idx = imageOnlyPhotos.value.findIndex((p) => p.token === photo.token)
  if (idx !== -1) {
    lightboxIndex.value = idx
    isLightboxOpen.value = true
  }
}

function closeLightbox() {
  isLightboxOpen.value = false
}

function stepLightbox(step) {
  const len = imageOnlyPhotos.value.length
  if (len === 0) return
  lightboxIndex.value = (lightboxIndex.value + step + len) % len
}

// Aksiyon Diyalogu Başlat
function promptAction(type, record) {
  const token = record.token
  actionModal.value = {
    open: true,
    type,
    token,
    title: '',
    text: '',
    reason: '',
    hakedistenDus: false,
    submitting: false,
  }

  if (type === 'approve') {
    actionModal.value.title = 'Bildirimi Onayla'
    actionModal.value.text = `${record.tutanak_no || 'Bu'} kaçak tutanağını onaylamak üzeresiniz. İşlem onaylanıp sisteme işlenecektir.`
  } else if (type === 'reject') {
    actionModal.value.title = 'Bildirimi Reddet'
    actionModal.value.text = 'Lütfen bildirimin reddedilme gerekçesini belirtin.'
  } else if (type === 'cancel') {
    actionModal.value.title = 'Tutanağı İptal Et'
    actionModal.value.text = 'Bu tutanak iptal edilecek. Lütfen iptal nedenini girin.'
    actionModal.value.hakedistenDus = false
  } else if (type === 'revert_cancel') {
    actionModal.value.title = 'İptali Geri Al'
    actionModal.value.text = 'İptal edilmiş bu tutanak tekrar aktif duruma getirilecek ve listelere dahil edilecektir.'
  }
}

function closeActionModal() {
  actionModal.value.open = false
}

// Aksiyon Gönder
async function submitAction() {
  const { type, token, reason, hakedistenDus } = actionModal.value
  const csrfToken = auth.state.csrf_token

  if ((type === 'reject' || type === 'cancel') && !reason.trim()) {
    showToast('İşlem nedeni yazılması zorunludur.', 'warning')
    return
  }

  actionModal.value.submitting = true
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

    showToast(res.message || 'İşlem başarıyla tamamlandı.', 'success')
    closeActionModal()
    closeDetail()
    await loadRecords()
  } catch (err) {
    showToast(err.message || 'İşlem gerçekleştirilemedi.', 'error')
  } finally {
    actionModal.value.submitting = false
  }
}

// Tür rozeti renkleri
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

// Durum rozeti metni ve stili
function getStatusBadge(item) {
  if (item.durum === 'iptal') {
    return { label: 'İptal', class: 'status-badge--cancelled' }
  }
  switch (item.onay_durumu) {
    case 'onaylandi':
      return { label: 'Onaylandı', class: 'status-badge--approved' }
    case 'reddedildi':
      return { label: 'Reddedildi', class: 'status-badge--rejected' }
    default:
      return { label: 'Beklemede', class: 'status-badge--pending' }
  }
}

// İlk yükleme
onMounted(() => {
  loadRecords()
})
</script>

<template>
  <div class="kacak-page ds-screen">
    <!-- Üst Başlık Barı -->
    <TopBar eyebrow="Saha İşlemleri & Denetim" subtitle="Kaçak ve abonesiz tutanak kontrolü">
      <template #actions>
        <button
          type="button"
          class="clay-circle-btn"
          title="Ekip Başarı Sıralaması"
          aria-label="Ekip Başarı Sıralaması"
          @click="showLeaderboardSheet = true"
        >
          <Trophy :size="20" class="text-amber-500" />
        </button>
        <button
          type="button"
          class="clay-circle-btn"
          :class="{ 'animate-spin': refreshing }"
          title="Yenile"
          aria-label="Yenile"
          @click="handleRefresh"
        >
          <RefreshCw :size="19" />
        </button>
      </template>
    </TopBar>

    <!-- Hızlı Tarih / Dönem Seçici Çubuğu -->
    <section class="kacak-date-bar">
      <div class="kacak-date-info">
        <Calendar :size="15" class="text-slate-400" />
        <span class="kacak-date-text">{{ startDate }} &ndash; {{ endDate }}</span>
      </div>
      <div class="kacak-quick-dates">
        <button
          type="button"
          class="kacak-date-chip"
          @click="applyQuickDate('30days')"
        >
          Son 30 Gün
        </button>
        <button
          type="button"
          class="kacak-date-chip"
          @click="applyQuickDate('thisYear')"
        >
          Bu Yıl
        </button>
        <button
          type="button"
          class="kacak-date-chip"
          @click="applyQuickDate('1year')"
        >
          Son 1 Yıl
        </button>
        <button
          type="button"
          class="kacak-date-chip"
          @click="applyQuickDate('all')"
        >
          Tümü
        </button>
        <button
          type="button"
          class="kacak-date-chip kacak-date-chip--icon"
          title="Özel Tarih Seç"
          @click="showDateFilter = !showDateFilter"
        >
          <SlidersHorizontal :size="14" />
        </button>
      </div>
    </section>

    <!-- Açılır Özel Tarih Formu -->
    <Transition name="fade">
      <section v-if="showDateFilter" class="kacak-custom-date-card bento-card">
        <div class="kacak-date-inputs">
          <label class="kacak-input-group">
            <span>Başlangıç</span>
            <input v-model="startDate" type="date" class="kacak-input" />
          </label>
          <label class="kacak-input-group">
            <span>Bitiş</span>
            <input v-model="endDate" type="date" class="kacak-input" />
          </label>
        </div>
        <div class="kacak-date-actions">
          <button type="button" class="clay-btn-secondary" @click="showDateFilter = false">
            Vazgeç
          </button>
          <button type="button" class="clay-btn-primary" @click="loadRecords">
            Tarihi Uygula
          </button>
        </div>
      </section>
    </Transition>

    <!-- 4 Adet Minimal Bento KPI Kartı -->
    <section class="kacak-kpi-grid">
      <div
        class="bento-card kpi-card"
        :class="{ 'kpi-card--active': activeStatus === 'all' }"
        @click="activeStatus = 'all'"
      >
        <div class="kpi-icon-wrap kpi-icon-wrap--primary">
          <ShieldAlert :size="20" />
        </div>
        <div class="kpi-body">
          <span class="kpi-label">Toplam Tutanak</span>
          <strong class="kpi-val">{{ counts.toplam }}</strong>
        </div>
      </div>

      <div
        class="bento-card kpi-card"
        :class="{ 'kpi-card--active': activeStatus === 'beklemede' }"
        @click="activeStatus = 'beklemede'"
      >
        <div class="kpi-icon-wrap kpi-icon-wrap--warning">
          <Clock :size="20" />
        </div>
        <div class="kpi-body">
          <span class="kpi-label">Bekleyen</span>
          <strong class="kpi-val text-amber-500">{{ counts.beklemede }}</strong>
        </div>
      </div>

      <div
        class="bento-card kpi-card"
        :class="{ 'kpi-card--active': activeStatus === 'onaylandi' }"
        @click="activeStatus = 'onaylandi'"
      >
        <div class="kpi-icon-wrap kpi-icon-wrap--success">
          <CheckCircle2 :size="20" />
        </div>
        <div class="kpi-body">
          <span class="kpi-label">Onaylanan</span>
          <strong class="kpi-val text-emerald-500">{{ counts.onaylandi }}</strong>
        </div>
      </div>

      <div
        class="bento-card kpi-card"
        :class="{ 'kpi-card--active': activeStatus === 'iptal' }"
        @click="activeStatus = 'iptal'"
      >
        <div class="kpi-icon-wrap kpi-icon-wrap--danger">
          <Ban :size="20" />
        </div>
        <div class="kpi-body">
          <span class="kpi-label">İptal Edilen</span>
          <strong class="kpi-val text-slate-400">{{ counts.iptal }}</strong>
        </div>
      </div>
    </section>

    <!-- Arama ve Canlı Filtreler -->
    <section class="kacak-search-section">
      <div class="kacak-search-wrap">
        <Search :size="18" class="kacak-search-icon" />
        <input
          v-model="searchQuery"
          type="search"
          placeholder="Tutanak no, sayaç, abone, ilçe veya ekip ara..."
          class="kacak-search-input"
        />
        <button
          v-if="searchQuery"
          type="button"
          class="kacak-search-clear"
          @click="searchQuery = ''"
        >
          <X :size="16" />
        </button>
      </div>

      <!-- Durum Sekmeleri (Clay Tabs) -->
      <div class="clay-tabs-wrap kacak-tabs">
        <button
          type="button"
          class="clay-filter-pill"
          :class="{ 'clay-filter-pill--active': activeStatus === 'all' }"
          @click="activeStatus = 'all'"
        >
          Tümü <small>({{ counts.toplam }})</small>
        </button>
        <button
          type="button"
          class="clay-filter-pill"
          :class="{ 'clay-filter-pill--active': activeStatus === 'beklemede' }"
          @click="activeStatus = 'beklemede'"
        >
          Bekleyen <small>({{ counts.beklemede }})</small>
        </button>
        <button
          type="button"
          class="clay-filter-pill"
          :class="{ 'clay-filter-pill--active': activeStatus === 'onaylandi' }"
          @click="activeStatus = 'onaylandi'"
        >
          Onaylı <small>({{ counts.onaylandi }})</small>
        </button>
        <button
          type="button"
          class="clay-filter-pill"
          :class="{ 'clay-filter-pill--active': activeStatus === 'reddedildi' }"
          @click="activeStatus = 'reddedildi'"
        >
          Reddedilen <small>({{ counts.reddedildi }})</small>
        </button>
        <button
          type="button"
          class="clay-filter-pill"
          :class="{ 'clay-filter-pill--active': activeStatus === 'iptal' }"
          @click="activeStatus = 'iptal'"
        >
          İptal <small>({{ counts.iptal }})</small>
        </button>
      </div>

      <!-- Tür Filtresi (Haplar) -->
      <div class="kacak-tur-chips">
        <button
          type="button"
          class="tur-chip"
          :class="{ 'tur-chip--active': activeTur === 'all' }"
          @click="activeTur = 'all'"
        >
          Tüm Türler
        </button>
        <button
          type="button"
          class="tur-chip"
          :class="{ 'tur-chip--active': activeTur === 'Kaçak' }"
          @click="activeTur = 'Kaçak'"
        >
          Kaçak
        </button>
        <button
          type="button"
          class="tur-chip"
          :class="{ 'tur-chip--active': activeTur === 'Abonesiz' }"
          @click="activeTur = 'Abonesiz'"
        >
          Abonesiz
        </button>
        <button
          type="button"
          class="tur-chip"
          :class="{ 'tur-chip--active': activeTur === 'Usülsüz' }"
          @click="activeTur = 'Usülsüz'"
        >
          Usülsüz
        </button>
        <button
          type="button"
          class="tur-chip"
          :class="{ 'tur-chip--active': activeTur === 'Pasif Abone' }"
          @click="activeTur = 'Pasif Abone'"
        >
          Pasif Abone
        </button>
      </div>
    </section>

    <!-- Kayıt Listesi / Boş Durum / Yükleniyor -->
    <section class="kacak-list-section">
      <!-- Yükleniyor İskelet Görünümü -->
      <div v-if="loading" class="kacak-skeletons">
        <div v-for="i in 4" :key="i" class="bento-card skeleton-card">
          <div class="skeleton-line skeleton-line--sm"></div>
          <div class="skeleton-line skeleton-line--md"></div>
          <div class="skeleton-line skeleton-line--lg"></div>
        </div>
      </div>

      <!-- Hata Durumu -->
      <div v-else-if="error" class="bento-card kacak-error-card">
        <AlertCircle :size="32" class="text-rose-500" />
        <strong>Veriler Alınamadı</strong>
        <p>{{ error }}</p>
        <button type="button" class="clay-btn-primary" @click="loadRecords">
          Tekrar Dene
        </button>
      </div>

      <!-- Boş Durum -->
      <div v-else-if="filteredRecords.length === 0" class="bento-card kacak-empty-card">
        <Search :size="40" class="text-slate-300" />
        <strong>Eşleşen Tutanak Bulunamadı</strong>
        <p>Arama veya tarih kriterlerinizi değiştirerek tekrar deneyebilirsiniz.</p>
        <button
          v-if="searchQuery || activeStatus !== 'all' || activeTur !== 'all'"
          type="button"
          class="clay-btn-secondary"
          @click="searchQuery = ''; activeStatus = 'all'; activeTur = 'all'"
        >
          Filtreleri Sıfırla
        </button>
      </div>

      <!-- Tutanak Kartları Listesi -->
      <div v-else class="kacak-cards-grid">
        <article
          v-for="record in filteredRecords"
          :key="record.token"
          class="bento-card kacak-record-card"
          @click="openDetail(record)"
        >
          <!-- Kart Üst Barı: Tür, Tarih, Durum Rozeti -->
          <div class="record-card-header">
            <div class="record-meta-left">
              <span class="badge-tur" :class="getTurBadgeClass(record.tur)">
                {{ record.tur }}
              </span>
              <span class="record-date">{{ record.tarih_formatted }}</span>
            </div>
            <span class="status-badge" :class="getStatusBadge(record).class">
              {{ getStatusBadge(record).label }}
            </span>
          </div>

          <!-- Tutanak & Abone Bilgisi -->
          <div class="record-card-main">
            <h3 class="record-tutanak-no">
              {{ record.tutanak_no || (record.abone_adi || 'Tutanak No Yok') }}
            </h3>
            <p v-if="record.abone_adi && record.tutanak_no" class="record-abone">
              {{ record.abone_adi }}
            </p>
            <div class="record-location-team">
              <span class="record-location">
                <MapPin :size="13" />
                {{ record.ilce }}
              </span>
              <span class="record-dot">&bull;</span>
              <span class="record-team truncate">
                <Users :size="13" />
                {{ record.ekip_adi || record.bildiren_adi || 'Ekip Belirtilmemiş' }}
              </span>
            </div>
          </div>

          <!-- Kart Alt Bilgi Çubuğu (Sayaç, Fotoğraf, Hızlı ZIP İndir) -->
          <div class="record-card-footer" @click.stop>
            <div class="record-footer-meta">
              <span v-if="record.sayac_no" class="record-footer-item" title="Sayaç No">
                <Gauge :size="14" />
                {{ record.sayac_no }}
              </span>
              <span class="record-footer-item" title="Fotoğraf Sayısı">
                <Camera :size="14" />
                {{ record.foto_sayisi }} Fotoğraf
              </span>
            </div>

            <!-- ZIP İndir Kısayolu -->
            <div class="record-footer-actions">
              <a
                v-if="record.foto_sayisi > 0"
                :href="`/views/kacak/api.php?action=download-zip&token=${encodeURIComponent(record.token)}`"
                target="_blank"
                class="record-action-icon-btn record-action-icon-btn--download"
                title="Fotoğrafları ZIP İndir"
                aria-label="Fotoğrafları ZIP İndir"
              >
                <Download :size="16" />
              </a>
              <button
                type="button"
                class="record-action-icon-btn"
                title="Detay Görüntüle"
                @click.stop="openDetail(record)"
              >
                <ChevronRight :size="16" />
              </button>
            </div>
          </div>
        </article>
      </div>
    </section>

    <!-- Ekip Başarı Sıralaması (Leaderboard) Drawer -->
    <Transition name="sheet">
      <div
        v-if="showLeaderboardSheet"
        class="kacak-modal-backdrop"
        @click.self="showLeaderboardSheet = false"
      >
        <div class="kacak-bottom-sheet">
          <div class="sheet-handle-bar"></div>
          <header class="sheet-header">
            <div>
              <div class="sheet-eyebrow">PERFORMANS VE SIRALAMA</div>
              <h2 class="sheet-title">Ekip Kaçak Kontrol Özeti</h2>
              <p class="sheet-subtitle">{{ startDate }} &ndash; {{ endDate }}</p>
            </div>
            <button type="button" class="sheet-close-btn" @click="showLeaderboardSheet = false">
              <X :size="20" />
            </button>
          </header>

          <div class="sheet-content">
            <div v-if="leaderboard.length === 0" class="leaderboard-empty">
              <p>Seçili dönemde ekip kaydı bulunamadı.</p>
            </div>
            <div v-else class="leaderboard-list">
              <div
                v-for="(team, index) in leaderboard"
                :key="team.name"
                class="leaderboard-item bento-card"
              >
                <div class="leaderboard-top">
                  <div class="leaderboard-rank-wrap">
                    <span
                      class="leaderboard-rank"
                      :class="{
                        'leaderboard-rank--first': team.rank === 1,
                        'leaderboard-rank--second': team.rank === 2,
                        'leaderboard-rank--third': team.rank === 3,
                      }"
                    >
                      <Trophy v-if="team.rank === 1" :size="14" />
                      <span v-else>{{ team.rank }}</span>
                    </span>
                    <strong class="leaderboard-name">{{ team.name }}</strong>
                  </div>
                  <span class="leaderboard-count">{{ team.count }} Tutanak</span>
                </div>
                <div class="leaderboard-track">
                  <div
                    class="leaderboard-fill"
                    :style="{
                      width: `${team.percentage}%`,
                      backgroundColor:
                        index === 0
                          ? '#ef4444'
                          : index === 1
                          ? '#f59e0b'
                          : index === 2
                          ? '#10b981'
                          : index === 3
                          ? '#3b82f6'
                          : '#8b5cf6',
                    }"
                  ></div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Kaçak Tutanak Detay Modalı (Bottom Sheet) -->
    <Transition name="sheet">
      <div v-if="isDetailOpen && selectedRecord" class="kacak-modal-backdrop" @click.self="closeDetail">
        <div class="kacak-bottom-sheet">
          <div class="sheet-handle-bar"></div>
          <header class="sheet-header">
            <div>
              <div class="sheet-eyebrow">TUTANAK DETAYI</div>
              <h2 class="sheet-title">
                {{ selectedRecord.tutanak_no || selectedRecord.abone_adi || 'Tutanak Detayı' }}
              </h2>
            </div>
            <button type="button" class="sheet-close-btn" @click="closeDetail">
              <X :size="20" />
            </button>
          </header>

          <div class="sheet-content detail-content">
            <!-- Durum & Tür Şeridi -->
            <div class="detail-status-strip">
              <span class="badge-tur" :class="getTurBadgeClass(selectedRecord.tur)">
                {{ selectedRecord.tur }}
              </span>
              <span class="status-badge" :class="getStatusBadge(selectedRecord).class">
                {{ getStatusBadge(selectedRecord).label }}
              </span>
            </div>

            <!-- Bilgi Izgarası (Bento Grid) -->
            <div class="detail-grid">
              <div class="detail-cell">
                <span class="detail-cell-label">Tutanak No</span>
                <strong class="detail-cell-val">{{ selectedRecord.tutanak_no || '-' }}</strong>
              </div>
              <div class="detail-cell">
                <span class="detail-cell-label">Tarih</span>
                <strong class="detail-cell-val">{{ selectedRecord.tarih_formatted }}</strong>
              </div>
              <div class="detail-cell">
                <span class="detail-cell-label">Abone Adı</span>
                <strong class="detail-cell-val">{{ selectedRecord.abone_adi || '-' }}</strong>
              </div>
              <div class="detail-cell">
                <span class="detail-cell-label">İlçe</span>
                <strong class="detail-cell-val">{{ selectedRecord.ilce || '-' }}</strong>
              </div>
              <div class="detail-cell">
                <span class="detail-cell-label">Sayaç No</span>
                <strong class="detail-cell-val">{{ selectedRecord.sayac_no || '-' }}</strong>
              </div>
              <div class="detail-cell">
                <span class="detail-cell-label">Endeks</span>
                <strong class="detail-cell-val">{{ selectedRecord.endeks || '-' }}</strong>
              </div>
              <div class="detail-cell col-span-2">
                <span class="detail-cell-label">Ekip / Bildiren Personel</span>
                <strong class="detail-cell-val">
                  {{ selectedRecord.ekip_adi || selectedRecord.bildiren_adi || '-' }}
                </strong>
              </div>
              <div v-if="selectedRecord.aciklama" class="detail-cell col-span-2">
                <span class="detail-cell-label">Açıklama</span>
                <p class="detail-cell-text">{{ selectedRecord.aciklama }}</p>
              </div>
            </div>

            <!-- Varsa Red Nedeni -->
            <div v-if="selectedRecord.red_nedeni" class="detail-warning-box">
              <AlertCircle :size="18" class="text-rose-600 shrink-0" />
              <div>
                <strong>Red Nedeni</strong>
                <p>{{ selectedRecord.red_nedeni }}</p>
              </div>
            </div>

            <!-- Varsa İptal Bilgisi -->
            <div v-if="selectedRecord.durum === 'iptal'" class="detail-warning-box detail-warning-box--slate">
              <Ban :size="18" class="text-slate-600 shrink-0" />
              <div>
                <strong>İptal Açıklaması</strong>
                <p>{{ selectedRecord.iptal_aciklama || 'Açıklama belirtilmemiş.' }}</p>
                <span class="text-xs text-slate-500 mt-1 block">
                  {{ selectedRecord.hakedisten_dus ? '⚠️ Hakediş hesabından düşüldü.' : 'ℹ️ Hakediş hesabından düşülmedi.' }}
                </span>
              </div>
            </div>

            <!-- Fotoğraf Aksiyon Butonları -->
            <div class="detail-photos-bar">
              <button
                type="button"
                class="detail-gallery-btn"
                :disabled="selectedRecord.foto_sayisi === 0"
                @click="openGallery(selectedRecord.token)"
              >
                <Camera :size="18" />
                <span>Fotoğrafları İncele ({{ selectedRecord.foto_sayisi }})</span>
              </button>
              <a
                v-if="selectedRecord.foto_sayisi > 0"
                :href="`/views/kacak/api.php?action=download-zip&token=${encodeURIComponent(selectedRecord.token)}`"
                target="_blank"
                class="detail-zip-btn"
                title="ZIP İndir"
              >
                <Download :size="18" />
              </a>
            </div>

            <!-- Yetki Bazlı Eylemler (Onayla / Reddet / İptal / Geri Al) -->
            <div class="detail-actions">
              <!-- Bekleyen Onayı -->
              <template v-if="permissions.can_approve && selectedRecord.onay_durumu === 'beklemede' && selectedRecord.durum !== 'iptal'">
                <button
                  type="button"
                  class="clay-btn-success"
                  @click="promptAction('approve', selectedRecord)"
                >
                  <Check :size="18" />
                  Bildirimi Onayla
                </button>
                <button
                  type="button"
                  class="clay-btn-warning"
                  @click="promptAction('reject', selectedRecord)"
                >
                  <XCircle :size="18" />
                  Bildirimi Reddet
                </button>
              </template>

              <!-- İptali Geri Al -->
              <button
                v-if="permissions.can_revert_cancel && selectedRecord.durum === 'iptal'"
                type="button"
                class="clay-btn-info"
                @click="promptAction('revert_cancel', selectedRecord)"
              >
                <RotateCcw :size="18" />
                İptali Geri Al
              </button>

              <!-- İptal Et -->
              <button
                v-if="permissions.can_cancel && selectedRecord.durum !== 'iptal'"
                type="button"
                class="clay-btn-danger"
                @click="promptAction('cancel', selectedRecord)"
              >
                <Ban :size="18" />
                Tutanağı İptal Et
              </button>
            </div>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Fotoğraf Galerisi Modalı -->
    <Transition name="fade">
      <div v-if="isGalleryOpen" class="kacak-gallery-modal">
        <header class="gallery-header">
          <div class="gallery-title-wrap">
            <button type="button" class="gallery-back-btn" @click="closeGallery">
              <ArrowLeft :size="20" />
            </button>
            <div>
              <h2 class="gallery-title">Tutanak Fotoğrafları</h2>
              <span class="gallery-count">{{ galleryPhotos.length }} Dosya Bulundu</span>
            </div>
          </div>
          <button type="button" class="gallery-close-btn" @click="closeGallery">
            <X :size="20" />
          </button>
        </header>

        <div class="gallery-body">
          <div v-if="galleryLoading" class="gallery-loading">
            <RefreshCw :size="32" class="animate-spin text-white/70" />
            <p>Fotoğraflar yükleniyor...</p>
          </div>
          <div v-else-if="galleryPhotos.length === 0" class="gallery-empty">
            <Camera :size="48" class="text-white/40" />
            <p>Görüntülenebilir fotoğraf bulunamadı.</p>
          </div>
          <div v-else class="gallery-grid">
            <div
              v-for="photo in galleryPhotos"
              :key="photo.token"
              class="gallery-item"
            >
              <!-- PDF Kartı -->
              <a
                v-if="photo.pdf"
                :href="photo.full_url"
                target="_blank"
                class="gallery-pdf-card"
              >
                <FileText :size="40" class="text-rose-400" />
                <span class="pdf-name truncate">{{ photo.ad }}</span>
                <span class="pdf-tag">PDF Belgesi</span>
              </a>

              <!-- Resim Kartı -->
              <button
                v-else
                type="button"
                class="gallery-photo-btn"
                @click="openLightbox(photo)"
              >
                <img
                  :src="photo.thumbnail_url"
                  :alt="photo.ad"
                  loading="lazy"
                  class="gallery-photo-img"
                />
                <span class="gallery-tag">
                  {{ photo.tur === 'tutanak' ? 'Tutanak' : 'Saha' }}
                </span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Tam Ekran Lightbox -->
    <Transition name="fade">
      <div
        v-if="isLightboxOpen && imageOnlyPhotos[lightboxIndex]"
        class="kacak-lightbox-modal"
        @click.self="closeLightbox"
      >
        <header class="lightbox-header">
          <span class="lightbox-counter">
            {{ lightboxIndex + 1 }} / {{ imageOnlyPhotos.length }}
          </span>
          <div class="lightbox-actions">
            <a
              :href="imageOnlyPhotos[lightboxIndex].full_url"
              download
              class="lightbox-btn"
              title="İndir"
            >
              <Download :size="20" />
            </a>
            <button type="button" class="lightbox-btn" @click="closeLightbox">
              <X :size="20" />
            </button>
          </div>
        </header>

        <div class="lightbox-image-wrap">
          <img
            :src="imageOnlyPhotos[lightboxIndex].full_url"
            :alt="imageOnlyPhotos[lightboxIndex].ad"
            class="lightbox-img"
          />
        </div>

        <!-- Önceki / Sonraki Butonları -->
        <button
          v-if="imageOnlyPhotos.length > 1"
          type="button"
          class="lightbox-nav-btn lightbox-nav-btn--prev"
          aria-label="Önceki Fotoğraf"
          @click="stepLightbox(-1)"
        >
          <ChevronLeft :size="26" />
        </button>
        <button
          v-if="imageOnlyPhotos.length > 1"
          type="button"
          class="lightbox-nav-btn lightbox-nav-btn--next"
          aria-label="Sonraki Fotoğraf"
          @click="stepLightbox(1)"
        >
          <ChevronRight :size="26" />
        </button>
      </div>
    </Transition>

    <!-- Aksiyon Onay Modalı (Onayla / Reddet / İptal / Geri Al) -->
    <Transition name="sheet">
      <div v-if="actionModal.open" class="kacak-modal-backdrop" @click.self="closeActionModal">
        <div class="kacak-bottom-sheet action-sheet">
          <div class="sheet-handle-bar"></div>
          <header class="sheet-header">
            <div>
              <h2 class="sheet-title">{{ actionModal.title }}</h2>
              <p class="sheet-subtitle">{{ actionModal.text }}</p>
            </div>
            <button type="button" class="sheet-close-btn" @click="closeActionModal">
              <X :size="20" />
            </button>
          </header>

          <div class="sheet-content">
            <!-- Neden / Açıklama Alanı -->
            <div v-if="actionModal.type === 'reject' || actionModal.type === 'cancel'" class="action-input-wrap">
              <label class="action-label">
                {{ actionModal.type === 'reject' ? 'Red Nedeni *' : 'İptal Açıklaması *' }}
              </label>
              <textarea
                v-model="actionModal.reason"
                rows="3"
                class="action-textarea"
                :placeholder="actionModal.type === 'reject' ? 'Lütfen red nedenini yazın...' : 'İptal gerekçesini yazın...'"
              ></textarea>
            </div>

            <!-- Hakedişten Düş Onay Kutusu (İptal için) -->
            <label v-if="actionModal.type === 'cancel'" class="action-checkbox-wrap">
              <input v-model="actionModal.hakedistenDus" type="checkbox" class="action-checkbox" />
              <span>Hakediş hesabından düşülsün</span>
            </label>

            <!-- Butonlar -->
            <div class="action-buttons-grid">
              <button
                type="button"
                class="clay-btn-secondary"
                :disabled="actionModal.submitting"
                @click="closeActionModal"
              >
                Vazgeç
              </button>
              <button
                type="button"
                :class="{
                  'clay-btn-success': actionModal.type === 'approve',
                  'clay-btn-warning': actionModal.type === 'reject',
                  'clay-btn-danger': actionModal.type === 'cancel',
                  'clay-btn-info': actionModal.type === 'revert_cancel',
                }"
                :disabled="actionModal.submitting"
                @click="submitAction"
              >
                <RefreshCw v-if="actionModal.submitting" :size="18" class="animate-spin" />
                <span v-else>{{ actionModal.title }}</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Global Toast Bildirimi -->
    <Transition name="fade">
      <div
        v-if="toastMessage"
        class="kacak-toast"
        :class="`kacak-toast--${toastType}`"
      >
        <CheckCircle2 v-if="toastType === 'success'" :size="18" />
        <AlertCircle v-else :size="18" />
        <span>{{ toastMessage }}</span>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
/* Ana Ekran Konteyneri */
.kacak-page {
  padding-bottom: 96px;
}

/* Tarih Seçim Çubuğu */
.kacak-date-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 16px 12px;
  gap: 8px;
}
.kacak-date-info {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  font-weight: 700;
  color: #64748b;
}
.kacak-quick-dates {
  display: flex;
  align-items: center;
  gap: 6px;
  overflow-x: auto;
}
.kacak-date-chip {
  padding: 4px 10px;
  border-radius: 9999px;
  font-size: 11px;
  font-weight: 700;
  background: #ffffff;
  color: #475569;
  border: 1px solid rgba(0, 0, 0, 0.06);
  white-space: nowrap;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
}
.kacak-date-chip--icon {
  padding: 5px 8px;
}

/* Açılır Özel Tarih Formu */
.kacak-custom-date-card {
  margin: 0 16px 12px;
  padding: 14px;
}
.kacak-date-inputs {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}
.kacak-input-group {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.kacak-input-group span {
  font-size: 11px;
  font-weight: 700;
  color: #64748b;
  text-transform: uppercase;
}
.kacak-input {
  width: 100%;
  padding: 8px 10px;
  border-radius: 12px;
  border: 1px solid #cbd5e1;
  font-size: 13px;
  font-weight: 600;
  color: #1e293b;
  background: #f8fafc;
}
.kacak-date-actions {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
  margin-top: 12px;
}

/* 4 KPI Grid */
.kacak-kpi-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 10px;
  padding: 0 16px 14px;
}
.kpi-card {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  border-radius: 20px;
  cursor: pointer;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.kpi-card:active {
  transform: scale(0.98);
}
.kpi-card--active {
  border-color: rgba(59, 130, 246, 0.4);
  box-shadow: 0 4px 14px rgba(59, 130, 246, 0.12);
}
.kpi-icon-wrap {
  width: 40px;
  height: 40px;
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.kpi-icon-wrap--primary {
  background: #eff6ff;
  color: #3b82f6;
}
.kpi-icon-wrap--warning {
  background: #fffbeb;
  color: #f59e0b;
}
.kpi-icon-wrap--success {
  background: #ecfdf5;
  color: #10b981;
}
.kpi-icon-wrap--danger {
  background: #fef2f2;
  color: #ef4444;
}
.kpi-body {
  display: flex;
  flex-direction: column;
  min-width: 0;
}
.kpi-label {
  font-size: 11px;
  font-weight: 700;
  color: #64748b;
  white-space: nowrap;
}
.kpi-val {
  font-size: 19px;
  font-weight: 800;
  color: #0f172a;
  line-height: 1.15;
}

/* Arama ve Sekmeler */
.kacak-search-section {
  padding: 0 16px 14px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.kacak-search-wrap {
  position: relative;
  display: flex;
  align-items: center;
}
.kacak-search-icon {
  position: absolute;
  left: 12px;
  color: #94a3b8;
  pointer-events: none;
}
.kacak-search-input {
  width: 100%;
  padding: 10px 36px;
  border-radius: 16px;
  border: 1px solid rgba(0, 0, 0, 0.08);
  background: #ffffff;
  font-size: 13px;
  font-weight: 600;
  color: #1e293b;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
}
.kacak-search-clear {
  position: absolute;
  right: 12px;
  width: 22px;
  height: 22px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #e2e8f0;
  color: #475569;
}
.kacak-tabs {
  overflow-x: auto;
  white-space: nowrap;
  scrollbar-width: none;
}
.kacak-tabs::-webkit-scrollbar {
  display: none;
}
.kacak-tur-chips {
  display: flex;
  align-items: center;
  gap: 6px;
  overflow-x: auto;
  scrollbar-width: none;
}
.kacak-tur-chips::-webkit-scrollbar {
  display: none;
}
.tur-chip {
  padding: 4px 10px;
  border-radius: 9999px;
  font-size: 11px;
  font-weight: 700;
  background: #f1f5f9;
  color: #64748b;
  border: 1px solid transparent;
  white-space: nowrap;
  transition: all 0.15s ease;
}
.tur-chip--active {
  background: #0f172a;
  color: #ffffff;
}

/* Kayıt Kartları */
.kacak-list-section {
  padding: 0 16px;
}
.kacak-cards-grid {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.kacak-record-card {
  padding: 14px;
  border-radius: 22px;
  cursor: pointer;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.kacak-record-card:active {
  transform: scale(0.985);
}
.record-card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 8px;
}
.record-meta-left {
  display: flex;
  align-items: center;
  gap: 8px;
}
.record-date {
  font-size: 11px;
  font-weight: 600;
  color: #94a3b8;
}

/* Tür Rozetleri */
.badge-tur {
  display: inline-block;
  padding: 3px 8px;
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

/* Durum Rozetleri */
.status-badge {
  display: inline-block;
  padding: 3px 10px;
  border-radius: 9999px;
  font-size: 11px;
  font-weight: 700;
}
.status-badge--pending {
  background: #fef3c7;
  color: #b45309;
}
.status-badge--approved {
  background: #d1fae5;
  color: #065f46;
}
.status-badge--rejected {
  background: #ffe4e6;
  color: #be123c;
}
.status-badge--cancelled {
  background: #f1f5f9;
  color: #64748b;
}

/* Tutanak İçerik */
.record-card-main {
  margin-bottom: 12px;
}
.record-tutanak-no {
  font-size: 16px;
  font-weight: 800;
  color: #0f172a;
  line-height: 1.25;
  margin: 0;
}
.record-abone {
  font-size: 13px;
  font-weight: 600;
  color: #475569;
  margin: 3px 0 0;
}
.record-location-team {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: #64748b;
  margin-top: 6px;
}
.record-location,
.record-team {
  display: flex;
  align-items: center;
  gap: 4px;
}
.record-dot {
  color: #cbd5e1;
}

/* Kart Alt Bilgi */
.record-card-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-top: 10px;
  border-top: 1px solid #f1f5f9;
}
.record-footer-meta {
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 11px;
  font-weight: 600;
  color: #64748b;
}
.record-footer-item {
  display: flex;
  align-items: center;
  gap: 4px;
}
.record-footer-actions {
  display: flex;
  align-items: center;
  gap: 6px;
}
.record-action-icon-btn {
  width: 32px;
  height: 32px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f8fafc;
  color: #475569;
  border: 1px solid #e2e8f0;
  transition: all 0.15s ease;
}
.record-action-icon-btn--download {
  background: #ecfdf5;
  color: #059669;
  border-color: #a7f3d0;
}

/* Boş ve Hata Durumları */
.kacak-empty-card,
.kacak-error-card {
  padding: 36px 20px;
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
}
.kacak-empty-card p,
.kacak-error-card p {
  font-size: 13px;
  color: #64748b;
  max-width: 280px;
  margin: 0;
}

/* Bottom Sheet & Modallar */
.kacak-modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 100;
  background: rgba(0, 0, 0, 0.45);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: flex-end;
}
.kacak-bottom-sheet {
  width: 100%;
  max-height: 85vh;
  background: #ffffff;
  border-radius: 28px 28px 0 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  box-shadow: 0 -8px 30px rgba(0, 0, 0, 0.15);
  padding-bottom: env(safe-area-inset-bottom, 20px);
}
.sheet-handle-bar {
  width: 44px;
  height: 5px;
  background: #cbd5e1;
  border-radius: 9999px;
  margin: 10px auto 4px;
}
.sheet-header {
  padding: 12px 20px 10px;
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  border-bottom: 1px solid #f1f5f9;
}
.sheet-eyebrow {
  font-size: 10px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #3b82f6;
}
.sheet-title {
  font-size: 18px;
  font-weight: 800;
  color: #0f172a;
  margin: 2px 0 0;
}
.sheet-subtitle {
  font-size: 12px;
  color: #64748b;
  margin: 2px 0 0;
}
.sheet-close-btn {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f1f5f9;
  color: #475569;
}
.sheet-content {
  padding: 16px 20px;
  overflow-y: auto;
}

/* Detay İçeriği */
.detail-status-strip {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 14px;
}
.detail-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}
.detail-cell {
  background: #f8fafc;
  padding: 10px 12px;
  border-radius: 14px;
  border: 1px solid #e2e8f0;
}
.detail-cell-label {
  font-size: 10px;
  font-weight: 700;
  color: #64748b;
  text-transform: uppercase;
  display: block;
}
.detail-cell-val {
  font-size: 13px;
  font-weight: 700;
  color: #0f172a;
  margin-top: 2px;
  display: block;
  word-break: break-word;
}
.detail-cell-text {
  font-size: 13px;
  color: #334155;
  margin-top: 3px;
  white-space: pre-wrap;
  word-break: break-word;
}
.detail-warning-box {
  display: flex;
  gap: 10px;
  padding: 12px;
  border-radius: 14px;
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #991b1b;
  font-size: 12px;
  margin-top: 12px;
}
.detail-warning-box--slate {
  background: #f8fafc;
  border-color: #cbd5e1;
  color: #334155;
}
.detail-photos-bar {
  display: flex;
  gap: 8px;
  margin-top: 14px;
}
.detail-gallery-btn {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 12px;
  border-radius: 14px;
  background: #3b82f6;
  color: #ffffff;
  font-size: 13px;
  font-weight: 700;
}
.detail-gallery-btn:disabled {
  background: #cbd5e1;
  cursor: not-allowed;
}
.detail-zip-btn {
  width: 48px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 14px;
  background: #10b981;
  color: #ffffff;
}

/* Detay Aksiyon Butonları */
.detail-actions {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-top: 16px;
  padding-top: 14px;
  border-top: 1px solid #f1f5f9;
}

/* Buton Stilleri */
.clay-btn-primary,
.clay-btn-secondary,
.clay-btn-success,
.clay-btn-warning,
.clay-btn-danger,
.clay-btn-info {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 12px;
  border-radius: 14px;
  font-size: 13px;
  font-weight: 700;
  transition: all 0.15s ease;
  width: 100%;
}
.clay-btn-primary {
  background: #0f172a;
  color: #ffffff;
}
.clay-btn-secondary {
  background: #f1f5f9;
  color: #475569;
}
.clay-btn-success {
  background: #10b981;
  color: #ffffff;
}
.clay-btn-warning {
  background: #f59e0b;
  color: #ffffff;
}
.clay-btn-danger {
  background: #ef4444;
  color: #ffffff;
}
.clay-btn-info {
  background: #0284c7;
  color: #ffffff;
}

/* Ekip Başarı Sıralaması (Leaderboard) */
.leaderboard-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.leaderboard-item {
  padding: 12px 14px;
  border-radius: 16px;
}
.leaderboard-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 8px;
}
.leaderboard-rank-wrap {
  display: flex;
  align-items: center;
  gap: 10px;
}
.leaderboard-rank {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 11px;
  font-weight: 800;
  background: #e2e8f0;
  color: #475569;
}
.leaderboard-rank--first {
  background: #fef3c7;
  color: #d97706;
}
.leaderboard-rank--second {
  background: #f1f5f9;
  color: #64748b;
}
.leaderboard-rank--third {
  background: #fed7aa;
  color: #c2410c;
}
.leaderboard-name {
  font-size: 13px;
  font-weight: 700;
  color: #0f172a;
}
.leaderboard-count {
  font-size: 12px;
  font-weight: 800;
  color: #3b82f6;
}
.leaderboard-track {
  width: 100%;
  height: 8px;
  background: #f1f5f9;
  border-radius: 9999px;
  overflow: hidden;
}
.leaderboard-fill {
  height: 100%;
  border-radius: 9999px;
  transition: width 0.4s ease;
}

/* Galeri Modalı */
.kacak-gallery-modal {
  position: fixed;
  inset: 0;
  z-index: 120;
  background: #090d16;
  color: #ffffff;
  display: flex;
  flex-direction: column;
}
.gallery-header {
  padding: 16px 20px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: rgba(9, 13, 22, 0.9);
  backdrop-filter: blur(8px);
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}
.gallery-title-wrap {
  display: flex;
  align-items: center;
  gap: 12px;
}
.gallery-back-btn,
.gallery-close-btn {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(255, 255, 255, 0.1);
  color: #ffffff;
}
.gallery-title {
  font-size: 16px;
  font-weight: 800;
  margin: 0;
}
.gallery-count {
  font-size: 11px;
  color: rgba(255, 255, 255, 0.6);
}
.gallery-body {
  flex: 1;
  padding: 16px;
  overflow-y: auto;
}
.gallery-loading,
.gallery-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 20px;
  gap: 12px;
  color: rgba(255, 255, 255, 0.6);
}
.gallery-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 10px;
  padding-bottom: 30px;
}
.gallery-photo-btn {
  position: relative;
  aspect-ratio: 1;
  border-radius: 18px;
  overflow: hidden;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  width: 100%;
  padding: 0;
}
.gallery-photo-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.gallery-tag {
  position: absolute;
  bottom: 8px;
  left: 8px;
  padding: 2px 8px;
  border-radius: 9999px;
  background: rgba(0, 0, 0, 0.65);
  backdrop-filter: blur(4px);
  font-size: 10px;
  font-weight: 700;
  color: #ffffff;
}
.gallery-pdf-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  aspect-ratio: 1;
  border-radius: 18px;
  background: rgba(255, 255, 255, 0.08);
  border: 1px solid rgba(255, 255, 255, 0.15);
  padding: 12px;
  text-align: center;
  text-decoration: none;
  gap: 6px;
}
.pdf-name {
  font-size: 11px;
  font-weight: 700;
  color: #ffffff;
  max-width: 100%;
}
.pdf-tag {
  font-size: 9px;
  color: rgba(255, 255, 255, 0.5);
  text-transform: uppercase;
}

/* Lightbox */
.kacak-lightbox-modal {
  position: fixed;
  inset: 0;
  z-index: 150;
  background: rgba(0, 0, 0, 0.95);
  display: flex;
  align-items: center;
  justify-content: center;
}
.lightbox-header {
  position: absolute;
  top: 0;
  inset-x: 0;
  padding: 16px 20px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: linear-gradient(to bottom, rgba(0, 0, 0, 0.7), transparent);
  z-index: 10;
}
.lightbox-counter {
  font-size: 13px;
  font-weight: 800;
  color: #ffffff;
}
.lightbox-actions {
  display: flex;
  align-items: center;
  gap: 10px;
}
.lightbox-btn {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(255, 255, 255, 0.15);
  color: #ffffff;
}
.lightbox-image-wrap {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 60px 16px;
}
.lightbox-img {
  max-width: 100%;
  max-height: 85vh;
  object-fit: contain;
  border-radius: 12px;
}
.lightbox-nav-btn {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  width: 44px;
  height: 44px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(0, 0, 0, 0.6);
  color: #ffffff;
  border: 1px solid rgba(255, 255, 255, 0.2);
}
.lightbox-nav-btn--prev {
  left: 14px;
}
.lightbox-nav-btn--next {
  right: 14px;
}

/* Aksiyon Sayfası */
.action-input-wrap {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-bottom: 12px;
}
.action-label {
  font-size: 11px;
  font-weight: 700;
  color: #475569;
  text-transform: uppercase;
}
.action-textarea {
  width: 100%;
  padding: 10px 12px;
  border-radius: 14px;
  border: 1px solid #cbd5e1;
  font-size: 13px;
  font-weight: 600;
  color: #0f172a;
}
.action-checkbox-wrap {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  font-weight: 600;
  color: #334155;
  padding: 10px 12px;
  background: #fffbeb;
  border: 1px solid #fde68a;
  border-radius: 12px;
  margin-bottom: 14px;
}
.action-checkbox {
  width: 18px;
  height: 18px;
  border-radius: 6px;
}
.action-buttons-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}

/* Global Toast */
.kacak-toast {
  position: fixed;
  bottom: 24px;
  left: 50%;
  transform: translateX(-50%);
  z-index: 200;
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 18px;
  border-radius: 9999px;
  font-size: 13px;
  font-weight: 700;
  color: #ffffff;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
}
.kacak-toast--success {
  background: #059669;
}
.kacak-toast--error {
  background: #dc2626;
}
.kacak-toast--warning {
  background: #d97706;
}

/* İskelet Animasyonu */
.kacak-skeletons {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.skeleton-card {
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.skeleton-line {
  height: 14px;
  background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
  border-radius: 8px;
}
.skeleton-line--sm {
  width: 35%;
  height: 10px;
}
.skeleton-line--md {
  width: 60%;
}
.skeleton-line--lg {
  width: 85%;
}
@keyframes shimmer {
  0% {
    background-position: 200% 0;
  }
  100% {
    background-position: -200% 0;
  }
}

/* Animasyon Geçişleri */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

.sheet-enter-active,
.sheet-leave-active {
  transition: opacity 0.25s ease, transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}
.sheet-enter-from,
.sheet-leave-to {
  opacity: 0;
  transform: translateY(100%);
}
</style>
