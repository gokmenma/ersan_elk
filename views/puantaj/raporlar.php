<?php
use App\Helper\Date;
use App\Helper\Form;
use App\Model\TanimlamalarModel;
use App\Model\EndeksOkumaModel;
use App\Model\PuantajModel;
use App\Model\PersonelModel;

$Tanimlamalar = new TanimlamalarModel();
$EndeksOkuma = new EndeksOkumaModel();
$Puantaj = new PuantajModel();
$Personel = new PersonelModel();

$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('m');
$activeTab = $_GET['tab'] ?? 'okuma';

$yearOptions = [];
for ($y = date('Y'); $y >= 2020; $y--) {
    $yearOptions[$y] = $y;
}

$monthOptions = [];
for ($m = 1; $m <= 12; $m++) {
    $m_val = str_pad($m, 2, '0', STR_PAD_LEFT);
    $monthOptions[$m_val] = Date::monthName($m_val);
}

$personelList = $Personel->all();
$personelOptions = ['' => 'Tüm Personeller'];
foreach ($personelList as $p) {
    $personelOptions[$p->id] = $p->adi_soyadi;
}

$regionList = $Tanimlamalar->getEkipBolgeleri();
$regionOptions = ['' => 'Tüm Bölgeler'];
foreach ($regionList as $r) {
    $regionOptions[$r] = $r;
}

?>
<div class="container-fluid">
    <?php
    $maintitle = "Puantaj";
    $title = "Özet Raporlar";
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
                                    <i class="bx bx-filter-alt me-2"></i> Filtreleme Seçenekleri
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
                                        <?php echo Form::FormSelect2("personel_id", $personelOptions, "", "Personel Seçiniz", "grid", "key", "", "form-select select2"); ?>
                                    </div>
                                    <div class="col-md-3">
                                        <?php echo Form::FormSelect2("region", $regionOptions, "", "Bölge Seçiniz", "grid", "key", "", "form-select select2"); ?>
                                    </div>
                                    <div class="col-md-2 d-flex align-items-end">
                                        <button type="submit" class="btn btn-primary w-100">Sorgula</button>
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
        <ul class="nav nav-tabs nav-tabs-custom nav-success mb-0" role="tablist" id="raporTabs">
            <li class="nav-item">
                <a class="nav-link <?= $activeTab === 'okuma' ? 'active' : '' ?>" href="javascript:void(0);"
                    data-tab="okuma">
                    <span class="d-block d-sm-none"><i class="fas fa-home"></i></span>
                    <span class="d-none d-sm-block">Endeks Okuma</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $activeTab === 'kesme' ? 'active' : '' ?>" href="javascript:void(0);"
                    data-tab="kesme">
                    <span class="d-block d-sm-none"><i class="far fa-user"></i></span>
                    <span class="d-none d-sm-block">Kesme/Açma İşlm.</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $activeTab === 'sokme_takma' ? 'active' : '' ?>" href="javascript:void(0);"
                    data-tab="sokme_takma">
                    <span class="d-block d-sm-none"><i class="far fa-user"></i></span>
                    <span class="d-none d-sm-block">Sayaç Sökme Takma</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $activeTab === 'muhurleme' ? 'active' : '' ?>" href="javascript:void(0);"
                    data-tab="muhurleme">
                    <span class="d-block d-sm-none"><i class="far fa-user"></i></span>
                    <span class="d-none d-sm-block">Mühürleme</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $activeTab === 'kacakkontrol' ? 'active' : '' ?>" href="javascript:void(0);"
                    data-tab="kacakkontrol">
                    <span class="d-block d-sm-none"><i class="far fa-user"></i></span>
                    <span class="d-none d-sm-block">Kaçak Kontrol</span>
                </a>
            </li>
        </ul>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-soft-primary" id="btnFullScreen">
                <i class="bx bx-fullscreen me-1"></i> Tam Ekran
            </button>
            <button type="button" class="btn btn-success" id="btnExportExcel">
                <i class="bx bx-spreadsheet me-1"></i> Excel'e Aktar
            </button>
        </div>
    </div>

    <div class="row" id="reportCardRow">
        <div class="col-12">
            <div class="card">
                <div class="card-body" id="reportContent">
                    <div class="text-center p-5">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-2">Rapor hazırlanıyor...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function () {
        let currentTab = '<?= $activeTab ?>';
        let currentYear = '<?= $year ?>';
        let currentMonth = '<?= $month ?>';
        let currentPersonelId = '';
        let currentRegion = '';

        function loadReport() {
            $('#reportContent').html('<div class="text-center p-5"><div class="spinner-border text-primary" role="status"></div><p class="mt-2">Rapor hazırlanıyor...</p></div>');

            $.ajax({
                url: 'views/puantaj/api.php',
                type: 'GET',
                data: {
                    action: 'get-report-table',
                    tab: currentTab,
                    year: currentYear,
                    month: currentMonth,
                    personel_id: currentPersonelId,
                    region: currentRegion
                },
                success: function (html) {
                    $('#reportContent').html(html);
                    updateUrl();
                },
                error: function () {
                    $('#reportContent').html('<div class="alert alert-danger">Rapor yüklenirken bir hata oluştu.</div>');
                }
            });
        }

        function updateUrl() {
            const url = new URL(window.location);
            url.searchParams.set('tab', currentTab);
            url.searchParams.set('year', currentYear);
            url.searchParams.set('month', currentMonth);
            if (currentPersonelId) url.searchParams.set('personel_id', currentPersonelId); else url.searchParams.delete('personel_id');
            if (currentRegion) url.searchParams.set('region', currentRegion); else url.searchParams.delete('region');
            window.history.pushState({}, '', url);
        }

        $('#raporTabs .nav-link').on('click', function () {
            $('#raporTabs .nav-link').removeClass('active');
            $(this).addClass('active');
            currentTab = $(this).data('tab');
            loadReport();
        });

        $('#filterForm').on('submit', function (e) {
            e.preventDefault();
            currentYear = $('select[name="year"]').val();
            currentMonth = $('select[name="month"]').val();
            currentPersonelId = $('select[name="personel_id"]').val();
            currentRegion = $('select[name="region"]').val();
            loadReport();
            const collapseElement = document.getElementById('collapseOne');
            const bsCollapse = bootstrap.Collapse.getInstance(collapseElement);
            if (bsCollapse) bsCollapse.hide();
        });

        $('#btnExportExcel').on('click', function () {
            const url = `views/puantaj/rapor-excel.php?tab=${currentTab}&year=${currentYear}&month=${currentMonth}&personel_id=${currentPersonelId}&region=${currentRegion}`;
            window.location.href = url;
        });

        $('#btnFullScreen').on('click', function () {
            const elem = document.getElementById('reportCardRow');
            if (!document.fullscreenElement) {
                elem.requestFullscreen().catch(err => {
                    alert(`Error attempting to enable full-screen mode: ${err.message} (${err.name})`);
                });
                $(this).html('<i class="bx bx-exit-fullscreen me-1"></i> Küçült');
                $(elem).addClass('fullscreen-mode');
            } else {
                document.exitFullscreen();
                $(this).html('<i class="bx bx-fullscreen me-1"></i> Tam Ekran');
                $(elem).removeClass('fullscreen-mode');
            }
        });

        document.addEventListener('fullscreenchange', () => {
            if (!document.fullscreenElement) {
                $('#btnFullScreen').html('<i class="bx bx-fullscreen me-1"></i> Tam Ekran');
                $('#reportCardRow').removeClass('fullscreen-mode');
            }
        });

        // Initial load
        loadReport();
    });
</script>

<style>
    body {
        overflow-x: hidden;
    }

    #raporTable {
        border-collapse: separate;
        border-spacing: 0;
    }

    #raporTable th,
    #raporTable td {
        border-color: #dee2e6 !important;
        font-size: 11px;
        white-space: nowrap;
        background-color: #fff;
    }

    #raporTable thead th {
        background-color: #f8f9fa;
        vertical-align: middle;
        position: sticky;
        top: 0;
        z-index: 20;
        box-shadow: inset 0 1px 0 #dee2e6, inset 0 -1px 0 #dee2e6;
    }

    /* Sticky headers for multi-row thead - Adjusting to be more flexible */
    #raporTable thead tr:nth-child(2) th {
        z-index: 19;
    }

    #raporTable thead tr:nth-child(3) th {
        z-index: 18;
    }

    /* Sticky Columns */
    #raporTable .sticky-col-1 {
        position: sticky;
        left: 0;
        z-index: 15;
    }

    #raporTable .sticky-col-2 {
        position: sticky;
        left: 40px;
        z-index: 15;
    }

    #raporTable .sticky-col-3 {
        position: sticky;
        left: 140px;
        z-index: 15;
    }

    /* Header Sticky Columns need higher z-index */
    #raporTable thead .sticky-col-1,
    #raporTable thead .sticky-col-2,
    #raporTable thead .sticky-col-3 {
        z-index: 30;
    }

    #raporTable tfoot td {
        background-color: #f8f9fa;
        position: sticky;
        bottom: 0;
        z-index: 20;
        box-shadow: inset 0 1px 0 #dee2e6, inset 0 -1px 0 #dee2e6;
    }

    /* Footer first column sticky */
    #raporTable tfoot td:nth-child(1) {
        position: sticky;
        left: 0;
        z-index: 25;
    }

    .table-responsive {
        max-height: calc(100vh - 380px);
        overflow: auto;
        border: 1px solid #dee2e6;
    }

    .accordion-button:not(.collapsed) {
        background-color: transparent;
        color: #556ee6;
        box-shadow: none;
    }

    .accordion-button {
        box-shadow: none !important;
    }

    .fullscreen-mode {
        background: #f4f5f8 !important;
        padding: 20px !important;
        display: flex !important;
        flex-direction: column !important;
        height: 100vh !important;
        width: 100% !important;
    }

    .fullscreen-mode>.col-12 {
        flex: 1;
        display: flex;
        flex-direction: column;
        height: 100%;
    }

    .fullscreen-mode .card {
        flex: 1;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        margin-bottom: 0;
    }

    .fullscreen-mode .card-body {
        flex: 1;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .fullscreen-mode .table-responsive {
        max-height: none !important;
        flex: 1;
        overflow: auto !important;
    }

    .report-legend {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        padding: 8px 12px;
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        margin-bottom: 10px;
        font-size: 11px;
    }

    .legend-item {
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .legend-code {
        font-weight: bold;
        color: #556ee6;
        background: #e2e7fb;
        padding: 2px 5px;
        border-radius: 3px;
        min-width: 25px;
        text-align: center;
    }
</style>