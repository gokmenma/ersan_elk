<?php
require_once dirname(__DIR__) . '/bootstrap.php';
use App\Model\Model;

$db = (new Model('tanimlamalar'))->getDb();

// 1. Find szef for Andırın
$sql = "SELECT p.id, p.adi_soyadi, t.ekip_bolge
        FROM personel p
        JOIN personel_ekip_gecmisi peg ON p.id = peg.personel_id
        JOIN tanimlamalar t ON peg.ekip_kodu_id = t.id
        WHERE t.grup = 'ekip_kodu' AND t.ekip_bolge = 'Andırın'
        AND peg.ekip_sefi_mi = 1
        AND (peg.bitis_tarihi IS NULL OR peg.bitis_tarihi >= CURDATE())";
$szef = $db->query($sql)->fetch(PDO::FETCH_ASSOC);

if ($szef) {
    echo "Szef for Andırın: " . $szef['adi_soyadi'] . " (ID: " . $szef['id'] . ")\n";
    
    // Now simulate calling getDelayedReadings for this szef
    // Logic from api.php lines 893-969
    $bolge = $szef['ekip_bolge'];
    $firma_id = 1;

    $sql = "SELECT 
                t.id, 
                t.tur_adi as defter_kodu, 
                t.defter_mahalle as mahalle,
                t.baslangic_tarihi,
                (SELECT MAX(eo.tarih) 
                 FROM endeks_okuma eo 
                 WHERE eo.defter = t.tur_adi 
                 AND eo.firma_id = :firma_id 
                 AND eo.silinme_tarihi IS NULL) as son_okuma_tarihi
            FROM tanimlamalar t
            WHERE t.grup = 'defter_kodu' 
            AND t.defter_bolge = :bolge 
            AND t.firma_id = :firma_id 
            AND t.silinme_tarihi IS NULL";

    $stmt = $db->prepare($sql);
    $stmt->execute(['bolge' => mb_strtoupper($bolge, 'UTF-8'), 'firma_id' => $firma_id]);
    $defterler = $stmt->fetchAll(PDO::FETCH_OBJ);

    echo "Found " . count($defterler) . " defters for Andırın (using UPPER region)\n";
    
    $stmt->execute(['bolge' => $bolge, 'firma_id' => $firma_id]);
    $defterler2 = $stmt->fetchAll(PDO::FETCH_OBJ);
    echo "Found " . count($defterler2) . " defters for Andırın (using exact Title Case region)\n";
}
