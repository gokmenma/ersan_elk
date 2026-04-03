<?php
require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$templatePath = __DIR__ . '/views/hakedisler/Hakedis.xlsx';
try {
    $spreadsheet = IOFactory::load($templatePath);
    foreach ($spreadsheet->getSheetNames() as $sheetName) {
        $sheet = $spreadsheet->getSheet($spreadsheet->getIndex($spreadsheet->getSheetByName($sheetName)));
        foreach ($sheet->getRowIterator() as $row) {
            foreach ($row->getCellIterator() as $cell) {
                try {
                    $formula = $cell->getValue();
                    if (is_string($formula) && strpos($formula, 'TEXT(') !== false && (strpos($formula, 'gg.aa') !== false || strpos($formula, 'GG.AA') !== false)) {
                        echo "Sheet: $sheetName | Cell: " . $cell->getCoordinate() . " | Formula: $formula\n";
                    }
                } catch (\Exception $e) {}
            }
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
