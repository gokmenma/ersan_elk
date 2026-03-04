<?php
require 'Autoloader.php';
$db = new \App\Core\Db();

$queries = [
    // personel_avanslari
    "ALTER TABLE personel_avanslari ADD COLUMN IF NOT EXISTS silen_kullanici INT NULL DEFAULT NULL",
    "ALTER TABLE personel_avanslari ADD COLUMN IF NOT EXISTS silinme_aciklama TEXT NULL",
    
    // personel_izinleri
    "ALTER TABLE personel_izinleri ADD COLUMN IF NOT EXISTS silen_kullanici INT NULL DEFAULT NULL",
    "ALTER TABLE personel_izinleri ADD COLUMN IF NOT EXISTS silinme_aciklama TEXT NULL",
    
    // personel_talepleri
    "ALTER TABLE personel_talepleri ADD COLUMN IF NOT EXISTS silen_kullanici INT NULL DEFAULT NULL",
    "ALTER TABLE personel_talepleri ADD COLUMN IF NOT EXISTS silinme_aciklama TEXT NULL"
];

foreach ($queries as $q) {
    try {
        echo "Executing: $q\n";
        $db->db->exec($q);
        echo "Success.\n";
    } catch (Exception $e) {
        // Some older MySQL versions don't support ADD COLUMN IF NOT EXISTS directly.
        // If it fails, it might be because it already exists or syntax.
        echo "Failed or already exists: " . $e->getMessage() . "\n";
    }
}
