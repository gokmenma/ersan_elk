<?php 


namespace App\Model;

use App\Model\Model;
use PDO;

class KasaModel extends Model
{
    protected $table = 'kasalar';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /** Varsayılan kasa ID'sini döner */
    public function getDefaultCashboxId($owner_id)
    {
        $sql = "SELECT id FROM {$this->table} WHERE owner_id = :owner_id AND varsayilan_mi = 1 LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':owner_id', $owner_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    /**
     * Veri Sahibinin kasa listesini döner
     * @param int $owner_id
     * @return array
     */
    public function getKasaListByOwner($owner_id)
    {
        $sql = "SELECT * FROM {$this->table} WHERE owner_id = :owner_id AND silinme_tarihi IS NULL";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':owner_id', $owner_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /** Kasaları varsayılan olmaktan çıkar */
    public function resetDefaultCashboxesExcept($owner_id)
    {
        $sql = "UPDATE {$this->table} SET varsayilan_mi = 0 WHERE owner_id = :owner_id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':owner_id', $owner_id, PDO::PARAM_INT);
        $stmt->execute();
    }

    /** kasa kodunu kontrol et */
    public function isCashboxCodeExists($owner_id, $hesap_no, $id = null)
    {
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE owner_id = :owner_id AND hesap_no = :hesap_no";
        if ($id) {
            $sql .= " AND id != :id";
        }
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':owner_id', $owner_id, PDO::PARAM_INT);
        $stmt->bindParam(':hesap_no', $hesap_no, PDO::PARAM_STR);
        if ($id) {
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchColumn() > 0;
    }

}
