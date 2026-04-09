<?php
use App\Helper\Date;
use App\Helper\Form;
use App\Model\AracModel;

$AracModel = new AracModel();
$araclar = $AracModel->all();

$aracOptions = ['' => 'Tüm Araçlar'];
foreach ($araclar as $arac) {
    if ($arac->aktif_mi) {
        $aracOptions[$arac->id] = $arac->plaka . ' (' . ($arac->marka ?? '-') . ')';
    }
}

// Default values
$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('m');
$arac_id = $_GET['arac_id'] ?? '';

$yearOptions = [];
for ($y = date('Y'); $y >= 2020; $y--) {
    $yearOptions[$y] = $y;
}

$monthOptions = [];
for ($m = 1; $m <= 12; $m++) {
    $m_val = str_pad($m, 2, '0', STR_PAD_LEFT);
    $monthOptions[$m_val] = Date::monthName($m_val);
}
?>

<div class="container-fluid">
    <?php
    $maintitle = "Araç Takip";
    $title = "Araç Puantaj Raporu";
    ?>
    <?php include 'layouts/breadcrumb.php'; ?>

    <div class="row mb-3">
        <div class="col-12">
            <div class="card">
                <div class="card-body p-2">
                    <div class="accordion" id="filterAccordion">
                        <div class="accordion-item border-0">
                            <h2 class="accordion-header" id="headingOne">
                                <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#collapseOne" aria-expanded="false" aria-controls="collapseOne">
                                    <div class="d-flex align-items-center justify-content-between w-100 me-3">
                                        <div>
                                            <i class="bx bx-filter-alt me-2"></i> Filtreleme Seçenekleri
                                        </div>
                                        <div id="filterSummary" class="d-none d-md-flex gap-2">
                                            <!-- JS ile doldurulacak -->
                                        </div>
                                    </div>
                                </button>
                            </h2>
                        </div>
                    </div>

                    <div id="collapseOne" class="accordion-collapse collapse" aria-labelledby="headingOne"
                        data-bs-parent="#filterAccordion">
                        <div class="accordion-body pt-3">
                            <form method="GET" action="" id="filterForm">
                                <div class="row g-3">
                                    <div class="col-md-2">
                                        <?php echo Form::FormSelect2("year", $yearOptions, $year, "Yıl Seçiniz", "grid", "key", "", "form-select select2"); ?>
                                    </div>
                                    <div class="col-md-2">
                                        <?php echo Form::FormSelect2("month", $monthOptions, $month, "Ay Seçiniz", "grid", "key", "", "form-select select2"); ?>
                                    </div>
                                    <div class="col-md-3">
                                        <?php echo Form::FormSelect2("arac_id", $aracOptions, $arac_id, "Araç Seçiniz", "truck", "key", "", "form-select select2"); ?>
                                    </div>
                                    <div class="col-md-2 d-flex align-items-end">
                                        <button type="submit" class="btn btn-primary w-100 fw-bold">
                                            <i class="mdi mdi-magnify me-1"></i> Sorgula
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="d-flex align-items-center gap-3">
            <h5 class="mb-0">Araç Listesi</h5>
            <div class="form-check form-switch form-switch-md">
                <input class="form-check-input" type="checkbox" id="toggleKmCols">
                <label class="form-check-label fw-bold cursor-pointer" for="toggleKmCols">Bas. / Bit. KM Göster</label>
            </div>
        </div>
        <div class="action-button-container d-flex align-items-center border rounded shadow-sm p-1 gap-1">
            <button type="button"
                class="btn btn-link btn-sm text-primary text-decoration-none px-2 d-flex align-items-center"
                id="btnFullScreen">
                <i class="mdi mdi-fullscreen fs-5 me-1"></i> Tam Ekran
            </button>
            <div class="vr mx-1" style="height: 25px;"></div>
            <button type="button"
                class="btn btn-link btn-sm text-success text-decoration-none px-2 d-flex align-items-center"
                id="btnExportExcel">
                <i class="mdi mdi-file-excel fs-5 me-1"></i> Excel'e Aktar
            </button>
            <div class="vr mx-1" style="height: 25px;"></div>
            <button type="button"
                class="btn btn-link btn-sm text-warning text-decoration-none px-2 d-flex align-items-center"
                id="btnKmExcelYukle" data-bs-toggle="modal" data-bs-target="#kmExcelYukleModal">
                <i class="mdi mdi-upload fs-5 me-1"></i> Excel'den Yükle
            </button>
        </div>
    </div>

    <div class="row" id="reportCardRow">
        <div class="col-12">
            <div class="card">
                <div class="card-body p-0" id="reportContent" style="min-height: 200px;">
                    <div class="text-center p-5">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-2">Rapor hazırlanıyor...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Araç Özel Puantaj Modal -->
<div class="modal fade" id="aracOzelPuantajModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-body" id="aracOzelPuantajContent">
                <!-- AJAX ile dolacak -->
            </div>
        </div>
    </div>
</div>

<!-- KM Context Menu -->
<div id="kmContextMenu" class="ctx-premium-menu" style="display: none;">
    <div class="ctx-header-label">İşlemler</div>
    <div class="ctx-items">
        <button class="ctx-btn" type="button" id="ctxKmDuzenle">
            <i class="bx bx-edit-alt"></i> 
            <span>Düzenle</span>
        </button>
        <button class="ctx-btn ctx-btn-danger" type="button" id="ctxKmSil">
            <i class="bx bx-trash"></i> 
            <span>Sil</span>
        </button>
    </div>
</div>

<?php include_once "modal/km-excel-yukle-modal.php"; ?>

<style>
    .filter-summary-badge {
        display: flex;
        align-items: center;
        background: #2a3042;
        color: #fff;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 500;
        overflow: hidden;
        border: 1px solid #2a3042;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        transition: all 0.2s ease;
    }

    .filter-summary-badge:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.15);
    }

    .filter-summary-badge .badge-label {
        padding: 5px 8px;
        background: rgba(0, 0, 0, 0.2);
        color: rgba(255, 255, 255, 0.8);
        border-right: 1px solid rgba(255, 255, 255, 0.1);
        font-weight: 500;
    }

    .filter-summary-badge .badge-value {
        padding: 5px 10px;
        font-weight: 600;
        color: #fff;
    }

    [data-bs-theme="dark"] .filter-summary-badge {
        background: #32394e !important;
        border-color: #3b445e !important;
    }

    [data-bs-theme="dark"] .filter-summary-badge .badge-label {
        background: rgba(0, 0, 0, 0.3) !important;
    }

    .cursor-pointer {
        cursor: pointer;
    }

    .fullscreen-mode {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 9999;
        background: white;
        padding: 20px;
        overflow-y: auto;
    }

    /* KM Columns Toggle */
    .km-start-col,
    .km-end-col {
        transition: all 0.3s ease;
    }

    /* KM Context Menu Exact Match Design */
    .ctx-premium-menu {
        position: fixed;
        z-index: 100000;
        min-width: 156px;
        background: #ffffff;
        border: 1px solid rgba(0, 0, 0, 0.08);
        border-radius: 16px;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.06), 0 4px 6px -2px rgba(0, 0, 0, 0.04) !important;
        padding: 4px;
        user-select: none;
        overflow: hidden;
    }

    [data-bs-theme="dark"] .ctx-premium-menu {
        background: #232731;
        border-color: rgba(255, 255, 255, 0.1);
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5) !important;
    }

    .ctx-header-label {
        padding: 12px 14px 8px 14px;
        font-size: 11px;
        font-weight: 600;
        color: #94a3b8;
        letter-spacing: 0.1px;
    }

    [data-bs-theme="dark"] .ctx-header-label {
        color: #64748b;
    }

    .ctx-items {
        border-top: 1px solid rgba(0, 0, 0, 0.04);
        padding-top: 4px;
    }

    [data-bs-theme="dark"] .ctx-items {
        border-top-color: rgba(255, 255, 255, 0.05);
    }

    .ctx-btn {
        display: flex;
        align-items: center;
        width: 100%;
        padding: 9px 12px;
        border: none;
        background: transparent;
        color: #1e293b;
        font-size: 13.5px;
        font-weight: 500;
        text-align: left;
        border-radius: 10px;
        transition: all 0.15s ease;
        gap: 12px;
        cursor: pointer;
    }

    [data-bs-theme="dark"] .ctx-btn {
        color: #e2e8f0;
    }

    .ctx-btn:hover {
        background: #f8fafc;
        color: #0f172a;
    }

    [data-bs-theme="dark"] .ctx-btn:hover {
        background: rgba(255, 255, 255, 0.05);
        color: #ffffff;
    }

    .ctx-btn i {
        font-size: 19px;
        color: #475569;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    [data-bs-theme="dark"] .ctx-btn i {
        color: #94a3b8;
    }

    /* Danger State Match */
    .ctx-btn-danger {
        color: #f43f5e !important;
    }

    .ctx-btn-danger i {
        color: #f43f5e !important;
    }

    .ctx-btn-danger:hover {
        background: #fff1f2 !important;
        color: #e11d48 !important;
    }

    [data-bs-theme="dark"] .ctx-btn-danger:hover {
        background: rgba(244, 63, 94, 0.1) !important;
        color: #fb7185 !important;
    }

    /* Highlight cell on right click */
    .km-cell-active {
        background-color: rgba(59, 130, 246, 0.12) !important;
        box-shadow: inset 0 0 0 2px #3b82f6 !important;
    }
</style>

<script>
    $(document).ready(function () {
        let currentYear = '<?= $year ?>';
        let currentMonth = '<?= $month ?>';
        let currentAracId = '<?= $arac_id ?>';

        window.loadReport = function () {
            $('#reportContent').html('<div class="text-center p-5"><div class="spinner-border text-primary" role="status"></div><p class="mt-2">Rapor hazırlanıyor...</p></div>');
            updateFilterSummary();

            $.ajax({
                url: 'views/arac-takip/api.php',
                type: 'GET',
                data: {
                    action: 'get-arac-puantaj-table',
                    year: currentYear,
                    month: currentMonth,
                    arac_id: currentAracId
                },
                success: function (html) {
                    $('#reportContent').html(html);
                    applyKmToggle();

                    // Initialize tooltips
                    const tooltipTriggerList = [].slice.call(document.querySelectorAll('#reportContent [data-bs-toggle="tooltip"]'));
                    tooltipTriggerList.map(function (tooltipTriggerEl) {
                        return new bootstrap.Tooltip(tooltipTriggerEl);
                    });
                },
                error: function (xhr) {
                    console.error("Puantaj Error:", xhr.responseText);
                    $('#reportContent').html('<div class="alert alert-danger mb-0 m-3">' +
                        '<strong>Hata:</strong> Rapor yüklenirken bir sorun oluştu.<br>' +
                        '<small>' + (xhr.statusText || 'Sunucu hatası') + '</small>' +
                        '</div>');
                }
            });
        };

        const updateFilterSummary = function () {
            const yearText = $('select[name="year"] option:selected').text();
            const monthText = $('select[name="month"] option:selected').text();
            const aracText = $('select[name="arac_id"] option:selected').text();

            let summary = `<div class="filter-summary-badge"><span class="badge-label">Yıl:</span><span class="badge-value">${yearText}</span></div>`;
            summary += `<div class="filter-summary-badge"><span class="badge-label">Ay:</span><span class="badge-value">${monthText}</span></div>`;
            if (currentAracId) {
                summary += `<div class="filter-summary-badge"><span class="badge-label">Araç:</span><span class="badge-value">${aracText}</span></div>`;
            }

            $('#filterSummary').html(summary);
        };

        const applyKmToggle = function () {
            const isChecked = $('#toggleKmCols').is(':checked');
            if (isChecked) {
                $('.km-start-col, .km-end-col').removeClass('d-none');
                $('#puantajTable thead th[colspan]').attr('colspan', 3);
            } else {
                $('.km-start-col, .km-end-col').addClass('d-none');
                $('#puantajTable thead th[colspan]').attr('colspan', 1);
            }
        };

        $('#filterForm').on('submit', function (e) {
            e.preventDefault();
            currentYear = $('select[name="year"]').val();
            currentMonth = $('select[name="month"]').val();
            currentAracId = $('select[name="arac_id"]').val();
            loadReport();
            const collapseEl = document.getElementById('collapseOne');
            if (collapseEl) {
                const bsCollapse = bootstrap.Collapse.getInstance(collapseEl);
                if (bsCollapse) bsCollapse.hide();
            }
        });

        $('#toggleKmCols').on('change', function () {
            applyKmToggle();
        });

        $('#btnFullScreen').on('click', function () {
            const elem = document.getElementById('reportCardRow');
            if (!document.fullscreenElement) {
                elem.requestFullscreen().catch(err => {
                    alert(`Hata: ${err.message}`);
                });
                $(this).html('<i class="mdi mdi-fullscreen-exit fs-5 me-1"></i> Küçült');
                $(elem).addClass('fullscreen-mode');
            } else {
                document.exitFullscreen();
                $(this).html('<i class="mdi mdi-fullscreen fs-5 me-1"></i> Tam Ekran');
                $(elem).removeClass('fullscreen-mode');
            }
        });

        $(document).on('click', '.btn-arac-detay', function () {
            const id = $(this).data('id');
            const year = $('select[name="year"]').val();
            const month = $('select[name="month"]').val();

            // Modal içinde değişiklik yapılıp yapılmadığını takip et
            window._kmModalChanged = false;

            $('#aracOzelPuantajContent').html('<div class="text-center p-5"><div class="spinner-border text-primary" role="status"></div><p class="mt-2">Rapor hazırlanıyor...</p></div>');
            const modal = new bootstrap.Modal(document.getElementById('aracOzelPuantajModal'));
            modal.show();

            $.ajax({
                url: 'views/arac-takip/api.php',
                type: 'GET',
                data: {
                    action: 'get-arac-ozel-puantaj',
                    id: id,
                    year: year,
                    month: month
                },
                success: function (html) {
                    $('#aracOzelPuantajContent').html(html);

                    // Initialize tooltips
                    const tooltipTriggerList = [].slice.call(document.querySelectorAll('#aracOzelPuantajContent [data-bs-toggle="tooltip"]'));
                    tooltipTriggerList.map(function (tooltipTriggerEl) {
                        return new bootstrap.Tooltip(tooltipTriggerEl);
                    });
                },
                error: function (xhr) {
                    $('#aracOzelPuantajContent').html('<div class="alert alert-danger m-3">Hata: ' + xhr.responseText + '</div>');
                }
            });
        });

        // Modal kapandığında puantaj tablosunu yenile
        $('#aracOzelPuantajModal').on('hidden.bs.modal', function () {
            if (window._kmModalChanged) {
                loadReport();
            }
        });

        $('#btnExportExcel').on('click', function () {
            const year = $('select[name="year"]').val();
            const month = $('select[name="month"]').val();
            const arac_id = $('select[name="arac_id"]').val();
            const show_km = $('#toggleKmCols').is(':checked') ? 1 : 0;
            let url = 'views/arac-takip/export-excel.php?year=' + year + '&month=' + month + '&show_km=' + show_km;
            if (arac_id) {
                url += '&arac_id=' + arac_id;
            }
            window.location.href = url;
        });

        $(document).on('keyup', '.table-filter', function () {
            const filters = {};
            $('.table-filter').each(function () {
                const val = $(this).val().toLowerCase();
                const col = $(this).data('col');
                if (val) filters[col] = val;
            });

            $('#puantajTable tbody tr').each(function () {
                if ($(this).find('td').length < 3) return; // Kayıt bulunamadı satırı vb. için

                let show = true;
                for (const col in filters) {
                    const text = $(this).find('td').eq(col).text().toLowerCase();
                    if (text.indexOf(filters[col]) === -1) {
                        show = false;
                        break;
                    }
                }
                $(this).toggle(show);
            });
        });

        // Initial load
        loadReport();
    });
</script>

<script src="views/arac-takip/js/arac-takip.js"></script>