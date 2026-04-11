<?php
require_once 'vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

$db = (new App\Core\Db())->db;
$personel_id = 84;
$donem_id = 20;

$p = $db->query("SELECT ekip_no FROM personel WHERE id = $personel_id")->fetch(PDO::FETCH_OBJ);
echo "Ekip No: [" . ($p->ekip_no ?? "NULL") . "]\n";

if ($p->ekip_no) {
    $sql = "SELECT baslangic_tarihi, bitis_tarihi FROM bordro_donemi WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$donem_id]);
    $donem = $stmt->fetch(PDO::FETCH_OBJ);

    $sql = "SELECT COUNT(DISTINCT DATE(tarih)) as gun_sayisi
            FROM yapilan_isler 
            WHERE ekip_kodu = ? 
            AND DATE(tarih) BETWEEN ? AND ?
            AND silinme_tarihi IS NULL";
    $stmt = $db->prepare($sql);
    $stmt->execute([$p->ekip_no, $donem->baslangic_tarihi, $donem->bitis_tarihi]);
    $res = $stmt->fetch(PDO::FETCH_OBJ);
    echo "Ekip Puantaj Gun Sayisi: " . $res->gun_sayisi . "\n";
}
