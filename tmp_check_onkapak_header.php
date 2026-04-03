<?php
require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$templatePath = __DIR__ . '/views/hakedisler/Hakedis.xlsx';
try {
    $spreadsheet = IOFactory::load($templatePath);
    $sheet = $spreadsheet->getSheetByName('Ön Kapak');
    echo "Ön Kapak Row 10-14 Details:\n";
    foreach (range(10, 14) as $row) {
        foreach (range('A', 'I') as $col) {
            $val = $sheet->getCell($col . $row)->getValue();
            if ($val) echo "$col" . "$row: $val\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
