<?php

namespace App\Model;

use App\Model\Model;
use PDO;

class PuantajModel extends Model
{
    protected $table = 'yapilan_isler';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    public function getFiltered($startDate, $endDate, $ekipKodu, $workType)
    {
        $sql = "SELECT * FROM $this->table WHERE 1=1";
        $params = [];

        if ($startDate) {
            $sql .= " AND tarih >= ?";
            $params[] = $startDate;
        }
        if ($endDate) {
            $sql .= " AND tarih <= ?";
            $params[] = $endDate;
        }
        if ($ekipKodu) {
            $sql .= " AND ekip_kodu = ?";
            $params[] = $ekipKodu;
        }
        if ($workType) {
            $sql .= " AND is_emri_tipi = ?";
            $params[] = $workType;
        }
        
        $sql .= " ORDER BY tarih DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }
    
    public function getWorkTypes() {
        $stmt = $this->db->query("SELECT DISTINCT is_emri_tipi FROM $this->table WHERE is_emri_tipi IS NOT NULL AND is_emri_tipi != ''");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
