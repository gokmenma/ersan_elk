async function request(action, options = {}) {
  const method = options.method || 'GET'
  let url = `/api/arac.php?action=${encodeURIComponent(action)}`
  const fetchOptions = {
    method,
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
      ...(options.headers || {}),
    },
  }

  if (method === 'GET' && options.params) {
    const cleanedParams = {}
    for (const [k, v] of Object.entries(options.params)) {
      if (v !== undefined && v !== null && v !== '') {
        cleanedParams[k] = v
      }
    }
    const query = new URLSearchParams(cleanedParams).toString()
    if (query) url += `&${query}`
  }

  if (method === 'POST') {
    if (options.body instanceof FormData) {
      fetchOptions.body = options.body
      if (options.csrfToken) {
        fetchOptions.headers['X-CSRF-Token'] = options.csrfToken
      }
    } else if (options.body) {
      const formData = new FormData()
      for (const [key, value] of Object.entries(options.body)) {
        if (value !== undefined && value !== null) {
          formData.append(key, value)
        }
      }
      fetchOptions.body = formData
      if (options.csrfToken) {
        fetchOptions.headers['X-CSRF-Token'] = options.csrfToken
      }
    }
  }

  const response = await fetch(url, fetchOptions)
  const payload = await response.json().catch(() => ({ success: false, message: 'Sunucudan geçersiz yanıt alındı.' }))

  if (!response.ok || !payload.success) {
    const error = new Error(payload.message || 'Araç takip işlemi gerçekleştirilemedi.')
    error.status = response.status
    throw error
  }

  return payload
}

export const aracApi = {
  list(params = {}) {
    return request('list', { params })
  },
  detail(id) {
    return request('detail', { params: { id } })
  },
  personeller() {
    return request('personeller')
  },
  saveArac(data, csrfToken) {
    return request('arac_kaydet', { method: 'POST', body: data, csrfToken })
  },
  deleteArac(id, csrfToken) {
    return request('arac_sil', { method: 'POST', body: { id }, csrfToken })
  },
  uploadRuhsat(aracId, file, csrfToken) {
    const formData = new FormData()
    formData.append('arac_id', aracId)
    formData.append('ruhsat_dosyasi', file)
    return request('ruhsat_yukle', { method: 'POST', body: formData, csrfToken })
  },
  deleteRuhsat(aracId, csrfToken) {
    return request('ruhsat_sil', { method: 'POST', body: { arac_id: aracId }, csrfToken })
  },
  saveZimmet(data, csrfToken) {
    return request('zimmet_ver', { method: 'POST', body: data, csrfToken })
  },
  returnZimmet(data, csrfToken) {
    return request('zimmet_iade', { method: 'POST', body: data, csrfToken })
  },
  saveYakit(data, csrfToken) {
    return request('yakit_kaydet', { method: 'POST', body: data, csrfToken })
  },
  deleteYakit(id, csrfToken) {
    return request('yakit_sil', { method: 'POST', body: { id }, csrfToken })
  },
  saveKm(data, csrfToken) {
    return request('km_kaydet', { method: 'POST', body: data, csrfToken })
  },
  saveServis(data, csrfToken) {
    return request('servis_kaydet', { method: 'POST', body: data, csrfToken })
  },
  completeServis(data, csrfToken) {
    return request('servis_tamamla', { method: 'POST', body: data, csrfToken })
  },
  deleteServis(id, csrfToken) {
    return request('servis_sil', { method: 'POST', body: { id }, csrfToken })
  },
}
