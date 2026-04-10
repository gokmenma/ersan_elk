<?php
/**
 * Araç Takip Özel Puantaj Cetveli Excel Export
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Model\AracKmModel;
use App\Helper\Date;
use App\Helper\Security;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

$year = intval($_GET['year'] ?? date('Y'));
$month = str_pad($_GET['month'] ?? date('m'), 2, '0', STR_PAD_LEFT);
$arac_id = intval(Security::decrypt($_GET['id'] ?? '0'));

if ($arac_id <= 0) {
    die('Geçersiz araç ID.');
}

try {
    $Km = new AracKmModel();
    $data = $Km->getSingleVehicleMonthlyPuantaj($year, $month, $arac_id);

    if (!$data) {
        die('Kayıt bulunamadı.');
    }

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Araç Puantaj Cetveli');

    $monthName = Date::monthName($month);

    // Başlık
    $sheet->setCellValue('A1', $year . ' ' . mb_strtoupper($monthName, 'UTF-8') . ' AYI ARAÇ PUANTAJ CETVELİ');
    $sheet->mergeCells('A1:E1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Bilgi Satırı
    $sheet->setCellValue('A3', 'PLAKA:');
    $sheet->setCellValue('B3', $data['info']->plaka);
    $sheet->setCellValue('C3', 'ŞOFÖR:');
    $sheet->setCellValue('D3', $data['info']->sofor_adi ?? 'Zimmetli Personel Yok');
    $sheet->setCellValue('A4', 'ARAÇ:');
    $sheet->setCellValue('B4', ($data['info']->marka ?? '') . ' ' . ($data['info']->model ?? ''));
    $sheet->mergeCells('B4:D4');

    $infoStyle = [
        'font' => ['bold' => true],
        'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN]]
    ];
    $sheet->getStyle('A3:D4')->applyFromArray($infoStyle);

    // Tablo Başlıkları
    $rowCount = 6;
    $sheet->setCellValue('A' . $rowCount, 'TARİH');
    $sheet->setCellValue('B' . $rowCount, 'BAŞLANGIÇ KM');
    $sheet->setCellValue('C' . $rowCount, 'BİTİŞ KM');
    $sheet->setCellValue('D' . $rowCount, 'TOPLAM KM');
    $sheet->setCellValue('E' . $rowCount, 'İMZA');

    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '333333']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ];
    $sheet->getStyle('A6:E6')->applyFromArray($headerStyle);

    // Veriler
    $rowCount++;
    $genelToplam = 0;
    for ($i = 1; $i <= $data['gunSayisi']; $i++) {
        $gunData = $data['gunler'][$i] ?? null;
        $yapilan = $gunData ? (float) $gunData['yapilan'] : 0;
        if ($yapilan < 0) $yapilan = 0;
        $genelToplam += $yapilan;
        $tarih = str_pad($i, 2, '0', STR_PAD_LEFT) . '.' . $month . '.' . $year;

        $sheet->setCellValue('A' . $rowCount, $tarih);
        $sheet->setCellValue('B' . $rowCount, $gunData ? $gunData['baslangic'] : '-');
        $sheet->setCellValue('C' . $rowCount, $gunData ? $gunData['bitis'] : '-');
        $sheet->setCellValue('D' . $rowCount, $yapilan > 0 ? $yapilan : '-');
        $sheet->setCellValue('E' . $rowCount, '');

        $sheet->getStyle('A' . $rowCount . ':E' . $rowCount)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('A' . $rowCount . ':D' . $rowCount)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        if ($yapilan > 0) {
            $sheet->getStyle('D' . $rowCount)->getFont()->setBold(true)->getColor()->setRGB('0062CC');
        }

        $rowCount++;
    }

    // Alt Toplam
    $sheet->setCellValue('A' . $rowCount, 'GENEL TOPLAM:');
    $sheet->mergeCells('A' . $rowCount . ':C' . $rowCount);
    $sheet->setCellValue('D' . $rowCount, $genelToplam . ' KM');

    $footerStyle = [
        'font' => ['bold' => true],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8F9FA']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ];
    $sheet->getStyle('A' . $rowCount . ':E' . $rowCount)->applyFromArray($footerStyle);
    $sheet->getStyle('A' . $rowCount)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

    // AutoSize
    foreach (range('A', 'E') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Dosya Adı
    $dosyaAdi = 'arac_cetvel_' . $data['info']->plaka . '_' . $year . '_' . $month . '.xlsx';

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
