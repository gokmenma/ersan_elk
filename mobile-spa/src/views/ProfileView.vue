<script setup>
import { Bell, ChevronRight, CircleHelp, LogOut, Moon, Palette, ShieldCheck, Smartphone } from '@lucide/vue'
import { useRouter } from 'vue-router'
import TopBar from '../components/TopBar.vue'
import { auth } from '../services/auth.js'

const router = useRouter()
async function logout() {
  await auth.logout()
  await router.replace('/giris')
}

const rows = [
  { label: 'Bildirim tercihleri', icon: Bell },
  { label: 'Görünüm ve tema', icon: Palette },
  { label: 'Güvenlik', icon: ShieldCheck },
  { label: 'Bağlı cihazlar', icon: Smartphone },
  { label: 'Yardım merkezi', icon: CircleHelp },
]
</script>

<template>
  <section class="screen">
    <TopBar eyebrow="HESABIM" title="Profil" subtitle="Hesap ve uygulama tercihleri" :search="false" />
    <div class="profile-card"><div class="profile-avatar">{{ auth.state.user?.initials }}</div><div><h2>{{ auth.state.user?.name }}</h2><p>{{ auth.state.company?.name }}</p><span>Aktif oturum</span></div></div>
    <div class="settings-card">
      <button v-for="row in rows" :key="row.label"><span><component :is="row.icon" :size="19" /></span><strong>{{ row.label }}</strong><ChevronRight :size="18" /></button>
      <button><span><Moon :size="19" /></span><strong>Koyu görünüm</strong><i class="toggle"></i></button>
    </div>
    <button class="logout-button" @click="logout"><LogOut :size="18" /> Güvenli çıkış</button>
    <p class="version">Ersan Mobile · v0.2</p>
  </section>
</template>
