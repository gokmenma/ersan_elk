<?php
$host = 'localhost';
$db   = 'mbeyazil_ersanelektrik';
$user = 'root';
$pass = '';
$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);

    // 1. Create formlar table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `formlar` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `firma_id` int(11) NOT NULL DEFAULT 1,
        `baslik` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
        `dosya_adi` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
        `dosya_yolu` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
        `ekleyen_id` int(11) NOT NULL,
        `eklenme_tarihi` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `guncellenme_tarihi` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "formlar tablosu oluşturuldu.\n";

    // 2. Add to menus
    $stmt = $pdo->prepare("SELECT id FROM menus WHERE menu_link = 'formlar/list'");
    $stmt->execute();
    $exists = $stmt->fetchColumn();

    if (!$exists) {
        $stmt = $pdo->prepare("INSERT INTO `menus` (`menu_name`, `menu_link`, `menu_icon`, `parent_id`, `group_name`, `is_active`, `group_order`, `menu_order`) 
                              VALUES ('Formlar ve Tutanaklar', 'formlar/list', 'bx bx-file', 0, 'İnsan Kaynakları', 1, 2, 5)");
        $stmt->execute();
        $newMenuId = $pdo->lastInsertId();
        echo "Menü eklendi. (ID: $newMenuId)\n";

        $stmt = $pdo->prepare("SELECT role_id FROM roles WHERE role_name LIKE '%Admin%' OR role_id = 1 LIMIT 1");
        $stmt->execute();
        $adminRole = $stmt->fetchColumn();
        
        if ($adminRole) {
            $stmt = $pdo->prepare("INSERT INTO `user_role_permissions` (`role_id`, `permission_id`) VALUES (?, ?)");
            $stmt->execute([$adminRole, $newMenuId]);
            echo "Menü izni Role ID $adminRole için eklendi.\n";
        }

    } else {
        echo "Menü zaten mevcut.\n";
    }

} catch (PDOException $e) {
    echo "Hata: " . $e->getMessage();
}
