<?php
require 'c:/xampp/htdocs/ersan_elk/vendor/autoload.php';
$inputFileName = 'c:/xampp/htdocs/ersan_elk/views/hakedisler/Hakedis.xlsx';
if (!file_exists($inputFileName)) {
    die("File not found");
}
$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($inputFileName);

$sheetsToDump = ['Bilgiler', 'Çarşaf'];

foreach ($sheetsToDump as $sheetName) {
    echo "=== Sheet: $sheetName ===\n";
    $worksheet = $spreadsheet->getSheetByName($sheetName);
    if ($worksheet) {
        $highestRow = $worksheet->getHighestRow();
        if ($highestRow > 100) $highestRow = 100; // Limit to 100 lines
        
        foreach ($worksheet->getRowIterator(1, $highestRow) as $row) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(TRUE);
            foreach ($cellIterator as $cell) {
                $val = $cell->getValue();
                if ($val !== null && $val !== '') {
                    echo $cell->getCoordinate() . ': ' . $val . "\n";
                }
            }
        }
    } else {
        echo "Sheet not found.\n";
    }
}
