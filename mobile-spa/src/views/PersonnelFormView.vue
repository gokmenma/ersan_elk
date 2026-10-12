<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { ArrowLeft, BadgeDollarSign, BriefcaseBusiness, CalendarCheck, CalendarDays, Check, ChevronRight, FileText, Gavel, History, Landmark, Mail, MapPin, PackageCheck, Phone, ReceiptText, Save, ShieldCheck, TrendingUp, UserRound, WalletCards } from '@lucide/vue'
import { useRoute, useRouter } from 'vue-router'
import { auth } from '../services/auth.js'
import { personnelApi } from '../services/personnel.js'

const route = useRoute(), router = useRouter()
const editing = computed(() => Boolean(route.params.id))
const loading = ref(editing.value), saving = ref(false), error = ref(''), departments = ref([]), activeArea = ref('profile')
const form = reactive({ id: route.params.id || '', name: '', initials: '', active: true, phone: '', email: '', job: '', department: '', region: '', start_date: new Date().toISOString().slice(0, 10), end_date: '' })
const groups = [
  { key: 'profile', label: 'Özlük & Profil', icon: UserRound },
  { key: 'finance', label: 'Finans & Haklar', icon: WalletCards },
  { key: 'operations', label: 'Kayıt & Operasyon', icon: BriefcaseBusiness },
]
const financeModules = [
  { label: 'Kesintiler', meta: 'Dönemsel ve sürekli kesintiler', icon: ReceiptText },
  { label: 'Ek ödemeler', meta: 'Prim, kira ve diğer ödemeler', icon: BadgeDollarSign },
  { label: 'İcralar', meta: 'İcra dosyaları ve kesintiler', icon: Gavel },
  { label: 'Vergi matrahları', meta: 'Dönemsel matrah geçmişi', icon: TrendingUp },
  { label: 'Hesap hareketleri', meta: 'Avans ve bordro hareketleri', icon: Landmark },
]
const operationModules = [
  { label: 'İş takip', meta: 'Puantaj ve çalışma kayıtları', icon: CalendarCheck },
  { label: 'İzin / Rapor', meta: 'İzin, rapor ve eksik günler', icon: CalendarDays },
  { label: 'Zimmetler', meta: 'Araç, cihaz ve demirbaşlar', icon: PackageCheck },
  { label: 'Evraklar', meta: 'Personel belgeleri ve arşiv', icon: FileText },
  { label: 'Giriş logları', meta: 'Mobil oturum geçmişi', icon: History },
]

async function prepare() {
  loading.value = true
  try {
    const list = await personnelApi.list({ status: 'all', page: 1, limit: 10 })
    if (!list.can_edit) throw new Error('Personel düzenleme yetkiniz bulunmuyor.')
    departments.value = list.departments || []
    if (editing.value) {
      const result = await personnelApi.detail(route.params.id)
      if (!result.can_edit) throw new Error('Personel düzenleme yetkiniz bulunmuyor.')
      Object.assign(form, result.personnel)
    }
  } catch (reason) { error.value = reason.message } finally { loading.value = false }
}
async function save() {
  error.value = ''
  if (!form.name.trim()) { error.value = 'Ad soyad alanı zorunludur.'; return }
  if (!form.start_date) { error.value = 'İşe giriş tarihi zorunludur.'; return }
  saving.value = true
  try {
    const result = await personnelApi.save(form, auth.state.csrfToken)
    if (!editing.value && result.personnel?.id) await router.replace(`/personel/${result.personnel.id}/duzenle`)
    else await prepare()
  } catch (reason) { error.value = reason.message } finally { saving.value = false }
}
onMounted(prepare)
</script>

<template>
  <section class="personnel-workspace">
    <header class="workspace-header">
      <button aria-label="Personel listesine dön" @click="router.push('/personel')"><ArrowLeft :size="20" /></button>
      <div><span>{{ editing ? 'PERSONEL PROFİLİ' : 'YENİ PERSONEL' }}</span><h1>{{ form.name || 'Personel oluştur' }}</h1></div>
      <span class="workspace-security"><ShieldCheck :size="17" /></span>
    </header>
    <main v-if="loading" class="form-loading">Personel bilgileri yükleniyor…</main>
    <main v-else>
      <section v-if="editing" class="workspace-profile-card">
        <span class="workspace-avatar">{{ form.initials || form.name.split(' ').map(item => item[0]).slice(0, 2).join('') }}</span>
        <div><span :class="form.active ? 'status-active' : 'status-passive'">{{ form.active ? 'Aktif personel' : 'Ayrılmış personel' }}</span><h2>{{ form.name }}</h2><p>{{ [form.job, form.department].filter(Boolean).join(' · ') || 'Çalışma bilgisi tanımlanmamış' }}</p></div>
      </section>
      <nav v-if="editing" class="workspace-groups" aria-label="Personel işlem grupları">
        <button v-for="group in groups" :key="group.key" :class="{ active: activeArea === group.key }" @click="activeArea = group.key"><component :is="group.icon" :size="17" /><span>{{ group.label }}</span></button>
      </nav>
      <section v-if="activeArea === 'profile' || !editing" class="workspace-panel">
        <div class="workspace-title"><span>ÖZLÜK & ÇALIŞMA</span><h2>Personel bilgileri</h2><p>Temel iletişim ve çalışma bilgilerini tek ekrandan yönetin.</p></div>
        <div v-if="error" class="form-alert" role="alert">{{ error }}</div>
        <div class="mobile-form-fields workspace-fields">
          <label><span>Ad soyad *</span><div><UserRound :size="18" /><input v-model.trim="form.name" autocomplete="name" placeholder="Ad ve soyad" /></div></label>
          <label><span>Telefon</span><div><Phone :size="18" /><input v-model.trim="form.phone" type="tel" inputmode="tel" placeholder="05xx xxx xx xx" /></div></label>
          <label><span>E-posta</span><div><Mail :size="18" /><input v-model.trim="form.email" type="email" inputmode="email" placeholder="ornek@firma.com" /></div></label>
          <label><span>Görev</span><div><BriefcaseBusiness :size="18" /><input v-model.trim="form.job" placeholder="Görev bilgisi" /></div></label>
          <label><span>Departman</span><div><BriefcaseBusiness :size="18" /><input v-model.trim="form.department" list="personnel-departments" placeholder="Departman" /><datalist id="personnel-departments"><option v-for="item in departments" :key="item" :value="item" /></datalist></div></label>
          <label><span>Ekip bölgesi</span><div><MapPin :size="18" /><input v-model.trim="form.region" placeholder="Ekip bölgesi" /></div></label>
          <div class="date-field-grid"><label><span>İşe giriş *</span><div><CalendarDays :size="17" /><input v-model="form.start_date" type="date" /></div></label><label><span>İşten çıkış</span><div><CalendarDays :size="17" /><input v-model="form.end_date" type="date" /></div></label></div>
        </div>
        <button class="workspace-save" :disabled="saving" @click="save"><Save v-if="!saving" :size="18" /><Check v-else :size="18" /> {{ saving ? 'Kaydediliyor…' : 'Değişiklikleri kaydet' }}</button>
      </section>
      <section v-else class="workspace-panel">
        <div class="workspace-title"><span>{{ activeArea === 'finance' ? 'FİNANS & HAKLAR' : 'KAYIT & OPERASYON' }}</span><h2>{{ activeArea === 'finance' ? 'Mali işlemler' : 'Personel işlemleri' }}</h2><p>İşlem yapmak istediğiniz bölümü seçin.</p></div>
        <div class="workspace-module-list">
          <button v-for="module in (activeArea === 'finance' ? financeModules : operationModules)" :key="module.label"><span><component :is="module.icon" :size="20" /></span><div><strong>{{ module.label }}</strong><small>{{ module.meta }}</small></div><ChevronRight :size="17" /></button>
        </div>
      </section>
    </main>
  </section>
</template>
