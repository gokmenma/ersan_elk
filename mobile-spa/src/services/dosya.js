import { auth } from './auth.js'

const BASE = '/api/dosya-yonetimi.php'

async function request(action, { method = 'GET', params = {}, body = null } = {}) {
  const query = new URLSearchParams({ action, ...params }).toString()
  const options = {
    method,
    credentials: 'same-origin',
    headers: { Accept: 'application/json' },
  }
  if (method === 'POST') {
    options.headers['X-CSRF-Token'] = auth.state.csrfToken
    if (body instanceof FormData) {
      options.body = body
    } else {
      options.headers['Content-Type'] = 'application/json'
      options.body = JSON.stringify(body || {})
    }
  }

  const response = await fetch(`${BASE}?${query}`, options)
  const payload = await response.json().catch(() => ({ success: false, message: response.status === 413 ? 'Dosya sunucu sınırını aşıyor (413).' : `Sunucudan geçersiz yanıt alındı (HTTP ${response.status}).` }))
  if (!response.ok || !payload.success) {
    const error = new Error(payload.message || 'İşlem gerçekleştirilemedi.')
    error.status = response.status
    throw error
  }
  return payload.data
}

export const dosyaApi = {
  agac: () => request('agac'),
  ozet: () => request('ozet'),
  icerik: (klasorId, arama = '', bolum = 'dosyalarim') => request('icerik', { params: { klasor_id: klasorId || 'kok', arama, bolum } }),
  indirUrl: (id, inline = false) => `${BASE}?action=indir&id=${encodeURIComponent(id)}${inline ? '&inline=1' : ''}`,
  yukle: (klasorId, files) => {
    const fd = new FormData()
    fd.append('klasor_id', klasorId || '')
    Array.from(files).forEach((f) => fd.append('dosyalar[]', f))
    return request('yukle', { method: 'POST', body: fd })
  },
  klasorOlustur: (parentId, ad, alan = 'kisisel') => request('klasor_olustur', { method: 'POST', body: { parent_id: parentId || '', ad, alan } }),
  klasorAdlandir: (id, ad) => request('klasor_adlandir', { method: 'POST', body: { id, ad } }),
  klasorSil: (id) => request('klasor_sil', { method: 'POST', body: { id } }),
  dosyaAdlandir: (id, ad) => request('dosya_adlandir', { method: 'POST', body: { id, ad } }),
  dosyaTasi: (id, hedefId) => request('dosya_tasi', { method: 'POST', body: { id, hedef_id: hedefId || '' } }),
  dosyaSil: (id) => request('dosya_sil', { method: 'POST', body: { id } }),
  paylasimOlustur: (id, gun, sifre, limit) => request('paylasim_olustur', { method: 'POST', body: { id, gun, sifre, limit } }),
  paylasimListele: (id) => request('paylasim_listele', { params: { id } }),
  erisimKullanicilari: () => request('erisim_kullanicilar'),
  erisimListele: (tur, id) => request('erisim_listele', { params: { tur, id } }),
  erisimVer: (tur, id, userId, seviye) => request('erisim_ver', { method: 'POST', body: { tur, id, user_id: userId, seviye } }),
  erisimKaldir: (id) => request('erisim_kaldir', { method: 'POST', body: { id } }),
  paylasimIptal: (id) => request('paylasim_iptal', { method: 'POST', body: { id } }),
}
