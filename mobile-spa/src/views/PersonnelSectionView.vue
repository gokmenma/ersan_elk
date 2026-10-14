<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowLeft, BadgeDollarSign, BriefcaseBusiness, CalendarCheck, CalendarDays, ChevronRight, ExternalLink, FileText, Gavel, History, Mail, MapPin, PackageCheck, Phone, RefreshCw, ShieldCheck, UserRoundCheck } from '@lucide/vue'
import { personnelApi } from '../services/personnel.js'

const route=useRoute(),router=useRouter(),loading=ref(true),error=ref(''),person=ref(null),canEdit=ref(false)
const definitions={
  genel:{label:'Genel bilgiler',eyebrow:'ÖZLÜK & İLETİŞİM',description:'İletişim, kimlik ve uygulama giriş bilgileri',icon:UserRoundCheck,features:[['İletişim bilgileri','Telefon ve e-posta'],['Kullanıcı erişimi','Uygulama giriş bilgileri'],['Personel durumu','Aktif ve ayrılış bilgileri']]},
  calisma:{label:'Çalışma bilgileri',eyebrow:'ÇALIŞMA & EKİP',description:'Görev, ekip ve çalışma geçmişi',icon:BriefcaseBusiness,features:[['Görev ve departman','Organizasyon bilgileri'],['Ekip geçmişi','Ekip kodu atamaları'],['Saha ve araç','Çalışma tercihleri']]},
  izinler:{label:'İzin ve raporlar',eyebrow:'İZİN YÖNETİMİ',description:'Hak ediş, kullanılan izin ve rapor geçmişi',icon:CalendarDays,features:[['İzin hak edişi','Yıllık izin bakiyesi'],['İzin geçmişi','Onaylı ve bekleyen izinler'],['Yeni izin','Personel adına kayıt oluşturma']]},
  zimmetler:{label:'Zimmetler',eyebrow:'VARLIK YÖNETİMİ',description:'Araç, cihaz ve demirbaş teslimleri',icon:PackageCheck,features:[['Aktif zimmetler','Teslim edilmiş varlıklar'],['Zimmet geçmişi','İade ve teslim kayıtları'],['Yeni zimmet','Demirbaş teslim işlemi']]},
  finansal:{label:'Maaş ve finans',eyebrow:'FİNANS & HAKLAR',description:'Ücret, ek ödeme ve kesinti bilgileri',icon:BadgeDollarSign,features:[['Maaş bilgileri','Ücret ve ödeme ayarları'],['Ek ödemeler','Prim, kira ve yardımlar'],['Kesintiler','Dönemsel ve sürekli kesintiler']]},
  evraklar:{label:'Personel evrakları',eyebrow:'DİJİTAL ARŞİV',description:'Belgeler, dosyalar ve geçerlilik takibi',icon:FileText,features:[['Evrak listesi','Yüklenen personel belgeleri'],['Yeni evrak','Dosya ve belge yükleme'],['Geçerlilik','Süre ve durum takibi']]},
  icralar:{label:'İcra dosyaları',eyebrow:'YASAL KESİNTİLER',description:'Borç, sıra ve kesinti takibi',icon:Gavel,features:[['Aktif dosyalar','Devam eden icra kayıtları'],['Kesinti geçmişi','Dönemsel tahsilatlar'],['Yeni dosya','İcra kaydı oluşturma']]},
  puantaj:{label:'İş ve puantaj',eyebrow:'ÇALIŞMA KAYITLARI',description:'Puantaj, görev ve saha hareketleri',icon:CalendarCheck,features:[['Puantaj özeti','Çalışma ve eksik günler'],['İş geçmişi','Tamamlanan saha işleri'],['Dönem takibi','Aylık çalışma kayıtları']]},
}
const section=computed(()=>definitions[route.params.section]||null)
const legacyUrl=computed(()=>`/mobile/index.php?p=personel-duzenle&id=${encodeURIComponent(route.params.id)}&tab=${route.params.section}`)
function formatDate(value){if(!value)return'Belirtilmemiş';return new Intl.DateTimeFormat('tr-TR',{day:'2-digit',month:'long',year:'numeric'}).format(new Date(`${value}T00:00:00`))}
async function load(){if(!section.value){router.replace(`/personel/${route.params.id}`);return}loading.value=true;error.value='';try{const result=await personnelApi.detail(route.params.id);person.value=result.personnel;canEdit.value=Boolean(result.can_edit)}catch(reason){error.value=reason.message}finally{loading.value=false}}
watch(()=>route.params.section,load)
onMounted(load)
</script>

<template>
  <section class="ds-screen personnel-section-screen">
    <header class="personnel-section-topbar"><button type="button" aria-label="Personel detayına dön" @click="router.push(`/personel/${route.params.id}`)"><ArrowLeft :size="20" /></button><div><span>{{ section?.eyebrow }}</span><h1>{{ section?.label }}</h1></div><span class="section-icon"><component :is="section?.icon || ShieldCheck" :size="19" /></span></header>
    <div v-if="loading" class="ds-loading"><i></i><i></i><i></i></div>
    <div v-else-if="error" class="ds-state"><RefreshCw :size="22"/><strong>Bilgiler alınamadı</strong><p>{{ error }}</p><button @click="load">Tekrar dene</button></div>
    <template v-else-if="person && section">
      <section class="personnel-section-profile"><span><img v-if="person.avatar" :src="person.avatar" :alt="person.name" @error="person.avatar = null" /><template v-else>{{ person.initials }}</template></span><div><small>{{ person.active?'AKTİF PERSONEL':'AYRILMIŞ PERSONEL' }}</small><h2>{{ person.name }}</h2><p>{{ section.description }}</p></div></section>

      <section v-if="route.params.section==='genel'" class="section-native-card">
        <header><span>GÜNCEL BİLGİLER</span><small>Profil özeti</small></header>
        <a v-if="person.phone" :href="`tel:${person.phone}`"><Phone :size="18"/><div><small>Telefon</small><strong>{{ person.phone }}</strong></div></a>
        <a v-if="person.email" :href="`mailto:${person.email}`"><Mail :size="18"/><div><small>E-posta</small><strong>{{ person.email }}</strong></div></a>
        <div><UserRoundCheck :size="18"/><div><small>Durum</small><strong>{{ person.active?'Aktif personel':'Ayrılmış personel' }}</strong></div></div>
      </section>
      <section v-else-if="route.params.section==='calisma'" class="section-native-card">
        <header><span>ÇALIŞMA ÖZETİ</span><small>Güncel kayıt</small></header>
        <div><BriefcaseBusiness :size="18"/><div><small>Görev / Departman</small><strong>{{ [person.job,person.department].filter(Boolean).join(' · ')||'Belirtilmemiş' }}</strong></div></div>
        <div><MapPin :size="18"/><div><small>Ekip / Bölge</small><strong>{{ [person.team,person.region].filter(Boolean).join(' · ')||'Atanmamış' }}</strong></div></div>
        <div><History :size="18"/><div><small>İşe giriş</small><strong>{{ formatDate(person.start_date) }}</strong></div></div>
      </section>

      <section class="section-capabilities"><header><span>BU BÖLÜMDE</span><h2>Yapabileceğiniz işlemler</h2></header><article v-for="([title,meta],index) in section.features" :key="title"><b>{{ String(index+1).padStart(2,'0') }}</b><div><strong>{{ title }}</strong><small>{{ meta }}</small></div><ChevronRight :size="18"/></article></section>
      <a class="section-manage-button" :href="legacyUrl"><span><ExternalLink :size="18"/><b>{{ canEdit?'Tüm yönetim işlemlerini aç':'Kayıtları görüntüle' }}</b></span><small>Mevcut mobil modülde kayıpsız devam et</small></a>
    </template>
  </section>
</template>

<style scoped>
.personnel-section-screen {
  min-height: 100svh;
  padding-bottom: calc(24px + env(safe-area-inset-bottom));
  background: var(--ds-canvas, var(--ds-canvas));
  font-family: var(--font-base, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif);
}
.personnel-section-topbar {
  display: grid;
  grid-template-columns: 44px 1fr 44px;
  align-items: center;
  gap: 12px;
  margin-bottom: 20px;
}
.personnel-section-topbar > button, .section-icon {
  display: grid;
  width: 44px;
  height: 44px;
  place-items: center;
  border: 1px solid rgba(255,255,255,.9);
  border-radius: 50%;
  color: var(--ds-ink);
  background: var(--ds-surface);
  box-shadow: var(--ds-shadow-bento, 0 10px 24px rgba(16,24,40,.08));
}
.personnel-section-topbar > div span {
  color: var(--ds-muted);
  font-size: 11px;
  font-weight: 700;
  letter-spacing: .1em;
  text-transform: uppercase;
}
.personnel-section-topbar h1 {
  margin-top: 2px;
  font-size: 18px;
  font-weight: 700;
  color: var(--ds-ink);
  line-height: 24px;
  letter-spacing: -.03em;
}
.personnel-section-profile {
  display: flex;
  padding: 18px;
  align-items: center;
  gap: 14px;
  border: 1px solid rgba(255,255,255,.9);
  border-radius: var(--ds-radius-card, 28px);
  background: var(--ds-surface);
  box-shadow: var(--ds-shadow-bento, 0 10px 24px rgba(16,24,40,.08));
}
.personnel-section-profile > span {
  display: grid;
  width: 52px;
  height: 52px;
  flex: 0 0 auto;
  place-items: center;
  border-radius: 18px;
  color: var(--ds-surface);
  background: var(--ds-surface-dark, var(--ds-surface-dark));
  font-size: 16px;
  font-weight: 700;
  overflow: hidden;
}
.personnel-section-profile > span img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.personnel-section-profile div { min-width: 0; }
.personnel-section-profile small {
  display: inline-block;
  padding: 3px 8px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 700;
  background: var(--ds-surface-subtle);
  color: #475569;
}
.personnel-section-profile h2 {
  margin-top: 4px;
  overflow: hidden;
  font-size: 17px;
  font-weight: 700;
  color: var(--ds-ink);
  text-overflow: ellipsis;
  white-space: nowrap;
}
.personnel-section-profile p {
  margin-top: 3px;
  color: var(--ds-muted);
  font-size: 12px;
}
.section-native-card {
  margin-top: 14px;
  padding: 18px;
  border: 1px solid rgba(255,255,255,.9);
  border-radius: var(--ds-radius-card, 28px);
  background: var(--ds-surface);
  box-shadow: var(--ds-shadow-bento, 0 10px 24px rgba(16,24,40,.08));
}
.section-native-card header {
  display: flex;
  margin-bottom: 10px;
  justify-content: space-between;
}
.section-native-card header span, .section-capabilities header span {
  color: var(--ds-muted);
  font-size: 11px;
  font-weight: 700;
  letter-spacing: .1em;
  text-transform: uppercase;
}
.section-native-card header small {
  color: var(--ds-muted-light);
  font-size: 11px;
}
.section-native-card > a, .section-native-card > div {
  display: grid;
  min-height: 52px;
  padding: 8px 0;
  grid-template-columns: 40px 1fr;
  align-items: center;
  gap: 12px;
  border-bottom: 1px solid rgba(0,0,0,.05);
  color: var(--ds-ink);
  text-decoration: none;
}
.section-native-card > :last-child { border-bottom: 0; }
.section-native-card > a > svg, .section-native-card > div > svg {
  padding: 8px;
  width: 36px;
  height: 36px;
  border-radius: 12px;
  color: var(--ds-ink);
  background: var(--ds-surface-subtle);
}
.section-native-card small, .section-native-card strong { display: block; }
.section-native-card div small { color: var(--ds-muted); font-size: 11px; }
.section-native-card div strong { margin-top: 2px; font-size: 13px; font-weight: 600; }
.section-capabilities { margin-top: 24px; }
.section-capabilities header { margin: 0 4px 12px; }
.section-capabilities h2 { margin-top: 4px; font-size: 18px; font-weight: 700; color: var(--ds-ink); letter-spacing: -.025em; }
.section-capabilities article {
  display: grid;
  min-height: 60px;
  padding: 12px 16px;
  grid-template-columns: 30px 1fr 18px;
  align-items: center;
  gap: 12px;
  border: 1px solid rgba(255,255,255,.9);
  border-bottom-color: rgba(0,0,0,.04);
  background: var(--ds-surface);
}
.section-capabilities article:first-of-type { border-radius: 20px 20px 0 0; }
.section-capabilities article:last-child { border-radius: 0 0 20px 20px; border-bottom-color: rgba(255,255,255,.9); }
.section-capabilities article > b { color: var(--ds-muted-light); font-size: 12px; font-weight: 700; }
.section-capabilities strong, .section-capabilities small { display: block; }
.section-capabilities strong { font-size: 13px; font-weight: 600; color: var(--ds-ink); }
.section-capabilities small { margin-top: 2px; color: var(--ds-muted); font-size: 11px; }
.section-manage-button {
  display: flex;
  margin-top: 16px;
  padding: 14px 18px;
  align-items: center;
  justify-content: space-between;
  border-radius: 20px;
  color: var(--ds-surface);
  background: var(--ds-surface-dark, var(--ds-surface-dark));
  box-shadow: 0 10px 24px rgba(25,29,36,.25);
  text-decoration: none;
}
.section-manage-button > span { display: flex; align-items: center; gap: 8px; }
.section-manage-button b { font-size: 13px; font-weight: 700; }
.section-manage-button small { color: rgba(255,255,255,.65); font-size: 11px; }
</style>
