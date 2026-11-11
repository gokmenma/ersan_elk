<?php

namespace App\Service;

class NobetDagitimService
{
    public static function oner(array $p): array
    {
        $personeller = $p['personeller'];
        $baslangic = $p['baslangic'];
        $bitis = $p['bitis'];
        $gunler = $p['gunler'];
        $kisiSayisi = max(1, (int) $p['kisi_sayisi']);
        $strateji = $p['strateji'] === 'sirali' ? 'sirali' : 'adil';
        $aylikLimit = max(0, (int) $p['aylik_limit']);
        $ardisikEngel = !empty($p['ardisik_engel']);
        $izinler = $p['izinler'];
        $mevcut = $p['mevcut'];
        $gecmis = $p['gecmis'];

        $sayac = [];
        $ozelSayac = [];
        $son = [];
        $ayRapor = [];
        foreach ($personeller as $kisi) {
            $id = (int) $kisi['id'];
            $sayac[$id] = (int) ($gecmis[$id]['toplam'] ?? 0);
            $ozelSayac[$id] = (int) ($gecmis[$id]['ozel_gun'] ?? 0);
            $son[$id] = $gecmis[$id]['son_nobet'] ?? null;
            $ayRapor[$id] = 0;
        }

        $atananGun = [];
        foreach ($mevcut as $tarih => $idler) {
            foreach ($idler as $pid) {
                $atananGun[$pid][$tarih] = true;
            }
        }

        $onerilen = [];
        $bosKalan = [];
        $siraIndeks = 0;
        $kisiListesi = array_values($personeller);
        $n = count($kisiListesi);

        $gun = $baslangic;
        while ($gun <= $bitis) {
            $haftaGunu = (int) date('N', strtotime($gun));
            if (!in_array($haftaGunu, $gunler, true)) {
                $gun = date('Y-m-d', strtotime($gun . ' +1 day'));
                continue;
            }

            $mevcutSayi = count($mevcut[$gun] ?? []);
            $ihtiyac = $kisiSayisi - $mevcutSayi;
            $ozelGun = $haftaGunu === 7;
            $ay = substr($gun, 0, 7);

            $dun = date('Y-m-d', strtotime($gun . ' -1 day'));
            $yarin = date('Y-m-d', strtotime($gun . ' +1 day'));

            for ($k = 0; $k < $ihtiyac; $k++) {
                $uygun = [];
                $gevsek = [];
                foreach ($kisiListesi as $kisi) {
                    $id = (int) $kisi['id'];
                    if (!empty($atananGun[$id][$gun]) || !empty($izinler[$id][$gun])) {
                        continue;
                    }
                    if ($aylikLimit > 0 && self::aydakiMevcut($atananGun[$id] ?? [], $ay) >= $aylikLimit) {
                        continue;
                    }
                    $ardisik = !empty($atananGun[$id][$dun]) || !empty($atananGun[$id][$yarin]);
                    if ($ardisik && $ardisikEngel) {
                        $gevsek[] = $id;
                        continue;
                    }
                    $uygun[] = $id;
                }

                if (empty($uygun) && !empty($gevsek)) {
                    $uygun = $gevsek;
                }

                if (empty($uygun)) {
                    $bosKalan[] = $gun;
                    continue;
                }

                if ($strateji === 'sirali') {
                    $secilen = null;
                    for ($t = 0; $t < $n; $t++) {
                        $aday = (int) $kisiListesi[($siraIndeks + $t) % $n]['id'];
                        if (in_array($aday, $uygun, true)) {
                            $secilen = $aday;
                            $siraIndeks = ($siraIndeks + $t + 1) % $n;
                            break;
                        }
                    }
                } else {
                    usort($uygun, function ($a, $b) use ($ozelGun, $sayac, $ozelSayac, $son) {
                        if ($ozelGun && $ozelSayac[$a] !== $ozelSayac[$b]) {
                            return $ozelSayac[$a] <=> $ozelSayac[$b];
                        }
                        if ($sayac[$a] !== $sayac[$b]) {
                            return $sayac[$a] <=> $sayac[$b];
                        }
                        $sa = $son[$a] ?? '0000-00-00';
                        $sb = $son[$b] ?? '0000-00-00';
                        if ($sa !== $sb) {
                            return strcmp($sa, $sb);
                        }
                        return $a <=> $b;
                    });
                    $secilen = $uygun[0];
                }

                if ($secilen === null) {
                    $bosKalan[] = $gun;
                    continue;
                }

                $atananGun[$secilen][$gun] = true;
                $sayac[$secilen]++;
                if ($ozelGun) {
                    $ozelSayac[$secilen]++;
                }
                $son[$secilen] = $gun;
                $ayRapor[$secilen]++;
                $onerilen[] = ['personel_id' => $secilen, 'tarih' => $gun, 'ozel_gun' => $ozelGun];
            }

            $gun = date('Y-m-d', strtotime($gun . ' +1 day'));
        }

        return ['atamalar' => $onerilen, 'bos_kalan' => array_values(array_unique($bosKalan)), 'ozet' => $ayRapor];
    }

    private static function aydakiMevcut(array $gunler, string $ay): int
    {
        $say = 0;
        foreach ($gunler as $tarih => $v) {
            if (strpos($tarih, $ay) === 0) {
                $say++;
            }
        }
        return $say;
    }
}
