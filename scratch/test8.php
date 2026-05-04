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

$asgariNetNominal = $asgariUcretNet;
$fiiliGunSayisi = $BordroPersonel->getPuantajXGunSayisi($bp->personel_id, $selectedDonem->baslangic_tarihi, $selectedDonem->bitis_tarihi);
$totalDeductions = floatval($bp->kesinti_tutar ?? 0);
$rawEkOdeme = floatval($bp->prim_tutar ?? 0);

// Let's call the private function via reflection
$ref = new ReflectionMethod($BordroPersonel, 'hesaplaMaasaDahilYardimDagilimi');
$ref->setAccessible(true);
$dahilDagilim = $ref->invoke($BordroPersonel, $bp, $asgariNetNominal, $bp->calisan_gun, $fiiliGunSayisi, $totalDeductions, $rawEkOdeme);

print_r($dahilDagilim);
