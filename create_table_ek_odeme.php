<?php
try {
    $db = new PDO('mysql:host=localhost;dbname=ersan_elk', 'root', '');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $sql = "CREATE TABLE IF NOT EXISTS personel_ek_odemeler (
        id INT AUTO_INCREMENT PRIMARY KEY,
        personel_id INT NOT NULL,
        donem VARCHAR(10) NOT NULL,
        tur VARCHAR(50) NOT NULL,
        tutar DECIMAL(10,2) NOT NULL,
        aciklama TEXT,
        silinme_tarihi DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(personel_id),
        INDEX(donem)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;";
    
    $db->exec($sql);
    echo "Table 'personel_ek_odemeler' created successfully.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>