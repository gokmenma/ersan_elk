<?php
require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$templatePath = __DIR__ . '/views/hakedisler/Hakedis.xlsx';
try {
    $spreadsheet = IOFactory::load($templatePath);
    $sheet = $spreadsheet->getSheetByName('Ön Kapak');
    echo "Ön Kapak Row 29 Details:\n";
    foreach (range('A', 'L') as $col) {
        $val = $sheet->getCell($col . "29")->getValue();
        echo "$col: $val\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
