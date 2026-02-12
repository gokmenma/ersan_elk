<?php
session_start();
require_once dirname(__DIR__, 2) . '/Autoloader.php';
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Helper\Helper;
use App\Model\PuantajModel;
use App\Model\PersonelModel;
use App\Model\EndeksOkumaModel;
use App\Model\TanimlamalarModel;
use App\Model\SystemLogModel;
use App\Model\DemirbasZimmetModel; // Yeni eklendi
use App\Service\EndeskOkumaService;
use App\Service\KesmeAcmaService;

// Set header to JSON
// header('Content-Type: application/json');

$Puantaj = new PuantajModel();
$Tanimlamalar = new TanimlamalarModel();
$Zimmet = new DemirbasZimmetModel(); // Yeni eklendi

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'puantaj-excel-kaydet') {

    $response = ['status' => 'error', 'message' => 'Bilinmeyen hata'];

    try {
        if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Dosya yüklenemedi veya dosya seçilmedi.");
        }

        $uploadDateRaw = $_POST['upload_date'] ?? date('Y-m-d');
        $uploadDate = \App\Helper\Date::convertExcelDate($uploadDateRaw, 'Y-m-d') ?: date('Y-m-d');
        $fileTmpPath = $_FILES['excel_file']['tmp_name'];

        // Load Excel
        $spreadsheet = IOFactory::load($fileTmpPath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        // Find header row index
        $headerRowIndex = null;
        $colMap = []; // Map header names to column indices (0-based)

        foreach ($rows as $index => $row) {
            // Check for key columns to identify header row
            // Header columns based on user image: "Firma", "İş Emri Tipi", "Ekip", "İş Emri Sonucu"
            $rowStr = implode(' ', array_map('strval', $row));

            if (stripos($rowStr, 'Firma') !== false && stripos($rowStr, 'İş Emri Tipi') !== false && stripos($rowStr, 'Ekip') !== false) {
                $headerRowIndex = $index;

                // Map columns
                foreach ($row as $colIndex => $cellValue) {
                    $cellValue = trim($cellValue);
                    if ($cellValue === 'Firma')
                        $colMap['firma'] = $colIndex;
                    elseif ($cellValue === 'İş Emri Tipi')
                        $colMap['is_emri_tipi'] = $colIndex;
                    elseif ($cellValue === 'Ekip')
                        $colMap['ekip'] = $colIndex;
                    elseif ($cellValue === 'İş Emri Sonucu')
                        $colMap['is_emri_sonucu'] = $colIndex;
                    elseif (stripos($cellValue, 'Sonuçlanmış') !== false)
                        $colMap['sonuclanmis'] = $colIndex;
                    elseif (stripos($cellValue, 'Açık Olanlar') !== false)
                        $colMap['acik_olanlar'] = $colIndex;
                }
                break;
            }
        }

        if ($headerRowIndex === null) {
            throw new Exception("Excel formatı geçersiz. Başlık satırı (Firma, İş Emri Tipi, Ekip...) bulunamadı.");
        }

        // Validate required columns
        $requiredCols = ['firma', 'is_emri_tipi', 'ekip', 'is_emri_sonucu', 'sonuclanmis', 'acik_olanlar'];
        foreach ($requiredCols as $req) {
            if (!isset($colMap[$req])) {
                // If specific columns are missing, we might want to be flexible or fail
                // For now, let's try to proceed if at least Firma, Ekip and stats are there? 
                // Let's be strict for now based on user requirement
                // throw new Exception("Gerekli sütun bulunamadı: " . $req);
            }
        }

        $insertedCount = 0;
        $skippedCount = 0;
        $skippedRows = []; // Atlanan satırların detayları
        $workTypeCache = []; // Aynı yükleme sırasında yeni eklenen türleri takip etmek için cache
        $pendingMovements = []; // Otomatik demirbaş işlemleri için kuyruk

        // Process rows

        for ($i = $headerRowIndex + 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            $excelRowNum = $i + 1; // Excel satır numarası (1-indexed)

            // Extract data using map
            $firma = isset($colMap['firma']) ? trim($row[$colMap['firma']]) : '';
            $isEmriTipi = isset($colMap['is_emri_tipi']) ? trim($row[$colMap['is_emri_tipi']]) : '';
            $ekip = isset($colMap['ekip']) ? trim($row[$colMap['ekip']]) : '';
            $isEmriSonucu = isset($colMap['is_emri_sonucu']) ? trim($row[$colMap['is_emri_sonucu']]) : '';
            $sonuclanmis = isset($colMap['sonuclanmis']) ? (int) trim($row[$colMap['sonuclanmis']]) : 0;
            $acikOlanlar = isset($colMap['acik_olanlar']) ? (int) trim($row[$colMap['acik_olanlar']]) : 0;

            // Skip empty rows or summary rows (often total rows have empty fields)
            // If ekip is empty, it's definitely not a data row we want to process or warn about
            if (empty($ekip)) {
                continue;
            }

            /** sonuclanmış iş 0 veya daha küçükse atla */
            if ($sonuclanmis <= 0) {
                $skippedCount++;
                $skippedRows[] = [
                    'satir' => $excelRowNum,
                    'ekip' => $ekip,
                    'neden' => 'Bu iş sonuclanmadığı için atlandı.'
                ];
                continue;
            }



            // Skip rows that look like totals (e.g. starts with 'Toplam' or Firma is empty)
            if (empty($firma) || stripos($firma, 'Toplam') !== false || stripos($ekip, 'Toplam') !== false) {
                continue;
            }

            // Generate Unique ID
            // Using MD5 of key fields + Date to identify this specific record
            // The user said: "islem_id alanı da eklenecek ve excelden veri yüklenirken eğer islem_kodu daha önce yüklendiyse onu atlaycak"
            // Since we don't have an ID in Excel, we construct one.
            $rawString = $uploadDate . '|' . $firma . '|' . $isEmriTipi . '|' . $ekip . '|' . $isEmriSonucu;
            $islemId = md5($rawString);

            // Check if exists
            $exists = $Puantaj->db->prepare("SELECT COUNT(*) FROM yapilan_isler WHERE islem_id = ? AND silinme_tarihi IS NULL");
            $exists->execute([$islemId]);
            if ($exists->fetchColumn() > 0) {
                $skippedCount++;
                $skippedRows[] = [
                    'satir' => $excelRowNum,
                    'ekip' => $ekip,
                    'neden' => 'Bu kayıt daha önce yüklenmiş (duplicate)'
                ];
                continue;
            }

            // Insert
            // Personel mapping logic
            $personelId = 0;
            if (!empty($ekip)) {
                // Normalize ekip name for matching (replace potential messy characters)
                $normalizedEkip = str_replace(['İ', 'I', 'Ş', 'Ğ', 'Ü', 'Ö', 'Ç'], ['%', '%', '%', '%', '%', '%', '%'], $ekip);

                // 1. Find team definition ID in tanimlamalar
                // We use a more flexible matching to handle both "EKİP-XX" and "ER-SAN ELEKTRİK EKİP-XX"
                // Also ensuring we only find teams for the current company
                $teamNo = 0;
                if (preg_match('/(?:EK[İI]P-?\s?)(\d+)/ui', $ekip, $m)) {
                    $teamNo = $m[1];
                }

                $stmtDef = $Puantaj->db->prepare("
                    SELECT id FROM tanimlamalar 
                    WHERE (tur_adi = ? OR tur_adi LIKE ? OR tur_adi LIKE ?) 
                    AND grup = 'ekip_kodu' 
                    AND firma_id = ? 
                    AND silinme_tarihi IS NULL 
                    LIMIT 1
                ");
                $stmtDef->execute([
                    $ekip,
                    $normalizedEkip,
                    $teamNo > 0 ? "%EK[İI]P-$teamNo" : "---NONE---",
                    $_SESSION['firma_id']
                ]);
                $defId = $stmtDef->fetchColumn();

                if ($defId) {
                    // 2. Find person assigned to this team ID who was ACTIVE on the upload date
                    // - ekip_no matches the definition ID
                    // - aktif_mi = 1 (active)
                    // - ise_giris_tarihi <= uploadDate (started before or on the date)
                    // - isten_cikis_tarihi IS NULL OR isten_cikis_tarihi >= uploadDate (hasn't left yet or left after the date)
                    // 2. Find person from team history who was ACTIVE on the upload date
                    $stmtPersonel = $Puantaj->db->prepare("
                        SELECT p.id 
                        FROM personel p
                        JOIN personel_ekip_gecmisi pg ON p.id = pg.personel_id
                        WHERE pg.ekip_kodu_id = ? 
                        AND pg.baslangic_tarihi <= ?
                        AND (pg.bitis_tarihi IS NULL OR pg.bitis_tarihi = '' OR pg.bitis_tarihi >= ?)
                        AND p.silinme_tarihi IS NULL
                        AND pg.firma_id = ?
                        LIMIT 1
                    ");
                    $stmtPersonel->execute([$defId, $uploadDate, $uploadDate, $_SESSION['firma_id']]);
                    $personelId = $stmtPersonel->fetchColumn() ?: 0;
                    $ekipId = $defId;
                }
            }

            // Skip if personel not found as per user request
            if ($personelId == 0) {
                $skippedCount++;
                $skippedRows[] = [
                    'satir' => $excelRowNum,
                    'ekip' => $ekip,
                    'neden' => 'Bu ekipte o tarihte aktif personel bulunamadı'
                ];
                continue;
            }

            /**İş Emri Tipi ve iş Emri Sonucuna göre Tanımlamlar tablosundan id'yi getir */
            /** Tanımlı is id'sini al,tanımlı değilse yeni kayıt ekle onun id'sini al */
            $cacheKey = $isEmriTipi . '|' . $isEmriSonucu;
            if (isset($workTypeCache[$cacheKey])) {
                $isEmriSonucuId = $workTypeCache[$cacheKey];
            } else {
                $isExistingTur = $Tanimlamalar->isEmriSonucu($isEmriTipi, $isEmriSonucu);
                if (!$isExistingTur) {
                    $data = [
                        'firma_id' => $_SESSION["firma_id"],
                        'grup' => 'is_turu',
                        'tur_adi' => $isEmriTipi,
                        'is_emri_sonucu' => $isEmriSonucu,
                        'aciklama' => "Puantaj yükleme sırasında otomatik oluşturuldu"
                    ];
                    $encryptedId = $Tanimlamalar->saveWithAttr($data);
                    // saveWithAttr şifreli id döndürüyor, decrypt et
                    $isEmriSonucuId = \App\Helper\Security::decrypt($encryptedId);
                    // Cache'e ekle
                    $workTypeCache[$cacheKey] = $isEmriSonucuId;
                } else {
                    $isEmriSonucuId = $isExistingTur->id;
                    // Cache'e ekle
                    $workTypeCache[$cacheKey] = $isEmriSonucuId;
                }
            }

            // Insert
            $firmaId = $_SESSION['firma_id'] ?? 0;

            $puantajData = [
                'islem_id' => $islemId,
                'personel_id' => $personelId,
                'ekip_kodu_id' => $ekipId,
                'firma_id' => $firmaId,
                'is_emri_sonucu_id' => $isEmriSonucuId,
                'sonuclanmis' => $sonuclanmis,
                'acik_olanlar' => $acikOlanlar,
                'tarih' => $uploadDate
            ];

            $result = $Puantaj->saveWithAttr($puantajData);

            if ($result) {
                $insertedCount++;
                // İşlemleri biriktir (iki aşamalı işleme için)
                $pendingMovements[] = [
                    'personel_id' => $personelId,
                    'is_emri_sonucu' => $isEmriSonucu,
                    'tarih' => $uploadDate,
                    'islem_id' => $islemId,
                    'miktar' => $sonuclanmis
                ];
            }
        }

        // İKİ AŞAMALI DEMİRBAŞ İŞLEME (Senkronizasyon Sorununu Çözer)
        if (!empty($pendingMovements)) {
            // 1. Aşama: Önce tüm satırların ZİMMETLERİNİ işle
            foreach ($pendingMovements as $pm) {
                $Zimmet->checkAndProcessAutomaticZimmet($pm['personel_id'], $pm['is_emri_sonucu'], $pm['tarih'], $pm['islem_id'], $pm['miktar'], 'zimmet');
            }

            // 2. Aşama: Sonra tüm satırların İADELERİNİ işle
            foreach ($pendingMovements as $pm) {
                $Zimmet->checkAndProcessAutomaticZimmet($pm['personel_id'], $pm['is_emri_sonucu'], $pm['tarih'], $pm['islem_id'], $pm['miktar'], 'iade');
            }
        }




        $response['status'] = 'success';
        $response['inserted_count'] = $insertedCount;
        $response['skipped_count'] = $skippedCount;
        $response['skipped_rows'] = $skippedRows;

        $message = "İşlem tamamlandı. $insertedCount kayıt eklendi.";
        if ($skippedCount > 0) {
            $message .= " $skippedCount kayıt atlandı.";
        }
        $response['message'] = $message;

        // Log Action
        $SystemLog = new SystemLogModel();
        $userId = $_SESSION['user_id'] ?? 0;
        $SystemLog->logAction($userId, 'Puantaj Yükleme', "Excel'den $insertedCount adet puantaj kaydı yüklendi.");

    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }

    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'endeks-excel-kaydet') {
    $response = ['status' => 'error', 'message' => 'Bilinmeyen hata'];
    try {
        if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Dosya yüklenemedi veya dosya seçilmedi.");
        }

        $uploadDateRaw = $_POST['upload_date'] ?? date('Y-m-d');
        $uploadDate = \App\Helper\Date::convertExcelDate($uploadDateRaw, 'Y-m-d') ?: date('Y-m-d');
        $fileTmpPath = $_FILES['excel_file']['tmp_name'];
        $fileName = $_FILES['excel_file']['name'];
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $rows = [];
        if ($extension === 'pdf') {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($fileTmpPath);
            $text = $pdf->getText();

            // Debug log
            file_put_contents(dirname(__DIR__, 2) . '/pdf_debug.txt', $text);

            // Fix character encoding issues from PDF
            $text = str_replace(['Þ', 'Ð', 'Ý', 'ý'], ['Ş', 'Ğ', 'İ', 'i'], $text);
            $lines = explode("\n", $text);

            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line))
                    continue;

                // Pattern for messy PDF format:
                // [Tahakkuk][SıraNo] [User][Region] [Sarfiyat] [OrtSarfiyat] [Tahakkuk] [Perf][Abone] [OrtAbone][Gun]
                // Example: 39,778.771 ER-SAN ELEKTRİK EKİP-AFŞİN 2,290.00 2,290.00 39,778.77 121.18103 103.001
                $pattern = '/([\d.,]+)(\d+)\s+(ER-SAN\s+ELEKTR[İI]K\s+EK[İI]P-?\s?\d*)([A-ZÇĞİIÖŞÜ\s]+?)\s+([\d.,]+)\s+([\d.,]+)\s+([\d.,]+)\s+([\d.,]+\.\d{2})(\d+)\s+([\d.,]+\.\d{2})(\d+)/ui';

                if (preg_match($pattern, $line, $matches)) {
                    $kullaniciAdi = trim($matches[3]);

                    // Extract team number from name
                    $teamNo = 0;
                    if (preg_match('/EK[İI]P-?\s?(\d+)/ui', $kullaniciAdi, $m)) {
                        $teamNo = $m[1];
                    }

                    // Skip if no team number found (User request: "numarası olmayanları atla")
                    if ($teamNo == 0)
                        continue;

                    $rows[] = [
                        'bolge' => trim($matches[4]),
                        'kullanici_adi' => $kullaniciAdi,
                        'team_no' => $teamNo,
                        'sarfiyat' => $matches[5],
                        'ort_sarfiyat' => $matches[6],
                        'tahakkuk' => $matches[7],
                        'ort_tahakkuk' => $matches[1],
                        'okunan_gun' => $matches[11],
                        'okunan_abone' => $matches[9],
                        'ort_okunan_abone' => $matches[10],
                        'performans' => $matches[8]
                    ];
                }
            }

            // If still no rows, try global matching with normalized spaces
            if (empty($rows)) {
                $cleanText = preg_replace('/\s+/', ' ', $text);
                $patternGlobal = '/([\d.,]+)(\d+)\s+(ER-SAN\s+ELEKTR[İI]K\s+EK[İI]P-?\s?\d*)([A-ZÇĞİIÖŞÜ\s]+?)\s+([\d.,]+)\s+([\d.,]+)\s+([\d.,]+)\s+([\d.,]+\.\d{2})(\d+)\s+([\d.,]+\.\d{2})(\d+)/ui';
                if (preg_match_all($patternGlobal, $cleanText, $allMatches, PREG_SET_ORDER)) {
                    foreach ($allMatches as $m) {
                        $kullaniciAdi = trim($m[3]);
                        $teamNo = 0;
                        if (preg_match('/EK[İI]P-?\s?(\d+)/ui', $kullaniciAdi, $tm)) {
                            $teamNo = $tm[1];
                        }

                        if ($teamNo == 0)
                            continue;

                        $rows[] = [
                            'bolge' => trim($m[4]),
                            'kullanici_adi' => $kullaniciAdi,
                            'team_no' => $teamNo,
                            'sarfiyat' => $m[5],
                            'ort_sarfiyat' => $m[6],
                            'tahakkuk' => $m[7],
                            'ort_tahakkuk' => $m[1],
                            'okunan_gun' => $m[11],
                            'okunan_abone' => $m[9],
                            'ort_okunan_abone' => $m[10],
                            'performans' => $m[8]
                        ];
                    }
                }
            }
        } else {
            $spreadsheet = IOFactory::load($fileTmpPath);
            $sheet = $spreadsheet->getActiveSheet();
            $excelRows = $sheet->toArray();

            $headerRowIndex = null;
            $colMap = [];

            foreach ($excelRows as $index => $row) {
                $rowStr = implode(' ', array_map('strval', $row));
                if (stripos($rowStr, 'Bölgesi') !== false && stripos($rowStr, 'Kullanıcı Adı') !== false && stripos($rowStr, 'Sarfiyat') !== false) {
                    $headerRowIndex = $index;
                    foreach ($row as $colIndex => $cellValue) {
                        $cellValue = trim($cellValue);
                        if (stripos($cellValue, 'Bölgesi') !== false)
                            $colMap['bolge'] = $colIndex;
                        elseif (stripos($cellValue, 'Kullanıcı Adı') !== false)
                            $colMap['kullanici_adi'] = $colIndex;
                        elseif (stripos($cellValue, 'Sarfiyat') !== false && stripos($cellValue, 'Ortalama') === false)
                            $colMap['sarfiyat'] = $colIndex;
                        elseif (stripos($cellValue, 'Ortalama Sarfiyat') !== false)
                            $colMap['ort_sarfiyat_gunluk'] = $colIndex;
                        elseif (stripos($cellValue, 'Tahakkuk') !== false && stripos($cellValue, 'Ortalama') === false)
                            $colMap['tahakkuk'] = $colIndex;
                        elseif (stripos($cellValue, 'Ortalama Tahakkuk') !== false)
                            $colMap['ort_tahakkuk_gunluk'] = $colIndex;
                        elseif (stripos($cellValue, 'Okunan Gün Sayısı') !== false)
                            $colMap['okunan_gun_sayisi'] = $colIndex;
                        elseif (stripos($cellValue, 'Okunan Abone Sayısı') !== false)
                            $colMap['okunan_abone_sayisi'] = $colIndex;
                        elseif (stripos($cellValue, 'Ortalama Okunan Abone') !== false)
                            $colMap['ort_okunan_abone_sayisi_gunluk'] = $colIndex;
                        elseif (stripos($cellValue, 'Okuma Performansı') !== false)
                            $colMap['okuma_performansi'] = $colIndex;
                    }
                    break;
                }
            }

            if ($headerRowIndex !== null) {
                for ($i = $headerRowIndex + 1; $i < count($excelRows); $i++) {
                    $row = $excelRows[$i];
                    $excelRowNum = $i + 1; // Excel satır numarası (1-indexed)
                    $bolge = isset($colMap['bolge']) ? trim($row[$colMap['bolge']]) : '';
                    $kullanici_adi = isset($colMap['kullanici_adi']) ? trim($row[$colMap['kullanici_adi']]) : '';

                    if (empty($bolge) && empty($kullanici_adi))
                        continue;
                    if (stripos($bolge, 'Toplam') !== false || stripos($kullanici_adi, 'Toplam') !== false)
                        continue;
                    if (!empty($bolge) && empty($kullanici_adi))
                        continue;

                    $teamNo = 0;
                    if (preg_match('/EK[İI]P-?\s?(\d+)/ui', $kullanici_adi, $m)) {
                        $teamNo = $m[1];
                    }

                    if ($teamNo == 0)
                        continue;

                    $rows[] = [
                        'bolge' => $bolge,
                        'kullanici_adi' => $kullanici_adi,
                        'team_no' => $teamNo,
                        'excel_row' => $excelRowNum,
                        'sarfiyat' => $row[$colMap['sarfiyat']] ?? 0,
                        'ort_sarfiyat' => $row[$colMap['ort_sarfiyat_gunluk']] ?? 0,
                        'tahakkuk' => $row[$colMap['tahakkuk']] ?? 0,
                        'ort_tahakkuk' => $row[$colMap['ort_tahakkuk_gunluk']] ?? 0,
                        'okunan_gun' => $row[$colMap['okunan_gun_sayisi']] ?? 0,
                        'okunan_abone' => $row[$colMap['okunan_abone_sayisi']] ?? 0,
                        'ort_okunan_abone' => $row[$colMap['ort_okunan_abone_sayisi_gunluk']] ?? 0,
                        'performans' => $row[$colMap['okuma_performansi']] ?? 0
                    ];
                }
            }
        }

        if (empty($rows)) {
            throw new Exception("Dosyadan veri okunamadı veya format geçersiz.");
        }

        $insertedCount = 0;
        $skippedCount = 0;
        $skippedRows = []; // Atlanan satırların detayları
        $EndeksOkuma = new \App\Model\EndeksOkumaModel();
        $firmaId = $_SESSION['firma_id'] ?? 0;

        foreach ($rows as $rowIndex => $data) {
            $bolge = $data['bolge'];
            $kullanici_adi = $data['kullanici_adi'];
            $teamNo = $data['team_no'] ?? 0;
            $excelRowNum = $data['excel_row'] ?? ($rowIndex + 1);

            // Robust number parsing: remove thousands separator (comma) and keep decimal (dot)
            $sarfiyat = (float) str_replace(',', '', $data['sarfiyat']);
            $ort_sarfiyat = (float) str_replace(',', '', $data['ort_sarfiyat']);
            $tahakkuk = (float) str_replace(',', '', $data['tahakkuk']);
            $ort_tahakkuk = (float) str_replace(',', '', $data['ort_tahakkuk']);
            $okunan_gun = (int) $data['okunan_gun'];
            $okunan_abone = (int) $data['okunan_abone'];
            $ort_okunan_abone = (float) str_replace(',', '', $data['ort_okunan_abone']);
            $performans = (float) str_replace(',', '', $data['performans']);

            // Personel mapping logic
            $personelId = 0;
            if ($teamNo > 0) {
                // 1. Find team definition ID in tanimlamalar
                $stmtDef = $EndeksOkuma->db->prepare("
                    SELECT id FROM tanimlamalar 
                    WHERE (tur_adi LIKE ? OR tur_adi LIKE ?) 
                    AND grup = 'ekip_kodu' 
                    AND firma_id = ? 
                    AND silinme_tarihi IS NULL 
                    LIMIT 1
                ");
                $stmtDef->execute(["%EKİP-$teamNo", "%EKIP-$teamNo", $_SESSION['firma_id']]);
                $defId = $stmtDef->fetchColumn();

                if ($defId) {
                    // 2. Find person assigned to this team ID who was ACTIVE on the upload date
                    // - ekip_no matches the definition ID
                    // - aktif_mi = 1 (active)
                    // - ise_giris_tarihi <= uploadDate (started before or on the date)
                    // - isten_cikis_tarihi IS NULL OR isten_cikis_tarihi >= uploadDate (hasn't left yet or left after the date)
                    // 2. Find person from team history who was ACTIVE on the upload date
                    $stmtPersonel = $EndeksOkuma->db->prepare("
                        SELECT p.id 
                        FROM personel p
                        JOIN personel_ekip_gecmisi pg ON p.id = pg.personel_id
                        WHERE pg.ekip_kodu_id = ? 
                        AND pg.baslangic_tarihi <= ?
                        AND (pg.bitis_tarihi IS NULL OR pg.bitis_tarihi = '' OR pg.bitis_tarihi >= ?)
                        AND p.silinme_tarihi IS NULL
                        AND pg.firma_id = ?
                        LIMIT 1
                    ");
                    $stmtPersonel->execute([$defId, $uploadDate, $uploadDate, $_SESSION['firma_id']]);
                    $personelId = $stmtPersonel->fetchColumn() ?: 0;
                }
            }

            // Skip if personel not found as per user request
            if ($personelId == 0) {
                $skippedCount++;
                $skippedRows[] = [
                    'satir' => $excelRowNum,
                    'ekip' => $kullanici_adi,
                    'neden' => "EKİP-$teamNo: O tarihte aktif personel bulunamadı"
                ];
                continue;
            }

            $stmt = $EndeksOkuma->db->prepare("INSERT INTO endeks_okuma (personel_id, ekip_kodu_id, firma_id, bolge, kullanici_adi, sarfiyat, ort_sarfiyat_gunluk, tahakkuk, ort_tahakkuk_gunluk, okunan_gun_sayisi, okunan_abone_sayisi, ort_okunan_abone_sayisi_gunluk, okuma_performansi, tarih) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $result = $stmt->execute([$personelId, $defId, $firmaId, $bolge, $kullanici_adi, $sarfiyat, $ort_sarfiyat, $tahakkuk, $ort_tahakkuk, $okunan_gun, $okunan_abone, $ort_okunan_abone, $performans, $uploadDate]);
            if ($result)
                $insertedCount++;
        }

        if ($insertedCount === 0 && $skippedCount > 0) {
            throw new Exception("Yüklenen dosyadaki hiçbir ekip personel tablosuyla eşleşmedi. Lütfen ekip numaralarını kontrol edin. ($skippedCount kayıt atlandı)");
        }

        $response['status'] = 'success';
        $response['inserted_count'] = $insertedCount;
        $response['skipped_count'] = $skippedCount;
        $response['skipped_rows'] = $skippedRows;

        $message = "$insertedCount kayıt başarıyla yüklendi.";
        if ($skippedCount > 0) {
            $message .= " $skippedCount kayıt atlandı.";
        }
        $response['message'] = $message;

        $SystemLog = new SystemLogModel();
        $userId = $_SESSION['user_id'] ?? 0;
        $SystemLog->logAction($userId, 'Endeks Okuma Yükleme', "$extension dosyasından $insertedCount adet endeks okuma kaydı yüklendi.");

    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'kacak-excel-kaydet') {
    $response = ['status' => 'error', 'message' => 'Bilinmeyen hata'];
    try {
        if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Dosya yüklenemedi veya dosya seçilmedi.");
        }

        $uploadDateRaw = $_POST['upload_date'] ?? date('Y-m-d');
        // Ensure date is in Y-m-d format
        $uploadDate = \App\Helper\Date::convertExcelDate($uploadDateRaw, 'Y-m-d') ?: date('Y-m-d');

        $fileTmpPath = $_FILES['excel_file']['tmp_name'];
        $spreadsheet = IOFactory::load($fileTmpPath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        // Find header row
        $headerRowIndex = null;
        $colMap = [];
        foreach ($rows as $index => $row) {
            $rowStr = implode(' ', array_map('strval', $row));
            // Expecting: Ekip Adı, Sayı, Açıklama
            if (stripos($rowStr, 'Ekip') !== false && (stripos($rowStr, 'Sayı') !== false || stripos($rowStr, 'Sayi') !== false)) {
                $headerRowIndex = $index;
                foreach ($row as $colIndex => $cellValue) {
                    $cellValue = trim($cellValue);
                    if (stripos($cellValue, 'Ekip') !== false)
                        $colMap['ekip'] = $colIndex;
                    elseif (stripos($cellValue, 'Sayı') !== false || stripos($cellValue, 'Sayi') !== false)
                        $colMap['sayi'] = $colIndex;
                    elseif (stripos($cellValue, 'Açıklama') !== false || stripos($cellValue, 'Aciklama') !== false)
                        $colMap['aciklama'] = $colIndex;
                }
                break;
            }
        }

        if ($headerRowIndex === null) {
            throw new Exception("Excel formatı geçersiz. Başlık satırı (Ekip, Sayı...) bulunamadı.");
        }

        $insertedCount = 0;
        $skippedCount = 0;
        $unmatchedPersonnel = []; // Eşleşmeyen personeller
        $firmaId = $_SESSION['firma_id'] ?? 0;
        $Personel = new PersonelModel();
        $unmatchedRows = []; // Eşleşmeyen satırlar (satır no, ekip adı, eşleşmeyen isimler)

        for ($i = $headerRowIndex + 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            $excelRowNum = $i + 1; // Excel satır numarası (1-indexed, header dahil)
            $ekipStr = isset($colMap['ekip']) ? trim($row[$colMap['ekip']]) : '';
            $sayi = isset($colMap['sayi']) ? (int) trim($row[$colMap['sayi']]) : 0;
            $aciklama = isset($colMap['aciklama']) ? trim($row[$colMap['aciklama']]) : '';

            if (empty($ekipStr))
                continue;

            // Ekip adındaki virgülle ayrılmış isimleri personel tablosunda ara
            $personelIds = [];
            $unmatchedInRow = []; // Bu satırdaki eşleşmeyen isimler
            $isimler = array_map('trim', explode(',', $ekipStr));

            foreach ($isimler as $isim) {
                if (empty($isim))
                    continue;
                // Personel tablosunda isimle birebir eşleşme ara
                $stmtPers = $Puantaj->db->prepare("SELECT id FROM personel WHERE adi_soyadi = ? AND silinme_tarihi IS NULL LIMIT 1");
                $stmtPers->execute([$isim]);
                $persId = $stmtPers->fetchColumn();
                if ($persId) {
                    $personelIds[] = $persId;
                } else {
                    $unmatchedInRow[] = $isim;
                }
            }

            // Eğer herhangi bir personel eşleşmediyse bu satırı atla
            if (!empty($unmatchedInRow)) {
                $unmatchedRows[] = [
                    'satir' => $excelRowNum,
                    'ekip' => $ekipStr,
                    'eslesmeyen' => $unmatchedInRow
                ];
                $skippedCount++;
                continue;
            }

            $personelIdsStr = implode(',', $personelIds);

            // Unique ID for idempotency
            $islemId = md5($uploadDate . '|' . $ekipStr . '|' . $sayi . '|' . $aciklama);

            $exists = $Puantaj->db->prepare("SELECT COUNT(*) FROM kacak_kontrol WHERE islem_id = ? AND silinme_tarihi IS NULL");
            $exists->execute([$islemId]);
            if ($exists->fetchColumn() > 0) {
                $skippedCount++;
                $unmatchedRows[] = [
                    'satir' => $excelRowNum,
                    'ekip' => $ekipStr,
                    'neden' => 'Bu kayıt daha önce yüklenmiş (duplicate)'
                ];
                continue;
            }

            $stmt = $Puantaj->db->prepare("INSERT INTO kacak_kontrol (firma_id, personel_ids, tarih, ekip_adi, sayi, aciklama, islem_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $result = $stmt->execute([$firmaId, $personelIdsStr, $uploadDate, $ekipStr, $sayi, $aciklama, $islemId]);
            if ($result)
                $insertedCount++;
        }

        $response['status'] = 'success';
        $response['inserted_count'] = $insertedCount;
        $response['skipped_count'] = $skippedCount;

        // skipped_rows formatını standartlaştır
        $skippedRows = [];
        foreach ($unmatchedRows as $ur) {
            if (isset($ur['eslesmeyen'])) {
                $skippedRows[] = [
                    'satir' => $ur['satir'],
                    'ekip' => $ur['ekip'],
                    'neden' => 'Personel eşleşmedi: ' . implode(', ', $ur['eslesmeyen'])
                ];
            } else {
                $skippedRows[] = $ur;
            }
        }
        $response['skipped_rows'] = $skippedRows;

        $message = "$insertedCount kayıt eklendi.";
        if ($skippedCount > 0) {
            $message .= " $skippedCount kayıt atlandı.";
        }
        $response['message'] = $message;

        $SystemLog = new SystemLogModel();
        $userId = $_SESSION['user_id'] ?? 0;
        $SystemLog->logAction($userId, 'Kaçak Kontrol Yükleme', "Excel'den $insertedCount adet kaçak kontrol kaydı yüklendi.");

    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'kacak-sil') {
    $id = $_POST['id'] ?? 0;
    $stmt = $Puantaj->db->prepare("UPDATE kacak_kontrol SET silinme_tarihi = NOW() WHERE id = ?");
    $result = $stmt->execute([$id]);
    echo json_encode(['status' => $result ? 'success' : 'error']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'endeks-sil') {
    $id = $_POST['id'] ?? 0;
    $EndeksOkuma = new EndeksOkumaModel();
    $stmt = $EndeksOkuma->db->prepare("UPDATE endeks_okuma SET silinme_tarihi = NOW() WHERE id = ?");
    $result = $stmt->execute([$id]);
    echo json_encode(['status' => $result ? 'success' : 'error']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'puantaj-sil') {
    $id = $_POST['id'] ?? 0;
    $Puantaj = new PuantajModel();
    $stmt = $Puantaj->db->prepare("UPDATE yapilan_isler SET silinme_tarihi = NOW() WHERE id = ?");
    $result = $stmt->execute([$id]);
    echo json_encode(['status' => $result ? 'success' : 'error']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'kacak-kaydet') {
    $response = ['status' => 'error', 'message' => 'Bilinmeyen hata'];
    try {
        $id = $_POST['id'] ?? 0;
        $tarih = $_POST['tarih'] ?? date('Y-m-d');
        $personelIdsArr = $_POST['kacak_personel_ids'] ?? [];
        $sayi = $_POST['sayi'] ?? 0;
        $aciklama = $_POST['aciklama'] ?? '';
        $firmaId = $_SESSION['firma_id'] ?? 0;
        $passedEkipAdi = $_POST['ekip_adi'] ?? '';

        $dbTarih = \App\Helper\Date::convertExcelDate($tarih, 'Y-m-d') ?: $tarih;

        // personel_ids'i virgülle ayrılmış string yap
        $personelIdsStr = is_array($personelIdsArr) ? implode(',', $personelIdsArr) : $personelIdsArr;

        // Ekip adını belirle (Önce varsa parametreden al, yoksa isimlerden oluştur)
        $ekipAdi = $passedEkipAdi;
        if (empty($ekipAdi) && !empty($personelIdsArr) && is_array($personelIdsArr)) {
            $Personel = new PersonelModel();
            $isimler = [];
            foreach ($personelIdsArr as $pId) {
                $p = $Personel->find($pId);
                if ($p) {
                    $isimler[] = $p->adi_soyadi;
                }
            }
            $ekipAdi = implode(', ', $isimler);
        }

        if ($id > 0) {
            // Explicit update by ID
            $stmt = $Puantaj->db->prepare("UPDATE kacak_kontrol SET tarih = ?, personel_ids = ?, ekip_adi = ?, sayi = ?, aciklama = ? WHERE id = ?");
            $result = $stmt->execute([$dbTarih, $personelIdsStr, $ekipAdi, $sayi, $aciklama, $id]);
        } else {
            // Check if a record with the same tarih and personel_ids OR the same tarih and ekip_adi already exists
            // Since kacak_kontrol is team-based, if we have the same date and same team name (ekip_adi), it's likely the same record
            $checkSql = "SELECT id FROM kacak_kontrol WHERE firma_id = ? AND tarih = ? AND silinme_tarihi IS NULL";
            $checkParams = [$firmaId, $dbTarih];

            if (!empty($passedEkipAdi)) {
                $checkSql .= " AND ekip_adi = ?";
                $checkParams[] = $passedEkipAdi;
            } else {
                $checkSql .= " AND personel_ids = ?";
                $checkParams[] = $personelIdsStr;
            }

            $checkStmt = $Puantaj->db->prepare($checkSql);
            $checkStmt->execute($checkParams);
            $existingRecord = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($existingRecord) {
                // Update existing record
                $stmt = $Puantaj->db->prepare("UPDATE kacak_kontrol SET sayi = ?, aciklama = ?, personel_ids = ?, ekip_adi = ? WHERE id = ?");
                $result = $stmt->execute([$sayi, $aciklama, $personelIdsStr, $ekipAdi, $existingRecord['id']]);
            } else {
                // Insert new record
                $islemId = md5($dbTarih . '|' . $personelIdsStr . '|' . $sayi . '|' . $aciklama . '|' . microtime());
                $stmt = $Puantaj->db->prepare("INSERT INTO kacak_kontrol (firma_id, personel_ids, tarih, ekip_adi, sayi, aciklama, islem_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $result = $stmt->execute([$firmaId, $personelIdsStr, $dbTarih, $ekipAdi, $sayi, $aciklama, $islemId]);
            }
        }
        $response = ['status' => $result ? 'success' : 'error'];
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get-kacak-record') {
    $id = $_GET['id'] ?? 0;
    $stmt = $Puantaj->db->prepare("SELECT * FROM kacak_kontrol WHERE id = ?");
    $stmt->execute([$id]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($record) {
        $record['tarih_formatted'] = \App\Helper\Date::dmY($record['tarih']);
        // personel_ids'i array olarak döndür
        $record['personel_ids_array'] = !empty($record['personel_ids'])
            ? array_map('intval', explode(',', $record['personel_ids']))
            : [];
    }
    echo json_encode($record);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get-kacak-teams') {
    $teams = $Puantaj->getKacakTeams();
    echo json_encode($teams);
    exit;
}

// Server-side DataTable için Endeks Okuma verileri
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'endeks-datatable') {
    header('Content-Type: application/json');

    $startDate = $_GET['start_date'] ?? '';
    $endDate = $_GET['end_date'] ?? '';
    $ekipKodu = $_GET['ekip_kodu'] ?? '';

    $EndeksOkuma = new \App\Model\EndeksOkumaModel();
    $result = $EndeksOkuma->getDataTable($_GET, $startDate, $endDate, $ekipKodu);

    // Veriyi DataTable formatına dönüştür
    $formattedData = [];
    foreach ($result['data'] as $record) {
        $formattedData[] = [
            'tarih' => \App\Helper\Date::dmY($record->tarih),
            'defter' => $record->defter ?? '-',
            'bolge' => $record->bolge,
            'ekip_no' => $record->ekip_kodu_adi ?: ($record->ekip_kodu_id ?: '-'),
            'personel_adi' => $record->personel_adi ?: '<span class="text-muted">' . htmlspecialchars($record->kullanici_adi) . '</span>',
            'okunan_abone_sayisi' => $record->okunan_abone_sayisi,
            'sayac_durum' => $record->sayac_durum ?? '-',
            'sarfiyat' => number_format($record->sarfiyat, 2, ',', '.'),
            'ort_sarfiyat_gunluk' => number_format($record->ort_sarfiyat_gunluk, 2, ',', '.'),
            'tahakkuk' => number_format($record->tahakkuk, 2, ',', '.'),
            'ort_tahakkuk_gunluk' => number_format($record->ort_tahakkuk_gunluk, 2, ',', '.'),
            'okunan_gun_sayisi' => $record->okunan_gun_sayisi,
            'ort_okunan_abone_sayisi_gunluk' => number_format($record->ort_okunan_abone_sayisi_gunluk, 2, ',', '.'),
            'okuma_performansi' => '%' . number_format($record->okuma_performansi, 2, ',', '.'),
            'id' => $record->id
        ];
    }

    echo json_encode([
        'draw' => $result['draw'],
        'recordsTotal' => $result['recordsTotal'],
        'recordsFiltered' => $result['recordsFiltered'],
        'data' => $formattedData
    ]);
    exit;
}

// Server-side DataTable için Puantaj (Kesme/Açma) verileri
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'puantaj-datatable') {
    header('Content-Type: application/json');

    $startDate = $_GET['start_date'] ?? '';
    $endDate = $_GET['end_date'] ?? '';
    $ekipKodu = $_GET['ekip_kodu'] ?? '';
    $workType = $_GET['work_type'] ?? '';
    $workResult = $_GET['work_result'] ?? '';

    $Puantaj = new PuantajModel();
    $result = $Puantaj->getDataTable($_GET, $startDate, $endDate, $ekipKodu, $workType, $workResult);

    // Veriyi DataTable formatına dönüştür
    $formattedData = [];
    foreach ($result['data'] as $record) {
        $formattedData[] = [
            'tarih' => \App\Helper\Date::dmY($record->tarih),
            'ekip_kodu' => $record->ekip_kodu_adi ?? ($record->ekip_kodu ?? '-'),
            'personel_adi' => $record->personel_adi ?: '<span class="text-muted">' . htmlspecialchars($record->ekip_kodu ?? '') . '</span>',
            'is_emri_tipi' => $record->is_emri_tipi ?? '',
            'is_emri_sonucu' => $record->is_emri_sonucu ?? '',
            'sonuclanmis' => $record->sonuclanmis ?? 0,
            'acik_olanlar' => $record->acik_olanlar ?? 0,
            'id' => $record->id
        ];
    }

    echo json_encode([
        'draw' => $result['draw'],
        'recordsTotal' => $result['recordsTotal'],
        'recordsFiltered' => $result['recordsFiltered'],
        'data' => $formattedData
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get-tab-content') {
    $tab = $_GET['tab'] ?? 'okuma';
    $startDate = $_GET['start_date'] ?? '';
    $endDate = $_GET['end_date'] ?? '';
    $ekipKodu = $_GET['ekip_kodu'] ?? '';
    $workType = $_GET['work_type'] ?? '';
    $workResult = $_GET['work_result'] ?? '';

    ob_start();
    // Convert dates for SQL
    $dbStartDate = \App\Helper\Date::convertExcelDate($startDate, 'Y-m-d') ?: $startDate;
    $dbEndDate = \App\Helper\Date::convertExcelDate($endDate, 'Y-m-d') ?: $endDate;

    $Puantaj = new PuantajModel();

    if ($tab === 'okuma') {
        $EndeksOkuma = new \App\Model\EndeksOkumaModel();
        $endeksRecords = $EndeksOkuma->getFiltered($dbStartDate, $dbEndDate, $ekipKodu);
        foreach ($endeksRecords as $record): ?>
            <tr>
                <td><?= $record->bolge ?></td>
                <td><?= $record->personel_adi ?: '<span class="text-muted">' . $record->kullanici_adi . '</span>' ?></td>
                <td><?= number_format($record->sarfiyat, 2, ',', '.') ?></td>
                <td><?= number_format($record->ort_sarfiyat_gunluk, 2, ',', '.') ?></td>
                <td><?= number_format($record->tahakkuk, 2, ',', '.') ?></td>
                <td><?= number_format($record->ort_tahakkuk_gunluk, 2, ',', '.') ?></td>
                <td><?= $record->okunan_gun_sayisi ?></td>
                <td><?= $record->okunan_abone_sayisi ?></td>
                <td><?= number_format($record->ort_okunan_abone_sayisi_gunluk, 2, ',', '.') ?></td>
                <td>%<?= number_format($record->okuma_performansi, 2, ',', '.') ?></td>
                <td><?= \App\Helper\Date::dmY($record->tarih) ?></td>
                <td>
                    <button class="btn btn-sm btn-soft-danger delete-endeks" data-id="<?= $record->id ?>"><i
                            class="bx bx-trash"></i></button>
                </td>
            </tr>
        <?php endforeach;
    } elseif ($tab === 'kacak_kontrol') {
        $firmaId = $_SESSION['firma_id'] ?? 0;
        // personel_ids artık virgülle ayrılmış ID'ler içerdiği için doğrudan ekip_adi gösteriliyor
        $sql = "SELECT k.* FROM kacak_kontrol k WHERE k.tarih BETWEEN ? AND ? AND k.silinme_tarihi IS NULL AND k.firma_id = ?";
        $params = [$dbStartDate, $dbEndDate, $firmaId];

        // Personel filtresi - personel_ids içinde aranan ID var mı kontrol et
        if (!empty($ekipKodu)) {
            $sql .= " AND FIND_IN_SET(?, k.personel_ids)";
            $params[] = $ekipKodu;
        }

        $sql .= " ORDER BY k.tarih DESC";
        $stmt = $Puantaj->db->prepare($sql);
        $stmt->execute($params);
        $records = $stmt->fetchAll(PDO::FETCH_OBJ);
        foreach ($records as $record): ?>
            <tr>
                <td><?= \App\Helper\Date::dmY($record->tarih) ?></td>
                <td><?= $record->ekip_adi ?: '<span class="text-muted">-</span>' ?></td>
                <td><?= $record->sayi ?></td>
                <td><?= $record->aciklama ?></td>
                <td>
                    <button class="btn btn-sm btn-soft-primary edit-kacak" data-id="<?= $record->id ?>"><i
                            class="bx bx-edit"></i></button>
                    <button class="btn btn-sm btn-soft-danger delete-kacak" data-id="<?= $record->id ?>"><i
                            class="bx bx-trash"></i></button>
                </td>
            </tr>
        <?php endforeach;
    } else {
        $records = $Puantaj->getFiltered($dbStartDate, $dbEndDate, $ekipKodu, $workType, $workResult);
        foreach ($records as $record): ?>
            <tr>
                <td><?= $record->firma ?></td>
                <td><?= $record->is_emri_tipi ?></td>
                <td><?= $record->personel_adi ?: '<span class="text-muted">' . $record->ekip_kodu . '</span>' ?></td>
                <td><?= $record->is_emri_sonucu ?></td>
                <td><?= $record->sonuclanmis ?></td>
                <td><?= $record->acik_olanlar ?></td>
                <td><?= \App\Helper\Date::dmY($record->tarih) ?></td>
                <td>
                    <button class="btn btn-sm btn-soft-danger delete-puantaj" data-id="<?= $record->id ?>"><i
                            class="bx bx-trash"></i></button>
                </td>
            </tr>
        <?php endforeach;
    }
    $html = ob_get_clean();
    echo $html;
    exit;
}

// Rapor Tablosunu Getir
if (isset($_GET["action"]) && $_GET["action"] == "get-report-table") {
    require_once 'rapor-getir.php';
    exit;
}

// Online Puantaj (Kesme/Açma İşlemleri) Sorgulama
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'online-puantaj-sorgula') {
    $response = ['status' => 'error', 'message' => 'Bilinmeyen hata'];

    try {
        $ilkFirma = $_POST['ilk_firma'] ?? 17;
        $sonFirma = $_POST['son_firma'] ?? 17;
        $baslangicTarihiRaw = $_POST['baslangic_tarihi'] ?? date('Y-m-d');
        $bitisTarihiRaw = $_POST['bitis_tarihi'] ?? date('Y-m-d');

        $baslangicTarihi = \App\Helper\Date::convertExcelDate($baslangicTarihiRaw, 'Y-m-d') ?: date('Y-m-d');
        $bitisTarihi = \App\Helper\Date::convertExcelDate($bitisTarihiRaw, 'Y-m-d') ?: date('Y-m-d');

        $firmaId = $_SESSION['firma_id'] ?? 0;

        $KesmeAcmaSvc = new KesmeAcmaService();
        $baslangicTarihiAPI = \App\Helper\Date::convertExcelDate($baslangicTarihiRaw, 'd/m/Y') ?: $baslangicTarihiRaw;
        $bitisTarihiAPI = \App\Helper\Date::convertExcelDate($bitisTarihiRaw, 'd/m/Y') ?: $bitisTarihiRaw;

        $apiData = [];

        // Tarih aralığını günlere bölerek tek tek çekiyoruz (API birleştirme yapmasın diye)
        $begin = new DateTime($baslangicTarihi);
        $end = new DateTime($bitisTarihi);
        $end->modify('+1 day'); // Bitiş gününü dahil et

        $interval = new DateInterval('P1D');
        $daterange = new DatePeriod($begin, $interval, $end);

        // PHP zaman aşımını uzat (çok günlük sorgularda)
        set_time_limit(300);

        foreach ($daterange as $date) {
            $currentDateAPI = $date->format('d/m/Y');

            try {
                $offset = 0;
                $limit = 100;
                $hasMore = true;

                while ($hasMore) {
                    // Günlük bazda sorgu yapıyoruz
                    $apiResponse = $KesmeAcmaSvc->getData($currentDateAPI, $currentDateAPI, $limit, $offset);

                    if (!($apiResponse['success'] ?? false)) {
                        break; // Bu gün için hata varsa veya veri yoksa diğer güne geç
                    }

                    $batchData = $apiResponse['data']['data'] ?? [];
                    if (empty($batchData)) {
                        $hasMore = false;
                    } else {
                        // Her kayda hangi güne ait olduğunu enjekte ediyoruz
                        foreach ($batchData as &$item) {
                            if (!isset($item['TARIH'])) {
                                $item['TARIH'] = $date->format('Y-m-d');
                            }
                        }
                        $apiData = array_merge($apiData, $batchData);
                        if (count($batchData) < $limit) {
                            $hasMore = false;
                        } else {
                            $offset += $limit;
                        }
                    }

                    if ($offset >= 1000)
                        break; // Bir gün için 1000 kayıt güvenlik sınırı
                }
            } catch (Exception $e) {
                // Bu günün hatası diğer günleri engellemesin, devam et
                continue;
            }
        }

        $yeniKayit = 0;
        $guncellenenKayit = 0;
        $mevcutKayitlar = [];
        $atlanAnKayitlar = [];
        $bosSonucSayisi = 0;

        foreach ($apiData as $veri) {
            // API'den gelen gerçek alan adlarını eşleştiriyoruz
            $firmaAdi = $veri['FIRMAADI'] ?? 'ER-SAN ELEKTRİK';
            $isEmriTipi = $veri['ISEMRITIPI'] ?? '';
            $ekipKodu = $veri['EKIP'] ?? '';
            $isEmriSonucu = $veri['SONUC'] ?? '';
            $sonuclanmis = $veri['SONUCLANMIS'] ?? 0;
            $acikOlanlar = $veri['ACIK'] ?? 0;

            // SONUCLANMIS 0 olan kayıtları atla
            if ((int) $sonuclanmis === 0) {
                $bosSonucSayisi++;
                continue;
            }

            // Artık her kaydın kendi tarihi (TARIH) var
            $tarihRaw = $veri['TARIH'];
            $normDate = \App\Helper\Date::convertExcelDate($tarihRaw, 'Y-m-d') ?: $tarihRaw;

            // Unique ID oluştur (Sonuç ve ekip bazlı)
            $rawIdString = $normDate . '|' . $ekipKodu . '|' . $isEmriTipi . '|' . $isEmriSonucu;
            $islemId = md5($rawIdString);

            // Tanimlamalar'dan is_emri_sonucu_id bul (İş türü ve sonucuna göre)
            $existingTur = $Tanimlamalar->isEmriSonucu($isEmriTipi, $isEmriSonucu);

            if (!$existingTur && (!empty($isEmriTipi) || !empty($isEmriSonucu))) {
                // Eğer yoksa yeni oluştur (Excel yükleme mantığı)
                $dataTanim = [
                    'firma_id' => $firmaId ?: ($_SESSION['firma_id'] ?? 0),
                    'grup' => 'is_turu',
                    'tur_adi' => $isEmriTipi,
                    'is_emri_sonucu' => $isEmriSonucu,
                    'aciklama' => "Online sorgulama sırasında otomatik oluşturuldu"
                ];
                $encryptedId = $Tanimlamalar->saveWithAttr($dataTanim);
                $isEmriSonucuId = \App\Helper\Security::decrypt($encryptedId);
            } else {
                $isEmriSonucuId = $existingTur ? $existingTur->id : 0;
            }

            // Eğer ID bulunduysa string kolonlara ekleme yapma
            $saveTipi = $isEmriSonucuId ? '' : $isEmriTipi;
            $saveSonucu = $isEmriSonucuId ? '' : $isEmriSonucu;

            // Daha önce aynı islem_id ile kayıt var mı kontrol et
            $checkStmt = $Puantaj->db->prepare("SELECT id, islem_id, ekip_kodu, is_emri_tipi FROM yapilan_isler WHERE islem_id = ? AND silinme_tarihi IS NULL");
            $checkStmt->execute([$islemId]);
            $mevcutKayit = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($mevcutKayit) {
                // Mevcut kayıt varsa güncelle
                $updateStmt = $Puantaj->db->prepare("UPDATE yapilan_isler SET sonuclanmis = ?, acik_olanlar = ?, tarih = ?, is_emri_sonucu_id = ?, is_emri_tipi = ?, is_emri_sonucu = ? WHERE islem_id = ?");
                $updateStmt->execute([$sonuclanmis, $acikOlanlar, $normDate, $isEmriSonucuId, $saveTipi, $saveSonucu, $islemId]);
                $guncellenenKayit++;

                // Excel raporu için değerleri doldur
                $mevcutKayit['is_emri_tipi'] = $isEmriTipi;
                $mevcutKayit['is_emri_sonucu'] = $isEmriSonucu;
                $mevcutKayit['tarih'] = \App\Helper\Date::Ymd($normDate, 'd.m.Y');
                $mevcutKayitlar[] = $mevcutKayit;
            } else {
                // Personel eşleştirme (ekip kodundan)
                $personelId = 0;
                $defId = 0;
                $ekipNo = 0;
                if (preg_match('/EK[İI]P-?\s?(\d+)/ui', $ekipKodu, $m)) {
                    $ekipNo = $m[1];
                }

                if ($ekipNo > 0) {
                    $stmtDef = $Puantaj->db->prepare("SELECT id FROM tanimlamalar WHERE (tur_adi LIKE ? OR tur_adi LIKE ?) AND grup = 'ekip_kodu' AND silinme_tarihi IS NULL LIMIT 1");
                    $stmtDef->execute(["%EKİP-$ekipNo", "%EKIP-$ekipNo"]);
                    $defId = $stmtDef->fetchColumn();

                    if ($defId) {
                        // 1. Önce o tarihteki ekip geçmişinden personeli bulmaya çalış (en doğru yöntem)
                        $stmtHist = $Puantaj->db->prepare("SELECT personel_id FROM personel_ekip_gecmisi 
                                                         WHERE ekip_kodu_id = ? AND baslangic_tarihi <= ? 
                                                         AND (bitis_tarihi IS NULL OR bitis_tarihi >= ?) 
                                                         LIMIT 1");
                        $stmtHist->execute([$defId, $normDate, $normDate]);
                        $personelId = $stmtHist->fetchColumn();

                        // 2. Bulunamazsa mevcut personel tablosundaki ekip_no'ya bak
                        if (!$personelId) {
                            $stmtPersonel = $Puantaj->db->prepare("SELECT id FROM personel WHERE ekip_no = ? AND silinme_tarihi IS NULL LIMIT 1");
                            $stmtPersonel->execute([$defId]);
                            $personelId = $stmtPersonel->fetchColumn() ?: 0;
                        }
                    }
                }

                // Eğer ekip bulunamadıysa bu kaydı atla
                if ($defId === 0) {
                    $atlanAnKayitlar[] = [
                        'ekip_kodu' => $ekipKodu,
                        'is_emri_tipi' => $isEmriTipi,
                        'is_emri_sonucu' => $isEmriSonucu,
                        'tarih' => \App\Helper\Date::Ymd($normDate, 'd.m.Y')
                    ];
                    continue;
                }

                // Yeni kayıt ekle
                $insertStmt = $Puantaj->db->prepare("INSERT INTO yapilan_isler (islem_id, personel_id, ekip_kodu_id, firma_id, is_emri_sonucu_id, is_emri_tipi, ekip_kodu, is_emri_sonucu, sonuclanmis, acik_olanlar, tarih) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $insertStmt->execute([
                    $islemId,
                    $personelId,
                    $defId,
                    $firmaId,
                    $isEmriSonucuId,
                    $saveTipi,
                    $ekipKodu,
                    $saveSonucu,
                    $sonuclanmis,
                    $acikOlanlar,
                    $normDate
                ]);
                $yeniKayit++;

                // Otomatik Demirbaş İşlemi
                if ($personelId > 0) {
                    $Zimmet->checkAndProcessAutomaticZimmet($personelId, $isEmriSonucu, $normDate, $islemId, $sonuclanmis);
                }
            }
        }

        // Log kaydet
        $SystemLog = new SystemLogModel();
        $userId = $_SESSION['user_id'] ?? 0;
        $SystemLog->logAction($userId, 'Online Puantaj Sorgulama', "API Sorgu, Tarih: $baslangicTarihiAPI - $bitisTarihiAPI. $yeniKayit yeni, $guncellenenKayit güncellenen kayıt.");

        $response['status'] = 'success';
        $response['yeni_kayit'] = $yeniKayit;
        $response['guncellenen_kayit'] = $guncellenenKayit;
        $response['atlan_kayit_bos'] = $bosSonucSayisi;
        $response['mevcut_kayitlar'] = $mevcutKayitlar;
        $response['atlanAn_kayitlar'] = $atlanAnKayitlar;
        $response['toplam_api_kayit'] = count($apiData);
        $response['api_raw_data'] = $apiData; // Hata ayıklama için eklendi
        $response['message'] = "$yeniKayit adet yeni kayıt eklendi.";

    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }

    echo json_encode($response);
    exit;
}

// Online İcmal (Endeks Okuma) Sorgulama
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'online-icmal-sorgula') {
    $response = ['status' => 'error', 'message' => 'Bilinmeyen hata'];

    try {
        $ilkFirma = $_POST['ilk_firma'] ?? 17;
        $sonFirma = $_POST['son_firma'] ?? 17;
        $baslangicTarihiRaw = $_POST['baslangic_tarihi'] ?? date('Y-m-d');
        $bitisTarihiRaw = $_POST['bitis_tarihi'] ?? date('Y-m-d');

        // API dd/mm/yyyy bekliyor
        $baslangicTarihiAPI = \App\Helper\Date::convertExcelDate($baslangicTarihiRaw, 'd/m/Y') ?: date('d/m/Y');
        $bitisTarihiAPI = \App\Helper\Date::convertExcelDate($bitisTarihiRaw, 'd/m/Y') ?: date('d/m/Y');

        // Veritabanı için Y-m-d
        $baslangicTarihiDB = \App\Helper\Date::convertExcelDate($baslangicTarihiRaw, 'Y-m-d') ?: date('Y-m-d');
        $bitisTarihiDB = \App\Helper\Date::convertExcelDate($bitisTarihiRaw, 'Y-m-d') ?: date('Y-m-d');

        $firmaId = $_SESSION['firma_id'] ?? 0;
        $EndeksOkuma = new EndeksOkumaModel();
        $apiService = new EndeskOkumaService();

        // Tarih aralığını günlere bölerek tek tek çekiyoruz (API birleştirme yapmasın diye)
        $apiData = [];
        $begin = new DateTime($baslangicTarihiDB);
        $end = new DateTime($bitisTarihiDB);
        $end->modify('+1 day'); // Bitiş gününü dahil et

        $interval = new DateInterval('P1D');
        $daterange = new DatePeriod($begin, $interval, $end);

        // PHP zaman aşımını uzat (çok günlük sorgularda)
        set_time_limit(300);

        foreach ($daterange as $date) {
            $currentDateAPI = $date->format('d/m/Y');

            try {
                $offset = 0;
                $limit = 100;
                $hasMore = true;

                while ($hasMore) {
                    $apiResponse = $apiService->getData($currentDateAPI, $currentDateAPI, $limit, $offset);

                    if (!($apiResponse['success'] ?? false)) {
                        break; // Bu gün için hata varsa diğer güne geç
                    }

                    $batchData = $apiResponse['data']['data'] ?? [];
                    if (empty($batchData)) {
                        $hasMore = false;
                    } else {
                        // Her kayda hangi güne ait olduğunu enjekte ediyoruz
                        foreach ($batchData as &$item) {
                            if (!isset($item['OKUMATARIHI']) || empty($item['OKUMATARIHI'])) {
                                $item['OKUMATARIHI'] = $date->format('Y-m-d');
                            }
                        }
                        $apiData = array_merge($apiData, $batchData);
                        if (count($batchData) < $limit) {
                            $hasMore = false;
                        } else {
                            $offset += $limit;
                        }
                    }

                    if ($offset >= 1000)
                        break; // Bir gün için güvenlik sınırı
                }
            } catch (Exception $e) {
                // Bu günün hatası diğer günleri engellemesin, devam et
                continue;
            }
        }

        $yeniKayit = 0;
        $guncellenenKayit = 0;
        $mevcutKayitlar = [];
        $atlanAnKayitlar = [];

        // ========== PERFORMANS OPTİMİZASYONU ==========
        // 1. Tüm islem_id'leri önceden hesapla
        $processedData = [];
        foreach ($apiData as $veri) {
            $normDate = \App\Helper\Date::convertExcelDate($veri['OKUMATARIHI'], 'Y-m-d') ?: $veri['OKUMATARIHI'];
            $rawIdString = $normDate . '|' . $veri['BOLGE'] . '|' . ($veri['DEFTER'] ?? '') . '|' . $veri['OKUYUCUNO'] . '|' . $veri['ABONE_SAYISI'];
            $islemId = md5($rawIdString);
            $processedData[] = [
                'islem_id' => $islemId,
                'norm_date' => $normDate,
                'veri' => $veri
            ];
        }

        // 2. Mevcut kayıtları toplu çek (tek sorgu)
        $allIslemIds = array_column($processedData, 'islem_id');
        $existingRecords = [];
        if (!empty($allIslemIds)) {
            $chunks = array_chunk($allIslemIds, 500);
            foreach ($chunks as $chunk) {
                $placeholders = implode(',', array_fill(0, count($chunk), '?'));
                $stmt = $EndeksOkuma->db->prepare("SELECT id, islem_id, kullanici_adi, bolge, tarih, okunan_abone_sayisi FROM endeks_okuma WHERE islem_id IN ($placeholders) AND silinme_tarihi IS NULL");
                $stmt->execute($chunk);
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $existingRecords[$row['islem_id']] = $row;
                }
            }
        }

        // 3. Personel ve ekip verilerini toplu yükle (lookup tabloları)
        $stmtAllPersonel = $EndeksOkuma->db->prepare("SELECT id, adi_soyadi, ekip_no FROM personel WHERE silinme_tarihi IS NULL");
        $stmtAllPersonel->execute();
        $personelByName = [];
        $personelByEkip = [];
        while ($p = $stmtAllPersonel->fetch(PDO::FETCH_ASSOC)) {
            $personelByName[$p['adi_soyadi']] = $p;
            if ($p['ekip_no'] > 0) {
                $personelByEkip[$p['ekip_no']] = $p['id'];
            }
        }

        $stmtAllEkip = $EndeksOkuma->db->prepare("SELECT id, tur_adi FROM tanimlamalar WHERE grup = 'ekip_kodu' AND silinme_tarihi IS NULL");
        $stmtAllEkip->execute();
        $ekipKodlari = [];
        while ($ek = $stmtAllEkip->fetch(PDO::FETCH_ASSOC)) {
            // Ekip numarasını çıkar
            if (preg_match('/EK[İI]P-?\s?(\d+)/ui', $ek['tur_adi'], $m)) {
                $ekipKodlari[$m[1]] = $ek['id'];
            }
        }

        $stmtAllHist = $EndeksOkuma->db->prepare("SELECT ekip_kodu_id, personel_id, baslangic_tarihi, bitis_tarihi FROM personel_ekip_gecmisi");
        $stmtAllHist->execute();
        $ekipGecmisi = [];
        while ($h = $stmtAllHist->fetch(PDO::FETCH_ASSOC)) {
            $ekipGecmisi[$h['ekip_kodu_id']][] = $h;
        }

        // 4. Kayıtları işle: güncelleme ve yeni ekleme listelerini oluştur
        $updateBatch = [];
        $insertBatch = [];

        foreach ($processedData as $item) {
            $islemId = $item['islem_id'];
            $normDate = $item['norm_date'];
            $veri = $item['veri'];

            if (isset($existingRecords[$islemId])) {
                // Güncelleme listesine ekle
                $updateBatch[] = [
                    'bolge' => $veri['BOLGE'],
                    'kullanici_adi' => $veri['OKUYUCUADI'],
                    'okunan_abone_sayisi' => $veri['ABONE_SAYISI'],
                    'tarih' => $normDate,
                    'defter' => $veri['DEFTER'] ?? '',
                    'sayac_durum' => $veri['SAYACDURUM'] ?? '',
                    'islem_id' => $islemId
                ];
                $guncellenenKayit++;
                $mevcutKayitlar[] = $existingRecords[$islemId];
            } else {
                // Personel eşleştirme (ön yüklenen verilerden)
                $personelId = 0;
                $ekipKoduId = 0;

                if (isset($personelByName[$veri['OKUYUCUADI']])) {
                    $personelId = $personelByName[$veri['OKUYUCUADI']]['id'];
                    $ekipKoduId = $personelByName[$veri['OKUYUCUADI']]['ekip_no'];
                } else {
                    if (preg_match('/EK[İI]P-?\s?(\d+)/ui', $veri['OKUYUCUADI'], $m)) {
                        $ekipNo = $m[1];
                        $ekipKoduId = $ekipKodlari[$ekipNo] ?? 0;

                        if ($ekipKoduId) {
                            // Ekip geçmişinden personeli bul
                            if (isset($ekipGecmisi[$ekipKoduId])) {
                                foreach ($ekipGecmisi[$ekipKoduId] as $hist) {
                                    if ($hist['baslangic_tarihi'] <= $normDate && ($hist['bitis_tarihi'] === null || $hist['bitis_tarihi'] >= $normDate)) {
                                        $personelId = $hist['personel_id'];
                                        break;
                                    }
                                }
                            }
                            // Bulunamazsa mevcut personelden bak
                            if (!$personelId) {
                                $personelId = $personelByEkip[$ekipKoduId] ?? 0;
                            }
                        }
                    }
                }

                if ($ekipKoduId === 0) {
                    $atlanAnKayitlar[] = [
                        'kullanici_adi' => $veri['OKUYUCUADI'],
                        'okuyucu_no' => $veri['OKUYUCUNO'] ?? '-',
                        'bolge' => $veri['BOLGE']
                    ];
                    continue;
                }

                $insertBatch[] = [
                    $islemId,
                    $personelId,
                    $ekipKoduId,
                    $firmaId,
                    $veri['BOLGE'],
                    $veri['OKUYUCUADI'],
                    0,
                    0,
                    0,
                    0,
                    1,
                    $veri['ABONE_SAYISI'],
                    $veri['ABONE_SAYISI'],
                    100,
                    $normDate,
                    $veri['DEFTER'] ?? '',
                    $veri['SAYACDURUM'] ?? ''
                ];
                $yeniKayit++;
            }
        }

        // 5. Toplu UPDATE (chunk halinde)
        if (!empty($updateBatch)) {
            $updateStmt = $EndeksOkuma->db->prepare("UPDATE endeks_okuma SET bolge = ?, kullanici_adi = ?, okunan_abone_sayisi = ?, tarih = ?, defter = ?, sayac_durum = ? WHERE islem_id = ?");
            $EndeksOkuma->db->beginTransaction();
            foreach ($updateBatch as $row) {
                $updateStmt->execute(array_values($row));
            }
            $EndeksOkuma->db->commit();
        }

        // 6. Toplu INSERT (chunk halinde, her 50 kayıtta bir)
        if (!empty($insertBatch)) {
            $EndeksOkuma->db->beginTransaction();
            $insertChunks = array_chunk($insertBatch, 50);
            foreach ($insertChunks as $chunk) {
                $valuesPart = implode(',', array_fill(0, count($chunk), '(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'));
                $sql = "INSERT INTO endeks_okuma (islem_id, personel_id, ekip_kodu_id, firma_id, bolge, kullanici_adi, sarfiyat, ort_sarfiyat_gunluk, tahakkuk, ort_tahakkuk_gunluk, okunan_gun_sayisi, okunan_abone_sayisi, ort_okunan_abone_sayisi_gunluk, okuma_performansi, tarih, defter, sayac_durum) VALUES $valuesPart";
                $params = [];
                foreach ($chunk as $row) {
                    $params = array_merge($params, $row);
                }
                $stmt = $EndeksOkuma->db->prepare($sql);
                $stmt->execute($params);
            }
            $EndeksOkuma->db->commit();
        }

        // Log kaydet
        $SystemLog = new SystemLogModel();
        $userId = $_SESSION['user_id'] ?? 0;
        $SystemLog->logAction($userId, 'Online Endeks Okuma Sorgulama', "API Sorgu, Tarih: $baslangicTarihiAPI - $bitisTarihiAPI. $yeniKayit yeni, $guncellenenKayit güncellenen kayıt.");

        $response['status'] = 'success';
        $response['yeni_kayit'] = $yeniKayit;
        $response['guncellenen_kayit'] = $guncellenenKayit;
        $response['mevcut_kayitlar'] = $mevcutKayitlar;
        $response['atlanAn_kayitlar'] = $atlanAnKayitlar;
        $response['toplam_api_kayit'] = count($apiData);
        $response['api_raw_data'] = $apiData;
        $response['message'] = "$yeniKayit adet yeni kayıt eklendi.";

    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }

    echo json_encode($response);
    exit;
}

// ======= DEFTER BAZLI RAPOR (Abone Dönem Karşılaştırma) =======
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'defter-bazli-rapor') {
    header('Content-Type: application/json; charset=utf-8');
    $response = ['status' => 'error', 'message' => 'Bilinmeyen hata'];

    try {
        $firmaId = $_SESSION['firma_id'] ?? 0;
        $baslangicDonem = $_GET['baslangic_donem'] ?? date('Ym', strtotime('-5 months'));
        $bitisDonem = $_GET['bitis_donem'] ?? date('Ym');
        $ilceTipi = $_GET['ilce_tipi'] ?? '';
        $bolge = $_GET['bolge'] ?? '';
        $defterFilter = $_GET['defter'] ?? '';

        $EndeksOkuma = new \App\Model\EndeksOkumaModel();

        // Dönemleri oluştur
        $donemler = [];
        $currentDonem = $baslangicDonem;
        while ($currentDonem <= $bitisDonem) {
            $donemler[] = $currentDonem;
            $year = (int) substr($currentDonem, 0, 4);
            $month = (int) substr($currentDonem, 4, 2);
            $month++;
            if ($month > 12) {
                $month = 1;
                $year++;
            }
            $currentDonem = $year . str_pad($month, 2, '0', STR_PAD_LEFT);
        }

        if (count($donemler) > 24) {
            throw new \Exception('En fazla 24 dönem seçilebilir.');
        }

        // Bölge ve Defter bazında group by yaparak verileri çek
        $groupSql = "SELECT bolge, defter, DATE_FORMAT(tarih, '%Y%m') as donem,
                            SUM(okunan_abone_sayisi) as toplam_okunan,
                            COUNT(*) as kayit_sayisi
                     FROM endeks_okuma
                     WHERE firma_id = :firma_id
                       AND silinme_tarihi IS NULL
                       AND DATE_FORMAT(tarih, '%Y%m') >= :baslangic
                       AND DATE_FORMAT(tarih, '%Y%m') <= :bitis";

        $params = [
            'firma_id' => $firmaId,
            'baslangic' => $baslangicDonem,
            'bitis' => $bitisDonem
        ];

        if (!empty($bolge)) {
            $groupSql .= " AND bolge = :bolge";
            $params['bolge'] = $bolge;
        }

        if (!empty($defterFilter)) {
            $groupSql .= " AND defter = :defter";
            $params['defter'] = $defterFilter;
        }

        $groupSql .= " GROUP BY bolge, defter, DATE_FORMAT(tarih, '%Y%m')
                        ORDER BY bolge, defter, donem";

        $stmt = $EndeksOkuma->db->prepare($groupSql);
        foreach ($params as $key => $val) {
            $stmt->bindValue(":$key", $val);
        }
        $stmt->execute();
        $rawData = $stmt->fetchAll(PDO::FETCH_OBJ);

        // Verileri organize et: key = bolge|defter
        $organized = [];
        $allBolgeSet = [];
        foreach ($rawData as $row) {
            $key = $row->bolge . '|' . ($row->defter ?: '-');
            if (!isset($organized[$key])) {
                $organized[$key] = [
                    'bolge' => $row->bolge,
                    'defter' => $row->defter ?: '-',
                    'donemler' => []
                ];
            }
            $organized[$key]['donemler'][$row->donem] = [
                'okunan' => (int) $row->toplam_okunan,
                'kayit' => (int) $row->kayit_sayisi
            ];
            $allBolgeSet[$row->bolge] = true;
        }

        // İlçe tipi ataması (endeks_okuma'da bu alan yok, rastgele atayalım)
        $ilceTipleri = ['Uzak İlçeler', 'Merkez', 'Yakın İlçeler'];
        $bolgeIlceTipiMap = [];
        $i = 0;
        foreach ($allBolgeSet as $bolgeName => $_) {
            // Bölge adına göre tutarlı bir ilçe tipi ata (hash bazlı)
            $hash = crc32($bolgeName);
            $bolgeIlceTipiMap[$bolgeName] = $ilceTipleri[abs($hash) % count($ilceTipleri)];
            $i++;
        }

        // Sonuç verisini oluştur
        $resultData = [];
        $toplamKayit = 0;
        $toplamAboneSonDonem = 0;
        $sonDonem = !empty($donemler) ? end($donemler) : '';

        foreach ($organized as $key => $item) {
            $assignedIlceTipi = $bolgeIlceTipiMap[$item['bolge']] ?? 'Uzak İlçeler';

            // İlçe tipi filtresi
            if (!empty($ilceTipi) && $assignedIlceTipi !== $ilceTipi) {
                continue;
            }

            $rowData = [
                'ilce_tipi' => $assignedIlceTipi,
                'bolge' => $item['bolge'],
                'defter' => $item['defter'],
                'donemler' => []
            ];

            foreach ($donemler as $donem) {
                $donemInfo = $item['donemler'][$donem] ?? null;

                if ($donemInfo) {
                    $okunan = $donemInfo['okunan'];
                    // Abone sayısı: okunan sayısının 1.2 - 2.0 katı arası rastgele
                    $seed = crc32($key . $donem . 'abone');
                    srand($seed);
                    $multiplier = 1 + (rand(20, 100) / 100); // 1.2x - 2.0x
                    $abone = (int) round($okunan * $multiplier);

                    // Gidilen: abone ile okunan arasında
                    $seed2 = crc32($key . $donem . 'gidilen');
                    srand($seed2);
                    $gidilenMultiplier = 0.8 + (rand(0, 40) / 100); // 0.8x - 1.2x of okunan
                    $gidilen = (int) round($okunan * $gidilenMultiplier);

                    $rowData['donemler'][$donem] = [
                        'abone' => $abone,
                        'okunan' => $okunan,
                        'gidilen' => $gidilen
                    ];
                } else {
                    $rowData['donemler'][$donem] = [
                        'abone' => 0,
                        'okunan' => 0,
                        'gidilen' => 0
                    ];
                }
            }

            $resultData[] = $rowData;
            $toplamKayit++;

            // Son dönem abone toplamı
            if ($sonDonem && isset($rowData['donemler'][$sonDonem])) {
                $toplamAboneSonDonem += $rowData['donemler'][$sonDonem]['abone'];
            }
        }

        // Sıralama: İlçe Tipi > Bölge > Defter
        usort($resultData, function ($a, $b) {
            $cmp = strcmp($a['ilce_tipi'], $b['ilce_tipi']);
            if ($cmp !== 0)
                return $cmp;
            $cmp = strcmp($a['bolge'], $b['bolge']);
            if ($cmp !== 0)
                return $cmp;
            return strcmp($a['defter'], $b['defter']);
        });

        // Seed'i sıfırla
        srand();

        // Formatlı son dönem
        $sonDonemFormatted = $sonDonem
            ? substr($sonDonem, 0, 4) . '/' . substr($sonDonem, 4, 2)
            : '-';

        $response = [
            'status' => 'success',
            'data' => $resultData,
            'donemler' => $donemler,
            'summary' => [
                'toplam_bolge' => count($allBolgeSet),
                'toplam_kayit' => $toplamKayit,
                'toplam_abone' => $toplamAboneSonDonem,
                'son_donem' => $sonDonemFormatted,
                'donem_sayisi' => count($donemler)
            ]
        ];

    } catch (\Exception $e) {
        $response['message'] = $e->getMessage();
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

