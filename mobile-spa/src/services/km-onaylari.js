import { reactive } from 'vue'

export const kmState = reactive({
  activeTab: 'pending', // 'pending' | 'unreported' | 'approved' | 'rejected'
  drawerOpen: true,
  turFilter: '', // '' (tümü) | 'sabah' | 'aksam'
  searchQuery: '',
  counts: {
    pending: 0,
    unreported: 0,
    approved: 0,
    rejected: 0,
  },
})

export function setKmTab(tab) {
  kmState.activeTab = tab
}

export function openKmDrawer() {
  kmState.drawerOpen = true
}

export function closeKmDrawer() {
  kmState.drawerOpen = false
}

export function toggleKmDrawer() {
  kmState.drawerOpen = !kmState.drawerOpen
}

async function request(action, options = {}) {
  const method = options.method || 'GET'
  let url = `/api/km-onaylari.php?action=${encodeURIComponent(action)}`
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
    const error = new Error(payload.message || 'KM işlemi gerçekleştirilemedi.')
    error.status = response.status
    error.payload = payload
    throw error
  }

  return payload
}

export const kmApi = {
  list(params = {}) {
    return request('list', { params })
  },
  approve(id, csrfToken) {
    return request('approve', { method: 'POST', body: { id }, csrfToken })
  },
  editApprove(id, km, aciklama = '', csrfToken) {
    return request('edit_approve', { method: 'POST', body: { id, km, aciklama }, csrfToken })
  },
  reject(id, redNedeni = '', csrfToken) {
    return request('reject', { method: 'POST', body: { id, red_nedeni: redNedeni }, csrfToken })
  },
  bulkApprove(ids, csrfToken) {
    return request('bulk_approve', { method: 'POST', body: { ids }, csrfToken })
  },
  aiCheck(ids, csrfToken) {
    return request('ai_check', { method: 'POST', body: { ids }, csrfToken })
  },
  sendReminder(personelId, tarih, tur = 'sabah', csrfToken) {
    return request('send_reminder', { method: 'POST', body: { personel_id: personelId, tarih, tur }, csrfToken })
  },
  bulkReminder(tarih, tur = 'sabah', csrfToken) {
    return request('bulk_reminder', { method: 'POST', body: { tarih, tur }, csrfToken })
  },
  getPhotoUrl(idOrToken) {
    if (!idOrToken) return ''
    const param = typeof idOrToken === 'number' || /^\d+$/.test(idOrToken) ? `id=${idOrToken}` : `token=${encodeURIComponent(idOrToken)}`
    return `/api/km-onaylari.php?action=foto&${param}`
  },
}
