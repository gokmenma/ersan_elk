<?php
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
$spreadsheet = IOFactory::load('views/hakedisler/Hakedis.xlsx');
foreach ($spreadsheet->getSheetNames() as $sheetName) {
    echo $sheetName . "\n";
}
