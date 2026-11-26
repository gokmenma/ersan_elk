-- Personel talepleri (genel talep, avans, izin) için yapay zekâ ön analiz sonuçları.
-- Analiz sonucu yalnızca yöneticiye ön bilgi sunar; onay/ret kararını etkilemez.

CREATE TABLE IF NOT EXISTS talep_ai_on_analiz (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id INT NOT NULL,
    talep_tipi VARCHAR(10) NOT NULL COMMENT 'genel, avans veya izin',
    talep_id INT NOT NULL,
    personel_id INT NOT NULL,
    durum VARCHAR(15) NOT NULL DEFAULT 'tamamlandi' COMMENT 'tamamlandi veya hata',
    oneri VARCHAR(20) DEFAULT NULL COMMENT 'onaylanabilir, dikkat veya incelenmeli',
    aciliyet VARCHAR(10) DEFAULT NULL COMMENT 'dusuk, orta veya yuksek',
    kategori VARCHAR(80) DEFAULT NULL,
    ozet TEXT DEFAULT NULL,
    bulgular LONGTEXT DEFAULT NULL COMMENT 'JSON: olumlu, dikkat, eksik, sorular',
    model VARCHAR(60) DEFAULT NULL,
    hata_mesaji VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_talep_ai_on_analiz (talep_tipi, talep_id),
    KEY idx_talep_ai_personel (personel_id),
    KEY idx_talep_ai_firma (firma_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Yeni sayfa ve yeni yetki eklenmiyor: uç, talepler sayfasındaki mevcut yetkilerle
-- (avans_talepleri, izin_talepleri, ariza_talepleri) API tarafında tip bazında doğrulanır.
INSERT INTO permission_policies
    (scope, resource, action, http_method, permission_id, superadmin_only, authenticated_only)
VALUES
    ('api', 'talepler/api', 'get-ai-on-analiz', 'POST', NULL, 0, 1)
ON DUPLICATE KEY UPDATE
    authenticated_only = 1,
    is_active = 1;
