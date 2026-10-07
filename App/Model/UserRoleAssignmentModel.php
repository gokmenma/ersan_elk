<?php

namespace App\Model;

use PDO;

class UserRoleAssignmentModel extends Model
{
    protected $table = 'user_role_assignments';
    private ?bool $ready = null;

    public function __construct()
    {
        parent::__construct($this->table);
    }

    public function isReady(): bool
    {
        if ($this->ready !== null) {
            return $this->ready;
        }
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
        );
        $stmt->execute([$this->table]);
        return $this->ready = (int) $stmt->fetchColumn() > 0;
    }

    public function activeRoleIdsForUser(int $userId, bool $legacyFallback = true): array
    {
        if ($userId <= 0) {
            return [];
        }
        if ($this->isReady()) {
            $stmt = $this->db->prepare(
                "SELECT DISTINCT role_id FROM user_role_assignments
                 WHERE user_id = ? AND is_active = 1 AND deleted_at IS NULL
                   AND (starts_at IS NULL OR starts_at <= NOW())
                   AND (ends_at IS NULL OR ends_at >= NOW())
                 ORDER BY role_id"
            );
            $stmt->execute([$userId]);
            $roleIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
            if ($roleIds || !$legacyFallback) {
                return $roleIds;
            }
        }
        $stmt = $this->db->prepare("SELECT roles FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        return $this->normalizeRoleIds(explode(',', (string) $stmt->fetchColumn()));
    }

    public function syncActiveAssignments(int $userId, array $roleIds, int $assignedBy = 0, ?string $reason = null): void
    {
        if (!$this->isReady() || $userId <= 0) {
            return;
        }
        $roleIds = $this->normalizeRoleIds($roleIds);
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                "SELECT id, role_id FROM user_role_assignments
                 WHERE user_id = ? AND is_active = 1 AND deleted_at IS NULL FOR UPDATE"
            );
            $stmt->execute([$userId]);
            $activeRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $activeByRole = [];
            foreach ($activeRows as $row) {
                $activeByRole[(int) $row['role_id']] = (int) $row['id'];
            }

            $removedRoleIds = array_diff(array_keys($activeByRole), $roleIds);
            if ($removedRoleIds) {
                $placeholders = implode(',', array_fill(0, count($removedRoleIds), '?'));
                $endStmt = $this->db->prepare(
                    "UPDATE user_role_assignments
                     SET is_active = 0, ends_at = COALESCE(ends_at, NOW()), updated_at = NOW()
                     WHERE user_id = ? AND role_id IN ({$placeholders})
                       AND is_active = 1 AND deleted_at IS NULL"
                );
                $endStmt->execute(array_merge([$userId], array_values($removedRoleIds)));
            }

            $insertStmt = $this->db->prepare(
                "INSERT INTO user_role_assignments
                    (user_id, role_id, assigned_by, starts_at, ends_at, is_active, reason, created_at, updated_at)
                 VALUES (?, ?, ?, NOW(), NULL, 1, ?, NOW(), NOW())"
            );
            foreach (array_diff($roleIds, array_keys($activeByRole)) as $roleId) {
                $insertStmt->execute([$userId, $roleId, $assignedBy > 0 ? $assignedBy : null, $reason]);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function activeUsersForRole(int $roleId): array
    {
        if ($roleId <= 0) {
            return [];
        }
        if ($this->isReady()) {
            $stmt = $this->db->prepare(
                "SELECT DISTINCT u.id, u.adi_soyadi, u.user_name, u.email_adresi, u.telefon,
                        u.gorevi, u.durum, p.id AS personel_id, p.departman,
                        p.calisilan_firma, p.personel_resim_yolu
                 FROM user_role_assignments ura
                 INNER JOIN users u ON u.id = ura.user_id
                 LEFT JOIN personel p ON p.id = u.personel_id
                 WHERE ura.role_id = ? AND ura.is_active = 1 AND ura.deleted_at IS NULL
                   AND (ura.starts_at IS NULL OR ura.starts_at <= NOW())
                   AND (ura.ends_at IS NULL OR ura.ends_at >= NOW())
                 ORDER BY u.durum, u.adi_soyadi"
            );
            $stmt->execute([$roleId]);
            $users = $stmt->fetchAll(PDO::FETCH_OBJ) ?: [];
            if ($users) {
                return $users;
            }
        }
        $stmt = $this->db->prepare(
            "SELECT u.id, u.adi_soyadi, u.user_name, u.email_adresi, u.telefon,
                    u.gorevi, u.durum, p.id AS personel_id, p.departman,
                    p.calisilan_firma, p.personel_resim_yolu
             FROM users u LEFT JOIN personel p ON p.id = u.personel_id
             WHERE FIND_IN_SET(?, REPLACE(u.roles, ' ', '')) > 0
             ORDER BY u.durum, u.adi_soyadi"
        );
        $stmt->execute([(string) $roleId]);
        return $stmt->fetchAll(PDO::FETCH_OBJ) ?: [];
    }

    public function activeUsersWithPermission(string $permissionName, ?int $ownerId = null): array
    {
        $assignmentJoin = $this->isReady()
            ? "INNER JOIN user_role_assignments ura
                   ON ura.user_id = u.id AND ura.is_active = 1 AND ura.deleted_at IS NULL
                  AND (ura.starts_at IS NULL OR ura.starts_at <= NOW())
                  AND (ura.ends_at IS NULL OR ura.ends_at >= NOW())
               INNER JOIN user_role_permissions urp ON urp.role_id = ura.role_id"
            : "INNER JOIN user_role_permissions urp
                   ON FIND_IN_SET(urp.role_id, REPLACE(u.roles, ' ', '')) > 0";
        $sql = "SELECT DISTINCT u.id, u.personel_id, u.adi_soyadi, u.email_adresi
                FROM users u {$assignmentJoin}
                INNER JOIN permissions p ON p.id = urp.permission_id
                WHERE u.durum = 'Aktif'
                  AND (p.auth_name = :permission OR p.name = :permission)";
        $params = ['permission' => $permissionName];
        if ($ownerId !== null && $ownerId > 0) {
            $sql .= ' AND u.owner_id = :owner_id';
            $params['owner_id'] = $ownerId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_OBJ) ?: [];
    }

    public function activeSuperAdminUserIds(): array
    {
        $assignmentJoin = $this->isReady()
            ? "INNER JOIN user_role_assignments ura
                   ON ura.user_id = u.id AND ura.is_active = 1 AND ura.deleted_at IS NULL
                  AND (ura.starts_at IS NULL OR ura.starts_at <= NOW())
                  AND (ura.ends_at IS NULL OR ura.ends_at >= NOW())
               INNER JOIN user_roles ur ON ur.id = ura.role_id"
            : "INNER JOIN user_roles ur
                   ON FIND_IN_SET(ur.id, REPLACE(u.roles, ' ', '')) > 0";
        $stmt = $this->db->prepare(
            "SELECT DISTINCT u.id FROM users u {$assignmentJoin}
             WHERE u.durum = 'Aktif' AND (ur.superadmin = 1 OR ur.role_type = 'superadmin')"
        );
        $stmt->execute();
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    private function normalizeRoleIds(array $roleIds): array
    {
        $normalized = array_values(array_unique(array_filter(array_map(
            static fn($roleId): int => (int) trim((string) $roleId),
            $roleIds
        ), static fn(int $roleId): bool => $roleId > 0)));
        sort($normalized);
        return $normalized;
    }
}
