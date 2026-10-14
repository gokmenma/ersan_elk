<script setup>
import { ArrowUpRight, CalendarRange, ChevronDown, TrendingUp, UsersRound, Zap } from '@lucide/vue'
import TopBar from '../components/TopBar.vue'
</script>

<template>
  <section class="ds-screen">
    <!-- 1. Standart TopBar -->
    <TopBar eyebrow="ANALİZ" title="Raporlar" subtitle="Operasyon verilerini tek ekranda izleyin" :back="true" />

    <!-- 2. Dönem Seçici Bento Kartı -->
    <button type="button" class="bento-card bento-card--white report-date-card">
      <div class="report-date-icon">
        <CalendarRange :size="18" />
      </div>
      <div class="report-date-text">
        <small>Rapor dönemi</small>
        <strong>Ekim 2026</strong>
      </div>
      <ChevronDown :size="18" class="report-date-arrow" />
    </button>

    <!-- 3. 2'li KPI Bento Kartları -->
    <div class="report-kpi-grid">
      <article class="bento-card bento-card--white report-kpi-card">
        <span class="op-icon-bubble" style="background: #0284c7;">
          <Zap :size="16" />
        </span>
        <small class="kpi-label">Tamamlanan iş</small>
        <strong class="kpi-value">8.426</strong>
        <em class="kpi-trend text-emerald">
          <ArrowUpRight :size="13" /> %8,4
        </em>
      </article>

      <article class="bento-card bento-card--white report-kpi-card">
        <span class="op-icon-bubble" style="background: #10b981;">
          <UsersRound :size="16" />
        </span>
        <small class="kpi-label">Aktif personel</small>
        <strong class="kpi-value">128</strong>
        <em class="kpi-trend text-emerald">
          <ArrowUpRight :size="13" /> %2,1
        </em>
      </article>
    </div>

    <!-- 4. Büyük Trend Grafik Bento Kartı -->
    <section class="bento-card bento-card--white">
      <div class="bento-head">
        <div class="bento-head-title">
          <span class="clay-pill-tag mb-1">AYLIK TREND</span>
          <h3>Saha Üretkenliği</h3>
        </div>
      </div>
      <div class="line-visual">
        <svg viewBox="0 0 320 150" role="img" aria-label="Aylık saha üretkenliği grafiği">
          <defs>
            <linearGradient id="fill" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0" stop-color="#181c24" stop-opacity=".18" />
              <stop offset="1" stop-color="#181c24" stop-opacity="0" />
            </linearGradient>
          </defs>
          <path d="M0,121 C35,105 44,112 70,88 S118,101 145,69 S190,78 216,48 S264,67 320,22 L320,150 L0,150 Z" fill="url(#fill)" />
          <path d="M0,121 C35,105 44,112 70,88 S118,101 145,69 S190,78 216,48 S264,67 320,22" fill="none" stroke="#181c24" stroke-width="3" stroke-linecap="round" />
        </svg>
      </div>
      <footer class="chart-footer">
        <span>May</span><span>Haz</span><span>Tem</span><span>Ağu</span><span>Eyl</span><span>Eki</span>
      </footer>
    </section>

    <!-- 5. Öne Çıkan İçgörü Bento Kartı (Kontrast Obsidian Koyu Bento) -->
    <div class="bento-card bento-card--dark insight-card">
      <span class="bento-mini-btn bento-mini-btn--dark">
        <TrendingUp :size="18" class="text-amber" />
      </span>
      <div class="insight-content">
        <span class="dark-pill-tag">İÇGÖRÜ</span>
        <strong>Onikişubat ekibi bu ay ortalamanın %14 üzerinde.</strong>
      </div>
    </div>
  </section>
</template>

<style scoped>
.report-date-card {
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
  padding: 14px 16px;
  text-align: left;
  cursor: pointer;
}

.report-date-icon {
  display: grid;
  width: 38px;
  height: 38px;
  place-items: center;
  border-radius: 12px;
  background: var(--ds-surface-subtle);
  color: var(--ds-muted);
}

.report-date-text {
  flex: 1;
}

.report-date-text small {
  display: block;
  font-size: 10px;
  color: var(--ds-muted);
}

.report-date-text strong {
  display: block;
  font-size: 14px;
  font-weight: 800;
  color: var(--ds-ink);
}

.report-date-arrow {
  color: var(--ds-muted);
}

.report-kpi-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 10px;
  margin-bottom: 14px;
}

.report-kpi-card {
  padding: 16px;
  margin-bottom: 0;
}

.kpi-label {
  display: block;
  font-size: 11px;
  color: var(--ds-muted);
  margin-top: 8px;
}

.kpi-value {
  display: block;
  font-size: 24px;
  font-weight: 800;
  letter-spacing: -0.04em;
  color: var(--ds-ink);
  line-height: 1.1;
  margin-top: 2px;
}

.kpi-trend {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  font-size: 11px;
  font-weight: 700;
  font-style: normal;
  margin-top: 4px;
}

.line-visual {
  height: 140px;
  margin-top: 8px;
}

.line-visual svg {
  width: 100%;
  height: 100%;
}

.chart-footer {
  display: flex;
  justify-content: space-between;
  color: var(--ds-muted);
  font: 600 10px var(--ds-font-mono);
  margin-top: 10px;
  padding-top: 8px;
  border-top: 1px solid var(--ds-border-subtle);
}

.insight-card {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 16px;
}

.insight-content {
  flex: 1;
}

.insight-content strong {
  display: block;
  font-size: 12.5px;
  font-weight: 700;
  color: #ffffff;
  margin-top: 3px;
  line-height: 1.4;
}
</style>
