-- Salt okunur "Kullanıcı Olarak Görüntüle" modu

ALTER TABLE permission_policies
    ADD COLUMN IF NOT EXISTS access_mode ENUM('read', 'write') NOT NULL DEFAULT 'write'
        COMMENT 'Gözlem modunda aksiyon sınıflandırması'
        AFTER http_method;

-- GET/HEAD politikaları ve adı açıkça okuma ifade eden API aksiyonları salt okunurdur.
UPDATE permission_policies
SET access_mode = 'read'
WHERE scope = 'page'
   OR http_method IN ('GET', 'HEAD')
   OR LOWER(action) REGEXP '^(get|list|search|summary|detail|fetch|load|check|validate|preview|onizle|tablo-yenile|runpermissionaudit)'
   OR LOWER(action) REGEXP '(^|[-_])list($|[-_])'
   OR LOWER(action) REGEXP '(ajax-list|datatable|dashboard|istatistik|ozet|summary|history|gecmis|foto|rapor|analiz|karsilastirma|comparison|sorgula|takip|listesi|listele|detay|getir|export|download|preview|unique-values)';

-- Adı okuma kalıbına benzese dahi veri üreten bilinen aksiyonlar yazma olarak kalır.
UPDATE permission_policies
SET access_mode = 'write'
WHERE LOWER(action) REGEXP '(kaydet|ekle|sil|guncelle|güncelle|onayla|reddet|olustur|oluştur|upload|yukle|yükle|yayinla|yayınla|reset|kapat|ac)$';

CREATE TABLE IF NOT EXISTS observer_session_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_user_id INT NOT NULL,
    target_user_id INT NOT NULL,
    firma_id INT NULL,
    event_type VARCHAR(40) NOT NULL,
    resource VARCHAR(191) NULL,
    action_name VARCHAR(191) NULL,
    ip_address VARCHAR(45) NULL,
    context_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_observer_actor_created (actor_user_id, created_at),
    KEY idx_observer_target_created (target_user_id, created_at),
    KEY idx_observer_event_created (event_type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
