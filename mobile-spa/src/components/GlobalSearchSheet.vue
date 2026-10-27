<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { Building2, Car, ChevronRight, FileText, FolderOpen, Loader2, Receipt, Search, SearchX, ShieldAlert, Users, X } from '@lucide/vue'
import { auth } from '../services/auth.js'
import {
  SEARCH_MODULES,
  searchState,
  closeSearchSheet,
  setSearchCategory,
  setSearchQuery,
  rememberSearch,
  clearRecentSearches,
} from '../services/search.js'

const router = useRouter()
const sheet = ref(null)
const input = ref(null)
let previousOverflow = ''
let returnFocus = null

const MODULE_ICONS = { personnel: Users, invoices: Receipt, vehicles: Car, current_accounts: Building2, documents: FolderOpen, illegal: ShieldAlert }
const MODULE_THEMES = { personnel: 'blue', invoices: 'coral', vehicles: 'amber', current_accounts: 'emerald', documents: 'purple', illegal: 'coral' }
const TONES = { success: 'emerald', danger: 'coral', warning: 'amber', info: 'blue', muted: 'purple' }

const permittedModules = computed(() => SEARCH_MODULES.filter((m) => auth.state.permissions?.[m.key]))
const categories = computed(() => {
  if (!searchState.searched) return []
  return permittedModules.value.filter((m) => searchState.allowedModules.includes(m.key) && (searchState.counts[m.key] || 0) > 0)
})
const groups = computed(() => {
  const active = searchState.category
  return SEARCH_MODULES
    .filter((m) => searchState.allowedModules.includes(m.key) && auth.state.permissions?.[m.key])
    .filter((m) => active === 'all' || active === m.key)
    .map((m) => ({ ...m, items: Array.isArray(searchState.results[m.key]) ? searchState.results[m.key] : [] }))
    .filter((g) => g.items.length > 0)
})
const showEmpty = computed(() => searchState.searched && !searchState.loading && !searchState.error && groups.value.length === 0)

watch(() => searchState.open, async (open) => {
  if (open) {
    returnFocus = document.activeElement
    previousOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    await nextTick()
    input.value?.focus()
  } else {
    document.body.style.overflow = previousOverflow
    if (returnFocus?.isConnected) returnFocus.focus()
  }
})

onBeforeUnmount(() => {
  if (searchState.open) document.body.style.overflow = previousOverflow
})

function handleKeydown(event) {
  if (event.key === 'Escape') closeSearchSheet()
}

function openResult(item) {
  rememberSearch(searchState.query)
  closeSearchSheet()
  router.push(item.route)
}

function initialsOf(title) {
  return String(title || '').split(/\s+/).filter(Boolean).slice(0, 2).map((p) => p[0]).join('').toLocaleUpperCase('tr-TR')
}
</script>

<template>
  <Teleport to="body">
    <div v-if="searchState.open" class="clay-modal-overlay gs-layer" @click.self="closeSearchSheet" @keydown="handleKeydown">
      <section ref="sheet" class="clay-modal-sheet gs-sheet" role="dialog" aria-modal="true" aria-label="Genel arama">
        <div class="clay-sheet-handle"></div>
        <div class="clay-sheet-header">
          <h2 class="clay-sheet-title">Genel Arama</h2>
          <button type="button" class="clay-circle-btn" aria-label="Aramayı kapat" @click="closeSearchSheet"><X :size="18" /></button>
        </div>

        <label class="clay-search-bar gs-bar">
          <Search :size="18" />
          <input
            ref="input"
            type="search"
            inputmode="search"
            enterkeyhint="search"
            autocomplete="off"
            class="clay-search-input"
            placeholder="Personel, fatura, araç, cari, evrak ara…"
            aria-label="Genel arama"
            :value="searchState.query"
            @input="setSearchQuery($event.target.value)"
          />
          <button v-if="searchState.query" type="button" class="gs-clear" aria-label="Temizle" @click="setSearchQuery(''); input?.focus()"><X :size="14" /></button>
        </label>

        <div v-if="categories.length > 1" class="clay-filter-bar">
          <button type="button" class="clay-filter-pill" :class="{ 'is-active': searchState.category === 'all' }" @click="setSearchCategory('all')">
            Tümü <span>{{ searchState.counts.all }}</span>
          </button>
          <button
            v-for="cat in categories"
            :key="cat.key"
            type="button"
            class="clay-filter-pill"
            :class="{ 'is-active': searchState.category === cat.key }"
            @click="setSearchCategory(cat.key)"
          >
            {{ cat.label }} <span>{{ searchState.counts[cat.key] }}</span>
          </button>
        </div>

        <div class="gs-body" aria-live="polite">
          <div v-if="searchState.loading" class="clay-state-card gs-state">
            <Loader2 :size="22" class="clay-spin-icon" />
            <p>Aranıyor…</p>
          </div>

          <div v-else-if="searchState.error" class="clay-state-card gs-state">
            <p>{{ searchState.error }}</p>
          </div>

          <div v-else-if="showEmpty" class="clay-state-card gs-state">
            <SearchX :size="22" />
            <p>Sonuç bulunamadı.</p>
          </div>

          <template v-else-if="searchState.searched">
            <div v-for="group in groups" :key="group.key" class="nav-sheet-group">
              <div class="nav-sheet-group__title">{{ group.label }}</div>
              <div class="nav-sheet-list">
                <button v-for="item in group.items" :key="item.id" type="button" class="nav-sheet-item gs-item" @click="openResult(item)">
                  <span class="nav-sheet-item__icon gs-avatar">
                    <img v-if="item.avatar" :src="item.avatar" alt="" loading="lazy" @error="item.avatar = null" />
                    <template v-else>{{ initialsOf(item.title) }}</template>
                  </span>
                  <span class="nav-sheet-item__body">
                    <span class="nav-sheet-item__label">{{ item.title }}</span>
                    <span class="nav-sheet-item__subtitle">{{ item.subtitle }}<template v-if="item.extra"> • {{ item.extra }}</template></span>
                  </span>
                  <span v-if="item.badge" class="clay-badge" :class="`clay-badge--${TONES[item.tone] || 'blue'}`">{{ item.badge }}</span>
                  <ChevronRight :size="16" class="nav-sheet-item__arrow" />
                </button>
              </div>
            </div>
          </template>

          <div v-else class="gs-quick">
            <div class="gs-quick__title">Hızlı Modül Sayfaları</div>
            <div class="gs-quick__grid">
              <button v-for="m in permittedModules" :key="m.key" type="button" class="gs-tile" :class="`gs-tile--${MODULE_THEMES[m.key]}`" @click="closeSearchSheet(); router.push(m.to)">
                <span class="gs-tile__icon"><component :is="MODULE_ICONS[m.key] || FileText" :size="22" /></span>
                <span class="gs-tile__text">
                  <span class="gs-tile__label">{{ m.hint }}</span>
                  <span class="gs-tile__desc">{{ m.desc }}</span>
                </span>
              </button>
            </div>
            <template v-if="searchState.recent.length">
              <div class="gs-quick__title gs-quick__title--row">
                <span>Son Aramalar</span>
                <button type="button" class="gs-link" @click="clearRecentSearches">Temizle</button>
              </div>
              <div class="gs-chips">
                <button v-for="r in searchState.recent" :key="r" type="button" class="gs-chip" @click="setSearchQuery(r)"><Search :size="13" />{{ r }}</button>
              </div>
            </template>
            <p class="gs-quick__tip">Kutucuklardaki alanlara göre arama yapabilirsiniz (en az 2 harf).</p>
          </div>
        </div>
      </section>
    </div>
  </Teleport>
</template>

<style scoped>
.gs-sheet {
  display: flex;
  flex-direction: column;
  min-height: 60svh;
}

.gs-bar {
  margin-bottom: 12px;
}

.gs-body {
  flex: 1;
  overflow-y: auto;
  overscroll-behavior: contain;
}

.gs-state {
  text-align: center;
  padding: 28px 16px;
}

.gs-item {
  width: 100%;
  min-height: 56px;
  text-align: left;
}

.gs-avatar {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  font: 700 13px var(--ds-font-main);
}

.gs-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.gs-sheet {
  min-height: 72svh;
}

.gs-body {
  padding: 4px 2px 8px;
  margin: 0 -2px;
}

.gs-bar {
  border: 1.5px solid var(--ds-border);
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.gs-bar:focus-within {
  border-color: #0a84ff;
  box-shadow: 0 0 0 4px rgba(10, 132, 255, 0.12);
}

.gs-clear {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  flex: none;
  border: 0;
  border-radius: 50%;
  background: rgba(100, 116, 139, 0.14);
  color: var(--ds-muted);
  cursor: pointer;
}

.gs-quick__title {
  margin: 6px 4px 12px;
  font: 700 11px var(--ds-font-main);
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--ds-muted);
}

.gs-quick__title--row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 22px;
}

.gs-link {
  border: 0;
  background: none;
  padding: 4px;
  font: 700 12px var(--ds-font-main);
  color: #0a84ff;
  cursor: pointer;
}

.gs-quick__grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 10px;
}

.gs-tile {
  display: flex;
  align-items: center;
  gap: 14px;
  min-height: 64px;
  padding: 10px 14px 10px 10px;
  border-radius: 20px;
  border: 1px solid rgba(15, 23, 42, 0.08);
  background: var(--ds-surface);
  box-shadow: 0 4px 14px rgba(15, 23, 42, 0.07);
  color: var(--ds-ink);
  text-align: left;
  cursor: pointer;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.gs-tile:active {
  transform: scale(0.98);
  box-shadow: 0 2px 6px rgba(15, 23, 42, 0.08);
}

.gs-tile__icon {
  display: inline-flex;
  flex: none;
  align-items: center;
  justify-content: center;
  width: 44px;
  height: 44px;
  border-radius: 15px;
}

.gs-tile__text {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.gs-tile__label {
  font: 700 15px var(--ds-font-main);
}

.gs-tile__desc {
  font: 500 12px var(--ds-font-main);
  color: var(--ds-muted);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.gs-tile--blue .gs-tile__icon { background: rgba(56, 189, 248, 0.16); color: #0284c7; }
.gs-tile--coral .gs-tile__icon { background: rgba(251, 113, 133, 0.16); color: #e11d48; }
.gs-tile--amber .gs-tile__icon { background: rgba(245, 158, 11, 0.16); color: #d97706; }
.gs-tile--emerald .gs-tile__icon { background: rgba(16, 185, 129, 0.16); color: #059669; }
.gs-tile--purple .gs-tile__icon { background: rgba(168, 85, 247, 0.16); color: #7c3aed; }

.gs-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.gs-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  min-height: 40px;
  padding: 0 14px;
  border-radius: 999px;
  border: 1px solid var(--ds-border);
  background: var(--ds-surface);
  font: 600 13px var(--ds-font-main);
  color: var(--ds-ink);
  cursor: pointer;
}

.gs-quick__tip {
  margin: 18px 4px 0;
  font: 500 12px var(--ds-font-main);
  color: var(--ds-muted);
  text-align: center;
}
</style>
