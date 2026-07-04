-- SQL Script to add yetki_gruplari_izleme permission

INSERT INTO `permissions` (`name`, `auth_name`, `description`, `group_name`, `permission_level`, `is_active`, `superadmin`, `is_required`)
VALUES ('Yetki Grupları İzleme', 'yetki_gruplari_izleme', 'Yetki gruplarını sadece görüntüleme yetkisi', 'Kullanıcı Yönetimi', 0, 1, 0, 0);
