<?php

namespace App\Model;

use App\Model\Model;
use PDO;

class BordroDonemModel extends Model
{
    protected $table = 'bordro_donemi';
    protected $primaryKey = 'id';

    public function __construct()
    {

       /**Session başlatılmamışsa başlat */
       if(!isset($_SESSION)){
           session_start();
       }

       
        parent::__construct($this->table);
    }

    /**
     * Veritabanı bağlantısını döndürür
     */
    public function getDb()
    {
        return $this->db;
    }

    /**
     * Tüm aktif dönemleri getirir (en yeniden en eskiye)
     */
    public function getAllDonems($yil)
    {
        $sql = $this->db->prepare("
            SELECT * FROM {$this->table} 
            WHERE silinme_tarihi IS NULL 
            AND firma_id = ? 
            AND YEAR(baslangic_tarihi) = ? 
            ORDER BY baslangic_tarihi DESC
        ");
        $sql->execute([$_SESSION["firma_id"], $yil]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Dönemlerin yıllana göre yılları getirir
     */
    public function getYearsByDonem()
    {
        $sql = $this->db->prepare(" 
            SELECT DISTINCT YEAR(baslangic_tarihi) AS yil_key, YEAR(baslangic_tarihi) AS yil_val
            FROM {$this->table} 
            WHERE silinme_tarihi IS NULL 
            ORDER BY yil_key DESC
        ");
        $sql->execute();
        return $sql->fetchAll(PDO::FETCH_KEY_PAIR);
    }
    /**
     * Belirli bir dönemi ID ile getirir
     */
    public function getDonemById($id)
    {
        $sql = $this->db->prepare("
            SELECT * FROM {$this->table} 
            WHERE id = ? AND silinme_tarihi IS NULL
        ");
        $sql->execute([$id]);
        return $sql->fetch(PDO::FETCH_OBJ);
    }


    /** Aynı Dönemde başka bir dönem var mı kontrol et */
    public function getDonemByDateRange($baslangic_tarihi, $bitis_tarihi)
    {
        $firma_id = $_SESSION["firma_id"];
        $sql = $this->db->prepare(" 
            SELECT * FROM {$this->table} 
            WHERE silinme_tarihi IS NULL 
            AND firma_id = ? 
            AND baslangic_tarihi BETWEEN ? AND ? 
            OR bitis_tarihi BETWEEN ? AND ?
        ");
        $sql->execute([$firma_id, $baslangic_tarihi, $bitis_tarihi, $baslangic_tarihi, $bitis_tarihi]);
        return $sql->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Yeni dönem oluşturur
     */
    public function createDonem($data)
    {
        $sql = $this->db->prepare("
            INSERT INTO {$this->table} (donem_adi, baslangic_tarihi, bitis_tarihi, olusturma_tarihi) 
            VALUES (:donem_adi, :baslangic_tarihi, :bitis_tarihi, NOW())
        ");
        $sql->bindParam(':donem_adi', $data['donem_adi']);
        $sql->bindParam(':baslangic_tarihi', $data['baslangic_tarihi']);
        $sql->bindParam(':bitis_tarihi', $data['bitis_tarihi']);
        $sql->execute();

        return $this->db->lastInsertId();
    }

    /**
     * Dönemi günceller
     */
    public function updateDonem($id, $data)
    {
        $sql = $this->db->prepare("
            UPDATE {$this->table} 
            SET donem_adi = :donem_adi, 
                baslangic_tarihi = :baslangic_tarihi, 
                bitis_tarihi = :bitis_tarihi
            WHERE id = :id
        ");
        $sql->bindParam(':id', $id);
        $sql->bindParam(':donem_adi', $data['donem_adi']);
        $sql->bindParam(':baslangic_tarihi', $data['baslangic_tarihi']);
        $sql->bindParam(':bitis_tarihi', $data['bitis_tarihi']);
        return $sql->execute();
    }

    /**
     * Dönemi siler (soft delete)
     */
    public function deleteDonem($id)
    {
        return $this->softDelete($id);
    }
}
