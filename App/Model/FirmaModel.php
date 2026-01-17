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
    public function all(){
        $query = $this->db->prepare("Select * from $this->table");
        $query->execute();
        return $query->fetchAll(PDO::FETCH_OBJ);

    }

    /**firmaları id ve firma adı olarak option döndürür */
    public function option(){
        $query = $this->db->prepare("Select id, firma_adi from $this->table");
        $query->execute();
        return $query->fetchAll(PDO::FETCH_OBJ);
    }

    /** Firmaları getirir */
    // public function all(){

    // }

}