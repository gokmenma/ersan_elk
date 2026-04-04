<?php

require_once 'bootstrap.php';

use App\Model\Model;

class Migration extends Model {
    public function up() {
        $sql = "
        CREATE TABLE IF NOT EXISTS `ayin_personeli_hediyeler` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `baslik` VARCHAR(255) NOT NULL,
            `aciklama` TEXT,
            `icon` VARCHAR(50) DEFAULT 'bx-gift',
            `renk` VARCHAR(50) DEFAULT '#f1b44c',
            `durum` TINYINT(1) DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS `ayin_personeli` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `personel_id` INT NOT NULL,
            `donem` VARCHAR(7) NOT NULL, -- Format: YYYY-MM
            `skor` FLOAT DEFAULT 0,
            `hediye_id` INT DEFAULT NULL,
            `aciklama` TEXT,
            `firma_id` INT NOT NULL,
            `ekleyen_user_id` INT NOT NULL,
            `tarih` DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `unique_donem_personel` (`donem`, `personel_id`, `firma_id`),
            FOREIGN KEY (`personel_id`) REFERENCES `personel`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`hediye_id`) REFERENCES `ayin_personeli_hediyeler`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        -- Örnek Hediyeler
        INSERT IGNORE INTO `ayin_personeli_hediyeler` (`baslik`, `aciklama`, `icon`, `renk`) VALUES
        ('Alışveriş Çeki', '500 TL Değerinde Alışveriş Çeki', 'bx-shopping-bag', '#34c38f'),
        ('Ek İzin', '1 Gün Ücretli İzin', 'bx-calendar-plus', '#556ee6'),
        ('Akşam Yemeği', 'Çift Kişilik Akşam Yemeği', 'bx-restaurant', '#f46a6a'),
        ('Teşekkür Belgesi', 'Dijital Başarı Belgesi', 'bx-award', '#f1b44c');
        ";

        try {
            $this->db->exec($sql);
            echo "Migration successful!\n";
        } catch (PDOException $e) {
            echo "Migration failed: " . $e->getMessage() . "\n";
        }
    }
}

$migration = new Migration();
$migration->up();
