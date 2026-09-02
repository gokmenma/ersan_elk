-- Kaçak Kontrol modülü altındaki 'Bildirim Personelleri' sekmesi ve yönetimi için yetki tanımı.
INSERT INTO `permissions`
    (`name`, `auth_name`, `description`, `group_name`, `permission_level`, `is_active`, `is_required`)
SELECT 'Kaçak Bildirim Personelleri', 'kacak_bildirim_personelleri',
       'Kaçak bildirim personellerini görüntüleme, ekleme, düzenleme, şifre belirleme ve silme',
       'İş Takip', 0, 1, 0
WHERE NOT EXISTS (
    SELECT 1 FROM `permissions` WHERE `auth_name` = 'kacak_bildirim_personelleri'
);

-- Kaçak düzenleme yetkisine sahip mevcut rollere otomatik olarak bu yetkiyi de bağla.
INSERT INTO `user_role_permissions` (`role_id`, `permission_id`)
SELECT DISTINCT urp.`role_id`, yeni.`id`
FROM `user_role_permissions` urp
JOIN `permissions` eski ON eski.`id` = urp.`permission_id` AND eski.`auth_name` = 'kacak_duzenle'
JOIN `permissions` yeni ON yeni.`auth_name` = 'kacak_bildirim_personelleri'
WHERE NOT EXISTS (
    SELECT 1 FROM `user_role_permissions` kontrol
    WHERE kontrol.`role_id` = urp.`role_id` AND kontrol.`permission_id` = yeni.`id`
);
