<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import {
  AlertTriangle,
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
  Filter,
  Layers,
  Pencil,
  Percent,
  Plus,
  RefreshCw,
  Search,
  SlidersHorizontal,
  Trash2,
  TrendingUp,
  Wallet,
  X,
} from '@lucide/vue'
import TopBar from '../components/TopBar.vue'
import { sozlesmeApi } from '../services/sozlesme-hakedis.js'
import { auth } from '../services/auth.js'

const router = useRouter()

// Veri Durumları
const loading = ref(true)
const error = ref('')
const contracts = ref([])
const summary = ref({
  toplam_sozlesme: 0,
  aktif_sayisi: 0,
  aktif_bedel: 0,
  aktif_bedel_formatted: '0,00 ₺',
  tamamlanan_sayisi: 0,
  tamamlanan_bedel: 0,
  tamamlanan_bedel_formatted: '0,00 ₺',
  pasif_sayisi: 0,
  pasif_bedel: 0,
  toplam_bedel: 0,
  toplam_bedel_formatted: '0,00 ₺',
})

const pagination = ref({ page: 1, limit: 20, total: 0, has_more: false })
const search = ref('')
const activeStatus = ref('all') // all, aktif, tamamlandi, pasif
let searchTimer = null

// Modal Durumu: Yeni Sözleşme / Düzenleme
const isModalOpen = ref(false)
const modalSubmitting = ref(false)
const modalError = ref('')
const editingContractId = ref(null)

const contractForm = ref({
  id: null,
  idare_adi: '',
  idare_baskanlik_adi: '',
  isin_adi: '',
  isin_yuklenicisi: '',
  ihale_kayit_no: '',
  kesif_bedeli: '',
  ihale_tenzilati: '0.168769',
  sozlesme_bedeli: '',
  sozlesme_tarihi: '',
  isin_bitecegi_tarih: '',
  isin_suresi: '',
  durum: 'aktif',
  a1_katsayisi: '0.28',
  b1_katsayisi: '0.22',
  b2_katsayisi: '0.25',
  c_katsayisi: '0.25',
  kdv_orani: '20.00',
  tevkifat_orani: '4/10',
  temel_endeks_ay: '',
  temel_endeks_yil: new Date().getFullYear(),
  asgari_ucret_temel: '',
  motorin_temel: '',
  ufe_genel_temel: '',
  makine_ekipman_temel: '',
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

async function loadContracts(resetPage = false) {
  if (resetPage) pagination.value.page = 1
  loading.value = true
  error.value = ''

  try {
    const res = await sozlesmeApi.listContracts({
      search: search.value,
      status: activeStatus.value,
      page: pagination.value.page,
      limit: pagination.value.limit,
    })

    if (res.success) {
      contracts.value = res.contracts || []
      if (res.summary) summary.value = res.summary
      if (res.pagination) pagination.value = res.pagination
    } else {
      error.value = res.message || 'Sözleşmeler yüklenemedi.'
    }
  } catch (err) {
    error.value = err.message || 'Bağlantı hatası oluştu.'
  } finally {
    loading.value = false
  }
}

watch(search, () => {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    loadContracts(true)
  }, 350)
})

watch(activeStatus, () => {
  loadContracts(true)
})

onMounted(() => {
  loadContracts(true)
})

function openNewContractModal() {
  editingContractId.value = null
  modalError.value = ''
  contractForm.value = {
    id: null,
    idare_adi: '',
    idare_baskanlik_adi: '',
    isin_adi: '',
    isin_yuklenicisi: auth.state.company?.name || 'ERSAN ELEKTRİK İNŞ. TİC. VE SAN. LTD. ŞTİ.',
    ihale_kayit_no: '',
    kesif_bedeli: '',
    ihale_tenzilati: '0.168769',
    sozlesme_bedeli: '',
    sozlesme_tarihi: '',
    isin_bitecegi_tarih: '',
    isin_suresi: '',
    durum: 'aktif',
    a1_katsayisi: '0.28',
    b1_katsayisi: '0.22',
    b2_katsayisi: '0.25',
    c_katsayisi: '0.25',
    kdv_orani: '20.00',
    tevkifat_orani: '4/10',
    temel_endeks_ay: '',
    temel_endeks_yil: new Date().getFullYear(),
    asgari_ucret_temel: '',
    motorin_temel: '',
    ufe_genel_temel: '',
    makine_ekipman_temel: '',
  }
  isModalOpen.value = true
}

function openEditContractModal(item, event) {
  event?.stopPropagation?.()
  editingContractId.value = item.id
  modalError.value = ''
  contractForm.value = {
    id: item.id,
    idare_adi: item.idare_adi || '',
    idare_baskanlik_adi: item.idare_baskanlik_adi || '',
    isin_adi: item.isin_adi || '',
    isin_yuklenicisi: item.isin_yuklenicisi || '',
    ihale_kayit_no: item.ihale_kayit_no || '',
    kesif_bedeli: item.kesif_bedeli || '',
    ihale_tenzilati: item.ihale_tenzilati || '',
    sozlesme_bedeli: item.sozlesme_bedeli || '',
    sozlesme_tarihi: item.sozlesme_tarihi || '',
    isin_bitecegi_tarih: item.isin_bitecegi_tarih || '',
    isin_suresi: item.isin_suresi || '',
    durum: item.durum || 'aktif',
    a1_katsayisi: '0.28',
    b1_katsayisi: '0.22',
    b2_katsayisi: '0.25',
    c_katsayisi: '0.25',
    kdv_orani: '20.00',
    tevkifat_orani: '4/10',
    temel_endeks_ay: item.temel_endeks_ay || '',
    temel_endeks_yil: item.temel_endeks_yil || new Date().getFullYear(),
    asgari_ucret_temel: '',
    motorin_temel: '',
    ufe_genel_temel: '',
    makine_ekipman_temel: '',
  }
  isModalOpen.value = true
}

async function submitContract() {
  if (!contractForm.value.idare_adi || !contractForm.value.isin_adi || !contractForm.value.sozlesme_bedeli) {
    modalError.value = 'Lütfen İdare Adı, İşin Adı ve Sözleşme Bedelini eksiksiz doldurun.'
    return
  }

  modalSubmitting.value = true
  modalError.value = ''

  try {
    const res = await sozlesmeApi.saveContract(contractForm.value, auth.state.csrfToken)
    if (res.success) {
      isModalOpen.value = false
      await loadContracts(false)
    } else {
      modalError.value = res.message || 'Sözleşme kaydedilemedi.'
    }
  } catch (err) {
    modalError.value = err.message || 'Kayıt sırasında hata oluştu.'
  } finally {
    modalSubmitting.value = false
  }
}

async function handleDeleteContract(item, event) {
  event?.stopPropagation?.()
  if (!confirm(`"${item.idare_adi}" sözleşmesini silmek istediğinize emin misiniz?`)) {
    return
  }

  try {
    const res = await sozlesmeApi.deleteContract(item.id, auth.state.csrfToken)
    if (res.success) {
      await loadContracts(false)
    } else {
      alert(res.message || 'Silme başarısız.')
    }
  } catch (err) {
    alert(err.message || 'Silme işlemi sırasında hata oluştu.')
  }
}

function goToDetail(item) {
  router.push(`/sozlesme-hakedis/${encodeURIComponent(item.id)}`)
}

function getStatusBadgeClass(durum) {
  switch (durum) {
    case 'aktif':
      return 'badge-status--aktif'
    case 'tamamlandi':
      return 'badge-status--tamamlandi'
    case 'pasif':
      return 'badge-status--pasif'
    default:
      return 'badge-status--neutral'
  }
}

function getStatusLabel(durum) {
  switch (durum) {
    case 'aktif':
      return 'Aktif'
    case 'tamamlandi':
      return 'Tamamlandı'
    case 'pasif':
      return 'Pasif'
    default:
      return durum
  }
}
</script>

<template>
  <div class="ds-screen sozlesme-screen">
    <!-- Üst TopBar -->
    <TopBar
      eyebrow="HAKEDİŞ & PROJE"
      title="Sözleşmeler"
      subtitle="Firma sözleşmeleri, keşif ve dönem hakedişleri"
    >
      <template #actions>
        <button
          type="button"
          class="clay-circle-btn"
          aria-label="Yeni Sözleşme Ekle"
          title="Yeni Sözleşme"
          @click="openNewContractModal"
        >
          <Plus :size="20" />
        </button>
      </template>
    </TopBar>

    <!-- 4 Minimal Bento KPI Kartı -->
    <section class="sozlesme-kpi-grid">
      <!-- 1. Toplam Sözleşme -->
      <div class="bento-kpi-card" :class="{ 'is-active': activeStatus === 'all' }" @click="activeStatus = 'all'">
        <div class="kpi-card-header">
          <span class="kpi-label">TOPLAM SÖZLEŞME</span>
          <div class="kpi-icon-pill bg-blue-soft">
            <Briefcase :size="16" class="text-blue" />
          </div>
        </div>
        <div class="kpi-value">{{ summary.toplam_sozlesme }}</div>
        <div class="kpi-subtext">Aktif: {{ summary.aktif_sayisi }} | Biten: {{ summary.tamamlanan_sayisi }}</div>
      </div>

      <!-- 2. Aktif Sözleşmeler -->
      <div class="bento-kpi-card" :class="{ 'is-active': activeStatus === 'aktif' }" @click="activeStatus = 'aktif'">
        <div class="kpi-card-header">
          <span class="kpi-label">AKTİF SÖZLEŞMELER</span>
          <div class="kpi-icon-pill bg-emerald-soft">
            <TrendingUp :size="16" class="text-emerald" />
          </div>
        </div>
        <div class="kpi-value text-emerald">{{ summary.aktif_bedel_formatted }}</div>
        <div class="kpi-subtext">{{ summary.aktif_sayisi }} Aktif Sözleşme</div>
      </div>

      <!-- 3. Tamamlananlar -->
      <div class="bento-kpi-card" :class="{ 'is-active': activeStatus === 'tamamlandi' }" @click="activeStatus = 'tamamlandi'">
        <div class="kpi-card-header">
          <span class="kpi-label">TAMAMLANANLAR</span>
          <div class="kpi-icon-pill bg-indigo-soft">
            <CheckCircle2 :size="16" class="text-indigo" />
          </div>
        </div>
        <div class="kpi-value text-indigo">{{ summary.tamamlanan_bedel_formatted }}</div>
        <div class="kpi-subtext">{{ summary.tamamlanan_sayisi }} Biten Sözleşme</div>
      </div>

      <!-- 4. Toplam Hacim -->
      <div class="bento-kpi-card">
        <div class="kpi-card-header">
          <span class="kpi-label">TOPLAM HACİM</span>
          <div class="kpi-icon-pill bg-amber-soft">
            <Wallet :size="16" class="text-amber" />
          </div>
        </div>
        <div class="kpi-value">{{ summary.toplam_bedel_formatted }}</div>
        <div class="kpi-subtext">Genel Portföy Bedeli</div>
      </div>
    </section>

    <!-- Arama ve Durum Filtreleri -->
    <section class="sozlesme-filter-bar">
      <div class="clay-search-wrap">
        <Search :size="16" class="search-icon" />
        <input
          v-model="search"
          type="text"
          class="clay-search-input"
          placeholder="İdare, iş adı veya ihale no ile ara..."
        />
        <button v-if="search" type="button" class="search-clear-btn" @click="search = ''">
          <X :size="14" />
        </button>
      </div>

      <div class="status-pills-row">
        <button
          type="button"
          class="clay-filter-pill"
          :class="{ 'is-active': activeStatus === 'all' }"
          @click="activeStatus = 'all'"
        >
          Tümü ({{ summary.toplam_sozlesme }})
        </button>
        <button
          type="button"
          class="clay-filter-pill"
          :class="{ 'is-active': activeStatus === 'aktif' }"
          @click="activeStatus = 'aktif'"
        >
          <span class="dot-aktif"></span>
          Aktif ({{ summary.aktif_sayisi }})
        </button>
        <button
          type="button"
          class="clay-filter-pill"
          :class="{ 'is-active': activeStatus === 'tamamlandi' }"
          @click="activeStatus = 'tamamlandi'"
        >
          <span class="dot-tamamlandi"></span>
          Tamamlandı ({{ summary.tamamlanan_sayisi }})
        </button>
        <button
          v-if="summary.pasif_sayisi > 0"
          type="button"
          class="clay-filter-pill"
          :class="{ 'is-active': activeStatus === 'pasif' }"
          @click="activeStatus = 'pasif'"
        >
          Pasif ({{ summary.pasif_sayisi }})
        </button>
      </div>
    </section>

    <!-- Ana Sözleşme Kartları Listesi -->
    <main class="sozlesme-list-container">
      <!-- Yükleniyor Durumu -->
      <div v-if="loading" class="ds-loading">
        <i v-for="n in 3" :key="n" style="height: 140px; border-radius: var(--ds-radius-card);"></i>
      </div>

      <!-- Hata Durumu -->
      <div v-else-if="error" class="ds-state error-state">
        <AlertTriangle :size="28" />
        <strong>Kayıtlar Yüklenemedi</strong>
        <p>{{ error }}</p>
        <button type="button" class="btn-retry" @click="loadContracts(true)">
          <RefreshCw :size="16" /> Tekrar Dene
        </button>
      </div>

      <!-- Boş Durum -->
      <div v-else-if="!contracts.length" class="ds-state">
        <FileSpreadsheet :size="36" />
        <strong>Sözleşme Bulunamadı</strong>
        <p v-if="search || activeStatus !== 'all'">Arama veya filtre kriterlerinizi değiştirin.</p>
        <p v-else>Firma adına kayıtlı aktif sözleşme bulunmuyor. Yeni bir sözleşme oluşturarak başlayın.</p>
        <button type="button" class="btn-create-empty" @click="openNewContractModal">
          <Plus :size="16" /> + Yeni Sözleşme
        </button>
      </div>

      <!-- Sözleşme Bento Kartları -->
      <div v-else class="contract-cards-stack">
        <article
          v-for="contract in contracts"
          :key="contract.id"
          class="bento-contract-card"
          role="button"
          tabindex="0"
          @click="goToDetail(contract)"
          @keydown.enter="goToDetail(contract)"
        >
          <div class="contract-card-top">
            <div class="contract-idare-block">
              <span class="contract-eyebrow">
                <Building2 :size="12" /> {{ contract.idare_adi }}
              </span>
              <h3 class="contract-title">{{ contract.isin_adi }}</h3>
            </div>
            <span class="badge-status" :class="getStatusBadgeClass(contract.durum)">
              {{ getStatusLabel(contract.durum) }}
            </span>
          </div>

          <div class="contract-amount-row">
            <div class="amount-group">
              <span class="amount-label">SÖZLEŞME BEDELİ</span>
              <span class="amount-value text-primary-dark">{{ contract.sozlesme_bedeli_formatted }}</span>
            </div>
            <div v-if="contract.hakedis_sayisi > 0" class="hakedis-stat-pill">
              <span class="h-count">{{ contract.hakedis_sayisi }} Hakediş</span>
              <span class="h-sum">{{ contract.toplam_hakedis_tutari_formatted }}</span>
            </div>
          </div>

          <div class="contract-card-footer">
            <div class="contract-meta-chips">
              <span v-if="contract.isin_bitecegi_tarih" class="meta-chip">
                <Calendar :size="12" /> Bitiş: {{ contract.isin_bitecegi_tarih }}
              </span>
              <span v-if="contract.isin_suresi" class="meta-chip">
                <Clock :size="12" /> {{ contract.isin_suresi }} Gün
              </span>
              <span v-if="contract.ihale_kayit_no" class="meta-chip">
                İKN: {{ contract.ihale_kayit_no }}
              </span>
            </div>

            <div class="contract-actions-inline" @click.stop>
              <button
                type="button"
                class="action-icon-btn"
                title="Düzenle"
                aria-label="Sözleşmeyi Düzenle"
                @click="openEditContractModal(contract, $event)"
              >
                <Pencil :size="15" />
              </button>
              <button
                type="button"
                class="action-icon-btn text-danger"
                title="Sil"
                aria-label="Sözleşmeyi Sil"
                @click="handleDeleteContract(contract, $event)"
              >
                <Trash2 :size="15" />
              </button>
              <div class="btn-chevron-wrap">
                <ChevronRight :size="18" class="text-muted" />
              </div>
            </div>
          </div>
        </article>
      </div>
    </main>

    <!-- Yeni / Düzenle Sözleşme Bottom Sheet (Modal) -->
    <Transition name="ds-modal">
      <div v-if="isModalOpen" class="ds-modal-layer" @click.self="isModalOpen = false">
        <div class="ds-bottom-sheet ds-bottom-sheet--large" role="dialog" aria-modal="true">
          <div class="sheet-header">
            <div class="sheet-title">
              <div class="sheet-icon-box bg-blue-soft text-blue">
                <Pencil v-if="editingContractId" :size="18" />
                <Plus v-else :size="18" />
              </div>
              <div>
                <h3>{{ editingContractId ? 'Sözleşmeyi Düzenle' : 'Yeni Sözleşme Ekle' }}</h3>
                <small>Firma sözleşme ve keşif bilgileri</small>
              </div>
            </div>
            <button type="button" class="sheet-close-btn" @click="isModalOpen = false">
              <X :size="18" />
            </button>
          </div>

          <form class="sheet-form" @submit.prevent="submitContract">
            <div v-if="modalError" class="modal-alert-error">
              <AlertTriangle :size="16" />
              <span>{{ modalError }}</span>
            </div>

            <div class="form-section-title">Genel Bilgiler</div>
            <div class="form-grid">
              <div class="form-field full-width">
                <label>İdare Adı <span class="req">*</span></label>
                <input
                  v-model="contractForm.idare_adi"
                  type="text"
                  required
                  placeholder="Örn: KAHRAMANMARAŞ SU VE KANALİZASYON İDARESİ"
                />
              </div>

              <div class="form-field full-width">
                <label>İşin Adı <span class="req">*</span></label>
                <textarea
                  v-model="contractForm.isin_adi"
                  rows="2"
                  required
                  placeholder="Örn: Mobil Okuma ve Sayaç Değişim Hizmeti..."
                ></textarea>
              </div>

              <div class="form-field">
                <label>İşin Yüklenicisi</label>
                <input v-model="contractForm.isin_yuklenicisi" type="text" placeholder="Yüklenici firma" />
              </div>

              <div class="form-field">
                <label>İhale Kayıt No (İKN)</label>
                <input v-model="contractForm.ihale_kayit_no" type="text" placeholder="Örn: 2025/12345" />
              </div>

              <div class="form-field">
                <label>Durum</label>
                <select v-model="contractForm.durum">
                  <option value="aktif">Aktif</option>
                  <option value="tamamlandi">Tamamlandı</option>
                  <option value="pasif">Pasif</option>
                </select>
              </div>

              <div class="form-field">
                <label>İşin Süresi (Gün)</label>
                <input v-model="contractForm.isin_suresi" type="number" placeholder="Örn: 730" />
              </div>
            </div>

            <div class="form-section-title">Mali Bilgiler ve Tarihler</div>
            <div class="form-grid">
              <div class="form-field">
                <label>Sözleşme Bedeli (TL) <span class="req">*</span></label>
                <input
                  v-model="contractForm.sozlesme_bedeli"
                  type="number"
                  step="0.01"
                  required
                  placeholder="0.00"
                />
              </div>

              <div class="form-field">
                <label>Keşif Bedeli (TL)</label>
                <input v-model="contractForm.kesif_bedeli" type="number" step="0.01" placeholder="0.00" />
              </div>

              <div class="form-field">
                <label>İhale Tenzilatı (%)</label>
                <input v-model="contractForm.ihale_tenzilati" type="number" step="0.000001" placeholder="0.168769" />
              </div>

              <div class="form-field">
                <label>KDV Oranı (%)</label>
                <input v-model="contractForm.kdv_orani" type="number" step="0.01" placeholder="20.00" />
              </div>

              <div class="form-field">
                <label>Sözleşme Tarihi</label>
                <input v-model="contractForm.sozlesme_tarihi" type="date" />
              </div>

              <div class="form-field">
                <label>İşin Bitiş Tarihi</label>
                <input v-model="contractForm.isin_bitecegi_tarih" type="date" />
              </div>
            </div>

            <div class="form-section-title">Fiyat Farkı Katsayıları & Endeksler</div>
            <div class="form-grid">
              <div class="form-field">
                <label>a1 (İşçilik) Katsayısı</label>
                <input v-model="contractForm.a1_katsayisi" type="number" step="0.000001" placeholder="0.28" />
              </div>
              <div class="form-field">
                <label>b1 (Motorin) Katsayısı</label>
                <input v-model="contractForm.b1_katsayisi" type="number" step="0.000001" placeholder="0.22" />
              </div>
              <div class="form-field">
                <label>b2 (Yİ-ÜFE) Katsayısı</label>
                <input v-model="contractForm.b2_katsayisi" type="number" step="0.000001" placeholder="0.25" />
              </div>
              <div class="form-field">
                <label>c (Makine) Katsayısı</label>
                <input v-model="contractForm.c_katsayisi" type="number" step="0.000001" placeholder="0.25" />
              </div>
              <div class="form-field">
                <label>Tevkifat Oranı</label>
                <input v-model="contractForm.tevkifat_orani" type="text" placeholder="4/10" />
              </div>
              <div class="form-field">
                <label>Temel Endeks Ayı</label>
                <select v-model="contractForm.temel_endeks_ay">
                  <option value="">Seçiniz</option>
                  <option v-for="a in aylar" :key="a.val" :value="a.val">{{ a.label }}</option>
                </select>
              </div>
            </div>

            <div class="sheet-actions">
              <button type="button" class="btn-cancel" @click="isModalOpen = false">Vazgeç</button>
              <button type="submit" class="btn-submit" :disabled="modalSubmitting">
                <span v-if="modalSubmitting">Kaydediliyor...</span>
                <span v-else>{{ editingContractId ? 'Güncelle' : 'Sözleşmeyi Oluştur' }}</span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.sozlesme-screen {
  padding-bottom: calc(var(--ds-nav-clearance) + 20px);
}

/* 4 Minimal Bento KPI Kartı */
.sozlesme-kpi-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
  margin-bottom: 16px;
}

.bento-kpi-card {
  background: var(--ds-surface);
  border-radius: var(--ds-radius-card);
  padding: 14px 16px;
  box-shadow: var(--ds-shadow-bento);
  border: 1px solid var(--ds-border);
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  cursor: pointer;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.bento-kpi-card:hover {
  transform: translateY(-2px);
}

.bento-kpi-card.is-active {
  border-color: var(--ds-blue);
  background: #fdfefe;
}

.kpi-card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 6px;
}

.kpi-label {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.5px;
  color: var(--ds-muted);
}

.kpi-icon-pill {
  width: 28px;
  height: 28px;
  border-radius: var(--ds-radius-sm);
  display: flex;
  align-items: center;
  justify-content: center;
}

.kpi-value {
  font-size: 19px;
  font-weight: 700;
  color: var(--ds-ink);
  line-height: 1.2;
}

.kpi-subtext {
  font-size: 11px;
  color: var(--ds-muted);
  margin-top: 4px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* Renk Sınıfları */
.bg-blue-soft { background: var(--ds-blue-soft); }
.text-blue { color: var(--ds-blue-ink); }
.bg-emerald-soft { background: var(--ds-emerald-soft); }
.text-emerald { color: var(--ds-emerald-ink); }
.bg-indigo-soft { background: var(--ds-indigo-soft); }
.text-indigo { color: var(--ds-indigo-ink); }
.bg-amber-soft { background: var(--ds-amber-soft); }
.text-amber { color: var(--ds-amber-ink); }

/* Filtre ve Arama Çubuğu */
.sozlesme-filter-bar {
  margin-bottom: 16px;
  display: flex;
  flex-direction: column;
  gap: 10px;
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

.clay-search-input:focus {
  border-color: var(--ds-blue);
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

.status-pills-row {
  display: flex;
  gap: 8px;
  overflow-x: auto;
  padding-bottom: 2px;
  scrollbar-width: none;
}
.status-pills-row::-webkit-scrollbar { display: none; }

.clay-filter-pill {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  height: 34px;
  padding: 0 14px;
  background: var(--ds-surface);
  border: 1px solid var(--ds-border-subtle);
  border-radius: var(--ds-radius-pill);
  font-size: 13px;
  font-weight: 600;
  color: var(--ds-muted);
  cursor: pointer;
  box-shadow: var(--ds-shadow-pill);
  transition: all 0.15s ease;
}

.clay-filter-pill.is-active {
  background: var(--ds-primary);
  color: var(--ds-ink-light);
  border-color: var(--ds-primary);
}

.dot-aktif { width: 8px; height: 8px; border-radius: 50%; background: var(--ds-emerald); }
.dot-tamamlandi { width: 8px; height: 8px; border-radius: 50%; background: var(--ds-blue); }

/* Sözleşme Bento Kartları */
.contract-cards-stack {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.bento-contract-card {
  background: var(--ds-surface);
  border-radius: var(--ds-radius-card);
  padding: 16px;
  border: 1px solid var(--ds-border);
  box-shadow: var(--ds-shadow-bento);
  cursor: pointer;
  display: flex;
  flex-direction: column;
  gap: 12px;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.bento-contract-card:hover {
  transform: translateY(-2px);
  box-shadow: var(--ds-shadow-float);
}

.contract-card-top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 10px;
}

.contract-idare-block {
  min-width: 0;
  flex: 1;
}

.contract-eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  color: var(--ds-blue-ink);
  letter-spacing: 0.3px;
  margin-bottom: 4px;
}

.contract-title {
  font-size: 15px;
  font-weight: 700;
  color: var(--ds-ink);
  line-height: 1.35;
  margin: 0;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.badge-status {
  padding: 4px 10px;
  border-radius: var(--ds-radius-pill);
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  flex-shrink: 0;
}

.badge-status--aktif {
  background: var(--ds-emerald-soft);
  color: var(--ds-emerald-ink);
  border: 1px solid rgba(16, 185, 129, 0.25);
}

.badge-status--tamamlandi {
  background: var(--ds-indigo-soft);
  color: var(--ds-indigo-ink);
  border: 1px solid rgba(99, 102, 241, 0.25);
}

.badge-status--pasif {
  background: var(--ds-surface-subtle);
  color: var(--ds-muted);
  border: 1px solid var(--ds-border-subtle);
}

.contract-amount-row {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  background: var(--ds-surface-subtle);
  border-radius: var(--ds-radius-sm);
  padding: 10px 14px;
}

.amount-group {
  display: flex;
  flex-direction: column;
}

.amount-label {
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.5px;
  color: var(--ds-muted);
}

.amount-value {
  font-size: 18px;
  font-weight: 800;
  color: #0f172a;
}

.hakedis-stat-pill {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
}

.h-count {
  font-size: 11px;
  font-weight: 700;
  color: var(--ds-blue-ink);
}

.h-sum {
  font-size: 12px;
  font-weight: 600;
  color: var(--ds-muted);
}

.contract-card-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding-top: 4px;
}

.contract-meta-chips {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
}

.meta-chip {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  color: var(--ds-muted);
  background: var(--ds-surface-subtle);
  padding: 3px 8px;
  border-radius: var(--ds-radius-pill);
}

.contract-actions-inline {
  display: flex;
  align-items: center;
  gap: 4px;
  flex-shrink: 0;
}

.action-icon-btn {
  width: 32px;
  height: 32px;
  border-radius: var(--ds-radius-sm);
  border: 1px solid var(--ds-border-subtle);
  background: var(--ds-surface-subtle);
  color: var(--ds-ink);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.15s ease;
}

.action-icon-btn:hover {
  background: #e2e8f0;
}

.btn-chevron-wrap {
  width: 24px;
  height: 24px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

/* Modal / Bottom Sheet */
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

@keyframes slideUp {
  from { transform: translateY(100%); }
  to { transform: translateY(0); }
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

.sheet-title h3 {
  font-size: 17px;
  font-weight: 700;
  margin: 0;
}

.sheet-title small {
  font-size: 12px;
  color: var(--ds-muted);
}

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

.form-section-title {
  font-size: 13px;
  font-weight: 700;
  text-transform: uppercase;
  color: var(--ds-blue-ink);
  letter-spacing: 0.5px;
  margin-top: 6px;
  border-bottom: 1px solid var(--ds-border-subtle);
  padding-bottom: 4px;
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

.form-field.full-width {
  grid-column: span 2;
}

.form-field label {
  font-size: 12px;
  font-weight: 600;
  color: var(--ds-muted);
}

.form-field label .req {
  color: var(--ds-danger);
}

.form-field input,
.form-field select,
.form-field textarea {
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

.form-field textarea {
  height: auto;
  padding: 8px 12px;
}

.form-field input:focus,
.form-field select:focus,
.form-field textarea:focus {
  border-color: var(--ds-blue);
  background: var(--ds-surface);
}

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

.btn-submit:disabled {
  opacity: 0.6;
}

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

.ds-state strong {
  font-size: 16px;
  color: var(--ds-ink);
}

.btn-create-empty,
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
