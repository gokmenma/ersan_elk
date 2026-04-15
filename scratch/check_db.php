<?php
require_once 'Autoloader.php';
$db = new \App\Core\Db();
$stmt = $db->db->prepare("DESCRIBE personel");
$stmt->execute();
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
