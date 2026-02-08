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

        //Helper::dd("Fetching menu for user_id: {$user_id} with role_ids: {$roleIds}");

        $cacheFile = $this->ownerSpecificCacheDir . "/menu_role_{$roleIds}.cache";

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
        $stmt = $this->db->prepare("SELECT DISTINCT permission_id FROM user_role_permissions WHERE role_id IN ({$rolePlaceholders})");
        $stmt->execute($roleIdArray);
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
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE menu_link = ? LIMIT 1");
        $stmt->execute([$link]);
        $result = $stmt->fetch(PDO::FETCH_OBJ);
        return $result ?: null;
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
}
