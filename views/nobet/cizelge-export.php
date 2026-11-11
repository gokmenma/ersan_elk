<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/Autoloader.php';
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Helper\Security;
use App\Model\NobetModel;
use App\Model\SystemLogModel;
use App\Service\Gate;

if (!Gate::allows('nobet/list')) {
    http_response_code(403);
    exit('Bu işlem için yetkiniz bulunmamaktadır.');
}

$ay = (string) ($_GET['ay'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $ay)) {
    http_response_code(400);
    exit('Geçersiz ay.');
}
$format = ($_GET['format'] ?? 'xlsx') === 'ics' ? 'ics' : 'xlsx';

$personelFiltre = null;
if (!empty($_GET['personel'])) {
    $personelFiltre = (int) Security::decrypt((string) $_GET['personel']);
    if ($personelFiltre <= 0) {
        http_response_code(400);
        exit('Geçersiz personel.');
    }
}

$bas = $ay . '-01';
$bit = date('Y-m-t', strtotime($bas));
$gunSayisi = (int) date('t', strtotime($bas));

try {
    $Nobet = new NobetModel();
    $nobetler = array_values(array_filter(
        $Nobet->getAralikNobetleri($bas, $bit),
        fn($n) => $personelFiltre === null || (int) $n->personel_id === $personelFiltre
    ));
} catch (\Throwable $e) {
    error_log('Nöbet çizelge dışa aktarma hatası: ' . $e->getMessage());
    http_response_code(500);
    exit('Çizelge oluşturulamadı.');
}

$tipKod = ['standart' => 'N', 'hafta_sonu' => 'HS', 'resmi_tatil' => 'RT', 'ozel' => 'Ö'];

(new SystemLogModel())->logAction(
    (int) ($_SESSION['user_id'] ?? 0),
    'Nöbet Dışa Aktarma',
    strtoupper($format) . " nöbet çizelgesi dışa aktarıldı ({$ay})."
);

if ($format === 'ics') {
    $icsKacis = static fn(string $t): string => str_replace(["\\", ";", ",", "\r", "\n"], ["\\\\", "\\;", "\\,", '', '\\n'], $t);
    $satirlar = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//Ersan ELK//Nobet Cizelgesi//TR',
        'CALSCALE:GREGORIAN',
        'METHOD:PUBLISH',
        'X-WR-CALNAME:Nöbet Çizelgesi ' . $ay,
    ];
    foreach ($nobetler as $n) {
        $gun = date('Ymd', strtotime($n->nobet_tarihi));
        $sonGun = date('Ymd', strtotime($n->nobet_tarihi . ' +1 day'));
        $satirlar[] = 'BEGIN:VEVENT';
        $satirlar[] = 'UID:nobet-' . (int) $n->id . '@ersan-elk';
        $satirlar[] = 'DTSTAMP:' . gmdate('Ymd\THis\Z');
        $satirlar[] = 'DTSTART;VALUE=DATE:' . $gun;
        $satirlar[] = 'DTEND;VALUE=DATE:' . $sonGun;
        $satirlar[] = 'SUMMARY:' . $icsKacis('Nöbet - ' . $n->adi_soyadi);
        $satirlar[] = 'DESCRIPTION:' . $icsKacis('Saat: ' . substr((string) $n->baslangic_saati, 0, 5) . ' - ' . substr((string) $n->bitis_saati, 0, 5) . ' | Tip: ' . ($tipKod[$n->nobet_tipi] ?? 'N'));
        $satirlar[] = 'END:VEVENT';
    }
    $satirlar[] = 'END:VCALENDAR';

    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="nobet-cizelgesi-' . $ay . '.ics"');
    echo implode("\r\n", $satirlar) . "\r\n";
    exit;
}

$kisiler = [];
$gunToplam = array_fill(1, $gunSayisi, 0);
foreach ($nobetler as $n) {
    $pid = (int) $n->personel_id;
    $g = (int) date('j', strtotime($n->nobet_tarihi));
    if (!isset($kisiler[$pid])) {
        $kisiler[$pid] = ['ad' => (string) $n->adi_soyadi, 'departman' => (string) $n->departman, 'gunler' => [], 'toplam' => 0];
    }
    $kisiler[$pid]['gunler'][$g] = $tipKod[$n->nobet_tipi] ?? 'N';
    $kisiler[$pid]['toplam']++;
    $gunToplam[$g]++;
}
uasort($kisiler, fn($a, $b) => strcmp($a['ad'], $b['ad']));

$ss = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
$sheet = $ss->getActiveSheet();
$sheet->setTitle('Nöbet Çizelgesi');
$sheet->setCellValueExplicit('A1', 'Nöbet Çizelgesi - ' . date('m.Y', strtotime($bas)), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

$sutun = static fn(int $i): string => \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
$gunAdlari = ['', 'Pzt', 'Sal', 'Çar', 'Per', 'Cum', 'Cmt', 'Paz'];

$sheet->setCellValue('A3', 'Personel');
$sheet->setCellValue('B3', 'Departman');
for ($g = 1; $g <= $gunSayisi; $g++) {
    $tarih = sprintf('%s-%02d', $ay, $g);
    $sheet->setCellValue($sutun($g + 2) . '3', $g . "\n" . $gunAdlari[(int) date('N', strtotime($tarih))]);
}
$toplamSutun = $sutun($gunSayisi + 3);
$sheet->setCellValue($toplamSutun . '3', 'Toplam');

$satir = 4;
foreach ($kisiler as $k) {
    $sheet->setCellValueExplicit('A' . $satir, $k['ad'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $sheet->setCellValueExplicit('B' . $satir, $k['departman'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    foreach ($k['gunler'] as $g => $kod) {
        $sheet->setCellValue($sutun($g + 2) . $satir, $kod);
    }
    $sheet->setCellValue($toplamSutun . $satir, $k['toplam']);
    $satir++;
}

$sheet->setCellValue('A' . $satir, 'Günlük Nöbetçi Sayısı');
for ($g = 1; $g <= $gunSayisi; $g++) {
    $sheet->setCellValue($sutun($g + 2) . $satir, $gunToplam[$g]);
}
$sheet->setCellValue($toplamSutun . $satir, array_sum($gunToplam));
$sheet->getStyle('A' . $satir . ':' . $toplamSutun . $satir)->getFont()->setBold(true);

$sonSatir = $satir;
$aralik = 'A3:' . $toplamSutun . $sonSatir;
$sheet->getStyle($aralik)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
$sheet->getStyle('A3:' . $toplamSutun . '3')->applyFromArray([
    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
    'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF4B39B9']],
    'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER, 'wrapText' => true],
]);
$sheet->getStyle('C4:' . $toplamSutun . $sonSatir)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
for ($g = 1; $g <= $gunSayisi; $g++) {
    $tarih = sprintf('%s-%02d', $ay, $g);
    if ((int) date('N', strtotime($tarih)) >= 6) {
        $sheet->getStyle($sutun($g + 2) . '4:' . $sutun($g + 2) . $sonSatir)->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFF1F5F9');
    }
    $sheet->getColumnDimension($sutun($g + 2))->setWidth(5);
}
$sheet->getColumnDimension('A')->setWidth(30);
$sheet->getColumnDimension('B')->setWidth(22);
$sheet->getColumnDimension($toplamSutun)->setWidth(9);
$sheet->getRowDimension(3)->setRowHeight(32);
$sheet->freezePane('C4');
$sheet->setCellValue('A' . ($sonSatir + 2), 'N: Standart   HS: Hafta Sonu   RT: Resmi Tatil   Ö: Özel');

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="nobet-cizelgesi-' . $ay . '.xlsx"');
header('Cache-Control: max-age=0');
(new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($ss))->save('php://output');
exit;
