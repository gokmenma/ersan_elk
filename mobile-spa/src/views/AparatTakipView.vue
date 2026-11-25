<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { AlertTriangle, ArrowLeft, ArrowRight, Building2, Check, CircleHelp, CircleOff, History, LoaderCircle, Trash2, MapPin, Minus, Package, PackageMinus, PackagePlus, Plus, RefreshCw, Search, Send, Users, X } from '@lucide/vue'
import { auth } from '../services/auth.js'
import { aparatApi } from '../services/aparat-takip.js'
import AparatAnlatim from '../components/AparatAnlatim.vue'

const router = useRouter()
const loading = ref(true)
const refreshing = ref(false)
const error = ref('')
const data = ref(null)
const filter = ref('stocked')
const search = ref('')
const sheet = ref(null)
const teamSearch = ref('')
const teamPickerOpen = ref(false)
const saving = ref(false)
const toast = ref('')
const guideOpen = ref(false)
const GUIDE_KEY = 'aparat_anlatim_izlendi_yonetici'
let toastTimer = null

const MOVES = {
  depo_giris: { title: 'Depoya Giriş', hint: 'Satın alınan aparatı depoya ekleyin.', team: false, source: null, icon: PackagePlus },
  depo_cikis: { title: 'Ekibe Ver', hint: 'Depodan düşer, seçilen ekibin zimmetine geçer.', team: true, source: 'depot', icon: Send },
  depo_iade: { title: 'İade Al', hint: 'Ekibin zimmetinden düşer, depoya geri girer.', team: true, source: 'team', icon: PackageMinus },
}

const summary = computed(() => data.value?.summary || { depot: 0, teams: 0, field: 0, scrap: 0, lost: 0, critical: 0 })
const types = computed(() => data.value?.types || [])
const teams = computed(() => data.value?.teams || [])
const depot = computed(() => data.value?.depot || { counts: {}, critical: {} })
const pools = computed(() => data.value?.pools || { scrap: { counts: {} }, lost: { counts: {} } })
const showPools = computed(() => summary.value.scrap !== 0 || summary.value.lost !== 0)
const poolCards = computed(() => [
  { key: 'scrap', label: 'Hurda', hint: 'Bozuk iade ve kırma kayıtları', icon: Trash2, total: summary.value.scrap, counts: pools.value.scrap.counts },
  { key: 'lost', label: 'Kayıp', hint: 'Sayım farkı ve kayıp aparatlar', icon: CircleOff, total: summary.value.lost, counts: pools.value.lost.counts },
].filter(pool => pool.total !== 0))
const canMove = computed(() => Boolean(data.value?.can_move))

const distribution = computed(() => {
  const total = summary.value.depot + summary.value.teams + summary.value.field
  if (total <= 0) return []
  return [
    { key: 'depot', label: 'Depo', value: summary.value.depot, cls: 'seg-pill--blue' },
    { key: 'teams', label: 'Ekipler', value: summary.value.teams, cls: 'seg-pill--emerald' },
    { key: 'field', label: 'Abonede', value: summary.value.field, cls: 'seg-pill--amber' },
  ].filter(item => item.value > 0).map(item => ({ ...item, width: Math.max(6, (item.value / total) * 100) }))
})

const criticalTeams = computed(() => teams.value.filter(team => Object.keys(team.critical).length))
const depotCritical = computed(() => types.value.filter(type => depot.value.critical[type.id]))

const tabs = computed(() => [
  { value: 'stocked', label: 'Aparatı Olanlar', count: teams.value.filter(team => team.total !== 0).length },
  { value: 'critical', label: 'Azalanlar', count: criticalTeams.value.length },
  { value: 'all', label: 'Tümü', count: teams.value.length },
])

const visibleTeams = computed(() => {
  const term = search.value.trim().toLocaleLowerCase('tr-TR')
  return teams.value
    .filter(team => {
      if (filter.value === 'stocked') return team.total !== 0
      if (filter.value === 'critical') return Object.keys(team.critical).length > 0
      return true
    })
    .filter(team => !term || `${team.name} ${team.code} ${team.region}`.toLocaleLowerCase('tr-TR').includes(term))
    .sort((a, b) => b.total - a.total || a.name.localeCompare(b.name, 'tr'))
})

const sheetMove = computed(() => (sheet.value ? MOVES[sheet.value.type] : null))
const selectedTeam = computed(() => teams.value.find(item => item.id === sheet.value?.teamId) || null)
const selectedType = computed(() => types.value.find(item => item.id === sheet.value?.typeId) || null)
const quantity = computed(() => Math.max(0, parseInt(sheet.value?.quantity, 10) || 0))

function sourceCount(typeId) {
  if (!sheet.value) return null
  if (sheetMove.value.source === 'team') {
    return selectedTeam.value ? Number(selectedTeam.value.counts[typeId] || 0) : null
  }
  return Number(depot.value.counts[typeId] || 0)
}

const sourceLabel = computed(() => (sheetMove.value?.source === 'team' ? 'ekipte' : 'depoda'))
const available = computed(() => (sheetMove.value?.source && sheet.value?.typeId ? sourceCount(sheet.value.typeId) : null))
const overLimit = computed(() => available.value !== null && quantity.value > Math.max(0, available.value))

const pickerTeams = computed(() => {
  if (!sheet.value) return []
  const typeId = sheet.value.typeId
  const term = teamSearch.value.trim().toLocaleLowerCase('tr-TR')
  let list = teams.value.filter(team => team.staffed || team.total)
  if (sheet.value.type === 'depo_iade') list = list.filter(team => Number(team.counts[typeId] || 0) > 0)
  if (term) list = list.filter(team => `${team.name} ${team.code}`.toLocaleLowerCase('tr-TR').includes(term))
  return [...list].sort((a, b) => (Number(b.counts[typeId] || 0) - Number(a.counts[typeId] || 0)) || a.name.localeCompare(b.name, 'tr'))
})

const preview = computed(() => {
  if (!sheet.value || !selectedType.value || quantity.value < 1) return []
  const id = selectedType.value.id
  const q = quantity.value
  const depotNow = Number(depot.value.counts[id] || 0)
  if (sheet.value.type === 'depo_giris') return [{ label: 'Depo', before: depotNow, after: depotNow + q }]
  if (!selectedTeam.value) return []
  const teamNow = Number(selectedTeam.value.counts[id] || 0)
  const teamRow = { label: selectedTeam.value.name, before: teamNow }
  if (sheet.value.type === 'depo_cikis') {
    return [{ label: 'Depo', before: depotNow, after: depotNow - q }, { ...teamRow, after: teamNow + q }]
  }
  if (sheet.value.broken) {
    return [{ ...teamRow, after: teamNow - q }, { label: 'Hurda', before: null, after: `+${q}`, scrap: true }]
  }
  return [{ ...teamRow, after: teamNow - q }, { label: 'Depo', before: depotNow, after: depotNow + q }]
})

const canSubmit = computed(() => Boolean(
  sheet.value && sheet.value.typeId && quantity.value >= 1 && !overLimit.value && !saving.value
  && (!sheetMove.value.team || sheet.value.teamId),
))

function initials(name) {
  return String(name || '?').split(/\s+/).filter(Boolean).slice(0, 2).map(part => part[0]?.toLocaleUpperCase('tr-TR')).join('') || '?'
}

function chipClass(level, count) {
  if (count < 0 || level === 'bitti') return 'at-chip--danger'
  if (level === 'azaldi') return 'at-chip--warn'
  if (count === 0) return 'at-chip--zero'
  return ''
}

function dateLabel(value) {
  if (!value) return ''
  const date = new Date(String(value).replace(' ', 'T'))
  if (Number.isNaN(date.getTime())) return value
  return date.toLocaleString('tr-TR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })
}

function showToast(message) {
  toast.value = message
  clearTimeout(toastTimer)
  toastTimer = window.setTimeout(() => { toast.value = '' }, 2600)
}

async function load(silent = false) {
  if (silent) refreshing.value = true
  else loading.value = true
  error.value = ''
  try {
    data.value = await aparatApi.overview()
  } catch (err) {
    error.value = err.message
  } finally {
    loading.value = false
    refreshing.value = false
  }
}

function openSheet(type, team = null) {
  if (!canMove.value) return
  const firstStocked = types.value.find(item => Number(depot.value.counts[item.id] || 0) > 0)
  const typeId = team
    ? (types.value.find(item => Number(team.counts[item.id] || 0) > 0) || firstStocked || types.value[0])?.id
    : (firstStocked || types.value[0])?.id
  sheet.value = { type, teamId: team?.id || '', typeId: typeId || '', quantity: 1, note: '', broken: false }
  teamSearch.value = ''
  teamPickerOpen.value = !team && MOVES[type].team
}

function setMove(type) {
  sheet.value.type = type
  if (type === 'depo_iade' && selectedTeam.value && Number(selectedTeam.value.counts[sheet.value.typeId] || 0) <= 0) {
    const elde = types.value.find(item => Number(selectedTeam.value.counts[item.id] || 0) > 0)
    if (elde) sheet.value.typeId = elde.id
    else sheet.value.teamId = ''
  }
  if (MOVES[type].team && !sheet.value.teamId) teamPickerOpen.value = true
}

function pickTeam(team) {
  sheet.value.teamId = team.id
  if (sheet.value.type === 'depo_iade' && Number(team.counts[sheet.value.typeId] || 0) <= 0) {
    const elde = types.value.find(item => Number(team.counts[item.id] || 0) > 0)
    if (elde) sheet.value.typeId = elde.id
  }
  teamPickerOpen.value = false
  teamSearch.value = ''
}

function closeSheet() {
  if (!saving.value) sheet.value = null
}

function step(delta) {
  sheet.value.quantity = Math.max(1, quantity.value + delta)
}

function setQuantity(value) {
  sheet.value.quantity = Math.max(1, value)
}

async function submit() {
  const form = sheet.value
  if (!form || saving.value) return
  if (overLimit.value) return showToast(`En fazla ${Math.max(0, available.value)} adet girebilirsiniz.`)
  if (sheetMove.value.team && !form.teamId) return showToast('Ekip seçin.')
  if (!form.typeId) return showToast('Aparat seçin.')
  const quantity = Number(form.quantity)
  if (!Number.isInteger(quantity) || quantity < 1) return showToast('Adet en az 1 olmalı.')
  saving.value = true
  try {
    const res = await aparatApi.move({
      type: form.type === 'depo_iade' && form.broken ? 'depo_iade_hurda' : form.type,
      team_id: form.teamId,
      type_id: form.typeId,
      quantity,
      note: form.note,
    }, auth.state.csrfToken)
    sheet.value = null
    showToast(res.message || 'Kaydedildi.')
    await load(true)
  } catch (err) {
    showToast(err.message)
  } finally {
    saving.value = false
  }
}

onMounted(async () => { await load(); maybeAutoGuide() })
function openGuide() {
  guideOpen.value = true
  try { localStorage.setItem(GUIDE_KEY, '1') } catch { /* depolama kapalı */ }
}

function maybeAutoGuide() {
  let seen = true
  try { seen = localStorage.getItem(GUIDE_KEY) === '1' } catch { /* depolama kapalı */ }
  if (!seen && canMove.value) openGuide()
}

onBeforeUnmount(() => clearTimeout(toastTimer))
</script>

<template>
  <section class="ds-screen at-screen">
    <header class="clay-topbar">
      <div class="clay-topbar-main">
        <button class="clay-circle-btn" type="button" aria-label="Geri" @click="router.back()"><ArrowLeft :size="19" /></button>
        <div class="clay-topbar-title-block">
          <span class="clay-topbar-eyebrow">SAHA ENVANTERİ</span>
          <h1 class="clay-topbar-title">Aparat Takip</h1>
          <p class="clay-topbar-subtitle">Depo, ekip zimmeti ve abonede takılı aparatlar</p>
        </div>
      </div>
      <div class="at-topbar-actions">
        <button class="clay-circle-btn" type="button" aria-label="Aparat nasıl takip edilir?" @click="openGuide"><CircleHelp :size="18" /></button>
        <button class="clay-circle-btn" type="button" aria-label="Yenile" :disabled="refreshing" @click="load(true)"><RefreshCw :size="18" :class="{ spin: refreshing }" /></button>
      </div>
    </header>

    <div v-if="loading" class="at-state"><LoaderCircle class="spin" :size="28" /><span>Aparat verileri yükleniyor…</span></div>
    <div v-else-if="error" class="at-state"><p>{{ error }}</p><button type="button" class="at-retry" @click="load()">Tekrar dene</button></div>

    <template v-else>
      <article class="bento-card bento-card--dark at-hero">
        <span class="at-hero__eyebrow">DEPODA</span>
        <strong class="at-hero__value">{{ summary.depot }} <small>adet</small></strong>
        <div v-if="distribution.length" class="segmented-track segmented-track--dark at-track">
          <span v-for="item in distribution" :key="item.key" class="seg-pill" :class="item.cls" :style="{ width: item.width + '%' }"></span>
        </div>
        <div class="at-hero__grid">
          <div><Users :size="16" /><strong>{{ summary.teams }}</strong><span>Ekiplerde</span></div>
          <div><MapPin :size="16" /><strong>{{ summary.field }}</strong><span>Abonede</span></div>
          <div :class="{ 'is-alert': summary.critical > 0 }"><AlertTriangle :size="16" /><strong>{{ summary.critical }}</strong><span>Azalan</span></div>
        </div>
        <div v-if="showPools" class="at-hero__pools">
          <span><Trash2 :size="14" /> Hurda <b>{{ summary.scrap }}</b></span>
          <span :class="{ 'is-alert': summary.lost !== 0 }"><CircleOff :size="14" /> Kayıp <b>{{ summary.lost }}</b></span>
        </div>
      </article>

      <div v-if="canMove" class="at-actions">
        <button v-for="(move, key) in MOVES" :key="key" type="button" :class="{ primary: key === 'depo_giris' }" @click="openSheet(key)">
          <component :is="move.icon" :size="18" />
          <span>{{ move.title }}</span>
        </button>
      </div>

      <article v-if="depotCritical.length || criticalTeams.length" class="at-alert">
        <header><AlertTriangle :size="17" /><strong>Azalan aparatlar</strong></header>
        <div v-if="depotCritical.length" class="at-alert__row">
          <span class="at-alert__who"><Building2 :size="14" /> Depo</span>
          <span class="at-chips">
            <em v-for="type in depotCritical" :key="type.id" class="at-chip" :class="chipClass(depot.critical[type.id], depot.counts[type.id])">{{ type.name }} {{ depot.counts[type.id] }}/{{ type.depot_limit }}</em>
          </span>
        </div>
        <div v-for="team in criticalTeams" :key="team.id" class="at-alert__row">
          <span class="at-alert__who"><Users :size="14" /> {{ team.name }}</span>
          <span class="at-chips">
            <em v-for="type in types.filter(item => team.critical[item.id])" :key="type.id" class="at-chip" :class="chipClass(team.critical[type.id], team.counts[type.id])">{{ type.name }} {{ team.counts[type.id] }}/{{ type.team_limit }}</em>
          </span>
        </div>
      </article>

      <div class="at-search"><Search :size="18" /><input v-model="search" type="search" placeholder="Personel veya ekip ara" aria-label="Ekiplerde ara"></div>
      <div class="clay-tabs-wrap at-tabs">
        <button v-for="tab in tabs" :key="tab.value" type="button" :class="{ active: filter === tab.value }" @click="filter = tab.value">{{ tab.label }} <span>{{ tab.count }}</span></button>
      </div>

      <div class="at-list">
        <article v-for="pool in poolCards" :key="pool.key" class="at-card" :class="`at-card--${pool.key}`">
          <div class="at-card__head">
            <span class="at-card__icon"><component :is="pool.icon" :size="18" /></span>
            <span class="at-card__title"><strong>{{ pool.label }}</strong><small>{{ pool.hint }}</small></span>
            <b>{{ pool.total }}</b>
          </div>
          <div class="at-chips">
            <em v-for="type in types" :key="type.id" class="at-chip" :class="{ 'at-chip--zero': !pool.counts[type.id] }">{{ type.name }} <b>{{ pool.counts[type.id] || 0 }}</b></em>
          </div>
        </article>

        <article class="at-card at-card--depot">
          <div class="at-card__head">
            <span class="at-card__icon"><Building2 :size="18" /></span>
            <span class="at-card__title"><strong>Depo</strong><small>Dağıtılmayı bekleyen</small></span>
            <b>{{ summary.depot }}</b>
          </div>
          <div class="at-chips">
            <em v-for="type in types" :key="type.id" class="at-chip" :class="chipClass(depot.critical[type.id], depot.counts[type.id] || 0)">{{ type.name }} <b>{{ depot.counts[type.id] || 0 }}</b></em>
          </div>
        </article>

        <component :is="canMove ? 'button' : 'article'" v-for="team in visibleTeams" :key="team.id" type="button" class="at-card" @click="canMove && openSheet('depo_cikis', team)">
          <div class="at-card__head">
            <span class="at-card__icon at-card__icon--team"><Users :size="18" /></span>
            <span class="at-card__title"><strong>{{ team.name }}</strong><small>{{ team.code }}<template v-if="team.region"> · {{ team.region }}</template></small></span>
            <b>{{ team.total }}</b>
          </div>
          <div class="at-chips">
            <em v-for="type in types" :key="type.id" class="at-chip" :class="chipClass(team.critical[type.id], team.counts[type.id] || 0)">{{ type.name }} <b>{{ team.counts[type.id] || 0 }}</b></em>
          </div>
        </component>

        <div v-if="!visibleTeams.length" class="at-state at-state--small"><Package :size="24" /><span>Bu filtrede ekip yok.</span></div>
      </div>

      <section v-if="data.movements?.length" class="at-history">
        <h2><History :size="16" /> Son depo hareketleri</h2>
        <div v-for="(move, index) in data.movements" :key="index" class="at-history__row">
          <em :class="`type-${move.type}`">{{ move.label }}</em>
          <span class="at-history__body">
            <strong>{{ move.quantity }} × {{ move.apparatus }}</strong>
            <small>{{ move.team || 'Depo' }} · {{ move.user }} · {{ dateLabel(move.date) }}</small>
          </span>
        </div>
      </section>
    </template>

    <Transition name="sheet">
      <div v-if="sheet" class="at-layer" @click.self="closeSheet">
        <div class="at-sheet" :class="`at-sheet--${sheet.type}`" role="dialog" aria-modal="true" :aria-label="sheetMove.title">
          <span class="at-sheet__handle" aria-hidden="true"></span>
          <header class="at-sheet__head">
            <span class="at-sheet__icon"><component :is="sheetMove.icon" :size="22" /></span>
            <div class="at-sheet__titles">
              <h2>{{ sheetMove.title }}</h2>
              <p>{{ sheetMove.hint }}</p>
            </div>
            <button type="button" class="at-sheet__close" aria-label="Kapat" @click="closeSheet"><X :size="20" /></button>
          </header>

          <div class="at-switch" role="tablist">
            <button v-for="(move, key) in MOVES" :key="key" type="button" role="tab" :aria-selected="sheet.type === key" :class="{ active: sheet.type === key, [`is-${key}`]: true }" @click="setMove(key)">
              <component :is="move.icon" :size="15" />{{ move.title }}
            </button>
          </div>

          <form id="aparatHareketForm" class="at-form" @submit.prevent="submit">
            <section class="at-field">
              <span class="at-field__label"><b>1</b> Hangi aparat?</span>
              <div class="at-types">
                <button v-for="type in types" :key="type.id" type="button" class="at-type" :class="{ active: sheet.typeId === type.id, 'is-empty': sheet.type !== 'depo_giris' && sourceCount(type.id) !== null && sourceCount(type.id) <= 0 }" @click="sheet.typeId = type.id">
                  <Check v-if="sheet.typeId === type.id" class="at-type__check" :size="14" />
                  <strong>{{ type.name }}</strong>
                  <small>
                    <template v-if="sourceCount(type.id) === null">Önce ekip seçin</template>
                    <template v-else>{{ sheet.type === 'depo_giris' ? 'Depoda' : (sheetMove.source === 'team' ? 'Ekipte' : 'Depoda') }} {{ sourceCount(type.id) }}</template>
                  </small>
                </button>
              </div>
            </section>

            <section v-if="sheetMove.team" class="at-field">
              <span class="at-field__label"><b>2</b> {{ sheet.type === 'depo_iade' ? 'Hangi ekip iade ediyor?' : 'Hangi ekibe veriliyor?' }}</span>
              <button v-if="selectedTeam && !teamPickerOpen" type="button" class="at-picked" @click="teamPickerOpen = true">
                <span class="at-avatar">{{ initials(selectedTeam.name) }}</span>
                <span class="at-picked__body">
                  <strong>{{ selectedTeam.name }}</strong>
                  <small>{{ selectedTeam.code }}</small>
                </span>
                <span class="at-picked__change">Değiştir</span>
              </button>
              <div v-else class="at-picker">
                <label class="at-picker__search">
                  <Search :size="17" />
                  <input v-model="teamSearch" type="search" placeholder="Personel veya ekip ara" aria-label="Ekip ara">
                </label>
                <div class="at-picker__list">
                  <button v-for="team in pickerTeams" :key="team.id" type="button" class="at-picker__item" :class="{ active: team.id === sheet.teamId }" @click="pickTeam(team)">
                    <span class="at-avatar">{{ initials(team.name) }}</span>
                    <span class="at-picker__body">
                      <strong>{{ team.name }}</strong>
                      <small>{{ team.code }}</small>
                    </span>
                    <em :class="{ zero: !Number(team.counts[sheet.typeId] || 0) }">{{ team.counts[sheet.typeId] || 0 }}</em>
                  </button>
                  <p v-if="!pickerTeams.length" class="at-picker__empty">
                    {{ sheet.type === 'depo_iade' && !teamSearch ? 'Bu aparattan zimmetinde olan ekip yok.' : 'Eşleşen ekip bulunamadı.' }}
                  </p>
                </div>
              </div>
            </section>

            <section class="at-field">
              <span class="at-field__label"><b>{{ sheetMove.team ? 3 : 2 }}</b> Kaç adet?</span>
              <div class="at-stepper" :class="{ 'is-over': overLimit }">
                <button type="button" aria-label="Azalt" :disabled="quantity <= 1" @click="step(-1)"><Minus :size="20" /></button>
                <input v-model.number="sheet.quantity" type="number" min="1" inputmode="numeric" aria-label="Adet" @focus="$event.target.select()">
                <button type="button" aria-label="Artır" @click="step(1)"><Plus :size="20" /></button>
              </div>
              <div class="at-quick">
                <button v-for="n in [5, 10, 25]" :key="n" type="button" :class="{ active: quantity === n }" @click="setQuantity(n)">{{ n }}</button>
                <button v-if="available > 0" type="button" class="at-quick__all" :class="{ active: quantity === available }" @click="setQuantity(available)">Tümü · {{ available }}</button>
              </div>
              <p v-if="overLimit" class="at-hint at-hint--danger">{{ available > 0 ? `${sourceLabel === 'ekipte' ? 'Ekipte' : 'Depoda'} yalnız ${available} adet var.` : `${sourceLabel === 'ekipte' ? 'Ekipte' : 'Depoda'} bu aparattan hiç yok.` }}</p>
            </section>

            <label v-if="sheet.type === 'depo_iade'" class="at-broken" :class="{ on: sheet.broken }">
              <input v-model="sheet.broken" type="checkbox">
              <span class="at-broken__box"><Check v-if="sheet.broken" :size="15" /></span>
              <span class="at-broken__text">
                <strong>Aparat bozuk / hasarlı</strong>
                <small>Depoya girmez, hurdaya ayrılır.</small>
              </span>
            </label>

            <section v-if="preview.length" class="at-preview">
              <span class="at-preview__title">Kaydedince</span>
              <div v-for="row in preview" :key="row.label" class="at-preview__row">
                <span class="at-preview__who">{{ row.label }}</span>
                <span v-if="row.scrap" class="at-preview__nums"><b class="neg">{{ row.after }}</b></span>
                <span v-else class="at-preview__nums">{{ row.before }} <ArrowRight :size="13" /> <b :class="{ up: row.after > row.before, down: row.after < row.before, neg: row.after < 0 }">{{ row.after }}</b></span>
              </div>
            </section>

            <label class="at-field">
              <span class="at-field__label at-field__label--plain">Not <i>(isteğe bağlı)</i></span>
              <input v-model="sheet.note" class="at-input" type="text" maxlength="255" :placeholder="sheet.type === 'depo_giris' ? 'İrsaliye / fatura no' : 'Kısa açıklama'">
            </label>
          </form>

          <footer class="at-sheet__foot">
            <button class="at-submit" type="submit" form="aparatHareketForm" :disabled="!canSubmit">
              <LoaderCircle v-if="saving" class="spin" :size="18" />
              <component :is="sheetMove.icon" v-else :size="18" />
              <span>{{ saving ? 'Kaydediliyor…' : `${sheet.type === 'depo_iade' && sheet.broken ? 'Bozuk İade · Hurdaya' : sheetMove.title}${quantity > 0 ? ' · ' + quantity + ' adet' : ''}` }}</span>
            </button>
          </footer>
        </div>
      </div>
    </Transition>

    <AparatAnlatim :open="guideOpen" @close="guideOpen = false" />

    <Transition name="toast"><div v-if="toast" class="at-toast" role="status">{{ toast }}</div></Transition>
  </section>
</template>

<style scoped>
.at-screen { padding-left: 16px; padding-right: 16px; font-family: var(--ds-font-main); }
.at-state { min-height: 200px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 9px; color: var(--ds-muted); text-align: center; }
.at-state--small { min-height: 120px; }
.at-retry { min-height: 44px; border: 0; border-radius: 12px; padding: 10px 14px; background: var(--ds-primary); color: #fff; }

.at-hero { padding: 20px; border-radius: var(--ds-radius-card); }
.at-hero__eyebrow { font-size: 10px; font-weight: 800; letter-spacing: .12em; color: var(--ds-on-dark-muted); }
.at-hero__value { display: block; margin-top: 2px; font-size: 38px; line-height: 1.1; color: #fff; font-weight: 800; }
.at-hero__value small { font-size: 14px; font-weight: 600; color: var(--ds-on-dark-muted); }
.at-track { margin: 14px 0 16px; }
.at-hero__grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
.at-hero__grid > div { display: flex; flex-direction: column; gap: 2px; padding: 12px; border-radius: var(--ds-radius-tile); background: var(--ds-on-dark-surface); color: var(--ds-on-dark-muted); }
.at-hero__grid strong { font-size: 20px; color: #fff; }
.at-hero__grid span { font-size: 11px; font-weight: 600; }
.at-hero__grid .is-alert { color: var(--ds-amber); }
.at-hero__grid .is-alert strong { color: var(--ds-amber); }

.at-topbar-actions { display: flex; gap: 8px; }
.at-actions { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin: 14px 0; }
.at-actions button { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 5px; min-height: 64px; border: 1px solid var(--ds-border); border-radius: 18px; background: var(--ds-surface); color: var(--ds-ink); box-shadow: var(--ds-shadow-tile); font: 700 12px var(--ds-font-main); }
.at-actions button.primary { background: var(--ds-primary); border-color: var(--ds-primary); color: #fff; }

.at-alert { margin-bottom: 14px; padding: 14px; border-radius: 20px; background: var(--ds-amber-soft); color: var(--ds-amber-ink); }
.at-alert header { display: flex; align-items: center; gap: 7px; margin-bottom: 8px; font-size: 13px; }
.at-alert__row { display: flex; flex-direction: column; gap: 5px; padding: 8px 0; border-top: 1px solid rgba(0, 0, 0, .06); }
.at-alert__who { display: flex; align-items: center; gap: 5px; font-size: 12px; font-weight: 700; color: var(--ds-ink); }

.at-search { display: flex; align-items: center; gap: 9px; background: var(--ds-surface); border: 1px solid var(--ds-border); border-radius: 16px; padding: 0 14px; box-shadow: var(--ds-shadow-tile); }
.at-search svg { color: var(--ds-muted); }
.at-search input { width: 100%; height: 48px; border: 0; outline: 0; background: transparent; font: 500 14px var(--ds-font-main); color: var(--ds-ink); }
.at-tabs { display: flex; gap: 6px; overflow: auto; margin: 12px 0; padding: 4px; }
.at-tabs button { white-space: nowrap; min-height: 44px; border: 0; border-radius: 999px; padding: 9px 12px; background: transparent; color: var(--ds-muted); font: 700 12px var(--ds-font-main); }
.at-tabs button.active { background: var(--ds-primary); color: #fff; }
.at-tabs span { display: inline-grid; place-items: center; min-width: 18px; height: 18px; margin-left: 3px; border-radius: 999px; background: rgba(127, 127, 127, .15); font-size: 10px; }

.at-list { display: grid; grid-template-columns: minmax(0, 1fr); gap: 10px; }
.at-card { display: block; box-sizing: border-box; width: 100%; min-width: 0; text-align: left; padding: 14px; border: 1px solid var(--ds-border); border-radius: 20px; background: var(--ds-surface); box-shadow: var(--ds-shadow-bento); color: var(--ds-ink); font-family: var(--ds-font-main); }
.at-card--scrap, .at-card--lost { background: var(--ds-danger-soft); border-color: transparent; }
.at-card--scrap .at-card__icon, .at-card--lost .at-card__icon { color: var(--ds-danger-ink); }
.at-hero__pools { display: flex; gap: 18px; margin-top: 14px; padding-top: 12px; border-top: 1px solid var(--ds-border-dark); font-size: 12px; font-weight: 600; color: var(--ds-on-dark-muted); }
.at-hero__pools span { display: inline-flex; align-items: center; gap: 5px; }
.at-hero__pools b { color: #fff; font-size: 14px; }
.at-hero__pools .is-alert, .at-hero__pools .is-alert b { color: var(--ds-amber); }
.at-card--depot { background: var(--ds-blue-soft); border-color: transparent; }
.at-card__head { display: flex; align-items: center; gap: 10px; }
.at-card__icon { display: grid; place-items: center; flex: 0 0 38px; width: 38px; height: 38px; border-radius: 13px; background: var(--ds-surface); color: var(--ds-blue-ink); }
.at-card__icon--team { background: var(--ds-emerald-soft); color: var(--ds-emerald-ink); }
.at-card__title { display: flex; flex: 1; min-width: 0; flex-direction: column; }
.at-card__title strong { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 14px; }
.at-card__title small { font-size: 11px; color: var(--ds-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.at-card__head > b { flex: 0 0 auto; font-size: 20px; }

.at-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; }
.at-alert .at-chips { margin-top: 0; }
.at-chip { font-style: normal; display: inline-flex; align-items: center; gap: 4px; padding: 5px 9px; border-radius: 999px; background: var(--ds-surface-subtle); color: var(--ds-ink); font-size: 11px; font-weight: 600; }
.at-chip b { font-weight: 800; }
.at-chip--zero { color: var(--ds-muted); }
.at-chip--warn { background: var(--ds-amber-soft); color: var(--ds-amber-ink); box-shadow: inset 0 0 0 1px var(--ds-amber); }
.at-chip--danger { background: var(--ds-danger-soft); color: var(--ds-danger-ink); box-shadow: inset 0 0 0 1px var(--ds-danger); }

.at-history { margin-top: 18px; padding: 14px; border-radius: 20px; background: var(--ds-surface); box-shadow: var(--ds-shadow-tile); }
.at-history h2 { display: flex; align-items: center; gap: 6px; margin: 0 0 6px; font-size: 13px; color: var(--ds-ink); }
.at-history__row { display: flex; align-items: center; gap: 10px; padding: 9px 0; border-top: 1px solid var(--ds-line); }
.at-history__row em { flex: 0 0 auto; font-style: normal; font-size: 9px; font-weight: 800; text-transform: uppercase; padding: 4px 7px; border-radius: 999px; background: var(--ds-blue-soft); color: var(--ds-blue-ink); }
.at-history__row em.type-depo_cikis { background: var(--ds-amber-soft); color: var(--ds-amber-ink); }
.at-history__row em.type-depo_iade { background: var(--ds-emerald-soft); color: var(--ds-emerald-ink); }
.at-history__row em.type-depo_iade_hurda { background: var(--ds-danger-soft); color: var(--ds-danger-ink); }
.at-broken { display: flex; align-items: center; gap: 12px; min-height: 56px; padding: 10px 14px; border: 1.5px solid var(--ds-border); border-radius: 16px; background: var(--ds-surface); cursor: pointer; }
.at-broken input { position: absolute; opacity: 0; pointer-events: none; }
.at-broken__box { display: grid; place-items: center; flex: 0 0 24px; width: 24px; height: 24px; border: 2px solid var(--ds-border); border-radius: 8px; color: #fff; }
.at-broken__text { display: flex; flex-direction: column; }
.at-broken__text strong { font-size: 13px; color: var(--ds-ink); }
.at-broken__text small { font-size: 11px; color: var(--ds-muted); }
.at-broken.on { border-color: var(--ds-danger); background: var(--ds-danger-soft); }
.at-broken.on .at-broken__box { background: var(--ds-danger); border-color: var(--ds-danger); }
.at-broken.on strong { color: var(--ds-danger-ink); }
.at-history__body { display: flex; min-width: 0; flex-direction: column; }
.at-history__body strong { font-size: 13px; color: var(--ds-ink); }
.at-history__body small { font-size: 11px; color: var(--ds-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.at-layer { position: fixed; inset: 0; z-index: 1080; display: flex; align-items: flex-end; background: var(--ds-overlay); font-family: var(--ds-font-main); }
.at-sheet { --at-accent: var(--ds-blue); --at-soft: var(--ds-blue-soft); --at-ink: var(--ds-blue-ink); position: relative; display: flex; flex-direction: column; width: 100%; max-height: 94dvh; border-radius: 28px 28px 0 0; background: var(--ds-canvas); overflow: hidden; box-shadow: var(--ds-shadow-float); }
.at-sheet--depo_cikis { --at-accent: var(--ds-amber); --at-soft: var(--ds-amber-soft); --at-ink: var(--ds-amber-ink); }
.at-sheet--depo_iade { --at-accent: var(--ds-emerald); --at-soft: var(--ds-emerald-soft); --at-ink: var(--ds-emerald-ink); }
.at-sheet__handle { position: absolute; top: 8px; left: 50%; width: 40px; height: 4px; margin-left: -20px; border-radius: 999px; background: var(--ds-line); }
.at-sheet__head { display: flex; align-items: center; gap: 12px; padding: 22px 18px 12px; background: var(--ds-surface); }
.at-sheet__icon { display: grid; place-items: center; flex: 0 0 46px; width: 46px; height: 46px; border-radius: 16px; background: var(--at-soft); color: var(--at-ink); transition: background .2s, color .2s; }
.at-sheet__titles { flex: 1; min-width: 0; }
.at-sheet__titles h2 { margin: 0; font-size: 20px; line-height: 1.15; color: var(--ds-ink); }
.at-sheet__titles p { margin: 3px 0 0; font-size: 12px; line-height: 1.35; color: var(--ds-muted); }
.at-sheet__close { display: grid; place-items: center; flex: 0 0 44px; width: 44px; height: 44px; border: 0; border-radius: 14px; background: var(--ds-surface-subtle); color: var(--ds-ink); }

.at-switch { display: grid; grid-template-columns: repeat(3, 1fr); gap: 4px; margin: 0; padding: 4px 14px 14px; background: var(--ds-surface); border-bottom: 1px solid var(--ds-line); }
.at-switch button { display: flex; align-items: center; justify-content: center; gap: 5px; min-height: 44px; border: 1px solid var(--ds-line); border-radius: 14px; background: var(--ds-surface); color: var(--ds-muted); font: 700 12px var(--ds-font-main); transition: background .2s, color .2s, border-color .2s; }
.at-switch button.active { background: var(--at-soft); border-color: var(--at-accent); color: var(--at-ink); }

.at-form { flex: 1; display: grid; grid-template-columns: minmax(0, 1fr); gap: 18px; padding: 16px 16px 8px; overflow-y: auto; overscroll-behavior: contain; }
.at-field { display: block; }
.at-field__label { display: flex; align-items: center; gap: 8px; margin: 0 0 8px 2px; font-size: 13px; font-weight: 800; color: var(--ds-ink); }
.at-field__label b { display: grid; place-items: center; width: 20px; height: 20px; border-radius: 999px; background: var(--at-soft); color: var(--at-ink); font-size: 11px; }
.at-field__label i { font-style: normal; font-weight: 600; color: var(--ds-muted); }

.at-types { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
.at-type { position: relative; display: flex; flex-direction: column; align-items: flex-start; gap: 3px; min-height: 62px; padding: 10px 12px; border: 1.5px solid var(--ds-border); border-radius: 16px; background: var(--ds-surface); text-align: left; color: var(--ds-ink); font-family: var(--ds-font-main); box-shadow: var(--ds-shadow-tile); transition: border-color .15s, background .15s; }
.at-type strong { font-size: 13px; line-height: 1.2; padding-right: 16px; overflow-wrap: anywhere; }
.at-type small { font-size: 11px; font-weight: 600; color: var(--ds-muted); }
.at-type.is-empty:not(.active) { opacity: .55; box-shadow: none; }
.at-type.active { border-color: var(--at-accent); background: var(--at-soft); }
.at-type.active small { color: var(--at-ink); }
.at-type__check { position: absolute; top: 9px; right: 9px; color: var(--at-ink); }

.at-avatar { display: grid; place-items: center; flex: 0 0 38px; width: 38px; height: 38px; border-radius: 13px; background: var(--ds-indigo-soft); color: var(--ds-indigo-ink); font-size: 13px; font-weight: 800; }
.at-picked { display: flex; align-items: center; gap: 10px; width: 100%; min-height: 60px; padding: 10px 12px; border: 1.5px solid var(--at-accent); border-radius: 16px; background: var(--at-soft); text-align: left; font-family: var(--ds-font-main); color: var(--ds-ink); }
.at-picked__body, .at-picker__body { display: flex; flex: 1; min-width: 0; flex-direction: column; }
.at-picked__body strong, .at-picker__body strong { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 14px; }
.at-picked__body small, .at-picker__body small { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 11px; color: var(--ds-muted); }
.at-picked__change { flex: 0 0 auto; padding: 6px 10px; border-radius: 999px; background: var(--ds-surface); color: var(--at-ink); font-size: 11px; font-weight: 800; }

.at-picker { border: 1px solid var(--ds-border); border-radius: 18px; background: var(--ds-surface); box-shadow: var(--ds-shadow-tile); overflow: hidden; }
.at-picker__search { display: flex; align-items: center; gap: 8px; padding: 0 12px; border-bottom: 1px solid var(--ds-line); color: var(--ds-muted); }
.at-picker__search input { flex: 1; min-width: 0; height: 46px; border: 0; outline: 0; background: transparent; font: 500 14px var(--ds-font-main); color: var(--ds-ink); }
.at-picker__list { max-height: 236px; overflow-y: auto; overscroll-behavior: contain; padding: 4px; }
.at-picker__item { display: flex; align-items: center; gap: 10px; width: 100%; min-height: 54px; padding: 8px; border: 0; border-radius: 13px; background: transparent; text-align: left; font-family: var(--ds-font-main); color: var(--ds-ink); }
.at-picker__item:active, .at-picker__item.active { background: var(--at-soft); }
.at-picker__item em { flex: 0 0 auto; min-width: 32px; padding: 4px 8px; border-radius: 999px; background: var(--ds-surface-subtle); text-align: center; font-style: normal; font-size: 12px; font-weight: 800; color: var(--ds-ink); }
.at-picker__item em.zero { color: var(--ds-muted); font-weight: 600; }
.at-picker__empty { margin: 0; padding: 18px 12px; text-align: center; font-size: 12px; color: var(--ds-muted); }

.at-stepper { display: grid; grid-template-columns: 56px 1fr 56px; gap: 8px; }
.at-stepper button { display: grid; place-items: center; height: 56px; border: 1px solid var(--ds-border); border-radius: 18px; background: var(--ds-surface); color: var(--ds-ink); box-shadow: var(--ds-shadow-tile); }
.at-stepper button:disabled { opacity: .4; }
.at-stepper input { box-sizing: border-box; width: 100%; height: 56px; border: 1.5px solid var(--ds-border); border-radius: 18px; background: var(--ds-surface); text-align: center; outline: 0; font: 800 24px var(--ds-font-main); color: var(--ds-ink); -moz-appearance: textfield; }
.at-stepper input::-webkit-outer-spin-button, .at-stepper input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
.at-stepper input:focus { border-color: var(--at-accent); }
.at-stepper.is-over input { border-color: var(--ds-danger); color: var(--ds-danger-ink); }
.at-quick { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
.at-quick button { min-height: 36px; min-width: 48px; padding: 0 12px; border: 1px solid var(--ds-border); border-radius: 999px; background: var(--ds-surface); color: var(--ds-ink); font: 700 12px var(--ds-font-main); }
.at-quick button.active { background: var(--at-soft); border-color: var(--at-accent); color: var(--at-ink); }
.at-quick__all { margin-left: auto; }
.at-hint { margin: 8px 0 0 2px; font-size: 12px; font-weight: 600; color: var(--ds-muted); }
.at-hint--danger { color: var(--ds-danger-ink); }

.at-preview { padding: 12px 14px; border-radius: 18px; background: var(--ds-surface); border: 1px dashed var(--ds-border); }
.at-preview__title { display: block; margin-bottom: 4px; font-size: 10px; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; color: var(--ds-muted); }
.at-preview__row { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 5px 0; font-size: 13px; }
.at-preview__who { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--ds-ink); font-weight: 600; }
.at-preview__nums { display: inline-flex; align-items: center; gap: 4px; flex: 0 0 auto; color: var(--ds-muted); font-variant-numeric: tabular-nums; }
.at-preview__nums b { font-size: 15px; color: var(--ds-ink); }
.at-preview__nums b.up { color: var(--ds-emerald-ink); }
.at-preview__nums b.down { color: var(--ds-amber-ink); }
.at-preview__nums b.neg { color: var(--ds-danger-ink); }

.at-input { box-sizing: border-box; width: 100%; min-height: 48px; border: 1px solid var(--ds-border); border-radius: 16px; background: var(--ds-surface); padding: 0 14px; outline: 0; font: 500 14px var(--ds-font-main); color: var(--ds-ink); }
.at-input:focus { border-color: var(--at-accent); }

.at-sheet__foot { padding: 10px 16px calc(12px + var(--ds-safe-bottom)); background: var(--ds-canvas); border-top: 1px solid var(--ds-line); }
.at-submit { display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; min-height: 54px; border: 0; border-radius: 18px; background: var(--ds-primary); color: #fff; font: 800 15px var(--ds-font-main); box-shadow: var(--ds-shadow-btn); transition: opacity .2s; }
.at-submit:disabled { opacity: .45; box-shadow: none; }
.sheet-enter-active, .sheet-leave-active { transition: opacity .22s ease; }
.sheet-enter-active .at-sheet, .sheet-leave-active .at-sheet { transition: transform .26s cubic-bezier(.2, .8, .2, 1); }
.sheet-enter-from, .sheet-leave-to { opacity: 0; }
.sheet-enter-from .at-sheet, .sheet-leave-to .at-sheet { transform: translateY(100%); }
@media (prefers-reduced-motion: reduce) { .sheet-enter-active, .sheet-leave-active, .sheet-enter-active .at-sheet, .sheet-leave-active .at-sheet { transition: none; } }

.at-toast { position: fixed; z-index: 1100; left: 50%; bottom: calc(92px + var(--ds-safe-bottom)); transform: translateX(-50%); max-width: calc(100vw - 32px); padding: 11px 16px; border-radius: 999px; background: var(--ds-primary); color: #fff; box-shadow: var(--ds-shadow-float); font-size: 12px; text-align: center; }
.spin { animation: spin .8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.toast-enter-active, .toast-leave-active { transition: .2s; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translate(-50%, 8px); }
</style>
