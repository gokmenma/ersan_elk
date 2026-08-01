<?php
require 'vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

try {
    $db = new PDO(
        "mysql:host=" . $_ENV['DB_HOST'] . ";dbname=" . $_ENV['DB_DATABASE'] . ";charset=utf8mb4",
        $_ENV['DB_USERNAME'],
        $_ENV['DB_PASSWORD'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $sql = file_get_contents(__DIR__ . '/sql/add_menu_management.sql');
    $db->exec($sql);
    echo "SQL script applied successfully.\n";

    // Clear cache directory if menu cache exists
    $cacheDir = __DIR__ . '/cache';
    if (is_dir($cacheDir)) {
        $files = glob($cacheDir . '/*/*.cache');
        foreach ($files as $file) {
            @unlink($file);
        }
    }
    echo "Menu cache cleared.\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
