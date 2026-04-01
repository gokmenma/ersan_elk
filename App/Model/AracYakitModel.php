<?php

namespace App\Model;

use App\Model\Model;
use App\Helper\Security;
use PDO;

class AracYakitModel extends Model
{
    protected $table = 'arac_yakit_kayitlari';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /**
     * Tüm yakıt kayıtlarını getirir
     */
    public function all($departman = null)
    {
        $sql = "
            SELECT y.*, 
                   a.plaka, a.marka, a.model
            FROM {$this->table} y
            INNER JOIN araclar a ON y.arac_id = a.id
            WHERE y.firma_id = :firma_id
            AND y.silinme_tarihi IS NULL
        ";
        $params = ['firma_id' => $_SESSION['firma_id']];
        if ($departman) {
            $sql .= " AND a.departmani = :departman";
            $params['departman'] = $departman;
        }
        $sql .= " ORDER BY y.tarih DESC, y.id DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Araca göre yakıt kayıtları
     */
    public function getByArac($aracId, $limit = null, $departman = null)
    {
        $sql = "
            SELECT y.*, 
                   a.plaka, a.marka, a.model
            FROM {$this->table} y
            INNER JOIN araclar a ON y.arac_id = a.id
            WHERE y.arac_id = :arac_id
            AND y.firma_id = :firma_id
            AND y.silinme_tarihi IS NULL
            ORDER BY y.tarih DESC, y.id DESC
        ";

        $params = [
            'arac_id' => $aracId,
            'firma_id' => $_SESSION['firma_id']
        ];
        if ($departman) {
            $sql .= " AND a.departmani = :departman";
            $params['departman'] = $departman;
        }

        if ($limit) {
            $sql .= " LIMIT " . intval($limit);
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Tarih aralığına göre yakıt kayıtları
     */
    public function getByDateRange($baslangic, $bitis, $aracId = null, $departman = null)
    {
        $sql = "
            SELECT y.*, 
                   a.plaka, a.marka, a.model
            FROM {$this->table} y
            INNER JOIN araclar a ON y.arac_id = a.id
            WHERE y.firma_id = :firma_id
            AND y.tarih BETWEEN :baslangic AND :bitis
            AND y.silinme_tarihi IS NULL
        ";

        $params = [
            'firma_id' => $_SESSION['firma_id'],
            'baslangic' => $baslangic,
            'bitis' => $bitis
        ];

        if ($aracId) {
            $sql .= " AND y.arac_id = :arac_id";
            $params['arac_id'] = $aracId;
        }
        if ($departman) {
            $sql .= " AND a.departmani = :departman";
            $params['departman'] = $departman;
        }

        $sql .= " ORDER BY y.tarih DESC, y.id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Tarih aralığına göre özet (araç bazlı)
     */
    public function getRangeOzet($baslangic, $bitis, $aracId = null, $departman = null)
    {
        $sql = "
            SELECT 
                a.id as arac_id,
                a.plaka,
                a.marka,
                a.model,
                COUNT(y.id) as kayit_sayisi,
                COALESCE(SUM(y.yakit_miktari), 0) as toplam_litre,
                COALESCE(SUM(y.toplam_tutar), 0) as toplam_tutar,
                COALESCE(MAX(y.km) - MIN(y.onceki_km), 0) as toplam_km,
                CASE 
                    WHEN SUM(y.yakit_miktari) > 0 AND (MAX(y.km) - MIN(y.onceki_km)) > 0
                    THEN ROUND((SUM(y.yakit_miktari) / (MAX(y.km) - MIN(y.onceki_km))) * 100, 2)
                    ELSE 0
                END as ortalama_tuketim
            FROM araclar a
            LEFT JOIN {$this->table} y ON a.id = y.arac_id 
                AND y.tarih BETWEEN :baslangic AND :bitis
                AND y.silinme_tarihi IS NULL
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
        if ($departman) {
            $sql .= " AND a.departmani = :departman";
            $params['departman'] = $departman;
        }

        $sql .= " GROUP BY a.id, a.plaka, a.marka, a.model HAVING kayit_sayisi > 0 ORDER BY a.plaka";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Aylık özet (araç bazlı)
     */
    public function getAylikOzet($yil, $ay, $aracId = null, $departman = null)
    {
        $sql = "
            SELECT 
                a.id as arac_id,
                a.plaka,
                a.marka,
                a.model,
                COUNT(y.id) as kayit_sayisi,
                COALESCE(SUM(y.yakit_miktari), 0) as toplam_litre,
                COALESCE(SUM(y.toplam_tutar), 0) as toplam_tutar,
                COALESCE(MAX(y.km) - MIN(y.onceki_km), 0) as toplam_km,
                CASE 
                    WHEN SUM(y.yakit_miktari) > 0 AND (MAX(y.km) - MIN(y.onceki_km)) > 0
                    THEN ROUND((SUM(y.yakit_miktari) / (MAX(y.km) - MIN(y.onceki_km))) * 100, 2)
                    ELSE 0
                END as ortalama_tuketim
            FROM araclar a
            LEFT JOIN {$this->table} y ON a.id = y.arac_id 
                AND YEAR(y.tarih) = :yil 
                AND MONTH(y.tarih) = :ay
                AND y.silinme_tarihi IS NULL
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
        if ($departman) {
            $sql .= " AND a.departmani = :departman";
            $params['departman'] = $departman;
        }

        $sql .= " GROUP BY a.id, a.plaka, a.marka, a.model ORDER BY a.plaka";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Günlük özet (araç bazlı)
     */
    public function getGunlukOzet($tarih, $aracId = null, $departman = null)
    {
        $sql = "
            SELECT 
                a.id as arac_id,
                a.plaka,
                a.marka,
                a.model,
                COUNT(y.id) as kayit_sayisi,
                COALESCE(SUM(y.yakit_miktari), 0) as toplam_litre,
                COALESCE(SUM(y.toplam_tutar), 0) as toplam_tutar
            FROM araclar a
            LEFT JOIN {$this->table} y ON a.id = y.arac_id 
                AND y.tarih = :tarih
                AND y.silinme_tarihi IS NULL
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
        if ($departman) {
            $sql .= " AND a.departmani = :departman";
            $params['departman'] = $departman;
        }

        $sql .= " GROUP BY a.id, a.plaka, a.marka, a.model HAVING kayit_sayisi > 0 ORDER BY a.plaka";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Genel istatistikler
     */
    public function getStats($yil = null, $ay = null, $baslangic = null, $bitis = null, $aracId = null, $departman = null)
    {
        $sql = "
            SELECT 
                COUNT(*) as toplam_kayit,
                COALESCE(SUM(y.yakit_miktari), 0) as toplam_litre,
                COALESCE(SUM(y.brut_tutar), 0) as toplam_brut,
                COALESCE(SUM(y.toplam_tutar), 0) as toplam_tutar,
                COALESCE(AVG(y.birim_fiyat), 0) as ortalama_birim_fiyat
            FROM {$this->table} y
            INNER JOIN araclar a ON y.arac_id = a.id
            WHERE y.firma_id = :firma_id
            AND y.silinme_tarihi IS NULL
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
            $sql .= " AND y.tarih BETWEEN :baslangic AND :bitis";
            $params['baslangic'] = $baslangic;
            $params['bitis'] = $bitis;
        }

        if ($aracId) {
            $sql .= " AND y.arac_id = :arac_id";
            $params['arac_id'] = $aracId;
        }

        if ($departman) {
            $sql .= " AND a.departmani = :departman";
            $params['departman'] = $departman;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Araç için önceki KM değerini getir
     */
    public function getOncekiKm($aracId)
    {
        $sql = $this->db->prepare("
            SELECT km FROM {$this->table}
            WHERE arac_id = :arac_id
            AND firma_id = :firma_id
            AND silinme_tarihi IS NULL
            ORDER BY tarih DESC, id DESC
            LIMIT 1
        ");
        $sql->execute([
            'arac_id' => $aracId,
            'firma_id' => $_SESSION['firma_id']
        ]);
        $result = $sql->fetch(PDO::FETCH_OBJ);
        return $result ? $result->km : null;
    }

    /**
     * Yıllık rapor (aylık bazda)
     */
    public function getYillikRapor($yil)
    {
        $sql = $this->db->prepare("
            SELECT 
                MONTH(tarih) as ay,
                COUNT(*) as kayit_sayisi,
                COALESCE(SUM(yakit_miktari), 0) as toplam_litre,
                COALESCE(SUM(toplam_tutar), 0) as toplam_tutar,
                COALESCE(AVG(birim_fiyat), 0) as ortalama_birim_fiyat
            FROM {$this->table}
            WHERE firma_id = :firma_id
            AND YEAR(tarih) = :yil
            AND silinme_tarihi IS NULL
            GROUP BY MONTH(tarih)
            ORDER BY ay
        ");
        $sql->execute([
            'firma_id' => $_SESSION['firma_id'],
            'yil' => $yil
        ]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }
}
