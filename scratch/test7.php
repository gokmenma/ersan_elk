<?php
require_once __DIR__ . '/../bootstrap.php';
$_SESSION['firma_id'] = 1;
$BordroPersonel = new App\Model\BordroPersonelModel();
$BordroDonem = new App\Model\BordroDonemModel();
$BordroParametre = new App\Model\BordroParametreModel();

$selectedDonemId = 20;
$selectedDonem = $BordroDonem->getDonemById($selectedDonemId);
$asgariUcretNet = $BordroParametre->getGenelAyar('asgari_ucret_net', $selectedDonem->baslangic_tarihi) ?? 17002.12;
$personeller = $BordroPersonel->getPersonellerByDonem($selectedDonemId, [1264]);
$bp = $personeller[0];
$hesap = $BordroPersonel->hesaplaOrtakGosterimDegerleri($bp, $selectedDonem, floatval($asgariUcretNet));

$toplamAlacak = floatval($hesap['toplamAlacagi']);
$nominalMaas = floatval($bp->hd_nominal_maas ?? $bp->maas_tutari);
$calismaGunu = intval($hesap['calismaGunu']);
$guncelEkOdeme = floatval($hesap['rawEkOdeme']);

$contractHakedis = ($nominalMaas / 30) * $calismaGunu;
if (intval($bp->personel_id ?? 0) === 77 && ($selectedDonem->baslangic_tarihi ?? '') === '2026-04-01') {
    $contractHakedis = (33000 / 30) * 13;
}
$yuvarlamaFarki = 0;
if ($toplamAlacak > ($contractHakedis + $guncelEkOdeme) && $contractHakedis > 0) {
    $yuvarlamaFarki = $toplamAlacak - $contractHakedis - $guncelEkOdeme;
}

echo "Top Alacak: $toplamAlacak\n";
echo "Nominal Maas: $nominalMaas\n";
echo "Calisma Gunu: $calismaGunu\n";
echo "Guncel Ek Odeme: $guncelEkOdeme\n";
echo "Contract Hakedis: $contractHakedis\n";
echo "Yuvarlama Farki: $yuvarlamaFarki\n";
