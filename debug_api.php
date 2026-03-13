<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['DOCUMENT_ROOT'] = 'C:/xampp/htdocs/ersan_elk';

$_SESSION['firma_id'] = 1; 
$_SESSION['user_id'] = 1;

$_GET['action'] = 'puantaj-datatable';
$_GET['start_date'] = date('Y-m-d');
$_GET['end_date'] = date('Y-m-d');

try {
    include 'views/puantaj/api.php';
} catch (Throwable $e) {
    echo "\nFATAL ERROR: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString();
}
