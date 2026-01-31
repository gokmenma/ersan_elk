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

        // Process rows
        for ($i = $headerRowIndex + 1; $i < count($rows); $i++) {
            $row = $rows[$i];

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
                    // 2. Find person assigned to this team ID
                    $stmtPersonel = $Puantaj->db->prepare("SELECT id FROM personel WHERE ekip_no = ? AND silinme_tarihi IS NULL LIMIT 1");
                    $stmtPersonel->execute([$defId]);
                    $personelId = $stmtPersonel->fetchColumn() ?: 0;
                }
            }

            // Skip if personel not found as per user request
            if ($personelId == 0) {
                $skippedCount++;
                continue;
            }

            // Insert
            $firmaId = $_SESSION['firma_id'] ?? 0;
            $stmt = $Puantaj->db->prepare("INSERT INTO yapilan_isler (islem_id, personel_id, firma_id, firma, is_emri_tipi, ekip_kodu, is_emri_sonucu, sonuclanmis, acik_olanlar, tarih) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $result = $stmt->execute([
                $islemId,
                $personelId,
                $firmaId,
                $firma,
                $isEmriTipi,
                $ekip,
                $isEmriSonucu,
                $sonuclanmis,
                $acikOlanlar,
                $uploadDate
            ]);

            if ($result) {
                $insertedCount++;
            }
        }

        $response['status'] = 'success';
        $response['message'] = "İşlem tamamlandı. $insertedCount kayıt eklendi. $skippedCount kayıt (daha önce yüklendiği veya personel eşleşmediği için) atlandı.";

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
        $EndeksOkuma = new \App\Model\EndeksOkumaModel();
        $firmaId = $_SESSION['firma_id'] ?? 0;

        foreach ($rows as $data) {
            $bolge = $data['bolge'];
            $kullanici_adi = $data['kullanici_adi'];
            $teamNo = $data['team_no'] ?? 0;

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
                    // 2. Find person assigned to this team ID
                    $stmtPersonel = $EndeksOkuma->db->prepare("SELECT id FROM personel WHERE ekip_no = ? AND silinme_tarihi IS NULL LIMIT 1");
                    $stmtPersonel->execute([$defId]);
                    $personelId = $stmtPersonel->fetchColumn() ?: 0;
                }
            }

            // Skip if personel not found as per user request
            if ($personelId == 0) {
                $skippedCount++;
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
        $response['message'] = "$insertedCount kayıt başarıyla yüklendi." . ($skippedCount > 0 ? " ($skippedCount kayıt personel eşleşmediği için atlandı)" : "");

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
                continue;
            }

            $stmt = $Puantaj->db->prepare("INSERT INTO kacak_kontrol (firma_id, personel_ids, tarih, ekip_adi, sayi, aciklama, islem_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $result = $stmt->execute([$firmaId, $personelIdsStr, $uploadDate, $ekipStr, $sayi, $aciklama, $islemId]);
            if ($result)
                $insertedCount++;
        }

        $response['status'] = 'success';
        $message = "$insertedCount kayıt eklendi. $skippedCount kayıt atlandı.";

        // Eşleşmeyen satırlar varsa detaylı bildir
        if (!empty($unmatchedRows)) {
            $message .= "\n\n⚠️ Eşleşmeyen Kayıtlar (" . count($unmatchedRows) . " satır):";
            foreach ($unmatchedRows as $ur) {
                $message .= "\n• Satır " . $ur['satir'] . ": " . $ur['ekip'] . " → Eşleşmeyen: " . implode(', ', $ur['eslesmeyen']);
            }
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

