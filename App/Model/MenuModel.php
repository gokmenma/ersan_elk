<?php

namespace App\Model;

use App\Helper\Helper;
use App\Model\Model;
use App\Model\UserModel;
use PDO;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class MenuModel extends Model
{
    protected $table = 'menus';

    // Cache ayarları
    private string $baseCacheDir; // Ana cache dizini
    private int $cacheLifetime = 3600; // Saniye cinsinden (1 saat)

    private int $ownerId; // Mevcut kiracının (owner) ID'si
    private string $ownerSpecificCacheDir; // Kiracıya özel cache alt dizini

    public function __construct()
    {
        parent::__construct($this->table);

        // Doğru baseCacheDir tanımı:
        // __DIR__ şu anki dosyanın (MenuModel.php) dizinidir: C:\xampp\htdocs\cansen\admin\App\Model
        $this->baseCacheDir = dirname(__DIR__, 2) . '/cache';

        $this->ownerId = isset($_SESSION['owner_id']) ? (int) $_SESSION['owner_id'] : 0; // Kiracı ID'si, oturumdan alınır. Eğer oturumda yoksa 0 olarak ayarlanır.

        if ($this->ownerId !== 0) {
            // $this->baseCacheDir şimdi doğru yolu (C:\xampp\htdocs\cansen\admin/cache) göstermeli
            $this->ownerSpecificCacheDir = $this->baseCacheDir . '/tenant_' . $this->ownerId;

            if (!is_dir($this->ownerSpecificCacheDir)) {
                // Hata kontrolü eklenebilir (örneğin loglama)
                if (!mkdir($this->ownerSpecificCacheDir, 0775, true) && !is_dir($this->ownerSpecificCacheDir)) {
                    // Dizin oluşturulamadıysa logla veya bir istisna fırlat
                    error_log("Failed to create directory: " . $this->ownerSpecificCacheDir);
                    $this->ownerSpecificCacheDir = ''; // Hata durumunda cache dizinini geçersiz kıl
                }
            }
        }
        // ownerId 0 ise veya dizin oluşturulamadıysa $this->ownerSpecificCacheDir boş kalır.
    }

    public function getHierarchicalMenuForRole(int $user_id): array
    {
        if ($this->ownerId === 0 || empty($this->ownerSpecificCacheDir) || !is_dir($this->ownerSpecificCacheDir)) {
            // Geçerli bir kiracı yoksa veya kiracıya özel cache dizini ayarlanamadıysa,
            // önbellekleme yapmadan doğrudan veritabanından çek.
            // VEYA burada bir istisna fırlatabilirsiniz.
            return $this->fetchAndBuildMenuFromDb($user_id);
        }

        //$this->clearMenuCacheForRole(0); // Genel önbelleği temizle


        $Users = new UserModel(); // Bağımlılık enjeksiyonu düşünülebilir
        $roleIds = $Users->getUserRoleID($user_id);
        $roleIdArray = $this->normalizeRoleIds($roleIds);

        if (empty($roleIdArray)) {
            return [];
        }

        //Helper::dd("Fetching menu for user_id: {$user_id} with role_ids: {$roleIds}");

        $cacheKey = $this->buildMenuCacheKey($roleIdArray);
        $cacheFile = $this->ownerSpecificCacheDir . "/menu_role_{$cacheKey}.cache";

        if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $this->cacheLifetime)) {
            $cachedData = @file_get_contents($cacheFile); // @ ile hata bastırma yerine try-catch veya is_readable kontrolü daha iyi
            if ($cachedData !== false) {
                $unserializedData = @unserialize($cachedData); // Aynı şekilde hata kontrolü
                if ($unserializedData !== false) {
                    return $unserializedData;
                }
                // Unserialize başarısız olursa, cache dosyasını bozuk kabul et ve sil.
                @unlink($cacheFile);
            }
        }

        $menuData = $this->fetchAndBuildMenuFromDb($user_id, $roleIds); // roleIds parametresini ekledim


        // Sadece geçerli bir kiracı varsa ve dizin oluşturulmuşsa cache'e yaz
        // (Bu kontrol yukarıda yapıldığı için burada sadece file_put_contents yeterli olabilir,
        // ama iki kez kontrol etmek zarar vermez)
        if (is_writable($this->ownerSpecificCacheDir)) { // Yazılabilir olup olmadığını kontrol et
            $written = @file_put_contents($cacheFile, serialize($menuData));
            if ($written === false) {
                // Cache yazma hatası loglanabilir.
            }
        }

        return $menuData;
    }

    private function normalizeRoleIds($roleIds): array
    {
        if (is_string($roleIds)) {
            $parts = explode(',', $roleIds);
        } else {
            $parts = [$roleIds];
        }

        $clean = [];
        foreach ($parts as $part) {
            $id = (int) trim((string) $part);
            if ($id > 0) {
                $clean[] = $id;
            }
        }

        $clean = array_values(array_unique($clean));
        sort($clean);
        return $clean;
    }

    private function buildMenuCacheKey(array $roleIdArray): string
    {
        $rolePart = implode('-', $roleIdArray);
        $permissionHash = 'none';

        if (!empty($roleIdArray)) {
            $placeholders = implode(',', array_fill(0, count($roleIdArray), '?'));
            $sql = "SELECT GROUP_CONCAT(DISTINCT permission_id ORDER BY permission_id SEPARATOR ',')
                    FROM user_role_permissions
                    WHERE role_id IN ({$placeholders})";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($roleIdArray);
            $permissionList = (string) ($stmt->fetchColumn() ?: '');
            $permissionHash = md5($permissionList);
        }

        return $rolePart . '_' . $permissionHash;
    }

    // Veritabanından menüyü çekip oluşturan yardımcı fonksiyon
    private function fetchAndBuildMenuFromDb(int $user_id, $roleIds = null): array
    {
        if ($roleIds === null) {
            $Users = new UserModel();
            $roleIds = $Users->getUserRoleID($user_id);
        }

        if (empty($roleIds)) {
            return []; // Geçerli rol yoksa boş menü
        }

        // $roleIds string (örn: "11,2") veya int olabilir.
        if (is_string($roleIds)) {
            $roleIdArray = explode(',', $roleIds);
        } else {
            $roleIdArray = [$roleIds];
        }

        $rolePlaceholders = implode(',', array_fill(0, count($roleIdArray), '?'));
        $isSuperAdmin = $this->isUserSuperAdmin($user_id);
        $superadminFilter = $isSuperAdmin ? "" : " AND (p.superadmin IS NULL OR p.superadmin = 0) ";
        $superadminFilter0 = $isSuperAdmin ? "" : " AND (p0.superadmin IS NULL OR p0.superadmin = 0) ";

        $sql = "SELECT DISTINCT m.id
                FROM {$this->table} m
                WHERE (
                    EXISTS (
                        SELECT 1
                        FROM permissions p
                        INNER JOIN user_role_permissions urp ON urp.permission_id = p.id
                        WHERE urp.role_id IN ({$rolePlaceholders})
                          AND (p.id = m.id OR p.name = m.menu_link OR p.auth_name = m.menu_link OR (m.menu_link = 'kullanici-gruplari/list' AND p.auth_name = 'yetki_gruplari_izleme'))
                          {$superadminFilter}
                    )
                    OR (
                        NOT EXISTS (
                            SELECT 1
                            FROM permissions p0
                            WHERE (p0.id = m.id OR p0.name = m.menu_link OR p0.auth_name = m.menu_link OR (m.menu_link = 'kullanici-gruplari/list' AND p0.auth_name = 'yetki_gruplari_izleme'))
                              {$superadminFilter0}
                        )
                        AND EXISTS (
                            SELECT 1
                            FROM user_role_permissions urp
                            WHERE urp.role_id IN ({$rolePlaceholders})
                              AND urp.permission_id = m.id
                        )
                    )
                )";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge($roleIdArray, $roleIdArray));
        $permittedMenuIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($permittedMenuIds)) {
            return [];
        }

        $allRequiredIds = $this->fetchParentMenuIds($permittedMenuIds);

        if (empty($allRequiredIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($allRequiredIds), '?'));
        $sql = "SELECT * FROM {$this->table} as m
                WHERE m.is_active = 1 AND m.id IN ({$placeholders})
                ORDER BY m.group_order, m.menu_order";
        //sql çıktısını göster 

        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_values($allRequiredIds)); // array_values burada da önemli
        $accessibleMenus = $stmt->fetchAll(PDO::FETCH_OBJ);

        return $this->buildMenuTree($accessibleMenus);
    }


    private function fetchParentMenuIds(array $menuIds): array
    {
        if (empty($menuIds)) {
            return [];
        }
        $allIds = array_values($menuIds); // Başlangıçta da indeksleri düzelt
        $idsToCheck = array_values($menuIds);

        while (!empty($idsToCheck)) {
            $placeholders = implode(',', array_fill(0, count($idsToCheck), '?'));
            $stmt = $this->db->prepare("SELECT DISTINCT parent_id FROM {$this->table} WHERE id IN ({$placeholders}) AND parent_id != 0");
            $stmt->execute($idsToCheck); // array_values($idsToCheck) zaten $idsToCheck'in kendisi döngü içinde düzgün
            $parentIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $newParentIds = array_diff($parentIds, $allIds);

            if (empty($newParentIds)) {
                break;
            }

            $allIds = array_merge($allIds, $newParentIds);
            $idsToCheck = array_values($newParentIds); // Bir sonraki iterasyon için önemli
        }

        return array_values(array_unique($allIds)); // Sonuçta da indeksleri düzelt
    }

    // Mevcut kiracının TÜM menü önbelleklerini temizler
    public function clearAllMenuCachesForCurrentTenant(): void
    {
        if ($this->ownerId === 0 || empty($this->ownerSpecificCacheDir) || !is_dir($this->ownerSpecificCacheDir)) {
            return;
        }

        $pattern = $this->ownerSpecificCacheDir . '/menu_role_*.cache';
        $files = glob($pattern);

        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file); // Hata kontrolü eklenebilir
            }
        }
    }

    // Mevcut kiracının belirli bir rolünün menü önbelleğini temizler
    // Not: Birden fazla rol desteği geldiği için, bir rol değiştiğinde 
    // o rolü içeren tüm kombinasyonları temizlemek yerine tüm kiracı önbelleğini temizlemek daha güvenlidir.
    public function clearMenuCacheForRole(int $roleId): void
    {
        $this->clearAllMenuCachesForCurrentTenant();
    }

    private function buildMenuTree(array $items): array
    {
        $structuredMenu = [];
        $itemsById = [];

        foreach ($items as $item) {
            $item->children = []; // Her öğeye çocuk dizisi ekle
            $itemsById[$item->id] = $item;
        }

        foreach ($items as $item) {
            if ($item->parent_id != 0 && isset($itemsById[$item->parent_id])) {
                $itemsById[$item->parent_id]->children[] = $itemsById[$item->id]; // Doğru referansla ekle
            } else {
                // Grup adına göre gruplandırma
                if (!isset($structuredMenu[$item->group_name])) {
                    $structuredMenu[$item->group_name] = [];
                }
                $structuredMenu[$item->group_name][] = $itemsById[$item->id];
            }
        }

        //echo Helper::dd($structuredMenu);
        return $structuredMenu;
    }

    public function getMenuByLink(string $link): ?object
    {
        // 1. Tam link eşleşmesi ara
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE menu_link = ? LIMIT 1");
        $stmt->execute([$link]);
        $result = $stmt->fetch(PDO::FETCH_OBJ);
        if ($result) {
            return $result;
        }

        // 2. Alt rotalar için modül öneki eşleşmesi (örn: personel/manage -> personel/list)
        $parts = explode('/', $link);
        if (count($parts) > 1) {
            $prefix = $parts[0] . '/';
            $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE menu_link LIKE ? AND is_active = 1 ORDER BY menu_order ASC LIMIT 1");
            $stmt->execute([$prefix . '%']);
            $result = $stmt->fetch(PDO::FETCH_OBJ);
            if ($result) {
                return $result;
            }
        }

        return null;
    }

    public function getActiveMenuIds(?object $currentMenu): array
    {
        $activeIds = [];
        if (!$currentMenu) {
            return $activeIds;
        }

        $activeIds[] = (int) $currentMenu->id;
        $parentId = (int) $currentMenu->parent_id;

        while ($parentId != 0) {
            $stmt = $this->db->prepare("SELECT id, parent_id FROM {$this->table} WHERE id = ?");
            $stmt->execute([$parentId]);
            $parent = $stmt->fetch(PDO::FETCH_OBJ);

            if ($parent) {
                $activeIds[] = (int) $parent->id;
                $parentId = (int) $parent->parent_id;
            } else {
                break;
            }
        }

        return $activeIds;
    }

    /**
     * Kullanıcının, verilen menü linkine erişim yetkisi olup olmadığını kontrol eder.
     * Kontrol user_role_permissions.permission_id -> menus.id eşleşmesi üzerinden yapılır.
     */
    public function userCanAccessMenuLink(int $userId, string $menuLink): bool
    {
        $menu = $this->getMenuByLink($menuLink);

        $users = new UserModel();
        $roleIds = $users->getUserRoleID($userId);

        if (empty($roleIds)) {
            return false;
        }

        $roleIdArray = is_string($roleIds) ? array_filter(explode(',', $roleIds)) : [(string) $roleIds];

        if (empty($roleIdArray)) {
            return false;
        }

        if (!$menu) {
            $placeholders = implode(',', array_fill(0, count($roleIdArray), '?'));

            $hasPermissionSql = "SELECT COUNT(*)
                                 FROM permissions p
                                 INNER JOIN user_role_permissions urp ON urp.permission_id = p.id
                                 WHERE urp.role_id IN ({$placeholders})
                                   AND (p.name = ? OR p.auth_name = ?)";

            $hasPermissionStmt = $this->db->prepare($hasPermissionSql);
            $hasPermissionParams = array_merge(array_values($roleIdArray), [$menuLink, $menuLink]);
            $hasPermissionStmt->execute($hasPermissionParams);

            if ((int) $hasPermissionStmt->fetchColumn() > 0) {
                return true;
            }

            $permissionExistsStmt = $this->db->prepare("SELECT COUNT(*) FROM permissions WHERE name = ? OR auth_name = ?");
            $permissionExistsStmt->execute([$menuLink, $menuLink]);
            if ((int) $permissionExistsStmt->fetchColumn() > 0) {
                return false;
            }

            return true;
        }

                $placeholders = implode(',', array_fill(0, count($roleIdArray), '?'));
                $isSuperAdmin = $this->isUserSuperAdmin($userId);
                $superadminFilter = $isSuperAdmin ? "" : " AND (p.superadmin IS NULL OR p.superadmin = 0) ";
                $superadminFilter0 = $isSuperAdmin ? "" : " AND (p0.superadmin IS NULL OR p0.superadmin = 0) ";

                $sql = "SELECT COUNT(*)
                                FROM {$this->table} m
                                WHERE m.id = ?
                                    AND (
                                        EXISTS (
                                                SELECT 1
                                                FROM permissions p
                                                INNER JOIN user_role_permissions urp ON urp.permission_id = p.id
                                                WHERE urp.role_id IN ({$placeholders})
                                                    AND (p.id = m.id OR p.name = m.menu_link OR p.auth_name = m.menu_link OR (m.menu_link = 'kullanici-gruplari/list' AND p.auth_name = 'yetki_gruplari_izleme'))
                                                    {$superadminFilter}
                                        )
                                        OR (
                                                NOT EXISTS (
                                                        SELECT 1
                                                        FROM permissions p0
                                                        WHERE (p0.id = m.id OR p0.name = m.menu_link OR p0.auth_name = m.menu_link OR (m.menu_link = 'kullanici-gruplari/list' AND p0.auth_name = 'yetki_gruplari_izleme'))
                                                          {$superadminFilter0}
                                                )
                                                AND EXISTS (
                                                        SELECT 1
                                                        FROM user_role_permissions urp
                                                        WHERE urp.role_id IN ({$placeholders})
                                                            AND urp.permission_id = m.id
                                                )
                                        )
                                    )";

                $stmt = $this->db->prepare($sql);
                $params = array_merge([(int) $menu->id], array_values($roleIdArray), array_values($roleIdArray));
                $stmt->execute($params);

                return (int) $stmt->fetchColumn() > 0;
    }

    private function isUserSuperAdmin(int $userId): bool
    {
        $stmt = $this->db->prepare("SELECT role, roles FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_OBJ);

        if (!$user) {
            return false;
        }

        if (($user->role ?? '') === 'superadmin') {
            return true;
        }

        $roleIdsStr = $user->roles ?? '';
        if (!empty($roleIdsStr)) {
            $roleIds = array_filter(array_map('intval', explode(',', $roleIdsStr)));
            if (!empty($roleIds)) {
                $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
                $stmtSuper = $this->db->prepare("SELECT COUNT(*) FROM user_roles WHERE id IN ($placeholders) AND (role_type = 'superadmin' OR role_name = 'Süper Admin')");
                $stmtSuper->execute($roleIds);
                if ((int) $stmtSuper->fetchColumn() > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Kullanıcının favori menü ID'lerini döndürür.
     */
    public function getFavoriteMenuIds(int $userId): array
    {
        $stmt = $this->db->prepare("SELECT menu_id FROM user_favorites WHERE user_id = ? ORDER BY sort_order ASC, id ASC");
        $stmt->execute([$userId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    /**
     * Kullanıcının favori menülerini (hiyerarşik değil, düz liste) sıralı olarak döndürür.
     */
    public function getFavoriteMenus(int $userId): array
    {
        $sql = "SELECT m.*, uf.sort_order as fav_sort, uf.id as fav_id 
                FROM {$this->table} m 
                INNER JOIN user_favorites uf ON uf.menu_id = m.id 
                WHERE uf.user_id = ? AND m.is_active = 1 
                ORDER BY uf.sort_order ASC, uf.id ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        $menus = $stmt->fetchAll(PDO::FETCH_OBJ) ?: [];

        if (empty($menus)) {
            return [];
        }

        // Kullanıcının erişim izni olan menüleri filtrele
        $allowedMenus = [];
        foreach ($menus as $m) {
            if (empty($m->link) || $this->userCanAccessMenuLink($userId, $m->link)) {
                $allowedMenus[] = $m;
            }
        }

        return $allowedMenus;
    }

    /**
     * Favori menü durumunu değiştirir (varsa siler, yoksa ekler).
     */
    public function toggleFavorite(int $userId, int $menuId): bool
    {
        $stmt = $this->db->prepare("SELECT id FROM user_favorites WHERE user_id = ? AND menu_id = ?");
        $stmt->execute([$userId, $menuId]);
        $exists = $stmt->fetch();

        $menu = $this->find($menuId);
        $menuTitle = $menu->title ?? "Menü #{$menuId}";

        if ($exists) {
            $stmt = $this->db->prepare("DELETE FROM user_favorites WHERE user_id = ? AND menu_id = ?");
            $result = $stmt->execute([$userId, $menuId]);
            if ($result) {
                try {
                    $log = new SystemLogModel();
                    $log->logAction($userId, 'Favori Menü', "'{$menuTitle}' favorilerden çıkarıldı.");
                } catch (\Exception $e) {}
            }
            return $result;
        } else {
            $stmt = $this->db->prepare("INSERT INTO user_favorites (user_id, menu_id) VALUES (?, ?)");
            $result = $stmt->execute([$userId, $menuId]);
            if ($result) {
                try {
                    $log = new SystemLogModel();
                    $log->logAction($userId, 'Favori Menü', "'{$menuTitle}' favorilere eklendi.");
                } catch (\Exception $e) {}
            }
            return $result;
        }
    }
}

