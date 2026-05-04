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
print_r($hesap);
