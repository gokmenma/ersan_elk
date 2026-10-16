<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ArrowUpRight, CalendarDays, CarFront, ChartNoAxesCombined, ChevronRight, ClipboardCheck, FileText, Gauge, Megaphone, RefreshCw, ShieldAlert, UsersRound, Zap } from '@lucide/vue'
import TopBar from '../components/TopBar.vue'
import { auth } from '../services/auth.js'
import { getDashboard } from '../services/dashboard.js'

const router = useRouter()
const loading = ref(true)
const error = ref('')
const dashboard = ref({})
const today = new Intl.DateTimeFormat('tr-TR', { day: 'numeric', month: 'long', weekday: 'long', timeZone: 'Europe/Istanbul' }).format(new Date())
const actionDefinitions = [
  { permission: 'personnel', label: 'Personel', icon: UsersRound, to: '/personel' },
  { permission: 'vehicles', label: 'Araçlar', icon: CarFront, to: '/arac-takip' },
  { permission: 'reports', label: 'Puantaj', icon: CalendarDays, href: '/mobile/index.php?p=puantaj' },
  { permission: 'documents', label: 'Evraklar', icon: FileText, to: '/evrak-takip' },
  { permission: 'tasks', label: 'Görevler', icon: ClipboardCheck, to: '/gorevler' },
  { permission: 'reports', label: 'Raporlar', icon: ChartNoAxesCombined, to: '/raporlar' },
]
const actions = computed(() => actionDefinitions.filter(item => auth.state.permissions[item.permission]).slice(0, 4))
const personnel = computed(() => auth.state.permissions.personnel ? dashboard.value.personnel : null)
const vehicles = computed(() => auth.state.permissions.vehicles ? dashboard.value.vehicles : null)
const operations = computed(() => dashboard.value.operations || [])
const statisticCards = computed(() => operations.value.slice(0, 6))
const personnelRate = computed(() => {
  const total = Number(personnel.value?.total || 0)
  return total ? Math.min(100, Math.round(Number(personnel.value?.working || 0) / total * 100)) : 0
})
const personnelSegments = computed(() => {
  const total = Number(personnel.value?.total || 0)
  const working = Math.min(total, Math.max(0, Number(personnel.value?.working || 0)))
  const leave = Math.min(total - working, Math.max(0, Number(personnel.value?.on_leave || 0)))
  return { working: total ? working / total * 100 : 0, leave: total ? leave / total * 100 : 0, other: total ? (total - working - leave) / total * 100 : 0 }
})
const vehicleSegments = computed(() => {
  const total = Number(vehicles.value?.total || 0)
  const ratio = value => total ? `${Math.max(0, Number(value || 0) / total * 100)}%` : '0%'
  return { field: ratio(vehicles.value?.field), service: ratio(vehicles.value?.service), idle: ratio(vehicles.value?.idle) }
})
const operationIcons = { sealing: ShieldAlert, cut_open: Zap, meter_change: Gauge, reading: Gauge, illegal: ShieldAlert, notice: Megaphone }
function openAction(action) {
  if (action.to) router.push(action.to)
  else if (action.href) window.location.href = action.href
}
function formatDate(value) {
  const date = new Date(value)
  return value && !Number.isNaN(date.getTime()) ? new Intl.DateTimeFormat('tr-TR', { day: '2-digit', month: 'short' }).format(date) : ''
}
async function load() {
  loading.value = true
  error.value = ''
  try { dashboard.value = await getDashboard() }
  catch (reason) { error.value = reason.message }
  finally { loading.value = false }
}
onMounted(load)
</script>

<template>
  <section class="ds-screen ds-home" :aria-busy="loading">
    <TopBar :eyebrow="today" :subtitle="auth.state.company?.name ? 'İşinizin bugünkü görünümü.' : 'Günlük operasyon ve saha özeti.'" />
    <div v-if="loading" class="clay-skeleton-grid" role="status" aria-label="Günlük özet yükleniyor">
      <div class="clay-skeleton-card clay-skeleton-card--md"></div>
      <div class="clay-skeleton-card clay-skeleton-card--lg"></div>
    </div>
    <div v-else-if="error" class="clay-state-card" role="alert">
      <RefreshCw :size="24" />
      <strong>Veriler alınamadı</strong><p>{{ error }}</p>
      <button type="button" class="clay-retry-btn" @click="load">Tekrar dene</button>
    </div>
    <template v-else>
      <section v-if="personnel" class="bento-card bento-card--dark bento-overview">
        <div class="bento-overview-head">
          <span class="dark-pill-tag"><span class="bento-status-dot"></span> BUGÜN SAHADA</span>
          <UsersRound :size="22" aria-hidden="true" />
        </div>
        <div class="bento-overview-value"><strong>{{ personnel.working }}</strong><span>personel<br />görevde</span></div>
        <div class="bento-overview-footer">
          <span>{{ personnel.total }} kişilik ekibin %{{ personnelRate }}’i</span>
          <RouterLink to="/personel" class="bento-overview-link">Ekibi gör <ArrowUpRight :size="16" /></RouterLink>
        </div>
      </section>
      <section v-if="actions.length" class="clay-section">
        <div class="clay-section-head"><h2>Hızlı erişim</h2><span class="clay-section-caption">İşiniz bir dokunuş uzakta</span></div>
        <div class="quick-action-grid" :style="{ '--action-count': actions.length }">
          <button v-for="action in actions" :key="action.label" type="button" class="quick-action-tile" @click="openAction(action)">
            <span class="quick-action-icon"><component :is="action.icon" :size="22" :stroke-width="1.7" /></span>
            <span class="quick-action-text"><strong>{{ action.label }}</strong></span>
          </button>
        </div>
      </section>
      <section v-if="personnel" class="clay-section">
        <div class="clay-section-head"><h2>Ekip özeti</h2><RouterLink to="/personel" class="clay-section-link">Tümünü gör <ChevronRight :size="14" /></RouterLink></div>
        <div class="bento-card bento-card--white">
          <div class="bento-team-heading"><span class="clay-page-eyebrow">TOPLAM PERSONEL</span><strong>{{ personnel.total }} <small>kişi</small></strong></div>
          <div class="segmented-track" role="img" :aria-label="`Sahada ${personnel.working}, izinli ${personnel.on_leave}, diğer ${Math.max(0, Number(personnel.total) - Number(personnel.working) - Number(personnel.on_leave))} personel`">
            <span class="seg-pill seg-pill--blue" :style="{ width: `${personnelSegments.working}%` }"></span>
            <span class="seg-pill seg-pill--yellow" :style="{ width: `${personnelSegments.leave}%` }"></span>
            <span class="seg-pill seg-pill--coral" :style="{ width: `${personnelSegments.other}%` }"></span>
          </div>
          <div class="bento-team-metrics">
            <div><span><i class="ds-dot ds-dot--blue"></i>Sahada</span><strong>{{ personnel.working }}</strong></div>
            <div><span><i class="ds-dot ds-dot--yellow"></i>İzinli</span><strong>{{ personnel.on_leave }}</strong></div>
            <div><span><i class="ds-dot ds-dot--coral"></i>Diğer</span><strong>{{ Math.max(0, Number(personnel.total) - Number(personnel.working) - Number(personnel.on_leave)) }}</strong></div>
          </div>
        </div>
      </section>
      <section v-if="vehicles" class="clay-section">
        <div class="clay-section-head"><h2>Araç & filo</h2><RouterLink to="/arac-takip" class="clay-section-link">Filoyu gör <ChevronRight :size="14" /></RouterLink></div>
        <div class="bento-card bento-card--white">
          <div class="bento-fleet-heading"><span class="quick-action-icon"><CarFront :size="22" /></span><div><strong>{{ vehicles.total }} araç</strong><span>Filo durum dağılımı</span></div><RouterLink class="clay-circle-btn" to="/arac-takip" aria-label="Araç takip ekranını aç"><ArrowUpRight :size="20" /></RouterLink></div>
          <div class="segmented-track" role="img" :aria-label="`Sahada ${vehicles.field}, serviste ${vehicles.service}, boşta ${vehicles.idle} araç`"><span class="seg-pill seg-pill--emerald" :style="{ width: vehicleSegments.field }"></span><span class="seg-pill seg-pill--coral" :style="{ width: vehicleSegments.service }"></span><span class="seg-pill seg-pill--amber" :style="{ width: vehicleSegments.idle }"></span></div>
          <div class="bento-team-metrics"><div><span>Sahada</span><strong>{{ vehicles.field }}</strong></div><div><span>Serviste</span><strong>{{ vehicles.service }}</strong></div><div><span>Boşta</span><strong>{{ vehicles.idle }}</strong></div></div>
        </div>
      </section>
      <section v-if="operations.length" class="clay-section">
        <div class="clay-section-head"><h2>Günün operasyonları</h2><span class="clay-pill-tag">Günlük / Aylık</span></div>
        <div class="operations-grid"><article v-for="item in statisticCards" :key="item.key" class="operation-tile"><header class="operation-tile-top"><span class="op-icon-bubble"><component :is="operationIcons[item.key] || Zap" :size="18" /></span><span class="op-label">{{ item.label }}</span></header><div class="operation-tile-bottom"><strong>{{ item.daily }}</strong><span>Bu ay <b>{{ item.monthly }}</b> işlem</span></div></article></div>
      </section>
      <section v-if="dashboard.announcements?.length" class="clay-section">
        <div class="clay-section-head"><h2>Duyurular</h2><span class="clay-pill-tag">{{ dashboard.announcements.length }} duyuru</span></div>
        <div class="announcements-stack"><article v-for="announcement in dashboard.announcements.slice(0, 3)" :key="`${announcement.date}-${announcement.title}`" class="announcement-card"><span class="announcement-icon"><Megaphone :size="19" /></span><div class="announcement-body"><strong>{{ announcement.title }}</strong><p>{{ announcement.content || 'Duyuru detayları yayınlandı.' }}</p></div><time class="announcement-date">{{ formatDate(announcement.date) }}</time></article></div>
      </section>
      <div v-if="!operations.length && !personnel && !vehicles && !actions.length" class="clay-state-card"><ShieldAlert :size="24" /><strong>Görüntülenebilir modül bulunmuyor</strong><p>Hesabınıza atanmış modül yetkisi bulunmuyor.</p></div>
    </template>
  </section>
</template>
