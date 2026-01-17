<?php

namespace App\Model;

use App\Model\Model;
use PDO;

class PersonelKesintileriModel extends Model
{
    protected $table = 'personel_kesintileri';
    protected $primaryKey = 'id';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    public function getPersonelKesintileri($personel_id)
    {
        $sql = $this->db->prepare("
            SELECT pk.*, pi.dosya_no, pi.icra_dairesi 
            FROM {$this->table} pk
            LEFT JOIN personel_icralari pi ON pk.icra_id = pi.id
            WHERE pk.personel_id = ? AND pk.silinme_tarihi IS NULL 
            ORDER BY pk.donem DESC, pk.created_at DESC
        ");
        $sql->execute([$personel_id]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }
}
