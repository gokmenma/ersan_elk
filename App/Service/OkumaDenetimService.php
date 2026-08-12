<?php

namespace App\Service;

class OkumaDenetimService
{
    const SEVIYE_TEMIZ = 'temiz';
    const SEVIYE_SUPHELI = 'supheli';
    const SEVIYE_KRITIK = 'kritik';

    private $dusukVerimEsigi;
    private $evdeYokEsigi;
    private $haftaSonuDahil;

    public function __construct($dusukVerimEsigi = 50, $evdeYokEsigi = 35, $haftaSonuDahil = false)
    {
        $this->dusukVerimEsigi = max(0, min(100, (int) $dusukVerimEsigi));
        $this->evdeYokEsigi = max(0, min(100, (int) $evdeYokEsigi));
        $this->haftaSonuDahil = (bool) $haftaSonuDahil;
    }

    public static function normalizeBolge($deger)
    {
        $deger = trim((string) $deger);
        if ($deger === '') {
            return '';
        }
        $deger = str_replace(['i', 'ı', 'İ', 'I'], ['I', 'I', 'I', 'I'], $deger);
        $deger = mb_strtoupper($deger, 'UTF-8');
        return preg_replace('/\s+/u', ' ', $deger);
    }

    public function analizEt(array $ekipGunler, array $tanimliEkipler, $baslangic, $bitis)
    {
        $ekipler = [];

        foreach ($tanimliEkipler as $ekip) {
            $ekipler[$ekip->id] = [
                'ekip_kodu_id' => (int) $ekip->id,
                'ekip_adi' => $ekip->tur_adi,
                'tanimli_bolge' => $ekip->ekip_bolge,
                'personeller' => $ekip->personeller,
                'listede_var' => true,
                'gunler' => [],
            ];
        }

        foreach ($ekipGunler as $satir) {
            $id = (int) $satir->ekip_kodu_id;

            if (!isset($ekipler[$id])) {
                $ekipler[$id] = [
                    'ekip_kodu_id' => $id,
                    'ekip_adi' => $satir->ekip_adi ?: ($satir->kullanici_adi ?: 'TANIMSIZ EKİP'),
                    'tanimli_bolge' => $satir->tanimli_bolge,
                    'personeller' => $satir->personeller,
                    'listede_var' => false,
                    'gunler' => [],
                ];
            }

            if (empty($ekipler[$id]['personeller']) && !empty($satir->personeller)) {
                $ekipler[$id]['personeller'] = $satir->personeller;
            }

            $toplam = (int) $satir->toplam_abone;
            $evdeYok = (int) $satir->evde_yok_abone;

            $ekipler[$id]['gunler'][$satir->tarih] = [
                'tarih' => $satir->tarih,
                'toplam_abone' => $toplam,
                'okunan_abone' => (int) $satir->okunan_abone,
                'evde_yok_abone' => $evdeYok,
                'arizali_abone' => (int) $satir->arizali_abone,
                'idari_abone' => (int) $satir->idari_abone,
                'evde_yok_orani' => $toplam > 0 ? round($evdeYok * 100 / $toplam, 1) : 0.0,
                'okuma_orani' => $toplam > 0 ? round(((int) $satir->okunan_abone) * 100 / $toplam, 1) : 0.0,
                'defter_sayisi' => (int) $satir->defter_sayisi,
                'kayit_sayisi' => (int) $satir->kayit_sayisi,
                'personel_sayisi' => (int) $satir->personel_sayisi,
                'okunan_bolgeler' => $satir->okunan_bolgeler,
                'hafta_sonu' => $this->haftaSonuMu($satir->tarih),
                'bayraklar' => [],
                'seviye' => self::SEVIYE_TEMIZ,
            ];
        }

        $takvim = $this->takvimGunleri($baslangic, $bitis);

        foreach ($ekipler as $id => &$ekip) {
            $ekip['referans'] = $this->referansDeger(array_column($ekip['gunler'], 'toplam_abone'));
            $ekip['okumasiz_gunler'] = [];

            if (!empty($ekip['gunler'])) {
                foreach ($takvim as $gun) {
                    if (isset($ekip['gunler'][$gun])) {
                        continue;
                    }
                    if (!$this->haftaSonuDahil && $this->haftaSonuMu($gun)) {
                        continue;
                    }
                    $ekip['okumasiz_gunler'][] = $gun;
                }
            }

            foreach ($ekip['gunler'] as $tarih => &$gun) {
                $gun['bayraklar'] = $this->bayraklariBul($gun, $ekip);
                $gun['seviye'] = $this->seviyeBelirle($gun['bayraklar']);
            }
            unset($gun);

            ksort($ekip['gunler']);

            $ekip['toplam_abone'] = array_sum(array_column($ekip['gunler'], 'toplam_abone'));
            $ekip['okunan_abone'] = array_sum(array_column($ekip['gunler'], 'okunan_abone'));
            $ekip['evde_yok_abone'] = array_sum(array_column($ekip['gunler'], 'evde_yok_abone'));
            $ekip['arizali_abone'] = array_sum(array_column($ekip['gunler'], 'arizali_abone'));
            $ekip['idari_abone'] = array_sum(array_column($ekip['gunler'], 'idari_abone'));
            $ekip['calisilan_gun'] = count($ekip['gunler']);
            $ekip['gunluk_ortalama'] = $ekip['calisilan_gun'] > 0
                ? round($ekip['toplam_abone'] / $ekip['calisilan_gun'])
                : 0;
            $ekip['evde_yok_orani'] = $ekip['toplam_abone'] > 0
                ? round($ekip['evde_yok_abone'] * 100 / $ekip['toplam_abone'], 1)
                : 0.0;
            $ekip['okuma_orani'] = $ekip['toplam_abone'] > 0
                ? round($ekip['okunan_abone'] * 100 / $ekip['toplam_abone'], 1)
                : 0.0;

            $seviyeler = array_column($ekip['gunler'], 'seviye');
            $ekip['supheli_gun'] = count(array_filter($seviyeler, fn($s) => $s === self::SEVIYE_SUPHELI));
            $ekip['kritik_gun'] = count(array_filter($seviyeler, fn($s) => $s === self::SEVIYE_KRITIK));
            $ekip['okumasiz_gun_sayisi'] = count($ekip['okumasiz_gunler']);

            $ekip['bolgeler'] = $this->ekipBolgeleri($ekip['gunler']);
            $ekip['gosterilecek_bolge'] = $this->gosterilecekBolge($ekip);
        }
        unset($ekip);

        $this->bolgeAdlariniBirlestir($ekipler);

        uasort($ekipler, function ($a, $b) {
            $fark = ($b['kritik_gun'] + $b['supheli_gun']) <=> ($a['kritik_gun'] + $a['supheli_gun']);
            return $fark !== 0 ? $fark : strcmp($a['ekip_adi'], $b['ekip_adi']);
        });

        return $ekipler;
    }

    private function bayraklariBul(array $gun, array $ekip)
    {
        $bayraklar = [];

        if ($ekip['referans'] > 0 && $gun['toplam_abone'] > 0) {
            $oran = $gun['toplam_abone'] * 100 / $ekip['referans'];
            if ($oran < $this->dusukVerimEsigi) {
                $bayraklar[] = [
                    'kod' => 'dusuk_verim',
                    'etiket' => 'Düşük verim',
                    'aciklama' => 'Ekibin normal gününün %' . round($oran) . '\'i kadar okuma yapılmış (' . $gun['toplam_abone'] . ' / referans ' . $ekip['referans'] . ').',
                    'agirlik' => $oran < ($this->dusukVerimEsigi / 2) ? 2 : 1,
                ];
            }
        }

        if ($gun['toplam_abone'] > 0 && $gun['evde_yok_orani'] >= $this->evdeYokEsigi) {
            $bayraklar[] = [
                'kod' => 'evde_yok',
                'etiket' => 'Yüksek evde yok',
                'aciklama' => 'Okumaların %' . $gun['evde_yok_orani'] . '\'i "EVDE YOK" olarak kapatılmış (' . $gun['evde_yok_abone'] . ' abone).',
                'agirlik' => $gun['evde_yok_orani'] >= ($this->evdeYokEsigi * 1.5) ? 2 : 1,
            ];
        }

        $disBolgeler = $this->bolgeDisiOkumalar($gun['okunan_bolgeler'], $ekip['tanimli_bolge']);
        if (!empty($disBolgeler)) {
            $bayraklar[] = [
                'kod' => 'bolge_disi',
                'etiket' => 'Bölge dışı okuma',
                'aciklama' => 'Ekibin tanımlı bölgesi "' . $ekip['tanimli_bolge'] . '" iken şu bölgelerde okuma var: ' . implode(', ', $disBolgeler) . '.',
                'agirlik' => 0,
            ];
        }

        if ($gun['hafta_sonu']) {
            $bayraklar[] = [
                'kod' => 'hafta_sonu',
                'etiket' => 'Hafta sonu',
                'aciklama' => 'Hafta sonu günü okuma kaydı bulunuyor.',
                'agirlik' => 0,
            ];
        }

        return $bayraklar;
    }

    private function seviyeBelirle(array $bayraklar)
    {
        $agirlik = array_sum(array_column($bayraklar, 'agirlik'));

        if ($agirlik >= 2) {
            return self::SEVIYE_KRITIK;
        }
        if ($agirlik >= 1) {
            return self::SEVIYE_SUPHELI;
        }
        return self::SEVIYE_TEMIZ;
    }

    private function bolgeDisiOkumalar($okunanBolgeler, $tanimliBolge)
    {
        $tanimli = self::normalizeBolge($tanimliBolge);
        if ($tanimli === '' || trim((string) $okunanBolgeler) === '') {
            return [];
        }

        $disinda = [];
        foreach (explode(',', (string) $okunanBolgeler) as $bolge) {
            $bolge = trim($bolge);
            if ($bolge === '') {
                continue;
            }
            if (self::normalizeBolge($bolge) !== $tanimli) {
                $disinda[] = $bolge;
            }
        }
        return array_values(array_unique($disinda));
    }

    private function ekipBolgeleri(array $gunler)
    {
        $bolgeler = [];
        foreach ($gunler as $gun) {
            foreach (explode(',', (string) $gun['okunan_bolgeler']) as $bolge) {
                $bolge = trim($bolge);
                if ($bolge === '') {
                    continue;
                }
                $bolgeler[$bolge] = ($bolgeler[$bolge] ?? 0) + $gun['toplam_abone'];
            }
        }
        arsort($bolgeler);
        return $bolgeler;
    }

    private function gosterilecekBolge(array $ekip)
    {
        if (!empty($ekip['tanimli_bolge'])) {
            return $ekip['tanimli_bolge'];
        }
        if (!empty($ekip['bolgeler'])) {
            return (string) array_key_first($ekip['bolgeler']);
        }
        return 'TANIMSIZ';
    }

    private function bolgeAdlariniBirlestir(array &$ekipler)
    {
        $adaylar = [];

        foreach ($ekipler as $ekip) {
            $anahtar = self::normalizeBolge($ekip['gosterilecek_bolge']);
            if ($anahtar === '') {
                continue;
            }
            $ad = $ekip['gosterilecek_bolge'];
            $adaylar[$anahtar][$ad] = ($adaylar[$anahtar][$ad] ?? 0) + 1;
        }

        $secilen = [];
        foreach ($adaylar as $anahtar => $secenekler) {
            arsort($secenekler);
            $secilen[$anahtar] = (string) array_key_first($secenekler);
        }

        foreach ($ekipler as &$ekip) {
            $anahtar = self::normalizeBolge($ekip['gosterilecek_bolge']);
            if (isset($secilen[$anahtar])) {
                $ekip['gosterilecek_bolge'] = $secilen[$anahtar];
            }
        }
        unset($ekip);
    }

    private function referansDeger(array $degerler)
    {
        $degerler = array_values(array_filter($degerler, fn($d) => $d > 0));
        $adet = count($degerler);
        if ($adet === 0) {
            return 0;
        }

        sort($degerler);
        $orta = (int) floor($adet / 2);

        if ($adet % 2 === 1) {
            return (int) $degerler[$orta];
        }
        return (int) round(($degerler[$orta - 1] + $degerler[$orta]) / 2);
    }

    private function takvimGunleri($baslangic, $bitis)
    {
        $gunler = [];
        $baslangicTs = strtotime($baslangic);
        $bitisTs = strtotime($bitis);

        if ($baslangicTs === false || $bitisTs === false || $baslangicTs > $bitisTs) {
            return $gunler;
        }

        for ($ts = $baslangicTs; $ts <= $bitisTs; $ts = strtotime('+1 day', $ts)) {
            $gunler[] = date('Y-m-d', $ts);
        }
        return $gunler;
    }

    private function haftaSonuMu($tarih)
    {
        $gun = (int) date('N', strtotime($tarih));
        return $gun >= 6;
    }

    public function bolgeOzeti(array $ekipler)
    {
        $ozet = [];

        foreach ($ekipler as $ekip) {
            if ($ekip['calisilan_gun'] === 0) {
                continue;
            }

            $bolge = $ekip['gosterilecek_bolge'];

            if (!isset($ozet[$bolge])) {
                $ozet[$bolge] = [
                    'bolge' => $bolge,
                    'ekip_sayisi' => 0,
                    'calisilan_gun' => 0,
                    'toplam_abone' => 0,
                    'okunan_abone' => 0,
                    'evde_yok_abone' => 0,
                    'supheli_gun' => 0,
                    'kritik_gun' => 0,
                    'okumasiz_gun' => 0,
                ];
            }

            $ozet[$bolge]['ekip_sayisi']++;
            $ozet[$bolge]['calisilan_gun'] += $ekip['calisilan_gun'];
            $ozet[$bolge]['toplam_abone'] += $ekip['toplam_abone'];
            $ozet[$bolge]['okunan_abone'] += $ekip['okunan_abone'];
            $ozet[$bolge]['evde_yok_abone'] += $ekip['evde_yok_abone'];
            $ozet[$bolge]['supheli_gun'] += $ekip['supheli_gun'];
            $ozet[$bolge]['kritik_gun'] += $ekip['kritik_gun'];
            $ozet[$bolge]['okumasiz_gun'] += $ekip['okumasiz_gun_sayisi'];
        }

        foreach ($ozet as &$satir) {
            $satir['gunluk_ortalama'] = $satir['calisilan_gun'] > 0
                ? round($satir['toplam_abone'] / $satir['calisilan_gun'])
                : 0;
            $satir['evde_yok_orani'] = $satir['toplam_abone'] > 0
                ? round($satir['evde_yok_abone'] * 100 / $satir['toplam_abone'], 1)
                : 0.0;
        }
        unset($satir);

        uasort($ozet, fn($a, $b) => $b['toplam_abone'] <=> $a['toplam_abone']);

        return $ozet;
    }

    public function genelOzet(array $ekipler, $baslangic, $bitis)
    {
        $aktifEkipler = array_filter($ekipler, fn($e) => $e['calisilan_gun'] > 0);

        return [
            'toplam_abone' => array_sum(array_column($aktifEkipler, 'toplam_abone')),
            'okunan_abone' => array_sum(array_column($aktifEkipler, 'okunan_abone')),
            'evde_yok_abone' => array_sum(array_column($aktifEkipler, 'evde_yok_abone')),
            'ekip_sayisi' => count($aktifEkipler),
            'ekip_gun' => array_sum(array_column($aktifEkipler, 'calisilan_gun')),
            'supheli_gun' => array_sum(array_column($aktifEkipler, 'supheli_gun')),
            'kritik_gun' => array_sum(array_column($aktifEkipler, 'kritik_gun')),
            'okumasiz_gun' => array_sum(array_column($aktifEkipler, 'okumasiz_gun_sayisi')),
            'okumasiz_ekip' => count($ekipler) - count($aktifEkipler),
            'is_gunu_sayisi' => count(array_filter(
                $this->takvimGunleri($baslangic, $bitis),
                fn($g) => $this->haftaSonuDahil || !$this->haftaSonuMu($g)
            )),
        ];
    }

    public function takvim($baslangic, $bitis)
    {
        return $this->takvimGunleri($baslangic, $bitis);
    }

    public function haftaSonu($tarih)
    {
        return $this->haftaSonuMu($tarih);
    }
}
