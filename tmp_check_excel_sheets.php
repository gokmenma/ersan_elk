<?php
require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$templatePath = __DIR__ . '/views/hakedisler/Hakedis.xlsx';
if (!file_exists($templatePath)) {
    die("File not found: " . $templatePath);
}

try {
    $spreadsheet = IOFactory::load($templatePath);
    echo "Sheet Names:\n";
    foreach ($spreadsheet->getSheetNames() as $index => $name) {
        echo "$index: $name\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
