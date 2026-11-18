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

Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

use App\Service\DosyaYedekService;
use App\Service\VeritabaniYedekService;

$ayar = VeritabaniYedekService::ayarlar();
$logDizin = __DIR__ . '/logs';
$logDosya = $logDizin . '/dosya_yedek.log';
if (!is_dir($logDizin)) {
    @mkdir($logDizin, 0750, true);
}

$secenekler = getopt('', ['tam', 'artimli', 'sessiz', 'mailsiz', 'drivesiz', 'dogrula:', 'liste']);
$sessiz = isset($secenekler['sessiz']);
if (isset($secenekler['mailsiz'])) {
    $ayar['mail_gonder'] = false;
}

$logSatirlari = '';
$yaz = function (string $mesaj, string $seviye = 'INFO') use ($logDosya, &$logSatirlari, $sessiz): void {
    $satir = '[' . date('Y-m-d H:i:s') . "] [$seviye] $mesaj" . PHP_EOL;
    @file_put_contents($logDosya, $satir, FILE_APPEND | LOCK_EX);
    $logSatirlari .= $satir;
    if (!$sessiz) {
        echo $satir;
    }
};

$servis = new DosyaYedekService($ayar);

if (isset($secenekler['liste'])) {
    foreach ($servis->yedekler() as $y) {
        printf("%-8s %s  %10s MB  %8s dosya  %s\n", strtoupper($y['tip']), $y['zaman'],
            number_format($y['boyut'] / 1048576, 2), $y['meta']['dosya_adet'] ?? '-', $y['ad']);
    }
    exit(0);
}

if (!empty($secenekler['dogrula'])) {
    $sonuc = $servis->dogrula(basename((string) $secenekler['dogrula']), $yaz);
    exit($sonuc['sonuc'] === 'basarili' ? 0 : 1);
}

$sonuc = $servis->yedekAl([
    'tam' => isset($secenekler['tam']),
    'artimli' => isset($secenekler['artimli']),
    'drive' => !isset($secenekler['drivesiz']),
], $yaz);

if (empty($sonuc['atlandi'])) {
    $servis->raporMailiGonder(!$sonuc['basarili'], $logSatirlari);
}

exit($sonuc['basarili'] ? 0 : 1);
