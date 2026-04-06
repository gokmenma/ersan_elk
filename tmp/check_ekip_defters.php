<?php
require_once dirname(__DIR__) . '/bootstrap.php';
$db = (new \App\Model\Model('endeks_okuma'))->getDb();
$sql = "SELECT DISTINCT e.defter, t.defter_bolge
        FROM endeks_okuma e
        LEFT JOIN tanimlamalar t ON e.defter = t.tur_adi AND t.grup = 'defter_kodu'
        WHERE e.ekip_kodu_id = 401
        LIMIT 20";
// 401 is ID for Ekip-111 based on previous scan: Array([2] => Array([id] => 401, [ekip_bolge] => Beldeler))
$stmt = $db->query($sql);
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
