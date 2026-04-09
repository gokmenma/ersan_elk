<?php
require_once 'Autoloader.php';
$db = (new \App\Model\AracKmModel())->getDb();
$stmt = $db->prepare("SELECT * FROM arac_km_kayitlari WHERE id = 3442");
$stmt->execute();
print_r($stmt->fetch(PDO::FETCH_ASSOC));
