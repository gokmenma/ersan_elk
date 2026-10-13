<script setup>
import { computed, onMounted, ref } from 'vue'
import {
  ArrowUpDown,
  Building2,
  Calendar,
  CalendarCheck,
  CalendarDays,
  Check,
  CheckCircle2,
  ChevronDown,
  ChevronRight,
  Clock3,
  FileCheck2,
  Filter,
  History,
  Info,
  Layers,
  MessageSquare,
  RefreshCw,
  Search,
  Sparkles,
  Trash2,
  User,
  Wallet,
  X,
  XCircle,
  Zap,
} from '@lucide/vue'
import TopBar from '../components/TopBar.vue'
import { requestsApi } from '../services/requests.js'

// State
const activeTab = ref('avans') // 'avans' | 'izin' | 'talep'
const activeFilter = ref('pending') // 'pending' | 'approved' | 'deleted'
const sortBy = ref('newest') // 'newest' | 'oldest' | 'name_asc' | 'name_desc' | 'amount_desc'
const searchQuery = ref('')
const loading = ref(true)
const actionBusy = ref(false)
const errorMessage = ref('')
const successToast = ref('')

const counts = ref({ avans: 0, izin: 0, talep: 0, total: 0 })
const permissions = ref({ avans: true, izin: true, talep: true })
const items = ref({ avans: [], izin: [], talep: [] })

// Modallar
const modalType = ref(null) // 'avansOnay' | 'avansRed' | 'izinOnay' | 'izinRed' | 'talepCoz' | 'sil' | 'detail'
const activeItem = ref(null)

// Form Modelleri
const formAvansOnay = ref({
  onay_tipi: 'ayni',
  farkli_tutar: '',
  aciklama: '',
  hesaba_isle: true,
})

const formAvansRed = ref({ aciklama: '' })
const formIzinOnay = ref({
  onay_tipi: 'ayni',
  farkli_baslangic: '',
  farkli_bitis: '',
  aciklama: '',
})
const formIzinRed = ref({ aciklama: '' })
const formTalepCoz = ref({ aciklama: '' })
const formSil = ref({ aciklama: '' })

function showToast(msg) {
  successToast.value = msg
  setTimeout(() => {
    if (successToast.value === msg) successToast.value = ''
  }, 3500)
}

async function loadData() {
  loading.value = true
  errorMessage.value = ''
  try {
    const res = await requestsApi.getRequests({ tab: activeTab.value, filter: activeFilter.value })
    if (res.success) {
      counts.value = res.counts || { avans: 0, izin: 0, talep: 0, total: 0 }
      permissions.value = res.permissions || { avans: true, izin: true, talep: true }
      items.value = res.data || { avans: [], izin: [], talep: [] }

      if (!permissions.value[activeTab.value]) {
        if (permissions.value.avans) activeTab.value = 'avans'
        else if (permissions.value.izin) activeTab.value = 'izin'
        else if (permissions.value.talep) activeTab.value = 'talep'
      }
    }
  } catch (err) {
    errorMessage.value = err.message || 'Talepler yüklenemedi.'
  } finally {
    loading.value = false
  }
}

onMounted(loadData)

async function setTab(tab) {
  activeTab.value = tab
  await loadData()
}

function setFilter(filt) {
  activeFilter.value = filt
  loadData()
}

// Filtre ve Sıralama
const currentList = computed(() => {
  const list = items.value[activeTab.value] || []
  return list.filter((item) => {
    if (!searchQuery.value.trim()) return true
    const q = searchQuery.value.toLowerCase()
    return (
      item.name?.toLowerCase().includes(q) ||
      item.department?.toLowerCase().includes(q) ||
      item.description?.toLowerCase().includes(q) ||
      item.title?.toLowerCase().includes(q)
    )
  }).sort((a, b) => {
    if (sortBy.value === 'newest') return (b.timestamp || 0) - (a.timestamp || 0)
    if (sortBy.value === 'oldest') return (a.timestamp || 0) - (b.timestamp || 0)
    if (sortBy.value === 'name_asc') return (a.name || '').localeCompare(b.name || '', 'tr')
    if (sortBy.value === 'name_desc') return (b.name || '').localeCompare(a.name || '', 'tr')
    if (sortBy.value === 'amount_desc') return (b.amount || 0) - (a.amount || 0)
    return 0
  })
})

// Modal Açıcılar
function openAvansOnayModal(item) {
  activeItem.value = item
  formAvansOnay.value = {
    onay_tipi: 'ayni',
    farkli_tutar: '',
    aciklama: '',
    hesaba_isle: true,
  }
  modalType.value = 'avansOnay'
}

function openAvansRedModal(item) {
  activeItem.value = item
  formAvansRed.value = { aciklama: '' }
  modalType.value = 'avansRed'
}

function openIzinOnayModal(item) {
  activeItem.value = item
  formIzinOnay.value = {
    onay_tipi: 'ayni',
    farkli_baslangic: item.start_date_raw || '',
    farkli_bitis: item.end_date_raw || '',
    aciklama: '',
  }
  modalType.value = 'izinOnay'
}

function openIzinRedModal(item) {
  activeItem.value = item
  formIzinRed.value = { aciklama: '' }
  modalType.value = 'izinRed'
}

function openTalepCozModal(item) {
  activeItem.value = item
  formTalepCoz.value = { aciklama: '' }
  modalType.value = 'talepCoz'
}

function openSilModal(item) {
  activeItem.value = item
  formSil.value = { aciklama: '' }
  modalType.value = 'sil'
}

function openDetailModal(item) {
  activeItem.value = item
  modalType.value = 'detail'
}

function closeModal() {
  modalType.value = null
  activeItem.value = null
}

// API Çağrıları
async function submitAvansOnay() {
  actionBusy.value = true
  try {
    const res = await requestsApi.avansOnayla({ id: activeItem.value.id, ...formAvansOnay.value })
    showToast(res.message || 'Avans onaylandı.')
    closeModal()
    await loadData()
  } catch (err) {
    alert(err.message || 'İşlem tamamlanamadı.')
  } finally {
    actionBusy.value = false
  }
}

async function submitAvansRed() {
  if (!formAvansRed.value.aciklama.trim()) {
    alert('Reddetme gerekçesi zorunludur.')
    return
  }
  actionBusy.value = true
  try {
    const res = await requestsApi.avansReddet({ id: activeItem.value.id, aciklama: formAvansRed.value.aciklama })
    showToast(res.message || 'Avans reddedildi.')
    closeModal()
    await loadData()
  } catch (err) {
    alert(err.message || 'İşlem tamamlanamadı.')
  } finally {
    actionBusy.value = false
  }
}

async function submitIzinOnay() {
  actionBusy.value = true
  try {
    const res = await requestsApi.izinOnayla({ id: activeItem.value.id, ...formIzinOnay.value })
    showToast(res.message || 'İzin onaylandı.')
    closeModal()
    await loadData()
  } catch (err) {
    alert(err.message || 'İşlem tamamlanamadı.')
  } finally {
    actionBusy.value = false
  }
}

async function submitIzinRed() {
  if (!formIzinRed.value.aciklama.trim()) {
    alert('Red açıklaması zorunludur.')
    return
  }
  actionBusy.value = true
  try {
    const res = await requestsApi.izinReddet({ id: activeItem.value.id, aciklama: formIzinRed.value.aciklama })
    showToast(res.message || 'İzin reddedildi.')
    closeModal()
    await loadData()
  } catch (err) {
    alert(err.message || 'İşlem tamamlanamadı.')
  } finally {
    actionBusy.value = false
  }
}

async function submitTalepIslemeAl(item) {
  actionBusy.value = true
  try {
    const res = await requestsApi.talepIslemeAl({ id: item.id })
    showToast(res.message || 'Talep işleme alındı.')
    await loadData()
  } catch (err) {
    alert(err.message || 'İşlem tamamlanamadı.')
  } finally {
    actionBusy.value = false
  }
}

async function submitTalepCoz() {
  actionBusy.value = true
  try {
    const res = await requestsApi.talepCozuldu({ id: activeItem.value.id, aciklama: formTalepCoz.value.aciklama })
    showToast(res.message || 'Talep çözüldü olarak işaretlendi.')
    closeModal()
    await loadData()
  } catch (err) {
    alert(err.message || 'İşlem tamamlanamadı.')
  } finally {
    actionBusy.value = false
  }
}

async function submitSil() {
  if (!formSil.value.aciklama.trim()) {
    alert('Silme gerekçesi belirtmelisiniz.')
    return
  }
  actionBusy.value = true
  try {
    const res = await requestsApi.sil({ type: activeItem.value.type, id: activeItem.value.id, aciklama: formSil.value.aciklama })
    showToast(res.message || 'Kayıt silindi.')
    closeModal()
    await loadData()
  } catch (err) {
    alert(err.message || 'İşlem tamamlanamadı.')
  } finally {
    actionBusy.value = false
  }
}
</script>

<template>
  <section class="screen ds-screen ds-requests talepler-screen">
    <!-- Standart TopBar: Ana Sayfa ile Tam Uyumlu -->
    <TopBar eyebrow="SÜREÇ YÖNETİMİ" title="Talepler" subtitle="Onay bekleyen tüm işlemler" :search="false" />

    <!-- 1. Minimal Bento KPI & Kategori Seçimi -->
    <section class="hero-card--talepler">
      <div class="talepler-kpi-row">
        <div class="kpi-main">
          <span class="kpi-label"><Sparkles :size="11" /> BEKLEYEN</span>
          <div class="kpi-value-row">
            <strong>{{ counts.total }}</strong>
            <small>talep</small>
          </div>
        </div>

        <div class="kpi-mini-grid" role="tablist" aria-label="Talep Kategorileri">
          <button
            v-if="permissions.avans"
            type="button"
            role="tab"
            :aria-selected="activeTab === 'avans'"
            class="kpi-mini-item"
            :class="{ 'is-active': activeTab === 'avans' }"
            @click="setTab('avans')"
          >
            <span class="kpi-mini-label"><Wallet :size="12" /> Avans</span>
            <strong>{{ counts.avans }}</strong>
          </button>
          <button
            v-if="permissions.izin"
            type="button"
            role="tab"
            :aria-selected="activeTab === 'izin'"
            class="kpi-mini-item"
            :class="{ 'is-active': activeTab === 'izin' }"
            @click="setTab('izin')"
          >
            <span class="kpi-mini-label"><CalendarDays :size="12" /> İzin</span>
            <strong>{{ counts.izin }}</strong>
          </button>
          <button
            v-if="permissions.talep"
            type="button"
            role="tab"
            :aria-selected="activeTab === 'talep'"
            class="kpi-mini-item"
            :class="{ 'is-active': activeTab === 'talep' }"
            @click="setTab('talep')"
          >
            <span class="kpi-mini-label"><Layers :size="12" /> Genel</span>
            <strong>{{ counts.talep }}</strong>
          </button>
        </div>
      </div>
    </section>

    <!-- 3. Durum Filtresi Pilleri (Bekleyenler / İşlem Görenler / Silinenler) -->
    <div class="status-pills-bar">
      <div class="status-pills-group">
        <button
          type="button"
          class="pill-btn"
          :class="{ active: activeFilter === 'pending' }"
          @click="setFilter('pending')"
        >
          <Clock3 :size="13" />
          <span>Bekleyenler</span>
        </button>
        <button
          type="button"
          class="pill-btn"
          :class="{ active: activeFilter === 'approved' }"
          @click="setFilter('approved')"
        >
          <CheckCircle2 :size="13" />
          <span>İşlem Görenler</span>
        </button>
        <button
          type="button"
          class="pill-btn pill-btn--danger"
          :class="{ active: activeFilter === 'deleted' }"
          @click="setFilter('deleted')"
        >
          <Trash2 :size="13" />
          <span>Silinenler</span>
        </button>
      </div>

      <!-- Sıralama Seçimi (Sadece İkon) -->
      <div class="sort-dropdown-wrap" title="Sıralama">
        <ArrowUpDown :size="15" class="sort-icon" />
        <select v-model="sortBy" aria-label="Sıralama">
          <option value="newest">En Yeni</option>
          <option value="oldest">En Eski</option>
          <option value="name_asc">İsim (A-Z)</option>
          <option value="name_desc">İsim (Z-A)</option>
          <option v-if="activeTab === 'avans'" value="amount_desc">Tutar</option>
        </select>
      </div>
    </div>

    <!-- 4. Canlı Arama Inputu -->
    <div class="search-box talepler-search">
      <Search :size="16" />
      <input
        v-model="searchQuery"
        placeholder="Personel adı, departman veya açıklama ile ara..."
        aria-label="Taleplerde ara"
      />
      <button v-if="searchQuery" type="button" @click="searchQuery = ''">
        <X :size="15" />
      </button>
    </div>

    <!-- Başarı Bildirimi Toast -->
    <Transition name="fade">
      <div v-if="successToast" class="toast-floating">
        <CheckCircle2 :size="16" />
        <span>{{ successToast }}</span>
      </div>
    </Transition>

    <!-- 5. Liste Alanı / Bento Cards -->
    <div v-if="loading" class="dashboard-loading">
      <i></i><i></i><i></i>
    </div>

    <div v-else-if="currentList.length === 0" class="empty-state-card">
      <div class="empty-icon-bubble" :class="{ 'is-pending': activeFilter === 'pending' }">
        <CheckCircle2 v-if="activeFilter === 'pending'" :size="30" />
        <Info v-else :size="30" />
      </div>
      <h3>
        {{
          activeFilter === 'pending'
            ? (activeTab === 'avans' ? 'Bekleyen Avans Yok' : activeTab === 'izin' ? 'Bekleyen İzin Yok' : 'Bekleyen Talep Yok')
            : (activeFilter === 'deleted' ? 'Silinmiş Kayıt Bulunmuyor' : 'İşlem Görmüş Kayıt Yok')
        }}
      </h3>
      <p>
        {{
          activeFilter === 'pending'
            ? 'Şu an onay bekleyen herhangi bir işlem bulunmuyor.'
            : 'Filtreleme kriterlerinize uygun talep kaydı bulunamadı.'
        }}
      </p>
      <button type="button" class="btn-subtle-refresh" @click="loadData">
        <RefreshCw :size="14" /> Yenile
      </button>
    </div>

    <div v-else class="talepler-list">
      <article
        v-for="item in currentList"
        :key="`${item.type}-${item.id}`"
        class="t-card"
        @click="openDetailModal(item)"
      >
        <!-- Üst Satır: Personel & Rozet -->
        <div class="t-card__top">
          <div class="t-card__profile">
            <div class="t-card__avatar">
              <img v-if="item.avatar" :src="item.avatar" :alt="item.name" />
              <span v-else>{{ item.name?.charAt(0) || 'P' }}</span>
            </div>
            <div class="t-card__meta">
              <h4>{{ item.name }}</h4>
              <p>
                <span>{{ item.department }}</span>
                <em v-if="item.type === 'izin'">· {{ item.leave_type }}</em>
                <em v-if="item.type === 'talep'">· {{ item.priority }}</em>
              </p>
            </div>
          </div>

          <!-- Sağ Vurgu (Tutar veya Gün) -->
          <div class="t-card__badge-wrap">
            <span v-if="item.type === 'avans'" class="badge-amount">
              {{ item.amount_formatted }}
            </span>
            <span v-else-if="item.type === 'izin'" class="badge-days">
              {{ item.days }} Gün
            </span>

            <span
              v-if="activeFilter === 'pending'"
              class="status-chip status-chip--pending"
            >
              Beklemede
            </span>
            <span
              v-else
              class="status-chip"
              :class="{
                'status-chip--approved': item.status?.toLowerCase().includes('onay') || item.status?.toLowerCase().includes('cozul'),
                'status-chip--rejected': item.status?.toLowerCase().includes('red'),
                'status-chip--deleted': activeFilter === 'deleted',
              }"
            >
              {{ item.status }}
            </span>
          </div>
        </div>

        <!-- Gövde: Talep Açıklaması ve Zaman -->
        <div class="t-card__body">
          <h5 v-if="item.type === 'talep'" class="talep-heading">{{ item.title }}</h5>

          <p v-if="item.description" class="t-card__quote">
            “{{ item.description }}”
          </p>

          <div class="t-card__details-row">
            <span class="detail-tag">
              <Clock3 :size="12" /> {{ item.date }}
            </span>
            <span v-if="item.type === 'izin' && item.start_date" class="detail-tag">
              <Calendar :size="12" /> {{ item.start_date }} - {{ item.end_date }}
            </span>
          </div>

          <!-- Çözülen / Onaylanan Detayı -->
          <div v-if="activeFilter !== 'pending' && (item.solver_name || item.approval_date)" class="solver-bar">
            <span><User :size="12" /> {{ item.solver_name || 'Yönetici' }}</span>
            <span v-if="item.approval_date">· {{ item.approval_date }}</span>
          </div>
        </div>

        <!-- Aksiyon Araç Çubuğu (Yalnızca Bekleyenlerde) -->
        <div v-if="activeFilter === 'pending'" class="t-card__actions" @click.stop>
          <!-- Avans Butonları -->
          <template v-if="item.type === 'avans'">
            <button
              type="button"
              class="act-btn act-btn--reject"
              @click="openAvansRedModal(item)"
            >
              <X :size="14" />
              <span>Reddet</span>
            </button>
            <button
              type="button"
              class="act-btn act-btn--approve"
              @click="openAvansOnayModal(item)"
            >
              <Check :size="14" />
              <span>Onayla</span>
            </button>
          </template>

          <!-- İzin Butonları -->
          <template v-else-if="item.type === 'izin'">
            <button
              type="button"
              class="act-btn act-btn--reject"
              @click="openIzinRedModal(item)"
            >
              <X :size="14" />
              <span>Reddet</span>
            </button>
            <button
              type="button"
              class="act-btn act-btn--primary"
              @click="openIzinOnayModal(item)"
            >
              <Check :size="14" />
              <span>Onayla</span>
            </button>
          </template>

          <!-- Saha Talep Butonları -->
          <template v-else-if="item.type === 'talep'">
            <button
              v-if="item.status !== 'islemde'"
              type="button"
              class="act-btn act-btn--neutral"
              :disabled="actionBusy"
              @click="submitTalepIslemeAl(item)"
            >
              <Clock3 :size="14" />
              <span>İşleme Al</span>
            </button>
            <button
              type="button"
              class="act-btn act-btn--purple"
              @click="openTalepCozModal(item)"
            >
              <Check :size="14" />
              <span>Çözüldü</span>
            </button>
          </template>

          <!-- Hızlı Sil Butonu -->
          <button
            type="button"
            class="act-btn-delete"
            title="Kayıt Sil"
            @click="openSilModal(item)"
          >
            <Trash2 :size="14" />
          </button>
        </div>
      </article>
    </div>

    <!-- ======================================================== -->
    <!-- MODALLAR & BOTTOM SHEETS -->
    <!-- ======================================================== -->

    <!-- Avans Onay Modalı -->
    <div v-if="modalType === 'avansOnay'" class="modal-backdrop" @click.self="closeModal">
      <div class="modal-card">
        <header class="modal-card__head head-success">
          <div class="modal-icon-circle"><Wallet :size="22" /></div>
          <h3>Avansı Onayla</h3>
          <p>{{ activeItem?.name }} · {{ activeItem?.amount_formatted }}</p>
        </header>

        <form class="modal-card__form" @submit.prevent="submitAvansOnay">
          <div class="segmented-choice">
            <button
              type="button"
              :class="{ active: formAvansOnay.onay_tipi === 'ayni' }"
              @click="formAvansOnay.onay_tipi = 'ayni'"
            >
              Aynı Tutar ({{ activeItem?.amount_formatted }})
            </button>
            <button
              type="button"
              :class="{ active: formAvansOnay.onay_tipi === 'farkli' }"
              @click="formAvansOnay.onay_tipi = 'farkli'"
            >
              Farklı Tutar
            </button>
          </div>

          <div v-if="formAvansOnay.onay_tipi === 'farkli'" class="form-group-field">
            <label>ONAYLANAN TUTAR (₺)</label>
            <input
              v-model="formAvansOnay.farkli_tutar"
              type="text"
              placeholder="0,00"
              required
              class="form-input"
            />
          </div>

          <div class="form-group-field">
            <label>AÇIKLAMA NOTU</label>
            <textarea
              v-model="formAvansOnay.aciklama"
              rows="2"
              placeholder="Personele iletilecek not..."
              class="form-textarea"
            ></textarea>
          </div>

          <label class="toggle-bar">
            <input v-model="formAvansOnay.hesaba_isle" type="checkbox" />
            <div>
              <strong>Bordroya İşle</strong>
              <small>Personelin maaş/avans kesintisi olarak kaydedilir</small>
            </div>
          </label>

          <div class="modal-buttons">
            <button type="button" class="btn-cancel" @click="closeModal">İptal</button>
            <button type="submit" class="btn-submit btn-submit--success" :disabled="actionBusy">
              {{ actionBusy ? 'İşleniyor...' : 'Onayla' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Avans Red Modalı -->
    <div v-if="modalType === 'avansRed'" class="modal-backdrop" @click.self="closeModal">
      <div class="modal-card">
        <header class="modal-card__head head-danger">
          <div class="modal-icon-circle"><XCircle :size="22" /></div>
          <h3>Avansı Reddet</h3>
          <p>{{ activeItem?.name }} · {{ activeItem?.amount_formatted }}</p>
        </header>

        <form class="modal-card__form" @submit.prevent="submitAvansRed">
          <div class="form-group-field">
            <label>RED GEREKÇESİ (ZORUNLU)</label>
            <textarea
              v-model="formAvansRed.aciklama"
              rows="3"
              placeholder="Personele iletilecek red açıklaması..."
              required
              class="form-textarea"
            ></textarea>
          </div>

          <div class="modal-buttons">
            <button type="button" class="btn-cancel" @click="closeModal">İptal</button>
            <button type="submit" class="btn-submit btn-submit--danger" :disabled="actionBusy">
              {{ actionBusy ? 'İşleniyor...' : 'Reddet' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- İzin Onay Modalı -->
    <div v-if="modalType === 'izinOnay'" class="modal-backdrop" @click.self="closeModal">
      <div class="modal-card">
        <header class="modal-card__head head-primary">
          <div class="modal-icon-circle"><CalendarCheck :size="22" /></div>
          <h3>İzin Talebini Onayla</h3>
          <p>{{ activeItem?.name }} · {{ activeItem?.days }} Gün ({{ activeItem?.leave_type }})</p>
        </header>

        <form class="modal-card__form" @submit.prevent="submitIzinOnay">
          <div class="segmented-choice">
            <button
              type="button"
              :class="{ active: formIzinOnay.onay_tipi === 'ayni' }"
              @click="formIzinOnay.onay_tipi = 'ayni'"
            >
              Aynı Tarih ({{ activeItem?.start_date }} - {{ activeItem?.end_date }})
            </button>
            <button
              type="button"
              :class="{ active: formIzinOnay.onay_tipi === 'farkli' }"
              @click="formIzinOnay.onay_tipi = 'farkli'"
            >
              Farklı Tarihler
            </button>
          </div>

          <div v-if="formIzinOnay.onay_tipi === 'farkli'" class="date-pickers-row">
            <div class="form-group-field">
              <label>BAŞLANGIÇ</label>
              <input v-model="formIzinOnay.farkli_baslangic" type="date" required class="form-input" />
            </div>
            <div class="form-group-field">
              <label>BİTİŞ</label>
              <input v-model="formIzinOnay.farkli_bitis" type="date" required class="form-input" />
            </div>
          </div>

          <div class="form-group-field">
            <label>AÇIKLAMA NOTU</label>
            <textarea
              v-model="formIzinOnay.aciklama"
              rows="2"
              placeholder="Onay açıklaması..."
              class="form-textarea"
            ></textarea>
          </div>

          <div class="modal-buttons">
            <button type="button" class="btn-cancel" @click="closeModal">İptal</button>
            <button type="submit" class="btn-submit btn-submit--primary" :disabled="actionBusy">
              {{ actionBusy ? 'İşleniyor...' : 'Onayla' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- İzin Red Modalı -->
    <div v-if="modalType === 'izinRed'" class="modal-backdrop" @click.self="closeModal">
      <div class="modal-card">
        <header class="modal-card__head head-danger">
          <div class="modal-icon-circle"><XCircle :size="22" /></div>
          <h3>İzin Talebini Reddet</h3>
          <p>{{ activeItem?.name }} · {{ activeItem?.leave_type }}</p>
        </header>

        <form class="modal-card__form" @submit.prevent="submitIzinRed">
          <div class="form-group-field">
            <label>RED GEREKÇESİ (ZORUNLU)</label>
            <textarea
              v-model="formIzinRed.aciklama"
              rows="3"
              placeholder="Personele iletilecek gerekçe..."
              required
              class="form-textarea"
            ></textarea>
          </div>

          <div class="modal-buttons">
            <button type="button" class="btn-cancel" @click="closeModal">İptal</button>
            <button type="submit" class="btn-submit btn-submit--danger" :disabled="actionBusy">
              {{ actionBusy ? 'İşleniyor...' : 'Reddet' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Genel Talep Çöz Modalı -->
    <div v-if="modalType === 'talepCoz'" class="modal-backdrop" @click.self="closeModal">
      <div class="modal-card">
        <header class="modal-card__head head-purple">
          <div class="modal-icon-circle"><CheckCircle2 :size="22" /></div>
          <h3>Talebi Çözüldü İşaretle</h3>
          <p>{{ activeItem?.name }} · {{ activeItem?.title }}</p>
        </header>

        <form class="modal-card__form" @submit.prevent="submitTalepCoz">
          <div class="form-group-field">
            <label>ÇÖZÜM AÇIKLAMASI</label>
            <textarea
              v-model="formTalepCoz.aciklama"
              rows="3"
              placeholder="Nasıl çözüldü veya personele not..."
              class="form-textarea"
            ></textarea>
          </div>

          <div class="modal-buttons">
            <button type="button" class="btn-cancel" @click="closeModal">İptal</button>
            <button type="submit" class="btn-submit btn-submit--purple" :disabled="actionBusy">
              {{ actionBusy ? 'İşleniyor...' : 'Çözüldü İşaretle' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Silme Modalı -->
    <div v-if="modalType === 'sil'" class="modal-backdrop" @click.self="closeModal">
      <div class="modal-card">
        <header class="modal-card__head head-danger">
          <div class="modal-icon-circle"><Trash2 :size="22" /></div>
          <h3>Talebi Sil</h3>
          <p>{{ activeItem?.name }} personeline ait kayıt silinecektir.</p>
        </header>

        <form class="modal-card__form" @submit.prevent="submitSil">
          <div class="form-group-field">
            <label>SİLME GEREKÇESİ (ZORUNLU)</label>
            <textarea
              v-model="formSil.aciklama"
              rows="3"
              placeholder="Kayıt silinme nedeni..."
              required
              class="form-textarea"
            ></textarea>
          </div>

          <div class="modal-buttons">
            <button type="button" class="btn-cancel" @click="closeModal">İptal</button>
            <button type="submit" class="btn-submit btn-submit--danger" :disabled="actionBusy">
              {{ actionBusy ? 'Siliniyor...' : 'Evet, Sil' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Detay Bottom Sheet -->
    <Transition name="sheet">
      <div v-if="modalType === 'detail'" class="sheet-backdrop" @click.self="closeModal">
        <div class="sheet-container">
          <div class="sheet-handle" @click="closeModal"></div>
          <header class="sheet-header">
            <div>
              <span class="sheet-category">
                {{ activeItem?.type === 'avans' ? 'AVANS DETAYI' : activeItem?.type === 'izin' ? 'İZİN DETAYI' : 'GENEL TALEP DETAYI' }}
              </span>
              <h2>{{ activeItem?.name }}</h2>
              <small>{{ activeItem?.department }}</small>
            </div>
            <button type="button" class="sheet-close-btn" @click="closeModal">
              <X :size="18" />
            </button>
          </header>

          <div class="sheet-body">
            <div class="sheet-info-grid">
              <div v-if="activeItem?.type === 'avans'" class="info-tile">
                <span>TALEP TUTARI</span>
                <strong>{{ activeItem?.amount_formatted }}</strong>
              </div>
              <div v-else-if="activeItem?.type === 'izin'" class="info-tile">
                <span>İZİN SÜRESİ</span>
                <strong>{{ activeItem?.days }} Gün</strong>
              </div>
              <div v-else class="info-tile">
                <span>ÖNCELİK</span>
                <strong>{{ activeItem?.priority }}</strong>
              </div>

              <div class="info-tile">
                <span>DURUM</span>
                <strong>{{ activeItem?.status }}</strong>
              </div>

              <div class="info-tile col-span-2">
                <span>TALEP TARİHİ</span>
                <strong>{{ activeItem?.date }}</strong>
              </div>

              <div v-if="activeItem?.type === 'izin'" class="info-tile col-span-2">
                <span>İZİN ARALIĞI</span>
                <strong>{{ activeItem?.start_date }} — {{ activeItem?.end_date }}</strong>
              </div>
            </div>

            <div v-if="activeItem?.description" class="sheet-box">
              <span class="sheet-box-label">Personel Açıklaması</span>
              <p>{{ activeItem.description }}</p>
            </div>

            <div v-if="activeItem?.approval_note || activeItem?.solution" class="sheet-box box-success">
              <span class="sheet-box-label">Yönetici Çözüm Notu</span>
              <p>{{ activeItem.approval_note || activeItem.solution }}</p>
            </div>

            <div v-if="activeItem?.photo" class="sheet-photo">
              <span class="sheet-box-label">Ekli Fotoğraf</span>
              <img :src="activeItem.photo" alt="Fotoğraf" />
            </div>

            <div v-if="activeItem?.solver_name" class="sheet-solver-badge">
              <User :size="14" />
              <span>İşlem Yapan: <strong>{{ activeItem.solver_name }}</strong></span>
              <span v-if="activeItem?.approval_date">({{ activeItem.approval_date }})</span>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </section>
</template>

<style scoped>
/* Glacier Alpine / DESIGN.md — Talepler */
.talepler-screen {
  padding-bottom: calc(var(--nav-height) + 20px + env(safe-area-inset-bottom));
}

.ds-requests {
  padding-top: calc(var(--ds-safe-top) + 24px);
  font-family: 'Plus Jakarta Sans', sans-serif;
  background: radial-gradient(circle at 90% 4%, rgba(181, 200, 224, 0.45), transparent 24%),
              radial-gradient(circle at 0 48%, rgba(213, 228, 249, 0.42), transparent 30%),
              linear-gradient(155deg, #f7fafc, #eef3f6 52%, #f7fafc);
}

.ds-requests :deep(.topbar) { margin-bottom: 22px; }
.ds-requests :deep(.topbar-row) { grid-template-columns: 44px 1fr 44px; gap: 12px; }
.ds-requests :deep(.avatar) {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  color: #fff;
  background: #11161b;
  font: 600 11px 'Space Grotesk', sans-serif;
  box-shadow: 0 9px 20px rgba(17, 22, 27, 0.16);
}
.ds-requests :deep(.eyebrow) {
  color: #8c9bae;
  font: 700 9px 'Space Grotesk', sans-serif;
  letter-spacing: 0.1em;
}
.ds-requests :deep(.topbar h1) {
  margin-top: 2px;
  color: #181c1e;
  font-size: 22px;
  line-height: 28px;
  letter-spacing: -0.03em;
}
.ds-requests :deep(.topbar p) {
  color: #8c9bae;
  font-size: 11px;
}
.ds-requests :deep(.icon-button) {
  width: 44px;
  height: 44px;
  border: 1px solid rgba(255, 255, 255, 0.9);
  color: #11161b;
  background: rgba(255, 255, 255, 0.78);
  box-shadow: var(--ds-shadow-card);
  backdrop-filter: blur(16px);
}

/* 1. Bento KPI & Overview Card (Minimal & Düşük Yükseklik) */
.hero-card--talepler {
  position: relative;
  min-height: 0 !important;
  height: auto !important;
  margin: 0 0 12px;
  padding: 10px 12px;
  overflow: hidden;
  border: 1px solid rgba(255, 255, 255, 0.9);
  border-radius: 18px;
  color: #181c1e;
  background: rgba(255, 255, 255, 0.72);
  box-shadow: var(--ds-shadow-card);
  backdrop-filter: blur(16px);
  isolation: isolate;
}

.hero-card--talepler::before {
  position: absolute;
  z-index: -1;
  inset: 0;
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.38), transparent 48%, rgba(181, 200, 224, 0.2));
  content: '';
}

.talepler-kpi-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.kpi-main {
  display: flex;
  flex-direction: column;
  justify-content: center;
  flex-shrink: 0;
  padding-right: 10px;
  border-right: 1px solid rgba(140, 155, 174, 0.2);
}

.kpi-main .kpi-label {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  color: #8c9bae;
  font: 700 8.5px 'Space Grotesk', sans-serif;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.kpi-main .kpi-value-row {
  display: flex;
  align-items: baseline;
  gap: 4px;
  margin-top: 1px;
}

.kpi-main .kpi-value-row strong {
  color: #181c1e;
  font-size: 24px;
  font-weight: 700;
  line-height: 1.1;
  letter-spacing: -0.04em;
}

.kpi-main .kpi-value-row small {
  color: #8c9bae;
  font: 600 9.5px 'Space Grotesk', sans-serif;
}

.kpi-mini-grid {
  display: flex;
  gap: 6px;
  flex: 1;
  min-width: 0;
}

.kpi-mini-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  flex: 1;
  min-width: 0;
  gap: 2px;
  padding: 6px 4px;
  border: 1px solid rgba(140, 155, 174, 0.2);
  border-radius: 12px;
  color: #516071;
  background: rgba(241, 244, 246, 0.85);
  cursor: pointer;
  text-align: center;
  transition: all 0.18s ease;
}

.kpi-mini-item:active {
  transform: scale(0.96);
}

.kpi-mini-item .kpi-mini-label,
.kpi-mini-item span {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  font: 700 8.5px 'Space Grotesk', sans-serif;
  color: #516071;
  white-space: nowrap;
  transition: color 0.15s ease;
}

.kpi-mini-item strong {
  display: block;
  margin-top: 1px;
  color: #181c1e;
  font-size: 14px;
  font-weight: 800;
  line-height: 1.1;
  transition: color 0.15s ease;
}

/* Aktif Kategori Kartı (Tam kontrast - beyaz metin) */
.kpi-mini-item.is-active {
  border-color: #11161b;
  background: #11161b;
  color: #ffffff !important;
  box-shadow: 0 4px 12px rgba(17, 22, 27, 0.2);
}

.kpi-mini-item.is-active .kpi-mini-label,
.kpi-mini-item.is-active span {
  color: #ffffff !important;
  opacity: 1 !important;
}

.kpi-mini-item.is-active strong {
  color: #ffffff !important;
  opacity: 1 !important;
}

/* 2. Durum Filtresi Pilleri */
.status-pills-bar {
  display: flex;
  margin-bottom: 12px;
  align-items: center;
  gap: 8px;
}

.status-pills-group {
  display: flex;
  min-width: 0;
  flex: 1;
  gap: 5px;
  overflow-x: auto;
  scrollbar-width: none;
}

.status-pills-group::-webkit-scrollbar { display: none; }

.pill-btn {
  display: inline-flex;
  align-items: center;
  height: 34px;
  padding: 0 11px;
  gap: 5px;
  border: 1px solid rgba(140, 155, 174, 0.2);
  border-radius: 999px;
  color: #8c9bae;
  background: rgba(255, 255, 255, 0.72);
  font: 600 10px 'Space Grotesk', sans-serif;
  white-space: nowrap;
  cursor: pointer;
  transition: all 0.15s ease;
}

.pill-btn:active {
  transform: scale(0.96);
}

.pill-btn.active {
  border-color: #11161b;
  color: #ffffff;
  background: #11161b;
  box-shadow: 0 4px 12px rgba(17, 22, 27, 0.15);
}

.pill-btn--danger.active {
  border-color: #ba1a1a;
  background: #ba1a1a;
}

.sort-dropdown-wrap {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  height: 34px;
  min-width: 34px;
  border: 1px solid rgba(140, 155, 174, 0.2);
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.85);
  box-shadow: var(--ds-shadow-card);
  cursor: pointer;
  transition: all 0.15s ease;
  flex-shrink: 0;
}

.sort-dropdown-wrap:active {
  transform: scale(0.95);
}

.sort-icon {
  pointer-events: none;
  color: #516071;
  transition: color 0.15s ease;
}

.sort-dropdown-wrap:hover .sort-icon {
  color: #11161b;
}

.sort-dropdown-wrap select {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  opacity: 0;
  border: 0;
  outline: none;
  cursor: pointer;
  -webkit-appearance: none;
  appearance: none;
}

/* 3. Arama */
.talepler-search {
  display: flex;
  align-items: center;
  gap: 9px;
  height: 46px;
  margin-bottom: 16px;
  padding: 0 14px;
  border: 1px solid rgba(255, 255, 255, 0.9);
  border-radius: 999px;
  color: #8c9bae;
  background: rgba(255, 255, 255, 0.74);
  box-shadow: var(--ds-shadow-card);
  backdrop-filter: blur(16px);
}

.talepler-search input {
  flex: 1;
  border: 0;
  outline: 0;
  background: transparent;
  color: #181c1e;
  font: 500 11px 'Plus Jakarta Sans', sans-serif;
}

.talepler-search input::placeholder { color: #8c9bae; }

.talepler-search button {
  display: grid;
  width: 26px;
  height: 26px;
  place-items: center;
  border-radius: 50%;
  background: #f1f4f6;
  border: 0;
  color: #516071;
  cursor: pointer;
}

/* 4. Liste Kartları */
.talepler-list {
  display: grid;
  gap: 12px;
}

.t-card {
  padding: 0;
  overflow: hidden;
  border: 1px solid rgba(255, 255, 255, 0.9);
  border-radius: 24px;
  background: rgba(255, 255, 255, 0.76);
  box-shadow: var(--ds-shadow-card);
  backdrop-filter: blur(16px);
  cursor: pointer;
  transition: transform 0.18s ease, opacity 0.18s ease;
}

.t-card:active {
  opacity: 0.9;
  transform: scale(0.99);
}

.t-card__top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  padding: 16px 16px 10px;
}

.t-card__profile {
  display: flex;
  align-items: center;
  gap: 10px;
}

.t-card__avatar {
  width: 44px;
  height: 44px;
  border-radius: 16px;
  color: #fff;
  background: #11161b;
  font: 600 12px 'Space Grotesk', sans-serif;
  display: grid;
  place-items: center;
  overflow: hidden;
  flex-shrink: 0;
}

.t-card__avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.t-card__meta h4 {
  margin: 0;
  color: #181c1e;
  font-size: 14px;
  font-weight: 700;
  letter-spacing: -0.02em;
}

.t-card__meta p {
  margin: 3px 0 0;
  color: #8c9bae;
  font-size: 10px;
}

.t-card__badge-wrap {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 4px;
}

.badge-amount, .badge-days {
  border: 0;
  border-radius: 999px;
  color: #181c1e;
  background: #f1f4f6;
  padding: 3px 8px;
  font: 700 10.5px 'Space Grotesk', sans-serif;
}

.badge-amount {
  color: #047857;
  background: #d1fae5;
}

.badge-days {
  color: #1d4ed8;
  background: #dbeafe;
}

.status-chip {
  border-radius: 999px;
  padding: 2px 6px;
  font: 600 8.5px 'Space Grotesk', sans-serif;
}

.status-chip--pending { background: #f1f4f6; color: #516071; }
.status-chip--approved { background: #dcfce7; color: #15803d; }
.status-chip--rejected { background: #fee2e2; color: #b91c1c; }
.status-chip--deleted { background: #fee2e2; color: #ba1a1a; }

.t-card__body {
  padding: 0 16px 14px;
}

.talep-heading {
  margin: 0 0 6px;
  color: #181c1e;
  font-size: 13px;
  font-weight: 700;
}

.t-card__quote {
  padding: 10px 12px;
  border: 1px solid rgba(140, 155, 174, 0.12);
  border-radius: 14px;
  color: #516071;
  background: #f1f4f6;
  font-size: 10.5px;
  line-height: 16px;
  margin: 0 0 8px;
}

.t-card__details-row {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.detail-tag {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  color: #8c9bae;
  font: 500 9.5px 'Space Grotesk', sans-serif;
}

.solver-bar {
  margin-top: 8px;
  padding: 6px 10px;
  border-radius: 10px;
  color: #516071;
  background: #f1f4f6;
  font-size: 10px;
  display: flex;
  align-items: center;
  gap: 4px;
}

.t-card__actions {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 10px 12px;
  border-top: 1px solid rgba(140, 155, 174, 0.15);
  background: rgba(241, 244, 246, 0.62);
}

.act-btn {
  flex: 1;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 5px;
  height: 36px;
  border: 0;
  border-radius: 999px;
  font: 600 10px 'Space Grotesk', sans-serif;
  cursor: pointer;
  transition: transform 0.1s ease;
}

.act-btn:active { transform: scale(0.97); }

.act-btn--approve, .act-btn--primary, .act-btn--purple {
  color: #fff;
  background: #11161b;
}

.act-btn--reject {
  color: #93000a;
  background: #ffdad6;
}

.act-btn--neutral {
  color: #516071;
  background: #ffffff;
}

.act-btn-delete {
  width: 36px;
  height: 36px;
  border: 0;
  border-radius: 50%;
  color: #93000a;
  background: #ffdad6;
  display: grid;
  place-items: center;
  cursor: pointer;
}

/* Empty State */
.empty-state-card {
  padding: 36px 22px;
  border: 1px solid rgba(255, 255, 255, 0.9);
  border-radius: 24px;
  background: rgba(255, 255, 255, 0.72);
  box-shadow: var(--ds-shadow-card);
  backdrop-filter: blur(16px);
  text-align: center;
}

.empty-icon-bubble {
  width: 52px;
  height: 52px;
  border-radius: 18px;
  color: #11161b;
  background: #f1f4f6;
  display: grid;
  place-items: center;
  margin: 0 auto 12px;
}

.empty-icon-bubble.is-pending {
  background: #ecfdf5;
  color: #059669;
}

.empty-state-card h3 {
  margin: 0;
  color: #181c1e;
  font-size: 16px;
}

.empty-state-card p {
  margin: 4px 0 16px;
  color: #8c9bae;
  font-size: 11px;
}

.btn-subtle-refresh {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 7px 14px;
  border: 0;
  border-radius: 999px;
  color: #fff;
  background: #11161b;
  font-size: 11px;
  font-weight: 700;
  cursor: pointer;
}

.toast-floating {
  display: flex;
  align-items: center;
  gap: 7px;
  padding: 9px 14px;
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 999px;
  color: #fff;
  background: rgba(17, 22, 27, 0.94);
  box-shadow: var(--ds-shadow-float);
  backdrop-filter: blur(20px);
  margin-bottom: 12px;
  font-size: 11px;
  font-weight: 700;
}

/* Modallar */
.modal-backdrop, .sheet-backdrop {
  position: fixed;
  inset: 0;
  z-index: 120;
  background: rgba(17, 22, 27, 0.44);
  backdrop-filter: blur(10px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
}

.sheet-backdrop {
  align-items: flex-end;
  padding: 0;
}

.modal-card {
  width: min(100%, 370px);
  border: 1px solid rgba(255, 255, 255, 0.9);
  border-radius: 28px;
  background: #f7fafc;
  box-shadow: 0 24px 60px rgba(17, 22, 27, 0.25);
  overflow: hidden;
}

.modal-card__head {
  padding: 22px 18px 15px;
  color: #181c1e;
  background: linear-gradient(145deg, #fff, rgba(213, 228, 249, 0.72));
  text-align: center;
}

.modal-icon-circle {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  color: #fff;
  background: #11161b;
  box-shadow: 0 8px 20px rgba(17, 22, 27, 0.18);
  display: grid;
  place-items: center;
  margin: 0 auto 8px;
}

.modal-card__head h3 {
  margin: 0;
  font-size: 18px;
  letter-spacing: -0.02em;
}

.modal-card__head p {
  margin: 3px 0 0;
  font-size: 11px;
  color: #516071;
}

.modal-card__form {
  padding: 18px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.segmented-choice {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 4px;
  padding: 4px;
  border-radius: 999px;
  background: #e5e9eb;
}

.segmented-choice button {
  padding: 7px 4px;
  border: 0;
  border-radius: 999px;
  color: #516071;
  font: 700 10.5px 'Space Grotesk', sans-serif;
  cursor: pointer;
}

.segmented-choice button.active {
  color: #fff;
  background: #11161b;
}

.form-group-field {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.form-group-field label {
  color: #516071;
  font: 700 9px 'Space Grotesk', sans-serif;
  letter-spacing: 0.08em;
}

.form-input, .form-textarea {
  width: 100%;
  padding: 12px 14px;
  border: 1px solid rgba(140, 155, 174, 0.22);
  border-radius: 16px;
  color: #181c1e;
  background: #fff;
  font-size: 12px;
  outline: none;
}

.form-input:focus, .form-textarea:focus {
  border-color: #516071;
}

.toggle-bar {
  display: flex;
  align-items: center;
  gap: 9px;
  padding: 10px 12px;
  border-radius: 14px;
  background: #ecfdf5;
  border: 1px solid #a7f3d0;
  cursor: pointer;
}

.toggle-bar strong {
  display: block;
  font-size: 11px;
  color: #065f46;
}

.toggle-bar small {
  display: block;
  font-size: 9px;
  color: #047857;
}

.date-pickers-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 6px;
}

.modal-buttons {
  display: grid;
  grid-template-columns: 1fr 1.3fr;
  gap: 8px;
  margin-top: 4px;
}

.btn-cancel, .btn-submit {
  height: 44px;
  border-radius: 999px;
  border: 0;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
}

.btn-cancel {
  background: #f1f4f6;
  color: #516071;
}

.btn-submit {
  color: #fff;
  background: #11161b;
}

.btn-submit--danger {
  background: #dc2626;
}

/* Bottom Sheet */
.sheet-container {
  width: min(100%, 460px);
  max-height: 85vh;
  margin: 0 auto;
  border: 1px solid rgba(255, 255, 255, 0.9);
  border-radius: 28px 28px 0 0;
  background: #f7fafc;
  box-shadow: 0 -18px 50px rgba(17, 22, 27, 0.2);
  padding: 12px 18px calc(24px + env(safe-area-inset-bottom));
  overflow-y: auto;
}

.sheet-handle {
  width: 44px;
  height: 5px;
  background: #8c9bae;
  border-radius: 999px;
  margin: 0 auto 12px;
  cursor: pointer;
}

.sheet-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-bottom: 12px;
  border-bottom: 1px solid rgba(140, 155, 174, 0.15);
}

.sheet-category {
  color: #516071;
  font: 700 9px 'Space Grotesk', sans-serif;
  letter-spacing: 0.1em;
}

.sheet-header h2 {
  margin: 2px 0 0;
  color: #181c1e;
  font-size: 20px;
}

.sheet-header small {
  color: #8c9bae;
  font-size: 11px;
}

.sheet-close-btn {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  border: 0;
  color: #fff;
  background: #11161b;
  display: grid;
  place-items: center;
  cursor: pointer;
}

.sheet-body {
  padding-top: 14px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.sheet-info-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
}

.info-tile, .sheet-box {
  padding: 11px 13px;
  border: 1px solid rgba(140, 155, 174, 0.15);
  border-radius: 16px;
  background: #fff;
}

.info-tile.col-span-2 { grid-column: span 2; }

.info-tile span, .sheet-box-label {
  display: block;
  color: #8c9bae;
  font: 700 8.5px 'Space Grotesk', sans-serif;
  letter-spacing: 0.08em;
}

.info-tile strong, .sheet-box p {
  display: block;
  margin-top: 2px;
  color: #181c1e;
  font-size: 13px;
  font-weight: 700;
}

.sheet-box.box-success {
  background: #ecfdf5;
  border-color: #a7f3d0;
}

.sheet-box.box-success p {
  color: #065f46;
}

.sheet-photo img {
  width: 100%;
  border-radius: 14px;
  margin-top: 6px;
}

.sheet-solver-badge {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 12px;
  border-radius: 16px;
  color: #516071;
  background: #e5e9eb;
  font-size: 11px;
}
</style>
