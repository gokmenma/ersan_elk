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
const quickActionForm = ref({
  type: 'aldim',
  tutar: '',
  islem_tarihi: '',
  belge_no: '',
  aciklama: '',
  file: null,
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
    type: 'aldim',
    tutar: '',
    islem_tarihi: getTodayDateTimeInput(),
    belge_no: '',
    aciklama: '',
    file: null,
  }
  quickActionError.value = ''
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
        <ArrowLeft :size="22" />
      </button>
      <div class="topbar-title">
        <span>HESAP HAREKETLERİ</span>
        <h1>{{ cari?.name || 'Cari Detayı' }}</h1>
      </div>
      <div class="topbar-actions">
        <button v-if="cari" type="button" class="topbar-btn" aria-label="Düzenle" @click="openEditCari">
          <Pencil :size="18" />
        </button>
        <button v-if="cari" type="button" class="topbar-btn topbar-btn--danger" aria-label="Sil" @click="deleteCari">
          <Trash2 :size="18" />
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
            <p v-if="cari.company" class="hero-company">{{ cari.company }}</p>
            <div class="hero-meta">
              <span v-if="cari.phone"><Phone :size="12" /> {{ cari.phone }}</span>
              <span v-if="cari.city || cari.district"><MapPin :size="12" /> {{ [cari.city, cari.district].filter(Boolean).join(' / ') }}</span>
            </div>
          </div>
        </div>

        <!-- Mini Özet Kartları -->
        <div class="hero-summary-grid">
          <div class="mini-summary-item">
            <small>TOP. ALDIM</small>
            <strong class="text-debt">{{ formatMoney(cari.toplam_borc) }}</strong>
          </div>
          <div class="mini-summary-item">
            <small>TOP. VERDİM</small>
            <strong class="text-credit">{{ formatMoney(cari.toplam_alacak) }}</strong>
          </div>
          <div class="mini-summary-item">
            <small>{{ cari.bakiye < 0 ? 'BORCUM' : cari.bakiye > 0 ? 'ALACAĞIM' : 'BAKİYE' }}</small>
            <strong :class="cari.bakiye < 0 ? 'text-debt' : cari.bakiye > 0 ? 'text-credit' : 'text-neutral'">
              {{ cari.bakiye < 0 ? '-' : cari.bakiye > 0 ? '+' : '' }}{{ formatMoney(cari.bakiye) }}
            </strong>
          </div>
        </div>

        <!-- Hızlı Aksiyon Butonları (Not, Ara, Paylaş, Ekstre) -->
        <div class="hero-actions-row">
          <button type="button" class="hero-action-btn" @click="openNoteModal">
            <div class="action-icon-circle"><StickyNote :size="18" /></div>
            <span>Cari Notu</span>
          </button>
          <a v-if="cari.phone" :href="phoneHref(cari.phone)" class="hero-action-btn">
            <div class="action-icon-circle"><Phone :size="18" /></div>
            <span>Ara</span>
          </a>
          <button type="button" class="hero-action-btn" @click="shareCari">
            <div class="action-icon-circle"><Share2 :size="18" /></div>
            <span>Paylaş</span>
          </button>
          <a :href="cari.ekstre_pdf_url" target="_blank" class="hero-action-btn">
            <div class="action-icon-circle"><FileDown :size="18" /></div>
            <span>Ekstre PDF</span>
          </a>
        </div>

        <!-- Cari Notu Vurgusu (varsa) -->
        <div v-if="cari.notes" class="hero-note-banner" @click="openNoteModal">
          <StickyNote :size="15" />
          <p>{{ cari.notes }}</p>
          <Pencil :size="13" class="note-edit-icon" />
        </div>
      </section>

      <!-- Hareketler Başlığı & Sayacı -->
      <section class="movements-header-row">
        <div>
          <h3>Hesap Hareketleri</h3>
          <small>{{ hareketler.length }} işlem kayıtlı</small>
        </div>
        <button type="button" class="add-movement-trigger-btn" @click="openQuickAction">
          <Plus :size="16" />
          <span>İşlem Ekle</span>
        </button>
      </section>

      <!-- Hareket Listesi -->
      <section class="movements-list-section">
        <div v-if="!hareketler.length" class="movements-empty">
          <Receipt :size="32" />
          <strong>Henüz Hareket Kaydı Yok</strong>
          <p>Bu cariye ait ilk tahsilat veya ödeme işlemini ekleyin.</p>
          <button type="button" @click="openQuickAction">+ İşlem Ekle</button>
        </div>

        <div v-else class="movements-container">
          <article
            v-for="h in hareketler"
            :key="h.id"
            class="movement-row-card"
            @click="openMovementDetail(h)"
          >
            <!-- Tip İkonu -->
            <div
              class="movement-type-box"
              :class="h.type === 'aldim' ? 'type-debt' : 'type-credit'"
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
                <Paperclip v-if="h.has_file" :size="12" class="m-clip" />
              </div>
              <p class="movement-desc">{{ h.aciklama || (h.type === 'aldim' ? 'Tahsilat' : 'Ödeme') }}</p>
              <small v-if="h.ekleyen_adi" class="movement-author">Ekleyen: {{ h.ekleyen_adi }}</small>
            </div>

            <!-- Tutar & Yürüyen Bakiye -->
            <div class="movement-amounts">
              <span
                class="m-amount"
                :class="h.type === 'aldim' ? 'text-debt' : 'text-credit'"
              >
                {{ h.type === 'aldim' ? '-' : '+' }}{{ formatMoney(h.amount) }}
              </span>
              <span class="m-yuruyen">
                Bakiye: {{ h.yuruyen_bakiye < 0 ? '-' : h.yuruyen_bakiye > 0 ? '+' : '' }}{{ formatMoney(h.yuruyen_bakiye) }}
              </span>
            </div>
          </article>
        </div>
      </section>
    </template>

    <!-- FAB: İŞLEM EKLE -->
    <aside class="detail-floating-fab">
      <button
        type="button"
        class="fab-btn"
        aria-label="Yeni İşlem Ekle"
        title="Yeni İşlem Ekle"
        @click="openQuickAction"
      >
        <Plus :size="24" />
        <span>İşlem Ekle</span>
      </button>
    </aside>

    <!-- ---------------------------------------------------------------- -->
    <!-- MODAL 1: HIZLI İŞLEM EKLE -->
    <!-- ---------------------------------------------------------------- -->
    <Transition name="ds-modal">
      <div v-if="isQuickActionOpen" class="ds-modal-layer" @click.self="isQuickActionOpen = false">
        <div class="ds-bottom-sheet" role="dialog" aria-modal="true" aria-label="İşlem Ekle">
          <div class="sheet-header">
            <div class="sheet-title">
              <div class="sheet-icon-box"><CreditCard :size="18" /></div>
              <div>
                <h3>Yeni İşlem Ekle</h3>
                <small>{{ cari?.name }}</small>
              </div>
            </div>
            <button type="button" class="sheet-close-btn" @click="isQuickActionOpen = false">
              <X :size="18" />
            </button>
          </div>

          <form class="sheet-form" @submit.prevent="submitQuickAction">
            <!-- Tip Toggle -->
            <div class="type-toggle-group">
              <label class="toggle-option toggle-option--debt" :class="{ selected: quickActionForm.type === 'aldim' }">
                <input v-model="quickActionForm.type" type="radio" value="aldim" />
                <ArrowDownLeft :size="17" />
                <span>Aldım (Borç)</span>
              </label>
              <label class="toggle-option toggle-option--credit" :class="{ selected: quickActionForm.type === 'verdim' }">
                <input v-model="quickActionForm.type" type="radio" value="verdim" />
                <ArrowUpRight :size="17" />
                <span>Verdim (Alacak)</span>
              </label>
            </div>

            <!-- Tutar & Tarih -->
            <div class="form-grid-two">
              <div class="form-group">
                <label>İşlem Tarihi*</label>
                <div class="input-wrap">
                  <Calendar :size="16" class="input-icon" />
                  <input v-model="quickActionForm.islem_tarihi" type="datetime-local" required />
                </div>
              </div>

              <div class="form-group">
                <label>Tutar (₺)*</label>
                <div class="input-wrap">
                  <span class="input-prefix">₺</span>
                  <input
                    v-model="quickActionForm.tutar"
                    type="number"
                    step="0.01"
                    min="0.01"
                    placeholder="0.00"
                    required
                    class="input-amount"
                  />
                </div>
              </div>
            </div>

            <!-- Belge No -->
            <div class="form-group">
              <label>Belge No</label>
              <div class="input-wrap">
                <Receipt :size="16" class="input-icon" />
                <input v-model="quickActionForm.belge_no" type="text" placeholder="Fatura / Makbuz No" />
              </div>
            </div>

            <!-- Açıklama -->
            <div class="form-group">
              <label>Açıklama</label>
              <div class="input-wrap">
                <FileText :size="16" class="input-icon" />
                <textarea v-model="quickActionForm.aciklama" rows="2" placeholder="İşlem detayı…"></textarea>
              </div>
            </div>

            <!-- Belge Yükle -->
            <div class="form-group">
              <label>Belge / Dekont Yükle</label>
              <div class="input-wrap file-wrap">
                <Paperclip :size="16" class="input-icon" />
                <input type="file" accept="image/*,application/pdf" @change="handleQuickActionFileChange" />
              </div>
            </div>

            <p v-if="quickActionError" class="form-error">{{ quickActionError }}</p>

            <button type="submit" class="submit-action-btn" :disabled="quickActionLoading">
              <Check v-if="!quickActionLoading" :size="18" />
              <span>{{ quickActionLoading ? 'Kaydediliyor…' : 'İşlemi Kaydet' }}</span>
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
              <span class="hero-type-label">{{ selectedMovement.type === 'aldim' ? 'Borç (Aldım)' : 'Alacak (Verdim)' }}</span>
              <strong
                class="hero-amount-text"
                :class="selectedMovement.type === 'aldim' ? 'text-debt' : 'text-credit'"
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
    </Transition>
  </section>
</template>

<style scoped>
.ds-cari-detail-screen {
  font-family: var(--ds-font-main);
  padding-bottom: calc(140px + var(--ds-safe-bottom));
}

/* TOPBAR */
.detail-topbar {
  display: grid;
  grid-template-columns: 42px 1fr auto;
  align-items: center;
  gap: 12px;
  margin-bottom: 20px;
}
.topbar-btn {
  display: grid;
  width: 42px;
  height: 42px;
  place-items: center;
  border: 1px solid rgba(255, 255, 255, 0.9);
  border-radius: 50%;
  color: var(--ds-ink);
  background: rgba(255, 255, 255, 0.8);
  box-shadow: var(--ds-shadow-card);
  backdrop-filter: blur(16px);
  cursor: pointer;
  transition: transform 0.15s ease;
}
.topbar-btn:active {
  transform: scale(0.93);
}
.topbar-btn--danger {
  color: var(--ds-danger-ink);
  background: var(--ds-danger-soft);
  border-color: var(--ds-danger-soft);
}
.topbar-title span {
  display: block;
  color: var(--ds-muted);
  font: 700 9px 'Space Grotesk', sans-serif;
  letter-spacing: 0.1em;
}
.topbar-title h1 {
  margin: 1px 0 0;
  font-size: 18px;
  font-weight: 700;
  letter-spacing: -0.03em;
  color: var(--ds-ink);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.topbar-actions {
  display: flex;
  align-items: center;
  gap: 6px;
}

/* HERO PROFİL KARTI */
.detail-hero-card {
  padding: 18px;
  border: 1px solid rgba(255, 255, 255, 0.9);
  border-radius: 24px;
  background: rgba(255, 255, 255, 0.78);
  box-shadow: var(--ds-shadow-card);
  backdrop-filter: blur(16px);
  margin-bottom: 20px;
}
.hero-profile-row {
  display: flex;
  align-items: center;
  gap: 14px;
}
.hero-avatar {
  display: grid;
  width: 54px;
  height: 54px;
  place-items: center;
  border-radius: 18px;
  color: var(--ds-surface);
  background: var(--ds-primary);
  font: 700 18px 'Space Grotesk', sans-serif;
  box-shadow: 0 8px 20px rgba(17, 22, 27, 0.18);
  flex-shrink: 0;
}
.hero-info {
  min-width: 0;
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
  margin: 2px 0 0;
  font-size: 11px;
  color: var(--ds-muted);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.hero-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: 5px;
}
.hero-meta span {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 10px;
  color: var(--ds-slate);
}

/* MINI ÖZET */
.hero-summary-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
  margin-top: 16px;
  padding-top: 14px;
  border-top: 1px solid var(--ds-line);
}
.mini-summary-item {
  text-align: center;
  padding: 8px 4px;
  border-radius: 14px;
  background: var(--ds-soft);
}
.mini-summary-item small {
  display: block;
  font: 700 8px 'Space Grotesk', sans-serif;
  color: var(--ds-muted);
  letter-spacing: 0.05em;
  margin-bottom: 2px;
}
.mini-summary-item strong {
  font-size: 12px;
  font-weight: 700;
  white-space: nowrap;
}

/* AKSİYON BUTONLARI (NOT, ARA, PAYLAŞ, EKSTRE) */
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
  border: 1px solid rgba(255, 255, 255, 0.9);
  background: rgba(255, 255, 255, 0.85);
  box-shadow: 0 4px 12px rgba(17, 22, 27, 0.04);
  color: var(--ds-ink);
  text-decoration: none;
  cursor: pointer;
  transition: transform 0.15s ease;
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
  background: var(--ds-soft);
  color: var(--ds-primary);
}
.hero-action-btn span {
  font: 600 9px 'Space Grotesk', sans-serif;
  color: var(--ds-slate);
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
  border: 1px solid var(--ds-yellow);
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
.movements-header-row h3 {
  margin: 0;
  font-size: 15px;
  font-weight: 700;
  color: var(--ds-ink);
}
.movements-header-row small {
  color: var(--ds-muted);
  font-size: 10px;
}
.add-movement-trigger-btn {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 6px 14px;
  border: 1px solid var(--ds-primary);
  border-radius: 999px;
  background: var(--ds-primary);
  color: var(--ds-surface);
  font: 700 10px 'Space Grotesk', sans-serif;
  cursor: pointer;
}

/* HAREKET KARTLARI */
.movements-container {
  display: grid;
  gap: 9px;
}
.movement-row-card {
  display: grid;
  grid-template-columns: 38px 1fr auto;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  border: 1px solid rgba(255, 255, 255, 0.9);
  border-radius: 18px;
  background: rgba(255, 255, 255, 0.78);
  box-shadow: var(--ds-shadow-card);
  backdrop-filter: blur(16px);
  cursor: pointer;
  transition: transform 0.15s ease;
}
.movement-row-card:active {
  transform: scale(0.985);
}
.movement-type-box {
  display: grid;
  width: 38px;
  height: 38px;
  place-items: center;
  border-radius: 12px;
}
.type-debt {
  background: var(--ds-danger-soft);
  color: var(--ds-danger-ink);
}
.type-credit {
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
  font: 700 9px 'Space Grotesk', sans-serif;
  color: var(--ds-muted);
}
.m-doc-badge {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  padding: 1px 6px;
  border-radius: 6px;
  background: var(--ds-soft);
  font-size: 8px;
  color: var(--ds-slate);
}
.m-clip {
  color: var(--ds-muted);
}
.movement-desc {
  margin: 3px 0 0;
  font-size: 11px;
  font-weight: 600;
  color: var(--ds-ink);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.movement-author {
  display: block;
  font-size: 9px;
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
  font-size: 13px;
  font-weight: 700;
  letter-spacing: -0.02em;
}
.m-yuruyen {
  font-size: 9px;
  color: var(--ds-muted);
}
.movements-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 40px 20px;
  border: 1px dashed var(--ds-line);
  border-radius: 20px;
  text-align: center;
  color: var(--ds-muted);
}
.movements-empty strong {
  color: var(--ds-ink);
  font-size: 13px;
}
.movements-empty p {
  margin: 0;
  font-size: 11px;
}
.movements-empty button {
  margin-top: 6px;
  padding: 8px 16px;
  border-radius: 999px;
  background: var(--ds-primary);
  color: var(--ds-surface);
  border: 0;
  font: 700 11px 'Space Grotesk', sans-serif;
  cursor: pointer;
}

/* FLOATING FAB */
.detail-floating-fab {
  position: fixed;
  right: 18px;
  bottom: calc(82px + var(--ds-safe-bottom));
  z-index: 40;
}
.fab-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  height: 48px;
  padding: 0 18px;
  border: 1px solid rgba(255, 255, 255, 0.2);
  border-radius: 999px;
  background: var(--ds-primary);
  color: var(--ds-surface);
  box-shadow: var(--ds-shadow-float);
  font: 700 12px 'Space Grotesk', sans-serif;
  cursor: pointer;
}

/* RENKLER */
.text-debt { color: var(--ds-danger-ink) !important; }
.text-credit { color: var(--ds-emerald-ink) !important; }
.text-neutral { color: var(--ds-slate) !important; }

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
  background: var(--ds-surface);
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
  background: var(--ds-soft);
  color: var(--ds-primary);
}
.sheet-title h3 {
  margin: 0;
  font-size: 15px;
  font-weight: 700;
  color: var(--ds-ink);
}
.sheet-title small {
  display: block;
  font-size: 10px;
  color: var(--ds-muted);
}
.sheet-close-btn {
  display: grid;
  place-items: center;
  width: 34px;
  height: 34px;
  border-radius: 50%;
  border: 0;
  background: var(--ds-soft);
  color: var(--ds-slate);
  cursor: pointer;
}

/* FORM FORM ELEMANLARI */
.sheet-form {
  display: grid;
  gap: 14px;
}
.type-toggle-group {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
  padding: 4px;
  border-radius: 16px;
  background: var(--ds-soft);
}
.toggle-option {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  height: 42px;
  border-radius: 12px;
  font: 700 11px 'Space Grotesk', sans-serif;
  color: var(--ds-slate);
  cursor: pointer;
  transition: all 0.15s ease;
}
.toggle-option input {
  display: none;
}
.toggle-option--debt.selected {
  background: var(--ds-surface);
  color: var(--ds-danger-ink);
  box-shadow: 0 4px 12px rgba(220, 38, 38, 0.12);
}
.toggle-option--credit.selected {
  background: var(--ds-surface);
  color: var(--ds-emerald-ink);
  box-shadow: 0 4px 12px rgba(22, 163, 74, 0.12);
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
  color: var(--ds-slate);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}
.input-wrap {
  position: relative;
  display: flex;
  align-items: center;
  border: 1px solid rgba(140, 155, 174, 0.25);
  border-radius: 14px;
  background: var(--ds-surface);
  padding: 0 12px;
  height: 46px;
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
  font: 500 12px var(--ds-font-main);
  color: var(--ds-ink);
  background: transparent;
}
.input-amount {
  font-weight: 700 !important;
  text-align: right;
  font-size: 14px !important;
}
.input-prefix {
  font-weight: 700;
  color: var(--ds-muted);
  margin-right: 6px;
}
.input-wrap textarea {
  padding: 10px 0;
  height: auto;
  resize: vertical;
}
.file-wrap input {
  font-size: 11px;
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
  color: var(--ds-surface);
  font: 700 12px 'Space Grotesk', sans-serif;
  cursor: pointer;
  box-shadow: 0 8px 20px rgba(17, 22, 27, 0.18);
  margin-top: 6px;
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
  background: var(--ds-soft);
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
}
.detail-data-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 11px;
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
  font: 700 11px 'Space Grotesk', sans-serif;
  cursor: pointer;
}
.neutral-close-btn {
  height: 44px;
  border: 1px solid var(--ds-line);
  border-radius: 999px;
  background: var(--ds-surface);
  color: var(--ds-slate);
  font: 700 11px 'Space Grotesk', sans-serif;
  cursor: pointer;
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
