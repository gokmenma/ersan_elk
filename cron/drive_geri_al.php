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

use App\Model\SystemLogModel;
use App\Service\DriveYedekSenkronService;

$ayar     = require __DIR__ . '/yedekleme_ayar.php';
$logDizin = __DIR__ . '/logs';
$logDosya = $logDizin . '/drive.log';
if (!is_dir($logDizin)) {
    @mkdir($logDizin, 0750, true);
}

function yaz(string $mesaj, string $seviye = 'INFO'): void
{
    global $logDosya;
    $satir = '[' . date('Y-m-d H:i:s') . "] [$seviye] $mesaj" . PHP_EOL;
    @file_put_contents($logDosya, $satir, FILE_APPEND | LOCK_EX);
    echo $satir;
}

function kullanim(): void
{
    echo <<<TXT

Kullanim:
  php cron/drive_geri_al.php --liste [--vt=ad]
  php cron/drive_geri_al.php --zaman="2026-08-02 03:00:00" [--vt=ad] [--hedef-dizin=/yol]
  php cron/drive_geri_al.php --dosya=tam_vt_2026-08-02_03-00-00.sql.gz [--vt=ad] [--hedef-dizin=/yol]

Drive'daki sifreli yedekleri indirir, dogrular ve yerel yedek dizinine acar.
Veritabanina yazmaz; geri yukleme icin ardindan cron/db_restore.php kullanilir.

TXT;
}

$secenekler = getopt('', ['liste', 'zaman:', 'dosya:', 'vt:', 'hedef-dizin:']);
if (!isset($secenekler['liste']) && empty($secenekler['zaman']) && empty($secenekler['dosya'])) {
    kullanim();
    exit(1);
}

try {
    $vt = (string) ($secenekler['vt'] ?? ($_ENV['DB_NAME'] ?? ''));
    if ($vt === '') {
        throw new RuntimeException('Veritabani adi belirlenemedi (--vt veya .env DB_NAME).');
    }

    $hedefDizin = rtrim((string) ($secenekler['hedef-dizin'] ?? $ayar['yedek_dizini']), '/');
    if (!is_dir($hedefDizin) && !@mkdir($hedefDizin, 0750, true)) {
        throw new RuntimeException('Hedef dizin olusturulamadi: ' . $hedefDizin);
    }

    $servis = new DriveYedekSenkronService();
    $uzak = $servis->uzakYedekler($vt);

    if (isset($secenekler['liste'])) {
        if ($uzak === []) {
            yaz("Drive'da $vt icin yedek bulunamadi.", 'WARNING');
            exit(0);
        }
        foreach ($uzak as $dosya) {
            printf("%-8s %s  %10s MB  %s\n", strtoupper($dosya['tip']), $dosya['zaman'],
                number_format(((int) ($dosya['size'] ?? 0)) / 1048576, 2), $dosya['name']);
        }
        exit(0);
    }

    if (!empty($secenekler['dosya'])) {
        $istenen = preg_replace('/\.enc$/', '', basename($secenekler['dosya'])) . '.enc';
        $secilen = array_values(array_filter($uzak, static fn(array $d): bool => $d['name'] === $istenen));
        if ($secilen === []) {
            throw new RuntimeException("Drive'da dosya bulunamadi: $istenen");
        }
    } else {
        $zaman = date('Y-m-d H:i:s', strtotime($secenekler['zaman']) ?: time());
        $adaylar = array_values(array_filter($uzak, static fn(array $d): bool => $d['zaman'] <= $zaman));
        $tamlar = array_values(array_filter($adaylar, static fn(array $d): bool => $d['tip'] === 'tam'));
        if ($tamlar === []) {
            throw new RuntimeException("$zaman oncesine ait tam yedek Drive'da yok.");
        }
        $temel = end($tamlar);
        $secilen = array_values(array_filter($adaylar, static fn(array $d): bool => $d['zaman'] >= $temel['zaman']));
    }

    yaz(sprintf("Drive'dan %d dosya alinacak -> %s", count($secilen), $hedefDizin));
    $alinan = 0;
    foreach ($secilen as $dosya) {
        $yerelAd = substr($dosya['name'], 0, -4);
        if (is_file($hedefDizin . '/' . $yerelAd)) {
            yaz("Yerelde zaten var, atlandi: $yerelAd");
            continue;
        }
        $servis->indirVeCoz($dosya, $hedefDizin);
        yaz("Indirildi ve sifresi cozuldu: $yerelAd");
        $alinan++;
    }

    try {
        (new SystemLogModel())->logAction(0, 'Veritabani Yedekleme',
            sprintf("Google Drive'dan %d yedek dosyasi geri alindi (%s)", $alinan, $vt), SystemLogModel::LEVEL_CRITICAL);
    } catch (Throwable $e) {
        error_log('[drive_geri_al] system_logs kaydi olusturulamadi: ' . $e->getMessage());
    }

    yaz('Tamamlandi. Geri yukleme plani icin: php cron/db_restore.php --liste');
} catch (Throwable $e) {
    error_log('[drive_geri_al] ' . $e->getMessage());
    yaz('Islem basarisiz: ' . $e->getMessage(), 'ERROR');
    exit(1);
}
