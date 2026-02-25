<?php

namespace App\Model;

use App\Helper\Date;
use App\Model\Model;
use PDO;

/**
 * Demirbaş Hareket Model
 * Zimmet, iade, sarf ve kayıp işlemlerini hareket bazlı takip eder
 */
class DemirbasHareketModel extends Model
{
    protected $table = 'demirbas_hareketler';

    // Hareket tipleri
    const HAREKET_ZIMMET = 'zimmet';
    const HAREKET_IADE = 'iade';
    const HAREKET_SARF = 'sarf';
    const HAREKET_KAYIP = 'kayip';
    const HAREKET_DUZELME = 'duzelme';

    // Kaynak tipleri
    const KAYNAK_MANUEL = 'manuel';
    const KAYNAK_PUANTAJ_EXCEL = 'puantaj_excel';
    const KAYNAK_PUANTAJ_ONLINE = 'puantaj_online';
    const KAYNAK_SISTEM = 'sistem';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /**
     * Hareket ekle
     */
    public function hareketEkle($data)
    {
        $sql = $this->db->prepare("
            INSERT INTO {$this->table} 
            (demirbas_id, personel_id, zimmet_id, hareket_tipi, miktar, tarih, islem_id, is_emri_sonucu, aciklama, islem_yapan_id, kaynak)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $sql->execute([
            $data['demirbas_id'],
            $data['personel_id'],
            $data['zimmet_id'] ?? null,
            $data['hareket_tipi'],
            $data['miktar'] ?? 1,
            Date::Ymd($data['tarih'], 'Y-m-d'),
            $data['islem_id'] ?? null,
            $data['is_emri_sonucu'] ?? null,
            $data['aciklama'] ?? null,
            $data['islem_yapan_id'] ?? ($_SESSION['id'] ?? null),
            $data['kaynak'] ?? self::KAYNAK_MANUEL
        ]);

        return $this->db->lastInsertId();
    }

    /**
     * Personelin belirli bir demirbaş için bakiyesini hesapla
     */
    public function getPersonelDemirbasBakiye($personel_id, $demirbas_id)
    {
        $sql = $this->db->prepare("
            SELECT 
                COALESCE(SUM(CASE WHEN hareket_tipi = 'zimmet' THEN miktar ELSE 0 END), 0) as toplam_zimmet,
                COALESCE(SUM(CASE WHEN hareket_tipi IN ('iade', 'sarf', 'kayip') THEN miktar ELSE 0 END), 0) as toplam_cikis,
                COALESCE(SUM(CASE WHEN hareket_tipi = 'zimmet' THEN miktar ELSE 0 END), 0) - 
                COALESCE(SUM(CASE WHEN hareket_tipi IN ('iade', 'sarf', 'kayip') THEN miktar ELSE 0 END), 0) as bakiye
            FROM {$this->table}
            WHERE personel_id = ? AND demirbas_id = ? AND silinme_tarihi IS NULL
        ");
        $sql->execute([$personel_id, $demirbas_id]);
        return $sql->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Personelin tüm demirbaş bakiyelerini getir
     */
    public function getPersonelTumBakiyeler($personel_id)
    {
        $sql = $this->db->prepare("
            SELECT 
                h.demirbas_id,
                d.demirbas_adi,
                d.demirbas_no,
                k.tur_adi as kategori_adi,
                COALESCE(SUM(CASE WHEN h.hareket_tipi = 'zimmet' THEN h.miktar ELSE 0 END), 0) as toplam_zimmet,
                COALESCE(SUM(CASE WHEN h.hareket_tipi IN ('iade', 'sarf', 'kayip') THEN h.miktar ELSE 0 END), 0) as toplam_cikis,
                COALESCE(SUM(CASE WHEN h.hareket_tipi = 'zimmet' THEN h.miktar ELSE 0 END), 0) - 
                COALESCE(SUM(CASE WHEN h.hareket_tipi IN ('iade', 'sarf', 'kayip') THEN h.miktar ELSE 0 END), 0) as bakiye
            FROM {$this->table} h
            INNER JOIN demirbas d ON h.demirbas_id = d.id
            LEFT JOIN tanimlamalar k ON d.kategori_id = k.id AND k.grup = 'demirbas_kategorisi'
            WHERE h.personel_id = ? AND h.silinme_tarihi IS NULL
            GROUP BY h.demirbas_id, d.demirbas_adi, d.demirbas_no, k.kategori_adi
            HAVING bakiye != 0
            ORDER BY d.demirbas_adi
        ");
        $sql->execute([$personel_id]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Demirbaşın hareket geçmişini getir
     */
    public function getDemirbasHareketleri($demirbas_id, $personel_id = null)
    {
        $sql = "
            SELECT 
                h.*,
                p.adi_soyadi as personel_adi,
                d.demirbas_adi,
                u.adi_soyadi as islem_yapan_adi
            FROM {$this->table} h
            INNER JOIN personel p ON h.personel_id = p.id
            INNER JOIN demirbas d ON h.demirbas_id = d.id
            LEFT JOIN personel u ON h.islem_yapan_id = u.id
            WHERE h.demirbas_id = ? AND h.silinme_tarihi IS NULL
        ";
        $params = [$demirbas_id];

        if ($personel_id) {
            $sql .= " AND h.personel_id = ?";
            $params[] = $personel_id;
        }

        $sql .= " ORDER BY h.tarih DESC, h.id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Personelin hareket geçmişini getir
     */
    public function getPersonelHareketleri($personel_id, $demirbas_id = null, $limit = 50, $zimmet_id = null)
    {
        $sql = "
            SELECT 
                h.*,
                d.demirbas_adi,
                d.demirbas_no,
                k.tur_adi as kategori_adi
            FROM {$this->table} h
            INNER JOIN demirbas d ON h.demirbas_id = d.id
            LEFT JOIN tanimlamalar k ON d.kategori_id = k.id AND k.grup = 'demirbas_kategorisi'
            WHERE h.personel_id = :personel_id AND h.silinme_tarihi IS NULL
        ";

        if ($demirbas_id) {
            $sql .= " AND h.demirbas_id = :demirbas_id";
        }

        if ($zimmet_id) {
            $sql .= " AND h.zimmet_id = :zimmet_id";
        }

        $sql .= " ORDER BY h.tarih DESC, h.id DESC LIMIT :limit";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':personel_id', $personel_id, PDO::PARAM_INT);
        if ($demirbas_id) {
            $stmt->bindValue(':demirbas_id', $demirbas_id, PDO::PARAM_INT);
        }
        if ($zimmet_id) {
            $stmt->bindValue(':zimmet_id', $zimmet_id, PDO::PARAM_INT);
        }
        $stmt->bindValue(':limit', (int) $limit, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Belirli bir zimmet kaydına ait hareket geçmişini getir
     */
    public function getZimmetHareketleri($zimmet_id)
    {
        $sql = $this->db->prepare("
            SELECT h.*, u.adi_soyadi as islem_yapan_adi
            FROM {$this->table} h
            LEFT JOIN personel u ON h.islem_yapan_id = u.id
            WHERE h.zimmet_id = ? AND h.silinme_tarihi IS NULL
            ORDER BY h.tarih ASC, h.id ASC
        ");
        $sql->execute([$zimmet_id]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }


    /**
     * İşlem ID'sine göre hareket var mı kontrol et (mükerrer önleme)
     */
    public function islemIdMevcutMu($islem_id, $hareket_tipi, $demirbas_id, $personel_id)
    {
        $sql = $this->db->prepare("
            SELECT id FROM {$this->table} 
            WHERE islem_id = ? AND hareket_tipi = ? AND demirbas_id = ? AND personel_id = ? AND silinme_tarihi IS NULL
            LIMIT 1
        ");
        $sql->execute([$islem_id, $hareket_tipi, $demirbas_id, $personel_id]);
        return $sql->fetch() ? true : false;
    }

    /**
     * Tarih ve personel bazlı manuel hareket var mı kontrol et
     */
    public function manuelHareketVarMi($tarih, $hareket_tipi, $demirbas_id, $personel_id)
    {
        $sql = $this->db->prepare("
            SELECT id FROM {$this->table} 
            WHERE tarih = ? AND hareket_tipi = ? AND demirbas_id = ? AND personel_id = ? 
            AND kaynak = 'manuel' AND silinme_tarihi IS NULL
            LIMIT 1
        ");
        $sql->execute([$tarih, $hareket_tipi, $demirbas_id, $personel_id]);
        return $sql->fetch() ? true : false;
    }

    /**
     * Özet istatistikler
     */
    public function getOzetIstatistikler()
    {
        $sql = $this->db->prepare("
            SELECT 
                COUNT(DISTINCT CASE WHEN hareket_tipi = 'zimmet' THEN CONCAT(personel_id, '-', demirbas_id) END) as aktif_zimmet_sayisi,
                SUM(CASE WHEN hareket_tipi = 'zimmet' THEN miktar ELSE 0 END) as toplam_zimmet_miktar,
                SUM(CASE WHEN hareket_tipi = 'iade' THEN miktar ELSE 0 END) as toplam_iade_miktar,
                SUM(CASE WHEN hareket_tipi = 'sarf' THEN miktar ELSE 0 END) as toplam_sarf_miktar,
                SUM(CASE WHEN hareket_tipi = 'kayip' THEN miktar ELSE 0 END) as toplam_kayip_miktar
            FROM {$this->table}
            WHERE silinme_tarihi IS NULL
        ");
        $sql->execute();
        return $sql->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Hareket tipi badge'i döndür
     */
    public static function getHareketTipiBadge($tip)
    {
        $badges = [
            'zimmet' => '<span class="badge bg-warning">Zimmet</span>',
            'iade' => '<span class="badge bg-success">İade</span>',
            'sarf' => '<span class="badge bg-info">Sarf</span>',
            'kayip' => '<span class="badge bg-danger">Kayıp</span>',
            'duzelme' => '<span class="badge bg-secondary">Düzeltme</span>'
        ];
        return $badges[$tip] ?? '<span class="badge bg-dark">Bilinmiyor</span>';
    }

    /**
     * Kaynak badge'i döndür
     */
    public static function getKaynakBadge($kaynak)
    {
        $badges = [
            'manuel' => '<span class="badge bg-soft-primary text-primary">Manuel</span>',
            'puantaj_excel' => '<span class="badge bg-soft-success text-success">Puantaj Excel</span>',
            'puantaj_online' => '<span class="badge bg-soft-info text-info">Puantaj Online</span>',
            'sistem' => '<span class="badge bg-soft-secondary text-secondary">Sistem</span>'
        ];
        return $badges[$kaynak] ?? '<span class="badge bg-soft-dark text-dark">Bilinmiyor</span>';
    }
}
