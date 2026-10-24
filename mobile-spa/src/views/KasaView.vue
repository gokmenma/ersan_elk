<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import {
  ArrowDownLeft,
  ArrowLeft,
  ArrowUpRight,
  BarChart3,
  Calendar,
  Check,
  ChevronRight,
  Clock,
  Eye,
  FileText,
  History,
  Landmark,
  Pencil,
  Plus,
  Receipt,
  RefreshCw,
  Search,
  Tag,
  Trash2,
  TrendingDown,
  TrendingUp,
  User,
  X,
} from '@lucide/vue'
import { kasaApi } from '../services/kasa.js'
import { auth } from '../services/auth.js'

const router = useRouter()

// Liste & Veri Durumları
const items = ref([])
const summary = ref({
  toplam_gelir: 0,
  toplam_gider: 0,
  bakiye: 0,
  gelir_adet: 0,
  gider_adet: 0,
  toplam_islem: 0,
})
const pagination = ref({ page: 1, limit: 30, total: 0, has_more: false })
const loading = ref(true)
const loadingMore = ref(false)
const error = ref('')

// Filtreler
const currentYear = new Date().getFullYear()
const selectedYear = ref(String(currentYear))
const selectedMonth = ref('')
const activeFilter = ref('all') // 'all', 'gelir' (1), 'gider' (2)
const search = ref('')
let searchTimer = null

// Meta veriler (Kategoriler, Hesap adları)
const categories = ref([])
const accounts = ref([])

// Form Modal Durumu (Yeni & Düzenle)
const isFormModalOpen = ref(false)
const isEditing = ref(false)
const formLoading = ref(false)
const formError = ref('')
const formData = ref({
  id: '',
  type: 2, // 1: Gelir, 2: Gider
  tutar: '',
  islem_tarihi: '',
  kategori: '',
  hesap_adi: '',
  aciklama: '',
  odeme_sekli: 'Nakit',
  plaka: '',
})

// Detay / Makbuz Modalı Durumu
const isDetailModalOpen = ref(false)
const selectedDetail = ref(null)

// İşlem Geçmişi (Tüm Hareketler) Modalı Durumu
const isHistoryModalOpen = ref(false)
const historyItems = ref([])
const historyLoading = ref(false)
const historyFilter = ref({
  search: '',
  type: 'all',
  baslangic: '',
  bitis: '',
})

// Silme Onay Modalı
const isDeleteConfirmOpen = ref(false)
const itemToDelete = ref(null)
const deleteLoading = ref(false)

// Yıl seçenekleri (Tüm zamanlar + son 4 yıl)
const yearOptions = computed(() => {
  const years = [{ value: '', label: 'Tüm Zamanlar' }]
  for (let y = currentYear; y >= currentYear - 3; y--) {
    years.push({ value: String(y), label: `${y} Yılı` })
  }
  return years
})

// Ay seçenekleri
const monthOptions = [
  { value: '', label: 'Tüm Aylar' },
  { value: '1', label: 'Ocak' },
  { value: '2', label: 'Şubat' },
  { value: '3', label: 'Mart' },
  { value: '4', label: 'Nisan' },
  { value: '5', label: 'Mayıs' },
  { value: '6', label: 'Haziran' },
  { value: '7', label: 'Temmuz' },
  { value: '8', label: 'Ağustos' },
  { value: '9', label: 'Eylül' },
  { value: '10', label: 'Ekim' },
  { value: '11', label: 'Kasım' },
  { value: '12', label: 'Aralık' },
]

// Para Formatlayıcı
function formatMoney(amount) {
  if (amount === undefined || amount === null) return '0,00 ₺'
  const val = Number(amount) || 0
  return (
    new Intl.NumberFormat('tr-TR', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(Math.abs(val)) + ' ₺'
  )
}

// 2 Harfli Avatar Üretici
function getCategoryInitials(name) {
  if (!name) return 'KS'
  const clean = name.trim().replace(/[^a-zA-ZğüşıöçĞÜŞİÖÇ\s]/g, '')
  const parts = clean.split(/\s+/).filter(Boolean)
  if (parts.length >= 2) {
    return (parts[0][0] + parts[1][0]).toLocaleUpperCase('tr-TR')
  }
  return clean.slice(0, 2).toLocaleUpperCase('tr-TR') || 'KS'
}

// Toplam Kasa Hacmi ve Oranlar (Cari Takip Mantığı)
const totalVolume = computed(() => {
  const inc = Math.max(0, Number(summary.value.toplam_gelir) || 0)
  const exp = Math.max(0, Number(summary.value.toplam_gider) || 0)
  return inc + exp
})

const gelirRatio = computed(() => {
  if (!totalVolume.value) return 50
  const inc = Math.max(0, Number(summary.value.toplam_gelir) || 0)
  return Math.min(100, Math.max(0, Math.round((inc / totalVolume.value) * 100)))
})

const giderRatio = computed(() => {
  if (!totalVolume.value) return 50
  return Math.max(0, 100 - gelirRatio.value)
})

function getTodayDateTimeInput() {
  const now = new Date()
  const offset = now.getTimezoneOffset() * 60000
  return new Date(now.getTime() - offset).toISOString().slice(0, 16)
}

// Hızlı Tutar Ekleme
function addQuickAmount(delta) {
  const cur = Number(formData.value.tutar) || 0
  formData.value.tutar = String(cur + delta)
}

// Kasa Listesini Yükle
async function loadKasa(reset = true) {
  if (reset) {
    loading.value = true
    pagination.value.page = 1
  } else {
    loadingMore.value = true
  }
  error.value = ''

  try {
    const tipParam = activeFilter.value === 'gelir' ? '1' : activeFilter.value === 'gider' ? '2' : ''
    const res = await kasaApi.list({
      yil: selectedYear.value,
      ay: selectedMonth.value,
      tip: tipParam,
      search: search.value,
      page: reset ? 1 : pagination.value.page + 1,
      limit: 30,
    })

    if (reset) {
      items.value = res.items || []
    } else {
      items.value = [...items.value, ...(res.items || [])]
    }

    summary.value = res.summary || summary.value
    pagination.value = res.pagination || pagination.value
  } catch (err) {
    error.value = err.message || 'Kasa kayıtları yüklenemedi.'
  } finally {
    loading.value = false
    loadingMore.value = false
  }
}

// Meta verileri yükle
async function loadMeta() {
  try {
    const res = await kasaApi.meta()
    if (res.success) {
      categories.value = res.kategoriler || []
      accounts.value = res.hesap_adlari || []
    }
  } catch {
    // Sessiz hata
  }
}

watch(selectedYear, (year) => {
  if (year === '' && selectedMonth.value !== '') {
    selectedMonth.value = ''
  }
})

watch([selectedYear, selectedMonth, activeFilter], () => {
  loadKasa(true)
})

watch(search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    loadKasa(true)
  }, 300)
})

// Modal Aç/Kapat
function openCreateModal() {
  isEditing.value = false
  formError.value = ''
  formData.value = {
    id: '',
    type: 2, // varsayılan gider
    tutar: '',
    islem_tarihi: getTodayDateTimeInput(),
    kategori: '',
    hesap_adi: '',
    aciklama: '',
    odeme_sekli: 'Nakit',
    plaka: '',
  }
  isFormModalOpen.value = true
}

async function openEditModal(item, e) {
  if (e) e.stopPropagation()
  isEditing.value = true
  formError.value = ''
  try {
    const res = await kasaApi.detail(item.id)
    if (res.item) {
      const d = res.item
      formData.value = {
        id: d.id,
        type: Number(d.type) || 2,
        tutar: String(d.tutar || ''),
        islem_tarihi: d.tarih_input || getTodayDateTimeInput(),
        kategori: d.kategori || '',
        hesap_adi: d.hesap_adi || '',
        aciklama: d.aciklama || '',
        odeme_sekli: d.odeme_sekli || 'Nakit',
        plaka: d.plaka || '',
      }
      isDetailModalOpen.value = false
      isFormModalOpen.value = true
    }
  } catch (err) {
    alert(err.message || 'Kayıt detayları getirilemedi.')
  }
}

function openDetailModal(item) {
  selectedDetail.value = item
  isDetailModalOpen.value = true
}

function confirmDelete(item, e) {
  if (e) e.stopPropagation()
  itemToDelete.value = item
  isDeleteConfirmOpen.value = true
}

async function executeDelete() {
  if (!itemToDelete.value) return
  deleteLoading.value = true
  try {
    await kasaApi.delete(itemToDelete.value.id, auth.state.csrfToken)
    isDeleteConfirmOpen.value = false
    if (isDetailModalOpen.value && selectedDetail.value?.id === itemToDelete.value.id) {
      isDetailModalOpen.value = false
    }
    itemToDelete.value = null
    await loadKasa(true)
  } catch (err) {
    alert(err.message || 'Silme işlemi gerçekleştirilemedi.')
  } finally {
    deleteLoading.value = false
  }
}

// Form Kaydet
async function handleSave() {
  formError.value = ''
  if (!formData.value.tutar || Number(formData.value.tutar) <= 0) {
    formError.value = 'Lütfen geçerli bir işlem tutarı girin.'
    return
  }
  if (!formData.value.kategori && !formData.value.hesap_adi) {
    formError.value = 'Lütfen kategori veya hesap adı giriniz.'
    return
  }

  formLoading.value = true
  try {
    await kasaApi.save(
      {
        id: formData.value.id,
        type: formData.value.type,
        tutar: formData.value.tutar,
        islem_tarihi: formData.value.islem_tarihi,
        kategori: formData.value.kategori,
        hesap_adi: formData.value.hesap_adi,
        aciklama: formData.value.aciklama,
        odeme_sekli: formData.value.odeme_sekli,
        plaka: formData.value.plaka,
      },
      auth.state.csrfToken
    )
    isFormModalOpen.value = false
    await loadKasa(true)
    loadMeta()
  } catch (err) {
    formError.value = err.message || 'İşlem kaydedilemedi.'
  } finally {
    formLoading.value = false
  }
}

// İşlem Geçmişi (Tüm Hareketler)
async function openHistoryModal() {
  isHistoryModalOpen.value = true
  await loadHistory()
}

async function loadHistory() {
  historyLoading.value = true
  try {
    const res = await kasaApi.tumHareketler(historyFilter.value)
    historyItems.value = res.items || []
  } catch {
    historyItems.value = []
  } finally {
    historyLoading.value = false
  }
}

function setHistoryType(type) {
  historyFilter.value.type = type
  loadHistory()
}

onMounted(() => {
  loadKasa(true)
  loadMeta()
})
</script>

<template>
  <section class="ds-screen ds-kasa-screen">
    <!-- 1. Üst Başlık & Aksiyon Çubuğu (Cari Takip Tasarımı) -->
    <header class="kasa-topbar">
      <button type="button" class="topbar-btn" aria-label="Geri dön" @click="router.back()">
        <ArrowLeft :size="22" />
      </button>

      <div class="topbar-title">
        <span>FİNANS & HESAPLAR</span>
        <h1>Kasa Takibi</h1>
      </div>

      <div class="topbar-actions">
        <button
          type="button"
          class="topbar-btn"
          aria-label="Yenile"
          :class="{ spinning: loading }"
          @click="loadKasa(true)"
        >
          <RefreshCw :size="19" />
        </button>
        <button
          type="button"
          class="topbar-btn topbar-btn--primary"
          aria-label="Yeni Kasa İşlemi Ekle"
          @click="openCreateModal"
        >
          <Plus :size="21" />
        </button>
      </div>
    </header>

    <!-- 2. Hero Obsidian Bento Kartı: Kasa İşlem Hacmi & Çift Yönlü Dağılım Analizi -->
    <article class="kasa-hero-volume-card">
      <header class="kasa-hero-volume-card__header">
        <div class="kasa-hero-volume-card__title">
          <span class="kasa-hero-icon-box">
            <BarChart3 :size="16" />
          </span>
          <div>
            <span class="kasa-hero-tag">KASA İŞLEM HACMİ</span>
            <span class="kasa-hero-subtitle">Tahsilat & Ödeme Dağılım Analizi</span>
          </div>
        </div>

        <span
          class="kasa-balance-pill"
          :class="summary.bakiye > 0 ? 'kasa-balance-pill--positive' : summary.bakiye < 0 ? 'kasa-balance-pill--warning' : 'kasa-balance-pill--neutral'"
        >
          <component :is="summary.bakiye >= 0 ? TrendingUp : TrendingDown" :size="13" />
          <span>{{ summary.bakiye > 0 ? 'Pozitif Denge' : summary.bakiye < 0 ? 'Negatif Denge' : 'Dengede' }}</span>
        </span>
      </header>

      <div class="kasa-hero-volume-main">
        <strong class="kasa-hero-volume-amount">{{ formatMoney(totalVolume) }}</strong>
        <div class="kasa-hero-balance-row">
          <span class="kasa-hero-balance-label">Net Kasa Bakiye:</span>
          <strong
            class="kasa-hero-balance-val"
            :class="summary.bakiye > 0 ? 'text-emerald' : summary.bakiye < 0 ? 'text-debt' : 'text-neutral'"
          >
            {{ (summary.bakiye > 0 ? '+' : summary.bakiye < 0 ? '-' : '') + formatMoney(summary.bakiye) }}
          </strong>
        </div>
      </div>

      <!-- Çift Yönlü Segmentli Dağılım Grafiği (Segmented Volume Bar) -->
      <div class="kasa-chart-container">
        <div
          class="kasa-segmented-track"
          role="progressbar"
          :aria-valuenow="gelirRatio"
          aria-valuemin="0"
          aria-valuemax="100"
        >
          <div
            class="kasa-seg-fill kasa-seg-fill--gelir"
            :style="{ width: `${gelirRatio}%` }"
            :title="`Top. Gelir: %${gelirRatio}`"
          >
            <span v-if="gelirRatio >= 16" class="kasa-seg-label">%{{ gelirRatio }}</span>
          </div>
          <div
            class="kasa-seg-fill kasa-seg-fill--gider"
            :style="{ width: `${giderRatio}%` }"
            :title="`Top. Gider: %${giderRatio}`"
          >
            <span v-if="giderRatio >= 16" class="kasa-seg-label">%{{ giderRatio }}</span>
          </div>
        </div>

        <!-- Grafik Lejantı (Legend Grid) -->
        <div class="kasa-chart-legend">
          <!-- Sol Kutu: Top. Gelir (Tahsilat) -->
          <div
            class="kasa-legend-item"
            :class="{ 'kasa-legend-item--active': activeFilter === 'gelir' }"
            @click="activeFilter = activeFilter === 'gelir' ? 'all' : 'gelir'"
          >
            <span class="kasa-legend-dot kasa-legend-dot--gelir"></span>
            <div class="kasa-legend-info">
              <div class="kasa-legend-header">
                <span class="kasa-legend-title">Top. Gelir (Tahsilat)</span>
                <b class="kasa-legend-pct text-emerald">%{{ gelirRatio }}</b>
              </div>
              <strong class="kasa-legend-val">{{ formatMoney(summary.toplam_gelir) }}</strong>
              <small class="kasa-legend-count">{{ summary.gelir_adet || 0 }} tahsilat / giriş</small>
            </div>
          </div>

          <!-- Sağ Kutu: Top. Gider (Ödeme) -->
          <div
            class="kasa-legend-item"
            :class="{ 'kasa-legend-item--active': activeFilter === 'gider' }"
            @click="activeFilter = activeFilter === 'gider' ? 'all' : 'gider'"
          >
            <span class="kasa-legend-dot kasa-legend-dot--gider"></span>
            <div class="kasa-legend-info">
              <div class="kasa-legend-header">
                <span class="kasa-legend-title">Top. Gider (Ödeme)</span>
                <b class="kasa-legend-pct text-sky">%{{ giderRatio }}</b>
              </div>
              <strong class="kasa-legend-val">{{ formatMoney(summary.toplam_gider) }}</strong>
              <small class="kasa-legend-count">{{ summary.gider_adet || 0 }} ödeme / çıkış</small>
            </div>
          </div>
        </div>
      </div>
    </article>

    <!-- 3. Dönem Seçicileri & Arama & Filtre Sekmeleri (Cari Takip Standartları) -->
    <section class="kasa-filter-section">
      <!-- Dönem Seçici Hapları (Yıl & Ay) -->
      <div class="kasa-period-pills-row">
        <div class="kasa-period-pill">
          <Calendar :size="14" class="period-icon" />
          <select v-model="selectedYear" class="period-select" aria-label="Yıl Seçin">
            <option v-for="y in yearOptions" :key="y.value || 'all'" :value="y.value">{{ y.label }}</option>
          </select>
        </div>

        <div class="kasa-period-pill">
          <Clock :size="14" class="period-icon" />
          <select v-model="selectedMonth" class="period-select" aria-label="Ay Seçin">
            <option v-for="m in monthOptions" :key="m.value" :value="m.value">{{ m.label }}</option>
          </select>
        </div>
      </div>

      <!-- Oval Arama Kutusu -->
      <div class="kasa-search-box">
        <Search :size="18" class="search-icon" />
        <input
          v-model="search"
          type="search"
          inputmode="search"
          placeholder="Kategori, hesap, açıklama veya plaka ara…"
          aria-label="Kasa hareketi ara"
        />
        <button v-if="search" type="button" class="search-clear-btn" aria-label="Temizle" @click="search = ''">
          <X :size="16" />
        </button>
      </div>

      <!-- Filtre Tabları (Hap Butonlar) -->
      <div class="kasa-filter-tabs">
        <button
          type="button"
          class="tab-btn"
          :class="{ active: activeFilter === 'all' }"
          @click="activeFilter = 'all'"
        >
          <span>Tümü</span>
          <b class="tab-badge">{{ summary.toplam_islem }}</b>
        </button>

        <button
          type="button"
          class="tab-btn"
          :class="{ active: activeFilter === 'gelir' }"
          @click="activeFilter = 'gelir'"
        >
          <span class="dot-indicator dot-credit"></span>
          <span>Gelirler</span>
          <b class="tab-badge">{{ summary.gelir_adet }}</b>
        </button>

        <button
          type="button"
          class="tab-btn"
          :class="{ active: activeFilter === 'gider' }"
          @click="activeFilter = 'gider'"
        >
          <span class="dot-indicator dot-debt"></span>
          <span>Giderler</span>
          <b class="tab-badge">{{ summary.gider_adet }}</b>
        </button>
      </div>
    </section>

    <!-- 4. Kasa Kartları Listesi (Cari Takip Kart Tasarımı) -->
    <main class="kasa-list-main">
      <!-- Yükleniyor Durumu -->
      <div v-if="loading" class="ds-loading">
        <i v-for="n in 4" :key="n" style="height: 82px;"></i>
      </div>

      <!-- Hata Durumu -->
      <div v-else-if="error" class="ds-state error-state">
        <RefreshCw :size="24" />
        <strong>Kayıtlar Yüklenemedi</strong>
        <p>{{ error }}</p>
        <button type="button" @click="loadKasa(true)">Tekrar Dene</button>
      </div>

      <!-- Boş Durum -->
      <div v-else-if="!items.length" class="ds-state">
        <Landmark :size="32" />
        <strong>İşlem Kaydı Bulunamadı</strong>
        <p v-if="search || activeFilter !== 'all'">Arama veya filtre kriterlerinizi değiştirin.</p>
        <p v-else>Henüz kayıtlı bir işlem bulunmuyor. Yeni bir kasa işlemi ekleyerek başlayın.</p>
        <button type="button" @click="openCreateModal">+ İşlem Ekle</button>
      </div>

      <!-- Kasa Kartları Listesi -->
      <div v-else class="kasa-cards-container">
        <article
          v-for="item in items"
          :key="item.id"
          class="kasa-card"
          role="button"
          tabindex="0"
          @click="openDetailModal(item)"
          @keydown.enter="openDetailModal(item)"
        >
          <!-- 46x46px Avatar (Cari Takip Stili) -->
          <div
            class="kasa-card__avatar"
            :class="item.type === 1 ? 'avatar--gelir' : 'avatar--gider'"
          >
            {{ getCategoryInitials(item.kategori) }}
          </div>

          <!-- Bilgi Bloğu -->
          <div class="kasa-card__info">
            <h3 class="kasa-card__name">{{ item.kategori }}</h3>
            <p v-if="item.hesap_adi" class="kasa-card__company">{{ item.hesap_adi }}</p>
            <div class="kasa-card__meta">
              <span class="kasa-meta-item">
                <Clock :size="12" />
                <span>{{ item.tarih_short }} {{ item.saat_short && item.saat_short !== '00:00' ? item.saat_short : '' }}</span>
              </span>
              <span v-if="item.aciklama" class="kasa-meta-item kasa-meta-item--desc">
                <FileText :size="12" />
                <span>{{ item.aciklama }}</span>
              </span>
              <span v-else-if="item.plaka" class="kasa-meta-item">
                <Tag :size="12" />
                <span>{{ item.plaka }}</span>
              </span>
            </div>
          </div>

          <!-- Bakiye ve Aksiyon Bloğu -->
          <div class="kasa-card__balance-block">
            <span
              class="balance-amount"
              :class="item.type === 1 ? 'text-credit' : 'text-debt'"
            >
              {{ item.type === 1 ? '+' : '-' }}{{ item.tutar_formatted }}
            </span>

            <span
              class="balance-badge"
              :class="item.type === 1 ? 'badge-credit' : 'badge-debt'"
            >
              {{ item.type === 1 ? 'GELİR' : 'GİDER' }}
            </span>

            <div class="kasa-card__actions">
              <button
                type="button"
                class="quick-action-btn"
                title="Detay / Makbuz"
                aria-label="Detay"
                @click.stop="openDetailModal(item)"
              >
                <Eye :size="14" />
              </button>
              <span class="chevron-icon">
                <ChevronRight :size="18" />
              </span>
            </div>
          </div>
        </article>

        <!-- Daha Fazla Yükle -->
        <div v-if="pagination.has_more" class="kasa-load-more">
          <button
            type="button"
            class="load-more-btn"
            :disabled="loadingMore"
            @click="loadKasa(false)"
          >
            <span v-if="loadingMore" class="loading-mini-spin"></span>
            <span>{{ loadingMore ? 'Yükleniyor…' : 'Daha Fazla Göster' }}</span>
          </button>
        </div>
      </div>
    </main>

    <!-- 5. Sağ Altta Kayan Çift Dairesel Butonlar (Cari Takipteki Gibi) -->
    <div class="kasa-floating-actions">
      <button
        type="button"
        class="floating-circle-btn floating-circle-btn--history"
        title="İşlem Geçmişi"
        aria-label="İşlem Geçmişi"
        @click="openHistoryModal"
      >
        <History :size="22" />
      </button>

      <button
        type="button"
        class="floating-circle-btn floating-circle-btn--add"
        title="Yeni İşlem Ekle"
        aria-label="Yeni İşlem Ekle"
        @click="openCreateModal"
      >
        <Plus :size="24" />
      </button>
    </div>

    <!-- 6. MODAL 1: HIZLI İŞLEM EKLEME / DÜZENLEME (Cari Takip Bottom Sheet) -->
    <Transition name="ds-modal">
      <div v-if="isFormModalOpen" class="ds-modal-layer" @click.self="isFormModalOpen = false">
        <div class="ds-bottom-sheet" role="dialog" aria-modal="true" :aria-label="isEditing ? 'İşlemi Düzenle' : 'Yeni Kasa İşlemi'">
          <div class="sheet-header">
            <div class="sheet-title">
              <div class="sheet-icon-box">
                <Landmark :size="18" />
              </div>
              <div>
                <h3>{{ isEditing ? 'İşlemi Düzenle' : 'Yeni Kasa İşlemi' }}</h3>
                <small>{{ isEditing ? 'Kayıt bilgilerini güncelleyin' : 'Gelir veya gider hareketi kaydedin' }}</small>
              </div>
            </div>
            <button type="button" class="sheet-close-btn" @click="isFormModalOpen = false">
              <X :size="18" />
            </button>
          </div>

          <form class="sheet-form" @submit.prevent="handleSave">
            <!-- 1. İşlem Yönü (Segmented Toggle) -->
            <div class="type-segmented-control">
              <button
                type="button"
                class="seg-btn seg-btn--credit"
                :class="{ 'is-active': formData.type === 1 }"
                @click="formData.type = 1"
              >
                <div class="seg-icon-box">
                  <ArrowDownLeft :size="18" />
                </div>
                <div class="seg-text">
                  <strong>Gelir (Giriş)</strong>
                  <small>Kasa / Banka Tahsilatı</small>
                </div>
              </button>

              <button
                type="button"
                class="seg-btn seg-btn--debt"
                :class="{ 'is-active': formData.type === 2 }"
                @click="formData.type = 2"
              >
                <div class="seg-icon-box">
                  <ArrowUpRight :size="18" />
                </div>
                <div class="seg-text">
                  <strong>Gider (Çıkış)</strong>
                  <small>Ödeme / Masraf / Çıkış</small>
                </div>
              </button>
            </div>

            <!-- 2. Tutar & Tarih -->
            <div class="form-grid-two">
              <div class="form-group">
                <label>Tutar (₺)*</label>
                <div class="input-wrap amount-wrap">
                  <span class="currency-badge">₺</span>
                  <input
                    v-model="formData.tutar"
                    type="number"
                    step="0.01"
                    min="0.01"
                    placeholder="0,00"
                    required
                    class="input-amount"
                    inputmode="decimal"
                  />
                </div>
              </div>

              <div class="form-group">
                <label>İşlem Tarihi*</label>
                <div class="input-wrap">
                  <Calendar :size="16" class="input-icon" />
                  <input v-model="formData.islem_tarihi" type="datetime-local" required />
                </div>
              </div>
            </div>

            <!-- Hızlı Tutar Çipleri -->
            <div class="quick-chips-row">
              <button type="button" class="quick-chip" @click="addQuickAmount(250)">+250 ₺</button>
              <button type="button" class="quick-chip" @click="addQuickAmount(500)">+500 ₺</button>
              <button type="button" class="quick-chip" @click="addQuickAmount(1000)">+1.000 ₺</button>
              <button type="button" class="quick-chip" @click="addQuickAmount(5000)">+5.000 ₺</button>
            </div>

            <!-- Kategori -->
            <div class="form-group">
              <label>Kategori / İşlem Türü*</label>
              <div class="input-wrap">
                <Tag :size="16" class="input-icon" />
                <input
                  v-model="formData.kategori"
                  list="kategori-datalist"
                  type="text"
                  placeholder="Örn: Akaryakıt, Maaş, Malzeme Satışı…"
                  required
                />
                <datalist id="kategori-datalist">
                  <option v-for="kat in categories" :key="kat" :value="kat" />
                </datalist>
              </div>
            </div>

            <!-- Hesap / Muhatap Kişi -->
            <div class="form-group">
              <label>Hesap / Muhatap Kişi*</label>
              <div class="input-wrap">
                <User :size="16" class="input-icon" />
                <input
                  v-model="formData.hesap_adi"
                  list="hesap-datalist"
                  type="text"
                  placeholder="Örn: Kasa, İsim Soyisim, Tedarikçi…"
                  required
                />
                <datalist id="hesap-datalist">
                  <option v-for="acc in accounts" :key="acc" :value="acc" />
                </datalist>
              </div>
            </div>

            <!-- Ödeme Şekli & Plaka -->
            <div class="form-grid-two">
              <div class="form-group">
                <label>Ödeme Şekli</label>
                <div class="input-wrap">
                  <select v-model="formData.odeme_sekli" class="select-plain">
                    <option value="Nakit">Nakit</option>
                    <option value="Banka / Havale">Banka / Havale</option>
                    <option value="Kredi Kartı">Kredi Kartı</option>
                    <option value="Çek / Senet">Çek / Senet</option>
                  </select>
                </div>
              </div>

              <div class="form-group">
                <label>İlgili Plaka (Opsiyonel)</label>
                <div class="input-wrap">
                  <input v-model="formData.plaka" type="text" placeholder="34 ABC 123" />
                </div>
              </div>
            </div>

            <!-- Açıklama -->
            <div class="form-group">
              <label>Açıklama</label>
              <div class="input-wrap textarea-wrap">
                <FileText :size="16" class="input-icon textarea-icon" />
                <textarea
                  v-model="formData.aciklama"
                  rows="2"
                  placeholder="İşlem detaylarını veya fiş/fatura numarasını buraya yazabilirsiniz…"
                ></textarea>
              </div>
            </div>

            <!-- Hata Mesajı -->
            <p v-if="formError" class="form-error">{{ formError }}</p>

            <!-- Gönder Butonu -->
            <button type="submit" class="submit-action-btn" :disabled="formLoading">
              <Check v-if="!formLoading" :size="18" />
              <span>{{ formLoading ? 'Kaydediliyor…' : (isEditing ? 'Güncellemeyi Kaydet' : 'İşlemi Kaydet') }}</span>
            </button>
          </form>
        </div>
      </div>
    </Transition>

    <!-- 7. MODAL 2: HAREKET DETAYI / MAKBUZ (Cari Takip Tarzı) -->
    <Transition name="ds-modal">
      <div v-if="isDetailModalOpen && selectedDetail" class="ds-modal-layer" @click.self="isDetailModalOpen = false">
        <div class="ds-bottom-sheet" role="dialog" aria-modal="true" aria-label="İşlem Detayı">
          <div class="sheet-header">
            <div class="sheet-title">
              <div class="sheet-icon-box">
                <Receipt :size="18" />
              </div>
              <div>
                <h3>İşlem Detay Fişi</h3>
                <small>{{ selectedDetail.tarih_formatted }}</small>
              </div>
            </div>
            <button type="button" class="sheet-close-btn" @click="isDetailModalOpen = false">
              <X :size="18" />
            </button>
          </div>

          <div class="movement-detail-body">
            <!-- Tutar Hero -->
            <div class="movement-amount-hero">
              <span class="hero-type-label">
                {{ selectedDetail.type === 1 ? 'GELİR HAREKETİ' : 'GİDER HAREKETİ' }}
              </span>
              <strong
                class="hero-amount-text"
                :class="selectedDetail.type === 1 ? 'text-credit' : 'text-debt'"
              >
                {{ selectedDetail.type === 1 ? '+' : '-' }}{{ selectedDetail.tutar_formatted }}
              </strong>
            </div>

            <!-- Parametre Listesi -->
            <div class="detail-data-list">
              <div class="detail-data-row">
                <span>Kategori:</span>
                <strong>{{ selectedDetail.kategori }}</strong>
              </div>
              <div class="detail-data-row">
                <span>Hesap / Muhatap:</span>
                <strong>{{ selectedDetail.hesap_adi }}</strong>
              </div>
              <div class="detail-data-row">
                <span>İşlem Tarihi:</span>
                <strong>{{ selectedDetail.tarih_formatted }}</strong>
              </div>
              <div class="detail-data-row">
                <span>Yürüyen Bakiye:</span>
                <strong :class="selectedDetail.bakiye < 0 ? 'text-debt' : 'text-credit'">
                  {{ selectedDetail.bakiye_formatted }} {{ selectedDetail.bakiye_yon ? `(${selectedDetail.bakiye_yon})` : '' }}
                </strong>
              </div>
              <div v-if="selectedDetail.odeme_sekli" class="detail-data-row">
                <span>Ödeme Şekli:</span>
                <strong>{{ selectedDetail.odeme_sekli }}</strong>
              </div>
              <div v-if="selectedDetail.plaka" class="detail-data-row">
                <span>İlgili Plaka:</span>
                <strong>{{ selectedDetail.plaka }}</strong>
              </div>
              <div class="detail-data-row">
                <span>Açıklama:</span>
                <p>{{ selectedDetail.aciklama || '—' }}</p>
              </div>
            </div>

            <!-- Aksiyon Butonları -->
            <div class="detail-action-buttons">
              <button
                type="button"
                class="detail-edit-btn"
                @click="openEditModal(selectedDetail, $event)"
              >
                <Pencil :size="16" />
                <span>Düzenle</span>
              </button>

              <button
                type="button"
                class="danger-delete-btn"
                @click="confirmDelete(selectedDetail, $event)"
              >
                <Trash2 :size="16" />
                <span>Bu Kaydı Sil</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </Transition>

    <!-- 8. MODAL 3: TÜM HAREKETLER (GEÇMİŞ) -->
    <Transition name="ds-modal">
      <div v-if="isHistoryModalOpen" class="ds-modal-layer" @click.self="isHistoryModalOpen = false">
        <div class="ds-bottom-sheet" role="dialog" aria-modal="true" aria-label="İşlem Geçmişi">
          <div class="sheet-header">
            <div class="sheet-title">
              <div class="sheet-icon-box">
                <History :size="18" />
              </div>
              <div>
                <h3>İşlem Geçmişi</h3>
                <small>Tüm kasa hareketleri dökümü</small>
              </div>
            </div>
            <button type="button" class="sheet-close-btn" @click="isHistoryModalOpen = false">
              <X :size="18" />
            </button>
          </div>

          <div class="sheet-form">
            <!-- Arama & Filtre -->
            <div class="history-controls-card">
              <div class="kasa-search-box" style="margin-bottom: 8px;">
                <Search :size="16" class="search-icon" />
                <input
                  v-model="historyFilter.search"
                  type="search"
                  placeholder="Geçmişte ara…"
                  @input="loadHistory"
                />
              </div>

              <div class="form-grid-two" style="margin-bottom: 8px;">
                <input
                  v-model="historyFilter.baslangic"
                  type="date"
                  class="date-input-plain"
                  aria-label="Başlangıç Tarihi"
                  @change="loadHistory"
                />
                <input
                  v-model="historyFilter.bitis"
                  type="date"
                  class="date-input-plain"
                  aria-label="Bitiş Tarihi"
                  @change="loadHistory"
                />
              </div>

              <div class="kasa-filter-tabs">
                <button
                  type="button"
                  class="tab-btn"
                  :class="{ active: historyFilter.type === 'all' }"
                  @click="setHistoryType('all')"
                >
                  Tümü
                </button>
                <button
                  type="button"
                  class="tab-btn"
                  :class="{ active: historyFilter.type === '1' }"
                  @click="setHistoryType('1')"
                >
                  <span class="dot-indicator dot-credit"></span>
                  Gelir
                </button>
                <button
                  type="button"
                  class="tab-btn"
                  :class="{ active: historyFilter.type === '2' }"
                  @click="setHistoryType('2')"
                >
                  <span class="dot-indicator dot-debt"></span>
                  Gider
                </button>
              </div>
            </div>

            <!-- Geçmiş Listesi -->
            <div v-if="historyLoading" class="ds-loading py-6">
              <i v-for="n in 3" :key="n" style="height: 60px;"></i>
            </div>

            <div v-else-if="!historyItems.length" class="ds-state py-6">
              <p>Kriterlere uygun geçmiş kaydı bulunamadı.</p>
            </div>

            <div v-else class="history-list-wrap">
              <article
                v-for="h in historyItems"
                :key="h.id"
                class="history-row-card"
                @click="openDetailModal(h)"
              >
                <div class="history-row-date">
                  <span class="day-num">{{ h.date_short }}</span>
                  <span class="time-str">{{ h.time_short }}</span>
                </div>

                <div class="history-row-content">
                  <h4>{{ h.kategori_adi }}</h4>
                  <small>{{ h.hesap_adi }}</small>
                </div>

                <div class="history-row-amount">
                  <strong :class="h.type === 1 ? 'text-credit' : 'text-debt'">
                    {{ h.type === 1 ? '+' : '-' }}{{ h.tutar_formatted }}
                  </strong>
                  <small>Bakiye: {{ h.yuruyen_formatted }} {{ h.yuruyen_yon ? `(${h.yuruyen_yon})` : '' }}</small>
                </div>
              </article>
            </div>
          </div>
        </div>
      </div>
    </Transition>

    <!-- 9. SİLME ONAY MODALI -->
    <Transition name="ds-modal">
      <div v-if="isDeleteConfirmOpen" class="ds-modal-layer" @click.self="isDeleteConfirmOpen = false">
        <div class="delete-dialog-box">
          <div class="delete-dialog-icon">
            <Trash2 :size="24" />
          </div>
          <h3>İşlem Kaydı Silinecek</h3>
          <p>
            "<strong>{{ itemToDelete?.kategori }}</strong>" kalemine ait
            {{ itemToDelete?.tutar_formatted }} tutarındaki işlem silinecek. Onaylıyor musunuz?
          </p>
          <div class="dialog-actions">
            <button
              type="button"
              class="btn-dialog-cancel"
              :disabled="deleteLoading"
              @click="isDeleteConfirmOpen = false"
            >
              Vazgeç
            </button>
            <button
              type="button"
              class="btn-dialog-confirm"
              :disabled="deleteLoading"
              @click="executeDelete"
            >
              <span v-if="deleteLoading" class="loading-mini-spin"></span>
              <span>Evet, Sil</span>
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </section>
</template>

<style scoped>
/* -------------------------------------------------------------------------
   CARI TAKİP TASARIM STANDARTLARI (GLACIER ALPINE OPTICS: KASA)
   ------------------------------------------------------------------------- */
.ds-kasa-screen {
  font-family: var(--ds-font-main);
  padding-bottom: calc(140px + var(--ds-safe-bottom));
}

/* TOPBAR */
.kasa-topbar {
  display: grid;
  grid-template-columns: 42px 1fr auto;
  align-items: center;
  gap: 12px;
  margin-bottom: 20px;
}

.topbar-btn {
  display: grid;
  width: 42px;
  height: 42px;
  place-items: center;
  border: 1px solid var(--dsx-bd-ffffff-a090);
  border-radius: 50%;
  color: var(--ds-ink);
  background: var(--dsx-bg-ffffff-a080);
  box-shadow: var(--ds-shadow-card);
  backdrop-filter: blur(16px);
  cursor: pointer;
  transition: transform 0.15s ease;
}

.topbar-btn:active {
  transform: scale(0.93);
}

.topbar-btn--primary {
  background: var(--ds-primary, var(--dsx-bg-11161b));
  color: var(--ds-ink-light, #ffffff);
  border-color: var(--ds-primary, #11161b);
}

.topbar-title span {
  display: block;
  color: var(--ds-muted);
  font: 700 9px 'Space Grotesk', sans-serif;
  letter-spacing: 0.1em;
}

.topbar-title h1 {
  margin: 1px 0 0;
  font-size: 22px;
  font-weight: 700;
  letter-spacing: -0.03em;
  color: var(--ds-ink);
}

.topbar-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.spinning {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

/* 2. HERO OBSIDIAN BENTO KARTI: KASA İŞLEM HACMİ */
.kasa-hero-volume-card {
  background: var(--ds-surface-dark, var(--dsx-bg-191d24));
  border-radius: var(--ds-radius-card, 28px);
  color: #ffffff;
  padding: 20px 18px;
  margin-bottom: 16px;
  border: 1px solid rgba(255, 255, 255, 0.1);
  box-shadow: var(--ds-shadow-bento-dark, 0 16px 36px rgba(0, 0, 0, 0.28));
}

.kasa-hero-volume-card__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 14px;
}

.kasa-hero-volume-card__title {
  display: flex;
  align-items: center;
  gap: 10px;
}

.kasa-hero-icon-box {
  display: grid;
  place-items: center;
  width: 34px;
  height: 34px;
  border-radius: 12px;
  background: rgba(255, 255, 255, 0.1);
  color: #38bdf8;
  border: 1px solid rgba(255, 255, 255, 0.12);
}

.kasa-hero-tag {
  display: block;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.08em;
  color: rgba(255, 255, 255, 0.65);
  text-transform: uppercase;
}

.kasa-hero-subtitle {
  display: block;
  font-size: 11px;
  font-weight: 500;
  color: rgba(255, 255, 255, 0.45);
}

.kasa-balance-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 700;
  white-space: nowrap;
}

.kasa-balance-pill--positive {
  background: rgba(16, 185, 129, 0.18);
  color: #34d399;
  border: 1px solid rgba(52, 211, 153, 0.3);
}

.kasa-balance-pill--warning {
  background: rgba(239, 68, 68, 0.18);
  color: #f87171;
  border: 1px solid rgba(248, 113, 113, 0.3);
}

.kasa-balance-pill--neutral {
  background: rgba(255, 255, 255, 0.1);
  color: rgba(255, 255, 255, 0.7);
  border: 1px solid rgba(255, 255, 255, 0.15);
}

.kasa-hero-volume-main {
  margin-bottom: 14px;
}

.kasa-hero-volume-amount {
  display: block;
  font-size: 26px;
  font-weight: 800;
  letter-spacing: -0.04em;
  color: #ffffff;
  line-height: 1.15;
}

.kasa-hero-balance-row {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-top: 4px;
  font-size: 11.5px;
}

.kasa-hero-balance-label {
  color: rgba(255, 255, 255, 0.55);
}

.kasa-hero-balance-val {
  font-weight: 700;
}

/* Çift Yönlü Segmentli Dağılım Grafiği (Segmented Track) */
.kasa-chart-container {
  padding-top: 6px;
  border-top: 1px solid rgba(255, 255, 255, 0.08);
}

.kasa-segmented-track {
  position: relative;
  display: flex;
  height: 16px;
  width: 100%;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.1);
  overflow: hidden;
  box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.3);
}

.kasa-seg-fill {
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: width 0.4s ease;
  position: relative;
}

.kasa-seg-fill--gelir {
  background: linear-gradient(90deg, #10b981, #059669);
}

.kasa-seg-fill--gider {
  background: linear-gradient(90deg, #38bdf8, #0284c7);
}

.kasa-seg-label {
  font-size: 9.5px;
  font-weight: 800;
  color: #ffffff;
  text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
  letter-spacing: -0.01em;
}

/* Lejant (Legend Grid) */
.kasa-chart-legend {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 10px;
  margin-top: 12px;
}

.kasa-legend-item {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  padding: 8px 10px;
  border-radius: 14px;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.06);
  cursor: pointer;
  transition: background 0.18s ease, border-color 0.18s ease;
}

.kasa-legend-item:active {
  background: rgba(255, 255, 255, 0.1);
}

.kasa-legend-item--active {
  background: rgba(255, 255, 255, 0.12);
  border-color: rgba(255, 255, 255, 0.25);
}

.kasa-legend-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  margin-top: 4px;
  flex-shrink: 0;
}

.kasa-legend-dot--gelir {
  background: #10b981;
  box-shadow: 0 0 8px rgba(16, 185, 129, 0.6);
}

.kasa-legend-dot--gider {
  background: #38bdf8;
  box-shadow: 0 0 8px rgba(56, 189, 248, 0.6);
}

.kasa-legend-info {
  min-width: 0;
  flex: 1;
}

.kasa-legend-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 4px;
}

.kasa-legend-title {
  font-size: 10.5px;
  font-weight: 600;
  color: rgba(255, 255, 255, 0.7);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.kasa-legend-pct {
  font-size: 11px;
  font-weight: 800;
}

.kasa-legend-val {
  display: block;
  font-size: 13px;
  font-weight: 800;
  color: #ffffff;
  margin-top: 2px;
  letter-spacing: -0.02em;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.kasa-legend-count {
  display: block;
  font-size: 9.5px;
  color: rgba(255, 255, 255, 0.45);
  margin-top: 1px;
}

/* RENKLER */
.text-debt { color: var(--ds-danger-ink, #dc2626) !important; }
.text-credit { color: var(--ds-emerald-ink, var(--dsx-fg-059669)) !important; }
.text-neutral { color: var(--ds-slate, var(--dsx-fg-516071)) !important; }
.text-emerald { color: #34d399 !important; }
.text-sky { color: #38bdf8 !important; }

/* 3. DÖNEM & ARAMA & FİLTRE */
.kasa-filter-section {
  margin-bottom: 16px;
}

.kasa-period-pills-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
  margin-bottom: 10px;
}

.kasa-period-pill {
  display: flex;
  align-items: center;
  height: 42px;
  padding: 0 12px;
  border: 1px solid var(--dsx-bd-ffffff-a090);
  border-radius: 999px;
  background: var(--dsx-bg-ffffff-a078);
  box-shadow: var(--ds-shadow-card);
  backdrop-filter: blur(16px);
}

.period-icon {
  color: var(--ds-muted);
  margin-right: 6px;
  flex-shrink: 0;
}

.period-select {
  width: 100%;
  border: none;
  background: transparent;
  font-size: 12px;
  font-weight: 700;
  color: var(--ds-ink);
  outline: none;
  cursor: pointer;
}

.kasa-search-box {
  position: relative;
  display: flex;
  align-items: center;
  height: 48px;
  padding: 0 14px;
  border: 1px solid var(--dsx-bd-ffffff-a090);
  border-radius: 999px;
  background: var(--dsx-bg-ffffff-a078);
  box-shadow: var(--ds-shadow-card);
  backdrop-filter: blur(16px);
  margin-bottom: 12px;
}

.kasa-search-box .search-icon {
  color: var(--ds-muted);
  margin-right: 10px;
  flex-shrink: 0;
}

.kasa-search-box input {
  width: 100%;
  height: 100%;
  border: none;
  background: transparent;
  outline: none;
  font-size: 13px;
  color: var(--ds-ink);
}

.kasa-search-box input::placeholder {
  color: var(--ds-muted);
}

.search-clear-btn {
  background: none;
  border: none;
  color: var(--ds-muted);
  padding: 4px;
  cursor: pointer;
}

/* FİLTRE HAPLARI */
.kasa-filter-tabs {
  display: flex;
  gap: 8px;
}

.tab-btn {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  height: 38px;
  padding: 0 12px;
  border: 1px solid var(--dsx-bd-ffffff-a085);
  border-radius: 999px;
  background: var(--dsx-bg-ffffff-a070);
  box-shadow: var(--ds-shadow-card);
  color: var(--ds-slate);
  font: 600 10.5px 'Space Grotesk', sans-serif;
  cursor: pointer;
  transition: all 0.15s ease;
}

.tab-btn.active {
  background: var(--ds-primary, var(--dsx-bg-11161b));
  color: var(--ds-ink-light, #ffffff);
  border-color: var(--ds-primary, #11161b);
}

.tab-badge {
  padding: 2px 6px;
  border-radius: 999px;
  font-size: 9px;
  background: rgba(140, 155, 174, 0.18);
}

.tab-btn.active .tab-badge {
  background: rgba(255, 255, 255, 0.22);
  color: var(--ds-ink-light, #ffffff);
}

.dot-indicator {
  width: 6px;
  height: 6px;
  border-radius: 50%;
}

.dot-credit { background: #10b981; }
.dot-debt { background: #ef4444; }

/* 4. KASA KARTLARI LİSTESİ (Cari Takip Kart Yapısı) */
.kasa-cards-container {
  display: grid;
  gap: 10px;
}

.kasa-card {
  display: grid;
  grid-template-columns: 46px 1fr auto;
  align-items: center;
  gap: 12px;
  padding: 13px 14px;
  border: 1px solid var(--dsx-bd-ffffff-a090);
  border-radius: 20px;
  background: var(--dsx-bg-ffffff-a078);
  box-shadow: var(--ds-shadow-card);
  backdrop-filter: blur(16px);
  cursor: pointer;
  transition: transform 0.15s ease, background 0.15s ease;
}

.kasa-card:active {
  transform: scale(0.985);
  background: var(--dsx-bg-ffffff-a092);
}

.kasa-card__avatar {
  display: grid;
  width: 46px;
  height: 46px;
  place-items: center;
  border-radius: 15px;
  color: #ffffff;
  background: var(--ds-primary, var(--dsx-bg-11161b));
  font: 700 15px 'Space Grotesk', sans-serif;
  box-shadow: 0 6px 16px rgba(17, 22, 27, 0.15);
}

.avatar--gelir {
  background: var(--dsx-bg-11161b);
  border-left: 3px solid #10b981;
}

.avatar--gider {
  background: var(--dsx-bg-11161b);
  border-left: 3px solid #ef4444;
}

.kasa-card__info {
  min-width: 0;
}

.kasa-card__name {
  margin: 0;
  font-size: 13.5px;
  font-weight: 700;
  color: var(--ds-ink);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  letter-spacing: -0.02em;
}

.kasa-card__company {
  margin: 2px 0 0;
  font-size: 11px;
  color: var(--ds-muted);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.kasa-card__meta {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-top: 4px;
}

.kasa-meta-item {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 10px;
  color: var(--ds-slate);
}

.kasa-meta-item--desc {
  max-width: 140px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.kasa-card__balance-block {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 3px;
}

.balance-amount {
  font-size: 12.5px;
  font-weight: 800;
  letter-spacing: -0.02em;
}

.balance-badge {
  display: inline-block;
  padding: 2px 6px;
  border-radius: 999px;
  font: 700 8.5px 'Space Grotesk', sans-serif;
  text-transform: uppercase;
}

.badge-debt {
  background: rgba(239, 68, 68, 0.12);
  color: #dc2626;
}

.badge-credit {
  background: rgba(16, 185, 129, 0.12);
  color: var(--dsx-fg-059669);
}

.kasa-card__actions {
  display: flex;
  align-items: center;
  gap: 4px;
  margin-top: 2px;
}

.quick-action-btn {
  display: grid;
  place-items: center;
  width: 26px;
  height: 26px;
  border-radius: 50%;
  border: 1px solid rgba(16, 185, 129, 0.3);
  background: rgba(16, 185, 129, 0.1);
  color: var(--dsx-fg-059669);
  cursor: pointer;
  transition: transform 0.12s ease;
}

.quick-action-btn:active {
  transform: scale(0.9);
}

.chevron-icon {
  color: var(--ds-muted);
}

/* Load More */
.kasa-load-more {
  text-align: center;
  padding: 10px 0;
}

.load-more-btn {
  width: 100%;
  height: 42px;
  border-radius: 14px;
  border: 1px solid var(--dsx-bd-ffffff-a090);
  background: var(--dsx-bg-ffffff-a080);
  box-shadow: var(--ds-shadow-card);
  color: var(--ds-ink);
  font: 700 12px 'Space Grotesk', sans-serif;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
}

/* 5. FLOATING ACTIONS (Cari Takipteki Sağ Alt Butonlar) */
.kasa-floating-actions {
  position: fixed;
  right: 18px;
  bottom: calc(82px + var(--ds-safe-bottom));
  display: flex;
  flex-direction: column;
  gap: 10px;
  z-index: 45;
}

.floating-circle-btn {
  width: 52px;
  height: 52px;
  border-radius: 50%;
  border: 1px solid rgba(255, 255, 255, 0.4);
  background: var(--ds-primary, var(--dsx-bg-11161b));
  color: #ffffff;
  box-shadow: 0 10px 24px rgba(17, 22, 27, 0.38);
  display: grid;
  place-items: center;
  cursor: pointer;
  transition: transform 0.15s ease;
}

.floating-circle-btn:active {
  transform: scale(0.92);
}

.floating-circle-btn--history {
  width: 46px;
  height: 46px;
  background: var(--dsx-bg-1e293b);
}

/* 6 & 7 & 8. BOTTOM SHEETS & MODALS (Cari Takip Standartları) */
.ds-modal-layer {
  position: fixed;
  inset: 0;
  z-index: 100;
  background: rgba(17, 22, 27, 0.6);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  display: flex;
  align-items: flex-end;
  justify-content: center;
}

.ds-bottom-sheet {
  width: 100%;
  max-width: 560px;
  max-height: 90vh;
  background: var(--dsx-bg-ffffff);
  border-radius: 28px 28px 0 0;
  box-shadow: 0 -16px 48px rgba(0, 0, 0, 0.25);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  padding-bottom: var(--ds-safe-bottom, 16px);
}

.sheet-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px 14px;
  border-bottom: 1px solid rgba(140, 155, 174, 0.15);
}

.sheet-title {
  display: flex;
  align-items: center;
  gap: 12px;
}

.sheet-icon-box {
  display: grid;
  place-items: center;
  width: 38px;
  height: 38px;
  border-radius: 12px;
  background: var(--dsx-bg-11161b-a008);
  color: var(--ds-primary-ink, var(--dsx-fg-11161b));
}

.sheet-title h3 {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
  color: var(--ds-ink);
}

.sheet-title small {
  display: block;
  font-size: 11px;
  color: var(--ds-muted);
}

.sheet-close-btn {
  display: grid;
  place-items: center;
  width: 34px;
  height: 34px;
  border-radius: 50%;
  border: none;
  background: rgba(140, 155, 174, 0.14);
  color: var(--ds-slate);
  cursor: pointer;
}

.sheet-form {
  padding: 16px 20px 24px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

/* TYPE SEGMENTED CONTROL */
.type-segmented-control {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
}

.seg-btn {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border-radius: 16px;
  border: 1.5px solid rgba(140, 155, 174, 0.2);
  background: var(--dsx-bg-f8fafc);
  cursor: pointer;
  text-align: left;
  transition: all 0.15s ease;
}

.seg-icon-box {
  display: grid;
  place-items: center;
  width: 32px;
  height: 32px;
  border-radius: 10px;
  background: rgba(140, 155, 174, 0.15);
  color: var(--ds-slate);
}

.seg-text strong {
  display: block;
  font-size: 12px;
  color: var(--ds-ink);
}

.seg-text small {
  display: block;
  font-size: 10px;
  color: var(--ds-muted);
}

.seg-btn--credit.is-active {
  border-color: #10b981;
  background: rgba(16, 185, 129, 0.08);
}
.seg-btn--credit.is-active .seg-icon-box {
  background: #10b981;
  color: #ffffff;
}

.seg-btn--debt.is-active {
  border-color: #ef4444;
  background: rgba(239, 68, 68, 0.08);
}
.seg-btn--debt.is-active .seg-icon-box {
  background: #ef4444;
  color: #ffffff;
}

/* FORM ALANLARI */
.form-grid-two {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.form-group label {
  font: 700 10px 'Space Grotesk', sans-serif;
  letter-spacing: 0.06em;
  color: var(--ds-muted);
  text-transform: uppercase;
}

.input-wrap {
  position: relative;
  display: flex;
  align-items: center;
  height: 44px;
  padding: 0 12px;
  border: 1.5px solid rgba(140, 155, 174, 0.24);
  border-radius: 14px;
  background: var(--dsx-bg-f8fafc);
  transition: border-color 0.15s ease;
}

.input-wrap:focus-within {
  border-color: var(--ds-primary-ink, #11161b);
  background: var(--dsx-bg-ffffff);
}

.input-icon {
  color: var(--ds-muted);
  margin-right: 8px;
  flex-shrink: 0;
}

.input-wrap input,
.select-plain {
  width: 100%;
  border: none;
  background: transparent;
  outline: none;
  font-size: 13px;
  color: var(--ds-ink);
}

.amount-wrap {
  background: var(--dsx-bg-ffffff);
  border-color: var(--dsx-bd-11161b-a030);
}

.currency-badge {
  font-size: 16px;
  font-weight: 800;
  color: var(--ds-muted);
  margin-right: 6px;
}

.input-amount {
  font-size: 16px !important;
  font-weight: 800 !important;
  color: var(--ds-ink) !important;
}

.textarea-wrap {
  height: auto;
  padding: 8px 12px;
  align-items: flex-start;
}

.textarea-icon {
  margin-top: 2px;
}

.textarea-wrap textarea {
  width: 100%;
  border: none;
  background: transparent;
  outline: none;
  font-size: 13px;
  color: var(--ds-ink);
  resize: none;
}

/* Hızlı Tutar Çipleri */
.quick-chips-row {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}

.quick-chip {
  padding: 5px 10px;
  border-radius: 999px;
  border: 1px solid rgba(140, 155, 174, 0.25);
  background: var(--dsx-bg-f1f5f9);
  font: 700 10.5px 'Space Grotesk', sans-serif;
  color: var(--ds-slate);
  cursor: pointer;
}

.quick-chip:active {
  background: var(--dsx-bg-e2e8f0);
}

.submit-action-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  height: 48px;
  border-radius: 16px;
  border: none;
  background: var(--ds-primary, var(--dsx-bg-11161b));
  color: #ffffff;
  font: 700 14px 'Space Grotesk', sans-serif;
  cursor: pointer;
  margin-top: 6px;
}

.submit-action-btn:active {
  transform: scale(0.98);
}

.form-error {
  color: #dc2626;
  font-size: 12px;
  font-weight: 600;
  margin: 0;
}

/* HAREKET DETAYI / MAKBUZ */
.movement-detail-body {
  padding: 16px 20px 24px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.movement-amount-hero {
  text-align: center;
  padding: 18px 14px;
  background: var(--dsx-bg-f8fafc);
  border-radius: 20px;
  border: 1px solid rgba(140, 155, 174, 0.16);
}

.hero-type-label {
  display: block;
  font: 700 10px 'Space Grotesk', sans-serif;
  letter-spacing: 0.1em;
  color: var(--ds-muted);
}

.hero-amount-text {
  display: block;
  margin-top: 4px;
  font-size: 30px;
  font-weight: 800;
  letter-spacing: -0.04em;
}

.detail-data-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.detail-data-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 10px;
  padding-bottom: 8px;
  border-bottom: 1px solid rgba(140, 155, 174, 0.12);
  font-size: 13px;
}

.detail-data-row span {
  color: var(--ds-muted);
  font-size: 12px;
}

.detail-data-row strong,
.detail-data-row p {
  color: var(--ds-ink);
  text-align: right;
  margin: 0;
  font-weight: 700;
}

.detail-action-buttons {
  display: flex;
  gap: 10px;
  margin-top: 4px;
}

.detail-edit-btn {
  flex: 1;
  height: 46px;
  border-radius: 14px;
  border: none;
  background: rgba(245, 158, 11, 0.14);
  color: var(--dsx-fg-d97706);
  font: 700 13px 'Space Grotesk', sans-serif;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
}

.danger-delete-btn {
  flex: 1;
  height: 46px;
  border-radius: 14px;
  border: none;
  background: rgba(239, 68, 68, 0.12);
  color: #dc2626;
  font: 700 13px 'Space Grotesk', sans-serif;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
}

/* GEÇMİŞ LİSTESİ */
.history-controls-card {
  padding: 12px;
  border-radius: 18px;
  background: var(--dsx-bg-f8fafc);
  border: 1px solid rgba(140, 155, 174, 0.16);
}

.date-input-plain {
  width: 100%;
  height: 38px;
  padding: 0 10px;
  border: 1.5px solid rgba(140, 155, 174, 0.2);
  border-radius: 12px;
  background: var(--dsx-bg-ffffff);
  font-size: 12px;
  color: var(--ds-ink);
  outline: none;
}

.history-list-wrap {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.history-row-card {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border-radius: 16px;
  background: var(--dsx-bg-f8fafc);
  border: 1px solid rgba(140, 155, 174, 0.14);
  cursor: pointer;
}

.history-row-card:active {
  background: var(--dsx-bg-f1f5f9);
}

.history-row-date {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 5px 8px;
  background: rgba(140, 155, 174, 0.12);
  border-radius: 10px;
  font-size: 9px;
  font-weight: 800;
  line-height: 1.1;
  flex-shrink: 0;
}

.day-num { color: var(--ds-primary-ink, var(--dsx-fg-11161b)); }
.time-str { color: var(--ds-muted); }

.history-row-content {
  flex: 1;
  min-width: 0;
}

.history-row-content h4 {
  margin: 0;
  font-size: 12px;
  font-weight: 700;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.history-row-content small {
  font-size: 10px;
  color: var(--ds-muted);
}

.history-row-amount {
  text-align: right;
  flex-shrink: 0;
}

.history-row-amount strong {
  display: block;
  font-size: 12px;
  font-weight: 800;
}

.history-row-amount small {
  font-size: 9px;
  color: var(--ds-muted);
}

/* SİLME DİYALOGU */
.delete-dialog-box {
  background: var(--dsx-bg-ffffff);
  border-radius: 24px;
  padding: 24px 20px;
  max-width: 340px;
  width: 90%;
  text-align: center;
  box-shadow: 0 16px 48px rgba(0, 0, 0, 0.25);
  margin-bottom: 40px;
}

.delete-dialog-icon {
  width: 50px;
  height: 50px;
  border-radius: 50%;
  background: rgba(239, 68, 68, 0.12);
  color: #dc2626;
  display: grid;
  place-items: center;
  margin: 0 auto 12px;
}

.delete-dialog-box h3 {
  margin: 0 0 6px;
  font-size: 17px;
  font-weight: 700;
  color: var(--ds-ink);
}

.delete-dialog-box p {
  margin: 0 0 18px;
  font-size: 13px;
  color: var(--ds-muted);
  line-height: 1.4;
}

.dialog-actions {
  display: flex;
  gap: 10px;
}

.btn-dialog-confirm {
  flex: 1;
  height: 44px;
  border-radius: 14px;
  border: none;
  background: #dc2626;
  color: #ffffff;
  font: 700 13px 'Space Grotesk', sans-serif;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}

.btn-dialog-cancel {
  flex: 1;
  height: 44px;
  border-radius: 14px;
  border: 1.5px solid rgba(140, 155, 174, 0.2);
  background: var(--dsx-bg-f8fafc);
  color: var(--ds-ink);
  font: 700 13px 'Space Grotesk', sans-serif;
  cursor: pointer;
}

/* DİĞER DURUMLAR */
.ds-loading {
  display: grid;
  gap: 10px;
}

.ds-loading i {
  display: block;
  border-radius: 20px;
  background: linear-gradient(90deg, rgba(255, 255, 255, 0.4), var(--dsx-bg-ffffff-a080), rgba(255, 255, 255, 0.4));
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
}

@keyframes shimmer {
  0% { background-position: -200% 0; }
  100% { background-position: 200% 0; }
}

.ds-state {
  text-align: center;
  padding: 40px 20px;
  color: var(--ds-muted);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
}

.ds-state button {
  margin-top: 10px;
  padding: 9px 16px;
  border-radius: 14px;
  border: none;
  background: var(--ds-primary, var(--dsx-bg-11161b));
  color: #ffffff;
  font: 700 12px 'Space Grotesk', sans-serif;
  cursor: pointer;
}

.loading-mini-spin {
  width: 16px;
  height: 16px;
  border: 2px solid rgba(255, 255, 255, 0.4);
  border-top-color: var(--dsx-bd-ffffff);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

/* MODAL ANIMATION */
.ds-modal-enter-active,
.ds-modal-leave-active {
  transition: opacity 0.25s ease;
}

.ds-modal-enter-active .ds-bottom-sheet,
.ds-modal-leave-active .ds-bottom-sheet {
  transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}

.ds-modal-enter-from,
.ds-modal-leave-to {
  opacity: 0;
}

.ds-modal-enter-from .ds-bottom-sheet,
.ds-modal-leave-to .ds-bottom-sheet {
  transform: translateY(100%);
}

.py-6 {
  padding-top: 24px;
  padding-bottom: 24px;
}
</style>
