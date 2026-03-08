<?php

namespace App\Model;

use App\Model\Model;
use PDO;

class AracServisModel extends Model
{
    protected $table = 'arac_servis_kayitlari';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /**
     * Firma bazlı tüm servis kayıtlarını getirir
     */
    public function all()
    {
        $sql = $this->db->prepare("
            SELECT s.*, a.plaka, a.marka, a.model,
                   s.ikame_arac_id, s.ikame_plaka, s.ikame_marka, s.ikame_model,
                   s.ikame_alis_tarihi, s.ikame_teslim_km, s.ikame_iade_km, s.ikame_iade_tarihi
            FROM {$this->table} s
            INNER JOIN araclar a ON s.arac_id = a.id
            WHERE s.firma_id = :firma_id 
            AND s.silinme_tarihi IS NULL
            ORDER BY s.servis_tarihi DESC
        ");
        $sql->execute(['firma_id' => $_SESSION['firma_id']]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Belirli bir aracın servis kayıtlarını getirir
     */
    public function getByArac($aracId)
    {
        $sql = $this->db->prepare("
            SELECT s.*, a.plaka, a.marka, a.model,
                   s.ikame_arac_id, s.ikame_plaka, s.ikame_marka, s.ikame_model,
                   s.ikame_alis_tarihi, s.ikame_teslim_km, s.ikame_iade_km, s.ikame_iade_tarihi
            FROM {$this->table} s
            INNER JOIN araclar a ON s.arac_id = a.id
            WHERE s.arac_id = :arac_id 
            AND s.firma_id = :firma_id
            AND s.silinme_tarihi IS NULL
            ORDER BY s.servis_tarihi DESC
        ");
        $sql->execute([
            'arac_id' => $aracId,
            'firma_id' => $_SESSION['firma_id']
        ]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Tarih aralığına göre servis kayıtlarını getirir
     */
    public function getByDateRange($baslangic, $bitis, $aracId = null)
    {
        $sqlStr = "SELECT s.*, a.plaka, a.marka, a.model,
                          s.ikame_arac_id, s.ikame_plaka, s.ikame_marka, s.ikame_model,
                          s.ikame_alis_tarihi, s.ikame_teslim_km, s.ikame_iade_km, s.ikame_iade_tarihi
                  FROM {$this->table} s
                  INNER JOIN araclar a ON s.arac_id = a.id
                  WHERE s.firma_id = :firma_id 
                  AND s.silinme_tarihi IS NULL
                  AND s.servis_tarihi BETWEEN :baslangic AND :bitis";

        $params = [
            'firma_id' => $_SESSION['firma_id'],
            'baslangic' => $baslangic,
            'bitis' => $bitis
        ];

        if ($aracId) {
            $sqlStr .= " AND s.arac_id = :arac_id";
            $params['arac_id'] = $aracId;
        }

        $sqlStr .= " ORDER BY s.servis_tarihi DESC";

        $sql = $this->db->prepare($sqlStr);
        $sql->execute($params);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Servis istatistikleri
     */
    public function getStats($yil = null, $ay = null, $baslangic = null, $bitis = null, $aracId = null)
    {
        $sqlStr = "SELECT 
                    COUNT(*) as toplam_kayit,
                    SUM(tutar) as toplam_maliyet,
                    (SELECT COUNT(DISTINCT arac_id) FROM {$this->table} WHERE iade_tarihi IS NULL AND silinme_tarihi IS NULL AND firma_id = :firma_id_inner) as servisteki_arac_sayisi
                  FROM {$this->table}
                  WHERE firma_id = :firma_id
                  AND silinme_tarihi IS NULL";

        $params = [
            'firma_id' => $_SESSION['firma_id'],
            'firma_id_inner' => $_SESSION['firma_id']
        ];

        if ($yil) {
            $sqlStr .= " AND YEAR(servis_tarihi) = :yil";
            $params['yil'] = $yil;
        }
        if ($ay) {
            $sqlStr .= " AND MONTH(servis_tarihi) = :ay";
            $params['ay'] = $ay;
        }
        if ($baslangic) {
            $sqlStr .= " AND servis_tarihi >= :baslangic";
            $params['baslangic'] = $baslangic;
        }
        if ($bitis) {
            $sqlStr .= " AND servis_tarihi <= :bitis";
            $params['bitis'] = $bitis;
        }
        if ($aracId) {
            $sqlStr .= " AND arac_id = :arac_id";
            $params['arac_id'] = $aracId;
        }

        $sql = $this->db->prepare($sqlStr);
        $sql->execute($params);
        return $sql->fetch(PDO::FETCH_OBJ);
    }
}
