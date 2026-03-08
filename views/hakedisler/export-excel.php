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
        SELECT hd.*, 
               hs.idare_adi, hs.isin_adi, hs.isin_yuklenicisi, hs.ihale_kayit_no, 
               hs.yuklenici_adres, hs.yuklenici_tel,
               hs.kesif_bedeli, hs.ihale_tenzilati, hs.sozlesme_bedeli, 
               hs.sozlesme_tarihi, hs.isin_bitecegi_tarih, hs.ihale_tarihi, 
               hs.yer_teslim_tarihi, hs.isin_suresi, hs.kontrol_teskilati, 
               hs.idare_onaylayan, hs.idare_onaylayan_unvan,
               hs.tasvip_eden, hs.tasvip_eden_unvan,
               hs.yuzde_yirmi_fazla_is, hs.son_sure_uzatimi,
               hs.gecici_kabul_tarihi, hs.gecici_kabul_itibar_tarihi, hs.gecici_kabul_onanma_tarihi
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

    // Önceki hakedişlerin toplamını hesapla (D23 için - shifted)
    $stmtPrevTotal = $db->prepare("
        SELECT SUM(hm.miktar * hk.teklif_edilen_birim_fiyat) as toplam
        FROM hakedis_miktarlari hm
        JOIN hakedis_donemleri hd ON hm.hakedis_donem_id = hd.id
        JOIN hakedis_kalemleri hk ON hm.kalem_id = hk.id
        WHERE hd.sozlesme_id = ? AND hd.hakedis_no < ? AND hd.silinme_tarihi IS NULL
    ");
    $stmtPrevTotal->execute([$sozlesme_id, $hakedis['hakedis_no']]);
    $oncekiHakedisBedeli = floatval($stmtPrevTotal->fetchColumn() ?? 0);

    // 2. Fetch Items (Kalemler) and Quantities (Miktarlar)
    // Similar to how hakedis-detay.php/online-api.php fetches them
    $hNo = $hakedis['hakedis_no'];

    $stmtPrev = $db->prepare("SELECT id FROM hakedis_donemleri WHERE sozlesme_id = ? AND hakedis_no < ? AND silinme_tarihi IS NULL ORDER BY hakedis_no DESC LIMIT 1");
    $stmtPrev->execute([$sozlesme_id, $hNo]);
    $prevHakedisId = $stmtPrev->fetchColumn();

    $stmtKalem = $db->prepare("SELECT * FROM hakedis_kalemleri WHERE sozlesme_id = :sid ORDER BY id ASC");
    $stmtKalem->execute([':sid' => $sozlesme_id]);
    $kalemler = $stmtKalem->fetchAll(PDO::FETCH_ASSOC);
    
    // ... rest of the items logic ...
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
            $dt = new \DateTime($dbDate);
            $excelDate = \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($dt);
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
        $sheetBilgiler->setCellValue('D4', $hakedis['yuklenici_adres']);
        $sheetBilgiler->setCellValue('D5', $hakedis['yuklenici_tel']);
        $sheetBilgiler->setCellValue('D6', $hakedis['ihale_kayit_no']);
        $sheetBilgiler->setCellValue('D7', $hakedis['kesif_bedeli']);
        // D8 - İhale Tenzilatı is formula, skip
        $sheetBilgiler->setCellValue('D9', $hakedis['sozlesme_bedeli']);
        
        setExcelDate($sheetBilgiler, 'D10', $hakedis['sozlesme_tarihi']);
        setExcelDate($sheetBilgiler, 'D11', $hakedis['isin_bitecegi_tarih']);
        
        $sheetBilgiler->setCellValue('D12', $hakedis['hakedis_no']);
        
        setExcelDate($sheetBilgiler, 'D13', $hakedis['is_yapilan_ayin_son_gunu']);
        
        // --- Signature Mapping ---
        // Kontrol (First split person)
        $kontrolText = $hakedis['kontrol_teskilati'] ?? '';
        $kontrolLines = array_filter(array_map('trim', explode("\n", $kontrolText)));
        if (!empty($kontrolLines)) {
            $firstKontrol = $kontrolLines[0];
            if (strpos($firstKontrol, '-') !== false) {
                list($kName, $kTitle) = explode('-', $firstKontrol, 2);
                $sheetBilgiler->setCellValue('D14', trim($kName));
                $sheetBilgiler->setCellValue('D15', trim($kTitle));
            } else {
                $sheetBilgiler->setCellValue('D14', $firstKontrol);
            }
        }

        // Müdür (Tasvip Eden)
        $sheetBilgiler->setCellValue('D16', $hakedis['tasvip_eden'] ?? '');
        $sheetBilgiler->setCellValue('D17', $hakedis['tasvip_eden_unvan'] ?? '');

        // Tasdik Eden (İdare Onaylayan)
        $sheetBilgiler->setCellValue('D18', $hakedis['idare_onaylayan'] ?? '');
        $sheetBilgiler->setCellValue('D19', $hakedis['idare_onaylayan_unvan'] ?? '');
        
        setExcelDate($sheetBilgiler, 'D20', $hakedis['ihale_tarihi']);
        setExcelDate($sheetBilgiler, 'D21', $hakedis['yer_teslim_tarihi']);
        
        $sheetBilgiler->setCellValue('D22', $hakedis['isin_suresi']);
        $sheetBilgiler->setCellValue('D23', $oncekiHakedisBedeli); // Önceki Hakediş Bedeli
        setExcelDate($sheetBilgiler, 'D24', $hakedis['tutanak_tasdik_tarihi']); // Tutanak Tasdik Tarihi
        
        $sheetBilgiler->setCellValue('D25', $hakedis['hakedis_tarihi_yil']);
        $sheetBilgiler->setCellValue('D26', $aylar[$hakedis['hakedis_tarihi_ay']] ?? '');

        // Bu Ayki Miktarlar (A27, C27, E27, G27, I27, K27, M27) -> Shifted to 27 and 29
        $qtyCols = ['A', 'C', 'E', 'G', 'I', 'K', 'M'];
        for ($i = 0; $i < min(7, count($sonucKalemler)); $i++) {
            $col = $qtyCols[$i];
            $sheetBilgiler->setCellValue($col . '27', $sonucKalemler[$i]['kalem_adi']); // Header
            $sheetBilgiler->setCellValue($col . '29', $sonucKalemler[$i]['bu_ay_miktar']);
        }

        // Fiyat Farkı Guncel Endeksleri -> Shifted to 32
        $sheetBilgiler->setCellValue('A32', $hakedis['asgari_ucret_guncel']);
        $sheetBilgiler->setCellValue('C32', $hakedis['motorin_guncel']);
        $sheetBilgiler->setCellValue('E32', $hakedis['ufe_genel_guncel']);
        $sheetBilgiler->setCellValue('G32', $hakedis['makine_ekipman_guncel']);
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
            // Current month quantities should go to Row 50 for formula mapping to Çarşaf/FFT
            $curMikt = $sonucKalemler[$i]['bu_ay_miktar'];
            $sheetIcmal->setCellValue($col . '50', $curMikt);
        }
    }

    // --- Fill 'Fiyat Farkı Tutanağı' Sheet ---
    $sheetFFT = $spreadsheet->getSheetByName('Fiyat Farkı Tutanağı');
    if ($sheetFFT) {
        // Coefficients (Pn Components)
        $sheetFFT->setCellValue('N8', $hakedis['a1_katsayisi'] ?? 0.28);
        $sheetFFT->setCellValue('N9', $hakedis['b1_katsayisi'] ?? 0.22);
        $sheetFFT->setCellValue('N10', $hakedis['b2_katsayisi'] ?? 0.25);
        $sheetFFT->setCellValue('N11', $hakedis['c_katsayisi'] ?? 0.25);

        // Temel Endeksler (Io, Mo, ÜFEo, Eo)
        $sheetFFT->setCellValue('O8', $hakedis['asgari_ucret_temel'] ?? 26005.5);
        $sheetFFT->setCellValue('O9', $hakedis['motorin_temel'] ?? 54.13308);
        $sheetFFT->setCellValue('O10', $hakedis['ufe_genel_temel'] ?? 4632.89);
        $sheetFFT->setCellValue('O11', $hakedis['makine_ekipman_temel'] ?? 3319.76);

        // Note: Pn components (P8, P9, P10, P11) are formulas referencing Bilgiler A30, C30, E30, G30
    }

    // --- Fill 'Arka Kapak' Sheet ---
    $sheetArkaKapak = $spreadsheet->getSheetByName('Arka Kapak');
    if ($sheetArkaKapak) {
        // Tasvip Eden
        $tasvip_tarihi = '';
        if (!empty($hakedis['is_yapilan_ayin_son_gunu']) && $hakedis['is_yapilan_ayin_son_gunu'] != '0000-00-00') {
            $tasvip_tarihi = (new \DateTime($hakedis['is_yapilan_ayin_son_gunu']))->format('d.m.Y');
        }
        $sheetArkaKapak->setCellValue('A37', $tasvip_tarihi);
        $sheetArkaKapak->setCellValue('A38', $hakedis['tasvip_eden'] ?? '');
        $sheetArkaKapak->setCellValue('A39', $hakedis['tasvip_eden_unvan'] ?? '');

        // Tasdik Eden
        $tasdik_tarihi = '...../...../2026';
        if (!empty($hakedis['tutanak_tasdik_tarihi']) && $hakedis['tutanak_tasdik_tarihi'] != '0000-00-00') {
            $tasdik_tarihi = (new \DateTime($hakedis['tutanak_tasdik_tarihi']))->format('d.m.Y');
        }
        $sheetArkaKapak->setCellValue('A47', $tasdik_tarihi);
        $sheetArkaKapak->setCellValue('A48', $hakedis['idare_onaylayan'] ?? '');
        $sheetArkaKapak->setCellValue('A49', $hakedis['idare_onaylayan_unvan'] ?? '');
    }

    // --- Fill 'Ön Kapak' Sheet ---
    $sheetOnKapak = $spreadsheet->getSheetByName('Ön Kapak');
    if ($sheetOnKapak) {
        $sheetOnKapak->setCellValue('F30', $hakedis['yuzde_yirmi_fazla_is'] ?? '');
        $sheetOnKapak->setCellValue('D35', $hakedis['son_sure_uzatimi'] ?? '');
        
        setExcelDate($sheetOnKapak, 'G41', $hakedis['isin_bitecegi_tarih']);
        setExcelDate($sheetOnKapak, 'G42', $hakedis['gecici_kabul_tarihi']);
        setExcelDate($sheetOnKapak, 'G43', $hakedis['gecici_kabul_itibar_tarihi']);
        setExcelDate($sheetOnKapak, 'G44', $hakedis['gecici_kabul_onanma_tarihi']);
    }

    // Calculate Formulas if needed (PhpSpreadsheet does this automatically on save for Excel)
    // $spreadsheet->getActiveSheet()->calculateColumnWidths();

    // 4. Send File to Browser
    $filename = 'Hakedis_' . $hakedis['hakedis_no'] . '_' . date('Ymd_His') . '.xlsx';

    // Herhangi bir PHP uyarısının (Warning/Notice) dosyayı bozmasını engellemek için çıktı tamponunu temizliyoruz
    if (ob_get_length()) ob_end_clean();

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
