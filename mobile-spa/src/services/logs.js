async function request(action, options = {}) {
  const method = options.method || 'GET'
  let url = `/api/logs.php?action=${encodeURIComponent(action)}`
  const fetchOptions = {
    method,
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
      ...(options.headers || {}),
    },
  }

  if (method === 'GET' && options.params) {
    const cleanParams = {}
    for (const [k, v] of Object.entries(options.params)) {
      if (v !== '' && v !== null && v !== undefined) {
        cleanParams[k] = v
      }
    }
    const query = new URLSearchParams(cleanParams).toString()
    if (query) url += `&${query}`
  }

  const response = await fetch(url, fetchOptions)
  const payload = await response.json().catch(() => ({ success: false, message: 'Sunucudan geçersiz yanıt alındı.' }))

  if (!response.ok || !payload.success) {
    const error = new Error(payload.message || 'Log kayıtları yüklenemedi.')
    error.status = response.status
    throw error
  }

  return payload
}

export const logsApi = {
  list(params = {}) {
    return request('list', { params })
  },
  detail(id) {
    return request('detail', { params: { id } })
  },
  meta() {
    return request('meta')
  },
}
