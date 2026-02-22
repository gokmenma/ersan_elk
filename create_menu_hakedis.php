<?php
require_once __DIR__ . '/bootstrap.php';

try {
    $db = new PDO("mysql:host=" . $_ENV['DB_HOST'] . ";dbname=" . $_ENV['DB_NAME'] . ";charset=utf8", $_ENV['DB_USER'], $_ENV['DB_PASS']);

    // Check if menus are already added
    $stmt = $db->query("SELECT id FROM menus WHERE menu_link = 'hakedisler/index'");
    if ($stmt->fetch()) {
        echo "Menu already exists.\n";
        exit;
    }

    // Since role_permissions failed, let's just insert the menu items.
    // Usually superadmin sees all, or permissions are set in UI.

    // Insert Parent Menu
    $sql = "INSERT INTO menus (menu_name, menu_link, parent_id, group_name, menu_icon, is_menu, menu_order, group_order) 
            VALUES ('Hakediş İşlemleri', '#', 0, 'Finans', 'file-text', 1, 10, 5)";
    $db->exec($sql);
    $parentId = $db->lastInsertId();

    // Insert Sub Menu
    $sql = "INSERT INTO menus (menu_name, menu_link, parent_id, menu_icon, is_menu, menu_order) 
            VALUES ('Sözleşmeler', 'hakedisler/index', $parentId, '', 1, 1)";
    $db->exec($sql);
    $subMenuId = $db->lastInsertId();

    // Now give permissions to role 1 (Admin/Super Admin)
    $db->exec("INSERT INTO user_role_permissions (role_id, menu_id, can_view, can_add, can_edit, can_delete) 
               VALUES (1, $parentId, 1, 1, 1, 1), (1, $subMenuId, 1, 1, 1, 1)");

    echo "Menu and permissions added successfully.\n";

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
