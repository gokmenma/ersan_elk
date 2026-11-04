-- Bordro yayını: personel bazlı onay kilidi, tekli onay geri alma ve önizlemeli toplu revizyon.
-- Tekrar çalıştırılabilir. Sabit permission ID kullanılmaz; bağlantılar permission_key üzerinden kurulur.

ALTER TABLE bordro_yayin_dokum
    ADD COLUMN IF NOT EXISTS revizyon_tarihi DATETIME NULL AFTER onay_icerik_hash,
    ADD COLUMN IF NOT EXISTS revizyon_id BIGINT UNSIGNED NULL AFTER revizyon_tarihi;

ALTER TABLE bordro_yayin_dokum
    ADD INDEX IF NOT EXISTS ix_dokum_revizyon (revizyon_id);

CREATE TABLE IF NOT EXISTS bordro_yayin_revizyon (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id INT NOT NULL,
    donem_id INT NOT NULL,
    tur VARCHAR(20) NOT NULL,
    gerekce TEXT NULL,
    yapan_id INT NOT NULL,
    tarih DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    incelenen_sayisi INT NOT NULL DEFAULT 0,
    revize_sayisi INT NOT NULL DEFAULT 0,
    ic_guncellenen_sayisi INT NOT NULL DEFAULT 0,
    korunan_sayisi INT NOT NULL DEFAULT 0,
    onizleme_hash CHAR(64) NULL,
    deleted_at DATETIME NULL,
    is_active TINYINT NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY ix_revizyon_donem (firma_id, donem_id, tarih)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO permissions
    (name, description, auth_name, permission_key, group_name, permission_level, is_required, is_active, superadmin)
VALUES
    (
        'Bordro Onayı Geri Alma',
        'Personelin onayladığı (okuma beyanı verdiği) bordro dökümünü gerekçe ile revizyona alır; personel yeniden hesaplanabilir ve yeni sürümü yeniden onaylar',
        'bordro_onay_geri_al',
        'bordro_onay_geri_al',
        'Bordro',
        0,
        0,
        1,
        0
    ),
    (
        'Bordro Toplu Revizyon',
        'Onaylı bordroları önizlemeli olarak toplu yeniden hesaplar; yalnız resmî dökümü değişen personelin onayı kaldırılır',
        'bordro_toplu_revizyon',
        'bordro_toplu_revizyon',
        'Bordro',
        0,
        0,
        1,
        0
    )
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    auth_name = VALUES(auth_name),
    group_name = VALUES(group_name),
    is_active = 1;

SET @bordro_onay_geri_al_id = (SELECT id FROM permissions WHERE permission_key = 'bordro_onay_geri_al' LIMIT 1);
SET @bordro_toplu_revizyon_id = (SELECT id FROM permissions WHERE permission_key = 'bordro_toplu_revizyon' LIMIT 1);

INSERT INTO user_role_permissions (role_id, permission_id, created_by)
SELECT roles.id, yeni.permission_id, NULL
FROM user_roles roles
JOIN (
    SELECT @bordro_onay_geri_al_id AS permission_id
    UNION ALL SELECT @bordro_toplu_revizyon_id
) yeni
WHERE (roles.role_type = 'superadmin' OR roles.superadmin = 1)
  AND yeni.permission_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM user_role_permissions existing_grant
      WHERE existing_grant.role_id = roles.id
        AND existing_grant.permission_id = yeni.permission_id
  );

INSERT INTO permission_policies (
    scope, resource, action, http_method, access_mode,
    permission_id, superadmin_only, authenticated_only, is_active, created_at, updated_at
)
SELECT 'api', 'bordro/api', a.action, '*', a.access_mode,
       a.permission_id, 0, 0, 1, NOW(), NOW()
FROM (
    SELECT 'yayin-onay-geri-al' AS action, 'write' AS access_mode, @bordro_onay_geri_al_id AS permission_id
    UNION ALL SELECT 'yayin-revizyon-onizle', 'write', @bordro_toplu_revizyon_id
    UNION ALL SELECT 'yayin-revizyon-uygula', 'write', @bordro_toplu_revizyon_id
) a
WHERE a.permission_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM permission_policies pp
      WHERE pp.scope = 'api'
        AND pp.resource = 'bordro/api'
        AND pp.action = a.action
  );
