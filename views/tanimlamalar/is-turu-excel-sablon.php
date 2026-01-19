<?php

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Model\TanimlamalarModel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

$Tanimlamalar = new TanimlamalarModel();
$isTurleri = $Tanimlamalar->getIsTurleri();

// Excel oluştur
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('İş Türleri');

// Başlık stilleri
$headerStyle = [
    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF'],
        'size' => 12
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '556ee6']
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

// Veri stilleri
$dataStyle = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => 'CCCCCC']
        ]
    ],
    'alignment' => [
        'vertical' => Alignment::VERTICAL_CENTER
    ]
];

// Başlıklar
$sheet->setCellValue('A1', 'İş Türü');
$sheet->setCellValue('B1', 'İş Türü Ücreti');
$sheet->setCellValue('C1', 'Açıklama');

// Başlık stili uygula
$sheet->getStyle('A1:C1')->applyFromArray($headerStyle);
$sheet->getRowDimension(1)->setRowHeight(25);

// Sütun genişlikleri
$sheet->getColumnDimension('A')->setWidth(30);
$sheet->getColumnDimension('B')->setWidth(20);
$sheet->getColumnDimension('C')->setWidth(40);

// Verileri ekle
$row = 2;
foreach ($isTurleri as $isTuru) {
    $sheet->setCellValue('A' . $row, $isTuru->tur_adi);
    $sheet->setCellValue('B' . $row, $isTuru->is_turu_ucret ?? '');
    $sheet->setCellValue('C' . $row, $isTuru->aciklama ?? '');

    $sheet->getStyle('A' . $row . ':C' . $row)->applyFromArray($dataStyle);
    $row++;
}

// Eğer hiç kayıt yoksa örnek satır ekle
if (count($isTurleri) == 0) {
    $sheet->setCellValue('A2', 'Örnek İş Türü');
    $sheet->setCellValue('B2', '100.00');
    $sheet->setCellValue('C2', 'Örnek açıklama');
    $sheet->getStyle('A2:C2')->applyFromArray($dataStyle);
    $sheet->getStyle('A2:C2')->getFont()->setItalic(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('999999'));
}

// Dosyayı indir
$filename = 'is_turleri_sablon_' . date('Y-m-d_H-i-s') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
