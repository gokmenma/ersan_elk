<?php

namespace App\Service;

use App\Controllers\AuthController;
use App\Helper\Security;
use App\Model\ObserverAuditModel;
use App\Model\PermissionsModel;
use App\Model\UserModel;
use App\Model\UserRoleAssignmentModel;

final class ObserverMode
{
    private const TTL = 1800;

    public static function isActive(): bool
    {
        return !empty($_SESSION['observer_mode']['active']);
    }

    public static function data(): array
    {
        return self::isActive() ? (array) $_SESSION['observer_mode'] : [];
    }

    public static function start(int $targetUserId): array
    {
        if (self::isActive() || !Gate::isSuperAdmin()) {
            throw new \RuntimeException('Bu işlem yalnızca normal Superadmin oturumundan başlatılabilir.');
        }

        $actor = AuthController::user();
        $target = (new UserModel())->find($targetUserId);
        if (!$actor || !$target || ($target->durum ?? '') !== 'Aktif') {
            throw new \RuntimeException('Aktif hedef kullanıcı bulunamadı.');
        }
        $actorOwner = (int) ($_SESSION['owner_id'] ?? 0);
        $targetOwner = (int) (($target->owner_id ?? 0) ?: $target->id);
        if ($targetOwner !== $actorOwner || (int) $target->id === (int) $actor->id) {
            throw new \RuntimeException('Bu kullanıcı gözlem kapsamında değil.');
        }

        $roleIds = (new UserRoleAssignmentModel())->activeRoleIdsForUser((int) $target->id);
        if ($roleIds) {
            $db = (new UserRoleAssignmentModel())->getDb();
            $marks = implode(',', array_fill(0, count($roleIds), '?'));
            $stmt = $db->prepare("SELECT COUNT(*) FROM user_roles WHERE id IN ({$marks}) AND (role_type = 'superadmin' OR role_name = 'Süper Admin')");
            $stmt->execute($roleIds);
            if ((int) $stmt->fetchColumn() > 0) {
                throw new \RuntimeException('Superadmin hesapları gözlem hedefi olamaz.');
            }
        }

        $original = $_SESSION;
        $allowedFirmIds = array_values(array_filter(array_map('intval', explode(',', (string) ($target->firma_ids ?? '')))));
        $currentFirm = (int) ($_SESSION['firma_id'] ?? 0);
        $firmaId = in_array($currentFirm, $allowedFirmIds, true) ? $currentFirm : (int) ($allowedFirmIds[0] ?? 0);
        $now = time();

        $_SESSION['user'] = $target;
        $_SESSION['user_id'] = (int) $target->id;
        $_SESSION['id'] = (int) $target->id;
        $_SESSION['full_name'] = (string) ($target->adi_soyadi ?? $target->full_name ?? $target->user_name ?? 'Kullanıcı');
        $_SESSION['user_role'] = (string) ($target->roles ?? '');
        $_SESSION['owner_id'] = $targetOwner;
        $_SESSION['loggedin'] = true;
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['permission_cache'] = [];
        $_SESSION['topbar_firma_option_cache'] = [];
        if ($firmaId > 0) {
            $_SESSION['firma_id'] = $firmaId;
            $_SESSION['site_id'] = $firmaId;
        } else {
            unset($_SESSION['firma_id'], $_SESSION['site_id']);
        }
        $_SESSION['observer_mode'] = [
            'active' => true,
            'actor_user_id' => (int) $actor->id,
            'actor_name' => (string) ($actor->adi_soyadi ?? $actor->full_name ?? $actor->user_name ?? 'Superadmin'),
            'target_user_id' => (int) $target->id,
            'target_name' => (string) ($target->adi_soyadi ?? $target->full_name ?? $target->user_name ?? 'Kullanıcı'),
            'started_at' => $now,
            'expires_at' => $now + self::TTL,
            'csrf_token' => bin2hex(random_bytes(32)),
            'original_session' => $original,
        ];
        session_regenerate_id(true);
        (new ObserverAuditModel())->log((int) $actor->id, (int) $target->id, $firmaId, 'started');

        return ['firma_id' => $firmaId];
    }

    public static function stop(string $reason = 'manual'): void
    {
        if (!self::isActive()) {
            return;
        }
        $state = self::data();
        (new ObserverAuditModel())->log(
            (int) ($state['actor_user_id'] ?? 0),
            (int) ($state['target_user_id'] ?? 0),
            (int) ($_SESSION['firma_id'] ?? 0),
            'stopped',
            '',
            '',
            ['reason' => $reason]
        );
        $original = is_array($state['original_session'] ?? null) ? $state['original_session'] : [];
        $_SESSION = $original;
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['permission_cache'] = [];
        $_SESSION['topbar_firma_option_cache'] = [];
        session_regenerate_id(true);
    }

    public static function ensureValid(): bool
    {
        if (!self::isActive()) {
            return true;
        }
        $state = self::data();
        $target = (new UserModel())->find((int) ($state['target_user_id'] ?? 0));
        $hasRoles = $target
            ? (new UserRoleAssignmentModel())->activeRoleIdsForUser((int) $target->id) !== []
            : false;
        $hasPermissions = $target
            ? (new PermissionsModel())->getPermissionsForUser((int) $target->id) !== []
            : false;
        if (($state['expires_at'] ?? 0) <= time() || !$target || ($target->durum ?? '') !== 'Aktif' || !$hasRoles || !$hasPermissions) {
            self::stop(($state['expires_at'] ?? 0) <= time() ? 'expired' : 'target_inactive');
            return false;
        }
        return true;
    }

    public static function verifyCsrf(string $token): bool
    {
        $expected = (string) (self::data()['csrf_token'] ?? '');
        return $expected !== '' && $token !== '' && hash_equals($expected, $token);
    }

    public static function allowsPolicy(?object $policy, string $resource, string $action, string $method): bool
    {
        $state = self::data();
        $allowed = $policy !== null && ($policy->access_mode ?? 'write') === 'read';
        (new ObserverAuditModel())->log(
            (int) ($state['actor_user_id'] ?? 0),
            (int) ($state['target_user_id'] ?? 0),
            (int) ($_SESSION['firma_id'] ?? 0),
            $allowed ? 'api_read' : 'write_blocked',
            $resource,
            $action,
            ['method' => $method]
        );
        return $allowed;
    }

    public static function logPage(string $resource): void
    {
        if (!self::isActive()) {
            return;
        }
        $state = self::data();
        (new ObserverAuditModel())->log(
            (int) ($state['actor_user_id'] ?? 0),
            (int) ($state['target_user_id'] ?? 0),
            (int) ($_SESSION['firma_id'] ?? 0),
            'page_view',
            $resource
        );
    }
}
