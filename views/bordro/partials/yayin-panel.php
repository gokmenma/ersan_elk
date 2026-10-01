<?php
if (!isset($selectedDonem) || !$selectedDonem) return;
$yayinUser = (int) ($_SESSION['user_id'] ?? 0);
if (!$yayinUser || !(new \App\Model\MenuModel())->userCanAccessMenuLink($yayinUser, 'bordro/list')) return;
?>
<style>
/* Resmî Bordro Yayını Modal Özel UI Standartları (Image 1 Referansı) */
#bordroYayinModal .modal-dialog {
  max-width: 1320px;
  height: calc(100vh - 40px);
  max-height: 940px;
  margin: 20px auto;
  display: flex;
  align-items: center;
}
#bordroYayinModal .modal-content {
  height: 100%;
  max-height: 100%;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border: 1px solid #cbd5e1;
  border-radius: 12px !important;
  box-shadow: 0 12px 48px rgba(0, 0, 0, 0.15);
}
#bordroYayinModal .modal-header {
  flex-shrink: 0;
  padding: 14px 24px;
  background-color: #ffffff;
  border-bottom: 1px solid #e2e8f0;
}
#bordroYayinModal .modal-tabs-header {
  flex-shrink: 0;
  background-color: #f8fafc;
  padding: 10px 24px;
  border-bottom: 1px solid #e2e8f0;
}
#bordroYayinModal .nav-segment {
  display: flex;
  align-items: center;
  justify-content: center;
  background-color: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 8px !important;
  padding: 4px;
  gap: 6px;
}
#bordroYayinModal .nav-segment .nav-link {
  border-radius: 6px !important;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  height: 38px;
  padding: 0 24px;
  font-weight: 600;
  font-size: 13.5px;
  color: #64748b;
  border: none;
  transition: all 0.2s ease;
}
#bordroYayinModal .nav-segment .nav-link:hover {
  background-color: #f1f5f9;
  color: #1e293b;
}
#bordroYayinModal .nav-segment .nav-link.active {
  background-color: #4f46e5 !important;
  color: #ffffff !important;
  box-shadow: 0 2px 6px rgba(79, 70, 229, 0.3);
}
#bordroYayinModal .nav-segment .nav-link .badge {
  border-radius: 6px !important;
  font-size: 11px;
  font-weight: 700;
  padding: 3px 8px;
  background-color: #f1f5f9 !important;
  color: #475569 !important;
  border: 1px solid #e2e8f0 !important;
}
#bordroYayinModal .nav-segment .nav-link.active .badge {
  background-color: rgba(255, 255, 255, 0.25) !important;
  color: #ffffff !important;
  border: 1px solid rgba(255, 255, 255, 0.35) !important;
}
#bordroYayinModal .modal-body {
  flex: 1 1 auto;
  overflow: hidden !important;
  display: flex;
  flex-direction: column;
  padding: 14px 22px;
  background-color: #f8fafc;
  min-height: 0;
}
#bordroYayinModal .tab-content {
  flex: 1 1 auto;
  overflow: hidden !important;
  display: flex;
  flex-direction: column;
  min-height: 0;
}
#bordroYayinModal .tab-pane {
  height: 100%;
  max-height: 100%;
  display: flex;
  flex-direction: column;
  overflow: hidden !important;
  min-height: 0;
}
#bordroYayinModal .tab-pane:not(.active) {
  display: none !important;
}
#bordroYayinModal .modal-footer {
  flex-shrink: 0;
  padding: 10px 22px;
  background-color: #ffffff;
  border-top: 1px solid #e2e8f0;
}

/* 1. Resimdeki Dashboard Stili KPI Kartları */
.kpi-stat-card {
  background: #ffffff;
  border: 1px solid #e2e8f0 !important;
  border-radius: 9px !important;
  padding: 10px 14px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  height: 100%;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
  transition: all 0.2s ease;
  cursor: pointer;
  user-select: none;
  position: relative;
}
.kpi-stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
  border-color: #cbd5e1 !important;
}
.kpi-stat-card.active-card {
  border-color: #4f46e5 !important;
  background-color: #fcfdfe !important;
  box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.2), 0 4px 12px rgba(79, 70, 229, 0.08) !important;
}
.kpi-stat-card.active-card::after {
  content: '';
  position: absolute;
  bottom: -1px;
  left: 12px;
  right: 12px;
  height: 3px;
  background: #4f46e5;
  border-radius: 3px 3px 0 0;
}
.kpi-stat-card .card-top-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 4px;
}
.kpi-stat-card .card-title-text {
  font-size: 10.5px;
  font-weight: 700;
  letter-spacing: 0.5px;
  color: #64748b;
  text-transform: uppercase;
  margin: 0;
}
.kpi-stat-card .card-icon-box {
  width: 26px;
  height: 26px;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
}
.kpi-stat-card .card-value-text {
  font-size: 20px !important;
  font-weight: 800;
  color: #0f172a;
  line-height: 1.2;
  margin: 2px 0 4px 0;
}
.kpi-stat-card .card-bottom-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-top: 1px solid #f1f5f9;
  padding-top: 4px;
  margin-top: 2px;
}
.kpi-stat-card .card-subtext {
  font-size: 11px;
  font-weight: 600;
}
.kpi-stat-card .card-pill-tag {
  font-size: 10.5px;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 10px;
  border: 1px solid #e2e8f0;
  background: #f8fafc;
  color: #475569;
}

/* 1. Resimdeki Tablo ve Satır Kenarlıkları */
.table-panel-card {
  background: #ffffff;
  border: 1px solid #e2e8f0 !important;
  border-radius: 10px !important;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
#bordroYayinTable {
  border-collapse: collapse !important;
  width: 100% !important;
  margin-bottom: 0 !important;
}
#bordroYayinTable thead th {
  background-color: #f8fafc !important;
  color: #475569 !important;
  font-weight: 700 !important;
  font-size: 11.5px !important;
  letter-spacing: 0.3px;
  padding: 10px 12px !important;
  border-bottom: 1px solid #cbd5e1 !important;
  border-right: 1px solid #e2e8f0 !important;
  white-space: nowrap;
}
#bordroYayinTable thead th:last-child {
  border-right: none !important;
}
#bordroYayinTable tbody tr {
  border-bottom: 1px solid #e2e8f0 !important;
  transition: background-color 0.15s ease;
}
#bordroYayinTable tbody tr:hover {
  background-color: #f8fafc !important;
}
#bordroYayinTable tbody td {
  padding: 10px 12px !important;
  font-size: 13px !important;
  vertical-align: middle !important;
  border-right: 1px solid #f1f5f9 !important;
  background-color: transparent !important;
}
#bordroYayinTable tbody td:last-child {
  border-right: none !important;
}

/* Önizleme 2 Sütunlu Panel */
.onizleme-grid {
  display: flex;
  flex: 1 1 auto;
  gap: 16px;
  min-height: 0;
  overflow: hidden;
}
.onizleme-col-left {
  flex: 0 0 380px;
  max-width: 380px;
  display: flex;
  flex-direction: column;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 10px !important;
  overflow: hidden;
  min-height: 0;
}
.onizleme-col-right {
  flex: 1 1 auto;
  min-width: 0;
  display: flex;
  flex-direction: column;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 10px !important;
  overflow-y: auto;
  min-height: 0;
}

/* Arama Kutusu */
.onizleme-search-box {
  padding: 10px 12px;
  background-color: #ffffff;
  border-bottom: 1px solid #e2e8f0;
  flex-shrink: 0;
}
.onizleme-search-input-group {
  display: flex;
  align-items: center;
  border: 1px solid #cbd5e1;
  border-radius: 6px !important;
  background: #ffffff;
  height: 42px;
  padding: 0 12px;
  transition: border-color 0.2s, box-shadow 0.2s;
}
.onizleme-search-input-group:focus-within {
  border-color: #4f46e5;
  box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
}
.onizleme-search-input-group input {
  border: none;
  outline: none;
  background: transparent;
  width: 100%;
  font-size: 13.5px;
  color: #1e293b;
  padding-left: 8px;
}

/* Personel Kartı Listesi */
.onizleme-item {
  border: 1px solid #e2e8f0;
  border-radius: 8px !important;
  margin: 4px 8px;
  padding: 9px 12px;
  cursor: pointer;
  transition: all 0.15s ease;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  background-color: #ffffff;
}
.onizleme-item:hover {
  background-color: #f8fafc;
  border-color: #cbd5e1;
  transform: translateX(2px);
}
.onizleme-item.active {
  background-color: #f5f3ff !important;
  border: 1px solid #6366f1 !important;
  box-shadow: 0 2px 6px rgba(99, 102, 241, 0.12);
}
.onizleme-item .personel-avatar {
  width: 36px;
  height: 36px;
  min-width: 36px;
  border-radius: 8px;
  background-color: #f1f5f9;
  color: #475569;
  font-size: 13px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  transition: all 0.15s ease;
}
.onizleme-item.active .personel-avatar {
  background-color: #4f46e5 !important;
  color: #ffffff !important;
}
.onizleme-item .personel-name {
  font-weight: 700;
  color: #1e293b;
  font-size: 13.5px;
  line-height: 1.25;
}
.onizleme-item.active .personel-name {
  color: #3730a3 !important;
}
.onizleme-item .personel-net {
  font-weight: 800;
  color: #0f172a;
  font-size: 13.5px;
  line-height: 1.25;
}
.onizleme-item.active .personel-net {
  color: #4338ca !important;
}
</style>

<!-- Resmî Bordro Yayını Ana Modalı -->
<div class="modal fade" id="bordroYayinModal" tabindex="-1" aria-labelledby="bordroYayinBaslik" aria-hidden="true" data-modal-icon="bx bx-broadcast" data-modal-subtitle="Dönem bordrolarının personel PWA yayını, önizleme kontrolleri, okuma beyanları ve inceleme talepleri">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content border-0">
      
      <!-- Modal Başlığı -->
      <div class="modal-header">
        <div class="d-flex align-items-center gap-3">
          <div class="avatar-sm flex-shrink-0">
            <span class="avatar-title bg-primary-subtle text-primary rounded-3 fs-3 p-2 d-flex align-items-center justify-center">
              <i class="bx bx-broadcast fs-3"></i>
            </span>
          </div>
          <div>
            <h5 id="bordroYayinBaslik" class="modal-title fw-bold text-dark mb-0">Resmî Bordro Yayını ve Takibi</h5>
            <p class="text-muted fs-12 mb-0">Dönem bordrolarının personel PWA yayını, önizleme kontrolleri, okuma beyanları ve inceleme talepleri</p>
          </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
      </div>

      <!-- Modal Sekme Navigasyonu -->
      <div class="modal-tabs-header">
        <div class="nav-segment" id="bordroYayinNavTabs" role="tablist">
          <button class="nav-link active" id="tab-takip-btn" data-bs-toggle="pill" data-bs-target="#tab-takip-pane" type="button" role="tab" aria-controls="tab-takip-pane" aria-selected="true">
            <i class="bx bx-list-check fs-5"></i>
            <span>Yayın Takibi ve Beyanlar</span>
            <span class="badge bg-primary" id="tabBadgeTakipAdet">0</span>
          </button>
          <button class="nav-link" id="tab-onizleme-btn" data-bs-toggle="pill" data-bs-target="#tab-onizleme-pane" type="button" role="tab" aria-controls="tab-onizleme-pane" aria-selected="false">
            <i class="bx bx-layer-plus fs-5"></i>
            <span>Yeni Yayın ve Döküm Önizleme</span>
            <span class="badge bg-secondary" id="tabBadgeOnizlemeAdet">Hazırla</span>
          </button>
        </div>
      </div>

      <div class="modal-body">
        <!-- Global Bildirim / Uyarı Alanı -->
        <div id="bordroYayinMesaj" role="status" class="alert alert-danger py-2 px-3 mb-2 d-none" style="border-radius: 8px;"></div>

        <div class="tab-content">
          
          <!-- ================= SEKME 1: YAYIN TAKİBİ VE BEYANLAR ================= -->
          <div class="tab-pane fade show active" id="tab-takip-pane" role="tabpanel" aria-labelledby="tab-takip-btn" tabindex="0">
            <!-- 1. Resimdeki Dashboard Stili 4 KPI Özet Kartı (Tıklanabilir Filtreler) -->
            <div class="row g-2 mb-2 flex-shrink-0" id="bordroYayinKpiRow">
              <div class="col-6 col-md-3">
                <div class="kpi-stat-card active-card" data-yayin-filtre="" title="Tüm yayınlanan personeli listele">
                  <div class="card-top-row">
                    <span class="card-title-text">TOPLAM YAYINLANAN</span>
                    <div class="card-icon-box" style="background: #f1f5f9; color: #475569;">
                      <i class="bx bx-user-check"></i>
                    </div>
                  </div>
                  <div class="card-value-text" id="statYayinlanan">0</div>
                  <div class="card-bottom-row">
                    <span class="card-subtext text-muted">Tüm Dökümler</span>
                    <span class="card-pill-tag"><i class="bx bx-broadcast me-1"></i>Yayında</span>
                  </div>
                </div>
              </div>

              <div class="col-6 col-md-3">
                <div class="kpi-stat-card" data-yayin-filtre="goruntuleyen" title="PWA üzerinden bordrosunu görüntüleyenleri filtrele">
                  <div class="card-top-row">
                    <span class="card-title-text">GÖRÜNTÜLEYENLER</span>
                    <div class="card-icon-box" style="background: #eff6ff; color: #3b82f6;">
                      <i class="bx bx-show"></i>
                    </div>
                  </div>
                  <div class="card-value-text text-primary" id="statGoruntuleyen">0</div>
                  <div class="card-bottom-row">
                    <span class="card-subtext text-primary" id="statGoruntuleyenOran">PWA Görüntüleme</span>
                    <span class="card-pill-tag"><i class="bx bx-mobile-alt me-1 text-primary"></i>İnceleyen</span>
                  </div>
                </div>
              </div>

              <div class="col-6 col-md-3">
                <div class="kpi-stat-card" data-yayin-filtre="beyan" title="Okuma ve teslim beyanı verenleri filtrele">
                  <div class="card-top-row">
                    <span class="card-title-text">OKUMA BEYANI VEREN</span>
                    <div class="card-icon-box" style="background: #ecfdf5; color: #10b981;">
                      <i class="bx bx-check-circle"></i>
                    </div>
                  </div>
                  <div class="card-value-text text-success" id="statBeyanVeren">0</div>
                  <div class="card-bottom-row">
                    <span class="card-subtext text-success" id="statBeyanOran">Onay Veren</span>
                    <span class="card-pill-tag" style="background: #ecfdf5; color: #047857; border-color: #a7f3d0;"><i class="bx bx-check-double me-1"></i>Onaylandı</span>
                  </div>
                </div>
              </div>

              <div class="col-6 col-md-3">
                <div class="kpi-stat-card" data-yayin-filtre="talep" title="Açık inceleme talebi bulunanları filtrele">
                  <div class="card-top-row">
                    <span class="card-title-text">AÇIK İNCELEME TALEBİ</span>
                    <div class="card-icon-box" style="background: #fefce8; color: #ca8a04;">
                      <i class="bx bx-message-square-error"></i>
                    </div>
                  </div>
                  <div class="card-value-text text-warning" id="statTalepVar">0</div>
                  <div class="card-bottom-row">
                    <span class="card-subtext text-warning">Bekleyen Talep</span>
                    <span class="card-pill-tag" style="background: #fefce8; color: #a16207; border-color: #fde047;"><i class="bx bx-time-five me-1"></i>İnceleniyor</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Tablo Üst Araç Çubuğu -->
            <div class="d-flex justify-content-between align-items-center gap-2 mb-2 flex-shrink-0">
              <div class="d-flex align-items-center gap-2">
                <span class="fs-13 fw-bold text-dark"><i class="bx bx-list-ul me-1 text-primary"></i>Personel Döküm Listesi</span>
                <span class="badge bg-light text-muted border fs-11" id="bordroYayinTabloSayisi">0 Kayıt</span>
              </div>
              <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1.5 bg-white" id="btnTakipYenile" style="border: 1px solid #cbd5e1; border-radius: 6px; font-weight: 600;" title="Personel okuma beyanları ve inceleme taleplerini canlı yenile">
                  <i class="bx bx-refresh fs-5"></i> Yenile
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1 bg-white" id="bordroYayinCsv" style="border: 1px solid #cbd5e1; border-radius: 6px; font-weight: 600;">
                  <i class="bx bx-download"></i> CSV İndir
                </button>
              </div>
            </div>

            <!-- 1. Resimdeki Panel Tablosu (Belirgin Satır ve Kenarlıklar) -->
            <div class="table-panel-card flex-grow-1" style="overflow-y: auto;">
              <table class="table align-middle w-100 mb-0" id="bordroYayinTable">
                <thead class="sticky-top" style="z-index: 2;">
                  <tr>
                    <th data-filter="string">PERSONEL</th>
                    <th data-filter="string" style="width: 80px;">SÜRÜM</th>
                    <th data-filter="select">YAYIN DURUMU</th>
                    <th data-filter="date">YAYIN TARİHİ</th>
                    <th data-filter="date">GÖRÜNTÜLEME</th>
                    <th data-filter="date">OKUMA BEYANI</th>
                    <th data-filter="string">İNCELEME TALEPLERİ</th>
                    <th data-filter="select">PUSH DURUMU</th>
                    <th style="width: 90px;">İŞLEM</th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>
          </div>

          <!-- ================= SEKME 2: YENİ YAYIN VE DÖKÜM ÖNİZLEME ================= -->
          <div class="tab-pane fade" id="tab-onizleme-pane" role="tabpanel" aria-labelledby="tab-onizleme-btn" tabindex="0">
            
            <!-- Önizleme Başlatılmamış Durum -->
            <div id="onizlemeBosDurum" class="text-center py-5 border bg-white my-auto shadow-sm" style="border: 1px solid #e2e8f0; border-radius: 10px;">
              <div class="avatar-md mx-auto mb-3">
                <span class="avatar-title bg-primary-subtle text-primary rounded-circle fs-2 p-3 d-inline-flex align-items-center justify-center">
                  <i class="bx bx-layer-plus"></i>
                </span>
              </div>
              <h5 class="fw-bold text-dark">Dönem Dökümlerini Hazırlayın</h5>
              <p class="text-muted fs-13 mb-4 mx-auto" style="max-width: 480px;">
                Kapalı döneme ait personellerin net ücret, yemek yardımı ve tüm kazanç kalemleri kontrol edilerek yayına hazır döküm paketi üretilir.
              </p>
              <button type="button" class="btn btn-primary px-4 py-2 d-inline-flex align-items-center gap-2 fw-semibold" id="bordroYayinOnizle" style="border-radius: 8px;">
                <i class="bx bx-play fs-5"></i> Önizlemeyi ve Dökümleri Hazırla
              </button>
            </div>

            <!-- Önizleme Yüklendiğinde Gösterilecek Alan -->
            <div id="onizlemeDoluDurum" class="d-none h-100 d-flex flex-column min-h-0">
              
              <!-- Tab 2: 4 KPI Özet Kartı -->
              <div class="row g-2 mb-2 flex-shrink-0" id="onizlemeKpiRow">
                <div class="col-6 col-md-3">
                  <div class="kpi-stat-card">
                    <div class="card-top-row">
                      <span class="card-title-text">YAYINA HAZIR</span>
                      <div class="card-icon-box" style="background: #ecfdf5; color: #10b981;">
                        <i class="bx bx-user-check"></i>
                      </div>
                    </div>
                    <div class="card-value-text text-success" id="onizlemeStatHazir">0</div>
                    <div class="card-bottom-row">
                      <span class="card-subtext text-success">Bordro Paketi</span>
                      <span class="card-pill-tag" style="background: #ecfdf5; color: #047857; border-color: #a7f3d0;"><i class="bx bx-check me-1"></i>Hazır</span>
                    </div>
                  </div>
                </div>

                <div class="col-6 col-md-3">
                  <div class="kpi-stat-card">
                    <div class="card-top-row">
                      <span class="card-title-text">TOPLAM BANKA ÖDEMESİ</span>
                      <div class="card-icon-box" style="background: #eff6ff; color: #3b82f6;">
                        <i class="bx bx-credit-card"></i>
                      </div>
                    </div>
                    <div class="card-value-text text-primary" id="onizlemeStatTutar" style="font-size: 22px;">₺0,00</div>
                    <div class="card-bottom-row">
                      <span class="card-subtext text-primary">Resmî Net</span>
                      <span class="card-pill-tag"><i class="bx bx-building-house me-1 text-primary"></i>Banka</span>
                    </div>
                  </div>
                </div>

                <div class="col-6 col-md-3">
                  <div class="kpi-stat-card">
                    <div class="card-top-row">
                      <span class="card-title-text">KONTROL DURUMU</span>
                      <div class="card-icon-box" id="onizlemeStatHataIcon" style="background: #ecfdf5; color: #10b981;">
                        <i class="bx bx-shield-check"></i>
                      </div>
                    </div>
                    <div class="card-value-text text-success" id="onizlemeStatHata">0 Hata</div>
                    <div class="card-bottom-row">
                      <span class="card-subtext text-muted" id="onizlemeStatHataAlt">Doğrulandı</span>
                      <span class="card-pill-tag" id="onizlemeStatHataPill" style="background: #ecfdf5; color: #047857; border-color: #a7f3d0;"><i class="bx bx-check-double me-1"></i>Sorunsuz</span>
                    </div>
                  </div>
                </div>

                <div class="col-6 col-md-3">
                  <div class="kpi-stat-card" id="btnDislananKpiCard" title="Dışlanan pasif ve ayrılmış personellerin listesini görüntüle" role="button">
                    <div class="card-top-row">
                      <span class="card-title-text">DIŞLANAN PERSONEL</span>
                      <div class="card-icon-box" style="background: #f1f5f9; color: #64748b;">
                        <i class="bx bx-user-x"></i>
                      </div>
                    </div>
                    <div class="card-value-text text-secondary" id="onizlemeStatDislanan">0</div>
                    <div class="card-bottom-row">
                      <span class="card-subtext text-muted">Pasif / Ayrılmış</span>
                      <span class="card-pill-tag"><i class="bx bx-list-ul me-1"></i>Listeyi Gör</span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Üst Eylem Araç Çubuğu -->
              <div class="d-flex justify-content-between align-items-center gap-2 mb-2 flex-shrink-0">
                <div class="d-flex align-items-center gap-2">
                  <span class="fs-13 fw-bold text-dark"><i class="bx bx-layer me-1 text-primary"></i>Döküm Pusulası ve Personel Listesi</span>
                  <span class="badge bg-light text-muted border fs-11" id="onizlemeListeSayisi">0 Personel</span>
                  <button type="button" class="btn btn-sm btn-outline-secondary d-none d-inline-flex align-items-center gap-1 bg-white py-0.5 px-2 fs-11" id="btnToolbarDislanan" style="border: 1px solid #cbd5e1; border-radius: 6px;">
                    <i class="bx bx-user-x text-muted"></i> Dışlananlar (<span id="toolbarDislananAdet">0</span>)
                  </button>
                </div>
                <div class="d-flex flex-wrap gap-2">
                  <button type="button" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1 bg-white" id="btnOnizlemeYenile" style="border: 1px solid #cbd5e1; border-radius: 6px; font-weight: 500;">
                    <i class="bx bx-refresh"></i> Yeniden Hesapla
                  </button>
                  <button type="button" id="bordroYayinTestYayinla" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1 bg-white" disabled style="border-radius: 6px; font-weight: 600;">
                    <i class="bx bx-test-tube"></i> Seçilen Personelde Test Et
                  </button>
                  <button type="button" id="bordroYayinYayinla" class="btn btn-sm btn-success d-inline-flex align-items-center gap-1 fw-bold px-3" disabled style="border-radius: 6px;">
                    <i class="bx bx-send"></i> Tüm Dökümleri Yayınla
                  </button>
                </div>
              </div>

              <!-- Hatalar Bildirim Alanı (Yalnızca Kritik Hatalar) -->
              <div id="bordroYayinHatalar" class="flex-shrink-0"></div>

              <!-- Master-Detail 2 Sütunlu Önizleme Alanı -->
              <div class="onizleme-grid">
                <!-- Sol Sütun: Personel Seçim Listesi -->
                <div class="onizleme-col-left">
                  <div class="onizleme-search-box">
                    <div class="onizleme-search-input-group">
                      <i class="bx bx-search text-muted fs-5"></i>
                      <input type="search" id="onizlemeArama" placeholder="Personel ara (İsim, Departman)..." autocomplete="off">
                    </div>
                  </div>
                  <div class="flex-grow-1 overflow-y-auto p-1" id="onizlemePersonelListe">
                    <!-- Personel kartları buraya JS ile dolacak -->
                  </div>
                </div>

                <!-- Sağ Sütun: Seçili Personelin Bordro Pusulası Tam Görünümü -->
                <div class="onizleme-col-right" id="onizlemePusulaContainer">
                  <!-- Seçilen personelin pusulası buraya yüklenecek -->
                </div>
              </div>
            </div>

          </div>

        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 8px;">Kapat</button>
      </div>
    </div>
  </div>
</div>

<!-- Bordro Detay ve Talep Yanıtlama Modalı -->
<div class="modal fade" id="bordroYayinDetayModal" tabindex="-1" aria-labelledby="bordroYayinDetayBaslik" aria-hidden="true" data-modal-icon="bx bx-receipt" data-modal-subtitle="Resmî bordro dökümü ve personel inceleme talebi detayları">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content shadow-lg" style="border: 1px solid #cbd5e1; border-radius: 12px;">
      <div class="modal-header bg-light border-bottom py-2.5 px-3">
        <div class="d-flex align-items-center gap-2">
          <i class="bx bx-receipt fs-4 text-primary"></i>
          <div>
            <h5 class="modal-title fw-bold text-dark mb-0 fs-15" id="bordroYayinDetayBaslik">Bordro Döküm Detayı</h5>
            <p class="text-muted fs-11 mb-0" id="bordroYayinDetayAltBaslik">Resmî bordro ve inceleme talebi detayları</p>
          </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
      </div>
      <div class="modal-body p-3" id="bordroYayinDetayGovde">
        <!-- Dinamik Detay İçeriği Buraya Yüklenecek -->
      </div>
      <div class="modal-footer bg-light border-top py-2 px-3">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal" style="border-radius: 6px;">Kapat</button>
      </div>
    </div>
  </div>
</div>

<!-- Dışlanan Personeller Geniş Modal -->
<div class="modal fade" id="bordroYayinDislananlarModal" tabindex="-1" aria-labelledby="bordroYayinDislananlarBaslik" aria-hidden="true" data-modal-icon="bx bx-user-x" data-modal-subtitle="Dönem yayınına dahil edilmeyen pasif ve ayrılmış personeller">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" style="max-width: 1080px;">
    <div class="modal-content shadow-lg" style="border: 1px solid #cbd5e1; border-radius: 12px;">
      <div class="modal-header bg-light border-bottom px-4 py-3">
        <div class="d-flex align-items-center gap-3">
          <div class="avatar-sm flex-shrink-0" style="width: 38px; height: 38px;">
            <span class="avatar-title bg-warning-subtle text-warning rounded-3 fs-4 p-2 d-flex align-items-center justify-center">
              <i class="bx bx-user-x"></i>
            </span>
          </div>
          <div>
            <h5 class="modal-title fw-bold text-dark mb-0 fs-16" id="bordroYayinDislananlarBaslik">Dışlanan Personeller</h5>
            <p class="text-muted fs-12 mb-0">Pasif durumda veya işten ayrılmış olduğu için PWA hesabı bulunmayan ve bu dönem yayınına dahil edilmeyen kayıtlar</p>
          </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
      </div>
      <div class="modal-body p-4 bg-light-subtle">
        
        <!-- Üst Filtreleme ve Arama Barı -->
        <div class="card border rounded-3 p-3.5 mb-3.5 bg-white shadow-none" style="border: 1px solid #e2e8f0 !important; padding: 16px 20px;">
          <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
            <div class="d-flex flex-wrap gap-1.5 align-items-center" id="dislananFilterPills">
              <!-- Filtre hapları JS ile dinamik gelecek -->
            </div>
            <div class="flex-shrink-0">
              <span class="badge bg-light text-muted border fs-11 px-2.5 py-1.5" id="dislananFiltreAdet">0 Kayıt</span>
            </div>
          </div>
          
          <div class="input-group" style="border: 1px solid #cbd5e1; border-radius: 6px; overflow: hidden; height: 42px;">
            <span class="input-group-text bg-white border-0 ps-3"><i class="bx bx-search text-muted fs-5"></i></span>
            <input type="search" id="dislananAramaInput" class="form-control border-0 fs-13 ps-2" placeholder="Personel ismi veya gerekçe ara..." autocomplete="off">
          </div>
        </div>

        <!-- Gruplanmış ve Geniş Tablo Listesi Alanı -->
        <div id="dislananListeGovde">
          <!-- Gruplanmış kartlar ve tablolar JS ile buraya yüklenecek -->
        </div>

      </div>
      <div class="modal-footer bg-light border-top px-4 py-2.5">
        <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal" style="border-radius: 6px;">Kapat</button>
      </div>
    </div>
  </div>
</div>

<script>
window.bordroYayinConfig = <?= json_encode([
    'donem_token' => \App\Helper\Security::encrypt((int) $selectedDonemId),
    'donem_adi' => (string) ($selectedDonem->donem_adi ?? ''),
    'donem_kapali' => (int) ($selectedDonem->kapali_mi ?? 0),
    'csrf_token' => \App\Helper\Security::csrf(),
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="views/bordro/js/bordro-yayin.js?v=<?= filemtime(dirname(__DIR__) . '/js/bordro-yayin.js') ?>"></script>
