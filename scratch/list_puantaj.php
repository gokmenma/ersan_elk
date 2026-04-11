<?php
require_once 'vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

$db = (new App\Core\Db())->db;
$personel_id = 84;
$donem_id = 20;

$sql = "SELECT baslangic_tarihi, bitis_tarihi FROM bordro_donemi WHERE id = ?";
$stmt = $db->prepare($sql);
$stmt->execute([$donem_id]);
$donem = $stmt->fetch(PDO::FETCH_OBJ);

$sql = "SELECT id, tarih, aciklama FROM yapilan_isler 
        WHERE personel_id = ? 
        AND DATE(tarih) BETWEEN ? AND ?
        AND silinme_tarihi IS NULL";
$stmt = $db->prepare($sql);
$stmt->execute([$personel_id, $donem->baslangic_tarihi, $donem->bitis_tarihi]);
$records = $stmt->fetchAll(PDO::FETCH_OBJ);

echo "Records found: " . count($records) . "\n";
foreach ($records as $r) {
    echo $r->tarih . " | " . $r->aciklama . "\n";
}
