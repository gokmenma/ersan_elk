<?php
require_once __DIR__ . '/Autoloader.php';
use App\Model\BordroPersonelModel;

$BordroPersonel = new BordroPersonelModel();
$stmt = $BordroPersonel->db->prepare("SELECT donem_id FROM bordro_personel LIMIT 1");
$stmt->execute();
$donem_id = $stmt->fetchColumn();

if ($donem_id) {
    $p = $BordroPersonel->getPersonellerByDonem($donem_id);
    var_dump($p[0]);
} else {
    echo "NO DONEM FOUND IN BORDRO_PERSONEL";
}
