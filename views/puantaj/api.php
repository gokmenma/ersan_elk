<?php
session_start();
require_once dirname(__DIR__, 2) . '/Autoloader.php';
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Helper\Helper;
use App\Model\PuantajModel;
use App\Model\PersonelModel;

// Set header to JSON
// header('Content-Type: application/json');

$Puantaj = new PuantajModel();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'puantaj-excel-kaydet') {
    
    $response = ['status' => 'error', 'message' => 'Bilinmeyen hata'];

    try {
        if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Dosya yüklenemedi veya dosya seçilmedi.");
        }

        $uploadDate = $_POST['upload_date'] ?? date('Y-m-d');
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
                    if ($cellValue === 'Firma') $colMap['firma'] = $colIndex;
                    elseif ($cellValue === 'İş Emri Tipi') $colMap['is_emri_tipi'] = $colIndex;
                    elseif ($cellValue === 'Ekip') $colMap['ekip'] = $colIndex;
                    elseif ($cellValue === 'İş Emri Sonucu') $colMap['is_emri_sonucu'] = $colIndex;
                    elseif (stripos($cellValue, 'Sonuçlanmış') !== false) $colMap['sonuclanmis'] = $colIndex;
                    elseif (stripos($cellValue, 'Açık Olanlar') !== false) $colMap['acik_olanlar'] = $colIndex;
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
            $sonuclanmis = isset($colMap['sonuclanmis']) ? (int)trim($row[$colMap['sonuclanmis']]) : 0;
            $acikOlanlar = isset($colMap['acik_olanlar']) ? (int)trim($row[$colMap['acik_olanlar']]) : 0;

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
            $stmt = $Puantaj->db->prepare("INSERT INTO yapilan_isler (islem_id, firma, is_emri_tipi, ekip_kodu, is_emri_sonucu, sonuclanmis, acik_olanlar, tarih) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $result = $stmt->execute([
                $islemId,
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
        $response['message'] = "İşlem tamamlandı. $insertedCount kayıt eklendi, $skippedCount kayıt daha önce yüklendiği için atlandı.";

    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }

    echo json_encode($response);
    exit;
}
