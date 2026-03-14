<?php
require_once 'bootstrap.php';

$pdo = getDbConnection();

echo "Duplicate check for yapilan_isler:\n";
$stmt = $pdo->prepare("SELECT id, islem_id, personel_id, ekip_kodu_id, tarih, is_emri_sonucu FROM yapilan_isler WHERE (ekip_kodu_id = 1657) AND tarih = '2026-03-10' AND silinme_tarihi IS NULL");
$stmt->execute();
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: {$row['id']}, IslemID: {$row['islem_id']}, PersonID: {$row['personel_id']}, TeamID: {$row['ekip_kodu_id']}, Date: {$row['tarih']}, Result: {$row['is_emri_sonucu']}\n";
}
