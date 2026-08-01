<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/Autoloader.php';

if (empty($_SESSION['firma_id'])) {
    http_response_code(401);
    exit('Excel aktarımı için lütfen sisteme giriş yapın.');
}

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

// ID sütunu geri yüklemede doğru kaydın güncellenmesi için kullanılır.
$headers = [
    'ID',
    'İş Türü',
    'İş Emri Sonucu',
    'İş Türü Ücreti',
    'Araçlı Personel İş Türü Ücreti',
    'Okuma Personeli İş Türü Ücreti',
    'Rapor Sekmesi',
    'Açıklama'
];
foreach ($headers as $columnIndex => $header) {
    $column = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnIndex + 1);
    $sheet->setCellValue($column . '1', $header);
}

// Başlık stili uygula
$sheet->getStyle('A1:H1')->applyFromArray($headerStyle);
$sheet->getRowDimension(1)->setRowHeight(25);

// Sütun genişlikleri
$sheet->getColumnDimension('A')->setWidth(12);
$sheet->getColumnDimension('B')->setWidth(30);
$sheet->getColumnDimension('C')->setWidth(30);
$sheet->getColumnDimension('D')->setWidth(20);
$sheet->getColumnDimension('E')->setWidth(28);
$sheet->getColumnDimension('F')->setWidth(28);
$sheet->getColumnDimension('G')->setWidth(20);
$sheet->getColumnDimension('H')->setWidth(40);

// Verileri ekle
$row = 2;
foreach ($isTurleri as $isTuru) {
    $sheet->setCellValue('A' . $row, $isTuru->id);
    $sheet->setCellValue('B' . $row, $isTuru->tur_adi);
    $sheet->setCellValue('C' . $row, $isTuru->is_emri_sonucu ?? '');
    $sheet->setCellValue('D' . $row, (float) ($isTuru->is_turu_ucret ?? 0));
    $sheet->setCellValue('E' . $row, (float) ($isTuru->aracli_personel_is_turu_ucret ?? 0));
    $sheet->setCellValue('F' . $row, (float) ($isTuru->okuma_is_turu_ucret ?? 0));
    $sheet->setCellValue('G' . $row, $isTuru->rapor_sekmesi ?? '');
    $sheet->setCellValue('H' . $row, $isTuru->aciklama ?? '');

    $sheet->getStyle('A' . $row . ':H' . $row)->applyFromArray($dataStyle);
    $sheet->getStyle('D' . $row . ':F' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
    $row++;
}

// Eğer hiç kayıt yoksa örnek satır ekle
if (count($isTurleri) == 0) {
    $sheet->setCellValue('B2', 'Örnek İş Türü');
    $sheet->setCellValue('C2', 'Örnek İş Emri Sonucu');
    $sheet->setCellValue('D2', 100);
    $sheet->setCellValue('E2', 120);
    $sheet->setCellValue('F2', 90);
    $sheet->setCellValue('G2', 'Kesme');
    $sheet->setCellValue('H2', 'Örnek açıklama');
    $sheet->getStyle('A2:H2')->applyFromArray($dataStyle);
    $sheet->getStyle('A2:H2')->getFont()->setItalic(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('999999'));
}

// Dosyayı indir
$filename = 'is_turleri_' . date('Y-m-d_H-i-s') . '.xlsx';

if (ob_get_length()) {
    ob_end_clean();
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
