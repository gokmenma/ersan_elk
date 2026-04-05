<?php
try {
    $db = new PDO('mysql:host=localhost;dbname=ersantrc_personel;charset=utf8', 'root', '');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $tables = ['tanimlamalar', 'yapilan_isler', 'endeks_okuma', 'sayac_degisim'];
    $out = "";
    foreach ($tables as $table) {
        $out .= "--- $table ---\n";
        $stmt = $db->query("DESCRIBE $table");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $out .= "{$row['Field']} - {$row['Type']}\n";
        }
        $out .= "\n";
    }
    file_put_contents('schema_audit.txt', $out);
    echo "Done. Saved to schema_audit.txt\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
