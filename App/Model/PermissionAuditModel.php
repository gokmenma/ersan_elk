<?php

namespace App\Model;

use PDO;

class PermissionAuditModel extends Model
{
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
            $roleIds = $subject ? array_values(array_unique(array_filter(array_map('intval', explode(',', (string) $subject->roles))))) : [];
        }

        if (!$subject || !$roleIds) {
            throw new \RuntimeException('Denetlenecek kullanıcı veya yetki grubu bulunamadı.');
        }

        $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
        $roleStmt = $this->db->prepare("SELECT id, role_name FROM user_roles WHERE owner_id = ? AND id IN ({$placeholders}) ORDER BY role_name");
        $roleStmt->execute(array_merge([$ownerId], $roleIds));
        $roles = $roleStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $validRoleIds = array_map('intval', array_column($roles, 'id'));
        if (!$validRoleIds) {
            throw new \RuntimeException('Geçerli bir yetki grubu bulunamadı.');
        }

        $menuStmt = $this->db->prepare(
            "SELECT id, menu_name, menu_link, group_name, is_menu, is_authorized
             FROM menus WHERE is_active = ?
             ORDER BY group_order, menu_order, menu_name"
        );
        $menuStmt->execute([1]);
        $menus = $menuStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $permissionStmt = $this->db->prepare(
            "SELECT id, name, auth_name FROM permissions WHERE is_active = ? ORDER BY id"
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
                $matches = (int) $permission['id'] === (int) $menu['id']
                    || (string) $permission['auth_name'] === (string) $menu['menu_link']
                    || (string) $permission['name'] === (string) $menu['menu_link']
                    || (string) $permission['name'] === (string) $menu['menu_name']
                    || ((string) $menu['menu_link'] === 'kullanici-gruplari/list'
                        && (string) $permission['auth_name'] === 'yetki_gruplari_izleme');
                if (!$matches) continue;

                $permissionId = (int) $permission['id'];
                $matchingPermissionIds[$permissionId] = true;
                if (!empty($permission['auth_name'])) $permissionCodes[] = (string) $permission['auth_name'];
                foreach ($grantSources[$permissionId] ?? [] as $roleName) {
                    $sourceRoles[$roleName] = true;
                }
            }
            $menu['matching_permission_count'] = count($matchingPermissionIds);
            $menu['granted_permission_count'] = count($sourceRoles);
            $menu['permission_codes'] = implode(' | ', array_values(array_unique($permissionCodes)));
            $menu['source_roles'] = implode(', ', array_keys($sourceRoles));
        }
        unset($menu);

        $stats = ['total' => count($menus), 'accessible' => 0, 'denied' => 0, 'critical' => 0, 'warning' => 0];
        foreach ($menus as &$menu) {
            $mapped = (int) $menu['matching_permission_count'] > 0;
            $granted = (int) $menu['granted_permission_count'] > 0;
            $public = (int) $menu['is_authorized'] === 0;
            $menu['accessible'] = $public || $granted;
            $menu['severity'] = 'ok';
            $menu['finding'] = $menu['accessible'] ? 'Erişim var' : 'Yetki kapalı';

            if (!$public && !$mapped && !empty($menu['menu_link'])) {
                $menu['severity'] = 'critical';
                $menu['finding'] = 'Yetki eşleşmesi bulunmayan korumalı rota';
                $stats['critical']++;
            } elseif ($public && !empty($menu['menu_link'])) {
                $menu['severity'] = 'warning';
                $menu['finding'] = 'Yetki kontrolü kapalı (herkese açık)';
                $stats['warning']++;
            } elseif ($granted && (int) $menu['is_menu'] === 0) {
                $menu['severity'] = 'warning';
                $menu['finding'] = 'Erişilebilir fakat sidebar’da gizli rota';
                $stats['warning']++;
            }
            $stats[$menu['accessible'] ? 'accessible' : 'denied']++;
        }
        unset($menu);

        return [
            'subject' => ['name' => $subject->subject_name, 'type' => $type],
            'roles' => $roles,
            'stats' => $stats,
            'menus' => $menus,
        ];
    }
}
