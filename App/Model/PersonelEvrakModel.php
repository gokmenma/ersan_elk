<?php

namespace App\Model;

use PDO;

class PersonelEvrakModel extends Model
{
    protected $table = 'personel_evraklar';
    protected $primaryKey = 'id';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Belirli bir personelin evraklarını getir
     */
    public function getByPersonel($personel_id)
    {
        $sql = "SELECT pe.*, 
                       u.adi_soyadi as yukleyen_adi
                FROM {$this->table} pe
                LEFT JOIN users u ON pe.yukleyen_id = u.id
                WHERE pe.personel_id = :personel_id 
                AND pe.aktif = 1
                ORDER BY pe.yukleme_tarihi DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':personel_id' => $personel_id]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Evrak türüne göre personel evraklarını getir
     */
    public function getByTur($personel_id, $evrak_turu)
    {
        $sql = "SELECT pe.*, 
                       u.adi_soyadi as yukleyen_adi
                FROM {$this->table} pe
                LEFT JOIN users u ON pe.yukleyen_id = u.id
                WHERE pe.personel_id = :personel_id 
                AND pe.evrak_turu = :evrak_turu
                AND pe.aktif = 1
                ORDER BY pe.yukleme_tarihi DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':personel_id' => $personel_id,
            ':evrak_turu' => $evrak_turu
        ]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Evrak detayını getir
     */
    public function getById($id)
    {
        $sql = "SELECT pe.*, 
                       u.adi_soyadi as yukleyen_adi,
                       p.adi_soyadi as personel_adi
                FROM {$this->table} pe
                LEFT JOIN users u ON pe.yukleyen_id = u.id
                LEFT JOIN personel p ON pe.personel_id = p.id
                WHERE pe.id = :id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Evrak istatistiklerini getir
     */
    public function getStats($personel_id)
    {
        $sql = "SELECT 
                    COUNT(*) as toplam_evrak,
                    COUNT(CASE WHEN evrak_turu = 'sozlesme' THEN 1 END) as sozlesme,
                    COUNT(CASE WHEN evrak_turu = 'kimlik' THEN 1 END) as kimlik,
                    COUNT(CASE WHEN evrak_turu = 'diploma' THEN 1 END) as diploma,
                    COUNT(CASE WHEN evrak_turu NOT IN ('sozlesme', 'kimlik', 'diploma') THEN 1 END) as diger
                FROM {$this->table}
                WHERE personel_id = :personel_id AND aktif = 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':personel_id' => $personel_id]);
        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Evrak soft delete
     */
    public function softDeleteEvrak($id)
    {
        $sql = "UPDATE {$this->table} SET aktif = 0 WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
}
