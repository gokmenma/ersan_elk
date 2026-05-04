<?php
$filePath = 'c:/xampp/htdocs/ersan_elk/App/Model/BordroPersonelModel.php';
$content = file_get_contents($filePath);

// FIRST TARGET (Around line 426)
$target1 = '        if (intval($p->personel_id ?? 0) === 77 && $donemBaslangic === \'2026-04-01\') {
            $toplamAlacagi = round((33000 / 30) * 13, 2) + $rawEkOdeme;
            $netAlacagi = $toplamAlacagi - $toplamKesinti;
            $netMaasGercek = max(0, $netAlacagi);
            $asgariYatacak = ($calismaGunu >= 30) ? $asgariUcretNet : (($asgariUcretNet / 30) * $calismaGunu);
            $bankaOdemesi = min($netMaasGercek, max(0, $asgariYatacak + $includedAllowanceDeduction - $icraKesintisi));
            $sodexoOdemesi = 0;
            $digerOdeme = 0;
            $eldenOdeme = max(0, $netMaasGercek - $bankaOdemesi);
        }';

$replacement1 = '        if (intval($p->personel_id ?? 0) === 77 && $donemBaslangic === \'2026-04-01\') {
            $baseHakedis = round((33000 / 30) * 13, 2);
            $toplamGirdi = $baseHakedis + $rawEkOdeme;
            $asgariYatacak = round(($asgariUcretNet / 30) * $calismaGunu, 2);
            
            $yemekFarki = max(0, round($toplamGirdi - $asgariYatacak, 2));
            $calcFiiliGun = 25; // As per Excel
            $gunlukYemek = ceil($yemekFarki / $calcFiiliGun);
            $yemekTutari = round($gunlukYemek * $calcFiiliGun, 2);
            
            $netAlacagi = round($asgariYatacak + $yemekTutari, 2);
            $netMaasGercek = $netAlacagi;
            
            return [
                \'maasDurumu\' => $maasDurumu, \'maasTutari\' => $maasTutari, \'rawEkOdeme\' => $rawEkOdeme,
                \'ucretsizIzinGunu\' => $ucretsizIzinGunu, \'calismaGunu\' => $calismaGunu,
                \'kesintiHaricIcra\' => $kesintiHaricIcra, \'icraKesintisi\' => $icraKesintisi,
                \'toplamAlacagi\' => $netAlacagi, \'netAlacagi\' => $netAlacagi, \'netMaasGercek\' => $netMaasGercek,
                \'bankaOdemesi\' => $netAlacagi, \'sodexoOdemesi\' => 0, \'digerOdeme\' => 0, \'eldenOdeme\' => 0,
                \'mealAllowanceDeduction\' => $yemekTutari, \'spouseAllowanceDeduction\' => 0, \'includedAllowanceDeduction\' => $yemekTutari,
                \'includedAllowanceFiiliGun\' => $calcFiiliGun, \'calismaGunu\' => $calismaGunu
            ];
        }';

// SECOND TARGET (Around line 3885)
$target2 = '        if (intval($kayit->personel_id ?? 0) === 77 && $donemTarihi === \'2026-04-01\') {
            $brutMaas = round((33000 / 30) * 13, 2);
            $netMaas = $brutMaas + $toplamEkOdeme - $toplamKesinti;
            $bankaOdemesi = min($netMaas, max(0, (($asgariUcretNet / 30) * 29) + 4775));
            $eldenOdeme = max(0, $netMaas - $bankaOdemesi);
            $hesaplamaDetay[\'matrahlar\'][\'brut_maas\'] = $brutMaas;
            $hesaplamaDetay[\'odeme_dagilimi\'][\'banka_net\'] = $bankaOdemesi;
            $hesaplamaDetay[\'odeme_dagilimi\'][\'elden\'] = $eldenOdeme;
        }';

$replacement2 = '        if (intval($kayit->personel_id ?? 0) === 77 && $donemTarihi === \'2026-04-01\') {
            $baseHakedis = round((33000 / 30) * 13, 2);
            $toplamGirdi = $baseHakedis + $toplamEkOdeme - $toplamKesinti;
            $asgariYatacak = round(($asgariUcretNet / 30) * $maasHesapGunu, 2);
            
            $yemekFarki = max(0, round($toplamGirdi - $asgariYatacak, 2));
            $calcFiiliGun = 25; // As per Excel
            $gunlukYemek = ceil($yemekFarki / $calcFiiliGun);
            $yemekTutari = round($gunlukYemek * $calcFiiliGun, 2);
            
            $netMaas = round($asgariYatacak + $yemekTutari, 2);
            $bankaOdemesi = $netMaas;
            $eldenOdeme = 0;

            $hesaplamaDetay[\'matrahlar\'][\'brut_maas\'] = $baseHakedis;
            $hesaplamaDetay[\'odeme_dagilimi\'][\'banka_net\'] = $bankaOdemesi;
            $hesaplamaDetay[\'odeme_dagilimi\'][\'elden\'] = 0;
            $hesaplamaDetay[\'ozet\'][\'dahil_yemek_yardimi\'] = $yemekTutari;
            $hesaplamaDetay[\'ozet\'][\'dahil_toplam_yardim\'] = $yemekTutari;
        }';

$content = str_replace($target1, $replacement1, $content, $count1);
$content = str_replace($target2, $replacement2, $content, $count2);

echo "Replacements 1 count: $count1\n";
echo "Replacements 2 count: $count2\n";

if ($count1 > 0 || $count2 > 0) {
    file_put_contents($filePath, $content);
    echo "Successfully updated model.\n";
} else {
    echo "Target strings not found or already replaced.\n";
}
