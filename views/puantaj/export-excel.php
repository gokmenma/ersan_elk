<?php
session_start();
require_once dirname(__DIR__, 2) . '/Autoloader.php';
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Model\TanimlamalarModel;
use App\Model\EndeksOkumaModel;
use App\Model\PuantajModel;
use App\Model\SayacDegisimModel;
use App\Model\FirmaModel;
use App\Helper\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

$Firma = new FirmaModel();
$firma = $Firma->getFirma($_SESSION['firma_id'] ?? 0);
$firmaAdi = $firma->firma_adi ?? 'Ersan Elektrik';

$tab = $_GET['tab'] ?? 'okuma';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$ekipKodu = $_GET['ekip_kodu'] ?? '';
$workType = $_GET['work_type'] ?? '';
$workResult = $_GET['work_result'] ?? '';

// Build request array for Model's getDataTable (simulating DataTables request)
$request = $_GET;
$request['length'] = -1; // Get all filtered records
$request['start'] = 0;

$data = [];
$headers = [];
$title = "";

if ($tab === 'okuma') {
    $Model = new EndeksOkumaModel();
    $result = $Model->getDataTable($request, $startDate, $endDate, $ekipKodu);
    $data = $result['data'];
    $title = "Endeks Okuma Listesi";
    $headers = ['TARİH', 'DEFTER', 'BÖLGE', 'EKİP NO', 'İSİM SOYİSİM', 'OKUNAN ABONE', 'SAYAÇ DURUM'];
    $colFields = ['tarih', 'defter', 'bolge', 'ekip_kodu_adi', 'personel_adi', 'okunan_abone_sayisi', 'sayac_durum'];
} elseif ($tab === 'yapilan_isler' || $tab === 'muhurleme') {
    $Model = new PuantajModel();
    // For muhurleme tab, we might need to filter by workType if not already in request
    $actualWorkResult = ($tab === 'muhurleme') ? 'MÜHÜRLEME' : $workResult;
    $result = $Model->getDataTable($request, $startDate, $endDate, $ekipKodu, $workType, $actualWorkResult);
    $data = $result['data'];
    $title = ($tab === 'muhurleme') ? "Mühürleme Listesi" : "Yapılan İşler Listesi";
    $headers = ['TARİH', 'EKİP KODU', 'İSİM SOYİSİM', 'İŞ EMRİ TİPİ', 'İŞ EMRİ SONUCU', 'SONUÇLANMIŞ', 'AÇIK OLANLAR'];
    $colFields = ['tarih', 'ekip_kodu_adi', 'personel_adi', 'is_emri_tipi', 'is_emri_sonucu', 'sonuclanmis', 'acik_olanlar'];
} elseif ($tab === 'sayac_sokme_takma') {
    $Model = new SayacDegisimModel();
    $result = $Model->getDataTable($request, $startDate, $endDate, $ekipKodu);
    $data = $result['data'];
    $title = "Sayaç Sökme Takma Listesi";
    $headers = ['KAYIT TARİHİ', 'EKİP', 'İSİM SOYİSİM', 'BÖLGE', 'İŞEMRİ SEBEP', 'İŞEMRİ SONUCU', 'ABONE NO', 'TAKILAN SAYAÇ NO'];
    $colFields = ['kayit_tarihi', 'ekip', 'personel_adi', 'bolge', 'isemri_sebep', 'isemri_sonucu', 'abone_no', 'takilan_sayacno'];
} elseif ($tab === 'kacak_kontrol') {
    // Kaçak kontrol is client-side, but we can still fetch data via API-like logic
    // Actually, kacak is small enough that we might just use the same pattern
    $Puantaj = new PuantajModel();
    $sql = "SELECT t.*, p.adi_soyadi as personel_adi 
            FROM kacak_kontrol t 
            LEFT JOIN personel p ON FIND_IN_SET(p.id, t.personel_ids)
            WHERE t.firma_id = ? AND t.silinme_tarihi IS NULL";
    $params = [$_SESSION['firma_id']];
    
    $search = $_GET['search']['value'] ?? '';
    if ($startDate) { $sql .= " AND t.tarih >= ?"; $params[] = Date::Ymd($startDate) ?: $startDate; }
    if ($endDate) { $sql .= " AND t.tarih <= ?"; $params[] = Date::Ymd($endDate) ?: $endDate; }
    if ($ekipKodu) { $sql .= " AND t.ekip_adi = ?"; $params[] = $ekipKodu; }
    if ($search) {
        $sql .= " AND (t.bolge LIKE ? OR t.ekip_adi LIKE ? OR t.aciklama LIKE ? OR p.adi_soyadi LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $sql .= " ORDER BY t.tarih DESC";
    $stmt = $Puantaj->db->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_OBJ);
    
    $title = "Kaçak Kontrol Listesi";
    $headers = ['TARİH', 'BÖLGE', 'EKİP ADI', 'İSİM SOYİSİM', 'SAYI', 'AÇIKLAMA'];
    $colFields = ['tarih', 'bolge', 'ekip_adi', 'personel_adi', 'sayi', 'aciklama'];
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Liste');

// --- Styles ---
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4B39B5']], // Premium Purple/Dark Blue
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]]
];

$dataStyle = [
    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'EEEEEE']]]
];

$titleStyle = [
    'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '333333']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT]
];

// --- Header Construction ---
$sheet->setCellValue('A1', $firmaAdi);
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);

$sheet->setCellValue('A2', $title);
$sheet->getStyle('A2')->applyFromArray($titleStyle);

$filterText = "";
if ($startDate || $endDate) $filterText .= "Tarih: $startDate - $endDate | ";
if ($ekipKodu) $filterText .= "Ekip: $ekipKodu | ";
$sheet->setCellValue('A3', rtrim($filterText, ' | '));
$sheet->getStyle('A3')->getFont()->setItalic(true)->setSize(10);

$row = 5;
$col = 'A';
foreach ($headers as $h) {
    $sheet->setCellValue($col . $row, $h);
    $sheet->getColumnDimension($col)->setAutoSize(true);
    $col++;
}
$lastCol = chr(ord('A') + count($headers) - 1);
$sheet->getStyle('A' . $row . ':' . $lastCol . $row)->applyFromArray($headerStyle);
$sheet->getRowDimension($row)->setRowHeight(25);

// --- Data Population ---
$row++;
foreach ($data as $item) {
    $col = 'A';
    foreach ($colFields as $field) {
        $val = $item->$field ?? '';
        
        // Date formatting if needed
        if (($field === 'tarih' || $field === 'kayit_tarihi') && !empty($val)) {
            $val = date('d.m.Y', strtotime($val)) . (strlen($val) > 10 ? ' ' . date('H:i', strtotime($val)) : '');
        }
        
        $sheet->setCellValue($col . $row, $val);
        
        // Centering for counts
        if (in_array($field, ['okunan_abone_sayisi', 'sonuclanmis', 'acik_olanlar', 'sayi'])) {
            $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
        
        $col++;
    }
    $row++;
}

// Final Styling
$sheet->getStyle('A6:' . $lastCol . ($row - 1))->applyFromArray($dataStyle);
foreach(range('A', $lastCol) as $columnID) {
    $sheet->getColumnDimension($columnID)->setAutoSize(true);
}

// --- Download ---
$fileName = str_replace(' ', '_', $title) . '_' . date('Ymd_His') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $fileName . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
