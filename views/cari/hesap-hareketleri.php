<?php
require_once dirname(__DIR__, 1) . '/../Autoloader.php';
use App\Helper\Security;
use App\Helper\Form;
use App\Model\CariModel;

$maintitle = 'Cari Yönetimi';
$title = 'Hesap Hareketleri';

$cari_id_enc = $_GET['id'] ?? '';
$cari_id = Security::decrypt($cari_id_enc);

$Cari = new CariModel();
$cariData = $Cari->find($cari_id);

if (!$cariData) {
    echo '<div class="alert alert-danger m-4">Cari bulunamadı!</div>';
    exit;
}

// Cari Özet Bilgileri
$stmt = $Cari->getDb()->prepare("SELECT COUNT(*) as toplam_islem, SUM(borc) as toplam_borc, SUM(alacak) as toplam_alacak, SUM(alacak - borc) as bakiye FROM cari_hareketleri WHERE cari_id = :cari_id AND silinme_tarihi IS NULL");
$stmt->execute(['cari_id' => $cari_id]);
$ozet = $stmt->fetch(PDO::FETCH_OBJ);
$toplam_islem = (int)($ozet->toplam_islem ?? 0);
$toplam_borc = (float)($ozet->toplam_borc ?? 0);
$toplam_alacak = (float)($ozet->toplam_alacak ?? 0);
$bakiye = (float)($ozet->bakiye ?? 0);
?>
<script>try { document.documentElement.classList.toggle('cari-hareket-summary-hidden', localStorage.getItem('cari_hareket_summary_cards_state') === 'hidden'); } catch (e) {}</script>
<style>
#summaryCardsContainer { overflow: hidden; max-height: 1100px; opacity: 1; transition: max-height .3s ease, opacity .3s ease, margin .3s ease; }
.cari-hareket-summary-hidden #summaryCardsContainer { max-height: 0 !important; opacity: 0; margin-top: 0 !important; margin-bottom: 0 !important; pointer-events: none; }
@media (prefers-reduced-motion: reduce) { #summaryCardsContainer { transition: none; } }
</style>

<?php include 'layouts/breadcrumb.php'; ?>

<div class="container-fluid">
    <!-- 1. Üst Başlık ve Aksiyon Araç Çubuğu (Fatura Sayfası Formatı) -->
    <div class="row align-items-center mb-3">
        <div class="col-md-6 col-12 d-flex align-items-center gap-3">
            <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 44px; height: 44px;">
                <i class="bx bx-history fs-4 text-primary"></i>
            </div>
            <div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <h4 class="mb-0 fw-bold text-dark font-size-16"><?= htmlspecialchars($cariData->CariAdi) ?></h4>
                    <?php if (!empty($cariData->vkn_tckn)): ?>
                        <span class="badge bg-light text-muted border font-monospace font-size-11"><?= htmlspecialchars($cariData->vkn_tckn) ?></span>
                    <?php endif; ?>
                </div>
                <p class="text-muted mb-0 font-size-12">
                    <?= !empty($cariData->firma) ? htmlspecialchars($cariData->firma) . ' &bull; ' : '' ?>
                    <i class="bx bx-phone me-1"></i><?= htmlspecialchars($cariData->Telefon ?: 'Telefon Yok') ?>
                    <?php if (!empty($cariData->Email)): ?>
                        &bull; <i class="bx bx-envelope me-1"></i><?= htmlspecialchars($cariData->Email) ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>
        
        <div class="personel-action-toolbar col-md-6 col-12 d-flex align-items-center justify-content-md-end gap-2 mt-2 mt-md-0 flex-wrap">
            <!-- 1. Listeye Dön Butonu -->
            <a href="index.php?p=cari/list" class="btn btn-outline-secondary bg-white top-action-btn shadow-sm">
                <i class="bx bx-arrow-back font-size-16"></i> <span class="d-none d-sm-inline">Listeye Dön</span>
            </a>

            <!-- 2. Aldım (+) Butonu -->
            <button type="button" class="btn btn-success top-action-btn shadow-sm text-white" id="btnAldimDesktop">
                <i class="bx bx-plus-circle font-size-16"></i> Aldım (+)
            </button>

            <!-- 3. Verdim (-) Butonu -->
            <button type="button" class="btn btn-danger top-action-btn shadow-sm text-white" id="btnVerdimDesktop">
                <i class="bx bx-minus-circle font-size-16"></i> Verdim (-)
            </button>

            <!-- 4. İşlemler Dropdown -->
            <div class="dropdown d-inline-block">
                <button type="button" class="btn btn-outline-secondary bg-white top-action-btn dropdown-toggle shadow-sm" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="bx bx-cog font-size-16 text-primary"></i> İşlemler
                </button>
                <div class="dropdown-menu dropdown-menu-end shadow-lg border-0">
                    <button type="button" class="dropdown-item d-flex align-items-center" id="btnExportExcel">
                        <i class="bx bx-file me-2 font-size-16 text-success"></i> Excel'e Aktar
                    </button>
                    <a class="dropdown-item d-flex align-items-center" href="views/cari/export-ekstre-pdf.php?id=<?= urlencode($cari_id_enc) ?>" target="_blank">
                        <i class="bx bx-file-blank me-2 font-size-16 text-danger"></i> PDF Ekstre İndir
                    </a>
                    <button type="button" class="dropdown-item d-flex align-items-center" id="btnPdfYukle">
                        <i class="bx bx-cloud-upload me-2 font-size-16 text-primary"></i> PDF Ekstre Yükle
                    </button>
                    <button type="button" class="dropdown-item d-flex align-items-center" onclick="editCariNoteDesktop()">
                        <i class="bx bx-notepad me-2 font-size-16 text-warning"></i> Cari Notu Ekle/Düzenle
                    </button>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item d-flex align-items-center" href="index.php?p=efatura/olustur">
                        <i class="bx bx-receipt me-2 text-primary font-size-16"></i> Yeni Fatura Kes
                    </a>
                </div>
            </div>

            <!-- 5. Özet Kartları Açma/Kapama Butonu -->
            <button type="button" class="btn btn-outline-secondary bg-white top-icon-btn shadow-sm" id="btnToggleSummaryCards" title="Özet Kartları Göster/Gizle" aria-expanded="true">
                <i class="bx bx-chevron-up"></i>
            </button>
        </div>
    </div>

    <!-- 2. 4 Adet Minimal Özet KPI Kartı (Fatura Formatı) -->
    <div class="row g-3 mb-3 summary-cards-group" id="summaryCardsContainer">
        <!-- Kart 1: TOPLAM GİRİŞ (ALD.') -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">TOPLAM GİRİŞ (ALD.")</span>
                        <div class="summary-kpi-icon bg-success-subtle text-success border border-success-subtle">
                            <i class="bx bx-trending-down"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-success" id="toplam_borc_kart"><?= number_format($toplam_borc, 2, ',', '.') ?> ₺</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-success fw-semibold">Giriş Hareketleri</span>
                        <button type="button" class="btn btn-sm btn-subtle-success rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-filter="aldim">
                            <i class="bx bx-plus-circle"></i> Girişler (+)
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kart 2: TOPLAM ÇIKIŞ (VERD.') -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">TOPLAM ÇIKIŞ (VERD.")</span>
                        <div class="summary-kpi-icon bg-danger-subtle text-danger border border-danger-subtle">
                            <i class="bx bx-trending-up"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-danger" id="toplam_alacak_kart"><?= number_format($toplam_alacak, 2, ',', '.') ?> ₺</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-danger fw-semibold">Çıkış Hareketleri</span>
                        <button type="button" class="btn btn-sm btn-subtle-danger rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-filter="verdim">
                            <i class="bx bx-minus-circle"></i> Çıkışlar (-)
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kart 3: BENİM DURUMUM (NET BAKİYE) -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">BENİM DURUMUM (NET)</span>
                        <div class="summary-kpi-icon bg-warning-subtle text-warning border border-warning-subtle">
                            <i class="bx bx-wallet"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1 <?= $bakiye < 0 ? 'text-danger' : ($bakiye > 0 ? 'text-success' : 'text-dark') ?>" id="genel_bakiye_kart">
                        <?= number_format(abs($bakiye), 2, ',', '.') ?> ₺
                    </h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-muted" id="stat_bakiye_durum_metni">Bakiye Durumu</span>
                        <span class="badge <?= $bakiye < 0 ? 'bg-danger-subtle text-danger border border-danger-subtle' : ($bakiye > 0 ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-secondary-subtle text-secondary border border-secondary-subtle') ?> rounded-pill px-2 py-1 font-size-11 fw-semibold" id="bakiye_status_text">
                            <?= $bakiye < 0 ? 'Borçluyum (Net)' : ($bakiye > 0 ? 'Alacaklıyım (Net)' : '0,00 ₺ (Dengede)') ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kart 4: TOPLAM İŞLEM SAYISI -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">TOPLAM HAREKET ADEDİ</span>
                        <div class="summary-kpi-icon bg-primary-subtle text-primary border border-primary-subtle">
                            <i class="bx bx-list-check"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1" id="toplam_islem_kart"><?= $toplam_islem ?></h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-muted" id="op_count"><?= $toplam_islem ?> Kayıtlı İşlem</span>
                        <button type="button" class="btn btn-sm btn-subtle-primary rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn active" data-filter="all">
                            <i class="bx bx-layer"></i> Tümü
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Cari Notu Varsa Şık Bildirim Kartı -->
    <?php if(!empty($cariData->notlar)): ?>
    <div class="card summary-kpi-card mb-3 border-warning-subtle" style="background: #fffdf5;">
        <div class="card-body p-3 d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-start gap-2">
                <div class="p-1.5 bg-warning-subtle text-warning rounded-2 border border-warning-subtle d-flex align-items-center justify-content-center flex-shrink-0 mt-0.5" style="width: 28px; height: 28px;">
                    <i class="bx bx-bookmark font-size-15"></i>
                </div>
                <div>
                    <h6 class="mb-1 fw-bold text-dark font-size-13">Cari Özel Notu</h6>
                    <p class="mb-0 text-muted font-size-12 fst-italic"><?= nl2br(htmlspecialchars($cariData->notlar)) ?></p>
                </div>
            </div>
            <button type="button" onclick="editCariNoteDesktop()" class="btn btn-sm btn-subtle-warning px-2.5 py-1 rounded-3 font-size-12 fw-semibold">
                <i class="bx bx-edit-alt me-1"></i> Düzenle
            </button>
        </div>
    </div>
    <?php endif; ?>

    <!-- 3. Standart DataTables Hareket Tablosu Kartı (Fatura Formatı) -->
    <div class="card summary-kpi-card mb-3" id="hareketListCard">
        <div class="card-header bg-transparent border-0 px-3 py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 38px; height: 38px;">
                    <i class="bx bx-list-ul font-size-20"></i>
                </div>
                <div>
                    <h5 class="card-title mb-0 font-size-14 fw-bold text-dark">Hesap Hareketleri Dökümü</h5>
                    <p class="text-muted mb-0 font-size-12" style="margin-top: 2px;">Tarih, belge, işlem tutarları ve yürüyen bakiye takibi</p>
                </div>
            </div>

            <!-- Sağ Araç Çubuğu -->
            <div class="d-flex align-items-center gap-2 ms-auto flex-wrap">
                <button type="button" class="btn btn-sm btn-subtle-success px-2.5 py-1.5 d-flex align-items-center gap-1 rounded-3 fw-semibold shadow-xs" id="btnHeaderExportExcel" title="Excel'e Aktar">
                    <i class="bx bx-file font-size-15"></i> <span class="d-none d-sm-inline font-size-12">Excel</span>
                </button>
                <button type="button" class="btn btn-sm btn-subtle-secondary px-2.5 py-1.5 d-flex align-items-center gap-1 rounded-3 fw-semibold shadow-xs" id="btnHeaderPrint" title="Tabloyu Yazdır">
                    <i class="bx bx-printer font-size-15"></i> <span class="d-none d-sm-inline font-size-12">Yazdır</span>
                </button>
                <button type="button" class="btn btn-sm btn-subtle-primary px-2.5 py-1.5 d-flex align-items-center gap-1 rounded-3 fw-semibold shadow-xs" id="btnHeaderRefresh" title="Listeyi Yenile">
                    <i class="bx bx-refresh font-size-15"></i> <span class="d-none d-sm-inline font-size-12">Yenile</span>
                </button>
            </div>
        </div>

        <div class="card-body p-3 pt-0">
            <div class="table-responsive" style="overflow-x: auto !important;">
                <table id="hareketTable" class="table table-bordered table-hover nowrap align-middle w-100 mb-0">
                    <thead class="table-light">
                        <tr>
                            <th data-filter="date" style="width: 130px;" class="text-center">TARİH</th>
                            <th data-filter="string" style="width: 120px;">BELGE NO</th>
                            <th data-filter="string">AÇIKLAMA</th>
                            <th data-filter="number" class="text-end" style="width: 140px;">GİRİŞ (ALD.)</th>
                            <th data-filter="number" class="text-end" style="width: 140px;">ÇIKIŞ (VERD.)</th>
                            <th data-filter="number" class="text-end" style="width: 150px;">YÜRÜYEN BAKİYE</th>
                            <th data-filter="none" style="width: 90px;" class="text-center">İŞLEMLER</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Mobil Liste Görünümü (Mobilde DataTables yerine gösterilir) -->
    <div class="hareket-mobile-list d-md-none mt-2">
        <div class="d-flex align-items-center justify-content-between mb-2 px-1">
            <span class="fw-bold font-size-13 text-dark">Kayıtlı İşlemler</span>
            <span class="text-muted font-size-11" id="mobile_op_count">(<?= $toplam_islem ?> İşlem)</span>
        </div>
        <div id="hareketMobileContainer"></div>
    </div>
</div>

<style>
/* Tablo Tipografi ve Okunabilirlik İyileştirmeleri */
#hareketTable {
    font-size: 13px !important;
}
#hareketTable thead th {
    font-size: 11.5px !important;
    font-weight: 700 !important;
    color: #334155 !important;
    letter-spacing: 0.3px;
    background-color: #f8fafc !important;
    vertical-align: middle !important;
}
#hareketTable tbody td {
    padding: 8px 12px !important;
    vertical-align: middle !important;
    color: #0f172a !important;
}
/* Muhasebe ve Tablo Satır Butonları */
.table-action-btn {
    width: 27px;
    height: 27px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    font-size: 13px;
    cursor: pointer;
    flex-shrink: 0;
    transition: all 0.15s ease;
}
.table-action-btn:hover {
    transform: translateY(-1px);
}
.action-btn-group {
    display: inline-flex;
    align-items: center;
    gap: 3px;
}

/* Modern KPI Kart Stilleri */
.summary-kpi-card {
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    background: #ffffff;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.summary-kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.05) !important;
}
.summary-kpi-label {
    font-size: 11px;
    font-weight: 700;
    color: #64748b;
    letter-spacing: 0.5px;
    text-transform: uppercase;
}
.summary-kpi-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 16px;
    line-height: 1;
}
.summary-kpi-value {
    font-size: 1.45rem;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.2;
}
.summary-kpi-subtext {
    font-size: 11.5px;
    color: #64748b;
    font-weight: 500;
}
.summary-pill-btn {
    font-size: 11px !important;
    height: 24px !important;
    line-height: 1 !important;
    padding: 0 10px !important;
    font-weight: 600 !important;
    border-radius: 20px !important;
    transition: all 0.2s ease;
}

/* Modern Subtle Renkli Butonlar */
.btn-subtle-primary {
    background-color: #eff6ff;
    color: #2563eb;
    border: 1px solid #bfdbfe;
    transition: all 0.18s ease;
}
.btn-subtle-primary:hover, .btn-subtle-primary:focus, .btn-subtle-primary.active {
    background-color: #2563eb !important;
    color: #ffffff !important;
    border-color: #2563eb !important;
    box-shadow: 0 2px 5px rgba(37, 99, 235, 0.25);
}

.btn-subtle-success {
    background-color: #f0fdf4;
    color: #16a34a;
    border: 1px solid #bbf7d0;
    transition: all 0.18s ease;
}
.btn-subtle-success:hover, .btn-subtle-success:focus, .btn-subtle-success.active {
    background-color: #16a34a !important;
    color: #ffffff !important;
    border-color: #16a34a !important;
    box-shadow: 0 2px 5px rgba(22, 163, 74, 0.25);
}

.btn-subtle-danger {
    background-color: #fef2f2;
    color: #dc2626;
    border: 1px solid #fecaca;
    transition: all 0.18s ease;
}
.btn-subtle-danger:hover, .btn-subtle-danger:focus, .btn-subtle-danger.active {
    background-color: #dc2626 !important;
    color: #ffffff !important;
    border-color: #dc2626 !important;
    box-shadow: 0 2px 5px rgba(220, 38, 38, 0.25);
}

.btn-subtle-warning {
    background-color: #fffbeb;
    color: #d97706;
    border: 1px solid #fde68a;
    transition: all 0.18s ease;
}
.btn-subtle-warning:hover, .btn-subtle-warning:focus, .btn-subtle-warning.active {
    background-color: #d97706 !important;
    color: #ffffff !important;
    border-color: #d97706 !important;
    box-shadow: 0 2px 5px rgba(217, 119, 6, 0.25);
}

.btn-subtle-secondary {
    background-color: #f8fafc;
    color: #475569;
    border: 1px solid #cbd5e1;
    transition: all 0.18s ease;
}
.btn-subtle-secondary:hover, .btn-subtle-secondary:focus {
    background-color: #475569;
    color: #ffffff !important;
    border-color: #475569;
    box-shadow: 0 2px 5px rgba(71, 85, 105, 0.25);
}

.top-action-btn {
    font-size: 13px;
    padding: 7px 14px;
    border-radius: 8px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s ease;
}

.top-icon-btn {
    width: 38px;
    height: 38px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    font-size: 1.15rem;
    color: #475569;
    border: 1px solid #cbd5e1;
    transition: all 0.2s ease;
}
.top-icon-btn:hover {
    background-color: #f1f5f9;
    color: #1e293b;
    border-color: #94a3b8;
}

/* Mobil Kart Stilleri */
.op-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 10px 12px;
    margin-bottom: 8px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.03);
}
</style>

<!-- Hızlı İşlem Modalı (Aldım/Verdim) -->
<div class="modal fade" id="hizliIslemModal" tabindex="-1" aria-labelledby="hizliIslemModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header border-bottom-0 pb-0 pt-4 px-4 align-items-start">
                <div class="d-flex align-items-center w-100">
                    <div class="bg-primary-subtle rounded-circle d-flex align-items-center justify-content-center me-3" id="hizliIslemIconBg" style="width: 48px; height: 48px;">
                        <i class="bx bx-transfer font-size-24 text-primary" id="hizliIslemIcon"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h5 class="modal-title fw-bold mb-1" id="hizliIslemModalLabel" style="color: #1a1a1a;">Yeni İşlem</h5>
                        <p class="text-muted small mb-0" id="hizliIslemModalDesc">İşlem bilgilerini doldurun.</p>
                    </div>
                </div>
                <button type="button" class="btn-close mt-1" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <form id="hizliIslemForm">
                <input type="hidden" name="action" value="hizli-hareket-kaydet">
                <input type="hidden" name="cari_id" value="<?= htmlspecialchars($cari_id_enc) ?>">
                <input type="hidden" name="hareket_id" id="hizli_hareket_id" value="">
                <input type="hidden" name="type" id="hizli_islem_type" value="">
                
                <div class="modal-body px-4 pt-4 pb-2">
                    <div class="mb-3">
                        <?= Form::FormFloatInput("text", "islem_tarihi", date('Y-m-d H:i'), "Tarih", "Tarih", "calendar", "form-control flatpickr-time-input", true, null, "off", false) ?>
                    </div>
                    <div class="mb-3">
                        <label id="hizli_islem_amt_label" class="form-label small fw-bold text-muted mb-1">Tutar</label>
                        <?= Form::FormFloatInput("text", "tutar", "", "0.00", "İşlem Tutarı", "dollar-sign", "form-control money", true, null, "off", false, 'step="0.01" min="0.01"') ?>
                    </div>
                    <div class="mb-3">
                        <?= Form::FormFloatInput("text", "belge_no", "", "Belge No", "Belge No", "hash", "form-control") ?>
                    </div>
                    <div class="mb-3">
                        <?= Form::FormFloatTextarea("aciklama", "", "Açıklama giriniz...", "Açıklama", "list", "form-control", false, "80px") ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted mb-1">Dosya (Resim veya PDF)</label>
                        <input type="file" name="dosya" class="form-control" accept="image/*,application/pdf">
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0 pb-4 px-4 justify-content-end">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal" style="background:#6c757d; color:#fff; border-radius: 10px; border:none; font-weight: 600;">İptal</button>
                    <button type="submit" class="btn btn-dark px-4 shadow-sm" style="background:#212529; color:#fff; border-radius: 10px; border:none; font-weight: 600;">
                        <i class="bx bx-save me-1"></i> Kaydet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- PDF'ten Hareket Yükleme Modalı -->
<div class="modal fade" id="pdfYukleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-fullscreen-md-down">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header border-bottom-0 pb-0 pt-4 px-4 align-items-start">
                <div class="d-flex align-items-center">
                    <div class="p-2 bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 48px; height: 48px;">
                        <i class="bx bx-cloud-upload font-size-24 text-primary"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-1" style="color: #1a1a1a;">PDF'ten Hareket Yükle</h5>
                        <p class="text-muted small mb-0">Ekstre PDF'ini seçin, satırları kontrol edip aktarın.</p>
                    </div>
                </div>
                <button type="button" class="btn-close mt-1" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>

            <div class="modal-body px-4 pt-4 pb-2">
                <div id="pdfAdim1">
                    <div class="border rounded-3 p-3 mb-3" style="border-style: dashed !important; background: #f8fafc;">
                        <label class="form-label small fw-bold text-muted mb-1">Ekstre PDF Dosyası</label>
                        <input type="file" id="pdfDosya" class="form-control" accept="application/pdf">
                        <div class="form-text small">Tarih, açıklama, Verdim ve Aldım sütunları içeren hesap ekstresi PDF'i yükleyin.</div>
                    </div>
                    <div class="text-end">
                        <button type="button" class="btn btn-dark px-4" id="btnPdfAnaliz" style="border-radius: 10px; font-weight: 600;">
                            <i class="bx bx-search me-1 font-size-16"></i> Analiz Et
                        </button>
                    </div>
                </div>

                <div id="pdfAdim2" class="d-none">
                    <div class="row g-2 mb-3">
                        <div class="col-6 col-md-3">
                            <div class="border rounded-3 p-2 text-center h-100 bg-light">
                                <div class="small text-muted fw-bold font-size-11">OKUNAN SATIR</div>
                                <div class="fw-bold font-size-16" id="pdfSatirSayisi">0</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="border rounded-3 p-2 text-center h-100 bg-light">
                                <div class="small text-muted fw-bold font-size-11">SEÇİLİ</div>
                                <div class="fw-bold text-primary font-size-16" id="pdfSecilenSayisi">0</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="border rounded-3 p-2 text-center h-100 bg-light">
                                <div class="small text-muted fw-bold font-size-11">TOP. ALDIM</div>
                                <div class="fw-bold text-success font-size-16" id="pdfToplamAldim">0,00 ₺</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="border rounded-3 p-2 text-center h-100 bg-light">
                                <div class="small text-muted fw-bold font-size-11">TOP. VERDİM</div>
                                <div class="fw-bold text-danger font-size-16" id="pdfToplamVerdim">0,00 ₺</div>
                            </div>
                        </div>
                    </div>

                    <div id="pdfUyarilar"></div>

                    <div class="d-flex flex-wrap align-items-center gap-3 mb-2">
                        <div class="form-check mb-0">
                            <input class="form-check-input" type="checkbox" id="pdfTumunuSec" checked>
                            <label class="form-check-label small fw-bold" for="pdfTumunuSec">Tümünü seç</label>
                        </div>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" id="pdfMukerrerAtla" checked>
                            <label class="form-check-label small fw-bold" for="pdfMukerrerAtla">Mükerrer kayıtları atla</label>
                        </div>
                        <div class="ms-auto" style="min-width: 200px;">
                            <input type="text" id="pdfBelgeNo" class="form-control form-control-sm" maxlength="50" placeholder="Belge No (opsiyonel)">
                        </div>
                    </div>

                    <div class="table-responsive border rounded-3" style="max-height: 45vh; overflow-y: auto;">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light position-sticky top-0">
                                <tr>
                                    <th style="width: 40px;"></th>
                                    <th style="width: 110px;">Tarih</th>
                                    <th>Açıklama</th>
                                    <th class="text-end" style="width: 130px;">Aldım</th>
                                    <th class="text-end" style="width: 130px;">Verdim</th>
                                    <th class="text-center" style="width: 100px;">Durum</th>
                                </tr>
                            </thead>
                            <tbody id="pdfSatirlar"></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="modal-footer border-top-0 pt-2 pb-4 px-4 justify-content-end">
                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal" style="background:#6c757d; color:#fff; border-radius: 10px; border:none; font-weight: 600;">Kapat</button>
                <button type="button" class="btn btn-dark px-4 d-none" id="btnPdfAktar" style="background:#212529; color:#fff; border-radius: 10px; border:none; font-weight: 600;">İçe Aktar</button>
            </div>
        </div>
    </div>
</div>

<!-- Cari Notu Modalı -->
<div class="modal fade" id="cariNotuModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header border-bottom-0 pb-0 pt-4 px-4 align-items-center">
                <i class="bx bx-bookmark font-size-22 text-warning me-2"></i>
                <h5 class="modal-title fw-bold mb-0" style="color: #1a1a1a;">Cari Notu Düzenle</h5>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <form id="cariNotuForm">
                <input type="hidden" name="action" value="cari-not-kaydet">
                <input type="hidden" name="cari_id" value="<?= htmlspecialchars($cari_id_enc) ?>">
                <div class="modal-body px-4 pt-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted mb-1">Notlar / Özel Açıklama</label>
                        <textarea name="notlar" class="form-control" rows="6" style="border-radius: 12px; border: 1px solid #e2e8f0;" placeholder="Cari ile ilgili notu buraya yazın..."><?= htmlspecialchars($cariData->notlar ?: '') ?></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0 pb-4 px-4 justify-content-end gap-2">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal" style="background:#6c757d; color:#fff; border-radius: 10px; border:none; font-weight: 600;">İptal</button>
                    <button type="submit" class="btn btn-dark px-4 shadow-sm" style="background:#212529; color:#fff; border-radius: 10px; border:none; font-weight: 600;">Kaydet</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const global_cari_id = '<?= htmlspecialchars($cari_id_enc, ENT_QUOTES, 'UTF-8') ?>';
</script>
<script src="views/cari/js/hareketler.js?v=<?= time() ?>"></script>
