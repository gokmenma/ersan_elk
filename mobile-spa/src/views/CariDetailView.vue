<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  ArrowDownLeft,
  ArrowLeft,
  ArrowUpRight,
  Building2,
  Calendar,
  Check,
  ChevronRight,
  CreditCard,
  FileDown,
  FileText,
  Mail,
  MapPin,
  Paperclip,
  Pencil,
  Phone,
  Plus,
  Receipt,
  RefreshCw,
  Share2,
  StickyNote,
  Trash2,
  TrendingDown,
  TrendingUp,
  Wallet,
  X,
} from '@lucide/vue'
import { cariApi } from '../services/cari.js'
import { auth } from '../services/auth.js'

const route = useRoute()
const router = useRouter()

const loading = ref(true)
const error = ref('')
const cari = ref(null)
const hareketler = ref([])
const movementFilter = ref('all')

const filteredHareketler = computed(() => {
  if (movementFilter.value === 'all') return hareketler.value
  return hareketler.value.filter((hareket) => hareket.type === movementFilter.value)
})

function setMovementFilter(filter) {
  movementFilter.value = movementFilter.value === filter ? 'all' : filter
}

// Not Modalı Durumu
const isNoteModalOpen = ref(false)
const noteText = ref('')
const noteLoading = ref(false)

// Cari Düzenleme Modalı
const isCariEditModalOpen = ref(false)
const cariEditForm = ref({
  CariAdi: '',
  firma: '',
  Telefon: '',
  Email: '',
  il: '',
  ilce: '',
  Adres: '',
})
const cariEditLoading = ref(false)
const cariEditError = ref('')

// Hızlı İşlem Ekle Modalı
const isQuickActionOpen = ref(false)
const quickFileInput = ref(null)
const quickActionForm = ref({
  id: null,
  type: 'aldim',
  tutar: '',
  islem_tarihi: '',
  belge_no: '',
  aciklama: '',
  file: null,
  existing_file: null,
})
const quickActionLoading = ref(false)
const quickActionError = ref('')

// Hareket Detay Modalı
const isMovementDetailOpen = ref(false)
const selectedMovement = ref(null)
const movementDeleteLoading = ref(false)

function formatMoney(amount) {
  if (amount === undefined || amount === null) return '0,00 ₺'
  const val = Number(amount) || 0
  return new Intl.NumberFormat('tr-TR', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(Math.abs(val)) + ' ₺'
}

function formatPhone(phone) {
  if (!phone) return ''
  const cleaned = String(phone).replace(/\D/g, '')
  if (cleaned.length === 10) {
    return `0${cleaned.slice(0, 3)} ${cleaned.slice(3, 6)} ${cleaned.slice(6, 8)} ${cleaned.slice(8, 10)}`
  } else if (cleaned.length === 11 && cleaned.startsWith('0')) {
    return `${cleaned.slice(0, 4)} ${cleaned.slice(4, 7)} ${cleaned.slice(7, 9)} ${cleaned.slice(9, 11)}`
  }
  return phone
}

function formatFileSize(bytes) {
  if (!bytes || bytes <= 0) return '0 B'
  const k = 1024
  const sizes = ['B', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return `${parseFloat((bytes / Math.pow(k, i)).toFixed(1))} ${sizes[i]}`
}

function removeQuickActionFile() {
  quickActionForm.value.file = null
  if (quickFileInput.value) {
    quickFileInput.value.value = ''
  }
}

function setQuickDescription(text) {
  quickActionForm.value.aciklama = text
}

function addQuickAmount(delta) {
  const current = Number(quickActionForm.value.tutar) || 0
  quickActionForm.value.tutar = String(current + delta)
}

function getTodayDateTimeInput() {
  const now = new Date()
  const offset = now.getTimezoneOffset() * 60000
  return new Date(now.getTime() - offset).toISOString().slice(0, 16)
}

async function loadDetail() {
  loading.value = true
  error.value = ''
  try {
    const res = await cariApi.detail(route.params.id)
    cari.value = res.cari
    hareketler.value = res.hareketler || []
    noteText.value = cari.value.notes || ''
  } catch (err) {
    error.value = err.message || 'Cari detayları yüklenemedi.'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadDetail()
})

// İletişim Aksiyonları
function phoneHref(phone) {
  return `tel:${String(phone || '').replace(/[^+\d]/g, '')}`
}

function shareCari() {
  if (!cari.value) return
  const bakiyeStr = `${cari.value.bakiye < 0 ? '-' : cari.value.bakiye > 0 ? '+' : ''}${formatMoney(cari.value.bakiye)}`
  const text = `Cari Bilgisi: ${cari.value.name}\nBakiye: ${bakiyeStr}\nTelefon: ${cari.value.phone || '-'}`
  if (navigator.share) {
    navigator.share({
      title: cari.value.name,
      text,
    }).catch(() => {})
  } else {
    navigator.clipboard.writeText(text).then(() => {
      alert('Cari bilgisi panoya kopyalandı.')
    })
  }
}

// Cari Düzenle
function openEditCari() {
  if (!cari.value) return
  cariEditForm.value = {
    CariAdi: cari.value.name || '',
    firma: cari.value.company || '',
    Telefon: cari.value.phone || '',
    Email: cari.value.email || '',
    il: cari.value.city || '',
    ilce: cari.value.district || '',
    Adres: cari.value.address || '',
  }
  cariEditError.value = ''
  isCariEditModalOpen.value = true
}

async function submitCariEdit() {
  if (!cariEditForm.value.CariAdi.trim()) {
    cariEditError.value = 'Cari adı zorunludur.'
    return
  }
  cariEditLoading.value = true
  cariEditError.value = ''
  try {
    await cariApi.saveCari({
      id: route.params.id,
      ...cariEditForm.value,
    }, auth.state.csrfToken)
    isCariEditModalOpen.value = false
    await loadDetail()
  } catch (err) {
    cariEditError.value = err.message || 'Cari güncellenemedi.'
  } finally {
    cariEditLoading.value = false
  }
}

// Cari Sil
async function deleteCari() {
  if (!confirm(`${cari.value.name} kaydını silmek istediğinize emin misiniz?`)) return
  const deleteMovements = confirm('Bu cariye ait hesap hareketleri de silinsin mi?')

  try {
    await cariApi.deleteCari(route.params.id, deleteMovements, auth.state.csrfToken)
    alert('Cari kaydı başarıyla silindi.')
    router.replace('/cari-takip')
  } catch (err) {
    alert(err.message || 'Cari silinemedi.')
  }
}

// Not Kaydet
function openNoteModal() {
  noteText.value = cari.value?.notes || ''
  isNoteModalOpen.value = true
}

async function saveNote() {
  noteLoading.value = true
  try {
    await cariApi.saveNote(route.params.id, noteText.value, auth.state.csrfToken)
    cari.value.notes = noteText.value
    isNoteModalOpen.value = false
  } catch (err) {
    alert(err.message || 'Not kaydedilemedi.')
  } finally {
    noteLoading.value = false
  }
}

// Hızlı İşlem Ekle
function openQuickAction() {
  quickActionForm.value = {
    id: null,
    type: 'aldim',
    tutar: '',
    islem_tarihi: getTodayDateTimeInput(),
    belge_no: '',
    aciklama: '',
    file: null,
    existing_file: null,
  }
  if (quickFileInput.value) {
    quickFileInput.value.value = ''
  }
  quickActionError.value = ''
  isQuickActionOpen.value = true
}

// Hareket Düzenle Modalı Aç
function openEditMovement(m) {
  const mov = m || selectedMovement.value
  if (!mov) return

  let dateVal = getTodayDateTimeInput()
  if (mov.date) {
    const d = new Date(mov.date)
    if (!isNaN(d.getTime())) {
      const offset = d.getTimezoneOffset() * 60000
      dateVal = new Date(d.getTime() - offset).toISOString().slice(0, 16)
    }
  }

  quickActionForm.value = {
    id: mov.id,
    type: mov.type || (mov.borc > 0 ? 'aldim' : 'verdim'),
    tutar: String(mov.amount ?? (mov.borc > 0 ? mov.borc : mov.alacak)),
    islem_tarihi: dateVal,
    belge_no: mov.belge_no || '',
    aciklama: mov.aciklama || '',
    file: null,
    existing_file: mov.file_url || null,
  }

  if (quickFileInput.value) {
    quickFileInput.value.value = ''
  }
  quickActionError.value = ''
  isMovementDetailOpen.value = false
  isQuickActionOpen.value = true
}

function handleQuickActionFileChange(e) {
  const files = e.target.files
  if (files && files.length > 0) {
    quickActionForm.value.file = files[0]
  }
}

async function submitQuickAction() {
  if (!quickActionForm.value.tutar || Number(quickActionForm.value.tutar) <= 0) {
    quickActionError.value = 'Lütfen geçerli bir tutar girin.'
    return
  }
  quickActionLoading.value = true
  quickActionError.value = ''

  try {
    const formData = new FormData()
    formData.append('cari_id', route.params.id)
    if (quickActionForm.value.id) {
      formData.append('hareket_id', quickActionForm.value.id)
    }
    formData.append('type', quickActionForm.value.type)
    formData.append('tutar', quickActionForm.value.tutar)
    formData.append('islem_tarihi', quickActionForm.value.islem_tarihi)
    formData.append('belge_no', quickActionForm.value.belge_no)
    formData.append('aciklama', quickActionForm.value.aciklama)
    if (quickActionForm.value.file) {
      formData.append('dosya', quickActionForm.value.file)
    }

    await cariApi.saveHareket(formData, auth.state.csrfToken)
    isQuickActionOpen.value = false
    await loadDetail()
  } catch (err) {
    quickActionError.value = err.message || 'İşlem kaydedilemedi.'
  } finally {
    quickActionLoading.value = false
  }
}

// Hareket Detay ve Sil
function openMovementDetail(m) {
  selectedMovement.value = m
  isMovementDetailOpen.value = true
}

async function deleteSelectedMovement() {
  if (!selectedMovement.value) return
  if (!confirm('Bu hareketi silmek istediğinize emin misiniz?')) return

  movementDeleteLoading.value = true
  try {
    await cariApi.deleteHareket(selectedMovement.value.id, auth.state.csrfToken)
    isMovementDetailOpen.value = false
    selectedMovement.value = null
    await loadDetail()
  } catch (err) {
    alert(err.message || 'Hareket silinemedi.')
  } finally {
    movementDeleteLoading.value = false
  }
}
</script>

<template>
  <section class="ds-screen ds-cari-detail-screen">
    <!-- Topbar -->
    <header class="detail-topbar">
      <button type="button" class="topbar-btn" aria-label="Geri dön" @click="router.push('/cari-takip')">
        <ArrowLeft :size="20" />
      </button>
      <div class="topbar-title">
        <span>HESAP HAREKETLERİ</span>
        <h1>{{ cari?.name || 'Cari Detayı' }}</h1>
      </div>
      <div class="topbar-actions">
        <button
          v-if="cari"
          type="button"
          class="topbar-btn topbar-btn--primary"
          aria-label="Yeni İşlem Ekle"
          title="Yeni İşlem Ekle"
          @click="openQuickAction"
        >
          <Plus :size="20" />
        </button>
        <button v-if="cari" type="button" class="topbar-btn" aria-label="Düzenle" title="Cariyi Düzenle" @click="openEditCari">
          <Pencil :size="17" />
        </button>
        <button v-if="cari" type="button" class="topbar-btn topbar-btn--danger" aria-label="Sil" title="Cariyi Sil" @click="deleteCari">
          <Trash2 :size="17" />
        </button>
      </div>
    </header>

    <!-- Yükleniyor / Hata Durumu -->
    <div v-if="loading" class="ds-loading">
      <i v-for="n in 3" :key="n" style="height: 110px;"></i>
    </div>

    <div v-else-if="error" class="ds-state error-state">
      <RefreshCw :size="24" />
      <strong>Cari Bilgisi Alınamadı</strong>
      <p>{{ error }}</p>
      <button type="button" @click="loadDetail">Tekrar Dene</button>
    </div>

    <!-- Cari Detay İçeriği -->
    <template v-else-if="cari">
      <!-- Hero Profil Kartı -->
      <section class="detail-hero-card">
        <div class="hero-profile-row">
          <div class="hero-avatar">
            {{ cari.initials }}
          </div>
          <div class="hero-info">
            <h2>{{ cari.name }}</h2>
            <p v-if="cari.company" class="hero-company">
              <Building2 :size="12" /> {{ cari.company }}
            </p>
            <div class="hero-meta">
              <span v-if="cari.phone">
                <Phone :size="12" /> {{ formatPhone(cari.phone) }}
              </span>
              <span v-if="cari.district || cari.city">
                <MapPin :size="12" /> {{ [cari.district, cari.city].filter(Boolean).join(', ') }}
              </span>
              <span v-if="cari.email">
                <Mail :size="12" /> {{ cari.email }}
              </span>
            </div>
          </div>
        </div>

        <!-- Mini Özet Kartları (Soft Bento Pills) -->
        <div class="hero-summary-grid">
          <button
            type="button"
            class="mini-summary-item summary-item--aldim mini-summary-filter"
            :class="{ 'is-active': movementFilter === 'aldim' }"
            :aria-pressed="movementFilter === 'aldim'"
            @click="setMovementFilter('aldim')"
          >
            <div class="summary-label">
              <span class="summary-dot summary-dot--aldim"></span>
              <small>TOP. ALDIM</small>
            </div>
            <strong class="text-aldim">{{ formatMoney(cari.toplam_borc) }}</strong>
          </button>
          <button
            type="button"
            class="mini-summary-item summary-item--verdim mini-summary-filter"
            :class="{ 'is-active': movementFilter === 'verdim' }"
            :aria-pressed="movementFilter === 'verdim'"
            @click="setMovementFilter('verdim')"
          >
            <div class="summary-label">
              <span class="summary-dot summary-dot--verdim"></span>
              <small>TOP. VERDİM</small>
            </div>
            <strong class="text-verdim">{{ formatMoney(cari.toplam_alacak) }}</strong>
          </button>
          <div
            class="mini-summary-item"
            :class="cari.bakiye < 0 ? 'summary-item--borclu' : cari.bakiye > 0 ? 'summary-item--alacakli' : 'summary-item--neutral'"
          >
            <div class="summary-label">
              <span
                class="summary-dot"
                :class="cari.bakiye < 0 ? 'summary-dot--borclu' : cari.bakiye > 0 ? 'summary-dot--alacakli' : 'summary-dot--neutral'"
              ></span>
              <small>{{ cari.bakiye < 0 ? 'BORÇLUYUM' : cari.bakiye > 0 ? 'ALACAKLIYIM' : 'NET BAKİYE' }}</small>
            </div>
            <strong :class="cari.bakiye < 0 ? 'text-borclu' : cari.bakiye > 0 ? 'text-alacakli' : 'text-neutral'">
              {{ cari.bakiye < 0 ? '-' : cari.bakiye > 0 ? '+' : '' }}{{ formatMoney(cari.bakiye) }}
            </strong>
          </div>
        </div>

        <!-- Hızlı Aksiyon Butonları (Not, Ara, Paylaş, Ekstre) -->
        <div class="hero-actions-row">
          <button type="button" class="hero-action-btn action-btn--note" @click="openNoteModal">
            <div class="action-icon-circle"><StickyNote :size="18" /></div>
            <span>Cari Notu</span>
          </button>
          <a v-if="cari.phone" :href="phoneHref(cari.phone)" class="hero-action-btn action-btn--phone">
            <div class="action-icon-circle"><Phone :size="18" /></div>
            <span>Ara</span>
          </a>
          <div v-else class="hero-action-btn action-btn--disabled">
            <div class="action-icon-circle"><Phone :size="18" /></div>
            <span>Ara</span>
          </div>
          <button type="button" class="hero-action-btn action-btn--share" @click="shareCari">
            <div class="action-icon-circle"><Share2 :size="18" /></div>
            <span>Paylaş</span>
          </button>
          <a v-if="cari.ekstre_pdf_url" :href="cari.ekstre_pdf_url" target="_blank" class="hero-action-btn action-btn--pdf">
            <div class="action-icon-circle"><FileDown :size="18" /></div>
            <span>Ekstre PDF</span>
          </a>
        </div>

        <!-- Cari Notu Vurgusu (varsa) -->
        <div v-if="cari.notes" class="hero-note-banner" @click="openNoteModal">
          <StickyNote :size="16" />
          <p>{{ cari.notes }}</p>
          <Pencil :size="13" class="note-edit-icon" />
        </div>
      </section>

      <!-- Hareketler Başlığı & Sayacı -->
      <section class="movements-header-row">
        <div class="movements-header-title">
          <h3>Hesap Hareketleri</h3>
          <span class="movements-badge">{{ filteredHareketler.length }} işlem</span>
        </div>
        <button type="button" class="add-movement-trigger-btn" @click="openQuickAction">
          <Plus :size="15" />
          <span>İşlem Ekle</span>
        </button>
      </section>

      <!-- Hareket Listesi -->
      <section class="movements-list-section">
        <div v-if="!filteredHareketler.length" class="movements-empty">
          <div class="empty-icon-wrap">
            <Receipt :size="30" />
          </div>
          <strong>{{ hareketler.length ? 'Bu Türde Hareket Yok' : 'Henüz Hareket Kaydı Yok' }}</strong>
          <p>{{ hareketler.length ? 'Diğer hareketleri görmek için seçili özete yeniden dokunun.' : 'Bu cariye ait ilk tahsilat veya ödeme işlemini hemen ekleyebilirsiniz.' }}</p>
          <button v-if="!hareketler.length" type="button" class="empty-add-btn" @click="openQuickAction">
            <Plus :size="15" />
            <span>İlk İşlemi Ekle</span>
          </button>
        </div>

        <div v-else class="movements-container">
          <article
            v-for="h in filteredHareketler"
            :key="h.id"
            class="movement-row-card"
            @click="openMovementDetail(h)"
          >
            <!-- Tip İkonu -->
            <div
              class="movement-type-box"
              :class="h.type === 'aldim' ? 'type-aldim' : 'type-verdim'"
            >
              <ArrowDownLeft v-if="h.type === 'aldim'" :size="18" />
              <ArrowUpRight v-else :size="18" />
            </div>

            <!-- Bilgiler -->
            <div class="movement-info">
              <div class="movement-date-row">
                <span class="m-date">{{ h.date_formatted }}</span>
                <span v-if="h.belge_no" class="m-doc-badge">
                  <Receipt :size="10" /> {{ h.belge_no }}
                </span>
                <span v-if="h.has_file" class="m-file-badge" title="Ekli Belge Var">
                  <Paperclip :size="10" /> Ek
                </span>
              </div>
              <p class="movement-desc">{{ h.aciklama || (h.type === 'aldim' ? 'Tahsilat (Aldım)' : 'Ödeme (Verdim)') }}</p>
              <small v-if="h.ekleyen_adi" class="movement-author">Ekleyen: {{ h.ekleyen_adi }}</small>
            </div>

            <!-- Tutar & Yürüyen Bakiye -->
            <div class="movement-amounts">
              <span
                class="m-amount"
                :class="h.type === 'aldim' ? 'text-aldim' : 'text-verdim'"
              >
                {{ h.type === 'aldim' ? '-' : '+' }}{{ formatMoney(h.amount) }}
              </span>
              <span
                class="m-yuruyen"
                :class="h.yuruyen_bakiye < 0 ? 'text-borclu' : h.yuruyen_bakiye > 0 ? 'text-alacakli' : ''"
              >
                Bakiye: {{ formatMoney(Math.abs(h.yuruyen_bakiye)) }} {{ h.yuruyen_bakiye < 0 ? '(B)' : h.yuruyen_bakiye > 0 ? '(A)' : '' }}
              </span>
            </div>

            <!-- Sağ Ok -->
            <ChevronRight :size="15" class="movement-chevron" />
          </article>
        </div>
      </section>
    </template>

    <!-- ---------------------------------------------------------------- -->
    <!-- MODAL 1: HIZLI İŞLEM EKLE (KULLANICI DOSTU BOTTOM SHEET) -->
    <!-- ---------------------------------------------------------------- -->
    <Transition name="ds-modal">
      <div v-if="isQuickActionOpen" class="ds-modal-layer" @click.self="isQuickActionOpen = false">
        <div class="ds-bottom-sheet" role="dialog" aria-modal="true" aria-label="İşlem Ekle">
          <!-- Sheet Header -->
          <div class="sheet-header">
            <div class="sheet-title">
              <div class="sheet-icon-box">
                <Pencil v-if="quickActionForm.id" :size="18" />
                <CreditCard v-else :size="18" />
              </div>
              <div>
                <h3>{{ quickActionForm.id ? 'İşlemi Düzenle' : 'Yeni İşlem Ekle' }}</h3>
                <small>{{ cari?.name }}</small>
              </div>
            </div>
            <button type="button" class="sheet-close-btn" aria-label="Kapat" @click="isQuickActionOpen = false">
              <X :size="18" />
            </button>
          </div>

          <form class="sheet-form" @submit.prevent="submitQuickAction">
            <!-- 1. İşlem Yönü (Segmented Toggle) -->
            <div class="type-segmented-control">
              <button
                type="button"
                class="seg-btn seg-btn--aldim"
                :class="{ 'is-active': quickActionForm.type === 'aldim' }"
                @click="quickActionForm.type = 'aldim'"
              >
                <div class="seg-icon-box">
                  <ArrowDownLeft :size="18" />
                </div>
                <div class="seg-text">
                  <strong>Aldım (-)</strong>
                  <small>Borç Artışı / Giriş</small>
                </div>
              </button>

              <button
                type="button"
                class="seg-btn seg-btn--verdim"
                :class="{ 'is-active': quickActionForm.type === 'verdim' }"
                @click="quickActionForm.type = 'verdim'"
              >
                <div class="seg-icon-box">
                  <ArrowUpRight :size="18" />
                </div>
                <div class="seg-text">
                  <strong>Verdim (+)</strong>
                  <small>Alacak Artışı / Çıkış</small>
                </div>
              </button>
            </div>

            <!-- 2. Tutar ve Tarih Alanları -->
            <div class="form-grid-two">
              <div class="form-group">
                <label>Tutar (₺)*</label>
                <div class="input-wrap amount-wrap">
                  <span class="currency-badge">₺</span>
                  <input
                    v-model="quickActionForm.tutar"
                    type="number"
                    step="0.01"
                    min="0.01"
                    placeholder="0,00"
                    required
                    class="input-amount"
                    inputmode="decimal"
                  />
                </div>
              </div>

              <div class="form-group">
                <label>İşlem Tarihi*</label>
                <div class="input-wrap">
                  <Calendar :size="16" class="input-icon" />
                  <input v-model="quickActionForm.islem_tarihi" type="datetime-local" required />
                </div>
              </div>
            </div>

            <!-- Hızlı Tutar Çipleri -->
            <div class="quick-chips-row">
              <button type="button" class="quick-chip" @click="addQuickAmount(500)">+500 ₺</button>
              <button type="button" class="quick-chip" @click="addQuickAmount(1000)">+1.000 ₺</button>
              <button type="button" class="quick-chip" @click="addQuickAmount(5000)">+5.000 ₺</button>
              <button
                v-if="cari?.bakiye && Math.abs(cari.bakiye) > 0"
                type="button"
                class="quick-chip quick-chip--bakiye"
                @click="quickActionForm.tutar = String(Math.abs(cari.bakiye))"
              >
                Tam Bakiye ({{ formatMoney(cari.bakiye) }})
              </button>
            </div>

            <!-- 3. Belge No -->
            <div class="form-group">
              <label>Belge / Dekont No</label>
              <div class="input-wrap">
                <Receipt :size="16" class="input-icon" />
                <input v-model="quickActionForm.belge_no" type="text" placeholder="Fatura / Makbuz / Havale No" />
              </div>
            </div>

            <!-- 4. Açıklama & Hızlı Şablonlar -->
            <div class="form-group">
              <div class="label-with-presets">
                <label>Açıklama</label>
                <div class="quick-presets">
                  <button
                    v-for="preset in ['Nakit', 'Havale/EFT', 'Fatura Ödemesi', 'Mal/Hizmet']"
                    :key="preset"
                    type="button"
                    class="preset-pill"
                    @click="setQuickDescription(preset)"
                  >
                    {{ preset }}
                  </button>
                </div>
              </div>
              <div class="input-wrap textarea-wrap">
                <FileText :size="16" class="input-icon textarea-icon" />
                <textarea v-model="quickActionForm.aciklama" rows="2" placeholder="Örn: 2. taksit havalesi, nakit tahsilat…"></textarea>
              </div>
            </div>

            <!-- 5. Belge / Dekont Dosya Yükleme (Custom Bento Dropzone) -->
            <div class="form-group">
              <label>Belge / Dekont (Opsiyonel)</label>

              <!-- Henüz Dosya Seçilmediğinde -->
              <div
                v-if="!quickActionForm.file"
                class="upload-dropzone"
                @click="$refs.quickFileInput.click()"
              >
                <input
                  ref="quickFileInput"
                  type="file"
                  accept="image/*,application/pdf"
                  class="hidden-file-input"
                  @change="handleQuickActionFileChange"
                />
                <div class="dropzone-icon">
                  <Paperclip :size="18" />
                </div>
                <div class="dropzone-text">
                  <strong>Dekont veya Belge Fotoğrafı Ekle</strong>
                  <small>Görsel veya PDF seçmek için dokunun (ISO 27001 şifreli)</small>
                </div>
              </div>

              <!-- Dosya Seçildiğinde Gösterilen Kart -->
              <div v-else class="selected-file-tile">
                <div class="selected-file-info">
                  <div class="selected-file-icon">
                    <Paperclip :size="16" />
                  </div>
                  <div class="selected-file-meta">
                    <strong class="selected-file-name">{{ quickActionForm.file.name }}</strong>
                    <small class="selected-file-size">{{ formatFileSize(quickActionForm.file.size) }}</small>
                  </div>
                </div>
                <button
                  type="button"
                  class="selected-file-del-btn"
                  title="Belgeyi Kaldır"
                  @click.stop="removeQuickActionFile"
                >
                  <X :size="16" />
                  <span>Kaldır</span>
                </button>
              </div>
            </div>

            <!-- Mevcut Dosya Bilgisi (Düzenlemede) -->
            <div v-if="quickActionForm.existing_file && !quickActionForm.file" class="existing-doc-banner">
              <div class="existing-doc-meta">
                <Paperclip :size="14" />
                <span>Mevcut ekli belge kayıtlı</span>
              </div>
              <a :href="quickActionForm.existing_file" target="_blank" class="existing-doc-link">
                Görüntüle
              </a>
            </div>

            <!-- Hata Mesajı -->
            <p v-if="quickActionError" class="form-error">{{ quickActionError }}</p>

            <!-- Gönder Butonu -->
            <button type="submit" class="submit-action-btn" :disabled="quickActionLoading">
              <Check v-if="!quickActionLoading" :size="18" />
              <span>{{ quickActionLoading ? 'Kaydediliyor…' : (quickActionForm.id ? 'Değişiklikleri Güncelle' : 'İşlemi Kaydet') }}</span>
            </button>
          </form>
        </div>
      </div>
    </Transition>

    <!-- ---------------------------------------------------------------- -->
    <!-- MODAL 2: CARİ NOTU DÜZENLEME -->
    <!-- ---------------------------------------------------------------- -->
    <Transition name="ds-modal">
      <div v-if="isNoteModalOpen" class="ds-modal-layer" @click.self="isNoteModalOpen = false">
        <div class="ds-bottom-sheet" role="dialog" aria-modal="true" aria-label="Cari Notu">
          <div class="sheet-header">
            <div class="sheet-title">
              <div class="sheet-icon-box"><StickyNote :size="18" /></div>
              <div>
                <h3>Cari Notu</h3>
                <small>{{ cari?.name }}</small>
              </div>
            </div>
            <button type="button" class="sheet-close-btn" @click="isNoteModalOpen = false">
              <X :size="18" />
            </button>
          </div>

          <form class="sheet-form" @submit.prevent="saveNote">
            <div class="form-group">
              <label>Özel Notlar</label>
              <div class="input-wrap">
                <textarea
                  v-model="noteText"
                  rows="4"
                  placeholder="Bu cariye özel çalışma şartları, hatırlatmalar veya notlar…"
                ></textarea>
              </div>
            </div>

            <button type="submit" class="submit-action-btn" :disabled="noteLoading">
              <Check v-if="!noteLoading" :size="18" />
              <span>{{ noteLoading ? 'Kaydediliyor…' : 'Notu Kaydet' }}</span>
            </button>
          </form>
        </div>
      </div>
    </Transition>

    <!-- ---------------------------------------------------------------- -->
    <!-- MODAL 3: CARİ DÜZENLEME -->
    <!-- ---------------------------------------------------------------- -->
    <Transition name="ds-modal">
      <div v-if="isCariEditModalOpen" class="ds-modal-layer" @click.self="isCariEditModalOpen = false">
        <div class="ds-bottom-sheet" role="dialog" aria-modal="true" aria-label="Cari Düzenle">
          <div class="sheet-header">
            <div class="sheet-title">
              <div class="sheet-icon-box"><Pencil :size="18" /></div>
              <div>
                <h3>Cari Düzenle</h3>
                <small>Bilgileri güncelleyin</small>
              </div>
            </div>
            <button type="button" class="sheet-close-btn" @click="isCariEditModalOpen = false">
              <X :size="18" />
            </button>
          </div>

          <form class="sheet-form" @submit.prevent="submitCariEdit">
            <div class="form-group">
              <label>Cari / Kişi Adı*</label>
              <div class="input-wrap">
                <input v-model="cariEditForm.CariAdi" type="text" required />
              </div>
            </div>

            <div class="form-group">
              <label>Firma / Şirket Ünvanı</label>
              <div class="input-wrap">
                <Building2 :size="16" class="input-icon" />
                <input v-model="cariEditForm.firma" type="text" />
              </div>
            </div>

            <div class="form-grid-two">
              <div class="form-group">
                <label>Telefon</label>
                <div class="input-wrap">
                  <Phone :size="16" class="input-icon" />
                  <input v-model="cariEditForm.Telefon" type="tel" />
                </div>
              </div>

              <div class="form-group">
                <label>E-Posta</label>
                <div class="input-wrap">
                  <input v-model="cariEditForm.Email" type="email" />
                </div>
              </div>
            </div>

            <div class="form-grid-two">
              <div class="form-group">
                <label>İl</label>
                <div class="input-wrap">
                  <MapPin :size="16" class="input-icon" />
                  <input v-model="cariEditForm.il" type="text" />
                </div>
              </div>

              <div class="form-group">
                <label>İlçe</label>
                <div class="input-wrap">
                  <input v-model="cariEditForm.ilce" type="text" />
                </div>
              </div>
            </div>

            <div class="form-group">
              <label>Açık Adres</label>
              <div class="input-wrap">
                <textarea v-model="cariEditForm.Adres" rows="2"></textarea>
              </div>
            </div>

            <p v-if="cariEditError" class="form-error">{{ cariEditError }}</p>

            <button type="submit" class="submit-action-btn" :disabled="cariEditLoading">
              <Check v-if="!cariEditLoading" :size="18" />
              <span>{{ cariEditLoading ? 'Güncelleniyor…' : 'Güncellemeyi Kaydet' }}</span>
            </button>
          </form>
        </div>
      </div>
    </Transition>

    <!-- ---------------------------------------------------------------- -->
    <!-- MODAL 4: HAREKET DETAY & SİLME -->
    <!-- ---------------------------------------------------------------- -->
    <Transition name="ds-modal">
      <div v-if="isMovementDetailOpen" class="ds-modal-layer" @click.self="isMovementDetailOpen = false">
        <div class="ds-bottom-sheet" role="dialog" aria-modal="true" aria-label="Hareket Detayı">
          <div class="sheet-header">
            <div class="sheet-title">
              <div class="sheet-icon-box"><Receipt :size="18" /></div>
              <div>
                <h3>İşlem Detayı</h3>
                <small v-if="selectedMovement">{{ selectedMovement.date_formatted }}</small>
              </div>
            </div>
            <button type="button" class="sheet-close-btn" @click="isMovementDetailOpen = false">
              <X :size="18" />
            </button>
          </div>

          <div v-if="selectedMovement" class="movement-detail-body">
            <div class="movement-amount-hero">
              <span class="hero-type-label">{{ selectedMovement.type === 'aldim' ? 'Giriş (Aldım)' : 'Çıkış (Verdim)' }}</span>
              <strong
                class="hero-amount-text"
                :class="selectedMovement.type === 'aldim' ? 'text-aldim' : 'text-verdim'"
              >
                {{ selectedMovement.type === 'aldim' ? '-' : '+' }}{{ formatMoney(selectedMovement.amount) }}
              </strong>
            </div>

            <div class="detail-data-list">
              <div v-if="selectedMovement.belge_no" class="detail-data-row">
                <span>Belge No:</span>
                <strong>{{ selectedMovement.belge_no }}</strong>
              </div>
              <div class="detail-data-row">
                <span>İşlem Tarihi:</span>
                <strong>{{ selectedMovement.date_formatted }}</strong>
              </div>
              <div v-if="selectedMovement.ekleyen_adi" class="detail-data-row">
                <span>Ekleyen:</span>
                <strong>{{ selectedMovement.ekleyen_adi }}</strong>
              </div>
              <div class="detail-data-row">
                <span>Açıklama:</span>
                <p>{{ selectedMovement.aciklama || '—' }}</p>
              </div>
              <div v-if="selectedMovement.has_file" class="detail-data-row">
                <span>Belge / Ek:</span>
                <a :href="selectedMovement.file_url" target="_blank" class="detail-file-btn">
                  <Paperclip :size="14" />
                  <span>Belgeyi Görüntüle</span>
                </a>
              </div>
            </div>

            <div class="detail-action-buttons">
              <button
                type="button"
                class="primary-edit-btn"
                @click="openEditMovement(selectedMovement)"
              >
                <Pencil :size="16" />
                <span>Hareketi Düzenle</span>
              </button>
              <div class="detail-sub-actions">
                <button
                  type="button"
                  class="danger-delete-btn"
                  :disabled="movementDeleteLoading"
                  @click="deleteSelectedMovement"
                >
                  <Trash2 :size="16" />
                  <span>{{ movementDeleteLoading ? 'Siliniyor…' : 'Bu Hareketi Sil' }}</span>
                </button>
                <button
                  type="button"
                  class="neutral-close-btn"
                  @click="isMovementDetailOpen = false"
                >
                  Kapat
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </section>
</template>

<style scoped>
.ds-cari-detail-screen {
  font-family: var(--ds-font-main);
  padding-bottom: calc(var(--ds-nav-clearance) + var(--ds-safe-bottom));
}

/* TOPBAR */
.detail-topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 18px;
}
.topbar-btn {
  display: grid;
  width: 40px;
  height: 40px;
  place-items: center;
  border: 1px solid var(--dsx-bd-ffffff-a090);
  border-radius: 50%;
  color: var(--ds-ink);
  background: var(--dsx-bg-ffffff-a085);
  box-shadow: var(--ds-shadow-pill);
  backdrop-filter: blur(16px);
  cursor: pointer;
  transition: transform 0.15s ease, background 0.15s ease;
  flex-shrink: 0;
}
.topbar-btn:active {
  transform: scale(0.93);
}
.topbar-btn--primary {
  background: var(--ds-primary);
  color: #ffffff;
  border-color: var(--ds-primary);
  box-shadow: 0 4px 14px rgba(24, 28, 36, 0.25);
}
.topbar-btn--danger {
  color: var(--ds-danger-ink);
  background: var(--ds-danger-soft);
  border-color: transparent;
}
.topbar-title {
  min-width: 0;
  flex: 1;
}
.topbar-title span {
  display: block;
  color: var(--ds-muted);
  font: 700 9px 'Space Grotesk', sans-serif;
  letter-spacing: 0.1em;
  text-transform: uppercase;
}
.topbar-title h1 {
  margin: 1px 0 0;
  font-size: 17px;
  font-weight: 700;
  letter-spacing: -0.02em;
  color: var(--ds-ink);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.topbar-actions {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-shrink: 0;
}

/* HERO PROFİL KARTI */
.detail-hero-card {
  padding: 18px;
  border: 1px solid var(--ds-border);
  border-radius: var(--ds-radius-card);
  background: var(--dsx-bg-ffffff);
  box-shadow: var(--ds-shadow-bento);
  margin-bottom: 20px;
}
.hero-profile-row {
  display: flex;
  align-items: center;
  gap: 14px;
}
.hero-avatar {
  display: grid;
  width: 52px;
  height: 52px;
  place-items: center;
  border-radius: 16px;
  color: #ffffff;
  background: var(--ds-primary);
  font: 700 18px 'Space Grotesk', sans-serif;
  box-shadow: 0 6px 16px -4px rgba(17, 22, 27, 0.3);
  flex-shrink: 0;
}
.hero-info {
  min-width: 0;
  flex: 1;
}
.hero-info h2 {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
  color: var(--ds-ink);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  letter-spacing: -0.02em;
}
.hero-company {
  margin: 3px 0 0;
  font-size: 12px;
  font-weight: 600;
  color: var(--ds-muted);
  display: flex;
  align-items: center;
  gap: 4px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.hero-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: 6px;
}
.hero-meta span {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  color: var(--ds-muted);
  font-weight: 500;
}

/* MINI ÖZET (SOFT BENTO PILLS) */
.hero-summary-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
  margin-top: 16px;
  padding-top: 14px;
  border-top: 1px solid var(--ds-line);
}
.mini-summary-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  padding: 10px 6px;
  border-radius: 16px;
  background: var(--dsx-bg-f8fafc);
  border: 1px solid var(--ds-line);
  transition: transform 0.15s ease;
}
.mini-summary-filter {
  width: 100%;
  color: inherit;
  font: inherit;
  cursor: pointer;
  appearance: none;
}
.mini-summary-filter:active {
  transform: scale(0.97);
}
.mini-summary-filter.is-active {
  border-color: currentColor;
  box-shadow: 0 0 0 2px currentColor, var(--ds-shadow-pill);
}
.summary-item--aldim.mini-summary-filter { color: var(--ds-danger); }
.summary-item--verdim.mini-summary-filter { color: var(--ds-emerald); }
.mini-summary-filter:focus-visible {
  outline: 3px solid rgba(10, 132, 255, 0.28);
  outline-offset: 2px;
}
.summary-label {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  margin-bottom: 3px;
}
.summary-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  flex-shrink: 0;
}
.summary-dot--debt,
.summary-dot--borclu,
.summary-dot--aldim { background: var(--ds-danger); }
.summary-dot--credit,
.summary-dot--alacakli,
.summary-dot--verdim { background: var(--ds-emerald); }
.summary-dot--neutral { background: var(--ds-muted); }

.text-aldim,
.text-borclu { color: var(--ds-danger-ink) !important; }
.text-verdim,
.text-alacakli { color: var(--ds-emerald-ink) !important; }

.mini-summary-item small {
  display: block;
  font: 700 8.5px 'Space Grotesk', sans-serif;
  color: var(--ds-muted);
  letter-spacing: 0.05em;
}
.mini-summary-item strong {
  font-size: 13px;
  font-weight: 800;
  font-family: var(--ds-font-main);
  letter-spacing: -0.02em;
  white-space: nowrap;
}

/* AKSİYON BUTONLARI */
.hero-actions-row {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 8px;
  margin-top: 14px;
}
.hero-action-btn {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  padding: 10px 4px;
  border-radius: 16px;
  border: 1px solid var(--ds-line);
  background: var(--dsx-bg-ffffff);
  box-shadow: var(--ds-shadow-tile);
  color: var(--ds-ink);
  text-decoration: none;
  cursor: pointer;
  transition: transform 0.15s ease, background 0.15s ease;
}
.hero-action-btn:active {
  transform: scale(0.94);
}
.action-icon-circle {
  display: grid;
  width: 36px;
  height: 36px;
  place-items: center;
  border-radius: 50%;
  transition: transform 0.15s ease;
}
.action-btn--note .action-icon-circle {
  background: var(--ds-amber-soft);
  color: var(--ds-amber-ink);
}
.action-btn--phone .action-icon-circle {
  background: var(--ds-emerald-soft);
  color: var(--ds-emerald-ink);
}
.action-btn--disabled {
  opacity: 0.45;
  pointer-events: none;
}
.action-btn--disabled .action-icon-circle {
  background: var(--ds-surface-subtle);
  color: var(--ds-muted);
}
.action-btn--share .action-icon-circle {
  background: var(--ds-blue-soft);
  color: var(--ds-blue-ink);
}
.action-btn--pdf .action-icon-circle {
  background: var(--ds-indigo-soft);
  color: var(--ds-indigo-ink);
}
.hero-action-btn span {
  font: 700 10px var(--ds-font-main);
  color: var(--ds-ink);
}

/* CARİ NOTU BANNER */
.hero-note-banner {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  margin-top: 12px;
  padding: 10px 12px;
  border-radius: 14px;
  background: var(--ds-amber-soft);
  border: 1px solid rgba(245, 158, 11, 0.25);
  color: var(--ds-amber-ink);
  cursor: pointer;
}
.hero-note-banner p {
  margin: 0;
  font-size: 11px;
  line-height: 1.4;
  flex: 1;
}
.note-edit-icon {
  color: var(--ds-amber-ink);
  flex-shrink: 0;
  margin-top: 2px;
}

/* HAREKETLER LİSTE BAŞLIĞI */
.movements-header-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin: 0 4px 12px;
}
.movements-header-title {
  display: flex;
  align-items: center;
  gap: 8px;
}
.movements-header-title h3 {
  margin: 0;
  font-size: 15px;
  font-weight: 700;
  color: var(--ds-ink);
}
.movements-badge {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 999px;
  background: var(--dsx-bg-ffffff);
  border: 1px solid var(--ds-line);
  color: var(--ds-muted);
  font: 600 10px 'Space Grotesk', sans-serif;
}
.add-movement-trigger-btn {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  height: 34px;
  padding: 0 14px;
  border: 0;
  border-radius: 999px;
  background: var(--ds-primary);
  color: #ffffff;
  font: 700 11px 'Space Grotesk', sans-serif;
  cursor: pointer;
  box-shadow: 0 4px 12px rgba(24, 28, 36, 0.16);
  transition: transform 0.15s ease;
}
.add-movement-trigger-btn:active {
  transform: scale(0.95);
}

/* HAREKET KARTLARI */
.movements-container {
  display: grid;
  gap: 9px;
}
.movement-row-card {
  display: grid;
  grid-template-columns: 40px 1fr auto auto;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  border: 1px solid var(--ds-border);
  border-radius: var(--ds-radius-tile);
  background: var(--dsx-bg-ffffff);
  box-shadow: var(--ds-shadow-tile);
  cursor: pointer;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.movement-row-card:active {
  transform: scale(0.985);
}
.movement-type-box {
  display: grid;
  width: 40px;
  height: 40px;
  place-items: center;
  border-radius: 14px;
  flex-shrink: 0;
}
.type-debt,
.type-aldim {
  background: var(--ds-danger-soft);
  color: var(--ds-danger-ink);
}
.type-credit,
.type-verdim {
  background: var(--ds-emerald-soft);
  color: var(--ds-emerald-ink);
}
.movement-info {
  min-width: 0;
}
.movement-date-row {
  display: flex;
  align-items: center;
  gap: 6px;
}
.m-date {
  font: 700 9.5px 'Space Grotesk', sans-serif;
  color: var(--ds-muted);
}
.m-doc-badge {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  padding: 1px 6px;
  border-radius: 6px;
  background: var(--ds-surface-subtle);
  border: 1px solid var(--ds-line);
  font-size: 8.5px;
  color: var(--ds-slate);
}
.m-file-badge {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  padding: 1px 6px;
  border-radius: 6px;
  background: var(--ds-blue-soft);
  font-size: 8.5px;
  color: var(--ds-blue-ink);
  font-weight: 700;
}
.movement-desc {
  margin: 3px 0 0;
  font-size: 12px;
  font-weight: 600;
  color: var(--ds-ink);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.movement-author {
  display: block;
  font-size: 9.5px;
  color: var(--ds-muted);
  margin-top: 1px;
}
.movement-amounts {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 2px;
}
.m-amount {
  font-size: 13.5px;
  font-weight: 800;
  font-family: var(--ds-font-main);
  letter-spacing: -0.02em;
}
.m-yuruyen {
  font-size: 9.5px;
  color: var(--ds-muted);
}
.movement-chevron {
  color: #cbd5e1;
  flex-shrink: 0;
}

.movements-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 40px 20px;
  border: 2px dashed var(--dsx-bd-cbd5e1);
  border-radius: 20px;
  background: var(--dsx-bg-ffffff);
  text-align: center;
  color: var(--ds-muted);
}
.empty-icon-wrap {
  width: 50px;
  height: 50px;
  border-radius: 50%;
  background: var(--ds-surface-subtle);
  display: grid;
  place-items: center;
  color: var(--ds-muted);
}
.movements-empty strong {
  color: var(--ds-ink);
  font-size: 13px;
}
.movements-empty p {
  margin: 0;
  font-size: 11px;
  max-width: 260px;
}
.empty-add-btn {
  margin-top: 8px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 18px;
  border-radius: 999px;
  background: var(--ds-primary);
  color: #ffffff;
  border: 0;
  font: 700 11px 'Space Grotesk', sans-serif;
  cursor: pointer;
}

/* RENKLER */
.text-debt { color: var(--ds-danger-ink) !important; }
.text-credit { color: var(--ds-emerald-ink) !important; }
.text-neutral { color: var(--ds-muted) !important; }

/* MODAL ORTAK */
.ds-modal-layer {
  position: fixed;
  inset: 0;
  z-index: 100;
  background: rgba(17, 22, 27, 0.55);
  backdrop-filter: blur(6px);
  display: flex;
  align-items: flex-end;
  justify-content: center;
}
.ds-bottom-sheet {
  width: 100%;
  max-width: 540px;
  max-height: 88vh;
  background: var(--dsx-bg-ffffff);
  border-radius: 28px 28px 0 0;
  box-shadow: var(--ds-shadow-float);
  overflow-y: auto;
  padding: 20px 20px calc(24px + var(--ds-safe-bottom));
  animation: slideUp 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}
@keyframes slideUp {
  from { transform: translateY(100%); }
  to { transform: translateY(0); }
}
.sheet-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 18px;
  padding-bottom: 12px;
  border-bottom: 1px solid var(--ds-line);
}
.sheet-title {
  display: flex;
  align-items: center;
  gap: 10px;
}
.sheet-icon-box {
  display: grid;
  width: 38px;
  height: 38px;
  place-items: center;
  border-radius: 12px;
  background: var(--ds-surface-subtle);
  color: var(--ds-primary-ink);
}
.sheet-title h3 {
  margin: 0;
  font-size: 15px;
  font-weight: 700;
  color: var(--ds-ink);
}
.sheet-title small {
  display: block;
  font-size: 11px;
  color: var(--ds-muted);
}
.sheet-close-btn {
  display: grid;
  place-items: center;
  width: 34px;
  height: 34px;
  border-radius: 50%;
  border: 0;
  background: var(--ds-surface-subtle);
  color: var(--ds-muted);
  cursor: pointer;
  transition: transform 0.15s ease;
}
.sheet-close-btn:active {
  transform: scale(0.9);
}

/* FORM ELEMANLARI */
.sheet-form {
  display: grid;
  gap: 14px;
}

/* 1. SEGMENTED CONTROL TOGGLE */
.type-segmented-control {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
}
.seg-btn {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border-radius: 16px;
  border: 1.5px solid var(--ds-line);
  background: var(--dsx-bg-f8fafc);
  cursor: pointer;
  transition: all 0.2s ease;
  text-align: left;
}
.seg-btn:active {
  transform: scale(0.98);
}
.seg-icon-box {
  width: 34px;
  height: 34px;
  border-radius: 10px;
  display: grid;
  place-items: center;
  background: var(--dsx-bg-ffffff);
  color: var(--ds-muted);
  flex-shrink: 0;
  transition: all 0.2s ease;
}
.seg-text strong {
  display: block;
  font-size: 12px;
  font-weight: 700;
  color: var(--ds-ink);
  line-height: 1.2;
}
.seg-text small {
  display: block;
  font-size: 9.5px;
  color: var(--ds-muted);
  margin-top: 2px;
}

/* Aktif Durumlar */
.seg-btn--debt.is-active,
.seg-btn--aldim.is-active {
  background: var(--ds-danger-soft);
  border-color: var(--ds-danger);
  box-shadow: 0 4px 12px rgba(239, 68, 68, 0.15);
}
.seg-btn--debt.is-active .seg-icon-box,
.seg-btn--aldim.is-active .seg-icon-box {
  background: var(--dsx-bg-ffffff);
  color: var(--ds-danger-ink);
}
.seg-btn--debt.is-active .seg-text strong,
.seg-btn--aldim.is-active .seg-text strong {
  color: var(--ds-danger-ink);
}

.seg-btn--credit.is-active,
.seg-btn--verdim.is-active {
  background: var(--ds-emerald-soft);
  border-color: var(--ds-emerald);
  box-shadow: 0 4px 12px rgba(16, 185, 129, 0.15);
}
.seg-btn--credit.is-active .seg-icon-box,
.seg-btn--verdim.is-active .seg-icon-box {
  background: var(--dsx-bg-ffffff);
  color: var(--ds-emerald-ink);
}
.seg-btn--credit.is-active .seg-text strong,
.seg-btn--verdim.is-active .seg-text strong {
  color: var(--ds-emerald-ink);
}

.form-grid-two {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}
.form-group label {
  display: block;
  margin-bottom: 5px;
  font: 700 10px 'Space Grotesk', sans-serif;
  color: var(--ds-muted);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}
.input-wrap {
  position: relative;
  display: flex;
  align-items: center;
  border: 1.5px solid var(--ds-line);
  border-radius: 14px;
  background: var(--dsx-bg-ffffff);
  padding: 0 12px;
  height: 46px;
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}
.input-wrap:focus-within {
  border-color: var(--ds-primary-ink);
  box-shadow: 0 0 0 3px rgba(24, 28, 36, 0.08);
}
.input-icon {
  color: var(--ds-muted);
  margin-right: 8px;
  flex-shrink: 0;
}
.input-wrap input,
.input-wrap textarea {
  width: 100%;
  border: 0;
  outline: none;
  font: 500 13px var(--ds-font-main);
  color: var(--ds-ink);
  background: transparent;
}
.amount-wrap {
  border-color: var(--dsx-bd-cbd5e1);
}
.currency-badge {
  font: 800 16px var(--ds-font-main);
  color: var(--ds-ink);
  margin-right: 6px;
}
.input-amount {
  font-weight: 800 !important;
  font-size: 16px !important;
  font-family: var(--ds-font-main) !important;
}
.textarea-wrap {
  height: auto;
  align-items: flex-start;
  padding: 10px 12px;
}
.textarea-icon {
  margin-top: 2px;
}
.input-wrap textarea {
  resize: vertical;
  line-height: 1.4;
}

/* Hızlı Tutar Çipleri */
.quick-chips-row {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: -6px;
  margin-bottom: 2px;
}
.quick-chip {
  height: 28px;
  padding: 0 10px;
  border-radius: 999px;
  border: 1px solid var(--ds-line);
  background: var(--dsx-bg-ffffff);
  color: var(--ds-muted);
  font: 700 11px 'Space Grotesk', sans-serif;
  cursor: pointer;
  transition: all 0.15s ease;
}
.quick-chip:active {
  transform: scale(0.95);
  background: var(--ds-surface-subtle);
}
.quick-chip--bakiye {
  background: var(--ds-indigo-soft);
  color: var(--ds-indigo-ink);
  border-color: transparent;
}

/* Açıklama & Hızlı Şablonlar */
.label-with-presets {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 5px;
}
.label-with-presets label {
  margin-bottom: 0;
}
.quick-presets {
  display: flex;
  gap: 5px;
}
.preset-pill {
  padding: 2px 8px;
  border-radius: 999px;
  border: 1px solid var(--ds-line);
  background: var(--dsx-bg-ffffff);
  color: var(--ds-muted);
  font-size: 10px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s ease;
}
.preset-pill:active {
  background: var(--ds-surface-subtle);
}

/* 5. DOSYA YÜKLEME (CUSTOM DROPZONE) */
.upload-dropzone {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  border: 2px dashed var(--dsx-bd-cbd5e1);
  border-radius: 16px;
  background: var(--dsx-bg-f8fafc);
  cursor: pointer;
  transition: all 0.2s ease;
}
.upload-dropzone:active {
  transform: scale(0.985);
  background: var(--dsx-bg-f1f5f9);
}
.dropzone-icon {
  width: 38px;
  height: 38px;
  border-radius: 12px;
  background: var(--dsx-bg-e0f2fe);
  color: var(--dsx-fg-0284c7);
  display: grid;
  place-items: center;
  flex-shrink: 0;
}
.dropzone-text strong {
  display: block;
  font-size: 12px;
  font-weight: 700;
  color: var(--dsx-fg-0284c7);
}
.dropzone-text small {
  display: block;
  font-size: 10px;
  color: var(--ds-muted);
  margin-top: 1px;
}
.hidden-file-input {
  display: none;
}

/* Seçilen Dosya Kartı */
.selected-file-tile {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 14px;
  border: 1.5px solid var(--dsx-bd-bbf7d0);
  border-radius: 16px;
  background: var(--dsx-bg-f0fdf4);
}
.selected-file-info {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
}
.selected-file-icon {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  background: var(--dsx-bg-dcfce7);
  color: var(--dsx-fg-16a34a);
  display: grid;
  place-items: center;
  flex-shrink: 0;
}
.selected-file-meta {
  min-width: 0;
}
.selected-file-name {
  display: block;
  font-size: 12px;
  font-weight: 700;
  color: var(--dsx-fg-166534);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.selected-file-size {
  display: block;
  font-size: 10px;
  color: var(--dsx-fg-15803d);
}
.selected-file-del-btn {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 10px;
  border-radius: 999px;
  background: var(--dsx-bg-fee2e2);
  color: var(--dsx-fg-991b1b);
  border: 0;
  font-size: 11px;
  font-weight: 700;
  cursor: pointer;
  flex-shrink: 0;
}

.form-error {
  margin: 0;
  color: var(--ds-danger-ink);
  font-size: 11px;
  font-weight: 600;
}
.submit-action-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  height: 48px;
  border: 0;
  border-radius: 999px;
  background: var(--ds-primary);
  color: #ffffff;
  font: 700 12px 'Space Grotesk', sans-serif;
  cursor: pointer;
  box-shadow: 0 8px 20px rgba(17, 22, 27, 0.18);
  margin-top: 6px;
  transition: transform 0.15s ease;
}
.submit-action-btn:active {
  transform: scale(0.98);
}
.submit-action-btn:disabled {
  opacity: 0.6;
}

/* HAREKET DETAY POPUP */
.movement-detail-body {
  display: grid;
  gap: 16px;
}
.movement-amount-hero {
  text-align: center;
  padding: 18px;
  border-radius: 20px;
  background: var(--dsx-bg-f8fafc);
  border: 1px solid var(--ds-line);
}
.hero-type-label {
  display: block;
  font: 700 9px 'Space Grotesk', sans-serif;
  letter-spacing: 0.1em;
  color: var(--ds-muted);
  text-transform: uppercase;
  margin-bottom: 4px;
}
.hero-amount-text {
  font-size: 26px;
  font-weight: 800;
  letter-spacing: -0.03em;
}
.detail-data-list {
  display: grid;
  gap: 8px;
  padding: 14px;
  border: 1px solid var(--ds-line);
  border-radius: 18px;
  background: var(--dsx-bg-ffffff);
}
.detail-data-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 11.5px;
}
.detail-data-row span {
  color: var(--ds-muted);
}
.detail-data-row strong,
.detail-data-row p {
  color: var(--ds-ink);
  font-weight: 600;
  margin: 0;
  text-align: right;
  max-width: 65%;
}
.detail-file-btn {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  color: var(--ds-blue-ink);
  font-weight: 700;
  text-decoration: none;
}
.detail-action-buttons {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.primary-edit-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  height: 46px;
  border-radius: 999px;
  border: 1px solid var(--ds-primary);
  background: var(--ds-primary);
  color: #ffffff;
  font: 700 13px var(--ds-font-main);
  cursor: pointer;
  transition: all 0.15s ease;
  box-shadow: 0 4px 12px rgba(19, 91, 236, 0.2);
}
.primary-edit-btn:active {
  transform: scale(0.98);
}
.detail-sub-actions {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}
.danger-delete-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  height: 44px;
  border: 1px solid var(--ds-danger-soft);
  border-radius: 999px;
  background: var(--ds-danger-soft);
  color: var(--ds-danger-ink);
  font: 700 12px var(--ds-font-main);
  cursor: pointer;
}
.neutral-close-btn {
  height: 44px;
  border: 1px solid var(--ds-line);
  border-radius: 999px;
  background: var(--dsx-bg-ffffff);
  color: var(--ds-muted);
  font: 700 12px var(--ds-font-main);
  cursor: pointer;
}

/* Mevcut Dosya Bilgisi */
.existing-doc-banner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 14px;
  border-radius: 14px;
  background: var(--dsx-bg-f1f5f9);
  border: 1px dashed var(--dsx-bd-cbd5e1);
}
.existing-doc-meta {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 11.5px;
  font-weight: 600;
  color: var(--dsx-fg-475569);
}
.existing-doc-link {
  font-size: 11px;
  font-weight: 700;
  color: var(--ds-primary-ink);
  text-decoration: underline;
}

/* ANİMASYON */
.ds-modal-enter-active,
.ds-modal-leave-active {
  transition: opacity 0.2s ease;
}
.ds-modal-enter-from,
.ds-modal-leave-to {
  opacity: 0;
}
</style>
