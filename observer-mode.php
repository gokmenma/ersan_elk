<?php

// Composer autoload ile birlikte Security'nin kullandığı ENCRYPTION_KEY dahil
// proje ortam değişkenlerini de yükler.
require_once __DIR__ . '/Autoloader.php';

use App\Helper\Security;
use App\Service\Gate;
use App\Service\ObserverMode;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit('Yönteme izin verilmiyor.');
}

$action = (string) ($_POST['action'] ?? '');
try {
    if ($action === 'start') {
        $csrf = (string) ($_POST['csrf_token'] ?? '');
        if (empty($_SESSION['csrf_token']) || !hash_equals((string) $_SESSION['csrf_token'], $csrf)) {
            throw new RuntimeException('Güvenlik doğrulaması başarısız.');
        }
        if (!Gate::isSuperAdmin()) {
            throw new RuntimeException('Bu işlem yalnızca Superadmin tarafından yapılabilir.');
        }
        $targetId = (int) Security::decrypt((string) ($_POST['user_id'] ?? ''));
        if ($targetId <= 0) {
            throw new RuntimeException('Geçersiz kullanıcı.');
        }
        $result = ObserverMode::start($targetId);
        $redirect = $result['firma_id'] > 0 ? '/index.php' : '/firma-secim.php';
    } elseif ($action === 'stop') {
        if (!ObserverMode::isActive() || !ObserverMode::verifyCsrf((string) ($_POST['csrf_token'] ?? ''))) {
            throw new RuntimeException('Gözlem oturumu doğrulanamadı.');
        }
        ObserverMode::stop('manual');
        $redirect = '/index.php?p=kullanici/list';
    } else {
        throw new RuntimeException('Geçersiz işlem.');
    }

    header('Location: ' . $redirect);
    exit;
} catch (Throwable $e) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<p>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p><p><a href="/index.php">Ana sayfaya dön</a></p>';
}
