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
use App\Service\MailGonderService;

const YEDEK_SURUM = 1;

$ayar    = require __DIR__ . '/yedekleme_ayar.php';
$kokDizin = dirname(__DIR__);
$logDizin = __DIR__ . '/logs';
$logDosya = $logDizin . '/backup.log';

$secenekler = getopt('', ['tam', 'artimli', 'tablo:', 'sessiz', 'mailsiz']);
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

function bt(string $ad): string
{
    return '`' . str_replace('`', '``', $ad) . '`';
}

function definerTemizle(string $sql): string
{
    return preg_replace('/\sDEFINER\s*=\s*(`[^`]*`|\'[^\']*\')@(`[^`]*`|\'[^\']*\')/i', '', $sql);
}

function ifadeYaz($gz, string $tablo, string $tur, string $sql): void
{
    gzwrite($gz, "-- @STMT t={$tablo} k={$tur}\n");
    gzwrite($gz, rtrim(rtrim($sql), ';') . ";\n");
    gzwrite($gz, "-- @END\n");
}

function veriYaz($gz, YedeklemeModel $model, string $tablo, array $kosul, string $komut, array $ayar): int
{
    $kolonlar = $model->kolonlar($tablo);
    if (empty($kolonlar)) {
        return 0;
    }

    $kolonSql = implode(',', array_map('bt', $kolonlar));
    $onEk     = "$komut " . bt($tablo) . " ($kolonSql) VALUES ";
    $limit    = max(64 * 1024, $ayar['paket_limiti'] - strlen($onEk) - 1024);

    $tampon    = '';
    $tamponAdet = 0;
    $toplam    = 0;

    foreach ($model->satirAkisi($tablo, $kosul) as $satir) {
        $degerler = '(' . implode(',', array_map([$model, 'tirnakla'], $satir)) . ')';

        $tasar = $tampon !== '' &&
            (strlen($tampon) + strlen($degerler) + 1 > $limit || $tamponAdet >= $ayar['satir_limiti']);

        if ($tasar) {
            ifadeYaz($gz, $tablo, 'veri', $onEk . $tampon);
            $tampon     = '';
            $tamponAdet = 0;
        }

        $tampon .= ($tampon === '' ? '' : ',') . $degerler;
        $tamponAdet++;
        $toplam++;
    }

    if ($tampon !== '') {
        ifadeYaz($gz, $tablo, 'veri', $onEk . $tampon);
    }

    return $toplam;
}

function durumOku(string $dosya): array
{
    if (!is_file($dosya)) {
        return [];
    }
    $veri = json_decode((string) file_get_contents($dosya), true);
    return is_array($veri) ? $veri : [];
}

function durumYaz(string $dosya, array $durum): void
{
    file_put_contents($dosya, json_encode($durum, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    @chmod($dosya, 0600);
}

function dizinKoru(string $dizin): void
{
    $htaccess = $dizin . '/.htaccess';
    $icerik   = is_file($htaccess) ? (string) file_get_contents($htaccess) : '';
    if (strpos($icerik, 'Require all denied') === false) {
        @file_put_contents(
            $htaccess,
            "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n"
            . "<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n"
            . "Options -Indexes\n"
        );
        @chmod($htaccess, 0644);
    }
    if (!is_file($dizin . '/index.html')) {
        @file_put_contents($dizin . '/index.html', '');
    }
}

function rotasyon(string $dizin, string $vt, int $saklamaGun): int
{
    $tamlar = glob($dizin . "/tam_{$vt}_*.sql.gz") ?: [];
    if (empty($tamlar)) {
        return 0;
    }
    sort($tamlar);

    $sinir    = time() - ($saklamaGun * 86400);
    $korunan  = [];
    foreach ($tamlar as $dosya) {
        if (filemtime($dosya) >= $sinir) {
            $korunan[] = $dosya;
        }
    }
    if (empty($korunan)) {
        $korunan[] = end($tamlar);
    }

    $enEskiKorunan = min(array_map('filemtime', $korunan));

    $silinen = 0;
    foreach ((glob($dizin . "/{tam,artimli}_{$vt}_*.sql.gz", GLOB_BRACE) ?: []) as $dosya) {
        if (in_array($dosya, $korunan, true)) {
            continue;
        }
        if (filemtime($dosya) < $enEskiKorunan) {
            @unlink($dosya);
            $silinen++;
        }
    }
    return $silinen;
}

if (!is_dir($logDizin)) {
    @mkdir($logDizin, 0750, true);
}

$yedekDizin = rtrim($ayar['yedek_dizini'], '/');
if (!is_dir($yedekDizin) && !@mkdir($yedekDizin, 0750, true)) {
    yaz("Yedek dizini olusturulamadi: $yedekDizin", 'ERROR');
    exit(1);
}
@chmod($yedekDizin, 0750);
if (strpos(realpath($yedekDizin) ?: '', realpath($kokDizin) ?: '') === 0) {
    dizinKoru($yedekDizin);
}

$durumDosya = $yedekDizin . '/durum.json';
$basladi    = microtime(true);
$hataVar    = false;
$ozet       = [];

try {
    $model = new YedeklemeModel();
    $vt    = $model->veritabaniAdi();

    $durum      = durumOku($durumDosya);
    $semaOzeti  = $model->semaOzeti();
    $sunucuMaxPaket = $model->maxPaket();

    if ($ayar['paket_limiti'] > ($sunucuMaxPaket * 0.8)) {
        $ayar['paket_limiti'] = (int) max(64 * 1024, $sunucuMaxPaket * 0.5);
        yaz('paket_limiti sunucu max_allowed_packet degerine gore dusuruldu: ' . $ayar['paket_limiti']);
    }

    $tamMi = isset($secenekler['tam']);
    if (!$tamMi && !isset($secenekler['artimli'])) {
        $tamMi = empty($durum['son_tam'])
            || (int) date('w') === (int) $ayar['tam_yedek_gunu'];
    }

    if (!$tamMi && ($durum['sema'] ?? '') !== $semaOzeti) {
        yaz('Tablo yapisi degismis, artimli yerine tam yedek aliniyor.', 'WARNING');
        $tamMi = true;
    }

    if (!$tamMi && !is_file($yedekDizin . '/' . ($durum['son_tam']['dosya'] ?? ''))) {
        yaz('Referans tam yedek dosyasi bulunamadi, tam yedek aliniyor.', 'WARNING');
        $tamMi = true;
    }

    $tumTablolar = $model->tabloListesi();
    $viewler     = $model->viewListesi();

    if (!empty($secenekler['tablo'])) {
        $istenen     = array_map('trim', explode(',', $secenekler['tablo']));
        $tumTablolar = array_values(array_intersect($tumTablolar, $istenen));
        $viewler     = array_values(array_intersect($viewler, $istenen));
        if (empty($tumTablolar) && empty($viewler)) {
            throw new RuntimeException('Belirtilen tablolar bulunamadi.');
        }
    }

    $artimliTablolar = $tamMi
        ? []
        : array_values(array_intersect($ayar['artimli_tablolar'], $tumTablolar));

    $tur = $tamMi ? 'tam' : 'artimli';

    yaz("--- YEDEKLEME BASLADI (tur: $tur, vt: $vt) ---");

    $model->tutarliOkumaBaslat();
    $anZamani = $model->sunucuZamani();

    $damga    = str_replace([' ', ':'], ['_', '-'], $anZamani);
    $dosyaAdi = "{$tur}_{$vt}_{$damga}.sql.gz";
    $hedef    = $yedekDizin . '/' . $dosyaAdi;

    $yeniIsaretler = $durum['isaretler'] ?? [];
    $kosullar      = [];

    foreach ($artimliTablolar as $tablo) {
        $idKolon        = $model->otoArtanKolon($tablo);
        $zamanKolonlari = $model->degisimKolonlari($tablo);
        $onceki         = $durum['isaretler'][$tablo] ?? null;

        if ($onceki === null || ($idKolon === null && empty($zamanKolonlari))) {
            $kosullar[$tablo] = [];
        } else {
            $kosullar[$tablo] = [
                'id_kolon'        => $idKolon,
                'id_deger'        => $idKolon !== null ? ($onceki['id_deger'] ?? null) : null,
                'zaman_kolonlari' => $zamanKolonlari,
                'zaman_deger'     => !empty($zamanKolonlari) ? ($onceki['zaman'] ?? null) : null,
            ];
        }

        $yeniIsaretler[$tablo] = [
            'id_kolon' => $idKolon,
            'id_deger' => $idKolon !== null ? $model->enBuyukDeger($tablo, $idKolon) : null,
            'zaman'    => $anZamani,
        ];
    }

    $gz = gzopen($hedef, 'wb' . (int) $ayar['sikistirma_seviyesi']);
    if ($gz === false) {
        throw new RuntimeException('Yedek dosyasi acilamadi: ' . $hedef);
    }

    $meta = [
        'surum' => YEDEK_SURUM,
        'tip'   => $tur,
        'vt'    => $vt,
        'zaman' => $anZamani,
        'sema'  => $semaOzeti,
        'temel' => $tamMi ? null : ($durum['son_tam']['dosya'] ?? null),
    ];
    gzwrite($gz, '-- @META ' . json_encode($meta, JSON_UNESCAPED_UNICODE) . "\n");

    ifadeYaz($gz, '*', 'oturum', "SET FOREIGN_KEY_CHECKS = 0");
    ifadeYaz($gz, '*', 'oturum', "SET UNIQUE_CHECKS = 0");
    ifadeYaz($gz, '*', 'oturum', "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO'");

    $toplamSatir = 0;

    foreach ($tumTablolar as $tablo) {
        if ($tamMi) {
            ifadeYaz($gz, $tablo, 'ddl', 'DROP TABLE IF EXISTS ' . bt($tablo));
            ifadeYaz($gz, $tablo, 'ddl', definerTemizle($model->olusturmaKodu($tablo)));
        }

        if (in_array($tablo, $ayar['veri_haric_tablolar'], true)) {
            yaz("Tablo atlandi (veri harici): $tablo");
            continue;
        }

        if ($tamMi) {
            $adet = veriYaz($gz, $model, $tablo, [], 'INSERT INTO', $ayar);
        } elseif (isset($kosullar[$tablo])) {
            $adet = veriYaz($gz, $model, $tablo, $kosullar[$tablo], 'REPLACE INTO', $ayar);
        } else {
            ifadeYaz($gz, $tablo, 'temizle', 'DELETE FROM ' . bt($tablo));
            $adet = veriYaz($gz, $model, $tablo, [], 'INSERT INTO', $ayar);
        }

        $toplamSatir += $adet;
        if ($adet > 0) {
            $ozet[$tablo] = $adet;
        }
    }

    if ($tamMi) {
        foreach ($viewler as $view) {
            ifadeYaz($gz, $view, 'view', 'DROP VIEW IF EXISTS ' . bt($view));
            ifadeYaz($gz, $view, 'view', definerTemizle($model->olusturmaKodu($view)));
        }

        foreach ($model->triggerListesi() as $trg) {
            if (trim($trg['sql']) === '') {
                continue;
            }
            ifadeYaz($gz, $trg['tablo'], 'trigger', 'DROP TRIGGER IF EXISTS ' . bt($trg['ad']));
            ifadeYaz($gz, $trg['tablo'], 'trigger', definerTemizle($trg['sql']));
        }

        foreach ($model->rutinListesi() as $rutin) {
            ifadeYaz($gz, '*', 'rutin', 'DROP ' . $rutin['tip'] . ' IF EXISTS ' . bt($rutin['ad']));
            ifadeYaz($gz, '*', 'rutin', definerTemizle($rutin['sql']));
        }
    }

    ifadeYaz($gz, '*', 'oturum', "SET UNIQUE_CHECKS = 1");
    ifadeYaz($gz, '*', 'oturum', "SET FOREIGN_KEY_CHECKS = 1");
    gzwrite($gz, "-- @SON\n");
    gzclose($gz);

    $model->tutarliOkumaBitir();
    @chmod($hedef, 0600);

    $boyut = filesize($hedef);
    $sure  = round(microtime(true) - $basladi, 1);
    yaz(sprintf('Yedek olusturuldu: %s (%s, %s satir, %s sn)',
        $dosyaAdi, number_format($boyut / 1048576, 2) . ' MB', number_format($toplamSatir), $sure));

    $durum['vt']        = $vt;
    $durum['sema']      = $semaOzeti;
    $durum['isaretler'] = $yeniIsaretler;
    $durum['son_yedek'] = ['dosya' => $dosyaAdi, 'zaman' => $anZamani, 'tip' => $tur];
    if ($tamMi) {
        $durum['son_tam'] = ['dosya' => $dosyaAdi, 'zaman' => $anZamani];
        $durum['isaretler'] = [];
        foreach ($ayar['artimli_tablolar'] as $tablo) {
            if (!in_array($tablo, $tumTablolar, true)) {
                continue;
            }
            $idKolon = $model->otoArtanKolon($tablo);
            $durum['isaretler'][$tablo] = [
                'id_kolon' => $idKolon,
                'id_deger' => $idKolon !== null ? $model->enBuyukDeger($tablo, $idKolon) : null,
                'zaman'    => $anZamani,
            ];
        }
    }
    durumYaz($durumDosya, $durum);

    $silinen = rotasyon($yedekDizin, $vt, (int) $ayar['saklama_gun']);
    yaz("Rotasyon: $silinen eski yedek silindi.");

    try {
        (new SystemLogModel())->logAction(
            0,
            'Veritabani Yedekleme',
            sprintf('%s yedek alindi: %s (%s MB, %s satir)', strtoupper($tur), $dosyaAdi,
                number_format($boyut / 1048576, 2), number_format($toplamSatir)),
            SystemLogModel::LEVEL_IMPORTANT
        );
    } catch (Throwable $e) {
        error_log('[db_backup] system_logs kaydi olusturulamadi: ' . $e->getMessage());
    }

    yaz('--- YEDEKLEME BITTI ---');
} catch (Throwable $e) {
    $hataVar = true;
    if (isset($gz) && is_resource($gz)) {
        @gzclose($gz);
    }
    if (isset($hedef) && is_file($hedef)) {
        @unlink($hedef);
    }
    error_log('[db_backup] ' . $e->getMessage());
    yaz('Yedekleme basarisiz: ' . $e->getMessage(), 'ERROR');
}

if (!empty($ayar['mail_gonder']) && !empty($ayar['mail_alicilar'])) {
    try {
        $baslik = ($hataVar ? '[HATA] ' : '') . 'DB Yedekleme - ' . date('d.m.Y H:i');
        $icerik = '<h3>Veritabani Yedekleme Raporu</h3><pre style="background:#f4f4f4;padding:10px;border:1px solid #ddd">'
            . htmlspecialchars($logSatirlari, ENT_QUOTES, 'UTF-8') . '</pre>';
        MailGonderService::gonder($ayar['mail_alicilar'], $baslik, $icerik);
    } catch (Throwable $e) {
        error_log('[db_backup] Mail gonderilemedi: ' . $e->getMessage());
    }
}

exit($hataVar ? 1 : 0);
