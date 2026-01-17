<?php 

namespace App\Model;

use App\Model\Model;
use App\Model\PermissionsModel;
use PDO;


class AuthModel extends Model
{
    protected $table = 'user_role_permissions';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /**
     * Kullanıcının Sayfaya erişim izni olup olmadığını kontrol eder.
     * @param int $userID Kullanıcı ID'si
     * @param string $pageName Erişim kontrolü yapılacak sayfanın adı
     * @return bool
     */
    public function hasAccess($userID, $pageName): bool
    {
        
        // Öncelikle sayfanın ID'sini al
        $permissionsModel = new PermissionsModel();
        $pageID = $permissionsModel->getPageIDByName($pageName);
        $sql = $this->db->prepare("SELECT COUNT(*) FROM $this->table 
                                    WHERE role_id = :user_id 
                                    AND permission_id = :page_id 
                                    ");
        $sql->execute([
            'user_id' => $_SESSION["roles"],
            'page_id' => $pageID
        ]);
        
        return $sql->fetchColumn() > 0;
    }
  
}