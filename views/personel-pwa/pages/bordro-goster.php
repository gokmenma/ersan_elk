<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_GET['id'])) {
    header('Location: ../../index.php');
    exit;
}

require_once dirname(__DIR__, 3) . '/Autoloader.php';

use App\Model\BordroPersonelModel;
use App\Model\PersonelModel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Pdf\Mpdf;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;

$bordro_id = intval($_GET['id']);
$personel_id = $_SESSION['personel_id'] ?? 0;

$BordroModel = new BordroPersonelModel();
$bordro = $BordroModel->find($bordro_id);

if (!$bordro || $bordro->personel_id !== $personel_id) {
    header('Location: ../?page=bordro');
    exit;
}

$PersonelModel = new PersonelModel();
$personel = $PersonelModel->find($personel_id);

$kesintilerDetay = $BordroModel->getDonemKesintileriDetay($personel_id, $bordro->donem_id);
$ekOdemelerDetay = $BordroModel->getDonemEkOdemeleriDetay($personel_id, $bordro->donem_id);
$guncelKesinti = $BordroModel->getDonemKesintileri($personel_id, $bordro->donem_id);
$guncelEkOdeme = $BordroModel->getDonemEkOdemeleri($personel_id, $bordro->donem_id);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Varsayılan Font Ayarı (UTF-8 desteği için)
$spreadsheet->getDefaultStyle()->getFont()->setName('DejaVu Sans');
$spreadsheet->getDefaultStyle()->getFont()->setSize(10);

// Sayfa Ayarları
$sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_PORTRAIT);
$sheet->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);
$sheet->getPageMargins()->setTop(0.5)->setBottom(0.5)->setLeft(0.5)->setRight(0.5);

// Renkler
$colorBlue = '135bec';
$colorRed = 'ef5350';
$colorGreen = '4caf50';
$colorOrange = 'ffb74d';
$colorLightBlue = 'e3f2fd';
$colorLightGreen = 'e8f5e9';
$colorLightOrange = 'fff3e0';

// 1. ÜST BAŞLIK
$sheet->mergeCells('A1:F2');
$sheet->setCellValue('A1', 'BORDRO DETAYI');
$sheet->getStyle('A1:F2')->applyFromArray([
    'font' => ['bold' => true, 'size' => 16, 'color' => ['argb' => Color::COLOR_WHITE]],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $colorBlue]],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]
]);

$row = 4;

// 2. PERSONEL BİLGİLERİ & MAAŞ ÖZETİ BAŞLIKLARI
$sheet->mergeCells("A{$row}:C{$row}");
$sheet->setCellValue("A{$row}", '  Personel Bilgileri');
$sheet->mergeCells("D{$row}:F{$row}");
$sheet->setCellValue("D{$row}", '  Maaş Özeti');
$sheet->getStyle("A{$row}:F{$row}")->getFont()->setBold(true)->setSize(11);
$sheet->getStyle("A{$row}:F{$row}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('dddddd');
$row++;

// İçerik Satırları
$infoRows = [
    ['Ad Soyad:', $personel->adi_soyadi ?? '-', $personel->maas_durumu . ' Maaş:', number_format($bordro->brut_maas ?? 0, 2, ',', '.') . ' ₺'],
    ['TC Kimlik:', $personel->tc_kimlik_no ?? '-', 'Toplam Ek Ödeme:', '+' . number_format($guncelEkOdeme, 2, ',', '.') . ' ₺'],
    ['Departman:', $personel->departman ?? '-', 'Toplam Kesinti:', '-' . number_format($guncelKesinti + floatval($bordro->sgk_isci) + floatval($bordro->issizlik_isci) + floatval($bordro->gelir_vergisi) + floatval($bordro->damga_vergisi), 2, ',', '.') . ' ₺'],
    ['Görev:', $personel->gorev ?? '-', 'Net Maaş:', number_format($bordro->net_maas ?? 0, 2, ',', '.') . ' ₺'],
    ['İşe Giriş:', $personel->ise_giris_tarihi ? date('d.m.Y', strtotime($personel->ise_giris_tarihi)) : '-', '', '']
];

foreach ($infoRows as $idx => $info) {
    $sheet->setCellValue("A{$row}", $info[0]);
    $sheet->setCellValue("B{$row}", $info[1]);
    $sheet->setCellValue("D{$row}", $info[2]);
    $sheet->setCellValue("E{$row}", $info[3]);

    $sheet->getStyle("A{$row}")->getFont()->getColor()->setARGB('777777');
    $sheet->getStyle("B{$row}")->getFont()->setBold(true);
    $sheet->getStyle("D{$row}")->getFont()->getColor()->setARGB('777777');
    $sheet->getStyle("E{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

    if ($idx == 0)
        $sheet->getStyle("E{$row}")->getFont()->setBold(true)->getColor()->setARGB($colorBlue);
    if ($idx == 1)
        $sheet->getStyle("E{$row}")->getFont()->setBold(true)->getColor()->setARGB($colorGreen);
    if ($idx == 2)
        $sheet->getStyle("E{$row}")->getFont()->setBold(true)->getColor()->setARGB($colorRed);
    if ($idx == 3) {
        $sheet->mergeCells("E{$row}:F{$row}");
        $sheet->getStyle("E{$row}")->getFont()->setBold(true)->setSize(12)->getColor()->setARGB($colorGreen);
        $sheet->getStyle("D{$row}:F{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($colorLightGreen);
    }
    $sheet->getRowDimension($row)->setRowHeight(20);
    $row++;
}

$row += 1;

// 3. KARTLAR (KESİNTİLER & EK ÖDEMELER)
$cardHeaderRow = $row;
$sheet->mergeCells("A{$row}:B{$row}");
$sheet->setCellValue("A{$row}", ' Yasal Kesintiler');
$sheet->getStyle("A{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($colorRed);
$sheet->getStyle("A{$row}")->getFont()->setBold(true)->getColor()->setARGB(Color::COLOR_WHITE);

$sheet->mergeCells("C{$row}:D{$row}");
$sheet->setCellValue("C{$row}", ' Diğer Kesintiler');
$sheet->getStyle("C{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('f39c12'); // Turuncu
$sheet->getStyle("C{$row}")->getFont()->setBold(true)->getColor()->setARGB(Color::COLOR_WHITE);

$sheet->mergeCells("E{$row}:F{$row}");
$sheet->setCellValue("E{$row}", ' Ek Ödemeler');
$sheet->getStyle("E{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($colorGreen);
$sheet->getStyle("E{$row}")->getFont()->setBold(true)->getColor()->setARGB(Color::COLOR_WHITE);

$row++;
$cardStartRow = $row;

$yasalKesintiler = [
    ['SGK İşçi (%14)', $bordro->sgk_isci],
    ['İşsizlik İşçi (%1)', $bordro->issizlik_isci],
    ['Gelir Vergisi', $bordro->gelir_vergisi],
    ['Damga Vergisi', $bordro->damga_vergisi]
];

$maxItems = max(count($yasalKesintiler), count($kesintilerDetay), count($ekOdemelerDetay), 4);

for ($i = 0; $i < $maxItems; $i++) {
    // Yasal
    if (isset($yasalKesintiler[$i])) {
        $sheet->setCellValue("A" . ($row + $i), $yasalKesintiler[$i][0]);
        $sheet->setCellValue("B" . ($row + $i), '-' . number_format($yasalKesintiler[$i][1], 2, ',', '.') . ' ₺');
        $sheet->getStyle("B" . ($row + $i))->getFont()->getColor()->setARGB($colorRed);
    }

    // Diğer
    if (isset($kesintilerDetay[$i])) {
        $sheet->setCellValue("C" . ($row + $i), ucfirst($kesintilerDetay[$i]->tur));
        $sheet->setCellValue("D" . ($row + $i), '-' . number_format($kesintilerDetay[$i]->toplam_tutar, 2, ',', '.') . ' ₺');
        $sheet->getStyle("D" . ($row + $i))->getFont()->getColor()->setARGB($colorRed);
    } elseif ($i == 0 && empty($kesintilerDetay)) {
        $sheet->setCellValue("C" . ($row + $i), 'Kesinti yok');
        $sheet->getStyle("C" . ($row + $i))->getFont()->setItalic(true)->getColor()->setARGB('999999');
    }

    // Ek
    if (isset($ekOdemelerDetay[$i])) {
        $sheet->setCellValue("E" . ($row + $i), ucfirst($ekOdemelerDetay[$i]->tur));
        $sheet->setCellValue("F" . ($row + $i), '+' . number_format($ekOdemelerDetay[$i]->toplam_tutar, 2, ',', '.') . ' ₺');
        $sheet->getStyle("F" . ($row + $i))->getFont()->getColor()->setARGB($colorGreen);
    } elseif ($i == 0 && empty($ekOdemelerDetay)) {
        $sheet->setCellValue("E" . ($row + $i), 'Ek ödeme yok');
        $sheet->getStyle("E" . ($row + $i))->getFont()->setItalic(true)->getColor()->setARGB('999999');
    }
    $sheet->getStyle("B" . ($row + $i))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet->getStyle("D" . ($row + $i))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet->getStyle("F" . ($row + $i))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet->getRowDimension($row + $i)->setRowHeight(18);
}

$row += $maxItems;

// Kart Toplamları
$sheet->setCellValue("A{$row}", 'Toplam');
$sheet->setCellValue("B{$row}", '-' . number_format(array_sum(array_column($yasalKesintiler, 1)), 2, ',', '.') . ' ₺');
$sheet->getStyle("A{$row}:B{$row}")->getFont()->setBold(true)->getColor()->setARGB($colorRed);

$sheet->setCellValue("C{$row}", 'Toplam');
$sheet->setCellValue("D{$row}", '-' . number_format($guncelKesinti, 2, ',', '.') . ' ₺');
$sheet->getStyle("C{$row}:D{$row}")->getFont()->setBold(true)->getColor()->setARGB($colorRed);

$sheet->setCellValue("E{$row}", 'Toplam');
$sheet->setCellValue("F{$row}", '+' . number_format($guncelEkOdeme, 2, ',', '.') . ' ₺');
$sheet->getStyle("E{$row}:F{$row}")->getFont()->setBold(true)->getColor()->setARGB($colorGreen);

$sheet->getStyle("B{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
$sheet->getStyle("D{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
$sheet->getStyle("F{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

// Kenarlıklar
$sheet->getStyle("A{$cardHeaderRow}:B{$row}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('eeeeee');
$sheet->getStyle("C{$cardHeaderRow}:D{$row}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('eeeeee');
$sheet->getStyle("E{$cardHeaderRow}:F{$row}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('eeeeee');

$row += 2;

// 4. İŞVEREN MALİYETLERİ
$sheet->mergeCells("A{$row}:F{$row}");
$sheet->setCellValue("A{$row}", '  İşveren Maliyetleri');
$sheet->getStyle("A{$row}")->getFont()->setBold(true);
$sheet->getStyle("A{$row}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('dddddd');
$row++;

$maliyetData = [
    ['SGK İşveren (%20.5)', $bordro->sgk_isveren ?? 0],
    ['İşsizlik İşveren (%2)', $bordro->issizlik_isveren ?? 0],
    ['Toplam Maliyet', $bordro->toplam_maliyet ?? 0]
];

$col = 'A';
foreach ($maliyetData as $idx => $m) {
    $nextCol = chr(ord($col) + 1);
    $sheet->mergeCells("{$col}{$row}:{$nextCol}" . ($row + 1));
    $sheet->setCellValue("{$col}{$row}", "{$m[0]}\n" . number_format($m[1], 2, ',', '.') . ' ₺');
    $sheet->getStyle("{$col}{$row}")->getAlignment()->setWrapText(true)->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getStyle("{$col}{$row}")->getFont()->setBold(true);

    if ($idx == 2) {
        $sheet->getStyle("{$col}{$row}")->getFont()->getColor()->setARGB($colorBlue);
        $sheet->getStyle("{$col}{$row}:{$nextCol}" . ($row + 1))->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($colorLightBlue);
    } else {
        $sheet->getStyle("{$col}{$row}")->getFont()->getColor()->setARGB('e67e22'); // Turuncu tonu
    }

    $sheet->getStyle("{$col}{$row}:{$nextCol}" . ($row + 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('dddddd');
    $col = chr(ord($nextCol) + 1);
}

$row += 3;

// 5. ÖDEME DAĞILIMI
$sheet->mergeCells("A{$row}:F{$row}");
$sheet->setCellValue("A{$row}", '  Ödeme Dağılımı');
$sheet->getStyle("A{$row}")->getFont()->setBold(true);
$sheet->getStyle("A{$row}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('dddddd');
$row++;

$elden = ($bordro->net_maas ?? 0) - ($bordro->banka_odemesi ?? 0) - ($bordro->sodexo_odemesi ?? 0) - ($bordro->diger_odeme ?? 0);
$odemeData = [
    ['Banka', $bordro->banka_odemesi ?? 0, $colorBlue],
    ['Sodexo', $bordro->sodexo_odemesi ?? 0, '00bcd4'],
    ['Diğer', $bordro->diger_odeme ?? 0, '607d8b'],
    ['Elden', $elden, $colorOrange]
];

$col = 'A';
foreach ($odemeData as $o) {
    $nextCol = chr(ord($col) + 1);
    if ($o[0] == 'Elden') {
        $sheet->mergeCells("{$col}{$row}:{$nextCol}" . ($row + 1));
        $sheet->getStyle("{$col}{$row}:{$nextCol}" . ($row + 1))->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($colorLightOrange);
    } else {
        $sheet->mergeCells("{$col}{$row}:{$nextCol}" . ($row + 1));
    }

    $sheet->setCellValue("{$col}{$row}", "{$o[0]}\n" . number_format($o[1], 2, ',', '.') . ' ₺');
    $sheet->getStyle("{$col}{$row}")->getAlignment()->setWrapText(true)->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getStyle("{$col}{$row}")->getFont()->setBold(true)->getColor()->setARGB($o[2]);
    $sheet->getStyle("{$col}{$row}:{$nextCol}" . ($row + 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('dddddd');

    if ($o[0] == 'Diğer') {
        $col = 'E'; // Elden için E kolonuna atla
    } else {
        $col = chr(ord($nextCol) + 1);
    }
}

$row += 3;

// ALT BİLGİ
$sheet->mergeCells("A{$row}:F{$row}");
$sheet->setCellValue("A{$row}", 'Son Hesaplama: ' . ($bordro->hesaplama_tarihi ? date('d.m.Y H:i', strtotime($bordro->hesaplama_tarihi)) : '-'));
$sheet->getStyle("A{$row}")->getFont()->setSize(8)->getColor()->setARGB('999999');
$sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

// Kolon Genişlikleri
foreach (range('A', 'F') as $c) {
    $sheet->getColumnDimension($c)->setWidth(18);
}

header('Content-Type: application/pdf');
header('Content-Disposition: attachment;filename="bordro_' . $personel_id . '.pdf"');
header('Cache-Control: max-age=0');

$writer = new Mpdf($spreadsheet);
$writer->save('php://output');
exit;
