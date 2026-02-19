<?php
/**
 * KM Kaydı Excel Şablonu - Tüm araçları başlangıç KM'leriyle indir
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Model\AracModel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Font;

$AracModel = new AracModel();

// Tüm aktif araçları ve güncel KM'lerini çek
$pdo = $AracModel->getDb();
$stmt = $pdo->prepare("
    SELECT id, plaka, marka, model, COALESCE(guncel_km, baslangic_km, 0) as son_km
    FROM araclar
    WHERE firma_id = :firma_id
    AND aktif_mi = 1
    AND silinme_tarihi IS NULL
    ORDER BY plaka ASC
");
$stmt->execute(['firma_id' => $_SESSION['firma_id']]);
$araclar = $stmt->fetchAll(\PDO::FETCH_OBJ);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('KM Kayıtları');

// Başlık satırı
$headers = [
    'A1' => 'PLAKA',
    'B1' => 'TARİH (GG.AA.YYYY)',
    'C1' => 'BAŞLANGIÇ KM',
    'D1' => 'BİTİŞ KM',
    'E1' => 'NOTLAR',
];

foreach ($headers as $cell => $value) {
    $sheet->setCellValue($cell, $value);
}

// Başlık stili
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1A237E']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]]
];
$sheet->getStyle('A1:E1')->applyFromArray($headerStyle);
$sheet->getRowDimension(1)->setRowHeight(22);

// Veri giriş stili (açık mavi)
$dataStyle = [
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E8EAF6']],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'BBBBBB']]],
    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]
];

// Bitiş KM vurgusu - kullanıcının doldurması gereken
$bitisStyle = [
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E8F5E9']],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '4CAF50']]],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
];

// Araçları doldur
$date = date('d.m.Y');
$row = 2;
foreach ($araclar as $arac) {
    $sheet->setCellValue('A' . $row, $arac->plaka);
    $sheet->setCellValue('B' . $row, $date);
    $sheet->setCellValue('C' . $row, (int) $arac->son_km);
    $sheet->setCellValue('D' . $row, ''); // Bitiş KM boş - kullanıcı dolduracak
    $sheet->setCellValue('E' . $row, '');

    $sheet->getStyle("A{$row}:E{$row}")->applyFromArray($dataStyle);
    $sheet->getStyle("D{$row}")->applyFromArray($bitisStyle);

    $row++;
}

// Sütun genişlikleri
$columnWidths = [
    'A' => 16,
    'B' => 20,
    'C' => 16,
    'D' => 16,
    'E' => 30
];
foreach ($columnWidths as $col => $width) {
    $sheet->getColumnDimension($col)->setWidth($width);
}

// Açıklama sayfası
$infoSheet = $spreadsheet->createSheet();
$infoSheet->setTitle('Açıklama');

$info = [
    ['KM Kaydı Excel Yükleme Şablonu'],
    [''],
    ['Sütun Açıklamaları:'],
    ['PLAKA', 'Aracın plakası. Sistemde kayıtlı olmalıdır. DEĞİŞTİRMEYİN.'],
    ['TARİH', 'KM kaydının tarihi. Format: GG.AA.YYYY (Örn: 19.02.2026)'],
    ['BAŞLANGIÇ KM', 'Günün başlangıç kilometre değeri. Otomatik doldurulmuştur.'],
    ['BİTİŞ KM', 'Günün bitiş kilometre değeri. SADECE BU SÜTUNU DOLDURUN.'],
    ['NOTLAR', 'Ek açıklamalar (Opsiyonel)'],
    [''],
    ['Önemli Notlar:'],
    ['- Her satır = 1 araç için 1 günlük KM kaydı.'],
    ['- Plaka ve Başlangıç KM sütunlarını değiştirmeyin.'],
    ['- Bitiş KM, Başlangıç KM değerinden büyük olmalıdır.'],
    ['- Aynı araç için aynı tarihe birden fazla kayıt girebilirsiniz.'],
    ['- Bitiş KM boş satırlar yükleme sırasında atlanacaktır.'],
];

$r = 1;
foreach ($info as $line) {
    if (is_array($line) && count($line) > 1) {
        $infoSheet->setCellValue('A' . $r, $line[0]);
        $infoSheet->setCellValue('B' . $r, $line[1]);
    } else {
        $infoSheet->setCellValue('A' . $r, is_array($line) ? $line[0] : $line);
    }
    $r++;
}

$infoSheet->getStyle('A1')->applyFromArray(['font' => ['bold' => true, 'size' => 14]]);
$infoSheet->getStyle('A3')->applyFromArray(['font' => ['bold' => true, 'size' => 11]]);
$infoSheet->getStyle('A10')->applyFromArray(['font' => ['bold' => true, 'size' => 11]]);
$infoSheet->getColumnDimension('A')->setWidth(20);
$infoSheet->getColumnDimension('B')->setWidth(65);

$spreadsheet->setActiveSheetIndex(0);

// Dosyayı indir
if (ob_get_length())
    ob_end_clean();
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="km_kaydi_sablonu_' . date('Ymd') . '.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
