<?php

namespace App\Service;

use App\Model\SystemLogModel;
use App\Model\YedeklemeModel;
use RuntimeException;
use Throwable;

class VeritabaniGeriYuklemeService
{
    private const TEST_GECMISI = 'geri_yukleme_testleri.json';
    private const TEST_GECMISI_LIMIT = 30;

    private string $yedekDizin;
    private $logCagri;

    public function __construct(?string $yedekDizin = null)
    {
        $this->yedekDizin = rtrim($yedekDizin ?? VeritabaniYedekService::ayarlar()['yedek_dizini'], '/');
    }

    public static function metaOku(string $dosya): ?array
    {
        $gz = @gzopen($dosya, 'rb');
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

    public function yedekleriTara(string $vt, ?callable $uyari = null): array
    {
        $liste = [];
        foreach ((glob($this->yedekDizin . "/{tam,artimli}_{$vt}_*.sql.gz", GLOB_BRACE) ?: []) as $dosya) {
            $meta = self::metaOku($dosya);
            if ($meta === null) {
                if ($uyari !== null) {
                    $uyari('Meta satiri okunamadi, atlandi: ' . basename($dosya), 'WARNING');
                }
                continue;
            }
            $meta['dosya'] = $dosya;
            $meta['ad'] = basename($dosya);
            $meta['boyut'] = filesize($dosya);
            $liste[] = $meta;
        }
        usort($liste, fn($a, $b) => strcmp($a['zaman'], $b['zaman']));
        return $liste;
    }

    public function zincirKur(array $liste, string $hedefZaman): array
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

    public function dosyaDogrula(string $dosya): array
    {
        $gz = gzopen($dosya, 'rb');
        if ($gz === false) {
            throw new RuntimeException('Dosya acilamadi: ' . basename($dosya));
        }

        $ifadeSayisi = 0;
        $tamamlandi = false;
        $acikBlok = false;
        $sayim = null;

        while (($satir = gzgets($gz)) !== false) {
            if (strpos($satir, '-- @STMT ') === 0) {
                $acikBlok = true;
                $ifadeSayisi++;
            } elseif (strpos($satir, '-- @END') === 0) {
                $acikBlok = false;
            } elseif (!$acikBlok && strpos($satir, '-- @SAYIM ') === 0) {
                $veri = json_decode(trim(substr($satir, 10)), true);
                $sayim = is_array($veri) ? $veri : null;
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

        return ['ifade' => $ifadeSayisi, 'sayim' => $sayim];
    }

    public function dosyaUygula(YedeklemeModel $model, string $dosya, array $tabloFiltre, bool $onayla, ?string $korunanVt = null): array
    {
        $gz = gzopen($dosya, 'rb');
        if ($gz === false) {
            throw new RuntimeException('Dosya acilamadi: ' . basename($dosya));
        }

        $calisan = 0;
        $atlanan = 0;
        $tampon = null;
        $tablo = '';
        $tur = '';
        $korunanRef = $korunanVt !== null ? '`' . str_replace('`', '``', $korunanVt) . '`.' : null;

        try {
            while (($satir = gzgets($gz)) !== false) {
                if (strpos($satir, '-- @STMT ') === 0) {
                    $tablo = '';
                    $tur = '';
                    if (preg_match('/t=(\S+)\s+k=(\S+)/', $satir, $e)) {
                        $tablo = $e[1];
                        $tur = $e[2];
                    }
                    $tampon = '';
                    continue;
                }

                if (strpos($satir, '-- @END') === 0) {
                    if ($tampon === null) {
                        continue;
                    }
                    $sql = trim($tampon);
                    $tampon = null;

                    if ($sql === '') {
                        continue;
                    }

                    $uygula = empty($tabloFiltre)
                        || $tablo === '*'
                        || in_array($tablo, $tabloFiltre, true);

                    if ($uygula && $korunanRef !== null && in_array($tur, ['trigger', 'rutin'], true)
                        && stripos($sql, $korunanRef) !== false) {
                        $uygula = false;
                    }

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

    public function testEt(string $dosyaAdi, int $kullaniciId, callable $log): array
    {
        $this->logCagri = $log;
        $basladi = microtime(true);
        $canliModel = new YedeklemeModel();
        $canliVt = $canliModel->veritabaniAdi();
        $testVt = trim((string) ($_ENV['YEDEK_TEST_VT'] ?? ''));

        $kayit = [
            'zaman' => date('Y-m-d H:i:s'),
            'dosya' => $dosyaAdi,
            'kullanici_id' => $kullaniciId,
            'sonuc' => 'hata',
        ];

        $testModel = null;
        try {
            if ($testVt === '') {
                throw new RuntimeException('.env icinde YEDEK_TEST_VT tanimli degil.');
            }
            if (strcasecmp($testVt, $canliVt) === 0 || strcasecmp($testVt, (string) ($_ENV['DB_NAME'] ?? '')) === 0) {
                throw new RuntimeException('YEDEK_TEST_VT canli veritabani ile ayni olamaz.');
            }

            $liste = $this->yedekleriTara($canliVt);
            $secilen = null;
            foreach ($liste as $yedek) {
                if ($yedek['ad'] === $dosyaAdi) {
                    $secilen = $yedek;
                }
            }
            if ($secilen === null) {
                throw new RuntimeException('Yedek dosyasi bulunamadi: ' . $dosyaAdi);
            }

            $zincir = $secilen['tip'] === 'tam'
                ? [$secilen]
                : $this->zincirKur($liste, $secilen['zaman']);
            if ($zincir === [] || end($zincir)['ad'] !== $dosyaAdi) {
                throw new RuntimeException('Bu artimli yedegin bagli oldugu tam yedek yerelde yok.');
            }

            $this->yaz(sprintf('--- GERI YUKLEME TESTI BASLADI (%s -> %s, %d dosya) ---', $dosyaAdi, $testVt, count($zincir)));

            $sayim = null;
            foreach ($zincir as $parca) {
                $dogrulama = $this->dosyaDogrula($parca['dosya']);
                $sayim = $dogrulama['sayim'];
                $this->yaz(sprintf('%s | %s | %s ifade | dogrulandi', $parca['tip'], $parca['ad'], number_format($dogrulama['ifade'])));
            }

            $testModel = YedeklemeModel::ayriBaglanti($testVt);
            $temizlenen = $testModel->veritabaniniBosalt($canliVt);
            if ($temizlenen > 0) {
                $this->yaz("Test veritabanindaki $temizlenen eski nesne temizlendi.");
            }

            $testModel->yedekOturumuAc();
            $toplamIfade = 0;
            $atlananGuvenlik = 0;
            foreach ($zincir as $parca) {
                $sonuc = $this->dosyaUygula($testModel, $parca['dosya'], [], true, $canliVt);
                $toplamIfade += $sonuc['calisan'];
                $atlananGuvenlik += $sonuc['atlanan'];
                $this->yaz(sprintf('Uygulandi: %s (%s ifade)', $parca['ad'], number_format($sonuc['calisan'])));
            }
            $testModel->yedekOturumuKapat();
            if ($atlananGuvenlik > 0) {
                $this->yaz("Canli veritabanina referans veren $atlananGuvenlik trigger/rutin ifadesi guvenlik icin atlandi.", 'WARNING');
            }

            $tablolar = [];
            $fark = 0;
            $testTablolari = array_flip($testModel->tabloListesi());
            $beklenenler = $sayim ?? array_fill_keys(array_keys($testTablolari), null);
            foreach ($beklenenler as $tablo => $beklenen) {
                $bulunan = isset($testTablolari[$tablo]) ? $testModel->satirSayisi($tablo) : null;
                $esit = $beklenen === null || $bulunan === (int) $beklenen;
                if (!$esit) {
                    $fark++;
                }
                $tablolar[] = ['tablo' => $tablo, 'beklenen' => $beklenen, 'bulunan' => $bulunan, 'esit' => $esit];
            }

            $kayit['sonuc'] = $sayim === null ? 'sayimsiz' : ($fark === 0 ? 'basarili' : 'farkli');
            $kayit['tablo_sayisi'] = count($tablolar);
            $kayit['fark_sayisi'] = $fark;
            $kayit['ifade'] = $toplamIfade;
            $kayit['toplam_satir'] = array_sum(array_map(static fn($t) => (int) $t['bulunan'], $tablolar));
            $kayit['farklar'] = array_values(array_filter($tablolar, static fn($t) => !$t['esit']));

            if ($sayim === null) {
                $this->yaz('Bu yedek eski bicimde (@SAYIM yok); yukleme basarili ancak satir sayilari karsilastirilamadi.', 'WARNING');
            } elseif ($fark === 0) {
                $this->yaz(sprintf('Tum tablolar (%d) yedekteki satir sayilariyla birebir eslesti.', count($tablolar)));
            } else {
                $this->yaz("$fark tabloda satir sayisi farki var (artimli zincirde kalici silinen satirlar bu farki olusturabilir).", 'WARNING');
            }
        } catch (Throwable $e) {
            error_log('[db_restore_test] ' . $e->getMessage());
            $this->yaz('Geri yukleme testi basarisiz: ' . VeritabaniYedekService::kullaniciMesaji($e), 'ERROR');
            $kayit['hata'] = VeritabaniYedekService::kullaniciMesaji($e);
        } finally {
            if ($testModel !== null) {
                try {
                    $testModel->veritabaniniBosalt($canliVt);
                    $this->yaz('Test veritabani bosaltildi.');
                } catch (Throwable $e) {
                    error_log('[db_restore_test] Test VT bosaltilamadi: ' . $e->getMessage());
                    $this->yaz('Test veritabani bosaltilamadi: ' . VeritabaniYedekService::kullaniciMesaji($e), 'WARNING');
                }
            }
        }

        $kayit['sure'] = (int) round(microtime(true) - $basladi);
        $this->testGecmisineEkle($kayit);

        try {
            (new SystemLogModel())->logAction(
                $kullaniciId,
                'Veritabani Geri Yukleme Testi',
                sprintf('%s: %s (%s sn)', $dosyaAdi, $kayit['sonuc'], $kayit['sure']),
                $kayit['sonuc'] === 'basarili' ? SystemLogModel::LEVEL_IMPORTANT : SystemLogModel::LEVEL_CRITICAL
            );
        } catch (Throwable $e) {
            error_log('[db_restore_test] system_logs kaydi olusturulamadi: ' . $e->getMessage());
        }

        $this->yaz(sprintf('--- GERI YUKLEME TESTI BITTI (%s, %s sn) ---', $kayit['sonuc'], $kayit['sure']));

        return $kayit;
    }

    public function testGecmisi(): array
    {
        $dosya = $this->yedekDizin . '/' . self::TEST_GECMISI;
        if (!is_file($dosya)) {
            return [];
        }
        $veri = json_decode((string) file_get_contents($dosya), true);
        return is_array($veri) ? $veri : [];
    }

    private function testGecmisineEkle(array $kayit): void
    {
        $gecmis = $this->testGecmisi();
        array_unshift($gecmis, $kayit);
        $dosya = $this->yedekDizin . '/' . self::TEST_GECMISI;
        file_put_contents($dosya, json_encode(array_slice($gecmis, 0, self::TEST_GECMISI_LIMIT), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
        @chmod($dosya, 0600);
    }

    private function yaz(string $mesaj, string $seviye = 'INFO'): void
    {
        if ($this->logCagri !== null) {
            ($this->logCagri)($mesaj, $seviye);
        }
    }
}
