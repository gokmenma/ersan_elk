async function request(action, options = {}) {
  const method = options.method || 'GET'
  let url = `/api/kasa.php?action=${encodeURIComponent(action)}`
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
    const error = new Error(payload.message || 'Kasa işlemi gerçekleştirilemedi.')
    error.status = response.status
    throw error
  }

  return payload
}

export const kasaApi = {
  list(params = {}) {
    return request('list', { params })
  },
  detail(id) {
    return request('detail', { params: { id } })
  },
  meta() {
    return request('meta')
  },
  tumHareketler(params = {}) {
    return request('tum_hareketler', { params })
  },
  save(data, csrfToken) {
    return request('save', { method: 'POST', body: data, csrfToken })
  },
  delete(id, csrfToken) {
    return request('delete', { method: 'POST', body: { id }, csrfToken })
  },
}
