<?php
require_once 'Autoloader.php';
$dbClass = new \App\Core\Db();
$db = $dbClass->getConnection();
// Current user name
$adi_soyadi = "Ersan ELK"; // Or whatever is in session?
// Actually, I'll just search for a record where id = some known admin
$stmt = $db->query("SELECT id FROM personel LIMIT 1");
echo $stmt->fetchColumn();
