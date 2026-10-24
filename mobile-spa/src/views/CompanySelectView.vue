<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { ArrowRight, Building2, LogOut } from '@lucide/vue'
import { auth } from '../services/auth.js'

const router = useRouter()
const busyId = ref('')
const error = ref('')

async function selectCompany(company) {
  busyId.value = company.id
  error.value = ''
  try {
    await auth.selectCompany(company.id)
    await router.replace('/')
  } catch (reason) {
    error.value = reason.message
  } finally {
    busyId.value = ''
  }
}

async function logout() {
  await auth.logout()
  await router.replace('/giris')
}
</script>

<template>
  <section class="company-screen">
    <header class="company-head">
      <div class="brand-mark small brand-mark--3d">
        <img src="/images/logo-3d.png?v=3" alt="Ersan Elektrik" class="brand-logo-3d" />
      </div>
      <button type="button" aria-label="Güvenli çıkış" @click="logout"><LogOut :size="18" /></button>
    </header>
    <div class="company-copy"><span class="section-kicker">ÇALIŞMA ALANI</span><h1>Merhaba, {{ auth.state.user?.name }}</h1><p>Devam etmek istediğiniz firmayı seçin.</p></div>
    <div v-if="error" class="form-alert" role="alert">{{ error }}</div>
    <div class="company-list">
      <button v-for="company in auth.state.companies" :key="company.id" :disabled="busyId !== ''" @click="selectCompany(company)">
        <span class="company-icon"><Building2 :size="21" /></span><span><strong>{{ company.name }}</strong><small>{{ company.code || 'Firma çalışma alanı' }}</small></span><span class="company-arrow"><ArrowRight :size="18" /></span>
      </button>
      <p v-if="!auth.state.companies.length" class="empty-companies">Hesabınıza tanımlı aktif bir firma bulunamadı.</p>
    </div>
    <p class="company-note">Seçiminiz bu oturum boyunca aktif kalır.</p>
  </section>
</template>
