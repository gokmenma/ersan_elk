<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import {
  BriefcaseBusiness, Building2, ChevronDown, ChevronRight, Mail,
  MapPin, Pencil, Phone, Plus, RefreshCw, Search, SlidersHorizontal, UserRoundCheck, UsersRound, X,
} from '@lucide/vue'
import { useRoute, useRouter } from 'vue-router'
import { personnelApi } from '../services/personnel.js'
import TopBar from '../components/TopBar.vue'

const router = useRouter()
const route = useRoute()
const specialStatuses = { working: 'Sahada', on_leave: 'İzinli', late: 'Geç Kalan' }
const items = ref([])
const summary = ref({ total: 0, active: 0, passive: 0, departments: 0 })
const departments = ref([])
const pagination = ref({ page: 1, has_more: false, total: 0 })
const search = ref('')
const status = ref(Object.hasOwn(specialStatuses, String(route.query.durum || '')) ? String(route.query.durum) : 'active')
const department = ref('')
const loading = ref(true)
const loadingMore = ref(false)
const error = ref('')
const selected = ref(null)
const detailLoading = ref(false)
const canEdit = ref(false)
const photoModalPerson = ref(null)

function openPhotoModal(person) {
  photoModalPerson.value = person
}

function closePhotoModal() {
  photoModalPerson.value = null
}
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
  await router.push(`/personel/${encodeURIComponent(person.id)}`)
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
  <section class="ds-screen personnel-screen">
    <!-- 1. Merkezi TopBar (Geri butonu, Başlık, Bildirim ve Profil) -->
    <TopBar eyebrow="EKİP REHBERİ" title="Personel" subtitle="Şirket içi ekip rehberi ve iletişim" :back="true" />

    <!-- 2. Hero Bento Kart (Personel Durumu Özeti) -->
    <section class="bento-card bento-card--white">
      <header class="bento-head">
        <div class="bento-head-title">
          <h3>Personel Dağılımı</h3>
        </div>
        <div class="bento-head-icons">
          <span class="bento-mini-btn" title="Personel"><UsersRound :size="14" /></span>
          <button
            type="button"
            class="bento-mini-btn"
            title="Listeyi Yenile"
            :class="{ 'is-spinning': loading }"
            @click="load(true)"
          >
            <RefreshCw :size="13" />
          </button>
        </div>
      </header>

      <!-- Segmentli İlerleme Çubuğu -->
      <div class="segmented-track">
        <span
          class="seg-pill seg-pill--blue"
          :style="{ width: `${summary.total ? Math.round((summary.active / summary.total) * 100) : 0}%` }"
        ></span>
        <span
          class="seg-pill seg-pill--coral"
          :style="{ width: `${summary.total ? Math.max(6, Math.round((summary.passive / summary.total) * 100)) : 0}%` }"
        ></span>
      </div>

      <!-- 3'lü Durum Seçici Hapları (Aktif, Toplam, Ayrılan) -->
      <div class="personnel-stat-pills">
        <button
          type="button"
          class="stat-pill-btn"
          :class="{ 'is-active': status === 'active' }"
          @click="status = 'active'"
        >
          <span class="pill-dot dot-active"></span>
          <div class="stat-pill-copy">
            <strong>{{ summary.active }}</strong>
            <small>Aktif</small>
          </div>
        </button>

        <button
          type="button"
          class="stat-pill-btn"
          :class="{ 'is-active': status === 'all' }"
          @click="status = 'all'"
        >
          <span class="pill-dot dot-all"></span>
          <div class="stat-pill-copy">
            <strong>{{ summary.total }}</strong>
            <small>Toplam</small>
          </div>
        </button>

        <button
          type="button"
          class="stat-pill-btn"
          :class="{ 'is-active': status === 'passive' }"
          @click="status = 'passive'"
        >
          <span class="pill-dot dot-passive"></span>
          <div class="stat-pill-copy">
            <strong>{{ summary.passive }}</strong>
            <small>Ayrılan</small>
          </div>
        </button>

        <button
          v-if="specialStatuses[status]"
          type="button"
          class="stat-pill-btn is-active"
          @click="status = 'active'"
        >
          <span class="pill-dot dot-active"></span>
          <div class="stat-pill-copy">
            <strong>{{ pagination.total }}</strong>
            <small>{{ specialStatuses[status] }} ✕</small>
          </div>
        </button>
      </div>
    </section>

    <!-- 3. Canlı Arama ve Filtreleme Çubuğu -->
    <div class="clay-search-bar">
      <Search :size="18" />
      <input
        v-model="search"
        type="search"
        inputmode="search"
        placeholder="İsim, görev, telefon veya ekip ara..."
        class="clay-search-input"
        aria-label="Personel ara"
      />
      <button v-if="search" type="button" class="clay-search-action" aria-label="Aramayı temizle" @click="search = ''">
        <X :size="16" />
      </button>
    </div>

    <!-- Filtre Seçici Satırı -->
    <div class="personnel-filter-row">
      <label class="personnel-dept-pill">
        <SlidersHorizontal :size="14" />
        <select v-model="department">
          <option value="">Tüm departmanlar</option>
          <option v-for="item in departments" :key="item" :value="item">{{ item }}</option>
        </select>
        <ChevronDown :size="13" />
      </label>
      <span v-if="activeFilterCount" class="clay-badge clay-badge--blue">{{ activeFilterCount }} filtre</span>
      <small class="personnel-result-count">{{ pagination.total }} sonuç</small>
    </div>

    <!-- 4. Yükleniyor Durumu -->
    <div v-if="loading" class="clay-skeleton-grid" aria-label="Personeller yükleniyor">
      <div v-for="index in 4" :key="index" class="clay-skeleton-card" style="height: 84px;"></div>
    </div>

    <!-- Hata Durumu -->
    <div v-else-if="error" class="clay-state-card">
      <RefreshCw :size="24" class="clay-spin-icon" />
      <strong>Liste alınamadı</strong>
      <p>{{ error }}</p>
      <button type="button" class="clay-retry-btn" @click="load(true)">Tekrar dene</button>
    </div>

    <!-- Boş Durum -->
    <div v-else-if="!items.length" class="clay-state-card">
      <UsersRound :size="28" />
      <strong>Personel bulunamadı</strong>
      <p>Arama veya filtre ölçütlerini değiştirmeyi deneyin.</p>
    </div>

    <!-- 5. Personel Liste Kartları (Bento Cards) -->
    <div v-else class="personnel-cards-list">
      <article
        v-for="person in items"
        :key="person.id"
        class="personnel-card"
        role="button"
        tabindex="0"
        @click="openDetail(person)"
      >
        <div class="personnel-avatar-wrap" @click.stop="openPhotoModal(person)">
          <img v-if="person.avatar" :src="person.avatar" :alt="person.name" @error="person.avatar = null" />
          <span v-else class="personnel-initials">{{ person.initials }}</span>
          <i :class="person.active ? 'dot-online' : 'dot-offline'"></i>
        </div>

        <div class="personnel-info">
          <strong class="personnel-name">{{ person.name }}</strong>
          <small class="personnel-job">{{ [person.job, person.department].filter(Boolean).join(' · ') || 'Görev bilgisi bulunmuyor' }}</small>
          <div v-if="person.team || person.region" class="personnel-location">
            <MapPin :size="13" />
            <span>{{ [person.team, person.region].filter(Boolean).join(' · ') }}</span>
          </div>
        </div>

        <span class="personnel-arrow">
          <ChevronRight :size="16" />
        </span>
      </article>

      <button
        v-if="pagination.has_more"
        class="clay-btn clay-btn--secondary w-100 mt-2"
        :disabled="loadingMore"
        @click="load(false)"
      >
        {{ loadingMore ? 'Yükleniyor…' : 'Daha fazla göster' }}
      </button>
    </div>

    <!-- 6. Yeni Personel Ekleme Butonu (FAB) -->
    <button
      v-if="canEdit"
      class="clay-fab-btn"
      type="button"
      aria-label="Yeni personel ekle"
      @click="router.push('/personel/yeni')"
    >
      <Plus :size="22" />
    </button>

    <!-- Büyük Fotoğraf Görüntüleme Modalı (Lightbox) -->
    <Teleport to="body">
      <div v-if="photoModalPerson" class="clay-lightbox-overlay" @click.self="closePhotoModal">
        <div class="clay-lightbox-card">
          <button type="button" class="clay-lightbox-close" @click="closePhotoModal" aria-label="Kapat">
            <X :size="20" />
          </button>
          
          <div class="clay-lightbox-image-box">
            <img 
              v-if="photoModalPerson.avatar" 
              :src="photoModalPerson.avatar" 
              :alt="photoModalPerson.name" 
              class="clay-lightbox-img"
              @error="photoModalPerson.avatar = null"
            />
            <div v-else class="clay-lightbox-avatar-fallback">
              <span>{{ photoModalPerson.initials }}</span>
            </div>
          </div>

          <div class="clay-lightbox-info">
            <span class="clay-badge" :class="photoModalPerson.active ? 'clay-badge--emerald' : 'clay-badge--coral'">
              {{ photoModalPerson.active ? 'AKTİF PERSONEL' : 'AYRILMIŞ PERSONEL' }}
            </span>
            <h3 class="clay-lightbox-name">{{ photoModalPerson.name }}</h3>
            <p class="clay-lightbox-role">
              {{ [photoModalPerson.job, photoModalPerson.department].filter(Boolean).join(' · ') || 'Görev bilgisi bulunmuyor' }}
            </p>
            <p class="clay-lightbox-role clay-lightbox-region">
              <MapPin :size="13" />
              {{ [photoModalPerson.team, photoModalPerson.region].filter(Boolean).join(' · ') || 'Ekip / Bölge atanmamış' }}
            </p>
          </div>

          <div class="clay-lightbox-actions">
            <button 
              type="button" 
              class="clay-btn clay-btn--primary w-100" 
              @click="openDetail(photoModalPerson); closePhotoModal()"
            >
              <UserRoundCheck :size="16" />
              <span>Profiline Git</span>
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </section>
</template>

<style scoped>
.personnel-screen {
  padding-bottom: calc(var(--nav-height) + 24px + env(safe-area-inset-bottom));
}

/* 1. 3'lü Durum Seçici Hapları */
.personnel-stat-pills {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
}

.stat-pill-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 12px;
  border-radius: 16px;
  border: 1px solid var(--ds-border-subtle);
  background: var(--ds-surface-subtle);
  color: var(--ds-ink);
  cursor: pointer;
  transition: all 0.18s ease;
  text-align: left;
}

.stat-pill-btn:active {
  transform: scale(0.97);
}

.stat-pill-btn.is-active {
  background: var(--ds-primary) !important;
  border-color: var(--ds-primary) !important;
  color: var(--ds-ink-light) !important;
  box-shadow: 0 4px 12px rgba(24, 28, 36, 0.2);
}

.stat-pill-btn.is-active strong,
.stat-pill-btn.is-active small {
  color: var(--ds-ink-light) !important;
}

.pill-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  flex-shrink: 0;
}

.dot-active  { background: var(--ds-emerald); }
.dot-all     { background: var(--ds-blue); }
.dot-passive { background: var(--ds-coral); }

.stat-pill-copy {
  display: flex;
  flex-direction: column;
}

.stat-pill-copy strong {
  font-size: 17px;
  font-weight: 800;
  line-height: 1.1;
  letter-spacing: -0.03em;
}

.stat-pill-copy small {
  font-size: 10px;
  color: var(--ds-muted);
  margin-top: 1px;
}

/* 2. Filtre Satırı */
.personnel-filter-row {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 14px;
}

.personnel-dept-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 12px;
  border-radius: var(--ds-radius-pill);
  background: var(--ds-surface);
  border: 1px solid var(--ds-border);
  box-shadow: var(--ds-shadow-pill);
  color: var(--ds-muted);
  font: 600 11px var(--ds-font-main);
  cursor: pointer;
}

.personnel-dept-pill select {
  border: 0;
  background: transparent;
  color: var(--ds-ink);
  font: 600 11px var(--ds-font-main);
  outline: none;
  cursor: pointer;
}

.personnel-result-count {
  margin-left: auto;
  font: 600 10px var(--ds-font-mono);
  color: var(--ds-muted);
}

/* 3. Personel Liste Kartları */
.personnel-cards-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.personnel-card {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 16px;
  border-radius: var(--ds-radius-tile);
  background: var(--ds-surface);
  border: 1px solid var(--ds-border);
  box-shadow: var(--ds-shadow-tile);
  cursor: pointer;
  transition: transform 0.15s ease;
}

.personnel-card:active {
  transform: scale(0.985);
}

.personnel-avatar-wrap {
  position: relative;
  display: grid;
  width: 44px;
  height: 44px;
  place-items: center;
  border-radius: 14px;
  background: var(--ds-primary);
  color: var(--ds-ink-light);
  flex-shrink: 0;
  overflow: hidden;
}

.personnel-avatar-wrap img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.personnel-initials {
  font: 800 13px var(--ds-font-mono);
  color: var(--ds-ink-light);
}

.dot-online, .dot-offline {
  position: absolute;
  right: -2px;
  bottom: -2px;
  width: 11px;
  height: 11px;
  border-radius: 50%;
  border: 2px solid var(--ds-surface);
}

.dot-online  { background: var(--ds-emerald); }
.dot-offline { background: var(--ds-muted-light); }

.personnel-info {
  flex: 1;
  min-width: 0;
}

.personnel-name {
  display: block;
  font-size: 13.5px;
  font-weight: 800;
  letter-spacing: -0.02em;
  color: var(--ds-ink);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.personnel-job {
  display: block;
  font-size: 11px;
  color: var(--ds-muted);
  margin-top: 2px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.personnel-location {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  margin-top: 4px;
  font-size: 10.5px;
  font-weight: 600;
  color: var(--ds-blue-ink);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.personnel-arrow {
  display: grid;
  width: 28px;
  height: 28px;
  place-items: center;
  border-radius: 50%;
  background: var(--ds-surface-subtle);
  color: var(--ds-muted);
  flex-shrink: 0;
}

/* 4. FAB Butonu */
.clay-fab-btn {
  position: fixed;
  z-index: 50;
  right: max(20px, calc((100vw - 480px) / 2 + 20px));
  bottom: calc(var(--nav-height) + 16px + env(safe-area-inset-bottom));
  display: grid;
  width: 52px;
  height: 52px;
  place-items: center;
  border-radius: 18px;
  border: 2px solid var(--ds-surface);
  background: var(--ds-primary);
  color: var(--ds-ink-light);
  box-shadow: var(--ds-shadow-float);
  cursor: pointer;
  transition: transform 0.15s ease;
}

.clay-fab-btn:active {
  transform: scale(0.94);
}

.is-spinning svg {
  animation: clay-spin 0.8s linear infinite;
}
</style>
