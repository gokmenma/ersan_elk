<?php
require_once 'vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

$db = (new App\Core\Db())->db;

$donem_id = 20; // From previous dump
$sql = "SELECT baslangic_tarihi, bitis_tarihi FROM bordro_donemi WHERE id = ?";
$stmt = $db->prepare($sql);
$stmt->execute([$donem_id]);
$donem = $stmt->fetch(PDO::FETCH_OBJ);

echo "Baslangic: " . $donem->baslangic_tarihi . "\n";
echo "Bitis: " . $donem->bitis_tarihi . "\n";

$personel_id = 84;
$sql = "SELECT COUNT(DISTINCT DATE(tarih)) as gun_sayisi
        FROM yapilan_isler 
        WHERE personel_id = ? 
        AND DATE(tarih) BETWEEN ? AND ?
        AND silinme_tarihi IS NULL";
$stmt = $db->prepare($sql);
$stmt->execute([$personel_id, $donem->baslangic_tarihi, $donem->bitis_tarihi]);
$res = $stmt->fetch(PDO::FETCH_OBJ);

echo "Calisma Gunu Sayisi (Puantaj): " . $res->gun_sayisi . "\n";
