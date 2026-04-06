<?php
require_once dirname(__DIR__) . '/bootstrap.php';
$db = (new \App\Model\Model('personel'))->getDb();
$stmt = $db->query("SELECT peg.*, t.tur_adi, t.ekip_bolge FROM personel_ekip_gecmisi peg JOIN tanimlamalar t ON peg.ekip_kodu_id = t.id WHERE peg.personel_id = 114");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
