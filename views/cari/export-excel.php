<?php
require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Model\CariModel;
use App\Helper\Helper;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

// Oturumu kontrol et
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$Cari = new CariModel();

// Filtreleri al
$balance_filter = $_GET['balance_filter'] ?? 'all';
$search = $_GET['search'] ?? '';

// Tüm verileri çekmek için bir metodumuz var mı bakalım veya manuel query
$db = $Cari->getDb();
$where = "silinme_tarihi IS NULL";
$params = [];

if ($balance_filter == 'borclu') {
    $where .= " AND (SELECT SUM(alacak - borc) FROM cari_hareketleri WHERE cari_id = cari.id AND silinme_tarihi IS NULL) < 0";
} elseif ($balance_filter == 'alacakli') {
    $where .= " AND (SELECT SUM(alacak - borc) FROM cari_hareketleri WHERE cari_id = cari.id AND silinme_tarihi IS NULL) > 0";
}

if ($search) {
    $where .= " AND (CariAdi LIKE :search OR firma LIKE :search OR Telefon LIKE :search OR Email LIKE :search OR Adres LIKE :search)";
    $params['search'] = "%$search%";
}

$sql = "SELECT *, (SELECT SUM(alacak - borc) FROM cari_hareketleri WHERE cari_id = cari.id AND silinme_tarihi IS NULL) as bakiye 
        FROM cari 
        WHERE $where 
        ORDER BY CariAdi ASC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll(PDO::FETCH_OBJ);

// Spreadsheet Oluştur
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Cari Listesi');

// Başlıklar
$headers = ['ID', 'Cari Adı', 'Firma', 'Telefon', 'Email', 'Adres', 'Bakiye'];
$col = 'A';
foreach ($headers as $header) {
    $sheet->setCellValue($col . '1', $header);
    $col++;
}

// Başlık Stili
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '135BEC']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
];
$sheet->getStyle('A1:G1')->applyFromArray($headerStyle);

// Verileri Yaz
$rowNum = 2;
foreach ($data as $row) {
    $bakiye = $row->bakiye ?? 0;
    $bakiyeLabel = $bakiye < 0 ? ' (B)' : ($bakiye > 0 ? ' (A)' : '');
    
    $sheet->setCellValue('A' . $rowNum, $row->id);
    $sheet->setCellValue('B' . $rowNum, $row->CariAdi);
    $sheet->setCellValue('C' . $rowNum, $row->firma ?: '-');
    $sheet->setCellValue('D' . $rowNum, $row->Telefon ?: '-');
    $sheet->setCellValue('E' . $rowNum, $row->Email ?: '-');
    $sheet->setCellValue('F' . $rowNum, $row->Adres ?: '-');
    $sheet->setCellValue('G' . $rowNum, abs($bakiye));
    
    // Sayı formatı ve bakiye türü
    $sheet->getStyle('G' . $rowNum)->getNumberFormat()->setFormatCode('#,##0.00"'. $bakiyeLabel .'"');
    
    // Bakiye rengi
    if ($bakiye < 0) {
        $sheet->getStyle('G' . $rowNum)->getFont()->getColor()->setRGB('FF0000');
    } elseif ($bakiye > 0) {
        $sheet->getStyle('G' . $rowNum)->getFont()->getColor()->setRGB('008000');
    }
    
    $rowNum++;
}

// Sütun genişliklerini otomatik ayarla
foreach (range('A', 'G') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Dosyayı İndir
$filename = 'Cari_Listesi_' . date('d_m_Y_H_i') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
