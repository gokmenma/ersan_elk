<?php

namespace App\Service;

use DateTime;
use RuntimeException;

class DriveYedekSenkronService
{
    private const UZANTI = '.enc';

    private GoogleDriveService $drive;
    private YedekSifrelemeService $sifreleme;
    private string $klasorId;

    public function __construct(?GoogleDriveService $drive = null, ?YedekSifrelemeService $sifreleme = null, ?string $klasorId = null)
    {
        $this->klasorId = (string) ($klasorId ?? ($_ENV['GDRIVE_KLASOR_ID'] ?? ''));
        if ($this->klasorId === '') {
            throw new RuntimeException('GDRIVE_KLASOR_ID .env icinde tanimli degil.');
        }
        $this->drive = $drive ?? new GoogleDriveService();
        $this->sifreleme = $sifreleme ?? new YedekSifrelemeService();
    }

    public static function yapilandirildi(): bool
    {
        foreach (['GDRIVE_CLIENT_ID', 'GDRIVE_CLIENT_SECRET', 'GDRIVE_REFRESH_TOKEN', 'GDRIVE_KLASOR_ID', 'YEDEK_SIFRELEME_ANAHTARI'] as $anahtar) {
            if (empty($_ENV[$anahtar])) {
                return false;
            }
        }
        return true;
    }

    public function senkronize(string $dizin, string $vt, int $saklamaGun, callable $log, ?int $artimliSaklamaGun = null): array
    {
        $artimliSinir = $artimliSaklamaGun !== null ? date('Y-m-d H:i:s', time() - $artimliSaklamaGun * 86400) : null;
        $uzak = [];
        foreach ($this->uzakYedekler($vt) as $dosya) {
            $uzak[$dosya['name']] = $dosya;
        }

        $yerel = [];
        foreach (glob($dizin . "/{tam,artimli}_{$vt}_*.sql.gz", GLOB_BRACE) ?: [] as $dosya) {
            $bilgi = $this->adCoz(basename($dosya) . self::UZANTI, $vt);
            if ($bilgi !== []) {
                $yerel[$dosya] = $bilgi;
            }
        }
        ksort($yerel);

        $sinir = $this->saklamaSiniri(array_merge(array_values($uzak), array_values($yerel)), $saklamaGun);

        $yuklenen = 0;
        $boyut = 0;
        foreach ($yerel as $dosya => $bilgi) {
            $uzakAd = basename($dosya) . self::UZANTI;
            if (isset($uzak[$uzakAd]) || $this->silinecekMi($bilgi, $sinir, $artimliSinir)) {
                continue;
            }

            $gecici = $dosya . self::UZANTI;
            try {
                $this->sifreleme->sifrele($dosya, $gecici);
                $md5 = md5_file($gecici);
                $sonuc = $this->drive->yukle($gecici, $uzakAd, $this->klasorId);

                if (($sonuc['md5Checksum'] ?? '') !== $md5) {
                    if (!empty($sonuc['id'])) {
                        $this->drive->sil($sonuc['id']);
                    }
                    throw new RuntimeException("Drive'a yuklenen dosyanin MD5 ozeti tutmuyor: $uzakAd");
                }

                $uzak[$uzakAd] = $sonuc + ['name' => $uzakAd] + $bilgi;
                $yuklenen++;
                $boyut += (int) ($sonuc['size'] ?? 0);
                $log(sprintf("Drive'a yuklendi: %s (%s MB, md5 dogrulandi)", $uzakAd, number_format(((int) ($sonuc['size'] ?? 0)) / 1048576, 2)));
            } finally {
                @unlink($gecici);
                @unlink($gecici . '.tmp');
            }
        }

        $silinen = 0;
        foreach ($uzak as $dosya) {
            if ($this->silinecekMi($dosya, $sinir, $artimliSinir)) {
                $this->drive->sil($dosya['id']);
                $log("Drive'dan eski yedek silindi: {$dosya['name']}");
                $silinen++;
            }
        }

        return [
            'yuklenen' => $yuklenen,
            'boyut' => $boyut,
            'silinen' => $silinen,
            'uzak_adet' => count($uzak) - $silinen,
        ];
    }

    public function uzakYedekler(string $vt): array
    {
        $liste = [];
        foreach ($this->drive->listele($this->klasorId) as $dosya) {
            $bilgi = $this->adCoz((string) $dosya['name'], $vt);
            if ($bilgi !== []) {
                $liste[] = $dosya + $bilgi;
            }
        }
        usort($liste, static fn(array $a, array $b): int => strcmp($a['zaman'], $b['zaman']));

        return $liste;
    }

    public function indirVeCoz(array $uzakDosya, string $hedefDizin): string
    {
        $hedef = rtrim($hedefDizin, '/') . '/' . substr($uzakDosya['name'], 0, -strlen(self::UZANTI));
        $gecici = $hedef . self::UZANTI;

        try {
            $this->drive->indir($uzakDosya['id'], $gecici);
            if (!empty($uzakDosya['md5Checksum']) && md5_file($gecici) !== $uzakDosya['md5Checksum']) {
                throw new RuntimeException('Indirilen dosyanin MD5 ozeti tutmuyor: ' . $uzakDosya['name']);
            }
            $this->sifreleme->coz($gecici, $hedef);
            @chmod($hedef, 0600);
        } finally {
            @unlink($gecici);
        }

        return $hedef;
    }

    private function silinecekMi(array $dosya, ?string $sinir, ?string $artimliSinir): bool
    {
        if ($sinir !== null && $dosya['zaman'] < $sinir) {
            return true;
        }
        return $dosya['tip'] === 'artimli' && $artimliSinir !== null && $dosya['zaman'] < $artimliSinir;
    }

    private function saklamaSiniri(array $dosyalar, int $saklamaGun): ?string
    {
        $tamlar = array_values(array_filter($dosyalar, static fn(array $d): bool => $d['tip'] === 'tam'));
        if ($tamlar === []) {
            return null;
        }
        usort($tamlar, static fn(array $a, array $b): int => strcmp($a['zaman'], $b['zaman']));

        $esik = date('Y-m-d H:i:s', time() - $saklamaGun * 86400);
        foreach ($tamlar as $tam) {
            if ($tam['zaman'] >= $esik) {
                return $tam['zaman'];
            }
        }

        return end($tamlar)['zaman'];
    }

    private function adCoz(string $ad, string $vt): array
    {
        $kalip = '/^(tam|artimli)_' . preg_quote($vt, '/') . '_(\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2})\.sql\.gz' . preg_quote(self::UZANTI, '/') . '$/';
        if (!preg_match($kalip, $ad, $m)) {
            return [];
        }

        $zaman = DateTime::createFromFormat('Y-m-d_H-i-s', $m[2]);
        if ($zaman === false) {
            return [];
        }

        return ['tip' => $m[1], 'zaman' => $zaman->format('Y-m-d H:i:s')];
    }
}
