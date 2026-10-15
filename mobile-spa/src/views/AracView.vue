<script setup>
import { computed, onMounted, onBeforeUnmount, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import {
  AlertTriangle,
  ArrowLeft,
  Calendar,
  Car,
  Check,
  CheckCircle2,
  ChevronDown,
  ChevronLeft,
  ChevronRight,
  Clock,
  Download,
  Eye,
  FileText,
  Fuel,
  Gauge,
  Info,
  MapPin,
  Pencil,
  Plus,
  RefreshCw,
  Search,
  Shield,
  ShieldAlert,
  ShieldCheck,
  Tag,
  Trash2,
  Upload,
  User,
  UserCheck,
  UserX,
  Wallet,
  Wrench,
  X,
} from '@lucide/vue'
import {
  aracApi,
  aracState,
  openAracDrawer,
  closeAracDrawer,
  toggleAracDrawer,
  setAracTab,
} from '../services/arac.js'
import { auth } from '../services/auth.js'
import TopBar from '../components/TopBar.vue'

const router = useRouter()

// Aktif Sekme: 'araclar' | 'zimmet' | 'yakit' | 'km' | 'servis'
const activeTab = ref(aracState.activeTab || 'araclar')

watch(() => aracState.activeTab, (newTab) => {
  if (activeTab.value !== newTab) {
    switchTab(newTab)
  }
})

watch(activeTab, (newTab) => {
  if (aracState.activeTab !== newTab) {
    aracState.activeTab = newTab
  }
})

// Veri Durumları
const loading = ref(true)
const error = ref('')
const items = ref([])
const summary = ref({
  toplam_arac: 0,
  aktif_arac: 0,
  pasif_arac: 0,
  zimmetli_sayisi: 0,
  bosta_arac: 0,
  servisteki_sayisi: 0,
  ikame_arac_sayisi: 0,
  evrak_uyari_sayisi: 0,
  totals: {
    total_yakit_tutar: 0,
    total_yakit_litre: 0,
    total_yakit_sayisi: 0,
    ortalama_yakit_tutar: 0,
    total_yapilan_km: 0,
    total_km_kayit_sayisi: 0,
    ortalama_yapilan_km: 0,
    aktif_servis_sayisi: 0,
    tamamlanan_servis_sayisi: 0,
    total_servis_tutar: 0,
    total_servis_sayisi: 0,
    zimmet_aktif_sayisi: 0,
    zimmet_iade_sayisi: 0,
    zimmet_toplam_sayisi: 0,
  },
})

// Filtreler
const searchQuery = ref('')
let searchDebounce = null
const statusFilter = ref('all') // all, aktif, zimmetli, bosta, serviste, ikame, evrak_uyari, pasif
const periodFilter = ref('this_month') // today, this_month, all, custom
const currentYear = new Date().getFullYear()
const currentMonth = String(new Date().getMonth() + 1).padStart(2, '0')
const customYear = ref(String(currentYear))
const customMonth = ref(currentMonth)

// Personel Listesi
const personeller = ref([])

// Toast Bildirimi
const toast = ref({ show: false, message: '', type: 'success' })
let toastTimer = null
function showToast(message, type = 'success') {
  clearTimeout(toastTimer)
  toast.value = { show: true, message, type }
  toastTimer = setTimeout(() => {
    toast.value.show = false
  }, 3500)
}

// -------------------------------------------------------------
// MODALLAR
// -------------------------------------------------------------
// 1. Araç Detay & Ruhsat Modalı
const isDetailModalOpen = ref(false)
const detailLoading = ref(false)
const selectedAracDetail = ref(null)

// 2. Yeni / Düzenle Araç Modalı
const isAracModalOpen = ref(false)
const isEditingArac = ref(false)
const aracFormLoading = ref(false)
const aracFormError = ref('')
const aracFormData = ref({
  id: '',
  plaka: '',
  marka: '',
  model: '',
  model_yili: currentYear,
  renk: '',
  arac_tipi: 'binek',
  yakit_tipi: 'dizel',
  sase_no: '',
  baslangic_km: 0,
  guncel_km: 0,
  aktif_mi: 1,
  ikame_mi: 0,
  muayene_tarihi: '',
  sigorta_tarihi: '',
  kasko_tarihi: '',
  notlar: '',
})

// 3. Zimmet Verme Modalı
const isZimmetModalOpen = ref(false)
const zimmetFormLoading = ref(false)
const zimmetFormError = ref('')
const zimmetFormData = ref({
  arac_id: '',
  arac_plaka: '',
  personel_id: '',
  zimmet_tarihi: new Date().toISOString().slice(0, 10),
  teslim_km: 0,
  notlar: '',
})

// 4. Zimmet İade Modalı
const isZimmetIadeModalOpen = ref(false)
const zimmetIadeLoading = ref(false)
const zimmetIadeError = ref('')
const zimmetIadeData = ref({
  zimmet_id: '',
  arac_plaka: '',
  personel_adi: '',
  iade_tarihi: new Date().toISOString().slice(0, 10),
  iade_km: 0,
  iade_notu: '',
})

// 5. Yakıt Ekleme Modalı
const isYakitModalOpen = ref(false)
const yakitFormLoading = ref(false)
const yakitFormError = ref('')
const yakitFormData = ref({
  arac_id: '',
  tarih: new Date().toISOString().slice(0, 10),
  km: 0,
  yakit_miktari: '',
  toplam_tutar: '',
  yakit_tipi: 'dizel',
  tam_depo_mu: 1,
  istasyon: '',
  fatura_no: '',
  notlar: '',
})

// 6. KM Ekleme Modalı
const isKmModalOpen = ref(false)
const kmFormLoading = ref(false)
const kmFormError = ref('')
const kmFormData = ref({
  arac_id: '',
  tarih: new Date().toISOString().slice(0, 10),
  baslangic_km: 0,
  bitis_km: 0,
  yapilan_km: 0,
  aciklama: '',
})

// 7. Servis Ekleme Modalı
const isServisModalOpen = ref(false)
const servisFormLoading = ref(false)
const servisFormError = ref('')
const servisFormData = ref({
  arac_id: '',
  servis_tarihi: new Date().toISOString().slice(0, 10),
  giris_km: 0,
  servis_adi: '',
  servis_nedeni: 'Periyodik Bakım',
  yapilan_islemler: '',
  tutar: '',
  fatura_no: '',
})

// 8. Servis Tamamlama Modalı
const isServisCompleteModalOpen = ref(false)
const servisCompleteLoading = ref(false)
const servisCompleteError = ref('')
const servisCompleteData = ref({
  servis_id: '',
  arac_plaka: '',
  servis_adi: '',
  iade_tarihi: new Date().toISOString().slice(0, 10),
  cikis_km: 0,
  tutar: '',
  yapilan_islemler: '',
})

// 9. Silme Onay Modalı
const isDeleteModalOpen = ref(false)
const deleteLoading = ref(false)
const deleteTarget = ref({ type: '', id: '', title: '' })

// 10. Hızlı Ekle FAB/Menü
const isFabMenuOpen = ref(false)

// -------------------------------------------------------------
// HESAPLANAN ÖZELLİKLER & FORMATLAYICILAR
// -------------------------------------------------------------
function formatMoney(amount) {
  if (amount === undefined || amount === null) return '0,00 ₺'
  const val = Number(amount) || 0
  return (
    new Intl.NumberFormat('tr-TR', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(val) + ' ₺'
  )
}

function formatKm(km) {
  if (km === undefined || km === null) return '0 km'
  const val = Math.max(0, Number(km) || 0)
  return new Intl.NumberFormat('tr-TR').format(val) + ' km'
}

function formatDate(dateStr) {
  if (!dateStr || dateStr === '0000-00-00') return '-'
  try {
    const d = new Date(dateStr)
    return d.toLocaleDateString('tr-TR', { day: '2-digit', month: '2-digit', year: 'numeric' })
  } catch {
    return dateStr
  }
}

// Filo Segment Oranları (Hero Bento Track)
const fleetRatio = computed(() => {
  const total = Number(summary.value.aktif_arac) || 1
  const zimmetli = Number(summary.value.zimmetli_sayisi) || 0
  const bosta = Number(summary.value.bosta_arac) || 0
  const serviste = Number(summary.value.servisteki_sayisi) || 0
  const ikame = Number(summary.value.ikame_arac_sayisi) || 0

  return {
    zimmetli: Math.min(100, Math.round((zimmetli / total) * 100)) || 0,
    bosta: Math.min(100, Math.round((bosta / total) * 100)) || 0,
    serviste: Math.min(100, Math.round((serviste / total) * 100)) || 0,
    ikame: Math.min(100, Math.round((ikame / total) * 100)) || 0,
  }
})

// Dinamik Koyu Bento Başlık & İkon
const heroCardMeta = computed(() => {
  switch (activeTab.value) {
    case 'zimmet':
      return {
        tag: 'ZİMMET DURUMU',
        title: 'Zimmet Operasyon Özeti',
        icon: UserCheck,
      }
    case 'yakit':
      return {
        tag: 'AKARYAKIT & TÜKETİM',
        title: 'Yakıt Operasyon Özeti',
        icon: Fuel,
      }
    case 'km':
      return {
        tag: 'YOLCULUK & KİLOMETRE',
        title: 'KM Operasyon Özeti',
        icon: Gauge,
      }
    case 'servis':
      return {
        tag: 'BAKIM & ONARIM',
        title: 'Servis Operasyon Özeti',
        icon: Wrench,
      }
    case 'araclar':
    default:
      return {
        tag: 'CANLI FİLO DURUMU',
        title: 'Filo Operasyon Özeti',
        icon: Car,
      }
  }
})

// Zimmet Segment Oranları
const zimmetRatio = computed(() => {
  const total = Number(summary.value.aktif_arac) || 1
  const zimmetli = Number(summary.value.zimmetli_sayisi) || 0
  const bosta = Number(summary.value.bosta_arac) || 0
  return {
    zimmetli: Math.min(100, Math.round((zimmetli / total) * 100)) || 0,
    bosta: Math.min(100, Math.round((bosta / total) * 100)) || 0,
  }
})

// Servis Segment Oranları
const servisRatio = computed(() => {
  const aktif = Number(summary.value.totals.aktif_servis_sayisi ?? summary.value.servisteki_sayisi) || 0
  const tamamlanan = Number(summary.value.totals.tamamlanan_servis_sayisi) || 0
  const total = aktif + tamamlanan || 1
  return {
    aktif: Math.min(100, Math.round((aktif / total) * 100)) || 0,
    tamamlanan: Math.min(100, Math.round((tamamlanan / total) * 100)) || 0,
  }
})

// -------------------------------------------------------------
// VERİ YÜKLEME
// -------------------------------------------------------------
async function loadData() {
  loading.value = true
  error.value = ''
  try {
    const params = {
      tab: activeTab.value,
      search: searchQuery.value,
      status: statusFilter.value,
      period: periodFilter.value,
      ay: customMonth.value,
      yil: customYear.value,
    }
    const res = await aracApi.list(params)
    if (res.success) {
      items.value = res.items || []
      summary.value = res.summary || summary.value
    }
  } catch (err) {
    error.value = err.message || 'Araç takip verileri yüklenirken bir hata oluştu.'
    items.value = []
  } finally {
    loading.value = false
  }
}

async function loadPersoneller() {
  try {
    const res = await aracApi.personeller()
    if (res.success) {
      personeller.value = res.data || []
    }
  } catch {
    // Sessiz hata
  }
}

function switchTab(tab) {
  if (activeTab.value === tab) return
  activeTab.value = tab
  aracState.activeTab = tab
  statusFilter.value = 'all'
  loadData()
}

function setPeriod(p) {
  periodFilter.value = p
  loadData()
}

// Ay & Dönem Seçici Modalı
const isMonthModalOpen = ref(false)
const pickerYear = ref(Number(currentYear))

const monthList = [
  { value: '01', name: 'Ocak', short: 'Oca' },
  { value: '02', name: 'Şubat', short: 'Şub' },
  { value: '03', name: 'Mart', short: 'Mar' },
  { value: '04', name: 'Nisan', short: 'Nis' },
  { value: '05', name: 'Mayıs', short: 'May' },
  { value: '06', name: 'Haziran', short: 'Haz' },
  { value: '07', name: 'Temmuz', short: 'Tem' },
  { value: '08', name: 'Ağustos', short: 'Ağu' },
  { value: '09', name: 'Eylül', short: 'Eyl' },
  { value: '10', name: 'Ekim', short: 'Eki' },
  { value: '11', name: 'Kasım', short: 'Kas' },
  { value: '12', name: 'Aralık', short: 'Ara' },
]

function openMonthModal() {
  pickerYear.value = Number(customYear.value) || currentYear
  isMonthModalOpen.value = true
}

function selectMonth(mVal) {
  customYear.value = String(pickerYear.value)
  customMonth.value = mVal
  periodFilter.value = 'custom'
  isMonthModalOpen.value = false
  loadData()
}

function prevPickerYear() {
  pickerYear.value--
}

function nextPickerYear() {
  pickerYear.value++
}

const customPeriodDisplay = computed(() => {
  const m = monthList.find((item) => item.value === customMonth.value)
  return m ? `${m.short} ${customYear.value}` : `${customMonth.value}/${customYear.value}`
})

watch(statusFilter, () => {
  loadData()
})

watch(searchQuery, () => {
  clearTimeout(searchDebounce)
  searchDebounce = setTimeout(() => {
    loadData()
  }, 350)
})

// -------------------------------------------------------------
// ARAÇ İŞLEMLERİ
// -------------------------------------------------------------
async function openAracDetail(item) {
  isDetailModalOpen.value = true
  detailLoading.value = true
  selectedAracDetail.value = null
  try {
    const res = await aracApi.detail(item.id)
    if (res.success) {
      selectedAracDetail.value = res.data
    }
  } catch (err) {
    showToast(err.message || 'Araç detayı alınamadı.', 'error')
    isDetailModalOpen.value = false
  } finally {
    detailLoading.value = false
  }
}

function openCreateArac() {
  isEditingArac.value = false
  aracFormError.value = ''
  aracFormData.value = {
    id: '',
    plaka: '',
    marka: '',
    model: '',
    model_yili: currentYear,
    renk: '',
    arac_tipi: 'binek',
    yakit_tipi: 'dizel',
    sase_no: '',
    baslangic_km: 0,
    guncel_km: 0,
    aktif_mi: 1,
    ikame_mi: 0,
    muayene_tarihi: '',
    sigorta_tarihi: '',
    kasko_tarihi: '',
    notlar: '',
  }
  isAracModalOpen.value = true
  isFabMenuOpen.value = false
}

async function openEditArac(item) {
  isEditingArac.value = true
  aracFormError.value = ''
  try {
    const res = await aracApi.detail(item.id)
    if (res.success && res.data) {
      const d = res.data
      aracFormData.value = {
        id: d.id,
        plaka: d.plaka,
        marka: d.marka,
        model: d.model,
        model_yili: d.model_yili || currentYear,
        renk: d.renk,
        arac_tipi: d.arac_tipi || 'binek',
        yakit_tipi: d.yakit_tipi || 'dizel',
        sase_no: d.sase_no,
        baslangic_km: d.baslangic_km,
        guncel_km: d.guncel_km,
        aktif_mi: d.aktif_mi ? 1 : 0,
        ikame_mi: d.ikame_mi ? 1 : 0,
        muayene_tarihi: d.evraklar.muayene.tarih || '',
        sigorta_tarihi: d.evraklar.sigorta.tarih || '',
        kasko_tarihi: d.evraklar.kasko.tarih || '',
        notlar: d.notlar || '',
      }
      isAracModalOpen.value = true
    }
  } catch (err) {
    showToast(err.message || 'Araç bilgileri alınamadı.', 'error')
  }
}

async function submitAracForm() {
  if (!aracFormData.value.plaka) {
    aracFormError.value = 'Lütfen araç plakasını girin.'
    return
  }
  aracFormLoading.value = true
  aracFormError.value = ''
  try {
    const res = await aracApi.saveArac(aracFormData.value, auth.state.csrfToken)
    if (res.success) {
      showToast(res.message || 'Araç kaydedildi.')
      isAracModalOpen.value = false
      await loadData()
    }
  } catch (err) {
    aracFormError.value = err.message || 'Kaydetme hatası.'
  } finally {
    aracFormLoading.value = false
  }
}

// Ruhsat Yükleme
async function handleRuhsatUpload(e) {
  const file = e.target.files?.[0]
  if (!file || !selectedAracDetail.value) return
  detailLoading.value = true
  try {
    const res = await aracApi.uploadRuhsat(selectedAracDetail.value.id, file, auth.state.csrfToken)
    if (res.success) {
      showToast('Ruhsat başarıyla yüklendi.')
      // Detayı yeniden yükle
      const updated = await aracApi.detail(selectedAracDetail.value.id)
      if (updated.success) selectedAracDetail.value = updated.data
      await loadData()
    }
  } catch (err) {
    showToast(err.message || 'Ruhsat yüklenemedi.', 'error')
  } finally {
    detailLoading.value = false
  }
}

async function deleteRuhsat() {
  if (!selectedAracDetail.value || !confirm('Ruhsat görselini silmek istediğinize emin misiniz?')) return
  detailLoading.value = true
  try {
    const res = await aracApi.deleteRuhsat(selectedAracDetail.value.id, auth.state.csrfToken)
    if (res.success) {
      showToast('Ruhsat silindi.')
      const updated = await aracApi.detail(selectedAracDetail.value.id)
      if (updated.success) selectedAracDetail.value = updated.data
      await loadData()
    }
  } catch (err) {
    showToast(err.message || 'Ruhsat silinemedi.', 'error')
  } finally {
    detailLoading.value = false
  }
}

// -------------------------------------------------------------
// ZİMMET İŞLEMLERİ
// -------------------------------------------------------------
function openZimmetModal(item = null) {
  zimmetFormError.value = ''
  zimmetFormData.value = {
    arac_id: item?.id || '',
    arac_plaka: item?.plaka || '',
    personel_id: '',
    zimmet_tarihi: new Date().toISOString().slice(0, 10),
    teslim_km: item?.guncel_km || 0,
    notlar: '',
  }
  isZimmetModalOpen.value = true
  isFabMenuOpen.value = false
}

async function submitZimmetForm() {
  if (!zimmetFormData.value.arac_id || !zimmetFormData.value.personel_id) {
    zimmetFormError.value = 'Lütfen araç ve personel seçin.'
    return
  }
  zimmetFormLoading.value = true
  zimmetFormError.value = ''
  try {
    const res = await aracApi.saveZimmet(zimmetFormData.value, auth.state.csrfToken)
    if (res.success) {
      showToast('Araç başarıyla zimmetlendi.')
      isZimmetModalOpen.value = false
      await loadData()
    }
  } catch (err) {
    zimmetFormError.value = err.message || 'Zimmet hatası.'
  } finally {
    zimmetFormLoading.value = false
  }
}

function openZimmetIadeModal(item) {
  zimmetIadeError.value = ''
  zimmetIadeData.value = {
    zimmet_id: item.id,
    arac_plaka: item.plaka,
    personel_adi: item.personel_adi,
    iade_tarihi: new Date().toISOString().slice(0, 10),
    iade_km: item.arac_guncel_km || item.teslim_km || 0,
    iade_notu: '',
  }
  isZimmetIadeModalOpen.value = true
}

async function submitZimmetIade() {
  zimmetIadeLoading.value = true
  zimmetIadeError.value = ''
  try {
    const res = await aracApi.returnZimmet(zimmetIadeData.value, auth.state.csrfToken)
    if (res.success) {
      showToast('Zimmet başarıyla iade alındı.')
      isZimmetIadeModalOpen.value = false
      await loadData()
    }
  } catch (err) {
    zimmetIadeError.value = err.message || 'İade hatası.'
  } finally {
    zimmetIadeLoading.value = false
  }
}

// -------------------------------------------------------------
// YAKIT İŞLEMLERİ
// -------------------------------------------------------------
function openYakitModal(item = null) {
  yakitFormError.value = ''
  yakitFormData.value = {
    arac_id: item?.id || '',
    tarih: new Date().toISOString().slice(0, 10),
    km: item?.guncel_km || 0,
    yakit_miktari: '',
    toplam_tutar: '',
    yakit_tipi: item?.yakit_tipi || 'dizel',
    tam_depo_mu: 1,
    istasyon: '',
    fatura_no: '',
    notlar: '',
  }
  isYakitModalOpen.value = true
  isFabMenuOpen.value = false
}

async function submitYakitForm() {
  if (!yakitFormData.value.arac_id || !yakitFormData.value.toplam_tutar) {
    yakitFormError.value = 'Lütfen araç ve toplam tutarı girin.'
    return
  }
  yakitFormLoading.value = true
  yakitFormError.value = ''
  try {
    const res = await aracApi.saveYakit(yakitFormData.value, auth.state.csrfToken)
    if (res.success) {
      showToast('Yakıt kaydı başarıyla eklendi.')
      isYakitModalOpen.value = false
      await loadData()
    }
  } catch (err) {
    yakitFormError.value = err.message || 'Yakıt kaydı hatası.'
  } finally {
    yakitFormLoading.value = false
  }
}

// -------------------------------------------------------------
// KM İŞLEMLERİ
// -------------------------------------------------------------
function openKmModal(item = null) {
  kmFormError.value = ''
  const guncel = item?.guncel_km || 0
  kmFormData.value = {
    arac_id: item?.id || '',
    tarih: new Date().toISOString().slice(0, 10),
    baslangic_km: guncel,
    bitis_km: guncel,
    yapilan_km: 0,
    aciklama: '',
  }
  isKmModalOpen.value = true
  isFabMenuOpen.value = false
}

function handleKmChange() {
  const b = Number(kmFormData.value.baslangic_km) || 0
  const e = Number(kmFormData.value.bitis_km) || 0
  kmFormData.value.yapilan_km = Math.max(0, e - b)
}

async function submitKmForm() {
  if (!kmFormData.value.arac_id || !kmFormData.value.bitis_km) {
    kmFormError.value = 'Lütfen araç ve güncel bitiş KM girin.'
    return
  }
  kmFormLoading.value = true
  kmFormError.value = ''
  try {
    const res = await aracApi.saveKm(kmFormData.value, auth.state.csrfToken)
    if (res.success) {
      showToast('KM kaydı başarıyla eklendi.')
      isKmModalOpen.value = false
      await loadData()
    }
  } catch (err) {
    kmFormError.value = err.message || 'KM kaydı hatası.'
  } finally {
    kmFormLoading.value = false
  }
}

// -------------------------------------------------------------
// SERVİS İŞLEMLERİ
// -------------------------------------------------------------
function openServisModal(item = null) {
  servisFormError.value = ''
  servisFormData.value = {
    arac_id: item?.id || '',
    servis_tarihi: new Date().toISOString().slice(0, 10),
    giris_km: item?.guncel_km || 0,
    servis_adi: '',
    servis_nedeni: 'Periyodik Bakım',
    yapilan_islemler: '',
    tutar: '',
    fatura_no: '',
  }
  isServisModalOpen.value = true
  isFabMenuOpen.value = false
}

async function submitServisForm() {
  if (!servisFormData.value.arac_id || !servisFormData.value.servis_adi) {
    servisFormError.value = 'Lütfen araç ve servis adını girin.'
    return
  }
  servisFormLoading.value = true
  servisFormError.value = ''
  try {
    const res = await aracApi.saveServis(servisFormData.value, auth.state.csrfToken)
    if (res.success) {
      showToast('Servis kaydı oluşturuldu.')
      isServisModalOpen.value = false
      await loadData()
    }
  } catch (err) {
    servisFormError.value = err.message || 'Servis kaydı hatası.'
  } finally {
    servisFormLoading.value = false
  }
}

function openServisCompleteModal(item) {
  servisCompleteError.value = ''
  servisCompleteData.value = {
    servis_id: item.id,
    arac_plaka: item.plaka,
    servis_adi: item.servis_adi,
    iade_tarihi: new Date().toISOString().slice(0, 10),
    cikis_km: item.arac_guncel_km || item.giris_km || 0,
    tutar: item.tutar > 0 ? String(item.tutar) : '',
    yapilan_islemler: item.yapilan_islemler || '',
  }
  isServisCompleteModalOpen.value = true
}

async function submitServisComplete() {
  servisCompleteLoading.value = true
  servisCompleteError.value = ''
  try {
    const res = await aracApi.completeServis(servisCompleteData.value, auth.state.csrfToken)
    if (res.success) {
      showToast('Servis tamamlandı ve araç teslim alındı.')
      isServisCompleteModalOpen.value = false
      await loadData()
    }
  } catch (err) {
    servisCompleteError.value = err.message || 'Servis tamamlama hatası.'
  } finally {
    servisCompleteLoading.value = false
  }
}

// -------------------------------------------------------------
// SİLME İŞLEMLERİ
// -------------------------------------------------------------
function confirmDelete(type, id, title) {
  deleteTarget.value = { type, id, title }
  isDeleteModalOpen.value = true
}

async function executeDelete() {
  deleteLoading.value = true
  try {
    let res
    if (deleteTarget.value.type === 'arac') {
      res = await aracApi.deleteArac(deleteTarget.value.id, auth.state.csrfToken)
    } else if (deleteTarget.value.type === 'yakit') {
      res = await aracApi.deleteYakit(deleteTarget.value.id, auth.state.csrfToken)
    } else if (deleteTarget.value.type === 'servis') {
      res = await aracApi.deleteServis(deleteTarget.value.id, auth.state.csrfToken)
    }
    if (res?.success) {
      showToast('Kayıt başarıyla silindi.')
      isDeleteModalOpen.value = false
      if (isDetailModalOpen.value && deleteTarget.value.type === 'arac') {
        isDetailModalOpen.value = false
      }
      await loadData()
    }
  } catch (err) {
    showToast(err.message || 'Silme işlemi gerçekleştirilemedi.', 'error')
  } finally {
    deleteLoading.value = false
  }
}

onMounted(() => {
  openAracDrawer()
  if (aracState.activeTab && aracState.activeTab !== activeTab.value) {
    activeTab.value = aracState.activeTab
  }
  loadData()
  loadPersoneller()
})

onBeforeUnmount(() => {
  closeAracDrawer()
})
</script>

<template>
  <section class="ds-screen ds-arac">
    <!-- Toast Bildirimi -->
    <Transition name="toast">
      <div
        v-if="toast.show"
        class="clay-toast"
        :class="toast.type === 'error' ? 'clay-toast--error' : 'clay-toast--success'"
      >
        <span class="clay-toast__icon">
          <CheckCircle2 v-if="toast.type === 'success'" :size="16" />
          <AlertTriangle v-else :size="16" />
        </span>
        <span class="clay-toast__msg">{{ toast.message }}</span>
        <button type="button" class="clay-toast__close" @click="toast.show = false">
          <X :size="14" />
        </button>
      </div>
    </Transition>

    <!-- 1. Üst Navigasyon & Aksiyonlar (TopBar) -->
    <TopBar
      title="Araç Takip"
      eyebrow="FİLO & OPERASYON YÖNETİMİ"
      :subtitle="auth.state.company?.name || 'Ersan Elektrik'"
    >
      <template #actions>
        <div class="relative">
          <button
            type="button"
            class="clay-circle-btn"
            title="Yeni Kayıt Ekle"
            aria-label="Yeni Kayıt Ekle"
            @click="isFabMenuOpen = !isFabMenuOpen"
          >
            <Plus :size="19" />
          </button>

          <!-- FAB Dropdown Menü -->
          <div v-if="isFabMenuOpen" class="fab-dropdown-menu">
            <button type="button" class="fab-menu-item" @click="openCreateArac">
              <span class="fab-menu-icon bg-blue-subtle text-blue"><Car :size="16" /></span>
              <span>Yeni Araç Ekle</span>
            </button>
            <button type="button" class="fab-menu-item" @click="openZimmetModal()">
              <span class="fab-menu-icon bg-emerald-subtle text-emerald"><UserCheck :size="16" /></span>
              <span>Araç Zimmetle</span>
            </button>
            <button type="button" class="fab-menu-item" @click="openYakitModal()">
              <span class="fab-menu-icon bg-yellow-subtle text-amber"><Fuel :size="16" /></span>
              <span>Yakıt Fişi Ekle</span>
            </button>
            <button type="button" class="fab-menu-item" @click="openKmModal()">
              <span class="fab-menu-icon bg-indigo-subtle text-indigo"><Gauge :size="16" /></span>
              <span>KM Girişi Yap</span>
            </button>
            <button type="button" class="fab-menu-item" @click="openServisModal()">
              <span class="fab-menu-icon bg-coral-subtle text-coral"><Wrench :size="16" /></span>
              <span>Servise Gönder</span>
            </button>
          </div>
        </div>

        <button
          type="button"
          class="clay-circle-btn"
          title="Yenile"
          aria-label="Yenile"
          :class="{ 'is-spinning': loading }"
          @click="loadData"
        >
          <RefreshCw :size="17" />
        </button>
      </template>
    </TopBar>

    <!-- 2. Hero Bento Koyu Kart: Dinamik Operasyon Durumu (Her Sekmeye Özel) -->
    <section class="bento-card bento-card--dark arac-hero-bento" :aria-label="heroCardMeta.title">
      <div class="bento-head">
        <div class="bento-head-title">
          <span class="dark-pill-tag">{{ heroCardMeta.tag }}</span>
          <h3>{{ heroCardMeta.title }}</h3>
        </div>
        <div class="bento-head-icons">
          <span class="bento-mini-btn bento-mini-btn--dark" :title="heroCardMeta.title">
            <component :is="heroCardMeta.icon" :size="16" />
          </span>
        </div>
      </div>

      <!-- Segmentli Dağılım İlerleme Çubuğu -->
      <!-- A) Araçlar Sekmesi Dağılımı -->
      <div v-if="activeTab === 'araclar'" class="segmented-track segmented-track--dark" title="Filo Durum Dağılımı">
        <div
          class="seg-pill seg-pill--blue"
          :style="{ width: `${fleetRatio.zimmetli}%` }"
          :title="`Zimmetli: %${fleetRatio.zimmetli}`"
        ></div>
        <div
          class="seg-pill seg-pill--emerald"
          :style="{ width: `${fleetRatio.bosta}%` }"
          :title="`Boşta: %${fleetRatio.bosta}`"
        ></div>
        <div
          class="seg-pill seg-pill--coral"
          :style="{ width: `${fleetRatio.serviste}%` }"
          :title="`Serviste: %${fleetRatio.serviste}`"
        ></div>
        <div
          v-if="fleetRatio.ikame > 0"
          class="seg-pill seg-pill--amber"
          :style="{ width: `${fleetRatio.ikame}%` }"
          :title="`İkame: %${fleetRatio.ikame}`"
        ></div>
      </div>

      <!-- B) Zimmet Sekmesi Dağılımı -->
      <div v-else-if="activeTab === 'zimmet'" class="segmented-track segmented-track--dark" title="Zimmet Durum Dağılımı">
        <div
          class="seg-pill seg-pill--blue"
          :style="{ width: `${zimmetRatio.zimmetli}%` }"
          :title="`Zimmetli: %${zimmetRatio.zimmetli}`"
        ></div>
        <div
          class="seg-pill seg-pill--emerald"
          :style="{ width: `${zimmetRatio.bosta}%` }"
          :title="`Boşta: %${zimmetRatio.bosta}`"
        ></div>
      </div>

      <!-- C) Yakıt Sekmesi Göstergesi -->
      <div v-else-if="activeTab === 'yakit'" class="segmented-track segmented-track--dark" title="Yakıt Tüketim Dağılımı">
        <div
          class="seg-pill seg-pill--emerald"
          style="width: 100%"
          title="Seçili Dönem Toplam Akaryakıt Tüketimi"
        ></div>
      </div>

      <!-- D) KM Sekmesi Göstergesi -->
      <div v-else-if="activeTab === 'km'" class="segmented-track segmented-track--dark" title="KM Sürüş Dağılımı">
        <div
          class="seg-pill seg-pill--indigo"
          style="width: 100%"
          title="Seçili Dönem Toplam Sürüş Mesafesi"
        ></div>
      </div>

      <!-- E) Servis Sekmesi Dağılımı -->
      <div v-else-if="activeTab === 'servis'" class="segmented-track segmented-track--dark" title="Servis Durum Dağılımı">
        <div
          class="seg-pill seg-pill--coral"
          :style="{ width: `${servisRatio.aktif}%` }"
          :title="`Serviste: %${servisRatio.aktif}`"
        ></div>
        <div
          class="seg-pill seg-pill--emerald"
          :style="{ width: `${servisRatio.tamamlanan}%` }"
          :title="`Tamamlandı: %${servisRatio.tamamlanan}`"
        ></div>
      </div>

      <!-- 4'lü Metrik Izgarası (Sekmeye Özel) -->
      <!-- 1. Araçlar Metrikleri -->
      <div v-if="activeTab === 'araclar'" class="bento-metrics-grid bento-metrics-grid--dark">
        <div class="metric-cell" @click="statusFilter = 'all'">
          <strong>{{ summary.toplam_arac }} <small>Araç</small></strong>
          <span>Toplam Filo ({{ summary.aktif_arac }} Aktif)</span>
        </div>
        <div class="metric-cell" @click="statusFilter = 'zimmetli'">
          <strong class="text-blue">{{ summary.zimmetli_sayisi }} <small>Zimmetli</small></strong>
          <span>Personelde Görevde</span>
        </div>
        <div class="metric-cell" @click="statusFilter = 'bosta'">
          <strong class="text-emerald">{{ summary.bosta_arac }} <small>Boşta</small></strong>
          <span>Kullanıma Hazır</span>
        </div>
        <div class="metric-cell" @click="statusFilter = 'serviste'">
          <strong class="text-coral">{{ summary.servisteki_sayisi }} <small>Serviste</small></strong>
          <span>Bakım / Onarımda</span>
        </div>
      </div>

      <!-- 2. Zimmet Metrikleri -->
      <div v-else-if="activeTab === 'zimmet'" class="bento-metrics-grid bento-metrics-grid--dark">
        <div class="metric-cell">
          <strong class="text-blue">{{ summary.zimmetli_sayisi }} <small>Araç</small></strong>
          <span>Aktif Görevde</span>
        </div>
        <div class="metric-cell">
          <strong class="text-emerald">{{ summary.bosta_arac }} <small>Boşta</small></strong>
          <span>Zimmetlenebilir</span>
        </div>
        <div class="metric-cell">
          <strong>{{ summary.totals.zimmet_toplam_sayisi || items.length }} <small>Kayıt</small></strong>
          <span>Dönem Hareketi</span>
        </div>
        <div class="metric-cell">
          <strong class="text-amber">{{ summary.totals.zimmet_iade_sayisi || 0 }} <small>İade</small></strong>
          <span>Teslim Alınan</span>
        </div>
      </div>

      <!-- 3. Yakıt Metrikleri -->
      <div v-else-if="activeTab === 'yakit'" class="bento-metrics-grid bento-metrics-grid--dark">
        <div class="metric-cell">
          <strong class="text-emerald">{{ formatMoney(summary.totals.total_yakit_tutar) }}</strong>
          <span>Dönem Yakıt Tutarı</span>
        </div>
        <div class="metric-cell">
          <strong class="text-blue">{{ Number(summary.totals.total_yakit_litre || 0).toFixed(1) }} <small>Litre</small></strong>
          <span>Toplam Tüketim</span>
        </div>
        <div class="metric-cell">
          <strong>{{ summary.totals.total_yakit_sayisi || items.length }} <small>Fiş</small></strong>
          <span>Yakıt Alımı</span>
        </div>
        <div class="metric-cell">
          <strong class="text-amber">{{ formatMoney(summary.totals.ortalama_yakit_tutar || 0) }}</strong>
          <span>Ort. Dolum Tutarı</span>
        </div>
      </div>

      <!-- 4. KM Metrikleri -->
      <div v-else-if="activeTab === 'km'" class="bento-metrics-grid bento-metrics-grid--dark">
        <div class="metric-cell">
          <strong class="text-indigo">{{ formatKm(summary.totals.total_yapilan_km) }}</strong>
          <span>Dönem Net KM</span>
        </div>
        <div class="metric-cell">
          <strong>{{ summary.totals.total_km_kayit_sayisi || items.length }} <small>Kayıt</small></strong>
          <span>Yolculuk Bildirimi</span>
        </div>
        <div class="metric-cell">
          <strong class="text-blue">{{ formatKm(summary.totals.ortalama_yapilan_km || 0) }}</strong>
          <span>Ortalama Sürüş</span>
        </div>
        <div class="metric-cell">
          <strong class="text-emerald">{{ summary.aktif_arac }} <small>Araç</small></strong>
          <span>Kateden Aktif Filo</span>
        </div>
      </div>

      <!-- 5. Servis Metrikleri -->
      <div v-else-if="activeTab === 'servis'" class="bento-metrics-grid bento-metrics-grid--dark">
        <div class="metric-cell">
          <strong class="text-coral">{{ summary.totals.aktif_servis_sayisi ?? summary.servisteki_sayisi }} <small>Araç</small></strong>
          <span>Şu An Serviste</span>
        </div>
        <div class="metric-cell">
          <strong class="text-emerald">{{ summary.totals.tamamlanan_servis_sayisi || 0 }} <small>İşlem</small></strong>
          <span>Tamamlanan Bakım</span>
        </div>
        <div class="metric-cell">
          <strong class="text-amber">{{ formatMoney(summary.totals.total_servis_tutar || 0) }}</strong>
          <span>Toplam Servis Maliyeti</span>
        </div>
        <div class="metric-cell">
          <strong>{{ summary.totals.total_servis_sayisi || items.length }} <small>Kayıt</small></strong>
          <span>Toplam Servis Girişi</span>
        </div>
      </div>
    </section>

    <!-- 3. Kritik Evrak Uyarı Kartı (Yalnızca Araçlar Sekmesinde Gösterilir) -->
    <article
      v-if="activeTab === 'araclar' && summary.evrak_uyari_sayisi > 0"
      class="announcement-card evrak-alert-card"
      @click="activeTab = 'araclar'; statusFilter = 'evrak_uyari'"
    >
      <div class="announcement-icon bg-amber-subtle text-amber">
        <ShieldAlert :size="20" />
      </div>
      <div class="announcement-body">
        <strong>Evrak Yenileme Uyarısı</strong>
        <p><b>{{ summary.evrak_uyari_sayisi }}</b> aracın muayene, sigorta veya kasko süresi yaklaşıyor.</p>
      </div>
      <button type="button" class="clay-pill-btn clay-pill-btn--warning">
        İncele
      </button>
    </article>

    <!-- Alt Menü Kapalıyken Yüzen Açma Butonu -->
    <Transition name="fade">
      <button
        v-if="!aracState.drawerOpen"
        type="button"
        class="floating-arac-dock-btn"
        title="Araç Menüsünü Aç"
        aria-label="Araç Menüsünü Aç"
        @click="openAracDrawer"
      >
        <Car :size="17" />
        <span>Menü</span>
      </button>
    </Transition>

    <!-- 5. Dönem Seçici (Zimmet, Yakıt, KM, Servis Sekmelerinde) -->
    <div v-if="activeTab !== 'araclar'" class="clay-pills-scroll mb-3">
      <button
        type="button"
        class="clay-filter-pill"
        :class="{ 'is-active': periodFilter === 'today' }"
        @click="setPeriod('today')"
      >
        Bugün
      </button>
      <button
        type="button"
        class="clay-filter-pill"
        :class="{ 'is-active': periodFilter === 'this_month' }"
        @click="setPeriod('this_month')"
      >
        Bu Ay
      </button>
      <button
        type="button"
        class="clay-filter-pill"
        :class="{ 'is-active': periodFilter === 'all' }"
        @click="setPeriod('all')"
      >
        Tümü
      </button>
      <button
        type="button"
        class="clay-filter-pill flex items-center gap-1.5"
        :class="{ 'is-active': periodFilter === 'custom' }"
        @click="openMonthModal"
      >
        <Calendar :size="13" />
        <span>{{ periodFilter === 'custom' ? customPeriodDisplay : 'Ay Seç' }}</span>
        <ChevronDown :size="12" class="opacity-70" />
      </button>
    </div>



    <!-- 7. Arama & Durum Filtre Hapları -->
    <div class="search-filter-section mb-3">
      <div class="clay-search-bar mb-2">
        <Search :size="17" />
        <input
          v-model="searchQuery"
          type="search"
          placeholder="Plaka, marka, personel, istasyon ara…"
          class="clay-search-input"
        />
        <button v-if="searchQuery" type="button" class="search-clear-btn" @click="searchQuery = ''">
          <X :size="14" />
        </button>
      </div>

      <!-- Araçlar Sekmesi Durum Filtreleri -->
      <div v-if="activeTab === 'araclar'" class="clay-pills-scroll">
        <button
          type="button"
          class="clay-filter-pill"
          :class="{ 'is-active': statusFilter === 'all' }"
          @click="statusFilter = 'all'"
        >
          Tümü ({{ summary.toplam_arac }})
        </button>
        <button
          type="button"
          class="clay-filter-pill"
          :class="{ 'is-active': statusFilter === 'aktif' }"
          @click="statusFilter = 'aktif'"
        >
          Aktif ({{ summary.aktif_arac }})
        </button>
        <button
          type="button"
          class="clay-filter-pill"
          :class="{ 'is-active': statusFilter === 'zimmetli' }"
          @click="statusFilter = 'zimmetli'"
        >
          Zimmetli ({{ summary.zimmetli_sayisi }})
        </button>
        <button
          type="button"
          class="clay-filter-pill"
          :class="{ 'is-active': statusFilter === 'bosta' }"
          @click="statusFilter = 'bosta'"
        >
          Boşta ({{ summary.bosta_arac }})
        </button>
        <button
          type="button"
          class="clay-filter-pill"
          :class="{ 'is-active': statusFilter === 'serviste' }"
          @click="statusFilter = 'serviste'"
        >
          Serviste ({{ summary.servisteki_sayisi }})
        </button>
        <button
          v-if="summary.ikame_arac_sayisi > 0"
          type="button"
          class="clay-filter-pill"
          :class="{ 'is-active': statusFilter === 'ikame' }"
          @click="statusFilter = 'ikame'"
        >
          İkame ({{ summary.ikame_arac_sayisi }})
        </button>
        <button
          v-if="summary.evrak_uyari_sayisi > 0"
          type="button"
          class="clay-filter-pill clay-filter-pill--warn"
          :class="{ 'is-active': statusFilter === 'evrak_uyari' }"
          @click="statusFilter = 'evrak_uyari'"
        >
          Uyarılar ({{ summary.evrak_uyari_sayisi }})
        </button>
      </div>
    </div>

    <!-- 8. LİSTE ALANI -->
    <!-- Yükleniyor Durumu -->
    <div v-if="loading" class="empty-state-wrap">
      <RefreshCw :size="32" class="is-spinning text-primary mb-2" />
      <p class="text-sm font-semibold text-muted">Veriler güncelleniyor…</p>
    </div>

    <!-- Hata Durumu -->
    <div v-else-if="error" class="bento-card bento-card--white text-center py-6">
      <AlertTriangle :size="32" class="text-coral mx-auto mb-2" />
      <h4 class="font-bold text-ink">Bir Hata Oluştu</h4>
      <p class="text-xs text-muted mt-1">{{ error }}</p>
      <button type="button" class="clay-pill-btn mt-3" @click="loadData">Yeniden Dene</button>
    </div>

    <!-- Kayıt Bulunamadı -->
    <div v-else-if="items.length === 0" class="empty-state-wrap">
      <Car :size="40" class="text-muted/40 mb-2" />
      <h4 class="font-bold text-ink text-base">Kayıt Bulunamadı</h4>
      <p class="text-xs text-muted max-w-xs mt-1">
        Seçilen kriterlere uygun herhangi bir kayıt mevcut değil.
      </p>
    </div>

    <!-- ======================================================== -->
    <!-- SEKME 1: ARAÇLAR LİSTESİ -->
    <!-- ======================================================== -->
    <div v-else-if="activeTab === 'araclar'" class="space-y-3">
      <article
        v-for="item in items"
        :key="item.id"
        class="bento-card bento-card--white vehicle-master-card"
        @click="openAracDetail(item)"
      >
        <!-- 1. Üst Bar: Plaka & Durum Rozeti -->
        <div class="vm-header">
          <div class="flex items-center gap-2">
            <div class="tr-plate-badge" title="Araç Plakası">
              <span class="tr-band">TR</span>
              <span class="plate-text">{{ item.plaka }}</span>
            </div>
            <span v-if="item.ikame_mi" class="status-pill status-pill--amber">İkame</span>
          </div>

          <!-- Durum Rozeti -->
          <span
            v-if="item.serviste_mi"
            class="status-pill status-pill--danger flex items-center gap-1.5"
          >
            <span class="status-dot status-dot--danger"></span> Serviste
          </span>
          <span
            v-else-if="item.zimmetli_mi"
            class="status-pill status-pill--blue flex items-center gap-1.5"
          >
            <span class="status-dot status-dot--blue"></span> Zimmetli
          </span>
          <span
            v-else-if="item.bosta_mi"
            class="status-pill status-pill--emerald flex items-center gap-1.5"
          >
            <span class="status-dot status-dot--emerald"></span> Boşta
          </span>
          <span
            v-else
            class="status-pill status-pill--muted flex items-center gap-1.5"
          >
            <span class="status-dot status-dot--muted"></span> Pasif
          </span>
        </div>

        <!-- 2. Araç Modeli ve Özellikler -->
        <div class="vm-model-row">
          <h4 class="vm-title">{{ item.marka }} {{ item.model }}</h4>
          <div class="vm-tags">
            <span v-if="item.model_yili" class="spec-tag">{{ item.model_yili }}</span>
            <span class="spec-tag">{{ item.yakit_tipi?.toUpperCase() }}</span>
            <span v-if="item.renk" class="spec-tag">{{ item.renk }}</span>
          </div>
        </div>

        <!-- 3. Sayaç ve Sürücü Bento Kutusu -->
        <div class="vm-stats-grid">
          <div class="vm-stat-cell">
            <div class="vm-stat-icon text-indigo">
              <Gauge :size="16" />
            </div>
            <div class="vm-stat-content">
              <span class="vm-stat-label">GÜNCEL SAYAÇ</span>
              <strong class="vm-stat-value text-indigo">{{ formatKm(item.guncel_km) }}</strong>
            </div>
          </div>

          <div class="vm-stat-cell">
            <div class="vm-stat-icon text-primary">
              <User :size="16" />
            </div>
            <div class="vm-stat-content">
              <span class="vm-stat-label">ZİMMETLİ KİŞİ</span>
              <strong class="vm-stat-value text-ink truncate">
                {{ item.zimmetli_personel ? item.zimmetli_personel.adi_soyadi : 'Boşta / Atanmamış' }}
              </strong>
            </div>
          </div>
        </div>

        <!-- 4. Evrak Bitiş Göstergeleri (Muayene, Sigorta, Kasko) -->
        <div class="vm-evrak-track">
          <div
            class="evrak-pill-item"
            :class="item.evraklar.muayene.kalan_gun !== null && item.evraklar.muayene.kalan_gun <= 30 ? (item.evraklar.muayene.kalan_gun < 0 ? 'is-danger' : 'is-warning') : 'is-ok'"
          >
            <Shield :size="12" />
            <span class="evrak-name">Muayene:</span>
            <strong class="evrak-days">
              {{ item.evraklar.muayene.kalan_gun !== null ? (item.evraklar.muayene.kalan_gun < 0 ? 'Geçti' : `${item.evraklar.muayene.kalan_gun}g`) : '-' }}
            </strong>
          </div>

          <div
            class="evrak-pill-item"
            :class="item.evraklar.sigorta.kalan_gun !== null && item.evraklar.sigorta.kalan_gun <= 30 ? (item.evraklar.sigorta.kalan_gun < 0 ? 'is-danger' : 'is-warning') : 'is-ok'"
          >
            <Shield :size="12" />
            <span class="evrak-name">Sigorta:</span>
            <strong class="evrak-days">
              {{ item.evraklar.sigorta.kalan_gun !== null ? (item.evraklar.sigorta.kalan_gun < 0 ? 'Geçti' : `${item.evraklar.sigorta.kalan_gun}g`) : '-' }}
            </strong>
          </div>

          <div
            class="evrak-pill-item"
            :class="item.evraklar.kasko.kalan_gun !== null && item.evraklar.kasko.kalan_gun <= 30 ? (item.evraklar.kasko.kalan_gun < 0 ? 'is-danger' : 'is-warning') : 'is-ok'"
          >
            <Shield :size="12" />
            <span class="evrak-name">Kasko:</span>
            <strong class="evrak-days">
              {{ item.evraklar.kasko.kalan_gun !== null ? (item.evraklar.kasko.kalan_gun < 0 ? 'Geçti' : `${item.evraklar.kasko.kalan_gun}g`) : '-' }}
            </strong>
          </div>
        </div>

        <!-- 5. Alt Hızlı Eylemler Çubuğu -->
        <div class="card-footer-actions" @click.stop>
          <button type="button" class="card-action-btn" title="Detay & Ruhsat" @click="openAracDetail(item)">
            <Eye :size="14" />
            <span>Detay</span>
          </button>
          <button type="button" class="card-action-btn" title="Düzenle" @click="openEditArac(item)">
            <Pencil :size="14" />
            <span>Düzenle</span>
          </button>
          <button
            v-if="!item.serviste_mi && !item.zimmetli_mi"
            type="button"
            class="card-action-btn text-blue"
            title="Zimmet Ver"
            @click="openZimmetModal(item)"
          >
            <UserCheck :size="14" />
            <span>Zimmetle</span>
          </button>
          <button
            type="button"
            class="card-action-btn text-amber"
            title="Yakıt Ekle"
            @click="openYakitModal(item)"
          >
            <Fuel :size="14" />
            <span>Yakıt</span>
          </button>
          <button
            v-if="!item.serviste_mi"
            type="button"
            class="card-action-btn text-coral"
            title="Servise Gönder"
            @click="openServisModal(item)"
          >
            <Wrench :size="14" />
            <span>Servis</span>
          </button>
        </div>
      </article>
    </div>

    <!-- ======================================================== -->
    <!-- SEKME 2: ZİMMET LİSTESİ -->
    <!-- ======================================================== -->
    <div v-else-if="activeTab === 'zimmet'" class="space-y-3">
      <article
        v-for="item in items"
        :key="item.id"
        class="bento-card bento-card--white zimmet-card"
      >
        <!-- Üst: Personel ve Durum Rozeti -->
        <div class="flex items-start justify-between gap-2 mb-3">
          <div class="flex items-center gap-2.5 min-w-0">
            <div class="clay-avatar-circle flex-shrink-0">
              {{ item.personel_adi ? item.personel_adi.slice(0, 2).toLocaleUpperCase('tr-TR') : 'PS' }}
            </div>
            <div class="min-w-0">
              <h4 class="font-bold text-sm text-ink leading-tight truncate">{{ item.personel_adi }}</h4>
              <span class="text-[11px] text-muted block truncate mt-0.5">{{ item.personel_departman || 'Genel Departman' }}</span>
            </div>
          </div>
          <span
            class="status-pill flex-shrink-0"
            :class="item.durum === 'aktif' ? 'status-pill--emerald' : 'status-pill--muted'"
          >
            {{ item.durum === 'aktif' ? 'Aktif Zimmet' : 'İade Edildi' }}
          </span>
        </div>

        <!-- Araç Plaka & Model Çubuğu -->
        <div class="flex items-center justify-between p-2.5 rounded-xl bg-surface-subtle mb-3">
          <div class="flex items-center gap-2 min-w-0">
            <div class="tr-plate-badge flex-shrink-0">
              <span class="tr-band">TR</span>
              <span class="plate-text">{{ item.plaka }}</span>
            </div>
            <span class="text-xs font-bold text-muted truncate">{{ item.arac_bilgi }}</span>
          </div>
          <div class="text-right flex-shrink-0">
            <span class="text-[9px] text-muted uppercase font-bold block">Teslim Sayaç</span>
            <span class="text-xs font-mono font-black text-indigo">{{ formatKm(item.teslim_km) }}</span>
          </div>
        </div>

        <!-- Tarihler ve Durum -->
        <div class="grid grid-cols-2 gap-2 text-xs text-muted mb-2.5 bg-surface-subtle/60 p-2 rounded-xl">
          <div>
            <span class="text-[10px] text-muted uppercase font-bold block mb-0.5">Teslim Tarihi</span>
            <span class="font-bold text-ink">{{ formatDate(item.zimmet_tarihi) }}</span>
          </div>
          <div class="text-right">
            <span class="text-[10px] text-muted uppercase font-bold block mb-0.5">İade Durumu</span>
            <span v-if="item.iade_tarihi" class="font-bold text-ink">
              {{ formatDate(item.iade_tarihi) }} ({{ formatKm(item.iade_km) }})
            </span>
            <span v-else class="font-bold text-emerald">Zimmet Devam Ediyor</span>
          </div>
        </div>

        <p v-if="item.notlar" class="text-[11px] text-slate-600 italic bg-amber-subtle/40 border border-amber-200/50 p-2 rounded-xl mb-3">
          {{ item.notlar }}
        </p>

        <!-- İade Al Butonu -->
        <div v-if="item.durum === 'aktif'" class="flex justify-end pt-1 border-t border-border-subtle">
          <button
            type="button"
            class="clay-pill-btn clay-pill-btn--danger flex items-center gap-1.5 text-xs py-1.5 px-3"
            @click="openZimmetIadeModal(item)"
          >
            <UserX :size="14" />
            <span>Zimmeti İade Al</span>
          </button>
        </div>
      </article>
    </div>

    <!-- ======================================================== -->
    <!-- SEKME 3: YAKIT LİSTESİ -->
    <!-- ======================================================== -->
    <div v-else-if="activeTab === 'yakit'" class="space-y-3">
      <article
        v-for="item in items"
        :key="item.id"
        class="bento-card bento-card--white yakit-card"
      >
        <!-- Üst Bar: Plaka + İstasyon + Toplam Tutar -->
        <div class="flex items-start justify-between gap-2 mb-3">
          <div class="flex items-center gap-2">
            <div class="tr-plate-badge" title="Araç Plakası">
              <span class="tr-band">TR</span>
              <span class="plate-text">{{ item.plaka }}</span>
            </div>
            <div>
              <span class="text-xs font-bold text-ink block leading-tight">{{ item.istasyon || 'Akaryakıt İstasyonu' }}</span>
              <span class="text-[10px] text-muted flex items-center gap-1 mt-0.5">
                <Calendar :size="10" /> {{ formatDate(item.tarih) }}
              </span>
            </div>
          </div>
          <div class="text-right">
            <strong class="text-base font-black text-emerald leading-tight block">
              {{ formatMoney(item.toplam_tutar) }}
            </strong>
            <span class="text-[10px] font-bold text-muted">{{ Number(item.yakit_miktari).toFixed(2) }} Litre</span>
          </div>
        </div>

        <!-- Orta Özet Bilgiler -->
        <div class="grid grid-cols-3 gap-2 p-2 rounded-xl bg-surface-subtle text-center text-xs mb-3">
          <div>
            <span class="block text-[9px] text-muted uppercase font-bold">Birim Fiyat</span>
            <strong class="text-ink font-mono">{{ formatMoney(item.birim_fiyat) }}</strong>
          </div>
          <div>
            <span class="block text-[9px] text-muted uppercase font-bold">Sayaç KM</span>
            <strong class="text-ink font-mono">{{ formatKm(item.km) }}</strong>
          </div>
          <div>
            <span class="block text-[9px] text-muted uppercase font-bold">Fiş / Fatura</span>
            <strong class="text-ink truncate block">{{ item.fatura_no || '-' }}</strong>
          </div>
        </div>

        <!-- Alt: Sürücü Künyesi & Silme -->
        <div class="flex items-center justify-between text-xs text-muted pt-2 border-t border-border-subtle">
          <div class="flex items-center gap-1.5 min-w-0">
            <User :size="13" class="text-muted flex-shrink-0" />
            <span class="text-slate-700 font-semibold truncate">{{ item.personel_adi }}</span>
          </div>
          <button
            type="button"
            class="text-coral hover:underline flex items-center gap-1 text-[11px] font-bold flex-shrink-0"
            title="Kaydı Sil"
            @click="confirmDelete('yakit', item.id, `${item.plaka} Yakıt Kaydı`)"
          >
            <Trash2 :size="13" />
            <span>Sil</span>
          </button>
        </div>
      </article>
    </div>

    <!-- ======================================================== -->
    <!-- SEKME 4: KM LİSTESİ (KULLANICI DOSTU YOLCULUK KARTI) -->
    <!-- ======================================================== -->
    <div v-else-if="activeTab === 'km'" class="space-y-3">
      <article
        v-for="item in items"
        :key="item.id"
        class="bento-card bento-card--white km-journey-card"
      >
        <!-- Üst Başlık: Plaka Badge + Net KM Hapı -->
        <div class="km-card-header">
          <div class="km-vehicle-identity">
            <div class="tr-plate-badge" title="Araç Plakası">
              <span class="tr-band">TR</span>
              <span class="plate-text">{{ item.plaka }}</span>
            </div>
            <div class="km-vehicle-meta">
              <span class="km-vehicle-name">{{ item.arac_bilgi }}</span>
            </div>
          </div>

          <!-- Sağ Taraf: Net KM Vurgusu ve Ayrık Tarih Rozeti -->
          <div class="km-badge-wrap">
            <span class="km-diff-pill">
              +{{ formatKm(Math.abs(item.yapilan_km || 0)) }}
            </span>
            <span class="km-date-badge">
              <Calendar :size="11" />
              <span>{{ formatDate(item.tarih) }}</span>
            </span>
          </div>
        </div>

        <!-- Orta Bölüm: Sayaç Yolculuğu (Journey Track) -->
        <div class="km-journey-box">
          <div class="journey-point">
            <span class="journey-label">BAŞLANGIÇ</span>
            <strong class="journey-val">{{ formatKm(item.baslangic_km) }}</strong>
          </div>

          <div class="journey-divider">
            <div class="journey-line"></div>
            <span class="journey-arrow">
              <ChevronRight :size="14" />
            </span>
          </div>

          <div class="journey-point text-right">
            <span class="journey-label">BİTİŞ SAYAÇ</span>
            <strong class="journey-val">{{ formatKm(item.bitis_km) }}</strong>
          </div>
        </div>

        <!-- Alt Bölüm: Sürücü Künyesi & Görev Açıklaması -->
        <div class="km-driver-footer">
          <div class="driver-info">
            <div class="driver-avatar">
              {{ item.personel_adi ? item.personel_adi.slice(0, 2).toLocaleUpperCase('tr-TR') : 'S' }}
            </div>
            <div class="driver-text">
              <span class="driver-role">Sürücü / Bildiren</span>
              <strong class="driver-name">{{ item.personel_adi || 'Belirtilmedi' }}</strong>
            </div>
          </div>

          <div v-if="item.aciklama" class="km-note-chip" :title="item.aciklama">
            <Info :size="12" class="flex-shrink-0" />
            <span class="truncate">{{ item.aciklama }}</span>
          </div>
        </div>
      </article>
    </div>

    <!-- ======================================================== -->
    <!-- SEKME 5: SERVİS LİSTESİ -->
    <!-- ======================================================== -->
    <div v-else-if="activeTab === 'servis'" class="space-y-3">
      <article
        v-for="item in items"
        :key="item.id"
        class="bento-card bento-card--white servis-card"
      >
        <div class="flex items-start justify-between gap-2 mb-3">
          <div class="flex items-center gap-2 min-w-0">
            <div class="tr-plate-badge flex-shrink-0">
              <span class="tr-band">TR</span>
              <span class="plate-text">{{ item.plaka }}</span>
            </div>
            <div class="min-w-0">
              <h4 class="font-bold text-sm text-ink leading-tight truncate">{{ item.servis_adi || 'Servis' }}</h4>
              <span class="text-[11px] text-muted block truncate mt-0.5">{{ item.servis_nedeni }}</span>
            </div>
          </div>
          <div class="text-right flex-shrink-0">
            <span
              class="status-pill mb-1 block text-right"
              :class="item.is_aktif ? 'status-pill--danger' : 'status-pill--emerald'"
            >
              {{ item.is_aktif ? 'Serviste' : 'Tamamlandı' }}
            </span>
            <strong v-if="item.tutar > 0" class="text-xs font-black text-ink block">
              {{ formatMoney(item.tutar) }}
            </strong>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-2 p-2.5 rounded-xl bg-surface-subtle text-xs mb-3">
          <div>
            <span class="text-[9px] text-muted uppercase font-bold block mb-0.5">Giriş Kaydı</span>
            <strong class="text-ink block">{{ formatDate(item.servis_tarihi) }}</strong>
            <span class="text-[10px] text-muted font-mono font-semibold">{{ formatKm(item.giris_km) }}</span>
          </div>
          <div class="text-right">
            <span class="text-[9px] text-muted uppercase font-bold block mb-0.5">Çıkış / Teslim</span>
            <strong class="text-ink block">{{ item.iade_tarihi ? formatDate(item.iade_tarihi) : 'Devam Ediyor' }}</strong>
            <span v-if="item.cikis_km" class="text-[10px] text-muted font-mono font-semibold">{{ formatKm(item.cikis_km) }}</span>
          </div>
        </div>

        <p v-if="item.yapilan_islemler" class="text-[11px] text-slate-700 bg-surface-subtle/80 border border-border-subtle p-2.5 rounded-xl mb-3">
          <span class="font-bold text-muted block text-[10px] uppercase mb-0.5">Yapılan İşlemler:</span>
          {{ item.yapilan_islemler }}
        </p>

        <div class="flex items-center justify-between pt-2 border-t border-border-subtle">
          <button
            type="button"
            class="text-coral hover:underline text-xs flex items-center gap-1 font-bold"
            @click="confirmDelete('servis', item.id, `${item.plaka} Servis Kaydı`)"
          >
            <Trash2 :size="13" />
            <span>Sil</span>
          </button>

          <button
            v-if="item.is_aktif"
            type="button"
            class="clay-pill-btn clay-pill-btn--primary flex items-center gap-1.5 text-xs py-1.5 px-3 font-bold"
            @click="openServisCompleteModal(item)"
          >
            <CheckCircle2 :size="14" />
            <span>Servisi Tamamla</span>
          </button>
        </div>
      </article>
    </div>

    <!-- ======================================================== -->
    <!-- MODAL 1: ARAÇ DETAY & RUHSAT BOTTOM SHEET -->
    <!-- ======================================================== -->
    <div
      v-if="isDetailModalOpen"
      class="clay-modal-overlay"
      @click.self="isDetailModalOpen = false"
    >
      <div class="clay-bottom-sheet detail-bottom-sheet">
        <!-- Drag Handle Indicator -->
        <div class="sheet-drag-handle"></div>

        <header class="sheet-header">
          <div class="flex items-center gap-2">
            <span class="detail-header-icon"><Car :size="20" /></span>
            <div>
              <h3 class="font-bold text-base text-ink">Araç Detay Kartı</h3>
              <p class="text-[11px] text-muted">{{ selectedAracDetail?.plaka ? `${selectedAracDetail.plaka} • Araç Künyesi` : 'Yükleniyor…' }}</p>
            </div>
          </div>
          <button type="button" class="sheet-close-btn" title="Kapat" @click="isDetailModalOpen = false">
            <X :size="18" />
          </button>
        </header>

        <div v-if="detailLoading" class="p-8 text-center">
          <RefreshCw :size="30" class="is-spinning text-primary mx-auto mb-2" />
          <p class="text-xs text-muted">Araç detayları ve kayıtlar alınıyor…</p>
        </div>

        <div v-else-if="selectedAracDetail" class="detail-sheet-content">
          <!-- 1. ÜST KİMLİK HERO KARTI (TR Plaka + Durum Rozeti + Marka/Model) -->
          <div class="detail-hero-box">
            <div class="detail-hero-top">
              <div class="tr-plate-badge" title="Araç Plakası">
                <span class="tr-band">TR</span>
                <span class="plate-text">{{ selectedAracDetail.plaka }}</span>
              </div>

              <!-- Canlı Durum Hapı -->
              <span
                v-if="selectedAracDetail.serviste_mi"
                class="status-pill status-pill--danger"
              >
                <span class="status-dot"></span>
                Serviste
              </span>
              <span
                v-else-if="selectedAracDetail.zimmetli_personel"
                class="status-pill status-pill--blue"
              >
                <span class="status-dot"></span>
                Zimmetli
              </span>
              <span
                v-else
                class="status-pill status-pill--emerald"
              >
                <span class="status-dot"></span>
                Boşta / Havuz
              </span>
            </div>

            <div class="detail-hero-info">
              <h4 class="detail-vehicle-name">
                {{ selectedAracDetail.marka }} {{ selectedAracDetail.model }}
              </h4>
              <div class="detail-hero-tags">
                <span v-if="selectedAracDetail.model_yili" class="detail-spec-chip">
                  {{ selectedAracDetail.model_yili }} Model
                </span>
                <span v-if="selectedAracDetail.renk" class="detail-spec-chip">
                  {{ selectedAracDetail.renk }}
                </span>
                <span class="detail-spec-chip capitalize">
                  {{ selectedAracDetail.arac_tipi }}
                </span>
                <span v-if="selectedAracDetail.ikame_mi" class="detail-spec-chip chip-amber">
                  İkame Araç
                </span>
              </div>
            </div>
          </div>

          <!-- 2. BENTO METRİK IZGARASI (2x2 KUTUCUK) -->
          <div class="detail-metrics-grid">
            <!-- Sayaç -->
            <div class="detail-metric-card">
              <div class="detail-metric-icon text-indigo">
                <Gauge :size="18" />
              </div>
              <div class="detail-metric-body">
                <span class="detail-metric-label">GÜNCEL SAYAÇ</span>
                <strong class="detail-metric-value text-indigo">
                  {{ formatKm(selectedAracDetail.guncel_km) }}
                </strong>
                <small v-if="selectedAracDetail.baslangic_km" class="detail-metric-hint">
                  Başlangıç: {{ formatKm(selectedAracDetail.baslangic_km) }}
                </small>
              </div>
            </div>

            <!-- Yakıt -->
            <div class="detail-metric-card">
              <div class="detail-metric-icon text-amber">
                <Fuel :size="18" />
              </div>
              <div class="detail-metric-body">
                <span class="detail-metric-label">YAKIT TİPİ</span>
                <strong class="detail-metric-value text-ink">
                  {{ selectedAracDetail.yakit_tipi?.toLocaleUpperCase('tr-TR') }}
                </strong>
                <small class="detail-metric-hint capitalize">
                  {{ selectedAracDetail.arac_tipi }} Sınıfı
                </small>
              </div>
            </div>

            <!-- Şase No -->
            <div class="detail-metric-card">
              <div class="detail-metric-icon text-blue">
                <ShieldCheck :size="18" />
              </div>
              <div class="detail-metric-body">
                <span class="detail-metric-label">ŞASE NUMARASI</span>
                <strong class="detail-metric-value font-mono text-muted text-xs truncate" :title="selectedAracDetail.sase_no">
                  {{ selectedAracDetail.sase_no || '-' }}
                </strong>
                <small v-if="selectedAracDetail.motor_no" class="detail-metric-hint truncate" :title="selectedAracDetail.motor_no">
                  Mot: {{ selectedAracDetail.motor_no }}
                </small>
              </div>
            </div>

            <!-- Mülkiyet / Departman -->
            <div class="detail-metric-card">
              <div class="detail-metric-icon text-purple">
                <MapPin :size="18" />
              </div>
              <div class="detail-metric-body">
                <span class="detail-metric-label">MÜLKİYET / KULLANIM</span>
                <strong class="detail-metric-value text-ink text-xs truncate">
                  {{ selectedAracDetail.mulkiyet || 'Şirket Öz Malı' }}
                </strong>
                <small class="detail-metric-hint truncate">
                  {{ selectedAracDetail.departmani || 'Genel Departman' }}
                </small>
              </div>
            </div>
          </div>

          <!-- 3. GÜNCEL ZİMMET SAHİBİ KARTI -->
          <div class="detail-card-panel">
            <div class="detail-panel-head">
              <span class="ds-kicker">GÜNCEL ZİMMET DURUMU</span>
              <span
                v-if="selectedAracDetail.zimmetli_personel"
                class="status-pill status-pill--blue"
              >
                Aktif Zimmet
              </span>
              <span
                v-else
                class="status-pill status-pill--emerald"
              >
                Boşta / Havuzda
              </span>
            </div>

            <div v-if="selectedAracDetail.zimmetli_personel" class="detail-person-row">
              <div class="detail-person-avatar">
                {{ selectedAracDetail.zimmetli_personel.adi_soyadi.slice(0, 2).toLocaleUpperCase('tr-TR') }}
              </div>
              <div class="detail-person-text">
                <strong class="detail-person-name">
                  {{ selectedAracDetail.zimmetli_personel.adi_soyadi }}
                </strong>
                <span class="detail-person-dept">
                  {{ selectedAracDetail.zimmetli_personel.departman || 'Genel Departman' }}
                </span>
              </div>
            </div>
            <div v-else class="detail-empty-box">
              <CheckCircle2 :size="18" class="text-emerald flex-shrink-0" />
              <span>Araç şu an herhangi bir personele zimmetli değil, havuzda bekliyor.</span>
            </div>
          </div>

          <!-- 4. EVRAK VE POLİÇE TAKVİMİ (3'LÜ BENTO KUTUSU) -->
          <div class="detail-card-panel">
            <div class="detail-panel-head">
              <span class="ds-kicker">EVRAK VE POLİÇE TAKVİMİ</span>
              <span class="detail-panel-subtext">Geçerlilik Bitişleri</span>
            </div>

            <div class="detail-docs-grid">
              <!-- Muayene -->
              <div
                class="detail-doc-tile"
                :class="selectedAracDetail.evraklar.muayene.kalan_gun !== null && selectedAracDetail.evraklar.muayene.kalan_gun <= 30 ? (selectedAracDetail.evraklar.muayene.kalan_gun < 0 ? 'is-expired' : 'is-warning') : 'is-ok'"
              >
                <div class="doc-tile-top">
                  <Calendar :size="13" />
                  <span class="doc-tile-title">Muayene</span>
                </div>
                <strong class="doc-tile-date">
                  {{ formatDate(selectedAracDetail.evraklar.muayene.tarih) }}
                </strong>
                <div
                  v-if="selectedAracDetail.evraklar.muayene.kalan_gun !== null"
                  class="doc-tile-badge"
                >
                  {{ selectedAracDetail.evraklar.muayene.kalan_gun < 0 ? 'Süresi Doldu' : `${selectedAracDetail.evraklar.muayene.kalan_gun} gün kaldı` }}
                </div>
                <div v-else class="doc-tile-badge is-muted">
                  Tarih Yok
                </div>
              </div>

              <!-- Sigorta -->
              <div
                class="detail-doc-tile"
                :class="selectedAracDetail.evraklar.sigorta.kalan_gun !== null && selectedAracDetail.evraklar.sigorta.kalan_gun <= 30 ? (selectedAracDetail.evraklar.sigorta.kalan_gun < 0 ? 'is-expired' : 'is-warning') : 'is-ok'"
              >
                <div class="doc-tile-top">
                  <Shield :size="13" />
                  <span class="doc-tile-title">Sigorta</span>
                </div>
                <strong class="doc-tile-date">
                  {{ formatDate(selectedAracDetail.evraklar.sigorta.tarih) }}
                </strong>
                <div
                  v-if="selectedAracDetail.evraklar.sigorta.kalan_gun !== null"
                  class="doc-tile-badge"
                >
                  {{ selectedAracDetail.evraklar.sigorta.kalan_gun < 0 ? 'Süresi Doldu' : `${selectedAracDetail.evraklar.sigorta.kalan_gun} gün kaldı` }}
                </div>
                <div v-else class="doc-tile-badge is-muted">
                  Tarih Yok
                </div>
              </div>

              <!-- Kasko -->
              <div
                class="detail-doc-tile"
                :class="selectedAracDetail.evraklar.kasko.kalan_gun !== null && selectedAracDetail.evraklar.kasko.kalan_gun <= 30 ? (selectedAracDetail.evraklar.kasko.kalan_gun < 0 ? 'is-expired' : 'is-warning') : 'is-ok'"
              >
                <div class="doc-tile-top">
                  <ShieldCheck :size="13" />
                  <span class="doc-tile-title">Kasko</span>
                </div>
                <strong class="doc-tile-date">
                  {{ formatDate(selectedAracDetail.evraklar.kasko.tarih) }}
                </strong>
                <div
                  v-if="selectedAracDetail.evraklar.kasko.kalan_gun !== null"
                  class="doc-tile-badge"
                >
                  {{ selectedAracDetail.evraklar.kasko.kalan_gun < 0 ? 'Süresi Doldu' : `${selectedAracDetail.evraklar.kasko.kalan_gun} gün kaldı` }}
                </div>
                <div v-else class="doc-tile-badge is-muted">
                  Tarih Yok
                </div>
              </div>
            </div>
          </div>

          <!-- 5. RUHSAT BELGESİ (AES-256 ŞİFRELİ ISO 27001 GÜVENLİK) -->
          <div class="detail-card-panel">
            <div class="detail-panel-head">
              <span class="ds-kicker">ARAÇ RUHSAT BELGESİ</span>
              <span class="security-chip">
                <ShieldCheck :size="12" />
                AES-256 Şifreli
              </span>
            </div>

            <!-- Ruhsat Yüklü İse -->
            <div v-if="selectedAracDetail.has_ruhsat" class="ruhsat-file-card">
              <div class="ruhsat-file-icon">
                <FileText :size="20" />
              </div>
              <div class="ruhsat-file-info">
                <strong class="ruhsat-file-name" :title="selectedAracDetail.ruhsat_dosya_adi">
                  {{ selectedAracDetail.ruhsat_dosya_adi || 'Ruhsat Belgesi' }}
                </strong>
                <span class="ruhsat-file-meta">
                  Güvenli Depo • ISO 27001
                </span>
              </div>
              <div class="ruhsat-file-actions">
                <a
                  :href="selectedAracDetail.ruhsat_url"
                  target="_blank"
                  class="ruhsat-view-btn"
                  title="Şifreli Belgeyi Aç"
                >
                  <Eye :size="13" />
                  <span>Görüntüle</span>
                </a>
                <button
                  type="button"
                  class="ruhsat-delete-btn"
                  title="Ruhsatı Sil"
                  @click="deleteRuhsat"
                >
                  <Trash2 :size="14" />
                </button>
              </div>
            </div>

            <!-- Ruhsat Yoksa Yükleme Alanı -->
            <div v-else class="ruhsat-upload-box">
              <label class="ruhsat-upload-label">
                <div class="ruhsat-upload-icon">
                  <Upload :size="20" />
                </div>
                <strong class="ruhsat-upload-title">Ruhsat Belgesi Yükle</strong>
                <span class="ruhsat-upload-hint">PDF, JPG veya PNG (Sunucuda AES-256 ile şifrelenir)</span>
                <input
                  type="file"
                  accept="image/*,application/pdf"
                  class="hidden-file-input"
                  @change="handleRuhsatUpload"
                />
              </label>
            </div>
          </div>

          <!-- 6. ALT AKSİYON ÇUBUĞU (DÜZENLE & SİL) -->
          <div class="detail-sheet-actions">
            <button
              type="button"
              class="detail-action-btn detail-action-btn--primary"
              @click="openEditArac(selectedAracDetail); isDetailModalOpen = false"
            >
              <Pencil :size="16" />
              <span>Aracı Düzenle</span>
            </button>
            <button
              type="button"
              class="detail-action-btn detail-action-btn--danger"
              @click="confirmDelete('arac', selectedAracDetail.id, selectedAracDetail.plaka)"
            >
              <Trash2 :size="16" />
              <span>Sil</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ======================================================== -->
    <!-- MODAL 2: YENİ / DÜZENLE ARAÇ FORMU BOTTOM SHEET -->
    <!-- ======================================================== -->
    <div
      v-if="isAracModalOpen"
      class="clay-modal-overlay"
      @click.self="isAracModalOpen = false"
    >
      <div class="clay-bottom-sheet">
        <header class="sheet-header">
          <div class="flex items-center gap-2">
            <span class="bento-mini-btn bg-blue-subtle text-blue"><Car :size="18" /></span>
            <h3 class="font-bold text-base text-ink">
              {{ isEditingArac ? 'Aracı Düzenle' : 'Yeni Araç Ekle' }}
            </h3>
          </div>
          <button type="button" class="sheet-close-btn" @click="isAracModalOpen = false">
            <X :size="18" />
          </button>
        </header>

        <form class="sheet-body space-y-3" @submit.prevent="submitAracForm">
          <div v-if="aracFormError" class="p-2.5 rounded-xl bg-coral-subtle text-coral text-xs font-semibold">
            {{ aracFormError }}
          </div>

          <div>
            <label class="form-label">Plaka *</label>
            <input
              v-model="aracFormData.plaka"
              type="text"
              required
              placeholder="Örn: 34 ABC 123"
              class="form-input font-black uppercase text-base"
            />
          </div>

          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="form-label">Marka</label>
              <input v-model="aracFormData.marka" type="text" placeholder="Örn: Ford" class="form-input" />
            </div>
            <div>
              <label class="form-label">Model</label>
              <input v-model="aracFormData.model" type="text" placeholder="Örn: Transit" class="form-input" />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="form-label">Model Yılı</label>
              <input v-model="aracFormData.model_yili" type="number" class="form-input" />
            </div>
            <div>
              <label class="form-label">Renk</label>
              <input v-model="aracFormData.renk" type="text" placeholder="Beyaz" class="form-input" />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="form-label">Araç Tipi</label>
              <select v-model="aracFormData.arac_tipi" class="form-input">
                <option value="binek">Binek</option>
                <option value="kamyonet">Kamyonet</option>
                <option value="kamyon">Kamyon</option>
                <option value="minibus">Minibüs</option>
                <option value="motosiklet">Motosiklet</option>
                <option value="diger">Diğer</option>
              </select>
            </div>
            <div>
              <label class="form-label">Yakıt Tipi</label>
              <select v-model="aracFormData.yakit_tipi" class="form-input">
                <option value="dizel">Dizel</option>
                <option value="benzin">Benzin</option>
                <option value="lpg">LPG</option>
                <option value="elektrik">Elektrik</option>
                <option value="hibrit">Hibrit</option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="form-label">Başlangıç KM</label>
              <input v-model="aracFormData.baslangic_km" type="number" class="form-input" />
            </div>
            <div>
              <label class="form-label">Güncel KM</label>
              <input v-model="aracFormData.guncel_km" type="number" class="form-input font-bold" />
            </div>
          </div>

          <div>
            <label class="form-label">Şase Numarası</label>
            <input v-model="aracFormData.sase_no" type="text" placeholder="Şase No" class="form-input font-mono" />
          </div>

          <div class="grid grid-cols-3 gap-2">
            <div>
              <label class="form-label text-[10px]">Muayene</label>
              <input v-model="aracFormData.muayene_tarihi" type="date" class="form-input text-xs" />
            </div>
            <div>
              <label class="form-label text-[10px]">Sigorta</label>
              <input v-model="aracFormData.sigorta_tarihi" type="date" class="form-input text-xs" />
            </div>
            <div>
              <label class="form-label text-[10px]">Kasko</label>
              <input v-model="aracFormData.kasko_tarihi" type="date" class="form-input text-xs" />
            </div>
          </div>

          <div class="flex items-center gap-4 py-1">
            <label class="flex items-center gap-2 cursor-pointer text-xs font-bold text-ink">
              <input v-model="aracFormData.aktif_mi" type="checkbox" :true-value="1" :false-value="0" />
              <span>Aktif Araç</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer text-xs font-bold text-ink">
              <input v-model="aracFormData.ikame_mi" type="checkbox" :true-value="1" :false-value="0" />
              <span>İkame Araç</span>
            </label>
          </div>

          <div>
            <label class="form-label">Notlar</label>
            <textarea v-model="aracFormData.notlar" rows="2" placeholder="Araç hakkında ek açıklamalar…" class="form-input"></textarea>
          </div>

          <button
            type="submit"
            class="clay-pill-btn clay-pill-btn--primary w-full py-3 font-bold text-sm mt-2"
            :disabled="aracFormLoading"
          >
            {{ aracFormLoading ? 'Kaydediliyor…' : (isEditingArac ? 'Güncelle' : 'Kaydet') }}
          </button>
        </form>
      </div>
    </div>

    <!-- ======================================================== -->
    <!-- MODAL 3: ZİMMET VERME BOTTOM SHEET -->
    <!-- ======================================================== -->
    <div
      v-if="isZimmetModalOpen"
      class="clay-modal-overlay"
      @click.self="isZimmetModalOpen = false"
    >
      <div class="clay-bottom-sheet">
        <header class="sheet-header">
          <div class="flex items-center gap-2">
            <span class="bento-mini-btn bg-emerald-subtle text-emerald"><UserCheck :size="18" /></span>
            <h3 class="font-bold text-base text-ink">Araç Zimmetle</h3>
          </div>
          <button type="button" class="sheet-close-btn" @click="isZimmetModalOpen = false">
            <X :size="18" />
          </button>
        </header>

        <form class="sheet-body space-y-3" @submit.prevent="submitZimmetForm">
          <div v-if="zimmetFormError" class="p-2.5 rounded-xl bg-coral-subtle text-coral text-xs font-semibold">
            {{ zimmetFormError }}
          </div>

          <div>
            <label class="form-label">Araç Seçin *</label>
            <select v-model="zimmetFormData.arac_id" required class="form-input">
              <option value="">Araç Seçiniz</option>
              <option
                v-for="a in items"
                :key="a.id"
                :value="a.id"
              >
                {{ a.plaka }} - {{ a.marka }} {{ a.model }} ({{ formatKm(a.guncel_km) }})
              </option>
            </select>
          </div>

          <div>
            <label class="form-label">Personel Seçin *</label>
            <select v-model="zimmetFormData.personel_id" required class="form-input">
              <option value="">Personel Seçiniz</option>
              <option
                v-for="p in personeller"
                :key="p.id"
                :value="p.id"
              >
                {{ p.name }} ({{ p.department || 'Genel' }})
              </option>
            </select>
          </div>

          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="form-label">Zimmet Tarihi</label>
              <input v-model="zimmetFormData.zimmet_tarihi" type="date" required class="form-input" />
            </div>
            <div>
              <label class="form-label">Teslim KM</label>
              <input v-model="zimmetFormData.teslim_km" type="number" required class="form-input font-bold" />
            </div>
          </div>

          <div>
            <label class="form-label">Zimmet Notu</label>
            <textarea v-model="zimmetFormData.notlar" rows="2" placeholder="Teslimat koşulları, hasar/ekipman bilgisi…" class="form-input"></textarea>
          </div>

          <button
            type="submit"
            class="clay-pill-btn clay-pill-btn--primary w-full py-3 font-bold text-sm mt-2"
            :disabled="zimmetFormLoading"
          >
            {{ zimmetFormLoading ? 'Zimmetleniyor…' : 'Zimmeti Onayla' }}
          </button>
        </form>
      </div>
    </div>

    <!-- ======================================================== -->
    <!-- MODAL 4: ZİMMET İADE BOTTOM SHEET -->
    <!-- ======================================================== -->
    <div
      v-if="isZimmetIadeModalOpen"
      class="clay-modal-overlay"
      @click.self="isZimmetIadeModalOpen = false"
    >
      <div class="clay-bottom-sheet">
        <header class="sheet-header">
          <div class="flex items-center gap-2">
            <span class="bento-mini-btn bg-coral-subtle text-coral"><UserX :size="18" /></span>
            <div>
              <h3 class="font-bold text-base text-ink">Zimmeti İade Al</h3>
              <p class="text-[11px] text-muted">{{ zimmetIadeData.arac_plaka }} • {{ zimmetIadeData.personel_adi }}</p>
            </div>
          </div>
          <button type="button" class="sheet-close-btn" @click="isZimmetIadeModalOpen = false">
            <X :size="18" />
          </button>
        </header>

        <form class="sheet-body space-y-3" @submit.prevent="submitZimmetIade">
          <div v-if="zimmetIadeError" class="p-2.5 rounded-xl bg-coral-subtle text-coral text-xs font-semibold">
            {{ zimmetIadeError }}
          </div>

          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="form-label">İade Tarihi</label>
              <input v-model="zimmetIadeData.iade_tarihi" type="date" required class="form-input" />
            </div>
            <div>
              <label class="form-label">İade KM *</label>
              <input v-model="zimmetIadeData.iade_km" type="number" required class="form-input font-bold" />
            </div>
          </div>

          <div>
            <label class="form-label">İade Notu & Durum Tespiti</label>
            <textarea v-model="zimmetIadeData.iade_notu" rows="3" placeholder="Aracın iade anındaki durumu, temizlik, hasar vb.…" class="form-input"></textarea>
          </div>

          <button
            type="submit"
            class="clay-pill-btn clay-pill-btn--danger w-full py-3 font-bold text-sm mt-2"
            :disabled="zimmetIadeLoading"
          >
            {{ zimmetIadeLoading ? 'İşleniyor…' : 'İadeyi Tamamla' }}
          </button>
        </form>
      </div>
    </div>

    <!-- ======================================================== -->
    <!-- MODAL 5: YAKIT EKLEME BOTTOM SHEET -->
    <!-- ======================================================== -->
    <div
      v-if="isYakitModalOpen"
      class="clay-modal-overlay"
      @click.self="isYakitModalOpen = false"
    >
      <div class="clay-bottom-sheet">
        <header class="sheet-header">
          <div class="flex items-center gap-2">
            <span class="bento-mini-btn bg-emerald-subtle text-emerald"><Fuel :size="18" /></span>
            <h3 class="font-bold text-base text-ink">Yakıt Fişi Ekle</h3>
          </div>
          <button type="button" class="sheet-close-btn" @click="isYakitModalOpen = false">
            <X :size="18" />
          </button>
        </header>

        <form class="sheet-body space-y-3" @submit.prevent="submitYakitForm">
          <div v-if="yakitFormError" class="p-2.5 rounded-xl bg-coral-subtle text-coral text-xs font-semibold">
            {{ yakitFormError }}
          </div>

          <div>
            <label class="form-label">Araç Seçin *</label>
            <select v-model="yakitFormData.arac_id" required class="form-input">
              <option value="">Araç Seçiniz</option>
              <option
                v-for="a in items"
                :key="a.id"
                :value="a.id"
              >
                {{ a.plaka }} - {{ a.marka }} {{ a.model }}
              </option>
            </select>
          </div>

          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="form-label">Dolum Tarihi</label>
              <input v-model="yakitFormData.tarih" type="date" required class="form-input" />
            </div>
            <div>
              <label class="form-label">Sayaç KM</label>
              <input v-model="yakitFormData.km" type="number" required class="form-input font-bold" />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="form-label">Litre</label>
              <input v-model="yakitFormData.yakit_miktari" type="number" step="0.01" placeholder="Örn: 45.5" class="form-input" />
            </div>
            <div>
              <label class="form-label">Toplam Tutar (₺) *</label>
              <input v-model="yakitFormData.toplam_tutar" type="number" step="0.01" required placeholder="0.00" class="form-input font-black text-emerald" />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="form-label">İstasyon</label>
              <input v-model="yakitFormData.istasyon" type="text" placeholder="Shell, Opet vb." class="form-input" />
            </div>
            <div>
              <label class="form-label">Fiş / Fatura No</label>
              <input v-model="yakitFormData.fatura_no" type="text" placeholder="Fiş No" class="form-input" />
            </div>
          </div>

          <button
            type="submit"
            class="clay-pill-btn clay-pill-btn--primary w-full py-3 font-bold text-sm mt-2"
            :disabled="yakitFormLoading"
          >
            {{ yakitFormLoading ? 'Kaydediliyor…' : 'Yakıt Kaydet' }}
          </button>
        </form>
      </div>
    </div>

    <!-- ======================================================== -->
    <!-- MODAL 6: KM KAYDI BOTTOM SHEET -->
    <!-- ======================================================== -->
    <div
      v-if="isKmModalOpen"
      class="clay-modal-overlay"
      @click.self="isKmModalOpen = false"
    >
      <div class="clay-bottom-sheet">
        <header class="sheet-header">
          <div class="flex items-center gap-2">
            <span class="bento-mini-btn bg-indigo-subtle text-indigo"><Gauge :size="18" /></span>
            <h3 class="font-bold text-base text-ink">KM Kaydı / Bildirimi</h3>
          </div>
          <button type="button" class="sheet-close-btn" @click="isKmModalOpen = false">
            <X :size="18" />
          </button>
        </header>

        <form class="sheet-body space-y-3" @submit.prevent="submitKmForm">
          <div v-if="kmFormError" class="p-2.5 rounded-xl bg-coral-subtle text-coral text-xs font-semibold">
            {{ kmFormError }}
          </div>

          <div>
            <label class="form-label">Araç Seçin *</label>
            <select v-model="kmFormData.arac_id" required class="form-input">
              <option value="">Araç Seçiniz</option>
              <option
                v-for="a in items"
                :key="a.id"
                :value="a.id"
              >
                {{ a.plaka }} (Güncel: {{ formatKm(a.guncel_km) }})
              </option>
            </select>
          </div>

          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="form-label">Tarih</label>
              <input v-model="kmFormData.tarih" type="date" required class="form-input" />
            </div>
            <div>
              <label class="form-label">Yeni Bitiş KM *</label>
              <input
                v-model="kmFormData.bitis_km"
                type="number"
                required
                class="form-input font-black text-indigo"
                @input="handleKmChange"
              />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-2 text-xs">
            <div>
              <label class="form-label">Başlangıç KM</label>
              <input
                v-model="kmFormData.baslangic_km"
                type="number"
                class="form-input"
                @input="handleKmChange"
              />
            </div>
            <div>
              <label class="form-label">Hesaplanan Net KM</label>
              <div class="p-2 rounded-xl bg-surface-subtle font-black text-ink text-sm text-center">
                +{{ formatKm(Math.abs(kmFormData.yapilan_km || 0)) }}
              </div>
            </div>
          </div>

          <div>
            <label class="form-label">Açıklama / Güzergah</label>
            <textarea v-model="kmFormData.aciklama" rows="2" placeholder="Gidilen lokasyon, görev detayı…" class="form-input"></textarea>
          </div>

          <button
            type="submit"
            class="clay-pill-btn clay-pill-btn--primary w-full py-3 font-bold text-sm mt-2"
            :disabled="kmFormLoading"
          >
            {{ kmFormLoading ? 'Kaydediliyor…' : 'KM Kaydet' }}
          </button>
        </form>
      </div>
    </div>

    <!-- ======================================================== -->
    <!-- MODAL 7: SERVİS KAYDI BOTTOM SHEET -->
    <!-- ======================================================== -->
    <div
      v-if="isServisModalOpen"
      class="clay-modal-overlay"
      @click.self="isServisModalOpen = false"
    >
      <div class="clay-bottom-sheet">
        <header class="sheet-header">
          <div class="flex items-center gap-2">
            <span class="bento-mini-btn bg-coral-subtle text-coral"><Wrench :size="18" /></span>
            <h3 class="font-bold text-base text-ink">Aracı Servise Gönder</h3>
          </div>
          <button type="button" class="sheet-close-btn" @click="isServisModalOpen = false">
            <X :size="18" />
          </button>
        </header>

        <form class="sheet-body space-y-3" @submit.prevent="submitServisForm">
          <div v-if="servisFormError" class="p-2.5 rounded-xl bg-coral-subtle text-coral text-xs font-semibold">
            {{ servisFormError }}
          </div>

          <div>
            <label class="form-label">Araç Seçin *</label>
            <select v-model="servisFormData.arac_id" required class="form-input">
              <option value="">Araç Seçiniz</option>
              <option
                v-for="a in items"
                :key="a.id"
                :value="a.id"
              >
                {{ a.plaka }} - {{ a.marka }} {{ a.model }}
              </option>
            </select>
          </div>

          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="form-label">Giriş Tarihi</label>
              <input v-model="servisFormData.servis_tarihi" type="date" required class="form-input" />
            </div>
            <div>
              <label class="form-label">Giriş KM</label>
              <input v-model="servisFormData.giris_km" type="number" required class="form-input font-bold" />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="form-label">Servis Adı / Firma *</label>
              <input v-model="servisFormData.servis_adi" type="text" required placeholder="Yetkili / Özel Servis" class="form-input" />
            </div>
            <div>
              <label class="form-label">Servis Nedeni</label>
              <select v-model="servisFormData.servis_nedeni" class="form-input">
                <option value="Periyodik Bakım">Periyodik Bakım</option>
                <option value="Arıza / Onarım">Arıza / Onarım</option>
                <option value="Kaza Onarımı">Kaza Onarımı</option>
                <option value="Lastik Değişimi">Lastik Değişimi</option>
                <option value="Muayene Hazırlık">Muayene Hazırlık</option>
                <option value="Diğer">Diğer</option>
              </select>
            </div>
          </div>

          <div>
            <label class="form-label">Yapılacak İşlemler</label>
            <textarea v-model="servisFormData.yapilan_islemler" rows="2" placeholder="Yağ değişimi, fren balataları vb.…" class="form-input"></textarea>
          </div>

          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="form-label">Tahmini Tutar (₺)</label>
              <input v-model="servisFormData.tutar" type="number" step="0.01" placeholder="0.00" class="form-input" />
            </div>
            <div>
              <label class="form-label">Fatura No</label>
              <input v-model="servisFormData.fatura_no" type="text" placeholder="Fatura No" class="form-input" />
            </div>
          </div>

          <button
            type="submit"
            class="clay-pill-btn clay-pill-btn--primary w-full py-3 font-bold text-sm mt-2"
            :disabled="servisFormLoading"
          >
            {{ servisFormLoading ? 'Kaydediliyor…' : 'Servise Al' }}
          </button>
        </form>
      </div>
    </div>

    <!-- ======================================================== -->
    <!-- MODAL 8: SERVİS TAMAMLAMA BOTTOM SHEET -->
    <!-- ======================================================== -->
    <div
      v-if="isServisCompleteModalOpen"
      class="clay-modal-overlay"
      @click.self="isServisCompleteModalOpen = false"
    >
      <div class="clay-bottom-sheet">
        <header class="sheet-header">
          <div class="flex items-center gap-2">
            <span class="bento-mini-btn bg-emerald-subtle text-emerald"><CheckCircle2 :size="18" /></span>
            <div>
              <h3 class="font-bold text-base text-ink">Servisi Tamamla</h3>
              <p class="text-[11px] text-muted">{{ servisCompleteData.arac_plaka }} • {{ servisCompleteData.servis_adi }}</p>
            </div>
          </div>
          <button type="button" class="sheet-close-btn" @click="isServisCompleteModalOpen = false">
            <X :size="18" />
          </button>
        </header>

        <form class="sheet-body space-y-3" @submit.prevent="submitServisComplete">
          <div v-if="servisCompleteError" class="p-2.5 rounded-xl bg-coral-subtle text-coral text-xs font-semibold">
            {{ servisCompleteError }}
          </div>

          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="form-label">Teslim / Çıkış Tarihi</label>
              <input v-model="servisCompleteData.iade_tarihi" type="date" required class="form-input" />
            </div>
            <div>
              <label class="form-label">Çıkış KM *</label>
              <input v-model="servisCompleteData.cikis_km" type="number" required class="form-input font-bold" />
            </div>
          </div>

          <div>
            <label class="form-label">Kesinleşen Tutar (₺)</label>
            <input v-model="servisCompleteData.tutar" type="number" step="0.01" placeholder="0.00" class="form-input font-black text-emerald" />
          </div>

          <div>
            <label class="form-label">Gerçekleşen İşlemler & Parçalar</label>
            <textarea v-model="servisCompleteData.yapilan_islemler" rows="3" placeholder="Filtreler, yağ değişti, rot ayarı yapıldı…" class="form-input"></textarea>
          </div>

          <button
            type="submit"
            class="clay-pill-btn clay-pill-btn--primary w-full py-3 font-bold text-sm mt-2"
            :disabled="servisCompleteLoading"
          >
            {{ servisCompleteLoading ? 'Tamamlanıyor…' : 'Servis Çıkışını Onayla' }}
          </button>
        </form>
      </div>
    </div>

    <!-- ======================================================== -->
    <!-- MODAL 9: SİLME ONAY MODALI -->
    <!-- ======================================================== -->
    <div
      v-if="isDeleteModalOpen"
      class="clay-modal-overlay"
      @click.self="isDeleteModalOpen = false"
    >
      <div class="clay-confirm-box">
        <span class="confirm-icon bg-coral-subtle text-coral"><Trash2 :size="24" /></span>
        <h3 class="font-bold text-base text-ink mt-2">Silme Onayı</h3>
        <p class="text-xs text-muted mt-1">
          <b>"{{ deleteTarget.title }}"</b> kaydını silmek istediğinize emin misiniz? Bu işlem geri alınamaz.
        </p>
        <div class="flex gap-2 mt-4">
          <button
            type="button"
            class="flex-1 clay-pill-btn py-2 text-xs font-bold"
            @click="isDeleteModalOpen = false"
          >
            Vazgeç
          </button>
          <button
            type="button"
            class="flex-1 clay-pill-btn clay-pill-btn--danger py-2 text-xs font-bold"
            :disabled="deleteLoading"
            @click="executeDelete"
          >
            {{ deleteLoading ? 'Siliniyor…' : 'Evet, Sil' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ======================================================== -->
    <!-- MODAL 10: DÖNEM & AY SEÇİCİ BOTTOM SHEET -->
    <!-- ======================================================== -->
    <div
      v-if="isMonthModalOpen"
      class="clay-modal-overlay"
      @click.self="isMonthModalOpen = false"
    >
      <div class="clay-bottom-sheet month-picker-sheet">
        <div class="sheet-drag-handle"></div>

        <header class="sheet-header">
          <div class="flex items-center gap-2">
            <span class="detail-header-icon bg-indigo-subtle text-indigo">
              <Calendar :size="18" />
            </span>
            <div>
              <h3 class="font-bold text-base text-ink">Dönem & Ay Seçimi</h3>
              <p class="text-[11px] text-muted">Görüntülemek istediğiniz ayı seçin</p>
            </div>
          </div>
          <button type="button" class="sheet-close-btn" title="Kapat" @click="isMonthModalOpen = false">
            <X :size="18" />
          </button>
        </header>

        <div class="month-picker-body">
          <!-- Yıl Navigasyonu -->
          <div class="year-nav-row">
            <button type="button" class="year-nav-btn" title="Önceki Yıl" @click="prevPickerYear">
              <ChevronLeft :size="16" />
            </button>
            <strong class="year-nav-label">{{ pickerYear }}</strong>
            <button type="button" class="year-nav-btn" title="Sonraki Yıl" @click="nextPickerYear">
              <ChevronRight :size="16" />
            </button>
          </div>

          <!-- 12 Ay Izgarası (3 Sütun x 4 Satır) -->
          <div class="month-grid">
            <button
              v-for="m in monthList"
              :key="m.value"
              type="button"
              class="month-grid-btn"
              :class="{
                'is-active': periodFilter === 'custom' && customYear === String(pickerYear) && customMonth === m.value,
                'is-current': String(currentYear) === String(pickerYear) && currentMonth === m.value
              }"
              @click="selectMonth(m.value)"
            >
              <span class="month-name">{{ m.name }}</span>
              <span v-if="String(currentYear) === String(pickerYear) && currentMonth === m.value" class="current-month-dot" title="Güncel Ay"></span>
            </button>
          </div>

          <!-- Hızlı Kısayollar -->
          <div class="month-shortcuts">
            <button
              type="button"
              class="shortcut-btn"
              :class="{ 'is-selected': periodFilter === 'this_month' }"
              @click="setPeriod('this_month'); isMonthModalOpen = false"
            >
              Bu Ay ({{ monthList[new Date().getMonth()].name }})
            </button>
            <button
              type="button"
              class="shortcut-btn"
              :class="{ 'is-selected': periodFilter === 'all' }"
              @click="setPeriod('all'); isMonthModalOpen = false"
            >
              Tüm Zamanlar
            </button>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
/* -------------------------------------------------------------
   ÖZEL STİLLER: ERSAN MOBIL ARAÇ TAKİP BENTO
------------------------------------------------------------- */
.ds-arac {
  padding-bottom: 120px;
}

/* Minimal Hero Bento Kartı (Üst Özet Kartı) */
.arac-hero-bento {
  padding: 13px 15px 14px !important;
  border-radius: 20px !important;
}

.arac-hero-bento .bento-head {
  margin-bottom: 8px !important;
}

.arac-hero-bento .bento-head-title h3 {
  font-size: 15px !important;
  font-weight: 700 !important;
  line-height: 1.2 !important;
}

.arac-hero-bento .dark-pill-tag {
  font-size: 8px !important;
  padding: 2px 6px !important;
  margin-bottom: 2px !important;
}

.arac-hero-bento .bento-mini-btn {
  width: 28px !important;
  height: 28px !important;
}

.arac-hero-bento .segmented-track {
  height: 5px !important;
  margin-bottom: 9px !important;
  gap: 2.5px !important;
}

.arac-hero-bento .bento-metrics-grid {
  gap: 7px !important;
}

.arac-hero-bento .metric-cell {
  padding: 7px 10px !important;
  border-radius: 10px !important;
  background: rgba(255, 255, 255, 0.04) !important;
}

.arac-hero-bento .metric-cell strong {
  font-size: 14.5px !important;
  font-weight: 800 !important;
  line-height: 1.2 !important;
}

.arac-hero-bento .metric-cell strong small {
  font-size: 9.5px !important;
  font-weight: 500 !important;
}

.arac-hero-bento .metric-cell span {
  font-size: 9.5px !important;
  margin-top: 1px !important;
  line-height: 1.2 !important;
  opacity: 0.85;
}

/* Türk Plaka Rozeti */
.tr-plate-badge {
  display: inline-flex;
  align-items: center;
  border: 1.5px solid #1e293b;
  border-radius: 7px;
  background: #ffffff;
  padding: 1.5px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  vertical-align: middle;
}

.tr-band {
  background: #0284c7;
  color: #ffffff;
  font: 900 9px sans-serif;
  padding: 3px 5px;
  border-radius: 4px;
  display: grid;
  place-items: center;
  letter-spacing: 0.02em;
}

.plate-text {
  font: 900 13.5px 'Space Grotesk', monospace;
  letter-spacing: 0.05em;
  color: #0f172a;
  padding: 1px 7px 1px 6px;
  line-height: 1.1;
}

/* Durum Hapları & Noktaları */
.status-pill {
  display: inline-flex;
  align-items: center;
  font: 700 11px var(--ds-font-main);
  padding: 4px 9px;
  border-radius: var(--ds-radius-pill);
  line-height: 1.2;
}

.status-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  flex-shrink: 0;
}
.status-dot--blue    { background: #0284c7; }
.status-dot--emerald { background: #16a34a; }
.status-dot--danger  { background: #dc2626; }
.status-dot--muted   { background: #94a3b8; }

.status-pill--blue    { background: #e0f2fe; color: #0284c7; }
.status-pill--emerald { background: #dcfce7; color: #16a34a; }
.status-pill--danger  { background: #fee2e2; color: #dc2626; }
.status-pill--amber   { background: #fef3c7; color: #d97706; }
.status-pill--muted   { background: #f1f5f9; color: #64748b; }

.spec-tag {
  display: inline-block;
  padding: 2.5px 7px;
  border-radius: 6px;
  background: var(--ds-surface-subtle);
  font: 700 10px var(--ds-font-mono);
  color: var(--ds-muted);
}

/* ========================================================
   1. VEHICLE MASTER CARD (ARAÇLAR SEKMESİ)
   ======================================================== */
.vehicle-master-card {
  padding: 16px 16px 14px;
  cursor: pointer;
  border: 1px solid rgba(255, 255, 255, 0.9);
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.vehicle-master-card:active {
  transform: scale(0.985);
}

.vm-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 10px;
}

.vm-model-row {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 12px;
}

.vm-title {
  font-size: 14px;
  font-weight: 800;
  color: var(--ds-ink);
  letter-spacing: -0.02em;
  margin: 0;
}

.vm-tags {
  display: flex;
  align-items: center;
  gap: 4px;
  flex-shrink: 0;
}

.vm-stats-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 8px;
  margin-bottom: 10px;
}

.vm-stat-cell {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 10px;
  border-radius: 12px;
  background: var(--ds-surface-subtle);
  border: 1px solid var(--ds-border-subtle);
  min-width: 0;
}

.vm-stat-icon {
  display: grid;
  place-items: center;
  width: 28px;
  height: 28px;
  border-radius: 8px;
  background: #ffffff;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
  flex-shrink: 0;
}

.vm-stat-content {
  min-width: 0;
  flex: 1;
}

.vm-stat-label {
  display: block;
  font: 700 9px var(--ds-font-mono);
  color: var(--ds-muted);
  letter-spacing: 0.04em;
  line-height: 1;
}

.vm-stat-value {
  display: block;
  font-size: 12px;
  font-weight: 800;
  margin-top: 3px;
  line-height: 1.1;
}

.vm-evrak-track {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 5px;
  margin-bottom: 10px;
}

.evrak-pill-item {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
  padding: 5px 6px;
  border-radius: 8px;
  font-size: 10px;
  font-weight: 700;
  border: 1px solid transparent;
}

.evrak-pill-item.is-ok {
  background: #f0fdf4;
  border-color: #bbf7d0;
  color: #166534;
}

.evrak-pill-item.is-warning {
  background: #fff7ed;
  border-color: #fed7aa;
  color: #c2410c;
}

.evrak-pill-item.is-danger {
  background: #fef2f2;
  border-color: #fecaca;
  color: #b91c1c;
}

.evrak-name {
  font-weight: 600;
}

.evrak-days {
  font-weight: 800;
}

/* ========================================================
   2. KM JOURNEY CARD (KM SEKMESİ - KULLANICI DOSTU)
   ======================================================== */
.km-journey-card {
  padding: 16px;
  border: 1px solid rgba(255, 255, 255, 0.9);
}

.km-card-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 12px;
}

.km-vehicle-identity {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.km-vehicle-meta {
  display: flex;
  align-items: center;
}

.km-vehicle-name {
  font-size: 12px;
  font-weight: 700;
  color: var(--ds-muted);
}

.km-badge-wrap {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 4px;
  flex-shrink: 0;
}

.km-diff-pill {
  display: inline-flex;
  align-items: center;
  padding: 3px 10px;
  border-radius: var(--ds-radius-pill);
  background: #eef2ff;
  color: #4f46e5;
  font: 900 13px 'Space Grotesk', monospace;
  box-shadow: 0 1px 2px rgba(79, 70, 229, 0.1);
}

.km-date-badge {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  font: 600 10px var(--ds-font-mono);
  color: var(--ds-muted);
}

.km-journey-box {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 14px;
  border-radius: 14px;
  background: var(--ds-surface-subtle);
  border: 1px solid var(--ds-border-subtle);
  margin-bottom: 12px;
}

.journey-point {
  display: flex;
  flex-direction: column;
}

.journey-label {
  font: 700 9px var(--ds-font-mono);
  letter-spacing: 0.05em;
  color: var(--ds-muted);
  margin-bottom: 2px;
}

.journey-val {
  font: 800 13px 'Space Grotesk', monospace;
  color: var(--ds-ink);
}

.journey-divider {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 0 12px;
  flex: 1;
}

.journey-line {
  flex: 1;
  height: 2px;
  border-top: 1.5px dashed #cbd5e1;
}

.journey-arrow {
  color: #94a3b8;
  display: grid;
  place-items: center;
}

.km-driver-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding-top: 8px;
  border-top: 1px solid var(--ds-border-subtle);
}

.driver-info {
  display: flex;
  align-items: center;
  gap: 7px;
  min-width: 0;
}

.driver-avatar {
  display: grid;
  place-items: center;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background: #e2e8f0;
  color: #334155;
  font: 700 10px var(--ds-font-mono);
  flex-shrink: 0;
}

.driver-text {
  min-width: 0;
  display: flex;
  flex-direction: column;
}

.driver-role {
  font-size: 9px;
  color: var(--ds-muted);
  line-height: 1;
}

.driver-name {
  font-size: 11px;
  font-weight: 700;
  color: var(--ds-ink);
  line-height: 1.2;
  margin-top: 2px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.km-note-chip {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 3px 7px;
  border-radius: 6px;
  background: var(--ds-surface-subtle);
  color: var(--ds-muted);
  font-size: 10px;
  max-width: 130px;
  flex-shrink: 0;
}

.evrak-mini-card {
  padding: 6px 8px;
  border-radius: 10px;
  background: var(--ds-surface-subtle);
  border: 1px solid var(--ds-border-subtle);
  text-align: center;
}

.evrak-mini-card.is-warning {
  background: #fff7ed;
  border-color: #fed7aa;
}

.evrak-mini-card.is-warning .evrak-val {
  color: #ea580c;
}

.evrak-label {
  display: block;
  font-size: 9px;
  font-weight: 700;
  text-transform: uppercase;
  color: var(--ds-muted);
}

.evrak-val {
  display: block;
  font-size: 11px;
  font-weight: 800;
  color: var(--ds-ink);
  margin-top: 1px;
}

/* Kart Aksiyonları */
.card-footer-actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 4px;
}

.card-action-btn {
  flex: 1;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
  padding: 6px 4px;
  border-radius: 8px;
  border: 0;
  background: transparent;
  font-size: 11px;
  font-weight: 700;
  color: var(--ds-muted);
  cursor: pointer;
  transition: all 0.15s ease;
}

.card-action-btn:active {
  background: var(--ds-surface-subtle);
  transform: scale(0.96);
}

/* Evrak Alert Banner */
.evrak-alert-card {
  margin-bottom: 14px;
  cursor: pointer;
}

.bg-amber-subtle { background: #fef3c7; }
.text-amber      { color: #d97706; }
.bg-blue-subtle  { background: #e0f2fe; }
.text-blue       { color: #0284c7; }
.bg-coral-subtle { background: #ffe4e6; }
.text-coral      { color: #e11d48; }
.bg-emerald-subtle { background: #dcfce7; }
.text-emerald    { color: #16a34a; }
.bg-indigo-subtle { background: #e0e7ff; }
.text-indigo     { color: #4f46e5; }

/* Yatay Kayar Filtre Hapları */
.clay-pills-scroll {
  display: flex;
  align-items: center;
  gap: 6px;
  overflow-x: auto;
  padding-bottom: 4px;
  scrollbar-width: none;
}
.clay-pills-scroll::-webkit-scrollbar {
  display: none;
}

.clay-filter-pill {
  flex-shrink: 0;
  padding: 6px 14px;
  border-radius: var(--ds-radius-pill);
  border: 1px solid var(--ds-border);
  background: var(--ds-surface);
  color: var(--ds-muted);
  font: 700 11px var(--ds-font-main);
  box-shadow: var(--ds-shadow-pill);
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.15s ease;
}

.clay-filter-pill.is-active {
  background: var(--ds-primary);
  color: #ffffff;
  border-color: var(--ds-primary);
}

.clay-filter-pill--warn.is-active {
  background: #ea580c;
  color: #ffffff;
}

/* Ay & Dönem Seçici Bottom Sheet */
.month-picker-sheet {
  max-width: 440px;
  padding: 14px 18px 28px;
}

.month-picker-body {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.year-nav-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 6px 12px;
  border-radius: 14px;
  background: var(--ds-surface-subtle);
}

.year-nav-btn {
  display: grid;
  place-items: center;
  width: 34px;
  height: 34px;
  border-radius: 10px;
  border: 1px solid rgba(15, 23, 42, 0.06);
  background: #ffffff;
  color: var(--ds-ink);
  cursor: pointer;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
  transition: transform 0.15s ease, background 0.15s ease;
}

.year-nav-btn:active {
  transform: scale(0.92);
  background: var(--ds-surface-subtle);
}

.year-nav-label {
  font-size: 17px;
  font-weight: 800;
  letter-spacing: -0.02em;
  color: var(--ds-ink);
}

.month-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
}

.month-grid-btn {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  height: 46px;
  border-radius: 14px;
  border: 1px solid rgba(15, 23, 42, 0.06);
  background: var(--ds-surface-subtle);
  font-size: 12.5px;
  font-weight: 700;
  color: var(--ds-ink);
  cursor: pointer;
  transition: all 0.15s ease;
}

.month-grid-btn:active {
  transform: scale(0.95);
}

.month-grid-btn.is-active {
  background: var(--ds-primary);
  color: #ffffff;
  border-color: var(--ds-primary);
  box-shadow: 0 4px 12px rgba(24, 28, 36, 0.22);
}

.month-grid-btn.is-current:not(.is-active) {
  border-color: #38bdf8;
  color: #0284c7;
  background: rgba(56, 189, 248, 0.08);
}

.current-month-dot {
  width: 4px;
  height: 4px;
  border-radius: 50%;
  background: #0284c7;
  position: absolute;
  bottom: 5px;
}

.month-grid-btn.is-active .current-month-dot {
  background: #ffffff;
}

.month-shortcuts {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
  padding-top: 10px;
  border-top: 1px solid var(--ds-border-subtle);
}

.shortcut-btn {
  padding: 10px 12px;
  border-radius: 12px;
  border: 1px solid rgba(15, 23, 42, 0.08);
  background: #ffffff;
  font-size: 11.5px;
  font-weight: 700;
  color: var(--ds-muted);
  cursor: pointer;
  text-align: center;
  transition: all 0.15s ease;
}

.shortcut-btn:active {
  background: var(--ds-surface-subtle);
}

.shortcut-btn.is-selected {
  background: var(--ds-surface-subtle);
  color: var(--ds-ink);
  font-weight: 800;
  border-color: #94a3b8;
}

/* FAB Dropdown */
.fab-dropdown-menu {
  position: absolute;
  top: 52px;
  right: 0;
  width: 190px;
  background: #ffffff;
  border: 1px solid var(--ds-border);
  border-radius: 18px;
  box-shadow: var(--ds-shadow-float);
  padding: 6px;
  z-index: 100;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.fab-menu-item {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
  padding: 8px 10px;
  border-radius: 12px;
  border: 0;
  background: transparent;
  color: var(--ds-ink);
  font: 700 12px var(--ds-font-main);
  text-align: left;
  cursor: pointer;
  transition: background 0.15s ease;
}

.fab-menu-item:active {
  background: var(--ds-surface-subtle);
}

.fab-menu-icon {
  display: grid;
  place-items: center;
  width: 28px;
  height: 28px;
  border-radius: 8px;
  flex-shrink: 0;
}

/* Modallar & Bottom Sheet */
.clay-modal-overlay {
  position: fixed;
  inset: 0;
  background: var(--ds-overlay);
  backdrop-filter: blur(4px);
  z-index: 1000;
  display: flex;
  align-items: flex-end;
  justify-content: center;
}

.clay-bottom-sheet {
  width: 100%;
  max-width: 500px;
  max-height: 88svh;
  background: #ffffff;
  border-radius: 28px 28px 0 0;
  box-shadow: var(--ds-shadow-float);
  overflow-y: auto;
  padding: 18px 20px 32px;
  animation: slideUp 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes slideUp {
  from { transform: translateY(100%); }
  to { transform: translateY(0); }
}

.sheet-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
  padding-bottom: 12px;
  border-bottom: 1px solid var(--ds-border-subtle);
}

.sheet-close-btn {
  display: grid;
  place-items: center;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  border: 0;
  background: var(--ds-surface-subtle);
  color: var(--ds-muted);
  cursor: pointer;
}

/* ========================================================
   ARAÇ DETAY KARTI (BENTO & CLAYMORPHIC STANDART)
   ======================================================== */
.detail-bottom-sheet {
  padding: 12px 18px 30px;
  max-height: 90svh;
}

.sheet-drag-handle {
  width: 40px;
  height: 4px;
  border-radius: 99px;
  background: #cbd5e1;
  margin: 0 auto 12px;
}

.detail-header-icon {
  display: grid;
  place-items: center;
  width: 38px;
  height: 38px;
  border-radius: 12px;
  background: rgba(56, 189, 248, 0.12);
  color: #0284c7;
  flex-shrink: 0;
}

.detail-sheet-content {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

/* 1. Kimlik Hero Kartı */
.detail-hero-box {
  padding: 14px 16px;
  border-radius: 20px;
  background: var(--ds-surface-subtle);
  border: 1px solid rgba(15, 23, 42, 0.05);
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.detail-hero-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.detail-hero-info {
  display: flex;
  flex-direction: column;
}

.detail-vehicle-name {
  font-size: 16px;
  font-weight: 800;
  color: var(--ds-ink);
  margin: 0;
  letter-spacing: -0.02em;
}

.detail-hero-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 6px;
}

.detail-spec-chip {
  font-size: 11px;
  font-weight: 700;
  color: var(--ds-muted);
  background: #ffffff;
  padding: 3px 9px;
  border-radius: 99px;
  border: 1px solid rgba(15, 23, 42, 0.06);
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
}

.chip-amber {
  background: #fef3c7 !important;
  color: #b45309 !important;
  border-color: #fde68a !important;
}

/* 2. 2x2 Bento Metrik Izgarası */
.detail-metrics-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 10px;
}

.detail-metric-card {
  display: flex;
  gap: 10px;
  align-items: flex-start;
  padding: 12px 14px;
  border-radius: 16px;
  background: #ffffff;
  border: 1px solid rgba(15, 23, 42, 0.06);
  box-shadow: var(--ds-shadow-tile);
}

.detail-metric-icon {
  display: grid;
  place-items: center;
  width: 32px;
  height: 32px;
  border-radius: 10px;
  background: var(--ds-surface-subtle);
  flex-shrink: 0;
}

.detail-metric-body {
  display: flex;
  flex-direction: column;
  min-width: 0;
  flex: 1;
}

.detail-metric-label {
  font: 800 9.5px var(--ds-font-mono, monospace);
  color: var(--ds-muted);
  letter-spacing: 0.05em;
  text-transform: uppercase;
  margin-bottom: 2px;
}

.detail-metric-value {
  font-size: 13px;
  font-weight: 800;
  color: var(--ds-ink);
  line-height: 1.25;
}

.detail-metric-hint {
  font-size: 10px;
  color: var(--ds-muted);
  margin-top: 2px;
}

/* 3. Panel Kartları */
.detail-card-panel {
  padding: 14px 16px;
  border-radius: 20px;
  background: #ffffff;
  border: 1px solid rgba(15, 23, 42, 0.06);
  box-shadow: var(--ds-shadow-tile);
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.detail-panel-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.detail-panel-subtext {
  font-size: 10px;
  color: var(--ds-muted);
}

.detail-person-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 12px;
  border-radius: 14px;
  background: var(--ds-surface-subtle);
}

.detail-person-avatar {
  display: grid;
  place-items: center;
  width: 38px;
  height: 38px;
  border-radius: 50%;
  background: linear-gradient(135deg, #38bdf8, #1d4ed8);
  color: #ffffff;
  font-weight: 800;
  font-size: 13px;
  flex-shrink: 0;
  box-shadow: 0 4px 10px rgba(29, 78, 216, 0.25);
}

.detail-person-text {
  display: flex;
  flex-direction: column;
  min-width: 0;
  flex: 1;
}

.detail-person-name {
  font-size: 13px;
  font-weight: 800;
  color: var(--ds-ink);
}

.detail-person-dept {
  font-size: 11px;
  color: var(--ds-muted);
  margin-top: 1px;
}

.detail-empty-box {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 12px;
  border-radius: 14px;
  background: rgba(16, 185, 129, 0.08);
  color: #047857;
  font-size: 11.5px;
  font-weight: 600;
}

/* 4. Evrak & Poliçe Takvimi */
.detail-docs-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
}

.detail-doc-tile {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  padding: 10px 6px;
  border-radius: 14px;
  background: var(--ds-surface-subtle);
  border: 1px solid rgba(15, 23, 42, 0.05);
  gap: 4px;
}

.doc-tile-top {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 10px;
  font-weight: 700;
  color: var(--ds-muted);
}

.doc-tile-date {
  font-size: 11px;
  font-weight: 800;
  color: var(--ds-ink);
  line-height: 1.2;
}

.doc-tile-badge {
  font-size: 9.5px;
  font-weight: 800;
  padding: 2px 6px;
  border-radius: 99px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.detail-doc-tile.is-ok .doc-tile-badge {
  background: rgba(16, 185, 129, 0.14);
  color: #059669;
}

.detail-doc-tile.is-warning .doc-tile-badge {
  background: rgba(245, 158, 11, 0.16);
  color: #b45309;
}

.detail-doc-tile.is-expired .doc-tile-badge {
  background: rgba(239, 68, 68, 0.14);
  color: #dc2626;
}

.detail-doc-tile.is-expired {
  border-color: rgba(239, 68, 68, 0.3);
  background: #fef2f2;
}

.doc-tile-badge.is-muted {
  background: rgba(100, 116, 139, 0.1);
  color: var(--ds-muted);
}

/* 5. Ruhsat Kartı */
.security-chip {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font: 800 10px var(--ds-font-mono, monospace);
  color: #059669;
  background: rgba(16, 185, 129, 0.12);
  padding: 3px 8px;
  border-radius: 99px;
}

.ruhsat-file-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 10px 12px;
  border-radius: 14px;
  background: var(--ds-surface-subtle);
  border: 1px solid rgba(15, 23, 42, 0.05);
}

.ruhsat-file-icon {
  display: grid;
  place-items: center;
  width: 36px;
  height: 36px;
  border-radius: 10px;
  background: rgba(56, 189, 248, 0.15);
  color: #0284c7;
  flex-shrink: 0;
}

.ruhsat-file-info {
  display: flex;
  flex-direction: column;
  min-width: 0;
  flex: 1;
}

.ruhsat-file-name {
  font-size: 12px;
  font-weight: 700;
  color: var(--ds-ink);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.ruhsat-file-meta {
  font-size: 10px;
  color: var(--ds-muted);
  margin-top: 1px;
}

.ruhsat-file-actions {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-shrink: 0;
}

.ruhsat-view-btn {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 6px 12px;
  border-radius: 99px;
  background: var(--ds-primary);
  color: #ffffff;
  font-size: 11px;
  font-weight: 700;
  text-decoration: none;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
  transition: transform 0.15s ease;
}

.ruhsat-view-btn:active {
  transform: scale(0.96);
}

.ruhsat-delete-btn {
  display: grid;
  place-items: center;
  width: 30px;
  height: 30px;
  border-radius: 50%;
  border: 0;
  background: rgba(239, 68, 68, 0.1);
  color: #dc2626;
  cursor: pointer;
  transition: all 0.15s ease;
}

.ruhsat-delete-btn:active {
  background: #fee2e2;
  transform: scale(0.92);
}

.ruhsat-upload-box {
  border: 1.5px dashed #cbd5e1;
  border-radius: 16px;
  background: var(--ds-surface-subtle);
  text-align: center;
  padding: 16px 12px;
  cursor: pointer;
  transition: all 0.15s ease;
}

.ruhsat-upload-box:hover {
  border-color: #38bdf8;
  background: rgba(56, 189, 248, 0.04);
}

.ruhsat-upload-label {
  display: flex;
  flex-direction: column;
  align-items: center;
  cursor: pointer;
}

.ruhsat-upload-icon {
  display: grid;
  place-items: center;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: #ffffff;
  color: #0284c7;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
  margin-bottom: 6px;
}

.ruhsat-upload-title {
  font-size: 12px;
  font-weight: 700;
  color: #0284c7;
}

.ruhsat-upload-hint {
  font-size: 10px;
  color: var(--ds-muted);
  margin-top: 2px;
}

.hidden-file-input {
  display: none;
}

/* 6. Alt Eylemler */
.detail-sheet-actions {
  display: flex;
  gap: 10px;
  padding-top: 8px;
  margin-top: 4px;
}

.detail-action-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  height: 48px;
  border-radius: var(--ds-radius-pill);
  border: 0;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.detail-action-btn:active {
  transform: scale(0.97);
}

.detail-action-btn--primary {
  flex: 1;
  background: var(--ds-primary);
  color: #ffffff;
  box-shadow: 0 4px 14px rgba(24, 28, 36, 0.25);
}

.detail-action-btn--danger {
  padding: 0 20px;
  background: rgba(239, 68, 68, 0.12);
  color: #dc2626;
  border: 1px solid rgba(239, 68, 68, 0.2);
}

.detail-action-btn--danger:active {
  background: #fee2e2;
}

.clay-confirm-box {
  background: #ffffff;
  border-radius: 24px;
  padding: 24px 20px;
  max-width: 340px;
  width: 90%;
  margin: auto;
  text-align: center;
  box-shadow: var(--ds-shadow-float);
}

.confirm-icon {
  display: grid;
  place-items: center;
  width: 48px;
  height: 48px;
  border-radius: 50%;
  margin: 0 auto;
}

/* Form Kontrolleri */
.form-label {
  display: block;
  font-size: 11px;
  font-weight: 700;
  color: var(--ds-muted);
  text-transform: uppercase;
  margin-bottom: 4px;
}

.form-input {
  width: 100%;
  padding: 10px 12px;
  border-radius: 12px;
  border: 1px solid #cbd5e1;
  background: var(--ds-surface-subtle);
  font: 500 13px var(--ds-font-main);
  color: var(--ds-ink);
  outline: none;
  transition: all 0.15s ease;
}

.form-input:focus {
  border-color: var(--ds-blue-ink);
  background: #ffffff;
  box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.15);
}

.clay-pill-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 8px 16px;
  border-radius: var(--ds-radius-pill);
  border: 0;
  background: var(--ds-surface-subtle);
  color: var(--ds-ink);
  font: 700 12px var(--ds-font-main);
  cursor: pointer;
  transition: all 0.15s ease;
}

.clay-pill-btn--primary {
  background: var(--ds-primary);
  color: #ffffff;
}

.clay-pill-btn--danger {
  background: var(--ds-danger);
  color: #ffffff;
}

.clay-pill-btn--warning {
  background: #f59e0b;
  color: #ffffff;
}

/* Toast Bildirimi */
.clay-toast {
  position: fixed;
  top: calc(var(--ds-safe-top) + 16px);
  left: 20px;
  right: 20px;
  max-width: 420px;
  margin: 0 auto;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 14px;
  border-radius: 16px;
  box-shadow: var(--ds-shadow-float);
  z-index: 2000;
  animation: fadeInDown 0.25s ease;
}

.clay-toast--success {
  background: #0f172a;
  color: #ffffff;
}

.clay-toast--error {
  background: #dc2626;
  color: #ffffff;
}

.clay-toast__msg {
  flex: 1;
  font: 700 12px var(--ds-font-main);
}

.clay-toast__close {
  background: transparent;
  border: 0;
  color: rgba(255, 255, 255, 0.7);
  cursor: pointer;
}

@keyframes fadeInDown {
  from { opacity: 0; transform: translateY(-10px); }
  to { opacity: 1; transform: translateY(0); }
}

.empty-state-wrap {
  text-align: center;
  padding: 40px 20px;
  background: var(--ds-surface);
  border-radius: var(--ds-radius-card);
  border: 1px solid var(--ds-border);
  box-shadow: var(--ds-shadow-bento);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}

.is-spinning {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  100% { transform: rotate(360deg); }
}

.ds-kicker {
  display: block;
  font: 700 10px var(--ds-font-mono);
  letter-spacing: 0.08em;
  color: var(--ds-muted);
}
</style>
