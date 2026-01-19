<?php
require_once 'bootstrap.php';

try {
    $db = getDbConnection();

    $sql = "CREATE TABLE IF NOT EXISTS `push_subscriptions` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `personel_id` int(11) NOT NULL,
        `endpoint` text NOT NULL,
        `public_key` text DEFAULT NULL,
        `auth_token` text DEFAULT NULL,
        `content_encoding` varchar(50) DEFAULT 'aes128gcm',
        `created_at` timestamp DEFAULT current_timestamp(),
        `updated_at` timestamp DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `personel_id` (`personel_id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    $db->exec($sql);
    echo "push_subscriptions tablosu başarıyla oluşturuldu.";
} catch (Exception $e) {
    echo "Hata: " . $e->getMessage();
}
