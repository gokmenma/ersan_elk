<?php
require_once 'Autoloader.php';
$db = (new \App\Core\Db())->getConnection();
$res = $db->query("SELECT bolge, defter, mahalle FROM endeks_okuma WHERE mahalle IS NOT NULL AND mahalle != '' LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
print_r($res);
