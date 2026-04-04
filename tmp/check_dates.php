<?php
require_once dirname(__DIR__) . '/Autoloader.php';
$db = (new App\Model\EvrakTakipModel())->getDb();
$stmt = $db->query("SELECT COUNT(*) as total, SUM(CASE WHEN son_bildirim_tarihi_personel IS NOT NULL THEN 1 ELSE 0 END) as p_not_null, SUM(CASE WHEN son_bildirim_tarihi_ilgili IS NOT NULL THEN 1 ELSE 0 END) as i_not_null FROM evrak_takip");
$res = $stmt->fetch(PDO::FETCH_ASSOC);
print_r($res);
?>
