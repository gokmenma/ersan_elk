<?php
try {
    $db = new PDO('mysql:host=localhost;dbname=ersantrc_personel;charset=utf8', 'root', '');
    $db->beginTransaction();

    // 1. Insert into menus
    $stmt = $db->prepare("INSERT INTO menus (menu_name, parent_id, group_name, group_order, menu_link, menu_icon, menu_order, is_active, is_menu, is_authorized)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute(['KM Onayları', 56, 'Yönetim', 5, 'arac-takip/km-onaylari', 'check-shield', 2, 1, 1, 1]);
    $newMenuId = $db->lastInsertId();

    // 2. Give roles access (1, 2, 16, 12)
    $roles = [1, 2, 16, 12];
    $stmtPerm = $db->prepare("INSERT INTO user_role_permissions (role_id, permission_id) VALUES (?, ?)");
    foreach ($roles as $roleId) {
        $stmtPerm->execute([$roleId, $newMenuId]);
    }

    $db->commit();
    echo "Menu item created with ID: " . $newMenuId;
} catch (Exception $e) {
    if (isset($db)) $db->rollBack();
    echo 'Error: ' . $e->getMessage();
}
