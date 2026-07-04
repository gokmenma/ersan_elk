-- Consolidated SQL Script to restore all database updates after production import

-- 1. Mark permissions unique to Super Admin as superadmin = 1
UPDATE `permissions` SET `superadmin` = 1 WHERE `id` IN (17, 21, 18, 26, 74, 75, 82, 913, 920, 929);

-- 2. Update user_roles descriptions with user-friendly explanations
UPDATE `user_roles` SET `description` = 'Sistemdeki tüm modüllere, ayarlara, loglara ve yetki gruplarına sınırsız erişim ve yönetim hakkına sahip üst düzey sistem yöneticisi yetki grubudur.' WHERE `id` = 1;
UPDATE `user_roles` SET `description` = 'Firmanın genel işleyişini, personel durumlarını, finansal kayıtları ve onay süreçlerini yöneten, firma düzeyinde tam yetkili yönetici grubudur.' WHERE `id` = 2;
UPDATE `user_roles` SET `description` = 'İdari süreçler, mali kayıtlar, personel maaş ve avans onayları ile genel finansal takipleri yürütmekle görevli yetki grubudur.' WHERE `id` = 11;
UPDATE `user_roles` SET `description` = 'Şirket bünyesindeki araçların zimmet işlemleri, kilometre takipleri, bakım, yakıt giderleri ve kullanım onaylarını yöneten yetki grubudur.' WHERE `id` = 12;
UPDATE `user_roles` SET `description` = 'Atanan işlerin, görevlerin, projelerin ilerleyişini takip eden ve iş süreçlerinin yönetiminden sorumlu yetki grubudur.' WHERE `id` = 13;
UPDATE `user_roles` SET `description` = 'Personel özlük dosyaları, izin talepleri, giriş-çıkış saatleri ve genel insan kaynakları süreçlerini takip eden yetki grubudur.' WHERE `id` = 14;
UPDATE `user_roles` SET `description` = 'Depodaki demirbaşların zimmetlenmesi, malzeme giriş-çıkışları, sarf malzemeleri ve stok durumlarının yönetiminden sorumlu yetki grubudur.' WHERE `id` = 15;
UPDATE `user_roles` SET `description` = 'Yalnızca kendi izin, avans talepleri, kişisel bilgileri ve kendisine atanan iş/evrak gibi temel işlemleri görebilen ve talep oluşturabilen personel yetki grubudur.' WHERE `id` = 16;
UPDATE `user_roles` SET `description` = 'Nöbet çizelgelerinin planlanması, nöbetçi personellerin organize edilmesi ve nöbet süreçlerinin takibinden sorumlu yetki grubudur.' WHERE `id` = 17;

-- 3. Update user_roles role_type classifications
UPDATE `user_roles` SET `role_type` = 'superadmin' WHERE `id` = 1;
UPDATE `user_roles` SET `role_type` = 'admin' WHERE `id` = 2;
UPDATE `user_roles` SET `role_type` = 'user' WHERE `id` IN (11, 12, 13, 14, 15, 16, 17);

-- 4. Set users emelciner and volkaner as admin in users table
UPDATE `users` SET `role` = 'admin' WHERE `id` IN (62, 63);
