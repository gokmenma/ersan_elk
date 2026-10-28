const DASHBOARD_CACHE_PREFIX = 'ersan_mobile_dashboard_cache_'

function dashboardCacheKey(scope) {
  return DASHBOARD_CACHE_PREFIX + String(scope || '')
}

export function readDashboardCache(scope) {
  try {
    const raw = localStorage.getItem(dashboardCacheKey(scope))
    const parsed = raw ? JSON.parse(raw) : null
    return parsed && typeof parsed === 'object' && parsed.data ? parsed.data : null
  } catch {
    return null
  }
}

export function writeDashboardCache(scope, data) {
  try {
    localStorage.setItem(dashboardCacheKey(scope), JSON.stringify({ at: Date.now(), data }))
  } catch {}
}

export function clearDashboardCache() {
  try {
    Object.keys(localStorage)
      .filter((key) => key.startsWith(DASHBOARD_CACHE_PREFIX))
      .forEach((key) => localStorage.removeItem(key))
  } catch {}
}

export async function getDashboard() {
  const response = await fetch('/api/dashboard.php', { credentials: 'same-origin', headers: { Accept: 'application/json' } })
  const payload = await response.json().catch(() => ({ success: false, message: 'Sunucudan geçersiz yanıt alındı.' }))
  if (!response.ok || !payload.success) throw new Error(payload.message || 'Ana sayfa verileri alınamadı.')
  return payload.data || {}
}

export async function getEndeksComparison() {
  const response = await fetch('/api/dashboard.php?section=endeks_comparison', { credentials: 'same-origin', headers: { Accept: 'application/json' } })
  const payload = await response.json().catch(() => ({ success: false, message: 'Sunucudan geçersiz yanıt alındı.' }))
  if (!response.ok || !payload.success) throw new Error(payload.message || 'Endeks karşılaştırması alınamadı.')
  return payload.data || {}
}

export async function saveDashboardSettings(order, settings = {}) {
  const response = await fetch('/api/dashboard.php', {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
    },
    body: JSON.stringify({
      action: 'save_card_settings',
      order,
      settings,
    }),
  })
  const payload = await response.json().catch(() => ({ success: false, message: 'Ayarlar sunucuya kaydedilemedi.' }))
  if (!response.ok || !payload.success) throw new Error(payload.message || 'Ayarlar kaydedilemedi.')
  return payload
}

export async function syncDailyOperations() {
  // Türkiye saat dilimine göre bugün (YYYY-MM-DD)
  const today = new Intl.DateTimeFormat('en-CA', { timeZone: 'Europe/Istanbul' }).format(new Date())

  // Tüm günlük operasyonlar: Endeks Okuma, Kesme/Açma, Mühürleme, Sayaç Değişim
  // Lock çakışmasını önlemek için ardışık (sequential) çalıştırılır
  const syncActions = [
    { action: 'online-icmal-sorgula', active_tab: '' },
    { action: 'online-puantaj-sorgula', active_tab: 'kesme' },
    { action: 'online-puantaj-sorgula', active_tab: 'muhurleme' },
    { action: 'online-sayac-degisim-sorgula', active_tab: 'sokme_takma' },
  ]

  const results = []
  for (const item of syncActions) {
    try {
      const formData = new FormData()
      formData.append('action', item.action)
      if (item.active_tab) {
        formData.append('active_tab', item.active_tab)
      }
      formData.append('baslangic_tarihi', today)
      formData.append('bitis_tarihi', today)

      const res = await fetch('/api/puantaj-sync.php', {
        method: 'POST',
        credentials: 'same-origin',
        body: formData,
      })
      const data = await res.json().catch(() => ({ status: 'error', message: 'Sunucudan geçersiz yanıt alındı.' }))
      results.push({ item, res: data })
    } catch (e) {
      results.push({ item, error: e })
    }
  }

  const failed = results.filter(result => result.error || result.res?.status !== 'success')
  if (failed.length) {
    const firstFailure = failed[0]
    const message = firstFailure.res?.message || firstFailure.error?.message || 'Günlük operasyon sorgusu tamamlanamadı.'
    throw new Error(message)
  }

  const freshDashboard = await getDashboard()
  return { results, dashboard: freshDashboard }
}

