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
    public function kayitVarMi($aracId, $tarih, $excludeId = null, $includeDeleted = false)
    {
        $sql = "SELECT * FROM {$this->table} WHERE arac_id = :arac_id AND tarih = :tarih AND firma_id = :firma_id";
        if (!$includeDeleted) {
            $sql .= " AND silinme_tarihi IS NULL";
        }
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
    /**
     * Tüm araçlar için en yüksek bitiş KM'lerini getirir
     */
    public function getAllMaxBitisKm()
    {
        $sql = $this->db->prepare("
            SELECT arac_id, MAX(bitis_km) as max_km 
            FROM {$this->table} 
            WHERE firma_id = :firma_id 
            AND silinme_tarihi IS NULL 
            GROUP BY arac_id
        ");
        $sql->execute(['firma_id' => $_SESSION['firma_id']]);
        return $sql->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /**
     * Belirli bir ay için tüm araçların günlük KM verilerini getirir
     */
    public function getMonthlyPuantaj($yil, $ay, $aracId = null)
    {
        $baslangic = "$yil-$ay-01";
        $bitis = date('Y-m-t', strtotime($baslangic));

        $sql = "
            SELECT 
                a.id as arac_id,
                a.plaka,
                a.marka,
                a.model,
                k.id,
                k.tarih,
                DAY(k.tarih) as gun,
                k.baslangic_km,
                k.bitis_km,
                k.yapilan_km
            FROM araclar a
            LEFT JOIN {$this->table} k ON a.id = k.arac_id 
                AND k.tarih BETWEEN :baslangic AND :bitis
                AND k.silinme_tarihi IS NULL
            WHERE a.firma_id = :firma_id
            AND a.silinme_tarihi IS NULL
            AND a.aktif_mi = 1
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

        $sql .= " ORDER BY a.plaka, k.tarih";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $results = $stmt->fetchAll(PDO::FETCH_OBJ);

        $data = [];
        foreach ($results as $row) {
            if (!isset($data[$row->arac_id])) {
                $data[$row->arac_id] = [
                    'info' => [
                        'plaka' => $row->plaka,
                        'marka' => $row->marka,
                        'model' => $row->model
                    ],
                    'gunler' => []
                ];
            }
            if ($row->gun) {
                $data[$row->arac_id]['gunler'][$row->gun] = [
                    'id' => $row->id,
                    'baslangic' => $row->baslangic_km,
                    'bitis' => $row->bitis_km,
                    'yapilan' => $row->yapilan_km
                ];
            }
        }

        return $data;
    }
    /**
     * Belirli bir ay için belirli bir aracın günlük KM verilerini ve şoför bilgisini getirir
     */
    public function getSingleVehicleMonthlyPuantaj($yil, $ay, $aracId)
    {
        $baslangic = "$yil-$ay-01";
        $bitis = date('Y-m-t', strtotime($baslangic));

        // Araç ve Şoför Bilgisi
        $sqlInfo = "
            SELECT a.plaka, a.marka, a.model, a.baslangic_km, p.adi_soyadi as sofor_adi
            FROM araclar a
            LEFT JOIN arac_zimmetleri az ON a.id = az.arac_id 
                AND az.durum = 'aktif' 
                AND az.firma_id = :firma_id
            LEFT JOIN personel p ON az.personel_id = p.id
            WHERE a.id = :arac_id 
            AND a.firma_id = :firma_id
            AND a.silinme_tarihi IS NULL
        ";

        $stmtInfo = $this->db->prepare($sqlInfo);
        $stmtInfo->execute([
            'arac_id' => $aracId,
            'firma_id' => $_SESSION['firma_id']
        ]);
        $info = $stmtInfo->fetch(PDO::FETCH_OBJ);

        if (!$info)
            return null;

        // Günlük Veriler
        $sqlKm = "
            SELECT id, tarih, DAY(tarih) as gun, baslangic_km, bitis_km, yapilan_km
            FROM {$this->table}
            WHERE arac_id = :arac_id
            AND tarih BETWEEN :baslangic AND :bitis
            AND firma_id = :firma_id
            AND silinme_tarihi IS NULL
            ORDER BY tarih ASC
        ";

        $stmtKm = $this->db->prepare($sqlKm);
        $stmtKm->execute([
            'arac_id' => $aracId,
            'baslangic' => $baslangic,
            'bitis' => $bitis,
            'firma_id' => $_SESSION['firma_id']
        ]);
        $kmData = $stmtKm->fetchAll(PDO::FETCH_OBJ);

        $gunler = [];
        foreach ($kmData as $row) {
            $gunler[$row->gun] = [
                'id' => $row->id,
                'baslangic' => $row->baslangic_km,
                'bitis' => $row->bitis_km,
                'yapilan' => $row->yapilan_km
            ];
        }

        return [
            'info' => $info,
            'gunler' => $gunler,
            'yil' => $yil,
            'ay' => $ay,
            'gunSayisi' => date('t', strtotime($baslangic))
        ];
    }
}
