async function get(action, params = {}) {
  const query = new URLSearchParams({ action, ...params })
  const response = await fetch(`/api/personnel.php?${query}`, {
    credentials: 'same-origin',
    headers: { Accept: 'application/json' },
  })
  const payload = await response.json().catch(() => ({ success: false, message: 'Sunucudan geçersiz yanıt alındı.' }))
  if (!response.ok || !payload.success) {
    const error = new Error(payload.message || 'Personel bilgileri alınamadı.')
    error.status = response.status
    throw error
  }
  return payload
}

export const personnelApi = {
  list(filters) {
    return get('list', filters)
  },
  detail(id) {
    return get('detail', { id })
  },
  async save(data, csrfToken) {
    const response = await fetch('/api/personnel.php?action=save', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
      body: JSON.stringify(data),
    })
    const payload = await response.json().catch(() => ({ success: false, message: 'Sunucudan geçersiz yanıt alındı.' }))
    if (!response.ok || !payload.success) throw new Error(payload.message || 'Personel kaydedilemedi.')
    return payload
  },
}
