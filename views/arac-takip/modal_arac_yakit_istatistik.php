<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Model\AracYakitModel;
use App\Helper\Date;

$Yakit = new AracYakitModel();

$baslangic = !empty($_GET['baslangic']) ? Date::Ymd($_GET['baslangic']) : null;
$bitis = !empty($_GET['bitis']) ? Date::Ymd($_GET['bitis']) : null;
$arac_id = isset($_GET['arac_id']) && $_GET['arac_id'] !== '' ? intval($_GET['arac_id']) : null;

$stats = $Yakit->getStats(null, null, $baslangic, $bitis, $arac_id);
$ozetler = $Yakit->getRangeOzet($baslangic, $bitis, $arac_id);
?>

<div class="row g-3">
    <div class="col-12">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h6 class="mb-1">Tarih Aralığı: <?php echo Date::dmY($baslangic); ?> - <?php echo Date::dmY($bitis); ?>
                </h6>
            </div>
            <button type="button" class="btn btn-success btn-sm" id="btnExportYakitStats">
                <i class="bx bx-file me-1"></i> Excel'e Aktar
            </button>
        </div>
    </div>

    <div class="col-12">
        <div class="table-responsive">
            <table class="table table-bordered table-hover mb-0" id="yakitStatsTable">
                <thead class="table-light">
                    <tr>
                        <th>Araç / Plaka</th>
                        <th class="text-center">Kayıt Sayısı</th>
                        <th class="text-end">Toplam Litre</th>
                        <th class="text-end">Toplam Tutar</th>
                        <th class="text-end">Toplam KM</th>
                        <th class="text-end">Ort. Tüketim (100km)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $totalLitre = 0;
                    $totalTutar = 0;
                    $totalKm = 0;
                    foreach ($ozetler as $ozet):
                        $totalLitre += $ozet->toplam_litre;
                        $totalTutar += $ozet->toplam_tutar;
                        $totalKm += $ozet->toplam_km;
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo $ozet->plaka; ?></strong><br>
                                <small class="text-muted"><?php echo $ozet->marka . ' ' . $ozet->model; ?></small>
                            </td>
                            <td class="text-center"><?php echo $ozet->kayit_sayisi; ?></td>
                            <td class="text-end"><?php echo number_format($ozet->toplam_litre, 2, ',', '.'); ?> L</td>
                            <td class="text-end"><?php echo number_format($ozet->toplam_tutar, 2, ',', '.'); ?> ₺</td>
                            <td class="text-end"><?php echo number_format($ozet->toplam_km, 0, ',', '.'); ?> km</td>
                            <td class="text-end">
                                <span class="badge bg-soft-info text-info">
                                    % <?php echo number_format($ozet->ortalama_tuketim, 2, ',', '.'); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td>TOPLAM</td>
                        <td class="text-center"><?php echo $stats->toplam_kayit; ?></td>
                        <td class="text-end"><?php echo number_format($totalLitre, 2, ',', '.'); ?> L</td>
                        <td class="text-end"><?php echo number_format($totalTutar, 2, ',', '.'); ?> ₺</td>
                        <td class="text-end"><?php echo number_format($totalKm, 0, ',', '.'); ?> km</td>
                        <td class="text-end">
                            <?php
                            $genelOrtalama = $totalKm > 0 ? ($totalLitre / $totalKm) * 100 : 0;
                            echo '% ' . number_format($genelOrtalama, 2, ',', '.');
                            ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<script>
    $(document).ready(function () {
        $('#btnExportYakitStats').off('click').on('click', function () {
            var table = document.getElementById('yakitStatsTable');
            var html = table.outerHTML;

            // Excel formatı için gerekli meta veriler
            var excelFile = "<html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:x='urn:schemas-microsoft-com:office:excel' xmlns='http://www.w3.org/TR/REC-html40'>";
            excelFile += "<head><meta charset='utf-8'><!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Yakit İstatistikleri</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]--></head>";
            excelFile += "<body>" + html + "</body></html>";

            var blob = new Blob([excelFile], { type: 'application/vnd.ms-excel' });
            var url = URL.createObjectURL(blob);

            var link = document.createElement('a');
            link.download = 'yakit_istatistikleri.xls';
            link.href = url;
            link.click();
        });
    });
</script>