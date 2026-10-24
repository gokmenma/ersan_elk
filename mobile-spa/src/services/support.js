async function request(action, options = {}) {
  const method = options.method || 'GET'
  let url = `/api/support.php?action=${encodeURIComponent(action)}`
  const config = {
    method,
    credentials: 'same-origin',
    headers: { Accept: 'application/json', ...(options.headers || {}) },
  }
  if (method === 'GET' && options.params) {
    const query = new URLSearchParams(Object.entries(options.params).filter(([, value]) => value !== '' && value != null)).toString()
    if (query) url += `&${query}`
  }
  if (method === 'POST') {
    config.headers['X-CSRF-Token'] = options.csrfToken || ''
    if (options.body instanceof FormData) config.body = options.body
    else {
      config.headers['Content-Type'] = 'application/json'
      config.body = JSON.stringify(options.body || {})
    }
  }
  const response = await fetch(url, config)
  const payload = await response.json().catch(() => ({ success: false, message: 'Sunucudan geçersiz yanıt alındı.' }))
  if (!response.ok || !payload.success) throw new Error(payload.message || 'Canlı destek işlemi gerçekleştirilemedi.')
  return payload
}

export const supportApi = {
  list(params = {}) { return request('list', { params }) },
  detail(id, afterId = '') { return request('detail', { params: { id, after_id: afterId } }) },
  poll(id, afterId = '') { return request('poll', { params: { id, after_id: afterId } }) },
  send(id, message, image, csrfToken) {
    const body = new FormData()
    body.append('id', id)
    body.append('message', message)
    if (image) body.append('image', image)
    return request('send', { method: 'POST', body, csrfToken })
  },
  updateStatus(id, status, csrfToken) { return request('update_status', { method: 'POST', body: { id, status }, csrfToken }) },
  setAvailability(status, csrfToken) { return request('set_availability', { method: 'POST', body: { status }, csrfToken }) },
  getSettings() { return request('get_settings') },
  saveSettings(data, csrfToken) { return request('save_settings', { method: 'POST', body: data, csrfToken }) },
}
