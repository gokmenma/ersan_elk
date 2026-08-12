-- İptal sekmesinden ve Kayıtlar listesinden yeni tutanak iptali başlatma yetkisi.
-- İptali geri alma yetkisi mevcut kacak_iptal yetkisinde kalır.
INSERT INTO `permissions`
    (`name`, `auth_name`, `description`, `group_name`, `permission_level`, `is_active`, `is_required`)
SELECT 'Kaçak Yeni İptal Ekle', 'kacak_iptal_ekle',
       'İptaller sekmesinden veya kayıt listesinden yeni tutanak iptali oluşturma',
       'İş Takip', 0, 1, 0
WHERE NOT EXISTS (
    SELECT 1 FROM `permissions` WHERE `auth_name` = 'kacak_iptal_ekle'
);

-- Geçişte mevcut iptal yetkisine sahip roller işlev kaybetmez.
INSERT INTO `user_role_permissions` (`role_id`, `permission_id`)
SELECT DISTINCT urp.`role_id`, yeni.`id`
FROM `user_role_permissions` urp
JOIN `permissions` eski ON eski.`id` = urp.`permission_id` AND eski.`auth_name` = 'kacak_iptal'
JOIN `permissions` yeni ON yeni.`auth_name` = 'kacak_iptal_ekle'
WHERE NOT EXISTS (
    SELECT 1 FROM `user_role_permissions` kontrol
    WHERE kontrol.`role_id` = urp.`role_id` AND kontrol.`permission_id` = yeni.`id`
);
