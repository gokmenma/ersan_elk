<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import {
  AlertCircle,
  AlertTriangle,
  ArrowDownLeft,
  ArrowLeft,
  ArrowUpRight,
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
  Layers,
  Package,
  Plus,
  Printer,
  Receipt,
  ReceiptText,
  RefreshCw,
  Search,
  Send,
  Share2,
  Sparkles,
  Trash2,
  User,
  X,
  XCircle,
} from '@lucide/vue'
import { faturaApi } from '../services/fatura.js'
import { auth } from '../services/auth.js'
import TopBar from '../components/TopBar.vue'

const router = useRouter()
const route = useRoute()

// 1. Ana Durumlar & Sekmeler
const activeTab = ref(route.query.tab || 'ozet') // ozet, giden, gelen, taslak
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
const previewHtmlContent = ref('')
const previewIframeRef = ref(null)

// 6. Ticari Yanıt Verme Modalı
const isResponseModalOpen = ref(false)
const responseInvoice = ref(null)
const responseType = ref('KABUL') // KABUL, RED
const responseReason = ref('')
const responseSubmitting = ref(false)

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

    if (searchQuery.value) params.search = searchQuery.value
    if (productSearchQuery.value) params.urun_ara = productSearchQuery.value
    if (statusFilter.value) params.durum_filtre = statusFilter.value
    if (responseFilter.value) params.ticari_yanit = responseFilter.value

    // Eğer ürün araması yapılıyorsa dönem filtresi opsiyoneldir (tüm dönemlerde aranır)
    if (!productSearchQuery.value) {
      if (startDate.value) params.baslangic_tarihi = startDate.value
      if (endDate.value) params.bitis_tarihi = endDate.value
    }

    const res = await faturaApi.list(params)
    if (res.success) {
      if (reset) {
        invoiceList.value = res.data || []
      } else {
        invoiceList.value = [...invoiceList.value, ...(res.data || [])]
      }
      listSummary.value = res.summary || {}
      listPagination.value = res.pagination || listPagination.value
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

function switchTab(tab) {
  activeTab.value = tab
  router.replace({ query: { ...route.query, tab } })
  // Arama ve filtreleri sıfırla
  searchQuery.value = ''
  productSearchQuery.value = ''
  statusFilter.value = ''
  responseFilter.value = ''
  loadCurrentTab(true)
}

function refreshAll() {
  refreshing.value = true
  loadCurrentTab(true)
}

// Arama kutusu izleme
watch([searchQuery, productSearchQuery, statusFilter, responseFilter], () => {
  if (activeTab.value === 'ozet') return
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    loadInvoices(true)
  }, 350)
})

// ----------------------------------------------------
// ÖNİZLEME MODALI
// ----------------------------------------------------
async function openPreview(encId, title = 'Fatura Önizleme') {
  previewEncId.value = encId
  previewTitle.value = title
  previewHtmlContent.value = ''
  isPreviewOpen.value = true
  previewLoading.value = true

  try {
    const res = await faturaApi.previewHtml(encId)
    if (res.success && res.html) {
      let html = res.html
      // Mobil viewport ve padding ayarla
      if (!html.includes('name="viewport"')) {
        html = html.replace(/<head>/i, '<head><meta name="viewport" content="width=850, initial-scale=0.45, minimum-scale=0.25, maximum-scale=3.0, user-scalable=yes">')
      }
      previewHtmlContent.value = html
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
}

function printInvoice() {
  if (previewIframeRef.value && previewIframeRef.value.contentWindow) {
    previewIframeRef.value.contentWindow.focus()
    previewIframeRef.value.contentWindow.print()
  }
}

async function shareInvoice(inv) {
  const encId = inv.encrypted_id
  const title = `Fatura: ${inv.fatura_no || 'Belge'}`
  const url = `${window.location.origin}/api/fatura.php?action=show_html&id=${encodeURIComponent(encId)}`

  if (navigator.share) {
    try {
      await navigator.share({
        title,
        text: `${inv.alici_unvan || 'Cari'} adına kesilen ${formatMoney(inv.odenecek_tutar)} tutarındaki fatura.`,
        url,
      })
    } catch (e) {}
  } else {
    try {
      await navigator.clipboard.writeText(url)
      showToast('Fatura bağlantısı panoya kopyalandı.')
    } catch (e) {
      showToast('Bağlantı kopyalanamadı.', 'error')
    }
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
  initDatesForPeriod('this_month')
  loadCurrentTab(true)
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

    <!-- 3. Sekmeli Navigasyon (Segmented Tabs) -->
    <nav class="clay-tabs-wrap fatura-tabs" aria-label="Fatura modülleri">
      <button
        type="button"
        class="clay-tab-pill"
        :class="{ active: activeTab === 'ozet' }"
        @click="switchTab('ozet')"
      >
        <ReceiptText :size="15" />
        <span>Özet & KDV</span>
      </button>
      <button
        type="button"
        class="clay-tab-pill"
        :class="{ active: activeTab === 'giden' }"
        @click="switchTab('giden')"
      >
        <ArrowUpRight :size="15" />
        <span>Giden</span>
      </button>
      <button
        type="button"
        class="clay-tab-pill"
        :class="{ active: activeTab === 'gelen' }"
        @click="switchTab('gelen')"
      >
        <ArrowDownLeft :size="15" />
        <span>Gelen</span>
      </button>
      <button
        type="button"
        class="clay-tab-pill"
        :class="{ active: activeTab === 'taslak' }"
        @click="switchTab('taslak')"
      >
        <Edit3 :size="15" />
        <span>Taslaklar</span>
      </button>
    </nav>

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
        <!-- 2'li Özet Kartları: Giden & Gelen -->
        <div class="fatura-kpi-grid">
          <!-- Giden (Satış) -->
          <article class="fatura-kpi-card fatura-kpi-card--outgoing" @click="switchTab('giden')">
            <header>
              <span class="fatura-kpi-tag">GİDEN (SATIŞ)</span>
              <span class="fatura-kpi-icon fatura-kpi-icon--emerald">
                <ArrowUpRight :size="16" />
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
                <ArrowDownLeft :size="16" />
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

        <!-- Net KDV Durumu Vurgulu Kartı -->
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

          <div class="fatura-kdv-amount">
            {{ formatMoney(dashData.kdv?.net_kdv > 0 ? dashData.kdv?.odenecek_kdv : dashData.kdv?.devreden_kdv) }}
          </div>

          <div class="fatura-kdv-breakdown">
            <div>
              <span>Hesaplanan KDV (Satış):</span>
              <b>{{ formatMoney(dashData.kdv?.hesaplanan_kdv) }}</b>
            </div>
            <div>
              <span>İndirilecek KDV (Alış):</span>
              <b>{{ formatMoney(dashData.kdv?.indirilecek_kdv) }}</b>
            </div>
          </div>
        </article>

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
              <ArrowUpRight :size="20" />
            </span>
            <strong>Giden Faturalar</strong>
            <small>EDM/GİB Satışları</small>
          </button>

          <button type="button" class="fatura-quick-item" @click="switchTab('gelen')">
            <span class="fatura-quick-item__icon bg-sky-soft text-sky">
              <ArrowDownLeft :size="20" />
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
              @click="openPreview(rg.encrypted_id, rg.fatura_no)"
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
                <div class="fatura-view-pill">
                  <Eye :size="13" />
                  <span>Fatura Gör</span>
                  <ChevronRight :size="13" />
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
              @click="openPreview(rg.encrypted_id, rg.fatura_no)"
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
                <div class="fatura-view-pill">
                  <Eye :size="13" />
                  <span>Fatura Gör</span>
                  <ChevronRight :size="13" />
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
      <!-- Özet Sayaç Şeridi -->
      <section v-if="listSummary.toplam_adet" class="fatura-summary-strip">
        <div class="fatura-summary-strip__item">
          <small>TOPLAM</small>
          <strong>{{ formatMoney(listSummary.toplam_tutar) }}</strong>
          <span>{{ listSummary.toplam_adet || 0 }} Fatura</span>
        </div>
        <div v-if="activeTab === 'giden'" class="fatura-summary-strip__item">
          <small>ONAYLI</small>
          <strong class="text-emerald">{{ formatMoney(listSummary.onaylanan_tutar) }}</strong>
          <span class="text-emerald">{{ listSummary.onaylanan_adet || 0 }} Adet</span>
        </div>
        <div v-if="activeTab === 'giden'" class="fatura-summary-strip__item">
          <small>BEKLEYEN</small>
          <strong class="text-amber">{{ formatMoney(listSummary.bekleyen_tutar) }}</strong>
          <span class="text-amber">{{ listSummary.bekleyen_adet || 0 }} Adet</span>
        </div>
        <div v-if="activeTab === 'gelen'" class="fatura-summary-strip__item">
          <small>KABUL</small>
          <strong class="text-emerald">{{ listSummary.kabul_adet || 0 }}</strong>
          <span class="text-emerald">Onaylı</span>
        </div>
        <div v-if="activeTab === 'gelen'" class="fatura-summary-strip__item">
          <small>YANIT BEKLEYEN</small>
          <strong class="text-amber">{{ listSummary.bekleyen_adet || 0 }}</strong>
          <span class="text-amber">Bekliyor</span>
        </div>
      </section>

      <!-- Arama Kutuları -->
      <div class="fatura-search-group">
        <!-- Genel Arama -->
        <div class="fatura-search-box">
          <Search :size="17" class="fatura-search-box__icon" />
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Fatura No, Cari Ünvanı veya VKN..."
            autocomplete="off"
          />
          <button v-if="searchQuery" type="button" @click="searchQuery = ''">
            <X :size="15" />
          </button>
        </div>

        <!-- Kalem / Ürün İçi Arama -->
        <div class="fatura-search-box fatura-search-box--product">
          <Package :size="17" class="fatura-search-box__icon text-purple" />
          <input
            v-model="productSearchQuery"
            type="text"
            placeholder="Fatura içindeki Ürün / Kalem / Marka Ara..."
            autocomplete="off"
          />
          <button v-if="productSearchQuery" type="button" @click="productSearchQuery = ''">
            <X :size="15" />
          </button>
        </div>
        <div v-if="productSearchQuery" class="fatura-product-badge">
          <Sparkles :size="13" />
          <span>Fatura kalemlerinde ve tüm dönemlerde aranıyor</span>
        </div>

        <!-- Hızlı Durum Filtreleri -->
        <div v-if="activeTab === 'giden'" class="fatura-filter-pills">
          <button
            type="button"
            :class="{ 'is-active': statusFilter === '' }"
            @click="statusFilter = ''"
          >
            Tümü
          </button>
          <button
            type="button"
            :class="{ 'is-active': statusFilter === 'ONAYLANDI' }"
            @click="statusFilter = 'ONAYLANDI'"
          >
            Onaylı
          </button>
          <button
            type="button"
            :class="{ 'is-active': statusFilter === 'BEKLIYOR' }"
            @click="statusFilter = 'BEKLIYOR'"
          >
            Bekleyen
          </button>
          <button
            type="button"
            :class="{ 'is-active': statusFilter === 'HATALI' }"
            @click="statusFilter = 'HATALI'"
          >
            Hatalı
          </button>
        </div>

        <div v-if="activeTab === 'gelen'" class="fatura-filter-pills">
          <button
            type="button"
            :class="{ 'is-active': responseFilter === '' }"
            @click="responseFilter = ''"
          >
            Tümü
          </button>
          <button
            type="button"
            :class="{ 'is-active': responseFilter === 'BEKLIYOR' }"
            @click="responseFilter = 'BEKLIYOR'"
          >
            Yanıt Bekleyen
          </button>
          <button
            type="button"
            :class="{ 'is-active': responseFilter === 'KABUL' }"
            @click="responseFilter = 'KABUL'"
          >
            Kabul
          </button>
          <button
            type="button"
            :class="{ 'is-active': responseFilter === 'RED' }"
            @click="responseFilter = 'RED'"
          >
            Red
          </button>
        </div>
      </div>

      <!-- Liste Durumları -->
      <div v-if="listLoading" class="fatura-loading-box">
        <RefreshCw :size="24" class="is-spinning text-primary" />
        <span>Faturalar yükleniyor...</span>
      </div>

      <div v-else-if="invoiceList.length === 0" class="fatura-empty-card">
        <Receipt :size="38" />
        <strong>Fatura Bulunamadı</strong>
        <p>Seçilen dönem veya arama kriterlerine uygun kayıt bulunmuyor.</p>
        <button
          v-if="activeTab === 'taslak'"
          type="button"
          class="fatura-primary-btn mt-3"
          @click="openNewInvoiceWizard()"
        >
          <Plus :size="16" />
          <span>Yeni Taslak Oluştur</span>
        </button>
      </div>

      <!-- Fatura Kartları -->
      <div v-else class="fatura-cards-list">
        <article
          v-for="inv in invoiceList"
          :key="inv.encrypted_id || inv.id"
          class="fatura-card"
          @click="openPreview(inv.encrypted_id, inv.fatura_no)"
        >
          <!-- Kart Üst Başlık -->
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
                  <span>{{ inv.ticari_yanit === 'KABUL' ? 'Kabul Edildi' : (inv.ticari_yanit === 'RED' ? 'Reddedildi' : 'Bekliyor') }}</span>
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
            <p class="fatura-customer-meta">
              <span>VKN/TCKN: {{ inv.alici_vkn_tckn || '-' }}</span>
              <span>•</span>
              <span>{{ formatDate(inv.fatura_tarihi) }}</span>
            </p>
          </div>

          <!-- Kalemler Özeti -->
          <div v-if="inv.kalemler_ozet" class="fatura-card__items-preview">
            <strong class="text-purple">İçerik:</strong>
            <span>{{ inv.kalemler_ozet }}</span>
          </div>

          <!-- Kart Altı & Aksiyonlar -->
          <div class="fatura-card__footer" @click.stop>
            <div class="fatura-card__amount-box">
              <small class="fatura-amount-label">Ödenecek Tutar</small>
              <strong class="fatura-amount-value">{{ formatInvoiceMoney(inv.odenecek_tutar_raw ?? inv.odenecek_tutar, inv.para_birimi) }}</strong>
            </div>

            <div class="fatura-card__actions">
              <!-- Tahsilat Rozeti (Giden faturalarda) -->
              <span
                v-if="activeTab === 'giden' && inv.tahsilat_durumu"
                class="fatura-payment-tag"
                :class="inv.tahsilat_durumu === 'ODENDI' ? 'is-paid' : (inv.tahsilat_durumu === 'KISMI_ODENDI' ? 'is-partial' : '')"
              >
                {{ inv.tahsilat_durumu === 'ODENDI' ? '✓ Ödendi' : (inv.tahsilat_durumu === 'KISMI_ODENDI' ? '◑ Kısmi' : '○ Ödenmedi') }}
              </span>

              <!-- Gelen Ticari Yanıt Butonu -->
              <button
                v-if="activeTab === 'gelen' && (!inv.ticari_yanit || inv.ticari_yanit === 'BEKLIYOR')"
                type="button"
                class="fatura-mini-btn fatura-mini-btn--primary"
                @click="openResponseModal(inv)"
              >
                <Check :size="14" />
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
                  <Trash2 :size="16" />
                </button>
                <button
                  type="button"
                  class="fatura-mini-btn"
                  title="Taslağı Düzenle"
                  @click="openNewInvoiceWizard(inv)"
                >
                  <Edit3 :size="14" />
                  <span>Düzenle</span>
                </button>
                <button
                  type="button"
                  class="fatura-mini-btn fatura-mini-btn--primary"
                  title="GİB'e Gönder"
                  @click="sendDraft(inv)"
                >
                  <Send :size="14" />
                  <span>Gönder</span>
                </button>
              </template>

              <!-- Paylaş & Önizle Butonları (Giden ve Gelen faturalarda) -->
              <template v-else>
                <button
                  type="button"
                  class="fatura-icon-action-btn"
                  title="Paylaş"
                  @click="shareInvoice(inv)"
                >
                  <Share2 :size="16" />
                </button>
                <button
                  type="button"
                  class="fatura-mini-btn"
                  @click="openPreview(inv.encrypted_id, inv.fatura_no)"
                >
                  <Eye :size="14" />
                  <span>PDF Gör</span>
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

            <div class="fatura-preview-header__actions">
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
              <button
                type="button"
                class="fatura-icon-btn"
                title="Kapat"
                @click="closePreview"
              >
                <X :size="18" />
              </button>
            </div>
          </header>

          <div class="fatura-preview-body">
            <div v-if="previewLoading" class="fatura-preview-loading">
              <RefreshCw :size="28" class="is-spinning text-primary" />
              <p>Fatura yükleniyor...</p>
            </div>
            <iframe
              v-else
              ref="previewIframeRef"
              class="fatura-preview-iframe"
              :srcdoc="previewHtmlContent"
              sandbox="allow-same-origin allow-scripts allow-modals allow-popups"
            ></iframe>
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
    <!-- MODAL 4: YENİ FATURA KESME SİHİRBAZI (4 ADIMLI WIZARD) -->
    <!-- ============================================================== -->
    <Transition name="sheet">
      <div v-if="isWizardOpen" class="fatura-modal-backdrop" @click.self="isWizardOpen = false">
        <section class="fatura-wizard-sheet" role="dialog" aria-modal="true">
          <!-- Wizard Header -->
          <header class="fatura-wizard-header">
            <div>
              <span class="fatura-wizard-tag">E-FATURA OLUŞTURUCU</span>
              <h2>{{ wizardEditId ? 'Taslağı Düzenle' : 'Yeni Fatura Kes' }}</h2>
            </div>
            <button type="button" class="fatura-icon-btn" @click="isWizardOpen = false">
              <X :size="18" />
            </button>
          </header>

          <!-- 4 Adımlı Navigasyon Çubuğu -->
          <nav class="fatura-wizard-steps">
            <button
              type="button"
              class="fatura-wizard-step-item"
              :class="{ 'is-active': wizardStep === 1, 'is-done': wizardStep > 1 }"
              @click="wizardStep = 1"
            >
              <span class="step-num">1</span>
              <span class="step-name">Cari</span>
            </button>
            <button
              type="button"
              class="fatura-wizard-step-item"
              :class="{ 'is-active': wizardStep === 2, 'is-done': wizardStep > 2 }"
              @click="wizardStep = 2"
            >
              <span class="step-num">2</span>
              <span class="step-name">Ayarlar</span>
            </button>
            <button
              type="button"
              class="fatura-wizard-step-item"
              :class="{ 'is-active': wizardStep === 3, 'is-done': wizardStep > 3 }"
              @click="wizardStep = 3"
            >
              <span class="step-num">3</span>
              <span class="step-name">Kalemler</span>
            </button>
            <button
              type="button"
              class="fatura-wizard-step-item"
              :class="{ 'is-active': wizardStep === 4 }"
              @click="wizardStep = 4"
            >
              <span class="step-num">4</span>
              <span class="step-name">Özet</span>
            </button>
          </nav>

          <!-- Wizard Body -->
          <div class="fatura-wizard-body">
            <!-- ADIM 1: CARİ / ALICI SEÇİMİ -->
            <div v-if="wizardStep === 1" class="wizard-step-panel">
              <!-- Müşteri Arama -->
              <div class="fatura-search-box">
                <Search :size="17" class="fatura-search-box__icon" />
                <input
                  v-model="wizardCustomerSearch"
                  type="text"
                  placeholder="Kayıtlı carilerde ara (Ünvan, VKN)..."
                  @input="searchCustomers"
                />
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
                  <strong>{{ c.CariAdi || c.firma }}</strong>
                  <small>VKN/TCKN: {{ c.vkn_tckn || '-' }} • {{ c.il || '' }}</small>
                </button>
              </div>

              <!-- Alıcı Bilgileri Formu -->
              <div class="fatura-form-fields mt-3">
                <div class="fatura-form-field">
                  <label>Alıcı Ünvanı / Adı Soyadı *</label>
                  <input v-model="invoiceHeader.alici_unvan" type="text" placeholder="Firma Ünvanı" />
                </div>

                <div class="fatura-form-field">
                  <label>VKN / TCKN *</label>
                  <div class="fatura-input-with-btn">
                    <input
                      v-model="invoiceHeader.alici_vkn_tckn"
                      type="text"
                      maxlength="11"
                      placeholder="10 veya 11 haneli kimlik"
                      @blur="checkTaxpayerStatus(invoiceHeader.alici_vkn_tckn)"
                    />
                    <button
                      type="button"
                      class="fatura-mini-btn"
                      :disabled="checkingTaxpayer"
                      @click="checkTaxpayerStatus(invoiceHeader.alici_vkn_tckn)"
                    >
                      <span>{{ checkingTaxpayer ? '...' : 'Sorgula' }}</span>
                    </button>
                  </div>
                </div>

                <!-- Mükellef Bilgi Rozeti -->
                <div v-if="taxpayerInfo" class="fatura-taxpayer-alert">
                  <component :is="taxpayerInfo.is_taxpayer ? CheckCircle2 : Sparkles" :size="16" />
                  <div>
                    <strong>{{ taxpayerInfo.is_taxpayer ? 'E-Fatura Mükellefi' : 'E-Arşiv Fatura Kullanıcısı' }}</strong>
                    <p>{{ taxpayerInfo.is_taxpayer ? 'Fatura E-Fatura senaryosu ile gönderilecek.' : 'Fatura E-Arşiv olarak düzenlenecek.' }}</p>
                  </div>
                </div>

                <div class="fatura-form-grid-2">
                  <div class="fatura-form-field">
                    <label>Vergi Dairesi</label>
                    <input v-model="invoiceHeader.alici_vergi_dairesi" type="text" placeholder="Vergi Dairesi" />
                  </div>
                  <div class="fatura-form-field">
                    <label>E-Posta</label>
                    <input v-model="invoiceHeader.alici_eposta" type="email" placeholder="fatura@sirket.com" />
                  </div>
                </div>

                <div class="fatura-form-grid-2">
                  <div class="fatura-form-field">
                    <label>İl</label>
                    <input v-model="invoiceHeader.alici_il" type="text" placeholder="İl" />
                  </div>
                  <div class="fatura-form-field">
                    <label>İlçe</label>
                    <input v-model="invoiceHeader.alici_ilce" type="text" placeholder="İlçe" />
                  </div>
                </div>

                <div class="fatura-form-field">
                  <label>Adres</label>
                  <textarea v-model="invoiceHeader.alici_adres" rows="2" placeholder="Açık adres..."></textarea>
                </div>
              </div>
            </div>

            <!-- ADIM 2: FATURA DETAY VE AYARLARI -->
            <div v-else-if="wizardStep === 2" class="wizard-step-panel">
              <div class="fatura-form-fields">
                <div class="fatura-form-grid-2">
                  <div class="fatura-form-field">
                    <label>Fatura Tarihi *</label>
                    <input v-model="invoiceHeader.fatura_tarihi" type="date" />
                  </div>
                  <div class="fatura-form-field">
                    <label>Fatura Saati</label>
                    <input v-model="invoiceHeader.fatura_saati" type="time" />
                  </div>
                </div>

                <div class="fatura-form-grid-2">
                  <div class="fatura-form-field">
                    <label>Belge Türü</label>
                    <select v-model="invoiceHeader.belge_turu">
                      <option value="EFATURA">E-Fatura</option>
                      <option value="EARSIV">E-Arşiv Fatura</option>
                    </select>
                  </div>
                  <div class="fatura-form-field">
                    <label>Fatura Tipi</label>
                    <select v-model="invoiceHeader.fatura_tipi">
                      <option value="SATIS">Satış</option>
                      <option value="IADE">İade</option>
                      <option value="TEVKIFAT">Tevkifat</option>
                      <option value="ISTISNA">İstisna</option>
                    </select>
                  </div>
                </div>

                <div class="fatura-form-grid-2">
                  <div class="fatura-form-field">
                    <label>Senaryo</label>
                    <select v-model="invoiceHeader.senaryo">
                      <option value="TICARIFATURA">Ticari Fatura</option>
                      <option value="TEMELFATURA">Temel Fatura</option>
                      <option value="EARSIVFATURA">E-Arşiv Fatura</option>
                    </select>
                  </div>
                  <div class="fatura-form-field">
                    <label>Seri / Ön Ek</label>
                    <select v-model="invoiceHeader.seri">
                      <option v-for="s in availableSerials" :key="s.seri" :value="s.seri">
                        {{ s.seri }} ({{ s.yil }})
                      </option>
                      <option v-if="availableSerials.length === 0" value="ERS">ERS (Varsayılan)</option>
                    </select>
                  </div>
                </div>

                <div class="fatura-form-field">
                  <label>Fatura Notları</label>
                  <textarea
                    v-model="invoiceHeader.notlar"
                    rows="3"
                    placeholder="Banka IBAN bilgisi, sipariş numarası veya açıklama ekleyin..."
                  ></textarea>
                </div>
              </div>
            </div>

            <!-- ADIM 3: KALEMLER -->
            <div v-else-if="wizardStep === 3" class="wizard-step-panel">
              <div class="fatura-lines-container">
                <article
                  v-for="(line, idx) in invoiceLines"
                  :key="idx"
                  class="fatura-line-card"
                >
                  <header class="fatura-line-card__header">
                    <span class="line-index">#{{ idx + 1 }}</span>
                    <button
                      v-if="invoiceLines.length > 1"
                      type="button"
                      class="fatura-icon-action-btn fatura-icon-action-btn--danger"
                      @click="removeLine(idx)"
                    >
                      <Trash2 :size="14" />
                    </button>
                  </header>

                  <div class="fatura-form-field">
                    <label>Kalem Açıklaması / Mal-Hizmet *</label>
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
                      <label>Birim Fiyat *</label>
                      <input
                        v-model.number="line.birim_fiyat"
                        type="number"
                        min="0"
                        step="0.01"
                        @input="recalcLines"
                      />
                    </div>
                  </div>

                  <div class="fatura-form-grid-2">
                    <div class="fatura-form-field">
                      <label>KDV Oranı (%)</label>
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
                        @input="recalcLines"
                      />
                    </div>
                  </div>

                  <div class="fatura-line-total">
                    <span>Satır Tutarı:</span>
                    <strong>{{ formatMoney(line.mal_hizmet_tutari + line.kdv_tutari) }}</strong>
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
              <div class="fatura-wizard-summary-card">
                <h3>Fatura Özeti</h3>

                <div class="fatura-summary-row">
                  <span>Alıcı:</span>
                  <strong>{{ invoiceHeader.alici_unvan }}</strong>
                </div>
                <div class="fatura-summary-row">
                  <span>VKN / TCKN:</span>
                  <strong>{{ invoiceHeader.alici_vkn_tckn }}</strong>
                </div>
                <div class="fatura-summary-row">
                  <span>Belge / Senaryo:</span>
                  <strong>{{ invoiceHeader.belge_turu }} • {{ invoiceHeader.senaryo }}</strong>
                </div>
                <div class="fatura-summary-row">
                  <span>Fatura Tarihi:</span>
                  <strong>{{ formatDate(invoiceHeader.fatura_tarihi) }}</strong>
                </div>

                <div class="fatura-summary-divider"></div>

                <div class="fatura-summary-row">
                  <span>Mal / Hizmet Toplamı:</span>
                  <b>{{ formatMoney(wizardCalculation.mal_hizmet_toplam) }}</b>
                </div>
                <div v-if="wizardCalculation.iskonto_toplam > 0" class="fatura-summary-row text-rose">
                  <span>İskonto:</span>
                  <b>-{{ formatMoney(wizardCalculation.iskonto_toplam) }}</b>
                </div>
                <div class="fatura-summary-row">
                  <span>KDV Toplamı:</span>
                  <b class="text-emerald">{{ formatMoney(wizardCalculation.kdv_toplam) }}</b>
                </div>

                <div class="fatura-summary-divider"></div>

                <div class="fatura-summary-row fatura-summary-row--total">
                  <span>Genel Toplam:</span>
                  <strong class="text-primary">{{ formatMoney(wizardCalculation.odenecek_tutar) }}</strong>
                </div>
              </div>
            </div>
          </div>

          <!-- Wizard Footer Aksiyon Butonları -->
          <footer class="fatura-wizard-footer">
            <button
              v-if="wizardStep > 1"
              type="button"
              class="fatura-secondary-btn"
              @click="prevWizardStep"
            >
              Geri
            </button>

            <button
              v-if="wizardStep < 4"
              type="button"
              class="fatura-primary-btn ml-auto"
              @click="nextWizardStep"
            >
              <span>Devam Et</span>
              <ChevronRight :size="16" />
            </button>

            <template v-else>
              <button
                type="button"
                class="fatura-secondary-btn ml-auto"
                :disabled="wizardSubmitting"
                @click="saveWizardInvoice(false)"
              >
                Taslak Olarak Kaydet
              </button>
              <button
                type="button"
                class="fatura-primary-btn"
                :disabled="wizardSubmitting"
                @click="saveWizardInvoice(true)"
              >
                <Send :size="16" />
                <span>EDM/GİB'e Gönder</span>
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
  color: var(--ds-surface);
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
  border: 1px solid rgba(255, 255, 255, 0.9);
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
  color: var(--ds-surface);
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

/* KPI Kartları (Giden & Gelen) */
.fatura-kpi-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
  margin-bottom: 14px;
}
.fatura-kpi-card {
  padding: 18px 16px;
  border: 1px solid rgba(255, 255, 255, 0.9);
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
  border-top: 1px solid rgba(0, 0, 0, 0.05);
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

/* Net KDV Durumu Kartı */
.fatura-kdv-card {
  padding: 20px 20px 18px;
  border-radius: var(--ds-radius-card, 28px);
  margin-bottom: 16px;
  background: var(--ds-surface);
  border: 1px solid rgba(255, 255, 255, 0.9);
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
.fatura-kdv-amount {
  font-size: 28px;
  font-weight: 800;
  line-height: 32px;
  letter-spacing: -0.04em;
  margin-top: 6px;
}
.fatura-kdv-card--payable .fatura-kdv-amount {
  color: var(--ds-danger-ink);
}
.fatura-kdv-card--devreden .fatura-kdv-amount {
  color: var(--ds-emerald-ink);
}
.fatura-kdv-breakdown {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 10px;
  margin-top: 14px;
  padding-top: 12px;
  border-top: 1px solid rgba(0, 0, 0, 0.05);
  font-size: 12px;
}
.fatura-kdv-breakdown span {
  display: block;
  color: var(--ds-muted);
  font-size: 11px;
}
.fatura-kdv-breakdown b {
  display: block;
  margin-top: 2px;
  font-weight: 700;
  color: var(--ds-ink);
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
  border: 1px solid rgba(255, 255, 255, 0.9);
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
  background: rgba(255, 255, 255, 0.7);
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
  color: var(--ds-surface);
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
  border: 1px solid rgba(255, 255, 255, 0.9);
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
  border-top: 1px solid rgba(0, 0, 0, 0.05);
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
  border: 1px solid rgba(0, 0, 0, 0.06);
  transition: background 0.15s ease, color 0.15s ease;
  flex-shrink: 0;
}
.fatura-view-pill:active {
  background: var(--ds-canvas);
}

/* Liste Sekmeleri Ortak Elemanları */
.fatura-summary-strip {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
  padding: 14px 12px;
  border: 1px solid rgba(255, 255, 255, 0.9);
  border-radius: 22px;
  background: var(--ds-surface);
  box-shadow: var(--ds-shadow-bento, 0 10px 24px rgba(16, 24, 40, 0.08));
  margin-bottom: 14px;
  text-align: center;
}
.fatura-summary-strip__item small {
  display: block;
  font-size: 10px;
  font-weight: 700;
  color: var(--ds-muted);
  letter-spacing: 0.05em;
  text-transform: uppercase;
}
.fatura-summary-strip__item strong {
  display: block;
  font-size: 15px;
  font-weight: 800;
  color: var(--ds-ink);
  margin-top: 2px;
}
.fatura-summary-strip__item span {
  display: block;
  font-size: 11px;
  font-weight: 600;
  color: var(--ds-muted);
}

.fatura-search-group {
  display: grid;
  gap: 8px;
  margin-bottom: 16px;
}
.fatura-search-box {
  position: relative;
  display: flex;
  align-items: center;
  height: 46px;
  border: 1px solid rgba(255, 255, 255, 0.9);
  border-radius: 999px;
  background: var(--ds-surface);
  box-shadow: var(--ds-shadow-bento, 0 10px 24px rgba(16, 24, 40, 0.08));
  padding: 0 16px;
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
  font-weight: 600;
}
.fatura-search-box button {
  border: 0;
  background: transparent;
  color: var(--ds-muted-light);
  cursor: pointer;
}
.fatura-search-box--product {
  background: var(--ds-surface-subtle);
  border-color: rgba(0, 0, 0, 0.08);
}
.fatura-product-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 4px 10px;
  border-radius: 999px;
  background: var(--ds-purple-soft);
  color: var(--ds-purple-ink);
  font-size: 11px;
  font-weight: 700;
}

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
  padding: 8px 14px;
  border: 1px solid rgba(255, 255, 255, 0.9);
  border-radius: 999px;
  background: var(--ds-surface);
  color: var(--ds-muted);
  font-size: 11px;
  font-weight: 700;
  box-shadow: var(--ds-shadow-bento, 0 10px 24px rgba(16, 24, 40, 0.08));
  cursor: pointer;
  transition: all 0.18s ease;
}
.fatura-filter-pills button.is-active {
  background: var(--ds-surface-dark, var(--ds-surface-dark));
  color: var(--ds-surface);
  border-color: var(--ds-surface-dark, var(--ds-surface-dark));
  box-shadow: 0 4px 12px rgba(25, 29, 36, 0.25);
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
  border: 1px solid rgba(255, 255, 255, 0.9);
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
  color: var(--ds-primary);
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
  background: rgba(245, 243, 255, 0.7);
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

.fatura-mini-btn {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  height: 32px;
  padding: 0 10px;
  border: 1px solid var(--ds-line);
  border-radius: 999px;
  background: var(--ds-surface);
  color: var(--ds-slate);
  font: 700 10px 'Space Grotesk', sans-serif;
  cursor: pointer;
}
.fatura-mini-btn--primary {
  background: var(--ds-primary);
  color: var(--ds-surface);
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
}
.fatura-icon-action-btn--danger {
  color: var(--ds-danger-ink);
  background: var(--ds-danger-soft);
  border-color: var(--ds-danger-soft);
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
  align-items: center;
  justify-content: space-between;
  padding: 14px 18px;
  border-bottom: 1px solid var(--ds-line);
}
.fatura-preview-header__title {
  display: flex;
  align-items: center;
  gap: 8px;
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
}
.fatura-preview-body {
  flex: 1;
  background: var(--ds-surface-subtle);
  overflow: hidden;
  position: relative;
}
.fatura-preview-iframe {
  width: 100%;
  height: 100%;
  border: 0;
  background: var(--ds-surface);
}
.fatura-preview-loading {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 10px;
  color: var(--ds-slate);
  font-weight: 600;
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
  color: var(--ds-surface);
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
  color: var(--ds-primary);
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

/* Sihirbaz (Wizard) */
.fatura-wizard-sheet {
  height: 90vh;
}
.fatura-wizard-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px 12px;
  border-bottom: 1px solid var(--ds-line);
}
.fatura-wizard-tag {
  font: 700 9px 'Space Grotesk', sans-serif;
  color: var(--ds-muted);
  letter-spacing: 0.1em;
}
.fatura-wizard-header h2 {
  margin: 1px 0 0;
  font-size: 18px;
  font-weight: 700;
}
.fatura-wizard-steps {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  padding: 8px 16px;
  background: var(--ds-surface-subtle);
  border-bottom: 1px solid var(--ds-line);
}
.fatura-wizard-step-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  border: 0;
  background: transparent;
  cursor: pointer;
}
.step-num {
  display: grid;
  place-items: center;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background: var(--ds-soft);
  color: var(--ds-muted);
  font: 700 11px 'Space Grotesk', sans-serif;
}
.fatura-wizard-step-item.is-active .step-num {
  background: var(--ds-primary);
  color: var(--ds-surface);
}
.fatura-wizard-step-item.is-done .step-num {
  background: var(--ds-emerald-ink);
  color: var(--ds-surface);
}
.step-name {
  font: 600 9px 'Space Grotesk', sans-serif;
  color: var(--ds-muted);
}
.fatura-wizard-step-item.is-active .step-name {
  color: var(--ds-primary);
  font-weight: 700;
}
.fatura-wizard-body {
  flex: 1;
  overflow-y: auto;
  padding: 16px 20px;
}
.fatura-wizard-footer {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 14px 20px;
  border-top: 1px solid var(--ds-line);
  background: var(--ds-surface);
}

/* Form Elemanları */
.fatura-form-fields {
  display: grid;
  gap: 12px;
}
.fatura-form-field label {
  display: block;
  font: 700 10px 'Space Grotesk', sans-serif;
  color: var(--ds-slate);
  margin-bottom: 4px;
}
.fatura-form-field input,
.fatura-form-field select,
.fatura-form-field textarea {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid var(--ds-line);
  border-radius: 14px;
  background: var(--ds-soft);
  font: 500 12px 'Plus Jakarta Sans', sans-serif;
  color: var(--ds-ink);
  outline: 0;
}
.fatura-form-field input:focus,
.fatura-form-field select:focus,
.fatura-form-field textarea:focus {
  border-color: var(--ds-primary);
  background: var(--ds-surface);
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
.fatura-input-with-btn {
  display: flex;
  gap: 6px;
}
.fatura-customer-dropdown {
  max-height: 160px;
  overflow-y: auto;
  border: 1px solid var(--ds-line);
  border-radius: 14px;
  margin-top: 6px;
  background: var(--ds-surface);
  box-shadow: var(--ds-shadow-card);
}
.fatura-customer-item {
  width: 100%;
  padding: 9px 12px;
  border: 0;
  border-bottom: 1px solid var(--ds-line);
  background: transparent;
  text-align: left;
  cursor: pointer;
}
.fatura-customer-item:last-child {
  border-bottom: 0;
}
.fatura-customer-item strong {
  display: block;
  font-size: 12px;
}
.fatura-customer-item small {
  display: block;
  font-size: 10px;
  color: var(--ds-muted);
}
.fatura-taxpayer-alert {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 14px;
  border-radius: 14px;
  background: var(--ds-emerald-soft);
  color: var(--ds-emerald-ink);
  font-size: 11px;
}
.fatura-taxpayer-alert strong {
  display: block;
  font-size: 12px;
}

/* Kalemler Listesi */
.fatura-lines-container {
  display: grid;
  gap: 12px;
}
.fatura-line-card {
  padding: 14px;
  border: 1px solid var(--ds-line);
  border-radius: 18px;
  background: var(--ds-soft);
  display: grid;
  gap: 8px;
}
.fatura-line-card__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.line-index {
  font: 700 11px 'Space Grotesk', sans-serif;
  color: var(--ds-slate);
}
.fatura-quick-products-row {
  display: flex;
  align-items: center;
  gap: 5px;
  overflow-x: auto;
  font-size: 10px;
  color: var(--ds-muted);
}
.fatura-quick-prod-pill {
  flex-shrink: 0;
  padding: 3px 8px;
  border-radius: 999px;
  border: 1px solid var(--ds-line);
  background: var(--ds-surface);
  font-size: 10px;
  cursor: pointer;
}
.fatura-line-total {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-top: 6px;
  border-top: 1px dashed var(--ds-line);
  font-size: 12px;
}
.fatura-add-line-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 12px;
  border: 1.5px dashed var(--ds-slate);
  border-radius: 16px;
  background: transparent;
  color: var(--ds-ink);
  font: 700 12px 'Space Grotesk', sans-serif;
  cursor: pointer;
}

/* Sihirbaz Özet Kartı */
.fatura-wizard-summary-card {
  padding: 16px;
  border: 1px solid var(--ds-line);
  border-radius: 20px;
  background: var(--ds-soft);
}
.fatura-wizard-summary-card h3 {
  margin: 0 0 12px;
  font-size: 15px;
  font-weight: 700;
}
.fatura-summary-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 7px;
  font-size: 12px;
  color: var(--ds-slate);
}
.fatura-summary-row strong,
.fatura-summary-row b {
  color: var(--ds-ink);
}
.fatura-summary-row--total {
  font-size: 15px;
  margin-top: 8px;
  margin-bottom: 0;
}
.fatura-summary-divider {
  height: 1px;
  background: var(--ds-line);
  margin: 10px 0;
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
  background: rgba(255, 255, 255, 0.72);
  border: 1px solid rgba(255, 255, 255, 0.9);
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
.text-primary { color: var(--ds-primary) !important; }
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
