<?php
require "Autoloader.php";
$id = 1;

$model = new \App\Model\CariHareketleriModel();
$db = $model->getDb();

$stmt = $db->prepare("SELECT COUNT(*) FROM cari_hareketleri WHERE cari_id = :cari_id");
$stmt->execute(['cari_id' => $id]);
echo "COUNT: " . $stmt->fetchColumn() . "\n";

$params = [
    'draw' => 1,
    'start' => 0,
    'length' => 50,
    'cari_id' => $id
];

print_r($model->ajaxList($params));
