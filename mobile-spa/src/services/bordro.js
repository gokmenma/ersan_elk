async function request(action, options = {}) {
  const method = options.method || 'GET'
  let url = `/api/bordro.php?action=${encodeURIComponent(action)}`
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

  if (!response.ok || payload.success === false) {
    const error = new Error(payload.message || 'Bordro işlemi gerçekleştirilemedi.')
    error.status = response.status
    error.payload = payload
    throw error
  }

  return payload
}

export const bordroApi = {
  init(params = {}) {
    return request('init', { params })
  },
  getDetail(token, action = 'get-detail-old') {
    return request(action, {
      method: 'POST',
      body: { action, bordro_token: token },
    })
  },
  calculate(donemToken, personelTokens = [], csrfToken) {
    return request('maas-hesapla', {
      method: 'POST',
      body: {
        action: 'maas-hesapla',
        donem_token: donemToken,
        personel_tokens: personelTokens,
      },
      csrfToken,
    })
  },
  togglePeriod(donemToken, isCurrentlyClosed, forceClose = false, csrfToken) {
    return request(isCurrentlyClosed ? 'donem-ac' : 'donem-kapat', {
      method: 'POST',
      body: {
        action: isCurrentlyClosed ? 'donem-ac' : 'donem-kapat',
        donem_token: donemToken,
        ...(forceClose ? { force_close: '1' } : {}),
      },
      csrfToken,
    })
  },
  distribute(bordroToken, { banka, sodexo, diger }, csrfToken) {
    return request('odeme-dagit', {
      method: 'POST',
      body: {
        action: 'odeme-dagit',
        bordro_token: bordroToken,
        banka_odemesi: String(banka ?? '').replace(',', '.'),
        sodexo_odemesi: String(sodexo ?? '').replace(',', '.'),
        diger_odeme: String(diger ?? '').replace(',', '.'),
      },
      csrfToken,
    })
  },
  resetAllPayments(donemToken, csrfToken) {
    return request('odeme-reset-all', {
      method: 'POST',
      body: {
        action: 'odeme-reset-all',
        donem_token: donemToken,
      },
      csrfToken,
    })
  },
  resetSinglePayment(bordroToken, csrfToken) {
    return request('odeme-reset', {
      method: 'POST',
      body: {
        action: 'odeme-reset',
        bordro_token: bordroToken,
      },
      csrfToken,
    })
  },
  addAdjustment({ kind, personelToken, donemToken, tur, tutar, aciklama, tarih }, csrfToken) {
    const isIncome = kind === 'gelir'
    const payload = {
      action: isIncome ? 'personel-gelir-ekle' : 'personel-kesinti-ekle',
      personel_token: personelToken,
      donem_token: donemToken,
      aciklama: aciklama || '',
      tarih: tarih || new Intl.DateTimeFormat('tr-TR').format(new Date()),
    }
    if (isIncome) {
      payload.ek_odeme_tur = tur
      payload.gelir_tutar = String(tutar || '').replace(',', '.')
    } else {
      payload.kesinti_tur = tur
      payload.kesinti_tutar = String(tutar || '').replace(',', '.')
    }

    return request(payload.action, {
      method: 'POST',
      body: payload,
      csrfToken,
    })
  },
}
