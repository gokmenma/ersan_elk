<?php
$db = new PDO('mysql:host=localhost;dbname=ersan_personel;charset=utf8', 'root', '');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
try {
    $sql = "SELECT COUNT(*) FROM (SELECT z.*, (SELECT COALESCE(SUM(miktar), 0) FROM demirbas_hareketler h_sub WHERE h_sub.zimmet_id = z.id AND h_sub.hareket_tipi IN ('iade', 'sarf', 'kayip') AND h_sub.silinme_tarihi IS NULL) as iade_miktar FROM demirbas_zimmet z ) AS temp";
    $stmtObj = $db->query($sql);
    print_r($stmtObj->fetchAll());
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
