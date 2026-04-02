-- =====================================================
-- Notlar Modülü - Veritabanı Tabloları
-- =====================================================

-- Not Defterleri
CREATE TABLE IF NOT EXISTS not_defterleri (
    id INT AUTO_INCREMENT PRIMARY KEY,
    firma_id INT NOT NULL,
    baslik VARCHAR(255) NOT NULL,
    sira INT DEFAULT 0,
    renk VARCHAR(7) DEFAULT '#4285f4',
    icon VARCHAR(50) DEFAULT 'bx-book',
    olusturan_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    silinme_tarihi DATETIME DEFAULT NULL,
    INDEX idx_firma_id (firma_id),
    INDEX idx_sira (sira),
    INDEX idx_olusturan (olusturan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notlar
CREATE TABLE IF NOT EXISTS notlar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    defter_id INT NOT NULL,
    firma_id INT NOT NULL,
    baslik VARCHAR(500) DEFAULT NULL,
    icerik LONGTEXT DEFAULT NULL,
    renk VARCHAR(7) DEFAULT NULL,
    pinli TINYINT(1) DEFAULT 0,
    sira INT DEFAULT 0,
    olusturan_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    silinme_tarihi DATETIME DEFAULT NULL,
    INDEX idx_defter_id (defter_id),
    INDEX idx_firma_id (firma_id),
    INDEX idx_pinli (pinli),
    INDEX idx_sira (sira),
    INDEX idx_olusturan (olusturan_id),
    FOREIGN KEY (defter_id) REFERENCES not_defterleri(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
