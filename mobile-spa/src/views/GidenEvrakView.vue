<script setup>
import { ref, reactive, computed, onMounted, nextTick, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  AlertCircle,
  AlignCenter,
  AlignJustify,
  AlignLeft,
  AlignRight,
  ArrowDown,
  ArrowLeft,
  ArrowUp,
  Bold,
  Bookmark,
  BookOpen,
  Building2,
  Calendar,
  Check,
  CheckCircle2,
  ChevronRight,
  Code,
  Download,
  Eraser,
  Eye,
  FileCheck,
  FileText,
  Gavel,
  Indent,
  Italic,
  List,
  ListOrdered,
  Lock,
  Outdent,
  Paperclip,
  PenTool,
  Plus,
  Redo2,
  RotateCcw,
  Save,
  Send,
  Sparkles,
  Strikethrough,
  Trash2,
  Underline,
  Undo2,
  Upload,
  User,
  Users,
  X,
} from '@lucide/vue'
import TopBar from '../components/TopBar.vue'
import { auth } from '../services/auth.js'
import { evrakApi } from '../services/evrak.js'

const route = useRoute()
const router = useRouter()

// Toast Bildirimi
const toastMessage = ref('')
const toastType = ref('success')
let toastTimer = null

function showToast(msg, type = 'success') {
  toastMessage.value = msg
  toastType.value = type
  if (toastTimer) clearTimeout(toastTimer)
  toastTimer = setTimeout(() => {
    toastMessage.value = ''
  }, 3500)
}

// Aktif Sekme: 'bilgiler' | 'icerik' | 'ekler-imza'
const activeTab = ref('bilgiler')

// Yüklenme & Kaydetme Durumları
const loading = ref(false)
const saving = ref(false)
const submittingApproval = ref(false)
const actionLoading = ref(false)

// Düzenlenen Evrak Bilgisi
const documentId = ref(route.query.id || '')
const isEdit = computed(() => Boolean(documentId.value))
const record = ref(null)

// Meta Seçenekleri
const meta = ref({
  personeller: [],
  ofis_personeller: [],
  araclar: [],
  signing_users: [],
  gelen_evraklar: [],
  icra_personeller: [],
  next_evrak_no: { gelen: '', giden: '' },
})

// Hızlı Konu Seçenekleri (EvrakView ile birebir uyumlu)
const presetKonular = [
  'İcra Yazısı',
  'Maaş Haczi Cevabı',
  'Haciz Kaldırma Yazısı',
  'Trafik Cezası',
  'Sigorta Bildirimi',
  'Resmi Yazışma',
]

// Form Verileri
const form = reactive({
  id: '',
  evrak_tipi: 'giden',
  tarih: new Date().toISOString().substring(0, 10),
  evrak_no: '',
  konu: '',
  kurum_adi: '',
  muhatap_alt_birim: '',
  muhatap_adres: '',
  personel_id: '',
  ilgili_personel_id: '',
  ilgili_evrak_id: '',
  ust_yazi_gerekli_degil: false,
  yazi_tipi: 'times_new_roman', // 'times_new_roman' | 'arial'
  aciklama: '',
  ilgiler: '',
  ekler: '',
  imza_kullanici_ids: [],
  imza_kimin_adina: [],
})

// Ek Dosyalar
const existingAttachments = ref([])
const removedAttachmentIds = ref([])
const newFiles = ref([])

// Modallar
const showSignerModal = ref(false)
const showIcraModal = ref(false)
const showAiTaslakModal = ref(false)
const showAiSecimModal = ref(false)
const showPdfModal = ref(false)
const showIadeModal = ref(false)
const showTemplateModal = ref(false)

// Editör ve Biçimlendirme Durumu
const editorRef = ref(null)
const selectedPunto = ref('12')
const showHtmlSource = ref(false)

// Hazır Resmî Kalıplar
const quickPhrases = [
  { label: 'Arz Ederim', text: 'Bilgilerinize arz ederim.' },
  { label: 'Rica Ederim', text: 'Gereğini rica ederim.' },
  { label: 'Arz ve Rica Ederim', text: 'Bilgilerinizi ve gereğini arz ve rica ederim.' },
  { label: 'İlgide Kayıtlı Müzekkere', text: 'İlgide kayıtlı müzekkerenize istinaden;' },
  { label: 'Haciz Kesintisi Başlatıldı', text: 'Borçlu personelin maaşından kanuni 1/4 kesinti yapılmaya başlanmıştır.' },
  { label: 'Personel Ayrılmıştır', text: 'İlgili personelin şirketimizle ilişiği kesilmiştir.' },
  { label: 'Ekte Sunulmuştur', text: 'Talep edilen bilgi ve belgeler ekte sunulmuştur.' },
]

// Standart Resmî Şablonlar
const builtinTemplates = [
  {
    id: 'maas_haczi_cevap',
    title: 'Maaş Haczi Kesintisi Başlatma',
    subject: 'Maaş Haczi Müzekkeresi Cevabı',
    desc: 'Borçlu personelin maaşından 1/4 yasal kesinti başlatıldığını bildirir.',
    content: '<p><strong>İLGİ:</strong> İlgide kayıtlı müzekkereniz.</p><p>İlgide kayıtlı müzekkerenize istinaden, şirketimiz bünyesinde çalışmakta olan borçlu personelin maaş ve sair alacaklarından İcra ve İflas Kanunu hükümleri gereğince <strong>1/4 oranında haciz kesintisi</strong> yapılmasına başlanmıştır.</p><p>Yapılan ilk kesinti yasal süresi içerisinde ilgili müdürlüğünüz banka hesabına aktarılacaktır.</p><p>Bilgilerinize arz olunur.</p>',
  },
  {
    id: 'borclu_ayrildi',
    title: 'Borçlu İşten Ayrıldı Bildirimi',
    subject: 'İşten Ayrılış ve Haciz Bildirimi',
    desc: 'Personelin işten ayrıldığını ve alacağı kalmadığını bildirir.',
    content: '<p><strong>İLGİ:</strong> İlgide kayıtlı müzekkereniz.</p><p>İlgide kayıtlı müzekkerenize konu borçlu personelin şirketimiz ile ilişiği kesilmiş bulunmaktadır.</p><p>Ayrılış tarihi itibariyle dosyaya aktarılacak herhangi bir maaş, kıdem/ihbar tazminatı veya başkaca hak ve alacağı bulunmamaktadır.</p><p>Bilgilerinize arz olunur.</p>',
  },
  {
    id: 'oncelikli_haciz_var',
    title: 'Öncelikli Haciz Var (Sıraya Alındı)',
    subject: 'Maaş Haczi Sıra Bildirimi',
    desc: 'Maaşta önceden mevcut hacizler nedeniyle dosyanın sıraya alındığını bildirir.',
    content: '<p><strong>İLGİ:</strong> İlgide kayıtlı müzekkereniz.</p><p>İlgide kayıtlı müzekkereniz incelenmiştir. Borçlu personelin maaş ve ücreti üzerinde daha önce kesinleşmiş icra hacizleri bulunması sebebiyle, İİK hükümleri gereğince müzekkereniz sıraya alınmıştır.</p><p>Önceki haciz dosyaları tamamlandığında dosyanızdan kesintiye başlanarak müdürlüğünüze ayrıca bilgi verilecektir.</p><p>Bilgilerinize arz olunur.</p>',
  },
  {
    id: 'haciz_kaldirma_teyit',
    title: 'Haciz Kaldırma (Fek) Teyidi',
    subject: 'Haciz Kaldırma Teyit Yazısı',
    desc: 'Haczin kaldırıldığını ve kesintinin durdurulduğunu teyit eder.',
    content: '<p><strong>İLGİ:</strong> İlgide kayıtlı haciz fek (kaldırma) müzekkereniz.</p><p>İlgide kayıtlı müzekkereniz uyarınca, borçlu personelimizin maaş ve ücretleri üzerindeki haciz kesintisi işlemi derhal durdurulmuş ve kayıtlarımızdan kaldırılmıştır.</p><p>Bilgilerinize arz olunur.</p>',
  },
  {
    id: 'genel_bilgi_ust_yazi',
    title: 'Genel Bilgilendirme ve Ek Sunumu',
    subject: 'İstenen Bilgi ve Belgelerin Sunumu',
    desc: 'Talep edilen belgelerin üst yazı ile kuruma sunulması.',
    content: '<p><strong>İLGİ:</strong> İlgide kayıtlı yazınız.</p><p>İlgide kayıtlı yazınız incelenmiş olup, şirketimizden talep edilen bilgi, belge ve raporlar ekte eksiksiz olarak sunulmuştur.</p><p>Gereğini ve bilgilerinizi arz ederiz.</p>',
  },
]

// İcra Modal Durumu
const icraPersonelId = ref('')
const icraDosyalari = ref([])
const selectedIcraId = ref('')
const icraLoading = ref(false)

// AI Taslak Modal Durumu
const aiTalimat = ref('')
const aiFile = ref(null)
const aiLoading = ref(false)

// AI Metin Düzenleme Modal Durumu
const aiSecimTalimat = ref('')
const aiSecimLoading = ref(false)

// İade Gerekçesi
const iadeGerekcesi = ref('')

// Hesaplanan Durumlar
const isLocked = computed(() => {
  if (!record.value) return false
  return (record.value.onay_durumu || 'taslak') !== 'taslak'
})

const siraBende = computed(() => Boolean(record.value?.sira_bende))
const canRevoke = computed(() => Boolean(record.value?.can_revoke))
const approvalSteps = computed(() => record.value?.approval_steps || [])

// Seçili İmzacılar Bilgisi (Sıralı)
const selectedSignersList = computed(() => {
  return form.imza_kullanici_ids.map((encId, index) => {
    const user = meta.value.signing_users.find(u => u.id === encId) || {
      name: 'Yetkili',
      title: 'İmza Sahibi',
    }
    return {
      encId,
      name: user.name,
      title: user.title,
      kiminAdina: form.imza_kimin_adina[index] || '',
    }
  })
})

// Editör Yardımcı Metotları & Senkronizasyon
function syncEditor(val = null) {
  nextTick(() => {
    if (editorRef.value) {
      const targetHtml = val !== null ? val : (form.aciklama || '')
      if (editorRef.value.innerHTML !== targetHtml) {
        editorRef.value.innerHTML = targetHtml
      }
    }
  })
}

function onEditorInput(e) {
  form.aciklama = e.target.innerHTML
}

function toggleSourceView() {
  showHtmlSource.value = !showHtmlSource.value
  if (!showHtmlSource.value) {
    syncEditor()
  }
}

function formatText(command, value = null) {
  if (isLocked.value) return
  if (editorRef.value) {
    editorRef.value.focus()
  }
  document.execCommand(command, false, value)
  if (editorRef.value) {
    form.aciklama = editorRef.value.innerHTML
  }
}

function setFontFamily(fontKey) {
  form.yazi_tipi = fontKey
  const fontName = fontKey === 'arial' ? 'Arial, Helvetica, sans-serif' : '"Times New Roman", Times, serif'
  formatText('fontName', fontName)
}

function setPunto(punto) {
  selectedPunto.value = String(punto)
  const sizeMap = { '10': '2', '11': '2', '12': '3', '14': '4' }
  formatText('fontSize', sizeMap[String(punto)] || '3')
}

function insertPhrase(phraseText) {
  if (isLocked.value) return
  if (editorRef.value) {
    editorRef.value.focus()
  }
  const htmlToInsert = `<p>${phraseText}</p>`
  document.execCommand('insertHTML', false, htmlToInsert)
  if (editorRef.value) {
    form.aciklama = editorRef.value.innerHTML
  } else {
    form.aciklama = (form.aciklama ? form.aciklama + '\n' : '') + phraseText
  }
}

function applyTemplate(tpl) {
  if (form.aciklama.trim() && !confirm(`"${tpl.title}" şablonu yüklenecektir. Mevcut yazı içeriğinizin yerine geçsin mi?`)) {
    return
  }
  if (tpl.subject && !form.konu) {
    form.konu = tpl.subject
  }
  form.aciklama = tpl.content
  syncEditor(tpl.content)
  showTemplateModal.value = false
  showToast(`"${tpl.title}" şablonu uygulandı.`, 'success')
}

watch(activeTab, (newTab) => {
  if (newTab === 'icerik') {
    syncEditor()
  }
})

onMounted(async () => {
  await loadMeta()
  if (isEdit.value) {
    await loadRecord(documentId.value)
  } else {
    const icraParam = route.query.icra_id
    if (icraParam) {
      await applyIcraTaslak(icraParam)
    }
    if (!form.evrak_no && meta.value.next_evrak_no?.giden) {
      form.evrak_no = String(meta.value.next_evrak_no.giden)
    }
  }
})

async function loadMeta() {
  try {
    const res = await evrakApi.meta()
    if (res.meta) {
      meta.value = res.meta
    }
  } catch (err) {
    console.error('Meta yüklenemedi:', err)
  }
}

async function loadRecord(id) {
  loading.value = true
  try {
    const res = await evrakApi.detail(id)
    if (res.data) {
      record.value = res.data
      form.id = res.data.id
      form.evrak_tipi = 'giden'
      form.tarih = res.data.tarih || new Date().toISOString().substring(0, 10)
      form.evrak_no = res.data.evrak_no || ''
      form.konu = res.data.konu || ''
      form.kurum_adi = res.data.kurum_adi || ''
      form.muhatap_alt_birim = res.data.muhatap_alt_birim || ''
      form.muhatap_adres = res.data.muhatap_adres || ''
      form.personel_id = res.data.personel_id || ''
      form.ilgili_personel_id = res.data.ilgili_personel_id || ''
      form.ilgili_evrak_id = res.data.ilgili_evrak_id || ''
      form.ust_yazi_gerekli_degil = Boolean(res.data.ust_yazi_gerekli_degil)
      form.yazi_tipi = res.data.yazi_tipi || 'times_new_roman'
      form.aciklama = res.data.aciklama || ''
      form.ilgiler = res.data.ilgiler || ''
      form.ekler = res.data.ekler_metin || ''
      form.imza_kullanici_ids = res.data.imza_kullanici_ids || []
      form.imza_kimin_adina = res.data.imza_kimin_adina || []

      existingAttachments.value = (res.data.attachments || []).map(att => ({
        ...att,
        url: att.url || evrakApi.getFileStreamUrl(att.id || att.path, !att.id),
      }))

      syncEditor()
    }
  } catch (err) {
    showToast(err.message || 'Evrak detayları alınamadı.', 'error')
    router.replace('/evrak-takip')
  } finally {
    loading.value = false
  }
}

function openSignerPicker() {
  if (isLocked.value) return
  showSignerModal.value = true
}

function isSignerSelected(encId) {
  return form.imza_kullanici_ids.includes(encId)
}

function toggleSigner(encId) {
  if (isSignerSelected(encId)) {
    const idx = form.imza_kullanici_ids.indexOf(encId)
    form.imza_kullanici_ids.splice(idx, 1)
    form.imza_kimin_adina.splice(idx, 1)
  } else {
    if (form.imza_kullanici_ids.length >= 3) {
      showToast('En fazla 3 imza yetkilisi seçebilirsiniz.', 'warning')
      return
    }
    form.imza_kullanici_ids.push(encId)
    form.imza_kimin_adina.push('')
  }
}

function moveSigner(index, direction) {
  const targetIndex = index + direction
  if (targetIndex < 0 || targetIndex >= form.imza_kullanici_ids.length) return

  const tempId = form.imza_kullanici_ids[index]
  form.imza_kullanici_ids[index] = form.imza_kullanici_ids[targetIndex]
  form.imza_kullanici_ids[targetIndex] = tempId

  const tempKimin = form.imza_kimin_adina[index]
  form.imza_kimin_adina[index] = form.imza_kimin_adina[targetIndex]
  form.imza_kimin_adina[targetIndex] = tempKimin
}

function updateKiminAdina(index, value) {
  form.imza_kimin_adina[index] = value
}

function onIlgiliEvrakChange() {
  if (!form.ilgili_evrak_id) return
  const evrak = meta.value.gelen_evraklar.find(g => g.id === form.ilgili_evrak_id)
  if (evrak && !form.ilgiler) {
    form.ilgiler = `${evrak.tarih || ''} tarihli ve ${evrak.evrak_no || ''} sayılı yazınız.`
  }
}

function handleFileChange(event) {
  const files = Array.from(event.target.files || [])
  for (const file of files) {
    if (file.size > 15 * 1024 * 1024) {
      showToast(`"${file.name}" dosyası 15MB sınırını aşıyor.`, 'warning')
      continue
    }
    newFiles.value.push({
      file,
      name: file.name,
      size: file.size,
      sizeFormatted: (file.size / 1024 / 1024).toFixed(2) + ' MB',
    })
  }
  event.target.value = ''
}

function removeNewFile(index) {
  newFiles.value.splice(index, 1)
}

function removeExistingAttachment(id) {
  if (confirm('Bu eki silmek istediğinize emin misiniz?')) {
    removedAttachmentIds.value.push(id)
    existingAttachments.value = existingAttachments.value.filter(a => a.id !== id)
  }
}

async function onIcraPersonelSelect() {
  if (!icraPersonelId.value) {
    icraDosyalari.value = []
    selectedIcraId.value = ''
    return
  }
  icraLoading.value = true
  try {
    const res = await evrakApi.getIcraDosyalari(icraPersonelId.value)
    icraDosyalari.value = res.dosyalar || []
    selectedIcraId.value = icraDosyalari.value.length > 0 ? icraDosyalari.value[0].id : ''
  } catch (err) {
    showToast(err.message || 'İcra dosyaları alınamadı.', 'error')
  } finally {
    icraLoading.value = false
  }
}

async function createIcraUstYazi() {
  if (!selectedIcraId.value) {
    showToast('Lütfen bir icra dosyası seçiniz.', 'warning')
    return
  }
  icraLoading.value = true
  try {
    await applyIcraTaslak(selectedIcraId.value)
    showIcraModal.value = false
    showToast('İcra üst yazı taslağı başarıyla aktarıldı.', 'success')
  } catch (err) {
    showToast(err.message || 'İcra taslağı oluşturulamadı.', 'error')
  } finally {
    icraLoading.value = false
  }
}

async function applyIcraTaslak(icraId) {
  const res = await evrakApi.getIcraTaslak(icraId)
  if (res.taslak) {
    const t = res.taslak
    form.konu = t.konu || form.konu
    form.kurum_adi = t.kurum_adi || form.kurum_adi
    form.muhatap_alt_birim = t.muhatap_alt_birim || form.muhatap_alt_birim
    form.muhatap_adres = t.muhatap_adres || form.muhatap_adres
    form.ilgiler = t.ilgiler || form.ilgiler
    form.aciklama = t.aciklama_html || form.aciklama
    if (t.personel_id) {
      form.ilgili_personel_id = t.personel_id
    }
    syncEditor()
    activeTab.value = 'icerik'
  }
}

async function generateAiTaslak() {
  if (!aiTalimat.value.trim()) {
    showToast('Lütfen yapay zekâya bir talimat yazınız.', 'warning')
    return
  }
  aiLoading.value = true
  try {
    const fd = new FormData()
    fd.append('talimat', aiTalimat.value)
    if (aiFile.value) {
      fd.append('dosya', aiFile.value)
    }
    const res = await evrakApi.generateAiTaslak(fd, auth.state.csrfToken)
    if (res.taslak) {
      const t = res.taslak
      if (t.konu) form.konu = t.konu
      if (t.kurum_adi) form.kurum_adi = t.kurum_adi
      if (t.muhatap_alt_birim) form.muhatap_alt_birim = t.muhatap_alt_birim
      if (t.muhatap_adres) form.muhatap_adres = t.muhatap_adres
      if (t.ilgiler) form.ilgiler = t.ilgiler
      if (t.aciklama_html) form.aciklama = t.aciklama_html
      syncEditor()
      showAiTaslakModal.value = false
      aiTalimat.value = ''
      aiFile.value = null
      activeTab.value = 'icerik'
      showToast('AI taslağı başarıyla oluşturuldu ve alanlara dolduruldu.', 'success')
    }
  } catch (err) {
    showToast(err.message || 'AI taslağı üretilemedi.', 'error')
  } finally {
    aiLoading.value = false
  }
}

async function applyAiTransform(presetPrompt = '') {
  const instruction = presetPrompt || aiSecimTalimat.value
  if (!instruction.trim()) {
    showToast('Lütfen düzenleme talimatını belirtiniz.', 'warning')
    return
  }
  if (!form.aciklama.trim()) {
    showToast('Düzenlenecek bir metin bulunmuyor.', 'warning')
    return
  }

  aiSecimLoading.value = true
  try {
    const fd = new FormData()
    fd.append('secili_metin', form.aciklama)
    fd.append('talimat', instruction)
    fd.append('icerik', form.konu + ' - ' + form.kurum_adi)

    const res = await evrakApi.reviseAiText(fd, auth.state.csrfToken)
    if (res.html) {
      form.aciklama = res.html
      syncEditor()
      showAiSecimModal.value = false
      aiSecimTalimat.value = ''
      showToast('Yapay zekâ metni başarıyla düzenledi.', 'success')
    }
  } catch (err) {
    showToast(err.message || 'Metin düzenlenemedi.', 'error')
  } finally {
    aiSecimLoading.value = false
  }
}

const previewUrl = computed(() => {
  if (!form.id) return ''
  return evrakApi.getPdfUrl(form.id)
})

function previewPdf() {
  if (!form.id) {
    showToast('Önizleme için lütfen önce evrakı kaydediniz.', 'info')
    return
  }
  showPdfModal.value = true
}

async function submitForm(silent = false) {
  if (!form.konu.trim()) {
    activeTab.value = 'bilgiler'
    showToast('Lütfen evrak konusunu belirtiniz.', 'warning')
    return false
  }
  if (!form.kurum_adi.trim()) {
    activeTab.value = 'bilgiler'
    showToast('Lütfen muhatap kurum veya firma adını belirtiniz.', 'warning')
    return false
  }

  saving.value = true
  try {
    const fd = new FormData()
    if (form.id) fd.append('id', form.id)
    fd.append('evrak_tipi', 'giden')
    fd.append('tarih', form.tarih)
    fd.append('evrak_no', form.evrak_no)
    fd.append('konu', form.konu)
    fd.append('kurum_adi', form.kurum_adi)
    fd.append('muhatap_alt_birim', form.muhatap_alt_birim)
    fd.append('muhatap_adres', form.muhatap_adres)
    fd.append('personel_id', form.personel_id)
    fd.append('ilgili_personel_id', form.ilgili_personel_id)
    fd.append('ilgili_evrak_id', form.ilgili_evrak_id)
    fd.append('ust_yazi_gerekli_degil', form.ust_yazi_gerekli_degil ? '1' : '0')
    fd.append('yazi_tipi', form.yazi_tipi)
    fd.append('aciklama', form.aciklama)
    fd.append('ilgiler', form.ilgiler)
    fd.append('ekler', form.ekler)
    fd.append('imza_kullanici_ids', JSON.stringify(form.imza_kullanici_ids))
    fd.append('imza_kimin_adina', JSON.stringify(form.imza_kimin_adina))
    fd.append('silinen_ek_ids', JSON.stringify(removedAttachmentIds.value))

    for (const fObj of newFiles.value) {
      fd.append('dosyalar[]', fObj.file)
    }

    const res = await evrakApi.save(fd, auth.state.csrfToken)
    if (!silent) {
      showToast(res.message || 'Giden evrak başarıyla kaydedildi.', 'success')
    }

    if (!form.id && res.id) {
      form.id = res.id
      documentId.value = res.id
      router.replace({ path: '/giden-evrak', query: { id: res.id } })
    }

    if (form.id) {
      await loadRecord(form.id)
      newFiles.value = []
      removedAttachmentIds.value = []
    }

    return true
  } catch (err) {
    showToast(err.message || 'Evrak kaydedilemedi.', 'error')
    return false
  } finally {
    saving.value = false
  }
}

async function onayaSun() {
  if (!form.id) {
    const saved = await submitForm(true)
    if (!saved) return
  }

  if (form.imza_kullanici_ids.length === 0) {
    activeTab.value = 'ekler-imza'
    showToast('Lütfen en az bir imza yetkilisi seçiniz.', 'warning')
    return
  }

  if (!confirm('Evrak elektronik imza onay sürecine sunulacaktır. Onaylıyor musunuz?')) return

  submittingApproval.value = true
  try {
    const res = await evrakApi.eImzaOnayaSun(form.id, auth.state.csrfToken)
    showToast(res.message || 'Evrak onaya sunuldu.', 'success')
    await loadRecord(form.id)
  } catch (err) {
    showToast(err.message || 'Onaya sunulamadı.', 'error')
  } finally {
    submittingApproval.value = false
  }
}

async function imzaOnayla() {
  if (!confirm('Resmî evrakı elektronik olarak onaylamak istediğinize emin misiniz?')) return
  actionLoading.value = true
  try {
    const res = await evrakApi.eImzaOnayla(form.id, auth.state.csrfToken)
    showToast(res.message || 'İmzanız kaydedildi.', 'success')
    await loadRecord(form.id)
  } catch (err) {
    showToast(err.message || 'İmza onaylanamadı.', 'error')
  } finally {
    actionLoading.value = false
  }
}

function promptIade() {
  iadeGerekcesi.value = ''
  showIadeModal.value = true
}

async function confirmIade() {
  if (iadeGerekcesi.value.trim().length < 5) {
    showToast('Lütfen en az 5 karakterlik bir iade gerekçesi yazınız.', 'warning')
    return
  }
  actionLoading.value = true
  try {
    const res = await evrakApi.eImzaIade(form.id, iadeGerekcesi.value.trim(), auth.state.csrfToken)
    showToast(res.message || 'Evrak düzeltilmek üzere iade edildi.', 'success')
    showIadeModal.value = false
    iadeGerekcesi.value = ''
    await loadRecord(form.id)
  } catch (err) {
    showToast(err.message || 'İade işlemi başarısız.', 'error')
  } finally {
    actionLoading.value = false
  }
}

async function imzaGeriAl() {
  if (!confirm('Evrak imza sürecinden çekilip üzerinize taslak olarak geri alınacaktır. Onaylıyor musunuz?')) return
  actionLoading.value = true
  try {
    const res = await evrakApi.eImzaGeriAl(form.id, auth.state.csrfToken)
    showToast(res.message || 'Evrak üzerinize geri alındı.', 'success')
    await loadRecord(form.id)
  } catch (err) {
    showToast(err.message || 'Evrak geri alınamadı.', 'error')
  } finally {
    actionLoading.value = false
  }
}
</script>

<template>
  <section class="ds-screen giden-view-screen">
    <!-- Üst TopBar: EvrakView ile Birebir Aynı Bileşen & Stil -->
    <TopBar
      eyebrow="RESMÎ YAZIŞMA & E-İMZA"
      :title="isEdit ? 'Giden Evrak Düzenle' : 'Yeni Giden Evrak'"
      :subtitle="form.evrak_no ? `Sayı: ${form.evrak_no}` : 'Resmî yazı, e-imza onay süreci ve ekler'"
      :back="true"
    >
      <template #actions>
        <button
          v-if="form.id"
          type="button"
          class="clay-circle-btn"
          title="PDF Önizle"
          @click="previewPdf"
        >
          <Eye :size="19" />
        </button>
        <button
          type="button"
          class="clay-circle-btn giden-save-btn"
          :class="{ 'is-saving': saving }"
          :disabled="saving"
          title="Evrakı Kaydet"
          @click="submitForm(false)"
        >
          <Save :size="19" />
        </button>
      </template>
    </TopBar>

    <!-- Toast Bildirimi -->
    <Transition name="fade">
      <div v-if="toastMessage" class="evrak-toast" :class="`evrak-toast--${toastType}`">
        <CheckCircle2 v-if="toastType === 'success'" :size="18" />
        <AlertCircle v-else :size="18" />
        <span>{{ toastMessage }}</span>
      </div>
    </Transition>

    <!-- Kilitli Durum Banner'ı -->
    <div v-if="isLocked" class="evrak-urgent-alert giden-locked-alert">
      <div class="evrak-urgent-alert__icon">
        <Lock :size="20" />
      </div>
      <div class="evrak-urgent-alert__content">
        <strong>{{ record?.onay_durumu === 'onaylandi' ? 'Evrak Elektronik İmzalıdır' : 'Evrak İmza Onay Sürecindedir' }}</strong>
        <p>{{ record?.onay_durumu === 'onaylandi' ? 'Tüm imza sahipleri onaylamıştır, içerik değiştirilemez.' : 'Süreç tamamlanana kadar evrak alanları kilitlidir.' }}</p>
      </div>
    </div>

    <!-- İade Uyarısı -->
    <div v-if="record?.iade_gerekcesi" class="evrak-returned-notice mb-3">
      <AlertCircle :size="20" />
      <div>
        <strong>Evrak İade Edildi {{ record?.iade_eden ? `(${record.iade_eden})` : '' }}</strong>
        <p><strong>Gerekçe:</strong> {{ record.iade_gerekcesi }}</p>
      </div>
    </div>

    <!-- 2 Minimal Bento Sihirbaz Kartı (EvrakView KPI Kartları Formatında) -->
    <div class="bento-metrics-grid giden-wizards-grid">
      <!-- İcra Üst Yazısı Sihirbazı -->
      <button
        type="button"
        class="bento-metric-card giden-wizard-card"
        :disabled="isLocked"
        @click="showIcraModal = true"
      >
        <div class="bento-metric-icon bg-emerald-soft text-emerald">
          <Gavel :size="20" />
        </div>
        <div class="bento-metric-data">
          <span class="bento-metric-val giden-card-title">İcra Sihirbazı</span>
          <span class="bento-metric-label">Dosyadan otomatik doldur</span>
        </div>
      </button>

      <!-- AI Taslak Asistanı -->
      <button
        type="button"
        class="bento-metric-card giden-wizard-card"
        :disabled="isLocked"
        @click="showAiTaslakModal = true"
      >
        <div class="bento-metric-icon bg-amber-soft text-amber">
          <Sparkles :size="20" />
        </div>
        <div class="bento-metric-data">
          <span class="bento-metric-val giden-card-title">AI Asistanı</span>
          <span class="bento-metric-label">Metin veya görselden üret</span>
        </div>
      </button>
    </div>

    <!-- Sekmeler: EvrakView ile Birebir Aynı Filtre Hapları (.clay-tabs-wrap & .clay-filter-pill) -->
    <div class="clay-tabs-wrap giden-tabs">
      <button
        type="button"
        class="clay-filter-pill"
        :class="{ 'is-active': activeTab === 'bilgiler' }"
        @click="activeTab = 'bilgiler'"
      >
        <FileText :size="15" />
        <span>Evrak Bilgileri</span>
      </button>

      <button
        type="button"
        class="clay-filter-pill"
        :class="{ 'is-active': activeTab === 'icerik' }"
        @click="activeTab = 'icerik'"
      >
        <AlignLeft :size="15" />
        <span>Resmî İçerik</span>
      </button>

      <button
        type="button"
        class="clay-filter-pill"
        :class="{ 'is-active': activeTab === 'ekler-imza' }"
        @click="activeTab = 'ekler-imza'"
      >
        <PenTool :size="15" />
        <span>Ekler & E-İmza</span>
        <span v-if="form.imza_kullanici_ids.length > 0" class="giden-tab-count">
          {{ form.imza_kullanici_ids.length }}
        </span>
      </button>
    </div>

    <!-- ==================================================== -->
    <!-- SEKME 1: EVRAK VE MUHATAP BİLGİLERİ -->
    <!-- ==================================================== -->
    <div v-show="activeTab === 'bilgiler'" class="giden-tab-body">
      <section class="bento-card giden-section-card">
        <div class="giden-card-head">
          <div class="giden-card-head__icon bg-primary-soft text-primary">
            <FileText :size="18" />
          </div>
          <div class="giden-card-head__text">
            <span class="giden-card-head__eyebrow">RESMÎ EVRAK DETAYLARI</span>
            <h2 class="giden-card-head__title">Muhatap ve Evrak Bilgileri</h2>
          </div>
        </div>

        <div class="giden-fields-stack">
          <!-- Tarih ve Sayı -->
          <div class="evrak-form-grid-2">
            <div class="evrak-form-group">
              <label class="evrak-form-label">EVRAK TARİHİ *</label>
              <input
                type="date"
                v-model="form.tarih"
                :disabled="isLocked"
                class="evrak-input"
                required
              />
            </div>
            <div class="evrak-form-group">
              <label class="evrak-form-label">SAYI / EVRAK NO *</label>
              <input
                type="text"
                v-model="form.evrak_no"
                placeholder="Örn: 2026-0042"
                :disabled="isLocked"
                class="evrak-input"
                required
              />
            </div>
          </div>

          <!-- Konu ve Hızlı Konu Çipleri -->
          <div class="evrak-form-group">
            <label class="evrak-form-label">RESMÎ YAZI KONUSU *</label>
            <div class="evrak-preset-chips">
              <button
                v-for="konu in presetKonular"
                :key="konu"
                type="button"
                class="evrak-preset-chip"
                :class="{ 'is-selected': form.konu === konu }"
                :disabled="isLocked"
                @click="form.konu = konu"
              >
                {{ konu }}
              </button>
            </div>
            <input
              type="text"
              v-model="form.konu"
              placeholder="Örn: Maaş Haczi Müzekkeresi Cevabı"
              :disabled="isLocked"
              class="evrak-input"
              required
            />
          </div>

          <!-- Muhatap Kurum / Kişi Adı -->
          <div class="evrak-form-group">
            <label class="evrak-form-label">MUHATAP KURUM / KİŞİ ADI *</label>
            <input
              type="text"
              v-model="form.kurum_adi"
              placeholder="Örn: T.C. Bakırköy 1. İcra Dairesi / TEDAŞ"
              :disabled="isLocked"
              class="evrak-input"
              required
            />
          </div>

          <!-- Alt Birim / Bölüm -->
          <div class="evrak-form-group">
            <label class="evrak-form-label">ALT BİRİM / KALEM / MÜDÜRLÜK</label>
            <input
              type="text"
              v-model="form.muhatap_alt_birim"
              placeholder="Örn: Maaş Hacizleri Kalemi"
              :disabled="isLocked"
              class="evrak-input"
            />
          </div>

          <!-- Muhatap Adresi -->
          <div class="evrak-form-group">
            <label class="evrak-form-label">MUHATAP ADRESİ</label>
            <textarea
              v-model="form.muhatap_adres"
              rows="2"
              placeholder="Kurumun açık tebligat/yazışma adresi..."
              :disabled="isLocked"
              class="evrak-textarea"
            ></textarea>
          </div>

          <!-- Zimmetli ve İlgili Personel -->
          <div class="evrak-form-grid-2">
            <div class="evrak-form-group">
              <label class="evrak-form-label">ZİMMETLENEN PERSONEL</label>
              <select v-model="form.personel_id" :disabled="isLocked" class="evrak-select">
                <option value="">Seçiniz...</option>
                <option v-for="p in meta.ofis_personeller" :key="p.id" :value="p.id">
                  {{ p.name }}
                </option>
              </select>
            </div>

            <div class="evrak-form-group">
              <label class="evrak-form-label">İLGİLİ PERSONEL</label>
              <select v-model="form.ilgili_personel_id" :disabled="isLocked" class="evrak-select">
                <option value="">Seçiniz...</option>
                <option v-for="p in meta.personeller" :key="p.id" :value="p.id">
                  {{ p.name }}
                </option>
              </select>
            </div>
          </div>
        </div>

        <!-- Kart Altı Adım Aksiyonları -->
        <div class="giden-card-footer">
          <button type="button" class="evrak-btn evrak-btn--outline" @click="router.push('/evrak-takip')">
            <ArrowLeft :size="16" />
            <span>Evrak Listesi</span>
          </button>
          <button type="button" class="evrak-btn evrak-btn--primary" @click="activeTab = 'icerik'">
            <span>Devam Et: İçerik</span>
            <ChevronRight :size="16" />
          </button>
        </div>
      </section>
    </div>

    <!-- ==================================================== -->
    <!-- SEKME 2: RESMÎ YAZI İÇERİĞİ -->
    <!-- ==================================================== -->
    <div v-show="activeTab === 'icerik'" class="giden-tab-body">
      <section class="bento-card giden-section-card">
        <div class="giden-card-head giden-between">
          <div class="giden-card-head-left">
            <div class="giden-card-head__icon bg-primary-soft text-primary">
              <AlignLeft :size="18" />
            </div>
            <div class="giden-card-head__text">
              <span class="giden-card-head__eyebrow">RESMÎ METİN</span>
              <h2 class="giden-card-head__title">Yazı Formatı ve İçeriği</h2>
            </div>
          </div>

          <div class="giden-head-actions">
            <button
              type="button"
              class="giden-template-action-btn"
              :disabled="isLocked"
              title="Resmî hazır şablon seç"
              @click="showTemplateModal = true"
            >
              <BookOpen :size="15" />
              <span>Şablonlar</span>
            </button>

            <button
              type="button"
              class="giden-ai-action-btn"
              :disabled="isLocked || !form.aciklama.trim()"
              title="Seçili metni yapay zekâ ile resmi dile uyarla"
              @click="showAiSecimModal = true"
            >
              <Sparkles :size="15" />
              <span>AI ile Düzenle</span>
            </button>
          </div>
        </div>

        <div class="giden-fields-stack">
          <!-- Üst Yazı Gerekli Değil Toggle -->
          <div class="evrak-toggle-card">
            <div class="evrak-toggle-card__text">
              <FileCheck :size="19" class="text-primary" />
              <div>
                <strong>Üst Yazı Gerekli Değil</strong>
                <small>Yalnızca ek evraklar/belgeler iletilecekse işaretleyiniz</small>
              </div>
            </div>
            <input
              type="checkbox"
              v-model="form.ust_yazi_gerekli_degil"
              :disabled="isLocked"
              class="evrak-checkbox"
            />
          </div>

          <!-- YAZI TİPİ VE PUNTO SEÇİMİ (Resmî Yazışma Standardı) -->
          <div class="giden-typography-grid">
            <!-- Yazı Tipi -->
            <div class="evrak-form-group mb-0">
              <label class="evrak-form-label">YAZI TİPİ (FONT SEÇİMİ)</label>
              <div class="evrak-type-switch">
                <button
                  type="button"
                  class="evrak-switch-opt"
                  :class="{ 'is-active': form.yazi_tipi === 'times_new_roman' }"
                  :disabled="isLocked"
                  @click="setFontFamily('times_new_roman')"
                >
                  Times New Roman
                </button>
                <button
                  type="button"
                  class="evrak-switch-opt"
                  :class="{ 'is-active': form.yazi_tipi === 'arial' }"
                  :disabled="isLocked"
                  @click="setFontFamily('arial')"
                >
                  Arial
                </button>
              </div>
            </div>

            <!-- Punto Seçimi -->
            <div class="evrak-form-group mb-0">
              <label class="evrak-form-label">PUNTO BOYUTU</label>
              <div class="giden-punto-pills">
                <button
                  v-for="p in ['10', '11', '12', '14']"
                  :key="p"
                  type="button"
                  class="giden-punto-btn"
                  :class="{ 'is-active': selectedPunto === p }"
                  :disabled="isLocked"
                  @mousedown.prevent
                  @click="setPunto(p)"
                >
                  {{ p }}pt
                  <span v-if="p === '12'" class="giden-punto-tag">Standart</span>
                </button>
              </div>
            </div>
          </div>

          <!-- ZENGİN METİN BİÇİMLENDİRME ARAÇ ÇUBUĞU (TOOLBAR) -->
          <div class="evrak-form-group mb-0">
            <div class="giden-label-between">
              <label class="evrak-form-label">METİN BİÇİMLENDİRME ARAÇLARI</label>
              <button
                type="button"
                class="giden-source-toggle-btn"
                :class="{ 'is-active': showHtmlSource }"
                :title="showHtmlSource ? 'Görsel Editöre Dön' : 'HTML Kaynak Kodunu Düzenle'"
                @click="toggleSourceView"
              >
                <Code :size="14" />
                <span>{{ showHtmlSource ? 'Görsel Editör' : 'HTML' }}</span>
              </button>
            </div>

            <div class="giden-formatting-bar">
              <!-- Font Stilleri -->
              <div class="giden-tool-group">
                <button
                  type="button"
                  class="giden-tool-btn"
                  title="Kalın (Ctrl+B)"
                  :disabled="isLocked || showHtmlSource"
                  @mousedown.prevent
                  @click="formatText('bold')"
                >
                  <Bold :size="15" />
                </button>
                <button
                  type="button"
                  class="giden-tool-btn"
                  title="İtalik (Ctrl+I)"
                  :disabled="isLocked || showHtmlSource"
                  @mousedown.prevent
                  @click="formatText('italic')"
                >
                  <Italic :size="15" />
                </button>
                <button
                  type="button"
                  class="giden-tool-btn"
                  title="Altı Çizili (Ctrl+U)"
                  :disabled="isLocked || showHtmlSource"
                  @mousedown.prevent
                  @click="formatText('underline')"
                >
                  <Underline :size="15" />
                </button>
                <button
                  type="button"
                  class="giden-tool-btn"
                  title="Üstü Çizili"
                  :disabled="isLocked || showHtmlSource"
                  @mousedown.prevent
                  @click="formatText('strikeThrough')"
                >
                  <Strikethrough :size="15" />
                </button>
              </div>

              <!-- Hizalama (Resmî Yazışma için İki Yana Yasla Zorunludur) -->
              <div class="giden-tool-group">
                <button
                  type="button"
                  class="giden-tool-btn"
                  title="Sola Yasla"
                  :disabled="isLocked || showHtmlSource"
                  @mousedown.prevent
                  @click="formatText('justifyLeft')"
                >
                  <AlignLeft :size="15" />
                </button>
                <button
                  type="button"
                  class="giden-tool-btn"
                  title="Ortala"
                  :disabled="isLocked || showHtmlSource"
                  @mousedown.prevent
                  @click="formatText('justifyCenter')"
                >
                  <AlignCenter :size="15" />
                </button>
                <button
                  type="button"
                  class="giden-tool-btn"
                  title="Sağa Yasla"
                  :disabled="isLocked || showHtmlSource"
                  @mousedown.prevent
                  @click="formatText('justifyRight')"
                >
                  <AlignRight :size="15" />
                </button>
                <button
                  type="button"
                  class="giden-tool-btn is-highlight"
                  title="İki Yana Yasla (Resmî Yazışma Standardı)"
                  :disabled="isLocked || showHtmlSource"
                  @mousedown.prevent
                  @click="formatText('justifyFull')"
                >
                  <AlignJustify :size="15" />
                </button>
              </div>

              <!-- Listeler & Girinti -->
              <div class="giden-tool-group">
                <button
                  type="button"
                  class="giden-tool-btn"
                  title="Madde İşaretli Liste"
                  :disabled="isLocked || showHtmlSource"
                  @mousedown.prevent
                  @click="formatText('insertUnorderedList')"
                >
                  <List :size="15" />
                </button>
                <button
                  type="button"
                  class="giden-tool-btn"
                  title="Numaralı Liste"
                  :disabled="isLocked || showHtmlSource"
                  @mousedown.prevent
                  @click="formatText('insertOrderedList')"
                >
                  <ListOrdered :size="15" />
                </button>
                <button
                  type="button"
                  class="giden-tool-btn"
                  title="Girintiyi Azalt"
                  :disabled="isLocked || showHtmlSource"
                  @mousedown.prevent
                  @click="formatText('outdent')"
                >
                  <Outdent :size="15" />
                </button>
                <button
                  type="button"
                  class="giden-tool-btn"
                  title="Girintiyi Artır"
                  :disabled="isLocked || showHtmlSource"
                  @mousedown.prevent
                  @click="formatText('indent')"
                >
                  <Indent :size="15" />
                </button>
              </div>

              <!-- Geri / İleri & Temizle -->
              <div class="giden-tool-group">
                <button
                  type="button"
                  class="giden-tool-btn"
                  title="Biçimlendirmeyi Temizle"
                  :disabled="isLocked || showHtmlSource"
                  @mousedown.prevent
                  @click="formatText('removeFormat')"
                >
                  <Eraser :size="15" />
                </button>
                <button
                  type="button"
                  class="giden-tool-btn"
                  title="Geri Al (Ctrl+Z)"
                  :disabled="isLocked || showHtmlSource"
                  @mousedown.prevent
                  @click="formatText('undo')"
                >
                  <Undo2 :size="15" />
                </button>
                <button
                  type="button"
                  class="giden-tool-btn"
                  title="İleri Al (Ctrl+Y)"
                  :disabled="isLocked || showHtmlSource"
                  @mousedown.prevent
                  @click="formatText('redo')"
                >
                  <Redo2 :size="15" />
                </button>
              </div>
            </div>
          </div>

          <!-- HAZIR RESMÎ İFADELER / HIZLI ÇİPLER -->
          <div class="evrak-form-group mb-0">
            <div class="giden-label-between">
              <label class="evrak-form-label">HAZIR RESMÎ İFADELER (HIZLI EKLE)</label>
              <small class="text-muted">İmleç konumuna ekler</small>
            </div>
            <div class="giden-quick-phrases">
              <button
                v-for="qp in quickPhrases"
                :key="qp.label"
                type="button"
                class="giden-phrase-chip"
                :disabled="isLocked"
                @mousedown.prevent
                @click="insertPhrase(qp.text)"
              >
                <span>+</span>
                <span>{{ qp.label }}</span>
              </button>
            </div>
          </div>

          <!-- RESMÎ YAZI GÖVDE METNİ (WYSIWYG & HTML SOURCE) -->
          <div class="evrak-form-group">
            <div class="giden-label-between">
              <label class="evrak-form-label">RESMÎ YAZI GÖVDE METNİ *</label>
              <small class="text-muted">{{ form.aciklama ? form.aciklama.replace(/<[^>]+>/g, '').length : 0 }} karakter</small>
            </div>

            <!-- HTML Kaynak Modu -->
            <textarea
              v-if="showHtmlSource"
              v-model="form.aciklama"
              rows="14"
              class="evrak-textarea giden-source-textarea"
              placeholder="HTML kodunu buraya giriniz..."
              :disabled="isLocked"
            ></textarea>

            <!-- WYSIWYG Canlı Editör Modu -->
            <div
              v-else
              ref="editorRef"
              class="giden-rich-editor"
              :class="[
                form.yazi_tipi === 'times_new_roman' ? 'font-serif' : 'font-sans',
                `punto-${selectedPunto}`
              ]"
              :contenteditable="!isLocked"
              @input="onEditorInput"
              data-placeholder="Resmî yazı metnini buraya yazınız veya üstteki araçlar ve şablonlarla oluşturunuz..."
            ></div>

            <small class="giden-field-note">
              Resmî Yazışma Yönetmeliği: Yazı alanı Times New Roman (12 punto) veya Arial (11 punto) ile iki yana yaslı biçimde PDF çıktısına aktarılır.
            </small>
          </div>
        </div>

        <!-- Kart Altı Adım Aksiyonları -->
        <div class="giden-card-footer">
          <button type="button" class="evrak-btn evrak-btn--outline" @click="activeTab = 'bilgiler'">
            <ArrowLeft :size="16" />
            <span>Geri: Bilgiler</span>
          </button>
          <button type="button" class="evrak-btn evrak-btn--primary" @click="activeTab = 'ekler-imza'">
            <span>Devam Et: Ekler & İmza</span>
            <ChevronRight :size="16" />
          </button>
        </div>
      </section>
    </div>

    <!-- ==================================================== -->
    <!-- SEKME 3: EKLER VE E-İMZA -->
    <!-- ==================================================== -->
    <div v-show="activeTab === 'ekler-imza'" class="giden-tab-body">
      <!-- 1. İmza Yetkilileri Kartı -->
      <section class="bento-card giden-section-card">
        <div class="giden-card-head giden-between">
          <div class="giden-card-head-left">
            <div class="giden-card-head__icon bg-primary-soft text-primary">
              <PenTool :size="18" />
            </div>
            <div class="giden-card-head__text">
              <span class="giden-card-head__eyebrow">ELEKTRONİK İMZA</span>
              <h2 class="giden-card-head__title">İmza Sahibi Yetkililer (En Fazla 3)</h2>
            </div>
          </div>

          <button
            type="button"
            class="evrak-btn evrak-btn--secondary giden-mini-btn"
            :disabled="isLocked || form.imza_kullanici_ids.length >= 3"
            @click="openSignerPicker"
          >
            <Plus :size="15" />
            <span>Yetkili Ekle</span>
          </button>
        </div>

        <div class="giden-fields-stack">
          <!-- Seçili Yetkili Yok Uyarısı -->
          <div v-if="selectedSignersList.length === 0" class="giden-empty-signers">
            <AlertCircle :size="18" class="text-amber" />
            <span>Resmî yazının onaylanabilmesi için en az bir imza yetkilisi seçilmelidir.</span>
          </div>

          <!-- Seçili Yetkililer Sıralı Listesi -->
          <div v-else class="giden-signers-list">
            <div
              v-for="(signer, idx) in selectedSignersList"
              :key="signer.encId"
              class="giden-signer-item"
            >
              <div class="giden-signer-main">
                <div class="evrak-signer-order">{{ idx + 1 }}</div>
                <div class="giden-signer-info">
                  <strong>{{ signer.name }}</strong>
                  <small>{{ signer.title || 'Yetkili' }}</small>
                </div>

                <div v-if="!isLocked" class="giden-signer-actions">
                  <button
                    type="button"
                    class="clay-circle-btn giden-order-icon-btn"
                    :disabled="idx === 0"
                    title="Yukarı Taşı"
                    @click="moveSigner(idx, -1)"
                  >
                    <ArrowUp :size="14" />
                  </button>
                  <button
                    type="button"
                    class="clay-circle-btn giden-order-icon-btn"
                    :disabled="idx === selectedSignersList.length - 1"
                    title="Aşağı Taşı"
                    @click="moveSigner(idx, 1)"
                  >
                    <ArrowDown :size="14" />
                  </button>
                  <button
                    type="button"
                    class="clay-circle-btn giden-delete-icon-btn"
                    title="Kaldır"
                    @click="toggleSigner(signer.encId)"
                  >
                    <Trash2 :size="14" />
                  </button>
                </div>
              </div>

              <!-- Kimin Adına Bilgisi -->
              <div v-if="!isLocked" class="giden-kimin-adina-box">
                <input
                  type="text"
                  :value="form.imza_kimin_adina[idx] || ''"
                  placeholder="Kimin adına? (Örn: Şirket Müdürü Adına - Opsiyonel)"
                  class="evrak-input giden-input--subtle"
                  @input="updateKiminAdina(idx, $event.target.value)"
                />
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- 2. İlişkili Evrak ve İlgi / Ek Metni -->
      <section class="bento-card giden-section-card">
        <div class="giden-card-head">
          <div class="giden-card-head__icon bg-primary-soft text-primary">
            <Paperclip :size="18" />
          </div>
          <div class="giden-card-head__text">
            <span class="giden-card-head__eyebrow">YAZIŞMA BAĞLANTILARI</span>
            <h2 class="giden-card-head__title">İlişkili Evrak, İlgi ve Ek Metinleri</h2>
          </div>
        </div>

        <div class="giden-fields-stack">
          <!-- İlişkili Gelen Evrak -->
          <div class="evrak-form-group">
            <label class="evrak-form-label">İLİŞKİLİ GELEN EVRAK</label>
            <select
              v-model="form.ilgili_evrak_id"
              :disabled="isLocked"
              class="evrak-select"
              @change="onIlgiliEvrakChange"
            >
              <option value="">İlişkili gelen evrak seçiniz...</option>
              <option v-for="g in meta.gelen_evraklar" :key="g.id" :value="g.id">
                {{ g.evrak_no }} — {{ g.konu }} ({{ g.tarih }})
              </option>
            </select>
          </div>

          <!-- İlgi Metni -->
          <div class="evrak-form-group">
            <label class="evrak-form-label">İLGİ METNİ (HER SATIRA BİR İLGİ)</label>
            <textarea
              v-model="form.ilgiler"
              rows="2"
              placeholder="Örn: 14.10.2026 tarihli ve 2026/894 sayılı müzekkereniz."
              :disabled="isLocked"
              class="evrak-textarea"
            ></textarea>
          </div>

          <!-- Ekler Metni -->
          <div class="evrak-form-group">
            <label class="evrak-form-label">EK METNİ (RESMÎ YAZI PDF'İNDE GÖRÜNECEK)</label>
            <input
              type="text"
              v-model="form.ekler"
              placeholder="Örn: 1 Adet Bordro Sureti, 1 Adet Kimlik Fotokopisi"
              :disabled="isLocked"
              class="evrak-input"
            />
          </div>
        </div>
      </section>

      <!-- 3. Fiziksel Ek Dosyalar (ISO 27001 AES-256-GCM) -->
      <section class="bento-card giden-section-card">
        <div class="giden-card-head">
          <div class="giden-card-head__icon bg-primary-soft text-primary">
            <Upload :size="18" />
          </div>
          <div class="giden-card-head__text">
            <span class="giden-card-head__eyebrow">DOKÜMAN YÖNETİMİ</span>
            <h2 class="giden-card-head__title">Fiziksel Ek Belgeler (ISO 27001)</h2>
          </div>
        </div>

        <div class="giden-fields-stack">
          <!-- Dosya Yükleme Dropzone -->
          <div v-if="!isLocked" class="evrak-form-group">
            <input
              type="file"
              id="gidenFileInput"
              multiple
              accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx"
              class="d-none"
              @change="handleFileChange"
            />
            <label for="gidenFileInput" class="evrak-upload-dropzone">
              <Upload :size="24" class="text-primary mb-1" />
              <span>Ek Belge Yüklemek İçin Dokunun</span>
              <small>PDF, Görsel ve Ofis belgeleri (ISO 27001 gereği AES-256 şifrelenir)</small>
            </label>
          </div>

          <!-- Yeni Seçilen Dosyalar -->
          <div v-if="newFiles.length > 0" class="evrak-existing-files-list">
            <span class="evrak-form-label w-100">YÜKLENECEK DOSYALAR</span>
            <div v-for="(f, idx) in newFiles" :key="idx" class="evrak-file-chip evrak-file-chip--new">
              <Paperclip :size="12" />
              <span class="text-truncate">{{ f.name }}</span>
              <button type="button" class="evrak-chip-del" @click="removeNewFile(idx)">
                <X :size="13" />
              </button>
            </div>
          </div>

          <!-- Mevcut Kayıtlı Ekler -->
          <div v-if="existingAttachments.length > 0" class="evrak-files-grid">
            <span class="evrak-form-label">KAYITLI EKLER ({{ existingAttachments.length }})</span>
            <div v-for="att in existingAttachments" :key="att.id" class="evrak-file-tile">
              <div class="evrak-file-icon">
                <FileText :size="18" class="text-primary" />
              </div>
              <div class="evrak-file-meta">
                <strong>{{ att.name }}</strong>
                <small>{{ (att.size / 1024).toFixed(1) }} KB</small>
              </div>
              <a :href="att.url + '&download=1'" class="clay-circle-btn evrak-file-dl" title="İndir">
                <Download :size="15" />
              </a>
              <button
                v-if="!isLocked"
                type="button"
                class="clay-circle-btn evrak-file-dl text-danger"
                title="Sil"
                @click="removeExistingAttachment(att.id)"
              >
                <Trash2 :size="15" />
              </button>
            </div>
          </div>
        </div>
      </section>

      <!-- 4. E-İmza Süreç Durumu ve Aksiyonları (Onaya Sunulmuşsa) -->
      <section v-if="isLocked && approvalSteps.length > 0" class="bento-card giden-section-card">
        <div class="giden-card-head">
          <div class="giden-card-head__icon bg-primary-soft text-primary">
            <PenTool :size="18" />
          </div>
          <div class="giden-card-head__text">
            <span class="giden-card-head__eyebrow">ONAY AŞAMALARI</span>
            <h2 class="giden-card-head__title">Elektronik İmza Akışı</h2>
          </div>
        </div>

        <div class="evrak-stepper">
          <div
            v-for="(st, idx) in approvalSteps"
            :key="idx"
            class="evrak-stepper-item"
            :class="{ 'is-approved': st.durum === 'onaylandi', 'is-current': st.is_current }"
          >
            <div class="evrak-stepper-badge">
              <Check v-if="st.durum === 'onaylandi'" :size="14" />
              <span v-else>{{ idx + 1 }}</span>
            </div>
            <div class="evrak-stepper-info">
              <strong>{{ st.user_name }}</strong>
              <small>{{ st.durum_text || (st.durum === 'onaylandi' ? 'İmzaladı' : 'Bekliyor') }}</small>
            </div>
          </div>
        </div>

        <!-- İmzacı Aksiyon Butonları -->
        <div v-if="siraBende" class="giden-approval-actions mt-3">
          <button
            type="button"
            class="evrak-btn evrak-btn--emerald evrak-btn--lg"
            :disabled="actionLoading"
            @click="imzaOnayla"
          >
            <CheckCircle2 :size="18" />
            <span>Elektronik Olarak İmzala</span>
          </button>
          <button
            type="button"
            class="evrak-btn evrak-btn--danger evrak-btn--lg"
            :disabled="actionLoading"
            @click="promptIade"
          >
            <Undo2 :size="18" />
            <span>Düzeltmeye İade Et</span>
          </button>
        </div>

        <!-- Taslağa Geri Al Butonu -->
        <div v-else-if="canRevoke" class="mt-3">
          <button
            type="button"
            class="evrak-btn evrak-btn--outline w-100"
            :disabled="actionLoading"
            @click="imzaGeriAl"
          >
            <RotateCcw :size="16" />
            <span>Evrakı Taslağa Geri Çek</span>
          </button>
        </div>
      </section>

      <!-- Kart Altı Adım & Form Tamamlama Aksiyonları -->
      <section class="bento-card giden-section-card">
        <div class="giden-form-actions">
          <button type="button" class="evrak-btn evrak-btn--outline" @click="activeTab = 'icerik'">
            <ArrowLeft :size="16" />
            <span>Geri: İçerik</span>
          </button>

          <button v-if="form.id" type="button" class="evrak-btn evrak-btn--secondary" @click="previewPdf">
            <Eye :size="16" />
            <span>PDF Önizle</span>
          </button>

          <button
            v-if="!isLocked && form.id && form.imza_kullanici_ids.length > 0"
            type="button"
            class="evrak-btn evrak-btn--emerald"
            :disabled="submittingApproval"
            @click="onayaSun"
          >
            <Send :size="16" />
            <span>{{ submittingApproval ? 'Sunuluyor...' : 'Onaya Sun' }}</span>
          </button>

          <button
            type="button"
            class="evrak-btn evrak-btn--primary"
            :disabled="saving"
            @click="submitForm(false)"
          >
            <Save :size="16" />
            <span>{{ saving ? 'Kaydediliyor...' : 'Kaydet' }}</span>
          </button>
        </div>
      </section>
    </div>

    <!-- ==================================================== -->
    <!-- MODAL 1: İMZA YETKİLİSİ SEÇİM MODALI -->
    <!-- ==================================================== -->
    <div v-if="showSignerModal" class="evrak-modal-overlay" @click.self="showSignerModal = false">
      <div class="evrak-modal-sheet">
        <div class="evrak-modal-header">
          <div>
            <span class="evrak-modal-eyebrow">ELEKTRONİK İMZA</span>
            <h2>İmza Yetkilisi Seçimi</h2>
          </div>
          <button type="button" class="clay-circle-btn" @click="showSignerModal = false">
            <X :size="18" />
          </button>
        </div>

        <div class="evrak-modal-body">
          <p class="giden-modal-hint">Resmî yazı için en fazla 3 imza yetkilisi seçebilirsiniz. Sıralamayı liste üzerinden oklarla düzenleyebilirsiniz.</p>
          <div class="evrak-signers-selector">
            <button
              v-for="user in meta.signing_users"
              :key="user.id"
              type="button"
              class="evrak-signer-pill"
              :class="{ 'is-selected': isSignerSelected(user.id) }"
              @click="toggleSigner(user.id)"
            >
              <div class="evrak-signer-order">
                <Check v-if="isSignerSelected(user.id)" :size="14" />
                <User v-else :size="14" />
              </div>
              <div class="flex-grow-1 text-left">
                <strong>{{ user.name }}</strong>
                <small class="d-block text-muted">{{ user.title || 'Yetkili' }}</small>
              </div>
            </button>
          </div>
        </div>

        <div class="evrak-modal-footer">
          <button type="button" class="evrak-btn evrak-btn--primary w-100" @click="showSignerModal = false">
            Tamam ({{ form.imza_kullanici_ids.length }} Seçildi)
          </button>
        </div>
      </div>
    </div>

    <!-- ==================================================== -->
    <!-- MODAL 2: İCRA ÜST YAZISI SİHİRBAZI MODALI -->
    <!-- ==================================================== -->
    <div v-if="showIcraModal" class="evrak-modal-overlay" @click.self="showIcraModal = false">
      <div class="evrak-modal-sheet">
        <div class="evrak-modal-header">
          <div>
            <span class="evrak-modal-eyebrow">OTOMATİK DOLDURMA</span>
            <h2>İcra Üst Yazısı Sihirbazı</h2>
          </div>
          <button type="button" class="clay-circle-btn" @click="showIcraModal = false">
            <X :size="18" />
          </button>
        </div>

        <div class="evrak-modal-body">
          <div class="evrak-form-group">
            <label class="evrak-form-label">PERSONEL SEÇİNİZ</label>
            <select v-model="icraPersonelId" class="evrak-select" @change="onIcraPersonelSelect">
              <option value="">İcra dosyası olan personeli seçiniz...</option>
              <option v-for="p in (meta.icra_personeller || meta.personeller)" :key="p.id" :value="p.id">
                {{ p.name }}
              </option>
            </select>
          </div>

          <div v-if="icraDosyalari.length > 0" class="evrak-form-group mt-3">
            <label class="evrak-form-label">İCRA DOSYASI SEÇİNİZ</label>
            <select v-model="selectedIcraId" class="evrak-select">
              <option v-for="dosya in icraDosyalari" :key="dosya.id" :value="dosya.id">
                {{ dosya.icra_dairesi }} — Esas: {{ dosya.dosya_no }} (Kalan: {{ dosya.kalan_borc }} ₺)
              </option>
            </select>
          </div>

          <div v-else-if="icraPersonelId && !icraLoading" class="text-center p-3 text-muted font-size-13">
            Bu personelin aktif icra dosyası bulunamadı.
          </div>
        </div>

        <div class="evrak-modal-footer">
          <button
            type="button"
            class="evrak-btn evrak-btn--emerald w-100"
            :disabled="!selectedIcraId || icraLoading"
            @click="createIcraUstYazi"
          >
            {{ icraLoading ? 'Taslak Aktarılıyor...' : 'Taslağı Evraka Aktar' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ==================================================== -->
    <!-- MODAL 3: YAPAY ZEKÂ TASLAK ASİSTANI MODALI -->
    <!-- ==================================================== -->
    <div v-if="showAiTaslakModal" class="evrak-modal-overlay" @click.self="showAiTaslakModal = false">
      <div class="evrak-modal-sheet">
        <div class="evrak-modal-header">
          <div>
            <span class="evrak-modal-eyebrow">YAPAY ZEKÂ MOTORU</span>
            <h2>AI ile Evrak Taslağı Üret</h2>
          </div>
          <button type="button" class="clay-circle-btn" @click="showAiTaslakModal = false">
            <X :size="18" />
          </button>
        </div>

        <div class="evrak-modal-body">
          <div class="evrak-form-group">
            <label class="evrak-form-label">NASIL BİR RESMÎ YAZI İSTİYORSUNUZ?</label>
            <textarea
              v-model="aiTalimat"
              rows="4"
              placeholder="Örn: Kadıköy 2. İcra Dairesine personelin maaşından kesinti yapılmaya başlandığını bildiren resmî cevabı hazırla..."
              class="evrak-textarea"
            ></textarea>
          </div>

          <div class="evrak-form-group mt-3">
            <label class="evrak-form-label">GELEN EVRAK FOTOĞRAFI VEYA BELGE (OPSİYONEL)</label>
            <input
              type="file"
              accept="image/*,application/pdf"
              class="evrak-input"
              @change="aiFile = $event.target.files[0]"
            />
            <small class="giden-field-note">Gelen yazının fotoğrafını yüklerseniz yapay zekâ kurum ve dosya bilgilerini otomatik çıkarır.</small>
          </div>
        </div>

        <div class="evrak-modal-footer">
          <button
            type="button"
            class="evrak-btn evrak-btn--primary w-100"
            :disabled="!aiTalimat.trim() || aiLoading"
            @click="generateAiTaslak"
          >
            <Sparkles :size="16" />
            <span>{{ aiLoading ? 'Taslak Üretiliyor...' : 'Yapay Zekâ ile Taslak Oluştur' }}</span>
          </button>
        </div>
      </div>
    </div>

    <!-- ==================================================== -->
    <!-- MODAL 4: AI METİN DÜZENLEME MODALI -->
    <!-- ==================================================== -->
    <div v-if="showAiSecimModal" class="evrak-modal-overlay" @click.self="showAiSecimModal = false">
      <div class="evrak-modal-sheet">
        <div class="evrak-modal-header">
          <div>
            <span class="evrak-modal-eyebrow">METİN REVİZYONU</span>
            <h2>Yapay Zekâ ile Metni Düzenle</h2>
          </div>
          <button type="button" class="clay-circle-btn" @click="showAiSecimModal = false">
            <X :size="18" />
          </button>
        </div>

        <div class="evrak-modal-body">
          <div class="evrak-preset-chips mb-3">
            <button
              type="button"
              class="evrak-preset-chip"
              @click="applyAiTransform('Resmî yazışma diline ve nezaket kurallarına uygun hale getir')"
            >
              Resmî Dili Güçlendir
            </button>
            <button
              type="button"
              class="evrak-preset-chip"
              @click="applyAiTransform('Metni özetle ve daha özlü hale getir')"
            >
              Daha Kısa / Özlü Yap
            </button>
            <button
              type="button"
              class="evrak-preset-chip"
              @click="applyAiTransform('İmla ve yazım hatalarını düzelt')"
            >
              İmla ve Yazımı Düzelt
            </button>
          </div>

          <div class="evrak-form-group">
            <label class="evrak-form-label">VEYA ÖZEL TALİMATINIZI YAZINIZ</label>
            <textarea
              v-model="aiSecimTalimat"
              rows="3"
              placeholder="Örn: Haciz kararının kaldırıldığını belirten paragrafı ekle..."
              class="evrak-textarea"
            ></textarea>
          </div>
        </div>

        <div class="evrak-modal-footer">
          <button
            type="button"
            class="evrak-btn evrak-btn--primary w-100"
            :disabled="!aiSecimTalimat.trim() || aiSecimLoading"
            @click="applyAiTransform()"
          >
            <Sparkles :size="16" />
            <span>{{ aiSecimLoading ? 'Düzenleniyor...' : 'Metni Güncelle' }}</span>
          </button>
        </div>
      </div>
    </div>

    <!-- ==================================================== -->
    <!-- MODAL 5: PDF ÖNİZLEME MODALI -->
    <!-- ==================================================== -->
    <div v-if="showPdfModal" class="evrak-modal-overlay" @click.self="showPdfModal = false">
      <div class="evrak-modal-sheet giden-pdf-sheet">
        <div class="evrak-modal-header">
          <div>
            <span class="evrak-modal-eyebrow">RESMÎ PDF ÇIKTISI</span>
            <h2>Resmî Yazı Önizleme</h2>
          </div>
          <button type="button" class="clay-circle-btn" @click="showPdfModal = false">
            <X :size="18" />
          </button>
        </div>

        <div class="evrak-modal-body p-0 giden-iframe-wrap">
          <iframe :src="previewUrl" class="w-100 h-100 border-0"></iframe>
        </div>

        <div class="evrak-modal-footer">
          <a :href="previewUrl" target="_blank" class="evrak-btn evrak-btn--secondary flex-grow-1">
            Yeni Sekmede Aç
          </a>
          <button type="button" class="evrak-btn evrak-btn--primary flex-grow-1" @click="showPdfModal = false">
            Kapat
          </button>
        </div>
      </div>
    </div>

    <!-- ==================================================== -->
    <!-- MODAL 6: İADE GEREKÇESİ MODALI -->
    <!-- ==================================================== -->
    <div v-if="showIadeModal" class="evrak-modal-overlay" @click.self="showIadeModal = false">
      <div class="evrak-modal-sheet">
        <div class="evrak-modal-header">
          <div>
            <span class="evrak-modal-eyebrow">REVİZYON TALEBİ</span>
            <h2>Düzeltilmek Üzere İade Et</h2>
          </div>
          <button type="button" class="clay-circle-btn" @click="showIadeModal = false">
            <X :size="18" />
          </button>
        </div>

        <div class="evrak-modal-body">
          <p class="giden-modal-hint">Evrak imza sürecinden çıkarılacak, taslağa dönecek ve gerekçeniz hazırlayan personele iletilecektir.</p>
          <div class="evrak-form-group">
            <label class="evrak-form-label">İADE GEREKÇESİ *</label>
            <textarea
              v-model="iadeGerekcesi"
              rows="4"
              placeholder="En az 5 karakter olacak şekilde iade nedeninizi yazınız..."
              class="evrak-textarea"
            ></textarea>
          </div>
        </div>

        <div class="evrak-modal-footer">
          <button
            type="button"
            class="evrak-btn evrak-btn--danger w-100"
            :disabled="actionLoading"
            @click="confirmIade"
          >
            İadeyi Onayla
          </button>
        </div>
      </div>
    </div>

    <!-- ==================================================== -->
    <!-- MODAL 7: STANDART RESMÎ ŞABLONLAR MODALI -->
    <!-- ==================================================== -->
    <div v-if="showTemplateModal" class="evrak-modal-overlay" @click.self="showTemplateModal = false">
      <div class="evrak-modal-sheet giden-template-sheet">
        <div class="evrak-modal-header">
          <div>
            <span class="evrak-modal-eyebrow">RESMÎ YAZIŞMA KÜTÜPHANESİ</span>
            <h2>Hazır Resmî Şablonlar</h2>
          </div>
          <button type="button" class="clay-circle-btn" @click="showTemplateModal = false">
            <X :size="18" />
          </button>
        </div>

        <div class="evrak-modal-body">
          <p class="giden-modal-hint">
            Aşağıdaki standart şablonlardan birini seçerek resmî yazı gövdesini ve konusunu saniyeler içinde doldurabilirsiniz.
          </p>

          <div class="giden-template-list">
            <button
              v-for="tpl in builtinTemplates"
              :key="tpl.id"
              type="button"
              class="giden-template-card"
              @click="applyTemplate(tpl)"
            >
              <div class="giden-template-head">
                <div class="giden-template-icon bg-primary-soft text-primary">
                  <FileText :size="18" />
                </div>
                <div class="giden-template-title-wrap">
                  <strong class="giden-template-title">{{ tpl.title }}</strong>
                  <span class="giden-template-subject">{{ tpl.subject }}</span>
                </div>
              </div>
              <p class="giden-template-desc">{{ tpl.desc }}</p>
              <div class="giden-template-preview" v-html="tpl.content"></div>
              <div class="giden-template-footer">
                <span class="giden-template-apply-label">Bu Şablonu Kullan →</span>
              </div>
            </button>
          </div>
        </div>

        <div class="evrak-modal-footer">
          <button type="button" class="evrak-btn evrak-btn--outline w-100" @click="showTemplateModal = false">
            Vazgeç
          </button>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
/* ==========================================================================
   GİDEN EVRAK - EVRAKVIEW İLE BİREBİR UYUMLU BENTO & CLAY TASARIMI
   ========================================================================== */

.giden-view-screen {
  background: var(--ds-canvas);
}

/* 2'li Bento Sihirbaz Kartları */
.giden-wizards-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
  margin-bottom: 18px;
}

.giden-wizard-card {
  text-align: left;
  border: none;
  cursor: pointer;
  background: var(--dsx-bg-ffffff);
  border-radius: var(--ds-radius-card);
  box-shadow: var(--ds-shadow-bento);
  border: 1px solid var(--dsx-bd-ffffff-a090);
  padding: 16px;
  display: flex;
  align-items: center;
  gap: 12px;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.giden-wizard-card:active {
  transform: scale(0.98);
}

.giden-wizard-card:disabled {
  opacity: 0.6;
  pointer-events: none;
}

.giden-card-title {
  font-size: 15px;
  font-weight: 800;
  color: var(--ds-ink);
  line-height: 1.2;
  display: block;
}

/* Sekmeler (Clay Filter Pills) */
.giden-tabs {
  display: flex;
  gap: 8px;
  overflow-x: auto;
  padding-bottom: 6px;
  margin-bottom: 16px;
  -webkit-overflow-scrolling: touch;
}

.giden-tabs::-webkit-scrollbar {
  display: none;
}

.giden-tab-count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 18px;
  height: 18px;
  background: #0284c7;
  color: #fff;
  border-radius: 50%;
  font-size: 10px;
  font-weight: 800;
  margin-left: 2px;
}

/* Bento Kart ve Bölümler */
.giden-tab-body {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.giden-section-card {
  background: var(--dsx-bg-ffffff);
  border-radius: var(--ds-radius-card);
  box-shadow: var(--ds-shadow-bento);
  border: 1px solid var(--dsx-bd-ffffff-a090);
  padding: 20px;
}

.giden-card-head {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 18px;
  padding-bottom: 14px;
  border-bottom: 1px solid var(--ds-border-subtle);
}

.giden-card-head-left {
  display: flex;
  align-items: center;
  gap: 12px;
}

.giden-card-head__icon {
  width: 40px;
  height: 40px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.giden-card-head__text {
  min-width: 0;
}

.giden-card-head__eyebrow {
  display: block;
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.5px;
  color: var(--ds-muted);
}

.giden-card-head__title {
  margin: 0;
  font-size: 16px;
  font-weight: 800;
  color: var(--ds-ink);
  line-height: 1.25;
}

.giden-between {
  justify-content: space-between;
}

.giden-fields-stack {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

/* ==========================================================================
   EVRAK FORM ELEMANLARI, BUTONLAR VE IZGARA (EVRAKVIEW İLE TAM EŞLEŞEN)
   ========================================================================== */

.evrak-form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.evrak-form-grid-2 {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
}

@media (max-width: 480px) {
  .evrak-form-grid-2 {
    grid-template-columns: 1fr;
  }
}

.evrak-form-label {
  font-size: 11px;
  font-weight: 800;
  color: var(--dsx-fg-475569);
  letter-spacing: 0.4px;
}

.evrak-input,
.evrak-select,
.evrak-textarea {
  width: 100%;
  padding: 12px 14px;
  background: var(--dsx-bg-ffffff);
  border: 1px solid var(--dsx-bd-cbd5e1);
  border-radius: var(--ds-radius-sm, 14px);
  font: 500 14px var(--ds-font-main);
  color: var(--ds-ink);
  outline: none;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
  box-sizing: border-box;
}

.evrak-input:focus,
.evrak-select:focus,
.evrak-textarea:focus {
  border-color: #0284c7;
  box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.12);
}

.evrak-input:disabled,
.evrak-select:disabled,
.evrak-textarea:disabled {
  background: var(--dsx-bg-f1f5f9);
  color: var(--dsx-fg-94a3b8);
  cursor: not-allowed;
}

/* Hızlı Konu Çipleri */
.evrak-preset-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-bottom: 6px;
}

.evrak-preset-chip {
  padding: 6px 12px;
  border-radius: var(--ds-radius-pill, 9999px);
  font-size: 11px;
  font-weight: 700;
  border: 1px solid var(--dsx-bd-cbd5e1);
  background: var(--dsx-bg-ffffff);
  color: var(--dsx-fg-334155);
  cursor: pointer;
  transition: all 0.15s ease;
}

.evrak-preset-chip:active {
  transform: scale(0.96);
}

.evrak-preset-chip.is-selected {
  background: #0284c7;
  color: #ffffff;
  border-color: #0284c7;
  box-shadow: 0 2px 6px rgba(2, 132, 199, 0.25);
}

/* Butonlar */
.evrak-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 10px 16px;
  border-radius: var(--ds-radius-sm, 14px);
  font: 700 13px var(--ds-font-main);
  border: none;
  cursor: pointer;
  transition: all 0.15s ease;
  text-decoration: none;
  box-sizing: border-box;
}

.evrak-btn:active {
  transform: scale(0.97);
}

.evrak-btn:disabled {
  opacity: 0.6;
  pointer-events: none;
}

.evrak-btn--lg {
  height: 48px;
  font-size: 14px;
}

.evrak-btn--primary {
  background: var(--ds-primary, var(--dsx-bg-181c24));
  color: #ffffff;
}

.evrak-btn--secondary {
  background: var(--dsx-bg-e0f2fe);
  color: var(--dsx-fg-0369a1);
}

.evrak-btn--outline {
  background: transparent;
  border: 1px solid var(--dsx-bd-cbd5e1);
  color: var(--dsx-fg-475569);
}

.evrak-btn--emerald {
  background: #10b981;
  color: #ffffff;
}

.evrak-btn--amber {
  background: #f59e0b;
  color: #ffffff;
}

.evrak-btn--danger {
  background: #ef4444;
  color: #ffffff;
}

/* Toggle Switch Kartı */
.evrak-toggle-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: var(--dsx-bg-ffffff);
  border: 1px solid var(--ds-border-subtle, var(--dsx-bd-0f172a-a006));
  border-radius: var(--ds-radius-tile, 20px);
  padding: 14px 16px;
}

.evrak-toggle-card__text {
  display: flex;
  align-items: center;
  gap: 12px;
}

.evrak-toggle-card__text strong {
  display: block;
  font-size: 14px;
  color: var(--ds-ink);
}

.evrak-toggle-card__text small {
  display: block;
  font-size: 11px;
  color: var(--ds-muted);
}

.evrak-checkbox {
  width: 20px;
  height: 20px;
  accent-color: #0284c7;
  cursor: pointer;
}

/* Evrak Tipi Switch */
.evrak-type-switch {
  display: flex;
  background: var(--dsx-bg-e2e8f0);
  padding: 4px;
  border-radius: var(--ds-radius-tile, 20px);
  gap: 4px;
}

.evrak-switch-opt {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 10px;
  border-radius: var(--ds-radius-sm, 14px);
  font-size: 12px;
  font-weight: 800;
  border: none;
  background: transparent;
  color: var(--dsx-fg-475569);
  cursor: pointer;
  transition: all 0.2s ease;
}

.evrak-switch-opt.is-active {
  background: var(--dsx-bg-ffffff);
  color: var(--ds-ink);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

/* Dosya Yükleme Dropzone */
.evrak-upload-dropzone {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4px;
  padding: 20px 16px;
  border: 2px dashed var(--dsx-bd-cbd5e1);
  border-radius: var(--ds-radius-tile, 20px);
  background: var(--dsx-bg-ffffff);
  cursor: pointer;
  text-align: center;
  transition: border-color 0.15s ease, background 0.15s ease;
}

.evrak-upload-dropzone:hover {
  border-color: #0284c7;
  background: var(--dsx-bg-f0f9ff);
}

.evrak-upload-dropzone span {
  font-size: 13px;
  font-weight: 700;
  color: var(--dsx-fg-0284c7);
}

.evrak-upload-dropzone small {
  font-size: 11px;
  color: var(--ds-muted);
}

/* Dosya Çipleri ve Listeleri */
.evrak-existing-files-list {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.evrak-file-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  background: var(--dsx-bg-f1f5f9);
  border-radius: var(--ds-radius-pill, 9999px);
  font-size: 11px;
  font-weight: 600;
  color: var(--dsx-fg-334155);
  max-width: 240px;
}

.evrak-file-chip--new {
  background: var(--dsx-bg-ecfdf5);
  color: var(--dsx-fg-065f46);
}

.evrak-chip-del {
  background: none;
  border: none;
  color: var(--dsx-fg-94a3b8);
  cursor: pointer;
  display: flex;
  align-items: center;
  padding: 0;
}

.evrak-chip-del:hover {
  color: #ef4444;
}

.evrak-files-grid {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.evrak-file-tile {
  background: var(--dsx-bg-ffffff);
  border: 1px solid var(--ds-border-subtle, var(--dsx-bd-0f172a-a006));
  border-radius: var(--ds-radius-tile, 20px);
  padding: 10px 14px;
  display: flex;
  align-items: center;
  gap: 12px;
}

.evrak-file-icon {
  width: 38px;
  height: 38px;
  border-radius: 10px;
  background: var(--ds-surface-subtle, var(--dsx-bg-f1f5f9));
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.evrak-file-meta {
  flex: 1;
  min-width: 0;
}

.evrak-file-meta strong {
  display: block;
  font-size: 13px;
  color: var(--ds-ink);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.evrak-file-meta small {
  display: block;
  font-size: 11px;
  color: var(--ds-muted);
}

.evrak-file-dl {
  flex-shrink: 0;
  width: 34px !important;
  height: 34px !important;
}

/* E-İmza Stepper */
.evrak-stepper {
  background: var(--dsx-bg-ffffff);
  border: 1px solid var(--ds-border-subtle, var(--dsx-bd-0f172a-a006));
  border-radius: var(--ds-radius-tile, 20px);
  padding: 14px 16px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.evrak-stepper-item {
  display: flex;
  align-items: center;
  gap: 12px;
}

.evrak-stepper-badge {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: var(--dsx-bg-f1f5f9);
  color: var(--ds-muted);
  font-size: 12px;
  font-weight: 800;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.evrak-stepper-item.is-approved .evrak-stepper-badge {
  background: #10b981;
  color: #ffffff;
}

.evrak-stepper-item.is-current .evrak-stepper-badge {
  background: #f59e0b;
  color: #ffffff;
}

.evrak-stepper-info {
  flex: 1;
  font-size: 13px;
}

.evrak-stepper-info strong {
  display: block;
  color: var(--ds-ink);
}

.evrak-stepper-info small {
  display: block;
  font-size: 11px;
  color: var(--ds-muted);
}

/* İmza Yetkilileri Seçici */
.evrak-signers-selector {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.evrak-signer-pill {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  border-radius: var(--ds-radius-sm, 14px);
  background: var(--dsx-bg-ffffff);
  border: 1.5px solid var(--ds-border-subtle, var(--dsx-bd-e2e8f0));
  font-size: 13px;
  font-weight: 600;
  color: var(--dsx-fg-334155);
  cursor: pointer;
  text-align: left;
  transition: all 0.15s ease;
}

.evrak-signer-pill.is-selected {
  border-color: #0284c7;
  background: var(--dsx-bg-f0f9ff);
  color: var(--dsx-fg-0369a1);
}

.evrak-signer-order {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background: var(--dsx-bg-e2e8f0);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 11px;
  font-weight: 800;
  flex-shrink: 0;
}

.evrak-signer-pill.is-selected .evrak-signer-order {
  background: #0284c7;
  color: #ffffff;
}

.evrak-urgent-alert {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 16px;
  border-radius: var(--ds-radius-tile, 20px);
  background: var(--dsx-bg-fffbeb);
  border: 1px solid var(--dsx-bd-fde68a);
  color: var(--dsx-fg-92400e);
  margin-bottom: 16px;
}

.evrak-urgent-alert__icon {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  background: var(--dsx-bg-fef3c7);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.evrak-urgent-alert__content strong {
  display: block;
  font-size: 13px;
  font-weight: 800;
}

.evrak-urgent-alert__content p {
  margin: 2px 0 0;
  font-size: 11px;
}

.evrak-returned-notice {
  background: var(--dsx-bg-fef2f2);
  border: 1px solid var(--dsx-bd-fecaca);
  color: var(--dsx-fg-991b1b);
  padding: 14px 16px;
  border-radius: var(--ds-radius-tile, 20px);
  display: flex;
  gap: 12px;
  font-size: 13px;
}

.evrak-returned-notice strong {
  display: block;
  font-size: 13px;
  font-weight: 800;
}

.evrak-returned-notice p {
  margin: 3px 0 0;
  font-size: 12px;
}

.giden-card-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-top: 20px;
  padding-top: 16px;
  border-top: 1px solid var(--ds-border-subtle);
}

.giden-form-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  width: 100%;
}

.giden-form-actions .evrak-btn {
  flex: 1;
  min-width: 120px;
}

/* Başlık Aksiyonları & Butonlar */
.giden-head-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.giden-template-action-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 12px;
  border-radius: var(--ds-radius-pill);
  background: var(--dsx-bg-eff6ff);
  color: var(--dsx-fg-1d4ed8);
  font: 700 12px var(--ds-font-main);
  border: 1px solid rgba(59, 130, 246, 0.25);
  cursor: pointer;
  transition: all 0.15s ease;
}

.giden-template-action-btn:active {
  transform: scale(0.96);
}

.giden-template-action-btn:disabled {
  opacity: 0.5;
  pointer-events: none;
}

/* AI Aksiyon Butonu */
.giden-ai-action-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 12px;
  border-radius: var(--ds-radius-pill);
  background: var(--ds-amber-soft);
  color: var(--ds-amber-ink);
  font: 700 12px var(--ds-font-main);
  border: 1px solid rgba(245, 158, 11, 0.2);
  cursor: pointer;
  transition: all 0.15s ease;
}

.giden-ai-action-btn:active {
  transform: scale(0.96);
}

.giden-ai-action-btn:disabled {
  opacity: 0.5;
  pointer-events: none;
}

/* Topbar Kaydet Butonu */
.giden-save-btn {
  background: var(--ds-primary) !important;
  color: #ffffff !important;
}

.giden-save-btn.is-saving {
  opacity: 0.6;
}

/* Tipografi & Punto Seçimi */
.giden-typography-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

@media (max-width: 480px) {
  .giden-typography-grid {
    grid-template-columns: 1fr;
  }
}

.giden-punto-pills {
  display: flex;
  background: var(--dsx-bg-e2e8f0);
  padding: 4px;
  border-radius: var(--ds-radius-tile, 20px);
  gap: 3px;
}

.giden-punto-btn {
  flex: 1;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
  padding: 9px 4px;
  border-radius: var(--ds-radius-sm, 14px);
  font-size: 11px;
  font-weight: 800;
  border: none;
  background: transparent;
  color: var(--dsx-fg-475569);
  cursor: pointer;
  transition: all 0.2s ease;
}

.giden-punto-btn.is-active {
  background: var(--dsx-bg-ffffff);
  color: var(--ds-ink);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.giden-punto-tag {
  font-size: 9px;
  font-weight: 700;
  padding: 1px 4px;
  border-radius: 4px;
  background: var(--dsx-bg-e0f2fe);
  color: var(--dsx-fg-0369a1);
}

/* Zengin Metin Biçimlendirme Araç Çubuğu */
.giden-formatting-bar {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  padding: 8px 10px;
  background: var(--dsx-bg-f8fafc);
  border: 1px solid var(--ds-border-subtle, var(--dsx-bd-e2e8f0));
  border-radius: var(--ds-radius-tile, 16px);
}

.giden-tool-group {
  display: inline-flex;
  align-items: center;
  background: var(--dsx-bg-ffffff);
  border: 1px solid var(--ds-border-subtle, var(--dsx-bd-e2e8f0));
  border-radius: 10px;
  padding: 2px;
  gap: 2px;
}

.giden-tool-btn {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  border: none;
  background: transparent;
  color: var(--dsx-fg-475569);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.15s ease;
}

.giden-tool-btn:hover:not(:disabled) {
  background: var(--dsx-bg-f1f5f9);
  color: var(--ds-ink);
}

.giden-tool-btn:active:not(:disabled) {
  transform: scale(0.92);
}

.giden-tool-btn:disabled {
  opacity: 0.35;
  cursor: not-allowed;
}

.giden-tool-btn.is-highlight {
  background: var(--dsx-bg-eff6ff);
  color: var(--dsx-fg-1d4ed8);
  font-weight: 700;
}

.giden-source-toggle-btn {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 4px 10px;
  border-radius: var(--ds-radius-pill, 9999px);
  border: 1px solid var(--ds-border-subtle, var(--dsx-bd-e2e8f0));
  background: var(--dsx-bg-ffffff);
  color: var(--dsx-fg-64748b);
  font-size: 11px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s ease;
}

.giden-source-toggle-btn.is-active {
  background: var(--dsx-bg-0f172a);
  color: #ffffff;
  border-color: #0f172a;
}

/* Hazır Resmî İfadeler (Hızlı Çipler) */
.giden-quick-phrases {
  display: flex;
  flex-wrap: nowrap;
  overflow-x: auto;
  gap: 6px;
  padding-bottom: 4px;
  -webkit-overflow-scrolling: touch;
}

.giden-quick-phrases::-webkit-scrollbar {
  display: none;
}

.giden-phrase-chip {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 6px 12px;
  border-radius: var(--ds-radius-pill, 9999px);
  background: var(--dsx-bg-f1f5f9);
  border: 1px solid var(--dsx-bd-e2e8f0);
  color: var(--dsx-fg-334155);
  font-size: 11px;
  font-weight: 700;
  white-space: nowrap;
  cursor: pointer;
  transition: all 0.15s ease;
  flex-shrink: 0;
}

.giden-phrase-chip:hover:not(:disabled) {
  background: var(--dsx-bg-e0f2fe);
  border-color: #7dd3fc;
  color: var(--dsx-fg-0369a1);
}

.giden-phrase-chip:active:not(:disabled) {
  transform: scale(0.96);
}

.giden-phrase-chip:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

/* WYSIWYG Canlı Editör & HTML Kaynak */
.giden-rich-editor {
  min-height: 280px;
  max-height: 520px;
  overflow-y: auto;
  background: var(--dsx-bg-ffffff);
  border: 1.5px solid var(--ds-border-subtle, var(--dsx-bd-cbd5e1));
  border-radius: var(--ds-radius-tile, 16px);
  padding: 16px;
  font-size: 15px;
  line-height: 1.65;
  color: var(--dsx-fg-0f172a);
  outline: none;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
  text-align: justify;
  text-justify: inter-word;
}

.giden-rich-editor:focus {
  border-color: #0284c7;
  box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.15);
}

.giden-rich-editor:empty::before {
  content: attr(data-placeholder);
  color: var(--dsx-fg-94a3b8);
  pointer-events: none;
}

.giden-rich-editor p {
  margin: 0 0 10px 0;
}

.giden-rich-editor p:last-child {
  margin-bottom: 0;
}

.giden-rich-editor ul,
.giden-rich-editor ol {
  margin: 0 0 10px 0;
  padding-left: 24px;
}

.giden-rich-editor.font-serif {
  font-family: 'Times New Roman', Times, serif !important;
}

.giden-rich-editor.font-sans {
  font-family: Arial, Helvetica, sans-serif !important;
}

.giden-rich-editor.punto-10 {
  font-size: 13px !important;
}

.giden-rich-editor.punto-11 {
  font-size: 14px !important;
}

.giden-rich-editor.punto-12 {
  font-size: 15px !important;
}

.giden-rich-editor.punto-14 {
  font-size: 17px !important;
}

.giden-source-textarea {
  font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace !important;
  font-size: 13px !important;
  line-height: 1.5 !important;
  min-height: 280px;
  background: var(--dsx-bg-0f172a) !important;
  color: #f8fafc !important;
  border-color: #334155 !important;
}

.font-serif {
  font-family: 'Times New Roman', Times, serif !important;
}

.font-sans {
  font-family: Arial, Helvetica, sans-serif !important;
}

.giden-label-between {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.giden-field-note {
  display: block;
  font-size: 11px;
  color: var(--ds-muted);
  margin-top: 4px;
}

/* Şablon Modalı Kartları */
.giden-template-sheet {
  max-height: 85vh;
}

.giden-template-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.giden-template-card {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 14px 16px;
  border-radius: var(--ds-radius-tile, 18px);
  background: var(--dsx-bg-ffffff);
  border: 1.5px solid var(--ds-border-subtle, var(--dsx-bd-e2e8f0));
  text-align: left;
  cursor: pointer;
  transition: all 0.15s ease;
}

.giden-template-card:hover {
  border-color: #0284c7;
  background: var(--dsx-bg-f8fafc);
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(2, 132, 199, 0.08);
}

.giden-template-card:active {
  transform: scale(0.99);
}

.giden-template-head {
  display: flex;
  align-items: center;
  gap: 10px;
}

.giden-template-icon {
  width: 34px;
  height: 34px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.giden-template-title-wrap {
  min-width: 0;
}

.giden-template-title {
  display: block;
  font-size: 14px;
  font-weight: 800;
  color: var(--ds-ink);
}

.giden-template-subject {
  display: block;
  font-size: 11px;
  font-weight: 600;
  color: var(--ds-muted);
}

.giden-template-desc {
  margin: 0;
  font-size: 12px;
  color: var(--dsx-fg-475569);
  line-height: 1.4;
}

.giden-template-preview {
  padding: 10px 12px;
  background: var(--dsx-bg-f1f5f9);
  border-radius: 10px;
  font-size: 11px;
  color: var(--dsx-fg-64748b);
  line-height: 1.45;
  max-height: 60px;
  overflow: hidden;
  position: relative;
}

.giden-template-preview::after {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(to bottom, transparent 30%, var(--dsx-bg-f1f5f9) 100%);
}

.giden-template-footer {
  display: flex;
  justify-content: flex-end;
}

.giden-template-apply-label {
  font-size: 11px;
  font-weight: 800;
  color: var(--dsx-fg-0284c7);
}

/* İmza Yetkilileri Listesi */
.giden-empty-signers {
  padding: 16px;
  border-radius: var(--ds-radius-tile);
  background: var(--ds-amber-soft);
  border: 1px dashed rgba(245, 158, 11, 0.4);
  color: var(--ds-amber-ink);
  font-size: 13px;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 10px;
}

.giden-signers-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.giden-signer-item {
  background: var(--dsx-bg-f8fafc);
  border: 1px solid var(--ds-border-subtle);
  border-radius: var(--ds-radius-tile);
  padding: 12px 14px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.giden-signer-main {
  display: flex;
  align-items: center;
  gap: 10px;
}

.giden-signer-info {
  flex: 1;
  min-width: 0;
}

.giden-signer-info strong {
  display: block;
  font-size: 14px;
  color: var(--ds-ink);
}

.giden-signer-info small {
  display: block;
  font-size: 12px;
  color: var(--ds-muted);
}

.giden-signer-actions {
  display: flex;
  align-items: center;
  gap: 6px;
}

.giden-order-icon-btn,
.giden-delete-icon-btn {
  width: 32px !important;
  height: 32px !important;
}

.giden-delete-icon-btn {
  color: #ef4444 !important;
}

.giden-input--subtle {
  background: var(--dsx-bg-ffffff) !important;
  font-size: 12px !important;
  padding: 8px 12px !important;
}

.giden-mini-btn {
  padding: 6px 12px !important;
  font-size: 12px !important;
}

/* Onay ve İade Aksiyonları */
.giden-approval-actions {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

/* Modal Stilleri */
.evrak-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.5);
  backdrop-filter: blur(4px);
  z-index: 1000;
  display: flex;
  align-items: flex-end;
  justify-content: center;
}

.evrak-modal-sheet {
  width: 100%;
  max-width: 540px;
  background: var(--dsx-bg-ffffff);
  border-radius: 24px 24px 0 0;
  max-height: 85vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 -10px 30px rgba(0, 0, 0, 0.15);
  animation: slideUp 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}

.giden-pdf-sheet {
  height: 88vh;
  max-height: 88vh;
}

.giden-iframe-wrap {
  flex: 1;
  min-height: 0;
  background: var(--dsx-bg-e2e8f0);
}

.evrak-modal-header {
  padding: 16px 20px;
  border-bottom: 1px solid var(--ds-border-subtle);
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.evrak-modal-eyebrow {
  display: block;
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.5px;
  color: var(--ds-muted);
}

.evrak-modal-header h2 {
  margin: 0;
  font-size: 16px;
  font-weight: 800;
  color: var(--ds-ink);
}

.evrak-modal-body {
  padding: 18px 20px;
  overflow-y: auto;
  overscroll-behavior: contain;
}

.evrak-modal-footer {
  padding: 14px 20px calc(14px + var(--ds-safe-bottom));
  border-top: 1px solid var(--ds-border-subtle);
  display: flex;
  gap: 10px;
  background: var(--dsx-bg-ffffff);
}

.giden-modal-hint {
  font-size: 12px;
  color: var(--ds-muted);
  line-height: 1.45;
  margin-bottom: 14px;
}

/* Kilitli ve Toast Stilleri */
.giden-locked-alert {
  margin-bottom: 16px;
  background: var(--dsx-bg-fffbeb);
  border: 1px solid var(--dsx-bd-fde68a);
}

.evrak-toast {
  position: fixed;
  top: calc(var(--ds-safe-top) + 16px);
  left: 20px;
  right: 20px;
  z-index: 1100;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 16px;
  border-radius: var(--ds-radius-tile);
  background: var(--dsx-bg-1e293b);
  color: #fff;
  font-size: 13px;
  font-weight: 600;
  box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
}

.evrak-toast--success {
  background: #065f46;
}

.evrak-toast--error {
  background: #991b1b;
}

@keyframes slideUp {
  from {
    transform: translateY(100%);
  }
  to {
    transform: translateY(0);
  }
}
</style>
