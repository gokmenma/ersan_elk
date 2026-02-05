<?php
require_once __DIR__ . '/Autoloader.php';

use App\Model\PersonelHareketleriModel;

header('Content-Type: application/json; charset=utf-8');

$m = new PersonelHareketleriModel();
$r = $m->getTumPersonelDurumu(null);

echo json_encode([
    'count' => count($r),
    'first' => count($r) > 0 ? $r[0] : null
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
