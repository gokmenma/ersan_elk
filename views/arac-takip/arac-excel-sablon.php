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
    'J1' => 'Mülkiyet',
    'K1' => 'Notlar',
    'L1' => 'Sigorta Şirketi',
    'M1' => 'Sigorta Poliçe No',
    'N1' => 'Sigorta Başlangıç',
    'O1' => 'Sigorta Bitiş',
    'P1' => 'Sigorta Prim',
    'Q1' => 'Kasko Şirketi',
    'R1' => 'Kasko Poliçe No',
    'S1' => 'Kasko Başlangıç',
    'T1' => 'Kasko Bitiş',
    'U1' => 'Kasko Prim'
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

$sheet->getStyle('A1:U1')->applyFromArray($headerStyle);

// Örnek veriler
$sampleData = [
    ['34 ABC 123', 'Ford', 'Focus', '2022', 'Beyaz', 'Binek', 'Dizel', '45000', '25.05.2026', 'Şirket Aracı', 'Genel hizmet aracı', 'Anadolu Sigorta', 'TS-1234567', '10.03.2025', '10.03.2026', '8500', 'Allianz', 'KSK-7654321', '10.03.2025', '10.03.2026', '21000'],
    ['34 XYZ 789', 'Renault', 'Clio', '2023', 'Siyah', 'Binek', 'Benzin', '15200', '15.08.2026', 'Kiralama', '', '', '', '', '', '', '', '', '', '', ''],
    ['34 DEF 456', 'Iveco', 'Daily', '2021', 'Beyaz', 'Kamyonet', 'Dizel', '125000', '20.02.2026', 'Kiralık Araç', 'Nakliye ekibi', '', '', '', '', '', '', '', '', '', '']
];

$row = 2;
foreach ($sampleData as $data) {
    foreach ($data as $i => $value) {
        $sheet->setCellValue(chr(65 + $i) . $row, $value);
    }
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

$sheet->getStyle('A2:U4')->applyFromArray($sampleStyle);

// Sütun genişlikleri
$columnWidths = [
    'A' => 15, // Plaka,
    'B' => 15, // Marka,
    'C' => 15, // Model,
    'D' => 12, // Model Yılı,
    'E' => 12, // Renk,
    'F' => 15, // Araç Tipi,
    'G' => 12, // Yakıt Tipi,
    'H' => 12, // Güncel KM,
    'I' => 15, // Muayene Bitiş,
    'J' => 15, // Mülkiyet,
    'K' => 25, // Notlar,
    'L' => 20, // Sigorta Şirketi,
    'M' => 18, // Sigorta Poliçe No,
    'N' => 16, // Sigorta Başlangıç,
    'O' => 15, // Sigorta Bitiş,
    'P' => 14, // Sigorta Prim,
    'Q' => 20, // Kasko Şirketi,
    'R' => 18, // Kasko Poliçe No,
    'S' => 16, // Kasko Başlangıç,
    'T' => 15, // Kasko Bitiş,
    'U' => 14 // Kasko Prim
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
    ['Mülkiyet', 'Şirket Aracı, Kiralama, Kiralık Araç'],
    ['Notlar', 'Ek notlar'],
    ['Sigorta Şirketi / Poliçe No / Başlangıç / Prim', 'İsteğe bağlı. Sigorta Bitiş ile birlikte doldurulursa araca poliçe kaydı olarak eklenir (dosya sonradan yüklenir).'],
    ['Sigorta Bitiş', 'Trafik sigortası bitiş tarihi. Format: GG.AA.YYYY'],
    ['Kasko Şirketi / Poliçe No / Başlangıç / Prim', 'İsteğe bağlı. Kasko Bitiş ile birlikte doldurulursa araca poliçe kaydı olarak eklenir (dosya sonradan yüklenir).'],
    ['Kasko Bitiş', 'Kasko poliçesi bitiş tarihi. Format: GG.AA.YYYY'],
    ['Poliçe Kaydı', 'Bitiş tarihi dolu ve ilgili türün diğer sütunlarından en az biri dolu ise poliçe kaydı açılır. Aynı poliçe no ve bitişle tekrar yüklemede çift kayıt oluşmaz.'],
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

$infoSheet->getStyle('A21')->applyFromArray([
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
