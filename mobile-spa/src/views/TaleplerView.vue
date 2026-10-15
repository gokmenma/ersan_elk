<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import {
  ArrowUpDown,
  Building2,
  Calendar,
  CalendarCheck,
  CalendarDays,
  Check,
  CheckCircle2,
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
  Trash2,
  User,
  Wallet,
  X,
  XCircle,
  Zap,
} from '@lucide/vue'
import TopBar from '../components/TopBar.vue'
import {
  requestsApi,
  requestsState,
  openRequestsDrawer,
  closeRequestsDrawer,
  setRequestsTab,
} from '../services/requests.js'

// State
const activeTab = ref(requestsState.activeTab || 'all') // 'all' | 'avans' | 'izin' | 'talep'
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

      if (activeTab.value !== 'all' && !permissions.value[activeTab.value]) {
        if (permissions.value.avans) activeTab.value = 'avans'
        else if (permissions.value.izin) activeTab.value = 'izin'
        else if (permissions.value.talep) activeTab.value = 'talep'
      }

      requestsState.counts = { ...counts.value }
      requestsState.permissions = { ...permissions.value }
      requestsState.activeTab = activeTab.value
    }
  } catch (err) {
    errorMessage.value = err.message || 'Talepler yüklenemedi.'
  } finally {
    loading.value = false
  }
}

watch(() => requestsState.activeTab, async (newTab) => {
  if (activeTab.value !== newTab) {
    activeTab.value = newTab
    await loadData()
  }
})

onMounted(async () => {
  openRequestsDrawer()
  await loadData()
})

onBeforeUnmount(() => {
  closeRequestsDrawer()
})

async function setTab(tab) {
  activeTab.value = tab
  setRequestsTab(tab)
  await loadData()
}

function setFilter(filt) {
  activeFilter.value = filt
  loadData()
}

// Filtre ve Sıralama
const currentList = computed(() => {
  const list = activeTab.value === 'all'
    ? [...(items.value.avans || []), ...(items.value.izin || []), ...(items.value.talep || [])]
    : (items.value[activeTab.value] || [])
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
    <!-- Standart TopBar: Başlık ve Açıklama Üstte (Geri Butonu Yanında) -->
    <TopBar title="Talepler" subtitle="Onay bekleyen tüm işlemler" :search="false" />

    <!-- 1. Durum Filtresi Pilleri (Bekleyenler / İşlem Görenler / Silinenler) - Başlık Altında En Üstte -->
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
    </div>

    <!-- 2. Arama Kutusu ve Sıralama Yan Yana -->
    <div class="talepler-search-sort-row">
      <div class="search-box talepler-search">
        <Search :size="16" />
        <input
          v-model="searchQuery"
          placeholder="Personel adı, departman veya açıklama ile ara..."
          aria-label="Taleplerde ara"
        />
        <button v-if="searchQuery" type="button" aria-label="Aramayı temizle" @click="searchQuery = ''">
          <X :size="15" />
        </button>
      </div>

      <div class="sort-dropdown-wrap" title="Sıralama">
        <ArrowUpDown :size="16" class="sort-icon" />
        <select v-model="sortBy" aria-label="Sıralama">
          <option value="newest">En Yeni</option>
          <option value="oldest">En Eski</option>
          <option value="name_asc">İsim (A-Z)</option>
          <option value="name_desc">İsim (Z-A)</option>
          <option v-if="activeTab === 'avans'" value="amount_desc">Tutar</option>
        </select>
      </div>
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
            ? (activeTab === 'all' ? 'Bekleyen Talep Yok' : activeTab === 'avans' ? 'Bekleyen Avans Yok' : activeTab === 'izin' ? 'Bekleyen İzin Yok' : 'Bekleyen Genel Talep Yok')
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
/* TaleplerView — Soft Bento & Claymorphic Theme */
.talepler-screen {
  padding-bottom: calc(var(--nav-height) + 20px + env(safe-area-inset-bottom));
}

.ds-requests {
  padding-top: calc(var(--ds-safe-top) + 16px);
  background: var(--ds-canvas);
  color: var(--ds-ink);
  font-family: var(--ds-font-main);
}

/* 1. Bento KPI & Overview Card */
.hero-card--talepler {
  position: relative;
  min-height: 0 !important;
  height: auto !important;
  margin: 0 0 14px;
  padding: 12px 14px;
  border-radius: var(--ds-radius-card);
  color: var(--ds-ink);
  background: var(--ds-surface);
  border: 1px solid var(--ds-border);
  box-shadow: var(--ds-shadow-bento);
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
  padding-right: 12px;
  border-right: 1px solid var(--ds-border-subtle);
}

.kpi-main .kpi-label {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  color: var(--ds-muted);
  font: 700 9px var(--ds-font-mono);
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
  color: var(--ds-ink);
  font-size: 24px;
  font-weight: 800;
  line-height: 1.1;
  letter-spacing: -0.04em;
}

.kpi-main .kpi-value-row small {
  color: var(--ds-muted);
  font: 600 10px var(--ds-font-mono);
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
  border: 1px solid var(--ds-border-subtle);
  border-radius: 14px;
  color: var(--ds-muted);
  background: var(--ds-surface-subtle);
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
  font: 700 9px var(--ds-font-mono);
  color: inherit;
  white-space: nowrap;
}

.kpi-mini-item strong {
  display: block;
  margin-top: 1px;
  font-size: 14px;
  font-weight: 800;
  line-height: 1.1;
  color: inherit;
}

/* Aktif Kategori Butonu */
.kpi-mini-item.is-active {
  background: var(--ds-primary) !important;
  border-color: var(--ds-primary) !important;
  color: var(--ds-surface) !important;
  box-shadow: 0 4px 12px rgba(24, 28, 36, 0.2);
}

/* 2. Durum Filtresi Pilleri */
.status-pills-bar {
  display: flex;
  margin-bottom: 12px;
  align-items: center;
  width: 100%;
}

.status-pills-group {
  display: flex;
  min-width: 0;
  width: 100%;
  gap: 8px;
  overflow-x: auto;
  scrollbar-width: none;
}

.status-pills-group::-webkit-scrollbar { display: none; }

.status-pills-group .pill-btn {
  flex: 1;
  justify-content: center;
  min-width: 0;
}

.pill-btn {
  display: inline-flex;
  align-items: center;
  height: 36px;
  padding: 0 12px;
  gap: 6px;
  border: 1px solid var(--ds-border);
  border-radius: var(--ds-radius-pill);
  color: var(--ds-muted);
  background: var(--ds-surface);
  box-shadow: var(--ds-shadow-pill);
  font: 600 11px var(--ds-font-main);
  white-space: nowrap;
  cursor: pointer;
  transition: all 0.15s ease;
}

.pill-btn:active {
  transform: scale(0.96);
}

.pill-btn.active {
  border-color: var(--ds-primary);
  color: var(--ds-surface);
  background: var(--ds-primary);
  box-shadow: 0 4px 12px rgba(24, 28, 36, 0.2);
}

.pill-btn--danger.active {
  border-color: var(--ds-danger);
  background: var(--ds-danger);
  box-shadow: 0 4px 12px rgba(239, 68, 68, 0.25);
}

/* 2. Arama ve Sıralama Yan Yana */
.talepler-search-sort-row {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 14px;
}

.talepler-search-sort-row .talepler-search {
  flex: 1;
  margin-bottom: 0;
  height: 44px;
}

.talepler-search-sort-row .sort-dropdown-wrap {
  width: 44px;
  height: 44px;
  min-width: 44px;
}

/* Sıralama Butonu */
.sort-dropdown-wrap {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 44px;
  height: 44px;
  min-width: 44px;
  border: 1px solid var(--ds-border);
  border-radius: 50%;
  background: var(--ds-surface);
  box-shadow: var(--ds-shadow-pill);
  cursor: pointer;
  flex-shrink: 0;
  transition: transform 0.15s ease;
}

.sort-dropdown-wrap:active {
  transform: scale(0.95);
}

.sort-icon {
  pointer-events: none;
  color: var(--ds-muted);
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
}

/* 3. Arama Çubuğu */
.talepler-search {
  display: flex;
  align-items: center;
  gap: 10px;
  height: 44px;
  margin-bottom: 14px;
  padding: 0 14px;
  border: 1px solid var(--ds-border);
  border-radius: var(--ds-radius-pill);
  color: var(--ds-muted);
  background: var(--ds-surface);
  box-shadow: var(--ds-shadow-pill);
}

.talepler-search input {
  flex: 1;
  border: 0;
  outline: 0;
  background: transparent;
  color: var(--ds-ink);
  font: 600 13px var(--ds-font-main);
}

.talepler-search input::placeholder { color: var(--ds-muted); }

.talepler-search button {
  display: grid;
  width: 26px;
  height: 26px;
  place-items: center;
  border-radius: 50%;
  background: var(--ds-surface-subtle);
  border: 0;
  color: var(--ds-muted);
  cursor: pointer;
}

/* 4. Talep Kartları */
.talepler-list {
  display: grid;
  gap: 10px;
}

.t-card {
  padding: 0;
  overflow: hidden;
  border: 1px solid var(--ds-border);
  border-radius: var(--ds-radius-tile);
  background: var(--ds-surface);
  box-shadow: var(--ds-shadow-tile);
  cursor: pointer;
  transition: transform 0.15s ease;
}

.t-card:active {
  transform: scale(0.985);
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
  width: 42px;
  height: 42px;
  border-radius: 14px;
  color: var(--ds-surface);
  background: var(--ds-primary);
  font: 700 13px var(--ds-font-mono);
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
  color: var(--ds-ink);
  font-size: 14px;
  font-weight: 800;
  letter-spacing: -0.02em;
}

.t-card__meta p {
  margin: 3px 0 0;
  color: var(--ds-muted);
  font-size: 11px;
}

.t-card__badge-wrap {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 4px;
}

.badge-amount, .badge-days {
  border-radius: var(--ds-radius-pill);
  padding: 3px 9px;
  font: 700 11px var(--ds-font-mono);
}

.badge-amount {
  color: var(--ds-emerald-ink);
  background: var(--ds-emerald-soft);
}

.badge-days {
  color: var(--ds-blue-ink);
  background: var(--ds-blue-soft);
}

.status-chip {
  border-radius: var(--ds-radius-pill);
  padding: 2px 7px;
  font: 700 9px var(--ds-font-mono);
}

.status-chip--pending { background: var(--ds-surface-subtle); color: var(--ds-muted); }
.status-chip--approved { background: var(--ds-emerald-soft); color: var(--ds-emerald-ink); }
.status-chip--rejected { background: var(--ds-danger-soft); color: var(--ds-danger-ink); }
.status-chip--deleted { background: var(--ds-danger-soft); color: var(--ds-danger-ink); }

.t-card__body {
  padding: 0 16px 14px;
}

.talep-heading {
  margin: 0 0 6px;
  color: var(--ds-ink);
  font-size: 13px;
  font-weight: 700;
}

.t-card__quote {
  padding: 10px 12px;
  border: 1px solid var(--ds-border-subtle);
  border-radius: 14px;
  color: var(--ds-ink);
  background: var(--ds-surface-subtle);
  font-size: 11px;
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
  color: var(--ds-muted);
  font: 600 10px var(--ds-font-mono);
}

.solver-bar {
  margin-top: 8px;
  padding: 6px 10px;
  border-radius: 10px;
  color: var(--ds-muted);
  background: var(--ds-surface-subtle);
  font-size: 10px;
  display: flex;
  align-items: center;
  gap: 4px;
}

.t-card__actions {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 14px;
  border-top: 1px solid var(--ds-border-subtle);
  background: var(--ds-surface-subtle);
}

.act-btn {
  flex: 1;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 5px;
  height: 38px;
  border: 0;
  border-radius: var(--ds-radius-pill);
  font: 700 11px var(--ds-font-main);
  cursor: pointer;
  transition: transform 0.1s ease;
}

.act-btn:active { transform: scale(0.97); }

.act-btn--approve, .act-btn--primary, .act-btn--purple {
  color: var(--ds-surface);
  background: var(--ds-primary);
  box-shadow: 0 2px 8px rgba(24, 28, 36, 0.2);
}

.act-btn--reject {
  color: var(--ds-danger);
  background: var(--ds-danger-soft);
}

.act-btn--neutral {
  color: var(--ds-muted);
  background: var(--ds-surface);
}

.act-btn-delete {
  width: 38px;
  height: 38px;
  border: 0;
  border-radius: 50%;
  color: var(--ds-danger);
  background: var(--ds-danger-soft);
  display: grid;
  place-items: center;
  cursor: pointer;
}

/* Empty State & Toast */
.empty-state-card {
  padding: 36px 20px;
  border: 1px solid var(--ds-border);
  border-radius: var(--ds-radius-tile);
  background: var(--ds-surface);
  box-shadow: var(--ds-shadow-tile);
  text-align: center;
}

.empty-icon-bubble {
  width: 52px;
  height: 52px;
  border-radius: 18px;
  color: var(--ds-primary);
  background: var(--ds-surface-subtle);
  display: grid;
  place-items: center;
  margin: 0 auto 12px;
}

.empty-icon-bubble.is-pending {
  background: var(--ds-emerald-soft);
  color: var(--ds-emerald-ink);
}

.empty-state-card h3 {
  margin: 0;
  color: var(--ds-ink);
  font-size: 15px;
  font-weight: 800;
}

.empty-state-card p {
  margin: 4px 0 16px;
  color: var(--ds-muted);
  font-size: 11px;
}

.btn-subtle-refresh {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 8px 16px;
  border: 0;
  border-radius: var(--ds-radius-pill);
  color: var(--ds-surface);
  background: var(--ds-primary);
  font-size: 11px;
  font-weight: 700;
  cursor: pointer;
}

.toast-floating {
  display: flex;
  align-items: center;
  gap: 7px;
  padding: 10px 16px;
  border-radius: var(--ds-radius-pill);
  color: var(--ds-surface);
  background: var(--ds-primary);
  box-shadow: var(--ds-shadow-float);
  margin-bottom: 12px;
  font-size: 12px;
  font-weight: 700;
}

/* Modallar & Sheets */
.modal-backdrop, .sheet-backdrop {
  position: fixed;
  inset: 0;
  z-index: 120;
  background: rgba(15, 23, 42, 0.45);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
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
  width: min(100%, 380px);
  border: 1px solid var(--ds-border);
  border-radius: 28px;
  background: var(--ds-surface);
  box-shadow: var(--ds-shadow-float);
  overflow: hidden;
}

.modal-card__head {
  padding: 22px 18px 15px;
  color: var(--ds-ink);
  background: var(--ds-surface-subtle);
  text-align: center;
}

.modal-icon-circle {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  color: var(--ds-surface);
  background: var(--ds-primary);
  box-shadow: 0 4px 12px rgba(24, 28, 36, 0.2);
  display: grid;
  place-items: center;
  margin: 0 auto 8px;
}

.modal-card__head h3 {
  margin: 0;
  font-size: 17px;
  font-weight: 800;
  letter-spacing: -0.02em;
}

.modal-card__head p {
  margin: 3px 0 0;
  font-size: 11px;
  color: var(--ds-muted);
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
  border-radius: var(--ds-radius-pill);
  background: var(--ds-surface-subtle);
}

.segmented-choice button {
  padding: 8px 4px;
  border: 0;
  border-radius: var(--ds-radius-pill);
  color: var(--ds-muted);
  font: 700 11px var(--ds-font-main);
  cursor: pointer;
}

.segmented-choice button.active {
  color: var(--ds-surface);
  background: var(--ds-primary);
}

.form-group-field {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.form-group-field label {
  color: var(--ds-muted);
  font: 700 9px var(--ds-font-mono);
  letter-spacing: 0.08em;
}

.form-input, .form-textarea {
  width: 100%;
  padding: 12px 14px;
  border: 1px solid var(--ds-border-subtle);
  border-radius: var(--ds-radius-sm);
  color: var(--ds-ink);
  background: var(--ds-surface-subtle);
  font: 600 13px var(--ds-font-main);
  outline: none;
}

.form-input:focus, .form-textarea:focus {
  background: var(--ds-surface);
  border-color: var(--ds-primary);
  box-shadow: 0 0 0 3px rgba(24, 28, 36, 0.08);
}

.toggle-bar {
  display: flex;
  align-items: center;
  gap: 9px;
  padding: 10px 12px;
  border-radius: 14px;
  background: var(--ds-emerald-soft);
  border: 1px solid var(--ds-emerald-soft);
  cursor: pointer;
}

.toggle-bar strong {
  display: block;
  font-size: 11px;
  color: var(--ds-emerald-ink);
}

.toggle-bar small {
  display: block;
  font-size: 9px;
  color: var(--ds-emerald-ink);
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
  border-radius: var(--ds-radius-pill);
  border: 0;
  font: 700 12px var(--ds-font-main);
  cursor: pointer;
}

.btn-cancel {
  background: var(--ds-surface-subtle);
  color: var(--ds-muted);
}

.btn-submit {
  color: var(--ds-surface);
  background: var(--ds-primary);
}

.btn-submit--danger {
  background: var(--ds-danger);
}

/* Bottom Sheet */
.sheet-container {
  width: min(100%, 480px);
  max-height: 85vh;
  margin: 0 auto;
  border: 1px solid var(--ds-border);
  border-radius: 28px 28px 0 0;
  background: var(--ds-surface);
  box-shadow: var(--ds-shadow-float);
  padding: 14px 18px calc(24px + env(safe-area-inset-bottom));
  overflow-y: auto;
}

.sheet-handle {
  width: 40px;
  height: 4px;
  background: var(--ds-muted-light);
  border-radius: var(--ds-radius-pill);
  margin: 0 auto 14px;
  cursor: pointer;
}

.sheet-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-bottom: 12px;
  border-bottom: 1px solid var(--ds-border-subtle);
}

.sheet-category {
  color: var(--ds-muted);
  font: 700 9px var(--ds-font-mono);
  letter-spacing: 0.1em;
}

.sheet-header h2 {
  margin: 2px 0 0;
  color: var(--ds-ink);
  font-size: 19px;
  font-weight: 800;
}

.sheet-header small {
  color: var(--ds-muted);
  font-size: 11px;
}

.sheet-close-btn {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  border: 0;
  color: var(--ds-surface);
  background: var(--ds-primary);
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
  padding: 12px 14px;
  border: 1px solid var(--ds-border-subtle);
  border-radius: 16px;
  background: var(--ds-surface-subtle);
}

.info-tile.col-span-2 { grid-column: span 2; }

.info-tile span, .sheet-box-label {
  display: block;
  color: var(--ds-muted);
  font: 700 9px var(--ds-font-mono);
  letter-spacing: 0.08em;
}

.info-tile strong, .sheet-box p {
  display: block;
  margin-top: 2px;
  color: var(--ds-ink);
  font-size: 13px;
  font-weight: 700;
}

.sheet-box.box-success {
  background: var(--ds-emerald-soft);
  border-color: var(--ds-emerald-soft);
}

.sheet-box.box-success p {
  color: var(--ds-emerald-ink);
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
  border-radius: 14px;
  color: var(--ds-muted);
  background: var(--ds-surface-subtle);
  font-size: 11px;
}
</style>
