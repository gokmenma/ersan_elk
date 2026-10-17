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

