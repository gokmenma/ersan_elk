<?php

if (php_sapi_name() !== 'cli') {
    header('HTTP/1.0 403 Forbidden');
    exit('Bu script sadece CLI uzerinden calisabilir.');
}

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
set_time_limit(0);

require_once dirname(__DIR__) . '/Autoloader.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

use App\Model\YedeklemeModel;
use App\Model\SystemLogModel;

$ayar     = require __DIR__ . '/yedekleme_ayar.php';
$logDizin = __DIR__ . '/logs';
$logDosya = $logDizin . '/restore.log';

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
  php cron/db_restore.php --liste
  php cron/db_restore.php --zaman="2026-08-02 03:00:00" [--tablo=a,b] [--onayla]
  php cron/db_restore.php --dosya=tam_vt_2026-08-02_03-00-00.sql.gz [--tablo=a,b] [--onayla]

Secenekler:
  --liste     Kullanilabilir geri yukleme noktalarini listeler.
  --zaman     Verilen ana kadar olan tam yedek + artimli yedekler uygulanir.
  --dosya     Tek bir yedek dosyasi uygulanir (zincir kurulmaz).
  --tablo     Sadece belirtilen tablolarin ifadeleri uygulanir (virgulle ayrilir).
  --hedef-vt  Yedegi baska bir veritabanina uygular (yedek dogrulama tatbikati icin).
  --onayla    Bu parametre verilmezse hicbir sey yazilmaz, sadece plan gosterilir.

TXT;
}

function metaOku(string $dosya): ?array
{
    $gz = gzopen($dosya, 'rb');
    if ($gz === false) {
        return null;
    }
    $satir = gzgets($gz);
    gzclose($gz);
    if (!is_string($satir) || strpos($satir, '-- @META ') !== 0) {
        return null;
    }
    $meta = json_decode(trim(substr($satir, 9)), true);
    return is_array($meta) ? $meta : null;
}

function yedekleriTara(string $dizin, string $vt): array
{
    $liste = [];
    foreach ((glob($dizin . "/{tam,artimli}_{$vt}_*.sql.gz", GLOB_BRACE) ?: []) as $dosya) {
        $meta = metaOku($dosya);
        if ($meta === null) {
            yaz('Meta satiri okunamadi, atlandi: ' . basename($dosya), 'WARNING');
            continue;
        }
        $meta['dosya'] = $dosya;
        $meta['ad']    = basename($dosya);
        $meta['boyut'] = filesize($dosya);
        $liste[]       = $meta;
    }
    usort($liste, fn($a, $b) => strcmp($a['zaman'], $b['zaman']));
    return $liste;
}

function zincirKur(array $liste, string $hedefZaman): array
{
    $temel = null;
    foreach ($liste as $kayit) {
        if ($kayit['tip'] === 'tam' && $kayit['zaman'] <= $hedefZaman) {
            $temel = $kayit;
        }
    }
    if ($temel === null) {
        return [];
    }

    $zincir = [$temel];
    foreach ($liste as $kayit) {
        if ($kayit['tip'] !== 'artimli') {
            continue;
        }
        if (($kayit['temel'] ?? null) !== $temel['ad']) {
            continue;
        }
        if ($kayit['zaman'] > $temel['zaman'] && $kayit['zaman'] <= $hedefZaman) {
            $zincir[] = $kayit;
        }
    }
    return $zincir;
}

function dosyaDogrula(string $dosya): array
{
    $gz = gzopen($dosya, 'rb');
    if ($gz === false) {
        throw new RuntimeException('Dosya acilamadi: ' . basename($dosya));
    }

    $ifadeSayisi = 0;
    $tamamlandi  = false;
    $acikBlok    = false;

    while (($satir = gzgets($gz)) !== false) {
        if (strpos($satir, '-- @STMT ') === 0) {
            $acikBlok = true;
            $ifadeSayisi++;
        } elseif (strpos($satir, '-- @END') === 0) {
            $acikBlok = false;
        } elseif (strpos($satir, '-- @SON') === 0) {
            $tamamlandi = true;
        }
    }
    gzclose($gz);

    if ($acikBlok) {
        throw new RuntimeException('Yedek dosyasi yarim kalmis (kapanmamis ifade): ' . basename($dosya));
    }
    if (!$tamamlandi) {
        throw new RuntimeException('Yedek dosyasi eksik, sonlandirma isareti yok: ' . basename($dosya));
    }

    return ['ifade' => $ifadeSayisi];
}

function dosyaUygula(YedeklemeModel $model, string $dosya, array $tabloFiltre, bool $onayla): array
{
    $gz = gzopen($dosya, 'rb');
    if ($gz === false) {
        throw new RuntimeException('Dosya acilamadi: ' . basename($dosya));
    }

    $calisan  = 0;
    $atlanan  = 0;
    $tampon   = null;
    $tablo    = '';
    $tur      = '';

    try {
        while (($satir = gzgets($gz)) !== false) {
            if (strpos($satir, '-- @STMT ') === 0) {
                $tablo = '';
                $tur   = '';
                if (preg_match('/t=(\S+)\s+k=(\S+)/', $satir, $e)) {
                    $tablo = $e[1];
                    $tur   = $e[2];
                }
                $tampon = '';
                continue;
            }

            if (strpos($satir, '-- @END') === 0) {
                if ($tampon === null) {
                    continue;
                }
                $sql    = trim($tampon);
                $tampon = null;

                if ($sql === '') {
                    continue;
                }

                $uygula = empty($tabloFiltre)
                    || $tablo === '*'
                    || in_array($tablo, $tabloFiltre, true);

                if (!$uygula) {
                    $atlanan++;
                    continue;
                }

                if ($onayla) {
                    $model->calistir($sql);
                }
                $calisan++;
                continue;
            }

            if ($tampon !== null) {
                $tampon .= $satir;
            }
        }
    } finally {
        gzclose($gz);
    }

    return ['calisan' => $calisan, 'atlanan' => $atlanan];
}

$secenekler = getopt('h', ['liste', 'zaman:', 'dosya:', 'tablo:', 'onayla', 'help', 'hedef-vt:']);

if (isset($secenekler['h']) || isset($secenekler['help']) || empty($secenekler)) {
    kullanim();
    exit(0);
}

$yedekDizin = rtrim($ayar['yedek_dizini'], '/');
$onayla     = isset($secenekler['onayla']);
$tabloFiltre = [];
if (!empty($secenekler['tablo'])) {
    $tabloFiltre = array_values(array_filter(array_map('trim', explode(',', $secenekler['tablo']))));
}

try {
    $model = new YedeklemeModel();
    $vt    = $model->veritabaniAdi();
    $liste = yedekleriTara($yedekDizin, $vt);

    if (empty($liste)) {
        yaz("Yedek dizininde '$vt' icin yedek bulunamadi: $yedekDizin", 'ERROR');
        exit(1);
    }

    if (isset($secenekler['liste'])) {
        echo PHP_EOL . "Geri yukleme noktalari ($vt):" . PHP_EOL;
        printf("%-4s %-10s %-21s %-10s %s" . PHP_EOL, '#', 'TIP', 'ZAMAN', 'BOYUT', 'DOSYA');
        foreach ($liste as $i => $kayit) {
            printf("%-4d %-10s %-21s %-10s %s" . PHP_EOL,
                $i + 1,
                $kayit['tip'],
                $kayit['zaman'],
                number_format($kayit['boyut'] / 1048576, 2) . ' MB',
                $kayit['ad']
            );
        }
        echo PHP_EOL;
        exit(0);
    }

    if (!empty($secenekler['dosya'])) {
        $ad     = basename($secenekler['dosya']);
        $zincir = array_values(array_filter($liste, fn($k) => $k['ad'] === $ad));
        if (empty($zincir)) {
            yaz("Belirtilen yedek dosyasi bulunamadi: $ad", 'ERROR');
            exit(1);
        }
    } else {
        $hedefZaman = $secenekler['zaman'] ?? $model->sunucuZamani();
        $zincir     = zincirKur($liste, $hedefZaman);
        if (empty($zincir)) {
            yaz("Belirtilen ana ($hedefZaman) uygun tam yedek bulunamadi.", 'ERROR');
            exit(1);
        }
    }

    yaz('--- GERI YUKLEME ' . ($onayla ? 'BASLADI' : 'PLANI (KURU CALISMA)') . " (vt: $vt) ---");

    $toplamIfade = 0;
    foreach ($zincir as $kayit) {
        $dogrulama = dosyaDogrula($kayit['dosya']);
        $toplamIfade += $dogrulama['ifade'];
        yaz(sprintf('%s | %s | %s | %s ifade | dogrulandi',
            $kayit['tip'], $kayit['zaman'], $kayit['ad'], number_format($dogrulama['ifade'])));
    }

    if (!empty($tabloFiltre)) {
        yaz('Tablo filtresi: ' . implode(', ', $tabloFiltre));
    }

    if (!$onayla) {
        yaz('Toplam ' . number_format($toplamIfade) . ' ifade uygulanacak.');
        yaz('DIKKAT: Tam yedek uygulamasi mevcut tablolari DROP eder.', 'WARNING');
        yaz('Gercekten calistirmak icin komuta --onayla ekleyin.');
        exit(0);
    }

    $basladi = microtime(true);

    if (!empty($secenekler['hedef-vt'])) {
        $model->veritabaniSec($secenekler['hedef-vt']);
        yaz('Hedef veritabani degistirildi: ' . $secenekler['hedef-vt'], 'WARNING');
    }

    $model->yedekOturumuAc();

    $toplamCalisan = 0;
    foreach ($zincir as $kayit) {
        $sonuc = dosyaUygula($model, $kayit['dosya'], $tabloFiltre, true);
        $toplamCalisan += $sonuc['calisan'];
        yaz(sprintf('Uygulandi: %s (%s ifade, %s atlandi)',
            $kayit['ad'], number_format($sonuc['calisan']), number_format($sonuc['atlanan'])));
    }

    $model->yedekOturumuKapat();

    if (!empty($secenekler['hedef-vt'])) {
        $model->veritabaniSec($vt);
    }

    $sure = round(microtime(true) - $basladi, 1);
    yaz(sprintf('--- GERI YUKLEME BITTI (%s ifade, %s sn) ---', number_format($toplamCalisan), $sure));

    try {
        (new SystemLogModel())->logAction(
            0,
            'Veritabani Geri Yukleme',
            sprintf('%s dosyadan %s ifade uygulandi. Son nokta: %s',
                count($zincir), number_format($toplamCalisan), end($zincir)['ad']),
            SystemLogModel::LEVEL_CRITICAL
        );
    } catch (Throwable $e) {
        error_log('[db_restore] system_logs kaydi olusturulamadi: ' . $e->getMessage());
    }
} catch (Throwable $e) {
    error_log('[db_restore] ' . $e->getMessage());
    yaz('Geri yukleme basarisiz: ' . $e->getMessage(), 'ERROR');
    exit(1);
}

exit(0);
