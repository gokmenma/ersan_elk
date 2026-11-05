async function request(action, options = {}) {
  const method = options.method || 'GET'
  const headers = { Accept: 'application/json' }
  const fetchOptions = { method, credentials: 'same-origin', headers }

  if (method === 'POST') {
    headers['Content-Type'] = 'application/json'
    if (options.csrfToken) headers['X-CSRF-Token'] = options.csrfToken
    fetchOptions.body = JSON.stringify(options.body || {})
  }

  const response = await fetch(`/api/gorevler.php?action=${encodeURIComponent(action)}`, fetchOptions)
  const payload = await response.json().catch(() => ({ success: false, message: 'Sunucudan geçersiz yanıt alındı.' }))

  if (!response.ok || !payload.success) {
    const error = new Error(payload.message || 'Görev işlemi gerçekleştirilemedi.')
    error.status = response.status
    throw error
  }

  return payload
}

export const gorevlerApi = {
  list() {
    return request('list')
  },
  tamamla(gorevId, csrfToken) {
    return request('tamamla', { method: 'POST', body: { gorev_id: gorevId }, csrfToken })
  },
  geriAl(gorevId, csrfToken) {
    return request('geri-al', { method: 'POST', body: { gorev_id: gorevId }, csrfToken })
  },
}
