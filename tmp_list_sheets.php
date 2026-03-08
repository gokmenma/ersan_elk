<?php
require 'c:/xampp/htdocs/ersan_elk/vendor/autoload.php';
$inputFileName = 'c:/xampp/htdocs/ersan_elk/views/hakedisler/Hakedis.xlsx';
if (!file_exists($inputFileName)) {
    die("File not found");
}
$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($inputFileName);
foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
    echo 'Sheet: ' . $worksheet->getTitle() . "\n";
}
