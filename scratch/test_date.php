<?php
require_once 'Autoloader.php';
use App\Helper\Date;

$testDate = '09.04.2026';
$result = Date::convertExcelDate($testDate);

echo "Input: $testDate\n";
echo "Result: $result\n";
