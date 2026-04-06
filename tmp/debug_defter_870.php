<?php
require_once dirname(__DIR__) . '/bootstrap.php';
$db = (new \App\Model\Model('tanimlamalar'))->getDb();
$stmt = $db->query("SELECT * FROM tanimlamalar WHERE grup = 'defter_kodu' AND tur_adi = '870'");
print_r($stmt->fetch(PDO::FETCH_ASSOC));
