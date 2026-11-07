async function request(action, options = {}) {
  const method = options.method || 'GET'
  let url = `/api/site-mesajlar.php?action=${encodeURIComponent(action)}`
  const config = {
    method,
    credentials: 'same-origin',
    headers: { Accept: 'application/json' },
  }
  if (method === 'GET' && options.params) {
    const query = new URLSearchParams(Object.entries(options.params).filter(([, value]) => value !== '' && value != null)).toString()
    if (query) url += `&${query}`
  }
  if (method === 'POST') {
    config.headers['X-CSRF-Token'] = options.csrfToken || ''
    config.headers['Content-Type'] = 'application/json'
    config.body = JSON.stringify(options.body || {})
  }
  const response = await fetch(url, config)
  const payload = await response.json().catch(() => ({ success: false, message: 'Sunucudan geçersiz yanıt alındı.' }))
  if (!response.ok || !payload.success) throw new Error(payload.message || 'Site mesajı işlemi gerçekleştirilemedi.')
  return payload
}

export const siteMesajApi = {
  list(params = {}) { return request('list', { params }) },
  detail(id) { return request('detail', { params: { id } }) },
  updateStatus(id, status, note, csrfToken) { return request('update_status', { method: 'POST', body: { id, status, note }, csrfToken }) },
}
