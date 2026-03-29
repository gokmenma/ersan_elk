<?php
// Initialize the session
session_start();

// Check if the user is logged in, if not then redirect him to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

// User status check (kicks out passive users)
require_once __DIR__ . '/../vendor/autoload.php';
$UserStatusModel = new \App\Model\UserModel();
// We use $_SESSION["id"] or $_SESSION["user_id"]
$currentUserIdForStatus = $_SESSION["id"] ?? $_SESSION["user_id"] ?? 0;
if ($currentUserIdForStatus > 0) {
    $currentUserStatusCheck = $UserStatusModel->find($currentUserIdForStatus);
    if (!$currentUserStatusCheck || ($currentUserStatusCheck->durum ?? 'Aktif') === 'Pasif') {
        // Log the logout action for security
        try {
            $systemLogStatus = new \App\Model\SystemLogModel();
            $systemLogStatus->logAction(
                $currentUserIdForStatus,
                'Zorunlu Çıkış',
                "Kullanıcı pasif duruma alındığı için sistemden zorunlu çıkarıldı.",
                \App\Model\SystemLogModel::LEVEL_CRITICAL
            );
        } catch (\Exception $e) {}

        session_unset();
        session_destroy();
        header("location: login.php?status=inactive");
        exit;
    }
}

define('BASE_PATH', dirname(__DIR__)); // Adjust the path as needed
