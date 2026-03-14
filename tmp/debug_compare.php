<?php
require_once dirname(__DIR__) . '/bootstrap.php';
use App\Model\PersonelModel;

$pModel = new PersonelModel();

// Find UFUK ÇELİK
$stmt = $pModel->db->prepare("SELECT id, adi_soyadi FROM personel WHERE adi_soyadi = 'UFUK ÇELİK'");
$stmt->execute();
$ufuk = $stmt->fetch(PDO::FETCH_OBJ);

// Find Cuma Canlı
$stmt = $pModel->db->prepare("SELECT id, adi_soyadi FROM personel WHERE adi_soyadi = 'Cuma Canlı'");
$stmt->execute();
$cuma = $stmt->fetch(PDO::FETCH_OBJ);

echo "Ufuk ID: " . ($ufuk->id ?? 'Not found') . "\n";
echo "Cuma ID: " . ($cuma->id ?? 'Not found') . "\n";

if ($ufuk) {
    echo "\nUFUK ÇELİK (ID: {$ufuk->id}) - February 2026\n";
    $stmt = $pModel->db->prepare("SELECT COUNT(*) FROM yapilan_isler WHERE personel_id = ? AND tarih BETWEEN '2026-02-01' AND '2026-02-28' AND silinme_tarihi IS NULL");
    $stmt->execute([$ufuk->id]);
    echo "Records in yapilan_isler: " . $stmt->fetchColumn() . "\n";
    
    $stmt = $pModel->db->prepare("SELECT COUNT(*) FROM sayac_degisim WHERE personel_id = ? AND tarih BETWEEN '2026-02-01' AND '2026-02-28' AND silinme_tarihi IS NULL");
    $stmt->execute([$ufuk->id]);
    echo "Records in sayac_degisim: " . $stmt->fetchColumn() . "\n";
}

if ($cuma) {
    echo "\nCUMA CANLI (ID: {$cuma->id}) - February 2026\n";
    $stmt = $pModel->db->prepare("SELECT COUNT(*) FROM yapilan_isler WHERE personel_id = ? AND tarih BETWEEN '2026-02-01' AND '2026-02-28' AND silinme_tarihi IS NULL");
    $stmt->execute([$cuma->id]);
    echo "Records in yapilan_isler: " . $stmt->fetchColumn() . "\n";
    
    $stmt = $pModel->db->prepare("SELECT COUNT(*) FROM sayac_degisim WHERE personel_id = ? AND tarih BETWEEN '2026-02-01' AND '2026-02-28' AND silinme_tarihi IS NULL");
    $stmt->execute([$cuma->id]);
    echo "Records in sayac_degisim: " . $stmt->fetchColumn() . "\n";
}
