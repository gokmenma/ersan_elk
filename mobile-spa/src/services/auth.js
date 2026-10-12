import { reactive, readonly } from 'vue'

const state = reactive({
  ready: false,
  authenticated: false,
  csrfToken: '',
  user: null,
  company: null,
  companies: [],
  permissions: {},
})

function applySession(payload) {
  state.authenticated = Boolean(payload.authenticated)
  state.csrfToken = payload.csrf_token || ''
  state.user = payload.user || null
  state.company = payload.company || null
  state.companies = payload.companies || []
  state.permissions = payload.permissions || {}
}

async function request(action = 'session', options = {}) {
  const response = await fetch(`/api/auth.php?action=${encodeURIComponent(action)}`, {
    credentials: 'same-origin',
    headers: { Accept: 'application/json', ...(options.headers || {}) },
    ...options,
  })
  const payload = await response.json().catch(() => ({ success: false, message: 'Sunucudan geçersiz yanıt alındı.' }))
  if (!response.ok || payload.success === false) {
    const error = new Error(payload.message || 'İşlem tamamlanamadı.')
    error.status = response.status
    throw error
  }
  return payload
}

export const auth = {
  state: readonly(state),
  async bootstrap(force = false) {
    if (state.ready && !force) return state
    try {
      applySession(await request())
    } finally {
      state.ready = true
    }
    return state
  },
  async login(credentials) {
    const payload = await request('login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': state.csrfToken },
      body: JSON.stringify(credentials),
    })
    applySession(payload)
    return state
  },
  async selectCompany(companyId) {
    const payload = await request('select-company', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': state.csrfToken },
      body: JSON.stringify({ company_id: companyId }),
    })
    applySession(payload)
  },
  async logout() {
    await request('logout', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': state.csrfToken },
      body: '{}',
    })
    state.authenticated = false
    state.user = null
    state.company = null
    state.companies = []
    state.permissions = {}
    state.csrfToken = ''
    state.ready = false
    await this.bootstrap(true)
  },
}
