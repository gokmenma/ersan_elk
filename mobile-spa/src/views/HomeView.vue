<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import {
  ArrowUpRight,
  Bell,
  Calendar,
  CalendarDays,
  CarFront,
  ChartNoAxesCombined,
  ChevronRight,
  ClipboardCheck,
  FileText,
  Gauge,
  Mail,
  Megaphone,
  RefreshCw,
  ShieldAlert,
  UsersRound,
  Zap,
} from '@lucide/vue'
import { auth } from '../services/auth.js'
import { getDashboard } from '../services/dashboard.js'

const router = useRouter()
const loading = ref(true)
const error = ref('')
const dashboard = ref({})

const actionDefinitions = [
  { permission: 'personnel', label: 'Personel', icon: UsersRound, to: '/personel' },
  { permission: 'vehicles', label: 'Araçlar', icon: CarFront, href: '/mobile/index.php?p=arac' },
  { permission: 'reports', label: 'Puantaj', icon: CalendarDays, href: '/mobile/index.php?p=puantaj' },
  { permission: 'documents', label: 'Evraklar', icon: FileText, href: '/mobile/index.php?p=evrak-takip' },
  { permission: 'tasks', label: 'Görevler', icon: ClipboardCheck, to: '/gorevler' },
  { permission: 'reports', label: 'Raporlar', icon: ChartNoAxesCombined, to: '/raporlar' },
]

const actions = computed(() =>
  actionDefinitions.filter(item => auth.state.permissions[item.permission]).slice(0, 4)
)
const operations = computed(() => dashboard.value.operations || [])
const statisticCards = computed(() => operations.value.slice(0, 6))

const personnelRate = computed(() => {
  const total = Number(dashboard.value.personnel?.total || 0)
  return total ? Math.min(100, Math.round((Number(dashboard.value.personnel?.working || 0) / total) * 100)) : 0
})

const vehicleSegments = computed(() => {
  const total = Number(dashboard.value.vehicles?.total || 0)
  const ratio = value => total ? `${Math.max(0, (Number(value || 0) / total) * 100)}%` : '0%'
  return {
    field: ratio(dashboard.value.vehicles?.field),
    service: ratio(dashboard.value.vehicles?.service),
    idle: ratio(dashboard.value.vehicles?.idle),
  }
})

const operationIcons = {
  sealing: ShieldAlert,
  cut_open: Zap,
  meter_change: Gauge,
  reading: Gauge,
  illegal: ShieldAlert,
  notice: Megaphone,
}

const operationColors = {
  sealing: '#0284c7',
  cut_open: '#f97316',
  meter_change: '#8b5cf6',
  reading: '#059669',
  illegal: '#e11d48',
  notice: '#d97706',
}

function openAction(action) {
  if (action.to) router.push(action.to)
  else if (action.href) window.location.href = action.href
}

function formatDate(value) {
  return value ? new Intl.DateTimeFormat('tr-TR', { day: '2-digit', month: 'short' }).format(new Date(value)) : ''
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    dashboard.value = await getDashboard()
  } catch (requestError) {
    error.value = requestError.message
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <section class="ds-screen">
    <!-- 1. Üst Marka & Hızlı Eylem Çubuğu -->
    <header class="clay-topbar">
      <div class="clay-brand-wrap">
        <span class="clay-brand-icon"><Zap :size="16" /></span>
        <span class="clay-brand-name">ersan</span>
      </div>

      <div class="clay-actions-row">
        <button type="button" class="clay-circle-btn" aria-label="Destek & Mesajlar" @click="router.push('/talepler')">
          <Mail :size="17" />
        </button>
        <button type="button" class="clay-circle-btn has-dot" aria-label="Bildirimler">
          <Bell :size="17" />
          <span class="clay-unread-dot"></span>
        </button>
        <div class="clay-avatar-circle" title="Kullanıcı Profili" @click="router.push('/profil')">
          <span>{{ auth.state.user?.initials || 'EE' }}</span>
        </div>
      </div>
    </header>

    <!-- 2. Sayfa Başlığı -->
    <div class="clay-page-header">
      <span class="clay-page-eyebrow">GÜNLÜK ÖZET</span>
      <h1 class="clay-page-title">Operasyon & Saha</h1>
    </div>

    <!-- Yükleniyor Durumu -->
    <div v-if="loading" class="clay-skeleton-grid">
      <div class="clay-skeleton-card clay-skeleton-card--lg"></div>
      <div class="clay-skeleton-card clay-skeleton-card--md"></div>
    </div>

    <!-- Hata Durumu -->
    <div v-else-if="error" class="clay-state-card">
      <RefreshCw :size="24" class="clay-spin-icon" />
      <strong>Veriler alınamadı</strong>
      <p>{{ error }}</p>
      <button type="button" class="clay-retry-btn" @click="load">Tekrar dene</button>
    </div>

    <!-- Ana İçerik -->
    <template v-else>
      <!-- 3. Bento Kart 1: Personel Durumu (Beyaz Bento Kart) -->
      <section
        v-if="dashboard.personnel"
        class="bento-card bento-card--white"
        role="button"
        tabindex="0"
        @click="router.push('/personel')"
      >
        <header class="bento-head">
          <div class="bento-head-title">
            <h3>Personel Durumu</h3>
          </div>
          <div class="bento-head-icons">
            <span class="bento-mini-btn" title="Personel"><UsersRound :size="14" /></span>
            <span class="bento-mini-btn" title="Takvim"><Calendar :size="14" /></span>
          </div>
        </header>

        <!-- Avatar Yığın Satırı -->
        <div class="avatar-stack-row">
          <div class="avatar-stack">
            <span class="avatar-item"><i>M</i><b class="avatar-badge badge-cyan">1</b></span>
            <span class="avatar-item"><i>A</i><b class="avatar-badge badge-orange">3</b></span>
            <span class="avatar-item"><i>K</i><b class="avatar-badge badge-blue">8</b></span>
            <span class="avatar-item"><i>E</i><b class="avatar-badge badge-purple">2</b></span>
            <span class="avatar-item"><i>S</i><b class="avatar-badge badge-amber">4</b></span>
          </div>
          <span class="avatar-stack-label">Saha Dağılımı</span>
        </div>

        <!-- Yuvarlatılmış Segmentli İlerleme Çubuğu -->
        <div class="segmented-track">
          <span class="seg-pill seg-pill--blue" :style="{ width: `${personnelRate}%` }"></span>
          <span
            class="seg-pill seg-pill--yellow"
            :style="{ width: `${Math.round((Number(dashboard.personnel.on_leave || 0) / Number(dashboard.personnel.total || 1)) * 100)}%` }"
          ></span>
          <span
            class="seg-pill seg-pill--coral"
            :style="{ width: `${Math.max(6, 100 - personnelRate - Math.round((Number(dashboard.personnel.on_leave || 0) / Number(dashboard.personnel.total || 1)) * 100))}%` }"
          ></span>
        </div>

        <!-- 2x2 Metrik Izgarası & Detay Butonu -->
        <div class="bento-metrics-grid">
          <div class="metric-cell">
            <strong>{{ dashboard.personnel.total }} <small>kişi</small></strong>
            <span>Toplam Personel</span>
          </div>
          <div class="metric-cell">
            <strong>{{ dashboard.personnel.working }} <small>aktif</small></strong>
            <span>Sahada Görevde</span>
          </div>
          <div class="metric-cell">
            <div class="metric-inline">
              <UsersRound :size="13" class="metric-tiny-icon text-muted" />
              <strong>{{ dashboard.personnel.on_leave }}</strong>
            </div>
            <span>İzinli Personel</span>
          </div>
          <div class="metric-cell metric-cell--action">
            <div class="metric-val-wrap">
              <strong>{{ Math.max(0, Number(dashboard.personnel.total) - Number(dashboard.personnel.working) - Number(dashboard.personnel.on_leave)) }}</strong>
              <span>Merkez / Diğer</span>
            </div>
            <span class="bento-round-action-btn">
              <ChevronRight :size="16" />
            </span>
          </div>
        </div>
      </section>

      <!-- 4. Bento Kart 2: Araç & Filo Durumu (Koyu Kömür / Obsidian Bento Kart) -->
      <section
        v-if="dashboard.vehicles"
        class="bento-card bento-card--dark"
        role="button"
        tabindex="0"
        @click="openAction({ href: '/mobile/index.php?p=arac' })"
      >
        <header class="bento-head">
          <div class="bento-head-title">
            <span class="dark-pill-tag">ARAÇ & FİLO</span>
            <h3>Filo Durumu</h3>
          </div>
          <div class="bento-head-icons">
            <span class="bento-mini-btn bento-mini-btn--dark"><CarFront :size="15" /></span>
          </div>
        </header>

        <!-- Koyu Kart Segmentli İlerleme Çubuğu -->
        <div class="segmented-track segmented-track--dark">
          <span class="seg-pill seg-pill--emerald" :style="{ width: vehicleSegments.field }"></span>
          <span class="seg-pill seg-pill--coral" :style="{ width: vehicleSegments.service }"></span>
          <span class="seg-pill seg-pill--amber" :style="{ width: vehicleSegments.idle }"></span>
        </div>

        <!-- 2x2 Metrik Izgarası (Koyu Zemin) -->
        <div class="bento-metrics-grid bento-metrics-grid--dark">
          <div class="metric-cell">
            <strong>{{ dashboard.vehicles.total }} <small>araç</small></strong>
            <span>Kayıtlı Filo</span>
          </div>
          <div class="metric-cell">
            <strong class="text-emerald">{{ dashboard.vehicles.field }} <small>sahada</small></strong>
            <span>Görevde Aktif</span>
          </div>
          <div class="metric-cell">
            <strong class="text-coral">{{ dashboard.vehicles.service }}</strong>
            <span>Servis / Bakım</span>
          </div>
          <div class="metric-cell metric-cell--action">
            <div class="metric-val-wrap">
              <strong class="text-amber">{{ dashboard.vehicles.idle }}</strong>
              <span>Boşta / Garaj</span>
            </div>
            <span class="bento-round-action-btn bento-round-action-btn--white">
              <ChevronRight :size="16" />
            </span>
          </div>
        </div>
      </section>

      <!-- 5. Hızlı İşlemler Bölümü -->
      <section v-if="actions.length" class="clay-section">
        <div class="clay-section-head">
          <h2>Hızlı İşlemler</h2>
          <span class="clay-pill-tag">Modüller</span>
        </div>
        <div class="quick-action-grid">
          <button
            v-for="action in actions"
            :key="action.label"
            type="button"
            class="quick-action-tile"
            @click="openAction(action)"
          >
            <span class="quick-action-icon">
              <component :is="action.icon" :size="18" :stroke-width="2" />
            </span>
            <div class="quick-action-text">
              <strong>{{ action.label }}</strong>
              <small>Hemen aç</small>
            </div>
            <span class="quick-action-arrow"><ArrowUpRight :size="13" /></span>
          </button>
        </div>
      </section>

      <!-- 6. Operasyon Sayaçları (Canlı Bento İstatistikleri) -->
      <section v-if="operations.length" class="clay-section">
        <div class="clay-section-head">
          <h2>Operasyon Sayaçları</h2>
          <span class="clay-pill-tag">Canlı Veri</span>
        </div>
        <div class="operations-grid">
          <article v-for="item in statisticCards" :key="item.key" class="operation-tile">
            <header class="operation-tile-top">
              <span class="op-icon-bubble" :style="{ background: operationColors[item.key] || '#11161b' }">
                <component :is="operationIcons[item.key] || Zap" :size="15" />
              </span>
              <span class="op-label">{{ item.label }}</span>
            </header>
            <div class="operation-tile-bottom">
              <strong>{{ item.daily }}</strong>
              <span>Bu ay <b>{{ item.monthly }}</b></span>
            </div>
          </article>
        </div>
      </section>

      <!-- 7. Duyurular Bölümü -->
      <section v-if="dashboard.announcements?.length" class="clay-section">
        <div class="clay-section-head">
          <h2>Duyurular</h2>
          <span class="clay-pill-tag">{{ dashboard.announcements.length }} yeni</span>
        </div>
        <div class="announcements-stack">
          <article
            v-for="announcement in dashboard.announcements.slice(0, 3)"
            :key="`${announcement.date}-${announcement.title}`"
            class="announcement-card"
          >
            <span class="announcement-icon"><Megaphone :size="16" /></span>
            <div class="announcement-body">
              <strong>{{ announcement.title }}</strong>
              <p>{{ announcement.content || 'Duyuru detayları yayınlandı.' }}</p>
            </div>
            <time class="announcement-date">{{ formatDate(announcement.date) }}</time>
          </article>
        </div>
      </section>

      <!-- Yetki Yok Durumu -->
      <div
        v-if="!operations.length && !dashboard.personnel && !dashboard.vehicles && !actions.length"
        class="clay-state-card"
      >
        <ShieldAlert :size="24" />
        <strong>Görüntülenebilir modül bulunmuyor</strong>
        <p>Hesabınıza atanmış modül yetkisi bulunmuyor.</p>
      </div>
    </template>
  </section>
</template>

<style scoped>
/* HomeView view-specific refinements; all core bento & claymorphism classes are centralized in design-system.css */
</style>
