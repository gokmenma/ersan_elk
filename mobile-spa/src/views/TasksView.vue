<script setup>
import { ref } from 'vue'
import { Check, ChevronRight, CircleAlert, Clock3, Filter, MapPin } from '@lucide/vue'
import TopBar from '../components/TopBar.vue'

const activeTab = ref('today')

const tasks = [
  { status: 'Acil', tone: 'danger', title: 'Sayaç değişim kontrolü', place: 'Dulkadiroğlu · 14 kayıt', time: '11:30' },
  { status: 'Sahada', tone: 'primary', title: 'Kesme–açma listesi', place: 'Onikişubat · Ekip 7', time: '13:00' },
  { status: 'Planlandı', tone: 'neutral', title: 'Araç bakım işlemi', place: '46 ABV 214 · Servis', time: '15:45' },
]
</script>

<template>
  <section class="ds-screen">
    <!-- 1. Standart TopBar -->
    <TopBar eyebrow="OPERASYON" title="Görevler" subtitle="Bugünkü iş planı ve saha durumu" :back="true" />

    <!-- 2. Hero Bento Kart (İlerleme Durumu) -->
    <section class="bento-card bento-card--white">
      <header class="bento-head">
        <div class="bento-head-title">
          <h3>Günlük Görev Planı</h3>
        </div>
        <span class="clay-pill-tag">%61 Tamamlandı</span>
      </header>

      <!-- Segmentli İlerleme Çubuğu -->
      <div class="segmented-track">
        <span class="seg-pill seg-pill--blue" style="width: 61%;"></span>
        <span class="seg-pill seg-pill--amber" style="width: 25%;"></span>
        <span class="seg-pill seg-pill--coral" style="width: 14%;"></span>
      </div>

      <div class="bento-metrics-grid">
        <div class="metric-cell">
          <strong>18 <small>görev</small></strong>
          <span>Bugünkü Toplam</span>
        </div>
        <div class="metric-cell">
          <strong class="text-emerald">11 <small>bitti</small></strong>
          <span>Tamamlanan</span>
        </div>
      </div>
    </section>

    <!-- 3. Filtre Hapları -->
    <div class="clay-tabs-wrap">
      <button
        type="button"
        class="clay-tab-btn"
        :class="{ 'is-active': activeTab === 'today' }"
        @click="activeTab = 'today'"
      >
        <span>Bugün</span>
        <span class="clay-tab-badge">18</span>
      </button>
      <button
        type="button"
        class="clay-tab-btn"
        :class="{ 'is-active': activeTab === 'upcoming' }"
        @click="activeTab = 'upcoming'"
      >
        <span>Yaklaşan</span>
      </button>
      <button
        type="button"
        class="clay-tab-btn"
        :class="{ 'is-active': activeTab === 'completed' }"
        @click="activeTab = 'completed'"
      >
        <span>Tamamlanan</span>
      </button>
    </div>

    <!-- 4. Görev Liste Kartları (Bento Cards) -->
    <div class="task-cards-list">
      <article v-for="task in tasks" :key="task.title" class="task-bento-card">
        <header class="task-head">
          <span
            class="clay-badge"
            :class="{
              'clay-badge--coral': task.tone === 'danger',
              'clay-badge--blue': task.tone === 'primary',
              'clay-badge--amber': task.tone === 'neutral'
            }"
          >
            <CircleAlert v-if="task.tone === 'danger'" :size="12" />
            <Check v-else :size="12" />
            <span>{{ task.status }}</span>
          </span>
          <span class="task-time">
            <Clock3 :size="12" />
            <span>{{ task.time }}</span>
          </span>
        </header>

        <h4 class="task-title">{{ task.title }}</h4>

        <footer class="task-foot">
          <span class="task-place">
            <MapPin :size="13" />
            <span>{{ task.place }}</span>
          </span>
          <span class="task-arrow">
            <ChevronRight :size="16" />
          </span>
        </footer>
      </article>
    </div>
  </section>
</template>

<style scoped>
.task-cards-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.task-bento-card {
  padding: 16px;
  border-radius: var(--ds-radius-tile);
  background: var(--ds-surface);
  border: 1px solid var(--ds-border);
  box-shadow: var(--ds-shadow-tile);
  cursor: pointer;
  transition: transform 0.15s ease;
}

.task-bento-card:active {
  transform: scale(0.985);
}

.task-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 10px;
}

.task-time {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font: 600 11px var(--ds-font-mono);
  color: var(--ds-muted);
}

.task-title {
  margin: 0 0 10px;
  font-size: 14px;
  font-weight: 800;
  letter-spacing: -0.02em;
  color: var(--ds-ink);
}

.task-foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-top: 10px;
  border-top: 1px solid var(--ds-border-subtle);
}

.task-place {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 11px;
  color: var(--ds-muted);
}

.task-arrow {
  display: grid;
  width: 28px;
  height: 28px;
  place-items: center;
  border-radius: 50%;
  background: var(--ds-surface-subtle);
  color: var(--ds-muted);
}
</style>
