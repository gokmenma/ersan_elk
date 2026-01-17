<?php

namespace App\Model;

use App\Model\Model;
use PDO;

class PersonelEkOdemelerModel extends Model
{
    protected $table = 'personel_ek_odemeler';
    protected $primaryKey = 'id';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    public function getPersonelEkOdemeler($personel_id)
    {
        $sql = $this->db->prepare("
            SELECT *
            FROM {$this->table}
            WHERE personel_id = ? AND silinme_tarihi IS NULL 
            ORDER BY donem DESC, created_at DESC
        ");
        $sql->execute([$personel_id]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }
}
