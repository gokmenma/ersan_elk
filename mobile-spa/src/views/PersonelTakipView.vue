<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import {
  AlarmClock,
  ArrowLeft,
  CalendarDays,
  ChartNoAxesColumn,
  ChevronLeft,
  ChevronRight,
  Clock3,
  Crosshair,
  ExternalLink,
  LoaderCircle,
  LocateFixed,
  Map as MapIcon,
  MapPin,
  MessageSquareText,
  Phone,
  Radio,
  RefreshCw,
  Save,
  Search,
  Settings2,
  Timer,
  TrendingUp,
  UsersRound,
  X,
} from '@lucide/vue'
import { auth } from '../services/auth.js'
import { personelTakipApi } from '../services/personel-takip.js'

const router = useRouter()

const STATUS_META = {
  gorevde: { label: 'Görevde', tone: 'emerald' },
  tamamladi: { label: 'Tamamladı', tone: 'blue' },
  kapanmadi: { label: 'Bitiş yok', tone: 'coral' },
  izinli: { label: 'İzinli', tone: 'purple' },
  baslamadi: { label: 'Başlamadı', tone: 'slate' },
}
const DAY_STATUS = {
  zamaninda: { label: 'Zamanında', tone: 'emerald' },
  gec: { label: 'Geç başladı', tone: 'amber' },
  gorevde: { label: 'Devam ediyor', tone: 'blue' },
  acik_kaldi: { label: 'Kapanmadı', tone: 'coral' },
  izinli: { label: 'İzinli', tone: 'purple' },
  kayit_yok: { label: 'Kayıt yok', tone: 'slate' },
}
const DEFAULT_CENTER = [37.5753, 36.9228]

function isoDate(d) {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}
function shiftDate(iso, days) {
  const d = new Date(`${iso}T12:00:00`)
  d.setDate(d.getDate() + days)
  return isoDate(d)
}
function presetRange(preset, end = isoDate(new Date())) {
  if (preset === '30') return { baslangic: shiftDate(end, -29), bitis: end }
  if (preset === 'ay') return { baslangic: `${end.slice(0, 8)}01`, bitis: end }
  return { baslangic: shiftDate(end, -6), bitis: end }
}
function longDate(iso) {
  return new Date(`${iso}T12:00:00`).toLocaleDateString('tr-TR', { day: 'numeric', month: 'long', weekday: 'long' })
}
function shortDate(iso) {
  return new Date(`${iso}T12:00:00`).toLocaleDateString('tr-TR', { day: '2-digit', month: 'short' })
}
function duration(minutes) {
  const m = Math.max(0, Math.round(Number(minutes) || 0))
  if (m < 60) return `${m} dk`
  return `${Math.floor(m / 60)} sa${m % 60 ? ` ${m % 60} dk` : ''}`
}
function initials(name = '') {
  const parts = name.trim().split(/\s+/).filter(Boolean)
  return (parts.length > 1 ? parts[0][0] + parts.at(-1)[0] : name.slice(0, 2)).toLocaleUpperCase('tr-TR') || '?'
}
function fold(text = '') {
  return text.toLocaleLowerCase('tr-TR')
}
function mapsUrl(lat, lng) {
  return `https://www.google.com/maps?q=${lat},${lng}`
}
function ageLabel(minutes) {
  if (minutes < 1) return 'şimdi'
  if (minutes < 60) return `${minutes} dk önce`
  return `${Math.floor(minutes / 60)} sa önce`
}

const activeTab = ref('canli')
const toast = ref('')
let toastTimer = null
function showToast(message) {
  toast.value = message
  clearTimeout(toastTimer)
  toastTimer = setTimeout(() => { toast.value = '' }, 2600)
}

const today = isoDate(new Date())
const selectedDate = ref(today)
const day = ref(null)
const dayLoading = ref(true)
const dayRefreshing = ref(false)
const dayError = ref('')
const statusFilter = ref('all')
const departmentFilter = ref('')
const search = ref('')
const sortBy = ref('durum')
const bulkRequesting = ref(false)
const requestingIds = ref(new Set())
let refreshTimer = null

const isToday = computed(() => selectedDate.value === today)
const canShiftSettings = computed(() => Boolean(day.value?.can_mesai))
const summary = computed(() => day.value?.ozet || { toplam: 0, gorevde: 0, kapanmadi: 0, tamamladi: 0, baslamadi: 0, izinli: 0, gec: 0, toplam_dakika: 0 })
const departments = computed(() => day.value?.departmanlar || [])
const segments = computed(() => {
  const total = Math.max(1, summary.value.toplam)
  return ['gorevde', 'tamamladi', 'kapanmadi', 'izinli', 'baslamadi']
    .map(key => ({ key, ...STATUS_META[key], count: summary.value[key] || 0, pct: ((summary.value[key] || 0) / total) * 100 }))
    .filter(seg => seg.key !== 'kapanmadi' || seg.count > 0)
})
const attendancePct = computed(() => {
  const s = summary.value
  const expected = s.toplam - s.izinli
  return expected > 0 ? Math.round((fieldCount.value / expected) * 100) : 0
})
const fieldCount = computed(() => summary.value.gorevde + summary.value.tamamladi + (summary.value.kapanmadi || 0))
const filterChips = computed(() => [
  { key: 'all', label: 'Tümü', count: summary.value.toplam },
  { key: 'gorevde', label: 'Görevde', count: summary.value.gorevde },
  { key: 'tamamladi', label: 'Tamamladı', count: summary.value.tamamladi },
  { key: 'kapanmadi', label: 'Bitiş yok', count: summary.value.kapanmadi || 0, tone: 'coral' },
  { key: 'gec', label: 'Geç', count: summary.value.gec, tone: 'amber' },
  { key: 'baslamadi', label: 'Başlamadı', count: summary.value.baslamadi },
  { key: 'izinli', label: 'İzinli', count: summary.value.izinli },
].filter(chip => chip.key !== 'kapanmadi' || chip.count > 0))
const STATUS_ORDER = { gorevde: 0, kapanmadi: 1, tamamladi: 2, baslamadi: 3, izinli: 4 }
const filteredItems = computed(() => {
  const q = fold(search.value.trim())
  const list = (day.value?.items || []).filter(item => {
    if (statusFilter.value === 'gec' && item.gecikme_dk === null) return false
    if (!['all', 'gec'].includes(statusFilter.value) && item.durum !== statusFilter.value) return false
    if (departmentFilter.value && item.departman !== departmentFilter.value) return false
    if (q && !fold(`${item.adi_soyadi} ${item.departman} ${item.gorev}`).includes(q)) return false
    return true
  })
  const sorters = {
    ad: (a, b) => a.adi_soyadi.localeCompare(b.adi_soyadi, 'tr'),
    baslama: (a, b) => (a.baslama || '99').localeCompare(b.baslama || '99'),
    gecikme: (a, b) => (b.gecikme_dk ?? -1) - (a.gecikme_dk ?? -1),
    sure: (a, b) => b.dakika - a.dakika,
    durum: (a, b) => (STATUS_ORDER[a.durum] - STATUS_ORDER[b.durum]) || a.adi_soyadi.localeCompare(b.adi_soyadi, 'tr'),
  }
  return [...list].sort(sorters[sortBy.value] || sorters.durum)
})

async function loadDay(silent = false) {
  if (silent) dayRefreshing.value = true
  else dayLoading.value = true
  dayError.value = ''
  try {
    day.value = await personelTakipApi.day({ tarih: selectedDate.value })
    if (activeTab.value === 'harita') nextTick(renderMarkers)
  } catch (err) {
    dayError.value = err.message
  } finally {
    dayLoading.value = false
    dayRefreshing.value = false
  }
}
function changeDate(offset) {
  const next = shiftDate(selectedDate.value, offset)
  if (next > today) return
  selectedDate.value = next
}
watch(selectedDate, () => {
  if (selectedDate.value !== today) mapMode.value = 'gorev'
  loadDay()
})

function startAutoRefresh() {
  clearInterval(refreshTimer)
  refreshTimer = setInterval(() => {
    if (!document.hidden && isToday.value && activeTab.value !== 'analiz' && !detailOpen.value) loadDay(true)
  }, 60000)
}

async function requestLocation(item) {
  if (requestingIds.value.has(item.id)) return
  requestingIds.value = new Set([...requestingIds.value, item.id])
  try {
    const res = await personelTakipApi.requestLocation(item.id, auth.state.csrfToken)
    item.konum_bekleniyor = true
    showToast(res.message)
  } catch (err) {
    showToast(err.message)
  } finally {
    const next = new Set(requestingIds.value)
    next.delete(item.id)
    requestingIds.value = next
  }
}
async function requestAllLocations() {
  if (bulkRequesting.value) return
  bulkRequesting.value = true
  try {
    const res = await personelTakipApi.requestAllLocations(auth.state.csrfToken)
    showToast(res.message)
    loadDay(true)
  } catch (err) {
    showToast(err.message)
  } finally {
    bulkRequesting.value = false
  }
}

const mapEl = ref(null)
const mapMode = ref('gorev')
const mapLoading = ref(false)
let L = null
let map = null
let markerLayer = null

const mapItems = computed(() => filteredItems.value
  .map(item => {
    const loc = mapMode.value === 'canli' ? item.canli_konum : item.gorev_konum
    return loc && loc.lat && loc.lng ? { item, loc } : null
  })
  .filter(Boolean))
const missingLocation = computed(() => filteredItems.value.length - mapItems.value.length)

async function ensureMap() {
  if (map) {
    map.invalidateSize()
    renderMarkers()
    return
  }
  mapLoading.value = true
  try {
    const [mod] = await Promise.all([import('leaflet'), import('leaflet/dist/leaflet.css')])
    L = mod.default || mod
    await nextTick()
    if (!mapEl.value) return
    map = L.map(mapEl.value, { zoomControl: false, attributionControl: true }).setView(DEFAULT_CENTER, 11)
    L.control.zoom({ position: 'bottomright' }).addTo(map)
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '© OpenStreetMap' }).addTo(map)
    markerLayer = L.layerGroup().addTo(map)
    renderMarkers(true)
  } catch {
    showToast('Harita yüklenemedi.')
  } finally {
    mapLoading.value = false
  }
}
function escapeHtml(value = '') {
  return String(value).replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]))
}
function renderMarkers(fit = false) {
  if (!map || !markerLayer || !L) return
  markerLayer.clearLayers()
  const bounds = []
  for (const { item, loc } of mapItems.value) {
    const late = item.gecikme_dk !== null ? ' is-late' : ''
    const icon = L.divIcon({
      className: 'pt-marker-wrap',
      html: `<span class="pt-marker pt-marker--${item.durum}${late}">${escapeHtml(initials(item.adi_soyadi))}</span>`,
      iconSize: [38, 38],
      iconAnchor: [19, 19],
    })
    const when = mapMode.value === 'canli' ? ageLabel(loc.yas_dk) : (loc.zaman || '').slice(11, 16)
    const marker = L.marker([loc.lat, loc.lng], { icon, title: item.adi_soyadi })
    marker.bindTooltip(`${escapeHtml(item.adi_soyadi)} · ${escapeHtml(when)}`, { direction: 'top', offset: [0, -18] })
    marker.on('click', () => openDetail(item))
    marker.addTo(markerLayer)
    bounds.push([loc.lat, loc.lng])
  }
  if (fit && bounds.length) map.fitBounds(bounds, { padding: [40, 40], maxZoom: 15 })
}
function fitAll() {
  if (!map) return
  const bounds = mapItems.value.map(({ loc }) => [loc.lat, loc.lng])
  if (bounds.length) map.fitBounds(bounds, { padding: [40, 40], maxZoom: 15 })
  else map.setView(DEFAULT_CENTER, 11)
}
watch([mapMode, statusFilter, departmentFilter, search], () => renderMarkers(true))
watch(activeTab, tab => {
  if (tab === 'harita') nextTick(ensureMap)
  if (tab === 'analiz' && !analysis.value) loadAnalysis()
})

const analysisPreset = ref('7')
const analysisRange = ref(presetRange('7'))
const analysisDepartment = ref('')
const analysis = ref(null)
const analysisLoading = ref(false)
const analysisError = ref('')
const perfSort = ref('saat')
const activeBar = ref(null)

async function loadAnalysis() {
  analysisLoading.value = true
  analysisError.value = ''
  try {
    analysis.value = await personelTakipApi.analysis({ ...analysisRange.value, departman: analysisDepartment.value })
    activeBar.value = null
  } catch (err) {
    analysisError.value = err.message
  } finally {
    analysisLoading.value = false
  }
}
function setAnalysisPreset(preset) {
  analysisPreset.value = preset
  if (preset !== 'ozel') {
    analysisRange.value = presetRange(preset)
    loadAnalysis()
  }
}
function applyCustomRange() {
  if (!analysisRange.value.baslangic || !analysisRange.value.bitis) return
  loadAnalysis()
}
watch(analysisDepartment, () => loadAnalysis())

const trendMax = computed(() => Math.max(1, ...(analysis.value?.trend || []).map(t => t.saat)))
const bucketMax = computed(() => Math.max(1, ...(analysis.value?.baslama_dagilimi || []).map(b => b.adet)))
const trendLabelStep = computed(() => Math.max(1, Math.ceil((analysis.value?.trend?.length || 1) / 8)))
const topLate = computed(() => [...(analysis.value?.personeller || [])]
  .filter(p => p.gec > 0)
  .sort((a, b) => (b.gec - a.gec) || (b.gecikme_dk - a.gecikme_dk))
  .slice(0, 5))
const performanceList = computed(() => {
  const list = [...(analysis.value?.personeller || [])].filter(p => p.gun > 0)
  if (perfSort.value === 'dakiklik') return list.sort((a, b) => (b.dakiklik ?? -1) - (a.dakiklik ?? -1))
  if (perfSort.value === 'gecikme') return list.sort((a, b) => b.gecikme_dk - a.gecikme_dk)
  return list.sort((a, b) => b.toplam_saat - a.toplam_saat)
})
const inactivePeople = computed(() => (analysis.value?.personeller || []).filter(p => p.gun === 0))
function punctualityTone(value) {
  if (value === null || value === undefined) return 'slate'
  if (value >= 90) return 'emerald'
  if (value >= 70) return 'amber'
  return 'coral'
}

const detailOpen = ref(false)
const detail = ref(null)
const detailBase = ref(null)
const detailLoading = ref(false)
const detailError = ref('')
const detailTab = ref('gunler')
const detailPreset = ref('7')
const detailEnd = ref(today)
const noteEditing = ref(null)
const noteDraft = ref('')
const noteSaving = ref(false)

async function loadDetail() {
  if (!detailBase.value) return
  detailLoading.value = true
  detailError.value = ''
  try {
    detail.value = await personelTakipApi.personnel({ id: detailBase.value.id, ...presetRange(detailPreset.value, detailEnd.value) })
  } catch (err) {
    detailError.value = err.message
  } finally {
    detailLoading.value = false
  }
}
function openDetail(item) {
  detailBase.value = item
  detailEnd.value = activeTab.value === 'analiz' ? (analysis.value?.aralik?.bitis || today) : selectedDate.value
  detail.value = null
  detailTab.value = 'gunler'
  detailPreset.value = '7'
  noteEditing.value = null
  detailOpen.value = true
  loadDetail()
}
function closeDetail() {
  detailOpen.value = false
  noteEditing.value = null
}
function setDetailPreset(preset) {
  detailPreset.value = preset
  loadDetail()
}
function editNote(row) {
  noteEditing.value = row.tarih
  noteDraft.value = row.aciklama || ''
}
async function saveNote(row) {
  if (noteSaving.value) return
  noteSaving.value = true
  try {
    const res = await personelTakipApi.saveNote({ id: detailBase.value.id, tarih: row.tarih, aciklama: noteDraft.value.trim() }, auth.state.csrfToken)
    row.aciklama = noteDraft.value.trim()
    row.aciklama_yazan = auth.state.user?.name || row.aciklama_yazan
    noteEditing.value = null
    showToast(res.message)
    if (row.tarih === selectedDate.value) {
      const item = day.value?.items?.find(i => i.id === detailBase.value.id)
      if (item) item.aciklama = row.aciklama
    }
  } catch (err) {
    showToast(err.message)
  } finally {
    noteSaving.value = false
  }
}
function openNoteFromList(item) {
  openDetail(item)
  const stop = watch(detail, value => {
    const row = value?.gunler?.find(g => g.tarih === selectedDate.value)
    if (row) {
      editNote(row)
      stop()
    }
  })
}
const timelineGroups = computed(() => {
  const groups = []
  for (const h of detail.value?.hareketler || []) {
    let group = groups.at(-1)
    if (!group || group.tarih !== h.tarih) {
      group = { tarih: h.tarih, items: [] }
      groups.push(group)
    }
    group.items.push(h)
  }
  return groups
})
const maxDayMinutes = computed(() => Math.max(600, ...(detail.value?.gunler || []).map(g => g.dakika)))

const shiftOpen = ref(false)
const shiftItems = ref([])
const shiftLoading = ref(false)
const shiftSaving = ref(false)
async function openShiftSettings() {
  shiftOpen.value = true
  shiftLoading.value = true
  try {
    const res = await personelTakipApi.shiftSettings()
    shiftItems.value = res.items || []
  } catch (err) {
    showToast(err.message)
    shiftOpen.value = false
  } finally {
    shiftLoading.value = false
  }
}
async function saveShiftSettings() {
  if (shiftSaving.value) return
  shiftSaving.value = true
  try {
    const res = await personelTakipApi.saveShiftSettings(shiftItems.value, auth.state.csrfToken)
    showToast(res.message)
    shiftOpen.value = false
    loadDay(true)
    if (analysis.value) loadAnalysis()
  } catch (err) {
    showToast(err.message)
  } finally {
    shiftSaving.value = false
  }
}

function refreshActive() {
  if (activeTab.value === 'analiz') loadAnalysis()
  else loadDay(true)
}
function handleKeydown(event) {
  if (event.key !== 'Escape') return
  if (shiftOpen.value) shiftOpen.value = false
  else if (detailOpen.value) closeDetail()
}
watch([detailOpen, shiftOpen], ([a, b]) => {
  document.body.style.overflow = a || b ? 'hidden' : ''
})

onMounted(() => {
  loadDay()
  startAutoRefresh()
  window.addEventListener('keydown', handleKeydown)
})
onBeforeUnmount(() => {
  clearInterval(refreshTimer)
  clearTimeout(toastTimer)
  window.removeEventListener('keydown', handleKeydown)
  document.body.style.overflow = ''
  if (map) {
    map.remove()
    map = null
  }
})
</script>

<template>
  <section class="ds-screen pt-screen">
    <header class="clay-topbar">
      <div class="clay-topbar-main">
        <button class="clay-circle-btn" type="button" aria-label="Geri" @click="router.back()"><ArrowLeft :size="19" /></button>
        <div class="clay-topbar-title-block">
          <span class="clay-topbar-eyebrow">SAHA & MESAİ</span>
          <h1 class="clay-topbar-title">Personel Takip</h1>
          <p class="clay-topbar-subtitle">Görev başlama, bitiş ve konum takibi</p>
        </div>
      </div>
      <div class="pt-top-actions">
        <button v-if="canShiftSettings" class="clay-circle-btn" type="button" aria-label="Departman mesai saatleri" @click="openShiftSettings"><Settings2 :size="18" /></button>
        <button class="clay-circle-btn" type="button" aria-label="Yenile" :disabled="dayRefreshing || analysisLoading" @click="refreshActive"><RefreshCw :size="18" :class="{ spin: dayRefreshing || analysisLoading }" /></button>
      </div>
    </header>

    <div class="pt-tabs" role="tablist">
      <button type="button" role="tab" :aria-selected="activeTab === 'canli'" :class="{ active: activeTab === 'canli' }" @click="activeTab = 'canli'"><Radio :size="15" />Canlı Durum</button>
      <button type="button" role="tab" :aria-selected="activeTab === 'harita'" :class="{ active: activeTab === 'harita' }" @click="activeTab = 'harita'"><MapIcon :size="15" />Harita</button>
      <button type="button" role="tab" :aria-selected="activeTab === 'analiz'" :class="{ active: activeTab === 'analiz' }" @click="activeTab = 'analiz'"><ChartNoAxesColumn :size="15" />Analiz</button>
    </div>

    <template v-if="activeTab !== 'analiz'">
      <div class="pt-date-bar">
        <button type="button" aria-label="Önceki gün" @click="changeDate(-1)"><ChevronLeft :size="18" /></button>
        <label class="pt-date-label">
          <CalendarDays :size="15" />
          <span>{{ isToday ? 'Bugün · ' : '' }}{{ longDate(selectedDate) }}</span>
          <input v-model="selectedDate" type="date" :max="today" aria-label="Tarih seç">
        </label>
        <button type="button" aria-label="Sonraki gün" :disabled="isToday" @click="changeDate(1)"><ChevronRight :size="18" /></button>
      </div>

      <div v-if="dayLoading" class="clay-skeleton-grid">
        <div class="clay-skeleton-card" style="height: 168px;"></div>
        <div v-for="i in 4" :key="i" class="clay-skeleton-card" style="height: 76px;"></div>
      </div>
      <div v-else-if="dayError" class="pt-state pt-state--error">
        <p>{{ dayError }}</p>
        <button type="button" @click="loadDay()">Tekrar dene</button>
      </div>

      <template v-else>
        <article v-if="activeTab === 'canli'" class="pt-hero bento-card--dark">
          <div class="pt-hero__top">
            <div>
              <span class="pt-eyebrow"><i v-if="isToday" class="pt-live-dot"></i>{{ isToday ? `CANLI · ${day.sunucu_saati}` : 'GÜN SONU ÖZETİ' }}</span>
              <h2><strong>{{ fieldCount }}</strong> / {{ summary.toplam }} <small>personel sahada</small></h2>
              <p>%{{ attendancePct }} katılım · {{ duration(summary.toplam_dakika) }} toplam çalışma</p>
            </div>
            <span class="pt-hero__icon"><UsersRound :size="20" /></span>
          </div>
          <div class="pt-track" role="img" :aria-label="segments.map(s => `${s.label} ${s.count}`).join(', ')">
            <span v-for="seg in segments" v-show="seg.count > 0" :key="seg.key" :class="`tone-${seg.tone}`" :style="{ width: `${seg.pct}%` }"></span>
          </div>
          <div class="pt-legend">
            <button v-for="seg in segments" :key="seg.key" type="button" :class="{ active: statusFilter === seg.key }" @click="statusFilter = statusFilter === seg.key ? 'all' : seg.key">
              <i :class="`tone-${seg.tone}`"></i>{{ seg.label }} <strong>{{ seg.count }}</strong>
            </button>
          </div>
          <div class="pt-hero__foot">
            <button type="button" class="pt-late-btn" :class="{ active: statusFilter === 'gec' }" @click="statusFilter = statusFilter === 'gec' ? 'all' : 'gec'">
              <AlarmClock :size="15" /><span><strong>{{ summary.gec }}</strong> geç kalan</span>
            </button>
            <button v-if="isToday && summary.gorevde > 0" type="button" class="pt-locate-btn" :disabled="bulkRequesting" @click="requestAllLocations">
              <LoaderCircle v-if="bulkRequesting" :size="15" class="spin" /><LocateFixed v-else :size="15" />Görevdekilerden konum iste
            </button>
          </div>
        </article>

        <div class="pt-chips">
          <button v-for="chip in filterChips" :key="chip.key" type="button" :class="[{ active: statusFilter === chip.key }, chip.tone ? `chip-${chip.tone}` : '']" @click="statusFilter = chip.key">
            {{ chip.label }} <span>{{ chip.count }}</span>
          </button>
        </div>

        <div class="pt-filter-row">
          <div class="pt-search">
            <Search :size="17" />
            <input v-model="search" type="search" placeholder="Personel veya departman ara" aria-label="Personel ara">
            <button v-if="search" type="button" aria-label="Aramayı temizle" @click="search = ''"><X :size="15" /></button>
          </div>
        </div>
        <div class="pt-filter-row pt-filter-row--selects">
          <label class="pt-select">
            <span>Departman</span>
            <select v-model="departmentFilter" aria-label="Departman filtresi">
              <option value="">Tümü</option>
              <option v-for="dep in departments" :key="dep" :value="dep">{{ dep }}</option>
            </select>
          </label>
          <label v-if="activeTab === 'canli'" class="pt-select">
            <span>Sırala</span>
            <select v-model="sortBy" aria-label="Sıralama">
              <option value="durum">Duruma göre</option>
              <option value="ad">Ada göre</option>
              <option value="baslama">Başlama saati</option>
              <option value="gecikme">En çok geciken</option>
              <option value="sure">Çalışma süresi</option>
            </select>
          </label>
          <div v-else class="pt-map-mode">
            <button type="button" :class="{ active: mapMode === 'gorev' }" @click="mapMode = 'gorev'">Görev konumu</button>
            <button type="button" :class="{ active: mapMode === 'canli' }" :disabled="!isToday" @click="mapMode = 'canli'">Canlı konum</button>
          </div>
        </div>

        <div v-show="activeTab === 'harita'" class="pt-map-card">
          <div ref="mapEl" class="pt-map"></div>
          <div v-if="mapLoading" class="pt-map-loading"><LoaderCircle :size="24" class="spin" /></div>
          <button type="button" class="pt-map-fit" aria-label="Tüm personeli göster" @click="fitAll"><Crosshair :size="18" /></button>
          <div class="pt-map-foot">
            <span><MapPin :size="14" /><strong>{{ mapItems.length }}</strong> konumlu</span>
            <span v-if="missingLocation > 0">{{ missingLocation }} personelin {{ mapMode === 'canli' ? 'bugün canlı konumu' : 'görev konumu' }} yok</span>
            <span class="pt-map-legend">
              <i class="tone-emerald"></i>Görevde <i class="tone-blue"></i>Tamamladı <i class="tone-amber-ring"></i>Geç
            </span>
          </div>
        </div>

        <div v-if="activeTab === 'canli'">
          <div v-if="!filteredItems.length" class="pt-state">
            <span class="pt-empty-icon"><UsersRound :size="24" /></span>
            <strong>Personel bulunamadı</strong>
            <span>Filtrelere uygun saha takibindeki personel yok.</span>
          </div>
          <div v-else class="pt-list">
            <article v-for="item in filteredItems" :key="item.id" class="pt-card" @click="openDetail(item)">
              <span class="pt-avatar" :class="`ring-${item.durum}`">
                <img v-if="item.foto" :src="item.foto" alt="" loading="lazy" @error="item.foto = null">
                <span v-else>{{ initials(item.adi_soyadi) }}</span>
                <i :class="`dot-${item.durum}`"></i>
              </span>
              <div class="pt-card__body">
                <div class="pt-card__head">
                  <strong>{{ item.adi_soyadi }}</strong>
                  <span class="pt-badge" :class="`tone-${STATUS_META[item.durum].tone}`">{{ item.durum === 'izinli' && item.izin_turu ? item.izin_turu : STATUS_META[item.durum].label }}</span>
                </div>
                <span class="pt-card__sub">{{ item.departman || 'Departman yok' }}<template v-if="item.gorev"> · {{ item.gorev }}</template></span>
                <div class="pt-card__times">
                  <span><Clock3 :size="13" />{{ item.baslama || '--:--' }} – {{ item.bitis || (item.durum === 'gorevde' ? 'devam' : '--:--') }}</span>
                  <span v-if="item.dakika"><Timer :size="13" />{{ duration(item.dakika) }}</span>
                  <span class="pt-muted">Mesai {{ item.mesai }}</span>
                </div>
                <div v-if="item.gecikme_dk !== null || item.otomatik_kapandi || item.canli_konum || item.aciklama || item.konum_bekleniyor" class="pt-card__tags">
                  <span v-if="item.gecikme_dk !== null" class="pt-tag tone-amber"><AlarmClock :size="12" />{{ item.baslama ? `${duration(item.gecikme_dk)} geç` : `${duration(item.gecikme_dk)} gecikiyor` }}</span>
                  <span v-if="item.otomatik_kapandi" class="pt-tag tone-coral">Otomatik kapandı</span>
                  <span v-if="item.canli_konum" class="pt-tag tone-emerald"><Radio :size="12" />Canlı · {{ ageLabel(item.canli_konum.yas_dk) }}</span>
                  <span v-if="item.konum_bekleniyor" class="pt-tag tone-blue"><LoaderCircle :size="12" class="spin" />Konum bekleniyor</span>
                  <span v-if="item.aciklama" class="pt-tag tone-slate" :title="item.aciklama"><MessageSquareText :size="12" />Açıklama</span>
                </div>
              </div>
              <div class="pt-card__actions" @click.stop>
                <a v-if="item.telefon" class="pt-icon-btn" :href="`tel:${item.telefon}`" :aria-label="`${item.adi_soyadi} ara`"><Phone :size="16" /></a>
                <button v-if="isToday && item.durum === 'gorevde'" type="button" class="pt-icon-btn" :disabled="requestingIds.has(item.id) || item.konum_bekleniyor" :aria-label="`${item.adi_soyadi} konum iste`" @click="requestLocation(item)">
                  <LoaderCircle v-if="requestingIds.has(item.id)" :size="16" class="spin" /><LocateFixed v-else :size="16" />
                </button>
                <button v-if="item.gecikme_dk !== null || item.durum === 'baslamadi'" type="button" class="pt-icon-btn" :aria-label="`${item.adi_soyadi} açıklama yaz`" @click="openNoteFromList(item)"><MessageSquareText :size="16" /></button>
              </div>
            </article>
          </div>
        </div>
      </template>
    </template>

    <template v-else>
      <div class="pt-chips">
        <button v-for="p in [{ k: '7', l: 'Son 7 gün' }, { k: '30', l: 'Son 30 gün' }, { k: 'ay', l: 'Bu ay' }, { k: 'ozel', l: 'Özel aralık' }]" :key="p.k" type="button" :class="{ active: analysisPreset === p.k }" @click="setAnalysisPreset(p.k)">{{ p.l }}</button>
      </div>
      <div v-if="analysisPreset === 'ozel'" class="pt-range">
        <label><span>Başlangıç</span><input v-model="analysisRange.baslangic" type="date" :max="analysisRange.bitis || today"></label>
        <label><span>Bitiş</span><input v-model="analysisRange.bitis" type="date" :min="analysisRange.baslangic" :max="today"></label>
        <button type="button" @click="applyCustomRange">Uygula</button>
      </div>
      <div class="pt-filter-row pt-filter-row--selects">
        <label class="pt-select pt-select--wide">
          <span>Departman</span>
          <select v-model="analysisDepartment" aria-label="Analiz departman filtresi">
            <option value="">Tüm departmanlar</option>
            <option v-for="dep in departments" :key="dep" :value="dep">{{ dep }}</option>
          </select>
        </label>
      </div>

      <div v-if="analysisLoading && !analysis" class="clay-skeleton-grid">
        <div v-for="i in 4" :key="i" class="clay-skeleton-card" style="height: 96px;"></div>
      </div>
      <div v-else-if="analysisError" class="pt-state pt-state--error">
        <p>{{ analysisError }}</p>
        <button type="button" @click="loadAnalysis">Tekrar dene</button>
      </div>
      <template v-else-if="analysis">
        <p class="pt-range-caption">{{ shortDate(analysis.aralik.baslangic) }} – {{ shortDate(analysis.aralik.bitis) }} · {{ analysis.kpi.takip_personel }} takipli personel</p>
        <div class="pt-kpis" :class="{ 'is-loading': analysisLoading }">
          <div class="pt-kpi">
            <span class="pt-kpi__icon tone-blue"><Timer :size="16" /></span>
            <span class="pt-kpi__label">Toplam çalışma</span>
            <strong>{{ analysis.kpi.toplam_saat.toLocaleString('tr-TR') }} <small>sa</small></strong>
            <span class="pt-kpi__foot">Kişi başı {{ analysis.kpi.kisi_basi_saat.toLocaleString('tr-TR') }} sa</span>
          </div>
          <div class="pt-kpi">
            <span class="pt-kpi__icon" :class="`tone-${punctualityTone(analysis.kpi.dakiklik)}`"><TrendingUp :size="16" /></span>
            <span class="pt-kpi__label">Dakiklik</span>
            <strong>{{ analysis.kpi.dakiklik === null ? '—' : `%${analysis.kpi.dakiklik}` }}</strong>
            <span class="pt-kpi__foot">{{ analysis.kpi.zamaninda }} zamanında giriş</span>
          </div>
          <div class="pt-kpi">
            <span class="pt-kpi__icon tone-amber"><AlarmClock :size="16" /></span>
            <span class="pt-kpi__label">Geç giriş</span>
            <strong>{{ analysis.kpi.gec }}</strong>
            <span class="pt-kpi__foot">Toplam {{ duration(analysis.kpi.gecikme_dk) }}</span>
          </div>
          <div class="pt-kpi">
            <span class="pt-kpi__icon tone-emerald"><UsersRound :size="16" /></span>
            <span class="pt-kpi__label">Katılım</span>
            <strong>%{{ analysis.kpi.katilim }}</strong>
            <span class="pt-kpi__foot">{{ analysis.kpi.aktif_personel }} aktif · {{ analysis.kpi.izin_gun }} izin günü</span>
          </div>
        </div>

        <article class="pt-panel">
          <header><h3>Günlük çalışma saati</h3><span>Toplam saat · çubuğa dokunun</span></header>
          <div class="pt-trend" role="list">
            <button
              v-for="(t, index) in analysis.trend"
              :key="t.tarih"
              type="button"
              role="listitem"
              class="pt-trend__col"
              :class="{ active: activeBar === t.tarih }"
              :aria-label="`${shortDate(t.tarih)}: ${t.saat} saat, ${t.kisi} kişi, ${t.gec} geç giriş`"
              @click="activeBar = activeBar === t.tarih ? null : t.tarih"
              @mouseenter="activeBar = t.tarih"
            >
              <span class="pt-trend__bar" :style="{ height: `${Math.max(2, (t.saat / trendMax) * 100)}%` }"></span>
              <span class="pt-trend__label">{{ index % trendLabelStep === 0 ? shortDate(t.tarih).split(' ')[0] : '' }}</span>
            </button>
          </div>
          <div class="pt-trend__tip">
            <template v-if="activeBar">
              <template v-for="t in analysis.trend.filter(x => x.tarih === activeBar)" :key="t.tarih">
                <strong>{{ shortDate(t.tarih) }} {{ t.gun_adi }}</strong>
                <span>{{ t.saat.toLocaleString('tr-TR') }} sa</span><span>{{ t.kisi }} kişi</span><span>{{ t.zamaninda }} zamanında</span><span>{{ t.gec }} geç</span>
              </template>
            </template>
            <span v-else class="pt-muted">En yüksek gün {{ trendMax.toLocaleString('tr-TR') }} sa</span>
          </div>
        </article>

        <article class="pt-panel">
          <header><h3>İşe başlama saatleri</h3><span>Giriş sayısı</span></header>
          <div class="pt-hbars">
            <div v-for="b in analysis.baslama_dagilimi" :key="b.etiket" class="pt-hbar">
              <span class="pt-hbar__label">{{ b.etiket }}</span>
              <span class="pt-hbar__track"><span :style="{ width: `${(b.adet / bucketMax) * 100}%` }"></span></span>
              <strong>{{ b.adet }}</strong>
            </div>
          </div>
        </article>

        <article v-if="analysis.departmanlar.length > 1" class="pt-panel">
          <header><h3>Departmanlar</h3><span>Dakiklik oranı</span></header>
          <div class="pt-dept-list">
            <div v-for="d in analysis.departmanlar" :key="d.departman" class="pt-dept">
              <div class="pt-dept__head">
                <strong>{{ d.departman }}</strong>
                <span class="pt-badge" :class="`tone-${punctualityTone(d.dakiklik)}`">{{ d.dakiklik === null ? '—' : `%${d.dakiklik}` }}</span>
              </div>
              <span class="pt-hbar__track"><span :class="`fill-${punctualityTone(d.dakiklik)}`" :style="{ width: `${d.dakiklik ?? 0}%` }"></span></span>
              <span class="pt-dept__meta">Mesai {{ d.mesai }} · {{ d.aktif }}/{{ d.personel }} aktif · kişi başı {{ d.ort_saat.toLocaleString('tr-TR') }} sa</span>
            </div>
          </div>
        </article>

        <article v-if="topLate.length" class="pt-panel">
          <header><h3>En çok gecikenler</h3><span>Geç giriş sayısı</span></header>
          <button v-for="(p, i) in topLate" :key="p.id" type="button" class="pt-rank" @click="openDetail(p)">
            <span class="pt-rank__no">{{ i + 1 }}</span>
            <span class="pt-rank__body"><strong>{{ p.adi_soyadi }}</strong><small>{{ p.departman }} · ort. başlama {{ p.ort_baslama || '—' }}</small></span>
            <span class="pt-rank__value"><strong>{{ p.gec }}×</strong><small>{{ duration(p.gecikme_dk) }}</small></span>
          </button>
        </article>

        <article class="pt-panel">
          <header>
            <h3>Personel performansı</h3>
            <div class="pt-seg">
              <button type="button" :class="{ active: perfSort === 'saat' }" @click="perfSort = 'saat'">Saat</button>
              <button type="button" :class="{ active: perfSort === 'dakiklik' }" @click="perfSort = 'dakiklik'">Dakiklik</button>
              <button type="button" :class="{ active: perfSort === 'gecikme' }" @click="perfSort = 'gecikme'">Gecikme</button>
            </div>
          </header>
          <button v-for="p in performanceList" :key="p.id" type="button" class="pt-perf" @click="openDetail(p)">
            <span class="pt-avatar pt-avatar--sm">
              <img v-if="p.foto" :src="p.foto" alt="" loading="lazy" @error="p.foto = null">
              <span v-else>{{ initials(p.adi_soyadi) }}</span>
            </span>
            <span class="pt-perf__body">
              <strong>{{ p.adi_soyadi }}</strong>
              <small>{{ p.gun }} gün · ort. {{ p.ort_saat.toLocaleString('tr-TR') }} sa/gün · başlama {{ p.ort_baslama || '—' }}</small>
            </span>
            <span class="pt-perf__value">
              <strong>{{ p.toplam_saat.toLocaleString('tr-TR') }} sa</strong>
              <span class="pt-badge" :class="`tone-${punctualityTone(p.dakiklik)}`">{{ p.dakiklik === null ? '—' : `%${p.dakiklik}` }}</span>
            </span>
          </button>
          <p v-if="inactivePeople.length" class="pt-inactive"><strong>{{ inactivePeople.length }} personelin</strong> bu aralıkta hiç görev kaydı yok: {{ inactivePeople.slice(0, 6).map(p => p.adi_soyadi).join(', ') }}{{ inactivePeople.length > 6 ? '…' : '' }}</p>
        </article>
      </template>
    </template>

    <Transition name="pt-sheet">
      <div v-if="detailOpen" class="pt-layer" @click.self="closeDetail">
        <div class="pt-sheet" role="dialog" aria-modal="true" aria-label="Personel takip detayı">
          <header class="pt-sheet__head">
            <span class="pt-avatar">
              <img v-if="detailBase.foto" :src="detailBase.foto" alt="" @error="detailBase.foto = null">
              <span v-else>{{ initials(detailBase.adi_soyadi) }}</span>
            </span>
            <div class="pt-sheet__title">
              <strong>{{ detailBase.adi_soyadi }}</strong>
              <span>{{ detail?.personel?.departman || detailBase.departman || 'Departman yok' }}<template v-if="detail?.personel?.mesai"> · Mesai {{ detail.personel.mesai }}</template></span>
            </div>
            <a v-if="detail?.personel?.telefon" class="pt-icon-btn" :href="`tel:${detail.personel.telefon}`" aria-label="Personeli ara"><Phone :size="17" /></a>
            <button type="button" class="pt-icon-btn" aria-label="Kapat" @click="closeDetail"><X :size="18" /></button>
          </header>

          <div class="pt-sheet__body">
            <div class="pt-chips pt-chips--tight">
              <button v-for="p in [{ k: '7', l: '7 gün' }, { k: '30', l: '30 gün' }, { k: 'ay', l: 'Bu ay' }]" :key="p.k" type="button" :class="{ active: detailPreset === p.k }" @click="setDetailPreset(p.k)">{{ p.l }}</button>
            </div>

            <div v-if="detailLoading && !detail" class="pt-state"><LoaderCircle :size="26" class="spin" /><span>Yükleniyor…</span></div>
            <div v-else-if="detailError" class="pt-state pt-state--error"><p>{{ detailError }}</p><button type="button" @click="loadDetail">Tekrar dene</button></div>
            <template v-else-if="detail">
              <div class="pt-mini-grid" :class="{ 'is-loading': detailLoading }">
                <div><span>Çalışılan gün</span><strong>{{ detail.ozet.calisilan_gun }}</strong></div>
                <div><span>Toplam süre</span><strong>{{ detail.ozet.toplam_saat.toLocaleString('tr-TR') }} sa</strong></div>
                <div><span>Günlük ort.</span><strong>{{ detail.ozet.ort_saat.toLocaleString('tr-TR') }} sa</strong></div>
                <div><span>Dakiklik</span><strong :class="`text-tone-${punctualityTone(detail.ozet.dakiklik)}`">{{ detail.ozet.dakiklik === null ? '—' : `%${detail.ozet.dakiklik}` }}</strong></div>
                <div><span>Ort. başlama</span><strong>{{ detail.ozet.ort_baslama || '—' }}</strong></div>
                <div><span>Ort. bitiş</span><strong>{{ detail.ozet.ort_bitis || '—' }}</strong></div>
                <div><span>Geç giriş</span><strong>{{ detail.ozet.gec }} <small>({{ duration(detail.ozet.gecikme_dk) }})</small></strong></div>
                <div><span>İzinli / kayıtsız</span><strong>{{ detail.ozet.izinli }} / {{ detail.ozet.kayitsiz }}</strong></div>
              </div>

              <div class="pt-seg pt-seg--full">
                <button type="button" :class="{ active: detailTab === 'gunler' }" @click="detailTab = 'gunler'">Günler</button>
                <button type="button" :class="{ active: detailTab === 'hareketler' }" @click="detailTab = 'hareketler'">Hareketler <span>{{ detail.hareketler.length }}</span></button>
                <button type="button" :class="{ active: detailTab === 'aciklamalar' }" @click="detailTab = 'aciklamalar'">Açıklamalar <span>{{ detail.aciklama_gecmisi.length }}</span></button>
              </div>

              <div v-if="detailTab === 'gunler'" class="pt-days">
                <div v-for="g in detail.gunler" :key="g.tarih" class="pt-day" :class="{ 'is-weekend': g.hafta_sonu }">
                  <div class="pt-day__date"><strong>{{ g.tarih.slice(8, 10) }}</strong><span>{{ g.gun_adi }}</span></div>
                  <div class="pt-day__body">
                    <div class="pt-day__head">
                      <span class="pt-badge" :class="`tone-${DAY_STATUS[g.durum].tone}`">{{ g.durum === 'izinli' && g.izin_turu ? g.izin_turu : DAY_STATUS[g.durum].label }}</span>
                      <span v-if="g.baslama" class="pt-day__time">{{ g.baslama }} – {{ g.bitis || '…' }}</span>
                      <span v-if="g.dakika" class="pt-muted">{{ duration(g.dakika) }}</span>
                    </div>
                    <span v-if="g.dakika" class="pt-hbar__track pt-day__bar"><span :class="g.gecikme_dk !== null ? 'fill-amber' : 'fill-blue'" :style="{ width: `${(g.dakika / maxDayMinutes) * 100}%` }"></span></span>
                    <div class="pt-day__meta">
                      <span v-if="g.gecikme_dk !== null" class="pt-tag tone-amber"><AlarmClock :size="12" />{{ duration(g.gecikme_dk) }} geç</span>
                      <span v-if="g.otomatik_kapandi" class="pt-tag tone-coral">Otomatik kapandı</span>
                      <a v-if="g.ilk_konum" class="pt-link" :href="mapsUrl(g.ilk_konum.lat, g.ilk_konum.lng)" target="_blank" rel="noopener"><MapPin :size="12" />Başlama</a>
                      <a v-if="g.son_konum" class="pt-link" :href="mapsUrl(g.son_konum.lat, g.son_konum.lng)" target="_blank" rel="noopener"><MapPin :size="12" />Bitiş</a>
                      <button v-if="noteEditing !== g.tarih" type="button" class="pt-link" @click="editNote(g)"><MessageSquareText :size="12" />{{ g.aciklama ? 'Düzenle' : 'Açıklama ekle' }}</button>
                    </div>
                    <p v-if="g.aciklama && noteEditing !== g.tarih" class="pt-note">“{{ g.aciklama }}”<small v-if="g.aciklama_yazan"> — {{ g.aciklama_yazan }}</small></p>
                    <div v-if="noteEditing === g.tarih" class="pt-note-edit">
                      <textarea v-model="noteDraft" rows="3" maxlength="1000" placeholder="Gecikme veya devamsızlık nedenini yazın" aria-label="Açıklama"></textarea>
                      <div>
                        <button type="button" class="pt-btn pt-btn--ghost" @click="noteEditing = null">Vazgeç</button>
                        <button type="button" class="pt-btn" :disabled="noteSaving" @click="saveNote(g)"><LoaderCircle v-if="noteSaving" :size="15" class="spin" /><Save v-else :size="15" />Kaydet</button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div v-else-if="detailTab === 'hareketler'" class="pt-timeline">
                <div v-if="!timelineGroups.length" class="pt-state"><span>Bu aralıkta hareket kaydı yok.</span></div>
                <div v-for="group in timelineGroups" :key="group.tarih" class="pt-timeline__group">
                  <h4>{{ longDate(group.tarih) }}</h4>
                  <div v-for="(h, i) in group.items" :key="`${group.tarih}-${i}`" class="pt-timeline__item" :class="h.tip === 'BASLA' ? 'is-start' : 'is-end'">
                    <i></i>
                    <div>
                      <strong>{{ h.saat }} · {{ h.tip === 'BASLA' ? 'Görev başladı' : 'Görev bitti' }}</strong>
                      <span v-if="h.aciklama" class="pt-muted">{{ h.aciklama }}</span>
                      <span class="pt-muted">
                        <a class="pt-link" :href="mapsUrl(h.lat, h.lng)" target="_blank" rel="noopener"><ExternalLink :size="12" />Haritada aç</a>
                        <template v-if="h.hassasiyet !== null"> · ±{{ h.hassasiyet }} m</template>
                      </span>
                    </div>
                  </div>
                </div>
              </div>

              <div v-else class="pt-notes">
                <div v-if="!detail.aciklama_gecmisi.length" class="pt-state"><span>Kayıtlı açıklama bulunmuyor.</span></div>
                <div v-for="n in detail.aciklama_gecmisi" :key="n.tarih" class="pt-note-card">
                  <strong>{{ longDate(n.tarih) }}</strong>
                  <p>{{ n.aciklama }}</p>
                  <small>{{ n.yazan || 'Bilinmiyor' }}</small>
                </div>
              </div>
            </template>
          </div>
        </div>
      </div>
    </Transition>

    <Transition name="pt-sheet">
      <div v-if="shiftOpen" class="pt-layer" @click.self="shiftOpen = false">
        <div class="pt-sheet pt-sheet--short" role="dialog" aria-modal="true" aria-label="Departman mesai saatleri">
          <header class="pt-sheet__head">
            <span class="pt-avatar tone-blue"><Clock3 :size="20" /></span>
            <div class="pt-sheet__title"><strong>İşe başlama saatleri</strong><span>Geç kalma bu saate göre hesaplanır</span></div>
            <button type="button" class="pt-icon-btn" aria-label="Kapat" @click="shiftOpen = false"><X :size="18" /></button>
          </header>
          <div class="pt-sheet__body">
            <div v-if="shiftLoading" class="pt-state"><LoaderCircle :size="26" class="spin" /></div>
            <div v-else class="pt-shift-list">
              <label v-for="s in shiftItems" :key="s.departman" class="pt-shift">
                <span>{{ s.departman }}</span>
                <input v-model="s.mesai_saati" type="time" required>
              </label>
            </div>
          </div>
          <footer class="pt-sheet__foot">
            <button type="button" class="pt-btn pt-btn--block" :disabled="shiftSaving || shiftLoading" @click="saveShiftSettings"><LoaderCircle v-if="shiftSaving" :size="16" class="spin" /><Save v-else :size="16" />Kaydet</button>
          </footer>
        </div>
      </div>
    </Transition>

    <Transition name="pt-toast"><div v-if="toast" class="pt-toast" role="status">{{ toast }}</div></Transition>
  </section>
</template>

<style scoped>
.pt-screen { padding-left: 16px; padding-right: 16px; }
.pt-top-actions { display: flex; gap: 7px; }
.pt-muted { color: var(--ds-muted); }

.pt-tabs { display: grid; grid-template-columns: repeat(3, 1fr); gap: 4px; padding: 4px; margin-bottom: 12px; border-radius: 999px; background: var(--ds-surface); box-shadow: var(--ds-shadow-tile); }
.pt-tabs button { display: flex; align-items: center; justify-content: center; gap: 6px; min-height: 40px; border: 0; border-radius: 999px; background: transparent; color: var(--ds-muted); font: 700 12px var(--ds-font-main); }
.pt-tabs button.active { background: var(--ds-primary); color: #fff; }

.pt-date-bar { display: flex; align-items: center; gap: 8px; margin-bottom: 12px; }
.pt-date-bar > button { display: grid; place-items: center; flex: 0 0 44px; height: 44px; border: 0; border-radius: 14px; background: var(--ds-surface); color: var(--ds-ink); box-shadow: var(--ds-shadow-tile); }
.pt-date-bar > button:disabled { opacity: .4; }
.pt-date-label { position: relative; flex: 1; display: flex; align-items: center; justify-content: center; gap: 7px; height: 44px; border-radius: 14px; background: var(--ds-surface); color: var(--ds-ink); box-shadow: var(--ds-shadow-tile); font: 700 13px var(--ds-font-main); text-transform: capitalize; }
.pt-date-label svg { color: var(--ds-muted); }
.pt-date-label input { position: absolute; inset: 0; width: 100%; opacity: 0; }

.pt-hero { padding: 16px 18px; border-radius: 24px; color: var(--ds-ink-light); background: var(--ds-surface-dark); box-shadow: var(--ds-shadow-bento-dark); margin-bottom: 12px; }
.pt-hero__top { display: flex; justify-content: space-between; gap: 12px; }
.pt-eyebrow { display: inline-flex; align-items: center; gap: 6px; font-size: 9px; font-weight: 800; letter-spacing: .13em; color: var(--ds-muted-light); }
.pt-live-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--ds-emerald); box-shadow: 0 0 0 0 rgba(16, 185, 129, .6); animation: pt-pulse 1.8s infinite; }
.pt-hero h2 { margin: 4px 0 2px; font-size: 16px; font-weight: 600; color: var(--ds-muted-light); }
.pt-hero h2 strong { font-size: 30px; color: #fff; letter-spacing: -.03em; }
.pt-hero h2 small { font-size: 12px; }
.pt-hero p { margin: 0; font-size: 11px; color: var(--ds-on-dark-muted); }
.pt-hero__icon { display: grid; place-items: center; flex: 0 0 40px; height: 40px; border-radius: 14px; background: var(--ds-on-dark-surface); color: var(--ds-emerald); }
.pt-track { display: flex; gap: 3px; height: 12px; margin: 14px 0 10px; }
.pt-track span { min-width: 6px; border-radius: 999px; transition: width .3s ease; }
.pt-legend { display: grid; grid-template-columns: repeat(2, 1fr); gap: 4px; }
.pt-legend button { display: flex; align-items: center; gap: 6px; min-height: 32px; padding: 0 9px; border: 1px solid transparent; border-radius: 10px; background: transparent; color: var(--ds-muted-light); font: 600 11px var(--ds-font-main); text-align: left; }
.pt-legend button strong { margin-left: auto; color: #fff; font-size: 13px; }
.pt-legend button.active { border-color: var(--ds-border-dark); background: var(--ds-on-dark-surface); }
.pt-legend i { width: 8px; height: 8px; border-radius: 3px; }
.pt-hero__foot { display: flex; gap: 8px; margin-top: 10px; flex-wrap: wrap; }
.pt-late-btn, .pt-locate-btn { display: flex; align-items: center; gap: 6px; min-height: 38px; padding: 0 12px; border: 1px solid var(--ds-border-dark); border-radius: 999px; background: transparent; color: var(--ds-muted-light); font: 600 11px var(--ds-font-main); }
.pt-late-btn strong { color: var(--ds-amber); font-size: 13px; }
.pt-late-btn.active { background: var(--ds-amber); color: var(--ds-ink); border-color: transparent; }
.pt-late-btn.active strong { color: var(--ds-ink); }
.pt-locate-btn { margin-left: auto; background: #fff; color: var(--ds-ink); border-color: transparent; }
.pt-locate-btn:disabled { opacity: .6; }

.pt-chips { display: flex; gap: 6px; overflow-x: auto; margin: 0 -16px 10px; padding: 2px 16px; scrollbar-width: none; }
.pt-chips::-webkit-scrollbar { display: none; }
.pt-chips button { flex: 0 0 auto; display: flex; align-items: center; gap: 5px; min-height: 36px; padding: 0 13px; border: 1px solid var(--ds-border); border-radius: 999px; background: var(--ds-surface); color: var(--ds-muted); font: 700 12px var(--ds-font-main); box-shadow: var(--ds-shadow-tile); }
.pt-chips button span { display: inline-grid; place-items: center; min-width: 20px; height: 20px; padding: 0 5px; border-radius: 999px; background: var(--ds-surface-subtle); font-size: 10px; }
.pt-chips button.active { background: var(--ds-primary); border-color: transparent; color: #fff; }
.pt-chips button.active span { background: rgba(255, 255, 255, .18); }
.pt-chips button.chip-amber:not(.active) span { background: var(--ds-amber-soft); color: var(--ds-amber-ink); }
.pt-chips button.chip-coral:not(.active) span { background: var(--ds-coral-soft); color: var(--ds-coral-ink); }
.pt-chips--tight { margin-bottom: 12px; }

.pt-filter-row { display: flex; gap: 8px; margin-bottom: 8px; }
.pt-filter-row--selects { margin-bottom: 12px; }
.pt-search { flex: 1; display: flex; align-items: center; gap: 8px; height: 46px; padding: 0 12px; border-radius: 15px; background: var(--ds-surface); box-shadow: var(--ds-shadow-tile); color: var(--ds-muted); }
.pt-search input { flex: 1; min-width: 0; height: 100%; border: 0; outline: 0; background: transparent; color: var(--ds-ink); font: 500 14px var(--ds-font-main); }
.pt-search button { display: grid; place-items: center; width: 30px; height: 30px; border: 0; border-radius: 10px; background: var(--ds-surface-subtle); color: var(--ds-muted); }
.pt-select { flex: 1; min-width: 0; display: flex; flex-direction: column; justify-content: center; height: 48px; padding: 0 12px; border-radius: 15px; background: var(--ds-surface); box-shadow: var(--ds-shadow-tile); }
.pt-select span { font-size: 9px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--ds-muted); }
.pt-select select { width: 100%; border: 0; outline: 0; padding: 0; background: transparent; color: var(--ds-ink); font: 700 13px var(--ds-font-main); }
.pt-map-mode { flex: 1.3; display: grid; grid-template-columns: 1fr 1fr; gap: 3px; padding: 3px; border-radius: 15px; background: var(--ds-surface); box-shadow: var(--ds-shadow-tile); }
.pt-map-mode button { border: 0; border-radius: 12px; background: transparent; color: var(--ds-muted); font: 700 11px var(--ds-font-main); }
.pt-map-mode button.active { background: var(--ds-primary); color: #fff; }
.pt-map-mode button:disabled { opacity: .4; }

.pt-map-card { position: relative; overflow: hidden; border-radius: 22px; background: var(--ds-surface); box-shadow: var(--ds-shadow-bento); }
.pt-map { height: min(62dvh, 520px); background: var(--ds-surface-subtle); z-index: 0; }
.pt-map-loading { position: absolute; inset: 0; display: grid; place-items: center; background: var(--ds-surface-translucent); color: var(--ds-muted); }
.pt-map-fit { position: absolute; top: 12px; right: 12px; z-index: 500; display: grid; place-items: center; width: 44px; height: 44px; border: 0; border-radius: 14px; background: var(--ds-surface); color: var(--ds-ink); box-shadow: var(--ds-shadow-float); }
.pt-map-foot { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 12px; padding: 11px 14px; font-size: 11px; color: var(--ds-muted); }
.pt-map-foot > span:first-child { display: flex; align-items: center; gap: 4px; color: var(--ds-ink); }
.pt-map-legend { display: flex; align-items: center; gap: 5px; margin-left: auto; }
.pt-map-legend i { width: 9px; height: 9px; border-radius: 50%; margin-left: 4px; }
.tone-amber-ring { background: transparent !important; border: 2px solid var(--ds-amber); }
:deep(.pt-marker-wrap) { background: transparent; border: 0; }
:deep(.pt-marker) { display: grid; place-items: center; width: 38px; height: 38px; border: 3px solid #fff; border-radius: 50%; color: #fff; font: 800 12px var(--ds-font-main); box-shadow: 0 6px 14px rgba(15, 23, 42, .3); background: var(--ds-muted); }
:deep(.pt-marker--gorevde) { background: var(--ds-emerald); }
:deep(.pt-marker--tamamladi) { background: var(--ds-blue-ink); }
:deep(.pt-marker--izinli) { background: var(--ds-purple); }
:deep(.pt-marker--kapanmadi) { background: var(--ds-coral); }
:deep(.pt-marker.is-late) { border-color: var(--ds-amber); }

.pt-list { display: grid; grid-template-columns: minmax(0, 1fr); gap: 9px; }
.pt-card { min-width: 0; display: flex; gap: 11px; padding: 13px; border-radius: 20px; background: var(--ds-surface); box-shadow: var(--ds-shadow-bento); color: var(--ds-ink); cursor: pointer; }
.pt-avatar { position: relative; display: grid; place-items: center; flex: 0 0 46px; width: 46px; height: 46px; border-radius: 16px; background: var(--ds-indigo-soft); color: var(--ds-indigo-ink); font-weight: 800; font-size: 13px; }
.pt-avatar img { width: 100%; height: 100%; object-fit: cover; border-radius: inherit; }
.pt-avatar > i { position: absolute; right: -3px; bottom: -3px; width: 14px; height: 14px; border: 3px solid var(--ds-surface); border-radius: 50%; background: var(--ds-muted-light); }
.pt-avatar--sm { flex-basis: 38px; width: 38px; height: 38px; border-radius: 13px; font-size: 11px; }
.dot-gorevde { background: var(--ds-emerald) !important; }
.dot-tamamladi { background: var(--ds-blue) !important; }
.dot-izinli { background: var(--ds-purple) !important; }
.dot-kapanmadi { background: var(--ds-coral) !important; }
.pt-card__body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 3px; }
.pt-card__head { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.pt-card__head strong { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 14px; }
.pt-card__sub { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 11px; color: var(--ds-muted); }
.pt-card__times { display: flex; flex-wrap: wrap; gap: 4px 10px; margin-top: 3px; font-size: 12px; font-weight: 600; }
.pt-card__times span { display: inline-flex; align-items: center; gap: 4px; }
.pt-card__times .pt-muted { font-weight: 500; font-size: 11px; }
.pt-card__tags, .pt-day__meta { display: flex; flex-wrap: wrap; gap: 5px; margin-top: 5px; }
.pt-card__actions { display: flex; flex-direction: column; gap: 6px; }
.pt-icon-btn { display: grid; place-items: center; flex: 0 0 auto; width: 40px; height: 40px; border: 0; border-radius: 13px; background: var(--ds-surface-subtle); color: var(--ds-ink); text-decoration: none; }
.pt-icon-btn:disabled { opacity: .45; }

.pt-badge { flex: 0 0 auto; padding: 4px 8px; border-radius: 999px; font-size: 9px; font-weight: 800; letter-spacing: .03em; text-transform: uppercase; white-space: nowrap; }
.pt-tag { display: inline-flex; align-items: center; gap: 4px; padding: 3px 8px; border-radius: 999px; font-size: 10px; font-weight: 700; }
.tone-emerald { background: var(--ds-emerald-soft); color: var(--ds-emerald-ink); }
.tone-blue { background: var(--ds-blue-soft); color: var(--ds-blue-ink); }
.tone-purple { background: var(--ds-purple-soft); color: var(--ds-purple-ink); }
.tone-amber { background: var(--ds-amber-soft); color: var(--ds-amber-ink); }
.tone-coral { background: var(--ds-coral-soft); color: var(--ds-coral-ink); }
.tone-slate { background: var(--ds-surface-subtle); color: var(--ds-muted); }
.pt-track .tone-emerald, .pt-legend .tone-emerald, .pt-map-legend .tone-emerald { background: var(--ds-emerald); }
.pt-track .tone-blue, .pt-legend .tone-blue, .pt-map-legend .tone-blue { background: var(--ds-blue); }
.pt-track .tone-purple, .pt-legend .tone-purple { background: var(--ds-purple); }
.pt-track .tone-coral, .pt-legend .tone-coral { background: var(--ds-coral); }
.pt-track .tone-slate, .pt-legend .tone-slate { background: #475569; }
.text-tone-emerald { color: var(--ds-emerald-ink); }
.text-tone-amber { color: var(--ds-amber-ink); }
.text-tone-coral { color: var(--ds-coral-ink); }

.pt-state { min-height: 160px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; color: var(--ds-muted); text-align: center; font-size: 12px; }
.pt-state strong { color: var(--ds-ink); font-size: 14px; }
.pt-state--error button { border: 0; border-radius: 12px; padding: 10px 14px; background: var(--ds-primary); color: #fff; font: 700 12px var(--ds-font-main); }
.pt-empty-icon { display: grid; place-items: center; width: 52px; height: 52px; border-radius: 18px; background: var(--ds-surface); box-shadow: var(--ds-shadow-tile); }

.pt-range { display: grid; grid-template-columns: 1fr 1fr auto; gap: 8px; margin-bottom: 10px; }
.pt-range label { display: flex; flex-direction: column; justify-content: center; height: 50px; padding: 0 10px; border-radius: 14px; background: var(--ds-surface); box-shadow: var(--ds-shadow-tile); }
.pt-range span { font-size: 9px; font-weight: 700; text-transform: uppercase; color: var(--ds-muted); }
.pt-range input { width: 100%; border: 0; outline: 0; padding: 0; background: transparent; color: var(--ds-ink); font: 700 12px var(--ds-font-main); }
.pt-range button { min-width: 64px; border: 0; border-radius: 14px; background: var(--ds-primary); color: #fff; font: 700 12px var(--ds-font-main); }
.pt-range-caption { margin: 0 0 10px 4px; font-size: 11px; color: var(--ds-muted); }

.pt-kpis { display: grid; grid-template-columns: 1fr 1fr; gap: 9px; margin-bottom: 12px; transition: opacity .2s; }
.is-loading { opacity: .55; }
.pt-kpi { display: flex; flex-direction: column; gap: 3px; padding: 13px; border-radius: 20px; background: var(--ds-surface); box-shadow: var(--ds-shadow-bento); }
.pt-kpi__icon { display: grid; place-items: center; width: 32px; height: 32px; border-radius: 11px; margin-bottom: 4px; }
.pt-kpi__label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--ds-muted); }
.pt-kpi strong { font-size: 22px; letter-spacing: -.02em; color: var(--ds-ink); }
.pt-kpi strong small { font-size: 12px; color: var(--ds-muted); }
.pt-kpi__foot { font-size: 10px; color: var(--ds-muted); }

.pt-panel { padding: 15px; margin-bottom: 12px; border-radius: 22px; background: var(--ds-surface); box-shadow: var(--ds-shadow-bento); color: var(--ds-ink); }
.pt-panel > header { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 12px; }
.pt-panel h3 { margin: 0; font-size: 14px; }
.pt-panel > header > span { font-size: 10px; color: var(--ds-muted); }

.pt-trend { display: flex; align-items: stretch; gap: 2px; height: 140px; border-bottom: 1px solid var(--ds-line); }
.pt-trend__col { flex: 1; min-width: 0; display: flex; flex-direction: column; justify-content: flex-end; align-items: center; gap: 4px; padding: 0; border: 0; background: transparent; cursor: pointer; position: relative; }
.pt-trend__bar { width: 100%; max-width: 22px; border-radius: 4px 4px 0 0; background: var(--ds-blue); transition: opacity .15s; }
.pt-trend:has(.active) .pt-trend__col:not(.active) .pt-trend__bar { opacity: .4; }
.pt-trend__label { position: absolute; bottom: -18px; font-size: 9px; color: var(--ds-muted); white-space: nowrap; }
.pt-trend__tip { display: flex; flex-wrap: wrap; align-items: center; gap: 4px 10px; min-height: 30px; margin-top: 24px; padding: 8px 10px; border-radius: 12px; background: var(--ds-surface-subtle); font-size: 11px; color: var(--ds-ink); }

.pt-hbars { display: grid; gap: 9px; }
.pt-hbar { display: grid; grid-template-columns: 86px 1fr 36px; align-items: center; gap: 8px; font-size: 11px; }
.pt-hbar__label { color: var(--ds-muted); }
.pt-hbar strong { text-align: right; }
.pt-hbar__track { display: block; height: 8px; border-radius: 999px; background: var(--ds-surface-subtle); overflow: hidden; }
.pt-hbar__track > span { display: block; height: 100%; border-radius: 999px; background: var(--ds-blue); }
.fill-emerald { background: var(--ds-emerald) !important; }
.fill-amber { background: var(--ds-amber) !important; }
.fill-coral { background: var(--ds-coral) !important; }
.fill-blue { background: var(--ds-blue) !important; }
.fill-slate { background: var(--ds-muted-light) !important; }

.pt-dept-list { display: grid; gap: 12px; }
.pt-dept { display: grid; gap: 5px; }
.pt-dept__head { display: flex; align-items: center; justify-content: space-between; gap: 8px; font-size: 13px; }
.pt-dept__meta { font-size: 10px; color: var(--ds-muted); }

.pt-rank, .pt-perf { display: flex; align-items: center; gap: 10px; width: 100%; min-height: 52px; padding: 8px 0; border: 0; border-top: 1px solid var(--ds-line); background: transparent; color: var(--ds-ink); text-align: left; font-family: var(--ds-font-main); }
.pt-rank:first-of-type, .pt-perf:first-of-type { border-top: 0; }
.pt-rank__no { display: grid; place-items: center; flex: 0 0 28px; height: 28px; border-radius: 10px; background: var(--ds-amber-soft); color: var(--ds-amber-ink); font-weight: 800; font-size: 12px; }
.pt-rank__body, .pt-perf__body { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.pt-rank__body strong, .pt-perf__body strong { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 13px; }
.pt-rank__body small, .pt-perf__body small { font-size: 10px; color: var(--ds-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pt-rank__value, .pt-perf__value { display: flex; flex-direction: column; align-items: flex-end; gap: 3px; font-size: 13px; }
.pt-rank__value small { font-size: 10px; color: var(--ds-muted); }
.pt-inactive { margin: 10px 0 0; padding: 10px; border-radius: 12px; background: var(--ds-surface-subtle); font-size: 11px; color: var(--ds-muted); line-height: 1.4; }

.pt-seg { display: flex; gap: 2px; padding: 3px; border-radius: 12px; background: var(--ds-surface-subtle); }
.pt-seg button { min-height: 32px; padding: 0 10px; border: 0; border-radius: 9px; background: transparent; color: var(--ds-muted); font: 700 11px var(--ds-font-main); white-space: nowrap; }
.pt-seg button.active { background: var(--ds-surface); color: var(--ds-ink); box-shadow: var(--ds-shadow-tile); }
.pt-seg button span { margin-left: 3px; font-size: 10px; opacity: .7; }
.pt-seg--full { display: grid; grid-template-columns: repeat(3, 1fr); margin: 12px 0; }
.pt-seg--full button { min-height: 38px; }

.pt-layer { position: fixed; inset: 0; z-index: 1080; display: flex; align-items: flex-end; background: var(--ds-overlay); font-family: var(--ds-font-main); }
.pt-sheet { display: flex; flex-direction: column; width: 100%; max-width: 640px; margin: 0 auto; height: 94dvh; border-radius: 28px 28px 0 0; background: var(--ds-canvas); overflow: hidden; }
.pt-sheet--short { height: auto; max-height: 86dvh; }
.pt-sheet__head { display: flex; align-items: center; gap: 10px; padding: 16px 16px 14px; background: var(--ds-surface); border-bottom: 1px solid var(--ds-line); color: var(--ds-ink); }
.pt-sheet__title { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.pt-sheet__title strong { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 16px; }
.pt-sheet__title span { font-size: 11px; color: var(--ds-muted); }
.pt-sheet__body { flex: 1; overflow-y: auto; padding: 14px 16px calc(20px + var(--ds-safe-bottom)); overscroll-behavior: contain; }
.pt-sheet__foot { padding: 10px 16px calc(10px + var(--ds-safe-bottom)); background: var(--ds-surface); border-top: 1px solid var(--ds-line); }

.pt-mini-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 6px; transition: opacity .2s; }
.pt-mini-grid > div { display: flex; flex-direction: column; gap: 2px; padding: 10px 12px; border-radius: 14px; background: var(--ds-surface); box-shadow: var(--ds-shadow-tile); }
.pt-mini-grid span { font-size: 10px; color: var(--ds-muted); }
.pt-mini-grid strong { font-size: 15px; color: var(--ds-ink); }
.pt-mini-grid strong small { font-size: 10px; color: var(--ds-muted); font-weight: 600; }

.pt-days { display: grid; gap: 7px; }
.pt-day { display: flex; gap: 10px; padding: 11px; border-radius: 16px; background: var(--ds-surface); box-shadow: var(--ds-shadow-tile); color: var(--ds-ink); }
.pt-day.is-weekend { background: var(--ds-surface-subtle); }
.pt-day__date { display: flex; flex-direction: column; align-items: center; flex: 0 0 38px; }
.pt-day__date strong { font-size: 18px; line-height: 1.1; }
.pt-day__date span { font-size: 10px; color: var(--ds-muted); font-weight: 700; }
.pt-day__body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 4px; }
.pt-day__head { display: flex; align-items: center; flex-wrap: wrap; gap: 4px 8px; font-size: 12px; }
.pt-day__time { font-weight: 700; }
.pt-day__bar { height: 6px; }
.pt-link { display: inline-flex; align-items: center; gap: 3px; padding: 3px 7px; border: 0; border-radius: 999px; background: var(--ds-surface-subtle); color: var(--ds-blue-ink); font: 700 10px var(--ds-font-main); text-decoration: none; }
.pt-note { margin: 2px 0 0; font-size: 12px; font-style: italic; color: var(--ds-ink); line-height: 1.4; overflow-wrap: anywhere; }
.pt-note small { font-style: normal; color: var(--ds-muted); }
.pt-note-edit textarea { box-sizing: border-box; width: 100%; margin-top: 4px; padding: 10px 12px; border: 1px solid var(--ds-line); border-radius: 12px; background: var(--ds-surface-subtle); color: var(--ds-ink); font: 500 13px var(--ds-font-main); resize: vertical; outline: 0; }
.pt-note-edit textarea:focus { border-color: var(--ds-blue); }
.pt-note-edit > div { display: flex; justify-content: flex-end; gap: 6px; margin-top: 6px; }
.pt-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; min-height: 40px; padding: 0 14px; border: 0; border-radius: 12px; background: var(--ds-primary); color: #fff; font: 700 12px var(--ds-font-main); }
.pt-btn:disabled { opacity: .55; }
.pt-btn--ghost { background: var(--ds-surface-subtle); color: var(--ds-ink); }
.pt-btn--block { width: 100%; min-height: 48px; border-radius: 15px; font-size: 14px; }

.pt-timeline__group h4 { margin: 12px 0 8px; font-size: 12px; color: var(--ds-muted); text-transform: capitalize; }
.pt-timeline__group:first-child h4 { margin-top: 0; }
.pt-timeline__item { position: relative; display: flex; gap: 12px; padding: 0 0 14px 4px; color: var(--ds-ink); }
.pt-timeline__item::before { content: ''; position: absolute; left: 9px; top: 14px; bottom: 0; width: 2px; background: var(--ds-line); }
.pt-timeline__item:last-child::before { display: none; }
.pt-timeline__item > i { flex: 0 0 12px; height: 12px; margin-top: 3px; border: 3px solid var(--ds-surface); border-radius: 50%; box-shadow: 0 0 0 1px var(--ds-line); z-index: 1; }
.pt-timeline__item.is-start > i { background: var(--ds-emerald); }
.pt-timeline__item.is-end > i { background: var(--ds-coral); }
.pt-timeline__item > div { display: flex; flex-direction: column; gap: 3px; font-size: 11px; }
.pt-timeline__item strong { font-size: 13px; }

.pt-notes { display: grid; gap: 8px; }
.pt-note-card { padding: 12px; border-radius: 16px; background: var(--ds-surface); box-shadow: var(--ds-shadow-tile); color: var(--ds-ink); }
.pt-note-card strong { font-size: 12px; text-transform: capitalize; }
.pt-note-card p { margin: 5px 0; font-size: 13px; line-height: 1.4; overflow-wrap: anywhere; }
.pt-note-card small { font-size: 10px; color: var(--ds-muted); }

.pt-shift-list { display: grid; gap: 7px; }
.pt-shift { display: flex; align-items: center; justify-content: space-between; gap: 10px; min-height: 54px; padding: 0 12px 0 14px; border-radius: 15px; background: var(--ds-surface); box-shadow: var(--ds-shadow-tile); color: var(--ds-ink); font-size: 13px; font-weight: 700; }
.pt-shift input { height: 40px; padding: 0 10px; border: 1px solid var(--ds-line); border-radius: 11px; background: var(--ds-surface-subtle); color: var(--ds-ink); font: 700 14px var(--ds-font-main); }

.pt-toast { position: fixed; z-index: 1100; left: 50%; bottom: calc(96px + var(--ds-safe-bottom)); transform: translateX(-50%); max-width: calc(100vw - 32px); padding: 11px 16px; border-radius: 16px; background: var(--ds-primary); color: #fff; box-shadow: var(--ds-shadow-float); font-size: 12px; text-align: center; }

.spin { animation: pt-spin .8s linear infinite; }
@keyframes pt-spin { to { transform: rotate(360deg); } }
@keyframes pt-pulse { 0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, .6); } 70% { box-shadow: 0 0 0 8px rgba(16, 185, 129, 0); } 100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); } }
.pt-sheet-enter-active, .pt-sheet-leave-active, .pt-toast-enter-active, .pt-toast-leave-active { transition: opacity .22s ease; }
.pt-sheet-enter-active .pt-sheet, .pt-sheet-leave-active .pt-sheet { transition: transform .26s cubic-bezier(.22, 1, .36, 1); }
.pt-sheet-enter-from, .pt-sheet-leave-to, .pt-toast-enter-from, .pt-toast-leave-to { opacity: 0; }
.pt-sheet-enter-from .pt-sheet, .pt-sheet-leave-to .pt-sheet { transform: translateY(40px); }
@media (prefers-reduced-motion: reduce) { .pt-live-dot, .spin { animation: none; } }
</style>
