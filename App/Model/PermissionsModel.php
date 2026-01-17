<?php 
namespace App\Model;

use App\Model\Model;
use PDO;

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
            'Ana Sayfa'              => 'home',
            'Personel Yönetim'       => 'users',
            'Şube Yönetimi'          => 'git-branch',
            'Temsilcilik Yönetimi'   => 'git-branch',
            'Finans Yönetimi'        => 'credit-card',
            'Evrak Yönetimi'         => 'file-text',
            'Kullanıcı Yönetimi'     => 'users',
            'Demirbaş Yönetimi'      => 'box',
            'Tanımlamalar'           => 'book-open',
            'Mail & Sms Yönetimi'    => 'send',
            'Rehber Yönetimi'        => 'book',
            'Ayarlar'                => 'settings',
            'Slider Yönetimi'        => 'chevrons-right',
            'Site Yönetimi'          => 'layout',
            'default'                =>  'layout'
        ];

        foreach ($flatPermissions as $permission) {
            $groupName = $permission->group_name;

            // Eğer bu grup daha önce işlenmediyse, ana grup yapısını oluştur.
            if (!isset($groupIndexMap[$groupName])) {
                $groupIndexMap[$groupName] = count($groupedPermissions); // Yeni grubun index'ini kaydet
                $groupedPermissions[] = [
                    'id'      => $nextGroupId++,
                    'name'    => $groupName,
                    'icon'    => $iconMap[$groupName] ?? $iconMap['default'],
                    'group'   => $this->slugify($groupName),
                    'permissions' => []
                ];
            }

            // Mevcut izni, doğru grubun 'permissions' dizisine ekle.
            $index = $groupIndexMap[$groupName];
            $groupedPermissions[$index]['permissions'][] = [
                'id'          => (int)$permission->id,
                'name'        => $permission->name,
                'description' => $permission->description ?? '',
                'level'       => (int)$permission->permission_level,
                'required'    => (bool)$permission->is_required
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
                ORDER BY group_name, id";
        $stmt = $this->db->query($sql);
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
        return $result !== false ? (int)$result : null;
    }
}