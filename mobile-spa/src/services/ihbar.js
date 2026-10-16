import { reactive } from 'vue'

export const ihbarState = reactive({
  activeStatus: '', // '' (tümü) | 'yeni' | 'yonlendirildi' | 'islemde' | 'olumlu' | 'olumsuz'
  searchQuery: '',
  selectedIlce: '',
  dateRange: {
    baslangic: '',
    bitis: '',
  },
  counts: {
    toplam: 0,
    yeni: 0,
    yonlendirildi: 0,
    islemde: 0,
    olumlu: 0,
    olumsuz: 0,
    devam: 0,
    sonuclanan: 0,
    basari_orani: 0,
  },
  selectedIhbarToken: null,
  drawerOpen: false,
  statsOpen: false,
  newIhbarOpen: false,
})

export function setIhbarStatus(status) {
  ihbarState.activeStatus = status
}

export function openIhbarDrawer(token) {
  ihbarState.selectedIhbarToken = token
  ihbarState.drawerOpen = true
}

export function closeIhbarDrawer() {
  ihbarState.drawerOpen = false
  ihbarState.selectedIhbarToken = null
}

export function openIhbarStats() {
  ihbarState.statsOpen = true
}

export function closeIhbarStats() {
  ihbarState.statsOpen = false
}

export function openNewIhbarModal() {
  ihbarState.newIhbarOpen = true
}

export function closeNewIhbarModal() {
  ihbarState.newIhbarOpen = false
}

async function request(action, options = {}) {
  const method = options.method || 'GET'
  let url = `/api/ihbar.php?action=${encodeURIComponent(action)}`
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

  if (!response.ok || !payload.success) {
    const error = new Error(payload.message || 'İhbar işlemi gerçekleştirilemedi.')
    error.status = response.status
    error.payload = payload
    throw error
  }

  return payload
}

export const ihbarApi = {
  list(params = {}) {
    return request('list', { params })
  },
  detail(token) {
    return request('detail', { params: { token } })
  },
  stats(params = {}) {
    return request('stats', { params })
  },
  assign(token, personelTokens, csrfToken) {
    const fd = new FormData()
    fd.append('action', 'assign')
    fd.append('token', token)
    personelTokens.forEach((pt, idx) => {
      fd.append(`personel_tokens[${idx}]`, pt)
    })
    return request('assign', { method: 'POST', body: fd, csrfToken })
  },
  addNote(token, aciklama, csrfToken) {
    const fd = new FormData()
    fd.append('action', 'addNote')
    fd.append('token', token)
    fd.append('aciklama', aciklama)
    return request('addNote', { method: 'POST', body: fd, csrfToken })
  },
  close(token, durum, tutanakNo = '', sebep = '', csrfToken) {
    const fd = new FormData()
    fd.append('action', 'close')
    fd.append('token', token)
    fd.append('durum', durum)
    if (tutanakNo) fd.append('tutanak_no', tutanakNo)
    if (sebep) fd.append('sebep', sebep)
    return request('close', { method: 'POST', body: fd, csrfToken })
  },
  cancelResult(token, csrfToken) {
    const fd = new FormData()
    fd.append('action', 'cancelResult')
    fd.append('token', token)
    return request('cancelResult', { method: 'POST', body: fd, csrfToken })
  },
  create(formData, csrfToken) {
    formData.append('action', 'create')
    return request('create', { method: 'POST', body: formData, csrfToken })
  },
  getPhotoUrl(token, boyut = '') {
    let url = `/api/ihbar.php?action=foto&token=${encodeURIComponent(token)}`
    if (boyut) url += `&boyut=${encodeURIComponent(boyut)}`
    return url
  },
}
