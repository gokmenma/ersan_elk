<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  AlertTriangle,
  ArrowLeft,
  Calendar,
  Check,
  CheckCircle2,
  ChevronRight,
  Clock,
  Download,
  FileSpreadsheet,
  FileText,
  Filter,
  Layers,
  Pencil,
  Percent,
  Plus,
  RefreshCw,
  Save,
  Search,
  SlidersHorizontal,
  Sparkles,
  Trash2,
  TrendingUp,
  Wallet,
  X,
} from '@lucide/vue'
import TopBar from '../components/TopBar.vue'
import { sozlesmeApi } from '../services/sozlesme-hakedis.js'
import { auth } from '../services/auth.js'

const route = useRoute()
const router = useRouter()

const sozlesmeId = computed(() => route.params.id)
const hakedisId = computed(() => route.params.hakedisId)

const loading = ref(true)
const error = ref('')
const toastMessage = ref('')
let toastTimer = null

function showToast(msg) {
  toastMessage.value = msg
  if (toastTimer) clearTimeout(toastTimer)
  toastTimer = setTimeout(() => {
    toastMessage.value = ''
  }, 3000)
}

const hakedisData = ref(null)
const activeTab = ref('kalemler') // 'kalemler' | 'parametreler'
const itemSearch = ref('')

// Parametreler Formu
const paramForm = ref({
  a1_katsayisi: 0.28,
  b1_katsayisi: 0.22,
  b2_katsayisi: 0.25,
  c_katsayisi: 0.25,
  asgari_ucret_temel: '',
  asgari_ucret_guncel: '',
  motorin_temel: '',
  motorin_guncel: '',
  ufe_genel_temel: '',
  ufe_genel_guncel: '',
  makine_ekipman_temel: '',
  makine_ekipman_guncel: '',
  kdv_orani: 20.0,
  tevkifat_orani: '4/10',
  asgari_farki_dahil_edilsin: false,
})
const paramSaving = ref(false)
const fetchingWebEndeks = ref(false)

// Miktar Düzenleme Sheet
const isMiktarModalOpen = ref(false)
const editingItem = ref(null)
const editMiktarVal = ref(0)
const editOncekiVal = ref(0)
const miktarSaving = ref(false)

async function loadHakedis() {
  loading.value = true
  error.value = ''

  try {
    const res = await sozlesmeApi.getHakedis(hakedisId.value)
    if (res.success) {
      hakedisData.value = res
      if (res.hakedis) {
        paramForm.value = {
          a1_katsayisi: res.hakedis.a1_katsayisi,
          b1_katsayisi: res.hakedis.b1_katsayisi,
          b2_katsayisi: res.hakedis.b2_katsayisi,
          c_katsayisi: res.hakedis.c_katsayisi,
          asgari_ucret_temel: res.hakedis.asgari_ucret_temel || '',
          asgari_ucret_guncel: res.hakedis.asgari_ucret_guncel || '',
          motorin_temel: res.hakedis.motorin_temel || '',
          motorin_guncel: res.hakedis.motorin_guncel || '',
          ufe_genel_temel: res.hakedis.ufe_genel_temel || '',
          ufe_genel_guncel: res.hakedis.ufe_genel_guncel || '',
          makine_ekipman_temel: res.hakedis.makine_ekipman_temel || '',
          makine_ekipman_guncel: res.hakedis.makine_ekipman_guncel || '',
          kdv_orani: res.hakedis.kdv_orani || 20.0,
          tevkifat_orani: res.hakedis.tevkifat_orani || '4/10',
          asgari_farki_dahil_edilsin: Boolean(res.hakedis.asgari_farki_dahil_edilsin),
        }
      }
    } else {
      error.value = res.message || 'Hakediş verisi yüklenemedi.'
    }
  } catch (err) {
    error.value = err.message || 'Bağlantı hatası oluştu.'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadHakedis()
})

const filteredItems = computed(() => {
  const list = hakedisData.value?.items || []
  const q = itemSearch.value.trim().toLowerCase()
  if (!q) return list
  return list.filter(k =>
    (k.poz_no && k.poz_no.toLowerCase().includes(q)) ||
    (k.kalem_adi && k.kalem_adi.toLowerCase().includes(q))
  )
})

function openEditMiktar(item) {
  editingItem.value = item
  editMiktarVal.value = item.bu_ay_miktar || 0
  editOncekiVal.value = item.onceki_miktar || 0
  isMiktarModalOpen.value = true
}

async function submitMiktar() {
  if (!editingItem.value) return
  miktarSaving.value = true

  try {
    const payload = {
      hakedis_id: hakedisId.value,
      kalem_id: editingItem.value.id,
      bu_ay_miktar: editMiktarVal.value,
      onceki_miktar: editOncekiVal.value,
    }
    const res = await sozlesmeApi.updateKalemMiktar(payload, auth.state.csrfToken)
    if (res.success) {
      isMiktarModalOpen.value = false
      showToast('İmalat miktarı güncellendi.')
      await loadHakedis()
    } else {
      alert(res.message || 'Güncelleme başarısız.')
    }
  } catch (err) {
    alert(err.message || 'Hata oluştu.')
  } finally {
    miktarSaving.value = false
  }
}

async function saveParameters() {
  paramSaving.value = true
  try {
    const payload = {
      hakedis_id: hakedisId.value,
      ...paramForm.value,
    }
    const res = await sozlesmeApi.updateHakedisParametreler(payload, auth.state.csrfToken)
    if (res.success) {
      showToast('Parametreler kaydedildi ve fiyat farkı güncellendi.')
      await loadHakedis()
    } else {
      alert(res.message || 'Kaydetme başarısız.')
    }
  } catch (err) {
    alert(err.message || 'Hata oluştu.')
  } finally {
    paramSaving.value = false
  }
}

async function fetchGuncelEndeksler() {
  if (!hakedisData.value?.hakedis) return
  fetchingWebEndeks.value = true

  try {
    const ay = hakedisData.value.hakedis.hakedis_tarihi_ay
    const yil = hakedisData.value.hakedis.hakedis_tarihi_yil
    const res = await sozlesmeApi.fetchEndeks({ ay, yil })
    if (res.success && res.data) {
      if (res.data.motorin_guncel !== null) paramForm.value.motorin_guncel = res.data.motorin_guncel
      if (res.data.asgari_ucret_guncel !== null) paramForm.value.asgari_ucret_guncel = res.data.asgari_ucret_guncel
      if (res.data.ufe_genel_guncel !== null) paramForm.value.ufe_genel_guncel = res.data.ufe_genel_guncel
      if (res.data.makine_ekipman_guncel !== null) paramForm.value.makine_ekipman_guncel = res.data.makine_ekipman_guncel

      showToast(res.data.message || 'Endeksler TÜİK ve EPDK servislerinden çekildi.')
    } else {
      alert(res.message || 'Endeksler çekilemedi.')
    }
  } catch (err) {
    alert(err.message || 'Bağlantı hatası.')
  } finally {
    fetchingWebEndeks.value = false
  }
}

function exportExcel() {
  if (!hakedisData.value?.hakedis?.raw_id) return
  const rawId = hakedisData.value.hakedis.raw_id
  window.open(`/views/hakedisler/export-excel.php?id=${encodeURIComponent(rawId)}`, '_blank')
}
</script>

<template>
  <div class="ds-screen hakedis-detail-screen">
    <!-- Bildirim Toast'u -->
    <div v-if="toastMessage" class="ds-toast">
      <Check :size="16" />
      <span>{{ toastMessage }}</span>
    </div>

    <!-- Üst TopBar -->
    <TopBar
      eyebrow="HAKEDİŞ DETAYI"
      :title="`Hakediş #${hakedisData?.hakedis?.hakedis_no || ''} - ${hakedisData?.hakedis?.donem_adi || ''}`"
      :subtitle="hakedisData?.hakedis?.idare_adi || 'İmalat ve Fiyat Farkı'"
      :back="true"
    >
      <template #actions>
        <button
          type="button"
          class="clay-circle-btn"
          title="Excel İndir"
          aria-label="Excel Çıktısı Al"
          @click="exportExcel"
        >
          <Download :size="18" />
        </button>
      </template>
    </TopBar>

    <!-- Yükleniyor Durumu -->
    <div v-if="loading" class="ds-loading">
      <i v-for="n in 3" :key="n" style="height: 120px; border-radius: var(--ds-radius-card);"></i>
    </div>

    <!-- Hata Durumu -->
    <div v-else-if="error" class="ds-state error-state">
      <AlertTriangle :size="28" />
      <strong>Yüklenemedi</strong>
      <p>{{ error }}</p>
      <button type="button" class="btn-retry" @click="loadHakedis">
        <RefreshCw :size="16" /> Tekrar Dene
      </button>
    </div>

    <!-- Ana Hakediş İçeriği -->
    <template v-else-if="hakedisData && hakedisData.hakedis">
      <!-- 1. Finansal Özet Bento Kartı -->
      <section class="hakedis-hero-bento">
        <div class="h-hero-header">
          <div class="h-hero-title-group">
            <span class="h-hero-no">Hakediş #{{ hakedisData.hakedis.hakedis_no }}</span>
            <span class="h-hero-period">{{ hakedisData.hakedis.donem_adi }}</span>
          </div>
          <span class="h-hero-badge" :class="`badge-durum--${hakedisData.hakedis.durum}`">
            {{ hakedisData.hakedis.durum }}
          </span>
        </div>

        <div class="h-hero-kpi-grid">
          <!-- Bu Ayki İmalat -->
          <div class="h-kpi-cell">
            <span class="kpi-cell-label">BU AYKİ İMALAT</span>
            <span class="kpi-cell-val text-primary-ink">{{ hakedisData.totals.imalat_donem_formatted }}</span>
            <span class="kpi-cell-sub">Kümülatif: {{ hakedisData.totals.imalat_kumulatif_formatted }}</span>
          </div>

          <!-- Fiyat Farkı -->
          <div class="h-kpi-cell">
            <span class="kpi-cell-label">FİYAT FARKI</span>
            <span class="kpi-cell-val text-emerald">{{ hakedisData.totals.fiyat_farki_formatted }}</span>
            <span class="kpi-cell-sub">Dahil: {{ hakedisData.totals.fiyat_farki_dahil_formatted }}</span>
          </div>
        </div>

        <!-- KDV Dahil Toplam ve Gerçekleşme Barı -->
        <div class="h-kpi-highlight-bar">
          <div class="highlight-main">
            <div class="hl-title-group">
              <span class="hl-label">BU AYKİ KDV DAHİL TOPLAM ÖDEME</span>
              <span v-if="hakedisData.totals.kumulatif_kdv_dahil_formatted" class="hl-sub-kumulatif">
                Kümülatif KDV Dahil: {{ hakedisData.totals.kumulatif_kdv_dahil_formatted }}
              </span>
            </div>
            <span class="hl-val">{{ hakedisData.totals.donem_kdv_dahil_formatted || hakedisData.totals.kdv_dahil_toplam_formatted }}</span>
          </div>
          <div class="highlight-progress">
            <span class="hl-perc">Genel Gerçekleşme: %{{ hakedisData.totals.genel_gerceklesme_yuzdesi }}</span>
            <div class="segmented-track">
              <div
                class="seg-pill bg-emerald"
                :style="{ width: `${Math.min(100, hakedisData.totals.genel_gerceklesme_yuzdesi)}%` }"
              ></div>
            </div>
          </div>
        </div>
      </section>

      <!-- 2. Segmented Navigasyon Tabları -->
      <nav class="clay-tabs-wrap">
        <button
          type="button"
          class="clay-tab-btn"
          :class="{ 'is-active': activeTab === 'kalemler' }"
          @click="activeTab = 'kalemler'"
        >
          <Layers :size="15" />
          <span>İmalat Kalemleri</span>
          <b class="tab-count-pill">{{ hakedisData.items?.length || 0 }}</b>
        </button>

        <button
          type="button"
          class="clay-tab-btn"
          :class="{ 'is-active': activeTab === 'parametreler' }"
          @click="activeTab = 'parametreler'"
        >
          <SlidersHorizontal :size="15" />
          <span>Endeks & Katsayılar</span>
        </button>
      </nav>

      <!-- TAB 1: İMALAT KALEMLERİ -->
      <section v-if="activeTab === 'kalemler'" class="tab-content-block">
        <div class="kalem-search-row">
          <div class="clay-search-wrap">
            <Search :size="16" class="search-icon" />
            <input
              v-model="itemSearch"
              type="text"
              class="clay-search-input"
              placeholder="Poz no veya iş kalemi ara..."
            />
            <button v-if="itemSearch" type="button" class="search-clear-btn" @click="itemSearch = ''">
              <X :size="14" />
            </button>
          </div>
        </div>

        <div v-if="!filteredItems.length" class="ds-state">
          <Layers :size="32" />
          <strong>Kalem Bulunamadı</strong>
          <p>Arama kriterinize uygun imalat kalemi eşleşmedi.</p>
        </div>

        <div v-else class="kalem-cards-stack">
          <div
            v-for="item in filteredItems"
            :key="item.id"
            class="bento-kalem-item-card"
            @click="openEditMiktar(item)"
          >
            <div class="k-item-header">
              <div class="k-item-poz-box">
                <span class="k-item-poz">{{ item.poz_no }}</span>
                <span class="k-item-unit">{{ item.birim }}</span>
              </div>
              <div class="k-item-tutar">
                <span class="tutar-label">Bu Ayki Tutar</span>
                <span class="tutar-val text-emerald">{{ item.bu_ay_tutar_formatted }}</span>
              </div>
            </div>

            <h4 class="k-item-title">{{ item.kalem_adi }}</h4>

            <div class="k-item-metrics-grid">
              <div class="k-m-cell">
                <span class="cell-l">Sözleşme Miktarı</span>
                <span class="cell-v">{{ item.sozlesme_miktari }}</span>
              </div>
              <div class="k-m-cell">
                <span class="cell-l">Önceki Toplam</span>
                <span class="cell-v">{{ item.onceki_miktar }}</span>
              </div>
              <div class="k-m-cell highlight-cell">
                <span class="cell-l">Bu Ayki İmalat</span>
                <span class="cell-v text-blue-ink">{{ item.bu_ay_miktar }}</span>
              </div>
              <div class="k-m-cell">
                <span class="cell-l">Toplam Miktar</span>
                <span class="cell-v">{{ item.toplam_miktar }}</span>
              </div>
            </div>

            <div class="k-item-progress-row">
              <div class="k-prog-info">
                <span>Gerçekleşme Oranı:</span>
                <b>%{{ item.gerceklesme_yuzdesi }}</b>
              </div>
              <div class="k-edit-pill">
                <Pencil :size="12" />
                <span>Miktar Düzenle</span>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- TAB 2: ENDEKSLER VE PARAMETRELER -->
      <section v-else-if="activeTab === 'parametreler'" class="tab-content-block">
        <form class="parametre-form-container" @submit.prevent="saveParameters">
          <div class="param-top-action-bar">
            <div>
              <h3 class="param-sec-title">Fiyat Farkı Katsayıları & Endeksler</h3>
              <p class="param-sec-sub">Resmî Pn formülü için geçerli çarpanlar</p>
            </div>
            <button
              type="button"
              class="btn-fetch-web"
              :disabled="fetchingWebEndeks"
              @click="fetchGuncelEndeksler"
            >
              <Sparkles :size="14" />
              <span v-if="fetchingWebEndeks">Çekiliyor...</span>
              <span v-else>Web'den Çek</span>
            </button>
          </div>

          <div class="param-group-card">
            <h4 class="group-title text-blue-ink">P1 Katsayıları</h4>
            <div class="param-grid-4">
              <div class="param-field">
                <label>a1 (İşçilik)</label>
                <input v-model="paramForm.a1_katsayisi" type="number" step="0.000001" />
              </div>
              <div class="param-field">
                <label>b1 (Motorin)</label>
                <input v-model="paramForm.b1_katsayisi" type="number" step="0.000001" />
              </div>
              <div class="param-field">
                <label>b2 (Yİ-ÜFE)</label>
                <input v-model="paramForm.b2_katsayisi" type="number" step="0.000001" />
              </div>
              <div class="param-field">
                <label>c (Makine)</label>
                <input v-model="paramForm.c_katsayisi" type="number" step="0.000001" />
              </div>
            </div>

            <div class="switch-row">
              <input
                id="toggleAsgari"
                v-model="paramForm.asgari_farki_dahil_edilsin"
                type="checkbox"
                class="ds-switch-input"
              />
              <label for="toggleAsgari" class="switch-label">
                İşçilik Endeks Farkını Hesaba (Pn) Dahil Et
              </label>
            </div>
          </div>

          <div class="param-group-card">
            <h4 class="group-title text-muted">Temel Endeksler (Io, Mo, ÜFEo, Eo)</h4>
            <div class="param-grid-2">
              <div class="param-field">
                <label>Asgari Ücret Temel (Io)</label>
                <input v-model="paramForm.asgari_ucret_temel" type="number" step="any" placeholder="0.00" />
              </div>
              <div class="param-field">
                <label>Motorin Temel (Mo)</label>
                <input v-model="paramForm.motorin_temel" type="number" step="any" placeholder="0.00" />
              </div>
              <div class="param-field">
                <label>Yİ-ÜFE Temel (ÜFEo)</label>
                <input v-model="paramForm.ufe_genel_temel" type="number" step="any" placeholder="0.00" />
              </div>
              <div class="param-field">
                <label>Makine Temel (Eo)</label>
                <input v-model="paramForm.makine_ekipman_temel" type="number" step="any" placeholder="0.00" />
              </div>
            </div>
          </div>

          <div class="param-group-card border-warning">
            <h4 class="group-title text-amber-ink">Güncel (Hakediş Ayı) Endeksleri (In, Mn, ÜFEn, En)</h4>
            <div class="param-grid-2">
              <div class="param-field">
                <label>Asgari Ücret Güncel (In)</label>
                <input v-model="paramForm.asgari_ucret_guncel" type="number" step="any" placeholder="0.00" />
              </div>
              <div class="param-field">
                <label>Motorin Güncel (Mn)</label>
                <input v-model="paramForm.motorin_guncel" type="number" step="any" placeholder="0.00" />
              </div>
              <div class="param-field">
                <label>Yİ-ÜFE Güncel (ÜFEn)</label>
                <input v-model="paramForm.ufe_genel_guncel" type="number" step="any" placeholder="0.00" />
              </div>
              <div class="param-field">
                <label>Makine Güncel (En)</label>
                <input v-model="paramForm.makine_ekipman_guncel" type="number" step="any" placeholder="0.00" />
              </div>
            </div>
          </div>

          <div class="param-group-card">
            <h4 class="group-title text-danger-ink">Vergi ve Kesinti Oranları</h4>
            <div class="param-grid-2">
              <div class="param-field">
                <label>KDV Oranı (%)</label>
                <input v-model="paramForm.kdv_orani" type="number" step="0.01" placeholder="20.00" />
              </div>
              <div class="param-field">
                <label>Tevkifat Oranı</label>
                <input v-model="paramForm.tevkifat_orani" type="text" placeholder="4/10" />
              </div>
            </div>
          </div>

          <button type="submit" class="btn-save-params" :disabled="paramSaving">
            <Save :size="18" />
            <span v-if="paramSaving">Kaydediliyor...</span>
            <span v-else>Parametreleri Kaydet</span>
          </button>
        </form>
      </section>
    </template>

    <!-- Miktar Düzenleme Bottom Sheet -->
    <Transition name="ds-modal">
      <div v-if="isMiktarModalOpen" class="ds-modal-layer" @click.self="isMiktarModalOpen = false">
        <div class="ds-bottom-sheet" role="dialog" aria-modal="true">
          <div class="sheet-header">
            <div class="sheet-title">
              <div class="sheet-icon-box bg-blue-soft text-blue">
                <Pencil :size="18" />
              </div>
              <div>
                <h3>İmalat Miktarı Girişi</h3>
                <small>{{ editingItem?.poz_no }} - {{ editingItem?.kalem_adi }}</small>
              </div>
            </div>
            <button type="button" class="sheet-close-btn" @click="isMiktarModalOpen = false">
              <X :size="18" />
            </button>
          </div>

          <form class="sheet-form" @submit.prevent="submitMiktar">
            <div class="quick-qty-display">
              <div class="q-cell">
                <span>Ölçü Birimi</span>
                <b>{{ editingItem?.birim }}</b>
              </div>
              <div class="q-cell">
                <span>Birim Fiyat</span>
                <b>{{ editingItem?.birim_fiyat_formatted }}</b>
              </div>
              <div class="q-cell">
                <span>Sözleşme Miktarı</span>
                <b>{{ editingItem?.sozlesme_miktari }}</b>
              </div>
            </div>

            <div class="form-grid">
              <div class="form-field">
                <label>Önceki Toplam Miktar</label>
                <input v-model="editOncekiVal" type="number" step="any" placeholder="0" />
              </div>
              <div class="form-field">
                <label>Bu Ayki Miktar <span class="req">*</span></label>
                <input v-model="editMiktarVal" type="number" step="any" required autofocus placeholder="0" />
              </div>
            </div>

            <div class="calc-preview-box">
              <div class="preview-item">
                <span class="p-lbl">Toplam Kümülatif:</span>
                <b class="p-val">{{ Number(editOncekiVal || 0) + Number(editMiktarVal || 0) }} {{ editingItem?.birim }}</b>
              </div>
              <div class="preview-item item-tutar">
                <span class="p-lbl">Tahmini Tutar:</span>
                <b class="p-val text-emerald">{{ ((Number(editMiktarVal || 0) * Number(editingItem?.birim_fiyat || 0))).toLocaleString('tr-TR', { minimumFractionDigits: 2 }) }} ₺</b>
              </div>
            </div>

            <div class="sheet-actions">
              <button type="button" class="btn-cancel" @click="isMiktarModalOpen = false">Vazgeç</button>
              <button type="submit" class="btn-submit" :disabled="miktarSaving">
                <span v-if="miktarSaving">Kaydediliyor...</span>
                <span v-else>Miktarı Kaydet</span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.hakedis-detail-screen {
  padding-bottom: calc(var(--ds-nav-clearance) + 20px);
}

.ds-toast {
  position: fixed;
  top: calc(var(--ds-safe-top) + 16px);
  left: 50%;
  transform: translateX(-50%);
  background: #0f172a;
  color: #fff;
  padding: 10px 18px;
  border-radius: var(--ds-radius-pill);
  font-size: 13px;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 8px;
  box-shadow: var(--ds-shadow-float);
  z-index: 2000;
  animation: fadeInDown 0.2s ease;
}

@keyframes fadeInDown {
  from { opacity: 0; transform: translate(-50%, -10px); }
  to { opacity: 1; transform: translate(-50%, 0); }
}

/* Finansal Özet Hero Bento */
.hakedis-hero-bento {
  background: var(--ds-surface);
  border-radius: var(--ds-radius-card);
  padding: 18px 20px;
  border: 1px solid var(--ds-border);
  box-shadow: var(--ds-shadow-bento);
  margin-bottom: 16px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.h-hero-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.h-hero-title-group {
  display: flex;
  align-items: baseline;
  gap: 8px;
}

.h-hero-no {
  font-size: 18px;
  font-weight: 800;
  color: var(--ds-ink);
}

.h-hero-period {
  font-size: 14px;
  font-weight: 600;
  color: var(--ds-muted);
}

.h-hero-badge {
  padding: 4px 10px;
  border-radius: var(--ds-radius-pill);
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
}

.badge-durum--onaylandi { background: var(--ds-blue-soft); color: var(--ds-blue-ink); }
.badge-durum--tamamlandi { background: var(--ds-emerald-soft); color: var(--ds-emerald-ink); }
.badge-durum--hazirlandi { background: var(--ds-amber-soft); color: var(--ds-amber-ink); }
.badge-durum--taslak { background: var(--ds-surface-subtle); color: var(--ds-muted); }

.h-hero-kpi-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
}

.h-kpi-cell {
  background: var(--ds-surface-subtle);
  border-radius: var(--ds-radius-sm);
  padding: 10px 14px;
  display: flex;
  flex-direction: column;
}

.kpi-cell-label {
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.5px;
  color: var(--ds-muted);
}

.kpi-cell-val {
  font-size: 17px;
  font-weight: 800;
  margin-top: 2px;
}

.kpi-cell-sub {
  font-size: 11px;
  color: var(--ds-muted);
  margin-top: 2px;
}

.h-kpi-highlight-bar {
  background: #191d24;
  color: #fff;
  border-radius: var(--ds-radius-tile);
  padding: 14px 16px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.highlight-main {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.hl-title-group {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.hl-label {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.5px;
  color: #94a3b8;
}

.hl-sub-kumulatif {
  font-size: 11px;
  font-weight: 500;
  color: #64748b;
}

.hl-val {
  font-size: 20px;
  font-weight: 800;
  color: #38bdf8;
  white-space: nowrap;
}

.highlight-progress {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.hl-perc {
  font-size: 11px;
  color: #cbd5e1;
}

.segmented-track {
  height: 6px;
  background: rgba(255, 255, 255, 0.15);
  border-radius: var(--ds-radius-pill);
  overflow: hidden;
}

.seg-pill {
  height: 100%;
  border-radius: var(--ds-radius-pill);
  transition: width 0.3s ease;
}

/* Clay Tablar */
.clay-tabs-wrap {
  display: flex;
  gap: 6px;
  background: var(--ds-surface);
  border-radius: var(--ds-radius-pill);
  padding: 4px;
  box-shadow: var(--ds-shadow-pill);
  border: 1px solid var(--ds-border-subtle);
  margin-bottom: 16px;
}

.clay-tab-btn {
  flex: 1;
  height: 38px;
  border-radius: var(--ds-radius-pill);
  border: none;
  background: transparent;
  color: var(--ds-muted);
  font-size: 13px;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  cursor: pointer;
  transition: all 0.15s ease;
}

.clay-tab-btn.is-active {
  background: var(--ds-primary);
  color: var(--ds-ink-light);
}

.tab-count-pill {
  font-size: 10px;
  background: rgba(255, 255, 255, 0.2);
  padding: 2px 6px;
  border-radius: var(--ds-radius-pill);
}

.clay-tab-btn:not(.is-active) .tab-count-pill {
  background: var(--ds-surface-subtle);
  color: var(--ds-muted);
}

/* Kalemler Tabı */
.tab-content-block {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.kalem-search-row {
  margin-bottom: 4px;
}

.clay-search-wrap {
  position: relative;
  display: flex;
  align-items: center;
}

.search-icon {
  position: absolute;
  left: 14px;
  color: var(--ds-muted);
  pointer-events: none;
}

.clay-search-input {
  width: 100%;
  height: 44px;
  padding: 0 38px 0 38px;
  background: var(--ds-surface);
  border: 1px solid var(--ds-border-subtle);
  border-radius: var(--ds-radius-pill);
  font-size: 14px;
  font-family: var(--ds-font-main);
  box-shadow: var(--ds-shadow-pill);
  color: var(--ds-ink);
  outline: none;
}

.search-clear-btn {
  position: absolute;
  right: 12px;
  background: transparent;
  border: none;
  color: var(--ds-muted);
  cursor: pointer;
  padding: 4px;
}

.kalem-cards-stack {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.bento-kalem-item-card {
  background: var(--ds-surface);
  border-radius: var(--ds-radius-tile);
  padding: 14px 16px;
  border: 1px solid var(--ds-border);
  box-shadow: var(--ds-shadow-tile);
  cursor: pointer;
  display: flex;
  flex-direction: column;
  gap: 8px;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.bento-kalem-item-card:hover {
  transform: translateY(-2px);
  box-shadow: var(--ds-shadow-bento);
}

.k-item-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.k-item-poz-box {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.k-item-poz {
  font-size: 13px;
  font-weight: 800;
  color: var(--ds-blue-ink);
}

.k-item-unit {
  font-size: 11px;
  color: var(--ds-muted);
  background: var(--ds-surface-subtle);
  padding: 2px 6px;
  border-radius: var(--ds-radius-pill);
}

.k-item-tutar {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
}

.tutar-label {
  font-size: 10px;
  font-weight: 700;
  color: var(--ds-muted);
}

.tutar-val {
  font-size: 15px;
  font-weight: 800;
}

.k-item-title {
  font-size: 14px;
  font-weight: 700;
  color: var(--ds-ink);
  margin: 0;
  line-height: 1.35;
}

.k-item-metrics-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 6px;
  background: var(--ds-surface-subtle);
  border-radius: var(--ds-radius-sm);
  padding: 8px;
  text-align: center;
}

.k-m-cell {
  display: flex;
  flex-direction: column;
}

.cell-l {
  font-size: 9px;
  font-weight: 700;
  color: var(--ds-muted);
  white-space: nowrap;
}

.cell-v {
  font-size: 13px;
  font-weight: 800;
  color: #0f172a;
}

.highlight-cell .cell-v {
  color: var(--ds-blue-ink);
  font-size: 14px;
}

.k-item-progress-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-top: 2px;
}

.k-prog-info {
  font-size: 11px;
  color: var(--ds-muted);
  display: inline-flex;
  gap: 4px;
}

.k-edit-pill {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  font-weight: 600;
  color: var(--ds-blue-ink);
  background: var(--ds-blue-soft);
  padding: 3px 8px;
  border-radius: var(--ds-radius-pill);
}

/* Parametreler Tabı */
.parametre-form-container {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.param-top-action-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.param-sec-title {
  font-size: 15px;
  font-weight: 700;
  margin: 0;
}

.param-sec-sub {
  font-size: 12px;
  color: var(--ds-muted);
  margin: 2px 0 0;
}

.btn-fetch-web {
  height: 36px;
  padding: 0 14px;
  border-radius: var(--ds-radius-pill);
  border: 1px solid var(--ds-amber);
  background: var(--ds-amber-soft);
  color: var(--ds-amber-ink);
  font-size: 12px;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  cursor: pointer;
  flex-shrink: 0;
}

.param-group-card {
  background: var(--ds-surface);
  border-radius: var(--ds-radius-tile);
  padding: 14px 16px;
  border: 1px solid var(--ds-border);
  box-shadow: var(--ds-shadow-tile);
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.group-title {
  font-size: 13px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.3px;
  margin: 0;
}

.param-grid-4 {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 8px;
}

.param-grid-2 {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
}

.param-field {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.param-field label {
  font-size: 11px;
  font-weight: 600;
  color: var(--ds-muted);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.param-field input {
  height: 40px;
  border-radius: var(--ds-radius-sm);
  border: 1px solid var(--ds-border-subtle);
  background: var(--ds-surface-subtle);
  padding: 0 10px;
  font-family: var(--ds-font-main);
  font-size: 14px;
  font-weight: 600;
  color: var(--ds-ink);
  outline: none;
}

.param-field input:focus {
  border-color: var(--ds-blue);
  background: #fff;
}

.switch-row {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 4px;
}

.switch-label {
  font-size: 12px;
  font-weight: 600;
  color: var(--ds-ink);
  cursor: pointer;
}

.btn-save-params {
  height: 48px;
  border-radius: var(--ds-radius-sm);
  border: none;
  background: var(--ds-primary);
  color: var(--ds-ink-light);
  font-size: 15px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  cursor: pointer;
  margin-top: 6px;
}

.btn-save-params:disabled { opacity: 0.6; }

/* Modal Bottom Sheet */
.ds-modal-layer {
  position: fixed;
  inset: 0;
  background: var(--ds-overlay);
  backdrop-filter: blur(4px);
  z-index: 1000;
  display: flex;
  align-items: flex-end;
  justify-content: center;
}

.ds-bottom-sheet {
  background: var(--ds-surface);
  width: 100%;
  max-width: 600px;
  border-radius: 28px 28px 0 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  box-shadow: var(--ds-shadow-float);
  animation: slideUp 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}

.sheet-header {
  padding: 16px 20px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-bottom: 1px solid var(--ds-border-subtle);
}

.sheet-title {
  display: flex;
  align-items: center;
  gap: 12px;
  min-width: 0;
}

.sheet-title h3 { font-size: 16px; font-weight: 700; margin: 0; }
.sheet-title small { font-size: 12px; color: var(--ds-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.sheet-icon-box {
  width: 38px;
  height: 38px;
  border-radius: var(--ds-radius-sm);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.sheet-close-btn {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  border: none;
  background: var(--ds-surface-subtle);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--ds-muted);
  cursor: pointer;
  flex-shrink: 0;
}

.sheet-form {
  padding: 16px 20px 28px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.quick-qty-display {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  background: var(--ds-surface-subtle);
  border-radius: var(--ds-radius-sm);
  padding: 10px;
  text-align: center;
  gap: 6px;
}

.q-cell { display: flex; flex-direction: column; }
.q-cell span { font-size: 10px; font-weight: 700; color: var(--ds-muted); }
.q-cell b { font-size: 13px; font-weight: 800; color: #0f172a; margin-top: 2px; }

.form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
  margin-top: 2px;
}

.form-field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-field label {
  font-size: 12px;
  font-weight: 700;
  color: var(--ds-ink);
  display: flex;
  align-items: center;
  gap: 4px;
}

.form-field label .req {
  color: #ef4444;
  font-weight: 800;
}

.form-field input {
  width: 100%;
  height: 48px;
  border-radius: var(--ds-radius-sm);
  border: 1.5px solid var(--ds-border-subtle);
  background: var(--ds-surface-subtle);
  padding: 0 12px;
  font-size: 16px;
  font-weight: 700;
  color: var(--ds-ink);
  text-align: center;
  transition: border-color 0.15s ease, background 0.15s ease, box-shadow 0.15s ease;
  box-sizing: border-box;
}

.form-field input:focus {
  outline: none;
  border-color: var(--ds-blue, #0284c7);
  background: #ffffff;
  box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.15);
}

.calc-preview-box {
  background: #f8fafc;
  border: 1px dashed #cbd5e1;
  border-radius: var(--ds-radius-sm);
  padding: 12px 14px;
  display: flex;
  flex-direction: column;
  gap: 6px;
  font-size: 12px;
}

@media (min-width: 360px) {
  .calc-preview-box {
    flex-direction: row;
    justify-content: space-between;
    align-items: center;
  }
}

.preview-item {
  display: flex;
  align-items: baseline;
  gap: 6px;
}

.p-lbl {
  font-size: 11px;
  font-weight: 600;
  color: var(--ds-muted);
}

.p-val {
  font-size: 13px;
  font-weight: 800;
  color: #0f172a;
}

.item-tutar .p-val {
  color: var(--ds-emerald-ink);
}

.sheet-actions {
  display: grid;
  grid-template-columns: 1fr 2fr;
  gap: 10px;
  margin-top: 6px;
}

.btn-cancel {
  height: 46px;
  border-radius: var(--ds-radius-sm);
  border: 1px solid var(--ds-border-subtle);
  background: var(--ds-surface-subtle);
  font-weight: 600;
  font-size: 14px;
  color: var(--ds-ink);
  cursor: pointer;
}

.btn-submit {
  height: 46px;
  border-radius: var(--ds-radius-sm);
  border: none;
  background: var(--ds-primary);
  font-weight: 700;
  font-size: 14px;
  color: var(--ds-ink-light);
  cursor: pointer;
}

.btn-submit:disabled { opacity: 0.6; }

/* Empty & Error State */
.ds-state {
  background: var(--ds-surface);
  border-radius: var(--ds-radius-card);
  padding: 32px 20px;
  text-align: center;
  border: 1px solid var(--ds-border);
  box-shadow: var(--ds-shadow-bento);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  color: var(--ds-muted);
}

.ds-state strong { font-size: 16px; color: var(--ds-ink); }

.btn-retry {
  margin-top: 8px;
  height: 40px;
  padding: 0 18px;
  border-radius: var(--ds-radius-pill);
  border: none;
  background: var(--ds-primary);
  color: #fff;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  cursor: pointer;
}
</style>
