<?php
require 'bootstrap.php';
$bp = new \App\Model\BordroPersonelModel();
$stmt = $bp->db->query("SELECT id FROM bordro_personel WHERE donem_id = 19 AND personel_id = 77");
$id = $stmt->fetchColumn();
echo "Bordro Personel ID: $id\n";
$bp->hesaplaMaas($id);
echo "Hesaplandı.\n";

$stmt2 = $bp->db->query("SELECT aciklama, tutar, donem_id FROM personel_ek_odemeler WHERE donem_id = 19 AND personel_id = 77 AND aciklama LIKE '%Puantaj%' AND silinme_tarihi IS NULL");
print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));
