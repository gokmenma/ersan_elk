<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { ArrowLeft, Check, CheckCheck, ChevronDown, Circle, Clock3, Headset, ImagePlus, Inbox, LoaderCircle, MessageCircle, RefreshCw, Save, Search, Send, Settings, UserRound, UsersRound, X } from '@lucide/vue'
import { auth } from '../services/auth.js'
import { supportApi } from '../services/support.js'

const router = useRouter()
const items = ref([])
const summary = ref({ toplam: 0, aktif: 0, bekleyen: 0, kapali: 0, okunmamis: 0 })
const loading = ref(true)
const refreshing = ref(false)
const error = ref('')
const search = ref('')
const status = ref('aktif')
const availability = ref('cevrimici')
const workingHours = ref(true)
const active = ref(null)
const messages = ref([])
const chatLoading = ref(false)
const sending = ref(false)
const draft = ref('')
const image = ref(null)
const imagePreview = ref('')
const messageList = ref(null)
const fileInput = ref(null)
const statusMenu = ref(false)
const toast = ref('')
const settingsOpen = ref(false)
const settingsLoading = ref(false)
const settingsSaving = ref(false)
const userSearch = ref('')
const supportUsers = ref([])
const supportSettings = ref({ enabled: true, authorized_user_ids: [], working_days: [1, 2, 3, 4, 5, 6], start_time: '08:00', end_time: '18:00' })
let listTimer
let chatTimer
let searchTimer

const tabs = computed(() => [
  { value: '', label: 'Tümü', count: summary.value.toplam },
  { value: 'aktif', label: 'Aktif', count: summary.value.aktif },
  { value: 'beklemede', label: 'Bekleyen', count: summary.value.bekleyen },
  { value: 'tamamlanan', label: 'Tamamlanan', count: summary.value.kapali },
])
const lastMessageId = computed(() => messages.value.at(-1)?.id || 0)
const activeOpen = computed(() => active.value && ['acik', 'beklemede'].includes(active.value.status))
const availabilityLabel = computed(() => ({ cevrimici: 'Çevrimiçi', mesgul: 'Meşgul', cevrimdisi: 'Çevrimdışı' }[availability.value] || 'Çevrimiçi'))
const filteredSupportUsers = computed(() => {
  const query = userSearch.value.trim().toLocaleLowerCase('tr-TR')
  if (!query) return supportUsers.value
  return supportUsers.value.filter(user => `${user.name} ${user.username}`.toLocaleLowerCase('tr-TR').includes(query))
})

function initials(name = '') {
  const parts = name.trim().split(/\s+/).filter(Boolean)
  return (parts.length > 1 ? parts[0][0] + parts.at(-1)[0] : name.slice(0, 2)).toLocaleUpperCase('tr-TR') || '?'
}
function dateLabel(value) {
  if (!value) return ''
  const date = new Date(value.replace(' ', 'T'))
  const now = new Date()
  if (date.toDateString() === now.toDateString()) return date.toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' })
  return date.toLocaleDateString('tr-TR', { day: '2-digit', month: 'short' })
}
function messageTime(value) {
  return new Date(value.replace(' ', 'T')).toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' })
}
function showToast(message) {
  toast.value = message
  window.setTimeout(() => { toast.value = '' }, 2400)
}
async function scrollBottom(behavior = 'auto') {
  await nextTick()
  messageList.value?.scrollTo({ top: messageList.value.scrollHeight, behavior })
}
async function loadList(silent = false) {
  if (silent) refreshing.value = true
  else loading.value = true
  error.value = ''
  try {
    const res = await supportApi.list({ status: status.value, search: search.value.trim() })
    items.value = res.items || []
    summary.value = res.summary || summary.value
    availability.value = res.availability || 'cevrimici'
    workingHours.value = Boolean(res.working_hours)
    if (active.value) {
      const fresh = items.value.find(item => item.id === active.value.id)
      if (fresh) active.value = { ...active.value, ...fresh }
    }
  } catch (err) { error.value = err.message }
  finally { loading.value = false; refreshing.value = false }
}
async function openChat(item) {
  active.value = item
  chatLoading.value = true
  statusMenu.value = false
  try {
    const res = await supportApi.detail(item.id)
    active.value = res.conversation
    messages.value = res.messages || []
    active.value.unread = 0
    scrollBottom()
    startChatPolling()
  } catch (err) { showToast(err.message) }
  finally { chatLoading.value = false }
}
function closeChat() {
  active.value = null
  messages.value = []
  clearInterval(chatTimer)
  clearImage()
}
async function pollChat() {
  if (!active.value || document.hidden) return
  try {
    const res = await supportApi.poll(active.value.id, lastMessageId.value)
    if (res.messages?.length) {
      messages.value.push(...res.messages)
      scrollBottom('smooth')
      loadList(true)
    }
  } catch { /* bağlantı geri geldiğinde sonraki tur devam eder */ }
}
function startChatPolling() {
  clearInterval(chatTimer)
  chatTimer = window.setInterval(pollChat, 3500)
}
async function sendMessage() {
  if ((!draft.value.trim() && !image.value) || sending.value || !active.value) return
  sending.value = true
  try {
    await supportApi.send(active.value.id, draft.value.trim(), image.value, auth.state.csrfToken)
    draft.value = ''
    clearImage()
    const res = await supportApi.poll(active.value.id, lastMessageId.value)
    if (res.messages?.length) messages.value.push(...res.messages)
    active.value.status = 'acik'
    scrollBottom('smooth')
    loadList(true)
  } catch (err) { showToast(err.message) }
  finally { sending.value = false }
}
function chooseImage(event) {
  const selected = event.target.files?.[0]
  if (!selected) return
  if (selected.size > 5 * 1024 * 1024) return showToast('Görsel en fazla 5 MB olabilir.')
  clearImage()
  image.value = selected
  imagePreview.value = URL.createObjectURL(selected)
}
function clearImage() {
  if (imagePreview.value) URL.revokeObjectURL(imagePreview.value)
  image.value = null
  imagePreview.value = ''
  if (fileInput.value) fileInput.value.value = ''
}
async function changeConversationStatus(next) {
  if (!active.value) return
  try {
    await supportApi.updateStatus(active.value.id, next, auth.state.csrfToken)
    active.value.status = next
    statusMenu.value = false
    if (['cozuldu', 'kapali'].includes(next)) {
      const res = await supportApi.poll(active.value.id, lastMessageId.value)
      if (res.messages?.length) messages.value.push(...res.messages)
    }
    await loadList(true)
    showToast('Konuşma durumu güncellendi.')
  } catch (err) { showToast(err.message) }
}
async function changeAvailability(next) {
  try {
    await supportApi.setAvailability(next, auth.state.csrfToken)
    availability.value = next
    showToast(`Durumunuz: ${{ cevrimici: 'Çevrimiçi', mesgul: 'Meşgul', cevrimdisi: 'Çevrimdışı' }[next]}`)
  } catch (err) { showToast(err.message) }
}
async function openSettings() {
  settingsOpen.value = true
  settingsLoading.value = true
  userSearch.value = ''
  try {
    const res = await supportApi.getSettings()
    supportSettings.value = res.settings
    supportUsers.value = res.users || []
  } catch (err) { showToast(err.message); settingsOpen.value = false }
  finally { settingsLoading.value = false }
}
function toggleWorkingDay(day) {
  const selected = supportSettings.value.working_days
  supportSettings.value.working_days = selected.includes(day) ? selected.filter(value => value !== day) : [...selected, day].sort()
}
function toggleAuthorizedUser(id) {
  const selected = supportSettings.value.authorized_user_ids
  supportSettings.value.authorized_user_ids = selected.includes(id) ? selected.filter(value => value !== id) : [...selected, id]
}
async function saveSettings() {
  settingsSaving.value = true
  try {
    const res = await supportApi.saveSettings(supportSettings.value, auth.state.csrfToken)
    settingsOpen.value = false
    await loadList(true)
    showToast(res.message || 'Canlı destek ayarları kaydedildi.')
  } catch (err) { showToast(err.message) }
  finally { settingsSaving.value = false }
}

watch([status, search], () => {
  clearTimeout(searchTimer)
  searchTimer = window.setTimeout(() => loadList(), search.value ? 300 : 0)
})
onMounted(() => {
  loadList()
  listTimer = window.setInterval(() => { if (!document.hidden && !active.value) loadList(true) }, 10000)
})
onBeforeUnmount(() => {
  clearInterval(listTimer); clearInterval(chatTimer); clearTimeout(searchTimer); clearImage()
})
</script>

<template>
  <section class="ds-screen support-screen">
    <template v-if="!active">
      <header class="clay-topbar">
        <div class="clay-topbar-main">
          <button class="clay-circle-btn" type="button" aria-label="Geri" @click="router.back()"><ArrowLeft :size="19" /></button>
          <div class="clay-topbar-title-block">
            <span class="clay-topbar-eyebrow">SİSTEM & DESTEK</span>
            <h1 class="clay-topbar-title">Canlı Destek</h1>
            <p class="clay-topbar-subtitle">Personel mesajları ve destek kuyruğu</p>
          </div>
        </div>
        <div class="support-top-actions">
          <button class="clay-circle-btn" type="button" aria-label="Canlı destek ayarları" @click="openSettings"><Settings :size="18" /></button>
          <button class="clay-circle-btn" type="button" aria-label="Yenile" :disabled="refreshing" @click="loadList(true)"><RefreshCw :size="18" :class="{ spin: refreshing }" /></button>
        </div>
      </header>

      <article class="support-hero bento-card--dark">
        <div class="support-hero__top">
          <div><span class="support-eyebrow">DESTEK MERKEZİ</span><h2>{{ summary.okunmamis }} yeni mesaj</h2><p>{{ workingHours ? 'Mesai saatleri içindesiniz' : 'Mesai dışı otomatik yanıt aktif' }}</p></div>
          <span class="support-hero__icon"><Headset :size="20" /></span>
        </div>
        <div class="support-hero__footer">
          <div class="support-availability">
            <button v-for="option in [{v:'cevrimici',l:'Çevrimiçi'},{v:'mesgul',l:'Meşgul'},{v:'cevrimdisi',l:'Çevrimdışı'}]" :key="option.v" type="button" :class="{ active: availability === option.v }" @click="changeAvailability(option.v)"><Circle :size="7" fill="currentColor" />{{ option.l }}</button>
          </div>
          <div class="support-mini-metrics"><span><strong>{{ summary.aktif }}</strong> aktif</span><i></i><span><strong>{{ summary.bekleyen }}</strong> bekleyen</span><i></i><span><strong>{{ summary.kapali }}</strong> tamamlanan</span></div>
        </div>
      </article>

      <div class="support-search"><Search :size="18" /><input v-model="search" type="search" placeholder="Personel, konu veya mesaj ara" aria-label="Destek konuşmalarında ara"></div>
      <div class="clay-tabs-wrap support-tabs">
        <button v-for="tab in tabs" :key="tab.value" type="button" :class="{ active: status === tab.value }" @click="status = tab.value">{{ tab.label }} <span>{{ tab.count }}</span></button>
      </div>

      <div v-if="loading" class="support-state"><LoaderCircle class="spin" :size="28" /><span>Konuşmalar yükleniyor…</span></div>
      <div v-else-if="error" class="support-state support-state--error"><p>{{ error }}</p><button type="button" @click="loadList()">Tekrar dene</button></div>
      <div v-else-if="!items.length" class="support-state"><span class="support-empty-icon"><Inbox :size="26" /></span><strong>Konuşma bulunamadı</strong><span>Filtrelerinize uygun destek kaydı yok.</span></div>
      <div v-else class="support-list">
        <button v-for="item in items" :key="item.id" type="button" class="support-card bento-card" @click="openChat(item)">
          <span class="support-avatar"><img v-if="item.avatar" :src="item.avatar" alt="" @error="item.avatar = null"><span v-else>{{ initials(item.personnel_name) }}</span><i :class="`is-${item.status}`"></i></span>
          <span class="support-card__body"><span class="support-card__heading"><strong>{{ item.personnel_name }}</strong><time>{{ dateLabel(item.last_message_at) }}</time></span><span class="support-card__subject">{{ item.subject }}</span><span class="support-card__preview">{{ item.preview || 'Henüz mesaj yok' }}</span><span class="support-card__meta"><em :class="`status-${item.status}`">{{ {acik:'Açık',beklemede:'Bekliyor',cozuldu:'Çözüldü',kapali:'Kapalı'}[item.status] }}</em><small>{{ item.department || 'Departman yok' }}</small></span></span>
          <span v-if="item.unread" class="support-unread">{{ item.unread > 99 ? '99+' : item.unread }}</span>
        </button>
      </div>
    </template>

    <template v-else>
      <div class="support-chat">
        <header class="support-chat__header">
          <button type="button" aria-label="Konuşmalara dön" @click="closeChat"><ArrowLeft :size="20" /></button>
          <span class="support-avatar support-avatar--small"><img v-if="active.avatar" :src="active.avatar" alt="" @error="active.avatar = null"><span v-else>{{ initials(active.personnel_name) }}</span></span>
          <div><strong>{{ active.personnel_name }}</strong><span>{{ active.department || active.subject }}</span></div>
          <div class="support-status-menu">
            <button type="button" @click="statusMenu = !statusMenu"><Circle :size="8" fill="currentColor" /> {{ {acik:'Açık',beklemede:'Bekliyor',cozuldu:'Çözüldü',kapali:'Kapalı'}[active.status] }} <ChevronDown :size="14" /></button>
            <div v-if="statusMenu"><button v-for="option in [{v:'acik',l:'Açık'},{v:'beklemede',l:'Beklemede'},{v:'cozuldu',l:'Çözüldü'},{v:'kapali',l:'Kapalı'}]" :key="option.v" type="button" @click="changeConversationStatus(option.v)"><Check v-if="active.status === option.v" :size="14" />{{ option.l }}</button></div>
          </div>
        </header>
        <div class="support-chat__subject"><MessageCircle :size="15" /><span>{{ active.subject }}</span><em>{{ availabilityLabel }}</em></div>
        <div ref="messageList" class="support-messages">
          <div v-if="chatLoading" class="support-state"><LoaderCircle class="spin" :size="26" /></div>
          <template v-else>
            <div v-for="message in messages" :key="message.id" class="support-message" :class="[`is-${message.sender_type}`]">
              <span v-if="message.sender_type === 'sistem'" class="support-system-message">{{ message.message }}</span>
              <div v-else class="support-bubble"><img v-if="message.file_url" :src="message.file_url" alt="Gönderilen destek görseli" loading="lazy"><p>{{ message.message }}</p><span>{{ messageTime(message.created_at) }} <CheckCheck v-if="message.sender_type === 'yonetici'" :size="13" :class="{ read: message.read }" /></span></div>
            </div>
          </template>
        </div>
        <footer class="support-composer">
          <div v-if="imagePreview" class="support-image-preview"><img :src="imagePreview" alt="Gönderilecek görsel"><button type="button" aria-label="Görseli kaldır" @click="clearImage"><X :size="15" /></button></div>
          <div v-if="!activeOpen" class="support-closed-note">Bu konuşma kapalı. Yanıtlamak için durumu “Açık” yapın.</div>
          <form v-else @submit.prevent="sendMessage">
            <input ref="fileInput" type="file" accept="image/jpeg,image/png,image/webp,image/gif" hidden @change="chooseImage">
            <button type="button" aria-label="Görsel ekle" @click="fileInput?.click()"><ImagePlus :size="20" /></button>
            <textarea v-model="draft" rows="1" maxlength="4000" placeholder="Mesajınızı yazın…" aria-label="Mesaj" @keydown.enter.exact.prevent="sendMessage"></textarea>
            <button class="send" type="submit" aria-label="Gönder" :disabled="sending || (!draft.trim() && !image)"><LoaderCircle v-if="sending" class="spin" :size="19" /><Send v-else :size="19" /></button>
          </form>
        </footer>
      </div>
    </template>

    <Teleport to="body">
      <Transition name="settings-sheet">
        <div v-if="settingsOpen" class="support-settings-layer" @click.self="settingsOpen = false">
          <section class="support-settings-sheet" role="dialog" aria-modal="true" aria-label="Canlı destek ayarları">
            <header><div><span>CANLI DESTEK</span><h2>Ayarlar</h2><p>Masaüstü modülle ortak yapılandırma</p></div><button type="button" aria-label="Kapat" @click="settingsOpen = false"><X :size="19" /></button></header>
            <div v-if="settingsLoading" class="support-state"><LoaderCircle class="spin" :size="28" /><span>Ayarlar yükleniyor…</span></div>
            <div v-else class="support-settings-body">
              <article class="support-setting-card support-setting-toggle"><span class="support-setting-icon is-blue"><Headset :size="20" /></span><div><strong>Canlı Destek Sistemi</strong><p>Kapalı olduğunda personel sohbet başlatamaz.</p></div><button type="button" role="switch" :aria-checked="supportSettings.enabled" :class="{ active: supportSettings.enabled }" @click="supportSettings.enabled = !supportSettings.enabled"><span></span></button></article>

              <article class="support-setting-card support-working-card">
                <div class="support-setting-heading"><span class="support-setting-icon is-amber"><Clock3 :size="20" /></span><div><strong>Mesai Günleri ve Saatleri</strong><p>Mesai dışında otomatik bilgilendirme gönderilir.</p></div></div>
                <div class="support-day-grid"><button v-for="day in [{v:1,l:'Pzt'},{v:2,l:'Sal'},{v:3,l:'Çar'},{v:4,l:'Per'},{v:5,l:'Cum'},{v:6,l:'Cmt'},{v:7,l:'Paz'}]" :key="day.v" type="button" :class="{ active: supportSettings.working_days.includes(day.v) }" @click="toggleWorkingDay(day.v)">{{ day.l }}</button></div>
                <div class="support-time-grid"><label><span>Başlangıç</span><input v-model="supportSettings.start_time" type="time"></label><label><span>Bitiş</span><input v-model="supportSettings.end_time" type="time"></label></div>
              </article>

              <article class="support-setting-card support-users-card">
                <div class="support-setting-heading"><span class="support-setting-icon is-green"><UsersRound :size="20" /></span><div><strong>Yetkili Kullanıcılar</strong><p>Seçim yoksa tüm yöneticiler erişebilir.</p></div></div>
                <div class="support-user-search"><Search :size="16" /><input v-model="userSearch" type="search" placeholder="Kullanıcı ara"></div>
                <div class="support-user-list">
                  <button v-for="user in filteredSupportUsers" :key="user.id" type="button" :class="{ selected: supportSettings.authorized_user_ids.includes(user.id) }" @click="toggleAuthorizedUser(user.id)"><span>{{ initials(user.name) }}</span><div><strong>{{ user.name }}</strong><small>@{{ user.username }}</small></div><i><Check :size="13" /></i></button>
                </div>
              </article>
            </div>
            <footer><button type="button" :disabled="settingsSaving || settingsLoading" @click="saveSettings"><LoaderCircle v-if="settingsSaving" class="spin" :size="18" /><Save v-else :size="18" />Ayarları Kaydet</button></footer>
          </section>
        </div>
      </Transition>
    </Teleport>
    <Transition name="toast"><div v-if="toast" class="support-toast">{{ toast }}</div></Transition>
  </section>
</template>

<style scoped>
.support-screen{padding-left:16px;padding-right:16px}.support-top-actions{display:flex;gap:7px}.support-hero{padding:16px 18px;border-radius:22px;color:var(--ds-ink-light);box-shadow:var(--ds-shadow-bento-dark);margin-bottom:14px}.support-hero__top{display:flex;align-items:center;justify-content:space-between;gap:16px}.support-eyebrow{font-size:8px;font-weight:800;letter-spacing:.13em;color:var(--ds-muted-light)}.support-hero h2{font-size:22px;margin:3px 0 1px}.support-hero p{font-size:11px;color:var(--ds-on-dark-muted);margin:0}.support-hero__icon{display:grid;place-items:center;width:40px;height:40px;border-radius:14px;background:var(--ds-on-dark-surface);color:var(--ds-blue)}.support-hero__footer{display:grid;gap:10px;margin-top:13px}.support-availability{display:flex;gap:5px;margin:0}.support-availability button{flex:1;display:flex;align-items:center;justify-content:center;gap:4px;min-height:30px;border:1px solid var(--ds-border-dark);border-radius:999px;background:transparent;color:var(--ds-muted-light);font:600 10px var(--ds-font-main)}.support-availability button.active{background:#fff;color:var(--ds-ink)}.support-availability button:first-child svg{color:var(--ds-emerald)}.support-availability button:nth-child(2) svg{color:var(--ds-amber)}.support-mini-metrics{display:flex;align-items:center;justify-content:center;gap:8px;color:var(--ds-muted-light);font-size:9px}.support-mini-metrics strong{color:#fff;font-size:11px}.support-mini-metrics i{width:3px;height:3px;border-radius:50%;background:var(--ds-muted)}.support-search{display:flex;align-items:center;gap:9px;background:var(--ds-surface);border:1px solid var(--ds-border);border-radius:16px;padding:0 14px;box-shadow:var(--ds-shadow-tile)}.support-search svg{color:var(--ds-muted)}.support-search input{width:100%;height:48px;border:0;outline:0;background:transparent;font:500 14px var(--ds-font-main);color:var(--ds-ink)}.support-tabs{display:flex;gap:6px;overflow:auto;margin:12px 0 16px;padding:4px}.support-tabs button{white-space:nowrap;border:0;border-radius:999px;padding:9px 12px;background:transparent;color:var(--ds-muted);font:700 12px var(--ds-font-main)}.support-tabs button.active{background:var(--ds-primary);color:#fff}.support-tabs span{display:inline-grid;place-items:center;min-width:18px;height:18px;margin-left:3px;border-radius:999px;background:rgba(127,127,127,.15);font-size:10px}.support-list{display:grid;gap:10px}.support-card{position:relative;display:flex;text-align:left;gap:12px;width:100%;border:1px solid var(--ds-border);padding:14px;border-radius:20px;background:var(--ds-surface);box-shadow:var(--ds-shadow-bento);color:var(--ds-ink)}.support-avatar{position:relative;display:grid;flex:0 0 48px;width:48px;height:48px;place-items:center;border-radius:17px;background:var(--ds-indigo-soft);color:var(--ds-indigo-ink);font-weight:800;overflow:visible}.support-avatar img{width:100%;height:100%;object-fit:cover;border-radius:inherit}.support-avatar i{position:absolute;right:-2px;bottom:-2px;width:13px;height:13px;border:3px solid #fff;border-radius:50%;background:var(--ds-muted-light)}.support-avatar i.is-acik{background:var(--ds-emerald)}.support-avatar i.is-beklemede{background:var(--ds-amber)}.support-card__body{display:flex;flex:1;min-width:0;flex-direction:column}.support-card__heading{display:flex;justify-content:space-between;gap:8px}.support-card__heading strong{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:15px}.support-card__heading time{font-size:10px;color:var(--ds-muted)}.support-card__subject{font-size:11px;font-weight:700;color:var(--ds-indigo-ink);margin-top:2px}.support-card__preview{font-size:12px;color:var(--ds-muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin-top:4px}.support-card__meta{display:flex;align-items:center;gap:8px;margin-top:8px}.support-card__meta em{font-style:normal;font-size:9px;font-weight:800;text-transform:uppercase;padding:4px 7px;border-radius:999px;background:var(--ds-blue-soft);color:var(--ds-blue-ink)}.support-card__meta em.status-beklemede{background:var(--ds-amber-soft);color:var(--ds-amber-ink)}.support-card__meta em.status-kapali,.support-card__meta em.status-cozuldu{background:var(--ds-surface-subtle);color:var(--ds-muted)}.support-card__meta small{font-size:10px;color:var(--ds-muted)}.support-unread{position:absolute;right:13px;bottom:13px;display:grid;place-items:center;min-width:21px;height:21px;padding:0 5px;border-radius:999px;background:var(--ds-coral);color:#fff;font-size:10px;font-weight:800}.support-state{min-height:190px;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:9px;color:var(--ds-muted);text-align:center}.support-state--error button{border:0;border-radius:12px;padding:10px 14px;background:var(--ds-primary);color:#fff}.support-empty-icon{display:grid;place-items:center;width:52px;height:52px;border-radius:18px;background:var(--ds-surface);box-shadow:var(--ds-shadow-tile)}.support-chat{position:fixed;inset:0;z-index:1030;display:flex;flex-direction:column;background:var(--ds-canvas);font-family:var(--ds-font-main)}.support-chat__header{display:flex;align-items:center;gap:10px;padding:calc(var(--ds-safe-top) + 10px) 14px 10px;background:var(--ds-surface);border-bottom:1px solid var(--ds-line)}.support-chat__header>button{display:grid;place-items:center;width:42px;height:42px;border:0;border-radius:14px;background:var(--ds-surface-subtle);color:var(--ds-ink)}.support-avatar--small{width:40px;height:40px;flex-basis:40px;border-radius:14px;font-size:12px}.support-chat__header>div:nth-of-type(1){flex:1;min-width:0}.support-chat__header strong,.support-chat__header span{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.support-chat__header strong{font-size:14px}.support-chat__header span{font-size:10px;color:var(--ds-muted)}.support-status-menu{position:relative}.support-status-menu>button{display:flex;align-items:center;gap:4px;border:0;border-radius:999px;padding:9px;background:var(--ds-emerald-soft);color:var(--ds-emerald-ink);font:700 10px var(--ds-font-main)}.support-status-menu>div{position:absolute;right:0;top:42px;z-index:4;width:150px;padding:6px;border-radius:16px;background:var(--ds-surface);box-shadow:var(--ds-shadow-float)}.support-status-menu>div button{display:flex;align-items:center;gap:7px;width:100%;min-height:38px;border:0;border-radius:10px;background:transparent;padding:8px;color:var(--ds-ink);font:600 12px var(--ds-font-main)}.support-chat__subject{display:flex;align-items:center;gap:7px;padding:9px 16px;background:var(--ds-surface);border-bottom:1px solid var(--ds-line);font-size:11px;color:var(--ds-muted)}.support-chat__subject span{flex:1}.support-chat__subject em{font-style:normal;color:var(--ds-emerald-ink)}.support-messages{flex:1;overflow:auto;padding:18px 14px;overscroll-behavior:contain}.support-message{display:flex;margin-bottom:10px}.support-message.is-yonetici{justify-content:flex-end}.support-message.is-sistem{justify-content:center}.support-bubble{max-width:82%;padding:10px 12px;border-radius:18px 18px 18px 5px;background:var(--ds-surface);box-shadow:var(--ds-shadow-tile)}.is-yonetici .support-bubble{border-radius:18px 18px 5px 18px;background:var(--ds-primary);color:#fff}.support-bubble img{display:block;max-width:100%;max-height:260px;border-radius:12px;margin-bottom:7px;object-fit:cover}.support-bubble p{margin:0;white-space:pre-wrap;overflow-wrap:anywhere;font-size:14px;line-height:1.42}.support-bubble>span{display:flex;justify-content:flex-end;align-items:center;gap:3px;margin-top:5px;font-size:9px;color:var(--ds-muted)}.is-yonetici .support-bubble>span{color:var(--ds-muted-light)}.support-bubble svg.read{color:var(--ds-blue)}.support-system-message{padding:7px 11px;border-radius:999px;background:rgba(100,116,139,.12);color:var(--ds-muted);font-size:10px;text-align:center}.support-composer{padding:8px 12px calc(8px + var(--ds-safe-bottom));background:var(--ds-surface);border-top:1px solid var(--ds-line)}.support-composer form{display:flex;align-items:flex-end;gap:7px}.support-composer form button{display:grid;place-items:center;flex:0 0 44px;width:44px;height:44px;border:0;border-radius:15px;background:var(--ds-surface-subtle);color:var(--ds-muted)}.support-composer form button.send{background:var(--ds-primary);color:#fff}.support-composer form button:disabled{opacity:.45}.support-composer textarea{flex:1;min-height:44px;max-height:104px;resize:none;border:1px solid var(--ds-line);border-radius:16px;background:var(--ds-surface-subtle);padding:11px 13px;outline:0;font:500 14px var(--ds-font-main);color:var(--ds-ink)}.support-image-preview{position:relative;width:74px;margin:0 0 7px 51px}.support-image-preview img{display:block;width:74px;height:58px;object-fit:cover;border-radius:12px}.support-image-preview button{position:absolute;right:-6px;top:-6px;display:grid;place-items:center;width:22px;height:22px;border:0;border-radius:50%;background:var(--ds-primary);color:#fff}.support-closed-note{text-align:center;padding:10px;border-radius:13px;background:var(--ds-amber-soft);color:var(--ds-amber-ink);font-size:12px}.support-toast{position:fixed;z-index:1100;left:50%;bottom:calc(92px + var(--ds-safe-bottom));transform:translateX(-50%);max-width:calc(100vw - 32px);padding:11px 16px;border-radius:999px;background:var(--ds-primary);color:#fff;box-shadow:var(--ds-shadow-float);font-size:12px;white-space:nowrap}.support-settings-layer{position:fixed;inset:0;z-index:1080;display:flex;align-items:flex-end;background:var(--ds-overlay);font-family:var(--ds-font-main)}.support-settings-sheet{display:flex;flex-direction:column;width:100%;max-height:94dvh;border-radius:28px 28px 0 0;background:var(--ds-canvas);overflow:hidden}.support-settings-sheet>header{display:flex;justify-content:space-between;align-items:center;padding:18px 20px 14px;background:var(--ds-surface);border-bottom:1px solid var(--ds-line)}.support-settings-sheet>header span{font-size:9px;font-weight:800;letter-spacing:.12em;color:var(--ds-indigo-ink)}.support-settings-sheet>header h2{margin:2px 0 0;font-size:22px;color:var(--ds-ink)}.support-settings-sheet>header p{margin:2px 0 0;font-size:11px;color:var(--ds-muted)}.support-settings-sheet>header button{display:grid;place-items:center;width:42px;height:42px;border:0;border-radius:14px;background:var(--ds-surface-subtle);color:var(--ds-ink)}.support-settings-body{display:grid;gap:12px;padding:14px 16px;overflow:auto}.support-setting-card{border:1px solid var(--ds-border);border-radius:20px;background:var(--ds-surface);padding:14px;box-shadow:var(--ds-shadow-tile)}.support-setting-toggle,.support-setting-heading{display:flex;align-items:center;gap:11px}.support-setting-toggle>div,.support-setting-heading>div{flex:1;min-width:0}.support-setting-card strong{display:block;font-size:14px;color:var(--ds-ink)}.support-setting-card p{margin:3px 0 0;font-size:11px;line-height:1.35;color:var(--ds-muted)}.support-setting-icon{display:grid;place-items:center;flex:0 0 40px;width:40px;height:40px;border-radius:14px}.support-setting-icon.is-blue{background:var(--ds-blue-soft);color:var(--ds-blue-ink)}.support-setting-icon.is-amber{background:var(--ds-amber-soft);color:var(--ds-amber-ink)}.support-setting-icon.is-green{background:var(--ds-emerald-soft);color:var(--ds-emerald-ink)}.support-setting-toggle>button{position:relative;width:48px;height:28px;border:0;border-radius:999px;background:#cbd5e1;padding:3px;transition:.2s}.support-setting-toggle>button span{display:block;width:22px;height:22px;border-radius:50%;background:#fff;box-shadow:0 2px 5px rgba(15,23,42,.18);transition:.2s}.support-setting-toggle>button.active{background:var(--ds-emerald)}.support-setting-toggle>button.active span{transform:translateX(20px)}.support-day-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:4px;margin-top:14px}.support-day-grid button{min-width:0;height:34px;border:1px solid var(--ds-line);border-radius:10px;background:var(--ds-surface-subtle);color:var(--ds-muted);font:700 10px var(--ds-font-main)}.support-day-grid button.active{background:var(--ds-primary);color:#fff}.support-time-grid{display:grid;grid-template-columns:1fr 1fr;gap:9px;margin-top:10px}.support-time-grid label span{display:block;margin:0 0 5px 3px;font-size:10px;font-weight:700;color:var(--ds-muted)}.support-time-grid input{box-sizing:border-box;width:100%;height:44px;border:1px solid var(--ds-line);border-radius:13px;background:var(--ds-surface-subtle);padding:0 11px;color:var(--ds-ink);font:700 14px var(--ds-font-main)}.support-user-search{display:flex;align-items:center;gap:7px;margin:12px 0 8px;padding:0 11px;border-radius:13px;background:var(--ds-surface-subtle);color:var(--ds-muted)}.support-user-search input{width:100%;height:40px;border:0;outline:0;background:transparent;font:500 13px var(--ds-font-main)}.support-user-list{display:grid;gap:4px;max-height:220px;overflow:auto}.support-user-list>button{display:flex;align-items:center;gap:9px;width:100%;min-height:48px;border:0;border-radius:13px;background:transparent;padding:5px 7px;text-align:left}.support-user-list>button>span{display:grid;place-items:center;width:34px;height:34px;border-radius:11px;background:var(--ds-indigo-soft);color:var(--ds-indigo-ink);font-size:11px;font-weight:800}.support-user-list>button>div{flex:1}.support-user-list strong{font-size:12px}.support-user-list small{display:block;color:var(--ds-muted);font-size:10px}.support-user-list i{display:grid;place-items:center;width:22px;height:22px;border:1px solid var(--ds-line);border-radius:7px;color:transparent}.support-user-list>button.selected{background:var(--ds-emerald-soft)}.support-user-list>button.selected i{border-color:var(--ds-emerald);background:var(--ds-emerald);color:#fff}.support-settings-sheet>footer{padding:10px 16px calc(10px + var(--ds-safe-bottom));background:var(--ds-surface);border-top:1px solid var(--ds-line)}.support-settings-sheet>footer button{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;min-height:48px;border:0;border-radius:15px;background:var(--ds-primary);color:#fff;font:700 14px var(--ds-font-main)}.support-settings-sheet>footer button:disabled{opacity:.55}.spin{animation:spin .8s linear infinite}@keyframes spin{to{transform:rotate(360deg)}}.toast-enter-active,.toast-leave-active,.settings-sheet-enter-active,.settings-sheet-leave-active{transition:.2s}.toast-enter-from,.toast-leave-to{opacity:0;transform:translate(-50%,8px)}.settings-sheet-enter-from,.settings-sheet-leave-to{opacity:0}.settings-sheet-enter-from .support-settings-sheet,.settings-sheet-leave-to .support-settings-sheet{transform:translateY(24px)}
</style>
