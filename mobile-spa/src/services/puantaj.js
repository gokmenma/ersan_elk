import { auth } from './auth.js'

async function request(action, options = {}) {
  const method = options.method || 'GET'
  let url = `/api/puantaj.php?action=${encodeURIComponent(action)}`
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
      if (options.csrfToken || auth.state.csrfToken) {
        fetchOptions.headers['X-CSRF-Token'] = options.csrfToken || auth.state.csrfToken
      }
    } else if (options.body) {
      fetchOptions.headers['Content-Type'] = 'application/json'
      if (options.csrfToken || auth.state.csrfToken) {
        fetchOptions.headers['X-CSRF-Token'] = options.csrfToken || auth.state.csrfToken
      }
      fetchOptions.body = JSON.stringify(options.body)
    }
  }

  const response = await fetch(url, fetchOptions)
  const payload = await response.json().catch(() => ({
    success: false,
    message: 'Sunucudan geçersiz yanıt alındı.',
  }))

  if (!response.ok || payload.success === false) {
    const error = new Error(payload.message || 'Puantaj işlemi gerçekleştirilemedi.')
    error.status = response.status
    error.payload = payload
    throw error
  }

  return payload
}

export const puantajApi = {
  getDefinitions() {
    return request('definitions')
  },
  getCalendarData(params = {}) {
    return request('calendar-data', { params })
  },
  saveBulkEntries({ ay, yil, data, overtimes }, csrfToken) {
    return request('save-bulk', {
      method: 'POST',
      body: {
        action: 'save-bulk',
        ay,
        yil,
        data,
        overtimes: overtimes || [],
      },
      csrfToken,
    })
  },
  getSgkReports(params = {}) {
    return request('sgk-reports', { params })
  },
}
