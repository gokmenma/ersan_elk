<?php

namespace App\Service;

class OkumaMesaiAnalizService
{
    const SEVIYE_SUPHELI = 'supheli';
    const SEVIYE_KRITIK = 'kritik';

    private $esikSaniye;

    public function __construct($esikDakika = 30)
    {
        $esikDakika = (int) $esikDakika;
        if ($esikDakika < 5) {
            $esikDakika = 5;
        }
        if ($esikDakika > 480) {
            $esikDakika = 480;
        }

        $this->esikSaniye = $esikDakika * 60;
    }

    public function esikDakika()
    {
        return (int) ($this->esikSaniye / 60);
    }

    public function analizEt(array $okumalar, array $ekipTanimlari = [])
    {
        $tanimHaritasi = [];
        foreach ($ekipTanimlari as $tanim) {
            $no = \App\Helper\EkipHelper::extractTeamNo((string) $tanim->tur_adi);
            if ($no > 0) {
                $tanimHaritasi[$no] = $tanim;
            }
        }

        $gruplar = [];

        foreach ($okumalar as $okuma) {
            $ekipKodu = trim((string) $okuma->ekip_kodu);
            $ekipAdi = trim((string) $okuma->ekip_adi);

            $ekipNo = $ekipAdi !== '' ? \App\Helper\EkipHelper::extractTeamNo($ekipAdi) : 0;

            if ($ekipNo > 0) {
                $kimlik = 'EKIP-' . $ekipNo;
            } elseif ($ekipKodu !== '') {
                $kimlik = $ekipKodu;
            } else {
                $kimlik = $ekipAdi !== '' ? $ekipAdi : 'TANIMSIZ';
            }

            if ($ekipKodu === '') {
                $ekipKodu = $ekipAdi !== '' ? $ekipAdi : $kimlik;
            }

            $anahtar = $kimlik . '|' . $okuma->tarih;

            if (!isset($gruplar[$anahtar])) {
                $gruplar[$anahtar] = [
                    'ekip_kodu' => $ekipKodu,
                    'ekip_adi' => $okuma->ekip_adi,
                    'tarih' => $okuma->tarih,
                    'okumalar' => [],
                    'bolgeler' => [],
                    'mahalleler' => [],
                ];
            }

            $gruplar[$anahtar]['okumalar'][] = $okuma;

            $bolge = trim((string) $okuma->bolge);
            if ($bolge !== '') {
                $gruplar[$anahtar]['bolgeler'][$bolge] = ($gruplar[$anahtar]['bolgeler'][$bolge] ?? 0) + 1;
            }

            $mahalle = trim((string) $okuma->mahalle);
            if ($mahalle !== '') {
                $gruplar[$anahtar]['mahalleler'][$mahalle] = ($gruplar[$anahtar]['mahalleler'][$mahalle] ?? 0) + 1;
            }
        }

        $sonuclar = [];

        foreach ($gruplar as $anahtar => $grup) {
            usort($grup['okumalar'], function ($a, $b) {
                return strcmp($a->okuma_zamani, $b->okuma_zamani);
            });

            $zamanlar = array_map(fn($o) => strtotime($o->okuma_zamani), $grup['okumalar']);
            $ilk = $zamanlar[0];
            $son = end($zamanlar);
            $sahadaSure = $son - $ilk;

            $bosluklar = $this->bosluklariBul($grup['okumalar'], $zamanlar);
            $boslukToplami = array_sum(array_column($bosluklar, 'sure'));
            $netCalisma = max(0, $sahadaSure - $boslukToplami);

            $okumaSayisi = count($grup['okumalar']);
            $netSaat = $netCalisma / 3600;

            arsort($grup['bolgeler']);
            arsort($grup['mahalleler']);

            $sonuclar[] = [
                'anahtar' => $anahtar,
                'ekip_kodu' => $grup['ekip_kodu'],
                'ekip_adi' => $grup['ekip_adi'],
                'tarih' => $grup['tarih'],
                'ilk_okuma' => $ilk,
                'son_okuma' => $son,
                'sahada_sure' => $sahadaSure,
                'net_calisma' => $netCalisma,
                'bosluk_toplami' => $boslukToplami,
                'okuma_sayisi' => $okumaSayisi,
                'okuma_hizi' => $netSaat > 0 ? round($okumaSayisi / $netSaat, 1) : 0.0,
                'bosluklar' => $bosluklar,
                'supheli_bosluk' => count(array_filter($bosluklar, fn($b) => $b['seviye'] === self::SEVIYE_SUPHELI)),
                'kritik_bosluk' => count(array_filter($bosluklar, fn($b) => $b['seviye'] === self::SEVIYE_KRITIK)),
                'bolgeler' => $grup['bolgeler'],
                'mahalleler' => $grup['mahalleler'],
                'saatlik_dagilim' => $this->saatlikDagilim($zamanlar),
                'okumalar' => $grup['okumalar'],
            ];
        }

        usort($sonuclar, function ($a, $b) {
            $fark = ($b['kritik_bosluk'] + $b['supheli_bosluk']) <=> ($a['kritik_bosluk'] + $a['supheli_bosluk']);
            if ($fark !== 0) {
                return $fark;
            }
            $fark = strcmp($b['tarih'], $a['tarih']);
            return $fark !== 0 ? $fark : strcmp($a['ekip_kodu'], $b['ekip_kodu']);
        });

        return $sonuclar;
    }

    private function bosluklariBul(array $okumalar, array $zamanlar)
    {
        $bosluklar = [];
        $adet = count($zamanlar);

        for ($i = 1; $i < $adet; $i++) {
            $fark = $zamanlar[$i] - $zamanlar[$i - 1];

            if ($fark < $this->esikSaniye) {
                continue;
            }

            $oncekiOkuma = $okumalar[$i - 1];
            $sonrakiOkuma = $okumalar[$i];

            $bosluklar[] = [
                'baslangic' => $zamanlar[$i - 1],
                'bitis' => $zamanlar[$i],
                'sure' => $fark,
                'seviye' => $fark >= ($this->esikSaniye * 2) ? self::SEVIYE_KRITIK : self::SEVIYE_SUPHELI,
                'onceki' => [
                    'abone_no' => $oncekiOkuma->abone_no,
                    'abone_adsoyad' => $oncekiOkuma->abone_adsoyad,
                    'mahalle' => $oncekiOkuma->mahalle,
                    'defter' => $oncekiOkuma->defter,
                    'sayfa' => $oncekiOkuma->sayfa,
                    'sira_no' => $oncekiOkuma->sira_no,
                ],
                'sonraki' => [
                    'abone_no' => $sonrakiOkuma->abone_no,
                    'abone_adsoyad' => $sonrakiOkuma->abone_adsoyad,
                    'mahalle' => $sonrakiOkuma->mahalle,
                    'defter' => $sonrakiOkuma->defter,
                    'sayfa' => $sonrakiOkuma->sayfa,
                    'sira_no' => $sonrakiOkuma->sira_no,
                ],
            ];
        }

        return $bosluklar;
    }

    private function saatlikDagilim(array $zamanlar)
    {
        $dagilim = array_fill(0, 24, 0);

        foreach ($zamanlar as $zaman) {
            $saat = (int) date('G', $zaman);
            $dagilim[$saat]++;
        }

        return $dagilim;
    }

    public function genelOzet(array $sonuclar)
    {
        $tumBosluklar = [];
        foreach ($sonuclar as $satir) {
            foreach ($satir['bosluklar'] as $bosluk) {
                $tumBosluklar[] = $bosluk;
            }
        }

        $ekipler = array_unique(array_column($sonuclar, 'ekip_kodu'));

        return [
            'okuma_sayisi' => array_sum(array_column($sonuclar, 'okuma_sayisi')),
            'ekip_sayisi' => count($ekipler),
            'ekip_gun' => count($sonuclar),
            'bosluk_sayisi' => count($tumBosluklar),
            'kritik_bosluk' => count(array_filter($tumBosluklar, fn($b) => $b['seviye'] === self::SEVIYE_KRITIK)),
            'bosluk_suresi' => array_sum(array_column($tumBosluklar, 'sure')),
            'sahada_sure' => array_sum(array_column($sonuclar, 'sahada_sure')),
            'net_calisma' => array_sum(array_column($sonuclar, 'net_calisma')),
        ];
    }

    public function bolgeOzeti(array $sonuclar)
    {
        $ozet = [];

        foreach ($sonuclar as $satir) {
            $bolge = !empty($satir['bolgeler']) ? (string) array_key_first($satir['bolgeler']) : 'TANIMSIZ';

            if (!isset($ozet[$bolge])) {
                $ozet[$bolge] = [
                    'bolge' => $bolge,
                    'ekipler' => [],
                    'ekip_gun' => 0,
                    'okuma_sayisi' => 0,
                    'bosluk_sayisi' => 0,
                    'kritik_bosluk' => 0,
                    'bosluk_suresi' => 0,
                    'sahada_sure' => 0,
                    'net_calisma' => 0,
                ];
            }

            $ozet[$bolge]['ekipler'][$satir['ekip_kodu']] = true;
            $ozet[$bolge]['ekip_gun']++;
            $ozet[$bolge]['okuma_sayisi'] += $satir['okuma_sayisi'];
            $ozet[$bolge]['bosluk_sayisi'] += count($satir['bosluklar']);
            $ozet[$bolge]['kritik_bosluk'] += $satir['kritik_bosluk'];
            $ozet[$bolge]['bosluk_suresi'] += $satir['bosluk_toplami'];
            $ozet[$bolge]['sahada_sure'] += $satir['sahada_sure'];
            $ozet[$bolge]['net_calisma'] += $satir['net_calisma'];
        }

        foreach ($ozet as &$satir) {
            $satir['ekip_sayisi'] = count($satir['ekipler']);
            unset($satir['ekipler']);
            $satir['bosluk_orani'] = $satir['sahada_sure'] > 0
                ? round($satir['bosluk_suresi'] * 100 / $satir['sahada_sure'], 1)
                : 0.0;
        }
        unset($satir);

        uasort($ozet, fn($a, $b) => $b['okuma_sayisi'] <=> $a['okuma_sayisi']);

        return $ozet;
    }

    public static function sureMetni($saniye)
    {
        $saniye = (int) $saniye;

        if ($saniye <= 0) {
            return '0 dk';
        }

        $saat = intdiv($saniye, 3600);
        $dakika = intdiv($saniye % 3600, 60);

        if ($saat > 0 && $dakika > 0) {
            return $saat . ' sa ' . $dakika . ' dk';
        }
        if ($saat > 0) {
            return $saat . ' sa';
        }

        return $dakika . ' dk';
    }
}
