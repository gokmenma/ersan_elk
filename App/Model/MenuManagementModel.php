<?php

namespace App\Model;

use App\Model\Model;
use App\Model\SystemLogModel;
use App\Helper\Security;
use PDO;
use Exception;

class MenuManagementModel extends Model
{
    protected $table = 'menus';
    protected $primaryKey = 'id';

    public function __construct()
    {
        parent::__construct($this->table);
        $this->ensureSchema();
    }

    /**
     * Gerekli veritabanı yapısını kontrol eder ve eksik sütun/yetkileri otomatik ekler.
     */
    private function ensureSchema(): void
    {
        try {
            // 1) deleted_at sütununu kontrol et
            $stmt = $this->db->query("SHOW COLUMNS FROM {$this->table} LIKE 'deleted_at'");
            if (!$stmt->fetch()) {
                $this->db->exec("ALTER TABLE {$this->table} ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL AFTER `created_by`");
            }

            // 2) Yetki kaydı (permissions)
            $stmt = $this->db->prepare("SELECT id FROM permissions WHERE auth_name = 'menu-yonetimi/list' OR name = 'Menü Yönetimi' LIMIT 1");
            $stmt->execute();
            $permId = $stmt->fetchColumn();

            if (!$permId) {
                $stmt = $this->db->prepare("INSERT INTO permissions (name, description, auth_name, group_name, permission_level, is_required, is_active, superadmin) VALUES ('Menü Yönetimi', 'Sistem menü haritası ve menü elemanlarını yönetme yetkisi', 'menu-yonetimi/list', 'Yönetim', 1, 0, 1, 1)");
                $stmt->execute();
                $permId = $this->db->lastInsertId();
            }

            // 3) Menü kaydı (menus)
            $stmt = $this->db->prepare("SELECT id FROM menus WHERE menu_link = 'menu-yonetimi/list' LIMIT 1");
            $stmt->execute();
            $menuId = $stmt->fetchColumn();

            if (!$menuId) {
                $stmt = $this->db->prepare("INSERT INTO menus (menu_name, page_description, parent_id, group_name, group_order, menu_link, menu_icon, menu_order, is_active, is_menu, is_authorized, created_by) VALUES ('Menü Yönetimi', 'Sistem menü ve yetki haritası yönetimi', 0, 'Yönetim', 5, 'menu-yonetimi/list', 'sliders', 100, 1, 1, 1, 0)");
                $stmt->execute();
                $this->clearMenuCache();
            }

            // 4) User Role Permissions (Superadmin rollerine yetki bağla)
            if ($permId) {
                $stmt = $this->db->prepare("
                    INSERT INTO user_role_permissions (role_id, permission_id, created_by)
                    SELECT ur.id, ?, 0
                    FROM user_roles ur
                    WHERE (ur.role_type = 'superadmin' OR ur.superadmin = 1 OR ur.role_name = 'Süper Admin')
                      AND NOT EXISTS (
                          SELECT 1 FROM user_role_permissions urp WHERE urp.role_id = ur.id AND urp.permission_id = ?
                      )
                ");
                $stmt->execute([$permId, $permId]);
            }
        } catch (Exception $e) {
            error_log("MenuManagementModel ensureSchema error: " . $e->getMessage());
        }
    }

    /**
     * Tüm menü listesini ve üst menü isimlerini getirir.
     * 
     * @param bool $includeDeleted Silinmiş (soft-deleted) menülerin dahil edilip edilmeyeceği
     * @return array
     */
    public function getAllMenus(bool $includeDeleted = false): array
    {
        $whereSql = $includeDeleted ? "1=1" : "m.deleted_at IS NULL";

        $sql = "SELECT 
                    m.id,
                    m.menu_name,
                    m.page_description,
                    m.parent_id,
                    m.group_name,
                    m.group_order,
                    m.menu_link,
                    m.menu_icon,
                    m.menu_order,
                    m.is_active,
                    m.is_menu,
                    m.is_authorized,
                    m.created_at,
                    m.deleted_at,
                    p.menu_name AS parent_name
                FROM {$this->table} m
                LEFT JOIN {$this->table} p ON p.id = m.parent_id
                WHERE {$whereSql}
                ORDER BY m.group_order ASC, m.parent_id ASC, m.menu_order ASC, m.id ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $menus = $stmt->fetchAll(PDO::FETCH_OBJ) ?: [];

        foreach ($menus as $menu) {
            $menu->encrypted_id = Security::encrypt($menu->id);
            $menu->is_deleted = !empty($menu->deleted_at);
        }

        return $menus;
    }

    /**
     * Üst menü seçimi için geçerli parent menüleri (parent_id = 0 olanları) getirir.
     * 
     * @param int|null $excludeId Kendisini üst menü seçmesini önlemek için hariç tutulacak menü ID'si
     * @return array
     */
    public function getParentMenus(?int $excludeId = null): array
    {
        $sql = "SELECT id, menu_name, group_name FROM {$this->table} 
                WHERE parent_id = 0 AND deleted_at IS NULL";
        
        $params = [];
        if ($excludeId !== null && $excludeId > 0) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }

        $sql .= " ORDER BY group_order ASC, menu_order ASC, menu_name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_OBJ) ?: [];
    }

    /**
     * Sistemde tanımlı tüm benzersiz grup isimlerini getirir.
     * 
     * @return array
     */
    public function getGroupNames(): array
    {
        $sql = "SELECT DISTINCT group_name FROM {$this->table} 
                WHERE group_name IS NOT NULL AND group_name != '' AND deleted_at IS NULL
                ORDER BY group_order ASC, group_name ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    /**
     * ID'ye göre tekil menü kaydı getirir.
     * 
     * @param int $id
     * @return object|null
     */
    public function getMenuById(int $id): ?object
    {
        $sql = "SELECT * FROM {$this->table} WHERE id = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $menu = $stmt->fetch(PDO::FETCH_OBJ);

        if ($menu) {
            $menu->encrypted_id = Security::encrypt($menu->id);
        }

        return $menu ?: null;
    }

    /**
     * Menü ekleme veya güncelleme kaydı yapar.
     * 
     * @param array $data Form verileri
     * @param int $userId İşlemi yapan kullanıcı ID
     * @return bool|string
     */
    public function saveMenuData(array $data, int $userId)
    {
        $id = isset($data['id']) ? (int) $data['id'] : 0;
        
        $menuName = trim($data['menu_name'] ?? '');
        $pageDesc = trim($data['page_description'] ?? '');
        $parentId = (int) ($data['parent_id'] ?? 0);
        $groupName = trim($data['group_name'] ?? 'Yönetim');
        $groupOrder = (int) ($data['group_order'] ?? 1);
        $menuLink = trim($data['menu_link'] ?? '');
        $menuIcon = trim($data['menu_icon'] ?? '');
        $menuOrder = (int) ($data['menu_order'] ?? 1);
        $isActive = isset($data['is_active']) ? (int) $data['is_active'] : 1;
        $isMenu = isset($data['is_menu']) ? (int) $data['is_menu'] : 1;
        $isAuthorized = isset($data['is_authorized']) ? (int) $data['is_authorized'] : 1;

        if (empty($menuName)) {
            throw new Exception("Menü adı boş bırakılamaz.");
        }

        if ($id > 0) {
            // GÜNCELLEME
            $sql = "UPDATE {$this->table} SET 
                        menu_name = ?,
                        page_description = ?,
                        parent_id = ?,
                        group_name = ?,
                        group_order = ?,
                        menu_link = ?,
                        menu_icon = ?,
                        menu_order = ?,
                        is_active = ?,
                        is_menu = ?,
                        is_authorized = ?
                    WHERE id = ?";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                $menuName,
                $pageDesc,
                $parentId,
                $groupName,
                $groupOrder,
                $menuLink,
                $menuIcon,
                $menuOrder,
                $isActive,
                $isMenu,
                $isAuthorized,
                $id
            ]);

            $logModel = new SystemLogModel();
            $logModel->logAction(
                $userId,
                'Menü Güncelleme',
                "Menü kaydı güncellendi: '$menuName' (ID: $id).",
                SystemLogModel::LEVEL_IMPORTANT
            );

            $this->clearMenuCache();
            return true;
        } else {
            // YENİ EKLEME
            $sql = "INSERT INTO {$this->table} (
                        menu_name, page_description, parent_id, group_name, group_order, 
                        menu_link, menu_icon, menu_order, is_active, is_menu, is_authorized, created_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $menuName,
                $pageDesc,
                $parentId,
                $groupName,
                $groupOrder,
                $menuLink,
                $menuIcon,
                $menuOrder,
                $isActive,
                $isMenu,
                $isAuthorized,
                $userId
            ]);

            $newId = (int) $this->db->lastInsertId();

            $logModel = new SystemLogModel();
            $logModel->logAction(
                $userId,
                'Menü Ekleme',
                "Yeni menü eklendi: '$menuName' (ID: $newId).",
                SystemLogModel::LEVEL_IMPORTANT
            );

            $this->clearMenuCache();
            return Security::encrypt($newId);
        }
    }

    /**
     * Menüyü soft delete yapar (deleted_at = NOW(), is_active = 0).
     * 
     * @param int $id
     * @param int $userId
     * @return bool
     */
    public function softDeleteMenu(int $id, int $userId): bool
    {
        $menu = $this->getMenuById($id);
        if (!$menu) {
            throw new Exception("Silinecek menü bulunamadı.");
        }

        $sql = "UPDATE {$this->table} SET deleted_at = NOW(), is_active = 0 WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([$id]);

        if ($result) {
            $logModel = new SystemLogModel();
            $logModel->logAction(
                $userId,
                'Menü Silme (Soft Delete)',
                "Menü soft-delete edildi: '{$menu->menu_name}' (ID: {$id}).",
                SystemLogModel::LEVEL_CRITICAL
            );

            $this->clearMenuCache();
        }

        return $result;
    }

    /**
     * Soft-delete edilmiş menüyü geri yükler.
     * 
     * @param int $id
     * @param int $userId
     * @return bool
     */
    public function restoreMenu(int $id, int $userId): bool
    {
        $menu = $this->getMenuById($id);
        if (!$menu) {
            throw new Exception("Geri yüklenecek menü bulunamadı.");
        }

        $sql = "UPDATE {$this->table} SET deleted_at = NULL, is_active = 1 WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([$id]);

        if ($result) {
            $logModel = new SystemLogModel();
            $logModel->logAction(
                $userId,
                'Menü Geri Yükleme',
                "Silinmiş menü geri yüklendi: '{$menu->menu_name}' (ID: {$id}).",
                SystemLogModel::LEVEL_IMPORTANT
            );

            $this->clearMenuCache();
        }

        return $result;
    }

    /**
     * Önbelleğe alınan menü dosyalarını temizler.
     */
    public function clearMenuCache(): void
    {
        try {
            $cacheDir = dirname(__DIR__, 2) . '/cache';
            if (is_dir($cacheDir)) {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($cacheDir, \RecursiveDirectoryIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach ($iterator as $file) {
                    if ($file->isFile() && stripos($file->getFilename(), '.cache') !== false) {
                        @unlink($file->getPathname());
                    }
                }
            }
        } catch (Exception $e) {
            error_log("clearMenuCache error: " . $e->getMessage());
        }
    }
}
