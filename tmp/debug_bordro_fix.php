<?php
// Correct path to bootstrap in root
require_once dirname(__DIR__) . '/bootstrap.php';

use App\Model\SayacDegisimModel;
use App\Model\BordroPersonelModel;

// Cuma Canlı
$personelId = 170;

$SayacDegisim = new SayacDegisimModel();
$BordroPersonel = new BordroPersonelModel();

// Get the latest period just for context - FIX: table name is bordro_donemi
$stmt = $SayacDegisim->db->prepare("SELECT id, donem_adi, baslangic_tarihi, bitis_tarihi FROM bordro_donemi ORDER BY id DESC LIMIT 1");
$stmt->execute();
$donem = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$donem) {
    die("No payroll period found!\n");
}

echo "Selected Period: " . $donem['donem_adi'] . " (" . $donem['baslangic_tarihi'] . " to " . $donem['bitis_tarihi'] . ")\n";

// Check sayac_degisim records for this period
$sql = "SELECT * FROM sayac_degisim WHERE personel_id = ? AND tarih BETWEEN ? AND ? AND silinme_tarihi IS NULL";
$stmt = $SayacDegisim->db->prepare($sql);
$stmt->execute([$personelId, $donem['baslangic_tarihi'], $donem['bitis_tarihi']]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Records found in sayac_degisim: " . count($results) . "\n";
if (count($results) > 0) {
    foreach ($results as $row) {
        echo "- Date: " . $row['tarih'] . ", Status: " . $row['isemri_sonucu'] . ", Abone: " . $row['abone_no'] . "\n";
    }
}

// Check what's in ek_odemeler
$sql = "SELECT * FROM personel_ek_odemeler WHERE personel_id = ? AND donem_id = ? AND silinme_tarihi IS NULL";
$stmt = $SayacDegisim->db->prepare($sql);
$stmt->execute([$personelId, $donem['id']]);
$ekOdemeler = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Records found in personel_ek_odemeler: " . count($ekOdemeler) . "\n";
foreach($ekOdemeler as $ek) {
    echo "- Desc: " . $ek['aciklama'] . ", Type: " . $ek['tur'] . ", Amount: " . $ek['tutar'] . " TL\n";
}
