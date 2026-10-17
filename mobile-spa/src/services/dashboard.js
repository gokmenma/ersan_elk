export async function getDashboard() {
  const response = await fetch('/api/dashboard.php', { credentials: 'same-origin', headers: { Accept: 'application/json' } })
  const payload = await response.json().catch(() => ({ success: false, message: 'Sunucudan geçersiz yanıt alındı.' }))
  if (!response.ok || !payload.success) throw new Error(payload.message || 'Ana sayfa verileri alınamadı.')
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
  const today = new Date().toISOString().slice(0, 10)
  const syncActions = [
    { action: 'online-puantaj-sorgula', active_tab: 'kesme' },
    { action: 'online-icmal-sorgula', active_tab: '' },
    { action: 'online-sayac-degisim-sorgula', active_tab: 'sokme_takma' },
  ]

  const results = await Promise.allSettled(
    syncActions.map(async (item) => {
      const formData = new FormData()
      formData.append('action', item.action)
      formData.append('active_tab', item.active_tab)
      formData.append('baslangic_tarihi', today)
      formData.append('bitis_tarihi', today)

      const res = await fetch('/views/puantaj/api.php', {
        method: 'POST',
        credentials: 'same-origin',
        body: formData,
      })
      return res.json().catch(() => ({ status: 'error' }))
    })
  )

  const freshDashboard = await getDashboard()
  return { results, dashboard: freshDashboard }
}

