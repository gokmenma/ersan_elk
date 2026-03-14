<?php
require_once 'vendor/autoload.php';

try {
    $model = new \App\Model\PuantajModel();
    $db = $model->getDb();
    $tables = ['yapilan_isler', 'endeks_okuma', 'sayac_degisim', 'kacak_kontrol', 'tanimlamalar'];
    
    foreach ($tables as $table) {
        echo "--- Table: $table ---\n";
        $stmt = $db->query("DESC $table");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "{$row['Field']} | {$row['Type']} | {$row['Null']} | {$row['Key']} | {$row['Default']} | {$row['Extra']}\n";
        }
        echo "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
