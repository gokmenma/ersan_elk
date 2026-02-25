<?php

namespace App\Model;

use App\Model\Model;
use PDO;

class DemirbasServisModel extends Model
{
    protected $table = 'demirbas_servis_kayitlari';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /**
     * Tüm servis kayıtlarını getirir
     */
    public function all()
    {
        $sql = $this->db->prepare("
            SELECT s.*, d.demirbas_adi, d.demirbas_no, p.adi_soyadi as teslim_eden_adi
            FROM {$this->table} s
            INNER JOIN demirbas d ON s.demirbas_id = d.id
            LEFT JOIN personel p ON s.teslim_eden_personel_id = p.id
            WHERE s.firma_id = :firma_id 
            AND s.silinme_tarihi IS NULL
            ORDER BY s.servis_tarihi DESC
        ");
        $sql->execute(['firma_id' => $_SESSION['firma_id']]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Belirli bir demirbaşın servis kayıtlarını getirir
     */
    public function getByDemirbas($demirbasId)
    {
        $sql = $this->db->prepare("
            SELECT s.*, d.demirbas_adi, d.demirbas_no, p.adi_soyadi as teslim_eden_adi
            FROM {$this->table} s
            INNER JOIN demirbas d ON s.demirbas_id = d.id
            LEFT JOIN personel p ON s.teslim_eden_personel_id = p.id
            WHERE s.demirbas_id = :demirbas_id 
            AND s.firma_id = :firma_id
            AND s.silinme_tarihi IS NULL
            ORDER BY s.servis_tarihi DESC
        ");
        $sql->execute([
            'demirbas_id' => $demirbasId,
            'firma_id' => $_SESSION['firma_id']
        ]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Tarih aralığına göre servis kayıtlarını getirir
     */
    public function getByDateRange($baslangic, $bitis, $demirbasId = null)
    {
        $sqlStr = "SELECT s.*, d.demirbas_adi, d.demirbas_no, p.adi_soyadi as teslim_eden_adi
                  FROM {$this->table} s
                  INNER JOIN demirbas d ON s.demirbas_id = d.id
                  LEFT JOIN personel p ON s.teslim_eden_personel_id = p.id
                  WHERE s.firma_id = :firma_id 
                  AND s.silinme_tarihi IS NULL
                  AND s.servis_tarihi BETWEEN :baslangic AND :bitis";

        $params = [
            'firma_id' => $_SESSION['firma_id'],
            'baslangic' => $baslangic,
            'bitis' => $bitis
        ];

        if ($demirbasId) {
            $sqlStr .= " AND s.demirbas_id = :demirbas_id";
            $params['demirbas_id'] = $demirbasId;
        }

        $sqlStr .= " ORDER BY s.servis_tarihi DESC";

        $sql = $this->db->prepare($sqlStr);
        $sql->execute($params);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Servis istatistikleri
     */
    public function getStats($baslangic = null, $bitis = null)
    {
        $sqlStr = "SELECT 
                    COUNT(*) as toplam_kayit,
                    SUM(tutar) as toplam_maliyet,
                    (SELECT COUNT(DISTINCT demirbas_id) FROM {$this->table} WHERE iade_tarihi IS NULL AND silinme_tarihi IS NULL AND firma_id = :firma_id_inner) as servisteki_sayisi
                  FROM {$this->table}
                  WHERE firma_id = :firma_id
                  AND silinme_tarihi IS NULL";

        $params = [
            'firma_id' => $_SESSION['firma_id'],
            'firma_id_inner' => $_SESSION['firma_id']
        ];

        if ($baslangic) {
            $sqlStr .= " AND servis_tarihi >= :baslangic";
            $params['baslangic'] = $baslangic;
        }
        if ($bitis) {
            $sqlStr .= " AND servis_tarihi <= :bitis";
            $params['bitis'] = $bitis;
        }

        $sql = $this->db->prepare($sqlStr);
        $sql->execute($params);
        return $sql->fetch(PDO::FETCH_OBJ);
    }
}
