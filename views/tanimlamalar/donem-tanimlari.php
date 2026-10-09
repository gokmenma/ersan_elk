<?php

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Helper\Security;
use App\Helper\Form;
use App\Service\Gate;
use App\Model\SozlesmeDonemModel;

$maintitle = "Tanımlamalar";
$title = "Dönem Tanımları";

$SozlesmeDonem = new SozlesmeDonemModel();
$firma_id = (int) ($_SESSION['firma_id'] ?? 0);
$summary = $SozlesmeDonem->summary($firma_id);
?>

<script>
    try {
        document.documentElement.classList.toggle('donem-summary-hidden', localStorage.getItem('donem_summary_cards_state') === 'hidden');
    } catch (e) {}
</script>

<style>
    #summaryCardsContainer {
        overflow: hidden;
        max-height: 1100px;
        opacity: 1;
        transition: max-height .3s ease, opacity .3s ease, margin .3s ease;
    }
    .donem-summary-hidden #summaryCardsContainer {
        max-height: 0 !important;
        opacity: 0;
        margin-top: 0 !important;
        margin-bottom: 0 !important;
        pointer-events: none;
    }
    @media (prefers-reduced-motion: reduce) {
        #summaryCardsContainer { transition: none; }
    }

    /* Tablo Aksiyon Butonları */
    .table-action-btn {
        width: 28px;
        height: 28px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
        font-size: 14px;
        transition: all 0.2s ease;
    }

    .btn-subtle-success {
        background-color: rgba(40, 199, 111, 0.12);
        color: #28c76f;
        border: 1px solid rgba(40, 199, 111, 0.2);
    }
    .btn-subtle-success:hover {
        background-color: #28c76f;
        color: #fff;
    }

    .btn-subtle-primary {
        background-color: rgba(115, 103, 240, 0.12);
        color: #7367f0;
        border: 1px solid rgba(115, 103, 240, 0.2);
    }
    .btn-subtle-primary:hover {
        background-color: #7367f0;
        color: #fff;
    }

    .btn-subtle-warning {
        background-color: rgba(255, 159, 67, 0.12);
        color: #ff9f43;
        border: 1px solid rgba(255, 159, 67, 0.2);
    }
    .btn-subtle-warning:hover {
        background-color: #ff9f43;
        color: #fff;
    }

    .btn-subtle-danger {
        background-color: rgba(234, 84, 85, 0.12);
        color: #ea5455;
        border: 1px solid rgba(234, 84, 85, 0.2);
    }
    .btn-subtle-danger:hover {
        background-color: #ea5455;
        color: #fff;
    }

    /* KPI Kartları */
    .summary-kpi-card {
        border: 1px solid rgba(0,0,0,0.06);
        border-radius: 10px;
        box-shadow: 0 2px 6px rgba(0,0,0,0.02);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .summary-kpi-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    }
    .summary-kpi-label {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #6c757d;
    }
    .summary-kpi-icon {
        width: 34px;
        height: 34px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        font-size: 18px;
    }
    .summary-kpi-value {
        font-size: 1.4rem;
        font-weight: 700;
        line-height: 1.2;
    }
    .summary-kpi-subtext {
        font-size: 11px;
        color: #8c9097;
    }
    .summary-pill-btn {
        font-size: 11px;
        font-weight: 600;
        border: 1px solid transparent;
        transition: all 0.2s;
    }
    .summary-pill-btn.active {
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        font-weight: 700;
    }
    .badge-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        display: inline-block;
    }
</style>

<?php include 'layouts/breadcrumb.php'; ?>

<div class="container-fluid">
    <!-- 1. Üst Başlık ve Aksiyon Araç Çubuğu -->
    <div class="row align-items-center mb-3">
        <div class="col-md-6 col-12 d-flex align-items-center gap-3">
            <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 44px; height: 44px;">
                <i class="bx bx-calendar-event fs-4 text-primary"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold text-dark font-size-16">Dönem Tanımları</h4>
                <p class="text-muted mb-0 font-size-12">Sözleşme ve çalışma dönemleri, geçerlilik tarihleri ve aktif dönem yönetimi</p>
            </div>
        </div>

        <div class="personel-action-toolbar col-md-6 col-12 d-flex align-items-center justify-content-md-end gap-2 mt-2 mt-md-0">
            <!-- 1. Yeni Dönem Ekle Butonu -->
            <button type="button" class="btn btn-primary top-action-btn shadow-sm text-white" id="btnYeniDonem" data-bs-toggle="modal" data-bs-target="#modalDonem">
                <i class="bx bx-plus font-size-16"></i> Yeni Dönem Ekle
            </button>

            <!-- 2. İşlemler Dropdown -->
            <div class="dropdown d-inline-block">
                <button type="button" class="btn btn-outline-secondary bg-white top-action-btn dropdown-toggle shadow-sm" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="bx bx-cog font-size-16 text-primary"></i> İşlemler
                </button>
                <div class="dropdown-menu dropdown-menu-end shadow-lg border-0">
                    <button type="button" class="dropdown-item d-flex align-items-center" id="btnPrintTable">
                        <i class="bx bx-printer me-2 font-size-16 text-secondary"></i> Tabloyu Yazdır
                    </button>
                    <button type="button" class="dropdown-item d-flex align-items-center" id="btnRefreshTable">
                        <i class="bx bx-refresh me-2 font-size-16 text-primary"></i> Listeyi Yenile
                    </button>
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
        <!-- Kart 1: TOPLAM DÖNEM -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">TOPLAM DÖNEM</span>
                        <div class="summary-kpi-icon bg-primary-subtle text-primary border border-primary-subtle">
                            <i class="bx bx-layer"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-primary" id="kpi_toplam"><?php echo $summary->toplam_donem; ?></h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext" id="kpi_sub_toplam">Aktif: <?php echo $summary->aktif_sayi; ?> | Pasif: <?php echo $summary->pasif_sayi; ?></span>
                        <button type="button" class="btn btn-sm btn-subtle-primary rounded-pill px-2.5 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn active" data-status="all">
                            <i class="bx bx-list-ul"></i> Tümü
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kart 2: AKTİF DÖNEM -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">YÜRÜRLÜKTEKİ DÖNEM</span>
                        <div class="summary-kpi-icon bg-success-subtle text-success border border-success-subtle">
                            <i class="bx bx-check-shield"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-success font-size-15 text-truncate" id="kpi_aktif_adi" title="<?php echo htmlspecialchars($summary->aktif_donem_adi); ?>">
                        <?php echo htmlspecialchars($summary->aktif_donem_adi); ?>
                    </h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-success fw-semibold" id="kpi_aktif_tarih"><?php echo $summary->aktif_donem_tarih; ?></span>
                        <button type="button" class="btn btn-sm btn-subtle-success rounded-pill px-2.5 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-status="aktif">
                            <i class="bx bx-check"></i> Aktif
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kart 3: PASİF / GEÇMİŞ DÖNEMLER -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">PASİF DÖNEMLER</span>
                        <div class="summary-kpi-icon bg-secondary-subtle text-secondary border border-secondary-subtle">
                            <i class="bx bx-history"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-secondary" id="kpi_pasif"><?php echo $summary->pasif_sayi; ?></h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext" id="kpi_sub_pasif">Tamamlanmış / Pasif Kayıtlar</span>
                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-2.5 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-status="pasif">
                            <i class="bx bx-archive"></i> Pasifler
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kart 4: KALAN SÜRE / GEÇERLİLİK -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">AKTİF DÖNEM SÜRESİ</span>
                        <div class="summary-kpi-icon bg-warning-subtle text-warning border border-warning-subtle">
                            <i class="bx bx-time-five"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-dark" id="kpi_kalan_gun">
                        <?php 
                        if ($summary->aktif_kalan_gun > 0) {
                            echo $summary->aktif_kalan_gun . ' <span class="font-size-14 text-muted fw-normal">Gün Kaldı</span>';
                        } elseif ($summary->aktif_kalan_gun == 0) {
                            echo '<span class="text-warning">Bugün Son Gün</span>';
                        } else {
                            echo '<span class="text-danger font-size-15">Süre Doldu</span>';
                        }
                        ?>
                    </h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext">Sözleşme Bitiş Takibi</span>
                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill font-size-11 px-2">Yürürlükte</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. DataTables Liste Kartı -->
    <div class="card shadow-sm border-0" id="donemListCard">
        <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <div class="p-1.5 bg-primary-subtle text-primary rounded-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                    <i class="bx bx-list-ul font-size-18"></i>
                </div>
                <div>
                    <h5 class="card-title font-size-14 fw-bold mb-0 text-dark">Dönem Listesi</h5>
                    <p class="text-muted font-size-11 mb-0">Anlık filtreleme, sıralama ve aktif dönem belirleme tablosu</p>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary bg-white shadow-sm" id="btnHeaderPrint" title="Yazdır">
                    <i class="bx bx-printer font-size-14 me-1"></i> Yazdır
                </button>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="donemTable" class="table table-bordered table-hover nowrap align-middle w-100 mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center" style="width: 50px;" data-filter="none">Sıra</th>
                            <th data-filter="string">Dönem Adı</th>
                            <th class="text-center" style="width: 140px;" data-filter="date">Başlangıç Tarihi</th>
                            <th class="text-center" style="width: 140px;" data-filter="date">Bitiş Tarihi</th>
                            <th class="text-center" style="width: 160px;" data-filter="none">Toplam Süre</th>
                            <th class="text-center" style="width: 130px;" data-filter="select">Durum</th>
                            <th class="text-center" style="width: 110px;" data-filter="none">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- DataTables ServerSide Render -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- 4. Ekle / Düzenle Modal -->
<div class="modal fade" id="modalDonem" tabindex="-1" aria-labelledby="modalDonemTitle" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
            <div class="modal-header bg-light py-3 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <div class="p-2 bg-primary-subtle text-primary rounded-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                        <i class="bx bx-calendar-edit fs-5"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold font-size-15 text-dark" id="modalDonemTitle">Yeni Dönem Tanımla</h5>
                        <p class="text-muted font-size-11 mb-0">Sözleşme/çalışma dönemi bilgilerini giriniz</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>

            <form id="formDonem" autocomplete="off">
                <input type="hidden" name="action" value="donem-kaydet">
                <input type="hidden" name="id" id="donem_id" value="">
                <input type="hidden" name="raw_id" id="donem_raw_id" value="0">

                <div class="modal-body p-4">
                    <!-- Dönem Adı -->
                    <div class="mb-3">
                        <?php echo Form::FormFloatInput('text', 'donem_adi', '', 'Örn: 2024-2026 1. Dönem Sözleşmesi', 'Dönem Adı *', 'bx bx-rename', 'form-control', true); ?>
                    </div>

                    <!-- Tarih Aralığı -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <?php echo Form::FormDate('baslangic_tarihi', '', 'Başlangıç Tarihi *', 'bx bx-calendar', 'form-control flatpickr-donem'); ?>
                        </div>
                        <div class="col-md-6">
                            <?php echo Form::FormDate('bitis_tarihi', '', 'Bitiş Tarihi *', 'bx bx-calendar-check', 'form-control flatpickr-donem'); ?>
                        </div>
                    </div>

                    <!-- Aktiflik Seçimi -->
                    <div class="mb-3 p-3 bg-light-subtle rounded-3 border">
                        <div class="form-check form-switch d-flex align-items-center justify-content-between p-0 m-0">
                            <label class="form-check-label fw-semibold font-size-13 mb-0 cursor-pointer" for="is_active">
                                Bu Dönemi Aktif Yap
                                <span class="d-block font-size-11 text-muted fw-normal mt-0.5">Aktif yapıldığında diğer tüm dönemler otomatik pasife alınır</span>
                            </label>
                            <input class="form-check-input ms-0 cursor-pointer" type="checkbox" role="switch" id="is_active" name="is_active" value="1" style="width: 2.2em; height: 1.2em;">
                        </div>
                    </div>

                    <!-- Açıklama / Not -->
                    <div class="mb-0">
                        <?php echo Form::FormFloatTextarea('aciklama', '', 'Dönem ile ilgili ek açıklamalar...', 'Açıklama / Notlar', 'bx bx-notepad', 'form-control', false, '95px', 3); ?>
                    </div>
                </div>

                <div class="modal-footer bg-light py-2.5 border-top">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Vazgeç</button>
                    <button type="submit" class="btn btn-primary px-4" id="btnSaveDonem">
                        <i class="bx bx-save me-1"></i> Kaydet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    let currentStatusFilter = 'all';

    // 1. Özet Kartları Aç/Kapa
    const summaryCardsContainer = document.getElementById('summaryCardsContainer');
    const btnToggleSummaryCards = document.getElementById('btnToggleSummaryCards');
    const storageKey = 'donem_summary_cards_state';

    function setSummaryCardsVisibility(visible) {
        if (!summaryCardsContainer || !btnToggleSummaryCards) return;
        document.documentElement.classList.toggle('donem-summary-hidden', !visible);
        localStorage.setItem(storageKey, visible ? 'visible' : 'hidden');
        btnToggleSummaryCards.setAttribute('aria-expanded', visible ? 'true' : 'false');
        btnToggleSummaryCards.innerHTML = visible ? '<i class="bx bx-chevron-up"></i>' : '<i class="bx bx-chevron-down"></i>';
    }

    if (btnToggleSummaryCards) {
        const isHidden = document.documentElement.classList.contains('donem-summary-hidden');
        setSummaryCardsVisibility(!isHidden);

        btnToggleSummaryCards.addEventListener('click', function() {
            const currentlyHidden = document.documentElement.classList.contains('donem-summary-hidden');
            setSummaryCardsVisibility(currentlyHidden);
        });
    }

    // 2. DataTables Başlatma
    const tableElement = $('#donemTable');
    let dtTable = null;

    if (typeof applyLengthStateSave === 'function') {
        dtTable = tableElement.DataTable(applyLengthStateSave({
            ...getDatatableOptions(),
            processing: true,
            serverSide: true,
            ajax: {
                url: 'views/tanimlamalar/api.php',
                type: 'POST',
                data: function(d) {
                    d.action = 'donem-liste';
                    d.status_filter = currentStatusFilter;
                }
            },
            columns: [
                { data: 'sira', orderable: false, searchable: false },
                { data: 'donem_adi' },
                { data: 'baslangic_tarihi' },
                { data: 'bitis_tarihi' },
                { data: 'toplam_gun', orderable: false, searchable: false },
                { data: 'durum' },
                { data: 'actions', orderable: false, searchable: false }
            ],
            order: [[2, 'desc']],
            pageLength: 25,
            language: {
                url: 'assets/js/datatables/tr.json'
            }
        }));
    } else {
        dtTable = tableElement.DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: 'views/tanimlamalar/api.php',
                type: 'POST',
                data: function(d) {
                    d.action = 'donem-liste';
                    d.status_filter = currentStatusFilter;
                }
            },
            columns: [
                { data: 'sira', orderable: false, searchable: false },
                { data: 'donem_adi' },
                { data: 'baslangic_tarihi' },
                { data: 'bitis_tarihi' },
                { data: 'toplam_gun', orderable: false, searchable: false },
                { data: 'durum' },
                { data: 'actions', orderable: false, searchable: false }
            ],
            order: [[2, 'desc']],
            pageLength: 25
        });
    }

    // 3. Hızlı Durum Filtreleri (Tümü / Aktif / Pasif)
    $('.status-quick-filter').on('click', function() {
        $('.status-quick-filter').removeClass('active');
        $(this).addClass('active');
        currentStatusFilter = $(this).data('status');
        dtTable.ajax.reload();
    });

    // 4. KPI Özet Kartlarını Güncelle
    function refreshSummary() {
        $.post('views/tanimlamalar/api.php', { action: 'donem-ozet' }, function(res) {
            if (res && res.status === 'success' && res.summary) {
                const s = res.summary;
                $('#kpi_toplam').text(s.toplam_donem);
                $('#kpi_sub_toplam').text('Aktif: ' + s.aktif_sayi + ' | Pasif: ' + s.pasif_sayi);
                $('#kpi_aktif_adi').text(s.aktif_donem_adi).attr('title', s.aktif_donem_adi);
                $('#kpi_aktif_tarih').text(s.aktif_donem_tarih);
                $('#kpi_pasif').text(s.pasif_sayi);
                
                let kalanHtml = '';
                if (s.aktif_kalan_gun > 0) {
                    kalanHtml = s.aktif_kalan_gun + ' <span class="font-size-14 text-muted fw-normal">Gün Kaldı</span>';
                } else if (s.aktif_kalan_gun === 0) {
                    kalanHtml = '<span class="text-warning">Bugün Son Gün</span>';
                } else {
                    kalanHtml = '<span class="text-danger font-size-15">Süre Doldu</span>';
                }
                $('#kpi_kalan_gun').html(kalanHtml);
            }
        }, 'json');
    }

    // Flatpickr Başlatma
    let fpBaslangic = null;
    let fpBitis = null;
    if (typeof flatpickr !== 'undefined') {
        const trLocale = (typeof flatpickr.l10ns !== 'undefined' && flatpickr.l10ns.tr) ? flatpickr.l10ns.tr : {};
        fpBaslangic = flatpickr("#baslangic_tarihi", {
            dateFormat: "Y-m-d",
            altInput: true,
            altFormat: "d.m.Y",
            locale: trLocale,
            allowInput: true
        });
        fpBitis = flatpickr("#bitis_tarihi", {
            dateFormat: "Y-m-d",
            altInput: true,
            altFormat: "d.m.Y",
            locale: trLocale,
            allowInput: true
        });
    }

    // 5. Yeni Dönem Butonu
    $('#btnYeniDonem').on('click', function() {
        $('#formDonem')[0].reset();
        $('#donem_id').val('');
        $('#donem_raw_id').val('0');
        $('#modalDonemTitle').text('Yeni Dönem Tanımla');
        $('#is_active').prop('checked', false);
        if (fpBaslangic) fpBaslangic.clear();
        if (fpBitis) fpBitis.clear();
    });

    // 6. Düzenleme Butonu
    $(document).on('click', '.btn-edit-donem', function() {
        const encId = $(this).data('id');
        const rawId = $(this).data('raw-id');

        $.post('views/tanimlamalar/api.php', { action: 'donem-getir', id: encId, raw_id: rawId }, function(res) {
            if (res && res.status === 'success' && res.data) {
                const d = res.data;
                $('#donem_id').val(d.id);
                $('#donem_raw_id').val(d.raw_id);
                $('#donem_adi').val(d.donem_adi);
                
                if (fpBaslangic && d.baslangic_tarihi) {
                    fpBaslangic.setDate(d.baslangic_tarihi, true);
                } else {
                    $('#baslangic_tarihi').val(d.baslangic_tarihi);
                }

                if (fpBitis && d.bitis_tarihi) {
                    fpBitis.setDate(d.bitis_tarihi, true);
                } else {
                    $('#bitis_tarihi').val(d.bitis_tarihi);
                }

                $('#is_active').prop('checked', d.is_active == 1);
                $('#aciklama').val(d.aciklama || '');

                $('#modalDonemTitle').text('Dönemi Düzenle');
                $('#modalDonem').modal('show');
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Hata!',
                    text: res.message || 'Dönem bilgileri getirilemedi.'
                });
            }
        }, 'json');
    });

    // 7. Form Gönderimi (Ekle/Güncelle)
    $('#formDonem').on('submit', function(e) {
        e.preventDefault();
        const btn = $('#btnSaveDonem');
        btn.prop('disabled', true).html('<i class="bx bx-loader-alt bx-spin me-1"></i> Kaydediliyor...');

        $.ajax({
            url: 'views/tanimlamalar/api.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(res) {
                btn.prop('disabled', false).html('<i class="bx bx-save me-1"></i> Kaydet');
                if (res && res.status === 'success') {
                    $('#modalDonem').modal('hide');
                    Swal.fire({
                        icon: 'success',
                        title: 'Başarılı!',
                        text: res.message,
                        timer: 1500,
                        showConfirmButton: false
                    });
                    dtTable.ajax.reload(null, false);
                    refreshSummary();
                    // Topbar dönem listesini de güncelle (varsa)
                    if (typeof window.refreshTopbarDonemSelector === 'function') {
                        window.refreshTopbarDonemSelector();
                    }
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Hata!',
                        text: res.message || 'Kayıt sırasında bir hata oluştu.'
                    });
                }
            },
            error: function() {
                btn.prop('disabled', false).html('<i class="bx bx-save me-1"></i> Kaydet');
                Swal.fire({
                    icon: 'error',
                    title: 'Hata!',
                    text: 'Sunucu ile iletişim kurulurken bir hata oluştu.'
                });
            }
        });
    });

    // 8. Dönemi Aktif Yap
    $(document).on('click', '.btn-set-active', function() {
        const encId = $(this).data('id');
        const rawId = $(this).data('raw-id');
        const donemAdi = $(this).data('name');

        Swal.fire({
            title: 'Aktif Dönem Yapılsın mı?',
            html: `<strong>"${donemAdi}"</strong> aktif dönem olarak belirlenecektir.<br><small class="text-muted">Diğer tüm dönemler otomatik olarak pasife alınacaktır.</small>`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28c76f',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="bx bx-check me-1"></i> Evet, Aktif Yap',
            cancelButtonText: 'Vazgeç'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('views/tanimlamalar/api.php', { action: 'donem-aktif-yap', id: encId, raw_id: rawId }, function(res) {
                    if (res && res.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Aktif Edildi!',
                            text: res.message,
                            timer: 1500,
                            showConfirmButton: false
                        });
                        dtTable.ajax.reload(null, false);
                        refreshSummary();
                        if (typeof window.refreshTopbarDonemSelector === 'function') {
                            window.refreshTopbarDonemSelector();
                        }
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Hata!',
                            text: res.message || 'İşlem gerçekleştirilemedi.'
                        });
                    }
                }, 'json');
            }
        });
    });

    // 9. Dönem Sil
    $(document).on('click', '.btn-delete-donem', function() {
        const encId = $(this).data('id');
        const rawId = $(this).data('raw-id');
        const donemAdi = $(this).data('name');

        Swal.fire({
            title: 'Dönemi Silmek İstiyor musunuz?',
            html: `<strong>"${donemAdi}"</strong> silinecektir.<br><small class="text-danger">Bu işlem geri alınabilir fakat önerilmez.</small>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ea5455',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="bx bx-trash me-1"></i> Evet, Sil',
            cancelButtonText: 'Vazgeç'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('views/tanimlamalar/api.php', { action: 'donem-sil', id: encId, raw_id: rawId }, function(res) {
                    if (res && res.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Silindi!',
                            text: res.message,
                            timer: 1500,
                            showConfirmButton: false
                        });
                        dtTable.ajax.reload(null, false);
                        refreshSummary();
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Silinemedi!',
                            text: res.message || 'Silme işlemi başarısız.'
                        });
                    }
                }, 'json');
            }
        });
    });

    // 10. Yenile & Yazdır Butonları
    $('#btnRefreshTable, #btnDropdownRefresh').on('click', function() {
        dtTable.ajax.reload(null, false);
        refreshSummary();
    });

    $('#btnPrintTable, #btnHeaderPrint, #btnDropdownPrint').on('click', function() {
        window.print();
    });
});
</script>
