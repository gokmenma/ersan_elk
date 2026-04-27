<?php
require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Model\CariModel;
use App\Model\CariHareketleriModel;
use App\Helper\Security;
use App\Helper\Helper;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$cari_id_enc = $_GET['id'] ?? '';
$cari_id = Security::decrypt($cari_id_enc);

if (!$cari_id) {
    die("Geçersiz Cari ID");
}

$Cari = new CariModel();
$cariData = $Cari->find($cari_id);

if (!$cariData) {
    die("Cari bulunamadı");
}

$filter_type = $_GET['filter_type'] ?? 'all';
$search = $_GET['search'] ?? '';

// Verileri Çek
$db = $Cari->getDb();
$where = "cari_id = :cari_id AND silinme_tarihi IS NULL";
$params = ['cari_id' => $cari_id];

if ($filter_type == 'aldim') {
    $where .= " AND borc > 0";
} elseif ($filter_type == 'verdim') {
    $where .= " AND alacak > 0";
}

if ($search) {
    $where .= " AND (aciklama LIKE :search OR belge_no LIKE :search)";
    $params['search'] = "%$search%";
}

// Yürüyen bakiye ile çekmek için SQL
$sql = "SELECT *, 
        (SELECT SUM(alacak - borc) 
         FROM cari_hareketleri h2 
         WHERE h2.cari_id = h.cari_id 
           AND h2.silinme_tarihi IS NULL 
           AND (h2.islem_tarihi < h.islem_tarihi OR (h2.islem_tarihi = h.islem_tarihi AND h2.id <= h.id))) as yuruyen_bakiye
        FROM cari_hareketleri h
        WHERE $where 
        ORDER BY islem_tarihi ASC, id ASC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll(PDO::FETCH_OBJ);

// Spreadsheet Oluştur
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Hesap Hareketleri');

// Üst Bilgi
$sheet->setCellValue('A1', 'Cari:');
$sheet->setCellValue('B1', $cariData->CariAdi);
$sheet->getStyle('A1')->getFont()->setBold(true);

$sheet->setCellValue('A2', 'Firma:');
$sheet->setCellValue('B2', $cariData->firma ?: '-');
$sheet->getStyle('A2')->getFont()->setBold(true);

$sheet->setCellValue('A3', 'Tarih:');
$sheet->setCellValue('B3', date('d.m.Y H:i'));
$sheet->getStyle('A3')->getFont()->setBold(true);

// Başlıklar (5. satırdan başla)
$headers = ['Tarih', 'Belge No', 'Açıklama', 'Aldım (+)', 'Verdim (-)', 'Bakiye'];
$col = 'A';
foreach ($headers as $header) {
    $sheet->setCellValue($col . '5', $header);
    $col++;
}

// Başlık Stili
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '135BEC']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
];
$sheet->getStyle('A5:F5')->applyFromArray($headerStyle);

// Verileri Yaz
$rowNum = 6;
foreach ($data as $row) {
    $sheet->setCellValue('A' . $rowNum, date('d.m.Y H:i', strtotime($row->islem_tarihi)));
    $sheet->setCellValue('B' . $rowNum, $row->belge_no ?: '-');
    $sheet->setCellValue('C' . $rowNum, $row->aciklama ?: '-');
    $sheet->setCellValue('D' . $rowNum, $row->borc > 0 ? (float)$row->borc : 0);
    $sheet->setCellValue('E' . $rowNum, $row->alacak > 0 ? (float)$row->alacak : 0);
    $sheet->setCellValue('F' . $rowNum, (float)$row->yuruyen_bakiye);
    
    // Para Formatları
    $sheet->getStyle('D' . $rowNum . ':F' . $rowNum)->getNumberFormat()->setFormatCode('#,##0.00');
    
    // Bakiye türü için özel format (B/A)
    $yBakiye = (float)$row->yuruyen_bakiye;
    $bLabel = $yBakiye < 0 ? ' (B)' : ($yBakiye > 0 ? ' (A)' : '');
    $sheet->getStyle('F' . $rowNum)->getNumberFormat()->setFormatCode('#,##0.00"'. $bLabel .'"');
    
    if ($yBakiye < 0) {
        $sheet->getStyle('F' . $rowNum)->getFont()->getColor()->setRGB('FF0000');
    } elseif ($yBakiye > 0) {
        $sheet->getStyle('F' . $rowNum)->getFont()->getColor()->setRGB('008000');
    }
    
    $rowNum++;
}

// Sütun genişliklerini otomatik ayarla
foreach (range('A', 'F') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Dosyayı İndir
$filename = 'Hesap_Hareketleri_' . str_replace(' ', '_', $cariData->CariAdi) . '_' . date('d_m_Y') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
