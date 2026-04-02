<?php
require_once 'Autoloader.php';
$db = new PDO('mysql:host=localhost;dbname=mbeyazil_ersanelektrik', 'root', '');
$res = $db->query("SHOW TABLES LIKE 'not_defterleri'")->fetchAll();
if(empty($res)) {
    echo "Table not_defterleri does not exist!\n";
    $sql = "CREATE TABLE IF NOT EXISTS not_defterleri (
        id INT AUTO_INCREMENT PRIMARY KEY,
        firma_id INT NOT NULL,
        baslik VARCHAR(255) NOT NULL,
        sira INT DEFAULT 0,
        renk VARCHAR(20) DEFAULT '#4285f4',
        icon VARCHAR(50) DEFAULT 'bx-book',
        olusturan_id INT NOT NULL,
        silinme_tarihi DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $db->exec($sql);
    echo "Table created.\n";
} else {
    echo "Table exists.\n";
    $cols = $db->query("DESCRIBE not_defterleri")->fetchAll(PDO::FETCH_ASSOC);
    print_r($cols);
}

$resNotlar = $db->query("SHOW TABLES LIKE 'notlar'")->fetchAll();
if(empty($resNotlar)) {
     echo "Table notlar does not exist!\n";
     $sqlNotlar = "CREATE TABLE IF NOT EXISTS notlar (
        id INT AUTO_INCREMENT PRIMARY KEY,
        defter_id INT NOT NULL,
        firma_id INT NOT NULL,
        baslik VARCHAR(255),
        icerik TEXT,
        renk VARCHAR(20),
        pinli TINYINT DEFAULT 0,
        sira INT DEFAULT 0,
        olusturan_id INT NOT NULL,
        silinme_tarihi DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (defter_id),
        INDEX (firma_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $db->exec($sqlNotlar);
    echo "Table notlar created.\n";
} else {
    echo "Table notlar exists.\n";
}
