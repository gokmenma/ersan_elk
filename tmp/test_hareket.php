<?php
require "Autoloader.php";
$db = new \App\Model\CariHareketleriModel();
$res = $db->getDb()->query("SELECT id, cari_id, borc, alacak, islem_tarihi FROM cari_hareketleri")->fetchAll(PDO::FETCH_ASSOC);
print_r($res);
