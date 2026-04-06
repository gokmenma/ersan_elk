<?php
require_once dirname(__DIR__) . '/bootstrap.php';
$db = (new \App\Model\Model('tanimlamalar'))->getDb();
$stmt = $db->query("SELECT id, tur_adi, defter_bolge, ekip_bolge FROM tanimlamalar WHERE defter_bolge LIKE '%Belde%' OR ekip_bolge LIKE '%Belde%'");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
