import { reactive } from 'vue'

export const faturaState = reactive({
  activeTab: 'ozet', // 'ozet' | 'giden' | 'gelen' | 'taslak'
  drawerOpen: true,
  counts: {
    giden: 0,
    gelen: 0,
    taslak: 0,
  },
})

export function setFaturaTab(tab) {
  faturaState.activeTab = tab
}

export function openFaturaDrawer() {
  faturaState.drawerOpen = true
}

export function closeFaturaDrawer() {
  faturaState.drawerOpen = false
}

export function toggleFaturaDrawer() {
  faturaState.drawerOpen = !faturaState.drawerOpen
}

async function request(action, options = {}) {
  const method = options.method || 'GET'
  let url = `/api/fatura.php?action=${encodeURIComponent(action)}`
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
    const error = new Error(payload.message || 'Fatura işlemi gerçekleştirilemedi.')
    error.status = response.status
    throw error
  }

  return payload
}

export const faturaApi = {
  dashboard(params = {}) {
    return request('dashboard', { params })
  },
  list(params = {}) {
    return request('list', { params })
  },
  detail(id) {
    return request('detail', { params: { id } })
  },
  previewHtml(id) {
    return request('preview_html', { params: { id } })
  },
  checkTaxpayer(vknTckn, csrfToken) {
    return request('check_taxpayer', { method: 'POST', body: { vkn_tckn: vknTckn }, csrfToken })
  },
  customers(params = {}) {
    return request('customers', { params })
  },
  products(params = {}) {
    return request('products', { params })
  },
  serials() {
    return request('serials')
  },
  calculate(lines) {
    return request('calculate', { method: 'POST', body: { lines } })
  },
  saveDraft(data, csrfToken) {
    return request('save_draft', { method: 'POST', body: data, csrfToken })
  },
  deleteDraft(invoiceId, csrfToken) {
    return request('delete_draft', { method: 'POST', body: { invoice_id: invoiceId }, csrfToken })
  },
  sendInvoice(invoiceId, csrfToken) {
    return request('send_invoice', { method: 'POST', body: { invoice_id: invoiceId }, csrfToken })
  },
  respondCommercial(invoiceId, responseType, reason = '', csrfToken) {
    return request('respond_commercial', {
      method: 'POST',
      body: { invoice_id: invoiceId, response_type: responseType, reason },
      csrfToken,
    })
  },
}
