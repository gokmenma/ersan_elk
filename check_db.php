<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=ersan_personel', 'root', '');
    $stmt = $pdo->query("SELECT firma_id, COUNT(*) as c FROM tanimlamalar WHERE grup = 'departman' GROUP BY firma_id");
    echo "=== TANIMLAMALAR (DEPARTMAN) ===\n";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "Firma ID: " . $row['firma_id'] . " -> Count: " . $row['c'] . "\n";
        $stmt2 = $pdo->prepare("SELECT tur_adi FROM tanimlamalar WHERE grup = 'departman' AND firma_id = ?");
        $stmt2->execute([$row['firma_id']]);
        while ($d = $stmt2->fetch())
            echo "  - " . $d['tur_adi'] . "\n";
    }
} catch (Exception $e) {
    echo $e->getMessage();
}
