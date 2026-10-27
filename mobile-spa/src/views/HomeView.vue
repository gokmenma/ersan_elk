<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import {
  AlertTriangle,
  ArrowRight,
  ArrowUpRight,
  BadgeDollarSign,
  Bell,
  Briefcase,
  CalendarCheck,
  Cake,
  CalendarDays,
  CarFront,
  ChartNoAxesCombined,
  Check,
  ChevronDown,
  ChevronLeft,
  ChevronRight,
  ChevronUp,
  ClipboardCheck,
  Clock,
  CreditCard,
  ExternalLink,
  EyeOff,
  FileText,
  FolderOpen,
  Gauge,
  GripVertical,
  Info,
  Layers,
  Link2,
  Megaphone,
  Pencil,
  Phone,
  Plus,
  RefreshCw,
  RotateCcw,
  ShieldAlert,
  ShieldCheck,
  SlidersHorizontal,
  Sparkles,
  Star,
  Trash2,
  UsersRound,
  Wrench,
  X,
  Zap,
} from '@lucide/vue'
import TopBar from '../components/TopBar.vue'
import { auth } from '../services/auth.js'
import { getDashboard, readDashboardCache, saveDashboardSettings, syncDailyOperations, writeDashboardCache } from '../services/dashboard.js'
import { notificationsState, toggleNotificationsDrawer } from '../services/notifications.js'

const router = useRouter()
const loading = ref(true)
const error = ref('')
const dashboard = ref({})
const showCustomizeModal = ref(false)
const showQuickActionsModal = ref(false)
const quickActionsModalTab = ref('active') // 'active' | 'system' | 'custom'

watch(quickActionsModalTab, async () => {
  await nextTick()
  const el = document.querySelector('.qa-sheet-body')
  if (el) el.scrollTop = 0
})
const selectedAnnouncement = ref(null)
const operationsMode = ref('daily') // 'daily' | 'monthly'
const isSavingSettings = ref(false)
const isSyncingOperations = ref(false)
const toastMessage = ref('')
let toastTimer = null

function showToast(msg) {
  toastMessage.value = msg
  if (toastTimer) clearTimeout(toastTimer)
  toastTimer = setTimeout(() => {
    toastMessage.value = ''
  }, 3500)
}

const today = new Intl.DateTimeFormat('tr-TR', {
  day: 'numeric',
  month: 'long',
  weekday: 'long',
  timeZone: 'Europe/Istanbul',
}).format(new Date())

const username = computed(() => auth.state.user?.name || auth.state.user?.username || 'Kullanıcı')
const initials = computed(() => auth.state.user?.initials || username.value.slice(0, 2).toLocaleUpperCase('tr-TR'))

// Kart Tanımları ve Varsayılan Sıralama
const DEFAULT_CARD_ORDER = [
  'personnel',
  'birthdays',
  'pending_approvals',
  'shifts',
  'vehicles',
  'operations',
  'quick_actions',
  'announcements',
]

const CARD_DEFINITIONS = {
  personnel: {
    id: 'personnel',
    title: 'Personel Durumu',
    icon: UsersRound,
    description: 'Saha, izinli ve geç kalan personel dökümü',
  },
  birthdays: {
    id: 'birthdays',
    title: 'Doğum Günleri',
    icon: Cake,
    description: 'Aylık takvimde personel doğum günleri',
  },
  pending_approvals: {
    id: 'pending_approvals',
    title: 'Bekleyen Onaylar',
    icon: AlertTriangle,
    description: 'Onay bekleyen talep, avans ve izinler',
  },
  shifts: {
    id: 'shifts',
    title: 'Bugünün Nöbetçileri',
    icon: CalendarCheck,
    description: 'Bugün görevli nöbetçi personeller',
  },
  vehicles: {
    id: 'vehicles',
    title: 'Araç & Filo Durumu',
    icon: CarFront,
    description: 'Aktif, sahada ve servisteki araçlar',
  },
  operations: {
    id: 'operations',
    title: 'Günün Operasyonları',
    icon: Gauge,
    description: 'Mühürleme, kesme, endeks ve sayaç sayaçları',
  },
  quick_actions: {
    id: 'quick_actions',
    title: 'Hızlı Erişim',
    icon: Zap,
    description: 'Sık kullanılan modüllere doğrudan kısayollar',
  },
  announcements: {
    id: 'announcements',
    title: 'Kurumsal Duyurular',
    icon: Megaphone,
    description: 'Şirket içi haberler ve duyurular',
  },
}

// Kart sıralaması ve görünürlük state'i
const cardOrder = ref([...DEFAULT_CARD_ORDER])
const cardSettings = ref({}) // { [cardId]: { hidden: boolean, quick_actions?: array } }

// Dinamik İkon Haritası
const iconComponentsMap = {
  UsersRound,
  Briefcase,
  CarFront,
  CalendarCheck,
  CalendarDays,
  Megaphone,
  FileText,
  ClipboardCheck,
  ChartNoAxesCombined,
  ShieldAlert,
  ShieldCheck,
  Gauge,
  Zap,
  BadgeDollarSign,
  CreditCard,
  AlertTriangle,
  FolderOpen,
  Wrench,
  Clock,
  Sparkles,
  Star,
  Link2,
  ExternalLink,
  Layers,
}

// Tüm Sistem Sayfaları ve Popüler Derin Sekmeler Kataloğu
const ALL_SYSTEM_ACTIONS = [
  // Standart Ana Modüller
  { id: 'act_personnel', permission: 'personnel', label: 'Personel', iconName: 'UsersRound', to: '/personel', theme: 'blue' },
  { id: 'act_vehicles', permission: 'vehicles', label: 'Araçlar', iconName: 'CarFront', to: '/arac-takip', theme: 'emerald' },
  { id: 'act_shifts', permission: 'shifts', label: 'Nöbetler', iconName: 'CalendarCheck', to: '/nobet-onay', theme: 'indigo' },
  { id: 'act_notices', permission: 'notices', label: 'İhbarlar', iconName: 'Megaphone', to: '/ihbar', theme: 'amber' },
  { id: 'act_puantaj', permission: 'puantaj', label: 'Puantaj', iconName: 'CalendarDays', to: '/puantaj', theme: 'teal' },
  { id: 'act_illegal', permission: 'illegal', label: 'Kaçak Kontrol', iconName: 'ShieldAlert', to: '/kacak-kontrol', theme: 'red' },
  { id: 'act_documents', permission: 'documents', label: 'Evraklar', iconName: 'FileText', to: '/evrak-takip', theme: 'yellow' },
  { id: 'act_tasks', permission: 'tasks', label: 'Görevler', iconName: 'ClipboardCheck', to: '/gorevler', theme: 'emerald' },
  { id: 'act_reports', permission: 'reports', label: 'Raporlar', iconName: 'ChartNoAxesCombined', to: '/raporlar', theme: 'teal' },
  { id: 'act_requests', permission: 'requests', label: 'Talepler', iconName: 'AlertTriangle', to: '/talepler', theme: 'amber' },
  { id: 'act_payroll', permission: 'payroll', label: 'Bordro & Maaş', iconName: 'BadgeDollarSign', to: '/bordro', theme: 'purple' },
  { id: 'act_contracts', permission: 'contracts', label: 'Sözleşme & Hakediş', iconName: 'Briefcase', to: '/sozlesme-hakedis', theme: 'blue' },
  { id: 'act_accounts', permission: 'current_accounts', label: 'Cari Takip', iconName: 'UsersRound', to: '/cari-takip', theme: 'blue' },
  { id: 'act_invoices', permission: 'invoices', label: 'Faturalar', iconName: 'FileText', to: '/fatura', theme: 'purple' },
  { id: 'act_cash', permission: 'cash', label: 'Kasa', iconName: 'CreditCard', to: '/kasa', theme: 'teal' },
  { id: 'act_km', permission: 'km_approvals', label: 'KM Onayları', iconName: 'Gauge', to: '/km-onaylari', theme: 'emerald' },
  { id: 'act_logs', permission: 'logs', label: 'Loglar', iconName: 'ShieldCheck', to: '/loglar', theme: 'slate' },

  // Popüler Alt Sekmeler ve Derin Bağlantılar
  { id: 'act_deep_ek_odeme', permission: 'personnel', label: 'Ek Ödemeler', iconName: 'BadgeDollarSign', to: 'personnel_section:ek_odemeler', theme: 'emerald', isDeep: true, subtitle: 'Personel > Ek Ödeme' },
  { id: 'act_deep_kesinti', permission: 'personnel', label: 'Maaş Kesintisi', iconName: 'CreditCard', to: 'personnel_section:kesintiler', theme: 'red', isDeep: true, subtitle: 'Personel > Kesinti' },
  { id: 'act_deep_pers_izin', permission: 'personnel', label: 'Personel İzinleri', iconName: 'CalendarDays', to: 'personnel_section:izinler', theme: 'blue', isDeep: true, subtitle: 'Personel > İzinler' },
  { id: 'act_deep_pers_evrak', permission: 'personnel', label: 'Personel Evrakları', iconName: 'FileText', to: 'personnel_section:evraklar', theme: 'yellow', isDeep: true, subtitle: 'Personel > Evraklar' },
  { id: 'act_deep_pers_zimmet', permission: 'personnel', label: 'Zimmetler', iconName: 'Layers', to: 'personnel_section:zimmetler', theme: 'indigo', isDeep: true, subtitle: 'Personel > Zimmetler' },
  { id: 'act_deep_pers_icra', permission: 'personnel', label: 'İcra Dosyaları', iconName: 'ShieldAlert', to: 'personnel_section:icralar', theme: 'red', isDeep: true, subtitle: 'Personel > İcra' },
  { id: 'act_deep_avans', permission: 'requests', label: 'Avans Talebi', iconName: 'BadgeDollarSign', to: '/talepler?tab=avans', theme: 'blue', isDeep: true, subtitle: 'Talepler > Avans' },
  { id: 'act_deep_izin', permission: 'requests', label: 'İzin Talebi', iconName: 'CalendarDays', to: '/talepler?tab=izin', theme: 'amber', isDeep: true, subtitle: 'Talepler > İzin' },
  { id: 'act_deep_giden_evrak', permission: 'outgoing_documents', label: 'Giden Evrak', iconName: 'FolderOpen', to: '/giden-evrak', theme: 'yellow', isDeep: true, subtitle: 'Evraklar > Giden' },
  { id: 'act_deep_okuma_rep', permission: 'reports', label: 'Endeks Raporu', iconName: 'Gauge', to: '/raporlar?tab=okuma', theme: 'blue', isDeep: true, subtitle: 'Raporlar > Endeks' },
  { id: 'act_deep_kesme_rep', permission: 'reports', label: 'Kesme Raporu', iconName: 'Zap', to: '/raporlar?tab=kesme', theme: 'amber', isDeep: true, subtitle: 'Raporlar > Kesme' },
  { id: 'act_deep_muhur_rep', permission: 'reports', label: 'Mühürleme', iconName: 'ShieldCheck', to: '/raporlar?tab=muhurleme', theme: 'indigo', isDeep: true, subtitle: 'Raporlar > Mühür' },
  { id: 'act_deep_sayac_rep', permission: 'reports', label: 'Sayaç Değişim', iconName: 'Wrench', to: '/raporlar?tab=sokme_takma', theme: 'emerald', isDeep: true, subtitle: 'Raporlar > Sayaç' },
]

// Varsayılan Hızlı Erişim Listesi (Operasyonlar Standart Renk Temalarıyla)
const DEFAULT_QUICK_ACTIONS = [
  { id: 'act_personnel', label: 'Personel', iconName: 'UsersRound', to: '/personel', theme: 'blue' },
  { id: 'act_vehicles', label: 'Araçlar', iconName: 'CarFront', to: '/arac-takip', theme: 'emerald' },
  { id: 'act_shifts', label: 'Nöbetler', iconName: 'CalendarCheck', to: '/nobet-onay', theme: 'indigo' },
  { id: 'act_notices', label: 'İhbarlar', iconName: 'Megaphone', to: '/ihbar', theme: 'amber' },
  { id: 'act_puantaj', label: 'Puantaj', iconName: 'CalendarDays', to: '/puantaj', theme: 'teal' },
  { id: 'act_illegal', label: 'Kaçak Kontrol', iconName: 'ShieldAlert', to: '/kacak-kontrol', theme: 'red' },
  { id: 'act_documents', label: 'Evraklar', iconName: 'FileText', to: '/evrak-takip', theme: 'yellow' },
  { id: 'act_tasks', label: 'Görevler', iconName: 'ClipboardCheck', to: '/gorevler', theme: 'emerald' },
]

// Kullanıcı Kısayolları State'i
const userQuickActions = ref([])

function canRenderQuickAction(item) {
  if (!item?.isCustom) {
    const systemAction = ALL_SYSTEM_ACTIONS.find(action => action.id === item?.id)
    return !systemAction?.permission || Boolean(auth.state.permissions[systemAction.permission])
  }
  if (item.href && /^https?:\/\//i.test(item.href)) return true
  const target = String(item.to || '').split('?')[0]
  const systemAction = ALL_SYSTEM_ACTIONS.find(action => String(action.to || '').split('?')[0] === target)
  return Boolean(systemAction && (!systemAction.permission || auth.state.permissions[systemAction.permission]))
}

// Ana Ekranda Görünecek Hızlı Erişim Öğeleri
const actions = computed(() => {
  if (userQuickActions.value.length > 0) {
    return userQuickActions.value.filter(canRenderQuickAction)
  }
  return DEFAULT_QUICK_ACTIONS.filter(canRenderQuickAction)
})

// Kullanıcının Yetkili Olduğu Sistem Önerileri
const availableSystemActions = computed(() => {
  return ALL_SYSTEM_ACTIONS.filter(item => {
    if (item.permission && !auth.state.permissions[item.permission]) {
      return false
    }
    return true
  })
})

const systemMainModules = computed(() => availableSystemActions.value.filter(a => !a.isDeep))
const systemDeepSections = computed(() => availableSystemActions.value.filter(a => a.isDeep))

// Özel Bağlantı Ekleme Form State'i
const customNewAction = ref({
  label: '',
  to: '',
  iconName: 'Zap',
  theme: 'blue',
})

const availableIconNames = [
  'Zap',
  'Briefcase',
  'BadgeDollarSign',
  'CreditCard',
  'UsersRound',
  'FileText',
  'CalendarCheck',
  'CalendarDays',
  'CarFront',
  'FolderOpen',
  'Gauge',
  'ShieldCheck',
  'Sparkles',
  'Star',
  'Wrench',
  'Clock',
  'Link2',
]

const availableThemes = [
  { key: 'blue', label: 'Mavi', hex: '#0284c7' },
  { key: 'emerald', label: 'Zümrüt', hex: '#10b981' },
  { key: 'amber', label: 'Turuncu', hex: '#f59e0b' },
  { key: 'indigo', label: 'İndigo', hex: '#6366f1' },
  { key: 'teal', label: 'Teal', hex: '#0d9488' },
  { key: 'red', label: 'Kırmızı', hex: '#ef4444' },
  { key: 'purple', label: 'Mor', hex: '#8b5cf6' },
  { key: 'yellow', label: 'Kehribar', hex: '#eab308' },
]

const quickPresets = [
  { label: 'Sözleşme & Hakediş', to: '/sozlesme-hakedis', iconName: 'Briefcase', theme: 'blue' },
  { label: 'Ek Ödemeler', to: 'personnel_section:ek_odemeler', iconName: 'BadgeDollarSign', theme: 'emerald' },
  { label: 'Maaş Kesintisi', to: 'personnel_section:kesintiler', iconName: 'CreditCard', theme: 'red' },
  { label: 'İzin Talebi', to: '/talepler?tab=izin', iconName: 'CalendarDays', theme: 'amber' },
  { label: 'Avans Talebi', to: '/talepler?tab=avans', iconName: 'BadgeDollarSign', theme: 'blue' },
  { label: 'KM Onayları', to: '/km-onaylari', iconName: 'Gauge', theme: 'emerald' },
  { label: 'Kaçak Kontrol', to: '/kacak-kontrol', iconName: 'ShieldAlert', theme: 'red' },
]

function applyPreset(preset) {
  customNewAction.value.label = preset.label
  customNewAction.value.to = preset.to
  customNewAction.value.iconName = preset.iconName
  customNewAction.value.theme = preset.theme
}

function saveQuickActions(newList) {
  userQuickActions.value = [...newList]
  try {
    localStorage.setItem('ersan_mobile_quick_actions', JSON.stringify(newList))
  } catch (e) {}
  cardSettings.value = {
    ...cardSettings.value,
    quick_actions: newList,
  }
  persistSettings()
}

function addActionToUser(action) {
  if (userQuickActions.value.some(a => a.id === action.id)) {
    showToast('Bu kısayol zaten ekli.')
    return
  }
  const updated = [
    ...userQuickActions.value,
    {
      id: action.id,
      label: action.label,
      iconName: action.iconName || 'Zap',
      to: action.to || '',
      href: action.href || '',
      theme: action.theme || 'blue',
      isCustom: Boolean(action.isCustom),
    },
  ]
  saveQuickActions(updated)
  showToast(`"${action.label}" hızlı işlemlere eklendi.`)
}

function removeActionFromUser(actionId) {
  const updated = userQuickActions.value.filter(a => a.id !== actionId)
  saveQuickActions(updated)
  showToast('Kısayol kaldırıldı.')
}

function moveAction(index, direction) {
  const targetIndex = direction === 'up' ? index - 1 : index + 1
  if (targetIndex < 0 || targetIndex >= userQuickActions.value.length) return
  const list = [...userQuickActions.value]
  const [moved] = list.splice(index, 1)
  list.splice(targetIndex, 0, moved)
  saveQuickActions(list)
}

function resetQuickActionsToDefault() {
  saveQuickActions([...DEFAULT_QUICK_ACTIONS])
  showToast('Hızlı işlemler varsayılana sıfırlandı.')
}

function submitCustomAction() {
  const lbl = customNewAction.value.label.trim()
  const link = customNewAction.value.to.trim()
  if (!lbl) {
    showToast('Lütfen bir kısayol başlığı girin.')
    return
  }
  if (!link) {
    showToast('Lütfen hedef sayfa / link adresini girin.')
    return
  }

  const newId = 'custom_' + Date.now()
  const isExternal = link.startsWith('http://') || link.startsWith('https://')
  const newAction = {
    id: newId,
    label: lbl,
    iconName: customNewAction.value.iconName || 'Zap',
    to: isExternal ? '' : link,
    href: isExternal ? link : '',
    theme: customNewAction.value.theme || 'blue',
    isCustom: true,
  }

  addActionToUser(newAction)
  customNewAction.value.label = ''
  customNewAction.value.to = ''
  customNewAction.value.iconName = 'Zap'
  customNewAction.value.theme = 'blue'
  quickActionsModalTab.value = 'active'
}

// Reaktif Veri Modelleri
const personnel = computed(() => (auth.state.permissions.personnel ? dashboard.value.personnel : null))
const vehicles = computed(() => (auth.state.permissions.vehicles ? dashboard.value.vehicles : null))
const shifts = computed(() => (auth.state.permissions.shift_schedule ? (dashboard.value.shifts || []) : []))
const pendingApprovals = computed(() => (auth.state.permissions.requests ? (dashboard.value.pending_approvals || { requests: 0, advances: 0, leaves: 0, total: 0 }) : null))
const BDAY_MONTHS = ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık']
const bdayNow = new Date()
const bdayYear = ref(bdayNow.getFullYear())
const bdayMonth = ref(bdayNow.getMonth() + 1)
const bdaySelectedDay = ref(0)
const birthdays = computed(() => (auth.state.permissions.personnel ? dashboard.value.birthdays || null : null))
const bdayDaysInMonth = computed(() => new Date(bdayYear.value, bdayMonth.value, 0).getDate())
const bdayLeadingBlanks = computed(() => (new Date(bdayYear.value, bdayMonth.value - 1, 1).getDay() + 6) % 7)
const bdayByDay = computed(() => {
  const map = {}
  ;(birthdays.value || []).forEach((item) => {
    if (item.month !== bdayMonth.value) return
    const day = Math.min(item.day, bdayDaysInMonth.value)
    ;(map[day] = map[day] || []).push({ ...item, shownDay: day })
  })
  return map
})
const bdayVisibleList = computed(() => {
  const hedefGun = bdaySelectedDay.value || (isBdayCurrentMonth.value ? bdayNow.getDate() : 0)
  return hedefGun ? bdayByDay.value[hedefGun] || [] : []
})
const isBdayCurrentMonth = computed(() => bdayNow.getFullYear() === bdayYear.value && bdayNow.getMonth() + 1 === bdayMonth.value)
function bdayShiftMonth(step) {
  let month = bdayMonth.value + step
  if (month < 1) { month = 12; bdayYear.value-- } else if (month > 12) { month = 1; bdayYear.value++ }
  bdayMonth.value = month
  bdaySelectedDay.value = 0
}
function isBdayToday(day) {
  return bdayNow.getFullYear() === bdayYear.value && bdayNow.getMonth() + 1 === bdayMonth.value && bdayNow.getDate() === day
}
const announcements = computed(() => dashboard.value.announcements || [])
const opSortOrder = {
  reading: 1,
  cut_open: 2,
  meter_change: 3,
  sealing: 4,
  illegal: 5,
  notice: 6,
}

const operations = computed(() => {
  const list = [...(dashboard.value.operations || [])]
  return list.sort((a, b) => (opSortOrder[a.key] || 99) - (opSortOrder[b.key] || 99))
})

const operationLinks = {
  reading: '/raporlar?tab=okuma',
  cut_open: '/raporlar?tab=kesme',
  meter_change: '/raporlar?tab=sokme_takma',
  sealing: '/raporlar?tab=muhurleme',
  illegal: '/raporlar?tab=kacakkontrol',
  notice: '/ihbar',
}

function goToOperation(item) {
  const target = operationLinks[item.key] || item.link || '/raporlar'
  router.push(target)
}

async function handleSyncOperations() {
  if (isSyncingOperations.value) return
  isSyncingOperations.value = true
  try {
    const res = await syncDailyOperations()
    if (res?.dashboard) {
      dashboard.value = res.dashboard
    }
    showToast('Günlük operasyonlar KASKİ sisteminden sorgulandı ve güncellendi.')
  } catch (err) {
    console.error('Sorgulama hatası:', err)
    showToast('Sorgulama sırasında bağlantı hatası oluştu.')
  } finally {
    isSyncingOperations.value = false
  }
}

// Personel Metrikleri
const personnelRate = computed(() => {
  const total = Number(personnel.value?.total || 0)
  return total ? Math.min(100, Math.round((Number(personnel.value?.working || 0) / total) * 100)) : 0
})

const personnelSegments = computed(() => {
  const total = Number(personnel.value?.total || 0)
  if (!total) return { working: 0, leave: 0, late: 0, other: 0 }
  const working = Math.max(0, Number(personnel.value?.working || 0))
  const leave = Math.max(0, Number(personnel.value?.on_leave || 0))
  const late = Math.max(0, Number(personnel.value?.late || 0))
  const other = Math.max(0, total - working - leave)
  return {
    working: (working / total) * 100,
    leave: (leave / total) * 100,
    late: (late / total) * 100,
    other: (other / total) * 100,
  }
})

// Araç Metrikleri
const vehicleSegments = computed(() => {
  const total = Number(vehicles.value?.total || 0)
  const ratio = val => (total ? `${Math.max(0, (Number(val || 0) / total) * 100)}%` : '0%')
  return {
    field: ratio(vehicles.value?.field),
    service: ratio(vehicles.value?.service),
    idle: ratio(vehicles.value?.idle),
  }
})

const operationIcons = {
  reading: Gauge,
  cut_open: Zap,
  meter_change: Wrench,
  sealing: ShieldCheck,
  illegal: ShieldAlert,
  notice: Megaphone,
}

// Görünürlük Kontrolleri
function isCardVisible(cardId) {
  if (cardSettings.value[cardId]?.hidden) return false

  if (cardId === 'announcements') return announcements.value.length > 0
  if (cardId === 'pending_approvals') return Boolean(pendingApprovals.value)
  if (cardId === 'shifts') return Boolean(auth.state.permissions.shift_schedule)
  if (cardId === 'personnel') return Boolean(personnel.value)
  if (cardId === 'birthdays') return Boolean(birthdays.value)
  if (cardId === 'vehicles') return Boolean(vehicles.value)
  if (cardId === 'operations') return operations.value.length > 0
  if (cardId === 'quick_actions') return actions.value.length > 0
  return true
}

// Sıralı ve Görünür Kart Listesi
const visibleCardList = computed(() => {
  return cardOrder.value.filter(cardId => isCardVisible(cardId))
})

// Gizlenen Kartlar Listesi
const hiddenCardList = computed(() => {
  return cardOrder.value.filter(cardId => cardSettings.value[cardId]?.hidden)
})

// Kart Taşıma (Yukarı / Aşağı)
function moveCard(cardId, direction) {
  const currentIndex = cardOrder.value.indexOf(cardId)
  if (currentIndex === -1) return
  const targetIndex = direction === 'up' ? currentIndex - 1 : currentIndex + 1
  if (targetIndex < 0 || targetIndex >= cardOrder.value.length) return

  const newOrder = [...cardOrder.value]
  const [removed] = newOrder.splice(currentIndex, 1)
  newOrder.splice(targetIndex, 0, removed)
  cardOrder.value = newOrder
  persistSettings()
}

// Kart Gizle / Göster
function toggleCardVisibility(cardId) {
  const currentHidden = Boolean(cardSettings.value[cardId]?.hidden)
  cardSettings.value = {
    ...cardSettings.value,
    [cardId]: { ...cardSettings.value[cardId], hidden: !currentHidden },
  }
  persistSettings()
}

// Varsayılan Sıralama ve Görünürlüğe Sıfırla
function resetToDefault() {
  cardOrder.value = [...DEFAULT_CARD_ORDER]
  cardSettings.value = {}
  persistSettings()
}

// Sürükle - Bırak (Mobil Dokunmatik + Masaüstü Pointer & Drag)
const draggedCard = ref(null)
const dragOverCard = ref(null)
const dragOverPosition = ref(null) // 'top' | 'bottom'
const isDraggingHandle = ref(false)

function reorderCards(draggedId, targetId, position) {
  if (!draggedId || !targetId || draggedId === targetId) return false

  const currentOrder = [...cardOrder.value]
  const fromIndex = currentOrder.indexOf(draggedId)
  if (fromIndex === -1) return false

  const [item] = currentOrder.splice(fromIndex, 1)

  const targetIndex = currentOrder.indexOf(targetId)
  if (targetIndex === -1) return false

  const insertIndex = position === 'bottom' ? targetIndex + 1 : targetIndex
  currentOrder.splice(insertIndex, 0, item)

  cardOrder.value = currentOrder
  return true
}

function findCardUnderPoint(clientY) {
  const wrappers = Array.from(document.querySelectorAll('.home-sections-stack .home-card-wrapper'))
  if (!wrappers.length) return null

  for (const wrapper of wrappers) {
    const cid = wrapper.dataset.cardId
    if (!cid) continue
    const rect = wrapper.getBoundingClientRect()
    if (clientY >= rect.top && clientY <= rect.bottom) {
      const isBottom = clientY > (rect.top + rect.height / 2)
      return { cardId: cid, position: isBottom ? 'bottom' : 'top' }
    }
  }

  const firstRect = wrappers[0].getBoundingClientRect()
  if (clientY < firstRect.top) {
    return { cardId: wrappers[0].dataset.cardId, position: 'top' }
  }

  const lastWrapper = wrappers[wrappers.length - 1]
  const lastRect = lastWrapper.getBoundingClientRect()
  if (clientY > lastRect.bottom) {
    return { cardId: lastWrapper.dataset.cardId, position: 'bottom' }
  }

  return null
}

function startHandleDrag(cardId, event) {
  if (isDraggingHandle.value) return
  if (event.button && event.button !== 0) return
  if (event.cancelable) event.preventDefault()

  draggedCard.value = cardId
  dragOverCard.value = null
  dragOverPosition.value = null
  isDraggingHandle.value = true

  const startY = event.clientY || (event.touches && event.touches[0]?.clientY) || 0
  let hasMoved = false
  const pointerId = event.pointerId
  const targetEl = event.currentTarget

  document.body.classList.add('is-card-dragging')

  try {
    if (pointerId !== undefined && targetEl?.setPointerCapture) {
      targetEl.setPointerCapture(pointerId)
    }
  } catch (err) {}

  if (navigator.vibrate) {
    try { navigator.vibrate(12) } catch (e) {}
  }

  function handleMoveCoord(clientY) {
    if (!hasMoved && Math.abs(clientY - startY) > 5) {
      hasMoved = true
    }
    if (!hasMoved) return

    const found = findCardUnderPoint(clientY)
    if (found && found.cardId !== draggedCard.value) {
      dragOverCard.value = found.cardId
      dragOverPosition.value = found.position
    } else {
      dragOverCard.value = null
      dragOverPosition.value = null
    }

    const edgeMargin = 70
    if (clientY < edgeMargin) {
      window.scrollBy({ top: -8, behavior: 'instant' })
    } else if (clientY > window.innerHeight - edgeMargin) {
      window.scrollBy({ top: 8, behavior: 'instant' })
    }
  }

  function onPointerMove(e) {
    if (pointerId !== undefined && e.pointerId !== pointerId) return
    handleMoveCoord(e.clientY)
  }

  function onTouchMove(e) {
    if (e.cancelable) e.preventDefault()
    if (e.touches && e.touches[0]) {
      handleMoveCoord(e.touches[0].clientY)
    }
  }

  function finishDrag() {
    cleanup()

    if (hasMoved && draggedCard.value && dragOverCard.value && draggedCard.value !== dragOverCard.value) {
      const changed = reorderCards(draggedCard.value, dragOverCard.value, dragOverPosition.value)
      if (changed) {
        persistSettings()
        if (navigator.vibrate) {
          try { navigator.vibrate(25) } catch (ve) {}
        }
        showToast('Kart sıralaması güncellendi.')
      }
    }

    resetDragState()
  }

  function onPointerUp(e) {
    if (pointerId !== undefined && e.pointerId !== pointerId) return
    finishDrag()
  }

  function onTouchEnd() {
    finishDrag()
  }

  function cleanup() {
    try {
      if (pointerId !== undefined && targetEl?.releasePointerCapture) {
        targetEl.releasePointerCapture(pointerId)
      }
    } catch (err) {}

    window.removeEventListener('pointermove', onPointerMove)
    window.removeEventListener('pointerup', onPointerUp)
    window.removeEventListener('pointercancel', finishDrag)
    window.removeEventListener('touchmove', onTouchMove)
    window.removeEventListener('touchend', onTouchEnd)
    window.removeEventListener('touchcancel', finishDrag)
    document.body.classList.remove('is-card-dragging')
    isDraggingHandle.value = false
  }

  window.addEventListener('pointermove', onPointerMove, { passive: false })
  window.addEventListener('pointerup', onPointerUp)
  window.addEventListener('pointercancel', finishDrag)
  window.addEventListener('touchmove', onTouchMove, { passive: false })
  window.addEventListener('touchend', onTouchEnd)
  window.addEventListener('touchcancel', finishDrag)
}

function handleDragStart(cardId, event) {
  draggedCard.value = cardId
  if (event.dataTransfer) {
    event.dataTransfer.effectAllowed = 'move'
    event.dataTransfer.setData('text/plain', cardId)
  }
}

function handleDragOver(cardId, event) {
  event.preventDefault()
  if (draggedCard.value === cardId) return
  dragOverCard.value = cardId

  const rect = event.currentTarget.getBoundingClientRect()
  const offset = event.clientY - rect.top
  dragOverPosition.value = offset > rect.height / 2 ? 'bottom' : 'top'
}

function handleDragLeave(cardId) {
  if (dragOverCard.value === cardId) {
    dragOverCard.value = null
    dragOverPosition.value = null
  }
}

function handleDrop(cardId, event) {
  event.preventDefault()
  if (!draggedCard.value || draggedCard.value === cardId) {
    resetDragState()
    return
  }

  const changed = reorderCards(draggedCard.value, cardId, dragOverPosition.value)
  if (changed) {
    persistSettings()
    showToast('Kart sıralaması güncellendi.')
  }
  resetDragState()
}

function handleDragEnd() {
  resetDragState()
}

function resetDragState() {
  draggedCard.value = null
  dragOverCard.value = null
  dragOverPosition.value = null
  isDraggingHandle.value = false
  document.body.classList.remove('is-card-dragging')
}

// Ayarları Yerel Depolama ve Sunucuya Kaydetme
async function persistSettings() {
  try {
    localStorage.setItem('ersan_mobile_home_card_order', JSON.stringify(cardOrder.value))
    localStorage.setItem('ersan_mobile_home_card_settings', JSON.stringify(cardSettings.value))
  } catch (e) {}

  isSavingSettings.value = true
  try {
    await saveDashboardSettings(cardOrder.value, cardSettings.value)
  } catch (err) {
    console.warn('Dashboard ayarları sunucuya kaydedilemedi:', err)
  } finally {
    isSavingSettings.value = false
  }
}

// Yerel ve Sunucu Ayarlarını Yükleme
function loadCachedPreferences() {
  try {
    const cachedOrder = localStorage.getItem('ersan_mobile_home_card_order')
    if (cachedOrder) {
      const parsed = JSON.parse(cachedOrder)
      if (Array.isArray(parsed) && parsed.length) {
        cardOrder.value = parsed
      }
    }
    const cachedSettings = localStorage.getItem('ersan_mobile_home_card_settings')
    if (cachedSettings) {
      const parsedSettings = JSON.parse(cachedSettings)
      if (typeof parsedSettings === 'object' && parsedSettings !== null) {
        cardSettings.value = parsedSettings
      }
    }
    const cachedQuickActions = localStorage.getItem('ersan_mobile_quick_actions')
    if (cachedQuickActions) {
      const parsedQA = JSON.parse(cachedQuickActions)
      if (Array.isArray(parsedQA) && parsedQA.length) {
        userQuickActions.value = parsedQA
      }
    }
  } catch (e) {}
}

function openAction(action) {
  if (action.to) {
    if (action.to.startsWith('personnel_section:')) {
      const sectionName = action.to.split(':')[1]
      const lastId = localStorage.getItem('ersan_last_personnel_id')
      if (lastId) {
        router.push(`/personel/${encodeURIComponent(lastId)}/bolum/${sectionName}`)
      } else {
        router.push(`/personel?section=${sectionName}`)
      }
    } else {
      router.push(action.to)
    }
  } else if (action.href) {
    if (action.href.startsWith('http://') || action.href.startsWith('https://')) {
      window.open(action.href, '_blank')
    } else {
      window.location.href = action.href
    }
  }
}

function formatDate(value) {
  if (!value) return ''
  const date = new Date(value)
  return !Number.isNaN(date.getTime())
    ? new Intl.DateTimeFormat('tr-TR', { day: '2-digit', month: 'short' }).format(date)
    : ''
}

function openAnnouncementDetail(announcement) {
  selectedAnnouncement.value = announcement
}

function closeAnnouncementDetail() {
  selectedAnnouncement.value = null
}

function dashboardScope() {
  return `${auth.state.user?.username ?? ''}_${auth.state.company?.id ?? ''}`
}

function applyDashboard(data) {
  dashboard.value = data

  if (data.card_preferences) {
    if (Array.isArray(data.card_preferences.order) && data.card_preferences.order.length) {
      cardOrder.value = data.card_preferences.order
    }
    if (data.card_preferences.settings && typeof data.card_preferences.settings === 'object') {
      cardSettings.value = data.card_preferences.settings
      if (Array.isArray(data.card_preferences.settings.quick_actions) && data.card_preferences.settings.quick_actions.length) {
        userQuickActions.value = data.card_preferences.settings.quick_actions
      }
    }
  }
  if (!userQuickActions.value || userQuickActions.value.length === 0) {
    userQuickActions.value = [...DEFAULT_QUICK_ACTIONS]
  }
}

async function load() {
  const scope = dashboardScope()
  const cached = readDashboardCache(scope)
  const hasCache = Boolean(cached)
  error.value = ''
  if (hasCache) {
    applyDashboard(cached)
    loading.value = false
  } else {
    loading.value = true
  }
  try {
    const data = await getDashboard()
    applyDashboard(data)
    writeDashboardCache(scope, data)
  } catch (reason) {
    if (!hasCache) error.value = reason.message
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadCachedPreferences()
  load()
})
</script>

<template>
  <section class="ds-screen ds-home" :aria-busy="loading">
    <!-- En Üst Bar: Kullanıcı isteği doğrultusunda sağ tarafta "Kartları Düzenle" butonu -->
    <TopBar
      :eyebrow="today"
      :subtitle="auth.state.company?.name ? 'İşinizin bugünkü görünümü.' : 'Günlük operasyon ve saha özeti.'"
    >
      <template #actions>
        <button
          type="button"
          class="clay-topbar-edit-btn"
          aria-label="Ana sayfayı özelleştir"
          title="Ana Sayfayı Düzenle"
          @click="showCustomizeModal = true"
        >
          <span class="edit-icon-wrap"><SlidersHorizontal :size="13" /></span>
          <span>Kartları Düzenle</span>
        </button>
        <button
          type="button"
          class="clay-circle-btn topbar-bell-btn"
          aria-label="Bildirimleri aç"
          title="Bildirimler"
          @click="toggleNotificationsDrawer"
        >
          <Bell :size="19" />
          <span
            v-if="notificationsState.unreadCount > 0"
            class="topbar-bell-badge"
          >
            {{ notificationsState.unreadCount > 99 ? '99+' : notificationsState.unreadCount }}
          </span>
        </button>
        <button
          type="button"
          class="clay-avatar-circle"
          aria-label="Profil ve ayarları aç"
          title="Profil"
          @click="router.push('/profil')"
        >
          {{ initials }}
        </button>
      </template>
    </TopBar>

    <!-- Yükleniyor İskeleti -->
    <div v-if="loading" class="clay-skeleton-grid" role="status" aria-label="Günlük özet yükleniyor">
      <div class="clay-skeleton-card clay-skeleton-card--md"></div>
      <div class="clay-skeleton-card clay-skeleton-card--lg"></div>
    </div>

    <!-- Hata Kartı -->
    <div v-else-if="error" class="clay-state-card" role="alert">
      <RefreshCw :size="24" />
      <strong>Veriler alınamadı</strong>
      <p>{{ error }}</p>
      <button type="button" class="clay-retry-btn" @click="load">Tekrar dene</button>
    </div>

    <!-- Dashboard Kartlar Listesi (Taşıma İşlemleri Kartların İçinde En Üstte) -->
    <template v-else>
      <div class="home-sections-stack">
        <template v-for="cardId in visibleCardList" :key="cardId">
          <div
            class="home-card-wrapper"
            :data-card-id="cardId"
            :class="{
              'is-dragging': draggedCard === cardId,
              'drag-over-top': dragOverCard === cardId && dragOverPosition === 'top',
              'drag-over-bottom': dragOverCard === cardId && dragOverPosition === 'bottom',
            }"
            draggable="true"
            @dragstart="handleDragStart(cardId, $event)"
            @dragover="handleDragOver(cardId, $event)"
            @dragleave="handleDragLeave(cardId)"
            @drop="handleDrop(cardId, $event)"
            @dragend="handleDragEnd"
          >
            <!-- 1. PERSONEL DURUMU (MİNİMAL, KOMPAKT VE ŞIK) -->
            <section
              v-if="cardId === 'personnel' && personnel"
              class="bento-card bento-card--dark"
            >
              <!-- Kart İçi En Üst Kontroller & Başlık -->
              <div class="card-inner-head">
                <div class="card-inner-title">
                  <UsersRound :size="16" class="text-blue" />
                  <span>Personel Durumu</span>
                  <span class="dark-pill-tag ms-1"><span class="bento-status-dot"></span> SAHADA</span>
                </div>
                <div class="card-inner-controls">
                  <span
                    class="card-inner-handle"
                    role="button"
                    tabindex="0"
                    title="Sürükle ve taşı"
                    aria-label="Sürükle ve taşı"
                    @pointerdown.stop="startHandleDrag(cardId, $event)"
                    @touchstart.stop="startHandleDrag(cardId, $event)"
                  >
                    <GripVertical :size="14" />
                  </span>
                  <button
                    type="button"
                    class="card-inner-btn"
                    title="Yukarı taşı"
                    aria-label="Yukarı taşı"
                    @click.stop="moveCard(cardId, 'up')"
                  >
                    <ChevronUp :size="14" />
                  </button>
                  <button
                    type="button"
                    class="card-inner-btn"
                    title="Aşağı taşı"
                    aria-label="Aşağı taşı"
                    @click.stop="moveCard(cardId, 'down')"
                  >
                    <ChevronDown :size="14" />
                  </button>
                </div>
              </div>

              <!-- Minimal Ana Metrik ve Ekip Linki -->
              <div class="personnel-minimal-body">
                <div class="personnel-minimal-stat">
                  <strong>{{ personnel.working }}</strong>
                  <span>/ {{ personnel.total }} sahada aktif (%{{ personnelRate }})</span>
                </div>
                <RouterLink to="/personel" class="personnel-minimal-link">
                  <span>Ekip detayı</span>
                  <ArrowUpRight :size="13" />
                </RouterLink>
              </div>

              <!-- İnce Zarif Progress Çubuğu -->
              <div
                class="personnel-minimal-track"
                role="img"
                :aria-label="`Sahada ${personnel.working}, İzinli ${personnel.on_leave}, Geç kalan ${personnel.late || 0}`"
              >
                <span class="seg-pill seg-pill--blue" :style="{ width: `${personnelSegments.working}%` }"></span>
                <span class="seg-pill seg-pill--yellow" :style="{ width: `${personnelSegments.leave}%` }"></span>
                <span class="seg-pill seg-pill--coral" :style="{ width: `${personnelSegments.late}%` }"></span>
              </div>

              <!-- 3'lü Kompakt Alt Metrik -->
              <div class="personnel-minimal-stats">
                <RouterLink :to="{ path: '/personel', query: { durum: 'working' } }" class="personnel-stat-col">
                  <span><i class="ds-dot ds-dot--blue"></i>Sahada</span>
                  <strong>{{ personnel.working }}</strong>
                </RouterLink>
                <RouterLink :to="{ path: '/personel', query: { durum: 'on_leave' } }" class="personnel-stat-col">
                  <span><i class="ds-dot ds-dot--yellow"></i>İzinli</span>
                  <strong>{{ personnel.on_leave }}</strong>
                </RouterLink>
                <RouterLink :to="{ path: '/personel', query: { durum: 'late' } }" class="personnel-stat-col">
                  <span><i class="ds-dot ds-dot--coral"></i>Geç Kalan</span>
                  <strong>{{ personnel.late || 0 }}</strong>
                </RouterLink>
              </div>
            </section>

            <!-- 2. BEKLEYEN ONAYLAR -->
            <section
              v-else-if="cardId === 'pending_approvals' && pendingApprovals"
              class="bento-card bento-card--white approvals-bento"
            >
              <!-- Kart İçi En Üst Kontroller -->
              <div class="card-inner-head">
                <div class="card-inner-title">
                  <AlertTriangle :size="16" class="text-amber" />
                  <span>Bekleyen Onaylar</span>
                  <span class="clay-pill-tag ms-1">{{ pendingApprovals.total }} İşlem</span>
                </div>
                <div class="card-inner-controls">
                  <span
                    class="card-inner-handle"
                    role="button"
                    tabindex="0"
                    title="Sürükle ve taşı"
                    aria-label="Sürükle ve taşı"
                    @pointerdown.stop="startHandleDrag(cardId, $event)"
                    @touchstart.stop="startHandleDrag(cardId, $event)"
                  >
                    <GripVertical :size="14" />
                  </span>
                  <button type="button" class="card-inner-btn" title="Yukarı taşı" @click.stop="moveCard(cardId, 'up')"><ChevronUp :size="14" /></button>
                  <button type="button" class="card-inner-btn" title="Aşağı taşı" @click.stop="moveCard(cardId, 'down')"><ChevronDown :size="14" /></button>
                </div>
              </div>

              <div class="approvals-content">
                <div class="approvals-info-block">
                  <div class="approvals-icon-bubble">
                    <AlertTriangle :size="20" />
                  </div>
                  <div class="approvals-text">
                    <strong>{{ pendingApprovals.total > 0 ? `${pendingApprovals.total} Onay Bekliyor` : 'Tüm Onaylar Güncel' }}</strong>
                    <p v-if="pendingApprovals.total > 0">
                      {{ pendingApprovals.requests }} talep, {{ pendingApprovals.advances }} avans, {{ pendingApprovals.leaves }} izin
                    </p>
                    <p v-else class="text-emerald">Bekleyen personel işlemi bulunmuyor.</p>
                  </div>
                </div>
                <RouterLink
                  to="/talepler"
                  class="clay-pill-btn"
                  aria-label="Onay ekranına git"
                >
                  <span>İncele</span>
                  <ChevronRight :size="13" />
                </RouterLink>
              </div>
            </section>

            <!-- 2.5 DOĞUM GÜNLERİ -->
            <section
              v-else-if="cardId === 'birthdays' && birthdays"
              class="bento-card bento-card--white"
            >
              <div class="card-inner-head">
                <div class="card-inner-title">
                  <Cake :size="16" class="text-primary" />
                  <span>Doğum Günleri</span>
                  <span class="clay-pill-tag ms-1">{{ !bdaySelectedDay && isBdayCurrentMonth ? 'Bugün · ' : '' }}{{ bdayVisibleList.length }} Kişi</span>
                </div>
                <div class="card-inner-controls">
                  <span
                    class="card-inner-handle"
                    role="button"
                    tabindex="0"
                    title="Sürükle ve taşı"
                    aria-label="Sürükle ve taşı"
                    @pointerdown.stop="startHandleDrag(cardId, $event)"
                    @touchstart.stop="startHandleDrag(cardId, $event)"
                  >
                    <GripVertical :size="14" />
                  </span>
                  <button type="button" class="card-inner-btn" title="Yukarı taşı" @click.stop="moveCard(cardId, 'up')"><ChevronUp :size="14" /></button>
                  <button type="button" class="card-inner-btn" title="Aşağı taşı" @click.stop="moveCard(cardId, 'down')"><ChevronDown :size="14" /></button>
                </div>
              </div>

              <div class="bday-nav">
                <button type="button" class="bday-nav-btn" aria-label="Önceki ay" @click="bdayShiftMonth(-1)"><ChevronLeft :size="16" /></button>
                <strong>{{ BDAY_MONTHS[bdayMonth - 1] }} {{ bdayYear }}</strong>
                <button type="button" class="bday-nav-btn" aria-label="Sonraki ay" @click="bdayShiftMonth(1)"><ChevronRight :size="16" /></button>
              </div>
              <div class="bday-weekdays"><span v-for="(d, i) in ['P','S','Ç','P','C','C','P']" :key="i">{{ d }}</span></div>
              <div class="bday-grid">
                <span v-for="n in bdayLeadingBlanks" :key="`b${n}`" class="bday-cell bday-cell--empty"></span>
                <button
                  v-for="day in bdayDaysInMonth"
                  :key="day"
                  type="button"
                  class="bday-cell"
                  :class="{ 'has-bday': bdayByDay[day], 'is-today': isBdayToday(day), 'is-selected': bdaySelectedDay === day }"
                  @click="bdaySelectedDay = bdaySelectedDay === day ? 0 : day"
                >{{ day }}</button>
              </div>

              <div v-if="bdayVisibleList.length" class="bday-list">
                <div v-for="(item, index) in bdayVisibleList" :key="`${item.name}-${index}`" class="bday-row">
                  <div class="bday-date">{{ item.shownDay }} {{ BDAY_MONTHS[bdayMonth - 1].slice(0, 3) }}</div>
                  <div class="bday-meta">
                    <strong>{{ item.name }}</strong>
                    <span v-if="item.info">{{ item.info }}</span>
                  </div>
                  <span class="clay-pill-tag">{{ bdayYear - item.year }}. yaş</span>
                </div>
              </div>
              <div v-else class="text-center py-3 text-xs text-muted">
                {{ bdaySelectedDay ? 'Bu gün doğum günü yok.' : (isBdayCurrentMonth ? 'Bugün doğum günü yok.' : 'Doğum günlerini görmek için bir gün seçin.') }}
              </div>
            </section>

            <!-- 3. BUGÜNÜN NÖBETÇİLERİ -->
            <section
              v-else-if="cardId === 'shifts'"
              class="bento-card bento-card--white"
            >
              <!-- Kart İçi En Üst Kontroller -->
              <div class="card-inner-head">
                <div class="card-inner-title">
                  <CalendarCheck :size="16" class="text-primary" />
                  <span>Bugünün Nöbetçileri</span>
                  <span class="clay-pill-tag ms-1">{{ shifts.length ? `${shifts.length} Kişi` : 'Yok' }}</span>
                </div>
                <div class="card-inner-controls">
                  <RouterLink to="/nobet-onay" class="clay-section-link text-[11px] me-1">
                    Takvim <ChevronRight :size="12" />
                  </RouterLink>
                  <span
                    class="card-inner-handle"
                    role="button"
                    tabindex="0"
                    title="Sürükle ve taşı"
                    aria-label="Sürükle ve taşı"
                    @pointerdown.stop="startHandleDrag(cardId, $event)"
                    @touchstart.stop="startHandleDrag(cardId, $event)"
                  >
                    <GripVertical :size="14" />
                  </span>
                  <button type="button" class="card-inner-btn" title="Yukarı taşı" @click.stop="moveCard(cardId, 'up')"><ChevronUp :size="14" /></button>
                  <button type="button" class="card-inner-btn" title="Aşağı taşı" @click.stop="moveCard(cardId, 'down')"><ChevronDown :size="14" /></button>
                </div>
              </div>

              <div v-if="shifts.length" class="shifts-grid">
                <div v-for="shift in shifts" :key="shift.id" class="shift-card-row">
                  <div class="shift-person-info">
                    <div class="shift-avatar">
                      <img
                        v-if="shift.photo"
                        :src="shift.photo"
                        :alt="shift.name"
                        @error="shift.photo = null"
                      />
                      <span v-else>{{ shift.initials }}</span>
                    </div>
                    <div class="shift-meta">
                      <strong>{{ shift.name }}</strong>
                      <span>
                        <Clock :size="11" />
                        {{ shift.start_time }} - {{ shift.end_time }}
                      </span>
                    </div>
                  </div>
                  <a
                    v-if="shift.phone"
                    :href="`tel:${shift.phone}`"
                    class="shift-call-btn"
                    :title="`${shift.name} ara`"
                    :aria-label="`${shift.name} telefonla ara`"
                  >
                    <Phone :size="15" />
                  </a>
                </div>
              </div>

              <div v-else class="text-center py-3 text-xs text-muted">
                Bugün için atanmış onaylı nöbet kaydı bulunmuyor.
              </div>
            </section>

            <!-- 4. ARAÇ DURUMU -->
            <section
              v-else-if="cardId === 'vehicles' && vehicles"
              class="bento-card bento-card--white"
            >
              <!-- Kart İçi En Üst Kontroller -->
              <div class="card-inner-head">
                <div class="card-inner-title">
                  <CarFront :size="16" class="text-emerald" />
                  <span>Araç & Filo</span>
                  <span class="clay-pill-tag ms-1">{{ vehicles.total }} Araç</span>
                </div>
                <div class="card-inner-controls">
                  <RouterLink to="/arac-takip" class="clay-section-link text-[11px] me-1">
                    Filo <ChevronRight :size="12" />
                  </RouterLink>
                  <span
                    class="card-inner-handle"
                    role="button"
                    tabindex="0"
                    title="Sürükle ve taşı"
                    aria-label="Sürükle ve taşı"
                    @pointerdown.stop="startHandleDrag(cardId, $event)"
                    @touchstart.stop="startHandleDrag(cardId, $event)"
                  >
                    <GripVertical :size="14" />
                  </span>
                  <button type="button" class="card-inner-btn" title="Yukarı taşı" @click.stop="moveCard(cardId, 'up')"><ChevronUp :size="14" /></button>
                  <button type="button" class="card-inner-btn" title="Aşağı taşı" @click.stop="moveCard(cardId, 'down')"><ChevronDown :size="14" /></button>
                </div>
              </div>

              <div
                class="segmented-track"
                role="img"
                :aria-label="`Sahada ${vehicles.field}, serviste ${vehicles.service}, boşta ${vehicles.idle} araç`"
              >
                <span class="seg-pill seg-pill--emerald" :style="{ width: vehicleSegments.field }"></span>
                <span class="seg-pill seg-pill--coral" :style="{ width: vehicleSegments.service }"></span>
                <span class="seg-pill seg-pill--amber" :style="{ width: vehicleSegments.idle }"></span>
              </div>
              <div class="bento-team-metrics">
                <RouterLink :to="{ path: '/arac-takip', query: { durum: 'zimmetli' } }"><span><i class="ds-dot ds-dot--emerald"></i>Sahada</span><strong>{{ vehicles.field }}</strong></RouterLink>
                <RouterLink :to="{ path: '/arac-takip', query: { durum: 'serviste' } }"><span><i class="ds-dot ds-dot--coral"></i>Serviste</span><strong>{{ vehicles.service }}</strong></RouterLink>
                <RouterLink :to="{ path: '/arac-takip', query: { durum: 'bosta' } }"><span><i class="ds-dot ds-dot--amber"></i>Boşta</span><strong>{{ vehicles.idle }}</strong></RouterLink>
              </div>
            </section>

            <!-- 5. GÜNÜN OPERASYONLARI -->
            <section
              v-else-if="cardId === 'operations' && operations.length"
              class="bento-card bento-card--white"
            >
              <!-- Kart İçi En Üst Kontroller, Gün/Ay Toggle & Günlük Sorgula Butonu -->
              <div class="card-inner-head">
                <div class="card-inner-title">
                  <Gauge :size="16" class="text-primary" />
                  <span>Operasyonlar</span>
                  <div class="op-mode-toggle ms-1">
                    <button
                      type="button"
                      class="op-mode-btn"
                      :class="{ 'is-active': operationsMode === 'daily' }"
                      @click="operationsMode = 'daily'"
                    >
                      Gün
                    </button>
                    <button
                      type="button"
                      class="op-mode-btn"
                      :class="{ 'is-active': operationsMode === 'monthly' }"
                      @click="operationsMode = 'monthly'"
                    >
                      Ay
                    </button>
                  </div>
                  <!-- Tek Dokunuşla Günlük Operasyonları Online Sorgula -->
                  <button
                    type="button"
                    class="op-sync-all-btn ms-1"
                    :class="{ 'is-syncing': isSyncingOperations }"
                    :disabled="isSyncingOperations"
                    title="Tüm günlük operasyonları KASKİ sisteminden online sorgula"
                    aria-label="Tüm günlük operasyonları online sorgula"
                    @click="handleSyncOperations"
                  >
                    <RefreshCw :size="12" :class="{ 'clay-spin-icon': isSyncingOperations }" />
                  </button>
                </div>
                <div class="card-inner-controls">
                  <span
                    class="card-inner-handle"
                    role="button"
                    tabindex="0"
                    title="Sürükle ve taşı"
                    aria-label="Sürükle ve taşı"
                    @pointerdown.stop="startHandleDrag(cardId, $event)"
                    @touchstart.stop="startHandleDrag(cardId, $event)"
                  >
                    <GripVertical :size="14" />
                  </span>
                  <button type="button" class="card-inner-btn" title="Yukarı taşı" @click.stop="moveCard(cardId, 'up')"><ChevronUp :size="14" /></button>
                  <button type="button" class="card-inner-btn" title="Aşağı taşı" @click.stop="moveCard(cardId, 'down')"><ChevronDown :size="14" /></button>
                </div>
              </div>

              <div class="operations-grid">
                <article
                  v-for="item in operations"
                  :key="item.key"
                  class="operation-tile cursor-pointer"
                  :class="`op-tile--${item.key}`"
                  @click="goToOperation(item)"
                >
                  <header class="operation-tile-top">
                    <span class="op-icon-bubble" :class="`op-bubble--${item.key}`">
                      <component :is="operationIcons[item.key] || Zap" :size="16" />
                    </span>
                    <span class="op-label">{{ item.label }}</span>
                  </header>

                  <div class="operation-tile-bottom">
                    <strong class="op-stat-num">{{ operationsMode === 'daily' ? item.daily : item.monthly }}</strong>
                    <span class="op-stat-badge">{{ operationsMode === 'daily' ? 'Bugün' : 'Bu ay' }}</span>
                  </div>

                  <footer class="op-footer-row">
                    <span v-if="item.last_update" class="op-last-update">
                      <Clock :size="10" />
                      {{ item.last_update }}
                    </span>
                    <span v-else class="op-last-update text-muted">
                      -
                    </span>
                    <span class="op-tile-git-btn" :class="`op-git--${item.key}`">
                      Git
                      <ArrowUpRight :size="11" />
                    </span>
                  </footer>
                </article>
              </div>
            </section>

            <!-- 6. HIZLI ERİŞİM -->
            <section
              v-else-if="cardId === 'quick_actions' && actions.length"
              class="bento-card bento-card--white"
            >
              <!-- Kart İçi En Üst Kontroller -->
              <div class="card-inner-head">
                <div class="card-inner-title">
                  <Zap :size="16" class="text-amber" />
                  <span>Hızlı Erişim</span>
                  <span class="clay-pill-tag ms-1">{{ actions.length }} Kısayol</span>
                </div>
                <div class="card-inner-controls">
                  <button
                    type="button"
                    class="qa-customize-trigger-btn me-1"
                    title="Kısayolları düzenle ve yeni bağlantı ekle"
                    aria-label="Kısayolları düzenle"
                    @click.stop="showQuickActionsModal = true"
                  >
                    <SlidersHorizontal :size="11" />
                    <span>Özelleştir</span>
                  </button>
                  <span
                    class="card-inner-handle"
                    role="button"
                    tabindex="0"
                    title="Sürükle ve taşı"
                    aria-label="Sürükle ve taşı"
                    @pointerdown.stop="startHandleDrag(cardId, $event)"
                    @touchstart.stop="startHandleDrag(cardId, $event)"
                  >
                    <GripVertical :size="14" />
                  </span>
                  <button type="button" class="card-inner-btn" title="Yukarı taşı" @click.stop="moveCard(cardId, 'up')"><ChevronUp :size="14" /></button>
                  <button type="button" class="card-inner-btn" title="Aşağı taşı" @click.stop="moveCard(cardId, 'down')"><ChevronDown :size="14" /></button>
                </div>
              </div>

              <div class="quick-action-grid">
                <button
                  v-for="action in actions"
                  :key="action.id || action.label"
                  type="button"
                  class="quick-action-tile"
                  :class="`qa-theme--${action.theme || 'blue'}`"
                  @click="openAction(action)"
                >
                  <span class="quick-action-icon">
                    <component
                      :is="iconComponentsMap[action.iconName] || action.icon || Zap"
                      :size="18"
                      :stroke-width="1.8"
                    />
                  </span>
                  <span class="quick-action-text"><strong>{{ action.label }}</strong></span>
                </button>
              </div>
            </section>

            <!-- 7. KURUMSAL DUYURULAR -->
            <section
              v-else-if="cardId === 'announcements' && announcements.length"
              class="announcement-slide-card"
              @click="openAnnouncementDetail(announcements[0])"
            >
              <!-- Kart İçi En Üst Kontroller -->
              <div class="card-inner-head">
                <div class="card-inner-title text-white">
                  <Megaphone :size="15" />
                  <span class="text-[10px] font-bold uppercase tracking-wider text-white/90">KURUMSAL DUYURU</span>
                  <span class="text-[10px] text-white/70">{{ formatDate(announcements[0].date) }}</span>
                </div>
                <div class="card-inner-controls" @click.stop>
                  <span
                    class="card-inner-handle"
                    role="button"
                    tabindex="0"
                    title="Sürükle ve taşı"
                    aria-label="Sürükle ve taşı"
                    @pointerdown.stop="startHandleDrag(cardId, $event)"
                    @touchstart.stop="startHandleDrag(cardId, $event)"
                  >
                    <GripVertical :size="14" />
                  </span>
                  <button type="button" class="card-inner-btn" title="Yukarı taşı" @click.stop="moveCard(cardId, 'up')"><ChevronUp :size="14" /></button>
                  <button type="button" class="card-inner-btn" title="Aşağı taşı" @click.stop="moveCard(cardId, 'down')"><ChevronDown :size="14" /></button>
                </div>
              </div>

              <strong class="block text-sm font-bold text-white truncate drop-shadow-sm">{{ announcements[0].title }}</strong>
              <p class="text-xs text-white/90 line-clamp-2 mt-1 leading-snug">{{ announcements[0].content }}</p>
              <div class="flex items-center gap-1.5 text-[11px] font-semibold text-white/90 mt-2.5 pt-1.5 border-t border-white/15">
                <span>Detayları görüntüle</span>
                <ArrowRight :size="13" class="ms-auto" />
              </div>
            </section>
          </div>
        </template>
      </div>

      <!-- Kapatılan / Gizlenen Kartlar Çubuğu -->
      <div v-if="hiddenCardList.length" class="hidden-cards-bar">
        <div class="flex items-center gap-2">
          <EyeOff :size="15" class="text-muted" />
          <span class="text-xs font-semibold text-muted">
            {{ hiddenCardList.length }} kart gizlendi
          </span>
        </div>
        <button
          type="button"
          class="clay-pill-btn"
          @click="showCustomizeModal = true"
        >
          <span>Yeniden Göster</span>
          <ChevronRight :size="12" />
        </button>
      </div>
    </template>

    <!-- KARTLARI ÖZELLEŞTİRME BOTTOM SHEET MODALI -->
    <div
      v-if="showCustomizeModal"
      class="home-sheet-backdrop"
      @click.self="showCustomizeModal = false"
    >
      <div class="home-sheet-modal">
        <div class="home-sheet-handle"></div>
        <header class="home-sheet-head">
          <div>
            <h3>Ana Sayfayı Özelleştir</h3>
            <p>Kartların sırasını belirleyin, kapatın veya açın.</p>
          </div>
          <button
            type="button"
            class="clay-circle-btn"
            aria-label="Kapat"
            @click="showCustomizeModal = false"
          >
            <X :size="18" />
          </button>
        </header>

        <div class="home-sheet-body">
          <div
            v-for="(cardId, index) in cardOrder"
            :key="cardId"
            class="customize-item-row"
            :class="{ 'is-hidden': cardSettings[cardId]?.hidden }"
          >
            <div class="customize-item-left">
              <span class="customize-item-icon">
                <component :is="CARD_DEFINITIONS[cardId]?.icon || Zap" :size="18" />
              </span>
              <div class="customize-item-meta">
                <strong>{{ CARD_DEFINITIONS[cardId]?.title || cardId }}</strong>
                <span>{{ CARD_DEFINITIONS[cardId]?.description || '' }}</span>
              </div>
            </div>

            <div class="customize-item-actions">
              <button
                type="button"
                class="home-card-btn"
                :disabled="index === 0"
                title="Yukarı Taşı"
                aria-label="Yukarı Taşı"
                @click="moveCard(cardId, 'up')"
              >
                <ChevronUp :size="16" />
              </button>
              <button
                type="button"
                class="home-card-btn"
                :disabled="index === cardOrder.length - 1"
                title="Aşağı Taşı"
                aria-label="Aşağı Taşı"
                @click="moveCard(cardId, 'down')"
              >
                <ChevronDown :size="16" />
              </button>
              <label class="switch-toggle" :title="cardSettings[cardId]?.hidden ? 'Kartı Göster' : 'Kartı Gizle'">
                <input
                  type="checkbox"
                  :checked="!cardSettings[cardId]?.hidden"
                  @change="toggleCardVisibility(cardId)"
                />
                <span class="switch-slider"></span>
              </label>
            </div>
          </div>
        </div>

        <footer class="home-sheet-foot">
          <button
            type="button"
            class="clay-pill-btn clay-pill-btn--secondary"
            @click="resetToDefault"
          >
            <RotateCcw :size="13" />
            <span>Varsayılana Sıfırla</span>
          </button>
          <button
            type="button"
            class="clay-pill-btn ms-auto"
            @click="showCustomizeModal = false"
          >
            <Check :size="14" />
            <span>Tamamla</span>
          </button>
        </footer>
      </div>
    </div>

    <!-- KURUMSAL DUYURU DETAY BOTTOM SHEET MODALI -->
    <div
      v-if="selectedAnnouncement"
      class="home-sheet-backdrop"
      @click.self="closeAnnouncementDetail"
    >
      <div class="home-sheet-modal">
        <div class="home-sheet-handle"></div>
        <header class="home-sheet-head">
          <div class="flex items-center gap-2">
            <span class="w-8 h-8 rounded-xl bg-primary/10 text-primary flex items-center justify-center">
              <Megaphone :size="17" />
            </span>
            <div>
              <span class="text-[10px] font-bold uppercase tracking-wider text-primary">KURUMSAL DUYURU</span>
              <p class="text-xs text-muted">{{ formatDate(selectedAnnouncement.date) }}</p>
            </div>
          </div>
          <button
            type="button"
            class="clay-circle-btn"
            aria-label="Kapat"
            @click="closeAnnouncementDetail"
          >
            <X :size="18" />
          </button>
        </header>

        <div class="home-sheet-body">
          <img
            v-if="selectedAnnouncement.image"
            :src="selectedAnnouncement.image"
            alt="Duyuru Görseli"
            class="w-full h-auto max-h-56 object-cover rounded-2xl mb-2 border border-slate-100"
          />
          <h3 class="text-base font-bold text-ink leading-snug">{{ selectedAnnouncement.title }}</h3>
          <div v-if="selectedAnnouncement.event_date" class="clay-pill-tag self-start">
            <CalendarDays :size="12" />
            <span>Etkinlik: {{ formatDate(selectedAnnouncement.event_date) }}</span>
          </div>
          <p class="text-sm text-slate-700 leading-relaxed whitespace-pre-line mt-2">
            {{ selectedAnnouncement.raw_content || selectedAnnouncement.content }}
          </p>
        </div>

        <footer class="home-sheet-foot">
          <button
            type="button"
            class="clay-pill-btn w-full justify-center py-2.5 text-xs font-bold"
            @click="closeAnnouncementDetail"
          >
            Kapat
          </button>
        </footer>
      </div>
    </div>

    <!-- HIZLI İŞLEMLERİ ÖZELLEŞTİRME BOTTOM SHEET MODALI -->
    <div
      v-if="showQuickActionsModal"
      class="home-sheet-backdrop"
      @click.self="showQuickActionsModal = false"
    >
      <div class="home-sheet-modal qa-custom-sheet">
        <div class="home-sheet-handle"></div>
        
        <header class="home-sheet-head">
          <div>
            <h3>Hızlı Erişim Kısayolları</h3>
            <p>Ana ekrandaki hızlı işlem butonlarını düzenleyin.</p>
          </div>
          <button
            type="button"
            class="clay-circle-btn"
            aria-label="Kapat"
            @click="showQuickActionsModal = false"
          >
            <X :size="18" />
          </button>
        </header>

        <!-- Segmented Tab Seçici -->
        <div class="qa-sticky-tabs-wrap">
          <div class="qa-capsule-tabs">
            <button
              type="button"
              class="qa-capsule-tab"
              :class="{ active: quickActionsModalTab === 'active' }"
              @click="quickActionsModalTab = 'active'"
            >
              <Layers :size="13" />
              <span>Kısayollarım</span>
              <span class="qa-tab-count">{{ userQuickActions.length }}</span>
            </button>
            <button
              type="button"
              class="qa-capsule-tab"
              :class="{ active: quickActionsModalTab === 'system' }"
              @click="quickActionsModalTab = 'system'"
            >
              <FolderOpen :size="13" />
              <span>Sistem Kataloğu</span>
            </button>
            <button
              type="button"
              class="qa-capsule-tab"
              :class="{ active: quickActionsModalTab === 'custom' }"
              @click="quickActionsModalTab = 'custom'"
            >
              <Plus :size="13" />
              <span>Özel Bağlantı</span>
            </button>
          </div>
        </div>

        <div class="home-sheet-body qa-sheet-body">
          <!-- 1. SEKME: AKTİF KISAYOLLAR -->
          <div v-if="quickActionsModalTab === 'active'" class="qa-active-list">
            <div class="qa-tab-lead">
              <span class="qa-lead-text">Ana sayfada 4'lü ızgara halinde gösterilir. Sıralamayı değiştirebilir veya silebilirsiniz.</span>
            </div>

            <div v-if="!userQuickActions.length" class="qa-empty-state">
              <Zap :size="32" class="text-muted" />
              <p>Henüz kısayol eklenmemiş. Aşağıdan varsayılan listeye dönebilir veya yukarıdaki sekmelerden yeni ekleyebilirsiniz.</p>
              <button
                type="button"
                class="clay-pill-btn clay-pill-btn--primary mt-2"
                @click="resetQuickActionsToDefault"
              >
                <RotateCcw :size="13" />
                <span>Varsayılanları Yükle</span>
              </button>
            </div>

            <div
              v-for="(action, index) in userQuickActions"
              :key="action.id"
              class="qa-active-item"
            >
              <div class="qa-item-reorder">
                <button
                  type="button"
                  class="qa-mini-btn"
                  :disabled="index === 0"
                  title="Yukarı Taşı"
                  aria-label="Yukarı Taşı"
                  @click="moveAction(index, 'up')"
                >
                  <ChevronUp :size="14" />
                </button>
                <button
                  type="button"
                  class="qa-mini-btn"
                  :disabled="index === userQuickActions.length - 1"
                  title="Aşağı Taşı"
                  aria-label="Aşağı Taşı"
                  @click="moveAction(index, 'down')"
                >
                  <ChevronDown :size="14" />
                </button>
              </div>

              <div class="qa-item-main">
                <span class="qa-item-bubble" :class="`qa-bubble--${action.theme || 'blue'}`">
                  <component
                    :is="iconComponentsMap[action.iconName] || Zap"
                    :size="16"
                  />
                </span>
                <div class="qa-item-text">
                  <div class="flex items-center gap-1.5">
                    <strong>{{ action.label }}</strong>
                    <span v-if="action.isCustom" class="qa-badge-custom">Özel</span>
                  </div>
                  <span class="qa-item-target">
                    {{ action.subtitle || action.to || action.href }}
                  </span>
                </div>
              </div>

              <button
                type="button"
                class="qa-delete-btn"
                title="Kısayolu Kaldır"
                aria-label="Kısayolu Kaldır"
                @click="removeActionFromUser(action.id)"
              >
                <Trash2 :size="15" />
              </button>
            </div>
          </div>

          <!-- 2. SEKME: SİSTEM SAYFALARI & DERİN BÖLÜMLER -->
          <div v-else-if="quickActionsModalTab === 'system'" class="qa-system-catalog">
            <!-- Popüler Derin Sekmeler Grubu -->
            <div class="qa-group-block">
              <div class="qa-group-title">
                <Sparkles :size="13" class="text-amber" />
                <span>Hızlı Bölümler & Derin Sekmeler</span>
              </div>
              <div class="qa-system-grid">
                <div
                  v-for="sysAction in systemDeepSections"
                  :key="sysAction.id"
                  class="qa-system-card"
                  :class="{ 'is-added': userQuickActions.some(a => a.id === sysAction.id) }"
                >
                  <span class="qa-item-bubble" :class="`qa-bubble--${sysAction.theme || 'blue'}`">
                    <component
                      :is="iconComponentsMap[sysAction.iconName] || Zap"
                      :size="16"
                    />
                  </span>
                  <div class="qa-system-info">
                    <strong>{{ sysAction.label }}</strong>
                    <small>{{ sysAction.subtitle }}</small>
                  </div>
                  <button
                    v-if="userQuickActions.some(a => a.id === sysAction.id)"
                    type="button"
                    class="qa-added-pill"
                    disabled
                  >
                    <Check :size="12" />
                    <span>Ekli</span>
                  </button>
                  <button
                    v-else
                    type="button"
                    class="qa-add-btn"
                    @click="addActionToUser(sysAction)"
                  >
                    <Plus :size="13" />
                    <span>Ekle</span>
                  </button>
                </div>
              </div>
            </div>

            <!-- Ana Sistem Modülleri Grubu -->
            <div class="qa-group-block mt-3">
              <div class="qa-group-title">
                <FolderOpen :size="13" class="text-primary" />
                <span>Ana Sistem Modülleri</span>
              </div>
              <div class="qa-system-grid">
                <div
                  v-for="sysAction in systemMainModules"
                  :key="sysAction.id"
                  class="qa-system-card"
                  :class="{ 'is-added': userQuickActions.some(a => a.id === sysAction.id) }"
                >
                  <span class="qa-item-bubble" :class="`qa-bubble--${sysAction.theme || 'blue'}`">
                    <component
                      :is="iconComponentsMap[sysAction.iconName] || Zap"
                      :size="16"
                    />
                  </span>
                  <div class="qa-system-info">
                    <strong>{{ sysAction.label }}</strong>
                    <small>{{ sysAction.to }}</small>
                  </div>
                  <button
                    v-if="userQuickActions.some(a => a.id === sysAction.id)"
                    type="button"
                    class="qa-added-pill"
                    disabled
                  >
                    <Check :size="12" />
                    <span>Ekli</span>
                  </button>
                  <button
                    v-else
                    type="button"
                    class="qa-add-btn"
                    @click="addActionToUser(sysAction)"
                  >
                    <Plus :size="13" />
                    <span>Ekle</span>
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- 3. SEKME: ÖZEL BAĞLANTI FORMU -->
          <div v-else-if="quickActionsModalTab === 'custom'" class="qa-custom-wrap">
            <!-- 1. Canlı Önizleme Bento Kartı -->
            <div class="qa-preview-bento">
              <span class="qa-preview-badge">Canlı Görünüm Önizlemesi</span>
              <div class="qa-preview-tile-wrap">
                <div class="quick-action-tile qa-preview-tile" :class="`qa-theme--${customNewAction.theme || 'blue'}`">
                  <span class="quick-action-icon">
                    <component :is="iconComponentsMap[customNewAction.iconName] || Zap" :size="18" />
                  </span>
                  <span class="quick-action-text">
                    <strong>{{ customNewAction.label || 'Kısayol Başlığı' }}</strong>
                  </span>
                </div>
              </div>
              <div class="qa-preview-meta">
                <span class="qa-preview-meta-label">Hedef:</span>
                <span class="qa-preview-meta-val truncate">{{ customNewAction.to || 'Belirtilmedi' }}</span>
              </div>
            </div>

            <!-- 2. Hızlı Şablonlar / Hazır Butonlar -->
            <div class="qa-bento-block">
              <div class="qa-block-head">
                <Sparkles :size="13" class="text-amber" />
                <strong>Hızlı Şablonlar</strong>
                <small class="ms-auto text-muted">Tek dokunuşla doldurur</small>
              </div>
              <div class="qa-presets-wrap">
                <button
                  v-for="p in quickPresets"
                  :key="p.label"
                  type="button"
                  class="qa-preset-pill"
                  :class="`qa-chip--${p.theme}`"
                  @click="applyPreset(p)"
                >
                  <component :is="iconComponentsMap[p.iconName] || Zap" :size="13" />
                  <span>{{ p.label }}</span>
                </button>
              </div>
            </div>

            <!-- 3. Bağlantı Detayları Giriş Alanları -->
            <div class="qa-bento-block">
              <div class="qa-block-head">
                <Link2 :size="13" class="text-primary" />
                <strong>Bağlantı Bilgileri</strong>
              </div>

              <div class="qa-form-field">
                <label class="qa-field-label">Kısayol Adı</label>
                <div class="qa-input-box">
                  <Pencil :size="15" class="qa-input-icon text-muted" />
                  <input
                    v-model="customNewAction.label"
                    type="text"
                    placeholder="Örn: Ek Ödemeler, Maaş Kesintisi..."
                    class="qa-field-input"
                  />
                </div>
              </div>

              <div class="qa-form-field mt-2">
                <label class="qa-field-label">Hedef URL / Sekme Adresi</label>
                <div class="qa-input-box">
                  <Link2 :size="15" class="qa-input-icon text-muted" />
                  <input
                    v-model="customNewAction.to"
                    type="text"
                    placeholder="Örn: /personel veya personnel_section:ek_odemeler"
                    class="qa-field-input font-mono text-xs"
                  />
                </div>
              </div>
            </div>

            <!-- 4. İkon ve Renk Seçimi -->
            <div class="qa-bento-block">
              <div class="qa-block-head">
                <Star :size="13" class="text-warning" />
                <strong>İkon ve Renk Teması</strong>
              </div>

              <div class="qa-form-field">
                <label class="qa-field-label">İkon Seçin</label>
                <div class="qa-icon-palette">
                  <button
                    v-for="iconName in availableIconNames"
                    :key="iconName"
                    type="button"
                    class="qa-palette-icon-btn"
                    :class="{ active: customNewAction.iconName === iconName }"
                    @click="customNewAction.iconName = iconName"
                  >
                    <component :is="iconComponentsMap[iconName] || Zap" :size="18" />
                  </button>
                </div>
              </div>

              <div class="qa-form-field mt-3">
                <label class="qa-field-label">Renk Teması</label>
                <div class="qa-color-tokens">
                  <button
                    v-for="th in availableThemes"
                    :key="th.key"
                    type="button"
                    class="qa-color-circle-btn"
                    :style="{ backgroundColor: th.hex }"
                    :class="{ active: customNewAction.theme === th.key }"
                    :title="th.label"
                    @click="customNewAction.theme = th.key"
                  >
                    <Check v-if="customNewAction.theme === th.key" :size="14" class="text-white" />
                  </button>
                </div>
              </div>
            </div>

            <!-- Kaydet Butonu -->
            <button
              type="button"
              class="qa-save-action-btn"
              @click="submitCustomAction"
            >
              <Plus :size="16" />
              <span>Bu Kısayolu Ekle</span>
            </button>
          </div>
        </div>

        <footer class="home-sheet-foot">
          <button
            v-if="quickActionsModalTab === 'active'"
            type="button"
            class="clay-pill-btn clay-pill-btn--secondary"
            @click="resetQuickActionsToDefault"
          >
            <RotateCcw :size="13" />
            <span>Varsayılana Sıfırla</span>
          </button>
          <button
            v-else
            type="button"
            class="clay-pill-btn clay-pill-btn--secondary"
            @click="quickActionsModalTab = 'active'"
          >
            <ChevronLeft :size="13" />
            <span>Kısayollara Dön</span>
          </button>

          <button
            type="button"
            class="clay-pill-btn ms-auto"
            @click="showQuickActionsModal = false"
          >
            <Check :size="14" />
            <span>Tamamla</span>
          </button>
        </footer>
      </div>
    </div>

    <!-- Yüzen Toast Bildirimi -->
    <transition name="fade">
      <div v-if="toastMessage" class="clay-floating-toast">
        <Check :size="15" class="text-success" />
        <span>{{ toastMessage }}</span>
      </div>
    </transition>
  </section>
</template>
