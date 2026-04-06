<?php
require_once dirname(__DIR__) . '/bootstrap.php';
$db = (new \App\Model\Model('personel'))->getDb();
// Find personnel who are şef of Ekip 111
$stmt = $db->query("SELECT peg.*, p.adi_soyadi 
                    FROM personel_ekip_gecmisi peg
                    JOIN personel p ON peg.personel_id = p.id
                    WHERE peg.ekip_kodu_id = 401 
                    AND peg.ekip_sefi_mi = 1 
                    AND (peg.bitis_tarihi IS NULL OR peg.bitis_tarihi >= CURDATE())");
$szefs = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($szefs);
