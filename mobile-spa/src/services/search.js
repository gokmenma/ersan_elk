import { reactive, computed } from 'vue'
import { auth } from './auth.js'

export const SEARCH_MODULES = [
  { key: 'personnel', desc: 'Ad, telefon, TC, görev', label: 'Personeller', hint: 'Personel', to: '/personel' },
  { key: 'invoices', desc: 'No, cari, VKN, ürün', label: 'Faturalar', hint: 'Fatura', to: '/fatura' },
  { key: 'vehicles', desc: 'Plaka, marka, şase', label: 'Araçlar', hint: 'Araç Takip', to: '/arac-takip' },
  { key: 'current_accounts', desc: 'Ünvan, telefon, e-posta', label: 'Cariler', hint: 'Cari Hesaplar', to: '/cari-takip' },
  { key: 'documents', desc: 'Evrak no, konu, kurum', label: 'Evraklar', hint: 'Evrak Takip', to: '/evrak-takip' },
  { key: 'illegal', desc: 'Tutanak, abone, sayaç', label: 'Kaçak', hint: 'Kaçak Kontrol', to: '/kacak-kontrol' },
]

const RECENT_KEY = 'ersan_mobile_recent_searches_v1'

function loadRecent() {
  try {
    const list = JSON.parse(localStorage.getItem(RECENT_KEY) || '[]')
    return Array.isArray(list) ? list.filter((v) => typeof v === 'string').slice(0, 5) : []
  } catch {
    return []
  }
}

export const searchState = reactive({
  open: false,
  query: '',
  category: 'all',
  loading: false,
  error: '',
  allowedModules: [],
  counts: { all: 0 },
  results: {},
  searched: false,
  recent: loadRecent(),
})

export const canGlobalSearch = computed(() => SEARCH_MODULES.some((m) => auth.state.permissions?.[m.key]))

let controller = null
let timer = null

function resetResults() {
  searchState.counts = { all: 0 }
  searchState.results = {}
  searchState.searched = false
  searchState.error = ''
}

export function openSearchSheet() {
  if (!canGlobalSearch.value) return
  searchState.open = true
}

export function closeSearchSheet() {
  searchState.open = false
  if (controller) controller.abort()
  clearTimeout(timer)
  searchState.loading = false
}

export function toggleSearchSheet() {
  if (searchState.open) closeSearchSheet()
  else openSearchSheet()
}

export function setSearchCategory(category) {
  searchState.category = category
}

export function setSearchQuery(value) {
  searchState.query = value
  clearTimeout(timer)
  if (controller) controller.abort()
  if (value.trim().length < 2) {
    searchState.loading = false
    resetResults()
    return
  }
  searchState.loading = true
  timer = setTimeout(runSearch, 250)
}

async function runSearch() {
  const q = searchState.query.trim()
  controller = new AbortController()
  try {
    const res = await fetch(`/api/search.php?${new URLSearchParams({ q, category: 'all', limit: '6' })}`, {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
      signal: controller.signal,
    })
    const data = await res.json().catch(() => ({}))
    if (!res.ok || !data.success) throw new Error(data.message || 'Arama yapılamadı.')
    searchState.allowedModules = Array.isArray(data.allowed_modules) ? data.allowed_modules : []
    searchState.counts = data.counts || { all: 0 }
    searchState.results = data.results || {}
    searchState.error = ''
    searchState.searched = true
    if (searchState.category !== 'all' && !searchState.allowedModules.includes(searchState.category)) {
      searchState.category = 'all'
    }
  } catch (e) {
    if (e.name === 'AbortError') return
    resetResults()
    searchState.error = e.message || 'Arama yapılamadı.'
  } finally {
    if (!controller.signal.aborted) searchState.loading = false
  }
}

export function rememberSearch(value) {
  const q = String(value || '').trim()
  if (q.length < 2) return
  searchState.recent = [q, ...searchState.recent.filter((v) => v !== q)].slice(0, 5)
  try { localStorage.setItem(RECENT_KEY, JSON.stringify(searchState.recent)) } catch {}
}

export function clearRecentSearches() {
  searchState.recent = []
  try { localStorage.removeItem(RECENT_KEY) } catch {}
}
