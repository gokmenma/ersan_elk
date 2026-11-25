async function request(action, options = {}) {
  const method = options.method || 'GET'
  const config = {
    method,
    credentials: 'same-origin',
    headers: { Accept: 'application/json' },
  }
  if (method === 'POST') {
    config.headers['X-CSRF-Token'] = options.csrfToken || ''
    config.headers['Content-Type'] = 'application/json'
    config.body = JSON.stringify(options.body || {})
  }
  const response = await fetch(`/api/aparat-takip.php?action=${encodeURIComponent(action)}`, config)
  const payload = await response.json().catch(() => ({ success: false, message: 'Sunucudan geçersiz yanıt alındı.' }))
  if (!response.ok || !payload.success) throw new Error(payload.message || 'Aparat işlemi gerçekleştirilemedi.')
  return payload
}

export const aparatApi = {
  overview() { return request('overview') },
  move(body, csrfToken) { return request('move', { method: 'POST', body, csrfToken }) },
}
