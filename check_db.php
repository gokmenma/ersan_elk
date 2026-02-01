<?php
try {
    $p = new PDO('mysql:host=localhost;dbname=ersan_personel', 'root', '');

    foreach (['yapilan_isler', 'endeks_okuma'] as $table) {
        $q = $p->query("SHOW COLUMNS FROM $table LIKE 'silinme_tarihi'");
        if ($q && $q->rowCount() > 0) {
            echo "YES_$table\n";
        } else {
            echo "NO_$table\n";
        }
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
