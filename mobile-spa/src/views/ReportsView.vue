<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import {
  AlertCircle,
  ArrowLeft,
  ArrowUpRight,
  BarChart3,
  Calendar,
  CalendarDays,
  CalendarRange,
  CheckCircle,
  ChevronDown,
  ChevronRight,
  Clock,
  ExternalLink,
  Filter,
  Gauge,
  Layers,
  MapPin,
  PieChart,
  Receipt,
  RefreshCw,
  Search,
  ShieldAlert,
  ShieldCheck,
  Sparkles,
  TrendingUp,
  Trophy,
  User,
  Users,
  UsersRound,
  Wrench,
  X,
  XCircle,
  Zap,
  ZapOff,
} from '@lucide/vue'
import TopBar from '../components/TopBar.vue'
import {
  applyCustomDateRange,
  closeCardDetail,
  closeDatePicker,
  closeDistributionModal,
  loadDistribution,
  loadReports,
  openCardDetail,
  openDatePicker,
  openDistributionModal,
  openReportsDrawer,
  reportsState,
  setDistributionFilter,
  setDistributionView,
  setReportsTab,
} from '../services/reports.js'

// Custom Date Picker Modalı İçin Geçici Tarihler
const customStartInput = ref(reportsState.startDate)
const customEndInput = ref(reportsState.endDate)

// Ay seçimi için ay listesi
const monthsList = [
  { val: '01', label: 'Ocak' },
  { val: '02', label: 'Şubat' },
  { val: '03', label: 'Mart' },
  { val: '04', label: 'Nisan' },
  { val: '05', label: 'Mayıs' },
  { val: '06', label: 'Haziran' },
  { val: '07', label: 'Temmuz' },
  { val: '08', label: 'Ağustos' },
  { val: '09', label: 'Eylül' },
  { val: '10', label: 'Ekim' },
  { val: '11', label: 'Kasım' },
  { val: '12', label: 'Aralık' },
]

const selectedYear = ref(new Date().getFullYear())

// Bölge Akordiyon Açık/Kapalı Durum Haritası
const expandedRegions = ref({})

// Aktif Sekme Birimi
const currentUnit = computed(() => {
  const current = reportsState.tabs.find((t) => t.id === reportsState.activeTab)
  return current?.unit || 'İşlem'
})

// Belirli bir ayı seçme
function selectMonth(mVal) {
  const y = selectedYear.value
  const startDate = `${y}-${mVal}-01`
  const lastDay = new Date(y, parseInt(mVal, 10), 0).getDate()
  const endDate = `${y}-${mVal}-${String(lastDay).padStart(2, '0')}`

  const monthObj = monthsList.find((m) => m.val === mVal)
  const label = `${monthObj?.label || mVal} ${y}`
  applyCustomDateRange(startDate, endDate, label)
}

// Özel Tarih Aralığını Uygula
function applyCustomDate() {
  if (!customStartInput.value || !customEndInput.value) return
  applyCustomDateRange(customStartInput.value, customEndInput.value, 'custom')
}

// Hazır Tarih Seçeneği Uygula (Modal İçinden)
function selectPresetInModal(preset) {
  loadReports(preset)
  closeDatePicker()
}

// Akordiyon Bölge Aç/Kapa
function toggleRegion(regName) {
  expandedRegions.value[regName] = !expandedRegions.value[regName]
}

// Tüm Bölgeleri Aç/Kapat
const allRegionsExpanded = computed(() => {
  if (groupedRegions.value.length === 0) return false
  return groupedRegions.value.every((r) => expandedRegions.value[r.name] === true)
})

function toggleAllRegions() {
  const target = !allRegionsExpanded.value
  for (const r of groupedRegions.value) {
    expandedRegions.value[r.name] = target
  }
}

// Filtrelenmiş Kartlar (Arama ve Sıralama)
const filteredCards = computed(() => {
  let list = [...reportsState.cards]

  // Arama filtresi
  const q = reportsState.searchQuery.trim().toLocaleLowerCase('tr-TR')
  if (q) {
    list = list.filter((c) => {
      const name = (c.name || '').toLocaleLowerCase('tr-TR')
      const team = (c.team || '').toLocaleLowerCase('tr-TR')
      const region = (c.region || '').toLocaleLowerCase('tr-TR')
      return name.includes(q) || team.includes(q) || region.includes(q)
    })
  }

  // Sıralama
  switch (reportsState.sortBy) {
    case 'total-desc':
      list.sort((a, b) => b.total - a.total)
      break
    case 'total-asc':
      list.sort((a, b) => a.total - b.total)
      break
    case 'name-asc':
      list.sort((a, b) => (a.name || '').localeCompare(b.name || '', 'tr-TR'))
      break
    case 'team-asc':
      list.sort((a, b) => (a.team || '').localeCompare(b.team || '', 'tr-TR'))
      break
  }

  return list
})

// Bölgelere Göre Gruplanmış Akordiyon Verisi
const groupedRegions = computed(() => {
  const map = {}

  // 1. Backend'den gelen bölgelerin sırasını koru
  for (const reg of reportsState.regions) {
    map[reg.name] = {
      ...reg,
      cards: [],
    }
  }

  // 2. Filtrelenmiş kartları bölgelerine dağıt
  for (const card of filteredCards.value) {
    const rName = card.region || 'TANIMSIZ'
    if (!map[rName]) {
      map[rName] = {
        name: rName,
        total: 0,
        personnel_count: 0,
        active_count: 0,
        percentage: 0,
        color: '#2563eb',
        cards: [],
      }
    }
    map[rName].cards.push(card)
  }

  // 3. Sadece kartı olan bölgeleri döndür
  return Object.values(map).filter((r) => r.cards.length > 0)
})

// Arama yapıldığında eşleşen bölgeleri otomatik aç; arama temizlendiğinde tekrar kapat
watch(
  () => reportsState.searchQuery,
  (q) => {
    if (q && q.trim()) {
      for (const r of groupedRegions.value) {
        expandedRegions.value[r.name] = true
      }
    } else {
      for (const r of groupedRegions.value) {
        expandedRegions.value[r.name] = false
      }
    }
  }
)

// İlk veri yüklendiğinde ve bölgeler güncellendiğinde tüm bölgeleri varsayılan olarak KAPALI ayarla
watch(
  () => reportsState.regions,
  (regs) => {
    if (regs && regs.length > 0) {
      regs.forEach((r) => {
        if (expandedRegions.value[r.name] === undefined) {
          expandedRegions.value[r.name] = false
        }
      })
    }
  },
  { immediate: true }
)

// Sekme değiştiğinde bölgelerin açık/kapalı durumu sıfırlanıp kapalı başlasın
watch(
  () => reportsState.activeTab,
  () => {
    expandedRegions.value = {}
  }
)

// Tarih aralığı değiştiğinde modal içi özel tarih inputlarını senkronize et
watch(
  () => [reportsState.startDate, reportsState.endDate],
  ([s, e]) => {
    customStartInput.value = s
    customEndInput.value = e
  },
  { immediate: true }
)

// Sayı Formatlama
function formatNumber(num) {
  return new Intl.NumberFormat('tr-TR').format(num || 0)
}

// İsim Baş Harfleri
function getInitials(name) {
  if (!name) return 'EE'
  const parts = name.trim().split(/\s+/)
  if (parts.length === 1) return parts[0].slice(0, 2).toLocaleUpperCase('tr-TR')
  return (parts[0][0] + parts[parts.length - 1][0]).toLocaleUpperCase('tr-TR')
}

// Drawer ESC Dinleyicisi
function handleDrawerKeydown(e) {
  if (e.key === 'Escape') {
    if (reportsState.distributionDrawerOpen) closeDistributionModal()
    if (reportsState.detailDrawerOpen) closeCardDetail()
    if (reportsState.datePickerOpen) closeDatePicker()
  }
}

const route = useRoute()

function applyTabFromRoute() {
  const queryTab = route.query.tab
  if (queryTab && reportsState.tabs.some(t => t.id === queryTab)) {
    reportsState.activeTab = queryTab
  }
}

function getHeroBubbleClass(icon) {
  switch (icon) {
    case 'CheckCircle':
      return 'bg-emerald-soft text-emerald'
    case 'AlertCircle':
    case 'Clock':
      return 'bg-amber-soft text-amber'
    case 'XCircle':
    case 'ShieldAlert':
      return 'bg-coral-soft text-coral'
    case 'Receipt':
    case 'Layers':
      return 'bg-purple-soft text-purple'
    case 'MapPin':
      return 'bg-cyan-soft text-cyan'
    default:
      return 'bg-blue-soft text-blue'
  }
}

watch(
  () => route.query.tab,
  (newTab) => {
    if (newTab && reportsState.tabs.some(t => t.id === newTab)) {
      setReportsTab(newTab)
    }
  }
)

onMounted(() => {
  window.addEventListener('keydown', handleDrawerKeydown)
  applyTabFromRoute()
  loadReports(reportsState.datePreset !== 'custom' ? reportsState.datePreset : null)
})
</script>

<template>
  <section class="ds-screen reports-page">
    <!-- 1. Standart TopBar -->
    <TopBar
      eyebrow="OPERASYONEL ANALİZ"
      title="İşlem Özetleri"
      subtitle="Saha performans ve okuma raporları"
      :back="true"
    >
      <template #actions>
        <button
          type="button"
          class="clay-circle-btn"
          aria-label="Verileri Yenile"
          title="Yenile"
          :disabled="reportsState.loading"
          @click="loadReports()"
        >
          <RefreshCw :size="18" :class="{ 'spin-anim': reportsState.loading }" />
        </button>
      </template>
    </TopBar>

    <!-- 2. Dönem Seçimi Kartı (Hızlı Aralık Kaldırıldı, Sadece Dönem Kartı) -->
    <section class="date-filter-section">
      <div
        class="date-range-badge bento-card bento-card--white cursor-pointer"
        role="button"
        tabindex="0"
        aria-label="Dönem değiştir"
        @click="openDatePicker()"
      >
        <div class="date-range-badge__left">
          <span class="date-icon-box">
            <CalendarRange :size="17" class="text-primary" />
          </span>
          <div>
            <small class="date-label-sub">Rapor Dönemi</small>
            <strong class="date-range-text">{{ reportsState.dateRangeLabel || 'Dönem verileri' }}</strong>
          </div>
        </div>
        <button
          type="button"
          class="date-change-link"
          tabindex="-1"
        >
          <span>Değiştir</span>
          <ChevronDown :size="14" />
        </button>
      </div>
    </section>

    <!-- 3. KPI Bento Kartları (2x2 Grid) -->
    <section class="kpi-grid">
      <!-- 1. Toplam İşlem (Tıklanabilir - Masaüstündeki gibi detaylı dağılım dökümü) -->
      <article
        class="bento-card bento-card--white kpi-card kpi-card--clickable cursor-pointer"
        role="button"
        tabindex="0"
        aria-label="Toplam işlem detaylı dökümünü göster"
        title="Detaylı dökümü görmek için dokunun"
        @click="openDistributionModal"
      >
        <div class="kpi-top">
          <span class="kpi-icon-bubble bg-blue-soft text-blue">
            <Zap :size="18" />
          </span>
          <div class="kpi-tag-group">
            <span class="clay-pill-tag text-xs font-semibold uppercase">{{ currentUnit }}</span>
            <span class="kpi-spark-badge" title="Dağılım Analizi">
              <PieChart :size="11" />
            </span>
          </div>
        </div>
        <div class="kpi-body">
          <small class="kpi-label">Toplam {{ currentUnit }}</small>
          <strong class="kpi-value">{{ formatNumber(reportsState.stats.total_operations) }}</strong>
          <div class="kpi-sub-row">
            <span class="kpi-sub">{{ reportsState.stats.active_personnel }} ekip işlem üretti</span>
            <span class="kpi-action-pill">
              <span>Döküm</span>
              <ChevronRight :size="11" />
            </span>
          </div>
        </div>
      </article>

      <!-- 2. Aktif Ekip / Personel -->
      <article class="bento-card bento-card--white kpi-card">
        <div class="kpi-top">
          <span class="kpi-icon-bubble bg-emerald-soft text-emerald">
            <UsersRound :size="18" />
          </span>
          <span class="clay-pill-tag text-xs font-semibold">KADRO</span>
        </div>
        <div class="kpi-body">
          <small class="kpi-label">Aktif / Toplam Ekip</small>
          <strong class="kpi-value">
            {{ reportsState.stats.active_personnel }}
            <span class="kpi-value-sub">/ {{ reportsState.stats.total_personnel }}</span>
          </strong>
          <span class="kpi-sub">{{ reportsState.stats.total_regions }} bölgede görevli</span>
        </div>
      </article>

      <!-- 3. Lider Bölge -->
      <article class="bento-card bento-card--white kpi-card">
        <div class="kpi-top">
          <span class="kpi-icon-bubble bg-amber-soft text-amber">
            <Trophy :size="18" />
          </span>
          <span v-if="reportsState.stats.top_region" class="clay-pill-tag text-xs font-semibold">
            %{{ reportsState.stats.top_region.percentage }}
          </span>
        </div>
        <div class="kpi-body">
          <small class="kpi-label">En Yüksek Bölge</small>
          <strong class="kpi-value text-truncate" :title="reportsState.stats.top_region?.name || '-'">
            {{ reportsState.stats.top_region?.name || '-' }}
          </strong>
          <span class="kpi-sub">
            {{ reportsState.stats.top_region ? `${formatNumber(reportsState.stats.top_region.total)} ${currentUnit}` : 'Veri yok' }}
          </span>
        </div>
      </article>

      <!-- 4. Kişi Başı Ortalama -->
      <article class="bento-card bento-card--white kpi-card">
        <div class="kpi-top">
          <span class="kpi-icon-bubble bg-purple-soft text-purple">
            <TrendingUp :size="18" />
          </span>
          <span class="clay-pill-tag text-xs font-semibold">ORTALAMA</span>
        </div>
        <div class="kpi-body">
          <small class="kpi-label">Kişi Başı Üretim</small>
          <strong class="kpi-value">{{ formatNumber(reportsState.stats.avg_per_active) }}</strong>
          <span class="kpi-sub">Aktif ekip ortalaması</span>
        </div>
      </article>
    </section>

    <!-- 4. Arama, Sıralama ve Akordiyon Kontrol Barı -->
    <section class="search-filter-section">
      <div class="search-row">
        <label class="clay-search-bar flex-1">
          <Search :size="18" />
          <input
            v-model="reportsState.searchQuery"
            type="search"
            aria-label="Personel veya ekip ara"
            placeholder="Personel veya ekip ara…"
            class="clay-search-input"
          />
          <button
            v-if="reportsState.searchQuery"
            type="button"
            class="search-clear-btn"
            @click="reportsState.searchQuery = ''"
          >
            <X :size="14" />
          </button>
        </label>

        <!-- Sıralama Seçici Dropdown -->
        <div class="sort-select-wrapper">
          <select
            v-model="reportsState.sortBy"
            class="clay-sort-select"
            aria-label="Sıralama ölçütü"
          >
            <option value="total-desc">En Çok {{ currentUnit }}</option>
            <option value="total-asc">En Az {{ currentUnit }}</option>
            <option value="name-asc">İsim (A-Z)</option>
            <option value="team-asc">Ekip No</option>
          </select>
        </div>
      </div>

      <!-- Akordiyon Hızlı Kontrol Şeridi -->
      <div class="accordion-control-bar">
        <span class="acc-info-text">
          <strong>{{ groupedRegions.length }}</strong> Bölgede <strong>{{ filteredCards.length }}</strong> Ekip
        </span>
        <button
          type="button"
          class="btn-toggle-all-acc"
          @click="toggleAllRegions"
        >
          {{ allRegionsExpanded ? 'Tümünü Kapat' : 'Tümünü Aç' }}
        </button>
      </div>
    </section>

    <!-- 5. BÖLGE AKORDİYONLARI VE PERSONEL KARTLARI -->
    <section class="cards-list-section">
      <!-- Loading State -->
      <div v-if="reportsState.loading" class="state-container state-container--loading">
        <div class="clay-spinner" />
        <strong class="state-title">Raporlar yükleniyor…</strong>
        <p class="state-desc">Saha ve puantaj kayıtları taranıyor</p>
      </div>

      <!-- Error State -->
      <div v-else-if="reportsState.error" class="state-container state-container--error">
        <ShieldAlert :size="36" class="text-danger mb-2" />
        <strong class="state-title">Veri Alınamadı</strong>
        <p class="state-desc">{{ reportsState.error }}</p>
        <button
          type="button"
          class="clay-btn clay-btn--primary mt-3"
          @click="loadReports()"
        >
          Tekrar Dene
        </button>
      </div>

      <!-- Empty State -->
      <div v-else-if="groupedRegions.length === 0" class="state-container state-container--empty">
        <div class="empty-icon-wrap">
          <Sparkles :size="28" />
        </div>
        <strong class="state-title">Kayıt Bulunamadı</strong>
        <p class="state-desc">
          Seçilen dönem ve filtre kriterlerine uygun işlem kaydı bulunmuyor.
        </p>
        <button
          v-if="reportsState.searchQuery"
          type="button"
          class="clay-btn clay-btn--secondary mt-3"
          @click="reportsState.searchQuery = ''"
        >
          Aramayı Temizle
        </button>
      </div>

      <!-- Akordiyon Bölge Listesi -->
      <div v-else class="accordion-regions-stack">
        <article
          v-for="reg in groupedRegions"
          :key="reg.name"
          class="bento-card bento-card--white region-accordion-card"
        >
          <!-- Akordiyon Başlığı -->
          <header
            class="region-accordion-head"
            role="button"
            tabindex="0"
            :aria-expanded="Boolean(expandedRegions[reg.name])"
            @click="toggleRegion(reg.name)"
          >
            <div class="reg-head-left">
              <div
                class="reg-pin-bubble"
                :style="{ backgroundColor: reg.bg || 'rgba(37, 99, 235, 0.1)', color: reg.color || '#2563eb' }"
              >
                <MapPin :size="16" />
              </div>
              <div>
                <div class="reg-name-row">
                  <h3 class="reg-title">{{ reg.name }}</h3>
                  <span class="reg-staff-pill">{{ reg.active_count || reg.cards.length }} / {{ reg.personnel_count || reg.cards.length }} Ekip</span>
                </div>
                <div class="reg-sub-stats">
                  <span>Pay: %{{ reg.percentage }}</span>
                </div>
              </div>
            </div>

            <div class="reg-head-right">
              <div class="reg-total-box">
                <strong class="reg-total-num">{{ formatNumber(reg.total) }}</strong>
                <small class="reg-total-unit">{{ currentUnit }}</small>
              </div>
              <button
                type="button"
                class="bento-mini-btn reg-chevron-btn"
                tabindex="-1"
                :aria-label="`${reg.name} bölgesini aç/kapat`"
              >
                <ChevronDown
                  :size="18"
                  class="transition-transform duration-200"
                  :class="{ 'rotate-180': expandedRegions[reg.name] }"
                />
              </button>
            </div>
          </header>

          <!-- Bölge Genel İlerleme Çubuğu -->
          <div class="region-track reg-main-track">
            <div
              class="region-fill"
              :style="{
                width: `${Math.max(reg.percentage, 3)}%`,
                backgroundColor: reg.color || '#2563eb',
              }"
            />
          </div>

          <!-- Akordiyon İçeriği: Bölgesel Dağılım Kartı Formatında Personel Kartları -->
          <div v-show="expandedRegions[reg.name]" class="region-accordion-body">
            <div class="region-cards-list">
              <div
                v-for="(c, cIdx) in reg.cards"
                :key="c.id + '_' + (c.composite_key || cIdx)"
                class="personnel-bar-card"
                role="button"
                tabindex="0"
                @click="openCardDetail(c)"
              >
                <!-- 1. Üst Satır (Meta) -->
                <div class="pr-bar-meta">
                  <div class="pr-bar-identity">
                    <!-- Sıralama Madalyası -->
                    <div
                      class="rank-badge"
                      :class="{
                        'rank-badge--gold': cIdx === 0 && c.total > 0,
                        'rank-badge--silver': cIdx === 1 && c.total > 0,
                        'rank-badge--bronze': cIdx === 2 && c.total > 0,
                      }"
                    >
                      <Trophy v-if="cIdx === 0 && c.total > 0" :size="11" />
                      <span v-else>{{ cIdx + 1 }}</span>
                    </div>

                    <!-- Avatar -->
                    <div class="pr-bar-avatar">
                      <img
                        v-if="c.photo"
                        :src="c.photo"
                        :alt="c.name"
                        class="pr-avatar-img"
                        @error="c.photo = ''"
                      />
                      <span v-else class="pr-avatar-initials">{{ getInitials(c.name) }}</span>
                    </div>

                    <!-- İsim & Ekip -->
                    <div class="pr-bar-text">
                      <h4 class="pr-bar-name" :title="c.name">{{ c.name }}</h4>
                      <span class="pr-bar-team">{{ c.team || 'Ekip Belirsiz' }}</span>
                    </div>
                  </div>

                  <!-- Sağ Taraf: Sayı ve Ok -->
                  <div class="pr-bar-right">
                    <div class="pr-bar-numbers">
                      <strong class="pr-bar-total">{{ formatNumber(c.total) }}</strong>
                      <small class="pr-bar-unit">{{ currentUnit }}</small>
                    </div>
                    <ChevronRight :size="16" class="pr-bar-arrow" />
                  </div>
                </div>

                <!-- 2. Bölgesel Dağılım Formatında İlerleme Çubuğu -->
                <div class="region-track pr-track">
                  <div
                    class="region-fill"
                    :style="{
                      width: `${reg.total > 0 ? Math.max(Math.round((c.total / reg.total) * 100), 2) : 2}%`,
                      backgroundColor: reg.color || '#2563eb',
                    }"
                  />
                </div>

                <!-- 3. Varsa İş Türleri Kırılım Etiketleri -->
                <div
                  v-if="c.breakdown && c.breakdown.length > 0 && reportsState.activeTab !== 'okuma' && reportsState.activeTab !== 'kacakkontrol'"
                  class="pr-bar-breakdown"
                >
                  <span
                    v-for="b in c.breakdown"
                    :key="b.name"
                    class="pr-bd-chip"
                    :title="b.name"
                  >
                    <small>{{ b.name }}:</small>
                    <strong>{{ formatNumber(b.count) }}</strong>
                  </span>
                </div>
              </div>
            </div>
          </div>
        </article>
      </div>
    </section>

    <!-- 6. Yüzen İş Türleri Menü Butonu (Alt Dock Çekmecesi Kapandığında Yeniden Açmak İçin) -->
    <button
      v-if="!reportsState.drawerOpen"
      type="button"
      class="floating-reports-dock-btn"
      aria-label="İş türleri menüsünü aç"
      @click="openReportsDrawer"
    >
      <Layers :size="17" />
      <span>İş Türleri</span>
    </button>

    <!-- ================================================================= -->
    <!-- 7. DETAY DRAWER / BOTTOM SHEET (Personel Günlük İcmal)            -->
    <!-- ================================================================= -->
    <Transition name="nav-sheet">
      <div
        v-if="reportsState.detailDrawerOpen"
        class="nav-sheet-layer"
        @click.self="closeCardDetail"
      >
        <section
          class="nav-sheet detail-sheet"
          role="dialog"
          aria-modal="true"
          :aria-label="reportsState.activeCard?.name || 'Personel Detayı'"
        >
          <!-- Drawer Başlığı -->
          <header class="detail-sheet__header">
            <div class="detail-sheet__person-box">
              <div class="detail-avatar">
                <img
                  v-if="reportsState.activeCard?.photo"
                  :src="reportsState.activeCard.photo"
                  :alt="reportsState.activeCard.name"
                  class="pr-avatar-img"
                  @error="reportsState.activeCard.photo = ''"
                />
                <span v-else class="pr-avatar-initials">
                  {{ getInitials(reportsState.activeCard?.name) }}
                </span>
              </div>
              <div class="detail-person-text">
                <span class="detail-eyebrow">PERSONEL İŞ DÖKÜMÜ</span>
                <h2 class="detail-person-name">{{ reportsState.activeCard?.name }}</h2>
                <div class="detail-meta-tags">
                  <span class="detail-pill">{{ reportsState.activeCard?.team }}</span>
                  <span class="detail-pill detail-pill--region">
                    <MapPin :size="10" />
                    {{ reportsState.activeCard?.region }}
                  </span>
                </div>
              </div>
            </div>

            <button
              type="button"
              class="detail-close-btn"
              aria-label="Kapat"
              @click="closeCardDetail"
            >
              <X :size="20" />
            </button>
          </header>

          <!-- Drawer Gövdesi -->
          <div class="detail-sheet__body hide-scrollbar">
            <!-- Loading -->
            <div v-if="reportsState.detailLoading" class="detail-loading-box">
              <div class="clay-spinner" />
              <span>Günlük veriler dökülüyor…</span>
            </div>

            <!-- Data Content -->
            <div v-else-if="reportsState.detailData" class="detail-content-wrap">
              <!-- KPI Özet Bandı -->
              <div class="detail-kpi-banner bento-card bento-card--white">
                <div class="detail-kpi-item">
                  <small>Dönem Toplamı</small>
                  <strong>
                    {{ formatNumber(reportsState.detailData.total_operations) }}
                    <span>{{ currentUnit }}</span>
                  </strong>
                </div>
                <div class="detail-kpi-item">
                  <small>Çalışılan Gün</small>
                  <strong>
                    {{ reportsState.detailData.total_days || 0 }}
                    <span>Gün</span>
                  </strong>
                </div>
                <div class="detail-kpi-item">
                  <small>Günlük Ortalama</small>
                  <strong>
                    {{
                      reportsState.detailData.total_days > 0
                        ? formatNumber(Math.round(reportsState.detailData.total_operations / reportsState.detailData.total_days))
                        : 0
                    }}
                    <span>/Gün</span>
                  </strong>
                </div>
              </div>

              <!-- İş Türleri Özeti (Birden fazla iş türü varsa) -->
              <div
                v-if="reportsState.detailData.work_types_summary && reportsState.detailData.work_types_summary.length > 0"
                class="detail-worktypes-summary bento-card bento-card--white"
              >
                <div class="summary-head">
                  <Layers :size="14" class="text-primary" />
                  <span>Dönem İçi İş Türü Dağılımı</span>
                </div>
                <div class="summary-chips-wrap">
                  <span
                    v-for="wt in reportsState.detailData.work_types_summary"
                    :key="wt.label"
                    class="summary-wt-chip"
                  >
                    <span class="wt-label">{{ wt.label }}</span>
                    <strong class="wt-count">{{ formatNumber(wt.count) }}</strong>
                  </span>
                </div>
              </div>

              <!-- Gün Bazlı Döküm Başlığı -->
              <div class="timeline-title-row">
                <Clock :size="15" class="text-muted" />
                <span class="timeline-title">Günlük İşlem Kayıtları</span>
                <span class="timeline-count-badge">{{ reportsState.detailData.days?.length || 0 }} Gün</span>
              </div>

              <!-- Günlük Liste -->
              <div v-if="reportsState.detailData.days?.length === 0" class="detail-empty">
                <Sparkles :size="24" class="text-muted mb-1" />
                <p>Bu tarih aralığında kaydedilmiş işlem bulunamadı.</p>
              </div>

              <div v-else class="daily-timeline-list">
                <article
                  v-for="day in reportsState.detailData.days"
                  :key="day.date"
                  class="daily-card bento-card bento-card--white"
                >
                  <div class="daily-card-head">
                    <div class="daily-date-block">
                      <strong class="daily-date-str">{{ day.date_formatted }}</strong>
                      <span class="daily-day-name">{{ day.day_name }}</span>
                    </div>
                    <div class="daily-total-badge">
                      <strong>{{ formatNumber(day.total) }}</strong>
                      <small>{{ currentUnit }}</small>
                    </div>
                  </div>

                  <!-- Gün içindeki kalemler -->
                  <div class="daily-items-grid">
                    <div
                      v-for="item in day.items"
                      :key="item.label"
                      class="daily-item-cell"
                    >
                      <span class="daily-item-label">{{ item.label }}</span>
                      <strong class="daily-item-val">{{ formatNumber(item.count) }}</strong>
                    </div>
                  </div>
                </article>
              </div>
            </div>
          </div>
        </section>
      </div>
    </Transition>

    <!-- ================================================================= -->
    <!-- 8. GELİŞMİŞ DÖNEM VE AY SEÇİCİ SHEET                              -->
    <!-- ================================================================= -->
    <Transition name="nav-sheet">
      <div
        v-if="reportsState.datePickerOpen"
        class="nav-sheet-layer"
        @click.self="closeDatePicker"
      >
        <section
          class="nav-sheet date-picker-sheet"
          role="dialog"
          aria-modal="true"
          aria-label="Tarih Aralığı Seç"
        >
          <header class="detail-sheet__header">
            <div>
              <span class="detail-eyebrow">DÖNEM FİLTRESİ</span>
              <h2 class="detail-person-name">Rapor Dönemini Seçin</h2>
            </div>
            <button
              type="button"
              class="detail-close-btn"
              aria-label="Kapat"
              @click="closeDatePicker"
            >
              <X :size="20" />
            </button>
          </header>

          <div class="date-picker-sheet__body hide-scrollbar">
            <!-- Hızlı Aralık Butonları (Modal İçi) -->
            <div class="modal-presets-grid mb-3">
              <button
                type="button"
                class="modal-preset-btn"
                @click="selectPresetInModal('buay')"
              >
                Bu Ay
              </button>
              <button
                type="button"
                class="modal-preset-btn"
                @click="selectPresetInModal('gecenay')"
              >
                Geçen Ay
              </button>
              <button
                type="button"
                class="modal-preset-btn"
                @click="selectPresetInModal('hafta')"
              >
                Bu Hafta
              </button>
              <button
                type="button"
                class="modal-preset-btn"
                @click="selectPresetInModal('bugun')"
              >
                Bugün
              </button>
            </div>

            <!-- Yıl ve Ay Seçim Matrisi -->
            <div class="month-selector-block bento-card bento-card--white">
              <div class="month-selector-head">
                <span class="block-title">Aylık Rapor Seçimi</span>
                <div class="year-stepper">
                  <button
                    type="button"
                    class="stepper-btn"
                    @click="selectedYear--"
                  >
                    -
                  </button>
                  <strong class="selected-year-text">{{ selectedYear }}</strong>
                  <button
                    type="button"
                    class="stepper-btn"
                    :disabled="selectedYear >= new Date().getFullYear()"
                    @click="selectedYear++"
                  >
                    +
                  </button>
                </div>
              </div>

              <div class="months-grid">
                <button
                  v-for="m in monthsList"
                  :key="m.val"
                  type="button"
                  class="month-pill-btn"
                  @click="selectMonth(m.val)"
                >
                  {{ m.label }}
                </button>
              </div>
            </div>

            <!-- Özel Tarih Aralığı Formu -->
            <div class="custom-range-block bento-card bento-card--white mt-3">
              <span class="block-title mb-2 block">Özel Tarih Aralığı</span>
              <div class="range-inputs-grid">
                <div class="range-input-group">
                  <label for="startDateInput">Başlangıç</label>
                  <input
                    id="startDateInput"
                    v-model="customStartInput"
                    type="date"
                    class="custom-date-field"
                  />
                </div>
                <div class="range-input-group">
                  <label for="endDateInput">Bitiş</label>
                  <input
                    id="endDateInput"
                    v-model="customEndInput"
                    type="date"
                    class="custom-date-field"
                  />
                </div>
              </div>

              <button
                type="button"
                class="clay-btn clay-btn--primary w-full mt-3"
                @click="applyCustomDate"
              >
                Tarih Aralığını Uygula
              </button>
            </div>
          </div>
        </section>
      </div>
    </Transition>

    <!-- ================================================================= -->
    <!-- 9. İŞLEM DAĞILIMI VE DETAYLI DÖKÜM SHEET (Design System Standardı) -->
    <!-- ================================================================= -->
    <Transition name="nav-sheet">
      <div
        v-if="reportsState.distributionDrawerOpen"
        class="nav-sheet-layer"
        @click.self="closeDistributionModal"
      >
        <section
          class="nav-sheet dist-sheet"
          role="dialog"
          aria-modal="true"
          :aria-label="reportsState.distributionData?.modal_title || 'İşlem Dağılımı'"
        >
          <!-- Drawer Üst Başlığı (Mobil SPA Standart Header) -->
          <header class="dist-sheet__header">
            <div class="dist-sheet__head-left">
              <span class="dist-avatar-box">
                <PieChart v-if="reportsState.activeTab === 'okuma'" :size="22" class="text-blue" />
                <ZapOff v-else-if="reportsState.activeTab === 'kesme'" :size="22" class="text-coral" />
                <Wrench v-else-if="reportsState.activeTab === 'sokme_takma'" :size="22" class="text-purple" />
                <ShieldCheck v-else-if="reportsState.activeTab === 'muhurleme'" :size="22" class="text-emerald" />
                <ShieldAlert v-else :size="22" class="text-amber" />
              </span>
              <div class="dist-header-text">
                <span class="detail-eyebrow">
                  {{ reportsState.distributionData?.tab_title || 'OPERASYONEL ANALİZ' }}
                </span>
                <h2 class="detail-person-name">
                  {{ reportsState.distributionData?.modal_title || 'Bu Dönemin Dağılımı' }}
                </h2>
                <div class="detail-meta-tags">
                  <span class="detail-pill detail-pill--region">
                    <CalendarRange :size="11" />
                    {{ reportsState.distributionData?.date_range_label || reportsState.dateRangeLabel }}
                  </span>
                  <span class="detail-pill uppercase font-semibold">{{ currentUnit }}</span>
                </div>
              </div>
            </div>

            <button
              type="button"
              class="detail-close-btn"
              aria-label="Kapat"
              @click="closeDistributionModal"
            >
              <X :size="20" />
            </button>
          </header>

          <!-- Sheet İçerik Gövdesi -->
          <div class="dist-sheet__body hide-scrollbar">
            <!-- Loading State -->
            <div v-if="reportsState.distributionLoading" class="dist-loading-box">
              <div class="clay-spinner" />
              <span>Dönem dağılım verileri hesaplanıyor…</span>
            </div>

            <!-- Error State -->
            <div v-else-if="reportsState.distributionData?.error" class="dist-error-box">
              <ShieldAlert :size="32" class="text-danger mb-2" />
              <p>{{ reportsState.distributionData.error }}</p>
              <button
                type="button"
                class="clay-btn clay-btn--primary btn-sm mt-2"
                @click="loadDistribution()"
              >
                Tekrar Dene
              </button>
            </div>

            <!-- Asıl Veri İçeriği -->
            <div v-else-if="reportsState.distributionData" class="dist-content-stack">
              <!-- Kesme / Olumlu / Olumsuz Filtre Hapları (Merkezi Clay Tabs) -->
              <div
                v-if="reportsState.distributionData?.supported_filters"
                class="clay-tabs-wrap"
              >
                <button
                  v-for="flt in reportsState.distributionData.supported_filters"
                  :key="flt.id"
                  type="button"
                  class="clay-tab-btn"
                  :class="{ 'is-active': reportsState.distributionFilterType === flt.id }"
                  @click="setDistributionFilter(flt.id)"
                >
                  <CheckCircle v-if="flt.id === 'olumlu'" :size="14" />
                  <XCircle v-else-if="flt.id === 'olumsuz'" :size="14" />
                  <span>{{ flt.label }}</span>
                </button>
              </div>

              <!-- 4'lü Hero KPI Bento Kartları (Mevcut Raporlar Sayfasıyla Birebir Aynı Tasarım Dili) -->
              <div
                v-if="reportsState.distributionData?.hero_stats"
                class="kpi-grid dist-kpi-grid"
              >
                <article
                  v-for="(st, sIdx) in reportsState.distributionData.hero_stats"
                  :key="sIdx"
                  class="bento-card bento-card--white kpi-card dist-kpi-card"
                >
                  <div class="kpi-top">
                    <span class="kpi-icon-bubble" :class="getHeroBubbleClass(st.icon)">
                      <Users v-if="st.icon === 'Users'" :size="17" />
                      <CheckCircle v-else-if="st.icon === 'CheckCircle'" :size="17" />
                      <AlertCircle v-else-if="st.icon === 'AlertCircle'" :size="17" />
                      <XCircle v-else-if="st.icon === 'XCircle'" :size="17" />
                      <Receipt v-else-if="st.icon === 'Receipt'" :size="17" />
                      <Zap v-else-if="st.icon === 'Zap'" :size="17" />
                      <Layers v-else-if="st.icon === 'Layers'" :size="17" />
                      <MapPin v-else-if="st.icon === 'MapPin'" :size="17" />
                      <Wrench v-else-if="st.icon === 'Wrench'" :size="17" />
                      <ShieldCheck v-else-if="st.icon === 'ShieldCheck'" :size="17" />
                      <ShieldAlert v-else-if="st.icon === 'ShieldAlert'" :size="17" />
                      <Clock v-else-if="st.icon === 'Clock'" :size="17" />
                    </span>
                    <span class="clay-pill-tag text-xs font-semibold uppercase">{{ currentUnit }}</span>
                  </div>
                  <div class="kpi-body">
                    <small class="kpi-label">{{ st.label }}</small>
                    <strong class="kpi-value">{{ formatNumber(st.value) }}</strong>
                  </div>
                </article>
              </div>

              <!-- Görünüm Seçici Sekmeler (Durumlar / Bölge Katkısı / Tümü) -->
              <div class="clay-tabs-wrap">
                <button
                  type="button"
                  class="clay-tab-btn"
                  :class="{ 'is-active': reportsState.distributionActiveView === 'all' }"
                  @click="setDistributionView('all')"
                >
                  <span>Tümü</span>
                </button>
                <button
                  type="button"
                  class="clay-tab-btn"
                  :class="{ 'is-active': reportsState.distributionActiveView === 'status' }"
                  @click="setDistributionView('status')"
                >
                  <PieChart :size="14" />
                  <span>{{ reportsState.distributionData.status_section?.title || 'Durumlar' }}</span>
                  <span class="clay-tab-badge">{{ reportsState.distributionData.status_section?.items?.length || 0 }}</span>
                </button>
                <button
                  type="button"
                  class="clay-tab-btn"
                  :class="{ 'is-active': reportsState.distributionActiveView === 'region' }"
                  @click="setDistributionView('region')"
                >
                  <MapPin :size="14" />
                  <span>Bölge Katkısı</span>
                  <span class="clay-tab-badge">{{ reportsState.distributionData.region_section?.items?.length || 0 }}</span>
                </button>
              </div>

              <!-- BÖLÜM 1: SAYAÇ / İŞ SONUCU / TUTANAK DAĞILIMI -->
              <section
                v-if="reportsState.distributionActiveView === 'all' || reportsState.distributionActiveView === 'status'"
                class="dist-section bento-card bento-card--white"
              >
                <div class="dist-section-head">
                  <div class="dist-head-left">
                    <span class="kpi-icon-bubble bg-blue-soft text-blue">
                      <PieChart :size="18" />
                    </span>
                    <div>
                      <h3 class="dist-section-title">
                        {{ reportsState.distributionData.status_section?.title }}
                      </h3>
                      <p class="dist-section-sub">
                        {{ reportsState.distributionData.status_section?.subtitle }}
                      </p>
                    </div>
                  </div>
                  <span class="clay-pill-tag text-xs font-semibold">
                    {{ reportsState.distributionData.status_section?.count_label }}
                  </span>
                </div>

                <div
                  v-if="!reportsState.distributionData.status_section?.items || reportsState.distributionData.status_section.items.length === 0"
                  class="dist-empty-note"
                >
                  <Sparkles :size="20" class="text-muted mb-1" />
                  <p>Bu filtreye uygun dağılım verisi bulunamadı.</p>
                </div>

                <!-- Durum Kartları Listesi -->
                <div v-else class="dist-items-list">
                  <article
                    v-for="(item, idx) in reportsState.distributionData.status_section.items"
                    :key="item.durum + '_' + idx"
                    class="dist-item-card"
                    :class="{ 'dist-item-card--olumsuz': item.sonuc_tipi === 'olumsuz' }"
                  >
                    <div class="dist-item-top">
                      <div class="dist-item-name-group">
                        <span
                          class="dist-item-dot"
                          :style="{ backgroundColor: item.color, boxShadow: `0 0 0 3px ${item.color}22` }"
                        />
                        <strong class="dist-item-name" :title="item.durum">
                          {{ item.durum }}
                        </strong>
                      </div>

                      <div class="dist-item-badge-group">
                        <span
                          v-if="item.sonuc_tipi === 'olumlu'"
                          class="status-micro-badge status-micro-badge--success"
                        >
                          <CheckCircle :size="10" />
                          <span>Olumlu</span>
                        </span>
                        <span
                          v-else-if="item.sonuc_tipi === 'olumsuz'"
                          class="status-micro-badge status-micro-badge--danger"
                        >
                          <XCircle :size="10" />
                          <span>Olumsuz</span>
                        </span>

                        <span class="dist-item-pct" :style="{ color: item.color }">
                          %{{ item.yuzde }}
                        </span>
                      </div>
                    </div>

                    <div class="dist-item-mid">
                      <strong class="dist-item-val">{{ formatNumber(item.toplam) }}</strong>
                      <span class="dist-item-records">{{ formatNumber(item.kayit_sayisi) }} kayıt</span>
                    </div>

                    <div class="dist-item-bar-track">
                      <div
                        class="dist-item-bar-fill"
                        :style="{ width: `${Math.min(Math.max(item.yuzde, 2), 100)}%`, backgroundColor: item.color }"
                      />
                    </div>
                  </article>
                </div>
              </section>

              <!-- BÖLÜM 2: BÖLGE KATKISI -->
              <section
                v-if="reportsState.distributionActiveView === 'all' || reportsState.distributionActiveView === 'region'"
                class="dist-section bento-card bento-card--white"
              >
                <div class="dist-section-head">
                  <div class="dist-head-left">
                    <span class="kpi-icon-bubble bg-cyan-soft text-cyan">
                      <MapPin :size="18" />
                    </span>
                    <div>
                      <h3 class="dist-section-title">
                        {{ reportsState.distributionData.region_section?.title }}
                      </h3>
                      <p class="dist-section-sub">
                        {{ reportsState.distributionData.region_section?.subtitle }}
                      </p>
                    </div>
                  </div>
                  <span class="clay-pill-tag text-xs font-semibold">
                    {{ reportsState.distributionData.region_section?.count_label }}
                  </span>
                </div>

                <div
                  v-if="!reportsState.distributionData.region_section?.items || reportsState.distributionData.region_section.items.length === 0"
                  class="dist-empty-note"
                >
                  <Sparkles :size="20" class="text-muted mb-1" />
                  <p>Bölge katkı verisi bulunamadı.</p>
                </div>

                <div v-else class="dist-region-stack">
                  <div
                    v-for="(reg, rIdx) in reportsState.distributionData.region_section.items"
                    :key="reg.bolge + '_' + rIdx"
                    class="dist-region-row"
                  >
                    <div class="dist-region-top">
                      <div class="dist-region-rank-name">
                        <span class="dist-region-rank">{{ rIdx + 1 }}.</span>
                        <strong class="dist-region-name">{{ reg.bolge }}</strong>
                      </div>
                      <strong class="dist-region-val">{{ formatNumber(reg.toplam) }}</strong>
                    </div>

                    <div class="dist-region-track">
                      <div
                        class="dist-region-fill"
                        :style="{ width: `${Math.min(Math.max(reg.bar_width, 3), 100)}%` }"
                      />
                    </div>

                    <div class="dist-region-meta">
                      <span>{{ formatNumber(reg.kayit_sayisi) }} kayıt</span>
                      <span>Toplamın %{{ reg.pay }}'si</span>
                    </div>
                  </div>
                </div>
              </section>

              <!-- Bilgilendirme Notu & Kapat Butonu -->
              <div class="dist-footer-info">
                <div class="dist-info-text">
                  <Sparkles :size="13" class="text-primary me-1" />
                  <span>Veriler, seçilen rapor dönemindeki tüm operasyonel kayıtları kapsar.</span>
                </div>
                <button
                  type="button"
                  class="clay-btn clay-btn--secondary w-full mt-3"
                  @click="closeDistributionModal"
                >
                  Kapat
                </button>
              </div>
            </div>
          </div>
        </section>
      </div>
    </Transition>
  </section>
</template>

<style scoped>
.reports-page {
  padding-bottom: calc(105px + var(--ds-safe-bottom));
}

/* 2. Tarih Filtre Bölümü */
.date-filter-section {
  margin-bottom: 14px;
}

.date-range-badge {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 16px;
  border-radius: var(--ds-radius-card);
  margin-bottom: 0;
  transition: transform 0.12s ease;
}

.date-range-badge:active {
  transform: scale(0.985);
}

.date-range-badge__left {
  display: flex;
  align-items: center;
  gap: 12px;
}

.date-icon-box {
  display: grid;
  width: 38px;
  height: 38px;
  place-items: center;
  border-radius: 12px;
  background: var(--ds-surface-subtle);
}

.date-label-sub {
  display: block;
  font-size: 10.5px;
  font-weight: 700;
  color: var(--ds-muted);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.date-range-text {
  display: block;
  font-size: 14px;
  font-weight: 900;
  color: var(--ds-ink);
  letter-spacing: -0.01em;
}

.date-change-link {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  background: var(--ds-surface-subtle);
  border: 1px solid var(--ds-border-subtle);
  padding: 6px 12px;
  border-radius: var(--ds-radius-pill);
  color: var(--ds-blue-ink);
  font: 800 12px var(--ds-font-main);
  pointer-events: none;
}

/* 3. KPI Grid */
.kpi-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 10px;
  margin-bottom: 14px;
}

.kpi-card {
  padding: 14px;
  margin-bottom: 0;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}

.kpi-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 10px;
}

.kpi-icon-bubble {
  display: grid;
  width: 36px;
  height: 36px;
  place-items: center;
  border-radius: 12px;
}

.kpi-label {
  display: block;
  font-size: 11px;
  color: var(--ds-muted);
  margin-bottom: 2px;
}

.kpi-value {
  display: block;
  font-size: 22px;
  font-weight: 800;
  letter-spacing: -0.03em;
  color: var(--ds-ink);
  line-height: 1.1;
}

.kpi-value-sub {
  font-size: 13px;
  font-weight: 600;
  color: var(--ds-muted);
}

.kpi-sub {
  display: block;
  font-size: 11px;
  color: var(--ds-muted);
  margin-top: 4px;
}

/* 4. Arama, Sıralama ve Akordiyon Kontrol */
.search-filter-section {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-bottom: 12px;
}

.search-row {
  display: flex;
  gap: 8px;
  align-items: center;
}

.sort-select-wrapper {
  position: relative;
  flex-shrink: 0;
}

.clay-sort-select {
  height: 48px;
  padding: 0 12px;
  border-radius: var(--ds-radius-card);
  border: 1px solid var(--ds-border-subtle);
  background: var(--ds-surface);
  color: var(--ds-ink);
  font: 700 12px var(--ds-font-main);
  box-shadow: var(--ds-shadow-tile);
  cursor: pointer;
  outline: none;
}

.search-clear-btn {
  background: none;
  border: none;
  color: var(--ds-muted);
  cursor: pointer;
  padding: 4px;
}

.accordion-control-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 2px 4px;
}

.acc-info-text {
  font-size: 11.5px;
  color: var(--ds-muted);
}

.acc-info-text strong {
  color: var(--ds-ink);
}

.btn-toggle-all-acc {
  background: none;
  border: none;
  color: var(--ds-blue-ink, #2563eb);
  font: 800 11.5px var(--ds-font-main);
  cursor: pointer;
}

/* 5. Bölge Akordiyonları */
.accordion-regions-stack {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.region-accordion-card {
  padding: 14px 16px;
  margin-bottom: 0;
}

.region-accordion-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  cursor: pointer;
  user-select: none;
}

.reg-head-left {
  display: flex;
  align-items: center;
  gap: 10px;
  flex: 1;
  min-width: 0;
}

.reg-pin-bubble {
  display: grid;
  width: 38px;
  height: 38px;
  place-items: center;
  border-radius: 12px;
  flex-shrink: 0;
}

.reg-name-row {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
}

.reg-title {
  margin: 0;
  font-size: 14.5px;
  font-weight: 800;
  color: var(--ds-ink);
  letter-spacing: -0.01em;
}

.reg-staff-pill {
  font-size: 10.5px;
  font-weight: 700;
  padding: 1px 7px;
  border-radius: var(--ds-radius-pill);
  background: var(--ds-surface-subtle);
  border: 1px solid var(--ds-border-subtle);
  color: var(--ds-muted);
}

.reg-sub-stats {
  font-size: 11px;
  font-weight: 700;
  color: var(--ds-muted);
  margin-top: 1px;
}

.reg-head-right {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
}

.reg-total-box {
  text-align: right;
  display: flex;
  flex-direction: column;
  align-items: flex-end;
}

.reg-total-num {
  font-size: 17px;
  font-weight: 900;
  color: var(--ds-ink);
  line-height: 1;
}

.reg-total-unit {
  font-size: 10px;
  font-weight: 700;
  color: var(--ds-muted);
  margin-top: 2px;
}

.reg-chevron-btn {
  pointer-events: none;
}

.reg-main-track {
  margin-top: 10px;
}

.region-track {
  width: 100%;
  height: 6px;
  border-radius: 999px;
  background: var(--dsx-bg-000000-a005);
  overflow: hidden;
}

.region-fill {
  height: 100%;
  border-radius: 999px;
  transition: width 0.3s ease;
}

/* Akordiyon Gövdesi ve Personel Kartları (Bölgesel Dağılım Formatı) */
.region-accordion-body {
  margin-top: 12px;
  padding-top: 12px;
  border-top: 1px solid var(--ds-border-subtle);
}

.region-cards-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.personnel-bar-card {
  padding: 10px 12px;
  border-radius: var(--ds-radius-card);
  background: var(--ds-surface-subtle);
  border: 1px solid var(--ds-border-subtle);
  cursor: pointer;
  transition: all 0.12s ease;
}

.personnel-bar-card:active {
  transform: scale(0.985);
  background: var(--dsx-bg-f0f7ff);
}

.pr-bar-meta {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 6px;
}

.pr-bar-identity {
  display: flex;
  align-items: center;
  gap: 8px;
  flex: 1;
  min-width: 0;
}

.rank-badge {
  display: grid;
  place-items: center;
  width: 22px;
  height: 22px;
  border-radius: 7px;
  background: var(--ds-surface);
  color: var(--ds-muted);
  font: 800 10.5px var(--ds-font-main);
  flex-shrink: 0;
  border: 1px solid var(--ds-border-subtle);
}

.rank-badge--gold {
  background: linear-gradient(135deg, var(--dsx-bg-fef08a), #eab308);
  color: var(--dsx-fg-713f12);
  border: none;
}

.rank-badge--silver {
  background: linear-gradient(135deg, var(--dsx-bg-f1f5f9), #94a3b8);
  color: var(--dsx-fg-1e293b);
  border: none;
}

.rank-badge--bronze {
  background: linear-gradient(135deg, var(--dsx-bg-fed7aa), #f97316);
  color: var(--dsx-fg-7c2d12);
  border: none;
}

.pr-bar-avatar {
  display: grid;
  place-items: center;
  width: 32px;
  height: 32px;
  border-radius: 10px;
  background: var(--ds-surface);
  overflow: hidden;
  flex-shrink: 0;
  border: 1px solid var(--ds-border-subtle);
}

.pr-avatar-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.pr-avatar-initials {
  font: 800 11.5px var(--ds-font-main);
  color: var(--ds-blue-ink);
}

.pr-bar-text {
  flex: 1;
  min-width: 0;
}

.pr-bar-name {
  margin: 0;
  font-size: 13px;
  font-weight: 800;
  color: var(--ds-ink);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.pr-bar-team {
  display: inline-block;
  font-size: 10px;
  font-weight: 800;
  padding: 1px 6px;
  border-radius: var(--ds-radius-pill);
  background: var(--ds-surface);
  border: 1px solid var(--ds-border-subtle);
  color: var(--ds-muted);
  margin-top: 1px;
}

.pr-bar-right {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-shrink: 0;
}

.pr-bar-numbers {
  text-align: right;
  display: flex;
  align-items: baseline;
  gap: 3px;
}

.pr-bar-total {
  font-size: 14.5px;
  font-weight: 900;
  color: var(--ds-ink);
}

.pr-bar-unit {
  font-size: 10px;
  font-weight: 700;
  color: var(--ds-muted);
}

.pr-bar-arrow {
  color: var(--ds-muted-light, #cbd5e1);
}

.pr-track {
  height: 5px;
  margin-top: 4px;
}

.pr-bar-breakdown {
  display: flex;
  flex-wrap: wrap;
  gap: 5px;
  margin-top: 6px;
  padding-top: 6px;
  border-top: 1px solid var(--dsx-bd-000000-a004);
}

.pr-bd-chip {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  padding: 2px 6px;
  border-radius: var(--ds-radius-pill);
  background: var(--ds-surface);
  border: 1px solid var(--ds-border-subtle);
  font-size: 9.5px;
  color: var(--ds-muted);
}

.pr-bd-chip strong {
  font-weight: 800;
  color: var(--ds-ink);
}

/* Durum Kapsayıcıları (Loading / Error / Empty) */
.state-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  padding: 48px 24px;
  background: var(--ds-surface);
  border-radius: var(--ds-radius-card);
  box-shadow: var(--ds-shadow-tile);
}

.state-title {
  font-size: 16px;
  font-weight: 800;
  color: var(--ds-ink);
  margin: 10px 0 4px;
}

.state-desc {
  font-size: 12.5px;
  color: var(--ds-muted);
  margin: 0;
  max-width: 280px;
  line-height: 1.4;
}

.empty-icon-wrap {
  display: grid;
  width: 54px;
  height: 54px;
  place-items: center;
  border-radius: 18px;
  background: var(--ds-surface-subtle);
  color: var(--ds-blue-ink);
}

/* 7. Detay Drawer / Bottom Sheet */
.detail-sheet {
  max-height: calc(88svh - 40px - var(--ds-safe-bottom));
  display: flex;
  flex-direction: column;
}

.detail-sheet__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 14px;
  flex-shrink: 0;
}

.detail-sheet__person-box {
  display: flex;
  align-items: center;
  gap: 12px;
  flex: 1;
  min-width: 0;
}

.detail-avatar {
  width: 48px;
  height: 48px;
  border-radius: 16px;
  background: var(--ds-surface-subtle);
  display: grid;
  place-items: center;
  overflow: hidden;
  flex-shrink: 0;
  border: 1px solid var(--ds-border-subtle);
}

.detail-eyebrow {
  display: block;
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.1em;
  color: var(--ds-blue-ink);
  margin-bottom: 2px;
}

.detail-person-name {
  margin: 0 0 4px;
  font-size: 17px;
  font-weight: 800;
  letter-spacing: -0.02em;
  color: var(--ds-ink);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.detail-meta-tags {
  display: flex;
  align-items: center;
  gap: 6px;
}

.detail-pill {
  font-size: 11px;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: var(--ds-radius-pill);
  background: var(--ds-surface-subtle);
  border: 1px solid var(--ds-border-subtle);
  color: var(--ds-ink);
}

.detail-pill--region {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  color: var(--ds-muted);
}

.detail-close-btn {
  display: grid;
  width: 36px;
  height: 36px;
  place-items: center;
  border-radius: 50%;
  border: 1px solid var(--ds-border-subtle);
  background: var(--ds-surface-subtle);
  color: var(--ds-ink);
  cursor: pointer;
  flex-shrink: 0;
}

.detail-sheet__body {
  overflow-y: auto;
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.detail-loading-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding: 40px 16px;
  color: var(--ds-muted);
  font-size: 13px;
}

.detail-kpi-banner {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
  padding: 12px;
  margin-bottom: 0;
  background: var(--ds-surface-subtle);
}

.detail-kpi-item {
  display: flex;
  flex-direction: column;
  text-align: center;
}

.detail-kpi-item small {
  font-size: 10px;
  font-weight: 700;
  color: var(--ds-muted);
  margin-bottom: 2px;
}

.detail-kpi-item strong {
  font-size: 16px;
  font-weight: 900;
  color: var(--ds-ink);
}

.detail-kpi-item strong span {
  font-size: 11px;
  font-weight: 600;
  color: var(--ds-muted);
}

.detail-worktypes-summary {
  padding: 12px;
  margin-bottom: 0;
}

.summary-head {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 11.5px;
  font-weight: 800;
  color: var(--ds-ink);
  margin-bottom: 8px;
}

.summary-chips-wrap {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.summary-wt-chip {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 4px 10px;
  border-radius: var(--ds-radius-pill);
  background: var(--ds-surface-subtle);
  border: 1px solid var(--ds-border-subtle);
  font-size: 11px;
}

.summary-wt-chip .wt-label {
  color: var(--ds-muted);
}

.summary-wt-chip .wt-count {
  font-weight: 900;
  color: var(--ds-primary-ink, var(--dsx-fg-0284c7));
}

.timeline-title-row {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-top: 4px;
}

.timeline-title {
  font-size: 12.5px;
  font-weight: 800;
  color: var(--ds-ink);
}

.timeline-count-badge {
  font-size: 10px;
  font-weight: 700;
  padding: 1px 6px;
  border-radius: var(--ds-radius-pill);
  background: var(--ds-surface-subtle);
  color: var(--ds-muted);
  border: 1px solid var(--ds-border-subtle);
}

.detail-empty {
  text-align: center;
  padding: 30px 16px;
  color: var(--ds-muted);
  font-size: 12px;
}

.daily-timeline-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding-bottom: 12px;
}

.daily-card {
  padding: 12px 14px;
  margin-bottom: 0;
}

.daily-card-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 8px;
}

.daily-date-block {
  display: flex;
  align-items: center;
  gap: 6px;
}

.daily-date-str {
  font-size: 13.5px;
  font-weight: 800;
  color: var(--ds-ink);
}

.daily-day-name {
  font-size: 11px;
  font-weight: 700;
  padding: 1px 6px;
  border-radius: var(--ds-radius-pill);
  background: var(--ds-surface-subtle);
  color: var(--ds-muted);
  border: 1px solid var(--ds-border-subtle);
}

.daily-total-badge {
  display: flex;
  align-items: baseline;
  gap: 3px;
  background: var(--dsx-bg-f0f7ff);
  border: 1px solid rgba(59, 130, 246, 0.2);
  padding: 3px 9px;
  border-radius: var(--ds-radius-pill);
}

.daily-total-badge strong {
  font-size: 14px;
  font-weight: 900;
  color: #2563eb;
}

.daily-total-badge small {
  font-size: 10px;
  font-weight: 700;
  color: #2563eb;
}

.daily-items-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 6px;
}

.daily-item-cell {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 6px 10px;
  border-radius: var(--ds-radius-card);
  background: var(--ds-surface-subtle);
  border: 1px solid var(--ds-border-subtle);
}

.daily-item-label {
  font-size: 11px;
  font-weight: 600;
  color: var(--ds-muted);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  margin-right: 6px;
}

.daily-item-val {
  font-size: 13px;
  font-weight: 800;
  color: var(--ds-ink);
  flex-shrink: 0;
}

/* 8. Tarih Seçici Sheet */
.date-picker-sheet {
  max-height: calc(85svh - 40px - var(--ds-safe-bottom));
  display: flex;
  flex-direction: column;
}

.date-picker-sheet__body {
  overflow-y: auto;
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.modal-presets-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 6px;
}

.modal-preset-btn {
  padding: 8px 4px;
  border-radius: var(--ds-radius-pill);
  border: 1px solid var(--ds-border-subtle);
  background: var(--ds-surface-subtle);
  color: var(--ds-ink);
  font: 700 11.5px var(--ds-font-main);
  cursor: pointer;
  text-align: center;
  transition: all 0.12s ease;
}

.modal-preset-btn:active {
  background: var(--ds-primary, #0284c7);
  color: #ffffff;
}

.month-selector-block {
  padding: 14px;
  margin-bottom: 0;
}

.month-selector-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
}

.block-title {
  font-size: 13px;
  font-weight: 800;
  color: var(--ds-ink);
}

.year-stepper {
  display: flex;
  align-items: center;
  gap: 8px;
}

.stepper-btn {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  border: 1px solid var(--ds-border-subtle);
  background: var(--ds-surface-subtle);
  font: 800 16px var(--ds-font-main);
  cursor: pointer;
  color: var(--ds-ink);
}

.stepper-btn:active {
  background: var(--ds-border-subtle);
}

.selected-year-text {
  font-size: 15px;
  font-weight: 800;
  color: var(--ds-ink);
  min-width: 44px;
  text-align: center;
}

.months-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
}

.month-pill-btn {
  padding: 10px 6px;
  border-radius: var(--ds-radius-card);
  border: 1px solid var(--ds-border-subtle);
  background: var(--ds-surface-subtle);
  color: var(--ds-ink);
  font: 700 12.5px var(--ds-font-main);
  cursor: pointer;
  transition: all 0.12s ease;
}

.month-pill-btn:active {
  background: var(--ds-primary, #0284c7);
  color: #ffffff;
}

.custom-range-block {
  padding: 14px;
  margin-bottom: 0;
}

.range-inputs-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 10px;
}

.range-input-group {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.range-input-group label {
  font-size: 11px;
  font-weight: 700;
  color: var(--ds-muted);
}

.custom-date-field {
  height: 44px;
  padding: 0 10px;
  border-radius: var(--ds-radius-card);
  border: 1px solid var(--ds-border-subtle);
  background: var(--ds-surface-subtle);
  color: var(--ds-ink);
  font: 700 13px var(--ds-font-main);
  outline: none;
}

.custom-date-field:focus {
  border-color: var(--ds-primary-ink, #0284c7);
  background: var(--dsx-bg-ffffff);
}

/* Animasyonlar */
.spin-anim {
  animation: spinAround 0.8s linear infinite;
}

@keyframes spinAround {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}

/* ------------------------------------------------------------- */
/* 9. DETAYLI DAĞILIM VE DÖKÜM SHEET (MASAÜSTÜ PARİTESİ)          */
/* ------------------------------------------------------------- */

/* Tıklanabilir KPI Kartı */
.kpi-card--clickable {
  cursor: pointer;
  transition: transform 0.16s cubic-bezier(0.2, 0.8, 0.2, 1), box-shadow 0.16s ease, border-color 0.16s ease;
  position: relative;
  overflow: hidden;
}

.kpi-card--clickable:active {
  transform: scale(0.975);
}

.kpi-tag-group {
  display: flex;
  align-items: center;
  gap: 5px;
}

.kpi-spark-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: rgba(37, 99, 235, 0.1);
  color: #2563eb;
  border: 1px solid rgba(37, 99, 235, 0.2);
}

.kpi-sub-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 4px;
  margin-top: 2px;
}

.kpi-action-pill {
  display: inline-flex;
  align-items: center;
  gap: 2px;
  font-size: 11px;
  font-weight: 700;
  color: #2563eb;
  background: rgba(37, 99, 235, 0.08);
  padding: 1px 6px;
  border-radius: 999px;
  flex-shrink: 0;
}

/* Color Helpers linking directly to --ds-* tokens */
.bg-blue-soft { background: var(--ds-blue-soft, var(--dsx-bg-f0f9ff)); }
.text-blue { color: var(--ds-blue-ink, var(--dsx-fg-0284c7)); }
.bg-emerald-soft { background: var(--ds-emerald-soft, var(--dsx-bg-ecfdf5)); }
.text-emerald { color: var(--ds-emerald-ink, var(--dsx-fg-059669)); }
.bg-amber-soft { background: var(--ds-amber-soft, var(--dsx-bg-fffbeb)); }
.text-amber { color: var(--ds-amber-ink, var(--dsx-fg-d97706)); }
.bg-purple-soft { background: var(--ds-purple-soft, var(--dsx-bg-f5f3ff)); }
.text-purple { color: var(--ds-purple-ink, #7c3aed); }
.bg-coral-soft { background: var(--ds-coral-soft, var(--dsx-bg-fff1f2)); }
.text-coral { color: var(--ds-coral-ink, var(--dsx-fg-e11d48)); }
.bg-cyan-soft { background: var(--dsx-bg-ecfeff); }
.text-cyan { color: var(--dsx-fg-0891b2); }

/* 9. DETAYLI DAĞILIM DÖKÜM SHEET (MOBİL SPA DESİGN SYSTEM) */
.dist-sheet {
  max-height: calc(88svh - 40px - var(--ds-safe-bottom));
  display: flex;
  flex-direction: column;
}

.dist-sheet__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 14px;
  flex-shrink: 0;
}

.dist-sheet__head-left {
  display: flex;
  align-items: center;
  gap: 12px;
  flex: 1;
  min-width: 0;
}

.dist-avatar-box {
  width: 48px;
  height: 48px;
  border-radius: 16px;
  background: var(--ds-surface-subtle);
  border: 1px solid var(--ds-border-subtle);
  display: grid;
  place-items: center;
  flex-shrink: 0;
}

.dist-header-text {
  flex: 1;
  min-width: 0;
}

.dist-sheet__body {
  overflow-y: auto;
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding-right: 2px;
}

.dist-loading-box,
.dist-error-box {
  padding: 36px 16px;
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  color: var(--ds-muted);
  font: 700 13px var(--ds-font-main);
}

.dist-content-stack {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

/* 4'lü Hero KPI Kartları (Bento Grid) */
.dist-kpi-grid {
  margin-bottom: 0;
}

.dist-kpi-card {
  padding: 12px 14px;
  margin-bottom: 0;
}

/* Bölüm Bento Kartı */
.dist-section {
  padding: 16px;
  border-radius: var(--ds-radius-card);
  margin-bottom: 0;
}

.dist-section-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 12px;
}

.dist-head-left {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
}

.dist-section-title {
  margin: 0;
  font-size: 14px;
  font-weight: 800;
  color: var(--ds-ink);
  letter-spacing: -0.01em;
}

.dist-section-sub {
  margin: 1px 0 0;
  font-size: 11px;
  color: var(--ds-muted);
}

.dist-empty-note {
  text-align: center;
  padding: 24px 10px;
  color: var(--ds-muted);
  font-size: 12px;
}

/* Durum Kartları Izgarası (Tek sütun mobile-first, ferah) */
.dist-items-list {
  display: grid;
  grid-template-columns: 1fr;
  gap: 8px;
}

@media (min-width: 500px) {
  .dist-items-list {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

.dist-item-card {
  padding: 12px 14px;
  border-radius: 16px;
  background: var(--ds-surface-subtle);
  border: 1px solid var(--ds-border-subtle);
  display: flex;
  flex-direction: column;
  gap: 6px;
  transition: transform 0.14s ease, border-color 0.14s ease;
}

.dist-item-card:active {
  transform: scale(0.985);
}

.dist-item-card--olumsuz {
  background: rgba(244, 63, 94, 0.04);
  border-color: rgba(244, 63, 94, 0.2);
}

.dist-item-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.dist-item-name-group {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
  flex: 1;
}

.dist-item-dot {
  width: 9px;
  height: 9px;
  border-radius: 50%;
  flex-shrink: 0;
}

.dist-item-name {
  font-size: 12.5px;
  font-weight: 700;
  color: var(--ds-ink);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.dist-item-badge-group {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-shrink: 0;
}

.status-micro-badge {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  font-size: 10px;
  font-weight: 700;
  padding: 1px 6px;
  border-radius: var(--ds-radius-pill);
}

.status-micro-badge--success {
  background: rgba(16, 185, 129, 0.12);
  color: var(--dsx-fg-059669);
  border: 1px solid rgba(16, 185, 129, 0.25);
}

.status-micro-badge--danger {
  background: rgba(244, 63, 94, 0.12);
  color: var(--dsx-fg-e11d48);
  border: 1px solid rgba(244, 63, 94, 0.25);
}

.dist-item-pct {
  font-size: 12.5px;
  font-weight: 800;
  letter-spacing: -0.01em;
}

.dist-item-mid {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 6px;
  margin-top: 2px;
}

.dist-item-val {
  font-size: 18px;
  font-weight: 800;
  color: var(--ds-ink);
  letter-spacing: -0.02em;
  line-height: 1;
}

.dist-item-records {
  font-size: 11px;
  color: var(--ds-muted);
  font-weight: 600;
}

.dist-item-bar-track {
  height: 5px;
  background: var(--ds-border-subtle);
  border-radius: var(--ds-radius-pill);
  overflow: hidden;
  margin-top: 3px;
}

.dist-item-bar-fill {
  height: 100%;
  border-radius: var(--ds-radius-pill);
  transition: width 0.4s cubic-bezier(0.2, 0.8, 0.2, 1);
}

/* Bölge Katkısı Sıralama Yığını */
.dist-region-stack {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.dist-region-row {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.dist-region-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.dist-region-rank-name {
  display: flex;
  align-items: center;
  gap: 6px;
  min-width: 0;
}

.dist-region-rank {
  font-size: 11px;
  font-weight: 800;
  color: var(--ds-muted);
}

.dist-region-name {
  font-size: 12.5px;
  font-weight: 700;
  color: var(--ds-ink);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.dist-region-val {
  font-size: 14px;
  font-weight: 800;
  color: var(--ds-ink);
}

.dist-region-track {
  height: 7px;
  background: var(--ds-border-subtle);
  border-radius: var(--ds-radius-pill);
  overflow: hidden;
}

.dist-region-fill {
  height: 100%;
  border-radius: var(--ds-radius-pill);
  background: linear-gradient(90deg, #2563eb, #38bdf8);
  transition: width 0.4s cubic-bezier(0.2, 0.8, 0.2, 1);
}

.dist-region-meta {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 10.5px;
  color: var(--ds-muted);
  font-weight: 600;
}

/* Bilgilendirme Notu & Kapat Butonu */
.dist-footer-info {
  margin-top: 4px;
}

.dist-info-text {
  display: flex;
  align-items: center;
  font-size: 11px;
  color: var(--ds-muted);
  padding: 0 4px;
}
</style>
