<?php
$asgariNetNominal = 28075.50;
$maasHesapGunu = 29;
$fiiliGunSayisi = 25;
$hedefMaasTutari = 33000;
$ekKesintiTutar = 0;

$asgari_hakedis = round(($asgariNetNominal / 30) * $maasHesapGunu, 2);
$hedef_net_hakedis = round(($hedefMaasTutari / 30) * $maasHesapGunu, 2);

$kalanHakedis = max(0, $hedef_net_hakedis - $ekKesintiTutar);
$fark_tutari = max(0, round($kalanHakedis - $asgari_hakedis, 2));

$kalanFark = $fark_tutari;
$fiiliGun = max(0, $fiiliGunSayisi);

$calcFiiliGun = $fiiliGun > 0 ? $fiiliGun : 26;
$yemek_gunluk_ham = $kalanFark / $calcFiiliGun;
$gunlukYemek = ceil($yemek_gunluk_ham);

$yemek_toplam = min($kalanFark, round($gunlukYemek * $calcFiiliGun, 2));
$kalanFark = max(0, round($kalanFark - $yemek_toplam, 2));

echo "Asgari Hakedis: $asgari_hakedis\n";
echo "Target Hakedis: $hedef_net_hakedis\n";
echo "Diff: $fark_tutari\n";
echo "Daily Meal: $gunlukYemek\n";
echo "Total Meal: $yemek_toplam\n";
echo "Remaining: $kalanFark\n";
