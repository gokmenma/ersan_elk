<?php

if (php_sapi_name() !== 'cli') {
    header('HTTP/1.0 403 Forbidden');
    exit('Bu script sadece CLI uzerinden calisabilir.');
}

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
set_time_limit(0);
date_default_timezone_set('Europe/Istanbul');

require_once dirname(__DIR__) . '/Autoloader.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

use App\Service\VeritabaniYedekService;

$ayar     = VeritabaniYedekService::ayarlar();
$logDizin = __DIR__ . '/logs';
$logDosya = $logDizin . '/backup.log';

$secenekler = getopt('', ['tam', 'artimli', 'tablo:', 'sessiz', 'mailsiz', 'drivesiz']);
$sessiz     = isset($secenekler['sessiz']);
if (isset($secenekler['mailsiz'])) {
    $ayar['mail_gonder'] = false;
}

$logSatirlari = '';

function yaz(string $mesaj, string $seviye = 'INFO'): void
{
    global $logDosya, $logSatirlari, $sessiz;
    $satir = '[' . date('Y-m-d H:i:s') . "] [$seviye] $mesaj" . PHP_EOL;
    @file_put_contents($logDosya, $satir, FILE_APPEND | LOCK_EX);
    $logSatirlari .= $satir;
    if (!$sessiz) {
        echo $satir;
    }
}

if (!is_dir($logDizin)) {
    @mkdir($logDizin, 0750, true);
}

$servis = new VeritabaniYedekService($ayar);
try {
    $kilit = $servis->kilitAl();
} catch (Throwable $e) {
    error_log('[db_backup] ' . $e->getMessage());
    yaz('Yedekleme baslatilamadi: ' . $e->getMessage(), 'ERROR');
    $servis->raporMailiGonder(true, $logSatirlari);
    exit(1);
}

if ($kilit === null) {
    yaz('Baska bir yedekleme/geri yukleme islemi suruyor, bu calisma atlandi.', 'WARNING');
    exit(0);
}

try {
    $sonuc = $servis->yedekAl([
        'tam'     => isset($secenekler['tam']),
        'artimli' => isset($secenekler['artimli']),
        'tablo'   => !empty($secenekler['tablo']) ? array_map('trim', explode(',', $secenekler['tablo'])) : [],
        'drive'   => !isset($secenekler['drivesiz']),
    ], 'yaz');
} finally {
    $servis->kilitBirak($kilit);
}

$servis->raporMailiGonder(!$sonuc['basarili'], $logSatirlari);

exit($sonuc['basarili'] ? 0 : 1);
