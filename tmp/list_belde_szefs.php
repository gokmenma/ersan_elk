<?php
require_once dirname(__DIR__) . '/bootstrap.php';
$db = (new \App\Model\Model('personel'))->getDb();
$stmt = $db->query("SELECT peg.*, p.adi_soyadi, t.ekip_bolge 
                    FROM personel_ekip_gecmisi peg
                    JOIN personel p ON peg.personel_id = p.id
                    JOIN tanimlamalar t ON peg.ekip_kodu_id = t.id
                    WHERE peg.ekip_sefi_mi = 1 
                    AND (peg.bitis_tarihi IS NULL OR peg.bitis_tarihi >= CURDATE())
                    AND t.ekip_bolge LIKE '%Belde%'");
$szefs = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($szefs);
