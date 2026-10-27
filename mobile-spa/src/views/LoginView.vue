<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowRight, Eye, EyeOff, LockKeyhole, ShieldCheck, UserRound } from '@lucide/vue'
import { auth } from '../services/auth.js'

const router = useRouter()
const route = useRoute()
const username = ref('')
const password = ref('')
const remember = ref(true)
const showPassword = ref(false)
const busy = ref(false)
const error = ref(route.query.expired ? 'Oturumunuz sona erdi, lütfen tekrar giriş yapın.' : '')
const logoSpinning = ref(false)

function triggerLogoSpin() {
  if (logoSpinning.value) return
  logoSpinning.value = true
  setTimeout(() => {
    logoSpinning.value = false
  }, 850)
}

async function submit() {
  error.value = ''
  busy.value = true
  try {
    await auth.login({ username: username.value, password: password.value, remember: remember.value })
    await router.replace(auth.state.company ? '/' : '/firma-secimi')
  } catch (reason) {
    error.value = reason.message
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <section class="auth-screen">
    <div class="auth-visual">
      <div class="auth-top-bar">
        <span class="auth-status"><i></i> Sistem aktif</span>
      </div>

      <div class="auth-brand-content">
        <div
          class="auth-splash-stage"
          :class="{ 'is-spinning-3d': logoSpinning }"
          title="Ersan Elektrik 3D"
          role="button"
          tabindex="0"
          @click="triggerLogoSpin"
          @keydown.enter="triggerLogoSpin"
        >
          <div class="splash-glow"></div>
          <div class="splash-orbit"></div>
          <div class="splash-orbit splash-orbit--inner"></div>
          <div class="splash-logo-card-3d">
            <img src="/images/logo-3d-splash.png?v=3" alt="Er-San Elektrik" width="104" height="104" />
          </div>
        </div>

        <div class="auth-brand-titles">
          <h1 class="auth-brand-name">ER-SAN ELEKTRİK</h1>
          <p class="auth-brand-sub">Yönetim Paneli</p>
          <p class="auth-brand-slogan">İşin Sahada, Kontrol Sende</p>
        </div>
      </div>
    </div>

    <form class="auth-form" @submit.prevent="submit">
      <span class="sheet-handle" aria-hidden="true"></span>
      <div><span class="section-kicker">GÜVENLİ GİRİŞ</span><h2>Tekrar hoş geldiniz</h2><p>Hesabınıza kaldığınız yerden devam edin.</p></div>
      <div v-if="error" class="form-alert" role="alert">{{ error }}</div>
      <label class="auth-field">
        <span>Kullanıcı adı</span>
        <div><UserRound :size="18" /><input v-model.trim="username" autocomplete="username" autocapitalize="none" spellcheck="false" inputmode="text" required placeholder="Gerçek kullanıcı adınız" /></div>
      </label>
      <label class="auth-field">
        <span>Şifre</span>
        <div><LockKeyhole :size="18" /><input v-model="password" :type="showPassword ? 'text' : 'password'" autocomplete="current-password" required placeholder="Şifreniz" /><button type="button" :aria-label="showPassword ? 'Şifreyi gizle' : 'Şifreyi göster'" @click="showPassword = !showPassword"><EyeOff v-if="showPassword" :size="18" /><Eye v-else :size="18" /></button></div>
      </label>
      <div class="auth-options"><label><input v-model="remember" type="checkbox" /> <span>Beni hatırla</span></label><a href="https://ersanelektrik.com/reset-password.php">Şifremi unuttum</a></div>
      <button class="login-button" type="submit" :disabled="busy"><span>{{ busy ? 'Giriş yapılıyor…' : 'Giriş yap' }}</span><ArrowRight :size="19" /></button>
      <p class="auth-help"><ShieldCheck :size="13" /> Bilgileriniz güvenli bağlantıyla korunur.</p>
    </form>
  </section>
</template>
