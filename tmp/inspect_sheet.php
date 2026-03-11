<?php
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
$spreadsheet = IOFactory::load('views/hakedisler/Hakedis.xlsx');
$sheet = $spreadsheet->getSheetByName('Hakediş Dengeleme Cetveli');
for ($row = 1; $row <= 6; $row++) {
    echo "Row $row: ";
    for ($col = 'A'; $col <= 'K'; $col++) {
        $val = $sheet->getCell($col . $row)->getValue();
        if ($val) echo "$col: $val | ";
    }
    echo "\n";
}
