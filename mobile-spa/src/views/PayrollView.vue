<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import {
  AlertCircle,
  AlertTriangle,
  ArrowDownLeft,
  ArrowUpRight,
  Banknote,
  Calendar,
  CalendarDays,
  Calculator,
  Check,
  CheckCircle2,
  ChevronDown,
  ChevronRight,
  CircleDollarSign,
  Clock,
  Coins,
  CreditCard,
  FileText,
  Info,
  Landmark,
  Lock,
  LockOpen,
  MinusCircle,
  MoreHorizontal,
  PlusCircle,
  Receipt,
  RefreshCw,
  RotateCcw,
  Search,
  SlidersHorizontal,
  TrendingDown,
  TrendingUp,
  User,
  Users,
  UtensilsCrossed,
  Wallet,
  X,
} from '@lucide/vue'
import TopBar from '../components/TopBar.vue'
import { bordroApi } from '../services/bordro.js'
import { auth } from '../services/auth.js'

const router = useRouter()

// Yükleme & Veri Durumları
const loading = ref(true)
const refreshing = ref(false)
const error = ref('')
const toast = ref({ show: false, message: '', type: 'success' })

// Dönem ve Liste Verileri
const donemler = ref([])
const selectedDonem = ref(null)
const selectedDonemId = ref('')
const isClosed = ref(false)
const summary = ref({
  personel: 0,
  alacak: 0,
  kesinti: 0,
  net: 0,
  banka: 0,
  sodexo: 0,
  elden: 0,
  diger: 0,
  hesaplanan: 0,
  hesaplanmayan: 0,
})
const items = ref([])

// Arama ve Filtre
const searchQuery = ref('')
const activeFilter = ref('all') // all, calculated, pending, bank, cash

// Modal ve Sheet Durumları
const isDetailOpen = ref(false)
const detailLoading = ref(false)
const detailData = ref(null)
const selectedPersonForDetail = ref(null)
const showBankTooltip = ref(false)

const isPaymentModalOpen = ref(false)
const paymentLoading = ref(false)
const paymentTarget = ref(null)
const paymentForm = ref({
  banka: '',
  sodexo: '',
  diger: '',
})

const isAdjustmentModalOpen = ref(false)
const adjustmentLoading = ref(false)
const adjustmentTarget = ref(null)
const adjustmentKind = ref('gelir') // 'gelir' | 'kesinti'
const adjustmentForm = ref({
  tur: 'prim',
  tutar: '',
  aciklama: '',
  tarih: '',
})

const isMoreActionsOpen = ref(false)

// Özel SPA Onay Modalı
const confirmModal = ref({
  open: false,
  title: '',
  message: '',
  warnings: [],
  confirmText: 'Onayla',
  danger: false,
  action: null,
})

// Toast Yardımcısı
let toastTimer = null
function showToast(message, type = 'success') {
  if (toastTimer) clearTimeout(toastTimer)
  toast.value = { show: true, message, type }
  toastTimer = setTimeout(() => {
    toast.value.show = false
  }, 3500)
}

// Para Formatlayıcı
function formatMoney(amount) {
  if (amount === undefined || amount === null || isNaN(Number(amount))) return '0,00 ₺'
  const val = Number(amount)
  const isNegative = val < 0
  const formatted = new Intl.NumberFormat('tr-TR', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(Math.abs(val))
  return `${isNegative ? '-' : ''}${formatted} ₺`
}

// Filtrelenmiş Personel Listesi
const filteredItems = computed(() => {
  let list = items.value
  const query = searchQuery.value.toLocaleLowerCase('tr-TR').trim()

  if (query) {
    list = list.filter((p) => {
      const text = `${p.name} ${p.department} ${p.job}`.toLocaleLowerCase('tr-TR')
      return text.includes(query)
    })
  }

  if (activeFilter.value === 'calculated') {
    list = list.filter((p) => p.is_calculated)
  } else if (activeFilter.value === 'pending') {
    list = list.filter((p) => !p.is_calculated)
  } else if (activeFilter.value === 'bank') {
    list = list.filter((p) => p.banka > 0)
  } else if (activeFilter.value === 'cash') {
    list = list.filter((p) => p.elden > 0 || p.sodexo > 0)
  }

  return list
})

// Verileri Yükle
async function loadBordro(donemToken = null, showLoader = true) {
  if (showLoader) loading.value = true
  else refreshing.value = true
  error.value = ''

  try {
    const params = {}
    if (donemToken) {
      params.donem = donemToken
    } else if (selectedDonemId.value) {
      params.donem = selectedDonemId.value
    }

    const res = await bordroApi.init(params)
    donemler.value = res.donemler || []
    selectedDonem.value = res.selected_donem || null
    selectedDonemId.value = res.selected_donem?.id || (res.donemler?.[0]?.id || '')
    isClosed.value = Boolean(res.is_closed)
    summary.value = res.summary || {
      personel: 0,
      alacak: 0,
      kesinti: 0,
      net: 0,
      banka: 0,
      sodexo: 0,
      elden: 0,
      diger: 0,
      hesaplanan: 0,
      hesaplanmayan: 0,
    }
    items.value = res.items || []
  } catch (err) {
    error.value = err.message || 'Bordro verileri yüklenirken bir hata oluştu.'
    showToast(error.value, 'error')
  } finally {
    loading.value = false
    refreshing.value = false
  }
}

// Dönem Değişimi
function handlePeriodChange(event) {
  const newDonemId = event.target.value
  selectedDonemId.value = newDonemId
  loadBordro(newDonemId)
}

// Onay Modalı Çağırma
function triggerConfirm({ title, message, warnings = [], confirmText = 'Devam Et', danger = false, onConfirm }) {
  confirmModal.value = {
    open: true,
    title,
    message,
    warnings,
    confirmText,
    danger,
    action: onConfirm,
  }
}

async function handleConfirmSubmit() {
  const action = confirmModal.value.action
  confirmModal.value.open = false
  if (action) {
    await action()
  }
}

// Maaşları Hesapla
function handleCalculatePayroll() {
  if (isClosed.value) {
    showToast('Kapalı dönemde maaş hesaplaması yapılamaz.', 'error')
    return
  }

  triggerConfirm({
    title: 'Maaşlar Hesaplansın mı?',
    message: `${selectedDonem.value?.name || 'Seçili'} dönemindeki tüm personellerin maaş ve hakediş hesaplamaları güncellenecektir.`,
    confirmText: 'Maaşları Hesapla',
    danger: false,
    onConfirm: async () => {
      try {
        const tokens = items.value.map((i) => i.token)
        const res = await bordroApi.calculate(selectedDonemId.value, tokens, auth.state.csrfToken)
        showToast(res.message || 'Maaş hesaplaması başarıyla tamamlandı.')
        await loadBordro(selectedDonemId.value, false)
      } catch (err) {
        showToast(err.message || 'Maaş hesaplama hatası oluştu.', 'error')
      }
    },
  })
}

// Dönemi Kapat / Aç
function handleTogglePeriod(forceClose = false) {
  const actionText = isClosed.value ? 'Dönemi Yeniden Aç' : 'Dönemi Kapat'
  triggerConfirm({
    title: `${actionText}?`,
    message: isClosed.value
      ? 'Dönem yeniden düzenleme ve hesaplamalara açılacaktır.'
      : 'Dönem kapatıldığında bordro kayıtları mühürlenir ve üzerinde değişiklik yapılamaz.',
    confirmText: actionText,
    danger: !isClosed.value,
    onConfirm: async () => {
      try {
        const res = await bordroApi.togglePeriod(selectedDonemId.value, isClosed.value, forceClose, auth.state.csrfToken)
        if (res.status === 'warning' && Array.isArray(res.warnings) && res.warnings.length > 0) {
          triggerConfirm({
            title: 'Tamamlanmamış İşlemler Var',
            message: 'Bu döneme ait tamamlanmamış kayıtlar tespit edildi:',
            warnings: res.warnings,
            confirmText: 'Yine de Kapat',
            danger: true,
            onConfirm: () => handleTogglePeriod(true),
          })
          return
        }
        showToast(res.message || 'Dönem durumu güncellendi.')
        await loadBordro(selectedDonemId.value, false)
      } catch (err) {
        showToast(err.message || 'Dönem işlemi başarısız oldu.', 'error')
      }
    },
  })
}

// Tüm Ödeme Dağılımlarını Sıfırla
function handleResetAllPayments() {
  if (isClosed.value) return
  triggerConfirm({
    title: 'Ödeme Dağılımları Sıfırlansın mı?',
    message: 'Tüm personellerin manuel ödeme dağılımları kaldırılacak ve kurallara göre varsayılan tutarlar yeniden hesaplanacaktır.',
    confirmText: 'Dağılımları Sıfırla',
    danger: true,
    onConfirm: async () => {
      try {
        const res = await bordroApi.resetAllPayments(selectedDonemId.value, auth.state.csrfToken)
        showToast(res.message || 'Ödeme dağılımları sıfırlandı.')
        await loadBordro(selectedDonemId.value, false)
      } catch (err) {
        showToast(err.message || 'Sıfırlama işlemi başarısız oldu.', 'error')
      }
    },
  })
}

// Bordro Detayını Aç
async function openDetail(person) {
  selectedPersonForDetail.value = person
  isDetailOpen.value = true
  detailLoading.value = true
  detailData.value = null
  showBankTooltip.value = false

  try {
    const res = await bordroApi.getDetail(person.token)
    if (res && res.html) {
      const host = document.createElement('div')
      host.innerHTML = res.html
      host.querySelector('.person-header-card')?.remove()
      host.querySelector('.summary-badge')?.remove()
      res.html = host.innerHTML
    }
    detailData.value = res
  } catch (err) {
    showToast(err.message || 'Bordro detayı alınamadı.', 'error')
  } finally {
    detailLoading.value = false
  }
}

// Ödeme Dağıtımı Modalı Aç
function openPaymentModal(person) {
  if (isClosed.value) return
  paymentTarget.value = person
  paymentForm.value = {
    banka: person.banka > 0 ? String(person.banka) : '',
    sodexo: person.sodexo > 0 ? String(person.sodexo) : '',
    diger: person.diger > 0 ? String(person.diger) : '',
  }
  isPaymentModalOpen.value = true
}

// Kalan Elden Hesaplama (Canlı gösterim)
const computedEldenPreview = computed(() => {
  if (!paymentTarget.value) return 0
  const net = Number(paymentTarget.value.net) || 0
  const banka = Number(String(paymentForm.value.banka).replace(',', '.')) || 0
  const sodexo = Number(String(paymentForm.value.sodexo).replace(',', '.')) || 0
  const diger = Number(String(paymentForm.value.diger).replace(',', '.')) || 0
  return Math.max(0, net - (banka + sodexo + diger))
})

async function submitPaymentDistribution() {
  if (!paymentTarget.value) return
  paymentLoading.value = true
  try {
    const res = await bordroApi.distribute(
      paymentTarget.value.token,
      {
        banka: paymentForm.value.banka,
        sodexo: paymentForm.value.sodexo,
        diger: paymentForm.value.diger,
      },
      auth.state.csrfToken
    )
    showToast(res.message || 'Ödeme dağılımı güncellendi.')
    isPaymentModalOpen.value = false
    await loadBordro(selectedDonemId.value, false)
  } catch (err) {
    showToast(err.message || 'Ödeme dağılımı kaydedilemedi.', 'error')
  } finally {
    paymentLoading.value = false
  }
}

// Gelir / Kesinti Modalı Aç
function openAdjustmentModal(person, kind) {
  if (isClosed.value) return
  adjustmentTarget.value = person
  adjustmentKind.value = kind
  adjustmentForm.value = {
    tur: kind === 'gelir' ? 'prim' : 'avans',
    tutar: '',
    aciklama: '',
    tarih: new Intl.DateTimeFormat('tr-TR').format(new Date()),
  }
  isAdjustmentModalOpen.value = true
}

async function submitAdjustment() {
  if (!adjustmentTarget.value) return
  if (!adjustmentForm.value.tutar || isNaN(Number(adjustmentForm.value.tutar.replace(',', '.')))) {
    showToast('Lütfen geçerli bir tutar girin.', 'error')
    return
  }
  if (!adjustmentForm.value.aciklama.trim()) {
    showToast('Lütfen bir açıklama girin.', 'error')
    return
  }

  adjustmentLoading.value = true
  try {
    const res = await bordroApi.addAdjustment(
      {
        kind: adjustmentKind.value,
        personelToken: adjustmentTarget.value.personel_token,
        donemToken: selectedDonemId.value,
        tur: adjustmentForm.value.tur,
        tutar: adjustmentForm.value.tutar,
        aciklama: adjustmentForm.value.aciklama,
        tarih: adjustmentForm.value.tarih,
      },
      auth.state.csrfToken
    )
    showToast(res.message || `${adjustmentKind.value === 'gelir' ? 'Gelir' : 'Kesinti'} başarıyla eklendi.`)
    isAdjustmentModalOpen.value = false
    await loadBordro(selectedDonemId.value, false)
  } catch (err) {
    showToast(err.message || 'İşlem gerçekleştirilemedi.', 'error')
  } finally {
    adjustmentLoading.value = false
  }
}

onMounted(() => {
  loadBordro()
})
</script>

<template>
  <section class="ds-screen ds-payroll" :aria-busy="loading">
    <!-- 1. Üst Bar -->
    <TopBar
      title="Bordro"
      eyebrow="MAAŞ & BORDRO YÖNETİMİ"
      :subtitle="selectedDonem?.name ? `${selectedDonem.name} dönemi hakediş ve dağılım özeti` : 'Dönemsel hakediş, kesinti ve ödeme dağılımı'"
      :back="true"
    >
      <template #actions>
        <button
          type="button"
          class="clay-circle-btn"
          title="Yenile"
          aria-label="Listeyi Yenile"
          :disabled="refreshing || loading"
          @click="loadBordro(selectedDonemId, false)"
        >
          <RefreshCw :size="18" :class="{ 'animate-spin': refreshing }" />
        </button>

        <button
          type="button"
          class="clay-circle-btn"
          title="Diğer İşlemler"
          aria-label="Diğer İşlemler Menüsü"
          @click="isMoreActionsOpen = true"
        >
          <MoreHorizontal :size="18" />
        </button>

        <button
          type="button"
          class="clay-circle-btn clay-circle-btn--primary"
          title="Maaşları Hesapla"
          aria-label="Tüm Maaşları Hesapla"
          :disabled="isClosed || loading || !items.length"
          @click="handleCalculatePayroll"
        >
          <Calculator :size="18" />
        </button>
      </template>
    </TopBar>

    <!-- Bildirim Balonu (Toast) -->
    <Transition name="fade">
      <div
        v-if="toast.show"
        class="payroll-toast"
        :class="toast.type === 'error' ? 'payroll-toast--error' : 'payroll-toast--success'"
        role="alert"
      >
        <component :is="toast.type === 'error' ? AlertCircle : CheckCircle2" :size="18" />
        <span>{{ toast.message }}</span>
      </div>
    </Transition>

    <!-- Yükleniyor İskeleti -->
    <div v-if="loading" class="clay-skeleton-grid" role="status" aria-label="Bordro verileri yükleniyor">
      <div class="clay-skeleton-card clay-skeleton-card--sm"></div>
      <div class="clay-skeleton-card clay-skeleton-card--md"></div>
      <div class="clay-skeleton-card clay-skeleton-card--lg"></div>
    </div>

    <!-- Hata Durumu -->
    <div v-else-if="error" class="clay-state-card" role="alert">
      <AlertTriangle :size="28" class="text-rose-500" />
      <strong>Bordro Verileri Alınamadı</strong>
      <p>{{ error }}</p>
      <button type="button" class="clay-retry-btn" @click="loadBordro(selectedDonemId)">
        <RefreshCw :size="16" /> Tekrar Dene
      </button>
    </div>

    <template v-else>
      <!-- 2. Dönem Seçimi Bento Kartı -->
      <section class="bento-card bento-card--white payroll-period-card">
        <div class="payroll-period-header">
          <div class="payroll-period-label-wrap">
            <span class="clay-page-eyebrow">BORDRO DÖNEMİ</span>
            <span
              class="clay-pill-tag"
              :class="isClosed ? 'clay-pill-tag--coral' : 'clay-pill-tag--emerald'"
            >
              <component :is="isClosed ? Lock : LockOpen" :size="12" />
              {{ isClosed ? 'KAPALI DÖNEM' : 'AÇIK DÖNEM' }}
            </span>
          </div>

          <div class="payroll-select-wrapper">
            <select
              id="bordroPeriodSelect"
              v-model="selectedDonemId"
              aria-label="Bordro dönemi seçin"
              class="payroll-select"
              @change="handlePeriodChange"
            >
              <option v-if="!donemler.length" value="">Dönem bulunamadı</option>
              <option
                v-for="d in donemler"
                :key="d.id"
                :value="d.id"
              >
                {{ d.name }} {{ d.closed ? '(Kapalı)' : '' }}
              </option>
            </select>
            <ChevronDown :size="16" class="payroll-select-chevron" />
          </div>
        </div>

        <div v-if="selectedDonem" class="payroll-period-dates">
          <span>
            <Calendar :size="13" />
            {{ selectedDonem.start_date || '-' }} — {{ selectedDonem.end_date || '-' }}
          </span>
          <span class="payroll-period-count">
            {{ summary.personel }} personel kayıtlı
          </span>
        </div>
      </section>

      <!-- 3. Özet Metrikleri (Bento KPI Grid) -->
      <section class="bento-card bento-card--dark payroll-kpi-hero">
        <div class="bento-overview-head">
          <span class="dark-pill-tag">
            <span class="bento-status-dot"></span> TOPLAM NET DAĞITIM
          </span>
          <Banknote :size="20" aria-hidden="true" />
        </div>
        <div class="payroll-hero-main">
          <strong class="payroll-hero-amount">{{ formatMoney(summary.net) }}</strong>
          <span class="payroll-hero-sublabel">ele geçen net toplam</span>
        </div>
        <div class="bento-overview-footer">
          <span>Toplam Hakediş: <b>{{ formatMoney(summary.alacak) }}</b></span>
          <span class="payroll-deduction-badge">Kesintiler: -{{ formatMoney(summary.kesinti) }}</span>
        </div>
      </section>

      <!-- 3'lü İkincil Bento KPI Izgarası -->
      <section class="payroll-sub-kpi-grid">
        <div class="bento-card bento-card--white kpi-mini-card">
          <div class="kpi-mini-icon kpi-mini-icon--blue">
            <Landmark :size="18" />
          </div>
          <div class="kpi-mini-body">
            <span class="kpi-mini-label">BANKA</span>
            <strong class="kpi-mini-value text-blue-600">{{ formatMoney(summary.banka) }}</strong>
          </div>
        </div>

        <div class="bento-card bento-card--white kpi-mini-card">
          <div class="kpi-mini-icon kpi-mini-icon--amber">
            <Wallet :size="18" />
          </div>
          <div class="kpi-mini-body">
            <span class="kpi-mini-label">ELDEN</span>
            <strong class="kpi-mini-value text-amber-600">{{ formatMoney(summary.elden) }}</strong>
          </div>
        </div>

        <div class="bento-card bento-card--white kpi-mini-card">
          <div class="kpi-mini-icon kpi-mini-icon--cyan">
            <UtensilsCrossed :size="18" />
          </div>
          <div class="kpi-mini-body">
            <span class="kpi-mini-label">SODEXO</span>
            <strong class="kpi-mini-value text-cyan-600">{{ formatMoney(summary.sodexo) }}</strong>
          </div>
        </div>
      </section>

      <!-- 4. Arama ve Filtreleme -->
      <section class="payroll-filter-section">
        <div class="payroll-search-box">
          <Search :size="18" class="payroll-search-icon" />
          <input
            v-model="searchQuery"
            type="search"
            placeholder="Personel, departman veya görev ara..."
            class="payroll-search-input"
            aria-label="Personel ara"
          />
          <button
            v-if="searchQuery"
            type="button"
            class="payroll-search-clear"
            aria-label="Aramayı temizle"
            @click="searchQuery = ''"
          >
            <X :size="16" />
          </button>
        </div>

        <!-- Hızlı Filtre Sekmeleri -->
        <div class="clay-tabs-wrap payroll-tabs" role="tablist">
          <button
            type="button"
            class="clay-filter-pill"
            :class="{ active: activeFilter === 'all' }"
            role="tab"
            @click="activeFilter = 'all'"
          >
            Tümü ({{ items.length }})
          </button>
          <button
            type="button"
            class="clay-filter-pill"
            :class="{ active: activeFilter === 'calculated' }"
            role="tab"
            @click="activeFilter = 'calculated'"
          >
            Hesaplanan ({{ summary.hesaplanan }})
          </button>
          <button
            type="button"
            class="clay-filter-pill"
            :class="{ active: activeFilter === 'pending' }"
            role="tab"
            @click="activeFilter = 'pending'"
          >
            Bekleyen ({{ summary.hesaplanmayan }})
          </button>
          <button
            type="button"
            class="clay-filter-pill"
            :class="{ active: activeFilter === 'bank' }"
            role="tab"
            @click="activeFilter = 'bank'"
          >
            Banka
          </button>
          <button
            type="button"
            class="clay-filter-pill"
            :class="{ active: activeFilter === 'cash' }"
            role="tab"
            @click="activeFilter = 'cash'"
          >
            Elden / Sodexo
          </button>
        </div>
      </section>

      <!-- 5. Personel Bordro Kartları Listesi -->
      <section class="payroll-cards-stack">
        <div v-if="!filteredItems.length" class="clay-state-card">
          <Users :size="24" class="text-slate-400" />
          <strong>Kayıt Bulunamadı</strong>
          <p v-if="searchQuery">"{{ searchQuery }}" aramasına uygun personel bulunamadı.</p>
          <p v-else>Bu dönem için kayıtlı bordro personeli bulunmuyor.</p>
        </div>

        <article
          v-for="person in filteredItems"
          :key="person.token"
          class="bento-card bento-card--white payroll-person-card"
        >
          <!-- Üst Bilgi Satırı -->
          <div class="payroll-card-header">
            <div class="payroll-avatar-box">
              <img
                v-if="person.avatar"
                :src="person.avatar"
                :alt="person.name"
                class="payroll-avatar-img"
                loading="lazy"
              />
              <div v-else class="payroll-avatar-initials">
                {{ person.initials }}
              </div>
            </div>

            <div class="payroll-person-meta">
              <div class="payroll-person-name-row">
                <h3 class="payroll-person-name">{{ person.name }}</h3>
                <span
                  class="payroll-status-pill"
                  :class="person.is_calculated ? 'payroll-status-pill--calc' : 'payroll-status-pill--pending'"
                >
                  {{ person.is_calculated ? 'Hesaplandı' : 'Bekliyor' }}
                </span>
              </div>
              <p class="payroll-person-sub">
                {{ person.department }} · {{ person.job }}
              </p>
            </div>

            <div class="payroll-net-col">
              <span class="payroll-net-label">NET TUTAR</span>
              <strong
                class="payroll-net-value"
                :class="person.net < 0 ? 'text-rose-600' : 'text-emerald-600'"
              >
                {{ formatMoney(person.net) }}
              </strong>
            </div>
          </div>

          <!-- Metrik Izgarası (2x3) -->
          <div class="payroll-breakdown-grid">
            <div class="breakdown-item">
              <span class="breakdown-label"><CalendarDays :size="12" /> Gün</span>
              <strong class="breakdown-value">{{ person.work_days }} gün</strong>
            </div>

            <div class="breakdown-item">
              <span class="breakdown-label"><TrendingUp :size="12" /> Alacak</span>
              <strong class="breakdown-value">{{ formatMoney(person.alacak) }}</strong>
            </div>

            <div class="breakdown-item breakdown-item--danger">
              <span class="breakdown-label"><TrendingDown :size="12" /> Kesinti</span>
              <strong class="breakdown-value text-rose-600">{{ formatMoney(person.kesinti + person.icra) }}</strong>
            </div>

            <div class="breakdown-item breakdown-item--blue">
              <span class="breakdown-label"><Landmark :size="12" /> Banka</span>
              <strong class="breakdown-value text-blue-600">{{ formatMoney(person.banka) }}</strong>
            </div>

            <div class="breakdown-item breakdown-item--cyan">
              <span class="breakdown-label"><UtensilsCrossed :size="12" /> Sodexo</span>
              <strong class="breakdown-value text-cyan-600">{{ formatMoney(person.sodexo) }}</strong>
            </div>

            <div class="breakdown-item breakdown-item--amber">
              <span class="breakdown-label"><Banknote :size="12" /> Elden</span>
              <strong class="breakdown-value text-amber-600">{{ formatMoney(person.elden) }}</strong>
            </div>
          </div>

          <!-- Aksiyon Butonları -->
          <footer class="payroll-card-actions">
            <button
              type="button"
              class="payroll-action-btn payroll-action-btn--primary"
              aria-label="Bordro Detayını Görüntüle"
              @click="openDetail(person)"
            >
              <FileText :size="16" />
              <span>Bordro Detayı</span>
            </button>

            <button
              type="button"
              class="payroll-action-btn"
              :disabled="isClosed"
              title="Ödeme Dağılımını Düzenle"
              aria-label="Ödeme Dağılımını Düzenle"
              @click="openPaymentModal(person)"
            >
              <Wallet :size="16" />
              <span>Ödeme Dağıt</span>
            </button>

            <div v-if="!isClosed" class="payroll-quick-adj-group">
              <button
                type="button"
                class="payroll-icon-action payroll-icon-action--income"
                title="Gelir Ekle (Prim, Mesai)"
                aria-label="Gelir Ekle"
                @click="openAdjustmentModal(person, 'gelir')"
              >
                <PlusCircle :size="16" />
              </button>
              <button
                type="button"
                class="payroll-icon-action payroll-icon-action--deduct"
                title="Kesinti Ekle (Avans, İcra)"
                aria-label="Kesinti Ekle"
                @click="openAdjustmentModal(person, 'kesinti')"
              >
                <MinusCircle :size="16" />
              </button>
            </div>
          </footer>
        </article>
      </section>
    </template>

    <!-- ==================== MODALLAR & BOTTOM SHEETS ==================== -->

    <!-- 1. BORDRO DETAY BOTTOM SHEET -->
    <Transition name="fade">
      <div
        v-if="isDetailOpen"
        class="payroll-modal-backdrop"
        role="dialog"
        aria-modal="true"
        aria-label="Bordro Detay Penceresi"
        @click.self="isDetailOpen = false"
      >
        <div class="payroll-modal-sheet">
          <div class="sheet-drag-handle"></div>

          <header class="payroll-sheet-header">
            <div>
              <span class="clay-page-eyebrow">BORDRO DÖKÜMÜ</span>
              <h2 class="payroll-sheet-title">
                {{ selectedPersonForDetail?.name || 'Personel Bordrosu' }}
              </h2>
              <p class="payroll-sheet-subtitle">
                {{ selectedPersonForDetail?.department }} · {{ selectedPersonForDetail?.job }}
              </p>
            </div>
            <button
              type="button"
              class="clay-circle-btn"
              aria-label="Kapat"
              @click="isDetailOpen = false"
            >
              <X :size="18" />
            </button>
          </header>

          <div class="payroll-sheet-body">
            <!-- Yükleniyor Göstergesi -->
            <div v-if="detailLoading" class="payroll-detail-loading">
              <div class="payroll-spinner"></div>
              <span>Bordro hesaplama kalemleri hazırlanıyor…</span>
            </div>

            <template v-else-if="detailData">
              <!-- Obsidian Hero Kartı -->
              <section class="bento-card bento-card--dark detail-hero-card">
                <div class="bento-overview-head">
                  <span class="dark-pill-tag">ELE GEÇEN NET TUTAR</span>
                  <Banknote :size="20" />
                </div>
                <div class="payroll-hero-main">
                  <strong class="payroll-hero-amount">{{ detailData.summary?.net_maas || formatMoney(selectedPersonForDetail?.net) }}</strong>
                </div>
                <div class="detail-hero-subgrid">
                  <div>
                    <span class="detail-hero-sublabel">TOPLAM HAKEDİŞ</span>
                    <strong>{{ detailData.summary?.brut_toplam || '—' }}</strong>
                  </div>
                  <div class="text-right">
                    <span class="detail-hero-sublabel">TOPLAM KESİNTİ</span>
                    <strong class="text-rose-300">{{ detailData.summary?.kesinti_toplam || '—' }}</strong>
                  </div>
                </div>
              </section>

              <!-- Dağılım Kartları -->
              <div class="detail-channels-grid">
                <div
                  v-if="detailData.summary?.banka"
                  class="detail-channel-card detail-channel-card--bank"
                >
                  <div class="channel-card-top">
                    <span class="channel-icon"><Landmark :size="16" /></span>
                    <button
                      v-if="detailData.summary?.banka_detay"
                      type="button"
                      class="channel-info-btn"
                      aria-label="Banka Detayı Bilgisi"
                      @click="showBankTooltip = !showBankTooltip"
                    >
                      <Info :size="14" />
                    </button>
                  </div>
                  <span class="channel-label">BANKA</span>
                  <strong class="channel-val">{{ detailData.summary?.banka }}</strong>

                  <!-- Banka Detay Açıklaması -->
                  <div
                    v-if="showBankTooltip && detailData.summary?.banka_detay"
                    class="banka-tooltip-box"
                    v-html="detailData.summary?.banka_detay"
                  ></div>
                </div>

                <div
                  v-if="detailData.summary?.elden"
                  class="detail-channel-card detail-channel-card--cash"
                >
                  <span class="channel-icon"><Banknote :size="16" /></span>
                  <span class="channel-label">ELDEN</span>
                  <strong class="channel-val">{{ detailData.summary?.elden }}</strong>
                </div>

                <div
                  v-if="detailData.summary?.sodexo"
                  class="detail-channel-card detail-channel-card--sodexo"
                >
                  <span class="channel-icon"><UtensilsCrossed :size="16" /></span>
                  <span class="channel-label">SODEXO</span>
                  <strong class="channel-val">{{ detailData.summary?.sodexo }}</strong>
                </div>

                <div
                  v-if="detailData.summary?.diger"
                  class="detail-channel-card detail-channel-card--other"
                >
                  <span class="channel-icon"><Wallet :size="16" /></span>
                  <span class="channel-label">DİĞER</span>
                  <strong class="channel-val">{{ detailData.summary?.diger }}</strong>
                </div>
              </div>

              <!-- Kalemler ve Resmi Kırılımlar HTML Entegrasyonu -->
              <details class="detail-legacy-details" open>
                <summary class="detail-legacy-summary">
                  <Receipt :size="16" />
                  <span>Resmî Hesaplama Kalemleri</span>
                  <ChevronDown :size="16" class="legacy-chevron" />
                </summary>
                <div
                  class="payroll-legacy-container"
                  v-html="detailData.html || '<p class=\'p-3 text-slate-400\'>Kalem detayı bulunamadı.</p>'"
                ></div>
              </details>
            </template>
          </div>
        </div>
      </div>
    </Transition>

    <!-- 2. ÖDEME DAĞILIMI DÜZENLE MODALI -->
    <Transition name="fade">
      <div
        v-if="isPaymentModalOpen"
        class="payroll-modal-backdrop"
        role="dialog"
        aria-modal="true"
        aria-label="Ödeme Dağılımını Düzenle"
        @click.self="isPaymentModalOpen = false"
      >
        <div class="payroll-modal-sheet payroll-modal-sheet--form">
          <div class="sheet-drag-handle"></div>

          <header class="payroll-sheet-header">
            <div>
              <span class="clay-page-eyebrow">ÖDEME DAĞILIMI</span>
              <h2 class="payroll-sheet-title">{{ paymentTarget?.name }}</h2>
              <p class="payroll-sheet-subtitle">
                Net Tutar: <b>{{ formatMoney(paymentTarget?.net) }}</b>
              </p>
            </div>
            <button
              type="button"
              class="clay-circle-btn"
              aria-label="Kapat"
              @click="isPaymentModalOpen = false"
            >
              <X :size="18" />
            </button>
          </header>

          <form class="payroll-modal-form" @submit.prevent="submitPaymentDistribution">
            <div class="form-group">
              <label for="inputBanka">Banka Ödemesi (₺)</label>
              <input
                id="inputBanka"
                v-model="paymentForm.banka"
                type="text"
                inputmode="decimal"
                placeholder="0,00"
                class="clay-form-input"
              />
            </div>

            <div class="form-group">
              <label for="inputSodexo">Sodexo Ödemesi (₺)</label>
              <input
                id="inputSodexo"
                v-model="paymentForm.sodexo"
                type="text"
                inputmode="decimal"
                placeholder="0,00"
                class="clay-form-input"
              />
            </div>

            <div class="form-group">
              <label for="inputDiger">Diğer Ödeme (₺)</label>
              <input
                id="inputDiger"
                v-model="paymentForm.diger"
                type="text"
                inputmode="decimal"
                placeholder="0,00"
                class="clay-form-input"
              />
            </div>

            <!-- Otomatik Kalan Elden Tutar -->
            <div class="payment-preview-box">
              <span>Otomatik Elden Ödeme:</span>
              <strong>{{ formatMoney(computedEldenPreview) }}</strong>
            </div>

            <div class="modal-form-actions">
              <button
                type="button"
                class="clay-btn clay-btn--cancel"
                @click="isPaymentModalOpen = false"
              >
                Vazgeç
              </button>
              <button
                type="submit"
                class="clay-btn clay-btn--primary"
                :disabled="paymentLoading"
              >
                <component :is="paymentLoading ? RefreshCw : Check" :size="16" :class="{ 'animate-spin': paymentLoading }" />
                <span>{{ paymentLoading ? 'Kaydediliyor…' : 'Dağılımı Kaydet' }}</span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </Transition>

    <!-- 3. GELİR / KESİNTİ EKLE MODALI -->
    <Transition name="fade">
      <div
        v-if="isAdjustmentModalOpen"
        class="payroll-modal-backdrop"
        role="dialog"
        aria-modal="true"
        :aria-label="adjustmentKind === 'gelir' ? 'Gelir Ekle' : 'Kesinti Ekle'"
        @click.self="isAdjustmentModalOpen = false"
      >
        <div class="payroll-modal-sheet payroll-modal-sheet--form">
          <div class="sheet-drag-handle"></div>

          <header class="payroll-sheet-header">
            <div>
              <span class="clay-page-eyebrow">
                {{ adjustmentKind === 'gelir' ? 'EK GELİR EKLE' : 'KESİNTİ EKLE' }}
              </span>
              <h2 class="payroll-sheet-title">{{ adjustmentTarget?.name }}</h2>
              <p class="payroll-sheet-subtitle">
                {{ adjustmentKind === 'gelir' ? 'Prim, mesai veya ikramiye' : 'Avans, icra veya izin kesintisi' }}
              </p>
            </div>
            <button
              type="button"
              class="clay-circle-btn"
              aria-label="Kapat"
              @click="isAdjustmentModalOpen = false"
            >
              <X :size="18" />
            </button>
          </header>

          <form class="payroll-modal-form" @submit.prevent="submitAdjustment">
            <div class="form-group">
              <label for="adjTur">İşlem Türü</label>
              <select id="adjTur" v-model="adjustmentForm.tur" class="clay-form-input">
                <template v-if="adjustmentKind === 'gelir'">
                  <option value="prim">Prim</option>
                  <option value="mesai">Fazla Mesai</option>
                  <option value="ikramiye">İkramiye</option>
                  <option value="yol">Yol Yardımı</option>
                  <option value="yemek">Yemek Yardımı</option>
                  <option value="diger">Diğer Ek Ödeme</option>
                </template>
                <template v-else>
                  <option value="avans">Avans</option>
                  <option value="icra">İcra</option>
                  <option value="nafaka">Nafaka</option>
                  <option value="izin_kesinti">Ücretsiz İzin</option>
                  <option value="diger">Diğer Kesinti</option>
                </template>
              </select>
            </div>

            <div class="form-group">
              <label for="adjTutar">Tutar (₺)</label>
              <input
                id="adjTutar"
                v-model="adjustmentForm.tutar"
                type="text"
                inputmode="decimal"
                required
                placeholder="Örn: 2500"
                class="clay-form-input"
              />
            </div>

            <div class="form-group">
              <label for="adjAciklama">Açıklama</label>
              <input
                id="adjAciklama"
                v-model="adjustmentForm.aciklama"
                type="text"
                required
                maxlength="250"
                placeholder="Açıklama yazın..."
                class="clay-form-input"
              />
            </div>

            <div class="form-group">
              <label for="adjTarih">Tarih</label>
              <input
                id="adjTarih"
                v-model="adjustmentForm.tarih"
                type="text"
                placeholder="GG.AA.YYYY"
                class="clay-form-input"
              />
            </div>

            <div class="modal-form-actions">
              <button
                type="button"
                class="clay-btn clay-btn--cancel"
                @click="isAdjustmentModalOpen = false"
              >
                Vazgeç
              </button>
              <button
                type="submit"
                class="clay-btn"
                :class="adjustmentKind === 'gelir' ? 'clay-btn--emerald' : 'clay-btn--rose'"
                :disabled="adjustmentLoading"
              >
                <component :is="adjustmentLoading ? RefreshCw : Check" :size="16" :class="{ 'animate-spin': adjustmentLoading }" />
                <span>{{ adjustmentLoading ? 'Kaydediliyor…' : 'Kaydet' }}</span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </Transition>

    <!-- 4. DİĞER İŞLEMLER BOTTOM SHEET -->
    <Transition name="fade">
      <div
        v-if="isMoreActionsOpen"
        class="payroll-modal-backdrop"
        role="dialog"
        aria-modal="true"
        aria-label="Diğer Bordro İşlemleri"
        @click.self="isMoreActionsOpen = false"
      >
        <div class="payroll-modal-sheet payroll-modal-sheet--actions">
          <div class="sheet-drag-handle"></div>

          <header class="payroll-sheet-header">
            <div>
              <span class="clay-page-eyebrow">DÖNEM İŞLEMLERİ</span>
              <h2 class="payroll-sheet-title">Diğer Bordro İşlemleri</h2>
            </div>
            <button
              type="button"
              class="clay-circle-btn"
              aria-label="Kapat"
              @click="isMoreActionsOpen = false"
            >
              <X :size="18" />
            </button>
          </header>

          <div class="payroll-action-list">
            <button
              type="button"
              class="payroll-action-row"
              :class="isClosed ? 'payroll-action-row--open' : 'payroll-action-row--close'"
              @click="isMoreActionsOpen = false; handleTogglePeriod()"
            >
              <span class="action-row-icon">
                <component :is="isClosed ? LockOpen : Lock" :size="20" />
              </span>
              <div class="action-row-meta">
                <strong>{{ isClosed ? 'Dönemi Yeniden Aç' : 'Dönemi Kapat' }}</strong>
                <span>{{ isClosed ? 'Dönemi yeniden düzenlemeye açar' : 'Bordro verilerini kilitler ve mühürler' }}</span>
              </div>
              <ChevronRight :size="18" class="action-row-chevron" />
            </button>

            <button
              type="button"
              class="payroll-action-row"
              :disabled="isClosed"
              @click="isMoreActionsOpen = false; handleResetAllPayments()"
            >
              <span class="action-row-icon action-row-icon--amber">
                <RotateCcw :size="20" />
              </span>
              <div class="action-row-meta">
                <strong>Ödeme Dağılımlarını Sıfırla</strong>
                <span>Tüm personellerin varsayılan dağılımını hesaplar</span>
              </div>
              <ChevronRight :size="18" class="action-row-chevron" />
            </button>

            <button
              type="button"
              class="payroll-action-row"
              @click="isMoreActionsOpen = false; loadBordro(selectedDonemId, true)"
            >
              <span class="action-row-icon action-row-icon--blue">
                <RefreshCw :size="20" />
              </span>
              <div class="action-row-meta">
                <strong>Verileri Yenile</strong>
                <span>En güncel dönem ve personel listesini çeker</span>
              </div>
              <ChevronRight :size="18" class="action-row-chevron" />
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- 5. ÖZEL CLAYMORPHIC / BENTO ONAY MODALI -->
    <Transition name="fade">
      <div
        v-if="confirmModal.open"
        class="payroll-modal-backdrop"
        role="alertdialog"
        aria-modal="true"
        :aria-label="confirmModal.title"
        @click.self="confirmModal.open = false"
      >
        <div class="payroll-confirm-dialog">
          <div class="confirm-dialog-icon" :class="confirmModal.danger ? 'confirm-dialog-icon--danger' : 'confirm-dialog-icon--info'">
            <component :is="confirmModal.danger ? AlertTriangle : Info" :size="24" />
          </div>

          <h3 class="confirm-dialog-title">{{ confirmModal.title }}</h3>
          <p class="confirm-dialog-message">{{ confirmModal.message }}</p>

          <div v-if="confirmModal.warnings?.length" class="confirm-warnings-box">
            <ul>
              <li v-for="(warn, idx) in confirmModal.warnings" :key="idx">{{ warn }}</li>
            </ul>
          </div>

          <div class="confirm-dialog-actions">
            <button
              type="button"
              class="clay-btn clay-btn--cancel"
              @click="confirmModal.open = false"
            >
              Vazgeç
            </button>
            <button
              type="button"
              class="clay-btn"
              :class="confirmModal.danger ? 'clay-btn--rose' : 'clay-btn--primary'"
              @click="handleConfirmSubmit"
            >
              {{ confirmModal.confirmText }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </section>
</template>

<style scoped>
/* ==================== BORDRO SAYFA YERLEŞİMİ ==================== */
.ds-payroll {
  padding-bottom: calc(var(--ds-bottom-dock-height, 72px) + env(safe-area-inset-bottom, 0px) + 32px);
  display: flex;
  flex-direction: column;
  gap: var(--ds-space-3, 12px);
}

/* ==================== DÖNEM SEÇİM KARTI ==================== */
.payroll-period-card {
  padding: var(--ds-space-3, 12px) var(--ds-space-4, 16px);
}

.payroll-period-header {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.payroll-period-label-wrap {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.payroll-select-wrapper {
  position: relative;
  width: 100%;
}

.payroll-select {
  width: 100%;
  height: 44px;
  background-color: #f1f5f9;
  border: 1px solid #e2e8f0;
  border-radius: var(--ds-radius-pill, 9999px);
  padding: 0 38px 0 16px;
  font-size: 14px;
  font-weight: 800;
  color: #0f172a;
  appearance: none;
  -webkit-appearance: none;
  outline: none;
  transition: border-color var(--ds-transition-fast), background-color var(--ds-transition-fast);
}

.payroll-select option {
  background-color: #ffffff;
  color: #0f172a;
  font-weight: 700;
}

.payroll-select:focus {
  border-color: #3b82f6;
  background-color: #ffffff;
}

.payroll-select-chevron {
  position: absolute;
  right: 14px;
  top: 50%;
  transform: translateY(-50%);
  pointer-events: none;
  color: #64748b;
}

.payroll-period-dates {
  margin-top: 10px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 11px;
  font-weight: 600;
  color: #64748b;
  border-top: 1px dashed #e2e8f0;
  padding-top: 8px;
}

.payroll-period-dates > span {
  display: flex;
  align-items: center;
  gap: 4px;
}

.payroll-period-count {
  font-weight: 700;
  color: #3b82f6;
}

/* ==================== KPI KARTLARI ==================== */
.payroll-kpi-hero {
  padding: 18px 20px;
}

.payroll-hero-main {
  margin: 12px 0 16px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.payroll-hero-amount {
  font-size: clamp(22px, 6.2vw, 28px);
  line-height: 1.15;
  font-weight: 800;
  letter-spacing: -0.02em;
  color: #ffffff;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 100%;
}

.payroll-hero-sublabel {
  font-size: 11px;
  font-weight: 600;
  color: rgba(255, 255, 255, 0.7);
  letter-spacing: 0.02em;
}

.payroll-deduction-badge {
  color: #fca5a5;
  font-weight: 700;
}

.payroll-sub-kpi-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
}

.kpi-mini-card {
  padding: 12px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.kpi-mini-icon {
  width: 32px;
  height: 32px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.kpi-mini-icon--blue {
  background: #eff6ff;
  color: #2563eb;
}

.kpi-mini-icon--amber {
  background: #fef3c7;
  color: #d97706;
}

.kpi-mini-icon--cyan {
  background: #ecfeff;
  color: #0891b2;
}

.kpi-mini-body {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.kpi-mini-label {
  font-size: 9px;
  font-weight: 800;
  letter-spacing: 0.05em;
  color: #94a3b8;
}

.kpi-mini-value {
  font-size: 13px;
  font-weight: 800;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* ==================== ARAMA VE FİLTRE ==================== */
.payroll-filter-section {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.payroll-search-box {
  position: relative;
  width: 100%;
}

.payroll-search-icon {
  position: absolute;
  left: 14px;
  top: 50%;
  transform: translateY(-50%);
  color: #94a3b8;
  pointer-events: none;
}

.payroll-search-input {
  width: 100%;
  height: 44px;
  background: #ffffff;
  border: 1px solid rgba(255, 255, 255, 0.9);
  box-shadow: var(--ds-shadow-bento);
  border-radius: var(--ds-radius-pill, 9999px);
  padding: 0 40px 0 42px;
  font-size: 13px;
  font-weight: 600;
  color: #1e293b;
  outline: none;
}

.payroll-search-input:focus {
  border-color: #3b82f6;
}

.payroll-search-clear {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background: #f1f5f9;
  color: #64748b;
  border: none;
  display: flex;
  align-items: center;
  justify-content: center;
}

.payroll-tabs {
  padding-bottom: 2px;
  overflow-x: auto;
  scrollbar-width: none;
}

.payroll-tabs::-webkit-scrollbar {
  display: none;
}

/* ==================== PERSONEL KARTLARI ==================== */
.payroll-cards-stack {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.payroll-person-card {
  padding: 14px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.payroll-card-header {
  display: flex;
  align-items: center;
  gap: 12px;
}

.payroll-avatar-box {
  width: 44px;
  height: 44px;
  border-radius: 14px;
  overflow: hidden;
  flex-shrink: 0;
  background: #f1f5f9;
}

.payroll-avatar-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.payroll-avatar-initials {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  font-weight: 800;
  color: #475569;
  background: linear-gradient(135deg, #e2e8f0 0%, #cbd5e1 100%);
}

.payroll-person-meta {
  flex: 1;
  min-width: 0;
}

.payroll-person-name-row {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
}

.payroll-person-name {
  font-size: 14px;
  font-weight: 800;
  color: #0f172a;
  margin: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.payroll-status-pill {
  font-size: 8px;
  font-weight: 800;
  padding: 2px 6px;
  border-radius: 6px;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.payroll-status-pill--calc {
  background: #dcfce7;
  color: #15803d;
}

.payroll-status-pill--pending {
  background: #fef3c7;
  color: #b45309;
}

.payroll-person-sub {
  font-size: 11px;
  font-weight: 600;
  color: #64748b;
  margin: 2px 0 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.payroll-net-col {
  text-align: right;
  flex-shrink: 0;
}

.payroll-net-label {
  display: block;
  font-size: 8px;
  font-weight: 800;
  color: #94a3b8;
  letter-spacing: 0.05em;
}

.payroll-net-value {
  display: block;
  font-size: 15px;
  font-weight: 900;
  line-height: 1.2;
}

/* ==================== 2x3 KIRILIM IZGARASI ==================== */
.payroll-breakdown-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 6px;
}

.breakdown-item {
  background: #f8fafc;
  border-radius: 10px;
  padding: 6px 8px;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.breakdown-item--danger {
  background: #fff1f2;
}

.breakdown-item--blue {
  background: #eff6ff;
}

.breakdown-item--cyan {
  background: #ecfeff;
}

.breakdown-item--amber {
  background: #fffbeb;
}

.breakdown-label {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 9px;
  font-weight: 700;
  color: #64748b;
  text-transform: uppercase;
}

.breakdown-value {
  font-size: 11px;
  font-weight: 800;
  color: #1e293b;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* ==================== AKSİYON BUTONLARI ==================== */
.payroll-card-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  padding-top: 4px;
  border-top: 1px dashed #f1f5f9;
}

.payroll-action-btn {
  height: 40px;
  border-radius: 12px;
  padding: 0 12px;
  font-size: 12px;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  background: #f1f5f9;
  color: #334155;
  border: none;
  cursor: pointer;
  transition: all var(--ds-transition-fast);
}

.payroll-action-btn:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.payroll-action-btn--primary {
  flex: 1;
  background: #eff6ff;
  color: #2563eb;
}

.payroll-action-btn--primary:hover {
  background: #dbeafe;
}

.payroll-quick-adj-group {
  display: flex;
  align-items: center;
  gap: 4px;
}

.payroll-icon-action {
  width: 40px;
  height: 40px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  border: none;
  cursor: pointer;
  transition: transform var(--ds-transition-fast);
}

.payroll-icon-action:active {
  transform: scale(0.94);
}

.payroll-icon-action--income {
  background: #dcfce7;
  color: #16a34a;
}

.payroll-icon-action--deduct {
  background: #fee2e2;
  color: #dc2626;
}

/* ==================== MODAL & BOTTOM SHEET STYLES ==================== */
.payroll-modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 200;
  background: rgba(15, 23, 42, 0.6);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: flex-end;
  justify-content: center;
}

.payroll-modal-sheet {
  width: 100%;
  max-width: 540px;
  max-height: 88vh;
  background: #ffffff;
  border-top-left-radius: 28px;
  border-top-right-radius: 28px;
  box-shadow: 0 -8px 32px rgba(15, 23, 42, 0.2);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  animation: slideUp 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes slideUp {
  from {
    transform: translateY(100%);
  }
  to {
    transform: translateY(0);
  }
}

.sheet-drag-handle {
  width: 40px;
  height: 4px;
  background: #cbd5e1;
  border-radius: 9999px;
  margin: 10px auto 4px;
}

.payroll-sheet-header {
  padding: 12px 20px;
  border-bottom: 1px solid #f1f5f9;
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.payroll-sheet-title {
  font-size: 16px;
  font-weight: 800;
  color: #0f172a;
  margin: 2px 0 0;
}

.payroll-sheet-subtitle {
  font-size: 11px;
  font-weight: 600;
  color: #64748b;
  margin: 2px 0 0;
}

.payroll-sheet-body {
  padding: 16px 20px 28px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

/* Detay Yükleniyor */
.payroll-detail-loading {
  padding: 36px 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  color: #64748b;
  font-size: 12px;
  font-weight: 700;
}

.payroll-spinner {
  width: 32px;
  height: 32px;
  border: 3px solid #e2e8f0;
  border-top-color: #3b82f6;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.detail-hero-card {
  padding: 18px;
}

.detail-hero-subgrid {
  margin-top: 12px;
  padding-top: 12px;
  border-top: 1px solid rgba(255, 255, 255, 0.15);
  display: grid;
  grid-template-columns: 1fr 1fr;
  font-size: 13px;
}

.detail-hero-sublabel {
  display: block;
  font-size: 9px;
  font-weight: 700;
  color: rgba(255, 255, 255, 0.6);
  letter-spacing: 0.05em;
}

.detail-channels-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 8px;
}

.detail-channel-card {
  border-radius: 16px;
  padding: 12px;
  display: flex;
  flex-direction: column;
  gap: 4px;
  position: relative;
}

.detail-channel-card--bank {
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  color: #1e40af;
}

.detail-channel-card--cash {
  background: #fffbeb;
  border: 1px solid #fde68a;
  color: #92400e;
}

.detail-channel-card--sodexo {
  background: #ecfeff;
  border: 1px solid #a5f3fc;
  color: #155e75;
}

.detail-channel-card--other {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  color: #334155;
}

.channel-card-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.channel-info-btn {
  background: rgba(30, 64, 175, 0.1);
  border: none;
  width: 22px;
  height: 22px;
  border-radius: 50%;
  color: #1e40af;
  display: flex;
  align-items: center;
  justify-content: center;
}

.channel-label {
  font-size: 9px;
  font-weight: 800;
  letter-spacing: 0.05em;
  opacity: 0.75;
}

.channel-val {
  font-size: 14px;
  font-weight: 900;
}

.banka-tooltip-box {
  margin-top: 8px;
  padding: 10px;
  background: #1e293b;
  color: #f1f5f9;
  font-size: 11px;
  line-height: 1.4;
  border-radius: 10px;
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.2);
}

.banka-tooltip-box :deep(table) {
  width: 100%;
  font-size: 10px;
}

/* Accordion Kalemler */
.detail-legacy-details {
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  overflow: hidden;
  background: #ffffff;
}

.detail-legacy-summary {
  padding: 12px 14px;
  font-size: 12px;
  font-weight: 800;
  color: #1e293b;
  background: #f8fafc;
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  list-style: none;
}

.detail-legacy-summary::-webkit-details-marker {
  display: none;
}

.legacy-chevron {
  margin-left: auto;
  transition: transform 0.2s ease;
}

.detail-legacy-details[open] .legacy-chevron {
  transform: rotate(180deg);
}

.payroll-legacy-container {
  padding: 12px;
  font-size: 11px;
  color: #334155;
  line-height: 1.5;
  overflow-x: auto;
}

.payroll-legacy-container :deep(table) {
  width: 100%;
  min-width: 320px;
  border-collapse: collapse;
}

.payroll-legacy-container :deep(th),
.payroll-legacy-container :deep(td) {
  padding: 6px 8px;
  border-bottom: 1px solid #f1f5f9;
}

.payroll-legacy-container :deep(th) {
  font-size: 9px;
  font-weight: 800;
  text-transform: uppercase;
  color: #64748b;
  background: #f8fafc;
}

/* ==================== FORM VE GİRİŞ ALANLARI ==================== */
.payroll-modal-form {
  padding: 16px 20px 24px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.form-group label {
  font-size: 11px;
  font-weight: 800;
  color: #475569;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.clay-form-input {
  height: 44px;
  border-radius: 12px;
  border: 1px solid #cbd5e1;
  background: #f8fafc;
  padding: 0 14px;
  font-size: 14px;
  font-weight: 600;
  color: #0f172a;
  outline: none;
}

.clay-form-input:focus {
  border-color: #3b82f6;
  background: #ffffff;
}

.payment-preview-box {
  background: #f0fdf4;
  border: 1px dashed #86efac;
  border-radius: 12px;
  padding: 10px 14px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 12px;
  font-weight: 700;
  color: #15803d;
}

.modal-form-actions {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-top: 8px;
}

.clay-btn {
  height: 44px;
  border-radius: 14px;
  padding: 0 18px;
  font-size: 13px;
  font-weight: 800;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  border: none;
  cursor: pointer;
  flex: 1;
}

.clay-btn--cancel {
  background: #f1f5f9;
  color: #64748b;
}

.clay-btn--primary {
  background: #2563eb;
  color: #ffffff;
}

.clay-btn--emerald {
  background: #16a34a;
  color: #ffffff;
}

.clay-btn--rose {
  background: #dc2626;
  color: #ffffff;
}

/* ==================== DİĞER İŞLEMLER LİSTESİ ==================== */
.payroll-action-list {
  padding: 16px 20px 24px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.payroll-action-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  border-radius: 16px;
  background: #f8fafc;
  border: 1px solid #f1f5f9;
  text-align: left;
  cursor: pointer;
  transition: background-color var(--ds-transition-fast);
}

.payroll-action-row:hover {
  background: #f1f5f9;
}

.payroll-action-row:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.payroll-action-row--open {
  background: #ecfdf5;
  border-color: #a7f3d0;
  color: #065f46;
}

.payroll-action-row--close {
  background: #fef2f2;
  border-color: #fecaca;
  color: #991b1b;
}

.action-row-icon {
  width: 40px;
  height: 40px;
  border-radius: 12px;
  background: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
}

.action-row-icon--amber {
  color: #d97706;
}

.action-row-icon--blue {
  color: #2563eb;
}

.action-row-meta {
  flex: 1;
  min-width: 0;
}

.action-row-meta strong {
  display: block;
  font-size: 13px;
  font-weight: 800;
}

.action-row-meta span {
  display: block;
  font-size: 10px;
  font-weight: 600;
  color: #64748b;
  margin-top: 1px;
}

.action-row-chevron {
  color: #94a3b8;
}

/* ==================== ONAY MODALI ==================== */
.payroll-confirm-dialog {
  width: 90%;
  max-width: 360px;
  background: #ffffff;
  border-radius: 24px;
  padding: 24px 20px;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: 10px;
  animation: popIn 0.2s cubic-bezier(0.16, 1, 0.3, 1);
  margin-bottom: auto;
  margin-top: auto;
}

@keyframes popIn {
  from {
    transform: scale(0.92);
    opacity: 0;
  }
  to {
    transform: scale(1);
    opacity: 1;
  }
}

.confirm-dialog-icon {
  width: 52px;
  height: 52px;
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 4px;
}

.confirm-dialog-icon--info {
  background: #eff6ff;
  color: #2563eb;
}

.confirm-dialog-icon--danger {
  background: #fef2f2;
  color: #dc2626;
}

.confirm-dialog-title {
  font-size: 16px;
  font-weight: 800;
  color: #0f172a;
  margin: 0;
}

.confirm-dialog-message {
  font-size: 12px;
  font-weight: 600;
  color: #64748b;
  line-height: 1.45;
  margin: 0;
}

.confirm-warnings-box {
  width: 100%;
  max-height: 120px;
  overflow-y: auto;
  background: #fff1f2;
  border-radius: 12px;
  padding: 8px 12px;
  text-align: left;
  font-size: 11px;
  color: #be123c;
}

.confirm-warnings-box ul {
  padding-left: 18px;
  margin: 0;
}

.confirm-dialog-actions {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  margin-top: 8px;
}

/* ==================== TOAST BİLDİRİMİ ==================== */
.payroll-toast {
  position: fixed;
  top: calc(env(safe-area-inset-top, 0px) + 64px);
  left: 16px;
  right: 16px;
  z-index: 300;
  padding: 12px 16px;
  border-radius: 16px;
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 12px;
  font-weight: 800;
  color: #ffffff;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
}

.payroll-toast--success {
  background: #059669;
}

.payroll-toast--error {
  background: #dc2626;
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}
</style>
