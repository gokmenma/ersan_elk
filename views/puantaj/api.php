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
use App\Service\SayacDegisimService;
use App\Model\SayacDegisimModel;

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
        $updatedCount = 0;
        $skippedCount = 0;
        $skippedRows = []; // Atlanan satırların detayları
        $workTypeCache = []; // Aynı yükleme sırasında yeni eklenen türleri takip etmek için cache
        $pendingMovements = []; // Otomatik demirbaş işlemleri için kuyruk
        $eksikZimmetListesi = []; // Zimmet kaydı bulunamayan personeller için liste

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
            // API sorgulaması ile aynı formatta islem_id oluşturuyoruz
            // Format: tarih|ekipKodu|isEmriTipi|isEmriSonucu (firma dahil değil, sıralama API ile aynı)
            $rawString = $uploadDate . '|' . $ekip . '|' . $isEmriTipi . '|' . $isEmriSonucu;
            $islemId = md5($rawString);

            // Check if exists - varsa güncelle (UPSERT mantığı)
            $existsStmt = $Puantaj->db->prepare("SELECT id, sonuclanmis, acik_olanlar FROM yapilan_isler WHERE islem_id = ? AND silinme_tarihi IS NULL");
            $existsStmt->execute([$islemId]);
            $existingRecord = $existsStmt->fetch(PDO::FETCH_ASSOC);

            if ($existingRecord) {
                // Mevcut kayıt varsa sonuclanmis ve acik_olanlar değerlerini güncelle
                $updateStmt = $Puantaj->db->prepare("UPDATE yapilan_isler SET sonuclanmis = ?, acik_olanlar = ? WHERE id = ?");
                $updateStmt->execute([$sonuclanmis, $acikOlanlar, $existingRecord['id']]);
                $updatedCount++;
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

            // Artık personeli bulamasa da (personelId = 0) ekliyoruz. Rapor ekranında 'Personel Eşleşmedi' olarak göstereceğiz.
            if ($personelId == 0) {
                $hatali_neden = ($defId == 0) ? "Sistemde bulunamadı (Eşleşmeyen Ekip eklendi)" : "O tarihte ataması yok (Eşleşmeyen Ekip eklendi)";
                $skippedRows[] = [
                    'satir' => $excelRowNum,
                    'ekip' => $ekip,
                    'neden' => $hatali_neden
                ];
                // continue yapmıyoruz, içeri aktarılması için!
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
                    'is_emri_sonucu_id' => $isEmriSonucuId,
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
                $Zimmet->checkAndProcessAutomaticZimmet($pm['personel_id'], $pm['is_emri_sonucu_id'], $pm['tarih'], $pm['islem_id'], $pm['miktar'], 'zimmet');
            }

            // 2. Aşama: Sonra tüm satırların İADELERİNİ işle
            foreach ($pendingMovements as $pm) {
                $zRes = $Zimmet->checkAndProcessAutomaticZimmet($pm['personel_id'], $pm['is_emri_sonucu_id'], $pm['tarih'], $pm['islem_id'], $pm['miktar'], 'iade');
                if (!empty($zRes['iade'])) {
                    foreach ($zRes['iade'] as $iRes) {
                        if (($iRes['status'] ?? '') === 'error' && ($iRes['type'] ?? '') === 'no_zimmet_found') {
                            $eksikZimmetListesi[] = $iRes['personel_adi'];
                        }
                    }
                }
            }

            // 3. Aşama: Zimmetten düşme işlemlerini yap (kırılma, çalınma vb.)
            foreach ($pendingMovements as $pm) {
                $zRes = $Zimmet->checkAndProcessAutomaticZimmet($pm['personel_id'], $pm['is_emri_sonucu_id'], $pm['tarih'], $pm['islem_id'], $pm['miktar'], 'dus');
                if (!empty($zRes['dus'])) {
                    foreach ($zRes['dus'] as $iRes) {
                        if (($iRes['status'] ?? '') === 'error' && ($iRes['type'] ?? '') === 'no_zimmet_found') {
                            $eksikZimmetListesi[] = $iRes['personel_adi'];
                        }
                    }
                }
            }
        }




        $response['status'] = 'success';
        $response['inserted_count'] = $insertedCount;
        $response['updated_count'] = $updatedCount;
        $response['skipped_count'] = $skippedCount;
        $response['skipped_rows'] = $skippedRows;

        $message = "İşlem tamamlandı. $insertedCount kayıt eklendi.";
        if ($updatedCount > 0) {
            $message .= " $updatedCount kayıt güncellendi.";
        }
        if ($skippedCount > 0) {
            $message .= " $skippedCount kayıt atlandı.";
        }
        if (!empty($eksikZimmetListesi)) {
            $uniqueEksik = array_unique($eksikZimmetListesi);
            $message .= "\n\nUYARI: Aşağıdaki personellerin zimmetinde aparat bulunmadığı için otomatik aparat iadeleri işlenemedi: " . implode(', ', $uniqueEksik);
        }
        $response['message'] = $message;
        $response['eksik_zimmetler'] = array_unique($eksikZimmetListesi);

        // Log Action
        $SystemLog = new SystemLogModel();
        $userId = $_SESSION['user_id'] ?? 0;
        $SystemLog->logAction($userId, 'Puantaj Yükleme', "Excel'den $insertedCount adet puantaj kaydı yüklendi, $updatedCount adet güncellendi.", SystemLogModel::LEVEL_IMPORTANT);

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
            $bolge = $data['bolge'] ?? '';

            // Bölgesi boş veya null olan kayıtları atla
            if (empty(trim((string) $bolge))) {
                continue;
            }

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

            // Artık personeli bulamasa da (personelId = 0) ekliyoruz. Rapor ekranında 'Personel Eşleşmedi' olarak göstereceğiz.
            if ($personelId == 0) {
                $hatali_neden = ($defId == 0) ? "Sistemde bulunamadı (Eşleşmeyen Ekip eklendi)" : "O tarihte ataması yok (Eşleşmeyen Ekip eklendi)";
                $skippedRows[] = [
                    'satir' => $excelRowNum,
                    'ekip' => $kullanici_adi,
                    'neden' => "EKİP: $teamNo - $hatali_neden"
                ];
                // continue yapmıyoruz, içeri aktarılması için!
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
        $SystemLog->logAction($userId, 'Endeks Okuma Yükleme', "$extension dosyasından $insertedCount adet endeks okuma kaydı yüklendi.", SystemLogModel::LEVEL_IMPORTANT);

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
            // Expecting: Ekip Adı, Sayı, Açıklama (Using mb_stripos for Turkish characters)
            if (mb_stripos($rowStr, 'Ekip', 0, 'UTF-8') !== false && (mb_stripos($rowStr, 'Sayı', 0, 'UTF-8') !== false || mb_stripos($rowStr, 'Sayi', 0, 'UTF-8') !== false)) {
                $headerRowIndex = $index;
                foreach ($row as $colIndex => $cellValue) {
                    $cellValue = trim($cellValue);
                    if (mb_stripos($cellValue, 'Ekip', 0, 'UTF-8') !== false)
                        $colMap['ekip'] = $colIndex;
                    elseif (mb_stripos($cellValue, 'Sayı', 0, 'UTF-8') !== false || mb_stripos($cellValue, 'Sayi', 0, 'UTF-8') !== false)
                        $colMap['sayi'] = $colIndex;
                    elseif (mb_stripos($cellValue, 'Açıklama', 0, 'UTF-8') !== false || mb_stripos($cellValue, 'Aciklama', 0, 'UTF-8') !== false)
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

            // Ekip adındaki virgülle veya tire ile ayrılmış isimleri personel tablosunda ara
            $personelIds = [];
            $unmatchedInRow = []; // Bu satırdaki eşleşmeyen isimler
            $isimler = array_map('trim', preg_split('/[,-]/', $ekipStr));

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
        $SystemLog->logAction($userId, 'Kaçak Kontrol Yükleme', "Excel'den $insertedCount adet kaçak kontrol kaydı yüklendi.", SystemLogModel::LEVEL_IMPORTANT);

    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'kacak-sil') {
    $id = $_POST['id'] ?? 0;
    $Puantaj = new PuantajModel();
    $stmt = $Puantaj->db->prepare("UPDATE kacak_kontrol SET silinme_tarihi = NOW() WHERE id = ?");
    $result = $stmt->execute([$id]);
    echo json_encode(['status' => $result ? 'success' : 'error']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'kacak-hucre-sil') {
    $tarih = $_POST['tarih'] ?? '';
    $personelIds = $_POST['personel_ids'] ?? '';
    $ekipAdi = $_POST['ekip_adi'] ?? '';
    $firmaId = $_SESSION['firma_id'] ?? 0;

    if (empty($tarih) || (empty($personelIds) && empty($ekipAdi))) {
        echo json_encode(['status' => 'error', 'message' => 'Eksik parametre.']);
        exit;
    }

    $Puantaj = new PuantajModel();
    // Hem ekip_adi hem de personel_ids üzerinden silme yapalım (Daha güvenli eşleşme için)
    // Rapor hücresi ekip_adi üzerinden gruplandığı için ekip_adi eşleşmesi esastır.
    $stmt = $Puantaj->db->prepare("UPDATE kacak_kontrol SET silinme_tarihi = NOW() WHERE firma_id = ? AND tarih = ? AND (ekip_adi = ? OR personel_ids = ?) AND silinme_tarihi IS NULL");
    $result = $stmt->execute([$firmaId, $tarih, $ekipAdi, $personelIds]);

    echo json_encode(['status' => $result ? 'success' : 'error', 'message' => $result ? 'Başarıyla silindi.' : 'Silme hatası.']);
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

        // Her zaman en güncel isimleri çekerek ekip adını oluştur (Encoding sorunlarını önlemek için)
        if (!empty($personelIdsArr) && is_array($personelIdsArr)) {
            $Personel = new PersonelModel();
            $isimler = [];
            foreach ($personelIdsArr as $pId) {
                $p = $Personel->find($pId);
                if ($p) {
                    $isimler[] = $p->adi_soyadi;
                }
            }
            if (!empty($isimler)) {
                $ekipAdi = implode(', ', $isimler);
            }
        }

        if ($id == 0) {
            // Aynı gün ve aynı personel(ler) için kayıt var mı kontrol et (Hızlı düzenleme için)
            $stmt = $Puantaj->db->prepare("SELECT id FROM kacak_kontrol WHERE firma_id = ? AND tarih = ? AND personel_ids = ? AND silinme_tarihi IS NULL LIMIT 1");
            $stmt->execute([$firmaId, $dbTarih, $personelIdsStr]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                $id = $existing['id'];
            }
        }

        if ($id > 0) {
            // Explicit update or found existing
            $stmt = $Puantaj->db->prepare("UPDATE kacak_kontrol SET tarih = ?, personel_ids = ?, ekip_adi = ?, sayi = ?, aciklama = ? WHERE id = ?");
            $result = $stmt->execute([$dbTarih, $personelIdsStr, $ekipAdi, $sayi, $aciklama, $id]);
        } else {
            // Insert new record
            $islemId = md5($dbTarih . '|' . $personelIdsStr . '|' . $sayi . '|' . $aciklama . '|' . microtime());
            $stmt = $Puantaj->db->prepare("INSERT INTO kacak_kontrol (firma_id, personel_ids, tarih, ekip_adi, sayi, aciklama, islem_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $result = $stmt->execute([$firmaId, $personelIdsStr, $dbTarih, $ekipAdi, $sayi, $aciklama, $islemId]);
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

// Server-side DataTable için Sayaç Değişim verileri
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'sayac-degisim-datatable') {
    header('Content-Type: application/json');

    $startDate = $_GET['start_date'] ?? '';
    $endDate = $_GET['end_date'] ?? '';
    $ekipKodu = $_GET['ekip_kodu'] ?? '';

    $SayacDegisim = new SayacDegisimModel();
    $result = $SayacDegisim->getDataTable($_GET, $startDate, $endDate, $ekipKodu);

    // Veriyi DataTable formatına dönüştür
    $formattedData = [];
    foreach ($result['data'] as $record) {
        $zimmetBadgeClass = '';
        if (!empty($record->takilan_sayacno)) {
            if ($record->zimmet_dusuldu == 1) {
                $zimmetBadgeClass = 'bg-success';
            } else {
                $zimmetBadgeClass = 'bg-danger';
            }
        }

        $takilanSayacNoHtml = $record->takilan_sayacno ?? '-';
        if ($zimmetBadgeClass && $takilanSayacNoHtml != '-') {
            $takilanSayacNoHtml = '<span class="badge ' . $zimmetBadgeClass . '">' . htmlspecialchars($record->takilan_sayacno) . '</span>';
        }

        $formattedData[] = [
            'kayit_tarihi' => $record->kayit_tarihi ? date('d.m.Y H:i', strtotime($record->kayit_tarihi)) : '-',
            'ekip' => $record->ekip ?? '-',
            'personel_adi' => $record->personel_adi ?: '<span class="text-muted">' . htmlspecialchars($record->ekip ?? '') . '</span>',
            'bolge' => $record->bolge ?? '-',
            'isemri_sebep' => $record->isemri_sebep ?? '-',
            'isemri_sonucu' => $record->isemri_sonucu ?? '-',
            'abone_no' => $record->abone_no ?? '-',
            'takilan_sayacno' => $takilanSayacNoHtml,
            'id' => $record->id,
            'zimmet_dusuldu' => $record->zimmet_dusuldu ?? 0
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

// Server-side DataTable için Mühürleme verileri (yapilan_isler'den is_emri_tipi = MÜHÜRLEME olanlar)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'muhurleme-datatable') {
    header('Content-Type: application/json');

    $startDate = $_GET['start_date'] ?? '';
    $endDate = $_GET['end_date'] ?? '';
    $ekipKodu = $_GET['ekip_kodu'] ?? '';

    $Puantaj = new PuantajModel();

    // Mühürleme is_emri_tipi'lerini al
    $Tanimlamalar = new TanimlamalarModel();
    $muhurlemeTypes = $Tanimlamalar->getIsTurleriByRaporTuru('muhurleme');
    $muhurlemeTypeNames = [];
    foreach ($muhurlemeTypes as $mt) {
        $muhurlemeTypeNames[] = $mt->tur_adi;
    }
    // Fallback: Eğer tanımlama yoksa MÜHÜRLEME kelimesini ara
    if (empty($muhurlemeTypeNames)) {
        $muhurlemeTypeNames = ['MÜHÜRLEME'];
    }
    // Tek bir iş tipi olarak birleştir (ilk bulunan)
    $workTypeFilter = !empty($muhurlemeTypeNames) ? $muhurlemeTypeNames[0] : 'MÜHÜRLEME';

    $result = $Puantaj->getDataTable($_GET, $startDate, $endDate, $ekipKodu, $workTypeFilter);

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

// Sayaç Değişim kaydı silme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'sayac-degisim-sil') {
    $id = $_POST['id'] ?? 0;
    $SayacDegisim = new SayacDegisimModel();
    $stmt = $SayacDegisim->db->prepare("UPDATE sayac_degisim SET silinme_tarihi = NOW() WHERE id = ?");
    $result = $stmt->execute([$id]);
    echo json_encode(['status' => $result ? 'success' : 'error']);
    exit;
}

// Online Sayaç Değişim Sorgulama
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'online-sayac-degisim-sorgula') {
    ob_start();
    header('Content-Type: application/json; charset=utf-8');

    $response = ['status' => 'error', 'message' => 'Bilinmeyen hata'];

    try {
        ini_set('memory_limit', '512M');
        $baslangicTarihiRaw = $_POST['baslangic_tarihi'] ?? date('Y-m-d');
        $bitisTarihiRaw = $_POST['bitis_tarihi'] ?? date('Y-m-d');

        $baslangicTarihi = \App\Helper\Date::convertExcelDate($baslangicTarihiRaw, 'Y-m-d') ?: date('Y-m-d');
        $bitisTarihi = \App\Helper\Date::convertExcelDate($bitisTarihiRaw, 'Y-m-d') ?: date('Y-m-d');

        // API dd/mm/yyyy format
        $baslangicTarihiAPI = \App\Helper\Date::convertExcelDate($baslangicTarihiRaw, 'd/m/Y') ?: date('d/m/Y');
        $bitisTarihiAPI = \App\Helper\Date::convertExcelDate($bitisTarihiRaw, 'd/m/Y') ?: date('d/m/Y');

        $firmaId = $_SESSION['firma_id'] ?? 0;
        $Settings = new \App\Model\SettingsModel();

        // ========== CONCURRENCY LOCK ==========
        $lockKey = 'lock_online_sayac_degisim_' . $firmaId;
        $activeLock = $Settings->getSettings($lockKey);
        $currentUserId = $_SESSION['user_id'] ?? 0;

        if (!empty($activeLock)) {
            $lockParts = explode('|', $activeLock);
            $lockTime = strtotime($lockParts[0]);
            $lockUserId = $lockParts[1] ?? 0;

            if ((time() - $lockTime) < 600) {
                if ($lockUserId == $currentUserId) {
                    throw new Exception("Şu anda devam eden bir sayaç değişim sorgulama işleminiz bulunuyor. Lütfen bekleyin.");
                } else {
                    throw new Exception("Şu anda başka bir kullanıcı tarafından sorgulama yapılıyor. Lütfen bekleyin.");
                }
            }
        }

        $Settings->upsertSetting($lockKey, date('Y-m-d H:i:s') . '|' . $currentUserId);
        // =======================================

        $SayacDegisimSvc = new SayacDegisimService();
        $SayacDegisimModel = new SayacDegisimModel();
        $apiData = [];

        // Ekip ve personel lookup
        $stmtAllEkip = $SayacDegisimModel->db->prepare("SELECT id, tur_adi, grup FROM tanimlamalar WHERE grup = 'ekip_kodu' AND silinme_tarihi IS NULL");
        $stmtAllEkip->execute();
        $ekipKodlariByNo = [];
        $ekipKodlariByName = [];
        while ($ek = $stmtAllEkip->fetch(PDO::FETCH_ASSOC)) {
            $name = trim($ek['tur_adi']);
            $ekipKodlariByName[mb_strtolower($name, 'UTF-8')] = $ek['id'];
            $groupName = trim((string) ($ek['grup'] ?? ''));
            if ($groupName !== '') {
                $ekipKodlariByName[mb_strtolower($groupName, 'UTF-8')] = $ek['id'];
            }
            $teamNo = \App\Helper\EkipHelper::extractTeamNo(trim($groupName . ' ' . $name));
            if ($teamNo > 0) {
                $ekipKodlariByNo[$teamNo] = $ek['id'];
            }
        }

        $stmtAllPersonel = $SayacDegisimModel->db->prepare("SELECT id, adi_soyadi, ekip_no FROM personel WHERE silinme_tarihi IS NULL");
        $stmtAllPersonel->execute();
        $personelByName = [];
        $personelByEkip = [];
        while ($p = $stmtAllPersonel->fetch(PDO::FETCH_ASSOC)) {
            $name = trim($p['adi_soyadi']);
            $personelByName[mb_strtolower($name, 'UTF-8')] = $p;
            if (($p['ekip_no'] ?? 0) > 0) {
                $personelByEkip[$p['ekip_no']] = $p['id'];
            }
        }

        $stmtAllHist = $SayacDegisimModel->db->prepare("SELECT ekip_kodu_id, personel_id, baslangic_tarihi, bitis_tarihi FROM personel_ekip_gecmisi WHERE firma_id = ?");
        $stmtAllHist->execute([$firmaId]);
        $ekipGecmisi = [];
        while ($h = $stmtAllHist->fetch(PDO::FETCH_ASSOC)) {
            $ekipGecmisi[$h['ekip_kodu_id']][] = $h;
        }

        // API'den veri çek
        set_time_limit(300);
        $apiResponse = $SayacDegisimSvc->getData($baslangicTarihiAPI, $bitisTarihiAPI, 5000, 0);

        if (!($apiResponse['success'] ?? false)) {
            throw new Exception("API yanıtı başarısız: " . json_encode($apiResponse));
        }

        $apiData = $apiResponse['data']['data'] ?? [];

        // Mevcut kayıtları sil (tarih aralığına göre)
        $SayacDegisimModel->db->beginTransaction();

        $deleteStmt = $SayacDegisimModel->db->prepare("UPDATE sayac_degisim SET silinme_tarihi = NOW() WHERE firma_id = ? AND tarih BETWEEN ? AND ? AND silinme_tarihi IS NULL");
        $deleteStmt->execute([$firmaId, $baslangicTarihi, $bitisTarihi]);
        $silinenKayit = $deleteStmt->rowCount();

        $yeniKayit = 0;
        $atlanAnKayitlar = [];
        $mevcutHatalar = [];

        $insertBatch = [];

        foreach ($apiData as $veri) {
            $isemriSebep = trim($veri['ISEMRI_SEBEP'] ?? '');
            $ekipStr = trim($veri['EKIP'] ?? '');
            $memur = trim($veri['MEMUR'] ?? '');
            $sonuclandiranKullanici = trim($veri['SONUCLANDIRAN_KULLANICI'] ?? '');
            $bolge = trim($veri['BOLGE'] ?? '');
            $kayitTarihiRaw = trim($veri['SONUC_TARIHI'] ?? '');
            $isemriNo = trim($veri['ISEMRI_NO'] ?? '');
            $aboneNo = trim($veri['ABONE_NO'] ?? '');
            $isemriSonucu = trim($veri['ISEMRI_SONUCU'] ?? '');
            $sonucAciklama = $veri['SONUC_ACIKLAMA'] ?? null;
            $takilanSayacNo = trim($veri['TAKILAN_SAYACNO'] ?? '');

            // Kayıt tarihini parse et
            $kayitTarihi = null;
            $tarih = null;
            if (!empty($kayitTarihiRaw)) {
                // Format: dd/mm/yyyy HH:ii:ss
                $dt = DateTime::createFromFormat('d/m/Y H:i:s', $kayitTarihiRaw);
                if ($dt) {
                    $kayitTarihi = $dt->format('Y-m-d H:i:s');
                    $tarih = $dt->format('Y-m-d');
                }
            }
            if (!$tarih) {
                $tarih = $baslangicTarihi; // Fallback
            }

            // Unique ID
            $islemId = md5($isemriNo . '|' . $aboneNo . '|' . $takilanSayacNo . '|' . $ekipStr);

            // Ekip ve Personel bul
            $personelId = 0;
            $defId = 0;
            $ekipKoduStrClean = trim($ekipStr);
            $ekipKoduStrLower = mb_strtolower($ekipKoduStrClean, 'UTF-8');

            if (isset($personelByName[$ekipKoduStrLower])) {
                $personelId = $personelByName[$ekipKoduStrLower]['id'];
                $defId = $personelByName[$ekipKoduStrLower]['ekip_no'];
            } else {
                $teamNo = \App\Helper\EkipHelper::extractTeamNo($ekipKoduStrClean);
                if ($teamNo > 0) {
                    $defId = $ekipKodlariByNo[$teamNo] ?? 0;
                }
                if (!$defId && isset($ekipKodlariByName[$ekipKoduStrLower])) {
                    $defId = $ekipKodlariByName[$ekipKoduStrLower];
                }
                if ($defId > 0) {
                    if (isset($ekipGecmisi[$defId])) {
                        foreach ($ekipGecmisi[$defId] as $hist) {
                            if ($hist['baslangic_tarihi'] <= $tarih && ($hist['bitis_tarihi'] === null || $hist['bitis_tarihi'] >= $tarih)) {
                                $personelId = $hist['personel_id'];
                                break;
                            }
                        }
                    }
                    if (!$personelId) {
                        $personelId = $personelByEkip[$defId] ?? 0;
                    }
                }
            }

            // Eşleşmeyen ekipler
            if ($defId === 0 && !empty($ekipKoduStrClean)) {
                $uniqKey = "EKIP|" . $ekipKoduStrClean;
                if (!isset($mevcutHatalar[$uniqKey])) {
                    $atlanAnKayitlar[] = [
                        'ekip_kodu' => $ekipKoduStrClean,
                        'tarih' => $tarih
                    ];
                    $mevcutHatalar[$uniqKey] = true;
                }
            }

            $zimmetDusuldu = 0;
            // Zimmet işlemi
            if ($personelId > 0 && !empty($takilanSayacNo)) {
                // Daha önce bu islem_id ile iade yapıldı mı?
                $stmtCheck = $SayacDegisimModel->db->prepare("SELECT id FROM demirbas_hareketler WHERE islem_id = ? AND hareket_tipi = 'iade' LIMIT 1");
                $stmtCheck->execute([$islemId]);
                if ($stmtCheck->fetchColumn()) {
                    // Daha önce düşülmüş
                    $zimmetDusuldu = 1;
                } else {
                    // Zimmette aktif olarak var mı?
                    $stmtZimmet = $SayacDegisimModel->db->prepare("
                        SELECT dz.id, d.kategori_id, d.demirbas_adi
                        FROM demirbas_zimmet dz
                        JOIN demirbas d ON d.id = dz.demirbas_id
                        WHERE dz.personel_id = ? 
                        AND d.seri_no = ? 
                        AND dz.silinme_tarihi IS NULL 
                        AND (dz.durum = 'teslim' OR dz.teslim_miktar > (SELECT COALESCE(SUM(miktar), 0) FROM demirbas_hareketler WHERE zimmet_id = dz.id AND hareket_tipi IN ('iade', 'sarf', 'kayip') AND silinme_tarihi IS NULL))
                        LIMIT 1
                    ");
                    $stmtZimmet->execute([$personelId, $takilanSayacNo]);
                    $zimmetRow = $stmtZimmet->fetch(PDO::FETCH_ASSOC);
                    if ($zimmetRow && $zimmetRow['id']) {
                        try {
                            $zimmetId = $zimmetRow['id'];
                            $kategoriId = $zimmetRow['kategori_id'];

                            $ZimmetModel = new \App\Model\DemirbasZimmetModel();

                            // Takılan sayacı Tüketim yap (iadeYap depoya döndürür)
                            $ZimmetModel->tuketimYap($zimmetId, ($kayitTarihi ?: $tarih), 1, "Sayaç değişimi otomatik tüketimi.\nİş Emri No: {$isemriNo}\nAbone No: {$aboneNo}", $islemId, $isemriSonucu, 'otomatik');

                            // Sökülen sayacı Hurda olarak ekle ve zimmetle
                            $yeniHurdaAdi = "Sökülen Hurda / Abone: " . $aboneNo;
                            $sqlHurdaInsert = $SayacDegisimModel->db->prepare("
                                INSERT INTO demirbas 
                                (firma_id, kategori_id, demirbas_adi, seri_no, miktar, kalan_miktar, durum, kayit_yapan, aciklama)
                                VALUES (?, ?, ?, ?, ?, ?, 'hurda', ?, ?)
                            ");
                            $sqlHurdaInsert->execute([
                                $firmaId,
                                $kategoriId, // Aynı kategori (Sayaç)
                                $yeniHurdaAdi,
                                '-', // seri_no bilinmiyor
                                1, // miktar 
                                1, // kalan_miktar
                                $_SESSION['user_id'] ?? null,
                                "Sayaç değişimi sonrası sökülen hurda (İş Emri: {$isemriNo})"
                            ]);
                            $yeniHurdaId = $SayacDegisimModel->db->lastInsertId();

                            // Personele zimmetle
                            $ZimmetModel->zimmetVer([
                                'demirbas_id' => $yeniHurdaId,
                                'personel_id' => $personelId,
                                'teslim_tarihi' => ($kayitTarihi ?: $tarih),
                                'teslim_miktar' => 1,
                                'aciklama' => "Otomatik Hurda Sayaç Zimmeti.\nİş Emri No: {$isemriNo}",
                                'islem_id' => $islemId . "_hurda",
                                'is_emri_sonucu' => $isemriSonucu,
                                'kaynak' => 'otomatik'
                            ]);

                            $zimmetDusuldu = 1;
                        } catch (Exception $e) {
                            $zimmetDusuldu = 0;
                        }
                    }
                }
            }

            $insertBatch[] = [
                $islemId,
                $firmaId,
                $personelId,
                $defId,
                $isemriNo,
                $aboneNo,
                $isemriSebep,
                $ekipStr,
                $memur,
                $sonuclandiranKullanici,
                $bolge,
                $isemriSonucu,
                $sonucAciklama,
                $takilanSayacNo,
                $kayitTarihi,
                $tarih,
                $zimmetDusuldu
            ];
            $yeniKayit++;
        }

        // Toplu kayıt
        if (!empty($insertBatch)) {
            $chunks = array_chunk($insertBatch, 500);
            foreach ($chunks as $chunk) {
                // Placeholder sayısını 17 yapıyoruz (zimmet_dusuldu eklendi)
                $placeholders = implode(',', array_fill(0, count($chunk), '(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'));
                $sql = "INSERT INTO sayac_degisim (islem_id, firma_id, personel_id, ekip_kodu_id, isemri_no, abone_no, isemri_sebep, ekip, memur, sonuclandiran_kullanici, bolge, isemri_sonucu, sonuc_aciklama, takilan_sayacno, kayit_tarihi, tarih, zimmet_dusuldu) 
                        VALUES $placeholders
                        ON DUPLICATE KEY UPDATE 
                            silinme_tarihi = NULL,
                            personel_id = VALUES(personel_id),
                            ekip_kodu_id = VALUES(ekip_kodu_id),
                            isemri_sebep = VALUES(isemri_sebep),
                            ekip = VALUES(ekip),
                            memur = VALUES(memur),
                            sonuclandiran_kullanici = VALUES(sonuclandiran_kullanici),
                            bolge = VALUES(bolge),
                            isemri_sonucu = VALUES(isemri_sonucu),
                            sonuc_aciklama = VALUES(sonuc_aciklama),
                            kayit_tarihi = VALUES(kayit_tarihi),
                            tarih = VALUES(tarih),
                            zimmet_dusuldu = VALUES(zimmet_dusuldu)";
                $stmt = $SayacDegisimModel->db->prepare($sql);
                $flatParams = [];
                foreach ($chunk as $row) {
                    $flatParams = array_merge($flatParams, $row);
                }
                $stmt->execute($flatParams);
            }
        }

        $SayacDegisimModel->db->commit();

        // Log
        $SystemLog = new SystemLogModel();
        $userId = $_SESSION['user_id'] ?? 0;
        $SystemLog->logAction($userId, 'Online Sayaç Değişim Sorgulama', "API Sorgu, Tarih: $baslangicTarihiAPI - $bitisTarihiAPI. $yeniKayit yeni kayıt, $silinenKayit eski kayıt silindi.", SystemLogModel::LEVEL_IMPORTANT);

        $response['status'] = 'success';
        $response['yeni_kayit'] = $yeniKayit;
        $response['silinen_kayit'] = $silinenKayit;
        $response['toplam_api_kayit'] = count($apiData);
        $response['atlanan_kayit'] = 0;
        $response['atlanAn_kayitlar'] = $atlanAnKayitlar;
        $response['message'] = "$yeniKayit kayıt başarıyla güncellendi.";

        unset($apiData);
        unset($insertBatch);

    } catch (Exception $e) {
        if (isset($SayacDegisimModel) && $SayacDegisimModel->db->inTransaction()) {
            $SayacDegisimModel->db->rollBack();
        }
        $response['message'] = $e->getMessage();
    } finally {
        if (isset($Settings) && isset($lockKey)) {
            $Settings->upsertSetting($lockKey, '');
        }
    }

    ob_end_clean();
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
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

        $sql .= " ORDER BY k.tarih DESC, k.id DESC";
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

// Manuel Düşüm Kaydet
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save-manuel-dusum') {
    $personelId = $_POST['personel_id'] ?? 0;
    $ekipKoduId = $_POST['ekip_kodu_id'] ?? 0;
    $dusumValue = (int) ($_POST['dusum_value'] ?? 0);
    $year = $_POST['year'] ?? date('Y');
    $month = $_POST['month'] ?? date('m');
    $tab = $_POST['tab'] ?? '';
    $firmaId = $_SESSION['firma_id'] ?? 0;

    if (!$ekipKoduId) {
        echo json_encode(['status' => 'error', 'message' => 'Ekip bulunamadı.']);
        exit;
    }

    $monthPadded = str_pad($month, 2, '0', STR_PAD_LEFT);
    $tarih = "$year-$monthPadded-01";
    $Puantaj = new \App\Model\PuantajModel();

    if ($tab === 'kacakkontrol') {
        $ekipAdi = $ekipKoduId;
        $islemId = md5("$tarih|$ekipAdi|MANUELDUSUM");

        if ($dusumValue <= 0) {
            $stmt = $Puantaj->db->prepare("UPDATE kacak_kontrol SET silinme_tarihi = NOW() WHERE islem_id = ?");
            $stmt->execute([$islemId]);
        } else {
            $sonuclanmis = -$dusumValue;
            $stmtCheck = $Puantaj->db->prepare("SELECT id FROM kacak_kontrol WHERE islem_id = ?");
            $stmtCheck->execute([$islemId]);
            $existing = $stmtCheck->fetchColumn();

            if ($existing) {
                $stmtUpdate = $Puantaj->db->prepare("UPDATE kacak_kontrol SET sayi = ?, silinme_tarihi = NULL WHERE id = ?");
                $stmtUpdate->execute([$sonuclanmis, $existing]);
            } else {
                $stmtInsert = $Puantaj->db->prepare("INSERT INTO kacak_kontrol (firma_id, personel_ids, ekip_adi, tarih, sayi, aciklama, islem_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmtInsert->execute([$firmaId, '0', $ekipAdi, $tarih, $sonuclanmis, 'Manuel Düşüm', $islemId]);
            }
        }
    } else {
        $Tanimlamalar = new \App\Model\TanimlamalarModel();

        $isEmriTipi = 'Manuel Düşüm';
        $isEmriSonucu = 'Manuel Düşüm';
        $existingTur = $Tanimlamalar->isEmriSonucu($isEmriTipi, $isEmriSonucu);
        $isEmriSonucuId = $existingTur ? $existingTur->id : 0;

        if (!$isEmriSonucuId) {
            $encryptedId = $Tanimlamalar->saveWithAttr([
                'firma_id' => $firmaId,
                'grup' => 'is_turu',
                'tur_adi' => $isEmriTipi,
                'is_emri_sonucu' => $isEmriSonucu,
                'aciklama' => "Kullanıcı tanımlı manuel düşüm"
            ]);
            $isEmriSonucuId = \App\Helper\Security::decrypt($encryptedId);
        }

        $islemId = md5("$tarih|$ekipKoduId|$personelId|MANUELDUSUM");

        if ($dusumValue <= 0) {
            $stmt = $Puantaj->db->prepare("UPDATE yapilan_isler SET silinme_tarihi = NOW() WHERE islem_id = ?");
            $stmt->execute([$islemId]);
        } else {
            $sonuclanmis = -$dusumValue;

            $stmtCheck = $Puantaj->db->prepare("SELECT id FROM yapilan_isler WHERE islem_id = ?");
            $stmtCheck->execute([$islemId]);
            $existing = $stmtCheck->fetchColumn();

            if ($existing) {
                $stmtUpdate = $Puantaj->db->prepare("UPDATE yapilan_isler SET sonuclanmis = ?, silinme_tarihi = NULL WHERE id = ?");
                $stmtUpdate->execute([$sonuclanmis, $existing]);
            } else {
                $stmtEkip = $Puantaj->db->prepare("SELECT tur_adi FROM tanimlamalar WHERE id = ?");
                $stmtEkip->execute([$ekipKoduId]);
                $ekipAdi = $stmtEkip->fetchColumn() ?: 'BİLİNMEYEN EKİP';

                $stmtInsert = $Puantaj->db->prepare("INSERT INTO yapilan_isler (islem_id, personel_id, ekip_kodu_id, firma_id, is_emri_sonucu_id, is_emri_tipi, ekip_kodu, is_emri_sonucu, sonuclanmis, acik_olanlar, tarih) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmtInsert->execute([$islemId, $personelId, $ekipKoduId, $firmaId, $isEmriSonucuId, $isEmriTipi, $ekipAdi, $isEmriSonucu, $sonuclanmis, 0, $tarih]);
            }
        }
    }

    echo json_encode(['status' => 'success']);
    exit;
}

// Rapor Tablosunu Getir
if (isset($_GET["action"]) && $_GET["action"] == "get-report-table") {
    require_once 'rapor-getir.php';
    exit;
}

// Karşılaştırma Raporu Getir
if (isset($_GET["action"]) && $_GET["action"] == "get-comparison-report") {
    require_once 'karsilastirma-getir.php';
    exit;
}

// Online Puantaj (Kesme/Açma İşlemleri) Sorgulama
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'online-puantaj-sorgula') {
    ob_start();
    header('Content-Type: application/json; charset=utf-8');

    // Yalnızca firma_kodu 17 olanlar sorgulayabilir.
    if (($_SESSION["firma_kodu"] ?? 17) != 17) {
        echo json_encode(['status' => 'error', 'message' => 'API sorgulaması şu an sadece Firma Kodu 17 için desteklenmektedir. Diğer firmaların verileri şu an çekilemez.']);
        exit;
    }

    $response = ['status' => 'error', 'message' => 'Bilinmeyen hata'];

    try {
        ini_set('memory_limit', '512M');
        $ilkFirma = $_POST['ilk_firma'] ?? ($_SESSION['firma_kodu'] ?? 17);
        $sonFirma = $_POST['son_firma'] ?? ($_SESSION['firma_kodu'] ?? 17);
        $baslangicTarihiRaw = $_POST['baslangic_tarihi'] ?? date('Y-m-d');
        $bitisTarihiRaw = $_POST['bitis_tarihi'] ?? date('Y-m-d');

        $baslangicTarihi = \App\Helper\Date::convertExcelDate($baslangicTarihiRaw, 'Y-m-d') ?: date('Y-m-d');
        $bitisTarihi = \App\Helper\Date::convertExcelDate($bitisTarihiRaw, 'Y-m-d') ?: date('Y-m-d');

        $firmaId = $_SESSION['firma_id'] ?? 0;
        $Settings = new \App\Model\SettingsModel();

        // ========== CONCURRENCY LOCK (ÇAKIŞMA ÖNLEME) ==========
        $lockKey = 'lock_online_puantaj_sorgula_' . $firmaId;
        $activeLock = $Settings->getSettings($lockKey);
        $currentUserId = $_SESSION['user_id'] ?? 0;

        if (!empty($activeLock)) {
            $lockParts = explode('|', $activeLock);
            $lockTime = strtotime($lockParts[0]);
            $lockUserId = $lockParts[1] ?? 0;

            // Eğer kilit 10 dakikadan eskiyse devam et
            if ((time() - $lockTime) < 600) {
                if ($lockUserId == $currentUserId) {
                    throw new Exception("Şu anda devam eden bir kesme/açma sorgulama işleminiz bulunuyor. Lütfen işlemin bitmesini bekleyin.");
                } else {
                    throw new Exception("Şu anda başka bir kullanıcı tarafından kesme/açma sorgulama işlemi yapılıyor. Lütfen işlemin bitmesini bekleyin.");
                }
            }
        }

        // Kilidi koy (Zaman ve Kullanıcı ID)
        $Settings->upsertSetting($lockKey, date('Y-m-d H:i:s') . '|' . $currentUserId);
        // ========================================================
        $activeTab = $_POST['active_tab'] ?? '';
        $resultsFilter = $_POST['results_filter'] ?? '';

        // Dinamik filtreleme: Eğer active_tab gelmişse ve results_filter boşsa, 
        // tanimlamalar tablosundan o tab'a ait ücretli iş türlerini çekelim.
        if (empty($resultsFilter) && !empty($activeTab)) {
            if (!isset($Tanimlamalar)) {
                $Tanimlamalar = new \App\Model\TanimlamalarModel();
            }
            // sokme_takma için bazen 'sokme' de kullanılıyor olabilir ama getIsTurleriByRaporTuru zaten handle ediyor
            $workTypes = $Tanimlamalar->getIsTurleriByRaporTuru($activeTab);

            // Eğer sokme_takma için sonuç dönmezse 'sokme' olarak da dene
            if (empty($workTypes) && $activeTab === 'sokme_takma') {
                $workTypes = $Tanimlamalar->getIsTurleriByRaporTuru('sokme');
            }

            if (!empty($workTypes)) {
                $filterNames = [];
                foreach ($workTypes as $wt) {
                    $filterNames[] = $wt->is_emri_sonucu;
                }
                $resultsFilter = implode(',', $filterNames);
            }
        }

        // Sayaçları ve dizileri başlat (Hata almamak için)
        $yeniKayit = 0;
        $guncellenenKayit = 0;
        $bosSonucSayisi = 0;
        $atlanAnKayitlar = [];
        $atlanAnListesi = [];
        $mevcutHatalar = []; // Eşleşmeyen ekipleri unique yapmak için
        $bosSonucSayisi = 0;
        $mevcutKayitlar = [];
        $eksikZimmetListesi = [];

        $KesmeAcmaSvc = new KesmeAcmaService();
        $apiData = [];

        $begin = new DateTime($baslangicTarihi);
        $end = new DateTime($bitisTarihi);
        $end->modify('+1 day');

        $interval = new DateInterval('P1D');
        $daterange = new DatePeriod($begin, $interval, $end);

        set_time_limit(600);

        foreach ($daterange as $date) {
            $currentDateAPI = $date->format('d/m/Y');
            $offset = 0;
            $limit = 500;
            $hasMore = true;

            while ($hasMore) {
                $apiResponse = $KesmeAcmaSvc->getData($currentDateAPI, $currentDateAPI, $ilkFirma, $sonFirma, $limit, $offset);
                if (!($apiResponse['success'] ?? false))
                    break;

                $batchData = $apiResponse['data']['data'] ?? [];
                if (empty($batchData)) {
                    $hasMore = false;
                } else {
                    foreach ($batchData as $item) {
                        $item['TARIH'] = $date->format('Y-m-d');
                        $apiData[] = $item;
                    }
                    if (count($batchData) < $limit) {
                        $hasMore = false;
                    } else {
                        $offset += $limit;
                    }
                }
                if ($offset >= 5000)
                    break;
            }
        }

        $Tanimlamalar = new \App\Model\TanimlamalarModel();
        $Puantaj = new \App\Model\PuantajModel();
        // $Zimmet dosyanın başında global olarak tanımlanmış (line 22)

        // 1. Ekip ve Personel lookup verilerini yükle
        $stmtAllEkip = $Puantaj->db->prepare("SELECT id, tur_adi, grup FROM tanimlamalar WHERE grup = 'ekip_kodu' AND silinme_tarihi IS NULL");
        $stmtAllEkip->execute();
        $ekipKodlariByNo = [];
        $ekipKodlariByName = [];
        while ($ek = $stmtAllEkip->fetch(PDO::FETCH_ASSOC)) {
            $name = trim($ek['tur_adi']);
            $ekipKodlariByName[mb_strtolower($name, 'UTF-8')] = $ek['id'];
            $groupName = trim((string) ($ek['grup'] ?? ''));
            if ($groupName !== '') {
                $ekipKodlariByName[mb_strtolower($groupName, 'UTF-8')] = $ek['id'];
            }
            $teamNo = \App\Helper\EkipHelper::extractTeamNo(trim($groupName . ' ' . $name));
            if ($teamNo > 0) {
                $ekipKodlariByNo[$teamNo] = $ek['id'];
            }
        }

        $stmtAllPersonel = $Puantaj->db->prepare("SELECT id, adi_soyadi, ekip_no FROM personel WHERE silinme_tarihi IS NULL");
        $stmtAllPersonel->execute();
        $personelByName = [];
        $personelByEkip = [];
        while ($p = $stmtAllPersonel->fetch(PDO::FETCH_ASSOC)) {
            $name = trim($p['adi_soyadi']);
            $personelByName[mb_strtolower($name, 'UTF-8')] = $p;
            if (($p['ekip_no'] ?? 0) > 0) {
                $personelByEkip[$p['ekip_no']] = $p['id'];
            }
        }

        $stmtAllHist = $Puantaj->db->prepare("SELECT ekip_kodu_id, personel_id, baslangic_tarihi, bitis_tarihi FROM personel_ekip_gecmisi WHERE firma_id = ?");
        $stmtAllHist->execute([$firmaId]);
        $ekipGecmisi = [];
        while ($h = $stmtAllHist->fetch(PDO::FETCH_ASSOC)) {
            $ekipGecmisi[$h['ekip_kodu_id']][] = $h;
        }

        // 2. Mevcut kayıtları temizle (İleride transaction içerisinde yapılacak, SQL'i hazırlıyoruz)
        $filterArray = !empty($resultsFilter) ? array_map('trim', explode(',', $resultsFilter)) : [];
        $deleteSql = "UPDATE yapilan_isler SET silinme_tarihi = NOW() WHERE firma_id = ? AND tarih BETWEEN ? AND ? AND silinme_tarihi IS NULL";
        $deleteParams = [$firmaId, $baslangicTarihi, $bitisTarihi];

        if (!empty($filterArray)) {
            $placeholders = implode(',', array_fill(0, count($filterArray), '?'));
            $deleteSql .= " AND TRIM(is_emri_sonucu) IN ($placeholders)";
            $deleteParams = array_merge($deleteParams, $filterArray);
        }

        // 3. API verilerini işle
        $insertBatch = [];

        $idx = 0;
        foreach ($apiData as $veri) {
            $idx++;
            $isEmriTipi = trim($veri['ISEMRITIPI'] ?? '');
            $ekipKoduStr = trim($veri['EKIP'] ?? '');
            $isEmriSonucu = trim($veri['SONUC'] ?? '');
            $sonuclanmis = $veri['SONUCLANMIS'] ?? 0;
            $acikOlanlar = $veri['ACIK'] ?? 0;
            $tarihRaw = $veri['TARIH'];

            // 1. İş Emri Sonucu / Ücretli iş kontrolü (USER: Sadece ücretli iş türlerini ver)
            if (!empty($filterArray) && !in_array($isEmriSonucu, $filterArray)) {
                $uniqKey = "FILTER|" . $isEmriSonucu;
                if (!isset($mevcutHatalar[$uniqKey])) {
                    $atlanAnListesi[] = "Filtreye Takıldı: $isEmriSonucu (Ekip: $ekipKoduStr)";
                    $mevcutHatalar[$uniqKey] = true;
                }
                continue;
            }

            // 2. İş emri tipi boşsa atla (USER: iş emri türü boş gelenleri eşleşmedi verme)
            if (empty(trim($isEmriTipi)))
                continue;

            // 3. Sonuçlanmamışsa atla
            if ((int) $sonuclanmis === 0) {
                $bosSonucSayisi++;
                continue;
            }

            $normDate = \App\Helper\Date::convertExcelDate($tarihRaw, 'Y-m-d') ?: $tarihRaw;
            // Benzersizlik için döngü sayacını ekliyoruz (Aynı ekip aynı gün aynı işi birden fazla satır yapabilirse çakışmasın)
            // Trim uygulayalım ki boşluk farklarından islem_id değişmesin
            $islemId = md5($normDate . '|' . trim($ekipKoduStr) . '|' . trim($isEmriTipi) . '|' . trim($isEmriSonucu));

            // Ekip ve Personel Bul
            $personelId = 0;
            $defId = 0;
            $ekipNo = 0;
            $ekipKoduStrClean = trim($ekipKoduStr);
            $ekipKoduStrLower = mb_strtolower($ekipKoduStrClean, 'UTF-8');

            // 1. Önce personel ismiyle eşleştir (API'den direkt personel adı geliyorsa)
            if (isset($personelByName[$ekipKoduStrLower])) {
                $personelId = $personelByName[$ekipKoduStrLower]['id'];
                $defId = $personelByName[$ekipKoduStrLower]['ekip_no'];
            } else {
                // 2. Ekip numarasıyla eşleştir (EKİP-XX formatı)
                $ekipNo = \App\Helper\EkipHelper::extractTeamNo($ekipKoduStrClean);
                if ($ekipNo > 0) {
                    $defId = $ekipKodlariByNo[$ekipNo] ?? 0;
                }

                // 3. Eğer hala bulamadıysa, direkt ekip adıyla eşleştir
                if (!$defId && isset($ekipKodlariByName[$ekipKoduStrLower])) {
                    $defId = $ekipKodlariByName[$ekipKoduStrLower];
                }

                if ($defId > 0) {
                    // Geçmişten personel bul
                    if (isset($ekipGecmisi[$defId])) {
                        foreach ($ekipGecmisi[$defId] as $hist) {
                            if ($hist['baslangic_tarihi'] <= $normDate && ($hist['bitis_tarihi'] === null || $hist['bitis_tarihi'] >= $normDate)) {
                                $personelId = $hist['personel_id'];
                                break;
                            }
                        }
                    }
                    // Geçmişte yoksa güncel personeli al
                    if (!$personelId) {
                        $personelId = $personelByEkip[$defId] ?? 0;
                    }
                }
            }

            // Eşleşmeyen ekip toplama (Hata listesi)
            // Not: defId 0 ise ekip sistemde yok demektir.
            if ($defId === 0 && !empty($ekipKoduStrClean)) {
                $uniqKey = "EKIP|" . $ekipKoduStrClean;
                if (!isset($mevcutHatalar[$uniqKey])) {
                    $atlanAnKayitlar[] = [
                        'ekip_kodu' => "Sistemde Ekip Yok (Eklendi): " . $ekipKoduStrClean,
                        'tarih' => date('d.m.Y', strtotime($normDate))
                    ];
                    $mevcutHatalar[$uniqKey] = true;
                }
                // continue YAPMIYORUZ! (artık eşleşmeyeni de yüklüyoruz)
            } elseif ($personelId === 0 && !empty($ekipKoduStrClean)) {
                $uniqKey = "PERS|" . $ekipKoduStrClean . "|" . $normDate;
                if (!isset($mevcutHatalar[$uniqKey])) {
                    $atlanAnKayitlar[] = [
                        'ekip_kodu' => "Ataması Yok (Eklendi): " . $ekipKoduStrClean,
                        'tarih' => date('d.m.Y', strtotime($normDate))
                    ];
                    $mevcutHatalar[$uniqKey] = true;
                }
            }

            // İş Türü ID Bul/Oluştur
            $existingTur = $Tanimlamalar->isEmriSonucu(trim($isEmriTipi), trim($isEmriSonucu));
            $isEmriSonucuId = $existingTur ? $existingTur->id : 0;
            if (!$isEmriSonucuId && (!empty($isEmriTipi) || !empty($isEmriSonucu))) {
                $encryptedId = $Tanimlamalar->saveWithAttr([
                    'firma_id' => $firmaId,
                    'grup' => 'is_turu',
                    'tur_adi' => $isEmriTipi,
                    'is_emri_sonucu' => $isEmriSonucu,
                    'aciklama' => "Online sorgulama"
                ]);
                $isEmriSonucuId = \App\Helper\Security::decrypt($encryptedId);
            }

            $insertBatch[] = [$islemId, $personelId, $defId, $firmaId, $isEmriSonucuId, $isEmriTipi, $ekipKoduStr, $isEmriSonucu, $sonuclanmis, $acikOlanlar, $normDate];
            $yeniKayit++;

            // Demirbaş işlemi (zimmet, iade ve düşme - 3 aşamalı)
            if ($personelId > 0 && $isEmriSonucuId > 0) {
                $zRes1 = $Zimmet->checkAndProcessAutomaticZimmet($personelId, $isEmriSonucuId, $normDate, $islemId, $sonuclanmis, 'zimmet');
                $zRes2 = $Zimmet->checkAndProcessAutomaticZimmet($personelId, $isEmriSonucuId, $normDate, $islemId, $sonuclanmis, 'iade');
                $zRes3 = $Zimmet->checkAndProcessAutomaticZimmet($personelId, $isEmriSonucuId, $normDate, $islemId, $sonuclanmis, 'dus');

                $allRes = [$zRes1, $zRes2, $zRes3];
                foreach ($allRes as $rArray) {
                    if (is_array($rArray)) {
                        foreach (['zimmet', 'iade', 'dus'] as $rKey) {
                            if (!empty($rArray[$rKey]) && is_array($rArray[$rKey])) {
                                foreach ($rArray[$rKey] as $iRes) {
                                    if (($iRes['status'] ?? '') === 'error' && ($iRes['type'] ?? '') === 'no_zimmet_found') {
                                        $eksikZimmetListesi[] = $iRes['personel_adi'];
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        // 4. Toplu Kayıt (Yeni olanlar) ve Temizleme işlemi
        $Puantaj->db->beginTransaction();

        $deleteStmt = $Puantaj->db->prepare($deleteSql);
        $deleteStmt->execute($deleteParams);
        $silinenKayit = $deleteStmt->rowCount();

        if (!empty($insertBatch)) {
            $chunks = array_chunk($insertBatch, 500);
            foreach ($chunks as $chunk) {
                $placeholders = implode(',', array_fill(0, count($chunk), '(?,?,?,?,?,?,?,?,?,?,?)'));
                $sql = "INSERT INTO yapilan_isler (islem_id, personel_id, ekip_kodu_id, firma_id, is_emri_sonucu_id, is_emri_tipi, ekip_kodu, is_emri_sonucu, sonuclanmis, acik_olanlar, tarih) VALUES $placeholders";
                $stmt = $Puantaj->db->prepare($sql);
                $flatParams = [];
                foreach ($chunk as $row) {
                    $flatParams = array_merge($flatParams, $row);
                }
                $stmt->execute($flatParams);
            }
        }
        $Puantaj->db->commit();

        // Response güncellemesi
        $response['silinen_kayit'] = $silinenKayit;
        $response['guncellenen_kayit'] = $guncellenenKayit;
        $response['mevcut_kayitlar'] = [];

        // Log kaydet
        $SystemLog = new SystemLogModel();
        $userId = $_SESSION['user_id'] ?? 0;
        $SystemLog->logAction($userId, 'Online Kesme/Açma Sorgulama', "API Sorgu, Tarih: $baslangicTarihiAPI - $bitisTarihiAPI. $yeniKayit yeni, $guncellenenKayit güncellenen kayıt.", SystemLogModel::LEVEL_IMPORTANT);

        $response['status'] = 'success';
        $response['yeni_kayit'] = $yeniKayit;
        $response['guncellenen_kayit'] = $guncellenenKayit;
        $response['atlan_kayit_bos'] = $bosSonucSayisi;
        $response['mevcut_kayitlar'] = $mevcutKayitlar;
        $response['atlanAn_kayitlar'] = $atlanAnKayitlar;
        $response['toplam_api_kayit'] = count($apiData);
        $response['atlanAn_listesi'] = $atlanAnListesi;
        $response['eksik_zimmetler'] = array_unique($eksikZimmetListesi);
        // Hata ayıklama verisini kaldırıyoruz (Yüzlerce MB JSON oluşmasını engellemek için)
        // $response['api_raw_data'] = $apiData; 
        $response['message'] = "$yeniKayit kayıt başarıyla güncellendi.";
        if ($yeniKayit == 0 && $silinenKayit == 0)
            $response['message'] = "Veriler zaten güncel.";

        // Belleği boşalt
        unset($apiData);
        unset($insertBatch);

    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    } finally {
        // ========== KİLİDİ KALDIR ==========
        if (isset($Settings) && isset($lockKey)) {
            $Settings->upsertSetting($lockKey, '');
        }
        // ===================================
    }

    ob_end_clean();
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    exit;
}

// Online İcmal (Endeks Okuma) Sorgulama
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'online-icmal-sorgula') {
    ob_start();
    header('Content-Type: application/json; charset=utf-8');

    // Yalnızca firma_kodu 17 olanlar sorgulayabilir.
    if (($_SESSION["firma_kodu"] ?? 17) != 17) {
        echo json_encode(['status' => 'error', 'message' => 'API sorgulaması şu an sadece Firma Kodu 17 için desteklenmektedir. Diğer firmaların verileri şu an çekilemez.']);
        exit;
    }

    $response = ['status' => 'error', 'message' => 'Bilinmeyen hata'];

    try {
        ini_set('memory_limit', '512M');
        $yeniKayit = 0;
        $guncellenenKayit = 0;
        $silinenKayit = 0;
        $atlanAnKayitlar = [];
        $mevcutKayitlar = [];
        $bosSonucSayisi = 0;
        $apiData = [];

        $ilkFirma = $_POST['ilk_firma'] ?? ($_SESSION['firma_kodu'] ?? 17);
        $sonFirma = $_POST['son_firma'] ?? ($_SESSION['firma_kodu'] ?? 17);
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
        $Settings = new \App\Model\SettingsModel();

        // ========== CONCURRENCY LOCK (ÇAKIŞMA ÖNLEME) ==========
        $lockKey = 'lock_online_icmal_sorgula_' . $firmaId;
        $activeLock = $Settings->getSettings($lockKey);
        $currentUserId = $_SESSION['user_id'] ?? 0;

        if (!empty($activeLock)) {
            $lockParts = explode('|', $activeLock);
            $lockTime = strtotime($lockParts[0]);
            $lockUserId = $lockParts[1] ?? 0;

            // Eğer kilit 10 dakikadan eskiyse devam et
            if ((time() - $lockTime) < 600) {
                if ($lockUserId == $currentUserId) {
                    throw new Exception("Şu anda devam eden bir sorgulama işleminiz bulunuyor. Lütfen işlemin bitmesini bekleyin.");
                } else {
                    throw new Exception("Şu anda başka bir kullanıcı tarafından sorgulama işlemi yapılıyor. Lütfen işlemin bitmesini bekleyin.");
                }
            }
        }

        // Kilidi koy (Zaman ve Kullanıcı ID)
        $Settings->upsertSetting($lockKey, date('Y-m-d H:i:s') . '|' . $currentUserId);
        // ===================================

        $apiData = [];
        $begin = new DateTime($baslangicTarihiDB);
        $end = new DateTime($bitisTarihiDB);
        $end->modify('+1 day');

        $interval = new DateInterval('P1D');
        $daterange = new DatePeriod($begin, $interval, $end);

        set_time_limit(600);

        foreach ($daterange as $date) {
            $currentDateAPI = $date->format('d/m/Y');
            $offset = 0;
            $limit = 500;
            $hasMore = true;

            while ($hasMore) {
                $apiResponse = $apiService->getData($currentDateAPI, $currentDateAPI, $ilkFirma, $sonFirma, $limit, $offset);
                if (!($apiResponse['success'] ?? false))
                    break;

                $batchData = $apiResponse['data']['data'] ?? [];
                if (empty($batchData)) {
                    $hasMore = false;
                } else {
                    foreach ($batchData as $item) {
                        // Eğer OKUMATARIHI boşsa günün tarihini enjekte et
                        if (!isset($item['OKUMATARIHI']) || empty($item['OKUMATARIHI'])) {
                            $item['OKUMATARIHI'] = $date->format('Y-m-d');
                        }
                        $apiData[] = $item;
                    }
                    if (count($batchData) < $limit) {
                        $hasMore = false;
                    } else {
                        $offset += $limit;
                    }
                }
                if ($offset >= 5000)
                    break;
            }
        }

        // ========== SİL VE YENİDEN YÜKLE YAKLAŞIMI ==========
        // 1. Personel ve ekip verilerini toplu yükle (lookup tabloları)
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

        $stmtAllEkip = $EndeksOkuma->db->prepare("SELECT id, tur_adi, grup FROM tanimlamalar WHERE grup = 'ekip_kodu' AND silinme_tarihi IS NULL");
        $stmtAllEkip->execute();
        $ekipKodlari = [];
        while ($ek = $stmtAllEkip->fetch(PDO::FETCH_ASSOC)) {
            $teamNo = \App\Helper\EkipHelper::extractTeamNo(trim(((string) ($ek['grup'] ?? '')) . ' ' . ((string) ($ek['tur_adi'] ?? ''))));
            if ($teamNo > 0) {
                $ekipKodlari[$teamNo] = $ek['id'];
            }
        }

        $stmtAllHist = $EndeksOkuma->db->prepare("SELECT ekip_kodu_id, personel_id, baslangic_tarihi, bitis_tarihi FROM personel_ekip_gecmisi");
        $stmtAllHist->execute();
        $ekipGecmisi = [];
        while ($h = $stmtAllHist->fetch(PDO::FETCH_ASSOC)) {
            $ekipGecmisi[$h['ekip_kodu_id']][] = $h;
        }

        // 2. Sorgulanan tarih aralığındaki mevcut kayıtları soft-delete et (SQL'i hazırlıyoruz, transaction içinde işlenecek)
        $deleteSql = "UPDATE endeks_okuma SET silinme_tarihi = NOW() WHERE firma_id = ? AND tarih BETWEEN ? AND ? AND silinme_tarihi IS NULL";
        $deleteParams = [$firmaId, $baslangicTarihiDB, $bitisTarihiDB];

        // 3. API verilerini işle ve insert listesi oluştur
        $insertBatch = [];

        foreach ($apiData as $veri) {
            // Bölgesi boş veya null olan kayıtları atla
            if (!isset($veri['BOLGE']) || empty(trim((string) $veri['BOLGE']))) {
                continue;
            }

            $okuyucuAdi = trim($veri['OKUYUCUADI'] ?? '');
            $bolge = trim($veri['BOLGE'] ?? '');
            $defter = trim($veri['DEFTER'] ?? '');
            $okuyucuNo = trim($veri['OKUYUCUNO'] ?? '');
            $sayacDurum = trim($veri['SAYACDURUM'] ?? '');

            $normDate = \App\Helper\Date::convertExcelDate($veri['OKUMATARIHI'], 'Y-m-d') ?: $veri['OKUMATARIHI'];
            $rawIdString = $normDate . '|' . $bolge . '|' . $defter . '|' . $okuyucuNo . '|' . $sayacDurum;
            $islemId = md5($rawIdString);

            // Personel eşleştirme
            $personelId = 0;
            $ekipKoduId = 0;

            if (isset($personelByName[$okuyucuAdi])) {
                $personelId = $personelByName[$okuyucuAdi]['id'];
                $ekipKoduId = $personelByName[$okuyucuAdi]['ekip_no'];
            } else {
                $ekipNo = \App\Helper\EkipHelper::extractTeamNo($veri['OKUYUCUADI'] ?? '');
                if ($ekipNo > 0) {
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
                // Ekip tamamen bulunamazsa ekrana atlanan olarak gönderiyoruz
                $uniqKey = "EKIP|" . $okuyucuAdi;
                if (!isset($mevcutHatalar[$uniqKey])) {
                    $atlanAnKayitlar[] = [
                        'kullanici_adi' => "Sistemde Ekip Yok (Eklendi): " . $okuyucuAdi,
                        'okuyucu_no' => $okuyucuNo ?: '-',
                        'bolge' => $bolge
                    ];
                    $mevcutHatalar[$uniqKey] = true;
                }
            } elseif ($personelId === 0) {
                // Ekip var ama personel atanmamışsa
                $uniqKey = "PERS|" . $okuyucuAdi;
                if (!isset($mevcutHatalar[$uniqKey])) {
                    $atlanAnKayitlar[] = [
                        'kullanici_adi' => "Ataması Yok (Eklendi): " . $okuyucuAdi,
                        'okuyucu_no' => $okuyucuNo ?: '-',
                        'bolge' => $bolge
                    ];
                    $mevcutHatalar[$uniqKey] = true;
                }
            }

            $insertBatch[] = [
                $islemId,
                $personelId,
                $ekipKoduId,
                $firmaId,
                $bolge,
                $okuyucuAdi,
                0,
                0,
                0,
                0,
                1,
                $veri['ABONE_SAYISI'],
                $veri['ABONE_SAYISI'],
                100,
                $normDate,
                $defter,
                $sayacDurum
            ];
            $yeniKayit++;
        }

        // 4. Toplu INSERT (chunk halinde, her 50 kayıtta bir) ve Temizleme işlemi
        $EndeksOkuma->db->beginTransaction();

        $deleteStmt = $EndeksOkuma->db->prepare($deleteSql);
        $deleteStmt->execute($deleteParams);
        $silinenKayit = $deleteStmt->rowCount();

        if (!empty($insertBatch)) {
            $insertChunks = array_chunk($insertBatch, 500);
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
        }
        $EndeksOkuma->db->commit();

        // Log kaydet
        $SystemLog = new SystemLogModel();
        $userId = $_SESSION['user_id'] ?? 0;
        $SystemLog->logAction($userId, 'Online Endeks Okuma Sorgulama', "API Sorgu, Tarih: $baslangicTarihiAPI - $bitisTarihiAPI. $silinenKayit eski kayıt silindi, $yeniKayit yeni kayıt eklendi.", SystemLogModel::LEVEL_IMPORTANT);

        $response['status'] = 'success';
        $response['yeni_kayit'] = $yeniKayit;
        $response['silinen_kayit'] = $silinenKayit;
        $response['atlanAn_kayitlar'] = $atlanAnKayitlar;
        $response['toplam_api_kayit'] = count($apiData);
        $response['message'] = "$yeniKayit kayıt başarıyla güncellendi.";
        if ($yeniKayit == 0 && $silinenKayit == 0)
            $response['message'] = "Veriler zaten güncel.";

        // Belleği boşalt
        unset($apiData);
        unset($insertBatch);

    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    } finally {
        // ========== KİLİDİ KALDIR ==========
        if (isset($Settings) && isset($lockKey)) {
            $Settings->upsertSetting($lockKey, '');
        }
        // ===================================
    }

    ob_end_clean();
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    exit;
}

// ======= DEFTER BAZLI RAPOR (Abone Dönem Karşılaştırma) =======
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'defter-bazli-rapor') {
    header('Content-Type: application/json; charset=utf-8');
    $response = ['status' => 'error', 'message' => 'Bilinmeyen hata'];

    try {
        $firmaId = $_SESSION['firma_id'] ?? 0;
        // Dönemleri belirle
        $donemler = [];

        // 1. Başlangıç - Bitiş Aralığını Ekle
        $baslangicDonem = $_GET['baslangic_donem'] ?? '';
        $bitisDonem = $_GET['bitis_donem'] ?? '';

        if (!empty($baslangicDonem) && !empty($bitisDonem)) {
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
                if (count($donemler) > 48)
                    break; // Güvenlik sınırı
            }
        }

        // 2. Özel Seçilen Dönemleri Ekle
        if (isset($_GET['donemler']) && is_array($_GET['donemler'])) {
            foreach ($_GET['donemler'] as $d) {
                if (!empty($d))
                    $donemler[] = $d;
            }
        }

        // 3. Tekilleştir ve Sırala
        $donemler = array_unique($donemler);
        sort($donemler);

        if (empty($donemler)) {
            $donemler = [date('Ym')];
        }

        $ilceTipi = $_GET['ilce_tipi'] ?? '';
        $bolge = $_GET['bolge'] ?? '';
        $defterFilter = $_GET['defter'] ?? '';

        $EndeksOkuma = new \App\Model\EndeksOkumaModel();

        // Bölge ve Defter bazında group by yaparak verileri çek
        $placeholders = implode(',', array_fill(0, count($donemler), '?'));
        $groupSql = "SELECT bolge, defter, DATE_FORMAT(tarih, '%Y%m') as donem,
                            SUM(okunan_abone_sayisi) as toplam_okunan,
                            COUNT(*) as kayit_sayisi,
                            MAX(tarih) as son_okuma
                     FROM endeks_okuma
                     WHERE firma_id = ?
                       AND silinme_tarihi IS NULL
                       AND DATE_FORMAT(tarih, '%Y%m') IN ($placeholders)";

        $queryParams = [$firmaId];
        $queryParams = array_merge($queryParams, $donemler);

        if (!empty($bolge)) {
            $groupSql .= " AND bolge = ?";
            $queryParams[] = $bolge;
        }

        if (!empty($defterFilter)) {
            $groupSql .= " AND defter = ?";
            $queryParams[] = $defterFilter;
        }

        $groupSql .= " GROUP BY bolge, defter, DATE_FORMAT(tarih, '%Y%m')
                        ORDER BY bolge, defter, donem";

        $stmt = $EndeksOkuma->db->prepare($groupSql);
        $stmt->execute($queryParams);
        $rawData = $stmt->fetchAll(PDO::FETCH_OBJ);

        // 2. tanimlamalar tablosundan defter_kodu bilgilerini al
        $defterTanimStmt = $EndeksOkuma->db->prepare(
            "SELECT tur_adi, defter_bolge, defter_mahalle, defter_abone_sayisi, 
                    baslangic_tarihi, bitis_tarihi
             FROM tanimlamalar
             WHERE firma_id = ? AND grup = 'defter_kodu' AND silinme_tarihi IS NULL
             ORDER BY baslangic_tarihi DESC"
        );
        $defterTanimStmt->execute([$firmaId]);
        $defterTanimRaw = $defterTanimStmt->fetchAll(PDO::FETCH_OBJ);

        $defterTanimListMap = [];
        foreach ($defterTanimRaw as $dt) {
            $code = trim($dt->tur_adi);
            $region = trim($dt->defter_bolge ?: '');
            $key = $region . '|' . $code;
            
            if (!isset($defterTanimListMap[$key])) {
                $defterTanimListMap[$key] = [];
            }
            $defterTanimListMap[$key][] = [
                'mahalle' => $dt->defter_mahalle ?: '',
                'abone_sayisi' => (int) ($dt->defter_abone_sayisi ?: 0),
                'baslangic' => $dt->baslangic_tarihi ?: '1900-01-01',
                'bitis' => $dt->bitis_tarihi ?: '2099-12-31'
            ];
        }

        // Verileri organize et: key = bolge|defter
        $organized = [];
        $allBolgeSet = [];
        foreach ($rawData as $row) {
            $bolgeName = trim($row->bolge);
            $defter = trim($row->defter ?: '-');
            $key = $bolgeName . '|' . $defter;
            
            if (!isset($organized[$key])) {
                $organized[$key] = [
                    'bolge' => $bolgeName,
                    'defter' => $defter,
                    'mahalle' => '', 
                    'abone_sayisi' => 0,
                    'donemler' => []
                ];
            }

            // Bu dönem için aktif olan tanımı bul (Son okuma tarihine göre)
            $activeTanim = null;
            if (isset($defterTanimListMap[$key])) {
                foreach ($defterTanimListMap[$key] as $t) {
                    if ($row->son_okuma >= $t['baslangic'] && $row->son_okuma <= $t['bitis']) {
                        $activeTanim = $t;
                        break;
                    }
                }
                if (!$activeTanim) $activeTanim = $defterTanimListMap[$key][0];
            }

            $currentAbone = $activeTanim ? $activeTanim['abone_sayisi'] : 0;
            if ($activeTanim && empty($organized[$key]['mahalle'])) {
                $organized[$key]['mahalle'] = $activeTanim['mahalle'];
                $organized[$key]['abone_sayisi'] = $activeTanim['abone_sayisi']; // Genel olarak en sonuncuyu veya ilk eşleşeni gösterir
            }

            $organized[$key]['donemler'][$row->donem] = [
                'abone' => $currentAbone,
                'okunan' => (int) $row->toplam_okunan,
                'gidilen' => (int) $row->kayit_sayisi
            ];
            $allBolgeSet[$row->bolge] = true;
        }

        // İlçe tipi ataması (hash bazlı)
        $ilceTipleri = ['Uzak İlçeler', 'Merkez', 'Yakın İlçeler'];
        
        // Sonuç verisini oluştur
        $resultData = [];
        $toplamKayit = 0;
        $toplamAboneSonDonem = 0;
        $sonDonem = !empty($donemler) ? end($donemler) : '';

        foreach ($organized as $key => $item) {
            $bolgeName = $item['bolge'];
            $hash = crc32($bolgeName);
            $assignedIlceTipi = $ilceTipleri[abs($hash) % count($ilceTipleri)];

            // İlçe tipi filtresi
            if (!empty($ilceTipi) && $assignedIlceTipi !== $ilceTipi) {
                continue;
            }

            $rowData = [
                'ilce_tipi' => $assignedIlceTipi,
                'bolge' => $bolgeName,
                'defter' => $item['defter'],
                'mahalle' => $item['mahalle'],
                'abone_sayisi' => $item['abone_sayisi'],
                'donemler' => []
            ];

            foreach ($donemler as $donem) {
                $donemInfo = $item['donemler'][$donem] ?? null;
                if ($donemInfo) {
                    $rowData['donemler'][$donem] = $donemInfo;
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

        // Sıralama: Bölge > İlçe Tipi > Mahalle > Defter
        usort($resultData, function ($a, $b) {
            $cmp = strcmp((string) ($a['bolge'] ?? ''), (string) ($b['bolge'] ?? ''));
            if ($cmp !== 0) return $cmp;
            $cmp = strcmp((string) ($a['ilce_tipi'] ?? ''), (string) ($b['ilce_tipi'] ?? ''));
            if ($cmp !== 0) return $cmp;
            $cmp = strcmp((string) ($a['mahalle'] ?? ''), (string) ($b['mahalle'] ?? ''));
            if ($cmp !== 0) return $cmp;
            return strcmp((string) ($a['defter'] ?? ''), (string) ($b['defter'] ?? ''));
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

// ======= OKUMA GÜN SAYILARI RAPORU =======
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'okuma-gun-sayilari') {
    header('Content-Type: application/json; charset=utf-8');
    $response = ['status' => 'error', 'message' => 'Bilinmeyen hata'];

    try {
        $firmaId = $_SESSION['firma_id'] ?? 0;

        // Dönemleri belirle (defter-bazli-rapor ile aynı mantık)
        $donemler = [];
        $baslangicDonem = $_GET['baslangic_donem'] ?? '';
        $bitisDonem = $_GET['bitis_donem'] ?? '';

        if (!empty($baslangicDonem) && !empty($bitisDonem)) {
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
                if (count($donemler) > 48)
                    break;
            }
        }

        if (isset($_GET['donemler']) && is_array($_GET['donemler'])) {
            foreach ($_GET['donemler'] as $d) {
                if (!empty($d))
                    $donemler[] = $d;
            }
        }

        $donemler = array_unique($donemler);
        sort($donemler);

        if (empty($donemler)) {
            $donemler = [date('Ym')];
        }

        $bolge = $_GET['bolge'] ?? '';
        $defterFilter = $_GET['defter'] ?? '';

        $EndeksOkuma = new \App\Model\EndeksOkumaModel();
        $TanimlamalarModel = new \App\Model\TanimlamalarModel();

        // 1. endeks_okuma'dan bolge + defter + dönem bazlı okuma tarihlerini çek
        $placeholders = implode(',', array_fill(0, count($donemler), '?'));
        $sql = "SELECT bolge, defter, DATE_FORMAT(tarih, '%Y%m') as donem,
                       MAX(tarih) as okuma_tarihi
                FROM endeks_okuma
                WHERE firma_id = ?
                  AND silinme_tarihi IS NULL
                  AND defter IS NOT NULL AND defter != ''
                  AND DATE_FORMAT(tarih, '%Y%m') IN ($placeholders)";

        $queryParams = [$firmaId];
        $queryParams = array_merge($queryParams, $donemler);

        if (!empty($bolge)) {
            $sql .= " AND bolge = ?";
            $queryParams[] = $bolge;
        }

        if (!empty($defterFilter)) {
            $sql .= " AND defter = ?";
            $queryParams[] = $defterFilter;
        }

        $sql .= " GROUP BY bolge, defter, DATE_FORMAT(tarih, '%Y%m')
                   ORDER BY bolge, defter, donem";

        $stmt = $EndeksOkuma->db->prepare($sql);
        $stmt->execute($queryParams);
        $rawData = $stmt->fetchAll(PDO::FETCH_OBJ);

        // 2. tanimlamalar tablosundan defter_kodu bilgilerini al
        $defterTanimStmt = $EndeksOkuma->db->prepare(
            "SELECT tur_adi, defter_bolge, defter_mahalle, defter_abone_sayisi, 
                    baslangic_tarihi, bitis_tarihi
             FROM tanimlamalar
             WHERE firma_id = ? AND grup = 'defter_kodu' AND silinme_tarihi IS NULL
             ORDER BY baslangic_tarihi DESC"
        );
        $defterTanimStmt->execute([$firmaId]);
        $defterTanimRaw = $defterTanimStmt->fetchAll(PDO::FETCH_OBJ);

        // Defter kodu → [tanım listesi] map'i oluştur
        $defterTanimListMap = [];
        foreach ($defterTanimRaw as $dt) {
            $code = trim($dt->tur_adi);
            $region = trim($dt->defter_bolge ?: '');
            $key = $region . '|' . $code;
            
            if (!isset($defterTanimListMap[$key])) {
                $defterTanimListMap[$key] = [];
            }
            $defterTanimListMap[$key][] = [
                'ilce' => $region,
                'mahalle' => $dt->defter_mahalle ?: '',
                'abone_sayisi' => (int) ($dt->defter_abone_sayisi ?: 0),
                'baslangic' => $dt->baslangic_tarihi ?: '1900-01-01',
                'bitis' => $dt->bitis_tarihi ?: '2099-12-31'
            ];
        }

        // 3. Verileri bolge + defter bazlı organize et
        $organized = []; // key: bolge|defter
        foreach ($rawData as $row) {
            $bolgeName = trim($row->bolge);
            $defter = trim($row->defter);
            if (empty($defter))
                continue;

            $key = $bolgeName . '|' . $defter;

            if (!isset($organized[$key])) {
                $organized[$key] = [
                    'bolge' => $bolgeName,
                    'defter' => $defter,
                    'ilce' => $bolgeName, // Varsayılan bölge adı
                    'mahalle' => '',
                    'abone_sayisi' => 0,
                    'donemler' => []
                ];
            }
            
            // Bu okuma tarihi için aktif olan tanımı bul
            $activeTanim = null;
            if (isset($defterTanimListMap[$key])) {
                foreach ($defterTanimListMap[$key] as $t) {
                    if ($row->okuma_tarihi >= $t['baslangic'] && $row->okuma_tarihi <= $t['bitis']) {
                        $activeTanim = $t;
                        break;
                    }
                }
                // Eğer tam eşleşme yoksa ilkini (en günceli) al
                if (!$activeTanim) $activeTanim = $defterTanimListMap[$key][0];
            }

            if ($activeTanim) {
                // $organized[$key]['ilce'] = $activeTanim['ilce']; // Bölge endeks_okuma'dan gelsin
                $organized[$key]['mahalle'] = $activeTanim['mahalle'];
                $organized[$key]['abone_sayisi'] = $activeTanim['abone_sayisi'];
            }

            $organized[$key]['donemler'][$row->donem] = [
                'okuma_tarihi_raw' => $row->okuma_tarihi,
                'okuma_tarihi' => date('d.m.Y', strtotime($row->okuma_tarihi))
            ];
        }

        // İlçe tipi ataması (hash bazlı, Tab 1 ile aynı mantık)
        $ilceTipleri = ['Uzak İlçeler', 'Merkez', 'Yakın İlçeler'];

        // 4. FARK hesapla (ardışık dönemler arasındaki gün farkı)
        $resultData = [];
        foreach ($organized as $key => $item) {
            $bolgeName = $item['bolge'];
            $hash = crc32($bolgeName);
            $assignedIlceTipi = $ilceTipleri[abs($hash) % count($ilceTipleri)];

            $rowData = [
                'ilce_tipi' => $assignedIlceTipi,
                'bolge' => $bolgeName,
                'defter' => $item['defter'],
                'mahalle' => $item['mahalle'],
                'abone_sayisi' => $item['abone_sayisi'],
                'donemler' => []
            ];

            $prevDate = null;
            foreach ($donemler as $donem) {
                $donemInfo = $item['donemler'][$donem] ?? null;
                if ($donemInfo && !empty($donemInfo['okuma_tarihi_raw'])) {
                    $currentDate = new DateTime($donemInfo['okuma_tarihi_raw']);
                    $fark = null;
                    if ($prevDate !== null) {
                        $interval = $prevDate->diff($currentDate);
                        $fark = (int) $interval->days;
                    }

                    $rowData['donemler'][$donem] = [
                        'okuma_tarihi' => $donemInfo['okuma_tarihi'],
                        'okuma_tarihi_raw' => $donemInfo['okuma_tarihi_raw'],
                        'fark' => $fark
                    ];
                    $prevDate = $currentDate;
                } else {
                    $rowData['donemler'][$donem] = [
                        'okuma_tarihi' => '',
                        'okuma_tarihi_raw' => '',
                        'fark' => null
                    ];
                    // prevDate değişmez, boş dönem fark hesabını kesmez
                }
            }

            $resultData[] = $rowData;
        }

        // 5. Bölge > İlçe Tipi > Defter sıralaması
        usort($resultData, function ($a, $b) {
            $cmp = strcmp((string) ($a['bolge'] ?? ''), (string) ($b['bolge'] ?? ''));
            if ($cmp !== 0) return $cmp;
            $cmp = strcmp((string) ($a['ilce_tipi'] ?? ''), (string) ($b['ilce_tipi'] ?? ''));
            if ($cmp !== 0) return $cmp;
            $cmp = strcmp((string) ($a['mahalle'] ?? ''), (string) ($b['mahalle'] ?? ''));
            if ($cmp !== 0) return $cmp;
            return strcmp((string) ($a['defter'] ?? ''), (string) ($b['defter'] ?? ''));
        });

        // Bölge listesini çıkar
        $bolgeSet = [];
        foreach ($resultData as $row) {
            if (!empty($row['bolge'])) {
                $bolgeSet[$row['bolge']] = true;
            }
        }

        $response = [
            'status' => 'success',
            'data' => $resultData,
            'donemler' => $donemler,
            'summary' => [
                'toplam_defter' => count($resultData),
                'toplam_bolge' => count($bolgeSet),
                'donem_sayisi' => count($donemler)
            ]
        ];

    } catch (\Exception $e) {
        $response['message'] = $e->getMessage();
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'report-settings-kaydet') {
    $response = ['status' => 'error', 'message' => 'Bilinmeyen hata'];
    try {
        $Settings = new \App\Model\SettingsModel();
        $settingsToUpdate = [
            'ekip_aralik_okuma' => $_POST['ekip_aralik_okuma'] ?? '',
            'ekip_aralik_kesme' => $_POST['ekip_aralik_kesme'] ?? '',
            'ekip_aralik_kesme_merkez' => $_POST['ekip_aralik_kesme_merkez'] ?? '',
            'ekip_aralik_kesme_ilce' => $_POST['ekip_aralik_kesme_ilce'] ?? '',
            'ekip_aralik_sayac_degisimi' => $_POST['ekip_aralik_sayac_degisimi'] ?? '',
            'ekip_aralik_kacak_kontrol' => $_POST['ekip_aralik_kacak_kontrol'] ?? '',
            'ekip_aralik_muhurleme' => $_POST['ekip_aralik_muhurleme'] ?? '',
            'dusulecek_is_turu' => $_POST['dusulecek_is_turu'] ?? 'Ödeme Yaptırıldı'
        ];

        $result = $Settings->upsertMultipleSettings($settingsToUpdate, $_SESSION['firma_id'] ?? null);
        $response = ['status' => $result ? 'success' : 'error', 'message' => $result ? 'Ayarlar başarıyla kaydedildi.' : 'Ayarlar kaydedilirken hata oluştu.'];
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'online-sorgu-sorgula') {
    ob_start();
    header('Content-Type: application/json; charset=utf-8');

    if (($_SESSION["firma_kodu"] ?? 17) != 17) {
        echo json_encode(['status' => 'error', 'message' => 'API sorgulaması şu an sadece Firma Kodu 17 için desteklenmektedir.']);
        exit;
    }

    $response = ['status' => 'error', 'message' => 'Bilinmeyen hata'];

    try {
        ini_set('memory_limit', '512M');
        $ilkFirma = $_POST['ilk_firma'] ?? ($_SESSION['firma_kodu'] ?? 17);
        $sonFirma = $_POST['son_firma'] ?? ($_SESSION['firma_kodu'] ?? 17);
        $baslangicTarihiRaw = $_POST['baslangic_tarihi'] ?? date('Y-m-d');
        $bitisTarihiRaw = $_POST['bitis_tarihi'] ?? date('Y-m-d');

        $baslangicTarihi = \App\Helper\Date::convertExcelDate($baslangicTarihiRaw, 'Y-m-d') ?: date('Y-m-d');
        $bitisTarihi = \App\Helper\Date::convertExcelDate($bitisTarihiRaw, 'Y-m-d') ?: date('Y-m-d');

        $firmaId = $_SESSION['firma_id'] ?? 0;
        $Settings = new \App\Model\SettingsModel();

        // CONCURRENCY LOCK
        $lockKey = 'lock_online_sorgu_sorgula_' . $firmaId;
        $activeLock = $Settings->getSettings($lockKey);
        $currentUserId = $_SESSION['user_id'] ?? 0;

        if (!empty($activeLock)) {
            $lockParts = explode('|', $activeLock);
            $lockTime = strtotime($lockParts[0]);
            if ((time() - $lockTime) < 600) {
                throw new Exception("Şu anda devam eden bir sorgulama işlemi bulunuyor.");
            }
        }
        $Settings->upsertSetting($lockKey, date('Y-m-d H:i:s') . '|' . $currentUserId);

        $resultsFilter = $_POST['results_filter'] ?? '';
        $yeniKayit = 0;
        $guncellenenKayit = 0;
        $bosSonucSayisi = 0;
        $atlanAnKayitlar = [];
        $atlanAnListesi = [];
        $mevcutHatalar = [];

        $KesmeAcmaSvc = new KesmeAcmaService();
        $apiData = [];

        $begin = new DateTime($baslangicTarihi);
        $end = new DateTime($bitisTarihi);
        $end->modify('+1 day');
        $interval = new DateInterval('P1D');
        $daterange = new DatePeriod($begin, $interval, $end);

        set_time_limit(1800); // 30 mins
        ini_set('max_execution_time', 1800);

        $gunlukHatalar = [];
        foreach ($daterange as $date) {
            $currentDateAPI = $date->format('d/m/Y');
            try {
                $offset = 0;
                $limit = 500;
                $hasMore = true;

                while ($hasMore) {
                    $apiResponse = $KesmeAcmaSvc->getData($currentDateAPI, $currentDateAPI, $ilkFirma, $sonFirma, $limit, $offset);
                    if (!($apiResponse['success'] ?? false)) break;

                    $batchData = $apiResponse['data']['data'] ?? [];
                    if (empty($batchData)) {
                        $hasMore = false;
                    } else {
                        foreach ($batchData as $item) {
                            $item['TARIH'] = $date->format('Y-m-d');
                            $apiData[] = $item;
                        }
                        if (count($batchData) < $limit) $hasMore = false;
                        else $offset += $limit;
                    }
                    if ($offset >= 5000) break;
                }
            } catch (Exception $e) {
                $gunlukHatalar[] = $currentDateAPI . ": " . $e->getMessage();
            }
        }

        $Tanimlamalar = new \App\Model\TanimlamalarModel();
        $Puantaj = new \App\Model\PuantajModel('yapilan_isler_sorgu');

        // Filter parameters from request
        $filterPersonelId = (int)($_POST['filter_personel_id'] ?? 0);
        $filterEkipKoduId = (int)($_POST['filter_ekip_kodu'] ?? 0);
        $filterWorkType = trim($_POST['filter_work_type'] ?? '');
        $filterWorkResult = trim($_POST['filter_work_result'] ?? '');

        // Lookup data for efficient processing
        $stmtAllEkip = $Puantaj->db->prepare("SELECT id, tur_adi, grup FROM tanimlamalar WHERE grup = 'ekip_kodu' AND silinme_tarihi IS NULL");
        $stmtAllEkip->execute();
        $ekipKodlariByNo = [];
        $ekipKodlariByName = [];
        while ($ek = $stmtAllEkip->fetch(PDO::FETCH_ASSOC)) {
            $name = trim($ek['tur_adi']);
            $ekipKodlariByName[mb_strtolower($name, 'UTF-8')] = $ek['id'];
            $gp = trim((string) ($ek['grup'] ?? ''));
            if ($gp !== '') $ekipKodlariByName[mb_strtolower($gp, 'UTF-8')] = $ek['id'];
            $teamNo = \App\Helper\EkipHelper::extractTeamNo(trim($gp . ' ' . $name));
            if ($teamNo > 0) $ekipKodlariByNo[$teamNo] = $ek['id'];
        }

        $stmtAllPersonel = $Puantaj->db->prepare("SELECT id, adi_soyadi, ekip_no FROM personel WHERE silinme_tarihi IS NULL");
        $stmtAllPersonel->execute();
        $personelByName = [];
        $personelByEkip = [];
        while ($p = $stmtAllPersonel->fetch(PDO::FETCH_ASSOC)) {
            $name = trim($p['adi_soyadi']);
            $personelByName[mb_strtolower($name, 'UTF-8')] = $p;
            if (($p['ekip_no'] ?? 0) > 0) $personelByEkip[$p['ekip_no']] = $p['id'];
        }

        $stmtAllHist = $Puantaj->db->prepare("SELECT ekip_kodu_id, personel_id, baslangic_tarihi, bitis_tarihi FROM personel_ekip_gecmisi WHERE firma_id = ?");
        $stmtAllHist->execute([$firmaId]);
        $ekipGecmisi = [];
        while ($h = $stmtAllHist->fetch(PDO::FETCH_ASSOC)) {
            $ekipGecmisi[$h['ekip_kodu_id']][] = $h;
        }

        $deleteSql = "UPDATE yapilan_isler_sorgu SET silinme_tarihi = NOW() WHERE firma_id = ? AND tarih BETWEEN ? AND ? AND silinme_tarihi IS NULL";
        $deleteParams = [$firmaId, $baslangicTarihi, $bitisTarihi];

        $insertBatch = [];
        foreach ($apiData as $veri) {
            $isEmriTipi = trim($veri['ISEMRITIPI'] ?? '');
            $ekipKoduStr = trim($veri['EKIP'] ?? '');
            $isEmriSonucu = trim($veri['SONUC'] ?? '');
            $sonuclanmis = $veri['SONUCLANMIS'] ?? 0;
            $acikOlanlar = $veri['ACIK'] ?? 0;
            $tarihRaw = $veri['TARIH'];

            if (empty(trim($isEmriTipi))) continue;
            if ((int) $sonuclanmis === 0) {
                $bosSonucSayisi++;
                continue;
            }

            $normDate = $tarihRaw;

            // 1. Identify Ekip/Team ID
            $defId = 0;
            $ekipKoduStrClean = trim($ekipKoduStr);
            $ekipKoduStrLower = mb_strtolower($ekipKoduStrClean, 'UTF-8');
            
            $ekipNo = \App\Helper\EkipHelper::extractTeamNo($ekipKoduStrClean);
            if ($ekipNo > 0) $defId = $ekipKodlariByNo[$ekipNo] ?? 0;
            if (!$defId && isset($ekipKodlariByName[$ekipKoduStrLower])) $defId = $ekipKodlariByName[$ekipKoduStrLower];

            // 2. Identify Personel via History matching the date
            $personelId = 0;
            if ($defId > 0) {
                if (isset($ekipGecmisi[$defId])) {
                    foreach ($ekipGecmisi[$defId] as $hist) {
                        if ($hist['baslangic_tarihi'] <= $normDate && ($hist['bitis_tarihi'] === null || $hist['bitis_tarihi'] >= $normDate)) {
                            $personelId = $hist['personel_id'];
                            break;
                        }
                    }
                }
                if (!$personelId) $personelId = $personelByEkip[$defId] ?? 0;
            }

            // Fallback for personel if EKIP string matches name
            if (!$personelId && isset($personelByName[$ekipKoduStrLower])) {
                $personelId = $personelByName[$ekipKoduStrLower]['id'];
            }

            // APPLY FILTERS
            if ($filterPersonelId > 0 && $personelId != $filterPersonelId) continue;
            if ($filterEkipKoduId > 0 && $defId != $filterEkipKoduId) continue;
            if ($filterWorkType !== '' && $isEmriTipi != $filterWorkType) continue;
            if ($filterWorkResult !== '' && $isEmriSonucu != $filterWorkResult) continue;

            $islemId = md5($normDate . '|' . trim($ekipKoduStr) . '|' . trim($isEmriTipi) . '|' . trim($isEmriSonucu));

            if ($defId === 0 && !empty($ekipKoduStrClean)) {
                $uniqKey = "EKIP|" . $ekipKoduStrClean;
                if (!isset($mevcutHatalar[$uniqKey])) {
                    $atlanAnKayitlar[] = ['ekip_kodu' => "Sistemde Ekip Yok: " . $ekipKoduStrClean, 'tarih' => date('d.m.Y', strtotime($normDate))];
                    $mevcutHatalar[$uniqKey] = true;
                }
            }

            $existingTur = $Tanimlamalar->isEmriSonucu(trim($isEmriTipi), trim($isEmriSonucu));
            $isEmriSonucuId = $existingTur ? $existingTur->id : 0;
            if (!$isEmriSonucuId && (!empty($isEmriTipi) || !empty($isEmriSonucu))) {
                $encryptedId = $Tanimlamalar->saveWithAttr([
                    'firma_id' => $firmaId,
                    'grup' => 'is_turu',
                    'tur_adi' => $isEmriTipi,
                    'is_emri_sonucu' => $isEmriSonucu,
                    'aciklama' => "Online sorgulama"
                ]);
                $isEmriSonucuId = \App\Helper\Security::decrypt($encryptedId);
            }

            $insertBatch[] = [$islemId, $personelId, $defId, $firmaId, $isEmriSonucuId, $isEmriTipi, $ekipKoduStr, $isEmriSonucu, $sonuclanmis, $acikOlanlar, $normDate];
            $yeniKayit++;
        }

        $Puantaj->db->beginTransaction();
        $deleteStmt = $Puantaj->db->prepare($deleteSql);
        $deleteStmt->execute($deleteParams);
        $silinenKayit = $deleteStmt->rowCount();

        if (!empty($insertBatch)) {
            $chunks = array_chunk($insertBatch, 500);
            foreach ($chunks as $chunk) {
                $placeholders = implode(',', array_fill(0, count($chunk), '(?,?,?,?,?,?,?,?,?,?,?)'));
                $sql = "INSERT INTO yapilan_isler_sorgu (islem_id, personel_id, ekip_kodu_id, firma_id, is_emri_sonucu_id, is_emri_tipi, ekip_kodu, is_emri_sonucu, sonuclanmis, acik_olanlar, tarih) VALUES $placeholders";
                $stmt = $Puantaj->db->prepare($sql);
                $flatParams = [];
                foreach ($chunk as $row) $flatParams = array_merge($flatParams, $row);
                $stmt->execute($flatParams);
            }
        }
        $Puantaj->db->commit();

        $response = [
            'status' => 'success',
            'yeni_kayit' => $yeniKayit,
            'silinen_kayit' => $silinenKayit,
            'atlan_kayit_bos' => $bosSonucSayisi,
            'atlanAn_kayitlar' => $atlanAnKayitlar,
            'gunluk_hatalar' => $gunlukHatalar,
            'toplam_api_kayit' => count($apiData),
            'message' => "$yeniKayit yeni kayıt eklendi." . (count($gunlukHatalar) > 0 ? " (Bazı günlerde hata oluştu: " . count($gunlukHatalar) . " gün)" : "")
        ];

    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    } finally {
        if (isset($Settings) && isset($lockKey)) $Settings->upsertSetting($lockKey, '');
    }

    ob_end_clean();
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get-puantaj-sorgu-datatable') {
    $Puantaj = new PuantajModel('yapilan_isler_sorgu');
    $startDate = $_GET['start_date'] ?? date('Y-m-d');
    $endDate = $_GET['end_date'] ?? date('Y-m-d');
    $ekipKodu = $_GET['ekip_kodu'] ?? '';
    $workType = $_GET['work_type'] ?? '';
    $workResult = $_GET['work_result'] ?? '';

    // Correctly call getDataTable which handles advanced filters
    $response = $Puantaj->getDataTable($_GET, $startDate, $endDate, $ekipKodu, $workType, $workResult);
    
    // Map data for display
    $response['data'] = array_map(function($row) {
        return [
            'id' => $row->id,
            'checkbox' => '<div class="form-check"><input class="form-check-input row-check" type="checkbox" value="'.$row->id.'"></div>',
            'tarih' => \App\Helper\Date::dmY($row->tarih),
            'ekip_kodu' => $row->ekip_kodu_adi ?: ($row->ekip_kodu ?: ($row->ekip_kodu_tanim ?? '-')),
            'personel_adi' => $row->personel_adi ?: 'Eşleşmedi',
            'is_emri_tipi' => $row->is_emri_tipi,
            'is_emri_sonucu' => $row->is_emri_sonucu,
            'sonuclanmis' => $row->sonuclanmis,
            'acik_olanlar' => $row->acik_olanlar
        ];
    }, $response['data']);

    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'puantaj-sil-toplu') {
    $ids = $_POST['ids'] ?? [];
    if (empty($ids)) {
        echo json_encode(['status' => 'error', 'message' => 'Silinecek kayıt bulunamadı.']);
        exit;
    }
    
    $Puantaj = new PuantajModel();
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $Puantaj->db->prepare("UPDATE yapilan_isler SET silinme_tarihi = NOW() WHERE id IN ($placeholders)");
    $result = $stmt->execute($ids);
    
    echo json_encode(['status' => $result ? 'success' : 'error']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'sorgu-sil-toplu') {
    $ids = $_POST['ids'] ?? [];
    if (empty($ids)) {
        echo json_encode(['status' => 'error', 'message' => 'Silinecek kayıt bulunamadı.']);
        exit;
    }
    
    $Puantaj = new PuantajModel('yapilan_isler_sorgu');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $Puantaj->db->prepare("UPDATE yapilan_isler_sorgu SET silinme_tarihi = NOW() WHERE id IN ($placeholders)");
    $result = $stmt->execute($ids);
    
    echo json_encode(['status' => $result ? 'success' : 'error']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'export-excel-sorgu') {
    $Puantaj = new PuantajModel('yapilan_isler_sorgu');
    $startDate = $_GET['start_date'] ?? date('Y-m-d');
    $endDate = $_GET['end_date'] ?? date('Y-m-d');
    $ekipKodu = $_GET['ekip_kodu'] ?? '';
    $workType = $_GET['work_type'] ?? '';
    $workResult = $_GET['work_result'] ?? '';

    // Fetch all filtered records (length = -1)
    $data = $Puantaj->getDataTable($_GET, $startDate, $endDate, $ekipKodu, $workType, $workResult);
    $records = $data['data'] ?? [];

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Puantaj Sorgu');

    // Headers
    $headers = ['Tarih', 'Ekip Kodu', 'Personel', 'İş Emri Tipi', 'İş Emri Sonucu', 'Sonuçlanmış', 'Açık Olanlar'];
    $cols = ['A', 'B', 'C', 'D', 'E', 'F', 'G'];
    foreach ($headers as $idx => $header) {
        $sheet->setCellValue($cols[$idx] . '1', $header);
    }
    $sheet->getStyle('A1:G1')->getFont()->setBold(true);

    // Data
    $rowIdx = 2;
    foreach ($records as $row) {
        $sheet->setCellValue('A' . $rowIdx, \App\Helper\Date::dmY($row->tarih));
        $sheet->setCellValue('B' . $rowIdx, $row->ekip_kodu_adi ?: ($row->ekip_kodu ?: '-'));
        $sheet->setCellValue('C' . $rowIdx, $row->personel_adi ?: 'Eşleşmedi');
        $sheet->setCellValue('D' . $rowIdx, $row->is_emri_tipi);
        $sheet->setCellValue('E' . $rowIdx, $row->is_emri_sonucu);
        $sheet->setCellValue('F' . $rowIdx, $row->sonuclanmis);
        $sheet->setCellValue('G' . $rowIdx, $row->acik_olanlar);
        $rowIdx++;
    }

    // Auto size columns
    foreach (range('A', 'G') as $colId) {
        $sheet->getColumnDimension($colId)->setAutoSize(true);
    }

    $filename = 'Puantaj_Sorgu_Export_' . date('Ymd_His') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'sorgu-sil') {
    $id = $_POST['id'] ?? 0;
    $Puantaj = new PuantajModel('yapilan_isler_sorgu');
    $stmt = $Puantaj->db->prepare("UPDATE yapilan_isler_sorgu SET silinme_tarihi = NOW() WHERE id = ?");
    $result = $stmt->execute([$id]);
    echo json_encode(['status' => $result ? 'success' : 'error']);
    exit;
}
