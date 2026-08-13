<?php

namespace App\Service;

use App\Model\EkipGunlukDurumModel;
use App\Model\EkipMahalleAtamaModel;
use App\Model\KesmeAcmaRaporModel;
use App\Model\KesmeNobetModel;
use App\Model\MahalleModel;

class KesmeAcmaPlanService
{
    const HIZ_GUN_SAYISI = 7;
    const VADE_GUN_SAYISI = 14;
    const GERI_DONUS_GUN = 45;
    const ONERI_ESIGI = 2;
    const NOBET_GECMIS_GUN = 90;

    private $Mahalle;
    private $Atama;
    private $Durum;
    private $Rapor;
    private $Nobet;

    public function __construct(
        ?MahalleModel $mahalle = null,
        ?EkipMahalleAtamaModel $atama = null,
        ?EkipGunlukDurumModel $durum = null,
        ?KesmeAcmaRaporModel $rapor = null,
        ?KesmeNobetModel $nobet = null
    ) {
        $this->Mahalle = $mahalle ?: new MahalleModel();
        $this->Atama = $atama ?: new EkipMahalleAtamaModel();
        $this->Durum = $durum ?: new EkipGunlukDurumModel();
        $this->Rapor = $rapor ?: new KesmeAcmaRaporModel();
        $this->Nobet = $nobet ?: new KesmeNobetModel();
    }

    public static function isGunuMu(string $tarih): bool
    {
        return (int) date('w', strtotime($tarih)) !== 0;
    }

    public static function isGunEkle(string $tarih, int $adet): string
    {
        $gun = strtotime($tarih);
        $eklenen = 0;
        while ($eklenen < $adet) {
            $gun = strtotime('+1 day', $gun);
            if ((int) date('w', $gun) !== 0) {
                $eklenen++;
            }
        }
        return date('Y-m-d', $gun);
    }

    public static function isGunSay(string $baslangic, string $bitis): int
    {
        if ($bitis < $baslangic) {
            return 0;
        }
        $gun = strtotime($baslangic);
        $son = strtotime($bitis);
        $sayi = 0;
        while ($gun <= $son) {
            if ((int) date('w', $gun) !== 0) {
                $sayi++;
            }
            $gun = strtotime('+1 day', $gun);
        }
        return $sayi;
    }

    /**
     * M3, M5, M6, M7: ekip başına kalan iş, hız, tahmini bitiş ve sıradaki mahalle önerisi.
     */
    public function ekipDurumlari(?string $bugun = null): array
    {
        $bugun = $bugun ?: date('Y-m-d');
        $hizBasi = date('Y-m-d', strtotime($bugun . ' -' . (self::HIZ_GUN_SAYISI + 3) . ' day'));
        $vadeBasi = date('Y-m-d', strtotime($bugun . ' -' . self::VADE_GUN_SAYISI . ' day'));
        $veriBasi = date('Y-m-d', strtotime($bugun . ' -' . self::GERI_DONUS_GUN . ' day'));

        $ekipler = $this->Rapor->ekipler();
        $hareketli = $this->Rapor->hareketliEkipler();
        $uyeler = $this->Rapor->ekipUyeHaritasi();
        $aktifAtamalar = $this->Atama->aktifAtamalar();
        $sonIlceler = $this->Atama->sonIlceler(3);
        $sonDurumlar = $this->Durum->sonDurumlar();
        $gunlukDurumlar = $this->Durum->gunlukDurumlar($vadeBasi, $bugun);
        $islemler = $this->Rapor->ekipAralikToplamlari(min($vadeBasi, $veriBasi), $bugun);

        $satirlar = [];
        foreach ($ekipler as $ekip) {
            $ekipId = (int) $ekip['id'];
            $aktif = $aktifAtamalar[$ekipId] ?? null;
            $gecmis = $sonIlceler[$ekipId] ?? [];

            $hiz = $this->gunlukHiz($islemler[$ekipId] ?? [], $hizBasi, $bugun);
            if ($hiz === 0) {
                $hiz = $this->gunlukHiz($islemler[$ekipId] ?? [], $veriBasi, $bugun);
            }
            $yeniVade = $this->gunlukYeniVade($gunlukDurumlar[$ekipId] ?? [], $islemler[$ekipId] ?? []);
            $net = max($hiz - $yeniVade, 1);

            $kalan = isset($sonDurumlar[$ekipId]) ? (int) $sonDurumlar[$ekipId]['kalan_is'] : null;
            $kalanTarihi = $sonDurumlar[$ekipId]['tarih'] ?? null;

            $kalanGun = $kalan !== null ? (int) ceil($kalan / $net) : null;
            $bitisTarihi = $kalanGun !== null ? self::isGunEkle($bugun, max($kalanGun - 1, 0)) : null;

            $satirlar[] = [
                'ekip_id' => $ekipId,
                'ekip_adi' => $ekip['tur_adi'],
                'personel' => $uyeler[$ekipId] ?? '',
                'mahalle_id' => $aktif ? (int) $aktif['mahalle_id'] : null,
                'mahalle_adi' => $aktif['mahalle_adi'] ?? null,
                'ilce' => $aktif['ilce'] ?? null,
                'atama_baslangic' => $aktif['baslangic'] ?? null,
                'kalan_is' => $kalan,
                'kalan_tarihi' => $kalanTarihi,
                'gunluk_hiz' => $hiz,
                'yeni_vade' => $yeniVade,
                'net_eritme' => $net,
                'kalan_gun' => $kalanGun,
                'bitis_tarihi' => $bitisTarihi,
                'son_atamalar' => $gecmis,
                'siradaki_ilce' => $this->siradakiIlce($gecmis, $aktif['ilce'] ?? null),
                'hareketli' => in_array($ekipId, $hareketli, true) ? 1 : 0,
            ];
        }

        return $satirlar;
    }

    private function gunlukHiz(array $ekipIslemleri, string $baslangic, string $bitis): int
    {
        $toplam = 0;
        $gunSayisi = 0;
        $gun = strtotime($baslangic);
        $son = strtotime($bitis);

        while ($gun <= $son) {
            $tarih = date('Y-m-d', $gun);
            if (self::isGunuMu($tarih)) {
                $adet = (int) ($ekipIslemleri[$tarih] ?? 0);
                if ($adet > 0) {
                    $toplam += $adet;
                    $gunSayisi++;
                }
            }
            $gun = strtotime('+1 day', $gun);
        }

        return $gunSayisi > 0 ? (int) round($toplam / $gunSayisi) : 0;
    }

    /**
     * M5: dünkü kalan − dün yapılan ile bugünkü kalan arasındaki fark yeni vade dolan abonedir.
     */
    private function gunlukYeniVade(array $kalanlar, array $islemler): int
    {
        if (count($kalanlar) < 2) {
            return 0;
        }

        ksort($kalanlar);
        $tarihler = array_keys($kalanlar);
        $farklar = [];

        for ($i = 1; $i < count($tarihler); $i++) {
            $onceki = $tarihler[$i - 1];
            $simdi = $tarihler[$i];
            $yapilan = (int) ($islemler[$simdi] ?? 0);
            $beklenen = $kalanlar[$onceki] - $yapilan;
            $fark = $kalanlar[$simdi] - $beklenen;
            if ($fark > 0) {
                $farklar[] = $fark;
            }
        }

        return $farklar ? (int) round(array_sum($farklar) / count($farklar)) : 0;
    }

    /**
     * M3: son 3 atamada 2'den az Dulkadiroğlu varsa sıra Dulkadiroğlu'nundur.
     */
    public function siradakiIlce(array $sonAtamalar, ?string $mevcutIlce = null): string
    {
        if (!$sonAtamalar) {
            return $mevcutIlce === 'dulkadiroglu' ? 'onikisubat' : 'dulkadiroglu';
        }

        $dulkadir = 0;
        foreach ($sonAtamalar as $atama) {
            if (($atama['ilce'] ?? '') === 'dulkadiroglu') {
                $dulkadir++;
            }
        }

        return $dulkadir < 2 ? 'dulkadiroglu' : 'onikisubat';
    }

    /**
     * M1, M2, M4, M7: bitişine 2 iş günü kalan ekiplere sıradaki mahalleyi önerir.
     */
    public function oneriler(?array $ekipDurumlari = null, ?array $mahalleler = null, ?string $bugun = null): array
    {
        $bugun = $bugun ?: date('Y-m-d');
        $ekipDurumlari = $ekipDurumlari ?: $this->ekipDurumlari($bugun);
        $mahalleler = $mahalleler ?: $this->Mahalle->listele();

        $hazir = [];
        $bekleyen = [];
        foreach ($mahalleler as $mahalle) {
            if ($mahalle['durum'] === 'atanabilir') {
                $hazir[] = $mahalle;
            } elseif ($mahalle['durum'] === 'bekliyor') {
                $bekleyen[] = $mahalle;
            }
        }

        usort($hazir, function ($a, $b) {
            return [$a['mesaj_tarihi'], $a['ad']] <=> [$b['mesaj_tarihi'], $b['ad']];
        });
        usort($bekleyen, function ($a, $b) {
            return [$a['hazir_tarihi'], $a['ad']] <=> [$b['hazir_tarihi'], $b['ad']];
        });

        $adaylar = array_filter($ekipDurumlari, function ($ekip) {
            return $ekip['kalan_gun'] !== null && $ekip['kalan_gun'] <= self::ONERI_ESIGI;
        });
        usort($adaylar, function ($a, $b) {
            return $a['kalan_gun'] <=> $b['kalan_gun'];
        });

        $dagitilan = [];
        $oneriler = [];

        foreach ($adaylar as $ekip) {
            $ilce = $ekip['siradaki_ilce'];
            $secilen = null;

            foreach ($hazir as $mahalle) {
                if ($mahalle['ilce'] === $ilce && !in_array($mahalle['id'], $dagitilan, true)) {
                    $secilen = $mahalle;
                    $dagitilan[] = $mahalle['id'];
                    break;
                }
            }

            $ilkHazir = null;
            if (!$secilen) {
                foreach ($bekleyen as $mahalle) {
                    if ($mahalle['ilce'] === $ilce) {
                        $ilkHazir = $mahalle;
                        break;
                    }
                }
            }

            $oneriler[] = [
                'ekip_id' => $ekip['ekip_id'],
                'ekip_adi' => $ekip['ekip_adi'],
                'personel' => $ekip['personel'],
                'kalan_gun' => $ekip['kalan_gun'],
                'mahalle_adi' => $ekip['mahalle_adi'],
                'siradaki_ilce' => $ilce,
                'onerilen' => $secilen,
                'ilk_hazir' => $ilkHazir,
            ];
        }

        return $oneriler;
    }

    /**
     * M8: ay sonu projeksiyonu = yapılan + (iş günü ortalaması × kalan iş günü).
     */
    public function ayProjeksiyonu(?string $bugun = null): array
    {
        $bugun = $bugun ?: date('Y-m-d');
        $ayBasi = date('Y-m-01', strtotime($bugun));
        $aySonu = date('Y-m-t', strtotime($bugun));

        $gunlukler = $this->Rapor->gunlukToplamlar($ayBasi, $bugun);
        $yapilan = array_sum($gunlukler);

        $gecenIsGunu = self::isGunSay($ayBasi, $bugun);
        $ortalama = $gecenIsGunu > 0 ? $yapilan / $gecenIsGunu : 0;

        $kalanIsGunu = $bugun < $aySonu
            ? self::isGunSay(date('Y-m-d', strtotime($bugun . ' +1 day')), $aySonu)
            : 0;

        return [
            'ay_basi' => $ayBasi,
            'ay_sonu' => $aySonu,
            'yapilan' => (int) $yapilan,
            'is_gunu_ortalamasi' => (int) round($ortalama),
            'kalan_is_gunu' => $kalanIsGunu,
            'projeksiyon' => (int) round($yapilan + $ortalama * $kalanIsGunu),
            'gunluk' => $gunlukler,
        ];
    }

    public static function haftaBasi(string $tarih): string
    {
        $gun = (int) date('N', strtotime($tarih));
        return date('Y-m-d', strtotime($tarih . ' -' . ($gun - 1) . ' day'));
    }

    /**
     * K3, K5: ilçe görevi haftalıktır; şirket aracı olan ekip gönderilmez,
     * ilçeye en uzun süredir gitmeyen ekip önce seçilir.
     */
    public function nobetEkipleri(): array
    {
        $hepsi = array_map(function ($ekip) {
            return (int) $ekip['id'];
        }, $this->Rapor->ekipler());

        $hareketli = array_values(array_intersect($hepsi, $this->Rapor->hareketliEkipler()));
        return $hareketli ?: $hepsi;
    }

    public function ilcePlaniUret(string $ayBasi, string $aySonu, ?int $olusturanId = null): int
    {
        $aracli = $this->Nobet->sirketAracliEkipler();
        $uygun = [];
        foreach ($this->nobetEkipleri() as $ekipId) {
            if (!in_array($ekipId, $aracli, true)) {
                $uygun[] = $ekipId;
            }
        }

        if (count($uygun) < 2) {
            return 0;
        }

        $buHafta = self::haftaBasi(date('Y-m-d'));
        $mevcut = $this->Nobet->ilcePlani(self::haftaBasi($ayBasi), $aySonu);
        $sonGorev = $this->Nobet->ilceGecmisi(self::haftaBasi($ayBasi));
        $yazilan = 0;

        for ($hafta = self::haftaBasi($ayBasi); $hafta <= $aySonu; $hafta = date('Y-m-d', strtotime($hafta . ' +7 day'))) {
            if ($hafta < $buHafta) {
                continue;
            }
            foreach (array_keys(KesmeNobetModel::ILCELER) as $ilce) {
                if (!empty($mevcut[$hafta][$ilce]['elle'])) {
                    $sonGorev[$mevcut[$hafta][$ilce]['ekip_id']] = $hafta;
                }
            }

            $secilenler = [];
            foreach (array_keys(KesmeNobetModel::ILCELER) as $ilce) {
                if (isset($mevcut[$hafta][$ilce]) && !empty($mevcut[$hafta][$ilce]['elle'])) {
                    $secilenler[] = (int) $mevcut[$hafta][$ilce]['ekip_id'];
                    continue;
                }

                $sirali = $uygun;
                usort($sirali, function ($a, $b) use ($sonGorev) {
                    return [$sonGorev[$a] ?? '0000-00-00', $a] <=> [$sonGorev[$b] ?? '0000-00-00', $b];
                });

                foreach ($sirali as $ekipId) {
                    if (in_array($ekipId, $secilenler, true)) {
                        continue;
                    }
                    $this->Nobet->ilceYaz($hafta, $ilce, $ekipId, false, $olusturanId);
                    $secilenler[] = $ekipId;
                    $sonGorev[$ekipId] = $hafta;
                    $yazilan++;
                    break;
                }
            }
        }

        return $yazilan;
    }

    /**
     * K1, K2, K6, K7: her güne 1 personel; o hafta ilçedeki ekiplerin personeli yazılmaz,
     * elle değiştirilen günler korunur, hafta sonu yükü dengelenir.
     */
    public function sahaPlaniUret(string $baslangic, string $bitis, ?int $olusturanId = null): int
    {
        $ekipler = $this->nobetEkipleri();
        $bugun = date('Y-m-d');
        if ($baslangic < $bugun) {
            $baslangic = $bugun;
        }
        if ($bitis < $baslangic) {
            return 0;
        }

        $personeller = $this->Nobet->sahaPersonelleri($baslangic, $ekipler);
        if (!$personeller) {
            return 0;
        }

        $personelEkip = [];
        foreach ($personeller as $personel) {
            $personelEkip[(int) $personel['id']] = (int) $personel['ekip_id'];
        }
        $havuz = array_keys($personelEkip);

        $this->Nobet->otomatikGunleriSil($baslangic, $bitis);

        $mevcut = $this->Nobet->sahaPlani($baslangic, $bitis);
        $ilcePlani = $this->Nobet->ilcePlani(self::haftaBasi($baslangic), $bitis);

        $sayac = [];
        $haftaSonuSayac = [];
        $sonNobet = [];
        foreach ($havuz as $personelId) {
            $sayac[$personelId] = 0;
            $haftaSonuSayac[$personelId] = 0;
            $sonNobet[$personelId] = '0000-00-00';
        }

        $gecmisBasi = date('Y-m-d', strtotime($baslangic . ' -' . self::NOBET_GECMIS_GUN . ' day'));
        $sayimAralik = $this->Nobet->sahaPlani($gecmisBasi, $bitis);

        foreach ($sayimAralik as $tarih => $kayit) {
            $personelId = (int) $kayit['personel_id'];
            if (!isset($sayac[$personelId])) {
                continue;
            }
            $sayac[$personelId]++;
            if ($tarih > $sonNobet[$personelId]) {
                $sonNobet[$personelId] = $tarih;
            }
            if (in_array((int) date('w', strtotime($tarih)), [0, 6], true)) {
                $haftaSonuSayac[$personelId]++;
            }
        }

        $yazilan = 0;
        for ($tarih = $baslangic; $tarih <= $bitis; $tarih = date('Y-m-d', strtotime($tarih . ' +1 day'))) {
            if (isset($mevcut[$tarih])) {
                continue;
            }

            $hafta = self::haftaBasi($tarih);
            $ilcedekiler = [];
            foreach ($ilcePlani[$hafta] ?? [] as $kayit) {
                $ilcedekiler[] = (int) $kayit['ekip_id'];
            }

            $haftaSonu = in_array((int) date('w', strtotime($tarih)), [0, 6], true);
            $adaylar = array_values(array_filter($havuz, function ($personelId) use ($personelEkip, $ilcedekiler) {
                return !in_array($personelEkip[$personelId], $ilcedekiler, true);
            }));
            if (!$adaylar) {
                continue;
            }

            usort($adaylar, function ($a, $b) use ($sayac, $haftaSonuSayac, $sonNobet, $haftaSonu) {
                $ilkA = $haftaSonu ? $haftaSonuSayac[$a] : $sayac[$a];
                $ilkB = $haftaSonu ? $haftaSonuSayac[$b] : $sayac[$b];
                return [$ilkA, $sayac[$a], $sonNobet[$a], $a] <=> [$ilkB, $sayac[$b], $sonNobet[$b], $b];
            });

            $secilen = $adaylar[0];
            $this->Nobet->sahaYaz($tarih, $secilen, false, $olusturanId);

            $sayac[$secilen]++;
            $sonNobet[$secilen] = $tarih;
            if ($haftaSonu) {
                $haftaSonuSayac[$secilen]++;
            }
            $yazilan++;
        }

        return $yazilan;
    }

    /**
     * Telefon nöbeti: personel kartında "telefon nöbeti tutar" işaretli
     * kişilere en az nöbet tutandan başlanarak yazılır; elle değişenler korunur.
     */
    public function telefonPlaniUret(string $ayBasi, string $aySonu, ?int $olusturanId = null): int
    {
        $havuz = array_map(function ($personel) {
            return (int) $personel['id'];
        }, $this->Nobet->telefonHavuzu());

        if (!$havuz) {
            return 0;
        }

        $bugun = date('Y-m-d');
        if ($ayBasi < $bugun) {
            $ayBasi = $bugun;
        }
        if ($aySonu < $ayBasi) {
            return 0;
        }

        $this->Nobet->telefonOtomatikSil($ayBasi, $aySonu);
        $mevcut = $this->Nobet->telefonPlani($ayBasi, $aySonu);
        $gecmis = $this->Nobet->telefonPlani(date('Y-m-d', strtotime($ayBasi . ' -' . self::NOBET_GECMIS_GUN . ' day')), $aySonu);

        $sayac = array_fill_keys($havuz, 0);
        foreach ($gecmis as $kayit) {
            $personelId = (int) $kayit['personel_id'];
            if (isset($sayac[$personelId])) {
                $sayac[$personelId]++;
            }
        }

        $yazilan = 0;
        for ($tarih = $ayBasi; $tarih <= $aySonu; $tarih = date('Y-m-d', strtotime($tarih . ' +1 day'))) {
            if (isset($mevcut[$tarih])) {
                continue;
            }

            asort($sayac);
            $secilen = (int) array_key_first($sayac);
            $this->Nobet->telefonYaz($tarih, $secilen, false, $olusturanId);
            $sayac[$secilen]++;
            $yazilan++;
        }

        return $yazilan;
    }
}
