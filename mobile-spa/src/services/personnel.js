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
  detail(id) {
    return get('detail', { id })
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
}


