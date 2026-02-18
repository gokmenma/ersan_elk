<?php
require_once __DIR__ . '/Autoloader.php';
use App\Core\Db;
$db = new Db();
echo "Fuel records: " . $db->db->query("SELECT COUNT(*) FROM arac_yakit_kayitlari")->fetchColumn() . "\n";
echo "Unique ExtIDs: " . $db->db->query("SELECT COUNT(DISTINCT external_id) FROM arac_yakit_kayitlari WHERE external_id IS NOT NULL AND external_id != ''")->fetchColumn() . "\n";
