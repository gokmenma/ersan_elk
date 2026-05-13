<?php
require 'bootstrap.php';
$db = (new \App\Core\Db())->db;

$bpModel = new \App\Model\BordroPersonelModel();
$bpId = 1298;

$bp = $db->query("SELECT * FROM bordro_personel WHERE id = $bpId")->fetch(PDO::FETCH_OBJ);
$donem = (new \App\Model\BordroDonemModel())->getDonemById($bp->donem_id);

// Let's inspect parameters inside $bp
echo "Bordro Personel ID: " . $bp->id . "\n";
echo "Yemek Yardimi Dahil: '" . $bp->yemek_yardimi_dahil . "'\n";
echo "Yemek Yardimi Tutari: " . ($bp->yemek_yardimi_tutari ?? 'NULL') . "\n";
echo "Net Maas (db): " . $bp->net_maas . "\n";

// Call ortak gosterim degerleri
$r = $bpModel->hesaplaOrtakGosterimDegerleri($bp, $donem);

echo "\nOrtak Gosterim Degerleri:\n";
echo "isPrimUsulu: " . ($r['isPrimUsulu'] ? 'YES' : 'NO') . "\n";
echo "isInclusive: " . ($r['isInclusive'] ? 'YES' : 'NO') . "\n";
echo "displayMealDeduction: " . $r['displayMealDeduction'] . "\n";
echo "spouseDeduction: " . $r['spouseDeduction'] . "\n";
echo "topRowLabel: " . $r['topRowLabel'] . "\n";
echo "topRowValue: " . $r['topRowValue'] . "\n";
echo "modalBaseRowValue: " . $r['modalBaseRowValue'] . "\n";
echo "modalMaasFarkiGosterim: " . $r['modalMaasFarkiGosterim'] . "\n";
echo "toplamEkOdemeNonPuantaj: " . $r['toplamEkOdemeNonPuantaj'] . "\n";
echo "displayToplamAlacak: " . $r['displayToplamAlacak'] . "\n";

// Let's also look at the calculation details if stored
if (!empty($bp->hesaplama_detay)) {
    $detay = json_decode($bp->hesaplama_detay, true);
    echo "\nHesaplama Detayi -> Ozet:\n";
    print_r($detay['ozet'] ?? []);
    echo "\nHesaplama Detayi -> Odeme Dagilimi:\n";
    print_r($detay['odeme_dagilimi'] ?? []);
}
