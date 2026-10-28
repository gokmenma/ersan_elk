import { reactive } from 'vue'
import { fetchBadges } from './badges.js'
import { auth } from './auth.js'

export const notificationsState = reactive({
  unreadCount: 0,
  items: [],
  loading: false,
  drawerOpen: false,
  pushSupported: false,
  pushSubscribed: false,
  permission: typeof Notification !== 'undefined' ? Notification.permission : 'default',
})

function urlBase64ToUint8Array(base64String) {
  const padding = '='.repeat((4 - (base64String.length % 4)) % 4)
  const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/')
  const rawData = window.atob(base64)
  const outputArray = new Uint8Array(rawData.length)
  for (let i = 0; i < rawData.length; ++i) {
    outputArray[i] = rawData.charCodeAt(i)
  }
  return outputArray
}

export async function fetchUnreadCount() {
  try {
    const res = await fetch('/api/notifications.php?action=unread-count', {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
    })
    if (!res.ok) return
    const data = await res.json()
    if (data.success && typeof data.count === 'number') {
      notificationsState.unreadCount = data.count
    }
  } catch (e) {
    console.warn('Unread count fetch failed:', e)
  }
}

export async function fetchNotifications() {
  notificationsState.loading = true
  try {
    const res = await fetch('/api/notifications.php?action=list&limit=30', {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
    })
    if (!res.ok) throw new Error('Bildirimler yüklenemedi.')
    const data = await res.json()
    if (data.success) {
      notificationsState.items = Array.isArray(data.notifications) ? data.notifications : []
      if (typeof data.unread_count === 'number') {
        notificationsState.unreadCount = data.unread_count
      }
    }
  } catch (e) {
    console.error('Bildirimler alınırken hata:', e)
  } finally {
    notificationsState.loading = false
  }
}

export async function markAsRead(id) {
  try {
    const item = notificationsState.items.find(n => n.id === id)
    if (item && !item.is_read) {
      item.is_read = true
      notificationsState.unreadCount = Math.max(0, notificationsState.unreadCount - 1)
    }
    const res = await fetch('/api/notifications.php?action=mark-read', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ id }),
    })
    const data = await res.json()
    if (data.success && typeof data.unread_count === 'number') {
      notificationsState.unreadCount = data.unread_count
    }
  } catch (e) {
    console.error('Okundu işaretleme hatası:', e)
  }
}

export async function markAllAsRead() {
  try {
    notificationsState.items.forEach(n => { n.is_read = true })
    notificationsState.unreadCount = 0

    const res = await fetch('/api/notifications.php?action=mark-all-read', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    })
    const data = await res.json()
    if (data.success) {
      notificationsState.unreadCount = 0
    }
  } catch (e) {
    console.error('Tümünü okundu işaretleme hatası:', e)
  }
}

export async function fetchPreferences() {
  const res = await fetch('/api/notifications.php?action=preferences', {
    credentials: 'same-origin',
    headers: { Accept: 'application/json' },
  })
  const data = await res.json()
  if (!res.ok || !data.success) throw new Error(data.message || 'Bildirim tercihleri alınamadı.')
  return data.preferences
}

export async function setPreference(type, enabled) {
  const res = await fetch('/api/notifications.php?action=set-preference', {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'X-CSRF-Token': auth.state.csrfToken,
    },
    body: JSON.stringify({ type, enabled }),
  })
  const data = await res.json()
  if (!res.ok || !data.success) throw new Error(data.message || 'Tercih kaydedilemedi.')
  if (typeof data.unread_count === 'number') notificationsState.unreadCount = data.unread_count
  fetchBadges()
  return true
}

export function openNotificationsDrawer() {
  notificationsState.drawerOpen = true
  fetchNotifications()
}

export function closeNotificationsDrawer() {
  notificationsState.drawerOpen = false
}

export function toggleNotificationsDrawer() {
  if (notificationsState.drawerOpen) {
    closeNotificationsDrawer()
  } else {
    openNotificationsDrawer()
  }
}

// Push Bildirim Kontrolü
export async function checkPushStatus() {
  const isSupported =
    typeof window !== 'undefined' &&
    'serviceWorker' in navigator &&
    'PushManager' in window &&
    'Notification' in window

  notificationsState.pushSupported = isSupported

  if (!isSupported) {
    notificationsState.pushSubscribed = false
    return false
  }

  notificationsState.permission = Notification.permission

  try {
    const reg = await navigator.serviceWorker.ready
    const sub = await reg.pushManager.getSubscription()
    notificationsState.pushSubscribed = !!sub

    if (sub && Notification.permission === 'granted') {
      // Sunucu tarafında da kayıtlı olduğunu doğrula/güncelle
      await saveSubscriptionToBackend(sub)
    }
    return notificationsState.pushSubscribed
  } catch (e) {
    console.warn('Push status check failed:', e)
    return false
  }
}

// Kullanıcıyı Web Push'a abone yap
export async function subscribePush() {
  if (!notificationsState.pushSupported) {
    throw new Error('Tarayıcınız veya cihazınız Web Push bildirimlerini desteklemiyor.')
  }

  // İzin iste
  const permission = await Notification.requestPermission()
  notificationsState.permission = permission

  if (permission !== 'granted') {
    throw new Error('Bildirim izni verilmedi. Lütfen tarayıcı ayarlarından bildirimlere izin verin.')
  }

  // VAPID anahtarını sunucudan al
  const keyRes = await fetch('/api/notifications.php?action=vapid-key', {
    credentials: 'same-origin',
    headers: { Accept: 'application/json' },
  })
  const keyData = await keyRes.json()
  if (!keyData.success || !keyData.publicKey) {
    throw new Error('VAPID bildirim anahtarı alınamadı.')
  }

  const reg = await navigator.serviceWorker.ready
  const appServerKey = urlBase64ToUint8Array(keyData.publicKey)

  const subscription = await reg.pushManager.subscribe({
    userVisibleOnly: true,
    applicationServerKey: appServerKey,
  })

  await saveSubscriptionToBackend(subscription)
  notificationsState.pushSubscribed = true
  return true
}

// Aboneliği iptal et
export async function unsubscribePush() {
  try {
    const reg = await navigator.serviceWorker.ready
    const sub = await reg.pushManager.getSubscription()
    if (sub) {
      const endpoint = sub.endpoint
      await sub.unsubscribe()
      await fetch('/api/notifications.php?action=unsubscribe', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ endpoint }),
      })
    }
    notificationsState.pushSubscribed = false
    return true
  } catch (e) {
    console.error('Abonelik iptal hatası:', e)
    throw e
  }
}

async function saveSubscriptionToBackend(sub) {
  try {
    const subJson = sub.toJSON()
    await fetch('/api/notifications.php?action=subscribe', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({
        endpoint: sub.endpoint,
        keys: subJson.keys,
        contentEncoding: 'aes128gcm',
      }),
    })
  } catch (e) {
    console.warn('Subscription backend sync failed:', e)
  }
}

// Otomatik periyodik sayım sorgusu (Polling)
let pollTimer = null
export function initNotificationsWatcher() {
  fetchUnreadCount()
  fetchBadges()
  checkPushStatus()

  if (pollTimer) clearInterval(pollTimer)
  pollTimer = setInterval(() => {
    if (typeof document !== 'undefined' && !document.hidden) {
      fetchUnreadCount()
      fetchBadges()
    }
  }, 45000)
}
