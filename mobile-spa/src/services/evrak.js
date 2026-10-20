async function request(action, options = {}) {
  const method = options.method || 'GET'
  let url = `/api/evrak.php?action=${encodeURIComponent(action)}`
  const fetchOptions = {
    method,
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
      ...(options.headers || {}),
    },
  }

  if (method === 'GET' && options.params) {
    const params = new URLSearchParams()
    for (const [key, val] of Object.entries(options.params)) {
      if (val !== undefined && val !== null && val !== '') {
        params.append(key, String(val))
      }
    }
    const query = params.toString()
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
      for (const [key, val] of Object.entries(options.body)) {
        if (val !== undefined && val !== null) {
          if (Array.isArray(val) || typeof val === 'object') {
            formData.append(key, JSON.stringify(val))
          } else {
            formData.append(key, String(val))
          }
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
    const error = new Error(payload.message || 'Evrak işlemi gerçekleştirilemedi.')
    error.status = response.status
    throw error
  }

  return payload
}

export const evrakApi = {
  list(params = {}) {
    return request('list', { params })
  },
  meta() {
    return request('meta')
  },
  detail(id) {
    return request('detail', { params: { id } })
  },
  aracZimmet(plaka, tarih) {
    return request('arac-zimmet', { params: { plaka, tarih } })
  },
  save(formData, csrfToken) {
    return request('save', { method: 'POST', body: formData, csrfToken })
  },
  delete(id, csrfToken) {
    const fd = new FormData()
    fd.append('id', id)
    return request('delete', { method: 'POST', body: fd, csrfToken })
  },
  eImzaOnayaSun(id, csrfToken) {
    const fd = new FormData()
    fd.append('id', id)
    return request('e-imza-onaya-sun', { method: 'POST', body: fd, csrfToken })
  },
  eImzaOnayla(id, csrfToken) {
    const fd = new FormData()
    fd.append('id', id)
    return request('e-imza-onayla', { method: 'POST', body: fd, csrfToken })
  },
  eImzaIade(id, gerekce, csrfToken) {
    const fd = new FormData()
    fd.append('id', id)
    fd.append('gerekce', gerekce)
    return request('e-imza-iade', { method: 'POST', body: fd, csrfToken })
  },
  eImzaGeriAl(id, csrfToken) {
    const fd = new FormData()
    fd.append('id', id)
    return request('e-imza-geri-al', { method: 'POST', body: fd, csrfToken })
  },
  getIcraDosyalari(personelId) {
    return request('icra-dosyalari', { params: { personel_id: personelId } })
  },
  getIcraTaslak(icraId) {
    return request('icra-taslak', { params: { icra_id: icraId } })
  },
  generateAiTaslak(formData, csrfToken) {
    return request('ai-taslak', { method: 'POST', body: formData, csrfToken })
  },
  reviseAiText(payload, csrfToken) {
    return request('ai-duzenle', { method: 'POST', body: payload, csrfToken })
  },
  getFileStreamUrl(idOrPath, isPath = false, download = false) {
    const param = isPath ? `path=${encodeURIComponent(idOrPath)}` : `id=${encodeURIComponent(idOrPath)}`
    return `/api/evrak.php?action=stream-file&${param}${download ? '&download=1' : ''}`
  },
  getPdfUrl(id) {
    return `/api/evrak.php?action=pdf&id=${encodeURIComponent(id)}`
  },
  kepDetail(id, kepId = '') {
    return request('kep-detail', { params: { id, kep_id: kepId } })
  },
  syncKepDelil(id, kepId = '', csrfToken = '') {
    const fd = new FormData()
    fd.append('id', id)
    if (kepId) fd.append('kep_id', kepId)
    return request('kep-sync-delil', { method: 'POST', body: fd, csrfToken })
  },
  getKepDelilXmlUrl(delilId) {
    return `/api/evrak.php?action=kep-delil-indir&delil_id=${encodeURIComponent(delilId)}`
  },
}
