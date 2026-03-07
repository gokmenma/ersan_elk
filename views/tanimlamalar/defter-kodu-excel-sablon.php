<?php

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Model\TanimlamalarModel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

$Tanimlamalar = new TanimlamalarModel();
$defterKodlari = $Tanimlamalar->getByGrup('defter_kodu');

// Excel oluştur
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Defter Kodları');

// Başlık stilleri (Siyah arka plan, Sarı yazı gibi resimdeki renklere uygun)
$headerStyle = [
    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'FFFF00'], // Sarı
        'size' => 12
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '000000'] // Siyah
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => 'FFFFFF']
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
$sheet->setCellValue('A1', 'DEFTER');
$sheet->setCellValue('B1', 'BÖLGESİ');
$sheet->setCellValue('C1', 'DEFTER MAHALLE');
$sheet->setCellValue('D1', 'ABONE SAYISI');
$sheet->setCellValue('E1', 'Başlangıç Tarihi');
$sheet->setCellValue('F1', 'Bitiş Tarihi');
$sheet->setCellValue('G1', 'AÇIKLAMA');

// Başlık stili uygula
$sheet->getStyle('A1:G1')->applyFromArray($headerStyle);
$sheet->getRowDimension(1)->setRowHeight(30);

// Sütun genişlikleri
$sheet->getColumnDimension('A')->setWidth(15);
$sheet->getColumnDimension('B')->setWidth(20);
$sheet->getColumnDimension('C')->setWidth(30);
$sheet->getColumnDimension('D')->setWidth(15);
$sheet->getColumnDimension('E')->setWidth(20);
$sheet->getColumnDimension('F')->setWidth(20);
$sheet->getColumnDimension('G')->setWidth(40);

// Verileri ekle
$row = 2;
foreach ($defterKodlari as $defter) {
    if ($defter->baslangic_tarihi) {
        $basTarih = date("d.m.Y", strtotime($defter->baslangic_tarihi));
    } else {
        $basTarih = '';
    }

    if ($defter->bitis_tarihi) {
        $bitTarih = date("d.m.Y", strtotime($defter->bitis_tarihi));
    } else {
        $bitTarih = '';
    }

    $sheet->setCellValue('A' . $row, $defter->tur_adi);
    $sheet->setCellValue('B' . $row, $defter->defter_bolge ?? '');
    $sheet->setCellValue('C' . $row, $defter->defter_mahalle ?? '');
    $sheet->setCellValue('D' . $row, $defter->defter_abone_sayisi ?? '');
    $sheet->setCellValue('E' . $row, $basTarih);
    $sheet->setCellValue('F' . $row, $bitTarih);
    $sheet->setCellValue('G' . $row, $defter->aciklama ?? '');

    $sheet->getStyle('A' . $row . ':G' . $row)->applyFromArray($dataStyle);
    $row++;
}

// Eğer hiç kayıt yoksa örnek satır ekle
if (count($defterKodlari) == 0) {
    $sheet->setCellValue('A2', '10');
    $sheet->setCellValue('B2', 'AFŞİN');
    $sheet->setCellValue('C2', 'BEYCEĞİZ MH.');
    $sheet->setCellValue('D2', '2.499');
    $sheet->setCellValue('E2', date('d.m.Y'));
    $sheet->setCellValue('F2', '');
    $sheet->setCellValue('G2', 'Örnek açıklama');
    $sheet->getStyle('A2:G2')->applyFromArray($dataStyle);
    $sheet->getStyle('A2:G2')->getFont()->setItalic(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('999999'));
}

// Dosyayı indir
$filename = 'defter_kodlari_sablon_' . date('Y-m-d_H-i-s') . '.xlsx';

if (ob_get_length()) {
    ob_end_clean();
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
