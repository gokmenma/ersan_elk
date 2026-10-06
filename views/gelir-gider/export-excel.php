<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__, 2) . '/Autoloader.php';
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Model\GelirGiderModel;
use App\Helper\Helper;
use App\Helper\Security;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

$GelirGider = new GelirGiderModel();

$yil = $_GET['yil'] ?? '';
$ay = $_GET['ay'] ?? '';
$tip = $_GET['tip'] ?? '';
$search = $_GET['search'] ?? '';

$params = [
    'yil' => $yil,
    'ay' => $ay,
    'tip' => $tip,
    'search' => ['value' => $search],
    'length' => 100000,
    'start' => 0
];

$res = $GelirGider->ajaxList($params);
$data = $res['data'] ?? [];

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Gelir-Gider Listesi');

// Başlıklar
$headers = [
    'A' => 'Sıra',
    'B' => 'Kayıt Tarihi',
    'C' => 'Tür',
    'D' => 'Hesap Adı',
    'E' => 'Kategori',
    'F' => 'Plaka',
    'G' => 'Ödeme Şekli',
    'H' => 'Banka Adı',
    'I' => 'İşlem Tarihi',
    'J' => 'Tutar (TL)',
    'K' => 'Bakiye (TL)',
    'L' => 'Açıklama'
];

foreach ($headers as $col => $header) {
    $sheet->setCellValue($col . '1', $header);
}

// Başlık Stili
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '135BEC']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
];
$sheet->getStyle('A1:L1')->applyFromArray($headerStyle);
$sheet->getRowDimension('1')->setRowHeight(26);

$rowNum = 2;
foreach ($data as $row) {
    $typeStr = ((int)($row->type ?? $row->TYPE ?? 1) === 1) ? 'Gelir' : 'Gider';
    $tutar = (float)($row->tutar ?? $row->TUTAR ?? 0);
    $bakiye = (float)($row->bakiye ?? 0);

    $sheet->setCellValue('A' . $rowNum, $row->id);
    $sheet->setCellValue('B' . $rowNum, !empty($row->kayit_tarihi) ? date('d.m.Y H:i', strtotime($row->kayit_tarihi)) : '');
    $sheet->setCellValue('C' . $rowNum, $typeStr);
    $sheet->setCellValue('D' . $rowNum, $row->hesap_adi ?? $row->HESAP_ADI ?? '');
    $sheet->setCellValue('E' . $rowNum, $row->kategori_adi ?? $row->KATEGORI_ADI ?? '');
    $sheet->setCellValue('F' . $rowNum, $row->plaka ?? '');
    $sheet->setCellValue('G' . $rowNum, $row->odeme_sekli ?? '');
    $sheet->setCellValue('H' . $rowNum, $row->banka_adi ?? '');
    $sheet->setCellValue('I' . $rowNum, !empty($row->tarih) ? date('d.m.Y H:i', strtotime($row->tarih)) : '');
    $sheet->setCellValue('J' . $rowNum, $tutar);
    $sheet->setCellValue('K' . $rowNum, $bakiye);
    $sheet->setCellValue('L' . $rowNum, $row->aciklama ?? $row->ACIKLAMA ?? '');

    $sheet->getStyle('A' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('B' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('C' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('F' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('G' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('H' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('I' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('J' . $rowNum)->getNumberFormat()->setFormatCode('#,##0.00');
    $sheet->getStyle('K' . $rowNum)->getNumberFormat()->setFormatCode('#,##0.00');

    $rowNum++;
}

foreach (range('A', 'L') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

$fileName = 'gelir_gider_listesi_' . date('Ymd_His') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $fileName . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
