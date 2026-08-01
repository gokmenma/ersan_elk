<?php
// Lint & Verification Test Script
require 'vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION['user_id'] = 1;

try {
    $db = new PDO(
        "mysql:host=" . $_ENV['DB_HOST'] . ";dbname=" . $_ENV['DB_DATABASE'] . ";charset=utf8mb4",
        $_ENV['DB_USERNAME'],
        $_ENV['DB_PASSWORD'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Apply Migration SQL if not already applied
    $sql = file_get_contents(__DIR__ . '/sql/add_menu_management.sql');
    $db->exec($sql);
    echo "[OK] SQL script applied successfully.\n";

    // Test Model
    $model = new \App\Model\MenuManagementModel();
    $menus = $model->getAllMenus(true);
    echo "[OK] MenuManagementModel instantiated and fetched " . count($menus) . " menus.\n";

    $parents = $model->getParentMenus();
    echo "[OK] Parent menus fetched: " . count($parents) . ".\n";

    $groups = $model->getGroupNames();
    echo "[OK] Groups fetched: " . implode(', ', $groups) . "\n";

    // Check if menu-yonetimi/list exists in menus table
    $stmt = $db->query("SELECT * FROM menus WHERE menu_link = 'menu-yonetimi/list'");
    $menuRecord = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($menuRecord) {
        echo "[OK] 'Menü Yönetimi' menu record found in database (ID: " . $menuRecord['id'] . ", Order: " . $menuRecord['menu_order'] . ", Group: " . $menuRecord['group_name'] . ").\n";
    } else {
        echo "[FAIL] 'Menü Yönetimi' menu record not found in database!\n";
    }

    // Clear cache
    $model->clearMenuCache();
    echo "[OK] Menu cache cleared successfully.\n";

} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
}
