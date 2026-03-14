<?php
require_once dirname(__DIR__) . '/bootstrap.php';
use App\Model\BordroPersonelModel;

if (!isset($_SESSION)) {
    session_start();
}
$_SESSION['firma_id'] = 1; // Try with 1 first
$_SESSION['user_id'] = 1;

$bpModel = new BordroPersonelModel();

$personel_id = 170;
$bp_id = 1179; 
$donem_id = 18; 

// Find the actual firma_id for this personel
$stmt = $bpModel->db->prepare("SELECT firma_id FROM personel WHERE id = ?");
$stmt->execute([$personel_id]);
$pFirma = $stmt->fetchColumn();
if ($pFirma) {
    $_SESSION['firma_id'] = $pFirma;
    echo "Confirmed firma_id for Cuma Canlı: " . $_SESSION['firma_id'] . "\n";
}

echo "Recalculating salary for Cuma Canlı (BP ID: $bp_id)...\n";

$result = $bpModel->hesaplaMaas($bp_id);

if ($result) {
    echo "Calculation successful!\n";
    
    $stmt = $bpModel->db->prepare("SELECT * FROM personel_ek_odemeler WHERE personel_id = ? AND donem_id = ? AND aciklama LIKE '[Sayaç]%' AND silinme_tarihi IS NULL");
    $stmt->execute([$personel_id, $donem_id]);
    $ekOdemeler = $stmt->fetchAll(PDO::FETCH_OBJ);
    
    echo "\nFound " . count($ekOdemeler) . " [Sayaç] extra payments in DB.\n";
    foreach ($ekOdemeler as $ek) {
        echo "- {$ek->aciklama}: {$ek->tutar} TL\n";
    }
} else {
    echo "Calculation failed!\n";
}
