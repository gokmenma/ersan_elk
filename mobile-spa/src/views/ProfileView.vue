<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import {
  Bell,
  BellOff,
  BellRing,
  ChevronDown,
  ChevronRight,
  CircleHelp,
  ExternalLink,
  Eye,
  EyeOff,
  Globe,
  Headset,
  Inbox,
  KeyRound,
  LayoutGrid,
  LogOut,
  MessageCircle,
  Monitor,
  Moon,
  SunMoon,
  Sun,
  Palette,
  RefreshCw,
  ShieldCheck,
  Smartphone,
  Sparkles,
  Trash2,
  X,
} from '@lucide/vue'
import Swal from 'sweetalert2'
import { useRouter } from 'vue-router'
import { version as appVersion } from '../../package.json'
import TopBar from '../components/TopBar.vue'
import { auth } from '../services/auth.js'
import { preferences } from '../services/preferences.js'
import {
  notificationsState,
  checkPushStatus,
  subscribePush,
  unsubscribePush,
  openNotificationsDrawer,
} from '../services/notifications.js'

const router = useRouter()
const activeSheet = ref(null)
const processingPush = ref(false)
const pushFeedback = ref({ type: '', text: '' })

const passwordForm = reactive({ current_password: '', new_password: '', new_password_confirm: '' })
const showPasswords = ref(false)
const savingPassword = ref(false)
const passwordFeedback = ref({ type: '', text: '' })

const devices = ref([])
const devicesLoaded = ref(false)
const loadingDevices = ref(false)
const removingDeviceId = ref('')
const currentEndpointHash = ref('')
const devicesFeedback = ref({ type: '', text: '' })

const appearanceFeedback = ref({ type: '', text: '' })
const resettingMenu = ref(false)
const refreshingCache = ref(false)

const openFaq = ref('')

const sheetMeta = {
  notifications: { eyebrow: 'TERCİHLER', title: 'Bildirim Ayarları' },
  theme: { eyebrow: 'GÖRÜNÜM', title: 'Görünüm ve Tema' },
  security: { eyebrow: 'HESAP GÜVENLİĞİ', title: 'Güvenlik' },
  devices: { eyebrow: 'OTURUMLAR', title: 'Bağlı Cihazlar' },
  help: { eyebrow: 'DESTEK', title: 'Yardım Merkezi' },
}

const rows = [
  { id: 'notifications', label: 'Bildirim tercihleri', icon: Bell },
  { id: 'theme', label: 'Görünüm ve tema', icon: Palette },
  { id: 'security', label: 'Güvenlik', icon: ShieldCheck },
  { id: 'devices', label: 'Bağlı cihazlar', icon: Smartphone },
  { id: 'help', label: 'Yardım merkezi', icon: CircleHelp },
]

const faqs = [
  {
    id: 'push',
    question: 'Bildirimler neden gelmiyor?',
    answer: 'Profil > Bildirim tercihleri ekranından cihaz bildirimlerini açın. iPhone kullanıyorsanız uygulamayı önce Safari üzerinden ana ekrana eklemeniz gerekir (iOS 16.4 ve üzeri).',
  },
  {
    id: 'install',
    question: 'Uygulamayı ana ekrana nasıl eklerim?',
    answer: 'Android Chrome’da sağ üstteki menüden “Ana ekrana ekle”yi, iPhone Safari’de Paylaş simgesinden “Ana Ekrana Ekle”yi seçin.',
  },
  {
    id: 'permission',
    question: 'Bazı menüleri neden göremiyorum?',
    answer: 'Menüler, kullanıcı grubunuza tanımlı yetkilere göre gösterilir. Erişmeniz gereken bir modül varsa sistem yöneticinizden yetki talep edin.',
  },
  {
    id: 'company',
    question: 'Çalışma alanını (firmayı) nasıl değiştiririm?',
    answer: 'Ana sayfada sol üstteki firma adına dokunarak yetkili olduğunuz firmalar arasında geçiş yapabilirsiniz.',
  },
  {
    id: 'stale',
    question: 'Ekranlar güncel görünmüyor, ne yapmalıyım?',
    answer: 'Profil > Görünüm ve tema ekranındaki “Uygulamayı yenile” işlemi önbelleği temizleyip en güncel sürümü yükler.',
  },
]

const canLiveSupport = computed(() => Boolean(auth.state.permissions.live_support))
const canSupport = computed(() => Boolean(auth.state.permissions.support))

onMounted(() => {
  checkPushStatus()
  loadDevices()
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

function rowBadge(rowId) {
  if (rowId === 'notifications') return { text: notifBadgeText.value, cls: notifBadgeClass.value }
  if (rowId === 'devices' && devicesLoaded.value) {
    return devices.value.length
      ? { text: `${devices.value.length} cihaz`, cls: 'clay-badge--blue' }
      : { text: 'Yok', cls: '' }
  }
  if (rowId === 'theme') {
    const option = themeOptions.find((item) => item.id === preferences.state.theme)
    return { text: option?.label || 'Açık', cls: preferences.state.resolvedTheme === 'dark' ? 'clay-badge--purple' : '' }
  }
  return null
}

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
    await loadDevices()
  } catch (err) {
    pushFeedback.value = { type: 'error', text: err.message || 'İşlem tamamlanamadı.' }
  } finally {
    processingPush.value = false
  }
}

function openSheet(id) {
  activeSheet.value = id
  pushFeedback.value = { type: '', text: '' }
  passwordFeedback.value = { type: '', text: '' }
  devicesFeedback.value = { type: '', text: '' }
  appearanceFeedback.value = { type: '', text: '' }
  if (id === 'devices') loadDevices()
}

function closeSheet() {
  activeSheet.value = null
  resetPasswordForm()
}

function resetPasswordForm() {
  passwordForm.current_password = ''
  passwordForm.new_password = ''
  passwordForm.new_password_confirm = ''
  showPasswords.value = false
}

const passwordChecks = computed(() => [
  { label: 'En az 8 karakter', ok: passwordForm.new_password.length >= 8 },
  { label: 'Harf içeriyor', ok: /\p{L}/u.test(passwordForm.new_password) },
  { label: 'Rakam içeriyor', ok: /\d/.test(passwordForm.new_password) },
  {
    label: 'Tekrarı eşleşiyor',
    ok: passwordForm.new_password !== '' && passwordForm.new_password === passwordForm.new_password_confirm,
  },
])

const canSubmitPassword = computed(() =>
  passwordForm.current_password !== '' && passwordChecks.value.every((check) => check.ok) && !savingPassword.value,
)

async function submitPassword() {
  if (!canSubmitPassword.value) return
  savingPassword.value = true
  passwordFeedback.value = { type: '', text: '' }
  try {
    const payload = await auth.changePassword({ ...passwordForm })
    resetPasswordForm()
    passwordFeedback.value = { type: 'success', text: payload.message || 'Şifreniz güncellendi.' }
  } catch (err) {
    passwordFeedback.value = { type: 'error', text: err.message || 'Şifre güncellenemedi.' }
  } finally {
    savingPassword.value = false
  }
}

async function sha256Hex(text) {
  const buffer = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(text))
  return Array.from(new Uint8Array(buffer), (byte) => byte.toString(16).padStart(2, '0')).join('')
}

async function resolveCurrentEndpointHash() {
  try {
    if (!('serviceWorker' in navigator) || !window.crypto?.subtle) return ''
    const registration = await navigator.serviceWorker.getRegistration()
    const subscription = await registration?.pushManager?.getSubscription()
    return subscription ? await sha256Hex(subscription.endpoint) : ''
  } catch {
    return ''
  }
}

async function loadDevices() {
  loadingDevices.value = true
  try {
    const [list, hash] = await Promise.all([auth.devices(), resolveCurrentEndpointHash()])
    devices.value = list
    currentEndpointHash.value = hash
    devicesLoaded.value = true
  } catch (err) {
    devicesFeedback.value = { type: 'error', text: err.message || 'Cihazlar yüklenemedi.' }
  } finally {
    loadingDevices.value = false
  }
}

function isCurrentDevice(device) {
  return currentEndpointHash.value !== '' && device.endpoint_hash === currentEndpointHash.value
}

const sortedDevices = computed(() =>
  [...devices.value].sort((a, b) => Number(isCurrentDevice(b)) - Number(isCurrentDevice(a))),
)

function deviceIcon(device) {
  return device.platform === 'windows' || device.platform === 'firefox' ? Monitor : device.platform === 'web' ? Globe : Smartphone
}

function formatDateTime(value) {
  if (!value) return '-'
  const date = new Date(String(value).replace(' ', 'T'))
  if (Number.isNaN(date.getTime())) return value
  return date.toLocaleString('tr-TR', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

async function removeDevice(device) {
  const current = isCurrentDevice(device)
  const result = await Swal.fire({
    title: current ? 'Bu cihazı kaldır?' : 'Cihazı kaldır?',
    text: current
      ? 'Bu cihazda anlık bildirimler kapatılacak.'
      : 'Seçilen cihaz artık anlık bildirim almayacak.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#ef4444',
    cancelButtonColor: '#64748b',
    confirmButtonText: 'Evet, Kaldır',
    cancelButtonText: 'Vazgeç',
    reverseButtons: true,
  })
  if (!result.isConfirmed) return

  removingDeviceId.value = device.id
  devicesFeedback.value = { type: '', text: '' }
  try {
    if (current) {
      await unsubscribePush()
      await loadDevices()
    } else {
      devices.value = await auth.removeDevice(device.id)
    }
    devicesFeedback.value = { type: 'success', text: 'Cihaz kaldırıldı.' }
  } catch (err) {
    devicesFeedback.value = { type: 'error', text: err.message || 'Cihaz kaldırılamadı.' }
  } finally {
    removingDeviceId.value = ''
  }
}

const themeOptions = [
  { id: 'light', label: 'Açık', icon: Sun },
  { id: 'dark', label: 'Koyu', icon: Moon },
  { id: 'system', label: 'Sistem', icon: SunMoon },
]

function toggleReduceMotion() {
  preferences.setReduceMotion(!preferences.state.reduceMotion)
}

async function resetMenuOrder() {
  const result = await Swal.fire({
    title: 'Menü düzeni sıfırlansın mı?',
    text: 'Alt menü ve “Daha Fazla” ekranındaki kişisel sıralamanız varsayılana döner.',
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#181c24',
    cancelButtonColor: '#64748b',
    confirmButtonText: 'Sıfırla',
    cancelButtonText: 'Vazgeç',
    reverseButtons: true,
  })
  if (!result.isConfirmed) return

  resettingMenu.value = true
  appearanceFeedback.value = { type: '', text: '' }
  try {
    const payload = await auth.resetMenuOrder()
    appearanceFeedback.value = { type: 'success', text: payload.message || 'Menü düzeni varsayılana döndürüldü.' }
  } catch (err) {
    appearanceFeedback.value = { type: 'error', text: err.message || 'Menü düzeni sıfırlanamadı.' }
  } finally {
    resettingMenu.value = false
  }
}

async function refreshAppCache() {
  refreshingCache.value = true
  try {
    await preferences.refreshAppCache()
  } catch (err) {
    refreshingCache.value = false
    appearanceFeedback.value = { type: 'error', text: err.message || 'Uygulama yenilenemedi.' }
  }
}

function goLiveSupport() {
  closeSheet()
  router.push('/canli-destek')
}
</script>

<template>
  <section class="ds-screen">
    <TopBar eyebrow="HESABIM" title="Profil" subtitle="Hesap ve uygulama tercihleri" :back="true" />

    <div class="bento-card bento-card--white profile-bento-card">
      <div class="profile-avatar-circle">
        <span>{{ auth.state.user?.initials || 'EE' }}</span>
      </div>
      <div class="profile-info">
        <span class="clay-badge clay-badge--emerald mb-1">Aktif Oturum</span>
        <h2>{{ auth.state.user?.name }}</h2>
        <p>
          <span v-if="auth.state.user?.username">@{{ auth.state.user.username }} · </span>{{ auth.state.company?.name }}
        </p>
      </div>
    </div>

    <div class="bento-card bento-card--white settings-bento-card">
      <button
        v-for="row in rows"
        :key="row.id"
        type="button"
        class="settings-row"
        @click="openSheet(row.id)"
      >
        <span class="settings-icon">
          <component :is="row.icon" :size="18" />
        </span>
        <strong>{{ row.label }}</strong>
        <span v-if="rowBadge(row.id)" class="clay-badge me-2" :class="rowBadge(row.id).cls">
          {{ rowBadge(row.id).text }}
        </span>
        <ChevronRight :size="16" class="settings-arrow" />
      </button>

      <button type="button" class="settings-row settings-row--danger" @click="logout">
        <span class="settings-icon settings-icon--danger">
          <LogOut :size="18" />
        </span>
        <strong class="text-danger">Güvenli Çıkış Yap</strong>
        <ChevronRight :size="16" class="settings-arrow" />
      </button>
    </div>

    <p class="profile-version">Ersan Mobil · Sürüm {{ appVersion }}</p>

    <Transition name="nav-sheet">
      <div v-if="activeSheet" class="nav-sheet-layer" @click.self="closeSheet">
        <section
          class="nav-sheet profile-sheet"
          role="dialog"
          aria-modal="true"
          :aria-label="sheetMeta[activeSheet].title"
        >
          <header class="profile-sheet__header">
            <div>
              <span class="profile-sheet__eyebrow">{{ sheetMeta[activeSheet].eyebrow }}</span>
              <h2>{{ sheetMeta[activeSheet].title }}</h2>
            </div>
            <button type="button" class="profile-sheet__close-btn" aria-label="Kapat" @click="closeSheet">
              <X :size="19" />
            </button>
          </header>

          <div v-if="activeSheet === 'notifications'" class="profile-sheet__content">
            <div v-if="pushFeedback.text" class="feedback-box" :class="`feedback-box--${pushFeedback.type}`">
              {{ pushFeedback.text }}
            </div>

            <div class="bento-card bento-card--white setting-card">
              <div class="setting-card__icon" :class="{ 'is-active': notificationsState.pushSubscribed }">
                <BellRing v-if="notificationsState.pushSubscribed" :size="20" />
                <BellOff v-else :size="20" />
              </div>
              <div class="setting-card__body">
                <div class="setting-card__title-row">
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
                  Cihazınız veya tarayıcınız Web Push bildirimlerini desteklemiyor. iPhone'da uygulamayı ana ekrana ekleyerek açmayı deneyin.
                </p>
                <p v-else>
                  Uygulama kapalıyken bile talep ve onaylardan haberdar olmak için bildirimleri açın.
                </p>
              </div>
              <div
                v-if="notificationsState.pushSupported && notificationsState.permission !== 'denied'"
                class="setting-card__action"
              >
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

            <div class="bento-card bento-card--white setting-card">
              <div class="setting-card__icon">
                <Inbox :size="20" />
              </div>
              <div class="setting-card__body">
                <div class="setting-card__title-row">
                  <strong>Bildirim Geçmişi</strong>
                  <span v-if="notificationsState.unreadCount > 0" class="clay-badge clay-badge--emerald">
                    {{ notificationsState.unreadCount }} Okunmamış
                  </span>
                </div>
                <p>Tüm geçmiş sistem bildirimlerini görüntüleyin ve yönetin.</p>
              </div>
              <div class="setting-card__action">
                <button
                  type="button"
                  class="clay-btn clay-btn--secondary"
                  @click="closeSheet(); openNotificationsDrawer()"
                >
                  <span>Bildirimleri Görüntüle</span>
                  <ChevronRight :size="15" />
                </button>
              </div>
            </div>
          </div>

          <div v-else-if="activeSheet === 'theme'" class="profile-sheet__content">
            <div
              v-if="appearanceFeedback.text"
              class="feedback-box"
              :class="`feedback-box--${appearanceFeedback.type}`"
            >
              {{ appearanceFeedback.text }}
            </div>

            <div class="bento-card bento-card--white setting-card">
              <div class="setting-card__inline">
                <div class="setting-card__icon is-active">
                  <Moon v-if="preferences.state.resolvedTheme === 'dark'" :size="20" />
                  <Sun v-else :size="20" />
                </div>
                <div class="setting-card__body">
                  <strong>Tema</strong>
                  <p>Sistem seçeneği, cihazınızın açık/koyu mod ayarını otomatik takip eder.</p>
                </div>
              </div>
              <div class="theme-options" role="radiogroup" aria-label="Tema">
                <button
                  v-for="option in themeOptions"
                  :key="option.id"
                  type="button"
                  role="radio"
                  class="theme-option"
                  :class="{ 'is-active': preferences.state.theme === option.id }"
                  :aria-checked="preferences.state.theme === option.id"
                  @click="preferences.setTheme(option.id)"
                >
                  <component :is="option.icon" :size="17" />
                  <span>{{ option.label }}</span>
                </button>
              </div>
            </div>

            <div class="bento-card bento-card--white setting-card">
              <div class="setting-card__inline">
                <div class="setting-card__icon" :class="{ 'is-active': preferences.state.reduceMotion }">
                  <Sparkles :size="20" />
                </div>
                <div class="setting-card__body">
                  <strong>Hareketleri azalt</strong>
                  <p>Geçiş ve kayma animasyonlarını kapatır; eski cihazlarda ekranlar daha akıcı açılır.</p>
                </div>
                <button
                  type="button"
                  class="ds-switch"
                  role="switch"
                  :aria-checked="preferences.state.reduceMotion"
                  aria-label="Hareketleri azalt"
                  :class="{ 'is-on': preferences.state.reduceMotion }"
                  @click="toggleReduceMotion"
                >
                  <span />
                </button>
              </div>
            </div>

            <div class="bento-card bento-card--white setting-card">
              <div class="setting-card__inline">
                <div class="setting-card__icon">
                  <LayoutGrid :size="20" />
                </div>
                <div class="setting-card__body">
                  <strong>Menü düzeni</strong>
                  <p>Alt menüye sabitlediğiniz modülleri ve grup sıralamasını varsayılana döndürün.</p>
                </div>
              </div>
              <div class="setting-card__action">
                <button
                  type="button"
                  class="clay-btn clay-btn--secondary"
                  :disabled="resettingMenu"
                  @click="resetMenuOrder"
                >
                  {{ resettingMenu ? 'Sıfırlanıyor…' : 'Varsayılana Döndür' }}
                </button>
              </div>
            </div>

            <div class="bento-card bento-card--white setting-card">
              <div class="setting-card__inline">
                <div class="setting-card__icon">
                  <RefreshCw :size="20" />
                </div>
                <div class="setting-card__body">
                  <strong>Uygulamayı yenile</strong>
                  <p>Önbelleği temizleyip uygulamanın en güncel sürümünü yükler. Oturumunuz kapanmaz.</p>
                </div>
              </div>
              <div class="setting-card__action">
                <button
                  type="button"
                  class="clay-btn clay-btn--primary"
                  :disabled="refreshingCache"
                  @click="refreshAppCache"
                >
                  {{ refreshingCache ? 'Yenileniyor…' : 'Önbelleği Temizle ve Yenile' }}
                </button>
              </div>
            </div>
          </div>

          <div v-else-if="activeSheet === 'security'" class="profile-sheet__content">
            <div v-if="passwordFeedback.text" class="feedback-box" :class="`feedback-box--${passwordFeedback.type}`">
              {{ passwordFeedback.text }}
            </div>

            <form class="bento-card bento-card--white setting-card" autocomplete="on" @submit.prevent="submitPassword">
              <div class="setting-card__inline">
                <div class="setting-card__icon">
                  <KeyRound :size="20" />
                </div>
                <div class="setting-card__body">
                  <strong>Şifre değiştir</strong>
                  <p>Yeni şifreniz web paneli ve mobil uygulama girişlerinde geçerli olur.</p>
                </div>
              </div>

              <input type="text" class="visually-hidden-field" autocomplete="username" :value="auth.state.user?.username" readonly tabindex="-1" aria-hidden="true" />

              <label class="password-field">
                <span>Mevcut şifre</span>
                <input
                  v-model="passwordForm.current_password"
                  class="clay-input"
                  :type="showPasswords ? 'text' : 'password'"
                  autocomplete="current-password"
                  required
                />
              </label>
              <label class="password-field">
                <span>Yeni şifre</span>
                <input
                  v-model="passwordForm.new_password"
                  class="clay-input"
                  :type="showPasswords ? 'text' : 'password'"
                  autocomplete="new-password"
                  required
                />
              </label>
              <label class="password-field">
                <span>Yeni şifre (tekrar)</span>
                <input
                  v-model="passwordForm.new_password_confirm"
                  class="clay-input"
                  :type="showPasswords ? 'text' : 'password'"
                  autocomplete="new-password"
                  required
                />
              </label>

              <button type="button" class="password-visibility" @click="showPasswords = !showPasswords">
                <EyeOff v-if="showPasswords" :size="15" />
                <Eye v-else :size="15" />
                {{ showPasswords ? 'Şifreleri gizle' : 'Şifreleri göster' }}
              </button>

              <ul class="password-checks">
                <li v-for="check in passwordChecks" :key="check.label" :class="{ 'is-ok': check.ok }">
                  {{ check.label }}
                </li>
              </ul>

              <div class="setting-card__action">
                <button type="submit" class="clay-btn clay-btn--primary" :disabled="!canSubmitPassword">
                  {{ savingPassword ? 'Kaydediliyor…' : 'Şifreyi Güncelle' }}
                </button>
              </div>
            </form>
          </div>

          <div v-else-if="activeSheet === 'devices'" class="profile-sheet__content">
            <div v-if="devicesFeedback.text" class="feedback-box" :class="`feedback-box--${devicesFeedback.type}`">
              {{ devicesFeedback.text }}
            </div>

            <p class="profile-sheet__hint">
              Hesabınıza anlık bildirim gönderilen cihazlar. Tanımadığınız veya artık kullanmadığınız cihazları kaldırın.
            </p>

            <div v-if="loadingDevices && !devicesLoaded" class="bento-card bento-card--white device-empty">
              Cihazlar yükleniyor…
            </div>
            <div v-else-if="!devices.length" class="bento-card bento-card--white device-empty">
              <Smartphone :size="22" />
              <span>Bildirim alan kayıtlı cihaz bulunmuyor.</span>
            </div>
            <div v-else class="bento-card bento-card--white device-list">
              <div v-for="device in sortedDevices" :key="device.id" class="device-row">
                <span class="settings-icon" :class="{ 'is-current': isCurrentDevice(device) }">
                  <component :is="deviceIcon(device)" :size="18" />
                </span>
                <div class="device-row__info">
                  <strong>
                    {{ device.label }}
                    <span v-if="isCurrentDevice(device)" class="clay-badge clay-badge--emerald">Bu cihaz</span>
                  </strong>
                  <small>Son etkinlik: {{ formatDateTime(device.last_seen_at) }}</small>
                  <small>Eklenme: {{ formatDateTime(device.created_at) }}</small>
                </div>
                <button
                  type="button"
                  class="device-row__remove"
                  :aria-label="`${device.label} cihazını kaldır`"
                  :disabled="removingDeviceId === device.id"
                  @click="removeDevice(device)"
                >
                  <Trash2 :size="16" />
                </button>
              </div>
            </div>
          </div>

          <div v-else-if="activeSheet === 'help'" class="profile-sheet__content">
            <div v-if="canLiveSupport || canSupport" class="bento-card bento-card--white settings-bento-card">
              <button v-if="canLiveSupport" type="button" class="settings-row" @click="goLiveSupport">
                <span class="settings-icon"><MessageCircle :size="18" /></span>
                <strong>Canlı Destek</strong>
                <ChevronRight :size="16" class="settings-arrow" />
              </button>
              <a v-if="canSupport" href="/mobile/index.php?p=yardim" class="settings-row">
                <span class="settings-icon"><Headset :size="18" /></span>
                <strong>Destek talebi ve kullanım kılavuzu</strong>
                <ExternalLink :size="15" class="settings-arrow" />
              </a>
            </div>

            <div class="bento-card bento-card--white faq-list">
              <div v-for="faq in faqs" :key="faq.id" class="faq-item" :class="{ 'is-open': openFaq === faq.id }">
                <button
                  type="button"
                  class="faq-item__question"
                  :aria-expanded="openFaq === faq.id"
                  @click="openFaq = openFaq === faq.id ? '' : faq.id"
                >
                  <span>{{ faq.question }}</span>
                  <ChevronDown :size="16" />
                </button>
                <p v-if="openFaq === faq.id">{{ faq.answer }}</p>
              </div>
            </div>

            <p class="profile-sheet__hint text-center">Ersan Mobil · Sürüm {{ appVersion }}</p>
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
  color: var(--ds-ink-light);
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
  overflow: hidden;
  font-size: 11px;
  color: var(--ds-muted);
  text-overflow: ellipsis;
  white-space: nowrap;
}

.profile-version {
  margin: 4px 0 0;
  text-align: center;
  font-size: 11px;
  color: var(--ds-muted-light);
}

.settings-bento-card {
  padding: 6px 16px;
}

.settings-row {
  display: flex;
  width: 100%;
  min-height: 44px;
  align-items: center;
  gap: 12px;
  padding: 13px 0;
  border: 0;
  border-bottom: 1px solid var(--ds-border-subtle);
  background: transparent;
  color: inherit;
  cursor: pointer;
  text-align: left;
  text-decoration: none;
}

.settings-row:last-child {
  border-bottom: 0;
}

.settings-icon {
  display: grid;
  width: 36px;
  height: 36px;
  flex-shrink: 0;
  place-items: center;
  border-radius: 12px;
  background: var(--ds-surface-subtle);
  color: var(--ds-primary-ink);
}

.settings-icon.is-current {
  background: var(--ds-emerald-soft);
  color: var(--ds-emerald-ink);
}

.settings-row strong {
  flex: 1;
  font-size: 13px;
  font-weight: 700;
  color: var(--ds-ink);
}

.settings-arrow {
  flex-shrink: 0;
  color: var(--ds-muted);
}

.settings-row--danger .settings-icon--danger {
  background: var(--ds-danger-soft);
  color: var(--ds-danger-ink);
}

.settings-row--danger strong {
  color: var(--ds-danger-ink);
}

.settings-row--danger .settings-arrow {
  color: var(--ds-danger-ink);
  opacity: 0.6;
}

.profile-sheet {
  max-height: calc(85svh - 40px - var(--ds-safe-bottom));
}

.profile-sheet__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
}

.profile-sheet__eyebrow {
  display: block;
  margin-bottom: 2px;
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.12em;
  color: var(--ds-blue-ink);
  text-transform: uppercase;
}

.profile-sheet__header h2 {
  margin: 0;
  font-size: 20px;
  font-weight: 800;
  letter-spacing: -0.03em;
  color: var(--ds-ink);
}

.profile-sheet__close-btn {
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

.profile-sheet__content {
  display: flex;
  flex-direction: column;
  gap: 12px;
  min-height: 0;
  overflow-y: auto;
  overscroll-behavior: contain;
}

.profile-sheet__hint {
  margin: 0;
  font-size: 12px;
  line-height: 1.4;
  color: var(--ds-muted);
}

.setting-card {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 16px;
}

.setting-card__inline {
  display: flex;
  align-items: center;
  gap: 12px;
}

.setting-card__icon {
  display: grid;
  width: 42px;
  height: 42px;
  flex-shrink: 0;
  place-items: center;
  border-radius: 14px;
  background: var(--ds-surface-subtle);
  color: var(--ds-muted);
}

.setting-card__icon.is-active {
  background: var(--ds-primary);
  color: var(--ds-ink-light);
}

.setting-card__body {
  display: flex;
  flex: 1;
  min-width: 0;
  flex-direction: column;
  gap: 4px;
}

.setting-card__body strong,
.setting-card__title-row strong {
  font-size: 14px;
  font-weight: 800;
  color: var(--ds-ink);
}

.setting-card__title-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.setting-card__body p {
  margin: 0;
  font-size: 12px;
  line-height: 1.35;
  color: var(--ds-muted);
}

.setting-card__action .clay-btn {
  width: 100%;
  height: 44px;
  font-size: 13px;
  font-weight: 800;
  border-radius: var(--ds-radius-tile);
}

.setting-card__action .clay-btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.clay-btn--subtle-danger {
  background: var(--ds-danger-soft);
  color: var(--ds-danger);
  border: 1px solid rgba(239, 68, 68, 0.2);
}

.theme-options {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 6px;
  padding: 4px;
  border-radius: var(--ds-radius-sm);
  background: var(--ds-surface-subtle);
}

.theme-option {
  display: flex;
  min-height: 44px;
  align-items: center;
  justify-content: center;
  gap: 6px;
  border: 0;
  border-radius: 11px;
  background: transparent;
  color: var(--ds-muted);
  font: 700 12px var(--ds-font-main);
  cursor: pointer;
  transition: background 0.18s ease, color 0.18s ease;
}

.theme-option.is-active {
  background: var(--ds-surface);
  color: var(--ds-ink);
  box-shadow: var(--ds-shadow-btn);
}

.ds-switch {
  position: relative;
  width: 50px;
  height: 30px;
  flex-shrink: 0;
  border: 0;
  border-radius: var(--ds-radius-pill);
  background: var(--ds-surface-subtle);
  box-shadow: inset 0 0 0 1px var(--ds-border-subtle);
  cursor: pointer;
  transition: background 0.2s ease;
}

.ds-switch span {
  position: absolute;
  top: 3px;
  left: 3px;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background: var(--ds-surface);
  box-shadow: var(--ds-shadow-btn);
  transition: transform 0.2s ease;
}

.ds-switch.is-on {
  background: var(--ds-emerald);
}

.ds-switch.is-on span {
  transform: translateX(20px);
}

.visually-hidden-field {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  opacity: 0;
  pointer-events: none;
}

.password-field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.password-field span {
  font-size: 12px;
  font-weight: 700;
  color: var(--ds-muted);
}

.password-visibility {
  display: inline-flex;
  align-self: flex-start;
  min-height: 32px;
  align-items: center;
  gap: 6px;
  border: 0;
  background: transparent;
  color: var(--ds-blue-ink);
  font: 700 12px var(--ds-font-main);
  cursor: pointer;
}

.password-checks {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 6px 10px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.password-checks li {
  position: relative;
  padding-left: 16px;
  font-size: 11px;
  color: var(--ds-muted);
}

.password-checks li::before {
  position: absolute;
  top: 50%;
  left: 0;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--ds-border-subtle);
  box-shadow: inset 0 0 0 1px var(--ds-muted-light);
  content: '';
  transform: translateY(-50%);
}

.password-checks li.is-ok {
  color: var(--ds-emerald-ink);
}

.password-checks li.is-ok::before {
  background: var(--ds-emerald);
  box-shadow: none;
}

.device-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 24px 16px;
  font-size: 12px;
  color: var(--ds-muted);
  text-align: center;
}

.device-list {
  padding: 4px 16px;
}

.device-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 0;
  border-bottom: 1px solid var(--ds-border-subtle);
}

.device-row:last-child {
  border-bottom: 0;
}

.device-row__info {
  display: flex;
  flex: 1;
  min-width: 0;
  flex-direction: column;
  gap: 2px;
}

.device-row__info strong {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  font-weight: 800;
  color: var(--ds-ink);
}

.device-row__info small {
  font-size: 11px;
  color: var(--ds-muted);
}

.device-row__remove {
  display: grid;
  width: 44px;
  height: 44px;
  flex-shrink: 0;
  place-items: center;
  border: 0;
  border-radius: 14px;
  background: var(--ds-danger-soft);
  color: var(--ds-danger-ink);
  cursor: pointer;
}

.device-row__remove:disabled {
  opacity: 0.5;
}

.faq-list {
  padding: 4px 16px;
}

.faq-item {
  border-bottom: 1px solid var(--ds-border-subtle);
}

.faq-item:last-child {
  border-bottom: 0;
}

.faq-item__question {
  display: flex;
  width: 100%;
  min-height: 48px;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 10px 0;
  border: 0;
  background: transparent;
  color: var(--ds-ink);
  font: 700 13px var(--ds-font-main);
  text-align: left;
  cursor: pointer;
}

.faq-item__question svg {
  flex-shrink: 0;
  color: var(--ds-muted);
  transition: transform 0.2s ease;
}

.faq-item.is-open .faq-item__question svg {
  transform: rotate(180deg);
}

.faq-item p {
  margin: 0 0 12px;
  font-size: 12px;
  line-height: 1.45;
  color: var(--ds-muted);
}

.feedback-box {
  padding: 10px 14px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 700;
}

.feedback-box--success {
  background: var(--ds-emerald-soft);
  color: var(--ds-emerald-ink);
  border: 1px solid var(--dsx-bd-a7f3d0);
}

.feedback-box--error {
  background: var(--ds-danger-soft);
  color: var(--ds-danger-ink);
  border: 1px solid var(--dsx-bd-fecaca);
}
</style>
