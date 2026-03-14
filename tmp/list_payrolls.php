<?php
require_once dirname(__DIR__) . '/bootstrap.php';
$pModel = new \App\Model\PersonelModel();
$db = $pModel->db;
$sql = "SELECT bp.id as bp_id, bp.donem_id, bd.baslangic_tarihi, bd.id as bd_id
        FROM bordro_personel bp 
        JOIN bordro_donemi bd ON bp.donem_id = bd.id 
        WHERE bp.personel_id = 170 
        ORDER BY bd.baslangic_tarihi DESC 
        LIMIT 5";
$stmt = $db->prepare($sql);
$stmt->execute();
print_r($stmt->fetchAll(PDO::FETCH_OBJ));
