<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import {
  AlertCircle,
  ArrowLeft,
  Calendar,
  CalendarDays,
  CalendarRange,
  Check,
  CheckCircle2,
  ChevronDown,
  ChevronLeft,
  ChevronRight,
  Clock,
  Filter,
  Layers,
  Minus,
  MoreVertical,
  Plus,
  RefreshCw,
  RotateCcw,
  Save,
  Search,
  SlidersHorizontal,
  Sparkles,
  Stethoscope,
  Trash2,
  Undo2,
  UserCheck,
  UsersRound,
  X,
  Zap,
} from '@lucide/vue'
import TopBar from '../components/TopBar.vue'
import { auth } from '../services/auth.js'
import { puantajApi } from '../services/puantaj.js'

// --- AY VE GÜN ADLARI ---
const monthNamesTR = [
  'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran',
  'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık',
]
const dayNamesTR = ['Pzt', 'Sal', 'Çar', 'Per', 'Cum', 'Cmt', 'Paz']

// Departman renkleri
const deptPalette = [
  { bg: '#e0f2fe', text: '#0284c7', border: '#bae6fd' },
  { bg: '#dcfce7', text: '#059669', border: '#a7f3d0' },
  { bg: '#f3e8ff', text: '#7c3aed', border: '#e9d5ff' },
  { bg: '#fef3c7', text: '#d97706', border: '#fde68a' },
  { bg: '#e0e7ff', text: '#4338ca', border: '#c7d2fe' },
  { bg: '#ffe4e6', text: '#e11d48', border: '#fecdd3' },
  { bg: '#ccfbf1', text: '#0f766e', border: '#99f6e4' },
]

function getDeptStyle(name) {
  if (!name) return { background: '#f1f5f9', color: '#64748b', borderColor: '#e2e8f0' }
  let hash = 0
  for (let i = 0; i < name.length; i++) {
    hash = name.charCodeAt(i) + ((hash << 5) - hash)
  }
  const idx = Math.abs(hash) % deptPalette.length
  return {
    background: deptPalette[idx].bg,
    color: deptPalette[idx].text,
    border: `1px solid ${deptPalette[idx].border}`,
  }
}

function resolveColor(code, dbColor) {
  const uc = (code || 'X').toUpperCase()
  if (dbColor && typeof dbColor === 'string' && dbColor.startsWith('#') && dbColor.length >= 4) {
    const lc = dbColor.toLowerCase()
    if (lc !== '#ffffff' && lc !== '#fff' && lc !== '#f46a6a' && lc !== '#556ee6') {
      return dbColor
    }
  }
  const defaults = {
    X: '#2563eb', // Mavi
    HT: '#d97706', // Turuncu/Amber
    Yİ: '#059669', // Yeşil
    Bİ: '#db2777', // Pembe
    Eİ: '#6366f1', // İndigo
    Üİ: '#10b981', // Açık Yeşil
    RTÇ: '#0d9488', // Teal
    HTÇ: '#9333ea', // Mor
    SGK: '#dc2626', // Kırmızı
    RP: '#dc2626', // Kırmızı
    Mİ: '#0284c7', // Açık Mavi
    D: '#b91c1c', // Devamsız
    UZ: '#64748b', // Gri
    ÜZ: '#e11d48', // Gül/Kırmızı
  }
  return defaults[uc] || '#2563eb'
}

function padZero(n) {
  return n < 10 ? `0${n}` : `${n}`
}

function getInitials(name) {
  if (!name) return '?'
  const parts = name.trim().split(/\s+/)
  if (parts.length >= 2) {
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
  }
  return name.slice(0, 2).toUpperCase()
}

// --- STATE ---
const now = new Date()
const currentYear = ref(now.getFullYear())
const currentMonth = ref(now.getMonth() + 1)

const loading = ref(false)
const saving = ref(false)
const errorMessage = ref('')
const toastMessage = ref('')
const toastType = ref('success')

const rawCalendarData = ref([])
const definitions = ref({ ucretli: [], ucretsiz: [], departmanlar: [], bolgeler: [] })
const typeMapByCode = ref({})
const typeMapById = ref({})

// Filtreler
const searchTerm = ref('')
const filterDepartman = ref('')
const filterBolge = ref('')
const filterIskur = ref(true)
const activeTabFilter = ref('all') // 'all' | 'izinli' | 'raporlu'
const showFilterSheet = ref(false)

// Ay Picker
const showMonthPicker = ref(false)
const pickerYear = ref(now.getFullYear())

// Değişiklikler: { "personelId_dateStr": { personel_id, type_id, code, date } }
const unsavedChanges = ref({})
// Fazla Mesai Değişiklikleri: { "personelId_dateStr": { personel_id, tarih, saat } }
const unsavedOvertimes = ref({})
const quickOvertimeOptions = [1, 1.5, 2, 3, 4, 5.5, 7.5]
const customOvertimeInput = ref('')
const showPickerFmAccordion = ref(false)

// Kesme Departmanı Kontrolü
function isKesmeDepartment(dept) {
  if (!dept) return false
  const s = dept.toLocaleLowerCase('tr-TR')
  return s.includes('kesme')
}

// Personel için geçerli fazla mesai saatini döndürür
function getEffectiveOvertime(personelId, dateStr, serverOvertimes, shiftOvertimes = null) {
  const key = `${personelId}_${dateStr}`
  const shiftHours = parseFloat(shiftOvertimes?.[dateStr]) || 0
  if (unsavedOvertimes.value[key] !== undefined) {
    return (parseFloat(unsavedOvertimes.value[key].saat) || 0) + shiftHours
  }
  if (serverOvertimes && serverOvertimes[dateStr] !== undefined) {
    const val = serverOvertimes[dateStr]
    if (typeof val === 'object' && val !== null) {
      return (parseFloat(val.saat) || 0) + shiftHours
    }
    return (parseFloat(val) || 0) + shiftHours
  }
  return shiftHours
}

// Fazla mesai saati uygula
function applyOvertimeToCell(personelId, dateStr, saat) {
  const key = `${personelId}_${dateStr}`
  const numSaat = Math.max(0, parseFloat(saat) || 0)
  unsavedOvertimes.value[key] = {
    personel_id: personelId,
    tarih: dateStr,
    saat: numSaat,
  }
}

// Personel Detay & Takvim Bottom Sheet
const selectedPersonel = ref(null)
const showDetailSheet = ref(false)
const modalPaletteTab = ref('ucretli') // 'ucretli' | 'ucretsiz'
const modalStampType = ref(null)

// Hızlı Gün Statüsü Seçici Bottom Sheet
const showStatusPickerSheet = ref(false)
const pickerTargetPersonel = ref(null)
const pickerTargetDay = ref(null)
const pickerActiveTab = ref('ucretli') // 'ucretli' | 'ucretsiz'

// Tarih Aralığı Tanımlama
const showRangeCard = ref(false)
const rangeStartDay = ref(1)
const rangeEndDay = ref(30)
const rangeStatusType = ref(0)

// SGK Bottom Sheet
const showSgkSheet = ref(false)
const sgkLoading = ref(false)
const sgkReports = ref([])
const sgkConfigured = ref(true)
const sgkError = ref('')

// Onay Diyaloğu
const showUnsavedPrompt = ref(false)
const pendingAction = ref(null)

// --- COMPUTED ---
const currentMonthLabel = computed(() => {
  return `${monthNamesTR[currentMonth.value - 1]} ${currentYear.value}`
})

const daysInCurrentMonth = computed(() => {
  return new Date(currentYear.value, currentMonth.value, 0).getDate()
})

const unsavedCount = computed(() => {
  return Object.keys(unsavedChanges.value).length + Object.keys(unsavedOvertimes.value).length
})

// Tip Haritasını Oluştur
function buildTypeMaps() {
  const mapCode = {}
  const mapId = {}

  // Temel varsayılan tipler
  const defaultTypes = [
    { id: 649, name: 'Çalışılan Gün', code: 'X', color: '#2563eb', ucretli: true },
    { id: 650, name: 'Hafta Tatili', code: 'HT', color: '#d97706', ucretli: true },
    { id: 47, name: 'Yıllık İzin', code: 'Yİ', color: '#059669', ucretli: true },
    { id: 48, name: 'Babalık İzni', code: 'Bİ', color: '#db2777', ucretli: true },
    { id: 51, name: 'Evlilik İzni', code: 'Eİ', color: '#6366f1', ucretli: true },
    { id: 1649, name: 'Ücretli İzin', code: 'Üİ', color: '#10b981', ucretli: true },
    { id: 2861, name: 'Resmi Tatil Çalışması', code: 'RTÇ', color: '#0d9488', ucretli: true },
    { id: 2862, name: 'Hafta Tatili Çalışması', code: 'HTÇ', color: '#9333ea', ucretli: true },
    { id: 50, name: 'SGK Raporu', code: 'SGK', color: '#dc2626', ucretli: false },
    { id: 50, name: 'Hastalık İzni (Rapor)', code: 'RP', color: '#dc2626', ucretli: false },
    { id: 49, name: 'Mazeret İzni', code: 'Mİ', color: '#0284c7', ucretli: false },
    { id: 523, name: 'Devamsız', code: 'D', color: '#b91c1c', ucretli: false },
    { id: 2225, name: 'Çalışılmayan Gün', code: 'UZ', color: '#64748b', ucretli: false },
    { id: 2900, name: 'Ücretsiz İzin', code: 'ÜZ', color: '#e11d48', ucretli: false },
  ]

  defaultTypes.forEach((dt) => {
    mapCode[dt.code] = dt
    mapId[dt.id] = dt
  })

  // Sunucudan gelen dinamik tanımlar
  const allFromServer = [...(definitions.value.ucretli || []), ...(definitions.value.ucretsiz || [])]
  allFromServer.forEach((t) => {
    const code = (t.kisa_kod || t.tur_adi.slice(0, 2)).toUpperCase()
    const hex = resolveColor(code, t.renk)
    const obj = {
      id: Number(t.id),
      name: t.tur_adi,
      code,
      color: hex,
      ucretli: Boolean(t.ucretli_mi),
    }
    mapCode[code] = obj
    mapId[Number(t.id)] = obj
  })

  typeMapByCode.value = mapCode
  typeMapById.value = mapId
}

// Personel için o günkü geçerli statü
function getEffectiveEntry(personelId, dateStr, originalEntries) {
  const key = `${personelId}_${dateStr}`

  // Kaydedilmemiş bir değişiklik varsa öncelikli olarak onu kullan!
  if (unsavedChanges.value[key]) {
    const change = unsavedChanges.value[key]
    const code = change.code ? change.code.toUpperCase() : ''

    if (code) {
      const typeObj = typeMapByCode.value[code] || {
        code,
        name: code === 'X' ? 'Çalışılan Gün' : (code === 'HT' ? 'Hafta Tatili' : 'İzin'),
        color: resolveColor(code),
        id: change.type_id || 0,
      }
      return {
        kisa_kod: typeObj.code,
        name: typeObj.name,
        color: typeObj.color,
        type: 'custom',
        tip_id: typeObj.id,
      }
    }

    if (change.type_id) {
      const typeObj = typeMapById.value[change.type_id] || { kisa_kod: 'İZN', name: 'İzin', color: '#059669' }
      return {
        kisa_kod: typeObj.code,
        name: typeObj.name,
        color: typeObj.color,
        type: 'custom',
        tip_id: change.type_id,
      }
    }

    // code === 'X' veya type_id === 0 ise normal çalışılan gün
    return { kisa_kod: 'X', name: 'Çalışılan Gün', color: '#2563eb', type: 'default', tip_id: 0 }
  }

  // Orijinal veritabanı kaydı
  if (originalEntries && originalEntries.length > 0) {
    const orig = originalEntries[0]
    return {
      ...orig,
      color: resolveColor(orig.kisa_kod, orig.color),
    }
  }

  // Varsayılan: Pazar ise Hafta Tatili (HT), diğer günler Çalışılan Gün (X)
  const d = new Date(dateStr)
  const isSun = d.getDay() === 0
  return isSun
    ? { kisa_kod: 'HT', name: 'Hafta Tatili', color: '#d97706', type: 'default', tip_id: 0 }
    : { kisa_kod: 'X', name: 'Çalışılan Gün', color: '#2563eb', type: 'default', tip_id: 0 }
}

// Filtrelenmiş Personel Listesi
const filteredPersonelList = computed(() => {
  const q = (searchTerm.value || '').toLowerCase().trim()
  const days = daysInCurrentMonth.value

  return rawCalendarData.value.filter((p) => {
    if (q) {
      const matchName = (p.adi_soyadi || '').toLowerCase().includes(q)
      const matchTc = (p.tc_kimlik_no || '').includes(q)
      const matchDept = (p.departman || '').toLowerCase().includes(q)
      if (!matchName && !matchTc && !matchDept) return false
    }

    if (activeTabFilter.value !== 'all') {
      let leaveDays = 0
      let sgkDays = 0
      for (let d = 1; d <= days; d++) {
        const dateStr = `${currentYear.value}-${padZero(currentMonth.value)}-${padZero(d)}`
        const entry = getEffectiveEntry(p.id, dateStr, p.entries?.[dateStr])
        const code = (entry.kisa_kod || 'X').toUpperCase()
        if (code === 'SGK' || code === 'RP') {
          sgkDays++
        } else if (code !== 'X' && code !== 'HTÇ' && code !== 'RTÇ' && code !== 'HT' && code !== '') {
          leaveDays++
        }
      }
      if (activeTabFilter.value === 'izinli') return leaveDays > 0
      if (activeTabFilter.value === 'raporlu') return sgkDays > 0
    }

    return true
  })
})

// Genel KPI İstatistikleri (Görsel 2 Overview)
const summaryStats = computed(() => {
  const days = daysInCurrentMonth.value
  let totalWorked = 0
  let totalLeaveDays = 0
  let totalSgkDays = 0
  const leavePersons = new Set()
  const sgkPersons = new Set()

  rawCalendarData.value.forEach((p) => {
    let pLeave = 0
    let pSgk = 0
    for (let d = 1; d <= days; d++) {
      const dateStr = `${currentYear.value}-${padZero(currentMonth.value)}-${padZero(d)}`
      const entry = getEffectiveEntry(p.id, dateStr, p.entries?.[dateStr])
      const code = (entry.kisa_kod || '').toUpperCase()
      if (code === 'X' || code === 'HTÇ' || code === 'RTÇ') {
        totalWorked++
      } else if (code === 'SGK' || code === 'RP') {
        totalSgkDays++
        pSgk++
      } else if (code !== 'HT' && code !== '') {
        totalLeaveDays++
        pLeave++
      }
    }
    if (pLeave > 0) leavePersons.add(p.id)
    if (pSgk > 0) sgkPersons.add(p.id)
  })

  return {
    totalPersonel: rawCalendarData.value.length,
    leavePersonsCount: leavePersons.size,
    leaveDaysCount: totalLeaveDays,
    sgkPersonsCount: sgkPersons.size,
    sgkDaysCount: totalSgkDays,
    totalWorkedDays: totalWorked,
  }
})

function getPersonelMonthStats(p) {
  const days = daysInCurrentMonth.value
  let worked = 0
  let leave = 0
  let sgk = 0
  let overtimeHours = 0

  for (let d = 1; d <= days; d++) {
    const dateStr = `${currentYear.value}-${padZero(currentMonth.value)}-${padZero(d)}`
    const entry = getEffectiveEntry(p.id, dateStr, p.entries?.[dateStr])
    const code = (entry.kisa_kod || 'X').toUpperCase()
    if (code === 'X' || code === 'HTÇ' || code === 'RTÇ') {
      worked++
    } else if (code === 'SGK' || code === 'RP') {
      sgk++
    } else if (code !== 'HT' && code !== '') {
      leave++
    }
    const ot = getEffectiveOvertime(p.id, dateStr, p.overtimes, p.shift_overtimes)
    overtimeHours += ot
  }

  const rate = Math.min(100, Math.round((worked / days) * 100))
  return { worked, leave, sgk, rate, days, overtimeHours: Number(overtimeHours.toFixed(1)) }
}

// --- VERİ ÇEKME ---
async function fetchDefinitions() {
  try {
    const res = await puantajApi.getDefinitions()
    if (res.success && res.data) {
      definitions.value = res.data
      buildTypeMaps()
    }
  } catch (err) {
    console.error('Definitions load error:', err)
  }
}

async function loadCalendarData() {
  loading.value = true
  errorMessage.value = ''
  try {
    const res = await puantajApi.getCalendarData({
      ay: padZero(currentMonth.value),
      yil: currentYear.value,
      departman: filterDepartman.value,
      bolge: filterBolge.value,
      iskur_dahil: filterIskur.value ? 1 : 0,
    })
    if (res.success) {
      rawCalendarData.value = res.data || []
    } else {
      errorMessage.value = res.message || 'Puantaj verileri alınamadı.'
    }
  } catch (err) {
    console.error(err)
    errorMessage.value = err.message || 'Bağlantı hatası oluştu.'
  } finally {
    loading.value = false
  }
}

function showToast(msg, type = 'success') {
  toastMessage.value = msg
  toastType.value = type
  setTimeout(() => {
    toastMessage.value = ''
  }, 3200)
}

// --- AY GEZİNME VE DEĞİŞİKLİK GÜVENLİĞİ ---
function confirmBeforeNavigate(action) {
  if (unsavedCount.value > 0) {
    pendingAction.value = action
    showUnsavedPrompt.value = true
    return
  }
  action()
}

function executePendingAction() {
  unsavedChanges.value = {}
  showUnsavedPrompt.value = false
  if (pendingAction.value) {
    pendingAction.value()
    pendingAction.value = null
  }
}

function cancelPendingAction() {
  showUnsavedPrompt.value = false
  pendingAction.value = null
}

function changeMonth(delta) {
  confirmBeforeNavigate(() => {
    let m = currentMonth.value + delta
    let y = currentYear.value
    if (m > 12) {
      m = 1
      y++
    } else if (m < 1) {
      m = 12
      y--
    }
    currentMonth.value = m
    currentYear.value = y
    loadCalendarData()
  })
}

function resetToCurrentMonth() {
  confirmBeforeNavigate(() => {
    const d = new Date()
    currentYear.value = d.getFullYear()
    currentMonth.value = d.getMonth() + 1
    loadCalendarData()
  })
}

function selectMonthFromPicker(mIndex) {
  confirmBeforeNavigate(() => {
    currentYear.value = pickerYear.value
    currentMonth.value = mIndex + 1
    showMonthPicker.value = false
    loadCalendarData()
  })
}

// --- PUANTAJ DEĞİŞİKLİĞİ UYGULAMA (KESİN ATAMA) ---
function applyStatusToCell(personelId, dateStr, typeId, code) {
  const key = `${personelId}_${dateStr}`
  unsavedChanges.value[key] = {
    personel_id: personelId,
    type_id: typeId,
    code: code.toUpperCase(),
    status_code: code.toUpperCase(),
    date: dateStr,
    baslangic_tarihi: dateStr,
    bitis_tarihi: dateStr,
  }
}

function cycleCellStatus(personelId, dateStr, currentCode) {
  let nextCode = 'HT'
  let nextTypeId = 0

  if (currentCode === 'X') {
    nextCode = 'HT'
    const t = typeMapByCode.value.HT
    nextTypeId = t ? t.id : 650
  } else if (currentCode === 'HT') {
    nextCode = 'Yİ'
    const t = typeMapByCode.value.Yİ
    nextTypeId = t ? t.id : 47
  } else if (currentCode === 'Yİ') {
    nextCode = 'SGK'
    const t = typeMapByCode.value.SGK || typeMapByCode.value.RP
    nextTypeId = t ? t.id : 50
  } else {
    nextCode = 'X'
    nextTypeId = 0
  }

  applyStatusToCell(personelId, dateStr, nextTypeId, nextCode)
}

function discardUnsavedChanges() {
  unsavedChanges.value = {}
  unsavedOvertimes.value = {}
  showToast('Değişiklikler sıfırlandı.', 'info')
}

async function savePuantajChanges() {
  const rows = Object.values(unsavedChanges.value)
  const otRows = Object.values(unsavedOvertimes.value)
  if (rows.length === 0 && otRows.length === 0) return

  saving.value = true
  try {
    const res = await puantajApi.saveBulkEntries(
      {
        ay: padZero(currentMonth.value),
        yil: currentYear.value,
        data: rows,
        overtimes: otRows,
      },
      auth.state.csrfToken,
    )

    if (res.success) {
      unsavedChanges.value = {}
      unsavedOvertimes.value = {}
      showToast(res.message || 'Puantaj değişiklikleri başarıyla kaydedildi!', 'success')
      await loadCalendarData()
      if (selectedPersonel.value) {
        const updated = rawCalendarData.value.find((x) => x.id === selectedPersonel.value.id)
        if (updated) selectedPersonel.value = updated
      }
    } else {
      showToast(res.message || 'Kayıt sırasında hata oluştu.', 'error')
    }
  } catch (err) {
    console.error(err)
    showToast(err.message || 'Sunucu ile iletişim kurulamadı.', 'error')
  } finally {
    saving.value = false
  }
}

// --- PERSONEL AY DETAY BOTTOM SHEET ---
function openPersonelDetail(p) {
  selectedPersonel.value = p
  modalStampType.value = null
  showRangeCard.value = false
  rangeStartDay.value = 1
  rangeEndDay.value = daysInCurrentMonth.value
  rangeStatusType.value = 0
  showDetailSheet.value = true
}

function closePersonelDetail() {
  showDetailSheet.value = false
  selectedPersonel.value = null
  modalStampType.value = null
}

// Hızlı Damga Paleti Seçenekleri (TÜM TÜRLER GELİR)
const availablePaletteList = computed(() => {
  const isUcretliTab = modalPaletteTab.value === 'ucretli'
  let list = []

  if (isUcretliTab) {
    // Ücretli Türler
    const defaultUcretli = [
      { id: 649, name: 'Çalışılan Gün', code: 'X', color: '#2563eb' },
      { id: 650, name: 'Hafta Tatili', code: 'HT', color: '#d97706' },
      { id: 47, name: 'Yıllık İzin', code: 'Yİ', color: '#059669' },
      { id: 48, name: 'Babalık İzni', code: 'Bİ', color: '#db2777' },
      { id: 51, name: 'Evlilik İzni', code: 'Eİ', color: '#6366f1' },
      { id: 1649, name: 'Ücretli İzin', code: 'Üİ', color: '#10b981' },
      { id: 2861, name: 'Resmi Tatil Çalışması', code: 'RTÇ', color: '#0d9488' },
      { id: 2862, name: 'Hafta Tatili Çalışması', code: 'HTÇ', color: '#9333ea' },
    ]
    list = [...defaultUcretli]
    if (definitions.value.ucretli && definitions.value.ucretli.length > 0) {
      definitions.value.ucretli.forEach((t) => {
        const code = (t.kisa_kod || t.tur_adi.slice(0, 2)).toUpperCase()
        if (!list.some((x) => x.code === code)) {
          list.push({
            id: Number(t.id),
            name: t.tur_adi,
            code,
            color: resolveColor(code, t.renk),
          })
        }
      })
    }
  } else {
    // Ücretsiz Türler
    const defaultUcretsiz = [
      { id: 50, name: 'SGK Raporu', code: 'SGK', color: '#dc2626' },
      { id: 50, name: 'Hastalık İzni (Rapor)', code: 'RP', color: '#dc2626' },
      { id: 49, name: 'Mazeret İzni', code: 'Mİ', color: '#0284c7' },
      { id: 523, name: 'Devamsız', code: 'D', color: '#b91c1c' },
      { id: 2225, name: 'Çalışılmayan Gün', code: 'UZ', color: '#64748b' },
      { id: 2900, name: 'Ücretsiz İzin', code: 'ÜZ', color: '#e11d48' },
    ]
    list = [...defaultUcretsiz]
    if (definitions.value.ucretsiz && definitions.value.ucretsiz.length > 0) {
      definitions.value.ucretsiz.forEach((t) => {
        const code = (t.kisa_kod || t.tur_adi.slice(0, 2)).toUpperCase()
        if (!list.some((x) => x.code === code)) {
          list.push({
            id: Number(t.id),
            name: t.tur_adi,
            code,
            color: resolveColor(code, t.renk),
          })
        }
      })
    }
  }

  return list
})

function toggleModalStamp(typeObj) {
  if (modalStampType.value && modalStampType.value.code === typeObj.code) {
    modalStampType.value = null
  } else {
    modalStampType.value = typeObj
  }
}

function selectFmStamp(hrs) {
  const code = hrs > 0 ? `+${hrs}s FM` : 'FM Sil'
  if (modalStampType.value && modalStampType.value.isFm && modalStampType.value.hours === hrs) {
    modalStampType.value = null
  } else {
    modalStampType.value = {
      isFm: true,
      hours: hrs,
      code,
      name: hrs > 0 ? `${hrs} Saat Fazla Mesai` : 'Fazla Mesaiyi Kaldır',
      color: hrs > 0 ? '#7c3aed' : '#dc2626',
    }
  }
}

function getCellTheme(code) {
  const uc = (code || 'X').toUpperCase()
  const map = {
    X:   { bg: '#eff6ff', border: '#bfdbfe', text: '#1d4ed8', badgeBg: '#2563eb', badgeText: '#ffffff' },
    HT:  { bg: '#fff7ed', border: '#fed7aa', text: '#c2410c', badgeBg: '#ea580c', badgeText: '#ffffff' },
    Yİ:  { bg: '#f0fdf4', border: '#bbf7d0', text: '#15803d', badgeBg: '#16a34a', badgeText: '#ffffff' },
    Bİ:  { bg: '#fdf2f8', border: '#fbcfe8', text: '#be185d', badgeBg: '#db2777', badgeText: '#ffffff' },
    Eİ:  { bg: '#eef2ff', border: '#c7d2fe', text: '#4338ca', badgeBg: '#4f46e5', badgeText: '#ffffff' },
    Üİ:  { bg: '#ecfdf5', border: '#a7f3d0', text: '#047857', badgeBg: '#059669', badgeText: '#ffffff' },
    RTÇ: { bg: '#f0fdfa', border: '#99f6e4', text: '#0f766e', badgeBg: '#0d9488', badgeText: '#ffffff' },
    HTÇ: { bg: '#faf5ff', border: '#e9d5ff', text: '#7e22ce', badgeBg: '#9333ea', badgeText: '#ffffff' },
    SGK: { bg: '#fef2f2', border: '#fecaca', text: '#b91c1c', badgeBg: '#dc2626', badgeText: '#ffffff' },
    RP:  { bg: '#fef2f2', border: '#fecaca', text: '#b91c1c', badgeBg: '#dc2626', badgeText: '#ffffff' },
    Mİ:  { bg: '#f0f9ff', border: '#bae6fd', text: '#0369a1', badgeBg: '#0284c7', badgeText: '#ffffff' },
    D:   { bg: '#fef2f2', border: '#fecaca', text: '#991b1b', badgeBg: '#b91c1c', badgeText: '#ffffff' },
    UZ:  { bg: '#f8fafc', border: '#cbd5e1', text: '#475569', badgeBg: '#64748b', badgeText: '#ffffff' },
    ÜZ:  { bg: '#fff1f2', border: '#fecdd3', text: '#be123c', badgeBg: '#e11d48', badgeText: '#ffffff' },
  }
  return map[uc] || { bg: '#f8fafc', border: '#e2e8f0', text: '#1e293b', badgeBg: '#2563eb', badgeText: '#ffffff' }
}

// --- HAFTALIK PUANTAJ (Weekly Schedule - İçinde Bulunulan Hafta) ---
const todayRealDate = new Date()
const todayRealYear = todayRealDate.getFullYear()
const todayRealMonth = todayRealDate.getMonth() + 1
const todayRealDay = todayRealDate.getDate()

const currentWeekDays = computed(() => {
  const isCurrentRealMonth = currentYear.value === todayRealYear && currentMonth.value === todayRealMonth

  let baseDate
  if (isCurrentRealMonth) {
    baseDate = new Date(todayRealYear, todayRealMonth - 1, todayRealDay)
  } else {
    // Farklı aysa o ayın 1. gününün haftasını göster
    baseDate = new Date(currentYear.value, currentMonth.value - 1, 1)
  }

  const dayOfWeek = baseDate.getDay() // 0: Pazar, 1: Pazartesi...
  const diffToMonday = dayOfWeek === 0 ? -6 : 1 - dayOfWeek

  const monday = new Date(baseDate.getFullYear(), baseDate.getMonth(), baseDate.getDate() + diffToMonday)

  const days = []
  const shortNamesTR = ['Pzt', 'Sal', 'Çar', 'Per', 'Cum', 'Cmt', 'Paz']

  for (let i = 0; i < 7; i++) {
    const d = new Date(monday.getFullYear(), monday.getMonth(), monday.getDate() + i)
    const y = d.getFullYear()
    const m = d.getMonth() + 1
    const dayNum = d.getDate()
    const dateStr = `${y}-${padZero(m)}-${padZero(dayNum)}`
    const isToday = y === todayRealYear && m === todayRealMonth && dayNum === todayRealDay
    const isSunday = i === 6
    const isCurrentMonth = m === currentMonth.value

    days.push({
      dateStr,
      dayNum,
      dayName: shortNamesTR[i],
      isToday,
      isSunday,
      isCurrentMonth,
      monthName: monthNamesTR[m - 1],
    })
  }

  return days
})

const weekRangeLabel = computed(() => {
  const days = currentWeekDays.value
  if (!days || days.length === 0) return ''
  const first = days[0]
  const last = days[6]
  if (first.monthName === last.monthName) {
    return `${first.dayNum} - ${last.dayNum} ${first.monthName} ${currentYear.value}`
  }
  return `${first.dayNum} ${first.monthName} - ${last.dayNum} ${last.monthName} ${currentYear.value}`
})

function getPersonelWeekDayEntry(personel, dayObj) {
  const entry = getEffectiveEntry(personel.id, dayObj.dateStr, personel.entries?.[dayObj.dateStr])
  const code = (entry.kisa_kod || (dayObj.isSunday ? 'HT' : 'X')).toUpperCase()
  const theme = getCellTheme(code)
  const unsavedKey = `${personel.id}_${dayObj.dateStr}`
  const isUnsaved = Boolean(unsavedChanges.value[unsavedKey]) || Boolean(unsavedOvertimes.value[unsavedKey])
  const overtimeHours = getEffectiveOvertime(personel.id, dayObj.dateStr, personel.overtimes, personel.shift_overtimes)

  return {
    ...entry,
    code,
    theme,
    isUnsaved,
    overtimeHours,
    hasOvertime: overtimeHours > 0,
  }
}

function openDayStatusPicker(personel, dayObj) {
  pickerTargetPersonel.value = personel
  pickerTargetDay.value = dayObj
  pickerActiveTab.value = 'ucretli'
  const currentOt = getEffectiveOvertime(personel.id, dayObj.dateStr, personel.overtimes)
  customOvertimeInput.value = currentOt > 0 ? String(currentOt) : '4.0'
  showStatusPickerSheet.value = true
}

function closeDayStatusPicker() {
  showStatusPickerSheet.value = false
  pickerTargetPersonel.value = null
  pickerTargetDay.value = null
  customOvertimeInput.value = ''
}

const pickerCurrentOvertime = computed(() => {
  if (!pickerTargetPersonel.value || !pickerTargetDay.value) return 0
  return getEffectiveOvertime(
    pickerTargetPersonel.value.id,
    pickerTargetDay.value.dateStr,
    pickerTargetPersonel.value.overtimes,
  )
})

function setPickerOvertime(saat) {
  if (!pickerTargetPersonel.value || !pickerTargetDay.value) return
  const num = Math.max(0, parseFloat(saat) || 0)
  applyOvertimeToCell(pickerTargetPersonel.value.id, pickerTargetDay.value.dateStr, num)
  customOvertimeInput.value = num > 0 ? String(num) : ''
  if (num > 0) {
    showToast(`${pickerTargetPersonel.value.adi_soyadi} için ${num} saat fazla mesai eklendi.`, 'success')
  } else {
    showToast('Fazla mesai kaydı kaldırıldı.', 'info')
  }
}

function applyCustomOvertime() {
  if (!pickerTargetPersonel.value || !pickerTargetDay.value) return
  const num = Math.max(0, parseFloat(customOvertimeInput.value) || 0)
  setPickerOvertime(num)
}

function stepOvertime(delta) {
  let cur = parseFloat(customOvertimeInput.value) || 0
  cur = Math.max(0, Math.min(24, Math.round((cur + delta) * 10) / 10))
  customOvertimeInput.value = cur > 0 ? String(cur) : '0'
}

function selectStatusForDay(typeObj) {
  if (!pickerTargetPersonel.value || !pickerTargetDay.value) return

  applyStatusToCell(
    pickerTargetPersonel.value.id,
    pickerTargetDay.value.dateStr,
    typeObj.id,
    typeObj.code,
  )

  closeDayStatusPicker()
}

const currentPickerDayEntry = computed(() => {
  if (!pickerTargetPersonel.value || !pickerTargetDay.value) return null
  return getPersonelWeekDayEntry(pickerTargetPersonel.value, pickerTargetDay.value)
})

const pickerPaletteList = computed(() => {
  const isUcretliTab = pickerActiveTab.value === 'ucretli'
  let list = []

  if (isUcretliTab) {
    const defaultUcretli = [
      { id: 649, name: 'Çalışılan Gün', code: 'X', color: '#2563eb' },
      { id: 650, name: 'Hafta Tatili', code: 'HT', color: '#d97706' },
      { id: 47, name: 'Yıllık İzin', code: 'Yİ', color: '#059669' },
      { id: 48, name: 'Babalık İzni', code: 'Bİ', color: '#db2777' },
      { id: 51, name: 'Evlilik İzni', code: 'Eİ', color: '#6366f1' },
      { id: 1649, name: 'Ücretli İzin', code: 'Üİ', color: '#10b981' },
      { id: 2861, name: 'Resmi Tatil Çalışması', code: 'RTÇ', color: '#0d9488' },
      { id: 2862, name: 'Hafta Tatili Çalışması', code: 'HTÇ', color: '#9333ea' },
    ]
    list = [...defaultUcretli]
    if (definitions.value.ucretli && definitions.value.ucretli.length > 0) {
      definitions.value.ucretli.forEach((t) => {
        const code = (t.kisa_kod || t.tur_adi.slice(0, 2)).toUpperCase()
        if (!list.some((x) => x.code === code)) {
          list.push({
            id: Number(t.id),
            name: t.tur_adi,
            code,
            color: resolveColor(code, t.renk),
          })
        }
      })
    }
  } else {
    const defaultUcretsiz = [
      { id: 50, name: 'SGK Raporu', code: 'SGK', color: '#dc2626' },
      { id: 50, name: 'Hastalık İzni (Rapor)', code: 'RP', color: '#dc2626' },
      { id: 49, name: 'Mazeret İzni', code: 'Mİ', color: '#0284c7' },
      { id: 523, name: 'Devamsız', code: 'D', color: '#b91c1c' },
      { id: 2225, name: 'Çalışılmayan Gün', code: 'UZ', color: '#64748b' },
      { id: 2900, name: 'Ücretsiz İzin', code: 'ÜZ', color: '#e11d48' },
    ]
    list = [...defaultUcretsiz]
    if (definitions.value.ucretsiz && definitions.value.ucretsiz.length > 0) {
      definitions.value.ucretsiz.forEach((t) => {
        const code = (t.kisa_kod || t.tur_adi.slice(0, 2)).toUpperCase()
        if (!list.some((x) => x.code === code)) {
          list.push({
            id: Number(t.id),
            name: t.tur_adi,
            code,
            color: resolveColor(code, t.renk),
          })
        }
      })
    }
  }

  return list
})

function handleWeekCellClick(personel, dayObj) {
  openDayStatusPicker(personel, dayObj)
}

// 7 Sütunlu Aylık Takvim Izgarası
const modalCalendarGridCells = computed(() => {
  if (!selectedPersonel.value) return []
  const cells = []
  const days = daysInCurrentMonth.value
  const firstDay = new Date(currentYear.value, currentMonth.value - 1, 1).getDay()
  const startOffset = firstDay === 0 ? 6 : firstDay - 1

  // Boş hücreler
  for (let i = 0; i < startOffset; i++) {
    cells.push({ empty: true, key: `empty-${i}` })
  }

  // Günler
  for (let d = 1; d <= days; d++) {
    const dateStr = `${currentYear.value}-${padZero(currentMonth.value)}-${padZero(d)}`
    const entry = getEffectiveEntry(selectedPersonel.value.id, dateStr, selectedPersonel.value.entries?.[dateStr])
    const code = (entry.kisa_kod || 'X').toUpperCase()
    const color = entry.color || resolveColor(code)
    const theme = getCellTheme(code)

    const dateObj = new Date(currentYear.value, currentMonth.value - 1, d)
    const isSunday = dateObj.getDay() === 0
    const unsavedKey = `${selectedPersonel.value.id}_${dateStr}`
    const isUnsaved = Boolean(unsavedChanges.value[unsavedKey]) || Boolean(unsavedOvertimes.value[unsavedKey])
    const overtimeHours = getEffectiveOvertime(selectedPersonel.value.id, dateStr, selectedPersonel.value.overtimes, selectedPersonel.value.shift_overtimes)

    cells.push({
      empty: false,
      day: d,
      dateStr,
      code,
      name: entry.name || code,
      color,
      theme,
      isSunday,
      isUnsaved,
      overtimeHours,
      hasOvertime: overtimeHours > 0,
      key: dateStr,
    })
  }

  return cells
})

// Takvim Hücresine Dokunulduğunda Damgalama veya Hızlı Seçici
function handleCellClick(cell) {
  if (cell.empty || !selectedPersonel.value) return

  if (modalStampType.value) {
    if (modalStampType.value.isFm) {
      // Fazla Mesai damgası aktif: Hücreye mesai saatini damgala!
      applyOvertimeToCell(selectedPersonel.value.id, cell.dateStr, modalStampType.value.hours)
    } else {
      // İzin / Çalışma statüsü damgala!
      applyStatusToCell(
        selectedPersonel.value.id,
        cell.dateStr,
        modalStampType.value.id,
        modalStampType.value.code,
      )
    }
  } else {
    // Damga modu kapalı: Hızlı statü & fazla mesai seçici sheet'ini aç
    const d = new Date(cell.dateStr)
    const dayOfWeek = d.getDay()
    const dayName = dayNamesTR[dayOfWeek === 0 ? 6 : dayOfWeek - 1]
    openDayStatusPicker(selectedPersonel.value, {
      dateStr: cell.dateStr,
      dayNum: cell.day,
      dayName,
      monthName: monthNamesTR[currentMonth.value - 1],
      isSunday: cell.isSunday,
    })
  }
}

function applyRangeStatus() {
  if (!selectedPersonel.value) return
  const start = Number(rangeStartDay.value)
  const end = Number(rangeEndDay.value)
  const typeId = Number(rangeStatusType.value)

  if (start > end) {
    showToast('Başlangıç günü bitiş gününden büyük olamaz.', 'error')
    return
  }

  let chosenCode = 'X'
  const found = Object.values(typeMapById.value).find((x) => x.id === typeId)
  if (found) chosenCode = found.code

  for (let d = start; d <= end; d++) {
    const dateStr = `${currentYear.value}-${padZero(currentMonth.value)}-${padZero(d)}`
    applyStatusToCell(selectedPersonel.value.id, dateStr, typeId, chosenCode)
  }

  showToast(`${start} - ${end} ${monthNamesTR[currentMonth.value - 1]} aralığına uygulandı.`, 'success')
  showRangeCard.value = false
}

// --- SGK BOTTOM SHEET ---
async function openSgkModal() {
  showSgkSheet.value = true
  sgkLoading.value = true
  sgkError.value = ''
  sgkReports.value = []

  try {
    const res = await puantajApi.getSgkReports({
      ay: padZero(currentMonth.value),
      yil: currentYear.value,
    })

    sgkConfigured.value = res.configured !== false
    if (res.success) {
      sgkReports.value = res.data || []
    } else {
      sgkError.value = res.message || 'SGK vizite raporları alınamadı.'
    }
  } catch (err) {
    console.error(err)
    sgkError.value = err.message || 'SGK sunucusuna bağlanırken hata oluştu.'
  } finally {
    sgkLoading.value = false
  }
}

function applySgkReport(report) {
  if (!report.personel_id || !report.baslangic || !report.bitis) {
    showToast('Bu rapor geçerli bir personel ile eşleşmedi.', 'error')
    return
  }

  const sgkType = typeMapByCode.value.SGK || typeMapByCode.value.RP || { id: 50, code: 'SGK' }
  const start = new Date(report.baslangic)
  const end = new Date(report.bitis)

  for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
    const dateStr = d.toISOString().split('T')[0]
    applyStatusToCell(report.personel_id, dateStr, sgkType.id, sgkType.code || 'SGK')
  }

  showSgkSheet.value = false
  showToast(`${report.ad_soyad} için SGK raporu puantaja işlendi. Kaydetmeyi unutmayın!`, 'success')
}

// --- LIFECYCLE ---
onMounted(async () => {
  buildTypeMaps()
  await fetchDefinitions()
  await loadCalendarData()
})
</script>

<template>
  <section class="ds-screen puantaj-screen" :aria-busy="loading">
    <!-- 1. ÜST HEADER (Görsel 2 Sol Ekran Tarzı Karşılama) -->
    <header class="app-welcome-bar">
      <div class="welcome-user-info">
        <div class="welcome-avatar">
          {{ auth.state.user?.initials || 'EE' }}
        </div>
        <div>
          <span class="welcome-subtitle">Personel Puantaj</span>
          <h1 class="welcome-title">{{ auth.state.company?.name || 'Ersan Elektrik' }}</h1>
        </div>
      </div>
      <div class="welcome-actions">
        <button
          type="button"
          class="welcome-icon-btn welcome-icon-btn--rose"
          title="SGK Vizite Raporları"
          @click="openSgkModal"
        >
          <Stethoscope :size="18" />
          <span class="welcome-badge-dot"></span>
        </button>
        <button
          type="button"
          class="welcome-icon-btn"
          title="Yenile"
          :disabled="loading"
          @click="loadCalendarData"
        >
          <RefreshCw :size="18" :class="{ 'spin-anim': loading }" />
        </button>
      </div>
    </header>

    <!-- 2. OVERVIEW BAŞLIĞI VE AY GEZİNME BAR (Görsel 2 Sol Ekran) -->
    <div class="overview-section-head">
      <div>
        <h2 class="overview-heading">Genel Bakış</h2>
        <span class="overview-caption">{{ currentMonthLabel }}</span>
      </div>

      <div class="overview-period-ctrls">
        <button
          type="button"
          class="period-chevron-btn"
          aria-label="Önceki Ay"
          @click="changeMonth(-1)"
        >
          <ChevronLeft :size="18" />
        </button>
        <button
          type="button"
          class="period-trigger-pill"
          @click="showMonthPicker = true; pickerYear = currentYear"
        >
          <span>{{ monthNamesTR[currentMonth - 1].slice(0, 3) }} '{{ String(currentYear).slice(2) }}</span>
          <ChevronDown :size="14" />
        </button>
        <button
          type="button"
          class="period-chevron-btn"
          aria-label="Sonraki Ay"
          @click="changeMonth(1)"
        >
          <ChevronRight :size="18" />
        </button>
      </div>
    </div>

    <!-- 3. 2x2 PASTEL BENTO KPI KARTLARI (Görsel 2'deki Birebir Pastel Tasarım) -->
    <div class="overview-2x2-grid">
      <!-- 1. Mor / Lila Kart: Toplam Personel -->
      <article
        class="pastel-kpi-card pastel-kpi-card--purple"
        :class="{ 'is-selected-filter': activeTabFilter === 'all' }"
        @click="activeTabFilter = 'all'"
      >
        <div class="pastel-kpi-top">
          <strong class="pastel-kpi-number">{{ summaryStats.totalPersonel }}</strong>
          <!-- Mor Dalga Grafiği SVG -->
          <svg class="pastel-sparkline text-purple" viewBox="0 0 60 28" fill="none">
            <path
              d="M2 20C10 24 16 4 24 14C32 24 38 6 46 16C52 24 56 12 58 8"
              stroke="#8b5cf6"
              stroke-width="2.5"
              stroke-linecap="round"
              stroke-linejoin="round"
            />
          </svg>
        </div>
        <div class="pastel-kpi-bottom">
          <span class="pastel-kpi-label">Toplam Personel</span>
          <ChevronRight :size="16" class="pastel-arrow" />
        </div>
      </article>

      <!-- 2. Şeftali / Turuncu Kart: İzinli Personel -->
      <article
        class="pastel-kpi-card pastel-kpi-card--peach"
        :class="{ 'is-selected-filter': activeTabFilter === 'izinli' }"
        @click="activeTabFilter = activeTabFilter === 'izinli' ? 'all' : 'izinli'"
      >
        <div class="pastel-kpi-top">
          <strong class="pastel-kpi-number text-peach">{{ summaryStats.leavePersonsCount }}</strong>
          <!-- Turuncu Dalga Grafiği SVG -->
          <svg class="pastel-sparkline text-peach" viewBox="0 0 60 28" fill="none">
            <path
              d="M2 14C10 4 16 22 24 10C32 2 38 24 46 12C52 4 56 18 58 14"
              stroke="#f97316"
              stroke-width="2.5"
              stroke-linecap="round"
              stroke-linejoin="round"
            />
          </svg>
        </div>
        <div class="pastel-kpi-bottom">
          <span class="pastel-kpi-label text-peach">İzinli ({{ summaryStats.leaveDaysCount }} Gün)</span>
          <ChevronRight :size="16" class="pastel-arrow" />
        </div>
      </article>

      <!-- 3. Açık Mavi Kart: SGK Raporlu -->
      <article
        class="pastel-kpi-card pastel-kpi-card--blue"
        :class="{ 'is-selected-filter': activeTabFilter === 'raporlu' }"
        @click="activeTabFilter = activeTabFilter === 'raporlu' ? 'all' : 'raporlu'"
      >
        <div class="pastel-kpi-top">
          <strong class="pastel-kpi-number text-blue">{{ summaryStats.sgkPersonsCount }}</strong>
          <!-- Mavi Dalga Grafiği SVG -->
          <svg class="pastel-sparkline text-blue" viewBox="0 0 60 28" fill="none">
            <path
              d="M2 18C10 18 16 6 24 12C32 18 38 4 46 8C52 12 56 6 58 4"
              stroke="#0ea5e9"
              stroke-width="2.5"
              stroke-linecap="round"
              stroke-linejoin="round"
            />
          </svg>
        </div>
        <div class="pastel-kpi-bottom">
          <span class="pastel-kpi-label text-blue">Raporlu ({{ summaryStats.sgkDaysCount }} Gün)</span>
          <ChevronRight :size="16" class="pastel-arrow" />
        </div>
      </article>

      <!-- 4. Açık Nane Yeşili Kart: Toplam Çalışılan -->
      <article class="pastel-kpi-card pastel-kpi-card--green">
        <div class="pastel-kpi-top">
          <strong class="pastel-kpi-number text-green">{{ summaryStats.totalWorkedDays }}</strong>
          <!-- Yeşil Dalga Grafiği SVG -->
          <svg class="pastel-sparkline text-green" viewBox="0 0 60 28" fill="none">
            <path
              d="M2 22C10 12 16 18 24 6C32 16 38 10 46 18C52 24 56 8 58 10"
              stroke="#10b981"
              stroke-width="2.5"
              stroke-linecap="round"
              stroke-linejoin="round"
            />
          </svg>
        </div>
        <div class="pastel-kpi-bottom">
          <span class="pastel-kpi-label text-green">Toplam Çalışılan Gün</span>
          <ChevronRight :size="16" class="pastel-arrow" />
        </div>
      </article>
    </div>

    <!-- 4. ARAMA VE FİLTRE SIRASI (Yan Yana) -->
    <div class="puantaj-search-filter-row mt-3 mb-3">
      <div class="puantaj-search-box">
        <Search :size="16" class="search-box-icon" />
        <input
          v-model="searchTerm"
          type="text"
          placeholder="Personel adı, departman veya TC ara..."
          class="search-box-input"
        />
        <button
          v-if="searchTerm"
          type="button"
          class="search-box-clear"
          @click="searchTerm = ''"
        >
          <X :size="13" />
        </button>
      </div>

      <!-- Filtreleme Butonu -->
      <button
        type="button"
        class="puantaj-filter-btn"
        :class="{ 'has-active-filter': filterDepartman || filterBolge || !filterIskur }"
        aria-label="Filtrele"
        @click="showFilterSheet = true"
      >
        <SlidersHorizontal :size="18" />
        <span
          v-if="filterDepartman || filterBolge || !filterIskur"
          class="filter-active-dot"
        ></span>
      </button>
    </div>

    <!-- 5. PERSONEL KARTLARI (Görsel 2'deki Beyaz Proje Kartı Tasarımı Birebir) -->
    <div v-if="loading && rawCalendarData.length === 0" class="clay-skeleton-grid">
      <div v-for="i in 4" :key="i" class="clay-skeleton-card clay-skeleton-card--md"></div>
    </div>

    <div v-else-if="filteredPersonelList.length === 0" class="clay-state-card">
      <UsersRound :size="32" class="text-muted" />
      <strong>Uygun Personel Bulunamadı</strong>
      <p>Arama veya filtre kriterlerinize uyan personel bulunmuyor.</p>
    </div>

    <div v-else class="project-cards-stack">
      <article
        v-for="p in filteredPersonelList"
        :key="p.id"
        class="project-card"
        @click="openPersonelDetail(p)"
      >
        <!-- Üst Satır: Takvim Çipi & Üç Nokta Menü -->
        <div class="project-card-header">
          <div class="project-date-pill">
            <Calendar :size="13" class="text-muted" />
            <span>1 - {{ daysInCurrentMonth }} {{ monthNamesTR[currentMonth - 1] }}</span>
          </div>

          <div class="project-card-actions">
            <span
              v-if="p.departman"
              class="project-dept-tag"
              :style="getDeptStyle(p.departman)"
            >
              {{ p.departman }}
            </span>
            <button
              type="button"
              class="project-more-btn"
              aria-label="İşlemler"
              @click.stop="openPersonelDetail(p)"
            >
              <MoreVertical :size="16" />
            </button>
          </div>
        </div>

        <!-- Orta Satır: Personel Adı ve Avatarı -->
        <div class="project-card-body">
          <div class="project-avatar">
            <img
              v-if="p.resim"
              :src="p.resim"
              :alt="p.adi_soyadi"
              class="project-avatar-img"
              @error="$event.target.style.display = 'none'"
            />
            <div class="project-avatar-txt">
              {{ getInitials(p.adi_soyadi) }}
            </div>
          </div>
          <div class="project-title-wrap">
            <h3 class="project-title">{{ p.adi_soyadi }}</h3>
            <span class="project-tc">TC: {{ p.tc_kimlik_no || '—' }}</span>
          </div>
        </div>

        <!-- YENİ: HAFTALIK PUANTAJ KAPSÜLLERİ (Kullanıcı Görselindeki Tasarım Birebir) -->
        <div class="weekly-schedule-box">
          <div class="weekly-schedule-header">
            <div class="weekly-header-text">
              <h4 class="weekly-title">Haftalık Puantaj</h4>
              <span class="weekly-subtitle">{{ weekRangeLabel }} • Değiştirmek için dokunun</span>
            </div>

            <!-- Ay Görünümü Butonu (Görseldeki [Calendar] Aug 2026 Butonu) -->
            <button
              type="button"
              class="weekly-month-btn"
              title="Aylık takvim görünümünü aç"
              @click.stop="openPersonelDetail(p)"
            >
              <Calendar :size="13" class="text-muted" />
              <span>{{ monthNamesTR[currentMonth - 1] }} {{ currentYear }}</span>
            </button>
          </div>

          <!-- 7 Sütunlu Gün Kapsülleri (Mon ... Sun) -->
          <div class="weekly-capsules-grid">
            <div
              v-for="dayObj in currentWeekDays"
              :key="dayObj.dateStr"
              class="weekly-day-col"
            >
              <!-- Gün Adı (Pzt ... Paz) -->
              <span
                class="weekly-day-label"
                :class="{ 'is-sun': dayObj.isSunday }"
              >
                {{ dayObj.dayName }}
              </span>

              <!-- Dikey Hap Kapsülü -->
              <button
                type="button"
                class="weekly-day-capsule"
                :class="{
                  'is-today': dayObj.isToday,
                  'is-sun': dayObj.isSunday,
                  'is-unsaved': getPersonelWeekDayEntry(p, dayObj).isUnsaved,
                }"
                :style="{
                  backgroundColor: dayObj.isSunday ? '#fff7ed' : '#f8fafc',
                  borderColor: getPersonelWeekDayEntry(p, dayObj).isUnsaved
                    ? '#f59e0b'
                    : (dayObj.isToday ? '#2563eb' : (dayObj.isSunday ? '#fed7aa' : '#e2e8f0')),
                }"
                @click.stop="handleWeekCellClick(p, dayObj)"
              >
                <!-- Gün Numarası -->
                <span
                  class="weekly-day-num"
                  :class="{
                    'text-blue': dayObj.isToday,
                    'text-rose': dayObj.isSunday,
                  }"
                >
                  {{ dayObj.dayNum }}
                </span>

                <!-- Yuvarlak Durum Rozeti -->
                <span
                  class="weekly-status-badge"
                  :style="{
                    backgroundColor: getPersonelWeekDayEntry(p, dayObj).theme.badgeBg,
                    color: getPersonelWeekDayEntry(p, dayObj).theme.badgeText,
                  }"
                >
                  {{ getPersonelWeekDayEntry(p, dayObj).code }}
                </span>

                <!-- Fazla Mesai Rozeti (Overlay) -->
                <span
                  v-if="getPersonelWeekDayEntry(p, dayObj).hasOvertime"
                  class="weekly-overtime-pill"
                >
                  +{{ getPersonelWeekDayEntry(p, dayObj).overtimeHours }}S
                </span>

                <!-- Kaydedilmemiş Değişiklik Glow Noktası -->
                <span
                  v-if="getPersonelWeekDayEntry(p, dayObj).isUnsaved"
                  class="weekly-unsaved-glow"
                ></span>
              </button>
            </div>
          </div>
        </div>

        <!-- Alt Satır: Renkli Durum Noktaları (Görsel 2'deki gibi!) -->
        <div class="project-card-footer">
          <div class="project-dot-item">
            <span class="dot-indicator dot-purple"></span>
            <span>Çalışılan: <strong>{{ getPersonelMonthStats(p).worked }} Gün</strong></span>
          </div>
          <div class="project-dot-item">
            <span class="dot-indicator dot-green"></span>
            <span>Devam: <strong>%{{ getPersonelMonthStats(p).rate }}</strong></span>
          </div>
          <div v-if="getPersonelMonthStats(p).overtimeHours > 0" class="project-dot-item">
            <span class="dot-indicator dot-purple"></span>
            <span>Mesai: <strong>{{ getPersonelMonthStats(p).overtimeHours }}s</strong></span>
          </div>
          <div v-if="getPersonelMonthStats(p).leave > 0" class="project-dot-item">
            <span class="dot-indicator dot-orange"></span>
            <span>İzin: <strong>{{ getPersonelMonthStats(p).leave }} G</strong></span>
          </div>
          <div v-if="getPersonelMonthStats(p).sgk > 0" class="project-dot-item">
            <span class="dot-indicator dot-red"></span>
            <span>Rapor: <strong>{{ getPersonelMonthStats(p).sgk }} G</strong></span>
          </div>
        </div>
      </article>
    </div>

    <!-- 6. YÜZEN KAYDETME ÇUBUĞU (Koyu Obsidian Clay Bar) -->
    <Transition name="slide-up">
      <div v-if="unsavedCount > 0" class="puantaj-floating-save-bar">
        <div class="save-bar-info">
          <span class="save-bar-counter">{{ unsavedCount }}</span>
          <div>
            <strong>Kaydedilmemiş Değişiklik</strong>
            <small>Değişiklikleri veritabanına işleyin</small>
          </div>
        </div>

        <div class="save-bar-actions">
          <button
            type="button"
            class="save-bar-discard-btn"
            :disabled="saving"
            @click="discardUnsavedChanges"
          >
            Vazgeç
          </button>
          <button
            type="button"
            class="save-bar-commit-btn"
            :disabled="saving"
            @click="savePuantajChanges"
          >
            <RefreshCw v-if="saving" :size="16" class="spin-anim" />
            <Save v-else :size="16" />
            <span>{{ saving ? 'Kaydediliyor...' : 'Kaydet' }}</span>
          </button>
        </div>
      </div>
    </Transition>

    <!-- 7. PERSONEL AY DETAY & TAKVİM BOTTOM SHEET (Görsel 2 Sağ Ekran Tarzı) -->
    <Transition name="sheet-slide">
      <div
        v-if="showDetailSheet && selectedPersonel"
        class="ds-sheet-overlay"
        @click.self="closePersonelDetail"
      >
        <div class="ds-sheet puantaj-detail-sheet" role="dialog" aria-modal="true">
          <div class="ds-sheet-handle"></div>

          <!-- Sheet Header (Sheet Başlığı ile Bütünleşik, Ayrı Köşeli Div Olmadan) -->
          <header class="detail-sheet-header">
            <div class="detail-sheet-user">
              <div class="project-avatar project-avatar--sm">
                <img
                  v-if="selectedPersonel.resim"
                  :src="selectedPersonel.resim"
                  :alt="selectedPersonel.adi_soyadi"
                  class="project-avatar-img"
                  @error="$event.target.style.display = 'none'"
                />
                <div class="project-avatar-txt">
                  {{ getInitials(selectedPersonel.adi_soyadi) }}
                </div>
              </div>
              <div class="detail-user-text">
                <h3 class="detail-user-name">{{ selectedPersonel.adi_soyadi }}</h3>
                <div class="detail-user-meta">
                  <span
                    v-if="selectedPersonel.departman"
                    class="project-dept-tag"
                    :style="getDeptStyle(selectedPersonel.departman)"
                  >
                    {{ selectedPersonel.departman }}
                  </span>
                  <span class="detail-user-tc">TC: {{ selectedPersonel.tc_kimlik_no || '—' }}</span>
                  <span class="detail-stat-pill">
                    {{ getPersonelMonthStats(selectedPersonel).worked }} Ç • {{ getPersonelMonthStats(selectedPersonel).leave }} İ • {{ getPersonelMonthStats(selectedPersonel).sgk }} R
                  </span>
                </div>
              </div>
            </div>

            <button
              type="button"
              class="clay-circle-btn"
              aria-label="Kapat"
              @click="closePersonelDetail"
            >
              <X :size="18" />
            </button>
          </header>

          <div class="ds-sheet-body hide-scrollbar">
            <!-- 1. HIZLI DAMGA MODU KUTUSU (TÜM TÜRLER GELİR) -->
            <section class="bento-card bento-card--white stamp-card-box">
              <div class="stamp-box-top">
                <div class="stamp-title-wrap">
                  <span class="stamp-box-title">HIZLI DAMGA MODU</span>
                  <small class="stamp-box-hint">Seçili türü takvimdeki günlere dokunarak anında atayın</small>
                </div>
                <span v-if="modalStampType" class="stamp-active-pill">
                  Aktif: {{ modalStampType.code }}
                </span>
              </div>

              <!-- Ücretli / Ücretsiz / Fazla Mesai Sekmeleri -->
              <div class="stamp-tabs">
                <button
                  type="button"
                  class="stamp-tab-btn"
                  :class="{ 'is-active': modalPaletteTab === 'ucretli' }"
                  @click="modalPaletteTab = 'ucretli'"
                >
                  Ücretli
                </button>
                <button
                  type="button"
                  class="stamp-tab-btn"
                  :class="{ 'is-active': modalPaletteTab === 'ucretsiz' }"
                  @click="modalPaletteTab = 'ucretsiz'"
                >
                  Ücretsiz
                </button>
                <button
                  type="button"
                  class="stamp-tab-btn"
                  :class="{ 'is-active': modalPaletteTab === 'fm' }"
                  @click="modalPaletteTab = 'fm'"
                >
                  ⚡ Fazla Mesai (+FM)
                </button>
              </div>

              <!-- Fazla Mesai Saat Çipleri -->
              <div v-if="modalPaletteTab === 'fm'" class="stamp-types-scroll hide-scrollbar">
                <button
                  v-for="hrs in quickOvertimeOptions"
                  :key="`stamp-fm-${hrs}`"
                  type="button"
                  class="stamp-chip"
                  :class="{ 'is-selected': modalStampType?.isFm && modalStampType?.hours === hrs }"
                  style="background-color: #7c3aed;"
                  @click="selectFmStamp(hrs)"
                >
                  <strong class="stamp-chip-code">+{{ hrs }}s</strong>
                  <span class="stamp-chip-name">{{ hrs === 4 ? 'Standart' : 'Mesai' }}</span>
                </button>
                <button
                  type="button"
                  class="stamp-chip"
                  :class="{ 'is-selected': modalStampType?.isFm && modalStampType?.hours === 0 }"
                  style="background-color: #dc2626;"
                  @click="selectFmStamp(0)"
                >
                  <strong class="stamp-chip-code">0s</strong>
                  <span class="stamp-chip-name">Mesai Sil</span>
                </button>
              </div>

              <!-- Puantaj Türleri Çipleri (Scrollable Yatay Liste - Scrollbar Gizli) -->
              <div v-else class="stamp-types-scroll hide-scrollbar">
                <button
                  v-for="t in availablePaletteList"
                  :key="t.code"
                  type="button"
                  class="stamp-chip"
                  :class="{ 'is-selected': modalStampType?.code === t.code }"
                  :style="{
                    backgroundColor: t.color,
                  }"
                  @click="toggleModalStamp(t)"
                >
                  <strong class="stamp-chip-code">{{ t.code }}</strong>
                  <span class="stamp-chip-name">{{ t.name }}</span>
                </button>
              </div>

              <!-- Damga Modu Açık Banner'ı -->
              <div v-if="modalStampType" class="stamp-selected-banner">
                <div class="banner-text">
                  <CheckCircle2 :size="15" />
                  <span>Damgalama Aktif: <strong>{{ modalStampType.name || modalStampType.code }}</strong></span>
                </div>
                <button type="button" class="banner-cancel-btn" @click="modalStampType = null">
                  İptal Et
                </button>
              </div>
            </section>

            <!-- 2. AY İÇİ GÜNLÜK TAKVİM (Görsel 2 Sağ Ekrandaki Zarif Pastel Takvim) -->
            <section class="bento-card bento-card--white clean-calendar-box">
              <div class="clean-calendar-head">
                <div>
                  <h4 class="calendar-section-title">Ay İçi Günlük Takvim</h4>
                  <small class="calendar-section-hint">
                    {{ modalStampType ? 'Güne dokunarak seçili statüyü damgalayın' : 'Güne dokunarak statüyü değiştirin (X → HT → Yİ → SGK)' }}
                  </small>
                </div>
                <div class="calendar-month-indicator">
                  <Calendar :size="12" class="text-muted" />
                  <span>{{ monthNamesTR[currentMonth - 1] }} {{ currentYear }}</span>
                </div>
              </div>

              <!-- Gün Başlıkları (Pzt ... Paz) -->
              <div class="clean-days-header">
                <span
                  v-for="(dayName, dIdx) in dayNamesTR"
                  :key="dayName"
                  :class="{ 'is-sun': dIdx === 6 }"
                >
                  {{ dayName }}
                </span>
              </div>

              <!-- Gün Hücreleri Izgarası (Pastel Tiles) -->
              <div class="calendar-tiles-grid">
                <template v-for="cell in modalCalendarGridCells" :key="cell.key">
                  <div v-if="cell.empty" class="calendar-tile calendar-tile--empty"></div>
                  <button
                    v-else
                    type="button"
                    class="calendar-tile"
                    :class="{
                      'is-sunday': cell.isSunday,
                      'is-unsaved': cell.isUnsaved,
                    }"
                    :style="{
                      backgroundColor: cell.theme.bg,
                      borderColor: cell.isUnsaved ? '#f59e0b' : cell.theme.border,
                    }"
                    @click="handleCellClick(cell)"
                  >
                    <span
                      class="calendar-tile-day"
                      :style="{ color: cell.isSunday ? '#dc2626' : cell.theme.text }"
                    >
                      {{ cell.day }}
                    </span>
                    <span
                      class="calendar-tile-badge"
                      :style="{
                        backgroundColor: cell.theme.badgeBg,
                        color: cell.theme.badgeText,
                      }"
                    >
                      {{ cell.code }}
                    </span>
                    <span
                      v-if="cell.hasOvertime"
                      class="calendar-tile-overtime-pill"
                    >
                      +{{ cell.overtimeHours }}S
                    </span>
                    <span v-if="cell.isUnsaved" class="tile-unsaved-dot"></span>
                  </button>
                </template>
              </div>
            </section>

            <!-- 3. TARİH ARALIĞI İLE STATÜ TANIMLA -->
            <section class="bento-card bento-card--white range-accordion-box">
              <button
                type="button"
                class="range-accordion-trigger"
                @click="showRangeCard = !showRangeCard"
              >
                <div class="range-trigger-title">
                  <CalendarRange :size="18" class="text-blue" />
                  <strong>Tarih Aralığı ile Statü Tanımla</strong>
                </div>
                <ChevronDown
                  :size="18"
                  class="text-muted transition-transform"
                  :class="{ 'rotate-180': showRangeCard }"
                />
              </button>

              <div v-if="showRangeCard" class="range-accordion-content">
                <div class="range-inputs-grid">
                  <div>
                    <label class="range-label">Başlangıç Günü</label>
                    <select v-model="rangeStartDay" class="range-select">
                      <option v-for="d in daysInCurrentMonth" :key="`start-${d}`" :value="d">
                        {{ d }} {{ monthNamesTR[currentMonth - 1] }}
                      </option>
                    </select>
                  </div>
                  <div>
                    <label class="range-label">Bitiş Günü</label>
                    <select v-model="rangeEndDay" class="range-select">
                      <option v-for="d in daysInCurrentMonth" :key="`end-${d}`" :value="d">
                        {{ d }} {{ monthNamesTR[currentMonth - 1] }}
                      </option>
                    </select>
                  </div>
                </div>

                <div class="mt-2">
                  <label class="range-label">Statü / İzin Türü</label>
                  <select v-model="rangeStatusType" class="range-select">
                    <option :value="0">X - Çalışılan Gün (Sıfırla)</option>
                    <optgroup label="Ücretli">
                      <option
                        v-for="t in availablePaletteList.filter(x => x.ucretli)"
                        :key="`ucretli-${t.id}-${t.code}`"
                        :value="t.id"
                      >
                        {{ t.code }} - {{ t.name }}
                      </option>
                    </optgroup>
                    <optgroup label="Ücretsiz">
                      <option
                        v-for="t in availablePaletteList.filter(x => !x.ucretli)"
                        :key="`ucretsiz-${t.id}-${t.code}`"
                        :value="t.id"
                      >
                        {{ t.code }} - {{ t.name }}
                      </option>
                    </optgroup>
                  </select>
                </div>

                <button
                  type="button"
                  class="range-submit-btn"
                  @click="applyRangeStatus"
                >
                  <Check :size="16" />
                  <span>Aralığa Statüyü Uygula</span>
                </button>
              </div>
            </section>
          </div>
        </div>
      </div>
    </Transition>

    <!-- 8. SGK VİZİTE RAPORLARI SHEET -->
    <Transition name="sheet-slide">
      <div
        v-if="showSgkSheet"
        class="ds-sheet-overlay"
        @click.self="showSgkSheet = false"
      >
        <div class="ds-sheet sgk-reports-sheet" role="dialog" aria-modal="true">
          <div class="ds-sheet-handle"></div>
          <header class="ds-sheet-header">
            <div class="sgk-header-title">
              <span class="welcome-icon-btn welcome-icon-btn--rose">
                <Stethoscope :size="18" />
              </span>
              <div>
                <h3 class="font-bold text-sm">SGK Vizite Raporları</h3>
                <small class="text-muted">Onaylanmış hastalık & istirahat raporları</small>
              </div>
            </div>
            <button
              type="button"
              class="clay-circle-btn"
              aria-label="Kapat"
              @click="showSgkSheet = false"
            >
              <X :size="18" />
            </button>
          </header>

          <div class="ds-sheet-body">
            <div v-if="sgkLoading" class="clay-skeleton-grid">
              <div v-for="i in 3" :key="i" class="clay-skeleton-card clay-skeleton-card--sm"></div>
            </div>

            <div v-else-if="sgkError" class="clay-state-card" role="alert">
              <AlertCircle :size="28" class="text-rose" />
              <strong>SGK Bilgileri</strong>
              <p>{{ sgkError }}</p>
            </div>

            <div v-else-if="sgkReports.length === 0" class="clay-state-card">
              <CheckCircle2 :size="32" class="text-emerald" />
              <strong>Kayıtlı SGK Raporu Yok</strong>
              <p>Seçili dönem için sisteme düşen onaylı istirahat raporu bulunamadı.</p>
            </div>

            <div v-else class="project-cards-stack">
              <article
                v-for="(r, idx) in sgkReports"
                :key="idx"
                class="project-card"
              >
                <div class="project-card-header">
                  <div class="project-date-pill">
                    <CalendarRange :size="13" class="text-rose" />
                    <span>{{ r.baslangic_raw || r.baslangic }} - {{ r.bitis_raw || r.bitis }}</span>
                  </div>
                  <span class="project-dept-tag" style="background: #ffe4e6; color: #e11d48;">
                    {{ r.toplam_gun }} Gün
                  </span>
                </div>

                <div class="project-card-body">
                  <div class="project-title-wrap">
                    <h3 class="project-title">{{ r.ad_soyad }}</h3>
                    <span class="project-tc">TC: {{ r.tc_kimlik || '—' }} • {{ r.vaka_adi || 'Hastalık' }}</span>
                  </div>
                </div>

                <div v-if="r.personel_id" class="mt-2">
                  <button
                    type="button"
                    class="range-submit-btn"
                    style="background: #059669;"
                    @click="applySgkReport(r)"
                  >
                    <Check :size="16" />
                    <span>Puantaja İşle (SGK)</span>
                  </button>
                </div>
                <div v-else class="text-xs text-rose font-bold text-center mt-2">
                  Personel T.C. numarası sistemle eşleşmedi
                </div>
              </article>
            </div>
          </div>
        </div>
      </div>
    </Transition>

    <!-- 9. HIZLI GÜN STATÜSÜ SEÇİCİ BOTTOM SHEET (Görsel 1 İsteği) -->
    <Transition name="sheet-slide">
      <div
        v-if="showStatusPickerSheet && pickerTargetPersonel && pickerTargetDay"
        class="ds-sheet-overlay"
        @click.self="closeDayStatusPicker"
      >
        <div class="ds-sheet status-picker-sheet" role="dialog" aria-modal="true">
          <div class="ds-sheet-handle"></div>

          <!-- Sheet Header -->
          <header class="status-picker-header">
            <div class="picker-header-user">
              <div class="project-avatar project-avatar--sm">
                <img
                  v-if="pickerTargetPersonel.resim"
                  :src="pickerTargetPersonel.resim"
                  :alt="pickerTargetPersonel.adi_soyadi"
                  class="project-avatar-img"
                  @error="$event.target.style.display = 'none'"
                />
                <div class="project-avatar-txt">
                  {{ getInitials(pickerTargetPersonel.adi_soyadi) }}
                </div>
              </div>
              <div class="picker-user-info">
                <h3 class="picker-user-name">{{ pickerTargetPersonel.adi_soyadi }}</h3>
                <div class="picker-target-date">
                  <Calendar :size="12" class="text-blue" />
                  <span>{{ pickerTargetDay.dayNum }} {{ pickerTargetDay.monthName }} {{ currentYear }} ({{ pickerTargetDay.dayName }})</span>
                </div>
              </div>
            </div>

            <button
              type="button"
              class="clay-circle-btn"
              aria-label="Kapat"
              @click="closeDayStatusPicker"
            >
              <X :size="18" />
            </button>
          </header>

          <div class="ds-sheet-body hide-scrollbar">
            <!-- Mevcut Durum Kartı (Statü + Varsa Fazla Mesai Rozeti) -->
            <div v-if="currentPickerDayEntry" class="current-status-banner">
              <span class="banner-title">Mevcut Durum:</span>
              <div class="banner-badges-group">
                <div class="banner-status-badge" :style="{ backgroundColor: currentPickerDayEntry.theme.badgeBg }">
                  <strong>{{ currentPickerDayEntry.code }}</strong>
                  <span>{{ currentPickerDayEntry.name || currentPickerDayEntry.code }}</span>
                </div>
                <div v-if="pickerCurrentOvertime > 0" class="banner-overtime-badge">
                  <Clock :size="12" />
                  <span>+{{ pickerCurrentOvertime }}s Mesai</span>
                </div>
              </div>
            </div>

            <!-- Ücretli / Ücretsiz / Fazla Mesai Sekmeleri -->
            <div class="stamp-tabs">
              <button
                type="button"
                class="stamp-tab-btn"
                :class="{ 'is-active': pickerActiveTab === 'ucretli' }"
                @click="pickerActiveTab = 'ucretli'"
              >
                Ücretli İzin
              </button>
              <button
                type="button"
                class="stamp-tab-btn"
                :class="{ 'is-active': pickerActiveTab === 'ucretsiz' }"
                @click="pickerActiveTab = 'ucretsiz'"
              >
                Ücretsiz İzin
              </button>
              <button
                type="button"
                class="stamp-tab-btn"
                :class="{ 'is-active': pickerActiveTab === 'fm' }"
                @click="pickerActiveTab = 'fm'"
              >
                ⚡ Fazla Mesai{{ pickerCurrentOvertime > 0 ? ` (+${pickerCurrentOvertime}s)` : '' }}
              </button>
            </div>

            <!-- 1. FAZLA MESAİ SEKME İÇERİĞİ -->
            <div v-if="pickerActiveTab === 'fm'" class="picker-fm-tab-pane">
              <!-- Kesme Departmanı Uyarısı (Masaüstü Standartlarında Kart İçi) -->
              <div
                v-if="isKesmeDepartment(pickerTargetPersonel.departman)"
                class="picker-kesme-alert mb-2"
              >
                <AlertCircle :size="16" class="kesme-alert-icon" />
                <div class="kesme-alert-text">
                  <strong>Kesme Departmanı Uyarısı</strong>
                  <p>Kesme personellerinin fazla mesaileri normal şartlarda Nöbet modülü üzerinden otomatik gelmektedir.</p>
                </div>
              </div>

              <!-- Hızlı Saat Seçimi (4 Sütunlu Kompakt Izgara - Kesilme Yok) -->
              <div class="picker-fm-section mb-2">
                <div class="picker-fm-section-title">HIZLI SAAT SEÇİMİ</div>
                <div class="picker-fm-grid">
                  <button
                    v-for="hrs in quickOvertimeOptions"
                    :key="hrs"
                    type="button"
                    class="picker-fm-tile"
                    :class="{ 'is-selected': pickerCurrentOvertime === hrs }"
                    @click="setPickerOvertime(hrs)"
                  >
                    <span class="picker-fm-tile-hours">{{ hrs }}s</span>
                    <span v-if="hrs === 4" class="picker-fm-tile-tag">Standart</span>
                  </button>
                </div>
              </div>

              <!-- Manuel Saat Girişi (Büyük Sayı + Step Butonları + Kaydet / Kaldır) -->
              <div class="picker-fm-section">
                <div class="picker-fm-section-title">MANUEL SAAT GİRİŞİ</div>
                <div class="picker-fm-manual-row">
                  <div class="picker-fm-stepper">
                    <button
                      type="button"
                      class="picker-fm-step-btn"
                      aria-label="0.5 saat azalt"
                      @click="stepOvertime(-0.5)"
                    >
                      <Minus :size="14" />
                    </button>
                    <div class="picker-fm-input-wrap">
                      <input
                        v-model="customOvertimeInput"
                        type="number"
                        step="0.5"
                        min="0"
                        max="24"
                        placeholder="4.0"
                        class="picker-fm-stepper-input"
                        @keydown.enter.prevent="applyCustomOvertime"
                      />
                      <span class="picker-fm-unit">Saat</span>
                    </div>
                    <button
                      type="button"
                      class="picker-fm-step-btn"
                      aria-label="0.5 saat arttır"
                      @click="stepOvertime(0.5)"
                    >
                      <Plus :size="14" />
                    </button>
                  </div>

                  <button
                    type="button"
                    class="picker-fm-commit-btn"
                    :disabled="!customOvertimeInput || parseFloat(customOvertimeInput) < 0"
                    @click="applyCustomOvertime"
                  >
                    <Check :size="15" />
                    <span>{{ pickerCurrentOvertime > 0 ? 'Güncelle' : 'Kaydet' }}</span>
                  </button>

                  <button
                    v-if="pickerCurrentOvertime > 0"
                    type="button"
                    class="picker-fm-remove-btn"
                    title="Mesaiyi Kaldır"
                    @click="setPickerOvertime(0)"
                  >
                    <Trash2 :size="14" />
                    <span>Kaldır</span>
                  </button>
                </div>
              </div>
            </div>

            <!-- 2. ÜCRETLİ / ÜCRETSİZ İZİN SEKME İÇERİĞİ -->
            <div v-else class="picker-status-tab-pane">
              <!-- Hızlı Seçenek: Normal Çalışılan Güne Döndür (X) (Ücretli sekmesinde) -->
              <button
                v-if="pickerActiveTab === 'ucretli'"
                type="button"
                class="quick-reset-work-btn mb-2"
                @click="selectStatusForDay({ id: 0, code: 'X', name: 'Çalışılan Gün' })"
              >
                <span class="reset-badge">X</span>
                <div class="reset-text">
                  <strong>Normal Çalışılan Gün Olarak Ayarla</strong>
                  <small>Bu güne ait izin veya tatil kaydını sıfırlar</small>
                </div>
                <Check v-if="currentPickerDayEntry?.code === 'X'" :size="16" class="text-blue" />
              </button>

              <!-- Statü Seçim Listesi (Kartlar) -->
              <div class="status-options-grid">
                <button
                  v-for="t in pickerPaletteList"
                  :key="t.code"
                  type="button"
                  class="status-option-tile"
                  :class="{ 'is-selected': currentPickerDayEntry?.code === t.code }"
                  @click="selectStatusForDay(t)"
                >
                  <div class="option-tile-badge" :style="{ backgroundColor: t.color }">
                    {{ t.code }}
                  </div>
                  <span class="option-tile-name">{{ t.name }}</span>
                  <Check v-if="currentPickerDayEntry?.code === t.code" :size="16" class="option-check-icon text-emerald" />
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Transition>

    <!-- 10. FİLTRE ÇEKMECESİ -->
    <Transition name="sheet-slide">
      <div
        v-if="showFilterSheet"
        class="ds-sheet-overlay"
        @click.self="showFilterSheet = false"
      >
        <div class="ds-sheet filter-sheet" role="dialog" aria-modal="true">
          <div class="ds-sheet-handle"></div>
          <header class="ds-sheet-header">
            <h3 class="font-bold text-sm">Filtreleme Seçenekleri</h3>
            <button
              type="button"
              class="clay-circle-btn"
              aria-label="Kapat"
              @click="showFilterSheet = false"
            >
              <X :size="18" />
            </button>
          </header>

          <div class="ds-sheet-body">
            <div>
              <label class="range-label">Departman</label>
              <select v-model="filterDepartman" class="range-select">
                <option value="">Tüm Departmanlar</option>
                <option v-for="d in definitions.departmanlar" :key="d" :value="d">
                  {{ d }}
                </option>
              </select>
            </div>

            <div class="mt-3">
              <label class="range-label">Bölge / Ekip</label>
              <select v-model="filterBolge" class="range-select">
                <option value="">Tüm Bölgeler</option>
                <option v-for="b in definitions.bolgeler" :key="b" :value="b">
                  {{ b }}
                </option>
              </select>
            </div>

            <div class="flex items-center gap-3 mt-4">
              <input v-model="filterIskur" type="checkbox" id="iskurToggle" class="w-4 h-4 rounded text-blue" />
              <label for="iskurToggle" class="text-xs font-bold text-slate-700 cursor-pointer">
                İŞKUR Personelleri Dahil
              </label>
            </div>

            <div class="flex gap-2 mt-5">
              <button
                type="button"
                class="save-bar-discard-btn flex-1 text-center py-2.5 rounded-xl border border-slate-200"
                @click="filterDepartman = ''; filterBolge = ''; filterIskur = true; showFilterSheet = false; loadCalendarData()"
              >
                Sıfırla
              </button>
              <button
                type="button"
                class="range-submit-btn flex-2"
                @click="showFilterSheet = false; loadCalendarData()"
              >
                Uygula
              </button>
            </div>
          </div>
        </div>
      </div>
    </Transition>

    <!-- 10. AY SEÇİCİ SHEET -->
    <Transition name="sheet-slide">
      <div
        v-if="showMonthPicker"
        class="ds-sheet-overlay"
        @click.self="showMonthPicker = false"
      >
        <div class="ds-sheet month-picker-sheet" role="dialog" aria-modal="true">
          <div class="ds-sheet-handle"></div>
          <header class="ds-sheet-header">
            <div class="flex items-center gap-3 text-sm font-bold">
              <button type="button" class="period-chevron-btn" @click="pickerYear--">
                <ChevronLeft :size="16" />
              </button>
              <span class="font-extrabold text-base">{{ pickerYear }}</span>
              <button type="button" class="period-chevron-btn" @click="pickerYear++">
                <ChevronRight :size="16" />
              </button>
            </div>
            <button
              type="button"
              class="clay-circle-btn"
              aria-label="Kapat"
              @click="showMonthPicker = false"
            >
              <X :size="18" />
            </button>
          </header>

          <div class="ds-sheet-body">
            <div class="grid grid-cols-3 gap-2">
              <button
                v-for="(mName, mIdx) in monthNamesTR"
                :key="mName"
                type="button"
                class="py-3 rounded-2xl text-xs font-bold transition-all text-center"
                :class="pickerYear === currentYear && currentMonth === (mIdx + 1)
                  ? 'bg-slate-900 text-white shadow-md'
                  : 'bg-white text-slate-800 border border-slate-100 hover:bg-slate-50'"
                @click="selectMonthFromPicker(mIdx)"
              >
                {{ mName }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </Transition>

    <!-- 11. ONAY DİYALOĞU -->
    <Transition name="fade">
      <div v-if="showUnsavedPrompt" class="prompt-modal-overlay">
        <div class="bento-card bento-card--white prompt-modal-box">
          <div class="prompt-icon-wrap">
            <AlertCircle :size="32" class="text-amber" />
          </div>
          <h3 class="font-bold text-base mb-1">Kaydedilmemiş Değişiklikler</h3>
          <p class="text-xs text-muted mb-4 leading-relaxed">
            Puantajda yaptığınız değişiklikleri kaydetmeden ayrılırsanız veriler kaybolacaktır.
            Yine de devam etmek istiyor musunuz?
          </p>
          <div class="flex gap-2">
            <button type="button" class="save-bar-discard-btn flex-1 py-2.5 rounded-xl border border-slate-200" @click="cancelPendingAction">
              İptal
            </button>
            <button type="button" class="range-submit-btn flex-1 py-2.5 bg-rose-600 shadow-rose-600/30" @click="executePendingAction">
              Devam Et
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- 12. TOAST -->
    <Transition name="fade">
      <div v-if="toastMessage" class="puantaj-toast" :class="`is-${toastType}`">
        <CheckCircle2 v-if="toastType === 'success'" :size="18" />
        <AlertCircle v-else :size="18" />
        <span>{{ toastMessage }}</span>
      </div>
    </Transition>
  </section>
</template>

<style scoped>
.puantaj-screen {
  padding-bottom: calc(var(--ds-nav-clearance) + 40px);
  background: #f4f6fa;
}

/* 1. ÜST HEADER (Welcome Bar) */
.app-welcome-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20px;
}

.welcome-user-info {
  display: flex;
  align-items: center;
  gap: 12px;
}

.welcome-avatar {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  background: #1e293b;
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 15px;
  font-weight: 800;
  box-shadow: 0 4px 12px rgba(15, 23, 42, 0.15);
}

.welcome-subtitle {
  display: block;
  font-size: 11px;
  font-weight: 600;
  color: #94a3b8;
  letter-spacing: -0.1px;
}

.welcome-title {
  font-size: 17px;
  font-weight: 800;
  color: #1e293b;
  margin: 0;
  letter-spacing: -0.3px;
}

.welcome-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.welcome-icon-btn {
  position: relative;
  width: 42px;
  height: 42px;
  border-radius: 50%;
  background: #ffffff;
  border: 1px solid rgba(255, 255, 255, 0.9);
  color: #1e293b;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  box-shadow: 0 4px 14px rgba(15, 23, 42, 0.06);
  transition: transform 0.15s ease;
}

.welcome-icon-btn:active {
  transform: scale(0.92);
}

.welcome-icon-btn--rose {
  color: #e11d48;
}

.welcome-badge-dot {
  position: absolute;
  top: 10px;
  right: 10px;
  width: 7px;
  height: 7px;
  background: #e11d48;
  border-radius: 50%;
  border: 1.5px solid #ffffff;
}

/* 2. OVERVIEW BAŞLIĞI */
.overview-section-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
}

.overview-heading {
  font-size: 18px;
  font-weight: 800;
  color: #1e293b;
  margin: 0;
  letter-spacing: -0.3px;
}

.overview-caption {
  font-size: 11px;
  color: #64748b;
  font-weight: 600;
}

.overview-period-ctrls {
  display: flex;
  align-items: center;
  gap: 6px;
  background: #ffffff;
  padding: 4px;
  border-radius: var(--ds-radius-pill);
  box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
}

.period-chevron-btn {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  border: none;
  background: #f1f5f9;
  color: #1e293b;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.period-trigger-pill {
  display: flex;
  align-items: center;
  gap: 4px;
  background: none;
  border: none;
  font-size: 12px;
  font-weight: 800;
  color: #1e293b;
  padding: 0 6px;
  cursor: pointer;
  text-transform: uppercase;
}

/* 3. 2x2 PASTEL BENTO KARTLARI (Görsel 2'deki Birebir) */
.overview-2x2-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  margin-bottom: 22px;
}

.pastel-kpi-card {
  border-radius: 22px;
  padding: 16px 14px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  min-height: 104px;
  cursor: pointer;
  box-shadow: 0 6px 18px rgba(15, 23, 42, 0.04);
  border: 2px solid transparent;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.pastel-kpi-card:active {
  transform: scale(0.97);
}

.pastel-kpi-card--purple {
  background: #f3effe;
}

.pastel-kpi-card--peach {
  background: #fef4ec;
}

.pastel-kpi-card--blue {
  background: #ebf7fd;
}

.pastel-kpi-card--green {
  background: #ebf8f2;
}

.pastel-kpi-card.is-selected-filter {
  border-color: #1e293b;
  box-shadow: 0 8px 24px rgba(15, 23, 42, 0.12);
}

.pastel-kpi-top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
}

.pastel-kpi-number {
  font-size: 26px;
  font-weight: 900;
  line-height: 1;
  color: #6b21a8;
  letter-spacing: -0.5px;
}

.text-peach { color: #c2410c !important; }
.text-blue { color: #0369a1 !important; }
.text-green { color: #047857 !important; }

.pastel-sparkline {
  width: 52px;
  height: 24px;
}

.pastel-kpi-bottom {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 12px;
}

.pastel-kpi-label {
  font-size: 12px;
  font-weight: 700;
  color: #6b21a8;
}

.pastel-arrow {
  opacity: 0.6;
}

/* 4. PROJECTS / PERSONEL LİSTESİ BAŞLIĞI */
.projects-section-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.projects-tabs-row {
  display: flex;
  align-items: center;
  gap: 6px;
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}

.project-pill-tab {
  padding: 8px 14px;
  border-radius: var(--ds-radius-pill);
  font-size: 12px;
  font-weight: 700;
  border: none;
  background: #ffffff;
  color: #64748b;
  white-space: nowrap;
  cursor: pointer;
  box-shadow: 0 2px 6px rgba(15, 23, 42, 0.04);
  transition: all 0.15s ease;
}

/* 4. ARAMA VE FİLTRE SIRASI (Yan Yana) */
.puantaj-search-filter-row {
  display: flex;
  align-items: center;
  gap: 10px;
}

.puantaj-search-box {
  flex: 1;
  position: relative;
  display: flex;
  align-items: center;
}

.search-box-icon {
  position: absolute;
  left: 14px;
  color: #94a3b8;
  pointer-events: none;
}

.search-box-input {
  width: 100%;
  height: 44px;
  padding: 0 36px 0 38px;
  border-radius: var(--ds-radius-pill);
  border: 1px solid rgba(255, 255, 255, 0.9);
  background: #ffffff;
  color: #1e293b;
  font-size: 13px;
  box-shadow: 0 2px 10px rgba(15, 23, 42, 0.04);
  outline: none;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.search-box-input:focus {
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
}

.search-box-clear {
  position: absolute;
  right: 12px;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: #e2e8f0;
  color: #64748b;
  border: none;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.puantaj-filter-btn {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  background: #ffffff;
  border: 1px solid rgba(255, 255, 255, 0.9);
  color: #1e293b;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  box-shadow: 0 2px 10px rgba(15, 23, 42, 0.04);
  flex-shrink: 0;
  position: relative;
  transition: transform 0.12s ease, background-color 0.15s ease;
}

.puantaj-filter-btn:active {
  transform: scale(0.92);
}

.puantaj-filter-btn.has-active-filter {
  background: #e0f2fe;
  color: #0284c7;
  border-color: #bae6fd;
}

.filter-active-dot {
  position: absolute;
  top: 9px;
  right: 9px;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #0284c7;
  border: 2px solid #ffffff;
}

/* 5. PERSONEL KARTLARI (Görsel 2 Proje Kartı) */
.project-cards-stack {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.project-card {
  background: #ffffff;
  border-radius: 22px;
  padding: 16px;
  box-shadow: 0 8px 24px -8px rgba(15, 23, 42, 0.06);
  border: 1px solid rgba(255, 255, 255, 0.8);
  cursor: pointer;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.project-card:active {
  transform: scale(0.98);
}

.project-card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
}

.project-date-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: #f8fafc;
  border: 1px solid #f1f5f9;
  padding: 4px 10px;
  border-radius: var(--ds-radius-pill);
  font-size: 11px;
  font-weight: 600;
  color: #64748b;
}

.project-card-actions {
  display: flex;
  align-items: center;
  gap: 6px;
}

.project-dept-tag {
  font-size: 10px;
  font-weight: 800;
  padding: 2px 8px;
  border-radius: 6px;
  white-space: nowrap;
}

.project-more-btn {
  background: none;
  border: none;
  color: #94a3b8;
  padding: 2px;
  cursor: pointer;
  display: flex;
  align-items: center;
}

.project-card-body {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 14px;
}

.project-avatar {
  width: 42px;
  height: 42px;
  border-radius: 50%;
  background: #f1f5f9;
  border: 2px solid #e2e8f0;
  overflow: hidden;
  flex-shrink: 0;
}

.project-avatar--sm {
  width: 38px;
  height: 38px;
}

.project-avatar-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.project-avatar-txt {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  font-weight: 800;
  color: #1e293b;
  background: #f1f5f9;
}

.project-title-wrap {
  flex: 1;
  min-width: 0;
}

.project-title {
  font-size: 15px;
  font-weight: 800;
  color: #1e293b;
  margin: 0 0 2px 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.project-tc {
  font-size: 11px;
  font-weight: 600;
  color: #94a3b8;
}

/* YENİ: HAFTALIK PUANTAJ KUTUSU VE KAPSÜLLERİ (Kullanıcı Görseli) */
.weekly-schedule-box {
  background: #ffffff;
  border-radius: 20px;
  border: 1px solid #f1f5f9;
  padding: 12px 14px 14px;
  margin: 10px 0 12px;
  box-shadow: 0 2px 8px rgba(15, 23, 42, 0.02);
}

.weekly-schedule-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 12px;
}

.weekly-header-text {
  display: flex;
  flex-direction: column;
  gap: 1px;
}

.weekly-title {
  font-size: 14px;
  font-weight: 800;
  color: #0f172a;
  margin: 0;
  letter-spacing: -0.2px;
}

.weekly-subtitle {
  font-size: 10.5px;
  font-weight: 600;
  color: #64748b;
}

.weekly-month-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  padding: 5px 10px;
  border-radius: var(--ds-radius-pill);
  font-size: 11px;
  font-weight: 700;
  color: #334155;
  cursor: pointer;
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
  transition: all 0.15s ease;
  white-space: nowrap;
}

.weekly-month-btn:active {
  transform: scale(0.94);
  background: #f8fafc;
}

/* 7 Sütunlu Gün Kapsülleri Izgarası */
.weekly-capsules-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 6px;
}

.weekly-day-col {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
}

.weekly-day-label {
  font-size: 11px;
  font-weight: 700;
  color: #64748b;
  text-align: center;
}

.weekly-day-label.is-sun {
  color: #ef4444;
  font-weight: 800;
}

.weekly-day-capsule {
  width: 100%;
  aspect-ratio: 1 / 1.7;
  border-radius: 20px;
  border: 1.5px solid #e2e8f0;
  background: #f8fafc;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: space-between;
  padding: 7px 2px 6px;
  cursor: pointer;
  position: relative;
  transition: all 0.15s cubic-bezier(0.16, 1, 0.3, 1);
  min-height: 58px;
}

.weekly-day-capsule:active {
  transform: scale(0.90);
}

.weekly-day-capsule.is-today {
  border: 2px solid #2563eb !important;
  background: #ffffff !important;
  box-shadow: 0 4px 14px rgba(37, 99, 235, 0.18);
  transform: translateY(-1px);
}

.weekly-day-capsule.is-sun {
  background: #fff7ed !important;
  border-color: #fed7aa !important;
}

.weekly-day-capsule.is-unsaved {
  border-color: #f59e0b !important;
  box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.45) !important;
}

.weekly-day-num {
  font-size: 12px;
  font-weight: 800;
  color: #1e293b;
  line-height: 1;
}

.weekly-day-num.text-blue {
  color: #2563eb;
  font-weight: 900;
}

.weekly-day-num.text-rose {
  color: #ea580c;
  font-weight: 900;
}

.weekly-status-badge {
  width: 22px;
  height: 22px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 9px;
  font-weight: 900;
  line-height: 1;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.14);
}

.weekly-overtime-pill {
  position: absolute;
  top: -6px;
  right: -5px;
  background: #7c3aed;
  color: #ffffff;
  font-size: 9px;
  font-weight: 800;
  line-height: 1;
  padding: 2.5px 5px;
  border-radius: 9px;
  box-shadow: 0 2px 6px rgba(124, 58, 237, 0.45);
  border: 1.5px solid #ffffff;
  z-index: 2;
  white-space: nowrap;
}

.weekly-unsaved-glow {
  position: absolute;
  top: 3px;
  right: 3px;
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #f59e0b;
  box-shadow: 0 0 0 1.5px #ffffff, 0 0 4px #f59e0b;
}

/* Görsel 2 Alt Satır: Renkli Durum Noktaları */
.project-card-footer {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
  padding-top: 10px;
  border-top: 1px solid #f1f5f9;
}

.project-dot-item {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 11px;
  color: #64748b;
}

.project-dot-item strong {
  color: #1e293b;
}

.dot-indicator {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  flex-shrink: 0;
}

.dot-purple { background: #8b5cf6; }
.dot-green { background: #10b981; }
.dot-orange { background: #f97316; }
.dot-red { background: #ef4444; }

/* 6. YÜZEN KAYDETME BARI */
.puantaj-floating-save-bar {
  position: fixed;
  bottom: calc(var(--ds-nav-clearance) - 10px);
  left: 16px;
  right: 16px;
  background: #191d24;
  color: #ffffff;
  padding: 12px 16px;
  border-radius: 24px;
  box-shadow: 0 20px 48px -8px rgba(0, 0, 0, 0.45);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  z-index: 80;
  border: 1px solid rgba(255, 255, 255, 0.12);
}

.save-bar-info {
  display: flex;
  align-items: center;
  gap: 10px;
}

.save-bar-counter {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: rgba(245, 158, 11, 0.25);
  color: #fbbf24;
  border: 1px solid rgba(245, 158, 11, 0.4);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  font-weight: 800;
}

.save-bar-info strong {
  display: block;
  font-size: 12px;
  font-weight: 800;
  line-height: 1.2;
}

.save-bar-info small {
  display: block;
  font-size: 10px;
  color: #94a3b8;
}

.save-bar-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.save-bar-discard-btn {
  background: none;
  border: none;
  color: #94a3b8;
  font-size: 12px;
  font-weight: 700;
  padding: 8px 10px;
  border-radius: var(--ds-radius-sm);
  cursor: pointer;
}

.save-bar-commit-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  background: #10b981;
  color: #ffffff;
  border: none;
  font-size: 12px;
  font-weight: 800;
  padding: 8px 14px;
  border-radius: var(--ds-radius-sm);
  cursor: pointer;
  box-shadow: 0 4px 14px rgba(16, 185, 129, 0.4);
}

/* 7. BOTTOM SHEET & TAKVİM DETAY */
.ds-sheet-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.55);
  backdrop-filter: blur(4px);
  -webkit-backdrop-filter: blur(4px);
  z-index: 100;
  display: flex;
  align-items: flex-end;
  justify-content: center;
}

.ds-sheet {
  width: 100%;
  max-width: 520px;
  background: #ffffff;
  border-radius: 28px 28px 0 0;
  display: flex;
  flex-direction: column;
  max-height: 92vh;
  box-shadow: 0 -12px 36px rgba(15, 23, 42, 0.18);
  border-top: 1px solid rgba(255, 255, 255, 0.9);
  overflow: hidden;
}

.ds-sheet-handle {
  width: 44px;
  height: 5px;
  border-radius: var(--ds-radius-pill);
  background: #cbd5e1;
  margin: 10px auto 4px;
  flex-shrink: 0;
}

.ds-sheet-header {
  padding: 8px 18px 12px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-bottom: 1px solid #f1f5f9;
  background: #ffffff;
  flex-shrink: 0;
}

/* Sheet Başlığı: Doğrudan Sheet'in Başlığı Olarak Bütünleşik, Ayrı Köşeli Kutu Olmadan */
.detail-sheet-header {
  padding: 8px 18px 12px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  background: #ffffff;
  border-bottom: 1px solid #f1f5f9;
  flex-shrink: 0;
  width: 100%;
  box-sizing: border-box;
}

.detail-sheet-user {
  display: flex;
  align-items: center;
  gap: 12px;
  flex: 1;
  min-width: 0;
}

.detail-user-text {
  display: flex;
  flex-direction: column;
  gap: 2px;
  flex: 1;
  min-width: 0;
}

.detail-user-name {
  font-size: 15px;
  font-weight: 800;
  color: #1e293b;
  margin: 0;
  line-height: 1.2;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.detail-user-meta {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 6px;
  font-size: 11px;
  color: #64748b;
  margin-top: 2px;
}

.detail-user-tc {
  color: #94a3b8;
  font-weight: 600;
  white-space: nowrap;
}

.detail-stat-pill {
  font-size: 10px;
  font-weight: 800;
  padding: 1px 7px;
  border-radius: var(--ds-radius-pill);
  background: #f1f5f9;
  color: #475569;
  border: 1px solid #e2e8f0;
  white-space: nowrap;
}

.ds-sheet-body {
  padding: 16px;
  overflow-y: auto;
  overscroll-behavior: contain;
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 14px;
  background: #f8fafc;
}

.hide-scrollbar {
  scrollbar-width: none !important;
  -ms-overflow-style: none !important;
}

.hide-scrollbar::-webkit-scrollbar {
  display: none !important;
  width: 0 !important;
  height: 0 !important;
}

/* Hızlı Damga Kutusu (Daha Kompakt) */
.stamp-card-box {
  padding: 10px 12px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.stamp-box-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.stamp-title-wrap {
  display: flex;
  flex-direction: column;
  gap: 1px;
}

.stamp-box-title {
  font-size: 11px;
  font-weight: 800;
  color: #475569;
  letter-spacing: 0.4px;
  text-transform: uppercase;
}

.stamp-box-hint {
  font-size: 10px;
  color: #94a3b8;
  font-weight: 600;
}

.stamp-active-pill {
  font-size: 10px;
  font-weight: 800;
  padding: 3px 8px;
  border-radius: var(--ds-radius-pill);
  background: #fef3c7;
  color: #92400e;
  border: 1px solid #fde68a;
  animation: pulse 1.5s infinite;
  white-space: nowrap;
}

.stamp-tabs {
  display: flex;
  background: #f1f5f9;
  padding: 3px;
  border-radius: 12px;
  gap: 4px;
}

.stamp-tab-btn {
  flex: 1;
  padding: 6px 8px;
  border: none;
  background: none;
  font-size: 11px;
  font-weight: 700;
  color: #64748b;
  border-radius: 9px;
  cursor: pointer;
  transition: all 0.15s ease;
}

.stamp-tab-btn.is-active {
  background: #ffffff;
  color: #1e293b;
  font-weight: 800;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
}

.stamp-types-scroll {
  display: flex;
  gap: 8px;
  overflow-x: auto;
  padding: 4px 2px 6px;
  -webkit-overflow-scrolling: touch;
}

.stamp-chip {
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 6px 12px;
  border-radius: 14px;
  color: #ffffff;
  cursor: pointer;
  box-shadow: 0 3px 8px rgba(0, 0, 0, 0.10);
  min-width: 60px;
  border: 2px solid transparent;
  transition: transform 0.12s ease, box-shadow 0.12s ease;
}

.stamp-chip:active {
  transform: scale(0.95);
}

.stamp-chip-code {
  font-size: 13px;
  font-weight: 900;
  line-height: 1.1;
}

.stamp-chip-name {
  font-size: 9px;
  font-weight: 700;
  opacity: 0.95;
  white-space: nowrap;
}

.stamp-chip.is-selected {
  transform: scale(1.06);
  border-color: #ffffff !important;
  box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.7), 0 8px 16px rgba(0, 0, 0, 0.15);
}

.stamp-selected-banner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: #191d24;
  color: #ffffff;
  padding: 8px 12px;
  border-radius: 12px;
  font-size: 11px;
  font-weight: 700;
  border: 1px solid rgba(255, 255, 255, 0.1);
}

.banner-text {
  display: flex;
  align-items: center;
  gap: 6px;
  color: #fbbf24;
}

.banner-text strong {
  color: #ffffff;
}

.banner-cancel-btn {
  background: rgba(255, 255, 255, 0.12);
  border: none;
  color: #ffffff;
  font-size: 10px;
  font-weight: 800;
  padding: 4px 8px;
  border-radius: 6px;
  cursor: pointer;
}

/* Takvim Izgarası (Zarif Pastel Görünüm - Tam Sığan ve Taşmasız Responsive) */
.clean-calendar-box {
  padding: 10px 8px 12px;
  width: 100%;
  box-sizing: border-box;
  overflow: visible;
  flex-shrink: 0;
}

.clean-calendar-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 6px;
  gap: 8px;
}

.calendar-section-title {
  font-size: 13px;
  font-weight: 800;
  color: #1e293b;
  margin: 0;
}

.calendar-section-hint {
  font-size: 10px;
  color: #94a3b8;
  font-weight: 600;
  display: block;
  margin-top: 2px;
}

.calendar-month-indicator {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  padding: 3px 8px;
  border-radius: var(--ds-radius-pill);
  font-size: 10px;
  font-weight: 800;
  color: #64748b;
  white-space: nowrap;
}

.clean-days-header {
  display: grid;
  grid-template-columns: repeat(7, minmax(0, 1fr));
  gap: 3px;
  width: 100%;
  box-sizing: border-box;
  text-align: center;
  font-size: 10px;
  font-weight: 800;
  color: #94a3b8;
  padding: 2px 0 4px;
  border-bottom: 1px solid #f1f5f9;
  margin-bottom: 4px;
}

.clean-days-header .is-sun {
  color: #ef4444;
}

/* Pastel Takvim Hücreleri - Kompakt ve Tam Sığan */
.calendar-tiles-grid {
  display: grid;
  grid-template-columns: repeat(7, minmax(0, 1fr));
  gap: 3px;
  width: 100%;
  box-sizing: border-box;
}

.calendar-tile {
  width: 100%;
  min-width: 0;
  box-sizing: border-box;
  aspect-ratio: 1 / 0.95;
  border-radius: 9px;
  border: 1px solid #e2e8f0;
  background: #f8fafc;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: space-between;
  padding: 2.5px 1px 2px;
  cursor: pointer;
  position: relative;
  transition: transform 0.12s ease, box-shadow 0.12s ease;
  overflow: visible;
}

.calendar-tile:active {
  transform: scale(0.90);
}

.calendar-tile--empty {
  background: transparent !important;
  border: 1px dashed rgba(203, 213, 225, 0.35) !important;
  pointer-events: none;
}

.calendar-tile-day {
  font-size: 10.5px;
  font-weight: 800;
  line-height: 1;
}

.calendar-tile-badge {
  font-size: 8px;
  font-weight: 900;
  padding: 1px 2.5px;
  border-radius: 4px;
  letter-spacing: 0.1px;
  line-height: 1.1;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
  max-width: 92%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  text-align: center;
}

.calendar-tile-overtime-pill {
  position: absolute;
  top: -3px;
  right: -3px;
  background: #7c3aed;
  color: #ffffff;
  font-size: 7.5px;
  font-weight: 800;
  line-height: 1;
  padding: 1px 3px;
  border-radius: 5px;
  box-shadow: 0 1px 3px rgba(124, 58, 237, 0.35);
  border: 1px solid #ffffff;
  z-index: 2;
  white-space: nowrap;
}

.calendar-tile.is-unsaved {
  border-width: 1.5px !important;
  box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.45) !important;
}

.tile-unsaved-dot {
  position: absolute;
  top: 2px;
  right: 2px;
  width: 5px;
  height: 5px;
  border-radius: 50%;
  background: #f59e0b;
}

/* Tarih Aralığı Akordeon */
.range-accordion-box {
  padding: 14px;
}

.range-accordion-trigger {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
  background: none;
  border: none;
  padding: 0;
  cursor: pointer;
}

.range-trigger-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: #1e293b;
}

.range-accordion-content {
  padding-top: 14px;
  border-top: 1px solid #f1f5f9;
  margin-top: 12px;
}

.range-inputs-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}

.range-label {
  display: block;
  font-size: 11px;
  font-weight: 700;
  color: #64748b;
  margin-bottom: 4px;
}

.range-select {
  width: 100%;
  height: 42px;
  padding: 0 10px;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  background: #f8fafc;
  color: #1e293b;
  font-size: 13px;
  font-weight: 600;
  outline: none;
}

.range-submit-btn {
  width: 100%;
  height: 44px;
  border-radius: 12px;
  background: #10b981;
  color: #ffffff;
  border: none;
  font-size: 13px;
  font-weight: 800;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  margin-top: 12px;
  cursor: pointer;
  box-shadow: 0 4px 14px rgba(16, 185, 129, 0.35);
}

/* 9. HIZLI GÜN STATÜSÜ SEÇİCİ BOTTOM SHEET */
.status-picker-sheet {
  max-height: 82vh;
}

.status-picker-header {
  padding: 8px 18px 12px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  border-bottom: 1px solid #f1f5f9;
  background: #ffffff;
  flex-shrink: 0;
  width: 100%;
  box-sizing: border-box;
}

.picker-header-user {
  display: flex;
  align-items: center;
  gap: 12px;
  flex: 1;
  min-width: 0;
}

.picker-user-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
  flex: 1;
  min-width: 0;
}

.picker-user-name {
  font-size: 15px;
  font-weight: 800;
  color: #1e293b;
  margin: 0;
  line-height: 1.2;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.picker-target-date {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 12px;
  font-weight: 700;
  color: #0284c7;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.current-status-banner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  padding: 10px 14px;
  border-radius: 16px;
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
}

.banner-title {
  font-size: 11.5px;
  font-weight: 700;
  color: #64748b;
}

.banner-status-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 10px;
  border-radius: 10px;
  color: #ffffff;
  font-size: 11px;
  font-weight: 800;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.12);
}

/* Kesme Departmanı Uyarısı */
.picker-kesme-alert {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  background: #fef3c7;
  border: 1px solid #fde68a;
  border-radius: 16px;
  padding: 10px 14px;
  margin-top: 10px;
  color: #92400e;
}

.kesme-alert-icon {
  color: #d97706;
  flex-shrink: 0;
  margin-top: 2px;
}

.kesme-alert-text strong {
  display: block;
  font-size: 13px;
  font-weight: 800;
  color: #b45309;
  margin-bottom: 2px;
}

.kesme-alert-text p {
  font-size: 11.5px;
  line-height: 1.35;
  color: #92400e;
  margin: 0;
}

.banner-badges-group {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
}

.banner-overtime-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 9px;
  border-radius: 10px;
  background: #7c3aed;
  color: #ffffff;
  font-size: 11px;
  font-weight: 800;
  box-shadow: 0 2px 6px rgba(124, 58, 237, 0.25);
}

.picker-fm-tab-pane {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 20px;
  padding: 14px 16px;
  box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
}

.picker-fm-card-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.picker-fm-head-left {
  display: flex;
  align-items: center;
  gap: 10px;
}

.picker-fm-icon-badge {
  width: 36px;
  height: 36px;
  border-radius: 12px;
  background: #f3e8ff;
  color: #7c3aed;
  border: 1px solid #e9d5ff;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  box-shadow: 0 2px 6px rgba(124, 58, 237, 0.12);
}

.picker-fm-card-title {
  font-size: 13.5px;
  font-weight: 800;
  color: #1e293b;
  margin: 0;
  line-height: 1.2;
}

.picker-fm-card-sub {
  font-size: 11px;
  color: #64748b;
  margin: 2px 0 0;
  font-weight: 500;
}

.picker-fm-head-right {
  display: flex;
  align-items: center;
  gap: 6px;
}

.picker-fm-active-badge {
  background: #7c3aed;
  color: #ffffff;
  font-size: 11px;
  font-weight: 800;
  padding: 4px 9px;
  border-radius: var(--ds-radius-pill);
  box-shadow: 0 2px 6px rgba(124, 58, 237, 0.3);
  white-space: nowrap;
}

.picker-fm-none-badge {
  font-size: 11px;
  font-weight: 700;
  color: #94a3b8;
  background: #f1f5f9;
  padding: 3px 8px;
  border-radius: var(--ds-radius-pill);
  white-space: nowrap;
}

.picker-fm-remove-btn {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  background: #fef2f2;
  border: 1px solid #fecdd3;
  color: #dc2626;
  font-size: 11px;
  font-weight: 800;
  padding: 4px 8px;
  border-radius: 10px;
  cursor: pointer;
  transition: all 0.15s ease;
}

.picker-fm-remove-btn:active {
  transform: scale(0.94);
  background: #fee2e2;
}

.picker-fm-section {
  display: flex;
  flex-direction: column;
  gap: 7px;
}

.picker-fm-section-title {
  font-size: 10.5px;
  font-weight: 800;
  color: #64748b;
  letter-spacing: 0.6px;
  text-transform: uppercase;
}

/* Hızlı Saatler: Kesilmesiz 4 Sütunlu Grid */
.picker-fm-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 6px;
  width: 100%;
}

.picker-fm-tile {
  min-height: 42px;
  padding: 6px 4px;
  background: #f8fafc;
  border: 1px solid #cbd5e1;
  border-radius: 12px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 1px;
  cursor: pointer;
  transition: all 0.15s ease;
  user-select: none;
}

.picker-fm-tile:active {
  transform: scale(0.95);
}

.picker-fm-tile-hours {
  font-size: 13px;
  font-weight: 900;
  color: #1e293b;
  line-height: 1.1;
}

.picker-fm-tile-tag {
  font-size: 9px;
  font-weight: 700;
  color: #64748b;
  line-height: 1;
}

.picker-fm-tile.is-selected {
  background: #7c3aed !important;
  border-color: #6d28d9 !important;
  box-shadow: 0 4px 12px rgba(124, 58, 237, 0.35);
}

.picker-fm-tile.is-selected .picker-fm-tile-hours {
  color: #ffffff !important;
}

.picker-fm-tile.is-selected .picker-fm-tile-tag {
  color: #e9d5ff !important;
}

/* Manuel Saat Girişi ve Stepper */
.picker-fm-manual-row {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
}

.picker-fm-stepper {
  flex: 1;
  min-width: 0;
  display: flex;
  align-items: center;
  background: #f8fafc;
  border: 1.5px solid #cbd5e1;
  border-radius: 14px;
  overflow: hidden;
  height: 44px;
  box-sizing: border-box;
}

.picker-fm-step-btn {
  width: 40px;
  height: 100%;
  background: #ffffff;
  border: none;
  color: #475569;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: background 0.12s;
  flex-shrink: 0;
}

.picker-fm-step-btn:active {
  background: #e2e8f0;
  color: #0f172a;
}

.picker-fm-input-wrap {
  flex: 1;
  min-width: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
  padding: 0 6px;
}

.picker-fm-stepper-input {
  width: 60px;
  height: 100%;
  border: none;
  background: transparent;
  font-size: 17px;
  font-weight: 900;
  color: #1e293b;
  text-align: center;
  outline: none;
  -moz-appearance: textfield;
}

.picker-fm-stepper-input::-webkit-outer-spin-button,
.picker-fm-stepper-input::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}

.picker-fm-unit {
  font-size: 12px;
  font-weight: 700;
  color: #64748b;
}

.picker-fm-commit-btn {
  height: 44px;
  padding: 0 16px;
  border-radius: 14px;
  background: #7c3aed;
  color: #ffffff;
  font-size: 13px;
  font-weight: 800;
  border: none;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  box-shadow: 0 3px 10px rgba(124, 58, 237, 0.3);
  transition: all 0.15s ease;
  flex-shrink: 0;
}

.picker-fm-commit-btn:active {
  transform: scale(0.96);
  background: #6d28d9;
}

.picker-fm-commit-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  transform: none;
}

.picker-fm-apply-btn:active {
  transform: scale(0.95);
}

.quick-reset-work-btn {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 12px;
  background: #eff6ff;
  border: 1.5px solid #bfdbfe;
  padding: 10px 14px;
  border-radius: 16px;
  cursor: pointer;
  text-align: left;
  transition: all 0.15s ease;
}

.quick-reset-work-btn:active {
  transform: scale(0.98);
  background: #dbeafe;
}

.reset-badge {
  width: 32px;
  height: 32px;
  border-radius: 10px;
  background: #2563eb;
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  font-weight: 900;
  flex-shrink: 0;
  box-shadow: 0 2px 6px rgba(37, 99, 235, 0.3);
}

.reset-text {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 1px;
}

.reset-text strong {
  font-size: 12.5px;
  font-weight: 800;
  color: #1e3a8a;
}

.reset-text small {
  font-size: 10.5px;
  color: #3b82f6;
  font-weight: 600;
}

.status-options-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 8px;
  margin-top: 8px;
}

.status-option-tile {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 9px 12px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  cursor: pointer;
  text-align: left;
  transition: all 0.12s ease;
  position: relative;
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
}

.status-option-tile:active {
  transform: scale(0.95);
}

.status-option-tile.is-selected {
  border-color: #10b981;
  background: #f0fdf4;
  box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.25);
}

.option-tile-badge {
  width: 28px;
  height: 28px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #ffffff;
  font-size: 10px;
  font-weight: 900;
  flex-shrink: 0;
  box-shadow: 0 2px 5px rgba(0, 0, 0, 0.12);
}

.option-tile-name {
  font-size: 11.5px;
  font-weight: 700;
  color: #1e293b;
  flex: 1;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.option-check-icon {
  flex-shrink: 0;
}

/* Onay Diyaloğu & Toast */
.prompt-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.6);
  backdrop-filter: blur(4px);
  z-index: 120;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}

.prompt-modal-box {
  width: 100%;
  max-width: 380px;
  padding: 24px 20px;
  text-align: center;
}

.prompt-icon-wrap {
  width: 54px;
  height: 54px;
  border-radius: 50%;
  background: #fef3c7;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 14px;
}

.puantaj-toast {
  position: fixed;
  top: calc(var(--ds-safe-top) + 20px);
  left: 20px;
  right: 20px;
  max-width: 440px;
  margin: 0 auto;
  padding: 12px 18px;
  border-radius: var(--ds-radius-pill);
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 13px;
  font-weight: 800;
  color: #ffffff;
  box-shadow: 0 12px 32px rgba(0, 0, 0, 0.25);
  z-index: 150;
}

.puantaj-toast.is-success { background: #059669; }
.puantaj-toast.is-error { background: #dc2626; }
.puantaj-toast.is-info { background: #1e293b; }

.spin-anim {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.6; }
}

.slide-up-enter-active,
.slide-up-leave-active {
  transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
.slide-up-enter-from,
.slide-up-leave-to {
  transform: translateY(100px);
  opacity: 0;
}

.sheet-slide-enter-active,
.sheet-slide-leave-active {
  transition: opacity 0.25s ease;
}
.sheet-slide-enter-active .ds-sheet,
.sheet-slide-leave-active .ds-sheet {
  transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
.sheet-slide-enter-from,
.sheet-slide-leave-to {
  opacity: 0;
}
.sheet-slide-enter-from .ds-sheet,
.sheet-slide-leave-to .ds-sheet {
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
