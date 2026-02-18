<?php
require_once __DIR__ . '/Autoloader.php';
use App\Core\Db;

$db = new Db();
$stmt = $db->db->query("SELECT id, external_id, arac_id, tarih, toplam_tutar FROM arac_yakit_kayitlari ORDER BY id DESC LIMIT 5");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
