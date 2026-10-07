<?php

namespace App\Model;

use PDO;

class PermissionCatalogModel extends Model
{
    private function hasColumn(string $table, string $column): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
        );
        $stmt->execute([$table, $column]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function getCatalog(): array
    {
        $hasPermissionKey = $this->hasColumn('permissions', 'permission_key');
        $hasMenuPermissionId = $this->hasColumn('menus', 'permission_id');

        $permissionKeySelect = $hasPermissionKey ? 'permission_key' : 'NULL AS permission_key';
        $permissionStmt = $this->db->prepare(
            "SELECT id, name, auth_name, description, group_name, is_active, {$permissionKeySelect}
             FROM permissions
             WHERE is_active = ?
             ORDER BY group_name, name, id"
        );
        $permissionStmt->execute([1]);
        $permissions = $permissionStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $menuPermissionSelect = $hasMenuPermissionId ? 'permission_id' : 'NULL AS permission_id';
        $menuStmt = $this->db->prepare(
            "SELECT id, menu_name, menu_link, group_name, is_menu, is_authorized, {$menuPermissionSelect}
             FROM menus
             WHERE is_active = ?
             ORDER BY group_order, menu_order, menu_name"
        );
        $menuStmt->execute([1]);
        $menus = $menuStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $permissionsById = [];
        foreach ($permissions as $permission) {
            $permissionsById[(int) $permission['id']] = $permission;
        }

        $usedPermissionIds = [];
        $rows = [];
        $stats = [
            'total' => count($permissions),
            'explicit' => 0,
            'legacy' => 0,
            'unmapped' => 0,
            'orphan' => 0,
        ];

        foreach ($menus as $menu) {
            $matched = null;
            $mappingType = 'unmapped';
            $explicitPermissionId = (int) ($menu['permission_id'] ?? 0);

            if ($hasMenuPermissionId && $explicitPermissionId > 0 && isset($permissionsById[$explicitPermissionId])) {
                $matched = $permissionsById[$explicitPermissionId];
                $mappingType = 'explicit';
            } else {
                foreach ($permissions as $permission) {
                    if ($this->isLegacyMatch($permission, $menu)) {
                        $matched = $permission;
                        $mappingType = 'legacy';
                        break;
                    }
                }
            }

            if ($matched) {
                $usedPermissionIds[(int) $matched['id']] = true;
                $stats[$mappingType]++;
            } elseif ((int) $menu['is_authorized'] === 1 && trim((string) $menu['menu_link']) !== '') {
                $stats['unmapped']++;
            }

            $rows[] = [
                'record_type' => 'Menü',
                'group_name' => (string) ($menu['group_name'] ?: '-'),
                'permission_name' => (string) ($matched['name'] ?? '-'),
                'permission_key' => $matched ? (string) (($matched['permission_key'] ?: $matched['auth_name']) ?: '-') : '-',
                'menu_name' => (string) ($menu['menu_name'] ?: '-'),
                'menu_link' => (string) ($menu['menu_link'] ?: '-'),
                'mapping_type' => $mappingType,
                'protected' => (int) $menu['is_authorized'] === 1,
            ];
        }

        foreach ($permissions as $permission) {
            $permissionId = (int) $permission['id'];
            if (isset($usedPermissionIds[$permissionId])) {
                continue;
            }

            $stats['orphan']++;
            $rows[] = [
                'record_type' => 'Aksiyon Yetkisi',
                'group_name' => (string) ($permission['group_name'] ?: '-'),
                'permission_name' => (string) ($permission['name'] ?: '-'),
                'permission_key' => (string) (($permission['permission_key'] ?: $permission['auth_name']) ?: '-'),
                'menu_name' => '-',
                'menu_link' => '-',
                'mapping_type' => 'orphan',
                'protected' => true,
            ];
        }

        return [
            'rows' => $rows,
            'stats' => $stats,
            'schema_ready' => $hasPermissionKey && $hasMenuPermissionId,
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
