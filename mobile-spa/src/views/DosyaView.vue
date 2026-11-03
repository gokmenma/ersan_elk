<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import {
  ArrowLeft,
  ChevronRight,
  Download,
  EllipsisVertical,
  Eye,
  FileImage,
  FileSpreadsheet,
  FileText,
  Folder,
  FolderOpen,
  FolderPlus,
  LayoutGrid,
  List,
  Pencil,
  Search,
  Share2,
  Trash2,
  Upload,
  X,
} from '@lucide/vue'
import { auth } from '../services/auth.js'
import { dosyaApi } from '../services/dosya.js'

const router = useRouter()
const perms = computed(() => auth.state.permissions)

const loading = ref(false)
const error = ref('')
const yol = ref([])
const klasorler = ref([])
const dosyalar = ref([])
const aranan = ref(false)
const ozet = ref(null)
const arama = ref('')
const gorunum = ref(localStorage.getItem('dosya_gorunum_mobil') || 'liste')
const aktifKlasor = ref(null)
const fileInput = ref(null)
const yukleniyor = ref(false)

const sheet = ref(null)
const nameSheet = ref(null)
const shareSheet = ref(null)
const paylasimlar = ref([])
const bildirim = ref('')
let aramaZamani = null

const boyutYaz = (b) => {
  b = Number(b) || 0
  if (b >= 1048576) return `${(b / 1048576).toFixed(1).replace('.', ',')} MB`
  if (b >= 1024) return `${Math.round(b / 1024)} KB`
  return `${b} B`
}
const tarihYaz = (t) => {
  const d = new Date(String(t || '').replace(' ', 'T'))
  return Number.isNaN(d.getTime()) ? '' : d.toLocaleDateString('tr-TR')
}
const ikon = (u) => (['xls', 'xlsx', 'csv'].includes(u) ? FileSpreadsheet : ['jpg', 'jpeg', 'png'].includes(u) ? FileImage : FileText)
const renk = (u) => ({ pdf: 'danger', doc: 'blue', docx: 'blue', xls: 'green', xlsx: 'green', csv: 'green' }[u] || 'slate')

const bildir = (m) => {
  bildirim.value = m
  setTimeout(() => { bildirim.value = '' }, 2600)
}

async function yukle() {
  loading.value = true
  error.value = ''
  try {
    const r = await dosyaApi.icerik(aktifKlasor.value, arama.value.trim())
    yol.value = r.yol || []
    klasorler.value = r.klasorler || []
    dosyalar.value = r.dosyalar || []
    aranan.value = Boolean(r.aranan)
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

async function ozetYukle() {
  try { ozet.value = await dosyaApi.ozet() } catch { ozet.value = null }
}

const klasorAc = (id) => { aktifKlasor.value = id; arama.value = ''; yukle() }
const yoldanAc = (id) => klasorAc(id)
const geri = () => {
  if (yol.value.length > 1) klasorAc(yol.value[yol.value.length - 2].id)
  else if (yol.value.length === 1) klasorAc(null)
  else router.back()
}

const gorunumSec = (g) => {
  gorunum.value = g
  try { localStorage.setItem('dosya_gorunum_mobil', g) } catch { /* depolama kapalı */ }
}

const aramaDegisti = () => {
  clearTimeout(aramaZamani)
  aramaZamani = setTimeout(yukle, 350)
}

function dosyaAc(d) {
  if (d.onizlenebilir) window.open(dosyaApi.indirUrl(d.id, true), '_blank', 'noopener')
  else window.location.href = dosyaApi.indirUrl(d.id)
}

const menuAc = (tur, nesne) => { sheet.value = { tur, nesne } }

async function calistir(is, basariMesaji) {
  try {
    await is()
    if (basariMesaji) bildir(basariMesaji)
    await Promise.all([yukle(), ozetYukle()])
  } catch (e) {
    bildir(e.message)
  }
}

function adSor(baslik, mevcut, kaydet) {
  sheet.value = null
  nameSheet.value = { baslik, deger: mevcut, kaydet }
}
async function adKaydet() {
  const { kaydet, deger } = nameSheet.value
  const ad = deger.trim()
  if (!ad) return
  nameSheet.value = null
  await calistir(() => kaydet(ad), 'Kaydedildi')
}

const yeniKlasor = () => adSor('Yeni Klasör', '', (ad) => dosyaApi.klasorOlustur(aktifKlasor.value, ad))

function adlandir() {
  const { tur, nesne } = sheet.value
  adSor(tur === 'klasor' ? 'Klasörü Yeniden Adlandır' : 'Dosyayı Yeniden Adlandır', nesne.ad, (ad) =>
    tur === 'klasor' ? dosyaApi.klasorAdlandir(nesne.id, ad) : dosyaApi.dosyaAdlandir(nesne.id, ad))
}

async function sil() {
  const { tur, nesne } = sheet.value
  sheet.value = null
  const mesaj = tur === 'klasor' ? `"${nesne.ad}" klasörü, alt klasörleri ve dosyalarıyla silinsin mi?` : `"${nesne.ad}" silinsin mi?`
  if (!window.confirm(mesaj)) return
  await calistir(() => (tur === 'klasor' ? dosyaApi.klasorSil(nesne.id) : dosyaApi.dosyaSil(nesne.id)), 'Silindi')
}

async function paylasAc() {
  const { nesne } = sheet.value
  sheet.value = null
  shareSheet.value = { dosya: nesne, gun: '7', sifre: '', limit: '', url: '' }
  try { paylasimlar.value = await dosyaApi.paylasimListele(nesne.id) } catch { paylasimlar.value = [] }
}
async function paylasOlustur() {
  const s = shareSheet.value
  try {
    const r = await dosyaApi.paylasimOlustur(s.dosya.id, Number(s.gun), s.sifre, s.limit)
    s.url = r.url
    paylasimlar.value = await dosyaApi.paylasimListele(s.dosya.id)
  } catch (e) {
    bildir(e.message)
  }
}
async function linkGonder() {
  const s = shareSheet.value
  if (navigator.share) {
    try { await navigator.share({ title: s.dosya.ad, url: s.url }); return } catch { /* iptal */ }
  }
  try { await navigator.clipboard.writeText(s.url); bildir('Bağlantı kopyalandı') } catch { bildir(s.url) }
}
async function paylasimIptal(p) {
  await dosyaApi.paylasimIptal(p.id).catch((e) => bildir(e.message))
  paylasimlar.value = await dosyaApi.paylasimListele(shareSheet.value.dosya.id).catch(() => [])
}

async function dosyaSecildi(e) {
  const files = e.target.files
  if (!files || !files.length) return
  yukleniyor.value = true
  try {
    const r = await dosyaApi.yukle(aktifKlasor.value, files)
    bildir(r.hatalar && r.hatalar.length ? `${r.yuklenen} yüklendi. ${r.hatalar[0]}` : `${r.yuklenen} dosya yüklendi`)
    await Promise.all([yukle(), ozetYukle()])
  } catch (err) {
    bildir(err.message)
  } finally {
    yukleniyor.value = false
    e.target.value = ''
  }
}

onMounted(() => { yukle(); ozetYukle() })
</script>

<template>
  <section class="ds-screen dosya-screen">
    <header class="dosya-topbar">
      <button type="button" class="clay-circle-btn" aria-label="Geri dön" @click="geri"><ArrowLeft :size="20" /></button>
      <div class="dosya-title">
        <span class="clay-page-eyebrow">ŞİRKET ARŞİVİ</span>
        <h1>Dosya Yönetimi</h1>
      </div>
      <button v-if="perms.files_folders" type="button" class="clay-circle-btn" aria-label="Yeni klasör" @click="yeniKlasor"><FolderPlus :size="20" /></button>
      <button v-if="perms.files_upload" type="button" class="clay-circle-btn clay-circle-btn--active" aria-label="Dosya yükle" :disabled="yukleniyor" @click="fileInput.click()"><Upload :size="20" /></button>
      <input ref="fileInput" type="file" class="dosya-input" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.jpg,.jpeg,.png" @change="dosyaSecildi">
    </header>

    <div v-if="ozet" class="bento-card bento-card--dark dosya-ozet">
      <div><strong>{{ ozet.toplam_dosya }}</strong><span>Dosya</span></div>
      <div><strong>{{ ozet.toplam_klasor }}</strong><span>Klasör</span></div>
      <div><strong>{{ boyutYaz(ozet.toplam_boyut) }}</strong><span>Boyut</span></div>
    </div>

    <div class="dosya-arama">
      <Search :size="18" />
      <input v-model="arama" type="search" placeholder="Dosya ara..." aria-label="Dosya ara" @input="aramaDegisti">
      <button v-if="arama" type="button" aria-label="Aramayı temizle" @click="arama = ''; yukle()"><X :size="16" /></button>
    </div>

    <div class="dosya-toolbar">
      <nav class="dosya-yol" aria-label="Konum">
        <button type="button" @click="yoldanAc(null)">Ana Dizin</button>
        <template v-for="p in yol" :key="p.id">
          <ChevronRight :size="14" />
          <button type="button" @click="yoldanAc(p.id)">{{ p.ad }}</button>
        </template>
      </nav>
      <div class="clay-tabs-wrap dosya-gorunum">
        <button type="button" class="clay-filter-pill" :class="{ 'is-active': gorunum === 'liste' }" aria-label="Liste" @click="gorunumSec('liste')"><List :size="16" /></button>
        <button type="button" class="clay-filter-pill" :class="{ 'is-active': gorunum === 'izgara' }" aria-label="Izgara" @click="gorunumSec('izgara')"><LayoutGrid :size="16" /></button>
      </div>
    </div>

    <p v-if="error" class="dosya-hata">{{ error }}</p>
    <p v-else-if="loading && !dosyalar.length && !klasorler.length" class="dosya-bos">Yükleniyor...</p>

    <div v-else :class="gorunum === 'izgara' ? 'dosya-izgara' : 'dosya-liste'">
      <div v-for="k in klasorler" :key="k.id" class="bento-card bento-card--white dosya-oge" @click="klasorAc(k.id)">
        <span class="dosya-ikon dosya-ikon--amber"><Folder :size="22" /></span>
        <span class="dosya-ad">{{ k.ad }}<small>{{ k.dosya_sayisi }} dosya</small></span>
        <button v-if="perms.files_edit || perms.files_delete || perms.files_folders" type="button" class="dosya-menu" aria-label="Klasör işlemleri" @click.stop="menuAc('klasor', k)"><EllipsisVertical :size="18" /></button>
      </div>

      <div v-for="d in dosyalar" :key="d.id" class="bento-card bento-card--white dosya-oge" @click="dosyaAc(d)">
        <span class="dosya-ikon" :class="`dosya-ikon--${renk(d.uzanti)}`"><component :is="ikon(d.uzanti)" :size="22" /></span>
        <span class="dosya-ad">{{ d.ad }}<small>{{ boyutYaz(d.boyut) }} · {{ tarihYaz(d.tarih) }}</small></span>
        <button type="button" class="dosya-menu" aria-label="Dosya işlemleri" @click.stop="menuAc('dosya', d)"><EllipsisVertical :size="18" /></button>
      </div>

      <div v-if="!klasorler.length && !dosyalar.length" class="dosya-bos">
        <FolderOpen :size="40" />
        <p>{{ aranan ? 'Aramanızla eşleşen dosya yok.' : 'Bu klasör boş.' }}</p>
      </div>
    </div>

    <p v-if="bildirim" class="dosya-toast" role="status">{{ bildirim }}</p>

    <div v-if="sheet" class="dosya-backdrop" @click.self="sheet = null">
      <div class="dosya-sheet">
        <h3>{{ sheet.nesne.ad }}</h3>
        <button v-if="sheet.tur === 'dosya' && sheet.nesne.onizlenebilir" type="button" @click="dosyaAc(sheet.nesne); sheet = null"><Eye :size="18" /> Önizle</button>
        <a v-if="sheet.tur === 'dosya'" :href="dosyaApi.indirUrl(sheet.nesne.id)" @click="sheet = null"><Download :size="18" /> İndir</a>
        <button v-if="sheet.tur === 'dosya' && perms.files_share" type="button" @click="paylasAc"><Share2 :size="18" /> Paylaş</button>
        <button v-if="perms.files_edit" type="button" @click="adlandir"><Pencil :size="18" /> Yeniden adlandır</button>
        <button v-if="perms.files_delete" type="button" class="is-danger" @click="sil"><Trash2 :size="18" /> Sil</button>
        <button type="button" class="is-cancel" @click="sheet = null">Kapat</button>
      </div>
    </div>

    <div v-if="nameSheet" class="dosya-backdrop" @click.self="nameSheet = null">
      <form class="dosya-sheet" @submit.prevent="adKaydet">
        <h3>{{ nameSheet.baslik }}</h3>
        <input v-model="nameSheet.deger" type="text" maxlength="190" placeholder="Ad" aria-label="Ad" autofocus>
        <button type="submit" class="is-primary">Kaydet</button>
        <button type="button" class="is-cancel" @click="nameSheet = null">Vazgeç</button>
      </form>
    </div>

    <div v-if="shareSheet" class="dosya-backdrop" @click.self="shareSheet = null">
      <div class="dosya-sheet">
        <h3>Paylaş: {{ shareSheet.dosya.ad }}</h3>
        <label>Geçerlilik
          <select v-model="shareSheet.gun">
            <option value="1">1 gün</option><option value="3">3 gün</option><option value="7">7 gün</option>
            <option value="14">14 gün</option><option value="30">30 gün</option><option value="90">90 gün</option>
          </select>
        </label>
        <input v-model="shareSheet.sifre" type="text" placeholder="Şifre (isteğe bağlı)" autocomplete="off" aria-label="Şifre">
        <input v-model="shareSheet.limit" type="number" min="1" placeholder="İndirme limiti (isteğe bağlı)" aria-label="İndirme limiti">
        <button type="button" class="is-primary" @click="paylasOlustur">Bağlantı oluştur</button>
        <template v-if="shareSheet.url">
          <input type="text" :value="shareSheet.url" readonly aria-label="Paylaşım bağlantısı">
          <button type="button" @click="linkGonder"><Share2 :size="18" /> Gönder / Kopyala</button>
        </template>
        <div v-for="p in paylasimlar" :key="p.id" class="dosya-paylasim">
          <span>{{ p.aktif ? 'Aktif' : (p.iptal ? 'İptal' : 'Süresi doldu') }} · {{ tarihYaz(p.bitis) }} · {{ p.indirme }}{{ p.limit ? `/${p.limit}` : '' }} indirme</span>
          <button v-if="p.aktif" type="button" class="is-danger" @click="paylasimIptal(p)">İptal</button>
        </div>
        <button type="button" class="is-cancel" @click="shareSheet = null">Kapat</button>
      </div>
    </div>
  </section>
</template>

<style scoped>
.dosya-screen { padding-bottom: calc(110px + var(--ds-safe-bottom)); }
.dosya-topbar { display: flex; align-items: center; gap: 10px; margin-bottom: 14px; }
.dosya-title { flex: 1; min-width: 0; }
.dosya-title h1 { margin: 2px 0 0; font-size: 22px; font-weight: 700; color: var(--ds-ink); letter-spacing: -0.03em; }
.dosya-input { display: none; }
.dosya-ozet { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-bottom: 14px; text-align: center; }
.dosya-ozet strong { display: block; font-size: 20px; color: var(--ds-ink-light); }
.dosya-ozet span { font-size: 11px; color: var(--ds-muted-light); text-transform: uppercase; letter-spacing: .04em; }
.dosya-arama { display: flex; align-items: center; gap: 8px; min-height: 48px; padding: 0 14px; margin-bottom: 12px; border-radius: var(--ds-radius-pill); background: var(--ds-surface); border: 1px solid var(--ds-border); color: var(--ds-muted); }
.dosya-arama input { flex: 1; min-width: 0; border: 0; outline: 0; background: transparent; font: 500 16px var(--ds-font-main); color: var(--ds-ink); }
.dosya-arama button { min-width: 44px; min-height: 44px; border: 0; background: transparent; color: var(--ds-muted); }
.dosya-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 12px; }
.dosya-yol { display: flex; align-items: center; flex-wrap: wrap; gap: 2px; min-width: 0; color: var(--ds-muted); }
.dosya-yol button { min-height: 44px; padding: 0 6px; border: 0; background: transparent; font: 600 13px var(--ds-font-main); color: var(--ds-ink); }
.dosya-gorunum { margin: 0; flex-shrink: 0; }
.dosya-liste { display: grid; gap: 10px; }
.dosya-izgara { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
.dosya-oge { display: flex; align-items: center; gap: 12px; min-height: 64px; padding: 12px 14px; cursor: pointer; }
.dosya-izgara .dosya-oge { flex-direction: column; align-items: flex-start; position: relative; padding-top: 16px; }
.dosya-izgara .dosya-menu { position: absolute; top: 4px; right: 4px; }
.dosya-ikon { width: 44px; height: 44px; flex-shrink: 0; display: grid; place-items: center; border-radius: 14px; background: var(--ds-surface-subtle); color: var(--ds-muted); }
.dosya-ikon--danger { background: #fee2e2; color: #dc2626; }
.dosya-ikon--blue { background: #dbeafe; color: #2563eb; }
.dosya-ikon--green { background: #dcfce7; color: #16a34a; }
.dosya-ikon--amber { background: #fef3c7; color: #d97706; }
.dosya-ad { flex: 1; min-width: 0; font: 600 14px var(--ds-font-main); color: var(--ds-ink); word-break: break-word; }
.dosya-ad small { display: block; margin-top: 2px; font-weight: 500; font-size: 12px; color: var(--ds-muted); }
.dosya-menu { min-width: 44px; min-height: 44px; border: 0; background: transparent; color: var(--ds-muted); }
.dosya-bos, .dosya-hata { grid-column: 1 / -1; text-align: center; padding: 40px 16px; color: var(--ds-muted); }
.dosya-hata { color: #dc2626; }
.dosya-toast { position: fixed; left: 16px; right: 16px; bottom: calc(96px + var(--ds-safe-bottom)); z-index: 60; margin: 0; padding: 12px 16px; border-radius: var(--ds-radius-pill); text-align: center; font: 600 13px var(--ds-font-main); background: var(--ds-surface-dark); color: var(--ds-ink-light); }
.dosya-backdrop { position: fixed; inset: 0; z-index: 50; display: flex; align-items: flex-end; background: rgba(25, 29, 36, .45); }
.dosya-sheet { width: 100%; max-height: 88dvh; overflow: auto; display: flex; flex-direction: column; gap: 8px; padding: 18px 16px calc(16px + var(--ds-safe-bottom)); border-radius: 28px 28px 0 0; background: var(--ds-surface); }
.dosya-sheet h3 { margin: 0 0 6px; font-size: 16px; color: var(--ds-ink); word-break: break-word; }
.dosya-sheet button, .dosya-sheet a { display: flex; align-items: center; justify-content: center; gap: 8px; min-height: 48px; padding: 0 16px; border: 1px solid var(--ds-border); border-radius: var(--ds-radius-pill); background: var(--ds-surface-subtle); color: var(--ds-ink); font: 600 14px var(--ds-font-main); text-decoration: none; }
.dosya-sheet .is-primary { background: var(--ds-primary); color: #fff; border-color: var(--ds-primary); }
.dosya-sheet .is-danger { color: #dc2626; }
.dosya-sheet .is-cancel { background: transparent; color: var(--ds-muted); }
.dosya-sheet input, .dosya-sheet select { min-height: 48px; padding: 0 14px; border-radius: 16px; border: 1px solid var(--ds-line); background: var(--ds-surface-subtle); font: 500 16px var(--ds-font-main); color: var(--ds-ink); width: 100%; box-sizing: border-box; }
.dosya-sheet label { display: grid; gap: 4px; font-size: 12px; color: var(--ds-muted); }
.dosya-paylasim { display: flex; align-items: center; justify-content: space-between; gap: 8px; font-size: 12px; color: var(--ds-muted); }
.dosya-paylasim button { min-height: 44px; }
</style>
