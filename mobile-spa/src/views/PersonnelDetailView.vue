<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowLeft, BadgeDollarSign, BriefcaseBusiness, CalendarCheck, CalendarDays, ChevronRight, FileText, Gavel, History, Mail, MapPin, PackageCheck, Pencil, Phone, RefreshCw, UserRoundCheck } from '@lucide/vue'
import { personnelApi } from '../services/personnel.js'

const route = useRoute()
const router = useRouter()
const loading = ref(true)
const error = ref('')
const person = ref(null)
const canEdit = ref(false)

const modules = [
  { tab: 'genel', label: 'Genel bilgiler', meta: 'İletişim, kimlik ve giriş bilgileri', icon: UserRoundCheck },
  { tab: 'calisma', label: 'Çalışma bilgileri', meta: 'Görev, ekip ve çalışma geçmişi', icon: BriefcaseBusiness },
  { tab: 'izinler', label: 'İzin ve raporlar', meta: 'Hak ediş ve izin geçmişi', icon: CalendarDays },
  { tab: 'zimmetler', label: 'Zimmetler', meta: 'Araç, cihaz ve demirbaşlar', icon: PackageCheck },
  { tab: 'finansal', label: 'Maaş ve finans', meta: 'Ücret, ek ödeme ve kesintiler', icon: BadgeDollarSign },
  { tab: 'evraklar', label: 'Personel evrakları', meta: 'Belgeler ve dijital arşiv', icon: FileText },
  { tab: 'icralar', label: 'İcra dosyaları', meta: 'Borç ve kesinti takibi', icon: Gavel },
  { tab: 'puantaj', label: 'İş ve puantaj', meta: 'Çalışma ve görev kayıtları', icon: CalendarCheck },
]
function phoneHref(value) { return `tel:${String(value || '').replace(/[^+\d]/g, '')}` }
function formatDate(value) { if (!value) return 'Belirtilmemiş'; return new Intl.DateTimeFormat('tr-TR',{day:'2-digit',month:'long',year:'numeric'}).format(new Date(`${value}T00:00:00`)) }
async function load() { loading.value=true; error.value=''; try { const result=await personnelApi.detail(route.params.id); person.value=result.personnel; canEdit.value=Boolean(result.can_edit) } catch(reason) { error.value=reason.message } finally { loading.value=false } }
onMounted(load)
</script>

<template>
  <section class="ds-screen personnel-detail-screen">
    <header class="personnel-detail-topbar">
      <button type="button" aria-label="Personel listesine dön" @click="router.push('/personel')"><ArrowLeft :size="20" /></button>
      <div><span>PERSONEL PROFİLİ</span><h1>Personel detayı</h1></div>
      <button v-if="canEdit && person" type="button" aria-label="Personeli düzenle" @click="router.push(`/personel/${route.params.id}/duzenle`)"><Pencil :size="18" /></button>
      <span v-else></span>
    </header>

    <div v-if="loading" class="ds-loading"><i></i><i></i><i></i></div>
    <div v-else-if="error" class="ds-state"><RefreshCw :size="22" /><strong>Personel alınamadı</strong><p>{{ error }}</p><button type="button" @click="load">Tekrar dene</button></div>
    <template v-else-if="person">
      <section class="personnel-detail-hero">
        <div class="personnel-detail-avatar"><img v-if="person.avatar" :src="person.avatar" :alt="person.name" /><template v-else>{{ person.initials }}</template></div>
        <div class="personnel-detail-identity"><span :class="person.active ? 'is-active' : 'is-passive'">{{ person.active ? 'AKTİF PERSONEL' : 'AYRILMIŞ PERSONEL' }}</span><h2>{{ person.name }}</h2><p>{{ [person.job,person.department].filter(Boolean).join(' · ') || 'Çalışma bilgisi tanımlanmamış' }}</p></div>
        <div class="personnel-detail-actions"><a v-if="person.phone" :href="phoneHref(person.phone)"><Phone :size="18" /><span>Ara</span></a><a v-if="person.email" :href="`mailto:${person.email}`"><Mail :size="18" /><span>E-posta</span></a></div>
      </section>

      <section class="personnel-detail-facts">
        <article><span><BriefcaseBusiness :size="18" /></span><small>Departman</small><strong>{{ person.department || 'Belirtilmemiş' }}</strong></article>
        <article><span><MapPin :size="18" /></span><small>Ekip / Bölge</small><strong>{{ [person.team,person.region].filter(Boolean).join(' · ') || 'Atanmamış' }}</strong></article>
        <article><span><History :size="18" /></span><small>İşe giriş</small><strong>{{ formatDate(person.start_date) }}</strong></article>
      </section>

      <section class="personnel-detail-modules">
        <header><div><span>PERSONEL İŞLEMLERİ</span><h2>Tüm bilgiler ve işlemler</h2></div><small>8 bölüm</small></header>
        <RouterLink v-for="module in modules" :key="module.tab" :to="`/personel/${encodeURIComponent(route.params.id)}/bolum/${module.tab}`"><span><component :is="module.icon" :size="20" /></span><div><strong>{{ module.label }}</strong><small>{{ module.meta }}</small></div><ChevronRight :size="18" /></RouterLink>
      </section>
    </template>
  </section>
</template>

<style scoped>
.personnel-detail-screen{font-family:'Plus Jakarta Sans',sans-serif}.personnel-detail-topbar{display:grid;grid-template-columns:42px 1fr 42px;align-items:center;gap:12px;margin-bottom:22px}.personnel-detail-topbar>button{display:grid;width:42px;height:42px;place-items:center;border:1px solid rgba(255,255,255,.9);border-radius:50%;color:#11161b;background:rgba(255,255,255,.76);box-shadow:var(--ds-shadow-card);backdrop-filter:blur(16px)}.personnel-detail-topbar>div span,.personnel-detail-modules>header span{color:#8c9bae;font:700 9px 'Space Grotesk',sans-serif;letter-spacing:.1em}.personnel-detail-topbar h1{margin-top:2px;font-size:18px;line-height:24px;letter-spacing:-.03em}.personnel-detail-hero{padding:20px;border:1px solid rgba(255,255,255,.9);border-radius:28px;background:linear-gradient(145deg,rgba(255,255,255,.8),rgba(213,228,249,.62));box-shadow:var(--ds-shadow-card);backdrop-filter:blur(16px)}.personnel-detail-avatar{display:grid;width:68px;height:68px;place-items:center;border-radius:22px;color:#fff;background:#11161b;font:600 19px 'Space Grotesk',sans-serif;box-shadow:0 12px 26px rgba(17,22,27,.18)}.personnel-detail-identity{margin-top:16px}.personnel-detail-identity>span{display:inline-flex;padding:5px 8px;border-radius:999px;font:700 8px 'Space Grotesk',sans-serif;letter-spacing:.08em}.personnel-detail-identity .is-active{color:#126b5d;background:#d9f2eb}.personnel-detail-identity .is-passive{color:#93000a;background:#ffdad6}.personnel-detail-identity h2{margin-top:8px;font-size:26px;line-height:32px;letter-spacing:-.04em}.personnel-detail-identity p{margin-top:3px;color:#516071;font-size:11px}.personnel-detail-actions{display:grid;margin-top:18px;grid-template-columns:repeat(2,1fr);gap:9px}.personnel-detail-actions a{display:flex;height:44px;align-items:center;justify-content:center;gap:7px;border-radius:999px;color:#fff;background:#11161b;font:600 10px 'Space Grotesk',sans-serif;text-decoration:none}.personnel-detail-facts{display:grid;margin-top:12px;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.personnel-detail-facts article{min-width:0;padding:14px;border:1px solid rgba(255,255,255,.9);border-radius:20px;background:rgba(255,255,255,.72);box-shadow:var(--ds-shadow-card);backdrop-filter:blur(16px)}.personnel-detail-facts article:last-child{grid-column:1/-1}.personnel-detail-facts article>span{display:grid;width:34px;height:34px;place-items:center;border-radius:12px;color:#fff;background:#516071}.personnel-detail-facts small,.personnel-detail-facts strong{display:block}.personnel-detail-facts small{margin-top:10px;color:#8c9bae;font:600 8px 'Space Grotesk',sans-serif;text-transform:uppercase;letter-spacing:.07em}.personnel-detail-facts strong{margin-top:3px;overflow:hidden;font-size:11px;line-height:16px;text-overflow:ellipsis}.personnel-detail-modules{margin-top:28px}.personnel-detail-modules>header{display:flex;margin:0 2px 13px;align-items:flex-end;justify-content:space-between}.personnel-detail-modules h2{margin-top:3px;font-size:18px;line-height:24px;letter-spacing:-.025em}.personnel-detail-modules>header>small{color:#8c9bae;font:500 9px 'Space Grotesk',sans-serif}.personnel-detail-modules>a{display:grid;min-height:72px;padding:13px 14px;grid-template-columns:42px 1fr 20px;align-items:center;gap:11px;border:1px solid rgba(255,255,255,.9);border-bottom-color:rgba(140,155,174,.12);color:#181c1e;background:rgba(255,255,255,.72);text-decoration:none;backdrop-filter:blur(16px)}.personnel-detail-modules>a:first-of-type{border-radius:22px 22px 0 0}.personnel-detail-modules>a:last-child{border-radius:0 0 22px 22px}.personnel-detail-modules>a>span{display:grid;width:42px;height:42px;place-items:center;border-radius:15px;color:#11161b;background:#f1f4f6}.personnel-detail-modules>a>div{min-width:0}.personnel-detail-modules strong,.personnel-detail-modules small{display:block}.personnel-detail-modules strong{font-size:12px}.personnel-detail-modules a small{margin-top:3px;overflow:hidden;color:#8c9bae;font-size:9px;text-overflow:ellipsis;white-space:nowrap}.personnel-detail-modules>a:nth-of-type(2) span{color:#3676d8}.personnel-detail-modules>a:nth-of-type(3) span{color:#2b9a8a}.personnel-detail-modules>a:nth-of-type(4) span{color:#d49a31}.personnel-detail-modules>a:nth-of-type(5) span{color:#6c63cf}.personnel-detail-modules>a:nth-of-type(6) span{color:#be5578}.personnel-detail-modules>a:nth-of-type(7) span{color:#df7049}
.personnel-detail-avatar{overflow:hidden}.personnel-detail-avatar img{width:100%;height:100%;object-fit:cover}
</style>
