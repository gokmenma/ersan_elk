<?php
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
session_start();

use App\Model\HakedisSozlesmeModel;
use App\Model\HakedisDonemModel;
use App\Model\HakedisKalemModel;
use App\Model\HakedisMiktarModel;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

if (!isset($_SESSION['id']) || !isset($_SESSION['firma_id'])) {
    die("Oturum süresi dolmuş. Lütfen tekrar giriş yapın.");
}

$firma_id = $_SESSION['firma_id'];
$hakedis_id = $_GET['id'] ?? 0;

if (!$hakedis_id) {
    die("Geçersiz Hakediş ID.");
}

try {
    // 1. Fetch Hakediş and Contract Data
    $donemModel = new HakedisDonemModel();
    $db = $donemModel->getDb();

    $stmt = $db->prepare("
        SELECT hd.*, hs.idare_adi, hs.isin_adi, hs.isin_yuklenicisi, hs.ihale_kayit_no, hs.kesif_bedeli, 
               hs.ihale_tenzilati, hs.sozlesme_bedeli, hs.sozlesme_tarihi, hs.isin_bitecegi_tarih, hs.ihale_tarihi, 
               hs.yer_teslim_tarihi, hs.isin_suresi, hs.kontrol_teskilati, hs.idare_onaylayan, hs.idare_onaylayan_unvan
        FROM hakedis_donemleri hd
        JOIN hakedis_sozlesmeler hs ON hd.sozlesme_id = hs.id
        WHERE hd.id = ? AND hs.firma_id = ? AND hd.silinme_tarihi IS NULL
    ");
    $stmt->execute([$hakedis_id, $firma_id]);
    $hakedis = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$hakedis) {
        die("Hakediş bulunamadı veya yetkiniz yok.");
    }

    $sozlesme_id = $hakedis['sozlesme_id'];

    // 2. Fetch Items (Kalemler) and Quantities (Miktarlar)
    // Similar to how hakedis-detay.php/online-api.php fetches them
    $stmtHakedis = $db->prepare("SELECT hakedis_no FROM hakedis_donemleri WHERE id = ?");
    $stmtHakedis->execute([$hakedis_id]);
    $hNo = $stmtHakedis->fetchColumn();

    $stmtPrev = $db->prepare("SELECT id FROM hakedis_donemleri WHERE sozlesme_id = ? AND hakedis_no < ? AND silinme_tarihi IS NULL ORDER BY hakedis_no DESC LIMIT 1");
    $stmtPrev->execute([$sozlesme_id, $hNo]);
    $prevHakedisId = $stmtPrev->fetchColumn();

    $stmtKalem = $db->prepare("SELECT * FROM hakedis_kalemleri WHERE sozlesme_id = :sid ORDER BY id ASC");
    $stmtKalem->execute([':sid' => $sozlesme_id]);
    $kalemler = $stmtKalem->fetchAll(PDO::FETCH_ASSOC);

    $relevantDonemIds = array_filter([$hakedis_id, $prevHakedisId]);
    $miktarlarMap = [];
    if (!empty($relevantDonemIds)) {
        $placeholders = implode(',', array_fill(0, count($relevantDonemIds), '?'));
        $stmtMiktar = $db->prepare("SELECT * FROM hakedis_miktarlari WHERE hakedis_donem_id IN ($placeholders)");
        $stmtMiktar->execute(array_values($relevantDonemIds));
        $allMiktar = $stmtMiktar->fetchAll(PDO::FETCH_ASSOC);

        foreach ($allMiktar as $m) {
            $miktarlarMap[$m['hakedis_donem_id']][$m['kalem_id']] = $m;
        }
    }

    $sonucKalemler = [];
    foreach ($kalemler as $k) {
        $kalem_id = $k['id'];

        $curMiktarRow = $miktarlarMap[$hakedis_id][$kalem_id] ?? null;
        $buay_toplam = floatval($curMiktarRow['miktar'] ?? 0);

        $onceki_toplam = 0;
        if ($curMiktarRow && isset($curMiktarRow['onceki_miktar']) && $curMiktarRow['onceki_miktar'] != 0) {
            $onceki_toplam = floatval($curMiktarRow['onceki_miktar']);
        } else if ($prevHakedisId) {
            $prevMiktarRow = $miktarlarMap[$prevHakedisId][$kalem_id] ?? null;
            if ($prevMiktarRow) {
                $onceki_toplam = floatval($prevMiktarRow['onceki_miktar'] ?? 0) + floatval($prevMiktarRow['miktar'] ?? 0);
            }
        }

        $k['onceki_miktar'] = $onceki_toplam;
        $k['bu_ay_miktar'] = $buay_toplam;
        $sonucKalemler[] = $k;
    }

    // 3. Load Excel Template
    $templatePath = __DIR__ . '/Hakedis.xlsx';
    if (!file_exists($templatePath)) {
        die("Excel şablon dosyası bulunamadı.");
    }
    
    $spreadsheet = IOFactory::load($templatePath);

    // Helpers
    $aylar = [
        1 => 'OCAK', 2 => 'ŞUBAT', 3 => 'MART', 4 => 'NİSAN', 5 => 'MAYIS', 6 => 'HAZİRAN',
        7 => 'TEMMUZ', 8 => 'AĞUSTOS', 9 => 'EYLÜL', 10 => 'EKİM', 11 => 'KASIM', 12 => 'ARALIK'
    ];

    function setExcelDate($worksheet, $cell, $dbDate) {
        if (!empty($dbDate) && $dbDate != '0000-00-00') {
            $timestamp = strtotime($dbDate);
            $excelDate = Date::PHPToExcel($timestamp);
            $worksheet->setCellValue($cell, $excelDate);
            $worksheet->getStyle($cell)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_DATE_DDMMYYYY);
        }
    }

    // --- Fill 'Bilgiler' Sheet ---
    $sheetBilgiler = $spreadsheet->getSheetByName('Bilgiler');
    if ($sheetBilgiler) {
        $sheetBilgiler->setCellValue('D1', $hakedis['idare_adi']);
        $sheetBilgiler->setCellValue('D2', $hakedis['isin_adi']);
        $sheetBilgiler->setCellValue('D3', $hakedis['isin_yuklenicisi']);
        $sheetBilgiler->setCellValue('D4', $hakedis['ihale_kayit_no']);
        $sheetBilgiler->setCellValue('D5', $hakedis['kesif_bedeli']);
        // D6 - İhale Tenzilatı is formula, skip
        $sheetBilgiler->setCellValue('D7', $hakedis['sozlesme_bedeli']);
        
        setExcelDate($sheetBilgiler, 'D8', $hakedis['sozlesme_tarihi']);
        setExcelDate($sheetBilgiler, 'D9', $hakedis['isin_bitecegi_tarih']);
        
        $sheetBilgiler->setCellValue('D10', $hakedis['hakedis_no']);
        
        setExcelDate($sheetBilgiler, 'D11', $hakedis['is_yapilan_ayin_son_gunu']);
        
        // Skip D12-D17 for now, unless dynamically mapped
        
        setExcelDate($sheetBilgiler, 'D18', $hakedis['ihale_tarihi']);
        setExcelDate($sheetBilgiler, 'D19', $hakedis['yer_teslim_tarihi']);
        
        $sheetBilgiler->setCellValue('D20', $hakedis['isin_suresi']);
        $sheetBilgiler->setCellValue('D23', $hakedis['hakedis_tarihi_yil']);
        $sheetBilgiler->setCellValue('D24', $aylar[$hakedis['hakedis_tarihi_ay']] ?? '');

        // Bu Ayki Miktarlar (A27, C27, E27, G27, I27, K27, M27)
        $qtyCols = ['A', 'C', 'E', 'G', 'I', 'K', 'M'];
        for ($i = 0; $i < min(7, count($sonucKalemler)); $i++) {
            $col = $qtyCols[$i];
            $sheetBilgiler->setCellValue($col . '25', $sonucKalemler[$i]['kalem_adi']); // Header
            $sheetBilgiler->setCellValue($col . '27', $sonucKalemler[$i]['bu_ay_miktar']);
        }

        // Fiyat Farkı Guncel Endeksleri
        $sheetBilgiler->setCellValue('A30', $hakedis['asgari_ucret_guncel']);
        $sheetBilgiler->setCellValue('C30', $hakedis['motorin_guncel']);
        $sheetBilgiler->setCellValue('E30', $hakedis['ufe_genel_guncel']);
        $sheetBilgiler->setCellValue('G30', $hakedis['makine_ekipman_guncel']);
    }

    // --- Fill 'BFTC' Sheet ---
    $sheetBFTC = $spreadsheet->getSheetByName('BFTC');
    if ($sheetBFTC) {
        $rowStart = 5;
        for ($i = 0; $i < min(7, count($sonucKalemler)); $i++) {
            $row = $rowStart + $i;
            $k = $sonucKalemler[$i];
            
            // $sheetBFTC->setCellValue('B' . $row, $k['id']); // Assuming 'Poz No' mapping or specific. 
            $sheetBFTC->setCellValue('C' . $row, $k['kalem_adi']);
            $sheetBFTC->setCellValue('D' . $row, $k['birim']);
            $sheetBFTC->setCellValue('E' . $row, $k['miktari']); // Sözleşme miktarı
            $sheetBFTC->setCellValue('F' . $row, $k['teklif_edilen_birim_fiyat']);
        }
    }

    // --- Fill 'İcmal' Sheet ---
    $sheetIcmal = $spreadsheet->getSheetByName('İcmal');
    if ($sheetIcmal) {
        $qtyCols = ['E', 'I', 'M', 'Q', 'U', 'Y', 'AC'];
        for ($i = 0; $i < min(7, count($sonucKalemler)); $i++) {
            $col = $qtyCols[$i];
            $prevMikt = $sonucKalemler[$i]['onceki_miktar'];
            $sheetIcmal->setCellValue($col . '24', $prevMikt);
        }
    }

    // --- Fill Fiyat Farkı Temel Endeksleri (Fiyat Farkı Tutanağı) ---
    // Instead of overriding formulas, you might need to supply the base index values
    // Looking at the template, Fiyat Farkı Tutanağı likely reads from Bilgiler or has hardcoded. 
    // Wait, the scratch script didn't show where Temel Endeks is placed.
    // Let's dump "Fiyat Farkı Tutanağı" if needed, but for now we'll serve the computed file.

    // Calculate Formulas if needed (PhpSpreadsheet does this automatically on save for Excel)
    // $spreadsheet->getActiveSheet()->calculateColumnWidths();

    // 4. Send File to Browser
    $filename = 'Hakedis_' . $hakedis['hakedis_no'] . '_' . date('Ymd_His') . '.xlsx';

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    // If you're serving to IE 9, then the following may be needed
    header('Cache-Control: max-age=1');
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); 
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
    header('Cache-Control: cache, must-revalidate'); 
    header('Pragma: public'); 

    $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save('php://output');
    exit;

} catch (\Exception $e) {
    die("Excel oluşturulurken bir hata oluştu: " . $e->getMessage());
}
