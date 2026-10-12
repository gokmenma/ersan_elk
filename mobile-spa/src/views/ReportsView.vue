<script setup>
import { ArrowUpRight, CalendarRange, ChevronDown, TrendingUp, UsersRound, Zap } from '@lucide/vue'
import TopBar from '../components/TopBar.vue'
</script>

<template>
  <section class="screen">
    <TopBar eyebrow="ANALİZ" title="Raporlar" subtitle="Operasyon verilerini tek ekranda izleyin" :search="false" />
    <button class="date-selector"><CalendarRange :size="18" /><span><small>Rapor dönemi</small><strong>Ekim 2026</strong></span><ChevronDown :size="18" /></button>
    <div class="report-grid">
      <article><span class="tone-blue"><Zap :size="19" /></span><small>Tamamlanan iş</small><strong>8.426</strong><em><ArrowUpRight :size="13" /> %8,4</em></article>
      <article><span class="tone-mint"><UsersRound :size="19" /></span><small>Aktif personel</small><strong>128</strong><em><ArrowUpRight :size="13" /> %2,1</em></article>
    </div>
    <section class="large-chart-card">
      <div><span class="section-kicker">AYLIK TREND</span><h2>Saha üretkenliği</h2></div>
      <div class="line-visual"><svg viewBox="0 0 320 150" role="img" aria-label="Aylık saha üretkenliği grafiği"><defs><linearGradient id="fill" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#2167f3" stop-opacity=".26"/><stop offset="1" stop-color="#2167f3" stop-opacity="0"/></linearGradient></defs><path d="M0,121 C35,105 44,112 70,88 S118,101 145,69 S190,78 216,48 S264,67 320,22 L320,150 L0,150 Z" fill="url(#fill)"/><path d="M0,121 C35,105 44,112 70,88 S118,101 145,69 S190,78 216,48 S264,67 320,22" fill="none" stroke="#2167f3" stroke-width="4" stroke-linecap="round"/></svg></div>
      <footer><span>May</span><span>Haz</span><span>Tem</span><span>Ağu</span><span>Eyl</span><span>Eki</span></footer>
    </section>
    <div class="insight-card"><span><TrendingUp :size="22" /></span><div><small>ÖNE ÇIKAN İÇGÖRÜ</small><strong>Onikişubat ekibi bu ay ortalamanın %14 üzerinde.</strong></div></div>
  </section>
</template>
