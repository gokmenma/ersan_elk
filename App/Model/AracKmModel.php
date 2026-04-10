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
    public function all($departman = null)
    {
        $sql = "
            SELECT k.*, 
                   a.plaka, a.marka, a.model,
                   p1.adi_soyadi as sabah_personel,
                   p2.adi_soyadi as aksam_personel,
                   s.sabah_durum,
                   ak.aksam_durum
            FROM {$this->table} k
            INNER JOIN araclar a ON k.arac_id = a.id
            LEFT JOIN (
                SELECT arac_id, tarih, personel_id, durum as sabah_durum
                FROM arac_km_bildirimleri 
                WHERE tur = 'sabah' AND silinme_tarihi IS NULL
                GROUP BY arac_id, tarih
            ) s ON k.arac_id = s.arac_id AND k.tarih = s.tarih
            LEFT JOIN (
                SELECT arac_id, tarih, personel_id, durum as aksam_durum
                FROM arac_km_bildirimleri 
                WHERE tur = 'aksam' AND silinme_tarihi IS NULL
                GROUP BY arac_id, tarih
            ) ak ON k.arac_id = ak.arac_id AND k.tarih = ak.tarih
            LEFT JOIN personel p1 ON s.personel_id = p1.id
            LEFT JOIN personel p2 ON ak.personel_id = p2.id
            WHERE k.firma_id = :firma_id
            AND k.silinme_tarihi IS NULL
        ";
        $params = ['firma_id' => $_SESSION['firma_id']];
        if ($departman) {
            $sql .= " AND a.departmani = :departman";
            $params['departman'] = $departman;
        }
        $sql .= " ORDER BY k.tarih DESC, k.id DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Araca göre KM kayıtları
     */
    public function getByArac($aracId, $limit = null, $departman = null)
    {
        $sql = "
            SELECT k.*, 
                   a.plaka, a.marka, a.model,
                   p1.adi_soyadi as sabah_personel,
                   p2.adi_soyadi as aksam_personel,
                   s.sabah_durum,
                   ak.aksam_durum
            FROM {$this->table} k
            INNER JOIN araclar a ON k.arac_id = a.id
            LEFT JOIN (
                SELECT arac_id, tarih, personel_id, durum as sabah_durum
                FROM arac_km_bildirimleri 
                WHERE tur = 'sabah' AND silinme_tarihi IS NULL
                GROUP BY arac_id, tarih
            ) s ON k.arac_id = s.arac_id AND k.tarih = s.tarih
            LEFT JOIN (
                SELECT arac_id, tarih, personel_id, durum as aksam_durum
                FROM arac_km_bildirimleri 
                WHERE tur = 'aksam' AND silinme_tarihi IS NULL
                GROUP BY arac_id, tarih
            ) ak ON k.arac_id = ak.arac_id AND k.tarih = ak.tarih
            LEFT JOIN personel p1 ON s.personel_id = p1.id
            LEFT JOIN personel p2 ON ak.personel_id = p2.id
            WHERE k.arac_id = :arac_id
            AND k.firma_id = :firma_id
            AND k.silinme_tarihi IS NULL
            ORDER BY k.tarih DESC, k.id DESC
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
     * Tarih aralığına göre KM kayıtları
     */
    public function getByDateRange($baslangic, $bitis, $aracId = null, $departman = null)
    {
        $sql = "
            SELECT k.*, 
                   a.plaka, a.marka, a.model,
                   p1.adi_soyadi as sabah_personel,
                   p2.adi_soyadi as aksam_personel,
                   s.sabah_durum,
                   ak.aksam_durum
            FROM {$this->table} k
            INNER JOIN araclar a ON k.arac_id = a.id
            LEFT JOIN (
                SELECT arac_id, tarih, personel_id, durum as sabah_durum
                FROM arac_km_bildirimleri 
                WHERE tur = 'sabah' AND silinme_tarihi IS NULL
                GROUP BY arac_id, tarih
            ) s ON k.arac_id = s.arac_id AND k.tarih = s.tarih
            LEFT JOIN (
                SELECT arac_id, tarih, personel_id, durum as aksam_durum
                FROM arac_km_bildirimleri 
                WHERE tur = 'aksam' AND silinme_tarihi IS NULL
                GROUP BY arac_id, tarih
            ) ak ON k.arac_id = ak.arac_id AND k.tarih = ak.tarih
            LEFT JOIN personel p1 ON s.personel_id = p1.id
            LEFT JOIN personel p2 ON ak.personel_id = p2.id
            WHERE k.tarih BETWEEN :baslangic AND :bitis
            AND k.firma_id = :firma_id
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

        if ($departman) {
            $sql .= " AND a.departmani = :departman";
            $params['departman'] = $departman;
        }

        $sql .= " ORDER BY k.tarih DESC, k.id DESC";

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
                COUNT(k.id) as kayit_sayisi,
                COALESCE(SUM(CASE WHEN k.yapilan_km > 0 THEN k.yapilan_km ELSE 0 END), 0) as toplam_km,
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
     * Aylık KM özeti (araç bazlı)
     */
    public function getAylikOzet($yil, $ay, $aracId = null, $departman = null)
    {
        $sql = "
            SELECT 
                a.id as arac_id,
                a.plaka,
                a.marka,
                a.model,
                COUNT(k.id) as kayit_sayisi,
                COALESCE(SUM(CASE WHEN k.yapilan_km > 0 THEN k.yapilan_km ELSE 0 END), 0) as toplam_km,
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
     * Günlük KM özeti
     */
    public function getGunlukOzet($tarih, $aracId = null, $departman = null)
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

        if ($departman) {
            $sql .= " AND a.departmani = :departman";
            $params['departman'] = $departman;
        }

        $sql .= " ORDER BY a.plaka";

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
                COALESCE(SUM(CASE WHEN k.yapilan_km > 0 THEN k.yapilan_km ELSE 0 END), 0) as toplam_km,
                COALESCE(AVG(k.yapilan_km), 0) as ortalama_gunluk_km
            FROM {$this->table} k
            INNER JOIN araclar a ON k.arac_id = a.id
            WHERE k.firma_id = :firma_id
            AND k.silinme_tarihi IS NULL
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
            $sql .= " AND k.tarih BETWEEN :baslangic AND :bitis";
            $params['baslangic'] = $baslangic;
            $params['bitis'] = $bitis;
        }

        if ($aracId) {
            $sql .= " AND k.arac_id = :arac_id";
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
     * Belirli bir araç ve tarih için önceki (en yakın) KM kaydını getirir
     */
    public function getOncekiKayit($aracId, $tarih, $excludeId = null)
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE arac_id = :arac_id 
                AND tarih < :tarih 
                AND firma_id = :firma_id 
                AND silinme_tarihi IS NULL";
        $params = [
            'arac_id' => $aracId,
            'tarih' => $tarih,
            'firma_id' => $_SESSION['firma_id']
        ];
        if ($excludeId) {
            $sql .= " AND id != :exclude_id";
            $params['exclude_id'] = $excludeId;
        }
        $sql .= " ORDER BY tarih DESC, id DESC LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Belirli bir araç ve tarih için sonraki (en yakın) KM kaydını getirir
     */
    public function getSonrakiKayit($aracId, $tarih, $excludeId = null)
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE arac_id = :arac_id 
                AND tarih > :tarih 
                AND firma_id = :firma_id 
                AND silinme_tarihi IS NULL";
        $params = [
            'arac_id' => $aracId,
            'tarih' => $tarih,
            'firma_id' => $_SESSION['firma_id']
        ];
        if ($excludeId) {
            $sql .= " AND id != :exclude_id";
            $params['exclude_id'] = $excludeId;
        }
        $sql .= " ORDER BY tarih ASC, id ASC LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Bir KM kaydının başlangıç KM'sini günceller ve yapılan KM'yi yeniden hesaplar
     */
    public function zincirlemeGuncelle($kayitId, $yeniBaslangicKm)
    {
        // Önce mevcut kaydı al
        $kayit = $this->find($kayitId);
        if (!$kayit)
            return false;

        $bitisKm = intval($kayit->bitis_km);
        $yapilanKm = ($bitisKm > 0 && $yeniBaslangicKm > 0) ? ($bitisKm - $yeniBaslangicKm) : 0;

        $sql = "UPDATE {$this->table} 
                SET baslangic_km = :baslangic_km, yapilan_km = :yapilan_km 
                WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'baslangic_km' => $yeniBaslangicKm,
            'yapilan_km' => $yapilanKm,
            'id' => $kayitId
        ]);
    }

    /**
     * Bir araç için en son bitiş KM değerini getirir
     */
    public function getEnSonBitisKm($aracId)
    {
        $sql = "SELECT bitis_km FROM {$this->table} 
                WHERE arac_id = :arac_id 
                AND firma_id = :firma_id 
                AND silinme_tarihi IS NULL 
                AND bitis_km > 0
                ORDER BY tarih DESC, id DESC LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'arac_id' => $aracId,
            'firma_id' => $_SESSION['firma_id']
        ]);
        $result = $stmt->fetch(PDO::FETCH_OBJ);
        return $result ? intval($result->bitis_km) : 0;
    }

    /**
     * Tüm araçlar için en son kaydedilen bitiş KM'lerini getirir
     */
    public function getAllMaxBitisKm()
    {
        $sql = $this->db->prepare("
            SELECT t1.arac_id, t1.bitis_km as max_km 
            FROM {$this->table} t1 
            INNER JOIN (
                SELECT arac_id, MAX(id) as last_id 
                FROM {$this->table} 
                WHERE firma_id = :firma_id 
                AND silinme_tarihi IS NULL 
                AND bitis_km > 0 
                GROUP BY arac_id
            ) t2 ON t1.id = t2.last_id
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
                CASE WHEN k.yapilan_km > 0 THEN k.yapilan_km ELSE 0 END as yapilan_km,
                k.olusturma_tarihi as created_at,
                u.adi_soyadi as giren_kullanici
            FROM araclar a
            LEFT JOIN {$this->table} k ON a.id = k.arac_id 
                AND k.tarih BETWEEN :baslangic AND :bitis
                AND k.silinme_tarihi IS NULL
            LEFT JOIN users u ON k.olusturan_kullanici_id = u.id
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
                    'yapilan' => $row->yapilan_km,
                    'giren_kullanici' => $row->giren_kullanici,
                    'created_at' => $row->created_at
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
            SELECT k.id, k.tarih, DAY(k.tarih) as gun, k.baslangic_km, k.bitis_km, 
                   CASE WHEN k.yapilan_km > 0 THEN k.yapilan_km ELSE 0 END as yapilan_km,
                   k.olusturma_tarihi as created_at, u.adi_soyadi as giren_kullanici
            FROM {$this->table} k
            LEFT JOIN users u ON k.olusturan_kullanici_id = u.id
            WHERE k.arac_id = :arac_id
            AND k.tarih BETWEEN :baslangic AND :bitis
            AND k.firma_id = :firma_id
            AND k.silinme_tarihi IS NULL
            ORDER BY k.tarih ASC
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
                'yapilan' => $row->yapilan_km,
                'giren_kullanici' => $row->giren_kullanici,
                'created_at' => $row->created_at
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
