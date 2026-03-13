<?php
require_once 'Autoloader.php';
$conn = new \App\Core\Db();
$db = $conn->db;
$stmt = $db->prepare("SELECT id, tur_adi, is_emri_sonucu, aciklama FROM tanimlamalar WHERE is_emri_sonucu LIKE '%DN 20 190 MM SAYAÇ%'");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Tanimlamalar matches:\n";
foreach ($rows as $row) {
    echo "ID: " . $row['id'] . " | TYPE: '" . $row['tur_adi'] . "' | RESULT: '" . $row['is_emri_sonucu'] . "' | DESC: '" . $row['aciklama'] . "'\n";
    echo "  Hex Type: " . bin2hex($row['tur_adi']) . "\n";
    echo "  Hex Result: " . bin2hex($row['is_emri_sonucu']) . "\n";
}
