<script setup>
import { computed, ref } from 'vue'
import {
  Calendar,
  Check,
  ChevronRight,
  CircleAlert,
  Clock3,
  FileText,
  Filter,
  Plus,
  UserRound,
  Wallet,
  Wrench,
} from '@lucide/vue'
import TopBar from '../components/TopBar.vue'

const activeFilter = ref('all')

const requests = [
  {
    id: 1,
    type: 'izin',
    category: 'İzin Talebi',
    title: 'Yıllık İzin Talebi',
    person: 'Ahmet Yılmaz',
    detail: '3 Gün · 14 - 16 Ekim 2026',
    status: 'Beklemede',
    tone: 'primary',
    time: 'Bugün 10:20',
  },
  {
    id: 2,
    type: 'avans',
    category: 'Avans Talebi',
    title: 'Nakit Maaş Avansı',
    person: 'Mustafa Demir',
    detail: '15.000 ₺ · Acil İhtiyaç',
    status: 'Onay Bekliyor',
    tone: 'warning',
    time: 'Dün 16:45',
  },
  {
    id: 3,
    type: 'ariza',
    category: 'Saha / Arıza',
    title: 'Sayaç ve Aparat Değişimi',
    person: 'Onikişubat Ekip 3',
    detail: 'Dulkadiroğlu · 12 Adet Aparat',
    status: 'İşlemde',
    tone: 'neutral',
    time: '11 Ekim',
  },
  {
    id: 4,
    type: 'izin',
    category: 'İzin Talebi',
    title: 'Mazeret İzni',
    person: 'Burak Şahin',
    detail: '1 Gün · 13 Ekim 2026',
    status: 'Onaylandı',
    tone: 'success',
    time: '10 Ekim',
  },
  {
    id: 5,
    type: 'avans',
    category: 'Avans Talebi',
    title: 'Haftalık Masraf Avansı',
    person: 'Emre Çelik',
    detail: '5.000 ₺ · Yakıt & Konaklama',
    status: 'Onaylandı',
    tone: 'success',
    time: '09 Ekim',
  },
]

const filteredRequests = computed(() => {
  if (activeFilter.value === 'all') return requests
  return requests.filter(r => r.type === activeFilter.value)
})
</script>

<template>
  <section class="screen">
    <TopBar eyebrow="SÜREÇ YÖNETİMİ" title="Talepler" subtitle="İzin, avans ve saha talepleri" />

    <div class="segmented">
      <button :class="{ active: activeFilter === 'all' }" @click="activeFilter = 'all'">Tümü</button>
      <button :class="{ active: activeFilter === 'izin' }" @click="activeFilter = 'izin'">İzin</button>
      <button :class="{ active: activeFilter === 'avans' }" @click="activeFilter = 'avans'">Avans</button>
      <button :class="{ active: activeFilter === 'ariza' }" @click="activeFilter = 'ariza'">Saha</button>
    </div>

    <div class="page-stat" style="background: linear-gradient(145deg, #3765ed, #1f45be); box-shadow: 0 18px 30px rgba(31,69,190,.22);">
      <div>
        <span>BEKLEYEN İŞLEMLER</span>
        <strong>2 Talep</strong>
        <small>Onay bekleyen izin ve avans işlemleri</small>
      </div>
      <div class="progress-ring"><b>%60</b></div>
    </div>

    <div class="task-list">
      <article v-for="req in filteredRequests" :key="req.id" class="task-card">
        <div class="task-status" :class="`status-${req.tone}`">
          <CircleAlert v-if="req.tone === 'warning' || req.tone === 'danger'" :size="14" />
          <Check v-else-if="req.tone === 'success'" :size="14" />
          <Clock3 v-else :size="14" />
          {{ req.status }}
        </div>
        <h3 style="margin-top: 8px;">{{ req.title }}</h3>
        <p><UserRound :size="14" /> <strong>{{ req.person }}</strong> · {{ req.detail }}</p>
        <footer>
          <span><Calendar :size="14" /> {{ req.time }}</span>
          <button type="button" aria-label="Detay" @click="() => {}">
            <ChevronRight :size="18" />
          </button>
        </footer>
      </article>
    </div>
  </section>
</template>
