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
$calcType = $_GET['calc_type'] ?? 'total'; // 'total' or 'normal'
$threshold = $_GET['threshold'] ?? 0.6;

$regionList = $Tanimlar->getEkipBolgeleri();
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
                                <?= Form::FormFloatInput('text', 'start_date', $startDate, 'Başlangıç Tarihi', '', 'calendar', 'form-control flatpickr') ?>
                            </div>
                            <div class="col-md-2">
                                <?= Form::FormFloatInput('text', 'end_date', $endDate, 'Bitiş Tarihi', '', 'calendar', 'form-control flatpickr') ?>
                            </div>
                            <div class="col-md-2">
                                <?= Form::FormSelect2('region', $regionOptions, $region, 'Bölge', 'globe', 'key', '', 'form-select select2') ?>
                            </div>
                            <div class="col-md-2">
                                <?= Form::FormSelect2('defter', $defterOptions, $defter, 'Defter', 'book', 'key', '', 'form-select select2') ?>
                            </div>
                            <div class="col-md-3">
                                <?= Form::FormSelect2('calc_type', $calcTypeOptions, $calcType, 'Hesaplama Yöntemi', 'calculator', 'key', '', 'form-select select2') ?>
                            </div>
                            <div class="col-md-1 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bx bx-filter-alt me-1"></i> Filtrele
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
                            <button type="button" class="btn btn-soft-success btn-sm" id="btnExportExcel">
                                <i class="mdi mdi-file-excel me-1"></i> Excel'e Aktar
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle table-nowrap mb-0" id="riskyTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Personel / Ekip</th>
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
                                if (empty($riskyData)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">Kayıt bulunamadı.</td>
                                    </tr>
                                <?php else: 
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
                                                <div class="avatar-xs flex-shrink-0 me-3">
                                                    <span class="avatar-title bg-primary-subtle text-primary rounded-circle font-size-12">
                                                        <?= mb_substr($row->personel_adi ?? '?', 0, 1) ?>
                                                    </span>
                                                </div>
                                                <div>
                                                    <h5 class="font-size-14 mb-1"><?= htmlspecialchars($row->personel_adi ?? 'Bilinmiyor') ?></h5>
                                                    <p class="text-muted mb-0 font-size-12"><?= htmlspecialchars($row->ekip_adi ?? '-') ?></p>
                                                </div>
                                            </div>
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

        $('#btnExportExcel').on('click', function() {
            let table = document.getElementById('riskyTable');
            let html = table.outerHTML;
            let url = 'data:application/vnd.ms-excel,' + encodeURIComponent(html);
            let link = document.createElement('a');
            link.download = 'riskli_islemler_raporu.xls';
            link.href = url;
            link.click();
        });
    });
</script>
