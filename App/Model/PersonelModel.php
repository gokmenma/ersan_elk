<?php

namespace App\Model;

use App\Model\Model;
use PDO;

use App\Helper\Security;

class PersonelModel extends Model
{
    protected $table = 'personel';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /**Tüm aktif personelleri getirir */
    public function all(){
        $query = $this->db->prepare("Select * from $this->table");
        $query->execute();
        return $query->fetchAll(PDO::FETCH_OBJ);

    }

}