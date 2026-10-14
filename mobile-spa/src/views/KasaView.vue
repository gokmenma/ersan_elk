<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import {
  ArrowLeft,
  ArrowUpRight,
  Calendar,
  Check,
  ChevronRight,
  Clock,
  History,
  Landmark,
  Pencil,
  Plus,
  Receipt,
  RefreshCw,
  Search,
  Sparkles,
  Tag,
  Trash2,
  TrendingDown,
  TrendingUp,
  User,
  Wallet,
  X,
} from '@lucide/vue'
import { kasaApi } from '../services/kasa.js'
import { auth } from '../services/auth.js'
import TopBar from '../components/TopBar.vue'

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
const selectedType = ref('') // '' (Tümü), '1' (Gelir), '2' (Gider)
const searchQuery = ref('')
let searchDebounce = null

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

// Makbuz / Detay Modalı Durumu
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

// Yıl seçenekleri (Son 4 yıl)
const yearOptions = computed(() => {
  const years = []
  for (let y = currentYear; y >= currentYear - 3; y--) {
    years.push(String(y))
  }
  return years
})

// Ay seçenekleri
const monthOptions = [
  { value: '', label: 'Tüm Yıl' },
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

// Gelir/Gider Oran Çubuğu Hesaplama
const incomeRatio = computed(() => {
  const income = Math.max(0, Number(summary.value.toplam_gelir) || 0)
  const expense = Math.max(0, Number(summary.value.toplam_gider) || 0)
  const total = income + expense
  if (!total) return 50
  return Math.min(100, Math.max(0, Math.round((income / total) * 100)))
})

function getNowIsoString() {
  const now = new Date()
  const offset = now.getTimezoneOffset() * 60000
  return new Date(now.getTime() - offset).toISOString().slice(0, 16)
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
    const res = await kasaApi.list({
      yil: selectedYear.value,
      ay: selectedMonth.value,
      tip: selectedType.value,
      search: searchQuery.value,
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

watch([selectedYear, selectedMonth, selectedType], () => {
  loadKasa(true)
})

watch(searchQuery, () => {
  clearTimeout(searchDebounce)
  searchDebounce = setTimeout(() => {
    loadKasa(true)
  }, 350)
})

// Form Modal Aç/Kapat
function openCreateModal() {
  isEditing.value = false
  formError.value = ''
  formData.value = {
    id: '',
    type: 2,
    tutar: '',
    islem_tarihi: getNowIsoString(),
    kategori: '',
    hesap_adi: '',
    aciklama: '',
    odeme_sekli: 'Nakit',
    plaka: '',
  }
  isFormModalOpen.value = true
}

async function openEditModal(item) {
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
        islem_tarihi: d.tarih_input || getNowIsoString(),
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

function confirmDelete(item) {
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
  <section class="ds-screen ds-kasa">
    <!-- 1. Üst Navigasyon & Aksiyonlar (TopBar) -->
    <TopBar
      title="Kasa Takibi"
      eyebrow="FİNANS & KASA YÖNETİMİ"
      :subtitle="auth.state.company?.name || 'Ersan Elektrik'"
    >
      <template #actions>
        <button
          type="button"
          class="clay-circle-btn"
          title="İşlem Geçmişi"
          aria-label="İşlem Geçmişi"
          @click="openHistoryModal"
        >
          <History :size="17" />
        </button>
        <button
          type="button"
          class="clay-circle-btn"
          title="Yenile"
          aria-label="Yenile"
          :class="{ 'is-spinning': loading }"
          @click="loadKasa(true)"
        >
          <RefreshCw :size="17" />
        </button>
      </template>
    </TopBar>

    <!-- 2. Hero Bento Kasa Özeti (Soft Bento Cards) -->
    <section class="kasa-bento-hero" aria-label="Kasa Finans Özeti">
      <!-- Ana Hero Kart: Güncel Net Bakiye -->
      <article class="bento-main-card" @click="selectedType = ''">
        <header class="bento-main-card__head">
          <div class="bento-tag-wrap">
            <span class="bento-kicker">GÜNCEL NET BAKİYE</span>
            <span
              class="bento-pill-status"
              :class="summary.bakiye >= 0 ? 'is-positive' : 'is-negative'"
            >
              <span class="pill-dot"></span>
              <span>{{ summary.bakiye >= 0 ? 'Kasa Artıda' : 'Kasa Ekside' }}</span>
            </span>
          </div>
          <span class="bento-icon-badge bento-icon-badge--blue">
            <Wallet :size="20" />
          </span>
        </header>

        <div class="bento-main-card__amount">
          <strong :class="summary.bakiye >= 0 ? 'text-emerald' : 'text-rose'">
            {{ summary.bakiye < 0 ? '-' : '' }}{{ formatMoney(summary.bakiye) }}
          </strong>
        </div>

        <!-- İnce Nakit Oran Çubuğu -->
        <div class="segmented-track bento-flow-bar" title="Gelir vs Gider Dağılımı">
          <div class="seg-pill bento-flow-bar__fill" :style="{ width: `${incomeRatio}%` }"></div>
        </div>

        <footer class="bento-main-card__footer">
          <span>Toplam <b>{{ summary.toplam_islem }}</b> hareket kaydı</span>
          <span class="bento-link">Tümünü gör <ChevronRight :size="14" /></span>
        </footer>
      </article>

      <!-- İkili Alt Bento Kartları: Gelirler & Giderler -->
      <div class="bento-split-grid">
        <!-- Toplam Gelir Kartı -->
        <article
          class="bento-sub-card bento-sub-card--income"
          :class="{ 'is-active-tab': selectedType === '1' }"
          @click="selectedType = selectedType === '1' ? '' : '1'"
        >
          <div class="bento-sub-card__top">
            <span class="bento-kicker text-emerald">GELİRLER</span>
            <span class="bento-icon-badge bento-icon-badge--emerald">
              <TrendingUp :size="16" />
            </span>
          </div>
          <strong class="bento-sub-card__amount text-emerald">
            +{{ formatMoney(summary.toplam_gelir) }}
          </strong>
          <small class="bento-sub-card__meta">
            {{ summary.gelir_adet }} Tahsilat / Giriş
          </small>
        </article>

        <!-- Toplam Gider Kartı -->
        <article
          class="bento-sub-card bento-sub-card--expense"
          :class="{ 'is-active-tab': selectedType === '2' }"
          @click="selectedType = selectedType === '2' ? '' : '2'"
        >
          <div class="bento-sub-card__top">
            <span class="bento-kicker text-rose">GİDERLER</span>
            <span class="bento-icon-badge bento-icon-badge--rose">
              <TrendingDown :size="16" />
            </span>
          </div>
          <strong class="bento-sub-card__amount text-rose">
            -{{ formatMoney(summary.toplam_gider) }}
          </strong>
          <small class="bento-sub-card__meta">
            {{ summary.gider_adet }} Ödeme / Çıkış
          </small>
        </article>
      </div>
    </section>

    <!-- 3. Kontrol & Filtre Paneli (Clay Controls) -->
    <section class="kasa-controls-panel">
      <!-- Üst Satır: Yıl ve Ay Seçicileri -->
      <div class="kasa-date-pills">
        <div class="clay-select-pill">
          <Calendar :size="15" class="select-pill-icon" />
          <select v-model="selectedYear" class="clay-select" aria-label="Yıl Seçin">
            <option v-for="y in yearOptions" :key="y" :value="y">{{ y }} Yılı</option>
          </select>
        </div>

        <div class="clay-select-pill">
          <Clock :size="15" class="select-pill-icon" />
          <select v-model="selectedMonth" class="clay-select" aria-label="Ay Seçin">
            <option v-for="m in monthOptions" :key="m.value" :value="m.value">{{ m.label }}</option>
          </select>
        </div>
      </div>

      <!-- Orta Satır: Akıcı Segmented Tür Kontrolü -->
      <div class="clay-tabs-wrap" role="tablist">
        <button
          type="button"
          role="tab"
          class="clay-tab-pill"
          :class="{ active: selectedType === '' }"
          @click="selectedType = ''"
        >
          Tümü ({{ summary.toplam_islem }})
        </button>
        <button
          type="button"
          role="tab"
          class="clay-tab-pill clay-tab-pill--income"
          :class="{ active: selectedType === '1' }"
          @click="selectedType = '1'"
        >
          Gelir ({{ summary.gelir_adet }})
        </button>
        <button
          type="button"
          role="tab"
          class="clay-tab-pill clay-tab-pill--expense"
          :class="{ active: selectedType === '2' }"
          @click="selectedType = '2'"
        >
          Gider ({{ summary.gider_adet }})
        </button>
      </div>

      <!-- Alt Satır: Arama Girişi -->
      <div class="clay-search-bar kasa-search-bar">
        <Search :size="17" />
        <input
          v-model="searchQuery"
          type="search"
          class="clay-search-input"
          placeholder="Kategori, hesap veya açıklamada ara..."
          autocomplete="off"
        />
        <button
          v-if="searchQuery"
          type="button"
          class="search-box-clear"
          aria-label="Aramayı temizle"
          @click="searchQuery = ''"
        >
          <X :size="16" />
        </button>
      </div>
    </section>

    <!-- Hata Bildirimi -->
    <div v-if="error" class="ds-error-state">
      <RefreshCw :size="24" />
      <strong>Veriler Alınamadı</strong>
      <p>{{ error }}</p>
      <button type="button" @click="loadKasa(true)">Tekrar Dene</button>
    </div>

    <!-- Yükleniyor Durumu (Cam Skeleton / Spinner) -->
    <div v-if="loading" class="ds-loading-wrap">
      <div class="ds-pulse-spinner"></div>
      <span>Kasa hareketleri yükleniyor...</span>
    </div>

    <!-- Boş Durum -->
    <div v-else-if="items.length === 0" class="ds-empty-card">
      <div class="empty-icon-wrap">
        <Landmark :size="36" />
      </div>
      <h3>İşlem Kaydı Bulunmuyor</h3>
      <p>Seçilen dönem veya filtre kriterine uygun kasa hareketi bulunamadı.</p>
      <button type="button" class="btn-glass-primary" @click="openCreateModal">
        <Plus :size="18" />
        <span>Yeni İşlem Kaydet</span>
      </button>
    </div>

    <!-- 4. Kasa Hareketleri Stream Listesi (3 Katmanlı Glacier Row Cards) -->
    <section v-else class="kasa-stream-list" aria-label="Kasa Hareketleri">
      <article
        v-for="item in items"
        :key="item.id"
        class="kasa-row-card"
        @click="openDetailModal(item)"
      >
        <!-- Katman 1: Meta Bar (Rozet, Tarih, Tutar) -->
        <div class="kasa-row-card__header">
          <div class="kasa-row-card__meta">
            <!-- İşlem Tipi Rozeti -->
            <span
              class="kasa-status-pill"
              :class="item.type === 1 ? 'is-income' : 'is-expense'"
            >
              <span class="status-dot"></span>
              <component :is="item.type === 1 ? TrendingUp : TrendingDown" :size="12" />
              <span>{{ item.type === 1 ? 'GELİR' : 'GİDER' }}</span>
            </span>

            <!-- Tarih & Saat Hapı -->
            <span class="kasa-date-tag">
              <Calendar :size="12" />
              <span>{{ item.tarih_short }}</span>
              <small v-if="item.saat_short && item.saat_short !== '00:00'">{{ item.saat_short }}</small>
            </span>
          </div>

          <!-- Tutar -->
          <strong
            class="kasa-row-card__amount"
            :class="item.type === 1 ? 'text-emerald' : 'text-rose'"
          >
            {{ item.type === 1 ? '+' : '-' }}{{ item.tutar_formatted }}
          </strong>
        </div>

        <!-- Katman 2: Gövde (Kategori, Hesap, Açıklama) -->
        <div class="kasa-row-card__body">
          <h2 class="kasa-row-card__title">{{ item.kategori }}</h2>
          <div class="kasa-row-card__account">
            <User :size="13" class="account-icon" />
            <span>{{ item.hesap_adi }}</span>
          </div>
          <p v-if="item.aciklama" class="kasa-row-card__desc">
            {{ item.aciklama }}
          </p>
        </div>

        <!-- Katman 3: Footer (Yürüyen Bakiye & Aksiyon Hapı) -->
        <div class="kasa-row-card__footer">
          <span
            class="kasa-balance-chip"
            :class="item.bakiye < 0 ? 'is-borc' : 'is-alacak'"
          >
            <span>Bakiye:</span>
            <b>{{ item.bakiye_formatted }}</b>
            <small v-if="item.bakiye_yon">({{ item.bakiye_yon }})</small>
          </span>

          <span class="kasa-action-pill">
            <Receipt :size="13" />
            <span>Detay</span>
            <ChevronRight :size="13" />
          </span>
        </div>
      </article>

      <!-- Daha Fazla Yükle -->
      <div v-if="pagination.has_more" class="kasa-load-wrap">
        <button
          type="button"
          class="btn-glass-load"
          :disabled="loadingMore"
          @click="loadKasa(false)"
        >
          <span v-if="loadingMore" class="loading-mini-spin"></span>
          <span>{{ loadingMore ? 'Yükleniyor...' : 'Daha Fazla Kayıt Yükle' }}</span>
        </button>
      </div>
    </section>

    <!-- FAB: Modern Glacier Cam Buton -->
    <button
      type="button"
      class="kasa-fab-btn"
      aria-label="Yeni Kasa İşlemi Ekle"
      @click="openCreateModal"
    >
      <Plus :size="26" />
    </button>

    <!-- 5. Yeni / Düzenle İşlem Modalı (Fintech Bottom Sheet) -->
    <Transition name="sheet-anim">
      <div v-if="isFormModalOpen" class="sheet-overlay" @click.self="isFormModalOpen = false">
        <section class="sheet-container" role="dialog" aria-modal="true">
          <div class="sheet-handle" aria-hidden="true"></div>

          <header class="sheet-head">
            <div class="sheet-head__left">
              <span class="sheet-badge-icon" :class="formData.type === 1 ? 'is-income' : 'is-expense'">
                <Landmark :size="20" />
              </span>
              <div>
                <h2>{{ isEditing ? 'İşlemi Düzenle' : 'Yeni Kasa İşlemi' }}</h2>
                <small>{{ isEditing ? 'Kayıt bilgilerini güncelleyin' : 'Nakit veya banka hareketini girin' }}</small>
              </div>
            </div>
            <button type="button" class="sheet-close" aria-label="Kapat" @click="isFormModalOpen = false">
              <X :size="19" />
            </button>
          </header>

          <form class="sheet-scroll-body" @submit.prevent="handleSave">
            <div v-if="formError" class="sheet-error-tag">
              {{ formError }}
            </div>

            <!-- Gelir / Gider Büyük Dokunmatik Seçici -->
            <div class="fintech-type-selector">
              <button
                type="button"
                class="fintech-type-pill fintech-type-pill--income"
                :class="{ 'is-selected': formData.type === 1 }"
                @click="formData.type = 1"
              >
                <TrendingUp :size="18" />
                <span>GELİR (Giriş)</span>
              </button>

              <button
                type="button"
                class="fintech-type-pill fintech-type-pill--expense"
                :class="{ 'is-selected': formData.type === 2 }"
                @click="formData.type = 2"
              >
                <TrendingDown :size="18" />
                <span>GİDER (Çıkış)</span>
              </button>
            </div>

            <!-- Tutar Girişi (Fintech Hero Input) -->
            <div class="fintech-hero-input-wrap">
              <label for="f_tutar">İŞLEM TUTARI</label>
              <div class="hero-amount-box">
                <span class="currency-symbol">₺</span>
                <input
                  id="f_tutar"
                  v-model="formData.tutar"
                  type="number"
                  step="0.01"
                  min="0.01"
                  placeholder="0,00"
                  required
                  class="hero-amount-input"
                />
              </div>
            </div>

            <!-- Kategori -->
            <div class="glass-form-group">
              <label for="f_kategori">
                <Tag :size="14" />
                <span>KATEGORİ *</span>
              </label>
              <input
                id="f_kategori"
                v-model="formData.kategori"
                list="kategori-datalist"
                type="text"
                class="glass-form-input"
                placeholder="Örn: Akaryakıt, Maaş, Malzeme Satışı..."
                required
              />
              <datalist id="kategori-datalist">
                <option v-for="kat in categories" :key="kat" :value="kat" />
              </datalist>
            </div>

            <!-- Hesap / Kişi -->
            <div class="glass-form-group">
              <label for="f_hesap">
                <User :size="14" />
                <span>HESAP / MUHATAP KİŞİ *</span>
              </label>
              <input
                id="f_hesap"
                v-model="formData.hesap_adi"
                list="hesap-datalist"
                type="text"
                class="glass-form-input"
                placeholder="Örn: Kasa, Banka, İsim Soyisim..."
                required
              />
              <datalist id="hesap-datalist">
                <option v-for="acc in accounts" :key="acc" :value="acc" />
              </datalist>
            </div>

            <!-- Tarih & Saat -->
            <div class="glass-form-group">
              <label for="f_tarih">
                <Calendar :size="14" />
                <span>İŞLEM TARİHİ VE SAATİ *</span>
              </label>
              <input
                id="f_tarih"
                v-model="formData.islem_tarihi"
                type="datetime-local"
                class="glass-form-input"
                required
              />
            </div>

            <!-- Açıklama -->
            <div class="glass-form-group">
              <label for="f_aciklama">AÇIKLAMA</label>
              <textarea
                id="f_aciklama"
                v-model="formData.aciklama"
                rows="3"
                class="glass-form-textarea"
                placeholder="İşlem detaylarını veya fiş/fatura numarasını buraya not edebilirsiniz..."
              ></textarea>
            </div>

            <!-- İkili Ekstra Bilgiler -->
            <div class="glass-form-row">
              <div class="glass-form-group">
                <label for="f_odeme">ÖDEME KANALI</label>
                <select id="f_odeme" v-model="formData.odeme_sekli" class="glass-form-select">
                  <option value="Nakit">Nakit</option>
                  <option value="Banka / Havale">Banka / Havale</option>
                  <option value="Kredi Kartı">Kredi Kartı</option>
                  <option value="Çek / Senet">Çek / Senet</option>
                </select>
              </div>

              <div class="glass-form-group">
                <label for="f_plaka">İLGİLİ PLAKA</label>
                <input
                  id="f_plaka"
                  v-model="formData.plaka"
                  type="text"
                  class="glass-form-input"
                  placeholder="34 ABC 123"
                />
              </div>
            </div>

            <!-- Butonlar -->
            <div class="sheet-foot-buttons">
              <button
                type="button"
                class="btn-sheet-cancel"
                :disabled="formLoading"
                @click="isFormModalOpen = false"
              >
                Vazgeç
              </button>

              <button
                type="submit"
                class="btn-sheet-submit"
                :disabled="formLoading"
              >
                <Check v-if="!formLoading" :size="18" />
                <span v-else class="loading-mini-spin"></span>
                <span>{{ isEditing ? 'Değişiklikleri Kaydet' : 'İşlemi Kaydet' }}</span>
              </button>
            </div>
          </form>
        </section>
      </div>
    </Transition>

    <!-- 6. Lüks Fintech Makbuz / Detay Modalı -->
    <Transition name="sheet-anim">
      <div v-if="isDetailModalOpen && selectedDetail" class="sheet-overlay" @click.self="isDetailModalOpen = false">
        <section class="sheet-container sheet-container--receipt" role="dialog" aria-modal="true">
          <div class="sheet-handle" aria-hidden="true"></div>

          <header class="sheet-head">
            <div class="sheet-head__left">
              <span class="sheet-badge-icon" :class="selectedDetail.type === 1 ? 'is-income' : 'is-expense'">
                <Receipt :size="20" />
              </span>
              <div>
                <h2>İşlem Detay Fişi</h2>
                <small>{{ selectedDetail.tarih_formatted }}</small>
              </div>
            </div>
            <button type="button" class="sheet-close" aria-label="Kapat" @click="isDetailModalOpen = false">
              <X :size="19" />
            </button>
          </header>

          <div class="sheet-scroll-body">
            <!-- Fintech Kartı -->
            <div class="fintech-receipt-card">
              <div class="receipt-header-hero">
                <span
                  class="receipt-status-pill"
                  :class="selectedDetail.type === 1 ? 'is-income' : 'is-expense'"
                >
                  <component :is="selectedDetail.type === 1 ? TrendingUp : TrendingDown" :size="14" />
                  <span>{{ selectedDetail.type === 1 ? 'GELİR HAREKETİ' : 'GİDER HAREKETİ' }}</span>
                </span>

                <h3
                  class="receipt-hero-amount"
                  :class="selectedDetail.type === 1 ? 'text-emerald' : 'text-rose'"
                >
                  {{ selectedDetail.type === 1 ? '+' : '-' }}{{ selectedDetail.tutar_formatted }}
                </h3>

                <span class="receipt-hero-category">{{ selectedDetail.kategori }}</span>
              </div>

              <!-- Parametre Izgarası -->
              <div class="receipt-meta-grid">
                <div class="receipt-cell">
                  <span class="receipt-cell__lbl">Hesap / Muhatap</span>
                  <strong class="receipt-cell__val">{{ selectedDetail.hesap_adi }}</strong>
                </div>

                <div class="receipt-cell">
                  <span class="receipt-cell__lbl">Tarih & Saat</span>
                  <strong class="receipt-cell__val">{{ selectedDetail.tarih_formatted }}</strong>
                </div>

                <div class="receipt-cell">
                  <span class="receipt-cell__lbl">Yürüyen Bakiye</span>
                  <strong
                    class="receipt-cell__val"
                    :class="selectedDetail.bakiye < 0 ? 'text-rose' : 'text-emerald'"
                  >
                    {{ selectedDetail.bakiye_formatted }} {{ selectedDetail.bakiye_yon ? `(${selectedDetail.bakiye_yon})` : '' }}
                  </strong>
                </div>

                <div v-if="selectedDetail.odeme_sekli" class="receipt-cell">
                  <span class="receipt-cell__lbl">Ödeme Şekli</span>
                  <strong class="receipt-cell__val">{{ selectedDetail.odeme_sekli }}</strong>
                </div>

                <div v-if="selectedDetail.plaka" class="receipt-cell">
                  <span class="receipt-cell__lbl">İlgili Plaka</span>
                  <strong class="receipt-cell__val">{{ selectedDetail.plaka }}</strong>
                </div>
              </div>

              <div v-if="selectedDetail.aciklama" class="receipt-note-box">
                <span class="receipt-cell__lbl">Açıklama</span>
                <p>{{ selectedDetail.aciklama }}</p>
              </div>
            </div>

            <!-- Aksiyonlar -->
            <div class="receipt-action-buttons">
              <button
                type="button"
                class="btn-action-pill btn-action-pill--edit"
                @click="openEditModal(selectedDetail)"
              >
                <Pencil :size="17" />
                <span>Düzenle</span>
              </button>

              <button
                type="button"
                class="btn-action-pill btn-action-pill--delete"
                @click="confirmDelete(selectedDetail)"
              >
                <Trash2 :size="17" />
                <span>Kaydı Sil</span>
              </button>
            </div>
          </div>
        </section>
      </div>
    </Transition>

    <!-- 7. Tüm Hareketler (İşlem Geçmişi) Modalı -->
    <Transition name="sheet-anim">
      <div v-if="isHistoryModalOpen" class="sheet-overlay" @click.self="isHistoryModalOpen = false">
        <section class="sheet-container sheet-container--history" role="dialog" aria-modal="true">
          <div class="sheet-handle" aria-hidden="true"></div>

          <header class="sheet-head">
            <div class="sheet-head__left">
              <span class="sheet-badge-icon">
                <History :size="20" />
              </span>
              <div>
                <h2>İşlem Geçmişi</h2>
                <small>Kasa hareketleri genel dökümü</small>
              </div>
            </div>
            <button type="button" class="sheet-close" aria-label="Kapat" @click="isHistoryModalOpen = false">
              <X :size="19" />
            </button>
          </header>

          <div class="sheet-scroll-body">
            <!-- Geçmiş Filtreleri -->
            <div class="history-controls-box">
              <div class="glass-search-box">
                <Search :size="16" class="search-box-icon" />
                <input
                  v-model="historyFilter.search"
                  type="search"
                  class="search-box-input"
                  placeholder="Geçmişte ara..."
                  @input="loadHistory"
                />
              </div>

              <div class="history-date-row">
                <input
                  v-model="historyFilter.baslangic"
                  type="date"
                  class="glass-form-input text-xs"
                  aria-label="Başlangıç Tarihi"
                  @change="loadHistory"
                />
                <input
                  v-model="historyFilter.bitis"
                  type="date"
                  class="glass-form-input text-xs"
                  aria-label="Bitiş Tarihi"
                  @change="loadHistory"
                />
              </div>

              <div class="glass-segmented-control">
                <button
                  type="button"
                  class="segment-item"
                  :class="{ 'is-selected': historyFilter.type === 'all' }"
                  @click="setHistoryType('all')"
                >
                  Tümü
                </button>
                <button
                  type="button"
                  class="segment-item segment-item--income"
                  :class="{ 'is-selected': historyFilter.type === '1' }"
                  @click="setHistoryType('1')"
                >
                  Gelir
                </button>
                <button
                  type="button"
                  class="segment-item segment-item--expense"
                  :class="{ 'is-selected': historyFilter.type === '2' }"
                  @click="setHistoryType('2')"
                >
                  Gider
                </button>
              </div>
            </div>

            <!-- Geçmiş Listesi -->
            <div v-if="historyLoading" class="ds-loading-wrap py-6">
              <div class="ds-pulse-spinner"></div>
              <span>Kayıtlar listeleniyor...</span>
            </div>

            <div v-else-if="historyItems.length === 0" class="ds-empty-card py-6">
              <p>Kriterlere uygun kayıt bulunamadı.</p>
            </div>

            <div v-else class="history-stream">
              <article
                v-for="h in historyItems"
                :key="h.id"
                class="history-card"
                @click="openDetailModal(h)"
              >
                <div class="history-card__date">
                  <span class="day-str">{{ h.date_short }}</span>
                  <span class="time-str">{{ h.time_short }}</span>
                </div>

                <div class="history-card__body">
                  <h4>{{ h.kategori_adi }}</h4>
                  <small>{{ h.hesap_adi }}</small>
                </div>

                <div class="history-card__right">
                  <strong :class="h.type === 1 ? 'text-emerald' : 'text-rose'">
                    {{ h.type === 1 ? '+' : '-' }}{{ h.tutar_formatted }}
                  </strong>
                  <small>Bakiye: {{ h.yuruyen_formatted }} {{ h.yuruyen_yon ? `(${h.yuruyen_yon})` : '' }}</small>
                </div>
              </article>
            </div>
          </div>
        </section>
      </div>
    </Transition>

    <!-- 8. Silme Onay Modalı -->
    <Transition name="fade-anim">
      <div v-if="isDeleteConfirmOpen" class="dialog-backdrop">
        <div class="glass-dialog">
          <div class="dialog-warning-icon">
            <Trash2 :size="26" />
          </div>
          <h3>İşlem Kaydı Silinecek</h3>
          <p>
            "<strong>{{ itemToDelete?.kategori }}</strong>" kalemine ait
            {{ itemToDelete?.tutar_formatted }} tutarındaki işlem silinecek. Onaylıyor musunuz?
          </p>
          <div class="dialog-buttons">
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
              class="btn-dialog-danger"
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
/* ==========================================================================
   SOFT BENTO & CLAYMORPHIC UI — KASA MODÜLÜ
   ========================================================================== */

.ds-kasa {
  display: flex;
  flex-direction: column;
  gap: 16px;
  max-width: 680px;
  margin: 0 auto;
  padding-bottom: calc(var(--nav-height, 68px) + 36px + env(safe-area-inset-bottom)) !important;
  font-family: var(--font-base, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif);
}

/* 2. Hero Bento Kasa Özeti */
.kasa-bento-hero {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.bento-main-card {
  padding: 22px 20px 18px;
  border-radius: var(--ds-radius-card, 28px);
  border: 1px solid rgba(255, 255, 255, 0.9);
  background: var(--ds-surface);
  box-shadow: var(--ds-shadow-bento, 0 10px 24px rgba(16, 24, 40, 0.08));
  cursor: pointer;
  transition: transform 0.18s ease;
}

.bento-main-card:active {
  transform: scale(0.99);
}

.bento-main-card__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.bento-tag-wrap {
  display: flex;
  align-items: center;
  gap: 8px;
}

.bento-kicker {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.1em;
  color: var(--ds-muted);
  text-transform: uppercase;
}

.bento-pill-status {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 3px 9px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 700;
}

.bento-pill-status.is-positive {
  background: rgba(16, 185, 129, 0.12);
  color: var(--ds-emerald-ink);
}

.bento-pill-status.is-negative {
  background: rgba(239, 68, 68, 0.12);
  color: var(--ds-danger-ink);
}

.pill-dot {
  width: 5px;
  height: 5px;
  border-radius: 50%;
  background: currentColor;
}

.bento-icon-badge {
  width: 38px;
  height: 38px;
  border-radius: 12px;
  display: grid;
  place-items: center;
}

.bento-icon-badge--blue {
  background: var(--ds-surface-subtle);
  color: var(--ds-ink);
}

.bento-icon-badge--emerald {
  background: rgba(16, 185, 129, 0.12);
  color: var(--ds-emerald-ink);
}

.bento-icon-badge--rose {
  background: rgba(239, 68, 68, 0.12);
  color: var(--ds-danger-ink);
}

.bento-main-card__amount {
  margin: 12px 0 12px;
}

.bento-main-card__amount strong {
  font-size: 32px;
  font-weight: 800;
  line-height: 1.1;
  letter-spacing: -0.04em;
}

.bento-flow-bar {
  margin-bottom: 14px;
}

.bento-flow-bar__fill {
  transition: width 0.4s ease;
}

.bento-main-card__footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 12px;
  color: var(--ds-muted);
  border-top: 1px solid rgba(0, 0, 0, 0.05);
  padding-top: 12px;
}

.bento-main-card__footer b {
  color: var(--ds-ink);
}

.bento-link {
  display: inline-flex;
  align-items: center;
  gap: 2px;
  color: var(--ds-ink);
  font-weight: 700;
}

/* İkili Alt Bento Izgarası */
.bento-split-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

.bento-sub-card {
  padding: 16px 18px;
  border-radius: 22px;
  border: 1px solid rgba(255, 255, 255, 0.9);
  background: var(--ds-surface);
  box-shadow: var(--ds-shadow-bento, 0 10px 24px rgba(16, 24, 40, 0.08));
  cursor: pointer;
  transition: transform 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease;
  display: flex;
  flex-direction: column;
}

.bento-sub-card:active {
  transform: scale(0.98);
}

.bento-sub-card.is-active-tab {
  border-color: var(--ds-surface-dark, var(--ds-surface-dark));
  box-shadow: 0 10px 26px rgba(25, 29, 36, 0.16);
}

.bento-sub-card__top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 6px;
}

.bento-sub-card__amount {
  font-size: 18px;
  font-weight: 800;
  line-height: 1.2;
  letter-spacing: -0.02em;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.bento-sub-card__meta {
  font-size: 11px;
  color: var(--ds-muted);
  margin-top: 4px;
}

/* 3. Kontroller Paneli */
.kasa-controls-panel {
  padding: 16px;
  border-radius: 24px;
  border: 1px solid rgba(255, 255, 255, 0.9);
  background: var(--ds-surface);
  box-shadow: var(--ds-shadow-bento, 0 10px 24px rgba(16, 24, 40, 0.08));
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.kasa-date-pills {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}

.clay-select-pill {
  position: relative;
  display: flex;
  align-items: center;
  background: var(--ds-surface-subtle);
  border: 1px solid rgba(0, 0, 0, 0.06);
  border-radius: 16px;
  padding: 0 12px;
  height: 42px;
}

.select-pill-icon {
  color: var(--ds-muted);
  margin-right: 8px;
  flex-shrink: 0;
}

.clay-select {
  width: 100%;
  border: none;
  background: transparent;
  font-size: 12px;
  font-weight: 700;
  color: var(--ds-ink);
  outline: none;
  cursor: pointer;
}

.clay-tabs-wrap {
  display: flex;
  gap: 6px;
  background: var(--ds-surface-subtle);
  padding: 4px;
  border-radius: 16px;
}

.clay-tab-pill {
  flex: 1;
  height: 36px;
  border: none;
  background: transparent;
  color: var(--ds-muted);
  font-size: 11px;
  font-weight: 700;
  border-radius: 12px;
  cursor: pointer;
  transition: all 0.18s ease;
}

.clay-tab-pill.active {
  background: var(--ds-surface-dark, var(--ds-surface-dark));
  color: var(--ds-surface);
  box-shadow: 0 4px 12px rgba(25, 29, 36, 0.22);
}

.clay-tab-pill--income.active {
  background: var(--ds-emerald-ink);
  color: var(--ds-surface);
  box-shadow: 0 4px 12px rgba(5, 150, 105, 0.25);
}

.clay-tab-pill--expense.active {
  background: var(--ds-danger-ink);
  color: var(--ds-surface);
  box-shadow: 0 4px 12px rgba(220, 38, 38, 0.25);
}

.kasa-search-bar {
  position: relative;
}

.search-box-clear {
  position: absolute;
  right: 12px;
  background: none;
  border: none;
  color: var(--ds-muted-light);
  padding: 4px;
  cursor: pointer;
}

/* 4. Kasa Hareketleri Stream Listesi */
.kasa-stream-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.kasa-row-card {
  padding: 16px 18px;
  border-radius: 22px;
  border: 1px solid rgba(255, 255, 255, 0.9);
  background: var(--ds-surface);
  box-shadow: var(--ds-shadow-bento, 0 10px 24px rgba(16, 24, 40, 0.08));
  cursor: pointer;
  transition: transform 0.15s ease;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.kasa-row-card:active {
  transform: scale(0.98);
}

/* Katman 1: Meta */
.kasa-row-card__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.kasa-row-card__meta {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.kasa-status-pill {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 3px 8px;
  border-radius: 999px;
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.05em;
}

.kasa-status-pill.is-income {
  background: rgba(16, 185, 129, 0.12);
  color: var(--ds-emerald-ink);
}

.kasa-status-pill.is-expense {
  background: rgba(239, 68, 68, 0.12);
  color: var(--ds-danger-ink);
}

.status-dot {
  width: 4px;
  height: 4px;
  border-radius: 50%;
  background: currentColor;
}

.kasa-date-tag {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  color: var(--ds-muted);
}

.kasa-date-tag small {
  color: var(--ds-ink);
  font-weight: 700;
}

.kasa-row-card__amount {
  font-size: 16px;
  font-weight: 800;
  letter-spacing: -0.02em;
  flex-shrink: 0;
}

/* Katman 2: Body */
.kasa-row-card__body {
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.kasa-row-card__title {
  margin: 0;
  font-size: 15px;
  font-weight: 700;
  color: var(--ds-ink);
  line-height: 1.3;
}

.kasa-row-card__account {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 12px;
  color: var(--ds-muted);
}

.account-icon {
  color: var(--ds-muted-light);
  flex-shrink: 0;
}

.kasa-row-card__desc {
  margin: 4px 0 0;
  font-size: 12px;
  color: var(--ds-muted);
  line-height: 1.4;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

/* Katman 3: Footer */
.kasa-row-card__footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-top: 10px;
  border-top: 1px solid rgba(0, 0, 0, 0.05);
}

.kasa-balance-chip {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 11px;
  color: var(--ds-muted);
}

.kasa-balance-chip b {
  font-weight: 700;
}

.kasa-balance-chip.is-borc b {
  color: var(--ds-danger-ink);
}

.kasa-balance-chip.is-alacak b {
  color: var(--ds-emerald-ink);
}

.kasa-action-pill {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  font-weight: 700;
  color: var(--ds-ink);
}

/* Load More */
.kasa-load-wrap {
  text-align: center;
  padding: 6px 0 16px;
}

.btn-glass-load {
  width: 100%;
  height: 46px;
  border-radius: 18px;
  border: 1px solid rgba(255, 255, 255, 0.9);
  background: var(--ds-surface);
  box-shadow: var(--ds-shadow-bento, 0 10px 24px rgba(16, 24, 40, 0.08));
  color: var(--ds-ink);
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
}

/* FAB */
.kasa-fab-btn {
  position: fixed;
  right: 20px;
  bottom: calc(var(--nav-height, 68px) + 24px + env(safe-area-inset-bottom));
  width: 56px;
  height: 56px;
  border-radius: 50%;
  background: var(--ds-surface-dark, var(--ds-surface-dark));
  color: var(--ds-surface);
  border: 1px solid rgba(255, 255, 255, 0.2);
  box-shadow: 0 12px 28px rgba(25, 29, 36, 0.35);
  display: grid;
  place-items: center;
  cursor: pointer;
  z-index: 90;
  transition: transform 0.18s ease;
}

.kasa-fab-btn:active {
  transform: scale(0.92);
}

/* Durum Bildirimleri */
.ds-error-state,
.ds-empty-card,
.ds-loading-wrap {
  text-align: center;
  padding: 40px 20px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  color: var(--ds-muted);
}

.empty-icon-wrap {
  width: 68px;
  height: 68px;
  border-radius: 50%;
  background: var(--ds-surface-subtle);
  display: grid;
  place-items: center;
  color: var(--ds-muted);
  margin-bottom: 4px;
}

.ds-empty-card h3 {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
  color: var(--ds-ink);
}

.ds-empty-card p {
  margin: 0;
  font-size: 12px;
  max-width: 260px;
}

.btn-glass-primary {
  margin-top: 10px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 12px 20px;
  border-radius: 16px;
  border: none;
  background: var(--ds-surface-dark, var(--ds-surface-dark));
  color: var(--ds-surface);
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  box-shadow: 0 8px 20px rgba(25, 29, 36, 0.25);
}

.ds-pulse-spinner {
  width: 32px;
  height: 32px;
  border: 3px solid rgba(0, 0, 0, 0.08);
  border-top-color: var(--ds-ink);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

.loading-mini-spin {
  width: 16px;
  height: 16px;
  border: 2px solid rgba(255, 255, 255, 0.4);
  border-top-color: var(--ds-surface);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

/* Modallar */
.sheet-overlay {
  position: fixed;
  inset: 0;
  background: rgba(17, 22, 27, 0.45);
  backdrop-filter: blur(4px);
  -webkit-backdrop-filter: blur(4px);
  z-index: 1000;
  display: flex;
  justify-content: center;
  align-items: flex-end;
}

.sheet-container {
  width: 100%;
  max-width: 600px;
  max-height: 92vh;
  background: var(--ds-surface);
  border-radius: 32px 32px 0 0;
  box-shadow: 0 -12px 36px rgba(16, 24, 40, 0.18);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  padding-bottom: var(--ds-safe-bottom, 16px);
}

.sheet-handle {
  width: 44px;
  height: 5px;
  border-radius: 999px;
  background: var(--ds-muted-light);
  margin: 10px auto 0;
}

.sheet-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 20px 16px;
  border-bottom: 1px solid rgba(0, 0, 0, 0.06);
}

.sheet-head__left {
  display: flex;
  align-items: center;
  gap: 12px;
}

.sheet-badge-icon {
  width: 42px;
  height: 42px;
  border-radius: 14px;
  display: grid;
  place-items: center;
}

.sheet-badge-icon.is-income {
  background: rgba(16, 185, 129, 0.12);
  color: var(--ds-emerald-ink);
}

.sheet-badge-icon.is-expense {
  background: rgba(239, 68, 68, 0.12);
  color: var(--ds-danger-ink);
}

.sheet-head h2 {
  margin: 0;
  font-size: 18px;
  font-weight: 700;
  color: var(--ds-ink);
  letter-spacing: -0.02em;
}

.sheet-head small {
  font-size: 12px;
  color: var(--ds-muted);
}

.sheet-close {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  border: none;
  background: var(--ds-surface-subtle);
  color: var(--ds-ink);
  display: grid;
  place-items: center;
  cursor: pointer;
}

.sheet-scroll-body {
  padding: 18px 20px 24px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.fintech-type-selector {
  display: flex;
  gap: 10px;
}

.fintech-type-pill {
  flex: 1;
  height: 48px;
  border-radius: 16px;
  border: 1.5px solid rgba(0, 0, 0, 0.08);
  background: var(--ds-surface-subtle);
  color: var(--ds-muted);
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.18s ease;
}

.fintech-type-pill--income.is-selected {
  border-color: var(--ds-emerald);
  background: rgba(16, 185, 129, 0.1);
  color: var(--ds-emerald-ink);
}

.fintech-type-pill--expense.is-selected {
  border-color: var(--ds-danger);
  background: rgba(239, 68, 68, 0.1);
  color: var(--ds-danger-ink);
}

.fintech-hero-input-wrap {
  background: var(--ds-surface-subtle);
  border: 1.5px solid rgba(0, 0, 0, 0.08);
  border-radius: 20px;
  padding: 12px 18px;
  transition: border-color 0.18s ease;
}

.fintech-hero-input-wrap:focus-within {
  border-color: var(--ds-blue-ink);
  background: var(--ds-surface);
  box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
}

.fintech-hero-input-wrap label {
  display: block;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.1em;
  color: var(--ds-muted);
  text-transform: uppercase;
  margin-bottom: 4px;
}

.hero-amount-box {
  display: flex;
  align-items: center;
}

.currency-symbol {
  font-size: 28px;
  font-weight: 800;
  color: var(--ds-muted);
  margin-right: 6px;
}

.hero-amount-input {
  width: 100%;
  border: none;
  background: transparent;
  font-size: 28px;
  font-weight: 800;
  color: var(--ds-ink);
  outline: none;
}

.glass-form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.glass-form-group label {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.08em;
  color: var(--ds-muted);
  text-transform: uppercase;
  display: flex;
  align-items: center;
  gap: 4px;
}

.glass-form-input,
.glass-form-select,
.glass-form-textarea {
  width: 100%;
  border: 1.5px solid rgba(0, 0, 0, 0.08);
  border-radius: 16px;
  background: var(--ds-surface-subtle);
  color: var(--ds-ink);
  font-size: 13px;
  outline: none;
  padding: 12px 16px;
  transition: border-color 0.18s ease;
  font-family: inherit;
}

.glass-form-input:focus,
.glass-form-select:focus,
.glass-form-textarea:focus {
  border-color: var(--ds-blue-ink);
  background: var(--ds-surface);
  box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
}

.glass-form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

.sheet-foot-buttons {
  display: flex;
  gap: 12px;
  margin-top: 10px;
}

.btn-sheet-submit {
  flex: 2;
  height: 50px;
  border-radius: 18px;
  border: none;
  background: var(--ds-surface-dark, var(--ds-surface-dark));
  color: var(--ds-surface);
  font-size: 14px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  box-shadow: 0 10px 22px rgba(25, 29, 36, 0.25);
  cursor: pointer;
}

.btn-sheet-submit:active {
  transform: scale(0.98);
}

.btn-sheet-cancel {
  flex: 1;
  height: 50px;
  border-radius: 18px;
  border: 1px solid rgba(0, 0, 0, 0.08);
  background: var(--ds-surface-subtle);
  color: var(--ds-muted);
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
}

.sheet-error-tag {
  padding: 12px 16px;
  border-radius: 14px;
  background: rgba(239, 68, 68, 0.1);
  color: var(--ds-danger-ink);
  font-size: 12px;
  font-weight: 700;
}

/* Fiş Detay Kartı */
.fintech-receipt-card {
  background: var(--ds-surface-subtle);
  border: 1px solid rgba(0, 0, 0, 0.06);
  border-radius: 24px;
  padding: 24px 20px;
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.receipt-header-hero {
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
}

.receipt-status-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 4px 12px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.08em;
}

.receipt-status-pill.is-income {
  background: rgba(16, 185, 129, 0.12);
  color: var(--ds-emerald-ink);
}

.receipt-status-pill.is-expense {
  background: rgba(239, 68, 68, 0.12);
  color: var(--ds-danger-ink);
}

.receipt-hero-amount {
  margin: 0;
  font-size: 34px;
  font-weight: 900;
  letter-spacing: -0.04em;
}

.receipt-hero-category {
  font-size: 15px;
  font-weight: 700;
  color: var(--ds-ink);
}

.receipt-meta-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px 12px;
  border-top: 1px solid rgba(0, 0, 0, 0.06);
  padding-top: 16px;
}

.receipt-cell {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.receipt-cell__lbl {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.08em;
  color: var(--ds-muted);
  text-transform: uppercase;
}

.receipt-cell__val {
  font-size: 13px;
  font-weight: 600;
  color: var(--ds-ink);
}

.receipt-note-box {
  background: var(--ds-surface);
  padding: 12px 16px;
  border-radius: 16px;
  border: 1px solid rgba(0, 0, 0, 0.06);
}

.receipt-note-box p {
  margin: 4px 0 0;
  font-size: 13px;
  color: var(--ds-ink);
}

.receipt-action-buttons {
  display: flex;
  gap: 12px;
}

.btn-action-pill {
  flex: 1;
  height: 48px;
  border-radius: 16px;
  border: none;
  font-size: 13px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  cursor: pointer;
}

.btn-action-pill--edit {
  background: rgba(245, 158, 11, 0.12);
  color: var(--ds-amber-ink);
}

.btn-action-pill--delete {
  background: rgba(239, 68, 68, 0.12);
  color: var(--ds-danger-ink);
}

/* İşlem Geçmişi */
.history-controls-box {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding-bottom: 14px;
  border-bottom: 1px solid rgba(0, 0, 0, 0.06);
}

.history-date-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
}

.history-stream {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.history-card {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  border-radius: 18px;
  background: var(--ds-surface);
  border: 1px solid rgba(0, 0, 0, 0.06);
  cursor: pointer;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
}

.history-card:active {
  background: var(--ds-surface-subtle);
}

.history-card__date {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 6px 8px;
  background: var(--ds-surface-subtle);
  border-radius: 12px;
  font-size: 10px;
  font-weight: 700;
  line-height: 1.1;
  flex-shrink: 0;
}

.day-str { color: var(--ds-ink); }
.time-str { color: var(--ds-muted); }

.history-card__body {
  flex: 1;
  min-width: 0;
}

.history-card__body h4 {
  margin: 0;
  font-size: 13px;
  font-weight: 700;
  color: var(--ds-ink);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.history-card__body small {
  font-size: 11px;
  color: var(--ds-muted);
}

.history-card__right {
  text-align: right;
  flex-shrink: 0;
}

.history-card__right strong {
  display: block;
  font-size: 13px;
  font-weight: 800;
}

.history-card__right small {
  font-size: 10px;
  color: var(--ds-muted);
}

/* Silme Onay Dialog */
.dialog-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(17, 22, 27, 0.5);
  backdrop-filter: blur(4px);
  z-index: 1100;
  display: grid;
  place-items: center;
  padding: 20px;
}

.glass-dialog {
  background: var(--ds-surface);
  border-radius: 28px;
  padding: 24px;
  max-width: 360px;
  width: 100%;
  text-align: center;
  box-shadow: 0 16px 40px rgba(0, 0, 0, 0.2);
}

.dialog-warning-icon {
  width: 54px;
  height: 54px;
  border-radius: 50%;
  background: rgba(239, 68, 68, 0.12);
  color: var(--ds-danger-ink);
  display: grid;
  place-items: center;
  margin: 0 auto 12px;
}

.glass-dialog h3 {
  margin: 0 0 6px;
  font-size: 18px;
  font-weight: 700;
  color: var(--ds-ink);
}

.glass-dialog p {
  margin: 0 0 18px;
  font-size: 13px;
  color: var(--ds-muted);
  line-height: 1.4;
}

.dialog-buttons {
  display: flex;
  gap: 10px;
}

.btn-dialog-danger {
  flex: 1;
  height: 46px;
  border-radius: 16px;
  border: none;
  background: var(--ds-danger-ink);
  color: var(--ds-surface);
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}

.btn-dialog-cancel {
  flex: 1;
  height: 46px;
  border-radius: 16px;
  border: 1px solid rgba(0, 0, 0, 0.08);
  background: var(--ds-surface-subtle);
  color: var(--ds-muted);
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
}

/* Animasyonlar */
.sheet-anim-enter-active,
.sheet-anim-leave-active {
  transition: opacity 0.25s ease;
}

.sheet-anim-enter-active .sheet-container,
.sheet-anim-leave-active .sheet-container {
  transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}

.sheet-anim-enter-from,
.sheet-anim-leave-to {
  opacity: 0;
}

.sheet-anim-enter-from .sheet-container,
.sheet-anim-leave-to .sheet-container {
  transform: translateY(100%);
}

.fade-anim-enter-active,
.fade-anim-leave-active {
  transition: opacity 0.2s ease;
}

.fade-anim-enter-from,
.fade-anim-leave-to {
  opacity: 0;
}

/* Yardımcı Renkler */
.text-emerald { color: var(--ds-emerald-ink) !important; }
.text-rose { color: var(--ds-danger-ink) !important; }
.text-xs { font-size: 11px !important; }
.py-6 { padding-top: 24px; padding-bottom: 24px; }
</style>
