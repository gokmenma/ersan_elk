<?php

namespace App\Model;

use App\Model\Model;
use PDO;

class DuyuruModel extends Model
{
    protected $table = 'duyurular';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    public function getAll($activeOnly = false)
    {
        $sql = "SELECT * FROM {$this->table} WHERE firma_id = :firma_id AND silinme_tarihi IS NULL";

        if ($activeOnly) {
            $sql .= " AND durum = 'Yayında' AND (etkinlik_tarihi IS NULL OR etkinlik_tarihi >= CURDATE())";
        }

        $sql .= " ORDER BY tarih DESC";

        $query = $this->db->prepare($sql);
        $query->execute([
            'firma_id' => $_SESSION['firma_id']
        ]);
        return $query->fetchAll(PDO::FETCH_OBJ);
    }

    public function createDuyuru($data)
    {
        return $this->saveWithAttr($data);
    }

    public function updateDuyuru($id, $data)
    {
        $data['id'] = $id;
        return $this->saveWithAttr($data);
    }

    public function getStats()
    {
        $sql = "SELECT 
                COUNT(*) as toplam,
                SUM(CASE WHEN durum = 'Yayında' AND (etkinlik_tarihi >= CURDATE() OR etkinlik_tarihi IS NULL) AND silinme_tarihi IS NULL THEN 1 ELSE 0 END) as aktif,
                SUM(CASE WHEN durum = 'Yayında' AND etkinlik_tarihi < CURDATE() AND etkinlik_tarihi IS NOT NULL AND silinme_tarihi IS NULL THEN 1 ELSE 0 END) as suresi_dolmus
                FROM {$this->table} 
                WHERE firma_id = ? AND silinme_tarihi IS NULL";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$_SESSION['firma_id']]);
        return $stmt->fetch(PDO::FETCH_OBJ);
    }
}
