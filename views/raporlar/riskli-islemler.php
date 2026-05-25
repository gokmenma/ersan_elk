<?php
use App\Helper\Date;
use App\Helper\Form;
use App\Model\TanimlamalarModel;
use App\Model\EndeksOkumaModel;

$Tanimlar = new TanimlamalarModel();
$EndeksOkuma = new EndeksOkumaModel();

$startDate = $_GET['start_date'] ?? Date::firstDayOfThisMonth();
$endDate = $_GET['end_date'] ?? Date::today();
$region = $_GET['region'] ?? '';
$defter = $_GET['defter'] ?? '';
$calcType = $_GET['calc_type'] ?? 'normal'; // 'total' or 'normal'
$thresholdInput = $_GET['threshold'] ?? 60; // Default %60
$threshold = (float)$thresholdInput / 100;

$regionList = $Tanimlar->getFilteredEkipBolgeleri();
$regionOptions = ['' => 'Tüm Bölgeler'];
foreach ($regionList as $r) {
    $regionOptions[$r] = $r;
}

$defterList = $Tanimlar->getDefterKodlari();
$defterOptions = ['' => 'Tüm Defterler'];
foreach ($defterList as $d) {
    if ($d) $defterOptions[$d] = $d;
}

$calcTypeOptions = [
    'total' => 'Evde Yok+Kullanılmıyor / Toplam Abone',
    'normal' => 'Evde Yok+Kullanılmıyor / Sayaç Normal'
];
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0 font-size-18">Riskli İşlemler Raporu</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="javascript: void(0);">Raporlar</a></li>
                        <li class="breadcrumb-item active">Riskli İşlemler</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="GET" action="" id="filterForm">
                        <input type="hidden" name="p" value="raporlar/riskli-islemler">
                        <div class="row g-3">
                            <div class="col-md-2">
                                <?= Form::FormFloatInput('text', 'start_date', $startDate, 'Başlangıç Tarihi', 'Başlangıç Tarihi', 'calendar', 'form-control flatpickr') ?>
                            </div>
                            <div class="col-md-2">
                                <?= Form::FormFloatInput('text', 'end_date', $endDate, 'Bitiş Tarihi', 'Bitiş Tarihi', 'calendar', 'form-control flatpickr') ?>
                            </div>
                            <div class="col-md-2">
                                <?= Form::FormSelect2('region', $regionOptions, $region, 'Bölge', 'globe', 'key', '', 'form-select select2') ?>
                            </div>
                            <div class="col-md-2">
                                <?= Form::FormSelect2('defter', $defterOptions, $defter, 'Defter', 'book', 'key', '', 'form-select select2') ?>
                            </div>
                            <div class="col-md-2">
                                <?= Form::FormSelect2('calc_type', $calcTypeOptions, $calcType, 'Hesaplama Yöntemi', 'hash', 'key', '', 'form-select select2') ?>
                            </div>
                            <div class="col-md-1">
                                <?= Form::FormFloatInput('number', 'threshold', $thresholdInput, 'Risk Oranı (%)', 'Risk Oranı (%)', 'percent', 'form-control') ?>
                            </div>
                            <div class="col-md-1 d-flex align-items-end">
                                <button type="submit" class="btn btn-dark w-100 h-100" style="min-height: 58px;">
                                    <i class="bx bx-filter-alt"></i>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-transparent border-bottom">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <h5 class="card-title mb-0">Riskli Personel Listesi</h5>
                            <p class="text-muted small mb-0">Belirlenen kriterlere göre risk oranı yüksek olan işlemler listelenmektedir.</p>
                        </div>
                        <div class="flex-shrink-0">
                            <button type="button" class="btn btn-soft-success btn-sm" id="exportExcel">
                                <i class="mdi mdi-file-excel me-1"></i> Excel'e Aktar
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle table-nowrap mb-0 datatable" id="riskyTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Personel / Ekip</th>
                                    <th>Bölge</th>
                                    <th class="text-center">Toplam Abone</th>
                                    <th class="text-center">Sayaç Normal</th>
                                    <th class="text-center text-danger">Evde Yok + Kull.</th>
                                    <th class="text-center">Hesaplama</th>
                                    <th class="text-center">Risk Oranı</th>
                                    <th class="text-center">Durum</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $riskyData = $EndeksOkuma->getRiskyPersonnel($startDate, $endDate, $region, $defter, $calcType, $threshold);
                                if (!empty($riskyData)): 
                                    foreach ($riskyData as $row): 
                                        $denominator = ($calcType === 'normal') ? $row->normal_sayisi : $row->toplam_abone_sayisi;
                                        $ratio = ($denominator > 0) ? ($row->evde_yok_sayisi / $denominator) * 100 : 0;
                                        
                                        $riskClass = 'bg-warning';
                                        $riskText = 'Riskli';
                                        if ($ratio >= 80) {
                                            $riskClass = 'bg-danger';
                                            $riskText = 'Yüksek Risk';
                                        }
                                ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-md flex-shrink-0 me-3">
                                                    <?php if (!empty($row->personel_resim) && file_exists($row->personel_resim)): ?>
                                                        <img src="<?= $row->personel_resim ?>" alt="" class="avatar-title rounded-circle shadow-sm">
                                                    <?php else: ?>
                                                        <span class="avatar-title bg-primary-subtle text-primary rounded-circle font-size-16 fw-bold">
                                                            <?= mb_substr($row->personel_adi ?? '?', 0, 1) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <h5 class="font-size-15 mb-1 fw-bold"><?= htmlspecialchars($row->personel_adi ?? 'Bilinmiyor') ?></h5>
                                                    <p class="text-muted mb-0 font-size-12"><i class="bx bx-group me-1"></i><?= htmlspecialchars($row->ekip_adi ?? '-') ?></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-soft-info text-info"><?= htmlspecialchars($row->bolge ?? '-') ?></span>
                                        </td>
                                        <td class="text-center fw-medium"><?= number_format($row->toplam_abone_sayisi, 0, ',', '.') ?></td>
                                        <td class="text-center"><?= number_format($row->normal_sayisi, 0, ',', '.') ?></td>
                                        <td class="text-center text-danger fw-bold"><?= number_format($row->evde_yok_sayisi, 0, ',', '.') ?></td>
                                        <td class="text-center">
                                            <span class="badge bg-light text-dark border">
                                                <?= $calcType === 'normal' ? 'v/Sayaç Normal' : 'v/Toplam Abone' ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex align-items-center justify-content-center">
                                                <div class="flex-grow-1 me-2" style="max-width: 100px;">
                                                    <div class="progress animated-progress progress-sm">
                                                        <div class="progress-bar <?= $ratio >= 80 ? 'bg-danger' : 'bg-warning' ?>" role="progressbar" style="width: <?= $ratio ?>%" aria-valuenow="<?= $ratio ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                                    </div>
                                                </div>
                                                <span class="fw-bold <?= $ratio >= 80 ? 'text-danger' : 'text-warning' ?>">%<?= number_format($ratio, 1) ?></span>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge <?= $riskClass ?>"><?= $riskText ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        if (typeof flatpickr !== 'undefined') {
            $(".flatpickr").flatpickr({
                dateFormat: "d.m.Y",
                locale: "tr"
            });
        }
        
        if (typeof $.fn.select2 !== 'undefined') {
            $('.select2').select2({
                width: '100%'
            });
        }

        // DataTable nesnesini yakala
        let table;
        setTimeout(function() {
            if ($.fn.DataTable.isDataTable('#riskyTable')) {
                table = $('#riskyTable').DataTable();
            } else {
                table = $('#riskyTable').DataTable({
                    language: {
                        url: "assets/js/tr.json"
                    },
                    pageLength: 25,
                    responsive: true,
                    dom: 't<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>'
                });
            }
        }, 100);

        // Manuel Excel Aktarımı (HTML Formatında - Daha Güvenli)
        $('#exportExcel').on('click', function() {
            let table_html = '<table border="1">';
            
            // Başlıkları al
            table_html += '<tr>';
            $('#riskyTable thead tr:first th').each(function() {
                table_html += '<th style="background-color: #f8f9fa;">' + $(this).text().trim() + '</th>';
            });
            table_html += '</tr>';

            // Verileri al (Sadece filtrelenmiş olanlar)
            let rows = table.rows({ filter: 'applied' }).nodes();
            $(rows).each(function() {
                table_html += '<tr>';
                $(this).find('td').each(function() {
                    let cell = $(this).clone();
                    // Temizleme işlemleri
                    if (cell.find('h5').length) {
                        // Personel ve Ekibi ayır veya birleştir ama temizle
                        let pName = cell.find('h5').text().trim();
                        let eName = cell.find('p').text().trim();
                        table_html += '<td>' + pName + ' (' + eName + ')</td>';
                    } else if (cell.find('.fw-bold').length) {
                        table_html += '<td>' + cell.find('.fw-bold').text().trim() + '</td>';
                    } else {
                        table_html += '<td>' + cell.text().trim() + '</td>';
                    }
                });
                table_html += '</tr>';
            });
            table_html += '</table>';

            let blob = new Blob(['\ufeff' + table_html], { type: 'application/vnd.ms-excel' });
            let link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = 'riskli_islemler_raporu.xls';
            link.click();
        });
    });
</script>
