<?php

namespace App\Model;

use PDO;

class KasaHareketModel extends Model
{
    protected $table = "kasa_hareketleri";
    public function __construct()
    {
        parent::__construct($this->table);
    }

    /** Gelen ID'ye göre kasa hareketlerini getirir.
     * @param int $id
     * @return array
     */
    public function getKasaHareketByKasa($id)
    {
        $sql = "SELECT * FROM $this->table WHERE kasa_id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(["id" => $id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
