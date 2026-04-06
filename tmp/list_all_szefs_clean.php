<?php
require_once dirname(__DIR__) . '/bootstrap.php';
$db = (new \App\Model\Model('tanimlamalar'))->getDb();
$sql = "SELECT t.tur_adi as ekip_adi, t.ekip_bolge, p.adi_soyadi as sef_adi, p.id as sef_id, p.departman 
        FROM tanimlamalar t 
        JOIN personel_ekip_gecmisi peg ON t.id = peg.ekip_kodu_id AND peg.ekip_sefi_mi = 1 AND (peg.bitis_tarihi IS NULL OR peg.bitis_tarihi >= CURDATE())
        JOIN personel p ON peg.personel_id = p.id 
        WHERE t.grup = 'ekip_kodu' AND t.silinme_tarihi IS NULL
        ORDER BY t.ekip_bolge";
$stmt = $db->query($sql);
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
