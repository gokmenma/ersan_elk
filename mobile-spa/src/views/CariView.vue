<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import {
  ArrowDownLeft,
  ArrowLeft,
  ArrowUpRight,
  Building2,
  Calendar,
  Check,
  ChevronRight,
  Clock,
  Contact,
  CreditCard,
  FileDown,
  FileText,
  Filter,
  History,
  MapPin,
  Paperclip,
  Phone,
  Plus,
  Receipt,
  RefreshCw,
  Search,
  Trash2,
  TrendingDown,
  TrendingUp,
  User,
  UsersRound,
  Wallet,
  X,
} from '@lucide/vue'
import { cariApi } from '../services/cari.js'
import { auth } from '../services/auth.js'

const router = useRouter()

// Veri Durumları
const items = ref([])
const summary = ref({
  total_cariler: 0,
  toplam_borc: 0,
  toplam_alacak: 0,
  genel_bakiye: 0,
  borclu_sayisi: 0,
  alacakli_sayisi: 0,
})
const pagination = ref({ page: 1, limit: 25, total: 0, has_more: false })
const loading = ref(true)
const loadingMore = ref(false)
const error = ref('')
const search = ref('')
const activeFilter = ref('all') // all, alacakli, borclu
let searchTimer = null

// Hızlı İşlem Modal Durumu
const isQuickActionOpen = ref(false)
const quickActionCari = ref(null)
const quickActionForm = ref({
  type: 'aldim',
  tutar: '',
  islem_tarihi: '',
  belge_no: '',
  aciklama: '',
  file: null,
})
const quickActionLoading = ref(false)
const quickActionError = ref('')

// Cari Ekleme / Düzenleme Modal Durumu
const isCariModalOpen = ref(false)
const editingCariId = ref('')
const cariForm = ref({
  CariAdi: '',
  firma: '',
  Telefon: '',
  Email: '',
  il: '',
  ilce: '',
  Adres: '',
})
const cariModalLoading = ref(false)
const cariModalError = ref('')

// İşlem Geçmişi (Tüm Hareketler) Modal Durumu
const isHistoryModalOpen = ref(false)
const historyItems = ref([])
const historyLoading = ref(false)
const historyPdfUrl = ref('')
const historyFilter = ref({
  search: '',
  type: 'all',
  baslangic: '',
  bitis: '',
})

// Hareket Detay Modal Durumu
const isMovementDetailOpen = ref(false)
const selectedMovement = ref(null)
const movementDeleteLoading = ref(false)

// Yardımcı Formatlayıcılar
function formatMoney(amount) {
  if (amount === undefined || amount === null) return '0,00 ₺'
  const val = Number(amount) || 0
  return new Intl.NumberFormat('tr-TR', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(Math.abs(val)) + ' ₺'
}

function getTodayDateTimeInput() {
  const now = new Date()
  const offset = now.getTimezoneOffset() * 60000
  return new Date(now.getTime() - offset).toISOString().slice(0, 16)
}

// Liste Yükleme
async function loadCariler(reset = true) {
  if (reset) {
    loading.value = true
    pagination.value.page = 1
  } else {
    loadingMore.value = true
  }
  error.value = ''

  try {
    const res = await cariApi.list({
      search: search.value,
      filter: activeFilter.value,
      page: reset ? 1 : pagination.value.page + 1,
      limit: 25,
    })

    if (reset) {
      items.value = res.items || []
    } else {
      items.value = [...items.value, ...(res.items || [])]
    }

    summary.value = res.summary || summary.value
    pagination.value = res.pagination || pagination.value
  } catch (err) {
    error.value = err.message || 'Cari listesi yüklenemedi.'
  } finally {
    loading.value = false
    loadingMore.value = false
  }
}

watch(activeFilter, () => loadCariler(true))
watch(search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => loadCariler(true), 300)
})

onMounted(() => {
  loadCariler(true)
})

// Detay Sayfasına Geçiş
function goToDetail(cari) {
  router.push(`/cari-takip/${encodeURIComponent(cari.id)}`)
}

// --------------------------------------------------------------------------
// HIZLI İŞLEM MODAL İŞLEMLERİ
// --------------------------------------------------------------------------
function openQuickAction(cari, e) {
  if (e) e.stopPropagation()
  quickActionCari.value = cari
  quickActionForm.value = {
    type: 'aldim',
    tutar: '',
    islem_tarihi: getTodayDateTimeInput(),
    belge_no: '',
    aciklama: '',
    file: null,
  }
  quickActionError.value = ''
  isQuickActionOpen.value = true
}

function handleQuickActionFileChange(e) {
  const files = e.target.files
  if (files && files.length > 0) {
    quickActionForm.value.file = files[0]
  }
}

async function submitQuickAction() {
  if (!quickActionForm.value.tutar || Number(quickActionForm.value.tutar) <= 0) {
    quickActionError.value = 'Lütfen geçerli bir tutar girin.'
    return
  }
  quickActionLoading.value = true
  quickActionError.value = ''

  try {
    const formData = new FormData()
    formData.append('cari_id', quickActionCari.value.id)
    formData.append('type', quickActionForm.value.type)
    formData.append('tutar', quickActionForm.value.tutar)
    formData.append('islem_tarihi', quickActionForm.value.islem_tarihi)
    formData.append('belge_no', quickActionForm.value.belge_no)
    formData.append('aciklama', quickActionForm.value.aciklama)
    if (quickActionForm.value.file) {
      formData.append('dosya', quickActionForm.value.file)
    }

    await cariApi.saveHareket(formData, auth.state.csrfToken)
    isQuickActionOpen.value = false
    await loadCariler(true)
  } catch (err) {
    quickActionError.value = err.message || 'İşlem kaydedilemedi.'
  } finally {
    quickActionLoading.value = false
  }
}

// --------------------------------------------------------------------------
// CARİ OLUŞTURMA / DÜZENLEME MODALI
// --------------------------------------------------------------------------
function openNewCariModal() {
  editingCariId.value = ''
  cariForm.value = {
    CariAdi: '',
    firma: '',
    Telefon: '',
    Email: '',
    il: '',
    ilce: '',
    Adres: '',
  }
  cariModalError.value = ''
  isCariModalOpen.value = true
}

async function selectContactFromPhone() {
  if ('contacts' in navigator && 'ContactsManager' in window) {
    try {
      const props = ['name', 'tel', 'email']
      const contacts = await navigator.contacts.select(props, { multiple: false })
      if (contacts && contacts.length > 0) {
        const c = contacts[0]
        if (c.name && c.name[0]) cariForm.value.CariAdi = c.name[0]
        if (c.tel && c.tel[0]) cariForm.value.Telefon = c.tel[0]
        if (c.email && c.email[0]) cariForm.value.Email = c.email[0]
      }
    } catch {
      // Kullanıcı iptal etti veya izin vermedi
    }
  } else {
    alert('Rehber erişimi bu tarayıcıda veya cihazda desteklenmiyor.')
  }
}

async function submitCariModal() {
  if (!cariForm.value.CariAdi.trim()) {
    cariModalError.value = 'Cari adı zorunludur.'
    return
  }
  cariModalLoading.value = true
  cariModalError.value = ''

  try {
    await cariApi.saveCari({
      id: editingCariId.value,
      ...cariForm.value,
    }, auth.state.csrfToken)

    isCariModalOpen.value = false
    await loadCariler(true)
  } catch (err) {
    cariModalError.value = err.message || 'Cari kaydedilemedi.'
  } finally {
    cariModalLoading.value = false
  }
}

// --------------------------------------------------------------------------
// TÜM HAREKETLER (İŞLEM GEÇMİŞİ) BOTTOM SHEET
// --------------------------------------------------------------------------
async function openHistoryModal() {
  isHistoryModalOpen.value = true
  await loadHistory()
}

async function loadHistory() {
  historyLoading.value = true
  try {
    const res = await cariApi.tumHareketler({
      search: historyFilter.value.search,
      type: historyFilter.value.type,
      baslangic: historyFilter.value.baslangic,
      bitis: historyFilter.value.bitis,
    })
    historyItems.value = res.items || []
    historyPdfUrl.value = res.export_pdf_url || ''
  } catch {
    historyItems.value = []
  } finally {
    historyLoading.value = false
  }
}

function openMovementDetail(m) {
  selectedMovement.value = m
  isMovementDetailOpen.value = true
}

async function deleteSelectedMovement() {
  if (!selectedMovement.value) return
  if (!confirm('Bu hareketi silmek istediğinize emin misiniz?')) return

  movementDeleteLoading.value = true
  try {
    await cariApi.deleteHareket(selectedMovement.value.id, auth.state.csrfToken)
    isMovementDetailOpen.value = false
    selectedMovement.value = null
    await loadHistory()
    await loadCariler(true)
  } catch (err) {
    alert(err.message || 'Hareket silinemedi.')
  } finally {
    movementDeleteLoading.value = false
  }
}
</script>

<template>
  <section class="ds-screen ds-cari-screen">
    <!-- Üst Başlık & Aksiyon Çubuğu -->
    <header class="cari-topbar">
      <button type="button" class="topbar-btn" aria-label="Geri dön" @click="router.back()">
        <ArrowLeft :size="22" />
      </button>
      <div class="topbar-title">
        <span>FİNANS & HESAPLAR</span>
        <h1>Cari Takip</h1>
      </div>
      <div class="topbar-actions">
        <button
          type="button"
          class="topbar-btn"
          aria-label="Yenile"
          :class="{ spinning: loading }"
          @click="loadCariler(true)"
        >
          <RefreshCw :size="19" />
        </button>
      </div>
    </header>

    <!-- Özet KPI Kartları (3 Kart) -->
    <section class="cari-summary-section">
      <div class="summary-cards-grid">
        <!-- Toplam Aldım (Borç) -->
        <article class="summary-kpi-card summary-kpi-card--debt">
          <div class="kpi-header">
            <span class="kpi-badge">TOP. ALDIM</span>
            <div class="kpi-icon"><TrendingDown :size="16" /></div>
          </div>
          <strong class="kpi-value text-debt">{{ formatMoney(summary.toplam_borc) }}</strong>
          <small class="kpi-sub">Tahsilat / Alınan</small>
        </article>

        <!-- Toplam Verdim (Alacak) -->
        <article class="summary-kpi-card summary-kpi-card--credit">
          <div class="kpi-header">
            <span class="kpi-badge">TOP. VERDİM</span>
            <div class="kpi-icon"><TrendingUp :size="16" /></div>
          </div>
          <strong class="kpi-value text-credit">{{ formatMoney(summary.toplam_alacak) }}</strong>
          <small class="kpi-sub">Ödeme / Verilen</small>
        </article>

        <!-- Genel Net Bakiye -->
        <article class="summary-kpi-card summary-kpi-card--net">
          <div class="kpi-header">
            <span class="kpi-badge">
              {{ summary.genel_bakiye < 0 ? 'BORCUM' : summary.genel_bakiye > 0 ? 'ALACAĞIM' : 'BAKİYE' }}
            </span>
            <div class="kpi-icon"><Wallet :size="16" /></div>
          </div>
          <strong
            class="kpi-value"
            :class="summary.genel_bakiye < 0 ? 'text-debt' : summary.genel_bakiye > 0 ? 'text-credit' : 'text-neutral'"
          >
            {{ summary.genel_bakiye < 0 ? '-' : summary.genel_bakiye > 0 ? '+' : '' }}{{ formatMoney(summary.genel_bakiye) }}
          </strong>
          <small class="kpi-sub">
            {{ summary.genel_bakiye < 0 ? 'Ödenmesi gereken' : summary.genel_bakiye > 0 ? 'Bekleyen alacak' : 'Dengede' }}
          </small>
        </article>
      </div>
    </section>

    <!-- Arama & Filtre Sekmeleri -->
    <section class="cari-filter-section">
      <div class="cari-search-box">
        <Search :size="18" class="search-icon" />
        <input
          v-model="search"
          type="search"
          inputmode="search"
          placeholder="Cari adı, firma, telefon veya şehir ara…"
          aria-label="Cari ara"
        />
        <button v-if="search" type="button" class="search-clear-btn" aria-label="Temizle" @click="search = ''">
          <X :size="16" />
        </button>
      </div>

      <div class="cari-filter-tabs">
        <button
          type="button"
          class="tab-btn"
          :class="{ active: activeFilter === 'all' }"
          @click="activeFilter = 'all'"
        >
          <span>Tümü</span>
          <b class="tab-badge">{{ summary.total_cariler }}</b>
        </button>
        <button
          type="button"
          class="tab-btn"
          :class="{ active: activeFilter === 'alacakli' }"
          @click="activeFilter = 'alacakli'"
        >
          <span class="dot-indicator dot-credit"></span>
          <span>Alacaklılar</span>
          <b class="tab-badge">{{ summary.alacakli_sayisi }}</b>
        </button>
        <button
          type="button"
          class="tab-btn"
          :class="{ active: activeFilter === 'borclu' }"
          @click="activeFilter = 'borclu'"
        >
          <span class="dot-indicator dot-debt"></span>
          <span>Borçlular</span>
          <b class="tab-badge">{{ summary.borclu_sayisi }}</b>
        </button>
      </div>
    </section>

    <!-- Liste İçeriği -->
    <main class="cari-list-main">
      <!-- Yükleniyor Durumu -->
      <div v-if="loading" class="ds-loading">
        <i v-for="n in 4" :key="n" style="height: 82px;"></i>
      </div>

      <!-- Hata Durumu -->
      <div v-else-if="error" class="ds-state error-state">
        <RefreshCw :size="24" />
        <strong>Kayıtlar Yüklenemedi</strong>
        <p>{{ error }}</p>
        <button type="button" @click="loadCariler(true)">Tekrar Dene</button>
      </div>

      <!-- Boş Durum -->
      <div v-else-if="!items.length" class="ds-state">
        <UsersRound :size="32" />
        <strong>Cari Kaydı Bulunamadı</strong>
        <p v-if="search || activeFilter !== 'all'">Arama veya filtre kriterlerinizi değiştirin.</p>
        <p v-else>Henüz kayıtlı bir cari bulunmuyor. Yeni bir cari ekleyerek başlayın.</p>
        <button type="button" @click="openNewCariModal">+ Cari Ekle</button>
      </div>

      <!-- Cari Kartları Listesi -->
      <div v-else class="cari-cards-container">
        <article
          v-for="cari in items"
          :key="cari.id"
          class="cari-card"
          role="button"
          tabindex="0"
          @click="goToDetail(cari)"
          @keydown.enter="goToDetail(cari)"
        >
          <div class="cari-card__avatar">
            {{ cari.initials }}
          </div>

          <div class="cari-card__info">
            <h3 class="cari-card__name">{{ cari.name }}</h3>
            <p v-if="cari.company" class="cari-card__company">{{ cari.company }}</p>
            <div class="cari-card__meta">
              <span v-if="cari.phone" class="cari-meta-item">
                <Phone :size="12" />
                <a :href="`tel:${cari.phone}`" @click.stop>{{ cari.phone }}</a>
              </span>
              <span v-if="cari.city || cari.district" class="cari-meta-item">
                <MapPin :size="12" />
                <span>{{ [cari.city, cari.district].filter(Boolean).join(' / ') }}</span>
              </span>
            </div>
          </div>

          <div class="cari-card__balance-block">
            <span
              class="balance-amount"
              :class="cari.bakiye < 0 ? 'text-debt' : cari.bakiye > 0 ? 'text-credit' : 'text-neutral'"
            >
              {{ cari.bakiye < 0 ? '-' : cari.bakiye > 0 ? '+' : '' }}{{ formatMoney(cari.bakiye) }}
            </span>
            <span
              class="balance-badge"
              :class="cari.bakiye < 0 ? 'badge-debt' : cari.bakiye > 0 ? 'badge-credit' : 'badge-neutral'"
            >
              {{ cari.bakiye_label }}
            </span>

            <div class="cari-card__actions">
              <button
                type="button"
                class="quick-action-btn"
                title="Hızlı İşlem Ekle"
                aria-label="Hızlı İşlem Ekle"
                @click="openQuickAction(cari, $event)"
              >
                <Plus :size="15" />
              </button>
              <span class="chevron-icon">
                <ChevronRight :size="18" />
              </span>
            </div>
          </div>
        </article>

        <!-- Daha Fazla Yükle -->
        <button
          v-if="pagination.has_more"
          type="button"
          class="load-more-btn"
          :disabled="loadingMore"
          @click="loadCariler(false)"
        >
          {{ loadingMore ? 'Yükleniyor…' : 'Daha fazla göster' }}
        </button>
      </div>
    </main>

    <!-- Sabit Alt Aksiyon Butonları (Yalnızca İkon Olarak Sağ Altta) -->
    <aside class="cari-floating-actions">
      <button
        type="button"
        class="floating-icon-btn floating-icon-btn--history"
        aria-label="İşlem Geçmişi"
        title="İşlem Geçmişi"
        @click="openHistoryModal"
      >
        <History :size="20" />
      </button>

      <button
        type="button"
        class="floating-icon-btn floating-icon-btn--create"
        aria-label="Yeni Cari Ekle"
        title="Yeni Cari Ekle"
        @click="openNewCariModal"
      >
        <Plus :size="25" />
      </button>
    </aside>

    <!-- ---------------------------------------------------------------- -->
    <!-- MODAL 1: HIZLI İŞLEM EKLE (TAHSİLAT / ÖDEME) -->
    <!-- ---------------------------------------------------------------- -->
    <Transition name="ds-modal">
      <div v-if="isQuickActionOpen" class="ds-modal-layer" @click.self="isQuickActionOpen = false">
        <div class="ds-bottom-sheet" role="dialog" aria-modal="true" aria-label="Hızlı İşlem Ekle">
          <div class="sheet-header">
            <div class="sheet-title">
              <div class="sheet-icon-box"><CreditCard :size="18" /></div>
              <div>
                <h3>Hızlı İşlem Ekle</h3>
                <small v-if="quickActionCari">{{ quickActionCari.name }}</small>
              </div>
            </div>
            <button type="button" class="sheet-close-btn" @click="isQuickActionOpen = false">
              <X :size="18" />
            </button>
          </div>

          <form class="sheet-form" @submit.prevent="submitQuickAction">
            <!-- Tip Seçimi Toggle (Aldım / Verdim) -->
            <div class="type-toggle-group">
              <label class="toggle-option toggle-option--debt" :class="{ selected: quickActionForm.type === 'aldim' }">
                <input v-model="quickActionForm.type" type="radio" value="aldim" />
                <ArrowDownLeft :size="17" />
                <span>Aldım (Borç)</span>
              </label>
              <label class="toggle-option toggle-option--credit" :class="{ selected: quickActionForm.type === 'verdim' }">
                <input v-model="quickActionForm.type" type="radio" value="verdim" />
                <ArrowUpRight :size="17" />
                <span>Verdim (Alacak)</span>
              </label>
            </div>

            <!-- Tutar & Tarih -->
            <div class="form-grid-two">
              <div class="form-group">
                <label>İşlem Tarihi*</label>
                <div class="input-wrap">
                  <Calendar :size="16" class="input-icon" />
                  <input v-model="quickActionForm.islem_tarihi" type="datetime-local" required />
                </div>
              </div>

              <div class="form-group">
                <label>Tutar (₺)*</label>
                <div class="input-wrap">
                  <span class="input-prefix">₺</span>
                  <input
                    v-model="quickActionForm.tutar"
                    type="number"
                    step="0.01"
                    min="0.01"
                    placeholder="0.00"
                    required
                    class="input-amount"
                  />
                </div>
              </div>
            </div>

            <!-- Belge No -->
            <div class="form-group">
              <label>Belge No</label>
              <div class="input-wrap">
                <Receipt :size="16" class="input-icon" />
                <input v-model="quickActionForm.belge_no" type="text" placeholder="Fatura / Makbuz No" />
              </div>
            </div>

            <!-- Açıklama -->
            <div class="form-group">
              <label>Açıklama</label>
              <div class="input-wrap">
                <FileText :size="16" class="input-icon" />
                <textarea v-model="quickActionForm.aciklama" rows="2" placeholder="İşlem açıklaması…"></textarea>
              </div>
            </div>

            <!-- Belge Yükleme -->
            <div class="form-group">
              <label>Belge / Dekont Yükle</label>
              <div class="input-wrap file-wrap">
                <Paperclip :size="16" class="input-icon" />
                <input type="file" accept="image/*,application/pdf" @change="handleQuickActionFileChange" />
              </div>
            </div>

            <p v-if="quickActionError" class="form-error">{{ quickActionError }}</p>

            <button type="submit" class="submit-action-btn" :disabled="quickActionLoading">
              <Check v-if="!quickActionLoading" :size="18" />
              <span>{{ quickActionLoading ? 'Kaydediliyor…' : 'İşlemi Kaydet' }}</span>
            </button>
          </form>
        </div>
      </div>
    </Transition>

    <!-- ---------------------------------------------------------------- -->
    <!-- MODAL 2: YENİ CARİ EKLEME / DÜZENLEME -->
    <!-- ---------------------------------------------------------------- -->
    <Transition name="ds-modal">
      <div v-if="isCariModalOpen" class="ds-modal-layer" @click.self="isCariModalOpen = false">
        <div class="ds-bottom-sheet" role="dialog" aria-modal="true" aria-label="Yeni Cari Ekle">
          <div class="sheet-header">
            <div class="sheet-title">
              <div class="sheet-icon-box"><User :size="18" /></div>
              <div>
                <h3>{{ editingCariId ? 'Cari Düzenle' : 'Yeni Cari Ekle' }}</h3>
                <small>Hesap ve iletişim bilgileri</small>
              </div>
            </div>
            <button type="button" class="sheet-close-btn" @click="isCariModalOpen = false">
              <X :size="18" />
            </button>
          </div>

          <form class="sheet-form" @submit.prevent="submitCariModal">
            <!-- Rehberden Seç Butonu -->
            <div class="contact-picker-row">
              <button type="button" class="contact-picker-btn" @click="selectContactFromPhone">
                <Contact :size="16" />
                <span>Telefondaki Rehberden Seç</span>
              </button>
            </div>

            <!-- Cari Adı -->
            <div class="form-group">
              <label>Cari / Kişi Adı*</label>
              <div class="input-wrap">
                <User :size="16" class="input-icon" />
                <input v-model="cariForm.CariAdi" type="text" placeholder="Ad Soyad" required />
              </div>
            </div>

            <!-- Firma Ünvanı -->
            <div class="form-group">
              <label>Firma / Şirket Ünvanı</label>
              <div class="input-wrap">
                <Building2 :size="16" class="input-icon" />
                <input v-model="cariForm.firma" type="text" placeholder="Şirket Adı" />
              </div>
            </div>

            <!-- Telefon & E-posta -->
            <div class="form-grid-two">
              <div class="form-group">
                <label>Telefon</label>
                <div class="input-wrap">
                  <Phone :size="16" class="input-icon" />
                  <input v-model="cariForm.Telefon" type="tel" placeholder="05XX XXX XX XX" />
                </div>
              </div>

              <div class="form-group">
                <label>E-Posta</label>
                <div class="input-wrap">
                  <input v-model="cariForm.Email" type="email" placeholder="ornek@mail.com" />
                </div>
              </div>
            </div>

            <!-- Şehir / İlçe -->
            <div class="form-grid-two">
              <div class="form-group">
                <label>İl</label>
                <div class="input-wrap">
                  <MapPin :size="16" class="input-icon" />
                  <input v-model="cariForm.il" type="text" placeholder="İl" />
                </div>
              </div>

              <div class="form-group">
                <label>İlçe</label>
                <div class="input-wrap">
                  <input v-model="cariForm.ilce" type="text" placeholder="İlçe" />
                </div>
              </div>
            </div>

            <!-- Adres -->
            <div class="form-group">
              <label>Açık Adres</label>
              <div class="input-wrap">
                <textarea v-model="cariForm.Adres" rows="2" placeholder="Adres detayı…"></textarea>
              </div>
            </div>

            <p v-if="cariModalError" class="form-error">{{ cariModalError }}</p>

            <button type="submit" class="submit-action-btn" :disabled="cariModalLoading">
              <Check v-if="!cariModalLoading" :size="18" />
              <span>{{ cariModalLoading ? 'Kaydediliyor…' : 'Cariyi Kaydet' }}</span>
            </button>
          </form>
        </div>
      </div>
    </Transition>

    <!-- ---------------------------------------------------------------- -->
    <!-- MODAL 3: TÜM HAREKETLER (İŞLEM GEÇMİŞİ) BOTTOM SHEET -->
    <!-- ---------------------------------------------------------------- -->
    <Transition name="ds-modal">
      <div v-if="isHistoryModalOpen" class="ds-modal-layer" @click.self="isHistoryModalOpen = false">
        <div class="ds-bottom-sheet ds-bottom-sheet--large" role="dialog" aria-modal="true" aria-label="İşlem Geçmişi">
          <div class="sheet-header">
            <div class="sheet-title">
              <div class="sheet-icon-box"><History :size="18" /></div>
              <div>
                <h3>Tüm İşlem Geçmişi</h3>
                <small>{{ historyItems.length }} hareket listeleniyor</small>
              </div>
            </div>
            <div class="header-action-group">
              <a
                v-if="historyPdfUrl"
                :href="historyPdfUrl"
                target="_blank"
                class="export-icon-btn"
                title="PDF Ekstre İndir"
              >
                <FileDown :size="18" />
              </a>
              <button type="button" class="sheet-close-btn" @click="isHistoryModalOpen = false">
                <X :size="18" />
              </button>
            </div>
          </div>

          <!-- Filtreleme Alanı -->
          <div class="history-filter-bar">
            <div class="search-mini-wrap">
              <Search :size="15" />
              <input
                v-model="historyFilter.search"
                type="text"
                placeholder="Açıklama, cari adı veya belge no ara…"
                @input="loadHistory"
              />
            </div>
            <div class="history-type-tabs">
              <button
                type="button"
                :class="{ active: historyFilter.type === 'all' }"
                @click="historyFilter.type = 'all'; loadHistory()"
              >
                Tümü
              </button>
              <button
                type="button"
                :class="{ active: historyFilter.type === 'aldim' }"
                @click="historyFilter.type = 'aldim'; loadHistory()"
              >
                Aldım
              </button>
              <button
                type="button"
                :class="{ active: historyFilter.type === 'verdim' }"
                @click="historyFilter.type = 'verdim'; loadHistory()"
              >
                Verdim
              </button>
            </div>
          </div>

          <!-- Hareket Listesi -->
          <div class="history-list-content">
            <div v-if="historyLoading" class="ds-loading">
              <i v-for="n in 3" :key="n" style="height: 64px;"></i>
            </div>

            <div v-else-if="!historyItems.length" class="history-empty">
              <History :size="28" />
              <span>Kayıtlı hareket bulunamadı.</span>
            </div>

            <div v-else class="history-cards">
              <article
                v-for="item in historyItems"
                :key="item.id"
                class="history-row-card"
                @click="openMovementDetail(item)"
              >
                <div class="history-date-badge">
                  <strong>{{ item.date_short }}</strong>
                  <small>{{ item.time_short }}</small>
                </div>

                <div class="history-row-body">
                  <div class="history-cari-name">{{ item.cari_name }}</div>
                  <div class="history-desc">{{ item.aciklama || 'Açıklama girilmemiş' }}</div>
                  <div v-if="item.belge_no" class="history-doc-badge">
                    <Receipt :size="11" />
                    <span>{{ item.belge_no }}</span>
                  </div>
                </div>

                <div class="history-amount-block">
                  <span
                    class="history-amount"
                    :class="item.type === 'aldim' ? 'text-debt' : 'text-credit'"
                  >
                    {{ item.type === 'aldim' ? '-' : '+' }}{{ formatMoney(item.amount) }}
                  </span>
                  <small class="history-type-label">{{ item.type_label }}</small>
                  <Paperclip v-if="item.has_file" :size="13" class="history-clip-icon" />
                </div>
              </article>
            </div>
          </div>
        </div>
      </div>
    </Transition>

    <!-- ---------------------------------------------------------------- -->
    <!-- MODAL 4: HAREKET DETAY POPUP -->
    <!-- ---------------------------------------------------------------- -->
    <Transition name="ds-modal">
      <div v-if="isMovementDetailOpen" class="ds-modal-layer" @click.self="isMovementDetailOpen = false">
        <div class="ds-bottom-sheet" role="dialog" aria-modal="true" aria-label="Hareket Detayı">
          <div class="sheet-header">
            <div class="sheet-title">
              <div class="sheet-icon-box"><Receipt :size="18" /></div>
              <div>
                <h3>İşlem Detayı</h3>
                <small v-if="selectedMovement">{{ selectedMovement.date_formatted }}</small>
              </div>
            </div>
            <button type="button" class="sheet-close-btn" @click="isMovementDetailOpen = false">
              <X :size="18" />
            </button>
          </div>

          <div v-if="selectedMovement" class="movement-detail-body">
            <div class="movement-amount-hero">
              <span class="hero-type-label">{{ selectedMovement.type_label }}</span>
              <strong
                class="hero-amount-text"
                :class="selectedMovement.type === 'aldim' ? 'text-debt' : 'text-credit'"
              >
                {{ selectedMovement.type === 'aldim' ? '-' : '+' }}{{ formatMoney(selectedMovement.amount) }}
              </strong>
            </div>

            <div class="detail-data-list">
              <div class="detail-data-row">
                <span>Cari / Hesap:</span>
                <strong>{{ selectedMovement.cari_name }}</strong>
              </div>
              <div v-if="selectedMovement.belge_no" class="detail-data-row">
                <span>Belge No:</span>
                <strong>{{ selectedMovement.belge_no }}</strong>
              </div>
              <div class="detail-data-row">
                <span>İşlem Tarihi:</span>
                <strong>{{ selectedMovement.date_formatted }}</strong>
              </div>
              <div v-if="selectedMovement.ekleyen_adi" class="detail-data-row">
                <span>Ekleyen:</span>
                <strong>{{ selectedMovement.ekleyen_adi }}</strong>
              </div>
              <div class="detail-data-row">
                <span>Açıklama:</span>
                <p>{{ selectedMovement.aciklama || '—' }}</p>
              </div>
              <div v-if="selectedMovement.has_file" class="detail-data-row">
                <span>Belge:</span>
                <a :href="selectedMovement.file_url" target="_blank" class="detail-file-btn">
                  <Paperclip :size="14" />
                  <span>Belgeyi Görüntüle</span>
                </a>
              </div>
            </div>

            <div class="detail-action-buttons">
              <button
                type="button"
                class="danger-delete-btn"
                :disabled="movementDeleteLoading"
                @click="deleteSelectedMovement"
              >
                <Trash2 :size="16" />
                <span>{{ movementDeleteLoading ? 'Siliniyor…' : 'Bu Hareketi Sil' }}</span>
              </button>
              <button
                type="button"
                class="neutral-close-btn"
                @click="isMovementDetailOpen = false"
              >
                Kapat
              </button>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </section>
</template>

<style scoped>
/* -------------------------------------------------------------------------
   GLACIER ALPINE OPTICS: CARI DESIGN SYSTEM
   ------------------------------------------------------------------------- */
.ds-cari-screen {
  font-family: 'Plus Jakarta Sans', sans-serif;
  padding-bottom: calc(140px + var(--ds-safe-bottom));
}

/* TOPBAR */
.cari-topbar {
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
  border: 1px solid rgba(255, 255, 255, 0.9);
  border-radius: 50%;
  color: var(--ds-ink);
  background: rgba(255, 255, 255, 0.8);
  box-shadow: var(--ds-shadow-card);
  backdrop-filter: blur(16px);
  cursor: pointer;
  transition: transform 0.15s ease;
}
.topbar-btn:active {
  transform: scale(0.93);
}
.topbar-btn--primary {
  background: var(--ds-primary);
  color: #fff;
  border-color: var(--ds-primary);
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

/* ÖZET KPI KARTLARI */
.cari-summary-section {
  margin-bottom: 18px;
}
.summary-cards-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 8px;
}
.summary-kpi-card {
  padding: 12px 10px;
  border: 1px solid rgba(255, 255, 255, 0.9);
  border-radius: 18px;
  background: rgba(255, 255, 255, 0.76);
  box-shadow: var(--ds-shadow-card);
  backdrop-filter: blur(16px);
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  min-height: 88px;
}
.kpi-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.kpi-badge {
  font: 700 8px 'Space Grotesk', sans-serif;
  color: var(--ds-muted);
  letter-spacing: 0.06em;
}
.kpi-icon {
  display: grid;
  width: 22px;
  height: 22px;
  place-items: center;
  border-radius: 7px;
  background: var(--ds-soft);
  color: var(--ds-slate);
}
.summary-kpi-card--debt .kpi-icon {
  background: #fee2e2;
  color: #dc2626;
}
.summary-kpi-card--credit .kpi-icon {
  background: #dcfce7;
  color: #16a34a;
}
.summary-kpi-card--net .kpi-icon {
  background: #e0e7ff;
  color: #4f46e5;
}
.kpi-value {
  font-size: 14px;
  font-weight: 700;
  line-height: 1.2;
  margin: 6px 0 2px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.kpi-sub {
  font-size: 9px;
  color: var(--ds-muted);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* RENKLER */
.text-debt { color: #dc2626 !important; }
.text-credit { color: #16a34a !important; }
.text-neutral { color: var(--ds-slate) !important; }

/* FİLTRE VE ARAMA */
.cari-filter-section {
  margin-bottom: 16px;
}
.cari-search-box {
  position: relative;
  display: flex;
  align-items: center;
  height: 48px;
  padding: 0 14px;
  border: 1px solid rgba(255, 255, 255, 0.9);
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.78);
  box-shadow: var(--ds-shadow-card);
  backdrop-filter: blur(16px);
  margin-bottom: 12px;
}
.search-icon {
  color: var(--ds-muted);
  margin-right: 10px;
  flex-shrink: 0;
}
.cari-search-box input {
  flex: 1;
  border: 0;
  background: transparent;
  color: var(--ds-ink);
  font: 500 12px 'Plus Jakarta Sans', sans-serif;
  outline: none;
}
.search-clear-btn {
  display: grid;
  place-items: center;
  width: 24px;
  height: 24px;
  border: 0;
  background: var(--ds-soft);
  border-radius: 50%;
  color: var(--ds-slate);
  cursor: pointer;
}
.cari-filter-tabs {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 8px;
}
.tab-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  height: 38px;
  padding: 0 8px;
  border: 1px solid rgba(255, 255, 255, 0.9);
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.7);
  box-shadow: var(--ds-shadow-card);
  color: var(--ds-slate);
  font: 600 10px 'Space Grotesk', sans-serif;
  cursor: pointer;
  transition: all 0.15s ease;
}
.tab-btn.active {
  background: var(--ds-primary);
  color: #fff;
  border-color: var(--ds-primary);
}
.tab-badge {
  padding: 2px 6px;
  border-radius: 999px;
  font-size: 9px;
  background: rgba(140, 155, 174, 0.18);
}
.tab-btn.active .tab-badge {
  background: rgba(255, 255, 255, 0.22);
  color: #fff;
}
.dot-indicator {
  width: 6px;
  height: 6px;
  border-radius: 50%;
}
.dot-credit { background: #16a34a; }
.dot-debt { background: #dc2626; }

/* CARİ KARTLARI LİSTESİ */
.cari-cards-container {
  display: grid;
  gap: 10px;
}
.cari-card {
  display: grid;
  grid-template-columns: 46px 1fr auto;
  align-items: center;
  gap: 12px;
  padding: 13px 14px;
  border: 1px solid rgba(255, 255, 255, 0.9);
  border-radius: 20px;
  background: rgba(255, 255, 255, 0.78);
  box-shadow: var(--ds-shadow-card);
  backdrop-filter: blur(16px);
  cursor: pointer;
  transition: transform 0.15s ease, background 0.15s ease;
}
.cari-card:active {
  transform: scale(0.985);
  background: rgba(255, 255, 255, 0.92);
}
.cari-card__avatar {
  display: grid;
  width: 46px;
  height: 46px;
  place-items: center;
  border-radius: 15px;
  color: #fff;
  background: var(--ds-primary);
  font: 700 15px 'Space Grotesk', sans-serif;
  box-shadow: 0 6px 16px rgba(17, 22, 27, 0.15);
}
.cari-card__info {
  min-width: 0;
}
.cari-card__name {
  margin: 0;
  font-size: 13px;
  font-weight: 700;
  color: var(--ds-ink);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  letter-spacing: -0.02em;
}
.cari-card__company {
  margin: 2px 0 0;
  font-size: 10px;
  color: var(--ds-muted);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.cari-card__meta {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-top: 4px;
}
.cari-meta-item {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 10px;
  color: var(--ds-slate);
}
.cari-meta-item a {
  color: inherit;
  text-decoration: none;
}
.cari-card__balance-block {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 3px;
}
.balance-amount {
  font-size: 12px;
  font-weight: 700;
  letter-spacing: -0.02em;
}
.balance-badge {
  display: inline-block;
  padding: 2px 6px;
  border-radius: 999px;
  font: 700 8px 'Space Grotesk', sans-serif;
  text-transform: uppercase;
}
.badge-debt {
  background: #fee2e2;
  color: #dc2626;
}
.badge-credit {
  background: #dcfce7;
  color: #16a34a;
}
.badge-neutral {
  background: var(--ds-soft);
  color: var(--ds-slate);
}
.cari-card__actions {
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
  border-radius: 8px;
  border: 1px solid rgba(22, 163, 74, 0.2);
  background: #ecfdf5;
  color: #059669;
  cursor: pointer;
}
.quick-action-btn:active {
  transform: scale(0.9);
}
.chevron-icon {
  color: var(--ds-muted);
}
.load-more-btn {
  width: 100%;
  height: 44px;
  margin-top: 10px;
  border: 1px solid rgba(255, 255, 255, 0.9);
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.8);
  box-shadow: var(--ds-shadow-card);
  color: var(--ds-ink);
  font: 600 11px 'Space Grotesk', sans-serif;
  cursor: pointer;
}

/* FLOATING BUTONLAR (YALNIZCA İKON OLARAK SAĞ ALTA HİZALI) */
.cari-floating-actions {
  position: fixed;
  right: 18px;
  bottom: calc(84px + var(--ds-safe-bottom));
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  z-index: 45;
}
.floating-icon-btn {
  display: grid;
  place-items: center;
  border: 1px solid rgba(255, 255, 255, 0.25);
  border-radius: 50%;
  cursor: pointer;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.floating-icon-btn:active {
  transform: scale(0.91);
}
.floating-icon-btn--history {
  width: 44px;
  height: 44px;
  background: #334155;
  color: #fff;
  box-shadow: 0 8px 20px rgba(51, 65, 85, 0.32);
}
.floating-icon-btn--create {
  width: 52px;
  height: 52px;
  background: var(--ds-primary);
  color: #fff;
  box-shadow: 0 10px 24px rgba(17, 22, 27, 0.36);
}

/* -------------------------------------------------------------------------
   MODAL VE BOTTOM SHEET STILLERI
   ------------------------------------------------------------------------- */
.ds-modal-layer {
  position: fixed;
  inset: 0;
  z-index: 100;
  background: rgba(17, 22, 27, 0.55);
  backdrop-filter: blur(6px);
  display: flex;
  align-items: flex-end;
  justify-content: center;
}
.ds-bottom-sheet {
  width: 100%;
  max-width: 540px;
  max-height: 88vh;
  background: #fff;
  border-radius: 28px 28px 0 0;
  box-shadow: var(--ds-shadow-float);
  overflow-y: auto;
  padding: 20px 20px calc(24px + var(--ds-safe-bottom));
  animation: slideUp 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}
.ds-bottom-sheet--large {
  max-height: 92vh;
}
@keyframes slideUp {
  from { transform: translateY(100%); }
  to { transform: translateY(0); }
}
.sheet-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 18px;
  padding-bottom: 12px;
  border-bottom: 1px solid var(--ds-line);
}
.sheet-title {
  display: flex;
  align-items: center;
  gap: 10px;
}
.sheet-icon-box {
  display: grid;
  width: 38px;
  height: 38px;
  place-items: center;
  border-radius: 12px;
  background: var(--ds-soft);
  color: var(--ds-primary);
}
.sheet-title h3 {
  margin: 0;
  font-size: 15px;
  font-weight: 700;
  color: var(--ds-ink);
}
.sheet-title small {
  display: block;
  font-size: 10px;
  color: var(--ds-muted);
}
.sheet-close-btn {
  display: grid;
  place-items: center;
  width: 34px;
  height: 34px;
  border-radius: 50%;
  border: 0;
  background: var(--ds-soft);
  color: var(--ds-slate);
  cursor: pointer;
}
.header-action-group {
  display: flex;
  align-items: center;
  gap: 8px;
}
.export-icon-btn {
  display: grid;
  place-items: center;
  width: 34px;
  height: 34px;
  border-radius: 50%;
  background: #ecfdf5;
  color: #059669;
  text-decoration: none;
}

/* FORM FORM ELEMANLARI */
.sheet-form {
  display: grid;
  gap: 14px;
}
.type-toggle-group {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
  padding: 4px;
  border-radius: 16px;
  background: var(--ds-soft);
}
.toggle-option {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  height: 42px;
  border-radius: 12px;
  font: 700 11px 'Space Grotesk', sans-serif;
  color: var(--ds-slate);
  cursor: pointer;
  transition: all 0.15s ease;
}
.toggle-option input {
  display: none;
}
.toggle-option--debt.selected {
  background: #fff;
  color: #dc2626;
  box-shadow: 0 4px 12px rgba(220, 38, 38, 0.12);
}
.toggle-option--credit.selected {
  background: #fff;
  color: #16a34a;
  box-shadow: 0 4px 12px rgba(22, 163, 74, 0.12);
}
.form-grid-two {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}
.form-group label {
  display: block;
  margin-bottom: 5px;
  font: 700 10px 'Space Grotesk', sans-serif;
  color: var(--ds-slate);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}
.input-wrap {
  position: relative;
  display: flex;
  align-items: center;
  border: 1px solid rgba(140, 155, 174, 0.25);
  border-radius: 14px;
  background: #fff;
  padding: 0 12px;
  height: 46px;
}
.input-icon {
  color: var(--ds-muted);
  margin-right: 8px;
  flex-shrink: 0;
}
.input-wrap input,
.input-wrap textarea {
  width: 100%;
  border: 0;
  outline: none;
  font: 500 12px 'Plus Jakarta Sans', sans-serif;
  color: var(--ds-ink);
  background: transparent;
}
.input-amount {
  font-weight: 700 !important;
  text-align: right;
  font-size: 14px !important;
}
.input-prefix {
  font-weight: 700;
  color: var(--ds-muted);
  margin-right: 6px;
}
.input-wrap textarea {
  padding: 10px 0;
  height: auto;
  resize: vertical;
}
.file-wrap input {
  font-size: 11px;
}
.contact-picker-row {
  margin-bottom: 4px;
}
.contact-picker-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border: 1px dashed rgba(17, 22, 27, 0.2);
  border-radius: 999px;
  background: var(--ds-soft);
  color: var(--ds-ink);
  font: 600 10px 'Space Grotesk', sans-serif;
  cursor: pointer;
}
.form-error {
  margin: 0;
  color: #dc2626;
  font-size: 11px;
  font-weight: 600;
}
.submit-action-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  height: 48px;
  border: 0;
  border-radius: 999px;
  background: var(--ds-primary);
  color: #fff;
  font: 700 12px 'Space Grotesk', sans-serif;
  cursor: pointer;
  box-shadow: 0 8px 20px rgba(17, 22, 27, 0.18);
  margin-top: 6px;
}
.submit-action-btn:disabled {
  opacity: 0.6;
}

/* İŞLEM GEÇMİŞİ LİSTE İÇERİĞİ */
.history-filter-bar {
  display: grid;
  gap: 8px;
  margin-bottom: 14px;
}
.search-mini-wrap {
  display: flex;
  align-items: center;
  gap: 8px;
  height: 38px;
  padding: 0 12px;
  border-radius: 999px;
  background: var(--ds-soft);
  color: var(--ds-muted);
}
.search-mini-wrap input {
  width: 100%;
  border: 0;
  background: transparent;
  outline: none;
  font: 500 11px 'Plus Jakarta Sans', sans-serif;
  color: var(--ds-ink);
}
.history-type-tabs {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 6px;
  padding: 3px;
  border-radius: 999px;
  background: var(--ds-soft);
}
.history-type-tabs button {
  height: 30px;
  border: 0;
  border-radius: 999px;
  background: transparent;
  color: var(--ds-slate);
  font: 600 10px 'Space Grotesk', sans-serif;
  cursor: pointer;
}
.history-type-tabs button.active {
  background: #fff;
  color: var(--ds-ink);
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.06);
}
.history-list-content {
  max-height: 55vh;
  overflow-y: auto;
}
.history-cards {
  display: grid;
  gap: 8px;
}
.history-row-card {
  display: grid;
  grid-template-columns: 50px 1fr auto;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border: 1px solid var(--ds-line);
  border-radius: 16px;
  background: #fff;
  cursor: pointer;
  transition: background 0.15s ease;
}
.history-row-card:active {
  background: var(--ds-soft);
}
.history-date-badge {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 6px 4px;
  border-radius: 10px;
  background: var(--ds-soft);
  text-align: center;
}
.history-date-badge strong {
  font: 700 9px 'Space Grotesk', sans-serif;
  color: var(--ds-ink);
}
.history-date-badge small {
  font-size: 8px;
  color: var(--ds-muted);
}
.history-row-body {
  min-width: 0;
}
.history-cari-name {
  font-size: 11px;
  font-weight: 700;
  color: var(--ds-ink);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.history-desc {
  font-size: 10px;
  color: var(--ds-slate);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  margin-top: 2px;
}
.history-doc-badge {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  margin-top: 2px;
  padding: 1px 6px;
  border-radius: 6px;
  background: var(--ds-soft);
  font-size: 9px;
  color: var(--ds-muted);
}
.history-amount-block {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 2px;
}
.history-amount {
  font-size: 12px;
  font-weight: 700;
}
.history-type-label {
  font: 700 8px 'Space Grotesk', sans-serif;
  color: var(--ds-muted);
  text-transform: uppercase;
}
.history-clip-icon {
  color: var(--ds-muted);
  margin-top: 2px;
}
.history-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 40px 20px;
  color: var(--ds-muted);
  font-size: 11px;
}

/* HAREKET DETAY POPUP */
.movement-detail-body {
  display: grid;
  gap: 16px;
}
.movement-amount-hero {
  text-align: center;
  padding: 18px;
  border-radius: 20px;
  background: var(--ds-soft);
}
.hero-type-label {
  display: block;
  font: 700 9px 'Space Grotesk', sans-serif;
  letter-spacing: 0.1em;
  color: var(--ds-muted);
  text-transform: uppercase;
  margin-bottom: 4px;
}
.hero-amount-text {
  font-size: 26px;
  font-weight: 800;
  letter-spacing: -0.03em;
}
.detail-data-list {
  display: grid;
  gap: 8px;
  padding: 14px;
  border: 1px solid var(--ds-line);
  border-radius: 18px;
}
.detail-data-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 11px;
}
.detail-data-row span {
  color: var(--ds-muted);
}
.detail-data-row strong,
.detail-data-row p {
  color: var(--ds-ink);
  font-weight: 600;
  margin: 0;
  text-align: right;
  max-width: 65%;
}
.detail-file-btn {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  color: #2563eb;
  font-weight: 700;
  text-decoration: none;
}
.detail-action-buttons {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}
.danger-delete-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  height: 44px;
  border: 1px solid #fee2e2;
  border-radius: 999px;
  background: #fef2f2;
  color: #dc2626;
  font: 700 11px 'Space Grotesk', sans-serif;
  cursor: pointer;
}
.neutral-close-btn {
  height: 44px;
  border: 1px solid var(--ds-line);
  border-radius: 999px;
  background: #fff;
  color: var(--ds-slate);
  font: 700 11px 'Space Grotesk', sans-serif;
  cursor: pointer;
}

/* GEÇİŞ ANİMASYONLARI */
.ds-modal-enter-active,
.ds-modal-leave-active {
  transition: opacity 0.2s ease;
}
.ds-modal-enter-from,
.ds-modal-leave-to {
  opacity: 0;
}
</style>
