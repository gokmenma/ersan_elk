import { reactive, readonly } from 'vue'

const REDUCE_MOTION_STORAGE_KEY = 'ersan_mobile_reduce_motion_v1'
const THEME_STORAGE_KEY = 'ersan_mobile_theme_v1'
const PALETTE_STORAGE_KEY = 'ersan_mobile_palette_v1'
const THEMES = ['light', 'dark', 'system']
const PALETTES = ['varsayilan', 'ersan', 'zumrut', 'kraliyet', 'rose', 'okyanus', 'kehribar', 'siber', 'iskandinav', 'nane', 'mocha']

function loadReduceMotion() {
  try {
    return localStorage.getItem(REDUCE_MOTION_STORAGE_KEY) === '1'
  } catch {
    return false
  }
}

function loadTheme() {
  try {
    const stored = localStorage.getItem(THEME_STORAGE_KEY)
    return THEMES.includes(stored) ? stored : 'light'
  } catch {
    return 'light'
  }
}

function loadPalette() {
  try {
    const stored = localStorage.getItem(PALETTE_STORAGE_KEY)
    return PALETTES.includes(stored) ? stored : 'varsayilan'
  } catch {
    return 'varsayilan'
  }
}

const systemDarkQuery = typeof window !== 'undefined' && window.matchMedia
  ? window.matchMedia('(prefers-color-scheme: dark)')
  : null

const state = reactive({
  reduceMotion: loadReduceMotion(),
  theme: loadTheme(),
  palette: loadPalette(),
  resolvedTheme: 'light',
})

function applyReduceMotion() {
  document.documentElement.classList.toggle('ds-reduce-motion', state.reduceMotion)
}

function syncThemeColorMeta() {
  const canvas = getComputedStyle(document.documentElement).getPropertyValue('--ds-canvas').trim()
  if (canvas) document.querySelector('meta[name="theme-color"]')?.setAttribute('content', canvas)
}

function applyPalette() {
  if (state.palette === 'varsayilan') delete document.documentElement.dataset.palette
  else document.documentElement.dataset.palette = state.palette
}

function applyTheme() {
  state.resolvedTheme = state.theme === 'system'
    ? (systemDarkQuery?.matches ? 'dark' : 'light')
    : state.theme
  applyPalette()
  document.documentElement.dataset.theme = state.resolvedTheme
  syncThemeColorMeta()
}

systemDarkQuery?.addEventListener?.('change', () => {
  if (state.theme === 'system') applyTheme()
})

export const preferences = {
  state: readonly(state),
  apply() {
    applyReduceMotion()
    applyTheme()
  },
  syncThemeColorMeta,
  setTheme(theme) {
    if (!THEMES.includes(theme)) return
    state.theme = theme
    try {
      localStorage.setItem(THEME_STORAGE_KEY, theme)
    } catch {
      // ignore storage errors
    }
    applyTheme()
  },
  setPalette(palette) {
    if (!PALETTES.includes(palette)) return
    state.palette = palette
    try {
      localStorage.setItem(PALETTE_STORAGE_KEY, palette)
    } catch {
      // ignore storage errors
    }
    applyTheme()
  },
  setReduceMotion(enabled) {
    state.reduceMotion = Boolean(enabled)
    try {
      localStorage.setItem(REDUCE_MOTION_STORAGE_KEY, state.reduceMotion ? '1' : '0')
    } catch {
      // ignore storage errors
    }
    applyReduceMotion()
  },
  async refreshAppCache() {
    if ('caches' in window) {
      const keys = await caches.keys()
      await Promise.all(keys.map((key) => caches.delete(key)))
    }
    if ('serviceWorker' in navigator) {
      const registration = await navigator.serviceWorker.getRegistration()
      await registration?.update().catch(() => {})
    }
    window.location.reload()
  },
}
