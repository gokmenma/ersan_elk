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
use App\Service\DriveYedekSenkronService;

$logDosya = __DIR__ . '/logs/dosya_restore.log';
$yaz = function (string $mesaj, string $seviye = 'INFO') use ($logDosya): void {
    $satir = '[' . date('Y-m-d H:i:s') . "] [$seviye] $mesaj" . PHP_EOL;
    @file_put_contents($logDosya, $satir, FILE_APPEND | LOCK_EX);
    echo $satir;
};

function kullanim(): void
{
    echo <<<TXT

Kullanim:
  php cron/dosya_restore.php --liste
  php cron/dosya_restore.php --zaman="2026-11-18 03:00:00"                      # kuru calisma (plan)
  php cron/dosya_restore.php --zaman="..." --onayla                             # yedek dizini altindaki geri_yukleme/ klasorune acar
  php cron/dosya_restore.php --dosya=dosya_artimli_2026-11-18_03-00-00.tar.gz --onayla
  php cron/dosya_restore.php --zaman="..." --yol=uploads/personel_evraklar --onayla
  php cron/dosya_restore.php --zaman="..." --hedef=/baska/dizin --onayla
  php cron/dosya_restore.php --zaman="..." --canli --onayla                     # dosyalari uygulama kokune yazar
  php cron/dosya_restore.php --drive-al --zaman="..."                           # Drive'dan indirip yedek dizinine acar

Varsayilan hedef canli dizin degildir; once ayri klasore acilip kontrol edilmesi onerilir.

TXT;
}

$secenekler = getopt('', ['liste', 'zaman:', 'dosya:', 'yol:', 'hedef:', 'canli', 'onayla', 'drive-al']);
if (!isset($secenekler['liste']) && empty($secenekler['zaman']) && empty($secenekler['dosya'])) {
    kullanim();
    exit(1);
}

try {
    $servis = new DosyaYedekService();
    $kok = realpath(dirname(__DIR__));

    if (isset($secenekler['liste'])) {
        foreach ($servis->yedekler() as $y) {
            printf("%-8s %s  %10s MB  %8s dosya  %s\n", strtoupper($y['tip']), $y['zaman'],
                number_format($y['boyut'] / 1048576, 2), $y['meta']['dosya_adet'] ?? '-', $y['ad']);
        }
        exit(0);
    }

    $zaman = !empty($secenekler['zaman']) ? date('Y-m-d H:i:s', strtotime((string) $secenekler['zaman']) ?: time()) : null;
    $ad = !empty($secenekler['dosya']) ? basename((string) $secenekler['dosya']) : null;

    if (isset($secenekler['drive-al'])) {
        $drive = new DriveYedekSenkronService();
        $uzak = $drive->uzakDosyaYedekleri();
        $hedefZaman = $zaman ?? (DosyaYedekService::adCoz((string) $ad)['zaman'] ?? date('Y-m-d H:i:s'));
        $adaylar = array_values(array_filter($uzak, static fn($d) => $d['zaman'] <= $hedefZaman));
        $tamlar = array_values(array_filter($adaylar, static fn($d) => $d['tip'] === 'tam'));
        if ($tamlar === []) {
            throw new RuntimeException("$hedefZaman oncesine ait dosya tam yedegi Drive'da yok.");
        }
        $temel = end($tamlar);
        foreach ($adaylar as $d) {
            if ($d['zaman'] < $temel['zaman']) {
                continue;
            }
            $yerelAd = substr($d['name'], 0, -4);
            if (is_file($servis->yedekDizini() . '/' . $yerelAd)) {
                $yaz("Yerelde zaten var, atlandi: $yerelAd");
                continue;
            }
            $drive->indirVeCoz($d, $servis->yedekDizini());
            $yaz("Drive'dan indirildi ve sifresi cozuldu: $yerelAd");
        }
        $yaz('Drive dosyalari yerelde. Plan icin ayni komutu --drive-al olmadan calistirin.');
        exit(0);
    }

    $zincir = $servis->zincir($zaman, $ad);
    $yaz('Uygulanacak zincir: ' . implode(' -> ', array_column($zincir, 'ad')));

    if (isset($secenekler['canli'])) {
        $hedef = $kok;
        $yaz('DIKKAT: Dosyalar canli uygulama dizinine yazilacak: ' . $hedef, 'WARNING');
    } elseif (!empty($secenekler['hedef'])) {
        $hedef = rtrim((string) $secenekler['hedef'], '/');
        if ((realpath($hedef) ?: '') === $kok) {
            throw new RuntimeException('Canli dizine yazmak icin --hedef yerine --canli kullanin.');
        }
    } else {
        $hedef = $servis->yedekDizini() . '/geri_yukleme/' . str_replace([' ', ':'], ['_', '-'], end($zincir)['zaman']);
    }

    $onayla = isset($secenekler['onayla']);
    $sonuc = $servis->geriYukle($zincir, $hedef, [
        'onek' => (string) ($secenekler['yol'] ?? ''),
        'onayla' => $onayla,
    ], $yaz);

    $yaz(sprintf('%s: %s dosya (%s MB), %s silme. Hedef: %s', $onayla ? 'Tamamlandi' : 'KURU CALISMA', number_format($sonuc['yazilan'], 0, ',', '.'),
        number_format($sonuc['boyut'] / 1048576, 2), $sonuc['silinen'], $hedef));
    if (!$onayla) {
        $yaz('Hicbir dosya yazilmadi. Uygulamak icin --onayla ekleyin.');
    }
} catch (Throwable $e) {
    error_log('[dosya_restore] ' . $e->getMessage());
    $yaz('Islem basarisiz: ' . $e->getMessage(), 'ERROR');
    exit(1);
}
