<script setup>
import { computed, onMounted, ref } from 'vue'
import {
  Bell,
  BellOff,
  BellRing,
  Check,
  ChevronRight,
  CircleHelp,
  Inbox,
  Info,
  LogOut,
  Moon,
  Palette,
  ShieldCheck,
  Smartphone,
  X,
} from '@lucide/vue'
import { useRouter } from 'vue-router'
import TopBar from '../components/TopBar.vue'
import { auth } from '../services/auth.js'
import {
  notificationsState,
  checkPushStatus,
  subscribePush,
  unsubscribePush,
  openNotificationsDrawer,
} from '../services/notifications.js'

const router = useRouter()
const showNotifModal = ref(false)
const processingPush = ref(false)
const pushFeedback = ref({ type: '', text: '' })

onMounted(() => {
  checkPushStatus()
})

async function logout() {
  await auth.logout()
  await router.replace('/giris')
}

const notifBadgeText = computed(() => {
  if (!notificationsState.pushSupported) return 'Desteklenmiyor'
  if (notificationsState.permission === 'denied') return 'Engellendi'
  if (notificationsState.pushSubscribed) return 'Açık'
  return 'Kapalı'
})

const notifBadgeClass = computed(() => {
  if (!notificationsState.pushSupported) return 'clay-badge--amber'
  if (notificationsState.permission === 'denied') return 'clay-badge--coral'
  if (notificationsState.pushSubscribed) return 'clay-badge--emerald'
  return ''
})

async function handleTogglePush() {
  processingPush.value = true
  pushFeedback.value = { type: '', text: '' }
  try {
    if (notificationsState.pushSubscribed) {
      await unsubscribePush()
      pushFeedback.value = { type: 'success', text: 'Cihaz bildirimleri kapatıldı.' }
    } else {
      await subscribePush()
      pushFeedback.value = { type: 'success', text: 'Cihaz bildirimleri başarıyla açıldı.' }
    }
  } catch (err) {
    pushFeedback.value = { type: 'error', text: err.message || 'İşlem tamamlanamadı.' }
  } finally {
    processingPush.value = false
  }
}

function handleRowClick(row) {
  if (row.id === 'notifications') {
    showNotifModal.value = true
    pushFeedback.value = { type: '', text: '' }
  }
}

const rows = [
  { id: 'notifications', label: 'Bildirim tercihleri', icon: Bell },
  { id: 'theme', label: 'Görünüm ve tema', icon: Palette },
  { id: 'security', label: 'Güvenlik', icon: ShieldCheck },
  { id: 'devices', label: 'Bağlı cihazlar', icon: Smartphone },
  { id: 'help', label: 'Yardım merkezi', icon: CircleHelp },
]
</script>

<template>
  <section class="ds-screen">
    <!-- 1. Standart TopBar -->
    <TopBar eyebrow="HESABIM" title="Profil" subtitle="Hesap ve uygulama tercihleri" :back="true" />

    <!-- 2. Kullanıcı Profil Bento Kartı -->
    <div class="bento-card bento-card--white profile-bento-card">
      <div class="profile-avatar-circle">
        <span>{{ auth.state.user?.initials || 'EE' }}</span>
      </div>
      <div class="profile-info">
        <span class="clay-badge clay-badge--emerald mb-1">Aktif Oturum</span>
        <h2>{{ auth.state.user?.name }}</h2>
        <p>{{ auth.state.company?.name }}</p>
      </div>
    </div>

    <!-- 3. Ayarlar Bento Kartı -->
    <div class="bento-card bento-card--white settings-bento-card">
      <button
        v-for="row in rows"
        :key="row.id"
        type="button"
        class="settings-row"
        @click="handleRowClick(row)"
      >
        <span class="settings-icon">
          <component :is="row.icon" :size="18" />
        </span>
        <strong>{{ row.label }}</strong>
        <span
          v-if="row.id === 'notifications'"
          class="clay-badge me-2"
          :class="notifBadgeClass"
        >
          {{ notifBadgeText }}
        </span>
        <ChevronRight :size="16" class="settings-arrow" />
      </button>
    </div>

    <!-- 4. Güvenli Çıkış Butonu -->
    <button type="button" class="clay-btn clay-btn--danger w-100 logout-btn" @click="logout">
      <LogOut :size="17" />
      <span>Güvenli Çıkış Yap</span>
    </button>

    <!-- Bildirim Tercihleri Modalı (Sheet) -->
    <Transition name="nav-sheet">
      <div
        v-if="showNotifModal"
        class="nav-sheet-layer"
        @click.self="showNotifModal = false"
      >
        <section
          class="nav-sheet notif-modal"
          role="dialog"
          aria-modal="true"
          aria-label="Bildirim Tercihleri"
        >
          <header class="notif-modal__header">
            <div>
              <span class="notif-modal__eyebrow">TERCİHLER</span>
              <h2>Bildirim Ayarları</h2>
            </div>
            <button
              type="button"
              class="notif-modal__close-btn"
              aria-label="Kapat"
              @click="showNotifModal = false"
            >
              <X :size="19" />
            </button>
          </header>

          <div class="notif-modal__content">
            <!-- Geri bildirim mesajı -->
            <div
              v-if="pushFeedback.text"
              class="feedback-box"
              :class="`feedback-box--${pushFeedback.type}`"
            >
              {{ pushFeedback.text }}
            </div>

            <!-- Kart 1: Cihaz Push Bildirimleri -->
            <div class="bento-card bento-card--white notif-setting-card">
              <div class="notif-setting-card__icon" :class="{ 'is-active': notificationsState.pushSubscribed }">
                <BellRing v-if="notificationsState.pushSubscribed" :size="20" />
                <BellOff v-else :size="20" />
              </div>
              <div class="notif-setting-card__body">
                <div class="notif-setting-card__title-row">
                  <strong>Anlık Cihaz Bildirimleri</strong>
                  <span class="clay-badge" :class="notifBadgeClass">{{ notifBadgeText }}</span>
                </div>
                <p v-if="notificationsState.pushSubscribed">
                  Talep, onay ve kritik operasyon duyuruları kilit ekranınıza anında iletilir.
                </p>
                <p v-else-if="notificationsState.permission === 'denied'" class="text-danger">
                  Tarayıcınızda bildirim izni engellenmiş. Tarayıcı adres çubuğundaki kilit simgesine dokunup bildirimlere izin verin.
                </p>
                <p v-else-if="!notificationsState.pushSupported">
                  Cihazınız veya tarayıcınız Web Push bildirimlerini desteklemiyor.
                </p>
                <p v-else>
                  Uygulama kapalıyken bile talep ve onaylardan haberdar olmak için bildirimleri açın.
                </p>
              </div>

              <div v-if="notificationsState.pushSupported && notificationsState.permission !== 'denied'" class="notif-setting-card__action">
                <button
                  type="button"
                  class="clay-btn"
                  :class="notificationsState.pushSubscribed ? 'clay-btn--subtle-danger' : 'clay-btn--primary'"
                  :disabled="processingPush"
                  @click="handleTogglePush"
                >
                  <span v-if="processingPush">İşleniyor…</span>
                  <span v-else-if="notificationsState.pushSubscribed">Bildirimleri Kapat</span>
                  <span v-else>Bildirimleri Aç</span>
                </button>
              </div>
            </div>

            <!-- Kart 2: Uygulama İçi Bildirimler -->
            <div class="bento-card bento-card--white notif-setting-card">
              <div class="notif-setting-card__icon">
                <Inbox :size="20" />
              </div>
              <div class="notif-setting-card__body">
                <div class="notif-setting-card__title-row">
                  <strong>Bildirim Geçmişi</strong>
                  <span
                    v-if="notificationsState.unreadCount > 0"
                    class="clay-badge clay-badge--emerald"
                  >
                    {{ notificationsState.unreadCount }} Okunmamış
                  </span>
                </div>
                <p>Tüm geçmiş sistem bildirimlerini görüntüleyin ve yönetin.</p>
              </div>
              <div class="notif-setting-card__action">
                <button
                  type="button"
                  class="clay-btn clay-btn--subtle w-100"
                  @click="showNotifModal = false; openNotificationsDrawer()"
                >
                  <span>Bildirimleri Görüntüle</span>
                  <ChevronRight :size="15" />
                </button>
              </div>
            </div>
          </div>
        </section>
      </div>
    </Transition>
  </section>
</template>

<style scoped>
.profile-bento-card {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 18px 20px;
}

.profile-avatar-circle {
  display: grid;
  width: 58px;
  height: 58px;
  place-items: center;
  border-radius: 20px;
  background: var(--ds-primary);
  color: var(--ds-surface);
  font: 800 18px var(--ds-font-mono);
  box-shadow: var(--ds-shadow-pill);
  flex-shrink: 0;
}

.profile-info {
  flex: 1;
  min-width: 0;
}

.profile-info h2 {
  margin: 2px 0 0;
  font-size: 16px;
  font-weight: 800;
  letter-spacing: -0.02em;
  color: var(--ds-ink);
}

.profile-info p {
  margin: 3px 0 0;
  font-size: 11px;
  color: var(--ds-muted);
}

.settings-bento-card {
  padding: 6px 16px;
}

.settings-row {
  display: flex;
  width: 100%;
  align-items: center;
  gap: 12px;
  padding: 13px 0;
  border: 0;
  border-bottom: 1px solid var(--ds-border-subtle);
  background: transparent;
  cursor: pointer;
  text-align: left;
}

.settings-row:last-child {
  border-bottom: 0;
}

.settings-icon {
  display: grid;
  width: 36px;
  height: 36px;
  place-items: center;
  border-radius: 12px;
  background: var(--ds-surface-subtle);
  color: var(--ds-primary);
}

.settings-row strong {
  flex: 1;
  font-size: 13px;
  font-weight: 700;
  color: var(--ds-ink);
}

.settings-arrow {
  color: var(--ds-muted);
}

.logout-btn {
  height: 48px;
  border-radius: var(--ds-radius-tile);
  margin-top: 14px;
}

/* Modal Stilleri */
.notif-modal {
  max-height: calc(85svh - 40px - var(--ds-safe-bottom));
}

.notif-modal__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
}

.notif-modal__eyebrow {
  display: block;
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.12em;
  color: var(--ds-blue-ink);
  text-transform: uppercase;
  margin-bottom: 2px;
}

.notif-modal__header h2 {
  margin: 0;
  font-size: 20px;
  font-weight: 800;
  letter-spacing: -0.03em;
  color: var(--ds-ink);
}

.notif-modal__close-btn {
  display: grid;
  width: 36px;
  height: 36px;
  place-items: center;
  border-radius: 50%;
  border: 1px solid var(--ds-border-subtle);
  background: var(--ds-surface-subtle);
  color: var(--ds-ink);
  cursor: pointer;
}

.notif-modal__content {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.notif-setting-card {
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.notif-setting-card__icon {
  display: grid;
  width: 42px;
  height: 42px;
  place-items: center;
  border-radius: 14px;
  background: var(--ds-surface-subtle);
  color: var(--ds-muted);
}

.notif-setting-card__icon.is-active {
  background: #3b82f6;
  color: #ffffff;
}

.notif-setting-card__body {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.notif-setting-card__title-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.notif-setting-card__title-row strong {
  font-size: 14px;
  font-weight: 800;
  color: var(--ds-ink);
}

.notif-setting-card__body p {
  margin: 0;
  font-size: 12px;
  color: var(--ds-muted);
  line-height: 1.35;
}

.notif-setting-card__action {
  margin-top: 4px;
}

.notif-setting-card__action .clay-btn {
  width: 100%;
  height: 40px;
  font-size: 13px;
  font-weight: 800;
  border-radius: var(--ds-radius-tile);
}

.clay-btn--subtle-danger {
  background: var(--ds-danger-soft, #fee2e2);
  color: var(--ds-danger-ink, #ef4444);
  border: 1px solid rgba(239, 68, 68, 0.2);
}

.feedback-box {
  padding: 10px 14px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 700;
}

.feedback-box--success {
  background: #ecfdf5;
  color: #059669;
  border: 1px solid #a7f3d0;
}

.feedback-box--error {
  background: #fef2f2;
  color: #dc2626;
  border: 1px solid #fecaca;
}
</style>
