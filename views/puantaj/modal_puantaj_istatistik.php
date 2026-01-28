<?php
require_once dirname(__DIR__, 2) . '/Autoloader.php';
session_start();

$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$personelId = $_GET['personel_id'] ?? '';
$workType = $_GET['work_type'] ?? '';
$firmaId = $_SESSION['firma_id'] ?? 0;

$Puantaj = new \App\Model\PuantajModel();

// Convert dates for SQL
$sqlStart = \App\Helper\Date::convertExcelDate($startDate, 'Y-m-d') ?: $startDate;
$sqlEnd = \App\Helper\Date::convertExcelDate($endDate, 'Y-m-d') ?: $endDate;

// Query for statistics by work type
$sql = "SELECT is_emri_tipi, 
               COUNT(*) as kayit_sayisi, 
               SUM(sonuclanmis) as toplam_sonuclanmis,
               SUM(acik_olanlar) as toplam_acik
        FROM yapilan_isler 
        WHERE firma_id = ? AND tarih >= ? AND tarih <= ?";

$params = [$firmaId, $sqlStart, $sqlEnd];

if ($personelId) {
    $sql .= " AND personel_id = ?";
    $params[] = $personelId;
}

if ($workType) {
    $sql .= " AND is_emri_tipi = ?";
    $params[] = $workType;
}

$sql .= " GROUP BY is_emri_tipi ORDER BY kayit_sayisi DESC";

$stmt = $Puantaj->db->prepare($sql);
$stmt->execute($params);
$stats = $stmt->fetchAll(PDO::FETCH_OBJ);

$grandTotal = [
    'kayit' => 0,
    'sonuclanmis' => 0,
    'acik' => 0
];
?>

<div class="mb-3 d-flex justify-content-between align-items-center">
    <div class="text-muted small">
        <i class="bx bx-calendar me-1"></i>
        <strong>Tarih Aralığı:</strong> <?= htmlspecialchars($startDate) ?> - <?= htmlspecialchars($endDate) ?>
    </div>
    <button type="button" class="btn btn-sm btn-success" id="btnExportPuantajStatsExcel">
        <i class="bx bx-spreadsheet me-1"></i> Excel'e Aktar
    </button>
</div>

<div class="table-responsive">
    <table class="table table-sm table-bordered table-striped mb-0" id="puantajStatsTableExport">
        <thead class="table-light">
            <tr>
                <th>İş Emri Tipi</th>
                <th class="text-center">Kayıt Sayısı</th>
                <th class="text-center">Sonuçlanmış</th>
                <th class="text-center">Açık Olanlar</th>
                <th class="text-center">Başarı Oranı</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($stats)): ?>
                <tr>
                    <td colspan="5" class="text-center py-3 text-muted">Bu kriterlere uygun veri bulunamadı.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($stats as $row):
                    $grandTotal['kayit'] += $row->kayit_sayisi;
                    $grandTotal['sonuclanmis'] += $row->toplam_sonuclanmis;
                    $grandTotal['acik'] += $row->toplam_acik;
                    $total = $row->toplam_sonuclanmis + $row->toplam_acik;
                    $ratio = $total > 0 ? ($row->toplam_sonuclanmis / $total) * 100 : 0;
                    ?>
                    <tr>
                        <td class="fw-medium"><?= $row->is_emri_tipi ?></td>
                        <td class="text-center"><?= $row->kayit_sayisi ?></td>
                        <td class="text-center text-success"><?= number_format($row->toplam_sonuclanmis, 0, ',', '.') ?></td>
                        <td class="text-center text-danger"><?= number_format($row->toplam_acik, 0, ',', '.') ?></td>
                        <td class="text-center">
                            %<?= number_format($ratio, 2, ',', '.') ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        <?php if (!empty($stats)):
            $totalAll = $grandTotal['sonuclanmis'] + $grandTotal['acik'];
            $ratioAll = $totalAll > 0 ? ($grandTotal['sonuclanmis'] / $totalAll) * 100 : 0;
            ?>
            <tfoot class="table-light fw-bold">
                <tr>
                    <td>TOPLAM</td>
                    <td class="text-center"><?= $grandTotal['kayit'] ?></td>
                    <td class="text-center text-success"><?= number_format($grandTotal['sonuclanmis'], 0, ',', '.') ?></td>
                    <td class="text-center text-danger"><?= number_format($grandTotal['acik'], 0, ',', '.') ?></td>
                    <td class="text-center">%<?= number_format($ratioAll, 2, ',', '.') ?></td>
                </tr>
            </tfoot>
        <?php endif; ?>
    </table>
</div>

<script>
    $('#btnExportPuantajStatsExcel').on('click', function () {
        var table = document.getElementById('puantajStatsTableExport');
        var html = table.outerHTML;

        // Add meta tag for UTF-8 and BOM for Excel to recognize encoding
        var excelHtml = '<html><head><meta charset="utf-8"></head><body>' + html + '</body></html>';
        var blob = new Blob(['\ufeff', excelHtml], {
            type: 'application/vnd.ms-excel'
        });

        var url = URL.createObjectURL(blob);
        var link = document.createElement('a');
        link.download = 'puantaj_istatistikleri.xls';
        link.href = url;
        link.click();

        // Clean up
        setTimeout(function () {
            URL.revokeObjectURL(url);
        }, 100);
    });
</script>