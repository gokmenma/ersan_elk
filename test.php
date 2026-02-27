<?php
session_start();
require_once __DIR__ . '/Autoloader.php';

use App\Model\DemirbasModel;

$_SESSION['id'] = 1;
$_SESSION['firma_id'] = 1; // Assuming a valid firma_id

$Demirbas = new DemirbasModel();

try {
    $result = $Demirbas->getDatatableList(['start' => 0, 'length' => 10, 'order' => [['column' => '0', 'dir' => 'desc']], 'search' => ['value' => '']], 'demirbas');
    echo json_encode($result);
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
