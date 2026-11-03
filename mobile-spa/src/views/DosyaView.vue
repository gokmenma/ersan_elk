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
  UserPlus,
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
const bolum = ref('dosyalarim')
const bolumler = ref(['dosyalarim'])
const baglam = ref({ seviye: 0, alan: 'kisisel', klasor_acabilir: false })
const erisimSheet = ref(null)
const erisimKullanicilari = ref([])
const BOLUM_ADLARI = { dosyalarim: 'Dosyalarım', paylasilan: 'Paylaşılan', ortak: 'Ortak Alan', diger: 'Tüm Kullanıcılar' }
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
    const r = await dosyaApi.icerik(aktifKlasor.value, arama.value.trim(), bolum.value)
    baglam.value = { seviye: r.seviye, alan: r.alan, klasor_acabilir: r.klasor_acabilir }
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
  try { bolumler.value = (await dosyaApi.agac()).bolumler || ['dosyalarim'] } catch { bolumler.value = ['dosyalarim'] }
}

const bolumSec = (b) => { bolum.value = b; aktifKlasor.value = null; arama.value = ''; yukle() }
const yukleyebilir = computed(() => perms.value.files_upload && baglam.value.seviye >= 2)
const klasorAcabilir = computed(() => perms.value.files_folders && baglam.value.klasor_acabilir)
const menuVar = (nesne, tur) => {
  const sv = nesne.seviye || 0
  return tur === 'dosya' || (perms.value.files_edit && sv >= 2) || (perms.value.files_share && sv >= 2) || (perms.value.files_delete && sv >= 3)
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

const yeniKlasor = () => adSor(baglam.value.alan === 'ortak' ? 'Yeni Ortak Klasör' : 'Yeni Klasör', '', (ad) => dosyaApi.klasorOlustur(aktifKlasor.value, ad, baglam.value.alan))

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

async function erisimAc() {
  const { tur, nesne } = sheet.value
  sheet.value = null
  erisimSheet.value = { tur, nesne, kullanici: '', seviye: 'goruntule', liste: [], tam: false }
  try {
    erisimKullanicilari.value = await dosyaApi.erisimKullanicilari()
    await erisimYukle()
  } catch (e) {
    erisimSheet.value = null
    bildir(e.message)
  }
}
async function erisimYukle() {
  const s = erisimSheet.value
  const r = await dosyaApi.erisimListele(s.tur, s.nesne.id)
  s.liste = r.erisimler
  s.tam = r.tam_yetki
  if (!r.tam_yetki) s.seviye = 'goruntule'
}
async function erisimEkle() {
  const s = erisimSheet.value
  if (!s.kullanici) return
  try {
    await dosyaApi.erisimVer(s.tur, s.nesne.id, Number(s.kullanici), s.seviye)
    s.kullanici = ''
    await erisimYukle()
  } catch (e) { bildir(e.message) }
}
async function erisimKaldir(e) {
  try { await dosyaApi.erisimKaldir(e.id); await erisimYukle() } catch (err) { bildir(err.message) }
}

async function dosyaSecildi(e) {
  const files = e.target.files
  if (!files || !files.length) return
  const izinli = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'jpg', 'jpeg', 'png']
  for (const f of Array.from(files)) {
    const uzanti = f.name.split('.').pop().toLowerCase()
    if (!izinli.includes(uzanti)) { bildir(`"${f.name}" desteklenmiyor. İzinli: ${izinli.join(', ')}`); e.target.value = ''; return }
    if (f.size > 25 * 1024 * 1024) { bildir(`"${f.name}" 25 MB sınırını aşıyor.`); e.target.value = ''; return }
  }
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
      <button v-if="klasorAcabilir" type="button" class="clay-circle-btn" aria-label="Yeni klasör" @click="yeniKlasor"><FolderPlus :size="20" /></button>
    </header>

    <div class="bento-card bento-card--dark dosya-hero">
      <div class="dosya-hero-head">
        <span class="dosya-hero-icon"><FolderOpen :size="22" /></span>
        <div>
          <strong>{{ BOLUM_ADLARI[bolum] }}</strong>
          <span>{{ yol.length ? yol.map((p) => p.ad).join(' / ') : 'Ana dizin' }}</span>
        </div>
      </div>
      <div v-if="ozet" class="dosya-hero-grid">
        <div><strong>{{ ozet.toplam_dosya }}</strong><span>Dosya</span></div>
        <div><strong>{{ ozet.toplam_klasor }}</strong><span>Klasör</span></div>
        <div><strong>{{ boyutYaz(ozet.toplam_boyut) }}</strong><span>Boyut</span></div>
      </div>
    </div>

    <div v-if="bolumler.length > 1" class="clay-tabs-wrap dosya-bolumler" role="tablist">
      <button v-for="b in bolumler" :key="b" type="button" class="clay-filter-pill" :class="{ 'is-active': bolum === b }" role="tab" :aria-selected="bolum === b" @click="bolumSec(b)">{{ BOLUM_ADLARI[b] }}</button>
    </div>

    <div class="dosya-sticky">
      <div class="dosya-arama">
        <Search :size="18" />
        <input v-model="arama" type="search" placeholder="Dosya veya klasör ara" aria-label="Dosya ara" @input="aramaDegisti">
        <button v-if="arama" type="button" aria-label="Aramayı temizle" @click="arama = ''; yukle()"><X :size="16" /></button>
      </div>
      <div class="clay-tabs-wrap dosya-gorunum">
        <button type="button" class="clay-filter-pill" :class="{ 'is-active': gorunum === 'liste' }" aria-label="Liste" @click="gorunumSec('liste')"><List :size="16" /></button>
        <button type="button" class="clay-filter-pill" :class="{ 'is-active': gorunum === 'izgara' }" aria-label="Izgara" @click="gorunumSec('izgara')"><LayoutGrid :size="16" /></button>
      </div>
    </div>

    <nav v-if="yol.length && !aranan" class="dosya-yol" aria-label="Konum">
      <button type="button" @click="yoldanAc(null)">{{ BOLUM_ADLARI[bolum] }}</button>
      <template v-for="p in yol" :key="p.id">
        <ChevronRight :size="14" />
        <button type="button" @click="yoldanAc(p.id)">{{ p.ad }}</button>
      </template>
    </nav>

    <p v-if="error" class="dosya-hata">{{ error }}</p>
    <div v-else-if="loading && !dosyalar.length && !klasorler.length" class="dosya-liste" aria-busy="true">
      <div v-for="n in 5" :key="n" class="dosya-iskelet" />
    </div>

    <template v-else>
      <h2 v-if="klasorler.length" class="dosya-baslik">Klasörler <small>{{ klasorler.length }}</small></h2>
      <div v-if="klasorler.length" :class="gorunum === 'izgara' ? 'dosya-izgara' : 'dosya-liste'">
        <div v-for="k in klasorler" :key="k.id" class="bento-card bento-card--white dosya-klasor" :class="{ 'dosya-oge--kare': gorunum === 'izgara' }" role="button" tabindex="0" @click="klasorAc(k.id)" @keydown.enter="klasorAc(k.id)">
          <span class="dosya-ikon dosya-ikon--amber"><Folder :size="22" /></span>
          <span class="dosya-ad">{{ k.ad }}<small>{{ k.dosya_sayisi }} dosya</small></span>
          <button v-if="menuVar(k, 'klasor')" type="button" class="dosya-menu" aria-label="Klasör işlemleri" @click.stop="menuAc('klasor', k)"><EllipsisVertical :size="18" /></button>
        </div>
      </div>

      <h2 v-if="dosyalar.length" class="dosya-baslik">Dosyalar <small>{{ dosyalar.length }}</small></h2>
      <div v-if="dosyalar.length" :class="gorunum === 'izgara' ? 'dosya-izgara' : 'dosya-liste'">
        <div v-for="d in dosyalar" :key="d.id" class="bento-card bento-card--white dosya-oge" :class="{ 'dosya-oge--kare': gorunum === 'izgara' }" role="button" tabindex="0" @click="dosyaAc(d)" @keydown.enter="dosyaAc(d)">
          <span class="dosya-ikon" :class="`dosya-ikon--${renk(d.uzanti)}`"><component :is="ikon(d.uzanti)" :size="22" /></span>
          <span class="dosya-ad">
            {{ d.ad }}
            <small>
              <b class="dosya-tur">{{ d.uzanti.toUpperCase() }}</b>
              {{ boyutYaz(d.boyut) }} · {{ tarihYaz(d.tarih) }}<template v-if="bolum !== 'dosyalarim' && d.yukleyen"> · {{ d.yukleyen }}</template>
            </small>
          </span>
          <button type="button" class="dosya-menu" aria-label="Dosya işlemleri" @click.stop="menuAc('dosya', d)"><EllipsisVertical :size="18" /></button>
        </div>
      </div>

      <div v-if="!klasorler.length && !dosyalar.length" class="dosya-bos">
        <span class="dosya-bos-ikon"><FolderOpen :size="34" /></span>
        <strong>{{ aranan ? 'Sonuç bulunamadı' : 'Burada henüz bir şey yok' }}</strong>
        <p>{{ aranan ? 'Farklı bir arama deneyin.' : (yukleyebilir ? 'Sağ alttaki + düğmesiyle dosya yükleyebilirsiniz.' : 'Bu bölümde görüntülenecek dosya yok.') }}</p>
      </div>
    </template>

    <button v-if="yukleyebilir" type="button" class="dosya-fab" aria-label="Dosya yükle" :disabled="yukleniyor" @click="fileInput.click()">
      <Upload v-if="!yukleniyor" :size="22" /><span v-else class="dosya-fab-yukleniyor" />
      <span>{{ yukleniyor ? 'Yükleniyor' : 'Yükle' }}</span>
    </button>
    <input ref="fileInput" type="file" class="dosya-input" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.jpg,.jpeg,.png" @change="dosyaSecildi">

    <p v-if="bildirim" class="dosya-toast" role="status">{{ bildirim }}</p>

    <div v-if="sheet" class="dosya-backdrop" @click.self="sheet = null">
      <div class="dosya-sheet" role="dialog" aria-modal="true">
        <span class="dosya-tutamac" />
        <div class="dosya-sheet-head">
          <span class="dosya-ikon" :class="sheet.tur === 'klasor' ? 'dosya-ikon--amber' : `dosya-ikon--${renk(sheet.nesne.uzanti)}`">
            <Folder v-if="sheet.tur === 'klasor'" :size="22" /><component :is="ikon(sheet.nesne.uzanti)" v-else :size="22" />
          </span>
          <div><h3>{{ sheet.nesne.ad }}</h3><small>{{ sheet.tur === 'klasor' ? `${sheet.nesne.dosya_sayisi} dosya` : `${boyutYaz(sheet.nesne.boyut)} · ${tarihYaz(sheet.nesne.tarih)}` }}</small></div>
        </div>
        <button v-if="sheet.tur === 'dosya' && sheet.nesne.onizlenebilir" type="button" @click="dosyaAc(sheet.nesne); sheet = null"><Eye :size="18" /> Önizle</button>
        <a v-if="sheet.tur === 'dosya'" :href="dosyaApi.indirUrl(sheet.nesne.id)" @click="sheet = null"><Download :size="18" /> İndir</a>
        <button v-if="sheet.tur === 'dosya' && perms.files_share && sheet.nesne.seviye >= 2" type="button" @click="paylasAc"><Share2 :size="18" /> Bağlantı ile paylaş</button>
        <button v-if="perms.files_share && sheet.nesne.seviye >= 2" type="button" @click="erisimAc"><UserPlus :size="18" /> Kullanıcılarla paylaş</button>
        <button v-if="perms.files_edit && sheet.nesne.seviye >= 2" type="button" @click="adlandir"><Pencil :size="18" /> Yeniden adlandır</button>
        <button v-if="perms.files_delete && sheet.nesne.seviye >= 3" type="button" class="is-danger" @click="sil"><Trash2 :size="18" /> Sil</button>
        <button type="button" class="is-cancel" @click="sheet = null">Kapat</button>
      </div>
    </div>

    <div v-if="nameSheet" class="dosya-backdrop" @click.self="nameSheet = null">
      <form class="dosya-sheet" @submit.prevent="adKaydet">
        <span class="dosya-tutamac" />
        <h3>{{ nameSheet.baslik }}</h3>
        <input v-model="nameSheet.deger" type="text" maxlength="190" placeholder="Ad" aria-label="Ad" autofocus>
        <button type="submit" class="is-primary">Kaydet</button>
        <button type="button" class="is-cancel" @click="nameSheet = null">Vazgeç</button>
      </form>
    </div>

    <div v-if="erisimSheet" class="dosya-backdrop" @click.self="erisimSheet = null">
      <div class="dosya-sheet" role="dialog" aria-modal="true">
        <span class="dosya-tutamac" />
        <h3>Kullanıcılarla paylaş</h3>
        <small class="dosya-alt">{{ erisimSheet.nesne.ad }}</small>
        <label>Kullanıcı
          <select v-model="erisimSheet.kullanici">
            <option value="">Kullanıcı seçin</option>
            <option v-for="u in erisimKullanicilari" :key="u.id" :value="u.id">{{ u.ad }}</option>
          </select>
        </label>
        <label>Yetki
          <select v-model="erisimSheet.seviye">
            <option value="goruntule">Görüntüleyebilir</option>
            <option v-if="erisimSheet.tam" value="duzenle">Düzenleyebilir</option>
          </select>
        </label>
        <button type="button" class="is-primary" @click="erisimEkle">Ekle</button>
        <div v-for="e in erisimSheet.liste" :key="e.id" class="dosya-paylasim">
          <span><b>{{ e.kullanici }}</b><small>{{ e.seviye === 'duzenle' ? 'Düzenleyebilir' : 'Görüntüleyebilir' }}</small></span>
          <button type="button" class="is-danger" @click="erisimKaldir(e)">Kaldır</button>
        </div>
        <p v-if="!erisimSheet.liste.length" class="dosya-alt">Henüz kimseyle paylaşılmadı.</p>
        <button type="button" class="is-cancel" @click="erisimSheet = null">Kapat</button>
      </div>
    </div>

    <div v-if="shareSheet" class="dosya-backdrop" @click.self="shareSheet = null">
      <div class="dosya-sheet" role="dialog" aria-modal="true">
        <span class="dosya-tutamac" />
        <h3>Bağlantı ile paylaş</h3>
        <small class="dosya-alt">{{ shareSheet.dosya.ad }}</small>
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
          <span><b>{{ p.aktif ? 'Aktif' : (p.iptal ? 'İptal' : 'Süresi doldu') }}</b><small>{{ tarihYaz(p.bitis) }} · {{ p.indirme }}{{ p.limit ? `/${p.limit}` : '' }} indirme</small></span>
          <button v-if="p.aktif" type="button" class="is-danger" @click="paylasimIptal(p)">İptal</button>
        </div>
        <button type="button" class="is-cancel" @click="shareSheet = null">Kapat</button>
      </div>
    </div>
  </section>
</template>

<style scoped>
.dosya-screen { padding-bottom: calc(150px + var(--ds-safe-bottom)); }
.dosya-topbar { display: flex; align-items: center; gap: 10px; margin-bottom: 14px; }
.dosya-title { flex: 1; min-width: 0; }
.dosya-title h1 { margin: 2px 0 0; font-size: 24px; font-weight: 700; color: var(--ds-ink); letter-spacing: -0.03em; }
.dosya-input { display: none; }

.dosya-hero { display: grid; gap: 14px; margin-bottom: 14px; padding: 16px; }
.dosya-hero-head { display: flex; align-items: center; gap: 12px; min-width: 0; }
.dosya-hero-head > div { min-width: 0; }
.dosya-hero-head strong { display: block; font-size: 17px; color: var(--ds-ink-light); }
.dosya-hero-head span:last-child, .dosya-hero-head div span { display: block; font-size: 12px; color: var(--ds-muted-light); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.dosya-hero-icon { width: 44px; height: 44px; flex-shrink: 0; display: grid; place-items: center; border-radius: 14px; background: rgba(255, 255, 255, .1); color: var(--ds-ink-light); }
.dosya-hero-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
.dosya-hero-grid > div { padding: 10px 8px; border-radius: 16px; background: rgba(255, 255, 255, .06); border: 1px solid rgba(255, 255, 255, .08); text-align: center; }
.dosya-hero-grid strong { display: block; font-size: 18px; color: var(--ds-ink-light); }
.dosya-hero-grid span { font-size: 11px; color: var(--ds-muted-light); text-transform: uppercase; letter-spacing: .05em; }

.dosya-bolumler { overflow-x: auto; scrollbar-width: none; }
.dosya-bolumler::-webkit-scrollbar { display: none; }
.dosya-bolumler { padding: 4px; gap: 4px; }
.dosya-bolumler .clay-filter-pill { flex: 1 0 auto; justify-content: center; min-height: 40px; box-shadow: none; border-color: transparent; background: transparent; }
.dosya-bolumler .clay-filter-pill.is-active { background: var(--ds-primary); border-color: var(--ds-primary); }

.dosya-sticky { position: sticky; top: 0; z-index: 5; display: flex; align-items: center; gap: 8px; margin: 0 -2px; padding: 6px 2px 10px; background: var(--ds-canvas); }
.dosya-arama { flex: 1; min-width: 0; display: flex; align-items: center; gap: 8px; min-height: 48px; padding: 0 12px 0 16px; border-radius: var(--ds-radius-pill); background: var(--ds-surface); border: 1px solid var(--ds-border); color: var(--ds-muted); }
.dosya-arama:focus-within { border-color: var(--ds-primary); }
.dosya-arama input { flex: 1; width: 0; min-width: 0; border: 0; outline: 0; background: transparent; font: 500 16px var(--ds-font-main); color: var(--ds-ink); }
.dosya-arama button { min-width: 44px; min-height: 44px; border: 0; background: transparent; color: var(--ds-muted); }
.dosya-gorunum { flex: 0 0 auto; width: auto; margin: 0; padding: 3px; gap: 2px; }
.dosya-gorunum .clay-filter-pill { min-width: 42px; min-height: 42px; padding: 0; justify-content: center; box-shadow: none; border-color: transparent; background: transparent; }
.dosya-gorunum .clay-filter-pill.is-active { background: var(--ds-primary); }

.dosya-yol { display: flex; align-items: center; flex-wrap: wrap; gap: 2px; margin-bottom: 6px; color: var(--ds-muted); }
.dosya-yol button { min-height: 44px; padding: 0 6px; border: 0; background: transparent; font: 600 13px var(--ds-font-main); color: var(--ds-ink); }

.dosya-baslik { display: flex; align-items: center; gap: 8px; margin: 16px 4px 8px; font: 700 13px var(--ds-font-main); letter-spacing: .06em; text-transform: uppercase; color: var(--ds-muted); }
.dosya-baslik small { padding: 1px 8px; border-radius: 999px; background: var(--ds-surface); font-size: 11px; }
.dosya-liste { display: grid; gap: 10px; }
.dosya-izgara { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
.dosya-oge, .dosya-klasor { display: flex; align-items: center; gap: 12px; min-height: 68px; padding: 12px 6px 12px 14px; cursor: pointer; }
.dosya-oge--kare { flex-direction: column; align-items: flex-start; position: relative; padding: 16px 14px; min-height: 132px; }
.dosya-oge--kare .dosya-menu { position: absolute; top: 4px; right: 4px; }
.dosya-oge--kare .dosya-ad { flex: 0 0 auto; width: 100%; padding-right: 8px; }
.dosya-klasor { padding-right: 4px; }
.dosya-klasor.dosya-oge--kare { min-height: 118px; }
.dosya-klasor.dosya-oge--kare .dosya-ad { margin-top: 4px; }
.dosya-ikon { width: 46px; height: 46px; flex-shrink: 0; display: grid; place-items: center; border-radius: 15px; background: var(--ds-surface-subtle); color: var(--ds-muted); }
.dosya-ikon--danger { background: #fee2e2; color: #dc2626; }
.dosya-ikon--blue { background: #dbeafe; color: #2563eb; }
.dosya-ikon--green { background: #dcfce7; color: #16a34a; }
.dosya-ikon--amber { background: #fef3c7; color: #d97706; }
.dosya-ad { flex: 1; min-width: 0; font: 600 14px var(--ds-font-main); color: var(--ds-ink); word-break: break-word; }
.dosya-ad small { display: block; margin-top: 3px; font-weight: 500; font-size: 12px; color: var(--ds-muted); }
.dosya-tur { display: inline-block; margin-right: 6px; padding: 0 6px; border-radius: 6px; background: var(--ds-surface-subtle); font-size: 10px; letter-spacing: .04em; color: var(--ds-ink); }
.dosya-menu { min-width: 44px; min-height: 44px; border: 0; background: transparent; color: var(--ds-muted); }

.dosya-iskelet { height: 68px; border-radius: 28px; background: linear-gradient(90deg, #f1f5f9, #fff, #f1f5f9); background-size: 200% 100%; animation: dosya-kay 1.2s linear infinite; }
@keyframes dosya-kay { to { background-position: -200% 0; } }
@media (prefers-reduced-motion: reduce) { .dosya-iskelet { animation: none; } }

.dosya-bos { display: grid; justify-items: center; gap: 6px; padding: 44px 24px; text-align: center; color: var(--ds-muted); }
.dosya-bos strong { color: var(--ds-ink); font-size: 16px; }
.dosya-bos p { margin: 0; font-size: 13px; }
.dosya-bos-ikon { width: 72px; height: 72px; display: grid; place-items: center; border-radius: 24px; background: var(--ds-surface); box-shadow: var(--ds-shadow-tile); color: var(--ds-primary); }
.dosya-hata { text-align: center; padding: 40px 16px; color: #dc2626; }

.dosya-fab { position: fixed; right: 18px; bottom: calc(104px + var(--ds-safe-bottom)); z-index: 20; display: inline-flex; align-items: center; gap: 8px; min-height: 52px; padding: 0 20px; border: 0; border-radius: 999px; background: var(--ds-primary); color: #fff; font: 700 14px var(--ds-font-main); box-shadow: 0 10px 24px rgba(25, 29, 36, .3); }
.dosya-fab:active { transform: scale(.96); }
.dosya-fab:disabled { opacity: .7; }
.dosya-fab-yukleniyor { width: 18px; height: 18px; border: 2px solid rgba(255, 255, 255, .4); border-top-color: #fff; border-radius: 50%; animation: dosya-don .8s linear infinite; }
@keyframes dosya-don { to { transform: rotate(360deg); } }

.dosya-toast { position: fixed; left: 16px; right: 16px; bottom: calc(168px + var(--ds-safe-bottom)); z-index: 60; margin: 0; padding: 12px 16px; border-radius: var(--ds-radius-pill); text-align: center; font: 600 13px var(--ds-font-main); background: var(--ds-surface-dark); color: var(--ds-ink-light); }
.dosya-backdrop { position: fixed; inset: 0; z-index: 70; display: flex; align-items: flex-end; background: rgba(25, 29, 36, .5); }
.dosya-sheet { width: 100%; max-height: 90dvh; overflow: auto; display: flex; flex-direction: column; gap: 8px; padding: 10px 16px calc(16px + var(--ds-safe-bottom)); border-radius: 28px 28px 0 0; background: var(--ds-surface); }
.dosya-tutamac { align-self: center; width: 40px; height: 4px; margin-bottom: 6px; border-radius: 4px; background: var(--ds-line); }
.dosya-sheet h3 { margin: 0; font-size: 16px; color: var(--ds-ink); word-break: break-word; }
.dosya-sheet-head { display: flex; align-items: center; gap: 12px; padding-bottom: 6px; }
.dosya-sheet-head small, .dosya-alt { color: var(--ds-muted); font-size: 12px; }
.dosya-sheet button, .dosya-sheet a { display: flex; align-items: center; justify-content: center; gap: 8px; min-height: 48px; padding: 0 16px; border: 1px solid var(--ds-border); border-radius: var(--ds-radius-pill); background: var(--ds-surface-subtle); color: var(--ds-ink); font: 600 14px var(--ds-font-main); text-decoration: none; }
.dosya-sheet .is-primary { background: var(--ds-primary); color: #fff; border-color: var(--ds-primary); }
.dosya-sheet .is-danger { color: #dc2626; }
.dosya-sheet .is-cancel { background: transparent; color: var(--ds-muted); }
.dosya-sheet input, .dosya-sheet select { min-height: 48px; padding: 0 14px; border-radius: 16px; border: 1px solid var(--ds-line); background: var(--ds-surface-subtle); font: 500 16px var(--ds-font-main); color: var(--ds-ink); width: 100%; box-sizing: border-box; }
.dosya-sheet label { display: grid; gap: 4px; font-size: 12px; color: var(--ds-muted); }
.dosya-paylasim { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 8px 4px; border-top: 1px solid var(--ds-line); }
.dosya-paylasim span { display: grid; font-size: 13px; color: var(--ds-ink); }
.dosya-paylasim small { color: var(--ds-muted); font-size: 12px; }
.dosya-paylasim button { min-height: 44px; flex-shrink: 0; }
</style>
