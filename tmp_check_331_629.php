<?php
require 'bootstrap.php';
$db = (new \App\Model\Model())->db;
$sql = "SELECT * FROM tanimlamalar WHERE id IN (331, 629)";
$res = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
echo "Tanımlamalar (331 vs 629):\n";
print_r($res);
