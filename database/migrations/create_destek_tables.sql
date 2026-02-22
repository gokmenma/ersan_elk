-- =====================================================
-- Canlı Destek Sistemi - Veritabanı Tabloları
-- =====================================================

-- Destek Konuşmaları
CREATE TABLE IF NOT EXISTS destek_konusmalar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    personel_id INT NOT NULL,
    konu VARCHAR(255) DEFAULT NULL,
    durum ENUM('acik', 'beklemede', 'cozuldu', 'kapali') DEFAULT 'acik',
    oncelik ENUM('dusuk', 'normal', 'yuksek', 'acil') DEFAULT 'normal',
    atanan_user_id INT DEFAULT NULL COMMENT 'Hangi yönetici ilgileniyor',
    son_mesaj_zamani DATETIME DEFAULT NULL,
    son_mesaj_onizleme VARCHAR(255) DEFAULT NULL,
    okunmamis_personel INT DEFAULT 0 COMMENT 'Personelin okumadigi mesaj sayisi',
    okunmamis_yonetici INT DEFAULT 0 COMMENT 'Yoneticinin okumadigi mesaj sayisi',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_personel_id (personel_id),
    INDEX idx_durum (durum),
    INDEX idx_atanan_user (atanan_user_id),
    INDEX idx_son_mesaj (son_mesaj_zamani)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Destek Mesajları
CREATE TABLE IF NOT EXISTS destek_mesajlar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    konusma_id INT NOT NULL,
    gonderen_tip ENUM('personel', 'yonetici', 'sistem') NOT NULL,
    gonderen_id INT NOT NULL,
    mesaj TEXT NOT NULL,
    dosya_url VARCHAR(500) DEFAULT NULL COMMENT 'Resim/dosya eki',
    dosya_tip VARCHAR(50) DEFAULT NULL COMMENT 'image/jpeg, image/png vs.',
    okundu TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_konusma_id (konusma_id),
    INDEX idx_gonderen (gonderen_tip, gonderen_id),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (konusma_id) REFERENCES destek_konusmalar(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
