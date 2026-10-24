import { reactive, readonly } from 'vue'
import { clearDashboardCache } from './dashboard.js'

const MENU_ORDER_STORAGE_KEY = 'ersan_mobile_menu_order_v1'

function loadCachedMenuOrder() {
  try {
    const raw = localStorage.getItem(MENU_ORDER_STORAGE_KEY)
    if (!raw) return null
    const parsed = JSON.parse(raw)
    return parsed && typeof parsed === 'object' ? parsed : null
  } catch {
    return null
  }
}

function saveCachedMenuOrder(order) {
  try {
    if (order && typeof order === 'object') {
      localStorage.setItem(MENU_ORDER_STORAGE_KEY, JSON.stringify(order))
    } else {
      localStorage.removeItem(MENU_ORDER_STORAGE_KEY)
    }
  } catch {
    // ignore storage errors
  }
}

const state = reactive({
  ready: false,
  authenticated: false,
  csrfToken: '',
  csrf_token: '',
  user: null,
  company: null,
  companies: [],
  permissions: {},
  menuOrder: loadCachedMenuOrder(),
})

function applySession(payload) {
  state.authenticated = Boolean(payload.authenticated)
  state.csrfToken = payload.csrf_token || ''
  state.csrf_token = payload.csrf_token || ''
  state.user = payload.user || null
  state.company = payload.company || null
  state.companies = payload.companies || []
  state.permissions = payload.permissions || {}
  if ('menu_order' in payload) {
    state.menuOrder = payload.menu_order || null
    saveCachedMenuOrder(state.menuOrder)
  }
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
  async logPageView(routeName) {
    return request('page-view', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': state.csrfToken },
      body: JSON.stringify({ route_name: routeName }),
    })
  },
  async saveMenuOrder(menuOrder) {
    state.menuOrder = menuOrder
    saveCachedMenuOrder(menuOrder)
    const payload = await request('save-menu-order', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': state.csrfToken },
      body: JSON.stringify(menuOrder),
    })
    if (payload.menu_order) {
      state.menuOrder = payload.menu_order
      saveCachedMenuOrder(payload.menu_order)
    }
    return payload
  },
  async resetMenuOrder() {
    state.menuOrder = null
    saveCachedMenuOrder(null)
    const payload = await request('reset-menu-order', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': state.csrfToken },
      body: '{}',
    })
    return payload
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
    state.menuOrder = null
    saveCachedMenuOrder(null)
    clearDashboardCache()
    state.csrfToken = ''
    state.ready = false
    await this.bootstrap(true)
  },
}
