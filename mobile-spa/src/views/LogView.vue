<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import {
  Activity,
  AlertCircle,
  AlertTriangle,
  ArrowLeft,
  Calendar,
  Check,
  ChevronRight,
  Clock,
  Eye,
  Filter,
  Info,
  LogIn,
  RefreshCw,
  Search,
  ShieldAlert,
  ShieldCheck,
  User,
  X,
} from '@lucide/vue'
import { logsApi } from '../services/logs.js'

const router = useRouter()

// Veri Durumları
const logs = ref([])
const stats = ref({
  total: 0,
  critical: 0,
  warning: 0,
  info: 0,
  views: 0,
  logins: 0,
})
const pagination = ref({
  page: 1,
  limit: 30,
  total: 0,
  total_pages: 1,
  has_more: false,
})
const loading = ref(true)
const loadingMore = ref(false)
const error = ref('')

// Meta Filtre Seçenekleri
const actionTypes = ref([])
const users = ref([])

// Filtre Değerleri
// Varsayılan: Son iki günlük kayıtlar (Kullanıcı Talebi)
function getFormattedDate(d) {
  const year = d.getFullYear()
  const month = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

const todayStr = getFormattedDate(new Date())
const yesterday = new Date()
yesterday.setDate(yesterday.getDate() - 1)
const yesterdayStr = getFormattedDate(yesterday)

const datePreset = ref('2days') // '2days', 'today', '7days', '30days', 'all', 'custom'
const dateStart = ref(yesterdayStr)
const dateEnd = ref(todayStr)
const selectedLevel = ref('all') // 'all', '2' (Kritik), '1' (Önemli), '0' (Bilgi), '3' (Sayfa)
const selectedActionType = ref('')
const selectedUserId = ref('')
const search = ref('')
let searchTimer = null

// Modallar / Çekmeceler
const isFilterDrawerOpen = ref(false)
const isDetailDrawerOpen = ref(false)
const selectedLog = ref(null)

// Aktif filtre sayısı rozeti
const activeFilterCount = computed(() => {
  let count = 0
  if (datePreset.value !== '2days') count++
  if (selectedLevel.value !== 'all') count++
  if (selectedActionType.value) count++
  if (selectedUserId.value) count++
  return count
})

// Tarih Önayarı Seçimi
function setDatePreset(preset) {
  datePreset.value = preset
  const now = new Date()
  dateEnd.value = getFormattedDate(now)

  if (preset === '2days') {
    const y = new Date(now)
    y.setDate(y.getDate() - 1)
    dateStart.value = getFormattedDate(y)
  } else if (preset === 'today') {
    dateStart.value = getFormattedDate(now)
  } else if (preset === '7days') {
    const d7 = new Date(now)
    d7.setDate(d7.getDate() - 6)
    dateStart.value = getFormattedDate(d7)
  } else if (preset === '30days') {
    const d30 = new Date(now)
    d30.setDate(d30.getDate() - 29)
    dateStart.value = getFormattedDate(d30)
  } else if (preset === 'all') {
    dateStart.value = ''
    dateEnd.value = ''
  }
  loadLogs(true)
}

// Seviye Filtresi Değişimi
function setLevel(lvl) {
  selectedLevel.value = lvl
  loadLogs(true)
}

// Arama kutusu izleme (300ms debounce)
watch(search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    loadLogs(true)
  }, 300)
})

// Log Listesi Yükleme
async function loadLogs(reset = true) {
  if (reset) {
    loading.value = true
    pagination.value.page = 1
  } else {
    loadingMore.value = true
  }
  error.value = ''

  try {
    const params = {
      page: reset ? 1 : pagination.value.page + 1,
      limit: 30,
      preset: datePreset.value,
      date_start: dateStart.value,
      date_end: dateEnd.value,
      level: selectedLevel.value === 'all' ? '' : selectedLevel.value,
      action_type: selectedActionType.value,
      user_id: selectedUserId.value,
      search: search.value.trim(),
    }

    const res = await logsApi.list(params)
    if (res.success) {
      if (reset) {
        logs.value = res.items || []
      } else {
        logs.value = [...logs.value, ...(res.items || [])]
      }
      stats.value = res.stats || stats.value
      pagination.value = res.pagination || pagination.value
    } else {
      error.value = res.message || 'Log kayıtları alınamadı.'
    }
  } catch (err) {
    error.value = err.message || 'Log kayıtları yüklenirken hata oluştu.'
  } finally {
    loading.value = false
    loadingMore.value = false
  }
}

// Meta Seçenekleri Yükle
async function loadMeta() {
  try {
    const res = await logsApi.meta()
    if (res.success) {
      actionTypes.value = res.action_types || []
      users.value = res.users || []
    }
  } catch {
    // Sessiz hata
  }
}

// Filtre Çekmecesi Aksiyonları
function openFilterDrawer() {
  isFilterDrawerOpen.value = true
}

function closeFilterDrawer() {
  isFilterDrawerOpen.value = false
}

function applyDrawerFilters() {
  closeFilterDrawer()
  loadLogs(true)
}

function resetAllFilters() {
  search.value = ''
  selectedLevel.value = 'all'
  selectedActionType.value = ''
  selectedUserId.value = ''
  setDatePreset('2days')
  closeFilterDrawer()
}

// Detay Çekmecesi Aksiyonları
function openDetail(item) {
  selectedLog.value = item
  isDetailDrawerOpen.value = true
}

function closeDetail() {
  isDetailDrawerOpen.value = false
  selectedLog.value = null
}

// Kart Teması Yardımcıları
function getCardIcon(item) {
  if (item.is_login) return LogIn
  if (item.level === 2) return AlertCircle
  if (item.level === 1) return AlertTriangle
  if (item.level === 3) return Eye
  return Info
}

function getCardThemeClass(item) {
  if (item.level === 2) return 'theme-critical'
  if (item.level === 1) return 'theme-warning'
  if (item.is_login) return 'theme-login'
  if (item.level === 3) return 'theme-view'
  return 'theme-info'
}

onMounted(() => {
  loadLogs(true)
  loadMeta()
})
</script>

<template>
  <section class="ds-screen log-screen">
    <!-- 1. Üst Başlık & Aksiyon Çubuğu -->
    <header class="log-topbar">
      <button type="button" class="topbar-btn" aria-label="Geri dön" @click="router.back()">
        <ArrowLeft :size="22" />
      </button>

      <div class="topbar-title">
        <span>GÜVENLİK & DENETİM</span>
        <h1>Sistem Logları</h1>
      </div>

      <div class="topbar-actions">
        <button
          type="button"
          class="topbar-btn"
          aria-label="Filtrele"
          :class="{ 'has-active': activeFilterCount > 0 }"
          @click="openFilterDrawer"
        >
          <Filter :size="19" />
          <span v-if="activeFilterCount > 0" class="topbar-badge">{{ activeFilterCount }}</span>
        </button>

        <button
          type="button"
          class="topbar-btn"
          aria-label="Yenile"
          :class="{ spinning: loading }"
          @click="loadLogs(true)"
        >
          <RefreshCw :size="19" />
        </button>
      </div>
    </header>

    <!-- 2. Hero Obsidian / Bento KPI Kartları -->
    <div class="log-kpi-grid">
      <!-- Toplam Kayıt -->
      <div class="log-kpi-card log-kpi-card--primary" @click="setLevel('all')">
        <div class="log-kpi-header">
          <span class="log-kpi-icon"><Activity :size="16" /></span>
          <span class="log-kpi-label">Toplam Kayıt</span>
        </div>
        <strong class="log-kpi-value">{{ stats.total }}</strong>
        <span class="log-kpi-foot">Seçilen aralıkta</span>
      </div>

      <!-- Kritik Olaylar -->
      <div
        class="log-kpi-card log-kpi-card--danger"
        :class="{ active: selectedLevel === '2' }"
        @click="setLevel(selectedLevel === '2' ? 'all' : '2')"
      >
        <div class="log-kpi-header">
          <span class="log-kpi-icon"><AlertCircle :size="16" /></span>
          <span class="log-kpi-label">Kritik Olay</span>
        </div>
        <strong class="log-kpi-value">{{ stats.critical }}</strong>
        <span class="log-kpi-foot">{{ selectedLevel === '2' ? 'Filtrelendi' : 'Seviye 2' }}</span>
      </div>

      <!-- Önemli / Uyarılar -->
      <div
        class="log-kpi-card log-kpi-card--warning"
        :class="{ active: selectedLevel === '1' }"
        @click="setLevel(selectedLevel === '1' ? 'all' : '1')"
      >
        <div class="log-kpi-header">
          <span class="log-kpi-icon"><AlertTriangle :size="16" /></span>
          <span class="log-kpi-label">Önemli İşlem</span>
        </div>
        <strong class="log-kpi-value">{{ stats.warning }}</strong>
        <span class="log-kpi-foot">{{ selectedLevel === '1' ? 'Filtrelendi' : 'Seviye 1' }}</span>
      </div>

      <!-- Başarılı Girişler -->
      <div class="log-kpi-card log-kpi-card--success" @click="openFilterDrawer">
        <div class="log-kpi-header">
          <span class="log-kpi-icon"><LogIn :size="16" /></span>
          <span class="log-kpi-label">Girişler</span>
        </div>
        <strong class="log-kpi-value">{{ stats.logins }}</strong>
        <span class="log-kpi-foot">Oturum kaydı</span>
      </div>
    </div>

    <!-- 3. Canlı Arama ve Hızlı Filtre Hapları -->
    <div class="log-controls-card">
      <!-- Arama Kutusu -->
      <div class="log-search-wrap">
        <Search class="log-search-icon" :size="18" />
        <input
          v-model="search"
          type="text"
          class="log-search-input"
          placeholder="İşlem, kullanıcı, açıklama veya IP ara..."
          aria-label="Loglarda ara"
        />
        <button
          v-if="search"
          type="button"
          class="log-search-clear"
          aria-label="Aramayı temizle"
          @click="search = ''"
        >
          <X :size="16" />
        </button>
      </div>

      <!-- Hızlı Tarih Hapları (Varsayılan: Son 2 Gün) -->
      <div class="log-pills-row">
        <span class="log-pills-label">ZAMAN:</span>
        <button
          type="button"
          class="log-pill"
          :class="{ active: datePreset === '2days' }"
          @click="setDatePreset('2days')"
        >
          Son 2 Gün
        </button>
        <button
          type="button"
          class="log-pill"
          :class="{ active: datePreset === 'today' }"
          @click="setDatePreset('today')"
        >
          Bugün
        </button>
        <button
          type="button"
          class="log-pill"
          :class="{ active: datePreset === '7days' }"
          @click="setDatePreset('7days')"
        >
          Son 7 Gün
        </button>
        <button
          type="button"
          class="log-pill"
          :class="{ active: datePreset === '30days' }"
          @click="setDatePreset('30days')"
        >
          Son 30 Gün
        </button>
        <button
          type="button"
          class="log-pill"
          :class="{ active: datePreset === 'all' }"
          @click="setDatePreset('all')"
        >
          Tüm Zamanlar
        </button>
      </div>

      <!-- Hızlı Seviye Hapları -->
      <div class="log-pills-row">
        <span class="log-pills-label">SEVİYE:</span>
        <button
          type="button"
          class="log-pill"
          :class="{ active: selectedLevel === 'all' }"
          @click="setLevel('all')"
        >
          Tümü
        </button>
        <button
          type="button"
          class="log-pill log-pill--danger"
          :class="{ active: selectedLevel === '2' }"
          @click="setLevel('2')"
        >
          Kritik
        </button>
        <button
          type="button"
          class="log-pill log-pill--warning"
          :class="{ active: selectedLevel === '1' }"
          @click="setLevel('1')"
        >
          Önemli
        </button>
        <button
          type="button"
          class="log-pill log-pill--info"
          :class="{ active: selectedLevel === '0' }"
          @click="setLevel('0')"
        >
          Bilgi
        </button>
        <button
          type="button"
          class="log-pill log-pill--view"
          :class="{ active: selectedLevel === '3' }"
          @click="setLevel('3')"
        >
          Sayfa Ziyareti
        </button>
      </div>
    </div>

    <!-- 4. Log Listesi -->
    <div class="log-list-wrap">
      <!-- Yükleniyor Durumu -->
      <div v-if="loading" class="log-empty-state">
        <RefreshCw class="spinning" :size="32" />
        <p>Log kayıtları yükleniyor...</p>
      </div>

      <!-- Hata Durumu -->
      <div v-else-if="error" class="log-empty-state log-empty-state--error">
        <AlertCircle :size="36" />
        <p>{{ error }}</p>
        <button type="button" class="retry-btn" @click="loadLogs(true)">Tekrar Dene</button>
      </div>

      <!-- Boş Durum -->
      <div v-else-if="logs.length === 0" class="log-empty-state">
        <ShieldCheck :size="40" />
        <h3>Kayıt Bulunamadı</h3>
        <p>Seçilen kriterlere ve tarih aralığına uygun sistem logu bulunamadı.</p>
        <button type="button" class="reset-filter-btn" @click="resetAllFilters">
          Filtreleri Sıfırla (Son 2 Gün)
        </button>
      </div>

      <!-- Liste Kartları -->
      <div v-else class="log-cards-container">
        <article
          v-for="item in logs"
          :key="item.id"
          class="log-card"
          :class="getCardThemeClass(item)"
          @click="openDetail(item)"
        >
          <!-- Sol İkon -->
          <div class="log-card-icon-box">
            <component :is="getCardIcon(item)" :size="20" />
          </div>

          <!-- Ana İçerik -->
          <div class="log-card-body">
            <div class="log-card-top">
              <span class="log-card-type">{{ item.action_type }}</span>
              <span class="log-card-time">{{ item.relative_time }}</span>
            </div>

            <!-- Kullanıcı ve IP -->
            <div class="log-card-meta">
              <span class="log-user-badge">
                <span class="log-user-avatar">{{ item.user_initials }}</span>
                <span class="log-user-name">{{ item.user_name }}</span>
              </span>
              <span v-if="item.ip_address" class="log-ip-badge">
                {{ item.ip_address }}
              </span>
              <span class="log-level-pill" :class="`pill-${item.level_color}`">
                {{ item.level_name }}
              </span>
            </div>

            <!-- Açıklama Özeti -->
            <p class="log-card-desc">
              {{ item.description }}
            </p>
          </div>

          <ChevronRight class="log-card-arrow" :size="18" />
        </article>

        <!-- Daha Fazla Yükle Butonu -->
        <div v-if="pagination.has_more" class="load-more-wrap">
          <button
            type="button"
            class="load-more-btn"
            :disabled="loadingMore"
            @click="loadLogs(false)"
          >
            <RefreshCw v-if="loadingMore" class="spinning" :size="18" />
            <span>{{ loadingMore ? 'Yükleniyor...' : 'Daha Fazla Kayıt Yükle' }}</span>
          </button>
        </div>

        <div v-else-if="logs.length > 0" class="log-list-end">
          <span>Toplam {{ pagination.total }} kaydın tamamı listelendi</span>
        </div>
      </div>
    </div>

    <!-- 5. Gelişmiş Filtre Çekmecesi (Filter Sheet) -->
    <transition name="fade">
      <div v-if="isFilterDrawerOpen" class="drawer-overlay" @click="closeFilterDrawer" />
    </transition>

    <transition name="slide-up">
      <div v-if="isFilterDrawerOpen" class="log-drawer" role="dialog" aria-modal="true">
        <header class="drawer-header">
          <div class="drawer-header-title">
            <Filter :size="20" />
            <h2>Detaylı Filtreleme</h2>
          </div>
          <button type="button" class="drawer-close-btn" aria-label="Kapat" @click="closeFilterDrawer">
            <X :size="20" />
          </button>
        </header>

        <div class="drawer-body">
          <!-- Tarih Aralığı -->
          <div class="drawer-group">
            <label class="drawer-label"><Calendar :size="15" /> Tarih Aralığı</label>
            <div class="date-inputs-row">
              <div class="date-input-field">
                <span>Başlangıç:</span>
                <input v-model="dateStart" type="date" class="drawer-input" />
              </div>
              <div class="date-input-field">
                <span>Bitiş:</span>
                <input v-model="dateEnd" type="date" class="drawer-input" />
              </div>
            </div>
          </div>

          <!-- İşlem Tipi Dropdown -->
          <div class="drawer-group">
            <label class="drawer-label"><Activity :size="15" /> İşlem Tipi</label>
            <select v-model="selectedActionType" class="drawer-select">
              <option value="">Tüm İşlem Tipleri</option>
              <option v-for="type in actionTypes" :key="type" :value="type">
                {{ type }}
              </option>
            </select>
          </div>

          <!-- Kullanıcı Dropdown -->
          <div class="drawer-group">
            <label class="drawer-label"><User :size="15" /> Kullanıcı</label>
            <select v-model="selectedUserId" class="drawer-select">
              <option value="">Tüm Kullanıcılar</option>
              <option v-for="u in users" :key="u.id" :value="u.id">
                {{ u.name }}
              </option>
            </select>
          </div>

          <!-- Log Seviyesi -->
          <div class="drawer-group">
            <label class="drawer-label"><AlertCircle :size="15" /> Log Seviyesi</label>
            <select v-model="selectedLevel" class="drawer-select">
              <option value="all">Tüm Seviyeler</option>
              <option value="2">Kritik (Seviye 2)</option>
              <option value="1">Önemli / Uyarı (Seviye 1)</option>
              <option value="0">Bilgilendirme (Seviye 0)</option>
              <option value="3">Sayfa Ziyareti (Seviye 3)</option>
            </select>
          </div>
        </div>

        <footer class="drawer-footer">
          <button type="button" class="drawer-btn drawer-btn--secondary" @click="resetAllFilters">
            Sıfırla
          </button>
          <button type="button" class="drawer-btn drawer-btn--primary" @click="applyDrawerFilters">
            Filtreleri Uygula
          </button>
        </footer>
      </div>
    </transition>

    <!-- 6. Log Detay Çekmecesi (Detail Sheet) -->
    <transition name="fade">
      <div v-if="isDetailDrawerOpen" class="drawer-overlay" @click="closeDetail" />
    </transition>

    <transition name="slide-up">
      <div v-if="isDetailDrawerOpen && selectedLog" class="log-drawer log-detail-drawer" role="dialog" aria-modal="true">
        <header class="drawer-header">
          <div class="drawer-header-title">
            <span class="detail-id-badge">#{{ selectedLog.id }}</span>
            <span class="log-level-pill" :class="`pill-${selectedLog.level_color}`">
              {{ selectedLog.level_name }}
            </span>
          </div>
          <button type="button" class="drawer-close-btn" aria-label="Kapat" @click="closeDetail">
            <X :size="20" />
          </button>
        </header>

        <div class="drawer-body">
          <h2 class="detail-action-title">{{ selectedLog.action_type }}</h2>

          <!-- Bilgi Kartları Grid -->
          <div class="detail-info-grid">
            <div class="detail-info-card">
              <span class="detail-info-label"><Clock :size="14" /> Tarih / Zaman</span>
              <strong class="detail-info-val">{{ selectedLog.formatted_date }} {{ selectedLog.formatted_time }}</strong>
              <small class="detail-info-sub">{{ selectedLog.relative_time }}</small>
            </div>

            <div class="detail-info-card">
              <span class="detail-info-label"><User :size="14" /> İşlemi Yapan</span>
              <strong class="detail-info-val">{{ selectedLog.user_name }}</strong>
              <small v-if="selectedLog.username" class="detail-info-sub">@{{ selectedLog.username }}</small>
            </div>

            <div v-if="selectedLog.ip_address" class="detail-info-card detail-info-card--full">
              <span class="detail-info-label"><Activity :size="14" /> IP Adresi</span>
              <strong class="detail-info-val font-mono">{{ selectedLog.ip_address }}</strong>
            </div>
          </div>

          <!-- Açıklama Kutusu -->
          <div class="detail-desc-wrap">
            <span class="detail-desc-label">Kayıt Açıklaması &amp; Detayı</span>
            <div class="detail-desc-box">
              {{ selectedLog.description || 'Açıklama detayı bulunmuyor.' }}
            </div>
          </div>
        </div>

        <footer class="drawer-footer">
          <button type="button" class="drawer-btn drawer-btn--primary w-100" @click="closeDetail">
            Kapat
          </button>
        </footer>
      </div>
    </transition>
  </section>
</template>

<style scoped>
.log-screen {
  padding: 16px 16px 90px;
  background-color: var(--ds-canvas, var(--dsx-bg-e6eaef));
  min-height: 100vh;
  box-sizing: border-box;
}

/* 1. Topbar */
.log-topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 16px;
}

.topbar-btn {
  width: 44px;
  height: 44px;
  border-radius: 14px;
  border: 1px solid var(--dsx-bd-ffffff-a090);
  background: var(--dsx-bg-ffffff);
  color: var(--dsx-fg-1e293b);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 12px rgba(15, 23, 42, 0.05);
  cursor: pointer;
  position: relative;
  transition: all 0.2s ease;
  flex-shrink: 0;
}

.topbar-btn:active {
  transform: scale(0.95);
}

.topbar-btn.has-active {
  background: var(--dsx-bg-eff6ff);
  color: #2563eb;
  border-color: var(--dsx-bd-bfdbfe);
}

.topbar-badge {
  position: absolute;
  top: -4px;
  right: -4px;
  background: #ef4444;
  color: #ffffff;
  font-size: 10px;
  font-weight: 700;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  display: grid;
  place-items: center;
}

.topbar-title {
  flex: 1;
  text-align: center;
}

.topbar-title span {
  display: block;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.06em;
  color: var(--dsx-fg-64748b);
  text-transform: uppercase;
}

.topbar-title h1 {
  font-size: 18px;
  font-weight: 800;
  color: var(--dsx-fg-0f172a);
  margin: 0;
  line-height: 1.2;
}

.topbar-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

/* 2. Bento KPI Grid */
.log-kpi-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 10px;
  margin-bottom: 14px;
}

.log-kpi-card {
  background: var(--dsx-bg-ffffff);
  border-radius: 20px;
  padding: 14px 14px 12px;
  border: 1px solid var(--dsx-bd-ffffff-a090);
  box-shadow: 0 6px 18px rgba(15, 23, 42, 0.04);
  cursor: pointer;
  transition: all 0.2s ease;
  display: flex;
  flex-direction: column;
}

.log-kpi-card:active {
  transform: scale(0.97);
}

.log-kpi-header {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 6px;
}

.log-kpi-icon {
  width: 28px;
  height: 28px;
  border-radius: 9px;
  display: grid;
  place-items: center;
}

.log-kpi-label {
  font-size: 11px;
  font-weight: 700;
  color: var(--dsx-fg-64748b);
  text-transform: uppercase;
  letter-spacing: 0.02em;
}

.log-kpi-value {
  font-size: 24px;
  font-weight: 800;
  line-height: 1.1;
  margin-bottom: 4px;
}

.log-kpi-foot {
  font-size: 10px;
  color: var(--dsx-fg-94a3b8);
  font-weight: 600;
}

/* KPI Kart Renk Temaları */
.log-kpi-card--primary .log-kpi-icon { background: var(--dsx-bg-eff6ff); color: #2563eb; }
.log-kpi-card--primary .log-kpi-value { color: var(--dsx-fg-1d4ed8); }

.log-kpi-card--danger .log-kpi-icon { background: var(--dsx-bg-fef2f2); color: #dc2626; }
.log-kpi-card--danger .log-kpi-value { color: var(--dsx-fg-b91c1c); }
.log-kpi-card--danger.active { border-color: #ef4444; background: var(--dsx-bg-fff5f5); }

.log-kpi-card--warning .log-kpi-icon { background: var(--dsx-bg-fffbeb); color: var(--dsx-fg-d97706); }
.log-kpi-card--warning .log-kpi-value { color: var(--dsx-fg-b45309); }
.log-kpi-card--warning.active { border-color: #f59e0b; background: var(--dsx-bg-fffdf5); }

.log-kpi-card--success .log-kpi-icon { background: var(--dsx-bg-ecfdf5); color: var(--dsx-fg-059669); }
.log-kpi-card--success .log-kpi-value { color: var(--dsx-fg-047857); }

/* 3. Arama ve Filtre Hapları Kartı */
.log-controls-card {
  background: var(--dsx-bg-ffffff);
  border-radius: 22px;
  padding: 14px;
  border: 1px solid var(--dsx-bd-ffffff-a090);
  box-shadow: 0 6px 18px rgba(15, 23, 42, 0.04);
  margin-bottom: 14px;
}

.log-search-wrap {
  position: relative;
  display: flex;
  align-items: center;
  margin-bottom: 12px;
}

.log-search-icon {
  position: absolute;
  left: 12px;
  color: var(--dsx-fg-94a3b8);
  pointer-events: none;
}

.log-search-input {
  width: 100%;
  height: 42px;
  padding: 0 38px 0 38px;
  border-radius: 12px;
  border: 1px solid var(--dsx-bd-e2e8f0);
  background: var(--dsx-bg-f8fafc);
  font-size: 13px;
  font-family: inherit;
  color: var(--dsx-fg-1e293b);
  outline: none;
  transition: all 0.2s ease;
  box-sizing: border-box;
}

.log-search-input:focus {
  border-color: #3b82f6;
  background: var(--dsx-bg-ffffff);
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12);
}

.log-search-clear {
  position: absolute;
  right: 10px;
  background: transparent;
  border: none;
  color: var(--dsx-fg-94a3b8);
  display: grid;
  place-items: center;
  padding: 4px;
  cursor: pointer;
}

/* Haplar Row */
.log-pills-row {
  display: flex;
  align-items: center;
  gap: 6px;
  overflow-x: auto;
  padding-bottom: 6px;
  scrollbar-width: none;
}
.log-pills-row::-webkit-scrollbar {
  display: none;
}
.log-pills-row:last-child {
  padding-bottom: 0;
  margin-top: 6px;
  border-top: 1px dashed var(--dsx-bd-f1f5f9);
  padding-top: 8px;
}

.log-pills-label {
  font-size: 10px;
  font-weight: 800;
  color: var(--dsx-fg-94a3b8);
  letter-spacing: 0.05em;
  white-space: nowrap;
  margin-right: 4px;
}

.log-pill {
  border: 1px solid var(--dsx-bd-e2e8f0);
  background: var(--dsx-bg-f8fafc);
  color: var(--dsx-fg-475569);
  border-radius: 999px;
  padding: 5px 12px;
  font-size: 11px;
  font-weight: 700;
  white-space: nowrap;
  cursor: pointer;
  transition: all 0.2s ease;
  font-family: inherit;
  min-height: 28px;
}

.log-pill:active {
  transform: scale(0.96);
}

.log-pill.active {
  background: var(--dsx-bg-1e293b);
  color: #ffffff;
  border-color: #1e293b;
  box-shadow: 0 2px 8px rgba(15, 23, 42, 0.15);
}

.log-pill--danger.active {
  background: #dc2626;
  border-color: #dc2626;
  color: #ffffff;
}

.log-pill--warning.active {
  background: #d97706;
  border-color: #d97706;
  color: #ffffff;
}

.log-pill--info.active {
  background: #2563eb;
  border-color: #2563eb;
  color: #ffffff;
}

.log-pill--view.active {
  background: #64748b;
  border-color: #64748b;
  color: #ffffff;
}

/* 4. Log Listesi & Kartları */
.log-cards-container {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.log-card {
  background: var(--dsx-bg-ffffff);
  border-radius: 18px;
  padding: 12px 14px;
  border: 1px solid var(--dsx-bd-ffffff-a090);
  box-shadow: 0 4px 14px rgba(15, 23, 42, 0.03);
  display: flex;
  align-items: center;
  gap: 12px;
  cursor: pointer;
  transition: all 0.2s ease;
  position: relative;
  overflow: hidden;
}

.log-card::before {
  content: '';
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
  width: 4px;
  background: #94a3b8;
}

.log-card.theme-critical::before { background: #ef4444; }
.log-card.theme-warning::before { background: #f59e0b; }
.log-card.theme-login::before { background: #10b981; }
.log-card.theme-view::before { background: #94a3b8; }
.log-card.theme-info::before { background: #3b82f6; }

.log-card:active {
  transform: scale(0.98);
}

.log-card-icon-box {
  width: 38px;
  height: 38px;
  border-radius: 12px;
  display: grid;
  place-items: center;
  flex-shrink: 0;
  background: var(--dsx-bg-f1f5f9);
  color: var(--dsx-fg-475569);
}

.log-card.theme-critical .log-card-icon-box { background: var(--dsx-bg-fef2f2); color: #dc2626; }
.log-card.theme-warning .log-card-icon-box { background: var(--dsx-bg-fffbeb); color: var(--dsx-fg-d97706); }
.log-card.theme-login .log-card-icon-box { background: var(--dsx-bg-ecfdf5); color: var(--dsx-fg-059669); }
.log-card.theme-info .log-card-icon-box { background: var(--dsx-bg-eff6ff); color: #2563eb; }

.log-card-body {
  flex: 1;
  min-width: 0;
}

.log-card-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 4px;
}

.log-card-type {
  font-size: 13px;
  font-weight: 750;
  color: var(--dsx-fg-0f172a);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.log-card-time {
  font-size: 10px;
  color: var(--dsx-fg-94a3b8);
  font-weight: 600;
  white-space: nowrap;
  flex-shrink: 0;
}

.log-card-meta {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
  margin-bottom: 5px;
}

.log-user-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  background: var(--dsx-bg-f1f5f9);
  border-radius: 999px;
  padding: 2px 7px 2px 2px;
}

.log-user-avatar {
  width: 17px;
  height: 17px;
  border-radius: 50%;
  background: #3b82f6;
  color: #ffffff;
  font-size: 9px;
  font-weight: 800;
  display: grid;
  place-items: center;
}

.log-user-name {
  font-size: 11px;
  font-weight: 650;
  color: var(--dsx-fg-334155);
  white-space: nowrap;
  max-width: 120px;
  overflow: hidden;
  text-overflow: ellipsis;
}

.log-ip-badge {
  font-size: 10px;
  font-family: monospace;
  background: var(--dsx-bg-f8fafc);
  border: 1px solid var(--dsx-bd-e2e8f0);
  color: var(--dsx-fg-64748b);
  border-radius: 5px;
  padding: 1px 5px;
}

.log-level-pill {
  font-size: 9px;
  font-weight: 800;
  text-transform: uppercase;
  border-radius: 5px;
  padding: 2px 6px;
}

.pill-danger { background: var(--dsx-bg-fee2e2); color: var(--dsx-fg-b91c1c); }
.pill-warning { background: var(--dsx-bg-fef3c7); color: var(--dsx-fg-b45309); }
.pill-info { background: var(--dsx-bg-e0f2fe); color: var(--dsx-fg-0369a1); }
.pill-secondary { background: var(--dsx-bg-f1f5f9); color: var(--dsx-fg-64748b); }

.log-card-desc {
  margin: 0;
  font-size: 11.5px;
  color: var(--dsx-fg-64748b);
  line-height: 1.35;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.log-card-arrow {
  color: #cbd5e1;
  flex-shrink: 0;
}

/* Yükleniyor / Boş Durum */
.log-empty-state {
  background: var(--dsx-bg-ffffff);
  border-radius: 22px;
  padding: 36px 20px;
  text-align: center;
  color: var(--dsx-fg-64748b);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  box-shadow: 0 4px 14px rgba(15, 23, 42, 0.03);
}

.log-empty-state h3 {
  font-size: 16px;
  font-weight: 800;
  color: var(--dsx-fg-1e293b);
  margin: 0;
}

.log-empty-state p {
  font-size: 12px;
  margin: 0;
  max-width: 280px;
}

.reset-filter-btn, .retry-btn {
  margin-top: 8px;
  background: #2563eb;
  color: #ffffff;
  border: none;
  border-radius: 12px;
  padding: 8px 16px;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  font-family: inherit;
}

/* Daha Fazla Yükle */
.load-more-wrap {
  text-align: center;
  margin-top: 14px;
}

.load-more-btn {
  width: 100%;
  height: 44px;
  background: var(--dsx-bg-ffffff);
  border: 1px solid var(--dsx-bd-cbd5e1);
  border-radius: 14px;
  color: var(--dsx-fg-334155);
  font-size: 13px;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  cursor: pointer;
  font-family: inherit;
  box-shadow: 0 4px 12px rgba(15, 23, 42, 0.03);
  transition: all 0.2s ease;
}

.load-more-btn:active {
  transform: scale(0.98);
}

.log-list-end {
  text-align: center;
  font-size: 11px;
  color: var(--dsx-fg-94a3b8);
  margin-top: 16px;
  font-weight: 600;
}

/* 5 & 6. Çekmeceler (Bottom Sheets / Drawers) */
.drawer-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.5);
  backdrop-filter: blur(4px);
  z-index: 1000;
}

.log-drawer {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  background: var(--dsx-bg-ffffff);
  border-top-left-radius: 28px;
  border-top-right-radius: 28px;
  box-shadow: 0 -8px 32px rgba(15, 23, 42, 0.16);
  z-index: 1001;
  max-height: 85vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  box-sizing: border-box;
}

.drawer-header {
  padding: 16px 20px;
  border-bottom: 1px solid var(--dsx-bd-f1f5f9);
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.drawer-header-title {
  display: flex;
  align-items: center;
  gap: 8px;
}

.drawer-header-title h2 {
  font-size: 16px;
  font-weight: 800;
  color: var(--dsx-fg-0f172a);
  margin: 0;
}

.drawer-close-btn {
  width: 34px;
  height: 34px;
  border-radius: 50%;
  border: none;
  background: var(--dsx-bg-f1f5f9);
  color: var(--dsx-fg-64748b);
  display: grid;
  place-items: center;
  cursor: pointer;
}

.drawer-body {
  padding: 18px 20px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.drawer-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.drawer-label {
  font-size: 12px;
  font-weight: 700;
  color: var(--dsx-fg-475569);
  display: flex;
  align-items: center;
  gap: 6px;
}

.date-inputs-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}

.date-input-field {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.date-input-field span {
  font-size: 11px;
  color: var(--dsx-fg-64748b);
  font-weight: 600;
}

.drawer-input, .drawer-select {
  height: 42px;
  border-radius: 12px;
  border: 1px solid var(--dsx-bd-cbd5e1);
  background: var(--dsx-bg-f8fafc);
  padding: 0 12px;
  font-size: 13px;
  font-family: inherit;
  color: var(--dsx-fg-1e293b);
  outline: none;
}

.drawer-input:focus, .drawer-select:focus {
  border-color: #3b82f6;
  background: var(--dsx-bg-ffffff);
}

.drawer-footer {
  padding: 14px 20px;
  border-top: 1px solid var(--dsx-bd-f1f5f9);
  display: flex;
  gap: 10px;
}

.drawer-btn {
  flex: 1;
  height: 44px;
  border-radius: 12px;
  font-size: 13px;
  font-weight: 750;
  cursor: pointer;
  border: none;
  font-family: inherit;
}

.drawer-btn--primary {
  background: var(--dsx-bg-1e293b);
  color: #ffffff;
}

.drawer-btn--secondary {
  background: var(--dsx-bg-f1f5f9);
  color: var(--dsx-fg-475569);
}

/* Detay Çekmecesi */
.detail-id-badge {
  font-size: 12px;
  font-family: monospace;
  font-weight: 700;
  color: var(--dsx-fg-64748b);
  background: var(--dsx-bg-f1f5f9);
  padding: 3px 8px;
  border-radius: 6px;
}

.detail-action-title {
  font-size: 18px;
  font-weight: 850;
  color: var(--dsx-fg-0f172a);
  margin: 0;
  line-height: 1.25;
}

.detail-info-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}

.detail-info-card {
  background: var(--dsx-bg-f8fafc);
  border: 1px solid var(--dsx-bd-e2e8f0);
  border-radius: 14px;
  padding: 10px 12px;
  display: flex;
  flex-direction: column;
}

.detail-info-card--full {
  grid-column: 1 / -1;
}

.detail-info-label {
  font-size: 10.5px;
  font-weight: 700;
  color: var(--dsx-fg-64748b);
  text-transform: uppercase;
  margin-bottom: 4px;
  display: flex;
  align-items: center;
  gap: 5px;
}

.detail-info-val {
  font-size: 13px;
  color: var(--dsx-fg-1e293b);
  font-weight: 750;
}

.detail-info-sub {
  font-size: 11px;
  color: var(--dsx-fg-94a3b8);
  margin-top: 2px;
}

.detail-desc-wrap {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.detail-desc-label {
  font-size: 11px;
  font-weight: 750;
  color: var(--dsx-fg-475569);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.detail-desc-box {
  background: var(--dsx-bg-f8fafc);
  border: 1px solid var(--dsx-bd-e2e8f0);
  border-radius: 14px;
  padding: 12px 14px;
  font-size: 13px;
  line-height: 1.5;
  color: var(--dsx-fg-1e293b);
  white-space: pre-wrap;
  word-break: break-word;
}

.font-mono {
  font-family: monospace;
}

.spinning {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

/* Animasyonlar */
.fade-enter-active, .fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from, .fade-leave-to {
  opacity: 0;
}

.slide-up-enter-active, .slide-up-leave-active {
  transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}
.slide-up-enter-from, .slide-up-leave-to {
  transform: translateY(100%);
}
</style>
