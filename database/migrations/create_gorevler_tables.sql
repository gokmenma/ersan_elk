-- =====================================================
-- Görevler Modülü - Veritabanı Tabloları
-- Google Tasks tarzı görev yönetim sistemi
-- =====================================================

-- Görev Listeleri (Sol panel)
CREATE TABLE IF NOT EXISTS gorev_listeleri (
    id INT AUTO_INCREMENT PRIMARY KEY,
    firma_id INT NOT NULL,
    baslik VARCHAR(255) NOT NULL,
    sira INT DEFAULT 0,
    renk VARCHAR(7) DEFAULT NULL,
    olusturan_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_firma_id (firma_id),
    INDEX idx_sira (sira),
    INDEX idx_olusturan (olusturan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Görevler
CREATE TABLE IF NOT EXISTS gorevler (
    id INT AUTO_INCREMENT PRIMARY KEY,
    liste_id INT NOT NULL,
    firma_id INT NOT NULL,
    baslik VARCHAR(500) NOT NULL,
    aciklama TEXT DEFAULT NULL,
    tarih DATE DEFAULT NULL,
    saat TIME DEFAULT NULL,
    tamamlandi TINYINT(1) DEFAULT 0,
    tamamlanma_tarihi DATETIME DEFAULT NULL,
    sira INT DEFAULT 0,
    yildizli TINYINT(1) DEFAULT 0,
    yineleme_sikligi INT DEFAULT NULL,
    yineleme_birimi ENUM('gun','hafta','ay','yil') DEFAULT NULL,
    yineleme_baslangic DATE DEFAULT NULL,
    yineleme_bitis_tipi ENUM('asla','tarih','adet') DEFAULT NULL,
    yineleme_bitis_tarihi DATE DEFAULT NULL,
    yineleme_bitis_adet INT DEFAULT NULL,
    olusturan_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_liste_id (liste_id),
    INDEX idx_firma_id (firma_id),
    INDEX idx_tamamlandi (tamamlandi),
    INDEX idx_sira (sira),
    INDEX idx_tarih (tarih),
    FOREIGN KEY (liste_id) REFERENCES gorev_listeleri(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
