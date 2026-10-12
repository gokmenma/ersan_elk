<script setup>
import { ref } from 'vue'
import {
  ArrowUpRight, CalendarDays, CarFront, ChevronRight, CircleCheckBig,
  Clock3, FileText, MapPinned, Plus, UsersRound, WalletCards, Zap,
} from '@lucide/vue'
import TopBar from '../components/TopBar.vue'

const period = ref('Bugün')
const periods = ['Bugün', 'Bu hafta', 'Bu ay']

const actions = [
  { label: 'Personel', meta: 'Ekip rehberi', icon: UsersRound, tone: 'blue', to: '/personel' },
  { label: 'Araçlar', meta: '24 sahada', icon: CarFront, tone: 'mint' },
  { label: 'Puantaj', meta: '8 bekliyor', icon: CalendarDays, tone: 'orange' },
  { label: 'Evraklar', meta: '12 yeni', icon: FileText, tone: 'violet' },
]

const operations = [
  { time: '09:40', title: 'Kesme–açma ekibi sahaya çıktı', detail: 'Ekip 7 · Onikişubat', icon: MapPinned, tone: 'blue' },
  { time: '09:18', title: 'KM bildirimi onaylandı', detail: '46 ABV 214 · 186.420 km', icon: CircleCheckBig, tone: 'mint' },
  { time: '08:55', title: 'Yeni avans talebi', detail: 'Mehmet Kaya · ₺4.500', icon: WalletCards, tone: 'orange' },
]
</script>

<template>
  <section class="screen home-screen">
    <TopBar />

    <section class="hero-card">
      <div class="hero-glow hero-glow-one"></div>
      <div class="hero-glow hero-glow-two"></div>
      <div class="hero-topline">
        <span><Zap :size="15" fill="currentColor" /> Operasyon merkezi</span>
        <button type="button">Canlı <span class="live-dot"></span></button>
      </div>
      <div class="hero-copy">
        <p>Bugünün tamamlanma oranı</p>
        <div class="hero-value"><strong>%84</strong><span><ArrowUpRight :size="14" /> %12</span></div>
        <div class="progress-track"><i></i></div>
        <div class="hero-foot"><span>326 / 388 işlem</span><span>Son güncelleme 09:42</span></div>
      </div>
    </section>

    <div class="section-heading">
      <div><span class="section-kicker">KISAYOLLAR</span><h2>Hızlı işlemler</h2></div>
      <button type="button" class="text-button">Tümü <ChevronRight :size="15" /></button>
    </div>

    <div class="quick-grid">
      <button v-for="action in actions" :key="action.label" type="button" class="quick-card" @click="action.to && $router.push(action.to)">
        <span class="quick-icon" :class="`tone-${action.tone}`"><component :is="action.icon" :size="21" /></span>
        <strong>{{ action.label }}</strong><small>{{ action.meta }}</small>
      </button>
    </div>

    <section class="metric-card">
      <div class="metric-head">
        <div><span class="section-kicker">SAHA PERFORMANSI</span><h2>İş hareketi</h2></div>
        <div class="period-switch" aria-label="Dönem seçimi">
          <button v-for="item in periods" :key="item" type="button" :class="{ active: period === item }" @click="period = item">{{ item }}</button>
        </div>
      </div>
      <div class="metric-body">
        <div><strong>388</strong><span>toplam iş</span></div>
        <div class="mini-chart" aria-label="Saatlik performans grafiği">
          <i v-for="height in [28, 42, 37, 58, 52, 76, 64, 88, 72, 95, 78, 90]" :key="height" :style="{ height: `${height}%` }"></i>
        </div>
      </div>
    </section>

    <div class="section-heading compact">
      <div><span class="section-kicker">CANLI AKIŞ</span><h2>Son hareketler</h2></div>
      <button type="button" class="round-add" aria-label="Yeni işlem"><Plus :size="18" /></button>
    </div>

    <div class="activity-list">
      <article v-for="item in operations" :key="item.time" class="activity-row">
        <span class="activity-icon" :class="`tone-${item.tone}`"><component :is="item.icon" :size="19" /></span>
        <div><strong>{{ item.title }}</strong><small>{{ item.detail }}</small></div>
        <time><Clock3 :size="12" /> {{ item.time }}</time>
      </article>
    </div>
  </section>
</template>
