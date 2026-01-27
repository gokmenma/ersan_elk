<?php

namespace App\Model;

use App\Model\Model;
use PDO;

use App\Helper\Security;

class FirmaModel extends Model
{
    protected $table = 'firmalar';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /**Tüm aktif firmaları getirir */
    public function all()
    {
        $query = $this->db->prepare("Select * from $this->table");
        $query->execute();
        return $query->fetchAll(PDO::FETCH_OBJ);

    }

    /**firmaları id ve firma adı olarak option döndürür */
    public function option()
    {
        $query = $this->db->prepare("Select id, firma_adi from $this->table 
                                where silinme_tarihi is null");
        $query->execute();
        return $query->fetchAll(PDO::FETCH_OBJ);
    }
    /**firmaları id ve firma adı olarak option döndürür */
    public function optionByUserPermission()
    {
        $query = $this->db->prepare("SELECT id, firma_adi 
                                     FROM $this->table
                                     WHERE FIND_IN_SET(id, (
                                        SELECT firma_ids FROM users WHERE id = :user_id
                                     ))
                                     AND silinme_tarihi IS NULL");
        $query->execute([
            'user_id' => $_SESSION['user']->id
        ]);
        return $query->fetchAll(PDO::FETCH_OBJ);
    }

    /**Firma Listesi */
    public function getFirmaList()
    {
        $query = $this->db->prepare("Select id, firma_adi from $this->table where silinme_tarihi is null");
        $query->execute();
        return $query->fetchAll(PDO::FETCH_OBJ);
    }

    /**Seçilen firmayı varsayılan yapar, diğerlerini sıfırlar */
    public function setDefault($id)
    {
        $query = $this->db->prepare("UPDATE $this->table SET varsayilan_mi = 0");
        $query->execute();

        $query = $this->db->prepare("UPDATE $this->table SET varsayilan_mi = 1 WHERE id = :id");
        return $query->execute(['id' => $id]);
    }

    /** Firmanın personeli veya yetkili kullanıcısı var mı kontrol eder */
    public function hasRelations($id)
    {
        // Personel kontrolü
        $query = $this->db->prepare("SELECT COUNT(*) FROM personel WHERE firma_id = :id AND silinme_tarihi IS NULL");
        $query->execute(['id' => $id]);
        if ($query->fetchColumn() > 0) {
            return "Bu firmaya kayıtlı personel bulunmaktadır. Silme işlemi yapılamaz.";
        }

        // Kullanıcı yetki kontrolü
        $query = $this->db->prepare("SELECT COUNT(*) FROM users WHERE FIND_IN_SET(:id, firma_ids)");
        $query->execute(['id' => $id]);
        if ($query->fetchColumn() > 0) {
            return "Bu firmada yetkili kullanıcılar bulunmaktadır. Silme işlemi yapılamaz.";
        }

        return false;
    }

}