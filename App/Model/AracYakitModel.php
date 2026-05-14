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
                   a.plaka, a.marka, a.model,
                   (SELECT p2.adi_soyadi 
                    FROM arac_zimmetleri az2 
                    INNER JOIN personel p2 ON az2.personel_id = p2.id
                    WHERE az2.arac_id = y.arac_id 
                    AND az2.zimmet_tarihi <= y.tarih 
                    AND (az2.iade_tarihi IS NULL OR az2.iade_tarihi >= y.tarih)
                    AND az2.durum != 'iptal'
                    AND az2.silinme_tarihi IS NULL
                    ORDER BY az2.id DESC LIMIT 1) as zimmetli_personel
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
                   a.plaka, a.marka, a.model,
                   (SELECT p2.adi_soyadi 
                    FROM arac_zimmetleri az2 
                    INNER JOIN personel p2 ON az2.personel_id = p2.id
                    WHERE az2.arac_id = y.arac_id 
                    AND az2.zimmet_tarihi <= y.tarih 
                    AND (az2.iade_tarihi IS NULL OR az2.iade_tarihi >= y.tarih)
                    AND az2.durum != 'iptal'
                    AND az2.silinme_tarihi IS NULL
                    ORDER BY az2.id DESC LIMIT 1) as zimmetli_personel
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
    public function getByDateRange($baslangic, $bitis, $aracId = null, $departman = null, $personelId = null)
    {
        $sql = "
            SELECT y.*, 
                   a.plaka, a.marka, a.model,
                   (SELECT p2.adi_soyadi 
                    FROM arac_zimmetleri az2 
                    INNER JOIN personel p2 ON az2.personel_id = p2.id
                    WHERE az2.arac_id = y.arac_id 
                    AND az2.zimmet_tarihi <= y.tarih 
                    AND (az2.iade_tarihi IS NULL OR az2.iade_tarihi >= y.tarih)
                    AND az2.durum != 'iptal'
                    AND az2.silinme_tarihi IS NULL
                    ORDER BY az2.id DESC LIMIT 1) as zimmetli_personel
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
        if ($personelId) {
            $sql .= " AND EXISTS (
                SELECT 1 FROM arac_zimmetleri az3
                WHERE az3.arac_id = y.arac_id
                AND az3.personel_id = :personel_id
                AND az3.zimmet_tarihi <= y.tarih
                AND (az3.iade_tarihi IS NULL OR az3.iade_tarihi >= y.tarih)
                AND az3.durum != 'iptal'
                AND az3.silinme_tarihi IS NULL
            )";
            $params['personel_id'] = $personelId;
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
    public function getStats($yil = null, $ay = null, $baslangic = null, $bitis = null, $aracId = null, $departman = null, $personelId = null)
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

        if ($personelId) {
            $sql .= " AND EXISTS (
                SELECT 1 FROM arac_zimmetleri az3
                WHERE az3.arac_id = y.arac_id
                AND az3.personel_id = :personel_id
                AND az3.zimmet_tarihi <= y.tarih
                AND (az3.iade_tarihi IS NULL OR az3.iade_tarihi >= y.tarih)
                AND az3.durum != 'iptal'
                AND az3.silinme_tarihi IS NULL
            )";
            $params['personel_id'] = $personelId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Yakıt kaydı bulunan personelleri getirir
     */
    public function getYakitPersonelleri($departman = null)
    {
        $sql = "
            SELECT DISTINCT p.id, p.adi_soyadi
            FROM personel p
            WHERE p.firma_id = :firma_id
            AND p.silinme_tarihi IS NULL
            AND EXISTS (
                SELECT 1 FROM yakit y
                INNER JOIN arac_zimmetleri az ON y.arac_id = az.arac_id
                WHERE az.personel_id = p.id
                AND az.zimmet_tarihi <= y.tarih 
                AND (az.iade_tarihi IS NULL OR az.iade_tarihi >= y.tarih)
                AND y.silinme_tarihi IS NULL
                AND az.silinme_tarihi IS NULL
                AND az.durum != 'iptal'
            )
        ";
        $params = ['firma_id' => $_SESSION['firma_id']];
        if (!empty($departman)) {
            $sql .= " AND p.departman = :departman";
            $params['departman'] = $departman;
        }
        $sql .= " ORDER BY p.adi_soyadi ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
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
