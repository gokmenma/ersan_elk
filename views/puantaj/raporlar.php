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
$personel_id = $_GET['personel_id'] ?? '';
$region = $_GET['region'] ?? '';
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
                                        <?php echo Form::FormSelect2("personel_id", $personelOptions, $personel_id, "Personel Seçiniz", "grid", "key", "", "form-select select2"); ?>
                                    </div>
                                    <div class="col-md-3">
                                        <?php echo Form::FormSelect2("region", $regionOptions, $region, "Bölge Seçiniz", "grid", "key", "", "form-select select2"); ?>
                                    </div>
                                    <div class="col-md-2 d-flex align-items-end">
                                        <div class="d-flex align-items-center bg-white border rounded shadow-sm p-1 gap-1 w-100">
                                            <button type="submit" class="btn btn-primary btn-sm flex-grow-1 fw-bold">
                                                <i class="mdi mdi-magnify me-1"></i> Sorgula
                                            </button>
                                            <div class="vr mx-1" style="height: 20px; align-self: center;"></div>
                                            <button type="button" class="btn btn-link btn-sm text-secondary text-decoration-none px-2"
                                                id="btnClearFilters">
                                                <i class="mdi mdi-filter-remove"></i>
                                            </button>
                                        </div>
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
        <div class="d-flex align-items-center bg-white border rounded shadow-sm p-1 gap-1">
            <button type="button"
                class="btn btn-link btn-sm text-primary text-decoration-none px-2 d-flex align-items-center"
                id="btnFullScreen">
                <i class="mdi mdi-fullscreen fs-5 me-1"></i> Tam Ekran
            </button>
            <div class="vr mx-1" style="height: 25px; align-self: center;"></div>
            <button type="button"
                class="btn btn-link btn-sm text-success text-decoration-none px-2 d-flex align-items-center"
                id="btnExportExcel">
                <i class="mdi mdi-file-excel fs-5 me-1"></i> Excel'e Aktar
            </button>
        </div>
    </div>

    <div class="row" id="reportCardRow">
        <div class="col-12">
            <div id="kacakHelpInfo" class="alert alert-soft-primary alert-dismissible fade show mb-2 p-2" role="alert"
                style="display: none;">
                <div class="d-flex align-items-center">
                    <i class="bx bxs-info-circle fs-5 me-2"></i>
                    <div>
                        <strong>İpucu:</strong> Kaçak Kontrol tablosunda gün kutucuklarına <strong>çift
                            tıklayarak</strong> o tarih ve o ekip için hızlıca yeni kayıt oluşturabilirsiniz.
                    </div>
                </div>
                <button type="button" class="btn-close p-2" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
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
        let currentPersonelId = '<?= $personel_id ?>';
        let currentRegion = '<?= $region ?>';

        const STORAGE_KEY = 'raporlar_filters';

        function saveFiltersToStorage() {
            const filters = {
                tab: currentTab,
                year: currentYear,
                month: currentMonth,
                personel_id: currentPersonelId,
                region: currentRegion
            };
            localStorage.setItem(STORAGE_KEY, JSON.stringify(filters));
        }

        function loadFiltersFromStorage() {
            const saved = localStorage.getItem(STORAGE_KEY);
            if (saved) {
                const filters = JSON.parse(saved);
                const urlParams = new URLSearchParams(window.location.search);

                // Sadece URL'de olmayan parametreleri storage'dan alalım
                if (!urlParams.has('tab')) currentTab = filters.tab || currentTab;
                if (!urlParams.has('year')) currentYear = filters.year || currentYear;
                if (!urlParams.has('month')) currentMonth = filters.month || currentMonth;
                if (!urlParams.has('personel_id')) currentPersonelId = filters.personel_id || currentPersonelId;
                if (!urlParams.has('region')) currentRegion = filters.region || currentRegion;

                // UI bileşenlerini güncelle
                $(`#raporTabs .nav-link[data-tab="${currentTab}"]`).addClass('active').parent().siblings().find('.nav-link').removeClass('active');
                $('select[name="year"]').val(currentYear).trigger('change.select2');
                $('select[name="month"]').val(currentMonth).trigger('change.select2');
                $('select[name="personel_id"]').val(currentPersonelId).trigger('change.select2');
                $('select[name="region"]').val(currentRegion).trigger('change.select2');
            }
        }

        function loadReport() {
            $('#reportContent').html('<div class="text-center p-5"><div class="spinner-border text-primary" role="status"></div><p class="mt-2">Rapor hazırlanıyor...</p></div>');
            updateFilterSummary();

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
                    saveFiltersToStorage();

                    // Show help info if it's kacak tab
                    if (currentTab === 'kacakkontrol') {
                        $('#kacakHelpInfo').show();
                    } else {
                        $('#kacakHelpInfo').hide();
                    }
                },
                error: function () {
                    $('#reportContent').html('<div class="alert alert-danger">Rapor yüklenirken bir hata oluştu.</div>');
                }
            });
        }

        function updateFilterSummary() {
            let summary = '';
            const yearText = $('select[name="year"] option:selected').text();
            const monthText = $('select[name="month"] option:selected').text();
            const personelText = $('select[name="personel_id"] option:selected').text();
            const regionText = $('select[name="region"] option:selected').text();

            if (yearText) summary += `<div class="filter-summary-badge"><span class="badge-label">Yıl:</span><span class="badge-value">${yearText}</span></div>`;
            if (monthText) summary += `<div class="filter-summary-badge"><span class="badge-label">Ay:</span><span class="badge-value">${monthText}</span></div>`;

            if (currentPersonelId && currentPersonelId !== '') {
                summary += `<div class="filter-summary-badge"><span class="badge-label">Pers:</span><span class="badge-value">${personelText}</span><button type="button" class="btn-clear-filter" data-filter="personel_id"><i class="bx bx-x"></i></button></div>`;
            }

            if (currentRegion && currentRegion !== '') {
                summary += `<div class="filter-summary-badge"><span class="badge-label">Bölge:</span><span class="badge-value">${regionText}</span><button type="button" class="btn-clear-filter" data-filter="region"><i class="bx bx-x"></i></button></div>`;
            }

            $('#filterSummary').html(summary);
        }

        $(document).on('click', '.btn-clear-filter', function (e) {
            e.stopPropagation();
            const filterType = $(this).data('filter');
            if (filterType === 'personel_id') {
                currentPersonelId = '';
                $('select[name="personel_id"]').val('').trigger('change');
            } else if (filterType === 'region') {
                currentRegion = '';
                $('select[name="region"]').val('').trigger('change');
            }
            loadReport();
        });

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

            // Reset filters on tab change (except year/month) - BU KISMI USER İSTEDİĞİ İÇİN KALDIRIYORUZ VEYA PERSONEL KALSIN MI?
            // "ay,yıl ve sekme kalmalı" dediği temizle butonu içindi. 
            // Tab değişiminde personel/bölge sıfırlanmalı mı? 
            // Genelde kullanıcılar tüm sekmelerde aynı personeli görmek ister.

            // Show/Hide Kacak Help Info
            if (currentTab === 'kacakkontrol') {
                $('#kacakHelpInfo').fadeIn();
            } else {
                $('#kacakHelpInfo').fadeOut();
            }

            loadReport();
        });

        $(document).on('dblclick', '.kacak-quick-cell', function () {
            // Remove previous selection
            $('.kacak-quick-cell').removeClass('kacak-cell-selected');
            // Add selection to current cell
            $(this).addClass('kacak-cell-selected');

            let tarih = $(this).attr('data-date');
            let pIds = $(this).attr('data-personel-ids');
            let ekipAdi = $(this).attr('data-ekip-adi'); // Yeni
            let sayi = $(this).text().trim() || '';
            window.openKacakModal(tarih, pIds, sayi, ekipAdi);
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

        $('#btnClearFilters').on('click', function () {
            currentPersonelId = '';
            currentRegion = '';
            $('select[name="personel_id"]').val('').trigger('change');
            $('select[name="region"]').val('').trigger('change');
            loadReport();
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
        loadFiltersFromStorage();
        loadReport();

        window.openKacakModal = function (tarih, pIds, sayi, ekipAdi) {
            $('#kacakManualForm input[name="id"]').val(0);
            $('#kacakManualForm')[0].reset();

            $('#kacakModalTitle').text('Hızlı Kaçak Kontrol Kaydı');

            // Convert to array of strings for Select2 compatibility
            let pIdsArr = [];
            if (pIds && typeof pIds === 'string' && pIds.trim() !== '') {
                pIdsArr = pIds.split(',').map(x => x.trim()).filter(x => x !== '');
            }

            console.log('Opening Kacak Modal - Date:', tarih, 'Personnel IDs:', pIdsArr, 'Sayi:', sayi);

            // Initialize Select2 with pre-selected values
            initPersonelSelect2(pIdsArr);

            // Set Date
            $('#kacakManualForm input[name="tarih"]').val(tarih);

            // Set Sayi (number)
            $('#kacakManualForm input[name="sayi"]').val(sayi || '');

            // Set Ekip Adi
            $('#kacakManualForm input[name="ekip_adi"]').val(ekipAdi || '');

            // Initialize flatpickr if available
            if (typeof flatpickr !== 'undefined' && $('#kacakManualForm .flatpickr').length > 0) {
                $('#kacakManualForm .flatpickr').flatpickr({
                    dateFormat: "d.m.Y",
                    locale: "tr",
                    allowInput: true
                });
            }

            $('#kacakModal').modal('show');

            // Focus on sayi input after modal is shown
            $('#kacakModal').one('shown.bs.modal', function () {
                $('#kacakManualForm input[name="sayi"]').focus().select();
            });
        }

        function initPersonelSelect2(selectedValues) {
            var $el = $('#kacak_personel_ids');
            if ($el.hasClass('select2-hidden-accessible')) {
                $el.select2('destroy');
            }

            // Set selected values before initializing Select2
            if (selectedValues && selectedValues.length > 0) {
                $el.val(selectedValues);
            }

            $el.select2({
                dropdownParent: $('#kacakModal'),
                placeholder: 'Personel Seçiniz',
                allowClear: true,
                maximumSelectionLength: 2,
                width: '100%'
            });

            // Trigger change to update Select2 display
            if (selectedValues && selectedValues.length > 0) {
                $el.trigger('change');
            }
        }

        $('#kacakManualForm').on('submit', function (e) {
            e.preventDefault();
            var formData = $(this).serialize();
            formData += '&action=kacak-kaydet';

            $.ajax({
                url: 'views/puantaj/api.php',
                type: 'POST',
                data: formData,
                success: function (response) {
                    try {
                        var res = JSON.parse(response);
                        if (res.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Başarılı',
                                text: 'Kayıt başarıyla kaydedildi.',
                                timer: 1500,
                                showConfirmButton: false
                            });
                            $('#kacakModal').modal('hide');
                            loadReport();
                        } else {
                            Swal.fire('Hata', 'Kayıt sırasında bir hata oluştu.', 'error');
                        }
                    } catch (err) {
                        Swal.fire('Hata', 'Sunucudan geçersiz yanıt alındı.', 'error');
                    }
                }
            });
        });
    });
</script>

<!-- Kaçak Kontrol Manuel Modal -->
<?php
$personelOptionsMultiple = [];
foreach ($personelList as $p) {
    if ($p->id)
        $personelOptionsMultiple[$p->id] = $p->adi_soyadi;
}
?>
<div class="modal fade" id="kacakModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="kacakModalTitle">Manuel Kaçak Kontrol Kaydı</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="kacakManualForm">
                <input type="hidden" name="id" id="kacak_id" value="0">
                <input type="hidden" name="ekip_adi" id="kacak_ekip_adi" value="">
                <div class="modal-body">
                    <div class="mb-3">
                        <?php echo Form::FormFloatInput(
                            type: 'text',
                            name: 'tarih',
                            value: date('d.m.Y'),
                            placeholder: '',
                            label: "Tarih",
                            icon: "calendar",
                            required: true,
                            class: "form-control flatpickr"
                        ); ?>
                    </div>
                    <div class="mb-3">
                        <label for="kacak_personel_ids">Personel Seçimi (En Fazla 2 Personel)</label>
                        <?php echo Form::FormMultipleSelect2(
                            name: 'kacak_personel_ids',
                            options: $personelOptionsMultiple,
                            selectedValues: [],
                            label: '',
                            icon: 'users',
                            valueField: 'key',
                            textField: '',
                            class: 'form-select select2',
                            required: true
                        ); ?>
                    </div>
                    <div class="mb-3">
                        <?php echo Form::FormFloatInput(
                            type: 'number',
                            name: 'sayi',
                            value: '',
                            placeholder: '',
                            label: "Sayı",
                            icon: "hash",
                            required: true
                        ); ?>
                    </div>
                    <div class="mb-3">
                        <?php echo Form::FormFloatInput(
                            type: 'text',
                            name: 'aciklama',
                            value: '',
                            placeholder: '',
                            label: "Açıklama",
                            icon: "file-text",
                            required: false
                        ); ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                    <button type="submit" class="btn btn-primary">Kaydet</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    body {
        overflow-x: hidden;
    }

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

    /* Kacak Quick Entry Cell Selection */
    .kacak-quick-cell {
        transition: all 0.2s ease;
    }

    .kacak-quick-cell:hover {
        background-color: rgba(var(--bs-primary-rgb), 0.15) !important;
    }

    .kacak-cell-selected {
        background-color: var(--bs-primary) !important;
        color: #fff !important;
        border-radius: 4px;
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
        cursor: pointer;
    }

    .legend-code {
        font-weight: bold;
        color: var(--bs-primary, #556ee6);
        background: rgba(var(--bs-primary-rgb, 85, 110, 230), 0.15);
        padding: 2px 5px;
        border-radius: 3px;
        min-width: 25px;
        text-align: center;
    }

    .legend-item {
        border: 1px solid rgba(var(--bs-primary-rgb, 85, 110, 230), 0.3);
        padding: 4px 8px;
        border-radius: 6px;
        transition: all 0.2s ease;
    }

    .legend-item:hover {
        background: rgba(var(--bs-primary-rgb, 85, 110, 230), 0.1);
        border-color: var(--bs-primary, #556ee6);
    }

    #raporTabs.nav-tabs-custom {
        border-bottom: none !important;
    }

    #raporTabs.nav-tabs-custom .nav-link {
        border: none !important;
        text-decoration: none !important;
        box-shadow: none !important;
    }

    #raporTabs.nav-tabs-custom .nav-link::after,
    #raporTabs.nav-tabs-custom .nav-link::before {
        display: none !important;
    }

    .accordion-button:not(.collapsed) #filterSummary {
        display: none !important;
    }

    .filter-summary-badge {
        display: flex;
        align-items: center;
        background: var(--bs-primary);
        color: #fff;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 500;
        overflow: hidden;
        border: 1px solid var(--bs-primary);
        box-shadow: 0 2px 4px rgba(var(--bs-primary-rgb), 0.15);
    }

    .filter-summary-badge .badge-label {
        padding: 6px 8px;
        background: rgba(0, 0, 0, 0.15);
        color: rgba(255, 255, 255, 0.85);
        border-right: 1px solid rgba(255, 255, 255, 0.1);
    }

    .filter-summary-badge .badge-value {
        padding: 6px 10px;
        font-weight: 600;
    }

    .btn-clear-filter {
        background: rgba(255, 255, 255, 0.2);
        border: none;
        color: #fff;
        padding: 4px 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s ease;
        font-size: 14px;
        height: 100%;
        border-left: 1px solid rgba(255, 255, 255, 0.1);
    }

    .btn-clear-filter:hover {
        background: rgba(255, 255, 255, 0.35);
        color: #fff;
    }

    .btn-clear-filter i {
        pointer-events: none;
    }
</style>