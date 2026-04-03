<?php
require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$templatePath = __DIR__ . '/views/hakedisler/Hakedis.xlsx';
try {
    $spreadsheet = IOFactory::load($templatePath);
    $sheet = $spreadsheet->getSheetByName('Bilgiler');
    echo "Bilgiler Sheet Content:\n";
    foreach (range(1, 40) as $row) {
        $c = $sheet->getCell("C$row")->getValue();
        $d = $sheet->getCell("D$row")->getValue();
        $h = $sheet->getCell("H$row")->getValue();
        if ($c || $d || $h) {
            echo "Row $row -> C: $c | D: $d | H: $h\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
