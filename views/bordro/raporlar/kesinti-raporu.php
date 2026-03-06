<?php
/**
 * Kesinti Raporu
 * Personel kesintilerini (İcra, Avans, Diğer) dönem bazlı listeler ve filtreler.
 */

use App\Model\BordroDonemModel;
use App\Model\PersonelKesintileriModel;
use App\Helper\Form;

$BordroDonem = new BordroDonemModel();
$KesintilerModel = new PersonelKesintileriModel();

// Filtre parametreleri
$selectedDonemId = $_GET['donem'] ?? $_SESSION['selectedDonemId'] ?? null;
$selectedTur = $_GET['tur'] ?? '';

$selectedDonem = null;
$kesintiler = [];
$toplamTutar = 0;

if ($selectedDonemId) {
    $selectedDonem = $BordroDonem->getDonemById($selectedDonemId);
    if ($selectedDonem) {
        $kesintiler = $KesintilerModel->getDonemKesintileriRaporu($selectedDonemId, $selectedTur);
        foreach ($kesintiler as $k) {
            $toplamTutar += floatval($k->tutar ?? 0);
        }
    }
}

// Yılları ve dönemleri getir (dönem seçimi için)
$selectedYil = $selectedDonem ? date('Y', strtotime($selectedDonem->baslangic_tarihi)) : date('Y');
$donemler = $BordroDonem->getAllDonems($selectedYil);
$yil_option = $BordroDonem->getYearsByDonem();

$donem_option = [];
foreach ($donemler as $donem) {
    $donem_option[$donem->id] = $donem->donem_adi;
}

$tur_option = [
    '' => 'Tüm Kesintiler',
    'icra' => 'İcra Kesintileri',
    'avans' => 'Avans Kesintileri',
    'diger' => 'Diğer Kesintiler'
];

?>

<div class="container-fluid">
    <?php
    $maintitle = "Bordro";
    $subtitle = "Raporlar";
    $title = "Kesinti Raporu";
    ?>
    <?php include 'layouts/breadcrumb.php'; ?>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header border-bottom bordro-sticky-header">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                        <!-- Left side: Filters -->
                        <div class="d-flex align-items-center bg-white border rounded shadow-sm p-1 gap-2">
                            <div style="min-width: 160px;">
                                <?php echo Form::FormSelect2(
                                    name: 'turSelectRapor',
                                    options: $tur_option,
                                    selectedValue: $selectedTur,
                                    label: 'Kesinti Türü',
                                    icon: 'filter',
                                    class: 'form-control select2'
                                ); ?>
                            </div>
                            <div class="vr mx-1" style="height: 25px; align-self: center;"></div>
                            <div style="min-width: 100px;">
                                <?php echo Form::FormSelect2(
                                    name: 'yilSelectRapor',
                                    options: $yil_option,
                                    selectedValue: $selectedYil,
                                    label: 'Yıl',
                                    icon: 'calendar',
                                    class: 'form-control select2'
                                ); ?>
                            </div>
                            <div class="vr mx-1" style="height: 25px; align-self: center;"></div>
                            <div style="min-width: 160px;">
                                <?php echo Form::FormSelect2(
                                    name: 'donemSelectRapor',
                                    options: $donem_option,
                                    selectedValue: $selectedDonemId,
                                    label: 'Dönem',
                                    icon: 'calendar',
                                    class: 'form-control select2'
                                ); ?>
                            </div>
                        </div>

                        <!-- Right side: Buttons -->
                        <div class="d-flex align-items-center gap-2 ms-auto">
                            <button type="button" class="btn btn-success shadow-sm text-nowrap" id="btnExportCustomExcel">
                                <i class="bx bx-file me-1"></i> Excel'e Aktar
                            </button>
                            <a href="index?p=bordro/raporlar&donem=<?= $selectedDonemId ?>" class="btn btn-secondary shadow-sm text-nowrap">
                                <i class="bx bx-arrow-back me-1"></i> Raporlara Dön
                            </a>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <?php if ($selectedDonem && !empty($kesintiler)): ?>

                        <!-- Tablo -->
                        <div class="table-responsive">
                            <table id="kesintiRaporuTable" class="table table-hover table-bordered nowrap w-100 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 50px;" class="text-center">#</th>
                                        <th>Personel Bilgileri</th>
                                        <th>Kesinti Türü</th>
                                        <th>Açıklama / Detay</th>
                                        <th class="text-end">Tutar</th>
                                        <th class="text-center">Durum</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $sira = 1;
                                    foreach ($kesintiler as $k):
                                        // Tür Rozeti Ayarı
                                        $badgeBg = 'bg-secondary';
                                        $turAdi = 'Diğer';
                                        $normalizedTur = mb_strtolower(trim($k->tur), 'UTF-8');
                                        if ($normalizedTur === 'icra' || $normalizedTur === 'i̇cra') { // account for dotted i
                                            $badgeBg = 'bg-danger';
                                            $turAdi = 'İcra';
                                        } else if ($normalizedTur === 'avans') {
                                            $badgeBg = 'bg-warning text-dark';
                                            $turAdi = 'Avans';
                                        }

                                        // Durum Rozeti Ayarı
                                        $durumBg = 'bg-secondary';
                                        $durumIcon = 'bx-time';
                                        $durumMetin = 'Beklemede';
                                        if ($k->durum === 'onaylandi' || $k->tur === 'icra') {
                                            $durumBg = 'bg-success';
                                            $durumIcon = 'bx-check-circle';
                                            $durumMetin = 'Onaylandı';
                                            if($k->tur === 'icra') $durumMetin = 'Kesinleşti'; // icra ise bekleme yoktur
                                        } elseif ($k->durum === 'reddedildi') {
                                            $durumBg = 'bg-danger';
                                            $durumIcon = 'bx-x-circle';
                                            $durumMetin = 'Reddedildi';
                                        }
                                        ?>
                                        <tr class="<?= $k->durum === 'reddedildi' ? 'text-muted' : '' ?>">
                                            <td class="text-center fw-medium"><?= $sira++ ?></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div>
                                                        <h6 class="mb-0 fw-bold"><?= htmlspecialchars($k->adi_soyadi ?? '-') ?></h6>
                                                        <span class="text-muted small">TC: <code><?= htmlspecialchars($k->tc_kimlik_no ?? '-') ?></code></span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge <?= $badgeBg ?> fs-6 px-2 py-1 shadow-sm"><?= $turAdi ?></span>
                                            </td>
                                            <td>
                                                <div class="text-wrap" style="max-width:300px;">
                                                    <span class="fw-medium text-dark d-block">
                                                        <?= htmlspecialchars($k->aciklama ?? '-') ?>
                                                    </span>
                                                    <?php if ($k->tur === 'icra' && !empty($k->dosya_no)): ?>
                                                        <span class="text-muted small d-block mt-1">
                                                            <i class="bx bx-buildings me-1"></i><?= htmlspecialchars($k->icra_dairesi ?? '-') ?> (<?= htmlspecialchars($k->dosya_no) ?>)
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="text-end fw-bold text-danger" style="font-size: 1.05rem;">
                                                <span class="<?= $k->durum === 'reddedildi' ? 'text-decoration-line-through' : '' ?>">
                                                    <?= number_format(floatval($k->tutar ?? 0), 2, ',', '.') ?> ₺
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge <?= $durumBg ?> px-2 py-1 fs-6">
                                                    <i class="bx <?= $durumIcon ?> me-1 align-middle"></i><?= $durumMetin ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <th colspan="4" class="text-end font-size-14 fw-bold">Toplam Kesinti Tutarı:</th>
                                        <th class="text-end text-danger font-size-16 fw-bold">
                                            <?= number_format($toplamTutar, 2, ',', '.') ?> ₺
                                        </th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                    <?php elseif ($selectedDonem): ?>
                        <div class="text-center py-5">
                            <i class="bx bx-cut display-1 text-muted opacity-50"></i>
                            <h5 class="mt-3">Kayıt Bulunamadı</h5>
                            <p class="text-muted">Bu dönem ve filtreleme kriterlerine uygun kesinti kaydı bulunamadı.</p>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="bx bx-calendar-x display-1 text-muted opacity-50"></i>
                            <h5 class="mt-3">Dönem Seçimi Bekleniyor</h5>
                            <p class="text-muted">Raporu görüntülemek için yukarıdan bir dönem seçiniz.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // DataTable başlat
        if (document.getElementById('kesintiRaporuTable')) {
            destroyAndInitDataTable('#kesintiRaporuTable', {
                pageLength: 25,
                order: [[1, 'asc']], // İsim sırası
                dom: '<"row align-items-center mb-3"<"col-md-6"><"col-md-6"f>>rt<"row align-items-center mt-3"<"col-md-6"i><"col-md-6"p>>'
            });
        }

        // Custom Excel Export from Filtered DataTable rows
        $('#btnExportCustomExcel').on('click', function() {
            var table = $('#kesintiRaporuTable').DataTable();
            
            var html = '<table border="1"><thead><tr>' +
                       '<th>Sira</th><th>Personel Bilgileri</th><th>Kesinti Turu</th><th>Aciklama / Detay</th><th>Tutar</th><th>Durum</th>' +
                       '</tr></thead><tbody>';
            
            table.rows({ search: 'applied' }).nodes().each(function(node, index) {
                var row = $(node);
                var sira = row.find('td:eq(0)').text().trim();
                var adSoyad = row.find('td:eq(1)').find('h6').text().trim();
                var tc = row.find('td:eq(1)').find('code').text().trim();
                var personel = adSoyad + (tc ? ' (TC: ' + tc + ')' : '');
                
                var kesintiTuru = row.find('td:eq(2)').text().trim();
                
                // Keep only visible text in Aciklama, removing small classes if we want or just text
                var aciklamaWrapper = row.find('td:eq(3)').clone();
                aciklamaWrapper.find('.small, i').remove();
                var aciklama = aciklamaWrapper.text().trim();
                
                // Get original text of Tutar without span styles
                var tutarCell = row.find('td:eq(4)');
                var tutarText = tutarCell.text().trim().replace(/₺/g, '').trim(); 
                
                var durum = row.find('td:eq(5)').text().trim();
                
                html += '<tr>' +
                        '<td>' + sira + '</td>' +
                        '<td>' + personel + '</td>' +
                        '<td>' + kesintiTuru + '</td>' +
                        '<td>' + aciklama + '</td>' +
                        '<td>' + tutarText + '</td>' +
                        '<td>' + durum + '</td>' +
                        '</tr>';
            });
            html += '</tbody></table>';
            
            var uri = 'data:application/vnd.ms-excel;base64,';
            var template = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40"><meta charset="utf-8"/><head><!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>{worksheet}</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]--></head><body>{table}</body></html>';
            var base64 = function(s) { return window.btoa(unescape(encodeURIComponent(s))); };
            var format = function(s, c) { return s.replace(/{(\w+)}/g, function(m, p) { return c[p]; }); };
            var ctx = { worksheet: 'Kesinti Raporu', table: html };
            
            var link = document.createElement("a");
            link.download = "Kesinti_Raporu.xls";
            link.href = uri + base64(format(template, ctx));
            link.click();
        });

        const yilSelect = $('[name="yilSelectRapor"]');
        const donemSelect = $('[name="donemSelectRapor"]');
        const turSelect = $('[name="turSelectRapor"]');

        function applyFilters() {
            let url = 'index?p=bordro/raporlar/kesinti-raporu';
            
            let yil = yilSelect.val();
            let donem = donemSelect.val();
            let tur = turSelect.val();

            if (yil) url += '&yil=' + yil;
            if (donem) url += '&donem=' + donem;
            if (tur) url += '&tur=' + tur;
            
            window.location.href = url;
        }

        // Change eventleri
        yilSelect.on('change', applyFilters);
        donemSelect.on('change', applyFilters);
        turSelect.on('change', applyFilters);
    });
</script>
