<?php

namespace App\Model;

use App\Model\Model;
use App\Helper\Security;
use PDO;

class AracKmModel extends Model
{
    protected $table = 'arac_km_kayitlari';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /**
     * Tüm KM kayıtlarını getirir
     */
    public function all()
    {
        $sql = $this->db->prepare("
            SELECT k.*, 
                   a.plaka, a.marka, a.model
            FROM {$this->table} k
            INNER JOIN araclar a ON k.arac_id = a.id
            WHERE k.firma_id = :firma_id
            AND k.silinme_tarihi IS NULL
            ORDER BY k.tarih DESC, k.id DESC
        ");
        $sql->execute(['firma_id' => $_SESSION['firma_id']]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Araca göre KM kayıtları
     */
    public function getByArac($aracId, $limit = null)
    {
        $sql = "
            SELECT k.*, 
                   a.plaka, a.marka, a.model
            FROM {$this->table} k
            INNER JOIN araclar a ON k.arac_id = a.id
            WHERE k.arac_id = :arac_id
            AND k.firma_id = :firma_id
            AND k.silinme_tarihi IS NULL
            ORDER BY k.tarih DESC, k.id DESC
        ";

        if ($limit) {
            $sql .= " LIMIT " . intval($limit);
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'arac_id' => $aracId,
            'firma_id' => $_SESSION['firma_id']
        ]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }
    /**
     * Tarih aralığına göre KM kayıtları
     */
    public function getByDateRange($baslangic, $bitis, $aracId = null)
    {
        $sql = "
            SELECT k.*, 
                   a.plaka, a.marka, a.model
            FROM {$this->table} k
            INNER JOIN araclar a ON k.arac_id = a.id
            WHERE k.firma_id = :firma_id
            AND k.tarih BETWEEN :baslangic AND :bitis
            AND k.silinme_tarihi IS NULL
        ";

        $params = [
            'firma_id' => $_SESSION['firma_id'],
            'baslangic' => $baslangic,
            'bitis' => $bitis
        ];

        if ($aracId) {
            $sql .= " AND k.arac_id = :arac_id";
            $params['arac_id'] = $aracId;
        }

        $sql .= " ORDER BY k.tarih DESC, k.id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Tarih aralığına göre özet (araç bazlı)
     */
    public function getRangeOzet($baslangic, $bitis, $aracId = null)
    {
        $sql = "
            SELECT 
                a.id as arac_id,
                a.plaka,
                a.marka,
                a.model,
                COUNT(k.id) as kayit_sayisi,
                COALESCE(SUM(k.yapilan_km), 0) as toplam_km,
                COALESCE(MIN(k.baslangic_km), 0) as range_baslangic_km,
                COALESCE(MAX(k.bitis_km), 0) as range_bitis_km
            FROM araclar a
            LEFT JOIN {$this->table} k ON a.id = k.arac_id 
                AND k.tarih BETWEEN :baslangic AND :bitis
                AND k.silinme_tarihi IS NULL
            WHERE a.firma_id = :firma_id
            AND a.silinme_tarihi IS NULL
        ";

        $params = [
            'firma_id' => $_SESSION['firma_id'],
            'baslangic' => $baslangic,
            'bitis' => $bitis
        ];

        if ($aracId) {
            $sql .= " AND a.id = :arac_id";
            $params['arac_id'] = $aracId;
        }

        $sql .= " GROUP BY a.id, a.plaka, a.marka, a.model HAVING kayit_sayisi > 0 ORDER BY a.plaka";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Aylık KM özeti (araç bazlı)
     */
    public function getAylikOzet($yil, $ay, $aracId = null)
    {
        $sql = "
            SELECT 
                a.id as arac_id,
                a.plaka,
                a.marka,
                a.model,
                COUNT(k.id) as kayit_sayisi,
                COALESCE(SUM(k.yapilan_km), 0) as toplam_km,
                COALESCE(MIN(k.baslangic_km), 0) as ay_baslangic_km,
                COALESCE(MAX(k.bitis_km), 0) as ay_bitis_km
            FROM araclar a
            LEFT JOIN {$this->table} k ON a.id = k.arac_id 
                AND YEAR(k.tarih) = :yil 
                AND MONTH(k.tarih) = :ay
                AND k.silinme_tarihi IS NULL
            WHERE a.firma_id = :firma_id
            AND a.silinme_tarihi IS NULL
        ";

        $params = [
            'firma_id' => $_SESSION['firma_id'],
            'yil' => $yil,
            'ay' => $ay
        ];

        if ($aracId) {
            $sql .= " AND a.id = :arac_id";
            $params['arac_id'] = $aracId;
        }

        $sql .= " GROUP BY a.id, a.plaka, a.marka, a.model ORDER BY a.plaka";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Günlük KM özeti
     */
    public function getGunlukOzet($tarih, $aracId = null)
    {
        $sql = "
            SELECT 
                a.id as arac_id,
                a.plaka,
                a.marka,
                a.model,
                k.baslangic_km,
                k.bitis_km,
                k.yapilan_km
            FROM araclar a
            LEFT JOIN {$this->table} k ON a.id = k.arac_id 
                AND k.tarih = :tarih
                AND k.silinme_tarihi IS NULL
            WHERE a.firma_id = :firma_id
            AND a.silinme_tarihi IS NULL
        ";

        $params = [
            'firma_id' => $_SESSION['firma_id'],
            'tarih' => $tarih
        ];

        if ($aracId) {
            $sql .= " AND a.id = :arac_id";
            $params['arac_id'] = $aracId;
        }

        $sql .= " ORDER BY a.plaka";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Genel istatistikler
     */
    public function getStats($yil = null, $ay = null, $baslangic = null, $bitis = null, $aracId = null)
    {
        $sql = "
            SELECT 
                COUNT(*) as toplam_kayit,
                COALESCE(SUM(yapilan_km), 0) as toplam_km,
                COALESCE(AVG(yapilan_km), 0) as ortalama_gunluk_km
            FROM {$this->table}
            WHERE firma_id = :firma_id
            AND silinme_tarihi IS NULL
        ";

        $params = ['firma_id' => $_SESSION['firma_id']];

        if ($yil) {
            $sql .= " AND YEAR(tarih) = :yil";
            $params['yil'] = $yil;
        }

        if ($ay) {
            $sql .= " AND MONTH(tarih) = :ay";
            $params['ay'] = $ay;
        }

        if ($baslangic && $bitis) {
            $sql .= " AND tarih BETWEEN :baslangic AND :bitis";
            $params['baslangic'] = $baslangic;
            $params['bitis'] = $bitis;
        }

        if ($aracId) {
            $sql .= " AND arac_id = :arac_id";
            $params['arac_id'] = $aracId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Belirli tarih için kayıt var mı kontrolü
     */
    public function kayitVarMi($aracId, $tarih, $excludeId = null)
    {
        $sql = "SELECT id FROM {$this->table} WHERE arac_id = :arac_id AND tarih = :tarih AND firma_id = :firma_id AND silinme_tarihi IS NULL";
        $params = [
            'arac_id' => $aracId,
            'tarih' => $tarih,
            'firma_id' => $_SESSION['firma_id']
        ];

        if ($excludeId) {
            $sql .= " AND id != :exclude_id";
            $params['exclude_id'] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_OBJ);
    }
}
