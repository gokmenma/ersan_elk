<?php
require_once dirname(__DIR__) . '/bootstrap.php';
use App\Model\Model;

$db = (new Model('tanimlamalar'))->getDb();

$sql = "SELECT p.id, p.adi_soyadi, peg.ekip_kodu_id, t.ekip_bolge, peg.ekip_sefi_mi
        FROM personel p
        LEFT JOIN personel_ekip_gecmisi peg ON p.id = peg.personel_id
        LEFT JOIN tanimlamalar t ON peg.ekip_kodu_id = t.id
        WHERE p.adi_soyadi LIKE '%KAAN%' AND p.silinme_tarihi IS NULL";
$res = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

print_r($res);
