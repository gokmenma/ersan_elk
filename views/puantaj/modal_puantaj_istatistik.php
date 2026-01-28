<?php
require_once dirname(__DIR__, 2) . '/Autoloader.php';
session_start();

$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$personelId = $_GET['personel_id'] ?? '';
$workType = $_GET['work_type'] ?? '';
$workResult = $_GET['work_result'] ?? '';
$firmaId = $_SESSION['firma_id'] ?? 0;

$Puantaj = new \App\Model\PuantajModel();

// Convert dates for SQL
$sqlStart = \App\Helper\Date::convertExcelDate($startDate, 'Y-m-d') ?: $startDate;
$sqlEnd = \App\Helper\Date::convertExcelDate($endDate, 'Y-m-d') ?: $endDate;

// Common WHERE clause
$where = " WHERE firma_id = ? AND tarih >= ? AND tarih <= ?";
$params = [$firmaId, $sqlStart, $sqlEnd];

if ($personelId) {
    $where .= " AND personel_id = ?";
    $params[] = $personelId;
}

if ($workType) {
    $where .= " AND is_emri_tipi = ?";
    $params[] = $workType;
}

if ($workResult) {
    $where .= " AND is_emri_sonucu = ?";
    $params[] = $workResult;
}

// 1. Query for statistics by work type
$sqlType = "SELECT is_emri_tipi, 
               COUNT(*) as kayit_sayisi, 
               SUM(sonuclanmis) as toplam_sonuclanmis,
               SUM(acik_olanlar) as toplam_acik
        FROM yapilan_isler 
        $where
        GROUP BY is_emri_tipi ORDER BY kayit_sayisi DESC";

$stmtType = $Puantaj->db->prepare($sqlType);
$stmtType->execute($params);
$statsType = $stmtType->fetchAll(PDO::FETCH_OBJ);

// 2. Query for statistics by work order result
$sqlResult = "SELECT is_emri_sonucu, 
               COUNT(*) as kayit_sayisi, 
               SUM(sonuclanmis) as toplam_sonuclanmis,
               SUM(acik_olanlar) as toplam_acik
        FROM yapilan_isler 
        $where
        GROUP BY is_emri_sonucu ORDER BY kayit_sayisi DESC";

$stmtResult = $Puantaj->db->prepare($sqlResult);
$stmtResult->execute($params);
$statsResult = $stmtResult->fetchAll(PDO::FETCH_OBJ);

$grandTotalType = ['kayit' => 0, 'sonuclanmis' => 0, 'acik' => 0];
$grandTotalResult = ['kayit' => 0, 'sonuclanmis' => 0, 'acik' => 0];
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

<div id="puantajStatsExportArea">
    <h6 class="text-primary mb-2"><i class="bx bx-list-ul me-1"></i> İş Emri Tipi Bazlı Özet</h6>
    <div class="table-responsive mb-4">
        <table class="table table-sm table-bordered table-striped mb-0" id="puantajTypeTable">
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
                <?php if (empty($statsType)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-3 text-muted">Bu kriterlere uygun veri bulunamadı.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($statsType as $row):
                        $grandTotalType['kayit'] += $row->kayit_sayisi;
                        $grandTotalType['sonuclanmis'] += $row->toplam_sonuclanmis;
                        $grandTotalType['acik'] += $row->toplam_acik;
                        $total = $row->toplam_sonuclanmis + $row->toplam_acik;
                        $ratio = $total > 0 ? ($row->toplam_sonuclanmis / $total) * 100 : 0;
                        ?>
                        <tr>
                            <td class="fw-medium"><?= $row->is_emri_tipi ?></td>
                            <td class="text-center"><?= $row->kayit_sayisi ?></td>
                            <td class="text-center text-success"><?= number_format($row->toplam_sonuclanmis, 0, ',', '.') ?>
                            </td>
                            <td class="text-center text-danger"><?= number_format($row->toplam_acik, 0, ',', '.') ?></td>
                            <td class="text-center">%<?= number_format($ratio, 2, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <?php if (!empty($statsType)):
                $totalAll = $grandTotalType['sonuclanmis'] + $grandTotalType['acik'];
                $ratioAll = $totalAll > 0 ? ($grandTotalType['sonuclanmis'] / $totalAll) * 100 : 0;
                ?>
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td>TOPLAM</td>
                        <td class="text-center"><?= $grandTotalType['kayit'] ?></td>
                        <td class="text-center text-success">
                            <?= number_format($grandTotalType['sonuclanmis'], 0, ',', '.') ?>
                        </td>
                        <td class="text-center text-danger"><?= number_format($grandTotalType['acik'], 0, ',', '.') ?></td>
                        <td class="text-center">%<?= number_format($ratioAll, 2, ',', '.') ?></td>
                    </tr>
                </tfoot>
            <?php endif; ?>
        </table>
    </div>

    <h6 class="text-primary mb-2"><i class="bx bx-check-circle me-1"></i> İş Emri Sonucu Bazlı Özet</h6>
    <div class="table-responsive">
        <table class="table table-sm table-bordered table-striped mb-0" id="puantajResultTable">
            <thead class="table-light">
                <tr>
                    <th>İş Emri Sonucu</th>
                    <th class="text-center">Kayıt Sayısı</th>
                    <th class="text-center">Sonuçlanmış</th>
                    <th class="text-center">Açık Olanlar</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($statsResult)): ?>
                    <tr>
                        <td colspan="4" class="text-center py-3 text-muted">Bu kriterlere uygun veri bulunamadı.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($statsResult as $row):
                        $grandTotalResult['kayit'] += $row->kayit_sayisi;
                        $grandTotalResult['sonuclanmis'] += $row->toplam_sonuclanmis;
                        $grandTotalResult['acik'] += $row->toplam_acik;
                        ?>
                        <tr>
                            <td class="fw-medium"><?= $row->is_emri_sonucu ?: 'Belirtilmemiş' ?></td>
                            <td class="text-center"><?= $row->kayit_sayisi ?></td>
                            <td class="text-center text-success"><?= number_format($row->toplam_sonuclanmis, 0, ',', '.') ?>
                            </td>
                            <td class="text-center text-danger"><?= number_format($row->toplam_acik, 0, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <?php if (!empty($statsResult)): ?>
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td>TOPLAM</td>
                        <td class="text-center"><?= $grandTotalResult['kayit'] ?></td>
                        <td class="text-center text-success">
                            <?= number_format($grandTotalResult['sonuclanmis'], 0, ',', '.') ?>
                        </td>
                        <td class="text-center text-danger"><?= number_format($grandTotalResult['acik'], 0, ',', '.') ?>
                        </td>
                    </tr>
                </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<script>
    $('#btnExportPuantajStatsExcel').on('click', function () {
        var content = document.getElementById('puantajStatsExportArea').innerHTML;

        // Add meta tag for UTF-8 and BOM for Excel to recognize encoding
        var excelHtml = '<html><head><meta charset="utf-8"><style>table{border-collapse:collapse;} th,td{border:1px solid #ccc; padding:5px;}</style></head><body>' + content + '</body></html>';
        var blob = new Blob(['\ufeff', excelHtml], {
            type: 'application/vnd.ms-excel'
        });

        var url = URL.createObjectURL(blob);
        var link = document.createElement('a');
        link.download = 'puantaj_istatistikleri.xls';
        link.href = url;
        link.click();

        setTimeout(function () {
            URL.revokeObjectURL(url);
        }, 100);
    });
</script>