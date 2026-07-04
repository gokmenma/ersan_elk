-- SQL Script to update user_roles descriptions with user-friendly explanations

UPDATE `user_roles` 
SET `description` = 'Sistemdeki tüm modüllere, ayarlara, loglara ve yetki gruplarına sınırsız erişim ve yönetim hakkına sahip üst düzey sistem yöneticisi yetki grubudur.' 
WHERE `id` = 1;

UPDATE `user_roles` 
SET `description` = 'Firmanın genel işleyişini, personel durumlarını, finansal kayıtları ve onay süreçlerini yöneten, firma düzeyinde tam yetkili yönetici grubudur.' 
WHERE `id` = 2;

UPDATE `user_roles` 
SET `description` = 'İdari süreçler, mali kayıtlar, personel maaş ve avans onayları ile genel finansal takipleri yürütmekle görevli yetki grubudur.' 
WHERE `id` = 11;

UPDATE `user_roles` 
SET `description` = 'Şirket bünyesindeki araçların zimmet işlemleri, kilometre takipleri, bakım, yakıt giderleri ve kullanım onaylarını yöneten yetki grubudur.' 
WHERE `id` = 12;

UPDATE `user_roles` 
SET `description` = 'Atanan işlerin, görevlerin, projelerin ilerleyişini takip eden ve iş süreçlerinin yönetiminden sorumlu yetki grubudur.' 
WHERE `id` = 13;

UPDATE `user_roles` 
SET `description` = 'Personel özlük dosyaları, izin talepleri, giriş-çıkış saatleri ve genel insan kaynakları süreçlerini takip eden yetki grubudur.' 
WHERE `id` = 14;

UPDATE `user_roles` 
SET `description` = 'Depodaki demirbaşların zimmetlenmesi, malzeme giriş-çıkışları, sarf malzemeleri ve stok durumlarının yönetiminden sorumlu yetki grubudur.' 
WHERE `id` = 15;

UPDATE `user_roles` 
SET `description` = 'Yalnızca kendi izin, avans talepleri, kişisel bilgileri ve kendisine atanan iş/evrak gibi temel işlemleri görebilen ve talep oluşturabilen personel yetki grubudur.' 
WHERE `id` = 16;

UPDATE `user_roles` 
SET `description` = 'Nöbet çizelgelerinin planlanması, nöbetçi personellerin organize edilmesi ve nöbet süreçlerinin takibinden sorumlu yetki grubudur.' 
WHERE `id` = 17;
