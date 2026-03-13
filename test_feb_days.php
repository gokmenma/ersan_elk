<?php
require_once 'vendor/autoload.php';
require_once 'Autoloader.php';
use App\Service\KesmeAcmaService;

$svc = new KesmeAcmaService();
for ($d = 1; $d <= 28; $d++) {
    $date = sprintf('%02d/02/2026', $d);
    echo "Testing $date... ";
    try {
        $res = $svc->getData($date, $date, 17, 17, 10, 0);
        echo "OK (" . (count($res['data']['data'] ?? [])) . " records)\n";
    } catch (Exception $e) {
        echo "FAILED: " . $e->getMessage() . "\n";
    }
}
