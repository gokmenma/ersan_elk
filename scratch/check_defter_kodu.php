<?php
require_once 'Autoloader.php';
$db = (new \App\Core\Db())->getConnection();
$res = $db->query("SELECT tur_adi, defter_bolge, defter_mahalle FROM tanimlamalar WHERE grup = 'defter_kodu' AND silinme_tarihi IS NULL LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
print_r($res);
