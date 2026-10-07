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

$staticFile = dirname(__DIR__, 2) . '/files/gelir_gider_sablon.xlsx';
if (file_exists($staticFile)) {
    if (ob_get_length()) {
        ob_end_clean();
    }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="gelir_gider_sablonu_' . date('Y-m-d') . '.xlsx"');
    header('Content-Length: ' . filesize($staticFile));
    header('Cache-Control: max-age=0');
    readfile($staticFile);
    exit;
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Gelir-Gider Şablonu');
$sheet->setShowGridLines(true);

// Başlık stilleri
$headerStyle = [
    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF'],
        'size' => 11
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '0D6EFD']
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

// Başlıklar
$headers = [
    'A1' => 'Sıra',
    'B1' => 'İşlem Tarihi',
    'C1' => 'Hesap Adı',
    'D1' => 'Tutar (TL)',
    'E1' => 'Kategori',
    'F1' => 'Plaka',
    'G1' => 'Açıklama',
    'H1' => 'Tür',
    'I1' => 'Ödeme Şekli',
    'J1' => 'Banka Adı',
    'K1' => 'Bakiye (TL)',
    'L1' => 'Kayıt Tarihi'
];

foreach ($headers as $cell => $value) {
    $sheet->setCellValue($cell, $value);
}

$sheet->getStyle('A1:L1')->applyFromArray($headerStyle);
$sheet->getRowDimension(1)->setRowHeight(26);

// Sütun Genişlikleri
$sheet->getColumnDimension('A')->setWidth(10);
$sheet->getColumnDimension('B')->setWidth(18);
$sheet->getColumnDimension('C')->setWidth(26);
$sheet->getColumnDimension('D')->setWidth(18);
$sheet->getColumnDimension('E')->setWidth(22);
$sheet->getColumnDimension('F')->setWidth(16);
$sheet->getColumnDimension('G')->setWidth(35);
$sheet->getColumnDimension('H')->setWidth(14);
$sheet->getColumnDimension('I')->setWidth(18);
$sheet->getColumnDimension('J')->setWidth(22);
$sheet->getColumnDimension('K')->setWidth(18);
$sheet->getColumnDimension('L')->setWidth(20);

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
