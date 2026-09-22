-- Kullanıcı Bazlı Menü Sıralama Tercihleri Tablosu
CREATE TABLE IF NOT EXISTS `user_menu_orders` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `order_data` LONGTEXT NOT NULL COMMENT 'JSON formatında sıralama verisi: {groups: [], menus: {}, submenus: {}}',
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
