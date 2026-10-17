import { reactive } from 'vue'

const defaultEndDate = new Date().toISOString().slice(0, 10)
const defaultStartDate = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().slice(0, 10)

export const kacakState = reactive({
  activeTab: 'all', // 'all' | 'beklemede' | 'onaylandi' | 'reddedildi' | 'iptal'
  searchQuery: '',
  startDate: defaultStartDate,
  endDate: defaultEndDate,
  counts: {
    toplam: 0,
    beklemede: 0,
    onaylandi: 0,
    reddedildi: 0,
    iptal: 0,
  },
  leaderboard: [],
  permissions: {
    can_approve: false,
    can_cancel: false,
    can_revert_cancel: false,
  },
})

export function setKacakTab(tab) {
  kacakState.activeTab = tab
}

export function setKacakDateRange(start, end) {
  kacakState.startDate = start
  kacakState.endDate = end
}

async function request(action, options = {}) {
  const method = options.method || 'GET'
  let url = `/api/kacak.php?action=${encodeURIComponent(action)}`
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
    const error = new Error(payload.message || 'Kaçak kontrol işlemi gerçekleştirilemedi.')
    error.status = response.status
    error.payload = payload
    throw error
  }

  return payload
}

export const kacakApi = {
  list(params = {}) {
    return request('list', { params })
  },
  detail(token) {
    return request('detail', { params: { token } })
  },
  photos(token) {
    return request('photos', { params: { token } })
  },
  approve(token, csrfToken) {
    return request('approve', { method: 'POST', body: { token }, csrfToken })
  },
  reject(token, redNedeni, csrfToken) {
    return request('reject', { method: 'POST', body: { token, red_nedeni: redNedeni }, csrfToken })
  },
  cancel(token, iptalAciklama, hakedistenDus, csrfToken) {
    return request('cancel', {
      method: 'POST',
      body: { token, iptal_aciklama: iptalAciklama, hakedisten_dus: hakedistenDus ? 1 : 0 },
      csrfToken,
    })
  },
  revertCancel(token, csrfToken) {
    return request('revert_cancel', { method: 'POST', body: { token }, csrfToken })
  },
  getPhotoUrl(token, boyut = '') {
    let url = `/api/kacak.php?action=foto&token=${encodeURIComponent(token)}`
    if (boyut) url += `&boyut=${encodeURIComponent(boyut)}`
    return url
  },
  getZipUrl(token) {
    return `/api/kacak.php?action=download_zip&token=${encodeURIComponent(token)}`
  },
}
