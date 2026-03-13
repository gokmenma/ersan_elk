<?php
require_once 'Autoloader.php';
use App\Core\Db;

try {
    $dbClass = new Db();
    $db = $dbClass->getConnection();
    
    echo "Starting database normalization...\n";
    
    // 1. Tanimlamalar
    $sql1 = "UPDATE tanimlamalar SET tur_adi = TRIM(REPLACE(tur_adi, CHAR(160), ' ')), is_emri_sonucu = TRIM(REPLACE(is_emri_sonucu, CHAR(160), ' '))";
    $count1 = $db->exec($sql1);
    echo "Normalized records in 'tanimlamalar' (Affected: $count1).\n";
    
    // 2. Yapilan Isler
    $sql2 = "UPDATE yapilan_isler SET is_emri_tipi = TRIM(REPLACE(is_emri_tipi, CHAR(160), ' ')), is_emri_sonucu = TRIM(REPLACE(is_emri_sonucu, CHAR(160), ' '))";
    $count2 = $db->exec($sql2);
    echo "Normalized records in 'yapilan_isler' (Affected: $count2).\n";
    
    // 3. Endeks Okuma
    $sql3 = "UPDATE endeks_okuma SET bolge = TRIM(REPLACE(bolge, CHAR(160), ' ')), kullanici_adi = TRIM(REPLACE(kullanici_adi, CHAR(160), ' '))";
    $count3 = $db->exec($sql3);
    echo "Normalized records in 'endeks_okuma' (Affected: $count3).\n";
    
    // 4. Kacak Kontrol
    $sql4 = "UPDATE kacak_kontrol SET ekip_adi = TRIM(REPLACE(ekip_adi, CHAR(160), ' '))";
    $count4 = $db->exec($sql4);
    echo "Normalized records in 'kacak_kontrol' (Affected: $count4).\n";

    // 5. Cleanup duplicates in tanimlamalar (if any)
    // Actually, this is risky without knowing which one is 'correct' (fees, etc.)
    // But we should at least check for them.
    
    echo "Database normalization completed successfully.";
    
} catch (Exception $e) {
    echo "Error during normalization: " . $e->getMessage();
}
