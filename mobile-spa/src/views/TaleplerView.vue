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

    <!-- 1. Bento KPI & Overview Card -->
    <section class="hero-card hero-card--talepler">
      <div class="hero-glow hero-glow-one"></div>
      <div class="hero-glow hero-glow-two"></div>
      <div class="hero-topline">
        <span><Sparkles :size="15" /> Talep & Onay Merkezi</span>
        <span class="live-status">
          Canlı <i class="live-dot"></i>
        </span>
      </div>

      <div class="talepler-kpi-row">
        <div class="kpi-main">
          <small>ONAY BEKLEYEN</small>
          <strong>{{ counts.total }}</strong>
          <span>Toplam açık talep</span>
        </div>

        <div class="kpi-mini-grid">
          <div v-if="permissions.avans" class="kpi-mini-item" :class="{ 'is-active': activeTab === 'avans' }" @click="setTab('avans')">
            <span>Avans</span>
            <strong>{{ counts.avans }}</strong>
          </div>
          <div v-if="permissions.izin" class="kpi-mini-item" :class="{ 'is-active': activeTab === 'izin' }" @click="setTab('izin')">
            <span>İzin</span>
            <strong>{{ counts.izin }}</strong>
          </div>
          <div v-if="permissions.talep" class="kpi-mini-item" :class="{ 'is-active': activeTab === 'talep' }" @click="setTab('talep')">
            <span>Genel</span>
            <strong>{{ counts.talep }}</strong>
          </div>
        </div>
      </div>
    </section>

    <!-- 2. Segmented Navigation (Kategori Seçimi) -->
    <div class="segmented segmented--talepler">
      <button
        v-if="permissions.avans"
        type="button"
        :class="{ active: activeTab === 'avans' }"
        @click="setTab('avans')"
      >
        <Wallet :size="15" />
        <span>Avans Talepleri</span>
        <em v-if="counts.avans > 0">{{ counts.avans }}</em>
      </button>

      <button
        v-if="permissions.izin"
        type="button"
        :class="{ active: activeTab === 'izin' }"
        @click="setTab('izin')"
      >
        <CalendarDays :size="15" />
        <span>İzin Talepleri</span>
        <em v-if="counts.izin > 0">{{ counts.izin }}</em>
      </button>

      <button
        v-if="permissions.talep"
        type="button"
        :class="{ active: activeTab === 'talep' }"
        @click="setTab('talep')"
      >
        <Layers :size="15" />
        <span>Genel Saha</span>
        <em v-if="counts.talep > 0">{{ counts.talep }}</em>
      </button>
    </div>

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

      <!-- Sıralama Seçimi -->
      <div class="sort-dropdown-wrap">
        <select v-model="sortBy" aria-label="Sıralama">
          <option value="newest">En Yeni</option>
          <option value="oldest">En Eski</option>
          <option value="name_asc">İsim (A-Z)</option>
          <option value="name_desc">İsim (Z-A)</option>
          <option v-if="activeTab === 'avans'" value="amount_desc">Tutar</option>
        </select>
        <ArrowUpDown :size="12" class="sort-icon" />
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
.talepler-screen {
  padding-bottom: calc(var(--nav-height) + 20px + env(safe-area-inset-bottom));
}

/* 1. Bento KPI & Overview Card */
.hero-card--talepler {
  min-height: auto;
  padding: 18px;
  margin-bottom: 14px;
}

.talepler-kpi-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 14px;
  gap: 12px;
}

.kpi-main {
  display: flex;
  flex-direction: column;
}

.kpi-main small {
  font-size: 8.5px;
  font-weight: 800;
  letter-spacing: 0.12em;
  color: rgba(255, 255, 255, 0.75);
}

.kpi-main strong {
  font-size: 34px;
  font-weight: 900;
  line-height: 1.05;
  letter-spacing: -0.05em;
  color: #ffffff;
}

.kpi-main span {
  font-size: 10.5px;
  color: rgba(255, 255, 255, 0.85);
  margin-top: 2px;
}

.kpi-mini-grid {
  display: flex;
  gap: 6px;
}

.kpi-mini-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  width: 58px;
  height: 54px;
  border-radius: 14px;
  background: rgba(255, 255, 255, 0.12);
  backdrop-filter: blur(8px);
  border: 1px solid rgba(255, 255, 255, 0.2);
  cursor: pointer;
  transition: all 0.18s ease;
}

.kpi-mini-item:active {
  transform: scale(0.95);
}

.kpi-mini-item.is-active {
  background: #ffffff;
  color: #172033;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.kpi-mini-item span {
  font-size: 8.5px;
  font-weight: 700;
  opacity: 0.85;
}

.kpi-mini-item strong {
  font-size: 15px;
  font-weight: 900;
  margin-top: 1px;
}

.kpi-mini-item.is-active span,
.kpi-mini-item.is-active strong {
  color: #172033;
  opacity: 1;
}

/* 2. Segmented Navigation */
.segmented--talepler {
  grid-template-columns: repeat(3, 1fr);
  gap: 4px;
  padding: 4px;
  border-radius: 16px;
  background: #eaecee;
}

.segmented--talepler button {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 5px;
  padding: 8px 4px;
  font-size: 11px;
  font-weight: 700;
  border-radius: 12px;
  color: #64748b;
  position: relative;
}

.segmented--talepler button em {
  font-style: normal;
  font-size: 9px;
  font-weight: 800;
  padding: 1px 5px;
  border-radius: 999px;
  background: rgba(0, 0, 0, 0.1);
  color: inherit;
}

.segmented--talepler button.active em {
  background: rgba(255, 255, 255, 0.25);
  color: #ffffff;
}

/* 3. Status Pills Bar */
.status-pills-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-top: 12px;
}

.status-pills-group {
  display: flex;
  gap: 4px;
  flex: 1;
  overflow-x: auto;
}

.pill-btn {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  height: 30px;
  padding: 0 10px;
  border-radius: 999px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  color: #64748b;
  font-size: 10.5px;
  font-weight: 700;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.15s ease;
}

.pill-btn:active {
  transform: scale(0.96);
}

.pill-btn.active {
  background: #172033;
  color: #ffffff;
  border-color: #172033;
  box-shadow: 0 2px 6px rgba(23, 32, 51, 0.16);
}

.pill-btn--danger.active {
  background: #dc2626;
  border-color: #dc2626;
  color: #ffffff;
}

.sort-dropdown-wrap {
  position: relative;
  display: flex;
  align-items: center;
  flex-shrink: 0;
}

.sort-dropdown-wrap select {
  height: 30px;
  padding: 0 22px 0 8px;
  border-radius: 999px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  font-size: 10.5px;
  font-weight: 700;
  color: #475569;
  outline: none;
  appearance: none;
}

.sort-icon {
  position: absolute;
  right: 7px;
  pointer-events: none;
  color: #94a3b8;
}

/* 4. Arama Inputu */
.talepler-search {
  margin-top: 10px;
  margin-bottom: 14px;
}

/* Floating Toast */
.toast-floating {
  display: flex;
  align-items: center;
  gap: 7px;
  background: #10b981;
  color: #ffffff;
  padding: 9px 13px;
  border-radius: 14px;
  margin-bottom: 12px;
  font-size: 11.5px;
  font-weight: 700;
  box-shadow: 0 6px 18px rgba(16, 185, 129, 0.25);
}

/* Empty State Card */
.empty-state-card {
  padding: 34px 20px;
  text-align: center;
  border-radius: 22px;
  background: #ffffff;
  border: 1px solid #e9eaec;
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.03);
}

.empty-icon-bubble {
  width: 52px;
  height: 52px;
  border-radius: 50%;
  display: grid;
  place-items: center;
  margin: 0 auto 12px;
  background: #f1f5f9;
  color: #94a3b8;
}

.empty-icon-bubble.is-pending {
  background: #ecfdf5;
  color: #10b981;
}

.empty-state-card h3 {
  font-size: 15px;
  font-weight: 800;
  color: #172033;
}

.empty-state-card p {
  font-size: 11.5px;
  color: #89909d;
  margin: 4px 0 16px;
}

.btn-subtle-refresh {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 7px 14px;
  border-radius: 11px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  font-size: 11px;
  font-weight: 700;
  color: #475569;
}

/* 5. Talep Kartları */
.talepler-list {
  display: grid;
  gap: 11px;
}

.t-card {
  padding: 14px;
  border-radius: 20px;
  background: #ffffff;
  border: 1px solid #e9eaec;
  box-shadow: 0 4px 14px rgba(28, 41, 58, 0.04);
  cursor: pointer;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.t-card:active {
  transform: scale(0.99);
}

.t-card__top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 8px;
}

.t-card__profile {
  display: flex;
  align-items: center;
  gap: 10px;
}

.t-card__avatar {
  width: 38px;
  height: 38px;
  border-radius: 13px;
  background: linear-gradient(145deg, #1d2b49, #3469b7);
  color: #ffffff;
  font-weight: 800;
  font-size: 13px;
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
  font-size: 13px;
  font-weight: 800;
  color: #172033;
  margin: 0;
}

.t-card__meta p {
  font-size: 10.5px;
  color: #89909d;
  margin-top: 2px;
}

.t-card__badge-wrap {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 3px;
}

.badge-amount {
  font-size: 13px;
  font-weight: 900;
  color: #16a34a;
  background: #f0fdf4;
  padding: 2px 7px;
  border-radius: 8px;
  border: 1px solid #bbf7d0;
}

.badge-days {
  font-size: 11.5px;
  font-weight: 800;
  color: #2167f3;
  background: #eaf1ff;
  padding: 2px 7px;
  border-radius: 8px;
}

.status-chip {
  font-size: 9px;
  font-weight: 800;
  padding: 2px 5px;
  border-radius: 6px;
}

.status-chip--pending { background: #f1f5f9; color: #64748b; }
.status-chip--approved { background: #dcfce7; color: #15803d; }
.status-chip--rejected { background: #fee2e2; color: #b91c1c; }
.status-chip--deleted { background: #fef2f2; color: #991b1b; }

/* Gövde */
.t-card__body {
  margin-top: 10px;
}

.talep-heading {
  font-size: 12.5px;
  font-weight: 800;
  color: #1e293b;
  margin: 0 0 4px;
}

.t-card__quote {
  font-size: 11px;
  color: #475569;
  background: #f8fafc;
  border-radius: 10px;
  padding: 8px 10px;
  margin: 0 0 8px;
  line-height: 1.4;
  border-left: 3px solid #cbd5e1;
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
  font-size: 10.5px;
  color: #64748b;
}

.solver-bar {
  margin-top: 8px;
  padding-top: 6px;
  border-top: 1px dashed #e9eaec;
  font-size: 10px;
  color: #94a3b8;
  display: flex;
  align-items: center;
  gap: 4px;
}

/* Aksiyon Araç Çubuğu */
.t-card__actions {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-top: 12px;
  padding-top: 10px;
  border-top: 1px solid #f1f5f9;
}

.act-btn {
  flex: 1;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 5px;
  height: 34px;
  border-radius: 11px;
  font-size: 11px;
  font-weight: 800;
  cursor: pointer;
  transition: transform 0.1s ease;
}

.act-btn:active { transform: scale(0.97); }

.act-btn--reject { background: #fee2e2; color: #dc2626; }
.act-btn--approve { background: #16a34a; color: #ffffff; box-shadow: 0 3px 8px rgba(22, 163, 74, 0.25); }
.act-btn--primary { background: #2167f3; color: #ffffff; box-shadow: 0 3px 8px rgba(33, 103, 243, 0.25); }
.act-btn--purple { background: #7c3aed; color: #ffffff; box-shadow: 0 3px 8px rgba(124, 58, 237, 0.25); }
.act-btn--neutral { background: #f1f5f9; color: #475569; }

.act-btn-delete {
  width: 34px;
  height: 34px;
  border-radius: 11px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  color: #94a3b8;
  display: grid;
  place-items: center;
  cursor: pointer;
}

.act-btn-delete:hover {
  color: #dc2626;
  background: #fee2e2;
  border-color: #fecaca;
}

/* Modallar */
.modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 100;
  background: rgba(15, 23, 42, 0.55);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
}

.modal-card {
  width: min(100%, 370px);
  background: #ffffff;
  border-radius: 24px;
  overflow: hidden;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
  animation: modalIn 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes modalIn {
  from { opacity: 0; transform: scale(0.95); }
  to { opacity: 1; transform: scale(1); }
}

.modal-card__head {
  padding: 18px 16px 12px;
  text-align: center;
}

.head-success { background: linear-gradient(135deg, #dcfce7, #bbf7d0); color: #166534; }
.head-danger { background: linear-gradient(135deg, #fee2e2, #fecaca); color: #991b1b; }
.head-primary { background: linear-gradient(135deg, #dbeafe, #bfdbfe); color: #1e40af; }
.head-purple { background: linear-gradient(135deg, #ede9fe, #ddd6fe); color: #5b21b6; }

.modal-icon-circle {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.85);
  display: grid;
  place-items: center;
  margin: 0 auto 8px;
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
}

.modal-card__head h3 {
  font-size: 16px;
  font-weight: 800;
  margin: 0;
}

.modal-card__head p {
  font-size: 11px;
  margin: 3px 0 0;
  opacity: 0.85;
}

.modal-card__form {
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.segmented-choice {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 4px;
  padding: 3px;
  border-radius: 12px;
  background: #f1f5f9;
}

.segmented-choice button {
  padding: 7px 4px;
  border-radius: 9px;
  font-size: 10.5px;
  font-weight: 700;
  color: #64748b;
}

.segmented-choice button.active {
  background: #ffffff;
  color: #0f172a;
  box-shadow: 0 2px 5px rgba(0, 0, 0, 0.08);
}

.form-group-field {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.form-group-field label {
  font-size: 9px;
  font-weight: 800;
  color: #64748b;
  letter-spacing: 0.05em;
}

.form-input, .form-textarea {
  width: 100%;
  padding: 9px 11px;
  border: 1.5px solid #e2e8f0;
  border-radius: 12px;
  font-size: 11.5px;
  color: #1e293b;
  outline: none;
  background: #f8fafc;
}

.form-input:focus, .form-textarea:focus {
  border-color: #2167f3;
  background: #ffffff;
}

.toggle-bar {
  display: flex;
  align-items: center;
  gap: 9px;
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  padding: 9px 11px;
  border-radius: 12px;
  cursor: pointer;
}

.toggle-bar strong {
  display: block;
  font-size: 11px;
  color: #166534;
}

.toggle-bar small {
  display: block;
  font-size: 9px;
  color: #15803d;
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

.btn-cancel {
  height: 38px;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  background: #f8fafc;
  color: #475569;
  font-size: 11.5px;
  font-weight: 700;
}

.btn-submit {
  height: 38px;
  border-radius: 12px;
  border: 0;
  color: #ffffff;
  font-size: 11.5px;
  font-weight: 800;
  cursor: pointer;
}

.btn-submit--success { background: #16a34a; }
.btn-submit--danger { background: #dc2626; }
.btn-submit--primary { background: #2167f3; }
.btn-submit--purple { background: #7c3aed; }

/* Bottom Sheet Detay */
.sheet-backdrop {
  position: fixed;
  inset: 0;
  z-index: 120;
  background: rgba(15, 23, 42, 0.45);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: flex-end;
  justify-content: center;
}

.sheet-container {
  width: min(100%, 460px);
  max-height: 85vh;
  background: #ffffff;
  border-radius: 28px 28px 0 0;
  padding: 12px 18px calc(24px + env(safe-area-inset-bottom));
  overflow-y: auto;
  box-shadow: 0 -10px 30px rgba(0, 0, 0, 0.15);
  animation: slideUp 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes slideUp {
  from { transform: translateY(100%); }
  to { transform: translateY(0); }
}

.sheet-handle {
  width: 44px;
  height: 5px;
  background: #cbd5e1;
  border-radius: 999px;
  margin: 0 auto 12px;
  cursor: pointer;
}

.sheet-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-bottom: 12px;
  border-bottom: 1px solid #f1f5f9;
}

.sheet-category {
  font-size: 9px;
  font-weight: 800;
  letter-spacing: 0.1em;
  color: #2167f3;
}

.sheet-header h2 {
  font-size: 18px;
  font-weight: 900;
  color: #0f172a;
  margin: 1px 0 0;
}

.sheet-header small {
  font-size: 11px;
  color: #64748b;
}

.sheet-close-btn {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  border: 0;
  background: #f1f5f9;
  color: #64748b;
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

.info-tile {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  padding: 10px 12px;
}

.info-tile.col-span-2 { grid-column: span 2; }

.info-tile span {
  display: block;
  font-size: 8.5px;
  font-weight: 800;
  letter-spacing: 0.08em;
  color: #94a3b8;
}

.info-tile strong {
  display: block;
  font-size: 13px;
  font-weight: 800;
  color: #0f172a;
  margin-top: 2px;
}

.sheet-box {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  padding: 12px;
}

.sheet-box.box-success {
  background: #f0fdf4;
  border-color: #bbf7d0;
  color: #166534;
}

.sheet-box-label {
  display: block;
  font-size: 9px;
  font-weight: 800;
  text-transform: uppercase;
  color: #64748b;
  margin-bottom: 4px;
}

.sheet-box p {
  font-size: 12px;
  color: #334155;
  margin: 0;
  line-height: 1.45;
}

.sheet-photo img {
  width: 100%;
  border-radius: 14px;
  margin-top: 6px;
  border: 1px solid #e2e8f0;
}

.sheet-solver-badge {
  display: flex;
  align-items: center;
  gap: 6px;
  background: #f1f5f9;
  border-radius: 12px;
  padding: 8px 12px;
  font-size: 11px;
  color: #475569;
}

/* Glacier Alpine / DESIGN.md — Talepler */
.ds-requests{padding-top:calc(var(--ds-safe-top) + 24px);font-family:'Plus Jakarta Sans',sans-serif;background:radial-gradient(circle at 90% 4%,rgba(181,200,224,.45),transparent 24%),radial-gradient(circle at 0 48%,rgba(213,228,249,.42),transparent 30%),linear-gradient(155deg,#f7fafc,#eef3f6 52%,#f7fafc)}
.ds-requests :deep(.topbar){margin-bottom:22px}.ds-requests :deep(.topbar-row){grid-template-columns:44px 1fr 44px;gap:12px}.ds-requests :deep(.avatar){width:44px;height:44px;border-radius:50%;color:#fff;background:#11161b;font:600 11px 'Space Grotesk',sans-serif;box-shadow:0 9px 20px rgba(17,22,27,.16)}.ds-requests :deep(.eyebrow){color:#8c9bae;font:700 9px 'Space Grotesk',sans-serif;letter-spacing:.1em}.ds-requests :deep(.topbar h1){margin-top:2px;color:#181c1e;font-size:22px;line-height:28px;letter-spacing:-.03em}.ds-requests :deep(.topbar p){color:#8c9bae;font-size:11px}.ds-requests :deep(.icon-button){width:44px;height:44px;border:1px solid rgba(255,255,255,.9);color:#11161b;background:rgba(255,255,255,.78);box-shadow:var(--ds-shadow-card);backdrop-filter:blur(16px)}
.hero-card--talepler{position:relative;margin:0 0 14px;padding:20px;overflow:hidden;border:1px solid rgba(255,255,255,.9);border-radius:28px;color:#181c1e;background:rgba(255,255,255,.72);box-shadow:var(--ds-shadow-card);backdrop-filter:blur(16px);isolation:isolate}.hero-card--talepler::before{position:absolute;z-index:-1;inset:0;background:linear-gradient(135deg,rgba(255,255,255,.38),transparent 48%,rgba(181,200,224,.2));content:''}.hero-card--talepler .hero-glow{display:none}.hero-card--talepler .hero-topline{display:flex;align-items:center;justify-content:space-between;color:#516071}.hero-card--talepler .hero-topline>span:first-child{display:flex;align-items:center;gap:7px;font:600 11px 'Space Grotesk',sans-serif}.hero-card--talepler .live-status{padding:6px 9px;border:1px solid var(--ds-line);border-radius:999px;color:#516071;background:rgba(255,255,255,.7);font:600 9px 'Space Grotesk',sans-serif}.hero-card--talepler .live-dot{background:#2b9a8a}
.talepler-kpi-row{margin-top:20px;align-items:flex-end}.kpi-main small{color:#8c9bae;font:700 9px 'Space Grotesk',sans-serif;letter-spacing:.1em}.kpi-main strong{margin-top:3px;color:#181c1e;font-size:48px;font-weight:600;line-height:52px;letter-spacing:-.06em}.kpi-main span{color:#8c9bae;font-size:10px}.kpi-mini-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:5px}.kpi-mini-item{min-width:52px;padding:8px 7px;border:1px solid rgba(140,155,174,.15);border-radius:14px;color:#516071;background:rgba(241,244,246,.76);text-align:center}.kpi-mini-item span{font:600 8px 'Space Grotesk',sans-serif}.kpi-mini-item strong{display:block;margin-top:2px;color:#181c1e;font-size:16px}.kpi-mini-item.is-active{border-color:#11161b;color:#fff;background:#11161b}.kpi-mini-item.is-active strong{color:#fff}
.segmented--talepler{display:flex;margin:0 0 12px;padding:4px;gap:4px;overflow-x:auto;border:1px solid rgba(255,255,255,.9);border-radius:999px;background:rgba(255,255,255,.66);box-shadow:var(--ds-shadow-card);scrollbar-width:none;backdrop-filter:blur(16px)}.segmented--talepler::-webkit-scrollbar{display:none}.segmented--talepler button{min-width:max-content;height:42px;padding:0 13px;gap:6px;border-radius:999px;color:#8c9bae;font:600 10px 'Space Grotesk',sans-serif}.segmented--talepler button.active{color:#fff;background:#11161b;box-shadow:0 8px 18px rgba(17,22,27,.18)}.segmented--talepler em{display:grid;min-width:18px;height:18px;padding:0 5px;place-items:center;border-radius:999px;color:#11161b;background:#fff;font-size:8px}
.status-pills-bar{display:flex;margin-bottom:12px;align-items:center;gap:8px}.status-pills-group{display:flex;min-width:0;flex:1;gap:5px;overflow-x:auto;scrollbar-width:none}.pill-btn{height:34px;padding:0 10px;gap:5px;border:1px solid rgba(140,155,174,.15);border-radius:999px;color:#8c9bae;background:rgba(255,255,255,.62);font:600 9px 'Space Grotesk',sans-serif;white-space:nowrap}.pill-btn.active{border-color:#11161b;color:#fff;background:#11161b}.pill-btn--danger.active{border-color:#ba1a1a;background:#ba1a1a}.sort-dropdown-wrap{height:34px;border:1px solid rgba(255,255,255,.9);border-radius:999px;background:rgba(255,255,255,.78)}.sort-dropdown-wrap select{height:100%;padding:0 27px 0 10px;color:#516071;background:transparent;font:600 9px 'Space Grotesk',sans-serif}
.talepler-search{height:48px;margin-bottom:18px;padding:0 14px;border:1px solid rgba(255,255,255,.9);border-radius:999px;color:#8c9bae;background:rgba(255,255,255,.74);box-shadow:var(--ds-shadow-card);backdrop-filter:blur(16px)}.talepler-search input{font:500 11px 'Plus Jakarta Sans',sans-serif}.talepler-search input::placeholder{color:#8c9bae}.talepler-search button{display:grid;width:28px;height:28px;place-items:center;border-radius:50%;background:#f1f4f6}
.talepler-list{display:grid;gap:12px}.t-card{padding:0;overflow:hidden;border:1px solid rgba(255,255,255,.9);border-radius:24px;background:rgba(255,255,255,.76);box-shadow:var(--ds-shadow-card);backdrop-filter:blur(16px);transition:transform .18s ease,opacity .18s ease}.t-card:active{opacity:.88;transform:scale(.99)}.t-card__top{padding:16px 16px 10px}.t-card__avatar{width:44px;height:44px;border-radius:16px;color:#fff;background:#11161b;font:600 12px 'Space Grotesk',sans-serif}.t-card__meta h4{color:#181c1e;font-size:14px;font-weight:700;letter-spacing:-.02em}.t-card__meta p{margin-top:3px;color:#8c9bae;font-size:9px}.badge-amount,.badge-days{border:0;border-radius:999px;color:#181c1e;background:#f1f4f6;font:700 10px 'Space Grotesk',sans-serif}.status-chip{border-radius:999px;font:600 8px 'Space Grotesk',sans-serif}.t-card__body{padding:0 16px 14px}.talep-heading{font-size:13px}.t-card__quote{padding:10px 12px;border:1px solid rgba(140,155,174,.12);border-radius:14px;color:#516071;background:#f1f4f6;font-size:10px;line-height:16px}.detail-tag{color:#8c9bae;font:500 9px 'Space Grotesk',sans-serif}.solver-bar{border-radius:12px;color:#516071;background:#f1f4f6}.t-card__actions{padding:10px 12px;border-top:1px solid rgba(140,155,174,.15);background:rgba(241,244,246,.62)}.act-btn{height:36px;border-radius:999px;font:600 9px 'Space Grotesk',sans-serif}.act-btn--approve,.act-btn--primary,.act-btn--purple{color:#fff;background:#11161b}.act-btn--reject{color:#93000a;background:#ffdad6}.act-btn--neutral{color:#516071;background:#fff}.act-btn-delete{width:36px;height:36px;border-radius:50%;color:#93000a;background:#ffdad6}
.empty-state-card{padding:36px 22px;border:1px solid rgba(255,255,255,.9);border-radius:24px;background:rgba(255,255,255,.72);box-shadow:var(--ds-shadow-card);backdrop-filter:blur(16px)}.empty-icon-bubble{border-radius:18px;color:#11161b;background:#f1f4f6}.empty-state-card h3{color:#181c1e;font-size:16px}.empty-state-card p{color:#8c9bae;font-size:11px}.btn-subtle-refresh{border-radius:999px;color:#fff;background:#11161b}.toast-floating{border:1px solid rgba(255,255,255,.12);border-radius:999px;color:#fff;background:rgba(17,22,27,.94);box-shadow:var(--ds-shadow-float);backdrop-filter:blur(20px)}
.modal-backdrop,.sheet-backdrop{background:rgba(17,22,27,.44);backdrop-filter:blur(10px)}.modal-card{border:1px solid rgba(255,255,255,.9);border-radius:28px;background:#f7fafc;box-shadow:0 24px 60px rgba(17,22,27,.25)}.modal-card__head,.head-success,.head-danger,.head-primary,.head-purple{padding:22px 18px 15px;color:#181c1e;background:linear-gradient(145deg,#fff,rgba(213,228,249,.72))}.modal-icon-circle{color:#fff;background:#11161b;box-shadow:0 8px 20px rgba(17,22,27,.18)}.modal-card__head h3{font-size:18px;letter-spacing:-.02em}.modal-card__form{padding:18px;gap:14px}.segmented-choice{padding:4px;border-radius:999px;background:#e5e9eb}.segmented-choice button{border-radius:999px;color:#516071}.segmented-choice button.active{color:#fff;background:#11161b}.form-group-field label{color:#516071;font:700 9px 'Space Grotesk',sans-serif;letter-spacing:.08em}.form-input,.form-textarea{padding:12px 14px;border:1px solid rgba(140,155,174,.22);border-radius:16px;color:#181c1e;background:#fff;font-size:12px}.form-input:focus,.form-textarea:focus{border-color:#516071}.btn-cancel,.btn-submit{height:44px;border-radius:999px}.btn-submit--success,.btn-submit--primary,.btn-submit--purple{background:#11161b}.sheet-container{border:1px solid rgba(255,255,255,.9);border-radius:28px 28px 0 0;background:#f7fafc;box-shadow:0 -18px 50px rgba(17,22,27,.2)}.sheet-handle{background:#8c9bae}.sheet-category{color:#516071;font-family:'Space Grotesk',sans-serif}.sheet-header h2{color:#181c1e;font-size:20px}.sheet-close-btn{color:#fff;background:#11161b}.info-tile,.sheet-box{border:1px solid rgba(140,155,174,.15);border-radius:16px;background:#fff}.info-tile span,.sheet-box-label{color:#8c9bae;font-family:'Space Grotesk',sans-serif}.info-tile strong,.sheet-box p{color:#181c1e}.sheet-solver-badge{border-radius:16px;color:#516071;background:#e5e9eb}
</style>
