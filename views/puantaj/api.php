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

// Set header to JSON
// header('Content-Type: application/json');

$Puantaj = new PuantajModel();
$Tanimlamalar = new TanimlamalarModel();

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
            if (empty($firma) && empty($ekip)) {
                continue;
            }

            // Skip rows that look like totals (e.g. Firma is empty but stats are there, or starts with 'Toplam')
            if (empty($firma) || stripos($firma, 'Toplam') !== false) {
                continue;
            }

            // Generate Unique ID
            // Using MD5 of key fields + Date to identify this specific record
            // The user said: "islem_id alanı da eklenecek ve excelden veri yüklenirken eğer islem_kodu daha önce yüklendiyse onu atlaycak"
            // Since we don't have an ID in Excel, we construct one.
            $rawString = $uploadDate . '|' . $firma . '|' . $isEmriTipi . '|' . $ekip . '|' . $isEmriSonucu;
            $islemId = md5($rawString);

            // Check if exists
            $exists = $Puantaj->db->prepare("SELECT COUNT(*) FROM yapilan_isler WHERE islem_id = ?");
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
                $stmtDef = $Puantaj->db->prepare("SELECT id FROM tanimlamalar WHERE (tur_adi = ? OR tur_adi LIKE ?) AND silinme_tarihi IS NULL LIMIT 1");
                $stmtDef->execute([$ekip, $normalizedEkip]);
                $defId = $stmtDef->fetchColumn();

                if ($defId) {
                    // 2. Find person assigned to this team ID who was ACTIVE on the upload date
                    // - ekip_no matches the definition ID
                    // - aktif_mi = 1 (active)
                    // - ise_giris_tarihi <= uploadDate (started before or on the date)
                    // - isten_cikis_tarihi IS NULL OR isten_cikis_tarihi >= uploadDate (hasn't left yet or left after the date)
                    $stmtPersonel = $Puantaj->db->prepare("
                        SELECT id FROM personel 
                        WHERE ekip_no = ? 
                        AND silinme_tarihi IS NULL
                        AND aktif_mi = 1
                        AND (ise_giris_tarihi IS NULL OR ise_giris_tarihi <= ?)
                        AND (isten_cikis_tarihi IS NULL OR isten_cikis_tarihi = '0000-00-00' OR isten_cikis_tarihi >= ?)
                        LIMIT 1
                    ");
                    $stmtPersonel->execute([$defId, $uploadDate, $uploadDate]);
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
            //$isEmriSonucuId = 0;
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
            } else {
                $isEmriSonucuId = $isExistingTur->id;
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
                $stmtDef = $EndeksOkuma->db->prepare("SELECT id FROM tanimlamalar WHERE (tur_adi LIKE ? OR tur_adi LIKE ?) AND silinme_tarihi IS NULL LIMIT 1");
                $stmtDef->execute(["%EKİP-$teamNo", "%EKIP-$teamNo"]);
                $defId = $stmtDef->fetchColumn();

                if ($defId) {
                    // 2. Find person assigned to this team ID who was ACTIVE on the upload date
                    // - ekip_no matches the definition ID
                    // - aktif_mi = 1 (active)
                    // - ise_giris_tarihi <= uploadDate (started before or on the date)
                    // - isten_cikis_tarihi IS NULL OR isten_cikis_tarihi >= uploadDate (hasn't left yet or left after the date)
                    $stmtPersonel = $EndeksOkuma->db->prepare("
                        SELECT id FROM personel 
                        WHERE ekip_no = ? 
                        AND silinme_tarihi IS NULL
                        AND aktif_mi = 1
                        AND (ise_giris_tarihi IS NULL OR ise_giris_tarihi <= ?)
                        AND (isten_cikis_tarihi IS NULL OR isten_cikis_tarihi = '0000-00-00' OR isten_cikis_tarihi >= ?)
                        LIMIT 1
                    ");
                    $stmtPersonel->execute([$defId, $uploadDate, $uploadDate]);
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

            $stmt = $EndeksOkuma->db->prepare("INSERT INTO endeks_okuma (personel_id, firma_id, bolge, kullanici_adi, sarfiyat, ort_sarfiyat_gunluk, tahakkuk, ort_tahakkuk_gunluk, okunan_gun_sayisi, okunan_abone_sayisi, ort_okunan_abone_sayisi_gunluk, okuma_performansi, tarih) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $result = $stmt->execute([$personelId, $firmaId, $bolge, $kullanici_adi, $sarfiyat, $ort_sarfiyat, $tahakkuk, $ort_tahakkuk, $okunan_gun, $okunan_abone, $ort_okunan_abone, $performans, $uploadDate]);
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

            $exists = $Puantaj->db->prepare("SELECT COUNT(*) FROM kacak_kontrol WHERE islem_id = ?");
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'kacak-kaydet') {
    $id = $_POST['id'] ?? 0;
    $tarih = $_POST['tarih'] ?? date('Y-m-d');
    $personelIdsArr = $_POST['kacak_personel_ids'] ?? [];
    $sayi = $_POST['sayi'] ?? 0;
    $aciklama = $_POST['aciklama'] ?? '';
    $firmaId = $_SESSION['firma_id'] ?? 0;

    $dbTarih = \App\Helper\Date::convertExcelDate($tarih, 'Y-m-d') ?: $tarih;

    // personel_ids'i virgülle ayrılmış string yap
    $personelIdsStr = is_array($personelIdsArr) ? implode(',', $personelIdsArr) : $personelIdsArr;

    // Seçilen personellerin isimlerini ekip_adi olarak oluştur
    $ekipAdi = '';
    if (!empty($personelIdsArr) && is_array($personelIdsArr)) {
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
        // Check if a record with the same tarih and personel_ids already exists
        $checkStmt = $Puantaj->db->prepare("SELECT id FROM kacak_kontrol WHERE firma_id = ? AND tarih = ? AND personel_ids = ? AND silinme_tarihi IS NULL");
        $checkStmt->execute([$firmaId, $dbTarih, $personelIdsStr]);
        $existingRecord = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($existingRecord) {
            // Update existing record
            $stmt = $Puantaj->db->prepare("UPDATE kacak_kontrol SET sayi = ?, aciklama = ? WHERE id = ?");
            $result = $stmt->execute([$sayi, $aciklama, $existingRecord['id']]);
        } else {
            // Insert new record
            $islemId = md5($dbTarih . '|' . $personelIdsStr . '|' . $sayi . '|' . $aciklama);
            $stmt = $Puantaj->db->prepare("INSERT INTO kacak_kontrol (firma_id, personel_ids, tarih, ekip_adi, sayi, aciklama, islem_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $result = $stmt->execute([$firmaId, $personelIdsStr, $dbTarih, $ekipAdi, $sayi, $aciklama, $islemId]);
        }
    }
    echo json_encode(['status' => $result ? 'success' : 'error']);
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
            'bolge' => $record->bolge,
            'personel_adi' => $record->personel_adi ?: '<span class="text-muted">' . htmlspecialchars($record->kullanici_adi) . '</span>',
            'sarfiyat' => number_format($record->sarfiyat, 2, ',', '.'),
            'ort_sarfiyat_gunluk' => number_format($record->ort_sarfiyat_gunluk, 2, ',', '.'),
            'tahakkuk' => number_format($record->tahakkuk, 2, ',', '.'),
            'ort_tahakkuk_gunluk' => number_format($record->ort_tahakkuk_gunluk, 2, ',', '.'),
            'okunan_gun_sayisi' => $record->okunan_gun_sayisi,
            'okunan_abone_sayisi' => $record->okunan_abone_sayisi,
            'ort_okunan_abone_sayisi_gunluk' => number_format($record->ort_okunan_abone_sayisi_gunluk, 2, ',', '.'),
            'okuma_performansi' => '%' . number_format($record->okuma_performansi, 2, ',', '.'),
            'tarih' => \App\Helper\Date::dmY($record->tarih)
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
            'is_emri_tipi' => $record->is_emri_tipi ?? '',
            'personel_adi' => $record->personel_adi ?: '<span class="text-muted">' . htmlspecialchars($record->ekip_kodu ?? '') . '</span>',
            'is_emri_sonucu' => $record->is_emri_sonucu ?? '',
            'sonuclanmis' => $record->sonuclanmis ?? 0,
            'acik_olanlar' => $record->acik_olanlar ?? 0
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
            </tr>
        <?php endforeach;
    } elseif ($tab === 'kacak_kontrol') {
        // personel_ids artık virgülle ayrılmış ID'ler içerdiği için doğrudan ekip_adi gösteriliyor
        $sql = "SELECT k.* FROM kacak_kontrol k WHERE k.tarih BETWEEN ? AND ? AND k.silinme_tarihi IS NULL";
        $params = [$dbStartDate, $dbEndDate];

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
        $Puantaj = new PuantajModel();
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

        // TODO: API hazır olduğunda burası API çağrısı yapacak
        // Şimdilik test verileri döndürüyoruz
        $testVeriler = [
            [
                'islem_id' => 'API_' . uniqid() . '_1',
                'firma' => 'ER-SAN ELEKTRİK',
                'is_emri_tipi' => 'KESME İŞEMRİ',
                'ekip_kodu' => 'EKİP-' . rand(1, 20),
                'is_emri_sonucu' => 'BAŞARILI KESME',
                'sonuclanmis' => rand(5, 20),
                'acik_olanlar' => rand(0, 5),
                'tarih' => $baslangicTarihi
            ],
            [
                'islem_id' => 'API_' . uniqid() . '_2',
                'firma' => 'ER-SAN ELEKTRİK',
                'is_emri_tipi' => 'KESME İŞEMRİ',
                'ekip_kodu' => 'EKİP-' . rand(1, 20),
                'is_emri_sonucu' => 'AÇMA İŞLEMİ',
                'sonuclanmis' => rand(3, 15),
                'acik_olanlar' => rand(0, 3),
                'tarih' => $baslangicTarihi
            ],
            [
                'islem_id' => 'API_' . uniqid() . '_3',
                'firma' => 'ER-SAN ELEKTRİK',
                'is_emri_tipi' => 'KESME İŞEMRİ',
                'ekip_kodu' => 'EKİP-' . rand(1, 20),
                'is_emri_sonucu' => 'İPTAL EDİLDİ',
                'sonuclanmis' => rand(1, 10),
                'acik_olanlar' => rand(0, 2),
                'tarih' => $bitisTarihi
            ]
        ];

        $yeniKayit = 0;
        $guncellenenKayit = 0;
        $mevcutKayitlar = [];

        foreach ($testVeriler as $veri) {
            // Daha önce aynı islem_id ile kayıt var mı kontrol et
            $checkStmt = $Puantaj->db->prepare("SELECT id, islem_id, ekip_kodu, is_emri_tipi FROM yapilan_isler WHERE islem_id = ?");
            $checkStmt->execute([$veri['islem_id']]);
            $mevcutKayit = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($mevcutKayit) {
                // Mevcut kayıt varsa güncelle
                $updateStmt = $Puantaj->db->prepare("UPDATE yapilan_isler SET sonuclanmis = ?, acik_olanlar = ?, tarih = ? WHERE islem_id = ?");
                $updateStmt->execute([$veri['sonuclanmis'], $veri['acik_olanlar'], $veri['tarih'], $veri['islem_id']]);
                $guncellenenKayit++;
                $mevcutKayitlar[] = $mevcutKayit;
            } else {
                // Personel eşleştirme (ekip kodundan)
                $personelId = 0;
                $ekipNo = 0;
                if (preg_match('/EK[İI]P-?\s?(\d+)/ui', $veri['ekip_kodu'], $m)) {
                    $ekipNo = $m[1];
                }

                if ($ekipNo > 0) {
                    $stmtDef = $Puantaj->db->prepare("SELECT id FROM tanimlamalar WHERE (tur_adi LIKE ? OR tur_adi LIKE ?) AND silinme_tarihi IS NULL LIMIT 1");
                    $stmtDef->execute(["%EKİP-$ekipNo", "%EKIP-$ekipNo"]);
                    $defId = $stmtDef->fetchColumn();

                    if ($defId) {
                        $stmtPersonel = $Puantaj->db->prepare("SELECT id FROM personel WHERE ekip_no = ? AND silinme_tarihi IS NULL LIMIT 1");
                        $stmtPersonel->execute([$defId]);
                        $personelId = $stmtPersonel->fetchColumn() ?: 0;
                    }
                }

                // Yeni kayıt ekle
                $insertStmt = $Puantaj->db->prepare("INSERT INTO yapilan_isler (islem_id, personel_id, firma_id, firma, is_emri_tipi, ekip_kodu, is_emri_sonucu, sonuclanmis, acik_olanlar, tarih) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $insertStmt->execute([
                    $veri['islem_id'],
                    $personelId,
                    $firmaId,
                    $veri['firma'],
                    $veri['is_emri_tipi'],
                    $veri['ekip_kodu'],
                    $veri['is_emri_sonucu'],
                    $veri['sonuclanmis'],
                    $veri['acik_olanlar'],
                    $veri['tarih']
                ]);
                $yeniKayit++;
            }
        }

        // Log kaydet
        $SystemLog = new SystemLogModel();
        $userId = $_SESSION['user_id'] ?? 0;
        $SystemLog->logAction($userId, 'Online Puantaj Sorgulama', "Firma $ilkFirma-$sonFirma, Tarih: $baslangicTarihi - $bitisTarihi. $yeniKayit yeni, $guncellenenKayit güncellenen kayıt.");

        $response['status'] = 'success';
        $response['yeni_kayit'] = $yeniKayit;
        $response['guncellenen_kayit'] = $guncellenenKayit;
        $response['mevcut_kayitlar'] = $mevcutKayitlar;
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

        $baslangicTarihi = \App\Helper\Date::convertExcelDate($baslangicTarihiRaw, 'Y-m-d') ?: date('Y-m-d');
        $bitisTarihi = \App\Helper\Date::convertExcelDate($bitisTarihiRaw, 'Y-m-d') ?: date('Y-m-d');

        $firmaId = $_SESSION['firma_id'] ?? 0;
        $EndeksOkuma = new EndeksOkumaModel();

        // TODO: API hazır olduğunda burası API çağrısı yapacak
        // Şimdilik test verileri döndürüyoruz
        $bolgeler = ['AFŞİN', 'ELBİSTAN', 'GÖKSUN', 'TÜRKOĞLU'];
        $testVeriler = [
            [
                'islem_id' => 'ENDEKS_' . uniqid() . '_1',
                'bolge' => $bolgeler[array_rand($bolgeler)],
                'kullanici_adi' => 'ER-SAN ELEKTRİK EKİP-' . rand(1, 20),
                'sarfiyat' => rand(1000, 5000) + (rand(0, 99) / 100),
                'ort_sarfiyat_gunluk' => rand(100, 500) + (rand(0, 99) / 100),
                'tahakkuk' => rand(10000, 50000) + (rand(0, 99) / 100),
                'ort_tahakkuk_gunluk' => rand(1000, 5000) + (rand(0, 99) / 100),
                'okunan_gun_sayisi' => rand(1, 5),
                'okunan_abone_sayisi' => rand(50, 200),
                'ort_okunan_abone_sayisi_gunluk' => rand(30, 100) + (rand(0, 99) / 100),
                'okuma_performansi' => rand(80, 120) + (rand(0, 99) / 100),
                'tarih' => $baslangicTarihi
            ],
            [
                'islem_id' => 'ENDEKS_' . uniqid() . '_2',
                'bolge' => $bolgeler[array_rand($bolgeler)],
                'kullanici_adi' => 'ER-SAN ELEKTRİK EKİP-' . rand(1, 20),
                'sarfiyat' => rand(1000, 5000) + (rand(0, 99) / 100),
                'ort_sarfiyat_gunluk' => rand(100, 500) + (rand(0, 99) / 100),
                'tahakkuk' => rand(10000, 50000) + (rand(0, 99) / 100),
                'ort_tahakkuk_gunluk' => rand(1000, 5000) + (rand(0, 99) / 100),
                'okunan_gun_sayisi' => rand(1, 5),
                'okunan_abone_sayisi' => rand(50, 200),
                'ort_okunan_abone_sayisi_gunluk' => rand(30, 100) + (rand(0, 99) / 100),
                'okuma_performansi' => rand(80, 120) + (rand(0, 99) / 100),
                'tarih' => $baslangicTarihi
            ],
            [
                'islem_id' => 'ENDEKS_' . uniqid() . '_3',
                'bolge' => $bolgeler[array_rand($bolgeler)],
                'kullanici_adi' => 'ER-SAN ELEKTRİK EKİP-' . rand(1, 20),
                'sarfiyat' => rand(1000, 5000) + (rand(0, 99) / 100),
                'ort_sarfiyat_gunluk' => rand(100, 500) + (rand(0, 99) / 100),
                'tahakkuk' => rand(10000, 50000) + (rand(0, 99) / 100),
                'ort_tahakkuk_gunluk' => rand(1000, 5000) + (rand(0, 99) / 100),
                'okunan_gun_sayisi' => rand(1, 5),
                'okunan_abone_sayisi' => rand(50, 200),
                'ort_okunan_abone_sayisi_gunluk' => rand(30, 100) + (rand(0, 99) / 100),
                'okuma_performansi' => rand(80, 120) + (rand(0, 99) / 100),
                'tarih' => $bitisTarihi
            ]
        ];

        $yeniKayit = 0;
        $guncellenenKayit = 0;
        $mevcutKayitlar = [];

        foreach ($testVeriler as $veri) {
            // Daha önce aynı islem_id ile kayıt var mı kontrol et
            $checkStmt = $EndeksOkuma->db->prepare("SELECT id, islem_id, kullanici_adi, bolge FROM endeks_okuma WHERE islem_id = ?");
            $checkStmt->execute([$veri['islem_id']]);
            $mevcutKayit = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($mevcutKayit) {
                // Mevcut kayıt varsa güncelle
                $updateStmt = $EndeksOkuma->db->prepare("UPDATE endeks_okuma SET sarfiyat = ?, ort_sarfiyat_gunluk = ?, tahakkuk = ?, ort_tahakkuk_gunluk = ?, okunan_gun_sayisi = ?, okunan_abone_sayisi = ?, ort_okunan_abone_sayisi_gunluk = ?, okuma_performansi = ?, tarih = ? WHERE islem_id = ?");
                $updateStmt->execute([
                    $veri['sarfiyat'],
                    $veri['ort_sarfiyat_gunluk'],
                    $veri['tahakkuk'],
                    $veri['ort_tahakkuk_gunluk'],
                    $veri['okunan_gun_sayisi'],
                    $veri['okunan_abone_sayisi'],
                    $veri['ort_okunan_abone_sayisi_gunluk'],
                    $veri['okuma_performansi'],
                    $veri['tarih'],
                    $veri['islem_id']
                ]);
                $guncellenenKayit++;
                $mevcutKayitlar[] = $mevcutKayit;
            } else {
                // Personel eşleştirme (kullanıcı adından ekip numarası çıkar)
                $personelId = 0;
                $ekipNo = 0;
                if (preg_match('/EK[İI]P-?\s?(\d+)/ui', $veri['kullanici_adi'], $m)) {
                    $ekipNo = $m[1];
                }

                if ($ekipNo > 0) {
                    $stmtDef = $EndeksOkuma->db->prepare("SELECT id FROM tanimlamalar WHERE (tur_adi LIKE ? OR tur_adi LIKE ?) AND silinme_tarihi IS NULL LIMIT 1");
                    $stmtDef->execute(["%EKİP-$ekipNo", "%EKIP-$ekipNo"]);
                    $defId = $stmtDef->fetchColumn();

                    if ($defId) {
                        $stmtPersonel = $EndeksOkuma->db->prepare("SELECT id FROM personel WHERE ekip_no = ? AND silinme_tarihi IS NULL LIMIT 1");
                        $stmtPersonel->execute([$defId]);
                        $personelId = $stmtPersonel->fetchColumn() ?: 0;
                    }
                }

                // Yeni kayıt ekle
                $insertStmt = $EndeksOkuma->db->prepare("INSERT INTO endeks_okuma (islem_id, personel_id, firma_id, bolge, kullanici_adi, sarfiyat, ort_sarfiyat_gunluk, tahakkuk, ort_tahakkuk_gunluk, okunan_gun_sayisi, okunan_abone_sayisi, ort_okunan_abone_sayisi_gunluk, okuma_performansi, tarih) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $insertStmt->execute([
                    $veri['islem_id'],
                    $personelId,
                    $firmaId,
                    $veri['bolge'],
                    $veri['kullanici_adi'],
                    $veri['sarfiyat'],
                    $veri['ort_sarfiyat_gunluk'],
                    $veri['tahakkuk'],
                    $veri['ort_tahakkuk_gunluk'],
                    $veri['okunan_gun_sayisi'],
                    $veri['okunan_abone_sayisi'],
                    $veri['ort_okunan_abone_sayisi_gunluk'],
                    $veri['okuma_performansi'],
                    $veri['tarih']
                ]);
                $yeniKayit++;
            }
        }

        // Log kaydet
        $SystemLog = new SystemLogModel();
        $userId = $_SESSION['user_id'] ?? 0;
        $SystemLog->logAction($userId, 'Online Endeks Okuma Sorgulama', "Firma $ilkFirma-$sonFirma, Tarih: $baslangicTarihi - $bitisTarihi. $yeniKayit yeni, $guncellenenKayit güncellenen kayıt.");

        $response['status'] = 'success';
        $response['yeni_kayit'] = $yeniKayit;
        $response['guncellenen_kayit'] = $guncellenenKayit;
        $response['mevcut_kayitlar'] = $mevcutKayitlar;
        $response['message'] = "$yeniKayit adet yeni kayıt eklendi.";

    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }

    echo json_encode($response);
    exit;
}

