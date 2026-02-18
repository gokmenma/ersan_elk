<?php
/**
 * Araç Takip Puantaj Excel Export
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Model\AracKmModel;
use App\Helper\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

$year = intval($_GET['year'] ?? date('Y'));
$month = str_pad($_GET['month'] ?? date('m'), 2, '0', STR_PAD_LEFT);
$arac_id = isset($_GET['arac_id']) && $_GET['arac_id'] !== '' ? intval($_GET['arac_id']) : null;
$showKm = isset($_GET['show_km']) && $_GET['show_km'] == '1';

try {
    $Km = new AracKmModel();
    $puantajData = $Km->getMonthlyPuantaj($year, $month, $arac_id);
    $gunSayisi = date('t', strtotime("$year-$month-01"));
    $monthName = Date::monthName($month);

    if (empty($puantajData)) {
        die('Kriterlere uygun kayıt bulunamadı.');
    }

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Araç Puantaj');

    // Başlık Satırı 1 (Ana Başlıklar)
    $sheet->setCellValue('A1', '#');
    $sheet->setCellValue('B1', 'Plaka');
    $sheet->setCellValue('C1', 'Marka/Model');

    $colIdx = 4; // D kolonundan itibaren
    for ($i = 1; $i <= $gunSayisi; $i++) {
        $colStart = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx);

        if ($showKm) {
            $colEnd = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx + 2);
            $sheet->setCellValue($colStart . '1', $i);
            $sheet->mergeCells($colStart . '1:' . $colEnd . '1');

            $sheet->setCellValue($colStart . '2', 'Bas.');
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx + 1) . '2', 'Bit.');
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx + 2) . '2', 'Toplam');
            $colIdx += 3;
        } else {
            $sheet->setCellValue($colStart . '1', $i);
            $sheet->mergeCells($colStart . '1:' . $colStart . '2');
            $colIdx += 1;
        }
    }

    $lastColStr = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx);
    $sheet->setCellValue($lastColStr . '1', 'AYLIK TOPLAM');
    $sheet->mergeCells($lastColStr . '1:' . $lastColStr . '2');

    // İlk 3 kolonu merge et
    $sheet->mergeCells('A1:A2');
    $sheet->mergeCells('B1:B2');
    $sheet->mergeCells('C1:C2');

    // Başlık Stili
    $baslikStyle = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4B5563']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ];
    $sheet->getStyle('A1:' . $lastColStr . '2')->applyFromArray($baslikStyle);

    // Verileri Yaz
    $rowIdx = 3;
    $sira = 1;
    foreach ($puantajData as $id => $row) {
        $sheet->setCellValue('A' . $rowIdx, $sira++);
        $sheet->setCellValue('B' . $rowIdx, $row['info']['plaka']);
        $sheet->setCellValue('C' . $rowIdx, ($row['info']['marka'] ?? '-') . ' ' . ($row['info']['model'] ?? ''));

        $aylikToplam = 0;
        $dataColIdx = 4;
        for ($i = 1; $i <= $gunSayisi; $i++) {
            $gunData = $row['gunler'][$i] ?? null;
            $yapilan = $gunData ? (float) $gunData['yapilan'] : 0;
            $aylikToplam += $yapilan;

            if ($showKm) {
                if ($gunData) {
                    $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($dataColIdx) . $rowIdx, $gunData['baslangic']);
                    $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($dataColIdx + 1) . $rowIdx, $gunData['bitis']);
                    $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($dataColIdx + 2) . $rowIdx, $yapilan);
                } else {
                    $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($dataColIdx) . $rowIdx, '-');
                    $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($dataColIdx + 1) . $rowIdx, '-');
                    $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($dataColIdx + 2) . $rowIdx, '-');
                }
                $dataColIdx += 3;
            } else {
                $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($dataColIdx) . $rowIdx, $yapilan > 0 ? $yapilan : '-');
                $dataColIdx += 1;
            }
        }

        $lastDataColStr = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($dataColIdx);
        $sheet->setCellValue($lastDataColStr . $rowIdx, $aylikToplam);
        $sheet->getStyle($lastDataColStr . $rowIdx)->getFont()->setBold(true);

        $rowIdx++;
    }

    // Stil ve AutoSize
    foreach (range('A', 'C') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    $sheet->getColumnDimension($lastColStr)->setAutoSize(true);

    // Dosya Adı
    $dosyaAdi = 'arac_puantaj_' . $year . '_' . $month . '.xlsx';

    // HTTP Başlıkları
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $dosyaAdi . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (Exception $e) {
    die('Hata: ' . $e->getMessage());
}
