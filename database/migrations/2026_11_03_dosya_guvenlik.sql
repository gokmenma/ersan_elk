-- Dosya Yönetimi: paylaşım bağlantısı için IP ve bağlantı bazlı başarısız deneme sınırı.
-- Ön koşul: 2026_11_03_dosya_yonetimi.sql

CREATE TABLE IF NOT EXISTS dosya_paylasim_denemeleri (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ip VARCHAR(45) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_dpd_ip (ip, created_at),
    KEY idx_dpd_token (token_hash, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
