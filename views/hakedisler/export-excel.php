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
               hs.idare_adi, hs.idare_baskanlik_adi, hs.isin_adi, hs.isin_yuklenicisi, hs.ihale_kayit_no, 
               hs.yuklenici_adres, hs.yuklenici_tel,
               hs.kesif_bedeli, hs.ihale_tenzilati, hs.sozlesme_bedeli, 
               hs.sozlesme_tarihi, hs.isin_bitecegi_tarih, hs.ihale_tarihi, 
               hs.yer_teslim_tarihi, hs.isin_suresi, hs.kontrol_teskilati, 
               hs.idare_onaylayan, hs.idare_onaylayan_unvan,
               hs.tasvip_eden, hs.tasvip_eden_unvan,
               hs.yuzde_yirmi_fazla_is, hs.son_sure_uzatimi,
               hs.gecici_kabul_tarihi, hs.gecici_kabul_itibar_tarihi, hs.gecici_kabul_onanma_tarihi,
               hs.temel_endeks_ay, hs.temel_endeks_yil,
               hd.onceki_hakedis_tutari
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
    // 4. Miktarları çek (Mevcut dönem için)
    $miktarlarMap = [];
    $stmtMiktar = $db->prepare("SELECT * FROM hakedis_miktarlari WHERE hakedis_donem_id = ?");
    $stmtMiktar->execute([$hakedis_id]);
    while ($m = $stmtMiktar->fetch(PDO::FETCH_ASSOC)) {
        $miktarlarMap[$m['kalem_id']] = $m;
    }

    // 5. Tüm önceki miktarları kalem bazlı topla (Kümülatif doğruluk için)
    $prevMiktarlarSum = [];
    $stmtPrevSum = $db->prepare("
        SELECT m.kalem_id, SUM(m.miktar) as toplam_prev
        FROM hakedis_miktarlari m
        JOIN hakedis_donemleri d ON m.hakedis_donem_id = d.id
        WHERE d.sozlesme_id = ? AND d.hakedis_no < ? AND d.silinme_tarihi IS NULL
        GROUP BY m.kalem_id
    ");
    $stmtPrevSum->execute([$sozlesme_id, $hNo]);
    while ($row = $stmtPrevSum->fetch(PDO::FETCH_ASSOC)) {
        $prevMiktarlarSum[$row['kalem_id']] = floatval($row['toplam_prev']);
    }

    // 6. İlk hakedişteki (hno=1) başlangıç 'onceki_miktar' değerlerini al
    $baslangicMiktarlari = [];
    $stmtBaslangic = $db->prepare("
        SELECT m.kalem_id, m.onceki_miktar
        FROM hakedis_miktarlari m
        JOIN hakedis_donemleri d ON m.hakedis_donem_id = d.id
        WHERE d.sozlesme_id = ? AND d.hakedis_no = 1 AND d.silinme_tarihi IS NULL
    ");
    $stmtBaslangic->execute([$sozlesme_id]);
    while ($row = $stmtBaslangic->fetch(PDO::FETCH_ASSOC)) {
        $baslangicMiktarlari[$row['kalem_id']] = floatval($row['onceki_miktar']);
    }

    $sonucKalemler = [];
    foreach ($kalemler as $k) {
        $kalem_id = $k['id'];

        // Bu ayki miktar
        $curMiktarRow = $miktarlarMap[$kalem_id] ?? null;
        $buay_toplam = floatval($curMiktarRow['miktar'] ?? 0);

        // Önceki toplam miktar
        $onceki_toplam = 0;
        if ($curMiktarRow && isset($curMiktarRow['onceki_miktar']) && $curMiktarRow['onceki_miktar'] != 0) {
            $onceki_toplam = floatval($curMiktarRow['onceki_miktar']);
        } else {
            $prevSum = $prevMiktarlarSum[$kalem_id] ?? 0;
            $baslangic = $baslangicMiktarlari[$kalem_id] ?? 0;
            $onceki_toplam = $prevSum + $baslangic;
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
        } else {
            $worksheet->setCellValue($cell, '');
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
        
        // D8 - İhale Tenzilatı: Excel hücre formatı '%' olduğu için değeri 100'e bölerek yazıyoruz. (örn: 16.88 -> 0.1688)
        $tenzilat = floatval($hakedis['ihale_tenzilati']);
        $sheetBilgiler->setCellValue('D8', $tenzilat / 100);

        $sheetBilgiler->setCellValue('D9', $hakedis['sozlesme_bedeli']);
        
        setExcelDate($sheetBilgiler, 'D10', $hakedis['sozlesme_tarihi']);
        setExcelDate($sheetBilgiler, 'D11', $hakedis['isin_bitecegi_tarih']);
        
        $sheetBilgiler->setCellValue('D12', $hakedis['hakedis_no']);
        
        setExcelDate($sheetBilgiler, 'D13', $hakedis['is_yapilan_ayin_son_gunu']);
        
        // --- Signature Mapping ---
        // Kontrol (All members)
        $kontrolText = $hakedis['kontrol_teskilati'] ?? '';
        $kontrolLines = array_filter(array_map('trim', explode("\n", $kontrolText)));
        $names = [];
        $titles = [];
        foreach ($kontrolLines as $line) {
            if (strpos($line, '-') !== false) {
                list($kName, $kTitle) = explode('-', $line, 2);
                $names[] = trim($kName);
                $titles[] = trim($kTitle);
            } else {
                $names[] = trim($line);
            }
        }
        $sheetBilgiler->setCellValue('D14', implode('  -  ', $names));
        $sheetBilgiler->setCellValue('D15', implode('  -  ', $titles));

        // Müdür (Tasvip Eden)
        $sheetBilgiler->setCellValue('D16', $hakedis['tasvip_eden'] ?? '');
        $sheetBilgiler->setCellValue('D17', $hakedis['tasvip_eden_unvan'] ?? '');

        // Tasdik Eden (İdare Onaylayan)
        $sheetBilgiler->setCellValue('D18', $hakedis['idare_onaylayan'] ?? '');
        $sheetBilgiler->setCellValue('D19', $hakedis['idare_onaylayan_unvan'] ?? '');
        
        setExcelDate($sheetBilgiler, 'D20', $hakedis['ihale_tarihi']);
        setExcelDate($sheetBilgiler, 'D21', $hakedis['yer_teslim_tarihi']);
        
        $sheetBilgiler->setCellValue('D22', $hakedis['isin_suresi']);
        
        // --- Önceki Hakediş Tutarı Mantığı ---
        // 1. Bir önceki hakedişin (hakedis_no - 1) sistemde kayıtlı 'hakedi_tutari' alanına bak.
        $stmtPrevVal = $db->prepare("SELECT hakedi_tutari FROM hakedis_donemleri WHERE sozlesme_id = ? AND hakedis_no = ? AND silinme_tarihi IS NULL LIMIT 1");
        $stmtPrevVal->execute([$hakedis['sozlesme_id'], $hakedis['hakedis_no'] - 1]);
        $prevHakedisSaving = floatval($stmtPrevVal->fetchColumn() ?? 0);

        // 2. Eğer o alan boşsa, mevcut hakedişteki manuel 'onceki_hakedis_tutari' alanını kullan.
        $oncekiTutarFinal = ($prevHakedisSaving > 0) ? $prevHakedisSaving : floatval($hakedis['onceki_hakedis_tutari'] ?? 0);

        $sheetBilgiler->setCellValue('D23', $oncekiTutarFinal); // Önceki Hakediş Bedeli
        setExcelDate($sheetBilgiler, 'D24', $hakedis['tutanak_tasdik_tarihi']); 
        
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
        
        // Formats for indices
        $sheetBilgiler->getStyle('A32:G32')->getNumberFormat()->setFormatCode('#,##0.000000');
    }

    // --- Fill 'BFTC' Sheet ---
    $sheetBFTC = $spreadsheet->getSheetByName('BFTC');
    if ($sheetBFTC) {
        $rowStart = 5;
        $genelToplam = 0;

        // Öncelikle 5-11 arasındaki satırları temizle (Eski şablon verisi kalmasın)
        for ($i = 5; $i <= 11; $i++) {
            $sheetBFTC->setCellValue('B' . $i, '');
            $sheetBFTC->setCellValue('C' . $i, '');
            $sheetBFTC->setCellValue('D' . $i, '');
            $sheetBFTC->setCellValue('E' . $i, '');
            $sheetBFTC->setCellValue('F' . $i, '');
            $sheetBFTC->setCellValue('G' . $i, '');
        }

        for ($i = 0; $i < min(7, count($sonucKalemler)); $i++) {
            $row = $rowStart + $i;
            $k = $sonucKalemler[$i];
            
            $miktari = floatval($k['miktari']);
            $b_fiyat = floatval($k['teklif_edilen_birim_fiyat']);
            $tutar = $miktari * $b_fiyat;
            $genelToplam += $tutar;
            
            $sheetBFTC->setCellValue('B' . $row, $k['poz_no'] ?? '');
            $sheetBFTC->setCellValue('C' . $row, $k['kalem_adi']);
            $sheetBFTC->setCellValue('D' . $row, $k['birim']);
            $sheetBFTC->setCellValue('E' . $row, $miktari); // Sözleşme miktarı
            $sheetBFTC->setCellValue('F' . $row, $b_fiyat);
            $sheetBFTC->setCellValue('G' . $row, $tutar);
        }

        // Genel Toplamı G12 hücresine yazdır

        $sheetBFTC->setCellValue('D12', "GENEL TOPLAM");
        $sheetBFTC->setCellValue('G12', $genelToplam);
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
        $sheetFFT->setCellValue('C3', $hakedis['idare_baskanlik_adi'] ?? '');

        // Hakediş tutarını hesapla ve A11 hücresine yaz (Donem Imalat)
        $donemModel = new HakedisDonemModel();
        $totals = $donemModel->calculateTotals($hakedis['id']);
        $hakedisTutari = $totals['imalat_donem'] ?? 0;
        $sheetFFT->setCellValue('A11', $hakedisTutari);
        
        // Pn Formülü kaldırıldı.
        // Kullanıcının Hakedis.xlsx şablonunda metin kutusu yerine hücre bazlı
        // veya resim bazlı bir çözüm uygulaması bekleniyor. (PhpSpreadsheet Textbox'ları siler)
        $sheetFFT->setCellValue('N8', $hakedis['a1_katsayisi'] ?? 0.28);
        $sheetFFT->setCellValue('N9', $hakedis['b1_katsayisi'] ?? 0.22);
        $sheetFFT->setCellValue('N10', $hakedis['b2_katsayisi'] ?? 0.25);
        $sheetFFT->setCellValue('N11', $hakedis['c_katsayisi'] ?? 0.25);

        // Temel Endeksler (Io, Mo, ÜFEo, Eo)
        $sheetFFT->setCellValue('O8', $hakedis['asgari_ucret_temel'] ?? 26005.5);
        $sheetFFT->setCellValue('O9', $hakedis['motorin_temel'] ?? 54.13308);
        $sheetFFT->setCellValue('O10', $hakedis['ufe_genel_temel'] ?? 4632.89);
        $sheetFFT->setCellValue('O11', $hakedis['makine_ekipman_temel'] ?? 3319.76);

        // Formats for coefficients and indices
        $sheetFFT->getStyle('N8:N11')->getNumberFormat()->setFormatCode('#,##0.000000');
        $sheetFFT->getStyle('O8:O11')->getNumberFormat()->setFormatCode('#,##0.000000');

        // --- Endeks Ay/Yıl ve 00.01.1900 Düzeltmeleri ---
        // Üst Başlık (C2)
        $sheetFFT->setCellValue('C2', $hakedis['idare_adi'] ?? '');

        // Temel Endeks Satırı (20. Satır)
        $sheetFFT->setCellValue('D20', $hakedis['temel_endeks_yil'] ?? '');
        $sheetFFT->setCellValue('E20', $aylar[$hakedis['temel_endeks_ay']] ?? '');

        // Güncel Endeks Satırı (21. Satır)
        $sheetFFT->setCellValue('D21', $hakedis['hakedis_tarihi_yil'] ?? '');
        $sheetFFT->setCellValue('E21', $aylar[$hakedis['hakedis_tarihi_ay']] ?? '');

        // Fiyat Farkı Hesabı Satırı (23. Satır)
        $sheetFFT->setCellValue('D23', $hakedis['hakedis_tarihi_yil'] ?? '');
        $sheetFFT->setCellValue('E23', $aylar[$hakedis['hakedis_tarihi_ay']] ?? '');

        // Üst Satır Özet (12. Satır)
        $sheetFFT->setCellValue('E12', $hakedis['hakedis_tarihi_yil'] ?? '');
        $sheetFFT->setCellValue('F12', $aylar[$hakedis['hakedis_tarihi_ay']] ?? '');

        // Alt Satır Özet (30. Satır)
        $sheetFFT->setCellValue('A30', $hakedis['hakedis_tarihi_yil'] ?? '');
        $sheetFFT->setCellValue('B30', $aylar[$hakedis['hakedis_tarihi_ay']] ?? '');
    }

    // --- Fill 'Arka Kapak' Sheet ---
    $sheetArkaKapak = $spreadsheet->getSheetByName('Arka Kapak');
    if ($sheetArkaKapak) {
      
        // Tasdik Eden
        $tasdik_tarihi = '...../...../2026';
        if (!empty($hakedis['tutanak_tasdik_tarihi']) && $hakedis['tutanak_tasdik_tarihi'] != '0000-00-00') {
            $tasdik_tarihi = (new \DateTime($hakedis['tutanak_tasdik_tarihi']))->format('d.m.Y');
        }

        $sheetArkaKapak->setCellValue('A37', $tasdik_tarihi);
        $sheetArkaKapak->setCellValue('A38', $hakedis['tasvip_eden'] ?? '');
        $sheetArkaKapak->setCellValue('A39', $hakedis['tasvip_eden_unvan'] ?? '');

        $sheetArkaKapak->setCellValue('A47', $tasdik_tarihi);
        $sheetArkaKapak->setCellValue('A48', $hakedis['idare_onaylayan'] ?? '');
        $sheetArkaKapak->setCellValue('A49', $hakedis['idare_onaylayan_unvan'] ?? '');
    }

    // --- Fill 'Ön Kapak' Sheet ---
    $sheetOnKapak = $spreadsheet->getSheetByName('Ön Kapak');
    if ($sheetOnKapak) {
        $sheetOnKapak->setCellValue('F30', $hakedis['yuzde_yirmi_fazla_is'] ?? '');
        $sheetOnKapak->setCellValue('D35', $hakedis['son_sure_uzatimi'] ?? '');
        
        //setExcelDate($sheetOnKapak, 'G41', $hakedis['isin_bitecegi_tarih']);
        setExcelDate($sheetOnKapak, 'G42', $hakedis['gecici_kabul_tarihi']);
        setExcelDate($sheetOnKapak, 'G43', $hakedis['gecici_kabul_itibar_tarihi']);
        setExcelDate($sheetOnKapak, 'G44', $hakedis['gecici_kabul_onanma_tarihi']);
    }

    // --- Fill 'İş Takip Arka Yüz' Sheet ---
    $sheetIsTakip = $spreadsheet->getSheetByName('İş Takip Arka Yüz');
    if ($sheetIsTakip) {
        $sozlesmeTarihi = $hakedis['sozlesme_tarihi'];
        $sozlesmeYili = $sozlesmeTarihi ? substr($sozlesmeTarihi, 0, 4) : date('Y');
        
        $sheetIsTakip->setCellValue('C6', $hakedis['sozlesme_bedeli']);
        // Yıllar Başlığı (H10, L10, P10 vb.)
        $yilSutunlari = ['H', 'L', 'P', 'T']; // Maksimum 4 yıl destekleyelim
        
        $bitisTarihi = $hakedis['isin_bitecegi_tarih'];
        $bitisYili = $bitisTarihi ? substr($bitisTarihi, 0, 4) : $sozlesmeYili;

        $yilFarki = $bitisYili - $sozlesmeYili;
        if ($yilFarki < 0) $yilFarki = 0;
        if ($yilFarki > 3) $yilFarki = 3; // En fazla 4 yıl (0, 1, 2, 3) yazdırıyoruz şablona sığması için

        // Geçici Değişkenler
        $donemModel = new HakedisDonemModel();
        $yilinSonHakedisleri = [];

        $stmtYillar = $db->prepare("
            SELECT hakedis_tarihi_yil, MAX(hakedis_no) as max_no
            FROM hakedis_donemleri
            WHERE sozlesme_id = ? AND hakedis_no <= ? AND silinme_tarihi IS NULL
            GROUP BY hakedis_tarihi_yil
        ");
        $stmtYillar->execute([$hakedis['sozlesme_id'], $hakedis['hakedis_no']]);

        while ($row = $stmtYillar->fetch(\PDO::FETCH_ASSOC)) {
            $stmtLastId = $db->prepare("SELECT id FROM hakedis_donemleri WHERE sozlesme_id = ? AND hakedis_no = ? AND silinme_tarihi IS NULL LIMIT 1");
            $stmtLastId->execute([$hakedis['sozlesme_id'], $row['max_no']]);
            $lastId = $stmtLastId->fetchColumn();
            
            if ($lastId) {
                $totalsLast = $donemModel->calculateTotals($lastId);
                $yilinSonHakedisleri[$row['hakedis_tarihi_yil']] = $totalsLast['imalat_kumulatif'] ?? 0;
            }
        }

        $oncekiYilinKumulatifi = 0;

        for ($i = 0; $i <= $yilFarki; $i++) {
            $col = $yilSutunlari[$i];
            $yil = $sozlesmeYili + $i;

            // 10. Satır: YIL Bilgisi
            $sheetIsTakip->setCellValue($col . '10', $yil);

            // 11. Satır: Sözleşme Bedeline Göre Dağılım
            // Tüm sözleşme bedelini ilk yıla yazdırıyoruz (Özel dağılım kuralı belirtilmediyse)
            if ($i == 0) {
                $sheetIsTakip->setCellValue($col . '11', $hakedis['sozlesme_bedeli']);
            } else {
                $sheetIsTakip->setCellValue($col . '11', '');
            }

            // 12. Satır: Yıl İçi Gerçekleşme (Bu Yılın Sonundaki - Önceki Yılın Sonundaki)
            $buYilinKumulatifi = $yilinSonHakedisleri[$yil] ?? $oncekiYilinKumulatifi; 
            $yilIciTutar = max(0, $buYilinKumulatifi - $oncekiYilinKumulatifi);
            
            $sheetIsTakip->setCellValue($col . '12', $yilIciTutar);
            $oncekiYilinKumulatifi = $buYilinKumulatifi;
        }

        // Genel Gerçekleşme Yüzdesi (Kümülatif toplam / Sözleşme Bedeli) => C13'te kalabilir
        $totals = $donemModel->calculateTotals($hakedis['id']);
        $genelKumulatif = $totals['imalat_kumulatif'] ?? 0;
        
        $bedel = floatval($hakedis['sozlesme_bedeli'] ?? 0);
        $yuzde = ($bedel > 0) ? ($genelKumulatif / $bedel) : 0;
        $sheetIsTakip->setCellValue('C13', $yuzde);

        // Kalem bazlı Yapılan Miktar ve Kalan Miktar bölümü (Satır 15'ten başlıyor)
        $rowStart = 15;

        // Eski dummy veriyi temizle
        for ($i = 0; $i < 7; $i++) {
            $r = $rowStart + $i;
            $sheetIsTakip->setCellValue('F' . $r, '');
            $sheetIsTakip->setCellValue('G' . $r, '');
            $sheetIsTakip->setCellValue('I' . $r, '');
            $sheetIsTakip->setCellValue('J' . $r, '');
        }

        for ($i = 0; $i < min(7, count($sonucKalemler)); $i++) {
            $row = $rowStart + $i;
            $k = $sonucKalemler[$i];
            
            $sozlesmeMiktar = floatval($k['miktari']);
            $yapilanMiktar = floatval($k['onceki_miktar']) + floatval($k['bu_ay_miktar']);
            $kalanMiktar = $sozlesmeMiktar - $yapilanMiktar;

            $sheetIsTakip->setCellValue('F' . $row, $sozlesmeMiktar);
            $sheetIsTakip->setCellValue('G' . $row, $sozlesmeMiktar); // Tatbikat Projesi Miktarı aynı kabul ediliyor
            $sheetIsTakip->setCellValue('I' . $row, $yapilanMiktar);
            $sheetIsTakip->setCellValue('J' . $row, $kalanMiktar);
        }
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
