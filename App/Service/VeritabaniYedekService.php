<?php

namespace App\Service;

use App\Model\SystemLogModel;
use App\Model\YedeklemeModel;
use RuntimeException;
use Throwable;

class VeritabaniYedekService
{
    public const YEDEK_SURUM = 1;

    private array $ayar;
    private string $yedekDizin;
    private $logCagri;

    public function __construct(?array $ayar = null)
    {
        $this->ayar = $ayar ?? self::ayarlar();
        $this->yedekDizin = rtrim($this->ayar['yedek_dizini'], '/');
    }

    public static function ayarlar(): array
    {
        return require dirname(__DIR__, 2) . '/cron/yedekleme_ayar.php';
    }

    public static function kullaniciMesaji(Throwable $e): string
    {
        return $e instanceof \PDOException
            ? 'Veritabanı hatası oluştu (ayrıntı sunucu hata logunda).'
            : $e->getMessage();
    }

    public function yedekDizini(): string
    {
        return $this->yedekDizin;
    }

    public function kilitAl(bool $bekle = false)
    {
        $this->dizinHazirla();
        $kilit = fopen($this->yedekDizin . '/.islem.lock', 'c');
        if ($kilit === false) {
            throw new RuntimeException('Kilit dosyasi acilamadi.');
        }
        if (!flock($kilit, LOCK_EX | ($bekle ? 0 : LOCK_NB))) {
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
        $dosya = $this->yedekDizin . '/durum.json';
        if (!is_file($dosya)) {
            return [];
        }
        $veri = json_decode((string) file_get_contents($dosya), true);
        return is_array($veri) ? $veri : [];
    }

    public function yedekAl(array $secenek, callable $log): array
    {
        $this->logCagri = $log;
        $secenek += ['tam' => false, 'artimli' => false, 'tablo' => [], 'drive' => true, 'kullanici_id' => 0];
        $ayar = $this->ayar;
        $basladi = microtime(true);
        $gz = null;
        $hedef = null;

        $this->dizinHazirla();

        try {
            $model = new YedeklemeModel();
            $vt = $model->veritabaniAdi();
            $durum = $this->durumOku();
            $semaOzeti = $model->semaOzeti();
            $sunucuMaxPaket = $model->maxPaket();

            if ($ayar['paket_limiti'] > ($sunucuMaxPaket * 0.8)) {
                $ayar['paket_limiti'] = (int) max(64 * 1024, $sunucuMaxPaket * 0.5);
                $this->yaz('paket_limiti sunucu max_allowed_packet degerine gore dusuruldu: ' . $ayar['paket_limiti']);
            }

            $tamMi = (bool) $secenek['tam'];
            if (!$tamMi && !$secenek['artimli']) {
                $tamMi = empty($durum['son_tam'])
                    || ((int) date('w') === (int) $ayar['tam_yedek_gunu']
                        && substr((string) ($durum['son_tam']['zaman'] ?? ''), 0, 10) !== date('Y-m-d'));
            }

            if (!$tamMi && ($durum['sema'] ?? '') !== $semaOzeti) {
                $this->yaz('Tablo yapisi degismis, artimli yerine tam yedek aliniyor.', 'WARNING');
                $tamMi = true;
            }

            if (!$tamMi && !is_file($this->yedekDizin . '/' . ($durum['son_tam']['dosya'] ?? ''))) {
                $this->yaz('Referans tam yedek dosyasi bulunamadi, tam yedek aliniyor.', 'WARNING');
                $tamMi = true;
            }

            $tumTablolar = $model->tabloListesi();
            $viewler = $model->viewListesi();

            if (!empty($secenek['tablo'])) {
                $tumTablolar = array_values(array_intersect($tumTablolar, $secenek['tablo']));
                $viewler = array_values(array_intersect($viewler, $secenek['tablo']));
                if (empty($tumTablolar) && empty($viewler)) {
                    throw new RuntimeException('Belirtilen tablolar bulunamadi.');
                }
            }

            $artimliTablolar = $tamMi ? [] : array_values(array_intersect($ayar['artimli_tablolar'], $tumTablolar));
            $tur = $tamMi ? 'tam' : 'artimli';

            $this->yaz("--- YEDEKLEME BASLADI (tur: $tur, vt: $vt) ---");

            $model->tutarliOkumaBaslat();
            $anZamani = $model->sunucuZamani();

            $damga = str_replace([' ', ':'], ['_', '-'], $anZamani);
            $dosyaAdi = "{$tur}_{$vt}_{$damga}.sql.gz";
            $hedef = $this->yedekDizin . '/' . $dosyaAdi;

            $yeniIsaretler = $durum['isaretler'] ?? [];
            $kosullar = [];

            foreach ($artimliTablolar as $tablo) {
                $idKolon = $model->otoArtanKolon($tablo);
                $zamanKolonlari = $model->degisimKolonlari($tablo);
                $onceki = $durum['isaretler'][$tablo] ?? null;

                if ($onceki === null || ($idKolon === null && empty($zamanKolonlari))) {
                    $kosullar[$tablo] = [];
                } else {
                    $kosullar[$tablo] = [
                        'id_kolon' => $idKolon,
                        'id_deger' => $idKolon !== null ? ($onceki['id_deger'] ?? null) : null,
                        'zaman_kolonlari' => $zamanKolonlari,
                        'zaman_deger' => !empty($zamanKolonlari) ? ($onceki['zaman'] ?? null) : null,
                    ];
                }

                $yeniIsaretler[$tablo] = [
                    'id_kolon' => $idKolon,
                    'id_deger' => $idKolon !== null ? $model->enBuyukDeger($tablo, $idKolon) : null,
                    'zaman' => $anZamani,
                ];
            }

            $gz = gzopen($hedef, 'wb' . (int) $ayar['sikistirma_seviyesi']);
            if ($gz === false) {
                throw new RuntimeException('Yedek dosyasi acilamadi: ' . $hedef);
            }

            $meta = [
                'surum' => self::YEDEK_SURUM,
                'tip' => $tur,
                'vt' => $vt,
                'zaman' => $anZamani,
                'sema' => $semaOzeti,
                'temel' => $tamMi ? null : ($durum['son_tam']['dosya'] ?? null),
            ];
            gzwrite($gz, '-- @META ' . json_encode($meta, JSON_UNESCAPED_UNICODE) . "\n");

            $this->ifadeYaz($gz, '*', 'oturum', "SET FOREIGN_KEY_CHECKS = 0");
            $this->ifadeYaz($gz, '*', 'oturum', "SET UNIQUE_CHECKS = 0");
            $this->ifadeYaz($gz, '*', 'oturum', "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO'");

            $toplamSatir = 0;
            $sayim = [];

            foreach ($tumTablolar as $tablo) {
                if ($tamMi) {
                    $this->ifadeYaz($gz, $tablo, 'ddl', 'DROP TABLE IF EXISTS ' . $this->bt($tablo));
                    $this->ifadeYaz($gz, $tablo, 'ddl', $this->definerTemizle($model->olusturmaKodu($tablo)));
                }

                if (in_array($tablo, $ayar['veri_haric_tablolar'], true)) {
                    $this->yaz("Tablo atlandi (veri harici): $tablo");
                    continue;
                }

                if ($tamMi) {
                    $adet = $this->veriYaz($gz, $model, $tablo, [], 'INSERT INTO', $ayar);
                    $sayim[$tablo] = $adet;
                } elseif (isset($kosullar[$tablo])) {
                    $adet = $this->veriYaz($gz, $model, $tablo, $kosullar[$tablo], 'REPLACE INTO', $ayar);
                    $sayim[$tablo] = $model->satirSayisi($tablo);
                } else {
                    $this->ifadeYaz($gz, $tablo, 'temizle', 'DELETE FROM ' . $this->bt($tablo));
                    $adet = $this->veriYaz($gz, $model, $tablo, [], 'INSERT INTO', $ayar);
                    $sayim[$tablo] = $adet;
                }

                $toplamSatir += $adet;
            }

            if ($tamMi) {
                foreach ($viewler as $view) {
                    $this->ifadeYaz($gz, $view, 'view', 'DROP VIEW IF EXISTS ' . $this->bt($view));
                    $this->ifadeYaz($gz, $view, 'view', $this->definerTemizle($model->olusturmaKodu($view)));
                }

                foreach ($model->triggerListesi() as $trg) {
                    if (trim($trg['sql']) === '') {
                        continue;
                    }
                    $this->ifadeYaz($gz, $trg['tablo'], 'trigger', 'DROP TRIGGER IF EXISTS ' . $this->bt($trg['ad']));
                    $this->ifadeYaz($gz, $trg['tablo'], 'trigger', $this->definerTemizle($trg['sql']));
                }

                foreach ($model->rutinListesi() as $rutin) {
                    $this->ifadeYaz($gz, '*', 'rutin', 'DROP ' . $rutin['tip'] . ' IF EXISTS ' . $this->bt($rutin['ad']));
                    $this->ifadeYaz($gz, '*', 'rutin', $this->definerTemizle($rutin['sql']));
                }
            }

            $this->ifadeYaz($gz, '*', 'oturum', "SET UNIQUE_CHECKS = 1");
            $this->ifadeYaz($gz, '*', 'oturum', "SET FOREIGN_KEY_CHECKS = 1");
            gzwrite($gz, '-- @SAYIM ' . json_encode($sayim, JSON_UNESCAPED_UNICODE) . "\n");
            gzwrite($gz, "-- @SON\n");
            gzclose($gz);
            $gz = null;

            $model->tutarliOkumaBitir();
            @chmod($hedef, 0600);

            $boyut = filesize($hedef);
            $sure = round(microtime(true) - $basladi, 1);
            $this->yaz(sprintf('Yedek olusturuldu: %s (%s, %s satir, %s sn)',
                $dosyaAdi, number_format($boyut / 1048576, 2) . ' MB', number_format($toplamSatir), $sure));

            $durum['vt'] = $vt;
            $durum['sema'] = $semaOzeti;
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
                        'zaman' => $anZamani,
                    ];
                }
            }
            $this->durumYaz($durum);

            $silinen = $this->rotasyon($vt, (int) $ayar['saklama_gun']);
            $this->yaz("Rotasyon: $silinen eski yedek silindi.");

            $this->sistemLogu((int) $secenek['kullanici_id'], 'Veritabani Yedekleme',
                sprintf('%s yedek alindi: %s (%s MB, %s satir)', strtoupper($tur), $dosyaAdi,
                    number_format($boyut / 1048576, 2), number_format($toplamSatir)));

            $this->yaz('--- YEDEKLEME BITTI ---');
        } catch (Throwable $e) {
            if ($gz !== null) {
                @gzclose($gz);
            }
            if ($hedef !== null && is_file($hedef)) {
                @unlink($hedef);
            }
            error_log('[db_backup] ' . $e->getMessage());
            $this->yaz('Yedekleme basarisiz: ' . self::kullaniciMesaji($e), 'ERROR');
            return ['basarili' => false, 'hata' => self::kullaniciMesaji($e)];
        }

        $sonuc = ['basarili' => true, 'tur' => $tur, 'dosya' => $dosyaAdi, 'boyut' => $boyut, 'satir' => $toplamSatir];

        if ($secenek['drive'] && !empty($ayar['drive_yedekleme']) && empty($secenek['tablo'])) {
            $drive = $this->driveSenkronize($vt, (int) $secenek['kullanici_id']);
            $sonuc['drive'] = $drive;
            if (($drive['durum'] ?? '') === 'hata') {
                $sonuc['basarili'] = false;
                $sonuc['hata'] = $drive['hata'];
            }
        }

        return $sonuc;
    }

    public function driveSenkronize(string $vt, int $kullaniciId, ?callable $log = null): array
    {
        if ($log !== null) {
            $this->logCagri = $log;
        }

        if (!DriveYedekSenkronService::yapilandirildi()) {
            $this->yaz('Google Drive yapilandirilmamis (.env GDRIVE_* / YEDEK_SIFRELEME_ANAHTARI), uzak kopya atlandi.', 'WARNING');
            return ['durum' => 'yapilandirilmamis'];
        }

        try {
            $this->yaz('--- GOOGLE DRIVE SENKRONU BASLADI ---');
            $sonuc = (new DriveYedekSenkronService())->senkronize(
                $this->yedekDizin,
                $vt,
                (int) ($this->ayar['drive_saklama_gun'] ?? 90),
                function (string $mesaj): void {
                    $this->yaz($mesaj);
                },
                isset($this->ayar['drive_artimli_saklama_gun']) ? (int) $this->ayar['drive_artimli_saklama_gun'] : null
            );
            $this->yaz(sprintf("Drive senkronu bitti: %d dosya yuklendi (%s MB), %d eski dosya silindi, Drive'da %d yedek var.",
                $sonuc['yuklenen'], number_format($sonuc['boyut'] / 1048576, 2), $sonuc['silinen'], $sonuc['uzak_adet']));

            $this->sistemLogu($kullaniciId, 'Veritabani Yedekleme',
                sprintf('Google Drive senkronu: %d yuklendi, %d silindi, toplam %d uzak yedek',
                    $sonuc['yuklenen'], $sonuc['silinen'], $sonuc['uzak_adet']));

            $durum = $this->durumOku();
            $durum['son_drive'] = ['zaman' => date('Y-m-d H:i:s'), 'uzak_adet' => $sonuc['uzak_adet']];
            $this->durumYaz($durum);

            return ['durum' => 'tamam'] + $sonuc;
        } catch (Throwable $e) {
            error_log('[db_backup] Drive: ' . $e->getMessage());
            $this->yaz('Google Drive senkronu basarisiz (yerel yedek saglam, bir sonraki calismada tekrar denenecek): ' . self::kullaniciMesaji($e), 'ERROR');
            return ['durum' => 'hata', 'hata' => self::kullaniciMesaji($e)];
        }
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
            $baslik = ($hataVar ? '[HATA] ' : '') . 'DB Yedekleme - ' . date('d.m.Y H:i');
            $icerik = '<h3>Veritabani Yedekleme Raporu</h3><pre style="background:#f4f4f4;padding:10px;border:1px solid #ddd">'
                . htmlspecialchars($logSatirlari, ENT_QUOTES, 'UTF-8') . '</pre>';
            MailGonderService::gonder($this->ayar['mail_alicilar'], $baslik, $icerik);

            if (!$hataVar) {
                $durum = $this->durumOku();
                $durum['son_basari_maili'] = $bugun;
                $this->durumYaz($durum);
            }
        } catch (Throwable $e) {
            error_log('[db_backup] Mail gonderilemedi: ' . $e->getMessage());
        }
    }

    private function dizinHazirla(): void
    {
        if (!is_dir($this->yedekDizin) && !@mkdir($this->yedekDizin, 0750, true)) {
            throw new RuntimeException('Yedek dizini olusturulamadi: ' . $this->yedekDizin);
        }
        @chmod($this->yedekDizin, 0750);

        $kok = realpath(dirname(__DIR__, 2)) ?: '';
        if (strpos(realpath($this->yedekDizin) ?: '', $kok) === 0) {
            $this->dizinKoru();
        }
    }

    private function dizinKoru(): void
    {
        $htaccess = $this->yedekDizin . '/.htaccess';
        $icerik = is_file($htaccess) ? (string) file_get_contents($htaccess) : '';
        if (strpos($icerik, 'Require all denied') === false) {
            @file_put_contents(
                $htaccess,
                "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n"
                . "<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n"
                . "Options -Indexes\n"
            );
            @chmod($htaccess, 0644);
        }
        if (!is_file($this->yedekDizin . '/index.html')) {
            @file_put_contents($this->yedekDizin . '/index.html', '');
        }
    }

    private function durumYaz(array $durum): void
    {
        $dosya = $this->yedekDizin . '/durum.json';
        file_put_contents($dosya, json_encode($durum, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
        @chmod($dosya, 0600);
    }

    private function rotasyon(string $vt, int $saklamaGun): int
    {
        $tamlar = glob($this->yedekDizin . "/tam_{$vt}_*.sql.gz") ?: [];
        if (empty($tamlar)) {
            return 0;
        }
        sort($tamlar);

        $sinir = time() - ($saklamaGun * 86400);
        $korunan = [];
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
        foreach ((glob($this->yedekDizin . "/{tam,artimli}_{$vt}_*.sql.gz", GLOB_BRACE) ?: []) as $dosya) {
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

    private function veriYaz($gz, YedeklemeModel $model, string $tablo, array $kosul, string $komut, array $ayar): int
    {
        $kolonlar = $model->kolonlar($tablo);
        if (empty($kolonlar)) {
            return 0;
        }

        $kolonSql = implode(',', array_map([$this, 'bt'], $kolonlar));
        $onEk = "$komut " . $this->bt($tablo) . " ($kolonSql) VALUES ";
        $limit = max(64 * 1024, $ayar['paket_limiti'] - strlen($onEk) - 1024);

        $tampon = '';
        $tamponAdet = 0;
        $toplam = 0;

        foreach ($model->satirAkisi($tablo, $kosul) as $satir) {
            $degerler = '(' . implode(',', array_map([$model, 'tirnakla'], $satir)) . ')';

            $tasar = $tampon !== '' &&
                (strlen($tampon) + strlen($degerler) + 1 > $limit || $tamponAdet >= $ayar['satir_limiti']);

            if ($tasar) {
                $this->ifadeYaz($gz, $tablo, 'veri', $onEk . $tampon);
                $tampon = '';
                $tamponAdet = 0;
            }

            $tampon .= ($tampon === '' ? '' : ',') . $degerler;
            $tamponAdet++;
            $toplam++;
        }

        if ($tampon !== '') {
            $this->ifadeYaz($gz, $tablo, 'veri', $onEk . $tampon);
        }

        return $toplam;
    }

    private function ifadeYaz($gz, string $tablo, string $tur, string $sql): void
    {
        gzwrite($gz, "-- @STMT t={$tablo} k={$tur}\n");
        gzwrite($gz, rtrim(rtrim($sql), ';') . ";\n");
        gzwrite($gz, "-- @END\n");
    }

    private function bt(string $ad): string
    {
        return '`' . str_replace('`', '``', $ad) . '`';
    }

    private function definerTemizle(string $sql): string
    {
        return preg_replace('/\sDEFINER\s*=\s*(`[^`]*`|\'[^\']*\')@(`[^`]*`|\'[^\']*\')/i', '', $sql);
    }

    private function sistemLogu(int $kullaniciId, string $tur, string $mesaj, int $seviye = SystemLogModel::LEVEL_IMPORTANT): void
    {
        try {
            (new SystemLogModel())->logAction($kullaniciId, $tur, $mesaj, $seviye);
        } catch (Throwable $e) {
            error_log('[yedekleme] system_logs kaydi olusturulamadi: ' . $e->getMessage());
        }
    }

    private function yaz(string $mesaj, string $seviye = 'INFO'): void
    {
        if ($this->logCagri !== null) {
            ($this->logCagri)($mesaj, $seviye);
        }
    }
}
