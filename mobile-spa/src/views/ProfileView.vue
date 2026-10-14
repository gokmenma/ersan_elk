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
  <section class="ds-screen">
    <!-- 1. Standart TopBar -->
    <TopBar eyebrow="HESABIM" title="Profil" subtitle="Hesap ve uygulama tercihleri" :back="true" />

    <!-- 2. Kullanıcı Profil Bento Kartı -->
    <div class="bento-card bento-card--white profile-bento-card">
      <div class="profile-avatar-circle">
        <span>{{ auth.state.user?.initials || 'EE' }}</span>
      </div>
      <div class="profile-info">
        <span class="clay-badge clay-badge--emerald mb-1">Aktif Oturum</span>
        <h2>{{ auth.state.user?.name }}</h2>
        <p>{{ auth.state.company?.name }}</p>
      </div>
    </div>

    <!-- 3. Ayarlar Bento Kartı -->
    <div class="bento-card bento-card--white settings-bento-card">
      <button v-for="row in rows" :key="row.label" type="button" class="settings-row">
        <span class="settings-icon">
          <component :is="row.icon" :size="18" />
        </span>
        <strong>{{ row.label }}</strong>
        <ChevronRight :size="16" class="settings-arrow" />
      </button>
    </div>

    <!-- 4. Güvenli Çıkış Butonu -->
    <button type="button" class="clay-btn clay-btn--danger w-100 logout-btn" @click="logout">
      <LogOut :size="17" />
      <span>Güvenli Çıkış Yap</span>
    </button>
  </section>
</template>

<style scoped>
.profile-bento-card {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 18px 20px;
}

.profile-avatar-circle {
  display: grid;
  width: 58px;
  height: 58px;
  place-items: center;
  border-radius: 20px;
  background: var(--ds-primary);
  color: var(--ds-surface);
  font: 800 18px var(--ds-font-mono);
  box-shadow: var(--ds-shadow-pill);
  flex-shrink: 0;
}

.profile-info {
  flex: 1;
  min-width: 0;
}

.profile-info h2 {
  margin: 2px 0 0;
  font-size: 16px;
  font-weight: 800;
  letter-spacing: -0.02em;
  color: var(--ds-ink);
}

.profile-info p {
  margin: 3px 0 0;
  font-size: 11px;
  color: var(--ds-muted);
}

.settings-bento-card {
  padding: 6px 16px;
}

.settings-row {
  display: flex;
  width: 100%;
  align-items: center;
  gap: 12px;
  padding: 13px 0;
  border: 0;
  border-bottom: 1px solid var(--ds-border-subtle);
  background: transparent;
  cursor: pointer;
  text-align: left;
}

.settings-row:last-child {
  border-bottom: 0;
}

.settings-icon {
  display: grid;
  width: 36px;
  height: 36px;
  place-items: center;
  border-radius: 12px;
  background: var(--ds-surface-subtle);
  color: var(--ds-primary);
}

.settings-row strong {
  flex: 1;
  font-size: 13px;
  font-weight: 700;
  color: var(--ds-ink);
}

.settings-arrow {
  color: var(--ds-muted);
}

.logout-btn {
  height: 48px;
  border-radius: var(--ds-radius-tile);
  margin-top: 14px;
}
</style>
