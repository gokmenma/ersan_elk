<?php
use App\Helper\Form;

$maintitle = 'Ana Sayfa';
$title = 'Sözleşme & Hakediş';

$aylar = [
    1 => 'Ocak',
    2 => 'Şubat',
    3 => 'Mart',
    4 => 'Nisan',
    5 => 'Mayıs',
    6 => 'Haziran',
    7 => 'Temmuz',
    8 => 'Ağustos',
    9 => 'Eylül',
    10 => 'Ekim',
    11 => 'Kasım',
    12 => 'Aralık'
];
?>
<script>try { document.documentElement.classList.toggle('sozlesme-summary-hidden', localStorage.getItem('sozlesme_summary_cards_state') === 'hidden'); } catch (e) {}</script>
<style>
#summaryCardsContainer { overflow: hidden; max-height: 1100px; opacity: 1; transition: max-height .3s ease, opacity .3s ease, margin .3s ease; }
.sozlesme-summary-hidden #summaryCardsContainer { max-height: 0 !important; opacity: 0; margin-top: 0 !important; margin-bottom: 0 !important; pointer-events: none; }
@media (prefers-reduced-motion: reduce) { #summaryCardsContainer { transition: none; } }
</style>

<?php include 'layouts/breadcrumb.php'; ?>

<div class="container-fluid">
    <!-- 1. Üst Başlık ve Aksiyon Araç Çubuğu (Cari Sayfası Standardı) -->
    <div class="row align-items-center mb-3">
        <div class="col-md-6 col-12 d-flex align-items-center gap-3">
            <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 44px; height: 44px;">
                <i class="bx bx-briefcase fs-4 text-primary"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold text-dark font-size-16">Sözleşmeler</h4>
                <p class="text-muted mb-0 font-size-12">Firma sözleşme listesi, keşif, hakediş ve süre yönetimi</p>
            </div>
        </div>
        
        <div class="personel-action-toolbar col-md-6 col-12 d-flex align-items-center justify-content-md-end gap-2 mt-2 mt-md-0">
            <!-- 1. Yeni Sözleşme Ekle Butonu -->
            <button type="button" class="btn btn-primary top-action-btn shadow-sm text-white" id="btnYeniSozlesme" data-bs-toggle="modal" data-bs-target="#yeniSozlesmeModal">
                <i class="bx bx-plus font-size-16"></i> Yeni Sözleşme
            </button>

            <!-- 2. Özet Kartları Açma/Kapama Butonu -->
            <button type="button" class="btn btn-outline-secondary bg-white top-icon-btn shadow-sm" id="btnToggleSummaryCards" title="Özet Kartları Göster/Gizle" aria-expanded="true">
                <i class="bx bx-chevron-up"></i>
            </button>
        </div>
    </div>

    <!-- 2. 4 Adet Minimal Özet KPI Kartı -->
    <div class="row g-3 mb-3 summary-cards-group" id="summaryCardsContainer">
        <!-- Kart 1: TOPLAM SÖZLEŞME -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">TOPLAM SÖZLEŞME</span>
                        <div class="summary-kpi-icon bg-primary-subtle text-primary border border-primary-subtle">
                            <i class="bx bx-briefcase"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1" id="stat_toplam_sozlesme">0</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext" id="stat_sub_durum">Aktif: 0 | Tamamlanan: 0</span>
                        <button type="button" class="btn btn-sm btn-subtle-primary rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn active" data-status="all">
                            <i class="bx bx-layer"></i> Tümü
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kart 2: AKTİF SÖZLEŞMELER -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">AKTİF SÖZLEŞMELER</span>
                        <div class="summary-kpi-icon bg-success-subtle text-success border border-success-subtle">
                            <i class="bx bx-trending-up"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-success" id="stat_aktif_bedel">0,00 ₺</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-success fw-semibold" id="stat_sub_aktif_sayi">0 Aktif Sözleşme</span>
                        <button type="button" class="btn btn-sm btn-subtle-success rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-status="aktif">
                            <i class="bx bx-check-circle"></i> Aktif
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kart 3: TAMAMLANANLAR -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">TAMAMLANANLAR</span>
                        <div class="summary-kpi-icon bg-info-subtle text-info border border-info-subtle">
                            <i class="bx bx-task"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-info" id="stat_tamamlanan_bedel">0,00 ₺</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-info fw-semibold" id="stat_sub_tamamlanan_sayi">0 Tamamlanan Sözleşme</span>
                        <button type="button" class="btn btn-sm btn-subtle-info rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-status="tamamlandi">
                            <i class="bx bx-check-double"></i> Tamamlanan
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kart 4: TOPLAM SÖZLEŞME HACMİ -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">TOPLAM SÖZLEŞME HACMİ</span>
                        <div class="summary-kpi-icon bg-warning-subtle text-warning border border-warning-subtle">
                            <i class="bx bx-wallet"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1" id="stat_toplam_bedel">0,00 ₺</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-muted" id="stat_bedel_durum_metni">Genel Sözleşme Hacmi</span>
                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold" id="stat_sozlesme_bilgi">
                            0 Sözleşme
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Standart DataTables Sözleşme Listesi Kartı -->
    <div class="card summary-kpi-card mb-3" id="sozlesmeListCard">
        <div class="card-header bg-transparent border-0 px-3 py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 38px; height: 38px;">
                    <i class="bx bx-list-ul font-size-20"></i>
                </div>
                <div>
                    <h5 class="card-title mb-0 font-size-14 fw-bold text-dark">Sözleşme Listesi</h5>
                    <p class="text-muted mb-0 font-size-12" style="margin-top: 2px;">Anlık arama, sütun filtreleme ve hakediş yönetimi</p>
                </div>
            </div>

            <!-- Sağ Araç Çubuğu -->
            <div class="d-flex align-items-center gap-2 ms-auto flex-wrap">
                <button type="button" class="btn btn-sm btn-subtle-secondary px-2.5 py-1.5 d-flex align-items-center gap-1 rounded-3 fw-semibold shadow-xs" id="btnHeaderPrint" title="Tabloyu Yazdır">
                    <i class="bx bx-printer font-size-15"></i> <span class="d-none d-sm-inline font-size-12">Yazdır</span>
                </button>
            </div>
        </div>

        <div class="card-body p-3 pt-0">
            <div class="table-responsive" style="overflow-x: auto !important;">
                <table id="sozlesmeTable" class="table table-bordered table-hover nowrap align-middle w-100 mb-0">
                    <thead class="table-light">
                        <tr>
                            <th data-filter="none" style="width: 50px;" class="text-center">SIRA</th>
                            <th data-filter="string">İDARE ADI</th>
                            <th data-filter="string">İŞİN ADI</th>
                            <th data-filter="date" class="text-center" style="width: 130px;">SÖZLEŞME TARİHİ</th>
                            <th data-filter="date" class="text-center" style="width: 130px;">BİTİŞ TARİHİ</th>
                            <th data-filter="number" class="text-end" style="width: 150px;">SÖZLEŞME BEDELİ</th>
                            <th data-filter="select" class="text-center" style="width: 120px;">DURUM</th>
                            <th data-filter="none" style="width: 120px;" class="text-center">İŞLEMLER</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
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
/* Modern Kart ve Tablo Stilleri */
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

.btn-subtle-info {
    background-color: #f0f9ff;
    color: #0284c7;
    border: 1px solid #bae6fd;
    transition: all 0.18s ease;
}
.btn-subtle-info:hover, .btn-subtle-info:focus, .btn-subtle-info.active {
    background-color: #0284c7 !important;
    color: #ffffff !important;
    border-color: #0284c7 !important;
    box-shadow: 0 2px 5px rgba(2, 132, 199, 0.25);
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
</style>

<!-- Yeni Sözleşme Modal -->
<div class="modal fade" id="yeniSozlesmeModal" tabindex="-1" aria-labelledby="yeniSozlesmeModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <form id="yeniSozlesmeForm" class="modal-content">
            <input type="hidden" name="id" id="sozlesme_id">
            <div class="modal-header bg-primary">
                <h5 class="modal-title text-white" id="yeniSozlesmeModalLabel">Sözleşme Tanımla/Düzenle</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Nav tabs -->
                <ul class="nav nav-tabs nav-tabs-custom nav-justified" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" data-bs-toggle="tab" href="#sozlesme-bilgileri-tab" role="tab">
                            <span class="d-block d-sm-none"><i class="fas fa-home"></i></span>
                            <span class="d-none d-sm-block">1. Sözleşme Bilgileri</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#birim-fiyat-tab" role="tab">
                            <span class="d-block d-sm-none"><i class="far fa-list-alt"></i></span>
                            <span class="d-none d-sm-block">2. Birim Fiyat Teklif Cetveli</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#fiyat-farki-tab" role="tab">
                            <span class="d-block d-sm-none"><i class="bx bx-calculator"></i></span>
                            <span class="d-none d-sm-block">3. Fiyat Farkı ve Kesintiler</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#is-artis-azalis-tab" role="tab">
                            <span class="d-block d-sm-none"><i class="bx bx-transfer-alt"></i></span>
                            <span class="d-none d-sm-block">4. İş Artış/Azalış İşlemleri</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#sure-uzatim-tab" role="tab">
                            <span class="d-block d-sm-none"><i class="bx bx-time-five"></i></span>
                            <span class="d-none d-sm-block">5. Süre Uzatımları</span>
                        </a>
                    </li>
                </ul>

                <!-- Tab panes -->
                <div class="tab-content p-3 text-muted">
                    <!-- SÖZLEŞME BİLGİLERİ TAB -->
                    <div class="tab-pane active" id="sozlesme-bilgileri-tab" role="tabpanel">
                        <div class="p-3">
                            <div class="row">
                                <!-- GRUP 1: GENEL BİLGİLER -->
                                <div class="col-md-12 mb-4">
                                    <h6 class="text-primary d-flex align-items-center mb-3 fw-bold">
                                        <i data-feather="info" class="me-2 text-primary" style="width: 18px;"></i>
                                        Genel İş ve İdare Bilgileri
                                    </h6>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <?= Form::FormFloatInput('text', 'idare_adi', '', 'T.C. KASKİ GENEL MÜDÜRLÜĞÜ', 'İdare Adı', icon: 'home', required: true) ?>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <?= Form::FormFloatInput('text', 'idare_baskanlik_adi', '', 'ABONE İŞLERİ DAİRE BAŞKANLIĞI', 'İdare Başkanlık Adı', icon: 'layers') ?>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <?= Form::FormFloatInput('text', 'isin_yuklenicisi', 'ER-SAN ELEKTRİK İNŞ. TAAH.TİC.LTD.ŞTİ.', 'Yüklenici Firma', 'İşin Yüklenicisi', icon: 'briefcase', required: true) ?>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <?= Form::FormFloatTextarea('yuklenici_adres', '', 'Firma Adresi', 'Yüklenici Adres', icon: 'map', rows: 2, minHeight: '60px') ?>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <?= Form::FormFloatInput('tel', 'yuklenici_tel', '', '05xx xxx xx xx', 'Yüklenici Tel', icon: 'phone') ?>
                                        </div>
                                        <div class="col-md-12">
                                            <?= Form::FormFloatTextarea('isin_adi', '', 'Sözleşmede geçen tam iş adı', 'İşin Adı', icon: 'file-text', required: true, rows: 2, minHeight: '80px') ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- GRUP 2: İHALE VE SÜRE BİLGİLERİ -->
                                <div class="col-md-7 border-end">
                                    <h6 class="text-info d-flex align-items-center mb-3 fw-bold">
                                        <i data-feather="calendar" class="me-2 text-info" style="width: 18px;"></i>
                                        İhale ve Süre Bilgileri
                                    </h6>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <?= Form::FormFloatInput('text', 'ihale_kayit_no', '', '2025/1219715 vb.', 'İhale Kayıt No', icon: 'hash') ?>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <?= Form::FormFloatInput('text', 'ihale_tarihi', '', '', 'İhale Tarihi', icon: 'calendar', class: 'form-control flatpickr') ?>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <?= Form::FormFloatInput('text', 'yer_teslim_tarihi', '', '', 'Yer Teslim Tarihi', icon: 'map-pin', class: 'form-control flatpickr') ?>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <?= Form::FormFloatInput('text', 'sozlesme_tarihi', '', '', 'Sözleşme Tarihi', icon: 'calendar', class: 'form-control flatpickr') ?>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <?= Form::FormFloatInput('text', 'isin_bitecegi_tarih', '', '', 'İşin Biteceği Tarih', icon: 'calendar', class: 'form-control flatpickr') ?>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <?= Form::FormFloatInput('number', 'isin_suresi', '', '422', 'İşin Süresi (Gün)', icon: 'clock') ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- GRUP 3: MALİ BİLGİLER VE DURUM -->
                                <div class="col-md-5">
                                    <h6 class="text-success d-flex align-items-center mb-3 fw-bold">
                                        <i data-feather="dollar-sign" class="me-2 text-success"
                                            style="width: 18px;"></i> Mali Bilgiler ve Durum
                                    </h6>
                                    <div class="mb-3">
                                        <?= Form::FormFloatInput('number', 'kesif_bedeli', '', '0.00', 'Keşif Bedeli (TL)', icon: 'dollar-sign', attributes: 'step="0.01"') ?>
                                    </div>
                                    <div class="mb-3">
                                        <?= Form::FormFloatInput('number', 'ihale_tenzilati', '', '0.168769', 'İhale Tenzilatı (%)', icon: 'percent', attributes: 'step="0.000001"') ?>
                                    </div>
                                    <div class="mb-3">
                                        <?= Form::FormFloatInput('number', 'sozlesme_bedeli', '', '0.00', 'Sözleşme Bedeli (TL)', icon: 'credit-card', required: true, attributes: 'step="0.01"') ?>
                                    </div>
                                    <div class="">
                                        <?= Form::FormSelect2('durum', [
                                            'aktif' => 'Aktif',
                                            'pasif' => 'Pasif',
                                            'tamamlandi' => 'Tamamlandı'
                                        ], 'aktif', 'Durum', icon: 'activity') ?>
                                    </div>
                                </div>

                                <div class="col-md-12 mt-4">
                                    <div class="accordion" id="accordionEkstra">
                                        <div class="accordion-item shadow-none border">
                                            <h2 class="accordion-header" id="headingEkstra">
                                                <button class="accordion-button collapsed fw-bold text-primary px-3 py-2 bg-light bg-opacity-50" type="button" data-bs-toggle="collapse" data-bs-target="#collapseEkstra" aria-expanded="false" aria-controls="collapseEkstra">
                                                    <i data-feather="info" class="me-2 text-primary" style="width: 18px;"></i> Excel Ön Kapak ve Geçici Kabul Bilgileri (İsteğe Bağlı)
                                                </button>
                                            </h2>
                                            <div id="collapseEkstra" class="accordion-collapse collapse" aria-labelledby="headingEkstra">
                                                <div class="accordion-body px-3 py-3 pb-0">
                                                    <div class="row">
                                                        <div class="col-md-6 mb-3">
                                                            <?= Form::FormFloatInput('text', 'yuzde_yirmi_fazla_is', '', 'Tarih ve Sayılı Onay/Karar', '% 20 Fazla İş (Onay/Karar No)', icon: 'file-plus') ?>
                                                        </div>
                                                        <div class="col-md-6 mb-3">
                                                            <?= Form::FormFloatInput('text', 'son_sure_uzatimi', '', '... Tarihi ve ... Sayılı', 'Son Süre Uzatımı (Olur vb.)', icon: 'clock') ?>
                                                        </div>
                                                        <div class="col-md-4 mb-3">
                                                            <?= Form::FormFloatInput('text', 'gecici_kabul_tarihi', '', '', 'Geçici Kabul Tarihi', icon: 'calendar', class: 'form-control flatpickr') ?>
                                                        </div>
                                                        <div class="col-md-4 mb-3">
                                                            <?= Form::FormFloatInput('text', 'gecici_kabul_itibar_tarihi', '', '', 'Kabul İtibar Tarihi', icon: 'calendar', class: 'form-control flatpickr') ?>
                                                        </div>
                                                        <div class="col-md-4 mb-3">
                                                            <?= Form::FormFloatInput('text', 'gecici_kabul_onanma_tarihi', '', '', 'Kabul Onanma Tarihi', icon: 'calendar', class: 'form-control flatpickr') ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-12 mt-4">
                                    <hr>
                                    <h6 class="text-secondary d-flex align-items-center mb-3 mt-3 fw-bold">
                                        <i data-feather="users" class="me-2 text-secondary" style="width: 18px;"></i>
                                        İmza Yetkilileri (Kontrol ve Onay)
                                    </h6>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <?= Form::FormFloatTextarea('kontrol_teskilati', '', "ÖMER FARUK YAŞAR - İDARİ İŞLER SORUMLUSU\nHARUN KAZANCI - OKUMA YÖNETİCİSİ", 'Kontrol Teşkilatı (İsim - Unvan)', icon: 'users', rows: 3, minHeight: '100px') ?>
                                            <small class="text-muted">Her yetkiliyi yeni bir satıra yazın</small>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="row">
                                                <div class="col-12 mb-3">
                                                    <?= Form::FormFloatInput('text', 'tasvip_eden', '', 'AHMET BOLAT', 'Tasvip Eden (İsim)', icon: 'user-check') ?>
                                                </div>
                                                <div class="col-12">
                                                    <?= Form::FormFloatInput('text', 'tasvip_eden_unvan', '', 'ABONE KOORDİNASYON ŞUBE MÜDÜRÜ', 'Tasvip Eden Unvanı', icon: 'award') ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="row">
                                                <div class="col-12 mb-3">
                                                    <?= Form::FormFloatInput('text', 'idare_onaylayan', '', 'KEMALETTİN GÜNEN', 'Tasdik Eden / Kesin Onaylayan (İsim)', icon: 'user') ?>
                                                </div>
                                                <div class="col-12">
                                                    <?= Form::FormFloatInput('text', 'idare_onaylayan_unvan', '', 'ABONE İŞLERİ DAİRE BAŞKANI', 'Tasdik Eden Unvanı', icon: 'award') ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- BİRİM FİYAT CETVELİ TAB -->
                    <div class="tab-pane" id="birim-fiyat-tab" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6>Birim Fiyat Teklif Cetveli (Sözleşme Kalemleri)</h6>
                            <button type="button" class="btn btn-sm btn-success" onclick="satirEkle()">
                                <i class="bx bx-plus me-1"></i> Yeni Satır Ekle
                            </button>
                        </div>
                        <div class="alert alert-warning mb-3">Milyon/Bin ayıracı kullanmayınız, ondalık kısımları
                            nokta (.) ile ayırınız (Örn: 1000.50).</div>
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm align-middle" id="birimFiyatTable">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 50px;">Sıra</th>
                                        <th style="width: 120px;">Poz No</th>
                                        <th>İşin Adı</th>
                                        <th style="width: 120px;">Ölçü Birimi</th>
                                        <th style="width: 120px;">Miktarı</th>
                                        <th style="width: 150px;">Teklif Edilen B.Fiyat</th>
                                        <th style="width: 150px;">Tutarı</th>
                                        <th style="width: 50px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="birimFiyatBody">
                                    <!-- Satırlar JS ile eklenecek -->
                                </tbody>
                                <tfoot>
                                    <tr class="table-light fw-bold">
                                        <td colspan="6" class="text-end">GENEL TOPLAM:</td>
                                        <td id="genelToplamTutar" class="text-end text-primary">0,00 ₺</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- FİYAT FARKI VE KESİNTİLER TAB -->
                    <div class="tab-pane" id="fiyat-farki-tab" role="tabpanel">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="alert alert-info">
                                    <i class="bx bx-info-circle me-1"></i> Sözleşme genelindeki standart katsayıları
                                    ve
                                    temel endeksleri buradan tanımlayınız. Bu değerler her yeni hakedişte otomatik
                                    olarak getirilecektir.
                                </div>
                            </div>
                            <div class="col-md-6 border-end">
                                <h6 class="mb-3 text-primary"><i data-feather="sliders" style="width:16px;height:16px"
                                        class="me-1"></i> Fiyat Farkı Katsayıları
                                    (P1)</h6>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <?= Form::FormFloatInput('number', 'a1_katsayisi', '0.28000', '0.28000', 'a1 (İşçilik) Katsayısı', icon: 'percent', attributes: 'step="0.000001"') ?>
                                    </div>
                                    <div class="col-md-6">
                                        <?= Form::FormFloatInput('number', 'b1_katsayisi', '0.22000', '0.22000', 'b1 (Motorin) Katsayısı', icon: 'percent', attributes: 'step="0.000001"') ?>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <?= Form::FormFloatInput('number', 'b2_katsayisi', '0.25000', '0.25000', 'b2 (Yİ-ÜFE) Katsayısı', icon: 'percent', attributes: 'step="0.000001"') ?>
                                    </div>
                                    <div class="col-md-6">
                                        <?= Form::FormFloatInput('number', 'c_katsayisi', '0.25000', '0.25000', 'c (Makine-Ekp) Katsayısı', icon: 'percent', attributes: 'step="0.000001"') ?>
                                    </div>
                                </div>

                                <h6 class="mt-4 mb-3 text-danger"><i data-feather="scissors"
                                        style="width:16px;height:16px" class="me-1"></i> Kesinti Oranları</h6>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <?= Form::FormFloatInput('number', 'kdv_orani', '20.00', '20.00', 'KDV Oranı (%)', icon: 'percent', attributes: 'step="0.01"') ?>
                                    </div>
                                    <div class="col-md-6">
                                        <?= Form::FormFloatInput('text', 'tevkifat_orani', '4/10', '4/10', 'Tevkifat Oranı (Örn: 4/10)', icon: 'divide-circle') ?>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <h6 class="mb-3 text-success"><i data-feather="calendar" style="width:16px;height:16px"
                                        class="me-1"></i> Temel (Sözleşme Ayı)
                                    Endeksleri (o)</h6>
                                <div class="row mb-3">
                                    <div class="col-md-7">
                                        <?= Form::FormSelect2('temel_endeks_ay', $aylar, '', 'Temel Endeks Ayı', icon: 'calendar', required: false) ?>
                                    </div>
                                    <div class="col-md-5">
                                        <?= Form::FormFloatInput('number', 'temel_endeks_yil', '', date('Y'), 'Temel Endeks Yılı', icon: 'calendar', required: false) ?>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <?= Form::FormFloatInput('number', 'asgari_ucret_temel', '', 'Örn: 26005.50', 'İşçilik (Asgari Ücret) - Io', icon: 'dollar-sign', attributes: 'step="0.000001"') ?>
                                </div>
                                <div class="mb-3">
                                    <?= Form::FormFloatInput('number', 'motorin_temel', '', 'Örn: 54.13308', 'Motorin Endeksi - Mo', icon: 'droplet', attributes: 'step="0.000001"') ?>
                                </div>
                                <div class="mb-3">
                                    <?= Form::FormFloatInput('number', 'ufe_genel_temel', '', 'Örn: 4632.89', 'Yİ-ÜFE Genel Endeksi - ÜFEo', icon: 'trending-up', attributes: 'step="0.000001"') ?>
                                </div>
                                <div class="mb-3">
                                    <?= Form::FormFloatInput('number', 'makine_ekipman_temel', '', 'Örn: 3319.76', 'Makine-Ekipman Endeksi - Eo', icon: 'tool', attributes: 'step="0.000001"') ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- İŞ ARTIŞ/AZALIŞ İŞLEMLERİ TAB -->
                    <div class="tab-pane" id="is-artis-azalis-tab" role="tabpanel">
                        <div id="revizyonYeniSozlesmeUyarisi" class="alert alert-warning mb-0">
                            <i class="bx bx-info-circle me-1"></i>
                            İş artış/azalış işlemleri sözleşme ve kalemleri ilk kez kaydedildikten sonra girilebilir.
                        </div>
                        <div id="revizyonAlani" class="d-none">
                            <div id="revizyonYeniFormu">
                            <div class="alert alert-info">
                                Her işlem ayrı bir revizyon olarak saklanır. Değişim alanına artış için pozitif,
                                azalış için negatif miktar giriniz. Kaydedilen revizyonlar geçmiş kaydı olarak korunur.
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-md-3">
                                    <label class="form-label">Revizyon Tarihi <span class="text-danger">*</span></label>
                                    <input type="text" id="revizyon_tarihi" class="form-control flatpickr"
                                        placeholder="GG.AA.YYYY">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Onay / Karar No</label>
                                    <input type="text" id="revizyon_karar_no" class="form-control"
                                        placeholder="Varsa karar veya onay no">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Açıklama</label>
                                    <input type="text" id="revizyon_aciklama" class="form-control"
                                        placeholder="Revizyonun kısa açıklaması">
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Poz No</th>
                                            <th>İş Kalemi</th>
                                            <th>Birim</th>
                                            <th class="text-end">Mevcut Miktar</th>
                                            <th style="width:160px">Artış / Azalış</th>
                                            <th class="text-end">Yeni Miktar</th>
                                            <th class="text-end">Tutar Farkı</th>
                                        </tr>
                                    </thead>
                                    <tbody id="revizyonKalemBody"></tbody>
                                    <tfoot>
                                        <tr class="table-light fw-bold">
                                            <td colspan="6" class="text-end">TOPLAM BEDEL DEĞİŞİMİ:</td>
                                            <td id="revizyonTutarFarki" class="text-end">0,00 ₺</td>
                                        </tr>
                                        <tr class="table-light fw-bold">
                                            <td colspan="6" class="text-end">TOPLAM ARTIŞ / AZALIŞ ORANI:</td>
                                            <td id="revizyonToplamOrani" class="text-end">%0,00</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            <div class="text-end mb-4">
                                <button type="button" class="btn btn-light me-1 d-none" id="revizyonFormKapatBtn"
                                    onclick="revizyonFormunuKapat()">Vazgeç</button>
                                <button type="button" class="btn btn-success" id="revizyonKaydetBtn"
                                    onclick="revizyonKaydet()">
                                    <i class="bx bx-save me-1"></i> İşlemi Kaydet
                                </button>
                            </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0">İş Artış/Azalış Geçmişi</h6>
                                <button type="button" id="revizyonYeniBtn" class="btn btn-primary btn-sm d-none"
                                    onclick="yeniRevizyonFormunuAc()">
                                    <i class="bx bx-plus me-1"></i> Yeni İş Artış/Azalışı
                                </button>
                            </div>
                            <div id="revizyonGecmisi"></div>
                        </div>
                    </div>

                    <!-- SÜRE UZATIMLARI TAB -->
                    <div class="tab-pane" id="sure-uzatim-tab" role="tabpanel">
                        <div id="sureUzatimYeniSozlesmeUyarisi" class="alert alert-warning mb-0">
                            <i class="bx bx-info-circle me-1"></i>
                            Süre uzatımı, sözleşme ilk kez kaydedildikten sonra eklenebilir.
                        </div>
                        <div id="sureUzatimAlani" class="d-none">
                            <div id="sureUzatimYeniFormu">
                            <div class="alert alert-info">
                                Her süre uzatımı ayrı kaydedilir. Yeni bitiş tarihi mevcut bitiş tarihine uzatım günü eklenerek hesaplanır.
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-md-3">
                                    <label class="form-label">Onay Tarihi <span class="text-danger">*</span></label>
                                    <input type="text" id="sure_uzatim_tarihi" class="form-control flatpickr" placeholder="GG.AA.YYYY">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Onay / Karar No</label>
                                    <input type="text" id="sure_uzatim_karar_no" class="form-control" placeholder="Varsa karar veya onay no">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Uzatım (Gün) <span class="text-danger">*</span></label>
                                    <input type="number" min="1" step="1" id="sure_uzatim_gun" class="form-control" oninput="sureUzatimHesapla()">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Açıklama</label>
                                    <input type="text" id="sure_uzatim_aciklama" class="form-control" placeholder="Süre uzatımının gerekçesi">
                                </div>
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <div class="border rounded p-3 bg-light">
                                        <small class="text-muted d-block">Mevcut Bitiş Tarihi</small>
                                        <strong id="sureUzatimMevcutBitis">-</strong>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="border rounded p-3 bg-light">
                                        <small class="text-muted d-block">Yeni Bitiş Tarihi</small>
                                        <strong id="sureUzatimYeniBitis" class="text-success">-</strong>
                                    </div>
                                </div>
                            </div>
                            <div class="text-end mb-4">
                                <button type="button" class="btn btn-light me-1 d-none" id="sureUzatimFormKapatBtn"
                                    onclick="sureUzatimFormunuKapat()">Vazgeç</button>
                                <button type="button" class="btn btn-success" id="sureUzatimKaydetBtn" onclick="sureUzatimKaydet()">
                                    <i class="bx bx-save me-1"></i> Süre Uzatımını Kaydet
                                </button>
                            </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0">Süre Uzatım Geçmişi</h6>
                                <button type="button" id="sureUzatimYeniBtn" class="btn btn-primary btn-sm d-none"
                                    onclick="yeniSureUzatimFormunuAc()">
                                    <i class="bx bx-plus me-1"></i> Yeni Süre Uzatımı
                                </button>
                            </div>
                            <div id="sureUzatimGecmisi"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer px-0 pb-0 mt-3">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                <button type="submit" class="btn btn-dark"><i class="bx bx-save me-1"></i> Sözleşmeyi Kaydet</button>
            </div>
        </form>
    </div>
</div>
