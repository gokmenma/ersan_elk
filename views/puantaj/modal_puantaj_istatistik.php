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
$Tanimlamalar = new \App\Model\TanimlamalarModel();

// İş emri sonucu bazlı birim ücret ve tür adı map'i oluştur
$isTurleri = $Tanimlamalar->getIsTurleri();
$birimUcretMap = [];
$turAdiMap = [];
foreach ($isTurleri as $isTuru) {
    if (!empty($isTuru->is_emri_sonucu)) {
        $birimUcretMap[$isTuru->is_emri_sonucu] = floatval(\App\Helper\Helper::formattedMoneyToNumber($isTuru->is_turu_ucret ?? 0));
        $turAdiMap[$isTuru->is_emri_sonucu] = $isTuru->tur_adi ?? '';
    }
}

// Convert dates for SQL
$sqlStart = \App\Helper\Date::convertExcelDate($startDate, 'Y-m-d') ?: $startDate;
$sqlEnd = \App\Helper\Date::convertExcelDate($endDate, 'Y-m-d') ?: $endDate;

// Common WHERE clause - normalizasyon sonrası COALESCE kullanılıyor
$where = " WHERE t.firma_id = ? AND t.silinme_tarihi IS NULL AND t.tarih >= ? AND t.tarih <= ?";
$params = [$firmaId, $sqlStart, $sqlEnd];

if ($personelId) {
    $where .= " AND t.personel_id = ?";
    $params[] = $personelId;
}

if ($workType) {
    // Hem yeni hem eski alandan filtrele
    $where .= " AND (tn.tur_adi = ? OR t.is_emri_tipi = ?)";
    $params[] = $workType;
    $params[] = $workType;
}

if ($workResult) {
    // Hem yeni hem eski alandan filtrele
    $where .= " AND (tn.is_emri_sonucu = ? OR t.is_emri_sonucu = ?)";
    $params[] = $workResult;
    $params[] = $workResult;
}

// DataTable sütun filtrelerini işle (yeni sıralama: 0-Tarih, 1-EkipKodu, 2-Personel, 3-İşEmriTipi, 4-İşEmriSonucu, 5-Sonuçlanmış, 6-AçıkOlanlar)
$colFilterMap = [
    0 => 'DATE_FORMAT(t.tarih, "%d.%m.%Y")',
    1 => 'ek.tur_adi',
    2 => 'p.adi_soyadi',
    3 => 'COALESCE(tn.tur_adi, t.is_emri_tipi)',
    4 => 'COALESCE(tn.is_emri_sonucu, t.is_emri_sonucu)',
    5 => 't.sonuclanmis',
    6 => 't.acik_olanlar'
];

foreach ($colFilterMap as $colIndex => $colField) {
    $colKey = 'col_' . $colIndex;
    if (!empty($_GET[$colKey])) {
        $where .= " AND $colField LIKE ?";
        $params[] = '%' . $_GET[$colKey] . '%';
    }
}

// Ekip kodu filtresi için ek LEFT JOIN gerekli
$needsEkipJoin = !empty($_GET['col_1']);
$ekipJoin = $needsEkipJoin ? "LEFT JOIN tanimlamalar ek ON t.ekip_kodu_id = ek.id" : "";

// Personel filtresi için ek LEFT JOIN gerekli  
$needsPersonelJoin = !empty($_GET['col_2']);
$personelJoin = $needsPersonelJoin ? "LEFT JOIN personel p ON t.personel_id = p.id" : "";

// 1. Query for statistics by work type - COALESCE ile hem yeni hem eski alanlardan
$sqlType = "SELECT COALESCE(tn.tur_adi, t.is_emri_tipi) as is_emri_tipi, 
               COUNT(*) as kayit_sayisi, 
               SUM(t.sonuclanmis) as toplam_sonuclanmis,
               SUM(t.acik_olanlar) as toplam_acik
        FROM yapilan_isler t
        LEFT JOIN tanimlamalar tn ON t.is_emri_sonucu_id = tn.id
        $ekipJoin
        $personelJoin
        $where
        GROUP BY COALESCE(tn.tur_adi, t.is_emri_tipi) ORDER BY kayit_sayisi DESC";

$stmtType = $Puantaj->db->prepare($sqlType);
$stmtType->execute($params);
$statsType = $stmtType->fetchAll(PDO::FETCH_OBJ);

// 2. Query for statistics by work order result - COALESCE ile hem yeni hem eski alanlardan
$sqlResult = "SELECT COALESCE(tn.is_emri_sonucu, t.is_emri_sonucu) as is_emri_sonucu,
               COALESCE(tn.tur_adi, t.is_emri_tipi) as is_emri_tipi,
               COUNT(*) as kayit_sayisi, 
               SUM(t.sonuclanmis) as toplam_sonuclanmis,
               SUM(t.acik_olanlar) as toplam_acik
        FROM yapilan_isler t
        LEFT JOIN tanimlamalar tn ON t.is_emri_sonucu_id = tn.id
        $ekipJoin
        $personelJoin
        $where
        GROUP BY COALESCE(tn.is_emri_sonucu, t.is_emri_sonucu), COALESCE(tn.tur_adi, t.is_emri_tipi) 
        ORDER BY kayit_sayisi DESC";

$stmtResult = $Puantaj->db->prepare($sqlResult);
$stmtResult->execute($params);
$statsResult = $stmtResult->fetchAll(PDO::FETCH_OBJ);

$grandTotalType = ['kayit' => 0, 'sonuclanmis' => 0, 'acik' => 0];
$grandTotalResult = ['kayit' => 0, 'sonuclanmis' => 0, 'acik' => 0];
?>

<?php 
// Sütun indekslerini Türkçe başlıklara eşle
$columnNames = [
    0 => 'Tarih',
    1 => 'Ekip Kodu',
    2 => 'Personel',
    3 => 'İş Emri Tipi',
    4 => 'İş Emri Sonucu',
    5 => 'Sonuçlanmış',
    6 => 'Açık Olanlar'
];

// Aktif sütun filtrelerini topla
$activeFilters = [];
for ($i = 0; $i <= 6; $i++) {
    if (!empty($_GET['col_' . $i])) {
        $activeFilters[] = $columnNames[$i] . ' = ' . htmlspecialchars($_GET['col_' . $i]);
    }
}
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
    <?php if (!empty($activeFilters)): ?>
    <div class="alert alert-info py-2 mb-3">
        <i class="bx bx-filter-alt me-1"></i>
        <strong>Uygulanan Filtreler:</strong> <?= implode(' | ', $activeFilters) ?>
    </div>
    <?php endif; ?>
    
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
                    <th>İş Türü</th>
                    <th>İş Emri Sonucu</th>
                    <th class="text-center">Birim Ücret</th>
                    <th class="text-center">Kayıt Sayısı</th>
                    <th class="text-center">Sonuçlanmış</th>
                    <th class="text-center">Açık Olanlar</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($statsResult)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-3 text-muted">Bu kriterlere uygun veri bulunamadı.</td>
                    </tr>
                <?php else: ?>
                    <?php
                    $grandTotalBirimUcret = 0;
                    foreach ($statsResult as $row):
                        $grandTotalResult['kayit'] += $row->kayit_sayisi;
                        $grandTotalResult['sonuclanmis'] += $row->toplam_sonuclanmis;
                        $grandTotalResult['acik'] += $row->toplam_acik;
                        // Birim ücreti al
                        $birimUcret = $birimUcretMap[$row->is_emri_sonucu] ?? 0;
                        $grandTotalBirimUcret += $birimUcret * $row->toplam_sonuclanmis;
                        ?>
                        <tr>
                            <td class="fw-medium">
                                <?= $row->is_emri_tipi ?: '<span class="text-muted">-</span>' ?>
                            </td>
                            <td class="fw-medium"><?= $row->is_emri_sonucu ?: 'Belirtilmemiş' ?></td>
                            <td class="text-center">
                                <?php if ($birimUcret > 0): ?>
                                    <span class="badge bg-info"><?= number_format($birimUcret, 2, ',', '.') ?> ₺</span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
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
                        <td colspan="2">TOPLAM</td>
                        <td class="text-center">
                            <span class="badge bg-success"><?= number_format($grandTotalBirimUcret, 2, ',', '.') ?> ₺</span>
                        </td>
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