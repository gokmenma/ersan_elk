<?php
require_once 'Autoloader.php';
$conn = new \App\Core\Db();
$db = $conn->db;
$stmt = $db->prepare("SELECT id, tur_adi, is_emri_sonucu, is_turu_ucret, rapor_sekmesi FROM tanimlamalar WHERE is_emri_sonucu LIKE '%DN 20 190 MM SAYAÇ%'");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Tanimlamalar Ucret Info:\n";
foreach ($rows as $row) {
    echo "ID: " . $row['id'] . " | RESULT: '" . $row['is_emri_sonucu'] . "' | UCRET: " . $row['is_turu_ucret'] . " | TAB: '" . $row['rapor_sekmesi'] . "'\n";
}
