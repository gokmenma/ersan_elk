<?php
session_start();
require_once __DIR__ . '/Autoloader.php';
use App\Model\DemirbasModel;

$_SESSION['id'] = 1;
$_SESSION['firma_id'] = 1;

$Demirbas = new DemirbasModel();
$valid_seri = '25926511';

$request = [
    'start' => 0, 
    'length' => 10, 
    'order' => [['column' => '1', 'dir' => 'desc']], 
    'search' => ['value' => ''],
    'columns' => [
        0 => ['search' => ['value' => '']],
        1 => ['search' => ['value' => '']],
        2 => ['search' => ['value' => '']],
        3 => ['search' => ['value' => '']],
        4 => ['search' => ['value' => '']],
        5 => ['search' => ['value' => $valid_seri]],
        6 => ['search' => ['value' => '']],
        7 => ['search' => ['value' => '']],
        8 => ['search' => ['value' => '']],
        9 => ['search' => ['value' => '']],
    ]
];

try {
    $result = $Demirbas->getDatatableList($request, 'sayac');
    echo "Total: " . $result['recordsTotal'] . "\n";
    echo "Filtered: " . $result['recordsFiltered'] . "\n";
    echo "Data count: " . count($result['data']) . "\n";
    if (count($result['data']) > 0) {
        echo "Found Seri: " . $result['data'][0]->seri_no . "\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
