<?php
require_once 'Autoloader.php';
use App\Helper\Helper;

$testValues = [
    '3.000',
    '₺3.000',
    '₺ 3.000',
    '3.000,00',
    '3,000.00',
    '3000',
    '0',
    '',
    '3.500',
    '3.50'
];

foreach ($testValues as $val) {
    echo "Input: '$val' -> Output: '" . Helper::formattedMoneyToNumber($val) . "'\n";
}
