<script setup>
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  Activity, AlertCircle, ArrowLeft, BadgeDollarSign, BriefcaseBusiness,
  Building2, CalendarCheck, CalendarDays, Car, Check, ChevronRight, Copy,
  Crown, ExternalLink, Eye, EyeOff, FileText, Gavel, Heart, History,
  IdCard, KeyRound, Mail, MapPin, PackageCheck, Pencil, Phone, Plus,
  RefreshCw, Save, Send, ShieldAlert, ShieldCheck, Smartphone,
  Trash2, UserRound, UserRoundCheck, X
} from '@lucide/vue'
import { auth } from '../services/auth.js'
import { personnelApi } from '../services/personnel.js'

const route = useRoute()
const router = useRouter()

const loading = ref(true)
const error = ref('')
const person = ref(null)
const canEdit = ref(false)

// Düzenleme durumu ve formu
const isEditing = ref(false)
const saving = ref(false)
const saveError = ref('')
const toastMessage = ref('')
const showKaskiPassword = ref(false)
const showLoginPassword = ref(false)
const copiedField = ref('')

// Üst Floating Sticky Kontrolü
const stickySentinel = ref(null)
const isFloatingSticky = ref(false)

const editForm = reactive({
  name: '',
  tc_kimlik_no: '',
  dogum_tarihi: '',
  cinsiyet: '',
  medeni_durum: '',
  kan_grubu: '',
  anne_adi: '',
  baba_adi: '',
  dogum_yeri_il: '',
  dogum_yeri_ilce: '',
  ehliyet_sinifi: '',
  seyahat_engeli: 'Yok',
  cep_telefonu: '',
  cep_telefonu_2: '',
  email_adresi: '',
  adres: '',
  ayakkabi_numarasi: '',
  ust_beden_no: '',
  alt_beden_no: '',
  esi_calisiyor_mu: 'Hayır',
  acil_kisi_adi_soyadi: '',
  acil_kisi_yakinlik: '',
  acil_kisi_telefonu: '',
  sifre: '',
  kaski_kullanici_adi: '',
  kaski_sifre: '',
})

const definitions = {
  genel: {
    label: 'Genel bilgiler',
    eyebrow: 'ÖZLÜK & KİMLİK',
    description: 'Kimlik, iletişim, kişisel detaylar ve uygulama erişimi',
    icon: UserRoundCheck,
    features: [
      ['Kimlik ve Kişisel Detaylar', 'TC Kimlik, kan grubu ve doğum bilgileri'],
      ['İletişim ve Adres', 'Telefon, e-posta ve harita konum yönetimi'],
      ['Fiziksel ve Aile', 'Beden ölçüleri, aile ve acil durum kişisi'],
      ['Program ve APK Erişimi', 'Web oturumu ve KASKİ APK giriş şifreleri'],
    ],
  },
  calisma: {
    label: 'Çalışma bilgileri',
    eyebrow: 'ÇALIŞMA & EKİP',
    description: 'Görev, ekip ve çalışma geçmişi',
    icon: BriefcaseBusiness,
    features: [
      ['Görev ve departman', 'Organizasyon bilgileri'],
      ['Ekip geçmişi', 'Ekip kodu atamaları'],
      ['Saha ve araç', 'Çalışma tercihleri'],
    ],
  },
  izinler: {
    label: 'İzin ve raporlar',
    eyebrow: 'İZİN YÖNETİMİ',
    description: 'Hak ediş, kullanılan izin ve rapor geçmişi',
    icon: CalendarDays,
    features: [
      ['İzin hak edişi', 'Yıllık izin bakiyesi'],
      ['İzin geçmişi', 'Onaylı ve bekleyen izinler'],
      ['Yeni izin', 'Personel adına kayıt oluşturma'],
    ],
  },
  zimmetler: {
    label: 'Zimmetler',
    eyebrow: 'VARLIK YÖNETİMİ',
    description: 'Araç, cihaz ve demirbaş teslimleri',
    icon: PackageCheck,
    features: [
      ['Aktif zimmetler', 'Teslim edilmiş varlıklar'],
      ['Zimmet geçmişi', 'İade ve teslim kayıtları'],
      ['Yeni zimmet', 'Demirbaş teslim işlemi'],
    ],
  },
  finansal: {
    label: 'Maaş ve finans',
    eyebrow: 'FİNANS & HAKLAR',
    description: 'Ücret, ek ödeme ve kesinti bilgileri',
    icon: BadgeDollarSign,
    features: [
      ['Maaş bilgileri', 'Ücret ve ödeme ayarları'],
      ['Ek ödemeler', 'Prim, kira ve yardımlar'],
      ['Kesintiler', 'Dönemsel ve sürekli kesintiler'],
    ],
  },
  evraklar: {
    label: 'Personel evrakları',
    eyebrow: 'DİJİTAL ARŞİV',
    description: 'Belgeler, dosyalar ve geçerlilik takibi',
    icon: FileText,
    features: [
      ['Evrak listesi', 'Yüklenen personel belgeleri'],
      ['Yeni evrak', 'Dosya ve belge yükleme'],
      ['Geçerlilik', 'Süre ve durum takibi'],
    ],
  },
  icralar: {
    label: 'İcra dosyaları',
    eyebrow: 'YASAL KESİNTİLER',
    description: 'Borç, sıra ve kesinti takibi',
    icon: Gavel,
    features: [
      ['Aktif dosyalar', 'Devam eden icra kayıtları'],
      ['Kesinti geçmişi', 'Dönemsel tahsilatlar'],
      ['Yeni dosya', 'İcra kaydı oluşturma'],
    ],
  },
  puantaj: {
    label: 'İş ve puantaj',
    eyebrow: 'ÇALIŞMA KAYITLARI',
    description: 'Puantaj, görev ve saha hareketleri',
    icon: CalendarCheck,
    features: [
      ['Puantaj özeti', 'Çalışma ve eksik günler'],
      ['İş geçmişi', 'Tamamlanan saha işleri'],
      ['Dönem takibi', 'Aylık çalışma kayıtları'],
    ],
  },
}

const section = computed(() => definitions[route.params.section] || null)
const legacyUrl = computed(() => `/mobile/index.php?p=personel-duzenle&id=${encodeURIComponent(route.params.id)}&tab=${route.params.section}`)

const general = computed(() => person.value?.general || {})
const work = computed(() => person.value?.work || {})
const workOptions = ref({
  ekipler: [],
  bolgeler: [],
  sgk_firmalari: [],
  personel_siniflari: ['Beyaz Yaka', 'Mavi Yaka'],
  arac_secenekleri: ['Yok', 'Şirket Aracı', 'Özel Araç', 'Havuz Aracı'],
})

// Çalışma Bilgileri Düzenleme Durumu
const isEditingWork = ref(false)
const editWorkForm = reactive({
  ise_giris_tarihi: '',
  isten_cikis_tarihi: '',
  personel_sinifi: 'Beyaz Yaka',
  saha_takibi: 0,
  arac_kullanim: 'Yok',
  sgk_yapilan_firma: 'Yok',
  isten_ayrilis_nedeni: '',
  departman: '',
  gorev: '',
})

// Ekip Modalı Durumu
const showTeamModal = ref(false)
const teamSaving = ref(false)
const teamForm = reactive({
  id: 0,
  ekip_kodu_id: '',
  baslangic_tarihi: '',
  bitis_tarihi: '',
  ekip_sefi_mi: false,
})

// Çalışma Dönemi Modalı Durumu
const showWorkHistoryModal = ref(false)
const workHistorySaving = ref(false)
const workHistoryForm = reactive({
  id: 0,
  sgk_yapilan_firma: '',
  ise_giris_tarihi: '',
  isten_cikis_tarihi: '',
  personel_sinifi: 'Beyaz Yaka',
  saha_takibi: false,
  arac_kullanim: 'Yok',
  isten_ayrilis_nedeni: '',
})

function formatDate(value) {
  if (!value || value === '0000-00-00') return 'Belirtilmemiş'
  try {
    return new Intl.DateTimeFormat('tr-TR', { day: '2-digit', month: 'long', year: 'numeric' }).format(new Date(`${value}T00:00:00`))
  } catch (e) {
    return value
  }
}

function calculateTenure(startDate, endDate) {
  if (!startDate || startDate === '0000-00-00') return ''
  try {
    const start = new Date(`${startDate}T00:00:00`)
    const end = endDate && endDate !== '0000-00-00' ? new Date(`${endDate}T00:00:00`) : new Date()
    let months = (end.getFullYear() - start.getFullYear()) * 12 + (end.getMonth() - start.getMonth())
    if (end.getDate() < start.getDate()) months--
    if (months <= 0) return '1 aydan az'
    const y = Math.floor(months / 12)
    const m = months % 12
    const parts = []
    if (y > 0) parts.push(`${y} yıl`)
    if (m > 0) parts.push(`${m} ay`)
    return parts.join(' ') || '1 ay'
  } catch (e) {
    return ''
  }
}

function calculateAge(birthDate) {
  if (!birthDate || birthDate === '0000-00-00') return ''
  try {
    const today = new Date()
    const birth = new Date(`${birthDate}T00:00:00`)
    let age = today.getFullYear() - birth.getFullYear()
    const m = today.getMonth() - birth.getMonth()
    if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age--
    return age > 0 ? `${age} Yaşında` : ''
  } catch (e) {
    return ''
  }
}

function copyText(val, keyName) {
  if (!val) return
  navigator.clipboard?.writeText(String(val)).then(() => {
    copiedField.value = keyName
    showToast('Panoya kopyalandı')
    setTimeout(() => {
      if (copiedField.value === keyName) copiedField.value = ''
    }, 2000)
  }).catch(() => {
    showToast('Kopyalanamadı')
  })
}

function showToast(msg) {
  toastMessage.value = msg
  setTimeout(() => {
    if (toastMessage.value === msg) toastMessage.value = ''
  }, 2500)
}

function startEdit() {
  saveError.value = ''
  const g = general.value
  editForm.name = person.value?.name || ''
  editForm.tc_kimlik_no = g.tc_kimlik_no || ''
  editForm.dogum_tarihi = g.dogum_tarihi || ''
  editForm.cinsiyet = g.cinsiyet || ''
  editForm.medeni_durum = g.medeni_durum || ''
  editForm.kan_grubu = g.kan_grubu || ''
  editForm.anne_adi = g.anne_adi || ''
  editForm.baba_adi = g.baba_adi || ''
  editForm.dogum_yeri_il = g.dogum_yeri_il || ''
  editForm.dogum_yeri_ilce = g.dogum_yeri_ilce || ''
  editForm.ehliyet_sinifi = g.ehliyet_sinifi || ''
  editForm.seyahat_engeli = g.seyahat_engeli || 'Yok'
  editForm.cep_telefonu = g.cep_telefonu || person.value?.phone || ''
  editForm.cep_telefonu_2 = g.cep_telefonu_2 || ''
  editForm.email_adresi = g.email_adresi || person.value?.email || ''
  editForm.adres = g.adres || ''
  editForm.ayakkabi_numarasi = g.ayakkabi_numarasi || ''
  editForm.ust_beden_no = g.ust_beden_no || ''
  editForm.alt_beden_no = g.alt_beden_no || ''
  editForm.esi_calisiyor_mu = g.esi_calisiyor_mu || 'Hayır'
  editForm.acil_kisi_adi_soyadi = g.acil_kisi_adi_soyadi || ''
  editForm.acil_kisi_yakinlik = g.acil_kisi_yakinlik || ''
  editForm.acil_kisi_telefonu = g.acil_kisi_telefonu || ''
  editForm.sifre = ''
  editForm.kaski_kullanici_adi = g.kaski_kullanici_adi || ''
  editForm.kaski_sifre = g.kaski_sifre || ''
  isEditing.value = true
}

function cancelEdit() {
  isEditing.value = false
  saveError.value = ''
}

async function saveGeneral() {
  saveError.value = ''
  if (!editForm.name.trim()) {
    saveError.value = 'Ad soyad alanı zorunludur.'
    return
  }
  if (editForm.tc_kimlik_no && editForm.tc_kimlik_no.replace(/\D/g, '').length !== 11) {
    saveError.value = 'TC Kimlik Numarası 11 haneli olmalıdır.'
    return
  }
  if (editForm.email_adresi && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(editForm.email_adresi)) {
    saveError.value = 'Geçerli bir e-posta adresi giriniz.'
    return
  }
  if (editForm.sifre && editForm.sifre.length < 6) {
    saveError.value = 'Program şifresi en az 6 karakter olmalıdır.'
    return
  }

  saving.value = true
  try {
    const result = await personnelApi.saveGeneral(route.params.id, editForm, auth.state.csrfToken)
    person.value = result.personnel
    startEdit()
    showToast('Genel bilgiler başarıyla güncellendi.')
  } catch (err) {
    saveError.value = err.message || 'Genel bilgiler kaydedilemedi.'
  } finally {
    saving.value = false
  }
}

// ================= ÇALIŞMA BİLGİLERİ METOTLARI =================
function startEditWork() {
  saveError.value = ''
  const w = work.value
  editWorkForm.ise_giris_tarihi = w.ise_giris_tarihi || person.value?.start_date || ''
  editWorkForm.isten_cikis_tarihi = w.isten_cikis_tarihi || person.value?.end_date || ''
  editWorkForm.personel_sinifi = w.personel_sinifi || 'Beyaz Yaka'
  editWorkForm.saha_takibi = Number(w.saha_takibi || 0)
  editWorkForm.arac_kullanim = w.arac_kullanim || 'Yok'
  editWorkForm.sgk_yapilan_firma = w.sgk_yapilan_firma || 'Yok'
  editWorkForm.isten_ayrilis_nedeni = w.isten_ayrilis_nedeni || ''
  editWorkForm.departman = w.departman || person.value?.department || ''
  editWorkForm.gorev = w.gorev || person.value?.job || ''
  isEditingWork.value = true
}

function cancelEditWork() {
  isEditingWork.value = false
  saveError.value = ''
}

async function saveWorkInfo() {
  saveError.value = ''
  if (!editWorkForm.ise_giris_tarihi) {
    saveError.value = 'İşe giriş tarihi zorunludur.'
    return
  }

  saving.value = true
  try {
    const result = await personnelApi.saveWorkInfo(route.params.id, editWorkForm, auth.state.csrfToken)
    person.value = result.personnel
    startEditWork()
    showToast('Çalışma bilgileri güncellendi.')
  } catch (err) {
    saveError.value = err.message || 'Çalışma bilgileri kaydedilemedi.'
  } finally {
    saving.value = false
  }
}

// Ekip Modalı İşlemleri
function openNewTeamModal() {
  teamForm.id = 0
  teamForm.ekip_kodu_id = workOptions.value.ekipler?.[0]?.id || ''
  teamForm.baslangic_tarihi = new Date().toISOString().slice(0, 10)
  teamForm.bitis_tarihi = ''
  teamForm.ekip_sefi_mi = false
  showTeamModal.value = true
}

function openEditTeamModal(item) {
  teamForm.id = item.id
  teamForm.ekip_kodu_id = item.ekip_kodu_id
  teamForm.baslangic_tarihi = item.baslangic_tarihi
  teamForm.bitis_tarihi = item.bitis_tarihi || ''
  teamForm.ekip_sefi_mi = Boolean(item.ekip_sefi_mi)
  showTeamModal.value = true
}

async function saveTeam() {
  if (!teamForm.ekip_kodu_id || !teamForm.baslangic_tarihi) {
    showToast('Ekip kodu ve başlangıç tarihi zorunludur.')
    return
  }

  teamSaving.value = true
  try {
    const payload = {
      ...teamForm,
      personel_id: route.params.id,
      ekip_sefi_mi: teamForm.ekip_sefi_mi ? 1 : 0,
    }
    const result = await personnelApi.saveTeamHistory(payload, auth.state.csrfToken)
    if (result.work && person.value) {
      person.value.work = result.work
    }
    showTeamModal.value = false
    showToast(result.message || 'Ekip ataması kaydedildi.')
  } catch (err) {
    showToast(err.message || 'Ekip ataması kaydedilemedi.')
  } finally {
    teamSaving.value = false
  }
}

async function deleteTeam(item, force = false) {
  if (!force && !confirm(`'${item.ekip_adi}' atamasını silmek istediğinize emin misiniz?`)) return
  try {
    const result = await personnelApi.deleteTeamHistory(item.id, route.params.id, force, auth.state.csrfToken)
    if (result.status === 'confirm') {
      if (confirm(result.message)) {
        await deleteTeam(item, true)
      }
      return
    }
    if (result.work && person.value) {
      person.value.work = result.work
    }
    showToast(result.message || 'Ekip ataması silindi.')
  } catch (err) {
    showToast(err.message || 'Ekip ataması silinemedi.')
  }
}

// Çalışma Dönemi Modalı İşlemleri
function openNewWorkHistoryModal() {
  workHistoryForm.id = 0
  workHistoryForm.sgk_yapilan_firma = workOptions.value.sgk_firmalari?.[0] || 'ER-SAN ELEKTRİK'
  workHistoryForm.ise_giris_tarihi = new Date().toISOString().slice(0, 10)
  workHistoryForm.isten_cikis_tarihi = ''
  workHistoryForm.personel_sinifi = 'Beyaz Yaka'
  workHistoryForm.saha_takibi = false
  workHistoryForm.arac_kullanim = 'Yok'
  workHistoryForm.isten_ayrilis_nedeni = ''
  showWorkHistoryModal.value = true
}

function openEditWorkHistoryModal(item) {
  workHistoryForm.id = item.id
  workHistoryForm.sgk_yapilan_firma = item.sgk_yapilan_firma || ''
  workHistoryForm.ise_giris_tarihi = item.ise_giris_tarihi || ''
  workHistoryForm.isten_cikis_tarihi = item.isten_cikis_tarihi || ''
  workHistoryForm.personel_sinifi = item.personel_sinifi || 'Beyaz Yaka'
  workHistoryForm.saha_takibi = Boolean(item.saha_takibi)
  workHistoryForm.arac_kullanim = item.arac_kullanim || 'Yok'
  workHistoryForm.isten_ayrilis_nedeni = item.isten_ayrilis_nedeni || ''
  showWorkHistoryModal.value = true
}

async function saveWorkHistory() {
  if (!workHistoryForm.ise_giris_tarihi) {
    showToast('İşe giriş tarihi zorunludur.')
    return
  }

  workHistorySaving.value = true
  try {
    const payload = {
      ...workHistoryForm,
      personel_id: route.params.id,
      saha_takibi: workHistoryForm.saha_takibi ? 1 : 0,
    }
    const result = await personnelApi.saveWorkHistory(payload, auth.state.csrfToken)
    if (result.work && person.value) {
      person.value.work = result.work
    }
    showWorkHistoryModal.value = false
    showToast(result.message || 'Çalışma dönemi kaydedildi.')
  } catch (err) {
    showToast(err.message || 'Çalışma dönemi kaydedilemedi.')
  } finally {
    workHistorySaving.value = false
  }
}

async function deleteWorkHistory(item) {
  if (!confirm(`Bu çalışma geçmişi dönemini silmek istediğinize emin misiniz?`)) return
  try {
    const result = await personnelApi.deleteWorkHistory(item.id, route.params.id, auth.state.csrfToken)
    if (result.work && person.value) {
      person.value.work = result.work
    }
    showToast(result.message || 'Çalışma dönemi silindi.')
  } catch (err) {
    showToast(err.message || 'Çalışma dönemi silinemedi.')
  }
}

async function load() {
  if (!section.value) {
    router.replace(`/personel/${route.params.id}`)
    return
  }
  loading.value = true
  error.value = ''
  try {
    const result = await personnelApi.detail(route.params.id)
    person.value = result.personnel
    canEdit.value = Boolean(result.can_edit)
    if (result.work_options) {
      workOptions.value = result.work_options
    }
    // Düzenleme yetkisi varsa doğrudan düzenleme açık gelsin
    if (canEdit.value) {
      if (route.params.section === 'genel') {
        startEdit()
      } else if (route.params.section === 'calisma') {
        startEditWork()
      }
    }
  } catch (reason) {
    error.value = reason.message
  } finally {
    loading.value = false
  }
}

function onWindowScroll() {
  if (!stickySentinel.value) {
    isFloatingSticky.value = false
    return
  }
  const rect = stickySentinel.value.getBoundingClientRect()
  isFloatingSticky.value = rect.top <= 10
}

watch(() => route.params.section, load)
onMounted(() => {
  load()
  window.addEventListener('scroll', onWindowScroll, { passive: true })
})

onUnmounted(() => {
  window.removeEventListener('scroll', onWindowScroll)
})
</script>

<template>
  <section class="ds-screen personnel-section-screen">
    <!-- 1. Üst Bar -->
    <header class="personnel-section-topbar">
      <button type="button" aria-label="Personel detayına dön" @click="router.push(`/personel/${route.params.id}`)">
        <ArrowLeft :size="20" />
      </button>
      <div>
        <span>{{ section?.eyebrow }}</span>
        <h1>{{ section?.label }}</h1>
      </div>
      <span class="section-icon">
        <component :is="section?.icon || ShieldCheck" :size="19" />
      </span>
    </header>

    <!-- Toast Bildirimi -->
    <transition name="fade">
      <div v-if="toastMessage" class="clay-floating-toast">
        <Check :size="16" />
        <span>{{ toastMessage }}</span>
      </div>
    </transition>

    <div v-if="loading" class="ds-loading">
      <i></i><i></i><i></i>
    </div>

    <div v-else-if="error" class="ds-state">
      <RefreshCw :size="22" />
      <strong>Bilgiler alınamadı</strong>
      <p>{{ error }}</p>
      <button @click="load">Tekrar dene</button>
    </div>

    <template v-else-if="person && section">
      <!-- 2. Profil Özet Başlık Kartı -->
      <section class="bento-card bento-card--white personnel-profile-head">
        <div class="head-left">
          <div class="profile-avatar">
            <img v-if="person.avatar" :src="person.avatar" :alt="person.name" @error="person.avatar = null" />
            <span v-else>{{ person.initials }}</span>
          </div>
          <div class="profile-meta">
            <span class="clay-badge" :class="person.active ? 'clay-badge--emerald' : 'clay-badge--coral'">
              {{ person.active ? 'AKTİF PERSONEL' : 'AYRILMIŞ PERSONEL' }}
            </span>
            <h2>{{ person.name }}</h2>
            <p>{{ [person.job, person.department].filter(Boolean).join(' · ') || 'Çalışma bilgisi tanımlanmamış' }}</p>
          </div>
        </div>

        <div v-if="canEdit && (route.params.section === 'genel' || route.params.section === 'calisma')" class="head-actions">
          <button
            type="button"
            class="clay-btn clay-btn--primary profile-save-btn"
            :disabled="saving"
            @click="route.params.section === 'calisma' ? saveWorkInfo() : saveGeneral()"
          >
            <RefreshCw v-if="saving" :size="15" class="clay-spin-icon" />
            <Save v-else :size="15" />
            <span>{{ saving ? 'Kaydediliyor…' : 'Kaydet' }}</span>
          </button>
        </div>
      </section>

      <!-- ========================================================================= -->
      <!-- 3. GENEL BİLGİLER SEKME İÇERİĞİ (Masaüstü Standartlarında Tam Yönetim)     -->
      <!-- ========================================================================= -->
      <div v-if="route.params.section === 'genel'" class="genel-section-container">
        <!-- Hata Mesajı -->
        <div v-if="saveError" class="clay-alert-banner clay-alert-banner--danger">
          <AlertCircle :size="18" />
          <span>{{ saveError }}</span>
        </div>

        <!-- Hızlı İletişim Çubuğu -->
        <div class="bento-card bento-card--white quick-contact-bar">
          <a
            v-if="person.phone || general.cep_telefonu"
            :href="`tel:${(person.phone || general.cep_telefonu).replace(/[^+\d]/g, '')}`"
            class="quick-action-pill"
          >
            <span class="pill-icon pill-icon--call"><Phone :size="15" /></span>
            <span>Ara</span>
          </a>
          <a
            v-if="person.phone || general.cep_telefonu"
            :href="`https://wa.me/90${(person.phone || general.cep_telefonu).replace(/[^0-9]/g, '').slice(-10)}`"
            target="_blank"
            class="quick-action-pill"
          >
            <span class="pill-icon pill-icon--whatsapp"><Send :size="15" /></span>
            <span>WhatsApp</span>
          </a>
          <a
            v-if="person.email || general.email_adresi"
            :href="`mailto:${person.email || general.email_adresi}`"
            class="quick-action-pill"
          >
            <span class="pill-icon pill-icon--mail"><Mail :size="15" /></span>
            <span>E-posta</span>
          </a>
        </div>

        <!-- Doğrudan Açık Düzenleme Formu -->
        <form class="genel-edit-form" @submit.prevent="saveGeneral">

          <!-- 1. Kimlik Düzenleme -->
          <section class="bento-card bento-card--white form-bento-section">
            <header class="group-header">
              <span class="group-icon group-icon--blue"><IdCard :size="17" /></span>
              <div class="group-titles">
                <h3>Kimlik Bilgileri</h3>
                <small>Resmi kimlik ve temel özlük bilgileri</small>
              </div>
            </header>

            <div class="form-grid">
              <label class="form-field form-field--full">
                <span>Adı Soyadı *</span>
                <input v-model.trim="editForm.name" type="text" placeholder="Ad ve soyad" required />
              </label>

              <label class="form-field">
                <span>TC Kimlik No (11 Haneli)</span>
                <input
                  v-model.trim="editForm.tc_kimlik_no"
                  type="text"
                  inputmode="numeric"
                  maxlength="11"
                  placeholder="11 haneli TC"
                />
              </label>

              <label class="form-field">
                <span>Doğum Tarihi</span>
                <input v-model="editForm.dogum_tarihi" type="date" />
              </label>

              <label class="form-field">
                <span>Cinsiyet</span>
                <select v-model="editForm.cinsiyet">
                  <option value="">Seçiniz</option>
                  <option value="Erkek">Erkek</option>
                  <option value="Kadın">Kadın</option>
                </select>
              </label>

              <label class="form-field">
                <span>Medeni Durum</span>
                <select v-model="editForm.medeni_durum">
                  <option value="">Seçiniz</option>
                  <option value="Bekar">Bekar</option>
                  <option value="Evli">Evli</option>
                </select>
              </label>

              <label class="form-field form-field--full">
                <span>Kan Grubu</span>
                <select v-model="editForm.kan_grubu">
                  <option value="">Seçiniz</option>
                  <option value="A Rh+">A Rh+</option>
                  <option value="A Rh-">A Rh-</option>
                  <option value="B Rh+">B Rh+</option>
                  <option value="B Rh-">B Rh-</option>
                  <option value="AB Rh+">AB Rh+</option>
                  <option value="AB Rh-">AB Rh-</option>
                  <option value="0 Rh+">0 Rh+</option>
                  <option value="0 Rh-">0 Rh-</option>
                </select>
              </label>
            </div>
          </section>

          <!-- 2. İletişim & Adres Düzenleme -->
          <section class="bento-card bento-card--white form-bento-section">
            <header class="group-header">
              <span class="group-icon group-icon--emerald"><Phone :size="17" /></span>
              <div class="group-titles">
                <h3>İletişim & Adres</h3>
                <small>Telefon numaraları, e-posta ve açık adres</small>
              </div>
            </header>

            <div class="form-grid">
              <label class="form-field">
                <span>Cep Telefonu</span>
                <input v-model.trim="editForm.cep_telefonu" type="tel" placeholder="05xx xxx xx xx" />
              </label>

              <label class="form-field">
                <span>2. Cep Telefonu</span>
                <input v-model.trim="editForm.cep_telefonu_2" type="tel" placeholder="05xx xxx xx xx" />
              </label>

              <label class="form-field form-field--full">
                <span>E-posta Adresi</span>
                <input v-model.trim="editForm.email_adresi" type="email" placeholder="ornek@firma.com" />
              </label>

              <label class="form-field form-field--full">
                <span>İkametgah / Açık Adres</span>
                <textarea v-model.trim="editForm.adres" rows="3" placeholder="Açık adres giriniz"></textarea>
              </label>
            </div>
          </section>

          <!-- 3. Kişisel Detaylar Düzenleme -->
          <section class="bento-card bento-card--white form-bento-section">
            <header class="group-header">
              <span class="group-icon group-icon--amber"><Heart :size="17" /></span>
              <div class="group-titles">
                <h3>Kişisel & Aile</h3>
                <small>Anne/baba adı, doğum yeri, ehliyet ve aile bilgileri</small>
              </div>
            </header>

            <div class="form-grid">
              <label class="form-field">
                <span>Anne Adı</span>
                <input v-model.trim="editForm.anne_adi" type="text" placeholder="Anne adı" />
              </label>

              <label class="form-field">
                <span>Baba Adı</span>
                <input v-model.trim="editForm.baba_adi" type="text" placeholder="Baba adı" />
              </label>

              <label class="form-field">
                <span>Doğum Yeri İl</span>
                <input v-model.trim="editForm.dogum_yeri_il" type="text" placeholder="İl" />
              </label>

              <label class="form-field">
                <span>Doğum Yeri İlçe</span>
                <input v-model.trim="editForm.dogum_yeri_ilce" type="text" placeholder="İlçe" />
              </label>

              <label class="form-field">
                <span>Ehliyet Sınıfı</span>
                <input v-model.trim="editForm.ehliyet_sinifi" type="text" placeholder="B, C vb." />
              </label>

              <label class="form-field">
                <span>Seyahat Engeli</span>
                <select v-model="editForm.seyahat_engeli">
                  <option value="Yok">Yok</option>
                  <option value="Var">Var</option>
                </select>
              </label>

              <label class="form-field form-field--full">
                <span>Eşi Çalışıyor Mu?</span>
                <select v-model="editForm.esi_calisiyor_mu">
                  <option value="Hayır">Hayır</option>
                  <option value="Evet">Evet</option>
                </select>
              </label>
            </div>
          </section>

          <!-- 4. Fiziksel Ölçüler & Acil Durum Düzenleme -->
          <section class="bento-card bento-card--white form-bento-section">
            <header class="group-header">
              <span class="group-icon group-icon--cyan"><Activity :size="17" /></span>
              <div class="group-titles">
                <h3>Fiziksel & Acil Durum</h3>
                <small>Beden numaraları ve acil durum irtibat kişisi</small>
              </div>
            </header>

            <div class="form-grid">
              <label class="form-field form-field--third">
                <span>Ayakkabı</span>
                <input v-model.trim="editForm.ayakkabi_numarasi" type="text" placeholder="42" />
              </label>

              <label class="form-field form-field--third">
                <span>Üst Beden</span>
                <input v-model.trim="editForm.ust_beden_no" type="text" placeholder="L, XL" />
              </label>

              <label class="form-field form-field--third">
                <span>Alt Beden</span>
                <input v-model.trim="editForm.alt_beden_no" type="text" placeholder="32, 42" />
              </label>

              <label class="form-field form-field--full">
                <span>Acil Kişi Ad Soyad</span>
                <input v-model.trim="editForm.acil_kisi_adi_soyadi" type="text" placeholder="Ad Soyad" />
              </label>

              <label class="form-field">
                <span>Yakınlık</span>
                <input v-model.trim="editForm.acil_kisi_yakinlik" type="text" placeholder="Eşi, Kardeşi vb." />
              </label>

              <label class="form-field">
                <span>Acil Telefon</span>
                <input v-model.trim="editForm.acil_kisi_telefonu" type="tel" placeholder="05xx xxx xx xx" />
              </label>
            </div>
          </section>

          <!-- 5. Giriş ve Şifre Düzenleme -->
          <section class="bento-card bento-card--white form-bento-section">
            <header class="group-header">
              <span class="group-icon group-icon--purple"><KeyRound :size="17" /></span>
              <div class="group-titles">
                <h3>Sistem & Giriş Bilgileri</h3>
                <small>Sistem şifresi ve entegrasyon kullanıcı bilgileri</small>
              </div>
            </header>

            <div class="form-grid">
              <label class="form-field form-field--full">
                <span>Program Giriş Şifresi (Değiştirmek için doldurun)</span>
                <div class="password-input-wrap">
                  <input
                    v-model="editForm.sifre"
                    :type="showLoginPassword ? 'text' : 'password'"
                    placeholder="En az 6 karakter"
                    autocomplete="new-password"
                  />
                  <button
                    type="button"
                    class="eye-btn"
                    @click="showLoginPassword = !showLoginPassword"
                  >
                    <EyeOff v-if="showLoginPassword" :size="16" />
                    <Eye v-else :size="16" />
                  </button>
                </div>
              </label>

              <label class="form-field">
                <span>KASKİ Kullanıcı Adı</span>
                <input v-model.trim="editForm.kaski_kullanici_adi" type="text" placeholder="Kullanıcı adı" />
              </label>

              <label class="form-field">
                <span>KASKİ Şifresi</span>
                <input v-model="editForm.kaski_sifre" type="text" placeholder="Şifre" />
              </label>
            </div>
          </section>
        </form>
      </div>

      <!-- ========================================================================= -->
      <!-- DİĞER SEKMELER (Çalışma, İzinler vb. mevcut yapı korunur)                  -->
      <!-- ========================================================================= -->
      <!-- ========================================================================= -->
      <!-- 4. ÇALIŞMA BİLGİLERİ SEKME İÇERİĞİ (Masaüstü Standartlarında Tam Yönetim)   -->
      <!-- ========================================================================= -->
      <div v-else-if="route.params.section === 'calisma'" class="calisma-section-container">
        <!-- Hata Mesajı -->
        <div v-if="saveError" class="clay-alert-banner clay-alert-banner--danger">
          <AlertCircle :size="18" />
          <span>{{ saveError }}</span>
        </div>

        <!-- 1. ÇALIŞMA YAŞAM DÖNGÜSÜ KARTI (Doğrudan Düzenlenebilir) -->
        <section class="bento-card bento-card--white form-bento-section">
          <header class="group-header">
            <div class="group-header-left">
              <span class="group-icon group-icon--blue"><BriefcaseBusiness :size="17" /></span>
              <div class="group-titles">
                <h3>Çalışma Yaşam Döngüsü</h3>
                <small>Güncel çalışma durumu ve sözleşme detayları</small>
              </div>
            </div>
          </header>

          <!-- KPI Rozetleri -->
          <div class="work-status-strip mb-3">
            <span
              class="clay-badge"
              :class="work.aktif_calisma_var ? 'clay-badge--emerald' : 'clay-badge--coral'"
            >
              {{ work.aktif_calisma_var ? '● Aktif Çalışan' : '● Pasif / Ayrılmış' }}
            </span>
            <span v-if="calculateTenure(editWorkForm.ise_giris_tarihi || work.ise_giris_tarihi, editWorkForm.isten_cikis_tarihi || work.isten_cikis_tarihi)" class="field-pill">
              Kıdem: {{ calculateTenure(editWorkForm.ise_giris_tarihi || work.ise_giris_tarihi, editWorkForm.isten_cikis_tarihi || work.isten_cikis_tarihi) }}
            </span>
            <span class="field-pill field-pill--purple">
              {{ editWorkForm.sgk_yapilan_firma || work.sgk_yapilan_firma || 'SGK Belirtilmemiş' }}
            </span>
          </div>

          <div class="form-grid">
            <label class="form-field">
              <span>İşe Giriş Tarihi *</span>
              <input v-model="editWorkForm.ise_giris_tarihi" type="date" required />
            </label>

            <label class="form-field">
              <span>İşten Çıkış Tarihi</span>
              <input v-model="editWorkForm.isten_cikis_tarihi" type="date" />
            </label>

            <label class="form-field">
              <span>Personel Sınıfı</span>
              <select v-model="editWorkForm.personel_sinifi">
                <option value="Beyaz Yaka">Beyaz Yaka</option>
                <option value="Mavi Yaka">Mavi Yaka</option>
              </select>
            </label>

            <label class="form-field">
              <span>Saha Takibi</span>
              <select v-model="editWorkForm.saha_takibi">
                <option :value="1">Evet (Saha Takibi Açık)</option>
                <option :value="0">Hayır (Kapalı)</option>
              </select>
            </label>

            <label class="form-field">
              <span>Araç Kullanımı</span>
              <select v-model="editWorkForm.arac_kullanim">
                <option value="Yok">Yok</option>
                <option value="Şirket Aracı">Şirket Aracı</option>
                <option value="Özel Araç">Özel Araç</option>
                <option value="Havuz Aracı">Havuz Aracı</option>
              </select>
            </label>

            <label class="form-field">
              <span>SGK Yapılan Firma</span>
              <input v-model.trim="editWorkForm.sgk_yapilan_firma" type="text" placeholder="Firma adı" />
            </label>

            <label class="form-field">
              <span>Departman</span>
              <input v-model.trim="editWorkForm.departman" type="text" placeholder="Departman" />
            </label>

            <label class="form-field">
              <span>Görev</span>
              <input v-model.trim="editWorkForm.gorev" type="text" placeholder="Görev ünvanı" />
            </label>

            <label v-if="editWorkForm.isten_cikis_tarihi || work.isten_ayrilis_nedeni" class="form-field form-field--full">
              <span>Ayrılış Nedeni</span>
              <textarea v-model.trim="editWorkForm.isten_ayrilis_nedeni" rows="2" placeholder="İşten çıkış nedeni"></textarea>
            </label>

            <div v-if="work.isten_ayrilis_belge_yolu" class="form-field form-field--full">
              <span>Ayrılış Belgesi</span>
              <a :href="work.isten_ayrilis_belge_yolu" target="_blank" class="clay-pill-link mt-1">
                <FileText :size="13" />
                <span>Ayrılış Belgesini Görüntüle</span>
              </a>
            </div>
          </div>
        </section>

        <!-- 2. EKİP ATAMA GEÇMİŞİ KARTI -->
        <section class="bento-card bento-card--white info-group-card">
          <header class="group-header">
            <div class="group-header-left">
              <span class="group-icon group-icon--amber"><Crown :size="17" /></span>
              <div class="group-titles">
                <h3>Ekip Atama Geçmişi</h3>
                <small>Personelin görev aldığı ekipler ve şeflik kayıtları</small>
              </div>
            </div>
            <button
              v-if="canEdit"
              type="button"
              class="clay-btn clay-btn--sm clay-btn--primary header-action-btn"
              @click="openNewTeamModal"
            >
              <Plus :size="14" />
              <span>Yeni Ekip</span>
            </button>
          </header>

          <div v-if="!work.ekip_gecmisi || work.ekip_gecmisi.length === 0" class="empty-list-box">
            <p>Henüz kayıtlı bir ekip ataması bulunmuyor.</p>
          </div>

          <div v-else class="team-list-stack">
            <div
              v-for="item in work.ekip_gecmisi"
              :key="item.id"
              class="team-item-card"
              :class="{ 'team-item-card--active': item.aktif_mi }"
            >
              <div class="team-item-left">
                <div class="team-title-row">
                  <strong>{{ item.ekip_adi }}</strong>
                  <span v-if="item.ekip_sefi_mi" class="clay-badge clay-badge--amber crown-badge">
                    <Crown :size="12" /> Ekip Şefi
                  </span>
                  <span
                    class="clay-badge"
                    :class="item.aktif_mi ? 'clay-badge--emerald' : 'clay-badge--secondary'"
                  >
                    {{ item.aktif_mi ? 'Aktif' : 'Pasif' }}
                  </span>
                </div>
                <div class="team-date-row">
                  <small>
                    <History :size="12" />
                    {{ formatDate(item.baslangic_tarihi) }} — {{ item.bitis_tarihi ? formatDate(item.bitis_tarihi) : 'Devam Ediyor' }}
                  </small>
                </div>
              </div>

              <div v-if="canEdit" class="team-item-actions">
                <button
                  type="button"
                  class="copy-btn"
                  title="Düzenle"
                  @click="openEditTeamModal(item)"
                >
                  <Pencil :size="13" />
                </button>
                <button
                  type="button"
                  class="copy-btn copy-btn--danger"
                  title="Sil"
                  @click="deleteTeam(item)"
                >
                  <Trash2 :size="13" />
                </button>
              </div>
            </div>
          </div>
        </section>

        <!-- 3. ÇALIŞMA BİLGİLERİ GEÇMİŞİ KARTI -->
        <section class="bento-card bento-card--white info-group-card">
          <header class="group-header">
            <div class="group-header-left">
              <span class="group-icon group-icon--purple"><Building2 :size="17" /></span>
              <div class="group-titles">
                <h3>Çalışma Dönemleri Geçmişi</h3>
                <small>SGK firması ve işe giriş-çıkış dönem kayıtları</small>
              </div>
            </div>
            <button
              v-if="canEdit"
              type="button"
              class="clay-btn clay-btn--sm clay-btn--primary header-action-btn"
              @click="openNewWorkHistoryModal"
            >
              <Plus :size="14" />
              <span>Dönem Ekle</span>
            </button>
          </header>

          <div v-if="!work.calisma_gecmisi || work.calisma_gecmisi.length === 0" class="empty-list-box">
            <p>Henüz kayıtlı bir çalışma dönemi geçmişi bulunmuyor.</p>
          </div>

          <div v-else class="period-list-stack">
            <div
              v-for="c in work.calisma_gecmisi"
              :key="c.id"
              class="period-item-card"
              :class="{ 'period-item-card--active': c.aktif_mi }"
            >
              <div class="period-item-left">
                <div class="period-title-row">
                  <strong>{{ c.sgk_yapilan_firma || 'Firma Belirtilmemiş' }}</strong>
                  <span
                    class="clay-badge"
                    :class="c.aktif_mi ? 'clay-badge--emerald' : 'clay-badge--secondary'"
                  >
                    {{ c.aktif_mi ? 'Aktif Dönem' : 'Geçmiş Dönem' }}
                  </span>
                </div>
                <div class="period-meta-row">
                  <small>
                    <History :size="12" />
                    {{ formatDate(c.ise_giris_tarihi) }} — {{ c.isten_cikis_tarihi ? formatDate(c.isten_cikis_tarihi) : 'Devam Ediyor' }}
                  </small>
                  <span class="period-chip">{{ c.personel_sinifi }}</span>
                  <span v-if="c.saha_takibi" class="period-chip period-chip--success">Saha Takibi</span>
                  <span v-if="c.arac_kullanim && c.arac_kullanim !== 'Yok'" class="period-chip period-chip--blue">
                    {{ c.arac_kullanim }}
                  </span>
                </div>
                <div v-if="c.isten_ayrilis_belge_yolu" class="mt-2">
                  <a :href="c.isten_ayrilis_belge_yolu" target="_blank" class="clay-pill-link">
                    <FileText :size="12" />
                    <span>Ayrılış Belgesi</span>
                  </a>
                </div>
              </div>

              <div v-if="canEdit" class="period-item-actions">
                <button
                  type="button"
                  class="copy-btn"
                  title="Düzenle"
                  @click="openEditWorkHistoryModal(c)"
                >
                  <Pencil :size="13" />
                </button>
                <button
                  type="button"
                  class="copy-btn copy-btn--danger"
                  title="Sil"
                  @click="deleteWorkHistory(c)"
                >
                  <Trash2 :size="13" />
                </button>
              </div>
            </div>
          </div>
        </section>

        <!-- =================== MODAL: EKİP ATAMA EKLE / DÜZENLE =================== -->
        <div v-if="showTeamModal" class="clay-modal-backdrop" @click.self="showTeamModal = false">
          <div class="clay-modal-sheet">
            <header class="modal-sheet-header">
              <h4>{{ teamForm.id ? 'Ekip Atamasını Düzenle' : 'Yeni Ekip Tanımla' }}</h4>
              <button type="button" class="close-btn" @click="showTeamModal = false"><X :size="18" /></button>
            </header>

            <form class="modal-sheet-body" @submit.prevent="saveTeam">
              <label class="form-field">
                <span>Ekip Kodu *</span>
                <select v-model="teamForm.ekip_kodu_id" required>
                  <option value="" disabled>Ekip seçiniz</option>
                  <option
                    v-for="e in workOptions.ekipler"
                    :key="e.id"
                    :value="e.id"
                  >
                    {{ e.tur_adi }} {{ e.ekip_bolge ? `(${e.ekip_bolge})` : '' }}
                  </option>
                </select>
              </label>

              <label class="form-field">
                <span>Başlangıç Tarihi *</span>
                <input v-model="teamForm.baslangic_tarihi" type="date" required />
              </label>

              <label class="form-field">
                <span>Bitiş Tarihi (Opsiyonel)</span>
                <input v-model="teamForm.bitis_tarihi" type="date" />
                <small class="text-muted">Boş bırakılırsa atama süresiz devam eder.</small>
              </label>

              <label class="checkbox-field">
                <input v-model="teamForm.ekip_sefi_mi" type="checkbox" />
                <span>Bu personel ekip şefi mi?</span>
              </label>

              <div class="modal-actions mt-3">
                <button type="button" class="clay-btn clay-btn--secondary" @click="showTeamModal = false">Vazgeç</button>
                <button type="submit" class="clay-btn clay-btn--primary" :disabled="teamSaving">
                  {{ teamSaving ? 'Kaydediliyor…' : (teamForm.id ? 'Güncelle' : 'Ekibe Ata') }}
                </button>
              </div>
            </form>
          </div>
        </div>

        <!-- =================== MODAL: ÇALIŞMA DÖNEMİ EKLE / DÜZENLE =================== -->
        <div v-if="showWorkHistoryModal" class="clay-modal-backdrop" @click.self="showWorkHistoryModal = false">
          <div class="clay-modal-sheet">
            <header class="modal-sheet-header">
              <h4>{{ workHistoryForm.id ? 'Çalışma Dönemini Düzenle' : 'Yeni Çalışma Dönemi Ekle' }}</h4>
              <button type="button" class="close-btn" @click="showWorkHistoryModal = false"><X :size="18" /></button>
            </header>

            <form class="modal-sheet-body" @submit.prevent="saveWorkHistory">
              <label class="form-field">
                <span>SGK Yapılan Firma *</span>
                <input v-model.trim="workHistoryForm.sgk_yapilan_firma" type="text" placeholder="Örn: ER-SAN ELEKTRİK" required />
              </label>

              <label class="form-field">
                <span>İşe Giriş Tarihi *</span>
                <input v-model="workHistoryForm.ise_giris_tarihi" type="date" required />
              </label>

              <label class="form-field">
                <span>İşten Çıkış Tarihi</span>
                <input v-model="workHistoryForm.isten_cikis_tarihi" type="date" />
              </label>

              <label class="form-field">
                <span>Personel Sınıfı</span>
                <select v-model="workHistoryForm.personel_sinifi">
                  <option value="Beyaz Yaka">Beyaz Yaka</option>
                  <option value="Mavi Yaka">Mavi Yaka</option>
                </select>
              </label>

              <label class="form-field">
                <span>Araç Kullanımı</span>
                <select v-model="workHistoryForm.arac_kullanim">
                  <option value="Yok">Yok</option>
                  <option value="Şirket Aracı">Şirket Aracı</option>
                  <option value="Özel Araç">Özel Araç</option>
                  <option value="Havuz Aracı">Havuz Aracı</option>
                </select>
              </label>

              <label class="checkbox-field">
                <input v-model="workHistoryForm.saha_takibi" type="checkbox" />
                <span>Saha takibi yapılsın mı?</span>
              </label>

              <label v-if="workHistoryForm.isten_cikis_tarihi" class="form-field">
                <span>Ayrılış Nedeni</span>
                <textarea v-model.trim="workHistoryForm.isten_ayrilis_nedeni" rows="2" placeholder="İşten çıkış nedeni"></textarea>
              </label>

              <div class="modal-actions mt-3">
                <button type="button" class="clay-btn clay-btn--secondary" @click="showWorkHistoryModal = false">Vazgeç</button>
                <button type="submit" class="clay-btn clay-btn--primary" :disabled="workHistorySaving">
                  {{ workHistorySaving ? 'Kaydediliyor…' : (workHistoryForm.id ? 'Güncelle' : 'Kaydet') }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- DİĞER SEKMELER (Geliştirilecek diğer sekmeler için geçiş görünümü) -->
      <template v-else>
        <section class="section-capabilities">
          <header>
            <span>BU BÖLÜMDE</span>
            <h2>Yapabileceğiniz işlemler</h2>
          </header>
          <article v-for="([title, meta], index) in section.features" :key="title">
            <b>{{ String(index + 1).padStart(2, '0') }}</b>
            <div>
              <strong>{{ title }}</strong>
              <small>{{ meta }}</small>
            </div>
            <ChevronRight :size="18" />
          </article>
        </section>

        <a class="section-manage-button" :href="legacyUrl">
          <span>
            <ExternalLink :size="18" />
            <b>{{ canEdit ? 'Tüm yönetim işlemlerini aç' : 'Kayıtları görüntüle' }}</b>
          </span>
          <small>Mevcut mobil modülde kayıpsız devam et</small>
        </a>
      </template>
    </template>
  </section>
</template>

<style scoped>
.personnel-section-screen {
  min-height: 100svh;
  padding-bottom: calc(var(--nav-height, 60px) + 32px + env(safe-area-inset-bottom));
  background: var(--ds-canvas);
  font-family: var(--ds-font-main);
}

.personnel-section-topbar {
  display: grid;
  grid-template-columns: 44px 1fr 44px;
  align-items: center;
  gap: 12px;
  margin-bottom: 16px;
}

.personnel-section-topbar > button,
.section-icon {
  display: grid;
  width: 44px;
  height: 44px;
  place-items: center;
  border: 1px solid rgba(255, 255, 255, 0.9);
  border-radius: 50%;
  color: var(--ds-ink);
  background: var(--ds-surface);
  box-shadow: var(--ds-shadow-bento, 0 10px 24px rgba(16, 24, 40, 0.08));
}

.personnel-section-topbar > div span {
  color: var(--ds-muted);
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.1em;
  text-transform: uppercase;
}

.personnel-section-topbar h1 {
  margin-top: 2px;
  font-size: 18px;
  font-weight: 800;
  color: var(--ds-ink);
  line-height: 24px;
  letter-spacing: -0.03em;
}

/* Profil Başlık Kartı */
.personnel-profile-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px;
  margin-bottom: var(--ds-card-gap, 16px) !important;
  gap: 12px;
}


.head-left {
  display: flex;
  align-items: center;
  gap: 14px;
  min-width: 0;
}

.profile-avatar {
  display: grid;
  width: 52px;
  height: 52px;
  flex: 0 0 auto;
  place-items: center;
  border-radius: 18px;
  color: var(--ds-surface);
  background: var(--ds-surface-dark);
  font-size: 17px;
  font-weight: 800;
  overflow: hidden;
  box-shadow: var(--ds-shadow-pill);
}

.profile-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.profile-meta {
  min-width: 0;
}

.profile-meta h2 {
  margin: 3px 0 0;
  font-size: 17px;
  font-weight: 800;
  color: var(--ds-ink);
  letter-spacing: -0.02em;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.profile-meta p {
  margin: 2px 0 0;
  color: var(--ds-muted);
  font-size: 11.5px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.head-actions {
  display: flex;
  align-items: center;
  flex-shrink: 0;
  margin-left: auto;
}

.profile-save-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  height: 38px;
  padding: 0 16px;
  font-size: 13px;
  font-weight: 800;
  border-radius: var(--ds-radius-sm, 14px);
  background: var(--ds-primary, #0a84ff);
  color: #ffffff;
  border: none;
  box-shadow: 0 4px 14px rgba(10, 132, 255, 0.35);
  cursor: pointer;
  white-space: nowrap;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.profile-save-btn:active {
  transform: scale(0.96);
}

.clay-spin-icon {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

/* Hızlı İletişim Çubuğu */
.quick-contact-bar {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
  padding: 10px;
  margin-bottom: var(--ds-card-gap, 16px) !important;
}

.quick-action-pill {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 10px 6px;
  border-radius: var(--ds-radius-sm);
  background: var(--ds-surface-subtle);
  color: var(--ds-ink);
  text-decoration: none;
  font-size: 11px;
  font-weight: 700;
  transition: transform 0.15s ease;
}

.quick-action-pill:active {
  transform: scale(0.96);
}

.pill-icon {
  display: grid;
  width: 32px;
  height: 32px;
  place-items: center;
  border-radius: 10px;
  color: #fff;
}

.pill-icon--call {
  background: #0284c7;
}

.pill-icon--whatsapp {
  background: #10b981;
}

.pill-icon--mail {
  background: #8b5cf6;
}

/* Bilgi Grubu Kartı */
.info-group-card {
  padding: 18px;
  margin-bottom: var(--ds-card-gap, 16px) !important;
}

.info-group-card:last-child {
  margin-bottom: 0 !important;
}

.group-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
  gap: 12px;
  margin-bottom: 14px;
  padding-bottom: 10px;
  border-bottom: 1px solid var(--ds-border, rgba(0, 0, 0, 0.05));
}

.group-header-left {
  display: flex;
  align-items: center;
  gap: 12px;
  min-width: 0;
}

.header-action-btn {
  margin-left: auto !important;
  flex-shrink: 0;
  white-space: nowrap;
}

.group-icon {
  display: grid;
  width: 34px;
  height: 34px;
  place-items: center;
  border-radius: 10px;
  color: #fff;
  flex-shrink: 0;
}

.group-icon--blue { background: #0284c7; }
.group-icon--emerald { background: #059669; }
.group-icon--amber { background: #d97706; }
.group-icon--cyan { background: #0891b2; }
.group-icon--purple { background: #7c3aed; }

.group-titles h3 {
  margin: 0;
  font-size: 14px;
  font-weight: 800;
  color: var(--ds-ink);
  letter-spacing: -0.01em;
}

.group-titles small {
  display: block;
  font-size: 10.5px;
  color: var(--ds-muted);
  margin-top: 1px;
}

.group-fields-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
}

.field-item {
  display: flex;
  flex-direction: column;
  gap: 3px;
  padding: 8px 10px;
  background: var(--ds-surface-subtle);
  border-radius: var(--ds-radius-sm);
}

.field-item--full {
  grid-column: 1 / -1;
}

.field-label {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--ds-muted);
}

.field-value {
  font-size: 13px;
  font-weight: 700;
  color: var(--ds-ink);
  display: flex;
  align-items: center;
  gap: 6px;
}

.field-value-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.field-value-row strong {
  font-size: 13px;
  font-weight: 700;
  color: var(--ds-ink);
}

.field-pill {
  display: inline-block;
  padding: 2px 6px;
  border-radius: 999px;
  font-size: 10px;
  font-weight: 700;
  background: rgba(2, 132, 199, 0.12);
  color: #0284c7;
}

.copy-btn {
  display: grid;
  width: 26px;
  height: 26px;
  place-items: center;
  border-radius: 6px;
  border: 1px solid var(--ds-border, rgba(0, 0, 0, 0.08));
  background: var(--ds-surface);
  color: var(--ds-muted);
  cursor: pointer;
  transition: all 0.15s ease;
}

.copy-btn:active {
  transform: scale(0.9);
}

.address-text {
  margin: 4px 0 0;
  font-size: 12.5px;
  line-height: 1.45;
  color: var(--ds-ink);
}

.address-actions {
  display: flex;
  gap: 8px;
}

.clay-pill-link,
.clay-pill-btn {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 5px 10px;
  border-radius: 999px;
  font-size: 10.5px;
  font-weight: 700;
  text-decoration: none;
  background: var(--ds-surface);
  color: var(--ds-ink);
  border: 1px solid var(--ds-border, rgba(0, 0, 0, 0.08));
  cursor: pointer;
}

.emergency-contact-box {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 6px 0;
}

.emergency-meta strong {
  display: block;
  font-size: 13px;
  font-weight: 700;
  color: var(--ds-ink);
}

.emergency-meta small {
  color: var(--ds-muted);
  font-size: 10.5px;
}

/* ================= Düzenleme Formu ve Görünüm Stack Stilleri ================= */
.genel-view-stack,
.calisma-view-stack {
  display: flex;
  flex-direction: column;
  gap: var(--ds-card-gap, 16px);
}

.genel-view-stack > .bento-card,
.genel-view-stack > .info-group-card,
.genel-view-stack > .quick-contact-bar,
.calisma-view-stack > .bento-card,
.calisma-view-stack > .info-group-card {
  margin-bottom: 0 !important;
}

.genel-edit-form,
.calisma-edit-form {
  display: flex;
  flex-direction: column;
  gap: var(--ds-card-gap, 16px);
}

.genel-edit-form > .bento-card,
.calisma-edit-form > .bento-card,
.genel-edit-form .form-bento-section,
.calisma-edit-form .form-bento-section,
.genel-edit-form .edit-sticky-top-bar,
.calisma-edit-form .edit-sticky-top-bar {
  margin-bottom: 0 !important;
}

.form-bento-section {
  padding: 18px;
}

.section-title {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 14px;
  padding-bottom: 8px;
  border-bottom: 1px solid var(--ds-border, rgba(0, 0, 0, 0.06));
}

.section-badge {
  display: grid;
  width: 22px;
  height: 22px;
  place-items: center;
  border-radius: 6px;
  background: var(--ds-primary);
  color: #fff;
  font-size: 10px;
  font-weight: 800;
}

.section-title h4 {
  margin: 0;
  font-size: 13.5px;
  font-weight: 800;
  color: var(--ds-ink);
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
}

.form-field {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 9px 13px;
  background: var(--ds-surface-subtle, #f1f4f9);
  border-radius: var(--ds-radius-sm, 14px);
  border: 1.5px solid transparent;
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
  cursor: text;
}

.form-field:focus-within {
  background: #ffffff;
  border-color: rgba(10, 132, 255, 0.45);
  box-shadow: 0 4px 14px rgba(10, 132, 255, 0.08), 0 0 0 3px rgba(10, 132, 255, 0.12);
}

.form-field--full {
  grid-column: 1 / -1;
}

.form-field--third {
  grid-column: span 1;
}

.form-field > span,
.form-field span.field-label {
  font-size: 10.5px;
  font-weight: 800;
  color: #7b889b;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-bottom: 2px;
  display: block;
  user-select: none;
}

.form-field input,
.form-field select,
.form-field textarea {
  width: 100%;
  padding: 1px 0 0 !important;
  border: none !important;
  outline: none !important;
  background: transparent !important;
  box-shadow: none !important;
  color: var(--ds-ink, #191d24);
  font-family: inherit;
  font-size: 14.5px;
  font-weight: 800;
  line-height: 1.35;
  transition: color 0.15s ease;
}

.form-field input::placeholder,
.form-field textarea::placeholder {
  color: #a4b1c2;
  font-weight: 600;
  font-size: 13.5px;
}

.form-field select {
  cursor: pointer;
  -webkit-appearance: none;
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%237b889b' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 4px center;
  padding-right: 22px !important;
}

.form-field textarea {
  resize: vertical;
  min-height: 56px;
  line-height: 1.4;
}

.password-input-wrap {
  position: relative;
  display: flex;
  align-items: center;
  width: 100%;
}

.password-input-wrap input {
  padding-right: 32px !important;
}

.eye-btn {
  position: absolute;
  right: 0;
  top: 50%;
  transform: translateY(-50%);
  display: grid;
  place-items: center;
  width: 28px;
  height: 28px;
  border: 0;
  background: transparent;
  color: var(--ds-muted);
  cursor: pointer;
}

/* Üst Sticky Aksiyon Barı (Kaydet & Vazgeç) */
.sticky-sentinel {
  height: 1px;
  margin-bottom: -1px;
  pointer-events: none;
  visibility: hidden;
}

.sticky-bar-placeholder {
  height: 56px;
  width: 100%;
}

.edit-sticky-top-bar {
  position: sticky;
  top: 8px;
  z-index: 50;
  display: grid;
  grid-template-columns: 1fr 2fr;
  gap: 10px;
  padding: 10px 14px;
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  border-radius: var(--ds-radius-card, 22px);
  border: 1px solid rgba(255, 255, 255, 0.95);
  box-shadow: 0 10px 26px rgba(16, 24, 40, 0.12), 0 2px 6px rgba(16, 24, 40, 0.04);
  margin-bottom: 0 !important;
  transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.2s ease;
}

.edit-sticky-top-bar.is-floating {
  position: fixed !important;
  top: 10px !important;
  left: 50% !important;
  transform: translateX(-50%) !important;
  width: calc(100% - 32px) !important;
  max-width: 448px !important;
  z-index: 100 !important;
  margin-bottom: 0 !important;
  box-shadow: 0 16px 36px rgba(16, 24, 40, 0.24), 0 4px 10px rgba(16, 24, 40, 0.08) !important;
}

.edit-sticky-top-bar .cancel-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  height: 42px;
  font-size: 13.5px;
  font-weight: 700;
  border-radius: var(--ds-radius-sm, 14px);
  border: 1px solid var(--ds-border, rgba(0, 0, 0, 0.08));
  background: var(--ds-surface, #ffffff);
  color: var(--ds-ink, #191d24);
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
  cursor: pointer;
}

.edit-sticky-top-bar .cancel-btn:active {
  transform: scale(0.97);
}

.edit-sticky-top-bar .save-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  height: 42px;
  font-size: 14px;
  font-weight: 800;
  border-radius: var(--ds-radius-sm, 14px);
  background: var(--ds-primary, #0a84ff);
  color: #ffffff;
  border: none;
  box-shadow: 0 4px 16px rgba(10, 132, 255, 0.35);
  cursor: pointer;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.edit-sticky-top-bar .save-btn:active {
  transform: scale(0.98);
}

/* Toast Bildirimi */
.clay-floating-toast {
  position: fixed;
  top: 76px;
  left: 50%;
  transform: translateX(-50%);
  z-index: 100;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  border-radius: 999px;
  background: var(--ds-surface-dark);
  color: #fff;
  font-size: 12px;
  font-weight: 700;
  box-shadow: 0 10px 24px rgba(0, 0, 0, 0.25);
}

.clay-alert-banner {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 14px;
  border-radius: var(--ds-radius-sm);
  font-size: 12px;
  font-weight: 700;
  margin-bottom: 12px;
}

.clay-alert-banner--danger {
  background: rgba(239, 68, 68, 0.1);
  color: #ef4444;
  border: 1px solid rgba(239, 68, 68, 0.2);
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
  transform: translate(-50%, -8px);
}

/* Mevcut diğer sekmeler için stiller */
.section-native-card {
  margin-top: 14px;
  padding: 18px;
  border: 1px solid rgba(255, 255, 255, 0.9);
  border-radius: var(--ds-radius-card, 28px);
  background: var(--ds-surface);
  box-shadow: var(--ds-shadow-bento, 0 10px 24px rgba(16, 24, 40, 0.08));
}

.section-native-card header {
  display: flex;
  margin-bottom: 10px;
  justify-content: space-between;
}

.section-native-card header span,
.section-capabilities header span {
  color: var(--ds-muted);
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.1em;
  text-transform: uppercase;
}

.section-native-card header small {
  color: var(--ds-muted-light);
  font-size: 11px;
}

.section-native-card > div {
  display: grid;
  min-height: 52px;
  padding: 8px 0;
  grid-template-columns: 40px 1fr;
  align-items: center;
  gap: 12px;
  border-bottom: 1px solid rgba(0, 0, 0, 0.05);
  color: var(--ds-ink);
}

.section-native-card > :last-child {
  border-bottom: 0;
}

.section-native-card > div > svg {
  padding: 8px;
  width: 36px;
  height: 36px;
  border-radius: 12px;
  color: var(--ds-ink);
  background: var(--ds-surface-subtle);
}

.section-native-card small,
.section-native-card strong {
  display: block;
}

.section-native-card div small {
  color: var(--ds-muted);
  font-size: 11px;
}

.section-native-card div strong {
  margin-top: 2px;
  font-size: 13px;
  font-weight: 600;
}

.section-capabilities {
  margin-top: 24px;
}

.section-capabilities header {
  margin: 0 4px 12px;
}

.section-capabilities h2 {
  margin-top: 4px;
  font-size: 18px;
  font-weight: 700;
  color: var(--ds-ink);
  letter-spacing: -0.025em;
}

.section-capabilities article {
  display: grid;
  min-height: 60px;
  padding: 12px 16px;
  grid-template-columns: 30px 1fr 18px;
  align-items: center;
  gap: 12px;
  border: 1px solid rgba(255, 255, 255, 0.9);
  border-bottom-color: rgba(0, 0, 0, 0.04);
  background: var(--ds-surface);
}

.section-capabilities article:first-of-type {
  border-radius: 20px 20px 0 0;
}

.section-capabilities article:last-child {
  border-radius: 0 0 20px 20px;
  border-bottom-color: rgba(255, 255, 255, 0.9);
}

.section-capabilities article > b {
  color: var(--ds-muted-light);
  font-size: 12px;
  font-weight: 700;
}

.section-capabilities strong,
.section-capabilities small {
  display: block;
}

.section-capabilities strong {
  font-size: 13px;
  font-weight: 600;
  color: var(--ds-ink);
}

.section-capabilities small {
  margin-top: 2px;
  color: var(--ds-muted);
  font-size: 11px;
}

.section-manage-button {
  display: flex;
  margin-top: 16px;
  padding: 14px 18px;
  align-items: center;
  justify-content: space-between;
  border-radius: 20px;
  color: var(--ds-surface);
  background: var(--ds-surface-dark);
  box-shadow: 0 10px 24px rgba(25, 29, 36, 0.25);
  text-decoration: none;
}

.section-manage-button > span {
  display: flex;
  align-items: center;
  gap: 8px;
}

.section-manage-button b {
  font-size: 13px;
  font-weight: 700;
}

.section-manage-button small {
  color: rgba(255, 255, 255, 0.65);
  font-size: 11px;
}

/* ================= Çalışma ve Genel Bölüm Kapsayıcıları ================= */
.genel-section-container,
.calisma-section-container {
  display: flex;
  flex-direction: column;
  gap: var(--ds-card-gap, 16px);
}

.work-status-strip {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}

.field-pill--purple {
  background: rgba(124, 58, 237, 0.12);
  color: #7c3aed;
}

.empty-list-box {
  padding: 24px 16px;
  text-align: center;
  color: var(--ds-muted);
  font-size: 12px;
}

.team-list-stack,
.period-list-stack {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.team-item-card,
.period-item-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 12px 14px;
  border-radius: var(--ds-radius-sm);
  background: var(--ds-surface-subtle);
  border: 1px solid var(--ds-border, rgba(0, 0, 0, 0.05));
}

.team-item-card--active,
.period-item-card--active {
  border-left: 3px solid #10b981;
  background: rgba(16, 185, 129, 0.03);
}

.team-item-left,
.period-item-left {
  flex: 1;
  min-width: 0;
}

.team-title-row,
.period-title-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  margin-bottom: 4px;
}

.team-title-row strong,
.period-title-row strong {
  font-size: 13px;
  font-weight: 800;
  color: var(--ds-ink);
}

.crown-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

.team-date-row small,
.period-meta-row small {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  color: var(--ds-muted);
}

.period-meta-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
  margin-top: 4px;
}

.period-chip {
  display: inline-block;
  padding: 2px 7px;
  border-radius: 999px;
  font-size: 10px;
  font-weight: 700;
  background: var(--ds-surface);
  border: 1px solid var(--ds-border, rgba(0, 0, 0, 0.08));
  color: var(--ds-ink);
}

.period-chip--success {
  background: rgba(16, 185, 129, 0.1);
  color: #10b981;
  border-color: rgba(16, 185, 129, 0.2);
}

.period-chip--blue {
  background: rgba(2, 132, 199, 0.1);
  color: #0284c7;
  border-color: rgba(2, 132, 199, 0.2);
}

.team-item-actions,
.period-item-actions {
  display: flex;
  align-items: center;
  gap: 6px;
}

.copy-btn--danger {
  color: #ef4444;
}

.copy-btn--danger:hover {
  background: rgba(239, 68, 68, 0.1);
}

/* Modallar */
.clay-modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 1000;
  background: rgba(0, 0, 0, 0.45);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: flex-end;
  justify-content: center;
}

.clay-modal-sheet {
  width: 100%;
  max-width: 500px;
  max-height: 85vh;
  overflow-y: auto;
  border-radius: 24px 24px 0 0;
  background: var(--ds-surface);
  padding: 20px;
  box-shadow: 0 -10px 40px rgba(0, 0, 0, 0.2);
}

.modal-sheet-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
  padding-bottom: 10px;
  border-bottom: 1px solid var(--ds-border, rgba(0, 0, 0, 0.06));
}

.modal-sheet-header h4 {
  margin: 0;
  font-size: 15px;
  font-weight: 800;
  color: var(--ds-ink);
}

.close-btn {
  display: grid;
  width: 32px;
  height: 32px;
  place-items: center;
  border-radius: 50%;
  border: 0;
  background: var(--ds-surface-subtle);
  color: var(--ds-muted);
  cursor: pointer;
}

.modal-sheet-body {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.checkbox-field {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12px;
  font-weight: 700;
  color: var(--ds-ink);
  cursor: pointer;
  margin-top: 4px;
}

.checkbox-field input {
  width: 18px;
  height: 18px;
  border-radius: 6px;
}

.modal-actions {
  display: grid;
  grid-template-columns: 1fr 2fr;
  gap: 10px;
}
</style>
