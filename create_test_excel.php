<?php
require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

$sheet->setCellValue('A1', 'Ekip');
$sheet->setCellValue('B1', 'Sayı');
$sheet->setCellValue('C1', 'Açıklama');

$sheet->setCellValue('A2', 'EMİRHAN EFE ÇİFTÇİOĞLU');
$sheet->setCellValue('B2', '5');
$sheet->setCellValue('C2', 'Test 1');

$writer = new Xlsx($spreadsheet);
$writer->save('test_kacak.xlsx');

echo "test_kacak.xlsx created.\n";
