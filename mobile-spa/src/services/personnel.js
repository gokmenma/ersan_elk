async function get(action, params = {}) {
  const query = new URLSearchParams({ action, ...params })
  const response = await fetch(`/api/personnel.php?${query}`, {
    credentials: 'same-origin',
    headers: { Accept: 'application/json' },
  })
  const payload = await response.json().catch(() => ({ success: false, message: 'Sunucudan geçersiz yanıt alındı.' }))
  if (!response.ok || !payload.success) {
    const error = new Error(payload.message || 'Personel bilgileri alınamadı.')
    error.status = response.status
    throw error
  }
  return payload
}

export const personnelApi = {
  list(filters) {
    return get('list', filters)
  },
  detail(id, section = 'profile') {
    return get('detail', { id, section })
  },
  async save(data, csrfToken) {
    const response = await fetch('/api/personnel.php?action=save', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
      body: JSON.stringify(data),
    })
    const payload = await response.json().catch(() => ({ success: false, message: 'Sunucudan geçersiz yanıt alındı.' }))
    if (!response.ok || !payload.success) throw new Error(payload.message || 'Personel kaydedilemedi.')
    return payload
  },
  async saveGeneral(id, data, csrfToken) {
    const response = await fetch('/api/personnel.php?action=save_general', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
      body: JSON.stringify({ id, ...data }),
    })
    const payload = await response.json().catch(() => ({ success: false, message: 'Sunucudan geçersiz yanıt alındı.' }))
    if (!response.ok || !payload.success) throw new Error(payload.message || 'Genel bilgiler kaydedilemedi.')
    return payload
  },
  async saveWorkInfo(id, data, csrfToken) {
    const response = await fetch('/api/personnel.php?action=save_work_info', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
      body: JSON.stringify({ id, ...data }),
    })
    const payload = await response.json().catch(() => ({ success: false, message: 'Sunucudan geçersiz yanıt alındı.' }))
    if (!response.ok || !payload.success) throw new Error(payload.message || 'Çalışma bilgileri kaydedilemedi.')
    return payload
  },
  async saveTeamHistory(data, csrfToken) {
    const response = await fetch('/api/personnel.php?action=save_team_history', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
      body: JSON.stringify(data),
    })
    const payload = await response.json().catch(() => ({ success: false, message: 'Sunucudan geçersiz yanıt alındı.' }))
    if (!response.ok || !payload.success) throw new Error(payload.message || 'Ekip ataması kaydedilemedi.')
    return payload
  },
  async deleteTeamHistory(id, personelId, force, csrfToken) {
    const response = await fetch('/api/personnel.php?action=delete_team_history', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
      body: JSON.stringify({ id, personel_id: personelId, force }),
    })
    const payload = await response.json().catch(() => ({ success: false, message: 'Sunucudan geçersiz yanıt alındı.' }))
    if (!response.ok || !payload.success) {
      if (payload.status === 'confirm') return payload
      throw new Error(payload.message || 'Ekip ataması silinemedi.')
    }
    return payload
  },
  async saveWorkHistory(data, csrfToken) {
    const response = await fetch('/api/personnel.php?action=save_work_history', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
      body: JSON.stringify(data),
    })
    const payload = await response.json().catch(() => ({ success: false, message: 'Sunucudan geçersiz yanıt alındı.' }))
    if (!response.ok || !payload.success) throw new Error(payload.message || 'Çalışma dönemi kaydedilemedi.')
    return payload
  },
  async deleteWorkHistory(id, personelId, csrfToken) {
    const response = await fetch('/api/personnel.php?action=delete_work_history', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
      body: JSON.stringify({ id, personel_id: personelId }),
    })
    const payload = await response.json().catch(() => ({ success: false, message: 'Sunucudan geçersiz yanıt alındı.' }))
    if (!response.ok || !payload.success) throw new Error(payload.message || 'Çalışma dönemi silinemedi.')
    return payload
  },
  async saveLeave(data, csrfToken) {
    const response = await fetch('/api/personnel.php?action=save_leave', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
      body: JSON.stringify(data),
    })
    const payload = await response.json().catch(() => ({ success: false, message: 'Sunucudan geçersiz yanıt alındı.' }))
    if (!response.ok || !payload.success) throw new Error(payload.message || 'İzin kaydı kaydedilemedi.')
    return payload
  },
  async deleteLeave(id, personelId, csrfToken) {
    const response = await fetch('/api/personnel.php?action=delete_leave', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
      body: JSON.stringify({ id, personel_id: personelId }),
    })
    const payload = await response.json().catch(() => ({ success: false, message: 'Sunucudan geçersiz yanıt alındı.' }))
    if (!response.ok || !payload.success) throw new Error(payload.message || 'İzin kaydı silinemedi.')
    return payload
  },
  async approveOrRejectLeave(id, personelId, status, note, csrfToken) {
    const response = await fetch('/api/personnel.php?action=approve_or_reject_leave', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
      body: JSON.stringify({ id, personel_id: personelId, status, note }),
    })
    const payload = await response.json().catch(() => ({ success: false, message: 'Sunucudan geçersiz yanıt alındı.' }))
    if (!response.ok || !payload.success) throw new Error(payload.message || 'İzin onay durumu güncellenemedi.')
    return payload
  },
  async saveEkOdeme(data, csrfToken) {
    const response = await fetch('/api/personnel.php?action=save_ek_odeme', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
      body: JSON.stringify(data),
    })
    const payload = await response.json().catch(() => ({ success: false, message: 'Sunucudan geçersiz yanıt alındı.' }))
    if (!response.ok || !payload.success) throw new Error(payload.message || 'Ek ödeme kaydedilemedi.')
    return payload
  },
  async deleteEkOdeme(id, personelId, csrfToken) {
    const response = await fetch('/api/personnel.php?action=delete_ek_odeme', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
      body: JSON.stringify({ id, personel_id: personelId }),
    })
    const payload = await response.json().catch(() => ({ success: false, message: 'Sunucudan geçersiz yanıt alındı.' }))
    if (!response.ok || !payload.success) throw new Error(payload.message || 'Ek ödeme silinemedi.')
    return payload
  },
  async sonlandirEkOdeme(id, personelId, bitisDonemi, csrfToken) {
    const response = await fetch('/api/personnel.php?action=sonlandir_ek_odeme', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
      body: JSON.stringify({ id, personel_id: personelId, bitis_donemi: bitisDonemi }),
    })
    const payload = await response.json().catch(() => ({ success: false, message: 'Sunucudan geçersiz yanıt alındı.' }))
    if (!response.ok || !payload.success) throw new Error(payload.message || 'Ek ödeme sonlandırılamadı.')
    return payload
  },
  async saveKesinti(data, csrfToken) {
    const response = await fetch('/api/personnel.php?action=save_kesinti', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
      body: JSON.stringify(data),
    })
    const payload = await response.json().catch(() => ({ success: false, message: 'Sunucudan geçersiz yanıt alındı.' }))
    if (!response.ok || !payload.success) throw new Error(payload.message || 'Kesinti kaydedilemedi.')
    return payload
  },
  async deleteKesinti(id, personelId, csrfToken) {
    const response = await fetch('/api/personnel.php?action=delete_kesinti', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
      body: JSON.stringify({ id, personel_id: personelId }),
    })
    const payload = await response.json().catch(() => ({ success: false, message: 'Sunucudan geçersiz yanıt alındı.' }))
    if (!response.ok || !payload.success) throw new Error(payload.message || 'Kesinti silinemedi.')
    return payload
  },
  async sonlandirKesinti(id, personelId, bitisDonemi, csrfToken) {
    const response = await fetch('/api/personnel.php?action=sonlandir_kesinti', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
      body: JSON.stringify({ id, personel_id: personelId, bitis_donemi: bitisDonemi }),
    })
    const payload = await response.json().catch(() => ({ success: false, message: 'Sunucudan geçersiz yanıt alındı.' }))
    if (!response.ok || !payload.success) throw new Error(payload.message || 'Kesinti sonlandırılamadı.')
    return payload
  },
  async uploadEvrak(formData, csrfToken) {
    const response = await fetch('/api/personnel.php?action=upload_evrak', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'X-CSRF-Token': csrfToken },
      body: formData,
    })
    const payload = await response.json().catch(() => ({ success: false, message: 'Sunucudan geçersiz yanıt alındı.' }))
    if (!response.ok || !payload.success) throw new Error(payload.message || 'Evrak yüklenemedi.')
    return payload
  },
  async deleteEvrak(id, personelId, csrfToken) {
    const response = await fetch('/api/personnel.php?action=delete_evrak', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
      body: JSON.stringify({ id, personel_id: personelId }),
    })
    const payload = await response.json().catch(() => ({ success: false, message: 'Sunucudan geçersiz yanıt alındı.' }))
    if (!response.ok || !payload.success) throw new Error(payload.message || 'Evrak silinemedi.')
    return payload
  },
  async saveIcra(data, csrfToken) {
    const response = await fetch('/api/personnel.php?action=save_icra', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
      body: JSON.stringify(data),
    })
    const payload = await response.json().catch(() => ({ success: false, message: 'Sunucudan geçersiz yanıt alındı.' }))
    if (!response.ok || !payload.success) throw new Error(payload.message || 'İcra dosyası kaydedilemedi.')
    return payload
  },
  async deleteIcra(id, personelId, csrfToken) {
    const response = await fetch('/api/personnel.php?action=delete_icra', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
      body: JSON.stringify({ id, personel_id: personelId }),
    })
    const payload = await response.json().catch(() => ({ success: false, message: 'Sunucudan geçersiz yanıt alındı.' }))
    if (!response.ok || !payload.success) throw new Error(payload.message || 'İcra dosyası silinemedi.')
    return payload
  },
  async getIcraKesintileri(icraId, personelId) {
    const response = await fetch(`/api/personnel.php?action=icra_kesintileri&icra_id=${encodeURIComponent(icraId)}&personel_id=${encodeURIComponent(personelId)}`, {
      method: 'GET',
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
    })
    const payload = await response.json().catch(() => ({ success: false, message: 'Sunucudan geçersiz yanıt alındı.' }))
    if (!response.ok || !payload.success) throw new Error(payload.message || 'İcra kesintileri alınamadı.')
    return payload
  },
  async saveZimmet(data, csrfToken) {
    const response = await fetch('/api/personnel.php?action=save_zimmet', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
      body: JSON.stringify(data),
    })
    const payload = await response.json().catch(() => ({ success: false, message: 'Sunucudan geçersiz yanıt alındı.' }))
    if (!response.ok || !payload.success) throw new Error(payload.message || 'Zimmet kaydedilemedi.')
    return payload
  },
  async iadeZimmet(data, csrfToken) {
    const response = await fetch('/api/personnel.php?action=iade_zimmet', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
      body: JSON.stringify(data),
    })
    const payload = await response.json().catch(() => ({ success: false, message: 'Sunucudan geçersiz yanıt alındı.' }))
    if (!response.ok || !payload.success) throw new Error(payload.message || 'Zimmet iade işlemi yapılamadı.')
    return payload
  },
  async deleteZimmet(id, personelId, csrfToken) {
    const response = await fetch('/api/personnel.php?action=delete_zimmet', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
      body: JSON.stringify({ id, personel_id: personelId }),
    })
    const payload = await response.json().catch(() => ({ success: false, message: 'Sunucudan geçersiz yanıt alındı.' }))
    if (!response.ok || !payload.success) throw new Error(payload.message || 'Zimmet kaydı silinemedi.')
    return payload
  },
  async getZimmetHareketleri(id, personelId) {
    const response = await fetch(`/api/personnel.php?action=zimmet_hareketleri&id=${encodeURIComponent(id)}&personel_id=${encodeURIComponent(personelId)}`, {
      method: 'GET',
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
    })
    const payload = await response.json().catch(() => ({ success: false, message: 'Sunucudan geçersiz yanıt alındı.' }))
    if (!response.ok || !payload.success) throw new Error(payload.message || 'Zimmet hareketleri alınamadı.')
    return payload
  },
  async searchDemirbas(q) {
    const response = await fetch(`/api/personnel.php?action=search_demirbas&q=${encodeURIComponent(q)}`, {
      method: 'GET',
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
    })
    const payload = await response.json().catch(() => ({ success: false, message: 'Sunucudan geçersiz yanıt alındı.' }))
    if (!response.ok || !payload.success) throw new Error(payload.message || 'Demirbaş araması yapılamadı.')
    return payload
  },
  async getPuantajIsTakip(personelId, filters = {}) {
    const query = new URLSearchParams({ action: 'puantaj_is_takip', id: personelId, ...filters })
    const response = await fetch(`/api/personnel.php?${query}`, {
      method: 'GET',
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
    })
    const payload = await response.json().catch(() => ({ success: false, message: 'Sunucudan geçersiz yanıt alındı.' }))
    if (!response.ok || !payload.success) throw new Error(payload.message || 'İş ve görev kayıtları alınamadı.')
    return payload
  },
  async savePuantajJob(data, csrfToken) {
    const response = await fetch('/api/personnel.php?action=save_puantaj_job', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
      body: JSON.stringify(data),
    })
    const payload = await response.json().catch(() => ({ success: false, message: 'Sunucudan geçersiz yanıt alındı.' }))
    if (!response.ok || !payload.success) throw new Error(payload.message || 'Görev kaydı oluşturulamadı.')
    return payload
  },
  async deletePuantajJob(id, type, personelId, csrfToken) {
    const response = await fetch('/api/personnel.php?action=delete_puantaj_job', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
      body: JSON.stringify({ id, type, personel_id: personelId }),
    })
    const payload = await response.json().catch(() => ({ success: false, message: 'Sunucudan geçersiz yanıt alındı.' }))
    if (!response.ok || !payload.success) throw new Error(payload.message || 'Görev kaydı silinemedi.')
    return payload
  },
}


