<?php
$path = '"'"'App/Model/BordroPersonelModel.php'"'"';
$text = file_get_contents($path);
$old = <<<'OLD'
            if ($isPrimUsuluDahilYardim && $isPuantajOdeme) {
                $primUsuluPuantajHedefToplami += $ekOdemeTutari;
                $netEkOdemeler -= $ekOdemeTutari;
                $detay['hedef_net_adayi'] = round($ekOdemeTutari, 2);
                $detay['net_etki'] = 0;
                $ekOdemeDetaylari[] = $detay;
                unset($toplamTutar);
                continue;
            }
OLD;
$new = <<<'NEW'
            if ($isPrimUsuluDahilYardim && $isPuantajOdeme) {
                $primUsuluPuantajHedefToplami += $ekOdemeTutari;

                $odemeHesaplamaTipi = mb_strtolower((string) ($parametre->hesaplama_tipi ?? $odeme->hesaplama_tipi ?? ''), 'UTF-8');
                if (strpos($odemeHesaplamaTipi, 'brut') !== false) {
                    $brutEkOdemeler -= $ekOdemeTutari;
                    if ($parametre && $parametre->sgk_matrahi_dahil) {
                        $sgkMatrahEkleri -= $ekOdemeTutari;
                    }
                    if ($parametre && $parametre->gelir_vergisi_dahil) {
                        $vergiliMatrahEkleri -= $ekOdemeTutari;
                    }
                } else {
                    $netEkOdemeler -= $ekOdemeTutari;
                }

                $detay['hedef_net_adayi'] = round($ekOdemeTutari, 2);
                $detay['donem_hedef_toplami'] = round($primUsuluPuantajHedefToplami, 2);
                $detay['net_etki'] = 0;
                $ekOdemeDetaylari[] = $detay;
                unset($toplamTutar);
                continue;
            }
NEW;
$text = str_replace($old, $new, $text, $count1);
$text = str_replace('if ($this->hasMaasaDahilSosyalYardim($kayit) && !$isPrimUsulu) {', 'if ($this->hasMaasaDahilSosyalYardim($kayit)) {', $text, $count2);
$old2 = <<<'OLD2'
            if ($isPrimUsuluDahilYardim && $primUsuluPuantajHedefToplami > 0) {
                $kayit->hedef_net_maas_tutari = $primUsuluPuantajHedefToplami;
            }
OLD2;
$new2 = <<<'NEW2'
            if ($isPrimUsuluDahilYardim && $primUsuluPuantajHedefToplami > 0) {
                $kayit->hedef_net_maas_tutari = ($maasHesapGunu > 0)
                    ? (($primUsuluPuantajHedefToplami / $maasHesapGunu) * 30)
                    : 0;
            }
NEW2;
$text = str_replace($old2, $new2, $text, $count3);
file_put_contents($path, $text);
echo "count1=$count1 count2=$count2 count3=$count3\n";
?>
