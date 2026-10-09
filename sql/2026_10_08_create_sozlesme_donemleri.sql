-- ============================================================================
-- Sözleşme / Çalışma Dönemleri Tablosu, Menü ve Yetkilendirmeleri
-- Tarih: 2026-10-08
-- Açıklama: Sözleşme ve çalışma dönemlerinin tanımlanması, her firma için 
--           tekil aktif dönem kuralı ve menü/yetki tanımları.
-- ============================================================================

-- 1. Tablo Oluşturma: sozlesme_donemleri
CREATE TABLE IF NOT EXISTS `sozlesme_donemleri` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `firma_id` int(11) NOT NULL,
  `donem_adi` varchar(150) NOT NULL,
  `baslangic_tarihi` date NOT NULL,
  `bitis_tarihi` date NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `aciklama` text DEFAULT NULL,
  `olusturan_id` int(11) DEFAULT NULL,
  `olusturma_tarihi` datetime DEFAULT CURRENT_TIMESTAMP,
  `guncelleme_tarihi` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `silinme_tarihi` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_donem_firma_active` (`firma_id`, `silinme_tarihi`, `is_active`),
  KEY `idx_donem_tarihler` (`baslangic_tarihi`, `bitis_tarihi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Mevcut Firmalar İçin Varsayılan Başlangıç Dönemi Ekleme (Eğer Dönem Yoksa)
INSERT INTO `sozlesme_donemleri` (`firma_id`, `donem_adi`, `baslangic_tarihi`, `bitis_tarihi`, `is_active`, `aciklama`, `olusturan_id`)
SELECT f.id, '1. Dönem Sözleşmesi (2024-2026)', '2024-01-01', '2026-12-31', 1, 'Otomatik oluşturulan başlangıç sözleşme dönemi', 1
FROM firmalar f
WHERE NOT EXISTS (
    SELECT 1 FROM sozlesme_donemleri sd WHERE sd.firma_id = f.id AND sd.silinme_tarihi IS NULL
);

-- 3. İzinler (permissions)
INSERT INTO permissions (name, description, auth_name, group_name, permission_level, is_required, is_active)
SELECT 'Dönem Tanımları', 'Sözleşme ve çalışma dönemlerini tanımlama ve yönetme', 'tanimlamalar/donem-tanimlari', 'Tanımlamalar', 1, 0, 1
FROM dual WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE auth_name = 'tanimlamalar/donem-tanimlari');

-- 4. Menü Tanımı (menus)
SET @tanimlamalar_parent_id = (SELECT id FROM menus WHERE (menu_name = 'Tanımlamalar' OR menu_link = 'tanimlamalar') AND parent_id = 0 LIMIT 1);
SET @max_menu_order = (SELECT IFNULL(MAX(menu_order), 0) + 1 FROM menus WHERE parent_id = @tanimlamalar_parent_id);

INSERT INTO menus (menu_name, parent_id, group_name, group_order, menu_link, menu_icon, menu_order, is_active, is_menu, is_authorized, created_by)
SELECT 'Dönem Tanımları', @tanimlamalar_parent_id, 'Yönetim', 9, 'tanimlamalar/donem-tanimlari', 'calendar', @max_menu_order, 1, 1, 1, 0
FROM dual WHERE NOT EXISTS (SELECT 1 FROM menus WHERE menu_link = 'tanimlamalar/donem-tanimlari');

-- 5. Tanımlamalara Yetkisi Olan Rollere Yetki Atama
SET @donem_perm_id = (SELECT id FROM permissions WHERE auth_name = 'tanimlamalar/donem-tanimlari' LIMIT 1);

INSERT INTO user_role_permissions (role_id, permission_id, created_by)
SELECT DISTINCT urp.role_id, @donem_perm_id, 1
FROM user_role_permissions urp 
JOIN permissions p ON urp.permission_id = p.id 
WHERE p.auth_name LIKE 'tanimlamalar/%'
AND NOT EXISTS (
    SELECT 1 FROM user_role_permissions urp2 WHERE urp2.role_id = urp.role_id AND urp2.permission_id = @donem_perm_id
);

-- 6. Sayfa ve API Yetki İlkeleri (permission_policies)
INSERT INTO permission_policies (scope, resource, action, http_method, access_mode, permission_id, authenticated_only, is_active)
VALUES 
  ('page', 'tanimlamalar/donem-tanimlari', '', 'GET', 'read', @donem_perm_id, 0, 1),
  ('api', 'tanimlamalar/api', 'donem-liste', '*', 'read', @donem_perm_id, 0, 1),
  ('api', 'tanimlamalar/api', 'donem-getir', '*', 'read', @donem_perm_id, 0, 1),
  ('api', 'tanimlamalar/api', 'donem-kaydet', '*', 'write', @donem_perm_id, 0, 1),
  ('api', 'tanimlamalar/api', 'donem-aktif-yap', '*', 'write', @donem_perm_id, 0, 1),
  ('api', 'tanimlamalar/api', 'donem-sil', '*', 'write', @donem_perm_id, 0, 1),
  ('api', 'tanimlamalar/api', 'donem-ozet', '*', 'read', @donem_perm_id, 0, 1),
  ('api', 'tanimlamalar/api', 'topbar-donem-degistir', '*', 'write', NULL, 1, 1)
ON DUPLICATE KEY UPDATE is_active = 1;
