<?php

namespace App\Model;

use PDO;

class PermissionPolicyModel extends Model
{
    private ?bool $ready = null;

    public function isReady(): bool
    {
        if ($this->ready !== null) {
            return $this->ready;
        }

        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
        );
        $stmt->execute(['permission_policies']);
        return $this->ready = (int) $stmt->fetchColumn() > 0;
    }

    public function resolve(string $scope, string $resource, string $action = '', string $httpMethod = '*'): ?object
    {
        if (!$this->isReady()) {
            return null;
        }

        $scope = $scope === 'api' ? 'api' : 'page';
        $resource = trim($resource, '/');
        $action = trim($action);
        $httpMethod = strtoupper(trim($httpMethod)) ?: '*';

        $permissionKeySelect = $this->hasPermissionKeyColumn()
            ? "COALESCE(NULLIF(p.permission_key, ''), p.auth_name)"
            : 'p.auth_name';

        $accessModeSelect = $this->hasAccessModeColumn() ? 'pp.access_mode' : "'write' AS access_mode";
        $stmt = $this->db->prepare(
            "SELECT pp.id, pp.scope, pp.resource, pp.action, pp.http_method,
                    pp.permission_id, pp.superadmin_only, pp.authenticated_only,
                    {$accessModeSelect},
                    {$permissionKeySelect} AS permission_key
             FROM permission_policies pp
             LEFT JOIN permissions p ON p.id = pp.permission_id AND p.is_active = 1
             WHERE pp.scope = ?
               AND pp.resource = ?
               AND pp.action IN (?, '*')
               AND pp.is_active = 1
               AND pp.http_method IN (?, '*')
             ORDER BY (pp.action = ?) DESC, (pp.http_method = ?) DESC
             LIMIT 1"
        );
        $stmt->execute([$scope, $resource, $action, $httpMethod, $action, $httpMethod]);
        $policy = $stmt->fetch(PDO::FETCH_OBJ);

        return $policy ?: null;
    }

    private function hasPermissionKeyColumn(): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
        );
        $stmt->execute(['permissions', 'permission_key']);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function hasAccessModeColumn(): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
        );
        $stmt->execute(['permission_policies', 'access_mode']);
        return (int) $stmt->fetchColumn() > 0;
    }
}
