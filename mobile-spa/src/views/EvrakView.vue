<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import {
  AlertCircle,
  ArrowDownLeft,
  ArrowLeft,
  ArrowUpRight,
  Building2,
  Calendar,
  Car,
  Check,
  CheckCircle2,
  ChevronRight,
  Clock,
  Download,
  Eye,
  FileCheck,
  FilePlus,
  FileText,
  Lock,
  Paperclip,
  Pencil,
  Plus,
  RefreshCw,
  RotateCcw,
  Search,
  Send,
  Trash2,
  Undo2,
  User,
  UserCheck,
  Users,
  X,
  Zap,
} from '@lucide/vue'
import TopBar from '../components/TopBar.vue'
import { auth } from '../services/auth.js'
import { evrakApi } from '../services/evrak.js'

const router = useRouter()

// Liste & Sayfa Durumu
const items = ref([])
const stats = ref({
  toplam_evrak: 0,
  gelen_evrak: 0,
  giden_evrak: 0,
  cevap_bekleyen: 0,
  imzamda_bekleyen: 0,
})
const pagination = ref({ page: 1, limit: 30, total: 0, has_more: false })
const loading = ref(true)
const loadingMore = ref(false)
const error = ref('')

// Filtreler
const activeFilter = ref('all') // 'all', 'gelen', 'giden', 'bekleyen', 'imzam'
const searchQuery = ref('')
let searchDebounce = null

// Meta Veriler (Personeller, Araçlar, İmzacılar vb.)
const meta = ref({
  personeller: [],
  ofis_personeller: [],
  araclar: [],
  signing_users: [],
  gelen_evraklar: [],
  next_evrak_no: { gelen: 1, giden: 1 },
})
const metaLoading = ref(false)

// Detay Çekmecesi Durumu
const isDetailOpen = ref(false)
const selectedDetail = ref(null)
const detailLoading = ref(false)

// Yeni & Düzenleme Formu Durumu
const isFormOpen = ref(false)
const isEditing = ref(false)
const formSubmitting = ref(false)
const formError = ref('')

const formData = ref({
  id: '',
  evrak_tipi: 'gelen',
  tarih: new Date().toISOString().slice(0, 10),
  evrak_no: '',
  konu: '',
  kurum_adi: '',
  muhatap_alt_birim: '',
  muhatap_adres: '',
  personel_id: '',
  ilgili_personel_id: '',
  cevap_verildi_mi: false,
  cevap_tarihi: '',
  personel_bildirim_durumu: false,
  aciklama: '',
  ust_yazi_gerekli_degil: false,
  ilgiler: '',
  ekler: '',
  ilgili_evrak_id: '',
  plaka: '',
  ceza_tutari: '',
  tutar: '',
  imza_kullanici_ids: [],
  silinen_ek_ids: [],
})
const selectedFiles = ref([])

// Dosya Önizleme Durumu
const isPreviewOpen = ref(false)
const previewFile = ref({
  url: '',
  name: '',
  mime: '',
  isPdf: false,
  isImage: false,
})

// E-İmza & İşlem Onay Modalları
const isSignConfirmOpen = ref(false)
const isReturnConfirmOpen = ref(false)
const isDeleteConfirmOpen = ref(false)
const returnGerekce = ref('')
const activeDocId = ref('')
const actionLoading = ref(false)

// Toast Bildirimi
const toastMessage = ref('')
const toastType = ref('success')
let toastTimer = null

function showToast(msg, type = 'success') {
  toastMessage.value = msg
  toastType.value = type
  if (toastTimer) clearTimeout(toastTimer)
  toastTimer = setTimeout(() => {
    toastMessage.value = ''
  }, 3500)
}

// Konu Hızlı Seçenekleri
const presetKonular = [
  'İcra Yazısı',
  'Haciz Kaldırma Yazısı',
  'Maaş Haczi',
  'Trafik Cezası',
  'Sigorta Giriş/Çıkış',
  'Resmi Yazışma',
]

// Para Formatlayıcı
function formatMoney(amount) {
  if (amount === undefined || amount === null || amount === '') return '0,00 ₺'
  const val = Number(amount) || 0
  return (
    new Intl.NumberFormat('tr-TR', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(val) + ' ₺'
  )
}

// Dosya Boyutu Formatlayıcı
function formatFileSize(bytes) {
  const size = Number(bytes || 0)
  if (size <= 0) return ''
  if (size < 1024) return `${size} B`
  if (size < 1024 * 1024) return `${(size / 1024).toFixed(1)} KB`
  return `${(size / (1024 * 1024)).toFixed(1)} MB`
}

// Evrak Listesini Yükle
async function loadList(resetPage = true) {
  if (resetPage) {
    pagination.value.page = 1
    loading.value = true
  } else {
    loadingMore.value = true
  }
  error.value = ''

  try {
    const res = await evrakApi.list({
      filter: activeFilter.value,
      search: searchQuery.value,
      page: pagination.value.page,
      limit: pagination.value.limit,
    })

    if (resetPage) {
      items.value = res.items || []
    } else {
      items.value.push(...(res.items || []))
    }

    stats.value = res.stats || stats.value
    pagination.value = res.pagination || pagination.value
  } catch (err) {
    error.value = err.message || 'Evrak listesi alınamadı.'
  } finally {
    loading.value = false
    loadingMore.value = false
  }
}

// Daha Fazla Yükle (Infinite Scroll / Load More)
function loadMore() {
  if (loadingMore.value || !pagination.value.has_more) return
  pagination.value.page++
  loadList(false)
}

// Meta Bilgilerini Yükle
async function loadMeta() {
  if (metaLoading.value) return
  metaLoading.value = true
  try {
    const res = await evrakApi.meta()
    meta.value = res.meta || meta.value
  } catch (err) {
    console.error('Meta verileri yüklenemedi:', err)
  } finally {
    metaLoading.value = false
  }
}

// Filtre Değiştir
function setFilter(filterKey) {
  activeFilter.value = filterKey
  loadList(true)
}

// Arama Girdisi Dinleyicisi
function handleSearch(val) {
  searchQuery.value = val
  if (searchDebounce) clearTimeout(searchDebounce)
  searchDebounce = setTimeout(() => {
    loadList(true)
  }, 300)
}

// Detay Modalını Aç
async function openDetail(doc) {
  selectedDetail.value = doc
  isDetailOpen.value = true
  detailLoading.value = true

  try {
    const res = await evrakApi.detail(doc.id)
    if (res.data) {
      selectedDetail.value = res.data
    }
  } catch (err) {
    showToast(err.message || 'Detaylar alınamadı.', 'error')
  } finally {
    detailLoading.value = false
  }
}

// Yeni Evrak Formunu Aç (Gelen Evrak için Modal, Giden Evrak için /giden-evrak rotası)
async function openCreateForm(type = 'gelen') {
  if (type === 'giden') {
    router.push('/giden-evrak')
    return
  }

  await loadMeta()
  isEditing.value = false
  formError.value = ''
  selectedFiles.value = []

  const nextNo = meta.value.next_evrak_no.gelen

  formData.value = {
    id: '',
    evrak_tipi: 'gelen',
    tarih: new Date().toISOString().slice(0, 10),
    evrak_no: String(nextNo || ''),
    konu: '',
    kurum_adi: '',
    muhatap_alt_birim: '',
    muhatap_adres: '',
    personel_id: '',
    ilgili_personel_id: '',
    cevap_verildi_mi: false,
    cevap_tarihi: '',
    personel_bildirim_durumu: false,
    aciklama: '',
    ust_yazi_gerekli_degil: false,
    ilgiler: '',
    ekler: '',
    ilgili_evrak_id: '',
    plaka: '',
    ceza_tutari: '',
    tutar: '',
    imza_kullanici_ids: [],
    silinen_ek_ids: [],
  }

  isFormOpen.value = true
}

// Evrak Düzenle Formunu Aç
async function openEditForm(doc) {
  if (doc.evrak_tipi === 'giden') {
    isDetailOpen.value = false
    router.push({ path: '/giden-evrak', query: { id: doc.id } })
    return
  }

  await loadMeta()
  isEditing.value = true
  formError.value = ''
  selectedFiles.value = []

  // Detay verisi çek
  try {
    const res = await evrakApi.detail(doc.id)
    const d = res.data || doc

    formData.value = {
      id: d.id,
      evrak_tipi: 'gelen',
      tarih: d.tarih || new Date().toISOString().slice(0, 10),
      evrak_no: d.evrak_no || '',
      konu: d.konu || '',
      kurum_adi: d.kurum_adi || '',
      muhatap_alt_birim: d.muhatap_alt_birim || '',
      muhatap_adres: d.muhatap_adres || '',
      personel_id: d.personel_id || '',
      ilgili_personel_id: d.ilgili_personel_id || '',
      cevap_verildi_mi: Boolean(d.cevap_verildi_mi),
      cevap_tarihi: d.cevap_tarihi || '',
      personel_bildirim_durumu: false,
      aciklama: d.aciklama || '',
      ust_yazi_gerekli_degil: false,
      ilgiler: d.ilgiler || '',
      ekler: d.ekler_metin || '',
      ilgili_evrak_id: d.ilgili_evrak_id || '',
      plaka: d.plaka || '',
      ceza_tutari: d.ceza_tutari ? String(d.ceza_tutari) : '',
      tutar: d.tutar ? String(d.tutar) : '',
      imza_kullanici_ids: [],
      silinen_ek_ids: [],
      existing_attachments: d.attachments || [],
    }

    isFormOpen.value = true
  } catch (err) {
    showToast(err.message || 'Evrak bilgileri alınamadı.', 'error')
  }
}

// Dosya Seçimi
function handleFileSelect(e) {
  const files = e.target.files
  if (!files || !files.length) return
  for (let i = 0; i < files.length; i++) {
    selectedFiles.value.push(files[i])
  }
  e.target.value = ''
}

function removeSelectedFile(index) {
  selectedFiles.value.splice(index, 1)
}

function removeExistingAttachment(attId) {
  if (!formData.value.silinen_ek_ids.includes(attId)) {
    formData.value.silinen_ek_ids.push(attId)
  }
  if (formData.value.existing_attachments) {
    formData.value.existing_attachments = formData.value.existing_attachments.filter(
      a => a.id !== attId
    )
  }
}

// Plaka Değiştiğinde Zimmet Sorgulama
async function onPlakaChange() {
  const plaka = formData.value.plaka
  const tarih = formData.value.tarih
  formData.value.ilgili_personel_id = ''
  if (!plaka) return

  try {
    const res = await evrakApi.aracZimmet(plaka, tarih)
    // Kullanıcı sorgu sürerken başka bir plaka seçtiyse eski yanıtı uygulama.
    if (formData.value.plaka !== plaka) return

    if (res.assignment) {
      formData.value.ilgili_personel_id = res.assignment.personel_id
      showToast(`Zimmetli sürücü bulundu: ${res.assignment.adi_soyadi}`, 'info')
    } else {
      showToast('Seçilen tarihte araca zimmetli personel bulunamadı.', 'info')
    }
  } catch (e) {
    console.error('Zimmet sorgu hatası:', e)
  }
}

// Ceza Tutarı Değiştiğinde %25 Erken Ödeme İndirimi Hesaplama
function onCezaTutariInput(val) {
  const amount = parseFloat(val)
  if (!isNaN(amount) && amount > 0) {
    formData.value.tutar = (amount * 0.75).toFixed(2)
  } else {
    formData.value.tutar = ''
  }
}

// Form Kaydet
async function saveForm() {
  formError.value = ''
  if (!formData.value.konu.trim()) {
    formError.value = 'Lütfen evrak konusunu belirtiniz.'
    return
  }
  if (!formData.value.kurum_adi.trim()) {
    formError.value = 'Lütfen muhatap kurum/firma adını belirtiniz.'
    return
  }

  formSubmitting.value = true
  try {
    const fd = new FormData()
    fd.append('id', formData.value.id || '')
    fd.append('evrak_tipi', formData.value.evrak_tipi)
    fd.append('tarih', formData.value.tarih)
    fd.append('evrak_no', formData.value.evrak_no)
    fd.append('konu', formData.value.konu)
    fd.append('kurum_adi', formData.value.kurum_adi)
    fd.append('muhatap_alt_birim', formData.value.muhatap_alt_birim)
    fd.append('muhatap_adres', formData.value.muhatap_adres)
    fd.append('personel_id', formData.value.personel_id)
    fd.append('ilgili_personel_id', formData.value.ilgili_personel_id)
    fd.append('cevap_verildi_mi', formData.value.cevap_verildi_mi ? '1' : '0')
    fd.append('cevap_tarihi', formData.value.cevap_tarihi || '')
    fd.append('personel_bildirim_durumu', formData.value.personel_bildirim_durumu ? '1' : '0')
    fd.append('aciklama', formData.value.aciklama)
    fd.append('ust_yazi_gerekli_degil', formData.value.ust_yazi_gerekli_degil ? '1' : '0')
    fd.append('ilgiler', formData.value.ilgiler)
    fd.append('ekler', formData.value.ekler)
    fd.append('ilgili_evrak_id', formData.value.ilgili_evrak_id)
    fd.append('plaka', formData.value.plaka)
    fd.append('ceza_tutari', formData.value.ceza_tutari)
    fd.append('tutar', formData.value.tutar)
    fd.append('imza_kullanici_ids', JSON.stringify(formData.value.imza_kullanici_ids || []))
    fd.append('silinen_ek_ids', JSON.stringify(formData.value.silinen_ek_ids || []))

    // Eklenen dosyalar
    for (let i = 0; i < selectedFiles.value.length; i++) {
      fd.append('dosyalar[]', selectedFiles.value[i])
    }

    const res = await evrakApi.save(fd, auth.state.csrfToken)
    showToast(res.message || 'Evrak başarıyla kaydedildi.')
    isFormOpen.value = false
    loadList(true)
  } catch (err) {
    formError.value = err.message || 'Kaydetme sırasında bir hata oluştu.'
  } finally {
    formSubmitting.value = false
  }
}

// Dosya Önizleme Modalı
function openFilePreview(file) {
  const isPdf =
    String(file.mime || '').includes('pdf') ||
    String(file.name || '').toLowerCase().endsWith('.pdf') ||
    String(file.url || '').includes('action=pdf')

  const isImg =
    String(file.mime || '').startsWith('image/') ||
    /\.(jpg|jpeg|png|webp|gif)$/i.test(file.name || '')

  previewFile.value = {
    url: file.url,
    name: file.name || 'Evrak Dosyası',
    mime: file.mime || '',
    isPdf,
    isImage: isImg,
  }
  isPreviewOpen.value = true
}

// Giden Evrak PDF Önizleme
function openGidenEvrakPdf(doc) {
  openFilePreview({
    url: doc.pdf_url || evrakApi.getPdfUrl(doc.id),
    name: `${doc.evrak_no || 'Evrak'}_Resmi_Yazi.pdf`,
    mime: 'application/pdf',
  })
}

// E-İmza Onaya Sunma
async function triggerOnayaSun(doc) {
  if (!confirm(`"${doc.konu}" konulu evrak elektronik imza onayına sunulacak. Devam etmek istiyor musunuz?`)) {
    return
  }
  actionLoading.value = true
  try {
    const res = await evrakApi.eImzaOnayaSun(doc.id, auth.state.csrfToken)
    showToast(res.message || 'Evrak onaya sunuldu.')
    if (isDetailOpen.value) isDetailOpen.value = false
    loadList(false)
  } catch (err) {
    showToast(err.message || 'İşlem başarısız.', 'error')
  } finally {
    actionLoading.value = false
  }
}

// E-İmza Onayla
function promptImzala(doc) {
  activeDocId.value = doc.id
  isSignConfirmOpen.value = true
}

async function confirmImzala() {
  actionLoading.value = true
  try {
    const res = await evrakApi.eImzaOnayla(activeDocId.value, auth.state.csrfToken)
    showToast(res.message || 'Elektronik imza onayınız kaydedildi.')
    isSignConfirmOpen.value = false
    if (isDetailOpen.value) isDetailOpen.value = false
    loadList(false)
  } catch (err) {
    showToast(err.message || 'Onay verilemedi.', 'error')
  } finally {
    actionLoading.value = false
  }
}

// E-İmza İade Et
function promptIade(doc) {
  activeDocId.value = doc.id
  returnGerekce.value = ''
  isReturnConfirmOpen.value = true
}

async function confirmIade() {
  if (returnGerekce.value.trim().length < 5) {
    showToast('Lütfen en az 5 karakterlik gerekçe yazınız.', 'error')
    return
  }
  actionLoading.value = true
  try {
    const res = await evrakApi.eImzaIade(
      activeDocId.value,
      returnGerekce.value.trim(),
      auth.state.csrfToken
    )
    showToast(res.message || 'Evrak iade edildi.')
    isReturnConfirmOpen.value = false
    if (isDetailOpen.value) isDetailOpen.value = false
    loadList(false)
  } catch (err) {
    showToast(err.message || 'İade işlemi başarısız.', 'error')
  } finally {
    actionLoading.value = false
  }
}

// E-İmza Geri Al (Taslağa çek)
async function triggerGeriAl(doc) {
  if (!confirm('İmza süreci iptal edilecek ve evrak taslak durumuna dönecek. Emin misiniz?')) {
    return
  }
  actionLoading.value = true
  try {
    const res = await evrakApi.eImzaGeriAl(doc.id, auth.state.csrfToken)
    showToast(res.message || 'Evrak geri alındı.')
    if (isDetailOpen.value) isDetailOpen.value = false
    loadList(false)
  } catch (err) {
    showToast(err.message || 'Geri alma başarısız.', 'error')
  } finally {
    actionLoading.value = false
  }
}

// Evrak Sil
function promptDelete(doc) {
  activeDocId.value = doc.id
  isDeleteConfirmOpen.value = true
}

async function confirmDelete() {
  actionLoading.value = true
  try {
    const res = await evrakApi.delete(activeDocId.value, auth.state.csrfToken)
    showToast(res.message || 'Evrak silindi.')
    isDeleteConfirmOpen.value = false
    if (isDetailOpen.value) isDetailOpen.value = false
    loadList(false)
  } catch (err) {
    showToast(err.message || 'Silme işlemi başarısız.', 'error')
  } finally {
    actionLoading.value = false
  }
}

// İmza Kişisi Seçim Toggle
function toggleSigner(userId) {
  const current = formData.value.imza_kullanici_ids || []
  const idx = current.indexOf(userId)
  if (idx > -1) {
    current.splice(idx, 1)
  } else {
    if (current.length >= 3) {
      showToast('En fazla 3 imza yetkilisi seçebilirsiniz.', 'info')
      return
    }
    current.push(userId)
  }
  formData.value.imza_kullanici_ids = [...current]
}

onMounted(() => {
  loadList(true)
  loadMeta()
})
</script>

<template>
  <section class="ds-screen ds-evrak">
    <!-- Üst TopBar -->
    <TopBar
      eyebrow="DOKÜMAN & RESMÎ YAZI YÖNETİMİ"
      title="Evrak Takip"
      :subtitle="`${stats.toplam_evrak} kayıtlı evrak`"
      :search="false"
    >
      <template #actions>
        <button
          type="button"
          class="clay-circle-btn evrak-add-btn"
          aria-label="Yeni evrak ekle"
          @click="openCreateForm('gelen')"
        >
          <Plus :size="20" />
        </button>
      </template>
    </TopBar>

    <!-- Toast Bildirimi -->
    <Transition name="fade">
      <div v-if="toastMessage" class="evrak-toast" :class="`evrak-toast--${toastType}`">
        <CheckCircle2 v-if="toastType === 'success'" :size="18" />
        <AlertCircle v-else :size="18" />
        <span>{{ toastMessage }}</span>
      </div>
    </Transition>

    <!-- Acil E-İmza Bekleyen Bildirim Kartı (Eğer imzada evrak varsa) -->
    <div
      v-if="stats.imzamda_bekleyen > 0"
      class="evrak-urgent-alert"
      role="alert"
      @click="setFilter('imzam')"
    >
      <div class="evrak-urgent-alert__icon">
        <Clock :size="22" />
      </div>
      <div class="evrak-urgent-alert__content">
        <strong>İmzanızı Bekleyen Evrak Var</strong>
        <p>{{ stats.imzamda_bekleyen }} adet giden resmî yazı onay sıranızı bekliyor.</p>
      </div>
      <span class="evrak-urgent-alert__badge">İncele</span>
    </div>

    <!-- 4 Minimal Bento KPI Kartları -->
    <div class="bento-metrics-grid evrak-metrics-grid">
      <!-- Toplam Evrak -->
      <button
        type="button"
        class="bento-metric-card"
        :class="{ 'is-selected': activeFilter === 'all' }"
        @click="setFilter('all')"
      >
        <div class="bento-metric-icon bg-primary-soft text-primary">
          <FileText :size="20" />
        </div>
        <div class="bento-metric-data">
          <span class="bento-metric-val">{{ stats.toplam_evrak }}</span>
          <span class="bento-metric-label">Toplam Evrak</span>
        </div>
      </button>

      <!-- Gelen Evrak -->
      <button
        type="button"
        class="bento-metric-card"
        :class="{ 'is-selected': activeFilter === 'gelen' }"
        @click="setFilter('gelen')"
      >
        <div class="bento-metric-icon bg-emerald-soft text-emerald">
          <ArrowDownLeft :size="20" />
        </div>
        <div class="bento-metric-data">
          <span class="bento-metric-val text-emerald">{{ stats.gelen_evrak }}</span>
          <span class="bento-metric-label">Gelen Evrak</span>
        </div>
      </button>

      <!-- Giden Evrak -->
      <button
        type="button"
        class="bento-metric-card"
        :class="{ 'is-selected': activeFilter === 'giden' }"
        @click="setFilter('giden')"
      >
        <div class="bento-metric-icon bg-blue-soft text-blue">
          <ArrowUpRight :size="20" />
        </div>
        <div class="bento-metric-data">
          <span class="bento-metric-val text-blue">{{ stats.giden_evrak }}</span>
          <span class="bento-metric-label">Giden Evrak</span>
        </div>
      </button>

      <!-- Cevap Bekleyen -->
      <button
        type="button"
        class="bento-metric-card"
        :class="{ 'is-selected': activeFilter === 'bekleyen' }"
        @click="setFilter('bekleyen')"
      >
        <div class="bento-metric-icon bg-amber-soft text-amber">
          <Clock :size="20" />
        </div>
        <div class="bento-metric-data">
          <span class="bento-metric-val text-amber">{{ stats.cevap_bekleyen }}</span>
          <span class="bento-metric-label">Cevap Bekleyen</span>
        </div>
      </button>
    </div>

    <!-- Hızlı Arama & Filtre Hapları -->
    <div class="evrak-filter-section">
      <!-- Arama Kutusu -->
      <div class="evrak-search-wrap">
        <Search :size="18" class="evrak-search-icon" />
        <input
          type="search"
          v-model="searchQuery"
          placeholder="Evrak no, konu, kurum veya personel ara..."
          class="evrak-search-input"
          @input="handleSearch($event.target.value)"
        />
        <button
          v-if="searchQuery"
          type="button"
          class="evrak-search-clear"
          @click="handleSearch('')"
        >
          <X :size="16" />
        </button>
      </div>

      <!-- Kategori Hapları -->
      <div class="clay-tabs-wrap evrak-tabs">
        <button
          type="button"
          class="clay-filter-pill"
          :class="{ 'is-active': activeFilter === 'all' }"
          @click="setFilter('all')"
        >
          Tümü ({{ stats.toplam_evrak }})
        </button>
        <button
          type="button"
          class="clay-filter-pill"
          :class="{ 'is-active': activeFilter === 'gelen' }"
          @click="setFilter('gelen')"
        >
          Gelen ({{ stats.gelen_evrak }})
        </button>
        <button
          type="button"
          class="clay-filter-pill"
          :class="{ 'is-active': activeFilter === 'giden' }"
          @click="setFilter('giden')"
        >
          Giden ({{ stats.giden_evrak }})
        </button>
        <button
          type="button"
          class="clay-filter-pill"
          :class="{ 'is-active': activeFilter === 'bekleyen' }"
          @click="setFilter('bekleyen')"
        >
          Cevap Bekleyen
        </button>
        <button
          v-if="stats.imzamda_bekleyen > 0"
          type="button"
          class="clay-filter-pill clay-filter-pill--urgent"
          :class="{ 'is-active': activeFilter === 'imzam' }"
          @click="setFilter('imzam')"
        >
          İmzamda ({{ stats.imzamda_bekleyen }})
        </button>
      </div>
    </div>

    <!-- Liste İçeriği -->
    <div class="evrak-list-container">
      <!-- Yükleniyor İskeleti -->
      <div v-if="loading" class="clay-skeleton-grid">
        <div class="clay-skeleton-card clay-skeleton-card--md"></div>
        <div class="clay-skeleton-card clay-skeleton-card--md"></div>
        <div class="clay-skeleton-card clay-skeleton-card--md"></div>
      </div>

      <!-- Hata Durumu -->
      <div v-else-if="error" class="clay-state-card" role="alert">
        <RefreshCw :size="24" />
        <strong>Evraklar yüklenemedi</strong>
        <p>{{ error }}</p>
        <button type="button" class="clay-retry-btn" @click="loadList(true)">Tekrar Dene</button>
      </div>

      <!-- Kayıt Bulunamadı -->
      <div v-else-if="!items.length" class="clay-state-card">
        <FileSearch :size="32" class="text-muted" />
        <strong>Kayıt bulunamadı</strong>
        <p>Seçili filtre veya aramaya uygun evrak kaydı mevcut değil.</p>
        <button type="button" class="clay-retry-btn" @click="openCreateForm('gelen')">
          + Yeni Evrak Ekle
        </button>
      </div>

      <!-- Evrak Kartları -->
      <div v-else class="evrak-cards-list">
        <article
          v-for="item in items"
          :key="item.id"
          class="bento-card bento-card--white evrak-card"
          @click="openDetail(item)"
        >
          <!-- Kart Üst Barı -->
          <div class="evrak-card-head">
            <div class="evrak-card-type-group">
              <!-- Yön Rozeti -->
              <span
                class="evrak-type-badge"
                :class="item.evrak_tipi === 'gelen' ? 'evrak-type-badge--gelen' : 'evrak-type-badge--giden'"
              >
                <component
                  :is="item.evrak_tipi === 'gelen' ? ArrowDownLeft : ArrowUpRight"
                  :size="14"
                />
                {{ item.evrak_tipi === 'gelen' ? 'GELEN' : 'GİDEN' }}
              </span>

              <!-- Evrak No -->
              <span class="evrak-meta-pill">No: {{ item.evrak_no }}</span>
              <!-- Tarih -->
              <span class="evrak-meta-pill">{{ item.tarih_formatted }}</span>
            </div>

            <!-- Durum Rozeti -->
            <div class="evrak-status-group">
              <span v-if="item.sira_bende" class="evrak-status-badge evrak-status-badge--urgent">
                <Clock :size="12" /> İmzanızda
              </span>
              <span
                v-else-if="item.onay_durumu === 'onaylandi'"
                class="evrak-status-badge evrak-status-badge--signed"
              >
                <Check :size="12" /> E-İmzalı
              </span>
              <span
                v-else-if="item.onay_durumu === 'onay_bekliyor'"
                class="evrak-status-badge evrak-status-badge--pending"
              >
                Onayda {{ item.onaylanan }}/{{ item.onay_toplam }}
              </span>
              <span
                v-else-if="item.is_bekleyen"
                class="evrak-status-badge evrak-status-badge--reply-pending"
              >
                Cevap Bekliyor
              </span>
              <span v-else-if="item.onay_durumu === 'taslak' && item.evrak_tipi === 'giden'" class="evrak-status-badge evrak-status-badge--draft">
                Taslak
              </span>
            </div>
          </div>

          <!-- Konu Başlığı -->
          <h3 class="evrak-card-subject">
            {{ item.konu }}
          </h3>

          <!-- Kurum / Muhatap Bilgisi -->
          <div class="evrak-card-info-row">
            <Building2 :size="16" class="text-muted" />
            <span class="evrak-card-org">{{ item.kurum_adi }}</span>
            <span v-if="item.muhatap_alt_birim" class="evrak-card-subunit">
              / {{ item.muhatap_alt_birim }}
            </span>
          </div>

          <!-- Personel ve Ek Bilgi Satırı -->
          <div class="evrak-card-meta-row">
            <div v-if="item.personel_adi" class="evrak-person-chip">
              <User :size="13" />
              <span>{{ item.personel_adi }}</span>
            </div>
            <div v-if="item.ilgili_personel_adi" class="evrak-person-chip evrak-person-chip--related">
              <UserCheck :size="13" />
              <span>{{ item.ilgili_personel_adi }}</span>
            </div>
            <!-- Trafik Cezası ise Plaka & Tutar -->
            <div v-if="item.plaka" class="evrak-plate-chip">
              <Car :size="13" />
              <strong>{{ item.plaka }}</strong>
              <span v-if="item.tutar" class="evrak-fine-amount">{{ formatMoney(item.tutar) }}</span>
            </div>
          </div>

          <!-- Alt Çubuk (Ek Dosyalar & Aksiyon Butonları) -->
          <div class="evrak-card-footer" @click.stop>
            <div class="evrak-card-files">
              <button
                v-if="item.attachments_count > 0"
                type="button"
                class="evrak-file-badge"
                @click="openDetail(item)"
              >
                <Paperclip :size="14" />
                <span>{{ item.attachments_count }} Ek</span>
              </button>

              <button
                v-if="item.evrak_tipi === 'giden'"
                type="button"
                class="evrak-preview-pill"
                @click="openGidenEvrakPdf(item)"
              >
                <FileText :size="14" />
                <span>Önizle</span>
              </button>
            </div>

            <!-- Doğrudan Aksiyonlar -->
            <div class="evrak-card-actions">
              <!-- İmza Sırası Bendeyse -->
              <template v-if="item.sira_bende">
                <button
                  type="button"
                  class="evrak-action-btn evrak-action-btn--sign"
                  title="Elektronik İmzala"
                  @click="promptImzala(item)"
                >
                  <Check :size="15" />
                  <span>İmzala</span>
                </button>
                <button
                  type="button"
                  class="evrak-action-btn evrak-action-btn--return"
                  title="Düzeltilmek Üzere İade Et"
                  @click="promptIade(item)"
                >
                  <Undo2 :size="15" />
                </button>
              </template>

              <!-- Onaya Sunulabilir Taslaksa -->
              <button
                v-else-if="item.onaya_sunulabilir"
                type="button"
                class="evrak-action-btn evrak-action-btn--submit"
                @click="triggerOnayaSun(item)"
              >
                <Send :size="14" />
                <span>Onaya Sun</span>
              </button>

              <!-- Geri Alınabilir Durumdaysa -->
              <button
                v-else-if="item.geri_alinabilir"
                type="button"
                class="evrak-action-btn evrak-action-btn--revoke"
                title="Taslağa Geri Al"
                @click="triggerGeriAl(item)"
              >
                <RotateCcw :size="14" />
              </button>

              <!-- Düzenle (Kilitli Değilse) -->
              <button
                v-if="!item.kilitli"
                type="button"
                class="evrak-action-btn evrak-action-btn--edit"
                title="Düzenle"
                @click="openEditForm(item)"
              >
                <Pencil :size="14" />
              </button>

              <!-- Sil (Taslaksa) -->
              <button
                v-if="!item.kilitli"
                type="button"
                class="evrak-action-btn evrak-action-btn--delete"
                title="Sil"
                @click="promptDelete(item)"
              >
                <Trash2 :size="14" />
              </button>

              <!-- Detay Aç Butonu -->
              <button
                type="button"
                class="evrak-action-btn evrak-action-btn--detail"
                title="Detayları Gör"
                @click="openDetail(item)"
              >
                <ChevronRight :size="16" />
              </button>
            </div>
          </div>
        </article>

        <!-- Daha Fazla Yükle Butonu -->
        <div v-if="pagination.has_more" class="evrak-load-more-wrap">
          <button
            type="button"
            class="clay-retry-btn"
            :disabled="loadingMore"
            @click="loadMore"
          >
            <RefreshCw v-if="loadingMore" :size="16" class="animate-spin" />
            <span>{{ loadingMore ? 'Yükleniyor...' : 'Daha Fazla Göster' }}</span>
          </button>
        </div>
      </div>
    </div>

    <!-- FAB: Hızlı Ekleme Çift Buton Grubu -->
    <div class="evrak-fab-group">
      <!-- Gelen Evrak Ekle -->
      <button
        type="button"
        class="evrak-fab evrak-fab--gelen"
        title="Gelen Evrak Ekle"
        @click="openCreateForm('gelen')"
      >
        <ArrowDownLeft :size="22" />
        <span class="evrak-fab-tip">Gelen</span>
      </button>

      <!-- Giden Evrak Ekle -->
      <button
        type="button"
        class="evrak-fab evrak-fab--giden"
        title="Giden Evrak Ekle"
        @click="openCreateForm('giden')"
      >
        <ArrowUpRight :size="22" />
        <span class="evrak-fab-tip">Giden</span>
      </button>
    </div>

    <!-- ======================================================== -->
    <!-- DETAY ÇEKMECESİ (DETAIL BOTTOM SHEET)                    -->
    <!-- ======================================================== -->
    <Transition name="drawer">
      <div v-if="isDetailOpen" class="evrak-drawer-layer" @click.self="isDetailOpen = false">
        <div class="evrak-drawer-sheet">
          <!-- Başlık Çubuğu -->
          <div class="evrak-drawer-handle" @click="isDetailOpen = false"></div>
          <header class="evrak-drawer-header">
            <div class="evrak-drawer-title-group">
              <span
                class="evrak-type-badge"
                :class="selectedDetail?.evrak_tipi === 'gelen' ? 'evrak-type-badge--gelen' : 'evrak-type-badge--giden'"
              >
                {{ selectedDetail?.evrak_tipi === 'gelen' ? 'GELEN EVRAK' : 'GİDEN EVRAK' }}
              </span>
              <h2>{{ selectedDetail?.konu }}</h2>
              <span class="evrak-drawer-subtitle">
                No: {{ selectedDetail?.evrak_no }} • {{ selectedDetail?.tarih_formatted }}
              </span>
            </div>
            <button
              type="button"
              class="clay-circle-btn evrak-drawer-close"
              aria-label="Kapat"
              @click="isDetailOpen = false"
            >
              <X :size="18" />
            </button>
          </header>

          <!-- Çekmece İçeriği -->
          <div class="evrak-drawer-body">
            <!-- Yükleniyor -->
            <div v-if="detailLoading" class="clay-skeleton-grid">
              <div class="clay-skeleton-card clay-skeleton-card--sm"></div>
            </div>

            <template v-else-if="selectedDetail">
              <!-- İade Edildiyse Uyarı Kartı -->
              <div v-if="selectedDetail.iade_gerekcesi" class="evrak-returned-notice">
                <AlertCircle :size="20" />
                <div>
                  <strong>İade Gerekçesi ({{ selectedDetail.iade_eden }})</strong>
                  <p>{{ selectedDetail.iade_gerekcesi }}</p>
                  <small>{{ selectedDetail.iade_tarihi }}</small>
                </div>
              </div>

              <!-- Muhatap ve Kurum Bilgisi -->
              <div class="evrak-detail-section">
                <span class="evrak-section-label">MUHATAP & KURUM</span>
                <div class="evrak-detail-card">
                  <div class="evrak-detail-row">
                    <Building2 :size="18" class="text-muted" />
                    <div>
                      <strong>{{ selectedDetail.kurum_adi }}</strong>
                      <span v-if="selectedDetail.muhatap_alt_birim" class="d-block text-muted">
                        {{ selectedDetail.muhatap_alt_birim }}
                      </span>
                      <p v-if="selectedDetail.muhatap_adres" class="evrak-detail-address">
                        {{ selectedDetail.muhatap_adres }}
                      </p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Personel Zimmet Bilgisi -->
              <div class="evrak-detail-section">
                <span class="evrak-section-label">ATANAN PERSONEL</span>
                <div class="evrak-detail-grid-2">
                  <div class="evrak-detail-box">
                    <User :size="16" class="text-muted" />
                    <div>
                      <small>Ofis Zimmetli</small>
                      <strong>{{ selectedDetail.personel_adi || 'Seçilmedi' }}</strong>
                    </div>
                  </div>
                  <div class="evrak-detail-box">
                    <UserCheck :size="16" class="text-blue" />
                    <div>
                      <small>İlgili Personel</small>
                      <strong>{{ selectedDetail.ilgili_personel_adi || 'Seçilmedi' }}</strong>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Trafik Cezası Detayları (Varsa) -->
              <div v-if="selectedDetail.plaka || selectedDetail.ceza_tutari" class="evrak-detail-section">
                <span class="evrak-section-label">TRAFİK CEZASI BİLGİLERİ</span>
                <div class="evrak-detail-grid-2">
                  <div class="evrak-detail-box">
                    <Car :size="16" class="text-amber" />
                    <div>
                      <small>Araç Plakası</small>
                      <strong>{{ selectedDetail.plaka }}</strong>
                    </div>
                  </div>
                  <div class="evrak-detail-box">
                    <Clock :size="16" class="text-emerald" />
                    <div>
                      <small>Ceza / Kesilecek Tutar</small>
                      <strong>{{ formatMoney(selectedDetail.tutar || selectedDetail.ceza_tutari) }}</strong>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Açıklama / Resmî Metin -->
              <div v-if="selectedDetail.aciklama" class="evrak-detail-section">
                <span class="evrak-section-label">İÇERİK / AÇIKLAMA</span>
                <div class="evrak-detail-card evrak-content-box" v-html="selectedDetail.aciklama"></div>
              </div>

              <!-- Giden Evrak E-İmza Akış Stepper'ı -->
              <div
                v-if="selectedDetail.evrak_tipi === 'giden' && selectedDetail.approval_steps?.length"
                class="evrak-detail-section"
              >
                <div class="evrak-section-header-flex">
                  <span class="evrak-section-label">E-İMZA ONAY SÜRECİ</span>
                  <button
                    type="button"
                    class="evrak-preview-pill"
                    @click="openGidenEvrakPdf(selectedDetail)"
                  >
                    <FileText :size="13" />
                    <span>Resmî Yazı PDF</span>
                  </button>
                </div>

                <div class="evrak-stepper">
                  <div
                    v-for="(step, idx) in selectedDetail.approval_steps"
                    :key="step.sira"
                    class="evrak-stepper-item"
                    :class="{
                      'is-approved': step.durum === 'onaylandi',
                      'is-current': step.is_current,
                    }"
                  >
                    <div class="evrak-stepper-badge">
                      <Check v-if="step.durum === 'onaylandi'" :size="14" />
                      <Clock v-else-if="step.is_current" :size="14" />
                      <span v-else>{{ idx + 1 }}</span>
                    </div>
                    <div class="evrak-stepper-info">
                      <strong>{{ step.name }}</strong>
                      <span v-if="step.durum === 'onaylandi'" class="text-emerald">
                        Onaylandı ({{ step.onay_tarihi }})
                      </span>
                      <span v-else-if="step.is_current" class="text-amber">
                        Sırada bekliyor
                      </span>
                      <span v-else class="text-muted">Bekliyor</span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Ek Dosyalar Listesi -->
              <div class="evrak-detail-section">
                <span class="evrak-section-label">
                  EKLER ({{ selectedDetail.attachments?.length || 0 }})
                </span>
                <div v-if="!selectedDetail.attachments?.length" class="evrak-no-files">
                  Ek dosya yüklenmemiş.
                </div>
                <div v-else class="evrak-files-grid">
                  <div
                    v-for="file in selectedDetail.attachments"
                    :key="file.name"
                    class="evrak-file-tile"
                    @click="openFilePreview(file)"
                  >
                    <div class="evrak-file-icon">
                      <FileText v-if="file.mime.includes('pdf')" :size="20" class="text-coral" />
                      <Paperclip v-else :size="20" class="text-blue" />
                    </div>
                    <div class="evrak-file-meta">
                      <strong class="truncate">{{ file.name }}</strong>
                      <small>{{ formatFileSize(file.size) }}</small>
                    </div>
                    <a
                      :href="`${file.url}&download=1`"
                      class="clay-circle-btn evrak-file-dl"
                      download
                      @click.stop
                      title="İndir"
                    >
                      <Download :size="16" />
                    </a>
                  </div>
                </div>
              </div>
            </template>
          </div>

          <!-- Çekmece Alt Aksiyon Çubuğu -->
          <footer v-if="selectedDetail" class="evrak-drawer-footer">
            <template v-if="selectedDetail.sira_bende">
              <button
                type="button"
                class="evrak-btn evrak-btn--emerald"
                :disabled="actionLoading"
                @click="promptImzala(selectedDetail)"
              >
                <Check :size="18" />
                <span>Elektronik İmzala</span>
              </button>
              <button
                type="button"
                class="evrak-btn evrak-btn--amber"
                :disabled="actionLoading"
                @click="promptIade(selectedDetail)"
              >
                <Undo2 :size="18" />
                <span>İade Et</span>
              </button>
            </template>

            <template v-else-if="selectedDetail.onaya_sunulabilir">
              <button
                type="button"
                class="evrak-btn evrak-btn--primary"
                :disabled="actionLoading"
                @click="triggerOnayaSun(selectedDetail)"
              >
                <Send :size="18" />
                <span>E-İmza Onayına Sun</span>
              </button>
            </template>

            <template v-else-if="selectedDetail.geri_alinabilir">
              <button
                type="button"
                class="evrak-btn evrak-btn--secondary"
                :disabled="actionLoading"
                @click="triggerGeriAl(selectedDetail)"
              >
                <RotateCcw :size="18" />
                <span>Taslağa Geri Al</span>
              </button>
            </template>

            <!-- Giden Evrak İse Özel Sayfasına Yönlendir -->
            <button
              v-if="selectedDetail.evrak_tipi === 'giden'"
              type="button"
              class="evrak-btn evrak-btn--primary"
              @click="isDetailOpen = false; router.push({ path: '/giden-evrak', query: { id: selectedDetail.id } })"
            >
              <Pencil v-if="!selectedDetail.kilitli" :size="16" />
              <Eye v-else :size="16" />
              <span>{{ selectedDetail.kilitli ? 'Evrakı İncele' : 'Giden Evrakı Düzenle' }}</span>
            </button>

            <!-- Gelen Evrak İse Modalda Düzenle -->
            <button
              v-else-if="!selectedDetail.kilitli"
              type="button"
              class="evrak-btn evrak-btn--subtle"
              @click="openEditForm(selectedDetail); isDetailOpen = false"
            >
              <Pencil :size="16" />
              <span>Düzenle</span>
            </button>

            <button
              type="button"
              class="evrak-btn evrak-btn--outline"
              @click="isDetailOpen = false"
            >
              Kapat
            </button>
          </footer>
        </div>
      </div>
    </Transition>

    <!-- ======================================================== -->
    <!-- YENİ / DÜZENLEME FORMU ÇEKMECESİ (GELEN EVRAK)           -->
    <!-- ======================================================== -->
    <Transition name="drawer">
      <div v-if="isFormOpen" class="evrak-drawer-layer" @click.self="isFormOpen = false">
        <div class="evrak-drawer-sheet evrak-drawer-sheet--form">
          <div class="evrak-drawer-handle" @click="isFormOpen = false"></div>
          <header class="evrak-drawer-header">
            <div>
              <span class="clay-topbar-eyebrow">
                {{ isEditing ? 'GELEN EVRAKI GÜNCELLE' : 'YENİ GELEN BELGE' }}
              </span>
              <h2>{{ isEditing ? 'Gelen Evrak Düzenle' : 'Yeni Gelen Evrak Kaydı' }}</h2>
            </div>
            <button
              type="button"
              class="clay-circle-btn evrak-drawer-close"
              aria-label="Kapat"
              @click="isFormOpen = false"
            >
              <X :size="18" />
            </button>
          </header>

          <form class="evrak-form-body" @submit.prevent="saveForm">
            <!-- Hata Uyarısı -->
            <div v-if="formError" class="evrak-returned-notice">
              <AlertCircle :size="18" />
              <span>{{ formError }}</span>
            </div>

            <!-- Tarih & Evrak No -->
            <div class="evrak-form-grid-2">
              <div class="evrak-form-group">
                <label class="evrak-form-label">EVRAK TARİHİ *</label>
                <input
                  type="date"
                  v-model="formData.tarih"
                  class="evrak-input"
                  required
                  @change="onPlakaChange"
                />
              </div>
              <div class="evrak-form-group">
                <label class="evrak-form-label">EVRAK NO</label>
                <input
                  type="text"
                  v-model="formData.evrak_no"
                  class="evrak-input"
                  placeholder="Otomatik veya No"
                />
              </div>
            </div>

            <!-- Konu ve Hızlı Çipler -->
            <div class="evrak-form-group">
              <label class="evrak-form-label">EVRAK KONUSU *</label>
              <div class="evrak-preset-chips">
                <button
                  v-for="konu in presetKonular"
                  :key="konu"
                  type="button"
                  class="evrak-preset-chip"
                  :class="{ 'is-selected': formData.konu === konu }"
                  @click="formData.konu = konu"
                >
                  {{ konu }}
                </button>
              </div>
              <input
                type="text"
                v-model="formData.konu"
                class="evrak-input mt-2"
                placeholder="Evrak konusunu yazınız veya yukarıdan seçiniz..."
                required
              />
            </div>

            <!-- Kurum / Gönderen Adı -->
            <div class="evrak-form-group">
              <label class="evrak-form-label">GÖNDEREN KURUM / FİRMA ADI *</label>
              <input
                type="text"
                v-model="formData.kurum_adi"
                class="evrak-input"
                placeholder="Örn: Kadıköy 3. İcra Dairesi / TEDAŞ"
                required
              />
            </div>

            <!-- Atanan Personeller -->
            <div class="evrak-form-grid-2">
              <div class="evrak-form-group">
                <label class="evrak-form-label">ZİMMETLİ OFİS PERSONELİ</label>
                <select v-model="formData.personel_id" class="evrak-select">
                  <option value="">Seçiniz...</option>
                  <option
                    v-for="p in meta.ofis_personeller"
                    :key="p.id"
                    :value="p.id"
                  >
                    {{ p.name }}
                  </option>
                </select>
              </div>
              <div class="evrak-form-group">
                <label class="evrak-form-label">İLGİLİ PERSONEL</label>
                <select v-model="formData.ilgili_personel_id" class="evrak-select">
                  <option value="">Seçiniz...</option>
                  <option
                    v-for="p in meta.personeller"
                    :key="p.id"
                    :value="p.id"
                  >
                    {{ p.name }}
                  </option>
                </select>
              </div>
            </div>

            <!-- Gelen Evrak Ekstra: Cevap Verildi mi? -->
            <div v-if="formData.evrak_tipi === 'gelen'" class="evrak-toggle-card">
              <div class="evrak-toggle-card__text">
                <CheckCircle2 :size="18" class="text-emerald" />
                <div>
                  <strong>Cevap Verildi mi?</strong>
                  <small>Bu gelen evraka yanıt oluşturuldu mu?</small>
                </div>
              </div>
              <input
                type="checkbox"
                v-model="formData.cevap_verildi_mi"
                class="evrak-checkbox"
              />
            </div>
            <div
              v-if="formData.evrak_tipi === 'gelen' && formData.cevap_verildi_mi"
              class="evrak-form-group"
            >
              <label class="evrak-form-label">CEVAP TARİHİ</label>
              <input type="date" v-model="formData.cevap_tarihi" class="evrak-input" />
            </div>

            <!-- Trafik Cezası İse Araç & Tutar Bölümü -->
            <div
              v-if="formData.konu.toLowerCase().includes('trafik') || formData.konu.toLowerCase().includes('ceza')"
              class="evrak-traffic-section"
            >
              <div class="evrak-section-label text-amber">
                <Car :size="15" /> TRAFİK CEZASI DETAYLARI
              </div>
              <div class="evrak-form-group">
                <label class="evrak-form-label">ARAÇ PLAKASI</label>
                <select
                  v-model="formData.plaka"
                  class="evrak-select"
                  @change="onPlakaChange"
                >
                  <option value="">Plaka Seçiniz...</option>
                  <option
                    v-for="ar in meta.araclar"
                    :key="ar.plaka"
                    :value="ar.plaka"
                  >
                    {{ ar.plaka }} ({{ ar.marka_model }})
                  </option>
                </select>
              </div>

              <div class="evrak-form-grid-2">
                <div class="evrak-form-group">
                  <label class="evrak-form-label">CEZA TUTARI (TL)</label>
                  <input
                    type="number"
                    step="0.01"
                    v-model="formData.ceza_tutari"
                    class="evrak-input"
                    placeholder="0.00"
                    @input="onCezaTutariInput($event.target.value)"
                  />
                </div>
                <div class="evrak-form-group">
                  <label class="evrak-form-label">KESİLECEK TUTAR (%25 İNDİRİM)</label>
                  <input
                    type="number"
                    step="0.01"
                    v-model="formData.tutar"
                    class="evrak-input"
                    placeholder="0.00"
                  />
                </div>
              </div>
            </div>

            <!-- Açıklama / Notlar -->
            <div class="evrak-form-group">
              <label class="evrak-form-label">AÇIKLAMA / NOTLAR</label>
              <textarea
                v-model="formData.aciklama"
                rows="4"
                class="evrak-textarea"
                placeholder="Evrak ile ilgili açıklama veya notlar..."
              ></textarea>
            </div>

            <!-- Dosya Yükleme (Çoklu Dosya) -->
            <div class="evrak-form-group">
              <label class="evrak-form-label">EK DOSYALAR (PDF / GÖRSEL)</label>

              <!-- Mevcut Dosyalar (Düzenleme Modunda) -->
              <div
                v-if="formData.existing_attachments && formData.existing_attachments.length"
                class="evrak-existing-files-list mb-2"
              >
                <div
                  v-for="att in formData.existing_attachments"
                  :key="att.id"
                  class="evrak-file-chip"
                >
                  <Paperclip :size="13" />
                  <span class="truncate">{{ att.name }}</span>
                  <button
                    type="button"
                    class="evrak-chip-del"
                    @click="removeExistingAttachment(att.id)"
                  >
                    <X :size="13" />
                  </button>
                </div>
              </div>

              <!-- Yeni Seçilen Dosyalar -->
              <div v-if="selectedFiles.length" class="evrak-existing-files-list mb-2">
                <div
                  v-for="(f, i) in selectedFiles"
                  :key="i"
                  class="evrak-file-chip evrak-file-chip--new"
                >
                  <Plus :size="13" />
                  <span class="truncate">{{ f.name }}</span>
                  <button
                    type="button"
                    class="evrak-chip-del"
                    @click="removeSelectedFile(i)"
                  >
                    <X :size="13" />
                  </button>
                </div>
              </div>

              <!-- Dosya Ekleme Butonu -->
              <label class="evrak-upload-dropzone">
                <input
                  type="file"
                  multiple
                  accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx"
                  class="hidden"
                  @change="handleFileSelect"
                />
                <Paperclip :size="20" class="text-blue" />
                <span>Dosya Seç veya Dokunarak Yükle</span>
                <small>PDF, Görsel ve Ofis belgeleri (ISO 27001 şifrelenir)</small>
              </label>
            </div>

            <!-- Form Butonları -->
            <div class="evrak-form-footer">
              <button
                type="submit"
                class="evrak-btn evrak-btn--primary evrak-btn--lg"
                :disabled="formSubmitting"
              >
                <Check :size="18" />
                <span>{{ formSubmitting ? 'Kaydediliyor...' : (isEditing ? 'Değişiklikleri Kaydet' : 'Evrakı Kaydet') }}</span>
              </button>
              <button
                type="button"
                class="evrak-btn evrak-btn--outline"
                @click="isFormOpen = false"
              >
                İptal
              </button>
            </div>
          </form>
        </div>
      </div>
    </Transition>

    <!-- ======================================================== -->
    <!-- DOSYA ÖNİZLEME MODALI (FILE PREVIEW MODAL)               -->
    <!-- ======================================================== -->
    <Transition name="fade">
      <div v-if="isPreviewOpen" class="evrak-preview-modal" @click.self="isPreviewOpen = false">
        <div class="evrak-preview-container">
          <header class="evrak-preview-head">
            <h3 class="truncate">{{ previewFile.name }}</h3>
            <div class="evrak-preview-actions">
              <a
                :href="`${previewFile.url}&download=1`"
                class="clay-circle-btn"
                download
                title="İndir"
              >
                <Download :size="18" />
              </a>
              <button
                type="button"
                class="clay-circle-btn"
                aria-label="Kapat"
                @click="isPreviewOpen = false"
              >
                <X :size="18" />
              </button>
            </div>
          </header>

          <div class="evrak-preview-content">
            <img
              v-if="previewFile.isImage"
              :src="previewFile.url"
              :alt="previewFile.name"
              class="evrak-preview-img"
            />
            <iframe
              v-else-if="previewFile.isPdf"
              :src="previewFile.url"
              class="evrak-preview-iframe"
              title="PDF Önizleme"
            ></iframe>
            <div v-else class="evrak-preview-unsupported">
              <FileText :size="48" class="text-muted" />
              <strong>Bu dosya türü doğrudan önizlenemiyor.</strong>
              <p>Görüntülemek için cihazınıza indirebilirsiniz.</p>
              <a :href="`${previewFile.url}&download=1`" class="clay-retry-btn" download>
                <Download :size="16" /> İndir
              </a>
            </div>
          </div>
        </div>
      </div>
    </Transition>

    <!-- ======================================================== -->
    <!-- E-İMZA ONAYLA MODALI                                     -->
    <!-- ======================================================== -->
    <Transition name="fade">
      <div v-if="isSignConfirmOpen" class="evrak-dialog-layer">
        <div class="evrak-dialog-card">
          <div class="evrak-dialog-icon bg-emerald-soft text-emerald">
            <CheckCircle2 :size="28" />
          </div>
          <h3>E-İmza ile Onayla</h3>
          <p>
            Evrakı elektronik imza ile onaylıyorsunuz.<br />
            Tüm imzacılar onayladığında evrak değiştirilemez biçimde kilitlenir.
          </p>
          <div class="evrak-dialog-actions">
            <button
              type="button"
              class="evrak-btn evrak-btn--emerald"
              :disabled="actionLoading"
              @click="confirmImzala"
            >
              {{ actionLoading ? 'İşleniyor...' : 'Evet, Onayla' }}
            </button>
            <button
              type="button"
              class="evrak-btn evrak-btn--outline"
              @click="isSignConfirmOpen = false"
            >
              Vazgeç
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- ======================================================== -->
    <!-- E-İMZA İADE ET MODALI                                    -->
    <!-- ======================================================== -->
    <Transition name="fade">
      <div v-if="isReturnConfirmOpen" class="evrak-dialog-layer">
        <div class="evrak-dialog-card">
          <div class="evrak-dialog-icon bg-amber-soft text-amber">
            <Undo2 :size="28" />
          </div>
          <h3>Düzeltilmek Üzere İade Et</h3>
          <p>Evrak imzalanmadan taslağa döndürülecek ve gerekçe hazırlayana bildirilecektir.</p>

          <div class="w-100 text-left my-3">
            <label class="evrak-form-label">İADE GEREKÇESİ *</label>
            <textarea
              v-model="returnGerekce"
              rows="3"
              class="evrak-textarea"
              placeholder="İade gerekçenizi en az 5 karakter olacak şekilde yazınız..."
            ></textarea>
          </div>

          <div class="evrak-dialog-actions">
            <button
              type="button"
              class="evrak-btn evrak-btn--amber"
              :disabled="actionLoading"
              @click="confirmIade"
            >
              {{ actionLoading ? 'İşleniyor...' : 'İade Et' }}
            </button>
            <button
              type="button"
              class="evrak-btn evrak-btn--outline"
              @click="isReturnConfirmOpen = false"
            >
              Vazgeç
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- ======================================================== -->
    <!-- SİLME ONAY MODALI                                        -->
    <!-- ======================================================== -->
    <Transition name="fade">
      <div v-if="isDeleteConfirmOpen" class="evrak-dialog-layer">
        <div class="evrak-dialog-card">
          <div class="evrak-dialog-icon bg-coral-soft text-coral">
            <Trash2 :size="28" />
          </div>
          <h3>Evrakı Sil</h3>
          <p>Bu evrak kaydı kalıcı olarak silinecektir. Devam etmek istediğinize emin misiniz?</p>
          <div class="evrak-dialog-actions">
            <button
              type="button"
              class="evrak-btn evrak-btn--danger"
              :disabled="actionLoading"
              @click="confirmDelete"
            >
              {{ actionLoading ? 'Siliniyor...' : 'Evet, Sil' }}
            </button>
            <button
              type="button"
              class="evrak-btn evrak-btn--outline"
              @click="isDeleteConfirmOpen = false"
            >
              Vazgeç
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </section>
</template>

<style scoped>
/* ==========================================================================
   EVRAK TAKİP BENTO & CLAYMORPHIC STİLLERİ
   ========================================================================== */

.ds-evrak {
  padding-bottom: calc(var(--ds-nav-clearance) + 50px);
}

.evrak-add-btn {
  background: var(--ds-primary) !important;
  color: #fff !important;
  border-color: var(--ds-primary) !important;
}

/* Toast Bildirimi */
.evrak-toast {
  position: fixed;
  top: calc(var(--ds-safe-top) + 16px);
  left: 20px;
  right: 20px;
  z-index: 99999;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 18px;
  border-radius: var(--ds-radius-tile);
  box-shadow: 0 12px 30px rgba(0, 0, 0, 0.18);
  font: 600 13px var(--ds-font-main);
  backdrop-filter: blur(8px);
}
.evrak-toast--success {
  background: #065f46;
  color: #ecfdf5;
}
.evrak-toast--error {
  background: #991b1b;
  color: #fef2f2;
}
.evrak-toast--info {
  background: #1e293b;
  color: #f8fafc;
}

/* Acil İmza Bildirim Kartı */
.evrak-urgent-alert {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 16px;
  margin-bottom: 16px;
  background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
  border: 1.5px solid #f59e0b;
  border-radius: var(--ds-radius-tile);
  cursor: pointer;
  box-shadow: var(--ds-shadow-tile);
  transition: transform 0.2s;
}
.evrak-urgent-alert:active {
  transform: scale(0.98);
}
.evrak-urgent-alert__icon {
  width: 40px;
  height: 40px;
  border-radius: 12px;
  background: #f59e0b;
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.evrak-urgent-alert__content {
  flex: 1;
  min-width: 0;
}
.evrak-urgent-alert__content strong {
  display: block;
  font-size: 14px;
  color: #92400e;
  font-weight: 800;
}
.evrak-urgent-alert__content p {
  margin: 2px 0 0;
  font-size: 12px;
  color: #b45309;
}
.evrak-urgent-alert__badge {
  padding: 6px 12px;
  background: #b45309;
  color: #fff;
  font-size: 11px;
  font-weight: 700;
  border-radius: var(--ds-radius-pill);
}

/* 4 Bento Metrik Izgarası */
.evrak-metrics-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
  margin-bottom: 18px;
}
.bento-metric-card {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px;
  background: var(--ds-surface);
  border: 1px solid var(--ds-border-subtle);
  border-radius: var(--ds-radius-tile);
  box-shadow: var(--ds-shadow-tile);
  cursor: pointer;
  text-align: left;
  transition: all 0.2s ease;
}
.bento-metric-card.is-selected {
  border-color: var(--ds-primary);
  box-shadow: 0 0 0 2px var(--ds-primary);
}
.bento-metric-icon {
  width: 42px;
  height: 42px;
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.bento-metric-data {
  min-width: 0;
}
.bento-metric-val {
  display: block;
  font-size: 20px;
  font-weight: 800;
  line-height: 1.1;
  color: var(--ds-ink);
}
.bento-metric-label {
  display: block;
  font-size: 11px;
  font-weight: 600;
  color: var(--ds-muted);
  margin-top: 3px;
}

/* Renk Kutuları */
.bg-primary-soft { background: #f1f5f9; }
.bg-emerald-soft { background: #ecfdf5; }
.bg-blue-soft { background: #eff6ff; }
.bg-amber-soft { background: #fffbeb; }
.bg-coral-soft { background: #fff1f2; }
.text-emerald { color: #059669; }
.text-blue { color: #2563eb; }
.text-amber { color: #d97706; }
.text-coral { color: #e11d48; }

/* Arama ve Filtre Bölümü */
.evrak-filter-section {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-bottom: 18px;
}
.evrak-search-wrap {
  position: relative;
  display: flex;
  align-items: center;
}
.evrak-search-icon {
  position: absolute;
  left: 14px;
  color: var(--ds-muted);
  pointer-events: none;
}
.evrak-search-input {
  width: 100%;
  height: 46px;
  padding: 0 40px 0 42px;
  border-radius: var(--ds-radius-tile);
  border: 1px solid var(--ds-border-subtle);
  background: var(--ds-surface);
  font: 500 14px var(--ds-font-main);
  color: var(--ds-ink);
  box-shadow: var(--ds-shadow-pill);
  outline: none;
  transition: border-color 0.2s;
}
.evrak-search-input:focus {
  border-color: #0284c7;
}
.evrak-search-clear {
  position: absolute;
  right: 12px;
  background: none;
  border: none;
  color: var(--ds-muted);
  cursor: pointer;
}
.evrak-tabs {
  overflow-x: auto;
  white-space: nowrap;
  gap: 8px;
  padding-bottom: 2px;
  -webkit-overflow-scrolling: touch;
}
.clay-filter-pill--urgent {
  background: #f59e0b !important;
  color: #fff !important;
  font-weight: 700 !important;
}

/* Evrak Kartı */
.evrak-cards-list {
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.evrak-card {
  padding: 16px;
  border-radius: var(--ds-radius-card);
  transition: transform 0.2s ease, box-shadow 0.2s ease;
  cursor: pointer;
}
.evrak-card:active {
  transform: scale(0.99);
}
.evrak-card-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 10px;
}
.evrak-card-type-group {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
}
.evrak-type-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 8px;
  border-radius: var(--ds-radius-sm);
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.3px;
}
.evrak-type-badge--gelen {
  background: #ecfdf5;
  color: #059669;
}
.evrak-type-badge--giden {
  background: #eff6ff;
  color: #2563eb;
}
.evrak-meta-pill {
  font-size: 11px;
  color: var(--ds-muted);
  font-weight: 600;
  background: var(--ds-surface-subtle);
  padding: 3px 8px;
  border-radius: var(--ds-radius-sm);
}

.evrak-status-group {
  flex-shrink: 0;
}
.evrak-status-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 9px;
  border-radius: var(--ds-radius-pill);
  font-size: 11px;
  font-weight: 700;
}
.evrak-status-badge--urgent {
  background: #f59e0b;
  color: #fff;
}
.evrak-status-badge--signed {
  background: #d1fae5;
  color: #065f46;
}
.evrak-status-badge--pending {
  background: #e0f2fe;
  color: #0369a1;
}
.evrak-status-badge--reply-pending {
  background: #fef3c7;
  color: #92400e;
}
.evrak-status-badge--draft {
  background: #f1f5f9;
  color: #64748b;
}

.evrak-card-subject {
  font-size: 15px;
  font-weight: 700;
  color: var(--ds-ink);
  line-height: 1.35;
  margin: 0 0 8px;
}

.evrak-card-info-row {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: var(--ds-ink);
  font-weight: 600;
  margin-bottom: 8px;
}
.evrak-card-org {
  color: #1e293b;
}
.evrak-card-subunit {
  font-size: 12px;
  color: var(--ds-muted);
  font-weight: 500;
}

.evrak-card-meta-row {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
  margin-bottom: 12px;
}
.evrak-person-chip {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 3px 8px;
  border-radius: var(--ds-radius-sm);
  background: var(--ds-surface-subtle);
  font-size: 11px;
  color: #334155;
  font-weight: 600;
}
.evrak-person-chip--related {
  background: #eff6ff;
  color: #1d4ed8;
}
.evrak-plate-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 3px 8px;
  border-radius: var(--ds-radius-sm);
  background: #fef3c7;
  color: #92400e;
  font-size: 11px;
}
.evrak-fine-amount {
  font-weight: 800;
  color: #b45309;
}

/* Kart Alt Barı */
.evrak-card-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-top: 1px solid var(--ds-border-subtle);
  padding-top: 10px;
  gap: 8px;
}
.evrak-card-files {
  display: flex;
  align-items: center;
  gap: 6px;
}
.evrak-file-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 10px;
  border-radius: var(--ds-radius-pill);
  background: #e0f2fe;
  color: #0284c7;
  font-size: 11px;
  font-weight: 700;
  border: none;
  cursor: pointer;
}
.evrak-preview-pill {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 10px;
  border-radius: var(--ds-radius-pill);
  background: #ecfdf5;
  color: #059669;
  font-size: 11px;
  font-weight: 700;
  border: none;
  cursor: pointer;
}

.evrak-card-actions {
  display: flex;
  align-items: center;
  gap: 6px;
}
.evrak-action-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
  height: 32px;
  padding: 0 10px;
  border-radius: var(--ds-radius-sm);
  font-size: 12px;
  font-weight: 700;
  border: none;
  cursor: pointer;
  transition: all 0.15s ease;
}
.evrak-action-btn:active {
  transform: scale(0.94);
}
.evrak-action-btn--sign {
  background: #10b981;
  color: #fff;
}
.evrak-action-btn--return {
  background: #fef3c7;
  color: #d97706;
  width: 32px;
  padding: 0;
}
.evrak-action-btn--submit {
  background: #0284c7;
  color: #fff;
}
.evrak-action-btn--revoke {
  background: #eff6ff;
  color: #0284c7;
  width: 32px;
  padding: 0;
}
.evrak-action-btn--edit {
  background: var(--ds-surface-subtle);
  color: #475569;
  width: 32px;
  padding: 0;
}
.evrak-action-btn--delete {
  background: #fef2f2;
  color: #dc2626;
  width: 32px;
  padding: 0;
}
.evrak-action-btn--detail {
  background: var(--ds-surface-subtle);
  color: var(--ds-muted);
  width: 32px;
  padding: 0;
}

/* FAB Çift Buton Grubu */
.evrak-fab-group {
  position: fixed;
  bottom: calc(var(--ds-nav-clearance) + 12px);
  right: 18px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  z-index: 80;
}
.evrak-fab {
  width: 50px;
  height: 50px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  border: none;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.22);
  cursor: pointer;
  position: relative;
  transition: transform 0.2s ease;
}
.evrak-fab:active {
  transform: scale(0.92);
}
.evrak-fab--gelen {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}
.evrak-fab--giden {
  background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
}
.evrak-fab-tip {
  position: absolute;
  right: 58px;
  background: #1e293b;
  color: #fff;
  font-size: 11px;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: var(--ds-radius-pill);
  white-space: nowrap;
  pointer-events: none;
  opacity: 0.9;
}

/* ======================================================== */
/* ÇEKMECE VE MODAL STİLLERİ (DRAWER & MODAL)               */
/* ======================================================== */
.evrak-drawer-layer {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.6);
  z-index: 1000;
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
  backdrop-filter: blur(4px);
}
.evrak-drawer-sheet {
  background: #fff;
  border-top-left-radius: 32px;
  border-top-right-radius: 32px;
  max-height: 90svh;
  display: flex;
  flex-direction: column;
  box-shadow: var(--ds-shadow-float);
  overflow: hidden;
}
.evrak-drawer-sheet--form {
  max-height: 94svh;
}
.evrak-drawer-handle {
  width: 44px;
  height: 5px;
  background: #cbd5e1;
  border-radius: 9999px;
  margin: 10px auto 4px;
  cursor: pointer;
}
.evrak-drawer-header {
  padding: 12px 20px 14px;
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  border-bottom: 1px solid var(--ds-border-subtle);
  background: #fff;
}
.evrak-drawer-title-group h2 {
  font-size: 17px;
  font-weight: 800;
  color: var(--ds-ink);
  margin: 4px 0 2px;
  line-height: 1.3;
}
.evrak-drawer-subtitle {
  font-size: 12px;
  color: var(--ds-muted);
}
.evrak-drawer-close {
  flex-shrink: 0;
}
.evrak-drawer-body {
  padding: 18px 20px;
  overflow-y: auto;
  overscroll-behavior: contain;
  display: flex;
  flex-direction: column;
  gap: 16px;
  background: #f8fafc;
}
.evrak-drawer-footer {
  padding: 12px 20px calc(12px + var(--ds-safe-bottom));
  background: #fff;
  border-top: 1px solid var(--ds-border-subtle);
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

/* Detay Bölümleri */
.evrak-detail-section {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.evrak-section-label {
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.5px;
  color: var(--ds-muted);
}
.evrak-section-header-flex {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.evrak-detail-card {
  background: #fff;
  border: 1px solid var(--ds-border-subtle);
  border-radius: var(--ds-radius-tile);
  padding: 12px 14px;
  box-shadow: var(--ds-shadow-pill);
}
.evrak-detail-row {
  display: flex;
  gap: 10px;
  align-items: flex-start;
  font-size: 13px;
}
.evrak-detail-address {
  margin: 4px 0 0;
  font-size: 11px;
  color: var(--ds-muted);
}
.evrak-detail-grid-2 {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 10px;
}
.evrak-detail-box {
  background: #fff;
  border: 1px solid var(--ds-border-subtle);
  border-radius: var(--ds-radius-tile);
  padding: 10px 12px;
  display: flex;
  gap: 8px;
  align-items: center;
}
.evrak-detail-box small {
  display: block;
  font-size: 10px;
  color: var(--ds-muted);
  font-weight: 600;
}
.evrak-detail-box strong {
  display: block;
  font-size: 13px;
  color: var(--ds-ink);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.evrak-content-box {
  font-size: 13px;
  line-height: 1.5;
  color: #334155;
  max-height: 220px;
  overflow-y: auto;
}

/* E-İmza Stepper */
.evrak-stepper {
  background: #fff;
  border: 1px solid var(--ds-border-subtle);
  border-radius: var(--ds-radius-tile);
  padding: 12px 14px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.evrak-stepper-item {
  display: flex;
  align-items: center;
  gap: 10px;
}
.evrak-stepper-badge {
  width: 26px;
  height: 26px;
  border-radius: 50%;
  background: #f1f5f9;
  color: var(--ds-muted);
  font-size: 11px;
  font-weight: 800;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.evrak-stepper-item.is-approved .evrak-stepper-badge {
  background: #10b981;
  color: #fff;
}
.evrak-stepper-item.is-current .evrak-stepper-badge {
  background: #f59e0b;
  color: #fff;
}
.evrak-stepper-info {
  flex: 1;
  font-size: 12px;
}
.evrak-stepper-info strong {
  display: block;
  color: var(--ds-ink);
}

/* Ek Dosyalar Izgarası */
.evrak-files-grid {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.evrak-file-tile {
  background: #fff;
  border: 1px solid var(--ds-border-subtle);
  border-radius: var(--ds-radius-tile);
  padding: 10px 14px;
  display: flex;
  align-items: center;
  gap: 12px;
  cursor: pointer;
  transition: background 0.15s;
}
.evrak-file-tile:active {
  background: #f8fafc;
}
.evrak-file-icon {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  background: var(--ds-surface-subtle);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.evrak-file-meta {
  flex: 1;
  min-width: 0;
}
.evrak-file-meta strong {
  display: block;
  font-size: 13px;
  color: var(--ds-ink);
}
.evrak-file-meta small {
  font-size: 11px;
  color: var(--ds-muted);
}
.evrak-file-dl {
  flex-shrink: 0;
  width: 34px !important;
  height: 34px !important;
}
.evrak-no-files {
  font-size: 12px;
  color: var(--ds-muted);
  font-style: italic;
  padding: 6px 0;
}

/* İade Uyarısı */
.evrak-returned-notice {
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #991b1b;
  padding: 12px 14px;
  border-radius: var(--ds-radius-tile);
  display: flex;
  gap: 10px;
  font-size: 13px;
}
.evrak-returned-notice strong {
  display: block;
  font-size: 13px;
  font-weight: 800;
}
.evrak-returned-notice p {
  margin: 3px 0;
  font-size: 12px;
}
.evrak-returned-notice small {
  font-size: 10px;
  color: #b91c1c;
}

/* Form Elemanları */
.evrak-form-body {
  padding: 18px 20px calc(24px + var(--ds-safe-bottom));
  overflow-y: auto;
  overscroll-behavior: contain;
  display: flex;
  flex-direction: column;
  gap: 16px;
  background: #f8fafc;
}
.evrak-form-group {
  display: flex;
  flex-direction: column;
  gap: 5px;
}
.evrak-form-grid-2 {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
}
.evrak-form-label {
  font-size: 11px;
  font-weight: 800;
  color: #475569;
  letter-spacing: 0.3px;
}
.evrak-input,
.evrak-select,
.evrak-textarea {
  width: 100%;
  padding: 12px 14px;
  background: #fff;
  border: 1px solid var(--ds-border-subtle);
  border-radius: var(--ds-radius-sm);
  font: 500 14px var(--ds-font-main);
  color: var(--ds-ink);
  outline: none;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
  transition: border-color 0.15s;
}
.evrak-input:focus,
.evrak-select:focus,
.evrak-textarea:focus {
  border-color: #0284c7;
}

/* Evrak Tipi Switch */
.evrak-type-switch {
  display: flex;
  background: #e2e8f0;
  padding: 4px;
  border-radius: var(--ds-radius-tile);
  gap: 4px;
}
.evrak-switch-opt {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 10px;
  border-radius: var(--ds-radius-sm);
  font-size: 12px;
  font-weight: 800;
  border: none;
  background: transparent;
  color: #475569;
  cursor: pointer;
  transition: all 0.2s;
}
.evrak-switch-opt.is-active {
  background: #fff;
  color: var(--ds-ink);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

/* Hızlı Konu Çipleri */
.evrak-preset-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-bottom: 4px;
}
.evrak-preset-chip {
  padding: 5px 10px;
  border-radius: var(--ds-radius-pill);
  font-size: 11px;
  font-weight: 700;
  border: 1px solid #cbd5e1;
  background: #fff;
  color: #334155;
  cursor: pointer;
}
.evrak-preset-chip.is-selected {
  background: #0284c7;
  color: #fff;
  border-color: #0284c7;
}

/* Toggle Switch Kartı */
.evrak-toggle-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: #fff;
  border: 1px solid var(--ds-border-subtle);
  border-radius: var(--ds-radius-tile);
  padding: 12px 14px;
}
.evrak-toggle-card__text {
  display: flex;
  align-items: center;
  gap: 10px;
}
.evrak-toggle-card__text strong {
  display: block;
  font-size: 13px;
  color: var(--ds-ink);
}
.evrak-toggle-card__text small {
  display: block;
  font-size: 11px;
  color: var(--ds-muted);
}
.evrak-checkbox {
  width: 20px;
  height: 20px;
  accent-color: #0284c7;
}

/* Trafik Bölümü */
.evrak-traffic-section {
  background: #fffbeb;
  border: 1px dashed #f59e0b;
  border-radius: var(--ds-radius-tile);
  padding: 14px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

/* İmzacılar Seçici */
.evrak-signers-selector {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.evrak-signer-pill {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border-radius: var(--ds-radius-sm);
  background: #fff;
  border: 1.5px solid var(--ds-border-subtle);
  font-size: 13px;
  font-weight: 600;
  color: #334155;
  cursor: pointer;
  text-align: left;
}
.evrak-signer-pill.is-selected {
  border-color: #0284c7;
  background: #f0f9ff;
  color: #0369a1;
}
.evrak-signer-order {
  width: 22px;
  height: 22px;
  border-radius: 50%;
  background: #e2e8f0;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 11px;
  font-weight: 800;
}
.evrak-signer-pill.is-selected .evrak-signer-order {
  background: #0284c7;
  color: #fff;
}

/* Dosya Yükleme Dropzone */
.evrak-upload-dropzone {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4px;
  padding: 16px;
  border: 2px dashed #cbd5e1;
  border-radius: var(--ds-radius-tile);
  background: #fff;
  cursor: pointer;
  text-align: center;
}
.evrak-upload-dropzone span {
  font-size: 13px;
  font-weight: 700;
  color: #0284c7;
}
.evrak-upload-dropzone small {
  font-size: 11px;
  color: var(--ds-muted);
}
.evrak-existing-files-list {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.evrak-file-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 10px;
  background: #f1f5f9;
  border-radius: var(--ds-radius-pill);
  font-size: 11px;
  font-weight: 600;
  color: #334155;
  max-width: 200px;
}
.evrak-file-chip--new {
  background: #ecfdf5;
  color: #065f46;
}
.evrak-chip-del {
  background: none;
  border: none;
  color: #94a3b8;
  cursor: pointer;
  display: flex;
  align-items: center;
}

/* Butonlar */
.evrak-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 10px 16px;
  border-radius: var(--ds-radius-sm);
  font: 700 13px var(--ds-font-main);
  border: none;
  cursor: pointer;
  transition: all 0.15s ease;
}
.evrak-btn:active {
  transform: scale(0.97);
}
.evrak-btn--lg {
  height: 48px;
  font-size: 14px;
}
.evrak-btn--primary {
  background: var(--ds-primary);
  color: #fff;
  flex: 1;
}
.evrak-btn--emerald {
  background: #10b981;
  color: #fff;
  flex: 1;
}
.evrak-btn--amber {
  background: #f59e0b;
  color: #fff;
  flex: 1;
}
.evrak-btn--danger {
  background: #ef4444;
  color: #fff;
  flex: 1;
}
.evrak-btn--secondary {
  background: #e0f2fe;
  color: #0369a1;
}
.evrak-btn--subtle {
  background: #f1f5f9;
  color: #475569;
}
.evrak-btn--outline {
  background: transparent;
  border: 1px solid #cbd5e1;
  color: #475569;
}
.evrak-form-footer {
  display: flex;
  gap: 10px;
  margin-top: 10px;
}

/* Önizleme Modal */
.evrak-preview-modal {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.85);
  z-index: 2000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
}
.evrak-preview-container {
  width: 100%;
  max-width: 650px;
  height: 85svh;
  background: #fff;
  border-radius: 24px;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}
.evrak-preview-head {
  padding: 12px 18px;
  background: #fff;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}
.evrak-preview-head h3 {
  font-size: 14px;
  font-weight: 700;
  margin: 0;
  color: var(--ds-ink);
}
.evrak-preview-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}
.evrak-preview-content {
  flex: 1;
  background: #0f172a;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}
.evrak-preview-img {
  max-width: 100%;
  max-height: 100%;
  object-fit: contain;
}
.evrak-preview-iframe {
  width: 100%;
  height: 100%;
  border: none;
  background: #fff;
}
.evrak-preview-unsupported {
  color: #fff;
  text-align: center;
  padding: 30px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
}
.evrak-preview-unsupported p {
  color: #94a3b8;
  font-size: 13px;
}

/* Diyaloglar */
.evrak-dialog-layer {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.65);
  z-index: 2100;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  backdrop-filter: blur(4px);
}
.evrak-dialog-card {
  width: 100%;
  max-width: 360px;
  background: #fff;
  border-radius: 28px;
  padding: 24px 20px 20px;
  text-align: center;
  box-shadow: var(--ds-shadow-float);
}
.evrak-dialog-icon {
  width: 52px;
  height: 52px;
  border-radius: 50%;
  margin: 0 auto 14px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.evrak-dialog-card h3 {
  font-size: 17px;
  font-weight: 800;
  color: var(--ds-ink);
  margin: 0 0 8px;
}
.evrak-dialog-card p {
  font-size: 13px;
  color: var(--ds-muted);
  line-height: 1.45;
  margin: 0 0 16px;
}
.evrak-dialog-actions {
  display: flex;
  gap: 10px;
}

/* Animasyonlar */
.drawer-enter-active,
.drawer-leave-active {
  transition: opacity 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
.drawer-enter-active .evrak-drawer-sheet,
.drawer-leave-active .evrak-drawer-sheet {
  transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
.drawer-enter-from,
.drawer-leave-to {
  opacity: 0;
}
.drawer-enter-from .evrak-drawer-sheet,
.drawer-leave-to .evrak-drawer-sheet {
  transform: translateY(100%);
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
