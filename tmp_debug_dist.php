<?php
try {
    $db = new PDO('mysql:host=localhost;dbname=mbeyazil_ersanelektrik;charset=utf8', 'root', '');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $ekipId = 1657; // EKİP-51

    echo "\n--- VERIFIED YAPILAN İŞLER (EKİP-51, 10.03-13.03) ---\n";
    $stmt = $db->prepare("SELECT y.id, y.personel_id, p.adi_soyadi, y.tarih, y.sonuclanmis, y.aciklama 
                          FROM yapilan_isler y 
                          JOIN personel p ON y.personel_id = p.id
                          WHERE y.ekip_kodu_id = ? AND y.tarih BETWEEN '2026-03-10' AND '2026-03-13' AND y.silinme_tarihi IS NULL 
                          ORDER BY y.tarih DESC, y.id ASC");
    $stmt->execute([$ekipId]);
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
