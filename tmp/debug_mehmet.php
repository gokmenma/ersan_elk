<?php
require_once dirname(__DIR__) . '/bootstrap.php';
$db = (new \App\Model\Model('personel'))->getDb();

// 1. Current User: Mehmet Kiraz (114)
$p = $db->query("SELECT * FROM personel WHERE id = 114")->fetch(PDO::FETCH_ASSOC);
echo "Personnel: {$p['adi_soyadi']} (ID: 114)\n";
echo "Ekip No: {$p['ekip_no']} - Ekip Bolge: {$p['ekip_bolge']}\n\n";

// 2. Active Szef Role
$stmt = $db->query("SELECT peg.*, t.tur_adi, t.ekip_bolge 
                    FROM personel_ekip_gecmisi peg
                    JOIN tanimlamalar t ON peg.ekip_kodu_id = t.id
                    WHERE peg.personel_id = 114 
                    AND peg.ekip_sefi_mi = 1 
                    AND (peg.bitis_tarihi IS NULL OR peg.bitis_tarihi >= CURDATE())");
$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($roles as $r) {
    echo "Szef Role: [{$r['ekip_kodu_id']}] {$r['tur_adi']} - Region: {$r['ekip_bolge']}\n";
}

// 3. Defters which Mehmet Kiraz's team (111) is reading or should read
// Let's find defters where defter_bolge is 'Beldeler' (redundant but confirming)
$stmt = $db->query("SELECT COUNT(*) FROM tanimlamalar WHERE grup = 'defter_kodu' AND UPPER(defter_bolge) = 'BELDELER'");
echo "\nDefters with bolge 'BELDELER': " . $stmt->fetchColumn() . "\n";

// 4. Any defter with bolge 'Beldeler' at all?
$stmt = $db->query("SELECT id, tur_adi, defter_bolge FROM tanimlamalar WHERE defter_bolge LIKE '%Belde%'");
echo "Any record with 'Belde' in defter_bolge: " . $stmt->rowCount() . "\n";

// 5. Let's see the defters for 'ELBİSTAN' to see if any are 'Belde'
$stmt = $db->query("SELECT id, tur_adi, defter_mahalle FROM tanimlamalar WHERE grup = 'defter_kodu' AND (defter_bolge = 'ELBİSTAN' OR defter_bolge = 'Elbistan') LIMIT 10");
$elbistanDefters = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "\nSample ELBİSTAN Defters:\n";
foreach ($elbistanDefters as $d) {
    echo "- [{$d['tur_adi']}] Mahalle: {$d['defter_mahalle']}\n";
}
