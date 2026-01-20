<?php
/**
 * Araç Takip Modülü - Veritabanı Tabloları Oluşturma
 * Bu dosyayı bir kez çalıştırarak tabloları oluşturabilirsiniz.
 */

require_once __DIR__ . '/Autoloader.php';

use App\Core\Db;

try {
    $db = new Db();
    $pdo = $db->db;

    echo "<h2>Araç Takip Modülü - Tablo Oluşturma</h2>";
    echo "<pre>";

    // 1. ARACLAR TABLOSU
    echo "1. araclar tablosu oluşturuluyor...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `araclar` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `firma_id` INT(11) NOT NULL,
            `plaka` VARCHAR(20) NOT NULL,
            `marka` VARCHAR(100) DEFAULT NULL,
            `model` VARCHAR(100) DEFAULT NULL,
            `model_yili` YEAR DEFAULT NULL,
            `arac_tipi` ENUM('binek', 'kamyonet', 'kamyon', 'minibus', 'otobus', 'motosiklet', 'diger') DEFAULT 'binek',
            `yakit_tipi` ENUM('benzin', 'dizel', 'lpg', 'elektrik', 'hibrit') DEFAULT 'dizel',
            `renk` VARCHAR(50) DEFAULT NULL,
            `sase_no` VARCHAR(50) DEFAULT NULL,
            `motor_no` VARCHAR(50) DEFAULT NULL,
            `ruhsat_sahibi` VARCHAR(150) DEFAULT NULL,
            `muayene_tarihi` DATE DEFAULT NULL,
            `sigorta_bitis_tarihi` DATE DEFAULT NULL,
            `kasko_bitis_tarihi` DATE DEFAULT NULL,
            `baslangic_km` INT(11) DEFAULT 0,
            `guncel_km` INT(11) DEFAULT 0,
            `aktif_mi` TINYINT(1) DEFAULT 1,
            `notlar` TEXT DEFAULT NULL,
            `resim_yolu` VARCHAR(255) DEFAULT NULL,
            `olusturma_tarihi` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `guncelleme_tarihi` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `silinme_tarihi` DATETIME DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `plaka_firma` (`plaka`, `firma_id`),
            KEY `idx_firma` (`firma_id`),
            KEY `idx_aktif` (`aktif_mi`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "   ✓ araclar tablosu oluşturuldu.\n";

    // 2. ARAÇ ZİMMETLERİ TABLOSU
    echo "2. arac_zimmetleri tablosu oluşturuluyor...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `arac_zimmetleri` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `firma_id` INT(11) NOT NULL,
            `arac_id` INT(11) NOT NULL,
            `personel_id` INT(11) NOT NULL,
            `zimmet_tarihi` DATE NOT NULL,
            `iade_tarihi` DATE DEFAULT NULL,
            `teslim_km` INT(11) DEFAULT NULL,
            `iade_km` INT(11) DEFAULT NULL,
            `durum` ENUM('aktif', 'iade_edildi', 'iptal') DEFAULT 'aktif',
            `notlar` TEXT DEFAULT NULL,
            `olusturan_kullanici_id` INT(11) DEFAULT NULL,
            `olusturma_tarihi` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `guncelleme_tarihi` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_arac` (`arac_id`),
            KEY `idx_personel` (`personel_id`),
            KEY `idx_firma` (`firma_id`),
            KEY `idx_durum` (`durum`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "   ✓ arac_zimmetleri tablosu oluşturuldu.\n";

    // 3. ARAÇ YAKIT KAYITLARI TABLOSU
    echo "3. arac_yakit_kayitlari tablosu oluşturuluyor...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `arac_yakit_kayitlari` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `firma_id` INT(11) NOT NULL,
            `arac_id` INT(11) NOT NULL,
            `tarih` DATE NOT NULL,
            `km` INT(11) NOT NULL COMMENT 'Yakıt alım anındaki kilometre',
            `onceki_km` INT(11) DEFAULT NULL COMMENT 'Önceki kayıttaki kilometre',
            `yakit_miktari` DECIMAL(10,2) NOT NULL COMMENT 'Litre',
            `birim_fiyat` DECIMAL(10,2) DEFAULT NULL COMMENT 'TL/Litre',
            `toplam_tutar` DECIMAL(10,2) NOT NULL COMMENT 'TL',
            `yakit_tipi` ENUM('benzin', 'dizel', 'lpg', 'elektrik') DEFAULT 'dizel',
            `tam_depo_mu` TINYINT(1) DEFAULT 0,
            `istasyon` VARCHAR(150) DEFAULT NULL,
            `fatura_no` VARCHAR(50) DEFAULT NULL,
            `notlar` TEXT DEFAULT NULL,
            `olusturan_kullanici_id` INT(11) DEFAULT NULL,
            `olusturma_tarihi` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `guncelleme_tarihi` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `silinme_tarihi` DATETIME DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_arac` (`arac_id`),
            KEY `idx_firma` (`firma_id`),
            KEY `idx_tarih` (`tarih`),
            KEY `idx_arac_tarih` (`arac_id`, `tarih`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "   ✓ arac_yakit_kayitlari tablosu oluşturuldu.\n";

    // 4. ARAÇ KM KAYITLARI TABLOSU
    echo "4. arac_km_kayitlari tablosu oluşturuluyor...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `arac_km_kayitlari` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `firma_id` INT(11) NOT NULL,
            `arac_id` INT(11) NOT NULL,
            `tarih` DATE NOT NULL,
            `baslangic_km` INT(11) NOT NULL,
            `bitis_km` INT(11) NOT NULL,
            `yapilan_km` INT(11) GENERATED ALWAYS AS (`bitis_km` - `baslangic_km`) STORED,
            `notlar` TEXT DEFAULT NULL,
            `olusturan_kullanici_id` INT(11) DEFAULT NULL,
            `olusturma_tarihi` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `guncelleme_tarihi` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `silinme_tarihi` DATETIME DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_arac` (`arac_id`),
            KEY `idx_firma` (`firma_id`),
            KEY `idx_tarih` (`tarih`),
            UNIQUE KEY `unique_arac_tarih` (`arac_id`, `tarih`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "   ✓ arac_km_kayitlari tablosu oluşturuldu.\n";

    // 5. ARAÇ BAKIM KAYITLARI TABLOSU
    echo "5. arac_bakim_kayitlari tablosu oluşturuluyor...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `arac_bakim_kayitlari` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `firma_id` INT(11) NOT NULL,
            `arac_id` INT(11) NOT NULL,
            `tarih` DATE NOT NULL,
            `km` INT(11) DEFAULT NULL,
            `bakim_tipi` ENUM('periyodik_bakim', 'yag_degisimi', 'lastik', 'fren', 'motor', 'elektrik', 'kaporta', 'diger') DEFAULT 'periyodik_bakim',
            `aciklama` TEXT NOT NULL,
            `servis_adi` VARCHAR(150) DEFAULT NULL,
            `tutar` DECIMAL(10,2) DEFAULT 0,
            `fatura_no` VARCHAR(50) DEFAULT NULL,
            `sonraki_bakim_km` INT(11) DEFAULT NULL,
            `sonraki_bakim_tarihi` DATE DEFAULT NULL,
            `notlar` TEXT DEFAULT NULL,
            `olusturan_kullanici_id` INT(11) DEFAULT NULL,
            `olusturma_tarihi` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `guncelleme_tarihi` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `silinme_tarihi` DATETIME DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_arac` (`arac_id`),
            KEY `idx_firma` (`firma_id`),
            KEY `idx_tarih` (`tarih`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "   ✓ arac_bakim_kayitlari tablosu oluşturuldu.\n";

    // 6. ARAÇ SİGORTA KAYITLARI TABLOSU
    echo "6. arac_sigorta_kayitlari tablosu oluşturuluyor...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `arac_sigorta_kayitlari` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `firma_id` INT(11) NOT NULL,
            `arac_id` INT(11) NOT NULL,
            `sigorta_tipi` ENUM('trafik', 'kasko', 'diger') DEFAULT 'trafik',
            `sigorta_sirketi` VARCHAR(150) DEFAULT NULL,
            `police_no` VARCHAR(100) DEFAULT NULL,
            `baslangic_tarihi` DATE NOT NULL,
            `bitis_tarihi` DATE NOT NULL,
            `prim_tutari` DECIMAL(10,2) DEFAULT 0,
            `acente` VARCHAR(150) DEFAULT NULL,
            `notlar` TEXT DEFAULT NULL,
            `olusturan_kullanici_id` INT(11) DEFAULT NULL,
            `olusturma_tarihi` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `guncelleme_tarihi` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `silinme_tarihi` DATETIME DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_arac` (`arac_id`),
            KEY `idx_firma` (`firma_id`),
            KEY `idx_bitis` (`bitis_tarihi`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "   ✓ arac_sigorta_kayitlari tablosu oluşturuldu.\n";

    echo "\n========================================\n";
    echo "✅ Tüm tablolar başarıyla oluşturuldu!\n";
    echo "========================================\n";
    echo "</pre>";

    echo "<div style='margin-top: 20px; padding: 15px; background: #d4edda; border-radius: 5px;'>";
    echo "<strong>✅ İşlem Tamamlandı!</strong><br>";
    echo "Araç Takip modülü için gerekli tüm tablolar veritabanında oluşturuldu.";
    echo "</div>";

} catch (Exception $e) {
    echo "<div style='margin-top: 20px; padding: 15px; background: #f8d7da; border-radius: 5px;'>";
    echo "<strong>❌ Hata:</strong> " . $e->getMessage();
    echo "</div>";
}
