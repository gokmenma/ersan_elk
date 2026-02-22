<?php
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

$inputFileName = 'c:/xampp/htdocs/ersan_elk/views/hakedis/Hakediş 4.xlsx';

try {
    $spreadsheet = IOFactory::load($inputFileName);
    $sheets = $spreadsheet->getAllSheets();

    foreach ($sheets as $sheet) {
        $sheetName = $sheet->getTitle();
        echo "====================================\n";
        echo "Sheet: $sheetName\n";
        echo "====================================\n";

        $highestRow = min(30, $sheet->getHighestRow());
        $highestColIndex = Coordinate::columnIndexFromString($sheet->getHighestColumn());

        for ($row = 1; $row <= $highestRow; $row++) {
            $rowData = [];
            for ($col = 1; $col <= $highestColIndex; $col++) {
                $colStr = Coordinate::stringFromColumnIndex($col);
                $cellValue = $sheet->getCell($colStr . $row)->getCalculatedValue();
                if ($cellValue !== null && $cellValue !== '') {
                    $rowData[] = $cellValue;
                }
            }
            if (!empty($rowData)) {
                echo "Row $row: " . implode(" | ", $rowData) . "\n";
            }
        }
        echo "\n";
    }
} catch (\Exception $e) {
    die('Error: ' . $e->getMessage());
}
