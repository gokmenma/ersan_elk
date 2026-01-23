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
        $sql = "SELECT p.*, 
                CASE WHEN ps.id IS NOT NULL THEN 1 ELSE 0 END as bildirim_abonesi
                FROM {$this->table} p 
                LEFT JOIN push_subscriptions ps ON p.id = ps.personel_id
                WHERE p.firma_id = :firma_id
                GROUP BY p.id";

        $query = $this->db->prepare($sql);
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

    /**
     * Aynı ekip kodunda aktif personel var mı kontrol eder
     * @param string $ekip_no Ekip kodu
     * @param int|null $exclude_id Hariç tutulacak personel ID'si (güncelleme işlemlerinde)
     * @return object|null Aktif personel varsa personel bilgisi, yoksa null
     */
    public function getAktifPersonelByEkipNo($ekip_no, $exclude_id = null)
    {
        if (empty($ekip_no)) {
            return null;
        }

        $sql = "SELECT id, adi_soyadi, ekip_no FROM $this->table 
                WHERE ekip_no = ? 
                AND aktif_mi = 1 
                AND firma_id = ?";

        $params = [$ekip_no, $_SESSION['firma_id']];

        if ($exclude_id) {
            $sql .= " AND id != ?";
            $params[] = $exclude_id;
        }

        $sql .= " LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_OBJ);
    }

   

}