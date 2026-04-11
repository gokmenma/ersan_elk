<?php
require_once 'vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

$db_obj = new App\Core\Db();
$db = $db_obj->db;
$sql = "SELECT p.id, p.adi_soyadi FROM personel p WHERE p.tc_kimlik_no = '23360750118'";
$res = $db->query($sql)->fetch(PDO::FETCH_OBJ);

if (!$res) {
    die("Personel bulunamadı.");
}

$personel_id = $res->id;
echo "Personel ID: $personel_id\n";

$sql = "SELECT peo.* FROM personel_ek_odemeler peo WHERE peo.personel_id = ? AND peo.silinme_tarihi IS NULL AND peo.tur = 'yemek_yardimi_tum'";
$stmt = $db->prepare($sql);
$stmt->execute([$personel_id]);
$records = $stmt->fetchAll(PDO::FETCH_OBJ);

echo "ID | DONEM | TUTAR | ACIKLAMA\n";
foreach ($records as $r) {
    printf("%s | %s | %s | %s\n", $r->id, $r->donem_id, $r->tutar, $r->aciklama);
}
