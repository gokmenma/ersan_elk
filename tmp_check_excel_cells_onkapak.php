<?php
require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$templatePath = __DIR__ . '/views/hakedisler/Hakedis.xlsx';
try {
    $spreadsheet = IOFactory::load($templatePath);
    $sheet = $spreadsheet->getSheetByName('Ön Kapak');
    echo "Ön Kapak Sheet Content:\n";
    foreach (range(1, 40) as $row) {
        $a = $sheet->getCell("A$row")->getValue();
        $b = $sheet->getCell("B$row")->getValue();
        $c = $sheet->getCell("C$row")->getValue();
        $g = $sheet->getCell("G$row")->getValue();
        $h = $sheet->getCell("H$row")->getValue();
        if ($a || $b || $c || $g || $h) {
            echo "Row $row -> A: $a | B: $b | C: $c | G: $g | H: $h\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
