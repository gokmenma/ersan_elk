<?php
require_once 'Autoloader.php';
$conn = new \App\Core\Db();
$db = $conn->db;
$stmt = $db->prepare("SELECT id, tur_adi, is_emri_sonucu FROM tanimlamalar WHERE is_emri_sonucu LIKE '%Periyodik Sayaç Değişim%'");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Tanimlamalar Periyodik matches:\n";
foreach ($rows as $row) {
    echo "ID: " . $row['id'] . " | TYPE: '" . $row['tur_adi'] . "' | RESULT: '" . $row['is_emri_sonucu'] . "'\n";
    echo "  Hex Result: " . bin2hex($row['is_emri_sonucu']) . "\n";
}
