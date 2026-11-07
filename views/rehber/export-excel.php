<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Model\RehberModel;
use App\Service\Gate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

if (!Gate::allows('rehber/list')) {
    http_response_code(403);
    die('Rehber listesini dışa aktarma yetkiniz bulunmamaktadır.');
}

$Rehber = new RehberModel();
$search = trim((string) ($_GET['search'] ?? ''));

$sql = "SELECT id, adi_soyadi, kurum_adi, telefon, email, adres, aciklama, kayit_tarihi FROM rehber WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql .= " AND (adi_soyadi LIKE :search OR kurum_adi LIKE :search OR telefon LIKE :search OR email LIKE :search OR adres LIKE :search OR aciklama LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

$sql .= " ORDER BY adi_soyadi ASC";

$stmt = $Rehber->getDb()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_OBJ);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Telefon Rehberi');

// Başlık ve Açıklama Satırları
$sheet->mergeCells('A1:H1');
$sheet->setCellValue('A1', 'TELEFON REHBERİ LİSTESİ');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFF'));
$sheet->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1E3A8A');
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
$sheet->getRowDimension(1)->setRowHeight(32);

// Tablo Başlıkları
$headers = ['Sıra', 'Adı Soyadı', 'Kurum / Firma', 'Telefon', 'E-Posta', 'Adres', 'Açıklama', 'Kayıt Tarihi'];
$colLetters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];

$sheet->getRowDimension(2)->setRowHeight(24);
foreach ($headers as $idx => $h) {
    $col = $colLetters[$idx];
    $sheet->setCellValue($col . '2', $h);
    $sheet->getStyle($col . '2')->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFF'));
    $sheet->getStyle($col . '2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('3B82F6');
    $sheet->getStyle($col . '2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
}

// Verileri Doldur
$rowNum = 3;
$sira = 1;
foreach ($rows as $row) {
    $sheet->setCellValue('A' . $rowNum, $sira++);
    $sheet->setCellValue('B' . $rowNum, (string) $row->adi_soyadi);
    $sheet->setCellValue('C' . $rowNum, (string) $row->kurum_adi);
    $sheet->setCellValueExplicit('D' . $rowNum, (string) $row->telefon, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $sheet->setCellValue('E' . $rowNum, (string) $row->email);
    $sheet->setCellValue('F' . $rowNum, (string) $row->adres);
    $sheet->setCellValue('G' . $rowNum, (string) $row->aciklama);
    $sheet->setCellValue('H' . $rowNum, !empty($row->kayit_tarihi) ? date('d.m.Y H:i', strtotime($row->kayit_tarihi)) : '');

    // Hizalama
    $sheet->getStyle('A' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('D' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('H' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Satır arka planı (zebra)
    if ($rowNum % 2 === 0) {
        $sheet->getStyle('A' . $rowNum . ':H' . $rowNum)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8FAFC');
    }

    $sheet->getRowDimension($rowNum)->setRowHeight(20);
    $rowNum++;
}

// Çerçeveler
$lastRow = max(2, $rowNum - 1);
$sheet->getStyle('A2:H' . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('CBD5E1');

// Otomatik Sütun Genişliği
foreach ($colLetters as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

$fileName = 'rehber_listesi_' . date('Ymd_His') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
