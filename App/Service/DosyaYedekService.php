<?php

namespace App\Service;

use App\Model\SystemLogModel;
use RuntimeException;
use Throwable;

class DosyaYedekService
{
    public const SURUM = 1;
    public const MANIFEST = '.yedek/manifest.json';
    public const AD_KALIBI = '/^dosya_(tam|artimli)_(\d{4}-\d{2}-\d{2})_(\d{2})-(\d{2})-(\d{2})\.tar\.gz$/';

    private const DURUM = 'dosya_durum.json';
    private const ENVANTER = 'dosya_envanter.json.gz';
    private const KILIT = '.dosya.lock';
    private const PARCA = 1048576;
    private const DOGRULAMA_LIMIT = 30;

    private array $ayar;
    private string $dizin;
    private string $kok;
    private $logCagri = null;

    public function __construct(?array $ayar = null)
    {
        $this->ayar = $ayar ?? VeritabaniYedekService::ayarlar();
        $this->dizin = rtrim($this->ayar['yedek_dizini'], '/');
        $this->kok = realpath(dirname(__DIR__, 2)) ?: dirname(__DIR__, 2);
    }

    public static function adCoz(string $ad): ?array
    {
        if (!preg_match(self::AD_KALIBI, $ad, $m)) {
            return null;
        }
        return ['tip' => $m[1], 'zaman' => "{$m[2]} {$m[3]}:{$m[4]}:{$m[5]}"];
    }

    public static function dosyaAdiGecerliMi(string $ad): bool
    {
        return self::adCoz($ad) !== null;
    }

    public function yedekDizini(): string
    {
        return $this->dizin;
    }

    public function kilitAl()
    {
        $this->dizinHazirla();
        $kilit = fopen($this->dizin . '/' . self::KILIT, 'c');
        if ($kilit === false) {
            throw new RuntimeException('Dosya yedek kilidi acilamadi.');
        }
        if (!flock($kilit, LOCK_EX | LOCK_NB)) {
            fclose($kilit);
            return null;
        }
        return $kilit;
    }

    public function kilitBirak($kilit): void
    {
        if (is_resource($kilit)) {
            flock($kilit, LOCK_UN);
            fclose($kilit);
        }
    }

    public function durumOku(): array
    {
        $dosya = $this->dizin . '/' . self::DURUM;
        if (!is_file($dosya)) {
            return [];
        }
        $veri = json_decode((string) file_get_contents($dosya), true);
        return is_array($veri) ? $veri : [];
    }

    public function yedekler(): array
    {
        $durum = $this->durumOku();
        $liste = [];
        foreach (glob($this->dizin . '/dosya_{tam,artimli}_*.tar.gz', GLOB_BRACE) ?: [] as $yol) {
            $ad = basename($yol);
            $bilgi = self::adCoz($ad);
            if ($bilgi === null) {
                continue;
            }
            $liste[] = $bilgi + [
                'ad' => $ad,
                'yol' => $yol,
                'boyut' => (int) filesize($yol),
                'meta' => $durum['arsivler'][$ad] ?? null,
                'dogrulama' => $durum['dogrulamalar'][$ad] ?? null,
            ];
        }
        usort($liste, static fn(array $a, array $b): int => strcmp($a['zaman'], $b['zaman']));
        return $liste;
    }

    public function yedekAl(array $secenek, callable $log): array
    {
        $this->logCagri = $log;
        $secenek += ['tam' => false, 'artimli' => false, 'drive' => true, 'kullanici_id' => 0];
        $kullaniciId = (int) $secenek['kullanici_id'];
        $basladi = microtime(true);
        $sonuc = ['basarili' => false, 'dosya' => null, 'hata' => null];

        $kilit = $this->kilitAl();
        if ($kilit === null) {
            $this->yaz('Baska bir dosya yedekleme islemi suruyor, bu calisma atlandi.', 'WARNING');
            return ['basarili' => true, 'atlandi' => true, 'dosya' => null, 'hata' => null];
        }

        $gecici = null;
        try {
            $durum = $this->durumOku();
            $eskiEnvanter = $this->envanterOku();
            $sonTam = (string) ($durum['son_tam']['dosya'] ?? '');

            $tamMi = (bool) $secenek['tam'];
            if (!$tamMi && !$secenek['artimli']) {
                $tamMi = $sonTam === ''
                    || ((int) date('w') === (int) ($this->ayar['dosya_tam_yedek_gunu'] ?? 0)
                        && substr((string) ($durum['son_tam']['zaman'] ?? ''), 0, 10) !== date('Y-m-d'));
            }
            if (!$tamMi && ($sonTam === '' || !is_file($this->dizin . '/' . $sonTam))) {
                $this->yaz('Referans dosya tam yedegi bulunamadi, tam yedek aliniyor.', 'WARNING');
                $tamMi = true;
            }
            if (!$tamMi && $eskiEnvanter === null) {
                $this->yaz('Dosya envanteri bulunamadi, tam yedek aliniyor.', 'WARNING');
                $tamMi = true;
            }

            $tur = $tamMi ? 'tam' : 'artimli';
            $this->yaz("--- DOSYA YEDEKLEME BASLADI (tur: $tur) ---");

            [$envanter, $kaynaklar, $dizinler, $okunamayan] = $this->tara();
            $toplamHam = array_sum(array_column($envanter, 0));
            $this->yaz(sprintf('Taranan: %s dosya, %s MB (%s)', number_format(count($envanter), 0, ',', '.'),
                number_format($toplamHam / 1048576, 2), implode(', ', $kaynaklar)));

            $eski = $tamMi ? [] : $eskiEnvanter;
            $secilen = [];
            foreach ($envanter as $yolu => $bilgi) {
                if (!isset($eski[$yolu]) || $eski[$yolu] !== $bilgi) {
                    $secilen[$yolu] = $bilgi;
                }
            }
            $silinen = $tamMi ? [] : array_map('strval', array_keys(array_diff_key($eski, $envanter)));

            if (!$tamMi && $secilen === [] && $silinen === []) {
                $this->yaz('Son yedekten bu yana degisen dosya yok, arsiv olusturulmadi.');
                $durum['son_kontrol'] = date('Y-m-d H:i:s');
                $this->durumYaz($durum);
                $sonuc = ['basarili' => true, 'dosya' => null, 'hata' => null, 'degisiklik_yok' => true];
            } else {
                $secilenBoyut = array_sum(array_column($secilen, 0));
                $bos = @disk_free_space($this->dizin);
                if ($bos !== false && $bos < $secilenBoyut + 100 * 1048576) {
                    throw new RuntimeException(sprintf('Yedek dizininde yeterli bos alan yok (bos: %s MB, gereken: ~%s MB).',
                        number_format($bos / 1048576, 0), number_format(($secilenBoyut + 100 * 1048576) / 1048576, 0)));
                }

                $zaman = date('Y-m-d H:i:s');
                $ad = 'dosya_' . $tur . '_' . str_replace([' ', ':'], ['_', '-'], $zaman) . '.tar.gz';
                $hedef = $this->dizin . '/' . $ad;
                $gecici = $hedef . '.tmp';

                $seviye = max(1, min(9, (int) ($this->ayar['dosya_sikistirma_seviyesi'] ?? 3)));
                $gz = gzopen($gecici, 'wb' . $seviye);
                if ($gz === false) {
                    throw new RuntimeException('Arsiv dosyasi olusturulamadi: ' . $gecici);
                }

                $yazilan = 0;
                $yazilanBoyut = 0;
                $atlanan = [];
                try {
                    foreach ($secilen as $yolu => $bilgi) {
                        $boyut = $this->dosyaYaz($gz, $yolu, $this->kok . '/' . $yolu, $bilgi[0], $bilgi[1]);
                        if ($boyut === null) {
                            $atlanan[] = $yolu;
                            continue;
                        }
                        $yazilan++;
                        $yazilanBoyut += $boyut;
                    }

                    $manifest = [
                        'surum' => self::SURUM,
                        'tip' => $tur,
                        'zaman' => $zaman,
                        'temel' => $tamMi ? null : $sonTam,
                        'kaynaklar' => $kaynaklar,
                        'dizinler' => $dizinler,
                        'okunamayan' => $okunamayan,
                        'dosya_adet' => $yazilan,
                        'toplam_boyut' => $yazilanBoyut,
                        'envanter_adet' => count($envanter),
                        'silinen' => $silinen,
                        'atlanan' => $atlanan,
                    ];
                    $this->icerikYaz($gz, self::MANIFEST, json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), time());
                    $this->gzTamYaz($gz, str_repeat("\0", 1024));
                } finally {
                    if (!gzclose($gz)) {
                        throw new RuntimeException('Arsiv dosyasi kapatilamadi (disk dolu olabilir).');
                    }
                }

                $sha = hash_file('sha256', $gecici);
                if (!rename($gecici, $hedef)) {
                    throw new RuntimeException('Arsiv dosyasi yerine tasinamadi: ' . $hedef);
                }
                $gecici = null;
                @chmod($hedef, 0600);

                foreach ($atlanan as $yolu) {
                    if (isset($eski[$yolu])) {
                        $envanter[$yolu] = $eski[$yolu];
                    } else {
                        unset($envanter[$yolu]);
                    }
                }
                $this->envanterYaz($envanter);

                $boyutArsiv = (int) filesize($hedef);
                $durum = $this->durumOku();
                $durum['son_yedek'] = ['dosya' => $ad, 'zaman' => $zaman, 'tip' => $tur];
                $durum['son_kontrol'] = $zaman;
                if ($tamMi) {
                    $durum['son_tam'] = ['dosya' => $ad, 'zaman' => $zaman];
                }
                $durum['arsivler'][$ad] = [
                    'tip' => $tur,
                    'zaman' => $zaman,
                    'temel' => $manifest['temel'],
                    'sha256' => $sha,
                    'boyut' => $boyutArsiv,
                    'dosya_adet' => $yazilan,
                    'toplam_boyut' => $yazilanBoyut,
                    'silinen_adet' => count($silinen),
                    'atlanan_adet' => count($atlanan),
                    'okunamayan_adet' => count($okunamayan),
                ];
                $this->durumYaz($durum);

                $sure = round(microtime(true) - $basladi, 1);
                $this->yaz(sprintf('Arsiv olusturuldu: %s (%s MB, %s dosya, %s silinen, %s sn)', $ad,
                    number_format($boyutArsiv / 1048576, 2), number_format($yazilan, 0, ',', '.'), count($silinen), $sure));
                if ($atlanan !== []) {
                    $this->yaz(count($atlanan) . ' dosya okunamadigi icin atlandi; bir sonraki calismada tekrar denenecek.', 'WARNING');
                }

                $this->sistemLogu($kullaniciId, sprintf('Dosya yedegi alindi: %s (%s dosya, %s MB)', $ad, $yazilan,
                    number_format($boyutArsiv / 1048576, 2)));

                $sonuc = ['basarili' => true, 'dosya' => $ad, 'hata' => null, 'dosya_adet' => $yazilan, 'boyut' => $boyutArsiv];
            }

            $silinenArsiv = $this->rotasyon();
            $this->yaz("Rotasyon: $silinenArsiv eski dosya yedegi silindi.");
        } catch (Throwable $e) {
            error_log('[dosya_yedek] ' . $e->getMessage());
            $this->yaz('Dosya yedegi basarisiz: ' . VeritabaniYedekService::kullaniciMesaji($e), 'ERROR');
            $this->sistemLogu($kullaniciId, 'Dosya yedegi basarisiz: ' . VeritabaniYedekService::kullaniciMesaji($e), SystemLogModel::LEVEL_CRITICAL);
            $sonuc = ['basarili' => false, 'dosya' => null, 'hata' => VeritabaniYedekService::kullaniciMesaji($e)];
        } finally {
            if ($gecici !== null) {
                @unlink($gecici);
            }
            $this->kilitBirak($kilit);
        }

        if ($sonuc['basarili'] && $secenek['drive'] && !empty($this->ayar['drive_yedekleme'])) {
            $drive = $this->driveSenkronize($kullaniciId);
            $sonuc['drive'] = $drive;
            if (($drive['durum'] ?? '') === 'hata') {
                $sonuc['basarili'] = false;
                $sonuc['hata'] = $drive['hata'];
            }
        }

        $this->yaz('--- DOSYA YEDEKLEME BITTI ---');
        return $sonuc;
    }

    public function driveSenkronize(int $kullaniciId, ?callable $log = null): array
    {
        if ($log !== null) {
            $this->logCagri = $log;
        }
        if (!DriveYedekSenkronService::yapilandirildi()) {
            $this->yaz('Google Drive yapilandirilmamis, dosya yedeklerinin uzak kopyasi atlandi.', 'WARNING');
            return ['durum' => 'yapilandirilmamis'];
        }

        try {
            $this->yaz('--- DOSYA YEDEKLERI GOOGLE DRIVE SENKRONU BASLADI ---');
            $sonuc = (new DriveYedekSenkronService())->dosyaSenkronize(
                $this->dizin,
                (int) ($this->ayar['dosya_drive_saklama_gun'] ?? 60),
                function (string $mesaj): void {
                    $this->yaz($mesaj);
                },
                isset($this->ayar['dosya_drive_artimli_saklama_gun']) ? (int) $this->ayar['dosya_drive_artimli_saklama_gun'] : null
            );
            $this->yaz(sprintf("Drive senkronu bitti: %d dosya yuklendi (%s MB), %d eski dosya silindi, Drive'da %d dosya yedegi var.",
                $sonuc['yuklenen'], number_format($sonuc['boyut'] / 1048576, 2), $sonuc['silinen'], $sonuc['uzak_adet']));
            $this->sistemLogu($kullaniciId, sprintf('Dosya yedekleri Drive senkronu: %d yuklendi, %d silindi, toplam %d uzak yedek',
                $sonuc['yuklenen'], $sonuc['silinen'], $sonuc['uzak_adet']));

            $durum = $this->durumOku();
            $durum['son_drive'] = ['zaman' => date('Y-m-d H:i:s'), 'uzak_adet' => $sonuc['uzak_adet']];
            $this->durumYaz($durum);

            return ['durum' => 'tamam'] + $sonuc;
        } catch (Throwable $e) {
            error_log('[dosya_yedek] Drive: ' . $e->getMessage());
            $this->yaz('Dosya yedeklerinin Drive senkronu basarisiz (yerel yedek saglam, bir sonraki calismada tekrar denenecek): '
                . VeritabaniYedekService::kullaniciMesaji($e), 'ERROR');
            return ['durum' => 'hata', 'hata' => VeritabaniYedekService::kullaniciMesaji($e)];
        }
    }

    public function dogrula(string $ad, callable $log, int $kullaniciId = 0): array
    {
        $this->logCagri = $log;
        $yol = $this->yerelYol($ad);
        $basladi = microtime(true);
        $meta = $this->durumOku()['arsivler'][$ad] ?? null;
        $sonuc = ['dosya' => $ad, 'zaman' => date('Y-m-d H:i:s'), 'sonuc' => 'hata', 'hata' => null];

        try {
            $this->yaz("Dogrulaniyor: $ad");
            if ($meta !== null && !empty($meta['sha256'])) {
                if (!hash_equals($meta['sha256'], hash_file('sha256', $yol))) {
                    throw new RuntimeException('SHA-256 ozeti yedek anindaki degerle eslesmiyor; dosya degismis veya bozulmus.');
                }
                $this->yaz('SHA-256 ozeti eslesti.');
            } else {
                $this->yaz('Yedek anina ait SHA-256 kaydi yok, yalnizca arsiv yapisi dogrulanacak.', 'WARNING');
            }

            $adet = 0;
            $boyut = 0;
            $manifest = $this->arsivGez($yol, function (string $yolu, int $b) use (&$adet, &$boyut) {
                $adet++;
                $boyut += $b;
                return null;
            });

            if ($manifest === null) {
                throw new RuntimeException('Arsivde manifest bulunamadi; dosya yarim kalmis olabilir.');
            }
            if ((int) $manifest['dosya_adet'] !== $adet || (int) $manifest['toplam_boyut'] !== $boyut) {
                throw new RuntimeException(sprintf('Arsiv icerigi manifestle eslesmiyor (beklenen %d dosya, bulunan %d).',
                    $manifest['dosya_adet'], $adet));
            }

            $sonuc = [
                'dosya' => $ad,
                'zaman' => date('Y-m-d H:i:s'),
                'sonuc' => 'basarili',
                'hata' => null,
                'dosya_adet' => $adet,
                'toplam_boyut' => $boyut,
                'silinen_adet' => count($manifest['silinen'] ?? []),
                'sha_kontrol' => $meta !== null && !empty($meta['sha256']),
            ];
            $this->yaz(sprintf('Dogrulama basarili: %s dosya, %s MB acildi, manifest ile birebir eslesti.',
                number_format($adet, 0, ',', '.'), number_format($boyut / 1048576, 2)));
        } catch (Throwable $e) {
            error_log('[dosya_yedek] dogrulama: ' . $e->getMessage());
            $sonuc['hata'] = VeritabaniYedekService::kullaniciMesaji($e);
            $this->yaz('Dogrulama basarisiz: ' . $sonuc['hata'], 'ERROR');
        }

        $sonuc['sure'] = round(microtime(true) - $basladi, 1);
        $durum = $this->durumOku();
        $durum['dogrulamalar'][$ad] = $sonuc;
        if (count($durum['dogrulamalar']) > self::DOGRULAMA_LIMIT) {
            uasort($durum['dogrulamalar'], static fn($a, $b) => strcmp($b['zaman'], $a['zaman']));
            $durum['dogrulamalar'] = array_slice($durum['dogrulamalar'], 0, self::DOGRULAMA_LIMIT, true);
        }
        $this->durumYaz($durum);
        $this->sistemLogu($kullaniciId, "Dosya yedegi dogrulandi: $ad ({$sonuc['sonuc']})");

        return $sonuc;
    }

    public function zincir(?string $zaman = null, ?string $ad = null): array
    {
        $yedekler = $this->yedekler();
        if ($ad !== null) {
            $hedef = null;
            foreach ($yedekler as $y) {
                if ($y['ad'] === $ad) {
                    $hedef = $y;
                }
            }
            if ($hedef === null) {
                throw new RuntimeException("Dosya yedegi bulunamadi: $ad");
            }
            $zaman = $hedef['zaman'];
        }
        $zaman ??= date('Y-m-d H:i:s');

        $adaylar = array_values(array_filter($yedekler, static fn($y) => $y['zaman'] <= $zaman));
        $tamlar = array_values(array_filter($adaylar, static fn($y) => $y['tip'] === 'tam'));
        if ($tamlar === []) {
            throw new RuntimeException("$zaman oncesine ait dosya tam yedegi bulunamadi.");
        }
        $temel = end($tamlar);

        $zincir = [$temel];
        foreach ($adaylar as $y) {
            if ($y['tip'] !== 'artimli' || $y['zaman'] <= $temel['zaman']) {
                continue;
            }
            $bagli = $y['meta']['temel'] ?? null;
            if ($bagli !== null && $bagli !== $temel['ad']) {
                continue;
            }
            $zincir[] = $y;
        }
        return $zincir;
    }

    public function geriYukle(array $zincir, string $hedefDizin, array $secenek, callable $log): array
    {
        $this->logCagri = $log;
        $secenek += ['onek' => '', 'onayla' => false, 'kullanici_id' => 0];
        $onek = trim((string) $secenek['onek'], '/');
        $hedefDizin = rtrim($hedefDizin, '/');

        if ($secenek['onayla'] && !is_dir($hedefDizin) && !@mkdir($hedefDizin, 0750, true)) {
            throw new RuntimeException('Hedef dizin olusturulamadi: ' . $hedefDizin);
        }

        $kapsam = static function (string $yolu) use ($onek): bool {
            return $onek === '' || $yolu === $onek || str_starts_with($yolu, $onek . '/');
        };

        $toplam = ['yazilan' => 0, 'silinen' => 0, 'boyut' => 0];
        foreach ($zincir as $arsiv) {
            $meta = $arsiv['meta'] ?? null;
            if ($meta !== null && !empty($meta['sha256']) && !hash_equals($meta['sha256'], hash_file('sha256', $arsiv['yol']))) {
                throw new RuntimeException("SHA-256 ozeti eslesmiyor, geri yukleme durduruldu: {$arsiv['ad']}");
            }

            $yazilan = 0;
            $boyut = 0;
            $manifest = $this->arsivGez($arsiv['yol'], function (string $yolu, int $b, int $mtime) use ($secenek, $hedefDizin, $kapsam, &$yazilan, &$boyut) {
                if (!$kapsam($yolu)) {
                    return null;
                }
                $yazilan++;
                $boyut += $b;
                if (!$secenek['onayla']) {
                    return null;
                }
                $hedef = $hedefDizin . '/' . $yolu;
                $ust = dirname($hedef);
                if (!is_dir($ust) && !@mkdir($ust, 0755, true)) {
                    throw new RuntimeException('Dizin olusturulamadi: ' . $ust);
                }
                $tmp = $hedef . '.geri-yukleme.tmp';
                $fh = fopen($tmp, 'wb');
                if ($fh === false) {
                    throw new RuntimeException('Dosya yazilamadi: ' . $hedef);
                }
                return [$fh, static function () use ($fh, $tmp, $hedef, $mtime): void {
                    fclose($fh);
                    if (!rename($tmp, $hedef)) {
                        @unlink($tmp);
                        throw new RuntimeException('Dosya yerine tasinamadi: ' . $hedef);
                    }
                    @chmod($hedef, 0644);
                    @touch($hedef, $mtime);
                }];
            });

            if ($manifest === null) {
                throw new RuntimeException("Arsivde manifest yok, geri yukleme durduruldu: {$arsiv['ad']}");
            }

            if ($secenek['onayla']) {
                foreach ($manifest['dizinler'] ?? [] as $yolu) {
                    if (is_string($yolu) && $this->yolGuvenliMi($yolu) && $kapsam($yolu) && !is_dir($hedefDizin . '/' . $yolu)) {
                        @mkdir($hedefDizin . '/' . $yolu, 0755, true);
                    }
                }
            }

            $silinen = 0;
            foreach ($manifest['silinen'] ?? [] as $yolu) {
                if (!is_string($yolu) || !$this->yolGuvenliMi($yolu) || !$kapsam($yolu)) {
                    continue;
                }
                if ($secenek['onayla']) {
                    $hedef = $hedefDizin . '/' . $yolu;
                    if (is_file($hedef) && @unlink($hedef)) {
                        $silinen++;
                    }
                } else {
                    $silinen++;
                }
            }

            $this->yaz(sprintf('%s %s: %s dosya (%s MB), %s silme', $secenek['onayla'] ? 'Uygulandi' : 'Plan', $arsiv['ad'],
                number_format($yazilan, 0, ',', '.'), number_format($boyut / 1048576, 2), $silinen));
            $toplam['yazilan'] += $yazilan;
            $toplam['silinen'] += $silinen;
            $toplam['boyut'] += $boyut;
        }

        if ($secenek['onayla']) {
            $this->sistemLogu((int) $secenek['kullanici_id'], sprintf('Dosya yedegi geri yuklendi: %s -> %s (%d dosya%s)',
                end($zincir)['ad'], $hedefDizin, $toplam['yazilan'], $onek !== '' ? ", kapsam: $onek" : ''), SystemLogModel::LEVEL_CRITICAL);
        }

        return $toplam;
    }

    public function raporMailiGonder(bool $hataVar, string $logSatirlari): void
    {
        if (empty($this->ayar['mail_gonder']) || empty($this->ayar['mail_alicilar'])) {
            return;
        }
        $durum = $this->durumOku();
        $bugun = date('Y-m-d');
        if (!$hataVar && ($durum['son_basari_maili'] ?? '') === $bugun) {
            return;
        }

        try {
            $baslik = ($hataVar ? '[HATA] ' : '') . 'Dosya Yedekleme - ' . date('d.m.Y H:i');
            $icerik = '<h3>Kullanici Dosyalari Yedekleme Raporu</h3><pre style="background:#f4f4f4;padding:10px;border:1px solid #ddd">'
                . htmlspecialchars($logSatirlari, ENT_QUOTES, 'UTF-8') . '</pre>';
            MailGonderService::gonder($this->ayar['mail_alicilar'], $baslik, $icerik);
            if (!$hataVar) {
                $durum = $this->durumOku();
                $durum['son_basari_maili'] = $bugun;
                $this->durumYaz($durum);
            }
        } catch (Throwable $e) {
            error_log('[dosya_yedek] Mail gonderilemedi: ' . $e->getMessage());
        }
    }

    public function yerelYol(string $ad): string
    {
        if (!self::dosyaAdiGecerliMi($ad)) {
            throw new RuntimeException('Gecersiz dosya yedegi adi.');
        }
        $yol = realpath($this->dizin . '/' . $ad);
        $dizin = realpath($this->dizin);
        if ($yol === false || $dizin === false || dirname($yol) !== $dizin || !is_file($yol)) {
            throw new RuntimeException('Dosya yedegi bulunamadi.');
        }
        return $yol;
    }

    private function tara(): array
    {
        $envanter = [];
        $dizinler = [];
        $okunamayan = [];
        $kaynaklar = [];
        $yedekDizin = realpath($this->dizin) ?: $this->dizin;
        $haric = (array) ($this->ayar['dosya_haric'] ?? []);

        foreach ((array) ($this->ayar['dosya_kaynaklari'] ?? []) as $kaynak) {
            $kaynak = trim((string) $kaynak, '/');
            if ($kaynak === '' || !$this->yolGuvenliMi($kaynak)) {
                continue;
            }
            $tam = $this->kok . '/' . $kaynak;
            if (!is_dir($tam) || is_link($tam)) {
                continue;
            }
            $kaynaklar[] = $kaynak;

            $bekleyen = [$kaynak];
            while ($bekleyen !== []) {
                $goreli = array_pop($bekleyen);
                $mutlak = $this->kok . '/' . $goreli;
                if ($mutlak === $yedekDizin || str_starts_with($mutlak, $yedekDizin . '/')) {
                    continue;
                }
                $icerik = @scandir($mutlak);
                if ($icerik === false) {
                    $okunamayan[] = $goreli;
                    continue;
                }
                $dizinler[] = $goreli;

                foreach ($icerik as $ad) {
                    if ($ad === '.' || $ad === '..') {
                        continue;
                    }
                    $yolu = $goreli . '/' . $ad;
                    $yol = $this->kok . '/' . $yolu;
                    if (is_link($yol) || str_ends_with($ad, '.geri-yukleme.tmp')) {
                        continue;
                    }
                    foreach ($haric as $kalip) {
                        if (fnmatch($kalip, $yolu) || fnmatch($kalip, $ad)) {
                            continue 2;
                        }
                    }
                    if (is_dir($yol)) {
                        $bekleyen[] = $yolu;
                        continue;
                    }
                    if (!is_file($yol)) {
                        continue;
                    }
                    if (!is_readable($yol)) {
                        $okunamayan[] = $yolu;
                        continue;
                    }
                    $envanter[$yolu] = [(int) filesize($yol), (int) filemtime($yol)];
                }
            }
        }

        if ($okunamayan !== []) {
            $this->yaz(sprintf('%d dosya/klasor okuma izni olmadigi icin yedege alinamadi: %s', count($okunamayan),
                implode(', ', array_slice($okunamayan, 0, 10)) . (count($okunamayan) > 10 ? ' ...' : '')), 'WARNING');
        }

        ksort($envanter, SORT_STRING);
        sort($dizinler, SORT_STRING);
        return [$envanter, $kaynaklar, $dizinler, $okunamayan];
    }

    private function dosyaYaz($gz, string $ad, string $yol, int $boyut, int $mtime): ?int
    {
        $fh = @fopen($yol, 'rb');
        if ($fh === false) {
            $this->yaz("Okunamadi, atlandi: $ad", 'WARNING');
            return null;
        }

        try {
            $this->baslikYaz($gz, $ad, $boyut, $mtime);
            $kalan = $boyut;
            while ($kalan > 0) {
                $parca = fread($fh, min(self::PARCA, $kalan));
                if ($parca === false || $parca === '') {
                    $this->yaz("Okuma sirasinda dosya kisaldi, sifirla tamamlandi: $ad", 'WARNING');
                    while ($kalan > 0) {
                        $n = min(self::PARCA, $kalan);
                        $this->gzTamYaz($gz, str_repeat("\0", $n));
                        $kalan -= $n;
                    }
                    break;
                }
                $this->gzTamYaz($gz, $parca);
                $kalan -= strlen($parca);
            }
            $this->dolguYaz($gz, $boyut);
        } finally {
            fclose($fh);
        }
        return $boyut;
    }

    private function icerikYaz($gz, string $ad, string $icerik, int $mtime): void
    {
        $this->baslikYaz($gz, $ad, strlen($icerik), $mtime);
        $this->gzTamYaz($gz, $icerik);
        $this->dolguYaz($gz, strlen($icerik));
    }

    private function baslikYaz($gz, string $ad, int $boyut, int $mtime): void
    {
        if (strlen($ad) > 100) {
            $uzun = $ad . "\0";
            $this->gzTamYaz($gz, $this->tarBaslik('././@LongLink', strlen($uzun), 0, 'L'));
            $this->gzTamYaz($gz, $uzun);
            $this->dolguYaz($gz, strlen($uzun));
            $ad = substr($ad, 0, 100);
        }
        $this->gzTamYaz($gz, $this->tarBaslik($ad, $boyut, $mtime, '0'));
    }

    private function tarBaslik(string $ad, int $boyut, int $mtime, string $tip): string
    {
        $baslik = str_pad($ad, 100, "\0")
            . sprintf('%07o', 0644) . "\0"
            . sprintf('%07o', 0) . "\0"
            . sprintf('%07o', 0) . "\0"
            . sprintf('%011o', $boyut) . "\0"
            . sprintf('%011o', max(0, $mtime)) . "\0"
            . '        '
            . $tip
            . str_repeat("\0", 100)
            . "ustar  \0"
            . str_pad('root', 32, "\0")
            . str_pad('root', 32, "\0")
            . str_repeat("\0", 8)
            . str_repeat("\0", 8)
            . str_repeat("\0", 155);
        $baslik = str_pad($baslik, 512, "\0");

        $toplam = array_sum(unpack('C*', $baslik));
        return substr_replace($baslik, sprintf('%06o', $toplam) . "\0 ", 148, 8);
    }

    private function dolguYaz($gz, int $boyut): void
    {
        $kalan = $boyut % 512;
        if ($kalan > 0) {
            $this->gzTamYaz($gz, str_repeat("\0", 512 - $kalan));
        }
    }

    private function gzTamYaz($gz, string $veri): void
    {
        if ($veri === '') {
            return;
        }
        if (gzwrite($gz, $veri) !== strlen($veri)) {
            throw new RuntimeException('Arsive yazilamadi (disk dolu olabilir).');
        }
    }

    private function arsivGez(string $yol, callable $hedefSec): ?array
    {
        $gz = gzopen($yol, 'rb');
        if ($gz === false) {
            throw new RuntimeException('Arsiv acilamadi: ' . basename($yol));
        }

        $manifest = null;
        $uzunAd = null;
        $sonaErdi = false;
        try {
            while (true) {
                $baslik = $this->gzTamOku($gz, 512);
                if ($baslik === null) {
                    break;
                }
                if (trim($baslik, "\0") === '') {
                    $sonaErdi = true;
                    break;
                }

                $beklenen = octdec(trim(substr($baslik, 148, 8), " \0"));
                $toplam = array_sum(unpack('C*', substr_replace($baslik, '        ', 148, 8)));
                if ($beklenen !== $toplam) {
                    throw new RuntimeException('Arsiv basligi bozuk (checksum hatasi): ' . basename($yol));
                }

                $ad = rtrim(substr($baslik, 0, 100), "\0");
                $onek = rtrim(substr($baslik, 345, 155), "\0");
                if ($onek !== '' && substr($baslik, 257, 6) === "ustar\0") {
                    $ad = $onek . '/' . $ad;
                }
                $boyut = (int) octdec(trim(substr($baslik, 124, 12), " \0"));
                $mtime = (int) octdec(trim(substr($baslik, 136, 12), " \0"));
                $tip = $baslik[156];
                $dolgu = (512 - $boyut % 512) % 512;

                if ($tip === 'L') {
                    $uzunAd = rtrim((string) $this->gzTamOku($gz, $boyut + $dolgu), "\0");
                    continue;
                }
                if ($uzunAd !== null) {
                    $ad = $uzunAd;
                    $uzunAd = null;
                }

                if ($tip !== '0' && $tip !== "\0") {
                    $this->atla($gz, $boyut + $dolgu);
                    continue;
                }

                if ($ad === self::MANIFEST) {
                    $icerik = (string) $this->gzTamOku($gz, $boyut);
                    $this->atla($gz, $dolgu);
                    $manifest = json_decode($icerik, true);
                    if (!is_array($manifest)) {
                        throw new RuntimeException('Arsiv manifesti okunamadi: ' . basename($yol));
                    }
                    continue;
                }

                if (!$this->yolGuvenliMi($ad)) {
                    throw new RuntimeException('Arsivde guvensiz dosya yolu var, islem durduruldu: ' . $ad);
                }

                $hedef = $hedefSec($ad, $boyut, $mtime);
                $fh = is_array($hedef) ? $hedef[0] : null;
                $bitir = is_array($hedef) ? $hedef[1] : null;

                $kalan = $boyut;
                while ($kalan > 0) {
                    $parca = gzread($gz, min(self::PARCA, $kalan));
                    if ($parca === false || $parca === '') {
                        throw new RuntimeException('Arsiv beklenmedik sekilde sona erdi: ' . basename($yol));
                    }
                    if ($fh !== null && fwrite($fh, $parca) !== strlen($parca)) {
                        throw new RuntimeException('Geri yuklenen dosya yazilamadi: ' . $ad);
                    }
                    $kalan -= strlen($parca);
                }
                if ($bitir !== null) {
                    $bitir();
                }
                $this->atla($gz, $dolgu);
            }
        } finally {
            gzclose($gz);
        }

        if (!$sonaErdi) {
            throw new RuntimeException('Arsiv sonlandirma blogu eksik, dosya yarim kalmis: ' . basename($yol));
        }
        return $manifest;
    }

    private function gzTamOku($gz, int $uzunluk): ?string
    {
        if ($uzunluk === 0) {
            return '';
        }
        $veri = '';
        while (strlen($veri) < $uzunluk) {
            $parca = gzread($gz, $uzunluk - strlen($veri));
            if ($parca === false || $parca === '') {
                break;
            }
            $veri .= $parca;
        }
        if ($veri === '') {
            return null;
        }
        if (strlen($veri) < $uzunluk) {
            throw new RuntimeException('Arsiv beklenmedik sekilde sona erdi.');
        }
        return $veri;
    }

    private function atla($gz, int $uzunluk): void
    {
        while ($uzunluk > 0) {
            $parca = gzread($gz, min(self::PARCA, $uzunluk));
            if ($parca === false || $parca === '') {
                throw new RuntimeException('Arsiv beklenmedik sekilde sona erdi.');
            }
            $uzunluk -= strlen($parca);
        }
    }

    private function yolGuvenliMi(string $yol): bool
    {
        if ($yol === '' || str_contains($yol, "\0") || str_starts_with($yol, '/') || str_contains($yol, '\\')) {
            return false;
        }
        foreach (explode('/', $yol) as $parca) {
            if ($parca === '' || $parca === '.' || $parca === '..') {
                return false;
            }
        }
        return true;
    }

    private function rotasyon(): int
    {
        $yedekler = $this->yedekler();
        $tamlar = array_values(array_filter($yedekler, static fn($y) => $y['tip'] === 'tam'));
        if ($tamlar === []) {
            return 0;
        }

        $esik = date('Y-m-d H:i:s', time() - (int) ($this->ayar['dosya_saklama_gun'] ?? 14) * 86400);
        $korunan = array_values(array_filter($tamlar, static fn($y) => $y['zaman'] >= $esik));
        $sinir = $korunan === [] ? end($tamlar)['zaman'] : $korunan[0]['zaman'];

        $silinen = 0;
        $durum = $this->durumOku();
        foreach ($yedekler as $y) {
            if ($y['zaman'] < $sinir && @unlink($y['yol'])) {
                unset($durum['arsivler'][$y['ad']], $durum['dogrulamalar'][$y['ad']]);
                $silinen++;
            }
        }
        if ($silinen > 0) {
            $this->durumYaz($durum);
        }
        return $silinen;
    }

    private function envanterOku(): ?array
    {
        $dosya = $this->dizin . '/' . self::ENVANTER;
        if (!is_file($dosya)) {
            return null;
        }
        $icerik = @gzdecode((string) file_get_contents($dosya));
        $veri = $icerik === false ? null : json_decode($icerik, true);
        return is_array($veri) ? $veri : null;
    }

    private function envanterYaz(array $envanter): void
    {
        $dosya = $this->dizin . '/' . self::ENVANTER;
        $tmp = $dosya . '.tmp';
        if (file_put_contents($tmp, gzencode(json_encode($envanter, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 6), LOCK_EX) === false
            || !rename($tmp, $dosya)) {
            @unlink($tmp);
            throw new RuntimeException('Dosya envanteri yazilamadi.');
        }
        @chmod($dosya, 0600);
    }

    private function durumYaz(array $durum): void
    {
        $dosya = $this->dizin . '/' . self::DURUM;
        file_put_contents($dosya, json_encode($durum, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
        @chmod($dosya, 0600);
    }

    private function dizinHazirla(): void
    {
        if (!is_dir($this->dizin) && !@mkdir($this->dizin, 0750, true)) {
            throw new RuntimeException('Yedek dizini olusturulamadi: ' . $this->dizin);
        }
        if (str_starts_with(realpath($this->dizin) ?: '', $this->kok) && !is_file($this->dizin . '/.htaccess')) {
            @file_put_contents($this->dizin . '/.htaccess',
                "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n"
                . "<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n"
                . "Options -Indexes\n");
            @file_put_contents($this->dizin . '/index.html', '');
        }
    }

    private function sistemLogu(int $kullaniciId, string $mesaj, int $seviye = SystemLogModel::LEVEL_IMPORTANT): void
    {
        try {
            (new SystemLogModel())->logAction($kullaniciId, 'Dosya Yedekleme', $mesaj, $seviye);
        } catch (Throwable $e) {
            error_log('[dosya_yedek] system_logs kaydi olusturulamadi: ' . $e->getMessage());
        }
    }

    private function yaz(string $mesaj, string $seviye = 'INFO'): void
    {
        if ($this->logCagri !== null) {
            ($this->logCagri)($mesaj, $seviye);
        }
    }
}
