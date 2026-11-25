<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { ChevronLeft, ChevronRight, House, Package, Pause, Play, ShoppingCart, Users, Warehouse, X } from '@lucide/vue'

const props = defineProps({ open: { type: Boolean, default: false } })
const emit = defineEmits(['close'])

const STEP_MS = 4600
const MOVE_DELAY = 300
const MOVE_MS = 1250

const reduceMotion = typeof window !== 'undefined' && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches

const NODES = {
  supply: { left: 10, top: 50, label: 'Satın alma', icon: ShoppingCart, tone: 'slate' },
  depot: { left: 30, top: 50, label: 'Depo', icon: Warehouse, tone: 'blue', counter: true },
  teamA: { left: 58, top: 22, label: 'Ekip', icon: Users, tone: 'dark', counter: true },
  teamB: { left: 58, top: 80, label: 'Diğer ekip', icon: Users, tone: 'green', counter: true },
  field: { left: 87, top: 22, label: 'Abone', icon: House, tone: 'amber', counter: true },
}

const START = { depot: 0, teamA: 0, teamB: 0, field: 0 }

const STEPS = [
  { who: 'manager', tag: 'Siz yaparsınız', title: 'Depoya Giriş', text: 'Satın alınan aparat, “Depoya Giriş” ile depo stoğuna eklenir. İrsaliye veya fatura numarasını not olarak yazabilirsiniz.', from: 'supply', to: 'depot', color: '#0284c7' },
  { who: 'manager', tag: 'Siz yaparsınız', title: 'Ekibe Ver', text: 'Depodan düşer, seçtiğiniz ekibin zimmetine geçer. Ekip kendi telefonunda elindeki adedi görür.', from: 'depot', to: 'teamA', color: '#d97706' },
  { who: 'team', tag: 'Ekip yapar · telefondan', title: 'Kesme', text: 'Ekip sayacı kesip aparatı takınca Kesme kaydı girer. Aparat ekibin zimmetinden düşer, abonede takılı görünür.', from: 'teamA', to: 'field', color: '#dc2626' },
  { who: 'team', tag: 'Açan ekip girer', title: 'Açma', text: 'Aboneyi hangi ekip açarsa Açma kaydını o girer ve aparat o ekibin zimmetine geçer.', from: 'field', to: 'teamB', color: '#059669' },
  { who: 'team', tag: 'Ekipler arası · onaylı', title: 'Ekipler Arası Transfer', text: 'Ekipler birbirine aparat gönderir; alan ekip onaylayınca zimmete geçer. Kimse elinde olmayanı gönderemez.', from: 'teamB', to: 'teamA', color: '#7c3aed' },
  { who: 'manager', tag: 'Siz yaparsınız', title: 'İade Al', text: 'Ekibin kullanmadığı aparatı teslim aldığınızda “İade Al” girersiniz. Ekibin zimmetinden düşer, depoya geri girer.', from: 'teamA', to: 'depot', color: '#0891b2' },
]

const index = ref(0)
const playing = ref(true)
const counts = ref({ ...START })
const flash = ref({})
const traveler = ref({ left: NODES.supply.left, top: NODES.supply.top, animate: false })
let timer = null

const step = computed(() => STEPS[index.value])
const activeNodes = computed(() => [step.value.from, step.value.to])

function stockBefore(n) {
  const c = { ...START }
  for (let i = 0; i < n; i++) {
    const s = STEPS[i]
    if (s.from in c) c[s.from] -= 1
    if (s.to in c) c[s.to] += 1
  }
  return c
}

function clear() {
  clearTimeout(timer)
  timer = null
}

function applyArrival(s) {
  const next = { ...counts.value }
  const change = {}
  if (s.from in next) {
    next[s.from] -= 1
    change[s.from] = 'down'
  }
  if (s.to in next) {
    next[s.to] += 1
    change[s.to] = 'up'
  }
  counts.value = next
  flash.value = change
}

async function go(n) {
  clear()
  index.value = (n + STEPS.length) % STEPS.length
  const s = STEPS[index.value]
  counts.value = stockBefore(index.value)
  flash.value = {}

  const from = NODES[s.from]
  const to = NODES[s.to]
  traveler.value = { left: from.left, top: from.top, animate: false }
  await nextTick()

  if (reduceMotion) {
    traveler.value = { left: to.left, top: to.top, animate: false }
    applyArrival(s)
    return
  }

  timer = setTimeout(() => {
    traveler.value = { left: to.left, top: to.top, animate: true }
    timer = setTimeout(() => {
      applyArrival(s)
      if (playing.value) timer = setTimeout(() => go(index.value + 1), STEP_MS - MOVE_DELAY - MOVE_MS)
    }, MOVE_MS)
  }, MOVE_DELAY)
}

function togglePlay() {
  playing.value = !playing.value && !reduceMotion
  go(playing.value ? index.value + 1 : index.value)
}

function manual(n) {
  playing.value = false
  go(n)
}

function close() {
  clear()
  emit('close')
}

watch(() => props.open, (value) => {
  if (value) {
    playing.value = !reduceMotion
    counts.value = { ...START }
    go(0)
  } else {
    clear()
  }
}, { immediate: true })

onBeforeUnmount(clear)

const stepStyle = computed(() => ({ '--an-color': step.value.color, '--an-ms': `${STEP_MS}ms` }))
</script>

<template>
  <Transition name="an">
    <div v-if="open" class="an-layer" @click.self="close">
      <div class="an-sheet" role="dialog" aria-modal="true" aria-labelledby="an-title">
        <span class="an-handle" aria-hidden="true"></span>
        <header class="an-head">
          <div>
            <h2 id="an-title">Aparat Nasıl Takip Edilir?</h2>
            <p>Aparatın depodan aboneye yolculuğu</p>
          </div>
          <button type="button" class="an-close" aria-label="Kapat" @click="close"><X :size="20" /></button>
        </header>

        <div class="an-card" :class="{ 'is-paused': !playing }" :style="stepStyle">
          <div class="an-stage" aria-hidden="true">
            <svg class="an-paths" viewBox="0 0 100 100" preserveAspectRatio="none">
              <line v-for="s in STEPS" :key="s.title" :x1="NODES[s.from].left" :y1="NODES[s.from].top" :x2="NODES[s.to].left" :y2="NODES[s.to].top" :class="{ active: s === step }" />
            </svg>
            <div v-for="(node, key) in NODES" :key="key" class="an-node" :class="[`an-node--${node.tone}`, { active: activeNodes.includes(key) }]" :style="{ left: node.left + '%', top: node.top + '%' }">
              <span class="an-node__icon">
                <component :is="node.icon" :size="21" />
                <b v-if="node.counter" class="an-count" :class="flash[key]" :key="`${key}-${counts[key]}`">{{ counts[key] }}</b>
              </span>
              <span class="an-node__label">{{ node.label }}</span>
            </div>
            <span class="an-pack" :class="{ move: traveler.animate }" :style="{ left: traveler.left + '%', top: traveler.top + '%' }"><Package :size="16" /></span>
          </div>

          <div class="an-copy" aria-live="polite">
            <span class="an-who" :class="`an-who--${step.who}`">{{ step.tag }}</span>
            <strong>{{ index + 1 }}. {{ step.title }}</strong>
            <p>{{ step.text }}</p>
          </div>

          <div class="an-controls">
            <button type="button" class="an-btn" aria-label="Önceki adım" @click="manual(index - 1)"><ChevronLeft :size="20" /></button>
            <button type="button" class="an-btn an-btn--main" :aria-label="playing ? 'Duraklat' : 'Oynat'" @click="togglePlay">
              <Pause v-if="playing" :size="20" />
              <Play v-else :size="20" />
            </button>
            <button type="button" class="an-btn" aria-label="Sonraki adım" @click="manual(index + 1)"><ChevronRight :size="20" /></button>
            <div class="an-steps" role="tablist" aria-label="Adımlar">
              <button v-for="(s, i) in STEPS" :key="s.title" type="button" role="tab" :aria-selected="i === index" :aria-label="`${i + 1}. adım: ${s.title}`" :class="{ done: i < index, current: i === index }" @click="manual(i)">
                <i :key="`${i}-${index}-${playing}`"></i>
              </button>
            </div>
          </div>
        </div>

        <div class="an-sum">
          <div><span class="an-who an-who--manager">Siz</span><p>Depoya giriş, ekibe aparat verme ve iade alma</p></div>
          <div><span class="an-who an-who--team">Ekip</span><p>Kesme, açma ve ekipler arası transfer kayıtları</p></div>
        </div>

        <button type="button" class="an-done" @click="close">Anladım</button>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.an-layer { position: fixed; inset: 0; z-index: 1100; display: flex; align-items: flex-end; background: var(--ds-overlay); font-family: var(--ds-font-main); }
.an-sheet { display: flex; flex-direction: column; width: 100%; max-height: 94dvh; overflow-y: auto; padding: 8px 16px calc(16px + var(--ds-safe-bottom)); border-radius: 28px 28px 0 0; background: var(--ds-canvas); box-shadow: var(--ds-shadow-float); }
.an-handle { align-self: center; width: 40px; height: 4px; margin: 4px 0 10px; border-radius: 999px; background: rgba(15, 23, 42, .18); flex: 0 0 auto; }
.an-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; margin-bottom: 12px; }
.an-head h2 { margin: 0; font-size: 18px; font-weight: 800; color: var(--ds-ink); }
.an-head p { margin: 2px 0 0; font-size: 12px; color: var(--ds-muted); }
.an-close { display: grid; place-items: center; flex: 0 0 44px; width: 44px; height: 44px; border: 0; border-radius: 14px; background: var(--ds-surface-subtle); color: var(--ds-ink); }

.an-card { --an-color: #475569; overflow: hidden; border-radius: var(--ds-radius-card); background: var(--ds-surface); border: 1px solid var(--ds-border); box-shadow: var(--ds-shadow-bento); }
.an-stage { position: relative; height: 230px; margin: 8px 6px 0; }
.an-paths { position: absolute; inset: 0; width: 100%; height: 100%; overflow: visible; }
.an-paths line { stroke: #cbd5e1; stroke-width: 2; stroke-dasharray: 4 5; vector-effect: non-scaling-stroke; }
.an-paths line.active { stroke: var(--an-color); stroke-width: 3; animation: an-flow .6s linear infinite; }
@keyframes an-flow { to { stroke-dashoffset: -18; } }

.an-node { position: absolute; z-index: 1; display: flex; flex-direction: column; align-items: center; gap: 4px; width: 72px; transform: translate(-50%, -50%); text-align: center; }
.an-node__icon { position: relative; display: grid; place-items: center; width: 44px; height: 44px; border-radius: 14px; background: var(--ds-surface); border: 1px solid var(--ds-border-subtle); box-shadow: var(--ds-shadow-tile); transition: transform .25s ease, box-shadow .25s ease, border-color .25s ease; }
.an-node--slate .an-node__icon { color: #64748b; }
.an-node--blue .an-node__icon { color: #0284c7; }
.an-node--dark .an-node__icon { background: var(--ds-primary); border-color: var(--ds-primary); color: #fff; }
.an-node--green .an-node__icon { color: #059669; }
.an-node--amber .an-node__icon { color: #d97706; }
.an-node.active .an-node__icon { transform: scale(1.1); border-color: var(--an-color); box-shadow: 0 0 0 4px color-mix(in srgb, var(--an-color) 22%, transparent); }
.an-node__label { font-size: 11.5px; font-weight: 800; line-height: 1.1; color: var(--ds-ink); }
.an-count { position: absolute; top: -8px; right: -8px; min-width: 21px; height: 21px; padding: 0 5px; border-radius: 999px; background: var(--ds-primary); color: #fff; font-size: 11px; font-weight: 800; line-height: 21px; text-align: center; }
.an-count.up { background: #059669; animation: an-pop .5s ease; }
.an-count.down { background: #d97706; animation: an-pop .5s ease; }
@keyframes an-pop { 40% { transform: scale(1.4); } }

.an-pack { position: absolute; z-index: 2; display: grid; place-items: center; width: 28px; height: 28px; margin: -14px 0 0 -14px; border-radius: 9px; background: var(--an-color); color: #fff; box-shadow: 0 6px 14px rgba(15, 23, 42, .28); }
.an-pack.move { transition: left 1.25s cubic-bezier(.45, .05, .25, 1), top 1.25s cubic-bezier(.45, .05, .25, 1); }

.an-copy { display: flex; flex-direction: column; align-items: center; gap: 4px; min-height: 122px; padding: 6px 16px; text-align: center; }
.an-copy strong { font-size: 16px; font-weight: 800; color: var(--ds-ink); }
.an-copy p { margin: 0; font-size: 13px; line-height: 1.45; color: var(--ds-muted); }
.an-who { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 800; }
.an-who--manager { background: #e0f2fe; color: #0369a1; }
.an-who--team { background: #d1fae5; color: #047857; }

.an-controls { display: flex; align-items: center; gap: 6px; padding: 10px 12px 12px; border-top: 1px solid var(--ds-border-subtle); }
.an-btn { display: grid; place-items: center; flex: 0 0 44px; width: 44px; height: 44px; border: 0; border-radius: 14px; background: var(--ds-surface-subtle); color: var(--ds-ink); }
.an-btn--main { background: var(--ds-primary); color: #fff; }
.an-steps { display: flex; flex: 1; gap: 4px; margin-left: 4px; }
.an-steps button { position: relative; flex: 1; height: 28px; padding: 0; border: 0; background: transparent; }
.an-steps button::before { content: ""; position: absolute; left: 0; right: 0; top: 11px; height: 6px; border-radius: 999px; background: #e2e8f0; }
.an-steps button.done::before { background: #94a3b8; }
.an-steps i { position: absolute; left: 0; top: 11px; width: 0; height: 6px; border-radius: 999px; background: var(--an-color); }
.an-steps button.current i { animation: an-fill var(--an-ms) linear forwards; }
.is-paused .an-steps button.current i { animation: none; width: 100%; }
@keyframes an-fill { to { width: 100%; } }

.an-sum { display: grid; gap: 8px; margin: 14px 2px; }
.an-sum > div { display: flex; align-items: flex-start; gap: 8px; }
.an-sum p { margin: 0; font-size: 12.5px; color: var(--ds-ink); }
.an-done { min-height: 48px; border: 0; border-radius: 16px; background: var(--ds-primary); color: #fff; font: 800 15px var(--ds-font-main); }

.an-enter-active, .an-leave-active { transition: opacity .22s ease; }
.an-enter-active .an-sheet, .an-leave-active .an-sheet { transition: transform .26s cubic-bezier(.2, .8, .2, 1); }
.an-enter-from, .an-leave-to { opacity: 0; }
.an-enter-from .an-sheet, .an-leave-to .an-sheet { transform: translateY(100%); }
@media (prefers-reduced-motion: reduce) {
  .an-enter-active, .an-leave-active, .an-enter-active .an-sheet, .an-leave-active .an-sheet, .an-node__icon, .an-pack.move { transition: none; }
  .an-paths line.active, .an-count.up, .an-count.down { animation: none; }
}
</style>
