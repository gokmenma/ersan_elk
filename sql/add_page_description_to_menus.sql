-- Topbar sayfa açıklaması için menus tablosuna page_description sütunu ekleme scripti

ALTER TABLE `menus` ADD COLUMN IF NOT EXISTS `page_description` VARCHAR(255) NULL AFTER `menu_name`;

-- Örnek menü açıklamalarının güncellenmesi
UPDATE `menus` SET `page_description` = 'Genel sistem özeti ve hızlı erişim istatistikleri' WHERE `menu_link` = 'home' OR `menu_name` = 'Ana Sayfa';
UPDATE `menus` SET `page_description` = 'Tüm personel listesi, yetki ve kart yönetimi' WHERE `menu_link` = 'personel/list';
UPDATE `menus` SET `page_description` = 'Personel puantaj ve izin takip ekranı' WHERE `menu_link` = 'personel/puantaj';
UPDATE `menus` SET `page_description` = 'Personel iş performans ve çalışma raporları' WHERE `menu_link` = 'personel/performans-raporu';
UPDATE `menus` SET `page_description` = 'Toplu rapor alma ve dışa aktarma işlemleri' WHERE `menu_link` = 'raporlar/list';
UPDATE `menus` SET `page_description` = 'Saha ihbar bildirimleri ve takip yönetimi' WHERE `menu_link` = 'ihbar/list';
UPDATE `menus` SET `page_description` = 'Şirket ve personel araç takip sistemleri' WHERE `menu_link` = 'arac-takip/list';
UPDATE `menus` SET `page_description` = 'Finansal gelir ve gider hesap takibi' WHERE `menu_link` = 'gelir-gider/list';
UPDATE `menus` SET `page_description` = 'Firma hakediş ve sözleşme yönetimi' WHERE `menu_link` = 'hakedisler/index';
UPDATE `menus` SET `page_description` = 'Personel nöbet çizelgesi ve onay işlemleri' WHERE `menu_link` = 'nobet/list';
UPDATE `menus` SET `page_description` = 'Aylık maaş bordrosu ve hesaplamalar' WHERE `menu_link` = 'bordro/list';
UPDATE `menus` SET `page_description` = 'Saha kaçak tespit ve müdahale takibi' WHERE `menu_link` = 'kacak/list';
UPDATE `menus` SET `page_description` = 'Kullanıcı ve rol yetkilendirme ayarları' WHERE `menu_link` = 'kullanici-gruplari/list';
UPDATE `menus` SET `page_description` = 'Sistem destek bildirimleri ve talepler' WHERE `menu_link` = 'yardim/list';
