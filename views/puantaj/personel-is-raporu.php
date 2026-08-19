<?php
use App\Helper\Date;
use App\Helper\Form;
use App\Model\PersonelModel;

$Personel = new PersonelModel();

$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('m');
$personel_id = $_GET['personel_id'] ?? '';
$startDate = $_GET['start_date'] ?? date('01.m.Y');
$endDate = $_GET['end_date'] ?? date('t.m.Y');
$filterType = $_GET['filter_type'] ?? 'period';

$yearOptions = [];
for ($y = (int) date('Y'); $y >= 2020; $y--) {
    $yearOptions[$y] = (string) $y;
}

$monthOptions = [];
for ($m = 1; $m <= 12; $m++) {
    $m_val = str_pad((string) $m, 2, '0', STR_PAD_LEFT);
    $monthOptions[$m_val] = Date::monthName($m_val);
}

$personelList = $Personel->all(false, 'puantaj');
$personelOptions = ['' => 'Personel Seçiniz...'];
foreach ($personelList as $p) {
    $personelOptions[$p->id] = $p->adi_soyadi;
}

$categoryOptions = [
    '' => 'Tüm Kategoriler',
    'kesme_acma' => 'Kesme / Açma',
    'endeks_okuma' => 'Endeks Okuma',
    'sayac_degisim' => 'Sayaç Sökme Takma',
    'muhurleme' => 'Mühürleme',
    'kacak_kontrol' => 'Kaçak İşlemleri'
];
?>

<style>
    .kpi-card {
        border-radius: 12px;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        border: none;
    }
    .kpi-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 18px rgba(0,0,0,0.08);
    }
    .kpi-icon-box {
        width: 44px;
        height: 44px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
    }
    .filter-type-switcher {
        display: inline-flex;
        background: #f1f3f7;
        padding: 3px;
        border-radius: 8px;
        border: 1px solid #e2e5e9;
    }
    .filter-type-switcher .form-check {
        padding-left: 0;
        margin: 0;
    }
    .filter-type-switcher .form-check-input {
        display: none;
    }
    .filter-type-switcher .form-check-label {
        padding: 4px 14px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 12px;
        font-weight: 600;
        transition: all 0.2s;
        margin-bottom: 0;
        color: #6c757d;
        display: flex;
        align-items: center;
        gap: 5px;
    }
    .filter-type-switcher .form-check-input:checked + .form-check-label {
        background: #fff;
        color: #5156be;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.08);
    }
    [data-bs-theme="dark"] .filter-type-switcher {
        background: #2a3042;
        border-color: #32394e;
    }
    [data-bs-theme="dark"] .filter-type-switcher .form-check-input:checked + .form-check-label {
        background: #32394e;
        color: #fff;
    }
    .category-badge-kesme_acma { background-color: rgba(240, 101, 72, 0.15); color: #f06548; }
    .category-badge-endeks_okuma { background-color: rgba(10, 179, 156, 0.15); color: #0ab39c; }
    .category-badge-sayac_degisim { background-color: rgba(255, 190, 11, 0.18); color: #b8860b; }
    .category-badge-muhurleme { background-color: rgba(6, 182, 212, 0.15); color: #0891b2; }
    .category-badge-kacak_kontrol { background-color: rgba(64, 81, 137, 0.15); color: #405189; }
</style>

<div class="container-fluid">
    <?php
    $maintitle = "İş Takip";
    $title = "Personel İş Raporu";
    ?>
    <?php include 'layouts/breadcrumb.php'; ?>

    <!-- Filtre Kartı -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="card border-0 shadow-sm" style="border-radius: 12px;">
                <div class="card-body p-3">
                    <form id="personelRaporFilterForm">
                        <div class="row g-2 align-items-center">
                            <!-- Personel Seçimi -->
                            <div class="col-xl-3 col-lg-4 col-md-6">
                                <?= Form::FormSelect2("filter_personel_id", $personelOptions, $personel_id, "Personel Seçiniz", "bx bx-user", "key", "", "form-select select2", false, "width:100%", 'data-placeholder="Personel Seçiniz..."'); ?>
                            </div>

                            <!-- Dönem Switcher -->
                            <div class="col-xl-2 col-lg-3 col-md-6">
                                <div class="filter-type-switcher w-100 justify-content-center">
                                    <div class="form-check flex-fill text-center">
                                        <input class="form-check-input" type="radio" name="filter_type" id="typePeriod" value="period" <?= $filterType === 'period' ? 'checked' : '' ?>>
                                        <label class="form-check-label justify-content-center" for="typePeriod">
                                            <i class="bx bx-calendar-event"></i> Dönem
                                        </label>
                                    </div>
                                    <div class="form-check flex-fill text-center">
                                        <input class="form-check-input" type="radio" name="filter_type" id="typeRange" value="range" <?= $filterType === 'range' ? 'checked' : '' ?>>
                                        <label class="form-check-label justify-content-center" for="typeRange">
                                            <i class="bx bx-calendar-week"></i> Aralık
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Dönem Bazlı Seçiciler (Yıl / Ay) -->
                            <div class="col-xl-2 col-lg-2 col-md-3 filter-group-period" <?= $filterType === 'range' ? 'style="display:none"' : '' ?>>
                                <?= Form::FormSelect2("filter_year", $yearOptions, $year, "Yıl", "bx bx-calendar", "key", "", "form-select select2", false, "width:100%"); ?>
                            </div>
                            <div class="col-xl-2 col-lg-3 col-md-3 filter-group-period" <?= $filterType === 'range' ? 'style="display:none"' : '' ?>>
                                <?= Form::FormSelect2("filter_month", $monthOptions, $month, "Ay", "bx bx-calendar-check", "key", "", "form-select select2", false, "width:100%"); ?>
                            </div>

                            <!-- Tarih Aralığı Seçicileri -->
                            <div class="col-xl-2 col-lg-2 col-md-3 filter-group-range" <?= $filterType === 'period' ? 'style="display:none"' : '' ?>>
                                <?= Form::FormFloatInput("text", "filter_start_date", $startDate, "gg.aa.yyyy", "Başlangıç", "bx bx-calendar", "form-control flatpickr", false, null, "off"); ?>
                            </div>
                            <div class="col-xl-2 col-lg-3 col-md-3 filter-group-range" <?= $filterType === 'period' ? 'style="display:none"' : '' ?>>
                                <?= Form::FormFloatInput("text", "filter_end_date", $endDate, "gg.aa.yyyy", "Bitiş", "bx bx-calendar", "form-control flatpickr", false, null, "off"); ?>
                            </div>

                            <!-- Kategori Filtresi -->
                            <div class="col-xl-2 col-lg-3 col-md-4">
                                <?= Form::FormSelect2("filter_category", $categoryOptions, "", "Kategori", "bx bx-category", "key", "", "form-select select2", false, "width:100%"); ?>
                            </div>

                            <!-- Butonlar -->
                            <div class="col-xl-1 col-lg-2 col-md-2 d-flex gap-1">
                                <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold d-flex align-items-center justify-content-center gap-1" style="height: 38px;" id="btnRaporGetir">
                                    <i class="bx bx-search-alt fs-6"></i>
                                </button>
                                <button type="button" class="btn btn-soft-success btn-sm d-flex align-items-center justify-content-center" style="height: 38px; width: 44px;" id="btnExportExcel" title="Excel İndir">
                                    <i class="mdi mdi-file-excel fs-5"></i>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Rapor Yükleniyor / Boş Durum Alanı -->
    <div id="reportEmptyState" class="card border-0 shadow-sm text-center p-5" style="border-radius: 12px;">
        <div class="py-4">
            <i class="bx bx-user-pin text-primary" style="font-size: 54px; opacity: 0.7;"></i>
            <h5 class="mt-3 fw-bold text-dark">Lütfen Bir Personel Seçiniz</h5>
            <p class="text-muted mb-0">Yukarıdaki filtre alanından bir personel ve dönem seçip "Sorgula" butonuna basarak iş takip dökümünü inceleyebilirsiniz.</p>
        </div>
    </div>

    <div id="reportLoadingState" class="card border-0 shadow-sm text-center p-5" style="border-radius: 12px; display: none;">
        <div class="py-4">
            <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;"></div>
            <h5 class="mt-3 fw-semibold text-muted">Personel İş Raporu Hazırlanıyor...</h5>
        </div>
    </div>

    <!-- Rapor Ana İçeriği -->
    <div id="reportMainContent" style="display: none;">
        
        <!-- KPI Özet Kartları -->
        <div class="row g-2 mb-4">
            <!-- Toplam İş -->
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="card kpi-card shadow-sm h-100 bg-primary text-white">
                    <div class="card-body p-3 d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-white-50 small fw-semibold">Toplam İşlem</span>
                            <h4 class="mb-0 fw-bold mt-1 text-white" id="kpiToplamIs">0</h4>
                            <small class="text-white-50" id="kpiOrtalamaGun">Ort. 0 iş/gün</small>
                        </div>
                        <div class="kpi-icon-box bg-white bg-opacity-25 text-white">
                            <i class="bx bx-check-double"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Kesme / Açma -->
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="card kpi-card shadow-sm h-100 bg-white">
                    <div class="card-body p-3 d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted small fw-semibold">Kesme / Açma</span>
                            <h4 class="mb-0 fw-bold mt-1 text-danger" id="kpiKesmeAcma">0</h4>
                            <small class="text-muted" id="kpiKesmeDetay">0 Kesme / 0 Açma</small>
                        </div>
                        <div class="kpi-icon-box bg-danger bg-opacity-10 text-danger">
                            <i class="bx bx-cut"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Endeks Okuma -->
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="card kpi-card shadow-sm h-100 bg-white">
                    <div class="card-body p-3 d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted small fw-semibold">Endeks Okuma</span>
                            <h4 class="mb-0 fw-bold mt-1 text-success" id="kpiEndeksOkuma">0</h4>
                            <small class="text-muted">Okunan Abone</small>
                        </div>
                        <div class="kpi-icon-box bg-success bg-opacity-10 text-success">
                            <i class="bx bx-tachometer"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sayaç Sökme Takma -->
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="card kpi-card shadow-sm h-100 bg-white">
                    <div class="card-body p-3 d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted small fw-semibold">Sayaç Değişim</span>
                            <h4 class="mb-0 fw-bold mt-1 text-warning" id="kpiSayacDegisim">0</h4>
                            <small class="text-muted">Sökme / Takma</small>
                        </div>
                        <div class="kpi-icon-box bg-warning bg-opacity-10 text-warning">
                            <i class="bx bx-reset"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Mühürleme -->
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="card kpi-card shadow-sm h-100 bg-white">
                    <div class="card-body p-3 d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted small fw-semibold">Mühürleme</span>
                            <h4 class="mb-0 fw-bold mt-1 text-info" id="kpiMuhurleme" style="color: #0891b2 !important;">0</h4>
                            <small class="text-muted">Mühür İşlemi</small>
                        </div>
                        <div class="kpi-icon-box bg-info bg-opacity-10" style="color: #0891b2;">
                            <i class="bx bx-lock-alt"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Kaçak İşlemleri -->
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="card kpi-card shadow-sm h-100 bg-white">
                    <div class="card-body p-3 d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted small fw-semibold">Kaçak İşlemleri</span>
                            <h4 class="mb-0 fw-bold mt-1" style="color: #405189;" id="kpiKacakKontrol">0</h4>
                            <small class="text-muted" id="kpiAktifGun">Aktif: 0 Gün</small>
                        </div>
                        <div class="kpi-icon-box bg-opacity-10" style="background-color: rgba(64, 81, 137, 0.1); color: #405189;">
                            <i class="bx bx-shield-quarter"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Grafikler Alanı (ApexCharts) -->
        <div class="row g-3 mb-4">
            <!-- Günlük Trend Grafiği -->
            <div class="col-xl-8 col-lg-7">
                <div class="card border-0 shadow-sm h-100" style="border-radius: 12px;">
                    <div class="card-header bg-transparent border-bottom py-3 d-flex align-items-center justify-content-between">
                        <h6 class="mb-0 fw-bold text-dark"><i class="bx bx-line-chart me-1 text-primary"></i> Günlük İşlem Trendi</h6>
                        <span class="badge bg-soft-primary text-primary" id="trendDonemBadge">Dönem</span>
                    </div>
                    <div class="card-body">
                        <div id="chartDailyTrend" style="min-height: 320px;"></div>
                    </div>
                </div>
            </div>

            <!-- İşlem Türü Dağılımı Donut Grafiği -->
            <div class="col-xl-4 col-lg-5">
                <div class="card border-0 shadow-sm h-100" style="border-radius: 12px;">
                    <div class="card-header bg-transparent border-bottom py-3">
                        <h6 class="mb-0 fw-bold text-dark"><i class="bx bx-pie-chart-alt-2 me-1 text-primary"></i> İş Dağılım Oranları</h6>
                    </div>
                    <div class="card-body d-flex align-items-center justify-content-center">
                        <div id="chartDistribution" class="w-100" style="min-height: 320px;"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Liste / Tablo Sekmeleri -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm" style="border-radius: 12px;">
                    <div class="card-header bg-transparent border-bottom py-2 d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <ul class="nav nav-pills nav-pills-custom" role="tablist" id="reportViewTabs">
                            <li class="nav-item">
                                <a class="nav-link active px-3 py-2 fw-semibold" data-bs-toggle="tab" href="#paneGunlukOzet" role="tab">
                                    <i class="bx bx-calendar-event me-1"></i> Günlük Döküm Tablosu
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link px-3 py-2 fw-semibold" data-bs-toggle="tab" href="#paneDetayliListe" role="tab">
                                    <i class="bx bx-list-ul me-1"></i> Detaylı İşlem Kayıtları
                                </a>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content">
                            <!-- Günlük Döküm Tablosu -->
                            <div class="tab-pane fade show active" id="paneGunlukOzet" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-hover table-bordered align-middle mb-0" id="dailySummaryTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="text-center" style="width: 110px;">TARİH</th>
                                                <th class="text-center" style="width: 70px;">GÜN</th>
                                                <th class="text-end" style="width: 130px;">KESME / AÇMA</th>
                                                <th class="text-end" style="width: 130px;">ENDEKS OKUMA</th>
                                                <th class="text-end" style="width: 130px;">SAYAÇ DEĞİŞİM</th>
                                                <th class="text-end" style="width: 130px;">MÜHÜRLEME</th>
                                                <th class="text-end" style="width: 130px;">KAÇAK İŞLEMLERİ</th>
                                                <th class="text-end fw-bold" style="width: 140px;">GÜNLÜK TOPLAM</th>
                                            </tr>
                                        </thead>
                                        <tbody id="dailySummaryTableBody"></tbody>
                                        <tfoot class="table-light fw-bold" id="dailySummaryTableFoot"></tfoot>
                                    </table>
                                </div>
                            </div>

                            <!-- Detaylı İşlem Listesi (DataTables) -->
                            <div class="tab-pane fade" id="paneDetayliListe" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-hover table-bordered align-middle w-100" id="detailedLogsTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 50px;" data-filter="none">SIRA</th>
                                                <th style="width: 100px;" data-filter="date">TARİH</th>
                                                <th style="width: 140px;" data-filter="select">KATEGORİ</th>
                                                <th data-filter="string">İŞ EMRİ TİPİ</th>
                                                <th data-filter="string">SONUÇ / DURUM</th>
                                                <th style="width: 130px;" data-filter="string">ABONE / SAYAÇ</th>
                                                <th style="width: 140px;" data-filter="string">BÖLGE / EKİP</th>
                                                <th style="width: 80px;" class="text-end" data-filter="none">ADET</th>
                                                <th data-filter="string">AÇIKLAMA</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
$(document).ready(function () {
    let trendChart = null;
    let distChart = null;
    let logsDataTable = null;

    // Flatpickr başlatma
    if (typeof flatpickr !== 'undefined') {
        $('.flatpickr').flatpickr({
            locale: 'tr',
            dateFormat: 'd.m.Y',
            allowInput: true
        });
    }

    // Filtre Tipi Değişimi
    $('input[name="filter_type"]').on('change', function () {
        const type = $(this).val();
        if (type === 'period') {
            $('.filter-group-period').show();
            $('.filter-group-range').hide();
        } else {
            $('.filter-group-period').hide();
            $('.filter-group-range').show();
        }
    });

    // Form Gönderimi (Raporu Getir)
    $('#personelRaporFilterForm').on('submit', function (e) {
        e.preventDefault();
        loadPersonelReport();
    });

    // Otomatik ilk yükleme (Eğer personel ID varsa)
    const initialPersonelId = $('select[name="filter_personel_id"]').val();
    if (initialPersonelId && initialPersonelId !== '') {
        loadPersonelReport();
    }

    // Excel Export
    $('#btnExportExcel').on('click', function () {
        const personelId = $('select[name="filter_personel_id"]').val();
        if (!personelId) {
            Swal.fire('Uyarı', 'Lütfen önce bir personel seçiniz.', 'warning');
            return;
        }

        const params = {
            personel_id: personelId,
            filter_type: $('input[name="filter_type"]:checked').val(),
            year: $('select[name="filter_year"]').val(),
            month: $('select[name="filter_month"]').val(),
            start_date: $('input[name="filter_start_date"]').val(),
            end_date: $('input[name="filter_end_date"]').val(),
            category: $('select[name="filter_category"]').val()
        };

        window.location.href = 'views/puantaj/personel-is-raporu-excel.php?' + $.param(params);
    });

    function loadPersonelReport() {
        const personelId = $('select[name="filter_personel_id"]').val();
        if (!personelId) {
            $('#reportMainContent').hide();
            $('#reportEmptyState').show();
            $('#reportLoadingState').hide();
            Swal.fire('Bilgi', 'Lütfen raporunu görmek istediğiniz personeli seçiniz.', 'info');
            return;
        }

        $('#reportEmptyState').hide();
        $('#reportMainContent').hide();
        $('#reportLoadingState').show();

        const formData = {
            action: 'get-report-data',
            personel_id: personelId,
            filter_type: $('input[name="filter_type"]:checked').val(),
            year: $('select[name="filter_year"]').val(),
            month: $('select[name="filter_month"]').val(),
            start_date: $('input[name="filter_start_date"]').val(),
            end_date: $('input[name="filter_end_date"]').val(),
            category: $('select[name="filter_category"]').val()
        };

        $.ajax({
            url: 'views/puantaj/api/personel-is-raporu-api.php',
            type: 'GET',
            data: formData,
            dataType: 'json'
        }).done(function (res) {
            $('#reportLoadingState').hide();
            if (res.status === 'success') {
                $('#reportMainContent').show();
                try { renderKpis(res.kpi); } catch (e) { console.error('renderKpis error:', e); }
                try { renderDailyTable(res.trend.daily_list); } catch (e) { console.error('renderDailyTable error:', e); }
                try { renderDetailedLogs(res.logs); } catch (e) { console.error('renderDetailedLogs error:', e); }
                
                setTimeout(function() {
                    try { renderCharts(res.trend, res.distribution, res.period); } catch (e) { console.error('renderCharts error:', e); }
                }, 100);
            } else {
                Swal.fire('Uyarı', res.message || 'Veriler alınamadı.', 'warning');
                $('#reportEmptyState').show();
            }
        }).fail(function () {
            $('#reportLoadingState').hide();
            $('#reportEmptyState').show();
            Swal.fire('Hata', 'Rapor verileri yüklenirken sunucu hatası oluştu.', 'error');
        });
    }

    function renderKpis(kpi) {
        $('#kpiToplamIs').text((kpi.toplam_is || 0).toLocaleString('tr-TR'));
        $('#kpiOrtalamaGun').text('Ort. ' + (kpi.gunluk_ortalama || 0) + ' iş/gün');
        $('#kpiKesmeAcma').text((kpi.kesme_acma || 0).toLocaleString('tr-TR'));
        $('#kpiKesmeDetay').text((kpi.kesme_adet || 0) + ' Kesme / ' + (kpi.acma_adet || 0) + ' Açma');
        $('#kpiEndeksOkuma').text((kpi.endeks_okuma || 0).toLocaleString('tr-TR'));
        $('#kpiSayacDegisim').text((kpi.sayac_degisim || 0).toLocaleString('tr-TR'));
        $('#kpiMuhurleme').text((kpi.muhurleme || 0).toLocaleString('tr-TR'));
        $('#kpiKacakKontrol').text((kpi.kacak_kontrol || 0).toLocaleString('tr-TR'));
        $('#kpiAktifGun').text('Aktif: ' + (kpi.aktif_gun_sayisi || 0) + ' Gün');
    }

    function renderCharts(trend, distribution, period) {
        if (typeof ApexCharts === 'undefined') {
            console.warn('ApexCharts is not defined yet.');
            return;
        }

        $('#trendDonemBadge').text(period.start_date_tr + ' - ' + period.end_date_tr);

        // 1. Günlük Trend Çizgi Grafiği
        if (trendChart) {
            try { trendChart.destroy(); } catch (e) {}
            trendChart = null;
        }
        $('#chartDailyTrend').empty();

        const trendOptions = {
            series: trend.series || [],
            chart: {
                type: 'bar',
                height: 320,
                stacked: true,
                toolbar: { show: true },
                zoom: { enabled: true }
            },
            responsive: [{
                breakpoint: 480,
                options: {
                    legend: { position: 'bottom', offsetX: -10, offsetY: 0 }
                }
            }],
            plotOptions: {
                bar: {
                    horizontal: false,
                    borderRadius: 4,
                    dataLabels: { total: { enabled: true, style: { fontSize: '10px', fontWeight: 700 } } }
                }
            },
            xaxis: {
                categories: trend.categories || [],
                labels: { rotate: -45, style: { fontSize: '11px' } }
            },
            yaxis: {
                title: { text: 'İşlem Adedi' }
            },
            legend: { position: 'top', horizontalAlign: 'right' },
            fill: { opacity: 1 },
            tooltip: {
                y: {
                    formatter: function (val) {
                        return val + " Adet";
                    }
                }
            }
        };

        trendChart = new ApexCharts(document.querySelector("#chartDailyTrend"), trendOptions);
        trendChart.render();

        // 2. Kategori Dağılım Donut Grafiği
        if (distChart) {
            try { distChart.destroy(); } catch (e) {}
            distChart = null;
        }
        $('#chartDistribution').empty();

        const hasDistData = (distribution.series && distribution.series.length > 0 && distribution.series.some(v => v > 0));

        if (!hasDistData) {
            $('#chartDistribution').html('<div class="text-center text-muted p-4"><i class="bx bx-info-circle fs-3 d-block mb-1"></i>Bu dönemde işlem kaydı bulunmuyor.</div>');
            return;
        }

        const distOptions = {
            series: distribution.series || [],
            labels: distribution.labels || [],
            colors: distribution.colors || ['#f06548', '#0ab39c', '#ffbe0b', '#06b6d4', '#405189'],
            chart: {
                type: 'donut',
                height: 320
            },
            plotOptions: {
                pie: {
                    donut: {
                        size: '65%',
                        labels: {
                            show: true,
                            total: {
                                show: true,
                                label: 'Toplam İş',
                                formatter: function (w) {
                                    return w.globals.seriesTotals.reduce((a, b) => a + b, 0);
                                }
                            }
                        }
                    }
                }
            },
            legend: { position: 'bottom' },
            responsive: [{
                breakpoint: 480,
                options: { chart: { width: 280 }, legend: { position: 'bottom' } }
            }]
        };

        distChart = new ApexCharts(document.querySelector("#chartDistribution"), distOptions);
        distChart.render();
    }

    function renderDailyTable(dailyList) {
        let html = '';
        let totKesme = 0, totOkuma = 0, totSayac = 0, totMuhur = 0, totKacak = 0, totGenel = 0;

        (dailyList || []).forEach(function (row) {
            totKesme += (row.kesme_acma || 0);
            totOkuma += (row.endeks_okuma || 0);
            totSayac += (row.sayac_degisim || 0);
            totMuhur += (row.muhurleme || 0);
            totKacak += (row.kacak_kontrol || 0);
            totGenel += (row.toplam || 0);

            const isWeekend = (row.gun_adi === 'Paz' || row.gun_adi === 'Cmt' || row.is_weekend);
            const rowClass = isWeekend ? 'table-light' : '';

            html += `<tr class="${rowClass}">
                <td class="text-center font-monospace">${row.tarih_tr}</td>
                <td class="text-center"><span class="badge ${isWeekend ? 'bg-danger' : 'bg-light text-dark'}">${row.gun_adi}</span></td>
                <td class="text-end ${row.kesme_acma > 0 ? 'fw-bold text-danger' : 'text-muted'}">${row.kesme_acma > 0 ? row.kesme_acma.toLocaleString('tr-TR') : '-'}</td>
                <td class="text-end ${row.endeks_okuma > 0 ? 'fw-bold text-success' : 'text-muted'}">${row.endeks_okuma > 0 ? row.endeks_okuma.toLocaleString('tr-TR') : '-'}</td>
                <td class="text-end ${row.sayac_degisim > 0 ? 'fw-bold text-warning' : 'text-muted'}">${row.sayac_degisim > 0 ? row.sayac_degisim.toLocaleString('tr-TR') : '-'}</td>
                <td class="text-end ${row.muhurleme > 0 ? 'fw-bold text-info' : 'text-muted'}">${row.muhurleme > 0 ? row.muhurleme.toLocaleString('tr-TR') : '-'}</td>
                <td class="text-end ${row.kacak_kontrol > 0 ? 'fw-bold text-info' : 'text-muted'}" style="${row.kacak_kontrol > 0 ? 'color: #405189 !important;' : ''}">${row.kacak_kontrol > 0 ? row.kacak_kontrol.toLocaleString('tr-TR') : '-'}</td>
                <td class="text-end fw-bold ${row.toplam > 0 ? 'text-primary' : 'text-muted'}">${row.toplam > 0 ? row.toplam.toLocaleString('tr-TR') : '-'}</td>
            </tr>`;
        });

        $('#dailySummaryTableBody').html(html);

        const footHtml = `<tr>
            <td colspan="2" class="text-center">TOPLAM</td>
            <td class="text-end text-danger">${totKesme.toLocaleString('tr-TR')}</td>
            <td class="text-end text-success">${totOkuma.toLocaleString('tr-TR')}</td>
            <td class="text-end text-warning">${totSayac.toLocaleString('tr-TR')}</td>
            <td class="text-end text-info">${totMuhur.toLocaleString('tr-TR')}</td>
            <td class="text-end" style="color: #405189;">${totKacak.toLocaleString('tr-TR')}</td>
            <td class="text-end text-primary fs-6">${totGenel.toLocaleString('tr-TR')}</td>
        </tr>`;
        $('#dailySummaryTableFoot').html(footHtml);
    }

    function renderDetailedLogs(logs) {
        if ($.fn.DataTable.isDataTable('#detailedLogsTable')) {
            $('#detailedLogsTable').DataTable().destroy();
        }

        let tbodyHtml = '';
        (logs || []).forEach(function (log, idx) {
            const dateStr = log.tarih ? log.tarih.split('-').reverse().join('.') : '-';
            const catBadgeClass = 'category-badge-' + (log.kategori || '');

            tbodyHtml += `<tr>
                <td class="text-center">${idx + 1}</td>
                <td class="text-center font-monospace">${dateStr}</td>
                <td><span class="badge px-2 py-1 ${catBadgeClass}">${log.kategori_adi || log.kategori}</span></td>
                <td class="fw-medium">${escapeHtml(log.is_emri_tipi || '-')}</td>
                <td>${escapeHtml(log.is_emri_sonucu || '-')}</td>
                <td class="font-monospace">${escapeHtml(log.abone_no || '-')}</td>
                <td>${escapeHtml(log.bolge || log.ekip || '-')}</td>
                <td class="text-end fw-bold">${(log.adet || 1)}</td>
                <td class="text-muted small">${escapeHtml(log.aciklama || '-')}</td>
            </tr>`;
        });

        $('#detailedLogsTable tbody').html(tbodyHtml);

        const dtOpts = (typeof getDatatableOptions === 'function') ? getDatatableOptions() : {};
        if (typeof applyLengthStateSave === 'function') {
            logsDataTable = $('#detailedLogsTable').DataTable(applyLengthStateSave({
                ...dtOpts,
                pageLength: 25,
                order: [[1, 'desc']]
            }));
        } else {
            logsDataTable = $('#detailedLogsTable').DataTable({
                ...dtOpts,
                pageLength: 25,
                order: [[1, 'desc']]
            });
        }
    }

    function escapeHtml(text) {
        if (!text) return '';
        return String(text)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
});
</script>
