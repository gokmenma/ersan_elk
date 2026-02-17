<?php
/**
 * Araç Excel Şablonu
 */

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Araç Listesi');

// Başlık satırı
$headers = [
    'A1' => 'Plaka',
    'B1' => 'Marka',
    'C1' => 'Model',
    'D1' => 'Model Yılı',
    'E1' => 'Renk',
    'F1' => 'Araç Tipi',
    'G1' => 'Yakıt Tipi',
    'H1' => 'Güncel KM',
    'I1' => 'Muayene Bitiş',
    'J1' => 'Sigorta Bitiş',
    'K1' => 'Kasko Bitiş',
    'L1' => 'Mülkiyet',
    'M1' => 'Notlar'
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
        'startColor' => ['rgb' => '1565C0']
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

$sheet->getStyle('A1:M1')->applyFromArray($headerStyle);

// Örnek veriler
$sampleData = [
    ['34 ABC 123', 'Ford', 'Focus', '2022', 'Beyaz', 'Binek', 'Dizel', '45000', '25.05.2026', '10.03.2026', '10.03.2026', 'Şirket Aracı', 'Genel hizmet aracı'],
    ['34 XYZ 789', 'Renault', 'Clio', '2023', 'Siyah', 'Binek', 'Benzin', '15200', '15.08.2026', '01.06.2026', '01.06.2026', 'Kiralama', ''],
    ['34 DEF 456', 'Iveco', 'Daily', '2021', 'Beyaz', 'Kamyonet', 'Dizel', '125000', '20.02.2026', '15.01.2026', '15.01.2026', 'Kiralık Araç', 'Nakliye ekibi']
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
    $sheet->setCellValue('J' . $row, $data[9]);
    $sheet->setCellValue('K' . $row, $data[10]);
    $sheet->setCellValue('L' . $row, $data[11]);
    $sheet->setCellValue('M' . $row, $data[12]);
    $row++;
}

// Örnek veri stili
$sampleStyle = [
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'E3F2FD']
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => 'CCCCCC']
        ]
    ]
];

$sheet->getStyle('A2:M4')->applyFromArray($sampleStyle);

// Sütun genişlikleri
$columnWidths = [
    'A' => 15, // Plaka
    'B' => 15, // Marka
    'C' => 15, // Model
    'D' => 12, // Model Yılı
    'E' => 12, // Renk
    'F' => 15, // Araç Tipi
    'G' => 12, // Yakıt Tipi
    'H' => 12, // Güncel KM
    'I' => 15, // Muayene Tarihi
    'J' => 15, // Sigorta Bitiş
    'K' => 15, // Kasko Bitiş
    'L' => 15, // Mülkiyet
    'M' => 25  // Notlar
];

foreach ($columnWidths as $col => $width) {
    $sheet->getColumnDimension($col)->setWidth($width);
}

// Açıklama sayfası
$infoSheet = $spreadsheet->createSheet();
$infoSheet->setTitle('Açıklama');

$info = [
    ['Araç Excel Yükleme Şablonu'],
    [''],
    ['Sütun Açıklamaları:'],
    ['Plaka', 'Aracın plakası (Zorunlu). Format: 34ABC123'],
    ['Marka', 'Aracın markası (Örn: Ford, Renault)'],
    ['Model', 'Aracın modeli (Örn: Focus, Clio)'],
    ['Model Yılı', 'Aracın üretim yılı (Örn: 2022)'],
    ['Renk', 'Aracın rengi'],
    ['Araç Tipi', 'Binek, Kamyonet, Kamyon, Minibüs, Otobüs, Motosiklet, Diğer'],
    ['Yakıt Tipi', 'Dizel, Benzin, LPG, Elektrik, Hibrit'],
    ['Güncel KM', 'Aracın şu anki kilometresi'],
    ['Muayene Bitiş', 'Sonraki muayene tarihi. Format: GG.AA.YYYY'],
    ['Sigorta Bitiş', 'Trafik sigortası bitiş tarihi. Format: GG.AA.YYYY'],
    ['Kasko Bitiş', 'Kasko poliçesi bitiş tarihi. Format: GG.AA.YYYY'],
    ['Mülkiyet', 'Şirket Aracı, Kiralama, Kiralık Araç'],
    ['Notlar', 'Ek notlar'],
    [''],
    ['Önemli Notlar:'],
    ['- Mavi renkli örnek satırları silip kendi verilerinizi girin.'],
    ['- Plaka sütunu zorunludur.'],
    ['- Araç Tipi ve Yakıt Tipi için yukarıdaki seçenekleri kullanın.'],
    ['- Tarihleri GG.AA.YYYY formatında girin.']
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

$infoSheet->getStyle('A17')->applyFromArray([
    'font' => ['bold' => true, 'size' => 12]
]);

$infoSheet->getColumnDimension('A')->setWidth(20);
$infoSheet->getColumnDimension('B')->setWidth(60);

// İlk sayfaya dön
$spreadsheet->setActiveSheetIndex(0);

// Dosyayı indir
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="arac_sablonu.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
