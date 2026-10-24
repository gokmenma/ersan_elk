<script setup>
import { computed, nextTick, onMounted, onBeforeUnmount, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
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
  ExternalLink,
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
import {
  ihbarApi,
  ihbarState,
  setIhbarStatus,
  openIhbarDrawer,
  closeIhbarDrawer,
  openIhbarStats,
  closeIhbarStats,
  openNewIhbarModal,
  closeNewIhbarModal,
} from '../services/ihbar.js'

const router = useRouter()

// UI Yükleme & İşlem Durumları
const loading = ref(false)
const actionLoading = ref(false)
const toastMessage = ref('')
let toastTimer = null

function showToast(msg) {
  toastMessage.value = msg
  if (toastTimer) clearTimeout(toastTimer)
  toastTimer = setTimeout(() => {
    toastMessage.value = ''
  }, 3500)
}

// Liste Verileri
const ihbarlar = ref([])
const ilceler = ref([])
const personelAdaylari = ref([])
const canEdit = ref(false)

// Filtreler
const searchInput = ref('')
const selectedIlce = ref('')
const datePreset = ref('month') // 'month' | '30days' | 'all'
const baslangicTarihi = ref('')
const bitisTarihi = ref('')

// İhbar Detayı & Drawer
const activeDetail = ref(null)
const detailLoading = ref(false)

// Resim Lightbox
const lightboxOpen = ref(false)
const lightboxIndex = ref(0)
const lightboxImages = ref([])

// Ekip Atama Modalı
const assignModalOpen = ref(false)
const assignSearch = ref('')
const selectedPersonelTokens = ref([])

// Not Ekle Modalı
const noteModalOpen = ref(false)
const noteText = ref('')

// Sonuçlandır Modalı
const closeModalOpen = ref(false)
const closeResultType = ref('olumlu') // 'olumlu' | 'olumsuz'
const closeTutanakNo = ref('')
const closeReason = ref('')

// Personel Performansı / İstatistikler
const statsLoading = ref(false)
const statsData = ref(null)
const statsSearch = ref('')

// Yeni İhbar Oluşturma
const newForm = ref({
  ilce: '',
  mahalle: '',
  telefon: '',
  komsu_abone_no: '',
  aciklama: '',
  konum_lat: null,
  konum_lng: null,
  konum_link: '',
  fotograflar: [],
})
const fotoPreviewList = ref([])
const gettingLocation = ref(false)

// Tarih Yardımcıları
function initDates() {
  const now = new Date()
  bitisTarihi.value = now.toISOString().slice(0, 10)
  const oneMonthAgo = new Date(now)
  oneMonthAgo.setMonth(oneMonthAgo.getMonth() - 1)
  baslangicTarihi.value = oneMonthAgo.toISOString().slice(0, 10)
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
  } else if (preset === 'all') {
    baslangicTarihi.value = '2024-01-01'
  }
  loadData()
}

// Ana Veri Yükleme
async function loadData() {
  loading.value = true
  try {
    const res = await ihbarApi.list({
      baslangic: baslangicTarihi.value,
      bitis: bitisTarihi.value,
    })
    if (res.success && res.data) {
      ihbarlar.value = res.data.ihbarlar || []
      ilceler.value = res.data.ilceler || []
      personelAdaylari.value = res.data.personel_adaylari || []
      canEdit.value = Boolean(res.data.can_edit)

      // State sayaçlarını güncelle
      Object.assign(ihbarState.counts, res.data.ozet || {})
    }
  } catch (err) {
    showToast(err.message || 'İhbar listesi yüklenemedi.')
  } finally {
    loading.value = false
  }
}

// İhbar Detayı Getir
async function fetchDetail(token) {
  detailLoading.value = true
  activeDetail.value = null
  try {
    const res = await ihbarApi.detail(token)
    if (res.success && res.data) {
      activeDetail.value = res.data
    }
  } catch (err) {
    showToast(err.message || 'İhbar detayı alınamadı.')
  } finally {
    detailLoading.value = false
  }
}

function handleCardClick(ihbar) {
  openIhbarDrawer(ihbar.token)
  fetchDetail(ihbar.token)
}

// Filtrelenmiş İhbar Listesi
const filteredIhbarlar = computed(() => {
  const q = searchInput.value.trim().toLocaleLowerCase('tr-TR')
  const st = ihbarState.activeStatus
  const ilc = selectedIlce.value

  return ihbarlar.value.filter((item) => {
    if (st && item.durum !== st) return false
    if (ilc && item.ilce !== ilc) return false
    if (q) {
      const hay = [
        item.ilce,
        item.mahalle,
        item.aciklama,
        item.bildiren,
        item.ekip,
        item.telefon,
        item.tutanak_no,
      ]
        .filter(Boolean)
        .join(' ')
        .toLocaleLowerCase('tr-TR')
      if (!hay.includes(q)) return false
    }
    return true
  })
})

// Segment Oranları (Hero Bento için)
const heroSegments = computed(() => {
  const c = ihbarState.counts
  const total = Number(c.toplam) || 0
  if (!total) {
    return { yeni: 0, devam: 0, olumlu: 0, olumsuz: 0 }
  }
  return {
    yeni: Math.round(((Number(c.yeni) || 0) / total) * 100),
    devam: Math.round(((Number(c.devam) || 0) / total) * 100),
    olumlu: Math.round(((Number(c.olumlu) || 0) / total) * 100),
    olumsuz: Math.round(((Number(c.olumsuz) || 0) / total) * 100),
  }
})

// Ekip Atama İşlemleri
function openAssignModal() {
  if (!activeDetail.value) return
  assignSearch.value = ''
  selectedPersonelTokens.value = [...(activeDetail.value.atanan_personel_tokenlari || [])]
  assignModalOpen.value = true
}

function togglePersonelSelection(token) {
  const idx = selectedPersonelTokens.value.indexOf(token)
  if (idx > -1) {
    selectedPersonelTokens.value.splice(idx, 1)
  } else {
    if (selectedPersonelTokens.value.length >= 2) {
      showToast('En fazla 2 personel seçebilirsiniz.')
      return
    }
    selectedPersonelTokens.value.push(token)
  }
}

const filteredPersonelAdaylari = computed(() => {
  const q = assignSearch.value.trim().toLocaleLowerCase('tr-TR')
  if (!q) return personelAdaylari.value
  return personelAdaylari.value.filter((p) => p.ad.toLocaleLowerCase('tr-TR').includes(q))
})

async function submitAssign() {
  if (!selectedPersonelTokens.value.length) {
    showToast('Lütfen en az bir personel seçin.')
    return
  }
  actionLoading.value = true
  try {
    const res = await ihbarApi.assign(
      activeDetail.value.token,
      selectedPersonelTokens.value,
      auth.state.csrfToken
    )
    showToast(res.message || 'Ekip başarıyla atandı.')
    assignModalOpen.value = false
    await fetchDetail(activeDetail.value.token)
    await loadData()
  } catch (err) {
    showToast(err.message || 'Atama işlemi başarısız.')
  } finally {
    actionLoading.value = false
  }
}

// Not Ekleme
function openNoteModal() {
  noteText.value = ''
  noteModalOpen.value = true
}

async function submitNote() {
  if (!noteText.value.trim()) {
    showToast('Lütfen bir not yazın.')
    return
  }
  actionLoading.value = true
  try {
    const res = await ihbarApi.addNote(
      activeDetail.value.token,
      noteText.value.trim(),
      auth.state.csrfToken
    )
    showToast(res.message || 'Not eklendi.')
    noteModalOpen.value = false
    await fetchDetail(activeDetail.value.token)
  } catch (err) {
    showToast(err.message || 'Not eklenemedi.')
  } finally {
    actionLoading.value = false
  }
}

// Sonuçlandırma
function openCloseModal() {
  closeResultType.value = 'olumlu'
  closeTutanakNo.value = ''
  closeReason.value = ''
  closeModalOpen.value = true
}

async function submitClose() {
  if (closeResultType.value === 'olumsuz' && !closeReason.value.trim()) {
    showToast('Olumsuz sonuç için lütfen gerekçe belirtin.')
    return
  }
  actionLoading.value = true
  try {
    const res = await ihbarApi.close(
      activeDetail.value.token,
      closeResultType.value,
      closeTutanakNo.value.trim(),
      closeReason.value.trim(),
      auth.state.csrfToken
    )
    showToast(res.message || 'İhbar sonuçlandırıldı.')
    closeModalOpen.value = false
    await fetchDetail(activeDetail.value.token)
    await loadData()
  } catch (err) {
    showToast(err.message || 'Sonuçlandırma başarısız.')
  } finally {
    actionLoading.value = false
  }
}

// Sonucu Geri Al
async function handleCancelResult() {
  if (!confirm('İhbar sonucunu iptal edip yeniden işleme almak istediğinize emin misiniz?')) {
    return
  }
  actionLoading.value = true
  try {
    const res = await ihbarApi.cancelResult(activeDetail.value.token, auth.state.csrfToken)
    showToast(res.message || 'İhbar sonucu iptal edildi.')
    await fetchDetail(activeDetail.value.token)
    await loadData()
  } catch (err) {
    showToast(err.message || 'İşlem geri alınamadı.')
  } finally {
    actionLoading.value = false
  }
}

// Resim Lightbox
function openLightbox(images, index = 0) {
  lightboxImages.value = images
  lightboxIndex.value = index
  lightboxOpen.value = true
}

function closeLightbox() {
  lightboxOpen.value = false
}

function nextPhoto() {
  if (!lightboxImages.value.length) return
  lightboxIndex.value = (lightboxIndex.value + 1) % lightboxImages.value.length
}

function prevPhoto() {
  if (!lightboxImages.value.length) return
  lightboxIndex.value =
    (lightboxIndex.value - 1 + lightboxImages.value.length) % lightboxImages.value.length
}

// Personel Performansı / İstatistik Sheet
async function openStatsSheet() {
  openIhbarStats()
  statsLoading.value = true
  statsSearch.value = ''
  try {
    const res = await ihbarApi.stats({
      baslangic: baslangicTarihi.value,
      bitis: bitisTarihi.value,
    })
    if (res.success && res.data) {
      statsData.value = res.data
    }
  } catch (err) {
    showToast(err.message || 'İstatistikler alınamadı.')
  } finally {
    statsLoading.value = false
  }
}

const filteredStatsList = computed(() => {
  if (!statsData.value?.personel_stats) return []
  const q = statsSearch.value.trim().toLocaleLowerCase('tr-TR')
  if (!q) return statsData.value.personel_stats
  return statsData.value.personel_stats.filter((p) =>
    p.ad.toLocaleLowerCase('tr-TR').includes(q)
  )
})

function filterByPersonel(personelAdi) {
  closeIhbarStats()
  searchInput.value = personelAdi
}

function exportStatsExcel() {
  if (!statsData.value?.personel_stats?.length) {
    showToast('Aktarılacak veri bulunamadı.')
    return
  }
  let tableHTML = `<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40"><head><meta charset="utf-8"><!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Personel Performansi</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]--></head><body><table border="1"><thead><tr style="background-color:#ea580c;color:#ffffff;font-weight:bold;"><th>Personel</th><th>Toplam İhbar</th><th>Olumlu İhbar</th><th>Olumsuz İhbar</th><th>Devam Eden</th><th>Başarı Oranı (%)</th><th>Son İhbar Tarihi</th></tr></thead><tbody>`

  statsData.value.personel_stats.forEach((p) => {
    tableHTML += `<tr><td>${p.ad}</td><td>${p.toplam}</td><td>${p.olumlu}</td><td>${p.olumsuz}</td><td>${p.bekleyen}</td><td>%${p.oran} (${p.olumlu}/${p.sonuclanan})</td><td>${p.son_ihbar_tarihi}</td></tr>`
  })

  tableHTML += `</tbody></table></body></html>`
  const blob = new Blob([tableHTML], { type: 'application/vnd.ms-excel;charset=utf-8' })
  const link = document.createElement('a')
  link.href = URL.createObjectURL(blob)
  link.download = `Personel_Ihbar_Performansi_${new Date().toISOString().slice(0, 10)}.xls`
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
}

// Yeni İhbar Fonksiyonları
function openCreateModal() {
  newForm.value = {
    ilce: '',
    mahalle: '',
    telefon: '',
    komsu_abone_no: '',
    aciklama: '',
    konum_lat: null,
    konum_lng: null,
    konum_link: '',
    fotograflar: [],
  }
  fotoPreviewList.value = []
  openNewIhbarModal()
}

function handleFileSelect(e) {
  const files = Array.from(e.target.files || [])
  if (!files.length) return

  const remaining = 5 - newForm.value.fotograflar.length
  if (remaining <= 0) {
    showToast('En fazla 5 fotoğraf ekleyebilirsiniz.')
    return
  }

  const toAdd = files.slice(0, remaining)
  toAdd.forEach((f) => {
    newForm.value.fotograflar.push(f)
    const reader = new FileReader()
    reader.onload = (ev) => {
      fotoPreviewList.value.push(ev.target.result)
    }
    reader.readAsDataURL(f)
  })
}

function removeFoto(index) {
  newForm.value.fotograflar.splice(index, 1)
  fotoPreviewList.value.splice(index, 1)
}

function getCurrentLocation() {
  if (!navigator.geolocation) {
    showToast('Cihazınız konum servisini desteklemiyor.')
    return
  }
  gettingLocation.value = true
  navigator.geolocation.getCurrentPosition(
    (pos) => {
      gettingLocation.value = false
      newForm.value.konum_lat = Number(pos.coords.latitude.toFixed(6))
      newForm.value.konum_lng = Number(pos.coords.longitude.toFixed(6))
      showToast('Konumunuz başarıyla alındı.')
    },
    (err) => {
      gettingLocation.value = false
      showToast('Konum alınamadı: ' + err.message)
    },
    { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
  )
}

async function submitNewIhbar() {
  if (!newForm.value.ilce) {
    showToast('Lütfen ilçe seçin.')
    return
  }
  if (!newForm.value.mahalle.trim()) {
    showToast('Lütfen mahalle alanını doldurun.')
    return
  }
  if (!newForm.value.aciklama.trim()) {
    showToast('Lütfen ihbar açıklamasını yazın.')
    return
  }
  if (
    !newForm.value.konum_link.trim() &&
    (newForm.value.konum_lat === null || newForm.value.konum_lng === null)
  ) {
    showToast('Lütfen konum bilgisi (GPS veya harita linki) ekleyin.')
    return
  }

  actionLoading.value = true
  try {
    const fd = new FormData()
    fd.append('ilce', newForm.value.ilce)
    fd.append('mahalle', newForm.value.mahalle.trim())
    fd.append('telefon', newForm.value.telefon.trim())
    fd.append('komsu_abone_no', newForm.value.komsu_abone_no.trim())
    fd.append('aciklama', newForm.value.aciklama.trim())

    if (newForm.value.konum_lat !== null) {
      fd.append('konum_lat', newForm.value.konum_lat)
      fd.append('konum_lng', newForm.value.konum_lng)
    }
    if (newForm.value.konum_link.trim()) {
      fd.append('konum_link', newForm.value.konum_link.trim())
    }

    newForm.value.fotograflar.forEach((file) => {
      fd.append('fotograflar[]', file)
    })

    const res = await ihbarApi.create(fd, auth.state.csrfToken)
    showToast(res.message || 'İhbar başarıyla oluşturuldu.')
    closeNewIhbarModal()
    await loadData()
  } catch (err) {
    showToast(err.message || 'İhbar oluşturulamadı.')
  } finally {
    actionLoading.value = false
  }
}

// Durum Rozet Stilleri
function getDurumBadgeClass(durum) {
  switch (durum) {
    case 'yeni':
      return 'badge--slate'
    case 'yonlendirildi':
      return 'badge--sky'
    case 'islemde':
      return 'badge--amber'
    case 'olumlu':
      return 'badge--emerald'
    case 'olumsuz':
      return 'badge--rose'
    default:
      return 'badge--slate'
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
      eyebrow="İş Takip"
      title="İhbar Yönetimi"
      subtitle="Kaçak ve saha ihbar operasyonları"
    >
      <template #actions>
        <button
          type="button"
          class="clay-circle-btn"
          aria-label="Personel Performansı"
          title="Personel Performansı"
          @click="openStatsSheet"
        >
          <Trophy :size="20" class="text-amber-500" />
        </button>
        <button
          v-if="canEdit"
          type="button"
          class="clay-circle-btn clay-circle-btn--primary"
          aria-label="Yeni İhbar Ekle"
          title="Yeni İhbar Ekle"
          @click="openCreateModal"
        >
          <Plus :size="20" />
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
              <strong>{{ ihbarState.counts.toplam }}</strong>
              <small>toplam ihbar</small>
            </div>
          </div>
          <div class="ihbar-hero-rate">
            <span class="rate-value">%{{ ihbarState.counts.basari_orani }}</span>
            <span class="rate-label">Olumlu Başarı</span>
          </div>
        </div>

        <!-- Segmentli İlerleme Çubuğu -->
        <div class="segmented-track" role="img" aria-label="İhbar durum dağılımı">
          <span
            class="seg-pill seg-pill--slate"
            :style="{ width: `${heroSegments.yeni}%` }"
            title="Yeni"
          ></span>
          <span
            class="seg-pill seg-pill--blue"
            :style="{ width: `${heroSegments.devam}%` }"
            title="Devam Eden"
          ></span>
          <span
            class="seg-pill seg-pill--emerald"
            :style="{ width: `${heroSegments.olumlu}%` }"
            title="Olumlu"
          ></span>
          <span
            class="seg-pill seg-pill--coral"
            :style="{ width: `${heroSegments.olumsuz}%` }"
            title="Olumsuz"
          ></span>
        </div>

        <!-- 4'lü Metrik Izgarası -->
        <div class="ihbar-hero-grid">
          <button
            type="button"
            class="ihbar-hero-cell"
            :class="{ 'is-active': ihbarState.activeStatus === 'yeni' }"
            @click="setIhbarStatus(ihbarState.activeStatus === 'yeni' ? '' : 'yeni')"
          >
            <span class="cell-label"><i class="ds-dot ds-dot--slate"></i>Yeni</span>
            <strong class="cell-num">{{ ihbarState.counts.yeni }}</strong>
          </button>

          <button
            type="button"
            class="ihbar-hero-cell"
            :class="{ 'is-active': ihbarState.activeStatus === 'islemde' || ihbarState.activeStatus === 'yonlendirildi' }"
            @click="setIhbarStatus(ihbarState.activeStatus === 'islemde' ? '' : 'islemde')"
          >
            <span class="cell-label"><i class="ds-dot ds-dot--blue"></i>Devam</span>
            <strong class="cell-num">{{ ihbarState.counts.devam }}</strong>
          </button>

          <button
            type="button"
            class="ihbar-hero-cell"
            :class="{ 'is-active': ihbarState.activeStatus === 'olumlu' }"
            @click="setIhbarStatus(ihbarState.activeStatus === 'olumlu' ? '' : 'olumlu')"
          >
            <span class="cell-label"><i class="ds-dot ds-dot--emerald"></i>Olumlu</span>
            <strong class="cell-num text-emerald-400">{{ ihbarState.counts.olumlu }}</strong>
          </button>

          <button
            type="button"
            class="ihbar-hero-cell"
            :class="{ 'is-active': ihbarState.activeStatus === 'olumsuz' }"
            @click="setIhbarStatus(ihbarState.activeStatus === 'olumsuz' ? '' : 'olumsuz')"
          >
            <span class="cell-label"><i class="ds-dot ds-dot--coral"></i>Olumsuz</span>
            <strong class="cell-num text-rose-400">{{ ihbarState.counts.olumsuz }}</strong>
          </button>
        </div>
      </section>

      <!-- Dönem Seçici & Tarih Hapları -->
      <div class="ihbar-date-bar">
        <div class="date-preset-group">
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

      <!-- Arama ve İlçe Filtresi -->
      <div class="ihbar-search-card bento-card bento-card--white">
        <div class="search-input-wrap">
          <Search :size="18" class="search-icon" />
          <input
            v-model="searchInput"
            type="search"
            placeholder="İlçe, mahalle, personel, tutanak ara…"
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
        <div class="status-pills-scroll">
          <button
            type="button"
            class="clay-filter-pill"
            :class="{ 'is-active': ihbarState.activeStatus === '' }"
            @click="setIhbarStatus('')"
          >
            Tümü
          </button>
          <button
            type="button"
            class="clay-filter-pill"
            :class="{ 'is-active': ihbarState.activeStatus === 'yeni' }"
            @click="setIhbarStatus('yeni')"
          >
            Yeni <span class="pill-badge">{{ ihbarState.counts.yeni }}</span>
          </button>
          <button
            type="button"
            class="clay-filter-pill"
            :class="{ 'is-active': ihbarState.activeStatus === 'yonlendirildi' }"
            @click="setIhbarStatus('yonlendirildi')"
          >
            Yönlendirildi <span class="pill-badge">{{ ihbarState.counts.yonlendirildi }}</span>
          </button>
          <button
            type="button"
            class="clay-filter-pill"
            :class="{ 'is-active': ihbarState.activeStatus === 'islemde' }"
            @click="setIhbarStatus('islemde')"
          >
            İşlemde <span class="pill-badge">{{ ihbarState.counts.islemde }}</span>
          </button>
          <button
            type="button"
            class="clay-filter-pill"
            :class="{ 'is-active': ihbarState.activeStatus === 'olumlu' }"
            @click="setIhbarStatus('olumlu')"
          >
            Olumlu <span class="pill-badge">{{ ihbarState.counts.olumlu }}</span>
          </button>
          <button
            type="button"
            class="clay-filter-pill"
            :class="{ 'is-active': ihbarState.activeStatus === 'olumsuz' }"
            @click="setIhbarStatus('olumsuz')"
          >
            Olumsuz <span class="pill-badge">{{ ihbarState.counts.olumsuz }}</span>
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

      <!-- İhbar Listesi -->
      <section class="ihbar-list-section">
        <div class="list-header-row">
          <span class="list-count-badge">
            <b>{{ filteredIhbarlar.length }}</b> ihbar gösteriliyor
          </span>
          <button
            v-if="searchInput || selectedIlce || ihbarState.activeStatus"
            type="button"
            class="clear-all-filter-btn"
            @click="searchInput = ''; selectedIlce = ''; setIhbarStatus('')"
          >
            Filtreleri Temizle
          </button>
        </div>

        <!-- Yükleniyor / Boş Durum -->
        <div v-if="loading && !ihbarlar.length" class="ihbar-state-card">
          <div class="loading-spinner"></div>
          <p>İhbarlar yükleniyor…</p>
        </div>

        <div v-else-if="!filteredIhbarlar.length" class="ihbar-state-card">
          <AlertCircle :size="32" class="text-slate-400" />
          <strong>Eşleşen ihbar bulunamadı</strong>
          <p>Arama veya filtre kriterlerinizi değiştirerek tekrar deneyin.</p>
        </div>

        <!-- İhbar Kartları Izgarası / Listesi -->
        <div v-else class="ihbar-cards-stack">
          <article
            v-for="item in filteredIhbarlar"
            :key="item.token"
            class="ihbar-card bento-card bento-card--white active:scale-[.99] transition-transform"
            @click="handleCardClick(item)"
          >
            <header class="ihbar-card-top">
              <div class="ihbar-location-wrap">
                <span class="location-icon-bubble">
                  <MapPin :size="16" />
                </span>
                <div class="location-text">
                  <h3 class="location-title">
                    {{ item.ilce || 'İlçe belirtilmedi' }}
                    <span v-if="item.mahalle" class="location-sub">· {{ item.mahalle }}</span>
                  </h3>
                </div>
              </div>
              <span class="status-badge" :class="getDurumBadgeClass(item.durum)">
                {{ item.durum_etiketi }}
              </span>
            </header>

            <p class="ihbar-card-desc">
              {{ item.aciklama || 'Açıklama belirtilmemiş.' }}
            </p>

            <!-- Ek Bilgi Etiketleri (Fotoğraf, Abone, Tutanak) -->
            <div class="ihbar-card-tags">
              <span v-if="item.foto_sayisi > 0" class="mini-tag mini-tag--foto">
                <ImageIcon :size="12" />
                {{ item.foto_sayisi }} Fotoğraf
              </span>
              <span v-if="item.komsu_abone_no && item.komsu_abone_no !== '-'" class="mini-tag">
                Abone: {{ item.komsu_abone_no }}
              </span>
              <span v-if="item.lat && item.lng" class="mini-tag mini-tag--gps">
                <Navigation :size="12" />
                GPS
              </span>
              <span v-if="item.tutanak_no && item.tutanak_no !== '-'" class="mini-tag mini-tag--tutanak">
                Tutanak: {{ item.tutanak_no }}
              </span>
            </div>

            <!-- Alt Bilgi Satırı: Bildiren & Ekip & Tarih -->
            <footer class="ihbar-card-footer">
              <div class="footer-left">
                <span class="footer-item">
                  <User :size="13" class="footer-icon" />
                  <span class="truncate">{{ item.bildiren }}</span>
                </span>
                <span class="footer-item footer-item--team">
                  <Users :size="13" class="footer-icon" />
                  <span class="truncate">{{ item.ekip }}</span>
                </span>
              </div>
              <time class="footer-date">
                {{ item.tarih }}
              </time>
            </footer>
          </article>
        </div>
      </section>
    </div>

    <!-- İhbar Detay Bottom Sheet (Drawer) -->
    <div
      v-if="ihbarState.drawerOpen"
      class="ihbar-drawer-backdrop"
      @click="closeIhbarDrawer"
    ></div>
    <div
      class="ihbar-drawer-sheet"
      :class="{ 'is-open': ihbarState.drawerOpen }"
    >
      <div class="drawer-drag-handle"></div>

      <header class="drawer-header">
        <div>
          <span class="drawer-eyebrow">İHBAR DETAYI</span>
          <h2 class="drawer-title">
            {{ activeDetail ? `${activeDetail.ilce || ''} · ${activeDetail.mahalle || ''}` : 'Yükleniyor…' }}
          </h2>
        </div>
        <button type="button" class="clay-circle-btn" @click="closeIhbarDrawer">
          <X :size="20" />
        </button>
      </header>

      <div v-if="detailLoading" class="drawer-loading">
        <div class="loading-spinner"></div>
        <p>Detaylar getiriliyor…</p>
      </div>

      <div v-else-if="activeDetail" class="drawer-body">
        <!-- Durum ve Hızlı Bilgi Bento -->
        <div class="bento-card bento-card--white detail-info-bento">
          <div class="detail-row">
            <span class="detail-label">Durum</span>
            <span class="status-badge" :class="getDurumBadgeClass(activeDetail.durum)">
              {{ activeDetail.durum_etiketi }}
            </span>
          </div>

          <div class="detail-row">
            <span class="detail-label">Bildirim Tarihi</span>
            <span class="detail-val">{{ activeDetail.tarih }}</span>
          </div>

          <div class="detail-row">
            <span class="detail-label">İhbarı Bildiren</span>
            <span class="detail-val font-semibold">{{ activeDetail.bildiren }}</span>
          </div>

          <div v-if="activeDetail.telefon && activeDetail.telefon !== '-'" class="detail-row">
            <span class="detail-label">İletişim Telefonu</span>
            <a :href="`tel:${activeDetail.telefon}`" class="detail-phone-link">
              <Phone :size="14" />
              <span>{{ activeDetail.telefon }}</span>
            </a>
          </div>

          <div v-if="activeDetail.komsu_abone_no && activeDetail.komsu_abone_no !== '-'" class="detail-row">
            <span class="detail-label">Komşu Abone No</span>
            <span class="detail-val">{{ activeDetail.komsu_abone_no }}</span>
          </div>

          <div class="detail-row">
            <span class="detail-label">Atanan Ekip</span>
            <span class="detail-val font-semibold text-primary">
              {{ activeDetail.ekip }}
            </span>
          </div>

          <div v-if="activeDetail.tutanak_no && activeDetail.tutanak_no !== '-'" class="detail-row">
            <span class="detail-label">Tutanak No</span>
            <span class="detail-val font-mono">{{ activeDetail.tutanak_no }}</span>
          </div>

          <div v-if="activeDetail.sebep && activeDetail.sebep !== '-'" class="detail-row detail-row--col">
            <span class="detail-label text-rose-500">Olumsuzluk Gerekçesi</span>
            <p class="detail-reason-box">{{ activeDetail.sebep }}</p>
          </div>
        </div>

        <!-- Açıklama Bento -->
        <div class="bento-card bento-card--white detail-desc-bento">
          <span class="detail-label">İhbar Açıklaması</span>
          <p class="detail-desc-text">
            {{ activeDetail.aciklama || 'Açıklama girilmemiş.' }}
          </p>
        </div>

        <!-- Konum & Harita Bento -->
        <div
          v-if="(activeDetail.lat && activeDetail.lng) || activeDetail.konum_link"
          class="bento-card bento-card--white detail-map-bento"
        >
          <div class="map-bento-left">
            <div class="map-icon-box">
              <Navigation :size="20" class="text-primary" />
            </div>
            <div>
              <strong class="block text-sm">GPS / Konum Bilgisi</strong>
              <span class="text-xs text-slate-400">
                {{ activeDetail.lat ? `${activeDetail.lat}, ${activeDetail.lng}` : 'Harita Bağlantısı' }}
              </span>
            </div>
          </div>
          <a
            :href="activeDetail.lat ? `https://www.google.com/maps?q=${activeDetail.lat},${activeDetail.lng}` : activeDetail.konum_link"
            target="_blank"
            rel="noopener"
            class="clay-filter-pill is-active"
          >
            <span>Haritada Aç</span>
            <ExternalLink :size="14" />
          </a>
        </div>

        <!-- Fotoğraf Galerisi -->
        <div v-if="activeDetail.fotograflar && activeDetail.fotograflar.length" class="detail-gallery-section">
          <div class="gallery-head">
            <span class="detail-label">İhbar Fotoğrafları</span>
            <span class="text-xs text-slate-400">{{ activeDetail.fotograflar.length }} fotoğraf</span>
          </div>
          <div class="gallery-grid">
            <div
              v-for="(foto, idx) in activeDetail.fotograflar"
              :key="foto.token"
              class="gallery-item-tile"
              @click="openLightbox(activeDetail.fotograflar, idx)"
            >
              <img
                :src="ihbarApi.getPhotoUrl(foto.token, 'kucuk')"
                alt="İhbar fotoğrafı"
                loading="lazy"
                class="gallery-thumb"
              />
              <span class="gallery-zoom-overlay">
                <Maximize2 :size="16" />
              </span>
            </div>
          </div>
        </div>

        <!-- İşlem Tarihçesi / Timeline -->
        <div v-if="activeDetail.tarihce && activeDetail.tarihce.length" class="detail-timeline-section">
          <span class="detail-label">İşlem Tarihçesi</span>
          <div class="timeline-stack">
            <div
              v-for="t in activeDetail.tarihce"
              :key="t.id"
              class="timeline-item"
            >
              <div class="timeline-dot"></div>
              <div class="timeline-content">
                <div class="timeline-header">
                  <strong class="timeline-user">{{ t.ekleyen_adi }}</strong>
                  <time class="timeline-date">{{ t.tarih }}</time>
                </div>
                <p class="timeline-text">{{ t.aciklama }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Yetkili Aksiyon Butonları -->
        <div v-if="canEdit" class="drawer-action-buttons">
          <button
            type="button"
            class="clay-action-btn clay-action-btn--primary"
            @click="openAssignModal"
          >
            <UserCheck :size="18" />
            <span>Ekibe Yönlendir</span>
          </button>

          <button
            type="button"
            class="clay-action-btn clay-action-btn--secondary"
            @click="openNoteModal"
          >
            <Edit3 :size="18" />
            <span>İşlem Notu Ekle</span>
          </button>

          <button
            v-if="activeDetail.durum !== 'olumlu' && activeDetail.durum !== 'olumsuz'"
            type="button"
            class="clay-action-btn clay-action-btn--success"
            @click="openCloseModal"
          >
            <CheckCircle2 :size="18" />
            <span>İhbarı Sonuçlandır</span>
          </button>

          <button
            v-else
            type="button"
            class="clay-action-btn clay-action-btn--warning"
            @click="handleCancelResult"
          >
            <RotateCcw :size="18" />
            <span>Sonucu İptal Et / Geri Al</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Ekip Atama Modalı (Sheet) -->
    <div v-if="assignModalOpen" class="modal-backdrop" @click="assignModalOpen = false"></div>
    <div class="modal-dialog-sheet" :class="{ 'is-open': assignModalOpen }">
      <header class="modal-head">
        <div>
          <h3 class="modal-title">Kaçak Ekibini Seçin</h3>
          <p class="modal-subtitle">En fazla 2 personel seçebilirsiniz</p>
        </div>
        <button type="button" class="clay-circle-btn" @click="assignModalOpen = false">
          <X :size="18" />
        </button>
      </header>

      <div class="modal-search-wrap">
        <Search :size="16" class="search-icon" />
        <input
          v-model="assignSearch"
          type="search"
          placeholder="Personel ara…"
          class="modal-search-input"
        />
      </div>

      <div class="personel-selection-list">
        <label
          v-for="p in filteredPersonelAdaylari"
          :key="p.token"
          class="personel-select-row"
          :class="{ 'is-selected': selectedPersonelTokens.includes(p.token) }"
        >
          <input
            type="checkbox"
            :checked="selectedPersonelTokens.includes(p.token)"
            class="checkbox-input"
            @change="togglePersonelSelection(p.token)"
          />
          <div class="personel-avatar-cell">
            {{ p.ad.charAt(0).toLocaleUpperCase('tr-TR') }}
          </div>
          <div class="personel-meta">
            <strong class="personel-name">{{ p.ad }}</strong>
            <span class="personel-dept">{{ p.departman }}</span>
          </div>
        </label>
      </div>

      <footer class="modal-footer">
        <button
          type="button"
          class="clay-submit-btn"
          :disabled="actionLoading || !selectedPersonelTokens.length"
          @click="submitAssign"
        >
          <span v-if="actionLoading" class="loading-spinner"></span>
          <span>Seçilen Ekibe Yönlendir ({{ selectedPersonelTokens.length }})</span>
        </button>
      </footer>
    </div>

    <!-- İşlem Notu Ekle Modalı -->
    <div v-if="noteModalOpen" class="modal-backdrop" @click="noteModalOpen = false"></div>
    <div class="modal-dialog-sheet" :class="{ 'is-open': noteModalOpen }">
      <header class="modal-head">
        <h3 class="modal-title">İşlem Notu Ekle</h3>
        <button type="button" class="clay-circle-btn" @click="noteModalOpen = false">
          <X :size="18" />
        </button>
      </header>

      <div class="modal-form-body">
        <label class="form-label">Yapılan işlem veya görüşme notu</label>
        <textarea
          v-model="noteText"
          rows="4"
          placeholder="Notunuzu buraya yazın…"
          class="form-textarea"
        ></textarea>
      </div>

      <footer class="modal-footer">
        <button
          type="button"
          class="clay-submit-btn"
          :disabled="actionLoading || !noteText.trim()"
          @click="submitNote"
        >
          <span v-if="actionLoading" class="loading-spinner"></span>
          <span>Notu Kaydet</span>
        </button>
      </footer>
    </div>

    <!-- Sonuçlandır Modalı -->
    <div v-if="closeModalOpen" class="modal-backdrop" @click="closeModalOpen = false"></div>
    <div class="modal-dialog-sheet" :class="{ 'is-open': closeModalOpen }">
      <header class="modal-head">
        <h3 class="modal-title">İhbarı Sonuçlandır</h3>
        <button type="button" class="clay-circle-btn" @click="closeModalOpen = false">
          <X :size="18" />
        </button>
      </header>

      <div class="modal-form-body space-y-3">
        <div>
          <label class="form-label">Sonuç Türü</label>
          <div class="result-toggle-group">
            <button
              type="button"
              class="result-toggle-btn"
              :class="{ 'is-active-emerald': closeResultType === 'olumlu' }"
              @click="closeResultType = 'olumlu'"
            >
              <CheckCircle2 :size="18" />
              <span>Olumlu (Kaçak Tespit Edildi)</span>
            </button>
            <button
              type="button"
              class="result-toggle-btn"
              :class="{ 'is-active-rose': closeResultType === 'olumsuz' }"
              @click="closeResultType = 'olumsuz'"
            >
              <XCircle :size="18" />
              <span>Olumsuz (Kaçak Bulunamadı)</span>
            </button>
          </div>
        </div>

        <div>
          <label class="form-label">Tutanak No (Opsiyonel)</label>
          <input
            v-model="closeTutanakNo"
            type="text"
            placeholder="Örn: 2026/A-12345"
            class="form-input"
          />
        </div>

        <div v-if="closeResultType === 'olumsuz'">
          <label class="form-label text-rose-500">Olumsuz Sonuç Gerekçesi (Zorunlu)</label>
          <textarea
            v-model="closeReason"
            rows="3"
            placeholder="Neden olumsuz sonuçlandı? Açıklayınız…"
            class="form-textarea"
          ></textarea>
        </div>
      </div>

      <footer class="modal-footer">
        <button
          type="button"
          class="clay-submit-btn clay-submit-btn--success"
          :disabled="actionLoading"
          @click="submitClose"
        >
          <span v-if="actionLoading" class="loading-spinner"></span>
          <span>Sonucu Kaydet</span>
        </button>
      </footer>
    </div>

    <!-- Personel Performans / Liderlik Tablosu Sheet -->
    <div v-if="ihbarState.statsOpen" class="modal-backdrop" @click="closeIhbarStats"></div>
    <div class="ihbar-stats-sheet" :class="{ 'is-open': ihbarState.statsOpen }">
      <div class="drawer-drag-handle"></div>

      <header class="stats-header">
        <div class="min-w-0">
          <div class="flex items-center gap-2">
            <Trophy :size="22" class="text-amber-500" />
            <h2 class="stats-title">Personel Performansı</h2>
            <span class="stats-count-tag">{{ statsData?.toplam_personel || 0 }} Personel</span>
          </div>
          <p class="stats-subtitle">
            {{ baslangicTarihi.split('-').reverse().join('.') }} – {{ bitisTarihi.split('-').reverse().join('.') }}
          </p>
        </div>

        <div class="stats-header-actions">
          <button
            type="button"
            class="stats-excel-btn"
            @click="exportStatsExcel"
          >
            <FileSpreadsheet :size="16" />
            <span>Excel</span>
          </button>
          <button type="button" class="clay-circle-btn" @click="closeIhbarStats">
            <X :size="18" />
          </button>
        </div>
      </header>

      <div v-if="statsLoading" class="drawer-loading">
        <div class="loading-spinner"></div>
        <p>İstatistikler hazırlanıyor…</p>
      </div>

      <div v-else-if="statsData" class="stats-body">
        <!-- 4'lü Üst KPI Kartları -->
        <div class="stats-kpi-grid">
          <div class="stats-kpi-card">
            <div class="kpi-icon-bubble bg-violet-100 text-violet-600">
              <User :size="16" />
            </div>
            <div>
              <span class="kpi-label">Bildirim Yapan</span>
              <strong class="kpi-val">{{ statsData.toplam_personel }} Kişi</strong>
            </div>
          </div>

          <div class="stats-kpi-card">
            <div class="kpi-icon-bubble bg-amber-100 text-amber-600">
              <Award :size="16" />
            </div>
            <div class="min-w-0">
              <span class="kpi-label">En Çok Gönderen</span>
              <strong class="kpi-val truncate">{{ statsData.en_cok_gonderen?.ad || '-' }}</strong>
              <small class="kpi-sub text-amber-600 font-semibold">{{ statsData.en_cok_gonderen?.toplam || 0 }} İhbar</small>
            </div>
          </div>

          <div class="stats-kpi-card">
            <div class="kpi-icon-bubble bg-emerald-100 text-emerald-600">
              <CheckCircle2 :size="16" />
            </div>
            <div class="min-w-0">
              <span class="kpi-label">En Yüksek Başarı</span>
              <strong class="kpi-val truncate">{{ statsData.en_basarili?.ad || '-' }}</strong>
              <small class="kpi-sub text-emerald-600 font-semibold">%{{ statsData.en_basarili?.oran || 0 }} Olumlu</small>
            </div>
          </div>

          <div class="stats-kpi-card">
            <div class="kpi-icon-bubble bg-orange-100 text-orange-600">
              <Send :size="16" />
            </div>
            <div>
              <span class="kpi-label">Saha İhbar Toplamı</span>
              <strong class="kpi-val">{{ statsData.saha_ihbar_toplam }} İhbar</strong>
            </div>
          </div>
        </div>

        <!-- Arama Çubuğu -->
        <div class="stats-search-wrap">
          <Search :size="16" class="search-icon" />
          <input
            v-model="statsSearch"
            type="search"
            placeholder="Personel ara…"
            class="stats-search-input"
          />
        </div>

        <!-- Personel Detay Kartları -->
        <div class="stats-personel-stack">
          <article
            v-for="p in filteredStatsList"
            :key="p.ad"
            class="stats-person-card bento-card bento-card--white"
          >
            <div class="person-head">
              <div class="person-info">
                <div class="person-avatar">
                  {{ p.ad.charAt(0).toLocaleUpperCase('tr-TR') }}
                </div>
                <div>
                  <h4 class="person-name">{{ p.ad }}</h4>
                  <span class="person-date">Son İhbar: {{ p.son_ihbar_tarihi }}</span>
                </div>
              </div>
              <button
                type="button"
                class="view-person-ihbarlar-btn"
                @click="filterByPersonel(p.ad)"
              >
                <Search :size="13" />
                <span>İhbarları Gör</span>
              </button>
            </div>

            <!-- Sayı Rozetleri (Toplam, Olumlu, Olumsuz, Devam) -->
            <div class="person-counts-grid">
              <div class="count-box">
                <span>Toplam</span>
                <strong>{{ p.toplam }}</strong>
              </div>
              <div class="count-box count-box--emerald">
                <span>Olumlu</span>
                <strong>{{ p.olumlu }}</strong>
              </div>
              <div class="count-box count-box--rose">
                <span>Olumsuz</span>
                <strong>{{ p.olumsuz }}</strong>
              </div>
              <div class="count-box count-box--amber">
                <span>Devam</span>
                <strong>{{ p.bekleyen }}</strong>
              </div>
            </div>

            <!-- Başarı Oranı Barı -->
            <div class="person-rate-row">
              <div class="rate-text-line">
                <span>Başarı Oranı</span>
                <span class="rate-percent">
                  <b>%{{ p.oran }}</b>
                  <small>({{ p.olumlu }} olumlu / {{ p.sonuclanan }} sonuçlanan)</small>
                </span>
              </div>
              <div class="person-rate-track">
                <div
                  class="person-rate-fill"
                  :style="{ width: `${p.oran}%` }"
                ></div>
              </div>
            </div>
          </article>
        </div>
      </div>
    </div>

    <!-- Yeni İhbar Oluşturma Modalı (Sheet) -->
    <div v-if="ihbarState.newIhbarOpen" class="modal-backdrop" @click="closeNewIhbarModal"></div>
    <div class="modal-dialog-sheet new-ihbar-sheet" :class="{ 'is-open': ihbarState.newIhbarOpen }">
      <header class="modal-head">
        <div>
          <h3 class="modal-title">Yeni İhbar Kaydı</h3>
          <p class="modal-subtitle">Sahada tespit edilen kaçak ihbarını bildirin</p>
        </div>
        <button type="button" class="clay-circle-btn" @click="closeNewIhbarModal">
          <X :size="18" />
        </button>
      </header>

      <div class="new-ihbar-body space-y-3">
        <!-- İlçe & Mahalle -->
        <div class="form-row-2">
          <div>
            <label class="form-label">İlçe *</label>
            <select v-model="newForm.ilce" class="form-select">
              <option value="">İlçe seçiniz</option>
              <option v-for="ilce in ilceler" :key="ilce" :value="ilce">{{ ilce }}</option>
            </select>
          </div>
          <div>
            <label class="form-label">Mahalle *</label>
            <input
              v-model="newForm.mahalle"
              type="text"
              placeholder="Mahalle adı"
              class="form-input"
            />
          </div>
        </div>

        <!-- İletişim Telefonu & Komşu Abone -->
        <div class="form-row-2">
          <div>
            <label class="form-label">Telefon (İsteğe bağlı)</label>
            <input
              v-model="newForm.telefon"
              type="tel"
              placeholder="05XX XXX XX XX"
              class="form-input"
            />
          </div>
          <div>
            <label class="form-label">Komşu Abone No</label>
            <input
              v-model="newForm.komsu_abone_no"
              type="text"
              placeholder="Komşu abone no"
              class="form-input"
            />
          </div>
        </div>

        <!-- Açıklama -->
        <div>
          <label class="form-label">Açıklama / Kaçak Detayı *</label>
          <textarea
            v-model="newForm.aciklama"
            rows="3"
            placeholder="Kaçak durumu, sayaç yeri, şüpheli detaylar…"
            class="form-textarea"
          ></textarea>
        </div>

        <!-- Konum Seçenekleri -->
        <div class="bento-card bento-card--white p-3 space-y-2 border border-slate-100">
          <label class="form-label font-bold flex items-center gap-1.5">
            <Navigation :size="15" class="text-primary" />
            <span>Konum Bilgisi *</span>
          </label>

          <div class="flex items-center gap-2">
            <button
              type="button"
              class="clay-filter-pill is-active flex-1 flex items-center justify-center gap-1.5 py-2.5"
              :disabled="gettingLocation"
              @click="getCurrentLocation"
            >
              <MapPin :size="16" />
              <span>{{ gettingLocation ? 'Alınıyor…' : (newForm.konum_lat ? 'Konum Güncelle' : 'Cihaz GPS Konumumu Al') }}</span>
            </button>
          </div>

          <div v-if="newForm.konum_lat" class="location-preview-box">
            <span class="text-xs text-emerald-600 font-bold flex items-center gap-1">
              <Check :size="14" />
              GPS Konumu Alındı: {{ newForm.konum_lat }}, {{ newForm.konum_lng }}
            </span>
          </div>

          <div>
            <label class="form-label text-[11px] text-slate-400">Veya Harita Linki</label>
            <input
              v-model="newForm.konum_link"
              type="url"
              placeholder="https://maps.google.com/..."
              class="form-input text-xs"
            />
          </div>
        </div>

        <!-- Fotoğraf Yükleme -->
        <div class="bento-card bento-card--white p-3 space-y-2 border border-slate-100">
          <div class="flex items-center justify-between">
            <label class="form-label font-bold flex items-center gap-1.5 mb-0">
              <Camera :size="15" class="text-primary" />
              <span>Fotoğraflar (En fazla 5)</span>
            </label>
            <span class="text-xs text-slate-400">{{ newForm.fotograflar.length }} / 5</span>
          </div>

          <!-- Yüklenen Fotoğraflar Önizleme -->
          <div v-if="fotoPreviewList.length" class="foto-preview-grid">
            <div
              v-for="(src, idx) in fotoPreviewList"
              :key="idx"
              class="foto-preview-item"
            >
              <img :src="src" alt="Önizleme" class="preview-img" />
              <button
                type="button"
                class="remove-foto-btn"
                @click="removeFoto(idx)"
              >
                <Trash2 :size="12" />
              </button>
            </div>
          </div>

          <label v-if="newForm.fotograflar.length < 5" class="foto-upload-btn">
            <Camera :size="20" />
            <span>Kamera ile Çek / Galeriden Seç</span>
            <input
              type="file"
              accept="image/*"
              multiple
              capture="environment"
              class="hidden"
              @change="handleFileSelect"
            />
          </label>
        </div>
      </div>

      <footer class="modal-footer">
        <button
          type="button"
          class="clay-submit-btn"
          :disabled="actionLoading"
          @click="submitNewIhbar"
        >
          <span v-if="actionLoading" class="loading-spinner"></span>
          <span>İhbarı Kaydet ve Gönder</span>
        </button>
      </footer>
    </div>

    <!-- Tam Ekran Fotoğraf Lightbox -->
    <div
      v-if="lightboxOpen && lightboxImages.length"
      class="lightbox-modal"
      @click.self="closeLightbox"
    >
      <header class="lightbox-head">
        <span class="lightbox-counter">
          {{ lightboxIndex + 1 }} / {{ lightboxImages.length }}
        </span>
        <button type="button" class="lightbox-close-btn" @click="closeLightbox">
          <X :size="22" />
        </button>
      </header>

      <div class="lightbox-image-stage">
        <img
          :src="ihbarApi.getPhotoUrl(lightboxImages[lightboxIndex].token)"
          alt="Büyük ihbar fotoğrafı"
          class="lightbox-img"
        />
      </div>

      <button
        v-if="lightboxImages.length > 1"
        type="button"
        class="lightbox-nav-btn lightbox-nav-btn--prev"
        @click.stop="prevPhoto"
      >
        <ChevronLeft :size="28" />
      </button>

      <button
        v-if="lightboxImages.length > 1"
        type="button"
        class="lightbox-nav-btn lightbox-nav-btn--next"
        @click.stop="nextPhoto"
      >
        <ChevronRight :size="28" />
      </button>
    </div>

    <!-- Toast Bildirimi -->
    <Transition name="fade">
      <div v-if="toastMessage" class="clay-toast-popup">
        {{ toastMessage }}
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.ihbar-view {
  min-height: 100vh;
  padding-bottom: 96px;
  background-color: var(--ds-canvas, var(--dsx-bg-e6eaef));
}

.ihbar-content {
  padding: 12px 14px 24px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

/* Obsidian Bento Hero */
.ihbar-hero-card {
  padding: 18px 16px;
  border-radius: var(--ds-radius-card, 28px);
  background: var(--dsx-bg-191d24);
  color: #fff;
  box-shadow: var(--ds-shadow-bento-dark, 0 10px 30px rgba(0, 0, 0, 0.25));
}

.ihbar-hero-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 14px;
}

.ihbar-hero-eyebrow {
  display: block;
  font-size: 10px;
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

.seg-pill--blue {
  background: #38bdf8;
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
}

.date-range-text {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  color: var(--dsx-fg-64748b);
  font-weight: 600;
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
  color: var(--dsx-fg-94a3b8);
  pointer-events: none;
}

.search-input {
  width: 100%;
  height: 42px;
  padding: 0 36px 0 38px;
  border-radius: 14px;
  border: 1px solid var(--dsx-bd-e2e8f0);
  background: var(--dsx-bg-f8fafc);
  font-size: 13px;
  color: var(--dsx-fg-1e293b);
  outline: none;
  transition: all 0.2s ease;
}

.search-input:focus {
  border-color: #3b82f6;
  background: var(--dsx-bg-ffffff);
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
  color: var(--dsx-fg-94a3b8);
}

.status-pills-scroll {
  display: flex;
  gap: 6px;
  overflow-x: auto;
  padding-bottom: 2px;
  scrollbar-width: none;
}

.status-pills-scroll::-webkit-scrollbar {
  display: none;
}

.pill-badge {
  font-size: 10px;
  background: var(--dsx-bg-000000-a008);
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
  border: 1px solid var(--dsx-bd-e2e8f0);
  background: var(--dsx-bg-f8fafc);
  padding: 0 10px;
  font-size: 12px;
  font-weight: 600;
  color: var(--dsx-fg-475569);
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
  color: var(--dsx-fg-64748b);
}

.clear-all-filter-btn {
  color: var(--dsx-fg-ea580c);
  font-weight: 700;
  font-size: 11px;
}

/* İhbar Kartı */
.ihbar-cards-stack {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.ihbar-card {
  padding: 14px;
  border-radius: 20px;
  cursor: pointer;
  border: 1px solid var(--dsx-bd-ffffff-a090);
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
  background: var(--dsx-bg-eff6ff);
  color: #2563eb;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.location-title {
  font-size: 14px;
  font-weight: 800;
  color: var(--dsx-fg-0f172a);
  margin: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.location-sub {
  color: var(--dsx-fg-64748b);
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
  background: var(--dsx-bg-f1f5f9);
  color: var(--dsx-fg-475569);
}

.badge--sky {
  background: var(--dsx-bg-e0f2fe);
  color: var(--dsx-fg-0369a1);
}

.badge--amber {
  background: var(--dsx-bg-fef3c7);
  color: var(--dsx-fg-b45309);
}

.badge--emerald {
  background: var(--dsx-bg-d1fae5);
  color: var(--dsx-fg-047857);
}

.badge--rose {
  background: var(--dsx-bg-ffe4e6);
  color: var(--dsx-fg-be123c);
}

.ihbar-card-desc {
  font-size: 12px;
  color: var(--dsx-fg-475569);
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
  background: var(--dsx-bg-f1f5f9);
  color: var(--dsx-fg-475569);
}

.mini-tag--foto {
  background: var(--dsx-bg-fdf2f8);
  color: var(--dsx-fg-be185d);
}

.mini-tag--gps {
  background: var(--dsx-bg-eff6ff);
  color: var(--dsx-fg-1d4ed8);
}

.mini-tag--tutanak {
  background: var(--dsx-bg-ecfdf5);
  color: var(--dsx-fg-047857);
}

.ihbar-card-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-top: 8px;
  border-top: 1px solid var(--dsx-bd-f1f5f9);
  font-size: 11px;
  color: var(--dsx-fg-94a3b8);
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
  color: var(--dsx-fg-64748b);
  font-weight: 600;
  max-width: 140px;
}

.footer-item--team {
  color: #3b82f6;
}

.footer-date {
  font-size: 10px;
  color: var(--dsx-fg-94a3b8);
  white-space: nowrap;
}

/* Durum Kartı (Boş & Yükleme) */
.ihbar-state-card {
  padding: 40px 16px;
  text-align: center;
  border-radius: 20px;
  background: var(--dsx-bg-ffffff);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  color: var(--dsx-fg-64748b);
}

.ihbar-state-card strong {
  color: var(--dsx-fg-1e293b);
  font-size: 15px;
}

.ihbar-state-card p {
  font-size: 12px;
  margin: 0;
}

.loading-spinner {
  width: 22px;
  height: 22px;
  border: 2px solid var(--dsx-bd-e2e8f0);
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
  background: var(--dsx-bg-ffffff);
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
  background: var(--dsx-bg-cbd5e1);
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
  border-bottom: 1px solid var(--dsx-bd-f1f5f9);
}

.drawer-eyebrow {
  display: block;
  font-size: 9px;
  font-weight: 800;
  letter-spacing: 0.1em;
  color: var(--dsx-fg-ea580c);
}

.drawer-title,
.modal-title,
.stats-title {
  font-size: 16px;
  font-weight: 800;
  color: var(--dsx-fg-0f172a);
  margin: 0;
}

.modal-subtitle,
.stats-subtitle {
  font-size: 11px;
  color: var(--dsx-fg-94a3b8);
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

.drawer-loading {
  padding: 50px 0;
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  color: var(--dsx-fg-64748b);
}

/* Detay Satırları */
.detail-info-bento {
  padding: 14px;
  border-radius: 18px;
  border: 1px solid var(--dsx-bd-f1f5f9);
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
  color: var(--dsx-fg-94a3b8);
  text-transform: uppercase;
}

.detail-val {
  color: var(--dsx-fg-1e293b);
}

.detail-phone-link {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  color: #2563eb;
  font-weight: 700;
  background: var(--dsx-bg-eff6ff);
  padding: 4px 10px;
  border-radius: 10px;
}

.detail-reason-box {
  background: var(--dsx-bg-fff1f2);
  border: 1px solid var(--dsx-bd-ffe4e6);
  border-radius: 10px;
  padding: 8px 10px;
  color: var(--dsx-fg-be123c);
  font-size: 12px;
  margin: 0;
  width: 100%;
}

.detail-desc-bento {
  padding: 14px;
  border-radius: 18px;
  border: 1px solid var(--dsx-bd-f1f5f9);
}

.detail-desc-text {
  margin: 6px 0 0 0;
  font-size: 13px;
  color: var(--dsx-fg-334155);
  line-height: 1.5;
}

.detail-map-bento {
  padding: 12px 14px;
  border-radius: 18px;
  border: 1px solid var(--dsx-bd-f1f5f9);
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.map-bento-left {
  display: flex;
  align-items: center;
  gap: 10px;
}

.map-icon-box {
  width: 36px;
  height: 36px;
  border-radius: 12px;
  background: var(--dsx-bg-eff6ff);
  display: flex;
  align-items: center;
  justify-content: center;
}

/* Galeri */
.gallery-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.gallery-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
}

.gallery-item-tile {
  position: relative;
  aspect-ratio: 1;
  border-radius: 14px;
  overflow: hidden;
  background: var(--dsx-bg-0f172a);
  cursor: pointer;
}

.gallery-thumb {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.gallery-zoom-overlay {
  position: absolute;
  right: 6px;
  bottom: 6px;
  width: 26px;
  height: 26px;
  border-radius: 8px;
  background: rgba(0, 0, 0, 0.6);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
}

/* Timeline */
.timeline-stack {
  margin-top: 8px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  border-left: 2px solid var(--dsx-bd-e2e8f0);
  padding-left: 14px;
  margin-left: 6px;
}

.timeline-item {
  position: relative;
}

.timeline-dot {
  position: absolute;
  left: -19px;
  top: 4px;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #3b82f6;
  border: 2px solid var(--dsx-bd-ffffff);
}

.timeline-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.timeline-user {
  font-size: 12px;
  color: var(--dsx-fg-1e293b);
}

.timeline-date {
  font-size: 10px;
  color: var(--dsx-fg-94a3b8);
}

.timeline-text {
  font-size: 12px;
  color: var(--dsx-fg-64748b);
  margin: 2px 0 0 0;
}

/* Aksiyon Butonları */
.drawer-action-buttons {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding-top: 8px;
}

.clay-action-btn {
  width: 100%;
  height: 46px;
  border-radius: 14px;
  font-weight: 700;
  font-size: 13px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  transition: all 0.2s ease;
  border: none;
}

.clay-action-btn--primary {
  background: #4f46e5;
  color: #fff;
  box-shadow: 0 4px 14px rgba(79, 70, 229, 0.3);
}

.clay-action-btn--secondary {
  background: #334155;
  color: #fff;
}

.clay-action-btn--success {
  background: #059669;
  color: #fff;
  box-shadow: 0 4px 14px rgba(5, 150, 105, 0.3);
}

.clay-action-btn--warning {
  background: #d97706;
  color: #fff;
}

.clay-action-btn:active {
  transform: scale(0.98);
}

/* Form Modalları */
.modal-form-body {
  padding: 16px;
  overflow-y: auto;
}

.form-label {
  display: block;
  font-size: 11px;
  font-weight: 700;
  color: var(--dsx-fg-64748b);
  margin-bottom: 4px;
}

.form-input,
.form-select,
.form-textarea {
  width: 100%;
  border-radius: 12px;
  border: 1px solid var(--dsx-bd-e2e8f0);
  background: var(--dsx-bg-f8fafc);
  padding: 10px 12px;
  font-size: 13px;
  color: var(--dsx-fg-1e293b);
  outline: none;
}

.form-input:focus,
.form-select:focus,
.form-textarea:focus {
  border-color: #3b82f6;
  background: var(--dsx-bg-ffffff);
}

.form-row-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
}

.modal-footer {
  padding: 12px 16px;
  border-top: 1px solid var(--dsx-bd-f1f5f9);
}

.clay-submit-btn {
  width: 100%;
  height: 48px;
  border-radius: 14px;
  background: #ea580c;
  color: #fff;
  font-weight: 800;
  font-size: 14px;
  border: none;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  box-shadow: 0 4px 14px rgba(234, 88, 12, 0.35);
}

.clay-submit-btn--success {
  background: #059669;
  box-shadow: 0 4px 14px rgba(5, 150, 105, 0.35);
}

.clay-submit-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* Personel Seçimi */
.modal-search-wrap,
.stats-search-wrap {
  position: relative;
  padding: 10px 16px;
}

.modal-search-input,
.stats-search-input {
  width: 100%;
  height: 38px;
  padding: 0 12px 0 34px;
  border-radius: 12px;
  border: 1px solid var(--dsx-bd-e2e8f0);
  background: var(--dsx-bg-f8fafc);
  font-size: 12px;
  outline: none;
}

.personel-selection-list {
  padding: 0 16px 12px;
  max-height: 40vh;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.personel-select-row {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px;
  border-radius: 14px;
  border: 1px solid var(--dsx-bd-f1f5f9);
  background: var(--dsx-bg-f8fafc);
  cursor: pointer;
}

.personel-select-row.is-selected {
  border-color: #4f46e5;
  background: var(--dsx-bg-eef2ff);
}

.checkbox-input {
  width: 18px;
  height: 18px;
  accent-color: #4f46e5;
}

.personel-avatar-cell {
  width: 32px;
  height: 32px;
  border-radius: 10px;
  background: var(--dsx-bg-e0e7ff);
  color: #4338ca;
  font-weight: 800;
  font-size: 13px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.personel-name {
  display: block;
  font-size: 13px;
  color: var(--dsx-fg-1e293b);
}

.personel-dept {
  display: block;
  font-size: 10px;
  color: var(--dsx-fg-94a3b8);
}

/* Sonuç Türü Toggle Butonları */
.result-toggle-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.result-toggle-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px;
  border-radius: 14px;
  border: 1px solid var(--dsx-bd-e2e8f0);
  background: var(--dsx-bg-f8fafc);
  font-size: 13px;
  font-weight: 700;
  color: var(--dsx-fg-475569);
  text-align: left;
}

.result-toggle-btn.is-active-emerald {
  border-color: #10b981;
  background: var(--dsx-bg-ecfdf5);
  color: var(--dsx-fg-047857);
}

.result-toggle-btn.is-active-rose {
  border-color: #f43f5e;
  background: var(--dsx-bg-fff1f2);
  color: var(--dsx-fg-be123c);
}

/* Personel İstatistik / Liderlik */
.stats-count-tag {
  background: var(--dsx-bg-ffedd5);
  color: var(--dsx-fg-ea580c);
  font-size: 11px;
  font-weight: 800;
  padding: 2px 8px;
  border-radius: 999px;
}

.stats-header-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.stats-excel-btn {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 6px 12px;
  border-radius: 10px;
  background: var(--dsx-bg-ecfdf5);
  color: var(--dsx-fg-047857);
  font-size: 12px;
  font-weight: 700;
  border: 1px solid var(--dsx-bd-a7f3d0);
}

.stats-kpi-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
}

.stats-kpi-card {
  padding: 10px;
  border-radius: 14px;
  background: var(--dsx-bg-f8fafc);
  border: 1px solid var(--dsx-bd-f1f5f9);
  display: flex;
  align-items: center;
  gap: 8px;
}

.kpi-icon-bubble {
  width: 32px;
  height: 32px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.kpi-label {
  display: block;
  font-size: 9px;
  color: var(--dsx-fg-94a3b8);
  font-weight: 700;
  text-transform: uppercase;
}

.kpi-val {
  display: block;
  font-size: 12px;
  color: var(--dsx-fg-1e293b);
}

.kpi-sub {
  display: block;
  font-size: 10px;
}

.stats-personel-stack {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.stats-person-card {
  padding: 12px;
  border-radius: 18px;
  border: 1px solid var(--dsx-bd-f1f5f9);
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.person-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.person-info {
  display: flex;
  align-items: center;
  gap: 8px;
}

.person-avatar {
  width: 34px;
  height: 34px;
  border-radius: 10px;
  background: #ea580c;
  color: #fff;
  font-weight: 900;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
}

.person-name {
  font-size: 13px;
  font-weight: 800;
  color: var(--dsx-fg-0f172a);
  margin: 0;
}

.person-date {
  font-size: 10px;
  color: var(--dsx-fg-94a3b8);
}

.view-person-ihbarlar-btn {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 5px 10px;
  border-radius: 10px;
  background: var(--dsx-bg-ffedd5);
  color: var(--dsx-fg-ea580c);
  font-size: 11px;
  font-weight: 700;
}

.person-counts-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 4px;
  text-align: center;
}

.count-box {
  background: var(--dsx-bg-f8fafc);
  padding: 6px 2px;
  border-radius: 10px;
}

.count-box span {
  display: block;
  font-size: 9px;
  color: var(--dsx-fg-94a3b8);
}

.count-box strong {
  display: block;
  font-size: 12px;
  color: var(--dsx-fg-334155);
}

.count-box--emerald {
  background: var(--dsx-bg-ecfdf5);
}

.count-box--emerald strong {
  color: var(--dsx-fg-047857);
}

.count-box--rose {
  background: var(--dsx-bg-fff1f2);
}

.count-box--rose strong {
  color: var(--dsx-fg-be123c);
}

.count-box--amber {
  background: var(--dsx-bg-fffbeb);
}

.count-box--amber strong {
  color: var(--dsx-fg-b45309);
}

.person-rate-row {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.rate-text-line {
  display: flex;
  justify-content: space-between;
  font-size: 11px;
  color: var(--dsx-fg-64748b);
  font-weight: 600;
}

.rate-percent b {
  color: var(--dsx-fg-047857);
}

.rate-percent small {
  color: var(--dsx-fg-94a3b8);
  margin-left: 4px;
}

.person-rate-track {
  height: 6px;
  background: var(--dsx-bg-e2e8f0);
  border-radius: 999px;
  overflow: hidden;
}

.person-rate-fill {
  height: 100%;
  background: linear-gradient(90deg, #10b981, #14b8a6);
  border-radius: 999px;
}

/* Yeni İhbar */
.new-ihbar-sheet {
  max-height: 92vh;
}

.new-ihbar-body {
  padding: 16px;
  overflow-y: auto;
}

.location-preview-box {
  background: var(--dsx-bg-ecfdf5);
  padding: 6px 10px;
  border-radius: 8px;
}

.foto-preview-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 6px;
}

.foto-preview-item {
  position: relative;
  aspect-ratio: 1;
  border-radius: 10px;
  overflow: hidden;
  border: 1px solid var(--dsx-bd-e2e8f0);
}

.preview-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.remove-foto-btn {
  position: absolute;
  top: 4px;
  right: 4px;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: rgba(225, 29, 72, 0.9);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
}

.foto-upload-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  height: 44px;
  border: 1px dashed var(--dsx-bd-cbd5e1);
  border-radius: 12px;
  background: var(--dsx-bg-f8fafc);
  color: var(--dsx-fg-475569);
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
}

/* Lightbox Modal */
.lightbox-modal {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.95);
  z-index: 100;
  display: flex;
  flex-direction: column;
}

.lightbox-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  color: #fff;
  z-index: 10;
}

.lightbox-counter {
  font-size: 13px;
  font-weight: 800;
}

.lightbox-close-btn {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.15);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
}

.lightbox-image-stage {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
}

.lightbox-img {
  max-width: 100%;
  max-height: 80vh;
  object-fit: contain;
  border-radius: 8px;
}

.lightbox-nav-btn {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  width: 44px;
  height: 44px;
  border-radius: 50%;
  background: rgba(0, 0, 0, 0.6);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
}

.lightbox-nav-btn--prev {
  left: 12px;
}

.lightbox-nav-btn--next {
  right: 12px;
}

/* Toast */
.clay-toast-popup {
  position: fixed;
  bottom: 84px;
  left: 50%;
  transform: translateX(-50%);
  background: var(--dsx-bg-0f172a);
  color: #fff;
  font-size: 12px;
  font-weight: 700;
  padding: 10px 18px;
  border-radius: 999px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
  z-index: 120;
  white-space: nowrap;
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.25s ease, transform 0.25s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
  transform: translate(-50%, 10px);
}
</style>
