<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import {
  Bell,
  BellRing,
  Check,
  CheckCheck,
  ChevronRight,
  Clock,
  ExternalLink,
  Inbox,
  Sparkles,
  X,
} from '@lucide/vue'
import {
  notificationsState,
  closeNotificationsDrawer,
  markAsRead,
  markAllAsRead,
  subscribePush,
} from '../services/notifications.js'

const router = useRouter()
const sheet = ref(null)
let previousOverflow = ''
let returnFocus = null

watch(() => notificationsState.drawerOpen, async (open) => {
  if (open) {
    returnFocus = document.activeElement
    previousOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    await nextTick()
    sheet.value?.querySelector('button')?.focus()
  } else {
    document.body.style.overflow = previousOverflow
    if (returnFocus?.isConnected) returnFocus.focus()
  }
})

onBeforeUnmount(() => {
  if (notificationsState.drawerOpen) {
    document.body.style.overflow = previousOverflow
  }
})

function handleSheetKeydown(event) {
  if (event.key === 'Escape') {
    closeNotificationsDrawer()
  }
}

function formatDate(isoStr) {
  if (!isoStr) return ''
  try {
    const d = new Date(isoStr.replace(' ', 'T'))
    if (isNaN(d.getTime())) return isoStr
    const now = new Date()
    const diffMs = now - d
    const diffMins = Math.floor(diffMs / 60000)
    const diffHours = Math.floor(diffMs / 3600000)
    const diffDays = Math.floor(diffMs / 86400000)

    if (diffMins < 1) return 'Az önce'
    if (diffMins < 60) return `${diffMins} dk önce`
    if (diffHours < 24) return `${diffHours} sa önce`
    if (diffDays === 1) return 'Dün'
    if (diffDays < 7) return `${diffDays} gün önce`

    return d.toLocaleDateString('tr-TR', { day: '2-digit', month: '2-digit', year: 'numeric' })
  } catch {
    return isoStr
  }
}

async function handleNotificationClick(n) {
  if (!n.is_read) {
    await markAsRead(n.id)
  }
  closeNotificationsDrawer()

  const link = n.link || ''
  if (!link) return

  // SPA hash veya route yönlendirmesi
  if (link.includes('talepler')) {
    router.push('/talepler')
  } else if (link.includes('km-onay')) {
    router.push('/km-onaylari')
  } else if (link.includes('arac-takip') || link.includes('arac')) {
    router.push('/arac-takip')
  } else if (link.includes('personel')) {
    router.push('/personel')
  } else if (link.includes('nobet')) {
    router.push('/nobet-onay')
  } else if (link.includes('kacak')) {
    router.push('/kacak-kontrol')
  } else if (link.includes('ihbar')) {
    router.push('/ihbar')
  } else if (link.includes('puantaj')) {
    router.push('/puantaj')
  } else if (link.includes('fatura') || link.includes('efatura')) {
    router.push('/fatura')
  } else if (link.includes('kasa') || link.includes('gelir-gider')) {
    router.push('/kasa')
  } else if (link.includes('bordro') || link.includes('maas')) {
    router.push('/bordro')
  } else if (link.startsWith('/')) {
    router.push(link)
  } else if (link.startsWith('http')) {
    window.location.href = link
  }
}

const enablingPush = ref(false)
const pushError = ref('')

async function handleEnablePush() {
  enablingPush.value = true
  pushError.value = ''
  try {
    await subscribePush()
  } catch (e) {
    pushError.value = e.message || 'Bildirim izni alınamadı.'
  } finally {
    enablingPush.value = false
  }
}
</script>

<template>
  <Transition name="nav-sheet">
    <div
      v-if="notificationsState.drawerOpen"
      class="nav-sheet-layer"
      @keydown="handleSheetKeydown"
      @click.self="closeNotificationsDrawer"
    >
      <section
        id="notifications-sheet"
        ref="sheet"
        class="nav-sheet notifications-sheet"
        role="dialog"
        aria-modal="true"
        aria-label="Bildirimler"
      >
        <header class="notifications-sheet__header">
          <div>
            <span class="notifications-eyebrow">BİLDİRİM MERKEZİ</span>
            <div class="notifications-title-row">
              <h2>Bildirimler</h2>
              <span
                v-if="notificationsState.unreadCount > 0"
                class="clay-badge clay-badge--emerald"
              >
                {{ notificationsState.unreadCount }} Yeni
              </span>
            </div>
          </div>
          <div class="notifications-header-actions">
            <button
              v-if="notificationsState.unreadCount > 0"
              type="button"
              class="notifications-mark-all-btn"
              title="Tümünü Okundu İşaretle"
              aria-label="Tümünü Okundu İşaretle"
              @click="markAllAsRead"
            >
              <CheckCheck :size="16" />
              <span class="notifications-mark-all-label--full">Tümü Okundu</span>
              <span class="notifications-mark-all-label--compact">Okundu</span>
            </button>
            <button
              type="button"
              class="notifications-close-btn"
              aria-label="Kapat"
              @click="closeNotificationsDrawer"
            >
              <X :size="19" />
            </button>
          </div>
        </header>

        <!-- Push Bildirim İzni Uyarısı (Açık değilse göster) -->
        <div
          v-if="notificationsState.pushSupported && !notificationsState.pushSubscribed && notificationsState.permission !== 'denied'"
          class="push-banner bento-card bento-card--white"
        >
          <div class="push-banner__icon">
            <BellRing :size="20" />
          </div>
          <div class="push-banner__body">
            <strong>Anlık Bildirimleri Açın</strong>
            <p>Onay ve talep gelişmelerinden anında haberdar olun.</p>
          </div>
          <button
            type="button"
            class="clay-btn clay-btn--primary push-banner__btn"
            :disabled="enablingPush"
            @click="handleEnablePush"
          >
            {{ enablingPush ? 'Açılıyor...' : 'Aç' }}
          </button>
        </div>
        <p v-if="pushError" class="push-error-text">{{ pushError }}</p>

        <!-- Bildirim Listesi -->
        <div class="notifications-sheet__content">
          <div v-if="notificationsState.loading" class="notifications-loading">
            <div class="clay-spinner" />
            <span>Bildirimler yükleniyor…</span>
          </div>

          <div
            v-else-if="notificationsState.items.length === 0"
            class="notifications-empty"
          >
            <div class="notifications-empty__icon">
              <Sparkles :size="28" />
            </div>
            <strong>Henüz bildirim yok</strong>
            <p>Yeni bir talep, onay veya güncelleme olduğunda burada listelenecektir.</p>
          </div>

          <div v-else class="notifications-list">
            <article
              v-for="n in notificationsState.items"
              :key="n.id"
              class="notification-item bento-card bento-card--white"
              :class="{ 'is-unread': !n.is_read }"
              @click="handleNotificationClick(n)"
            >
              <div
                class="notification-item__icon-wrap"
                :class="`notification-item__icon-wrap--${n.color || 'primary'}`"
              >
                <Bell :size="18" />
              </div>

              <div class="notification-item__content">
                <div class="notification-item__meta">
                  <span class="notification-item__time">
                    <Clock :size="11" />
                    {{ formatDate(n.created_at) }}
                  </span>
                  <span v-if="!n.is_read" class="notification-item__unread-dot" />
                </div>
                <strong class="notification-item__title">{{ n.title }}</strong>
                <p class="notification-item__message">{{ n.message }}</p>
              </div>

              <div class="notification-item__arrow">
                <ChevronRight :size="16" />
              </div>
            </article>
          </div>
        </div>
      </section>
    </div>
  </Transition>
</template>

<style scoped>
.notifications-sheet {
  max-height: calc(88svh - 40px - var(--ds-safe-bottom));
  display: flex;
  flex-direction: column;
}

.notifications-sheet__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 14px;
  flex-shrink: 0;
}

.notifications-sheet__header > :first-child {
  min-width: 0;
}

.notifications-eyebrow {
  display: block;
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.12em;
  color: var(--ds-blue-ink);
  text-transform: uppercase;
  margin-bottom: 2px;
}

.notifications-title-row {
  display: flex;
  align-items: center;
  gap: 8px;
}

.notifications-title-row h2 {
  margin: 0;
  font-size: 20px;
  font-weight: 800;
  letter-spacing: -0.03em;
  color: var(--ds-ink);
}

.notifications-header-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  flex: 0 0 auto;
}

.notifications-mark-all-btn {
  display: flex;
  align-items: center;
  gap: 5px;
  height: 36px;
  padding: 0 11px;
  border-radius: var(--ds-radius-pill);
  border: 1px solid var(--ds-border-subtle);
  background: var(--ds-surface-subtle);
  color: var(--ds-muted);
  font: 700 11.5px var(--ds-font-main);
  cursor: pointer;
  transition: all 0.15s ease;
  flex: 0 0 auto;
  white-space: nowrap;
}

.notifications-mark-all-label--compact {
  display: none;
}

.notifications-mark-all-btn:active {
  background: var(--ds-surface);
  color: var(--ds-ink);
}

.notifications-close-btn {
  display: grid;
  width: 36px;
  height: 36px;
  place-items: center;
  border-radius: 50%;
  border: 1px solid var(--ds-border-subtle);
  background: var(--ds-surface-subtle);
  color: var(--ds-ink);
  cursor: pointer;
  flex: 0 0 36px;
}

@media (max-width: 430px) {
  .notifications-sheet__header {
    gap: 6px;
  }

  .notifications-header-actions {
    gap: 6px;
  }

  .notifications-mark-all-btn {
    height: 36px;
    padding: 0 9px;
    gap: 4px;
    font-size: 11px;
  }

  .notifications-mark-all-label--full {
    display: none;
  }

  .notifications-mark-all-label--compact {
    display: inline;
  }
}

@media (max-width: 350px) {
  .notifications-mark-all-btn {
    width: 36px;
    padding: 0;
    justify-content: center;
    border-radius: 50%;
  }

  .notifications-mark-all-label--compact {
    display: none;
  }
}

.push-banner {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  margin-bottom: 12px;
  border-radius: var(--ds-radius-card);
  background: linear-gradient(135deg, var(--dsx-bg-eef5ff), var(--dsx-bg-f8fafc));
  border: 1px solid rgba(59, 130, 246, 0.25);
  flex-shrink: 0;
}

.push-banner__icon {
  display: grid;
  width: 40px;
  height: 40px;
  place-items: center;
  border-radius: 12px;
  background: #3b82f6;
  color: #ffffff;
  flex-shrink: 0;
}

.push-banner__body {
  flex: 1;
  min-width: 0;
}

.push-banner__body strong {
  display: block;
  font-size: 13px;
  font-weight: 800;
  color: var(--ds-ink);
}

.push-banner__body p {
  margin: 2px 0 0;
  font-size: 11px;
  color: var(--ds-muted);
  line-height: 1.25;
}

.push-banner__btn {
  height: 34px;
  padding: 0 14px;
  font-size: 12px;
  font-weight: 800;
  flex-shrink: 0;
}

.push-error-text {
  font-size: 11px;
  color: var(--ds-danger-ink);
  margin: -6px 0 10px 4px;
}

.notifications-sheet__content {
  overflow-y: auto;
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding-right: 2px;
}

.notifications-loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding: 40px 16px;
  color: var(--ds-muted);
  font-size: 13px;
}

.notifications-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  padding: 48px 24px;
  color: var(--ds-muted);
}

.notifications-empty__icon {
  display: grid;
  width: 54px;
  height: 54px;
  place-items: center;
  border-radius: 18px;
  background: var(--ds-surface-subtle);
  color: var(--ds-blue-ink);
  margin-bottom: 12px;
}

.notifications-empty strong {
  font-size: 15px;
  font-weight: 800;
  color: var(--ds-ink);
}

.notifications-empty p {
  margin: 4px 0 0;
  font-size: 12px;
  line-height: 1.35;
  max-width: 260px;
}

.notifications-list {
  display: flex;
  flex-direction: column;
  gap: 9px;
  padding-bottom: 10px;
}

.notification-item {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 13px 14px;
  border-radius: var(--ds-radius-card);
  cursor: pointer;
  transition: transform 0.12s ease, background 0.12s ease, border-color 0.12s ease;
  position: relative;
}

.notification-item:active {
  transform: scale(0.985);
}

.notification-item.is-unread {
  background: var(--dsx-bg-f7faff);
  border-color: rgba(59, 130, 246, 0.35);
  box-shadow: 0 4px 14px rgba(59, 130, 246, 0.08);
}

.notification-item__icon-wrap {
  display: grid;
  width: 36px;
  height: 36px;
  place-items: center;
  border-radius: 12px;
  flex-shrink: 0;
  background: var(--ds-surface-subtle);
  color: var(--ds-ink);
  margin-top: 1px;
}

.notification-item__icon-wrap--primary {
  background: var(--ds-blue-soft);
  color: var(--ds-blue-ink);
}

.notification-item__icon-wrap--success {
  background: var(--ds-emerald-soft);
  color: var(--ds-emerald-ink);
}

.notification-item__icon-wrap--warning {
  background: var(--ds-amber-soft);
  color: var(--ds-amber-ink);
}

.notification-item__icon-wrap--danger {
  background: var(--ds-danger-soft);
  color: var(--ds-danger-ink);
}

.notification-item__content {
  flex: 1;
  min-width: 0;
}

.notification-item__meta {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 2px;
}

.notification-item__time {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 10.5px;
  font-weight: 700;
  color: var(--ds-muted);
}

.notification-item__unread-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.25);
}

.notification-item__title {
  display: block;
  font-size: 13px;
  font-weight: 800;
  color: var(--ds-ink);
  line-height: 1.25;
}

.notification-item__message {
  margin: 3px 0 0;
  font-size: 11.5px;
  color: var(--ds-muted);
  line-height: 1.35;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.notification-item__arrow {
  color: var(--ds-muted-light);
  margin-top: 9px;
  flex-shrink: 0;
}
</style>
