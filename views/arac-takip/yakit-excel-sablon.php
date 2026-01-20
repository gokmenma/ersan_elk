<?php
/**
 * Yakıt Kaydı Excel Şablonu
 */

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Yakıt Kayıtları');

// Başlık satırı
$headers = [
    'A1' => 'Plaka',
    'B1' => 'Tarih',
    'C1' => 'KM',
    'D1' => 'Litre',
    'E1' => 'Birim Fiyat',
    'F1' => 'Toplam Tutar',
    'G1' => 'İstasyon',
    'H1' => 'Fatura No',
    'I1' => 'Notlar'
];

foreach ($headers as $cell => $value) {
    $sheet->setCellValue($cell, $value);
}

// Başlık stili
$headerStyle = [
    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF']
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '2E7D32']
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

$sheet->getStyle('A1:I1')->applyFromArray($headerStyle);

// Örnek veriler
$sampleData = [
    ['34 ABC 123', '20.01.2026', '125000', '50', '42.50', '2125.00', 'Shell', 'F-12345', 'Tam depo'],
    ['34 XYZ 789', '20.01.2026', '87500', '35', '42.75', '1496.25', 'Opet', 'F-67890', ''],
    ['34 DEF 456', '21.01.2026', '45200', '40', '42.50', '1700.00', 'BP', 'F-11111', 'Otoyol seferi']
];

$row = 2;
foreach ($sampleData as $data) {
    $sheet->setCellValue('A' . $row, $data[0]);
    $sheet->setCellValue('B' . $row, $data[1]);
    $sheet->setCellValue('C' . $row, $data[2]);
    $sheet->setCellValue('D' . $row, $data[3]);
    $sheet->setCellValue('E' . $row, $data[4]);
    $sheet->setCellValue('F' . $row, $data[5]);
    $sheet->setCellValue('G' . $row, $data[6]);
    $sheet->setCellValue('H' . $row, $data[7]);
    $sheet->setCellValue('I' . $row, $data[8]);
    $row++;
}

// Örnek veri stili (sarı arka plan - silinmesi gerektiğini belirtmek için)
$sampleStyle = [
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'FFF9C4']
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => 'CCCCCC']
        ]
    ]
];

$sheet->getStyle('A2:I4')->applyFromArray($sampleStyle);

// Sütun genişlikleri
$columnWidths = [
    'A' => 15, // Plaka
    'B' => 12, // Tarih
    'C' => 12, // KM
    'D' => 10, // Litre
    'E' => 12, // Birim Fiyat
    'F' => 14, // Toplam Tutar
    'G' => 15, // İstasyon
    'H' => 12, // Fatura No
    'I' => 25  // Notlar
];

foreach ($columnWidths as $col => $width) {
    $sheet->getColumnDimension($col)->setWidth($width);
}

// Açıklama sayfası
$infoSheet = $spreadsheet->createSheet();
$infoSheet->setTitle('Açıklama');

$info = [
    ['Yakıt Kaydı Excel Yükleme Şablonu'],
    [''],
    ['Sütun Açıklamaları:'],
    ['Plaka', 'Aracın plakası (Zorunlu). Sistemde kayıtlı olmalıdır.'],
    ['Tarih', 'Yakıt alım tarihi. Format: GG.AA.YYYY veya YYYY-AA-GG'],
    ['KM', 'Yakıt alım anındaki kilometre'],
    ['Litre', 'Alınan yakıt miktarı (litre)'],
    ['Birim Fiyat', 'Litre başına fiyat (TL). Boş bırakılırsa otomatik hesaplanır.'],
    ['Toplam Tutar', 'Ödenen toplam tutar (TL)'],
    ['İstasyon', 'Akaryakıt istasyonu adı (Opsiyonel)'],
    ['Fatura No', 'Fiş veya fatura numarası (Opsiyonel)'],
    ['Notlar', 'Ek notlar (Opsiyonel)'],
    [''],
    ['Önemli Notlar:'],
    ['- Sarı renkli örnek satırları silip kendi verilerinizi girin.'],
    ['- Plaka sütunu zorunludur ve sistemde kayıtlı olmalıdır.'],
    ['- Tarihi farklı formatlarda girebilirsiniz (GG.AA.YYYY veya YYYY-AA-GG).'],
    ['- Birim fiyat boş bırakılırsa, Toplam Tutar / Litre formülüyle hesaplanır.']
];

$row = 1;
foreach ($info as $line) {
    if (is_array($line) && count($line) > 1) {
        $infoSheet->setCellValue('A' . $row, $line[0]);
        $infoSheet->setCellValue('B' . $row, $line[1]);
    } else {
        $infoSheet->setCellValue('A' . $row, is_array($line) ? $line[0] : $line);
    }
    $row++;
}

// Başlık stili
$infoSheet->getStyle('A1')->applyFromArray([
    'font' => ['bold' => true, 'size' => 14]
]);

$infoSheet->getStyle('A3')->applyFromArray([
    'font' => ['bold' => true, 'size' => 12]
]);

$infoSheet->getStyle('A14')->applyFromArray([
    'font' => ['bold' => true, 'size' => 12]
]);

$infoSheet->getColumnDimension('A')->setWidth(20);
$infoSheet->getColumnDimension('B')->setWidth(60);

// İlk sayfaya dön
$spreadsheet->setActiveSheetIndex(0);

// Dosyayı indir
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="yakit_kaydi_sablonu.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
