<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=ersan_personel', 'root', '');
    $stmt = $pdo->query("SELECT DISTINCT grup FROM tanimlamalar");
    echo "=== MEVCUT GRUPLAR ===\n";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo $row['grup'] . "\n";
    }
} catch (Exception $e) {
    echo $e->getMessage();
}
