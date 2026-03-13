<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('memory_limit', '1024M');
set_time_limit(0);

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['DOCUMENT_ROOT'] = 'C:/xampp/htdocs/ersan_elk';
session_start();
$_SESSION['firma_id'] = 1;
$_SESSION['user_id'] = 1;
$_SESSION['firma_kodu'] = 17;

$_POST['action'] = 'online-sorgu-sorgula';
$_POST['baslangic_tarihi'] = '01.02.2026';
$_POST['bitis_tarihi'] = '28.02.2026';
$_POST['filter_personel_id'] = 0;
$_POST['filter_ekip_kodu'] = 0;
$_POST['filter_work_type'] = '';
$_POST['filter_work_result'] = '';

echo "Starting debug query for 2026-02-01 to 2026-02-28...\n";
$start = microtime(true);

try {
    include 'views/puantaj/api.php';
} catch (Throwable $e) {
    echo "\nFATAL ERROR: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString();
}

$end = microtime(true);
echo "\n\nExecution took " . ($end - $start) . " seconds.\n";
echo "Peak Memory Usage: " . (memory_get_peak_usage(true) / 1024 / 1024) . " MB\n";
