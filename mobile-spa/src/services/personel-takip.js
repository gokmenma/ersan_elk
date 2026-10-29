async function request(action, options = {}) {
  const method = options.method || 'GET'
  let url = `/api/personel-takip.php?action=${encodeURIComponent(action)}`
  const fetchOptions = {
    method,
    credentials: 'same-origin',
    headers: { Accept: 'application/json' },
  }

  if (method === 'GET' && options.params) {
    const clean = Object.fromEntries(Object.entries(options.params).filter(([, v]) => v !== '' && v !== null && v !== undefined))
    const query = new URLSearchParams(clean).toString()
    if (query) url += `&${query}`
  }

  if (method === 'POST') {
    fetchOptions.headers['Content-Type'] = 'application/json'
    fetchOptions.headers['X-CSRF-Token'] = options.csrfToken || ''
    fetchOptions.body = JSON.stringify(options.body || {})
  }

  const response = await fetch(url, fetchOptions)
  const payload = await response.json().catch(() => ({ success: false, message: 'Sunucudan geçersiz yanıt alındı.' }))

  if (!response.ok || !payload.success) {
    const error = new Error(payload.message || 'Personel takip işlemi gerçekleştirilemedi.')
    error.status = response.status
    throw error
  }

  return payload
}

export const personelTakipApi = {
  day(params = {}) {
    return request('gun', { params })
  },
  personnel(params = {}) {
    return request('personel', { params })
  },
  analysis(params = {}) {
    return request('analiz', { params })
  },
  shiftSettings() {
    return request('mesai')
  },
  saveShiftSettings(items, csrfToken) {
    return request('mesai_kaydet', { method: 'POST', body: { items }, csrfToken })
  },
  requestLocation(id, csrfToken) {
    return request('konum_iste', { method: 'POST', body: { id }, csrfToken })
  },
  requestAllLocations(csrfToken) {
    return request('konum_iste_toplu', { method: 'POST', csrfToken })
  },
  saveNote(data, csrfToken) {
    return request('aciklama_kaydet', { method: 'POST', body: data, csrfToken })
  },
}
