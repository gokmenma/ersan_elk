<script setup>
import { computed, onMounted, ref } from 'vue'
import { Check, CircleAlert, Clock3, ListChecks, Users } from '@lucide/vue'
import TopBar from '../components/TopBar.vue'
import { auth } from '../services/auth.js'
import { gorevlerApi } from '../services/gorevler.js'

const activeTab = ref('today')
const loading = ref(false)
const errorMessage = ref('')
const busyId = ref('')
const listeler = ref([])

function bugun() {
  const d = new Date()
  const p = (n) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}`
}

const tasks = computed(() => {
  const today = bugun()
  return listeler.value.flatMap((liste) =>
    liste.gorevler.map((g) => {
      const overdue = !g.tamamlandi && g.tarih && g.tarih < today
      const isToday = g.tarih === today
      let status = 'Planlandı'
      let tone = 'neutral'
      if (g.tamamlandi) {
        status = 'Tamamlandı'
        tone = 'primary'
      } else if (overdue) {
        status = 'Gecikti'
        tone = 'danger'
      } else if (isToday) {
        status = 'Bugün'
        tone = 'primary'
      }
      return { ...g, listeAdi: liste.baslik, erisim: liste.erisim, overdue, isToday, status, tone }
    }),
  )
})

const todayTasks = computed(() => tasks.value.filter((t) => !t.tamamlandi && (t.overdue || t.isToday)))
const upcomingTasks = computed(() => tasks.value.filter((t) => !t.tamamlandi && !t.overdue && !t.isToday))
const doneTasks = computed(() => tasks.value.filter((t) => t.tamamlandi))

const visibleTasks = computed(() => {
  if (activeTab.value === 'completed') return doneTasks.value
  if (activeTab.value === 'upcoming') return upcomingTasks.value
  return todayTasks.value
})

const total = computed(() => tasks.value.length)
const donePercent = computed(() => (total.value ? Math.round((doneTasks.value.length / total.value) * 100) : 0))
const overduePercent = computed(() =>
  total.value ? Math.round((tasks.value.filter((t) => t.overdue).length / total.value) * 100) : 0,
)
const pendingPercent = computed(() => Math.max(0, 100 - donePercent.value - overduePercent.value))

function formatWhen(t) {
  if (!t.tarih) return t.saat || 'Tarihsiz'
  const [y, m, d] = t.tarih.split('-')
  return `${d}.${m}.${y}${t.saat ? ' ' + t.saat : ''}`
}

async function load() {
  loading.value = true
  errorMessage.value = ''
  try {
    const payload = await gorevlerApi.list()
    listeler.value = payload.data || []
  } catch (error) {
    errorMessage.value = error.message
  } finally {
    loading.value = false
  }
}

async function toggle(task) {
  if (busyId.value) return
  busyId.value = task.id
  errorMessage.value = ''
  try {
    const csrf = auth.csrfToken
    if (task.tamamlandi) await gorevlerApi.geriAl(task.id, csrf)
    else await gorevlerApi.tamamla(task.id, csrf)
    await load()
  } catch (error) {
    errorMessage.value = error.message
  } finally {
    busyId.value = ''
  }
}

onMounted(load)
</script>

<template>
  <section class="ds-screen">
    <TopBar eyebrow="OPERASYON" title="Görevler" subtitle="Görev listeleriniz ve bugünkü iş planı" :back="true" />

    <section class="bento-card bento-card--white">
      <header class="bento-head">
        <div class="bento-head-title">
          <h3>Görev Durumu</h3>
        </div>
        <span class="clay-pill-tag">%{{ donePercent }} Tamamlandı</span>
      </header>

      <div class="segmented-track">
        <span class="seg-pill seg-pill--blue" :style="{ width: donePercent + '%' }"></span>
        <span class="seg-pill seg-pill--amber" :style="{ width: pendingPercent + '%' }"></span>
        <span class="seg-pill seg-pill--coral" :style="{ width: overduePercent + '%' }"></span>
      </div>

      <div class="bento-metrics-grid">
        <div class="metric-cell">
          <strong>{{ total }} <small>görev</small></strong>
          <span>Toplam</span>
        </div>
        <div class="metric-cell">
          <strong class="text-emerald">{{ doneTasks.length }} <small>bitti</small></strong>
          <span>Tamamlanan</span>
        </div>
      </div>
    </section>

    <div class="clay-tabs-wrap">
      <button type="button" class="clay-tab-btn" :class="{ 'is-active': activeTab === 'today' }" @click="activeTab = 'today'">
        <span>Bugün</span>
        <span class="clay-tab-badge">{{ todayTasks.length }}</span>
      </button>
      <button type="button" class="clay-tab-btn" :class="{ 'is-active': activeTab === 'upcoming' }" @click="activeTab = 'upcoming'">
        <span>Yaklaşan</span>
        <span class="clay-tab-badge">{{ upcomingTasks.length }}</span>
      </button>
      <button type="button" class="clay-tab-btn" :class="{ 'is-active': activeTab === 'completed' }" @click="activeTab = 'completed'">
        <span>Tamamlanan</span>
        <span class="clay-tab-badge">{{ doneTasks.length }}</span>
      </button>
    </div>

    <p v-if="errorMessage" class="task-state">{{ errorMessage }}</p>
    <p v-else-if="loading && !listeler.length" class="task-state">Görevler yükleniyor…</p>
    <p v-else-if="!visibleTasks.length" class="task-state">Bu sekmede görev bulunmuyor.</p>

    <div class="task-cards-list">
      <article v-for="task in visibleTasks" :key="task.id" class="task-bento-card">
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
            <span>{{ formatWhen(task) }}</span>
          </span>
        </header>

        <h4 class="task-title" :class="{ 'is-done': task.tamamlandi }">{{ task.baslik }}</h4>

        <footer class="task-foot">
          <span class="task-place">
            <ListChecks :size="13" />
            <span>{{ task.listeAdi }}</span>
            <template v-if="task.atananlar.length">
              <Users :size="13" />
              <span class="task-who">{{ task.atananlar.join(', ') }}</span>
            </template>
          </span>
          <button
            type="button"
            class="task-state-btn"
            :class="{ 'is-done': task.tamamlandi }"
            :disabled="busyId === task.id || task.erisim === 'goruntule'"
            :aria-label="task.tamamlandi ? 'Tamamlamayı geri al' : 'Tamamla'"
            @click="toggle(task)"
          >
            <Check :size="18" />
          </button>
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

.task-state-btn {
  display: grid;
  width: 44px;
  height: 44px;
  flex-shrink: 0;
  place-items: center;
  border: 0;
  border-radius: 50%;
  background: var(--ds-surface-subtle);
  color: var(--ds-muted);
  cursor: pointer;
}

.task-state-btn.is-done {
  background: rgba(16, 185, 129, 0.12);
  color: #059669;
}

.task-state-btn:disabled {
  opacity: 0.5;
}

.task-who {
  font-size: 11px;
  color: var(--ds-muted);
}

.task-title.is-done {
  text-decoration: line-through;
  opacity: 0.6;
}

.task-state {
  padding: 24px 8px;
  text-align: center;
  font-size: 13px;
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
