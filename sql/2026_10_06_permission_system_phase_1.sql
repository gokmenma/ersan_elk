-- Yetki Sistemi Dönüşümü / Aşama 1
-- Amaç: Mevcut sistemi bozmadan kanonik yetki anahtarı ve açık menü-yetki ilişkisi eklemek.
-- Bu script veri silmez. Eski auth_name ve ID eşleştirmeleri geçiş süresince korunur.

SET @schema_name = DATABASE();

-- 1. permissions.permission_key
SET @has_permission_key = (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = @schema_name
      AND table_name = 'permissions'
      AND column_name = 'permission_key'
);
SET @sql = IF(
    @has_permission_key = 0,
    'ALTER TABLE permissions ADD COLUMN permission_key VARCHAR(191) NULL AFTER auth_name',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Mevcut auth_name değerleri mümkün olduğunda korunur.
-- Aynı auth_name birden fazla kayıtta varsa ID son eki ile benzersizleştirilir.
UPDATE permissions AS p
LEFT JOIN (
    SELECT auth_name, COUNT(*) AS duplicate_count
    FROM permissions
    WHERE auth_name IS NOT NULL AND TRIM(auth_name) <> ''
    GROUP BY auth_name
) AS duplicate_auth ON duplicate_auth.auth_name = p.auth_name
SET p.permission_key = CASE
    WHEN p.permission_key IS NOT NULL AND TRIM(p.permission_key) <> '' THEN p.permission_key
    WHEN p.auth_name IS NULL OR TRIM(p.auth_name) = '' THEN CONCAT('legacy.permission.', p.id)
    WHEN duplicate_auth.duplicate_count > 1 THEN CONCAT(TRIM(p.auth_name), '.', p.id)
    ELSE TRIM(p.auth_name)
END;

SET @has_permission_key_unique = (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = @schema_name
      AND table_name = 'permissions'
      AND index_name = 'uq_permissions_permission_key'
);
SET @sql = IF(
    @has_permission_key_unique = 0,
    'ALTER TABLE permissions ADD UNIQUE KEY uq_permissions_permission_key (permission_key)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. menus.permission_id
SET @has_menu_permission_id = (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = @schema_name
      AND table_name = 'menus'
      AND column_name = 'permission_id'
);
SET @sql = IF(
    @has_menu_permission_id = 0,
    'ALTER TABLE menus ADD COLUMN permission_id INT NULL AFTER menu_link',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Öncelik: rota/auth_name, görünen ad, eski ID eşitliği.
UPDATE menus AS m
LEFT JOIN permissions AS current_permission ON current_permission.id = m.permission_id
SET m.permission_id = NULL
WHERE m.permission_id IS NOT NULL
  AND current_permission.id IS NULL;

UPDATE menus AS m
SET m.permission_id = COALESCE(
    (SELECT MIN(p1.id) FROM permissions AS p1
     WHERE p1.is_active = 1 AND p1.auth_name = m.menu_link),
    (SELECT MIN(p2.id) FROM permissions AS p2
     WHERE p2.is_active = 1 AND p2.name = m.menu_name),
    (SELECT MIN(p3.id) FROM permissions AS p3
     WHERE p3.is_active = 1 AND p3.id = m.id)
)
WHERE m.permission_id IS NULL;

-- Tarihsel olarak rota adı ile yetki kodu farklı kalan bilinen menüler.
UPDATE menus AS m
INNER JOIN permissions AS p
    ON p.is_active = 1
   AND p.auth_name = CASE m.menu_link
       WHEN 'puantaj/upload' THEN 'puantaj/veri-yukleme'
       WHEN 'kasa/list' THEN 'gelir_gider_takibi'
       WHEN 'gelir-gider/online-hesap-hareketleri' THEN 'gelir_gider_takibi'
   END
SET m.permission_id = p.id
WHERE m.permission_id IS NULL
  AND m.menu_link IN ('puantaj/upload', 'kasa/list', 'gelir-gider/online-hesap-hareketleri');

-- Rota taşımayan üst menüler yetki değildir; görünürlükleri erişilebilir çocuklardan gelir.
UPDATE menus
SET permission_id = NULL
WHERE parent_id = 0
  AND (menu_link IS NULL OR TRIM(menu_link) = '');

SET @has_menu_permission_index = (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = @schema_name
      AND table_name = 'menus'
      AND index_name = 'idx_menus_permission_id'
);
SET @sql = IF(
    @has_menu_permission_index = 0,
    'ALTER TABLE menus ADD KEY idx_menus_permission_id (permission_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_menu_permission_fk = (
    SELECT COUNT(*) FROM information_schema.table_constraints
    WHERE constraint_schema = @schema_name
      AND table_name = 'menus'
      AND constraint_name = 'fk_menus_permission'
      AND constraint_type = 'FOREIGN KEY'
);
SET @sql = IF(
    @has_menu_permission_fk = 0,
    'ALTER TABLE menus ADD CONSTRAINT fk_menus_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON UPDATE CASCADE ON DELETE RESTRICT',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3. Geçiş sonrası kontrol raporları
SELECT id, menu_name, menu_link, is_authorized
FROM menus
WHERE is_active = 1
  AND is_authorized = 1
  AND permission_id IS NULL
ORDER BY group_order, menu_order;

SELECT permission_key, COUNT(*) AS record_count
FROM permissions
GROUP BY permission_key
HAVING COUNT(*) > 1;
