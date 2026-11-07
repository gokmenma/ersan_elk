import { auth } from './auth.js'

async function request(action, options = {}) {
  const method = options.method || 'GET'
  let url = `/api/ik-ise-alim.php?action=${encodeURIComponent(action)}`

  const config = {
    method,
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
    },
  }

  if (method === 'GET' && options.params) {
    const query = new URLSearchParams(
      Object.entries(options.params).filter(([, value]) => value !== '' && value != null)
    ).toString()
    if (query) url += `&${query}`
  }

  if (method === 'POST') {
    config.headers['X-CSRF-Token'] = auth.state.csrfToken || options.csrfToken || ''
    if (options.body instanceof FormData) {
      config.body = options.body
    } else {
      config.headers['Content-Type'] = 'application/x-www-form-urlencoded'
      const form = new URLSearchParams()
      for (const [key, value] of Object.entries(options.body || {})) {
        if (value !== undefined && value !== null) {
          form.append(key, typeof value === 'object' ? JSON.stringify(value) : String(value))
        }
      }
      config.body = form.toString()
    }
  }

  const response = await fetch(url, config)
  const payload = await response.json().catch(() => ({ success: false, message: 'Sunucudan geçersiz yanıt alındı.' }))

  if (!response.ok || payload.success === false) {
    const error = new Error(payload.message || 'İşe alım işlemi gerçekleştirilemedi.')
    error.status = response.status
    throw error
  }
  return payload
}

export const recruitmentApi = {
  // 1. İstatistikler
  getStats(gun = 30) {
    return request('stats', { params: { gun } })
  },

  // 2. Başvurular
  getApplications(params = {}) {
    return request('applications', { params })
  },

  // 3. Başvuru Detayı
  getApplicationDetail(id) {
    return request('application-detail', { params: { id } })
  },

  // 4. Durum Güncelle
  updateStatus(id, status) {
    return request('update-status', { method: 'POST', body: { id, status } })
  },

  // 5. İK Notu Kaydet
  saveNote(id, note) {
    return request('save-note', { method: 'POST', body: { id, note } })
  },

  // 6. AI Yeniden Değerlendir
  rerunAi(id) {
    return request('rerun-ai', { method: 'POST', body: { id } })
  },

  // 7. İlanlar
  getJobs() {
    return request('jobs')
  },

  // 7.1. İlan Detay
  getJobDetail(id) {
    return request('job-detail', { params: { id } })
  },

  // 7.2. İlan Kaydet / Güncelle
  saveJob(data) {
    return request('save-job', { method: 'POST', body: data })
  },

  // 7.3. İlan Sil
  deleteJob(id) {
    return request('delete-job', { method: 'POST', body: { id } })
  },

  // 8. İlan Durumu Güncelle
  updateJobStatus(id, status) {
    return request('job-status', { method: 'POST', body: { id, status } })
  },

  // 9. İlan Paylaşım Linki
  getJobLink(id) {
    return request('job-link', { params: { id } })
  },

  // 10. Mülakat Kaydet
  saveInterview(data) {
    return request('save-interview', { method: 'POST', body: data })
  },

  // 11. Mülakat Sil
  deleteInterview(mulakatId) {
    return request('delete-interview', { method: 'POST', body: { mulakat_id: mulakatId } })
  },

  // 12. Aday Bildirim Şablonu Hazırla
  prepareNotification(id, template) {
    return request('prepare-notification', { method: 'POST', body: { id, template } })
  },

  // 13. Aday Bildirimi Gönder
  sendNotification(data) {
    return request('send-notification', { method: 'POST', body: data })
  },

  // 14. Personele Dönüştür
  convertToPersonnel(id, tcKimlikNo, iseGirisTarihi) {
    return request('convert-to-personnel', {
      method: 'POST',
      body: {
        id,
        tc_kimlik_no: tcKimlikNo,
        ise_giris_tarihi: iseGirisTarihi,
      },
    })
  },

  // 15. CV Dosya URL
  getCvUrl(id) {
    return `/api/ik-ise-alim.php?action=cv-view&id=${encodeURIComponent(id)}`
  },

  // 16. Fotoğraf Dosya URL
  getPhotoUrl(id) {
    return `/api/ik-ise-alim.php?action=photo-view&id=${encodeURIComponent(id)}`
  },
}
