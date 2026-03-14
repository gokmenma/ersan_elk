<?php
try {
    $db = new PDO('mysql:host=localhost;dbname=mbeyazil_ersanelektrik;charset=utf8', 'root', '');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $tables = ['tanimlamalar', 'yapilan_isler', 'endeks_okuma', 'sayac_degisim'];
    foreach ($tables as $table) {
        echo "--- $table ---\n";
        $stmt = $db->query("DESCRIBE $table");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "{$row['Field']} - {$row['Type']}\n";
        }
        echo "\n";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
