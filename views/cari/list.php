<?php
require_once dirname(__DIR__, 1) . '/../Autoloader.php';
use App\Helper\Form;
$maintitle = 'Ana Sayfa';
$title = 'Cari Yönetimi';

// İl, İlçe ve Ülke verilerini yükle
$edmJsonPath = dirname(__DIR__) . '/efatura/data/edm_kod_listesi.json';
$edmKodListesi = file_exists($edmJsonPath) ? json_decode(file_get_contents($edmJsonPath), true) : [];
$sehirler = $edmKodListesi['cities'] ?? [];
$ilceler = $edmKodListesi['districts'] ?? [];
$ulkeler = $edmKodListesi['countries'] ?? [];

$ilOptions = ['' => 'İl Seçiniz...'];
foreach ($sehirler as $sehir) {
    $ilOptions[$sehir] = $sehir;
}

$ulkeOptions = ['Türkiye' => 'Türkiye'];
foreach ($ulkeler as $u) {
    if (($u['name'] ?? '') !== 'Türkiye' && !empty($u['name'])) {
        $ulkeOptions[$u['name']] = $u['name'];
    }
}

$aliciTuruOptions = [
    'KURUMSAL' => 'Kurumsal Firma (Tüzel Kişi / VKN)',
    'BIREYSEL' => 'Bireysel Şahıs (Gerçek Kişi / TCKN)'
];

$belgeTuruOptions = [
    'OTOMATIK' => 'Otomatik Tespit (GİB Sorgusuna Göre)',
    'EFATURA'  => 'e-Fatura (GİB Kayıtlı)',
    'EARSIV'   => 'e-Arşiv Fatura'
];
?>
<script>try { document.documentElement.classList.toggle('cari-summary-hidden', localStorage.getItem('cari_summary_cards_state') === 'hidden'); } catch (e) {}</script>
<style>
#summaryCardsContainer { overflow: hidden; max-height: 1100px; opacity: 1; transition: max-height .3s ease, opacity .3s ease, margin .3s ease; }
.cari-summary-hidden #summaryCardsContainer { max-height: 0 !important; opacity: 0; margin-top: 0 !important; margin-bottom: 0 !important; pointer-events: none; }
@media (prefers-reduced-motion: reduce) { #summaryCardsContainer { transition: none; } }
</style>
<script>
    const EDM_DISTRICTS = <?= json_encode($ilceler, JSON_UNESCAPED_UNICODE) ?>;
</script>

<?php include 'layouts/breadcrumb.php'; ?>

<div class="container-fluid">
    <!-- 1. Üst Başlık ve Aksiyon Araç Çubuğu (Fatura Sayfası Formatı) -->
    <div class="row align-items-center mb-3">
        <div class="col-md-6 col-12 d-flex align-items-center gap-3">
            <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 44px; height: 44px;">
                <i class="bx bx-group fs-4 text-primary"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold text-dark font-size-16">Cari Hesaplar</h4>
                <p class="text-muted mb-0 font-size-12">Firmaya kayıtlı tüm cari hesapların listesi, mükellefiyet ve bakiye durumu</p>
            </div>
        </div>
        
        <div class="personel-action-toolbar col-md-6 col-12 d-flex align-items-center justify-content-md-end gap-2 mt-2 mt-md-0">
            <!-- 1. Yeni Cari Ekle Butonu (Canlı Mavi) -->
            <button type="button" class="btn btn-primary top-action-btn shadow-sm text-white" id="btnYeniCari">
                <i class="bx bx-plus font-size-16"></i> Yeni Cari Ekle
            </button>

            <!-- 2. İşlemler Dropdown -->
            <div class="dropdown d-inline-block">
                <button type="button" class="btn btn-outline-secondary bg-white top-action-btn dropdown-toggle shadow-sm" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="bx bx-cog font-size-16 text-primary"></i> İşlemler
                </button>
                <div class="dropdown-menu dropdown-menu-end shadow-lg border-0">
                    <button type="button" class="dropdown-item d-flex align-items-center" id="btnDropdownExportExcel">
                        <i class="bx bx-file me-2 font-size-16 text-success"></i> Excel'e Aktar
                    </button>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item d-flex align-items-center" href="index.php?p=efatura/olustur">
                        <i class="bx bx-receipt me-2 text-primary font-size-16"></i> Yeni Fatura Kes
                    </a>
                    <a class="dropdown-item d-flex align-items-center" href="index.php?p=efatura/taslak-list">
                        <i class="bx bx-edit-alt me-2 text-info font-size-16"></i> Taslak Faturalara Git
                    </a>
                    <a class="dropdown-item d-flex align-items-center" href="index.php?p=efatura/giden-list">
                        <i class="bx bx-cloud-upload me-2 text-primary font-size-16"></i> Giden Faturalara Git
                    </a>
                </div>
            </div>

            <!-- 3. Özet Kartları Açma/Kapama Butonu -->
            <button type="button" class="btn btn-outline-secondary bg-white top-icon-btn shadow-sm" id="btnToggleSummaryCards" title="Özet Kartları Göster/Gizle" aria-expanded="true">
                <i class="bx bx-chevron-up"></i>
            </button>
        </div>
    </div>

    <!-- 2. 4 Adet Minimal Özet KPI Kartı (Fatura Sayfaları Standardı) -->
    <div class="row g-3 mb-3 summary-cards-group" id="summaryCardsContainer">
        <!-- Kart 1: TOPLAM CARİ HESAP -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">TOPLAM CARİ HESAP</span>
                        <div class="summary-kpi-icon bg-primary-subtle text-primary border border-primary-subtle">
                            <i class="bx bx-group"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1" id="stat_toplam_cari">0</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext" id="stat_sub_borclu_alacakli">Borçlu: 0 | Alacaklı: 0</span>
                        <button type="button" class="btn btn-sm btn-subtle-primary rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn active" data-balance="all">
                            <i class="bx bx-layer"></i> Tümü
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kart 2: TOPLAM VERDİM (ALACAK) -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">TOPLAM VERDİM (ALACAK)</span>
                        <div class="summary-kpi-icon bg-success-subtle text-success border border-success-subtle">
                            <i class="bx bx-trending-up"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-success" id="toplam_alacak">0,00 ₺</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-success fw-semibold" id="stat_sub_alacakli_sayi">Alacaklı Hesaplar</span>
                        <button type="button" class="btn btn-sm btn-subtle-success rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-balance="alacakli">
                            <i class="bx bx-check-circle"></i> Alacaklı
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kart 3: TOPLAM ALDIM (BORÇ) -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">TOPLAM ALDIM (BORÇ)</span>
                        <div class="summary-kpi-icon bg-danger-subtle text-danger border border-danger-subtle">
                            <i class="bx bx-trending-down"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-danger" id="toplam_borc">0,00 ₺</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-danger fw-semibold" id="stat_sub_borclu_sayi">Borçlu Hesaplar</span>
                        <button type="button" class="btn btn-sm btn-subtle-danger rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-balance="borclu">
                            <i class="bx bx-minus-circle"></i> Borçlu
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kart 4: NET DURUM (GENEL BAKİYE) -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">NET DURUM (GENEL BAKİYE)</span>
                        <div class="summary-kpi-icon bg-warning-subtle text-warning border border-warning-subtle">
                            <i class="bx bx-wallet"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1" id="genel_bakiye">0,00 ₺</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-muted" id="stat_bakiye_durum_metni">Bakiye Dengesi</span>
                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold" id="bakiye_bilgi">
                            Net Bakiye
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Standart DataTables Cari Listesi Kartı (Fatura Formatı) -->
    <div class="card summary-kpi-card mb-3" id="cariListCard">
        <div class="card-header bg-transparent border-0 px-3 py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 38px; height: 38px;">
                    <i class="bx bx-list-ul font-size-20"></i>
                </div>
                <div>
                    <h5 class="card-title mb-0 font-size-14 fw-bold text-dark">Cari Hesap Listesi</h5>
                    <p class="text-muted mb-0 font-size-12" style="margin-top: 2px;">Anlık arama, sütun filtreleme ve bakiye yönetimi</p>
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
            </div>
        </div>

        <div class="card-body p-3 pt-0">
            <div class="table-responsive" style="overflow-x: auto !important;">
                <table id="cariTable" class="table table-bordered table-hover nowrap align-middle w-100 mb-0">
                    <thead class="table-light">
                        <tr>
                            <th data-filter="none" style="width: 50px;" class="text-center">SIRA</th>
                            <th data-filter="string">CARİ ADI</th>
                            <th data-filter="string">FİRMA / ÜNVAN</th>
                            <th data-filter="string" style="width: 120px;">VKN / TCKN</th>
                            <th data-filter="string" style="width: 120px;">TELEFON</th>
                            <th data-filter="string" style="width: 140px;">İL / İLÇE</th>
                            <th data-filter="number" class="text-end" style="width: 130px;">BAKİYE</th>
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
/* Tablo Tipografi ve Okunabilirlik İyileştirmeleri */
#cariTable {
    font-size: 13px !important;
}
#cariTable thead th {
    font-size: 11.5px !important;
    font-weight: 700 !important;
    color: #334155 !important;
    letter-spacing: 0.3px;
    background-color: #f8fafc !important;
    vertical-align: middle !important;
}
#cariTable tbody td {
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

/* Modal Nav Pills */
#cariModal .nav-pills {
    background: #f1f5f9;
}
#cariModal .nav-pills .nav-link {
    color: #64748b;
    border-radius: 8px;
    font-size: 13px;
    transition: all 0.2s ease;
}
#cariModal .nav-pills .nav-link:hover:not(.active) {
    background: #e2e8f0;
    color: #1e293b;
}
#cariModal .nav-pills .nav-link.active {
    background-color: #2563eb;
    color: #ffffff;
    box-shadow: 0 2px 6px rgba(37, 99, 235, 0.3);
}
</style>

<!-- Cari Ekle / Düzenle Modalı (Sekmeli Modern Yapı) -->
<div class="modal fade" id="cariModal" tabindex="-1" aria-labelledby="cariModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header border-bottom-0 pb-0 pt-4 px-4 align-items-start">
                <div class="d-flex align-items-center">
                    <div class="bg-success-subtle text-success rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 48px; height: 48px; background-color: #d1fae5;">
                        <i data-feather="plus-circle" style="width: 24px; height: 24px; color: #10b981;"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-1" id="cariModalLabel" style="color: #1a1a1a;">Yeni Cari Ekle</h5>
                        <p class="text-muted small mb-0">Cari kartı, e-Fatura/e-Arşiv ve vergi bilgilerini doldurun.</p>
                    </div>
                </div>
                <button type="button" class="btn-close mt-1" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            
            <form id="cariForm">
                <input type="hidden" name="action" value="cari-kaydet">
                <input type="hidden" name="cari_id" id="cari_id" value="">
                
                <div class="modal-body px-4 pt-3 pb-2">
                    <!-- Sekmeler (Nav Tabs: 1. Genel, 2. İletişim & Adres, 3. Fatura & Vergi) -->
                    <ul class="nav nav-pills nav-justified mb-3 p-1 bg-light rounded-3" id="cariModalTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active py-2 fw-semibold d-flex align-items-center justify-content-center gap-1" id="tab-genel-btn" data-bs-toggle="pill" data-bs-target="#tab-genel" type="button" role="tab" aria-selected="true">
                                <i class="bx bx-user font-size-16"></i> <span>Genel Bilgiler</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link py-2 fw-semibold d-flex align-items-center justify-content-center gap-1" id="tab-iletisim-btn" data-bs-toggle="pill" data-bs-target="#tab-iletisim" type="button" role="tab" aria-selected="false">
                                <i class="bx bx-map-pin font-size-16"></i> <span>İletişim & Adres</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link py-2 fw-semibold d-flex align-items-center justify-content-center gap-1" id="tab-fatura-btn" data-bs-toggle="pill" data-bs-target="#tab-fatura" type="button" role="tab" aria-selected="false">
                                <i class="bx bx-receipt font-size-16"></i> <span>Fatura & Vergi (GİB)</span>
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="cariModalTabsContent">
                        <!-- 1. GENEL BİLGİLER -->
                        <div class="tab-pane fade show active" id="tab-genel" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <?= Form::FormFloatInput("text", "CariAdi", "", "Cari Adı / Kısa Ad", "Cari Adı / Kısa Ad *", "user", "form-control fw-semibold", true) ?>
                                </div>
                                <div class="col-md-6">
                                    <?= Form::FormSelect2("alici_turu", $aliciTuruOptions, "KURUMSAL", "Cari Türü", "briefcase") ?>
                                </div>
                                <div class="col-12">
                                    <?= Form::FormFloatInput("text", "firma", "", "Firma / Resmi Ünvan", "Firma / Resmi Tam Ünvan", "home", "form-control") ?>
                                </div>
                                <div class="col-12">
                                    <?= Form::FormFloatTextarea("notlar", "", "Cari hakkında özel notlar, banka/IBAN veya açıklamalar...", "Özel Notlar / Açıklama", "file-text", "form-control", false, "90px") ?>
                                </div>
                            </div>
                        </div>

                        <!-- 2. İLETİŞİM & ADRES DETAYLARI -->
                        <div class="tab-pane fade" id="tab-iletisim" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <?= Form::FormFloatInput("text", "Telefon", "", "Telefon (05XX...)", "Telefon", "phone", "form-control") ?>
                                </div>
                                <div class="col-md-6">
                                    <?= Form::FormFloatInput("email", "Email", "", "E-posta Adresi", "E-posta", "mail", "form-control") ?>
                                </div>
                                <div class="col-md-6">
                                    <?= Form::FormFloatInput("text", "web_sitesi", "", "Web Sitesi (www...)", "Web Sitesi", "globe", "form-control") ?>
                                </div>
                                <div class="col-md-6">
                                    <?= Form::FormSelect2("ulke", $ulkeOptions, "Türkiye", "Ülke", "globe") ?>
                                </div>
                                <div class="col-md-4">
                                    <?= Form::FormSelect2("il", $ilOptions, "", "İl", "map") ?>
                                </div>
                                <div class="col-md-4">
                                    <?= Form::FormSelect2("ilce", ['' => 'İlçe Seçiniz...'], "", "İlçe", "map-pin") ?>
                                </div>
                                <div class="col-md-4">
                                    <?= Form::FormFloatInput("text", "posta_kodu", "", "Posta Kodu", "Posta Kodu", "archive", "form-control") ?>
                                </div>
                                <div class="col-12">
                                    <?= Form::FormFloatTextarea("Adres", "", "Cadde, sokak, bina no, kapı no vb. açık adres...", "Açık Adres", "map-pin", "form-control", false, "80px") ?>
                                </div>
                            </div>
                        </div>

                        <!-- 3. FATURA & VERGİ BİLGİLERİ (GİB / e-FATURA) -->
                        <div class="tab-pane fade" id="tab-fatura" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-7">
                                    <div class="input-group">
                                        <div class="form-floating form-floating-custom flex-grow-1">
                                            <input type="text" class="form-control fw-bold" id="vkn_tckn" name="vkn_tckn" maxlength="11" placeholder="10 veya 11 Haneli VKN/TCKN">
                                            <label for="vkn_tckn">Vergi No / TCKN</label>
                                            <div class="form-floating-icon">
                                                <i data-feather="hash"></i>
                                            </div>
                                        </div>
                                        <button type="button" class="btn btn-primary px-3 d-flex align-items-center justify-content-center shadow-xs" id="btnCariVknSorgula" title="GİB'de Sorgula" style="border-top-right-radius: 8px; border-bottom-right-radius: 8px;">
                                            <i class="bx bx-search font-size-18 me-1"></i> <span class="font-size-12 fw-semibold">Sorgula</span>
                                        </button>
                                    </div>
                                    <div class="field-help-text text-muted font-size-11 mt-1">
                                        Tüzel kişiler için 10 haneli VKN, şahıs firmaları için 11 haneli TCKN girin.
                                    </div>
                                </div>
                                <div class="col-md-5 d-flex align-items-center">
                                    <div id="cariMukellefBadge" class="w-100">
                                        <span class="badge bg-light text-muted border px-3 py-2 rounded-pill font-size-12 w-100 d-flex align-items-center justify-content-center gap-1">
                                            <i class="bx bx-info-circle"></i> VKN sorgulayabilirsiniz
                                        </span>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <?= Form::FormFloatInput("text", "vergi_dairesi", "", "Vergi Dairesi", "Vergi Dairesi", "briefcase", "form-control") ?>
                                </div>
                                <div class="col-md-6">
                                    <?= Form::FormSelect2("belge_turu", $belgeTuruOptions, "OTOMATIK", "Varsayılan Fatura Türü", "file") ?>
                                </div>

                                <div class="col-12" id="divCariPostaKutusu">
                                    <?= Form::FormFloatInput("text", "posta_kutusu", "", "GİB Posta Kutusu / GB Etiketi (örn: urn:mail:defaultgb@...)", "GİB Posta Kutusu (GB Etiketi)", "mail", "form-control font-monospace") ?>
                                </div>

                                <div class="col-md-6">
                                    <?= Form::FormFloatInput("text", "ticaret_sicil_no", "", "Ticaret Sicil No", "Ticaret Sicil No", "credit-card", "form-control") ?>
                                </div>
                                <div class="col-md-6">
                                    <?= Form::FormFloatInput("text", "mersis_no", "", "Mersis No", "Mersis No", "tag", "form-control") ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer border-top-0 pt-2 pb-4 px-4 justify-content-between">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal" style="background:#6c757d; color:#fff; border-radius: 10px; border:none; font-weight: 600;">İptal</button>
                    <button type="submit" class="btn btn-dark px-4 shadow-sm" style="background:#212529; color:#fff; border-radius: 10px; border:none; font-weight: 600;">
                        <i class="bx bx-save me-1"></i> Kaydet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Hızlı Hareket Ekle Modalı -->
<div class="modal fade" id="hizliIslemModal" tabindex="-1" aria-labelledby="hizliIslemModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header border-bottom-0 pb-0 pt-4 px-4 align-items-start">
                <div class="d-flex align-items-center w-100">
                    <div class="bg-primary-subtle rounded-circle d-flex align-items-center justify-content-center me-3" id="hizliIslemIconBg" style="width: 48px; height: 48px;">
                        <i class="bx bx-transfer font-size-24 text-primary" id="hizliIslemIcon"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h5 class="modal-title fw-bold mb-1" id="hizliIslemModalLabel" style="color: #1a1a1a;">Yeni İşlem Ekle</h5>
                        <p class="text-muted small mb-0" id="hizliIslemModalDesc">İşlem türünü seçin ve bilgileri girin.</p>
                    </div>
                </div>
                <button type="button" class="btn-close mt-1" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <form id="hizliIslemForm">
                <input type="hidden" name="action" value="hizli-hareket-kaydet">
                <input type="hidden" name="cari_id" id="hizli_islem_cari_id" value="">
                
                <div class="modal-body px-4 pt-4 pb-2">
                    <div class="mb-4 d-flex justify-content-center gap-2">
                        <input type="radio" class="btn-check" name="type" id="type_aldim" value="aldim" autocomplete="off" checked>
                        <label class="btn btn-outline-danger flex-grow-1 fw-bold" for="type_aldim"><i class="bx bx-minus-circle me-1"></i>Aldım</label>

                        <input type="radio" class="btn-check" name="type" id="type_verdim" value="verdim" autocomplete="off">
                        <label class="btn btn-outline-success flex-grow-1 fw-bold" for="type_verdim"><i class="bx bx-plus-circle me-1"></i>Verdim</label>
                    </div>

                    <div class="mb-3">
                        <?php 
                        echo Form::FormFloatInput("text", "islem_tarihi", date('Y-m-d H:i'), "Tarih", "Tarih", "calendar", "form-control flatpickr-time-input", true, null, "off", false); 
                        ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted mb-1">Tutar</label>
                        <?php echo Form::FormFloatInput("text", "tutar", "", "0.00", "İşlem Tutarı", "dollar-sign", "form-control money", true, null, "off", false, 'step="0.01" min="0.01"'); ?>
                    </div>
                    <div class="mb-3">
                        <?php echo Form::FormFloatInput("text", "belge_no", "", "Belge No", "Belge No", "hash", "form-control"); ?>
                    </div>
                    <div class="mb-3">
                        <?php echo Form::FormFloatTextarea("aciklama", "", "Açıklama giriniz...", "Açıklama", "list", "form-control", false, "80px"); ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted mb-1">Belge (Resim/PDF)</label>
                        <input type="file" name="dosya" class="form-control" accept="image/*,application/pdf">
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0 pb-4 px-4 justify-content-end">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal" style="background:#6c757d; color:#fff; border-radius: 10px; border:none; font-weight: 600;">İptal</button>
                    <button type="submit" class="btn btn-dark px-4" style="background:#212529; color:#fff; border-radius: 10px; border:none; font-weight: 600;">Kaydet</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="views/cari/js/cari.js?v=<?php echo time(); ?>"></script>
