async function request(action, options = {}) {
  const method = options.method || 'GET'
  let url = `/api/cari.php?action=${encodeURIComponent(action)}`
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

  if (method === 'POST') {
    if (options.body instanceof FormData) {
      fetchOptions.body = options.body
      if (options.csrfToken) {
        fetchOptions.headers['X-CSRF-Token'] = options.csrfToken
      }
    } else if (options.body) {
      fetchOptions.headers['Content-Type'] = 'application/json'
      if (options.csrfToken) {
        fetchOptions.headers['X-CSRF-Token'] = options.csrfToken
      }
      fetchOptions.body = JSON.stringify(options.body)
    }
  }

  const response = await fetch(url, fetchOptions)
  const payload = await response.json().catch(() => ({ success: false, message: 'Sunucudan geçersiz yanıt alındı.' }))

  if (!response.ok || !payload.success) {
    const error = new Error(payload.message || 'Cari işlemi gerçekleştirilemedi.')
    error.status = response.status
    throw error
  }

  return payload
}

export const cariApi = {
  list(params = {}) {
    return request('list', { params })
  },
  detail(id) {
    return request('detail', { params: { id } })
  },
  tumHareketler(params = {}) {
    return request('tum_hareketler', { params })
  },
  hareketDetail(id) {
    return request('hareket_detail', { params: { id } })
  },
  saveCari(data, csrfToken) {
    return request('save_cari', { method: 'POST', body: data, csrfToken })
  },
  saveNote(cariId, notes, csrfToken) {
    return request('save_note', { method: 'POST', body: { cari_id: cariId, notlar: notes }, csrfToken })
  },
  deleteCari(id, deleteMovements = false, csrfToken) {
    return request('delete_cari', { method: 'POST', body: { id, delete_movements: deleteMovements ? 1 : 0 }, csrfToken })
  },
  saveHareket(formData, csrfToken) {
    return request('save_hareket', { method: 'POST', body: formData, csrfToken })
  },
  deleteHareket(id, csrfToken) {
    return request('delete_hareket', { method: 'POST', body: { id }, csrfToken })
  },
}
