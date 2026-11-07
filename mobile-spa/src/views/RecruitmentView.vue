<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import {
  ArrowLeft,
  Briefcase,
  Calendar,
  CheckCircle2,
  ChevronRight,
  ChevronLeft,
  ChevronUp,
  ChevronDown,
  Clock,
  ExternalLink,
  FileText,
  GraduationCap,
  HelpCircle,
  History,
  Info,
  LoaderCircle,
  Mail,
  MapPin,
  MessageSquare,
  Phone,
  Plus,
  RefreshCw,
  Search,
  Share2,
  Sparkles,
  Star,
  Trash2,
  TrendingUp,
  UserCheck,
  UserPlus,
  Users,
  X,
  AlertCircle,
  Building2,
  Send,
  Eye,
  Edit,
} from '@lucide/vue'
import { auth } from '../services/auth.js'
import { recruitmentApi } from '../services/recruitment.js'

const router = useRouter()
const route = useRoute()

// Ana Tablar: 'applications' | 'jobs' | 'dashboard'
const activeMainTab = ref('applications')

// Başvurular Durumu
const applications = ref([])
const totalApplications = ref(0)
const loadingApps = ref(true)
const refreshingApps = ref(false)
const appStatus = ref('all')
const appSearch = ref('')
const appJobFilter = ref('')
const appPage = ref(1)
const hasMoreApps = ref(false)
let searchTimer = null

// Başvuru Özetleri
const appSummary = ref({
  toplam: 0,
  yeni: 0,
  degerlendirildi: 0,
  gorusme: 0,
  olumlu: 0,
  olumsuz: 0,
  personele_donustu: 0,
})

// İlanlar Durumu
const jobs = ref([])
const loadingJobs = ref(false)
const jobSummary = ref({ toplam: 0, yayinda: 0, taslak: 0, kapali: 0 })
const showJobModal = ref(false)
const savingJob = ref(false)
const loadingJobDetail = ref(false)
const jobStep = ref(1) // 1: İlan Bilgileri, 2: Aday Ekranı, 3: Yapay Zekâ & Sorular
const jobQuestions = ref([])
const newQuestionText = ref('')
const jobForm = ref({
  id: '',
  title: '',
  department: '',
  duty: '',
  location: '',
  description: '',
  requirements: '',
  ai_criteria: '',
  questions: '',
  status: 'yayinda',
  expires_at: '',
})

// Dashboard Durumu
const stats = ref(null)
const loadingStats = ref(false)

// Aktif Seçili Başvuru ve Detay Paneli
const selectedAppId = ref(null)
const activeApp = ref(null)
const loadingDetail = ref(false)
const activeDetailTab = ref('info') // 'info' | 'ai' | 'interviews' | 'decision' | 'timeline'
const ikNote = ref('')
const savingNote = ref(false)
const runningAi = ref(false)

// Mülakat Modal
const showInterviewModal = ref(false)
const interviewForm = ref({
  mulakat_id: '',
  mulakat_tarihi: '',
  tur: 'yuz_yuze',
  sonuc: 'beklemede',
  genel_puan: 5,
  genel_not: '',
})
const savingInterview = ref(false)

// Bildirim Modal
const showNotifyModal = ref(false)
const notifyForm = ref({
  channel: 'whatsapp',
  template: 'gorusme',
  subject: '',
  message: '',
})
const preparingNotify = ref(false)
const sendingNotify = ref(false)

// Personele Dönüştür Modal
const showConvertModal = ref(false)
const convertForm = ref({
  tc_kimlik_no: '',
  ise_giris_tarihi: new Date().toISOString().slice(0, 10),
})
const converting = ref(false)

// Toast Bildirimi
const toast = ref('')
let toastTimer = null
function showToast(msg) {
  toast.value = msg
  clearTimeout(toastTimer)
  toastTimer = setTimeout(() => { toast.value = '' }, 2800)
}

// Durum Sözlüğü
const STATUS_LABELS = {
  all: 'Tümü',
  yeni: 'Yeni',
  degerlendirildi: 'İncelendi',
  gorusme: 'Mülakat',
  olumlu: 'Olumlu',
  olumsuz: 'Olumsuz',
  personele_donustu: 'Personele Dönüştü',
}

const STATUS_BADGE_CLASSES = {
  yeni: 'badge-blue',
  degerlendirildi: 'badge-indigo',
  gorusme: 'badge-amber',
  olumlu: 'badge-emerald',
  olumsuz: 'badge-red',
  personele_donustu: 'badge-purple',
}

// Sekmeler
const statusTabs = computed(() => [
  { value: 'all', label: 'Tümü', count: appSummary.value.toplam },
  { value: 'yeni', label: 'Yeni', count: appSummary.value.yeni },
  { value: 'gorusme', label: 'Mülakat', count: appSummary.value.gorusme },
  { value: 'olumlu', label: 'Olumlu', count: appSummary.value.olumlu },
  { value: 'olumsuz', label: 'Olumsuz', count: appSummary.value.olumsuz },
  { value: 'personele_donustu', label: 'İşe Alındı', count: appSummary.value.personele_donustu },
])

// Yetkiler
const canInterview = computed(() => !!auth.state.permissions?.recruitment_interview)
const canNotify = computed(() => !!auth.state.permissions?.recruitment_notify)
const canConvert = computed(() => !!auth.state.permissions?.recruitment_convert)

// Başvuruları Yükle
async function loadApplications(reset = true, silent = false) {
  if (reset) {
    appPage.value = 1
    if (silent) refreshingApps.value = true
    else loadingApps.value = true
  }
  try {
    const res = await recruitmentApi.getApplications({
      status: appStatus.value,
      search: appSearch.value.trim(),
      job_id: appJobFilter.value,
      page: appPage.value,
      limit: 20,
    })
    if (reset) {
      applications.value = res.items || []
    } else {
      applications.value = [...applications.value, ...(res.items || [])]
    }
    totalApplications.value = res.total || 0
    hasMoreApps.value = !!res.has_more
    if (res.summary) appSummary.value = res.summary
  } catch (err) {
    showToast(err.message || 'Başvurular yüklenemedi.')
  } finally {
    loadingApps.value = false
    refreshingApps.value = false
  }
}

// Daha Fazla Başvuru Yükle
function loadMoreApps() {
  if (hasMoreApps.value && !loadingApps.value) {
    appPage.value++
    loadApplications(false)
  }
}

// İlanları Yükle
async function loadJobs() {
  loadingJobs.value = true
  try {
    const res = await recruitmentApi.getJobs()
    jobs.value = res.items || []
    if (res.summary) jobSummary.value = res.summary
  } catch (err) {
    showToast(err.message || 'İlanlar yüklenemedi.')
  } finally {
    loadingJobs.value = false
  }
}

// İstatistikleri Yükle
async function loadStats() {
  loadingStats.value = true
  try {
    const res = await recruitmentApi.getStats(30)
    stats.value = res.stats || null
    if (res.summary) appSummary.value = res.summary
  } catch (err) {
    showToast(err.message || 'İstatistikler yüklenemedi.')
  } finally {
    loadingStats.value = false
  }
}

// Başvuru Detayı Aç
async function openApplication(appId) {
  selectedAppId.value = appId
  loadingDetail.value = true
  activeDetailTab.value = 'info'
  activeApp.value = null
  try {
    const res = await recruitmentApi.getApplicationDetail(appId)
    activeApp.value = res.application
    ikNote.value = res.application.ik_note || ''
  } catch (err) {
    showToast(err.message || 'Başvuru detayı yüklenemedi.')
    selectedAppId.value = null
  } finally {
    loadingDetail.value = false
  }
}

function closeDetail() {
  selectedAppId.value = null
  activeApp.value = null
}

// Durum Güncelle
async function changeAppStatus(newStatus) {
  if (!activeApp.value || activeApp.value.status === newStatus) return
  try {
    await recruitmentApi.updateStatus(activeApp.value.id, newStatus)
    activeApp.value.status = newStatus
    activeApp.value.status_label = STATUS_LABELS[newStatus] || newStatus
    showToast('Başvuru durumu güncellendi.')
    loadApplications(true, true)
  } catch (err) {
    showToast(err.message || 'Durum güncellenemedi.')
  }
}

// İK Notu Kaydet
async function saveNote() {
  if (!activeApp.value || savingNote.value) return
  savingNote.value = true
  try {
    await recruitmentApi.saveNote(activeApp.value.id, ikNote.value)
    activeApp.value.ik_note = ikNote.value
    showToast('İK notu kaydedildi.')
  } catch (err) {
    showToast(err.message || 'Not kaydedilemedi.')
  } finally {
    savingNote.value = false
  }
}

// AI Yeniden Analiz
async function rerunAiAnalysis() {
  if (!activeApp.value || runningAi.value) return
  runningAi.value = true
  try {
    await recruitmentApi.rerunAi(activeApp.value.id)
    showToast('AI değerlendirmesi başarıyla yenilendi.')
    // Detayı yeniden yükle
    await openApplication(activeApp.value.id)
    loadApplications(true, true)
  } catch (err) {
    showToast(err.message || 'AI analizi gerçekleştirilemedi.')
  } finally {
    runningAi.value = false
  }
}

// Mülakat Formunu Aç
function openNewInterview() {
  const now = new Date()
  const localIso = new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().slice(0, 16)
  interviewForm.value = {
    mulakat_id: '',
    mulakat_tarihi: localIso,
    tur: 'yuz_yuze',
    sonuc: 'beklemede',
    genel_puan: 5,
    genel_not: '',
  }
  showInterviewModal.value = true
}

// Mülakat Kaydet
async function submitInterview() {
  if (!activeApp.value || savingInterview.value) return
  savingInterview.value = true
  try {
    await recruitmentApi.saveInterview({
      id: activeApp.value.id,
      mulakat_id: interviewForm.value.mulakat_id,
      mulakat_tarihi: interviewForm.value.mulakat_tarihi,
      tur: interviewForm.value.tur,
      sonuc: interviewForm.value.sonuc,
      genel_puan: interviewForm.value.genel_puan,
      genel_not: interviewForm.value.genel_not,
    })
    showToast('Mülakat kaydedildi.')
    showInterviewModal.value = false
    await openApplication(activeApp.value.id)
    loadApplications(true, true)
  } catch (err) {
    showToast(err.message || 'Mülakat kaydedilemedi.')
  } finally {
    savingInterview.value = false
  }
}

// Mülakat Sil
async function removeInterview(mulakatId) {
  if (!confirm('Bu mülakat kaydını silmek istediğinize emin misiniz?')) return
  try {
    await recruitmentApi.deleteInterview(mulakatId)
    showToast('Mülakat kaydı silindi.')
    await openApplication(activeApp.value.id)
  } catch (err) {
    showToast(err.message || 'Silinemedi.')
  }
}

// Bildirim Modalını Aç ve Şablonu Hazırla
async function openNotifyModal(template = 'gorusme') {
  if (!activeApp.value) return
  notifyForm.value.template = template
  notifyForm.value.channel = 'whatsapp'
  showNotifyModal.value = true
  preparingNotify.value = true
  try {
    const res = await recruitmentApi.prepareNotification(activeApp.value.id, template)
    notifyForm.value.subject = res.subject || ''
    notifyForm.value.message = res.message || ''
  } catch (err) {
    showToast(err.message || 'Şablon hazırlanamadı.')
  } finally {
    preparingNotify.value = false
  }
}

// Şablon Değişince
async function onTemplateChange() {
  if (!activeApp.value) return
  preparingNotify.value = true
  try {
    const res = await recruitmentApi.prepareNotification(activeApp.value.id, notifyForm.value.template)
    notifyForm.value.subject = res.subject || ''
    notifyForm.value.message = res.message || ''
  } catch (err) {
    showToast(err.message || 'Şablon hazırlanamadı.')
  } finally {
    preparingNotify.value = false
  }
}

// Bildirim Gönder
async function submitNotification() {
  if (!activeApp.value || sendingNotify.value) return
  sendingNotify.value = true
  try {
    const res = await recruitmentApi.sendNotification({
      id: activeApp.value.id,
      channel: notifyForm.value.channel,
      template: notifyForm.value.template,
      subject: notifyForm.value.subject,
      message: notifyForm.value.message,
    })
    showToast(res.message || 'Bildirim kaydedildi.')
    showNotifyModal.value = false

    // WhatsApp seçiliyse WhatsApp Web / Uygulamasını aç
    if (notifyForm.value.channel === 'whatsapp') {
      const cleanPhone = String(activeApp.value.phone || '').replace(/\D/g, '')
      const phoneParam = cleanPhone.startsWith('90') ? cleanPhone : '90' + cleanPhone.replace(/^0/, '')
      const waUrl = `https://wa.me/${phoneParam}?text=${encodeURIComponent(notifyForm.value.message)}`
      window.open(waUrl, '_blank')
    }

    await openApplication(activeApp.value.id)
  } catch (err) {
    showToast(err.message || 'Bildirim gönderilemedi.')
  } finally {
    sendingNotify.value = false
  }
}

// Personele Dönüştür Modalını Aç
function openConvertModal() {
  convertForm.value.tc_kimlik_no = ''
  convertForm.value.ise_giris_tarihi = new Date().toISOString().slice(0, 10)
  showConvertModal.value = true
}

// Personele Dönüştür Onayla
async function submitConversion() {
  if (!activeApp.value || converting.value) return
  if (!convertForm.value.tc_kimlik_no || convertForm.value.tc_kimlik_no.length !== 11) {
    showToast('Lütfen 11 haneli geçerli TC Kimlik No girin.')
    return
  }
  converting.value = true
  try {
    const res = await recruitmentApi.convertToPersonnel(
      activeApp.value.id,
      convertForm.value.tc_kimlik_no,
      convertForm.value.ise_giris_tarihi
    )
    showToast('Aday başarıyla personel olarak eklendi!')
    showConvertModal.value = false
    await openApplication(activeApp.value.id)
    loadApplications(true, true)
  } catch (err) {
    showToast(err.message || 'Personele dönüştürme başarısız.')
  } finally {
    converting.value = false
  }
}

// İlan Paylaş
async function shareJob(jobId) {
  try {
    const res = await recruitmentApi.getJobLink(jobId)
    if (navigator.share) {
      await navigator.share({
        title: 'İş İlanı',
        text: res.message,
        url: res.link,
      })
    } else {
      await navigator.clipboard.writeText(res.message)
      showToast('İlan başvuru metni panoya kopyalandı!')
    }
  } catch (err) {
    if (err.name !== 'AbortError') {
      showToast('Paylaşım bağlantısı alınamadı.')
    }
  }
}

// İlan Ekleme & Düzenleme
function openNewJobModal() {
  jobStep.value = 1
  jobQuestions.value = []
  newQuestionText.value = ''
  jobForm.value = {
    id: '',
    title: '',
    department: '',
    duty: '',
    location: '',
    description: '',
    requirements: '',
    ai_criteria: '',
    questions: '',
    status: 'yayinda',
    expires_at: '',
  }
  showJobModal.value = true
}

async function openEditJobModal(job) {
  showJobModal.value = true
  loadingJobDetail.value = true
  jobStep.value = 1
  newQuestionText.value = ''
  jobQuestions.value = []
  jobForm.value = {
    id: job.id,
    title: job.title || '',
    department: job.department || '',
    duty: job.duty || '',
    location: job.location || '',
    description: job.description || '',
    requirements: job.requirements || '',
    ai_criteria: (job.ai_criteria || '').replace(/undefined/g, '').trim(),
    questions: '',
    status: job.status || 'yayinda',
    expires_at: job.expires_at || '',
  }
  try {
    const res = await recruitmentApi.getJobDetail(job.id)
    if (res.job) {
      jobForm.value = {
        id: res.job.id,
        title: res.job.title,
        department: res.job.department,
        duty: res.job.duty,
        location: res.job.location,
        description: res.job.description,
        requirements: res.job.requirements,
        ai_criteria: (res.job.ai_criteria || '').replace(/undefined/g, '').trim(),
        questions: res.job.questions,
        status: res.job.status,
        expires_at: res.job.expires_at,
      }
      jobQuestions.value = (res.job.questions || '')
        .split('\n')
        .map(q => q.trim())
        .filter(Boolean)
    }
  } catch (err) {
    showToast(err.message || 'İlan detayları alınamadı.')
  } finally {
    loadingJobDetail.value = false
  }
}

// AI Kriter Çipi Ekle
function addAiCriteriaChip(prefix) {
  const current = jobForm.value.ai_criteria || ''
  jobForm.value.ai_criteria = current + (current ? '\n' : '') + prefix
}

// Soru Ekle
function addQuestion(text) {
  const q = String(text || newQuestionText.value || '').trim()
  if (!q) return
  if (jobQuestions.value.length >= 8) {
    showToast('En fazla 8 özel soru ekleyebilirsiniz.')
    return
  }
  if (!jobQuestions.value.includes(q)) {
    jobQuestions.value.push(q)
  }
  newQuestionText.value = ''
}

// Soru Sil
function removeQuestion(index) {
  jobQuestions.value.splice(index, 1)
}

// Soru Sırala
function moveQuestion(index, direction) {
  const newIndex = index + direction
  if (newIndex < 0 || newIndex >= jobQuestions.value.length) return
  const item = jobQuestions.value.splice(index, 1)[0]
  jobQuestions.value.splice(newIndex, 0, item)
}

async function submitJobForm() {
  if (!jobForm.value.title?.trim()) {
    showToast('Lütfen ilan başlığını girin.')
    jobStep.value = 1
    return
  }
  savingJob.value = true
  try {
    const postData = {
      ...jobForm.value,
      questions: jobQuestions.value.join('\n'),
    }
    const res = await recruitmentApi.saveJob(postData)
    showToast(res.message || 'İlan başarıyla kaydedildi.')
    showJobModal.value = false
    loadJobs()
  } catch (err) {
    showToast(err.message || 'İlan kaydedilemedi.')
  } finally {
    savingJob.value = false
  }
}

async function removeJob(jobId) {
  if (!confirm('Bu ilanı silmek istediğinize emin misiniz?')) return
  try {
    await recruitmentApi.deleteJob(jobId)
    showToast('İlan silindi.')
    showJobModal.value = false
    loadJobs()
  } catch (err) {
    showToast(err.message || 'İlan silinemedi.')
  }
}

// İlan Durumu Değiştir
async function toggleJobStatus(job) {
  const nextStatus = job.status === 'yayinda' ? 'kapali' : 'yayinda'
  try {
    await recruitmentApi.updateJobStatus(job.id, nextStatus)
    job.status = nextStatus
    job.status_label = nextStatus === 'yayinda' ? 'Yayında' : 'Kapalı'
    showToast(`İlan durumu "${job.status_label}" olarak güncellendi.`)
    loadJobs()
  } catch (err) {
    showToast(err.message || 'İlan durumu güncellenemedi.')
  }
}

// İsim Baş Harfleri
function getInitials(name) {
  return String(name || '?')
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map(p => p[0]?.toLocaleUpperCase('tr-TR'))
    .join('') || '?'
}

// Tarih Formatlama
function formatDate(val) {
  if (!val) return ''
  const d = new Date(String(val).replace(' ', 'T'))
  if (isNaN(d.getTime())) return val
  return d.toLocaleDateString('tr-TR', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

function formatDateTime(val) {
  if (!val) return ''
  const d = new Date(String(val).replace(' ', 'T'))
  if (isNaN(d.getTime())) return val
  return d.toLocaleString('tr-TR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

// Arama Timer
watch(appSearch, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    loadApplications(true)
  }, 350)
})

// Tab Değişimi
watch(activeMainTab, (tab) => {
  if (tab === 'applications') loadApplications(true, true)
  else if (tab === 'jobs') loadJobs()
  else if (tab === 'dashboard') loadStats()
})

onMounted(() => {
  loadApplications(true)
  loadJobs()
})
</script>

<template>
  <div class="recruitment-view ds-page">
    <!-- Üst Başlık Satırı -->
    <header class="ds-top-bar">
      <div class="ds-top-bar-left">
        <button class="clay-circle-btn back-btn" @click="router.back()" aria-label="Geri">
          <ArrowLeft :size="20" />
        </button>
        <div class="title-wrap">
          <h1 class="page-title">İşe Alım Yönetimi</h1>
          <p class="page-subtitle">Adaylar, Yapay Zekâ Analizleri ve İlanlar</p>
        </div>
      </div>
      <div class="ds-top-bar-right">
        <button
          class="clay-circle-btn refresh-btn"
          :class="{ 'is-spinning': refreshingApps || loadingJobs || loadingStats }"
          @click="activeMainTab === 'applications' ? loadApplications(true, true) : activeMainTab === 'jobs' ? loadJobs() : loadStats()"
          aria-label="Yenile"
        >
          <RefreshCw :size="18" />
        </button>
      </div>
    </header>

    <!-- Ana Mod Switcher (Segmented Clay Tabs) -->
    <div class="clay-tabs-wrap">
      <button
        class="clay-tab-item"
        :class="{ active: activeMainTab === 'applications' }"
        @click="activeMainTab = 'applications'"
      >
        <Users :size="16" />
        <span>Başvurular</span>
        <span v-if="appSummary.toplam" class="tab-badge">{{ appSummary.toplam }}</span>
      </button>

      <button
        class="clay-tab-item"
        :class="{ active: activeMainTab === 'jobs' }"
        @click="activeMainTab = 'jobs'"
      >
        <Briefcase :size="16" />
        <span>İlanlar</span>
        <span v-if="jobSummary.yayinda" class="tab-badge green">{{ jobSummary.yayinda }}</span>
      </button>

      <button
        class="clay-tab-item"
        :class="{ active: activeMainTab === 'dashboard' }"
        @click="activeMainTab = 'dashboard'"
      >
        <TrendingUp :size="16" />
        <span>Özet KPI</span>
      </button>
    </div>

    <!-- ========================================== -->
    <!-- SEKME 1: BAŞVURULAR (ADAYLAR)              -->
    <!-- ========================================== -->
    <main v-if="activeMainTab === 'applications'" class="tab-content">
      <!-- 4 Adet Minimal Bento KPI Kartı -->
      <section class="bento-kpi-grid">
        <div class="bento-kpi-card" @click="appStatus = 'all'; loadApplications(true)">
          <div class="kpi-icon-wrap blue"><Users :size="18" /></div>
          <div class="kpi-info">
            <span class="kpi-value">{{ appSummary.toplam }}</span>
            <span class="kpi-label">Toplam Başvuru</span>
          </div>
        </div>

        <div class="bento-kpi-card" @click="appStatus = 'yeni'; loadApplications(true)">
          <div class="kpi-icon-wrap amber"><Sparkles :size="18" /></div>
          <div class="kpi-info">
            <span class="kpi-value text-amber">{{ appSummary.yeni }}</span>
            <span class="kpi-label">Yeni İncelenecek</span>
          </div>
        </div>

        <div class="bento-kpi-card" @click="appStatus = 'gorusme'; loadApplications(true)">
          <div class="kpi-icon-wrap purple"><MessageSquare :size="18" /></div>
          <div class="kpi-info">
            <span class="kpi-value text-purple">{{ appSummary.gorusme }}</span>
            <span class="kpi-label">Mülakat / Süreçte</span>
          </div>
        </div>

        <div class="bento-kpi-card" @click="appStatus = 'olumlu'; loadApplications(true)">
          <div class="kpi-icon-wrap emerald"><CheckCircle2 :size="18" /></div>
          <div class="kpi-info">
            <span class="kpi-value text-emerald">{{ appSummary.olumlu + appSummary.personele_donustu }}</span>
            <span class="kpi-label">Olumlu / İşe Alınan</span>
          </div>
        </div>
      </section>

      <!-- Arama Çubuğu -->
      <div class="search-box-wrap">
        <div class="clay-search-box">
          <Search :size="18" class="search-icon" />
          <input
            v-model="appSearch"
            type="search"
            placeholder="Aday adı, telefon, şehir veya pozisyon..."
            aria-label="Aday ara"
          />
          <button v-if="appSearch" class="clear-search-btn" @click="appSearch = ''">
            <X :size="16" />
          </button>
        </div>
      </div>

      <!-- Durum Filtre Hapları (Scrollable Pills) -->
      <div class="status-pills-bar">
        <button
          v-for="st in statusTabs"
          :key="st.value"
          class="clay-filter-pill"
          :class="{ active: appStatus === st.value }"
          @click="appStatus = st.value; loadApplications(true)"
        >
          <span>{{ st.label }}</span>
          <span v-if="st.count" class="pill-count">{{ st.count }}</span>
        </button>
      </div>

      <!-- Aday Listesi -->
      <div v-if="loadingApps && !applications.length" class="loading-state">
        <LoaderCircle :size="32" class="is-spinning text-primary" />
        <p>Aday başvuruları getiriliyor...</p>
      </div>

      <div v-else-if="!applications.length" class="empty-state">
        <div class="empty-icon-wrap"><Users :size="40" /></div>
        <h3>Başvuru Bulunamadı</h3>
        <p>Seçili filtreye uygun aday başvurusu bulunmuyor.</p>
      </div>

      <div v-else class="candidates-list">
        <div
          v-for="app in applications"
          :key="app.id"
          class="bento-candidate-card"
          @click="openApplication(app.id)"
        >
          <div class="card-avatar-col">
            <img
              v-if="app.has_photo"
              :src="recruitmentApi.getPhotoUrl(app.id)"
              :alt="app.name"
              class="candidate-photo"
              @error="app.has_photo = false"
            />
            <div v-else class="candidate-initials">
              {{ getInitials(app.name) }}
            </div>
          </div>

          <div class="card-body-col">
            <div class="card-header-line">
              <h3 class="candidate-name">{{ app.name }}</h3>
              <!-- AI Skor Rozeti -->
              <div
                v-if="app.ai_score !== null"
                class="ai-score-pill"
                :class="{
                  'ai-high': app.ai_score >= 70,
                  'ai-mid': app.ai_score >= 45 && app.ai_score < 70,
                  'ai-low': app.ai_score < 45,
                }"
              >
                <Sparkles :size="12" />
                <span>%{{ app.ai_score }}</span>
              </div>
            </div>

            <p class="candidate-job">
              <Briefcase :size="13" />
              <span>{{ app.job_title || 'Genel Başvuru' }}</span>
            </p>

            <div class="candidate-meta-line">
              <span v-if="app.city" class="meta-tag">
                <MapPin :size="12" /> {{ app.city }}
              </span>
              <span v-if="app.experience_years" class="meta-tag">
                <Clock :size="12" /> {{ app.experience_years }} yıl
              </span>
              <span v-if="app.has_cv" class="meta-tag cv-tag">
                <FileText :size="12" /> CV
              </span>
            </div>

            <div class="card-footer-line">
              <span class="app-date">{{ formatDate(app.created_at) }}</span>
              <span class="status-badge" :class="STATUS_BADGE_CLASSES[app.status] || 'badge-slate'">
                {{ app.status_label }}
              </span>
            </div>
          </div>

          <div class="card-chevron-col">
            <ChevronRight :size="18" class="text-muted" />
          </div>
        </div>

        <!-- Daha Fazla Yükle Butonu -->
        <div v-if="hasMoreApps" class="load-more-wrap">
          <button class="clay-btn-secondary w-full" :disabled="loadingApps" @click="loadMoreApps">
            <LoaderCircle v-if="loadingApps" :size="16" class="is-spinning" />
            <span>Daha Fazla Göster</span>
          </button>
        </div>
      </div>
    </main>

    <!-- ========================================== -->
    <!-- SEKME 2: İLANLAR                           -->
    <!-- ========================================== -->
    <main v-if="activeMainTab === 'jobs'" class="tab-content">
      <div class="jobs-top-action-bar mb-3">
        <button class="clay-btn-primary w-full" @click="openNewJobModal">
          <Plus :size="16" />
          <span>Yeni İlan Oluştur</span>
        </button>
      </div>

      <div v-if="loadingJobs" class="loading-state">
        <LoaderCircle :size="32" class="is-spinning text-primary" />
        <p>İş ilanları listeleniyor...</p>
      </div>

      <div v-else-if="!jobs.length" class="empty-state">
        <div class="empty-icon-wrap"><Briefcase :size="40" /></div>
        <h3>Aktif İlan Yok</h3>
        <p>Sistemde kayıtlı iş ilanı bulunmuyor.</p>
      </div>

      <div v-else class="jobs-list">
        <div v-for="job in jobs" :key="job.id" class="bento-job-card">
          <div class="job-card-top">
            <div class="job-title-col" @click="openEditJobModal(job)" style="cursor: pointer;">
              <h3 class="job-title">{{ job.title }}</h3>
              <p class="job-dept">{{ job.department }} • {{ job.location || 'Merkez' }}</p>
            </div>
            <div class="job-status-col">
              <span
                class="status-badge"
                :class="job.status === 'yayinda' ? 'badge-emerald' : job.status === 'taslak' ? 'badge-amber' : 'badge-slate'"
              >
                {{ job.status_label || (job.status === 'yayinda' ? 'Yayında' : job.status === 'taslak' ? 'Taslak' : 'Kapalı') }}
              </span>
            </div>
          </div>

          <div class="job-card-stats" @click="openEditJobModal(job)" style="cursor: pointer;">
            <div class="job-stat-item">
              <span class="job-stat-val">{{ job.application_count }}</span>
              <span class="job-stat-lbl">Gelen Başvuru</span>
            </div>
            <div class="job-stat-item" v-if="job.expires_at">
              <span class="job-stat-val">{{ formatDate(job.expires_at) }}</span>
              <span class="job-stat-lbl">Son Tarih</span>
            </div>
          </div>

          <div class="job-card-actions">
            <button class="clay-btn-outline" @click="openEditJobModal(job)">
              <Edit :size="15" />
              <span>Düzenle</span>
            </button>
            <button class="clay-btn-outline" @click="shareJob(job.id)">
              <Share2 :size="15" />
              <span>Paylaş</span>
            </button>
            <button
              class="clay-btn-subtle"
              :class="job.status === 'yayinda' ? 'text-danger' : 'text-success'"
              @click="toggleJobStatus(job)"
            >
              <span>{{ job.status === 'yayinda' ? 'Yayından Kaldır' : 'Yayına Al' }}</span>
            </button>
          </div>
        </div>
      </div>
    </main>

    <!-- ========================================== -->
    <!-- SEKME 3: GENEL BAKIŞ & KPI (DASHBOARD)     -->
    <!-- ========================================== -->
    <main v-if="activeMainTab === 'dashboard'" class="tab-content">
      <div v-if="loadingStats" class="loading-state">
        <LoaderCircle :size="32" class="is-spinning text-primary" />
        <p>Metrikler hesaplanıyor...</p>
      </div>

      <div v-else-if="stats" class="dashboard-wrap">
        <div class="bento-card kpi-overview-card">
          <h3 class="bento-title">30 Günlük Başvuru Durumu</h3>
          <div class="stats-big-number">
            <span class="num">{{ stats.toplam_basvuru }}</span>
            <span class="unit">Toplam Başvuru</span>
          </div>

          <div class="segmented-track-wrap">
            <div class="segmented-track">
              <div
                class="seg-pill bg-blue"
                :style="{ width: ((stats.durum_dagilim?.yeni || 0) / (stats.toplam_basvuru || 1) * 100) + '%' }"
                title="Yeni"
              ></div>
              <div
                class="seg-pill bg-purple"
                :style="{ width: ((stats.durum_dagilim?.gorusme || 0) / (stats.toplam_basvuru || 1) * 100) + '%' }"
                title="Mülakat"
              ></div>
              <div
                class="seg-pill bg-emerald"
                :style="{ width: (((stats.durum_dagilim?.olumlu || 0) + (stats.durum_dagilim?.personele_donustu || 0)) / (stats.toplam_basvuru || 1) * 100) + '%' }"
                title="Olumlu"
              ></div>
              <div
                class="seg-pill bg-red"
                :style="{ width: ((stats.durum_dagilim?.olumsuz || 0) / (stats.toplam_basvuru || 1) * 100) + '%' }"
                title="Olumsuz"
              ></div>
            </div>
            <div class="track-legend">
              <span><i class="dot bg-blue"></i> Yeni ({{ stats.durum_dagilim?.yeni || 0 }})</span>
              <span><i class="dot bg-purple"></i> Mülakat ({{ stats.durum_dagilim?.gorusme || 0 }})</span>
              <span><i class="dot bg-emerald"></i> Olumlu ({{ (stats.durum_dagilim?.olumlu || 0) + (stats.durum_dagilim?.personele_donustu || 0) }})</span>
              <span><i class="dot bg-red"></i> Olumsuz ({{ stats.durum_dagilim?.olumsuz || 0 }})</span>
            </div>
          </div>
        </div>

        <!-- AI Analiz Özeti Bento Kartı -->
        <div class="bento-card ai-overview-card">
          <div class="bento-header-line">
            <div class="icon-title">
              <Sparkles :size="20" class="text-amber" />
              <h3>Yapay Zekâ Analizleri</h3>
            </div>
            <span class="ai-badge-highlight">AI Motoru Aktif</span>
          </div>

          <div class="ai-stats-row">
            <div class="ai-stat-box">
              <span class="ai-val">{{ stats.ai_ortalama_puan ? '%' + stats.ai_ortalama_puan : '-' }}</span>
              <span class="ai-lbl">Ortalama AI Uyum Skoru</span>
            </div>
            <div class="ai-stat-box">
              <span class="ai-val text-emerald">{{ stats.ai_olumlu_sayi || 0 }}</span>
              <span class="ai-lbl">AI Olumlu Önerilen</span>
            </div>
          </div>
        </div>
      </div>
    </main>

    <!-- ======================================================== -->
    <!-- ADAY DETAY BOTTOM SHEET / DRAWER                         -->
    <!-- ======================================================== -->
    <transition name="drawer">
      <div v-if="selectedAppId" class="drawer-overlay" @click.self="closeDetail">
        <div class="clay-drawer">
          <!-- Drawer Header -->
          <div class="drawer-header">
            <div class="drawer-user-info" v-if="activeApp">
              <div class="drawer-avatar">
                <img
                  v-if="activeApp.has_photo"
                  :src="recruitmentApi.getPhotoUrl(activeApp.id)"
                  :alt="activeApp.name"
                  class="drawer-photo"
                  @error="activeApp.has_photo = false"
                />
                <span v-else class="drawer-initials">{{ getInitials(activeApp.name) }}</span>
              </div>
              <div class="drawer-user-text">
                <h2 class="drawer-title">{{ activeApp.name }}</h2>
                <p class="drawer-sub">{{ activeApp.job_title }} • {{ activeApp.city || 'Belirtilmedi' }}</p>
              </div>
            </div>
            <button class="clay-circle-btn close-btn" @click="closeDetail" aria-label="Kapat">
              <X :size="20" />
            </button>
          </div>

          <div v-if="loadingDetail" class="drawer-loading">
            <LoaderCircle :size="36" class="is-spinning text-primary" />
            <p>Aday profili ve AI analizi getiriliyor...</p>
          </div>

          <div v-else-if="activeApp" class="drawer-content-scroll">
            <!-- Hızlı Aksiyon Barı (Call / WhatsApp / CV) -->
            <div class="quick-action-strip">
              <a
                v-if="activeApp.phone"
                :href="'tel:' + activeApp.phone"
                class="quick-action-btn btn-call"
              >
                <Phone :size="16" />
                <span>Ara</span>
              </a>
              <a
                v-if="activeApp.phone"
                :href="'https://wa.me/' + String(activeApp.phone).replace(/\D/g, '')"
                target="_blank"
                class="quick-action-btn btn-wa"
              >
                <MessageSquare :size="16" />
                <span>WhatsApp</span>
              </a>
              <a
                v-if="activeApp.has_cv"
                :href="recruitmentApi.getCvUrl(activeApp.id)"
                target="_blank"
                class="quick-action-btn btn-cv"
              >
                <FileText :size="16" />
                <span>CV Görüntüle</span>
              </a>
            </div>

            <!-- Detay Sekmeleri (Tab Switcher) -->
            <div class="clay-mini-tabs">
              <button
                class="mini-tab"
                :class="{ active: activeDetailTab === 'info' }"
                @click="activeDetailTab = 'info'"
              >
                Bilgiler
              </button>
              <button
                class="mini-tab"
                :class="{ active: activeDetailTab === 'ai' }"
                @click="activeDetailTab = 'ai'"
              >
                <Sparkles :size="13" />
                <span>AI Analizi</span>
              </button>
              <button
                class="mini-tab"
                :class="{ active: activeDetailTab === 'interviews' }"
                @click="activeDetailTab = 'interviews'"
              >
                <span>Mülakatlar</span>
                <span v-if="activeApp.interviews?.length" class="mini-badge">
                  {{ activeApp.interviews.length }}
                </span>
              </button>
              <button
                class="mini-tab"
                :class="{ active: activeDetailTab === 'decision' }"
                @click="activeDetailTab = 'decision'"
              >
                Karar & İşlem
              </button>
              <button
                class="mini-tab"
                :class="{ active: activeDetailTab === 'timeline' }"
                @click="activeDetailTab = 'timeline'"
              >
                Tarihçe
              </button>
            </div>

            <!-- ================= SEKME 1: BİLGİLER ================= -->
            <div v-if="activeDetailTab === 'info'" class="detail-section">
              <div class="bento-info-grid">
                <div class="info-cell">
                  <span class="cell-label">Telefon</span>
                  <span class="cell-value">{{ activeApp.phone || '-' }}</span>
                </div>
                <div class="info-cell">
                  <span class="cell-label">E-Posta</span>
                  <span class="cell-value">{{ activeApp.email || '-' }}</span>
                </div>
                <div class="info-cell">
                  <span class="cell-label">Eğitim</span>
                  <span class="cell-value">{{ activeApp.education || '-' }}</span>
                </div>
                <div class="info-cell">
                  <span class="cell-label">Deneyim</span>
                  <span class="cell-value">{{ activeApp.experience_years ? activeApp.experience_years + ' Yıl' : '-' }}</span>
                </div>
                <div class="info-cell">
                  <span class="cell-label">Ehliyet</span>
                  <span class="cell-value">{{ activeApp.driving_license || '-' }}</span>
                </div>
                <div class="info-cell">
                  <span class="cell-label">Maaş Beklentisi</span>
                  <span class="cell-value">{{ activeApp.salary_expectation ? activeApp.salary_expectation.toLocaleString('tr-TR') + ' ₺' : '-' }}</span>
                </div>
                <div class="info-cell">
                  <span class="cell-label">İşe Başlama</span>
                  <span class="cell-value">{{ activeApp.start_date_type || '-' }}</span>
                </div>
                <div class="info-cell">
                  <span class="cell-label">Doğum Tarihi</span>
                  <span class="cell-value">{{ formatDate(activeApp.birth_date) || '-' }}</span>
                </div>
              </div>

              <!-- Deneyim Özeti -->
              <div v-if="activeApp.experience_summary" class="bento-card mt-3">
                <h4 class="sub-title">Deneyim Özeti</h4>
                <p class="body-text">{{ activeApp.experience_summary }}</p>
              </div>

              <!-- Başvuru Formu Soruları & Cevapları -->
              <div v-if="activeApp.answers && Object.keys(activeApp.answers).length" class="bento-card mt-3">
                <h4 class="sub-title">İlan Özel Soruları & Cevaplar</h4>
                <div
                  v-for="(ans, q) in activeApp.answers"
                  :key="q"
                  class="qa-item"
                >
                  <p class="qa-q">{{ q }}</p>
                  <p class="qa-a">{{ ans }}</p>
                </div>
              </div>

              <!-- İK Notu -->
              <div class="bento-card mt-3">
                <h4 class="sub-title">İK Değerlendirme Notu</h4>
                <textarea
                  v-model="ikNote"
                  rows="3"
                  class="clay-textarea"
                  placeholder="Aday hakkında özel İK notları ekleyin..."
                ></textarea>
                <button
                  class="clay-btn-primary mt-2 w-full"
                  :disabled="savingNote"
                  @click="saveNote"
                >
                  <LoaderCircle v-if="savingNote" :size="16" class="is-spinning" />
                  <span>Notu Kaydet</span>
                </button>
              </div>
            </div>

            <!-- ================= SEKME 2: AI ANALİZİ ================= -->
            <div v-if="activeDetailTab === 'ai'" class="detail-section">
              <div class="ai-score-card bento-card">
                <div class="ai-score-ring-wrap">
                  <div
                    class="ai-score-circle"
                    :class="{
                      'score-high': activeApp.ai_score >= 70,
                      'score-mid': activeApp.ai_score >= 45 && activeApp.ai_score < 70,
                      'score-low': activeApp.ai_score < 45,
                    }"
                  >
                    <span class="score-number">{{ activeApp.ai_score !== null ? '%' + activeApp.ai_score : '-' }}</span>
                    <span class="score-caption">Uyum Skoru</span>
                  </div>
                </div>

                <div class="ai-score-desc">
                  <h4 class="ai-tag-title">{{ activeApp.ai_label || 'Yapay Zekâ Değerlendirmesi' }}</h4>
                  <p class="ai-date-text">Analiz Tarihi: {{ formatDateTime(activeApp.ai_date) || '-' }}</p>
                  <button
                    class="clay-btn-subtle rerun-ai-btn"
                    :disabled="runningAi"
                    @click="rerunAiAnalysis"
                  >
                    <RefreshCw :size="14" :class="{ 'is-spinning': runningAi }" />
                    <span>Yeniden Analiz Et</span>
                  </button>
                </div>
              </div>

              <!-- AI Yönetici Özeti -->
              <div v-if="activeApp.ai_summary" class="bento-card mt-3">
                <h4 class="sub-title"><Sparkles :size="15" class="text-amber" /> AI Yönetici Özeti</h4>
                <p class="body-text">{{ activeApp.ai_summary }}</p>
              </div>

              <!-- Güçlü Yönler -->
              <div v-if="activeApp.ai_strengths?.length" class="bento-card mt-3 border-emerald">
                <h4 class="sub-title text-emerald"><CheckCircle2 :size="16" /> Güçlü Yönler</h4>
                <ul class="point-list">
                  <li v-for="(item, idx) in activeApp.ai_strengths" :key="idx">{{ item }}</li>
                </ul>
              </div>

              <!-- Zayıf & Gelişime Açık Yönler -->
              <div v-if="activeApp.ai_weaknesses?.length" class="bento-card mt-3 border-amber">
                <h4 class="sub-title text-amber"><AlertCircle :size="16" /> Gelişime Açık Yönler</h4>
                <ul class="point-list">
                  <li v-for="(item, idx) in activeApp.ai_weaknesses" :key="idx">{{ item }}</li>
                </ul>
              </div>

              <!-- Riskler -->
              <div v-if="activeApp.ai_risks?.length" class="bento-card mt-3 border-red">
                <h4 class="sub-title text-red"><AlertCircle :size="16" /> Potansiyel Riskler</h4>
                <ul class="point-list">
                  <li v-for="(item, idx) in activeApp.ai_risks" :key="idx">{{ item }}</li>
                </ul>
              </div>

              <!-- Önerilen Mülakat Soruları -->
              <div v-if="activeApp.ai_interview_questions?.length" class="bento-card mt-3">
                <h4 class="sub-title"><HelpCircle :size="16" /> Önerilen Mülakat Soruları</h4>
                <ol class="num-list">
                  <li v-for="(q, idx) in activeApp.ai_interview_questions" :key="idx">{{ q }}</li>
                </ol>
              </div>
            </div>

            <!-- ================= SEKME 3: MÜLAKATLAR ================= -->
            <div v-if="activeDetailTab === 'interviews'" class="detail-section">
              <div class="section-action-header">
                <h4 class="sub-title">Aday Mülakatları</h4>
                <button
                  v-if="canInterview && activeApp.status !== 'personele_donustu'"
                  class="clay-btn-primary btn-sm"
                  @click="openNewInterview"
                >
                  <Plus :size="15" />
                  <span>Yeni Mülakat</span>
                </button>
              </div>

              <div v-if="!activeApp.interviews?.length" class="empty-sub-state">
                <MessageSquare :size="32" class="text-muted" />
                <p>Henüz mülakat kaydı girilmedi.</p>
              </div>

              <div v-else class="interview-list mt-3">
                <div
                  v-for="m in activeApp.interviews"
                  :key="m.id"
                  class="bento-card interview-card"
                >
                  <div class="interview-card-top">
                    <div class="interview-type">
                      <span class="badge-indigo">{{ m.tur_label }}</span>
                      <span class="interview-date">{{ formatDateTime(m.mulakat_tarihi) }}</span>
                    </div>
                    <span
                      class="status-badge"
                      :class="m.sonuc === 'olumlu' ? 'badge-emerald' : m.sonuc === 'olumsuz' ? 'badge-red' : 'badge-amber'"
                    >
                      {{ m.sonuc_label }}
                    </span>
                  </div>

                  <div v-if="m.genel_puan" class="interview-stars mt-2">
                    <Star
                      v-for="s in 5"
                      :key="s"
                      :size="15"
                      :class="s <= m.genel_puan ? 'text-amber fill-amber' : 'text-muted'"
                    />
                  </div>

                  <p v-if="m.genel_not" class="interview-notes mt-2">{{ m.genel_not }}</p>

                  <div class="interview-footer mt-2">
                    <span class="interviewer-name">Mülakatçı: {{ m.yapan || 'Yetkili' }}</span>
                    <button
                      v-if="canInterview"
                      class="delete-icon-btn"
                      @click="removeInterview(m.id)"
                      title="Sil"
                    >
                      <Trash2 :size="15" />
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <!-- ================= SEKME 4: KARAR & İŞLEM ================= -->
            <div v-if="activeDetailTab === 'decision'" class="detail-section">
              <!-- Durum Değiştir -->
              <div class="bento-card">
                <h4 class="sub-title">Başvuru Durumunu Değiştir</h4>
                <div class="status-change-grid mt-2">
                  <button
                    v-for="(lbl, stKey) in STATUS_LABELS"
                    :key="stKey"
                    v-show="stKey !== 'all' && stKey !== 'personele_donustu'"
                    class="clay-choice-btn"
                    :class="{ active: activeApp.status === stKey }"
                    @click="changeAppStatus(stKey)"
                  >
                    {{ lbl }}
                  </button>
                </div>
              </div>

              <!-- Adaya Karar Bildir (E-Posta / WhatsApp) -->
              <div v-if="canNotify" class="bento-card mt-3">
                <h4 class="sub-title">Adaya Bildirim Gönder</h4>
                <p class="body-text">Adaya e-posta veya WhatsApp ile mülakat daveti veya sonuç bildirin.</p>
                <div class="action-buttons-wrap mt-2">
                  <button class="clay-btn-secondary" @click="openNotifyModal('gorusme')">
                    <Calendar :size="15" />
                    <span>Mülakat Daveti</span>
                  </button>
                  <button class="clay-btn-secondary" @click="openNotifyModal('kabul')">
                    <CheckCircle2 :size="15" class="text-emerald" />
                    <span>Olumlu / Teklif</span>
                  </button>
                  <button class="clay-btn-secondary" @click="openNotifyModal('olumsuz')">
                    <X :size="15" class="text-red" />
                    <span>Olumsuz Karar</span>
                  </button>
                </div>
              </div>

              <!-- Personele Dönüştür -->
              <div v-if="canConvert" class="bento-card mt-3 border-purple">
                <h4 class="sub-title text-purple"><UserCheck :size="18" /> Personele Dönüştür</h4>
                <p class="body-text">
                  Adayın işe alımı tamamlandıysa sisteme resmî personel olarak kaydedin.
                </p>
                <div v-if="activeApp.status === 'personele_donustu'" class="converted-alert mt-2">
                  <CheckCircle2 :size="18" class="text-emerald" />
                  <span>Bu aday zaten personel kadrosuna aktarılmıştır.</span>
                </div>
                <button
                  v-else
                  class="clay-btn-primary mt-2 w-full bg-purple"
                  @click="openConvertModal"
                >
                  <UserPlus :size="16" />
                  <span>Personel Olarak Kaydet</span>
                </button>
              </div>
            </div>

            <!-- ================= SEKME 5: TARİHÇE ================= -->
            <div v-if="activeDetailTab === 'timeline'" class="detail-section">
              <h4 class="sub-title">Başvuru Süreç Günlüğü</h4>
              <div v-if="!activeApp.timeline?.length" class="empty-sub-state">
                <History :size="32" class="text-muted" />
                <p>Henüz bir olay kaydı bulunmuyor.</p>
              </div>
              <div v-else class="timeline-list mt-3">
                <div v-for="(ev, idx) in activeApp.timeline" :key="idx" class="timeline-item">
                  <div class="timeline-dot"></div>
                  <div class="timeline-body">
                    <h5 class="timeline-title">{{ ev.title }}</h5>
                    <p v-if="ev.description" class="timeline-desc">{{ ev.description }}</p>
                    <span class="timeline-meta">{{ ev.user }} • {{ formatDateTime(ev.date) }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </transition>

    <!-- ======================================================== -->
    <!-- MÜLAKAT KAYIT MODALI                                     -->
    <!-- ======================================================== -->
    <transition name="fade">
      <div v-if="showInterviewModal" class="modal-backdrop" @click.self="showInterviewModal = false">
        <div class="clay-modal">
          <div class="modal-header">
            <h3>Mülakat Kaydı Ekle</h3>
            <button class="close-btn" @click="showInterviewModal = false"><X :size="18" /></button>
          </div>
          <div class="modal-body">
            <div class="form-group">
              <label>Mülakat Tarihi ve Saati</label>
              <input v-model="interviewForm.mulakat_tarihi" type="datetime-local" class="clay-input" />
            </div>

            <div class="form-group">
              <label>Mülakat Türü</label>
              <select v-model="interviewForm.tur" class="clay-select">
                <option value="yuz_yuze">Yüz Yüze Görüşme</option>
                <option value="online">Online / Görüntülü</option>
                <option value="telefon">Telefon Görüşmesi</option>
              </select>
            </div>

            <div class="form-group">
              <label>Mülakat Sonucu</label>
              <select v-model="interviewForm.sonuc" class="clay-select">
                <option value="beklemede">Beklemede / Karar Verilmedi</option>
                <option value="olumlu">Olumlu</option>
                <option value="olumsuz">Olumsuz</option>
              </select>
            </div>

            <div class="form-group">
              <label>Genel Değerlendirme Puanı (1 - 5 Yıldız)</label>
              <div class="star-rating-picker">
                <Star
                  v-for="s in 5"
                  :key="s"
                  :size="26"
                  class="star-pick"
                  :class="s <= interviewForm.genel_puan ? 'text-amber fill-amber' : 'text-muted'"
                  @click="interviewForm.genel_puan = s"
                />
              </div>
            </div>

            <div class="form-group">
              <label>Mülakat Notları & Gözlemler</label>
              <textarea
                v-model="interviewForm.genel_not"
                rows="3"
                class="clay-textarea"
                placeholder="Adayın iletişimi, teknik bilgisi ve genel izlenim..."
              ></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button class="clay-btn-subtle" @click="showInterviewModal = false">Vazgeç</button>
            <button class="clay-btn-primary" :disabled="savingInterview" @click="submitInterview">
              <LoaderCircle v-if="savingInterview" :size="16" class="is-spinning" />
              <span>Kaydet</span>
            </button>
          </div>
        </div>
      </div>
    </transition>

    <!-- ======================================================== -->
    <!-- BİLDİRİM GÖNDERME MODALI                                 -->
    <!-- ======================================================== -->
    <transition name="fade">
      <div v-if="showNotifyModal" class="modal-backdrop" @click.self="showNotifyModal = false">
        <div class="clay-modal">
          <div class="modal-header">
            <h3>Adaya Bildirim Gönder</h3>
            <button class="close-btn" @click="showNotifyModal = false"><X :size="18" /></button>
          </div>
          <div class="modal-body">
            <div class="form-group">
              <label>Gönderim Kanalı</label>
              <div class="channel-toggle-row">
                <button
                  type="button"
                  class="channel-btn"
                  :class="{ active: notifyForm.channel === 'whatsapp' }"
                  @click="notifyForm.channel = 'whatsapp'"
                >
                  <MessageSquare :size="16" /> WhatsApp
                </button>
                <button
                  type="button"
                  class="channel-btn"
                  :class="{ active: notifyForm.channel === 'eposta' }"
                  @click="notifyForm.channel = 'eposta'"
                >
                  <Mail :size="16" /> E-Posta
                </button>
              </div>
            </div>

            <div class="form-group">
              <label>Bildirim Şablonu</label>
              <select v-model="notifyForm.template" class="clay-select" @change="onTemplateChange">
                <option value="gorusme">Mülakat / Görüşme Daveti</option>
                <option value="kabul">Olumlu Karar / Teklif</option>
                <option value="olumsuz">Olumsuz Sonuç Bildirimi</option>
                <option value="alindi">Başvuru Alındı Teyidi</option>
              </select>
            </div>

            <div v-if="notifyForm.channel === 'eposta'" class="form-group">
              <label>Konu</label>
              <input v-model="notifyForm.subject" type="text" class="clay-input" />
            </div>

            <div class="form-group">
              <label>Mesaj İçeriği</label>
              <textarea v-model="notifyForm.message" rows="5" class="clay-textarea"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button class="clay-btn-subtle" @click="showNotifyModal = false">İptal</button>
            <button class="clay-btn-primary" :disabled="sendingNotify || preparingNotify" @click="submitNotification">
              <LoaderCircle v-if="sendingNotify" :size="16" class="is-spinning" />
              <Send v-else :size="16" />
              <span>{{ notifyForm.channel === 'whatsapp' ? "WhatsApp'ta Aç" : 'E-Posta Gönder' }}</span>
            </button>
          </div>
        </div>
      </div>
    </transition>

    <!-- ======================================================== -->
    <!-- PERSONELE DÖNÜŞTÜR MODALI                                -->
    <!-- ======================================================== -->
    <transition name="fade">
      <div v-if="showConvertModal" class="modal-backdrop" @click.self="showConvertModal = false">
        <div class="clay-modal">
          <div class="modal-header">
            <h3>Personele Dönüştür</h3>
            <button class="close-btn" @click="showConvertModal = false"><X :size="18" /></button>
          </div>
          <div class="modal-body">
            <p class="body-text mb-3">
              <strong>{{ activeApp?.name }}</strong> adlı adayı personel kadrosuna eklemek için kimlik numarasını ve işe giriş tarihini doğrulayın.
            </p>

            <div class="form-group">
              <label>T.C. Kimlik No (11 Hane)</label>
              <input
                v-model="convertForm.tc_kimlik_no"
                type="tel"
                maxlength="11"
                class="clay-input"
                placeholder="11111111110"
              />
            </div>

            <div class="form-group">
              <label>İşe Giriş Tarihi</label>
              <input v-model="convertForm.ise_giris_tarihi" type="date" class="clay-input" />
            </div>
          </div>
          <div class="modal-footer">
            <button class="clay-btn-subtle" @click="showConvertModal = false">Vazgeç</button>
            <button class="clay-btn-primary bg-purple" :disabled="converting" @click="submitConversion">
              <LoaderCircle v-if="converting" :size="16" class="is-spinning" />
              <UserCheck v-else :size="16" />
              <span>Onayla ve Ekle</span>
            </button>
          </div>
        </div>
      </div>
    </transition>

    <!-- ======================================================== -->
    <!-- İLAN OLUŞTURMA & DÜZENLEME MODALI (3 ADIMLI)             -->
    <!-- ======================================================== -->
    <transition name="fade">
      <div v-if="showJobModal" class="modal-backdrop" @click.self="showJobModal = false">
        <div class="clay-modal ilan-wizard-modal">
          <div class="modal-header">
            <div class="modal-title-wrap">
              <h3>{{ jobForm.id ? 'İlanı Düzenle' : 'Yeni İlan Oluştur' }}</h3>
              <p class="modal-subtitle">Üç adımda ilanı hazırlayın ve yönetin.</p>
            </div>
            <button class="close-btn" @click="showJobModal = false"><X :size="18" /></button>
          </div>

          <!-- 3 Adım Sekmeleri -->
          <div class="wizard-steps-bar">
            <button
              type="button"
              class="wizard-step-btn"
              :class="{ active: jobStep === 1, completed: jobStep > 1 }"
              @click="jobStep = 1"
            >
              <span class="step-num">1</span>
              <span class="step-text">İlan Bilgisi</span>
            </button>
            <button
              type="button"
              class="wizard-step-btn"
              :class="{ active: jobStep === 2, completed: jobStep > 2 }"
              @click="jobStep = 2"
            >
              <span class="step-num">2</span>
              <span class="step-text">Aday Ekranı</span>
            </button>
            <button
              type="button"
              class="wizard-step-btn"
              :class="{ active: jobStep === 3 }"
              @click="jobStep = 3"
            >
              <span class="step-num">3</span>
              <span class="step-text">AI & Sorular</span>
            </button>
          </div>

          <div class="modal-body">
            <div v-if="loadingJobDetail" class="drawer-loading py-4">
              <LoaderCircle :size="32" class="is-spinning text-primary" />
              <p>İlan detayları getiriliyor...</p>
            </div>

            <div v-else>
              <!-- ADIM 1: İLAN BİLGİLERİ -->
              <div v-show="jobStep === 1" class="wizard-panel">
                <div class="form-group">
                  <label>İlan Başlığı <span class="text-danger">*</span></label>
                  <input
                    v-model="jobForm.title"
                    type="text"
                    class="clay-input"
                    placeholder="Örn: Endeks Okuma Memuru"
                  />
                </div>

                <div class="form-group">
                  <label>Durum</label>
                  <select v-model="jobForm.status" class="clay-select">
                    <option value="yayinda">Yayında (Başvuruya Açık)</option>
                    <option value="taslak">Taslak (Hazırlanıyor)</option>
                    <option value="kapali">Kapalı (Başvuruya Kapalı)</option>
                  </select>
                </div>

                <div class="form-row-2">
                  <div class="form-group">
                    <label>Departman</label>
                    <input
                      v-model="jobForm.department"
                      type="text"
                      class="clay-input"
                      placeholder="Departman"
                    />
                  </div>
                  <div class="form-group">
                    <label>Görev / Unvan</label>
                    <input
                      v-model="jobForm.duty"
                      type="text"
                      class="clay-input"
                      placeholder="Görev / Unvan"
                    />
                  </div>
                </div>

                <div class="form-group">
                  <label>Çalışma Yeri</label>
                  <input
                    v-model="jobForm.location"
                    type="text"
                    class="clay-input"
                    placeholder="Örn: Kahramanmaraş / Saha"
                  />
                </div>

                <div class="form-group">
                  <label>Son Başvuru Tarihi</label>
                  <input
                    v-model="jobForm.expires_at"
                    type="date"
                    class="clay-input"
                  />
                  <p class="field-hint">Boş bırakırsanız ilan süresiz açık kalır.</p>
                </div>
              </div>

              <!-- ADIM 2: ADAY EKRANI -->
              <div v-show="jobStep === 2" class="wizard-panel">
                <div class="form-group">
                  <label>İş Tanımı</label>
                  <textarea
                    v-model="jobForm.description"
                    rows="4"
                    class="clay-textarea"
                    placeholder="İş tanımı, çalışma koşulları, yan haklar..."
                  ></textarea>
                  <p class="field-hint">Başvuru sayfasının üstünde adaya gösterilir.</p>
                </div>

                <div class="form-group">
                  <label>Aranan Nitelikler</label>
                  <textarea
                    v-model="jobForm.requirements"
                    rows="4"
                    class="clay-textarea"
                    placeholder="Eğitim, deneyim, belge, ehliyet... (Satır satır yazmanız önerilir)"
                  ></textarea>
                  <p class="field-hint">Aday bu maddeleri okuyup kendini değerlendirir.</p>
                </div>
              </div>

              <!-- ADIM 3: YAPAY ZEKÂ & SORULAR -->
              <div v-show="jobStep === 3" class="wizard-panel">
                <div class="form-group">
                  <div class="label-with-badge">
                    <label>Yapay Zekâ Kriterleri</label>
                    <span class="badge-subtle-gray">Adaya gösterilmez</span>
                  </div>
                  <textarea
                    v-model="jobForm.ai_criteria"
                    rows="3"
                    class="clay-textarea"
                    placeholder="Zorunlu: B sınıfı ehliyet. Artı: 2 yıl saha deneyimi..."
                  ></textarea>

                  <!-- Hızlı Kriter Çipleri -->
                  <div class="chips-row mt-2">
                    <button type="button" class="kriter-chip" @click="addAiCriteriaChip('Zorunlu: ')">+ Zorunlu</button>
                    <button type="button" class="kriter-chip" @click="addAiCriteriaChip('Artı puan: ')">+ Artı puan</button>
                    <button type="button" class="kriter-chip" @click="addAiCriteriaChip('Elenme sebebi: ')">+ Elenme sebebi</button>
                    <button type="button" class="kriter-chip" @click="addAiCriteriaChip('Maaş aralığı: ')">+ Maaş aralığı</button>
                  </div>
                  <p class="field-hint mt-1">Puanlama bu kriterlere ve niteliklere göre yapılır; sonuç öneri niteliğindedir.</p>
                </div>

                <!-- Özel Başvuru Soruları -->
                <div class="form-group mt-3">
                  <div class="label-with-badge">
                    <label>Pozisyona Özel Sorular</label>
                    <span class="badge-pill-blue">{{ jobQuestions.length }} / 8</span>
                  </div>

                  <!-- Soru Listesi -->
                  <div v-if="!jobQuestions.length" class="empty-questions-box">
                    Henüz soru eklenmedi. Aşağıdan yazın veya hazır öneriyi seçin.
                  </div>
                  <div v-else class="questions-list-wrap">
                    <div
                      v-for="(q, qIdx) in jobQuestions"
                      :key="qIdx"
                      class="question-row-item"
                    >
                      <span class="q-num">{{ qIdx + 1 }}</span>
                      <span class="q-text">{{ q }}</span>
                      <div class="q-actions">
                        <button
                          type="button"
                          class="q-btn"
                          :disabled="qIdx === 0"
                          @click="moveQuestion(qIdx, -1)"
                          title="Yukarı taşı"
                        >
                          <ChevronUp :size="15" />
                        </button>
                        <button
                          type="button"
                          class="q-btn"
                          :disabled="qIdx === jobQuestions.length - 1"
                          @click="moveQuestion(qIdx, 1)"
                          title="Aşağı taşı"
                        >
                          <ChevronDown :size="15" />
                        </button>
                        <button
                          type="button"
                          class="q-btn text-danger"
                          @click="removeQuestion(qIdx)"
                          title="Sil"
                        >
                          <Trash2 :size="14" />
                        </button>
                      </div>
                    </div>
                  </div>

                  <!-- Hazır Soru Önerileri Çipleri -->
                  <div class="chips-row mt-2">
                    <button type="button" class="kriter-chip" @click="addQuestion('Daha önce hangi firmalarda çalıştınız?')">+ Önceki firmalar</button>
                    <button type="button" class="kriter-chip" @click="addQuestion('Hangi ehliyet sınıflarına sahipsiniz?')">+ Ehliyet sınıfı</button>
                    <button type="button" class="kriter-chip" @click="addQuestion('Ne zaman işe başlayabilirsiniz?')">+ İşe başlama</button>
                    <button type="button" class="kriter-chip" @click="addQuestion('Sahada vardiyalı / seyahatli çalışmaya uygun musunuz?')">+ Saha uygunluğu</button>
                  </div>

                  <!-- Yeni Soru Ekleme Inputu -->
                  <div class="add-question-input-row mt-2">
                    <input
                      v-model="newQuestionText"
                      type="text"
                      class="clay-input"
                      placeholder="Yeni soru yazın..."
                      @keyup.enter.prevent="addQuestion()"
                    />
                    <button
                      type="button"
                      class="clay-btn-secondary"
                      :disabled="!newQuestionText.trim() || jobQuestions.length >= 8"
                      @click="addQuestion()"
                    >
                      <Plus :size="15" /> Ekle
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Wizard Footer -->
          <div class="modal-footer wizard-modal-footer">
            <div class="footer-left">
              <button
                v-if="jobStep > 1"
                type="button"
                class="clay-btn-secondary"
                @click="jobStep--"
              >
                <ChevronLeft :size="16" /> Geri
              </button>
              <button
                v-else-if="jobForm.id"
                type="button"
                class="delete-icon-btn text-danger"
                @click="removeJob(jobForm.id)"
                title="İlanı Sil"
              >
                <Trash2 :size="18" />
              </button>
            </div>

            <div class="footer-right">
              <button
                v-if="jobStep < 3"
                type="button"
                class="clay-btn-secondary"
                @click="jobStep++"
              >
                İleri <ChevronRight :size="16" />
              </button>
              <button
                type="button"
                class="clay-btn-primary"
                :disabled="savingJob || loadingJobDetail"
                @click="submitJobForm"
              >
                <LoaderCircle v-if="savingJob" :size="16" class="is-spinning" />
                <span v-else>Kaydet</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </transition>

    <!-- Toast Bildirimi -->
    <transition name="fade">
      <div v-if="toast" class="ds-toast">
        {{ toast }}
      </div>
    </transition>
  </div>
</template>

<style scoped>
.recruitment-view {
  min-height: 100vh;
  padding: var(--ds-safe-top, 16px) 16px calc(var(--ds-safe-bottom, 20px) + 80px) 16px;
  background-color: var(--ds-canvas, #e6eaef);
  font-family: var(--ds-font-main, 'Roboto Condensed', sans-serif);
  color: #1e293b;
}

/* Üst Bar */
.ds-top-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 14px;
}

.ds-top-bar-left {
  display: flex;
  align-items: center;
  gap: 12px;
}

.title-wrap .page-title {
  margin: 0;
  font-size: 20px;
  font-weight: 700;
  color: #0f172a;
}

.title-wrap .page-subtitle {
  margin: 2px 0 0 0;
  font-size: 12px;
  color: #64748b;
}

.clay-circle-btn {
  width: 42px;
  height: 42px;
  border-radius: 50%;
  border: 1px solid rgba(255, 255, 255, 0.85);
  background: #ffffff;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
  display: flex;
  align-items: center;
  justify-content: center;
  color: #334155;
  cursor: pointer;
  transition: transform 0.15s ease;
}

.clay-circle-btn:active {
  transform: scale(0.95);
}

.is-spinning {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

/* Clay Tabs Switcher */
.clay-tabs-wrap {
  display: flex;
  gap: 6px;
  background: rgba(255, 255, 255, 0.7);
  padding: 5px;
  border-radius: 18px;
  border: 1px solid rgba(255, 255, 255, 0.9);
  box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.03);
  margin-bottom: 16px;
}

.clay-tab-item {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 10px 8px;
  border-radius: 14px;
  border: none;
  background: transparent;
  font-size: 13px;
  font-weight: 600;
  color: #64748b;
  cursor: pointer;
  transition: all 0.2s ease;
}

.clay-tab-item.active {
  background: #ffffff;
  color: #0f172a;
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.06);
}

.tab-badge {
  background: #e2e8f0;
  color: #334155;
  font-size: 11px;
  padding: 1px 6px;
  border-radius: 10px;
  font-weight: 700;
}

.tab-badge.green {
  background: #dcfce7;
  color: #15803d;
}

/* 4 KPI Grid */
.bento-kpi-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 10px;
  margin-bottom: 14px;
}

.bento-kpi-card {
  background: #ffffff;
  border-radius: 18px;
  padding: 12px 14px;
  display: flex;
  align-items: center;
  gap: 10px;
  border: 1px solid rgba(255, 255, 255, 0.9);
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.04);
  cursor: pointer;
}

.kpi-icon-wrap {
  width: 36px;
  height: 36px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.kpi-icon-wrap.blue { background: #eff6ff; color: #2563eb; }
.kpi-icon-wrap.amber { background: #fef3c7; color: #d97706; }
.kpi-icon-wrap.purple { background: #f3e8ff; color: #9333ea; }
.kpi-icon-wrap.emerald { background: #dcfce7; color: #16a34a; }

.kpi-info {
  display: flex;
  flex-direction: column;
}

.kpi-value {
  font-size: 17px;
  font-weight: 800;
  line-height: 1.2;
}

.kpi-label {
  font-size: 11px;
  color: #64748b;
  font-weight: 500;
}

/* Arama Kutusu */
.search-box-wrap {
  margin-bottom: 12px;
}

.clay-search-box {
  display: flex;
  align-items: center;
  gap: 8px;
  background: #ffffff;
  padding: 10px 14px;
  border-radius: 16px;
  border: 1px solid rgba(255, 255, 255, 0.9);
  box-shadow: 0 3px 10px rgba(0, 0, 0, 0.04);
}

.clay-search-box input {
  flex: 1;
  border: none;
  outline: none;
  font-size: 13px;
  font-family: inherit;
  color: #1e293b;
  background: transparent;
}

.clear-search-btn {
  background: none;
  border: none;
  color: #94a3b8;
  cursor: pointer;
  padding: 2px;
}

/* Status Pills */
.status-pills-bar {
  display: flex;
  gap: 6px;
  overflow-x: auto;
  padding-bottom: 8px;
  margin-bottom: 12px;
  scrollbar-width: none;
}

.status-pills-bar::-webkit-scrollbar {
  display: none;
}

.clay-filter-pill {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  gap: 5px;
  padding: 6px 12px;
  border-radius: 14px;
  border: 1px solid rgba(255, 255, 255, 0.85);
  background: #ffffff;
  font-size: 12px;
  font-weight: 600;
  color: #64748b;
  cursor: pointer;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.03);
}

.clay-filter-pill.active {
  background: #0f172a;
  color: #ffffff;
  border-color: #0f172a;
}

.pill-count {
  font-size: 10px;
  background: rgba(0, 0, 0, 0.08);
  padding: 1px 5px;
  border-radius: 8px;
}

.clay-filter-pill.active .pill-count {
  background: rgba(255, 255, 255, 0.25);
  color: #ffffff;
}

/* Aday Kartları */
.candidates-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.bento-candidate-card {
  background: #ffffff;
  border-radius: 20px;
  padding: 14px;
  display: flex;
  align-items: center;
  gap: 12px;
  border: 1px solid rgba(255, 255, 255, 0.9);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.04);
  cursor: pointer;
  transition: transform 0.15s ease;
}

.bento-candidate-card:active {
  transform: scale(0.98);
}

.card-avatar-col {
  width: 48px;
  height: 48px;
  flex-shrink: 0;
}

.candidate-photo {
  width: 48px;
  height: 48px;
  border-radius: 16px;
  object-fit: cover;
  border: 2px solid #e2e8f0;
}

.candidate-initials {
  width: 48px;
  height: 48px;
  border-radius: 16px;
  background: linear-gradient(135deg, #3b82f6, #1d4ed8);
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 16px;
}

.card-body-col {
  flex: 1;
  min-width: 0;
}

.card-header-line {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.candidate-name {
  margin: 0;
  font-size: 15px;
  font-weight: 700;
  color: #0f172a;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.ai-score-pill {
  display: flex;
  align-items: center;
  gap: 3px;
  padding: 2px 7px;
  border-radius: 10px;
  font-size: 11px;
  font-weight: 800;
  flex-shrink: 0;
}

.ai-score-pill.ai-high { background: #dcfce7; color: #15803d; }
.ai-score-pill.ai-mid { background: #fef3c7; color: #b45309; }
.ai-score-pill.ai-low { background: #fee2e2; color: #b91c1c; }

.candidate-job {
  margin: 3px 0 0 0;
  font-size: 12px;
  color: #475569;
  display: flex;
  align-items: center;
  gap: 5px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.candidate-meta-line {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 6px;
}

.meta-tag {
  display: flex;
  align-items: center;
  gap: 3px;
  font-size: 11px;
  color: #64748b;
  background: #f1f5f9;
  padding: 2px 6px;
  border-radius: 6px;
}

.meta-tag.cv-tag {
  background: #e0e7ff;
  color: #4338ca;
  font-weight: 600;
}

.card-footer-line {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 8px;
}

.app-date {
  font-size: 11px;
  color: #94a3b8;
}

/* Rozetler */
.status-badge {
  font-size: 11px;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 8px;
}

.badge-blue { background: #eff6ff; color: #2563eb; }
.badge-indigo { background: #e0e7ff; color: #4f46e5; }
.badge-amber { background: #fef3c7; color: #d97706; }
.badge-emerald { background: #dcfce7; color: #16a34a; }
.badge-red { background: #fee2e2; color: #dc2626; }
.badge-purple { background: #f3e8ff; color: #9333ea; }
.badge-slate { background: #f1f5f9; color: #475569; }

/* İlanlar Listesi */
.jobs-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.bento-job-card {
  background: #ffffff;
  border-radius: 20px;
  padding: 16px;
  border: 1px solid rgba(255, 255, 255, 0.9);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.04);
}

.job-card-top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 10px;
}

.job-title {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
  color: #0f172a;
}

.job-dept {
  margin: 3px 0 0 0;
  font-size: 12px;
  color: #64748b;
}

.job-card-stats {
  display: flex;
  gap: 16px;
  margin: 14px 0;
  padding: 10px 14px;
  background: #f8fafc;
  border-radius: 12px;
}

.job-stat-item {
  display: flex;
  flex-direction: column;
}

.job-stat-val {
  font-size: 16px;
  font-weight: 800;
  color: #0f172a;
}

.job-stat-lbl {
  font-size: 11px;
  color: #64748b;
}

.job-card-actions {
  display: flex;
  gap: 8px;
}

.clay-btn-outline {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 9px;
  border-radius: 12px;
  border: 1px solid #cbd5e1;
  background: #ffffff;
  font-size: 12px;
  font-weight: 600;
  color: #334155;
  cursor: pointer;
}

.clay-btn-subtle {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 9px 14px;
  border-radius: 12px;
  border: none;
  background: #f1f5f9;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
}

/* Dashboard */
.dashboard-wrap {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.bento-card {
  background: #ffffff;
  border-radius: 22px;
  padding: 16px;
  border: 1px solid rgba(255, 255, 255, 0.9);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.04);
}

.bento-title {
  margin: 0;
  font-size: 15px;
  font-weight: 700;
  color: #0f172a;
}

.stats-big-number {
  margin: 10px 0;
  display: flex;
  align-items: baseline;
  gap: 8px;
}

.stats-big-number .num {
  font-size: 36px;
  font-weight: 900;
  color: #0f172a;
}

.stats-big-number .unit {
  font-size: 13px;
  color: #64748b;
}

.segmented-track-wrap {
  margin-top: 10px;
}

.segmented-track {
  height: 10px;
  background: #f1f5f9;
  border-radius: 10px;
  overflow: hidden;
  display: flex;
}

.seg-pill { height: 100%; transition: width 0.3s ease; }
.bg-blue { background-color: #3b82f6; }
.bg-purple { background-color: #9333ea; }
.bg-emerald { background-color: #10b981; }
.bg-red { background-color: #ef4444; }

.track-legend {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: 10px;
  font-size: 11px;
  color: #64748b;
}

.track-legend .dot {
  display: inline-block;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  margin-right: 4px;
}

.ai-overview-card {
  background: linear-gradient(145deg, #1e293b, #0f172a);
  color: #ffffff;
}

.ai-overview-card .bento-header-line {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.ai-overview-card .icon-title {
  display: flex;
  align-items: center;
  gap: 8px;
}

.ai-overview-card h3 {
  margin: 0;
  font-size: 16px;
  color: #ffffff;
}

.ai-badge-highlight {
  font-size: 10px;
  background: rgba(251, 191, 36, 0.2);
  color: #fbbf24;
  padding: 3px 8px;
  border-radius: 10px;
  font-weight: 700;
}

.ai-stats-row {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
  margin-top: 16px;
}

.ai-stat-box {
  background: rgba(255, 255, 255, 0.06);
  padding: 12px;
  border-radius: 14px;
  display: flex;
  flex-direction: column;
}

.ai-stat-box .ai-val {
  font-size: 22px;
  font-weight: 900;
  color: #ffffff;
}

.ai-stat-box .ai-lbl {
  font-size: 11px;
  color: #94a3b8;
  margin-top: 2px;
}

/* ================= DRAWER (BOTTOM SHEET) ================= */
.drawer-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.45);
  backdrop-filter: blur(4px);
  z-index: 1000;
  display: flex;
  justify-content: center;
  align-items: flex-end;
}

.clay-drawer {
  width: 100%;
  max-width: 540px;
  height: 90vh;
  background: #ffffff;
  border-radius: 28px 28px 0 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  box-shadow: 0 -8px 30px rgba(0, 0, 0, 0.12);
}

.drawer-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  border-bottom: 1px solid #f1f5f9;
}

.drawer-user-info {
  display: flex;
  align-items: center;
  gap: 12px;
  min-width: 0;
}

.drawer-avatar {
  width: 44px;
  height: 44px;
  flex-shrink: 0;
}

.drawer-photo {
  width: 44px;
  height: 44px;
  border-radius: 14px;
  object-fit: cover;
}

.drawer-initials {
  width: 44px;
  height: 44px;
  border-radius: 14px;
  background: #3b82f6;
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
}

.drawer-title {
  margin: 0;
  font-size: 17px;
  font-weight: 700;
  color: #0f172a;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.drawer-sub {
  margin: 2px 0 0 0;
  font-size: 12px;
  color: #64748b;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.drawer-content-scroll {
  flex: 1;
  overflow-y: auto;
  padding: 16px 20px 40px 20px;
}

/* Hızlı Aksiyon Barı */
.quick-action-strip {
  display: flex;
  gap: 8px;
  margin-bottom: 16px;
}

.quick-action-btn {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 10px;
  border-radius: 14px;
  text-decoration: none;
  font-size: 12px;
  font-weight: 700;
  transition: transform 0.15s ease;
}

.quick-action-btn:active { transform: scale(0.96); }

.btn-call { background: #eff6ff; color: #2563eb; }
.btn-wa { background: #dcfce7; color: #15803d; }
.btn-cv { background: #f3e8ff; color: #9333ea; }

/* Mini Tabs */
.clay-mini-tabs {
  display: flex;
  gap: 6px;
  overflow-x: auto;
  padding-bottom: 6px;
  margin-bottom: 16px;
  scrollbar-width: none;
}

.clay-mini-tabs::-webkit-scrollbar { display: none; }

.mini-tab {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  gap: 5px;
  padding: 8px 12px;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  background: #f8fafc;
  font-size: 12px;
  font-weight: 600;
  color: #475569;
  cursor: pointer;
}

.mini-tab.active {
  background: #0f172a;
  color: #ffffff;
  border-color: #0f172a;
}

.mini-badge {
  background: #3b82f6;
  color: #fff;
  font-size: 10px;
  padding: 1px 5px;
  border-radius: 6px;
}

/* Bilgiler Grid */
.bento-info-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 10px;
}

.info-cell {
  background: #f8fafc;
  padding: 10px 12px;
  border-radius: 12px;
  display: flex;
  flex-direction: column;
}

.cell-label { font-size: 11px; color: #64748b; }
.cell-value { font-size: 13px; font-weight: 700; color: #0f172a; margin-top: 2px; }

.sub-title {
  margin: 0 0 8px 0;
  font-size: 14px;
  font-weight: 700;
  color: #0f172a;
  display: flex;
  align-items: center;
  gap: 6px;
}

.body-text {
  margin: 0;
  font-size: 13px;
  color: #334155;
  line-height: 1.5;
}

.qa-item {
  margin-bottom: 10px;
  padding-bottom: 8px;
  border-bottom: 1px dashed #e2e8f0;
}

.qa-item:last-child { margin-bottom: 0; padding-bottom: 0; border: none; }
.qa-q { margin: 0; font-size: 12px; font-weight: 700; color: #1e293b; }
.qa-a { margin: 3px 0 0 0; font-size: 13px; color: #475569; }

/* AI Detay Kartı */
.ai-score-card {
  display: flex;
  align-items: center;
  gap: 16px;
  background: #f8fafc;
}

.ai-score-circle {
  width: 72px;
  height: 72px;
  border-radius: 50%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  box-shadow: inset 0 2px 4px rgba(0,0,0,0.06);
}

.score-high { background: #dcfce7; color: #15803d; border: 3px solid #86efac; }
.score-mid { background: #fef3c7; color: #b45309; border: 3px solid #fde68a; }
.score-low { background: #fee2e2; color: #b91c1c; border: 3px solid #fca5a5; }

.score-number { font-size: 20px; font-weight: 900; line-height: 1; }
.score-caption { font-size: 9px; font-weight: 600; text-transform: uppercase; margin-top: 2px; }

.ai-score-desc .ai-tag-title { margin: 0; font-size: 15px; font-weight: 800; color: #0f172a; }
.ai-score-desc .ai-date-text { margin: 2px 0 6px 0; font-size: 11px; color: #64748b; }

.rerun-ai-btn {
  padding: 6px 12px;
  font-size: 11px;
}

.point-list, .num-list {
  margin: 0;
  padding-left: 18px;
  font-size: 12px;
  color: #334155;
  line-height: 1.6;
}

.border-emerald { border-left: 4px solid #10b981; }
.border-amber { border-left: 4px solid #f59e0b; }
.border-red { border-left: 4px solid #ef4444; }
.border-purple { border-left: 4px solid #9333ea; }

/* Mülakatlar */
.section-action-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.interview-card {
  padding: 12px;
}

.interview-card-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.interview-type {
  display: flex;
  align-items: center;
  gap: 8px;
}

.interview-date { font-size: 11px; color: #64748b; }
.interview-notes { font-size: 12px; color: #334155; }
.interview-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 11px;
  color: #94a3b8;
}

.delete-icon-btn {
  background: none;
  border: none;
  color: #ef4444;
  cursor: pointer;
  padding: 4px;
}

/* Durum Değiştirme Grid */
.status-change-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 8px;
}

.clay-choice-btn {
  padding: 10px;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  background: #f8fafc;
  font-size: 12px;
  font-weight: 600;
  color: #334155;
  cursor: pointer;
}

.clay-choice-btn.active {
  background: #0f172a;
  color: #fff;
  border-color: #0f172a;
}

.action-buttons-wrap {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.action-buttons-wrap button {
  flex: 1 1 calc(50% - 4px);
}

.converted-alert {
  display: flex;
  align-items: center;
  gap: 8px;
  background: #dcfce7;
  color: #15803d;
  padding: 10px 14px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 600;
}

/* Timeline */
.timeline-list {
  display: flex;
  flex-direction: column;
  position: relative;
  padding-left: 16px;
}

.timeline-list::before {
  content: '';
  position: absolute;
  top: 6px;
  bottom: 6px;
  left: 6px;
  width: 2px;
  background: #e2e8f0;
}

.timeline-item {
  position: relative;
  margin-bottom: 16px;
}

.timeline-item:last-child { margin-bottom: 0; }

.timeline-dot {
  position: absolute;
  left: -14px;
  top: 4px;
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: #3b82f6;
  border: 2px solid #ffffff;
}

.timeline-title { margin: 0; font-size: 13px; font-weight: 700; color: #0f172a; }
.timeline-desc { margin: 2px 0 0 0; font-size: 12px; color: #475569; }
.timeline-meta { font-size: 10px; color: #94a3b8; display: block; margin-top: 3px; }

/* Modallar */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.5);
  backdrop-filter: blur(4px);
  z-index: 1100;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
}

.clay-modal {
  width: 100%;
  max-width: 440px;
  background: #ffffff;
  border-radius: 24px;
  overflow: hidden;
  box-shadow: 0 10px 35px rgba(0, 0, 0, 0.15);
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  border-bottom: 1px solid #f1f5f9;
}

.modal-header h3 { margin: 0; font-size: 16px; font-weight: 700; }

.modal-body {
  padding: 16px 20px;
  max-height: 70vh;
  overflow-y: auto;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  padding: 12px 20px;
  border-top: 1px solid #f1f5f9;
  background: #f8fafc;
}

.form-group {
  margin-bottom: 12px;
}

.form-group label {
  display: block;
  font-size: 12px;
  font-weight: 600;
  color: #475569;
  margin-bottom: 4px;
}

.clay-input, .clay-select, .clay-textarea {
  width: 100%;
  padding: 10px 12px;
  border-radius: 12px;
  border: 1px solid #cbd5e1;
  font-family: inherit;
  font-size: 13px;
  color: #1e293b;
  outline: none;
  background: #f8fafc;
}

.clay-input:focus, .clay-select:focus, .clay-textarea:focus {
  border-color: #3b82f6;
  background: #ffffff;
}

.star-rating-picker {
  display: flex;
  gap: 8px;
  cursor: pointer;
}

.channel-toggle-row {
  display: flex;
  gap: 8px;
}

.channel-btn {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 10px;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  background: #f8fafc;
  font-weight: 600;
  font-size: 12px;
  color: #475569;
  cursor: pointer;
}

.channel-btn.active {
  background: #0f172a;
  color: #ffffff;
  border-color: #0f172a;
}

/* Genel Butonlar */
.clay-btn-primary {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 10px 16px;
  border-radius: 14px;
  border: none;
  background: #2563eb;
  color: #ffffff;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
}

.clay-btn-secondary {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 10px 14px;
  border-radius: 14px;
  border: 1px solid #cbd5e1;
  background: #ffffff;
  color: #334155;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
}

.btn-sm { padding: 6px 12px; font-size: 11px; border-radius: 10px; }
.w-full { width: 100%; }
.mt-2 { margin-top: 8px; }
.mt-3 { margin-top: 12px; }
.mb-3 { margin-bottom: 12px; }
.bg-purple { background-color: #9333ea !important; }
.text-amber { color: #d97706 !important; }
.text-emerald { color: #16a34a !important; }
.text-purple { color: #9333ea !important; }
.text-red { color: #dc2626 !important; }
.text-danger { color: #dc2626 !important; }
.text-success { color: #16a34a !important; }
.text-primary { color: #2563eb !important; }
.text-muted { color: #94a3b8 !important; }
.fill-amber { fill: #d97706; }
.py-4 { padding-top: 24px !important; padding-bottom: 24px !important; }

/* Boş ve Yükleme Durumları */
.loading-state, .empty-state, .empty-sub-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 40px 16px;
  text-align: center;
}

.empty-icon-wrap {
  width: 72px;
  height: 72px;
  border-radius: 50%;
  background: #f1f5f9;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #94a3b8;
  margin-bottom: 12px;
}

.empty-state h3 { margin: 0; font-size: 17px; font-weight: 700; color: #1e293b; }
.empty-state p, .loading-state p, .empty-sub-state p { margin: 6px 0 0 0; font-size: 13px; color: #64748b; }

/* Toast */
.ds-toast {
  position: fixed;
  bottom: calc(var(--ds-safe-bottom, 20px) + 80px);
  left: 50%;
  transform: translateX(-50%);
  background: #0f172a;
  color: #ffffff;
  padding: 10px 18px;
  border-radius: 20px;
  font-size: 13px;
  font-weight: 600;
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.25);
  z-index: 1200;
  pointer-events: none;
}

/* Geçiş Animasyonları */
.drawer-enter-active, .drawer-leave-active {
  transition: opacity 0.25s ease;
}
.drawer-enter-active .clay-drawer, .drawer-leave-active .clay-drawer {
  transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}
.drawer-enter-from, .drawer-leave-to {
  opacity: 0;
}
.drawer-enter-from .clay-drawer, .drawer-leave-to .clay-drawer {
  transform: translateY(100%);
}

.fade-enter-active, .fade-leave-active { transition: opacity 0.2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

/* İlan 3 Adımlı Sihirbaz Modalı */
.ilan-wizard-modal {
  max-width: 500px;
}
.modal-title-wrap h3 { margin: 0; font-size: 16px; font-weight: 700; color: #0f172a; }
.modal-subtitle { margin: 2px 0 0 0; font-size: 11px; color: #64748b; }

.wizard-steps-bar {
  display: flex;
  gap: 4px;
  padding: 8px 16px;
  background: #f8fafc;
  border-bottom: 1px solid #f1f5f9;
}

.wizard-step-btn {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 8px 6px;
  border-radius: 10px;
  border: 1px solid transparent;
  background: transparent;
  font-size: 11px;
  font-weight: 600;
  color: #64748b;
  cursor: pointer;
  transition: all 0.15s ease;
}

.wizard-step-btn .step-num {
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: #e2e8f0;
  color: #475569;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 11px;
  font-weight: 700;
}

.wizard-step-btn.active {
  background: #ffffff;
  color: #0f172a;
  border-color: #cbd5e1;
  box-shadow: 0 2px 6px rgba(0,0,0,0.04);
}

.wizard-step-btn.active .step-num {
  background: #2563eb;
  color: #ffffff;
}

.wizard-step-btn.completed .step-num {
  background: #dcfce7;
  color: #15803d;
}

.form-row-2 {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 10px;
}

.field-hint {
  margin: 4px 0 0 0;
  font-size: 11px;
  color: #94a3b8;
  line-height: 1.35;
}

.label-with-badge {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 4px;
}

.label-with-badge label { margin-bottom: 0; }
.badge-subtle-gray { font-size: 10px; color: #94a3b8; font-weight: 500; }
.badge-pill-blue { font-size: 10px; background: #eff6ff; color: #2563eb; padding: 1px 7px; border-radius: 8px; font-weight: 700; }

.chips-row {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.kriter-chip {
  background: #f1f5f9;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 5px 9px;
  font-size: 11px;
  font-weight: 600;
  color: #334155;
  cursor: pointer;
  transition: all 0.12s ease;
}

.kriter-chip:active { transform: scale(0.96); background: #e2e8f0; }

.empty-questions-box {
  padding: 14px;
  text-align: center;
  background: #f8fafc;
  border: 1px dashed #cbd5e1;
  border-radius: 12px;
  font-size: 12px;
  color: #64748b;
}

.questions-list-wrap {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.question-row-item {
  display: flex;
  align-items: center;
  gap: 8px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  padding: 7px 10px;
  border-radius: 10px;
}

.q-num {
  font-size: 11px;
  font-weight: 700;
  color: #94a3b8;
  width: 16px;
}

.q-text {
  flex: 1;
  font-size: 12px;
  color: #1e293b;
  font-weight: 500;
  line-height: 1.3;
}

.q-actions {
  display: flex;
  gap: 2px;
}

.q-btn {
  background: none;
  border: none;
  color: #64748b;
  cursor: pointer;
  padding: 4px;
  border-radius: 4px;
  display: flex;
  align-items: center;
}

.q-btn:disabled { opacity: 0.25; cursor: not-allowed; }

.add-question-input-row {
  display: flex;
  gap: 6px;
}

.wizard-modal-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.footer-left, .footer-right {
  display: flex;
  align-items: center;
  gap: 8px;
}
</style>
