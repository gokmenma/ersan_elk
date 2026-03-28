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
                   p1.adi_soyadi as personel_adi,
                   p2.adi_soyadi as ilgili_personel_adi
            FROM {$this->table} et
            LEFT JOIN personel p1 ON et.personel_id = p1.id
            LEFT JOIN personel p2 ON et.ilgili_personel_id = p2.id
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
                   p1.adi_soyadi as personel_adi,
                   p2.adi_soyadi as ilgili_personel_adi
            FROM {$this->table} et
            LEFT JOIN personel p1 ON et.personel_id = p1.id
            LEFT JOIN personel p2 ON et.ilgili_personel_id = p2.id
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
                SUM(CASE WHEN evrak_tipi = 'giden' THEN 1 ELSE 0 END) as giden_evrak,
                SUM(CASE WHEN evrak_tipi = 'gelen' AND (cevap_verildi_mi = 0 OR cevap_verildi_mi IS NULL) THEN 1 ELSE 0 END) as cevap_bekleyen
            FROM {$this->table}
            WHERE firma_id = :firma_id
            AND silinme_tarihi IS NULL
        ");
        $sql->execute(['firma_id' => $_SESSION['firma_id']]);
        return $sql->fetch(PDO::FETCH_OBJ);
    }

    /**
     * En yüksek evrak numarasını getirir
     */
    public function getMaxEvrakNo($tip)
    {
        $sql = $this->db->prepare("
            SELECT MAX(evrak_no) as max_no 
            FROM {$this->table} 
            WHERE firma_id = :firma_id 
            AND evrak_tipi = :evrak_tipi
            AND silinme_tarihi IS NULL
        ");
        $sql->execute([
            'firma_id' => $_SESSION['firma_id'],
            'evrak_tipi' => $tip
        ]);
        $row = $sql->fetch(PDO::FETCH_OBJ);
        return ($row->max_no ?? 0) + 1;
    }

    /**
     * Benzersiz evrak konularını getirir
     */
    public function getDistinctKonular()
    {
        $sql = $this->db->prepare("
            SELECT DISTINCT konu 
            FROM {$this->table} 
            WHERE firma_id = :firma_id 
            AND silinme_tarihi IS NULL
            AND konu IS NOT NULL
            AND konu != ''
            ORDER BY konu ASC
        ");
        $sql->execute(['firma_id' => $_SESSION['firma_id']]);
        return $sql->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * İlişkilendirme için tüm gelen evrakları getirir
     */
    public function getGelenEvraklar()
    {
        $sql = $this->db->prepare("
            SELECT id, evrak_no, konu, kurum_adi, tarih 
            FROM {$this->table} 
            WHERE firma_id = :firma_id 
            AND evrak_tipi = 'gelen'
            AND silinme_tarihi IS NULL
            ORDER BY tarih DESC
        ");
        $sql->execute(['firma_id' => $_SESSION['firma_id']]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Evrakı cevaplandı olarak işaretler
     */
    public function markAsReplied($id, $tarih)
    {
        $sql = $this->db->prepare("
            UPDATE {$this->table} 
            SET cevap_verildi_mi = 1, 
                cevap_tarihi = :cevap_tarihi 
            WHERE id = :id 
            AND firma_id = :firma_id
        ");
        return $sql->execute([
            'cevap_tarihi' => $tarih,
            'id' => $id,
            'firma_id' => $_SESSION['firma_id']
        ]);
    }
}
