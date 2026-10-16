import { reactive } from 'vue'

const now = new Date()

export const nobetState = reactive({
  activeTab: 'bekleyen', // 'bekleyen' | 'planlama' | 'havuz'
  selectedMonth: now.getMonth() + 1,
  selectedYear: now.getFullYear(),
  durumFilter: 'bekleyen', // 'bekleyen' | 'onaylanan' | 'tumu'
  tipFilter: '', // '' | 'hafta_ici' | 'hafta_sonu' | 'resmi_tatil'
  searchQuery: '',
  stats: {
    total_nobet: 0,
    total_bekleyen: 0,
    total_onaylanan: 0,
    hafta_sonu_bekleyen: 0,
    resmi_tatil_bekleyen: 0,
    hafta_ici_bekleyen: 0,
  },
})

export function setNobetTab(tab) {
  nobetState.activeTab = tab
}

export function setNobetMonth(month, year) {
  nobetState.selectedMonth = month
  if (year) nobetState.selectedYear = year
}

async function request(action, options = {}) {
  const method = options.method || 'GET'
  let url = `/api/nobet.php?action=${encodeURIComponent(action)}`
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
    const error = new Error(payload.message || 'Nöbet işlemi gerçekleştirilemedi.')
    error.status = response.status
    error.payload = payload
    throw error
  }

  return payload
}

export const nobetApi = {
  list(params = {}) {
    return request('list', { params })
  },
  calendar(params = {}) {
    return request('calendar', { params })
  },
  personnelSummary(params = {}) {
    return request('personnel_summary', { params })
  },
  personnelList() {
    return request('personnel_list')
  },
  approve(id, csrfToken) {
    return request('approve', { method: 'POST', body: { id }, csrfToken })
  },
  unapprove(id, csrfToken) {
    return request('unapprove', { method: 'POST', body: { id }, csrfToken })
  },
  delete(id, csrfToken) {
    return request('delete', { method: 'POST', body: { id }, csrfToken })
  },
  bulkApprove(ids, csrfToken) {
    return request('bulk_approve', { method: 'POST', body: { ids }, csrfToken })
  },
  bulkUnapprove(ids, csrfToken) {
    return request('bulk_unapprove', { method: 'POST', body: { ids }, csrfToken })
  },
  bulkDelete(ids, csrfToken) {
    return request('bulk_delete', { method: 'POST', body: { ids }, csrfToken })
  },
  add(data, csrfToken) {
    return request('add', { method: 'POST', body: data, csrfToken })
  },
  sendReminder(data, csrfToken) {
    return request('send_reminder', { method: 'POST', body: data, csrfToken })
  },
}
