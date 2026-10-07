<?php

namespace App\Model;

use PDO;

class PermissionAuditModel extends Model
{
    private function hasTable(string $table): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
        );
        $stmt->execute([$table]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function hasColumn(string $table, string $column): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
        );
        $stmt->execute([$table, $column]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function getSubjects(int $ownerId): array
    {
        $rolesStmt = $this->db->prepare("SELECT id, role_name FROM user_roles WHERE owner_id = ? ORDER BY role_name");
        $rolesStmt->execute([$ownerId]);

        $usersStmt = $this->db->prepare("SELECT id, adi_soyadi, user_name FROM users WHERE owner_id = ? ORDER BY adi_soyadi");
        $usersStmt->execute([$ownerId]);

        return [
            'roles' => $rolesStmt->fetchAll(PDO::FETCH_OBJ) ?: [],
            'users' => $usersStmt->fetchAll(PDO::FETCH_OBJ) ?: [],
        ];
    }

    public function audit(string $type, int $subjectId, int $ownerId): array
    {
        if ($type === 'role') {
            $stmt = $this->db->prepare("SELECT id, role_name AS subject_name FROM user_roles WHERE id = ? AND owner_id = ? LIMIT 1");
            $stmt->execute([$subjectId, $ownerId]);
            $subject = $stmt->fetch(PDO::FETCH_OBJ);
            $roleIds = $subject ? [(int) $subject->id] : [];
        } else {
            $stmt = $this->db->prepare("SELECT id, adi_soyadi AS subject_name, roles FROM users WHERE id = ? AND owner_id = ? LIMIT 1");
            $stmt->execute([$subjectId, $ownerId]);
            $subject = $stmt->fetch(PDO::FETCH_OBJ);
            $roleIds = $subject ? (new UserRoleAssignmentModel())->activeRoleIdsForUser((int) $subject->id) : [];
        }

        if (!$subject || !$roleIds) {
            throw new \RuntimeException('Denetlenecek kullanıcı veya yetki grubu bulunamadı.');
        }

        $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
        $roleStmt = $this->db->prepare(
            "SELECT id, role_name, superadmin, role_type
             FROM user_roles WHERE owner_id = ? AND id IN ({$placeholders}) ORDER BY role_name"
        );
        $roleStmt->execute(array_merge([$ownerId], $roleIds));
        $roles = $roleStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $validRoleIds = array_map('intval', array_column($roles, 'id'));
        if (!$validRoleIds) {
            throw new \RuntimeException('Geçerli bir yetki grubu bulunamadı.');
        }
        $isSuperAdminSubject = count(array_filter(
            $roles,
            static fn(array $role): bool => (int) ($role['superadmin'] ?? 0) === 1
                || (string) ($role['role_type'] ?? '') === 'superadmin'
        )) > 0;

        $hasMenuPermissionId = $this->hasColumn('menus', 'permission_id');
        $menuPermissionSelect = $hasMenuPermissionId ? 'permission_id' : 'NULL AS permission_id';
        $menuStmt = $this->db->prepare(
            "SELECT id, menu_name, menu_link, parent_id, group_name, is_menu, is_authorized, {$menuPermissionSelect}
             FROM menus WHERE is_active = ?
             ORDER BY group_order, menu_order, menu_name"
        );
        $menuStmt->execute([1]);
        $menus = $menuStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $policyReady = $this->hasTable('permission_policies');
        $pagePolicies = [];
        if ($policyReady) {
            $policyStmt = $this->db->prepare(
                "SELECT resource, permission_id, superadmin_only, authenticated_only
                 FROM permission_policies
                 WHERE scope = ? AND action = ? AND is_active = 1"
            );
            $policyStmt->execute(['page', '']);
            foreach ($policyStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $policy) {
                $pagePolicies[(string) $policy['resource']] = $policy;
            }
        }

        $hasPermissionKey = $this->hasColumn('permissions', 'permission_key');
        $permissionKeySelect = $hasPermissionKey ? 'permission_key' : 'NULL AS permission_key';
        $permissionStmt = $this->db->prepare(
            "SELECT id, name, auth_name, {$permissionKeySelect} FROM permissions WHERE is_active = ? ORDER BY id"
        );
        $permissionStmt->execute([1]);
        $permissions = $permissionStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $placeholders = implode(',', array_fill(0, count($validRoleIds), '?'));
        $grantStmt = $this->db->prepare(
            "SELECT DISTINCT urp.permission_id, ur.role_name
             FROM user_role_permissions urp
             INNER JOIN user_roles ur ON ur.id = urp.role_id
             WHERE urp.role_id IN ({$placeholders})"
        );
        $grantStmt->execute($validRoleIds);
        $grantRows = $grantStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $grantSources = [];
        foreach ($grantRows as $grant) {
            $permissionId = (int) $grant['permission_id'];
            $grantSources[$permissionId][] = (string) $grant['role_name'];
        }

        foreach ($menus as &$menu) {
            $matchingPermissionIds = [];
            $permissionCodes = [];
            $sourceRoles = [];
            foreach ($permissions as $permission) {
                $explicitPermissionId = (int) ($menu['permission_id'] ?? 0);
                $matches = $explicitPermissionId > 0
                    ? (int) $permission['id'] === $explicitPermissionId
                    : (!empty($menu['menu_link']) && $this->isLegacyMatch($permission, $menu));
                if (!$matches) continue;

                $permissionId = (int) $permission['id'];
                $matchingPermissionIds[$permissionId] = true;
                $permissionCode = (string) (($permission['permission_key'] ?: $permission['auth_name']) ?: '');
                if ($permissionCode !== '') $permissionCodes[] = $permissionCode;
                foreach ($grantSources[$permissionId] ?? [] as $roleName) {
                    $sourceRoles[$roleName] = true;
                }
            }
            $menu['matching_permission_count'] = count($matchingPermissionIds);
            $menu['matching_permission_ids'] = array_map('intval', array_keys($matchingPermissionIds));
            $menu['granted_permission_count'] = count($sourceRoles);
            $menu['permission_codes'] = implode(' | ', array_values(array_unique($permissionCodes)));
            $menu['source_roles'] = implode(', ', array_keys($sourceRoles));
            $policy = $pagePolicies[(string) $menu['menu_link']] ?? null;
            $menu['policy_status'] = empty($menu['menu_link'])
                ? 'Uygulanmaz'
                : (!$policyReady ? 'Geçiş SQL’i bekleniyor' : ($policy ? 'Tanımlı' : 'Eksik'));
            $menu['policy_permission_id'] = (int) ($policy['permission_id'] ?? 0);
            $menu['policy_superadmin_only'] = (int) ($policy['superadmin_only'] ?? 0);
            $menu['policy_authenticated_only'] = (int) ($policy['authenticated_only'] ?? 0);
        }
        unset($menu);

        $menusById = [];
        foreach ($menus as $index => &$menu) {
            $menu['sidebar_visible'] = (int) $menu['is_menu'] === 1
                && ($isSuperAdminSubject || (int) $menu['granted_permission_count'] > 0);
            $menu['has_visible_children'] = false;
            $policyPermissionGranted = $menu['policy_permission_id'] > 0
                && !empty($grantSources[$menu['policy_permission_id']]);
            $menu['route_accessible'] = $menu['policy_status'] === 'Tanımlı'
                && ($isSuperAdminSubject
                    || $menu['policy_authenticated_only'] === 1
                    || $policyPermissionGranted);
            $menusById[(int) $menu['id']] = $index;
        }
        unset($menu);

        // Üst menüler kendi başlarına yetki değildir; görünürlükleri erişilebilir alt menüden türetilir.
        for ($pass = 0; $pass < count($menus); $pass++) {
            $changed = false;
            foreach ($menus as $menu) {
                if (!$menu['sidebar_visible'] || (int) $menu['parent_id'] === 0) continue;
                $parentIndex = $menusById[(int) $menu['parent_id']] ?? null;
                if ($parentIndex !== null && !$menus[$parentIndex]['sidebar_visible']) {
                    $menus[$parentIndex]['sidebar_visible'] = true;
                    $menus[$parentIndex]['has_visible_children'] = true;
                    $changed = true;
                } elseif ($parentIndex !== null) {
                    $menus[$parentIndex]['has_visible_children'] = true;
                }
            }
            if (!$changed) break;
        }

        $stats = ['total' => count($menus), 'accessible' => 0, 'denied' => 0, 'critical' => 0, 'warning' => 0];
        foreach ($menus as &$menu) {
            $mapped = (int) $menu['matching_permission_count'] > 0;
            $hasRoute = !empty($menu['menu_link']);
            $menu['accessible'] = $hasRoute && !$menu['has_visible_children']
                ? $menu['route_accessible']
                : $menu['sidebar_visible'];
            $menu['severity'] = 'ok';
            $menu['finding'] = $menu['accessible'] ? 'Erişim var' : 'Yetki kapalı';

            if ($hasRoute && !$mapped) {
                $menu['severity'] = 'critical';
                $menu['finding'] = 'Yetki eşleşmesi bulunmayan korumalı rota';
                $stats['critical']++;
            } elseif ($policyReady && $hasRoute && $menu['policy_status'] === 'Eksik') {
                $menu['severity'] = 'critical';
                $menu['finding'] = 'Korumalı rota için sayfa politikası bulunamadı';
                $stats['critical']++;
            } elseif ($policyReady && $mapped && $menu['policy_permission_id'] > 0
                && !in_array($menu['policy_permission_id'], $menu['matching_permission_ids'], true)) {
                $menu['severity'] = 'critical';
                $menu['finding'] = 'Menü yetkisi ile sayfa politikası çelişiyor';
                $stats['critical']++;
            } elseif ($hasRoute && !$menu['has_visible_children'] && (int) $menu['is_menu'] === 1
                && $menu['sidebar_visible'] !== $menu['route_accessible']) {
                $menu['severity'] = 'critical';
                $menu['finding'] = $menu['sidebar_visible']
                    ? 'Menü görünüyor fakat rota erişimi kapalı'
                    : 'Rota erişimi açık fakat menü görünmüyor';
                $stats['critical']++;
            } elseif ($hasRoute && $menu['route_accessible'] && (int) $menu['is_menu'] === 0) {
                $menu['severity'] = 'info';
                $menu['finding'] = 'Politika ile korunan sidebar dışı alt/işlem rotası';
            } elseif (!$hasRoute || $menu['has_visible_children']) {
                $menu['finding'] = $menu['sidebar_visible']
                    ? 'Erişilebilir alt menü bulundu'
                    : 'Erişilebilir alt menü yok';
            }
            $stats[$menu['accessible'] ? 'accessible' : 'denied']++;
        }
        unset($menu);

        $assignmentModel = new UserRoleAssignmentModel();
        $migration = [
            'policy_ready' => $policyReady,
            'assignment_ready' => $assignmentModel->isReady(),
            'role_sources_match' => true,
        ];
        if ($type === 'user' && $assignmentModel->isReady()) {
            $legacyRoleIds = array_values(array_unique(array_filter(array_map('intval', explode(',', (string) ($subject->roles ?? ''))))));
            sort($legacyRoleIds);
            $normalizedRoleIds = $assignmentModel->activeRoleIdsForUser((int) $subject->id, false);
            sort($normalizedRoleIds);
            $migration['role_sources_match'] = $legacyRoleIds === $normalizedRoleIds;
            $migration['legacy_role_ids'] = $legacyRoleIds;
            $migration['normalized_role_ids'] = $normalizedRoleIds;
        }

        return [
            'subject' => ['name' => $subject->subject_name, 'type' => $type],
            'roles' => $roles,
            'stats' => $stats,
            'menus' => $menus,
            'migration' => $migration,
        ];
    }

    private function isLegacyMatch(array $permission, array $menu): bool
    {
        $menuLink = (string) $menu['menu_link'];
        $authName = (string) $permission['auth_name'];

        return $authName === $menuLink
            || (string) $permission['name'] === $menuLink
            || ((int) $permission['id'] === (int) $menu['id'] && (int) $menu['id'] <= 944)
            || ($menuLink === 'kullanici-gruplari/list'
                && in_array($authName, ['yetki_gruplari', 'yetki_gruplari_izleme'], true));
    }
}
