<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/Autoloader.php';

if (empty($_SESSION['firma_id']) && empty($_SESSION['owner_id']) && empty($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Excel şablonu indirmek için lütfen sisteme giriş yapın.');
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Gelir Gider Şablonu');

// Başlık stilleri
$headerStyle = [
    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF'],
        'size' => 11
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '1E293B']
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => '000000']
        ]
    ]
];

// Veri stili
$dataStyle = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => 'E2E8F0']
        ]
    ],
    'alignment' => [
        'vertical' => Alignment::VERTICAL_CENTER
    ]
];

// Başlıklar
$headers = [
    'A1' => 'TARİH',
    'B1' => 'İŞLEM TİPİ',
    'C1' => 'KATEGORİ / İŞLEM TÜRÜ',
    'D1' => 'HESAP / CARİ ADI',
    'E1' => 'TUTAR',
    'F1' => 'AÇIKLAMA'
];

foreach ($headers as $cell => $value) {
    $sheet->setCellValue($cell, $value);
}

$sheet->getStyle('A1:F1')->applyFromArray($headerStyle);
$sheet->getRowDimension(1)->setRowHeight(25);

// Sütun Genişlikleri
$sheet->getColumnDimension('A')->setWidth(18);
$sheet->getColumnDimension('B')->setWidth(16);
$sheet->getColumnDimension('C')->setWidth(25);
$sheet->getColumnDimension('D')->setWidth(25);
$sheet->getColumnDimension('E')->setWidth(18);
$sheet->getColumnDimension('F')->setWidth(35);

// Örnek Satırlar
$samples = [
    [
        'tarih' => date('d.m.Y'),
        'tip' => 'GELİR',
        'kategori' => 'Hakediş / Satış',
        'hesap' => 'ABC Elektrik A.Ş.',
        'tutar' => 15000.00,
        'aciklama' => 'Örnek gelir kaydı'
    ],
    [
        'tarih' => date('d.m.Y'),
        'tip' => 'GİDER',
        'kategori' => 'Malzeme Alımı',
        'hesap' => 'XYZ Ticaret',
        'tutar' => 4500.50,
        'aciklama' => 'Örnek gider kaydı'
    ]
];

$rowNum = 2;
foreach ($samples as $sample) {
    $sheet->setCellValue('A' . $rowNum, $sample['tarih']);
    $sheet->setCellValue('B' . $rowNum, $sample['tip']);
    $sheet->setCellValue('C' . $rowNum, $sample['kategori']);
    $sheet->setCellValue('D' . $rowNum, $sample['hesap']);
    $sheet->setCellValue('E' . $rowNum, $sample['tutar']);
    $sheet->setCellValue('F' . $rowNum, $sample['aciklama']);

    $sheet->getStyle('A' . $rowNum . ':F' . $rowNum)->applyFromArray($dataStyle);
    $sheet->getStyle('A' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('B' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('E' . $rowNum)->getNumberFormat()->setFormatCode('#,##0.00');

    $rowNum++;
}

// Dosyayı indir
$filename = 'gelir_gider_sablonu_' . date('Y-m-d') . '.xlsx';

if (ob_get_length()) {
    ob_end_clean();
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
