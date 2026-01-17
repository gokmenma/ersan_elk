<?php 

namespace App\Helper;

use App\Core\Db;
use PDO;


class Financial extends Db
{
    protected $table = 'tanimlamalar';

    //Gelir türlerini getir
    public function getGelirTurleri()
    {
        $sql = $this->db->prepare("SELECT * FROM $this->table WHERE type = ?");
        $sql->execute([1]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

  
}