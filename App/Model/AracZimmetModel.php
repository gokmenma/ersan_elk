<?php

namespace App\Model;

use App\Model\Model;
use App\Helper\Security;
use PDO;

class AracZimmetModel extends Model
{
    protected $table = 'arac_zimmetleri';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /**
     * Tüm zimmet kayıtlarını getirir
     */
    public function all()
    {
        $sql = $this->db->prepare("
            SELECT az.*, 
                   a.plaka, a.marka, a.model,
                   p.adi_soyadi as personel_adi
            FROM {$this->table} az
            INNER JOIN araclar a ON az.arac_id = a.id
            LEFT JOIN personel p ON az.personel_id = p.id
            WHERE az.firma_id = :firma_id
            ORDER BY az.zimmet_tarihi DESC
        ");
        $sql->execute(['firma_id' => $_SESSION['firma_id']]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Tarih aralığına göre zimmet kayıtlarını getirir
     */
    public function getByDateRange($baslangic, $bitis)
    {
        $sql = $this->db->prepare("
            SELECT az.*, 
                   a.plaka, a.marka, a.model,
                   p.adi_soyadi as personel_adi
            FROM {$this->table} az
            INNER JOIN araclar a ON az.arac_id = a.id
            LEFT JOIN personel p ON az.personel_id = p.id
            WHERE az.firma_id = :firma_id
            AND az.zimmet_tarihi BETWEEN :baslangic AND :bitis
            ORDER BY az.zimmet_tarihi DESC
        ");
        $sql->execute([
            'firma_id' => $_SESSION['firma_id'],
            'baslangic' => $baslangic,
            'bitis' => $bitis
        ]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Aktif zimmetleri getirir
     */
    public function getAktifZimmetler()
    {
        $sql = $this->db->prepare("
            SELECT az.*, 
                   a.plaka, a.marka, a.model,
                   p.adi_soyadi as personel_adi
            FROM {$this->table} az
            INNER JOIN araclar a ON az.arac_id = a.id
            INNER JOIN personel p ON az.personel_id = p.id
            WHERE az.firma_id = :firma_id
            AND az.durum = 'aktif'
            ORDER BY az.zimmet_tarihi DESC
        ");
        $sql->execute(['firma_id' => $_SESSION['firma_id']]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Araca ait aktif zimmet var mı kontrolü
     */
    public function getAktifZimmetByArac($aracId)
    {
        $sql = $this->db->prepare("
            SELECT az.*, p.adi_soyadi as personel_adi
            FROM {$this->table} az
            LEFT JOIN personel p ON az.personel_id = p.id
            WHERE az.arac_id = :arac_id
            AND az.durum = 'aktif'
            AND az.firma_id = :firma_id
            ORDER BY az.id DESC
            LIMIT 1
        ");
        $sql->execute([
            'arac_id' => $aracId,
            'firma_id' => $_SESSION['firma_id']
        ]);
        return $sql->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Personele ait aktif zimmet var mı kontrolü
     */
    public function getAktifZimmetByPersonel($personelId)
    {
        $sql = $this->db->prepare("
            SELECT az.*, a.plaka
            FROM {$this->table} az
            INNER JOIN araclar a ON az.arac_id = a.id
            WHERE az.personel_id = :personel_id
            AND az.durum = 'aktif'
            AND az.firma_id = :firma_id
            LIMIT 1
        ");
        $sql->execute([
            'personel_id' => $personelId,
            'firma_id' => $_SESSION['firma_id']
        ]);
        return $sql->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Personele ait aktif zimmetler
     */
    public function getByPersonel($personelId)
    {
        $sql = $this->db->prepare("
            SELECT az.*, 
                   a.plaka, a.marka, a.model
            FROM {$this->table} az
            INNER JOIN araclar a ON az.arac_id = a.id
            WHERE az.personel_id = :personel_id
            AND az.durum = 'aktif'
            AND az.firma_id = :firma_id
            ORDER BY az.zimmet_tarihi DESC
        ");
        $sql->execute([
            'personel_id' => $personelId,
            'firma_id' => $_SESSION['firma_id']
        ]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Zimmet iade et
     */
    public function iadeEt($id, $iadeKm = null, $notlar = null)
    {
        $sql = $this->db->prepare("
            UPDATE {$this->table}
            SET durum = 'iade_edildi',
                iade_tarihi = CURDATE(),
                iade_km = :iade_km,
                notlar = CONCAT(COALESCE(notlar, ''), ' | İade Notu: ', COALESCE(:notlar, '')),
                guncelleme_tarihi = NOW()
            WHERE id = :id
            AND firma_id = :firma_id
        ");
        return $sql->execute([
            'id' => $id,
            'iade_km' => $iadeKm,
            'notlar' => $notlar,
            'firma_id' => $_SESSION['firma_id']
        ]);
    }

    /**
     * Zimmet istatistikleri
     */
    public function getStats()
    {
        $sql = $this->db->prepare("
            SELECT 
                COUNT(*) as toplam_zimmet,
                SUM(CASE WHEN durum = 'aktif' THEN 1 ELSE 0 END) as aktif_zimmet,
                SUM(CASE WHEN durum = 'iade_edildi' THEN 1 ELSE 0 END) as iade_edilen
            FROM {$this->table}
            WHERE firma_id = :firma_id
        ");
        $sql->execute(['firma_id' => $_SESSION['firma_id']]);
        return $sql->fetch(PDO::FETCH_OBJ);
    }
}
