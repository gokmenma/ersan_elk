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
    public function all()
    {
        $query = $this->db->prepare("Select * from $this->table where firma_id = :firma_id");
        $query->execute([
            'firma_id' => $_SESSION['firma_id']
        ]);
        return $query->fetchAll(PDO::FETCH_OBJ);

    }

    public function where($column, $value)
    {
        $sql = $this->db->prepare("SELECT * FROM $this->table WHERE $column = ? AND firma_id = ?");
        $sql->execute(array($value, $_SESSION['firma_id']));
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

}