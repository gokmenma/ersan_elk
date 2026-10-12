<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import {
  ArrowLeft, BriefcaseBusiness, Building2, ChevronDown, ChevronRight, Mail,
  MapPin, Pencil, Phone, Plus, RefreshCw, Search, SlidersHorizontal, UserRoundCheck, UsersRound, X,
} from '@lucide/vue'
import { useRouter } from 'vue-router'
import { personnelApi } from '../services/personnel.js'

const router = useRouter()
const items = ref([])
const summary = ref({ total: 0, active: 0, passive: 0, departments: 0 })
const departments = ref([])
const pagination = ref({ page: 1, has_more: false, total: 0 })
const search = ref('')
const status = ref('active')
const department = ref('')
const loading = ref(true)
const loadingMore = ref(false)
const error = ref('')
const selected = ref(null)
const detailLoading = ref(false)
const canEdit = ref(false)
let searchTimer
let requestNumber = 0

const activeFilterCount = computed(() => (status.value !== 'active' ? 1 : 0) + (department.value ? 1 : 0))

async function load(reset = true) {
  const currentRequest = ++requestNumber
  if (reset) loading.value = true
  else loadingMore.value = true
  error.value = ''
  try {
    const result = await personnelApi.list({
      search: search.value,
      status: status.value,
      department: department.value,
      page: reset ? 1 : pagination.value.page + 1,
      limit: 24,
    })
    if (currentRequest !== requestNumber) return
    items.value = reset ? result.items : [...items.value, ...result.items]
    summary.value = result.summary
    departments.value = result.departments
    pagination.value = result.pagination
    canEdit.value = Boolean(result.can_edit)
  } catch (reason) {
    if (currentRequest === requestNumber) error.value = reason.message
  } finally {
    if (currentRequest === requestNumber) {
      loading.value = false
      loadingMore.value = false
    }
  }
}

watch([status, department], () => load(true))
watch(search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => load(true), 320)
})

async function openDetail(person) {
  selected.value = person
  detailLoading.value = true
  try {
    const result = await personnelApi.detail(person.id)
    selected.value = result.personnel
  } catch (reason) {
    error.value = reason.message
    selected.value = null
  } finally {
    detailLoading.value = false
  }
}

function closeDetail() { selected.value = null }
function phoneHref(phone) { return `tel:${phone.replace(/[^+\d]/g, '')}` }
function mailHref(email) { return `mailto:${email}` }
function formatDate(value) {
  if (!value) return '—'
  return new Intl.DateTimeFormat('tr-TR', { day: '2-digit', month: 'long', year: 'numeric' }).format(new Date(`${value}T00:00:00`))
}

onMounted(() => load(true))
</script>

<template>
  <section class="personnel-screen">
    <header class="personnel-hero">
      <div class="directory-topbar">
        <button type="button" aria-label="Geri dön" @click="router.back()"><ArrowLeft :size="20" /></button>
        <div><span>EKİP REHBERİ</span><h1>Personel</h1></div>
        <button type="button" aria-label="Listeyi yenile" :class="{ spinning: loading }" @click="load(true)"><RefreshCw :size="19" /></button>
      </div>
      <div class="personnel-summary">
        <button :class="{ active: status === 'active' }" @click="status = 'active'"><strong>{{ summary.active }}</strong><span><i class="dot-active"></i> Aktif</span></button>
        <button :class="{ active: status === 'all' }" @click="status = 'all'"><strong>{{ summary.total }}</strong><span><UsersRound :size="12" /> Toplam</span></button>
        <button :class="{ active: status === 'passive' }" @click="status = 'passive'"><strong>{{ summary.passive }}</strong><span><i class="dot-passive"></i> Ayrılan</span></button>
      </div>
    </header>

    <div class="directory-body">
      <div class="directory-search">
        <Search :size="19" />
        <input v-model="search" type="search" inputmode="search" placeholder="İsim, görev, telefon veya ekip ara" aria-label="Personel ara" />
        <button v-if="search" type="button" aria-label="Aramayı temizle" @click="search = ''"><X :size="17" /></button>
      </div>

      <div class="directory-filters">
        <label><SlidersHorizontal :size="15" /><select v-model="department"><option value="">Tüm departmanlar</option><option v-for="item in departments" :key="item" :value="item">{{ item }}</option></select><ChevronDown :size="14" /></label>
        <span v-if="activeFilterCount">{{ activeFilterCount }} filtre</span>
        <small>{{ pagination.total }} sonuç</small>
      </div>

      <div v-if="loading" class="personnel-skeletons" aria-label="Personeller yükleniyor">
        <div v-for="index in 5" :key="index" class="personnel-skeleton"><i></i><span></span><b></b></div>
      </div>

      <div v-else-if="error" class="directory-state error-state">
        <span><RefreshCw :size="23" /></span><strong>Liste alınamadı</strong><p>{{ error }}</p><button @click="load(true)">Tekrar dene</button>
      </div>

      <div v-else-if="!items.length" class="directory-state">
        <span><UsersRound :size="25" /></span><strong>Personel bulunamadı</strong><p>Arama veya filtre ölçütlerini değiştirmeyi deneyin.</p>
      </div>

      <div v-else class="personnel-list">
        <button v-for="person in items" :key="person.id" class="personnel-row" type="button" @click="openDetail(person)">
          <span class="personnel-avatar">{{ person.initials }}<i :class="person.active ? 'online' : 'offline'"></i></span>
          <span class="personnel-main"><strong>{{ person.name }}</strong><small>{{ [person.job, person.department].filter(Boolean).join(' · ') || 'Görev bilgisi bulunmuyor' }}</small><em v-if="person.team || person.region"><MapPin :size="11" /> {{ person.team || person.region }}</em></span>
          <span class="row-arrow"><ChevronRight :size="18" /></span>
        </button>
        <button v-if="pagination.has_more" class="load-more" :disabled="loadingMore" @click="load(false)">{{ loadingMore ? 'Yükleniyor…' : 'Daha fazla göster' }}</button>
      </div>
    </div>

    <button v-if="canEdit" class="personnel-fab" type="button" aria-label="Yeni personel ekle" @click="router.push('/personel/yeni')"><Plus :size="23" /></button>

    <Transition name="sheet-fade">
      <div v-if="selected" class="personnel-sheet-layer" @click.self="closeDetail">
        <aside class="personnel-sheet" role="dialog" aria-modal="true" aria-label="Personel detayı">
          <span class="sheet-handle"></span>
          <button class="sheet-close" type="button" aria-label="Kapat" @click="closeDetail"><X :size="19" /></button>
          <div class="sheet-profile">
            <span class="sheet-avatar">{{ selected.initials }}</span>
            <div><span :class="selected.active ? 'status-active' : 'status-passive'">{{ selected.active ? 'Aktif personel' : 'Ayrılmış personel' }}</span><h2>{{ selected.name }}</h2><p>{{ selected.job || 'Görev belirtilmemiş' }}</p></div>
          </div>
          <div v-if="detailLoading" class="detail-loading">Bilgiler yükleniyor…</div>
          <template v-else>
            <div class="contact-actions">
              <a v-if="selected.phone" :href="phoneHref(selected.phone)"><Phone :size="19" /><span>Ara</span></a>
              <a v-if="selected.email" :href="mailHref(selected.email)"><Mail :size="19" /><span>E-posta</span></a>
            </div>
            <button v-if="canEdit" class="edit-personnel-button" type="button" @click="router.push(`/personel/${selected.id}/duzenle`)"><Pencil :size="17" /> Bilgileri düzenle</button>
            <div class="personnel-facts">
              <div><span><Building2 :size="18" /></span><p><small>Departman</small><strong>{{ selected.department || 'Belirtilmemiş' }}</strong></p></div>
              <div><span><BriefcaseBusiness :size="18" /></span><p><small>Ekip / Bölge</small><strong>{{ [selected.team, selected.region].filter(Boolean).join(' · ') || 'Atanmamış' }}</strong></p></div>
              <div><span><UserRoundCheck :size="18" /></span><p><small>İşe giriş</small><strong>{{ formatDate(selected.start_date) }}</strong></p></div>
            </div>
          </template>
        </aside>
      </div>
    </Transition>
  </section>
</template>
