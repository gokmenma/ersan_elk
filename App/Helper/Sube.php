<?php

namespace App\Helper;

use App\Core\Db;
use PDO;
use PDOException;

class Sube extends Db
{

    protected $table = 'subeler';
    //Sube Listesini getir
    
    public function getSubeList()
    {
        $query = $this->db->prepare("SELECT * FROM $this->table");  // Fetch all columns
        $query->execute();
        return $query->fetchAll(PDO::FETCH_OBJ);
    }


    /* Şube id ve Adını options olarak döndürür */
    public function getSubeOptions()
    {
        $query = $this->db->prepare("SELECT id, sube_adi FROM $this->table");
        $query->execute();
        $result = $query->fetchAll(PDO::FETCH_ASSOC);

        $options = [];
        foreach ($result as $row) {
            
            $options[$row["id"]] = $row["sube_adi"];

        }
        return $options;
    }
  

 
}