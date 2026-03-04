<?php
namespace App\Model;

use App\Model\Model;
use PDO;
use App\Helper\Helper;

class PermissionsModel extends Model
{
    protected $table = 'permissions';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /**
     * Veritabanındaki tüm izinleri alır ve istenen gruplanmış/hiyerarşik yapıya dönüştürür.
     * Bu ana metot, tüm süreci yönetir.
     *
     * @return array
     */
    public function getGroupedPermissions(): array
    {
        // 1. Adım: Tüm izinleri veritabanından tek bir sorguyla al.
        $flatPermissions = $this->fetchAllPermissionsFromDb();

        if (empty($flatPermissions)) {
            return [];
        }

        // 2. Adım: Alınan düz veriyi hiyerarşik yapıya dönüştür.
        $groupedPermissions = [];
        $groupIndexMap = []; // Hangi grubun hangi index'te olduğunu tutan performans haritası
        $nextGroupId = 1;

        // Veritabanında olmayan ikonları burada eşleştiriyoruz.
        $iconMap = [
            'Ana Sayfa' => 'home',
            'Personel Yönetim' => 'users',
            'Şube Yönetimi' => 'git-branch',
            'Temsilcilik Yönetimi' => 'git-branch',
            'Finans Yönetimi' => 'credit-card',
            'Evrak Yönetimi' => 'file-text',
            'Kullanıcı Yönetimi' => 'users',
            'Demirbaş Yönetimi' => 'box',
            'Tanımlamalar' => 'book-open',
            'Mail & Sms Yönetimi' => 'send',
            'Rehber Yönetimi' => 'book',
            'Ayarlar' => 'settings',
            'default' => 'layout'
        ];

        foreach ($flatPermissions as $permission) {
            $groupName = $permission->group_name;

            // Eğer bu grup daha önce işlenmediyse, ana grup yapısını oluştur.
            if (!isset($groupIndexMap[$groupName])) {
                $groupIndexMap[$groupName] = count($groupedPermissions); // Yeni grubun index'ini kaydet
                $groupedPermissions[] = [
                    'id' => $nextGroupId++,
                    'name' => $groupName,
                    'icon' => $iconMap[$groupName] ?? $iconMap['default'],
                    'group' => $this->slugify($groupName),
                    'permissions' => []
                ];
            }

            // Mevcut izni, doğru grubun 'permissions' dizisine ekle.
            $index = $groupIndexMap[$groupName];
            $groupedPermissions[$index]['permissions'][] = [
                'id' => (int) $permission->id,
                'name' => $permission->name,
                'description' => $permission->description ?? '',
                'level' => (int) $permission->permission_level,
                'required' => (bool) $permission->is_required
            ];
        }

        return $groupedPermissions;
    }

    /**
     * Veritabanından tüm izinleri tek bir sorguyla çeker.
     * Gruplama mantığının doğru çalışması için group_name'e göre sıralamak önemlidir.
     * @return array
     */
    private function fetchAllPermissionsFromDb(): array
    {
        $sql = "SELECT id, name, description, group_name, permission_level, is_required
                FROM {$this->table} 
                WHERE is_active = ?
                ORDER BY group_name, id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([1]);

        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Bir metni URL dostu bir "slug" formatına dönüştürür.
     * @param string $text
     * @return string
     */
    private function slugify(string $text): string
    {
        $text = str_replace(
            ['ı', 'ğ', 'ü', 'ş', 'ö', 'ç', 'İ', 'Ğ', 'Ü', 'Ş', 'Ö', 'Ç'],
            ['i', 'g', 'u', 's', 'o', 'c', 'i', 'g', 'u', 's', 'o', 'c'],
            $text
        );
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = trim($text, '-');
        $text = strtolower($text);
        if (empty($text)) {
            return 'n-a';
        }
        return $text;
    }

    /**
     * Sayfa adından sayfa ID'sini alır. (Mevcut metodunuz)
     * @param string $pageName Sayfa adı
     * @return int|null
     */
    public function getPageIDByName($pageName): ?int
    {
        $sql = $this->db->prepare("SELECT id FROM $this->table WHERE name = ?");
        $sql->execute([$pageName]);
        $result = $sql->fetchColumn();
        return $result !== false ? (int) $result : null;
    }

    /**
     * Menü linkine göre sayfa için kullanılacak auth_name değerini döndürür.
     * Öncelik: permissions.id = menus.id eşleşmesi, ardından permissions.name = menus.menu_name eşleşmesi.
     */
    public function getAuthNameByMenuLink(string $menuLink): ?string
    {
        $sql = "SELECT p.auth_name
                FROM menus m
                INNER JOIN permissions p
                    ON (p.id = m.id OR p.name = m.menu_name)
                WHERE m.menu_link = ?
                  AND p.is_active = 1
                ORDER BY (p.id = m.id) DESC
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$menuLink]);

        $authName = $stmt->fetchColumn();
        return $authName !== false ? (string) $authName : null;
    }



    /**
     * Bir kullanıcının ID'sine göre, rolü üzerinden sahip olduğu tüm izinlerin adlarını
     * içeren düz bir dizi döndürür.
     * 
     * @param int $userId
     * @return array Örnek: ['kullanici_listele', 'kullanici_ekle', 'rapor_goruntule']
     */
    public function getPermissionsForUser(int $userId): array
    {
        // 1. Kullanıcının rol ID'sini al.
        // Eğer getUserRoleID metodu zaten varsa, onu kullanın.
        // Yoksa aşağıdaki gibi bir sorgu yazılabilir.
        $stmt = $this->db->prepare("SELECT roles FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $roleId = $stmt->fetchColumn();

        if (empty($roleId)) {
            return []; // Rolü olmayan kullanıcının izni yoktur.
        }

        // 2. Rol ID'sine göre tüm izin adlarını çek.
        $roleIds = explode(',', $roleId);
        $placeholders = implode(',', array_fill(0, count($roleIds), '?'));

        $sql = "SELECT DISTINCT p.auth_name
            FROM user_role_permissions urp
            JOIN permissions p ON urp.permission_id = p.id
            WHERE urp.role_id IN ($placeholders)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($roleIds);

        // fetchAll(PDO::FETCH_COLUMN) sadece 'permission_name' sütununu içeren
        // ['izin1', 'izin2', ...] şeklinde düz bir dizi döndürür.
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }



    /**Personelin yetkili olduğu ilk sayfaya yönlendir */
    public function redirectFirstPersmissionPage(): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);
        if (!$userId) {
            return;
        }

        // Kullanıcının rolünü al
        $stmt = $this->db->prepare("SELECT roles FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $roleIdStr = $stmt->fetchColumn();

        if (empty($roleIdStr)) {
            return;
        }

        $roleIds = explode(',', $roleIdStr);
        $placeholders = implode(',', array_fill(0, count($roleIds), '?'));

        // Kullanıcının yetkili olduğu ve menüde karşılığı olan ilk sayfayı bul
        $sql = "SELECT m.menu_link 
                FROM user_role_permissions urp
                JOIN permissions p ON urp.permission_id = p.id
            JOIN menus m ON (m.id = p.id OR m.menu_name = p.name)
                WHERE urp.role_id IN ($placeholders) 
                AND m.menu_link IS NOT NULL 
                AND m.menu_link != ''
            AND m.is_active = 1
            ORDER BY m.group_order, m.menu_order
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($roleIds);
        $link = $stmt->fetchColumn();

        if ($link) {
            $url = "index?p=" . $link;
            if (!headers_sent()) {
                header("Location: " . $url);
                exit;
            } else {
                echo '<script>window.location.href = "' . $url . '";</script>';
                exit;
            }
        }

        if (!headers_sent()) {
            header("Location: /unauthorize.php");
            exit;
        }

        echo '<script>window.location.href = "/unauthorize.php";</script>';
        exit;
    }

}