<?php

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Model\SystemLogModel;
use App\Model\UserModel;

$userId = (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);
$User = new UserModel();
$user = $userId > 0 ? $User->find($userId) : null;

if (!$user
    || ($user->durum ?? 'Aktif') === 'Pasif'
    || !$User->hasRoleName($userId, 'KASKİ Görüntüleme')
    || empty($_SESSION['firma_id'])) {
    header('Location: logout.php');
    exit;
}

unset($_SESSION['portal_scope'], $_SESSION['permission_cache']);
$_SESSION['user'] = $user;

try {
    (new SystemLogModel())->logAction(
        $userId,
        'Yönetim Paneline Geçiş',
        'KASKİ portalından normal yönetim paneline geçiş yapıldı.',
        SystemLogModel::LEVEL_INFO
    );
} catch (Throwable $e) {
    // Loglama sorunu yönlendirmeyi engellemez.
}

header('Location: ../index.php?p=home');
exit;
