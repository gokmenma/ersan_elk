<?php
require_once dirname(__DIR__) . '/Autoloader.php';
$db = (new App\Model\EvrakTakipModel())->getDb();
$stmt = $db->query("DESC evrak_takip");
$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($res);
?>
