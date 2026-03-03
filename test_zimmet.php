<?php
require 'c:/xampp/htdocs/ersan_elk/config.php';
$model = new \App\Model\DemirbasZimmetModel();
$request = [
    'start' => 0,
    'length' => 10,
    'search' => ['value' => ''],
    'order' => [['column' => 0, 'dir' => 'desc']]
];
$_SESSION['firma_id'] = 1;

try {
    $result = $model->getDatatableList($request);
    echo "SUCCESS\n";
    echo "Count: " . count($result['data']) . "\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
