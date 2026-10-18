<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  ArrowLeft, BadgeDollarSign, BriefcaseBusiness, CalendarCheck, CalendarDays, Check,
  ChevronDown, ChevronRight, FileText, Gavel, History, Mail, MapPin, PackageCheck, Pencil,
  Phone, Receipt, RefreshCw, Search, UserRound, UserRoundCheck, X,
} from '@lucide/vue'
import { personnelApi } from '../services/personnel.js'
import TopBar from '../components/TopBar.vue'

const route = useRoute()
const router = useRouter()
const loading = ref(true)
const error = ref('')
const person = ref(null)
const canEdit = ref(false)
const quickPersonnelList = ref([])
const showPhotoModal = ref(false)

const isPersonSheetOpen = ref(false)
const personSheetSearch = ref('')

function openPhotoModal() {
  showPhotoModal.value = true
}

function closePhotoModal() {
  showPhotoModal.value = false
}

function openPersonSheet() {
  personSheetSearch.value = ''
  isPersonSheetOpen.value = true
}

function closePersonSheet() {
  isPersonSheetOpen.value = false
}

function selectPersonFromSheet(newId) {
  isPersonSheetOpen.value = false
  if (newId && newId !== person.value?.id) {
    router.push({ name: 'personnel-detail', params: { id: newId } })
  }
}

function getPersonInitials(name) {
  if (!name) return '?'
  const parts = name.trim().split(/\s+/)
  if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase()
  return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
}

const filteredQuickPersonnelList = computed(() => {
  const list = quickPersonnelList.value || []
  const q = personSheetSearch.value.trim().toLowerCase()
  if (!q) return list
  return list.filter(p =>
    (p.name && p.name.toLowerCase().includes(q)) ||
    (p.department && p.department.toLowerCase().includes(q)) ||
    (p.job && p.job.toLowerCase().includes(q))
  )
})

const modules = [
  { tab: 'genel', label: 'Genel bilgiler', meta: 'İletişim, kimlik ve giriş bilgileri', icon: UserRoundCheck, color: '#181c24' },
  { tab: 'calisma', label: 'Çalışma bilgileri', meta: 'Görev, ekip ve çalışma geçmişi', icon: BriefcaseBusiness, color: '#0284c7' },
  { tab: 'izinler', label: 'İzin ve raporlar', meta: 'Hak ediş ve izin geçmişi', icon: CalendarDays, color: '#059669' },
  { tab: 'zimmetler', label: 'Zimmetler', meta: 'Araç, cihaz ve demirbaşlar', icon: PackageCheck, color: '#d97706' },
  { tab: 'ek_odemeler', label: 'Ek ödemeler', meta: 'Prim, ikramiye, kira ve yardımlar', icon: BadgeDollarSign, color: '#059669' },
  { tab: 'kesintiler', label: 'Kesintiler', meta: 'Dönemsel, sürekli ve diğer kesintiler', icon: Receipt, color: '#dc2626' },
  { tab: 'evraklar', label: 'Personel evrakları', meta: 'Belgeler ve dijital arşiv', icon: FileText, color: '#e11d48' },
  { tab: 'icralar', label: 'İcra dosyaları', meta: 'Borç ve kesinti takibi', icon: Gavel, color: '#f97316' },
  { tab: 'puantaj', label: 'İş ve puantaj', meta: 'Çalışma ve görev kayıtları', icon: CalendarCheck, color: '#0891b2' },
]

function phoneHref(value) {
  return `tel:${String(value || '').replace(/[^+\d]/g, '')}`
}

function formatDate(value) {
  if (!value) return 'Belirtilmemiş'
  return new Intl.DateTimeFormat('tr-TR', { day: '2-digit', month: 'long', year: 'numeric' }).format(new Date(`${value}T00:00:00`))
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const result = await personnelApi.detail(route.params.id)
    person.value = result.personnel
    if (result.personnel?.id) {
      localStorage.setItem('ersan_last_personnel_id', String(result.personnel.id))
    }
    canEdit.value = Boolean(result.can_edit)
    quickPersonnelList.value = result.personnel_quick_list || []
  } catch (reason) {
    error.value = reason.message
  } finally {
    loading.value = false
  }
}

watch(() => route.params.id, (newId) => {
  if (newId) load()
})

onMounted(load)
</script>

<template>
  <section class="ds-screen personnel-detail-screen">
    <!-- 1. Standart TopBar -->
    <TopBar eyebrow="PERSONEL PROFİLİ" title="Personel Detayı" :back="true" />

    <div v-if="loading" class="clay-skeleton-grid">
      <div class="clay-skeleton-card clay-skeleton-card--lg"></div>
      <div class="clay-skeleton-card clay-skeleton-card--md"></div>
    </div>

    <div v-else-if="error" class="clay-state-card">
      <RefreshCw :size="24" class="clay-spin-icon" />
      <strong>Personel alınamadı</strong>
      <p>{{ error }}</p>
      <button type="button" class="clay-retry-btn" @click="load">Tekrar dene</button>
    </div>

    <template v-else-if="person">
      <!-- 2. Hero Bento Kart (Kimlik, İletişim ve Entegre Özet Bilgiler) -->
      <section class="bento-card bento-card--white detail-hero-bento">
        <div class="detail-avatar-row">
          <div class="detail-avatar-box" @click="openPhotoModal" style="cursor: pointer;" title="Fotoğrafı büyüt">
            <img v-if="person.avatar" :src="person.avatar" :alt="person.name" @error="person.avatar = null" />
            <span v-else class="initials">{{ person.initials }}</span>
          </div>
          <button
            v-if="canEdit"
            type="button"
            class="clay-circle-btn edit-btn"
            title="Düzenle"
            @click="router.push(`/personel/${route.params.id}/duzenle`)"
          >
            <Pencil :size="16" />
          </button>
        </div>

        <div class="detail-identity">
          <span
            class="clay-badge mb-1"
            :class="person.active ? 'clay-badge--emerald' : 'clay-badge--coral'"
          >
            {{ person.active ? 'AKTİF PERSONEL' : 'AYRILMIŞ PERSONEL' }}
          </span>
          <div class="person-switcher-wrap" @click="openPersonSheet" role="button" tabindex="0" title="Personel Değiştir">
            <div class="person-switcher-display">
              <h2 class="person-switcher-name">{{ person.name }}</h2>
              <span class="person-switcher-pill">
                <ChevronDown :size="14" />
              </span>
            </div>
          </div>
          <p class="person-role">{{ [person.job, person.department].filter(Boolean).join(' · ') || 'Çalışma bilgisi tanımlanmamış' }}</p>
        </div>

        <!-- İletişim Butonları -->
        <div class="detail-contact-row">
          <a v-if="person.phone" :href="phoneHref(person.phone)" class="clay-btn clay-btn--primary contact-link">
            <Phone :size="15" />
            <span>Ara</span>
          </a>
          <a v-if="person.email" :href="`mailto:${person.email}`" class="clay-btn clay-btn--secondary contact-link">
            <Mail :size="15" />
            <span>E-posta</span>
          </a>
        </div>

        <!-- Minimal Kompakt Bilgi Listesi -->
        <div class="hero-mini-meta-card">
          <div class="meta-row">
            <span class="meta-lbl"><BriefcaseBusiness :size="13" /> Departman</span>
            <strong class="meta-val">{{ person.department || 'Belirtilmemiş' }}</strong>
          </div>
          <div class="meta-row">
            <span class="meta-lbl"><MapPin :size="13" /> Ekip / Bölge</span>
            <strong class="meta-val">{{ [person.team, person.region].filter(Boolean).join(' · ') || 'Atanmamış' }}</strong>
          </div>
          <div class="meta-row">
            <span class="meta-lbl"><History :size="13" /> İşe Giriş</span>
            <strong class="meta-val">{{ formatDate(person.start_date) }}</strong>
          </div>
        </div>
      </section>

      <!-- 4. Modül ve İşlemler Listesi (Bento Tiles) -->
      <section class="detail-modules-section">
        <div class="clay-section-head">
          <h2>Tüm Bilgiler ve İşlemler</h2>
          <span class="clay-pill-tag">9 Bölüm</span>
        </div>

        <div class="detail-modules-stack">
          <RouterLink
            v-for="module in modules"
            :key="module.tab"
            :to="`/personel/${encodeURIComponent(route.params.id)}/bolum/${module.tab}`"
            class="module-tile"
          >
            <span class="module-icon" :style="{ background: module.color }">
              <component :is="module.icon" :size="18" />
            </span>
            <div class="module-text">
              <strong>{{ module.label }}</strong>
              <small>{{ module.meta }}</small>
            </div>
            <ChevronRight :size="16" class="module-arrow" />
          </RouterLink>
        </div>
      </section>

      <!-- Bottom-Sheet Modal: Personel Seçimi (Bordro Standardı & Arama) -->
      <Teleport to="body">
        <div
          v-if="isPersonSheetOpen"
          class="payroll-modal-backdrop"
          @click.self="closePersonSheet"
        >
          <div class="payroll-modal-sheet payroll-modal-sheet--period">
            <div class="sheet-drag-handle"></div>
            <header class="payroll-sheet-header">
              <div>
                <span class="clay-page-eyebrow">PERSONEL SEÇİMİ</span>
                <h3 class="payroll-sheet-title">Personel Seçin</h3>
                <p class="payroll-sheet-subtitle">Görüntülemek istediğiniz personeli seçin</p>
              </div>
              <button
                type="button"
                class="clay-circle-btn"
                @click="closePersonSheet"
                aria-label="Kapat"
              >
                <X :size="18" />
              </button>
            </header>

            <!-- Arama Kutusu -->
            <div class="person-sheet-search-wrap">
              <Search :size="16" class="person-sheet-search-icon" />
              <input
                v-model="personSheetSearch"
                type="text"
                placeholder="Personel ara (ad, görev, departman)…"
                class="person-sheet-search-input"
              />
              <button
                v-if="personSheetSearch"
                type="button"
                class="person-sheet-search-clear"
                @click="personSheetSearch = ''"
                aria-label="Temizle"
              >
                <X :size="12" />
              </button>
            </div>

            <div class="period-sheet-body hide-scrollbar" style="max-height: 55vh; overflow-y: auto;">
              <div v-if="filteredQuickPersonnelList.length === 0" class="clay-state-card py-4" style="margin: 8px 0;">
                <UserRound :size="24" class="text-muted mb-1" />
                <strong style="font-size: 13px;">Eşleşen personel bulunamadı</strong>
                <p style="font-size: 11.5px; margin: 0;">Farklı bir arama terimi deneyebilirsiniz.</p>
              </div>

              <div v-else class="period-sheet-list">
                <button
                  v-for="p in filteredQuickPersonnelList"
                  :key="p.id"
                  type="button"
                  class="period-sheet-item"
                  :class="{ 'period-sheet-item--active': p.id === person.id }"
                  @click="selectPersonFromSheet(p.id)"
                >
                  <div class="period-sheet-item__left">
                    <div
                      class="period-sheet-icon"
                      :class="{ 'period-sheet-icon--active': p.id === person.id }"
                      style="overflow: hidden; padding: 0;"
                    >
                      <img
                        v-if="p.avatar"
                        :src="p.avatar"
                        :alt="p.name"
                        style="width: 100%; height: 100%; object-fit: cover;"
                        @error="p.avatar = null"
                      />
                      <span v-else style="font-weight: 800; font-size: 12px;">{{ getPersonInitials(p.name) }}</span>
                    </div>
                    <div class="period-sheet-info">
                      <strong class="period-sheet-name">{{ p.name }}</strong>
                      <span class="period-sheet-dates">
                        {{ [p.job, p.department].filter(Boolean).join(' · ') || 'Personel' }}
                      </span>
                    </div>
                  </div>

                  <div class="period-sheet-item__right">
                    <span
                      class="clay-pill-tag"
                      :class="p.active ? 'clay-pill-tag--emerald' : 'clay-pill-tag--coral'"
                    >
                      ● {{ p.active ? 'Aktif' : 'Ayrıldı' }}
                    </span>
                    <span v-if="p.id === person.id" class="period-sheet-check">
                      <Check :size="16" />
                    </span>
                  </div>
                </button>
              </div>
            </div>
          </div>
        </div>
      </Teleport>

      <!-- Büyük Fotoğraf Görüntüleme Modalı (Lightbox) -->
      <Teleport to="body">
        <div v-if="showPhotoModal" class="clay-lightbox-overlay" @click.self="closePhotoModal">
          <div class="clay-lightbox-card">
            <button type="button" class="clay-lightbox-close" @click="closePhotoModal" aria-label="Kapat">
              <X :size="20" />
            </button>
            
            <div class="clay-lightbox-image-box">
              <img 
                v-if="person.avatar" 
                :src="person.avatar" 
                :alt="person.name" 
                class="clay-lightbox-img"
                @error="person.avatar = null"
              />
              <div v-else class="clay-lightbox-avatar-fallback">
                <span>{{ person.initials }}</span>
              </div>
            </div>

            <div class="clay-lightbox-info">
              <span class="clay-badge" :class="person.active ? 'clay-badge--emerald' : 'clay-badge--coral'">
                {{ person.active ? 'AKTİF PERSONEL' : 'AYRILMIŞ PERSONEL' }}
              </span>
              <h3 class="clay-lightbox-name">{{ person.name }}</h3>
              <p class="clay-lightbox-role">
                {{ [person.job, person.department].filter(Boolean).join(' · ') || 'Çalışma bilgisi tanımlanmamış' }}
              </p>
            </div>

            <div class="clay-lightbox-actions">
              <button 
                type="button" 
                class="clay-btn clay-btn--secondary w-100" 
                @click="closePhotoModal"
              >
                <span>Kapat</span>
              </button>
            </div>
          </div>
        </div>
      </Teleport>
    </template>
  </section>
</template>

<style scoped>
.personnel-detail-screen {
  padding-bottom: calc(var(--nav-height) + 24px + env(safe-area-inset-bottom));
}

.detail-hero-bento {
  padding: 16px;
}

.detail-avatar-row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 10px;
}

.detail-avatar-box {
  position: relative;
  display: grid;
  width: 50px;
  height: 50px;
  place-items: center;
  border-radius: 16px;
  background: var(--ds-primary);
  color: var(--ds-surface);
  font: 800 16px var(--ds-font-mono);
  overflow: hidden;
  box-shadow: var(--ds-shadow-pill);
}

.detail-avatar-box img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.edit-btn {
  margin-left: auto;
  width: 36px;
  height: 36px;
}

.detail-identity {
  margin-bottom: 12px;
}

.person-name {
  margin: 3px 0 0;
  font-size: 18px;
  font-weight: 800;
  letter-spacing: -0.02em;
  color: var(--ds-ink);
}

.person-role {
  margin: 2px 0 0;
  font-size: 11.5px;
  color: var(--ds-muted);
}

.detail-contact-row {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 8px;
  margin-bottom: 12px;
}

.contact-link {
  height: 38px;
  font-size: 12px;
  text-decoration: none;
  gap: 6px;
}

/* Minimal Kompakt Bilgi Şeridi */
.hero-mini-meta-card {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 8px 12px;
  border-radius: var(--ds-radius-sm);
  background: var(--ds-surface-subtle);
  border: 1px solid var(--ds-border, rgba(0, 0, 0, 0.05));
}

.meta-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 3px 0;
  border-bottom: 1px solid rgba(0, 0, 0, 0.035);
}

.meta-row:last-child {
  border-bottom: 0;
}

.meta-lbl {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  color: var(--ds-muted);
  font-size: 11px;
  font-weight: 600;
  flex-shrink: 0;
}

.meta-val {
  color: var(--ds-ink);
  font-size: 12px;
  font-weight: 700;
  text-align: right;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* Modül Listesi */
.detail-modules-section {
  margin-top: 0;
}

.detail-modules-stack {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.module-tile {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 16px;
  border-radius: var(--ds-radius-tile);
  background: var(--ds-surface);
  border: 1px solid var(--ds-border);
  box-shadow: var(--ds-shadow-tile);
  text-decoration: none;
  color: var(--ds-ink);
  transition: transform 0.15s ease;
}

.module-tile:active {
  transform: scale(0.985);
}

.module-icon {
  display: grid;
  width: 38px;
  height: 38px;
  place-items: center;
  border-radius: 12px;
  color: var(--ds-surface);
  flex-shrink: 0;
}

.module-text {
  flex: 1;
  min-width: 0;
}

.module-text strong {
  display: block;
  font-size: 13px;
  font-weight: 700;
}

.module-text small {
  display: block;
  font-size: 10.5px;
  color: var(--ds-muted);
  margin-top: 2px;
}

.module-arrow {
  color: var(--ds-muted);
}
</style>
