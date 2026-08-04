-- Profil ayarlarının önceki sürümlerinden eksik kalabilen kolon.
-- Script tekrar çalıştırıldığında mevcut kolona dokunmaz.
SET @database_name = DATABASE();
SET @users_table = 'users';
SET @favorites_column = 'show_favorites_bar';

SET @add_favorites_column = (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = @database_name
              AND TABLE_NAME = @users_table
              AND COLUMN_NAME = @favorites_column
        ),
        'SELECT 1',
        'ALTER TABLE `users` ADD COLUMN `show_favorites_bar` TINYINT(1) NOT NULL DEFAULT 1'
    )
);

PREPARE add_favorites_column_statement FROM @add_favorites_column;
EXECUTE add_favorites_column_statement;
DEALLOCATE PREPARE add_favorites_column_statement;

-- Kullanıcı bazlı uygulama içi ve push bildirim tercihleri
CREATE TABLE IF NOT EXISTS `user_notification_preferences` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `notification_type` VARCHAR(64) NOT NULL,
    `is_enabled` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_user_notification_preference` (`user_id`, `notification_type`),
    KEY `idx_notification_type_enabled` (`notification_type`, `is_enabled`),
    CONSTRAINT `fk_notification_preference_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;
