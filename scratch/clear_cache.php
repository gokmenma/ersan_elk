<?php
require_once dirname(__DIR__) . '/Autoloader.php';
use App\Model\MenuModel;

session_start();
$Menu = new MenuModel();
$Menu->clearAllMenuCachesForCurrentTenant();
echo "Menu cache cleared for owner_id: " . ($_SESSION['owner_id'] ?? 0);
