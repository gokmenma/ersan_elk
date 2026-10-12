import { auth } from './auth.js'

async function request(action, options = {}) {
  const url = options.params
    ? `/api/requests.php?${new URLSearchParams(options.params).toString()}`
    : '/api/requests.php'

  const response = await fetch(url, {
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
      'X-CSRF-Token': auth.state.csrfToken,
      ...(options.headers || {}),
    },
    ...options,
  })

  const payload = await response.json().catch(() => ({ success: false, message: 'Sunucu yanıtı alınamadı.' }))
  if (!response.ok || payload.success === false) {
    const error = new Error(payload.message || 'İşlem tamamlanamadı.')
    error.status = response.status
    throw error
  }
  return payload
}

export const requestsApi = {
  async getRequests({ tab = 'all', filter = 'pending' } = {}) {
    return await request('get', {
      params: { tab, filter },
    })
  },

  async avansOnayla({ id, onay_tipi, farkli_tutar, aciklama, hesaba_isle }) {
    return await request('post', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'avans-onayla',
        id,
        onay_tipi,
        farkli_tutar,
        aciklama,
        hesaba_isle,
      }),
    })
  },

  async avansReddet({ id, aciklama }) {
    return await request('post', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'avans-reddet',
        id,
        aciklama,
      }),
    })
  },

  async izinOnayla({ id, onay_tipi, farkli_baslangic, farkli_bitis, aciklama }) {
    return await request('post', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'izin-onayla',
        id,
        onay_tipi,
        farkli_baslangic,
        farkli_bitis,
        aciklama,
      }),
    })
  },

  async izinReddet({ id, aciklama }) {
    return await request('post', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'izin-reddet',
        id,
        aciklama,
      }),
    })
  },

  async talepIslemeAl({ id }) {
    return await request('post', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'talep-isleme-al',
        id,
      }),
    })
  },

  async talepCozuldu({ id, aciklama }) {
    return await request('post', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'talep-cozuldu',
        id,
        aciklama,
      }),
    })
  },

  async sil({ type, id, aciklama }) {
    const action = type === 'avans' ? 'avans-sil' : type === 'izin' ? 'izin-sil' : 'talep-sil'
    return await request('post', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action,
        id,
        aciklama,
      }),
    })
  },
}
