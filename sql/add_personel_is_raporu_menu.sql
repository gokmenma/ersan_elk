-- =======================================================
-- Menü: Personel İş Raporu (İş Takip Altına Ekleme)
-- =======================================================

INSERT INTO `menus` (
    `menu_name`,
    `page_description`,
    `parent_id`,
    `group_name`,
    `group_order`,
    `menu_link`,
    `menu_icon`,
    `menu_order`,
    `is_active`,
    `is_menu`,
    `is_authorized`,
    `created_at`,
    `created_by`
)
SELECT 
    'Personel İş Raporu',
    'Personel bazlı tüm işlerin (Kesme/Açma, Endeks Okuma, Sayaç Sökme-Takma, Kaçak) grafik ve liste dökümü.',
    5,
    'İş Takip Yönetim',
    2,
    'puantaj/personel-is-raporu',
    'bx bx-user-check',
    COALESCE(MAX(menu_order), 0) + 1,
    1,
    1,
    1,
    NOW(),
    1
FROM `menus`
WHERE `parent_id` = 5
  AND NOT EXISTS (
      SELECT 1 FROM `menus` WHERE `menu_link` = 'puantaj/personel-is-raporu' AND `deleted_at` IS NULL
  );

-- Yetki kaydı ekleme
INSERT INTO `permissions` (`name`, `auth_name`, `description`, `group_name`, `permission_level`, `is_active`, `superadmin`, `is_required`)
SELECT 
    'puantaj/personel-is-raporu',
    'Personel İş Raporu',
    'Personel bazlı iş takip raporlarını görüntüleme yetkisi',
    'İş Takip',
    1,
    1,
    0,
    0
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM `permissions` WHERE `name` = 'puantaj/personel-is-raporu'
);

-- Superadmin rolüne yetki bağlama (role_id: 1)
INSERT INTO `user_role_permissions` (`role_id`, `permission_id`, `created_at`, `created_by`)
SELECT 1, p.id, NOW(), 1
FROM `permissions` p
WHERE p.name = 'puantaj/personel-is-raporu'
  AND NOT EXISTS (
      SELECT 1 FROM `user_role_permissions` urp WHERE urp.role_id = 1 AND urp.permission_id = p.id
  );
