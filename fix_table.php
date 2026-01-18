<?php
require 'Autoloader.php';

use App\Core\Db;

$db = (new Db())->db;

// aktif kolonu ekle
try {
    $db->exec('ALTER TABLE bordro_genel_ayarlar ADD COLUMN aktif TINYINT(1) DEFAULT 1 AFTER aciklama');
    echo "aktif kolonu eklendi.\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "aktif kolonu zaten var.\n";
    } else {
        echo "Hata: " . $e->getMessage() . "\n";
    }
}

// Mevcut NULL tarihleri düzelt
$db->exec("UPDATE bordro_genel_ayarlar SET gecerlilik_baslangic = '2026-01-01' WHERE gecerlilik_baslangic IS NULL");
echo "NULL tarihler düzeltildi.\n";
