import { reactive } from 'vue'

export function formatLocalYmd(d) {
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

export function getCurrentMonthRange() {
  const now = new Date()
  const y = now.getFullYear()
  const m = String(now.getMonth() + 1).padStart(2, '0')
  const lastDay = new Date(y, now.getMonth() + 1, 0).getDate()
  return {
    start: `${y}-${m}-01`,
    end: `${y}-${m}-${String(lastDay).padStart(2, '0')}`,
  }
}

const initialMonthRange = getCurrentMonthRange()

export const reportsState = reactive({
  activeTab: 'okuma', // 'okuma' | 'kesme' | 'sokme_takma' | 'muhurleme' | 'kacakkontrol'
  datePreset: 'buay', // 'bugun' | 'dun' | 'hafta' | 'buay' | 'gecenay' | 'custom'
  startDate: initialMonthRange.start,
  endDate: initialMonthRange.end,
  dateRangeLabel: '',
  searchQuery: '',
  selectedRegion: '', // '' (tümü) | 'Onikişubat' | vb.
  sortBy: 'total-desc', // 'total-desc' | 'total-asc' | 'name-asc' | 'team-asc'
  loading: false,
  error: null,
  stats: {
    total_operations: 0,
    total_personnel: 0,
    active_personnel: 0,
    total_regions: 0,
    top_performer: null,
    top_region: null,
    avg_per_active: 0,
  },
  regions: [],
  cards: [],
  tabs: [
    { id: 'okuma', name: 'Endeks Okuma', icon: 'Gauge', unit: 'Okuma' },
    { id: 'kesme', name: 'Kesme / Açma', icon: 'ZapOff', unit: 'İşlem' },
    { id: 'sokme_takma', name: 'Sayaç Sökme/Takma', icon: 'Wrench', unit: 'Sayaç' },
    { id: 'muhurleme', name: 'Mühürleme', icon: 'ShieldCheck', unit: 'Mühür' },
    { id: 'kacakkontrol', name: 'Kaçak Kontrol', icon: 'ShieldAlert', unit: 'Tutanak' },
  ],
  // Detay Drawer
  detailDrawerOpen: false,
  detailLoading: false,
  activeCard: null,
  detailData: null,
  // Dağılım Dökümü Modal / Sheet
  distributionDrawerOpen: false,
  distributionLoading: false,
  distributionData: null,
  distributionFilterType: 'all',
  distributionActiveView: 'all', // 'all' | 'status' | 'region'
  // Tarih Seçici Sheet
  datePickerOpen: false,
  // Bottom Navbar Sekmeler Çekmecesi (Talepler / KM Onayları Stili)
  drawerOpen: true,
})

export function setReportsTab(tabId) {
  if (reportsState.activeTab === tabId && !reportsState.loading) return
  reportsState.activeTab = tabId
  loadReports()
}

export function openReportsDrawer() {
  reportsState.drawerOpen = true
}

export function closeReportsDrawer() {
  reportsState.drawerOpen = false
}

export function toggleReportsDrawer() {
  reportsState.drawerOpen = !reportsState.drawerOpen
}

async function request(action, options = {}) {
  const method = options.method || 'GET'
  let url = `/api/reports.php?action=${encodeURIComponent(action)}`
  const fetchOptions = {
    method,
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
      ...(options.headers || {}),
    },
  }

  if (method === 'GET' && options.params) {
    const query = new URLSearchParams(options.params).toString()
    if (query) url += `&${query}`
  }

  const response = await fetch(url, fetchOptions)
  const payload = await response.json().catch(() => ({
    success: false,
    message: 'Sunucudan geçersiz yanıt alındı.',
  }))

  if (!response.ok || payload.success === false) {
    const error = new Error(payload.message || 'Rapor verileri alınamadı.')
    error.status = response.status
    error.payload = payload
    throw error
  }

  return payload
}

export const reportsApi = {
  async getCards(params = {}) {
    return request('cards', { params })
  },
  async getDetail(params = {}) {
    return request('detail', { params })
  },
  async getDistribution(params = {}) {
    return request('distribution', { params })
  },
}

export function computePresetDates(preset) {
  const now = new Date()
  const todayStr = formatLocalYmd(now)

  if (preset === 'bugun') {
    return { start: todayStr, end: todayStr }
  }

  if (preset === 'dun') {
    const yest = new Date(now.getFullYear(), now.getMonth(), now.getDate() - 1)
    return { start: formatLocalYmd(yest), end: formatLocalYmd(yest) }
  }

  if (preset === 'hafta') {
    // Pazartesi
    const day = now.getDay()
    const diff = now.getDate() - day + (day === 0 ? -6 : 1)
    const monday = new Date(now.getFullYear(), now.getMonth(), diff)
    return {
      start: formatLocalYmd(monday),
      end: todayStr,
    }
  }

  if (preset === 'buay') {
    return getCurrentMonthRange()
  }

  if (preset === 'gecenay') {
    const prevMonthDate = new Date(now.getFullYear(), now.getMonth() - 1, 1)
    const py = prevMonthDate.getFullYear()
    const pm = String(prevMonthDate.getMonth() + 1).padStart(2, '0')
    const lastDayPrev = new Date(py, prevMonthDate.getMonth() + 1, 0).getDate()
    return {
      start: `${py}-${pm}-01`,
      end: `${py}-${pm}-${String(lastDayPrev).padStart(2, '0')}`,
    }
  }

  return { start: reportsState.startDate, end: reportsState.endDate }
}

export async function loadReports(forcePreset = null) {
  if (forcePreset) {
    reportsState.datePreset = forcePreset
    const dates = computePresetDates(forcePreset)
    reportsState.startDate = dates.start
    reportsState.endDate = dates.end
  }

  reportsState.loading = true
  reportsState.error = null

  try {
    const res = await reportsApi.getCards({
      tab: reportsState.activeTab,
      filter_type: reportsState.datePreset,
      start_date: reportsState.startDate,
      end_date: reportsState.endDate,
    })

    reportsState.stats = res.stats || reportsState.stats
    reportsState.regions = res.regions || []
    reportsState.cards = res.cards || []
    reportsState.dateRangeLabel = res.date_range_label || ''
    if (Array.isArray(res.tabs) && res.tabs.length > 0) {
      reportsState.tabs = res.tabs
    }
  } catch (err) {
    reportsState.error = err.message || 'Veriler yüklenirken bir sorun oluştu.'
  } finally {
    reportsState.loading = false
  }
}

export async function openCardDetail(card) {
  reportsState.activeCard = card
  reportsState.detailData = null
  reportsState.detailLoading = true
  reportsState.detailDrawerOpen = true

  try {
    const clickId = card.is_kacak ? (card.composite_key || card.team) : (card.pId || card.id)
    const res = await reportsApi.getDetail({
      pId: clickId,
      tab: reportsState.activeTab,
      start_date: reportsState.startDate,
      end_date: reportsState.endDate,
      composite_key: card.composite_key || '',
    })
    reportsState.detailData = res
  } catch (err) {
    reportsState.detailData = {
      error: err.message || 'Detaylar alınamadı.',
      days: [],
    }
  } finally {
    reportsState.detailLoading = false
  }
}

export function closeCardDetail() {
  reportsState.detailDrawerOpen = false
  reportsState.activeCard = null
  reportsState.detailData = null
}

export async function openDistributionModal() {
  reportsState.distributionDrawerOpen = true
  reportsState.distributionLoading = true
  reportsState.distributionData = null
  reportsState.distributionFilterType = 'all'
  reportsState.distributionActiveView = 'all'
  await loadDistribution()
}

export async function loadDistribution(forceType = null) {
  if (forceType !== null) {
    reportsState.distributionFilterType = forceType
  }
  reportsState.distributionLoading = true
  try {
    const res = await reportsApi.getDistribution({
      tab: reportsState.activeTab,
      start_date: reportsState.startDate,
      end_date: reportsState.endDate,
      type: reportsState.distributionFilterType,
    })
    reportsState.distributionData = res
  } catch (err) {
    reportsState.distributionData = {
      error: err.message || 'Dağılım dökümü alınamadı.',
    }
  } finally {
    reportsState.distributionLoading = false
  }
}

export function setDistributionFilter(type) {
  if (reportsState.distributionFilterType === type && !reportsState.distributionLoading) return
  return loadDistribution(type)
}

export function setDistributionView(view) {
  reportsState.distributionActiveView = view
}

export function closeDistributionModal() {
  reportsState.distributionDrawerOpen = false
  reportsState.distributionData = null
}

export function openDatePicker() {
  reportsState.datePickerOpen = true
}

export function closeDatePicker() {
  reportsState.datePickerOpen = false
}

export function applyCustomDateRange(start, end, presetLabel = 'custom') {
  reportsState.startDate = start
  reportsState.endDate = end
  reportsState.datePreset = presetLabel
  reportsState.datePickerOpen = false
  return loadReports()
}
