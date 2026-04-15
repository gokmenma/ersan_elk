<?php
session_start();
$_SESSION['firma_id'] = 1; // Assuming 1 for testing
require_once 'Autoloader.php';
$KmBildirim = new \App\Model\AracKmBildirimModel();
$yesterday = date('Y-m-d', strtotime('-1 day'));
try {
    $res = $KmBildirim->getUnreported($yesterday, 'sabah');
    print_r($res);
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
