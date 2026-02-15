<?php
require_once dirname(__DIR__, 2) . '/Autoloader.php';
session_start();

$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$personelId = $_GET['personel_id'] ?? '';
$firmaId = $_SESSION['firma_id'] ?? 0;

$EndeksOkuma = new \App\Model\EndeksOkumaModel();

// Convert dates for SQL
$sqlStart = \App\Helper\Date::convertExcelDate($startDate, 'Y-m-d') ?: $startDate;
$sqlEnd = \App\Helper\Date::convertExcelDate($endDate, 'Y-m-d') ?: $endDate;

// Query for statistics by region
$sql = "SELECT bolge, 
               COUNT(*) as kayit_sayisi, 
               SUM(okunan_abone_sayisi) as toplam_abone,
               AVG(okuma_performansi) as ort_performans,
               SUM(sarfiyat) as toplam_sarfiyat,
               SUM(tahakkuk) as toplam_tahakkuk
        FROM endeks_okuma 
        WHERE firma_id = ? AND tarih >= ? AND tarih <= ? AND silinme_tarihi IS NULL";

$params = [$firmaId, $sqlStart, $sqlEnd];
if ($personelId) {
    $sql .= " AND personel_id = ?";
    $params[] = $personelId;
}

$sql .= " GROUP BY bolge ORDER BY toplam_abone DESC";

$stmt = $EndeksOkuma->db->prepare($sql);
$stmt->execute($params);
$stats = $stmt->fetchAll(PDO::FETCH_OBJ);

$grandTotal = [
    'kayit' => 0,
    'abone' => 0,
    'sarfiyat' => 0,
    'tahakkuk' => 0,
    'perf_sum' => 0,
    'count' => 0
];
?>

<div class="mb-3 d-flex justify-content-between align-items-center">
    <div class="text-muted small">
        <i class="bx bx-calendar me-1"></i>
        <strong>Tarih Aralığı:</strong> <?= htmlspecialchars($startDate) ?> - <?= htmlspecialchars($endDate) ?>
    </div>
    <button type="button" class="btn btn-sm btn-success" id="btnExportStatsExcel">
        <i class="bx bx-spreadsheet me-1"></i> Excel'e Aktar
    </button>
</div>

<div class="table-responsive">
    <table class="table table-sm table-bordered table-striped mb-0" id="statsTableExport">
        <thead class="table-light">
            <tr>
                <th>Bölge</th>
                <th class="text-center">Ekip Sayısı</th>
                <th class="text-center">Okunan Abone</th>
                <th class="text-center">Sarfiyat</th>
                <th class="text-center">Tahakkuk</th>
                <th class="text-center">Ort. Perf.</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($stats)): ?>
                <tr>
                    <td colspan="6" class="text-center py-3 text-muted">Bu tarih aralığında veri bulunamadı.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($stats as $row):
                    $grandTotal['kayit'] += $row->kayit_sayisi;
                    $grandTotal['abone'] += $row->toplam_abone;
                    $grandTotal['sarfiyat'] += $row->toplam_sarfiyat;
                    $grandTotal['tahakkuk'] += $row->toplam_tahakkuk;
                    $grandTotal['perf_sum'] += $row->ort_performans;
                    $grandTotal['count']++;
                    ?>
                    <tr>
                        <td class="fw-medium">
                            <?= $row->bolge ?>
                        </td>
                        <td class="text-center">
                            <?= $row->kayit_sayisi ?>
                        </td>
                        <td class="text-center">
                            <?= number_format($row->toplam_abone, 0, ',', '.') ?>
                        </td>
                        <td class="text-center">
                            <?= number_format($row->toplam_sarfiyat, 2, ',', '.') ?>
                        </td>
                        <td class="text-center">
                            <?= number_format($row->toplam_tahakkuk, 2, ',', '.') ?>
                        </td>
                        <td class="text-center">
                            %<?= number_format($row->ort_performans, 2, ',', '.') ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        <?php if (!empty($stats)): ?>
            <tfoot class="table-light fw-bold">
                <tr>
                    <td>TOPLAM</td>
                    <td class="text-center">
                        <?= $grandTotal['kayit'] ?>
                    </td>
                    <td class="text-center">
                        <?= number_format($grandTotal['abone'], 0, ',', '.') ?>
                    </td>
                    <td class="text-center">
                        <?= number_format($grandTotal['sarfiyat'], 2, ',', '.') ?>
                    </td>
                    <td class="text-center">
                        <?= number_format($grandTotal['tahakkuk'], 2, ',', '.') ?>
                    </td>
                    <td class="text-center">%
                        <?= number_format($grandTotal['perf_sum'] / $grandTotal['count'], 2, ',', '.') ?>
                    </td>
                </tr>
            </tfoot>
        <?php endif; ?>
    </table>
</div>

<script>
    $('#btnExportStatsExcel').on('click', function () {
        var table = document.getElementById('statsTableExport');
        var html = table.outerHTML;

        // Add meta tag for UTF-8 and BOM for Excel to recognize encoding
        var excelHtml = '<html><head><meta charset="utf-8"></head><body>' + html + '</body></html>';
        var blob = new Blob(['\ufeff', excelHtml], {
            type: 'application/vnd.ms-excel'
        });

        var url = URL.createObjectURL(blob);
        var link = document.createElement('a');
        link.download = 'okuma_istatistikleri.xls';
        link.href = url;
        link.click();

        // Clean up
        setTimeout(function () {
            URL.revokeObjectURL(url);
        }, 100);
    });
</script>