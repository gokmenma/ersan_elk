<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ArrowUpRight, Bell, CalendarDays, CarFront, ChartNoAxesCombined, ChevronRight, ClipboardCheck, FileText, Gauge, Megaphone, RefreshCw, ShieldAlert, UsersRound, Zap } from '@lucide/vue'
import { auth } from '../services/auth.js'
import { getDashboard } from '../services/dashboard.js'

const router = useRouter()
const loading = ref(true)
const error = ref('')
const dashboard = ref({})
const actionDefinitions = [
  { permission: 'personnel', label: 'Personel', icon: UsersRound, to: '/personel' },
  { permission: 'vehicles', label: 'Araçlar', icon: CarFront, href: '/mobile/index.php?p=arac' },
  { permission: 'reports', label: 'Puantaj', icon: CalendarDays, href: '/mobile/index.php?p=puantaj' },
  { permission: 'documents', label: 'Evraklar', icon: FileText, href: '/mobile/index.php?p=evrak-takip' },
  { permission: 'tasks', label: 'Görevler', icon: ClipboardCheck, to: '/gorevler' },
  { permission: 'reports', label: 'Raporlar', icon: ChartNoAxesCombined, to: '/raporlar' },
]
const actions = computed(() => actionDefinitions.filter(item => auth.state.permissions[item.permission]).slice(0, 4))
const operations = computed(() => dashboard.value.operations || [])
const statisticCards = computed(() => operations.value.slice(0, 6))
const personnelRate = computed(() => { const total = Number(dashboard.value.personnel?.total || 0); return total ? Math.min(100, Math.round((Number(dashboard.value.personnel?.working || 0) / total) * 100)) : 0 })
const vehicleSegments = computed(() => { const total = Number(dashboard.value.vehicles?.total || 0); const ratio = value => total ? `${Math.max(0, (Number(value || 0) / total) * 100)}%` : '0%'; return { field: ratio(dashboard.value.vehicles?.field), service: ratio(dashboard.value.vehicles?.service), idle: ratio(dashboard.value.vehicles?.idle) } })
const operationIcons = { sealing: ShieldAlert, cut_open: Zap, meter_change: Gauge, reading: Gauge, illegal: ShieldAlert, notice: Megaphone }

function openAction(action) { if (action.to) router.push(action.to); else if (action.href) window.location.href = action.href }
function formatDate(value) { return value ? new Intl.DateTimeFormat('tr-TR', { day: '2-digit', month: 'short' }).format(new Date(value)) : '' }
async function load() { loading.value = true; error.value = ''; try { dashboard.value = await getDashboard() } catch (requestError) { error.value = requestError.message } finally { loading.value = false } }
onMounted(load)
</script>

<template>
  <section class="ds-screen ds-home">
    <header class="ds-home-header">
      <span class="ds-avatar">{{ auth.state.user?.initials || 'EE' }}</span>
      <div><small>Tekrar hoş geldiniz</small><h1>{{ auth.state.user?.username || 'Kullanıcı' }}</h1></div>
      <button type="button" aria-label="Bildirimler"><Bell :size="21" /><i></i></button>
    </header>

    <div v-if="loading" class="ds-loading"><i></i><i></i><i></i></div>
    <div v-else-if="error" class="ds-state"><RefreshCw :size="24" /><strong>Veriler alınamadı</strong><p>{{ error }}</p><button type="button" @click="load">Tekrar dene</button></div>

    <template v-else>
      <section v-if="dashboard.personnel || dashboard.vehicles || operations.length" class="ds-overview-card">
        <div v-if="dashboard.personnel || dashboard.vehicles" class="ds-summary-grid">
        <button v-if="dashboard.personnel" type="button" class="ds-summary-card ds-summary-card--personnel" @click="router.push('/personel')">
          <header><span>PERSONEL DURUMU</span><ChevronRight :size="18" /></header>
          <div class="ds-summary-value"><strong>{{ dashboard.personnel.total }}</strong><span>toplam personel</span></div>
          <div class="ds-summary-progress"><i :style="{ width: `${personnelRate}%` }"></i></div>
          <footer><span><i></i><small>Sahada</small><b>{{ dashboard.personnel.working }}</b></span><span><i></i><small>İzinli</small><b>{{ dashboard.personnel.on_leave }}</b></span><span><i></i><small>Diğer</small><b>{{ Math.max(0, Number(dashboard.personnel.total) - Number(dashboard.personnel.working) - Number(dashboard.personnel.on_leave)) }}</b></span></footer>
        </button>
        <a v-if="dashboard.vehicles" href="/mobile/index.php?p=arac" class="ds-summary-card ds-summary-card--vehicles">
          <header><span>ARAÇ DURUMU</span><ChevronRight :size="18" /></header>
          <div class="ds-summary-value"><strong>{{ dashboard.vehicles.total }}</strong><span>aktif araç</span></div>
          <div class="ds-summary-segments"><i :style="{ width: vehicleSegments.field }"></i><i :style="{ width: vehicleSegments.service }"></i><i :style="{ width: vehicleSegments.idle }"></i></div>
          <footer><span><i></i><small>Sahada</small><b>{{ dashboard.vehicles.field }}</b></span><span><i></i><small>Serviste</small><b>{{ dashboard.vehicles.service }}</b></span><span><i></i><small>Boşta</small><b>{{ dashboard.vehicles.idle }}</b></span></footer>
        </a>
        </div>
        <div v-if="operations.length" class="ds-stat-grid">
          <article v-for="item in statisticCards" :key="item.key"><header><span><component :is="operationIcons[item.key] || Zap" :size="17" /></span><small>{{ item.label }}</small></header><div><strong>{{ item.daily }}</strong><p>Bu ay <b>{{ item.monthly }}</b></p></div></article>
        </div>
      </section>

      <section v-if="actions.length" class="ds-section">
        <header class="ds-section-title"><h2>Hızlı işlemler</h2><span>Yetkili modüller</span></header>
        <div class="ds-quick-actions"><button v-for="action in actions" :key="action.label" type="button" @click="openAction(action)"><span><component :is="action.icon" :size="20" :stroke-width="1.8" /></span><strong>{{ action.label }}</strong><i><ArrowUpRight :size="13" /></i></button></div>
      </section>

      <section v-if="dashboard.announcements?.length" class="ds-section ds-announcements">
        <header class="ds-section-title"><h2>Duyurular</h2><span>{{ dashboard.announcements.length }} yeni</span></header>
        <article v-for="announcement in dashboard.announcements.slice(0, 3)" :key="`${announcement.date}-${announcement.title}`"><span><Megaphone :size="18" /></span><div><strong>{{ announcement.title }}</strong><p>{{ announcement.content || 'Duyuru detayları yayınlandı.' }}</p></div><time>{{ formatDate(announcement.date) }}</time></article>
      </section>

      <div v-if="!operations.length && !dashboard.personnel && !dashboard.vehicles && !actions.length" class="ds-state"><ShieldAlert :size="24" /><strong>Görüntülenebilir modül bulunmuyor</strong><p>Hesabınıza atanmış modül yetkisi bulunmuyor.</p></div>
    </template>
  </section>
</template>
