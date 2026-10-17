<script setup>
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  Activity, AlertCircle, ArrowLeft, BadgeDollarSign, Ban, Banknote,
  BriefcaseBusiness, Building2, CalendarCheck, CalendarDays, Car, Check,
  ChevronDown, ChevronRight, ChevronUp, Clock, Coins, Copy, CreditCard, Crown,
  ExternalLink, Eye, EyeOff, FileText, Gavel, Heart, History, IdCard, KeyRound,
  Mail, MapPin, MinusCircle, PackageCheck, Pencil, Percent, Phone, Plus,
  Receipt, RefreshCw, Save, Send, ShieldAlert, ShieldCheck, Smartphone,
  Trash2, TrendingDown, TrendingUp, UserRound, UserRoundCheck, Wallet, X
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
  ek_odemeler: {
    label: 'Ek ödemeler',
    eyebrow: 'EK KAZANÇ & YARDIMLAR',
    description: 'Prim, ikramiye, kira ve düzenli yardımlar',
    icon: BadgeDollarSign,
    features: [
      ['Tek Seferlik Ödemeler', 'Dönemsel prim ve ikramiyeler'],
      ['Sürekli Ek Ödemeler', 'Kira, yol ve düzenli yardımlar'],
      ['Bordro Entegrasyonu', 'Banka matrahı ve resmi tutar takibi'],
    ],
  },
  kesintiler: {
    label: 'Kesintiler',
    eyebrow: 'KESİNTİ & MAHSUP',
    description: 'Dönemsel, sürekli ve taksitli kesintiler',
    icon: Receipt,
    features: [
      ['Tek Seferlik Kesintiler', 'Avans, ceza ve dönemsel kesintiler'],
      ['Taksitli Kesintiler', 'Borç ve taksitli mahsup işlemleri'],
      ['Sürekli Kesintiler', 'BES, nafaka ve düzenli kesintiler'],
    ],
  },
  finansal: {
    label: 'Ek ödemeler ve kesintiler',
    eyebrow: 'FİNANS & HAKLAR',
    description: 'Ücret, ek ödeme ve kesinti bilgileri',
    icon: BadgeDollarSign,
    features: [
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

// ================= İZİN VE RAPOR DURUMLARI =================
const leaves = ref([])
const entitlement = ref({ toplam_hakedis: 0, kullanilan_izin: 0, kalan_izin: 0, detay: [] })
const leaveTypes = ref([])

const showLeaveModal = ref(false)
const leaveSaving = ref(false)
const leaveForm = reactive({
  id: 0,
  izin_tipi_id: '',
  baslangic_tarihi: '',
  bitis_tarihi: '',
  toplam_gun: 1,
  ucretli_mi: 1,
  yillik_izne_etki: false,
  aciklama: '',
  onay_durumu: 'Onaylandı',
})

const showEntitlementModal = ref(false)

const showApprovalModal = ref(false)
const approvalSaving = ref(false)
const approvalForm = reactive({
  id: 0,
  status: 'Onaylandı',
  note: '',
})

// ================= EK ÖDEMELER DURUMLARI =================
const ekOdemeler = ref([])
const ekOdemeOzet = ref({
  toplam_tutar: 0,
  tek_sefer_adet: 0,
  tek_sefer_tutar: 0,
  surekli_adet: 0,
  surekli_tutar: 0,
  banka_tutar: 0,
  elden_tutar: 0,
})
const gelirParametreleri = ref([])
const bordroDonemleri = ref([])
const ekOdemeTab = ref('all')
const ekOdemeDonemFilter = ref('all')
const showEkOdemeModal = ref(false)
const showEkOdemeSonlandirModal = ref(false)
const ekOdemeSaving = ref(false)

// Puantaj Grubu Açılır Detay Durumu
const expandedPuantajGroups = ref({})

function togglePuantajDetails(groupId) {
  expandedPuantajGroups.value[groupId] = !expandedPuantajGroups.value[groupId]
}

function cleanPuantajDescription(desc) {
  if (!desc) return ''
  return desc.replace(/^\[Puantaj\]\s*/i, '').trim()
}

// Oranlar (Obsidian Dağılım Kartı)
const ekOdemeTekSeferRatio = computed(() => {
  const total = Number(ekOdemeOzet.value.toplam_tutar) || 0
  if (total <= 0) return 50
  return Math.round((Number(ekOdemeOzet.value.tek_sefer_tutar) / total) * 100)
})

const ekOdemeSurekliRatio = computed(() => {
  const total = Number(ekOdemeOzet.value.toplam_tutar) || 0
  if (total <= 0) return 50
  return Math.max(0, 100 - ekOdemeTekSeferRatio.value)
})

const ekOdemeForm = reactive({
  id: 0,
  parametre_id: '',
  tur: 'diger',
  tekrar_tipi: 'tek_sefer',
  hesaplama_tipi: 'sabit',
  tutar: '',
  resmi_tutar: '',
  oran: '',
  tarih: new Date().toISOString().slice(0, 10),
  donem_id: '',
  baslangic_donemi: new Date().toISOString().slice(0, 7) + '-01',
  bitis_donemi: '',
  banka_matrahina_ekle: 1,
  aciklama: '',
})

const ekOdemeSonlandirForm = reactive({
  id: 0,
  parametre_adi: '',
  bitis_donemi: new Date().toISOString().slice(0, 7) + '-01',
})

const filteredEkOdemeler = computed(() => {
  let list = ekOdemeler.value
  if (ekOdemeTab.value === 'tek_sefer') {
    list = list.filter(k => k.tekrar_tipi === 'tek_sefer')
  } else if (ekOdemeTab.value === 'surekli') {
    list = list.filter(k => k.tekrar_tipi === 'surekli')
  }

  if (ekOdemeDonemFilter.value !== 'all') {
    const selDonem = bordroDonemleri.value.find(d => String(d.id) === String(ekOdemeDonemFilter.value))
    if (selDonem) {
      list = list.filter(k => {
        if (k.tekrar_tipi === 'tek_sefer') {
          return Number(k.donem_id) === Number(selDonem.id) || (k.donem_adi && k.donem_adi.toUpperCase() === selDonem.donem_adi.toUpperCase())
        }
        if (k.tekrar_tipi === 'surekli') {
          const dBas = selDonem.baslangic_tarihi || '2000-01-01'
          const dBit = selDonem.bitis_tarihi || '2099-12-31'
          const kBas = k.baslangic_donemi || '2000-01-01'
          const kBit = k.bitis_donemi || '2099-12-31'
          return kBas <= dBit && kBit >= dBas
        }
        return true
      })
    }
  }

  return list
})

// Puantaj primlerini dönem bazlı tek kart haline getiren liste
const displayEkOdemeler = computed(() => {
  const regularItems = []
  const puantajGroups = {}

  for (const item of filteredEkOdemeler.value) {
    const isPuantaj = item.is_puantaj || (item.aciklama && item.aciklama.includes('[Puantaj]'))
    if (isPuantaj) {
      const key = item.donem_id ? `donem_${item.donem_id}` : (item.donem_adi || 'belirtilmemis')
      if (!puantajGroups[key]) {
        puantajGroups[key] = {
          id: `puantaj_${key}`,
          is_puantaj_group: true,
          parametre_adi: 'Puantaj Primleri',
          tur: 'prim',
          tekrar_tipi: 'tek_sefer',
          hesaplama_tipi: 'sabit',
          tutar: 0,
          resmi_tutar: 0,
          donem_id: item.donem_id,
          donem_adi: item.donem_adi || 'Dönem Belirtilmemiş',
          kapali_mi: item.kapali_mi,
          banka_matrahina_ekle: item.banka_matrahina_ekle,
          tarih: item.tarih,
          aktif: 1,
          sub_items: [],
        }
      }
      puantajGroups[key].tutar += Number(item.tutar) || 0
      puantajGroups[key].resmi_tutar += Number(item.resmi_tutar) || 0
      if (item.kapali_mi) puantajGroups[key].kapali_mi = 1
      puantajGroups[key].sub_items.push(item)
    } else {
      regularItems.push(item)
    }
  }

  return [...regularItems, ...Object.values(puantajGroups)]
})

// ================= KESİNTİLER DURUMLARI =================
const kesintiler = ref([])
const kesintiOzet = ref({
  toplam_tutar: 0,
  tek_sefer_adet: 0,
  tek_sefer_tutar: 0,
  taksitli_adet: 0,
  taksitli_tutar: 0,
  surekli_adet: 0,
  surekli_tutar: 0,
  banka_tutar: 0,
  elden_tutar: 0,
})
const kesintiParametreleri = ref([])
const icraDosyalari = ref([])
const kesintiTab = ref('all')
const kesintiDonemFilter = ref('all')
const showKesintiModal = ref(false)
const showKesintiSonlandirModal = ref(false)
const kesintiSaving = ref(false)

// Kesinti Oranları (Obsidian Dağılım Kartı)
const kesintiTekSeferRatio = computed(() => {
  const total = Number(kesintiOzet.value.toplam_tutar) || 0
  if (total <= 0) return 33
  return Math.round((Number(kesintiOzet.value.tek_sefer_tutar) / total) * 100)
})

const kesintiTaksitliRatio = computed(() => {
  const total = Number(kesintiOzet.value.toplam_tutar) || 0
  if (total <= 0) return 33
  return Math.round((Number(kesintiOzet.value.taksitli_tutar) / total) * 100)
})

const kesintiSurekliRatio = computed(() => {
  const total = Number(kesintiOzet.value.toplam_tutar) || 0
  if (total <= 0) return 34
  return Math.max(0, 100 - (kesintiTekSeferRatio.value + kesintiTaksitliRatio.value))
})

const kesintiForm = reactive({
  id: 0,
  parametre_id: '',
  tur: 'diger',
  tekrar_tipi: 'tek_sefer',
  hesaplama_tipi: 'sabit',
  tutar: '',
  oran: '',
  taksit_sayisi: 1,
  tarih: new Date().toISOString().slice(0, 10),
  donem_id: '',
  baslangic_donemi: new Date().toISOString().slice(0, 7) + '-01',
  bitis_donemi: '',
  banka_matrahina_ekle: 1,
  icra_id: '',
  aciklama: '',
})

const kesintiSonlandirForm = reactive({
  id: 0,
  parametre_adi: '',
  bitis_donemi: new Date().toISOString().slice(0, 7) + '-01',
})

const filteredKesintiler = computed(() => {
  let list = kesintiler.value
  if (kesintiTab.value === 'tek_sefer') {
    list = list.filter(k => k.tekrar_tipi === 'tek_sefer')
  } else if (kesintiTab.value === 'taksitli') {
    list = list.filter(k => k.tekrar_tipi === 'taksitli')
  } else if (kesintiTab.value === 'surekli') {
    list = list.filter(k => k.tekrar_tipi === 'surekli')
  }

  if (kesintiDonemFilter.value !== 'all') {
    const selDonem = bordroDonemleri.value.find(d => String(d.id) === String(kesintiDonemFilter.value))
    if (selDonem) {
      list = list.filter(k => {
        if (k.tekrar_tipi === 'tek_sefer' || k.tekrar_tipi === 'taksitli') {
          return Number(k.donem_id) === Number(selDonem.id) || (k.donem_adi && k.donem_adi.toUpperCase() === selDonem.donem_adi.toUpperCase())
        }
        if (k.tekrar_tipi === 'surekli') {
          const dBas = selDonem.baslangic_tarihi || '2000-01-01'
          const dBit = selDonem.bitis_tarihi || '2099-12-31'
          const kBas = k.baslangic_donemi || '2000-01-01'
          const kBit = k.bitis_donemi || '2099-12-31'
          return kBas <= dBit && kBit >= dBas
        }
        return true
      })
    }
  }

  return list
})

function formatMoney(amount) {
  const val = Number(amount) || 0
  return new Intl.NumberFormat('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(val) + ' ₺'
}

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

// ================= İZİN VE RAPOR METOTLARI =================
function onLeaveDatesChange() {
  if (leaveForm.baslangic_tarihi && leaveForm.bitis_tarihi) {
    const start = new Date(`${leaveForm.baslangic_tarihi}T00:00:00`)
    const end = new Date(`${leaveForm.bitis_tarihi}T00:00:00`)
    if (end >= start) {
      const diffTime = Math.abs(end - start)
      const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1
      leaveForm.toplam_gun = diffDays
    }
  }
}

function onLeaveTypeChange() {
  const selected = leaveTypes.value.find(t => Number(t.id) === Number(leaveForm.izin_tipi_id))
  if (selected) {
    leaveForm.ucretli_mi = Number(selected.ucretli_mi ?? 1)
    if (selected.tur_adi?.includes('Yıllık') || selected.tur_adi?.includes('Yillik')) {
      leaveForm.yillik_izne_etki = true
    } else {
      leaveForm.yillik_izne_etki = false
    }
  }
}

function openNewLeaveModal() {
  leaveForm.id = 0
  const yillik = leaveTypes.value.find(t => t.tur_adi?.includes('Yıllık') || t.tur_adi?.includes('Yillik'))
  leaveForm.izin_tipi_id = yillik ? yillik.id : (leaveTypes.value[0]?.id || '')
  const today = new Date().toISOString().slice(0, 10)
  leaveForm.baslangic_tarihi = today
  leaveForm.bitis_tarihi = today
  leaveForm.toplam_gun = 1
  leaveForm.ucretli_mi = yillik ? Number(yillik.ucretli_mi ?? 1) : 1
  leaveForm.yillik_izne_etki = Boolean(yillik)
  leaveForm.aciklama = ''
  leaveForm.onay_durumu = 'Onaylandı'
  showLeaveModal.value = true
}

function openEditLeaveModal(item) {
  leaveForm.id = item.id
  leaveForm.izin_tipi_id = item.izin_tipi_id
  leaveForm.baslangic_tarihi = item.baslangic_tarihi
  leaveForm.bitis_tarihi = item.bitis_tarihi
  leaveForm.toplam_gun = item.toplam_gun
  leaveForm.ucretli_mi = item.ucretli_mi
  leaveForm.yillik_izne_etki = item.yillik_izne_etki === 'Dus' || item.yillik_izne_etki === '1' || item.yillik_izne_etki === 1
  leaveForm.aciklama = item.aciklama || ''
  leaveForm.onay_durumu = item.onay_durumu || 'Onaylandı'
  showLeaveModal.value = true
}

async function saveLeave() {
  if (!leaveForm.izin_tipi_id) {
    showToast('Lütfen izin türü seçiniz.')
    return
  }
  if (!leaveForm.baslangic_tarihi || !leaveForm.bitis_tarihi) {
    showToast('Başlangıç ve bitiş tarihleri zorunludur.')
    return
  }
  if (leaveForm.toplam_gun <= 0) {
    showToast('İzin süresi sıfırdan büyük olmalıdır.')
    return
  }

  leaveSaving.value = true
  try {
    const payload = {
      ...leaveForm,
      personel_id: route.params.id,
      yillik_izne_etki: leaveForm.yillik_izne_etki ? 1 : 0,
    }
    const result = await personnelApi.saveLeave(payload, auth.state.csrfToken)
    if (result.leaves) {
      leaves.value = result.leaves
    }
    if (result.entitlement) {
      entitlement.value = result.entitlement
    }
    showLeaveModal.value = false
    showToast(result.message || 'İzin kaydı kaydedildi.')
  } catch (err) {
    showToast(err.message || 'İzin kaydı kaydedilemedi.')
  } finally {
    leaveSaving.value = false
  }
}

async function deleteLeave(item) {
  if (!confirm(`'${item.izin_tipi_adi}' kaydını silmek istediğinize emin misiniz?`)) return
  try {
    const result = await personnelApi.deleteLeave(item.id, route.params.id, auth.state.csrfToken)
    if (result.leaves) {
      leaves.value = result.leaves
    }
    if (result.entitlement) {
      entitlement.value = result.entitlement
    }
    showToast(result.message || 'İzin kaydı silindi.')
  } catch (err) {
    showToast(err.message || 'İzin kaydı silinemedi.')
  }
}

async function quickApproveLeave(item) {
  try {
    const result = await personnelApi.approveOrRejectLeave(item.id, route.params.id, 'Onaylandı', null, auth.state.csrfToken)
    if (result.leaves) {
      leaves.value = result.leaves
    }
    if (result.entitlement) {
      entitlement.value = result.entitlement
    }
    showToast(result.message || 'İzin talebi onaylandı.')
  } catch (err) {
    showToast(err.message || 'İşlem gerçekleştirilemedi.')
  }
}

function openRejectLeaveModal(item) {
  approvalForm.id = item.id
  approvalForm.status = 'Reddedildi'
  approvalForm.note = ''
  showApprovalModal.value = true
}

async function submitApproval() {
  approvalSaving.value = true
  try {
    const result = await personnelApi.approveOrRejectLeave(
      approvalForm.id,
      route.params.id,
      approvalForm.status,
      approvalForm.note,
      auth.state.csrfToken
    )
    if (result.leaves) {
      leaves.value = result.leaves
    }
    if (result.entitlement) {
      entitlement.value = result.entitlement
    }
    showApprovalModal.value = false
    showToast(result.message || 'Durum güncellendi.')
  } catch (err) {
    showToast(err.message || 'İşlem gerçekleştirilemedi.')
  } finally {
    approvalSaving.value = false
  }
}

// ================= EK ÖDEME İŞLEMLERİ =================

function onEkOdemeParametreChange() {
  const param = gelirParametreleri.value.find(p => p.id === Number(ekOdemeForm.parametre_id))
  if (param) {
    ekOdemeForm.tur = param.kod || 'diger'
    if (param.hesaplama_tipi) {
      if (param.hesaplama_tipi.includes('oran')) {
        ekOdemeForm.hesaplama_tipi = 'oran_net'
        ekOdemeForm.oran = param.oran || ''
      } else {
        ekOdemeForm.hesaplama_tipi = 'sabit'
      }
    }
    if (param.varsayilan_tutar && !ekOdemeForm.tutar) {
      ekOdemeForm.tutar = param.varsayilan_tutar
    }
  }
}

function openNewEkOdemeModal() {
  const defaultDonem = bordroDonemleri.value.find(d => !d.kapali_mi)
  ekOdemeForm.id = 0
  ekOdemeForm.parametre_id = gelirParametreleri.value[0]?.id || ''
  ekOdemeForm.tur = gelirParametreleri.value[0]?.kod || 'diger'
  ekOdemeForm.tekrar_tipi = 'tek_sefer'
  ekOdemeForm.hesaplama_tipi = 'sabit'
  ekOdemeForm.tutar = ''
  ekOdemeForm.resmi_tutar = ''
  ekOdemeForm.oran = ''
  ekOdemeForm.tarih = new Date().toISOString().slice(0, 10)
  ekOdemeForm.donem_id = defaultDonem ? defaultDonem.id : ''
  ekOdemeForm.baslangic_donemi = new Date().toISOString().slice(0, 7) + '-01'
  ekOdemeForm.bitis_donemi = ''
  ekOdemeForm.banka_matrahina_ekle = 1
  ekOdemeForm.aciklama = ''
  onEkOdemeParametreChange()
  showEkOdemeModal.value = true
}

function openEditEkOdemeModal(item) {
  ekOdemeForm.id = item.id
  ekOdemeForm.parametre_id = item.parametre_id || ''
  ekOdemeForm.tur = item.tur || 'diger'
  ekOdemeForm.tekrar_tipi = item.tekrar_tipi || 'tek_sefer'
  ekOdemeForm.hesaplama_tipi = item.hesaplama_tipi || 'sabit'
  ekOdemeForm.tutar = item.tutar || ''
  ekOdemeForm.resmi_tutar = item.resmi_tutar || ''
  ekOdemeForm.oran = item.oran || ''
  ekOdemeForm.tarih = item.tarih || new Date().toISOString().slice(0, 10)
  ekOdemeForm.donem_id = item.donem_id || ''
  ekOdemeForm.baslangic_donemi = item.baslangic_donemi || (new Date().toISOString().slice(0, 7) + '-01')
  ekOdemeForm.bitis_donemi = item.bitis_donemi || ''
  ekOdemeForm.banka_matrahina_ekle = item.banka_matrahina_ekle !== undefined ? item.banka_matrahina_ekle : 1
  ekOdemeForm.aciklama = item.aciklama || ''
  showEkOdemeModal.value = true
}

async function submitEkOdeme() {
  if (ekOdemeForm.hesaplama_tipi === 'sabit' && (!ekOdemeForm.tutar || Number(ekOdemeForm.tutar) <= 0)) {
    showToast('Lütfen geçerli bir tutar giriniz.')
    return
  }
  if (ekOdemeForm.hesaplama_tipi !== 'sabit' && (!ekOdemeForm.oran || Number(ekOdemeForm.oran) <= 0)) {
    showToast('Lütfen geçerli bir oran giriniz.')
    return
  }
  if (ekOdemeForm.tekrar_tipi === 'tek_sefer' && !ekOdemeForm.donem_id) {
    showToast('Lütfen uygulanacak dönemi seçiniz.')
    return
  }

  ekOdemeSaving.value = true
  try {
    const payload = {
      ...ekOdemeForm,
      personel_id: route.params.id,
      tutar: Number(ekOdemeForm.tutar) || 0,
      resmi_tutar: Number(ekOdemeForm.resmi_tutar) || 0,
      oran: Number(ekOdemeForm.oran) || 0,
      banka_matrahina_ekle: Number(ekOdemeForm.banka_matrahina_ekle),
    }
    const result = await personnelApi.saveEkOdeme(payload, auth.state.csrfToken)
    if (result.ek_odemeler) ekOdemeler.value = result.ek_odemeler
    if (result.ek_odeme_ozet) ekOdemeOzet.value = result.ek_odeme_ozet
    showEkOdemeModal.value = false
    showToast(result.message || 'Ek ödeme kaydedildi.')
  } catch (err) {
    showToast(err.message || 'Ek ödeme kaydedilemedi.')
  } finally {
    ekOdemeSaving.value = false
  }
}

async function deleteEkOdeme(item) {
  if (!confirm(`'${item.parametre_adi}' kaydını silmek istediğinize emin misiniz?`)) return
  try {
    const result = await personnelApi.deleteEkOdeme(item.id, route.params.id, auth.state.csrfToken)
    if (result.ek_odemeler) ekOdemeler.value = result.ek_odemeler
    if (result.ek_odeme_ozet) ekOdemeOzet.value = result.ek_odeme_ozet
    showToast(result.message || 'Ek ödeme silindi.')
  } catch (err) {
    showToast(err.message || 'Ek ödeme silinemedi.')
  }
}

function openSonlandirEkOdemeModal(item) {
  ekOdemeSonlandirForm.id = item.id
  ekOdemeSonlandirForm.parametre_adi = item.parametre_adi
  ekOdemeSonlandirForm.bitis_donemi = new Date().toISOString().slice(0, 7) + '-01'
  showEkOdemeSonlandirModal.value = true
}

async function submitSonlandirEkOdeme() {
  if (!ekOdemeSonlandirForm.bitis_donemi) {
    showToast('Lütfen bitiş tarihi seçiniz.')
    return
  }
  try {
    const result = await personnelApi.sonlandirEkOdeme(
      ekOdemeSonlandirForm.id,
      route.params.id,
      ekOdemeSonlandirForm.bitis_donemi,
      auth.state.csrfToken
    )
    if (result.ek_odemeler) ekOdemeler.value = result.ek_odemeler
    if (result.ek_odeme_ozet) ekOdemeOzet.value = result.ek_odeme_ozet
    showEkOdemeSonlandirModal.value = false
    showToast(result.message || 'Sürekli ek ödeme sonlandırıldı.')
  } catch (err) {
    showToast(err.message || 'İşlem gerçekleştirilemedi.')
  }
}

// ================= KESİNTİ İŞLEMLERİ =================

function onKesintiParametreChange() {
  const param = kesintiParametreleri.value.find(p => p.id === Number(kesintiForm.parametre_id))
  if (param) {
    kesintiForm.tur = param.kod || 'diger'
    if (param.hesaplama_tipi) {
      if (param.hesaplama_tipi.includes('oran')) {
        kesintiForm.hesaplama_tipi = 'oran_net'
        kesintiForm.oran = param.oran || ''
      } else {
        kesintiForm.hesaplama_tipi = 'sabit'
      }
    }
    if (param.varsayilan_tutar && !kesintiForm.tutar) {
      kesintiForm.tutar = param.varsayilan_tutar
    }
  }
}

function openNewKesintiModal() {
  const defaultDonem = bordroDonemleri.value.find(d => !d.kapali_mi)
  kesintiForm.id = 0
  kesintiForm.parametre_id = kesintiParametreleri.value[0]?.id || ''
  kesintiForm.tur = kesintiParametreleri.value[0]?.kod || 'diger'
  kesintiForm.tekrar_tipi = 'tek_sefer'
  kesintiForm.hesaplama_tipi = 'sabit'
  kesintiForm.tutar = ''
  kesintiForm.oran = ''
  kesintiForm.taksit_sayisi = 1
  kesintiForm.tarih = new Date().toISOString().slice(0, 10)
  kesintiForm.donem_id = defaultDonem ? defaultDonem.id : ''
  kesintiForm.baslangic_donemi = new Date().toISOString().slice(0, 7) + '-01'
  kesintiForm.bitis_donemi = ''
  kesintiForm.banka_matrahina_ekle = 1
  kesintiForm.icra_id = ''
  kesintiForm.aciklama = ''
  onKesintiParametreChange()
  showKesintiModal.value = true
}

function openEditKesintiModal(item) {
  kesintiForm.id = item.id
  kesintiForm.parametre_id = item.parametre_id || ''
  kesintiForm.tur = item.tur || 'diger'
  kesintiForm.tekrar_tipi = item.tekrar_tipi || 'tek_sefer'
  kesintiForm.hesaplama_tipi = item.hesaplama_tipi || 'sabit'
  kesintiForm.tutar = item.tutar || ''
  kesintiForm.oran = item.oran || ''
  kesintiForm.taksit_sayisi = item.taksit_sayisi || 1
  kesintiForm.tarih = item.tarih || new Date().toISOString().slice(0, 10)
  kesintiForm.donem_id = item.donem_id || ''
  kesintiForm.baslangic_donemi = item.baslangic_donemi || (new Date().toISOString().slice(0, 7) + '-01')
  kesintiForm.bitis_donemi = item.bitis_donemi || ''
  kesintiForm.banka_matrahina_ekle = item.banka_matrahina_ekle !== undefined ? item.banka_matrahina_ekle : 1
  kesintiForm.icra_id = item.icra_id || ''
  kesintiForm.aciklama = item.aciklama || ''
  showKesintiModal.value = true
}

async function submitKesinti() {
  if (kesintiForm.hesaplama_tipi === 'sabit' && (!kesintiForm.tutar || Number(kesintiForm.tutar) <= 0)) {
    showToast('Lütfen geçerli bir tutar giriniz.')
    return
  }
  if (kesintiForm.hesaplama_tipi !== 'sabit' && (!kesintiForm.oran || Number(kesintiForm.oran) <= 0)) {
    showToast('Lütfen geçerli bir oran giriniz.')
    return
  }
  if ((kesintiForm.tekrar_tipi === 'tek_sefer' || kesintiForm.tekrar_tipi === 'taksitli') && !kesintiForm.donem_id) {
    showToast('Lütfen uygulanacak dönemi seçiniz.')
    return
  }

  kesintiSaving.value = true
  try {
    const payload = {
      ...kesintiForm,
      personel_id: route.params.id,
      tutar: Number(kesintiForm.tutar) || 0,
      oran: Number(kesintiForm.oran) || 0,
      taksit_sayisi: Number(kesintiForm.taksit_sayisi) || 1,
      banka_matrahina_ekle: Number(kesintiForm.banka_matrahina_ekle),
    }
    const result = await personnelApi.saveKesinti(payload, auth.state.csrfToken)
    if (result.kesintiler) kesintiler.value = result.kesintiler
    if (result.kesinti_ozet) kesintiOzet.value = result.kesinti_ozet
    showKesintiModal.value = false
    showToast(result.message || 'Kesinti kaydedildi.')
  } catch (err) {
    showToast(err.message || 'Kesinti kaydedilemedi.')
  } finally {
    kesintiSaving.value = false
  }
}

async function deleteKesinti(item) {
  if (!confirm(`'${item.parametre_adi}' kaydını silmek istediğinize emin misiniz?`)) return
  try {
    const result = await personnelApi.deleteKesinti(item.id, route.params.id, auth.state.csrfToken)
    if (result.kesintiler) kesintiler.value = result.kesintiler
    if (result.kesinti_ozet) kesintiOzet.value = result.kesinti_ozet
    showToast(result.message || 'Kesinti silindi.')
  } catch (err) {
    showToast(err.message || 'Kesinti silinemedi.')
  }
}

function openSonlandirKesintiModal(item) {
  kesintiSonlandirForm.id = item.id
  kesintiSonlandirForm.parametre_adi = item.parametre_adi
  kesintiSonlandirForm.bitis_donemi = new Date().toISOString().slice(0, 7) + '-01'
  showKesintiSonlandirModal.value = true
}

async function submitSonlandirKesinti() {
  if (!kesintiSonlandirForm.bitis_donemi) {
    showToast('Lütfen bitiş tarihi seçiniz.')
    return
  }
  try {
    const result = await personnelApi.sonlandirKesinti(
      kesintiSonlandirForm.id,
      route.params.id,
      kesintiSonlandirForm.bitis_donemi,
      auth.state.csrfToken
    )
    if (result.kesintiler) kesintiler.value = result.kesintiler
    if (result.kesinti_ozet) kesintiOzet.value = result.kesinti_ozet
    showKesintiSonlandirModal.value = false
    showToast(result.message || 'Sürekli kesinti sonlandırıldı.')
  } catch (err) {
    showToast(err.message || 'İşlem gerçekleştirilemedi.')
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
    if (result.leaves) {
      leaves.value = result.leaves
    }
    if (result.entitlement) {
      entitlement.value = result.entitlement
    }
    if (result.leave_types) {
      leaveTypes.value = result.leave_types
    }
    if (result.ek_odemeler) {
      ekOdemeler.value = result.ek_odemeler
    }
    if (result.ek_odeme_ozet) {
      ekOdemeOzet.value = result.ek_odeme_ozet
    }
    if (result.gelir_parametreleri) {
      gelirParametreleri.value = result.gelir_parametreleri
    }
    if (result.bordro_donemleri) {
      bordroDonemleri.value = result.bordro_donemleri
    }
    if (result.kesintiler) {
      kesintiler.value = result.kesintiler
    }
    if (result.kesinti_ozet) {
      kesintiOzet.value = result.kesinti_ozet
    }
    if (result.kesinti_parametreleri) {
      kesintiParametreleri.value = result.kesinti_parametreleri
    }
    if (result.icra_dosyalari) {
      icraDosyalari.value = result.icra_dosyalari
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

      <!-- ========================================================================= -->
      <!-- 5. İZİN VE RAPORLAR SEKME İÇERİĞİ (Masaüstü Standartlarında Tam Yönetim)   -->
      <!-- ========================================================================= -->
      <div v-else-if="route.params.section === 'izinler'" class="izinler-section-container">
        <!-- Hata Mesajı -->
        <div v-if="saveError" class="clay-alert-banner clay-alert-banner--danger">
          <AlertCircle :size="18" />
          <span>{{ saveError }}</span>
        </div>

        <!-- 1. YILLIK İZİN HAKEDİŞ VE BAKİYE BENTO KARTI -->
        <section class="bento-card bento-card--white leave-entitlement-card">
          <header class="group-header">
            <div class="group-header-left">
              <span class="group-icon group-icon--blue"><CalendarCheck :size="17" /></span>
              <div class="group-titles">
                <h3>Yıllık İzin Bakiyesi</h3>
                <small>Hakediş, kullanılan ve kalan izin günleri</small>
              </div>
            </div>
            <button
              v-if="entitlement.detay && entitlement.detay.length > 0"
              type="button"
              class="clay-btn clay-btn--sm clay-btn--secondary header-action-btn"
              @click="showEntitlementModal = true"
            >
              <History :size="13" />
              <span>Hakediş Detayı</span>
            </button>
          </header>

          <div class="entitlement-metrics-grid">
            <div class="metric-box metric-box--primary">
              <small>TOPLAM HAKEDİŞ</small>
              <strong>{{ entitlement.toplam_hakedis }} <span class="unit">Gün</span></strong>
              <div class="metric-subtext">Kanuni hakediş</div>
            </div>

            <div class="metric-box metric-box--coral">
              <small>KULLANILAN</small>
              <strong>{{ entitlement.kullanilan_izin }} <span class="unit">Gün</span></strong>
              <div class="metric-subtext">Düşülen izinler</div>
            </div>

            <div class="metric-box metric-box--emerald">
              <small>KALAN İZİN</small>
              <strong>{{ entitlement.kalan_izin }} <span class="unit">Gün</span></strong>
              <div class="metric-subtext">Mevcut bakiye</div>
            </div>
          </div>
        </section>

        <!-- 2. İZİN VE RAPOR HAREKETLERİ KARTI -->
        <section class="bento-card bento-card--white info-group-card">
          <header class="group-header">
            <div class="group-header-left">
              <span class="group-icon group-icon--amber"><CalendarDays :size="17" /></span>
              <div class="group-titles">
                <h3>İzin & Rapor Hareketleri</h3>
                <small>Kayıtlı izin, rapor ve mazeret geçmişi</small>
              </div>
            </div>
            <button
              v-if="canEdit"
              type="button"
              class="clay-btn clay-btn--sm clay-btn--primary header-action-btn"
              @click="openNewLeaveModal"
            >
              <Plus :size="14" />
              <span>Yeni İzin Ekle</span>
            </button>
          </header>

          <div v-if="!leaves || leaves.length === 0" class="empty-list-box">
            <p>Henüz kayıtlı bir izin veya rapor hareketi bulunmuyor.</p>
          </div>

          <div v-else class="leave-list-stack">
            <div
              v-for="l in leaves"
              :key="l.id"
              class="leave-item-card"
              :class="{
                'leave-item-card--approved': l.onay_durumu === 'Onaylandı',
                'leave-item-card--pending': l.onay_durumu === 'Beklemede',
                'leave-item-card--rejected': l.onay_durumu === 'Reddedildi'
              }"
            >
              <div class="leave-item-left">
                <div class="leave-title-row">
                  <strong>{{ l.izin_tipi_adi }}</strong>
                  <span
                    class="clay-badge"
                    :class="l.ucretli_mi ? 'clay-badge--cyan' : 'clay-badge--secondary'"
                  >
                    {{ l.ucretli_mi ? 'Ücretli' : 'Ücretsiz' }}
                  </span>
                  <span
                    class="clay-badge"
                    :class="{
                      'clay-badge--emerald': l.onay_durumu === 'Onaylandı',
                      'clay-badge--amber': l.onay_durumu === 'Beklemede',
                      'clay-badge--coral': l.onay_durumu === 'Reddedildi'
                    }"
                  >
                    {{ l.onay_durumu }}
                  </span>
                </div>

                <div class="leave-date-row">
                  <small>
                    <History :size="12" />
                    {{ formatDate(l.baslangic_tarihi) }} — {{ formatDate(l.bitis_tarihi) }}
                  </small>
                  <span class="leave-days-chip">{{ l.toplam_gun }} Gün</span>
                  <span v-if="l.yillik_izne_etki === 'Dus' || l.yillik_izne_etki === '1'" class="leave-chip leave-chip--dus">
                    Yıllık İzne Etki: Düşüldü
                  </span>
                </div>

                <p v-if="l.aciklama" class="leave-desc-text">{{ l.aciklama }}</p>

                <div v-if="l.onaylar && l.onaylar.length > 0" class="leave-approvers-box">
                  <small v-for="(o, idx) in l.onaylar" :key="idx" class="approver-info">
                    <UserRoundCheck :size="12" />
                    <span>{{ o.adi || 'Yetkili' }} ({{ formatDate(o.tarih.slice(0, 10)) }}) — {{ o.durum }}</span>
                  </small>
                </div>
              </div>

              <div v-if="canEdit" class="leave-item-actions">
                <!-- Beklemede ise hızlı onayla / reddet -->
                <button
                  v-if="l.onay_durumu === 'Beklemede'"
                  type="button"
                  class="action-icon-btn action-icon-btn--approve"
                  title="Onayla"
                  @click="quickApproveLeave(l)"
                >
                  <Check :size="14" />
                </button>
                <button
                  v-if="l.onay_durumu === 'Beklemede'"
                  type="button"
                  class="action-icon-btn action-icon-btn--reject"
                  title="Reddet"
                  @click="openRejectLeaveModal(l)"
                >
                  <X :size="14" />
                </button>

                <button
                  type="button"
                  class="copy-btn"
                  title="Düzenle"
                  @click="openEditLeaveModal(l)"
                >
                  <Pencil :size="13" />
                </button>
                <button
                  type="button"
                  class="copy-btn copy-btn--danger"
                  title="Sil"
                  @click="deleteLeave(l)"
                >
                  <Trash2 :size="13" />
                </button>
              </div>
            </div>
          </div>
        </section>

        <!-- =================== MODAL: İZİN EKLE / DÜZENLE =================== -->
        <div v-if="showLeaveModal" class="clay-modal-backdrop" @click.self="showLeaveModal = false">
          <div class="clay-modal-sheet">
            <header class="modal-sheet-header">
              <h4>{{ leaveForm.id ? 'İzin Kaydını Düzenle' : 'Yeni İzin & Rapor Ekle' }}</h4>
              <button type="button" class="close-btn" @click="showLeaveModal = false"><X :size="18" /></button>
            </header>

            <form class="modal-sheet-body" @submit.prevent="saveLeave">
              <label class="form-field">
                <span>İzin Türü *</span>
                <select v-model="leaveForm.izin_tipi_id" required @change="onLeaveTypeChange">
                  <option value="" disabled>İzin türü seçiniz</option>
                  <option
                    v-for="t in leaveTypes"
                    :key="t.id"
                    :value="t.id"
                  >
                    {{ t.tur_adi }} {{ t.ucretli_mi ? '(Ücretli)' : '(Ücretsiz)' }}
                  </option>
                </select>
              </label>

              <div class="form-grid">
                <label class="form-field">
                  <span>Başlangıç Tarihi *</span>
                  <input v-model="leaveForm.baslangic_tarihi" type="date" required @change="onLeaveDatesChange" />
                </label>

                <label class="form-field">
                  <span>Bitiş Tarihi *</span>
                  <input v-model="leaveForm.bitis_tarihi" type="date" required @change="onLeaveDatesChange" />
                </label>
              </div>

              <div class="form-grid">
                <label class="form-field">
                  <span>Süre (Gün) *</span>
                  <input v-model.number="leaveForm.toplam_gun" type="number" step="0.5" min="0.5" required />
                </label>

                <label class="form-field">
                  <span>Onay Durumu</span>
                  <select v-model="leaveForm.onay_durumu">
                    <option value="Onaylandı">Onaylandı</option>
                    <option value="Beklemede">Beklemede</option>
                    <option value="Reddedildi">Reddedildi</option>
                  </select>
                </label>
              </div>

              <label class="checkbox-field">
                <input v-model="leaveForm.yillik_izne_etki" type="checkbox" />
                <span>Yıllık izin bakiyesinden düşülsün</span>
              </label>

              <label class="form-field">
                <span>Açıklama (Opsiyonel)</span>
                <textarea v-model.trim="leaveForm.aciklama" rows="2" placeholder="İzin gerekçesi veya açıklaması"></textarea>
              </label>

              <div class="modal-actions mt-3">
                <button type="button" class="clay-btn clay-btn--secondary" @click="showLeaveModal = false">Vazgeç</button>
                <button type="submit" class="clay-btn clay-btn--primary" :disabled="leaveSaving">
                  {{ leaveSaving ? 'Kaydediliyor…' : (leaveForm.id ? 'Güncelle' : 'Kaydet') }}
                </button>
              </div>
            </form>
          </div>
        </div>

        <!-- =================== MODAL: HAKEDİŞ DETAYLARI =================== -->
        <div v-if="showEntitlementModal" class="clay-modal-backdrop" @click.self="showEntitlementModal = false">
          <div class="clay-modal-sheet">
            <header class="modal-sheet-header">
              <h4>Yıllık İzin Hakediş Detayları</h4>
              <button type="button" class="close-btn" @click="showEntitlementModal = false"><X :size="18" /></button>
            </header>

            <div class="modal-sheet-body">
              <div class="entitlement-table-wrap">
                <table class="entitlement-table">
                  <thead>
                    <tr>
                      <th>Hizmet Yılı</th>
                      <th>Hakediş Tarihi</th>
                      <th class="text-end">Gün</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="d in entitlement.detay" :key="d.yil">
                      <td><strong>{{ d.yil }}. Yıl</strong></td>
                      <td>{{ formatDate(d.hakedis_tarihi) }}</td>
                      <td class="text-end font-bold">{{ d.hakedis_gun }} Gün</td>
                    </tr>
                    <tr class="total-row">
                      <td colspan="2"><strong>Toplam Hakediş</strong></td>
                      <td class="text-end font-bold text-primary">{{ entitlement.toplam_hakedis }} Gün</td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <div class="modal-actions mt-3">
                <button type="button" class="clay-btn clay-btn--secondary w-100" @click="showEntitlementModal = false">Kapat</button>
              </div>
            </div>
          </div>
        </div>

        <!-- =================== MODAL: İZİN RET GEREKÇESİ =================== -->
        <div v-if="showApprovalModal" class="clay-modal-backdrop" @click.self="showApprovalModal = false">
          <div class="clay-modal-sheet">
            <header class="modal-sheet-header">
              <h4>İzin Talebini Reddet</h4>
              <button type="button" class="close-btn" @click="showApprovalModal = false"><X :size="18" /></button>
            </header>

            <form class="modal-sheet-body" @submit.prevent="submitApproval">
              <p class="text-muted font-size-13 mb-2">
                Bu izin talebini reddetmek istediğinize emin misiniz?
              </p>

              <label class="form-field">
                <span>Ret Nedeni (Opsiyonel)</span>
                <textarea v-model.trim="approvalForm.note" rows="2" placeholder="Gerekçe giriniz"></textarea>
              </label>

              <div class="modal-actions mt-3">
                <button type="button" class="clay-btn clay-btn--secondary" @click="showApprovalModal = false">İptal</button>
                <button type="submit" class="clay-btn clay-btn--danger" :disabled="approvalSaving">
                  {{ approvalSaving ? 'İşleniyor…' : 'Reddet' }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- ========================================================================= -->
      <!-- 6. EK ÖDEMELER SEKME İÇERİĞİ (Masaüstü Standartlarında Tam Yönetim)     -->
      <!-- ========================================================================= -->
      <div v-else-if="route.params.section === 'ek_odemeler'" class="ek-odemeler-section-container">
        <!-- 1. HERO OBSIDIAN BENTO KART: EK ÖDEME DAĞILIMI (Cari Takip Tarzı Minimal) -->
        <article class="cari-hero-volume-card">
          <header class="cari-hero-volume-card__header">
            <div class="cari-hero-volume-card__title">
              <span class="cari-hero-icon-box" style="color: #10b981;">
                <Coins :size="16" />
              </span>
              <div>
                <span class="cari-hero-tag">EK ÖDEME DAĞILIMI</span>
                <span class="cari-hero-subtitle">Ek Kazanç, Prim & Yardım Analizi</span>
              </div>
            </div>
            <button
              type="button"
              class="cari-hero-refresh-btn"
              title="Yenile"
              :class="{ 'is-spinning': loading }"
              @click="load"
            >
              <RefreshCw :size="13" />
            </button>
          </header>

          <div class="cari-hero-volume-main">
            <strong class="cari-hero-volume-amount">{{ formatMoney(ekOdemeOzet.toplam_tutar) }}</strong>
            <div class="cari-hero-balance-row">
              <span class="cari-hero-balance-label">Ödeme Kanalı:</span>
              <span class="text-emerald fw-bold">Banka: {{ formatMoney(ekOdemeOzet.banka_tutar) }}</span>
              <span class="text-white-50">·</span>
              <span class="text-sky fw-bold">Elden: {{ formatMoney(ekOdemeOzet.elden_tutar) }}</span>
            </div>
          </div>

          <!-- Segmentli İlerleme Çubuğu -->
          <div class="cari-chart-container">
            <div class="cari-segmented-track">
              <div
                class="cari-seg-fill cari-seg-fill--borc"
                :style="{ width: `${ekOdemeTekSeferRatio}%` }"
                :title="`Tek Sefer: %${ekOdemeTekSeferRatio}`"
              >
                <span v-if="ekOdemeTekSeferRatio >= 15" class="cari-seg-label">%{{ ekOdemeTekSeferRatio }}</span>
              </div>
              <div
                class="cari-seg-fill cari-seg-fill--alacak"
                :style="{ width: `${ekOdemeSurekliRatio}%` }"
                :title="`Sürekli: %${ekOdemeSurekliRatio}`"
              >
                <span v-if="ekOdemeSurekliRatio >= 15" class="cari-seg-label">%{{ ekOdemeSurekliRatio }}</span>
              </div>
            </div>

            <!-- Tıklanabilir Filtre / Lejant Kutuları (3'lü Grid) -->
            <div class="cari-chart-legend cari-chart-legend--3col">
              <div
                class="cari-legend-item"
                :class="{ 'cari-legend-item--active': ekOdemeTab === 'all' }"
                @click="ekOdemeTab = 'all'"
              >
                <span class="cari-legend-dot" style="background: #ffffff; box-shadow: 0 0 8px rgba(255,255,255,0.6);"></span>
                <div class="cari-legend-info">
                  <div class="cari-legend-header">
                    <span class="cari-legend-title">Tümü</span>
                  </div>
                  <strong class="cari-legend-val">{{ formatMoney(ekOdemeOzet.toplam_tutar) }}</strong>
                  <small class="cari-legend-count">{{ displayEkOdemeler.length }} kayıt</small>
                </div>
              </div>

              <div
                class="cari-legend-item"
                :class="{ 'cari-legend-item--active': ekOdemeTab === 'tek_sefer' }"
                @click="ekOdemeTab = 'tek_sefer'"
              >
                <span class="cari-legend-dot cari-legend-dot--borc"></span>
                <div class="cari-legend-info">
                  <div class="cari-legend-header">
                    <span class="cari-legend-title">Tek Sefer</span>
                    <b class="cari-legend-pct text-sky">%{{ ekOdemeTekSeferRatio }}</b>
                  </div>
                  <strong class="cari-legend-val">{{ formatMoney(ekOdemeOzet.tek_sefer_tutar) }}</strong>
                  <small class="cari-legend-count">{{ ekOdemeOzet.tek_sefer_adet }} adet</small>
                </div>
              </div>

              <div
                class="cari-legend-item"
                :class="{ 'cari-legend-item--active': ekOdemeTab === 'surekli' }"
                @click="ekOdemeTab = 'surekli'"
              >
                <span class="cari-legend-dot cari-legend-dot--alacak"></span>
                <div class="cari-legend-info">
                  <div class="cari-legend-header">
                    <span class="cari-legend-title">Sürekli</span>
                    <b class="cari-legend-pct text-emerald">%{{ ekOdemeSurekliRatio }}</b>
                  </div>
                  <strong class="cari-legend-val">{{ formatMoney(ekOdemeOzet.surekli_tutar) }}</strong>
                  <small class="cari-legend-count">{{ ekOdemeOzet.surekli_adet }} adet</small>
                </div>
              </div>
            </div>
          </div>
        </article>

        <!-- 2. BÖLÜM BAŞLIĞI (Canvas Üzerinde Yüzen - PersonnelDetailView Standardı) -->
        <div class="clay-section-head">
          <div class="clay-section-head-left">
            <h2>Ek Ödeme Hareketleri</h2>
            <span class="clay-pill-tag">{{ displayEkOdemeler.length }} Kayıt</span>
          </div>
          <button
            v-if="canEdit"
            type="button"
            class="clay-btn clay-btn--sm clay-btn--primary"
            @click="openNewEkOdemeModal"
          >
            <Plus :size="14" />
            <span>Yeni Ek Ödeme</span>
          </button>
        </div>

        <!-- 3. DÖNEM BAZLI FİLTRE ÇUBUĞU -->
        <div class="period-filter-strip">
          <div class="period-select-wrap">
            <CalendarDays :size="14" class="period-select-icon" />
            <select v-model="ekOdemeDonemFilter" class="period-select-input">
              <option value="all">Tüm Dönemler</option>
              <option
                v-for="d in bordroDonemleri"
                :key="d.id"
                :value="d.id"
              >
                {{ d.donem_adi }} {{ d.kapali_mi ? '(Kapalı)' : '(Açık)' }}
              </option>
            </select>
            <ChevronDown :size="14" class="period-select-arrow" />
          </div>
          <button
            v-if="ekOdemeDonemFilter !== 'all'"
            type="button"
            class="period-clear-btn"
            title="Dönem Filtresini Temizle"
            @click="ekOdemeDonemFilter = 'all'"
          >
            <X :size="13" />
            <span>Temizle</span>
          </button>
        </div>

        <!-- Boş Durum (PersonnelView clay-state-card Standardı) -->
        <div v-if="!displayEkOdemeler.length" class="clay-state-card">
          <Coins :size="28" />
          <strong>Kayıtlı Ek Ödeme Bulunamadı</strong>
          <p>Bu filtre veya dönemde personele ait kayıtlı bir ek ödeme hareketi bulunmuyor.</p>
        </div>

        <!-- 4. EK ÖDEME LİSTE KARTLARI -->
        <div v-else class="financial-cards-list">
          <template v-for="item in displayEkOdemeler" :key="item.id">
            <!-- 4.1. PUANTAJ PRİMLERİ GRUP KARTI (Dönem Bazlı Tek Kart) -->
            <article
              v-if="item.is_puantaj_group"
              class="financial-card financial-card--puantaj"
            >
              <div class="financial-avatar-wrap avatar-blue">
                <Coins :size="20" />
                <i class="dot-online"></i>
              </div>

              <div class="financial-info">
                <div class="financial-head-row">
                  <strong class="financial-name">Puantaj Primleri</strong>
                  <div class="financial-badge-group">
                    <span class="clay-badge clay-badge--blue">Tek Sefer</span>
                    <span class="clay-badge clay-badge--emerald">Banka</span>
                    <span class="clay-badge clay-badge--primary">{{ item.sub_items.length }} Kalem</span>
                    <span v-if="item.kapali_mi" class="clay-badge clay-badge--secondary">Kapalı</span>
                  </div>
                </div>

                <div class="financial-money-row">
                  <span class="financial-money-amount text-emerald">{{ formatMoney(item.tutar) }}</span>
                  <span class="financial-chip-badge">Puantaj Hakedişi</span>
                </div>

                <div class="financial-date-row">
                  <span class="financial-date-pill">
                    <CalendarDays :size="12" />
                    {{ item.donem_adi }}
                  </span>
                  <span class="financial-date-pill text-muted">
                    <History :size="12" />
                    {{ item.sub_items.length }} Kalem Puantaj
                  </span>
                </div>

                <!-- Açılır Puantaj Döküm Butonu -->
                <button
                  type="button"
                  class="puantaj-drawer-toggle"
                  @click="togglePuantajDetails(item.id)"
                >
                  <component :is="expandedPuantajGroups[item.id] ? ChevronUp : ChevronDown" :size="13" />
                  <span>{{ expandedPuantajGroups[item.id] ? 'Kalemleri Gizle' : 'Kalem Dökümünü İncele' }} ({{ item.sub_items.length }})</span>
                </button>

                <!-- Açılır Liste Dökümü -->
                <div v-if="expandedPuantajGroups[item.id]" class="puantaj-sub-drawer">
                  <div
                    v-for="sub in item.sub_items"
                    :key="sub.id"
                    class="puantaj-sub-row"
                  >
                    <div class="puantaj-sub-left">
                      <span class="puantaj-sub-bullet"></span>
                      <span class="puantaj-sub-title">{{ cleanPuantajDescription(sub.aciklama) }}</span>
                    </div>
                    <strong class="puantaj-sub-amount text-emerald">{{ formatMoney(sub.tutar) }}</strong>
                  </div>
                </div>
              </div>
            </article>

            <!-- 4.2. STANDART EK ÖDEME KARTI -->
            <article
              v-else
              class="financial-card"
            >
              <div
                class="financial-avatar-wrap"
                :class="item.tekrar_tipi === 'surekli' ? 'avatar-amber' : 'avatar-blue'"
              >
                <BadgeDollarSign v-if="item.tekrar_tipi === 'surekli'" :size="20" />
                <Coins v-else :size="20" />
                <i :class="item.aktif ? 'dot-online' : 'dot-offline'"></i>
              </div>

              <div class="financial-info">
                <div class="financial-head-row">
                  <strong class="financial-name">{{ item.parametre_adi }}</strong>
                  <div class="financial-badge-group">
                    <span
                      class="clay-badge"
                      :class="item.tekrar_tipi === 'surekli' ? 'clay-badge--amber' : 'clay-badge--blue'"
                    >
                      {{ item.tekrar_tipi === 'surekli' ? 'Sürekli' : 'Tek Sefer' }}
                    </span>
                    <span
                      class="clay-badge"
                      :class="item.banka_matrahina_ekle ? 'clay-badge--emerald' : 'clay-badge--warning'"
                    >
                      {{ item.banka_matrahina_ekle ? 'Banka' : 'Elden' }}
                    </span>
                    <span v-if="item.kapali_mi" class="clay-badge clay-badge--secondary">
                      Kapalı
                    </span>
                  </div>
                </div>

                <div class="financial-money-row">
                  <span class="financial-money-amount text-emerald">{{ formatMoney(item.tutar) }}</span>
                  <span v-if="item.resmi_tutar && item.resmi_tutar > 0" class="financial-resmi-text">
                    Resmi: {{ formatMoney(item.resmi_tutar) }}
                  </span>
                  <span class="financial-chip-badge">
                    {{ item.hesaplama_tipi === 'sabit' ? 'Sabit' : (item.oran ? `%${item.oran}` : 'Oran') }}
                  </span>
                </div>

                <div class="financial-date-row">
                  <span v-if="item.tekrar_tipi === 'tek_sefer'" class="financial-date-pill">
                    <CalendarDays :size="12" />
                    {{ item.donem_adi || 'Dönem belirtilmemiş' }}
                  </span>
                  <span v-else class="financial-date-pill">
                    <CalendarDays :size="12" />
                    {{ formatDate(item.baslangic_donemi) }} — {{ item.bitis_donemi ? formatDate(item.bitis_donemi) : 'Süresiz' }}
                  </span>
                  <span class="financial-date-pill text-muted">
                    <History :size="12" />
                    {{ formatDate(item.tarih) }}
                  </span>
                </div>

                <p v-if="item.aciklama" class="financial-note">
                  {{ item.aciklama }}
                </p>
              </div>

              <!-- Aksiyonlar (Sağ Taraf) -->
              <div v-if="canEdit" class="financial-actions">
                <button
                  v-if="!item.kapali_mi"
                  type="button"
                  class="action-pill-btn"
                  title="Düzenle"
                  @click.stop="openEditEkOdemeModal(item)"
                >
                  <Pencil :size="13" />
                </button>
                <button
                  v-if="item.tekrar_tipi === 'surekli' && item.aktif"
                  type="button"
                  class="action-pill-btn action-pill-btn--amber"
                  title="Sonlandır"
                  @click.stop="openSonlandirEkOdemeModal(item)"
                >
                  <Clock :size="13" />
                </button>
                <button
                  v-if="!item.kapali_mi"
                  type="button"
                  class="action-pill-btn action-pill-btn--danger"
                  title="Sil"
                  @click.stop="deleteEkOdeme(item)"
                >
                  <Trash2 :size="13" />
                </button>
              </div>
            </article>
          </template>
        </div>

        <!-- =================== MODAL: EK ÖDEME EKLE / DÜZENLE =================== -->
        <div v-if="showEkOdemeModal" class="clay-modal-backdrop" @click.self="showEkOdemeModal = false">
          <div class="clay-modal-sheet">
            <header class="modal-sheet-header">
              <h4>{{ ekOdemeForm.id ? 'Ek Ödeme Düzenle' : 'Yeni Ek Ödeme Ekle' }}</h4>
              <button type="button" class="close-btn" @click="showEkOdemeModal = false"><X :size="18" /></button>
            </header>

            <form class="modal-sheet-body" @submit.prevent="submitEkOdeme">
              <!-- Ek Ödeme Türü -->
              <label class="form-field">
                <span>Ek Ödeme Türü *</span>
                <select v-model="ekOdemeForm.parametre_id" required @change="onEkOdemeParametreChange">
                  <option value="" disabled>Ek ödeme türü seçiniz</option>
                  <option
                    v-for="p in gelirParametreleri"
                    :key="p.id"
                    :value="p.id"
                  >
                    {{ p.etiket }}
                  </option>
                </select>
              </label>

              <!-- Ödeme Tipi & Hesaplama Tipi (Segmented Switch) -->
              <div class="form-grid">
                <div class="form-field">
                  <span>Ödeme Tipi</span>
                  <div class="segmented-pills">
                    <button
                      type="button"
                      class="seg-btn"
                      :class="{ 'seg-btn--active': ekOdemeForm.tekrar_tipi === 'tek_sefer' }"
                      @click="ekOdemeForm.tekrar_tipi = 'tek_sefer'"
                    >
                      Tek Sefer
                    </button>
                    <button
                      type="button"
                      class="seg-btn"
                      :class="{ 'seg-btn--active': ekOdemeForm.tekrar_tipi === 'surekli' }"
                      @click="ekOdemeForm.tekrar_tipi = 'surekli'"
                    >
                      Sürekli
                    </button>
                  </div>
                </div>

                <div class="form-field">
                  <span>Hesaplama Tipi</span>
                  <div class="segmented-pills">
                    <button
                      type="button"
                      class="seg-btn"
                      :class="{ 'seg-btn--active': ekOdemeForm.hesaplama_tipi === 'sabit' }"
                      @click="ekOdemeForm.hesaplama_tipi = 'sabit'"
                    >
                      Sabit
                    </button>
                    <button
                      type="button"
                      class="seg-btn"
                      :class="{ 'seg-btn--active': ekOdemeForm.hesaplama_tipi === 'oran_net' }"
                      @click="ekOdemeForm.hesaplama_tipi = 'oran_net'"
                    >
                      Net %
                    </button>
                    <button
                      type="button"
                      class="seg-btn"
                      :class="{ 'seg-btn--active': ekOdemeForm.hesaplama_tipi === 'oran_brut' }"
                      @click="ekOdemeForm.hesaplama_tipi = 'oran_brut'"
                    >
                      Brüt %
                    </button>
                  </div>
                </div>
              </div>

              <!-- Dönem / Tarih Alanları -->
              <div v-if="ekOdemeForm.tekrar_tipi === 'tek_sefer'" class="form-field">
                <span>Uygulanacak Bordro Dönemi *</span>
                <select v-model="ekOdemeForm.donem_id" required>
                  <option value="" disabled>Dönem seçiniz</option>
                  <option
                    v-for="d in bordroDonemleri"
                    :key="d.id"
                    :value="d.id"
                    :disabled="Boolean(d.kapali_mi)"
                  >
                    {{ d.donem_adi }} {{ d.kapali_mi ? '(Kapalı)' : '' }}
                  </option>
                </select>
              </div>

              <div v-else class="form-grid">
                <label class="form-field">
                  <span>Başlangıç Tarihi *</span>
                  <input v-model="ekOdemeForm.baslangic_donemi" type="date" required />
                </label>
                <label class="form-field">
                  <span>Bitiş Tarihi (Opsiyonel)</span>
                  <input v-model="ekOdemeForm.bitis_donemi" type="date" />
                </label>
              </div>

              <!-- Tutar ve Resmi Tutar -->
              <div class="form-grid">
                <label class="form-field">
                  <span>Maaşa Ek Tutar (TL) *</span>
                  <input
                    v-model.number="ekOdemeForm.tutar"
                    type="number"
                    step="0.01"
                    min="0"
                    placeholder="0.00"
                    required
                  />
                </label>
                <label class="form-field">
                  <span>Resmi Alacağa Dahil Tutar (TL)</span>
                  <input
                    v-model.number="ekOdemeForm.resmi_tutar"
                    type="number"
                    step="0.01"
                    min="0"
                    placeholder="0.00"
                  />
                </label>
              </div>

              <div v-if="ekOdemeForm.hesaplama_tipi !== 'sabit'" class="form-field">
                <span>Hesaplama Oranı (%) *</span>
                <input
                  v-model.number="ekOdemeForm.oran"
                  type="number"
                  step="0.01"
                  min="0"
                  max="100"
                  placeholder="Örn: 10"
                  required
                />
              </div>

              <!-- Kayıt Tarihi & Ödeme Kanalı -->
              <div class="form-grid">
                <label class="form-field">
                  <span>İşlem Tarihi</span>
                  <input v-model="ekOdemeForm.tarih" type="date" required />
                </label>

                <div class="form-field">
                  <span>Ödeme Kanalı</span>
                  <div class="segmented-pills">
                    <button
                      type="button"
                      class="seg-btn"
                      :class="{ 'seg-btn--active': ekOdemeForm.banka_matrahina_ekle == 1 }"
                      @click="ekOdemeForm.banka_matrahina_ekle = 1"
                    >
                      Banka
                    </button>
                    <button
                      type="button"
                      class="seg-btn"
                      :class="{ 'seg-btn--active': ekOdemeForm.banka_matrahina_ekle == 0 }"
                      @click="ekOdemeForm.banka_matrahina_ekle = 0"
                    >
                      Elden
                    </button>
                  </div>
                </div>
              </div>

              <!-- Açıklama -->
              <label class="form-field">
                <span>Açıklama / Not</span>
                <textarea v-model.trim="ekOdemeForm.aciklama" rows="2" placeholder="Örn: Saha başarı primi, araç bakım desteği"></textarea>
              </label>

              <!-- Butonlar -->
              <div class="modal-actions mt-3">
                <button type="button" class="clay-btn clay-btn--secondary" @click="showEkOdemeModal = false">Vazgeç</button>
                <button type="submit" class="clay-btn clay-btn--primary" :disabled="ekOdemeSaving">
                  {{ ekOdemeSaving ? 'Kaydediliyor…' : (ekOdemeForm.id ? 'Güncelle' : 'Kaydet') }}
                </button>
              </div>
            </form>
          </div>
        </div>

        <!-- =================== MODAL: EK ÖDEME SONLANDIR =================== -->
        <div v-if="showEkOdemeSonlandirModal" class="clay-modal-backdrop" @click.self="showEkOdemeSonlandirModal = false">
          <div class="clay-modal-sheet">
            <header class="modal-sheet-header">
              <h4>Sürekli Ek Ödemeyi Sonlandır</h4>
              <button type="button" class="close-btn" @click="showEkOdemeSonlandirModal = false"><X :size="18" /></button>
            </header>

            <form class="modal-sheet-body" @submit.prevent="submitSonlandirEkOdeme">
              <p class="text-muted font-size-13 mb-3">
                <strong>{{ ekOdemeSonlandirForm.parametre_adi }}</strong> için sürekli ödemeyi hangi tarihten itibaren sonlandırmak istiyorsunuz?
              </p>

              <label class="form-field">
                <span>Bitiş Tarihi *</span>
                <input v-model="ekOdemeSonlandirForm.bitis_donemi" type="date" required />
              </label>

              <div class="modal-actions mt-3">
                <button type="button" class="clay-btn clay-btn--secondary" @click="showEkOdemeSonlandirModal = false">Vazgeç</button>
                <button type="submit" class="clay-btn clay-btn--warning">Sonlandır</button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- ========================================================================= -->
      <!-- 7. KESİNTİLER SEKME İÇERİĞİ (Masaüstü Standartlarında Tam Yönetim)       -->
      <!-- ========================================================================= -->
      <div v-else-if="route.params.section === 'kesintiler'" class="kesintiler-section-container">
        <!-- 1. HERO OBSIDIAN BENTO KART: KESİNTİ DAĞILIMI (Cari Takip Tarzı Minimal) -->
        <article class="cari-hero-volume-card">
          <header class="cari-hero-volume-card__header">
            <div class="cari-hero-volume-card__title">
              <span class="cari-hero-icon-box" style="color: #fb7185;">
                <Receipt :size="16" />
              </span>
              <div>
                <span class="cari-hero-tag">KESİNTİ DAĞILIMI</span>
                <span class="cari-hero-subtitle">Dönemsel, Taksitli & Yasal Mahsuplar</span>
              </div>
            </div>
            <button
              type="button"
              class="cari-hero-refresh-btn"
              title="Yenile"
              :class="{ 'is-spinning': loading }"
              @click="load"
            >
              <RefreshCw :size="13" />
            </button>
          </header>

          <div class="cari-hero-volume-main">
            <strong class="cari-hero-volume-amount">{{ formatMoney(kesintiOzet.toplam_tutar) }}</strong>
            <div class="cari-hero-balance-row">
              <span class="cari-hero-balance-label">Ödeme Kanalı:</span>
              <span class="text-emerald fw-bold">Banka: {{ formatMoney(kesintiOzet.banka_tutar) }}</span>
              <span class="text-white-50">·</span>
              <span class="text-coral fw-bold">Elden: {{ formatMoney(kesintiOzet.elden_tutar) }}</span>
            </div>
          </div>

          <!-- Segmentli İlerleme Çubuğu -->
          <div class="cari-chart-container">
            <div class="cari-segmented-track">
              <div
                class="cari-seg-fill cari-seg-fill--borc"
                :style="{ width: `${kesintiTekSeferRatio}%` }"
                :title="`Tek Sefer: %${kesintiTekSeferRatio}`"
              >
                <span v-if="kesintiTekSeferRatio >= 15" class="cari-seg-label">%{{ kesintiTekSeferRatio }}</span>
              </div>
              <div
                class="cari-seg-fill cari-seg-fill--purple"
                :style="{ width: `${kesintiTaksitliRatio}%` }"
                :title="`Taksitli: %${kesintiTaksitliRatio}`"
              >
                <span v-if="kesintiTaksitliRatio >= 15" class="cari-seg-label">%{{ kesintiTaksitliRatio }}</span>
              </div>
              <div
                class="cari-seg-fill cari-seg-fill--coral"
                :style="{ width: `${kesintiSurekliRatio}%` }"
                :title="`Sürekli: %${kesintiSurekliRatio}`"
              >
                <span v-if="kesintiSurekliRatio >= 15" class="cari-seg-label">%{{ kesintiSurekliRatio }}</span>
              </div>
            </div>

            <!-- Tıklanabilir Filtre / Lejant Kutuları (2x2 Grid) -->
            <div class="cari-chart-legend cari-chart-legend--2x2">
              <div
                class="cari-legend-item"
                :class="{ 'cari-legend-item--active': kesintiTab === 'all' }"
                @click="kesintiTab = 'all'"
              >
                <span class="cari-legend-dot" style="background: #ffffff; box-shadow: 0 0 8px rgba(255,255,255,0.6);"></span>
                <div class="cari-legend-info">
                  <div class="cari-legend-header">
                    <span class="cari-legend-title">Tümü</span>
                  </div>
                  <strong class="cari-legend-val">{{ formatMoney(kesintiOzet.toplam_tutar) }}</strong>
                  <small class="cari-legend-count">{{ filteredKesintiler.length }} kayıt</small>
                </div>
              </div>

              <div
                class="cari-legend-item"
                :class="{ 'cari-legend-item--active': kesintiTab === 'tek_sefer' }"
                @click="kesintiTab = 'tek_sefer'"
              >
                <span class="cari-legend-dot cari-legend-dot--borc"></span>
                <div class="cari-legend-info">
                  <div class="cari-legend-header">
                    <span class="cari-legend-title">Tek Sefer</span>
                    <b class="cari-legend-pct text-sky">%{{ kesintiTekSeferRatio }}</b>
                  </div>
                  <strong class="cari-legend-val">{{ formatMoney(kesintiOzet.tek_sefer_tutar) }}</strong>
                  <small class="cari-legend-count">{{ kesintiOzet.tek_sefer_adet }} adet</small>
                </div>
              </div>

              <div
                class="cari-legend-item"
                :class="{ 'cari-legend-item--active': kesintiTab === 'taksitli' }"
                @click="kesintiTab = 'taksitli'"
              >
                <span class="cari-legend-dot cari-legend-dot--purple"></span>
                <div class="cari-legend-info">
                  <div class="cari-legend-header">
                    <span class="cari-legend-title">Taksitli</span>
                    <b class="cari-legend-pct text-purple">%{{ kesintiTaksitliRatio }}</b>
                  </div>
                  <strong class="cari-legend-val">{{ formatMoney(kesintiOzet.taksitli_tutar) }}</strong>
                  <small class="cari-legend-count">{{ kesintiOzet.taksitli_adet }} adet</small>
                </div>
              </div>

              <div
                class="cari-legend-item"
                :class="{ 'cari-legend-item--active': kesintiTab === 'surekli' }"
                @click="kesintiTab = 'surekli'"
              >
                <span class="cari-legend-dot cari-legend-dot--coral"></span>
                <div class="cari-legend-info">
                  <div class="cari-legend-header">
                    <span class="cari-legend-title">Sürekli</span>
                    <b class="cari-legend-pct text-coral">%{{ kesintiSurekliRatio }}</b>
                  </div>
                  <strong class="cari-legend-val">{{ formatMoney(kesintiOzet.surekli_tutar) }}</strong>
                  <small class="cari-legend-count">{{ kesintiOzet.surekli_adet }} adet</small>
                </div>
              </div>
            </div>
          </div>
        </article>

        <!-- 2. BÖLÜM BAŞLIĞI (Canvas Üzerinde Yüzen - PersonnelDetailView Standardı) -->
        <div class="clay-section-head">
          <div class="clay-section-head-left">
            <h2>Kesinti Hareketleri</h2>
            <span class="clay-pill-tag">{{ filteredKesintiler.length }} Kayıt</span>
          </div>
          <button
            v-if="canEdit"
            type="button"
            class="clay-btn clay-btn--sm clay-btn--primary"
            @click="openNewKesintiModal"
          >
            <Plus :size="14" />
            <span>Yeni Kesinti Ekle</span>
          </button>
        </div>

        <!-- 3. DÖNEM BAZLI FİLTRE ÇUBUĞU -->
        <div class="period-filter-strip">
          <div class="period-select-wrap">
            <CalendarDays :size="14" class="period-select-icon" />
            <select v-model="kesintiDonemFilter" class="period-select-input">
              <option value="all">Tüm Dönemler</option>
              <option
                v-for="d in bordroDonemleri"
                :key="d.id"
                :value="d.id"
              >
                {{ d.donem_adi }} {{ d.kapali_mi ? '(Kapalı)' : '(Açık)' }}
              </option>
            </select>
            <ChevronDown :size="14" class="period-select-arrow" />
          </div>
          <button
            v-if="kesintiDonemFilter !== 'all'"
            type="button"
            class="period-clear-btn"
            title="Dönem Filtresini Temizle"
            @click="kesintiDonemFilter = 'all'"
          >
            <X :size="13" />
            <span>Temizle</span>
          </button>
        </div>

        <!-- Boş Durum (PersonnelView clay-state-card Standardı) -->
        <div v-if="!filteredKesintiler.length" class="clay-state-card">
          <Receipt :size="28" />
          <strong>Kayıtlı Kesinti Bulunamadı</strong>
          <p>Bu filtrede veya personele ait kayıtlı bir kesinti hareketi bulunmuyor.</p>
        </div>

        <!-- 3. KESİNTİ LİSTE KARTLARI (Canvas Üzerinde Bağımsız Beyaz Bento Kartları - PersonnelView Standardı) -->
        <div v-else class="financial-cards-list">
          <article
            v-for="item in filteredKesintiler"
            :key="item.id"
            class="financial-card"
          >
            <div
              class="financial-avatar-wrap"
              :class="{
                'avatar-blue': item.tekrar_tipi === 'tek_sefer',
                'avatar-purple': item.tekrar_tipi === 'taksitli',
                'avatar-coral': item.tekrar_tipi === 'surekli'
              }"
            >
              <CreditCard v-if="item.tekrar_tipi === 'taksitli'" :size="20" />
              <Gavel v-else-if="item.dosya_no" :size="20" />
              <Receipt v-else :size="20" />
              <i :class="item.aktif ? 'dot-online' : 'dot-offline'"></i>
            </div>

            <div class="financial-info">
              <div class="financial-head-row">
                <strong class="financial-name">{{ item.parametre_adi }}</strong>
                <div class="financial-badge-group">
                  <span
                    class="clay-badge"
                    :class="{
                      'clay-badge--blue': item.tekrar_tipi === 'tek_sefer',
                      'clay-badge--amber': item.tekrar_tipi === 'taksitli',
                      'clay-badge--coral': item.tekrar_tipi === 'surekli'
                    }"
                  >
                    {{ item.tekrar_tipi === 'taksitli' ? `${item.taksit_sayisi || 1} Taksit` : (item.tekrar_tipi === 'surekli' ? 'Sürekli' : 'Tek Sefer') }}
                  </span>
                  <span
                    class="clay-badge"
                    :class="item.banka_matrahina_ekle ? 'clay-badge--emerald' : 'clay-badge--warning'"
                  >
                    {{ item.banka_matrahina_ekle ? 'Banka' : 'Elden' }}
                  </span>
                  <span v-if="item.kapali_mi" class="clay-badge clay-badge--secondary">
                    Kapalı
                  </span>
                </div>
              </div>

              <div class="financial-money-row">
                <span class="financial-money-amount text-coral">{{ formatMoney(item.tutar) }}</span>
                <span v-if="item.tekrar_tipi === 'taksitli' && item.taksit_sayisi > 1" class="financial-resmi-text">
                  Aylık: ~{{ formatMoney(item.tutar / item.taksit_sayisi) }}
                </span>
                <span class="financial-chip-badge">
                  {{ item.hesaplama_tipi === 'sabit' ? 'Sabit' : (item.oran ? `%${item.oran}` : 'Oran') }}
                </span>
              </div>

              <div class="financial-date-row">
                <span v-if="item.tekrar_tipi === 'tek_sefer' || item.tekrar_tipi === 'taksitli'" class="financial-date-pill">
                  <CalendarDays :size="12" />
                  {{ item.donem_adi || 'Dönem belirtilmemiş' }}
                </span>
                <span v-else class="financial-date-pill">
                  <CalendarDays :size="12" />
                  {{ formatDate(item.baslangic_donemi) }} — {{ item.bitis_donemi ? formatDate(item.bitis_donemi) : 'Süresiz' }}
                </span>
                <span v-if="item.dosya_no" class="financial-date-pill financial-date-pill--icra">
                  <Gavel :size="12" />
                  İcra: {{ item.dosya_no }}
                </span>
                <span class="financial-date-pill text-muted">
                  <History :size="12" />
                  {{ formatDate(item.tarih) }}
                </span>
              </div>

              <p v-if="item.aciklama" class="financial-note">
                {{ item.aciklama }}
              </p>
            </div>

            <!-- Aksiyonlar (Sağ Taraf) -->
            <div v-if="canEdit" class="financial-actions">
              <button
                v-if="!item.kapali_mi"
                type="button"
                class="action-pill-btn"
                title="Düzenle"
                @click.stop="openEditKesintiModal(item)"
              >
                <Pencil :size="13" />
              </button>
              <button
                v-if="item.tekrar_tipi === 'surekli' && item.aktif"
                type="button"
                class="action-pill-btn action-pill-btn--amber"
                title="Sonlandır"
                @click.stop="openSonlandirKesintiModal(item)"
              >
                <Clock :size="13" />
              </button>
              <button
                v-if="!item.kapali_mi"
                type="button"
                class="action-pill-btn action-pill-btn--danger"
                title="Sil"
                @click.stop="deleteKesinti(item)"
              >
                <Trash2 :size="13" />
              </button>
            </div>
          </article>
        </div>

        <!-- =================== MODAL: KESİNTİ EKLE / DÜZENLE =================== -->
        <div v-if="showKesintiModal" class="clay-modal-backdrop" @click.self="showKesintiModal = false">
          <div class="clay-modal-sheet">
            <header class="modal-sheet-header">
              <h4>{{ kesintiForm.id ? 'Kesintiyi Düzenle' : 'Yeni Kesinti Ekle' }}</h4>
              <button type="button" class="close-btn" @click="showKesintiModal = false"><X :size="18" /></button>
            </header>

            <form class="modal-sheet-body" @submit.prevent="submitKesinti">
              <!-- Kesinti Türü -->
              <label class="form-field">
                <span>Kesinti Türü *</span>
                <select v-model="kesintiForm.parametre_id" required @change="onKesintiParametreChange">
                  <option value="" disabled>Kesinti türü seçiniz</option>
                  <option
                    v-for="p in kesintiParametreleri"
                    :key="p.id"
                    :value="p.id"
                  >
                    {{ p.etiket }}
                  </option>
                </select>
              </label>

              <!-- İcra Dosyası Seçimi (Opsiyonel / İcra dosyası varsa) -->
              <div v-if="icraDosyalari && icraDosyalari.length > 0" class="form-field">
                <span>İcra Dosyası Bağlantısı (Opsiyonel)</span>
                <select v-model="kesintiForm.icra_id">
                  <option value="">Dosya seçilmedi</option>
                  <option
                    v-for="ic in icraDosyalari"
                    :key="ic.id"
                    :value="ic.id"
                  >
                    {{ ic.dosya_no }} - {{ ic.icra_dairesi }} (Toplam: {{ formatMoney(ic.toplam_borc) }})
                  </option>
                </select>
              </div>

              <!-- Kesinti Tipi & Hesaplama Tipi -->
              <div class="form-grid">
                <div class="form-field">
                  <span>Kesinti Tipi</span>
                  <div class="segmented-pills">
                    <button
                      type="button"
                      class="seg-btn"
                      :class="{ 'seg-btn--active': kesintiForm.tekrar_tipi === 'tek_sefer' }"
                      @click="kesintiForm.tekrar_tipi = 'tek_sefer'"
                    >
                      Tek Sefer
                    </button>
                    <button
                      type="button"
                      class="seg-btn"
                      :class="{ 'seg-btn--active': kesintiForm.tekrar_tipi === 'taksitli' }"
                      @click="kesintiForm.tekrar_tipi = 'taksitli'"
                    >
                      Taksitli
                    </button>
                    <button
                      type="button"
                      class="seg-btn"
                      :class="{ 'seg-btn--active': kesintiForm.tekrar_tipi === 'surekli' }"
                      @click="kesintiForm.tekrar_tipi = 'surekli'"
                    >
                      Sürekli
                    </button>
                  </div>
                </div>

                <div class="form-field">
                  <span>Hesaplama Tipi</span>
                  <div class="segmented-pills">
                    <button
                      type="button"
                      class="seg-btn"
                      :class="{ 'seg-btn--active': kesintiForm.hesaplama_tipi === 'sabit' }"
                      @click="kesintiForm.hesaplama_tipi = 'sabit'"
                    >
                      Sabit
                    </button>
                    <button
                      type="button"
                      class="seg-btn"
                      :class="{ 'seg-btn--active': kesintiForm.hesaplama_tipi === 'oran_net' }"
                      @click="kesintiForm.hesaplama_tipi = 'oran_net'"
                    >
                      Net %
                    </button>
                    <button
                      type="button"
                      class="seg-btn"
                      :class="{ 'seg-btn--active': kesintiForm.hesaplama_tipi === 'oran_brut' }"
                      @click="kesintiForm.hesaplama_tipi = 'oran_brut'"
                    >
                      Brüt %
                    </button>
                  </div>
                </div>
              </div>

              <!-- Taksit Sayısı (Taksitli ise) -->
              <div v-if="kesintiForm.tekrar_tipi === 'taksitli'" class="form-field">
                <span>Taksit Sayısı *</span>
                <input
                  v-model.number="kesintiForm.taksit_sayisi"
                  type="number"
                  min="1"
                  max="60"
                  placeholder="Örn: 6"
                  required
                />
              </div>

              <!-- Dönem / Tarih Alanları -->
              <div v-if="kesintiForm.tekrar_tipi === 'tek_sefer' || kesintiForm.tekrar_tipi === 'taksitli'" class="form-field">
                <span>Uygulanacak Bordro Dönemi *</span>
                <select v-model="kesintiForm.donem_id" required>
                  <option value="" disabled>Dönem seçiniz</option>
                  <option
                    v-for="d in bordroDonemleri"
                    :key="d.id"
                    :value="d.id"
                    :disabled="Boolean(d.kapali_mi)"
                  >
                    {{ d.donem_adi }} {{ d.kapali_mi ? '(Kapalı)' : '' }}
                  </option>
                </select>
              </div>

              <div v-else class="form-grid">
                <label class="form-field">
                  <span>Başlangıç Tarihi *</span>
                  <input v-model="kesintiForm.baslangic_donemi" type="date" required />
                </label>
                <label class="form-field">
                  <span>Bitiş Tarihi (Opsiyonel)</span>
                  <input v-model="kesintiForm.bitis_donemi" type="date" />
                </label>
              </div>

              <!-- Tutar veya Oran -->
              <div class="form-grid">
                <label class="form-field">
                  <span>Kesinti Tutarı (TL) *</span>
                  <input
                    v-model.number="kesintiForm.tutar"
                    type="number"
                    step="0.01"
                    min="0"
                    placeholder="0.00"
                    required
                  />
                </label>

                <label v-if="kesintiForm.hesaplama_tipi !== 'sabit'" class="form-field">
                  <span>Hesaplama Oranı (%) *</span>
                  <input
                    v-model.number="kesintiForm.oran"
                    type="number"
                    step="0.01"
                    min="0"
                    max="100"
                    placeholder="Örn: 5"
                    required
                  />
                </label>
              </div>

              <!-- İşlem Tarihi & Kesinti Kanalı -->
              <div class="form-grid">
                <label class="form-field">
                  <span>İşlem Tarihi</span>
                  <input v-model="kesintiForm.tarih" type="date" required />
                </label>

                <div class="form-field">
                  <span>Kesinti Kanalı</span>
                  <div class="segmented-pills">
                    <button
                      type="button"
                      class="seg-btn"
                      :class="{ 'seg-btn--active': kesintiForm.banka_matrahina_ekle == 1 }"
                      @click="kesintiForm.banka_matrahina_ekle = 1"
                    >
                      Banka
                    </button>
                    <button
                      type="button"
                      class="seg-btn"
                      :class="{ 'seg-btn--active': kesintiForm.banka_matrahina_ekle == 0 }"
                      @click="kesintiForm.banka_matrahina_ekle = 0"
                    >
                      Elden
                    </button>
                  </div>
                </div>
              </div>

              <!-- Açıklama -->
              <label class="form-field">
                <span>Açıklama / Not</span>
                <textarea v-model.trim="kesintiForm.aciklama" rows="2" placeholder="Örn: Avans kesintisi, telefon borcu"></textarea>
              </label>

              <!-- Butonlar -->
              <div class="modal-actions mt-3">
                <button type="button" class="clay-btn clay-btn--secondary" @click="showKesintiModal = false">Vazgeç</button>
                <button type="submit" class="clay-btn clay-btn--danger" :disabled="kesintiSaving">
                  {{ kesintiSaving ? 'Kaydediliyor…' : (kesintiForm.id ? 'Güncelle' : 'Kaydet') }}
                </button>
              </div>
            </form>
          </div>
        </div>

        <!-- =================== MODAL: KESİNTİ SONLANDIR =================== -->
        <div v-if="showKesintiSonlandirModal" class="clay-modal-backdrop" @click.self="showKesintiSonlandirModal = false">
          <div class="clay-modal-sheet">
            <header class="modal-sheet-header">
              <h4>Sürekli Kesintiyi Sonlandır</h4>
              <button type="button" class="close-btn" @click="showKesintiSonlandirModal = false"><X :size="18" /></button>
            </header>

            <form class="modal-sheet-body" @submit.prevent="submitSonlandirKesinti">
              <p class="text-muted font-size-13 mb-3">
                <strong>{{ kesintiSonlandirForm.parametre_adi }}</strong> için sürekli kesintiyi hangi tarihten itibaren sonlandırmak istiyorsunuz?
              </p>

              <label class="form-field">
                <span>Bitiş Tarihi *</span>
                <input v-model="kesintiSonlandirForm.bitis_donemi" type="date" required />
              </label>

              <div class="modal-actions mt-3">
                <button type="button" class="clay-btn clay-btn--secondary" @click="showKesintiSonlandirModal = false">Vazgeç</button>
                <button type="submit" class="clay-btn clay-btn--warning">Sonlandır</button>
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
.group-icon--coral { background: #e11d48; }

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

.copy-btn--amber {
  color: #d97706;
}

.copy-btn--amber:hover {
  background: rgba(245, 158, 11, 0.1);
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

/* ================= İzin ve Raporlar Bölüm Stilleri ================= */
.izinler-section-container {
  display: flex;
  flex-direction: column;
  gap: var(--ds-card-gap, 16px);
}

.leave-entitlement-card {
  padding: 18px;
}

.entitlement-metrics-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 10px;
}

.metric-box {
  display: flex;
  flex-direction: column;
  gap: 3px;
  padding: 12px 10px;
  border-radius: var(--ds-radius-sm, 14px);
  text-align: center;
}

.metric-box small {
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.metric-box strong {
  font-size: 18px;
  font-weight: 900;
  color: var(--ds-ink);
  line-height: 1.2;
}

.metric-box strong .unit {
  font-size: 11px;
  font-weight: 700;
  color: var(--ds-muted);
}

.metric-box .metric-subtext {
  font-size: 10px;
  color: var(--ds-muted);
  font-weight: 600;
}

.metric-box--primary {
  background: rgba(10, 132, 255, 0.08);
  border: 1px solid rgba(10, 132, 255, 0.15);
}
.metric-box--primary small { color: #0a84ff; }

.metric-box--coral {
  background: rgba(239, 68, 68, 0.08);
  border: 1px solid rgba(239, 68, 68, 0.15);
}
.metric-box--coral small { color: #ef4444; }

.metric-box--emerald {
  background: rgba(16, 185, 129, 0.08);
  border: 1px solid rgba(16, 185, 129, 0.15);
}
.metric-box--emerald small { color: #10b981; }

.metric-box--amber {
  background: rgba(245, 158, 11, 0.08);
  border: 1px solid rgba(245, 158, 11, 0.18);
}
.metric-box--amber small { color: #d97706; }

.leave-list-stack {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.leave-item-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 12px 14px;
  border-radius: var(--ds-radius-sm, 14px);
  background: var(--ds-surface-subtle);
  border: 1px solid var(--ds-border, rgba(0, 0, 0, 0.05));
  transition: transform 0.15s ease;
}

.leave-item-card--approved {
  border-left: 3.5px solid #10b981;
}

.leave-item-card--pending {
  border-left: 3.5px solid #f59e0b;
  background: rgba(245, 158, 11, 0.03);
}

.leave-item-card--rejected {
  border-left: 3.5px solid #ef4444;
  opacity: 0.85;
}

.leave-item-left {
  flex: 1;
  min-width: 0;
}

.leave-title-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  margin-bottom: 4px;
}

.leave-title-row strong {
  font-size: 13.5px;
  font-weight: 800;
  color: var(--ds-ink);
}

.leave-date-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
  margin-top: 4px;
}

.leave-date-row small {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  color: var(--ds-muted);
}

.leave-days-chip {
  display: inline-block;
  padding: 2px 7px;
  border-radius: 999px;
  font-size: 10.5px;
  font-weight: 800;
  background: var(--ds-primary, #0a84ff);
  color: #fff;
}

.leave-chip {
  display: inline-block;
  padding: 2px 7px;
  border-radius: 999px;
  font-size: 10px;
  font-weight: 700;
}

.leave-chip--dus {
  background: rgba(239, 68, 68, 0.1);
  color: #ef4444;
  border: 1px solid rgba(239, 68, 68, 0.2);
}

.leave-desc-text {
  margin: 6px 0 0;
  font-size: 12px;
  color: var(--ds-ink);
  line-height: 1.4;
}

.leave-approvers-box {
  margin-top: 6px;
  padding-top: 5px;
  border-top: 1px dashed var(--ds-border, rgba(0, 0, 0, 0.07));
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.approver-info {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 10.5px;
  color: var(--ds-muted);
}

.leave-item-actions {
  display: flex;
  align-items: center;
  gap: 6px;
}

.action-icon-btn {
  display: grid;
  width: 28px;
  height: 28px;
  place-items: center;
  border-radius: 8px;
  border: none;
  cursor: pointer;
  transition: transform 0.15s ease;
}

.action-icon-btn:active {
  transform: scale(0.92);
}

.action-icon-btn--approve {
  background: rgba(16, 185, 129, 0.14);
  color: #10b981;
}

.action-icon-btn--reject {
  background: rgba(239, 68, 68, 0.14);
  color: #ef4444;
}

.entitlement-table-wrap {
  border-radius: var(--ds-radius-sm, 14px);
  border: 1px solid var(--ds-border, rgba(0, 0, 0, 0.08));
  overflow: hidden;
  background: var(--ds-surface);
}

.entitlement-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 12.5px;
}

.entitlement-table th,
.entitlement-table td {
  padding: 10px 12px;
  border-bottom: 1px solid var(--ds-border, rgba(0, 0, 0, 0.05));
  color: var(--ds-ink);
}

.entitlement-table th {
  background: var(--ds-surface-subtle);
  font-size: 11px;
  font-weight: 800;
  color: var(--ds-muted);
  text-transform: uppercase;
}

.entitlement-table .total-row td {
  background: var(--ds-surface-subtle);
  border-bottom: none;
}

/* ================= Ek Ödemeler ve Kesintiler Bölüm Stilleri ================= */
.ek-odemeler-section-container,
.kesintiler-section-container {
  display: flex;
  flex-direction: column;
  gap: var(--ds-card-gap, 16px);
}

/* Hero Dağılım Kartı 4'lü Stat Pills (Kesintiler İçin 2x2 Grid) */
.kesinti-stat-pills {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 8px;
}

.dot-amber  { background: var(--ds-amber, #f59e0b); }
.dot-purple { background: var(--ds-purple, #a855f7); }

.clay-section-head-left {
  display: flex;
  align-items: center;
  gap: 8px;
}

/* Canvas Üzerinde Bağımsız Bento Kartları Listesi */
.financial-cards-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.financial-card {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 14px 16px;
  border-radius: var(--ds-radius-tile, 20px);
  background: var(--ds-surface, #ffffff);
  border: 1px solid var(--ds-border, rgba(255, 255, 255, 0.9));
  box-shadow: var(--ds-shadow-tile);
  transition: transform 0.15s ease;
}

.financial-card:active {
  transform: scale(0.985);
}

.financial-avatar-wrap {
  position: relative;
  display: grid;
  width: 44px;
  height: 44px;
  place-items: center;
  border-radius: 14px;
  flex-shrink: 0;
}

.avatar-blue {
  background: rgba(2, 132, 199, 0.12);
  color: #0284c7;
}

.avatar-emerald {
  background: rgba(16, 185, 129, 0.12);
  color: #059669;
}

.avatar-amber {
  background: rgba(245, 158, 11, 0.12);
  color: #d97706;
}

.avatar-coral {
  background: rgba(239, 68, 68, 0.12);
  color: #dc2626;
}

.avatar-purple {
  background: rgba(168, 85, 247, 0.12);
  color: #7c3aed;
}

.dot-online, .dot-offline {
  position: absolute;
  right: -2px;
  bottom: -2px;
  width: 11px;
  height: 11px;
  border-radius: 50%;
  border: 2px solid var(--ds-surface, #ffffff);
}

.dot-online  { background: var(--ds-emerald, #10b981); }
.dot-offline { background: var(--ds-muted-light, #94a3b8); }

.financial-info {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.financial-head-row {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 6px;
}

.financial-name {
  font-size: 13.5px;
  font-weight: 800;
  letter-spacing: -0.02em;
  color: var(--ds-ink);
}

.financial-badge-group {
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

.financial-money-row {
  display: flex;
  align-items: baseline;
  flex-wrap: wrap;
  gap: 8px;
  margin: 2px 0;
}

.financial-money-amount {
  font-size: 16px;
  font-weight: 800;
  letter-spacing: -0.02em;
}

.financial-resmi-text {
  font-size: 11px;
  font-weight: 600;
  color: var(--ds-muted);
}

.financial-chip-badge {
  font-size: 10px;
  font-weight: 700;
  background: var(--ds-surface-subtle);
  color: var(--ds-muted);
  padding: 2px 7px;
  border-radius: 999px;
  border: 1px solid var(--ds-border-subtle, rgba(15, 23, 42, 0.06));
}

.financial-date-row {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
}

.financial-date-pill {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  color: var(--ds-muted);
  font-weight: 600;
}

.financial-date-pill--icra {
  color: #dc2626;
  font-weight: 700;
}

.financial-note {
  margin: 4px 0 0;
  font-size: 11px;
  color: var(--ds-muted);
  line-height: 1.4;
  background: var(--ds-surface-subtle);
  padding: 4px 8px;
  border-radius: 6px;
}

.financial-actions {
  display: flex;
  flex-direction: column;
  gap: 6px;
  flex-shrink: 0;
}

.action-pill-btn {
  display: grid;
  width: 28px;
  height: 28px;
  place-items: center;
  border-radius: 8px;
  border: 1px solid var(--ds-border-subtle, rgba(15, 23, 42, 0.06));
  background: var(--ds-surface-subtle);
  color: var(--ds-muted);
  cursor: pointer;
  transition: all 0.15s ease;
}

.action-pill-btn:hover {
  background: var(--ds-surface);
  color: var(--ds-ink);
}

.action-pill-btn--amber {
  color: #d97706;
}
.action-pill-btn--amber:hover {
  background: rgba(245, 158, 11, 0.1);
}

.action-pill-btn--danger {
  color: #ef4444;
}
.action-pill-btn--danger:hover {
  background: rgba(239, 68, 68, 0.1);
}

/* Clay Badge Ek Renkleri */
.clay-badge--secondary {
  background: rgba(100, 116, 139, 0.12);
  color: #64748b;
}

.clay-badge--warning {
  background: rgba(245, 158, 11, 0.12);
  color: #d97706;
}

.clay-badge--cyan,
.clay-badge--blue {
  background: rgba(2, 132, 199, 0.12);
  color: #0284c7;
}

/* Modallardaki Segmentli Seçimler */
.segmented-pills {
  display: flex;
  background: var(--ds-surface-subtle, #f1f5f9);
  padding: 3px;
  border-radius: var(--ds-radius-pill, 999px);
  gap: 3px;
  border: 1px solid var(--ds-border, rgba(0, 0, 0, 0.06));
}

.seg-btn {
  flex: 1;
  min-height: 36px;
  padding: 6px 10px;
  border-radius: var(--ds-radius-pill, 999px);
  border: none;
  font-size: 11.5px;
  font-weight: 700;
  background: transparent;
  color: var(--ds-muted);
  cursor: pointer;
  transition: all 0.15s ease;
  text-align: center;
}

.seg-btn--active {
  background: #ffffff;
  color: var(--ds-ink);
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
}

.text-emerald {
  color: #059669 !important;
}

.text-coral {
  color: #dc2626 !important;
}

.text-sky {
  color: #38bdf8 !important;
}

.text-purple {
  color: #c084fc !important;
}

.text-white-50 {
  color: rgba(255, 255, 255, 0.5) !important;
}

/* ========================================================================== */
/* HERO OBSIDIAN BENTO KART (Cari Takip Tarzı Minimal Dağılım Kartı)          */
/* ========================================================================== */
.cari-hero-volume-card {
  background: var(--ds-surface-dark, #191d24);
  border-radius: 24px;
  color: #ffffff;
  padding: 16px 15px;
  margin-bottom: 16px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  box-shadow: var(--ds-shadow-bento-dark, 0 16px 36px rgba(0, 0, 0, 0.28));
}

.cari-hero-volume-card__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 12px;
}

.cari-hero-volume-card__title {
  display: flex;
  align-items: center;
  gap: 10px;
}

.cari-hero-icon-box {
  display: grid;
  place-items: center;
  width: 32px;
  height: 32px;
  border-radius: 11px;
  background: rgba(255, 255, 255, 0.08);
  border: 1px solid rgba(255, 255, 255, 0.12);
}

.cari-hero-tag {
  display: block;
  font-size: 9.5px;
  font-weight: 700;
  letter-spacing: 0.08em;
  color: rgba(255, 255, 255, 0.65);
  text-transform: uppercase;
}

.cari-hero-subtitle {
  display: block;
  font-size: 11px;
  font-weight: 500;
  color: rgba(255, 255, 255, 0.45);
}

.cari-hero-refresh-btn {
  width: 28px;
  height: 28px;
  display: grid;
  place-items: center;
  border-radius: 9px;
  background: rgba(255, 255, 255, 0.08);
  color: rgba(255, 255, 255, 0.7);
  border: 1px solid rgba(255, 255, 255, 0.1);
  cursor: pointer;
  transition: all 0.2s ease;
}

.cari-hero-refresh-btn:active {
  background: rgba(255, 255, 255, 0.16);
  transform: scale(0.96);
}

.cari-hero-volume-main {
  margin-bottom: 12px;
}

.cari-hero-volume-amount {
  display: block;
  font-size: 24px;
  font-weight: 800;
  letter-spacing: -0.04em;
  color: #ffffff;
  line-height: 1.15;
}

.cari-hero-balance-row {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-top: 4px;
  font-size: 11.5px;
}

.cari-hero-balance-label {
  color: rgba(255, 255, 255, 0.55);
}

/* Çift Yönlü Segmentli Dağılım Grafiği (Minimal) */
.cari-chart-container {
  padding-top: 8px;
  border-top: 1px solid rgba(255, 255, 255, 0.08);
}

.cari-segmented-track {
  position: relative;
  display: flex;
  height: 12px;
  width: 100%;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.1);
  overflow: hidden;
  box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.3);
}

.cari-seg-fill {
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: width 0.4s ease;
  position: relative;
}

.cari-seg-fill--alacak {
  background: linear-gradient(90deg, #10b981, #059669);
}

.cari-seg-fill--borc {
  background: linear-gradient(90deg, #38bdf8, #0284c7);
}

.cari-seg-fill--purple {
  background: linear-gradient(90deg, #a855f7, #7c3aed);
}

.cari-seg-fill--coral {
  background: linear-gradient(90deg, #fb7185, #e11d48);
}

.cari-seg-label {
  font-size: 8.5px;
  font-weight: 800;
  color: #ffffff;
  text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
  letter-spacing: -0.01em;
}

/* Lejant (Tıklanabilir Filtre Kutuları) */
.cari-chart-legend {
  display: grid;
  gap: 8px;
  margin-top: 10px;
}

.cari-chart-legend--3col {
  grid-template-columns: repeat(3, 1fr);
}

.cari-chart-legend--2x2 {
  grid-template-columns: repeat(2, 1fr);
}

.cari-legend-item {
  display: flex;
  align-items: flex-start;
  gap: 7px;
  padding: 7px 9px;
  border-radius: 12px;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.06);
  cursor: pointer;
  transition: background 0.18s ease, border-color 0.18s ease;
}

.cari-legend-item:active {
  background: rgba(255, 255, 255, 0.1);
}

.cari-legend-item--active {
  background: rgba(255, 255, 255, 0.14);
  border-color: rgba(255, 255, 255, 0.28);
  box-shadow: 0 0 10px rgba(255, 255, 255, 0.05);
}

.cari-legend-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  margin-top: 4px;
  flex-shrink: 0;
}

.cari-legend-dot--alacak {
  background: #10b981;
  box-shadow: 0 0 8px rgba(16, 185, 129, 0.6);
}

.cari-legend-dot--borc {
  background: #38bdf8;
  box-shadow: 0 0 8px rgba(56, 189, 248, 0.6);
}

.cari-legend-dot--purple {
  background: #a855f7;
  box-shadow: 0 0 8px rgba(168, 85, 247, 0.6);
}

.cari-legend-dot--coral {
  background: #fb7185;
  box-shadow: 0 0 8px rgba(251, 113, 133, 0.6);
}

.cari-legend-info {
  min-width: 0;
  flex: 1;
}

.cari-legend-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 4px;
}

.cari-legend-title {
  font-size: 10px;
  font-weight: 600;
  color: rgba(255, 255, 255, 0.75);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.cari-legend-pct {
  font-size: 10px;
  font-weight: 800;
}

.cari-legend-val {
  display: block;
  font-size: 11.5px;
  font-weight: 800;
  color: #ffffff;
  letter-spacing: -0.02em;
  line-height: 1.2;
  margin-top: 1px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.cari-legend-count {
  display: block;
  font-size: 9.5px;
  font-weight: 500;
  color: rgba(255, 255, 255, 0.45);
  margin-top: 1px;
  white-space: nowrap;
}

/* ========================================================================== */
/* DÖNEM BAZLI FİLTRE ÇUBUĞU                                                 */
/* ========================================================================== */
.period-filter-strip {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 12px;
}

.period-select-wrap {
  position: relative;
  flex: 1;
  display: flex;
  align-items: center;
  background: #ffffff;
  border: 1px solid var(--ds-border, rgba(0, 0, 0, 0.06));
  border-radius: var(--ds-radius-sm, 14px);
  padding: 0 12px;
  height: 38px;
  box-shadow: var(--ds-shadow-tile);
}

.period-select-icon {
  color: var(--ds-muted, #64748b);
  flex-shrink: 0;
  margin-right: 6px;
}

.period-select-input {
  width: 100%;
  border: none;
  background: transparent;
  font-size: 12.5px;
  font-weight: 600;
  color: var(--ds-text, #1e293b);
  outline: none;
  appearance: none;
  -webkit-appearance: none;
  cursor: pointer;
  padding-right: 18px;
}

.period-select-arrow {
  position: absolute;
  right: 10px;
  color: var(--ds-muted, #64748b);
  pointer-events: none;
}

.period-clear-btn {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  height: 38px;
  padding: 0 12px;
  border-radius: var(--ds-radius-sm, 14px);
  background: #ffffff;
  border: 1px solid var(--ds-border, rgba(0, 0, 0, 0.06));
  font-size: 11.5px;
  font-weight: 700;
  color: #e11d48;
  box-shadow: var(--ds-shadow-tile);
  cursor: pointer;
  transition: all 0.15s ease;
}

.period-clear-btn:active {
  background: rgba(225, 29, 72, 0.08);
}

/* ========================================================================== */
/* PUANTAJ PRİMLERİ GRUP KARTI & AÇILIR LİSTE (Accordion)                    */
/* ========================================================================== */
.financial-card--puantaj {
  border-left: 3.5px solid #0284c7;
}

.puantaj-drawer-toggle {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  margin-top: 8px;
  padding: 5px 10px;
  border-radius: 8px;
  background: rgba(2, 132, 199, 0.08);
  border: 1px solid rgba(2, 132, 199, 0.18);
  font-size: 11.5px;
  font-weight: 700;
  color: #0284c7;
  cursor: pointer;
  transition: all 0.2s ease;
  width: fit-content;
}

.puantaj-drawer-toggle:active {
  background: rgba(2, 132, 199, 0.16);
}

.puantaj-sub-drawer {
  margin-top: 8px;
  padding: 8px 10px;
  border-radius: 12px;
  background: var(--ds-surface-subtle, #f8fafc);
  border: 1px solid var(--ds-border, rgba(0, 0, 0, 0.05));
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.puantaj-sub-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 5px 0;
  border-bottom: 1px dashed rgba(0, 0, 0, 0.06);
}

.puantaj-sub-row:last-child {
  border-bottom: none;
}

.puantaj-sub-left {
  display: flex;
  align-items: center;
  gap: 6px;
  min-width: 0;
}

.puantaj-sub-bullet {
  width: 5px;
  height: 5px;
  border-radius: 50%;
  background: #0284c7;
  flex-shrink: 0;
}

.puantaj-sub-title {
  font-size: 11.5px;
  font-weight: 600;
  color: var(--ds-text, #1e293b);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.puantaj-sub-amount {
  font-size: 12px;
  font-weight: 800;
  white-space: nowrap;
  flex-shrink: 0;
}

.text-white-50 {
  color: rgba(255, 255, 255, 0.5) !important;
}
</style>
