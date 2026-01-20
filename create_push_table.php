<?php
require_once 'Autoloader.php';

use App\Core\Db;

try {
    $db = new Db();
    $pdo = $db->db; // Db sınıfında public $db özelliği var

    $sql = "CREATE TABLE IF NOT EXISTS `push_subscriptions` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `personel_id` int(11) NOT NULL,
        `endpoint` text NOT NULL,
        `public_key` text DEFAULT NULL,
        `auth_token` varchar(255) DEFAULT NULL,
        `content_encoding` varchar(50) DEFAULT 'aes128gcm',
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `personel_id` (`personel_id`),
        CONSTRAINT `fk_push_personel` FOREIGN KEY (`personel_id`) REFERENCES `personel` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

    $pdo->exec($sql);
    echo "push_subscriptions tablosu başarıyla oluşturuldu.";

} catch (Exception $e) {
    echo "Hata: " . $e->getMessage();
}
