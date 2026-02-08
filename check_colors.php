<?php
require_once 'Autoloader.php';
$model = new \App\Model\TanimlamalarModel();
$stmt = $model->db->prepare("SELECT tur_adi, kisa_kod, renk FROM tanimlamalar WHERE grup = 'izin_turu' AND silinme_tarihi IS NULL");
$stmt->execute();
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
