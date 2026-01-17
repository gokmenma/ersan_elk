<?php

namespace App\Model;

use App\Model\Model;
use PDO;

class PersonelIcralariModel extends Model
{
    protected $table = 'personel_icralari';
    protected $primaryKey = 'id';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    public function getPersonelIcralari($personel_id)
    {
        $sql = $this->db->prepare("
            SELECT * FROM {$this->table} 
            WHERE personel_id = ? AND silinme_tarihi IS NULL 
            ORDER BY created_at DESC
        ");
        $sql->execute([$personel_id]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    public function getDevamEdenIcralar($personel_id)
    {
        $sql = $this->db->prepare("
            SELECT * FROM {$this->table} 
            WHERE personel_id = ? AND durum = 'devam_ediyor' AND silinme_tarihi IS NULL 
            ORDER BY created_at DESC
        ");
        $sql->execute([$personel_id]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }
}
