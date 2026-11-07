-- İK İşe Alım: adaya karar bildirimi (e-posta / WhatsApp) kayıt tablosu, yetkisi ve API politikaları.
-- Sabit ID kullanılmaz; bağlantılar permission_key üzerinden çözülür. Tekrar çalıştırılabilir.

CREATE TABLE IF NOT EXISTS ik_basvuru_bildirimleri (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id INT NOT NULL,
    basvuru_id INT UNSIGNED NOT NULL,
    kanal ENUM('eposta','whatsapp') NOT NULL,
    sablon VARCHAR(30) NULL,
    alici VARCHAR(190) NULL,
    konu VARCHAR(190) NULL,
    mesaj TEXT NOT NULL,
    durum ENUM('gonderildi','hata','whatsapp_acildi') NOT NULL,
    kullanici_id INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ik_bildirim_basvuru (basvuru_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

START TRANSACTION;

INSERT INTO permissions
    (name, description, auth_name, permission_key, group_name, permission_level, is_required, is_active, superadmin)
VALUES
    ('İşe Alım Adaya Bildirim', 'İK kararını adaya e-posta veya WhatsApp ile bildirme yetkisi', 'ik-ise-alim/aday-bildirim', 'ik_basvuru_aday_bildirim', 'İşe Alım', 1, 0, 1, 0)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    auth_name = VALUES(auth_name),
    group_name = VALUES(group_name),
    permission_level = VALUES(permission_level),
    is_active = 1,
    superadmin = 0;

INSERT INTO user_role_permissions (role_id, permission_id, created_by)
SELECT roles.id, perm.id, NULL
FROM user_roles roles
JOIN permissions perm ON perm.permission_key = 'ik_basvuru_aday_bildirim'
WHERE (roles.role_type = 'superadmin' OR roles.superadmin = 1)
  AND NOT EXISTS (
      SELECT 1 FROM user_role_permissions existing_grant
      WHERE existing_grant.role_id = roles.id AND existing_grant.permission_id = perm.id
  );

SET @ik_bildirim_perm = (SELECT id FROM permissions WHERE permission_key = 'ik_basvuru_aday_bildirim' LIMIT 1);

INSERT INTO permission_policies
    (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active)
SELECT 'api', 'ik-ise-alim/api', d.action, '*', d.access_mode, @ik_bildirim_perm, 0, 0, 1
FROM (
    SELECT 'aday-bildirim-hazirla' AS action, 'read' AS access_mode
    UNION ALL SELECT 'aday-bildirim-gonder', 'write'
) d
WHERE @ik_bildirim_perm IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM permission_policies p
      WHERE p.scope = 'api' AND p.resource = 'ik-ise-alim/api' AND p.action = d.action
        AND p.http_method = '*' AND p.permission_id = @ik_bildirim_perm
  );

COMMIT;
