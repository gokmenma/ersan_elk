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
use App\Service\GoogleDriveService;
use App\Service\YedekSifrelemeService;
use App\Service\DriveYedekSenkronService;

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

function sistemLogu(string $mesaj): void
{
    try {
        (new SystemLogModel())->logAction(0, 'Veritabani Yedekleme', $mesaj, SystemLogModel::LEVEL_IMPORTANT);
    } catch (Throwable $e) {
        error_log('[drive_yetkilendir] system_logs kaydi olusturulamadi: ' . $e->getMessage());
    }
}

$secenekler = getopt('', ['kod:', 'klasor-adi:', 'anahtar-uret', 'test']);

try {
    if (isset($secenekler['anahtar-uret'])) {
        echo PHP_EOL . 'YEDEK_SIFRELEME_ANAHTARI=' . YedekSifrelemeService::anahtarUret() . PHP_EOL . PHP_EOL;
        echo 'Bu anahtari .env dosyasina ekleyin ve AYRICA sunucu disinda (sifre yoneticisi vb.) saklayin.' . PHP_EOL;
        echo 'Anahtar kaybolursa Drive uzerindeki yedekler acilamaz.' . PHP_EOL;
        exit(0);
    }

    if (isset($secenekler['test'])) {
        if (!DriveYedekSenkronService::yapilandirildi()) {
            throw new RuntimeException('.env icinde GDRIVE_CLIENT_ID, GDRIVE_CLIENT_SECRET, GDRIVE_REFRESH_TOKEN, GDRIVE_KLASOR_ID ve YEDEK_SIFRELEME_ANAHTARI tanimli olmali.');
        }

        $drive = new GoogleDriveService();
        $sifreleme = new YedekSifrelemeService();
        $tmp = sys_get_temp_dir() . '/ersan_drive_test_' . bin2hex(random_bytes(6));
        $icerik = random_bytes(3 * 1024 * 1024 + 123);
        file_put_contents($tmp, $icerik);

        try {
            $sifreleme->sifrele($tmp, $tmp . '.enc');
            $yuklenen = $drive->yukle($tmp . '.enc', 'baglanti_testi_' . date('Ymd_His') . '.enc', $_ENV['GDRIVE_KLASOR_ID']);
            yaz('Test dosyasi yuklendi: ' . $yuklenen['id']);

            $drive->indir($yuklenen['id'], $tmp . '.indirilen');
            $sifreleme->coz($tmp . '.indirilen', $tmp . '.cozulen');
            $drive->sil($yuklenen['id']);

            if (md5_file($tmp . '.enc') !== $yuklenen['md5Checksum'] || file_get_contents($tmp . '.cozulen') !== $icerik) {
                throw new RuntimeException('Gidis-donus testinde icerik farkli cikti.');
            }
            yaz('Drive baglanti testi BASARILI: yukleme, indirme, sifre cozme ve silme calisiyor.');
            sistemLogu('Google Drive baglanti testi basarili');
        } finally {
            foreach (['', '.enc', '.enc.tmp', '.indirilen', '.cozulen', '.cozulen.tmp'] as $ek) {
                @unlink($tmp . $ek);
            }
        }
        exit(0);
    }

    $drive = new GoogleDriveService('');

    if (empty($secenekler['kod'])) {
        echo <<<TXT

1) Asagidaki adresi tarayicida acin ve yedeklerin gidecegi Google hesabiyla izin verin:

{$drive->yetkilendirmeAdresi()}

2) Izin sonrasi tarayici "http://localhost/?code=..." adresine gider (sayfa acilmayabilir, sorun degil).
   Adres cubugundaki tam adresi kopyalayip su komutu calistirin:

   php cron/drive_yetkilendir.php --kod="http://localhost/?code=...."


TXT;
        exit(0);
    }

    $kod = trim($secenekler['kod']);
    if (strpos($kod, 'code=') !== false) {
        parse_str((string) parse_url($kod, PHP_URL_QUERY), $sorgu);
        $kod = (string) ($sorgu['code'] ?? '');
    }
    if ($kod === '') {
        throw new RuntimeException('Yetkilendirme kodu bulunamadi.');
    }

    $yenilemeTokeni = $drive->koduDegistir($kod);
    $klasorId = $_ENV['GDRIVE_KLASOR_ID'] ?? '';
    if ($klasorId === '') {
        $klasorId = $drive->klasorOlustur($secenekler['klasor-adi'] ?? 'ersan_db_yedekleri');
    }

    yaz('Google Drive yetkilendirmesi tamamlandi.');
    sistemLogu('Google Drive yedekleme yetkilendirmesi tamamlandi');

    echo PHP_EOL . 'Asagidaki satirlari .env dosyasina ekleyin / guncelleyin:' . PHP_EOL . PHP_EOL;
    echo 'GDRIVE_REFRESH_TOKEN=' . $yenilemeTokeni . PHP_EOL;
    echo 'GDRIVE_KLASOR_ID=' . $klasorId . PHP_EOL . PHP_EOL;
    echo 'Ardindan dogrulayin: php cron/drive_yetkilendir.php --test' . PHP_EOL;
} catch (Throwable $e) {
    error_log('[drive_yetkilendir] ' . $e->getMessage());
    yaz('Islem basarisiz: ' . $e->getMessage(), 'ERROR');
    exit(1);
}
