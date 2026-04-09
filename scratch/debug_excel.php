<?php
require_once 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

$file = 'km_kaydi_sablonu_20260409.xlsx';
if (!file_exists($file)) exit;

$spreadsheet = IOFactory::load($file);
$sheet = $spreadsheet->getActiveSheet();
$rows = $sheet->toArray(null, true, true, false);

$out = fopen('scratch/excel_rows.txt', 'w');
foreach ($rows as $row) {
    fputcsv($out, $row);
}
fclose($out);
echo "Dumped to scratch/excel_rows.txt\n";
