<?php
\App\Service\Gate::authorizeOrDie('efatura/mal-hizmet-list');

use App\Helper\Form;
use App\Helper\Security;
use App\Config\EdmConfig;

$maintitle = 'E-Fatura & E-Arşiv';
$title = 'Mal/Hizmet Tanımları';

$unitCodes = EdmConfig::getUnitCodes();
$unitOptions = [];
foreach ($unitCodes as $k => $v) {
    $unitOptions[$k] = $v . ' (' . $k . ')';
}

$turOptions = [
    'MAL'    => 'Mal / Ticari Ürün',
    'HIZMET' => 'Hizmet / İşçilik / Faaliyet'
];

$paraBirimiOptions = [
    'TRY' => 'TRY - Türk Lirası (₺)',
    'USD' => 'USD - Amerikan Doları ($)',
    'EUR' => 'EUR - Euro (€)',
    'GBP' => 'GBP - İngiliz Sterlini (£)'
];

$kdvOptions = [
    '20.00' => '%20 (Standart Oran)',
    '10.00' => '%10 (İndirimli Oran)',
    '1.00'  => '%1 (Temel Gıda / Tarım)',
    '0.00'  => '%0 (İstisna / Muafiyet)'
];
?>
<script>try { document.documentElement.classList.toggle('efatura-mal-hizmet-summary-hidden', localStorage.getItem('efatura_mal_hizmet_summary_cards_state') === 'hidden'); } catch (e) {}</script>
<style>
#summaryCardsContainer { overflow: hidden; max-height: 1100px; opacity: 1; transition: max-height .3s ease, opacity .3s ease, margin .3s ease; }
.efatura-mal-hizmet-summary-hidden #summaryCardsContainer { max-height: 0 !important; opacity: 0; margin-top: 0 !important; margin-bottom: 0 !important; pointer-events: none; }
@media (prefers-reduced-motion: reduce) { #summaryCardsContainer { transition: none; } }
#efaturaMalHizmetTable tbody tr { cursor: pointer; }
#efaturaMalHizmetTable { border-bottom: 1px solid #e2e8f0 !important; }
#efaturaMalHizmetTable tbody tr:last-child td { border-bottom: 1px solid #e2e8f0 !important; }
.table-responsive { border-bottom: 1px solid #e2e8f0 !important; }
</style>
<meta name="csrf-token" content="<?= htmlspecialchars(Security::csrf(), ENT_QUOTES, 'UTF-8') ?>">

<?php include 'layouts/breadcrumb.php'; ?>

<div class="container-fluid">
    <!-- 1. Üst Başlık ve Aksiyon Araç Çubuğu -->
    <div class="row align-items-center mb-3">
        <div class="col-md-6 col-12 d-flex align-items-center gap-3">
            <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 44px; height: 44px;">
                <i class="bx bx-package fs-4 text-primary"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold text-dark font-size-16">Mal / Hizmet Tanımları</h4>
                <p class="text-muted mb-0 font-size-12">Faturalarda kullanılacak ürün, hizmet, birim, KDV ve fiyat kartları</p>
            </div>
        </div>
        
        <div class="personel-action-toolbar col-md-6 col-12 d-flex align-items-center justify-content-md-end gap-2 mt-2 mt-md-0">
            <!-- 1. Yeni Mal/Hizmet Ekle Butonu -->
            <button type="button" class="btn btn-primary top-action-btn shadow-sm text-white" id="btnYeniMalHizmet">
                <i class="bx bx-plus font-size-16"></i> Yeni Mal/Hizmet Ekle
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
                    <a class="dropdown-item d-flex align-items-center" href="index.php?p=efatura/cari-list">
                        <i class="bx bx-group me-2 text-info font-size-16"></i> Cari Listesi
                    </a>
                </div>
            </div>

            <!-- 3. Özet Kartları Açma/Kapama Butonu -->
            <button type="button" class="btn btn-outline-secondary bg-white top-icon-btn shadow-sm" id="btnToggleSummaryCards" title="Özet Kartları Göster/Gizle" aria-expanded="true">
                <i class="bx bx-chevron-up"></i>
            </button>
        </div>
    </div>

    <!-- 2. 4 Adet Minimal Özet KPI Kartı -->
    <div class="row g-3 mb-3 summary-cards-group" id="summaryCardsContainer">
        <!-- Kart 1: TOPLAM TANIM -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">TOPLAM MAL / HİZMET</span>
                        <div class="summary-kpi-icon bg-primary-subtle text-primary border border-primary-subtle">
                            <i class="bx bx-package"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1" id="stat_toplam_kalem">0</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext" id="stat_sub_mal_hizmet">Mal: 0 | Hizmet: 0</span>
                        <button type="button" class="btn btn-sm btn-subtle-primary rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn active" data-status="all">
                            <i class="bx bx-layer"></i> Tümü
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kart 2: TİCARİ MALLAR -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">TİCARİ MALLAR</span>
                        <div class="summary-kpi-icon bg-success-subtle text-success border border-success-subtle">
                            <i class="bx bx-box"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-success" id="stat_mal_sayisi">0</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-success fw-semibold">Fiziksel Ürünler</span>
                        <button type="button" class="btn btn-sm btn-subtle-success rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-status="mal">
                            <i class="bx bx-check-circle"></i> Mallar
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kart 3: HİZMETLER -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">HİZMET / İŞÇİLİK</span>
                        <div class="summary-kpi-icon bg-info-subtle text-info border border-info-subtle">
                            <i class="bx bx-wrench"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-info" id="stat_hizmet_sayisi">0</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-info fw-semibold">Hizmet & Faaliyetler</span>
                        <button type="button" class="btn btn-sm btn-subtle-info rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-status="hizmet">
                            <i class="bx bx-wrench"></i> Hizmetler
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kart 4: AKTİF KALEMLER -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">AKTİF TANIMLAR</span>
                        <div class="summary-kpi-icon bg-warning-subtle text-warning border border-warning-subtle">
                            <i class="bx bx-check-shield"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-warning" id="stat_aktif_kalem">0</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-warning fw-semibold" id="stat_pasif_kalem">Pasif: 0</span>
                        <button type="button" class="btn btn-sm btn-subtle-warning rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-status="aktif">
                            <i class="bx bx-check"></i> Aktifler
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. DataTables Liste Kartı -->
    <div class="card shadow-sm border-0 mb-4" id="efaturaMalHizmetListCard">
        <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <div class="p-2 bg-primary-subtle text-primary rounded-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                    <i class="bx bx-list-ul font-size-16"></i>
                </div>
                <div>
                    <h5 class="card-title mb-0 font-size-14 fw-bold">Mal ve Hizmet Listesi</h5>
                    <span class="text-muted font-size-11">Sistemdeki tüm kayıtlı ürün ve hizmet tanımları</span>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary bg-white shadow-sm" id="btnHeaderPrint" title="Yazdır">
                    <i class="bx bx-printer font-size-14"></i> Yazdır
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive p-3">
                <table id="efaturaMalHizmetTable" class="table table-bordered table-hover nowrap align-middle w-100 mb-0">
                    <thead class="table-light">
                        <tr>
                            <th data-filter="select" style="width: 90px;">Tür</th>
                            <th data-filter="string" style="width: 120px;">Stok Kodu</th>
                            <th data-filter="string">Mal / Hizmet Adı</th>
                            <th data-filter="select" style="width: 100px;">Birim</th>
                            <th data-filter="select" style="width: 80px;">Para Birimi</th>
                            <th data-filter="select" style="width: 80px;">KDV %</th>
                            <th data-filter="number" style="width: 110px;">Alış Fiyatı</th>
                            <th data-filter="number" style="width: 110px;">Satış Fiyatı</th>
                            <th data-filter="select" style="width: 80px;">Durum</th>
                            <th data-filter="none" class="text-center" style="width: 110px;">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================== -->
<!-- MAL / HİZMET EKLE / DÜZENLE MODALI                            -->
<!-- ============================================================== -->
<div class="modal fade" id="modalMalHizmet" tabindex="-1" aria-labelledby="modalMalHizmetLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 18px;">
            <div class="modal-header border-bottom-0 pb-0 pt-4 px-4 align-items-start">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width: 44px; height: 44px; background-color: #d1fae5; color: #10b981;">
                        <i class="bx bx-plus-circle fs-3 text-success" id="modalMalHizmetHeaderIcon"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0 text-dark" id="modalMalHizmetLabel">Yeni Mal / Hizmet Ekle</h5>
                        <p class="text-muted small mb-0">Yeni kayıt oluşturmak için bilgileri doldurun.</p>
                    </div>
                </div>
                <button type="button" class="btn-close mt-1" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            
            <form id="formMalHizmet" autocomplete="off">
                <div class="modal-body px-4 pt-3 pb-2">
                    <input type="hidden" name="enc_id" id="item_enc_id" value="">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Security::csrf(), ENT_QUOTES, 'UTF-8') ?>">

                    <!-- Sekmeler (Nav Pills) -->
                    <ul class="nav nav-pills nav-justified mb-3 p-1 rounded-3" id="malHizmetModalTabs" role="tablist" style="background: #f1f5f9;">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active py-2 fw-semibold d-flex align-items-center justify-content-center gap-1" id="tab-mh-genel-btn" data-bs-toggle="pill" data-bs-target="#tab-mh-genel" type="button" role="tab" aria-selected="true" style="border-radius: 8px;">
                                <i class="bx bx-package font-size-16"></i> <span>Genel & Fiyat Bilgileri</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link py-2 fw-semibold d-flex align-items-center justify-content-center gap-1" id="tab-mh-vergi-btn" data-bs-toggle="pill" data-bs-target="#tab-mh-vergi" type="button" role="tab" aria-selected="false" style="border-radius: 8px;">
                                <i class="bx bx-receipt font-size-16"></i> <span>Vergi & Kodlar</span>
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="malHizmetModalTabsContent">
                        <!-- 1. SEKME: GENEL & FİYAT BİLGİLERİ -->
                        <div class="tab-pane fade show active" id="tab-mh-genel" role="tabpanel">
                            <div class="row g-3">
                                <!-- Kayıt Türü -->
                                <div class="col-md-6 col-12">
                                    <?= Form::FormSelect2('tur', $turOptions, 'MAL', 'Kayıt Türü', 'bx bx-layer') ?>
                                </div>

                                <!-- Stok Kodu -->
                                <div class="col-md-6 col-12">
                                    <?= Form::FormFloatInput('text', 'stok_kodu', '', 'Örn: STK-001', 'Stok Kodu', 'bx bx-hash', 'form-control', false, 100) ?>
                                </div>

                                <!-- Mal / Hizmet Adı -->
                                <div class="col-12">
                                    <?= Form::FormFloatInput('text', 'urun_adi', '', 'Faturada görünecek ürün veya hizmet tam adı', 'Mal / Hizmet Adı *', 'bx bx-tag', 'form-control fw-semibold', true) ?>
                                </div>

                                <!-- Ölçü Birimi -->
                                <div class="col-md-6 col-12">
                                    <?= Form::FormSelect2('birim', $unitOptions, 'C62', 'Ölçü Birimi', 'bx bx-slider') ?>
                                </div>

                                <!-- Para Birimi -->
                                <div class="col-md-6 col-12">
                                    <?= Form::FormSelect2('para_birimi', $paraBirimiOptions, 'TRY', 'Para Birimi', 'bx bx-dollar') ?>
                                </div>

                                <!-- Alış Fiyatı (Solda Hizalı) -->
                                <div class="col-md-6 col-12">
                                    <?= Form::FormFloatInput('text', 'alis_fiyati', '', '0.00', 'Alış Fiyatı (Birim)', 'bx bx-trending-down', 'form-control') ?>
                                </div>

                                <!-- Satış Fiyatı (Solda Hizalı) -->
                                <div class="col-md-6 col-12">
                                    <?= Form::FormFloatInput('text', 'satis_fiyati', '', '0.00', 'Satış Fiyatı (Birim)', 'bx bx-trending-up', 'form-control fw-semibold') ?>
                                </div>
                            </div>
                        </div>

                        <!-- 2. SEKME: VERGİ, KODLAR & DETAYLAR -->
                        <div class="tab-pane fade" id="tab-mh-vergi" role="tabpanel">
                            <div class="row g-3">
                                <!-- KDV Oranı -->
                                <div class="col-md-6 col-12">
                                    <?= Form::FormSelect2('kdv_orani', $kdvOptions, '20.00', 'KDV Oranı', 'bx bx-purchase-tag-alt') ?>
                                </div>

                                <!-- Barkod -->
                                <div class="col-md-6 col-12">
                                    <?= Form::FormFloatInput('text', 'barkod', '', 'Barkod Numarası', 'Barkod', 'bx bx-barcode') ?>
                                </div>

                                <!-- GTİP No -->
                                <div class="col-12">
                                    <?= Form::FormFloatInput('text', 'gtip_no', '', 'Örn: 8471.30.00.00.00', 'GTİP No (Gümrük Tarife)', 'bx bx-globe') ?>
                                </div>

                                <!-- Switch Seçenekleri -->
                                <div class="col-md-6 col-12">
                                    <div class="p-3 bg-light rounded-3 border">
                                        <div class="form-check form-switch m-0 d-flex align-items-center">
                                            <input class="form-check-input" type="checkbox" name="kdv_dahil_mi" id="kdv_dahil_mi" value="1">
                                            <label class="form-check-label font-size-13 fw-semibold text-dark ms-2" for="kdv_dahil_mi">Fiyatlara KDV Dahildir</label>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6 col-12">
                                    <div class="p-3 bg-light rounded-3 border">
                                        <div class="form-check form-switch m-0 d-flex align-items-center">
                                            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" checked>
                                            <label class="form-check-label font-size-13 fw-semibold text-dark ms-2" for="is_active">Aktif Olarak Kullanımda</label>
                                        </div>
                                    </div>
                                </div>

                                <!-- Açıklama / Notlar -->
                                <div class="col-12">
                                    <?= Form::FormFloatTextarea('aciklama', '', 'Ürün veya hizmetle ilgili açıklamalar...', 'Açıklama / Notlar', 'bx bx-file-blank', 'form-control', false, '80px', 2) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer border-top-0 pt-2 pb-4 px-4 justify-content-between">
                    <button type="button" class="btn btn-secondary px-4 fw-semibold" data-bs-dismiss="modal" style="background:#6c757d; border-radius: 8px; border:none;">İptal</button>
                    <button type="submit" class="btn btn-dark px-4 fw-semibold shadow-sm" id="btnSaveMalHizmet" style="background:#1e293b; border-radius: 8px; border:none;">
                        <i class="bx bx-save me-1"></i> Kaydet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    const csrfToken = $('meta[name="csrf-token"]').attr('content');
    let currentStatusFilter = 'all';

    // 1. Özet Kartları Aç/Kapa
    $('#btnToggleSummaryCards').on('click', function() {
        const isHidden = document.documentElement.classList.toggle('efatura-mal-hizmet-summary-hidden');
        localStorage.setItem('efatura_mal_hizmet_summary_cards_state', isHidden ? 'hidden' : 'visible');
        $(this).find('i').toggleClass('bx-chevron-up bx-chevron-down');
        $(this).attr('aria-expanded', !isHidden);
    });

    if (localStorage.getItem('efatura_mal_hizmet_summary_cards_state') === 'hidden') {
        $('#btnToggleSummaryCards i').removeClass('bx-chevron-up').addClass('bx-chevron-down');
        $('#btnToggleSummaryCards').attr('aria-expanded', 'false');
    }

    // Modal içi Select2 başlatma fonksiyonu (Çift eleman oluşmasını önler)
    function initModalMalHizmetSelect2() {
        $('#modalMalHizmet select.select2').each(function() {
            const $select = $(this);
            if ($select.data('select2')) {
                $select.select2('destroy');
            }
            $select.siblings('.select2-container').remove();
            $select.select2({
                dropdownParent: $('#modalMalHizmet'),
                width: '100%'
            });
        });
    }

    // Modal açıldığında Select2 başlat
    $('#modalMalHizmet').on('shown.bs.modal', function() {
        initModalMalHizmetSelect2();
    });

    // Sekmeler arasında geçiş yapıldığında aktif sekmedeki Select2'leri tazele
    $('#malHizmetModalTabs button[data-bs-toggle="pill"]').on('shown.bs.tab', function() {
        initModalMalHizmetSelect2();
    });

    // 2. DataTables Başlatma
    const table = $('#efaturaMalHizmetTable').DataTable(applyLengthStateSave({
        ...getDatatableOptions(),
        processing: true,
        serverSide: true,
        ajax: {
            url: 'api/efatura-mal-hizmet-api.php',
            type: 'GET',
            data: function(d) {
                d.action = 'list';
                d.status_filter = currentStatusFilter;
            },
            error: function(xhr) {
                console.error('DataTables Load Error:', xhr);
            }
        },
        columns: [
            {
                data: 'tur',
                render: function(data) {
                    if (data === 'HIZMET') {
                        return '<span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold"><i class="bx bx-wrench me-1"></i>Hizmet</span>';
                    }
                    return '<span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold"><i class="bx bx-box me-1"></i>Mal</span>';
                }
            },
            {
                data: 'stok_kodu',
                render: function(data) {
                    return data ? '<span class="fw-semibold text-dark">' + escapeHtml(data) + '</span>' : '<span class="text-muted">-</span>';
                }
            },
            {
                data: 'urun_adi',
                render: function(data, type, row) {
                    let sub = row.barkod ? '<div class="text-muted font-size-11"><i class="bx bx-scan me-1"></i>' + escapeHtml(row.barkod) + '</div>' : '';
                    return '<div class="fw-bold text-dark font-size-13">' + escapeHtml(data) + '</div>' + sub;
                }
            },
            {
                data: 'birim',
                render: function(data) {
                    const units = <?= json_encode($unitCodes, JSON_UNESCAPED_UNICODE) ?>;
                    const label = units[data] || data;
                    return '<span class="badge bg-light text-secondary border font-size-11">' + escapeHtml(label) + '</span>';
                }
            },
            {
                data: 'para_birimi',
                render: function(data) {
                    return '<span class="badge bg-light text-dark border fw-bold font-size-11">' + escapeHtml(data || 'TRY') + '</span>';
                }
            },
            {
                data: 'kdv_orani',
                render: function(data) {
                    return '<span class="badge bg-secondary-subtle text-secondary font-size-11 fw-semibold">%' + parseFloat(data || 0).toFixed(0) + '</span>';
                }
            },
            {
                data: 'alis_fiyati',
                className: 'text-end',
                render: function(data, type, row) {
                    const num = parseFloat(data || 0);
                    return '<span class="text-muted font-size-12">' + formatMoney(num) + ' ' + (row.para_birimi || '₺') + '</span>';
                }
            },
            {
                data: 'satis_fiyati',
                className: 'text-end',
                render: function(data, type, row) {
                    const num = parseFloat(data || 0);
                    return '<span class="fw-bold text-dark font-size-13">' + formatMoney(num) + ' ' + (row.para_birimi || '₺') + '</span>';
                }
            },
            {
                data: 'is_active',
                render: function(data) {
                    if (parseInt(data) === 1) {
                        return '<span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">Aktif</span>';
                    }
                    return '<span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">Pasif</span>';
                }
            },
            {
                data: 'enc_id',
                orderable: false,
                searchable: false,
                className: 'text-center',
                render: function(data, type, row) {
                    return `
                        <div class="action-btn-group d-flex align-items-center justify-content-center gap-1">
                            <button type="button" class="btn btn-sm btn-subtle-warning table-action-btn btn-edit-item" data-id="${data}" title="Düzenle">
                                <i class="bx bx-edit font-size-14"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-subtle-danger table-action-btn btn-delete-item" data-id="${data}" data-name="${escapeHtml(row.urun_adi)}" title="Sil">
                                <i class="bx bx-trash font-size-14"></i>
                            </button>
                        </div>
                    `;
                }
            }
        ],
        order: [[2, 'asc']]
    }));

    // 3. KPI İstatistiklerini Yükle
    function loadSummaryStats() {
        $.getJSON('api/efatura-mal-hizmet-api.php?action=summary', function(res) {
            if (res.status === 'success' && res.data) {
                const d = res.data;
                $('#stat_toplam_kalem').text(d.toplam_kalem || 0);
                $('#stat_sub_mal_hizmet').text('Mal: ' + (d.mal_sayisi || 0) + ' | Hizmet: ' + (d.hizmet_sayisi || 0));
                $('#stat_mal_sayisi').text(d.mal_sayisi || 0);
                $('#stat_hizmet_sayisi').text(d.hizmet_sayisi || 0);
                $('#stat_aktif_kalem').text(d.aktif_kalem || 0);
                $('#stat_pasif_kalem').text('Pasif: ' + (d.pasif_kalem || 0));
            }
        });
    }
    loadSummaryStats();

    // 4. Hızlı Durum Filtre Butonları
    $('.status-quick-filter').on('click', function() {
        $('.status-quick-filter').removeClass('active');
        $(this).addClass('active');
        currentStatusFilter = $(this).data('status');
        table.ajax.reload();
    });

    // 5. Yeni Mal/Hizmet Ekle Butonu
    $('#btnYeniMalHizmet').on('click', function() {
        $('#formMalHizmet')[0].reset();
        $('#item_enc_id').val('');
        $('#modalMalHizmetLabel').text('Yeni Mal / Hizmet Ekle');
        $('#tab-mh-genel-btn').tab('show');
        initModalMalHizmetSelect2();
        $('#tur').val('MAL').trigger('change.select2');
        $('#birim').val('C62').trigger('change.select2');
        $('#para_birimi').val('TRY').trigger('change.select2');
        $('#kdv_orani').val('20.00').trigger('change.select2');
        $('#kdv_dahil_mi').prop('checked', false);
        $('#is_active').prop('checked', true);
        $('#modalMalHizmet').modal('show');
        if (typeof feather !== 'undefined') feather.replace();
    });

    // 6. Satıra Tıklandığında veya Düzenle Butonunda Modalı Aç
    $('#efaturaMalHizmetTable tbody').on('click', 'tr', function(e) {
        if ($(e.target).closest('.action-btn-group, button, a').length) return;
        const data = table.row(this).data();
        if (data && data.enc_id) {
            openEditModal(data.enc_id);
        }
    });

    $(document).on('click', '.btn-edit-item', function(e) {
        e.stopPropagation();
        const encId = $(this).data('id');
        openEditModal(encId);
    });

    function openEditModal(encId) {
        Swal.fire({
            title: 'Yükleniyor...',
            text: 'Mal/Hizmet bilgileri getiriliyor.',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        $.getJSON('api/efatura-mal-hizmet-api.php?action=get&id=' + encodeURIComponent(encId), function(res) {
            Swal.close();
            if (res.status === 'success' && res.data) {
                const d = res.data;
                $('#formMalHizmet')[0].reset();
                $('#item_enc_id').val(d.enc_id);
                $('#modalMalHizmetLabel').text('Mal / Hizmeti Düzenle: ' + d.urun_adi);
                $('#tab-mh-genel-btn').tab('show');
                initModalMalHizmetSelect2();

                $('#stok_kodu').val(d.stok_kodu || '');
                $('#urun_adi').val(d.urun_adi || '');
                $('#barkod').val(d.barkod || '');
                $('#gtip_no').val(d.gtip_no || '');
                $('#alis_fiyati').val(d.alis_fiyati || '0.00');
                $('#satis_fiyati').val(d.satis_fiyati || '0.00');
                $('#aciklama').val(d.aciklama || '');

                $('#tur').val(d.tur || 'MAL').trigger('change.select2');
                $('#birim').val(d.birim || 'C62').trigger('change.select2');
                $('#para_birimi').val(d.para_birimi || 'TRY').trigger('change.select2');
                $('#kdv_orani').val(parseFloat(d.kdv_orani || 20).toFixed(2)).trigger('change.select2');

                $('#kdv_dahil_mi').prop('checked', parseInt(d.kdv_dahil_mi) === 1);
                $('#is_active').prop('checked', parseInt(d.is_active) === 1);

                $('#modalMalHizmet').modal('show');
                if (typeof feather !== 'undefined') feather.replace();
            } else {
                Swal.fire('Hata', res.message || 'Kayıt bulunamadı.', 'error');
            }
        }).fail(function(xhr) {
            Swal.close();
            const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Sunucuyla bağlantı kurulamadı.';
            Swal.fire('Hata', msg, 'error');
        });
    }

    // 7. Formu Kaydet
    $('#formMalHizmet').on('submit', function(e) {
        e.preventDefault();
        const $btn = $('#btnSaveMalHizmet');
        $btn.prop('disabled', true).html('<i class="bx bx-loader-alt bx-spin"></i> Kaydediliyor...');

        const formData = $(this).serialize();
        const effectiveCsrf = $('input[name="csrf_token"]').val() || csrfToken;

        $.ajax({
            url: 'api/efatura-mal-hizmet-api.php?action=save',
            type: 'POST',
            data: formData,
            headers: { 'X-CSRF-Token': effectiveCsrf },
            dataType: 'json',
            success: function(res) {
                $btn.prop('disabled', false).html('<i class="bx bx-save me-1"></i> Kaydet');
                if (res.status === 'success') {
                    $('#modalMalHizmet').modal('hide');
                    Swal.fire({
                        icon: 'success',
                        title: 'Başarılı',
                        text: res.message || 'Kayıt başarıyla tamamlandı.',
                        timer: 1500,
                        showConfirmButton: false
                    });
                    table.ajax.reload(null, false);
                    loadSummaryStats();
                } else {
                    Swal.fire('Hata', res.message || 'Kayıt sırasında hata oluştu.', 'error');
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false).html('<i class="bx bx-save me-1"></i> Kaydet');
                const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Sunucuyla bağlantı hatası oluştu.';
                Swal.fire('Hata', msg, 'error');
            }
        });
    });

    // 8. Kayıt Silme
    $(document).on('click', '.btn-delete-item', function(e) {
        e.stopPropagation();
        const encId = $(this).data('id');
        const name = $(this).data('name') || 'Bu kayıt';
        const effectiveCsrf = $('input[name="csrf_token"]').val() || csrfToken;

        Swal.fire({
            title: 'Emin misiniz?',
            text: `"${name}" kaydı sistemden silinecektir.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Evet, Sil',
            cancelButtonText: 'Vazgeç'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'api/efatura-mal-hizmet-api.php?action=delete',
                    type: 'POST',
                    data: { id: encId, csrf_token: effectiveCsrf },
                    headers: { 'X-CSRF-Token': effectiveCsrf },
                    dataType: 'json',
                    success: function(res) {
                        if (res.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Silindi',
                                text: res.message || 'Kayıt başarıyla silindi.',
                                timer: 1500,
                                showConfirmButton: false
                            });
                            table.ajax.reload(null, false);
                            loadSummaryStats();
                        } else {
                            Swal.fire('Hata', res.message || 'Silme işlemi başarısız.', 'error');
                        }
                    },
                    error: function(xhr) {
                        const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Sunucu hatası oluştu.';
                        Swal.fire('Hata', msg, 'error');
                    }
                });
            }
        });
    });

    // 9. Yazdır ve Excel
    $('#btnHeaderPrint').on('click', function() {
        window.print();
    });

    $('#btnDropdownExportExcel').on('click', function() {
        table.button('.buttons-excel').trigger();
    });

    function formatMoney(amount) {
        return Number(amount || 0).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function escapeHtml(text) {
        if (!text) return '';
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }
});
</script>
