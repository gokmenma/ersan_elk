import { reactive } from 'vue'
import { auth } from './auth.js'

export const badgesState = reactive({
  counts: {},
})

export const BADGE_MODULES = {
  invoices: ['efatura_gelen', 'efatura_giden'],
  live_support: ['live_support'],
  km_approvals: ['km_approvals'],
  illegal: ['kacak'],
  notices: ['ihbar'],
  documents: ['evrak', 'uets_unread'],
  requests: ['requests'],
}

export function moduleBadge(moduleKey) {
  const keys = BADGE_MODULES[moduleKey] || []
  return keys.reduce((sum, key) => sum + (Number(badgesState.counts[key]) || 0), 0)
}

export async function fetchBadges() {
  try {
    const res = await fetch('/api/badges.php?action=list', {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
    })
    if (!res.ok) return
    const data = await res.json()
    if (data.success && data.badges && typeof data.badges === 'object') {
      badgesState.counts = data.badges
    }
  } catch (e) {
    console.warn('Rozet sayıları alınamadı:', e)
  }
}

export async function markSeen(key) {
  if (!(Number(badgesState.counts[key]) > 0)) return
  badgesState.counts = { ...badgesState.counts, [key]: 0 }
  try {
    await fetch('/api/badges.php?action=seen', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-Token': auth.state.csrfToken,
      },
      body: JSON.stringify({ key }),
    })
  } catch (e) {
    console.warn('Rozet görüldü işaretlenemedi:', e)
  }
}
