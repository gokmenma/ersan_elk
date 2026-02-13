<?php

namespace App\Model;

use App\Model\Model;
use PDO;

class EvrakTakipModel extends Model
{
    protected $table = 'evrak_takip';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /**
     * Firma bazlı tüm evrakları getirir
     */
    public function all()
    {
        $sql = $this->db->prepare("
            SELECT et.*, 
                   p.adi_soyadi as personel_adi
            FROM {$this->table} et
            LEFT JOIN personel p ON et.personel_id = p.id
            WHERE et.firma_id = :firma_id 
            AND et.silinme_tarihi IS NULL
            ORDER BY et.tarih DESC, et.id DESC
        ");
        $sql->execute(['firma_id' => $_SESSION['firma_id']]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Tek evrak detayı
     */
    public function getById($id)
    {
        $sql = $this->db->prepare("
            SELECT et.*, 
                   p.adi_soyadi as personel_adi
            FROM {$this->table} et
            LEFT JOIN personel p ON et.personel_id = p.id
            WHERE et.id = :id 
            AND et.firma_id = :firma_id
        ");
        $sql->execute([
            'id' => $id,
            'firma_id' => $_SESSION['firma_id']
        ]);
        return $sql->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Evrak istatistikleri
     */
    public function getStats()
    {
        $sql = $this->db->prepare("
            SELECT 
                COUNT(*) as toplam_evrak,
                SUM(CASE WHEN evrak_tipi = 'gelen' THEN 1 ELSE 0 END) as gelen_evrak,
                SUM(CASE WHEN evrak_tipi = 'giden' THEN 1 ELSE 0 END) as giden_evrak
            FROM {$this->table}
            WHERE firma_id = :firma_id
            AND silinme_tarihi IS NULL
        ");
        $sql->execute(['firma_id' => $_SESSION['firma_id']]);
        return $sql->fetch(PDO::FETCH_OBJ);
    }
}
