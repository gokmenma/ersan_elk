<?php
require_once dirname(__DIR__) . '/bootstrap.php';
use App\Model\Model;

$db = (new Model('tanimlamalar'))->getDb();

// 1. Find all active szefs in "Beldeler"
$sql = "SELECT p.id, p.adi_soyadi, peg.ekip_kodu_id 
        FROM personel p
        JOIN personel_ekip_gecmisi peg ON p.id = peg.personel_id
        JOIN tanimlamalar t ON peg.ekip_kodu_id = t.id
        WHERE t.grup = 'ekip_kodu' AND t.ekip_bolge = 'Beldeler'
        AND peg.ekip_sefi_mi = 1
        AND (peg.bitis_tarihi IS NULL OR peg.bitis_tarihi >= CURDATE())
        AND p.silinme_tarihi IS NULL";
$res = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

print_r($res);
