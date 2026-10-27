<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import {
  AlertCircle,
  AlertTriangle,
  Archive,
  ArrowDownLeft,
  ArrowLeft,
  ArrowLeftRight,
  ArrowUpRight,
  BarChart3,
  Building2,
  Calendar,
  Check,
  CheckCircle2,
  ChevronRight,
  Clock,
  Download,
  Edit3,
  ExternalLink,
  Eye,
  FileText,
  Filter,
  Inbox,
  Layers,
  Maximize2,
  Package,
  Percent,
  PieChart,
  Plus,
  Printer,
  Receipt,
  ReceiptText,
  RefreshCw,
  Scale,
  Search,
  Send,
  Share2,
  Sparkles,
  Trash2,
  TrendingDown,
  TrendingUp,
  User,
  Wallet,
  X,
  XCircle,
  Zap,
  ZoomIn,
  ZoomOut,
} from '@lucide/vue'
import {
  faturaApi,
  faturaState,
  openFaturaDrawer,
  closeFaturaDrawer,
  setFaturaTab,
} from '../services/fatura.js'
import { auth } from '../services/auth.js'
import TopBar from '../components/TopBar.vue'

const router = useRouter()
const route = useRoute()

const invoiceTabPermissions = computed(() => ({
  ozet: Boolean(auth.state.permissions.invoice_dashboard),
  giden: Boolean(auth.state.permissions.invoice_outgoing),
  gelen: Boolean(auth.state.permissions.invoice_incoming),
  taslak: Boolean(auth.state.permissions.invoice_drafts),
}))
const firstAllowedInvoiceTab = () => ['ozet', 'giden', 'gelen', 'taslak'].find(tab => invoiceTabPermissions.value[tab]) || 'ozet'

// 1. Ana Durumlar & Sekmeler
const activeTab = ref(route.query.tab || faturaState.activeTab || 'ozet') // ozet, giden, gelen, taslak

watch(() => faturaState.activeTab, (newTab) => {
  if (activeTab.value !== newTab) {
    switchTab(newTab)
  }
})

watch(activeTab, (newTab) => {
  if (faturaState.activeTab !== newTab) {
    faturaState.activeTab = newTab
  }
})
const loading = ref(true)
const refreshing = ref(false)
const errorMessage = ref('')
const feedbackMessage = ref('')
const feedbackType = ref('success') // success, error

// 2. Dönem Durumu
const currentPeriod = ref('this_month') // this_month, last_month, last_3_months, this_year, all, custom
const startDate = ref('')
const endDate = ref('')
const customPeriodLabel = ref('Dönem Seç')
const isPeriodModalOpen = ref(false)
const periodModalYear = ref(new Date().getFullYear())
const customStartInput = ref('')
const customEndInput = ref('')

// 3. Dashboard / Finans Verisi
const dashData = ref({
  gelen: { toplam_tutar: 0, toplam_matrah: 0, toplam_kdv: 0, toplam_adet: 0 },
  giden: { toplam_tutar: 0, toplam_matrah: 0, toplam_kdv: 0, toplam_adet: 0 },
  kdv: { net_kdv: 0, odenecek_kdv: 0, devreden_kdv: 0, hesaplanan_kdv: 0, indirilecek_kdv: 0 },
  recent_gelen: [],
  recent_giden: [],
})
const recentSubTab = ref('gelen') // gelen, giden

// 4. Liste Verileri & Filtreler
const invoiceList = ref([])
const listSummary = ref({})
const listLoading = ref(false)
const listPagination = ref({ page: 1, limit: 25, total: 0, has_more: false })
const searchQuery = ref('')
const productSearchQuery = ref('')
const statusFilter = ref('') // giden için durum
const responseFilter = ref('') // gelen için ticari yanıt
let searchTimer = null

// 5. Fatura HTML / PDF Önizleme Modalı
const isPreviewOpen = ref(false)
const previewLoading = ref(false)
const previewTitle = ref('Fatura Önizleme')
const previewEncId = ref('')
const previewInvoice = ref(null)
const previewHtmlContent = ref('')
const previewIframeRef = ref(null)
const viewerViewportRef = ref(null)
const baseWidth = ref(820)
const baseHeight = ref(1160)
const zoomScale = ref(1)
const fitScale = ref(1)
const minScale = ref(0.2)
const maxScale = ref(2.5)

const isFitted = computed(() => {
  return Math.abs(zoomScale.value - fitScale.value) < 0.03
})

const scaledDimensions = computed(() => ({
  width: Math.round(baseWidth.value * zoomScale.value),
  height: Math.round(baseHeight.value * zoomScale.value),
}))

const sharingPdf = ref(false)
const sharingInvoiceId = ref(null)

// 6. Ticari Yanıt Verme Modalı
const isResponseModalOpen = ref(false)
const responseInvoice = ref(null)
const responseType = ref('KABUL') // KABUL, RED
const responseReason = ref('')
const responseSubmitting = ref(false)

// 6.1. Cariye İşle Durumları & Modalları
const syncingInvoiceId = ref(null)
const isCariConfirmModalOpen = ref(false)
const cariConfirmInfo = ref(null)
const cariConfirmInvoice = ref(null)
const cariCheckingLoading = ref(false)

const isDuplicateModalOpen = ref(false)
const duplicateWarningData = ref(null)
const duplicatePendingInvoice = ref(null)
const duplicateSubmitting = ref(false)

// 7. Yeni Fatura Kes / Taslak Sihirbazı Modalı
const isWizardOpen = ref(false)
const wizardStep = ref(1) // 1: Cari, 2: Fatura Detay, 3: Kalemler, 4: Özet & Gönder
const wizardSubmitting = ref(false)
const wizardEditId = ref(null) // Eğer taslak düzenleniyorsa

// Sihirbaz Form Alanları
const wizardCustomerSearch = ref('')
const customerSearchResults = ref([])
const customerSearching = ref(false)
const checkingTaxpayer = ref(false)
const taxpayerInfo = ref(null)

const invoiceHeader = ref({
  cari_id: '',
  alici_unvan: '',
  alici_vkn_tckn: '',
  alici_vergi_dairesi: '',
  alici_adres: '',
  alici_il: '',
  alici_ilce: '',
  alici_eposta: '',
  fatura_tarihi: new Date().toISOString().slice(0, 10),
  fatura_saati: new Date().toTimeString().slice(0, 5),
  belge_turu: 'EFATURA', // EFATURA veya EARSIV
  fatura_tipi: 'SATIS', // SATIS, IADE, ISTISNA
  senaryo: 'TICARIFATURA', // TICARIFATURA, TEMELFATURA, EARSIVFATURA
  seri: '',
  para_birimi: 'TRY',
  doviz_kuru: 1,
  notlar: '',
})

const invoiceLines = ref([
  {
    mal_hizmet_id: '',
    aciklama: '',
    miktar: 1,
    birim: 'C62', // Adet
    birim_fiyat: 0,
    iskonto_orani: 0,
    kdv_orani: 20,
    kdv_tutari: 0,
    mal_hizmet_tutari: 0,
  },
])

const wizardCalculation = ref({
  mal_hizmet_toplam: 0,
  iskonto_toplam: 0,
  kdv_toplam: 0,
  odenecek_tutar: 0,
})

const availableSerials = ref([])
const availableProducts = ref([])
const productSearchResults = ref([])
const productSearching = ref(false)

function parseMoneyAmount(amount) {
  if (amount === undefined || amount === null || amount === '') return 0
  if (typeof amount === 'number') return isNaN(amount) ? 0 : amount
  let str = String(amount).trim()
  str = str.replace(/[^0-9,.-]/g, '').trim()
  if (!str) return 0
  if (str.includes(',') && str.includes('.')) {
    str = str.replace(/\./g, '').replace(',', '.')
  } else if (str.includes(',')) {
    str = str.replace(',', '.')
  }
  const num = Number(str)
  return isNaN(num) ? 0 : num
}

// Yardımcı Para ve Tarih Formatlayıcıları
function formatMoney(amount) {
  const val = parseMoneyAmount(amount)
  return new Intl.NumberFormat('tr-TR', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(val) + ' ₺'
}

function formatInvoiceMoney(amount, currency = 'TRY') {
  const val = parseMoneyAmount(amount)
  const formatted = new Intl.NumberFormat('tr-TR', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(val)
  const c = String(currency || 'TRY').toUpperCase()
  const sym = c === 'USD' ? '$' : (c === 'EUR' ? '€' : '₺')
  return `${formatted} ${sym}`
}

// ====================================================
// Özet & Grafik Analiz Metrikleri (Computed)
// ====================================================
const totalTradeVolume = computed(() => {
  const giden = parseMoneyAmount(dashData.value.giden?.toplam_tutar)
  const gelen = parseMoneyAmount(dashData.value.gelen?.toplam_tutar)
  return giden + gelen
})

const netTradeBalance = computed(() => {
  const giden = parseMoneyAmount(dashData.value.giden?.toplam_tutar)
  const gelen = parseMoneyAmount(dashData.value.gelen?.toplam_tutar)
  return giden - gelen
})

const salesRatio = computed(() => {
  if (totalTradeVolume.value <= 0) return 0
  const giden = parseMoneyAmount(dashData.value.giden?.toplam_tutar)
  return Math.round((giden / totalTradeVolume.value) * 100)
})

const purchaseRatio = computed(() => {
  if (totalTradeVolume.value <= 0) return 0
  return 100 - salesRatio.value
})

const avgInvoiceOutgoing = computed(() => {
  const count = Number(dashData.value.giden?.toplam_adet || 0)
  if (count <= 0) return 0
  return parseMoneyAmount(dashData.value.giden?.toplam_tutar) / count
})

const avgInvoiceIncoming = computed(() => {
  const count = Number(dashData.value.gelen?.toplam_adet || 0)
  if (count <= 0) return 0
  return parseMoneyAmount(dashData.value.gelen?.toplam_tutar) / count
})

const kdvCoverageRatio = computed(() => {
  const hesaplanan = parseMoneyAmount(dashData.value.kdv?.hesaplanan_kdv)
  const indirilecek = parseMoneyAmount(dashData.value.kdv?.indirilecek_kdv)
  if (hesaplanan <= 0) {
    return indirilecek > 0 ? 100 : 0
  }
  return Math.min(Math.round((indirilecek / hesaplanan) * 100), 100)
})

const kdvEffectiveRate = computed(() => {
  const matrah = parseMoneyAmount(dashData.value.giden?.toplam_matrah)
  const kdv = parseMoneyAmount(dashData.value.kdv?.hesaplanan_kdv)
  if (matrah <= 0) return 0
  return ((kdv / matrah) * 100).toFixed(1)
})

function formatDate(dateStr) {
  if (!dateStr) return '-'
  try {
    const parts = String(dateStr).split(' ')[0].split('-')
    if (parts.length === 3) {
      return `${parts[2]}.${parts[1]}.${parts[0]}`
    }
  } catch (e) {}
  return String(dateStr)
}

function showToast(msg, type = 'success') {
  feedbackMessage.value = msg
  feedbackType.value = type
  setTimeout(() => {
    feedbackMessage.value = ''
  }, 3500)
}

// ----------------------------------------------------
// DÖNEM YÖNETİMİ
// ----------------------------------------------------
function initDatesForPeriod(period) {
  const now = new Date()
  const y = now.getFullYear()
  const m = now.getMonth()
  const toIso = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`

  if (period === 'this_month') {
    startDate.value = toIso(new Date(y, m, 1))
    endDate.value = toIso(new Date(y, m + 1, 0))
  } else if (period === 'last_month') {
    startDate.value = toIso(new Date(y, m - 1, 1))
    endDate.value = toIso(new Date(y, m, 0))
  } else if (period === 'last_3_months') {
    startDate.value = toIso(new Date(y, m - 2, 1))
    endDate.value = toIso(new Date(y, m + 1, 0))
  } else if (period === 'this_year') {
    startDate.value = toIso(new Date(y, 0, 1))
    endDate.value = toIso(new Date(y, 11, 31))
  } else if (period === 'all') {
    startDate.value = ''
    endDate.value = ''
  }
}

function setPeriod(p) {
  currentPeriod.value = p
  customPeriodLabel.value = 'Dönem Seç'
  initDatesForPeriod(p)
  loadCurrentTab(true)
}

function openPeriodModal() {
  customStartInput.value = startDate.value || new Date().toISOString().slice(0, 10)
  customEndInput.value = endDate.value || new Date().toISOString().slice(0, 10)
  isPeriodModalOpen.value = true
}

function applyCustomMonth(mNum, mName) {
  const year = periodModalYear.value
  const lastDay = new Date(year, mNum, 0).getDate()
  startDate.value = `${year}-${String(mNum).padStart(2, '0')}-01`
  endDate.value = `${year}-${String(mNum).padStart(2, '0')}-${String(lastDay).padStart(2, '0')}`
  customPeriodLabel.value = `${mName} ${year}`
  currentPeriod.value = 'custom'
  isPeriodModalOpen.value = false
  loadCurrentTab(true)
}

function applyCustomDateRange() {
  if (!customStartInput.value || !customEndInput.value) {
    showToast('Lütfen başlangıç ve bitiş tarihlerini seçin.', 'error')
    return
  }
  startDate.value = customStartInput.value
  endDate.value = customEndInput.value
  const fmt = (d) => {
    const p = d.split('-')
    return `${p[2]}.${p[1]}`
  }
  customPeriodLabel.value = `${fmt(startDate.value)} - ${fmt(endDate.value)}`
  currentPeriod.value = 'custom'
  isPeriodModalOpen.value = false
  loadCurrentTab(true)
}

// ----------------------------------------------------
// VERİ ÇEKME FONKSİYONLARI
// ----------------------------------------------------
async function loadDashboard() {
  loading.value = true
  errorMessage.value = ''
  try {
    const params = {}
    if (startDate.value) params.baslangic_tarihi = startDate.value
    if (endDate.value) params.bitis_tarihi = endDate.value

    const res = await faturaApi.dashboard(params)
    if (res.success && res.data) {
      dashData.value = res.data
      faturaState.counts.giden = res.data.giden?.toplam_adet || 0
      faturaState.counts.gelen = res.data.gelen?.toplam_adet || 0
    }
  } catch (err) {
    errorMessage.value = err.message || 'Dashboard verisi yüklenirken hata oluştu.'
  } finally {
    loading.value = false
    refreshing.value = false
  }
}

async function loadInvoices(reset = true) {
  if (reset) {
    listPagination.value.page = 1
    invoiceList.value = []
  }
  listLoading.value = true
  errorMessage.value = ''

  try {
    const params = {
      list_type: activeTab.value,
      page: listPagination.value.page,
      limit: listPagination.value.limit,
    }

    if (searchQuery.value) {
      params.search = searchQuery.value
      params.urun_ara = searchQuery.value
    }
    if (statusFilter.value) params.durum_filtre = statusFilter.value
    if (responseFilter.value) params.ticari_yanit = responseFilter.value

    if (startDate.value) params.baslangic_tarihi = startDate.value
    if (endDate.value) params.bitis_tarihi = endDate.value

    const res = await faturaApi.list(params)
    if (res.success) {
      if (reset) {
        invoiceList.value = res.data || []
      } else {
        invoiceList.value = [...invoiceList.value, ...(res.data || [])]
      }
      listSummary.value = res.summary || {}
      listPagination.value = res.pagination || listPagination.value
      if (activeTab.value === 'taslak') {
        faturaState.counts.taslak = res.pagination?.total || 0
      } else if (activeTab.value === 'giden' && !statusFilter.value && !searchQuery.value) {
        faturaState.counts.giden = res.pagination?.total || 0
      } else if (activeTab.value === 'gelen' && !responseFilter.value && !searchQuery.value) {
        faturaState.counts.gelen = res.pagination?.total || 0
      }
    }
  } catch (err) {
    errorMessage.value = err.message || 'Fatura listesi yüklenemedi.'
  } finally {
    listLoading.value = false
    refreshing.value = false
  }
}

function loadCurrentTab(reset = true) {
  if (activeTab.value === 'ozet') {
    loadDashboard()
  } else {
    loadInvoices(reset)
  }
}

function handleTopBarBack() {
  if (activeTab.value === 'ozet' || !invoiceTabPermissions.value.ozet) return false
  switchTab('ozet')
  return true
}

function switchTab(tab) {
  if (!invoiceTabPermissions.value[tab]) return
  activeTab.value = tab
  if (faturaState.activeTab !== tab) {
    faturaState.activeTab = tab
  }
  router.replace({ query: { ...route.query, tab } })
  // Arama ve filtreleri sıfırla
  searchQuery.value = ''
  statusFilter.value = ''
  responseFilter.value = ''
  loadCurrentTab(true)
}

function refreshAll() {
  refreshing.value = true
  loadCurrentTab(true)
}

// Arama kutusu ve filtreleri izleme
watch([searchQuery, statusFilter, responseFilter], () => {
  if (activeTab.value === 'ozet') return
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    loadInvoices(true)
  }, 300)
})

// ----------------------------------------------------
// ÖNİZLEME MODALI & ZOOM / FIT YÖNETİMİ
// ----------------------------------------------------
function fitToScreen() {
  const viewport = viewerViewportRef.value
  if (!viewport) return
  const availWidth = Math.max(viewport.clientWidth - 16, 260)
  const scale = Number((availWidth / baseWidth.value).toFixed(3))
  fitScale.value = scale
  zoomScale.value = scale
  minScale.value = Math.max(Number((scale * 0.7).toFixed(3)), 0.15)
}

function zoomIn() {
  const next = Math.min(zoomScale.value + 0.15, maxScale.value)
  zoomScale.value = Number(next.toFixed(3))
}

function zoomOut() {
  const prev = Math.max(zoomScale.value - 0.15, minScale.value)
  zoomScale.value = Number(prev.toFixed(3))
}

function toggleFitOr100() {
  if (isFitted.value) {
    zoomScale.value = 1.0
  } else {
    fitToScreen()
  }
}

function onIframeLoaded() {
  try {
    const iframe = previewIframeRef.value
    if (iframe && iframe.contentDocument) {
      const doc = iframe.contentDocument
      const style = doc.createElement('style')
      style.textContent = `
        html, body {
          background-color: #ffffff !important;
          margin: 0 !important;
          padding: 8px !important;
          box-sizing: border-box !important;
        }
      `
      doc.head?.appendChild(style)

      const bodyWidth = Math.max(
        doc.body?.scrollWidth || 0,
        doc.documentElement?.scrollWidth || 0,
        doc.body?.offsetWidth || 0
      )
      const bodyHeight = Math.max(
        doc.body?.scrollHeight || 0,
        doc.documentElement?.scrollHeight || 0,
        doc.body?.offsetHeight || 0
      )
      if (bodyWidth > 400) {
        baseWidth.value = bodyWidth
      }
      if (bodyHeight > 400) {
        baseHeight.value = bodyHeight
      }
    }
  } catch (e) {}

  setTimeout(() => {
    fitToScreen()
  }, 50)
}

// İki parmakla kıstırma (Pinch to Zoom) dokunmatik hareketleri
let touchInitialDist = 0
let touchStartScale = 1

function getTouchesDistance(touches) {
  const dx = touches[0].clientX - touches[1].clientX
  const dy = touches[0].clientY - touches[1].clientY
  return Math.sqrt(dx * dx + dy * dy)
}

function onTouchStart(e) {
  if (e.touches.length === 2) {
    touchInitialDist = getTouchesDistance(e.touches)
    touchStartScale = zoomScale.value
  }
}

function onTouchMove(e) {
  if (e.touches.length === 2 && touchInitialDist > 0) {
    const currentDist = getTouchesDistance(e.touches)
    const factor = currentDist / touchInitialDist
    const newScale = Math.min(Math.max(touchStartScale * factor, minScale.value), maxScale.value)
    zoomScale.value = Number(newScale.toFixed(3))
    if (e.cancelable) e.preventDefault()
  }
}

function onTouchEnd(e) {
  if (e.touches.length < 2) {
    touchInitialDist = 0
  }
}

function onWindowResize() {
  if (isPreviewOpen.value && isFitted.value) {
    fitToScreen()
  }
}

async function openPreview(encId, title = 'Fatura Önizleme', invObj = null) {
  previewEncId.value = encId
  previewTitle.value = title
  previewHtmlContent.value = ''
  isPreviewOpen.value = true
  previewLoading.value = true
  baseWidth.value = 820
  baseHeight.value = 1160
  zoomScale.value = 1

  const found = invObj
    || invoiceList.value.find(i => (i.encrypted_id || i.id) === encId)
    || (dashData.value.recent_giden || []).find(i => (i.encrypted_id || i.id) === encId)
    || (dashData.value.recent_gelen || []).find(i => (i.encrypted_id || i.id) === encId)
    || null
  previewInvoice.value = found ? { ...found } : null

  try {
    const res = await faturaApi.previewHtml(encId)
    if (res.success && res.html) {
      previewHtmlContent.value = res.html
    } else {
      previewHtmlContent.value = '<div style="padding:24px;text-align:center;color:#ba1a1a;font-family:sans-serif;font-weight:bold;">Önizleme oluşturulamadı.</div>'
    }
  } catch (err) {
    previewHtmlContent.value = `<div style="padding:24px;text-align:center;color:#ba1a1a;font-family:sans-serif;font-weight:bold;">${err.message || 'Önizleme yüklenirken hata oluştu.'}</div>`
  } finally {
    previewLoading.value = false
  }
}

function closePreview() {
  isPreviewOpen.value = false
  previewHtmlContent.value = ''
  zoomScale.value = 1
  previewInvoice.value = null
}

function printInvoice() {
  if (previewIframeRef.value && previewIframeRef.value.contentWindow) {
    previewIframeRef.value.contentWindow.focus()
    previewIframeRef.value.contentWindow.print()
  }
}

// ----------------------------------------------------
// PDF PAYLAŞIMI (WEB SHARE API - DOSYA BAZLI)
// ----------------------------------------------------
async function shareInvoicePdf(encId, invoiceNo, recipient = '') {
  const cleanNo = String(invoiceNo || 'Fatura').replace(/[^a-zA-Z0-9_-]/g, '_')
  const fileName = `${cleanNo}.pdf`

  try {
    const res = await fetch(`/api/fatura.php?action=download_pdf&id=${encodeURIComponent(encId)}`)
    if (!res.ok) {
      let errMsg = 'PDF belgesi sunucudan alınamadı.'
      try {
        const json = await res.json()
        if (json.message) errMsg = json.message
      } catch (e) {}
      throw new Error(errMsg)
    }

    const blob = await res.blob()
    const file = new File([blob], fileName, { type: 'application/pdf' })

    if (navigator.canShare && navigator.canShare({ files: [file] })) {
      await navigator.share({
        files: [file],
        title: `Fatura: ${cleanNo}`,
        text: recipient ? `${recipient} adına fatura.` : `Fatura No: ${cleanNo}`,
      })
      showToast('Fatura PDF dosyası paylaşıldı.')
    } else {
      // Tarayıcı doğrudan dosya paylaşımını desteklemiyorsa otomatik PDF indir ve bildir
      const blobUrl = URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = blobUrl
      a.download = fileName
      document.body.appendChild(a)
      a.click()
      document.body.removeChild(a)
      setTimeout(() => URL.revokeObjectURL(blobUrl), 3000)
      showToast('Tarayıcınız dosya paylaşımını desteklemediğinden PDF cihazınıza indirildi.')
    }
  } catch (err) {
    if (err.name === 'AbortError') {
      return
    }
    showToast(err.message || 'PDF paylaşılırken hata oluştu.', 'error')
  }
}

async function shareInvoice(inv) {
  if (sharingInvoiceId.value) return
  sharingInvoiceId.value = inv.encrypted_id
  showToast('Fatura PDF belgesi hazırlanıyor...')
  try {
    await shareInvoicePdf(inv.encrypted_id, inv.fatura_no, inv.alici_unvan)
  } finally {
    sharingInvoiceId.value = null
  }
}

async function shareCurrentPreviewPdf() {
  if (sharingPdf.value || !previewEncId.value) return
  sharingPdf.value = true
  showToast('Fatura PDF belgesi hazırlanıyor...')
  try {
    await shareInvoicePdf(previewEncId.value, previewTitle.value)
  } finally {
    sharingPdf.value = false
  }
}

// ----------------------------------------------------
// TİCARİ YANIT VERME (KABUL / RED)
// ----------------------------------------------------
function openResponseModal(inv) {
  responseInvoice.value = inv
  responseType.value = 'KABUL'
  responseReason.value = ''
  isResponseModalOpen.value = true
}

async function submitCommercialResponse() {
  if (!responseInvoice.value) return
  if (responseType.value === 'RED' && !responseReason.value.trim()) {
    showToast('Faturayı reddetmek için lütfen bir gerekçe belirtiniz.', 'error')
    return
  }

  responseSubmitting.value = true
  try {
    const encId = responseInvoice.value.encrypted_id
    const res = await faturaApi.respondCommercial(encId, responseType.value, responseReason.value, auth.state.csrfToken)
    if (res.success) {
      showToast(responseType.value === 'KABUL' ? 'Fatura onaylandı (Kabul yanıtı verildi).' : 'Fatura gerekçeli olarak reddedildi.')
      isResponseModalOpen.value = false
      loadInvoices(true)
    }
  } catch (err) {
    showToast(err.message || 'Ticari yanıt gönderilemedi.', 'error')
  } finally {
    responseSubmitting.value = false
  }
}

// ----------------------------------------------------
// TASLAK İŞLEMLERİ (GÖNDER / SİL)
// ----------------------------------------------------
async function sendDraft(inv) {
  if (!confirm(`"${inv.alici_unvan || 'Taslak'}" faturası EDM ve GİB sistemine iletilecektir. Onaylıyor musunuz?`)) {
    return
  }

  showToast('EDM sistemine iletiliyor...', 'success')
  try {
    const res = await faturaApi.sendInvoice(inv.encrypted_id, auth.state.csrfToken)
    if (res.success) {
      showToast(`Fatura başarıyla gönderildi! Fatura No: ${res.fatura_no || '-'}`)
      loadInvoices(true)
    }
  } catch (err) {
    showToast(err.message || 'Fatura gönderilirken hata oluştu.', 'error')
  }
}

async function deleteDraft(inv) {
  if (!confirm(`Bu taslak faturayı silmek istediğinizden emin misiniz?`)) {
    return
  }

  try {
    const res = await faturaApi.deleteDraft(inv.encrypted_id, auth.state.csrfToken)
    if (res.success) {
      showToast('Taslak fatura silindi.')
      loadInvoices(true)
    }
  } catch (err) {
    showToast(err.message || 'Taslak silinemedi.', 'error')
  }
}

// ----------------------------------------------------
// CARİYE İŞLE İŞLEMLERİ (DESKTOP İLE BİREBİR PARİTE)
// ----------------------------------------------------
async function openSyncToCariModal(inv) {
  if (!inv || !inv.encrypted_id || isCariyeIslePasif(inv)) return
  cariConfirmInvoice.value = inv
  cariConfirmInfo.value = null
  cariCheckingLoading.value = true
  isCariConfirmModalOpen.value = true

  try {
    const res = await faturaApi.checkInvoiceCari(inv.encrypted_id)
    if (res.success && res.status === 'success') {
      cariConfirmInfo.value = res
    } else {
      showToast(res.message || 'Cari bilgisi alınamadı.', 'error')
      isCariConfirmModalOpen.value = false
    }
  } catch (err) {
    showToast(err.message || 'Cari kontrol edilirken hata oluştu.', 'error')
    isCariConfirmModalOpen.value = false
  } finally {
    cariCheckingLoading.value = false
  }
}

function isCariyeIslePasif(inv) {
  return inv.cariye_islendi == 1 || inv.ticari_yanit === 'RED' || /^RED/.test(String(inv.entegrator_durum_kodu || '').toUpperCase())
}

function cariyeIsleBaslik(inv) {
  if (inv.cariye_islendi == 1) return 'Cariye işlendi'
  if (isCariyeIslePasif(inv)) return 'Reddedilen fatura cariye işlenemez'
  return 'Cariye İşle'
}

async function confirmSyncToCari(force = false) {
  const inv = force ? duplicatePendingInvoice.value : cariConfirmInvoice.value
  if (!inv || !inv.encrypted_id) return

  if (force) {
    duplicateSubmitting.value = true
  } else {
    syncingInvoiceId.value = inv.encrypted_id
  }

  try {
    const res = await faturaApi.syncToCari(inv.encrypted_id, force, auth.state.csrfToken)
    if (res.status === 'warning_duplicate') {
      isCariConfirmModalOpen.value = false
      duplicatePendingInvoice.value = inv
      duplicateWarningData.value = {
        message: res.message,
        data: res.data || {},
      }
      isDuplicateModalOpen.value = true
    } else if (res.status === 'success' || res.success) {
      showToast(res.message || 'Fatura cari hareketlerine başarıyla işlendi.', 'success')
      inv.cariye_islendi = 1
      if (previewInvoice.value && (previewInvoice.value.encrypted_id === inv.encrypted_id || previewInvoice.value.id === inv.id)) {
        previewInvoice.value.cariye_islendi = 1
      }
      const inList = invoiceList.value.find(i => (i.encrypted_id || i.id) === (inv.encrypted_id || inv.id))
      if (inList) {
        inList.cariye_islendi = 1
      }
      const inRecentGiden = (dashData.value.recent_giden || []).find(i => (i.encrypted_id || i.id) === (inv.encrypted_id || inv.id))
      if (inRecentGiden) {
        inRecentGiden.cariye_islendi = 1
      }
      const inRecentGelen = (dashData.value.recent_gelen || []).find(i => (i.encrypted_id || i.id) === (inv.encrypted_id || inv.id))
      if (inRecentGelen) {
        inRecentGelen.cariye_islendi = 1
      }
      isCariConfirmModalOpen.value = false
      isDuplicateModalOpen.value = false
      duplicatePendingInvoice.value = null
      duplicateWarningData.value = null
    } else {
      showToast(res.message || 'İşlem gerçekleştirilemedi.', 'error')
    }
  } catch (err) {
    showToast(err.message || 'Cariye işlenirken hata oluştu.', 'error')
  } finally {
    syncingInvoiceId.value = null
    duplicateSubmitting.value = false
  }
}

// ----------------------------------------------------
// FATURA KESME SİHİRBAZI (WIZARD)
// ----------------------------------------------------
async function openNewInvoiceWizard(draftToEdit = null) {
  wizardStep.value = 1
  wizardEditId.value = draftToEdit ? draftToEdit.encrypted_id : null
  taxpayerInfo.value = null

  // Varsayılan form değerleri
  if (draftToEdit) {
    invoiceHeader.value = {
      cari_id: draftToEdit.cari_id_enc || '',
      alici_unvan: draftToEdit.alici_unvan || '',
      alici_vkn_tckn: draftToEdit.alici_vkn_tckn || '',
      alici_vergi_dairesi: draftToEdit.alici_vergi_dairesi || '',
      alici_adres: draftToEdit.alici_adres || '',
      alici_il: draftToEdit.alici_il || '',
      alici_ilce: draftToEdit.alici_ilce || '',
      alici_eposta: draftToEdit.alici_eposta || '',
      fatura_tarihi: draftToEdit.fatura_tarihi || new Date().toISOString().slice(0, 10),
      fatura_saati: new Date().toTimeString().slice(0, 5),
      belge_turu: draftToEdit.belge_turu || 'EFATURA',
      fatura_tipi: draftToEdit.fatura_tipi || 'SATIS',
      senaryo: draftToEdit.senaryo || 'TICARIFATURA',
      seri: draftToEdit.fatura_no ? draftToEdit.fatura_no.slice(0, 3) : '',
      para_birimi: draftToEdit.para_birimi || 'TRY',
      doviz_kuru: 1,
      notlar: draftToEdit.notlar || '',
    }
  } else {
    invoiceHeader.value = {
      cari_id: '',
      alici_unvan: '',
      alici_vkn_tckn: '',
      alici_vergi_dairesi: '',
      alici_adres: '',
      alici_il: '',
      alici_ilce: '',
      alici_eposta: '',
      fatura_tarihi: new Date().toISOString().slice(0, 10),
      fatura_saati: new Date().toTimeString().slice(0, 5),
      belge_turu: 'EFATURA',
      fatura_tipi: 'SATIS',
      senaryo: 'TICARIFATURA',
      seri: '',
      para_birimi: 'TRY',
      doviz_kuru: 1,
      notlar: '',
    }
    invoiceLines.value = [
      {
        mal_hizmet_id: '',
        aciklama: '',
        miktar: 1,
        birim: 'C62',
        birim_fiyat: 0,
        iskonto_orani: 0,
        kdv_orani: 20,
        kdv_tutari: 0,
        mal_hizmet_tutari: 0,
      },
    ]
  }

  isWizardOpen.value = true
  loadSerialsAndProducts()
}

async function loadSerialsAndProducts() {
  try {
    const [serialsRes, prodsRes] = await Promise.all([
      faturaApi.serials(),
      faturaApi.products(),
    ])
    if (serialsRes.success) {
      availableSerials.value = serialsRes.data || []
      if (!invoiceHeader.value.seri && availableSerials.value.length > 0) {
        invoiceHeader.value.seri = availableSerials.value[0].seri || ''
      }
    }
    if (prodsRes.success) {
      availableProducts.value = prodsRes.data || []
    }
  } catch (e) {}
}

async function searchCustomers() {
  if (!wizardCustomerSearch.value.trim()) {
    customerSearchResults.value = []
    return
  }
  customerSearching.value = true
  try {
    const res = await faturaApi.customers({ search: wizardCustomerSearch.value.trim() })
    if (res.success) {
      customerSearchResults.value = res.data || []
    }
  } catch (e) {
  } finally {
    customerSearching.value = false
  }
}

function selectCustomer(c) {
  invoiceHeader.value.cari_id = c.encrypted_id
  invoiceHeader.value.alici_unvan = c.CariAdi || c.firma || ''
  invoiceHeader.value.alici_vkn_tckn = c.vkn_tckn || ''
  invoiceHeader.value.alici_vergi_dairesi = c.vergi_dairesi || ''
  invoiceHeader.value.alici_adres = c.Adres || ''
  invoiceHeader.value.alici_il = c.il || ''
  invoiceHeader.value.alici_ilce = c.ilce || ''
  invoiceHeader.value.alici_eposta = c.Email || ''
  customerSearchResults.value = []
  wizardCustomerSearch.value = ''

  // Mükellef kontrolünü otomatik tetikle
  if (c.vkn_tckn) {
    checkTaxpayerStatus(c.vkn_tckn)
  }
}

async function checkTaxpayerStatus(vkn) {
  const v = (vkn || invoiceHeader.value.alici_vkn_tckn).trim()
  if (!v || v.length < 10) return

  checkingTaxpayer.value = true
  taxpayerInfo.value = null
  try {
    const res = await faturaApi.checkTaxpayer(v, auth.state.csrfToken)
    if (res.success && res.data) {
      taxpayerInfo.value = res.data
      if (res.data.is_taxpayer) {
        invoiceHeader.value.belge_turu = 'EFATURA'
        invoiceHeader.value.senaryo = 'TICARIFATURA'
      } else {
        invoiceHeader.value.belge_turu = 'EARSIV'
        invoiceHeader.value.senaryo = 'EARSIVFATURA'
      }
    }
  } catch (e) {
  } finally {
    checkingTaxpayer.value = false
  }
}

function addLine() {
  invoiceLines.value.push({
    mal_hizmet_id: '',
    aciklama: '',
    miktar: 1,
    birim: 'C62',
    birim_fiyat: 0,
    iskonto_orani: 0,
    kdv_orani: 20,
    kdv_tutari: 0,
    mal_hizmet_tutari: 0,
  })
  recalcLines()
}

function removeLine(idx) {
  if (invoiceLines.value.length <= 1) return
  invoiceLines.value.splice(idx, 1)
  recalcLines()
}

function onSelectProduct(line, item) {
  line.mal_hizmet_id = item.id
  line.aciklama = item.urun_adi
  line.birim = item.birim || 'C62'
  line.birim_fiyat = Number(item.satis_fiyati || 0)
  line.kdv_orani = Number(item.kdv_orani || 20)
  recalcLines()
}

async function recalcLines() {
  let matrah = 0
  let kdv = 0
  let iskonto = 0

  invoiceLines.value.forEach((l) => {
    const m = Number(l.miktar) || 0
    const bf = Number(l.birim_fiyat) || 0
    const iskO = Number(l.iskonto_orani) || 0
    const kdvO = Number(l.kdv_orani) || 0

    const brut = m * bf
    const diskTutar = brut * (iskO / 100)
    const net = brut - diskTutar
    const kdvTutar = net * (kdvO / 100)

    l.mal_hizmet_tutari = net
    l.kdv_tutari = kdvTutar

    matrah += net
    kdv += kdvTutar
    iskonto += diskTutar
  })

  wizardCalculation.value = {
    mal_hizmet_toplam: matrah,
    iskonto_toplam: iskonto,
    kdv_toplam: kdv,
    odenecek_tutar: matrah + kdv,
  }
}

function nextWizardStep() {
  if (wizardStep.value === 1) {
    if (!invoiceHeader.value.alici_unvan || !invoiceHeader.value.alici_vkn_tckn) {
      showToast('Lütfen Alıcı Ünvanı ve VKN/TCKN bilgilerini doldurun.', 'error')
      return
    }
  }
  if (wizardStep.value === 3) {
    const hasEmpty = invoiceLines.value.some((l) => !l.aciklama || !l.birim_fiyat || Number(l.birim_fiyat) <= 0)
    if (hasEmpty) {
      showToast('Lütfen tüm kalemlerin açıklama ve fiyatlarını girin.', 'error')
      return
    }
    recalcLines()
  }
  wizardStep.value++
}

function prevWizardStep() {
  if (wizardStep.value > 1) {
    wizardStep.value--
  }
}

async function saveWizardInvoice(sendDirect = false) {
  wizardSubmitting.value = true
  try {
    const payload = {
      header: invoiceHeader.value,
      lines: invoiceLines.value,
    }
    if (wizardEditId.value) {
      payload.invoice_id = wizardEditId.value
    }

    const res = await faturaApi.saveDraft(payload, auth.state.csrfToken)
    if (!res.success) {
      throw new Error(res.message || 'Taslak kaydedilemedi.')
    }

    if (sendDirect && res.invoice_id) {
      showToast('Taslak kaydedildi, GİB sistemine gönderiliyor...')
      const sendRes = await faturaApi.sendInvoice(res.invoice_id, auth.state.csrfToken)
      if (sendRes.success) {
        showToast(`Fatura başarıyla kesildi ve gönderildi! No: ${sendRes.fatura_no || '-'}`)
      }
    } else {
      showToast('Taslak fatura başarıyla kaydedildi.')
    }

    isWizardOpen.value = false
    loadCurrentTab(true)
  } catch (err) {
    showToast(err.message || 'Fatura kaydedilemedi.', 'error')
  } finally {
    wizardSubmitting.value = false
  }
}

// ----------------------------------------------------
// YAŞAM DÖNGÜSÜ
// ----------------------------------------------------
onMounted(() => {
  openFaturaDrawer()
  window.addEventListener('resize', onWindowResize)
  if (route.query.tab && invoiceTabPermissions.value[route.query.tab]) {
    faturaState.activeTab = route.query.tab
    activeTab.value = route.query.tab
  } else {
    activeTab.value = invoiceTabPermissions.value[activeTab.value] ? activeTab.value : firstAllowedInvoiceTab()
    faturaState.activeTab = activeTab.value
  }
  initDatesForPeriod('this_month')
  loadCurrentTab(true)
})

onBeforeUnmount(() => {
  closeFaturaDrawer()
  window.removeEventListener('resize', onWindowResize)
})
</script>

<template>
  <div class="ds-screen fatura-screen">
    <!-- Bildirim Rozeti (Toast) -->
    <Transition name="fade">
      <div
        v-if="feedbackMessage"
        class="fatura-toast"
        :class="{ 'fatura-toast--error': feedbackType === 'error' }"
      >
        <component :is="feedbackType === 'error' ? AlertCircle : CheckCircle2" :size="18" />
        <span>{{ feedbackMessage }}</span>
      </div>
    </Transition>

    <!-- 1. Üst Navigasyon & Aksiyonlar (TopBar) -->
    <TopBar
      title="E-Fatura & Finans"
      eyebrow="E-DÖNÜŞÜM & MALİ YÖNETİM"
      subtitle="E-Fatura, E-Arşiv ve KDV Yönetimi"
      :back-handler="handleTopBarBack"
    >
      <template #actions>
        <button
          type="button"
          class="clay-circle-btn"
          :class="{ 'is-spinning': refreshing }"
          aria-label="Yenile"
          title="Yenile"
          @click="refreshAll"
        >
          <RefreshCw :size="17" />
        </button>
        <button
          v-if="auth.state.permissions.invoice_create"
          type="button"
          class="clay-circle-btn"
          title="Fatura Kes"
          aria-label="Fatura Kes"
          @click="openNewInvoiceWizard()"
        >
          <Plus :size="18" />
        </button>
      </template>
    </TopBar>

    <!-- 2. Hızlı Dönem Filtreleri Çubuğu -->
    <section class="fatura-periods-bar" aria-label="Dönem filtreleri">
      <button
        type="button"
        class="fatura-period-pill"
        :class="{ 'is-active': currentPeriod === 'this_month' }"
        @click="setPeriod('this_month')"
      >
        Bu Ay
      </button>
      <button
        type="button"
        class="fatura-period-pill"
        :class="{ 'is-active': currentPeriod === 'last_month' }"
        @click="setPeriod('last_month')"
      >
        Geçen Ay
      </button>
      <button
        type="button"
        class="fatura-period-pill"
        :class="{ 'is-active': currentPeriod === 'last_3_months' }"
        @click="setPeriod('last_3_months')"
      >
        Son 3 Ay
      </button>
      <button
        type="button"
        class="fatura-period-pill"
        :class="{ 'is-active': currentPeriod === 'this_year' }"
        @click="setPeriod('this_year')"
      >
        {{ new Date().getFullYear() }} Yılı
      </button>
      <button
        type="button"
        class="fatura-period-pill"
        :class="{ 'is-active': currentPeriod === 'all' }"
        @click="setPeriod('all')"
      >
        Tümü
      </button>
      <button
        type="button"
        class="fatura-period-pill fatura-period-pill--custom"
        :class="{ 'is-active': currentPeriod === 'custom' }"
        @click="openPeriodModal"
      >
        <Calendar :size="14" />
        <span>{{ customPeriodLabel }}</span>
      </button>
    </section>

    <!-- Alt Menü Kapalıyken Yüzen Açma Butonu -->
    <Transition name="fade">
      <button
        v-if="!faturaState.drawerOpen"
        type="button"
        class="floating-fatura-dock-btn"
        title="Fatura Menüsünü Aç"
        aria-label="Fatura Menüsünü Aç"
        @click="openFaturaDrawer"
      >
        <ReceiptText :size="17" />
        <span>Menü</span>
      </button>
    </Transition>

    <!-- Hata Bildirimi -->
    <div v-if="errorMessage" class="fatura-error-box">
      <AlertTriangle :size="18" />
      <span>{{ errorMessage }}</span>
    </div>

    <!-- ============================================================== -->
    <!-- TAB 1: GENEL ÖZET & KDV DASHBOARD -->
    <!-- ============================================================== -->
    <main v-if="activeTab === 'ozet'" class="fatura-tab-content">
      <div v-if="loading" class="fatura-skeleton-grid">
        <div class="fatura-skeleton-card"></div>
        <div class="fatura-skeleton-card"></div>
        <div class="fatura-skeleton-card fatura-skeleton-card--lg"></div>
      </div>

      <template v-else>
        <!-- 1. Hero Obsidian Bento Kartı: Ticari İşlem Hacmi & Çift Yönlü Dağılım Grafiği -->
        <article class="fatura-hero-volume-card">
          <header class="fatura-hero-volume-card__header">
            <div class="fatura-hero-volume-card__title">
              <span class="fatura-hero-icon-box">
                <BarChart3 :size="16" />
              </span>
              <div>
                <span class="fatura-hero-tag">TİCARİ İŞLEM HACMİ</span>
                <span class="fatura-hero-subtitle">Satış & Alış Dağılım Analizi</span>
              </div>
            </div>
            <span
              class="fatura-balance-pill"
              :class="netTradeBalance >= 0 ? 'fatura-balance-pill--positive' : 'fatura-balance-pill--warning'"
            >
              <component :is="netTradeBalance >= 0 ? TrendingUp : TrendingDown" :size="13" />
              <span>{{ netTradeBalance >= 0 ? 'Pozitif Denge' : 'Alış Ağırlıklı' }}</span>
            </span>
          </header>

          <div class="fatura-hero-volume-main">
            <strong class="fatura-hero-volume-amount">{{ formatMoney(totalTradeVolume) }}</strong>
            <div class="fatura-hero-balance-row">
              <span class="fatura-hero-balance-label">Net Ticari Bakiye:</span>
              <strong
                class="fatura-hero-balance-val"
                :class="netTradeBalance >= 0 ? 'text-emerald' : 'text-amber'"
              >
                {{ (netTradeBalance >= 0 ? '+' : '') + formatMoney(netTradeBalance) }}
              </strong>
            </div>
          </div>

          <!-- Çift Yönlü Segmentli Dağılım Grafiği (Segmented Volume Bar) -->
          <div class="fatura-chart-container">
            <div
              class="fatura-segmented-track"
              role="progressbar"
              :aria-valuenow="salesRatio"
              aria-valuemin="0"
              aria-valuemax="100"
            >
              <div
                class="fatura-seg-fill fatura-seg-fill--sales"
                :style="{ width: `${salesRatio}%` }"
                :title="`Satış: %${salesRatio}`"
              >
                <span v-if="salesRatio >= 18" class="fatura-seg-label">%{{ salesRatio }}</span>
              </div>
              <div
                class="fatura-seg-fill fatura-seg-fill--purchase"
                :style="{ width: `${purchaseRatio}%` }"
                :title="`Alış: %${purchaseRatio}`"
              >
                <span v-if="purchaseRatio >= 18" class="fatura-seg-label">%{{ purchaseRatio }}</span>
              </div>
            </div>

            <!-- Grafik Lejantı (Legend) -->
            <div class="fatura-chart-legend">
              <div class="fatura-legend-item" @click="switchTab('giden')">
                <span class="fatura-legend-dot fatura-legend-dot--sales"></span>
                <div class="fatura-legend-info">
                  <div class="fatura-legend-header">
                    <span class="fatura-legend-title">Giden Satış</span>
                    <b class="fatura-legend-pct text-emerald">%{{ salesRatio }}</b>
                  </div>
                  <strong class="fatura-legend-val">{{ formatMoney(dashData.giden?.toplam_tutar) }}</strong>
                  <small class="fatura-legend-count">{{ dashData.giden?.toplam_adet || 0 }} adet fatura</small>
                </div>
              </div>

              <div class="fatura-legend-item" @click="switchTab('gelen')">
                <span class="fatura-legend-dot fatura-legend-dot--purchase"></span>
                <div class="fatura-legend-info">
                  <div class="fatura-legend-header">
                    <span class="fatura-legend-title">Gelen Alış</span>
                    <b class="fatura-legend-pct text-sky">%{{ purchaseRatio }}</b>
                  </div>
                  <strong class="fatura-legend-val">{{ formatMoney(dashData.gelen?.toplam_tutar) }}</strong>
                  <small class="fatura-legend-count">{{ dashData.gelen?.toplam_adet || 0 }} adet fatura</small>
                </div>
              </div>
            </div>
          </div>
        </article>

        <!-- 2'li Özet Kartları: Giden & Gelen -->
        <div class="fatura-kpi-grid">
          <!-- Giden (Satış) -->
          <article class="fatura-kpi-card fatura-kpi-card--outgoing" @click="switchTab('giden')">
            <header>
              <span class="fatura-kpi-tag">GİDEN (SATIŞ)</span>
              <span class="fatura-kpi-icon fatura-kpi-icon--emerald">
                <Send :size="16" />
              </span>
            </header>
            <div class="fatura-kpi-main">
              <strong class="fatura-kpi-amount">{{ formatMoney(dashData.giden?.toplam_tutar) }}</strong>
              <small class="fatura-kpi-count">{{ dashData.giden?.toplam_adet || 0 }} Fatura</small>
            </div>
            <div class="fatura-kpi-sub">
              <div>
                <span>Matrah:</span>
                <b>{{ formatMoney(dashData.giden?.toplam_matrah) }}</b>
              </div>
              <div>
                <span>KDV:</span>
                <b class="text-emerald">{{ formatMoney(dashData.giden?.toplam_kdv) }}</b>
              </div>
            </div>
            <footer>
              <span>Satış Listesi</span>
              <ChevronRight :size="14" />
            </footer>
          </article>

          <!-- Gelen (Alış) -->
          <article class="fatura-kpi-card fatura-kpi-card--incoming" @click="switchTab('gelen')">
            <header>
              <span class="fatura-kpi-tag">GELEN (ALIŞ)</span>
              <span class="fatura-kpi-icon fatura-kpi-icon--sky">
                <Inbox :size="16" />
              </span>
            </header>
            <div class="fatura-kpi-main">
              <strong class="fatura-kpi-amount">{{ formatMoney(dashData.gelen?.toplam_tutar) }}</strong>
              <small class="fatura-kpi-count">{{ dashData.gelen?.toplam_adet || 0 }} Fatura</small>
            </div>
            <div class="fatura-kpi-sub">
              <div>
                <span>Matrah:</span>
                <b>{{ formatMoney(dashData.gelen?.toplam_matrah) }}</b>
              </div>
              <div>
                <span>KDV:</span>
                <b class="text-sky">{{ formatMoney(dashData.gelen?.toplam_kdv) }}</b>
              </div>
            </div>
            <footer>
              <span>Alış Listesi</span>
              <ChevronRight :size="14" />
            </footer>
          </article>
        </div>

        <!-- Net KDV Durumu Vurgulu Kartı & Karşılama İlerleme Skalası -->
        <article
          class="fatura-kdv-card"
          :class="dashData.kdv?.net_kdv > 0 ? 'fatura-kdv-card--payable' : 'fatura-kdv-card--devreden'"
        >
          <header>
            <div class="fatura-kdv-card__badge">
              <component :is="dashData.kdv?.net_kdv > 0 ? AlertCircle : CheckCircle2" :size="18" />
              <strong>
                {{ dashData.kdv?.net_kdv > 0 ? 'ÖDENECEK KDV (MALİYE)' : 'SONRAKİ DÖNEME DEVREDEN KDV' }}
              </strong>
            </div>
            <span class="fatura-kdv-status">Dönem Net Durumu</span>
          </header>

          <div class="fatura-kdv-amount-row">
            <div class="fatura-kdv-amount">
              {{ formatMoney(dashData.kdv?.net_kdv > 0 ? dashData.kdv?.odenecek_kdv : dashData.kdv?.devreden_kdv) }}
            </div>
            <span
              class="fatura-kdv-indicator-pill"
              :class="dashData.kdv?.net_kdv > 0 ? 'fatura-kdv-indicator-pill--danger' : 'fatura-kdv-indicator-pill--emerald'"
            >
              {{ dashData.kdv?.net_kdv > 0 ? 'Maliye Ödemesi' : 'Devreden Bakiye' }}
            </span>
          </div>

          <!-- KDV Karşılama İlerleme Skalası (Coverage Gauge Bar) -->
          <div class="fatura-kdv-gauge-wrap">
            <div class="fatura-kdv-gauge-meta">
              <span class="fatura-kdv-gauge-title">Alış KDV Karşılama Oranı</span>
              <strong class="fatura-kdv-gauge-pct" :class="kdvCoverageRatio >= 100 ? 'text-emerald' : 'text-amber'">
                %{{ kdvCoverageRatio }}
              </strong>
            </div>
            <div class="fatura-kdv-gauge-track">
              <div
                class="fatura-kdv-gauge-fill"
                :class="kdvCoverageRatio >= 100 ? 'fatura-kdv-gauge-fill--full' : 'fatura-kdv-gauge-fill--partial'"
                :style="{ width: `${kdvCoverageRatio}%` }"
              ></div>
            </div>
            <p class="fatura-kdv-gauge-desc">
              {{
                kdvCoverageRatio >= 100
                  ? 'Alış KDV\'niz satış KDV\'sini tamamen karşılıyor ve devreden KDV oluşturuyor.'
                  : `Alış KDV\'niz satış KDV\'sinin %${kdvCoverageRatio}'ünü dengeliyor; kalan tutar Maliye yükümlülüğüdür.`
              }}
            </p>
          </div>

          <div class="fatura-kdv-breakdown">
            <div class="fatura-kdv-box fatura-kdv-box--hesaplanan">
              <div class="fatura-kdv-box__icon"><ArrowUpRight :size="14" /></div>
              <div>
                <span>Hesaplanan KDV (Satış):</span>
                <b>{{ formatMoney(dashData.kdv?.hesaplanan_kdv) }}</b>
              </div>
            </div>
            <div class="fatura-kdv-box fatura-kdv-box--indirilecek">
              <div class="fatura-kdv-box__icon"><ArrowDownLeft :size="14" /></div>
              <div>
                <span>İndirilecek KDV (Alış):</span>
                <b>{{ formatMoney(dashData.kdv?.indirilecek_kdv) }}</b>
              </div>
            </div>
          </div>
        </article>

        <!-- 3'lü Finansal Analiz & Ortalama Metrikleri -->
        <section class="fatura-metrics-strip" aria-label="Finansal ortalamalar">
          <div class="fatura-metric-cell">
            <div class="fatura-metric-cell__icon bg-emerald-soft text-emerald">
              <TrendingUp :size="16" />
            </div>
            <span class="fatura-metric-cell__label">Ort. Satış Faturası</span>
            <strong class="fatura-metric-cell__value">{{ formatMoney(avgInvoiceOutgoing) }}</strong>
          </div>

          <div class="fatura-metric-cell">
            <div class="fatura-metric-cell__icon bg-sky-soft text-sky">
              <TrendingDown :size="16" />
            </div>
            <span class="fatura-metric-cell__label">Ort. Alış Faturası</span>
            <strong class="fatura-metric-cell__value">{{ formatMoney(avgInvoiceIncoming) }}</strong>
          </div>

          <div class="fatura-metric-cell">
            <div class="fatura-metric-cell__icon bg-primary-soft text-primary">
              <Percent :size="16" />
            </div>
            <span class="fatura-metric-cell__label">Efektif KDV Oranı</span>
            <strong class="fatura-metric-cell__value">%{{ kdvEffectiveRate }}</strong>
          </div>
        </section>

        <!-- Hızlı İşlemler Kartları Grid -->
        <section class="fatura-quick-grid">
          <button type="button" class="fatura-quick-item" @click="openNewInvoiceWizard()">
            <span class="fatura-quick-item__icon bg-primary-soft text-primary">
              <Plus :size="20" />
            </span>
            <strong>Fatura Kes</strong>
            <small>Yeni satış faturası</small>
          </button>

          <button type="button" class="fatura-quick-item" @click="switchTab('taslak')">
            <span class="fatura-quick-item__icon bg-amber-soft text-amber">
              <Edit3 :size="20" />
            </span>
            <strong>Taslaklar</strong>
            <small>Bekleyen kayıtlar</small>
          </button>

          <button type="button" class="fatura-quick-item" @click="switchTab('giden')">
            <span class="fatura-quick-item__icon bg-emerald-soft text-emerald">
              <Send :size="20" />
            </span>
            <strong>Giden Faturalar</strong>
            <small>EDM/GİB Satışları</small>
          </button>

          <button type="button" class="fatura-quick-item" @click="switchTab('gelen')">
            <span class="fatura-quick-item__icon bg-sky-soft text-sky">
              <Inbox :size="20" />
            </span>
            <strong>Gelen Faturalar</strong>
            <small>Tedarikçi alışları</small>
          </button>
        </section>

        <!-- Son Faturalar Akışı -->
        <section class="fatura-recent-section">
          <header class="fatura-recent-header">
            <h2>Son Faturalar</h2>
            <div class="fatura-recent-pills">
              <button
                type="button"
                :class="{ 'is-active': recentSubTab === 'gelen' }"
                @click="recentSubTab = 'gelen'"
              >
                Gelen (Alış)
              </button>
              <button
                type="button"
                :class="{ 'is-active': recentSubTab === 'giden' }"
                @click="recentSubTab = 'giden'"
              >
                Giden (Satış)
              </button>
            </div>
          </header>

          <!-- Gelen Son Faturalar -->
          <div v-if="recentSubTab === 'gelen'" class="fatura-recent-list">
            <div v-if="!dashData.recent_gelen || dashData.recent_gelen.length === 0" class="fatura-empty">
              Henüz gelen fatura bulunmuyor
            </div>
            <article
              v-for="rg in dashData.recent_gelen"
              :key="rg.encrypted_id || rg.id"
              class="fatura-row-card"
              @click="openPreview(rg.encrypted_id, rg.fatura_no, rg)"
            >
              <div class="fatura-row-card__header">
                <div class="fatura-row-card__meta">
                  <span class="fatura-no-tag fatura-no-tag--sky">{{ rg.fatura_no || 'Taslak' }}</span>
                  <span class="fatura-date-pill">
                    <Calendar :size="12" />
                    <span>{{ rg.fatura_tarihi_fmt || formatDate(rg.fatura_tarihi) }}</span>
                  </span>
                </div>
                <span
                  class="fatura-badge"
                  :class="rg.ticari_yanit === 'KABUL' ? 'fatura-badge--success' : (rg.ticari_yanit === 'RED' ? 'fatura-badge--danger' : 'fatura-badge--warning')"
                >
                  <span class="fatura-badge-dot"></span>
                  <span>{{ rg.ticari_yanit === 'KABUL' ? 'Kabul Edildi' : (rg.ticari_yanit === 'RED' ? 'Reddedildi' : 'Bekliyor') }}</span>
                </span>
              </div>

              <div class="fatura-row-card__body">
                <h3 class="fatura-row-card__customer" :title="rg.alici_unvan || 'Tedarikçi'">
                  {{ rg.alici_unvan || 'Tedarikçi' }}
                </h3>
              </div>

              <div class="fatura-row-card__footer">
                <div class="fatura-row-card__amount-wrap">
                  <span class="fatura-amount-label">Ödenecek Tutar</span>
                  <strong class="fatura-amount-value">
                    {{ formatInvoiceMoney(rg.odenecek_tutar, rg.para_birimi) }}
                  </strong>
                </div>
                <div class="fatura-row-card__actions" @click.stop>
                  <!-- Cariye İşle Butonu / Rozeti (Özet Kartı) -->
                  <template v-if="rg.entegrator_durum_kodu !== 'IPTAL'">
                    <button
                      type="button"
                      class="fatura-icon-action-btn fatura-icon-action-btn--sync"
                      :class="{ 'is-loading': syncingInvoiceId === rg.encrypted_id }"
                      :disabled="syncingInvoiceId === rg.encrypted_id || isCariyeIslePasif(rg)"
                      :title="cariyeIsleBaslik(rg)"
                      :aria-label="cariyeIsleBaslik(rg)"
                      @click="openSyncToCariModal(rg)"
                    >
                      <ArrowLeftRight v-if="syncingInvoiceId !== rg.encrypted_id" :size="15" />
                      <RefreshCw v-else :size="14" class="is-spinning" />
                    </button>
                  </template>

                  <!-- Fatura Gör (İkon Buton) -->
                  <button
                    type="button"
                    class="fatura-icon-action-btn fatura-icon-action-btn--view"
                    title="Faturayı Görüntüle"
                    aria-label="Faturayı Görüntüle"
                    @click="openPreview(rg.encrypted_id, rg.fatura_no, rg)"
                  >
                    <Eye :size="15" />
                  </button>
                </div>
              </div>
            </article>
          </div>

          <!-- Giden Son Faturalar -->
          <div v-if="recentSubTab === 'giden'" class="fatura-recent-list">
            <div v-if="!dashData.recent_giden || dashData.recent_giden.length === 0" class="fatura-empty">
              Henüz giden fatura bulunmuyor
            </div>
            <article
              v-for="rg in dashData.recent_giden"
              :key="rg.encrypted_id || rg.id"
              class="fatura-row-card"
              @click="openPreview(rg.encrypted_id, rg.fatura_no, rg)"
            >
              <div class="fatura-row-card__header">
                <div class="fatura-row-card__meta">
                  <span class="fatura-no-tag fatura-no-tag--emerald">{{ rg.fatura_no || 'Taslak' }}</span>
                  <span class="fatura-date-pill">
                    <Calendar :size="12" />
                    <span>{{ rg.fatura_tarihi_fmt || formatDate(rg.fatura_tarihi) }}</span>
                  </span>
                </div>
                <span
                  class="fatura-badge"
                  :class="rg.entegrator_durum_kodu === 'ONAYLANDI' ? 'fatura-badge--success' : (rg.entegrator_durum_kodu === 'HATALI' ? 'fatura-badge--danger' : 'fatura-badge--neutral')"
                >
                  <span class="fatura-badge-dot"></span>
                  <span>{{ rg.entegrator_durum_kodu === 'ONAYLANDI' ? 'Onaylandı' : (rg.entegrator_durum_kodu || 'İletildi') }}</span>
                </span>
              </div>

              <div class="fatura-row-card__body">
                <h3 class="fatura-row-card__customer" :title="rg.alici_unvan || 'Müşteri'">
                  {{ rg.alici_unvan || 'Müşteri' }}
                </h3>
              </div>

              <div class="fatura-row-card__footer">
                <div class="fatura-row-card__amount-wrap">
                  <span class="fatura-amount-label">Ödenecek Tutar</span>
                  <strong class="fatura-amount-value">
                    {{ formatInvoiceMoney(rg.odenecek_tutar, rg.para_birimi) }}
                  </strong>
                </div>
                <div class="fatura-row-card__actions" @click.stop>
                  <!-- Cariye İşle Butonu / Rozeti (Özet Kartı) -->
                  <template v-if="rg.entegrator_durum_kodu !== 'IPTAL'">
                    <button
                      type="button"
                      class="fatura-icon-action-btn fatura-icon-action-btn--sync"
                      :class="{ 'is-loading': syncingInvoiceId === rg.encrypted_id }"
                      :disabled="syncingInvoiceId === rg.encrypted_id || isCariyeIslePasif(rg)"
                      :title="cariyeIsleBaslik(rg)"
                      :aria-label="cariyeIsleBaslik(rg)"
                      @click="openSyncToCariModal(rg)"
                    >
                      <ArrowLeftRight v-if="syncingInvoiceId !== rg.encrypted_id" :size="15" />
                      <RefreshCw v-else :size="14" class="is-spinning" />
                    </button>
                  </template>

                  <!-- Fatura Gör (İkon Buton) -->
                  <button
                    type="button"
                    class="fatura-icon-action-btn fatura-icon-action-btn--view"
                    title="Faturayı Görüntüle"
                    aria-label="Faturayı Görüntüle"
                    @click="openPreview(rg.encrypted_id, rg.fatura_no, rg)"
                  >
                    <Eye :size="15" />
                  </button>
                </div>
              </div>
            </article>
          </div>
        </section>
      </template>
    </main>

    <!-- ============================================================== -->
    <!-- TAB 2, 3, 4: LİSTE GÖRÜNÜMLERİ (GİDEN, GELEN, TASLAK) -->
    <!-- ============================================================== -->
    <main v-else class="fatura-tab-content">
      <!-- Minimal Bento Özet Kartı (Giden / Gelen / Taslak) -->
      <section v-if="listSummary.toplam_adet" class="fatura-mini-bento-strip">
        <div class="fatura-mini-strip__main">
          <div class="fatura-mini-strip__info">
            <span
              class="fatura-mini-strip__icon"
              :class="activeTab === 'giden' ? 'fatura-mini-strip__icon--emerald' : (activeTab === 'gelen' ? 'fatura-mini-strip__icon--sky' : 'fatura-mini-strip__icon--amber')"
            >
              <component :is="activeTab === 'giden' ? Send : (activeTab === 'gelen' ? Inbox : Edit3)" :size="15" />
            </span>
            <div class="fatura-mini-strip__text">
              <span class="fatura-mini-strip__tag">
                {{ activeTab === 'giden' ? 'GİDEN SATIŞ' : (activeTab === 'gelen' ? 'GELEN ALIŞ' : 'BEKLEYEN TASLAKLAR') }}
              </span>
              <strong class="fatura-mini-strip__amount">{{ formatMoney(listSummary.toplam_tutar) }}</strong>
            </div>
          </div>

          <div class="fatura-mini-strip__badges">
            <span class="fatura-mini-badge fatura-mini-badge--count">
              {{ listSummary.toplam_adet || 0 }} Fatura
            </span>

            <!-- Giden Sekmesi Durum Kırılımları -->
            <template v-if="activeTab === 'giden'">
              <span v-if="listSummary.onaylanan_adet" class="fatura-mini-badge fatura-mini-badge--success" title="Onaylı Fatura">
                <span class="fatura-badge-dot"></span>
                <span>{{ listSummary.onaylanan_adet }} Onaylı</span>
              </span>
              <span v-if="listSummary.bekleyen_adet" class="fatura-mini-badge fatura-mini-badge--warning" title="Kuyrukta / Bekleyen">
                <span class="fatura-badge-dot"></span>
                <span>{{ listSummary.bekleyen_adet }} Bekliyor</span>
              </span>
              <span v-if="listSummary.iptal_adet" class="fatura-mini-badge fatura-mini-badge--danger" title="İptal / Hatalı">
                <span>{{ listSummary.iptal_adet }} İptal</span>
              </span>
            </template>

            <!-- Gelen Sekmesi Durum Kırılımları -->
            <template v-else-if="activeTab === 'gelen'">
              <span v-if="listSummary.kabul_adet" class="fatura-mini-badge fatura-mini-badge--success" title="Kabul Edilen">
                <span class="fatura-badge-dot"></span>
                <span>{{ listSummary.kabul_adet }} Kabul</span>
              </span>
              <span v-if="listSummary.bekleyen_adet" class="fatura-mini-badge fatura-mini-badge--warning" title="Yanıt Bekleyen">
                <span class="fatura-badge-dot"></span>
                <span>{{ listSummary.bekleyen_adet }} Yanıt Bekliyor</span>
              </span>
              <span v-if="listSummary.red_adet" class="fatura-mini-badge fatura-mini-badge--danger" title="Reddedilen">
                <span>{{ listSummary.red_adet }} Red</span>
              </span>
            </template>

            <!-- Taslak Sekmesi -->
            <template v-else-if="activeTab === 'taslak'">
              <span v-if="listSummary.efatura_adet" class="fatura-mini-badge fatura-mini-badge--neutral">
                {{ listSummary.efatura_adet }} E-Fatura
              </span>
              <span v-if="listSummary.earsiv_adet" class="fatura-mini-badge fatura-mini-badge--neutral">
                {{ listSummary.earsiv_adet }} E-Arşiv
              </span>
            </template>
          </div>
        </div>

        <!-- Kart Altındaki Minimal Dağılım Çizgisi (3px) -->
        <div v-if="listSummary.toplam_adet" class="fatura-mini-strip__track">
          <template v-if="activeTab === 'giden'">
            <div
              class="fatura-mini-strip__fill bg-emerald"
              :style="{ width: `${Math.round(((listSummary.onaylanan_adet || 0) / listSummary.toplam_adet) * 100)}%` }"
            ></div>
            <div
              class="fatura-mini-strip__fill bg-amber"
              :style="{ width: `${Math.round(((listSummary.bekleyen_adet || 0) / listSummary.toplam_adet) * 100)}%` }"
            ></div>
          </template>
          <template v-else-if="activeTab === 'gelen'">
            <div
              class="fatura-mini-strip__fill bg-emerald"
              :style="{ width: `${Math.round(((listSummary.kabul_adet || 0) / listSummary.toplam_adet) * 100)}%` }"
            ></div>
            <div
              class="fatura-mini-strip__fill bg-amber"
              :style="{ width: `${Math.round(((listSummary.bekleyen_adet || 0) / listSummary.toplam_adet) * 100)}%` }"
            ></div>
            <div
              class="fatura-mini-strip__fill bg-danger"
              :style="{ width: `${Math.round(((listSummary.red_adet || 0) / listSummary.toplam_adet) * 100)}%` }"
            ></div>
          </template>
        </div>
      </section>

      <!-- Arama ve Filtre Grubu -->
      <div class="fatura-search-group">
        <!-- Tekil Akıllı Arama Çubuğu -->
        <div class="fatura-search-box fatura-search-box--unified">
          <Search :size="17" class="fatura-search-box__icon" />
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Fatura No, Cari, VKN veya Ürün/Kalem Ara..."
            autocomplete="off"
          />
          <button
            v-if="searchQuery"
            type="button"
            class="fatura-search-clear-btn"
            aria-label="Aramayı Temizle"
            title="Aramayı Temizle"
            @click="searchQuery = ''"
          >
            <X :size="15" />
          </button>
        </div>

        <!-- Hızlı Durum Filtreleri (Giden) -->
        <div v-if="activeTab === 'giden'" class="fatura-filter-pills">
          <button
            type="button"
            :class="{ 'is-active': statusFilter === '' }"
            @click="statusFilter = ''"
          >
            <span>Tümü</span>
            <span v-if="listSummary.toplam_adet" class="fatura-pill-num">{{ listSummary.toplam_adet }}</span>
          </button>
          <button
            type="button"
            :class="{ 'is-active': statusFilter === 'ONAYLANDI' }"
            @click="statusFilter = 'ONAYLANDI'"
          >
            <span>Onaylı</span>
            <span v-if="listSummary.onaylanan_adet" class="fatura-pill-num">{{ listSummary.onaylanan_adet }}</span>
          </button>
          <button
            type="button"
            :class="{ 'is-active': statusFilter === 'BEKLIYOR' }"
            @click="statusFilter = 'BEKLIYOR'"
          >
            <span>Bekleyen</span>
            <span v-if="listSummary.bekleyen_adet" class="fatura-pill-num">{{ listSummary.bekleyen_adet }}</span>
          </button>
          <button
            type="button"
            :class="{ 'is-active': statusFilter === 'HATALI' }"
            @click="statusFilter = 'HATALI'"
          >
            <span>Hatalı</span>
          </button>
        </div>

        <!-- Hızlı Durum Filtreleri (Gelen) -->
        <div v-if="activeTab === 'gelen'" class="fatura-filter-pills">
          <button
            type="button"
            :class="{ 'is-active': responseFilter === '' }"
            @click="responseFilter = ''"
          >
            <span>Tümü</span>
            <span v-if="listSummary.toplam_adet" class="fatura-pill-num">{{ listSummary.toplam_adet }}</span>
          </button>
          <button
            type="button"
            :class="{ 'is-active': responseFilter === 'BEKLIYOR' }"
            @click="responseFilter = 'BEKLIYOR'"
          >
            <span>Yanıt Bekleyen</span>
            <span v-if="listSummary.bekleyen_adet" class="fatura-pill-num">{{ listSummary.bekleyen_adet }}</span>
          </button>
          <button
            type="button"
            :class="{ 'is-active': responseFilter === 'KABUL' }"
            @click="responseFilter = 'KABUL'"
          >
            <span>Kabul</span>
            <span v-if="listSummary.kabul_adet" class="fatura-pill-num">{{ listSummary.kabul_adet }}</span>
          </button>
          <button
            type="button"
            :class="{ 'is-active': responseFilter === 'RED' }"
            @click="responseFilter = 'RED'"
          >
            <span>Red</span>
            <span v-if="listSummary.red_adet" class="fatura-pill-num">{{ listSummary.red_adet }}</span>
          </button>
        </div>
      </div>

      <!-- Liste Durumları (Loading & Empty) -->
      <div v-if="listLoading" class="fatura-loading-box">
        <RefreshCw :size="24" class="is-spinning text-primary" />
        <span>Faturalar yükleniyor...</span>
      </div>

      <!-- UI/UX Pro Max Bento Boş Durum Kartı -->
      <div v-else-if="invoiceList.length === 0" class="fatura-empty-bento">
        <div
          class="fatura-empty-bento__icon-wrap"
          :class="activeTab === 'taslak' ? 'fatura-empty-bento__icon-wrap--amber' : (activeTab === 'gelen' ? 'fatura-empty-bento__icon-wrap--sky' : '')"
        >
          <component :is="activeTab === 'taslak' ? Edit3 : (activeTab === 'gelen' ? Inbox : Receipt)" :size="30" />
        </div>

        <h3 class="fatura-empty-bento__title">
          {{ activeTab === 'taslak' ? 'Henüz Taslak Fatura Yok' : (searchQuery ? 'Aramanıza Uygun Fatura Bulunamadı' : 'Fatura Bulunamadı') }}
        </h3>

        <p class="fatura-empty-bento__desc">
          <template v-if="activeTab === 'taslak'">
            Oluşturduğunuz faturaları GİB/EDM'e göndermeden önce burada taslak olarak kaydedebilir, dilediğiniz an düzenleyip tek dokunuşla gönderebilirsiniz.
          </template>
          <template v-else-if="searchQuery">
            "<strong>{{ searchQuery }}</strong>" aramasına uyan kayıt bulunamadı. Fatura numarası, müşteri ünvanı veya ürün adını kontrol edebilirsiniz.
          </template>
          <template v-else>
            Seçilen dönem ve filtreleme kriterlerine uygun herhangi bir fatura kaydı bulunmuyor.
          </template>
        </p>

        <!-- Taslak / Fatura Oluşturma Aksiyonu -->
        <button
          type="button"
          class="fatura-empty-bento__cta-btn"
          @click="openNewInvoiceWizard()"
        >
          <Plus :size="18" />
          <span>{{ activeTab === 'taslak' ? 'Yeni Taslak Fatura Oluştur' : 'Yeni Fatura Kes' }}</span>
        </button>

        <!-- Taslaklara Özel Güven & İpucu Rozetleri -->
        <div v-if="activeTab === 'taslak'" class="fatura-empty-bento__badges">
          <span class="fatura-tip-badge">
            <CheckCircle2 :size="13" class="text-emerald" />
            <span>Taslaklar resmiyet taşımaz</span>
          </span>
          <span class="fatura-tip-badge">
            <Sparkles :size="13" class="text-amber" />
            <span>İstediğiniz zaman düzenleyebilirsiniz</span>
          </span>
        </div>
      </div>

      <!-- Fatura Kartları Listesi -->
      <div v-else class="fatura-cards-list">
        <article
          v-for="inv in invoiceList"
          :key="inv.encrypted_id || inv.id"
          class="fatura-card"
          @click="openPreview(inv.encrypted_id, inv.fatura_no, inv)"
        >
          <!-- Kart Üst Başlık (No, Belge Türü & Durum) -->
          <div class="fatura-card__header">
            <div class="fatura-card__badge-group">
              <span class="fatura-no-tag" :class="activeTab === 'giden' ? 'fatura-no-tag--emerald' : 'fatura-no-tag--sky'">
                {{ inv.fatura_no || 'Taslak' }}
              </span>
              <span class="fatura-type-tag">
                {{ inv.belge_turu || (activeTab === 'gelen' ? 'GELEN' : 'EFATURA') }}
              </span>
            </div>

            <!-- Durum Rozeti -->
            <div class="fatura-card__status">
              <template v-if="activeTab === 'gelen'">
                <span
                  class="fatura-badge"
                  :class="inv.ticari_yanit === 'KABUL' ? 'fatura-badge--success' : (inv.ticari_yanit === 'RED' ? 'fatura-badge--danger' : 'fatura-badge--warning')"
                >
                  <span class="fatura-badge-dot"></span>
                  <span>{{ inv.ticari_yanit === 'KABUL' ? 'Kabul Edildi' : (inv.ticari_yanit === 'RED' ? 'Reddedildi' : 'Yanıt Bekliyor') }}</span>
                </span>
              </template>
              <template v-else-if="activeTab === 'giden'">
                <span
                  class="fatura-badge"
                  :class="inv.entegrator_durum_kodu === 'ONAYLANDI' ? 'fatura-badge--success' : (inv.entegrator_durum_kodu === 'HATALI' ? 'fatura-badge--danger' : 'fatura-badge--warning')"
                >
                  <span class="fatura-badge-dot"></span>
                  <span>{{ inv.entegrator_durum_kodu === 'ONAYLANDI' ? 'Onaylandı' : (inv.entegrator_durum_kodu || 'İletildi') }}</span>
                </span>
              </template>
              <template v-else>
                <span class="fatura-badge fatura-badge--warning">
                  <span class="fatura-badge-dot"></span>
                  <span>Taslak</span>
                </span>
              </template>
            </div>
          </div>

          <!-- Alıcı / Tedarikçi Detay -->
          <div class="fatura-card__body">
            <h3 class="fatura-customer-name" :title="inv.alici_unvan || 'İsimsiz Cari'">
              {{ inv.alici_unvan || 'İsimsiz Cari' }}
            </h3>
            <div class="fatura-customer-meta">
              <span class="fatura-meta-pill">
                <Calendar :size="12" />
                <span>{{ formatDate(inv.fatura_tarihi) }}</span>
              </span>
              <span v-if="inv.alici_vkn_tckn" class="fatura-meta-vkn">
                VKN: {{ inv.alici_vkn_tckn }}
              </span>
            </div>
          </div>

          <!-- Kalemler Özeti -->
          <div v-if="inv.kalemler_ozet" class="fatura-card__items-preview">
            <Package :size="13" class="text-purple" style="flex-shrink: 0;" />
            <span :title="inv.kalemler_ozet">{{ inv.kalemler_ozet }}</span>
          </div>

          <!-- Kart Altı (Tutar & Aksiyon Butonları) -->
          <div class="fatura-card__footer" @click.stop>
            <div class="fatura-card__amount-box">
              <span class="fatura-amount-label">Ödenecek Tutar</span>
              <strong class="fatura-amount-value">
                {{ formatInvoiceMoney(inv.odenecek_tutar_raw ?? inv.odenecek_tutar, inv.para_birimi) }}
              </strong>
            </div>

            <div class="fatura-card__actions">
              <!-- Tahsilat Rozeti (Giden faturalarda) -->
              <span
                v-if="activeTab === 'giden' && inv.tahsilat_durumu"
                class="fatura-payment-tag"
                :class="inv.tahsilat_durumu === 'ODENDI' ? 'is-paid' : (inv.tahsilat_durumu === 'KISMI_ODENDI' ? 'is-partial' : '')"
              >
                {{ inv.tahsilat_durumu === 'ODENDI' ? '✓ Ödendi' : (inv.tahsilat_durumu === 'KISMI_ODENDI' ? '◑ Kısmi' : '○ Bekliyor') }}
              </span>

              <!-- Gelen Ticari Yanıt Butonu -->
              <button
                v-if="activeTab === 'gelen' && (!inv.ticari_yanit || inv.ticari_yanit === 'BEKLIYOR')"
                type="button"
                class="fatura-action-btn fatura-action-btn--primary"
                @click="openResponseModal(inv)"
              >
                <Check :size="13" />
                <span>Yanıt Ver</span>
              </button>

              <!-- Taslak Butonları: Sil, Düzenle, Gönder -->
              <template v-if="activeTab === 'taslak'">
                <button
                  type="button"
                  class="fatura-icon-action-btn fatura-icon-action-btn--danger"
                  title="Taslağı Sil"
                  @click="deleteDraft(inv)"
                >
                  <Trash2 :size="15" />
                </button>
                <button
                  type="button"
                  class="fatura-icon-action-btn"
                  title="Taslağı Düzenle"
                  aria-label="Taslağı Düzenle"
                  @click="openNewInvoiceWizard(inv)"
                >
                  <Edit3 :size="15" />
                </button>
                <button
                  type="button"
                  class="fatura-icon-action-btn fatura-icon-action-btn--primary"
                  title="GİB'e Gönder"
                  aria-label="GİB'e Gönder"
                  @click="sendDraft(inv)"
                >
                  <Send :size="14" />
                </button>
              </template>

              <!-- Paylaş, Cariye İşle & Önizle Butonları (Giden ve Gelen faturalarda) -->
              <template v-else>
                <!-- Cariye İşle (İkon Buton / Rozet) -->
                <template v-if="inv.entegrator_durum_kodu !== 'IPTAL'">
                  <button
                    type="button"
                    class="fatura-icon-action-btn fatura-icon-action-btn--sync"
                    :class="{ 'is-loading': syncingInvoiceId === inv.encrypted_id }"
                    :disabled="syncingInvoiceId === inv.encrypted_id || isCariyeIslePasif(inv)"
                    :title="cariyeIsleBaslik(inv)"
                    :aria-label="cariyeIsleBaslik(inv)"
                    @click="openSyncToCariModal(inv)"
                  >
                    <ArrowLeftRight v-if="syncingInvoiceId !== inv.encrypted_id" :size="15" />
                    <RefreshCw v-else :size="14" class="is-spinning" />
                  </button>
                </template>

                <!-- PDF Paylaş (İkon Buton) -->
                <button
                  type="button"
                  class="fatura-icon-action-btn"
                  :class="{ 'is-loading': sharingInvoiceId === inv.encrypted_id }"
                  title="PDF Olarak Paylaş"
                  aria-label="PDF Olarak Paylaş"
                  :disabled="sharingInvoiceId === inv.encrypted_id"
                  @click="shareInvoice(inv)"
                >
                  <Share2 v-if="sharingInvoiceId !== inv.encrypted_id" :size="15" />
                  <RefreshCw v-else :size="14" class="is-spinning" />
                </button>

                <!-- Fatura Gör (İkon Buton) -->
                <button
                  type="button"
                  class="fatura-icon-action-btn fatura-icon-action-btn--view"
                  title="Faturayı Görüntüle"
                  aria-label="Faturayı Görüntüle"
                  @click="openPreview(inv.encrypted_id, inv.fatura_no, inv)"
                >
                  <Eye :size="15" />
                </button>
              </template>
            </div>
          </div>
        </article>
      </div>
    </main>

    <!-- ============================================================== -->
    <!-- MODAL 1: FATURA HTML / PDF ÖNİZLEME (FULL-SCREEN SHEET) -->
    <!-- ============================================================== -->
    <Transition name="sheet">
      <div v-if="isPreviewOpen" class="fatura-modal-backdrop" @click.self="closePreview">
        <section class="fatura-preview-sheet" role="dialog" aria-modal="true">
          <header class="fatura-preview-header">
            <div class="fatura-preview-header__title">
              <ReceiptText :size="20" class="text-primary" />
              <h3>{{ previewTitle }}</h3>
            </div>

            <button
              type="button"
              class="fatura-icon-btn fatura-preview-close"
              title="Kapat"
              aria-label="Kapat"
              @click="closePreview"
            >
              <X :size="20" />
            </button>

            <div class="fatura-preview-header__actions">
              <!-- Cariye İşle Butonu / Rozeti (Önizleme Başlığı) -->
              <template v-if="previewInvoice && previewInvoice.entegrator_durum_kodu !== 'TASLAK' && previewInvoice.entegrator_durum_kodu !== 'IPTAL'">
                <button
                  type="button"
                  class="fatura-icon-btn fatura-icon-btn--sync"
                  :class="{ 'is-loading': syncingInvoiceId === previewInvoice.encrypted_id }"
                  :disabled="syncingInvoiceId === previewInvoice.encrypted_id || isCariyeIslePasif(previewInvoice)"
                  :title="cariyeIsleBaslik(previewInvoice)"
                  :aria-label="cariyeIsleBaslik(previewInvoice)"
                  @click="openSyncToCariModal(previewInvoice)"
                >
                  <ArrowLeftRight v-if="syncingInvoiceId !== previewInvoice.encrypted_id" :size="17" />
                  <RefreshCw v-else :size="15" class="is-spinning" />
                </button>
              </template>

              <button
                type="button"
                class="fatura-icon-btn"
                :class="{ 'is-loading': sharingPdf }"
                title="PDF Olarak Paylaş"
                aria-label="PDF Olarak Paylaş"
                :disabled="sharingPdf"
                @click="shareCurrentPreviewPdf"
              >
                <Share2 v-if="!sharingPdf" :size="17" />
                <RefreshCw v-else :size="15" class="is-spinning" />
              </button>
              <button
                type="button"
                class="fatura-icon-btn"
                title="Yazdır"
                @click="printInvoice"
              >
                <Printer :size="17" />
              </button>
              <a
                :href="`/api/fatura.php?action=show_html&id=${encodeURIComponent(previewEncId)}`"
                target="_blank"
                class="fatura-icon-btn"
                title="Yeni Sekmede Aç"
              >
                <ExternalLink :size="17" />
              </a>
              <a
                :href="`/api/fatura.php?action=download_pdf&id=${encodeURIComponent(previewEncId)}`"
                class="fatura-icon-btn"
                title="PDF İndir"
              >
                <Download :size="17" />
              </a>
            </div>
          </header>

          <div class="fatura-preview-body" ref="previewBodyRef">
            <div v-if="previewLoading" class="fatura-preview-loading">
              <RefreshCw :size="30" class="is-spinning text-primary" />
              <p>Fatura yükleniyor...</p>
            </div>

            <template v-else>
              <!-- Zoomable & Panning Viewport -->
              <div
                class="fatura-viewer-viewport"
                ref="viewerViewportRef"
                @touchstart="onTouchStart"
                @touchmove="onTouchMove"
                @touchend="onTouchEnd"
              >
                <div
                  class="fatura-viewer-scaler"
                  :style="{
                    width: `${scaledDimensions.width}px`,
                    height: `${scaledDimensions.height}px`,
                  }"
                >
                  <iframe
                    ref="previewIframeRef"
                    class="fatura-preview-iframe"
                    :srcdoc="previewHtmlContent"
                    sandbox="allow-same-origin allow-scripts allow-modals allow-popups"
                    :style="{
                      width: `${baseWidth}px`,
                      height: `${baseHeight}px`,
                      transform: `scale(${zoomScale})`,
                      transformOrigin: 'top left',
                    }"
                    @load="onIframeLoaded"
                  ></iframe>
                </div>
              </div>

              <!-- Floating Modern Zoom Toolbar -->
              <div class="fatura-zoom-dock">
                <button
                  type="button"
                  class="fatura-zoom-dock-btn"
                  title="Uzaklaştır"
                  aria-label="Uzaklaştır"
                  :disabled="zoomScale <= minScale"
                  @click="zoomOut"
                >
                  <ZoomOut :size="16" />
                </button>

                <button
                  type="button"
                  class="fatura-zoom-dock-pill"
                  :class="{ 'is-active': isFitted }"
                  title="Sığdır / %100"
                  @click="toggleFitOr100"
                >
                  <span>{{ isFitted ? 'Sığdırıldı' : `%${Math.round(zoomScale * 100)}` }}</span>
                </button>

                <button
                  type="button"
                  class="fatura-zoom-dock-btn"
                  title="Yakınlaştır"
                  aria-label="Yakınlaştır"
                  :disabled="zoomScale >= maxScale"
                  @click="zoomIn"
                >
                  <ZoomIn :size="16" />
                </button>

                <button
                  type="button"
                  class="fatura-zoom-dock-fit"
                  :class="{ 'is-active': isFitted }"
                  title="Ekrana Tam Sığdır"
                  aria-label="Ekrana Tam Sığdır"
                  @click="fitToScreen"
                >
                  <Maximize2 :size="14" />
                  <span>Sığdır</span>
                </button>
              </div>
            </template>
          </div>
        </section>
      </div>
    </Transition>

    <!-- ============================================================== -->
    <!-- MODAL 2: DÖNEM & TARİH SEÇİM MODALI -->
    <!-- ============================================================== -->
    <Transition name="sheet">
      <div v-if="isPeriodModalOpen" class="fatura-modal-backdrop" @click.self="isPeriodModalOpen = false">
        <section class="fatura-period-sheet" role="dialog" aria-modal="true">
          <header class="fatura-sheet-header">
            <div class="fatura-sheet-header__title">
              <Calendar :size="18" class="text-primary" />
              <h3>Dönem & Tarih Seçimi</h3>
            </div>
            <button type="button" class="fatura-icon-btn" @click="isPeriodModalOpen = false">
              <X :size="18" />
            </button>
          </header>

          <div class="fatura-sheet-body">
            <!-- Yıl Seçici -->
            <div class="fatura-year-selector">
              <span>Yıl:</span>
              <select v-model="periodModalYear">
                <option :value="new Date().getFullYear()">{{ new Date().getFullYear() }}</option>
                <option :value="new Date().getFullYear() - 1">{{ new Date().getFullYear() - 1 }}</option>
                <option :value="new Date().getFullYear() - 2">{{ new Date().getFullYear() - 2 }}</option>
                <option :value="new Date().getFullYear() - 3">{{ new Date().getFullYear() - 3 }}</option>
              </select>
            </div>

            <!-- 12 Ay Butonları -->
            <div class="fatura-months-grid">
              <button
                v-for="(mName, idx) in ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık']"
                :key="mName"
                type="button"
                class="fatura-month-btn"
                @click="applyCustomMonth(idx + 1, mName)"
              >
                {{ mName }}
              </button>
            </div>

            <!-- Özel Tarih Aralığı -->
            <div class="fatura-custom-range">
              <h4>Özel Tarih Aralığı</h4>
              <div class="fatura-custom-inputs">
                <div>
                  <label>Başlangıç</label>
                  <input v-model="customStartInput" type="date" />
                </div>
                <div>
                  <label>Bitiş</label>
                  <input v-model="customEndInput" type="date" />
                </div>
              </div>
              <button type="button" class="fatura-primary-btn w-full mt-3" @click="applyCustomDateRange">
                <Check :size="16" />
                <span>Tarih Aralığını Uygula</span>
              </button>
            </div>
          </div>
        </section>
      </div>
    </Transition>

    <!-- ============================================================== -->
    <!-- MODAL 3: TİCARİ YANIT VERME MODALI (KABUL / RED) -->
    <!-- ============================================================== -->
    <Transition name="sheet">
      <div v-if="isResponseModalOpen" class="fatura-modal-backdrop" @click.self="isResponseModalOpen = false">
        <section class="fatura-response-sheet" role="dialog" aria-modal="true">
          <header class="fatura-sheet-header">
            <div class="fatura-sheet-header__title">
              <CheckCircle2 :size="18" class="text-primary" />
              <h3>Ticari Fatura Yanıtı</h3>
            </div>
            <button type="button" class="fatura-icon-btn" @click="isResponseModalOpen = false">
              <X :size="18" />
            </button>
          </header>

          <div class="fatura-sheet-body">
            <div class="fatura-response-target">
              <strong>{{ responseInvoice?.fatura_no || 'Fatura' }}</strong>
              <p>{{ responseInvoice?.alici_unvan || 'Tedarikçi' }}</p>
              <span>{{ responseInvoice?.odenecek_tutar_fmt || formatMoney(responseInvoice?.odenecek_tutar) }}</span>
            </div>

            <!-- Yanıt Türü Seçimi -->
            <div class="fatura-response-types">
              <button
                type="button"
                class="fatura-response-type-btn"
                :class="{ 'is-selected is-kabul': responseType === 'KABUL' }"
                @click="responseType = 'KABUL'"
              >
                <CheckCircle2 :size="20" />
                <strong>KABUL</strong>
                <small>Faturayı onayla</small>
              </button>

              <button
                type="button"
                class="fatura-response-type-btn"
                :class="{ 'is-selected is-red': responseType === 'RED' }"
                @click="responseType = 'RED'"
              >
                <XCircle :size="20" />
                <strong>RED</strong>
                <small>Gerekçeli itiraz</small>
              </button>
            </div>

            <!-- Red Gerekçesi -->
            <div v-if="responseType === 'RED'" class="fatura-form-field">
              <label>Red Gerekçesi (Zorunlu):</label>
              <textarea
                v-model="responseReason"
                rows="3"
                placeholder="Faturanın reddedilme sebebini yazınız (mal teslim edilmedi, tutar hatalı vb.)..."
              ></textarea>
            </div>

            <button
              type="button"
              class="fatura-primary-btn w-full mt-4"
              :disabled="responseSubmitting"
              @click="submitCommercialResponse"
            >
              <Check :size="16" />
              <span>{{ responseSubmitting ? 'İletiliyor...' : 'Yanıtı Gönder' }}</span>
            </button>
          </div>
        </section>
      </div>
    </Transition>

    <!-- ============================================================== -->
    <!-- MODAL 3.1: CARİYE İŞLE ONAY MODALI -->
    <!-- ============================================================== -->
    <Transition name="sheet">
      <div v-if="isCariConfirmModalOpen" class="fatura-modal-backdrop" @click.self="isCariConfirmModalOpen = false">
        <section class="fatura-cari-sheet" role="dialog" aria-modal="true">
          <header class="fatura-sheet-header">
            <div class="fatura-sheet-header__title">
              <ArrowLeftRight :size="18" class="text-primary" />
              <h3>{{ cariConfirmInfo?.cari_exists ? 'Faturayı Cariye İşle' : 'Cari Kart Oluşturulup İşlenecek' }}</h3>
            </div>
            <button type="button" class="fatura-icon-btn" @click="isCariConfirmModalOpen = false">
              <X :size="18" />
            </button>
          </header>

          <div class="fatura-sheet-body">
            <!-- Yükleniyor Göstergesi -->
            <div v-if="cariCheckingLoading" class="fatura-preview-loading" style="padding: 32px 16px;">
              <RefreshCw :size="28" class="is-spinning text-primary" />
              <p>Cari bilgisi kontrol ediliyor...</p>
            </div>

            <template v-else-if="cariConfirmInfo">
              <!-- Cari Durum Bildirimi -->
              <div
                class="fatura-cari-notice-box"
                :class="cariConfirmInfo.cari_exists ? 'fatura-cari-notice-box--success' : 'fatura-cari-notice-box--info'"
              >
                <template v-if="cariConfirmInfo.cari_exists">
                  <p style="margin: 0 0 6px; font-weight: 700; font-size: 13.5px; line-height: 1.45;">
                    <strong>"{{ cariConfirmInfo.invoice_no }}"</strong> numaralı fatura, 
                    <span class="text-primary" style="font-weight: 800;">"{{ cariConfirmInfo.cari_name }}"</span> isimli cari hesabına 
                    <strong>({{ cariConfirmInfo.direction_text }})</strong> olarak işlenecektir.
                  </p>
                </template>
                <template v-else>
                  <p style="margin: 0 0 6px; color: #0284c7; font-weight: 700; font-size: 13.5px;">
                    Sistemde <strong>"{{ cariConfirmInfo.counterparty_name }}"</strong> adına kayıtlı bir cari kart bulunamadı.
                  </p>
                  <p style="margin: 0; color: #475569; font-size: 12px; line-height: 1.4;">
                    Yeni cari kart <strong>otomatik oluşturulacak</strong> ve fatura bu cari hesabına 
                    <strong>({{ cariConfirmInfo.direction_text }})</strong> olarak aktarılacaktır.
                  </p>
                </template>
              </div>

              <!-- Detay Özet Kartları -->
              <div class="fatura-cari-details-grid">
                <div class="fatura-cari-detail-card">
                  <span>Fatura No</span>
                  <strong>{{ cariConfirmInfo.invoice_no }}</strong>
                </div>
                <div class="fatura-cari-detail-card">
                  <span>Fatura Tarihi</span>
                  <strong>{{ cariConfirmInfo.fatura_tarihi }}</strong>
                </div>
                <div class="fatura-cari-detail-card">
                  <span>İşlem Yönü</span>
                  <strong :class="cariConfirmInfo.direction === 'GELEN' ? 'text-amber' : 'text-primary'">
                    {{ cariConfirmInfo.direction_text }}
                  </strong>
                </div>
                <div class="fatura-cari-detail-card">
                  <span>İşlenecek Tutar</span>
                  <strong class="text-primary">{{ cariConfirmInfo.odenecek_tutar }}</strong>
                </div>
              </div>

              <!-- Onay Aksiyon Butonları -->
              <div class="fatura-sheet-actions mt-4">
                <button
                  type="button"
                  class="fatura-secondary-btn flex-1"
                  @click="isCariConfirmModalOpen = false"
                >
                  <span>Vazgeç</span>
                </button>
                <button
                  type="button"
                  class="fatura-primary-btn flex-2"
                  :disabled="syncingInvoiceId === cariConfirmInvoice?.encrypted_id"
                  @click="confirmSyncToCari(false)"
                >
                  <RefreshCw v-if="syncingInvoiceId === cariConfirmInvoice?.encrypted_id" :size="15" class="is-spinning" />
                  <ArrowLeftRight v-else :size="15" />
                  <span>{{ syncingInvoiceId === cariConfirmInvoice?.encrypted_id ? 'İşleniyor...' : 'Evet, Cariye İşle' }}</span>
                </button>
              </div>
            </template>
          </div>
        </section>
      </div>
    </Transition>

    <!-- ============================================================== -->
    <!-- MODAL 3.2: MÜKERRER / BENZER HAREKET UYARISI MODALI -->
    <!-- ============================================================== -->
    <Transition name="sheet">
      <div v-if="isDuplicateModalOpen" class="fatura-modal-backdrop" @click.self="isDuplicateModalOpen = false">
        <section class="fatura-duplicate-sheet" role="dialog" aria-modal="true">
          <header class="fatura-sheet-header">
            <div class="fatura-sheet-header__title">
              <AlertTriangle :size="18" class="text-warning" />
              <h3>Benzer Hareket Uyarısı</h3>
            </div>
            <button type="button" class="fatura-icon-btn" @click="isDuplicateModalOpen = false">
              <X :size="18" />
            </button>
          </header>

          <div class="fatura-sheet-body">
            <div class="fatura-cari-notice-box fatura-cari-notice-box--warning mb-3">
              <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 8px; font-weight: 700; color: #b45309;">
                <AlertTriangle :size="16" />
                <span>Aynı tarihte aynı tutarda hareket bulundu:</span>
              </div>
              <p style="font-size: 13px; line-height: 1.45; margin: 0 0 10px; color: #1e293b;">
                {{ duplicateWarningData?.message }}
              </p>
              <div class="fatura-alert-warning-callout">
                Bu fatura zaten cariye manuel veya farklı bir hareketle işlenmiş olabilir. Yine de devam etmek istiyor musunuz?
              </div>
            </div>

            <!-- Onay Aksiyon Butonları -->
            <div class="fatura-sheet-actions mt-4">
              <button
                type="button"
                class="fatura-secondary-btn flex-1"
                @click="isDuplicateModalOpen = false"
              >
                <span>Vazgeç</span>
              </button>
              <button
                type="button"
                class="fatura-primary-btn fatura-btn-warning flex-2"
                :disabled="duplicateSubmitting"
                @click="confirmSyncToCari(true)"
              >
                <RefreshCw v-if="duplicateSubmitting" :size="15" class="is-spinning" />
                <Check v-else :size="15" />
                <span>{{ duplicateSubmitting ? 'İşleniyor...' : 'Evet, Yine de İşle' }}</span>
              </button>
            </div>
          </div>
        </section>
      </div>
    </Transition>

    <!-- ============================================================== -->
    <!-- MODAL 4: YENİ FATURA KESME SİHİRBAZI (4 ADIMLI WIZARD) -->
    <!-- ============================================================== -->
    <Transition name="sheet">
      <div v-if="isWizardOpen" class="fatura-modal-backdrop" @click.self="isWizardOpen = false">
        <section class="fatura-wizard-sheet" role="dialog" aria-modal="true">
          <!-- Wizard Header -->
          <header class="fatura-wizard-header">
            <div class="fatura-wizard-header__info">
              <span class="fatura-wizard-tag">E-FATURA OLUŞTURUCU</span>
              <h2>{{ wizardEditId ? 'Taslağı Düzenle' : 'Yeni Fatura Kes' }}</h2>
              <p class="fatura-wizard-progress-text">
                <strong>Adım {{ wizardStep }}/4:</strong>
                <span>{{ ['Alıcı & Cari Seçimi', 'Fatura Türü & Tarih', 'Mal / Hizmet Kalemleri', 'Özet & Gönderim'][wizardStep - 1] }}</span>
              </p>
            </div>
            <button type="button" class="fatura-icon-btn fatura-wizard-close-btn" aria-label="Sihirbazı kapat" @click="isWizardOpen = false">
              <X :size="18" />
            </button>
          </header>

          <!-- İlerleme Çubuğu & Stepper -->
          <div class="fatura-stepper-container">
            <div class="fatura-stepper-progress-track">
              <div class="fatura-stepper-progress-fill" :style="{ width: `${(wizardStep / 4) * 100}%` }"></div>
            </div>
            <nav class="fatura-wizard-steps" aria-label="Sihirbaz adımları">
              <button
                v-for="(st, sIdx) in [
                  { num: 1, name: 'Cari' },
                  { num: 2, name: 'Ayarlar' },
                  { num: 3, name: 'Kalemler' },
                  { num: 4, name: 'Özet' }
                ]"
                :key="st.num"
                type="button"
                class="fatura-wizard-step-item"
                :class="{
                  'is-active': wizardStep === st.num,
                  'is-done': wizardStep > st.num,
                  'is-pending': wizardStep < st.num
                }"
                :aria-current="wizardStep === st.num ? 'step' : undefined"
                @click="wizardStep = st.num"
              >
                <span class="step-indicator">
                  <Check v-if="wizardStep > st.num" :size="12" class="step-check" />
                  <span v-else class="step-num">{{ st.num }}</span>
                </span>
                <span class="step-name">{{ st.name }}</span>
              </button>
            </nav>
          </div>

          <!-- Wizard Body -->
          <div class="fatura-wizard-body">
            <!-- ADIM 1: CARİ / ALICI SEÇİMİ -->
            <div v-if="wizardStep === 1" class="wizard-step-panel">
              <div class="wizard-step-intro">
                <span class="wizard-step-intro__icon"><Building2 :size="18" /></span>
                <div>
                  <h3>Alıcı & Cari Bilgileri</h3>
                  <p>Kayıtlı carilerden hızlıca seçin veya yeni bir alıcı bilgisi girin.</p>
                </div>
              </div>

              <!-- Müşteri Arama -->
              <div class="fatura-wizard-search-box">
                <Search :size="16" class="fatura-search-box__icon" />
                <input
                  v-model="wizardCustomerSearch"
                  type="text"
                  placeholder="Kayıtlı carilerde ara (Ünvan, VKN)..."
                  @input="searchCustomers"
                />
                <button
                  v-if="wizardCustomerSearch"
                  type="button"
                  class="fatura-search-clear"
                  @click="wizardCustomerSearch = ''; customerSearchResults = []"
                >
                  <X :size="14" />
                </button>
              </div>

              <!-- Arama Sonuçları Açılır Listesi -->
              <div v-if="customerSearchResults.length > 0" class="fatura-customer-dropdown">
                <button
                  v-for="c in customerSearchResults"
                  :key="c.id"
                  type="button"
                  class="fatura-customer-item"
                  @click="selectCustomer(c)"
                >
                  <span class="fatura-customer-item__avatar">
                    {{ (c.CariAdi || c.firma || 'C').charAt(0).toUpperCase() }}
                  </span>
                  <div class="fatura-customer-item__info">
                    <strong>{{ c.CariAdi || c.firma }}</strong>
                    <small>VKN/TCKN: {{ c.vkn_tckn || '-' }} • {{ c.il || 'Şehir Belirtilmemiş' }}</small>
                  </div>
                  <ChevronRight :size="14" class="fatura-customer-item__arrow" />
                </button>
              </div>

              <!-- Alıcı Bilgileri Formu -->
              <div class="fatura-form-fields mt-3">
                <div class="fatura-form-field">
                  <label>Alıcı Ünvanı / Adı Soyadı <span class="text-rose">*</span></label>
                  <input v-model="invoiceHeader.alici_unvan" type="text" placeholder="Firma veya şahıs ünvanı" />
                </div>

                <div class="fatura-form-field">
                  <label>VKN / TCKN <span class="text-rose">*</span></label>
                  <div class="fatura-input-inline-action">
                    <input
                      v-model="invoiceHeader.alici_vkn_tckn"
                      type="text"
                      maxlength="11"
                      placeholder="10 veya 11 haneli vergi / kimlik no"
                      @blur="checkTaxpayerStatus(invoiceHeader.alici_vkn_tckn)"
                    />
                    <button
                      type="button"
                      class="fatura-inline-btn"
                      :disabled="checkingTaxpayer || !invoiceHeader.alici_vkn_tckn"
                      @click="checkTaxpayerStatus(invoiceHeader.alici_vkn_tckn)"
                    >
                      <Sparkles v-if="!checkingTaxpayer" :size="13" />
                      <RefreshCw v-else :size="13" class="is-spinning" />
                      <span>{{ checkingTaxpayer ? 'Sorgulanıyor' : 'Sorgula' }}</span>
                    </button>
                  </div>
                </div>

                <!-- Mükellef Bilgi Rozeti -->
                <div v-if="taxpayerInfo" class="fatura-taxpayer-alert" :class="taxpayerInfo.is_taxpayer ? 'fatura-taxpayer-alert--efatura' : 'fatura-taxpayer-alert--earsiv'">
                  <component :is="taxpayerInfo.is_taxpayer ? CheckCircle2 : Sparkles" :size="18" />
                  <div>
                    <strong>{{ taxpayerInfo.is_taxpayer ? 'GİB E-Fatura Mükellefi' : 'E-Arşiv Fatura Kullanıcısı' }}</strong>
                    <p>{{ taxpayerInfo.is_taxpayer ? 'Fatura doğrudan E-Fatura senaryosu ile gönderilecektir.' : 'Alıcı e-fatura kayıtlısı olmadığından E-Arşiv düzenlenecektir.' }}</p>
                  </div>
                </div>

                <div class="fatura-form-grid-2">
                  <div class="fatura-form-field">
                    <label>Vergi Dairesi</label>
                    <input v-model="invoiceHeader.alici_vergi_dairesi" type="text" placeholder="Vergi Dairesi Adı" />
                  </div>
                  <div class="fatura-form-field">
                    <label>E-Posta</label>
                    <input v-model="invoiceHeader.alici_eposta" type="email" placeholder="fatura@sirket.com" />
                  </div>
                </div>

                <div class="fatura-form-grid-2">
                  <div class="fatura-form-field">
                    <label>İl</label>
                    <input v-model="invoiceHeader.alici_il" type="text" placeholder="İl (Örn: Ankara)" />
                  </div>
                  <div class="fatura-form-field">
                    <label>İlçe</label>
                    <input v-model="invoiceHeader.alici_ilce" type="text" placeholder="İlçe (Örn: Çankaya)" />
                  </div>
                </div>

                <div class="fatura-form-field">
                  <label>Açık Adres</label>
                  <textarea v-model="invoiceHeader.alici_adres" rows="2" placeholder="Cadde, sokak, bina ve kapı no..."></textarea>
                </div>
              </div>
            </div>

            <!-- ADIM 2: FATURA DETAY VE AYARLARI -->
            <div v-else-if="wizardStep === 2" class="wizard-step-panel">
              <div class="wizard-step-intro">
                <span class="wizard-step-intro__icon wizard-step-intro__icon--purple"><FileText :size="18" /></span>
                <div>
                  <h3>Fatura Ayarları</h3>
                  <p>Tarih, belge türü ve gönderim senaryosunu belirleyin.</p>
                </div>
              </div>
              <div class="fatura-form-fields">
                <div class="fatura-form-grid-2">
                  <div class="fatura-form-field">
                    <label>Fatura Tarihi <span class="text-rose">*</span></label>
                    <input v-model="invoiceHeader.fatura_tarihi" type="date" />
                  </div>
                  <div class="fatura-form-field">
                    <label>Fatura Saati</label>
                    <input v-model="invoiceHeader.fatura_saati" type="time" />
                  </div>
                </div>

                <!-- Belge Türü Segmented Control -->
                <div class="fatura-form-field">
                  <label>Belge Türü</label>
                  <div class="fatura-segmented-pill-wrap">
                    <button
                      type="button"
                      class="fatura-seg-pill-btn"
                      :class="{ 'is-active': invoiceHeader.belge_turu === 'EFATURA' }"
                      @click="invoiceHeader.belge_turu = 'EFATURA'"
                    >
                      <Zap :size="14" />
                      <span>E-Fatura</span>
                    </button>
                    <button
                      type="button"
                      class="fatura-seg-pill-btn"
                      :class="{ 'is-active': invoiceHeader.belge_turu === 'EARSIV' }"
                      @click="invoiceHeader.belge_turu = 'EARSIV'"
                    >
                      <Archive :size="14" />
                      <span>E-Arşiv Fatura</span>
                    </button>
                  </div>
                </div>

                <!-- Fatura Tipi Chips -->
                <div class="fatura-form-field">
                  <label>Fatura Tipi</label>
                  <div class="fatura-pill-selector">
                    <button
                      v-for="tip in [
                        { id: 'SATIS', label: 'Satış' },
                        { id: 'IADE', label: 'İade' },
                        { id: 'TEVKIFAT', label: 'Tevkifat' },
                        { id: 'ISTISNA', label: 'İstisna' }
                      ]"
                      :key="tip.id"
                      type="button"
                      class="fatura-sel-pill"
                      :class="{ 'is-active': invoiceHeader.fatura_tipi === tip.id }"
                      @click="invoiceHeader.fatura_tipi = tip.id"
                    >
                      {{ tip.label }}
                    </button>
                  </div>
                </div>

                <!-- Senaryo Chips -->
                <div class="fatura-form-field">
                  <label>Senaryo</label>
                  <div class="fatura-pill-selector">
                    <button
                      v-for="sen in [
                        { id: 'TICARIFATURA', label: 'Ticari Fatura' },
                        { id: 'TEMELFATURA', label: 'Temel Fatura' },
                        { id: 'EARSIVFATURA', label: 'E-Arşiv Fatura' }
                      ]"
                      :key="sen.id"
                      type="button"
                      class="fatura-sel-pill"
                      :class="{ 'is-active': invoiceHeader.senaryo === sen.id }"
                      @click="invoiceHeader.senaryo = sen.id"
                    >
                      {{ sen.label }}
                    </button>
                  </div>
                </div>

                <div class="fatura-form-grid-2">
                  <div class="fatura-form-field">
                    <label>Seri / Ön Ek</label>
                    <select v-model="invoiceHeader.seri">
                      <option v-for="s in availableSerials" :key="s.seri" :value="s.seri">
                        {{ s.seri }} ({{ s.yil }})
                      </option>
                      <option v-if="availableSerials.length === 0" value="ERS">ERS (Varsayılan)</option>
                    </select>
                  </div>
                  <div class="fatura-form-field">
                    <label>Para Birimi</label>
                    <select v-model="invoiceHeader.para_birimi">
                      <option value="TRY">TRY (₺)</option>
                      <option value="USD">USD ($)</option>
                      <option value="EUR">EUR (€)</option>
                    </select>
                  </div>
                </div>

                <div class="fatura-form-field">
                  <label>Fatura Notları & Banka Bilgisi</label>
                  <textarea
                    v-model="invoiceHeader.notlar"
                    rows="2"
                    placeholder="Banka IBAN, sipariş no veya ek açıklamalar..."
                  ></textarea>
                </div>
              </div>
            </div>

            <!-- ADIM 3: KALEMLER -->
            <div v-else-if="wizardStep === 3" class="wizard-step-panel">
              <div class="wizard-step-intro">
                <span class="wizard-step-intro__icon wizard-step-intro__icon--amber"><Layers :size="18" /></span>
                <div>
                  <h3>Mal ve Hizmet Kalemleri</h3>
                  <p>Faturaya eklenecek kalemleri, miktarları ve KDV oranlarını girin.</p>
                </div>
              </div>
              <div class="fatura-lines-container">
                <article
                  v-for="(line, idx) in invoiceLines"
                  :key="idx"
                  class="fatura-line-card"
                >
                  <header class="fatura-line-card__header">
                    <span class="line-index">#{{ idx + 1 }} Kalem</span>
                    <button
                      v-if="invoiceLines.length > 1"
                      type="button"
                      class="fatura-icon-action-btn fatura-icon-action-btn--danger"
                      title="Kalemi Sil"
                      @click="removeLine(idx)"
                    >
                      <Trash2 :size="14" />
                    </button>
                  </header>

                  <div class="fatura-form-field">
                    <label>Kalem Açıklaması / Mal-Hizmet <span class="text-rose">*</span></label>
                    <input
                      v-model="line.aciklama"
                      type="text"
                      placeholder="Mal veya hizmet adı..."
                      @input="recalcLines"
                    />
                  </div>

                  <!-- Hızlı Ürün Seçimi -->
                  <div v-if="availableProducts.length > 0" class="fatura-quick-products-row">
                    <span>Hızlı Seç:</span>
                    <button
                      v-for="p in availableProducts.slice(0, 4)"
                      :key="p.id"
                      type="button"
                      class="fatura-quick-prod-pill"
                      @click="onSelectProduct(line, p)"
                    >
                      {{ p.urun_adi }}
                    </button>
                  </div>

                  <div class="fatura-form-grid-3">
                    <div class="fatura-form-field">
                      <label>Miktar</label>
                      <input
                        v-model.number="line.miktar"
                        type="number"
                        min="1"
                        step="1"
                        @input="recalcLines"
                      />
                    </div>
                    <div class="fatura-form-field">
                      <label>Birim</label>
                      <select v-model="line.birim" @change="recalcLines">
                        <option value="C62">Adet</option>
                        <option value="KGM">Kg</option>
                        <option value="MTR">Metre</option>
                        <option value="HUR">Saat</option>
                        <option value="DAY">Gün</option>
                        <option value="MON">Ay</option>
                      </select>
                    </div>
                    <div class="fatura-form-field">
                      <label>Birim Fiyat <span class="text-rose">*</span></label>
                      <input
                        v-model.number="line.birim_fiyat"
                        type="number"
                        min="0"
                        step="0.01"
                        placeholder="0.00"
                        @input="recalcLines"
                      />
                    </div>
                  </div>

                  <div class="fatura-form-grid-2">
                    <div class="fatura-form-field">
                      <label>KDV Oranı</label>
                      <select v-model.number="line.kdv_orani" @change="recalcLines">
                        <option :value="0">%0</option>
                        <option :value="1">%1</option>
                        <option :value="10">%10</option>
                        <option :value="20">%20</option>
                      </select>
                    </div>
                    <div class="fatura-form-field">
                      <label>İskonto (%)</label>
                      <input
                        v-model.number="line.iskonto_orani"
                        type="number"
                        min="0"
                        max="100"
                        step="1"
                        placeholder="0"
                        @input="recalcLines"
                      />
                    </div>
                  </div>

                  <div class="fatura-line-total">
                    <div class="fatura-line-total__matrah">
                      <span>Matrah:</span>
                      <strong>{{ formatMoney(line.mal_hizmet_tutari) }}</strong>
                    </div>
                    <div class="fatura-line-total__sum">
                      <span>KDV Dahil:</span>
                      <strong>{{ formatMoney(line.mal_hizmet_tutari + line.kdv_tutari) }}</strong>
                    </div>
                  </div>
                </article>

                <button type="button" class="fatura-add-line-btn" @click="addLine">
                  <Plus :size="16" />
                  <span>Yeni Kalem Ekle</span>
                </button>
              </div>
            </div>

            <!-- ADIM 4: ÖZET VE GÖNDER / KAYDET -->
            <div v-else-if="wizardStep === 4" class="wizard-step-panel">
              <div class="wizard-step-intro">
                <span class="wizard-step-intro__icon wizard-step-intro__icon--green"><CheckCircle2 :size="18" /></span>
                <div>
                  <h3>Kontrol ve Gönderim</h3>
                  <p>Bilgileri son kez gözden geçirin, taslağı kaydedin veya GİB'e iletin.</p>
                </div>
              </div>

              <!-- Termal / Bento Makbuz Kartı -->
              <div class="fatura-wizard-summary-card">
                <div class="fatura-receipt-header">
                  <div class="fatura-receipt-badge">
                    <ReceiptText :size="15" />
                    <span>FATURA ÖZETİ</span>
                  </div>
                  <span class="fatura-receipt-type-tag">{{ invoiceHeader.belge_turu }}</span>
                </div>

                <div class="fatura-receipt-customer">
                  <strong class="fatura-receipt-customer__name">{{ invoiceHeader.alici_unvan }}</strong>
                  <span class="fatura-receipt-customer__vkn">VKN / TCKN: {{ invoiceHeader.alici_vkn_tckn }}</span>
                </div>

                <div class="fatura-receipt-meta-grid">
                  <div class="fatura-receipt-meta-item">
                    <span>Senaryo</span>
                    <strong>{{ invoiceHeader.senaryo }}</strong>
                  </div>
                  <div class="fatura-receipt-meta-item">
                    <span>Fatura Tarihi</span>
                    <strong>{{ formatDate(invoiceHeader.fatura_tarihi) }}</strong>
                  </div>
                  <div class="fatura-receipt-meta-item">
                    <span>Kalem Sayısı</span>
                    <strong>{{ invoiceLines.length }} Adet Kalem</strong>
                  </div>
                  <div class="fatura-receipt-meta-item">
                    <span>Para Birimi</span>
                    <strong>{{ invoiceHeader.para_birimi || 'TRY' }}</strong>
                  </div>
                </div>

                <div class="fatura-receipt-divider"></div>

                <!-- Finansal Kırılımlar -->
                <div class="fatura-receipt-rows">
                  <div class="fatura-receipt-row">
                    <span>Mal / Hizmet Toplamı:</span>
                    <b>{{ formatInvoiceMoney(wizardCalculation.mal_hizmet_toplam, invoiceHeader.para_birimi) }}</b>
                  </div>
                  <div v-if="wizardCalculation.iskonto_toplam > 0" class="fatura-receipt-row text-rose">
                    <span>İskonto Toplamı:</span>
                    <b>-{{ formatInvoiceMoney(wizardCalculation.iskonto_toplam, invoiceHeader.para_birimi) }}</b>
                  </div>
                  <div class="fatura-receipt-row">
                    <span>Hesaplanan KDV:</span>
                    <b class="text-emerald">{{ formatInvoiceMoney(wizardCalculation.kdv_toplam, invoiceHeader.para_birimi) }}</b>
                  </div>
                </div>

                <div class="fatura-receipt-divider fatura-receipt-divider--bold"></div>

                <div class="fatura-receipt-total">
                  <div>
                    <span class="fatura-receipt-total__label">Ödenecek Tutar</span>
                    <small>KDV ve tüm vergiler dahil</small>
                  </div>
                  <strong class="fatura-receipt-total__amount">
                    {{ formatInvoiceMoney(wizardCalculation.odenecek_tutar, invoiceHeader.para_birimi) }}
                  </strong>
                </div>

                <!-- Güvenlik & EDM Bilgilendirme Rozeti -->
                <div class="fatura-receipt-trust-badge">
                  <CheckCircle2 :size="14" class="text-emerald" />
                  <span>Faturanız EDM Bilişim güvencesiyle doğrudan Gelir İdaresi Başkanlığı sistemine iletilir.</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Wizard Footer Aksiyon Butonları -->
          <footer class="fatura-wizard-footer" :class="{ 'is-summary': wizardStep === 4 }">
            <button
              v-if="wizardStep > 1"
              type="button"
              class="fatura-wizard-back-btn"
              :disabled="wizardSubmitting"
              @click="prevWizardStep"
            >
              <ArrowLeft :size="16" />
              <span>Geri</span>
            </button>

            <button
              v-if="wizardStep < 4"
              type="button"
              class="fatura-wizard-next-btn ml-auto"
              @click="nextWizardStep"
            >
              <span>{{ wizardStep === 3 ? 'Özeti Gör' : 'Devam Et' }}</span>
              <ChevronRight :size="16" />
            </button>

            <template v-else>
              <button
                type="button"
                class="fatura-wizard-draft-btn"
                :disabled="wizardSubmitting"
                @click="saveWizardInvoice(false)"
              >
                <FileText :size="15" />
                <span>{{ wizardSubmitting ? 'Kaydediliyor…' : 'Taslağı Kaydet' }}</span>
              </button>
              <button
                type="button"
                class="fatura-wizard-send-btn"
                :disabled="wizardSubmitting"
                @click="saveWizardInvoice(true)"
              >
                <Send :size="15" />
                <span>{{ wizardSubmitting ? 'İletiliyor…' : 'GİB\'e Gönder' }}</span>
              </button>
            </template>
          </footer>
        </section>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
/* ==========================================================================
   GLACIER ALPINE DESIGN SYSTEM — FATURA MODÜLÜ
   ========================================================================== */
.fatura-screen {
  padding-bottom: calc(140px + var(--ds-safe-bottom, 24px)) !important;
  width: 100%;
  max-width: 100%;
  overflow-x: hidden;
  box-sizing: border-box;
}
.fatura-tab-content {
  width: 100%;
  max-width: 100%;
  min-width: 0;
  box-sizing: border-box;
  overflow-x: hidden;
}

/* Toast Bildirimi */
.fatura-toast {
  position: fixed;
  top: max(16px, var(--ds-safe-top));
  left: 20px;
  right: 20px;
  z-index: 1200;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 13px 18px;
  border-radius: var(--ds-radius-sm);
  background: var(--ds-primary);
  color: var(--ds-ink-light);
  font-size: 13px;
  font-weight: 600;
  box-shadow: var(--ds-shadow-float);
}
.fatura-toast--error {
  background: var(--ds-danger-ink);
}

/* 2. Dönem Filtreleri Çubuğu */
.fatura-periods-bar {
  display: flex;
  align-items: center;
  gap: 8px;
  overflow-x: auto;
  padding-bottom: 6px;
  margin-bottom: 14px;
  scrollbar-width: none;
}
.fatura-periods-bar::-webkit-scrollbar {
  display: none;
}
.fatura-period-pill {
  flex-shrink: 0;
  padding: 8px 14px;
  border: 1px solid var(--dsx-bd-ffffff-a090);
  border-radius: 999px;
  background: var(--ds-surface);
  color: var(--ds-muted);
  font-size: 11px;
  font-weight: 700;
  box-shadow: var(--ds-shadow-bento, 0 10px 24px rgba(16, 24, 40, 0.08));
  cursor: pointer;
  transition: all 0.18s ease;
}
.fatura-period-pill.is-active {
  background: var(--ds-surface-dark, var(--ds-surface-dark));
  color: var(--ds-ink-light);
  border-color: var(--ds-surface-dark, var(--ds-surface-dark));
  box-shadow: 0 4px 12px rgba(25, 29, 36, 0.25);
}
.fatura-period-pill--custom {
  display: inline-flex;
  align-items: center;
  gap: 5px;
}

/* 3. Sekmeli Navigasyon */
.fatura-tabs {
  margin-bottom: 18px;
}

/* Hata Kutusu */
.fatura-error-box {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 16px;
  margin-bottom: 14px;
  border-radius: 16px;
  background: var(--ds-danger-soft);
  color: var(--ds-danger-ink);
  font-size: 12px;
  font-weight: 700;
}

/* ==========================================================================
   ÖZET SEKME GRAFİKLERİ & BENTO KARTLARI
   ========================================================================== */

/* 1. Hero Obsidian Bento Kartı: Ticari Hacim & Çift Yönlü Bar */
.fatura-hero-volume-card {
  background: var(--ds-surface-dark, var(--dsx-bg-191d24));
  border-radius: var(--ds-radius-card, 28px);
  color: #ffffff;
  padding: 20px 18px;
  margin-bottom: 14px;
  border: 1px solid rgba(255, 255, 255, 0.1);
  box-shadow: var(--ds-shadow-bento-dark, 0 16px 36px rgba(0, 0, 0, 0.28));
}

.fatura-hero-volume-card__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 14px;
}

.fatura-hero-volume-card__title {
  display: flex;
  align-items: center;
  gap: 10px;
}

.fatura-hero-icon-box {
  display: grid;
  place-items: center;
  width: 34px;
  height: 34px;
  border-radius: 12px;
  background: rgba(255, 255, 255, 0.1);
  color: #38bdf8;
  border: 1px solid rgba(255, 255, 255, 0.12);
}

.fatura-hero-tag {
  display: block;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.08em;
  color: rgba(255, 255, 255, 0.65);
  text-transform: uppercase;
}

.fatura-hero-subtitle {
  display: block;
  font-size: 11px;
  font-weight: 500;
  color: rgba(255, 255, 255, 0.45);
}

.fatura-balance-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 700;
  white-space: nowrap;
}

.fatura-balance-pill--positive {
  background: rgba(16, 185, 129, 0.18);
  color: #34d399;
  border: 1px solid rgba(52, 211, 153, 0.3);
}

.fatura-balance-pill--warning {
  background: rgba(245, 158, 11, 0.18);
  color: #fbbf24;
  border: 1px solid rgba(251, 191, 36, 0.3);
}

.fatura-hero-volume-main {
  margin-bottom: 14px;
}

.fatura-hero-volume-amount {
  display: block;
  font-size: 26px;
  font-weight: 800;
  letter-spacing: -0.04em;
  color: #ffffff;
  line-height: 1.15;
}

.fatura-hero-balance-row {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-top: 4px;
  font-size: 11.5px;
}

.fatura-hero-balance-label {
  color: rgba(255, 255, 255, 0.55);
}

.fatura-hero-balance-val {
  font-weight: 700;
}

/* Çift Yönlü Segmentli Dağılım Grafiği (Segmented Track) */
.fatura-chart-container {
  padding-top: 6px;
  border-top: 1px solid rgba(255, 255, 255, 0.08);
}

.fatura-segmented-track {
  position: relative;
  display: flex;
  height: 16px;
  width: 100%;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.1);
  overflow: hidden;
  box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.3);
}

.fatura-seg-fill {
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: width 0.4s ease;
  position: relative;
}

.fatura-seg-fill--sales {
  background: linear-gradient(90deg, #10b981, #059669);
}

.fatura-seg-fill--purchase {
  background: linear-gradient(90deg, #38bdf8, #0284c7);
}

.fatura-seg-label {
  font-size: 9.5px;
  font-weight: 800;
  color: #ffffff;
  text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
  letter-spacing: -0.01em;
}

/* Lejant (Legend Grid) */
.fatura-chart-legend {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 10px;
  margin-top: 12px;
}

.fatura-legend-item {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  padding: 8px 10px;
  border-radius: 14px;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.06);
  cursor: pointer;
  transition: background 0.18s ease;
}

.fatura-legend-item:active {
  background: rgba(255, 255, 255, 0.1);
}

.fatura-legend-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  margin-top: 4px;
  flex-shrink: 0;
}

.fatura-legend-dot--sales {
  background: #10b981;
  box-shadow: 0 0 8px rgba(16, 185, 129, 0.6);
}

.fatura-legend-dot--purchase {
  background: #38bdf8;
  box-shadow: 0 0 8px rgba(56, 189, 248, 0.6);
}

.fatura-legend-info {
  min-width: 0;
  flex: 1;
}

.fatura-legend-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 4px;
}

.fatura-legend-title {
  font-size: 10.5px;
  font-weight: 600;
  color: rgba(255, 255, 255, 0.7);
}

.fatura-legend-pct {
  font-size: 11px;
  font-weight: 800;
}

.fatura-legend-val {
  display: block;
  font-size: 13px;
  font-weight: 800;
  color: #ffffff;
  margin-top: 2px;
  letter-spacing: -0.02em;
}

.fatura-legend-count {
  display: block;
  font-size: 9.5px;
  color: rgba(255, 255, 255, 0.45);
}

/* KPI Kartları (Giden & Gelen) */
.fatura-kpi-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
  margin-bottom: 14px;
}
.fatura-kpi-card {
  padding: 18px 16px;
  border: 1px solid var(--dsx-bd-ffffff-a090);
  border-radius: 22px;
  background: var(--ds-surface);
  box-shadow: var(--ds-shadow-bento, 0 10px 24px rgba(16, 24, 40, 0.08));
  cursor: pointer;
  transition: transform 0.18s ease;
}
.fatura-kpi-card:active {
  transform: scale(0.98);
}
.fatura-kpi-card header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 8px;
}
.fatura-kpi-tag {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.08em;
  color: var(--ds-muted);
  text-transform: uppercase;
}
.fatura-kpi-icon {
  display: grid;
  place-items: center;
  width: 32px;
  height: 32px;
  border-radius: 10px;
}
.fatura-kpi-icon--emerald {
  background: var(--ds-emerald-soft);
  color: var(--ds-emerald-ink);
}
.fatura-kpi-icon--sky {
  background: var(--ds-blue-soft);
  color: var(--ds-blue-ink);
}
.fatura-kpi-amount {
  display: block;
  font-size: 18px;
  font-weight: 800;
  line-height: 22px;
  letter-spacing: -0.03em;
  color: var(--ds-ink);
}
.fatura-kpi-count {
  display: block;
  font-size: 11px;
  font-weight: 600;
  color: var(--ds-muted);
  margin-top: 3px;
}
.fatura-kpi-sub {
  margin-top: 10px;
  padding-top: 8px;
  border-top: 1px solid var(--dsx-bd-000000-a005);
  font-size: 11px;
}
.fatura-kpi-sub div {
  display: flex;
  justify-content: space-between;
  margin-top: 3px;
  color: var(--ds-muted);
}
.fatura-kpi-card footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 10px;
  font-size: 11px;
  font-weight: 700;
  color: var(--ds-ink);
}

/* Net KDV Durumu & Gauge Kartı */
.fatura-kdv-card {
  padding: 20px 20px 18px;
  border-radius: var(--ds-radius-card, 28px);
  margin-bottom: 14px;
  background: var(--ds-surface);
  border: 1px solid var(--dsx-bd-ffffff-a090);
  box-shadow: var(--ds-shadow-bento, 0 10px 24px rgba(16, 24, 40, 0.08));
}
.fatura-kdv-card--payable {
  border-top: 4px solid var(--ds-danger);
}
.fatura-kdv-card--devreden {
  border-top: 4px solid var(--ds-emerald);
}
.fatura-kdv-card header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 6px;
}
.fatura-kdv-card__badge {
  display: flex;
  align-items: center;
  gap: 6px;
}
.fatura-kdv-card--payable .fatura-kdv-card__badge {
  color: var(--ds-danger-ink);
}
.fatura-kdv-card--devreden .fatura-kdv-card__badge {
  color: var(--ds-emerald-ink);
}
.fatura-kdv-card__badge strong {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}
.fatura-kdv-status {
  font-size: 11px;
  font-weight: 600;
  color: var(--ds-muted);
}

.fatura-kdv-amount-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  margin-top: 6px;
}

.fatura-kdv-amount {
  font-size: 26px;
  font-weight: 800;
  line-height: 30px;
  letter-spacing: -0.04em;
}
.fatura-kdv-card--payable .fatura-kdv-amount {
  color: var(--ds-danger-ink);
}
.fatura-kdv-card--devreden .fatura-kdv-amount {
  color: var(--ds-emerald-ink);
}

.fatura-kdv-indicator-pill {
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 10.5px;
  font-weight: 700;
  white-space: nowrap;
}
.fatura-kdv-indicator-pill--danger {
  background: var(--ds-danger-soft);
  color: var(--ds-danger-ink);
}
.fatura-kdv-indicator-pill--emerald {
  background: var(--ds-emerald-soft);
  color: var(--ds-emerald-ink);
}

/* KDV Karşılama İlerleme Skalası (Gauge) */
.fatura-kdv-gauge-wrap {
  margin-top: 14px;
  padding: 12px 14px;
  border-radius: 18px;
  background: var(--ds-surface-subtle);
  border: 1px solid var(--dsx-bd-000000-a004);
}

.fatura-kdv-gauge-meta {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 11.5px;
}

.fatura-kdv-gauge-title {
  font-weight: 600;
  color: var(--ds-muted);
}

.fatura-kdv-gauge-pct {
  font-weight: 800;
  font-size: 13px;
}

.fatura-kdv-gauge-track {
  height: 8px;
  border-radius: 999px;
  background: var(--dsx-bg-000000-a008);
  margin-top: 6px;
  overflow: hidden;
}

.fatura-kdv-gauge-fill {
  height: 100%;
  border-radius: 999px;
  transition: width 0.4s ease;
}

.fatura-kdv-gauge-fill--full {
  background: linear-gradient(90deg, #10b981, #059669);
}

.fatura-kdv-gauge-fill--partial {
  background: linear-gradient(90deg, #f59e0b, #ef4444);
}

.fatura-kdv-gauge-desc {
  margin: 6px 0 0;
  font-size: 10.5px;
  line-height: 1.4;
  color: var(--ds-slate);
}

.fatura-kdv-breakdown {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 10px;
  margin-top: 12px;
}

.fatura-kdv-box {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border-radius: 16px;
  background: var(--ds-soft);
  border: 1px solid var(--ds-line);
}

.fatura-kdv-box__icon {
  display: grid;
  place-items: center;
  width: 28px;
  height: 28px;
  border-radius: 8px;
  flex-shrink: 0;
}

.fatura-kdv-box--hesaplanan .fatura-kdv-box__icon {
  background: var(--ds-emerald-soft);
  color: var(--ds-emerald-ink);
}

.fatura-kdv-box--indirilecek .fatura-kdv-box__icon {
  background: var(--ds-blue-soft);
  color: var(--ds-blue-ink);
}

.fatura-kdv-box span {
  display: block;
  color: var(--ds-muted);
  font-size: 10.5px;
}

.fatura-kdv-box b {
  display: block;
  margin-top: 1px;
  font-size: 12.5px;
  font-weight: 800;
  color: var(--ds-ink);
}

/* 3'lü Finansal Bento Metrikler Şeridi */
.fatura-metrics-strip {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 10px;
  margin-bottom: 14px;
}

.fatura-metric-cell {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  padding: 14px 8px;
  border-radius: 20px;
  background: var(--ds-surface);
  border: 1px solid var(--dsx-bd-ffffff-a090);
  box-shadow: var(--ds-shadow-bento, 0 10px 24px rgba(16, 24, 40, 0.08));
}

.fatura-metric-cell__icon {
  display: grid;
  place-items: center;
  width: 32px;
  height: 32px;
  border-radius: 10px;
  margin-bottom: 6px;
}

.fatura-metric-cell__label {
  font-size: 10px;
  font-weight: 700;
  color: var(--ds-muted);
  line-height: 1.25;
}

.fatura-metric-cell__value {
  font-size: 13.5px;
  font-weight: 800;
  color: var(--ds-ink);
  margin-top: 4px;
  letter-spacing: -0.02em;
}

/* Hızlı Modül Menüleri Grid */
.fatura-quick-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
  margin-bottom: 22px;
}
.fatura-quick-item {
  display: flex;
  flex-direction: column;
  padding: 16px;
  border: 1px solid var(--dsx-bd-ffffff-a090);
  border-radius: 20px;
  background: var(--ds-surface);
  box-shadow: var(--ds-shadow-bento, 0 10px 24px rgba(16, 24, 40, 0.08));
  text-align: left;
  cursor: pointer;
  transition: transform 0.18s ease;
}
.fatura-quick-item:active {
  transform: scale(0.97);
}
.fatura-quick-item__icon {
  display: grid;
  place-items: center;
  width: 38px;
  height: 38px;
  border-radius: 12px;
  margin-bottom: 10px;
}
.fatura-quick-item strong {
  font-size: 13px;
  font-weight: 700;
  color: var(--ds-ink);
}
.fatura-quick-item small {
  font-size: 10px;
  color: var(--ds-muted);
  margin-top: 2px;
}

/* Son Faturalar Akışı */
.fatura-recent-section {
  margin-top: 10px;
}
.fatura-recent-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
}
.fatura-recent-header h2 {
  font-size: 15px;
  font-weight: 700;
  color: var(--ds-ink);
  letter-spacing: -0.02em;
  margin: 0;
}
.fatura-recent-pills {
  display: flex;
  gap: 4px;
  background: var(--dsx-bg-ffffff-a070);
  padding: 3px;
  border-radius: 999px;
  border: 1px solid var(--ds-line);
}
.fatura-recent-pills button {
  padding: 4px 10px;
  border: 0;
  border-radius: 999px;
  background: transparent;
  color: var(--ds-slate);
  font: 600 10px 'Space Grotesk', sans-serif;
  cursor: pointer;
}
.fatura-recent-pills button.is-active {
  background: var(--ds-primary);
  color: var(--ds-ink-light);
}
.fatura-recent-list {
  display: grid;
  gap: 9px;
}
.fatura-row-card {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 16px 18px;
  border: 1px solid var(--dsx-bd-ffffff-a090);
  border-radius: 22px;
  background: var(--ds-surface);
  box-shadow: var(--ds-shadow-bento, 0 10px 24px rgba(16, 24, 40, 0.08));
  cursor: pointer;
  transition: transform 0.18s ease;
}
.fatura-row-card:active {
  transform: scale(0.985);
}
.fatura-row-card__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}
.fatura-row-card__meta {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
  flex-wrap: wrap;
}
.fatura-no-tag {
  display: inline-flex;
  align-items: center;
  padding: 3px 8px;
  border-radius: 8px;
  font-family: monospace;
  font-size: 11.5px;
  font-weight: 700;
  letter-spacing: -0.01em;
}
.fatura-no-tag--sky {
  background: var(--ds-blue-soft);
  color: var(--ds-blue-ink);
  border: 1px solid rgba(2, 132, 199, 0.16);
}
.fatura-no-tag--emerald {
  background: var(--ds-emerald-soft);
  color: var(--ds-emerald-ink);
  border: 1px solid rgba(5, 150, 105, 0.16);
}
.fatura-date-pill {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  font-weight: 600;
  color: var(--ds-muted);
}
.fatura-row-card__body {
  min-width: 0;
}
.fatura-row-card__customer {
  margin: 0;
  font-size: 13.5px;
  font-weight: 700;
  color: var(--ds-ink);
  line-height: 1.35;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  word-break: break-word;
}
.fatura-row-card__footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding-top: 10px;
  border-top: 1px solid var(--dsx-bd-000000-a005);
}
.fatura-row-card__amount-wrap {
  display: flex;
  flex-direction: column;
  gap: 1px;
  min-width: 0;
}
.fatura-amount-label {
  font-size: 10px;
  font-weight: 700;
  color: var(--ds-muted);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}
.fatura-amount-value {
  font-size: 16px;
  font-weight: 800;
  color: var(--ds-ink);
  letter-spacing: -0.02em;
  line-height: 1.2;
}
.fatura-view-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 6px 11px;
  border-radius: 999px;
  background: var(--ds-surface-subtle);
  color: var(--ds-ink);
  font-size: 11px;
  font-weight: 700;
  border: 1px solid var(--dsx-bd-000000-a006);
  transition: background 0.15s ease, color 0.15s ease;
  flex-shrink: 0;
}
.fatura-view-pill:active {
  background: var(--ds-canvas);
}

/* ==========================================================================
   LİSTE GÖRÜNÜMÜ: MİNİMAL BENTO ÖZET KARTI & FİLTRELER
   ========================================================================== */

/* Minimal Bento Özet Kartı (Giden / Gelen / Taslak) */
.fatura-mini-bento-strip {
  position: relative;
  border: 1px solid var(--dsx-bd-ffffff-a095);
  border-radius: 20px;
  background: var(--ds-surface);
  box-shadow: var(--ds-shadow-bento, 0 8px 20px rgba(16, 24, 40, 0.06));
  margin-bottom: 12px;
  overflow: hidden;
}

.fatura-mini-strip__main {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 11px 14px;
}

.fatura-mini-strip__info {
  display: flex;
  align-items: center;
  gap: 9px;
  min-width: 0;
}

.fatura-mini-strip__icon {
  display: grid;
  place-items: center;
  width: 32px;
  height: 32px;
  border-radius: 10px;
  flex-shrink: 0;
}
.fatura-mini-strip__icon--emerald {
  background: var(--ds-emerald-soft);
  color: var(--ds-emerald-ink);
}
.fatura-mini-strip__icon--sky {
  background: var(--ds-blue-soft);
  color: var(--ds-blue-ink);
}
.fatura-mini-strip__icon--amber {
  background: var(--ds-amber-soft);
  color: var(--ds-amber-ink);
}

.fatura-mini-strip__text {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.fatura-mini-strip__tag {
  font-size: 9.5px;
  font-weight: 700;
  letter-spacing: 0.06em;
  color: var(--ds-muted);
  text-transform: uppercase;
  line-height: 1.1;
}

.fatura-mini-strip__amount {
  font-size: 15.5px;
  font-weight: 800;
  color: var(--ds-ink);
  letter-spacing: -0.03em;
  margin-top: 1px;
  line-height: 1.2;
}

.fatura-mini-strip__badges {
  display: flex;
  align-items: center;
  gap: 5px;
  flex-wrap: wrap;
  justify-content: flex-end;
}

.fatura-mini-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 3.5px 8px;
  border-radius: 999px;
  font-size: 10.5px;
  font-weight: 700;
  white-space: nowrap;
}
.fatura-mini-badge--count {
  background: var(--dsx-bg-000000-a005);
  color: var(--ds-ink);
}
.fatura-mini-badge--success {
  background: var(--ds-emerald-soft);
  color: var(--ds-emerald-ink);
}
.fatura-mini-badge--warning {
  background: var(--ds-amber-soft);
  color: var(--ds-amber-ink);
}
.fatura-mini-badge--danger {
  background: var(--ds-danger-soft);
  color: var(--ds-danger-ink);
}
.fatura-mini-badge--neutral {
  background: var(--ds-surface-subtle);
  color: var(--ds-slate);
}

/* 3px Dağılım Çizgisi */
.fatura-mini-strip__track {
  display: flex;
  height: 3px;
  width: 100%;
  background: var(--dsx-bg-000000-a004);
}
.fatura-mini-strip__fill {
  height: 100%;
  transition: width 0.3s ease;
}
.bg-emerald { background: var(--ds-emerald); }
.bg-amber { background: var(--ds-amber); }
.bg-danger { background: var(--ds-danger); }

/* Arama ve Filtre Grubu */
.fatura-search-group {
  display: grid;
  gap: 8px;
  margin-bottom: 12px;
}
.fatura-search-box {
  position: relative;
  display: flex;
  align-items: center;
  height: 42px;
  border: 1px solid var(--dsx-bd-ffffff-a090);
  border-radius: 999px;
  background: var(--ds-surface);
  box-shadow: var(--ds-shadow-bento, 0 6px 18px rgba(16, 24, 40, 0.05));
  padding: 0 14px;
}
.fatura-search-box--unified {
  height: 46px;
  border-radius: 16px;
  background: var(--ds-surface);
  border: 1px solid var(--dsx-bd-ffffff-a095);
  box-shadow: 0 4px 18px rgba(15, 23, 42, 0.045);
  padding: 0 12px 0 16px;
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.fatura-search-box--unified:focus-within {
  border-color: rgba(67, 56, 202, 0.35);
  box-shadow: 0 0 0 3px rgba(67, 56, 202, 0.08), 0 8px 24px rgba(15, 23, 42, 0.06);
}
.fatura-search-box__icon {
  flex-shrink: 0;
  color: var(--ds-muted);
  margin-right: 8px;
}
.fatura-search-box input {
  flex: 1;
  border: 0;
  outline: 0;
  background: transparent;
  color: var(--ds-ink);
  font-size: 13px;
  font-weight: 500;
}
.fatura-search-clear-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 26px;
  height: 26px;
  border-radius: 50%;
  border: 0;
  background: var(--ds-soft, var(--dsx-bg-f1f5f9));
  color: var(--ds-muted, var(--dsx-fg-64748b));
  cursor: pointer;
  transition: all 0.15s ease;
  margin-left: 6px;
}
.fatura-search-clear-btn:active {
  background: var(--dsx-bg-e2e8f0);
  transform: scale(0.92);
}
.fatura-search-box button {
  border: 0;
  background: transparent;
  color: var(--ds-muted-light);
  cursor: pointer;
}
.fatura-search-box--product {
  background: var(--ds-surface-subtle);
  border-color: var(--dsx-bd-000000-a006);
}
.fatura-product-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 3px 10px;
  border-radius: 999px;
  background: var(--ds-purple-soft);
  color: var(--ds-purple-ink);
  font-size: 10.5px;
  font-weight: 700;
}

/* Durum Filtre Hapları */
.fatura-filter-pills {
  display: flex;
  gap: 6px;
  overflow-x: auto;
  scrollbar-width: none;
  padding-bottom: 2px;
}
.fatura-filter-pills::-webkit-scrollbar {
  display: none;
}
.fatura-filter-pills button {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 7px 13px;
  border: 1px solid var(--dsx-bd-ffffff-a090);
  border-radius: 999px;
  background: var(--ds-surface);
  color: var(--ds-muted);
  font-size: 11px;
  font-weight: 700;
  box-shadow: var(--ds-shadow-bento, 0 6px 16px rgba(16, 24, 40, 0.05));
  cursor: pointer;
  transition: all 0.18s ease;
}
.fatura-filter-pills button.is-active {
  background: var(--ds-surface-dark);
  color: var(--ds-ink-light);
  border-color: var(--ds-surface-dark);
  box-shadow: 0 4px 12px rgba(25, 29, 36, 0.25);
}
.fatura-pill-num {
  display: inline-block;
  padding: 1px 5px;
  border-radius: 999px;
  font-size: 9.5px;
  font-weight: 800;
  background: var(--dsx-bg-000000-a007);
}
.fatura-filter-pills button.is-active .fatura-pill-num {
  background: rgba(255, 255, 255, 0.22);
  color: #ffffff;
}

/* UI/UX Pro Max Bento Boş Durum Kartı */
.fatura-empty-bento {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  padding: 36px 22px 32px;
  border-radius: 26px;
  background: var(--ds-surface);
  border: 1px solid var(--dsx-bd-ffffff-a095);
  box-shadow: 0 10px 30px rgba(15, 23, 42, 0.05);
  margin: 12px 0 24px;
}
.fatura-empty-bento__icon-wrap {
  width: 64px;
  height: 64px;
  border-radius: 20px;
  background: var(--ds-blue-soft, var(--dsx-bg-e0f2fe));
  color: var(--ds-blue-ink, var(--dsx-fg-0284c7));
  display: grid;
  place-items: center;
  margin-bottom: 16px;
  box-shadow: 0 8px 20px rgba(2, 132, 199, 0.12);
  position: relative;
}
.fatura-empty-bento__icon-wrap::after {
  content: '';
  position: absolute;
  inset: -5px;
  border-radius: 24px;
  border: 1.5px dashed rgba(2, 132, 199, 0.25);
}
.fatura-empty-bento__icon-wrap--amber {
  background: var(--ds-amber-soft, var(--dsx-bg-fef3c7));
  color: var(--ds-amber-ink, var(--dsx-fg-d97706));
  box-shadow: 0 8px 20px rgba(217, 119, 6, 0.14);
}
.fatura-empty-bento__icon-wrap--amber::after {
  border-color: rgba(217, 119, 6, 0.28);
}
.fatura-empty-bento__icon-wrap--sky {
  background: var(--ds-blue-soft, var(--dsx-bg-e0f2fe));
  color: var(--ds-blue-ink, var(--dsx-fg-0284c7));
}
.fatura-empty-bento__title {
  margin: 0 0 8px;
  font-size: 17px;
  font-weight: 800;
  color: var(--ds-ink, var(--dsx-fg-0f172a));
  letter-spacing: -0.02em;
}
.fatura-empty-bento__desc {
  margin: 0 0 20px;
  font-size: 12.5px;
  line-height: 1.55;
  color: var(--ds-muted, var(--dsx-fg-64748b));
  max-width: 320px;
}
.fatura-empty-bento__cta-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 46px;
  padding: 0 24px;
  border: 0;
  border-radius: 14px;
  background: linear-gradient(135deg, var(--ds-primary, #4338ca), #6366f1);
  color: #ffffff;
  font-size: 13.5px;
  font-weight: 700;
  cursor: pointer;
  box-shadow: 0 8px 22px rgba(67, 56, 202, 0.28);
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.fatura-empty-bento__cta-btn:active {
  transform: scale(0.97);
  box-shadow: 0 4px 12px rgba(67, 56, 202, 0.22);
}
.fatura-empty-bento__badges {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-top: 22px;
  padding-top: 18px;
  border-top: 1px dashed var(--ds-line, var(--dsx-bd-e2e8f0));
  width: 100%;
}
.fatura-tip-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  font-size: 11px;
  font-weight: 600;
  color: var(--ds-slate, var(--dsx-fg-475569));
}

/* Fatura Kartları */
.fatura-cards-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
  width: 100%;
  max-width: 100%;
  min-width: 0;
  box-sizing: border-box;
  padding-bottom: 50px;
}
.fatura-card {
  display: flex;
  flex-direction: column;
  gap: 10px;
  width: 100%;
  max-width: 100%;
  min-width: 0;
  box-sizing: border-box;
  overflow: hidden;
  padding: 16px 18px;
  border: 1px solid var(--dsx-bd-ffffff-a090);
  border-radius: 22px;
  background: var(--ds-surface);
  box-shadow: var(--ds-shadow-bento, 0 10px 24px rgba(16, 24, 40, 0.08));
  cursor: pointer;
  transition: transform 0.18s ease;
}
.fatura-card:active {
  transform: scale(0.985);
}
.fatura-card__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  width: 100%;
  min-width: 0;
  box-sizing: border-box;
}
.fatura-card__badge-group {
  display: flex;
  align-items: center;
  gap: 6px;
  min-width: 0;
  flex-wrap: wrap;
}
.fatura-card__status {
  flex-shrink: 0;
}
.fatura-no {
  font-family: monospace;
  font-size: 12px;
  font-weight: 700;
  color: var(--ds-primary-ink);
}
.fatura-no--emerald {
  color: var(--ds-emerald-ink);
}
.fatura-type-tag {
  padding: 2.5px 7px;
  border-radius: 6px;
  background: rgba(100, 116, 139, 0.08);
  color: var(--ds-slate);
  font: 700 8.5px 'Space Grotesk', sans-serif;
  letter-spacing: 0.04em;
}
.fatura-card__body {
  width: 100%;
  min-width: 0;
  box-sizing: border-box;
}
.fatura-customer-name {
  margin: 0;
  font-size: 13.5px;
  font-weight: 700;
  color: var(--ds-ink);
  line-height: 1.35;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  word-break: break-word;
}
.fatura-customer-meta {
  margin: 4px 0 0;
  font: 500 10.5px 'Space Grotesk', sans-serif;
  color: var(--ds-muted);
  display: flex;
  align-items: center;
  gap: 5px;
  flex-wrap: wrap;
}
.fatura-card__items-preview {
  display: flex;
  align-items: center;
  gap: 6px;
  width: 100%;
  max-width: 100%;
  min-width: 0;
  box-sizing: border-box;
  margin-top: 4px;
  padding: 6px 11px;
  border-radius: 10px;
  background: var(--dsx-bg-f5f3ff-a070);
  font-size: 11px;
  color: var(--ds-slate);
  overflow: hidden;
}
.fatura-card__items-preview strong {
  flex-shrink: 0;
  font-weight: 700;
  color: var(--ds-purple-ink);
}
.fatura-card__items-preview span {
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  min-width: 0;
  flex: 1;
}
.fatura-card__footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  width: 100%;
  min-width: 0;
  box-sizing: border-box;
  margin-top: 6px;
  padding-top: 10px;
  border-top: 1px solid rgba(226, 232, 240, 0.85);
}
.fatura-card__amount-box {
  display: flex;
  flex-direction: column;
  gap: 1px;
  min-width: 0;
  flex-shrink: 0;
}
.fatura-card__actions {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-shrink: 0;
  flex-wrap: wrap;
  justify-content: flex-end;
}
.fatura-payment-tag {
  padding: 3px 7px;
  border-radius: 999px;
  font: 700 9px 'Space Grotesk', sans-serif;
  background: var(--ds-soft);
  color: var(--ds-muted);
}
.fatura-payment-tag.is-paid {
  background: var(--ds-emerald-soft);
  color: var(--ds-emerald-ink);
}
.fatura-payment-tag.is-partial {
  background: var(--ds-amber-soft);
  color: var(--ds-amber-ink);
}

.fatura-meta-pill {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  color: var(--ds-slate);
  font-size: 11px;
}
.fatura-meta-vkn {
  font-family: monospace;
  font-size: 10.5px;
  color: var(--ds-muted);
}

.fatura-action-btn {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  height: 30px;
  padding: 0 11px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 700;
  cursor: pointer;
  border: 1px solid var(--ds-line);
  background: var(--ds-surface);
  color: var(--ds-ink);
  transition: all 0.15s ease;
  white-space: nowrap;
}
.fatura-action-btn:active {
  transform: scale(0.96);
}
.fatura-action-btn--primary {
  background: var(--ds-emerald-soft);
  color: var(--ds-emerald-ink);
  border-color: rgba(16, 185, 129, 0.3);
}
.fatura-action-btn--view {
  background: var(--ds-surface-subtle);
  border-color: var(--dsx-bd-000000-a008);
}
.fatura-action-btn--sync {
  background: rgba(14, 165, 233, 0.1);
  color: var(--dsx-fg-0284c7);
  border-color: rgba(14, 165, 233, 0.3);
}
.fatura-action-btn--sync:active {
  transform: scale(0.96);
  background: rgba(14, 165, 233, 0.18);
}
.fatura-cari-synced-pill {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  height: 28px;
  padding: 0 9px;
  border-radius: 999px;
  font-size: 10.5px;
  font-weight: 700;
  background: rgba(16, 185, 129, 0.1);
  color: var(--dsx-fg-059669);
  border: 1px solid rgba(16, 185, 129, 0.28);
  white-space: nowrap;
}
.fatura-icon-btn--sync {
  background: rgba(14, 165, 233, 0.12) !important;
  color: var(--dsx-fg-0284c7) !important;
  border-color: rgba(14, 165, 233, 0.3) !important;
}
.fatura-preview-synced-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  height: 34px;
  padding: 0 10px;
  border-radius: 999px;
  font-size: 11.5px;
  font-weight: 700;
  background: rgba(16, 185, 129, 0.12);
  color: var(--dsx-fg-059669);
  border: 1px solid rgba(16, 185, 129, 0.28);
  white-space: nowrap;
}

.fatura-mini-btn {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  height: 30px;
  padding: 0 10px;
  border: 1px solid var(--ds-line);
  border-radius: 999px;
  background: var(--ds-surface);
  color: var(--ds-slate);
  font: 700 10.5px 'Space Grotesk', sans-serif;
  cursor: pointer;
}
.fatura-mini-btn--primary {
  background: var(--ds-primary);
  color: var(--ds-ink-light);
  border-color: var(--ds-primary);
}
.fatura-icon-action-btn {
  display: grid;
  place-items: center;
  width: 32px;
  height: 32px;
  border: 1px solid var(--ds-line);
  border-radius: 50%;
  background: var(--ds-surface);
  color: var(--ds-slate);
  cursor: pointer;
  transition: transform 0.15s ease, background 0.15s ease, border-color 0.15s ease;
  flex-shrink: 0;
  box-sizing: border-box;
}
.fatura-icon-action-btn:active {
  transform: scale(0.92);
}
.fatura-icon-action-btn--primary {
  color: #ffffff;
  background: linear-gradient(135deg, var(--ds-primary, #4338ca), #6366f1);
  border-color: transparent;
  box-shadow: 0 2px 8px rgba(67, 56, 202, 0.25);
}
.fatura-icon-action-btn--danger {
  color: var(--ds-danger-ink);
  background: var(--ds-danger-soft);
  border-color: rgba(239, 68, 68, 0.2);
}
.fatura-icon-action-btn--sync {
  color: var(--dsx-fg-0284c7);
  background: rgba(14, 165, 233, 0.1);
  border-color: rgba(14, 165, 233, 0.3);
}
.fatura-icon-action-btn--sync:disabled,
.fatura-icon-btn--sync:disabled {
  opacity: 0.4;
  cursor: not-allowed;
  pointer-events: none;
}
.fatura-icon-action-btn--sync:active {
  background: rgba(14, 165, 233, 0.2);
}
.fatura-icon-action-btn--synced {
  color: var(--dsx-fg-059669);
  background: rgba(16, 185, 129, 0.1);
  border-color: rgba(16, 185, 129, 0.28);
  cursor: default;
}
.fatura-icon-action-btn--view {
  color: var(--ds-primary-ink, #4338ca);
  background: rgba(79, 70, 229, 0.08);
  border-color: rgba(79, 70, 229, 0.22);
}
.fatura-icon-action-btn--view:active {
  background: rgba(79, 70, 229, 0.16);
}

.fatura-row-card__actions {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-shrink: 0;
}

/* Rozetler */
.fatura-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 3.5px 9px;
  border-radius: 999px;
  font: 700 9.5px 'Space Grotesk', sans-serif;
  letter-spacing: 0.02em;
  white-space: nowrap;
}
.fatura-badge-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background-color: currentColor;
  flex-shrink: 0;
}
.fatura-badge--success {
  background: var(--ds-emerald-soft);
  color: var(--ds-emerald-ink);
  border: 1px solid rgba(5, 150, 105, 0.2);
}
.fatura-badge--warning {
  background: var(--ds-amber-soft);
  color: var(--ds-amber-ink);
  border: 1px solid rgba(217, 119, 6, 0.2);
}
.fatura-badge--danger {
  background: var(--ds-danger-soft);
  color: var(--ds-danger-ink);
  border: 1px solid rgba(220, 38, 38, 0.2);
}
.fatura-badge--neutral {
  background: var(--ds-surface-subtle);
  color: var(--ds-muted);
  border: 1px solid rgba(100, 116, 139, 0.15);
}

/* Modallar & Bottom Sheet Taban */
.fatura-modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 1300;
  background: rgba(17, 22, 27, 0.6);
  backdrop-filter: blur(8px);
  display: flex;
  align-items: flex-end;
  justify-content: center;
}
.fatura-preview-sheet,
.fatura-period-sheet,
.fatura-response-sheet,
.fatura-wizard-sheet {
  width: 100%;
  max-width: 520px;
  background: var(--ds-surface);
  border-radius: 28px 28px 0 0;
  box-shadow: var(--ds-shadow-float);
  overflow: hidden;
  display: flex;
  flex-direction: column;
  animation: slideUp 0.25s ease-out;
}
@keyframes slideUp {
  from { transform: translateY(100%); }
  to { transform: translateY(0); }
}

/* Önizleme Sheet (Tam Ekran Yakını) */
.fatura-preview-sheet {
  height: 94vh;
}
.fatura-preview-header {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
  padding: 14px 18px;
  border-bottom: 1px solid var(--ds-line);
}
.fatura-preview-header__title {
  display: flex;
  align-items: center;
  gap: 8px;
  flex: 1;
  min-width: 0;
}
.fatura-preview-header__title h3 {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.fatura-preview-close {
  width: 44px;
  height: 44px;
}
.fatura-preview-header__title h3 {
  margin: 0;
  font-size: 15px;
  font-weight: 700;
}
.fatura-preview-header__actions {
  display: flex;
  align-items: center;
  gap: 6px;
  flex: 0 0 100%;
  overflow-x: auto;
}
.fatura-icon-btn {
  display: grid;
  place-items: center;
  width: 34px;
  height: 34px;
  border-radius: 50%;
  border: 1px solid var(--ds-line, var(--dsx-bd-e2e8f0));
  background: var(--ds-surface, var(--dsx-bg-ffffff));
  color: var(--ds-slate, var(--dsx-fg-475569));
  cursor: pointer;
  text-decoration: none;
  transition: all 0.15s ease;
  flex-shrink: 0;
}
.fatura-icon-btn:active {
  background: var(--ds-soft, var(--dsx-bg-f1f5f9));
  transform: scale(0.94);
}
.fatura-icon-btn.is-loading,
.fatura-icon-action-btn.is-loading {
  opacity: 0.7;
  pointer-events: none;
}
.fatura-preview-body {
  flex: 1;
  background: #334155; /* Belge zeminini belirginleştiren nötr koyu arduvaz tuval */
  overflow: hidden;
  position: relative;
}
.fatura-viewer-viewport {
  width: 100%;
  height: 100%;
  overflow-x: auto;
  overflow-y: auto;
  -webkit-overflow-scrolling: touch;
  display: flex;
  justify-content: center;
  align-items: flex-start;
  padding: 12px 8px 84px; /* Alttaki yüzen yakınlaştırma çubuğu için boşluk */
  box-sizing: border-box;
  touch-action: pan-x pan-y pinch-zoom;
}
.fatura-viewer-scaler {
  position: relative;
  overflow: hidden;
  box-shadow: 0 10px 32px rgba(0, 0, 0, 0.35);
  border-radius: 6px;
  background: var(--dsx-bg-ffffff);
  flex-shrink: 0;
  transition: width 0.12s ease-out, height 0.12s ease-out;
}
.fatura-preview-iframe {
  position: absolute;
  top: 0;
  left: 0;
  border: 0;
  background: var(--dsx-bg-ffffff);
}
.fatura-preview-loading {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 10px;
  color: var(--dsx-fg-94a3b8);
  font-weight: 600;
  background: rgba(15, 23, 42, 0.7);
  z-index: 5;
}

/* Floating Modern Zoom Toolbar Dock */
.fatura-zoom-dock {
  position: absolute;
  bottom: 16px;
  left: 50%;
  transform: translateX(-50%);
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 5px 8px;
  border-radius: 999px;
  background: rgba(15, 23, 42, 0.88);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  border: 1px solid rgba(255, 255, 255, 0.16);
  box-shadow: 0 10px 28px rgba(0, 0, 0, 0.35);
  z-index: 10;
}
.fatura-zoom-dock-btn {
  display: grid;
  place-items: center;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  border: 0;
  background: transparent;
  color: #f1f5f9;
  cursor: pointer;
  transition: all 0.15s ease;
}
.fatura-zoom-dock-btn:active:not(:disabled) {
  background: rgba(255, 255, 255, 0.2);
  transform: scale(0.92);
}
.fatura-zoom-dock-btn:disabled {
  opacity: 0.35;
  cursor: not-allowed;
}
.fatura-zoom-dock-pill {
  min-width: 62px;
  height: 28px;
  padding: 0 10px;
  border-radius: 999px;
  border: 1px solid rgba(255, 255, 255, 0.15);
  background: rgba(255, 255, 255, 0.1);
  color: #f8fafc;
  font: 700 11px var(--ds-font-main);
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: all 0.15s ease;
}
.fatura-zoom-dock-pill:active {
  background: rgba(255, 255, 255, 0.25);
}
.fatura-zoom-dock-pill.is-active {
  background: rgba(99, 102, 241, 0.35);
  border-color: rgba(129, 140, 248, 0.5);
  color: #c7d2fe;
}
.fatura-zoom-dock-fit {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  height: 28px;
  padding: 0 10px;
  border-radius: 999px;
  border: 0;
  background: var(--ds-primary, #4338ca);
  color: #ffffff;
  font: 700 11px var(--ds-font-main);
  cursor: pointer;
  transition: all 0.15s ease;
  margin-left: 2px;
}
.fatura-zoom-dock-fit:active {
  transform: scale(0.94);
}
.fatura-zoom-dock-fit.is-active {
  background: #312e81;
  color: #e0e7ff;
}

/* Ortak Sheet Başlığı */
.fatura-sheet-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px 12px;
  border-bottom: 1px solid var(--ds-line);
}
.fatura-sheet-header__title {
  display: flex;
  align-items: center;
  gap: 8px;
}
.fatura-sheet-header__title h3 {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
}
.fatura-sheet-body {
  padding: 18px 20px 30px;
  overflow-y: auto;
  max-height: 80vh;
}

/* Dönem Sheet */
.fatura-year-selector {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
  font: 700 12px 'Space Grotesk', sans-serif;
}
.fatura-year-selector select {
  padding: 6px 12px;
  border-radius: 10px;
  border: 1px solid var(--ds-line);
  background: var(--ds-soft);
  font: 700 12px 'Space Grotesk', sans-serif;
}
.fatura-months-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 8px;
  margin-bottom: 18px;
}
.fatura-month-btn {
  padding: 10px 4px;
  border: 1px solid var(--ds-line);
  border-radius: 14px;
  background: var(--ds-soft);
  color: var(--ds-ink);
  font: 600 11px 'Space Grotesk', sans-serif;
  cursor: pointer;
}
.fatura-month-btn:active {
  background: var(--ds-primary);
  color: var(--ds-ink-light);
}
.fatura-custom-range h4 {
  margin: 0 0 8px;
  font-size: 13px;
  font-weight: 700;
}
.fatura-custom-inputs {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 10px;
}
.fatura-custom-inputs label {
  display: block;
  font: 700 9px 'Space Grotesk', sans-serif;
  color: var(--ds-muted);
  margin-bottom: 4px;
}
.fatura-custom-inputs input {
  width: 100%;
  padding: 8px 10px;
  border-radius: 12px;
  border: 1px solid var(--ds-line);
  background: var(--ds-soft);
  font-size: 12px;
}

/* Ticari Yanıt Sheet */
.fatura-response-target {
  padding: 14px;
  border-radius: 16px;
  background: var(--ds-soft);
  margin-bottom: 14px;
}
.fatura-response-target strong {
  display: block;
  font-size: 14px;
}
.fatura-response-target p {
  margin: 2px 0 4px;
  font-size: 12px;
  color: var(--ds-slate);
}
.fatura-response-target span {
  font: 700 13px 'Space Grotesk', sans-serif;
  color: var(--ds-primary-ink);
}
.fatura-response-types {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 10px;
  margin-bottom: 14px;
}
.fatura-response-type-btn {
  padding: 14px;
  border: 1.5px solid var(--ds-line);
  border-radius: 18px;
  background: var(--ds-surface);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  cursor: pointer;
}
.fatura-response-type-btn.is-selected.is-kabul {
  border-color: var(--ds-emerald-ink);
  background: var(--ds-emerald-soft);
  color: var(--ds-emerald-ink);
}
.fatura-response-type-btn.is-selected.is-red {
  border-color: var(--ds-danger-ink);
  background: var(--ds-danger-soft);
  color: var(--ds-danger-ink);
}

/* ==========================================================================
   SİHİRBAZ (WIZARD) & MODAL STİLLERİ (UI/UX PRO MAX)
   ========================================================================== */
.fatura-wizard-sheet {
  height: min(92vh, 840px);
  border-radius: 28px 28px 0 0;
  display: flex;
  flex-direction: column;
}
.fatura-wizard-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px 12px;
  border-bottom: 1px solid var(--ds-line, var(--dsx-bd-e2e8f0));
  background: var(--ds-surface, var(--dsx-bg-ffffff));
}
.fatura-wizard-header__info {
  display: flex;
  flex-direction: column;
  min-width: 0;
}
.fatura-wizard-tag {
  font: 800 9px 'Space Grotesk', sans-serif;
  color: var(--ds-primary-ink, #4338ca);
  letter-spacing: 0.1em;
  text-transform: uppercase;
}
.fatura-wizard-header h2 {
  margin: 1px 0 2px;
  font-size: 17px;
  font-weight: 800;
  color: var(--ds-ink, var(--dsx-fg-0f172a));
  letter-spacing: -0.02em;
}
.fatura-wizard-progress-text {
  margin: 0;
  font-size: 11px;
  color: var(--ds-muted, var(--dsx-fg-64748b));
}
.fatura-wizard-progress-text strong {
  color: var(--ds-primary-ink, #4338ca);
  margin-right: 4px;
}
.fatura-wizard-close-btn {
  display: grid;
  place-items: center;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  border: 0;
  background: var(--ds-soft, var(--dsx-bg-f1f5f9));
  color: var(--ds-muted, var(--dsx-fg-64748b));
  cursor: pointer;
  transition: all 0.15s ease;
  flex-shrink: 0;
}
.fatura-wizard-close-btn:active {
  background: var(--dsx-bg-e2e8f0);
}

/* Stepper Progress Bar & Steps */
.fatura-stepper-container {
  display: flex;
  flex-direction: column;
  background: var(--dsx-bg-f8fafc);
  border-bottom: 1px solid var(--ds-line, var(--dsx-bd-e2e8f0));
}
.fatura-stepper-progress-track {
  width: 100%;
  height: 3px;
  background: var(--dsx-bg-e2e8f0);
  overflow: hidden;
}
.fatura-stepper-progress-fill {
  height: 100%;
  background: linear-gradient(90deg, var(--ds-primary, #4338ca), #6366f1);
  transition: width 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
.fatura-wizard-steps {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  padding: 8px 12px 8px;
  gap: 4px;
}
.fatura-wizard-step-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  border: 0;
  background: transparent;
  cursor: pointer;
  padding: 4px 0;
  border-radius: 10px;
  transition: background 0.15s ease;
}
.step-indicator {
  display: grid;
  place-items: center;
  width: 26px;
  height: 26px;
  border-radius: 50%;
  background: var(--dsx-bg-e2e8f0);
  color: var(--ds-muted, var(--dsx-fg-64748b));
  font: 700 11px 'Space Grotesk', sans-serif;
  transition: all 0.25s ease;
}
.step-check {
  color: #ffffff;
}
.step-name {
  font: 600 10.5px var(--ds-font-main);
  color: var(--ds-muted, var(--dsx-fg-64748b));
  transition: color 0.2s ease;
}
.fatura-wizard-step-item.is-active .step-indicator {
  background: var(--ds-primary, #4338ca);
  color: #ffffff;
  box-shadow: 0 4px 12px rgba(67, 56, 202, 0.3);
}
.fatura-wizard-step-item.is-active .step-name {
  color: var(--ds-primary-ink, #4338ca);
  font-weight: 700;
}
.fatura-wizard-step-item.is-done .step-indicator {
  background: var(--ds-emerald, #10b981);
  color: #ffffff;
}
.fatura-wizard-step-item.is-done .step-name {
  color: var(--ds-emerald-ink, var(--dsx-fg-059669));
}

/* Wizard Body (Scrollbar Hidden!) */
.fatura-wizard-body {
  flex: 1;
  overflow-y: auto;
  padding: 16px 18px 24px;
  background: var(--ds-canvas, var(--dsx-bg-f8fafc));
  scrollbar-width: none;
  -ms-overflow-style: none;
}
.fatura-wizard-body::-webkit-scrollbar {
  display: none;
}

/* Wizard Step Intro Banner */
.wizard-step-panel {
  display: flex;
  flex-direction: column;
}
.wizard-step-intro {
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 0 0 16px;
  padding: 12px 14px;
  border: 1px solid var(--dsx-bd-ffffff-a090);
  border-radius: 16px;
  background: var(--dsx-bg-ffffff);
  box-shadow: 0 4px 14px rgba(15, 23, 42, 0.035);
}
.wizard-step-intro__icon {
  display: grid;
  width: 38px;
  height: 38px;
  flex: 0 0 38px;
  place-items: center;
  border-radius: 12px;
  color: var(--ds-blue-ink, var(--dsx-fg-0284c7));
  background: var(--ds-blue-soft, var(--dsx-bg-e0f2fe));
}
.wizard-step-intro__icon--purple {
  color: var(--ds-purple-ink, #7c3aed);
  background: var(--ds-purple-soft, var(--dsx-bg-ede9fe));
}
.wizard-step-intro__icon--amber {
  color: var(--ds-amber-ink, var(--dsx-fg-d97706));
  background: var(--ds-amber-soft, var(--dsx-bg-fef3c7));
}
.wizard-step-intro__icon--green {
  color: var(--ds-emerald-ink, var(--dsx-fg-059669));
  background: var(--ds-emerald-soft, var(--dsx-bg-d1fae5));
}
.wizard-step-intro h3 {
  margin: 0;
  font-size: 13.5px;
  font-weight: 800;
  color: var(--ds-ink, var(--dsx-fg-0f172a));
}
.wizard-step-intro p {
  margin: 2px 0 0;
  font-size: 11px;
  line-height: 1.35;
  color: var(--ds-muted, var(--dsx-fg-64748b));
}

/* Cari Arama & Dropdown */
.fatura-wizard-search-box {
  position: relative;
  display: flex;
  align-items: center;
  height: 44px;
  border: 1px solid var(--ds-line, var(--dsx-bd-e2e8f0));
  border-radius: 14px;
  background: var(--dsx-bg-ffffff);
  padding: 0 12px;
  box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
}
.fatura-wizard-search-box input {
  flex: 1;
  border: 0;
  outline: 0;
  background: transparent;
  color: var(--ds-ink);
  font-size: 12.5px;
  font-weight: 500;
}
.fatura-search-clear {
  border: 0;
  background: transparent;
  color: var(--ds-muted);
  cursor: pointer;
}
.fatura-customer-dropdown {
  max-height: 180px;
  overflow-y: auto;
  border: 1px solid var(--ds-line);
  border-radius: 14px;
  margin-top: 6px;
  background: var(--dsx-bg-ffffff);
  box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
}
.fatura-customer-item {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  padding: 10px 14px;
  border: 0;
  border-bottom: 1px solid var(--ds-line);
  background: transparent;
  text-align: left;
  cursor: pointer;
  transition: background 0.15s ease;
}
.fatura-customer-item:active {
  background: var(--ds-soft, var(--dsx-bg-f1f5f9));
}
.fatura-customer-item:last-child {
  border-bottom: 0;
}
.fatura-customer-item__avatar {
  width: 32px;
  height: 32px;
  flex-shrink: 0;
  border-radius: 10px;
  background: var(--ds-blue-soft, var(--dsx-bg-e0f2fe));
  color: var(--ds-blue-ink, var(--dsx-fg-0284c7));
  display: grid;
  place-items: center;
  font-weight: 800;
  font-size: 13px;
}
.fatura-customer-item__info {
  flex: 1;
  min-width: 0;
}
.fatura-customer-item__info strong {
  display: block;
  font-size: 12.5px;
  color: var(--ds-ink);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.fatura-customer-item__info small {
  display: block;
  font-size: 10.5px;
  color: var(--ds-muted);
}
.fatura-customer-item__arrow {
  color: var(--ds-muted);
  flex-shrink: 0;
}

/* Form Elemanları */
.fatura-form-fields {
  display: grid;
  gap: 12px;
}
.fatura-form-field label {
  display: block;
  font: 700 10px 'Space Grotesk', sans-serif;
  color: var(--ds-slate, var(--dsx-fg-475569));
  margin-bottom: 4px;
}
.fatura-form-field input,
.fatura-form-field select,
.fatura-form-field textarea {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid var(--ds-line, var(--dsx-bd-e2e8f0));
  border-radius: 14px;
  min-height: 44px;
  background: var(--dsx-bg-ffffff);
  font: 500 12.5px var(--ds-font-main);
  color: var(--ds-ink, var(--dsx-fg-0f172a));
  outline: 0;
  box-sizing: border-box;
  transition: border-color 0.18s ease;
}
.fatura-form-field textarea {
  min-height: 68px;
  resize: vertical;
}
.fatura-form-field input:focus,
.fatura-form-field select:focus,
.fatura-form-field textarea:focus {
  border-color: var(--ds-primary-ink, #4338ca);
  box-shadow: 0 0 0 3px rgba(67, 56, 202, 0.08);
}
.fatura-form-grid-2 {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 10px;
}
.fatura-form-grid-3 {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
}

/* Inline VKN / TCKN Aksiyon Butonu */
.fatura-input-inline-action {
  position: relative;
  display: flex;
  align-items: center;
}
.fatura-input-inline-action input {
  padding-right: 94px !important;
}
.fatura-inline-btn {
  position: absolute;
  right: 6px;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 6px 11px;
  border: 0;
  border-radius: 10px;
  background: var(--ds-primary-soft, var(--dsx-bg-eef2ff));
  color: var(--ds-primary-ink, #4338ca);
  font: 700 11px var(--ds-font-main);
  cursor: pointer;
  transition: all 0.2s ease;
}
.fatura-inline-btn:active {
  background: var(--dsx-bg-e0e7ff);
}
.fatura-inline-btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

/* Mükellef Bilgilendirme Rozeti */
.fatura-taxpayer-alert {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 14px;
  border-radius: 14px;
  font-size: 11px;
  margin-top: 4px;
}
.fatura-taxpayer-alert strong {
  display: block;
  font-size: 12px;
  margin-bottom: 2px;
}
.fatura-taxpayer-alert p {
  margin: 0;
  font-size: 10.5px;
  line-height: 1.35;
}
.fatura-taxpayer-alert--efatura {
  background: var(--ds-emerald-soft, var(--dsx-bg-d1fae5));
  color: var(--ds-emerald-ink, var(--dsx-fg-065f46));
  border: 1px solid rgba(16, 185, 129, 0.25);
}
.fatura-taxpayer-alert--earsiv {
  background: var(--ds-amber-soft, var(--dsx-bg-fef3c7));
  color: var(--ds-amber-ink, var(--dsx-fg-92400e));
  border: 1px solid rgba(245, 158, 11, 0.25);
}

/* Step 2: Segmented Pill & Pill Selector */
.fatura-segmented-pill-wrap {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 6px;
  padding: 4px;
  border-radius: 14px;
  background: var(--dsx-bg-f1f5f9);
  border: 1px solid var(--ds-line, var(--dsx-bd-e2e8f0));
}
.fatura-seg-pill-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  min-height: 38px;
  padding: 0 12px;
  border: 0;
  border-radius: 10px;
  background: transparent;
  color: var(--ds-slate, var(--dsx-fg-475569));
  font: 700 12px var(--ds-font-main);
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.fatura-seg-pill-btn.is-active {
  background: var(--dsx-bg-ffffff);
  color: var(--ds-primary-ink, #4338ca);
  box-shadow: 0 3px 10px rgba(15, 23, 42, 0.08);
}
.fatura-pill-selector {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.fatura-sel-pill {
  padding: 8px 14px;
  border: 1px solid var(--ds-line, var(--dsx-bd-e2e8f0));
  border-radius: 12px;
  background: var(--dsx-bg-ffffff);
  color: var(--ds-slate, var(--dsx-fg-475569));
  font: 600 11.5px var(--ds-font-main);
  cursor: pointer;
  transition: all 0.18s ease;
}
.fatura-sel-pill.is-active {
  border-color: var(--ds-primary-ink, #4338ca);
  background: var(--ds-primary-soft, var(--dsx-bg-eef2ff));
  color: var(--ds-primary-ink, #4338ca);
  font-weight: 700;
  box-shadow: 0 2px 8px rgba(67, 56, 202, 0.12);
}

/* Step 3: Kalemler Listesi */
.fatura-lines-container {
  display: grid;
  gap: 12px;
}
.fatura-line-card {
  padding: 14px;
  border: 1px solid var(--dsx-bd-e2e8f0);
  border-radius: 18px;
  background: var(--dsx-bg-ffffff);
  box-shadow: 0 4px 16px rgba(15, 23, 42, 0.04);
  display: grid;
  gap: 10px;
}
.fatura-line-card__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.line-index {
  display: inline-flex;
  align-items: center;
  padding: 3px 10px;
  border-radius: 999px;
  background: var(--ds-blue-soft, var(--dsx-bg-e0f2fe));
  color: var(--ds-blue-ink, var(--dsx-fg-0284c7));
  font: 700 11px 'Space Grotesk', sans-serif;
}
.fatura-quick-products-row {
  display: flex;
  align-items: center;
  gap: 5px;
  overflow-x: auto;
  font-size: 10px;
  color: var(--ds-muted);
  scrollbar-width: none;
}
.fatura-quick-products-row::-webkit-scrollbar {
  display: none;
}
.fatura-quick-prod-pill {
  flex-shrink: 0;
  padding: 4px 9px;
  border-radius: 999px;
  border: 1px solid var(--ds-line, var(--dsx-bd-e2e8f0));
  background: var(--dsx-bg-f8fafc);
  color: var(--ds-slate);
  font-size: 10px;
  cursor: pointer;
  transition: background 0.15s ease;
}
.fatura-quick-prod-pill:active {
  background: var(--dsx-bg-e2e8f0);
}
.fatura-line-total {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-top: 8px;
  border-top: 1px dashed var(--ds-line, var(--dsx-bd-e2e8f0));
  font-size: 11.5px;
}
.fatura-line-total__matrah span,
.fatura-line-total__sum span {
  color: var(--ds-muted);
  margin-right: 4px;
}
.fatura-line-total__matrah strong {
  color: var(--ds-slate);
}
.fatura-line-total__sum strong {
  color: var(--ds-primary-ink, #4338ca);
  font-size: 13px;
  font-weight: 700;
}
.fatura-add-line-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  min-height: 44px;
  border: 1.5px dashed rgba(67, 56, 202, 0.35);
  border-radius: 14px;
  background: rgba(67, 56, 202, 0.03);
  color: var(--ds-primary-ink, #4338ca);
  font: 700 12.5px var(--ds-font-main);
  cursor: pointer;
  transition: all 0.2s ease;
}
.fatura-add-line-btn:active {
  background: rgba(67, 56, 202, 0.08);
}

/* Step 4: Termal / Bento Makbuz Kartı */
.fatura-wizard-summary-card {
  padding: 18px 18px 20px;
  border-radius: 22px;
  background: var(--dsx-bg-ffffff);
  border: 1px solid var(--dsx-bd-e2e8f0);
  box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05);
}
.fatura-receipt-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
}
.fatura-receipt-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font: 800 10.5px 'Space Grotesk', sans-serif;
  color: var(--ds-primary-ink, #4338ca);
  letter-spacing: 0.05em;
}
.fatura-receipt-type-tag {
  display: inline-block;
  padding: 3px 8px;
  border-radius: 8px;
  background: var(--dsx-bg-f1f5f9);
  color: var(--ds-slate);
  font: 700 10px 'Space Grotesk', sans-serif;
}
.fatura-receipt-customer {
  margin-bottom: 14px;
  padding-bottom: 12px;
  border-bottom: 1px solid var(--ds-line);
}
.fatura-receipt-customer__name {
  display: block;
  font-size: 14px;
  font-weight: 700;
  color: var(--ds-ink);
  line-height: 1.35;
}
.fatura-receipt-customer__vkn {
  display: block;
  margin-top: 3px;
  font-size: 11px;
  color: var(--ds-muted);
}
.fatura-receipt-meta-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 8px 12px;
  margin-bottom: 12px;
}
.fatura-receipt-meta-item span {
  display: block;
  font-size: 10px;
  color: var(--ds-muted);
}
.fatura-receipt-meta-item strong {
  display: block;
  font-size: 11.5px;
  color: var(--ds-ink);
}
.fatura-receipt-divider {
  height: 1px;
  background: var(--ds-line, var(--dsx-bg-e2e8f0));
  margin: 12px 0;
}
.fatura-receipt-divider--bold {
  height: 1.5px;
  background: var(--dsx-bg-cbd5e1);
}
.fatura-receipt-rows {
  display: grid;
  gap: 7px;
}
.fatura-receipt-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 12px;
  color: var(--ds-slate);
}
.fatura-receipt-row b {
  color: var(--ds-ink);
  font-weight: 700;
}
.fatura-receipt-total {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 8px;
  margin-bottom: 14px;
}
.fatura-receipt-total__label {
  display: block;
  font-size: 13px;
  font-weight: 700;
  color: var(--ds-ink);
}
.fatura-receipt-total small {
  display: block;
  font-size: 10px;
  color: var(--ds-muted);
}
.fatura-receipt-total__amount {
  font-size: 18px;
  font-weight: 800;
  color: var(--ds-primary-ink, #4338ca);
}
.fatura-receipt-trust-badge {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  padding: 10px 12px;
  border-radius: 12px;
  background: var(--dsx-bg-f0fdf4);
  border: 1px solid rgba(16, 185, 129, 0.2);
  color: var(--dsx-fg-166534);
  font-size: 10.5px;
  line-height: 1.4;
}

/* Wizard Footer Action Buttons */
.fatura-wizard-footer {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 18px calc(12px + var(--ds-safe-bottom, 16px));
  border-top: 1px solid var(--ds-line, var(--dsx-bd-e2e8f0));
  background: var(--ds-surface, var(--dsx-bg-ffffff));
  box-shadow: 0 -4px 16px rgba(15, 23, 42, 0.04);
}
.fatura-wizard-back-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  min-height: 44px;
  padding: 0 16px;
  border: 1px solid var(--ds-line, var(--dsx-bd-e2e8f0));
  border-radius: 14px;
  background: var(--dsx-bg-ffffff);
  color: var(--ds-slate, var(--dsx-fg-475569));
  font: 700 12.5px var(--ds-font-main);
  cursor: pointer;
  transition: all 0.15s ease;
}
.fatura-wizard-back-btn:active {
  background: var(--dsx-bg-f1f5f9);
}
.fatura-wizard-next-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  min-height: 44px;
  padding: 0 20px;
  border: 0;
  border-radius: 14px;
  background: linear-gradient(135deg, var(--ds-primary, #4338ca), #6366f1);
  color: #ffffff;
  font: 700 13px var(--ds-font-main);
  cursor: pointer;
  box-shadow: 0 4px 14px rgba(67, 56, 202, 0.24);
  transition: all 0.2s ease;
}
.fatura-wizard-next-btn:active {
  transform: scale(0.97);
}
.fatura-wizard-draft-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  min-height: 44px;
  padding: 0 16px;
  border: 1px solid var(--ds-line, var(--dsx-bd-e2e8f0));
  border-radius: 14px;
  background: var(--dsx-bg-ffffff);
  color: var(--ds-ink, var(--dsx-fg-0f172a));
  font: 700 12px var(--ds-font-main);
  cursor: pointer;
  transition: all 0.15s ease;
  margin-left: auto;
}
.fatura-wizard-draft-btn:active {
  background: var(--dsx-bg-f1f5f9);
}
.fatura-wizard-send-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  min-height: 44px;
  padding: 0 18px;
  border: 0;
  border-radius: 14px;
  background: linear-gradient(135deg, #059669, #10b981);
  color: #ffffff;
  font: 700 13px var(--ds-font-main);
  cursor: pointer;
  box-shadow: 0 4px 14px rgba(16, 185, 129, 0.28);
  transition: all 0.2s ease;
}
.fatura-wizard-send-btn:active {
  transform: scale(0.97);
}

/* Genel Buton Yardımcıları (Modallarda kullanılmak üzere) */
.fatura-primary-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  min-height: 44px;
  padding: 0 18px;
  border: 0;
  border-radius: 14px;
  background: linear-gradient(135deg, var(--ds-primary, #4338ca), #6366f1);
  color: #ffffff;
  font: 700 13px var(--ds-font-main);
  cursor: pointer;
  box-shadow: 0 4px 14px rgba(67, 56, 202, 0.24);
  transition: all 0.2s ease;
}
.fatura-primary-btn:active {
  transform: scale(0.97);
}
.fatura-secondary-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  min-height: 44px;
  padding: 0 16px;
  border: 1px solid var(--ds-line, var(--dsx-bd-e2e8f0));
  border-radius: 14px;
  background: var(--dsx-bg-ffffff);
  color: var(--ds-slate, var(--dsx-fg-475569));
  font: 700 12.5px var(--ds-font-main);
  cursor: pointer;
  transition: all 0.15s ease;
}
.fatura-secondary-btn:active {
  background: var(--dsx-bg-f1f5f9);
}

/* Cari ve Benzer Hareket Modalları */
.fatura-cari-sheet,
.fatura-duplicate-sheet {
  height: auto;
  max-height: min(88vh, 680px);
  border-radius: 28px 28px 0 0;
  display: flex;
  flex-direction: column;
  background: var(--ds-surface, var(--dsx-bg-ffffff));
  box-shadow: 0 -10px 40px rgba(0, 0, 0, 0.2);
  overflow: hidden;
  animation: ds-slide-up 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}

.fatura-cari-sheet .fatura-sheet-body,
.fatura-duplicate-sheet .fatura-sheet-body {
  padding: 20px 20px 40px;
  padding-bottom: max(40px, calc(env(safe-area-inset-bottom, 24px) + 26px));
}

.fatura-cari-notice-box {
  padding: 15px 16px;
  border-radius: 16px;
  background: var(--ds-surface-subtle, var(--dsx-bg-f8fafc));
  border: 1px solid var(--ds-line, var(--dsx-bd-e2e8f0));
}

.fatura-cari-notice-box--success {
  background: rgba(16, 185, 129, 0.08);
  border-color: rgba(16, 185, 129, 0.25);
  color: var(--dsx-fg-065f46);
}

.fatura-cari-notice-box--info {
  background: rgba(14, 165, 233, 0.08);
  border-color: rgba(14, 165, 233, 0.25);
  color: var(--dsx-fg-0369a1);
}

.fatura-cari-notice-box--warning {
  background: var(--dsx-bg-fffbeb);
  border-color: var(--dsx-bd-fde68a);
  color: var(--dsx-fg-92400e);
}

.fatura-alert-warning-callout {
  padding: 10px 12px;
  border-radius: 12px;
  background: rgba(245, 158, 11, 0.14);
  border: 1px solid rgba(245, 158, 11, 0.3);
  font-size: 11.5px;
  line-height: 1.45;
  color: var(--dsx-fg-b45309);
}

.fatura-cari-details-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
  margin-top: 16px;
  margin-bottom: 24px;
}

.fatura-cari-detail-card {
  padding: 12px 14px;
  border-radius: 14px;
  background: var(--ds-canvas, var(--dsx-bg-f1f5f9));
  border: 1px solid var(--ds-line, var(--dsx-bd-e2e8f0));
}

.fatura-cari-detail-card span {
  display: block;
  font-size: 10px;
  font-weight: 600;
  color: var(--ds-slate, var(--dsx-fg-64748b));
  margin-bottom: 3px;
}

.fatura-cari-detail-card strong {
  display: block;
  font-size: 13.5px;
  font-weight: 700;
  color: var(--ds-ink, var(--dsx-fg-0f172a));
}

.fatura-sheet-actions {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-top: 24px;
  padding-bottom: 8px;
}

.fatura-btn-warning {
  background: linear-gradient(135deg, #f59e0b, #d97706) !important;
  color: #ffffff !important;
  border: none !important;
  box-shadow: 0 4px 14px rgba(245, 158, 11, 0.3) !important;
}
.fatura-btn-warning:active {
  transform: scale(0.97);
}

.flex-1 {
  flex: 1;
}
.flex-2 {
  flex: 2;
}

/* Skeleton & Yükleme */
.fatura-skeleton-grid {
  display: grid;
  gap: 12px;
}
.fatura-skeleton-card {
  height: 90px;
  border-radius: 20px;
  background: linear-gradient(100deg, var(--ds-canvas) 20%, var(--ds-surface-subtle) 40%, var(--ds-canvas) 60%);
  background-size: 220% 100%;
  animation: ds-shimmer 1.3s infinite;
}
.fatura-skeleton-card--lg {
  height: 140px;
}
.fatura-loading-box,
.fatura-empty,
.fatura-empty-card {
  padding: 32px 20px;
  text-align: center;
  border-radius: 22px;
  background: var(--dsx-bg-ffffff-a072);
  border: 1px solid var(--dsx-bd-ffffff-a090);
  color: var(--ds-muted);
  font-size: 13px;
  box-shadow: var(--ds-shadow-card);
}
.fatura-empty-card svg {
  margin-bottom: 8px;
  color: var(--ds-muted);
}
.fatura-empty-card strong {
  display: block;
  font-size: 15px;
  color: var(--ds-ink);
}
.fatura-empty-card p {
  margin: 4px 0 0;
  font-size: 11px;
}

/* Renk Yardımcıları */
.text-primary { color: var(--ds-primary-ink) !important; }
.text-emerald { color: var(--ds-emerald-ink) !important; }
.text-sky { color: var(--ds-blue-ink) !important; }
.text-amber { color: var(--ds-amber-ink) !important; }
.text-rose { color: var(--ds-danger-ink) !important; }
.text-purple { color: var(--ds-purple-ink) !important; }

.bg-primary-soft { background: var(--ds-soft) !important; }
.bg-emerald-soft { background: var(--ds-emerald-soft) !important; }
.bg-sky-soft { background: var(--ds-blue-soft) !important; }
.bg-amber-soft { background: var(--ds-amber-soft) !important; }

.fade-enter-active, .fade-leave-active { transition: opacity 0.2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
.sheet-enter-active, .sheet-leave-active { transition: opacity 0.25s ease; }
.sheet-enter-from, .sheet-leave-to { opacity: 0; }
</style>
