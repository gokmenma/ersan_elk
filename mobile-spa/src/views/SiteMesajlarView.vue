<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowLeft, Check, Inbox, LoaderCircle, Mail, Phone, RefreshCw, Search, X } from '@lucide/vue'
import { auth } from '../services/auth.js'
import { siteMesajApi } from '../services/site-mesajlar.js'

const route = useRoute()
const router = useRouter()
const items = ref([])
const summary = ref({ toplam: 0, yeni: 0, okundu: 0, cevaplandi: 0 })
const loading = ref(true)
const refreshing = ref(false)
const error = ref('')
const search = ref('')
const status = ref('all')
const active = ref(null)
const detailLoading = ref(false)
const note = ref('')
const saving = ref(false)
const toast = ref('')
let searchTimer = null
let toastTimer = null

const STATUS_LABELS = { yeni: 'Yeni', okundu: 'Okundu', cevaplandi: 'Cevaplandı' }
const tabs = computed(() => [
  { value: 'all', label: 'Tümü', count: summary.value.toplam },
  { value: 'yeni', label: 'Yeni', count: summary.value.yeni },
  { value: 'okundu', label: 'Okundu', count: summary.value.okundu },
  { value: 'cevaplandi', label: 'Cevaplandı', count: summary.value.cevaplandi },
])

function showToast(message) {
  toast.value = message
  clearTimeout(toastTimer)
  toastTimer = window.setTimeout(() => { toast.value = '' }, 2600)
}

function dateLabel(value) {
  if (!value) return ''
  const date = new Date(String(value).replace(' ', 'T'))
  if (Number.isNaN(date.getTime())) return value
  return date.toLocaleString('tr-TR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function initials(name) {
  return String(name || '?').split(/\s+/).filter(Boolean).slice(0, 2).map(part => part[0]?.toLocaleUpperCase('tr-TR')).join('') || '?'
}

async function loadList(silent = false) {
  if (silent) refreshing.value = true
  else loading.value = true
  error.value = ''
  try {
    const res = await siteMesajApi.list({ status: status.value, search: search.value.trim() })
    items.value = res.items || []
    summary.value = res.summary || summary.value
  } catch (err) {
    error.value = err.message
  } finally {
    loading.value = false
    refreshing.value = false
  }
}

async function openMessage(id) {
  detailLoading.value = true
  active.value = { id }
  note.value = ''
  try {
    const res = await siteMesajApi.detail(id)
    active.value = res.item
    note.value = res.item.note || ''
    if (res.item.status === 'yeni') await changeStatus('okundu', true)
  } catch (err) {
    active.value = null
    showToast(err.message)
  } finally {
    detailLoading.value = false
  }
}

async function changeStatus(next, silent = false) {
  if (!active.value?.id || saving.value) return
  saving.value = true
  try {
    await siteMesajApi.updateStatus(active.value.id, next, note.value, auth.state.csrfToken)
    active.value.status = next
    active.value.note = note.value
    if (!silent) showToast('Mesaj güncellendi.')
    await loadList(true)
  } catch (err) {
    showToast(err.message)
  } finally {
    saving.value = false
  }
}

function closeMessage() {
  active.value = null
  if (route.query.id) router.replace({ path: route.path })
}

watch([status, search], () => {
  clearTimeout(searchTimer)
  searchTimer = window.setTimeout(() => loadList(), search.value ? 300 : 0)
})

onMounted(async () => {
  await loadList()
  const id = String(route.query.id || '')
  if (id) openMessage(id)
})
onBeforeUnmount(() => {
  clearTimeout(searchTimer)
  clearTimeout(toastTimer)
})
</script>

<template>
  <section class="ds-screen sm-screen">
    <header class="clay-topbar">
      <div class="clay-topbar-main">
        <button class="clay-circle-btn" type="button" aria-label="Geri" @click="router.back()"><ArrowLeft :size="19" /></button>
        <div class="clay-topbar-title-block">
          <span class="clay-topbar-eyebrow">KURUMSAL SİTE</span>
          <h1 class="clay-topbar-title">Site Mesajları</h1>
          <p class="clay-topbar-subtitle">Siteden gelen iletişim mesajları</p>
        </div>
      </div>
      <button class="clay-circle-btn" type="button" aria-label="Yenile" :disabled="refreshing" @click="loadList(true)"><RefreshCw :size="18" :class="{ spin: refreshing }" /></button>
    </header>

    <div class="sm-search"><Search :size="18" /><input v-model="search" type="search" placeholder="Ad, konu veya mesaj ara" aria-label="Site mesajlarında ara"></div>
    <div class="clay-tabs-wrap sm-tabs">
      <button v-for="tab in tabs" :key="tab.value" type="button" :class="{ active: status === tab.value }" @click="status = tab.value">{{ tab.label }} <span>{{ tab.count }}</span></button>
    </div>

    <div v-if="loading" class="sm-state"><LoaderCircle class="spin" :size="28" /><span>Mesajlar yükleniyor…</span></div>
    <div v-else-if="error" class="sm-state"><p>{{ error }}</p><button type="button" class="sm-retry" @click="loadList()">Tekrar dene</button></div>
    <div v-else-if="!items.length" class="sm-state"><span class="sm-empty-icon"><Inbox :size="26" /></span><strong>Mesaj bulunamadı</strong><span>Filtrelerinize uygun mesaj yok.</span></div>
    <div v-else class="sm-list">
      <button v-for="item in items" :key="item.id" type="button" class="sm-card bento-card" @click="openMessage(item.id)">
        <span class="sm-avatar">{{ initials(item.name) }}</span>
        <span class="sm-card__body">
          <span class="sm-card__heading"><strong>{{ item.name }}</strong><time>{{ dateLabel(item.created_at) }}</time></span>
          <span v-if="item.subject" class="sm-card__subject">{{ item.subject }}</span>
          <span class="sm-card__preview">{{ item.preview }}</span>
          <span class="sm-card__meta"><em :class="`status-${item.status}`">{{ STATUS_LABELS[item.status] || item.status }}</em></span>
        </span>
      </button>
    </div>

    <div v-if="active" class="sm-layer" @click.self="closeMessage">
      <div class="sm-sheet">
        <header>
          <div>
            <span>SİTE MESAJI</span>
            <h2>{{ active.name || 'Mesaj' }}</h2>
            <p v-if="active.created_at">{{ dateLabel(active.created_at) }}</p>
          </div>
          <button type="button" aria-label="Kapat" @click="closeMessage"><X :size="20" /></button>
        </header>
        <div v-if="detailLoading" class="sm-state"><LoaderCircle class="spin" :size="26" /></div>
        <div v-else class="sm-body">
          <div class="sm-contact">
            <a v-if="active.phone" :href="`tel:${active.phone}`"><Phone :size="16" /> {{ active.phone }}</a>
            <a v-if="active.email" :href="`mailto:${active.email}`"><Mail :size="16" /> {{ active.email }}</a>
          </div>
          <div v-if="active.subject" class="sm-subject">{{ active.subject }}</div>
          <p class="sm-message">{{ active.message }}</p>
          <label class="sm-note">
            <span>Yönetici notu</span>
            <textarea v-model="note" rows="3" maxlength="2000" placeholder="İç not (siteye gösterilmez)"></textarea>
          </label>
          <div class="sm-actions">
            <button v-for="(label, key) in STATUS_LABELS" :key="key" type="button" :class="{ active: active.status === key }" :disabled="saving" @click="changeStatus(key)">
              <Check v-if="active.status === key" :size="14" />{{ label }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <Transition name="toast"><div v-if="toast" class="sm-toast" role="status">{{ toast }}</div></Transition>
  </section>
</template>

<style scoped>
.sm-screen { padding-left: 16px; padding-right: 16px; }
.sm-search { display: flex; align-items: center; gap: 9px; background: var(--ds-surface); border: 1px solid var(--ds-border); border-radius: 16px; padding: 0 14px; box-shadow: var(--ds-shadow-tile); }
.sm-search svg { color: var(--ds-muted); }
.sm-search input { width: 100%; height: 48px; border: 0; outline: 0; background: transparent; font: 500 14px var(--ds-font-main); color: var(--ds-ink); }
.sm-tabs { display: flex; gap: 6px; overflow: auto; margin: 12px 0 16px; padding: 4px; }
.sm-tabs button { white-space: nowrap; min-height: 44px; border: 0; border-radius: 999px; padding: 9px 12px; background: transparent; color: var(--ds-muted); font: 700 12px var(--ds-font-main); }
.sm-tabs button.active { background: var(--ds-primary); color: #fff; }
.sm-tabs span { display: inline-grid; place-items: center; min-width: 18px; height: 18px; margin-left: 3px; border-radius: 999px; background: rgba(127, 127, 127, .15); font-size: 10px; }
.sm-list { display: grid; gap: 10px; }
.sm-card { display: flex; text-align: left; gap: 12px; width: 100%; border: 1px solid var(--ds-border); padding: 14px; border-radius: 20px; background: var(--ds-surface); box-shadow: var(--ds-shadow-bento); color: var(--ds-ink); }
.sm-avatar { display: grid; flex: 0 0 46px; width: 46px; height: 46px; place-items: center; border-radius: 16px; background: var(--ds-indigo-soft); color: var(--ds-indigo-ink); font-weight: 800; }
.sm-card__body { display: flex; flex: 1; min-width: 0; flex-direction: column; }
.sm-card__heading { display: flex; justify-content: space-between; gap: 8px; }
.sm-card__heading strong { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 15px; }
.sm-card__heading time { font-size: 10px; color: var(--ds-muted); white-space: nowrap; }
.sm-card__subject { font-size: 11px; font-weight: 700; color: var(--ds-indigo-ink); margin-top: 2px; }
.sm-card__preview { font-size: 12px; color: var(--ds-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; margin-top: 4px; }
.sm-card__meta { margin-top: 8px; }
.sm-card__meta em, .sm-actions button { font-style: normal; }
.sm-card__meta em { font-size: 9px; font-weight: 800; text-transform: uppercase; padding: 4px 7px; border-radius: 999px; background: var(--ds-blue-soft); color: var(--ds-blue-ink); }
.sm-card__meta em.status-okundu { background: var(--ds-amber-soft); color: var(--ds-amber-ink); }
.sm-card__meta em.status-cevaplandi { background: var(--ds-emerald-soft); color: var(--ds-emerald-ink); }
.sm-state { min-height: 190px; display: flex; align-items: center; justify-content: center; flex-direction: column; gap: 9px; color: var(--ds-muted); text-align: center; }
.sm-retry { min-height: 44px; border: 0; border-radius: 12px; padding: 10px 14px; background: var(--ds-primary); color: #fff; }
.sm-empty-icon { display: grid; place-items: center; width: 52px; height: 52px; border-radius: 18px; background: var(--ds-surface); box-shadow: var(--ds-shadow-tile); }
.sm-layer { position: fixed; inset: 0; z-index: 1080; display: flex; align-items: flex-end; background: var(--ds-overlay); font-family: var(--ds-font-main); }
.sm-sheet { display: flex; flex-direction: column; width: 100%; max-height: 94dvh; border-radius: 28px 28px 0 0; background: var(--ds-canvas); overflow: hidden; }
.sm-sheet > header { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 18px 20px 14px; background: var(--ds-surface); border-bottom: 1px solid var(--ds-line); }
.sm-sheet > header span { font-size: 9px; font-weight: 800; letter-spacing: .12em; color: var(--ds-indigo-ink); }
.sm-sheet > header h2 { margin: 2px 0 0; font-size: 20px; color: var(--ds-ink); }
.sm-sheet > header p { margin: 2px 0 0; font-size: 11px; color: var(--ds-muted); }
.sm-sheet > header button { display: grid; place-items: center; flex: 0 0 44px; width: 44px; height: 44px; border: 0; border-radius: 14px; background: var(--ds-surface-subtle); color: var(--ds-ink); }
.sm-body { display: grid; gap: 12px; padding: 14px 16px calc(16px + var(--ds-safe-bottom)); overflow: auto; }
.sm-contact { display: flex; flex-wrap: wrap; gap: 8px; }
.sm-contact a { display: inline-flex; align-items: center; gap: 6px; min-height: 44px; padding: 0 14px; border-radius: 999px; background: var(--ds-surface); border: 1px solid var(--ds-border); color: var(--ds-ink); font-size: 13px; font-weight: 600; text-decoration: none; }
.sm-subject { font-size: 13px; font-weight: 700; color: var(--ds-indigo-ink); }
.sm-message { margin: 0; padding: 14px; border-radius: 20px; background: var(--ds-surface); box-shadow: var(--ds-shadow-tile); white-space: pre-wrap; overflow-wrap: anywhere; font-size: 14px; line-height: 1.5; color: var(--ds-ink); }
.sm-note span { display: block; margin: 0 0 5px 3px; font-size: 10px; font-weight: 700; color: var(--ds-muted); }
.sm-note textarea { box-sizing: border-box; width: 100%; resize: none; border: 1px solid var(--ds-line); border-radius: 16px; background: var(--ds-surface); padding: 11px 13px; outline: 0; font: 500 14px var(--ds-font-main); color: var(--ds-ink); }
.sm-actions { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; }
.sm-actions button { display: flex; align-items: center; justify-content: center; gap: 4px; min-height: 44px; border: 1px solid var(--ds-line); border-radius: 14px; background: var(--ds-surface); color: var(--ds-muted); font: 700 12px var(--ds-font-main); }
.sm-actions button.active { background: var(--ds-primary); border-color: var(--ds-primary); color: #fff; }
.sm-actions button:disabled { opacity: .55; }
.sm-toast { position: fixed; z-index: 1100; left: 50%; bottom: calc(92px + var(--ds-safe-bottom)); transform: translateX(-50%); max-width: calc(100vw - 32px); padding: 11px 16px; border-radius: 999px; background: var(--ds-primary); color: #fff; box-shadow: var(--ds-shadow-float); font-size: 12px; white-space: nowrap; }
.spin { animation: spin .8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.toast-enter-active, .toast-leave-active { transition: .2s; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translate(-50%, 8px); }
</style>
