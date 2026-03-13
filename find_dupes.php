<?php
require_once 'Autoloader.php';
$conn = new \App\Core\Db();
$db = $conn->db;
$stmt = $db->prepare("SELECT is_emri_sonucu, COUNT(*) as c FROM tanimlamalar WHERE grup = 'is_turu' GROUP BY is_emri_sonucu HAVING c > 0");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Duplicate check (by name):\n";
foreach ($rows as $row) {
    $name = $row['is_emri_sonucu'];
    $stmt2 = $db->prepare("SELECT id, tur_adi, is_emri_sonucu, is_turu_ucret FROM tanimlamalar WHERE TRIM(REPLACE(is_emri_sonucu, CHAR(160), ' ')) = ?");
    $stmt2->execute([trim(str_replace("\xA0", ' ', $name))]);
    $variants = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    if (count($variants) > 1) {
        echo "Match for '" . trim($name) . "':\n";
        foreach ($variants as $v) {
            echo "  ID: " . $v['id'] . " | TYPE: '" . $v['tur_adi'] . "' | RESULT: '" . $v['is_emri_sonucu'] . "' (Hex: " . bin2hex($v['is_emri_sonucu']) . ") | UCRET: " . $v['is_turu_ucret'] . "\n";
        }
    }
}
