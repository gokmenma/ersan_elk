<?php
require_once dirname(__DIR__) . '/bootstrap.php';
use App\Model\Model;

$db = (new Model('tanimlamalar'))->getDb();

$sql = "SELECT p.adi_soyadi, t.ekip_bolge
        FROM personel p
        JOIN personel_ekip_gecmisi peg ON p.id = peg.personel_id
        JOIN tanimlamalar t ON peg.ekip_kodu_id = t.id
        WHERE t.grup = 'ekip_kodu'
        AND peg.ekip_sefi_mi = 1
        AND (peg.bitis_tarihi IS NULL OR peg.bitis_tarihi >= CURDATE())";
$szefs = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

foreach ($szefs as $szef) {
    $bolge = $szef['ekip_bolge'];
    
    // Check for exact match
    $sql1 = "SELECT COUNT(*) FROM tanimlamalar WHERE grup = 'defter_kodu' AND defter_bolge = ? AND silinme_tarihi IS NULL";
    $stmt1 = $db->prepare($sql1);
    $stmt1->execute([$bolge]);
    $count1 = $stmt1->fetchColumn();
    
    // Check for upper case match
    $sql2 = "SELECT COUNT(*) FROM tanimlamalar WHERE grup = 'defter_kodu' AND UPPER(defter_bolge) = ? AND silinme_tarihi IS NULL";
    $stmt2 = $db->prepare($sql2);
    $stmt2->execute([mb_strtoupper($bolge, 'UTF-8')]);
    $count2 = $stmt2->fetchColumn();

    echo "ŞEF: {$szef['adi_soyadi']} - Bölge: [$bolge]\n";
    echo "  -> EXACT Defters: $count1\n";
    echo "  -> UPPER Defters: $count2\n";
}
