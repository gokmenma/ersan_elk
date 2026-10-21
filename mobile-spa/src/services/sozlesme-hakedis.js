async function request(action, options = {}) {
  const method = options.method || 'GET'
  let url = `/api/sozlesme-hakedis.php?action=${encodeURIComponent(action)}`
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
  const payload = await response.json().catch(() => ({
    success: false,
    message: 'Sunucudan geçersiz yanıt alındı.',
  }))

  if (!response.ok || !payload.success) {
    const error = new Error(payload.message || 'İşlem gerçekleştirilemedi.')
    error.status = response.status
    throw error
  }

  return payload
}

export const sozlesmeApi = {
  listContracts(params = {}) {
    return request('contracts_list', { params })
  },
  getContract(id) {
    return request('contract_detail', { params: { id } })
  },
  saveContract(data, csrfToken) {
    return request('save_contract', { method: 'POST', body: data, csrfToken })
  },
  deleteContract(id, csrfToken) {
    return request('delete_contract', { method: 'POST', body: { id }, csrfToken })
  },
  saveRevizyon(data, csrfToken) {
    return request('save_is_revizyonu', { method: 'POST', body: data, csrfToken })
  },
  saveSureUzatimi(data, csrfToken) {
    return request('save_sure_uzatimi', { method: 'POST', body: data, csrfToken })
  },
  getHakedis(id) {
    return request('hakedis_detail', { params: { id } })
  },
  saveHakedis(data, csrfToken) {
    return request('save_hakedis', { method: 'POST', body: data, csrfToken })
  },
  deleteHakedis(id, csrfToken) {
    return request('delete_hakedis', { method: 'POST', body: { id }, csrfToken })
  },
  updateHakedisParametreler(data, csrfToken) {
    return request('update_hakedis_parametreler', { method: 'POST', body: data, csrfToken })
  },
  updateKalemMiktar(data, csrfToken) {
    return request('update_kalem_miktar', { method: 'POST', body: data, csrfToken })
  },
  fetchEndeks(params = {}) {
    return request('fetch_endeks', { params })
  },
}
