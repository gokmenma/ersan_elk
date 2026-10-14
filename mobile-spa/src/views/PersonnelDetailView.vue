<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  ArrowLeft, BadgeDollarSign, BriefcaseBusiness, CalendarCheck, CalendarDays,
  ChevronRight, FileText, Gavel, History, Mail, MapPin, PackageCheck, Pencil,
  Phone, RefreshCw, UserRoundCheck,
} from '@lucide/vue'
import { personnelApi } from '../services/personnel.js'
import TopBar from '../components/TopBar.vue'

const route = useRoute()
const router = useRouter()
const loading = ref(true)
const error = ref('')
const person = ref(null)
const canEdit = ref(false)

const modules = [
  { tab: 'genel', label: 'Genel bilgiler', meta: 'İletişim, kimlik ve giriş bilgileri', icon: UserRoundCheck, color: '#181c24' },
  { tab: 'calisma', label: 'Çalışma bilgileri', meta: 'Görev, ekip ve çalışma geçmişi', icon: BriefcaseBusiness, color: '#0284c7' },
  { tab: 'izinler', label: 'İzin ve raporlar', meta: 'Hak ediş ve izin geçmişi', icon: CalendarDays, color: '#059669' },
  { tab: 'zimmetler', label: 'Zimmetler', meta: 'Araç, cihaz ve demirbaşlar', icon: PackageCheck, color: '#d97706' },
  { tab: 'finansal', label: 'Maaş ve finans', meta: 'Ücret, ek ödeme ve kesintiler', icon: BadgeDollarSign, color: '#7c3aed' },
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
    canEdit.value = Boolean(result.can_edit)
  } catch (reason) {
    error.value = reason.message
  } finally {
    loading.value = false
  }
}

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
      <!-- 2. Hero Bento Kart (Kimlik ve İletişim) -->
      <section class="bento-card bento-card--white detail-hero-bento">
        <div class="detail-avatar-row">
          <div class="detail-avatar-box">
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
          <h2 class="person-name">{{ person.name }}</h2>
          <p class="person-role">{{ [person.job, person.department].filter(Boolean).join(' · ') || 'Çalışma bilgisi tanımlanmamış' }}</p>
        </div>

        <!-- İletişim Butonları -->
        <div class="detail-contact-row">
          <a v-if="person.phone" :href="phoneHref(person.phone)" class="clay-btn clay-btn--primary contact-link">
            <Phone :size="16" />
            <span>Ara</span>
          </a>
          <a v-if="person.email" :href="`mailto:${person.email}`" class="clay-btn clay-btn--secondary contact-link">
            <Mail :size="16" />
            <span>E-posta</span>
          </a>
        </div>
      </section>

      <!-- 3. Bilgi Özet Bento Kartı -->
      <section class="bento-card bento-card--white detail-facts-bento">
        <div class="fact-cell">
          <span class="fact-icon"><BriefcaseBusiness :size="16" /></span>
          <div class="fact-text">
            <small>Departman</small>
            <strong>{{ person.department || 'Belirtilmemiş' }}</strong>
          </div>
        </div>

        <div class="fact-cell">
          <span class="fact-icon"><MapPin :size="16" /></span>
          <div class="fact-text">
            <small>Ekip / Bölge</small>
            <strong>{{ [person.team, person.region].filter(Boolean).join(' · ') || 'Atanmamış' }}</strong>
          </div>
        </div>

        <div class="fact-cell fact-cell--full">
          <span class="fact-icon"><History :size="16" /></span>
          <div class="fact-text">
            <small>İşe giriş tarihi</small>
            <strong>{{ formatDate(person.start_date) }}</strong>
          </div>
        </div>
      </section>

      <!-- 4. Modül ve İşlemler Listesi (Bento Tiles) -->
      <section class="detail-modules-section">
        <div class="clay-section-head">
          <h2>Tüm Bilgiler ve İşlemler</h2>
          <span class="clay-pill-tag">8 Bölüm</span>
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
    </template>
  </section>
</template>

<style scoped>
.personnel-detail-screen {
  padding-bottom: calc(var(--nav-height) + 24px + env(safe-area-inset-bottom));
}

.detail-hero-bento {
  padding: 20px;
}

.detail-avatar-row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 14px;
}

.detail-avatar-box {
  position: relative;
  display: grid;
  width: 64px;
  height: 64px;
  place-items: center;
  border-radius: 20px;
  background: var(--ds-primary);
  color: #ffffff;
  font: 800 20px var(--ds-font-mono);
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
}

.detail-identity {
  margin-bottom: 16px;
}

.person-name {
  margin: 4px 0 0;
  font-size: 22px;
  font-weight: 800;
  letter-spacing: -0.03em;
  color: var(--ds-ink);
}

.person-role {
  margin: 3px 0 0;
  font-size: 12px;
  color: var(--ds-muted);
}

.detail-contact-row {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 10px;
}

.contact-link {
  height: 44px;
  text-decoration: none;
}

/* Bilgi Özet Kartı */
.detail-facts-bento {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
  padding: 16px;
}

.fact-cell {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border-radius: var(--ds-radius-sm);
  background: var(--ds-surface-subtle);
}

.fact-cell--full {
  grid-column: 1 / -1;
}

.fact-icon {
  display: grid;
  width: 32px;
  height: 32px;
  place-items: center;
  border-radius: 10px;
  background: var(--ds-surface);
  color: var(--ds-primary);
  flex-shrink: 0;
}

.fact-text small {
  display: block;
  font-size: 10px;
  color: var(--ds-muted);
}

.fact-text strong {
  display: block;
  font-size: 12px;
  font-weight: 700;
  color: var(--ds-ink);
  margin-top: 1px;
}

/* Modül Listesi */
.detail-modules-section {
  margin-top: 20px;
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
  color: #ffffff;
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
