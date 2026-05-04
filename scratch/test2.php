<?php
require_once __DIR__ . '/../bootstrap.php';
$db = (new App\Model\BordroPersonelModel())->getDb();
$s = $db->query("SELECT id, donem_adi FROM bordro_donemi");
print_r($s->fetchAll(PDO::FETCH_ASSOC));
