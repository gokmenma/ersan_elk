<?php
require_once dirname(__DIR__) . '/bootstrap.php';
$db = (new \App\Model\Model('personel'))->getDb();
$stmt = $db->query("SELECT peg.*, p.adi_soyadi, t.tur_adi, t.ekip_bolge 
                    FROM personel_ekip_gecmisi peg
                    JOIN personel p ON peg.personel_id = p.id
                    JOIN tanimlamalar t ON peg.ekip_kodu_id = t.id
                    WHERE peg.ekip_sefi_mi = 1 
                    AND (peg.bitis_tarihi IS NULL OR peg.bitis_tarihi >= CURDATE())");
$szefs = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($szefs as $s) {
    echo "ID: {$s['personel_id']} - Name: {$s['adi_soyadi']} - Ekip: {$s['tur_adi']} - Region: {$s['ekip_bolge']}\n";
}
