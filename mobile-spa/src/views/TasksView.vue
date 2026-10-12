<script setup>
import { Check, ChevronRight, CircleAlert, Clock3, Filter, MapPin } from '@lucide/vue'
import TopBar from '../components/TopBar.vue'

const tasks = [
  { status: 'Acil', tone: 'danger', title: 'Sayaç değişim kontrolü', place: 'Dulkadiroğlu · 14 kayıt', time: '11:30' },
  { status: 'Sahada', tone: 'primary', title: 'Kesme–açma listesi', place: 'Onikişubat · Ekip 7', time: '13:00' },
  { status: 'Planlandı', tone: 'neutral', title: 'Araç bakım işlemi', place: '46 ABV 214 · Servis', time: '15:45' },
]
</script>

<template>
  <section class="screen">
    <TopBar eyebrow="OPERASYON" title="Görevler" subtitle="Bugünkü iş planı ve saha durumu" />
    <div class="segmented"><button class="active">Bugün</button><button>Yaklaşan</button><button>Tamamlanan</button><button aria-label="Filtre"><Filter :size="16" /></button></div>
    <div class="page-stat"><div><span>Bugün</span><strong>18 görev</strong><small>11 görev tamamlandı</small></div><div class="progress-ring"><b>%61</b></div></div>
    <div class="task-list">
      <article v-for="task in tasks" :key="task.title" class="task-card">
        <div class="task-status" :class="`status-${task.tone}`"><CircleAlert v-if="task.tone === 'danger'" :size="14" /><Check v-else :size="14" />{{ task.status }}</div>
        <h3>{{ task.title }}</h3>
        <p><MapPin :size="15" /> {{ task.place }}</p>
        <footer><span><Clock3 :size="14" /> {{ task.time }}</span><button aria-label="Detay"><ChevronRight :size="18" /></button></footer>
      </article>
    </div>
  </section>
</template>
