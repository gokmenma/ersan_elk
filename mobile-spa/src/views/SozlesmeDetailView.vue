<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  AlertTriangle,
  ArrowLeft,
  ArrowRight,
  Briefcase,
  Building2,
  Calendar,
  CheckCircle2,
  ChevronRight,
  Clock,
  Coins,
  FileSpreadsheet,
  FileText,
  Layers,
  Pencil,
  Percent,
  Plus,
  RefreshCw,
  SlidersHorizontal,
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

const loading = ref(true)
const error = ref('')
const contractData = ref(null)
const activeTab = ref('hakedisler') // 'hakedisler' | 'revizyonlar' | 'uzatimlar' | 'kalemler'

// Modal State: Yeni Hakediş
const isHakedisModalOpen = ref(false)
const hakedisModalSubmitting = ref(false)
const hakedisModalError = ref('')
const hakedisForm = ref({
  id: null,
  hakedis_no: 1,
  hakedis_tarihi_ay: new Date().getMonth() + 1,
  hakedis_tarihi_yil: new Date().getFullYear(),
  tutanak_tasdik_tarihi: '',
  durum: 'taslak',
})

// Modal State: Yeni Süre Uzatımı
const isUzatimModalOpen = ref(false)
const uzatimModalSubmitting = ref(false)
const uzatimModalError = ref('')
const uzatimForm = ref({
  uzatim_gun: 30,
  uzatim_tarihi: new Date().toISOString().split('T')[0],
  karar_no: '',
  aciklama: '',
})

// Modal State: Yeni İş Artış/Azalışı
const isRevizyonModalOpen = ref(false)
const revizyonModalSubmitting = ref(false)
const revizyonModalError = ref('')
const revizyonForm = ref({
  revizyon_tarihi: new Date().toISOString().split('T')[0],
  karar_no: '',
  aciklama: '',
  kalemler: [],
})

const aylar = [
  { val: 1, label: 'Ocak' },
  { val: 2, label: 'Şubat' },
  { val: 3, label: 'Mart' },
  { val: 4, label: 'Nisan' },
  { val: 5, label: 'Mayıs' },
  { val: 6, label: 'Haziran' },
  { val: 7, label: 'Temmuz' },
  { val: 8, label: 'Ağustos' },
  { val: 9, label: 'Eylül' },
  { val: 10, label: 'Ekim' },
  { val: 11, label: 'Kasım' },
  { val: 12, label: 'Aralık' },
]

async function loadDetail() {
  loading.value = true
  error.value = ''

  try {
    const res = await sozlesmeApi.getContract(sozlesmeId.value)
    if (res.success) {
      contractData.value = res
      // Hakediş formu için varsayılan bir sonraki no
      const nextNo = (res.donemler?.length || 0) + 1
      hakedisForm.value.hakedis_no = nextNo
    } else {
      error.value = res.message || 'Sözleşme bilgisi yüklenemedi.'
    }
  } catch (err) {
    error.value = err.message || 'Bağlantı hatası oluştu.'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadDetail()
})

// Hakediş Oluştur
function openNewHakedisModal() {
  hakedisModalError.value = ''
  const nextNo = (contractData.value?.donemler?.length || 0) + 1
  hakedisForm.value = {
    id: null,
    hakedis_no: nextNo,
    hakedis_tarihi_ay: new Date().getMonth() + 1,
    hakedis_tarihi_yil: new Date().getFullYear(),
    tutanak_tasdik_tarihi: '',
    durum: 'taslak',
  }
  isHakedisModalOpen.value = true
}

async function submitHakedis() {
  hakedisModalSubmitting.value = true
  hakedisModalError.value = ''

  try {
    const payload = {
      ...hakedisForm.value,
      sozlesme_id: sozlesmeId.value,
    }
    const res = await sozlesmeApi.saveHakedis(payload, auth.state.csrfToken)
    if (res.success) {
      isHakedisModalOpen.value = false
      await loadDetail()
      if (res.id) {
        goToHakedis(res.id)
      }
    } else {
      hakedisModalError.value = res.message || 'Hakediş oluşturulamadı.'
    }
  } catch (err) {
    hakedisModalError.value = err.message || 'Hata oluştu.'
  } finally {
    hakedisModalSubmitting.value = false
  }
}

async function deleteHakedis(d, event) {
  event?.stopPropagation?.()
  if (!confirm(`Hakediş #${d.hakedis_no} kaydını silmek istediğinize emin misiniz?`)) return

  try {
    const res = await sozlesmeApi.deleteHakedis(d.id, auth.state.csrfToken)
    if (res.success) {
      await loadDetail()
    } else {
      alert(res.message || 'Silme işlemi başarısız.')
    }
  } catch (err) {
    alert(err.message || 'Hata oluştu.')
  }
}

function goToHakedis(hakedisId) {
  router.push(`/sozlesme-hakedis/${encodeURIComponent(sozlesmeId.value)}/hakedis/${encodeURIComponent(hakedisId)}`)
}

// Süre Uzatımı Ekle
function openUzatimModal() {
  uzatimModalError.value = ''
  uzatimForm.value = {
    uzatim_gun: 30,
    uzatim_tarihi: new Date().toISOString().split('T')[0],
    karar_no: '',
    aciklama: '',
  }
  isUzatimModalOpen.value = true
}

async function submitUzatim() {
  if (uzatimForm.value.uzatim_gun <= 0) {
    uzatimModalError.value = 'Lütfen geçerli bir gün sayısı girin.'
    return
  }

  uzatimModalSubmitting.value = true
  uzatimModalError.value = ''

  try {
    const payload = {
      ...uzatimForm.value,
      sozlesme_id: sozlesmeId.value,
    }
    const res = await sozlesmeApi.saveSureUzatimi(payload, auth.state.csrfToken)
    if (res.success) {
      isUzatimModalOpen.value = false
      await loadDetail()
    } else {
      uzatimModalError.value = res.message || 'Süre uzatımı kaydedilemedi.'
    }
  } catch (err) {
    uzatimModalError.value = err.message || 'Hata oluştu.'
  } finally {
    uzatimModalSubmitting.value = false
  }
}

// Revizyon (İş Artış/Azalış) Ekle
function openRevizyonModal() {
  revizyonModalError.value = ''
  const currentKalemler = contractData.value?.kalemler || []
  revizyonForm.value = {
    revizyon_tarihi: new Date().toISOString().split('T')[0],
    karar_no: '',
    aciklama: '',
    kalemler: currentKalemler.map(k => ({
      kalem_id: k.id,
      poz_no: k.poz_no,
      kalem_adi: k.kalem_adi,
      birim: k.birim,
      birim_fiyat: k.teklif_edilen_birim_fiyat,
      onceki_miktar: k.miktari,
      degisim_miktari: 0,
      yeni_miktar: k.miktari,
    })),
  }
  isRevizyonModalOpen.value = true
}

function updateRevizyonMiktar(item) {
  item.yeni_miktar = Number(item.onceki_miktar) + Number(item.degisim_miktari || 0)
}

async function submitRevizyon() {
  revizyonModalSubmitting.value = true
  revizyonModalError.value = ''

  try {
    const payload = {
      ...revizyonForm.value,
      sozlesme_id: sozlesmeId.value,
    }
    const res = await sozlesmeApi.saveRevizyon(payload, auth.state.csrfToken)
    if (res.success) {
      isRevizyonModalOpen.value = false
      await loadDetail()
    } else {
      revizyonModalError.value = res.message || 'Revizyon kaydedilemedi.'
    }
  } catch (err) {
    revizyonModalError.value = err.message || 'Hata oluştu.'
  } finally {
    revizyonModalSubmitting.value = false
  }
}

function getHakedisBadgeClass(durum) {
  switch (durum) {
    case 'onaylandi':
      return 'badge-hakedis--onaylandi'
    case 'tamamlandi':
      return 'badge-hakedis--tamamlandi'
    case 'hazirlandi':
      return 'badge-hakedis--hazirlandi'
    default:
      return 'badge-hakedis--taslak'
  }
}

function getHakedisBadgeLabel(durum) {
  switch (durum) {
    case 'onaylandi':
      return 'Onaylandı'
    case 'tamamlandi':
      return 'Tamamlandı'
    case 'hazirlandi':
      return 'Hazırlandı'
    default:
      return 'Taslak'
  }
}
</script>

<template>
  <div class="ds-screen sozlesme-detail-screen">
    <!-- Üst TopBar -->
    <TopBar
      eyebrow="SÖZLEŞME DETAYI"
      :title="contractData?.contract?.idare_adi || 'Sözleşme Detayı'"
      :subtitle="contractData?.contract?.isin_adi || 'Keşif, süre ve hakedişler'"
      :back="true"
    />

    <!-- Yükleniyor Durumu -->
    <div v-if="loading" class="ds-loading">
      <i v-for="n in 3" :key="n" style="height: 120px; border-radius: var(--ds-radius-card);"></i>
    </div>

    <!-- Hata Durumu -->
    <div v-else-if="error" class="ds-state error-state">
      <AlertTriangle :size="28" />
      <strong>Yüklenemedi</strong>
      <p>{{ error }}</p>
      <button type="button" class="btn-retry" @click="loadDetail">
        <RefreshCw :size="16" /> Tekrar Dene
      </button>
    </div>

    <!-- Ana İçerik -->
    <template v-else-if="contractData && contractData.contract">
      <!-- 1. Üst Özet Bento Kartı -->
      <section class="contract-hero-bento">
        <div class="hero-top-row">
          <div class="hero-idare-tag">
            <Building2 :size="13" />
            <span>{{ contractData.contract.idare_adi }}</span>
          </div>
          <span class="hero-status-badge" :class="`status--${contractData.contract.durum}`">
            {{ contractData.contract.durum === 'aktif' ? 'Aktif' : contractData.contract.durum }}
          </span>
        </div>

        <h2 class="hero-work-title">{{ contractData.contract.isin_adi }}</h2>

        <div class="hero-metrics-grid">
          <div class="hero-metric-box">
            <span class="m-label">SÖZLEŞME BEDELİ</span>
            <span class="m-val">{{ contractData.contract.yeni_sozlesme_bedeli_formatted }}</span>
            <span v-if="contractData.toplam_is_artis_tutari != 0" class="m-sub">
              Ana Bedel: {{ contractData.contract.sozlesme_bedeli_formatted }}
              ({{ contractData.toplam_is_artis_tutari_formatted }})
            </span>
          </div>

          <div class="hero-metric-box">
            <span class="m-label">BİTİŞ TARİHİ</span>
            <span class="m-val">{{ contractData.contract.isin_bitecegi_tarih || '-' }}</span>
            <span class="m-sub">
              {{ contractData.contract.isin_suresi }} Gün
              <template v-if="contractData.contract.toplam_uzatim_gunu > 0">
                (+{{ contractData.contract.toplam_uzatim_gunu }} gün uzatım)
              </template>
            </span>
          </div>
        </div>
      </section>

      <!-- 2. Segmented Navigasyon Tabları -->
      <nav class="clay-tabs-wrap">
        <button
          type="button"
          class="clay-tab-btn"
          :class="{ 'is-active': activeTab === 'hakedisler' }"
          @click="activeTab = 'hakedisler'"
        >
          <FileSpreadsheet :size="15" />
          <span>Hakedişler</span>
          <b class="tab-count-pill">{{ contractData.donemler?.length || 0 }}</b>
        </button>

        <button
          type="button"
          class="clay-tab-btn"
          :class="{ 'is-active': activeTab === 'revizyonlar' }"
          @click="activeTab = 'revizyonlar'"
        >
          <TrendingUp :size="15" />
          <span>İş Artış</span>
          <b v-if="contractData.is_revizyonlari?.length" class="tab-count-pill">
            {{ contractData.is_revizyonlari.length }}
          </b>
        </button>

        <button
          type="button"
          class="clay-tab-btn"
          :class="{ 'is-active': activeTab === 'uzatimlar' }"
          @click="activeTab = 'uzatimlar'"
        >
          <Clock :size="15" />
          <span>Süre</span>
          <b v-if="contractData.sure_uzatimlari?.length" class="tab-count-pill">
            {{ contractData.sure_uzatimlari.length }}
          </b>
        </button>

        <button
          type="button"
          class="clay-tab-btn"
          :class="{ 'is-active': activeTab === 'kalemler' }"
          @click="activeTab = 'kalemler'"
        >
          <Layers :size="15" />
          <span>Kalemler</span>
        </button>
      </nav>

      <!-- TAB 1: HAKEDİŞLER LİSTESİ -->
      <section v-if="activeTab === 'hakedisler'" class="tab-content-section">
        <div class="tab-action-header">
          <div>
            <h3 class="section-title">Hakediş Dönemleri</h3>
            <p class="section-desc">Dönem imalatları, endeksler ve fiyat farkı takibi</p>
          </div>
          <button type="button" class="btn-primary-action" @click="openNewHakedisModal">
            <Plus :size="16" /> + Yeni Hakediş
          </button>
        </div>

        <div v-if="!contractData.donemler?.length" class="tab-empty-state">
          <FileSpreadsheet :size="32" />
          <p>Bu sözleşmeye ait henüz hakediş dönemi oluşturulmamış.</p>
          <button type="button" class="btn-create-sub" @click="openNewHakedisModal">
            İlk Hakedişi Oluştur
          </button>
        </div>

        <div v-else class="hakedis-cards-stack">
          <div
            v-for="d in contractData.donemler"
            :key="d.id"
            class="bento-hakedis-card"
            role="button"
            tabindex="0"
            @click="goToHakedis(d.id)"
            @keydown.enter="goToHakedis(d.id)"
          >
            <div class="hakedis-card-top">
              <div class="hakedis-no-badge">
                <span class="h-hash">#{{ d.hakedis_no }}</span>
                <span class="h-period">{{ d.donem_adi }}</span>
              </div>
              <span class="badge-hakedis" :class="getHakedisBadgeClass(d.durum)">
                {{ getHakedisBadgeLabel(d.durum) }}
              </span>
            </div>

            <div class="hakedis-card-body">
              <div class="hakedis-amount-block">
                <span class="h-label">DÖNEM İMALAT TUTARI</span>
                <span class="h-val">{{ d.imalat_donem_formatted || d.hakedi_tutari_formatted }}</span>
              </div>
              <div v-if="d.imalat_kumulatif > 0 && d.imalat_kumulatif !== d.imalat_donem" class="h-sub-kumulatif">
                <span>Kümülatif İmalat: {{ d.imalat_kumulatif_formatted }}</span>
              </div>
            </div>

            <div class="hakedis-card-bottom">
              <span class="h-date-chip">
                <Calendar :size="12" /> Tasdik: {{ d.tutanak_tasdik_tarihi }}
              </span>

              <div class="h-actions" @click.stop>
                <button
                  type="button"
                  class="action-icon-btn text-danger"
                  title="Sil"
                  @click="deleteHakedis(d, $event)"
                >
                  <Trash2 :size="14" />
                </button>
                <div class="btn-enter-arrow">
                  <span>Detaya Git</span>
                  <ChevronRight :size="16" />
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- TAB 2: İŞ ARTIŞ/AZALIŞ (REVİZYONLAR) -->
      <section v-else-if="activeTab === 'revizyonlar'" class="tab-content-section">
        <div class="tab-action-header">
          <div>
            <h3 class="section-title">İş Artış ve Azalışları</h3>
            <p class="section-desc">Sözleşme keşif artışları ve kalem revizyonları</p>
          </div>
          <button type="button" class="btn-primary-action" @click="openRevizyonModal">
            <Plus :size="16" /> + Yeni Revizyon
          </button>
        </div>

        <div class="revizyon-summary-box">
          <div class="r-sum-item">
            <span class="r-sum-label">TOPLAM İŞ ARTIŞ TUTARI</span>
            <span
              class="r-sum-val"
              :class="contractData.toplam_is_artis_tutari >= 0 ? 'text-emerald' : 'text-danger'"
            >
              {{ contractData.toplam_is_artis_tutari_formatted }}
            </span>
          </div>
          <div class="r-sum-item">
            <span class="r-sum-label">REVİZE SÖZLEŞME BEDELİ</span>
            <span class="r-sum-val">{{ contractData.contract.yeni_sozlesme_bedeli_formatted }}</span>
          </div>
        </div>

        <div v-if="!contractData.is_revizyonlari?.length" class="tab-empty-state">
          <TrendingUp :size="32" />
          <p>Henüz iş artış veya azalış revizyonu girilmemiş.</p>
        </div>

        <div v-else class="revizyon-cards-stack">
          <div v-for="r in contractData.is_revizyonlari" :key="r.id" class="bento-revizyon-card">
            <div class="rev-card-header">
              <div class="rev-title-group">
                <span class="rev-badge">{{ r.revizyon_no }}. Revizyon</span>
                <span class="rev-date">{{ r.revizyon_tarihi }}</span>
              </div>
              <div class="rev-diff-tag" :class="r.toplam_tutar_farki >= 0 ? 'tag-plus' : 'tag-minus'">
                {{ r.toplam_tutar_farki_formatted }} (%{{ r.artis_orani }})
              </div>
            </div>

            <p v-if="r.aciklama" class="rev-note">{{ r.aciklama }}</p>
            <small v-if="r.karar_no" class="rev-karar">Karar / Onay No: {{ r.karar_no }}</small>

            <div v-if="r.kalemler?.length" class="rev-items-table">
              <div v-for="k in r.kalemler" :key="k.poz_no" class="rev-item-row">
                <span class="rev-item-name">{{ k.poz_no }} - {{ k.kalem_adi }}</span>
                <span class="rev-item-change" :class="k.degisim_miktari >= 0 ? 'text-emerald' : 'text-danger'">
                  {{ k.degisim_miktari > 0 ? '+' : '' }}{{ k.degisim_miktari }} {{ k.birim }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- TAB 3: SÜRE UZATIMLARI -->
      <section v-else-if="activeTab === 'uzatimlar'" class="tab-content-section">
        <div class="tab-action-header">
          <div>
            <h3 class="section-title">Süre Uzatımları</h3>
            <p class="section-desc">Olur / karar ile uzatılan iş günleri</p>
          </div>
          <button type="button" class="btn-primary-action" @click="openUzatimModal">
            <Plus :size="16" /> + Yeni Uzatım
          </button>
        </div>

        <div class="uzatim-summary-box">
          <span class="u-sum-label">TOPLAM UZATILAN SÜRE</span>
          <span class="u-sum-val text-emerald">+{{ contractData.contract.toplam_uzatim_gunu }} Gün</span>
          <span class="u-sum-sub">Son Bitiş Tarihi: {{ contractData.contract.isin_bitecegi_tarih }}</span>
        </div>

        <div v-if="!contractData.sure_uzatimlari?.length" class="tab-empty-state">
          <Clock :size="32" />
          <p>Henüz süre uzatımı kaydı bulunmuyor.</p>
        </div>

        <div v-else class="uzatim-cards-stack">
          <div v-for="u in contractData.sure_uzatimlari" :key="u.id" class="bento-uzatim-card">
            <div class="u-top-row">
              <span class="u-badge">{{ u.uzatim_no }}. Uzatım</span>
              <span class="u-days-tag text-emerald">+{{ u.uzatim_gun }} Gün</span>
            </div>
            <div class="u-dates-row">
              <span>Eski Bitiş: {{ u.onceki_bitis_tarihi }}</span>
              <ArrowRight :size="14" />
              <strong>Yeni: {{ u.yeni_bitis_tarihi }}</strong>
            </div>
            <p v-if="u.aciklama" class="u-desc">{{ u.aciklama }}</p>
            <small v-if="u.karar_no" class="u-karar">Karar: {{ u.karar_no }}</small>
          </div>
        </div>
      </section>

      <!-- TAB 4: SÖZLEŞME KALEMLERİ & BİLGİLER -->
      <section v-else-if="activeTab === 'kalemler'" class="tab-content-section">
        <div class="tab-action-header">
          <div>
            <h3 class="section-title">Birim Fiyat Cetveli</h3>
            <p class="section-desc">Sözleşme teklif kalemleri ve birim fiyatları</p>
          </div>
        </div>

        <div class="kalem-items-stack">
          <div v-for="(k, idx) in contractData.kalemler" :key="k.id" class="bento-kalem-card">
            <div class="k-card-top">
              <span class="k-idx">#{{ idx + 1 }}</span>
              <span class="k-poz">{{ k.poz_no }}</span>
              <span class="k-total text-emerald">{{ k.toplam_tutar_formatted }}</span>
            </div>
            <h4 class="k-name">{{ k.kalem_adi }}</h4>
            <div class="k-meta-row">
              <span>Miktar: <b>{{ k.miktari }} {{ k.birim }}</b></span>
              <span>B.Fiyat: <b>{{ k.teklif_edilen_birim_fiyat_formatted }}</b></span>
            </div>
          </div>
        </div>

        <div class="form-section-title mt-4">Katsayılar & Temel Endeksler</div>
        <div class="contract-indices-grid">
          <div class="idx-box"><span>a1 (İşçilik):</span> <b>{{ contractData.contract.a1_katsayisi }}</b></div>
          <div class="idx-box"><span>b1 (Motorin):</span> <b>{{ contractData.contract.b1_katsayisi }}</b></div>
          <div class="idx-box"><span>b2 (Yİ-ÜFE):</span> <b>{{ contractData.contract.b2_katsayisi }}</b></div>
          <div class="idx-box"><span>c (Makine):</span> <b>{{ contractData.contract.c_katsayisi }}</b></div>
          <div class="idx-box"><span>KDV Oranı:</span> <b>%{{ contractData.contract.kdv_orani }}</b></div>
          <div class="idx-box"><span>Tevkifat:</span> <b>{{ contractData.contract.tevkifat_orani }}</b></div>
        </div>
      </section>
    </template>

    <!-- Modal: Yeni Hakediş Ekle -->
    <Transition name="ds-modal">
      <div v-if="isHakedisModalOpen" class="ds-modal-layer" @click.self="isHakedisModalOpen = false">
        <div class="ds-bottom-sheet" role="dialog" aria-modal="true">
          <div class="sheet-header">
            <div class="sheet-title">
              <div class="sheet-icon-box bg-emerald-soft text-emerald">
                <FileSpreadsheet :size="18" />
              </div>
              <div>
                <h3>Yeni Hakediş Dönemi</h3>
                <small>Dönem parametrelerini belirleyin</small>
              </div>
            </div>
            <button type="button" class="sheet-close-btn" @click="isHakedisModalOpen = false">
              <X :size="18" />
            </button>
          </div>

          <form class="sheet-form" @submit.prevent="submitHakedis">
            <div v-if="hakedisModalError" class="modal-alert-error">
              <AlertTriangle :size="16" />
              <span>{{ hakedisModalError }}</span>
            </div>

            <div class="form-grid">
              <div class="form-field">
                <label>Hakediş No <span class="req">*</span></label>
                <input v-model="hakedisForm.hakedis_no" type="number" required />
              </div>

              <div class="form-field">
                <label>Durum</label>
                <select v-model="hakedisForm.durum">
                  <option value="taslak">Taslak</option>
                  <option value="hazirlandi">Hazırlandı</option>
                  <option value="tamamlandi">Tamamlandı</option>
                  <option value="onaylandi">Onaylandı</option>
                </select>
              </div>

              <div class="form-field">
                <label>Hakediş Ayı <span class="req">*</span></label>
                <select v-model="hakedisForm.hakedis_tarihi_ay" required>
                  <option v-for="a in aylar" :key="a.val" :value="a.val">{{ a.label }}</option>
                </select>
              </div>

              <div class="form-field">
                <label>Hakediş Yılı <span class="req">*</span></label>
                <input v-model="hakedisForm.hakedis_tarihi_yil" type="number" required />
              </div>

              <div class="form-field full-width">
                <label>Tutanak Tasdik Tarihi</label>
                <input v-model="hakedisForm.tutanak_tasdik_tarihi" type="date" />
              </div>
            </div>

            <div class="sheet-actions">
              <button type="button" class="btn-cancel" @click="isHakedisModalOpen = false">Vazgeç</button>
              <button type="submit" class="btn-submit" :disabled="hakedisModalSubmitting">
                <span v-if="hakedisModalSubmitting">Oluşturuluyor...</span>
                <span v-else>Hakedişi Oluştur</span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </Transition>

    <!-- Modal: Süre Uzatımı Ekle -->
    <Transition name="ds-modal">
      <div v-if="isUzatimModalOpen" class="ds-modal-layer" @click.self="isUzatimModalOpen = false">
        <div class="ds-bottom-sheet" role="dialog" aria-modal="true">
          <div class="sheet-header">
            <div class="sheet-title">
              <div class="sheet-icon-box bg-blue-soft text-blue">
                <Clock :size="18" />
              </div>
              <div>
                <h3>Süre Uzatımı Ekle</h3>
                <small>İş bitiş süresine gün ekler</small>
              </div>
            </div>
            <button type="button" class="sheet-close-btn" @click="isUzatimModalOpen = false">
              <X :size="18" />
            </button>
          </div>

          <form class="sheet-form" @submit.prevent="submitUzatim">
            <div v-if="uzatimModalError" class="modal-alert-error">
              <AlertTriangle :size="16" />
              <span>{{ uzatimModalError }}</span>
            </div>

            <div class="form-grid">
              <div class="form-field">
                <label>Uzatılan Süre (Gün) <span class="req">*</span></label>
                <input v-model="uzatimForm.uzatim_gun" type="number" required placeholder="30" />
              </div>

              <div class="form-field">
                <label>Karar Tarihi</label>
                <input v-model="uzatimForm.uzatim_tarihi" type="date" />
              </div>

              <div class="form-field full-width">
                <label>Karar / Onay No</label>
                <input v-model="uzatimForm.karar_no" type="text" placeholder="Varsa karar numarası" />
              </div>

              <div class="form-field full-width">
                <label>Açıklama</label>
                <textarea v-model="uzatimForm.aciklama" rows="2" placeholder="Süre uzatımı gerekçesi"></textarea>
              </div>
            </div>

            <div class="sheet-actions">
              <button type="button" class="btn-cancel" @click="isUzatimModalOpen = false">Vazgeç</button>
              <button type="submit" class="btn-submit" :disabled="uzatimModalSubmitting">
                <span v-if="uzatimModalSubmitting">Kaydediliyor...</span>
                <span v-else>Süre Uzatımını Kaydet</span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </Transition>

    <!-- Modal: Revizyon (İş Artış/Azalış) Ekle -->
    <Transition name="ds-modal">
      <div v-if="isRevizyonModalOpen" class="ds-modal-layer" @click.self="isRevizyonModalOpen = false">
        <div class="ds-bottom-sheet ds-bottom-sheet--large" role="dialog" aria-modal="true">
          <div class="sheet-header">
            <div class="sheet-title">
              <div class="sheet-icon-box bg-emerald-soft text-emerald">
                <TrendingUp :size="18" />
              </div>
              <div>
                <h3>Yeni İş Artış/Azalış Revizyonu</h3>
                <small>Kalem bazlı pozitif/negatif miktar değişimi</small>
              </div>
            </div>
            <button type="button" class="sheet-close-btn" @click="isRevizyonModalOpen = false">
              <X :size="18" />
            </button>
          </div>

          <form class="sheet-form" @submit.prevent="submitRevizyon">
            <div v-if="revizyonModalError" class="modal-alert-error">
              <AlertTriangle :size="16" />
              <span>{{ revizyonModalError }}</span>
            </div>

            <div class="form-grid">
              <div class="form-field">
                <label>Revizyon Tarihi</label>
                <input v-model="revizyonForm.revizyon_tarihi" type="date" />
              </div>

              <div class="form-field">
                <label>Karar / Onay No</label>
                <input v-model="revizyonForm.karar_no" type="text" placeholder="Karar No" />
              </div>

              <div class="form-field full-width">
                <label>Açıklama</label>
                <input v-model="revizyonForm.aciklama" type="text" placeholder="Revizyon açıklaması" />
              </div>
            </div>

            <div class="form-section-title mt-2">Kalem Miktar Değişimleri</div>
            <div class="rev-items-form-list">
              <div
                v-for="k in revizyonForm.kalemler"
                :key="k.kalem_id"
                class="rev-edit-card"
              >
                <div class="rev-edit-head">
                  <span class="rev-edit-poz">{{ k.poz_no }}</span>
                  <span class="rev-edit-name">{{ k.kalem_adi }}</span>
                </div>
                <div class="rev-edit-inputs">
                  <div class="rev-qty-info">
                    Mevcut: <b>{{ k.onceki_miktar }} {{ k.birim }}</b>
                  </div>
                  <div class="rev-input-field">
                    <label>Değişim (+/-)</label>
                    <input
                      v-model="k.degisim_miktari"
                      type="number"
                      step="any"
                      placeholder="0"
                      @input="updateRevizyonMiktar(k)"
                    />
                  </div>
                  <div class="rev-qty-info text-end">
                    Yeni: <b>{{ k.yeni_miktar }} {{ k.birim }}</b>
                  </div>
                </div>
              </div>
            </div>

            <div class="sheet-actions">
              <button type="button" class="btn-cancel" @click="isRevizyonModalOpen = false">Vazgeç</button>
              <button type="submit" class="btn-submit" :disabled="revizyonModalSubmitting">
                <span v-if="revizyonModalSubmitting">Kaydediliyor...</span>
                <span v-else>Revizyonu Kaydet</span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.sozlesme-detail-screen {
  padding-bottom: calc(var(--ds-nav-clearance) + 20px);
}

/* Üst Özet Bento Kartı */
.contract-hero-bento {
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

.hero-top-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.hero-idare-tag {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  color: var(--ds-blue-ink);
  letter-spacing: 0.3px;
}

.hero-status-badge {
  padding: 4px 10px;
  border-radius: var(--ds-radius-pill);
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
}

.status--aktif {
  background: var(--ds-emerald-soft);
  color: var(--ds-emerald-ink);
  border: 1px solid rgba(16, 185, 129, 0.25);
}

.hero-work-title {
  font-size: 16px;
  font-weight: 800;
  color: var(--ds-ink);
  line-height: 1.35;
  margin: 0;
}

.hero-metrics-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
  background: var(--ds-surface-subtle);
  border-radius: var(--ds-radius-sm);
  padding: 12px 14px;
}

.hero-metric-box {
  display: flex;
  flex-direction: column;
}

.m-label {
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.5px;
  color: var(--ds-muted);
}

.m-val {
  font-size: 16px;
  font-weight: 800;
  color: #0f172a;
}

.m-sub {
  font-size: 11px;
  color: var(--ds-muted);
  margin-top: 2px;
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
  overflow-x: auto;
  scrollbar-width: none;
}
.clay-tabs-wrap::-webkit-scrollbar { display: none; }

.clay-tab-btn {
  flex: 1;
  min-width: fit-content;
  height: 36px;
  padding: 0 12px;
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

/* Tab İçerik Bölümleri */
.tab-content-section {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.tab-action-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.section-title {
  font-size: 15px;
  font-weight: 700;
  margin: 0;
  color: var(--ds-ink);
}

.section-desc {
  font-size: 12px;
  color: var(--ds-muted);
  margin: 2px 0 0;
}

.btn-primary-action {
  height: 36px;
  padding: 0 14px;
  border-radius: var(--ds-radius-pill);
  border: none;
  background: var(--ds-primary);
  color: #fff;
  font-size: 12px;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  cursor: pointer;
  flex-shrink: 0;
}

/* Hakediş Kartları */
.hakedis-cards-stack {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.bento-hakedis-card {
  background: var(--ds-surface);
  border-radius: var(--ds-radius-tile);
  padding: 14px 16px;
  border: 1px solid var(--ds-border);
  box-shadow: var(--ds-shadow-tile);
  cursor: pointer;
  display: flex;
  flex-direction: column;
  gap: 10px;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.bento-hakedis-card:hover {
  transform: translateY(-2px);
  box-shadow: var(--ds-shadow-bento);
}

.hakedis-card-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.hakedis-no-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.h-hash {
  font-size: 15px;
  font-weight: 800;
  color: var(--ds-ink);
}

.h-period {
  font-size: 13px;
  font-weight: 600;
  color: var(--ds-muted);
}

.badge-hakedis {
  padding: 3px 8px;
  border-radius: var(--ds-radius-pill);
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
}

.badge-hakedis--onaylandi { background: var(--ds-blue-soft); color: var(--ds-blue-ink); }
.badge-hakedis--tamamlandi { background: var(--ds-emerald-soft); color: var(--ds-emerald-ink); }
.badge-hakedis--hazirlandi { background: var(--ds-amber-soft); color: var(--ds-amber-ink); }
.badge-hakedis--taslak { background: var(--ds-surface-subtle); color: var(--ds-muted); }

.hakedis-card-body {
  background: var(--ds-surface-subtle);
  border-radius: var(--ds-radius-sm);
  padding: 8px 12px;
}

.hakedis-amount-block {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
}

.h-label {
  font-size: 11px;
  font-weight: 700;
  color: var(--ds-muted);
}

.h-val {
  font-size: 16px;
  font-weight: 800;
  color: var(--ds-emerald-ink);
}

.h-sub-kumulatif {
  display: flex;
  justify-content: flex-end;
  font-size: 11px;
  font-weight: 600;
  color: var(--ds-muted);
  margin-top: 4px;
}

.hakedis-card-bottom {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-top: 2px;
}

.h-date-chip {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  color: var(--ds-muted);
}

.h-actions {
  display: flex;
  align-items: center;
  gap: 6px;
}

.btn-enter-arrow {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  font-weight: 600;
  color: var(--ds-blue-ink);
}

/* Revizyon ve Süre Kutuları */
.revizyon-summary-box,
.uzatim-summary-box {
  background: var(--ds-surface);
  border-radius: var(--ds-radius-tile);
  padding: 14px 16px;
  border: 1px solid var(--ds-border);
  box-shadow: var(--ds-shadow-tile);
  display: flex;
  align-items: center;
  justify-content: space-around;
  text-align: center;
}

.r-sum-item {
  display: flex;
  flex-direction: column;
}

.r-sum-label, .u-sum-label {
  font-size: 11px;
  font-weight: 700;
  color: var(--ds-muted);
}

.r-sum-val, .u-sum-val {
  font-size: 17px;
  font-weight: 800;
  margin-top: 2px;
}

.u-sum-sub {
  font-size: 11px;
  color: var(--ds-muted);
}

.bento-revizyon-card,
.bento-uzatim-card {
  background: var(--ds-surface);
  border-radius: var(--ds-radius-tile);
  padding: 14px 16px;
  border: 1px solid var(--ds-border);
  box-shadow: var(--ds-shadow-tile);
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.rev-card-header, .u-top-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.rev-badge, .u-badge {
  font-size: 14px;
  font-weight: 800;
  color: var(--ds-ink);
}

.rev-diff-tag {
  font-size: 12px;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: var(--ds-radius-pill);
}

.tag-plus { background: var(--ds-emerald-soft); color: var(--ds-emerald-ink); }
.tag-minus { background: var(--ds-danger-soft); color: var(--ds-danger-ink); }

.rev-items-table {
  background: var(--ds-surface-subtle);
  border-radius: var(--ds-radius-sm);
  padding: 8px 12px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.rev-item-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 12px;
}

/* Kalem Kartları */
.bento-kalem-card {
  background: var(--ds-surface);
  border-radius: var(--ds-radius-tile);
  padding: 12px 14px;
  border: 1px solid var(--ds-border);
  box-shadow: var(--ds-shadow-tile);
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-bottom: 8px;
}

.k-card-top {
  display: flex;
  align-items: center;
  gap: 8px;
}

.k-idx { font-size: 12px; font-weight: 700; color: var(--ds-muted); }
.k-poz { font-size: 12px; font-weight: 800; color: var(--ds-blue-ink); }
.k-total { margin-left: auto; font-size: 13px; font-weight: 700; }
.k-name { font-size: 13px; font-weight: 600; margin: 0; line-height: 1.3; }
.k-meta-row { display: flex; justify-content: space-between; font-size: 12px; color: var(--ds-muted); }

.contract-indices-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
  margin-top: 8px;
}

.idx-box {
  background: var(--ds-surface);
  padding: 10px;
  border-radius: var(--ds-radius-sm);
  border: 1px solid var(--ds-border-subtle);
  font-size: 12px;
  display: flex;
  justify-content: space-between;
}

/* Modal Stilleri */
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
  max-height: 90vh;
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
}

.sheet-title h3 { font-size: 17px; font-weight: 700; margin: 0; }
.sheet-title small { font-size: 12px; color: var(--ds-muted); }

.sheet-icon-box {
  width: 38px;
  height: 38px;
  border-radius: var(--ds-radius-sm);
  display: flex;
  align-items: center;
  justify-content: center;
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
}

.sheet-form {
  padding: 16px 20px 28px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

.form-field {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.form-field.full-width { grid-column: span 2; }
.form-field label { font-size: 12px; font-weight: 600; color: var(--ds-muted); }
.form-field label .req { color: var(--ds-danger); }

.form-field input, .form-field select, .form-field textarea {
  height: 42px;
  border-radius: var(--ds-radius-sm);
  border: 1px solid var(--ds-border-subtle);
  background: var(--ds-surface-subtle);
  padding: 0 12px;
  font-family: var(--ds-font-main);
  font-size: 14px;
  color: var(--ds-ink);
  outline: none;
}

.form-field textarea { height: auto; padding: 8px 12px; }

.modal-alert-error {
  background: var(--ds-danger-soft);
  color: var(--ds-danger-ink);
  border: 1px solid rgba(239, 68, 68, 0.2);
  border-radius: var(--ds-radius-sm);
  padding: 10px 14px;
  font-size: 13px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.sheet-actions {
  display: grid;
  grid-template-columns: 1fr 2fr;
  gap: 10px;
  margin-top: 10px;
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

.tab-empty-state {
  background: var(--ds-surface);
  border-radius: var(--ds-radius-tile);
  padding: 28px 16px;
  text-align: center;
  color: var(--ds-muted);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
}

.btn-create-sub {
  height: 38px;
  padding: 0 16px;
  border-radius: var(--ds-radius-pill);
  border: none;
  background: var(--ds-primary);
  color: #fff;
  font-weight: 600;
  cursor: pointer;
}

.rev-items-form-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
  max-height: 280px;
  overflow-y: auto;
}

.rev-edit-card {
  background: var(--ds-surface-subtle);
  border-radius: var(--ds-radius-sm);
  padding: 10px 12px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.rev-edit-head {
  display: flex;
  gap: 6px;
  font-size: 12px;
  font-weight: 700;
}

.rev-edit-poz { color: var(--ds-blue-ink); }
.rev-edit-inputs {
  display: grid;
  grid-template-columns: 1fr 1.2fr 1fr;
  align-items: center;
  gap: 8px;
}

.rev-qty-info { font-size: 11px; color: var(--ds-muted); }
.rev-input-field input {
  height: 36px;
  width: 100%;
  border-radius: var(--ds-radius-sm);
  border: 1px solid var(--ds-border-subtle);
  background: #fff;
  padding: 0 8px;
  text-align: center;
  font-weight: 700;
}
</style>
