<?php
require_once 'vendor/autoload.php';
require_once 'Autoloader.php';
use App\Service\KesmeAcmaService;

$svc = new KesmeAcmaService();
$res = $svc->getData('01/02/2026', '02/02/2026', 17, 17, 10, 0);
echo json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
