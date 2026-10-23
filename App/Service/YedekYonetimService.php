<?php

namespace App\Service;

use App\Model\SystemLogModel;
use DomainException;
use Throwable;

class YedekYonetimService
{
    public const ISLER = [
        'yedek_tam' => 'Tam Yedek',
        'yedek_artimli' => 'Artımlı Yedek',
        'drive_senkron' => 'Google Drive Senkronu',
        'geri_yukleme_testi' => 'Geri Yükleme Testi',
        'drive_indir' => "Drive'dan Yerele Alma",
    ];

    private const IS_DOSYASI = 'is_durumu.json';
    private const DRIVE_ONBELLEK = 'drive_liste.json';
    private const DRIVE_ONBELLEK_SURE = 600;
    private const LOG_LIMIT = 300;

    private VeritabaniYedekService $yedek;
    private string $dizin;
    private $kilit = null;
    private ?array $is = null;

    public function __construct()
    {
        $this->yedek = new VeritabaniYedekService();
        $this->dizin = $this->yedek->yedekDizini();
    }

    public static function dosyaAdiGecerliMi(string $ad): bool
    {
        return (bool) preg_match('/^(tam|artimli)_[A-Za-z0-9_]+_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.sql\.gz$/', $ad);
    }

    public function vt(): string
    {
        return (string) ($_ENV['DB_NAME'] ?? '');
    }

    public function yerelDosyaYolu(string $ad): string
    {
        if (!self::dosyaAdiGecerliMi($ad)) {
            throw new DomainException('Geçersiz yedek dosya adı.');
        }
        $yol = realpath($this->dizin . '/' . $ad);
        $dizin = realpath($this->dizin);
        if ($yol === false || $dizin === false || dirname($yol) !== $dizin || !is_file($yol)) {
            throw new DomainException('Yedek dosyası bulunamadı.');
        }
        return $yol;
    }

    public function liste(bool $driveYenile = false): array
    {
        $vt = $this->vt();
        $geriYukleme = new VeritabaniGeriYuklemeService($this->dizin);
        $testler = [];
        foreach ($geriYukleme->testGecmisi() as $test) {
            $testler[$test['dosya']] ??= $test;
        }

        $satirlar = [];
        foreach ((glob($this->dizin . "/{tam,artimli}_{$vt}_*.sql.gz", GLOB_BRACE) ?: []) as $dosya) {
            $ad = basename($dosya);
            $meta = VeritabaniGeriYuklemeService::metaOku($dosya) ?? [];
            $satirlar[$ad] = [
                'ad' => $ad,
                'tip' => $meta['tip'] ?? (str_starts_with($ad, 'tam_') ? 'tam' : 'artimli'),
                'zaman' => $meta['zaman'] ?? $this->addanZaman($ad),
                'temel' => $meta['temel'] ?? null,
                'boyut' => (int) filesize($dosya),
                'yerel' => true,
                'drive' => false,
                'drive_boyut' => null,
            ];
        }

        $drive = $this->driveListesi($driveYenile);
        foreach ($drive['dosyalar'] as $uzak) {
            $ad = substr($uzak['name'], 0, -4);
            if (!isset($satirlar[$ad])) {
                $satirlar[$ad] = [
                    'ad' => $ad,
                    'tip' => $uzak['tip'],
                    'zaman' => $uzak['zaman'],
                    'temel' => null,
                    'boyut' => (int) ($uzak['size'] ?? 0),
                    'yerel' => false,
                ];
            }
            $satirlar[$ad]['drive'] = true;
            $satirlar[$ad]['drive_boyut'] = (int) ($uzak['size'] ?? 0);
        }

        foreach ($satirlar as $ad => &$satir) {
            $satir['test'] = $testler[$ad] ?? null;
        }
        unset($satir);

        usort($satirlar, static fn($a, $b) => strcmp($b['zaman'], $a['zaman']));

        return [
            'satirlar' => array_values($satirlar),
            'drive_durum' => $drive['durum'],
            'drive_hata' => $drive['hata'],
            'drive_zaman' => $drive['zaman'],
        ];
    }

    public function ozet(array $liste): array
    {
        $durum = $this->yedek->durumOku();
        $testler = (new VeritabaniGeriYuklemeService($this->dizin))->testGecmisi();
        $yerel = array_filter($liste['satirlar'], static fn($s) => $s['yerel']);

        return [
            'toplam' => count($liste['satirlar']),
            'yerel_adet' => count($yerel),
            'yerel_boyut' => array_sum(array_column($yerel, 'boyut')),
            'tam_adet' => count(array_filter($liste['satirlar'], static fn($s) => $s['tip'] === 'tam')),
            'artimli_adet' => count(array_filter($liste['satirlar'], static fn($s) => $s['tip'] === 'artimli')),
            'drive_adet' => count(array_filter($liste['satirlar'], static fn($s) => $s['drive'])),
            'drive_eksik' => count(array_filter($yerel, static fn($s) => !$s['drive'])),
            'drive_yapilandirildi' => DriveYedekSenkronService::yapilandirildi(),
            'drive_durum' => $liste['drive_durum'],
            'son_yedek' => $durum['son_yedek'] ?? null,
            'son_tam' => $durum['son_tam'] ?? null,
            'son_drive' => $durum['son_drive'] ?? null,
            'son_test' => $testler[0] ?? null,
            'test_gecmisi' => array_slice(array_map(static function ($t) {
                unset($t['farklar']);
                return $t;
            }, $testler), 0, 10),
            'test_vt_tanimli' => trim((string) ($_ENV['YEDEK_TEST_VT'] ?? '')) !== '',
            'bos_alan' => @disk_free_space($this->dizin) ?: null,
            'is' => $this->isDurumu(),
        ];
    }

    public function testDetayi(string $ad): ?array
    {
        foreach ((new VeritabaniGeriYuklemeService($this->dizin))->testGecmisi() as $test) {
            if ($test['dosya'] === $ad) {
                return $test;
            }
        }
        return null;
    }

    public function isDurumu(): ?array
    {
        $dosya = $this->dizin . '/' . self::IS_DOSYASI;
        if (!is_file($dosya)) {
            return null;
        }
        $is = json_decode((string) file_get_contents($dosya), true);
        if (!is_array($is)) {
            return null;
        }

        if (($is['durum'] ?? '') === 'calisiyor') {
            $kilit = $this->yedek->kilitAl();
            if ($kilit !== null) {
                $this->yedek->kilitBirak($kilit);
                $is['durum'] = 'yarida_kaldi';
                $is['log'][] = ['z' => date('H:i:s'), 's' => 'ERROR', 'm' => 'İşlem sunucu tarafından sonlandırıldı (zaman aşımı veya yeniden başlatma).'];
                $this->isYaz($is);
            }
        }

        return $is;
    }

    public function isBaslat(string $tip, array $parametre, int $kullaniciId): array
    {
        if (!isset(self::ISLER[$tip])) {
            throw new DomainException('Tanımsız işlem.');
        }

        if ($tip === 'geri_yukleme_testi') {
            if (trim((string) ($_ENV['YEDEK_TEST_VT'] ?? '')) === '') {
                throw new DomainException('Geri yükleme testi için .env içinde YEDEK_TEST_VT tanımlanmalı.');
            }
            $this->yerelDosyaYolu((string) ($parametre['dosya'] ?? ''));
        }
        if (in_array($tip, ['drive_senkron', 'drive_indir'], true) && !DriveYedekSenkronService::yapilandirildi()) {
            throw new DomainException('Google Drive yapılandırılmamış.');
        }
        if ($tip === 'drive_indir' && !self::dosyaAdiGecerliMi((string) ($parametre['dosya'] ?? ''))) {
            throw new DomainException('Geçersiz yedek dosya adı.');
        }

        $kilit = $this->yedek->kilitAl();
        if ($kilit === null) {
            throw new DomainException('Şu anda başka bir yedekleme işlemi sürüyor. Bitmesini bekleyin.');
        }
        $this->kilit = $kilit;

        $this->is = [
            'id' => bin2hex(random_bytes(8)),
            'tip' => $tip,
            'baslik' => self::ISLER[$tip],
            'parametre' => array_intersect_key($parametre, ['dosya' => true]),
            'durum' => 'calisiyor',
            'kullanici_id' => $kullaniciId,
            'basladi' => date('Y-m-d H:i:s'),
            'bitti' => null,
            'log' => [],
        ];
        $this->isYaz($this->is);

        return $this->is;
    }

    public function isCalistir(): void
    {
        if ($this->is === null || $this->kilit === null) {
            throw new DomainException('Başlatılmış işlem yok.');
        }

        $log = function (string $mesaj, string $seviye = 'INFO'): void {
            $this->isLogu($mesaj, $seviye);
        };

        $basarili = false;
        try {
            $kullaniciId = (int) $this->is['kullanici_id'];
            switch ($this->is['tip']) {
                case 'yedek_tam':
                case 'yedek_artimli':
                    $sonuc = $this->yedek->yedekAl([
                        'tam' => $this->is['tip'] === 'yedek_tam',
                        'artimli' => $this->is['tip'] === 'yedek_artimli',
                        'kullanici_id' => $kullaniciId,
                    ], $log);
                    $basarili = $sonuc['basarili'];
                    $this->driveOnbellekSil();
                    break;

                case 'drive_senkron':
                    $sonuc = $this->yedek->driveSenkronize($this->vt(), $kullaniciId, $log);
                    $basarili = ($sonuc['durum'] ?? '') === 'tamam';
                    $this->driveOnbellekSil();
                    break;

                case 'geri_yukleme_testi':
                    $sonuc = (new VeritabaniGeriYuklemeService($this->dizin))
                        ->testEt($this->is['parametre']['dosya'], $kullaniciId, $log);
                    $basarili = in_array($sonuc['sonuc'], ['basarili', 'sayimsiz'], true);
                    break;

                case 'drive_indir':
                    $basarili = $this->drivedanIndir($this->is['parametre']['dosya'], $kullaniciId);
                    break;
            }
        } catch (Throwable $e) {
            error_log('[yedek_arayuz] ' . $e->getMessage());
            $this->isLogu('İşlem başarısız: ' . VeritabaniYedekService::kullaniciMesaji($e), 'ERROR');
        } finally {
            $this->is['durum'] = $basarili ? 'tamam' : 'hata';
            $this->is['bitti'] = date('Y-m-d H:i:s');
            $this->isYaz($this->is);
            $this->yedek->kilitBirak($this->kilit);
            $this->kilit = null;
        }
    }

    private function drivedanIndir(string $ad, int $kullaniciId): bool
    {
        if (is_file($this->dizin . '/' . $ad)) {
            $this->isLogu("Dosya zaten yerelde var: $ad", 'WARNING');
            return true;
        }

        $servis = new DriveYedekSenkronService();
        foreach ($servis->uzakYedekler($this->vt()) as $uzak) {
            if ($uzak['name'] === $ad . '.enc') {
                $this->isLogu("Drive'dan indiriliyor: {$uzak['name']}");
                $servis->indirVeCoz($uzak, $this->dizin);
                $this->isLogu('İndirildi, MD5 ve şifre bütünlüğü doğrulandı.');
                $this->sistemLogu($kullaniciId, "Google Drive'dan yedek yerele alindi: $ad", SystemLogModel::LEVEL_CRITICAL);
                return true;
            }
        }

        throw new DomainException("Drive'da dosya bulunamadı: $ad");
    }

    private function driveListesi(bool $yenile): array
    {
        $bos = ['dosyalar' => [], 'durum' => 'yapilandirilmamis', 'hata' => null, 'zaman' => null];
        if (!DriveYedekSenkronService::yapilandirildi()) {
            return $bos;
        }

        $onbellek = $this->dizin . '/' . self::DRIVE_ONBELLEK;
        if (!$yenile && is_file($onbellek) && filemtime($onbellek) > time() - self::DRIVE_ONBELLEK_SURE) {
            $veri = json_decode((string) file_get_contents($onbellek), true);
            if (is_array($veri)) {
                return $veri;
            }
        }

        try {
            $veri = [
                'dosyalar' => (new DriveYedekSenkronService())->uzakYedekler($this->vt()),
                'durum' => 'tamam',
                'hata' => null,
                'zaman' => date('Y-m-d H:i:s'),
            ];
            file_put_contents($onbellek, json_encode($veri, JSON_UNESCAPED_UNICODE), LOCK_EX);
            @chmod($onbellek, 0600);
            return $veri;
        } catch (Throwable $e) {
            error_log('[yedek_arayuz] Drive listesi alinamadi: ' . $e->getMessage());
            return ['durum' => 'hata', 'hata' => "Google Drive listesi alınamadı."] + $bos;
        }
    }

    private function driveOnbellekSil(): void
    {
        @unlink($this->dizin . '/' . self::DRIVE_ONBELLEK);
    }

    private function isLogu(string $mesaj, string $seviye = 'INFO'): void
    {
        $this->is['log'][] = ['z' => date('H:i:s'), 's' => $seviye, 'm' => $mesaj];
        if (count($this->is['log']) > self::LOG_LIMIT) {
            $this->is['log'] = array_slice($this->is['log'], -self::LOG_LIMIT);
        }
        $this->isYaz($this->is);

        $logDosya = in_array($this->is['tip'], ['geri_yukleme_testi'], true) ? 'restore.log' : 'backup.log';
        @file_put_contents(
            dirname(__DIR__, 2) . '/cron/logs/' . $logDosya,
            '[' . date('Y-m-d H:i:s') . "] [$seviye] [ARAYUZ] $mesaj" . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }

    private function isYaz(array $is): void
    {
        $dosya = $this->dizin . '/' . self::IS_DOSYASI;
        file_put_contents($dosya, json_encode($is, JSON_UNESCAPED_UNICODE), LOCK_EX);
        @chmod($dosya, 0600);
    }

    private function addanZaman(string $ad): string
    {
        if (preg_match('/_(\d{4}-\d{2}-\d{2})_(\d{2})-(\d{2})-(\d{2})\.sql\.gz$/', $ad, $m)) {
            return "{$m[1]} {$m[2]}:{$m[3]}:{$m[4]}";
        }
        return '';
    }

    private function sistemLogu(int $kullaniciId, string $mesaj, int $seviye = SystemLogModel::LEVEL_IMPORTANT): void
    {
        try {
            (new SystemLogModel())->logAction($kullaniciId, 'Veritabani Yedekleme', $mesaj, $seviye);
        } catch (Throwable $e) {
            error_log('[yedek_arayuz] system_logs kaydi olusturulamadi: ' . $e->getMessage());
        }
    }
}
